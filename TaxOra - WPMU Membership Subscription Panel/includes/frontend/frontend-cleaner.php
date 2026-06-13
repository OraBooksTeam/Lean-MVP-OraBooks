<?php
/**
 * Frontend Cleaner
 * Hides WP Admin Bar and standard menu bars on client sites and addon frontends.
 */

if (!defined('ABSPATH')) exit;

/**
 * Redirect client logout to client landing page
 * MODIFIED: Skip if wp-frontend-dashboard plugin is active
 */
add_action('wp_logout', 'orabooks_client_logout_redirect');
function orabooks_client_logout_redirect() {
    // Restored legacy logout redirect

    
    if (is_multisite() && get_current_blog_id() != 1) {
        wp_redirect(home_url());
        exit;
    }
}

/**
 * Redirect client login to main site login
 * SECURITY: All subdomain logins go through main site login for security validation
 * Then AccessManager.handleLoginRedirect() ensures proper routing based on user role
 */
add_action('login_form_login', 'orabooks_client_login_redirect', 5);
function orabooks_client_login_redirect() {
    if (is_multisite() && get_current_blog_id() != 1) {
        // When accessed from a subdomain, redirect to main site login
        // Get the intended redirect (usually their subdomain dashboard)
        $redirect_to = isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : home_url('/dashboard/');
        $main_site_login_url = network_site_url('login/?redirect_to=' . urlencode($redirect_to));
        wp_redirect($main_site_login_url);
        exit;
    }
}

/**
 * Redirect client registration to main site registration or dashboard
 * MODIFIED: Use main site registration for client sites
 */
add_action('login_form_register', 'orabooks_client_registration_redirect');
add_action('before_signup_form', 'orabooks_client_registration_redirect');
function orabooks_client_registration_redirect() {
    if (is_multisite() && get_current_blog_id() != 1) {
        // If logged in, go to dashboard
        if (is_user_logged_in()) {
            wp_redirect(home_url('/dashboard/'));
            exit;
        }

        // If logged out, go to main site registration
        $main_site_register_url = network_site_url('wp-signup.php');
        wp_redirect($main_site_register_url);
        exit;
    }
}

/**
 * Filter login URL on client sites to point to main site login
 * MODIFIED: Return wp-frontend-dashboard login if active, otherwise use main site login
 */
add_filter('login_url', 'orabooks_client_login_url', 10, 3);
function orabooks_client_login_url($login_url, $redirect, $force_reauth) {
    if (is_multisite() && get_current_blog_id() != 1) {

        
        if (empty($redirect)) {
            $redirect = home_url('/dashboard/');
        }

        return add_query_arg('redirect_to', $redirect, home_url('/login/'));
    }
    return $login_url;
}

/**
 * Add no-sidebar classes to body to help themes adjust layout
 */
add_filter('body_class', 'orabooks_add_no_sidebar_body_classes', 999);
function orabooks_add_no_sidebar_body_classes($classes) {
    // Exclude activation page from body class modifications
    global $pagenow;
    if ($pagenow === 'wp-activate.php' || strpos((string)$_SERVER['REQUEST_URI'], 'wp-activate.php') !== false) {
        return $classes;
    }

    // Only apply on client sites or workspace frontends
    $is_client_site = (get_current_blog_id() != 1);
    $is_workspace = defined('ORABOOKS_WORKSPACE_REQUEST') && ORABOOKS_WORKSPACE_REQUEST;
    $is_addon_frontend = isset($_GET['feature']) || isset($_GET['orabooks_feature']);

    if (!$is_client_site && !$is_workspace && !$is_addon_frontend) {
        return $classes;
    }

    // Skip for network administrators
    if (current_user_can('manage_network')) {
        return $classes;
    }

    // Standard no-sidebar classes
    $classes[] = 'no-sidebar';
    $classes[] = 'full-width';
    $classes[] = 'content-full-width';
    
    // Astra
    $classes[] = 'ast-no-sidebar';
    $classes[] = 'ast-full-width-layout';
    
    // GeneratePress
    $classes[] = 'no-sidebar';
    $classes[] = 'full-width-content';
    
    // OceanWP
    $classes[] = 'full-width';
    
    // Storefront
    $classes[] = 'page-template-template-fullwidth-php';
    
    // Divi / Extra
    $classes[] = 'et_full_width_page';
    $classes[] = 'et_no_sidebar';
    
    return array_unique($classes);
}

/**
 * Hide the WordPress Admin Bar on the frontend for non-network administrators
 * MODIFIED: Skip if wp-frontend-dashboard plugin is active (it handles this)
 */
add_filter('show_admin_bar', 'orabooks_hide_admin_bar_on_frontend', 999);

function orabooks_hide_admin_bar_on_frontend($show) {
    // Only apply on frontend

    
    // Only apply on frontend
    if (is_admin()) {
        return $show;
    }

    // Hide for all logged in users on frontend (both main site and client sites)
    if (is_user_logged_in()) {
        return false;
    }

    // Existing logic for client sites, workspaces, and addons
    $is_client_site = (get_current_blog_id() != 1);
    $is_workspace = defined('ORABOOKS_WORKSPACE_REQUEST') && ORABOOKS_WORKSPACE_REQUEST;
    $is_addon_frontend = isset($_GET['feature']) || isset($_GET['orabooks_feature']);

    if ($is_client_site || $is_workspace || $is_addon_frontend) {
        return false;
    }

    return $show;
}

/**
 * Hide standard WP menu bars and toolbars via CSS
 */
add_action('wp_head', 'orabooks_hide_frontend_elements_css', 999);
add_action('admin_head', 'orabooks_hide_frontend_elements_css_admin', 999);

function orabooks_hide_frontend_elements_css() {
    // Only apply on frontend
    if (is_admin()) {
        return;
    }

    // Exclude activation page from any CSS modifications
    global $pagenow;
    if ($pagenow === 'wp-activate.php' || strpos((string)$_SERVER['REQUEST_URI'], 'wp-activate.php') !== false) {
        return;
    }

    // Only apply on client sites, workspace frontends, or for logged-in users on main site
    $is_client_site = (get_current_blog_id() != 1);
    $is_workspace = defined('ORABOOKS_WORKSPACE_REQUEST') && ORABOOKS_WORKSPACE_REQUEST;
    $is_addon_frontend = isset($_GET['feature']) || isset($_GET['orabooks_feature']);
    $is_logged_in = is_user_logged_in();

    // CRITICAL FIX: Don't apply aggressive CSS on main site to preserve theme
    if (!$is_client_site && !$is_workspace && !$is_addon_frontend) {
        return;
    }

    ?>
    <style type="text/css">
        /* Hide WP Admin Bar */
        #wpadminbar, 
        .admin-bar #wpadminbar { 
            display: none !important; 
        }
        
        /* Adjust page top margin if admin bar was expected */
        html { 
            margin-top: 0 !important; 
        }
        * html body { 
            margin-top: 0 !important; 
        }
        
        @media screen and (max-width: 600px) {
            html { margin-top: 0 !important; }
            #wpadminbar { display: none !important; }
        }

        /* Hide standard WP Navigation/Menu bars that themes often output */
        /* Only hide these if we want a completely empty header, but user wants standard header */
        /* .wp-block-navigation, .main-navigation, etc removed to restore header */

        /* Sidebars as requested for client theme */
        #secondary,
        .sidebar,
        .widget-area,
        aside,
        .right-sidebar,
        .sidebar-primary,
        .sidebar-secondary,
        /* Divi / Extra specific sidebar classes */
        .et_pb_extra_column_sidebar,
        #sidebar,
        .et_pb_widget_area_sidebar,
        /* Page Titles to hide */
        .entry-title,
        .page-title,
        .post-title,
        h1.entry-title,
        .et_pb_title_container,
        .entry-header,
        .main-title,
        .page-header,
        .header-post-title-container,
        .post-header,
        .post-title-container {
            display: none !important;
        }

        /* Expand content to edge-to-edge full width */
        #primary,
        .content-area,
        .site-main,
        #main,
        .main-content,
        .content-container,
        #content,
        #page-container,
        #et-main-area,
        #main-content,
        /* Divi / Extra specific content area */
        #left-area,
        .et_pb_extra_column_main,
        .et_pb_column_3_4,
        .et_pb_column_2_3 {
            width: 100% !important;
            max-width: 100% !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            float: none !important;
            flex: 0 0 100% !important;
        }

        /* Force full width for all nested containers within content areas */
        .container-width-100, 
        #main-content .container,
        .et_pb_extra_column_main,
        .et_pb_row,
        .et_pb_section,
        /* Scoped containers to avoid stretching header */
        .site-content .container,
        .content-area .container,
        #primary .container,
        #main .container,
        #content .container,
        /* Only remove article styling within plugin containers to preserve theme */
        .orabooks-levels-container article,
        .orabooks-checkout-container article,
        .orabooks-account-container article,
        .orabooks-register-container article,
        .orabooks-features-container article,
        .orabooks-pos-container article,
        .entry-content .container,
        .post-content .container,
        .ast-article-post,
        .ast-article-single,
        .blog-entry,
        .single-post,
        .site-main article,
        .entry {
            width: 100% !important;
            max-width: 100% !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            box-sizing: border-box !important;
            border: none !important;
            box-shadow: none !important;
            background: transparent !important;
            background-color: transparent !important;
        }
        
        /* Remove any fixed width constraints from outer content wrappers */
        #et-main-area,
        #main-content {
            width: 100% !important;
            max-width: 100% !important;
            padding: 0px !important;
        }
        
        /* Remove padding from post wrap elements */
        .post-wrap,
        .post-content,
        .entry-content,
        .article-wrap,
        .content-wrapper {
            padding: 0 !important;
        }
        
        /* Fix forgot password form styling conflicts */
        .forgot-pass-form,
        .forgot-pass-form * {
            width: auto !important;
            max-width: none !important;
            padding: initial !important;
            margin: initial !important;
            box-sizing: border-box !important;
            border: initial !important;
            background: initial !important;
            box-shadow: initial !important;
        }
        
        .forgot-pass-form-group {
            margin: 10px !important;
            width: 100% !important;
        }
        
        .forgot-pass-form-group label {
            width: 100% !important;
            margin-bottom: 5px !important;
        }
        
        .forgot-pass-form-group input[type="email"] {
            width: 100% !important;
            padding: 8px !important;
            border: 1px solid #ccc !important;
            border-radius: 4px !important;
        }
        
        .forgot-pass-form-group input[type="submit"] {
            width: 100% !important;
            padding: 10px !important;
            border: 1px solid #ccc !important;
            border-radius: 4px !important;
            background: #007bff !important;
            color: white !important;
            cursor: pointer !important;
        }
        
        /* Remove sidebar separators */
        .et_pb_extra_column_main {
            border-right: none !important;
            border-left: none !important;
        }
        
        /* Astra specific full width content area */
        .ast-container:not(header .ast-container),
        .ast-separate-container .ast-article-post,
        .ast-separate-container .ast-article-single,
        .ast-plain-container .site-content .ast-container {
            max-width: 100% !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            width: 100% !important;
        }
        
        /* OceanWP specific */
        .container:not(header .container),
        #content-wrap,
        .oceanwp-content-area,
        #main #content-wrap,
        .single-post #content-wrap,
        .single-page #content-wrap {
            max-width: 100% !important;
            width: 100% !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
        }

        /* GeneratePress specific content area */
        .grid-container:not(header .grid-container),
        .separate-containers .site-main {
            max-width: 100% !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            width: 100% !important;
        }

        /* Generic row/column expansion for various builders */
        .et_pb_row .et_pb_column.et_pb_column_3_4,
        .et_pb_row .et_pb_column.et_pb_column_2_3,
        .et_pb_row .et_pb_column.et_pb_column_1_2,
        .et_pb_row .et_pb_column,
        .entry-content .et_pb_column_3_4,
        .entry-content .et_pb_column_2_3 {
            width: 100% !important;
            max-width: 100% !important;
            margin-bottom: 20px !important;
        }
        
        /* Extra specific row width */
        .et_pb_extra_row {
            width: 100% !important;
            max-width: 100% !important;
        }
        
        /* If the user wants to hide ALL standard WP menu bars, 
           we might need to be more aggressive if they are using standard themes. */
        body.orabooks-clean-frontend .site-header,
        body.orabooks-clean-frontend #masthead {
            display: none !important;
        }
    </style>
    <script type="text/javascript">
        (function() {
            // Add a class to body to allow for more targeted CSS if needed
            document.addEventListener('DOMContentLoaded', function() {
                // CRITICAL FIX: Don't add clean-frontend class on signup page to preserve theme header
                if (!window.location.pathname.includes('wp-signup.php')) {
                    document.body.classList.add('orabooks-clean-frontend');
                }
            });
        })();
    </script>
    <?php
}

/**
 * Also hide some elements in the admin if it's a client site and they are not an admin
 */
function orabooks_hide_frontend_elements_css_admin() {
    if (get_current_blog_id() == 1) {
        return;
    }

    if (current_user_can('manage_network')) {
        return;
    }

    ?>
    <style type="text/css">
        /* Hide the "Visit Site" and other WP-specific links in the admin bar for clients */
        #wp-admin-bar-site-name,
        #wp-admin-bar-updates,
        #wp-admin-bar-comments,
        #wp-admin-bar-new-content {
            display: none !important;
        }
    </style>
    <?php
}
