<?php
/**
 * Post-Payment Handler
 * 
 * Handles actions after successful payment:
 * - Assigns membership to user
 * - Checks if user has a site
 * - Redirects to wp-signup if no site exists
 * - Redirects to success page if site exists
 */

if (!defined('ABSPATH')) exit;

/**
 * Handle order completion after successful payment
 */
add_action('orabooks_order_completed', 'orabooks_handle_post_payment', 10, 2);

function orabooks_handle_post_payment($order_id, $order) {
    // Assign membership level to user
    orabooks_assign_membership_to_user($order->user_id, $order->level_id);
    
    // Log the payment
    error_log('Payment completed: Order ' . $order->order_id . ' | User ' . $order->user_id . ' | Level ' . $order->level_id);
}

/**
 * Assign membership level and features to user
 */
function orabooks_assign_membership_to_user($user_id, $level_id) {
    try {
        error_log('Assigning membership: User ' . $user_id . ' → Level ' . $level_id);
        
        // Ensure tables are set up
        if (function_exists('orabooks_handle_multisite_tables')) {
            orabooks_handle_multisite_tables();
        }
        
        // Assign membership level (numeric ID for backward compatibility)
        update_user_meta($user_id, 'orabooks_level', $level_id);
        error_log('Membership level updated: ' . $level_id);
        
        // Also store the tier key for feature access lookups
        global $wpdb;
        $level_row = $wpdb->get_row($wpdb->prepare(
            "SELECT build_guide_level_key, name, price, mode FROM {$wpdb->orabooks_levels} WHERE id = %d",
            $level_id
        ));
        
        if ($level_row) {
            $tier_key = null;
            if (!empty($level_row->build_guide_level_key)) {
                $tier_key = $level_row->build_guide_level_key;
            } elseif (function_exists('orabooks_guess_tier_key_from_level')) {
                $tier_key = orabooks_guess_tier_key_from_level($level_row);
            }
            
            if ($tier_key) {
                update_user_meta($user_id, 'orabooks_level_key', $tier_key);
                error_log('Membership tier key stored: ' . $tier_key);
            }
        }
        
        // Get features assigned to this level
        // Check if table exists
        if (!isset($wpdb->orabooks_feature_assignments)) {
            error_log('Feature assignments table not defined');
            // Still set basic membership
            update_user_meta($user_id, 'orabooks_enabled_features', array());
            update_user_meta($user_id, 'orabooks_membership_start_date', current_time('mysql'));
            return;
        }
        
        $features = $wpdb->get_results($wpdb->prepare(
            "SELECT feature_key FROM {$wpdb->orabooks_feature_assignments}
            WHERE level_id = %d",
            $level_id
        ));
        
        error_log('Features found for level ' . $level_id . ': ' . count($features));
        
        // Assign features to user
        $feature_slugs = array();
        foreach ($features as $feature) {
            $feature_slugs[] = $feature->feature_key;
        }
        update_user_meta($user_id, 'orabooks_enabled_features', $feature_slugs);
        
        // Set membership start date
        update_user_meta($user_id, 'orabooks_membership_start_date', current_time('mysql'));
        
        // Log the assignment
        error_log('Membership assigned: User ' . $user_id . ' → Level ' . $level_id . ' | Features: ' . implode(', ', $feature_slugs));
        
    } catch (Exception $e) {
        error_log('Membership assignment error: ' . $e->getMessage());
        // Ensure basic membership is still set
        update_user_meta($user_id, 'orabooks_level', $level_id);
        update_user_meta($user_id, 'orabooks_enabled_features', array());
        update_user_meta($user_id, 'orabooks_membership_start_date', current_time('mysql'));
    }
}

/**
 * Redirect to wp-signup or success page after payment
 * This modifies the payment callback behavior
 */
add_filter('orabooks_payment_success_redirect', 'orabooks_post_payment_redirect', 10, 3);

function orabooks_post_payment_redirect($redirect_url, $order_id, $order) {
    // Check if user already has a site
    $user_has_site = orabooks_user_has_site($order->user_id);
    
    if (!$user_has_site) {
        // User doesn't have a site, redirect to wp-signup
        $signup_url = network_site_url('wp-signup.php');
        $signup_url = add_query_arg(array(
            'payment_success' => 'true',
            'order_id' => $order->order_id,
            'level_id' => $order->level_id,
            'from_payment' => '1'
        ), $signup_url);
        
        // Store order info in session for later use
        OraBooks_Session::get_instance()->set('orabooks_pending_order', array(
            'order_id' => $order->order_id,
            'user_id' => $order->user_id,
            'level_id' => $order->level_id
        ));
        
        return $signup_url;
    }
    
    // User already has a site, use default success page
    return $redirect_url;
}


/**
 * Add welcome message after first login to new site
 */
add_action('wp_login', 'orabooks_check_first_login_after_payment', 10, 2);

function orabooks_check_first_login_after_payment($user_login, $user) {
    // Check if this is from payment flow
    $order_info = OraBooks_Session::get_instance()->get('orabooks_pending_order');
    
    if ($order_info) {
        // Clear session
        OraBooks_Session::get_instance()->delete('orabooks_pending_order');
        
        // Redirect to welcome page or dashboard
        $welcome_url = home_url('/?welcome=true&first_login=1');
        wp_redirect($welcome_url);
        exit;
    }
}

/**
 * Display welcome message on first login
 */
add_action('wp_footer', 'orabooks_display_welcome_message');

function orabooks_display_welcome_message() {
    if (!isset($_GET['welcome']) || $_GET['welcome'] !== 'true') {
        return;
    }
    
    if (!isset($_GET['first_login']) || $_GET['first_login'] !== '1') {
        return;
    }
    
    $user = wp_get_current_user();
    $user_level = get_user_meta($user->ID, 'orabooks_level', true);
    
    global $wpdb;
    orabooks_handle_multisite_tables();
    $level = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->orabooks_levels} WHERE id = %d",
        $user_level
    ));
    
    ?>
    <div id="orabooks-welcome-modal" style="display: none;">
        <div class="orabooks-modal-overlay"></div>
        <div class="orabooks-modal-content">
            <div class="welcome-header">
                <h2>🎉 Welcome to Your New Site!</h2>
                <button class="close-modal" onclick="orabooksCloseWelcomeModal()">&times;</button>
            </div>
            
            <div class="welcome-body">
                <p>Congratulations, <strong><?php echo esc_html($user->display_name); ?></strong>!</p>
                <p>Your site has been successfully created and your <strong><?php echo $level ? esc_html($level->name) : 'membership'; ?></strong> plan is now active.</p>
                
                <div class="welcome-features">
                    <h3>What's Next?</h3>
                    <ul>
                        <li>✓ Explore your features and start using them</li>
                        <li>✓ Customize your site theme and logo</li>
                        <li>✓ Set up your profile and preferences</li>
                        <li>✓ Check out the dashboard for an overview</li>
                    </ul>
                </div>
                
                <div class="welcome-actions">
                    <a href="<?php echo admin_url(); ?>" class="btn btn-primary">Go to Dashboard</a>
                    <a href="<?php echo home_url(); ?>" class="btn btn-secondary" onclick="orabooksCloseWelcomeModal(); return false;">Explore Site</a>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    #orabooks-welcome-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 999999;
    }
    
    .orabooks-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(5px);
    }
    
    .orabooks-modal-content {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        border-radius: 16px;
        max-width: 600px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: slideIn 0.3s ease;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translate(-50%, -60%);
        }
        to {
            opacity: 1;
            transform: translate(-50%, -50%);
        }
    }
    
    .welcome-header {
        padding: 30px;
        border-bottom: 1px solid #e0e0e0;
        position: relative;
    }
    
    .welcome-header h2 {
        margin: 0;
        font-size: 28px;
        color: #3b82f6;
    }
    
    .close-modal {
        position: absolute;
        top: 20px;
        right: 20px;
        background: none;
        border: none;
        font-size: 32px;
        color: #999;
        cursor: pointer;
        line-height: 1;
        padding: 0;
        width: 32px;
        height: 32px;
    }
    
    .close-modal:hover {
        color: #333;
    }
    
    .welcome-body {
        padding: 30px;
    }
    
    .welcome-body p {
        font-size: 16px;
        line-height: 1.6;
        color: #555;
        margin: 0 0 15px 0;
    }
    
    .welcome-features {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin: 25px 0;
    }
    
    .welcome-features h3 {
        margin: 0 0 15px 0;
        font-size: 18px;
        color: #333;
    }
    
    .welcome-features ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .welcome-features li {
        padding: 8px 0;
        font-size: 15px;
        color: #555;
    }
    
    .welcome-actions {
        display: flex;
        gap: 15px;
        margin-top: 25px;
    }
    
    .welcome-actions .btn {
        flex: 1;
        padding: 15px 30px;
        border-radius: 8px;
        text-align: center;
        text-decoration: none;
        font-weight: 600;
        font-size: 16px;
        transition: all 0.3s ease;
    }
    
    .btn-primary {
        background: #3b82f6;
        color: white;
    }
    
    .btn-primary:hover {
        background: #2563eb;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    
    .btn-secondary {
        background: white;
        color: #3b82f6;
        border: 2px solid #3b82f6;
    }
    
    .btn-secondary:hover {
        background: #3b82f6;
        color: white;
    }
    
    @media (max-width: 768px) {
        .welcome-actions {
            flex-direction: column;
        }
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        $('#orabooks-welcome-modal').fadeIn(300);
    });
    
    function orabooksCloseWelcomeModal() {
        jQuery('#orabooks-welcome-modal').fadeOut(300);
        
        // Remove welcome parameter from URL
        var url = window.location.href;
        url = url.replace(/[\?&]welcome=true/, '');
        url = url.replace(/[\?&]first_login=1/, '');
        window.history.replaceState({}, document.title, url);
    }
    </script>
    <?php
}

/**
 * Store user's subdomain after site creation
 */
add_action('wpmu_new_blog', 'orabooks_store_user_subdomain', 10, 6);

function orabooks_store_user_subdomain($blog_id, $user_id, $domain, $path, $site_id, $meta) {
    // Extract subdomain from domain
    $network_domain = get_network()->domain;
    $subdomain = str_replace('.' . (string) $network_domain, '', (string) $domain);
    
    // Store subdomain in user meta
    update_user_meta($user_id, 'orabooks_subdomain', $subdomain);
    update_user_meta($user_id, 'orabooks_site_id', $blog_id);
    
    // Log the site creation
    error_log('Site created: Blog ' . $blog_id . ' | User ' . $user_id . ' | Subdomain: ' . $subdomain);
}
