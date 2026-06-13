<?php
namespace WPFD\Core;

/**
 * Access Manager
 * Handles centralized login redirection and blocks unauthorized wp-admin access.
 */
class AccessManager {

    public function register() {
        // Redirection after login - priority 99999 to override ALL other plugins
        add_filter('login_redirect', [$this, 'handleLoginRedirect'], 99999, 3);

        // Block wp-admin access for all non-super-admins - priority 0 ensures this fires FIRST
        add_action('admin_init', [$this, 'handleAdminAccess'], 0);

        // Remove competing legacy hooks from TaxOra plugin if it exists
        /* add_action('init', function() {
            remove_filter('login_redirect', 'orabooks_login_redirect_filter', 999);
            remove_action('admin_init', 'orabooks_block_admin_access');
            remove_action('login_form_login', 'orabooks_client_login_redirect', 5);
            remove_filter('login_url', 'orabooks_client_login_url', 10);
            remove_action('login_form_register', 'orabooks_client_registration_redirect');
            remove_action('before_signup_form', 'orabooks_client_registration_redirect');
        }, 20); */

        // Prevent logged-in clients from lingering on wp-login.php
        add_action('login_init', [$this, 'handleLoginInit'], 0);

        // Guard with a final login redirect after successful login
        add_action('wp_login', [$this, 'handleWpLogin'], 99999, 2);

        // Remove admin bar for non-super-admins
        add_filter('show_admin_bar', [$this, 'hideAdminBar']);

        // Custom login message for access errors
        add_filter('login_message', [$this, 'handleLoginMessage']);

        // Handle logout redirection
        add_filter('logout_redirect', [$this, 'handleLogoutRedirect'], 10, 3);

        // Hook into Login Sidebar Widget specifically
        add_filter('lwws_login_redirect', [$this, 'handleLoginRedirect'], 20, 3);

        // Allow subdomain redirects
        add_filter('allowed_redirect_hosts', [$this, 'allowSubdomainRedirects']);
        
        // Make front-end login links point to the site's `/login` page so shortcode is used
        add_filter('login_url', [$this, 'filterLoginUrl'], 10, 3);
        
        // Serve a frontend `/login` route with the login widget when a static page is not present
        // Priority 0 so it runs before other plugins that may redirect (TaxOra, Router)
        add_action('template_redirect', [$this, 'serveLoginRoute'], 0);
    }

    public function serveLoginRoute() {
        if (is_admin()) return;

        $request_path = isset($_SERVER['REQUEST_URI']) ? wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
        $request_path = trim($request_path, '/');

        if ($request_path !== 'login') return;

        // If a real page with slug 'login' exists on this site, let WP handle it
        if (get_page_by_path('login')) return;

        // Otherwise, render the login widget directly
        status_header(200);
        nocache_headers();
        // Allow themes to enqueue header/footer
        get_header();
        echo do_shortcode('[login_widget]');
        get_footer();
        exit;
    }

    public function filterLoginUrl($login_url, $redirect, $force_reauth) {
        // Prefer frontend login page when not in admin context
        if (!is_admin()) {
            $redirect_part = '';
            if ($redirect) {
                $redirect_part = '?redirect_to=' . urlencode($redirect);
            }
            return home_url('/login/' . $redirect_part);
        }
        return $login_url;
    }

    /**
     * Determine where to send the user after a successful login.
     * Uses static flag to prevent redirect loops.
     */
    public function handleLoginRedirect($redirect_to, $request = '', $user = null) {
        static $already_redirecting = false;
        if ($already_redirecting) return $redirect_to;
        $already_redirecting = true;

        // Normalize cases where an integer user ID is passed as the second parameter
        // (some plugins, e.g. login-sidebar-widget, call apply_filters('lwws_login_redirect', $redirect, $user_id))
        if (is_null($user) && !empty($request)) {
            if (is_int($request) || (is_string($request) && ctype_digit($request))) {
                $user = get_user_by('id', intval($request));
                $request = '';
            } elseif ($request instanceof \WP_User) {
                // Some callers may pass the WP_User object as second arg
                $user = $request;
                $request = '';
            }
        }

        // If $user is an ID passed as third arg, convert to WP_User
        if (!is_null($user) && !($user instanceof \WP_User)) {
            if (is_int($user) || (is_string($user) && ctype_digit($user))) {
                $user = get_user_by('id', intval($user));
            }
        }

        if (is_wp_error($user) || !$user instanceof \WP_User) {
            return $redirect_to;
        }

        $user_id = intval($user->ID);
        if ($user_id <= 0) {
            return $redirect_to;
        }

        // If the user is a super admin, send them to the normal wp-admin
        if (is_super_admin($user_id)) {
            return admin_url();
        }

        // If this user can access wp-admin on this blog, send them to wp-admin
        if ($this->user_can_access_wp_admin()) {
            return admin_url();
        }

        // Respect requested redirect if it's explicitly a dashboard URL
        if (!empty($request) && strpos($request, 'dashboard') !== false) {
             error_log("AccessManager: Respecting requested redirect: $request");
             return trailingslashit($request);
        }

        $dashboard_url = $this->getUserDashboardUrl($user_id);
        error_log("AccessManager: handleLoginRedirect user $user_id. Request: $request. Dashboard: $dashboard_url");
        if ($dashboard_url) {
            return $dashboard_url;
        }

        // No suitable destination found: ensure user is logged out and show access denied on login
        wp_logout();
        error_log("AccessManager: handleLoginRedirect insufficient permissions for user $user_id");
        return add_query_arg('wpfd_error', 'insufficient_permissions', wp_login_url());
    }

    public function handleLoginInit() {
        // Security: Validate request
        $action = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : '';
        if ($action === 'logout') {
            return;
        }

        if (!is_user_logged_in()) {
            return;
        }

        if (is_super_admin()) {
            return;
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return;
        }

        $dashboard_url = $this->getUserDashboardUrl($user_id);
        if ($dashboard_url) {
            wp_redirect($dashboard_url);
            exit;
        }
    }

    public function handleLogoutRedirect($redirect_to, $requested_redirect_to, $user) {
        // For client sites (multisite subsites), redirect to landing page (home)
        if (is_multisite() && get_current_blog_id() != 1) {
            return home_url('/');
        }
        
        // For main site, redirect to frontend `/login` with dashboard redirect
        return home_url('/login/?redirect_to=' . urlencode(home_url('/dashboard/')));
    }

    public function handleWpLogin($user_login, $user) {
        static $already_redirecting = false;
        if ($already_redirecting) return;
        $already_redirecting = true;

        if (!($user instanceof \WP_User)) {
            $user = get_user_by('login', $user_login);
        }

        if (!$user) {
            return;
        }

        // Allow super admins and admin-capable users to continue to wp-admin
        if (is_super_admin($user->ID) || $this->user_can_access_wp_admin()) {
            return;
        }

        // Otherwise, send them to their frontend dashboard if available
        $dashboard_url = $this->getUserDashboardUrl($user->ID);
        error_log('AccessManager: Login redirect for user ' . $user->ID . ' to ' . $dashboard_url);
        if ($dashboard_url) {
            wp_redirect($dashboard_url);
            exit;
        }

        // Fallback: show insufficient permissions on login
        wp_logout();
        wp_redirect(add_query_arg('wpfd_error', 'insufficient_permissions', wp_login_url()));
        exit;
    }

    /**
     * Intercept wp-admin access and redirect to the appropriate frontend dashboard.
     * Prevents infinite redirects by tracking redirect count in a request flag.
     */
    public function handleAdminAccess() {
        if (defined('DOING_AJAX') && DOING_AJAX) return;
        if (defined('DOING_CRON') && DOING_CRON) return;
        if (defined('REST_REQUEST') && REST_REQUEST) return;

        // Prevent infinite redirect loops: track redirect count
        static $redirect_depth = 0;
        $redirect_depth++;
        if ($redirect_depth > 2) {
            return;
        }

        // Super admins have backend access
        if (is_super_admin()) {
            return;
        }

        // If user is logged in but not a member of the current site, redirect them to their own dashboard
        if (is_user_logged_in() && !is_user_member_of_blog()) {
            $dashboard_url = $this->getUserDashboardUrl(get_current_user_id());
            wp_redirect($dashboard_url ? $dashboard_url : network_site_url());
            exit;
        }

        // Site Editors, Authors, and Administrators need wp-admin list/edit screens.
        // BUT: Only on the main site. On subsites, we redirect them to dashboard unless it's an iframe request.
        if (is_multisite()) {
            // Allow if it's the Divi builder, an iframe request, or a specific admin action
            if (isset($_GET['wpfd_iframe']) || isset($_GET['et_fb']) || isset($_GET['vc_editable'])) {
                return;
            }

            // Otherwise, redirect to dashboard
            $dashboard_url = $this->getUserDashboardUrl(get_current_user_id());
            wp_safe_redirect($dashboard_url ? $dashboard_url : home_url('/dashboard/'));
            exit;
        }

        // Allow access to essential files
        $script = isset($_SERVER['SCRIPT_FILENAME']) ? basename(sanitize_text_field($_SERVER['SCRIPT_FILENAME'])) : '';
        if (in_array($script, ['admin-ajax.php', 'admin-post.php', 'async-upload.php'])) return;

        if (!defined('WPFD_HANDLING_ADMIN_ACCESS')) {
            define('WPFD_HANDLING_ADMIN_ACCESS', true);
        }

        // For multisite, redirect regular users on wp-admin to their client dashboard
        if (is_multisite()) {
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                if ($user_id > 0) {
                    $dashboard_url = $this->getUserDashboardUrl($user_id);
                    if ($dashboard_url) {
                        wp_safe_redirect($dashboard_url);
                        exit;
                    }
                }
            }

            $dashboard_url = home_url('/dashboard/');
            wp_safe_redirect(network_site_url('login/?redirect_to=' . urlencode($dashboard_url)));
            exit;
        }

        if (!is_user_logged_in()) return;

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return;
        }

        $dashboard_url = $this->getUserDashboardUrl($user_id);

        if ($dashboard_url) {
            wp_safe_redirect($dashboard_url);
            exit;
        }

        wp_logout();
        wp_safe_redirect(add_query_arg('wpfd_error', 'insufficient_permissions', wp_login_url()));
        exit;
    }

    /**
     * Whether the current user may use standard wp-admin screens (posts, media, etc.).
     *
     * Filter: wpfd_user_can_access_wp_admin — pass bool $allow, int $user_id; return bool.
     */
    private function user_can_access_wp_admin() {
        // Super admins always have access
        if (is_super_admin()) {
            return true;
        }

        // On subsites, only allow if they have manage_network (effectively super admin check again)
        // or if it's the main site and they have typical admin/editor roles.
        if (is_multisite() && get_current_blog_id() !== 1) {
            return false;
        }

        $user_id = get_current_user_id();
        $allow = current_user_can('manage_options')
            || current_user_can('edit_posts')
            || current_user_can('edit_pages')
            || current_user_can('upload_files')
            || current_user_can('moderate_comments')
            || current_user_can('list_users');

        return (bool) apply_filters('wpfd_user_can_access_wp_admin', $allow, $user_id);
    }

    /**
     * Helper to find the first blog where the user has manage_options capability.
     */
    private function getUserPrimaryBlog($user_id) {
        if (!is_multisite()) {
            return user_can($user_id, 'manage_options') ? (object)['userblog_id' => get_current_blog_id()] : null;
        }

        $blogs = get_blogs_of_user($user_id);
        foreach ($blogs as $blog) {
            $blog_id = isset($blog->userblog_id) ? $blog->userblog_id : (isset($blog->blog_id) ? $blog->blog_id : 0);
            if (!$blog_id) {
                continue;
            }

            // Need to switch context to correctly check capabilities for this specific subsite
            switch_to_blog($blog_id);
            $can_manage = user_can($user_id, 'manage_options');
            restore_current_blog();

            if ($can_manage) {
                return (object)['userblog_id' => $blog_id];
            }
        }

        return null;
    }

    private function getUserDashboardUrl($user_id) {
        // Security: Validate user ID
        if ($user_id <= 0) {
            return false;
        }

        if (!is_multisite()) {
            return home_url('/dashboard/');
        }

        $primary_blog_id = get_user_meta($user_id, 'primary_blog', true);
        if ($primary_blog_id && (int) $primary_blog_id !== 1) {
            return trailingslashit(get_home_url((int) $primary_blog_id)) . 'dashboard/';
        }

        $site_id = get_user_meta($user_id, 'orabooks_site_id', true);
        if ($site_id && (int) $site_id !== 1) {
            return trailingslashit(get_home_url((int) $site_id)) . 'dashboard/';
        }

        $blogs = get_blogs_of_user($user_id);
        if (!empty($blogs)) {
            foreach ($blogs as $blog) {
                $blog_id = isset($blog->userblog_id) ? $blog->userblog_id : (isset($blog->blog_id) ? $blog->blog_id : 0);
                if ($blog_id && (int) $blog_id !== 1) {
                    return trailingslashit(get_home_url($blog_id)) . 'dashboard/';
                }
            }
        }

        $subdomain = get_user_meta($user_id, 'orabooks_subdomain', true);
        if ($subdomain) {
            $main_domain = defined('DOMAIN_CURRENT_SITE') ? DOMAIN_CURRENT_SITE : parse_url(network_site_url(), PHP_URL_HOST);
            $scheme = is_ssl() ? 'https' : 'http';
            return $scheme . '://' . sanitize_text_field($subdomain) . '.' . $main_domain . '/dashboard/';
        }

        // Final fallback: check if user is a member of ANY blog other than 1
        $user_blogs = get_blogs_of_user($user_id);
        if (!empty($user_blogs)) {
            foreach ($user_blogs as $blog) {
                $bid = isset($blog->userblog_id) ? $blog->userblog_id : (isset($blog->blog_id) ? $blog->blog_id : 0);
                if ($bid && (int)$bid !== 1) {
                    return trailingslashit(get_home_url($bid)) . 'dashboard/';
                }
            }
        }

        return home_url('/dashboard/');
    }

    /**
     * Show a custom error message on the login screen if redirected back.
     */
    public function handleLoginMessage($message) {
        if (isset($_GET['wpfd_error']) && $_GET['wpfd_error'] === 'insufficient_permissions') {
            return '<div id="login_error"><strong>Access Denied</strong>: You do not have permission to access the administration dashboard.</div>' . $message;
        }
        return $message;
    }

    /**
     * Disable admin bar for non-super-admins on frontend only.
     * Keep admin bar visible in wp-admin so Divi builder button shows.
     */
    public function hideAdminBar($show) {
        if (!is_super_admin() && !is_admin()) {
            return false;
        }
        return $show;
    }

    /**
     * Allow redirects to subdomains in multisite.
     */
    public function allowSubdomainRedirects($hosts) {
        $user_id = get_current_user_id();
        if ($user_id) {
            $subdomain = get_user_meta($user_id, 'orabooks_subdomain', true);
            if ($subdomain) {
                $main_domain = defined('DOMAIN_CURRENT_SITE') ? DOMAIN_CURRENT_SITE : parse_url(network_site_url(), PHP_URL_HOST);
                $hosts[] = $subdomain . '.' . $main_domain;
            }
        }
        return $hosts;
    }
}
