<?php
/**
 * Plugin Name: OraBooks - WPMU Frontend Basic Inventory
 * Plugin URI: https://www.enest.com.bd/plugins/wpmu-tob-febinv
 * Description: A Basic Inventory Management System for Orabooks Membership System. Access Limited to Users with the 'Inventory' Feature Enabled.
 * Version: 1/25
 * Author: Engr. AnwarIT CASDP and Farid Ahmed
 * Author URI: https://www.anwarit.com
 * Creditse: TOB Developer Team
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpmu tob febinv
 *
 * Minimum PHP: 8.0.30
 * Minimum WP: 6.8
 * @author 		Engr.AnwarIT- CASDP/TaxOra
 * @package		WPMU Default Logo
 *
 * @copyright 	Copyright (c) 2025, TaxOra LLC USA
 * Orabooks Addon: true
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fix for PHP Notice: ob_end_flush(): failed to send buffer of zlib output compression (0)
 * This occurs when zlib compression is enabled and WordPress tries to flush all buffers at shutdown.
 */
if (!has_action('shutdown', 'wp_ob_end_flush_all_safe')) {
    remove_action('shutdown', 'wp_ob_end_flush_all', 1);
    add_action('shutdown', 'wp_ob_end_flush_all_safe', 1);
}

if (!function_exists('wp_ob_end_flush_all_safe')) {
    function wp_ob_end_flush_all_safe()
    {
        $levels = ob_get_level();
        for ($i = 0; $i < $levels; $i++) {
            if (ob_get_length() > 0) {
                @ob_end_flush();
            } else {
                @ob_end_clean();
            }
        }
    }
}

// Define Plugin Constants
define('FRONTEND_INVENTORY_VERSION', '1.0.5');
define('FRONTEND_INVENTORY_PATH', plugin_dir_path(__FILE__));
define('FRONTEND_INVENTORY_URL', plugin_dir_url(__FILE__));
define('FRONTEND_INVENTORY_TEMPLATE_PATH', FRONTEND_INVENTORY_PATH . 'templates/');

// Set active flag early if on inventory page to ensure isolation
if (
    !defined('ORABOOKS_INVENTORY_ACTIVE') &&
    ((isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/inventory') !== false) ||
        (isset($_GET['page']) && strpos($_GET['page'], 'inventory') !== false) ||
        (isset($_GET['view']) && !empty($_GET['view'])))
) {
    define('ORABOOKS_INVENTORY_ACTIVE', true);
}

/**
 * Check if the current user can access the inventory system
 * 
 * @return bool
 */
function orabooks_can_access_inventory()
{
    if (!is_user_logged_in()) {
        return false;
    }

    // Administrators always have access
    if (current_user_can('manage_options')) {
        return true;
    }

    // Check custom permissions table
    global $wpdb;
    $table_permissions = $wpdb->prefix . 'orabooks_user_permissions';

    // Check if table exists first to avoid errors during initial setup
    if (!empty($wpdb->get_var("SHOW TABLES LIKE '$table_permissions'"))) {
        $has_permissions = $wpdb->get_var($wpdb->prepare("SELECT count(*) FROM $table_permissions WHERE user_id = %d", get_current_user_id()));
        if ($has_permissions > 0) {
            return true;
        }
    }

    return false;
}

/**
 * Register as Orabooks Addon
 */
function obn_register_inventory_addon()
{
    if (function_exists('orabooks_register_addon')) {
        orabooks_register_addon(array(
            'id' => 'inventory',
            'name' => 'Frontend Inventory',
            'description' => 'Complete inventory management system for frontend users.',
            'version' => FRONTEND_INVENTORY_VERSION,
            'plugin_file' => __FILE__,
            'author' => 'ExtremeNest',
            'features' => array(
                'inventory' => array(
                    'name' => 'Inventory System',
                    'description' => 'Access to complete inventory management module (Items, Stocks, Reports).',
                    'icon' => '📦',
                    'category' => 'business',
                    'subdomain_path' => '/inventory'
                )
            )
        ));
    }
}
add_action('orabooks_register_addons', 'obn_register_inventory_addon');

//Dashboard Shortcode [orabooks_inventory]

// Include Classes
require_once FRONTEND_INVENTORY_PATH . 'includes/class-db-manager.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-sidebar.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-dashboard.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-backup.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-items.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-brands.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-categories.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-units.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-variants.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-print-labels.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-contacts.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-sales.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-purchases.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-purchasereturn.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-salesreturn.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-stock.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-reports.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-taxes.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-permissions.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-roles.php';
require_once FRONTEND_INVENTORY_PATH . 'includes/class-employees.php';

// Temporary: Force table creation for development - Commented out to prevent "headers already sent"
// add_action('init', ['Frontend_Inventory_DB_Manager', 'create_table']);

/**
 * Check for OraBooks Membership Plugin
 */

function frontend_inventory_check_dependency() {
    // 1. Check for common OraBooks functions/classes (Best)
    if ( function_exists( 'orabooks_register_addon' ) || 
         function_exists( 'orabooks_is_feature_enabled' ) ||
         class_exists( 'OraBooks' ) || 
         defined( 'ORABOOKS_VERSION' ) ) {
        return true;
    }

    // 2. Fallback check for active plugins list
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $active_plugins = (array) get_option( 'active_plugins', array() );
    if ( is_multisite() ) {
        $network_active = (array) get_site_option( 'active_sitewide_plugins', array() );
        $active_plugins = array_merge( $active_plugins, array_keys( $network_active ) );
    }

    foreach ( $active_plugins as $plugin_path ) {
        // Look for membership plugin specifically
        if ( stripos( $plugin_path, 'orabooks' ) !== false && stripos( $plugin_path, 'membership' ) !== false ) {
            return true;
        }
        // Broad search for any "OraBooks" core plugin if membership is integrated
        if ( stripos( $plugin_path, 'orabooks-membership' ) !== false ) {
            return true;
        }
    }

    return false;
}

// Activation Hook
register_activation_hook(__FILE__, 'frontend_inventory_activate');

function frontend_inventory_activate()
{
    if ( ! frontend_inventory_check_dependency() ) {
        // Removed wp_die to allow redirect and show notice on plugins page
        return;
    }

    // Create tables
    require_once FRONTEND_INVENTORY_PATH . 'includes/class-db-manager.php';
    Frontend_Inventory_DB_Manager::create_table();
}

/**
 * Dependency Check on Admin Init
 */

function frontend_inventory_dependency_check() {
    if ( ! frontend_inventory_check_dependency() ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        add_action( 'admin_notices', 'frontend_inventory_dependency_notice' );
        add_action( 'network_admin_notices', 'frontend_inventory_dependency_notice' );
        if ( isset( $_GET['activate'] ) ) unset( $_GET['activate'] );
    }
}
add_action( 'admin_init', 'frontend_inventory_dependency_check' );

function frontend_inventory_dependency_notice() {
    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php _e( 'OraBooks - WPMU Frontend Basic Inventory requires the TaxOra - WPMU Membership plugin to be installed and activated.', 'wpmu-tob-febinv' ); ?></p>
    </div>
    <?php
}

// Initialize Plugin
function frontend_inventory_init()
{
    if ( ! frontend_inventory_check_dependency() ) {
        return;
    }
    // Ensure sidebar table and inventory data exist
    global $wpdb;
    $table_sidebar = $wpdb->prefix . 'orabooks_db_sidebar';
    $table_permissions = $wpdb->prefix . 'orabooks_user_permissions';

    $sidebar_exists = !empty($wpdb->get_var("SHOW TABLES LIKE '$table_sidebar'"));
    $permissions_exists = !empty($wpdb->get_var("SHOW TABLES LIKE '$table_permissions'"));

    // Check for specific recent additions to ensure they are added to existing installations
    $adjustment_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s", 'add-adjustment')) : 0;
    $transfer_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s", 'add-transfer')) : 0;
    $sales_return_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s", 'edit-sales-return')) : 0;
    $sales_order_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s", 'sales-order-list')) : 0;
    $sales_pending_delivery_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s", 'sales-pending-delivery')) : 0;
    $sales_return_list_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s", 'sales-return-list')) : 0;
    $purchase_ordered_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s", 'purchase-ordered-list')) : 0;
    $purchase_pending_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s", 'purchase-pending-list')) : 0;
    $purchase_return_list_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s", 'purchase-return-list')) : 0;
    $import_cust_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s", 'import-customers')) : 0;
    $backup_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s", 'db-backup')) : 0;
    $inventory_data_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE module = %s", 'inventory')) : 0;
    $employee_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s", 'employee')) : 0;
    $customer_pay_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s", 'customer-pay')) : 0;
    $supplier_pay_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s", 'supplier-pay')) : 0;
    $journal_report_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s", 'journal-report')) : 0;
    $user_permissions_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s", 'user-permissions')) : 0;
    $add_service_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s", 'add-service')) : 0;
    $variants_list_exists = $sidebar_exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_sidebar WHERE menu_slug = %s", 'variants-list')) : 0;
    $permissions_data_exists = $permissions_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_permissions") : 0;

    if (!$sidebar_exists || !$inventory_data_exists || !$permissions_exists || !$adjustment_exists || !$transfer_exists || !$sales_return_exists || !$employee_exists || !$permissions_data_exists || !$sales_order_exists || !$sales_pending_delivery_exists || !$sales_return_list_exists || !$purchase_ordered_exists || !$purchase_pending_exists || !$purchase_return_list_exists || !$import_cust_exists || !$backup_exists || !$customer_pay_exists || !$supplier_pay_exists || !$journal_report_exists || !$user_permissions_exists || !$add_service_exists || !$variants_list_exists) {
        if (class_exists('Frontend_Inventory_DB_Manager')) {
            Frontend_Inventory_DB_Manager::create_table();
        }
    }

    new Frontend_Inventory_Dashboard();

    // Initialize AJAX handlers and other logic
    if (class_exists('Frontend_Inventory_Items'))
        Frontend_Inventory_Items::init();
    if (class_exists('Frontend_Inventory_Brands'))
        Frontend_Inventory_Brands::init();
    if (class_exists('Frontend_Inventory_Categories'))
        Frontend_Inventory_Categories::init();
    if (class_exists('Frontend_Inventory_Units'))
        Frontend_Inventory_Units::init();
    if (class_exists('Frontend_Inventory_Variants'))
        Frontend_Inventory_Variants::init();
    if (class_exists('Frontend_Inventory_Print_Labels'))
        Frontend_Inventory_Print_Labels::init();
    if (class_exists('Frontend_Inventory_Contacts'))
        Frontend_Inventory_Contacts::init();
    if (class_exists('Frontend_Inventory_Sales'))
        new Frontend_Inventory_Sales();
    if (class_exists('Frontend_Inventory_Purchases'))
        new Frontend_Inventory_Purchases();
    if (class_exists('Frontend_Inventory_PurchaseReturn'))
        new Frontend_Inventory_PurchaseReturn();
    if (class_exists('Frontend_Inventory_SalesReturn'))
        new Frontend_Inventory_SalesReturn();
    if (class_exists('Frontend_Inventory_Stock'))
        new Frontend_Inventory_Stock();
    if (class_exists('Frontend_Inventory_Reports'))
        new Frontend_Inventory_Reports();
    if (class_exists('Frontend_Inventory_Taxes'))
        Frontend_Inventory_Taxes::init();
    if (class_exists('Frontend_Inventory_Backup'))
        new Frontend_Inventory_Backup();
    if (class_exists('Frontend_Inventory_Permissions'))
        Frontend_Inventory_Permissions::init();
    if (class_exists('Frontend_Inventory_Roles'))
        Frontend_Inventory_Roles::init();
    if (class_exists('Frontend_Inventory_Employees'))
        Frontend_Inventory_Employees::init();
}
add_action('plugins_loaded', 'frontend_inventory_init');

// Enqueue Scripts
function frontend_inventory_scripts()
{
    // Check if current page is the inventory dashboard
    // or if the URL contains 'inventory' 
    // or if the shortcode is present
    $is_inventory_page = (defined('ORABOOKS_INVENTORY_ACTIVE') && ORABOOKS_INVENTORY_ACTIVE) ||
        (isset($post->post_content) && has_shortcode($post->post_content, 'orabooks_inventory')) ||
        (isset($post->post_name) && (strpos($post->post_name, 'inventory') !== false)) ||
        (strpos($_SERVER['REQUEST_URI'], '/inventory') !== false);

    if (!$is_inventory_page)
        return;
    // Strictly separate from accounting to avoid conflicts

    // SweetAlert2
    wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], '11.0.0', false);

    // FontAwesome 6
    wp_enqueue_style('font-awesome-6', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css');

    // Tailwind CSS (Compiled locally)
    wp_enqueue_style('frontend-inventory-tailwind', FRONTEND_INVENTORY_URL . 'assets/css/tailwind.css', [], FRONTEND_INVENTORY_VERSION . '.' . time());

    // Select2
    wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', false);

    // jQuery UI Autocomplete (Built-in WP)
    wp_enqueue_script('jquery-ui-autocomplete');
    wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css');

    // Register and Enqueue Dedicated Inventory Script with timestamp for cache busting
    wp_enqueue_script('frontend-inventory-dashboard-js', FRONTEND_INVENTORY_URL . 'assets/js/inventory-dashboard.js', ['jquery'], FRONTEND_INVENTORY_VERSION . '.' . time(), false);

    // Add same to bootstrap to ensure it's not cached
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css', [], '5.3.2');
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js', ['jquery'], '5.3.2', false);

    // Enqueue Responsive CSS for full mobile, tablet, and desktop support
    wp_enqueue_style('frontend-inventory-responsive', FRONTEND_INVENTORY_URL . 'assets/css/responsive.css', ['bootstrap-css'], FRONTEND_INVENTORY_VERSION . '.' . time());
    wp_enqueue_style('frontend-inventory-brand-theme', FRONTEND_INVENTORY_URL . 'assets/css/brand-theme.css', ['frontend-inventory-responsive'], FRONTEND_INVENTORY_VERSION . '.' . time());

    wp_localize_script('frontend-inventory-dashboard-js', 'frontend_inventory_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('frontend_ajax_nonce')
    ]);
}
add_action('wp_enqueue_scripts', 'frontend_inventory_scripts', 5);
