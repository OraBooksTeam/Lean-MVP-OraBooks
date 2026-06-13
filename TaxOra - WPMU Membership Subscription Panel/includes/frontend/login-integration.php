<?php
/**
 * Login Integration
 * Integrates custom login page with WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize login integration
 */
function orabooks_login_integration_init() {
    // Include login page display
    require_once(plugin_dir_path(__FILE__) . 'login-page-display.php');
    
    // Add rewrite rules for /login
    add_action('init', 'orabooks_login_add_rewrite_rules');
    add_filter('query_vars', 'orabooks_login_add_query_vars');
    add_action('template_redirect', 'orabooks_login_handle_template_redirect');
    
    // Handle login redirect
    add_action('template_redirect', 'orabooks_login_redirect_handler', 1);
}

/**
 * Add rewrite rules for login page
 */
function orabooks_login_add_rewrite_rules() {
    add_rewrite_rule(
        '^login/?$',
        'index.php?orabooks_login=1',
        'top'
    );
    
    // Flush rules once
    if (!get_option('orabooks_login_rules_flushed')) {
        flush_rewrite_rules();
        update_option('orabooks_login_rules_flushed', true);
    }
}

/**
 * Add query variables
 */
function orabooks_login_add_query_vars($query_vars) {
    $query_vars[] = 'orabooks_login';
    return $query_vars;
}

/**
 * Handle template redirect for login page
 * Redirects to standard WordPress wp-login.php for default styling
 */
function orabooks_login_handle_template_redirect() {
    /* if (get_query_var('orabooks_login')) {
        $redirect_to = isset($_REQUEST['redirect_to']) ? esc_url_raw($_REQUEST['redirect_to']) : home_url('/dashboard/');
        $login_url = site_url('login/?redirect_to=' . urlencode($redirect_to));
        wp_redirect($login_url);
        exit;
    } */
}

/**
 * Remove/disable login redirect handler - using default WordPress login
 */
function orabooks_login_redirect_handler() {
    // Disabled - using default WordPress login page
    return;
}

/**
 * Restored legacy login redirect filter
 */
add_filter('login_redirect', 'orabooks_login_redirect_filter', 999, 3);
function orabooks_login_redirect_filter($redirect_to, $request, $user) {
    if (is_wp_error($user) || !($user instanceof WP_User)) {
        return $redirect_to;
    }

    $dest = !empty($request) ? $request : $redirect_to;
    
    if (user_can($user, 'manage_network') && !empty($request) && strpos($request, 'wp-admin') !== false) {
        return $request;
    }

    $is_admin_dest = (strpos($dest, 'wp-admin') !== false || strpos($dest, 'profile.php') !== false || empty($dest) || $dest == admin_url() || $dest == admin_url('/', 'https'));

    if ($is_admin_dest) {
        if (is_multisite() && get_current_blog_id() == 1) {
            $subdomain = get_user_meta($user->ID, 'orabooks_subdomain', true);
            if ($subdomain) {
                $main_domain = defined('DOMAIN_CURRENT_SITE') ? DOMAIN_CURRENT_SITE : parse_url(home_url(), PHP_URL_HOST);
                $scheme = is_ssl() ? 'https' : 'http';
                return $scheme . '://' . $subdomain . '.' . $main_domain . '/dashboard/';
            }
            return home_url('/dashboard/');
        }
        return home_url('/dashboard/');
    }
    
    return $dest;
}

/**
 * Add login link to navigation menu
 */
function orabooks_add_login_menu_item($items, $args) {
    // Only add to main menu
    if ($args->theme_location !== 'primary') {
        return $items;
    }
    
    // Don't add if user is logged in
    if (is_user_logged_in()) {
        return $items;
    }
    
    // Add login menu item
    $items[] = array(
        'title' => __('Login', 'orabooks'),
        'url' => home_url('/login'),
        'classes' => array('login-menu-item')
    );
    
    return $items;
}

add_filter('wp_nav_menu_items', 'orabooks_add_login_menu_item', 10, 2);

/**
 * Create login page in WordPress admin for testing
 */
function orabooks_create_login_page() {
    // Check if page already exists
    $existing_page = get_page_by_path('orabooks-login');
    
    if ($existing_page) {
        return $existing_page->ID;
    }
    
    // Create the page
    $page_data = array(
        'post_title' => __('Login', 'orabooks'),
        'post_content' => '[login_widget]',
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_name' => 'orabooks-login'
    );
    
    $page_id = wp_insert_post($page_data);
    
    return $page_id;
}

/**
 * Initialize the login integration
 */
add_action('plugins_loaded', 'orabooks_login_integration_init');

/**
 * Flush rewrite rules on plugin activation
 */
register_activation_hook(__FILE__, function() {
    orabooks_login_add_rewrite_rules();
    flush_rewrite_rules();
});

/**
 * Clean up on plugin deactivation
 */
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
    delete_option('orabooks_login_rules_flushed');
});
/**
 * Block wp-admin access for regular users and redirect to dashboard
 * Restored from legacy backup
 */
add_action('admin_init', 'orabooks_block_admin_access', 0);
function orabooks_block_admin_access() {
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }

    $user = wp_get_current_user();
    if (!$user || $user->ID == 0) {
        return;
    }

    // Allow network admins
    if (user_can($user, 'manage_network')) {
        return;
    }

    // Redirect to dashboard if trying to access wp-admin
    if (is_admin() && !current_user_can('manage_options')) {
         $redirect_to = orabooks_login_redirect_filter('', '', $user);
         if ($redirect_to && strpos($redirect_to, 'wp-admin') === false) {
             wp_redirect($redirect_to);
             exit;
         } else {
             wp_redirect(home_url('/dashboard/'));
             exit;
         }
    }
}
?>
