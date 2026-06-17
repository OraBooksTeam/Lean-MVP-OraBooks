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
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-tax.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-posting.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-ajax.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-shortcodes.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-commission.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-notifications.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-event-bus.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-async-queue.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-exports.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-customers.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-vendors.php';
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
    OraBooks_Tax::init();
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
    OraBooks_Customers::init();
    OraBooks_Vendors::init();
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
    // Register commission_config report provider for SL-114 export
    OraBooks_Exports::register_report_provider('commission_config', function($params) {
        if (class_exists('OraBooks_Commission') && method_exists('OraBooks_Commission', 'get_config')) {
            try {
                $config = OraBooks_Commission::get_config();
                if (is_object($config)) {
                    $config = (array) $config;
                }
                return is_array($config) ? [$config] : null;
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    });
    // Register partner_onboarding report provider for SL-114 export
    OraBooks_Exports::register_report_provider('partner_onboarding', function($params) {
        global $wpdb;
        $user_id = intval($params['user_id'] ?? get_current_user_id());
        if (!$user_id) return null;
        $table = OraBooks_Database::table('partners');
        $partner = $wpdb->get_row($wpdb->prepare(
            "SELECT p.id, p.user_id, p.partner_code, p.partner_type, p.status, p.organization_name, 
                    p.active_customers, p.total_attributions, p.created_at, u.user_email
             FROM {$table} p
             LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id
             WHERE p.user_id = %d LIMIT 1",
            $user_id
        ));
        return $partner ? [$partner] : null;
    });
    // Register the generate_export handler with SL-303 async queue
    OraBooks_AsyncQueue::register_handler('generate_export', ['OraBooks_Exports', 'generate_export_job']);
}

/**
 * Create a WordPress page with shortcode content if it doesn't already exist
 */
function orabooks_create_page($slug, $title, $shortcode, $parent_slug = '') {
    $existing = get_page_by_path($slug, OBJECT, 'page');
    if ($existing) {
        return $existing->ID;
    }
    
    $page_data = [
        'post_title'    => $title,
        'post_content'  => '<!-- wp:shortcode -->' . $shortcode . '<!-- /wp:shortcode -->',
        'post_status'   => 'publish',
        'post_type'     => 'page',
        'post_name'     => $slug,
        'comment_status' => 'closed',
        'ping_status'   => 'closed',
    ];
    
    if (!empty($parent_slug)) {
        $parent = get_page_by_path($parent_slug, OBJECT, 'page');
        if ($parent) {
            $page_data['post_parent'] = $parent->ID;
        }
    }
    
    return wp_insert_post($page_data);
}

/**
 * Create all required OraBooks frontend pages on activation
 */
function orabooks_create_required_pages() {
    $pages = [
        // Main auth pages
        'login'               => ['Login', '[orabooks_login]'],
        'register'            => ['Register', '[orabooks_register]'],
        'verify-email'        => ['Verify Email', '[orabooks_verify_email]'],
        'reset-password'      => ['Reset Password', '[orabooks_reset_password]'],
        'tier-selection'      => ['Choose Your Plan', '[orabooks_tier_selection]'],
        
        // Partner pages
        'partner-onboarding'  => ['Partner Onboarding', '[orabooks_partner_onboarding]'],
        'partner-program'     => ['Partner Program', '[orabooks_partner_dashboard]'],
        
        // Dashboard
        'dashboard'           => ['Dashboard', '[orabooks_dashboard]'],
        
        // Notification pages
        'notifications'       => ['Notifications', '[orabooks_notification_center]'],
        'notification-preferences' => ['Notification Preferences', '[orabooks_notification_preferences]'],
        
        // Exports
        'my-exports'          => ['My Exports', '[orabooks_export_status]'],
    ];
    
    // Create a parent page "OraBooks" if needed (optional)
    $created_ids = [];
    
    foreach ($pages as $slug => $config) {
        $page_id = orabooks_create_page($slug, $config[0], $config[1]);
        $created_ids[$slug] = $page_id;
    }
    
    // Store created page IDs in options for reference
    update_option('orabooks_pages', $created_ids);
    
    return $created_ids;
}

// Activation hook
register_activation_hook(__FILE__, 'orabooks_activate');
function orabooks_activate() {
    require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-database.php';
    OraBooks_Database::install();
    
    // Flush rewrite rules so OIDC routes (/orabooks-google-login, /orabooks-google-callback) work
    orabooks_oidc_rewrite_rules();
    flush_rewrite_rules();
    
    // Create required frontend pages with shortcodes
    orabooks_create_required_pages();
    
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
    wp_clear_scheduled_hook('orabooks_daily_customer_status_check');
    wp_clear_scheduled_hook('orabooks_daily_invoice_overdue_check');
    wp_clear_scheduled_hook('orabooks_daily_overdue_digest');
    wp_clear_scheduled_hook('orabooks_daily_ap_aging_snapshot');
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
    
    // Partner Approvals page - admin only
    add_submenu_page(
        'orabooks',
        'Partner Approvals',
        'Partner Approvals',
        'manage_options',
        'orabooks-partners',
        'orabooks_admin_partners'
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

    // Customers & Invoices page (admin only)
    add_submenu_page(
        'orabooks',
        'Customers & Invoices',
        'Customers & Invoices',
        'manage_options',
        'orabooks-customers',
        'orabooks_admin_customers'
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

function orabooks_admin_customers() {
    include ORABOOKS_PLUGIN_DIR . 'admin/customers.php';
}
function orabooks_admin_dashboard() {
    include ORABOOKS_PLUGIN_DIR . 'admin/dashboard.php';
}

function orabooks_admin_partners() {
    include ORABOOKS_PLUGIN_DIR . 'admin/partners.php';
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

// ============================================
// SL-013: Google OIDC Rewrite Rules
// ============================================
add_action('init', 'orabooks_oidc_rewrite_rules');
function orabooks_oidc_rewrite_rules() {
    add_rewrite_tag('%orabooks_oidc%', '([^&]+)');
    add_rewrite_rule('^orabooks-google-login/?$', 'index.php?orabooks_oidc=initiate', 'top');
    add_rewrite_rule('^orabooks-google-callback/?$', 'index.php?orabooks_oidc=callback', 'top');
}

add_action('template_redirect', 'orabooks_oidc_route_handler');
function orabooks_oidc_route_handler() {
    $action = get_query_var('orabooks_oidc');
    if (!$action) {
        return;
    }
    
    if ($action === 'initiate') {
        // Redirect user to Google OAuth authorization URL
        $url = OraBooks_Auth::initiate_google_oauth();
        if (is_wp_error($url)) {
            wp_die($url->get_error_message());
        }
        wp_redirect($url);
        exit;
    }
    
    if ($action === 'callback') {
        $code = $_GET['code'] ?? '';
        $state = $_GET['state'] ?? '';
        
        if (empty($code) || empty($state)) {
            wp_die('Missing authorization code or state parameter.');
        }
        
        $result = OraBooks_Auth::handle_google_callback($code, $state);
        
        if (is_wp_error($result)) {
            if ($result->get_error_code() === 'oidc_email_conflict') {
                wp_die($result->get_error_message(), '', ['response' => 409]);
            }
            wp_die($result->get_error_message());
        }
        
        // Login successful — redirect to dashboard with token in URL fragment
        $redirect = home_url('/dashboard/');
        if (!empty($result['redirect_to'])) {
            $redirect = home_url(ltrim($result['redirect_to'], '/'));
        } elseif (!empty($result['needs_tier_selection'])) {
            $redirect = home_url('/tier-selection/');
        } elseif (!empty($result['org_id'])) {
            $org = OraBooks_Organization::get($result['org_id']);
            if ($org) {
                $redirect = 'https://' . $org->subdomain . '.orabooks.app/dashboard';
            }
        }
        
        // Store token in cookie for the frontend to pick up
        if (!empty($result['token'])) {
            setcookie('orabooks_token', $result['token'], time() + 900, '/', '', is_ssl(), true);
        }
        
        wp_redirect($redirect);
        exit;
    }
}

// Flush rewrite rules on activation
add_action('after_switch_theme', 'orabooks_flush_rewrites');
function orabooks_flush_rewrites() {
    orabooks_oidc_rewrite_rules();
    flush_rewrite_rules();
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

    $react_entry = ORABOOKS_PLUGIN_DIR . 'assets/react/frontend.js';
    if (file_exists($react_entry)) {
        foreach (glob(ORABOOKS_PLUGIN_DIR . 'assets/react/assets/*.css') ?: [] as $css_file) {
            $handle = 'orabooks-react-' . sanitize_key(basename($css_file, '.css'));
            wp_enqueue_style(
                $handle,
                ORABOOKS_PLUGIN_URL . 'assets/react/assets/' . basename($css_file),
                [],
                filemtime($css_file)
            );
        }

        wp_enqueue_script(
            'orabooks-react-frontend',
            ORABOOKS_PLUGIN_URL . 'assets/react/frontend.js',
            [],
            filemtime($react_entry),
            true
        );
        wp_script_add_data('orabooks-react-frontend', 'type', 'module');
        wp_localize_script('orabooks-react-frontend', 'orabooks_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('orabooks_nonce'),
            'current_user_id' => get_current_user_id()
        ]);
    }
}