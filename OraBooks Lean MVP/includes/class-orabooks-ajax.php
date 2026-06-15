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
}