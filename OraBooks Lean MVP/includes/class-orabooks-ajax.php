<?php
/**
 * OraBooks AJAX Handlers
 * 
 * Additional admin AJAX handlers for organization management.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Ajax {
    
    private static $instance = null;
    
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            add_action('wp_ajax_orabooks_list_orgs', [self::$instance, 'ajax_list_orgs']);
            add_action('wp_ajax_orabooks_suspend_org', [self::$instance, 'ajax_suspend_org']);
            add_action('wp_ajax_orabooks_activate_org', [self::$instance, 'ajax_activate_org']);
            add_action('wp_ajax_orabooks_change_org_region', [self::$instance, 'ajax_change_org_region']);
            add_action('wp_ajax_orabooks_get_org_by_subdomain', [self::$instance, 'ajax_get_org_by_subdomain']);
            add_action('wp_ajax_nopriv_orabooks_get_org_by_subdomain', [self::$instance, 'ajax_get_org_by_subdomain']);
            add_action('wp_ajax_orabooks_list_users', [self::$instance, 'ajax_list_users']);
            add_action('wp_ajax_orabooks_dashboard_stats', [self::$instance, 'ajax_dashboard_stats']);
            add_action('wp_ajax_orabooks_platform_settings_get', [self::$instance, 'ajax_platform_settings_get']);
            add_action('wp_ajax_orabooks_platform_settings_save', [self::$instance, 'ajax_platform_settings_save']);
            add_action('wp_ajax_orabooks_deploy_checks', [self::$instance, 'ajax_deploy_checks']);
            add_action('wp_ajax_orabooks_deploy_repair', [self::$instance, 'ajax_deploy_repair']);
            add_action('wp_ajax_orabooks_frontend_context', [self::$instance, 'ajax_frontend_context']);
            add_action('wp_ajax_orabooks_customer_dashboard', [self::$instance, 'ajax_customer_dashboard']);
            add_action('wp_ajax_orabooks_vendor_dashboard', [self::$instance, 'ajax_vendor_dashboard']);
            add_action('wp_ajax_orabooks_inventory_dashboard', [self::$instance, 'ajax_inventory_dashboard']);
            add_action('wp_ajax_orabooks_bank_dashboard', [self::$instance, 'ajax_bank_dashboard']);
            add_action('wp_ajax_orabooks_reports_dashboard', [self::$instance, 'ajax_reports_dashboard']);
            add_action('wp_ajax_orabooks_csv_imports_dashboard', [self::$instance, 'ajax_csv_imports_dashboard']);
            add_action('wp_ajax_orabooks_team_dashboard', [self::$instance, 'ajax_team_dashboard']);
            add_action('wp_ajax_orabooks_attachments_dashboard', [self::$instance, 'ajax_attachments_dashboard']);
            add_action('wp_ajax_orabooks_approval_dashboard', [self::$instance, 'ajax_approval_dashboard']);
            add_action('wp_ajax_orabooks_ai_review_dashboard', [self::$instance, 'ajax_ai_review_dashboard']);
            add_action('wp_ajax_orabooks_expenses_dashboard', [self::$instance, 'ajax_expenses_dashboard']);
            add_action('wp_ajax_orabooks_voice_dashboard', [self::$instance, 'ajax_voice_dashboard']);
            add_action('wp_ajax_nopriv_orabooks_frontend_context', [self::$instance, 'ajax_frontend_context']);
            add_action('wp_ajax_nopriv_orabooks_customer_dashboard', [self::$instance, 'ajax_customer_dashboard']);
            add_action('wp_ajax_nopriv_orabooks_vendor_dashboard', [self::$instance, 'ajax_vendor_dashboard']);
            add_action('wp_ajax_nopriv_orabooks_inventory_dashboard', [self::$instance, 'ajax_inventory_dashboard']);
            add_action('wp_ajax_nopriv_orabooks_bank_dashboard', [self::$instance, 'ajax_bank_dashboard']);
            add_action('wp_ajax_nopriv_orabooks_reports_dashboard', [self::$instance, 'ajax_reports_dashboard']);
            add_action('wp_ajax_nopriv_orabooks_csv_imports_dashboard', [self::$instance, 'ajax_csv_imports_dashboard']);
            add_action('wp_ajax_nopriv_orabooks_team_dashboard', [self::$instance, 'ajax_team_dashboard']);
            add_action('wp_ajax_nopriv_orabooks_attachments_dashboard', [self::$instance, 'ajax_attachments_dashboard']);
            add_action('wp_ajax_nopriv_orabooks_approval_dashboard', [self::$instance, 'ajax_approval_dashboard']);
            add_action('wp_ajax_nopriv_orabooks_ai_review_dashboard', [self::$instance, 'ajax_ai_review_dashboard']);
            add_action('wp_ajax_nopriv_orabooks_expenses_dashboard', [self::$instance, 'ajax_expenses_dashboard']);
            add_action('wp_ajax_nopriv_orabooks_voice_dashboard', [self::$instance, 'ajax_voice_dashboard']);
            
            // Register settings
            add_action('admin_init', [self::$instance, 'register_settings']);
        }
        return self::$instance;
    }
    
    public function register_settings() {
        register_setting('orabooks_settings', 'orabooks_block_same_email_domain');
        register_setting('orabooks_settings', 'orabooks_partner_commission_for_staff_viewer');
        register_setting('orabooks_settings', 'orabooks_audit_retention_days');
        register_setting('orabooks_settings', 'orabooks_jwt_expiry');
        register_setting('orabooks_settings', 'orabooks_refresh_token_expiry');
    }

    private function get_current_orabooks_context() {
        global $wpdb;

        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'Please log in to continue.');
        }

        $table_users = OraBooks_Database::table('users');
        $table_user_org = OraBooks_Database::table('user_org');
        $table_orgs = OraBooks_Database::table('organizations');

        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, email, is_partner, is_email_verified, is_2fa_enabled, org_id
             FROM {$table_users}
             WHERE id = %d",
            $user_id
        ));

        if (!$user) {
            return new WP_Error('user_not_found', 'OraBooks user record was not found.');
        }

        $org_id = orabooks_get_current_org_id($user_id);

        $org = $org_id ? $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, tier, subdomain, region, status, organization_type, owner_id, config
             FROM {$table_orgs}
             WHERE id = %d",
            $org_id
        )) : null;

        $role = $org ? orabooks_get_user_role($user_id, (int) $org->id) : null;
        $permissions = [];
        if ($role && class_exists('OraBooks_RBAC')) {
            $permissions = class_exists('OBN_Access_Control')
                ? OBN_Access_Control::get_effective_permissions($role, $org ? (int) $org->id : null)
                : OraBooks_RBAC::get_effective_permissions($role, $org ? (int) $org->id : null);
        }

        $org_require_2fa = $org && class_exists('OraBooks_TwoFactor')
            ? OraBooks_TwoFactor::org_requires_2fa((int) $org->id)
            : false;
        $needs_2fa_setup = $org && class_exists('OraBooks_TwoFactor')
            ? OraBooks_TwoFactor::user_needs_2fa_setup($user_id, (int) $org->id)
            : false;
        $remaining_backup_codes = class_exists('OraBooks_TwoFactor')
            ? OraBooks_TwoFactor::count_remaining_backup_codes($user_id)
            : 0;

        return [
            'user_id' => $user_id,
            'user' => [
                'id' => (int) $user->id,
                'email' => $user->email,
                'is_partner' => (bool) $user->is_partner,
                'is_email_verified' => (bool) $user->is_email_verified,
                'is_2fa_enabled' => (bool) $user->is_2fa_enabled,
                'remaining_backup_codes' => $remaining_backup_codes,
            ],
            'organization' => $org ? [
                'id' => (int) $org->id,
                'name' => $org->name,
                'tier' => $org->tier,
                'subdomain' => $org->subdomain,
                'region' => $org->region,
                'status' => $org->status,
                'organization_type' => $org->organization_type,
                'owner_id' => (int) $org->owner_id,
                'require_2fa' => $org_require_2fa,
            ] : null,
            'security' => [
                'needs_2fa_setup' => $needs_2fa_setup,
                'org_require_2fa' => $org_require_2fa,
            ],
            'role' => $role,
            'permissions' => $permissions,
            'permission_matrix' => class_exists('OBN_Access_Control') ? OBN_Access_Control::get_permission_matrix(true) : [],
            'is_admin' => current_user_can('manage_options'),
        ];
    }

    public function ajax_frontend_context() {
        $context = $this->get_current_orabooks_context();
        if (is_wp_error($context)) {
            orabooks_json_error($context->get_error_message(), 401);
        }

        orabooks_json_success($context);
    }

    public function ajax_customer_dashboard() {
        global $wpdb;

        $context = $this->get_current_orabooks_context();
        if (is_wp_error($context)) {
            orabooks_json_error($context->get_error_message(), 401);
        }

        $org = $context['organization'];
        $org_id = $org ? (int) $org['id'] : 0;
        if (!$org_id) {
            orabooks_json_error('Organization is not set up yet.', 400);
        }

        if (($org['organization_type'] ?? '') === 'partner') {
            orabooks_json_error('Partner accounts cannot perform accounting operations.', 403);
        }

        if (!OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'view_reports')) {
            orabooks_json_error('Permission denied', 403);
        }

        if (class_exists('OraBooks_Customers') && !OraBooks_Customers::schema_is_ready()) {
            if (function_exists('orabooks_with_data_blog')) {
                orabooks_with_data_blog(['OraBooks_Customers', 'ensure_schema']);
            } else {
                OraBooks_Customers::ensure_schema();
            }
        }

        $stats = class_exists('OraBooks_Customers') ? OraBooks_Customers::get_customer_stats($org_id) : [];
        $accounts_table = OraBooks_Database::table('accounts');
        $journals_table = OraBooks_Database::table('journals');
        $invoices_table = OraBooks_Database::table('invoices');

        $accounts_summary = $wpdb->get_results($wpdb->prepare(
            "SELECT type, COUNT(*) AS total
             FROM {$accounts_table}
             WHERE org_id = %d AND is_active = 1
             GROUP BY type",
            $org_id
        ));

        $journal_statuses = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) AS total
             FROM {$journals_table}
             WHERE org_id = %d
             GROUP BY status",
            $org_id
        ));

        $recent_journals = $wpdb->get_results($wpdb->prepare(
            "SELECT id, journal_number, status, transaction_date, total_amount, source_type, created_at
             FROM {$journals_table}
             WHERE org_id = %d
             ORDER BY created_at DESC
             LIMIT 8",
            $org_id
        ));

        $recent_invoices = $wpdb->get_results($wpdb->prepare(
            "SELECT id, invoice_number, customer_id, invoice_date, due_date, total_amount, paid_amount, payment_status, workflow_status, currency
             FROM {$invoices_table}
             WHERE org_id = %d
             ORDER BY created_at DESC
             LIMIT 8",
            $org_id
        ));

        $recent_customers = class_exists('OraBooks_Customers')
            ? OraBooks_Customers::get_list($org_id, ['limit' => 25, 'offset' => 0])
            : ['customers' => [], 'total' => 0, 'page' => 1, 'per_page' => 25];
        $can_manage_event_bus = current_user_can('manage_options') || (int) ($org['owner_id'] ?? 0) === (int) $context['user_id'];
        $event_org_scope = current_user_can('manage_options') ? 0 : $org_id;
        $event_bus_health = $can_manage_event_bus && class_exists('OraBooks_Event_Module')
            ? array_merge(OraBooks_Event_Module::get_health($event_org_scope), ['can_manage' => true])
            : null;

        orabooks_json_success([
            'context' => $context,
            'stats' => $stats,
            'accounts_summary' => $accounts_summary ?: [],
            'journal_statuses' => $journal_statuses ?: [],
            'recent_journals' => $recent_journals ?: [],
            'recent_invoices' => $recent_invoices ?: [],
            'recent_customers' => $recent_customers,
            'event_bus_health' => $event_bus_health,
            'timestamp' => current_time('mysql'),
        ]);
    }

    public function ajax_vendor_dashboard() {
        global $wpdb;

        $context = $this->get_current_orabooks_context();
        if (is_wp_error($context)) {
            orabooks_json_error($context->get_error_message(), 401);
        }

        $org = $context['organization'];
        $org_id = $org ? (int) $org['id'] : 0;
        if (!$org_id) {
            orabooks_json_error('Organization is not set up yet.', 400);
        }

        if (($org['organization_type'] ?? '') === 'partner') {
            orabooks_json_error('Partner accounts cannot perform accounting operations.', 403);
        }

        if (!OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'view_reports')) {
            orabooks_json_error('Permission denied', 403);
        }

        $vendors_table = OraBooks_Database::table('vendors');
        $bills_table = OraBooks_Database::table('bills');

        $vendor_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) AS total_vendors,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_vendors,
                COALESCE(SUM(payable_balance), 0) AS total_payable,
                COALESCE(SUM(credit_balance), 0) AS total_credit
             FROM {$vendors_table}
             WHERE org_id = %d",
            $org_id
        ));

        $bill_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT workflow_status, payment_status, COUNT(*) AS total
             FROM {$bills_table}
             WHERE org_id = %d
             GROUP BY workflow_status, payment_status",
            $org_id
        ));

        $recent_vendors = class_exists('OraBooks_Vendors')
            ? OraBooks_Vendors::get_vendors_list($org_id, ['limit' => 25, 'offset' => 0])
            : ['vendors' => [], 'total' => 0, 'page' => 1, 'per_page' => 25];

        $recent_bills = class_exists('OraBooks_Vendors')
            ? OraBooks_Vendors::get_bills_list($org_id, ['limit' => 25, 'offset' => 0])
            : ['bills' => [], 'total' => 0, 'page' => 1, 'per_page' => 25];

        $ap_aging = class_exists('OraBooks_Vendors')
            ? OraBooks_Vendors::get_ap_aging($org_id)
            : ['current' => 0, '30' => 0, '60' => 0, '90_plus' => 0];

        orabooks_json_success([
            'context' => $context,
            'stats' => [
                'total_vendors' => (int) ($vendor_stats->total_vendors ?? 0),
                'active_vendors' => (int) ($vendor_stats->active_vendors ?? 0),
                'total_payable' => (float) ($vendor_stats->total_payable ?? 0),
                'total_credit' => (float) ($vendor_stats->total_credit ?? 0),
            ],
            'bill_stats' => $bill_stats ?: [],
            'ap_aging' => $ap_aging,
            'recent_vendors' => $recent_vendors,
            'recent_bills' => $recent_bills,
            'timestamp' => current_time('mysql'),
        ]);
    }

    public function ajax_inventory_dashboard() {
        global $wpdb;

        $context = $this->get_current_orabooks_context();
        if (is_wp_error($context)) {
            orabooks_json_error($context->get_error_message(), 401);
        }

        $org = $context['organization'];
        $org_id = $org ? (int) $org['id'] : 0;
        if (!$org_id) {
            orabooks_json_error('Organization is not set up yet.', 400);
        }

        if (($org['organization_type'] ?? '') === 'partner') {
            orabooks_json_error('Partner accounts cannot perform accounting operations.', 403);
        }

        if (!OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'view_reports')) {
            orabooks_json_error('Permission denied', 403);
        }

        $products_table = OraBooks_Database::table('products');

        $product_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) AS total_products,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_products,
                COALESCE(SUM(current_stock * average_cost), 0) AS total_stock_value,
                SUM(CASE
                    WHEN low_stock_threshold IS NOT NULL
                         AND current_stock <= low_stock_threshold
                         AND is_active = 1
                    THEN 1 ELSE 0 END) AS low_stock_count
             FROM {$products_table}
             WHERE org_id = %d",
            $org_id
        ));

        $recent_products = class_exists('OraBooks_Inventory')
            ? OraBooks_Inventory::get_products_list($org_id, ['limit' => 25, 'offset' => 0])
            : ['products' => [], 'total' => 0, 'page' => 1, 'per_page' => 25];

        $recent_movements = class_exists('OraBooks_Inventory')
            ? OraBooks_Inventory::get_recent_movements($org_id, ['limit' => 25, 'offset' => 0])
            : [];

        orabooks_json_success([
            'context' => $context,
            'stats' => [
                'total_products' => (int) ($product_stats->total_products ?? 0),
                'active_products' => (int) ($product_stats->active_products ?? 0),
                'total_stock_value' => (float) ($product_stats->total_stock_value ?? 0),
                'low_stock_count' => (int) ($product_stats->low_stock_count ?? 0),
            ],
            'recent_products' => $recent_products,
            'recent_movements' => $recent_movements,
            'timestamp' => current_time('mysql'),
        ]);
    }

    public function ajax_bank_dashboard() {
        global $wpdb;

        $context = $this->get_current_orabooks_context();
        if (is_wp_error($context)) {
            orabooks_json_error($context->get_error_message(), 401);
        }

        $org = $context['organization'];
        $org_id = $org ? (int) $org['id'] : 0;
        if (!$org_id) {
            orabooks_json_error('Organization is not set up yet.', 400);
        }

        if (($org['organization_type'] ?? '') === 'partner') {
            orabooks_json_error('Partner accounts cannot perform accounting operations.', 403);
        }

        if (!OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'view_reports')) {
            orabooks_json_error('Permission denied', 403);
        }

        $accounts_table = OraBooks_Database::table('bank_accounts');
        $transactions_table = OraBooks_Database::table('bank_transactions');

        $account_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) AS total_accounts,
                COALESCE(SUM(current_balance), 0) AS total_balance
             FROM {$accounts_table}
             WHERE org_id = %d AND is_active = 1",
            $org_id
        ));

        $transaction_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) AS total
             FROM {$transactions_table}
             WHERE org_id = %d
             GROUP BY status",
            $org_id
        ));

        $status_counts = [
            'unmatched' => 0,
            'matched' => 0,
            'reconciled' => 0,
            'skipped' => 0,
        ];
        foreach ($transaction_stats ?: [] as $row) {
            $status_counts[$row->status] = (int) $row->total;
        }

        $accounts = class_exists('OraBooks_Bank_Reconciliation')
            ? OraBooks_Bank_Reconciliation::get_accounts_list($org_id)
            : [];

        $recent_transactions = class_exists('OraBooks_Bank_Reconciliation')
            ? OraBooks_Bank_Reconciliation::get_recent_transactions($org_id, ['limit' => 25, 'offset' => 0])
            : [];

        $recent_reconciliations = class_exists('OraBooks_Bank_Reconciliation')
            ? OraBooks_Bank_Reconciliation::get_recent_reconciliation_log($org_id, ['limit' => 10])
            : [];

        orabooks_json_success([
            'context' => $context,
            'stats' => [
                'total_accounts' => (int) ($account_stats->total_accounts ?? 0),
                'total_balance' => (float) ($account_stats->total_balance ?? 0),
                'unmatched_count' => $status_counts['unmatched'],
                'matched_count' => $status_counts['matched'],
                'reconciled_count' => $status_counts['reconciled'],
                'skipped_count' => $status_counts['skipped'],
            ],
            'accounts' => $accounts,
            'recent_transactions' => $recent_transactions,
            'recent_reconciliations' => $recent_reconciliations,
            'timestamp' => current_time('mysql'),
        ]);
    }

    public function ajax_reports_dashboard() {
        $context = $this->get_current_orabooks_context();
        if (is_wp_error($context)) {
            orabooks_json_error($context->get_error_message(), 401);
        }

        $org = $context['organization'];
        $org_id = $org ? (int) $org['id'] : 0;
        if (!$org_id) {
            orabooks_json_error('Organization is not set up yet.', 400);
        }

        if (($org['organization_type'] ?? '') === 'partner') {
            orabooks_json_error('Partner accounts cannot perform accounting operations.', 403);
        }

        if (!OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'view_reports')) {
            orabooks_json_error('Permission denied', 403);
        }

        $period_start = date('Y-m-01');
        $period_end = current_time('Y-m-d');
        $as_of_date = current_time('Y-m-d');

        $financial_preview = null;
        if (class_exists('OraBooks_Financial_Reports')) {
            $pl = OraBooks_Financial_Reports::generate_report(
                $org_id,
                'profit_loss',
                $period_start,
                $period_end,
                ['generated_by' => $context['user_id']]
            );
            if (!is_wp_error($pl)) {
                $report = $pl['report'] ?? [];
                $financial_preview = [
                    'total_revenue' => (float) ($report['total_revenue'] ?? 0),
                    'total_expenses' => (float) ($report['total_expenses'] ?? 0),
                    'net_income' => (float) ($report['net_income'] ?? 0),
                    'period_start' => $period_start,
                    'period_end' => $period_end,
                    'from_cache' => !empty($pl['from_cache']),
                ];
            }
        }

        $operational_preview = [
            'net_sales_mtd' => 0.0,
            'ar_customers' => 0,
            'low_stock_items' => 0,
        ];

        if (class_exists('OraBooks_Operational_Reports')) {
            $sales = OraBooks_Operational_Reports::generate_report($org_id, 'sales_summary', [
                'start_date' => $period_start,
                'end_date' => $period_end,
            ]);
            if (!is_wp_error($sales) && is_array($sales['data'] ?? null)) {
                foreach ($sales['data'] as $row) {
                    $operational_preview['net_sales_mtd'] += (float) ($row->net_sales ?? 0);
                }
            }

            $ar = OraBooks_Operational_Reports::generate_report($org_id, 'ar_aging', [
                'as_of_date' => $as_of_date,
            ]);
            if (!is_wp_error($ar) && is_array($ar['data'] ?? null)) {
                $operational_preview['ar_customers'] = count($ar['data']);
            }

            $inventory = OraBooks_Operational_Reports::generate_report($org_id, 'inventory_status', [
                'status' => 'low',
            ]);
            if (!is_wp_error($inventory) && is_array($inventory['data'] ?? null)) {
                $operational_preview['low_stock_items'] = count($inventory['data']);
            }
        }

        $recent_snapshots = class_exists('OraBooks_Financial_Reports')
            ? OraBooks_Financial_Reports::get_recent_snapshots($org_id, ['limit' => 10])
            : [];

        orabooks_json_success([
            'context' => $context,
            'period' => [
                'start' => $period_start,
                'end' => $period_end,
                'as_of_date' => $as_of_date,
            ],
            'financial_types' => [
                ['id' => 'profit_loss', 'label' => 'Profit & Loss'],
                ['id' => 'balance_sheet', 'label' => 'Balance Sheet'],
                ['id' => 'cash_flow', 'label' => 'Cash Flow'],
                ['id' => 'trial_balance', 'label' => 'Trial Balance'],
                ['id' => 'changes_equity', 'label' => 'Changes in Equity'],
            ],
            'operational_types' => [
                ['id' => 'ar_aging', 'label' => 'AR Aging'],
                ['id' => 'ap_aging', 'label' => 'AP Aging'],
                ['id' => 'inventory_status', 'label' => 'Inventory Status'],
                ['id' => 'bank_reconciliation', 'label' => 'Bank Reconciliation'],
                ['id' => 'sales_summary', 'label' => 'Sales Summary'],
                ['id' => 'purchase_summary', 'label' => 'Purchase Summary'],
            ],
            'financial_preview' => $financial_preview,
            'operational_preview' => $operational_preview,
            'recent_snapshots' => $recent_snapshots,
            'timestamp' => current_time('mysql'),
        ]);
    }

    public function ajax_csv_imports_dashboard() {
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        if (current_user_can('manage_options')) {
            global $wpdb;
            $table = OraBooks_Database::table('csv_imports');
            $imports = $wpdb->get_results(
                "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 50"
            );
            $formatted = class_exists('OraBooks_Csv_Imports')
                ? array_map([OraBooks_Csv_Imports::class, 'format_import'], $imports ?: [])
                : [];

            orabooks_json_success([
                'imports' => $formatted,
                'recent_imports' => $formatted,
                'is_platform_admin' => true,
                'timestamp' => current_time('mysql'),
            ]);
            return;
        }

        $context = $this->get_current_orabooks_context();
        if (is_wp_error($context)) {
            orabooks_json_error($context->get_error_message(), 401);
        }

        $org = $context['organization'];
        $org_id = $org ? (int) $org['id'] : 0;
        if (!$org_id) {
            orabooks_json_error('Organization is not set up yet.', 400);
        }

        if (($org['organization_type'] ?? '') === 'partner') {
            orabooks_json_error('Partner accounts cannot perform accounting operations.', 403);
        }

        if (!OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'submit_transaction')) {
            orabooks_json_error('Permission denied', 403);
        }

        $stats = [
            'total' => 0,
            'uploaded' => 0,
            'parsing' => 0,
            'mapping' => 0,
            'pending_confirm' => 0,
            'confirmed' => 0,
            'failed' => 0,
        ];
        $recent_imports = [];
        $resource_types = [];

        if (class_exists('OraBooks_Csv_Imports')) {
            $stats = OraBooks_Csv_Imports::get_import_stats($org_id, $context['user_id']);
            $recent = OraBooks_Csv_Imports::list_imports($org_id, $context['user_id'], 15);
            $recent_imports = array_map([OraBooks_Csv_Imports::class, 'format_import'], $recent ?: []);

            $labels = [
                'inventory_item' => 'Inventory Items',
                'contact'        => 'Contacts',
                'vendor'         => 'Vendors',
                'expense'        => 'Expenses',
                'invoice'        => 'Invoices',
                'journal'        => 'Journal Entries',
                'price_list'     => 'Price Lists',
                'payroll'        => 'Payroll',
            ];
            foreach (OraBooks_Csv_Imports::RESOURCE_TYPES as $type) {
                $resource_types[] = [
                    'id'    => $type,
                    'label' => $labels[$type] ?? $type,
                ];
            }
        }

        orabooks_json_success([
            'context' => $context,
            'stats' => $stats,
            'recent_imports' => $recent_imports,
            'imports' => $recent_imports,
            'is_platform_admin' => false,
            'org_id' => $org_id,
            'resource_types' => $resource_types,
            'limits' => class_exists('OraBooks_Csv_Imports') ? [
                'max_file_size' => OraBooks_Csv_Imports::MAX_FILE_SIZE,
                'max_rows' => OraBooks_Csv_Imports::MAX_ROWS,
                'confidence_threshold' => OraBooks_Csv_Imports::CONFIDENCE_THRESHOLD,
            ] : [],
            'timestamp' => current_time('mysql'),
        ]);
    }

    public function ajax_team_dashboard() {
        $context = $this->get_current_orabooks_context();
        if (is_wp_error($context)) {
            orabooks_json_error($context->get_error_message(), 401);
        }

        $org = $context['organization'];
        $org_id = $org ? (int) $org['id'] : 0;
        if (!$org_id) {
            orabooks_json_error('Organization is not set up yet.', 400);
        }

        global $wpdb;
        $membership = orabooks_user_belongs_to_org((int) $context['user_id'], $org_id);

        if (!$membership && !current_user_can('manage_options')) {
            orabooks_json_error('You are not a member of this organization', 403);
        }

        $stats = [
            'total_members' => 0,
            'pending_invites' => 0,
            'by_role' => [
                'owner' => 0,
                'admin' => 0,
                'approver' => 0,
                'staff' => 0,
                'viewer' => 0,
            ],
        ];
        $members = [];
        $pending_invites = [];

        if (class_exists('OraBooks_Team')) {
            $stats = OraBooks_Team::get_team_stats($org_id);
            $member_rows = OraBooks_Team::list_members($org_id);
            $members = array_map([OraBooks_Team::class, 'format_member'], $member_rows ?: []);

            if (OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'manage_employees')) {
                $invite_rows = OraBooks_Team::list_pending_invites($org_id);
                $pending_invites = array_map([OraBooks_Team::class, 'format_invite'], $invite_rows ?: []);
            }
        }

        $role_labels = [
            'owner' => 'Owner',
            'admin' => 'Admin',
            'approver' => 'Approver',
            'staff' => 'Staff',
            'viewer' => 'Viewer',
        ];

        $invite_roles = [];
        $member_roles = [];
        foreach ($role_labels as $id => $label) {
            $member_roles[] = ['id' => $id, 'label' => $label];
            if ($id !== 'owner') {
                $invite_roles[] = ['id' => $id, 'label' => $label];
            }
        }

        $effective_permissions = $context['permissions'] ?? [];
        $capabilities = [
            'invite_user' => in_array('manage_employees', $effective_permissions, true),
            'change_role' => in_array('manage_roles', $effective_permissions, true),
            'remove_user' => in_array('remove_user', $effective_permissions, true),
            'manage_employees' => in_array('manage_employees', $effective_permissions, true),
            'manage_roles' => in_array('manage_roles', $effective_permissions, true),
        ];

        orabooks_json_success([
            'context' => $context,
            'stats' => $stats,
            'members' => $members,
            'pending_invites' => $pending_invites,
            'invite_roles' => $invite_roles,
            'member_roles' => $member_roles,
            'capabilities' => $capabilities,
            'timestamp' => current_time('mysql'),
        ]);
    }

    public function ajax_attachments_dashboard() {
        $context = $this->get_current_orabooks_context();
        if (is_wp_error($context)) {
            orabooks_json_error($context->get_error_message(), 401);
        }

        $org = $context['organization'];
        $org_id = $org ? (int) $org['id'] : 0;
        if (!$org_id) {
            orabooks_json_error('Organization is not set up yet.', 400);
        }

        if (($org['organization_type'] ?? '') === 'partner') {
            orabooks_json_error('Partner accounts cannot perform accounting operations.', 403);
        }

        if (!OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'view_reports')) {
            orabooks_json_error('Permission denied', 403);
        }

        $stats = [
            'active_count'  => 0,
            'deleted_count' => 0,
            'total_bytes'   => 0,
        ];
        $attachments = [];
        $resource_types = [];

        if (class_exists('OraBooks_Attachments')) {
            $stats = OraBooks_Attachments::get_attachment_stats($org_id);
            $rows = OraBooks_Attachments::list_attachments($org_id, ['limit' => 25]);
            $attachments = array_map([OraBooks_Attachments::class, 'format_attachment_row'], $rows ?: []);

            $labels = [
                'invoice'          => 'Invoice',
                'bill'             => 'Bill',
                'expense'          => 'Expense',
                'voice_input'      => 'Voice Input',
                'csv_import'       => 'CSV Import',
                'user_profile'     => 'User Profile',
                'journal'          => 'Journal',
                'customer'         => 'Customer',
                'vendor'           => 'Vendor',
                'inventory_item'   => 'Inventory Item',
                'bank_account'     => 'Bank Account',
                'bank_transaction' => 'Bank Transaction',
                'export'           => 'Export',
                'general'          => 'General',
            ];
            foreach (OraBooks_Attachments::RESOURCE_TYPES as $type) {
                $resource_types[] = [
                    'id'    => $type,
                    'label' => $labels[$type] ?? ucwords(str_replace('_', ' ', $type)),
                ];
            }
        }

        orabooks_json_success([
            'context' => $context,
            'stats' => $stats,
            'attachments' => $attachments,
            'resource_types' => $resource_types,
            'capabilities' => class_exists('OraBooks_Attachments') ? [
                'upload'   => OraBooks_Attachments::can_upload($context['user_id'], $org_id),
                'download' => OraBooks_Attachments::can_download($context['user_id'], $org_id),
                'delete'   => OraBooks_Attachments::can_delete($context['user_id'], $org_id),
            ] : [],
            'limits' => class_exists('OraBooks_Attachments') ? [
                'max_file_size' => OraBooks_Attachments::MAX_FILE_SIZE,
            ] : [],
            'timestamp' => current_time('mysql'),
        ]);
    }

    public function ajax_approval_dashboard() {
        $context = $this->get_current_orabooks_context();
        if (is_wp_error($context)) {
            orabooks_json_error($context->get_error_message(), 401);
        }

        $org = $context['organization'];
        $org_id = $org ? (int) $org['id'] : 0;
        if (!$org_id) {
            orabooks_json_error('Organization is not set up yet.', 400);
        }

        if (($org['organization_type'] ?? '') === 'partner') {
            orabooks_json_error('Partner accounts cannot perform accounting operations.', 403);
        }

        if (!OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'view_reports')) {
            orabooks_json_error('Permission denied', 403);
        }

        $stats = [
            'pending_review' => 0,
            'approved_ready' => 0,
            'draft_count'    => 0,
            'posted_mtd'     => 0,
        ];
        $pending_review = [];
        $approved_ready = [];
        $draft_journals = [];
        $recent_history = [];

        if (class_exists('OraBooks_Posting')) {
            $stats = OraBooks_Posting::get_approval_stats($org_id);
            $sort = sanitize_text_field($_GET['sort'] ?? $_POST['sort'] ?? 'created_at');
            $order = sanitize_text_field($_GET['order'] ?? $_POST['order'] ?? 'ASC');
            if (class_exists('OraBooks_Approval')) {
                $pending_rows = OraBooks_Approval::get_pending_queue($org_id, $sort, $order);
            } else {
                $pending_rows = OraBooks_Posting::get_journals($org_id, ['status' => 'review_pending', 'limit' => 25]);
            }
            $approved_rows = OraBooks_Posting::get_journals($org_id, ['status' => 'approved', 'limit' => 25]);
            $draft_rows = OraBooks_Posting::get_journals($org_id, ['status' => 'draft', 'limit' => 15]);

            $pending_review = array_map([OraBooks_Posting::class, 'format_journal'], $pending_rows ?: []);
            $approved_ready = array_map([OraBooks_Posting::class, 'format_journal'], $approved_rows ?: []);
            $draft_journals = array_map([OraBooks_Posting::class, 'format_journal'], $draft_rows ?: []);

            global $wpdb;
            $history_table = OraBooks_Database::table('journal_approval_history');
            $journals_table = OraBooks_Database::table('journals');
            $history_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT h.* FROM {$history_table} h
                 JOIN {$journals_table} j ON j.id = h.journal_id
                 WHERE j.org_id = %d
                 ORDER BY h.created_at DESC
                 LIMIT 15",
                $org_id
            ));
            $recent_history = array_map([OraBooks_Posting::class, 'format_approval_history_row'], $history_rows ?: []);
        }

        orabooks_json_success([
            'context' => $context,
            'stats' => $stats,
            'pending_review' => $pending_review,
            'approved_ready' => $approved_ready,
            'draft_journals' => $draft_journals,
            'recent_history' => $recent_history,
            'capabilities' => [
                'submit'  => OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'submit_transaction'),
                'approve' => class_exists('OraBooks_Approval')
                    ? OraBooks_Approval::user_can_approve($context['user_id'], $org_id)
                    : OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'approve_journal'),
                'post'    => OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'submit_transaction'),
                'manage_policy' => OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'manage_org_settings'),
            ],
            'policy' => class_exists('OraBooks_Approval') ? OraBooks_Approval::get_policy($org_id) : null,
            'timestamp' => current_time('mysql'),
        ]);
    }

    public function ajax_ai_review_dashboard() {
        $context = $this->get_current_orabooks_context();
        if (is_wp_error($context)) {
            orabooks_json_error($context->get_error_message(), 401);
        }

        $org = $context['organization'];
        $org_id = $org ? (int) $org['id'] : 0;
        if (!$org_id) {
            orabooks_json_error('Organization is not set up yet.', 400);
        }

        if (($org['organization_type'] ?? '') === 'partner') {
            orabooks_json_error('Partner accounts cannot perform accounting operations.', 403);
        }

        if (!OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'view_ai_review_queue')) {
            orabooks_json_error('Permission denied', 403);
        }

        $stats = [
            'pending'    => 0,
            'processing' => 0,
            'escalated'  => 0,
            'resolved'   => 0,
            'total_open' => 0,
        ];
        $escalated = [];
        $pending = [];

        if (class_exists('OraBooks_Ai_Review')) {
            $stats = OraBooks_Ai_Review::get_queue_stats($org_id);
            $escalated_rows = OraBooks_Ai_Review::list_queue($org_id, ['statuses' => ['escalated'], 'limit' => 25]);
            $pending_rows = OraBooks_Ai_Review::list_queue($org_id, ['statuses' => ['pending', 'processing'], 'limit' => 15]);

            $escalated = array_map([OraBooks_Ai_Review::class, 'format_queue_item'], $escalated_rows ?: []);
            $pending = array_map([OraBooks_Ai_Review::class, 'format_queue_item'], $pending_rows ?: []);
        }

        orabooks_json_success([
            'context' => $context,
            'stats' => $stats,
            'escalated' => $escalated,
            'pending' => $pending,
            'threshold' => OraBooks_Ai_Review::CONFIDENCE_THRESHOLD,
            'capabilities' => [
                'review' => OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'approve_journal'),
            ],
            'timestamp' => current_time('mysql'),
        ]);
    }

    public function ajax_expenses_dashboard() {
        $context = $this->get_current_orabooks_context();
        if (is_wp_error($context)) {
            orabooks_json_error($context->get_error_message(), 401);
        }

        $org = $context['organization'];
        $org_id = $org ? (int) $org['id'] : 0;
        if (!$org_id) {
            orabooks_json_error('Organization is not set up yet.', 400);
        }

        if (($org['organization_type'] ?? '') === 'partner') {
            orabooks_json_error('Partner accounts cannot perform accounting operations.', 403);
        }

        if (!OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'view_expenses')) {
            orabooks_json_error('Permission denied', 403);
        }

        $stats = [
            'total'       => 0,
            'draft'       => 0,
            'submitted'   => 0,
            'ai_review'   => 0,
            'approved'    => 0,
            'posted'      => 0,
            'pending_ocr' => 0,
        ];
        $expenses = [];
        $pending_approval = [];

        if (class_exists('OraBooks_Expenses')) {
            $stats = OraBooks_Expenses::get_expense_stats($org_id);
            $rows = OraBooks_Expenses::list_expenses($org_id, ['limit' => 25]);
            $expenses = array_map([OraBooks_Expenses::class, 'format_expense'], $rows ?: []);

            $approval_rows = OraBooks_Expenses::list_expenses($org_id, ['status' => 'submitted', 'limit' => 15]);
            $ai_rows = OraBooks_Expenses::list_expenses($org_id, ['status' => 'ai_review', 'limit' => 15]);
            $pending_approval = array_map(
                [OraBooks_Expenses::class, 'format_expense'],
                array_merge($approval_rows ?: [], $ai_rows ?: [])
            );
        }

        orabooks_json_success([
            'context' => $context,
            'stats' => $stats,
            'expenses' => $expenses,
            'pending_approval' => $pending_approval,
            'threshold' => OraBooks_Expenses::CONFIDENCE_THRESHOLD,
            'capabilities' => [
                'upload'  => OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'manage_expenses'),
                'submit'  => OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'manage_expenses'),
                'approve' => OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'approve_expense'),
                'post'    => OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'approve_expense'),
                'override_tax' => class_exists('OraBooks_Tax')
                    ? OraBooks_Tax::user_can_override_tax($context['user_id'], $org_id)
                    : OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'override_tax'),
            ],
            'limits' => [
                'max_file_size' => OraBooks_Expenses::MAX_RECEIPT_SIZE,
            ],
            'timestamp' => current_time('mysql'),
        ]);
    }

    public function ajax_voice_dashboard() {
        $context = $this->get_current_orabooks_context();
        if (is_wp_error($context)) {
            orabooks_json_error($context->get_error_message(), 401);
        }

        $org = $context['organization'];
        $org_id = $org ? (int) $org['id'] : 0;
        if (!$org_id) {
            orabooks_json_error('Organization is not set up yet.', 400);
        }

        if (($org['organization_type'] ?? '') === 'partner') {
            orabooks_json_error('Partner accounts cannot perform accounting operations.', 403);
        }

        if (!OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'view_voice_inputs')) {
            orabooks_json_error('Permission denied', 403);
        }

        $stats = [
            'total'     => 0,
            'pending'   => 0,
            'processed' => 0,
            'failed'    => 0,
            'escalated' => 0,
        ];
        $voice_inputs = [];

        if (class_exists('OraBooks_Voice')) {
            $stats = OraBooks_Voice::get_voice_stats($org_id);
            $rows = OraBooks_Voice::list_voice_inputs($org_id, ['limit' => 25]);
            $voice_inputs = array_map([OraBooks_Voice::class, 'format_voice_input'], $rows ?: []);
        }

        orabooks_json_success([
            'context' => $context,
            'stats' => $stats,
            'voice_inputs' => $voice_inputs,
            'transaction_types' => OraBooks_Voice::TRANSACTION_TYPES,
            'threshold' => OraBooks_Voice::CONFIDENCE_THRESHOLD,
            'capabilities' => [
                'record'  => OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'manage_voice_inputs'),
                'confirm' => OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'manage_voice_inputs'),
            ],
            'limits' => [
                'max_file_size' => OraBooks_Voice::MAX_AUDIO_SIZE,
                'max_duration_seconds' => OraBooks_Voice::MAX_RECORDING_SECONDS,
                'retention_days' => OraBooks_Voice::RETENTION_DAYS,
            ],
            'timestamp' => current_time('mysql'),
        ]);
    }
    
    public function ajax_list_orgs() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $type = sanitize_text_field($_GET['type'] ?? '');
        $status = sanitize_text_field($_GET['status'] ?? '');
        
        $args = ['limit' => 100];
        if (!empty($type)) $args['type'] = $type;
        if (!empty($status)) $args['status'] = $status;
        
        $orgs = OraBooks_Organization::get_all($args);
        orabooks_json_success($orgs);
    }
    
    public function ajax_suspend_org() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $org_id = intval($_POST['org_id'] ?? 0);
        $result = OraBooks_Organization::suspend($org_id, get_current_user_id());
        
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success([], 'Organization suspended');
    }
    
    public function ajax_activate_org() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $org_id = intval($_POST['org_id'] ?? 0);
        $result = OraBooks_Organization::reactivate_customer($org_id, get_current_user_id());
        
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success([], 'Organization activated');
    }

    /**
     * SL-004 §5.6: Change enterprise customer data residency (super admin).
     */
    public function ajax_change_org_region() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }

        $org_id = intval($_POST['org_id'] ?? 0);
        $region = sanitize_text_field($_POST['region'] ?? '');
        $admin_id = function_exists('orabooks_get_current_user_id')
            ? orabooks_get_current_user_id()
            : get_current_user_id();

        $result = OraBooks_Organization::change_region($org_id, $region, (int) $admin_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success(['region' => strtolower(trim($region))], 'Organization region updated');
    }

    /**
     * SL-004 §5.2: Resolve organization by subdomain (active orgs only).
     */
    public function ajax_get_org_by_subdomain() {
        $subdomain = sanitize_text_field($_GET['subdomain'] ?? $_POST['subdomain'] ?? '');
        if ($subdomain === '') {
            orabooks_json_error('Subdomain required', 400);
        }

        if (!orabooks_check_rate_limit('org_subdomain_lookup_' . md5($subdomain), 60, 60)) {
            orabooks_json_error('Too many requests', 429);
        }

        $org = OraBooks_Organization::get_active_by_subdomain($subdomain);
        if (is_wp_error($org)) {
            $code = $org->get_error_code() === 'org_inactive' ? 403 : 404;
            orabooks_json_error($org->get_error_message(), $code);
        }

        orabooks_json_success([
            'id' => (int) $org->id,
            'name' => $org->name,
            'subdomain' => $org->subdomain,
            'tier' => $org->tier,
            'organization_type' => $org->organization_type,
            'region' => $org->region,
            'status' => $org->status,
        ]);
    }
    
    public function ajax_list_users() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        global $wpdb;
        $table = OraBooks_Database::table('users');
        $users = $wpdb->get_results("SELECT id, email, is_partner, is_email_verified, is_2fa_enabled, org_id, created_at FROM {$table} ORDER BY created_at DESC LIMIT 100");
        orabooks_json_success($users);
    }
    
    /**
     * AJAX: Get dashboard statistics with live counts
     */
    public function ajax_dashboard_stats() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        global $wpdb;
        
        $table_orgs = OraBooks_Database::table('organizations');
        $table_users = OraBooks_Database::table('users');
        $table_codes = OraBooks_Database::table('partner_codes');
        $table_attributions = OraBooks_Database::table('partner_attributions');
        $table_invoices = OraBooks_Database::table('invoices');
        
        // Total organizations
        $total_orgs = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_orgs}");
        
        // Organizations breakdown by type
        $customer_orgs = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_orgs} WHERE organization_type = %s", 'customer'
        ));
        $partner_orgs = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_orgs} WHERE organization_type = %s", 'partner'
        ));
        
        // Organizations by status
        $active_orgs = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_orgs} WHERE status = %s", 'active'
        ));
        $pending_orgs = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_orgs} WHERE status = %s", 'pending_setup'
        ));
        $suspended_orgs = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_orgs} WHERE status = %s", 'suspended'
        ));
        
        // Active partners (partner_codes with status='active')
        $active_partners = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_codes} WHERE status = %s", 'active'
        ));
        
        // Total partner codes by status
        $pending_partners = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_codes} WHERE status = %s", 'pending_review'
        ));
        $inactive_partners = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_codes} WHERE status = %s", 'inactive'
        ));
        $disabled_partners = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_codes} WHERE status = %s", 'disabled'
        ));
        
        // Total users
        $total_users = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_users}");
        
        // Users breakdown
        $partner_users = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_users} WHERE is_partner = %d", 1
        ));
        $customer_users = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_users} WHERE is_partner = %d", 0
        ));
        $verified_users = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_users} WHERE is_email_verified = %d", 1
        ));
        $twofa_enabled = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_users} WHERE is_2fa_enabled = %d", 1
        ));
        
        // Partner attributions
        $total_attributions = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_attributions}");
        $verified_attributions = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_attributions} WHERE status = %s", 'verified'
        ));
        $pending_attributions = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_attributions} WHERE status = %s", 'pending'
        ));
        $blocked_attributions = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_attributions} WHERE status = %s", 'blocked'
        ));
        
        // Invoice totals (if table exists)
        $total_invoices = null;
        $paid_invoices = null;
        $invoice_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_invoices}'");
        if ($invoice_table_exists) {
            $total_invoices = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_invoices}");
            $paid_invoices = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_invoices} WHERE payment_status = %s", 'paid'
            ));
        }
        
        // Recent activity — last 7 days counts
        $seven_days_ago = date('Y-m-d H:i:s', time() - 7 * 86400);
        $recent_orgs = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_orgs} WHERE created_at >= %s", $seven_days_ago
        ));
        $recent_users = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_users} WHERE created_at >= %s", $seven_days_ago
        ));
        $recent_attributions = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_attributions} WHERE attribution_date >= %s", $seven_days_ago
        ));
        
        $stats = [
            'organizations' => [
                'total'      => $total_orgs,
                'customer'   => $customer_orgs,
                'partner'    => $partner_orgs,
                'active'     => $active_orgs,
                'pending'    => $pending_orgs,
                'suspended'  => $suspended_orgs,
                'recent_7d'  => $recent_orgs,
            ],
            'partners' => [
                'active'   => $active_partners,
                'pending'  => $pending_partners,
                'inactive' => $inactive_partners,
                'disabled' => $disabled_partners,
            ],
            'users' => [
                'total'           => $total_users,
                'partner'         => $partner_users,
                'customer'        => $customer_users,
                'verified'        => $verified_users,
                '2fa_enabled'     => $twofa_enabled,
                'recent_7d'       => $recent_users,
            ],
            'attributions' => [
                'total'    => $total_attributions,
                'verified' => $verified_attributions,
                'pending'  => $pending_attributions,
                'blocked'  => $blocked_attributions,
                'recent_7d' => $recent_attributions,
            ],
            'invoices' => [
                'total' => $total_invoices,
                'paid'  => $paid_invoices,
            ],
            'event_bus_health' => class_exists('OraBooks_Event_Module') ? OraBooks_Event_Module::get_health() : null,
            'timestamp' => current_time('mysql'),
        ];
        
        orabooks_json_success($stats);
    }

    public function ajax_platform_settings_get() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }

        orabooks_json_success([
            'block_same_email_domain' => (bool) get_option('orabooks_block_same_email_domain', 0),
            'partner_commission_for_staff_viewer' => (bool) get_option('orabooks_partner_commission_for_staff_viewer', 0),
            'audit_retention_days' => (int) get_option('orabooks_audit_retention_days', 365),
            'jwt_expiry' => (int) get_option('orabooks_jwt_expiry', 900),
            'refresh_token_expiry' => (int) get_option('orabooks_refresh_token_expiry', 604800),
        ]);
    }

    public function ajax_platform_settings_save() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }

        $block_same_email_domain = !empty($_POST['block_same_email_domain']) ? 1 : 0;
        $partner_commission_for_staff_viewer = !empty($_POST['partner_commission_for_staff_viewer']) ? 1 : 0;
        $audit_retention_days = max(30, min(3650, intval($_POST['audit_retention_days'] ?? 365)));
        $jwt_expiry = max(60, min(86400, intval($_POST['jwt_expiry'] ?? 900)));
        $refresh_token_expiry = max(3600, min(2592000, intval($_POST['refresh_token_expiry'] ?? 604800)));

        update_option('orabooks_block_same_email_domain', $block_same_email_domain);
        update_option('orabooks_partner_commission_for_staff_viewer', $partner_commission_for_staff_viewer);
        update_option('orabooks_audit_retention_days', $audit_retention_days);
        update_option('orabooks_jwt_expiry', $jwt_expiry);
        update_option('orabooks_refresh_token_expiry', $refresh_token_expiry);

        orabooks_json_success([
            'block_same_email_domain' => (bool) $block_same_email_domain,
            'partner_commission_for_staff_viewer' => (bool) $partner_commission_for_staff_viewer,
            'audit_retention_days' => $audit_retention_days,
            'jwt_expiry' => $jwt_expiry,
            'refresh_token_expiry' => $refresh_token_expiry,
        ], 'Settings saved');
    }

    /**
     * Post-deploy verification checks (WP admin only).
     */
    public function ajax_deploy_checks() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }

        orabooks_json_success(OraBooks_DeployChecks::run());
    }

    /**
     * Repair missing MVP cron schedules and re-run deploy checks (WP admin only).
     */
    public function ajax_deploy_repair() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }

        $repaired = OraBooks_DeployChecks::ensure_mvp_cron_schedules();

        orabooks_json_success([
            'repaired' => $repaired,
            'checks' => OraBooks_DeployChecks::run(),
        ]);
    }
}