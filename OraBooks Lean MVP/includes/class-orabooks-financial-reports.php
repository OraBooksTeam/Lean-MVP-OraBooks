<?php
/**
 * OraBooks Core Financial Statements (SL-074)
 *
 * Canonical financial reporting engine for P&L, Balance Sheet, Cash Flow,
 * Trial Balance, snapshots, signatures, and projection governance.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Financial_Reports {

    const SNAPSHOT_TTL_SECONDS = 3600;
    const SCHEMA_VERSION = 1;

    private static $instance = null;

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();

            add_action('wp_ajax_orabooks_financial_report_generate', [self::$instance, 'ajax_generate_report']);
            add_action('wp_ajax_nopriv_orabooks_financial_report_generate', [self::$instance, 'ajax_generate_report']);
            add_action('wp_ajax_orabooks_financial_report_export', [self::$instance, 'ajax_request_export']);
            add_action('wp_ajax_nopriv_orabooks_financial_report_export', [self::$instance, 'ajax_request_export']);
            add_action('wp_ajax_orabooks_financial_report_sign', [self::$instance, 'ajax_sign_report']);
            add_action('wp_ajax_nopriv_orabooks_financial_report_sign', [self::$instance, 'ajax_sign_report']);
            add_action('wp_ajax_orabooks_financial_report_rebuild', [self::$instance, 'ajax_rebuild_projection']);
            add_action('wp_ajax_nopriv_orabooks_financial_report_rebuild', [self::$instance, 'ajax_rebuild_projection']);
            add_action('wp_ajax_orabooks_financial_report_replay', [self::$instance, 'ajax_replay_projection']);
            add_action('wp_ajax_nopriv_orabooks_financial_report_replay', [self::$instance, 'ajax_replay_projection']);

            add_action('orabooks_journal_posted', [self::$instance, 'project_journal_posted'], 10, 2);
            add_action('orabooks_monthly_report_snapshot_archive', [self::$instance, 'archive_old_snapshots']);
            add_action('orabooks_daily_projection_integrity_check', [self::$instance, 'run_integrity_checks']);
        }
        return self::$instance;
    }

    public static function get_create_table_sql() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_orgs = OraBooks_Database::table('organizations');
        $table_accounts = OraBooks_Database::table('accounts');
        $tables = [];

        $table_summary = OraBooks_Database::table('report_ledger_summary');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_summary} (
            org_id BIGINT UNSIGNED NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            period_date DATE NOT NULL,
            debit_sum DECIMAL(20,2) DEFAULT 0,
            credit_sum DECIMAL(20,2) DEFAULT 0,
            balance DECIMAL(20,2) DEFAULT 0,
            schema_version INT DEFAULT 1,
            last_event_id BIGINT UNSIGNED NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (org_id, account_id, period_date),
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
            FOREIGN KEY (account_id) REFERENCES {$table_accounts}(id) ON DELETE CASCADE,
            INDEX idx_period (period_date)
        ) {$charset_collate};";

        $tables[] = self::simple_read_model_sql('report_ar_aging', $charset_collate);
        $tables[] = self::simple_read_model_sql('report_ap_aging', $charset_collate);
        $tables[] = self::simple_read_model_sql('report_inventory_valuation', $charset_collate, 'valuation_amount');

        $table_cash = OraBooks_Database::table('cash_flow_mappings');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_cash} (
            org_id BIGINT UNSIGNED NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            cash_flow_category ENUM('operating','investing','financing') NOT NULL,
            method ENUM('indirect','direct') DEFAULT 'indirect',
            PRIMARY KEY (org_id, account_id),
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
            FOREIGN KEY (account_id) REFERENCES {$table_accounts}(id) ON DELETE CASCADE
        ) {$charset_collate};";

        $table_snapshots = OraBooks_Database::table('report_snapshots');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_snapshots} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            report_type VARCHAR(50) NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            snapshot_data LONGTEXT NULL,
            snapshot_data_archive_uri VARCHAR(500) NULL,
            snapshot_hash VARCHAR(64) NOT NULL,
            encryption_key_id VARCHAR(100) NULL,
            is_encrypted TINYINT(1) DEFAULT 0,
            generated_by BIGINT UNSIGNED NULL,
            correlation_id VARCHAR(64) NOT NULL,
            report_schema_version INT DEFAULT 1,
            frozen TINYINT(1) DEFAULT 0,
            archived TINYINT(1) DEFAULT 0,
            expires_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
            INDEX idx_org_period (org_id, report_type, period_start, period_end),
            INDEX idx_archive (archived, created_at)
        ) {$charset_collate};";

        $table_signatures = OraBooks_Database::table('report_signatures');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_signatures} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            report_snapshot_id BIGINT UNSIGNED NOT NULL,
            signed_by BIGINT UNSIGNED NOT NULL,
            signed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            signature_hash VARCHAR(128) NOT NULL,
            digital_certificate_id VARCHAR(255) NULL,
            board_approval_reference VARCHAR(100) NULL,
            FOREIGN KEY (report_snapshot_id) REFERENCES {$table_snapshots}(id) ON DELETE CASCADE,
            INDEX idx_snapshot (report_snapshot_id)
        ) {$charset_collate};";

        $table_deps = OraBooks_Database::table('projection_dependencies');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_deps} (
            projection_name VARCHAR(100) PRIMARY KEY,
            depends_on VARCHAR(100) NULL,
            rebuild_order INT DEFAULT 0,
            is_partitioned TINYINT(1) DEFAULT 0,
            partition_key VARCHAR(50) NULL
        ) {$charset_collate};";

        $table_checkpoints = OraBooks_Database::table('projector_checkpoints');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_checkpoints} (
            projection_name VARCHAR(100) PRIMARY KEY,
            last_event_id BIGINT UNSIGNED NULL,
            last_processed_at TIMESTAMP NULL,
            status ENUM('running','lagging','failed') DEFAULT 'running',
            lag_seconds INT DEFAULT 0
        ) {$charset_collate};";

        $table_integrity = OraBooks_Database::table('projection_integrity_checks');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_integrity} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            check_date DATE NOT NULL,
            projection_name VARCHAR(100) NOT NULL,
            ledger_total DECIMAL(20,2) DEFAULT 0,
            projection_total DECIMAL(20,2) DEFAULT 0,
            difference DECIMAL(20,2) DEFAULT 0,
            status ENUM('pass','fail','repaired') DEFAULT 'pass',
            repaired_at TIMESTAMP NULL,
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
            INDEX idx_org_date (org_id, check_date)
        ) {$charset_collate};";

        return $tables;
    }

    private static function simple_read_model_sql($name, $charset_collate, $amount_col = 'amount') {
        $table = OraBooks_Database::table($name);
        return "CREATE TABLE IF NOT EXISTS {$table} (
            org_id BIGINT UNSIGNED NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            period_date DATE NOT NULL,
            {$amount_col} DECIMAL(20,2) DEFAULT 0,
            bucket VARCHAR(30) NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (org_id, entity_id, period_date),
            INDEX idx_period (period_date)
        ) {$charset_collate};";
    }

    public static function seed_projection_dependencies() {
        global $wpdb;

        $table = OraBooks_Database::table('projection_dependencies');
        $deps = [
            ['ledger_summary', null, 1, 1, 'period_date'],
            ['ar_aging', 'ledger_summary', 2, 1, 'period_date'],
            ['ap_aging', 'ledger_summary', 3, 1, 'period_date'],
            ['inventory_valuation', 'ledger_summary', 4, 1, 'period_date'],
        ];

        foreach ($deps as $dep) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT projection_name FROM {$table} WHERE projection_name = %s", $dep[0]));
            if ($exists) {
                continue;
            }
            $wpdb->insert($table, [
                'projection_name' => $dep[0],
                'depends_on' => $dep[1],
                'rebuild_order' => $dep[2],
                'is_partitioned' => $dep[3],
                'partition_key' => $dep[4],
            ], ['%s', '%s', '%d', '%d', '%s']);
        }
    }

    public static function generate_report($org_id, $report_type, $period_start, $period_end, $args = []) {
        $org_id = intval($org_id);
        $report_type = sanitize_text_field($report_type);
        $period_start = sanitize_text_field($period_start);
        $period_end = sanitize_text_field($period_end);
        $generated_by = intval($args['generated_by'] ?? get_current_user_id());
        $correlation_id = sanitize_text_field($args['correlation_id'] ?? self::correlation_id());

        if ($org_id <= 0 || !self::valid_report_type($report_type) || !$period_start || !$period_end) {
            return new WP_Error('invalid_report_request', 'Invalid report request.');
        }

        $is_hard_closed = self::is_period_hard_closed($org_id, $period_start, $period_end);
        $cached = self::get_cached_snapshot($org_id, $report_type, $period_start, $period_end, $is_hard_closed);
        if ($cached) {
            return self::snapshot_response($cached, true);
        }

        switch ($report_type) {
            case 'profit_loss':
                $data = self::build_profit_loss($org_id, $period_start, $period_end);
                break;
            case 'balance_sheet':
                $data = self::build_balance_sheet($org_id, $period_end);
                break;
            case 'cash_flow':
                $data = self::build_cash_flow($org_id, $period_start, $period_end, $args['method'] ?? 'indirect');
                break;
            case 'trial_balance':
                $data = self::build_trial_balance($org_id, $period_start, $period_end);
                break;
            case 'changes_equity':
                $data = self::build_changes_equity($org_id, $period_start, $period_end);
                break;
            default:
                return new WP_Error('unsupported_report', 'Unsupported report type.');
        }

        if (is_wp_error($data)) {
            return $data;
        }

        $snapshot = self::create_snapshot($org_id, $report_type, $period_start, $period_end, $data, $generated_by, $correlation_id, $is_hard_closed);
        return self::snapshot_response($snapshot, false);
    }

    private static function valid_report_type($type) {
        return in_array($type, ['profit_loss', 'balance_sheet', 'cash_flow', 'trial_balance', 'changes_equity'], true);
    }

    private static function build_profit_loss($org_id, $period_start, $period_end) {
        $rows = self::ledger_summary_rows($org_id, $period_start, $period_end, ['revenue', 'expense']);
        $revenue = [];
        $expenses = [];
        $total_revenue = 0.0;
        $total_expenses = 0.0;

        foreach ($rows as $row) {
            $amount = self::account_amount($row);
            $item = self::report_item($row, $amount);
            if ($row->type === 'revenue') {
                $revenue[] = $item;
                $total_revenue += $amount;
            } else {
                $expenses[] = $item;
                $total_expenses += $amount;
            }
        }

        return [
            'report_type' => 'profit_loss',
            'revenue' => $revenue,
            'expenses' => $expenses,
            'total_revenue' => round($total_revenue, 2),
            'total_expenses' => round($total_expenses, 2),
            'net_income' => round($total_revenue - $total_expenses, 2),
        ];
    }

    private static function build_balance_sheet($org_id, $as_of_date) {
        $rows = self::ledger_summary_rows($org_id, null, $as_of_date, ['asset', 'liability', 'equity']);
        $sections = ['assets' => [], 'liabilities' => [], 'equity' => []];
        $totals = ['assets' => 0.0, 'liabilities' => 0.0, 'equity' => 0.0];

        foreach ($rows as $row) {
            $amount = self::account_amount($row);
            $key = $row->type === 'asset' ? 'assets' : ($row->type === 'liability' ? 'liabilities' : 'equity');
            $sections[$key][] = self::report_item($row, $amount);
            $totals[$key] += $amount;
        }

        return [
            'report_type' => 'balance_sheet',
            'assets' => $sections['assets'],
            'liabilities' => $sections['liabilities'],
            'equity' => $sections['equity'],
            'total_assets' => round($totals['assets'], 2),
            'total_liabilities' => round($totals['liabilities'], 2),
            'total_equity' => round($totals['equity'], 2),
            'balanced' => abs($totals['assets'] - ($totals['liabilities'] + $totals['equity'])) <= 0.01,
        ];
    }

    private static function build_cash_flow($org_id, $period_start, $period_end, $method = 'indirect') {
        global $wpdb;

        $method = in_array($method, ['indirect', 'direct'], true) ? $method : 'indirect';
        $table_summary = OraBooks_Database::table('report_ledger_summary');
        $table_accounts = OraBooks_Database::table('accounts');
        $table_mappings = OraBooks_Database::table('cash_flow_mappings');

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT COALESCE(m.cash_flow_category, 'operating') as cash_flow_category,
                    a.id as account_id, a.code, a.name, a.type, a.normal_balance,
                    SUM(s.debit_sum) as debit_sum, SUM(s.credit_sum) as credit_sum
             FROM {$table_summary} s
             JOIN {$table_accounts} a ON a.id = s.account_id
             LEFT JOIN {$table_mappings} m ON m.org_id = s.org_id AND m.account_id = s.account_id
             WHERE s.org_id = %d AND s.period_date BETWEEN %s AND %s
               AND (m.method IS NULL OR m.method = %s)
             GROUP BY COALESCE(m.cash_flow_category, 'operating'), a.id, a.code, a.name, a.type, a.normal_balance
             ORDER BY a.code",
            $org_id,
            $period_start,
            $period_end,
            $method
        ));

        $sections = ['operating' => [], 'investing' => [], 'financing' => []];
        $totals = ['operating' => 0.0, 'investing' => 0.0, 'financing' => 0.0];

        foreach ($rows as $row) {
            $category = in_array($row->cash_flow_category, ['operating', 'investing', 'financing'], true) ? $row->cash_flow_category : 'operating';
            $amount = self::account_amount($row);
            $sections[$category][] = self::report_item($row, $amount);
            $totals[$category] += $amount;
        }

        return [
            'report_type' => 'cash_flow',
            'method' => $method,
            'operating' => $sections['operating'],
            'investing' => $sections['investing'],
            'financing' => $sections['financing'],
            'net_cash_flow' => round($totals['operating'] + $totals['investing'] + $totals['financing'], 2),
        ];
    }

    private static function build_trial_balance($org_id, $period_start, $period_end) {
        $rows = self::ledger_summary_rows($org_id, $period_start, $period_end, []);
        $items = [];
        $debits = 0.0;
        $credits = 0.0;

        foreach ($rows as $row) {
            $debit = (float) $row->debit_sum;
            $credit = (float) $row->credit_sum;
            $items[] = [
                'account_id' => (int) $row->account_id,
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
            ];
            $debits += $debit;
            $credits += $credit;
        }

        return [
            'report_type' => 'trial_balance',
            'accounts' => $items,
            'total_debits' => round($debits, 2),
            'total_credits' => round($credits, 2),
            'balanced' => abs($debits - $credits) <= 0.01,
        ];
    }

    private static function build_changes_equity($org_id, $period_start, $period_end) {
        $pl = self::build_profit_loss($org_id, $period_start, $period_end);
        $equity_rows = self::ledger_summary_rows($org_id, null, $period_end, ['equity']);
        $ending_equity = 0.0;
        foreach ($equity_rows as $row) {
            $ending_equity += self::account_amount($row);
        }

        return [
            'report_type' => 'changes_equity',
            'net_income' => $pl['net_income'],
            'ending_equity' => round($ending_equity, 2),
            'equity_accounts' => array_map(function ($row) {
                return self::report_item($row, self::account_amount($row));
            }, $equity_rows),
        ];
    }

    private static function ledger_summary_rows($org_id, $period_start, $period_end, $types = []) {
        global $wpdb;

        $table_summary = OraBooks_Database::table('report_ledger_summary');
        $table_accounts = OraBooks_Database::table('accounts');
        $where = 's.org_id = %d AND s.period_date <= %s';
        $params = [$org_id, $period_end];

        if ($period_start) {
            $where .= ' AND s.period_date >= %s';
            $params[] = $period_start;
        }

        if (!empty($types)) {
            $placeholders = implode(',', array_fill(0, count($types), '%s'));
            $where .= " AND a.type IN ({$placeholders})";
            $params = array_merge($params, $types);
        }

        $sql = "SELECT a.id as account_id, a.code, a.name, a.type, a.normal_balance,
                       SUM(s.debit_sum) as debit_sum, SUM(s.credit_sum) as credit_sum,
                       SUM(s.balance) as balance
                FROM {$table_summary} s
                JOIN {$table_accounts} a ON a.id = s.account_id
                WHERE {$where}
                GROUP BY a.id, a.code, a.name, a.type, a.normal_balance
                ORDER BY a.code";

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    private static function account_amount($row) {
        $debit = (float) ($row->debit_sum ?? 0);
        $credit = (float) ($row->credit_sum ?? 0);
        return in_array($row->normal_balance, ['credit'], true)
            ? round($credit - $debit, 2)
            : round($debit - $credit, 2);
    }

    private static function report_item($row, $amount) {
        return [
            'account_id' => (int) $row->account_id,
            'code' => $row->code,
            'name' => $row->name,
            'type' => $row->type,
            'amount' => round((float) $amount, 2),
        ];
    }

    private static function is_period_hard_closed($org_id, $period_start, $period_end) {
        global $wpdb;
        $table = OraBooks_Database::table('fiscal_periods');
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$table}
             WHERE org_id = %d AND period_start <= %s AND period_end >= %s
             ORDER BY period_start DESC LIMIT 1",
            $org_id,
            $period_start,
            $period_end
        ));
        return $status === 'hard_closed';
    }

    private static function get_cached_snapshot($org_id, $report_type, $period_start, $period_end, $require_frozen = false) {
        global $wpdb;
        $table = OraBooks_Database::table('report_snapshots');

        $frozen_sql = $require_frozen ? 'AND frozen = 1' : 'AND (frozen = 1 OR expires_at > NOW())';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE org_id = %d AND report_type = %s AND period_start = %s AND period_end = %s
               AND archived = 0 {$frozen_sql}
             ORDER BY created_at DESC LIMIT 1",
            $org_id,
            $report_type,
            $period_start,
            $period_end
        ));
    }

    private static function create_snapshot($org_id, $report_type, $period_start, $period_end, $data, $generated_by, $correlation_id, $frozen = false) {
        global $wpdb;
        $table = OraBooks_Database::table('report_snapshots');

        $payload = [
            'report' => $data,
            'period_start' => $period_start,
            'period_end' => $period_end,
            'generated_at' => current_time('mysql'),
            'correlation_id' => $correlation_id,
        ];
        $json = wp_json_encode($payload);
        $hash = hash('sha256', $json);
        $stored_data = $json;
        $encryption_key_id = null;
        $is_encrypted = 0;

        $report_config = self::get_org_report_config($org_id);
        if (!empty($report_config['encrypt_snapshots'])) {
            $encrypted = self::encrypt_snapshot_payload($org_id, $json);
            $stored_data = $encrypted['ciphertext'];
            $encryption_key_id = $encrypted['encryption_key_id'];
            $is_encrypted = 1;
        }

        $wpdb->insert($table, [
            'org_id' => $org_id,
            'report_type' => $report_type,
            'period_start' => $period_start,
            'period_end' => $period_end,
            'snapshot_data' => $stored_data,
            'snapshot_hash' => $hash,
            'encryption_key_id' => $encryption_key_id,
            'is_encrypted' => $is_encrypted,
            'generated_by' => $generated_by,
            'correlation_id' => $correlation_id,
            'report_schema_version' => self::SCHEMA_VERSION,
            'frozen' => $frozen ? 1 : 0,
            'archived' => 0,
            'expires_at' => $frozen ? null : date('Y-m-d H:i:s', time() + self::SNAPSHOT_TTL_SECONDS),
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s']);

        $snapshot = (object) [
            'id' => (int) $wpdb->insert_id,
            'org_id' => $org_id,
            'report_type' => $report_type,
            'period_start' => $period_start,
            'period_end' => $period_end,
            'snapshot_data' => $json,
            'snapshot_hash' => $hash,
            'encryption_key_id' => $encryption_key_id,
            'is_encrypted' => $is_encrypted,
            'generated_by' => $generated_by,
            'correlation_id' => $correlation_id,
            'frozen' => $frozen ? 1 : 0,
            'archived' => 0,
        ];

        orabooks_log_event('financial_report_generated', 'Financial report generated', 'info', [
            'snapshot_id' => $snapshot->id,
            'report_type' => $report_type,
            'period_start' => $period_start,
            'period_end' => $period_end,
            'correlation_id' => $correlation_id,
            'frozen' => $frozen,
        ], $generated_by, $org_id);

        return $snapshot;
    }

    private static function snapshot_response($snapshot, $from_cache) {
        $raw = self::decode_snapshot_payload($snapshot);
        $payload = json_decode($raw, true) ?: [];
        $signature = self::get_report_signature((int) $snapshot->id);
        $export_meta = self::build_export_watermark($snapshot, $signature);

        return [
            'snapshot_id' => (int) $snapshot->id,
            'report_type' => $snapshot->report_type,
            'period_start' => $snapshot->period_start,
            'period_end' => $snapshot->period_end,
            'report' => $payload['report'] ?? $payload,
            'snapshot_hash' => $snapshot->snapshot_hash,
            'correlation_id' => $snapshot->correlation_id,
            'frozen' => !empty($snapshot->frozen),
            'archived' => !empty($snapshot->archived),
            'is_encrypted' => !empty($snapshot->is_encrypted),
            'from_cache' => $from_cache,
            'watermark' => $export_meta['watermark'],
            'board_approved' => $export_meta['board_approved'],
            'signature' => $export_meta['signature'],
            'retention_days' => self::get_org_report_config((int) $snapshot->org_id)['snapshot_retention_days'],
        ];
    }

    public static function get_recent_snapshots($org_id, $args = []) {
        global $wpdb;

        $table = OraBooks_Database::table('report_snapshots');
        $limit = intval($args['limit'] ?? 10);

        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, report_type, period_start, period_end, frozen, created_at, correlation_id
             FROM {$table}
             WHERE org_id = %d
             ORDER BY created_at DESC, id DESC
             LIMIT %d",
            intval($org_id),
            $limit
        ));
    }

    public static function project_journal_posted($journal_id, $payload = []) {
        global $wpdb;

        $org_id = intval($payload['org_id'] ?? 0);
        $event_id = intval($payload['event_id'] ?? $journal_id);
        if ($org_id <= 0 || intval($journal_id) <= 0) {
            return new WP_Error('invalid_projection_payload', 'Missing journal projection payload.');
        }

        $table_journals = OraBooks_Database::table('journals');
        $table_lines = OraBooks_Database::table('journal_lines');
        $table_summary = OraBooks_Database::table('report_ledger_summary');
        $table_checkpoints = OraBooks_Database::table('projector_checkpoints');

        $journal = $wpdb->get_row($wpdb->prepare(
            "SELECT id, org_id, transaction_date, status FROM {$table_journals} WHERE id = %d AND org_id = %d",
            intval($journal_id),
            $org_id
        ));
        if (!$journal || $journal->status !== 'posted') {
            return new WP_Error('journal_not_posted', 'Only posted journals update report projections.');
        }

        $lines = $wpdb->get_results($wpdb->prepare(
            "SELECT account_id, SUM(debit_amount) as debit_sum, SUM(credit_amount) as credit_sum
             FROM {$table_lines}
             WHERE journal_id = %d
             GROUP BY account_id",
            intval($journal_id)
        ));

        foreach ($lines as $line) {
            $debit = (float) $line->debit_sum;
            $credit = (float) $line->credit_sum;
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$table_summary} (org_id, account_id, period_date, debit_sum, credit_sum, balance, schema_version, last_event_id)
                 VALUES (%d, %d, %s, %f, %f, %f, %d, %d)
                 ON DUPLICATE KEY UPDATE
                    debit_sum = debit_sum + VALUES(debit_sum),
                    credit_sum = credit_sum + VALUES(credit_sum),
                    balance = balance + VALUES(balance),
                    last_event_id = GREATEST(COALESCE(last_event_id, 0), VALUES(last_event_id))",
                $org_id,
                intval($line->account_id),
                $journal->transaction_date,
                $debit,
                $credit,
                $debit - $credit,
                self::SCHEMA_VERSION,
                $event_id
            ));
        }

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_checkpoints} (projection_name, last_event_id, last_processed_at, status, lag_seconds)
             VALUES ('ledger_summary', %d, %s, 'running', 0)
             ON DUPLICATE KEY UPDATE last_event_id = VALUES(last_event_id), last_processed_at = VALUES(last_processed_at), status = 'running', lag_seconds = 0",
            $event_id,
            current_time('mysql')
        ));

        self::invalidate_snapshots($org_id, $journal->transaction_date);
        return ['projected_lines' => count($lines), 'last_event_id' => $event_id];
    }

    private static function invalidate_snapshots($org_id, $event_date) {
        global $wpdb;
        $table = OraBooks_Database::table('report_snapshots');
        $wpdb->update(
            $table,
            ['archived' => 1],
            [
                'org_id' => intval($org_id),
                'frozen' => 0,
                'archived' => 0,
            ],
            ['%d'],
            ['%d', '%d', '%d']
        );
        orabooks_log_event('financial_report_cache_invalidated', 'Financial report snapshots invalidated', 'info', [
            'event_date' => $event_date,
        ], null, $org_id);
    }

    public static function sign_report($snapshot_id, $user_id, $board_approval_reference = '') {
        global $wpdb;

        $table_snapshots = OraBooks_Database::table('report_snapshots');
        $table_signatures = OraBooks_Database::table('report_signatures');
        $snapshot = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_snapshots} WHERE id = %d AND archived = 0",
            intval($snapshot_id)
        ));
        if (!$snapshot) {
            return new WP_Error('snapshot_not_found', 'Report snapshot not found.');
        }

        $existing = self::get_report_signature((int) $snapshot_id);
        if ($existing) {
            return new WP_Error('already_signed', 'Report snapshot is already signed.');
        }

        $plaintext = self::decode_snapshot_payload($snapshot);
        $signed_at = current_time('mysql');
        $signature_hash = self::compute_signature_hash($plaintext, $snapshot->snapshot_hash, $user_id, $signed_at);

        $wpdb->insert($table_signatures, [
            'report_snapshot_id' => intval($snapshot_id),
            'signed_by' => intval($user_id),
            'signed_at' => $signed_at,
            'signature_hash' => $signature_hash,
            'board_approval_reference' => sanitize_text_field($board_approval_reference),
        ], ['%d', '%d', '%s', '%s', '%s']);

        orabooks_log_event('financial_report_signed', 'Financial report signed', 'info', [
            'snapshot_id' => intval($snapshot_id),
            'signature_hash' => $signature_hash,
            'board_approval_reference' => sanitize_text_field($board_approval_reference),
        ], intval($user_id), intval($snapshot->org_id));

        return [
            'signature_id' => (int) $wpdb->insert_id,
            'signature_hash' => $signature_hash,
            'watermark' => 'APPROVED',
            'board_approved' => true,
            'signed_at' => $signed_at,
        ];
    }

    public static function get_report_signature($snapshot_id) {
        global $wpdb;

        $table = OraBooks_Database::table('report_signatures');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE report_snapshot_id = %d ORDER BY signed_at DESC LIMIT 1",
            (int) $snapshot_id
        ));
    }

    public static function compute_signature_hash($snapshot_data, $snapshot_hash, $user_id, $signed_at) {
        return hash_hmac(
            'sha256',
            ($snapshot_data ?? '') . '|' . $snapshot_hash . '|' . (int) $user_id . '|' . $signed_at,
            defined('ORABOOKS_JWT_SECRET') ? ORABOOKS_JWT_SECRET : 'orabooks-report-signature'
        );
    }

    public static function verify_report_signature($snapshot_id) {
        global $wpdb;

        $table_snapshots = OraBooks_Database::table('report_snapshots');
        $snapshot = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_snapshots} WHERE id = %d",
            (int) $snapshot_id
        ));
        if (!$snapshot) {
            return new WP_Error('snapshot_not_found', 'Report snapshot not found.');
        }

        $signature = self::get_report_signature((int) $snapshot_id);
        if (!$signature) {
            return new WP_Error('not_signed', 'Report snapshot is not signed.');
        }

        $plaintext = self::decode_snapshot_payload($snapshot);
        $expected = self::compute_signature_hash(
            $plaintext,
            $snapshot->snapshot_hash,
            (int) $signature->signed_by,
            $signature->signed_at
        );

        $valid = hash_equals($expected, (string) $signature->signature_hash);
        return [
            'valid' => $valid,
            'signature_id' => (int) $signature->id,
            'signed_by' => (int) $signature->signed_by,
            'signed_at' => $signature->signed_at,
            'board_approval_reference' => $signature->board_approval_reference,
        ];
    }

    public static function get_snapshot_export_metadata($snapshot_id) {
        global $wpdb;

        $table = OraBooks_Database::table('report_snapshots');
        $snapshot = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $snapshot_id));
        if (!$snapshot) {
            return new WP_Error('snapshot_not_found', 'Report snapshot not found.');
        }

        $signature = self::get_report_signature((int) $snapshot_id);
        return self::build_export_watermark($snapshot, $signature);
    }

    public static function build_export_watermark($snapshot, $signature = null) {
        if ($signature) {
            return [
                'watermark' => 'APPROVED',
                'board_approved' => true,
                'signature' => [
                    'signature_id' => (int) $signature->id,
                    'signed_by' => (int) $signature->signed_by,
                    'signed_at' => $signature->signed_at,
                    'signature_hash' => $signature->signature_hash,
                    'board_approval_reference' => $signature->board_approval_reference,
                ],
                'correlation_id' => $snapshot->correlation_id ?? null,
            ];
        }

        if (!empty($snapshot->frozen)) {
            return [
                'watermark' => 'CONFIDENTIAL',
                'board_approved' => false,
                'signature' => null,
                'correlation_id' => $snapshot->correlation_id ?? null,
            ];
        }

        return [
            'watermark' => 'DRAFT',
            'board_approved' => false,
            'signature' => null,
            'correlation_id' => $snapshot->correlation_id ?? null,
        ];
    }

    public static function archive_old_snapshots($retention_days = null) {
        global $wpdb;

        $table = OraBooks_Database::table('report_snapshots');
        $default_retention = $retention_days !== null ? intval($retention_days) : 365;

        $snapshots = $wpdb->get_results(
            "SELECT id, org_id, snapshot_hash, snapshot_data, created_at
             FROM {$table}
             WHERE archived = 0 AND frozen = 0
             ORDER BY created_at ASC
             LIMIT 500"
        );

        $archived = 0;
        foreach ($snapshots as $snapshot) {
            $org_retention = self::get_org_report_config((int) $snapshot->org_id)['snapshot_retention_days'];
            $retention = $retention_days !== null ? $default_retention : (int) $org_retention;
            $cutoff = strtotime('-' . max(1, $retention) . ' days');
            if (strtotime($snapshot->created_at) >= $cutoff) {
                continue;
            }

            self::archive_snapshot_record($snapshot);
            $archived++;
        }

        orabooks_log_event('financial_report_snapshots_archived', 'Old financial report snapshots archived', 'info', [
            'count' => $archived,
            'default_retention_days' => $default_retention,
        ], null, null);

        return ['archived' => $archived];
    }

    public static function archive_snapshot_record($snapshot) {
        global $wpdb;

        $table = OraBooks_Database::table('report_snapshots');
        $archive_uri = self::build_archive_uri((int) $snapshot->org_id, (string) $snapshot->snapshot_hash);

        $wpdb->update(
            $table,
            [
                'archived' => 1,
                'snapshot_data_archive_uri' => $archive_uri,
                'snapshot_data' => null,
            ],
            ['id' => (int) $snapshot->id],
            ['%d', '%s', '%s'],
            ['%d']
        );

        return [
            'snapshot_id' => (int) $snapshot->id,
            'archive_uri' => $archive_uri,
        ];
    }

    public static function build_archive_uri($org_id, $snapshot_hash) {
        return 'cold://orabooks/reports/' . (int) $org_id . '/' . sanitize_text_field($snapshot_hash) . '.json';
    }

    public static function run_integrity_checks($org_id = null, $check_date = null) {
        global $wpdb;

        $table_ledger = OraBooks_Database::table('ledger_entries');
        $table_summary = OraBooks_Database::table('report_ledger_summary');
        $table_integrity = OraBooks_Database::table('projection_integrity_checks');
        $check_date = sanitize_text_field($check_date ?: current_time('Y-m-d'));

        $where = $org_id ? 'WHERE org_id = ' . intval($org_id) : '';
        $org_ids = $org_id ? [intval($org_id)] : $wpdb->get_col("SELECT DISTINCT org_id FROM {$table_summary}");
        $results = [];

        foreach ($org_ids as $oid) {
            $ledger_total = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(debit_amount - credit_amount), 0) FROM {$table_ledger} WHERE org_id = %d",
                intval($oid)
            ));
            $projection_total = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(balance), 0) FROM {$table_summary} WHERE org_id = %d",
                intval($oid)
            ));
            $difference = round($ledger_total - $projection_total, 2);
            $status = abs($difference) > 0.01 ? 'fail' : 'pass';

            $wpdb->insert($table_integrity, [
                'org_id' => intval($oid),
                'check_date' => $check_date,
                'projection_name' => 'ledger_summary',
                'ledger_total' => $ledger_total,
                'projection_total' => $projection_total,
                'difference' => $difference,
                'status' => $status,
            ], ['%d', '%s', '%s', '%f', '%f', '%f', '%s']);

            if ($status === 'fail' && function_exists('orabooks_publish_event')) {
                orabooks_publish_event('projection_integrity_failed', intval($oid), [
                    'org_id' => intval($oid),
                    'difference' => $difference,
                    'priority' => 'high',
                ]);
            }

            $results[] = ['org_id' => intval($oid), 'status' => $status, 'difference' => $difference];
        }

        return ['checks' => $results, 'org_filter' => $where];
    }

    public static function rebuild_projection($projection_name, $args = []) {
        global $wpdb;

        $projection_name = sanitize_text_field($projection_name);
        $batch_size = max(1, min(10000, intval($args['batch_size'] ?? 1000)));
        $throttle_per_sec = max(1, intval($args['throttle_per_sec'] ?? 100));
        $table_checkpoints = OraBooks_Database::table('projector_checkpoints');

        if ($projection_name !== 'ledger_summary') {
            return new WP_Error('unsupported_projection', 'Only ledger_summary rebuild is supported in MVP.');
        }

        $wpdb->update($table_checkpoints, [
            'status' => 'running',
            'lag_seconds' => 0,
            'last_processed_at' => current_time('mysql'),
        ], ['projection_name' => $projection_name], ['%s', '%d', '%s'], ['%s']);

        orabooks_log_event('financial_projection_rebuild_requested', 'Financial projection rebuild requested', 'info', [
            'projection_name' => $projection_name,
            'batch_size' => $batch_size,
            'throttle_per_sec' => $throttle_per_sec,
        ], get_current_user_id(), null);

        return [
            'projection_name' => $projection_name,
            'batch_size' => $batch_size,
            'throttle_per_sec' => $throttle_per_sec,
            'queued' => !empty($args['use_queue']),
        ];
    }

    private static function correlation_id() {
        return function_exists('orabooks_uuid') ? orabooks_uuid() : bin2hex(random_bytes(16));
    }

    /**
     * Resolve report data for SL-114 CSV/PDF export.
     *
     * @param array $params org_id, report_type (or export_type financial_*), period_start, period_end.
     * @return array|null { columns, rows } or null on failure.
     */
    public static function export_report_data($params) {
        $org_id = intval($params['org_id'] ?? 0);
        $report_type = sanitize_text_field($params['report_type'] ?? '');

        if (!$report_type && !empty($params['export_type']) && strpos($params['export_type'], 'financial_') === 0) {
            $report_type = substr($params['export_type'], strlen('financial_'));
        }

        if ($org_id <= 0 || !self::valid_report_type($report_type)) {
            return null;
        }

        $period_start = sanitize_text_field($params['period_start'] ?? date('Y-m-01'));
        $period_end = sanitize_text_field($params['period_end'] ?? date('Y-m-d'));

        $result = self::generate_report($org_id, $report_type, $period_start, $period_end, $params);
        if (is_wp_error($result)) {
            return null;
        }

        return self::flatten_for_export($result);
    }

    /**
     * Flatten a financial report snapshot into tabular export rows.
     */
    public static function flatten_for_export($result) {
        $report = $result['report'] ?? $result;
        $report_type = $report['report_type'] ?? ($result['report_type'] ?? '');
        $rows = [];

        switch ($report_type) {
            case 'profit_loss':
                foreach (['revenue' => 'Revenue', 'expenses' => 'Expense'] as $key => $section) {
                    foreach ($report[$key] ?? [] as $item) {
                        $rows[] = array_merge(['section' => $section], $item);
                    }
                }
                $rows[] = [
                    'section' => 'Summary',
                    'code' => '',
                    'name' => 'Net Income',
                    'type' => '',
                    'amount' => $report['net_income'] ?? 0,
                ];
                return [
                    'columns' => ['section', 'code', 'name', 'type', 'amount'],
                    'rows' => $rows,
                ];

            case 'balance_sheet':
                foreach (['assets' => 'Assets', 'liabilities' => 'Liabilities', 'equity' => 'Equity'] as $key => $section) {
                    foreach ($report[$key] ?? [] as $item) {
                        $rows[] = array_merge(['section' => $section], $item);
                    }
                }
                return [
                    'columns' => ['section', 'code', 'name', 'type', 'amount'],
                    'rows' => $rows,
                ];

            case 'cash_flow':
                foreach (['operating' => 'Operating', 'investing' => 'Investing', 'financing' => 'Financing'] as $key => $section) {
                    foreach ($report[$key] ?? [] as $item) {
                        $rows[] = array_merge(['section' => $section], $item);
                    }
                }
                $rows[] = [
                    'section' => 'Summary',
                    'code' => '',
                    'name' => 'Net Cash Flow',
                    'type' => '',
                    'amount' => $report['net_cash_flow'] ?? 0,
                ];
                return [
                    'columns' => ['section', 'code', 'name', 'type', 'amount'],
                    'rows' => $rows,
                ];

            case 'trial_balance':
                foreach ($report['accounts'] ?? [] as $item) {
                    $rows[] = $item;
                }
                return [
                    'columns' => ['code', 'name', 'type', 'debit', 'credit'],
                    'rows' => $rows,
                ];

            case 'changes_equity':
                foreach ($report['equity_accounts'] ?? [] as $item) {
                    $rows[] = $item;
                }
                $rows[] = [
                    'code' => '',
                    'name' => 'Net Income',
                    'type' => 'summary',
                    'amount' => $report['net_income'] ?? 0,
                ];
                $rows[] = [
                    'code' => '',
                    'name' => 'Ending Equity',
                    'type' => 'summary',
                    'amount' => $report['ending_equity'] ?? 0,
                ];
                return [
                    'columns' => ['code', 'name', 'type', 'amount'],
                    'rows' => $rows,
                ];
        }

        return null;
    }

    private function current_user_id() {
        return orabooks_get_current_user_id();
    }

    private function require_customer_org_access($user_id, $org_id) {
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }
    }

    public function ajax_generate_report() {
        $user_id = $this->current_user_id();
        $org_id = intval($_REQUEST['org_id'] ?? 0);
        $this->require_customer_org_access($user_id, $org_id);
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'view_financial_reports')) {
            orabooks_json_error('Permission denied', 403);
        }

        $result = self::generate_report(
            $org_id,
            $_REQUEST['report_type'] ?? '',
            $_REQUEST['period_start'] ?? '',
            $_REQUEST['period_end'] ?? '',
            [
                'generated_by' => $user_id,
                'correlation_id' => $_REQUEST['correlation_id'] ?? null,
                'method' => $_REQUEST['method'] ?? 'indirect',
            ]
        );

        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success($result);
    }

    public function ajax_request_export() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_customer_org_access($user_id, $org_id);
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'export_reports')) {
            orabooks_json_error('Permission denied', 403);
        }

        $report_type = sanitize_text_field($_POST['report_type'] ?? '');
        orabooks_log_event('financial_report_export_requested', 'Financial report export requested', 'info', [
            'report_type' => $report_type,
            'format' => sanitize_text_field($_POST['format'] ?? 'csv'),
        ], $user_id, $org_id);

        if (class_exists('OraBooks_Exports') && method_exists('OraBooks_Exports', 'request_export')) {
            $result = OraBooks_Exports::request_export(
                $org_id,
                $user_id,
                'financial_' . $report_type,
                sanitize_text_field($_POST['format'] ?? 'csv'),
                $_POST
            );
            if (is_wp_error($result)) {
                orabooks_json_error($result->get_error_message(), 400);
            }
            orabooks_json_success($result);
        }

        orabooks_json_error('Export service unavailable.', 501);
    }

    public function ajax_sign_report() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_customer_org_access($user_id, $org_id);
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'sign_report')) {
            orabooks_json_error('Permission denied', 403);
        }

        $result = self::sign_report(intval($_POST['snapshot_id'] ?? 0), $user_id, $_POST['board_approval_reference'] ?? '');
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success($result);
    }

    public function ajax_rebuild_projection() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }

        $result = self::rebuild_projection($_POST['projection_name'] ?? '', [
            'batch_size' => intval($_POST['batch_size'] ?? 1000),
            'throttle_per_sec' => intval($_POST['throttle_per_sec'] ?? 100),
            'use_queue' => !empty($_POST['use_queue']),
        ]);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success($result);
    }
}
