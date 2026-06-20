<?php
/**
 * Plugin Name: OraBooks - Multi-Tenant Accounting & Partner Platform
 * Plugin URI: https://orabooks.app
 * Description: Lean MVP multi-tenant accounting and partner platform (SL-004 through SL-139).
 * Version: 1.0.0
 * Network: true
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
define('ORABOOKS_DB_VERSION', '1.0.1');

// Include core files
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-database.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-auth.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-organization.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-rbac.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-obn-access-control.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-team.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-audit.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-partner.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-secrets.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-ai-providers.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-coa.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-fiscal.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-tax.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-workflow.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-posting.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-approval.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-deploy-checks.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-ajax.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-shortcodes.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-assets.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-views.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-commission.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-notifications.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-event-bus.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/events/loader.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-async-queue.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/jobs/loader.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-exports.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-customers.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-vendors.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-inventory.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-bank-reconciliation.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-financial-reports.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-operational-reports.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-observability.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-csv-imports.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-attachments.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-ai-review.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-classification.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-expenses.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-voice.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-security.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-pwa.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-rest-api.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/helpers.php';

$orabooks_accounting_auth = ORABOOKS_PLUGIN_DIR . 'accounting/includes/class-obn-auth.php';
if (file_exists($orabooks_accounting_auth)) {
    require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-accounting-loader.php';
}

add_filter('script_loader_tag', ['OraBooks_Assets', 'filter_react_script_tag'], 10, 3);

// Initialize plugin
add_action('plugins_loaded', 'orabooks_init');

function orabooks_init() {
    // Load text domain
    load_plugin_textdomain('orabooks', false, dirname(plugin_basename(__FILE__)) . '/languages');

    orabooks_ensure_database();
    orabooks_ensure_mvp_cron_schedules();
    
    // Initialize core classes
    OraBooks_Database::init();
    OraBooks_Secrets::init();
    OraBooks_Organization::init();
    OraBooks_Auth::init();
    OraBooks_RBAC::init();
    OBN_Access_Control::init();
    OraBooks_Team::init();
    OraBooks_Audit::init();
    OraBooks_Partner::init();
    OraBooks_COA::init();
    OraBooks_Fiscal::init();
    OraBooks_Tax::init();
    OraBooks_Workflow::init();
    OraBooks_Posting::init();
    OraBooks_Approval::init();
    OraBooks_Ajax::init();
    OraBooks_Shortcodes::init();
    OraBooks_Commission::init();
    OraBooks_Notifications::init();
    OraBooks_EventBus::init();
    OraBooks_EventBus::register_consumers();
    OraBooks_Event_Module::init();
    OraBooks_Event_Module::schedule();
    OraBooks_AsyncQueue::init();
    OraBooks_AsyncQueue::register_default_handlers();
    OraBooks_Exports::init();
    OraBooks_Customers::init();
    OraBooks_Vendors::init();
    OraBooks_Inventory::init();
    OraBooks_Bank_Reconciliation::init();
    OraBooks_Financial_Reports::init();
    OraBooks_Operational_Reports::init();
    OraBooks_Observability::init();
    OraBooks_Csv_Imports::init();
    OraBooks_Attachments::init();
    OraBooks_Ai_Review::init();
    OraBooks_Classification::init();
    OraBooks_Expenses::init();
    OraBooks_Voice::init();
    OraBooks_Security::init();
    OraBooks_Pwa::init();
    OraBooks_Rest_Api::init();
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
    // Register partner_onboarding report provider (SL-139 / SL-114 export)
    OraBooks_Exports::register_report_provider('partner_onboarding', function($params) {
        $user_id = intval($params['user_id'] ?? get_current_user_id());
        if (!$user_id || !class_exists('OraBooks_Partner')) {
            return null;
        }
        $data = OraBooks_Partner::get_dashboard_data($user_id);
        return $data ? [$data] : null;
    });
    // Register the generate_export handler with SL-303 async queue
    OraBooks_AsyncQueue::register_handler('generate_export', ['OraBooks_Exports', 'generate_export_job']);
    OraBooks_AsyncQueue::register_handler('parse_csv_import', ['OraBooks_Csv_Imports', 'parse_csv_import_job']);

    if (class_exists('OraBooks_Accounting') && apply_filters('orabooks_enable_legacy_accounting', false)) {
        OraBooks_Accounting::init();
    }

    /**
     * Allow optional extensions to register additional features.
     */
    do_action('orabooks_register_addons');
}

/**
 * Create a WordPress page with shortcode content if it doesn't already exist
 */
function orabooks_create_page($slug, $title, $shortcode, $parent_slug = '') {
    $path = $parent_slug ? trim($parent_slug, '/') . '/' . $slug : $slug;
    $existing = get_page_by_path($path, OBJECT, 'page');
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
    $pages = orabooks_get_lean_mvp_page_definitions();
    
    // Create a parent page "OraBooks" if needed (optional)
    $created_ids = [];
    
    foreach ($pages as $slug => $config) {
        $page_id = orabooks_create_page($slug, $config[0], $config[1], $config[2] ?? '');
        $created_ids[$slug] = $page_id;
    }
    
    // Store created page IDs in options for reference
    update_option('orabooks_pages', $created_ids);
    
    return $created_ids;
}

/**
 * Ensure frontend pages exist on existing installs (idempotent).
 */
function orabooks_ensure_frontend_pages() {
    orabooks_create_required_pages();
}

add_action('init', 'orabooks_ensure_frontend_pages', 20);

// Activation hook
register_activation_hook(__FILE__, 'orabooks_activate');
function orabooks_activate($network_wide = false) {
    require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-database.php';

    orabooks_with_data_blog(function () {
        OraBooks_Database::install();
    });
    
    // Flush rewrite rules so OIDC routes (/orabooks-google-login, /orabooks-google-callback) work
    orabooks_oidc_rewrite_rules();
    flush_rewrite_rules();
    
    // Create required frontend pages with shortcodes
    orabooks_create_required_pages();

    if (class_exists('OraBooks_Accounting')) {
        OraBooks_Accounting::activate((bool) $network_wide);
    }
    
    // Set default options
    add_option('orabooks_db_version', ORABOOKS_DB_VERSION);
    add_option('orabooks_block_same_email_domain', 0);
    add_option('orabooks_partner_commission_for_staff_viewer', 0);
    add_option('orabooks_audit_retention_days', 365);
    add_option('orabooks_jwt_secret', wp_generate_password(64, true, true));
    add_option('orabooks_jwt_expiry', 900); // 15 min
    add_option('orabooks_refresh_token_expiry', 604800); // 7 days
}

/**
 * Ensure database tables exist (e.g. plugin uploaded without re-activation).
 */
function orabooks_ensure_database() {
    orabooks_with_data_blog(function () {
        global $wpdb;

        if (class_exists('OraBooks_Customers')) {
            OraBooks_Customers::ensure_schema();
        }

        $table_users = OraBooks_Database::table('users');
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_users));

        if ($table_exists !== $table_users || get_option('orabooks_db_version') !== ORABOOKS_DB_VERSION) {
            OraBooks_Database::install();
        }
    });
}

/**
 * Ensure MVP cron schedules exist after FTP upload (no re-activation required).
 */
function orabooks_ensure_mvp_cron_schedules() {
    if (class_exists('OraBooks_DeployChecks')) {
        OraBooks_DeployChecks::ensure_mvp_cron_schedules();
    }
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
    wp_clear_scheduled_hook('orabooks_events_process_outbox');
    wp_clear_scheduled_hook('orabooks_async_queue_process');
    wp_clear_scheduled_hook('orabooks_async_queue_heartbeat');
    wp_clear_scheduled_hook('orabooks_async_queue_monitor');
    wp_clear_scheduled_hook('orabooks_exports_cleanup');
    wp_clear_scheduled_hook('orabooks_daily_customer_status_check');
    wp_clear_scheduled_hook('orabooks_daily_invoice_overdue_check');
    wp_clear_scheduled_hook('orabooks_daily_overdue_digest');
    wp_clear_scheduled_hook('orabooks_daily_ap_aging_snapshot');
    wp_clear_scheduled_hook('orabooks_monthly_report_snapshot_archive');
    wp_clear_scheduled_hook('orabooks_daily_projection_integrity_check');
    wp_clear_scheduled_hook('orabooks_daily_low_stock_check');
    wp_clear_scheduled_hook('orabooks_observability_collect');
    wp_clear_scheduled_hook('orabooks_observability_evaluate');
    wp_clear_scheduled_hook('orabooks_observability_purge');
    wp_clear_scheduled_hook('orabooks_csv_imports_purge');
    wp_clear_scheduled_hook('orabooks_security_dependency_scan');
    wp_clear_scheduled_hook('orabooks_security_header_check');
    wp_clear_scheduled_hook('orabooks_security_audit_integrity');
    wp_clear_scheduled_hook('orabooks_security_secret_rotation_reminder');
    wp_clear_scheduled_hook('orabooks_security_purge');
    wp_clear_scheduled_hook('orabooks_monthly_fiscal_period_rollover');
    wp_clear_scheduled_hook('orabooks_daily_ledger_integrity_check');
    wp_clear_scheduled_hook('orabooks_monthly_balance_snapshot');
    wp_clear_scheduled_hook('orabooks_approval_expire_stale');
    wp_clear_scheduled_hook('orabooks_approval_escalate_overdue');
    wp_clear_scheduled_hook('orabooks_approval_expiry_reminders');
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
    $schedules['every_6_hours'] = [
        'interval' => 6 * HOUR_IN_SECONDS,
        'display'  => __('Every 6 Hours', 'orabooks'),
    ];
    $schedules['monthly'] = [
        'interval' => 30 * DAY_IN_SECONDS,
        'display'  => __('Monthly', 'orabooks'),
    ];
    return $schedules;
}

// Admin menu
add_action('admin_menu', 'orabooks_admin_menu');
function orabooks_admin_menu() {
    add_menu_page(
        'OraBooks',
        'OraBooks',
        'read',
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

    // Observability dashboard (admin only)
    add_submenu_page(
        'orabooks',
        'Observability',
        'Observability',
        'manage_options',
        'orabooks-observability',
        'orabooks_admin_observability'
    );

    add_submenu_page(
        'orabooks',
        'Event Dead Letters',
        'Event Dead Letters',
        'read',
        'orabooks-event-dead-letter',
        ['OraBooks_Event_Module', 'render_dead_letter_replay_page']
    );

    // Security dashboard (admin only)
    add_submenu_page(
        'orabooks',
        'Security',
        'Security',
        'manage_options',
        'orabooks-security',
        'orabooks_admin_security'
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

    // CSV Imports page
    add_submenu_page(
        'orabooks',
        'CSV Imports',
        'CSV Imports',
        'read',
        'orabooks-csv-imports',
        'orabooks_admin_csv_imports'
    );
}

/**
 * Nav items for React admin subnav (capability-filtered).
 */
function orabooks_user_can_see_partner_program() {
    if (current_user_can('manage_options')) {
        return true;
    }

    $user_id = orabooks_get_current_user_id();
    if (!$user_id) {
        return false;
    }

    global $wpdb;
    $table_users = OraBooks_Database::table('users');
    $user = $wpdb->get_row($wpdb->prepare(
        "SELECT is_partner, org_id FROM {$table_users} WHERE id = %d",
        $user_id
    ));

    if (!$user) {
        return false;
    }

    if ((bool) $user->is_partner) {
        return true;
    }

    $org_id = (int) $user->org_id;
    if (!$org_id) {
        $table_user_org = OraBooks_Database::table('user_org');
        $org_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT org_id FROM {$table_user_org} WHERE user_id = %d ORDER BY joined_at ASC LIMIT 1",
            $user_id
        ));
    }

    if ($org_id && class_exists('OraBooks_RBAC')) {
        return OraBooks_RBAC::has_partner_commission_access($user_id, $org_id);
    }

    return false;
}

function orabooks_get_admin_nav_items() {
    $items = [];

    if (current_user_can('manage_options')) {
        $items = array_merge($items, [
            ['slug' => 'orabooks', 'label' => __('Dashboard', 'orabooks'), 'route' => '/admin/dashboard'],
            ['slug' => 'orabooks-orgs', 'label' => __('Organizations', 'orabooks'), 'route' => '/admin/organizations'],
            ['slug' => 'orabooks-users', 'label' => __('Users', 'orabooks'), 'route' => '/admin/users'],
            ['slug' => 'orabooks-audit', 'label' => __('Audit', 'orabooks'), 'route' => '/admin/audit'],
            ['slug' => 'orabooks-partners', 'label' => __('Partners', 'orabooks'), 'route' => '/admin/partners'],
            ['slug' => 'orabooks-job-queue', 'label' => __('Job Queue', 'orabooks'), 'route' => '/admin/job-queue'],
            ['slug' => 'orabooks-observability', 'label' => __('Observability', 'orabooks'), 'route' => '/admin/observability'],
            ['slug' => 'orabooks-event-dead-letter', 'label' => __('Event Dead Letters', 'orabooks'), 'route' => '/admin/event-dead-letter'],
            ['slug' => 'orabooks-security', 'label' => __('Security', 'orabooks'), 'route' => '/admin/security'],
            ['slug' => 'orabooks-coa', 'label' => __('Chart of Accounts', 'orabooks'), 'route' => '/admin/coa'],
            ['slug' => 'orabooks-customers', 'label' => __('Customers', 'orabooks'), 'route' => '/admin/customers'],
            ['slug' => 'orabooks-settings', 'label' => __('Settings', 'orabooks'), 'route' => '/admin/settings'],
        ]);
    }

    if (current_user_can('read')) {
        $read_items = [
            ['slug' => 'orabooks-notifications', 'label' => __('Notifications', 'orabooks'), 'route' => '/admin/notifications'],
            ['slug' => 'orabooks-exports', 'label' => __('My Exports', 'orabooks'), 'route' => '/admin/exports'],
            ['slug' => 'orabooks-csv-imports', 'label' => __('CSV Imports', 'orabooks'), 'route' => '/admin/csv-imports'],
        ];

        if (orabooks_user_can_see_partner_program()) {
            $read_items = array_merge([
                ['slug' => 'orabooks-commissions', 'label' => __('Partner Program', 'orabooks'), 'route' => '/admin/commissions'],
            ], $read_items);
        }

        $items = array_merge($items, $read_items);
    }

    // De-dupe by slug (admin items first).
    $seen = [];
    $unique = [];
    foreach ($items as $item) {
        if (isset($seen[$item['slug']])) {
            continue;
        }
        $seen[$item['slug']] = true;
        $unique[] = $item;
    }

    return $unique;
}

add_action('admin_menu', 'orabooks_admin_menu_tweaks', 999);
function orabooks_admin_menu_tweaks() {
    global $submenu;
    if (isset($submenu['orabooks'][0][0])) {
        $submenu['orabooks'][0][0] = __('Dashboard', 'orabooks');
    }
}

add_filter('parent_file', 'orabooks_admin_parent_file');
function orabooks_admin_parent_file($parent_file) {
    global $plugin_page;
    if (!empty($plugin_page) && strpos($plugin_page, 'orabooks') !== false) {
        return 'orabooks';
    }
    return $parent_file;
}

add_filter('submenu_file', 'orabooks_admin_submenu_file');
function orabooks_admin_submenu_file($submenu_file) {
    global $plugin_page;
    if (!empty($plugin_page) && strpos($plugin_page, 'orabooks') !== false) {
        return $plugin_page;
    }
    return $submenu_file;
}

// Admin page render functions
function orabooks_admin_include($file, $vars = []) {
    $path = ORABOOKS_PLUGIN_DIR . 'admin/' . $file;
    if (!file_exists($path)) {
        echo '<div class="wrap"><h1>OraBooks</h1><p>' . esc_html__('Admin view not found.', 'orabooks') . '</p></div>';
        return;
    }

    if (!empty($vars)) {
        extract($vars, EXTR_SKIP);
    }

    include $path;
}

function orabooks_admin_react_page($route) {
    orabooks_admin_include('app.php', [
        'orabooks_admin_route' => $route,
    ]);
}

function orabooks_admin_commissions() {
    orabooks_admin_react_page('/admin/commissions');
}
function orabooks_admin_notifications() {
    orabooks_admin_react_page('/admin/notifications');
}
function orabooks_admin_job_queue() {
    orabooks_admin_react_page('/admin/job-queue');
}
function orabooks_admin_observability() {
    orabooks_admin_react_page('/admin/observability');
}
function orabooks_admin_security() {
    orabooks_admin_react_page('/admin/security');
}
function orabooks_admin_exports() {
    orabooks_admin_react_page('/admin/exports');
}
function orabooks_admin_csv_imports() {
    orabooks_admin_react_page('/admin/csv-imports');
}
function orabooks_admin_coa() {
    orabooks_admin_react_page('/admin/coa');
}

function orabooks_admin_customers() {
    orabooks_admin_react_page('/admin/customers');
}
function orabooks_admin_dashboard() {
    if (current_user_can('manage_options')) {
        orabooks_admin_react_page('/admin/dashboard');
    } elseif (orabooks_user_can_see_partner_program()) {
        orabooks_admin_react_page('/admin/commissions');
    } else {
        orabooks_admin_react_page('/admin/notifications');
    }
}

function orabooks_admin_partners() {
    orabooks_admin_react_page('/admin/partners');
}

function orabooks_admin_orgs() {
    orabooks_admin_react_page('/admin/organizations');
}

function orabooks_admin_users() {
    orabooks_admin_react_page('/admin/users');
}

function orabooks_admin_audit() {
    orabooks_admin_react_page('/admin/audit');
}

function orabooks_admin_settings() {
    orabooks_admin_react_page('/admin/settings');
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
        
        // Login successful — redirect to org workspace with auth cookies set
        $result = orabooks_enrich_login_response($result);
        orabooks_persist_login_session($result);

        $redirect = $result['redirect_to'] ?? orabooks_get_org_workspace_url(
            (int) ($result['org_id'] ?? 0),
            '/dashboard/'
        );

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
    wp_enqueue_style('orabooks-frontend', ORABOOKS_PLUGIN_URL . 'assets/css/frontend.css', [], ORABOOKS_VERSION);

    $ajax_config = OraBooks_Assets::get_ajax_config('admin');

    if (OraBooks_Assets::should_enqueue_admin_react($hook)) {
        OraBooks_Assets::enqueue_admin_react($ajax_config);
        return;
    }

    OraBooks_Assets::enqueue_legacy_admin_scripts($ajax_config);
}

// Enqueue frontend assets
add_action('wp_enqueue_scripts', 'orabooks_frontend_enqueue');
function orabooks_frontend_enqueue() {
    if (!is_singular('page')) {
        return;
    }

    $post = get_post();
    if (!$post || !orabooks_page_needs_frontend_assets($post)) {
        return;
    }

    wp_enqueue_style('orabooks-frontend', ORABOOKS_PLUGIN_URL . 'assets/css/frontend.css', [], ORABOOKS_VERSION);

    $ajax_config = OraBooks_Assets::get_ajax_config('frontend');

    if (OraBooks_Assets::should_enqueue_frontend_react($post->post_content, $post)) {
        OraBooks_Assets::enqueue_frontend_react($ajax_config);
    } else {
        OraBooks_Assets::enqueue_theme_compat();
        if (function_exists('orabooks_is_divi_theme') && orabooks_is_divi_theme()) {
            OraBooks_Assets::enqueue_divi_compat();
        }
    }

    if (OraBooks_Assets::should_enqueue_legacy_frontend($post->post_content)) {
        OraBooks_Assets::enqueue_legacy_frontend_scripts($ajax_config);
    }
}

add_action('wp_footer', ['OraBooks_Assets', 'print_late_frontend_styles'], 1);
add_action('wp_footer', ['OraBooks_Assets', 'maybe_enqueue_missed_frontend_assets'], 5);

// Add body classes on OraBooks frontend pages for full-width layout
add_filter('body_class', 'orabooks_body_class');
function orabooks_body_class($classes) {
    if (!is_singular('page')) {
        return $classes;
    }

    $post = get_post();
    if (!$post || !orabooks_page_needs_frontend_assets($post)) {
        return $classes;
    }

    $classes[] = 'orabooks-page';

    if (function_exists('orabooks_is_divi_theme') && orabooks_is_divi_theme()) {
        $classes[] = 'orabooks-divi-theme';
    }

    if (OraBooks_Assets::should_enqueue_frontend_react($post->post_content, $post)) {
        $classes[] = 'orabooks-react-page';
    }

    $auth_shortcodes = [
        'orabooks_login',
        'orabooks_register',
        'orabooks_tier_selection',
        'orabooks_reset_password',
        'orabooks_verify_email',
        'orabooks_accept_invite',
    ];
    foreach ($auth_shortcodes as $shortcode) {
        if (has_shortcode($post->post_content, $shortcode)) {
            $classes[] = 'orabooks-auth-page';
            break;
        }
    }

    return $classes;
}