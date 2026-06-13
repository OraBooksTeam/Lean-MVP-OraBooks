<?php
/**
 * MFA Verification Page
 * Multi-factor authentication verification for sensitive roles
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add MFA verification menu item - disabled as not mandatory
 */
function orabooks_add_mfa_verify_menu() {
    // Menu item disabled as requested
    return;
    
    // add_submenu_page(
    //     'orabooks-membership',
    //     'MFA Verification',
    //     'MFA Verification',
    //     'manage_options',
    //     'orabooks-membership-mfa-verify',
    //     'orabooks_mfa_verify_page'
    // );
}

add_action('admin_menu', 'orabooks_add_mfa_verify_menu');

/**
 * Display MFA verification page
 */
function orabooks_mfa_verify_page() {
    $current_user = wp_get_current_user();
    
    // Check if user can access this page
    if (!current_user_can('manage_options')) {
        wp_die(__('Sorry, you are not allowed to access this page.'));
    }
    
    // Check if MFA is already verified
    if (OraBooks_Session::get_instance()->get('orabooks_mfa_verified') === true) {
        // Redirect to dashboard if already verified
        wp_redirect(admin_url());
        exit;
    }
    
    // Handle MFA verification
    if (isset($_POST['orabooks_mfa_verify'])) {
        $mfa_code = sanitize_text_field($_POST['mfa_code']);
        
        // For now, use a simple verification (in production, this would integrate with authenticator app)
        if ($mfa_code === '123456' || $mfa_code === '000000') {
            // Mark MFA as verified
            OraBooks_Session::get_instance()->set('orabooks_mfa_verified', true);
            
            // Log MFA verification
            error_log('[OraBooks Security] MFA verified for user: ' . $current_user->ID);
            
            // Redirect to intended destination
            $redirect_to = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : admin_url();
            wp_redirect($redirect_to);
            exit;
        } else {
            $error_message = __('Invalid verification code. Please try again.', 'orabooks');
        }
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Multi-Factor Authentication', 'orabooks'); ?></h1>
        
        <div class="mfa-verify-container">
            <div class="mfa-verify-card">
                <h2><?php _e('Verify Your Identity', 'orabooks'); ?></h2>
                <p><?php _e('For security purposes, please enter the verification code from your authenticator app.', 'orabooks'); ?></p>
                
                <?php if (isset($error_message)): ?>
                    <div class="notice notice-error">
                        <p><?php echo esc_html($error_message); ?></p>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <?php wp_nonce_field('orabooks_mfa_verify', 'mfa_nonce'); ?>
                    
                    <div class="mfa-form-group">
                        <label for="mfa_code"><?php _e('Verification Code:', 'orabooks'); ?></label>
                        <input type="text" id="mfa_code" name="mfa_code" class="regular-text" 
                               placeholder="<?php _e('Enter 6-digit code', 'orabooks'); ?>" 
                               maxlength="6" pattern="[0-9]{6}" required>
                    </div>
                    
                    <div class="mfa-form-group">
                        <input type="hidden" name="redirect_to" value="<?php echo esc_url(isset($_GET['redirect_to']) ? $_GET['redirect_to'] : admin_url()); ?>">
                        <input type="submit" name="orabooks_mfa_verify" class="button button-primary" 
                               value="<?php _e('Verify', 'orabooks'); ?>">
                    </div>
                </form>
                
                <div class="mfa-help">
                    <h3><?php _e('Need Help?', 'orabooks'); ?></h3>
                    <p><strong><?php _e('Test Codes:', 'orabooks'); ?></strong> 123456 or 000000</p>
                    <p><?php _e('In production, you would use codes from your authenticator app (Google Authenticator, Authy, etc.).', 'orabooks'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <style type="text/css">
        .mfa-verify-container {
            max-width: 500px;
            margin: 40px auto;
        }
        
        .mfa-verify-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .mfa-verify-card h2 {
            margin-top: 0;
            color: #23282d;
        }
        
        .mfa-form-group {
            margin-bottom: 20px;
        }
        
        .mfa-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .mfa-form-group input[type="text"] {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .mfa-form-group input[type="text"]:focus {
            border-color: #0073aa;
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
        }
        
        .mfa-help {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #666;
        }
        
        .mfa-help h3 {
            margin-top: 0;
            font-size: 16px;
        }
        
        .mfa-help p {
            margin-bottom: 10px;
        }
    </style>
    <?php
}

/**
 * Handle MFA verification for AJAX requests
 */
function orabooks_ajax_mfa_verify() {
    check_ajax_referer('orabooks_mfa_verify', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permission denied', 'orabooks')]);
    }
    
    $mfa_code = sanitize_text_field($_POST['mfa_code']);
    
    if ($mfa_code === '123456' || $mfa_code === '000000') {
        OraBooks_Session::get_instance()->set('orabooks_mfa_verified', true);
        wp_send_json_success(['message' => __('MFA verified successfully', 'orabooks')]);
    } else {
        wp_send_json_error(['message' => __('Invalid verification code', 'orabooks')]);
    }
}

add_action('wp_ajax_orabooks_mfa_verify', 'orabooks_ajax_mfa_verify');

/**
 * Reset MFA verification on logout
 */
function orabooks_reset_mfa_on_logout() {
    OraBooks_Session::get_instance()->delete('orabooks_mfa_verified');
}

add_action('wp_logout', 'orabooks_reset_mfa_on_logout');
?>
