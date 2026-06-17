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
            add_action('wp_ajax_nopriv_orabooks_get_coa', [self::$instance, 'ajax_get_coa']);
            add_action('wp_ajax_orabooks_export_coa', [self::$instance, 'ajax_export_coa']);
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
        $table = $wpdb->prefix . 'orabooks_accounts';
        
        $inserted = 0;
        foreach ($template as $acc) {
            $result = $wpdb->insert(
                $table,
                [
                    'org_id' => $org_id,
                    'code' => $acc['code'],
                    'name' => $acc['name'],
                    'type' => $acc['type'],
                    'normal_balance' => $acc['normal_balance'],
                    'system_generated' => 1,
                    'is_active' => 1
                ],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%d']
            );
            if ($result) {
                $inserted++;
            }
        }
        
        // Initialize account balances
        $table_balances = $wpdb->prefix . 'orabooks_account_balances';
        $accounts = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$table} WHERE org_id = %d", $org_id
        ));
        foreach ($accounts as $account) {
            $wpdb->insert(
                $table_balances,
                ['org_id' => $org_id, 'account_id' => $account->id, 'balance' => 0],
                ['%d', '%d', '%f']
            );
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
        
        $table = $wpdb->prefix . 'orabooks_accounts';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, code, name, type, normal_balance, system_generated, is_active 
             FROM {$table} WHERE org_id = %d AND is_active = 1 
             ORDER BY code",
            $org_id
        ));
    }
    
    /**
     * Get account by code
     */
    public static function get_account_by_code($org_id, $code) {
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_accounts';
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
        $table = $wpdb->prefix . 'orabooks_accounts';
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
        $table = $wpdb->prefix . 'orabooks_account_balances';
        $balance = $wpdb->get_var($wpdb->prepare(
            "SELECT balance FROM {$table} WHERE account_id = %d AND org_id = %d",
            $account_id, $org_id
        ));
        return $balance ? (float) $balance : 0;
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
        orabooks_json_success($accounts);
    }
    
    public function ajax_export_coa() {
        $user_id = get_current_user_id();
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