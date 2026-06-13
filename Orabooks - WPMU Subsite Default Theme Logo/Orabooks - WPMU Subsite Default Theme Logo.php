<?php
/**
 * Plugin Name: OraBooks - WPMU Global Menu Logo
 * Plugin URI: https://www.enest.com.bd/plugins/wpmu-tob-glbmenulogo
 * Description: Add global menu items, logo, and theme management across all multisite sites
 * Version: 1.0
 * Author: Engr. AnwarIT CASDP and Jahidul Islam
 * Author URI: https://www.anwarit.com
 * Creditse: TOB Developer Team
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpmu tob glbmenulogo
 *
 * Minimum PHP: 8.0.30
 * Minimum WP: 6.8
 * @author 		Engr.AnwarIT- CASDP/TaxOra
 * @package		WPMU Default Logo
 *
 * @copyright 	Copyright (c) 2025, TaxOra LLC USA
 * Orabooks Addon: true
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check for TaxOra - WPMU Membership Subscription Panel Plugin
 */
function orabooks_logo_check_dependency() {
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
        if ( stripos( $plugin_path, 'taxora' ) !== false && stripos( $plugin_path, 'membership' ) !== false ) {
            return true;
        }
        if ( stripos( $plugin_path, 'taxora-membership' ) !== false ) {
            return true;
        }
    }

    return false;
}

function orabooks_logo_membership_notice() {
    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'OraBooks - WPMU Global Menu Logo requires the TaxOra - WPMU Membership Subscription Panel to be installed and activated.', 'wpmu-tob-glbmenulogo' ) . '</p></div>';
}

/**
 * Enforce dependency: Deactivate if membership plugin is missing
 */
function orabooks_logo_enforce_dependency() {
    if ( ! is_admin() || ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    if ( ! orabooks_logo_check_dependency() ) {
        $plugin_file = plugin_basename( __FILE__ );
        if ( is_plugin_active( $plugin_file ) ) {
            deactivate_plugins( $plugin_file );
            add_action( 'admin_notices', 'orabooks_logo_membership_notice' );
            add_action( 'network_admin_notices', 'orabooks_logo_membership_notice' );
            if ( isset( $_GET['activate'] ) ) {
                unset( $_GET['activate'] );
            }
        }
    }
}
add_action( 'admin_init', 'orabooks_logo_enforce_dependency' );


class MultisiteGlobalMenu {
    
    private static $instance = null;
    private $menu_items_option = 'multisite_global_menu_items';
    private $logo_option = 'multisite_global_logo';
    private $network_theme_option = 'multisite_network_theme';
    private $client_theme_option = 'multisite_client_theme';
    private $global_page_rules_option = 'multisite_global_page_rules';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Add a body class when the global logo should be used and the site has no custom logo.
     * This allows CSS in the <head> to hide theme default logos before they flash.
     */
    public function add_global_logo_body_class($classes) {
        // Only apply on client sites and front-end
        if (is_admin() || get_current_blog_id() == 1) {
            return $classes;
        }

        $global_logo = $this->get_secure_logo_url();
        if (empty($global_logo)) {
            return $classes;
        }

        // Respect site-specific logo if present
        $db_logo_id = get_option('theme_mods_' . get_option('stylesheet'));
        $has_own_logo = isset($db_logo_id['custom_logo']) && !empty($db_logo_id['custom_logo']);
        if ($has_own_logo) {
            return $classes;
        }

        $classes[] = 'global-logo-active';
        return $classes;
    }
    
    private function __construct() {
        // Always hook into both admin menus
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('network_admin_menu', array($this, 'add_network_admin_menu'));
        
        add_action('init', array($this, 'init'));
        add_action('init', array($this, 'sync_global_menu_to_client'), 20);
        add_action('wp_head', array($this, 'add_custom_css'));
        
        // LOGO FIXES - Replace theme logo with global logo
        add_action('wp_head', array($this, 'output_logo_html'), 5);
        add_action('wp_footer', array($this, 'inject_logo_javascript'), 999);
        
        // Theme switching hooks
        add_action('wp_loaded', array($this, 'maybe_switch_theme'));
        add_filter('pre_option_template', array($this, 'override_theme_template'));
        add_filter('pre_option_stylesheet', array($this, 'override_theme_stylesheet'));
        
        // Content Injection Hook
        add_filter('the_content', array($this, 'inject_global_page_content'));
        
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate_with_check'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // AJAX handlers
        add_action('wp_ajax_save_global_logo', array($this, 'ajax_save_logo'));
        add_action('wp_ajax_nopriv_save_global_logo', array($this, 'ajax_save_logo'));
        add_action('wp_ajax_remove_global_logo', array($this, 'ajax_remove_logo'));
        add_action('wp_ajax_nopriv_remove_global_logo', array($this, 'ajax_remove_logo'));
        
        // Sync menus when new site is created
        add_action('wp_initialize_site', array($this, 'on_new_site_created'), 10, 1);
        add_action('wpmu_new_blog', array($this, 'on_new_blog_created'), 10, 6);
        
        // Virtual Page Handler (for Global Page Rules)
        add_filter('template_include', array($this, 'setup_virtual_page'));
        
        // Disable Sidebars on Client Sites
        add_action('widgets_init', array($this, 'disable_client_sidebars'), 99);
        
        // Handle Form Submissions (redirects)
        add_action('admin_init', array($this, 'handle_form_submissions'));
    }
    
    /**
     * Helper function to ensure logo URL uses HTTPS protocol
     * Prevents mixed content errors on HTTPS sites
     */
    private function get_secure_logo_url() {
        $logo_url = get_site_option($this->logo_option, '');
        
        if (empty($logo_url)) {
            return '';
        }
        
        // Sanitize the URL
        $logo_url = esc_url_raw($logo_url);
        
        // Convert HTTP to HTTPS if the current site is HTTPS
        if (is_ssl() || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')) {
            $logo_url = str_replace('http://', 'https://', $logo_url);
        }
        
        // Verify the URL is not empty after sanitization
        if (empty($logo_url)) {
            // Log the issue for debugging
            error_log('Orabooks Global Menu: Logo URL is empty after sanitization. Raw value: ' . get_site_option($this->logo_option, ''));
            return '';
        }
        
        return $logo_url;
    }
    
    public function init() {
        // Check dependency before running
        if ( ! orabooks_logo_check_dependency() ) {
            return;
        }
        // add_filter('wp_get_nav_menu_items', array($this, 'add_global_menu_items'), 10, 3); // Removed to prevent duplicates
        add_filter('theme_mod_custom_logo', array($this, 'override_site_logo'));
        add_filter('get_custom_logo', array($this, 'filter_custom_logo_html'), 10, 2);
        
        // Extra / Divi Theme Support
        add_filter('et_get_option_logo', array($this, 'override_et_logo'));
        add_filter('et_get_option_divi_logo', array($this, 'override_et_logo'));
        add_filter('et_get_option_header_logo', array($this, 'override_et_logo'));
        
        // Generic theme mod fallback
        add_filter('theme_mod_logo', array($this, 'override_et_logo'));
        
        // Replace hardcoded theme logo URLs with global logo (Extra theme, etc.)
        add_filter('the_content', array($this, 'replace_hardcoded_logo_in_content'), 5);
        add_filter('wp_footer', array($this, 'replace_hardcoded_logo_in_footer'), 5);
        // Add a body class so we can hide theme logos before DOM/JS runs
        add_filter('body_class', array($this, 'add_global_logo_body_class'));
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // Load text domain
        load_plugin_textdomain('multisite-global-menu', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Output logo HTML directly to header to ensure visibility
     */
    public function output_logo_html() {
        // Only on client sites
        if (get_current_blog_id() == 1 || is_admin()) {
            return;
        }
        
        $global_logo = $this->get_secure_logo_url();
        
        // Debug logging
        error_log('Orabooks Logo - Blog ID: ' . get_current_blog_id() . ', Logo URL: ' . ($global_logo ? $global_logo : 'EMPTY'));
        
        if (!empty($global_logo)) {
            $site_name = get_bloginfo('name');
            $home_url = esc_url(home_url('/'));
            
            echo '<!-- Orabooks Global Logo System Active -->';
            
            // CSS to ensure replaced logo is visible and theme logo is hidden
            echo '<style type="text/css" id="orabooks-logo-css">
                /* Force replaced logo visibility */
                .orabooks-replaced-logo {
                    display: inline-block !important;
                    visibility: visible !important;
                    opacity: 1 !important;
                    max-height: 60px !important;
                    width: auto !important;
                    height: auto !important;
                    position: relative !important;
                    z-index: 999 !important;
                }
                
                /* Hide theme logos that will be replaced */
                .custom-logo:not(.orabooks-replaced-logo),
                .site-logo img:not(.orabooks-replaced-logo),
                .logo img:not(.orabooks-replaced-logo),
                .site-branding img:not(.orabooks-replaced-logo),
                .et_logo img:not(.orabooks-replaced-logo),
                .et_pb_main_logo img:not(.orabooks-replaced-logo) {
                    opacity: 0 !important;
                    visibility: hidden !important;
                }
                
                /* Ensure logo containers stay visible */
                .custom-logo-link,
                .site-logo,
                .logo,
                .site-branding {
                    display: inline-block !important;
                    visibility: visible !important;
                    opacity: 1 !important;
                }
            </style>';
            
            // Output logo data for JavaScript (hidden)
            echo '<div id="orabooks-logo-data" style="display:none !important;" 
                  data-logo-url="' . esc_attr($global_logo) . '" 
                  data-site-name="' . esc_attr($site_name) . '" 
                  data-home-url="' . esc_attr($home_url) . '"></div>';
            
        } else {
            echo '<!-- Orabooks: No global logo configured -->';
            error_log('Orabooks Logo - No logo found for blog ID: ' . get_current_blog_id());
        }
    }
    
    /**
     * Inject JavaScript to replace theme logo with global logo IN PLACE
     */
    public function inject_logo_javascript() {
        // Only on client sites
        if (get_current_blog_id() == 1 || is_admin()) {
            return;
        }
        
        $global_logo = $this->get_secure_logo_url();
        
        if (!empty($global_logo)) {
            ?>
            <script type="text/javascript">
            console.log('Orabooks: Logo replacement system loading');
            
            (function() {
                function replaceThemeLogoInPlace() {
                    console.log('Orabooks: Replacing theme logo in its original position');
                    
                    // Get logo data
                    var logoData = document.getElementById('orabooks-logo-data');
                    if (!logoData) {
                        console.error('Orabooks: Logo data not found');
                        return;
                    }
                    
                    var globalLogoUrl = logoData.getAttribute('data-logo-url');
                    var siteName = logoData.getAttribute('data-site-name');
                    var homeUrl = logoData.getAttribute('data-home-url');
                    
                    console.log('Orabooks: Global logo URL:', globalLogoUrl);
                    
                    if (!globalLogoUrl) {
                        console.error('Orabooks: No logo URL');
                        return;
                    }
                    
                    var replaced = 0;
                    
                    // Find ALL images in header and replace logos
                    var logoSelectors = [
                        '.custom-logo',
                        '.site-logo img',
                        '.logo img',
                        '.site-branding img',
                        '.site-header img',
                        '.et_logo img',
                        '.et_pb_main_logo img',
                        '#logo img',
                        'header img',
                        'img[class*="logo"]',
                        'img[id*="logo"]'
                    ];
                    
                    function setImportant(el, prop, val) {
                        try { if (el && el.style) el.style.setProperty(prop, val, 'important'); } catch (e) {}
                    }

                    for (var i = 0; i < logoSelectors.length; i++) {
                        var logos = document.querySelectorAll(logoSelectors[i]);
                        
                        for (var j = 0; j < logos.length; j++) {
                            var img = logos[j];
                            
                            // Skip if already replaced
                            if (img.classList.contains('orabooks-replaced-logo')) {
                                continue;
                            }
                            
                            // Check if in header (not footer)
                            var inHeader = false;
                            var parent = img;
                            while (parent && parent !== document.body) {
                                var tag = parent.tagName ? parent.tagName.toLowerCase() : '';
                                var cls = parent.className || '';
                                
                                if (tag === 'header' || cls.indexOf('header') !== -1 || cls.indexOf('masthead') !== -1) {
                                    inHeader = true;
                                    break;
                                }
                                if (tag === 'footer' || cls.indexOf('footer') !== -1) {
                                    break;
                                }
                                parent = parent.parentElement;
                            }
                            
                            if (inHeader) {
                                console.log('Orabooks: Replacing logo at selector:', logoSelectors[i]);
                                
                                // REPLACE the image source
                                img.src = globalLogoUrl;
                                img.alt = siteName;
                                img.className += ' orabooks-replaced-logo';

                                // Force visibility (use !important to overcome theme CSS)
                                setImportant(img, 'display', 'inline-block');
                                setImportant(img, 'visibility', 'visible');
                                setImportant(img, 'opacity', '1');
                                setImportant(img, 'max-height', '60px');
                                setImportant(img, 'width', 'auto');
                                setImportant(img, 'height', 'auto');
                                setImportant(img, 'z-index', '9999');
                                setImportant(img, 'position', 'relative');
                                setImportant(img, 'clip', 'auto');
                                setImportant(img, 'clip-path', 'none');
                                setImportant(img, '-webkit-clip-path', 'none');
                                setImportant(img, 'transform', 'none');
                                setImportant(img, 'filter', 'none');

                                // Update parent link if exists
                                var link = img.closest('a');
                                if (link) {
                                    link.href = homeUrl;
                                    setImportant(link, 'display', 'inline-block');
                                    setImportant(link, 'visibility', 'visible');
                                    setImportant(link, 'opacity', '1');
                                    setImportant(link, 'z-index', '9999');
                                    setImportant(link, 'position', 'relative');
                                }
                                
                                replaced++;
                            }
                        }
                    }
                    
                    // If no logo images found, try to insert into logo containers
                    if (replaced === 0) {
                        console.log('Orabooks: No logo images found, trying containers');
                        
                        var containerSelectors = [
                            '.custom-logo-link',
                            '.site-logo',
                            '.logo',
                            '.site-branding',
                            '.et_logo',
                            '.et_pb_main_logo',
                            '#logo'
                        ];
                        
                        for (var k = 0; k < containerSelectors.length; k++) {
                            var containers = document.querySelectorAll(containerSelectors[k]);
                            
                            for (var l = 0; l < containers.length; l++) {
                                var container = containers[l];
                                
                                // Check if in header
                                var inHeader = false;
                                var parent = container;
                                while (parent && parent !== document.body) {
                                    var tag = parent.tagName ? parent.tagName.toLowerCase() : '';
                                    var cls = parent.className || '';
                                    
                                    if (tag === 'header' || cls.indexOf('header') !== -1 || cls.indexOf('masthead') !== -1) {
                                        inHeader = true;
                                        break;
                                    }
                                    if (tag === 'footer' || cls.indexOf('footer') !== -1) {
                                        break;
                                    }
                                    parent = parent.parentElement;
                                }
                                
                                if (inHeader) {
                                    console.log('Orabooks: Inserting logo into container:', containerSelectors[k]);
                                    
                                    // Clear container
                                    container.innerHTML = '';

                                    // Create new logo
                                    var newLink = document.createElement('a');
                                    newLink.href = homeUrl;
                                    newLink.className = 'custom-logo-link';
                                    setImportant(newLink, 'display', 'inline-block');
                                    setImportant(newLink, 'visibility', 'visible');
                                    setImportant(newLink, 'opacity', '1');

                                    var newImg = document.createElement('img');
                                    newImg.src = globalLogoUrl;
                                    newImg.alt = siteName;
                                    newImg.className = 'custom-logo orabooks-replaced-logo';
                                    setImportant(newImg, 'max-height', '60px');
                                    setImportant(newImg, 'width', 'auto');
                                    setImportant(newImg, 'display', 'inline-block');
                                    setImportant(newImg, 'visibility', 'visible');
                                    setImportant(newImg, 'opacity', '1');
                                    setImportant(newImg, 'z-index', '9999');
                                    setImportant(newImg, 'position', 'relative');

                                    newLink.appendChild(newImg);
                                    container.appendChild(newLink);

                                    setImportant(container, 'display', 'inline-block');
                                    setImportant(container, 'visibility', 'visible');
                                    setImportant(container, 'opacity', '1');
                                    
                                    replaced++;
                                    break;
                                }
                            }
                            
                            if (replaced > 0) {
                                break;
                            }
                        }
                    }
                    
                    if (replaced > 0) {
                        console.log('Orabooks: Successfully replaced', replaced, 'logo(s) in original position');
                    } else {
                        // Silently handle no logo found to avoid console clutter
                        // console.warn('Orabooks: No logo found to replace in header');
                    }
                }
                
                // Run immediately
                replaceThemeLogoInPlace();
                
                // Run when DOM ready
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', replaceThemeLogoInPlace);
                } else {
                    setTimeout(replaceThemeLogoInPlace, 100);
                }
                
                // Run after delays to catch late-loading elements
                setTimeout(replaceThemeLogoInPlace, 500);
                setTimeout(replaceThemeLogoInPlace, 1000);
            })();
            </script>
            <?php
        }
    }
    
    /**
     * Filter menu items to hide/show based on user login status
     * DISABLED: The OraBooks Membership plugin's default menu system handles this better
     */
    
    public function override_et_logo($logo_url) {
        // Only apply to client sites (not main network site)
        if (get_current_blog_id() == 1) {
            return $logo_url;
        }
        
        // Check if site has its own custom logo set
        $site_logo_id = get_theme_mod('custom_logo');
        if ($site_logo_id) {
            return $logo_url;
        }
        
        $global_logo = $this->get_secure_logo_url();
        if (!empty($global_logo)) {
            return $global_logo;
        }
        
        return $logo_url;
    }

    /**
     * Replace hardcoded theme logo URLs with global logo
     * Handles cases where theme hardcodes the logo path (e.g., Extra theme)
     */
    public function replace_hardcoded_logo_in_content($content) {
        // Only apply to client sites
        if (get_current_blog_id() == 1 || is_admin()) {
            return $content;
        }

        $global_logo = $this->get_secure_logo_url();
        if (empty($global_logo)) {
            return $content;
        }

        // Check if site has its own custom logo (respect site customization)
        $db_logo_id = get_option('theme_mods_' . get_option('stylesheet'));
        $has_own_logo = isset($db_logo_id['custom_logo']) && !empty($db_logo_id['custom_logo']);
        if ($has_own_logo) {
            return $content;
        }

        // Replace common hardcoded theme logo paths
        $logo_patterns = array(
            // Extra theme logo
            '#wp-content/themes/Extra/images/logo\.svg#i',
            '#wp-content/themes/extra/images/logo\.svg#i',
            // Divi theme logo paths
            '#wp-content/themes/Divi/images/logo\.svg#i',
            '#wp-content/themes/divi/images/logo\.svg#i',
            // Generic theme logo patterns
            '#wp-content/themes/[^/]+/images/logo\.[^"\']+#i',
            '#wp-content/themes/[^/]+/assets/images/logo\.[^"\']+#i',
        );

        foreach ($logo_patterns as $pattern) {
            $content = preg_replace($pattern, esc_url($global_logo), $content);
        }

        return $content;
    }

    /**
     * Replace hardcoded logo URLs in footer/header output
     */
    public function replace_hardcoded_logo_in_footer() {
        // Only apply to client sites
        if (get_current_blog_id() == 1 || is_admin()) {
            return;
        }

        $global_logo = $this->get_secure_logo_url();
        if (empty($global_logo)) {
            return;
        }

        // Check if site has its own custom logo (respect site customization)
        $db_logo_id = get_option('theme_mods_' . get_option('stylesheet'));
        $has_own_logo = isset($db_logo_id['custom_logo']) && !empty($db_logo_id['custom_logo']);
        if ($has_own_logo) {
            return;
        }

        // Add JavaScript to replace hardcoded logo URLs in DOM after page load
        // Also apply inline opacity rules to prevent theme logo flash
        echo '<script type="text/javascript">
            (function() {
                var globalLogo = "' . esc_js($global_logo) . '";

                function initializeLogo() {
                    try {
                        var imgs = document.querySelectorAll("img[src]");
                        imgs.forEach(function(img) {
                            try {
                                var src = img.getAttribute("src") || "";
                                // If image is from theme and filename contains "logo", hide it and replace source
                                if (/\/wp-content\/themes\//i.test(src) && /logo/i.test(src)) {
                                    img.style.transition = "opacity 0.05s linear";
                                    img.style.opacity = "0";
                                    img.style.visibility = "hidden";
                                    // Replace src to our global logo to avoid mixed content or broken src
                                    img.src = globalLogo;
                                    img.alt = "' . esc_js(get_bloginfo('name')) . '";
                                }
                            } catch (e) { /* ignore */ }
                        });
                    } catch (e) { /* ignore */ }

                    // Narrow selectors for common theme logo images
                    try {
                        var themeImgs = document.querySelectorAll(
                            ".site-logo img, .logo img, .et_logo img, .et_pb_main_logo img, .site-header img[src*=\"logo\"]"
                        );
                        themeImgs.forEach(function(ti) {
                            try { ti.style.transition = "opacity 0.05s linear"; ti.style.opacity = "0"; ti.style.visibility = "hidden"; } catch (e) {}
                        });
                    } catch (e) {}

                    // Remove background-image logos from common containers while preserving layout
                    try {
                        var bgContainers = document.querySelectorAll(".site-logo, .logo, .et_logo, .et_pb_main_logo");
                        bgContainers.forEach(function(el) { try { el.style.backgroundImage = "none"; } catch (e) {} });
                    } catch (e) {}

                    // Ensure custom/plugin logos are visible and opaque
                    try {
                        var customImgs = document.querySelectorAll(".custom-logo-link img, .custom-logo img");
                        customImgs.forEach(function(ci) { try { ci.style.transition = "opacity 0.05s linear"; ci.style.opacity = "1"; ci.style.visibility = "visible"; ci.style.display = "inline-block"; ci.style.zIndex = "9999"; } catch (e) {} });

                        var customContainers = document.querySelectorAll(".custom-logo-link, .custom-logo");
                        customContainers.forEach(function(el) { try { el.style.opacity = "1"; el.style.visibility = "visible"; el.style.display = "inline-block"; el.style.zIndex = "9999"; } catch (e) {} });
                    } catch (e) {}
                }

                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", initializeLogo);
                } else {
                    initializeLogo();
                }

                // Retries to catch dynamic content
                setTimeout(initializeLogo, 150);
                setTimeout(initializeLogo, 400);
                setTimeout(initializeLogo, 1000);
            })();
        </script>';
    }
    
    public function disable_client_sidebars() {
        // Only run on client sites (not main network site)
        if (get_current_blog_id() == 1 || is_admin()) {
            return;
        }
        
        global $wp_registered_sidebars;
        if ($wp_registered_sidebars) {
            foreach ($wp_registered_sidebars as $sidebar_id => $sidebar) {
                unregister_sidebar($sidebar_id);
            }
        }
    }

    
    public function activate_with_check() {
        // Check dependency immediately
        if ( ! orabooks_logo_check_dependency() ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
            // Note: Notices triggered here might not show up immediately on activation redirect
            // as the plugin deactivates before the next page load. 
            // The admin_init hook handles the persistent notice.
            return;
        }
        $this->activate();
    }

    public function activate() {
        // Set default options if not exists
        if (false === get_site_option($this->menu_items_option)) {
            update_site_option($this->menu_items_option, array());
        }
        if (false === get_site_option($this->logo_option)) {
            update_site_option($this->logo_option, '');
        }
        if (false === get_site_option($this->network_theme_option)) {
            update_site_option($this->network_theme_option, '');
        }
        if (false === get_site_option($this->client_theme_option)) {
            update_site_option($this->client_theme_option, '');
        }
        if (false === get_site_option($this->global_page_rules_option)) {
            update_site_option($this->global_page_rules_option, array());
        }
        
        // Sync to all existing sites
        $this->sync_to_all_sites();
        
        // Force flush rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Remove global menu items from all sites
        $this->remove_from_all_sites();
        flush_rewrite_rules();
    }
    
    public function add_admin_menu() {
        // Add to regular admin menu for Super Admins
        if (current_user_can('manage_network_options')) {
            add_menu_page(
                __('Global Menu & Logo', 'multisite-global-menu'),
                __('Global Menu & Logo', 'multisite-global-menu'),
                'manage_network_options',
                'multisite-global-menu',
                array($this, 'admin_page'),
                'dashicons-admin-multisite',
                90
            );
        }
    }
    
    public function add_network_admin_menu() {
        // Add to network admin menu
        add_menu_page(
            __('Global Menu & Logo', 'multisite-global-menu'),
            __('Global Menu & Logo', 'multisite-global-menu'),
            'manage_network_options',
            'multisite-global-menu',
            array($this, 'admin_page'),
            'dashicons-admin-multisite',
            90
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        // Check if we're on our plugin page in either admin or network admin
        if ('toplevel_page_multisite-global-menu' === $hook || 
            'toplevel_page_multisite-global-menu-network' === $hook) {
            
            wp_enqueue_media();
            wp_enqueue_style('multisite-global-menu-admin', plugin_dir_url(__FILE__) . 'admin.css', array(), '2.0.0');
            wp_enqueue_script('multisite-global-menu-admin', plugin_dir_url(__FILE__) . 'admin.js', array('jquery', 'media-upload'), '2.0.0', true);

            // Tailwind CDN (lightweight dev-friendly include). Loads before styles so utilities are available.
            wp_enqueue_script('multisite-tailwind-cdn', 'https://cdn.tailwindcss.com', array(), null, false);
            // Custom design system overrides to remove background colors and add modern components
            wp_enqueue_style('multisite-design-system', plugin_dir_url(__FILE__) . 'design-system.css', array(), '1.0.0');
            
            wp_localize_script('multisite-global-menu-admin', 'multisiteGlobalMenu', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('multisite_global_menu_nonce'),
                'title' => __('Select or Upload Logo', 'multisite-global-menu'),
                'buttonText' => __('Use as Global Logo', 'multisite-global-menu'),
                'confirmDelete' => __('Are you sure you want to delete this menu item?', 'multisite-global-menu'),
                'confirmLogoDelete' => __('Are you sure you want to remove the global logo?', 'multisite-global-menu'),
                'uploadLogoText' => __('Upload Logo', 'multisite-global-menu'),
                'changeLogoText' => __('Change Logo', 'multisite-global-menu'),
                'removeLogoText' => __('Remove Logo', 'multisite-global-menu'),
                'noLogoText' => __('No logo set', 'multisite-global-menu')
            ));
        }
    }
    
    public function enqueue_frontend_scripts() {
        // Only enqueue on client sites (not main network site)
        if (get_current_blog_id() == 1) {
            return;
        }
        
        // Only load minimal frontend CSS for logo - removed Tailwind CDN and design system
        // to prevent CSS conflicts with default WordPress theme styles
        wp_enqueue_style('multisite-global-menu-frontend', plugin_dir_url(__FILE__) . 'frontend.css', array(), '2.0.0');
    }
    
    public function inject_global_page_content($content) {
        // Only run on client sites (not main network site)
        if (get_current_blog_id() == 1 || is_admin()) {
            return $content;
        }
        
        // Get global page rules
        $rules = get_site_option($this->global_page_rules_option, array());
        
        if (empty($rules)) {
            return $content;
        }
        
        // Get current URL
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        
        foreach ($rules as $rule) {
            $pattern = $rule['url_pattern'];
            $shortcode = $rule['shortcode'];
            
            // Convert wildcard * to regex .*
            $regex = str_replace('*', '.*', $pattern);
            // Escape other regex characters
            $regex = str_replace('/', '\/', $regex);
            // Add delimiters
            $regex = '/^' . $regex . '$/i';
            
            // Check if current URL matches pattern
            // We also check if the pattern matches the path (e.g. /pricing)
            $path = parse_url($current_url, PHP_URL_PATH);
            
            // Match against full URL or just path
            if (preg_match($regex, $current_url) || preg_match($regex, $path)) {
                // Match found! Inject shortcode
                return do_shortcode($shortcode);
            }
        }
        
        return $content;
    }

    public function setup_virtual_page($template) {
        // Only run on client sites (not main network site)
        if (get_current_blog_id() == 1 || is_admin()) {
            return $template;
        }

        global $wp_query, $post;

        // Only proceed if it's a 404 error
        if (!$wp_query->is_404) {
            return $template;
        }
        
        // Get global page rules
        $rules = get_site_option($this->global_page_rules_option, array());
        
        if (empty($rules)) {
            return $template;
        }
        
        // Get current URL
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $path = parse_url($current_url, PHP_URL_PATH);
        
        foreach ($rules as $rule) {
            $pattern = $rule['url_pattern'];
            $shortcode = $rule['shortcode'];
            
            // Normalize pattern
            $pattern = trim($rule['url_pattern']);
            
            // If pattern is just a path (e.g. "features" or "/features"), ensure it matches the path
            if (strpos($pattern, '*') === false && strpos($pattern, 'http') === false) {
                // It's likely a path
                $check_pattern = '/' . ltrim($pattern, '/');
                $check_path = '/' . ltrim($path, '/');
                
                if ($check_pattern === $check_path) {
                    $match = true;
                } else {
                    $match = false;
                }
            } else {
                // It's a regex/wildcard pattern
                // Convert wildcard * to regex .*
                $regex = str_replace('*', '.*', $pattern);
                // Escape other regex characters (except .* which we just added)
                $regex = str_replace('/', '\/', $regex);
                // Add delimiters
                $regex = '/^' . $regex . '$/i';
                
                $match = preg_match($regex, $current_url) || preg_match($regex, $path);
            }
            
            // Check if current URL matches pattern
            if ($match) {
                // Match found! Create virtual page
                
                // Reset flags
                $wp_query->init(); // Reset all flags to defaults
                $wp_query->is_404 = false;
                $wp_query->is_page = true;
                $wp_query->is_singular = true;
                $wp_query->is_main_query = true;
                
                $wp_query->found_posts = 1;
                $wp_query->post_count = 1;
                $wp_query->max_num_pages = 1;
                $wp_query->current_post = -1;
                
                // Clean up path to generate a title
                $slug = trim($path, '/');
                $title = ucwords(str_replace(array('-', '_'), ' ', basename($slug)));
                if (empty($title)) {
                    $title = 'Global Page';
                }
                
                // Create dummy post
                $post = new stdClass();
                $post->ID = -42; // Use a negative ID to avoid conflicts
                $post->post_author = 1;
                $post->post_date = current_time('mysql');
                $post->post_date_gmt = current_time('mysql', 1);
                $post->post_content = $shortcode; // The content will be processed by do_shortcode in the loop
                $post->post_title = $title;
                $post->post_excerpt = '';
                $post->post_status = 'publish';
                $post->comment_status = 'closed';
                $post->ping_status = 'closed';
                $post->post_password = '';
                $post->post_name = basename($slug);
                $post->to_ping = '';
                $post->pinged = '';
                $post->modified = $post->post_date;
                $post->modified_gmt = $post->post_date_gmt;
                $post->post_content_filtered = '';
                $post->post_parent = 0;
                $post->guid = $current_url;
                $post->menu_order = 0;
                $post->post_type = 'page';
                $post->post_mime_type = '';
                $post->comment_count = 0;
                $post->filter = 'raw';
                
                // Add WP_Post methods if available (WP 4.4+)
                if (class_exists('WP_Post')) {
                    $post = new WP_Post($post);
                }
                
                $wp_query->posts = array($post);
                $wp_query->post = $post;
                $wp_query->queried_object = $post;
                $wp_query->queried_object_id = $post->ID;
                
                // Set global post
                $GLOBALS['post'] = $post;
                
                // Force content injection as fallback
                add_filter('the_content', function($content) use ($shortcode) {
                    if (in_the_loop() && is_main_query()) {
                        return do_shortcode($shortcode);
                    }
                    return $content;
                });
                
                // Set 200 OK status
                status_header(200);
                
                // Return page template
                $page_template = get_page_template();
                if (empty($page_template)) {
                    $page_template = get_index_template();
                }
                return $page_template;
            }
        }
        
        return $template;
    }

    public function handle_form_submissions() {
        if (!current_user_can('manage_network_options')) {
            return;
        }

        // Handle form submission
        if (isset($_POST['submit_global_menu_item']) && check_admin_referer('add_global_menu_item')) {
            $this->handle_menu_item_submission();
        }
        
        // Handle edit form submission
        if (isset($_POST['update_global_menu_item']) && check_admin_referer('edit_global_menu_item')) {
            $this->handle_menu_item_submission();
        }
        
        // Handle delete action (separate from add/edit)
        if (isset($_POST['delete_global_menu_item']) && isset($_POST['delete_menu_item_id'])) {
            $item_id = sanitize_text_field($_POST['delete_menu_item_id']);
            if (check_admin_referer('delete_global_menu_item_' . $item_id, '_wpnonce')) {
                $this->delete_menu_item($item_id);
            }
        }
        
        // Handle theme settings submission
        if (isset($_POST['submit_theme_settings']) && check_admin_referer('save_theme_settings')) {
            $this->handle_theme_settings_submission();
        }
        
        // Handle global page rule submission
        if (isset($_POST['submit_global_page_rule']) && check_admin_referer('add_global_page_rule')) {
            $this->handle_page_rule_submission();
        }
        
        // Handle global page rule deletion
        if (isset($_POST['delete_global_page_rule']) && isset($_POST['delete_rule_id'])) {
            $rule_id = sanitize_text_field($_POST['delete_rule_id']);
            if (check_admin_referer('delete_global_page_rule_' . $rule_id)) {
                $this->delete_page_rule($rule_id);
            }
        }
    }
    
    public function admin_page() {
        // Check capabilities
        if (!current_user_can('manage_network_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'multisite-global-menu'));
        }
        
        $menu_items = get_site_option($this->menu_items_option, array());
        $global_logo = $this->get_secure_logo_url();
        $network_theme = get_site_option($this->network_theme_option, '');
        $client_theme = get_site_option($this->client_theme_option, '');
        
        // Get all available themes
        $themes = wp_get_themes();
        $available_themes = array();
        
        foreach ($themes as $theme_slug => $theme) {
            if ($theme->exists()) {
                $available_themes[$theme_slug] = $theme->get('Name');
            }
        }
        
        $page_rules = get_site_option($this->global_page_rules_option, array());
        
        ?>
        <div class="wrap multisite-global-menu-wrap">
            <h1><?php _e('Multisite Global Settings', 'multisite-global-menu'); ?></h1>
            
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Settings saved successfully!', 'multisite-global-menu'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="notice notice-info">
                <p>
                    <strong><?php _e('ℹ️ Important:', 'multisite-global-menu'); ?></strong>
                    <?php _e('The global menu and logo configured here will only appear on <strong>client sites</strong> (Site ID > 1), not on the main network site.', 'multisite-global-menu'); ?>
                </p>
            </div>
            
            <!-- Theme Settings -->
            <div class="card">
                <h2><?php _e('Theme Management', 'multisite-global-menu'); ?></h2>
                <p><?php _e('Set different themes for the main network site and all client sites.', 'multisite-global-menu'); ?></p>
                
                <form method="post">
                    <?php wp_nonce_field('save_theme_settings'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="network_theme"><?php _e('Main Network Site Theme', 'multisite-global-menu'); ?></label>
                            </th>
                            <td>
                                <select id="network_theme" name="network_theme">
                                    <option value=""><?php _e('— Use Current Theme —', 'multisite-global-menu'); ?></option>
                                    <?php foreach ($available_themes as $slug => $name): ?>
                                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($network_theme, $slug); ?>>
                                            <?php echo esc_html($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php _e('Theme for the main network site (Site ID = 1)', 'multisite-global-menu'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="client_theme"><?php _e('Client Sites Theme', 'multisite-global-menu'); ?></label>
                            </th>
                            <td>
                                <select id="client_theme" name="client_theme">
                                    <option value=""><?php _e('— Use Current Theme —', 'multisite-global-menu'); ?></option>
                                    <?php foreach ($available_themes as $slug => $name): ?>
                                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($client_theme, $slug); ?>>
                                            <?php echo esc_html($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php _e('Theme for all client sites (Site ID > 1)', 'multisite-global-menu'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Save Theme Settings', 'multisite-global-menu'), 'primary', 'submit_theme_settings'); ?>
                </form>
                
                <div class="theme-status" style="margin-top: 20px; padding: 10px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                    <h4><?php _e('Current Status:', 'multisite-global-menu'); ?></h4>
                    <ul>
                        <li><strong><?php _e('Main Network Site:', 'multisite-global-menu'); ?></strong> 
                            <?php echo $network_theme ? esc_html($available_themes[$network_theme] ?? $network_theme) : __('Using current theme', 'multisite-global-menu'); ?>
                        </li>
                        <li><strong><?php _e('Client Sites:', 'multisite-global-menu'); ?></strong> 
                            <?php echo $client_theme ? esc_html($available_themes[$client_theme] ?? $client_theme) : __('Using current theme', 'multisite-global-menu'); ?>
                        </li>
                        <li><strong><?php _e('Total Sites:', 'multisite-global-menu'); ?></strong> 
                            <?php echo get_blog_count(); ?>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Logo Settings -->
            <div class="card">
                <h2><?php _e('Global Logo Settings', 'multisite-global-menu'); ?></h2>
                <p><?php _e('This logo will be used as both header logo and site logo across all sites in the network.', 'multisite-global-menu'); ?></p>
                
                <div class="logo-upload-section">
                    <div class="logo-preview">
                        <?php if ($global_logo): ?>
                            <img src="<?php echo esc_url($global_logo); ?>" alt="<?php _e('Global Logo', 'multisite-global-menu'); ?>" style="max-width: 200px; height: auto;" />
                        <?php else: ?>
                            <div class="no-logo"><?php _e('No logo set', 'multisite-global-menu'); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="logo-actions">
                        <button type="button" id="upload-logo-btn" class="button button-primary">
                            <?php echo $global_logo ? __('Change Logo', 'multisite-global-menu') : __('Upload Logo', 'multisite-global-menu'); ?>
                        </button>
                        
                        <?php if ($global_logo): ?>
                            <button type="button" id="remove-logo-btn" class="button button-link-delete">
                                <?php _e('Remove Logo', 'multisite-global-menu'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>


        </div>
        <?php
    }
    
    private function handle_page_rule_submission() {
        if (!current_user_can('manage_network_options')) {
            return;
        }
        
        $url_pattern = sanitize_text_field($_POST['rule_url_pattern']);
        $shortcode = sanitize_text_field($_POST['rule_shortcode']);
        
        if (!empty($url_pattern) && !empty($shortcode)) {
            $rules = get_site_option($this->global_page_rules_option, array());
            $rule_id = uniqid('rule_');
            
            $rules[$rule_id] = array(
                'id' => $rule_id,
                'url_pattern' => $url_pattern,
                'shortcode' => $shortcode
            );
            
            update_site_option($this->global_page_rules_option, $rules);
            
            // Redirect to show success
            wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
            exit;
        }
    }
    
    private function delete_page_rule($rule_id) {
        $rules = get_site_option($this->global_page_rules_option, array());
        
        if (isset($rules[$rule_id])) {
            unset($rules[$rule_id]);
            update_site_option($this->global_page_rules_option, $rules);
            
            // Redirect to show success
            wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
            exit;
        }
    }

    private function handle_theme_settings_submission() {
        if (!current_user_can('manage_network_options')) {
            return;
        }
        
        $network_theme = sanitize_text_field($_POST['network_theme']);
        $client_theme = sanitize_text_field($_POST['client_theme']);
        
        update_site_option($this->network_theme_option, $network_theme);
        update_site_option($this->client_theme_option, $client_theme);
        
        // Apply themes immediately
        $this->apply_theme_to_all_sites();
        
        // Redirect to show success
        wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
        exit;
    }
    
    private function apply_theme_to_all_sites() {
        if (!is_multisite()) {
            return;
        }
        
        $network_theme = get_site_option($this->network_theme_option, '');
        $client_theme = get_site_option($this->client_theme_option, '');
        
        $sites = get_sites(array('number' => 0));
        
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            
            // Determine which theme to apply
            if ($site->blog_id == 1 && $network_theme) {
                // Main network site
                switch_theme($network_theme);
            } elseif ($site->blog_id > 1 && $client_theme) {
                // Client site
                switch_theme($client_theme);
            }
            
            restore_current_blog();
        }
    }
    
    public function maybe_switch_theme() {
        if (!is_multisite() || is_admin()) {
            return;
        }
        
        $network_theme = get_site_option($this->network_theme_option, '');
        $client_theme = get_site_option($this->client_theme_option, '');
        
        $current_blog_id = get_current_blog_id();
        
        if ($current_blog_id == 1 && $network_theme) {
            // Main network site
            if (get_template() !== $network_theme) {
                switch_theme($network_theme);
            }
        } elseif ($current_blog_id > 1 && $client_theme) {
            // Client site
            if (get_template() !== $client_theme) {
                switch_theme($client_theme);
            }
        }
    }
    
    public function override_theme_template($template) {
        if (!is_multisite() || is_admin()) {
            return $template;
        }
        
        $network_theme = get_site_option($this->network_theme_option, '');
        $client_theme = get_site_option($this->client_theme_option, '');
        
        $current_blog_id = get_current_blog_id();
        
        if ($current_blog_id == 1 && $network_theme) {
            return $network_theme;
        } elseif ($current_blog_id > 1 && $client_theme) {
            return $client_theme;
        }
        
        return $template;
    }
    
    public function override_theme_stylesheet($stylesheet) {
        if (!is_multisite() || is_admin()) {
            return $stylesheet;
        }
        
        $network_theme = get_site_option($this->network_theme_option, '');
        $client_theme = get_site_option($this->client_theme_option, '');
        
        $current_blog_id = get_current_blog_id();
        
        if ($current_blog_id == 1 && $network_theme) {
            return $network_theme;
        } elseif ($current_blog_id > 1 && $client_theme) {
            return $client_theme;
        }
        
        return $stylesheet;
    }
    
    private function handle_menu_item_submission() {
        if (!current_user_can('manage_network_options')) {
            return;
        }
        
        // Handle edit action
        if (isset($_POST['update_global_menu_item']) && isset($_POST['edit_menu_item_id'])) {
            $item_id = sanitize_text_field($_POST['edit_menu_item_id']);
            $title = sanitize_text_field($_POST['menu_title']);
            $url = sanitize_text_field($_POST['menu_url']); // Allow relative URLs
            $target = in_array($_POST['menu_target'], array('_self', '_blank')) ? $_POST['menu_target'] : '_self';
            $classes = sanitize_text_field($_POST['menu_classes']);
            
            if (!empty($title) && !empty($url)) {
                $this->update_menu_item($item_id, $title, $url, $target, $classes);
            }
            return;
        }
        
        // Handle add action
        $title = sanitize_text_field($_POST['menu_title']);
        $url = sanitize_text_field($_POST['menu_url']); // Allow relative URLs
        $target = in_array($_POST['menu_target'], array('_self', '_blank')) ? $_POST['menu_target'] : '_self';
        $classes = sanitize_text_field($_POST['menu_classes']);
        
        if (!empty($title) && !empty($url)) {
            $this->add_menu_item($title, $url, $target, $classes);
        }
    }
    
    private function add_menu_item($title, $url, $target = '_self', $classes = '') {
        $menu_items = get_site_option($this->menu_items_option, array());
        $item_id = uniqid('global_menu_');
        
        $menu_items[$item_id] = array(
            'title' => $title,
            'url' => $url,
            'target' => $target,
            'classes' => $classes,
            'id' => $item_id
        );
        
        update_site_option($this->menu_items_option, $menu_items);
        $this->sync_to_all_sites();
        
        // Redirect to show success
        wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
        exit;
    }
    
    private function update_menu_item($item_id, $title, $url, $target = '_self', $classes = '') {
        $menu_items = get_site_option($this->menu_items_option, array());
        
        if (isset($menu_items[$item_id])) {
            $menu_items[$item_id] = array(
                'title' => $title,
                'url' => $url,
                'target' => $target,
                'classes' => $classes,
                'id' => $item_id
            );
            
            update_site_option($this->menu_items_option, $menu_items);
            $this->sync_to_all_sites();
            
            // Redirect to show success (remove edit_item from URL)
            $redirect_url = remove_query_arg('edit_item', wp_get_referer());
            wp_redirect(add_query_arg('settings-updated', 'true', $redirect_url));
            exit;
        }
    }
    
    private function delete_menu_item($item_id) {
        $menu_items = get_site_option($this->menu_items_option, array());
        
        if (isset($menu_items[$item_id])) {
            unset($menu_items[$item_id]);
            update_site_option($this->menu_items_option, $menu_items);
            $this->remove_from_all_sites($item_id);
            
            // Redirect to show success
            $redirect_url = remove_query_arg('edit_item', wp_get_referer());
            wp_redirect(add_query_arg('settings-updated', 'true', $redirect_url));
            exit;
        }
    }    
    public function ajax_save_logo() {
        check_ajax_referer('multisite_global_menu_nonce', 'nonce');
        
        if (!current_user_can('manage_network_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'multisite-global-menu')));
        }
        
        $logo_url = isset($_POST['logo_url']) ? sanitize_text_field($_POST['logo_url']) : '';
        
        if ($logo_url) {
            // Validate the URL
            $logo_url = esc_url_raw($logo_url);
            
            if (empty($logo_url)) {
                wp_send_json_error(array('message' => __('Invalid logo URL format', 'multisite-global-menu')));
                return;
            }
            
            // Convert to HTTPS to prevent mixed content errors
            $logo_url = str_replace('http://', 'https://', $logo_url);
            
            // Save to site options
            $saved = update_site_option($this->logo_option, $logo_url);
            
            if (!$saved) {
                error_log('Failed to save logo URL: ' . $logo_url);
                wp_send_json_error(array('message' => __('Failed to save logo', 'multisite-global-menu')));
                return;
            }
            
            // Verify it was saved
            $verify = get_site_option($this->logo_option, '');
            error_log('Logo saved. URL: ' . $verify);
            
            wp_send_json_success(array(
                'message' => __('Logo saved successfully!', 'multisite-global-menu'),
                'logo_url' => $logo_url
            ));
        } else {
            wp_send_json_error(array('message' => __('Invalid logo URL', 'multisite-global-menu')));
        }
    }
    
    public function ajax_remove_logo() {
        check_ajax_referer('multisite_global_menu_nonce', 'nonce');
        
        if (!current_user_can('manage_network_options')) {
            wp_die(__('Unauthorized', 'multisite-global-menu'));
        }
        
        update_site_option($this->logo_option, '');
        wp_send_json_success(array(
            'message' => __('Logo removed successfully!', 'multisite-global-menu')
        ));
    }
    
    public function override_site_logo($value) {
        // Only apply to client sites (not main network site)
        if (get_current_blog_id() == 1) {
            return $value;
        }
        
        // Check if site has its own custom logo set (this filter itself might be called during that check, so be careful of recursion if we call get_theme_mod)
        // However, this filter 'theme_mod_custom_logo' modifies the return value of get_theme_mod('custom_logo').
        // If $value is set (valid ID), it means the site has a logo. We should respect it.
        if (!empty($value)) {
             return $value;
        }
        
        $global_logo = $this->get_secure_logo_url();
        
        if (!empty($global_logo)) {
            // Try to get attachment ID from URL on the main site
            $attachment_id = attachment_url_to_postid($global_logo);
            if ($attachment_id) {
                return $attachment_id;
            }
            
            // If attachment_url_to_postid fails (common in multisite), 
            // we return a fake ID to trigger the get_custom_logo filter.
            // The get_custom_logo filter will then generate the proper HTML
            // using the global logo URL.
            return -1; // Fake ID that will trigger get_custom_logo filter
        }
        
        return $value;
    }
    
    public function filter_custom_logo_html($html, $blog_id) {
        // Only apply to client sites (not main network site)
        if (get_current_blog_id() == 1) {
            return $html;
        }
        
        // Check if site has its own custom logo set
        // We need to bypass our own filter 'theme_mod_custom_logo' to get the real value, 
        // but 'get_theme_mod' applies filters.
        // Instead, let's just check if we have an output HTML and if it looks like a real logo (not our fake one)
        // Actually, easiest way: if the site has a custom logo ID set in DB options, respect it.
        $db_logo_id = get_option('theme_mods_' . get_option('stylesheet'));
        $has_own_logo = isset($db_logo_id['custom_logo']) && !empty($db_logo_id['custom_logo']);
        
        if ($has_own_logo) {
            return $html;
        }
        
        $global_logo = $this->get_secure_logo_url();
        
        if (!empty($global_logo)) {
            // Get site name for alt text
            $site_name = get_bloginfo('name');
            
            // Generate custom logo HTML with proper attributes
            // We force this HTML regardless of what ID was passed
            $html = sprintf(
                '<a href="%1$s" class="custom-logo-link" rel="home" itemprop="url"><img src="%2$s" class="custom-logo orabooks-replaced-logo" alt="%3$s" itemprop="logo" style="max-height:60px;width:auto;height:auto;display:inline-block;visibility:visible;opacity:1;"/></a>',
                esc_url(home_url('/'))  ,
                esc_url($global_logo),
                esc_attr($site_name)
            );
        }
        
        return $html;
    }

    
    public function add_custom_css() {
        // Only apply to client sites (not main network site)
        if (get_current_blog_id() == 1) {
            return;
        }
        
        $global_logo = $this->get_secure_logo_url();
        
        if (!empty($global_logo)) {
            // Only add global logo CSS if we are actually using it
            $db_logo_id = get_option('theme_mods_' . get_option('stylesheet'));
            $has_own_logo = isset($db_logo_id['custom_logo']) && !empty($db_logo_id['custom_logo']);
            
            if (!$has_own_logo) {
                echo '<style type="text/css">
                    /* Hide theme default logo IMAGES (narrowly targeted) immediately when using global logo */
                    /* Only target images inside themes that contain "logo" in the filename to avoid hiding other images */
                    body.global-logo-active img[src*="/wp-content/themes/"][src*="logo"] {
                        visibility: hidden !important;
                        opacity: 0 !important;
                    }

                    /* Also hide common logo img selectors while preserving container/layout */
                    body.global-logo-active .site-logo img,
                    body.global-logo-active .logo img,
                    body.global-logo-active .site-header .logo img,
                    body.global-logo-active .site-branding .logo img,
                    body.global-logo-active .et_logo img,
                    body.global-logo-active .et_pb_main_logo img {
                        visibility: hidden !important;
                        opacity: 0 !important;
                    }

                    /* Prevent background-image logos from showing while keeping container visible */
                    body.global-logo-active .site-logo,
                    body.global-logo-active .logo,
                    body.global-logo-active .et_logo,
                    body.global-logo-active .et_pb_main_logo {
                        background-image: none !important;
                    }

                    /* allow our custom-logo elements to show */
                    body.global-logo-active .custom-logo-link,
                    body.global-logo-active .custom-logo,
                    body.global-logo-active .custom-logo img {
                        display: inline-block !important;
                        visibility: visible !important;
                        opacity: 1 !important;
                    }

                    /* Ensure global logo displays in theme header */
                    .custom-logo-link {
                        display: inline-block !important;
                        visibility: visible !important;
                        opacity: 1 !important;
                        z-index: 9999 !important;
                        position: relative !important;
                    }
                    
                    .custom-logo-link .custom-logo,
                    .custom-logo {
                        max-width: 100% !important;
                        height: auto !important;
                        width: auto !important;
                        display: inline-block !important;
                        visibility: visible !important;
                        opacity: 1 !important;
                        z-index: 9999 !important;
                    }
                    
                    /* Force logo image to be visible on all elements */
                    .custom-logo-link img,
                    .custom-logo img {
                        opacity: 1 !important;
                        visibility: visible !important;
                        display: inline-block !important;
                        z-index: 9999 !important;
                        max-width: 100% !important;
                        height: auto !important;
                        width: auto !important;
                    }
                    
                    /* Ensure header/branding areas display logo properly */
                    .site-header .custom-logo-link,
                    .site-header img[src*="logo"],
                    #masthead .custom-logo-link,
                    #et-header .custom-logo-link,
                    .site-branding .custom-logo-link {
                        display: inline-block !important;
                        max-width: 300px !important;
                        opacity: 1 !important;
                        visibility: visible !important;
                        z-index: 9999 !important;
                    }
                    
                    /* Extra theme specific logo selectors */
                    #et-header .custom-logo,
                    #et-header img[src*="logo"],
                    .et_logo img,
                    .et_pb_main_logo img {
                        opacity: 1 !important;
                        visibility: visible !important;
                        display: inline-block !important;
                        z-index: 9999 !important;
                    }
                    
                    .et_logo,
                    .et_pb_main_logo {
                        opacity: 1 !important;
                        visibility: visible !important;
                        display: inline-block !important;
                        z-index: 9999 !important;
                    }
                    
                    /* Ensure branding container supports logo */
                    .site-branding {
                        display: flex !important;
                        align-items: center !important;
                        visibility: visible !important;
                        overflow: visible !important;
                    }
                    
                    /* Extra theme header overflow fix - ensure logo is not clipped */
                    #et-header {
                        overflow: visible !important;
                    }
                    
                    .et-fixed-header #et-header {
                        overflow: visible !important;
                    }
                    
                    /* Remove any clips or masks that might hide logo */
                    .custom-logo-link,
                    .custom-logo-link img,
                    .site-header,
                    #et-header {
                        clip: auto !important;
                        clip-path: none !important;
                        -webkit-clip-path: none !important;
                    }
                    
                    /* Ensure no pointer-events:none blocking clicks */
                    .custom-logo-link {
                        pointer-events: auto !important;
                    }
                    
                    /* Force display even if theme tries to hide it */
                    img[src*="logo.svg"] {
                        opacity: 1 !important;
                        visibility: visible !important;
                        display: inline-block !important;
                    }
                </style>';
            }
        }
        // Add menu styling for proper alignment
        echo '<style type="text/css">
            /* Global Menu - Header alignment and centering */
            
            /* DIVI/Extra Theme Specific - Center navigation items */
            #et-navigation,
            #et-top-navigation {
                -webkit-box-align: center !important;
                -ms-flex-align: center !important;
                align-items: center !important;
            }
            
            /* Hide footer menu completely */
            #footer-menu,
            ul#footer-menu,
            .bottom-nav,
            ul.bottom-nav {
                display: none !important;
                visibility: hidden !important;
            }
            
            /* Hide footer menus with Global Menu class */
            footer .menu,
            footer .nav-menu,
            footer nav,
            .site-footer .menu,
            .site-footer .nav-menu,
            .footer-navigation {
                display: none !important;
            }
            
            /* Header navigation container */
            header nav,
            .site-header nav,
            .header-navigation,
            .main-navigation,
            .primary-navigation,
            #site-navigation,
            .global-menu-container {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 100% !important;
            }
            
            /* Menu UL elements - horizontal layout with vertical centering */
            header .menu,
            header .nav-menu,
            header ul.menu,
            header ul.nav-menu,
            .site-header .menu,
            .site-header .nav-menu,
            .main-navigation .menu,
            .primary-navigation .menu,
            #site-navigation .menu,
            #site-navigation ul,
            #et-navigation ul,
            #et-top-navigation ul {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                justify-content: center !important;
                margin: 0 auto !important;
                padding: 0 !important;
                list-style: none !important;
                height: auto !important;
            }
            
            /* Menu list items - inline with vertical centering */
            header .menu > li,
            header .nav-menu > li,
            .site-header .menu > li,
            .main-navigation .menu > li,
            .primary-navigation .menu > li,
            #site-navigation .menu > li,
            #et-navigation .menu > li,
            #et-top-navigation .menu > li {
                display: inline-flex !important;
                align-items: center !important;
                margin: 0 !important;
                padding: 0 !important;
                float: none !important;
            }
            
            /* Menu links - centered text with proper padding */
            header .menu a,
            header .nav-menu a,
            .site-header .menu a,
            .main-navigation .menu a,
            .primary-navigation .menu a,
            #site-navigation .menu a,
            #et-navigation .menu a,
            #et-top-navigation .menu a {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                padding: 12px 20px !important;
                text-align: center !important;
                line-height: 1.5 !important;
                height: auto !important;
            }
            
            /* Remove any absolute positioning that might interfere */
            header .menu,
            header .nav-menu,
            .site-header .menu,
            .main-navigation .menu,
            #et-navigation .menu,
            #et-top-navigation .menu {
                position: relative !important;
                top: auto !important;
                bottom: auto !important;
                left: auto !important;
                right: auto !important;
            }

            /* HIDE EXTRA ELEMENTS (Search, Social, Hamburger extras) REQUESTED BY USER */
            #et_top_search,
            .et_search_outer,
            #et-search-icon,
            .et-search-form,
            .et_close_search_field,
            #et-social-icons,
            .et-social-icons,
            #et-info,
            .search-button,
            .search-field,
            .et_pb_search_form,
            form.search-form {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                width: 0 !important;
                height: 0 !important;
                pointer-events: none !important;
            }

            /* Hide the separate "dropdown" container for mobile that often holds these widgets in Extra/Divi */
            /* Be careful not to hide #mobile_menu which holds the links */
            .et_mobile_menu .search-form,
            .et_mobile_menu .et-social-icons {
                display: none !important;
            }
        </style>';
    }
    
    
    /**
     * Physically sync the Global Menu to the client site
     * This handles creation, population, and assignment
     */
    public function sync_global_menu_to_client() {
        if (!is_multisite() || get_current_blog_id() == 1) {
            return;
        }

        // Run sync once to ensure mobile menus are assigned (V3 fix)
        $synced = get_option('orabooks_menu_synced_v3');
        if (!$synced) {
            $this->sync_menus_to_current_site();
            update_option('orabooks_menu_synced_v3', time());
        }
    }
    
    private function sync_to_all_sites() {
        if (!is_multisite()) {
            return;
        }
        
        // This function ensures menu items are physically added to each site's menu
        $menu_items = get_site_option($this->menu_items_option, array());
        $sites = get_sites(array('number' => 0));
        
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            
            // Get menu locations
            $locations = get_nav_menu_locations();
            
            // Find all relevant menus (Primary + Mobile)
            $target_menu_ids = array();
            
            // Check common menu location names including mobile
            $location_names = array(
                'primary', 'main', 'header', 'main-menu', 'header-menu', 'navigation', 'top', 'top-menu', 'site-header', 'site-navigation', 'primary-menu', 'main-nav',
                'mobile', 'mobile-menu', 'mobile-navigation', 'handheld', 'responsive', 'off-canvas'
            );
            
            foreach ($location_names as $loc_name) {
                if (isset($locations[$loc_name]) && $locations[$loc_name]) {
                    $target_menu_ids[] = $locations[$loc_name];
                }
            }
            $target_menu_ids = array_unique($target_menu_ids);
            
            // If no menu location found, try to find or create a menu
            if (empty($target_menu_ids)) {
                // Try to find existing menu with common names
                $menu_names = array('Primary Menu', 'Main Menu', 'Header Menu', 'Navigation', 'Menu');
                foreach ($menu_names as $menu_name) {
                    $menu = wp_get_nav_menu_object($menu_name);
                    if ($menu) {
                        $target_menu_ids[] = $menu->term_id;
                        break;
                    }
                }
                
                // If still no menu, create one for client sites
                if (empty($target_menu_ids) && $site->blog_id > 1) {
                    $menu_id = wp_create_nav_menu('Primary Menu');
                    if (!is_wp_error($menu_id)) {
                        $target_menu_ids[] = $menu_id;
                        
                        // Register menu location if theme supports it
                        if (!isset($locations['primary'])) {
                            // Try to assign to primary location
                            $locations['primary'] = $menu_id;
                            set_theme_mod('nav_menu_locations', $locations);
                        }
                    }
                }
            }
            
            // Add global menu items to all target menus
            if (!empty($target_menu_ids)) {
                foreach ($target_menu_ids as $menu_id) {
                    $menu = wp_get_nav_menu_object($menu_id);
                    if ($menu) {
                        // Remove existing global menu items
                        $existing_items = wp_get_nav_menu_items($menu->term_id);
                        if ($existing_items) {
                            foreach ($existing_items as $item) {
                                if (isset($item->ID) && (strpos((string)$item->ID, '1000000') === 0 || 
                                    (isset($item->classes) && is_array($item->classes) && in_array('global-menu-item', $item->classes)))) {
                                    wp_delete_post($item->ID, true);
                                }
                            }
                        }
                        
                        // Add current global menu items
                        foreach ($menu_items as $item_data) {
                            // Prepare classes as a space-separated string
                            $classes_value = isset($item_data['classes']) ? $item_data['classes'] : '';
                            $classes_array = !empty($classes_value) ? array_filter(explode(' ', $classes_value)) : array();
                            $classes_array[] = 'global-menu-item';
                            $classes_string = implode(' ', array_filter($classes_array));
                            
                            wp_update_nav_menu_item($menu->term_id, 0, array(
                                'menu-item-title' => $item_data['title'],
                                'menu-item-url' => $item_data['url'],
                                'menu-item-target' => $item_data['target'],
                                'menu-item-classes' => $classes_string,
                                'menu-item-status' => 'publish',
                                'menu-item-type' => 'custom'
                            ));
                        }
                    }
                }
            }
            
            restore_current_blog();
        }
    }
    
    private function remove_from_all_sites($specific_item_id = null) {
        if (!is_multisite()) {
            return;
        }
        
        $sites = get_sites(array('number' => 0));
        
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            
            $locations = get_nav_menu_locations();
            $primary_menu_id = isset($locations['primary']) ? $locations['primary'] : 0;
            
            if ($primary_menu_id) {
                $menu_items = wp_get_nav_menu_items($primary_menu_id);
                
                if ($menu_items) {
                    foreach ($menu_items as $item) {
                        if (strpos((string)$item->ID, '1000000') === 0) {
                            wp_delete_post($item->ID, true);
                        }
                    }
                }
            }
            
            restore_current_blog();
        }
    }
    
    /**
     * Hook: When a new site is initialized
     */
    public function on_new_site_created($site) {
        if (!is_multisite() || !$site) {
            return;
        }
        
        // Sync global menu items to the new site
        switch_to_blog($site->blog_id);
        $this->sync_menus_to_current_site();
        restore_current_blog();
    }
    
    /**
     * Hook: Legacy hook for new blog creation (for backward compatibility)
     */
    public function on_new_blog_created($blog_id, $user_id, $domain, $path, $site_id, $meta) {
        if (!is_multisite() || !$blog_id) {
            return;
        }
        
        // Sync global menu items to the new blog
        switch_to_blog($blog_id);
        $this->sync_menus_to_current_site();
        restore_current_blog();
    }
    
    /**
     * Sync menus to the current site
     */
    private function sync_menus_to_current_site() {
        $menu_items = get_site_option($this->menu_items_option, array());
        if (empty($menu_items)) {
            return;
        }
        
        // Get menu locations
        $locations = get_nav_menu_locations();
        $primary_menu_id = 0;
        $primary_location = null;
        
        // Check common menu location names
        $location_names = array(
            'primary', 'main', 'header', 'main-menu', 'header-menu', 'navigation', 'top', 'top-menu', 'site-header', 'site-navigation', 'primary-menu', 'main-nav',
            'mobile', 'mobile-menu', 'mobile-navigation', 'handheld', 'responsive', 'off-canvas'
        );
        
        $target_menu_ids = array();
        
        foreach ($location_names as $loc_name) {
            if (isset($locations[$loc_name]) && $locations[$loc_name]) {
                $target_menu_ids[] = $locations[$loc_name];
            }
        }
        $target_menu_ids = array_unique($target_menu_ids);
        
        // Create menu if it doesn't exist
        if (empty($target_menu_ids)) {
            $menu_id = wp_create_nav_menu('Primary Menu');
            if (!is_wp_error($menu_id)) {
                $target_menu_ids[] = $menu_id;
                
                // Try to assign to primary location
                if (!isset($locations['primary'])) {
                    $locations['primary'] = $menu_id;
                    set_theme_mod('nav_menu_locations', $locations);
                }
            }
        }
        
        // Add global menu items
        if (!empty($target_menu_ids)) {
            foreach ($target_menu_ids as $menu_id) {
                $menu = wp_get_nav_menu_object($menu_id);
                if ($menu) {
                    // Remove existing global menu items
                    $existing_items = wp_get_nav_menu_items($menu->term_id);
                    if ($existing_items) {
                        foreach ($existing_items as $item) {
                            if (isset($item->ID) && (strpos((string)$item->ID, '1000000') === 0 || 
                                (isset($item->classes) && is_array($item->classes) && in_array('global-menu-item', $item->classes)))) {
                                wp_delete_post($item->ID, true);
                            }
                        }
                    }
                    
                    // Add current global menu items
                    foreach ($menu_items as $item_data) {
                        // Prepare classes as a space-separated string (WordPress expects string, not array)
                        $classes_value = isset($item_data['classes']) ? $item_data['classes'] : '';
                        $classes_array = !empty($classes_value) ? array_filter(explode(' ', $classes_value)) : array();
                        $classes_array[] = 'global-menu-item';
                        $classes_string = implode(' ', array_filter($classes_array));
                        
                        wp_update_nav_menu_item($menu->term_id, 0, array(
                            'menu-item-title' => $item_data['title'],
                            'menu-item-url' => $item_data['url'],
                            'menu-item-target' => $item_data['target'],
                            'menu-item-classes' => $classes_string,
                            'menu-item-status' => 'publish',
                            'menu-item-type' => 'custom'
                        ));
                    }
                }
            }
        }
    }
}

// Initialize the plugin
MultisiteGlobalMenu::get_instance();
