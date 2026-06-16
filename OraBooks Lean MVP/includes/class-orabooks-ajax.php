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