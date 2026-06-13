<?php
// Workspace setup AJAX
add_action( 'wp_ajax_orabooks_setup_workspace', 'orabooks_ajax_setup_workspace' );
function orabooks_ajax_setup_workspace() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'Not logged in' );
    }

    check_ajax_referer( 'orabooks_setup_workspace', 'nonce' );

    $user_id = get_current_user_id();
    $user = get_userdata( $user_id );
    
    // Check if already has subdomain
    $existing_subdomain = get_user_meta( $user_id, 'orabooks_subdomain', true );
    if ( ! empty( $existing_subdomain ) ) {
        wp_send_json_error( 'Workspace already setup' );
    }

    // Generate subdomain
    $subdomain_base = get_option( 'orabooks_subdomain_base', 'client' );
    $username_clean = sanitize_title( $user->user_login );
    $subdomain = $subdomain_base . '-' . $username_clean . '-' . wp_generate_password( 4, false );
    
    // Create subdomain via API if configured
    $api_endpoint = get_option( 'orabooks_api_endpoint' );
    $api_key = get_option( 'orabooks_api_key' );
    
    if ( ! empty( $api_endpoint ) && ! empty( $api_key ) ) {
        $response = orabooks_create_subdomain_via_api( $subdomain, $user );
        
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }
    }
    
    // Create multisite site if this is a multisite installation and function exists
    $site_id = null;
    $site_creation_error = null;
    
    if (is_multisite() && function_exists('wpmu_create_blog')) {
        try {
            // Determine domain and path based on installation type
            $network = get_network();
            $domain = $subdomain . '.' . preg_replace('|^www\.|', '', $network->domain);
            $path = $network->path;
            
            // Check if subdomain install or subdirectory
            if (!is_subdomain_install()) {
                $domain = $network->domain;
                $path = $network->path . $subdomain . '/';
            }
            
            // Check if site already exists
            if (!domain_exists($domain, $path)) {
                $site_title = $user->display_name . "'s Workspace";
                
                // Create the multisite site
                $site_id = wpmu_create_blog(
                    $domain,
                    $path,
                    $site_title,
                    $user_id,
                    array('public' => 1),
                    get_current_network_id()
                );
                
                if (is_wp_error($site_id)) {
                    $site_creation_error = $site_id->get_error_message();
                    $site_id = null; // Continue anyway - workspace routing doesn't require multisite site
                }
            }
        } catch (Exception $e) {
            $site_creation_error = $e->getMessage();
            // Continue anyway - workspace can work without multisite site
        }
    }
    
    // Save subdomain to user meta (this is the critical part)
    update_user_meta( $user_id, 'orabooks_subdomain', $subdomain );
    update_user_meta( $user_id, 'orabooks_workspace_setup', current_time( 'mysql' ) );
    if ($site_id) {
        update_user_meta( $user_id, 'orabooks_site_id', $site_id );
    }
    
    // Send welcome email (optional, don't fail if this errors)
    if (function_exists('orabooks_send_workspace_welcome_email')) {
        @orabooks_send_workspace_welcome_email($user, $subdomain);
    }
    
    // Always send success response since subdomain is saved
    $response_data = array(
        'message' => 'Workspace setup completed successfully! Your subdomain has been created.',
        'subdomain' => $subdomain
    );
    
    if ($site_id) {
        $response_data['site_id'] = $site_id;
    }
    
    if ($site_creation_error) {
        $response_data['warning'] = 'Site creation had an issue, but your workspace is still available: ' . $site_creation_error;
    }
    
    wp_send_json_success($response_data);
}

function orabooks_create_subdomain_via_api( $subdomain, $user ) {
    $api_endpoint = get_option( 'orabooks_api_endpoint' );
    $api_key = get_option( 'orabooks_api_key' );
    
    $data = array(
        'subdomain' => $subdomain,
        'full_domain' => $subdomain . '.e-nest.net', // ADD THIS
        'user_data' => array(
            'user_id' => $user->ID,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'username' => $user->user_login
        ),
        'timestamp' => current_time( 'mysql' )
    );
    
    $args = array(
        'body' => json_encode( $data ),
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ),
        'timeout' => 30
    );
    
    $response = wp_remote_post( $api_endpoint, $args );
    
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    
    $body = wp_remote_retrieve_body( $response );
    $result = json_decode( $body, true );
    
    if ( $result['success'] !== true ) {
        return new WP_Error( 'api_error', $result['message'] ?? 'Unknown API error' );
    }
    
    return $result;
}

// Send welcome email with workspace details - FIXED VERSION
function orabooks_send_workspace_welcome_email( $user, $subdomain ) {
    $to = $user->user_email;
    $subject = 'Your Workspace is Ready!';
    
    $features_config = get_option( 'orabooks_features_config', array() );
    $user_level = get_user_meta( $user->ID, 'orabooks_level', true );
    $available_features = orabooks_get_available_features();
    
    $features_list = '';
    $user_features = orabooks_get_user_features( $user->ID );
    
    foreach ( $user_features as $feature ) {
        if ( isset( $available_features[$feature->feature_key] ) ) {
            $feature_data = $available_features[$feature->feature_key];
            $feature_path = $features_config[$feature->feature_key]['subdomain_path'] ?? '/'.$feature->feature_key;
            
            // FIX: Add .e-nest.net to the URL
            $features_list .= '- ' . $feature_data['name'] . ': https://' . $subdomain . '.e-nest.net' . $feature_path . "\n";
        }
    }
    
    $message = "
Hello {$user->display_name},

Your dedicated workspace has been set up successfully!

Your Workspace URL: https://{$subdomain}.e-nest.net

Available Features:
{$features_list}

You can access all your features from your account dashboard:
" . home_url( '/orabooks-my-account' ) . "

Thank you for choosing our service!

Best regards,
The Orabooks Team
";
    
    $from = get_option( 'orabooks_from_email', get_option( 'admin_email' ) );
    $headers = array( 'From: Orabooks <' . $from . '>' );
    
    wp_mail( $to, $subject, $message, $headers );
}

// Automatically setup workspace after successful payment - FIXED VERSION
add_action( 'orabooks_order_completed', 'orabooks_auto_setup_workspace', 20, 2 );
function orabooks_auto_setup_workspace( $order_row_id, $order_row ) {
    $user_id = $order_row->user_id;
    
    // Check if user already has workspace
    $existing_subdomain = get_user_meta( $user_id, 'orabooks_subdomain', true );
    if ( empty( $existing_subdomain ) ) {
        // Trigger workspace setup
        $user = get_userdata( $user_id );
        $subdomain_base = get_option( 'orabooks_subdomain_base', 'client' );
        $username_clean = sanitize_title( $user->user_login );
        $subdomain = $subdomain_base . '-' . $username_clean . '-' . wp_generate_password( 4, false );
        
        // Try to create via API if configured
        $api_endpoint = get_option( 'orabooks_api_endpoint' );
        $api_key = get_option( 'orabooks_api_key' );
        
        if ( ! empty( $api_endpoint ) && ! empty( $api_key ) ) {
            orabooks_create_subdomain_via_api( $subdomain, $user );
        }
        
        // Create multisite site if this is a multisite installation
        $site_id = null;
        if (is_multisite() && function_exists('wpmu_create_blog')) {
            try {
                // Determine domain and path based on installation type
                $network = get_network();
                $domain = $subdomain . '.' . preg_replace('|^www\.|', '', $network->domain);
                $path = $network->path;
                
                // Check if subdomain install or subdirectory
                if (!is_subdomain_install()) {
                    $domain = $network->domain;
                    $path = $network->path . $subdomain . '/';
                }
                
                // Check if site already exists
                if (!domain_exists($domain, $path)) {
                    $site_title = $user->display_name . "'s Workspace";
                    
                    // Create the multisite site
                    $site_id = wpmu_create_blog(
                        $domain,
                        $path,
                        $site_title,
                        $user_id,
                        array('public' => 1),
                        get_current_network_id()
                    );
                    
                    if (!is_wp_error($site_id)) {
                        update_user_meta( $user_id, 'orabooks_site_id', $site_id );
                    }
                }
            } catch (Exception $e) {
                // Log error but continue - workspace can work without multisite site
                error_log('Orabooks: Failed to create multisite site for user ' . $user_id . ': ' . $e->getMessage());
            }
        }
        
        // Save subdomain
        update_user_meta( $user_id, 'orabooks_subdomain', $subdomain );
        update_user_meta( $user_id, 'orabooks_workspace_setup', current_time( 'mysql' ) );
        
        // Send welcome email WITH CORRECT DOMAIN
        orabooks_send_workspace_welcome_email( $user, $subdomain );
    }
}

// Feature access check for other plugins
add_action( 'wp_ajax_orabooks_check_feature_access', 'orabooks_ajax_check_feature_access' );
add_action( 'wp_ajax_nopriv_orabooks_check_feature_access', 'orabooks_ajax_check_feature_access' );
function orabooks_ajax_check_feature_access() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'Not logged in' );
    }
    
    check_ajax_referer( 'orabooks-frontend-nonce', 'nonce' );
    
    $user_id = get_current_user_id();
    $feature_key = sanitize_text_field( $_POST['feature_key'] ?? '' );
    
    if ( empty( $feature_key ) ) {
        wp_send_json_error( 'Feature key required' );
    }
    
    $has_access = orabooks_user_has_feature_access( $user_id, $feature_key );
    
    wp_send_json_success( array(
        'has_access' => $has_access,
        'feature_key' => $feature_key
    ) );
}

// Get user features for external plugins
add_action( 'wp_ajax_orabooks_get_user_features', 'orabooks_ajax_get_user_features' );
function orabooks_ajax_get_user_features() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'Not logged in' );
    }
    
    check_ajax_referer( 'orabooks-frontend-nonce', 'nonce' );
    
    $user_id = get_current_user_id();
    $features = orabooks_get_user_features( $user_id );
    $feature_data = array();
    
    foreach ( $features as $feature ) {
        $feature_data[] = array(
            'key' => $feature->feature_key,
            'name' => $feature->feature_name,
            'access_type' => $feature->access_type,
            'settings' => maybe_unserialize( $feature->settings )
        );
    }
    
    wp_send_json_success( array(
        'features' => $feature_data
    ) );
}

/**
 * AJAX handler to set landing page for a client site
 */
add_action('wp_ajax_orabooks_set_landing_page', 'orabooks_ajax_set_landing_page');
function orabooks_ajax_set_landing_page() {
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
    }

    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'orabooks_set_landing_page')) {
        wp_send_json_error('Security check failed');
    }

    $user_id = get_current_user_id();
    $user_site_id = get_user_meta($user_id, 'orabooks_site_id', true);

    if (!$user_site_id) {
        wp_send_json_error('Site not found');
    }

    // Get selected page ID
    $page_id = intval($_POST['page_id'] ?? 0);

    // Switch to the user's site
    switch_to_blog($user_site_id);

    // Validate page exists (if not 0 for default)
    if ($page_id > 0) {
        $page = get_post($page_id);
        if (!$page || $page->post_type !== 'page' || $page->post_status !== 'publish') {
            restore_current_blog();
            wp_send_json_error('Invalid page selected');
        }
    }

    // Set the landing page
    if ($page_id > 0) {
        // Set to show a specific page as front page
        update_option('show_on_front', 'page');
        update_option('page_on_front', $page_id);
        delete_option('page_for_posts');
    } else {
        // Set to show default landing page
        update_option('show_on_front', 'page');
        $landing_page = get_page_by_path('landing-page');
        if ($landing_page) {
            update_option('page_on_front', $landing_page->ID);
        } else {
            update_option('show_on_front', 'posts');
        }
    }

    // Flush rewrite rules to ensure proper display
    flush_rewrite_rules();

    restore_current_blog();

    wp_send_json_success(array(
        'message' => 'Landing page updated successfully'
    ));
}