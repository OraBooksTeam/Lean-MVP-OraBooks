<?php
/**
 * Configure Login Sidebar Widget Redirects
 * Sets up proper login/logout redirection for client sites
 */

if (!defined('ABSPATH')) exit;

/**
 * Set login widget redirect options for client sites
 */
function orabooks_configure_login_widget_redirects() {
    // Only run on client sites (not main site)
    if (!is_multisite() || get_current_blog_id() == 1) {
        return;
    }
    
    // Get the dashboard URL
    $dashboard_url = home_url('/dashboard/');
    
    // Get the landing page ID (should be set as front page)
    $landing_page_id = get_option('page_on_front');
    
    // Configure login redirect to dashboard page
    update_option('redirect_page_url', $dashboard_url);
    delete_option('redirect_page');
    
    // Configure logout redirect to landing page (root)
    // Don't set logout_redirect_page option - let the filter handle it with home_url('/')
    // This ensures redirect to root domain instead of /landing-page/ permalink
    
    // Clear any existing logout_redirect_page option to force filter usage
    delete_option('logout_redirect_page');
    
    // Set logout redirect URL directly to root domain
    update_option('logout_redirect_page_url', home_url('/'));
}

/**
 * Hook the configuration function to run when pages are created
 */
add_action('wpmu_new_blog', 'orabooks_configure_login_widget_redirects', 100, 6);
add_action('init', 'orabooks_configure_login_widget_redirects', 20);

/**
 * Ensure redirects are properly configured on client sites
 */
function orabooks_ensure_login_widget_redirects() {
    // Only run on client sites
    if (!is_multisite() || get_current_blog_id() == 1) {
        return;
    }
    
    // Check if login redirect is configured (logout is handled by filter)
    $login_redirect = get_option('redirect_page');
    
    // If login redirect not configured, set it up
    if (!$login_redirect) {
        orabooks_configure_login_widget_redirects();
    }
}

/**
 * Add WordPress core login_redirect filter to ensure client site users
 * are redirected to /dashboard after authentication on wp-login.php
 * 
 * This works for:
 * 1. Logging in directly on a client subdomain's wp-login.php
 * 2. Logging in on the main site's wp-login.php (with redirect_to to a subdomain)
 */
add_filter('login_redirect', 'orabooks_handle_core_login_redirect', 10, 3);
function orabooks_handle_core_login_redirect($redirect_to, $requested_redirect_to, $user) {
    // Only apply in multisite context
    if (!is_multisite()) {
        return $redirect_to;
    }
    
    // Allow super admins to go where they want
    if (is_a($user, 'WP_User') && is_super_admin($user->ID)) {
        return $redirect_to;
    }
    
    // CASE 1: We're on a client subdomain - force redirect to /dashboard
    if (get_current_blog_id() != 1) {
        // If the requested redirect_to contains /dashboard, keep it
        if (!empty($requested_redirect_to) && strpos($requested_redirect_to, '/dashboard/') !== false) {
            return $requested_redirect_to;
        }
        
        // If there's a requested redirect_to that's not wp-admin, respect it
        if (!empty($requested_redirect_to) && 
            strpos($requested_redirect_to, '/wp-admin') === false &&
            strpos($requested_redirect_to, '/wp-login') === false) {
            return $requested_redirect_to;
        }
        
        // Default: redirect to dashboard
        return home_url('/dashboard/');
    }
    
    // CASE 2: We're on the main site - check if redirect_to points to a subdomain
    if (!empty($requested_redirect_to)) {
        // If redirect_to already contains /dashboard, keep it (it was set by orabooks_client_login_redirect)
        if (strpos($requested_redirect_to, '/dashboard/') !== false) {
            return $requested_redirect_to;
        }
        
        // If redirect_to points to a subdomain (not main site), redirect to that subdomain's dashboard
        $main_site_host = parse_url(network_site_url(), PHP_URL_HOST);
        $redirect_host = parse_url($requested_redirect_to, PHP_URL_HOST);
        
        if ($redirect_host && $redirect_host !== $main_site_host) {
            // This is a redirect to a subdomain - replace the path with /dashboard
            $redirect_parts = parse_url($requested_redirect_to);
            $dashboard_url = $redirect_parts['scheme'] . '://' . $redirect_parts['host'] . '/dashboard/';
            return $dashboard_url;
        }
        
        // For main site redirects that don't point to wp-admin, respect them
        if (strpos($requested_redirect_to, '/wp-admin') === false &&
            strpos($requested_redirect_to, '/wp-login') === false) {
            return $requested_redirect_to;
        }
    }
    
    // Default main site behavior - redirect to main site dashboard if user has one
    return $redirect_to;
}

/**
 * Add filter to handle redirect_to parameter for login widget
 */
add_filter('lwws_login_redirect', 'orabooks_handle_widget_login_redirect', 10, 2);
function orabooks_handle_widget_login_redirect($redirect, $user_id) {
    // Only on client sites
    if (!is_multisite() || get_current_blog_id() == 1) {
        return $redirect;
    }
    
    // Always redirect to dashboard for client sites
    return home_url('/dashboard/');
}

/**
 * Add filter to handle logout redirect
 */
add_filter('lwws_logout_redirect', 'orabooks_handle_logout_redirect', 10, 2);
function orabooks_handle_logout_redirect($redirect, $user_id) {
    // Only on client sites
    if (!is_multisite() || get_current_blog_id() == 1) {
        return $redirect;
    }
    
    // Always redirect to landing page (root) for client sites
    // Use home_url('/') to ensure we go to root domain where landing page is set as front page
    return home_url('/');
}

/**
 * Add WordPress core logout redirect filter as backup
 * This ensures logout redirect works even if login widget filters fail
 */
add_filter('logout_redirect', 'orabooks_handle_core_logout_redirect', 10, 3);
function orabooks_handle_core_logout_redirect($redirect_url, $requested_redirect_to, $user) {
    // Only on client sites
    if (!is_multisite() || get_current_blog_id() == 1) {
        return $redirect_url;
    }
    
    // Force redirect to landing page (root) for client sites
    return home_url('/');
}

// Run the configuration immediately if we're on a client site
if (is_multisite() && get_current_blog_id() != 1) {
    orabooks_ensure_login_widget_redirects();
}
