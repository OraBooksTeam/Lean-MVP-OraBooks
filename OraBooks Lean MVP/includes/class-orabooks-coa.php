<?php
/**
 * OraBooks Chart of Accounts (SL-017)
 * 
 * Pre-loaded CoA templates per tier, account management,
 * partner org skip logic, and CSV export.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_COA {
    
    private static $instance = null;
    
    // Centralized account types enum
    const ACCOUNT_TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'];
    
    // CoA templates per tier
    private static $templates = [
        'free' => [
            ['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '3000', 'name' => 'Owner\'s Equity', 'type' => 'equity', 'normal_balance' => 'credit'],
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '5000', 'name' => 'Operating Expenses', 'type' => 'expense', 'normal_balance' => 'debit'],
        ],
        'premium' => [
            ['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1200', 'name' => 'Inventory', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2100', 'name' => 'Accrued Liabilities', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '3000', 'name' => 'Owner\'s Equity', 'type' => 'equity', 'normal_balance' => 'credit'],
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '5000', 'name' => 'Operating Expenses', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5100', 'name' => 'COGS', 'type' => 'expense', 'normal_balance' => 'debit'],
        ],
        'enterprise' => [
            ['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1200', 'name' => 'Inventory', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1300', 'name' => 'Prepaid Expenses', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1400', 'name' => 'Fixed Assets', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1500', 'name' => 'Accumulated Depreciation', 'type' => 'asset', 'normal_balance' => 'credit'],
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2100', 'name' => 'Accrued Liabilities', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2200', 'name' => 'Deferred Revenue', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2300', 'name' => 'Long-term Debt', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '3000', 'name' => 'Owner\'s Equity', 'type' => 'equity', 'normal_balance' => 'credit'],
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'normal_balance' => 'credit'],
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4100', 'name' => 'Service Revenue', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '5000', 'name' => 'Operating Expenses', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5100', 'name' => 'COGS', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5200', 'name' => 'Depreciation', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5300', 'name' => 'Tax Expenses', 'type' => 'expense', 'normal_balance' => 'debit'],
        ],
    ];
    
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            add_action('wp_ajax_orabooks_get_coa', [self::$instance, 'ajax_get_coa']);
            add_action('wp_ajax_orabooks_export_coa', [self::$instance, 'ajax_export_coa']);
            add_action('wp_ajax_orabooks_coa_create', [self::$instance, 'ajax_create_account']);
            add_action('wp_ajax_orabooks_coa_update', [self::$instance, 'ajax_update_account']);
        }
        return self::$instance;
    }
    
    /**
     * Load CoA for an organization on creation (called from SL-013 tier selection)
     * Skips for partner orgs
     */
    public static function load_chart_of_accounts($org_id, $tier, $organization_type) {
        global $wpdb;
        
        // Partner orgs: skip entirely
        if ($organization_type === 'partner' || $tier === 'partner') {
            orabooks_log_event('coa_skipped_partner', "Org $org_id is partner, no CoA loaded", 'info', [
                'org_id' => $org_id,
                'organization_type' => $organization_type
            ], null, $org_id);
            return;
        }
        
        $template = self::$templates[$tier] ?? self::$templates['free'];
        $table = OraBooks_Database::table('accounts');
        $table_balances = OraBooks_Database::table('account_balances');
        $inserted = 0;

        foreach ($template as $acc) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$table}
                    (org_id, code, name, type, normal_balance, system_generated, is_active)
                 VALUES (%d, %s, %s, %s, %s, 1, 1)",
                $org_id,
                $acc['code'],
                $acc['name'],
                $acc['type'],
                $acc['normal_balance']
            ));

            if ((int) $wpdb->insert_id > 0) {
                $inserted++;
                $wpdb->insert(
                    $table_balances,
                    ['org_id' => $org_id, 'account_id' => (int) $wpdb->insert_id, 'balance' => 0],
                    ['%d', '%d', '%f']
                );
            }
        }
        
        orabooks_log_event('coa_loaded', "Tier $tier template loaded with $inserted accounts", 'info', [
            'org_id' => $org_id,
            'tier' => $tier,
            'accounts_count' => $inserted
        ], null, $org_id);
    }
    
    /**
     * Get accounts for an organization
     */
    public static function get_accounts($org_id) {
        global $wpdb;
        
        $org = OraBooks_Organization::get($org_id);
        if (!$org) {
            return [];
        }
        
        // Partner orgs return empty
        if ($org->organization_type === 'partner') {
            return [];
        }
        
        $table = OraBooks_Database::table('accounts');
        $table_lines = OraBooks_Database::table('journal_lines');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.code, a.name, a.type, a.normal_balance, a.system_generated, a.is_active,
                    CASE WHEN EXISTS (
                        SELECT 1 FROM {$table_lines} jl WHERE jl.account_id = a.id LIMIT 1
                    ) THEN 1 ELSE 0 END AS has_journal_entries
             FROM {$table} a
             WHERE a.org_id = %d AND a.is_active = 1
             ORDER BY a.code",
            $org_id
        ));
    }
    
    /**
     * Get account by code
     */
    public static function get_account_by_code($org_id, $code) {
        global $wpdb;
        $table = OraBooks_Database::table('accounts');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d AND code = %s AND is_active = 1",
            $org_id, $code
        ));
    }
    
    /**
     * Get account by ID
     */
    public static function get_account($account_id, $org_id) {
        global $wpdb;
        $table = OraBooks_Database::table('accounts');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND org_id = %d",
            $account_id, $org_id
        ));
    }
    
    /**
     * Get account balance
     */
    public static function get_account_balance($account_id, $org_id) {
        global $wpdb;
        $table = OraBooks_Database::table('account_balances');
        $balance = $wpdb->get_var($wpdb->prepare(
            "SELECT balance FROM {$table} WHERE account_id = %d AND org_id = %d",
            $account_id, $org_id
        ));
        return $balance ? (float) $balance : 0;
    }

    public static function default_normal_balance($type) {
        return in_array($type, ['asset', 'expense'], true) ? 'debit' : 'credit';
    }

    public static function account_has_journal_entries($account_id) {
        global $wpdb;

        $table_lines = OraBooks_Database::table('journal_lines');
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$table_lines} WHERE account_id = %d LIMIT 1",
            (int) $account_id
        ));
    }

    public static function format_account_for_api($account) {
        if (!$account) {
            return [];
        }

        return [
            'id'                  => (int) $account->id,
            'code'                => $account->code,
            'name'                => $account->name,
            'type'                => $account->type,
            'normal_balance'      => $account->normal_balance,
            'system_generated'    => (int) ($account->system_generated ?? 0),
            'is_active'           => (int) ($account->is_active ?? 1),
            'has_journal_entries' => isset($account->has_journal_entries)
                ? (int) $account->has_journal_entries
                : (int) self::account_has_journal_entries((int) $account->id),
            'can_edit'            => (int) ($account->is_active ?? 1) === 1,
        ];
    }

    public static function create_account($org_id, array $data) {
        global $wpdb;

        $org = OraBooks_Organization::get($org_id);
        if (!$org || $org->organization_type === 'partner') {
            return new WP_Error('accounting_isolation', 'Partner organizations cannot manage chart of accounts.');
        }

        $code = sanitize_text_field($data['code'] ?? '');
        $name = sanitize_text_field($data['name'] ?? '');
        $type = strtolower(sanitize_text_field($data['type'] ?? ''));
        $normal_balance = strtolower(sanitize_text_field($data['normal_balance'] ?? ''));

        if ($code === '' || $name === '') {
            return new WP_Error('invalid_account', 'Account code and name are required.');
        }
        if (!in_array($type, self::ACCOUNT_TYPES, true)) {
            return new WP_Error('invalid_type', 'Invalid account type.');
        }
        if ($normal_balance === '') {
            $normal_balance = self::default_normal_balance($type);
        }
        if (!in_array($normal_balance, ['debit', 'credit'], true)) {
            return new WP_Error('invalid_normal_balance', 'Normal balance must be debit or credit.');
        }

        $table = OraBooks_Database::table('accounts');
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE org_id = %d AND code = %s LIMIT 1",
            (int) $org_id,
            $code
        ));
        if ($existing) {
            return new WP_Error('duplicate_code', 'An account with this code already exists.');
        }

        $inserted = $wpdb->insert($table, [
            'org_id'           => (int) $org_id,
            'code'             => $code,
            'name'             => $name,
            'type'             => $type,
            'normal_balance'   => $normal_balance,
            'system_generated' => 0,
            'is_active'        => 1,
        ], ['%d', '%s', '%s', '%s', '%s', '%d', '%d']);

        if (!$inserted) {
            return new WP_Error('db_error', 'Failed to create account.');
        }

        $account_id = (int) $wpdb->insert_id;
        $table_balances = OraBooks_Database::table('account_balances');
        $wpdb->insert($table_balances, [
            'org_id'     => (int) $org_id,
            'account_id' => $account_id,
            'balance'    => 0,
        ], ['%d', '%d', '%f']);

        orabooks_log_event('account_created', "Account {$code} created", 'info', [
            'account_id' => $account_id,
            'code'       => $code,
            'type'       => $type,
        ], null, (int) $org_id);

        return $account_id;
    }

    public static function update_account($account_id, $org_id, array $data, $user_id = 0) {
        global $wpdb;

        $account = self::get_account($account_id, $org_id);
        if (!$account) {
            return new WP_Error('not_found', 'Account not found.');
        }

        $org = OraBooks_Organization::get($org_id);
        if (!$org || $org->organization_type === 'partner') {
            return new WP_Error('accounting_isolation', 'Partner organizations cannot manage chart of accounts.');
        }

        $has_entries = self::account_has_journal_entries((int) $account_id);
        $is_system = (int) ($account->system_generated ?? 0) === 1;

        if (class_exists('OraBooks_Fiscal')) {
            $structure_lock = OraBooks_Fiscal::can_modify_account_structure($org_id);
            if (is_wp_error($structure_lock)) {
                if (
                    isset($data['type']) && strtolower((string) $data['type']) !== strtolower((string) $account->type)
                    || isset($data['normal_balance']) && strtolower((string) $data['normal_balance']) !== strtolower((string) $account->normal_balance)
                    || isset($data['code']) && sanitize_text_field((string) $data['code']) !== (string) $account->code
                ) {
                    return $structure_lock;
                }
            }
        }

        $updates = [];
        $formats = [];

        if (isset($data['name'])) {
            $name = sanitize_text_field($data['name']);
            if ($name === '') {
                return new WP_Error('invalid_account', 'Account name is required.');
            }
            $updates['name'] = $name;
            $formats[] = '%s';
        }

        if (isset($data['code']) && sanitize_text_field((string) $data['code']) !== (string) $account->code) {
            if ($is_system) {
                return new WP_Error('system_account_locked', 'System account codes cannot be changed.');
            }
            if ($has_entries) {
                return new WP_Error('account_in_use', 'Account code cannot be changed after journal entries exist.');
            }
            $code = sanitize_text_field($data['code']);
            if ($code === '') {
                return new WP_Error('invalid_account', 'Account code is required.');
            }
            $table = OraBooks_Database::table('accounts');
            $duplicate = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE org_id = %d AND code = %s AND id != %d LIMIT 1",
                (int) $org_id,
                $code,
                (int) $account_id
            ));
            if ($duplicate) {
                return new WP_Error('duplicate_code', 'An account with this code already exists.');
            }
            $updates['code'] = $code;
            $formats[] = '%s';
        }

        if (isset($data['type']) && strtolower((string) $data['type']) !== strtolower((string) $account->type)) {
            if ($is_system || $has_entries) {
                return new WP_Error('account_in_use', 'Account type cannot be changed for system or used accounts.');
            }
            $type = strtolower(sanitize_text_field($data['type']));
            if (!in_array($type, self::ACCOUNT_TYPES, true)) {
                return new WP_Error('invalid_type', 'Invalid account type.');
            }
            $updates['type'] = $type;
            $formats[] = '%s';
        }

        if (
            isset($data['normal_balance'])
            && strtolower((string) $data['normal_balance']) !== strtolower((string) $account->normal_balance)
        ) {
            if ($is_system || $has_entries) {
                return new WP_Error('account_in_use', 'Normal balance cannot be changed for system or used accounts.');
            }
            $normal_balance = strtolower(sanitize_text_field($data['normal_balance']));
            if (!in_array($normal_balance, ['debit', 'credit'], true)) {
                return new WP_Error('invalid_normal_balance', 'Normal balance must be debit or credit.');
            }
            $updates['normal_balance'] = $normal_balance;
            $formats[] = '%s';
        }

        if (empty($updates)) {
            return new WP_Error('no_changes', 'No editable fields were provided.');
        }

        $table = OraBooks_Database::table('accounts');
        $updated = $wpdb->update(
            $table,
            $updates,
            ['id' => (int) $account_id, 'org_id' => (int) $org_id],
            $formats,
            ['%d', '%d']
        );

        if ($updated === false) {
            return new WP_Error('update_failed', 'Failed to update account.');
        }

        orabooks_log_event('account_updated', "Account {$account->code} updated", 'info', [
            'account_id' => (int) $account_id,
            'updates'    => array_keys($updates),
        ], (int) $user_id, (int) $org_id);

        return true;
    }
    
    /**
     * Export CoA as CSV
     */
    public static function export_csv($org_id) {
        $accounts = self::get_accounts($org_id);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="chart_of_accounts_' . $org_id . '_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['code', 'name', 'type', 'normal_balance', 'system_generated', 'is_active']);
        
        foreach ($accounts as $acc) {
            fputcsv($output, [$acc->code, $acc->name, $acc->type, $acc->normal_balance, $acc->system_generated, $acc->is_active]);
        }
        
        fclose($output);
        exit;
    }
    
    // AJAX handlers
    public function ajax_get_coa() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_GET['org_id'] ?? 0);
        
        // SL-013: Enforce customer org isolation on accounting endpoints
        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }
        
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'view_coa')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $accounts = self::get_accounts($org_id);
        orabooks_json_success(array_map([self::class, 'format_account_for_api'], $accounts ?: []));
    }
    
    public function ajax_create_account() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_coa')) {
            orabooks_json_error('Permission denied', 403);
        }

        $account_id = self::create_account($org_id, [
            'code'           => $_POST['code'] ?? '',
            'name'           => $_POST['name'] ?? '',
            'type'           => $_POST['type'] ?? '',
            'normal_balance' => $_POST['normal_balance'] ?? '',
        ]);
        if (is_wp_error($account_id)) {
            orabooks_json_error($account_id->get_error_message(), 409);
        }

        $account = self::get_account((int) $account_id, $org_id);
        orabooks_json_success([
            'account_id' => (int) $account_id,
            'account'    => self::format_account_for_api($account),
        ]);
    }

    public function ajax_update_account() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $account_id = intval($_POST['account_id'] ?? 0);

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_coa')) {
            orabooks_json_error('Permission denied', 403);
        }

        $result = self::update_account($account_id, $org_id, array_filter([
            'code'           => isset($_POST['code']) ? wp_unslash($_POST['code']) : null,
            'name'           => isset($_POST['name']) ? wp_unslash($_POST['name']) : null,
            'type'           => isset($_POST['type']) ? wp_unslash($_POST['type']) : null,
            'normal_balance' => isset($_POST['normal_balance']) ? wp_unslash($_POST['normal_balance']) : null,
        ], static function ($value) {
            return $value !== null;
        }), $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 409);
        }

        $account = self::get_account($account_id, $org_id);
        orabooks_json_success([
            'account_id' => $account_id,
            'account'    => self::format_account_for_api($account),
        ]);
    }

    public function ajax_export_coa() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_GET['org_id'] ?? 0);
        
        // SL-013: Enforce customer org isolation on accounting endpoints
        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }
        
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'view_coa')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        self::export_csv($org_id);
    }
}