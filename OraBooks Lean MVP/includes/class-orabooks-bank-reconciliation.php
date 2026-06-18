<?php
/**
 * OraBooks Bank Feeds / Rules / Reconcile (SL-031)
 *
 * Bank statement import, transaction matching, skip/finalize reconciliation,
 * and bank feed metadata. This bounded context records reconciliation state
 * without mutating the accounting ledger.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Bank_Reconciliation {

    private static $instance = null;

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();

            add_action('wp_ajax_orabooks_bank_accounts_list', [self::$instance, 'ajax_accounts_list']);
            add_action('wp_ajax_nopriv_orabooks_bank_accounts_list', [self::$instance, 'ajax_accounts_list']);
            add_action('wp_ajax_orabooks_bank_account_create', [self::$instance, 'ajax_account_create']);
            add_action('wp_ajax_nopriv_orabooks_bank_account_create', [self::$instance, 'ajax_account_create']);
            add_action('wp_ajax_orabooks_bank_import_rows', [self::$instance, 'ajax_import_rows']);
            add_action('wp_ajax_nopriv_orabooks_bank_import_rows', [self::$instance, 'ajax_import_rows']);
            add_action('wp_ajax_orabooks_bank_transactions_list', [self::$instance, 'ajax_transactions_list']);
            add_action('wp_ajax_nopriv_orabooks_bank_transactions_list', [self::$instance, 'ajax_transactions_list']);
            add_action('wp_ajax_orabooks_bank_match', [self::$instance, 'ajax_manual_match']);
            add_action('wp_ajax_nopriv_orabooks_bank_match', [self::$instance, 'ajax_manual_match']);
            add_action('wp_ajax_orabooks_bank_skip', [self::$instance, 'ajax_skip_transaction']);
            add_action('wp_ajax_nopriv_orabooks_bank_skip', [self::$instance, 'ajax_skip_transaction']);
            add_action('wp_ajax_orabooks_bank_reconcile', [self::$instance, 'ajax_finalize_reconciliation']);
            add_action('wp_ajax_nopriv_orabooks_bank_reconcile', [self::$instance, 'ajax_finalize_reconciliation']);
        }
        return self::$instance;
    }

    public static function get_create_table_sql() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_accounts = OraBooks_Database::table('bank_accounts');
        $table_transactions = OraBooks_Database::table('bank_transactions');
        $table_feeds = OraBooks_Database::table('bank_feeds');
        $table_matches = OraBooks_Database::table('reconciliation_matches');
        $table_log = OraBooks_Database::table('reconciliation_log');
        $table_orgs = OraBooks_Database::table('organizations');

        return [
            "CREATE TABLE IF NOT EXISTS {$table_accounts} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                account_name VARCHAR(100) NOT NULL,
                account_number VARCHAR(50) NULL,
                currency CHAR(3) DEFAULT 'USD',
                current_balance DECIMAL(20,2) DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                liquidity_pool_id BIGINT UNSIGNED NULL,
                treasury_workflow_enabled TINYINT(1) DEFAULT 0,
                min_balance_threshold DECIMAL(20,2) NULL,
                target_balance DECIMAL(20,2) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
                INDEX idx_org_active (org_id, is_active)
            ) {$charset_collate};",
            "CREATE TABLE IF NOT EXISTS {$table_transactions} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                bank_account_id BIGINT UNSIGNED NOT NULL,
                transaction_date DATE NOT NULL,
                amount DECIMAL(20,2) NOT NULL,
                description TEXT NULL,
                reference VARCHAR(100) NULL,
                status ENUM('unmatched','matched','reconciled','skipped') DEFAULT 'unmatched',
                treasury_workflow_id BIGINT UNSIGNED NULL,
                liquidity_pool_id BIGINT UNSIGNED NULL,
                is_internal_transfer TINYINT(1) DEFAULT 0,
                raw_data JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
                FOREIGN KEY (bank_account_id) REFERENCES {$table_accounts}(id) ON DELETE CASCADE,
                UNIQUE KEY uk_bank_dedupe (bank_account_id, transaction_date, amount, reference),
                INDEX idx_status (status),
                INDEX idx_date_amount (transaction_date, amount),
                INDEX idx_org_account (org_id, bank_account_id)
            ) {$charset_collate};",
            "CREATE TABLE IF NOT EXISTS {$table_feeds} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                bank_account_id BIGINT UNSIGNED NOT NULL,
                provider VARCHAR(50) NOT NULL,
                access_token TEXT NULL,
                refresh_token TEXT NULL,
                last_sync_at TIMESTAMP NULL,
                status VARCHAR(20) DEFAULT 'inactive',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
                FOREIGN KEY (bank_account_id) REFERENCES {$table_accounts}(id) ON DELETE CASCADE,
                INDEX idx_org_provider (org_id, provider)
            ) {$charset_collate};",
            "CREATE TABLE IF NOT EXISTS {$table_matches} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                bank_transaction_id BIGINT UNSIGNED NOT NULL,
                transaction_type VARCHAR(20) NOT NULL,
                transaction_id BIGINT UNSIGNED NOT NULL,
                matched_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
                confidence_score DECIMAL(5,2) DEFAULT 0,
                match_status ENUM('suggested','confirmed') DEFAULT 'suggested',
                matched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (bank_transaction_id) REFERENCES {$table_transactions}(id) ON DELETE CASCADE,
                INDEX idx_bank_txn (bank_transaction_id),
                INDEX idx_system_txn (transaction_type, transaction_id)
            ) {$charset_collate};",
            "CREATE TABLE IF NOT EXISTS {$table_log} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                bank_account_id BIGINT UNSIGNED NOT NULL,
                statement_date DATE NOT NULL,
                ending_balance DECIMAL(20,2) NOT NULL,
                system_balance DECIMAL(20,2) NOT NULL DEFAULT 0,
                difference DECIMAL(20,2) NOT NULL DEFAULT 0,
                note TEXT NULL,
                reconciled_by BIGINT UNSIGNED NOT NULL,
                reconciled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
                FOREIGN KEY (bank_account_id) REFERENCES {$table_accounts}(id) ON DELETE CASCADE,
                INDEX idx_org_account_date (org_id, bank_account_id, statement_date)
            ) {$charset_collate};",
        ];
    }

    public static function create_bank_account($org_id, $data) {
        global $wpdb;

        $org_id = intval($org_id);
        $name = sanitize_text_field($data['account_name'] ?? '');
        if ($org_id <= 0 || $name === '') {
            return new WP_Error('missing_field', 'Organization and account name are required');
        }

        $wpdb->insert(
            OraBooks_Database::table('bank_accounts'),
            [
                'org_id' => $org_id,
                'account_name' => $name,
                'account_number' => isset($data['account_number']) ? sanitize_text_field($data['account_number']) : null,
                'currency' => strtoupper(sanitize_text_field($data['currency'] ?? 'USD')),
                'current_balance' => round(floatval($data['current_balance'] ?? 0), 2),
                'is_active' => 1,
                'liquidity_pool_id' => !empty($data['liquidity_pool_id']) ? intval($data['liquidity_pool_id']) : null,
                'treasury_workflow_enabled' => !empty($data['treasury_workflow_enabled']) ? 1 : 0,
                'min_balance_threshold' => isset($data['min_balance_threshold']) ? floatval($data['min_balance_threshold']) : null,
                'target_balance' => isset($data['target_balance']) ? floatval($data['target_balance']) : null,
            ],
            ['%d', '%s', '%s', '%s', '%f', '%d', '%d', '%d', '%f', '%f']
        );

        $account_id = intval($wpdb->insert_id);
        orabooks_log_event('bank_account_created', 'Bank account created', 'info', [
            'bank_account_id' => $account_id,
            'account_name' => $name,
        ], orabooks_get_current_user_id(), $org_id);

        return self::get_bank_account($account_id, $org_id);
    }

    public static function get_bank_account($bank_account_id, $org_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . OraBooks_Database::table('bank_accounts') . " WHERE id = %d AND org_id = %d",
            intval($bank_account_id),
            intval($org_id)
        ));
    }

    public static function get_accounts_list($org_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . OraBooks_Database::table('bank_accounts') . " WHERE org_id = %d AND is_active = 1 ORDER BY account_name",
            intval($org_id)
        ));
    }

    public static function import_rows($org_id, $bank_account_id, $rows, $user_id = null) {
        global $wpdb;

        $org_id = intval($org_id);
        $bank_account_id = intval($bank_account_id);
        if ($org_id <= 0 || $bank_account_id <= 0 || !is_array($rows)) {
            return new WP_Error('invalid_import', 'Bank account and rows are required');
        }

        if (!orabooks_check_rate_limit('bank_import_' . $org_id, 10, 3600)) {
            return new WP_Error('rate_limit', 'Too many bank imports. Please try again later.');
        }

        $summary = [
            'total_rows' => count($rows),
            'inserted' => 0,
            'duplicates' => 0,
            'suggested_matches' => 0,
            'errors' => [],
        ];

        foreach ($rows as $index => $row) {
            $date = sanitize_text_field($row['date'] ?? $row['transaction_date'] ?? '');
            $amount = round(floatval($row['amount'] ?? 0), 2);
            $description = sanitize_textarea_field($row['description'] ?? '');
            $reference = sanitize_text_field($row['reference'] ?? '');

            if ($date === '' || $amount == 0.0) {
                $summary['errors'][] = ['row' => $index + 1, 'error' => 'date and non-zero amount are required'];
                continue;
            }

            if (self::transaction_exists($bank_account_id, $date, $amount, $reference)) {
                $summary['duplicates']++;
                continue;
            }

            $wpdb->insert(
                OraBooks_Database::table('bank_transactions'),
                [
                    'org_id' => $org_id,
                    'bank_account_id' => $bank_account_id,
                    'transaction_date' => $date,
                    'amount' => $amount,
                    'description' => $description,
                    'reference' => $reference ?: null,
                    'status' => 'unmatched',
                    'raw_data' => wp_json_encode($row),
                ],
                ['%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s']
            );

            $bank_transaction_id = intval($wpdb->insert_id);
            $summary['inserted']++;
            $suggested = self::suggest_match($org_id, $bank_transaction_id, [
                'transaction_date' => $date,
                'amount' => $amount,
                'description' => $description,
                'reference' => $reference,
            ]);
            if (!is_wp_error($suggested) && !empty($suggested['suggested'])) {
                $summary['suggested_matches']++;
            }
        }

        orabooks_log_event('bank_statement_imported', 'Bank statement rows imported', 'info', $summary, $user_id ?: orabooks_get_current_user_id(), $org_id);
        return $summary;
    }

    public static function suggest_match($org_id, $bank_transaction_id, $bank_transaction = null) {
        global $wpdb;

        if (!$bank_transaction) {
            $bank_transaction = self::get_bank_transaction($bank_transaction_id, $org_id);
        } elseif (is_array($bank_transaction)) {
            $bank_transaction = (object) $bank_transaction;
        }

        if (!$bank_transaction) {
            return new WP_Error('not_found', 'Bank transaction not found');
        }

        $candidate = self::find_rule_based_candidate($org_id, $bank_transaction);
        if (!$candidate) {
            return ['suggested' => false];
        }

        $wpdb->insert(
            OraBooks_Database::table('reconciliation_matches'),
            [
                'bank_transaction_id' => intval($bank_transaction_id),
                'transaction_type' => $candidate['transaction_type'],
                'transaction_id' => intval($candidate['transaction_id']),
                'matched_by' => 0,
                'confidence_score' => floatval($candidate['confidence_score']),
                'match_status' => 'suggested',
            ],
            ['%d', '%s', '%d', '%d', '%f', '%s']
        );

        orabooks_log_event('match_suggested', 'Bank transaction match suggested', 'info', [
            'bank_transaction_id' => intval($bank_transaction_id),
            'transaction_type' => $candidate['transaction_type'],
            'transaction_id' => intval($candidate['transaction_id']),
            'confidence_score' => floatval($candidate['confidence_score']),
        ], 0, intval($org_id));

        return [
            'suggested' => true,
            'match_id' => intval($wpdb->insert_id),
            'candidate' => $candidate,
        ];
    }

    public static function manual_match($org_id, $bank_transaction_id, $transaction_type, $transaction_id, $user_id) {
        global $wpdb;

        $bank_transaction = self::get_bank_transaction($bank_transaction_id, $org_id);
        if (!$bank_transaction) {
            return new WP_Error('not_found', 'Bank transaction not found');
        }

        if ($bank_transaction->status !== 'unmatched') {
            return new WP_Error('invalid_status', 'Only unmatched bank transactions can be matched');
        }

        $transaction_type = sanitize_text_field($transaction_type);
        if (!in_array($transaction_type, ['payment', 'expense', 'journal'], true)) {
            return new WP_Error('invalid_transaction_type', 'Invalid transaction type');
        }

        $wpdb->insert(
            OraBooks_Database::table('reconciliation_matches'),
            [
                'bank_transaction_id' => intval($bank_transaction_id),
                'transaction_type' => $transaction_type,
                'transaction_id' => intval($transaction_id),
                'matched_by' => intval($user_id),
                'confidence_score' => 100,
                'match_status' => 'confirmed',
            ],
            ['%d', '%s', '%d', '%d', '%f', '%s']
        );

        $wpdb->update(
            OraBooks_Database::table('bank_transactions'),
            ['status' => 'matched'],
            ['id' => intval($bank_transaction_id), 'org_id' => intval($org_id)],
            ['%s'],
            ['%d', '%d']
        );

        orabooks_log_event('match_manual', 'Bank transaction manually matched', 'info', [
            'bank_transaction_id' => intval($bank_transaction_id),
            'transaction_type' => $transaction_type,
            'transaction_id' => intval($transaction_id),
        ], intval($user_id), intval($org_id));

        return ['match_id' => intval($wpdb->insert_id), 'status' => 'matched'];
    }

    public static function skip_transaction($org_id, $bank_transaction_id, $reason, $user_id) {
        global $wpdb;

        $bank_transaction = self::get_bank_transaction($bank_transaction_id, $org_id);
        if (!$bank_transaction) {
            return new WP_Error('not_found', 'Bank transaction not found');
        }

        if ($bank_transaction->status === 'reconciled') {
            return new WP_Error('invalid_status', 'Reconciled transactions cannot be skipped');
        }

        $wpdb->update(
            OraBooks_Database::table('bank_transactions'),
            ['status' => 'skipped'],
            ['id' => intval($bank_transaction_id), 'org_id' => intval($org_id)],
            ['%s'],
            ['%d', '%d']
        );

        orabooks_log_event('transaction_skipped', 'Bank transaction skipped', 'info', [
            'bank_transaction_id' => intval($bank_transaction_id),
            'reason' => sanitize_textarea_field($reason),
        ], intval($user_id), intval($org_id));

        return true;
    }

    public static function finalize_reconciliation($org_id, $bank_account_id, $statement_date, $ending_balance, $user_id, $force = false, $note = '') {
        global $wpdb;

        $org_id = intval($org_id);
        $bank_account_id = intval($bank_account_id);
        $statement_date = sanitize_text_field($statement_date);
        $ending_balance = round(floatval($ending_balance), 2);

        $unmatched = self::get_unresolved_transactions($org_id, $bank_account_id, $statement_date);
        if (!empty($unmatched)) {
            return new WP_Error('unmatched_transactions', 'All bank transactions must be matched or skipped before reconciliation');
        }

        $system_balance = self::calculate_system_balance($org_id, $bank_account_id, $statement_date);
        $difference = round($ending_balance - $system_balance, 2);
        if (abs($difference) > 0.01 && !$force) {
            return new WP_Error('balance_mismatch', 'Ending balance does not match system balance');
        }

        $wpdb->query('START TRANSACTION');
        $wpdb->query($wpdb->prepare(
            "UPDATE " . OraBooks_Database::table('bank_transactions') . "
             SET status = 'reconciled'
             WHERE org_id = %d AND bank_account_id = %d AND transaction_date <= %s AND status = 'matched'",
            $org_id,
            $bank_account_id,
            $statement_date
        ));

        $wpdb->insert(
            OraBooks_Database::table('reconciliation_log'),
            [
                'org_id' => $org_id,
                'bank_account_id' => $bank_account_id,
                'statement_date' => $statement_date,
                'ending_balance' => $ending_balance,
                'system_balance' => $system_balance,
                'difference' => $difference,
                'note' => sanitize_textarea_field($note),
                'reconciled_by' => intval($user_id),
            ],
            ['%d', '%d', '%s', '%f', '%f', '%f', '%s', '%d']
        );
        $log_id = intval($wpdb->insert_id);

        $wpdb->update(
            OraBooks_Database::table('bank_accounts'),
            ['current_balance' => $ending_balance],
            ['id' => $bank_account_id, 'org_id' => $org_id],
            ['%f'],
            ['%d', '%d']
        );
        $wpdb->query('COMMIT');

        orabooks_log_event('reconciliation_completed', 'Bank reconciliation completed', 'info', [
            'bank_account_id' => $bank_account_id,
            'statement_date' => $statement_date,
            'ending_balance' => $ending_balance,
            'system_balance' => $system_balance,
            'difference' => $difference,
        ], intval($user_id), $org_id);

        return [
            'reconciliation_log_id' => $log_id,
            'ending_balance' => $ending_balance,
            'system_balance' => $system_balance,
            'difference' => $difference,
        ];
    }

    public static function get_transactions_list($org_id, $bank_account_id, $args = []) {
        global $wpdb;

        $where = 'org_id = %d AND bank_account_id = %d';
        $params = [intval($org_id), intval($bank_account_id)];
        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $params[] = sanitize_text_field($args['status']);
        }
        $limit = intval($args['limit'] ?? 100);
        $offset = intval($args['offset'] ?? 0);
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . OraBooks_Database::table('bank_transactions') . " WHERE {$where} ORDER BY transaction_date DESC, id DESC LIMIT %d OFFSET %d",
            $params
        ));
    }

    public static function get_recent_transactions($org_id, $args = []) {
        global $wpdb;

        $table_transactions = OraBooks_Database::table('bank_transactions');
        $table_accounts = OraBooks_Database::table('bank_accounts');
        $limit = intval($args['limit'] ?? 25);
        $offset = intval($args['offset'] ?? 0);

        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, a.account_name
             FROM {$table_transactions} t
             JOIN {$table_accounts} a ON t.bank_account_id = a.id
             WHERE t.org_id = %d
             ORDER BY t.transaction_date DESC, t.id DESC
             LIMIT %d OFFSET %d",
            intval($org_id),
            $limit,
            $offset
        ));
    }

    public static function get_recent_reconciliation_log($org_id, $args = []) {
        global $wpdb;

        $table = OraBooks_Database::table('reconciliation_log');
        $table_accounts = OraBooks_Database::table('bank_accounts');
        $limit = intval($args['limit'] ?? 10);

        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, a.account_name
             FROM {$table} l
             JOIN {$table_accounts} a ON l.bank_account_id = a.id
             WHERE l.org_id = %d
             ORDER BY l.statement_date DESC, l.id DESC
             LIMIT %d",
            intval($org_id),
            $limit
        ));
    }

    private static function transaction_exists($bank_account_id, $date, $amount, $reference) {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . OraBooks_Database::table('bank_transactions') . "
             WHERE bank_account_id = %d AND transaction_date = %s AND amount = %f AND COALESCE(reference, '') = %s",
            intval($bank_account_id),
            $date,
            floatval($amount),
            $reference
        ));
    }

    private static function get_bank_transaction($bank_transaction_id, $org_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . OraBooks_Database::table('bank_transactions') . " WHERE id = %d AND org_id = %d",
            intval($bank_transaction_id),
            intval($org_id)
        ));
    }

    private static function find_rule_based_candidate($org_id, $bank_transaction) {
        global $wpdb;

        $amount = abs(floatval($bank_transaction->amount));
        $date = sanitize_text_field($bank_transaction->transaction_date);
        $reference = sanitize_text_field($bank_transaction->reference ?? '');

        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT id, reference FROM " . OraBooks_Database::table('payments') . "
             WHERE org_id = %d AND ABS(amount) = %f AND payment_date BETWEEN DATE_SUB(%s, INTERVAL 3 DAY) AND DATE_ADD(%s, INTERVAL 3 DAY)
             ORDER BY ABS(DATEDIFF(payment_date, %s)) ASC LIMIT 1",
            intval($org_id),
            $amount,
            $date,
            $date,
            $date
        ));

        if ($payment) {
            $confidence = ($reference && isset($payment->reference) && stripos((string) $payment->reference, $reference) !== false) ? 98 : 85;
            return [
                'transaction_type' => 'payment',
                'transaction_id' => intval($payment->id),
                'confidence_score' => $confidence,
            ];
        }

        return null;
    }

    private static function get_unresolved_transactions($org_id, $bank_account_id, $statement_date) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM " . OraBooks_Database::table('bank_transactions') . "
             WHERE org_id = %d AND bank_account_id = %d AND transaction_date <= %s AND status = 'unmatched'",
            intval($org_id),
            intval($bank_account_id),
            sanitize_text_field($statement_date)
        ));
    }

    private static function calculate_system_balance($org_id, $bank_account_id, $statement_date) {
        global $wpdb;

        return round(floatval($wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM " . OraBooks_Database::table('bank_transactions') . "
             WHERE org_id = %d AND bank_account_id = %d AND transaction_date <= %s AND status IN ('matched','reconciled')",
            intval($org_id),
            intval($bank_account_id),
            sanitize_text_field($statement_date)
        ))), 2);
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

    public function ajax_accounts_list() {
        $user_id = $this->current_user_id();
        $org_id = intval($_GET['org_id'] ?? 0);
        $this->require_customer_org_access($user_id, $org_id);
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'view_reports')) {
            orabooks_json_error('Permission denied', 403);
        }
        orabooks_json_success(['accounts' => self::get_accounts_list($org_id)]);
    }

    public function ajax_account_create() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_customer_org_access($user_id, $org_id);
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_org_settings')) {
            orabooks_json_error('Permission denied', 403);
        }
        $result = self::create_bank_account($org_id, $_POST);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success(['account' => $result]);
    }

    public function ajax_import_rows() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_customer_org_access($user_id, $org_id);
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'submit_transaction')) {
            orabooks_json_error('Permission denied', 403);
        }
        $rows = json_decode(stripslashes($_POST['rows_json'] ?? '[]'), true);
        $result = self::import_rows($org_id, intval($_POST['bank_account_id'] ?? 0), is_array($rows) ? $rows : [], $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success($result);
    }

    public function ajax_transactions_list() {
        $user_id = $this->current_user_id();
        $org_id = intval($_GET['org_id'] ?? 0);
        $this->require_customer_org_access($user_id, $org_id);
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'view_reports')) {
            orabooks_json_error('Permission denied', 403);
        }
        orabooks_json_success(['transactions' => self::get_transactions_list($org_id, intval($_GET['bank_account_id'] ?? 0), $_GET)]);
    }

    public function ajax_manual_match() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_customer_org_access($user_id, $org_id);
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'submit_transaction')) {
            orabooks_json_error('Permission denied', 403);
        }
        $result = self::manual_match($org_id, intval($_POST['bank_transaction_id'] ?? 0), $_POST['transaction_type'] ?? '', intval($_POST['transaction_id'] ?? 0), $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success($result);
    }

    public function ajax_skip_transaction() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_customer_org_access($user_id, $org_id);
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'submit_transaction')) {
            orabooks_json_error('Permission denied', 403);
        }
        $result = self::skip_transaction($org_id, intval($_POST['bank_transaction_id'] ?? 0), $_POST['reason'] ?? '', $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success([], 'Bank transaction skipped');
    }

    public function ajax_finalize_reconciliation() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_customer_org_access($user_id, $org_id);
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_org_settings')) {
            orabooks_json_error('Permission denied', 403);
        }
        $result = self::finalize_reconciliation(
            $org_id,
            intval($_POST['bank_account_id'] ?? 0),
            sanitize_text_field($_POST['statement_date'] ?? ''),
            floatval($_POST['ending_balance'] ?? 0),
            $user_id,
            !empty($_POST['force']),
            sanitize_textarea_field($_POST['note'] ?? '')
        );
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success($result);
    }
}
