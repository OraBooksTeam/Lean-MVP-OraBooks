<?php
/**
 * OraBooks Tax Governance & Compliance Engine (SL-305)
 *
 * Centralized tax configuration, calculation, snapshots, and override governance.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Tax {

    private static $instance = null;

    const DEFAULT_OVERRIDE_REASONS = [
        'WRONG_AI_CLASSIFICATION',
        'LOCAL_TAX_RULE',
        'MANUAL_JURISDICTION_ADJUSTMENT',
        'CUSTOMER_EXEMPTION',
        'REGIONAL_COMPLIANCE_OVERRIDE',
    ];

    /** SL-017 tax liability account codes by tax type. */
    const TAX_LIABILITY_ACCOUNTS = [
        'VAT'        => '2100',
        'GST'        => '2100',
        'Sales Tax'  => '2100',
    ];

    /** Journal line account codes that represent posted tax amounts. */
    const TAX_LINE_ACCOUNT_CODES = ['2100', '5300'];

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            add_action('wp_ajax_orabooks_tax_calculate', [self::$instance, 'ajax_calculate']);
            add_action('wp_ajax_nopriv_orabooks_tax_calculate', [self::$instance, 'ajax_calculate']);
            add_action('wp_ajax_orabooks_tax_save_config', [self::$instance, 'ajax_save_config']);
            add_action('wp_ajax_nopriv_orabooks_tax_save_config', [self::$instance, 'ajax_save_config']);
            add_action('wp_ajax_orabooks_tax_configs_list', [self::$instance, 'ajax_list_configs']);
            add_action('wp_ajax_nopriv_orabooks_tax_configs_list', [self::$instance, 'ajax_list_configs']);
            add_action('wp_ajax_orabooks_tax_jurisdictions_list', [self::$instance, 'ajax_list_jurisdictions']);
            add_action('wp_ajax_nopriv_orabooks_tax_jurisdictions_list', [self::$instance, 'ajax_list_jurisdictions']);
            add_action('wp_ajax_orabooks_tax_lock_status', [self::$instance, 'ajax_lock_status']);
            add_action('wp_ajax_nopriv_orabooks_tax_lock_status', [self::$instance, 'ajax_lock_status']);
            add_action('wp_ajax_orabooks_tax_snapshot', [self::$instance, 'ajax_create_snapshot']);
            add_action('wp_ajax_nopriv_orabooks_tax_snapshot', [self::$instance, 'ajax_create_snapshot']);
            add_action('wp_ajax_orabooks_tax_get_snapshot', [self::$instance, 'ajax_get_snapshot']);
            add_action('wp_ajax_nopriv_orabooks_tax_get_snapshot', [self::$instance, 'ajax_get_snapshot']);
            add_action('wp_ajax_orabooks_tax_snapshots_list', [self::$instance, 'ajax_list_snapshots']);
            add_action('wp_ajax_nopriv_orabooks_tax_snapshots_list', [self::$instance, 'ajax_list_snapshots']);
        }
        return self::$instance;
    }

    private static function maybe_ensure_tax_schema() {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;

        global $wpdb;
        $tables = [
            OraBooks_Database::table('tax_configs') => [
                'exemption_certificate_url' => 'TEXT NULL',
                'override_reasons' => 'JSON NULL',
            ],
        ];

        foreach ($tables as $table => $columns) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
                continue;
            }
            $existing = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
            foreach ($columns as $column => $definition) {
                if (!in_array($column, $existing, true)) {
                    $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
                }
            }
        }

        $snapshots_table = OraBooks_Database::table('tax_snapshots');
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $snapshots_table)) === $snapshots_table) {
            $type_col = $wpdb->get_row("SHOW COLUMNS FROM {$snapshots_table} LIKE 'transaction_type'");
            if ($type_col && isset($type_col->Type) && stripos($type_col->Type, 'bill') === false) {
                $wpdb->query(
                    "ALTER TABLE {$snapshots_table} MODIFY transaction_type ENUM('invoice','expense','journal','bill') NOT NULL"
                );
            }
        }
    }

    public static function get_create_table_sql() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $tables = [];

        $table_configs = OraBooks_Database::table('tax_configs');
        $table_jurisdictions = OraBooks_Database::table('tax_jurisdictions');
        $table_snapshots = OraBooks_Database::table('tax_snapshots');
        $table_orgs = OraBooks_Database::table('organizations');

        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_configs} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            jurisdiction VARCHAR(32) NOT NULL,
            default_tax_rate DECIMAL(8,4) NOT NULL DEFAULT 0,
            tax_type ENUM('VAT','GST','Sales Tax','None') NOT NULL DEFAULT 'Sales Tax',
            exemption_certificate_url TEXT NULL,
            override_reasons JSON NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_org_jurisdiction (org_id, jurisdiction),
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
            INDEX idx_org_active (org_id, is_active)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_jurisdictions} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            jurisdiction_code VARCHAR(32) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            parent_id BIGINT UNSIGNED NULL,
            tax_rules JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_id) REFERENCES {$table_jurisdictions}(id) ON DELETE SET NULL,
            INDEX idx_parent (parent_id)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_snapshots} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            transaction_id BIGINT UNSIGNED NOT NULL,
            transaction_type ENUM('invoice','expense','journal','bill') NOT NULL,
            taxable_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
            tax_rate DECIMAL(8,4) NOT NULL DEFAULT 0,
            tax_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
            jurisdiction VARCHAR(32) NOT NULL,
            tax_type VARCHAR(32) NOT NULL,
            rule_id VARCHAR(64) NULL,
            override_reason VARCHAR(64) NULL,
            override_note TEXT NULL,
            calculated_by BIGINT UNSIGNED NULL,
            metadata JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_transaction (org_id, transaction_type, transaction_id),
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
            INDEX idx_org_created (org_id, created_at),
            INDEX idx_jurisdiction (jurisdiction)
        ) {$charset_collate};";

        return $tables;
    }

    public static function seed_default_jurisdictions() {
        global $wpdb;

        $table = OraBooks_Database::table('tax_jurisdictions');
        $defaults = [
            [
                'jurisdiction_code' => 'BD',
                'name' => 'Bangladesh VAT',
                'tax_rules' => [
                    'default_rate' => 15.0,
                    'tax_type' => 'VAT',
                    'product_rates' => ['standard' => 15.0, 'exempt' => 0.0, 'reduced' => 5.0],
                ],
            ],
            [
                'jurisdiction_code' => 'IN',
                'name' => 'India GST',
                'tax_rules' => [
                    'default_rate' => 18.0,
                    'tax_type' => 'GST',
                    'product_rates' => ['standard' => 18.0, 'exempt' => 0.0, 'reduced' => 5.0],
                ],
            ],
            [
                'jurisdiction_code' => 'US',
                'name' => 'United States Sales Tax',
                'tax_rules' => [
                    'default_rate' => 0.0,
                    'tax_type' => 'Sales Tax',
                    'product_rates' => ['standard' => 0.0, 'exempt' => 0.0, 'reduced' => 0.0],
                ],
            ],
        ];

        foreach ($defaults as $row) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE jurisdiction_code = %s",
                $row['jurisdiction_code']
            ));

            if ($exists) {
                continue;
            }

            $wpdb->insert(
                $table,
                [
                    'jurisdiction_code' => $row['jurisdiction_code'],
                    'name' => $row['name'],
                    'parent_id' => null,
                    'tax_rules' => wp_json_encode($row['tax_rules']),
                ],
                ['%s', '%s', null, '%s']
            );
        }
    }

    /**
     * Resolve jurisdiction from explicit code or billing/shipping address (SL-305 §5.2).
     */
    public static function resolve_jurisdiction($data) {
        if (!empty($data['jurisdiction'])) {
            return strtoupper(sanitize_text_field($data['jurisdiction']));
        }

        $address = $data['billing_address'] ?? $data['shipping_address'] ?? null;
        if (is_string($address) && $address !== '') {
            $decoded = json_decode($address, true);
            if (is_array($decoded)) {
                $address = $decoded;
            }
        }

        if (is_array($address)) {
            $country = strtoupper(sanitize_text_field($address['country'] ?? $address['country_code'] ?? ''));
            $state = strtoupper(sanitize_text_field($address['state'] ?? $address['region'] ?? $address['province'] ?? ''));
            if ($country === 'US' && $state !== '') {
                return 'US-' . $state;
            }
            if ($country !== '') {
                return $country;
            }
        }

        return 'US';
    }

    /**
     * Apply product-type rate overrides from jurisdiction JSON rules.
     */
    private static function apply_product_type_rate($rules, $product_type, $base_rate) {
        $product_type = sanitize_text_field($product_type ?: 'standard');
        if ($product_type === 'exempt') {
            return [0.0, 'product_exempt'];
        }

        $product_rates = [];
        if (is_array($rules) && !empty($rules['product_rates']) && is_array($rules['product_rates'])) {
            $product_rates = $rules['product_rates'];
        }

        if (isset($product_rates[$product_type])) {
            return [floatval($product_rates[$product_type]), 'product_' . $product_type];
        }

        return [floatval($base_rate), null];
    }

    /**
     * Validate tax liability account exists before posting tax (SL-017).
     */
    public static function validate_tax_posting_accounts($org_id, $tax_type) {
        $tax_type = is_string($tax_type) ? sanitize_text_field($tax_type) : $tax_type;
        if ($tax_type === 'None' || (is_numeric($tax_type) && floatval($tax_type) === 0.0)) {
            return true;
        }

        if (!class_exists('OraBooks_COA')) {
            return true;
        }

        $code = self::TAX_LIABILITY_ACCOUNTS[$tax_type] ?? '2100';
        $account = OraBooks_COA::get_account_by_code((int) $org_id, $code);
        if (!$account) {
            return new WP_Error(
                'tax_account_missing',
                sprintf('Tax liability account %s is required before posting tax (SL-017).', $code)
            );
        }

        return true;
    }

    /**
     * Seed default org tax profile from country/jurisdiction defaults (SL-305 §5.1).
     */
    public static function seed_default_org_configs($org_id, $country_code = null) {
        global $wpdb;

        self::maybe_ensure_tax_schema();
        $org_id = (int) $org_id;
        if ($org_id <= 0) {
            return false;
        }

        $table = OraBooks_Database::table('tax_configs');
        $existing = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE org_id = %d",
            $org_id
        ));
        if ($existing > 0) {
            return false;
        }

        $country = strtoupper(sanitize_text_field($country_code ?: 'US'));
        $rule = self::get_jurisdiction_rule($country);
        $rate = floatval($rule['default_rate'] ?? 0);
        $tax_type = sanitize_text_field($rule['tax_type'] ?? 'Sales Tax');

        self::save_config($org_id, [
            'jurisdiction' => $country,
            'default_tax_rate' => $rate,
            'tax_type' => $tax_type,
            'is_active' => 1,
        ]);

        return true;
    }

    public static function calculate($data) {
        self::maybe_ensure_tax_schema();
        $org_id = intval($data['org_id'] ?? 0);
        $amount = round(floatval($data['amount'] ?? 0), 2);
        $jurisdiction = self::resolve_jurisdiction($data);
        $customer_tax_status = sanitize_text_field($data['customer_tax_status'] ?? 'taxable');
        $product_type = sanitize_text_field($data['product_type'] ?? 'standard');

        if ($org_id <= 0) {
            return new WP_Error('invalid_org', 'Organization is required for tax calculation');
        }

        if ($amount < 0) {
            return new WP_Error('invalid_amount', 'Amount cannot be negative');
        }

        $jurisdiction_rules = self::get_jurisdiction_rule($jurisdiction);

        if ($customer_tax_status === 'exempt') {
            $rate = 0.0;
            $tax_type = 'None';
            $rule_id = 'customer_exempt';
        } else {
            $config = self::get_active_config($org_id, $jurisdiction);
            if ($config) {
                $rate = isset($config->default_tax_rate) ? floatval($config->default_tax_rate) : 0.0;
                $tax_type = isset($config->tax_type) ? sanitize_text_field((string) $config->tax_type) : 'Sales Tax';
                $rule_id = 'org_config_' . intval($config->id);
            } else {
                $rate = floatval($jurisdiction_rules['default_rate'] ?? 0);
                $tax_type = sanitize_text_field($jurisdiction_rules['tax_type'] ?? 'Sales Tax');
                $rule_id = 'jurisdiction_' . $jurisdiction;
            }

            list($product_rate, $product_rule_id) = self::apply_product_type_rate($jurisdiction_rules, $product_type, $rate);
            $rate = $product_rate;
            if ($product_rule_id) {
                $rule_id = $product_rule_id;
            }
        }

        $tax_amount = round($amount * ($rate / 100), 2);

        if (!empty($data['validate_posting_accounts']) && $tax_amount > 0) {
            $account_check = self::validate_tax_posting_accounts($org_id, $tax_type);
            if (is_wp_error($account_check)) {
                return $account_check;
            }
        }

        $result = [
            'org_id' => $org_id,
            'taxable_amount' => $amount,
            'tax_rate' => $rate,
            'tax_amount' => $tax_amount,
            'jurisdiction_applied' => $jurisdiction,
            'tax_type' => $tax_type,
            'rule_id' => $rule_id,
        ];

        orabooks_log_event('tax_calculated', 'Tax calculated', 'info', [
            'amount' => $amount,
            'tax_rate' => $rate,
            'tax_amount' => $tax_amount,
            'jurisdiction' => $jurisdiction,
            'rule_id' => $rule_id,
            'product_type' => $product_type,
        ], get_current_user_id(), $org_id);

        return $result;
    }

    public static function save_config($org_id, $data, $user_id = null) {
        global $wpdb;
        self::maybe_ensure_tax_schema();

        $org_id = intval($org_id);
        $user_id = $user_id ? intval($user_id) : get_current_user_id();
        $jurisdiction = strtoupper(sanitize_text_field($data['jurisdiction'] ?? ''));
        $rate = round(floatval($data['default_tax_rate'] ?? 0), 4);
        $tax_type = sanitize_text_field($data['tax_type'] ?? 'Sales Tax');

        if ($org_id <= 0 || $jurisdiction === '') {
            return new WP_Error('invalid_config', 'Organization and jurisdiction are required');
        }

        if (self::is_tax_config_locked($org_id, ['transaction_date' => $data['transaction_date'] ?? current_time('Y-m-d')])) {
            return new WP_Error('tax_locked', 'Tax configuration is locked for closed fiscal periods');
        }

        if ($rate < 0 || $rate > 100) {
            return new WP_Error('invalid_rate', 'Tax rate must be between 0 and 100');
        }

        if (!in_array($tax_type, ['VAT', 'GST', 'Sales Tax', 'None'], true)) {
            return new WP_Error('invalid_tax_type', 'Invalid tax type');
        }

        $table = OraBooks_Database::table('tax_configs');
        $override_reasons = self::normalize_override_reasons($data['override_reasons'] ?? null);

        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE org_id = %d AND jurisdiction = %s",
            $org_id,
            $jurisdiction
        ));

        $payload = [
            'org_id' => $org_id,
            'jurisdiction' => $jurisdiction,
            'default_tax_rate' => $rate,
            'tax_type' => $tax_type,
            'exemption_certificate_url' => !empty($data['exemption_certificate_url']) ? esc_url_raw($data['exemption_certificate_url']) : null,
            'override_reasons' => wp_json_encode(array_values($override_reasons)),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];

        $formats = ['%d', '%s', '%f', '%s', '%s', '%s', '%d'];

        if ($existing_id) {
            $wpdb->update(
                $table,
                $payload,
                ['id' => $existing_id],
                $formats,
                ['%d']
            );
            $config_id = intval($existing_id);
        } else {
            $wpdb->insert(
                $table,
                $payload,
                $formats
            );
            $config_id = intval($wpdb->insert_id);
        }

        orabooks_log_event('tax_config_updated', 'Tax configuration updated', 'info', [
            'jurisdiction' => $jurisdiction,
            'default_tax_rate' => $rate,
            'tax_type' => $tax_type,
        ], $user_id, $org_id);

        return self::get_config($config_id);
    }

    public static function create_snapshot($data, $user_id = null) {
        global $wpdb;

        $org_id = intval($data['org_id'] ?? 0);
        $transaction_id = intval($data['transaction_id'] ?? 0);
        $transaction_type = sanitize_text_field($data['transaction_type'] ?? '');
        $user_id = $user_id ? intval($user_id) : get_current_user_id();

        if ($org_id <= 0 || $transaction_id <= 0) {
            return new WP_Error('invalid_transaction', 'Organization and transaction are required');
        }

        if (!in_array($transaction_type, ['invoice', 'expense', 'journal', 'bill'], true)) {
            return new WP_Error('invalid_transaction_type', 'Invalid transaction type');
        }

        if (self::is_tax_locked($org_id, $data)) {
            return new WP_Error('tax_locked', 'Tax is locked for this transaction period');
        }

        if (!empty($data['override'])) {
            $calculation = self::build_override_calculation($data);
        } elseif (!empty($data['posted_tax'])) {
            $calculation = self::build_posted_tax_calculation($data);
        } else {
            $calculation = self::calculate($data);
        }

        if (is_wp_error($calculation)) {
            return $calculation;
        }

        $table = OraBooks_Database::table('tax_snapshots');
        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE org_id = %d AND transaction_type = %s AND transaction_id = %d",
            $org_id,
            $transaction_type,
            $transaction_id
        ));

        if ($existing_id) {
            return [
                'snapshot_id' => (int) $existing_id,
                'calculation' => $calculation,
                'existing' => true,
            ];
        }

        $wpdb->insert(
            $table,
            [
                'org_id' => $org_id,
                'transaction_id' => $transaction_id,
                'transaction_type' => $transaction_type,
                'taxable_amount' => $calculation['taxable_amount'],
                'tax_rate' => $calculation['tax_rate'],
                'tax_amount' => $calculation['tax_amount'],
                'jurisdiction' => $calculation['jurisdiction_applied'],
                'tax_type' => $calculation['tax_type'],
                'rule_id' => $calculation['rule_id'],
                'override_reason' => $calculation['override_reason'] ?? null,
                'override_note' => isset($data['override_note']) ? sanitize_textarea_field($data['override_note']) : null,
                'calculated_by' => $user_id,
                'metadata' => !empty($data['metadata']) ? wp_json_encode($data['metadata']) : null,
            ],
            ['%d', '%d', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
        );

        $snapshot_id = intval($wpdb->insert_id);

        orabooks_log_event(
            !empty($data['override']) ? 'tax_override' : (!empty($data['posted_tax']) ? 'tax_snapshot_created' : 'tax_snapshot_created'),
            'Tax snapshot created',
            'info', [
            'transaction_id' => $transaction_id,
            'transaction_type' => $transaction_type,
            'tax_amount' => $calculation['tax_amount'],
            'override_reason' => $calculation['override_reason'] ?? null,
        ], $user_id, $org_id);

        return [
            'snapshot_id' => $snapshot_id,
            'calculation' => $calculation,
            'existing' => false,
        ];
    }

    /**
     * Create or return an immutable tax snapshot for a posted invoice.
     */
    public static function snapshot_for_invoice($invoice, $user_id = null) {
        if (!$invoice) {
            return new WP_Error('invalid_invoice', 'Invoice is required for tax snapshot');
        }

        $tax_base = max(0, round(floatval($invoice->total_amount ?? 0) - floatval($invoice->tax_amount ?? 0), 2));
        $payload = [
            'org_id' => (int) $invoice->org_id,
            'transaction_id' => (int) $invoice->id,
            'transaction_type' => 'invoice',
            'amount' => $tax_base,
            'jurisdiction' => sanitize_text_field($invoice->tax_jurisdiction ?? 'US'),
            'transaction_date' => $invoice->invoice_date ?? current_time('Y-m-d'),
            'tax_type' => sanitize_text_field($invoice->tax_type ?? 'Sales Tax'),
        ];

        if (!empty($invoice->tax_override_reason)) {
            $payload['override'] = true;
            $payload['override_tax_rate'] = floatval($invoice->tax_rate ?? 0);
            $payload['override_reason'] = $invoice->tax_override_reason;
        } else {
            $payload['override'] = false;
        }

        return self::create_snapshot($payload, $user_id);
    }

    /**
     * Create or return an immutable tax snapshot for a posted expense.
     */
    public static function create_snapshot_from_expense($expense, $user_id = null) {
        if (!$expense) {
            return new WP_Error('invalid_expense', 'Expense is required for tax snapshot');
        }

        $subtotal = isset($expense->subtotal) ? (float) $expense->subtotal : 0.0;
        $total = (float) ($expense->total_amount ?? 0);
        $tax = (float) ($expense->tax_amount ?? 0);
        $tax_base = $subtotal > 0 ? $subtotal : max(0, round($total - $tax, 2));

        $payload = [
            'org_id' => (int) $expense->org_id,
            'transaction_id' => (int) $expense->id,
            'transaction_type' => 'expense',
            'amount' => $tax_base,
            'jurisdiction' => sanitize_text_field($expense->tax_jurisdiction ?? 'US'),
            'transaction_date' => $expense->transaction_date ?? current_time('Y-m-d'),
            'tax_type' => sanitize_text_field($expense->tax_type ?? 'Sales Tax'),
        ];

        if (!empty($expense->tax_override_reason)) {
            $payload['override'] = true;
            $payload['override_tax_rate'] = (float) ($expense->tax_rate ?? 0);
            $payload['override_reason'] = $expense->tax_override_reason;
        } else {
            $payload['override'] = false;
        }

        return self::create_snapshot($payload, $user_id);
    }

    /**
     * Create or return an immutable tax snapshot for a posted vendor bill.
     */
    public static function snapshot_for_vendor_bill($bill, $user_id = null) {
        if (!$bill || floatval($bill->tax_amount ?? 0) <= 0) {
            return null;
        }

        $subtotal = round(floatval($bill->subtotal_amount ?? 0), 2);
        $tax_amount = round(floatval($bill->tax_amount ?? 0), 2);
        $tax_rate = $subtotal > 0 ? round(($tax_amount / $subtotal) * 100, 4) : 0.0;

        return self::create_snapshot([
            'org_id' => (int) $bill->org_id,
            'transaction_id' => (int) $bill->id,
            'transaction_type' => 'bill',
            'amount' => $subtotal,
            'tax_rate' => $tax_rate,
            'tax_amount' => $tax_amount,
            'jurisdiction' => sanitize_text_field($bill->tax_jurisdiction ?? 'US'),
            'tax_type' => sanitize_text_field($bill->tax_type ?? 'Sales Tax'),
            'transaction_date' => $bill->transaction_date ?? current_time('Y-m-d'),
            'posted_tax' => true,
            'rule_id' => 'vendor_bill_posted',
            'metadata' => ['bill_number' => $bill->bill_number ?? null],
        ], $user_id);
    }

    /**
     * Create or return an immutable tax snapshot for a posted journal (SL-305 §5.3).
     */
    public static function snapshot_for_journal($journal, $lines, $user_id = null) {
        if (!$journal || !is_array($lines)) {
            return null;
        }

        $tax_amount = 0.0;
        foreach ($lines as $line) {
            $code = isset($line->account_code) ? (string) $line->account_code : '';
            if (!in_array($code, self::TAX_LINE_ACCOUNT_CODES, true)) {
                continue;
            }
            if ($code === '2100') {
                $tax_amount += max(0, floatval($line->credit_amount ?? 0) - floatval($line->debit_amount ?? 0));
            } else {
                $tax_amount += max(0, floatval($line->debit_amount ?? 0) - floatval($line->credit_amount ?? 0));
            }
        }

        $tax_amount = round($tax_amount, 2);
        if ($tax_amount <= 0) {
            return null;
        }

        $metadata = [];
        if (!empty($journal->metadata)) {
            $decoded = json_decode($journal->metadata, true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        $total = round(floatval($journal->total_amount ?? 0), 2);
        $taxable_amount = max(0, round($total - $tax_amount, 2));
        if ($taxable_amount <= 0 && $total > 0) {
            $taxable_amount = $total;
        }

        $jurisdiction = sanitize_text_field($metadata['tax_jurisdiction'] ?? self::resolve_jurisdiction($metadata));
        $tax_type = sanitize_text_field($metadata['tax_type'] ?? 'Sales Tax');
        $tax_rate = $taxable_amount > 0 ? round(($tax_amount / $taxable_amount) * 100, 4) : 0.0;

        return self::create_snapshot([
            'org_id' => (int) $journal->org_id,
            'transaction_id' => (int) $journal->id,
            'transaction_type' => 'journal',
            'amount' => $taxable_amount,
            'tax_rate' => $tax_rate,
            'tax_amount' => $tax_amount,
            'jurisdiction' => $jurisdiction,
            'tax_type' => $tax_type,
            'transaction_date' => $journal->transaction_date ?? current_time('Y-m-d'),
            'posted_tax' => true,
            'rule_id' => 'journal_posted',
            'metadata' => array_merge($metadata, ['journal_number' => $journal->journal_number ?? null]),
        ], $user_id);
    }

    public static function list_snapshots($org_id, $limit = 25) {
        global $wpdb;
        self::maybe_ensure_tax_schema();

        $table = OraBooks_Database::table('tax_snapshots');
        $limit = max(1, min(100, (int) $limit));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE org_id = %d
             ORDER BY created_at DESC
             LIMIT %d",
            (int) $org_id,
            $limit
        ));

        return array_map([self::class, 'format_snapshot'], $rows ?: []);
    }

    private static function normalize_override_reasons($raw) {
        $allowed = self::DEFAULT_OVERRIDE_REASONS;
        $reasons = [];

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = array_map('trim', explode(',', $raw));
            }
        }

        if (is_array($raw)) {
            foreach ($raw as $reason) {
                $reason = sanitize_text_field((string) $reason);
                if ($reason !== '' && in_array($reason, $allowed, true)) {
                    $reasons[] = $reason;
                }
            }
        }

        return $reasons ?: $allowed;
    }

    public static function list_configs($org_id) {
        global $wpdb;
        self::maybe_ensure_tax_schema();

        $table = OraBooks_Database::table('tax_configs');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d ORDER BY jurisdiction ASC",
            intval($org_id)
        ));

        return array_map([self::class, 'format_config'], $rows ?: []);
    }

    public static function list_jurisdictions() {
        global $wpdb;

        $table = OraBooks_Database::table('tax_jurisdictions');
        $rows = $wpdb->get_results(
            "SELECT jurisdiction_code, name, tax_rules FROM {$table} ORDER BY name ASC"
        );

        return array_map(function ($row) {
            $rules = !empty($row->tax_rules) ? json_decode($row->tax_rules, true) : [];
            return [
                'jurisdiction_code' => $row->jurisdiction_code,
                'name' => $row->name,
                'default_tax_rate' => floatval($rules['default_rate'] ?? 0),
                'tax_type' => $rules['tax_type'] ?? 'Sales Tax',
            ];
        }, $rows ?: []);
    }

    public static function get_snapshot($org_id, $transaction_type, $transaction_id) {
        global $wpdb;

        $table = OraBooks_Database::table('tax_snapshots');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE org_id = %d AND transaction_type = %s AND transaction_id = %d",
            intval($org_id),
            sanitize_text_field($transaction_type),
            intval($transaction_id)
        ));

        return $row ? self::format_snapshot($row) : null;
    }

    public static function get_lock_status($org_id, $transaction_date = null) {
        $date = $transaction_date ?: current_time('Y-m-d');
        $config_locked = self::is_tax_config_locked((int) $org_id, ['transaction_date' => $date]);
        $transaction_locked = self::is_tax_locked((int) $org_id, ['transaction_date' => $date]);

        return [
            'org_id' => (int) $org_id,
            'transaction_date' => $date,
            'tax_locked' => $transaction_locked,
            'config_tax_locked' => $config_locked,
            'message' => $transaction_locked
                ? 'Tax settings are locked for closed fiscal periods.'
                : 'Tax changes are allowed for this period.',
            'config_message' => $config_locked
                ? 'Tax configuration cannot be changed while a fiscal period is hard closed.'
                : 'Tax configuration can be updated.',
        ];
    }

    /**
     * Whether org tax configuration changes are blocked (hard-closed period only).
     */
    public static function is_tax_config_locked($org_id, $data = []) {
        $date = $data['transaction_date'] ?? current_time('Y-m-d');

        if (class_exists('OraBooks_Fiscal') && method_exists('OraBooks_Fiscal', 'is_period_hard_closed')) {
            return OraBooks_Fiscal::is_period_hard_closed($org_id, $date);
        }

        global $wpdb;
        $table = OraBooks_Database::table('fiscal_periods');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT status FROM {$table} WHERE org_id = %d AND %s BETWEEN period_start AND period_end LIMIT 1",
            (int) $org_id,
            (string) $date
        ));

        return ($row->status ?? null) === 'hard_closed';
    }

    public static function format_config($config) {
        $override_reasons = self::DEFAULT_OVERRIDE_REASONS;
        if (!empty($config->override_reasons)) {
            $decoded = json_decode($config->override_reasons, true);
            if (is_array($decoded) && $decoded) {
                $override_reasons = $decoded;
            }
        }

        return [
            'id' => (int) $config->id,
            'org_id' => (int) $config->org_id,
            'jurisdiction' => $config->jurisdiction,
            'default_tax_rate' => (float) $config->default_tax_rate,
            'tax_type' => $config->tax_type,
            'exemption_certificate_url' => $config->exemption_certificate_url,
            'override_reasons' => $override_reasons,
            'is_active' => (int) $config->is_active,
            'updated_at' => $config->updated_at ?? null,
        ];
    }

    public static function format_snapshot($snapshot) {
        return [
            'id' => (int) $snapshot->id,
            'org_id' => (int) $snapshot->org_id,
            'transaction_id' => (int) $snapshot->transaction_id,
            'transaction_type' => $snapshot->transaction_type,
            'taxable_amount' => (float) $snapshot->taxable_amount,
            'tax_rate' => (float) $snapshot->tax_rate,
            'tax_amount' => (float) $snapshot->tax_amount,
            'jurisdiction' => $snapshot->jurisdiction,
            'tax_type' => $snapshot->tax_type,
            'rule_id' => $snapshot->rule_id,
            'override_reason' => $snapshot->override_reason,
            'override_note' => $snapshot->override_note,
            'created_at' => $snapshot->created_at,
        ];
    }

    public static function get_active_config($org_id, $jurisdiction) {
        global $wpdb;

        $table = OraBooks_Database::table('tax_configs');
        $jurisdiction = strtoupper(sanitize_text_field($jurisdiction));
        $candidates = [$jurisdiction];
        if (strpos($jurisdiction, '-') !== false) {
            $candidates[] = explode('-', $jurisdiction)[0];
        }

        foreach ($candidates as $code) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE org_id = %d AND jurisdiction = %s AND is_active = 1 LIMIT 1",
                intval($org_id),
                $code
            ));
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    public static function get_config($config_id) {
        global $wpdb;

        $table = OraBooks_Database::table('tax_configs');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            intval($config_id)
        ));
    }

    private static function get_jurisdiction_rule($jurisdiction) {
        global $wpdb;

        $table = OraBooks_Database::table('tax_jurisdictions');
        $jurisdiction = strtoupper(sanitize_text_field($jurisdiction));
        $candidates = [$jurisdiction];

        if (strpos($jurisdiction, '-') !== false) {
            $candidates[] = explode('-', $jurisdiction)[0];
        }

        foreach ($candidates as $code) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT tax_rules FROM {$table} WHERE jurisdiction_code = %s",
                $code
            ));

            if ($row && !empty($row->tax_rules)) {
                $rules = json_decode($row->tax_rules, true);
                if (is_array($rules)) {
                    return $rules;
                }
            }
        }

        return ['default_rate' => 0.0, 'tax_type' => 'Sales Tax'];
    }

    private static function build_posted_tax_calculation($data) {
        $org_id = intval($data['org_id'] ?? 0);
        $amount = round(floatval($data['amount'] ?? 0), 2);
        $jurisdiction = self::resolve_jurisdiction($data);
        $tax_rate = round(floatval($data['tax_rate'] ?? 0), 4);
        $tax_amount = round(floatval($data['tax_amount'] ?? ($amount * ($tax_rate / 100))), 2);

        return [
            'org_id' => $org_id,
            'taxable_amount' => $amount,
            'tax_rate' => $tax_rate,
            'tax_amount' => $tax_amount,
            'jurisdiction_applied' => $jurisdiction,
            'tax_type' => sanitize_text_field($data['tax_type'] ?? 'Sales Tax'),
            'rule_id' => sanitize_text_field($data['rule_id'] ?? 'journal_posted'),
        ];
    }

    private static function build_override_calculation($data) {
        $org_id = intval($data['org_id'] ?? 0);
        $amount = round(floatval($data['amount'] ?? 0), 2);
        $jurisdiction = strtoupper(sanitize_text_field($data['jurisdiction'] ?? 'US'));
        $reason = sanitize_text_field($data['override_reason'] ?? '');

        if ($reason === '') {
            return new WP_Error('override_reason_required', 'Tax override reason is required');
        }

        if (!in_array($reason, self::get_allowed_override_reasons($org_id, $jurisdiction), true)) {
            return new WP_Error('invalid_override_reason', 'Tax override reason is not allowed');
        }

        $rate = round(floatval($data['override_tax_rate'] ?? 0), 4);
        if ($rate < 0 || $rate > 100) {
            return new WP_Error('invalid_rate', 'Override tax rate must be between 0 and 100');
        }

        return [
            'org_id' => $org_id,
            'taxable_amount' => $amount,
            'tax_rate' => $rate,
            'tax_amount' => round($amount * ($rate / 100), 2),
            'jurisdiction_applied' => $jurisdiction,
            'tax_type' => sanitize_text_field($data['tax_type'] ?? 'Sales Tax'),
            'rule_id' => 'manual_override',
            'override_reason' => $reason,
        ];
    }

    private static function get_allowed_override_reasons($org_id, $jurisdiction) {
        $config = self::get_active_config($org_id, $jurisdiction);
        if ($config && !empty($config->override_reasons)) {
            $reasons = json_decode($config->override_reasons, true);
            if (is_array($reasons) && $reasons) {
                return array_map('strval', $reasons);
            }
        }

        return self::DEFAULT_OVERRIDE_REASONS;
    }

    public static function validate_override($org_id, $jurisdiction, $tax_rate, $reason_code) {
        $org_id = intval($org_id);
        $jurisdiction = strtoupper(sanitize_text_field($jurisdiction ?: 'US'));
        $tax_rate = round(floatval($tax_rate), 4);
        $reason_code = sanitize_text_field($reason_code);

        if ($org_id <= 0) {
            return new WP_Error('invalid_org', 'Organization is required');
        }

        if ($reason_code === '') {
            return new WP_Error('override_reason_required', 'Tax override reason is required');
        }

        if (!in_array($reason_code, self::get_allowed_override_reasons($org_id, $jurisdiction), true)) {
            return new WP_Error('invalid_override_reason', 'Tax override reason is not allowed');
        }

        if ($tax_rate < 0 || $tax_rate > 100) {
            return new WP_Error('invalid_rate', 'Tax override rate must be between 0 and 100');
        }

        return true;
    }

    public static function is_tax_locked($org_id, $data = []) {
        $date = $data['transaction_date'] ?? current_time('Y-m-d');

        if (class_exists('OraBooks_Fiscal')) {
            if (method_exists('OraBooks_Fiscal', 'is_period_hard_closed')
                && OraBooks_Fiscal::is_period_hard_closed($org_id, $date)) {
                return true;
            }

            if (method_exists('OraBooks_Fiscal', 'can_post')) {
                $can_post = OraBooks_Fiscal::can_post($org_id, $date);
                if (is_wp_error($can_post)) {
                    return true;
                }
            }
        }

        // Fallback guard for environments where fiscal helper class is not wired.
        global $wpdb;
        $table = OraBooks_Database::table('fiscal_periods');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT status FROM {$table} WHERE org_id = %d AND %s BETWEEN period_start AND period_end LIMIT 1",
            (int) $org_id,
            (string) $date
        ));
        $status = $row->status ?? null;
        if (in_array($status, ['soft_closed', 'hard_closed'], true)) {
            return true;
        }

        return false;
    }

    private function require_tax_access($user_id, $org_id, $permission = 'manage_org_settings') {
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, $permission)) {
            orabooks_json_error('Permission denied', 403);
        }
    }

    public function ajax_calculate() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? $_GET['org_id'] ?? 0);
        $this->require_tax_access($user_id, $org_id, 'create_invoice');

        $result = self::calculate($_POST);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success($result);
    }

    public function ajax_save_config() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_tax_access($user_id, $org_id, 'manage_org_settings');

        $result = self::save_config($org_id, $_POST, $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success(['config' => self::format_config($result)]);
    }

    public function ajax_list_configs() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_GET['org_id'] ?? $_POST['org_id'] ?? 0);
        $this->require_tax_access($user_id, $org_id, 'manage_org_settings');

        orabooks_json_success([
            'configs' => self::list_configs($org_id),
            'override_reasons' => self::DEFAULT_OVERRIDE_REASONS,
            'lock_status' => [
                'tax_locked' => self::is_tax_config_locked($org_id),
                'message' => self::is_tax_config_locked($org_id)
                    ? 'Tax configuration cannot be changed while a fiscal period is hard closed.'
                    : 'Tax configuration can be updated.',
            ],
        ]);
    }

    public function ajax_list_jurisdictions() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_GET['org_id'] ?? $_POST['org_id'] ?? 0);
        if ($org_id > 0) {
            $this->require_tax_access($user_id, $org_id, 'view_reports');
        } elseif (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        orabooks_json_success(['jurisdictions' => self::list_jurisdictions()]);
    }

    public function ajax_lock_status() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_GET['org_id'] ?? $_POST['org_id'] ?? 0);
        $date = sanitize_text_field($_GET['transaction_date'] ?? $_POST['transaction_date'] ?? '');
        $this->require_tax_access($user_id, $org_id, 'manage_org_settings');

        orabooks_json_success(self::get_lock_status($org_id, $date ?: null));
    }

    public function ajax_create_snapshot() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_tax_access($user_id, $org_id, 'submit_transaction');

        $result = self::create_snapshot($_POST, $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 409);
        }

        orabooks_json_success($result);
    }

    public function ajax_get_snapshot() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_GET['org_id'] ?? $_POST['org_id'] ?? 0);
        $transaction_type = sanitize_text_field($_GET['transaction_type'] ?? $_POST['transaction_type'] ?? '');
        $transaction_id = intval($_GET['transaction_id'] ?? $_POST['transaction_id'] ?? 0);
        $this->require_tax_access($user_id, $org_id, 'view_reports');

        $snapshot = self::get_snapshot($org_id, $transaction_type, $transaction_id);
        if (!$snapshot) {
            orabooks_json_error('Tax snapshot not found', 404);
        }

        orabooks_json_success(['snapshot' => $snapshot]);
    }

    public function ajax_list_snapshots() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_GET['org_id'] ?? $_POST['org_id'] ?? 0);
        $limit = intval($_GET['limit'] ?? $_POST['limit'] ?? 25);
        $this->require_tax_access($user_id, $org_id, 'view_reports');

        orabooks_json_success([
            'snapshots' => self::list_snapshots($org_id, $limit),
        ]);
    }
}



