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
            add_action('wp_ajax_orabooks_bank_import_csv', [self::$instance, 'ajax_import_csv']);
            add_action('wp_ajax_nopriv_orabooks_bank_import_csv', [self::$instance, 'ajax_import_csv']);
            add_action('wp_ajax_orabooks_bank_confirm_match', [self::$instance, 'ajax_confirm_match']);
            add_action('wp_ajax_nopriv_orabooks_bank_confirm_match', [self::$instance, 'ajax_confirm_match']);
            add_action('wp_ajax_orabooks_bank_create_transaction', [self::$instance, 'ajax_create_transaction']);
            add_action('wp_ajax_nopriv_orabooks_bank_create_transaction', [self::$instance, 'ajax_create_transaction']);
            add_action('wp_ajax_orabooks_bank_connect_feed', [self::$instance, 'ajax_connect_feed']);
            add_action('wp_ajax_nopriv_orabooks_bank_connect_feed', [self::$instance, 'ajax_connect_feed']);
            add_action('wp_ajax_orabooks_bank_feeds_list', [self::$instance, 'ajax_feeds_list']);
            add_action('wp_ajax_nopriv_orabooks_bank_feeds_list', [self::$instance, 'ajax_feeds_list']);
            add_action('wp_ajax_orabooks_bank_account_summary', [self::$instance, 'ajax_account_summary']);
            add_action('wp_ajax_nopriv_orabooks_bank_account_summary', [self::$instance, 'ajax_account_summary']);
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

    /**
     * Idempotent schema upgrades for existing installs.
     */
    public static function ensure_schema() {
        if (self::get_schema_flag('orabooks_sl031_bank_schema_v1') === '1') {
            return;
        }

        global $wpdb;
        $upgrade = ABSPATH . 'wp-admin/includes/upgrade.php';
        if (file_exists($upgrade)) {
            require_once $upgrade;
            foreach (self::get_create_table_sql() as $sql) {
                dbDelta($sql);
            }
        }

        $table = OraBooks_Database::table('bank_transactions');
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
            if (!in_array('external_id', $columns, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN external_id VARCHAR(128) NULL AFTER reference");
                $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_external_id (bank_account_id, external_id)");
            }
        }

        self::set_schema_flag('orabooks_sl031_bank_schema_v1', '1');
    }

    private static function get_schema_flag($key) {
        if (function_exists('is_multisite') && is_multisite() && function_exists('get_site_option')) {
            return get_site_option($key);
        }
        return get_option($key);
    }

    private static function set_schema_flag($key, $value) {
        if (function_exists('is_multisite') && is_multisite() && function_exists('update_site_option')) {
            update_site_option($key, $value);
            return;
        }
        update_option($key, $value, false);
    }

    public static function parse_csv_content($content) {
        $content = trim((string) $content);
        if ($content === '') {
            return new WP_Error('empty_csv', 'CSV file is empty');
        }

        if (strlen($content) > 10485760) {
            return new WP_Error('file_too_large', 'CSV file exceeds 10MB limit');
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        $rows = [];
        $header = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $fields = str_getcsv($line);
            if ($header === null) {
                $normalized = array_map(function ($value) {
                    return strtolower(trim((string) $value));
                }, $fields);
                if (in_array('date', $normalized, true) || in_array('transaction_date', $normalized, true)) {
                    $header = $normalized;
                    continue;
                }
            }
            if ($header) {
                $mapped = [];
                foreach ($header as $index => $column) {
                    $mapped[$column] = $fields[$index] ?? '';
                }
                $rows[] = [
                    'date' => $mapped['date'] ?? $mapped['transaction_date'] ?? '',
                    'amount' => $mapped['amount'] ?? '',
                    'description' => $mapped['description'] ?? $mapped['memo'] ?? '',
                    'reference' => $mapped['reference'] ?? $mapped['ref'] ?? '',
                ];
            } else {
                $rows[] = [
                    'date' => $fields[0] ?? '',
                    'amount' => $fields[1] ?? '',
                    'description' => $fields[2] ?? '',
                    'reference' => $fields[3] ?? '',
                ];
            }
        }

        if (empty($rows)) {
            return new WP_Error('invalid_csv', 'No data rows found in CSV');
        }

        return $rows;
    }

    public static function import_csv($org_id, $bank_account_id, $csv_content, $user_id = null) {
        $rows = self::parse_csv_content($csv_content);
        if (is_wp_error($rows)) {
            return $rows;
        }
        return self::import_rows($org_id, $bank_account_id, $rows, $user_id);
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
            $candidate = self::find_ai_candidate($org_id, $bank_transaction);
        }
        if (!$candidate) {
            return ['suggested' => false];
        }

        if (self::has_suggested_match(intval($bank_transaction_id))) {
            return ['suggested' => false, 'duplicate' => true];
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
        if (!in_array($transaction_type, ['payment', 'expense', 'journal', 'commission_payout', 'vendor_payment'], true)) {
            return new WP_Error('invalid_transaction_type', 'Invalid transaction type');
        }

        if ($transaction_type === 'commission_payout') {
            if (!class_exists('OraBooks_Commission')) {
                return new WP_Error('commission_unavailable', 'Commission module unavailable');
            }

            $settle = OraBooks_Commission::settle_payout(
                intval($transaction_id),
                intval($bank_transaction_id),
                $bank_transaction->transaction_date
            );
            if (is_wp_error($settle)) {
                return $settle;
            }
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

    public static function confirm_suggested_match($org_id, $bank_transaction_id, $match_id, $user_id) {
        global $wpdb;

        $bank_transaction = self::get_bank_transaction($bank_transaction_id, $org_id);
        if (!$bank_transaction) {
            return new WP_Error('not_found', 'Bank transaction not found');
        }
        if ($bank_transaction->status !== 'unmatched') {
            return new WP_Error('invalid_status', 'Only unmatched bank transactions can be matched');
        }

        $match = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . OraBooks_Database::table('reconciliation_matches') . "
             WHERE id = %d AND bank_transaction_id = %d AND match_status = 'suggested'",
            intval($match_id),
            intval($bank_transaction_id)
        ));
        if (!$match) {
            return new WP_Error('not_found', 'Suggested match not found');
        }

        $wpdb->update(
            OraBooks_Database::table('reconciliation_matches'),
            [
                'match_status' => 'confirmed',
                'matched_by' => intval($user_id),
                'matched_at' => current_time('mysql'),
            ],
            ['id' => intval($match_id)],
            ['%s', '%d', '%s'],
            ['%d']
        );

        $wpdb->update(
            OraBooks_Database::table('bank_transactions'),
            ['status' => 'matched'],
            ['id' => intval($bank_transaction_id), 'org_id' => intval($org_id)],
            ['%s'],
            ['%d', '%d']
        );

        orabooks_log_event('match_manual', 'Suggested bank match confirmed', 'info', [
            'bank_transaction_id' => intval($bank_transaction_id),
            'match_id' => intval($match_id),
            'transaction_type' => $match->transaction_type,
            'transaction_id' => intval($match->transaction_id),
        ], intval($user_id), intval($org_id));

        return ['match_id' => intval($match_id), 'status' => 'matched'];
    }

    public static function create_transaction_from_bank($org_id, $bank_transaction_id, $transaction_type, $user_id, $extra = []) {
        $bank_transaction = self::get_bank_transaction($bank_transaction_id, $org_id);
        if (!$bank_transaction) {
            return new WP_Error('not_found', 'Bank transaction not found');
        }
        if ($bank_transaction->status !== 'unmatched') {
            return new WP_Error('invalid_status', 'Only unmatched bank transactions can create linked transactions');
        }

        $amount = abs(floatval($bank_transaction->amount));
        $transaction_type = sanitize_key($transaction_type);
        $created_id = null;
        $match_type = $transaction_type;

        if ($transaction_type === 'expense') {
            if (!class_exists('OraBooks_Expenses')) {
                return new WP_Error('expense_unavailable', 'Expense module unavailable');
            }
            $vendor = sanitize_text_field($extra['vendor'] ?? ($bank_transaction->description ?: 'Bank transaction'));
            $created = OraBooks_Expenses::create_draft_from_voice($org_id, $user_id, [
                'vendor' => $vendor,
                'transaction_date' => $bank_transaction->transaction_date,
                'total_amount' => $amount,
                'subtotal' => $amount,
                'tax_amount' => 0,
                'tax_rate' => 0,
                'currency' => 'USD',
                'payment_method' => 'Bank',
                'category' => sanitize_text_field($extra['category'] ?? 'General'),
                'description' => sanitize_textarea_field($bank_transaction->description ?: 'Created from bank reconciliation'),
            ], 100, 'low');
            if (is_wp_error($created)) {
                return $created;
            }
            $created_id = (int) ($created['id'] ?? 0);
            $match_type = 'expense';
        } elseif ($transaction_type === 'invoice') {
            if (!class_exists('OraBooks_Customers')) {
                return new WP_Error('invoice_unavailable', 'Invoice module unavailable');
            }
            $customer_id = (int) ($extra['customer_id'] ?? 0);
            if ($customer_id <= 0) {
                return new WP_Error('missing_customer', 'Customer is required to create an invoice');
            }
            $created = OraBooks_Customers::create_invoice($org_id, [
                'customer_id' => $customer_id,
                'invoice_date' => $bank_transaction->transaction_date,
                'subtotal_amount' => $amount,
                'description' => sanitize_textarea_field($bank_transaction->description ?: 'Created from bank reconciliation'),
                'workflow_status' => 'draft',
            ]);
            if (is_wp_error($created)) {
                return $created;
            }
            $created_id = is_object($created) ? (int) ($created->id ?? 0) : (int) ($created['id'] ?? 0);
            $match_type = 'payment';
        } else {
            return new WP_Error('invalid_transaction_type', 'Supported types: expense, invoice');
        }

        if ($created_id <= 0) {
            return new WP_Error('create_failed', 'Failed to create linked transaction');
        }

        $match = self::manual_match($org_id, $bank_transaction_id, $match_type, $created_id, $user_id);
        if (is_wp_error($match)) {
            return $match;
        }

        orabooks_log_event('bank_transaction_created', 'System transaction created from bank entry', 'info', [
            'bank_transaction_id' => intval($bank_transaction_id),
            'transaction_type' => $match_type,
            'transaction_id' => $created_id,
        ], intval($user_id), intval($org_id));

        return [
            'transaction_type' => $match_type,
            'transaction_id' => $created_id,
            'match' => $match,
        ];
    }

    public static function connect_bank_feed($org_id, $bank_account_id, $provider, $user_id) {
        global $wpdb;

        $provider = sanitize_key($provider);
        if (!in_array($provider, ['plaid', 'yodlee'], true)) {
            return new WP_Error('invalid_provider', 'Supported providers: plaid, yodlee');
        }

        if (!self::get_bank_account($bank_account_id, $org_id)) {
            return new WP_Error('not_found', 'Bank account not found');
        }

        $table = OraBooks_Database::table('bank_feeds');
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d AND bank_account_id = %d AND provider = %s",
            intval($org_id),
            intval($bank_account_id),
            $provider
        ));

        if ($existing) {
            return $existing;
        }

        $wpdb->insert(
            $table,
            [
                'org_id' => intval($org_id),
                'bank_account_id' => intval($bank_account_id),
                'provider' => $provider,
                'status' => 'pending_oauth',
            ],
            ['%d', '%d', '%s', '%s']
        );

        orabooks_log_event('bank_feed_connect_requested', 'Bank feed connection requested', 'info', [
            'bank_account_id' => intval($bank_account_id),
            'provider' => $provider,
        ], intval($user_id), intval($org_id));

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            intval($wpdb->insert_id)
        ));
    }

    public static function get_feeds_list($org_id, $bank_account_id = 0) {
        global $wpdb;

        $table = OraBooks_Database::table('bank_feeds');
        if (intval($bank_account_id) > 0) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT id, org_id, bank_account_id, provider, last_sync_at, status, created_at
                 FROM {$table} WHERE org_id = %d AND bank_account_id = %d ORDER BY id DESC",
                intval($org_id),
                intval($bank_account_id)
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, org_id, bank_account_id, provider, last_sync_at, status, created_at
             FROM {$table} WHERE org_id = %d ORDER BY id DESC",
            intval($org_id)
        ));
    }

    public static function get_account_reconciliation_summary($org_id, $bank_account_id, $statement_date = null) {
        $account = self::get_bank_account($bank_account_id, $org_id);
        if (!$account) {
            return new WP_Error('not_found', 'Bank account not found');
        }

        $statement_date = $statement_date ?: current_time('Y-m-d');
        $system_balance = self::calculate_system_balance($org_id, $bank_account_id, $statement_date);

        return [
            'bank_account_id' => intval($bank_account_id),
            'account_name' => $account->account_name,
            'bank_balance' => round(floatval($account->current_balance), 2),
            'system_balance' => $system_balance,
            'difference' => round(floatval($account->current_balance) - $system_balance, 2),
            'statement_date' => $statement_date,
            'unmatched_count' => count(self::get_unresolved_transactions($org_id, $bank_account_id, $statement_date)),
        ];
    }

    public static function enrich_transactions_with_suggestions(array $transactions) {
        if (empty($transactions)) {
            return $transactions;
        }

        global $wpdb;
        $ids = array_map(function ($row) {
            return intval(is_object($row) ? $row->id : ($row['id'] ?? 0));
        }, $transactions);
        $ids = array_values(array_filter($ids));
        if (empty($ids)) {
            return $transactions;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $matches = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . OraBooks_Database::table('reconciliation_matches') . "
             WHERE bank_transaction_id IN ({$placeholders}) AND match_status = 'suggested'
             ORDER BY confidence_score DESC, id DESC",
            ...$ids
        ));

        $grouped = [];
        foreach ($matches ?: [] as $match) {
            $grouped[intval($match->bank_transaction_id)][] = self::format_match($match);
        }

        foreach ($transactions as $index => $transaction) {
            $txn_id = intval(is_object($transaction) ? $transaction->id : ($transaction['id'] ?? 0));
            $suggestions = $grouped[$txn_id] ?? [];
            if (is_object($transaction)) {
                $transaction->suggestions = $suggestions;
            } else {
                $transactions[$index]['suggestions'] = $suggestions;
            }
        }

        return $transactions;
    }

    private static function format_match($match) {
        return [
            'id' => (int) $match->id,
            'transaction_type' => $match->transaction_type,
            'transaction_id' => (int) $match->transaction_id,
            'confidence_score' => (float) $match->confidence_score,
            'match_status' => $match->match_status,
            'matched_by' => (int) $match->matched_by,
        ];
    }

    private static function has_suggested_match($bank_transaction_id) {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . OraBooks_Database::table('reconciliation_matches') . "
             WHERE bank_transaction_id = %d AND match_status = 'suggested' LIMIT 1",
            intval($bank_transaction_id)
        ));
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

        $where = 'org_id = %d';
        $params = [intval($org_id)];
        if (intval($bank_account_id) > 0) {
            $where .= ' AND bank_account_id = %d';
            $params[] = intval($bank_account_id);
        }
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

    private function require_bank_permission($user_id, $org_id, $permissions) {
        if ($org_id <= 0) {
            orabooks_json_error('Organization ID required', 400);
        }

        $this->require_customer_org_access($user_id, $org_id);
        if (current_user_can('manage_options')) {
            return;
        }

        foreach ((array) $permissions as $permission) {
            if (OraBooks_RBAC::require_permission($user_id, $org_id, $permission)) {
                return;
            }
        }

        orabooks_json_error('Permission denied', 403);
    }

    public function ajax_accounts_list() {
        $user_id = $this->current_user_id();
        $org_id = intval($_GET['org_id'] ?? 0);
        $this->require_bank_permission($user_id, $org_id, ['view_reports']);
        orabooks_json_success(['accounts' => self::get_accounts_list($org_id)]);
    }

    public function ajax_account_create() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_bank_permission($user_id, $org_id, ['manage_org_settings']);
        $result = self::create_bank_account($org_id, $_POST);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success(['account' => $result]);
    }

    public function ajax_import_rows() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_bank_permission($user_id, $org_id, ['submit_transaction', 'manage_org_settings']);
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
        $this->require_bank_permission($user_id, $org_id, ['view_reports']);
        orabooks_json_success(['transactions' => self::get_transactions_list($org_id, intval($_GET['bank_account_id'] ?? 0), $_GET)]);
    }

    public function ajax_manual_match() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_bank_permission($user_id, $org_id, ['submit_transaction', 'approve_journal']);
        $result = self::manual_match($org_id, intval($_POST['bank_transaction_id'] ?? 0), $_POST['transaction_type'] ?? '', intval($_POST['transaction_id'] ?? 0), $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success($result);
    }

    public function ajax_skip_transaction() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_bank_permission($user_id, $org_id, ['submit_transaction', 'approve_journal']);
        $result = self::skip_transaction($org_id, intval($_POST['bank_transaction_id'] ?? 0), $_POST['reason'] ?? '', $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success([], 'Bank transaction skipped');
    }

    public function ajax_finalize_reconciliation() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_bank_permission($user_id, $org_id, ['manage_org_settings', 'approve_journal']);
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
