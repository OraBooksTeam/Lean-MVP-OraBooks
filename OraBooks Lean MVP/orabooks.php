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
}

// Admin page render functions
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
        'nonce' => wp_create_nonce('orabooks_nonce')
    ]);
}