<?php
// Get user by subdomain
function orabooks_get_user_by_subdomain($subdomain) {
    // Sanitize subdomain
    $subdomain = sanitize_text_field($subdomain);
    
    if (empty($subdomain)) {
        return null;
    }
    
    // Try get_users first (works in most cases)
    $users = get_users(array(
        'meta_key' => 'orabooks_subdomain',
        'meta_value' => $subdomain,
        'number' => 1,
        'meta_compare' => '='
    ));
    
    if (!empty($users) && isset($users[0])) {
        return $users[0];
    }
    
    // Fallback: Direct database query (more reliable, especially in multisite)
    global $wpdb;
    
    // In multisite, we need to check the main site's user meta
    if (is_multisite()) {
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = 'orabooks_subdomain' 
            AND meta_value = %s 
            LIMIT 1",
            $subdomain
        ));
    } else {
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = 'orabooks_subdomain' 
            AND meta_value = %s 
            LIMIT 1",
            $subdomain
        ));
    }
    
    if ($user_id) {
        return get_user_by('ID', $user_id);
    }
    
    return null;
}

/**
 * Check if a user has a WordPress site in the network
 * 
 * @param int $user_id User ID
 * @return bool Whether user has a site
 */
function orabooks_user_has_site($user_id) {
    if (empty($user_id)) {
        return false;
    }
    
    // Check if user has a stored subdomain
    $subdomain = get_user_meta($user_id, 'orabooks_subdomain', true);
    if (!empty($subdomain)) {
        return true;
    }
    
    // Check if user has a stored site ID
    $site_id = get_user_meta($user_id, 'orabooks_site_id', true);
    if (!empty($site_id)) {
        return true;
    }
    
    // Check if user is a member of any blog in multisite
    if (is_multisite() && function_exists('get_blogs_of_user')) {
        $blogs = get_blogs_of_user($user_id);
        if (!empty($blogs)) {
            // Store the first blog's info for future use
            $first_blog = reset($blogs);
            if (isset($first_blog->userblog_id)) {
                update_user_meta($user_id, 'orabooks_site_id', $first_blog->userblog_id);
            }
            return true;
        }
    }
    
    return false;
}

/**
 * Get user's site URL
 * 
 * @param int $user_id User ID
 * @return string|false Site URL or false if no site
 */
function orabooks_get_user_site_url($user_id) {
    if (empty($user_id)) {
        return false;
    }
    
    // Check for stored subdomain first
    $subdomain = get_user_meta($user_id, 'orabooks_subdomain', true);
    if (!empty($subdomain)) {
        $main_domain = defined('DOMAIN_CURRENT_SITE') ? DOMAIN_CURRENT_SITE : parse_url(home_url(), PHP_URL_HOST);
        return 'https://' . $subdomain . '.' . $main_domain;
    }
    
    // Check for stored site ID
    $site_id = get_user_meta($user_id, 'orabooks_site_id', true);
    if (!empty($site_id) && is_multisite()) {
        $site_url = get_blogaddress_by_id(intval($site_id));
        if ($site_url) {
            return $site_url;
        }
    }
    
    // Fallback: check if user has any blogs
    if (is_multisite() && function_exists('get_blogs_of_user')) {
        $blogs = get_blogs_of_user($user_id);
        if (!empty($blogs)) {
            $first_blog = reset($blogs);
            if (isset($first_blog->siteurl)) {
                return $first_blog->siteurl;
            }
        }
    }
    
    return false;
}

/**
 * Get feature access URL for the current user
 * 
 * @return string URL to access features
 */
function orabooks_get_feature_access_url() {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return home_url('/login');
    }
    
    // Check if user has a site/subdomain
    $subdomain = get_user_meta($user_id, 'orabooks_subdomain', true);
    if (!empty($subdomain)) {
        $main_domain = defined('DOMAIN_CURRENT_SITE') ? DOMAIN_CURRENT_SITE : parse_url(home_url(), PHP_URL_HOST);
        return 'https://' . $subdomain . '.' . $main_domain;
    }
    
    // Fallback to features page
    $features_page = get_page_by_path('features');
    if ($features_page) {
        return get_permalink($features_page->ID);
    }
    
    return home_url('/');
}

// Redirect subdomains to workspace page
function orabooks_handle_subdomain_redirect() {
    $current_url = (string) ($_SERVER['HTTP_HOST'] ?? '');
    
    // Get main domain using parse_url with correct constant
    $main_domain = parse_url(get_site_url(), PHP_URL_HOST);
    
    // Check if this is a subdomain request
    if ($main_domain && $current_url !== '' && strpos($current_url, $main_domain) !== false) {
        $subdomain_parts = explode('.', $current_url);
        
        // If it's a subdomain of our main domain
        if (count($subdomain_parts) > 2) {
            $subdomain = $subdomain_parts[0];
            
            // Check if this is a valid customer subdomain (not www, not admin subdomains)
            if (!in_array($subdomain, ['www', 'admin', 'api'])) {
                $user = orabooks_get_user_by_subdomain($subdomain);
                
                if ($user) {
                    // Store subdomain in session
                    OraBooks_Session::get_instance()->set('orabooks_current_subdomain', $subdomain);
                    
                    // Redirect to workspace page
                    $workspace_page = get_page_by_path('orabooks-workspace');
                    if ($workspace_page) {
                        wp_redirect(get_permalink($workspace_page->ID));
                        exit;
                    }
                }
            }
        }
    }
}
add_action('template_redirect', 'orabooks_handle_subdomain_redirect');
