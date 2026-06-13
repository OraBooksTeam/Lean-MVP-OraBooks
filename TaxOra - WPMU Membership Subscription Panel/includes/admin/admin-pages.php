<?php

// Limited Access Manager Page
function orabooks_limited_access_manager_page() {
    if (!current_user_can('manage_options')) return;
    
    include TAXORA_MEMBERSHIP_DIR . 'templates/admin/limited-access-manager.php';
}

// Dashboard Page
function orabooks_dashboard_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    
    orabooks_admin_tabs( 'dashboard' );
    include TAXORA_MEMBERSHIP_DIR . 'templates/admin/dashboard.php';
}

// Groups Page
function orabooks_groups_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    
    orabooks_admin_tabs( 'groups' );
    include TAXORA_MEMBERSHIP_DIR . 'templates/admin/groups.php';
}

// Members Page
function orabooks_members_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    
    orabooks_admin_tabs( 'members' );
    include TAXORA_MEMBERSHIP_DIR . 'templates/admin/members.php';
}

// Subscribers Page
function orabooks_subscribers_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    
    orabooks_admin_tabs( 'subscribers' );
    include TAXORA_MEMBERSHIP_DIR . 'templates/admin/subscribers.php';
}

// Levels Page
function orabooks_levels_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    
    orabooks_admin_tabs( 'levels' );
    include TAXORA_MEMBERSHIP_DIR . 'templates/admin/levels.php';
}

// Orders Page
function orabooks_orders_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    
    orabooks_admin_tabs( 'orders' );
    include TAXORA_MEMBERSHIP_DIR . 'templates/admin/orders.php';
}

// Reports Page
function orabooks_reports_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    
    orabooks_admin_tabs( 'reports' );
    include TAXORA_MEMBERSHIP_DIR . 'templates/admin/reports.php';
}

// Settings Page
function orabooks_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    
    orabooks_admin_tabs( 'settings' );
    include TAXORA_MEMBERSHIP_DIR . 'templates/admin/settings.php';
}

// Handle Settings Save (Post-Redirect-Get)
add_action('admin_post_orabooks_save_settings', 'orabooks_handle_settings_save');
function orabooks_handle_settings_save() {
    // Verify nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'orabooks_save_settings')) {
        wp_die('Security check failed');
    }
    
    // Check permission
    if (!current_user_can('manage_options')) {
        wp_die('Permission denied');
    }
    
    // Store redirect URL early to ensure we always redirect
    $redirect_url = admin_url('admin.php?page=orabooks-membership-settings&settings-updated=true');
    
    try {
        // Currency Settings - Allow user customization
        if (isset($_POST['currency_code'])) {
            update_option('orabooks_currency_code', sanitize_text_field($_POST['currency_code']));
        }
        if (isset($_POST['currency_name'])) {
            update_option('orabooks_currency_name', sanitize_text_field($_POST['currency_name']));
        }
        if (isset($_POST['currency_symbol'])) {
            update_option('orabooks_currency_symbol', sanitize_text_field($_POST['currency_symbol']));
        }
        if (isset($_POST['currency_position'])) {
            update_option('orabooks_currency_position', sanitize_text_field($_POST['currency_position']));
        }
        if (isset($_POST['currency_decimals'])) {
            update_option('orabooks_currency_decimals', absint($_POST['currency_decimals']));
        }
        if (isset($_POST['currency_thousands_separator'])) {
            update_option('orabooks_currency_thousands_separator', sanitize_text_field($_POST['currency_thousands_separator']));
        }
        
        // Page Settings - use null coalescing to prevent undefined index warnings
        update_option('orabooks_pricing_page_id', intval($_POST['pricing_page_id'] ?? 0));
        update_option('orabooks_checkout_page_id', intval($_POST['checkout_page_id'] ?? 0));
        update_option('orabooks_account_page_id', intval($_POST['account_page_id'] ?? 0));
        update_option('orabooks_features_page_id', intval($_POST['features_page_id'] ?? 0));
        update_option('orabooks_confirmation_page_id', intval($_POST['confirmation_page_id'] ?? 0));
        update_option('orabooks_register_page_id', intval($_POST['register_page_id'] ?? 0));
        update_option('orabooks_login_page_id', intval($_POST['login_page_id'] ?? 0));
        
        // Email Settings
        update_option('orabooks_from_email', sanitize_email($_POST['from_email'] ?? ''));
        update_option('orabooks_from_name', sanitize_text_field($_POST['from_name'] ?? ''));
        
        // Payment Settings (network-wide on multisite — main site)
        if (function_exists('orabooks_save_payment_settings_from_post')) {
            orabooks_save_payment_settings_from_post($_POST);
        }

        // Security Settings (ReCAPTCHA)
        orabooks_update_membership_option('orabooks_recaptcha_site_key', sanitize_text_field($_POST['recaptcha_site_key'] ?? ''));
        if (!empty($_POST['recaptcha_secret_key'])) {
            orabooks_update_membership_option('orabooks_recaptcha_secret_key', sanitize_text_field($_POST['recaptcha_secret_key']));
            // SL-008: Audit log credential update
            do_action('orabooks_security_event', 'credential_updated', array(
                'option' => 'orabooks_recaptcha_secret_key',
                'user_id' => get_current_user_id(),
            ));
        }

        // Feature Settings
        if (isset($_POST['features_config']) && is_array($_POST['features_config'])) {
            $features_config = array();
            foreach ($_POST['features_config'] as $feature_key => $feature) {
                if (is_array($feature)) {
                    $features_config[$feature_key] = array(
                        'name' => sanitize_text_field($feature['name'] ?? ''),
                        'description' => sanitize_text_field($feature['description'] ?? ''),
                        'subdomain_path' => sanitize_text_field($feature['subdomain_path'] ?? ''),
                        'enabled_levels' => isset($feature['enabled_levels']) && is_array($feature['enabled_levels']) ? array_map('intval', $feature['enabled_levels']) : array()
                    );
                }
            }
            update_option('orabooks_features_config', $features_config);
        }
    } catch (Exception $e) {
        // Log error but still redirect
        error_log('Orabooks settings save error: ' . $e->getMessage());
        $redirect_url = add_query_arg('settings-updated', 'error', $redirect_url);
    }
    
    // Always redirect, even on error
    wp_safe_redirect($redirect_url);
    exit;
}