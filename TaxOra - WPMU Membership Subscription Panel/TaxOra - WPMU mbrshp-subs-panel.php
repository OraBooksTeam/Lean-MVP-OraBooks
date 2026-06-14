<?php
/**
 * Plugin Name: OraBooks - WPMU Membership Subscription Panel
 * Plugin URI: https://www.enest.com.bd/plugins/wpmu-membership-subscription-panel
 * Description: Manage Membership Plans, Access Permission and other features across all multisite sites
 * Version: 1.0
 * Author: Engr. AnwarIT CASDP and Jahidul Islam
 * Author URI: https://www.anwarit.com
 * Creditse: TOB Developer Team
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpmu tob membership panel
 *
 * Minimum PHP: 8.0.30
 * Minimum WP: 6.8
 * @author 		Engr.AnwarIT- CASDP/TaxOra
 * @package		WPMU Membership Subscription Panel
 *
 * @copyright 	Copyright (c) 2025, TaxOra LLC USA
 * Orabooks Addon: true
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register custom plugin headers for the addon system
add_filter('extra_plugin_headers', function($headers) {
    $headers[] = 'Orabooks Addon';
    return $headers;
});

// Plugin Constants
define('TAXORA_MEMBERSHIP_VERSION', '2.0.0');
define('TAXORA_MEMBERSHIP_DIR', plugin_dir_path(__FILE__));
define('TAXORA_MEMBERSHIP_URL', plugin_dir_url(__FILE__));

// Bridge constant for wp-frontend-dashboard TaxOraIntegration
if (!defined('ORABOOKS_VERSION')) {
    define('ORABOOKS_VERSION', TAXORA_MEMBERSHIP_VERSION);
}
if (!defined('ORABOOKS_DIR')) {
    define('ORABOOKS_DIR', TAXORA_MEMBERSHIP_DIR);
}
if (!defined('ORABOOKS_URL')) {
    define('ORABOOKS_URL', TAXORA_MEMBERSHIP_URL);
}

// Color constants used by landing page and features CSS
if ( ! defined( 'ORABOOKS_PRIMARY_COLOR' ) ) {
    define( 'ORABOOKS_PRIMARY_COLOR', '#3b82f6' );
}
if ( ! defined( 'ORABOOKS_SECONDARY_COLOR' ) ) {
    define( 'ORABOOKS_SECONDARY_COLOR', '#2563eb' );
}

/**
 * Check for new OraBooks Membership Core System
 * If found, disable legacy functionality and redirect
 */
function taxora_membership_check_core_system() {
    // Check if new core system is active
    if (
        class_exists('OraBooks_Membership') &&
        method_exists('OraBooks_Membership', 'get_instance')
    ) {
        // New core system is active, disable legacy features
        add_action('admin_notices', 'taxora_membership_core_system_active_notice');
        return false;
    }
    
    // Check if core system files exist but not loaded
    $core_system_path = WP_PLUGIN_DIR . '/OraBooks-Membership/orabooks-membership.php';
    if (file_exists($core_system_path)) {
        add_action('admin_notices', 'taxora_membership_core_system_exists_notice');
        return false;
    }
    
    return true; // Legacy system should be active
}

function taxora_membership_core_system_exists_notice() {
    if (is_admin() && current_user_can('activate_plugins')) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong>OraBooks Core System Available:</strong> The OraBooks Membership Core System is installed but not activated. Please activate it for build guide compliance, mode-aware operations, and audit-ready features.</p>
            <p><a href="<?php echo admin_url('plugins.php'); ?>">Activate Core System</a></p>
        </div>
        <?php
    }
}

function taxora_membership_core_system_active_notice() {
    ?>
    <div class="notice notice-info is-dismissible">
        <p><strong>OraBooks Core System Active:</strong> The new OraBooks Membership Core System is active. This legacy plugin will remain disabled to prevent conflicts. You can safely deactivate this legacy plugin.</p>
    </div>
    <?php
}

// Only load legacy functionality if core system is not present
if (taxora_membership_check_core_system()) {
    // Include required files
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/database.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/cache-manager.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/error-logger.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-session.php';
    
    // Include core functionality
    // Removed monitoring.php - monitoring functionality (audit logging should be added to new core system when available)
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/subscription-manager.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/email-queue.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/dunning-manager.php';
    // Removed usage-tracker.php - usage tracking should be added to new core system when available
    // Restored features.php - needed for legacy plugin operation until new core system is available
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/features.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/subdomain-functions.php';
    // Include addon system for frontend dashboard integration
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/addon-system.php';

    // Include permission matrix and mode manager (build guide compliance)
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-permission-matrix.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-mode-manager.php';
    
    // Include rate limiter (SL-013: registration, login, subdomain check rate limits)
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-rate-limiter.php';
    
    // Include OIDC/Google login handler (SL-013 §5.5)
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-oidc.php';
    
    // Include JWT token management (SL-013: JWT 15min, refresh 7day, rotation)
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-jwt.php';
    
    // Include forgot/reset password with rate limiting (SL-013 §5.13)
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-password-reset.php';
    
    // Include RBAC (SL-003)
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-rbac.php';

    // Include ACL check endpoints (SL-003 middleware + AJAX endpoints)
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-acl-endpoints.php';

    // Include 2FA handler (SL-013 §5.8-5.10: TOTP, backup codes, challenge flow)
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-2fa-handler.php';

    // Include Commissions system (SL-068: partner commission tracking, lifecycle, payouts)
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-commissions.php';

    // Include build guide compliant core systems
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-audit-logger.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-chart-of-accounts.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-journal-entry.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-security.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-localization.php';
    
    // Include build guide compliant membership system
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-membership-levels.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-membership-permissions.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-subscription-plans.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-feature-access-manager.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-invoices.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-invoices-rest.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/admin/admin-menu.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/admin/admin-pages.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/admin/admin-ajax.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/admin/admin-mfa-verify.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/admin/wizard-handler.php';
    // Removed update-features-shortcode.php - one-time migration script, not needed in legacy plugin
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/frontend/shortcodes.php';
    
    // Include frontend functionality
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/frontend/frontend-ajax.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/frontend/frontend-functions.php';
    // Removed feature-pricing-redirect.php - feature access handled by new core system's permission matrix
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/frontend/client-dashboard-manager.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/frontend/frontend-cleaner.php';
    
    // Include login integration for /login page
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/frontend/login-integration.php';
    
    // Include clean signup styling
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/clean-signup-styling.php';
    
        // Removed client-homepage-features.php - filter is commented out, functionality handled by new core system
    // Removed subscriber-access-control.php - redundant with new core system's permission matrix
    // Removed feature-access-control.php - redundant with new core system's mode manager
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/new-site-setup.php';
     // signup-page-styling.php - not included; styling is in clean-signup-styling.php
    
    // Include additional legacy functionality
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/signup-validation.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/post-payment-handler.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/auto-login-site-setup.php';
    // Removed activation-page-diagnostic.php - debug tool no longer needed
    // Removed original-activation.php - workaround no longer needed
    // Removed remove-old-dashboard.php - one-time migration script, not needed in legacy plugin
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/hide-admin-bar.php';
    // Removed sample-addon-features.php - modules handle their own access control
    // Removed debug-features.php - debug file no longer needed
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/client-customization.php';
    
    // Include client and authentication functionality
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/client-landing-page.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/client-default-pages.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/frontend/client-homepage-redirect.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/login-widget-redirect-config.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/authentication.php';
    // Removed cleanup-pages.php - one-time cleanup script, not needed in legacy plugin
    // Removed accounting-permissions-integration.php - accounting module not present
    // Removed accounting-permissions-setup.php - accounting module not present
        // addon-system.php - included above at line 109
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/membership-options.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'payment-processing.php';
    // Restore output-buffer-helper.php - needed for orabooks_fix_output_compression_conflicts function during activation
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/output-buffer-helper.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-payment-gateway.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/payment-functions.php';
    
    // Load payment gateway implementations (depend on class-payment-gateway.php above)
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/payment-gateways/class-sslcommerz-gateway.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/payment-gateways/class-shurjopay-gateway.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/payment-gateways/class-stripe-gateway.php';
    require_once TAXORA_MEMBERSHIP_DIR . 'includes/payment-gateways/class-paypal-gateway.php';
}

// Disable legacy client dashboard manager if wp-frontend-dashboard is active
/* add_action('init', function() {
    if (class_exists('WPFD\Core\Plugin')) {
        // Remove the legacy login redirect filter
        remove_filter('login_redirect', 'orabooks_login_redirect_filter', 0);
        remove_filter('login_redirect', 'taxora_membership_legacy_login_redirect_filter', 999);
        // Remove legacy admin bar/access actions
        remove_action('admin_init', 'orabooks_block_admin_access');
        remove_action('login_form_login', 'orabooks_client_login_redirect', 5);
        remove_filter('login_url', 'orabooks_client_login_url', 10);
        remove_action('login_form_register', 'orabooks_client_registration_redirect');
        remove_action('before_signup_form', 'orabooks_client_registration_redirect');
        remove_filter('show_admin_bar', 'orabooks_hide_admin_bar_on_frontend', 999);
        remove_action('wp_logout', 'orabooks_client_logout_redirect');
    }
}, 0); */

// Initialize Client Dashboard Manager only when the modern frontend dashboard plugin is not active
add_action('plugins_loaded', function() {
    if (!class_exists('WPFD\Core\Plugin') && class_exists('Orabooks_Client_Dashboard_Manager')) {
        new Orabooks_Client_Dashboard_Manager();
    }
});

// activation/deactivation_hook
register_activation_hook( __FILE__, 'taxora_membership_legacy_activate' );
register_deactivation_hook(__FILE__, 'taxora_membership_legacy_network_deactivate');
register_uninstall_hook( __FILE__, 'taxora_membership_legacy_uninstall' );

// ===== ACTIVATION HOOK =====
/**
 * Legacy plugin activation function
 * Calls the main activation function from database.php
 */
function taxora_membership_legacy_activate($network_wide = false) {
    // Call the existing activation function
    if (function_exists('orabooks_activate')) {
        orabooks_activate($network_wide);
    }
}

// ===== NETWORK ACTIVATION HOOK =====
// Note: Network activation is handled by taxora_membership_legacy_activate which checks $network_wide parameter

// ===== NETWORK DEACTIVATION HOOK =====
function taxora_membership_legacy_network_deactivate($network_wide) {
    // Fix output compression conflicts
    taxora_membership_legacy_fix_output_compression_conflicts();
    
    try {
        if ($network_wide && is_multisite()) {
            $sites = get_sites(array('number' => 1000));
            
            foreach ($sites as $site) {
                switch_to_blog($site->blog_id);
                taxora_membership_legacy_cleanup_on_deactivation();
                restore_current_blog();
            }
        } else {
            taxora_membership_legacy_cleanup_on_deactivation();
        }
        
        flush_rewrite_rules();
    } finally {
        // Restore error handler
        taxora_membership_legacy_restore_error_handler();
    }
}

// ===== UNINSTALL HOOK =====
/**
 * Legacy plugin uninstall function
 */
function taxora_membership_legacy_uninstall() {
    // Call existing uninstall function if available
    if (function_exists('orabooks_uninstall')) {
        orabooks_uninstall();
    }
}

// Cleanup on deactivation
function taxora_membership_legacy_cleanup_on_deactivation() {
    // Remove menu from theme locations
    taxora_membership_legacy_remove_menu_from_locations();
    
    // Delete our menu
    taxora_membership_legacy_delete_primary_menu();
    
    // Clear any transients or options
    delete_option('taxora_membership_legacy_menu_created');
    delete_transient('taxora_membership_legacy_menu_exists');
}

// Remove menu from theme locations
function taxora_membership_legacy_remove_menu_from_locations() {
    $locations = get_theme_mod('nav_menu_locations');
    
    if ($locations) {
        $menu_name = 'OraBooks Primary Menu';
        $menu = wp_get_nav_menu_object($menu_name);
        
        if ($menu && !is_wp_error($menu)) {
            // Remove our menu from all locations
            foreach ($locations as $location => $menu_id) {
                if ($menu_id == $menu->term_id) {
                    $locations[$location] = 0;
                }
            }
            
            set_theme_mod('nav_menu_locations', $locations);
        }
    }
}

// Delete the primary menu
function taxora_membership_legacy_delete_primary_menu() {
    $menu_name = 'OraBooks Primary Menu';
    $menu = wp_get_nav_menu_object($menu_name);
    
    if ($menu && !is_wp_error($menu)) {
        wp_delete_nav_menu($menu->term_id);
    }
}

// Set table names early
add_action( 'plugins_loaded', 'taxora_membership_legacy_init_early' );
function taxora_membership_legacy_init_early() {
    taxora_membership_legacy_handle_multisite_tables();
    if (class_exists('OraBooks_Session')) {
        OraBooks_Session::init();
    }
}

// Initialize the plugin
add_action('plugins_loaded', 'taxora_membership_legacy_init');

function taxora_membership_legacy_init() {
    // Initialize subscription manager
    if (class_exists('OraBooks_Subscription_Manager')) {
        OraBooks_Subscription_Manager::get_instance();
    }
    
    // Initialize permission matrix and mode manager (build guide compliance)
    if (class_exists('OraBooks_Permission_Matrix')) {
        OraBooks_Permission_Matrix::init();
    }
    
    if (class_exists('OraBooks_Mode_Manager')) {
        OraBooks_Mode_Manager::init();
    }
}

// Database schema updates
add_action( 'plugins_loaded', 'taxora_membership_legacy_update_database_schema' );
add_action('init', 'taxora_membership_legacy_main_init');

function taxora_membership_legacy_main_init() {
    // Initialize session handler
    if (class_exists('OraBooks_Session')) {
        OraBooks_Session::init();
    }
    // Multi-site support
    if ( is_multisite() ) {
        taxora_membership_legacy_handle_multisite_tables();
    }
    
    // Load text domain
    load_plugin_textdomain( 'taxora-membership-legacy', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    
    // Settings form persistence clearing removed - not essential for core functionality
}

/**
 * Check if a feature is enabled for the current site/user
 * 
 * This function checks if a user has access to a specific feature
 * based on their membership level configuration.
 * 
 * @param string $feature_name Feature key to check
 * @return bool True if feature is enabled, false otherwise
 */
function taxora_membership_legacy_is_feature_enabled( $feature_name ) {
    // Check if feature is enabled at site level
    $site_features = get_option( 'taxora_membership_legacy_enabled_features', array() );
    
    if ( in_array( $feature_name, $site_features ) ) {
        return true;
    }
    
    // Check if feature is enabled for current user's membership level
    if ( is_user_logged_in() ) {
        $user_id = get_current_user_id();
        $user_features = get_user_meta( $user_id, 'taxora_membership_legacy_enabled_features', true );
        
        if ( is_array( $user_features ) && in_array( $feature_name, $user_features ) ) {
            return true;
        }
    }
    
    // Check feature assignments table (Addon System)
    if ( is_user_logged_in() && function_exists( 'taxora_membership_legacy_user_has_feature_access' ) ) {
        if ( taxora_membership_legacy_user_has_feature_access( get_current_user_id(), $feature_name ) ) {
                return true;
        }
    }

    return false;
}

// Enqueue assets - ONLY WHEN PLUGIN IS ACTIVE
add_action( 'admin_enqueue_scripts', 'taxora_membership_legacy_admin_assets' );
add_action( 'wp_enqueue_scripts', 'taxora_membership_legacy_frontend_assets', 20 );

function taxora_membership_legacy_admin_assets() {
    // Check if plugin is active
    if (!taxora_membership_legacy_is_plugin_active()) {
        return;
    }
    
    $screen = get_current_screen();
    if ( ! $screen || strpos( (string)$screen->base, 'taxora-membership-legacy' ) === false ) {
        return;
    }
    
    wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.0', true );
    $admin_css_file = TAXORA_MEMBERSHIP_DIR . 'assets/css/admin.css';
    $admin_js_file = TAXORA_MEMBERSHIP_DIR . 'assets/js/admin.js';
    $admin_css_ver = file_exists( $admin_css_file ) ? filemtime( $admin_css_file ) : TAXORA_MEMBERSHIP_VERSION;
    $admin_js_ver = file_exists( $admin_js_file ) ? filemtime( $admin_js_file ) : TAXORA_MEMBERSHIP_VERSION;
    // Admin CSS
    wp_enqueue_style( 'taxora-membership-legacy-admin', TAXORA_MEMBERSHIP_URL . 'assets/css/admin.css', array(), TAXORA_MEMBERSHIP_VERSION );
    
    // Gorgeous Dashboard CSS
    if (isset($_GET['page']) && $_GET['page'] === 'orabooks-membership') {
        wp_enqueue_style( 'taxora-membership-legacy-dashboard-gorgeous', TAXORA_MEMBERSHIP_URL . 'assets/css/admin-dashboard-gorgeous.css', array(), TAXORA_MEMBERSHIP_VERSION );
    }
    wp_enqueue_script( 'orabooks-admin-js', TAXORA_MEMBERSHIP_URL . 'assets/js/admin.js', array( 'jquery', 'chart-js' ), $admin_js_ver, true );
    // Removed Tailwind CDN - using regular CSS instead to avoid conflicts

    // Design system overrides (keeps plugin visuals modern, removes heavy background fills)
    $design_css_file = TAXORA_MEMBERSHIP_DIR . 'assets/css/design-system.css';
    $design_css_ver = file_exists( $design_css_file ) ? filemtime( $design_css_file ) : TAXORA_MEMBERSHIP_VERSION;
    wp_enqueue_style( 'orabooks-design-system', TAXORA_MEMBERSHIP_URL . 'assets/css/design-system.css', array(), $design_css_ver );
    
    wp_localize_script( 'orabooks-admin-js', 'taxoraMembershipLegacyAdmin', array( 
        'ajax_url' => admin_url( 'admin-ajax.php' ), 
        'nonce' => wp_create_nonce( 'taxora-membership-legacy-admin-nonce' ),
        'primary_color' => '#3b82f6',
        'secondary_color' => '#2563eb'
    ) );
}

function taxora_membership_legacy_frontend_assets() {
    // Check if plugin is active
    if (!taxora_membership_legacy_is_plugin_active() || is_admin()) {
        return;
    }
    
    // Prevent custom CSS on main site as requested (to avoid Divi layout conflicts)
    if (is_multisite() && get_current_blog_id() == 1) {
        return;
    }
    
    // Exclude activation page from loading frontend CSS
    global $pagenow;
    if ($pagenow === 'wp-activate.php' || strpos((string)$_SERVER['REQUEST_URI'], 'wp-activate.php') !== false) {
        return;
    }
    
    // Frontend CSS removed - all shortcodes now use Tailwind CSS/DaisyUI utility classes
    // injected directly in the HTML through src/styles.css build pipeline
    
    // Load built Tailwind+DaisyUI CSS if present
    $built_css = TAXORA_MEMBERSHIP_DIR . 'build/dist/orabooks-membership.css';
    if ( file_exists( $built_css ) ) {
        $built_ver = filemtime( $built_css );
        wp_enqueue_style( 'orabooks-frontend', TAXORA_MEMBERSHIP_URL . 'build/dist/orabooks-membership.css', array(), $built_ver );
    }
    
    $frontend_js_file = TAXORA_MEMBERSHIP_DIR . 'assets/js/frontend.js';
    $frontend_js_ver = file_exists( $frontend_js_file ) ? filemtime( $frontend_js_file ) : TAXORA_MEMBERSHIP_VERSION;
    wp_enqueue_script( 'orabooks-frontend-js', TAXORA_MEMBERSHIP_URL . 'assets/js/frontend.js', array( 'jquery' ), $frontend_js_ver, true );
    
    wp_localize_script( 'orabooks-frontend-js', 'orabooksFrontend', array( 
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'home_url' => home_url( '/' ),
        'nonce' => wp_create_nonce( 'orabooks_payment_nonce' )
    ) );
}

// Check if plugin is active - IMPROVED VERSION
function taxora_membership_legacy_is_plugin_active() {
    // If we're inside the plugin and this code is running, it's active!
    // But for external calls, we check the active plugins list.
    $plugin_basename = plugin_basename(__FILE__);
    
    // Check network activation if multisite
    if (is_multisite()) {
        $network_plugins = get_site_option('active_sitewide_plugins', array());
        if (isset($network_plugins[$plugin_basename])) {
            return true;
        }
    }
    
    // Check regular activation (works for single site and per-site activation in multisite)
    $active_plugins = get_option('active_plugins', array());
    if (is_array($active_plugins) && in_array($plugin_basename, $active_plugins)) {
        return true;
    }
    
    // Fallback: if TAXORA_MEMBERSHIP_VERSION is defined, we are definitely loaded
    return defined('TAXORA_MEMBERSHIP_VERSION');
}

// Utility functions
function taxora_membership_legacy_safe_redirect( $url ) {
    $url = esc_url_raw( $url );
    if ( ! headers_sent() ) {
        wp_safe_redirect( $url );
        exit;
    } else {
        echo '<script>window.location.replace("' . esc_js( $url ) . '");</script>';
        exit;
    }
}

function taxora_membership_legacy_get_page_by_title( $title ) {
    $slug = sanitize_title( $title );
    $p = get_page_by_path( $slug );
    if ( $p ) return $p;

    $q = new WP_Query( array( 'post_type' => 'page', 'title' => $title, 'posts_per_page' => 1 ) );
    if ( $q->have_posts() ) {
        $post = $q->posts[0];
        wp_reset_postdata();
        return $post;
    }
    return null;
}

// Prevent settings form from being processed on wrong pages
add_action('admin_init', 'taxora_membership_legacy_prevent_settings_leak');
function taxora_membership_legacy_prevent_settings_leak() {
    if (isset($_POST['taxora_membership_legacy_save_settings'])) {
        $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if ($current_page !== 'taxora-membership-legacy-settings') {
            unset($_POST['taxora_membership_legacy_save_settings']);
        }
    }
}

// Get or create menu pages with CORRECT SLUGS - ONLY WHEN ACTIVE
// Note: Page creation is handled by external plugin
function taxora_membership_legacy_get_or_create_menu_pages() {
    // Only create pages if plugin is active
    if (!taxora_membership_legacy_is_plugin_active()) {
        return array();
    }
    
    return array(); // Return empty array since pages are handled externally
}

// Hook for other plugins to extend functionality - ONLY WHEN ACTIVE
add_action( 'wp_loaded', 'taxora_membership_legacy_load_integrations' );
function taxora_membership_legacy_load_integrations() {
    if (taxora_membership_legacy_is_plugin_active()) {
        do_action( 'taxora_membership_legacy_plugin_loaded' );
        do_action('taxora_membership_legacy_accounting_integration_loaded');
    }
}

// Stub functions for helper functions defined in included files
// These maintain backward compatibility while using new naming convention
if (!function_exists('taxora_membership_legacy_fix_output_compression_conflicts')) {
    function taxora_membership_legacy_fix_output_compression_conflicts() {
        if (function_exists('orabooks_fix_output_compression_conflicts')) {
            return orabooks_fix_output_compression_conflicts();
        }
    }
}

if (!function_exists('taxora_membership_legacy_create_tables')) {
    function taxora_membership_legacy_create_tables() {
        if (function_exists('orabooks_create_tables')) {
            return orabooks_create_tables();
        }
    }
}

if (!function_exists('taxora_membership_legacy_restore_error_handler')) {
    function taxora_membership_legacy_restore_error_handler() {
        if (function_exists('orabooks_restore_error_handler')) {
            return orabooks_restore_error_handler();
        }
    }
}

if (!function_exists('taxora_membership_legacy_handle_multisite_tables')) {
    function taxora_membership_legacy_handle_multisite_tables() {
        if (function_exists('orabooks_handle_multisite_tables')) {
            return orabooks_handle_multisite_tables();
        }
    }
}

if (!function_exists('taxora_membership_legacy_update_database_schema')) {
    function taxora_membership_legacy_update_database_schema() {
        if (function_exists('orabooks_update_database_schema')) {
            return orabooks_update_database_schema();
        }
    }
}

if (!function_exists('taxora_membership_legacy_user_has_feature_access')) {
    function taxora_membership_legacy_user_has_feature_access($user_id, $feature_name) {
        if (function_exists('orabooks_user_has_feature_access')) {
            return orabooks_user_has_feature_access($user_id, $feature_name);
        }
        return false;
    }
}
