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
            self::ensure_schema();

            add_action('wp_ajax_orabooks_bank_accounts_list', [self::$instance, 'ajax_accounts_list']);
            add_action('wp_ajax_orabooks_bank_account_create', [self::$instance, 'ajax_account_create']);
            add_action('wp_ajax_orabooks_bank_import_rows', [self::$instance, 'ajax_import_rows']);
            add_action('wp_ajax_orabooks_bank_import_csv', [self::$instance, 'ajax_import_csv']);
            add_action('wp_ajax_orabooks_bank_transactions_list', [self::$instance, 'ajax_transactions_list']);
            add_action('wp_ajax_orabooks_bank_match', [self::$instance, 'ajax_manual_match']);
            add_action('wp_ajax_orabooks_bank_create_transaction', [self::$instance, 'ajax_create_transaction']);
            add_action('wp_ajax_orabooks_bank_skip', [self::$instance, 'ajax_skip_transaction']);
            add_action('wp_ajax_orabooks_bank_feed_connect', [self::$instance, 'ajax_feed_connect']);
            add_action('wp_ajax_orabooks_bank_reconcile', [self::$instance, 'ajax_finalize_reconciliation']);
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
                external_id VARCHAR(120) NULL,
                import_source VARCHAR(20) DEFAULT 'csv',
                status ENUM('unmatched','matched','reconciled','skipped') DEFAULT 'unmatched',
                treasury_workflow_id BIGINT UNSIGNED NULL,
                liquidity_pool_id BIGINT UNSIGNED NULL,
                is_internal_transfer TINYINT(1) DEFAULT 0,
                raw_data JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
                FOREIGN KEY (bank_account_id) REFERENCES {$table_accounts}(id) ON DELETE CASCADE,
                UNIQUE KEY uk_bank_dedupe (bank_account_id, transaction_date, amount, reference),
                UNIQUE KEY uk_bank_external (bank_account_id, external_id),
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
                token_expires_at TIMESTAMP NULL,
                last_sync_at TIMESTAMP NULL,
                status VARCHAR(20) DEFAULT 'inactive',
                last_error TEXT NULL,
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

    public static function ensure_schema() {
        global $wpdb;

        $table_transactions = OraBooks_Database::table('bank_transactions');
        $table_feeds = OraBooks_Database::table('bank_feeds');

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_transactions)) === $table_transactions) {
            $columns = $wpdb->get_col("DESCRIBE {$table_transactions}", 0);
            if (is_array($columns) && !in_array('external_id', $columns, true)) {
                $wpdb->query("ALTER TABLE {$table_transactions} ADD COLUMN external_id VARCHAR(120) NULL AFTER reference");
            }
            if (is_array($columns) && !in_array('import_source', $columns, true)) {
                $wpdb->query("ALTER TABLE {$table_transactions} ADD COLUMN import_source VARCHAR(20) DEFAULT 'csv' AFTER external_id");
            }

            $indexes = $wpdb->get_results("SHOW INDEX FROM {$table_transactions}");
            $has_external_idx = false;
            foreach ((array) $indexes as $index) {
                if (!empty($index->Key_name) && $index->Key_name === 'uk_bank_external') {
                    $has_external_idx = true;
                    break;
                }
            }
            if (!$has_external_idx) {
                $wpdb->query("ALTER TABLE {$table_transactions} ADD UNIQUE KEY uk_bank_external (bank_account_id, external_id)");
            }
        }

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_feeds)) === $table_feeds) {
            $feed_columns = $wpdb->get_col("DESCRIBE {$table_feeds}", 0);
            if (is_array($feed_columns) && !in_array('token_expires_at', $feed_columns, true)) {
                $wpdb->query("ALTER TABLE {$table_feeds} ADD COLUMN token_expires_at TIMESTAMP NULL AFTER refresh_token");
            }
            if (is_array($feed_columns) && !in_array('last_error', $feed_columns, true)) {
                $wpdb->query("ALTER TABLE {$table_feeds} ADD COLUMN last_error TEXT NULL AFTER status");
            }
        }
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
            $external_id = sanitize_text_field($row['external_id'] ?? '');
            $import_source = sanitize_text_field($row['import_source'] ?? 'csv');

            if ($date === '' || $amount == 0.0) {
                $summary['errors'][] = ['row' => $index + 1, 'error' => 'date and non-zero amount are required'];
                continue;
            }

            if (self::transaction_exists($bank_account_id, $date, $amount, $reference, $external_id)) {
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
                    'external_id' => $external_id ?: null,
                    'import_source' => $import_source ?: 'csv',
                    'status' => 'unmatched',
                    'raw_data' => wp_json_encode($row),
                ],
                ['%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s']
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

    public static function import_csv($org_id, $bank_account_id, $file, $user_id = null) {
        $org_id = intval($org_id);
        $bank_account_id = intval($bank_account_id);

        if ($org_id <= 0 || $bank_account_id <= 0) {
            return new WP_Error('invalid_import', 'Organization and bank account are required');
        }

        if (empty($file) || !is_array($file) || !empty($file['error']) || empty($file['tmp_name'])) {
            return new WP_Error('invalid_file', 'CSV file upload failed');
        }

        if (!empty($file['size']) && intval($file['size']) > 10 * 1024 * 1024) {
            return new WP_Error('file_too_large', 'CSV max size is 10MB');
        }

        $csv_rows = self::parse_csv_file($file['tmp_name']);
        if (is_wp_error($csv_rows)) {
            return $csv_rows;
        }

        return self::import_rows($org_id, $bank_account_id, $csv_rows, $user_id);
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

        $existing_confirmed = intval($wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . OraBooks_Database::table('reconciliation_matches') . " WHERE bank_transaction_id = %d AND match_status = 'confirmed' LIMIT 1",
            intval($bank_transaction_id)
        )));
        if ($existing_confirmed > 0) {
            return new WP_Error('already_matched', 'Bank transaction already has a confirmed match');
        }

        $transaction_type = sanitize_text_field($transaction_type);
        if (!in_array($transaction_type, ['payment', 'expense', 'invoice', 'journal', 'commission_payout'], true)) {
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

    public static function create_transaction_from_bank_entry($org_id, $bank_transaction_id, $transaction_type, $data, $user_id) {
        $bank_transaction = self::get_bank_transaction($bank_transaction_id, $org_id);
        if (!$bank_transaction) {
            return new WP_Error('not_found', 'Bank transaction not found');
        }

        if ($bank_transaction->status !== 'unmatched') {
            return new WP_Error('invalid_status', 'Only unmatched bank transactions can create linked records');
        }

        $transaction_type = sanitize_text_field($transaction_type);
        $created_id = 0;

        if ($transaction_type === 'expense') {
            if (!class_exists('OraBooks_Expenses')) {
                return new WP_Error('missing_module', 'Expenses module unavailable');
            }

            $created = OraBooks_Expenses::create_draft_from_voice(intval($org_id), intval($user_id), [
                'vendor' => sanitize_text_field($data['vendor'] ?? 'Bank Entry'),
                'transaction_date' => sanitize_text_field($bank_transaction->transaction_date),
                'amount' => abs(floatval($bank_transaction->amount)),
                'currency' => sanitize_text_field($data['currency'] ?? 'USD'),
                'payment_method' => 'Bank',
                'category' => sanitize_text_field($data['category'] ?? 'General'),
                'description' => sanitize_textarea_field($data['description'] ?? ($bank_transaction->description ?: 'Created from bank reconciliation')),
            ], 95, 'low');

            if (is_wp_error($created)) {
                return $created;
            }

            $created_id = intval($created['id'] ?? 0);
        } elseif ($transaction_type === 'invoice') {
            if (!class_exists('OraBooks_Customers')) {
                return new WP_Error('missing_module', 'Customers/Invoice module unavailable');
            }

            $customer_id = intval($data['customer_id'] ?? 0);
            if ($customer_id <= 0) {
                $customer_name = sanitize_text_field($data['customer_name'] ?? ($bank_transaction->description ?: 'Bank Customer'));
                $customer = OraBooks_Customers::create_customer(intval($org_id), [
                    'display_name' => $customer_name,
                    'contact_email' => sanitize_email($data['customer_email'] ?? ''),
                    'default_currency' => sanitize_text_field($data['currency'] ?? 'USD'),
                ]);
                if (is_wp_error($customer)) {
                    return $customer;
                }
                $customer_id = intval($customer->id ?? 0);
            }

            $created = OraBooks_Customers::create_invoice(intval($org_id), [
                'customer_id' => $customer_id,
                'invoice_date' => sanitize_text_field($bank_transaction->transaction_date),
                'transaction_date' => sanitize_text_field($bank_transaction->transaction_date),
                'description' => sanitize_textarea_field($data['description'] ?? ($bank_transaction->description ?: 'Created from bank reconciliation')),
                'subtotal_amount' => abs(floatval($bank_transaction->amount)),
                'total_amount' => abs(floatval($bank_transaction->amount)),
                'currency' => sanitize_text_field($data['currency'] ?? 'USD'),
            ]);

            if (is_wp_error($created)) {
                return $created;
            }

            $created_id = intval($created->id ?? 0);
        } else {
            return new WP_Error('invalid_transaction_type', 'Only expense or invoice creation is supported');
        }

        if ($created_id <= 0) {
            return new WP_Error('create_failed', 'Failed to create transaction');
        }

        $match_type = $transaction_type === 'invoice' ? 'invoice' : 'expense';
        $match = self::manual_match($org_id, $bank_transaction_id, $match_type, $created_id, $user_id);
        if (is_wp_error($match)) {
            return $match;
        }

        return [
            'transaction_type' => $transaction_type,
            'transaction_id' => $created_id,
            'match' => $match,
        ];
    }

    public static function connect_feed($org_id, $bank_account_id, $provider, $access_token, $refresh_token, $token_expires_at = null) {
        global $wpdb;

        $provider = strtolower(sanitize_text_field($provider));
        if (!in_array($provider, ['plaid', 'yodlee'], true)) {
            return new WP_Error('invalid_provider', 'Provider must be plaid or yodlee');
        }

        $org_id = intval($org_id);
        $bank_account_id = intval($bank_account_id);
        if ($org_id <= 0 || $bank_account_id <= 0) {
            return new WP_Error('invalid_feed', 'Organization and bank account are required');
        }

        $table = OraBooks_Database::table('bank_feeds');
        $existing_id = intval($wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE org_id = %d AND bank_account_id = %d AND provider = %s LIMIT 1",
            $org_id,
            $bank_account_id,
            $provider
        )));

        $payload = [
            'access_token' => sanitize_text_field($access_token),
            'refresh_token' => sanitize_text_field($refresh_token),
            'token_expires_at' => $token_expires_at ? sanitize_text_field($token_expires_at) : null,
            'status' => 'active',
            'last_error' => null,
            'last_sync_at' => null,
        ];

        if ($existing_id > 0) {
            $wpdb->update($table, $payload, ['id' => $existing_id], ['%s', '%s', '%s', '%s', '%s', '%s'], ['%d']);
            $feed_id = $existing_id;
        } else {
            $wpdb->insert($table, array_merge($payload, [
                'org_id' => $org_id,
                'bank_account_id' => $bank_account_id,
                'provider' => $provider,
            ]), ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']);
            $feed_id = intval($wpdb->insert_id);
        }

        orabooks_log_event('bank_feed_connected', 'Bank feed connected', 'info', [
            'bank_feed_id' => $feed_id,
            'bank_account_id' => $bank_account_id,
            'provider' => $provider,
        ], orabooks_get_current_user_id(), $org_id);

        return ['feed_id' => $feed_id, 'provider' => $provider, 'status' => 'active'];
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
            "SELECT t.*,
                (SELECT rm.transaction_type FROM " . OraBooks_Database::table('reconciliation_matches') . " rm WHERE rm.bank_transaction_id = t.id AND rm.match_status = 'suggested' ORDER BY rm.confidence_score DESC, rm.id DESC LIMIT 1) AS suggested_transaction_type,
                (SELECT rm.transaction_id FROM " . OraBooks_Database::table('reconciliation_matches') . " rm WHERE rm.bank_transaction_id = t.id AND rm.match_status = 'suggested' ORDER BY rm.confidence_score DESC, rm.id DESC LIMIT 1) AS suggested_transaction_id,
                (SELECT rm.confidence_score FROM " . OraBooks_Database::table('reconciliation_matches') . " rm WHERE rm.bank_transaction_id = t.id AND rm.match_status = 'suggested' ORDER BY rm.confidence_score DESC, rm.id DESC LIMIT 1) AS suggested_confidence
             FROM " . OraBooks_Database::table('bank_transactions') . " t
             WHERE {$where}
             ORDER BY t.transaction_date DESC, t.id DESC LIMIT %d OFFSET %d",
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
            "SELECT t.*, a.account_name,
                (SELECT rm.transaction_type FROM " . OraBooks_Database::table('reconciliation_matches') . " rm WHERE rm.bank_transaction_id = t.id AND rm.match_status = 'suggested' ORDER BY rm.confidence_score DESC, rm.id DESC LIMIT 1) AS suggested_transaction_type,
                (SELECT rm.transaction_id FROM " . OraBooks_Database::table('reconciliation_matches') . " rm WHERE rm.bank_transaction_id = t.id AND rm.match_status = 'suggested' ORDER BY rm.confidence_score DESC, rm.id DESC LIMIT 1) AS suggested_transaction_id,
                (SELECT rm.confidence_score FROM " . OraBooks_Database::table('reconciliation_matches') . " rm WHERE rm.bank_transaction_id = t.id AND rm.match_status = 'suggested' ORDER BY rm.confidence_score DESC, rm.id DESC LIMIT 1) AS suggested_confidence
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

    private static function transaction_exists($bank_account_id, $date, $amount, $reference, $external_id = '') {
        global $wpdb;

        $external_id = sanitize_text_field($external_id);
        if ($external_id !== '') {
            $existing_external = intval($wpdb->get_var($wpdb->prepare(
                "SELECT id FROM " . OraBooks_Database::table('bank_transactions') . " WHERE bank_account_id = %d AND external_id = %s LIMIT 1",
                intval($bank_account_id),
                $external_id
            )));
            if ($existing_external > 0) {
                return true;
            }
        }

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

    private static function find_ai_candidate($org_id, $bank_transaction) {
        global $wpdb;

        $amount = abs(floatval($bank_transaction->amount));
        $date = sanitize_text_field($bank_transaction->transaction_date);
        $needle = trim(strtolower((string) ($bank_transaction->description ?: $bank_transaction->reference ?: '')));
        if ($needle === '') {
            return null;
        }

        $candidates = [];

        $payments = $wpdb->get_results($wpdb->prepare(
            "SELECT id, COALESCE(reference, '') AS text_ref FROM " . OraBooks_Database::table('payments') . "
             WHERE org_id = %d AND ABS(amount) = %f AND payment_date BETWEEN DATE_SUB(%s, INTERVAL 10 DAY) AND DATE_ADD(%s, INTERVAL 10 DAY)
             ORDER BY ABS(DATEDIFF(payment_date, %s)) ASC LIMIT 8",
            intval($org_id),
            $amount,
            $date,
            $date,
            $date
        ));

        foreach ((array) $payments as $payment) {
            $score = self::similarity_score($needle, strtolower((string) ($payment->text_ref ?? '')));
            if ($score >= 60) {
                $candidates[] = [
                    'transaction_type' => 'payment',
                    'transaction_id' => intval($payment->id),
                    'confidence_score' => min(95, max(60, $score)),
                ];
            }
        }

        $expenses = $wpdb->get_results($wpdb->prepare(
            "SELECT id, COALESCE(description, '') AS text_ref FROM " . OraBooks_Database::table('expenses') . "
             WHERE org_id = %d AND ABS(total_amount) = %f AND transaction_date BETWEEN DATE_SUB(%s, INTERVAL 10 DAY) AND DATE_ADD(%s, INTERVAL 10 DAY)
             ORDER BY ABS(DATEDIFF(transaction_date, %s)) ASC LIMIT 8",
            intval($org_id),
            $amount,
            $date,
            $date,
            $date
        ));

        foreach ((array) $expenses as $expense) {
            $score = self::similarity_score($needle, strtolower((string) ($expense->text_ref ?? '')));
            if ($score >= 60) {
                $candidates[] = [
                    'transaction_type' => 'expense',
                    'transaction_id' => intval($expense->id),
                    'confidence_score' => min(92, max(60, $score)),
                ];
            }
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, static function ($a, $b) {
            return (float) $b['confidence_score'] <=> (float) $a['confidence_score'];
        });

        return $candidates[0];
    }

    private static function similarity_score($left, $right) {
        $left = trim((string) $left);
        $right = trim((string) $right);
        if ($left === '' || $right === '') {
            return 0;
        }

        $percent = 0.0;
        similar_text($left, $right, $percent);
        return round(floatval($percent), 2);
    }

    private static function parse_csv_file($tmp_name) {
        $handle = @fopen($tmp_name, 'r');
        if (!$handle) {
            return new WP_Error('invalid_csv', 'Unable to read CSV file');
        }

        $header = fgetcsv($handle);
        if (!is_array($header) || empty($header)) {
            fclose($handle);
            return new WP_Error('invalid_csv', 'CSV header is required');
        }

        $map = [];
        foreach ($header as $index => $column) {
            $normalized = strtolower(trim((string) $column));
            $map[$normalized] = intval($index);
        }

        foreach (['date', 'amount', 'description'] as $required) {
            if (!array_key_exists($required, $map)) {
                fclose($handle);
                return new WP_Error('invalid_csv', 'CSV columns must include: date, amount, description');
            }
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (!is_array($row)) {
                continue;
            }

            $date = isset($map['date']) ? trim((string) ($row[$map['date']] ?? '')) : '';
            $amount = isset($map['amount']) ? floatval($row[$map['amount']] ?? 0) : 0;
            $description = isset($map['description']) ? trim((string) ($row[$map['description']] ?? '')) : '';
            $reference = isset($map['reference']) ? trim((string) ($row[$map['reference']] ?? '')) : '';
            $external_id = isset($map['external_id']) ? trim((string) ($row[$map['external_id']] ?? '')) : '';

            if ($date === '' && $amount === 0.0 && $description === '') {
                continue;
            }

            $rows[] = [
                'date' => $date,
                'amount' => $amount,
                'description' => $description,
                'reference' => $reference,
                'external_id' => $external_id,
                'import_source' => 'csv',
            ];
        }

        fclose($handle);
        return $rows;
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
            $alternatives = is_array($permission) ? $permission : [$permission];
            foreach ($alternatives as $single) {
                if (OraBooks_RBAC::require_permission($user_id, $org_id, $single)) {
                    return;
                }
            }
        }

        foreach ((array) $permissions as $permission) {
            $alternatives = is_array($permission) ? $permission : [$permission];
            if (in_array('view_bank_reconciliation', $alternatives, true) && OraBooks_RBAC::require_permission($user_id, $org_id, 'view_reports')) {
                return;
            }
            if (in_array('match_transaction', $alternatives, true) && OraBooks_RBAC::require_permission($user_id, $org_id, 'submit_transaction')) {
                return;
            }
            if (in_array('reconcile_bank', $alternatives, true) && OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_org_settings')) {
                return;
            }
        }

        orabooks_json_error('Permission denied', 403);
    }

    public function ajax_accounts_list() {
        $user_id = $this->current_user_id();
        $org_id = intval($_GET['org_id'] ?? 0);
        $this->require_bank_permission($user_id, $org_id, [['view_bank_reconciliation', 'view_reports']]);
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
        $this->require_bank_permission($user_id, $org_id, [['match_transaction', 'submit_transaction'], ['reconcile_bank', 'manage_org_settings']]);
        $rows = json_decode(stripslashes($_POST['rows_json'] ?? '[]'), true);
        $result = self::import_rows($org_id, intval($_POST['bank_account_id'] ?? 0), is_array($rows) ? $rows : [], $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success($result);
    }

    public function ajax_import_csv() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_bank_permission($user_id, $org_id, [['match_transaction', 'submit_transaction'], ['reconcile_bank', 'manage_org_settings']]);

        if (empty($_FILES['statement_file'])) {
            orabooks_json_error('CSV statement_file is required', 400);
        }

        $result = self::import_csv($org_id, intval($_POST['bank_account_id'] ?? 0), $_FILES['statement_file'], $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success($result);
    }

    public function ajax_transactions_list() {
        $user_id = $this->current_user_id();
        $org_id = intval($_GET['org_id'] ?? 0);
        $this->require_bank_permission($user_id, $org_id, [['view_bank_reconciliation', 'view_reports']]);
        orabooks_json_success(['transactions' => self::get_transactions_list($org_id, intval($_GET['bank_account_id'] ?? 0), $_GET)]);
    }

    public function ajax_manual_match() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_bank_permission($user_id, $org_id, [['match_transaction', 'submit_transaction'], ['approve_journal']]);
        $result = self::manual_match($org_id, intval($_POST['bank_transaction_id'] ?? 0), $_POST['transaction_type'] ?? '', intval($_POST['transaction_id'] ?? 0), $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success($result);
    }

    public function ajax_create_transaction() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_bank_permission($user_id, $org_id, [['match_transaction', 'submit_transaction'], ['approve_journal']]);

        $data = json_decode(stripslashes($_POST['payload_json'] ?? '{}'), true);
        if (!is_array($data)) {
            $data = [];
        }

        $result = self::create_transaction_from_bank_entry(
            $org_id,
            intval($_POST['bank_transaction_id'] ?? 0),
            sanitize_text_field($_POST['transaction_type'] ?? ''),
            $data,
            $user_id
        );

        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success($result);
    }

    public function ajax_skip_transaction() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_bank_permission($user_id, $org_id, [['match_transaction', 'submit_transaction'], ['approve_journal']]);
        $result = self::skip_transaction($org_id, intval($_POST['bank_transaction_id'] ?? 0), $_POST['reason'] ?? '', $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success([], 'Bank transaction skipped');
    }

    public function ajax_feed_connect() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_bank_permission($user_id, $org_id, [['connect_bank_feed', 'manage_org_settings']]);

        $result = self::connect_feed(
            $org_id,
            intval($_POST['bank_account_id'] ?? 0),
            sanitize_text_field($_POST['provider'] ?? ''),
            sanitize_text_field($_POST['access_token'] ?? ''),
            sanitize_text_field($_POST['refresh_token'] ?? ''),
            sanitize_text_field($_POST['token_expires_at'] ?? '')
        );

        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success($result);
    }

    public function ajax_finalize_reconciliation() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_bank_permission($user_id, $org_id, [['reconcile_bank', 'manage_org_settings'], ['approve_journal']]);
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
