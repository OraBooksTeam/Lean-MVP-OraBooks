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
        }
        return self::$instance;
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
            transaction_type ENUM('invoice','expense','journal') NOT NULL,
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
                'tax_rules' => ['default_rate' => 15.0, 'tax_type' => 'VAT'],
            ],
            [
                'jurisdiction_code' => 'IN',
                'name' => 'India GST',
                'tax_rules' => ['default_rate' => 18.0, 'tax_type' => 'GST'],
            ],
            [
                'jurisdiction_code' => 'US',
                'name' => 'United States Sales Tax',
                'tax_rules' => ['default_rate' => 0.0, 'tax_type' => 'Sales Tax'],
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

    public static function calculate($data) {
        $org_id = intval($data['org_id'] ?? 0);
        $amount = round(floatval($data['amount'] ?? 0), 2);
        $jurisdiction = strtoupper(sanitize_text_field($data['jurisdiction'] ?? 'US'));
        $customer_tax_status = sanitize_text_field($data['customer_tax_status'] ?? 'taxable');
        $product_type = sanitize_text_field($data['product_type'] ?? 'standard');

        if ($org_id <= 0) {
            return new WP_Error('invalid_org', 'Organization is required for tax calculation');
        }

        if ($amount < 0) {
            return new WP_Error('invalid_amount', 'Amount cannot be negative');
        }

        if ($customer_tax_status === 'exempt') {
            $rate = 0.0;
            $tax_type = 'None';
            $rule_id = 'customer_exempt';
        } else {
            $config = self::get_active_config($org_id, $jurisdiction);
            if ($config) {
                $rate = floatval($config->default_tax_rate);
                $tax_type = $config->tax_type;
                $rule_id = 'org_config_' . intval($config->id);
            } else {
                $jurisdiction_rule = self::get_jurisdiction_rule($jurisdiction);
                $rate = floatval($jurisdiction_rule['default_rate'] ?? 0);
                $tax_type = sanitize_text_field($jurisdiction_rule['tax_type'] ?? 'Sales Tax');
                $rule_id = 'jurisdiction_' . $jurisdiction;
            }
        }

        if ($product_type === 'exempt') {
            $rate = 0.0;
            $rule_id = 'product_exempt';
        }

        $tax_amount = round($amount * ($rate / 100), 2);

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
        ], get_current_user_id(), $org_id);

        return $result;
    }

    public static function save_config($org_id, $data, $user_id = null) {
        global $wpdb;

        $org_id = intval($org_id);
        $user_id = $user_id ? intval($user_id) : get_current_user_id();
        $jurisdiction = strtoupper(sanitize_text_field($data['jurisdiction'] ?? ''));
        $rate = round(floatval($data['default_tax_rate'] ?? 0), 4);
        $tax_type = sanitize_text_field($data['tax_type'] ?? 'Sales Tax');

        if ($org_id <= 0 || $jurisdiction === '') {
            return new WP_Error('invalid_config', 'Organization and jurisdiction are required');
        }

        if ($rate < 0 || $rate > 100) {
            return new WP_Error('invalid_rate', 'Tax rate must be between 0 and 100');
        }

        if (!in_array($tax_type, ['VAT', 'GST', 'Sales Tax', 'None'], true)) {
            return new WP_Error('invalid_tax_type', 'Invalid tax type');
        }

        $table = OraBooks_Database::table('tax_configs');
        $override_reasons = $data['override_reasons'] ?? self::DEFAULT_OVERRIDE_REASONS;
        if (!is_array($override_reasons)) {
            $override_reasons = self::DEFAULT_OVERRIDE_REASONS;
        }

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
            'exemption_certificate_url' => isset($data['exemption_certificate_url']) ? esc_url_raw($data['exemption_certificate_url']) : null,
            'override_reasons' => wp_json_encode(array_values($override_reasons)),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];

        if ($existing_id) {
            $wpdb->update(
                $table,
                $payload,
                ['id' => $existing_id],
                ['%d', '%s', '%f', '%s', '%s', '%s', '%d'],
                ['%d']
            );
            $config_id = intval($existing_id);
        } else {
            $wpdb->insert(
                $table,
                $payload,
                ['%d', '%s', '%f', '%s', '%s', '%s', '%d']
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

        if (!in_array($transaction_type, ['invoice', 'expense', 'journal'], true)) {
            return new WP_Error('invalid_transaction_type', 'Invalid transaction type');
        }

        if (self::is_tax_locked($org_id, $data)) {
            return new WP_Error('tax_locked', 'Tax is locked for this transaction period');
        }

        if (!empty($data['override'])) {
            $calculation = self::build_override_calculation($data);
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

        orabooks_log_event(!empty($data['override']) ? 'tax_override' : 'tax_snapshot_created', 'Tax snapshot created', 'info', [
            'transaction_id' => $transaction_id,
            'transaction_type' => $transaction_type,
            'tax_amount' => $calculation['tax_amount'],
            'override_reason' => $calculation['override_reason'] ?? null,
        ], $user_id, $org_id);

        return [
            'snapshot_id' => $snapshot_id,
            'calculation' => $calculation,
        ];
    }

    public static function get_active_config($org_id, $jurisdiction) {
        global $wpdb;

        $table = OraBooks_Database::table('tax_configs');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d AND jurisdiction = %s AND is_active = 1 LIMIT 1",
            intval($org_id),
            strtoupper($jurisdiction)
        ));
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
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT tax_rules FROM {$table} WHERE jurisdiction_code = %s",
            strtoupper($jurisdiction)
        ));

        if ($row && !empty($row->tax_rules)) {
            $rules = json_decode($row->tax_rules, true);
            if (is_array($rules)) {
                return $rules;
            }
        }

        return ['default_rate' => 0.0, 'tax_type' => 'Sales Tax'];
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

    private static function is_tax_locked($org_id, $data) {
        if (class_exists('OraBooks_Fiscal') && method_exists('OraBooks_Fiscal', 'is_period_hard_closed')) {
            $date = $data['transaction_date'] ?? current_time('Y-m-d');
            return OraBooks_Fiscal::is_period_hard_closed($org_id, $date);
        }

        return false;
    }

    public function ajax_calculate() {
        check_ajax_referer('orabooks_nonce', 'nonce');

        $user_id = get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'create_invoice')) {
            orabooks_json_error('Permission denied', 403);
        }

        $result = self::calculate($_POST);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success($result);
    }

    public function ajax_save_config() {
        check_ajax_referer('orabooks_nonce', 'nonce');

        $user_id = get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_org_settings')) {
            orabooks_json_error('Permission denied', 403);
        }

        $result = self::save_config($org_id, $_POST, $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success(['config' => $result]);
    }

    public function ajax_create_snapshot() {
        check_ajax_referer('orabooks_nonce', 'nonce');

        $user_id = get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'submit_transaction')) {
            orabooks_json_error('Permission denied', 403);
        }

        $result = self::create_snapshot($_POST, $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success($result);
    }
}
