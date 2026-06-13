<?php
/**
 * Client Homepage Redirect
 * 
 * Redirects client site homepage based on login status:
 * - Logged in: Show dashboard
 * - Logged out: Show landing page
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle client homepage display using template_include filter
 */
add_filter('template_include', 'orabooks_client_homepage_template');

function orabooks_client_homepage_template($template) {
    // Only on client sites
    if (get_current_blog_id() == 1) {
        return $template;
    }
    
    // Only on homepage
    if (!is_front_page() && !is_home()) {
        return $template;
    }
    
    // Determine which page to show based on login status
    if (is_user_logged_in()) {
        // Check if wp-frontend-dashboard plugin is active
        if (class_exists('\WPFD\Core\Plugin')) {
            // Redirect to the dashboard route
            wp_redirect(home_url('/dashboard'));
            exit;
        } else {
            // Fallback: Show dashboard page with shortcode
            $dashboard_page = get_page_by_path('dashboard');
            if ($dashboard_page && $dashboard_page->ID != get_the_ID()) {
                wp_redirect(get_permalink($dashboard_page->ID));
                exit;
            }
        }
    } else {
        // Show landing page for logged-out users
        $landing_page = null;
        $front_page_id = get_option('page_on_front');

        if ($front_page_id) {
            $front_page = get_post($front_page_id);
            if ($front_page && $front_page->post_type === 'page' && $front_page->post_status === 'publish') {
                $landing_page = $front_page;
            }
        }

        if (!$landing_page) {
            $landing_page = get_page_by_path('landing-page');
        }

        if ($landing_page) {
            global $wp_query;
            $wp_query->is_page = true;
            $wp_query->is_singular = true;
            $wp_query->is_home = false;
            $wp_query->is_front_page = true;
            $wp_query->post = $landing_page;
            $wp_query->posts = array($landing_page);
            $wp_query->queried_object = $landing_page;
            $wp_query->queried_object_id = $landing_page->ID;

            return plugin_dir_path(__FILE__) . 'client-landing-page-template.php';
        }
    }

    return $template;
}
