<?php
/**
 * OraBooks Fiscal Period & Lock Governance (SL-304)
 *
 * Fiscal period lifecycle, close/reopen governance, and posting lock checks.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Fiscal {

    private static $instance = null;

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            add_action('wp_ajax_orabooks_fiscal_periods_list', [self::$instance, 'ajax_list_periods']);
            add_action('wp_ajax_nopriv_orabooks_fiscal_periods_list', [self::$instance, 'ajax_list_periods']);
            add_action('wp_ajax_orabooks_fiscal_period_close', [self::$instance, 'ajax_close_period']);
            add_action('wp_ajax_nopriv_orabooks_fiscal_period_close', [self::$instance, 'ajax_close_period']);
            add_action('wp_ajax_orabooks_fiscal_period_reopen', [self::$instance, 'ajax_reopen_period']);
            add_action('wp_ajax_nopriv_orabooks_fiscal_period_reopen', [self::$instance, 'ajax_reopen_period']);
            add_action('orabooks_monthly_fiscal_period_rollover', [__CLASS__, 'cron_ensure_periods']);
        }
        return self::$instance;
    }

    /**
     * Seed open fiscal periods when a customer org is created.
     */
    public static function ensure_periods_for_org($org_id) {
        global $wpdb;

        $org = OraBooks_Organization::get($org_id);
        if (!$org || $org->organization_type !== 'customer') {
            return;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        self::ensure_month_period($org_id, $now);
        self::ensure_year_period($org_id, (int) $now->format('Y'));
        self::ensure_next_month_period($org_id, $now);
    }

    /**
     * Monthly cron: ensure next month periods exist for all customer orgs.
     */
    public static function cron_ensure_periods() {
        global $wpdb;

        $table = OraBooks_Database::table('organizations');
        $org_ids = $wpdb->get_col(
            "SELECT id FROM {$table} WHERE organization_type = 'customer' AND status = 'active'"
        );

        foreach ($org_ids as $org_id) {
            self::ensure_periods_for_org((int) $org_id);
        }
    }

    public static function list_periods($org_id) {
        global $wpdb;

        $table = OraBooks_Database::table('fiscal_periods');
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, org_id, period_start, period_end, status, closed_by, closed_at,
                    reopened_by, reopened_at, reopen_reason
             FROM {$table}
             WHERE org_id = %d
             ORDER BY period_start DESC",
            $org_id
        ));
    }

    public static function format_period_for_api($row) {
        if (!$row) {
            return [];
        }

        $map = [
            'open'         => 'OPEN',
            'soft_closed'  => 'SOFT_CLOSED',
            'hard_closed'  => 'HARD_CLOSED',
        ];
        $db_status = strtolower((string) $row->status);

        return [
            'id'            => (int) $row->id,
            'org_id'        => (int) $row->org_id,
            'period_start'  => $row->period_start,
            'period_end'    => $row->period_end,
            'status'        => $map[$db_status] ?? strtoupper($db_status),
            'closed_by'     => isset($row->closed_by) ? (int) $row->closed_by : null,
            'closed_at'     => $row->closed_at ?? null,
            'reopened_by'   => isset($row->reopened_by) ? (int) $row->reopened_by : null,
            'reopened_at'   => $row->reopened_at ?? null,
            'reopen_reason' => $row->reopen_reason ?? null,
        ];
    }

    public static function paginate_periods($org_id, array $args = []) {
        $rows = self::list_periods($org_id);
        $status_filter = strtoupper(sanitize_text_field($args['status'] ?? ''));
        $year = (int) ($args['year'] ?? 0);
        $month = (int) ($args['month'] ?? 0);
        $page = max(1, (int) ($args['page'] ?? 1));
        $per_page = min(100, max(1, (int) ($args['per_page'] ?? 20)));

        $filtered = [];
        foreach ($rows ?: [] as $row) {
            $formatted = self::format_period_for_api($row);
            if ($status_filter !== '' && $formatted['status'] !== $status_filter) {
                continue;
            }
            if ($year > 0 && (int) substr((string) $row->period_start, 0, 4) !== $year) {
                continue;
            }
            if ($month > 0 && (int) substr((string) $row->period_start, 5, 2) !== $month) {
                continue;
            }
            $filtered[] = $formatted;
        }

        $total = count($filtered);
        $offset = ($page - 1) * $per_page;

        return [
            'items'    => array_slice($filtered, $offset, $per_page),
            'page'     => $page,
            'per_page' => $per_page,
            'total'    => $total,
        ];
    }

    public static function create_period($org_id, $period_start, $period_end, $period_name = '') {
        global $wpdb;

        $period_start = sanitize_text_field($period_start);
        $period_end = sanitize_text_field($period_end);
        if ($period_start === '' || $period_end === '') {
            return new WP_Error('invalid_period', 'period_start and period_end are required.');
        }
        if ($period_start > $period_end) {
            return new WP_Error('invalid_period', 'period_start must be before period_end.');
        }

        $table = OraBooks_Database::table('fiscal_periods');
        $overlap = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE org_id = %d AND period_start <= %s AND period_end >= %s
             LIMIT 1",
            $org_id,
            $period_end,
            $period_start
        ));

        if ($overlap) {
            return new WP_Error('duplicate_period', 'Overlapping fiscal period already exists.');
        }

        $inserted = $wpdb->insert($table, [
            'org_id'       => (int) $org_id,
            'period_start' => $period_start,
            'period_end'   => $period_end,
            'status'       => 'open',
        ], ['%d', '%s', '%s', '%s']);

        if (!$inserted) {
            return new WP_Error('db_error', 'Failed to create fiscal period.');
        }

        return (int) $wpdb->insert_id;
    }

    public static function override_reopen_period($period_id, $org_id, $user_id, $justification) {
        global $wpdb;

        $justification = trim((string) $justification);
        if ($justification === '') {
            return new WP_Error('justification_required', 'Mandatory justification is required.');
        }

        $period = self::get_period($period_id, $org_id);
        if (!$period) {
            return new WP_Error('not_found', 'Fiscal period not found.');
        }

        if ($period->status !== 'hard_closed') {
            return new WP_Error('invalid_status', 'Only hard-closed periods can be override-reopened.');
        }

        $table = OraBooks_Database::table('fiscal_periods');
        $updated = $wpdb->update(
            $table,
            [
                'status'        => 'open',
                'reopened_by'   => $user_id,
                'reopened_at'   => current_time('mysql', true),
                'reopen_reason' => $justification,
            ],
            ['id' => $period_id, 'org_id' => $org_id],
            ['%s', '%d', '%s', '%s'],
            ['%d', '%d']
        );

        if ($updated === false) {
            return new WP_Error('update_failed', 'Failed to override-reopen fiscal period.');
        }

        orabooks_log_event('period_override_reopened', "Fiscal period {$period->period_start} override-reopened", 'warning', [
            'period_id'     => $period_id,
            'justification' => $justification,
        ], $user_id, $org_id);

        return true;
    }

    public static function get_period($period_id, $org_id) {
        global $wpdb;

        $table = OraBooks_Database::table('fiscal_periods');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND org_id = %d",
            $period_id,
            $org_id
        ));
    }

    public static function close_period($period_id, $org_id, $close_type, $user_id, $note = null) {
        global $wpdb;

        $close_type = $close_type === 'hard' ? 'hard_closed' : 'soft_closed';
        $period = self::get_period($period_id, $org_id);

        if (!$period) {
            return new WP_Error('not_found', 'Fiscal period not found.');
        }

        if ($period->status !== 'open') {
            return new WP_Error('invalid_status', 'Only open periods can be closed.');
        }

        $table = OraBooks_Database::table('fiscal_periods');
        $updated = $wpdb->update(
            $table,
            [
                'status' => $close_type,
                'closed_by' => $user_id,
                'closed_at' => current_time('mysql', true),
            ],
            ['id' => $period_id, 'org_id' => $org_id],
            ['%s', '%d', '%s'],
            ['%d', '%d']
        );

        if ($updated === false) {
            return new WP_Error('update_failed', 'Failed to close fiscal period.');
        }

        orabooks_log_event('period_closed', "Fiscal period {$period->period_start} closed ({$close_type})", 'info', [
            'period_id' => $period_id,
            'close_type' => $close_type,
            'note' => $note,
        ], $user_id, $org_id);

        return true;
    }

    public static function reopen_period($period_id, $org_id, $user_id, $reason) {
        global $wpdb;

        $reason = trim((string) $reason);
        if ($reason === '') {
            return new WP_Error('reason_required', 'A reason is required to reopen a fiscal period.');
        }

        $period = self::get_period($period_id, $org_id);
        if (!$period) {
            return new WP_Error('not_found', 'Fiscal period not found.');
        }

        if ($period->status === 'hard_closed') {
            return new WP_Error('hard_closed', 'Hard-closed periods cannot be reopened.');
        }

        if ($period->status !== 'soft_closed') {
            return new WP_Error('invalid_status', 'Only soft-closed periods can be reopened.');
        }

        $table = OraBooks_Database::table('fiscal_periods');
        $updated = $wpdb->update(
            $table,
            [
                'status' => 'open',
                'reopened_by' => $user_id,
                'reopened_at' => current_time('mysql', true),
                'reopen_reason' => $reason,
            ],
            ['id' => $period_id, 'org_id' => $org_id],
            ['%s', '%d', '%s', '%s'],
            ['%d', '%d']
        );

        if ($updated === false) {
            return new WP_Error('update_failed', 'Failed to reopen fiscal period.');
        }

        orabooks_log_event('period_reopened', "Fiscal period {$period->period_start} reopened", 'info', [
            'period_id' => $period_id,
            'reason' => $reason,
        ], $user_id, $org_id);

        return true;
    }

    /**
     * Check whether posting is allowed for a transaction date.
     */
    public static function can_post($org_id, $transaction_date) {
        global $wpdb;

        $table = OraBooks_Database::table('fiscal_periods');
        $fiscal = $wpdb->get_row($wpdb->prepare(
            "SELECT status FROM {$table}
             WHERE org_id = %d AND period_start <= %s AND period_end >= %s
             ORDER BY period_start DESC
             LIMIT 1",
            $org_id,
            $transaction_date,
            $transaction_date
        ));

        if ($fiscal && in_array($fiscal->status, ['soft_closed', 'hard_closed'], true)) {
            return new WP_Error('fiscal_closed', 'Fiscal period is closed. Cannot post.');
        }

        return true;
    }

    public static function is_period_hard_closed($org_id, $date) {
        global $wpdb;

        $table = OraBooks_Database::table('fiscal_periods');
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$table}
             WHERE org_id = %d AND period_start <= %s AND period_end >= %s
             ORDER BY period_start DESC
             LIMIT 1",
            $org_id,
            $date,
            $date
        ));

        return $status === 'hard_closed';
    }

    private static function ensure_month_period($org_id, DateTimeImmutable $date) {
        $start = $date->modify('first day of this month')->format('Y-m-d');
        $end = $date->modify('last day of this month')->format('Y-m-d');
        self::insert_period_if_missing($org_id, $start, $end);
    }

    private static function ensure_next_month_period($org_id, DateTimeImmutable $date) {
        $next = $date->modify('first day of next month');
        $start = $next->format('Y-m-d');
        $end = $next->modify('last day of this month')->format('Y-m-d');
        self::insert_period_if_missing($org_id, $start, $end);
    }

    private static function ensure_year_period($org_id, $year) {
        $start = sprintf('%04d-01-01', $year);
        $end = sprintf('%04d-12-31', $year);
        self::insert_period_if_missing($org_id, $start, $end);
    }

    private static function insert_period_if_missing($org_id, $period_start, $period_end) {
        global $wpdb;

        $table = OraBooks_Database::table('fiscal_periods');
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table} (org_id, period_start, period_end, status)
             VALUES (%d, %s, %s, 'open')",
            $org_id,
            $period_start,
            $period_end
        ));
    }

    public function ajax_list_periods() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_GET['org_id'] ?? $_POST['org_id'] ?? 0);

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_fiscal_periods')) {
            orabooks_json_error('Permission denied', 403);
        }

        orabooks_json_success(self::list_periods($org_id));
    }

    public function ajax_close_period() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $period_id = intval($_POST['period_id'] ?? 0);
        $close_type = sanitize_text_field($_POST['close_type'] ?? 'soft');
        $note = sanitize_textarea_field($_POST['note'] ?? '');

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_fiscal_periods')) {
            orabooks_json_error('Permission denied', 403);
        }

        $result = self::close_period($period_id, $org_id, $close_type, $user_id, $note);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 409);
        }

        orabooks_json_success(['period_id' => $period_id, 'status' => $close_type === 'hard' ? 'hard_closed' : 'soft_closed']);
    }

    public function ajax_reopen_period() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $period_id = intval($_POST['period_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_fiscal_periods')) {
            orabooks_json_error('Permission denied', 403);
        }

        $result = self::reopen_period($period_id, $org_id, $user_id, $reason);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 409);
        }

        orabooks_json_success(['period_id' => $period_id, 'status' => 'open']);
    }
}
