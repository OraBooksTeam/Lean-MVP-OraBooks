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
            add_action('wp_ajax_orabooks_fiscal_period_override_reopen', [self::$instance, 'ajax_override_reopen_period']);
            add_action('wp_ajax_nopriv_orabooks_fiscal_period_override_reopen', [self::$instance, 'ajax_override_reopen_period']);
            add_action('wp_ajax_orabooks_fiscal_period_create', [self::$instance, 'ajax_create_period']);
            add_action('wp_ajax_nopriv_orabooks_fiscal_period_create', [self::$instance, 'ajax_create_period']);
            add_action('wp_ajax_orabooks_fiscal_period_update', [self::$instance, 'ajax_update_period']);
            add_action('wp_ajax_nopriv_orabooks_fiscal_period_update', [self::$instance, 'ajax_update_period']);
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

        $db_status = strtolower((string) $row->status);

        return [
            'id'            => (int) $row->id,
            'org_id'        => (int) $row->org_id,
            'period_start'  => $row->period_start,
            'period_end'    => $row->period_end,
            'status'        => $db_status,
            'status_label'  => self::status_label($db_status),
            'closed_by'     => isset($row->closed_by) ? (int) $row->closed_by : null,
            'closed_at'     => $row->closed_at ?? null,
            'reopened_by'   => isset($row->reopened_by) ? (int) $row->reopened_by : null,
            'reopened_at'   => $row->reopened_at ?? null,
            'reopen_reason' => $row->reopen_reason ?? null,
            'can_close'     => $db_status === 'open',
            'can_edit'      => $db_status === 'open',
            'can_reopen'    => $db_status === 'soft_closed',
            'can_override_reopen' => $db_status === 'hard_closed',
        ];
    }

    public static function status_label($status) {
        $map = [
            'open'         => 'Open',
            'soft_closed'  => 'Soft Closed',
            'hard_closed'  => 'Hard Closed',
        ];

        return $map[strtolower((string) $status)] ?? ucwords(str_replace('_', ' ', (string) $status));
    }

    public static function list_periods_for_api($org_id) {
        $rows = self::list_periods($org_id);
        $items = [];

        foreach ($rows ?: [] as $row) {
            $formatted = self::format_period_for_api($row);
            $pending = self::count_pending_transactions($org_id, $row->period_start, $row->period_end);
            $formatted['pending_drafts'] = $pending['draft_journals'];
            $formatted['pending_submitted'] = $pending['submitted_journals'];
            $formatted['pending_total'] = $pending['total'];
            $items[] = $formatted;
        }

        return $items;
    }

    public static function count_pending_transactions($org_id, $period_start, $period_end) {
        global $wpdb;

        $table = OraBooks_Database::table('journals');
        $draft_journals = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE org_id = %d
               AND status = 'draft'
               AND transaction_date >= %s
               AND transaction_date <= %s",
            $org_id,
            $period_start,
            $period_end
        ));
        $submitted_journals = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE org_id = %d
               AND status IN ('submitted', 'review_pending', 'approved')
               AND transaction_date >= %s
               AND transaction_date <= %s",
            $org_id,
            $period_start,
            $period_end
        ));

        return [
            'draft_journals'     => $draft_journals,
            'submitted_journals' => $submitted_journals,
            'total'              => $draft_journals + $submitted_journals,
        ];
    }

    public static function get_period_status($org_id, $transaction_date) {
        global $wpdb;

        $table = OraBooks_Database::table('fiscal_periods');
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$table}
             WHERE org_id = %d AND period_start <= %s AND period_end >= %s
             ORDER BY period_start DESC
             LIMIT 1",
            $org_id,
            $transaction_date,
            $transaction_date
        ));

        return $status ? strtolower((string) $status) : 'open';
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
            if ($status_filter !== '' && strtoupper($formatted['status']) !== $status_filter && $formatted['status'] !== strtolower($status_filter)) {
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

    public static function close_period($period_id, $org_id, $close_type, $user_id, $note = null, $options = []) {
        global $wpdb;

        $close_type = $close_type === 'hard' ? 'hard_closed' : 'soft_closed';
        $period = self::get_period($period_id, $org_id);

        if (!$period) {
            return new WP_Error('not_found', 'Fiscal period not found.');
        }

        if ($period->status !== 'open') {
            return new WP_Error('invalid_status', 'Only open periods can be closed.');
        }

        if ($close_type === 'hard_closed' && empty($options['hard_confirm'])) {
            return new WP_Error('hard_confirm_required', 'Hard close requires explicit confirmation.');
        }

        $pending = self::count_pending_transactions($org_id, $period->period_start, $period->period_end);
        $warnings = [];
        if ($pending['total'] > 0) {
            $warnings[] = sprintf(
                '%d unposted journal(s) remain in this period.',
                $pending['total']
            );
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
            'pending_total' => $pending['total'],
        ], $user_id, $org_id);

        return [
            'success'  => true,
            'status'   => $close_type,
            'warnings' => $warnings,
            'pending'  => $pending,
        ];
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
        $status = self::get_period_status($org_id, $transaction_date);

        if (in_array($status, ['soft_closed', 'hard_closed'], true)) {
            return new WP_Error('fiscal_closed', 'Fiscal period is closed. Cannot post.', ['status' => 409]);
        }

        return true;
    }

    /**
     * Hard-closed periods block reversals; soft-closed periods still allow them.
     */
    public static function can_reverse($org_id, $transaction_date) {
        if (self::get_period_status($org_id, $transaction_date) === 'hard_closed') {
            return new WP_Error('fiscal_hard_closed', 'Hard-closed fiscal period cannot be reversed.', ['status' => 409]);
        }

        return true;
    }

    /**
     * Chart-of-accounts structural edits are blocked once any period is closed.
     */
    public static function can_modify_account_structure($org_id) {
        global $wpdb;

        $table = OraBooks_Database::table('fiscal_periods');
        $closed = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE org_id = %d AND status IN ('soft_closed', 'hard_closed')",
            $org_id
        ));

        if ($closed > 0) {
            return new WP_Error(
                'fiscal_account_locked',
                'Account type and normal balance cannot be changed after a fiscal period has been closed.',
                ['status' => 409]
            );
        }

        return true;
    }

    public static function is_period_hard_closed($org_id, $date) {
        return self::get_period_status($org_id, $date) === 'hard_closed';
    }

    public static function is_period_closed($org_id, $date) {
        return in_array(self::get_period_status($org_id, $date), ['soft_closed', 'hard_closed'], true);
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

        orabooks_json_success(self::list_periods_for_api($org_id));
    }

    public function ajax_close_period() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $period_id = intval($_POST['period_id'] ?? 0);
        $close_type = sanitize_text_field($_POST['close_type'] ?? 'soft');
        $note = sanitize_textarea_field($_POST['note'] ?? '');
        $hard_confirm = !empty($_POST['hard_confirm']);

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_fiscal_periods')) {
            orabooks_json_error('Permission denied', 403);
        }

        $result = self::close_period($period_id, $org_id, $close_type, $user_id, $note, [
            'hard_confirm' => $hard_confirm,
        ]);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 409);
        }

        orabooks_json_success([
            'period_id' => $period_id,
            'status'    => $result['status'],
            'warnings'  => $result['warnings'],
            'pending'   => $result['pending'],
        ]);
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

    public function ajax_override_reopen_period() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $period_id = intval($_POST['period_id'] ?? 0);
        $justification = sanitize_textarea_field($_POST['justification'] ?? '');

        if (!current_user_can('manage_options')) {
            orabooks_json_error('Platform admin permission required', 403);
        }

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }

        $result = self::override_reopen_period($period_id, $org_id, $user_id, $justification);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 409);
        }

        orabooks_json_success(['period_id' => $period_id, 'status' => 'open']);
    }
}
