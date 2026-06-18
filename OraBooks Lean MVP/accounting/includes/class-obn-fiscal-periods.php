<?php
/**
 * SL-304 Fiscal Period & Lock Governance.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OBN_Fiscal_Period_Status {
    const OPEN = 'OPEN';
    const SOFT_CLOSED = 'SOFT_CLOSED';
    const HARD_CLOSED = 'HARD_CLOSED';

    public static function all() {
        return [self::OPEN, self::SOFT_CLOSED, self::HARD_CLOSED];
    }
}

class OBN_Fiscal_Period {
    private $id;
    private $organization_id;
    private $period_type;
    private $period_name;
    private $start_date;
    private $end_date;
    private $status;
    private $closed_by;
    private $closed_at;
    private $reopened_by;
    private $reopened_at;
    private $reopen_reason;

    public function __construct($row) {
        $this->id = isset($row->id) ? intval($row->id) : 0;
        $this->organization_id = intval($row->org_id ?? 0);
        $this->period_type = (string) ($row->period_type ?? 'MONTH');
        $this->period_name = (string) ($row->period_name ?? '');
        $this->start_date = (string) ($row->period_start ?? '');
        $this->end_date = (string) ($row->period_end ?? '');
        $this->status = (string) ($row->status ?? OBN_Fiscal_Period_Status::OPEN);
        $this->closed_by = $row->closed_by ?? null;
        $this->closed_at = $row->closed_at ?? null;
        $this->reopened_by = $row->reopened_by ?? null;
        $this->reopened_at = $row->reopened_at ?? null;
        $this->reopen_reason = $row->reopen_reason ?? null;
    }

    public function id() { return $this->id; }
    public function organization_id() { return $this->organization_id; }
    public function status() { return $this->status; }
    public function start_date() { return $this->start_date; }
    public function end_date() { return $this->end_date; }

    public function can_post() {
        return $this->status === OBN_Fiscal_Period_Status::OPEN;
    }

    public function can_reverse() {
        return $this->status === OBN_Fiscal_Period_Status::OPEN;
    }

    public function validate_transition($new_status, $is_super_admin = false) {
        if (!in_array($new_status, OBN_Fiscal_Period_Status::all(), true)) {
            return new WP_Error('invalid_status', 'Invalid fiscal period status.');
        }

        if ($this->status === $new_status) {
            return new WP_Error('invalid_transition', 'Fiscal period is already in the requested status.');
        }

        $allowed = [
            OBN_Fiscal_Period_Status::OPEN => [
                OBN_Fiscal_Period_Status::SOFT_CLOSED,
                OBN_Fiscal_Period_Status::HARD_CLOSED,
            ],
            OBN_Fiscal_Period_Status::SOFT_CLOSED => [
                OBN_Fiscal_Period_Status::OPEN,
                OBN_Fiscal_Period_Status::HARD_CLOSED,
            ],
            OBN_Fiscal_Period_Status::HARD_CLOSED => $is_super_admin ? [
                OBN_Fiscal_Period_Status::OPEN,
            ] : [],
        ];

        if (!in_array($new_status, $allowed[$this->status] ?? [], true)) {
            return new WP_Error('invalid_transition', 'Illegal fiscal period status transition.');
        }

        return true;
    }

    public function close_soft() {
        return $this->validate_transition(OBN_Fiscal_Period_Status::SOFT_CLOSED);
    }

    public function close_hard() {
        return $this->validate_transition(OBN_Fiscal_Period_Status::HARD_CLOSED);
    }

    public function reopen($is_super_admin = false) {
        return $this->validate_transition(OBN_Fiscal_Period_Status::OPEN, $is_super_admin);
    }
}

class OBN_Fiscal_Period_Policy {
    public static function current_org_id() {
        if ( class_exists( 'OBN_Lean_MVP_Bridge' ) ) {
            return OBN_Lean_MVP_Bridge::current_org_id();
        }

        return (int) get_current_blog_id();
    }

    public static function is_super_admin() {
        return is_multisite() ? is_super_admin(get_current_user_id()) : current_user_can('manage_options');
    }

    public static function user_role_name($user_id = null) {
        $user_id = $user_id ?: get_current_user_id();
        if (!$user_id) {
            return '';
        }
        if (user_can($user_id, 'manage_options')) {
            return 'Admin';
        }

        global $wpdb;
        $emp_table = $wpdb->prefix . 'orabooks_ac_employees';
        $roles_table = $wpdb->prefix . 'orabooks_ac_roles';
        $role = $wpdb->get_var($wpdb->prepare(
            "SELECT r.role_name FROM $emp_table e LEFT JOIN $roles_table r ON e.role_id = r.id WHERE e.wp_user_id = %d AND e.status = 1 LIMIT 1",
            $user_id
        ));

        return is_string($role) ? $role : '';
    }

    public static function can_view() {
        if (!is_user_logged_in()) {
            return false;
        }
        if (current_user_can('manage_options')) {
            return true;
        }
        if (class_exists('OBN_Accounting_Permissions') && OBN_Accounting_Permissions::has_view_permission('fiscal-periods')) {
            return true;
        }
        $auth = class_exists('OBN_Auth') ? new OBN_Auth() : null;
        return $auth ? $auth->can_access_accounting() : false;
    }

    public static function can_close() {
        if (!self::can_view()) {
            return false;
        }
        return in_array(self::user_role_name(), ['Owner', 'Admin'], true) || current_user_can('manage_options');
    }

    public static function can_reopen() {
        return self::can_close();
    }

    public static function can_override() {
        return self::is_super_admin();
    }
}

class OBN_Fiscal_Period_Repository {
    private $wpdb;
    private $table;
    private $audit_table;

    public function __construct($wpdb_instance = null) {
        if (!$wpdb_instance) {
            global $wpdb;
            $wpdb_instance = $wpdb;
        }
        $this->wpdb = $wpdb_instance;
        $this->table = $this->wpdb->prefix . 'fiscal_periods';
        $this->audit_table = $this->wpdb->prefix . 'orabooks_ac_audit_events';
    }

    public function table() {
        return $this->table;
    }

    public function install_schema() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql_periods = "CREATE TABLE {$this->table} (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `org_id` bigint(20) unsigned NOT NULL,
            `period_type` varchar(20) NOT NULL DEFAULT 'MONTH',
            `period_name` varchar(100) NOT NULL,
            `period_start` date NOT NULL,
            `period_end` date NOT NULL,
            `status` varchar(20) NOT NULL DEFAULT 'OPEN',
            `closed_by` bigint(20) unsigned DEFAULT NULL,
            `closed_at` datetime DEFAULT NULL,
            `reopened_by` bigint(20) unsigned DEFAULT NULL,
            `reopened_at` datetime DEFAULT NULL,
            `reopen_reason` text DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY org_type_period_start (org_id, period_type, period_start),
            KEY org_id (org_id),
            KEY org_status (org_id, status),
            KEY org_period_start_idx (org_id, period_start),
            KEY org_period_end_idx (org_id, period_end)
        ) ENGINE=InnoDB {$charset_collate};";

        $sql_audit = "CREATE TABLE {$this->audit_table} (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `org_id` bigint(20) unsigned NOT NULL,
            `event_type` varchar(80) NOT NULL,
            `entity_type` varchar(80) NOT NULL,
            `entity_id` bigint(20) unsigned NOT NULL,
            `user_id` bigint(20) unsigned DEFAULT NULL,
            `old_value` longtext DEFAULT NULL,
            `new_value` longtext DEFAULT NULL,
            `reason` text DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY org_event (org_id, event_type),
            KEY entity_lookup (entity_type, entity_id),
            KEY created_at (created_at)
        ) ENGINE=InnoDB {$charset_collate};";

        dbDelta($sql_periods . "\n" . $sql_audit);

        $legacy_unique = $this->wpdb->get_var($this->wpdb->prepare(
            "SHOW INDEX FROM {$this->table} WHERE Key_name = %s",
            'org_period_start'
        ));
        if ($legacy_unique) {
            $this->wpdb->query("ALTER TABLE {$this->table} DROP INDEX org_period_start");
        }
    }

    public function find($id, $org_id) {
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d AND org_id = %d LIMIT 1",
            $id,
            $org_id
        ));
        return $row ? new OBN_Fiscal_Period($row) : null;
    }

    public function get_row($id, $org_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT fp.*, cb.display_name AS closed_by_name, rb.display_name AS reopened_by_name
             FROM {$this->table} fp
             LEFT JOIN {$this->wpdb->users} cb ON fp.closed_by = cb.ID
             LEFT JOIN {$this->wpdb->users} rb ON fp.reopened_by = rb.ID
             WHERE fp.id = %d AND fp.org_id = %d LIMIT 1",
            $id,
            $org_id
        ), ARRAY_A);
    }

    public function paginate($org_id, $filters) {
        $page = max(1, intval($filters['page'] ?? 1));
        $per_page = min(100, max(1, intval($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $per_page;
        $where = ['fp.org_id = %d'];
        $params = [$org_id];

        if (!empty($filters['status']) && in_array($filters['status'], OBN_Fiscal_Period_Status::all(), true)) {
            $where[] = 'fp.status = %s';
            $params[] = $filters['status'];
        }
        if (!empty($filters['year'])) {
            $where[] = 'YEAR(fp.period_start) = %d';
            $params[] = intval($filters['year']);
        }
        if (!empty($filters['month'])) {
            $where[] = 'MONTH(fp.period_start) = %d';
            $params[] = intval($filters['month']);
        }

        $where_sql = implode(' AND ', $where);
        $count_sql = $this->wpdb->prepare("SELECT COUNT(*) FROM {$this->table} fp WHERE $where_sql", $params);
        $total = intval($this->wpdb->get_var($count_sql));

        $list_params = array_merge($params, [$per_page, $offset]);
        $list_sql = $this->wpdb->prepare(
            "SELECT fp.*, cb.display_name AS closed_by_name, rb.display_name AS reopened_by_name
             FROM {$this->table} fp
             LEFT JOIN {$this->wpdb->users} cb ON fp.closed_by = cb.ID
             LEFT JOIN {$this->wpdb->users} rb ON fp.reopened_by = rb.ID
             WHERE $where_sql
             ORDER BY fp.period_start DESC
             LIMIT %d OFFSET %d",
            $list_params
        );

        return [
            'data' => $this->wpdb->get_results($list_sql, ARRAY_A),
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 0,
        ];
    }

    public function find_by_date($org_id, $transaction_date) {
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE org_id = %d AND period_start <= %s AND period_end >= %s
             ORDER BY period_start DESC LIMIT 1",
            $org_id,
            $transaction_date,
            $transaction_date
        ));
        return $row ? new OBN_Fiscal_Period($row) : null;
    }

    public function find_all_by_date($org_id, $transaction_date) {
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE org_id = %d AND period_start <= %s AND period_end >= %s
             ORDER BY
                CASE period_type
                    WHEN 'FISCAL_YEAR' THEN 1
                    WHEN 'QUARTER' THEN 2
                    WHEN 'MONTH' THEN 3
                    ELSE 4
                END ASC,
                period_start DESC",
            $org_id,
            $transaction_date,
            $transaction_date
        ));

        return array_map(function ($row) {
            return new OBN_Fiscal_Period($row);
        }, $rows ?: []);
    }

    public function has_overlap($org_id, $start_date, $end_date, $ignore_id = 0, $period_type = '') {
        $period_type = $period_type ? strtoupper(sanitize_text_field($period_type)) : '';
        $sql = "SELECT id FROM {$this->table}
            WHERE org_id = %d AND period_start <= %s AND period_end >= %s AND id != %d";
        $params = [$org_id, $end_date, $start_date, $ignore_id];

        if ($period_type !== '') {
            $sql .= " AND period_type = %s";
            $params[] = $period_type;
        }

        $sql .= "
            LIMIT 1";
        return (bool) $this->wpdb->get_var($this->wpdb->prepare($sql, $params));
    }

    public function create($data) {
        $org_id = intval($data['org_id']);
        $start = sanitize_text_field($data['period_start']);
        $end = sanitize_text_field($data['period_end']);
        $type = strtoupper(sanitize_text_field($data['period_type'] ?? 'MONTH'));

        if (!$org_id || empty($start) || empty($end) || strtotime($start) > strtotime($end)) {
            return new WP_Error('invalid_period', 'A valid organization, start date, and end date are required.');
        }
        if ($this->has_overlap($org_id, $start, $end, 0, $type)) {
            return new WP_Error('period_overlap', 'Fiscal period overlaps an existing period in this organization.');
        }

        $inserted = $this->wpdb->insert($this->table, [
            'org_id' => $org_id,
            'period_type' => $type,
            'period_name' => sanitize_text_field($data['period_name']),
            'period_start' => $start,
            'period_end' => $end,
            'status' => OBN_Fiscal_Period_Status::OPEN,
        ], ['%d', '%s', '%s', '%s', '%s', '%s']);

        if (!$inserted) {
            return new WP_Error('period_create_failed', 'Failed to create fiscal period.');
        }

        return intval($this->wpdb->insert_id);
    }

    public function transition($period, $new_status, $reason = '', $is_override = false) {
        $validation = $period->validate_transition($new_status, $is_override);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $old_status = $period->status();
        $now = current_time('mysql');
        $user_id = get_current_user_id();
        $data = ['status' => $new_status, 'updated_at' => $now];
        $formats = ['%s', '%s'];

        if ($new_status === OBN_Fiscal_Period_Status::OPEN) {
            if ($old_status === OBN_Fiscal_Period_Status::SOFT_CLOSED && trim($reason) === '') {
                return new WP_Error('reason_required', 'Reopen reason is mandatory.');
            }
            if ($old_status === OBN_Fiscal_Period_Status::HARD_CLOSED && !$is_override) {
                return new WP_Error('override_required', 'Hard closed periods require Super Admin override.');
            }
            $data['reopened_by'] = $user_id;
            $data['reopened_at'] = $now;
            $data['reopen_reason'] = sanitize_textarea_field($reason);
            $formats = array_merge($formats, ['%d', '%s', '%s']);
        } else {
            $data['closed_by'] = $user_id;
            $data['closed_at'] = $now;
            $formats = array_merge($formats, ['%d', '%s']);
        }

        $this->wpdb->query('START TRANSACTION');
        $updated = $this->wpdb->update(
            $this->table,
            $data,
            ['id' => $period->id(), 'org_id' => $period->organization_id(), 'status' => $old_status],
            $formats,
            ['%d', '%d', '%s']
        );

        if ($updated === false || $updated === 0) {
            $this->wpdb->query('ROLLBACK');
            return new WP_Error('period_transition_failed', 'Fiscal period status changed or could not be updated.');
        }

        $event = $this->event_type($old_status, $new_status, $is_override);
        $audit = $this->append_audit($period->organization_id(), $period->id(), $event, $old_status, $new_status, $reason);
        if (is_wp_error($audit)) {
            $this->wpdb->query('ROLLBACK');
            return $audit;
        }

        $this->wpdb->query('COMMIT');
        return true;
    }

    private function event_type($old_status, $new_status, $is_override) {
        if ($is_override) {
            return 'period_override_reopened';
        }
        if ($new_status === OBN_Fiscal_Period_Status::HARD_CLOSED) {
            return 'period_hard_closed';
        }
        if ($new_status === OBN_Fiscal_Period_Status::SOFT_CLOSED) {
            return 'period_closed';
        }
        return 'period_reopened';
    }

    private function append_audit($org_id, $period_id, $event_type, $old_status, $new_status, $reason) {
        $inserted = $this->wpdb->insert($this->audit_table, [
            'org_id' => $org_id,
            'event_type' => $event_type,
            'entity_type' => 'FiscalPeriod',
            'entity_id' => $period_id,
            'user_id' => get_current_user_id(),
            'old_value' => wp_json_encode(['status' => $old_status]),
            'new_value' => wp_json_encode(['status' => $new_status]),
            'reason' => sanitize_textarea_field($reason),
        ], ['%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s']);

        return $inserted ? true : new WP_Error('audit_failed', 'Failed to create immutable fiscal period audit event.');
    }

    public function ensure_default_open_periods($org_id = null) {
        $org_id = $org_id ? intval($org_id) : OBN_Fiscal_Period_Policy::current_org_id();
        $today = current_time('Y-m-d');
        $month_start = date('Y-m-01', strtotime($today));
        $month_end = date('Y-m-t', strtotime($today));
        $year_start = date('Y-01-01', strtotime($today));
        $year_end = date('Y-12-31', strtotime($today));

        $this->create_if_missing($org_id, 'MONTH', date('F Y', strtotime($today)), $month_start, $month_end);
        $this->create_if_missing($org_id, 'FISCAL_YEAR', 'FY ' . date('Y', strtotime($today)), $year_start, $year_end);
    }

    public function create_if_missing($org_id, $period_type, $period_name, $start_date, $end_date) {
        if ($this->has_overlap($org_id, $start_date, $end_date, 0, $period_type)) {
            return true;
        }

        return $this->create([
            'org_id' => $org_id,
            'period_type' => $period_type,
            'period_name' => $period_name,
            'period_start' => $start_date,
            'period_end' => $end_date,
        ]);
    }
}

class OBN_Fiscal_Period_Posting_Guard {
    const LOCKED_MESSAGE = 'Fiscal period is locked. Only report and list views are allowed.';

    public static function can_post($org_id, $transaction_date) {
        if (class_exists('OBN_Fiscal_Adapter')) {
            return OBN_Fiscal_Adapter::can_post($org_id, $transaction_date);
        }

        if (class_exists('OraBooks_Fiscal') && method_exists('OraBooks_Fiscal', 'can_post')) {
            $result = OraBooks_Fiscal::can_post((int) $org_id, $transaction_date);
            if (is_wp_error($result)) {
                return new WP_Error('fiscal_period_locked', $result->get_error_message(), ['status' => 409]);
            }
            return true;
        }

        return self::can_post_legacy($org_id, $transaction_date);
    }

    public static function can_post_legacy($org_id, $transaction_date) {
        $repo = new OBN_Fiscal_Period_Repository();
        $periods = $repo->find_all_by_date($org_id, $transaction_date);

        if (empty($periods)) {
            return new WP_Error('fiscal_period_missing', 'No fiscal period exists for the transaction date.', ['status' => 409]);
        }

        foreach ($periods as $period) {
            if ($period->status() === OBN_Fiscal_Period_Status::HARD_CLOSED) {
                return new WP_Error('fiscal_period_locked', self::LOCKED_MESSAGE, ['status' => 409]);
            }
        }

        foreach ($periods as $period) {
            if ($period->status() === OBN_Fiscal_Period_Status::SOFT_CLOSED) {
                return new WP_Error('fiscal_period_locked', self::LOCKED_MESSAGE, ['status' => 409]);
            }
        }

        return true;
    }

    public static function assert_can_post_or_fail($transaction_date, $org_id = null) {
        $org_id = $org_id ?: obn_current_org_id();
        $allowed = self::can_post($org_id, $transaction_date);

        if (is_wp_error($allowed)) {
            wp_send_json_error(['message' => $allowed->get_error_message()]);
        }

        return true;
    }

    public static function can_modify($org_id, $transaction_date) {
        $posting_allowed = self::can_post($org_id, $transaction_date);
        if ($posting_allowed === true) {
            return true;
        }

        if (is_wp_error($posting_allowed) && $posting_allowed->get_error_code() === 'fiscal_period_locked') {
            return new WP_Error('fiscal_period_locked', self::LOCKED_MESSAGE, ['status' => 409]);
        }

        return new WP_Error('fiscal_period_locked', self::LOCKED_MESSAGE, ['status' => 409]);
    }

    public static function can_modify_journal_entry($journal_entry_id) {
        global $wpdb;
        $je_table = $wpdb->prefix . 'orabooks_ac_journal_entry';
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT organization_id, store_id, entry_date FROM $je_table WHERE id = %d",
            $journal_entry_id
        ));

        if (!$entry) {
            return true;
        }

        $org_id = intval($entry->organization_id ?: $entry->store_id ?: obn_current_org_id());
        return self::can_modify($org_id, $entry->entry_date);
    }

    public static function assert_journal_entry_modifiable_or_fail($journal_entry_id) {
        $allowed = self::can_modify_journal_entry($journal_entry_id);
        if (is_wp_error($allowed)) {
            wp_send_json_error(['message' => $allowed->get_error_message()]);
        }
        return true;
    }
}

class OBN_Fiscal_Periods {
    const SCHEMA_VERSION = '1.0.0';

    public static function init() {
        add_action('init', [__CLASS__, 'maybe_install_schema'], 8);
        add_action('init', [__CLASS__, 'ensure_default_open_periods'], 30);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        add_action('wp_ajax_obn_fiscal_period_create', [__CLASS__, 'ajax_create_period']);
        add_action('wp_ajax_obn_fiscal_period_close', [__CLASS__, 'ajax_close_period']);
        add_action('wp_ajax_obn_fiscal_period_reopen', [__CLASS__, 'ajax_reopen_period']);
        add_action('wp_ajax_obn_fiscal_period_override_reopen', [__CLASS__, 'ajax_override_reopen_period']);
        add_action('obn_fiscal_period_monthly_job', [__CLASS__, 'generate_next_month_period']);
        add_action('obn_fiscal_period_yearly_job', [__CLASS__, 'generate_next_fiscal_year_period']);
        self::schedule_jobs();
    }

    public static function maybe_install_schema() {
        if (get_option('obn_fiscal_period_schema_version') === self::SCHEMA_VERSION) {
            return;
        }
        $repo = new OBN_Fiscal_Period_Repository();
        $repo->install_schema();
        update_option('obn_fiscal_period_schema_version', self::SCHEMA_VERSION, false);
    }

    public static function schedule_jobs() {
        if (!wp_next_scheduled('obn_fiscal_period_monthly_job')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'obn_fiscal_period_monthly_job');
        }
        if (!wp_next_scheduled('obn_fiscal_period_yearly_job')) {
            wp_schedule_event(time() + (2 * HOUR_IN_SECONDS), 'daily', 'obn_fiscal_period_yearly_job');
        }
    }

    public static function register_rest_routes() {
        register_rest_route('api', '/fiscal-periods', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'rest_list_periods'],
                'permission_callback' => [__CLASS__, 'rest_can_view'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [__CLASS__, 'rest_create_period'],
                'permission_callback' => [__CLASS__, 'rest_can_close'],
            ],
        ]);

        register_rest_route('api', '/fiscal-periods/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'rest_get_period'],
            'permission_callback' => [__CLASS__, 'rest_can_view'],
        ]);

        register_rest_route('api', '/fiscal-periods/(?P<id>\d+)/close', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'rest_close_period'],
            'permission_callback' => [__CLASS__, 'rest_can_close'],
        ]);

        register_rest_route('api', '/fiscal-periods/(?P<id>\d+)/reopen', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'rest_reopen_period'],
            'permission_callback' => [__CLASS__, 'rest_can_reopen'],
        ]);

        register_rest_route('api', '/fiscal-periods/(?P<id>\d+)/override-reopen', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'rest_override_reopen_period'],
            'permission_callback' => [__CLASS__, 'rest_can_override'],
        ]);

        self::register_legacy_rest_routes();
    }

    private static function register_legacy_rest_routes() {
        register_rest_route('api', '/fiscal/periods', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'rest_list_periods'],
                'permission_callback' => [__CLASS__, 'rest_can_view'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [__CLASS__, 'rest_create_period'],
                'permission_callback' => [__CLASS__, 'rest_can_close'],
            ],
        ]);

        foreach ([
            '/fiscal/periods/(?P<id>\d+)' => 'rest_get_period',
            '/fiscal/periods/(?P<id>\d+)/close' => 'rest_close_period',
            '/fiscal/periods/(?P<id>\d+)/reopen' => 'rest_reopen_period',
            '/fiscal/periods/(?P<id>\d+)/override-reopen' => 'rest_override_reopen_period',
        ] as $route => $callback) {
            $permission_callback = $callback === 'rest_get_period' ? 'rest_can_view' : ($callback === 'rest_override_reopen_period' ? 'rest_can_override' : 'rest_can_close');
            register_rest_route('api', $route, [
                'methods' => $callback === 'rest_get_period' ? WP_REST_Server::READABLE : WP_REST_Server::CREATABLE,
                'callback' => [__CLASS__, $callback],
                'permission_callback' => [__CLASS__, $permission_callback],
            ]);
        }
    }

    public static function rest_can_view() {
        return OBN_Fiscal_Period_Policy::can_view();
    }

    public static function rest_can_close() {
        return OBN_Fiscal_Period_Policy::can_close();
    }

    public static function rest_can_reopen() {
        return OBN_Fiscal_Period_Policy::can_reopen();
    }

    public static function rest_can_override() {
        return OBN_Fiscal_Period_Policy::can_override();
    }

    public static function rest_list_periods(WP_REST_Request $request) {
        $repo = new OBN_Fiscal_Period_Repository();
        $status = strtoupper(sanitize_text_field($request->get_param('status')));
        return rest_ensure_response($repo->paginate(OBN_Fiscal_Period_Policy::current_org_id(), [
            'status' => $status,
            'year' => intval($request->get_param('year')),
            'month' => intval($request->get_param('month')),
            'page' => intval($request->get_param('page') ?: 1),
            'per_page' => intval($request->get_param('per_page') ?: 20),
        ]));
    }

    public static function rest_get_period(WP_REST_Request $request) {
        $repo = new OBN_Fiscal_Period_Repository();
        $row = $repo->get_row(intval($request['id']), OBN_Fiscal_Period_Policy::current_org_id());
        return $row ? rest_ensure_response($row) : new WP_Error('not_found', 'Fiscal period not found.', ['status' => 404]);
    }

    public static function rest_create_period(WP_REST_Request $request) {
        $repo = new OBN_Fiscal_Period_Repository();
        $result = $repo->create([
            'org_id' => OBN_Fiscal_Period_Policy::current_org_id(),
            'period_type' => $request->get_param('periodType') ?: $request->get_param('period_type') ?: 'MONTH',
            'period_name' => $request->get_param('periodName') ?: $request->get_param('period_name'),
            'period_start' => $request->get_param('periodStart') ?: $request->get_param('period_start'),
            'period_end' => $request->get_param('periodEnd') ?: $request->get_param('period_end'),
        ]);
        if (is_wp_error($result)) {
            $result->add_data(['status' => 422]);
            return $result;
        }
        return rest_ensure_response(['id' => $result]);
    }

    public static function rest_close_period(WP_REST_Request $request) {
        $close_type = strtolower(sanitize_text_field($request->get_param('closeType') ?: 'soft'));
        $new_status = $close_type === 'hard' ? OBN_Fiscal_Period_Status::HARD_CLOSED : OBN_Fiscal_Period_Status::SOFT_CLOSED;
        return self::transition_from_request(intval($request['id']), $new_status, sanitize_textarea_field($request->get_param('note')));
    }

    public static function rest_reopen_period(WP_REST_Request $request) {
        return self::transition_from_request(intval($request['id']), OBN_Fiscal_Period_Status::OPEN, sanitize_textarea_field($request->get_param('reason')));
    }

    public static function rest_override_reopen_period(WP_REST_Request $request) {
        $justification = sanitize_textarea_field($request->get_param('justification'));
        if (trim($justification) === '') {
            return new WP_Error('justification_required', 'Mandatory justification is required.', ['status' => 422]);
        }
        return self::transition_from_request(intval($request['id']), OBN_Fiscal_Period_Status::OPEN, $justification, true);
    }

    private static function transition_from_request($id, $new_status, $reason = '', $is_override = false) {
        $repo = new OBN_Fiscal_Period_Repository();
        $period = $repo->find($id, OBN_Fiscal_Period_Policy::current_org_id());
        if (!$period) {
            return new WP_Error('not_found', 'Fiscal period not found.', ['status' => 404]);
        }

        $result = $repo->transition($period, $new_status, $reason, $is_override);
        if (is_wp_error($result)) {
            $result->add_data(['status' => 409]);
            return $result;
        }

        return rest_ensure_response(['success' => true, 'period' => $repo->get_row($id, OBN_Fiscal_Period_Policy::current_org_id())]);
    }

    public static function ajax_create_period() {
        check_ajax_referer('obn_fiscal_period_nonce', 'security');
        if (!OBN_Fiscal_Period_Policy::can_close()) {
            wp_send_json_error(['message' => 'Unauthorized access.'], 403);
        }
        $request = new WP_REST_Request('POST');
        foreach ($_POST as $key => $value) {
            $request->set_param($key, is_string($value) ? wp_unslash($value) : $value);
        }
        $response = self::rest_create_period($request);
        self::send_ajax_response($response, 'Fiscal period created successfully.');
    }

    public static function ajax_close_period() {
        check_ajax_referer('obn_fiscal_period_nonce', 'security');
        if (!OBN_Fiscal_Period_Policy::can_close()) {
            wp_send_json_error(['message' => 'Unauthorized access.'], 403);
        }
        $request = new WP_REST_Request('POST');
        $request->set_param('id', intval($_POST['id'] ?? 0));
        $request->set_param('closeType', sanitize_text_field(wp_unslash($_POST['closeType'] ?? 'soft')));
        $request->set_param('note', sanitize_textarea_field(wp_unslash($_POST['note'] ?? '')));
        $response = self::rest_close_period($request);
        self::send_ajax_response($response, 'Fiscal period closed successfully.');
    }

    public static function ajax_reopen_period() {
        check_ajax_referer('obn_fiscal_period_nonce', 'security');
        if (!OBN_Fiscal_Period_Policy::can_reopen()) {
            wp_send_json_error(['message' => 'Unauthorized access.'], 403);
        }
        $request = new WP_REST_Request('POST');
        $request->set_param('id', intval($_POST['id'] ?? 0));
        $request->set_param('reason', sanitize_textarea_field(wp_unslash($_POST['reason'] ?? '')));
        $response = self::rest_reopen_period($request);
        self::send_ajax_response($response, 'Fiscal period reopened successfully.');
    }

    public static function ajax_override_reopen_period() {
        check_ajax_referer('obn_fiscal_period_nonce', 'security');
        if (!OBN_Fiscal_Period_Policy::can_override()) {
            wp_send_json_error(['message' => 'Unauthorized access.'], 403);
        }
        $request = new WP_REST_Request('POST');
        $request->set_param('id', intval($_POST['id'] ?? 0));
        $request->set_param('justification', sanitize_textarea_field(wp_unslash($_POST['justification'] ?? '')));
        $response = self::rest_override_reopen_period($request);
        self::send_ajax_response($response, 'Fiscal period override reopen completed.');
    }

    private static function send_ajax_response($response, $success_message) {
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()], 400);
        }
        wp_send_json_success(['message' => $success_message]);
    }

    public static function ensure_default_open_periods() {
        if (!is_user_logged_in() || !class_exists('OBN_Auth')) {
            return;
        }

        $auth = new OBN_Auth();
        if (!$auth->can_access_accounting() && !current_user_can('manage_options')) {
            return;
        }

        $repo = new OBN_Fiscal_Period_Repository();
        $repo->ensure_default_open_periods();
    }

    public static function generate_next_month_period() {
        if (gmdate('j') !== '1') {
            return;
        }
        $repo = new OBN_Fiscal_Period_Repository();
        $org_id = OBN_Fiscal_Period_Policy::current_org_id();
        $start = gmdate('Y-m-01', strtotime('+1 month'));
        $end = gmdate('Y-m-t', strtotime($start));
        if ($repo->has_overlap($org_id, $start, $end, 0, 'MONTH')) {
            return;
        }
        $repo->create([
            'org_id' => $org_id,
            'period_type' => 'MONTH',
            'period_name' => gmdate('F Y', strtotime($start)),
            'period_start' => $start,
            'period_end' => $end,
        ]);
    }

    public static function generate_next_fiscal_year_period() {
        if (gmdate('m-d') !== '01-01') {
            return;
        }
        $repo = new OBN_Fiscal_Period_Repository();
        $org_id = OBN_Fiscal_Period_Policy::current_org_id();
        $year = intval(gmdate('Y')) + 1;
        $start = $year . '-01-01';
        $end = $year . '-12-31';
        if ($repo->has_overlap($org_id, $start, $end, 0, 'FISCAL_YEAR')) {
            return;
        }
        $repo->create([
            'org_id' => $org_id,
            'period_type' => 'FISCAL_YEAR',
            'period_name' => 'FY ' . $year,
            'period_start' => $start,
            'period_end' => $end,
        ]);
    }
}

OBN_Fiscal_Periods::init();
