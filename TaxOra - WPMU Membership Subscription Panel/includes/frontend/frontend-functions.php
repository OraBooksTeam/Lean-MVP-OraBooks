<?php
// CRITICAL: Intercept subdomain requests BEFORE WordPress multisite redirects to wp-signup.php
// This must run very early, before WordPress checks if the site exists
add_action('muplugins_loaded', 'orabooks_intercept_subdomain_early', 1);

/**
 * Restrict logged-in clients with subdomains from visiting the main site.
 * They are redirected back to their workspace subdomain until they logout.
 */
add_action('template_redirect', 'orabooks_restrict_main_site_for_clients', 0);
function orabooks_restrict_main_site_for_clients() {
    // Only run on frontend
    if (is_admin()) {
        return;
    }

    if (!is_user_logged_in()) {
        return;
    }

    // Get main domain
    $main_domain = defined('DOMAIN_CURRENT_SITE') ? DOMAIN_CURRENT_SITE : parse_url(home_url(), PHP_URL_HOST);
    $http_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

    // Check if we are on the main domain (not a subdomain)
    $is_main_site = ($http_host === $main_domain || $http_host === 'www.' . $main_domain);
    
    if ($is_main_site) {
        $user_id = get_current_user_id();
        
        // Skip for administrators
        if (current_user_can('manage_options')) {
            return;
        }
        
        $subdomain = get_user_meta($user_id, 'orabooks_subdomain', true);
        
        if (!empty($subdomain)) {
            // Whitelist: Allow logout
            if (isset($_GET['orabooks_action']) && $_GET['orabooks_action'] === 'logout') {
                return;
            }
            
            // Allow logout redirect
            if (isset($_GET['action']) && $_GET['action'] === 'logout') {
                return;
            }

            // Redirect to their workspace, preserving the path
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $workspace_url = 'https://' . $subdomain . '.' . $main_domain . $request_uri;
            wp_redirect($workspace_url);
            exit;
        }
    }
}

/**
 * Redirect /pricing to /upgrade-plan on client sites
 */
add_action('template_redirect', 'orabooks_redirect_pricing_to_upgrade_plan');
function orabooks_redirect_pricing_to_upgrade_plan() {
    if (is_multisite() && get_current_blog_id() != 1) {
        global $wp;
        if (isset($wp->request) && $wp->request === 'pricing') {
            wp_redirect(home_url('/upgrade-plan'));
            exit;
        }
    }
}

function orabooks_intercept_subdomain_early() {
    // Only run on frontend
    if (is_admin()) {
        return;
    }
    
    $http_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    
    // Get main domain
    $main_domain = defined('DOMAIN_CURRENT_SITE') ? DOMAIN_CURRENT_SITE : parse_url(home_url(), PHP_URL_HOST);
    
    // Check if this is a subdomain request
    if (!empty($main_domain) && !empty($http_host) && $http_host !== $main_domain) {
        $host_parts = explode('.', $http_host);
        $main_domain_parts = explode('.', $main_domain);
        
        // If it's a subdomain (has more parts than main domain)
        if (count($host_parts) > count($main_domain_parts)) {
            $subdomain_parts = array_slice($host_parts, 0, count($host_parts) - count($main_domain_parts));
            $subdomain = implode('.', $subdomain_parts);
            
            // Skip system subdomains
            if (in_array($subdomain, ['www', 'admin', 'api', 'mail', 'ftp'])) {
                return;
            }
            
            // ONLY intercept if this is a workspace/feature request
            // Check for /workspace/ in URL or ?feature= parameter
            $is_workspace_request = (strpos($request_uri, '/workspace') !== false) || 
                                   (isset($_GET['feature']) && !empty($_GET['feature']));
            
            if (!$is_workspace_request) {
                // This is a normal page request on the client site
                // Let WordPress handle it normally (show theme, menu, logo, etc.)
                return;
            }
            
            // Check if this subdomain belongs to a user (not a WordPress site)
            global $wpdb;
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} 
                WHERE meta_key = 'orabooks_subdomain' 
                AND meta_value = %s 
                LIMIT 1",
                $subdomain
            ));
            
            if ($user_id) {
                // This is a user workspace subdomain, not a WordPress site
                // Prevent WordPress from redirecting to wp-signup.php
                // We'll handle this in our routing function
                
                // Set a flag so WordPress doesn't try to redirect
                define('ORABOOKS_WORKSPACE_REQUEST', true);
                define('ORABOOKS_WORKSPACE_SUBDOMAIN', $subdomain);
                define('ORABOOKS_WORKSPACE_USER_ID', $user_id);
                
                // Hook into WordPress to prevent signup redirect
                add_filter('pre_option_ms_files_rewriting', '__return_zero', 999);
                
                // We'll handle the actual routing in the later hooks
                return;
            }
        }
    }
}

// Improved workspace routing with GET parameter support
// Run early to catch workspace URLs before 404 handling
add_action('template_redirect', 'orabooks_handle_workspace_routing', 1);
add_action('wp', 'orabooks_handle_workspace_routing', 1);
function orabooks_handle_workspace_routing() {
    // PRIORITY: Check if this was flagged as a workspace request in early interception
    if (defined('ORABOOKS_WORKSPACE_REQUEST') && ORABOOKS_WORKSPACE_REQUEST) {
        $subdomain = defined('ORABOOKS_WORKSPACE_SUBDOMAIN') ? ORABOOKS_WORKSPACE_SUBDOMAIN : '';
        $user_id = defined('ORABOOKS_WORKSPACE_USER_ID') ? ORABOOKS_WORKSPACE_USER_ID : 0;
        
        if (!empty($subdomain) && $user_id) {
            // Get the path from the request
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $current_path = parse_url($request_uri, PHP_URL_PATH);
            
            // Handle /workspace/ path or root
            $path = '/';
            if (strpos((string)$current_path, '/workspace') === 0) {
                $path = str_replace('/workspace', '', (string)$current_path);
                if (empty($path)) {
                    $path = '/';
                }
            } else {
                $path = $current_path;
            }
            
            // Load workspace immediately
            if (function_exists('orabooks_load_workspace')) {
                orabooks_load_workspace($subdomain, $path);
                exit;
            }
        }
    }
    
    // Check if this is a workspace request
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $current_path = parse_url($request_uri, PHP_URL_PATH);
    $http_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    
    // Get main domain
    $main_domain = parse_url(home_url(), PHP_URL_HOST);
    
    // METHOD 1: SUBDOMAIN-BASED ROUTING (Priority - handles https://clientusername.fundsme.xyz/workspace/?feature=accounting)
    if (!empty($main_domain) && !empty($http_host) && $http_host !== $main_domain) {
        // Extract subdomain from host
        $host_parts = explode('.', $http_host);
        
        // Check if this is a subdomain (has more parts than main domain)
        $main_domain_parts = explode('.', $main_domain);
        if (count($host_parts) > count($main_domain_parts)) {
            // Get the subdomain part (everything before the main domain)
            $subdomain_parts = array_slice($host_parts, 0, count($host_parts) - count($main_domain_parts));
            $subdomain = implode('.', $subdomain_parts);
            
            // Clean subdomain (remove www, admin, api, etc.)
            if (!in_array($subdomain, ['www', 'admin', 'api', 'mail', 'ftp'])) {
                // Check if function exists before calling
                if (function_exists('orabooks_get_user_by_subdomain')) {
                    $user = orabooks_get_user_by_subdomain($subdomain);
                    
                    if ($user) {
                        // Check if this is a workspace request
                        $is_workspace_request = (strpos($current_path, '/workspace') !== false) || 
                                               (isset($_GET['feature']) && !empty($_GET['feature']));
                        
                        if (!$is_workspace_request) {
                            return; // Let WordPress handle normal site pages
                        }

                        // Extract path from URL
                        $path = $current_path;
                        
                        // Get WordPress installation path
                        $wp_install_path = parse_url(home_url(), PHP_URL_PATH);
                        if (!empty($wp_install_path) && $wp_install_path !== '/') {
                            // Remove WordPress path from current path
                            $path = str_replace($wp_install_path, '/', $path);
                        }
                        
                        // Handle /workspace/ path or root
                        if (strpos((string)$path, '/workspace') === 0) {
                            $path = str_replace('/workspace', '', (string)$path);
                            if (empty($path)) {
                                $path = '/';
                            }
                        }
                        
                        // Load workspace with subdomain
                        if (function_exists('orabooks_load_workspace')) {
                            orabooks_load_workspace($subdomain, $path);
                            exit;
                        }
                    }
                }
            }
        }
    }
    
    // METHOD 2: SUBFOLDER-BASED ROUTING (Fallback - for backward compatibility)
    // Get WordPress installation path (e.g., /orabooks/ or /)
    $wp_install_path = parse_url(home_url(), PHP_URL_PATH);
    if (empty($wp_install_path) || $wp_install_path === '/') {
        $wp_install_path = '';
    } else {
        // Ensure it ends with / for proper matching
        $wp_install_path = rtrim($wp_install_path, '/') . '/';
    }
    
    // Skip if not a workspace URL
    if (strpos($current_path, '/workspace/') === false && !isset($_GET['orabooks_workspace'])) {
        return;
    }
    
    // Method 1: Check for GET parameters from rewrite rules
    if (isset($_GET['orabooks_workspace']) && !empty($_GET['orabooks_workspace'])) {
        $subdomain = sanitize_text_field($_GET['orabooks_workspace']);
        $path = isset($_GET['orabooks_workspace_path']) ? '/' . sanitize_text_field($_GET['orabooks_workspace_path']) : '/';
        orabooks_load_workspace($subdomain, $path);
        exit;
    }
    
    // Method 2: Direct path parsing (works even if rewrite rules aren't flushed)
    // Handle URLs like /workspace/client-orabook-s-VP6G/ or /orabooks/workspace/client-admin-6R5u/?feature=accounting
    // Pattern accounts for WordPress subdirectory installation
    $workspace_pattern = '#^' . preg_quote($wp_install_path, '#') . 'workspace/([a-z0-9-]+)(/.*)?$#';
    if (preg_match($workspace_pattern, $current_path, $matches)) {
        $subdomain = $matches[1];
        $path = isset($matches[2]) && !empty($matches[2]) ? $matches[2] : '/';
        if (function_exists('orabooks_load_workspace')) {
            orabooks_load_workspace($subdomain, $path);
            exit;
        }
    }
    
    // Method 3: Fallback - check if URL contains /workspace/ in the path
    // This handles cases where the URL might have been modified by other plugins
    if (strpos($current_path, '/workspace/') !== false) {
        $parts = explode('/workspace/', $current_path, 2);
        if (isset($parts[1])) {
            $workspace_part = $parts[1];
            // Remove query string if present
            $workspace_part = strtok($workspace_part, '?');
            // Remove trailing slash
            $workspace_part = rtrim($workspace_part, '/');
            $workspace_parts = explode('/', $workspace_part, 2);
            $subdomain = sanitize_text_field($workspace_parts[0]);
            $path = isset($workspace_parts[1]) ? '/' . $workspace_parts[1] : '/';
            
            if (!empty($subdomain) && function_exists('orabooks_load_workspace')) {
                orabooks_load_workspace($subdomain, $path);
                exit;
            }
        }
    }
}

function orabooks_load_workspace($subdomain, $path = '/') {
    // Get user by subdomain - use function from subdomain-functions.php
    if (!function_exists('orabooks_get_user_by_subdomain')) {
        wp_die('Subdomain functions not loaded properly.');
    }
    
    $user = orabooks_get_user_by_subdomain($subdomain);
    
    if (!$user) {
        status_header(404);
        
        // Enhanced error message with debugging info (only for admins)
        $error_message = 'Invalid workspace: ' . esc_html($subdomain) . '. Please check your URL.';
        
        // Add debugging info for administrators
        if (current_user_can('manage_options') && defined('WP_DEBUG') && WP_DEBUG) {
            global $wpdb;
            // Check if any users have subdomains
            $all_subdomains = $wpdb->get_results(
                "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'orabooks_subdomain' LIMIT 10"
            );
            
            $error_message .= '<br><br><strong>Debug Info (Admin Only):</strong><br>';
            $error_message .= 'Looking for subdomain: <code>' . esc_html($subdomain) . '</code><br>';
            
            if (!empty($all_subdomains)) {
                $error_message .= 'Found ' . count($all_subdomains) . ' user(s) with subdomains:<br>';
                foreach ($all_subdomains as $subdomain_data) {
                    $error_message .= '- User ID ' . $subdomain_data->user_id . ': <code>' . esc_html($subdomain_data->meta_value) . '</code><br>';
                }
            } else {
                $error_message .= 'No users found with subdomain meta key.';
            }
        }
        
        wp_die($error_message);
    }

    // Set current user for this request
    wp_set_current_user($user->ID);
    
    // Store in session
    OraBooks_Session::get_instance()->set('orabooks_current_subdomain', $subdomain);
    OraBooks_Session::get_instance()->set('orabooks_current_user_id', $user->ID);
    
    // Check if user has access to features
    $user_level = get_user_meta($user->ID, 'orabooks_level', true);
    if (!$user_level) {
        status_header(403);
        wp_die('You do not have an active membership. Please subscribe to access features.');
    }
    
    
    // Handle specific feature paths
    $feature = isset($_GET['feature']) ? sanitize_text_field($_GET['feature']) : '';
    
    
    if (strpos($path, '/accounting') === 0 || $feature === 'accounting') {
        // Debug logging
        error_log('OraBooks Workspace: Accounting feature requested by user ' . $user->ID);
        
        orabooks_output_workspace_html($subdomain, 'Accounting', function() {
            if (shortcode_exists('orabooks_accounting')) {
                echo do_shortcode('[orabooks_accounting]');
            } else {
                echo '<div class="orabooks-error">';
                echo '<h3>Accounting Error</h3>';
                echo '<p>The accounting system is not available. Please ensure the plugin is activated.</p>';
                echo '</div>';
            }
        }, 'orabooks-accounting');
        exit;
    }
    
    // Handle inventory feature
    if (strpos($path, '/inventory') === 0 || $feature === 'inventory') {
        error_log('OraBooks Workspace: Inventory feature requested by user ' . $user->ID);
        
        orabooks_output_workspace_html($subdomain, 'Inventory', function() {
            if (shortcode_exists('orabooks_inventory')) {
                // If the shortcode exists but returns empty (like inventory does), 
                // we might need to include the template directly or trigger its logic
                if (defined('FRONTEND_INVENTORY_PATH')) {
                    include FRONTEND_INVENTORY_PATH . 'templates/dashboard.php';
                } else {
                    echo do_shortcode('[orabooks_inventory]');
                }
            } else {
                echo '<div class="orabooks-error">';
                echo '<h3>Inventory Error</h3>';
                echo '<p>The inventory system is not available. Please ensure the plugin is activated.</p>';
                echo '</div>';
            }
        }, 'orabooks-inventory');
        exit;
    }
    
    // Handle other feature paths
    if (strpos($path, '/crm') === 0 || $feature === 'crm') {
        status_header(501);
        wp_die('CRM feature coming soon!');
    }
    
    if (strpos($path, '/projects') === 0 || $feature === 'projects') {
        status_header(501);
        wp_die('Project Management feature coming soon!');
    }
    
    if (strpos($path, '/storage') === 0 || $feature === 'storage') {
        status_header(501);
        wp_die('File Storage feature coming soon!');
    }
    
    // Handle root workspace path - show features dashboard
    if ($path === '/' || $path === '' || $path === '/index.php') {
        if (function_exists('orabooks_features_shortcode')) {
            orabooks_output_workspace_html($subdomain, 'Workspace', function() {
                echo orabooks_features_shortcode();
            }, 'orabooks-workspace');
            exit;
        } else {
            status_header(503);
            wp_die('Features system not available.');
        }
    }
    
    // If we get here and path is not '/', show not found
    if ($path !== '/') {
        status_header(404);
        wp_die('Feature not found: ' . esc_html($path) . '. Please check the URL or contact support.');
    }
}

// Helper function to output proper HTML for workspace
function orabooks_output_workspace_html($subdomain, $title_suffix, $content_callback, $extra_body_class = '') {
    // Avoid manipulating output buffers to prevent zlib compression notices
    
    // Set proper headers
    // Content-Type is set by WordPress; avoid overriding to prevent compression issues
    status_header(200);
    
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html($subdomain); ?> - <?php echo esc_html($title_suffix); ?> - <?php bloginfo('name'); ?></title>
        
        <!-- Load WordPress scripts and styles -->
        <?php 
        // Load WordPress head
        wp_head();
        
        // Ensure jQuery is loaded
        if (!wp_script_is('jquery', 'done')) {
            wp_enqueue_script('jquery');
            wp_print_scripts('jquery');
        }
        ?>
        
        <style>
        /* Basic workspace styles */
        .orabooks-workspace-wrapper {
            min-height: 100vh;
            background: #f8f9fa;
        }
        .orabooks-workspace-header {
            background: white;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
        .orabooks-workspace-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        </style>
    </head>
    <?php 
        $classes = get_body_class();
        if (!empty($extra_body_class)) {
            $classes[] = $extra_body_class;
        }
    ?>
    <body class="<?php echo esc_attr(implode(' ', $classes)); ?>">
        <div class="orabooks-workspace-wrapper">
            <div class="orabooks-workspace-header">
                <div class="orabooks-workspace-content">
                    <h1 style="margin: 0; color: #43a62d;">
                        <?php echo esc_html($subdomain); ?> Workspace
                        <small style="color: #6c757d; font-size: 16px;">- <?php echo esc_html($title_suffix); ?></small>
                    </h1>
                </div>
            </div>
            <div class="orabooks-workspace-content">
                <?php call_user_func($content_callback); ?>
            </div>
        </div>
        
        <?php 
        // Load WordPress footer scripts
        wp_footer(); 
        ?>
        
        <script>
        // Ensure jQuery is available
        if (typeof jQuery === 'undefined' && typeof window.$ === 'function') {
            window.jQuery = window.$;
        }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Add rewrite rules for workspace URLs
add_action('init', 'orabooks_add_workspace_rewrite_rules');
function orabooks_add_workspace_rewrite_rules() {
    // Custom login redirect rule: /login/redirect_to=...
    add_rewrite_rule(
        '^login/redirect_to=(.+)/?$',
        'index.php?pagename=login&redirect_to=$matches[1]',
        'top'
    );
    
    add_rewrite_rule(
        '^workspace/([a-z0-9-]+)/?$',
        'index.php?orabooks_workspace=$matches[1]',
        'top'
    );
    add_rewrite_rule(
        '^workspace/([a-z0-9-]+)/(.+)/?$',
        'index.php?orabooks_workspace=$matches[1]&orabooks_workspace_path=$matches[2]',
        'top'
    );
}

// Flush rewrite rules when plugin is activated or when admin visits settings
add_action('admin_init', 'orabooks_maybe_flush_rewrite_rules');
function orabooks_maybe_flush_rewrite_rules() {
    // Check if user manually requested flush
    if (isset($_GET['orabooks_flush_rewrites']) && current_user_can('manage_options')) {
        check_admin_referer('orabooks_flush_rewrites');
        flush_rewrite_rules(false);
        update_option('orabooks_flush_rewrite_rules', '1');
        wp_redirect(remove_query_arg('orabooks_flush_rewrites'));
        exit;
    }
    
    // Only flush if we're on an admin page and option is not set
    if (is_admin() && get_option('orabooks_flush_rewrite_rules') !== '1') {
        flush_rewrite_rules(false);
        update_option('orabooks_flush_rewrite_rules', '1');
    }
}

// Add admin notice for flushing rewrite rules if needed
// add_action('admin_notices', 'orabooks_rewrite_rules_notice');
function orabooks_rewrite_rules_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Only show on OraBooks admin pages
    $screen = get_current_screen();
    if (!$screen || strpos((string)$screen->base, 'orabooks-membership') === false) {
        return;
    }
    
    $flush_url = wp_nonce_url(
        add_query_arg('orabooks_flush_rewrites', '1'),
        'orabooks_flush_rewrites'
    );
    
    echo '<div class="notice notice-info is-dismissible">';
    echo '<p><strong>OraBooks:</strong> If workspace URLs are not working, ';
    echo '<a href="' . esc_url($flush_url) . '">click here to flush rewrite rules</a>.';
    echo '</p>';
    echo '</div>';
}

// Force flush rewrite rules on plugin activation
// This will be called from the main plugin file's activation hook
add_action('orabooks_plugin_activated', 'orabooks_flush_rewrite_on_activation');
function orabooks_flush_rewrite_on_activation() {
    orabooks_add_workspace_rewrite_rules();
    flush_rewrite_rules();
    update_option('orabooks_flush_rewrite_rules', '1');
}

// Register query variables
add_filter('query_vars', 'orabooks_register_workspace_query_vars');
function orabooks_register_workspace_query_vars($vars) {
    $vars[] = 'orabooks_workspace';
    $vars[] = 'orabooks_workspace_path';
    $vars[] = 'redirect_to';
    return $vars;
}

/**
 * JS fallback removed - handled by login-integration.php
 */
add_action('wp_head', 'orabooks_debug_routing');
function orabooks_debug_routing() {
    if (isset($_GET['debug_workspace'])) {
        echo '<!-- Workspace Debug: ';
        echo 'GET: ' . print_r($_GET, true);
        echo 'URI: ' . $_SERVER['REQUEST_URI'];
        echo 'Current User ID: ' . get_current_user_id();
        echo ' -->';
    }
}

// Frontend menu integration
add_filter( 'wp_nav_menu_items', 'orabooks_conditional_menu_items', 10, 2 );
function orabooks_conditional_menu_items( $items, $args ) {
    $items = (string) $items;
    // Only add menu items if explicitly enabled in settings
    $auto_menu = get_option( 'orabooks_auto_menu', '0' );
    if ( is_multisite() && get_current_blog_id() != 1 ) {
        return $items;
    }
    
    if ( ! $auto_menu ) {
        return $items;
    }
    
    // Check if items already contain orabooks links to avoid duplicates
    if ( $items !== '' && strpos( $items, 'orabooks-link' ) !== false ) {
        return $items;
    }
    
    $levels_page = orabooks_get_page_by_title( 'Orabooks Pricing' );
    $account_page = orabooks_get_page_by_title( 'Orabooks My Account' );
    $login_page = orabooks_get_page_by_title( 'Login' );
    $register_page = orabooks_get_page_by_title( 'Orabooks Register' );
    
    $append = '';
    $is_user_logged_in = is_user_logged_in();
    
    // Pricing as standalone menu item (always show)
    if ( $levels_page ) {
        $append .= '<li class="menu-item orabooks-link"><a href="' . esc_url( get_permalink( $levels_page->ID ) ) . '">Pricing</a></li>';
    }
    
    // Show different items based on login status
    if ( $is_user_logged_in ) {
        // When logged in: Show My Account and Logout only
        if ( $account_page ) {
            $append .= '<li class="menu-item orabooks-link"><a href="' . esc_url( get_permalink( $account_page->ID ) ) . '">My Account</a></li>';
        }
        $append .= '<li class="menu-item orabooks-link"><a href="' . esc_url( wp_logout_url( home_url() ) ) . '">Logout</a></li>';
    } else {
        // When logged out: Show Login and Register only
        if ( $login_page ) {
            $append .= '<li class="menu-item orabooks-link"><a href="' . esc_url( get_permalink( $login_page->ID ) ) . '">Login</a></li>';
        }
        if ( $register_page ) {
            $append .= '<li class="menu-item orabooks-link"><a href="' . esc_url( network_site_url('wp-signup.php') ) . '">Register</a></li>';
        }
    }
    
    return $items . $append;
}

// Add feature access button to confirmation page
// Feature access button confirmation removed by request
// Original content was here (orabooks_add_feature_access_to_confirmation)

// User registration hooks for external integrations
add_action( 'orabooks_user_registered', 'orabooks_handle_new_user_integration', 10, 2 );
function orabooks_handle_new_user_integration( $user_id, $user_data ) {
    do_action( 'orabooks_external_user_registered', $user_id, $user_data );
}

// Membership level change hooks
add_action( 'orabooks_membership_level_changed', 'orabooks_handle_level_change_integration', 10, 3 );
function orabooks_handle_level_change_integration( $user_id, $old_level_id, $new_level_id ) {
    do_action( 'orabooks_external_level_changed', $user_id, $old_level_id, $new_level_id );
}

// Helper function to generate subdomain-based workspace URL
function orabooks_get_workspace_url( $subdomain, $path = '/', $feature = '' ) {
    if (empty($subdomain)) {
        return home_url('/workspace/');
    }
    
    $main_domain = parse_url(home_url(), PHP_URL_HOST);
    
    // Build URL: https://subdomain.domain.com/workspace/?feature=key
    $url = 'https://' . $subdomain . '.' . $main_domain . '/workspace/';
    
    if (!empty($feature)) {
        $url .= '?feature=' . urlencode($feature);
    } elseif ($path !== '/' && !empty($path)) {
        $url .= ltrim($path, '/');
    }
    
    return $url;
}

// Utility function to check if user can access specific feature
function orabooks_can_access_feature( $user_id, $feature_key ) {
    if (!function_exists('orabooks_user_has_feature_access')) {
        return !empty(get_user_meta($user_id, 'orabooks_level', true));
    }
    return orabooks_user_has_feature_access( $user_id, $feature_key );
}

// Get user's subscription details for external use
function orabooks_get_user_subscription_details( $user_id ) {
    global $wpdb;
    
    $level_id = get_user_meta( $user_id, 'orabooks_level', true );
    if ( ! $level_id ) {
        return null;
    }
    
    if (!function_exists('orabooks_get_level')) {
        return null;
    }
    
    $level = orabooks_get_level( $level_id );
    $subscription = $wpdb->get_row( $wpdb->prepare( 
        "SELECT * FROM {$wpdb->prefix}orabooks_subscriptions WHERE user_id = %d AND status = 'active' ORDER BY started_at DESC LIMIT 1", 
        $user_id 
    ) );
    
    if ( ! $level || ! $subscription ) {
        return null;
    }
    
    return array(
        'level' => $level,
        'subscription' => $subscription,
        'features' => function_exists('orabooks_get_user_features') ? orabooks_get_user_features( $user_id ) : array()
    );
}

// Helper function to check if current user can access current workspace
function orabooks_can_access_current_workspace() {
    $sess = OraBooks_Session::get_instance();
    $current_subdomain = $sess->get('orabooks_current_subdomain', '');
    $current_user_id = $sess->get('orabooks_current_user_id', 0);
    
    if (empty($current_subdomain) || $current_user_id != get_current_user_id()) {
        return false;
    }
    
    $user_subdomain = get_user_meta(get_current_user_id(), 'orabooks_subdomain', true);
    return $user_subdomain === $current_subdomain;
}
