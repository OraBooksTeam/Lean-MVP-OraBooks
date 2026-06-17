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
            add_action('wp_ajax_orabooks_list_users', [self::$instance, 'ajax_list_users']);
            add_action('wp_ajax_orabooks_dashboard_stats', [self::$instance, 'ajax_dashboard_stats']);
            add_action('wp_ajax_orabooks_frontend_context', [self::$instance, 'ajax_frontend_context']);
            add_action('wp_ajax_orabooks_customer_dashboard', [self::$instance, 'ajax_customer_dashboard']);
            
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

        $user_id = get_current_user_id();
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

        $org_id = (int) $user->org_id;
        if (!$org_id) {
            $org_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT org_id FROM {$table_user_org} WHERE user_id = %d ORDER BY joined_at ASC LIMIT 1",
                $user_id
            ));
        }

        $org = $org_id ? $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, tier, subdomain, region, status, organization_type, owner_id
             FROM {$table_orgs}
             WHERE id = %d",
            $org_id
        )) : null;

        $role = $org ? orabooks_get_user_role($user_id, (int) $org->id) : null;
        $permissions = [];
        if ($role && class_exists('OraBooks_RBAC')) {
            $permissions = OraBooks_RBAC::get_role_permissions($role);
        }

        return [
            'user_id' => $user_id,
            'user' => [
                'id' => (int) $user->id,
                'email' => $user->email,
                'is_partner' => (bool) $user->is_partner,
                'is_email_verified' => (bool) $user->is_email_verified,
                'is_2fa_enabled' => (bool) $user->is_2fa_enabled,
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
            ] : null,
            'role' => $role,
            'permissions' => $permissions,
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

        if (!current_user_can('manage_options') && !OraBooks_RBAC::require_permission($context['user_id'], $org_id, 'view_reports')) {
            orabooks_json_error('Permission denied', 403);
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

        orabooks_json_success([
            'context' => $context,
            'stats' => $stats,
            'accounts_summary' => $accounts_summary ?: [],
            'journal_statuses' => $journal_statuses ?: [],
            'recent_journals' => $recent_journals ?: [],
            'recent_invoices' => $recent_invoices ?: [],
            'recent_customers' => $recent_customers,
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
        $table_invoices = $wpdb->prefix . 'orabooks_invoices';
        
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
            'timestamp' => current_time('mysql'),
        ];
        
        orabooks_json_success($stats);
    }
}