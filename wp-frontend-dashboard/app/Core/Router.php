<?php
namespace WPFD\Core;

class Router {
    public function register() {
        add_action('init', function () {
            // Dashboard catch-all
            add_rewrite_rule('dashboard(/.*)?$', 'index.php?wpfd_route=dashboard', 'top');
            
            // Flush rewrite rules on activation to ensure they work
            if (get_option('wpfd_rewrite_rules_flushed') !== '1.0.3') {
                flush_rewrite_rules();
                update_option('wpfd_rewrite_rules_flushed', '1.0.3');
            }
        });

        add_filter('query_vars', function ($vars) {
            $vars[] = 'wpfd_route';
            return $vars;
        });

        // Robust detection for virtual routes (handles both subdomain and subdirectory)
        add_filter('request', function ($query_vars) {
            // Security: Validate input
            if (empty($query_vars) || !is_array($query_vars)) {
                return $query_vars;
            }
            
            // Priority 1: WordPress query vars (if rewrite rules are active)
            if (isset($query_vars['pagename'])) {
                $pagename = sanitize_text_field($query_vars['pagename']);
                if (in_array($pagename, ['dashboard'])) {
                    $query_vars['wpfd_route'] = $pagename;
                    return $query_vars;
                } elseif (strpos($pagename, 'dashboard/') === 0) {
                    $query_vars['wpfd_route'] = 'dashboard';
                    return $query_vars;
                }
            }
            
            // Priority 2: Direct URI detection (fallback if rewrite rules are bypassed or not flushed)
            if (isset($_SERVER['REQUEST_URI'])) {
                $home_path = parse_url(home_url(), PHP_URL_PATH) ?: '';
                $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '';
                
                // Get path relative to the site's home path
                $relative_path = $request_uri;
                if ($home_path && $home_path !== '/' && strpos($request_uri, $home_path) === 0) {
                    $relative_path = substr($request_uri, strlen($home_path));
                }
                $relative_path = trim(sanitize_text_field($relative_path), '/');

                if ($relative_path === 'dashboard' || strpos($relative_path, 'dashboard/') === 0) {
                    $query_vars['wpfd_route'] = 'dashboard';
                }
            }

            return $query_vars;
        });

        add_action('template_redirect', function () {
            $route = get_query_var('wpfd_route');
            if (!$route) return;

            // Security: Validate route
            $route = sanitize_text_field($route);
            if (!in_array($route, ['dashboard'])) {
                return;
            }

            // Prevent direct access to dashboard if not logged in, not a member, or not an administrator
            if ($route === 'dashboard') {
                $dashboard_url = home_url('/dashboard');

                if (!is_user_logged_in()) {
                    $redirect_url = network_site_url('login/?redirect_to=' . urlencode($dashboard_url));
                    wp_safe_redirect($redirect_url);
                    exit;
                }
                
                // Multisite: Check membership and prevent superadmin access on subdomains
                if (is_multisite()) {
                    $user_id = get_current_user_id();
                    $current_blog_id = get_current_blog_id();
                    
                    // CRITICAL: Prevent superadmins from accessing /dashboard on non-main sites
                    if (is_super_admin($user_id) && $current_blog_id !== 1) {
                        // Superadmins should access wp-admin on main site, not dashboard on subdomains
                        wp_safe_redirect(network_site_url('wp-admin/'));
                        exit;
                    }
                    
                    // CRITICAL: Verify user is the OWNER of this subdomain (not just a member)
                    $user_site_id = get_user_meta($user_id, 'orabooks_site_id', true);
                    if ($user_site_id && (int)$user_site_id !== (int)$current_blog_id) {
                        // User is a member of this blog but doesn't own this subdomain
                        // This prevents accessing other client's dashboards
                        wp_logout();
                        $redirect_url = network_site_url('login/?redirect_to=' . urlencode($dashboard_url));
                        wp_safe_redirect($redirect_url);
                        exit;
                    }
                    
                    // Check blog membership - if user is not a member of this blog, deny access
                    if (!is_user_member_of_blog($user_id, $current_blog_id)) {
                        wp_logout();
                        // Redirect to frontend login instead of showing access denied page
                        $redirect_url = network_site_url('login/?redirect_to=' . urlencode($dashboard_url));
                        wp_safe_redirect($redirect_url);
                        exit;
                    }
                }
                // Single site: Allow any logged-in user
            }

            // Stop WordPress from redirecting to wp-login.php
            remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);

            // Load the WordPress native template
            if (!headers_sent()) {
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
                header('Expires: 0');
                status_header(200);
            }
            
            // Use TemplateRenderer to render dashboard
            if (class_exists('WPFD\Core\TemplateRenderer')) {
                $template_renderer = new \WPFD\Core\TemplateRenderer();
                $template_renderer->render_dashboard();
            } else {
                wp_die(__('Dashboard template renderer not available.', 'wp-frontend-dashboard'));
            }
            exit;
        }, 1);

        // Disable canonical redirect for our routes
        add_filter('redirect_canonical', function ($redirect_url, $requested_url) {
            if (get_query_var('wpfd_route')) {
                return false;
            }
            return $redirect_url;
        }, 10, 2);
    }
}
