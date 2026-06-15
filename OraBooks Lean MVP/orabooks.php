<?php
/**
 * Plugin Name: OraBooks - Multi-Tenant Accounting & Partner Platform
 * Plugin URI: https://orabooks.app
 * Description: Complete multi-tenant accounting platform with partner/commission system, multi-org support, and tier-based access control.
 * Version: 1.0.0
 * Author: OraBooks Team
 * Text Domain: orabooks
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ORABOOKS_VERSION', '1.0.0');
define('ORABOOKS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ORABOOKS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ORABOOKS_DB_VERSION', '1.0.0');

// Include core files
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-database.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-auth.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-organization.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-rbac.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-team.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-audit.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-partner.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-secrets.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-coa.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-posting.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-ajax.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-shortcodes.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-commission.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-notifications.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-event-bus.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-async-queue.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-exports.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/helpers.php';

// Initialize plugin
add_action('plugins_loaded', 'orabooks_init');

function orabooks_init() {
    // Load text domain
    load_plugin_textdomain('orabooks', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Initialize core classes
    OraBooks_Database::init();
    OraBooks_Secrets::init();
    OraBooks_Organization::init();
    OraBooks_Auth::init();
    OraBooks_RBAC::init();
    OraBooks_Team::init();
    OraBooks_Audit::init();
    OraBooks_Partner::init();
    OraBooks_COA::init();
    OraBooks_Posting::init();
    OraBooks_Ajax::init();
    OraBooks_Shortcodes::init();
    OraBooks_Commission::init();
    OraBooks_Notifications::init();
    OraBooks_EventBus::init();
    OraBooks_EventBus::register_consumers();
    OraBooks_AsyncQueue::init();
    OraBooks_AsyncQueue::register_default_handlers();
    OraBooks_Exports::init();
    OraBooks_Exports::register_report_provider('coa', function($params) {
        // Reuse OraBooks_COA if available
        if (class_exists('OraBooks_COA') && method_exists('OraBooks_COA', 'get_accounts')) {
            $org_id = intval($params['org_id'] ?? 0);
            if ($org_id) {
                $accounts = OraBooks_COA::get_accounts($org_id);
                return is_array($accounts) ? $accounts : null;
            }
        }
        return null;
    });
    // Register the generate_export handler with SL-303 async queue
    // Register notification_log report provider for SL-114 export
    OraBooks_Exports::register_report_provider('notification_log', function($params) {
        if (class_exists('OraBooks_Notifications') && method_exists('OraBooks_Notifications', 'get_notifications')) {
            $user_id = intval($params['user_id'] ?? get_current_user_id());
            $org_id = intval($params['org_id'] ?? 0);
            $args = [];
            if (!empty($params['from_date'])) $args['from_date'] = $params['from_date'];
            if (!empty($params['to_date'])) $args['to_date'] = $params['to_date'];
            if (!empty($params['event_type'])) $args['event_type'] = $params['event_type'];
            if (!empty($params['priority'])) $args['priority'] = $params['priority'];
            if ($user_id) {
                return OraBooks_Notifications::get_notifications($user_id, $args);
            }
        }
        return null;
    });
    // Register commission_data report provider
    OraBooks_Exports::register_report_provider('commission_data', function($params) {
        if (class_exists('OraBooks_Commission') && method_exists('OraBooks_Commission', 'get_earned_commissions')) {
            $user_id = intval($params['user_id'] ?? get_current_user_id());
            $org_id = intval($params['org_id'] ?? 0);
            try {
                $data = OraBooks_Commission::get_earned_commissions($user_id, $org_id);
                return is_array($data) ? $data : null;
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    });
    // Register users_data report provider for SL-114 export
    OraBooks_Exports::register_report_provider('users_data', function($params) {
        global $wpdb;
        $table = OraBooks_Database::table('users');
        $users = $wpdb->get_results("SELECT id, email, is_active, is_email_verified, is_2fa_enabled, auth_provider, org_id, is_partner, created_at FROM {$table} ORDER BY created_at DESC LIMIT 1000");
        return $users ?: null;
    });
    // Register async_queue_data report provider
    OraBooks_Exports::register_report_provider('async_queue_data', function($params) {
        if (class_exists('OraBooks_AsyncQueue') && method_exists('OraBooks_AsyncQueue', 'get_queue_stats')) {
            try {
                $stats = OraBooks_AsyncQueue::get_queue_stats();
                return $stats;
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    });
    // Register the generate_export handler with SL-303 async queue
    OraBooks_AsyncQueue::register_handler('generate_export', ['OraBooks_Exports', 'generate_export_job']);
}

// Activation hook
register_activation_hook(__FILE__, 'orabooks_activate');
function orabooks_activate() {
    require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-database.php';
    OraBooks_Database::install();
    
    // Set default options
    add_option('orabooks_db_version', ORABOOKS_DB_VERSION);
    add_option('orabooks_block_same_email_domain', 0);
    add_option('orabooks_partner_commission_for_staff_viewer', 0);
    add_option('orabooks_audit_retention_days', 365);
    add_option('orabooks_jwt_secret', wp_generate_password(64, true, true));
    add_option('orabooks_jwt_expiry', 900); // 15 min
    add_option('orabooks_refresh_token_expiry', 604800); // 7 days
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'orabooks_deactivate');
function orabooks_deactivate() {
    // Cleanup if needed
    wp_clear_scheduled_hook('orabooks_daily_cleanup');
    wp_clear_scheduled_hook('orabooks_partner_activity_check');
    wp_clear_scheduled_hook('orabooks_monthly_commission_release');
    wp_clear_scheduled_hook('orabooks_monthly_payout_batch');
    wp_clear_scheduled_hook('orabooks_daily_commission_expiry');
    wp_clear_scheduled_hook('orabooks_daily_active_status_refresh');
    wp_clear_scheduled_hook('orabooks_notification_provider_health_update');
    wp_clear_scheduled_hook('orabooks_notification_sla_check');
    wp_clear_scheduled_hook('orabooks_notification_device_cleanup');
    wp_clear_scheduled_hook('orabooks_notification_delivery_retry');
    wp_clear_scheduled_hook('orabooks_eventbus_process_outbox');
    wp_clear_scheduled_hook('orabooks_eventbus_retry_deadletter');
    wp_clear_scheduled_hook('orabooks_eventbus_monitor');
    wp_clear_scheduled_hook('orabooks_async_queue_process');
    wp_clear_scheduled_hook('orabooks_async_queue_heartbeat');
    wp_clear_scheduled_hook('orabooks_async_queue_monitor');
    wp_clear_scheduled_hook('orabooks_exports_cleanup');
}

// Add custom cron schedule for every_minute
add_filter('cron_schedules', 'orabooks_cron_schedules');
function orabooks_cron_schedules($schedules) {
    $schedules['every_minute'] = [
        'interval' => 60,
        'display'  => __('Every Minute', 'orabooks'),
    ];
    $schedules['every_5_minutes'] = [
        'interval' => 300,
        'display'  => __('Every 5 Minutes', 'orabooks'),
    ];
    return $schedules;
}

// Admin menu
add_action('admin_menu', 'orabooks_admin_menu');
function orabooks_admin_menu() {
    add_menu_page(
        'OraBooks',
        'OraBooks',
        'manage_options',
        'orabooks',
        'orabooks_admin_dashboard',
        'dashicons-chart-area',
        30
    );
    
    add_submenu_page(
        'orabooks',
        'Organizations',
        'Organizations',
        'manage_options',
        'orabooks-orgs',
        'orabooks_admin_orgs'
    );
    
    add_submenu_page(
        'orabooks',
        'Users & Teams',
        'Users & Teams',
        'manage_options',
        'orabooks-users',
        'orabooks_admin_users'
    );
    
    add_submenu_page(
        'orabooks',
        'Audit Log',
        'Audit Log',
        'manage_options',
        'orabooks-audit',
        'orabooks_admin_audit'
    );
    
    add_submenu_page(
        'orabooks',
        'Settings',
        'Settings',
        'manage_options',
        'orabooks-settings',
        'orabooks_admin_settings'
    );
    
    // Partner Program page - visible to users with partner_commission_access
    add_submenu_page(
        'orabooks',
        'Partner Program',
        'Partner Program',
        'read',
        'orabooks-commissions',
        'orabooks_admin_commissions'
    );
    
    // Notification Center - visible to all logged-in users
    add_submenu_page(
        'orabooks',
        'Notifications',
        'Notifications',
        'read',
        'orabooks-notifications',
        'orabooks_admin_notifications'
    );
    
    // Async Queue Dashboard (admin only)
    add_submenu_page(
        'orabooks',
        'Job Queue',
        'Job Queue',
        'manage_options',
        'orabooks-job-queue',
        'orabooks_admin_job_queue'
    );

    // Chart of Accounts page (admin only)
    add_submenu_page(
        'orabooks',
        'Chart of Accounts',
        'Chart of Accounts',
        'manage_options',
        'orabooks-coa',
        'orabooks_admin_coa'
    );

    // My Exports page
    add_submenu_page(
        'orabooks',
        'My Exports',
        'My Exports',
        'read',
        'orabooks-exports',
        'orabooks_admin_exports'
    );
}

// Admin page render functions
function orabooks_admin_commissions() {
    echo do_shortcode('[orabooks_partner_dashboard]');
}
function orabooks_admin_notifications() {
    echo do_shortcode('[orabooks_notification_center]');
}
function orabooks_admin_job_queue() {
    echo do_shortcode('[orabooks_async_queue_dashboard]');
}
function orabooks_admin_exports() {
    echo do_shortcode('[orabooks_export_status]');
}
function orabooks_admin_coa() {
    include ORABOOKS_PLUGIN_DIR . 'admin/coa.php';
}
function orabooks_admin_dashboard() {
    include ORABOOKS_PLUGIN_DIR . 'admin/dashboard.php';
}

function orabooks_admin_orgs() {
    include ORABOOKS_PLUGIN_DIR . 'admin/organizations.php';
}

function orabooks_admin_users() {
    include ORABOOKS_PLUGIN_DIR . 'admin/users.php';
}

function orabooks_admin_audit() {
    include ORABOOKS_PLUGIN_DIR . 'admin/audit.php';
}

function orabooks_admin_settings() {
    include ORABOOKS_PLUGIN_DIR . 'admin/settings.php';
}

// Enqueue admin assets
add_action('admin_enqueue_scripts', 'orabooks_admin_enqueue');
function orabooks_admin_enqueue($hook) {
    if (strpos($hook, 'orabooks') === false) {
        return;
    }
    
    wp_enqueue_style('orabooks-admin', ORABOOKS_PLUGIN_URL . 'assets/css/admin.css', [], ORABOOKS_VERSION);
    wp_enqueue_script('orabooks-admin', ORABOOKS_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], ORABOOKS_VERSION, true);
    
    wp_localize_script('orabooks-admin', 'orabooks_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('orabooks_nonce')
    ]);
}

// Enqueue frontend assets
add_action('wp_enqueue_scripts', 'orabooks_frontend_enqueue');
function orabooks_frontend_enqueue() {
    wp_enqueue_style('orabooks-frontend', ORABOOKS_PLUGIN_URL . 'assets/css/frontend.css', [], ORABOOKS_VERSION);
    wp_enqueue_script('orabooks-frontend', ORABOOKS_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], ORABOOKS_VERSION, true);
    wp_localize_script('orabooks-frontend', 'orabooks_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('orabooks_nonce'),
        'current_user_id' => get_current_user_id()
    ]);
}