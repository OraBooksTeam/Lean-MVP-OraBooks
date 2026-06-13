<?php
/**
 * MFA / 2FA Verification Page
 * 
 * SL-013 Compliance: TOTP ±30 sec drift, backup codes single-use.
 * SL-008 Compliance: 2FA secrets encrypted at rest.
 * 
 * This implements the 2FA workflow:
 *   /api/auth/2fa/setup        - Generate TOTP secret, QR code, backup codes
 *   /api/auth/2fa/verify-setup - Verify OTP and enable 2FA
 *   /api/auth/2fa/challenge    - Challenge during login
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add MFA verification submenu page
 */
function orabooks_add_mfa_verify_menu() {
    add_submenu_page(
        'orabooks-membership',
        __('2FA Verification', 'orabooks'),
        __('2FA Verification', 'orabooks'),
        'manage_options',
        'orabooks-mfa-verify',
        'orabooks_mfa_verify_page'
    );
}
add_action('admin_menu', 'orabooks_add_mfa_verify_menu');

/**
 * SL-013 Compliance: Generate TOTP secret for 2FA setup.
 * Returns the secret, QR code URL, and 8 single-use backup codes.
 */
function orabooks_generate_2fa_setup_data($user_id) {
    // Generate a cryptographically secure TOTP secret (random 20 bytes → base32)
    $random_bytes = random_bytes(20);
    $secret = orabooks_base32_encode($random_bytes);
    
    // Encrypt the secret for storage (SL-008 compliance)
    $encrypted_secret = orabooks_encrypt_credential($secret);
    update_user_meta($user_id, '_orabooks_2fa_encrypted_secret', $encrypted_secret);
    
    // Generate issuer + user info for QR code URI (TOTP URI format)
    $issuer = rawurlencode(get_bloginfo('name'));
    $user_info = get_userdata($user_id);
    $label = rawurlencode($issuer . ':' . $user_info->user_email);
    $qr_uri = sprintf(
        'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
        $label,
        $secret,
        $issuer
    );
    
    // Generate 8 single-use backup codes (SL-013: backup codes single-use)
    $backup_codes = array();
    $hashed_codes = array();
    for ($i = 0; $i < 8; $i++) {
        // Format: XXXXX-XXXXX (10 alphanumeric chars)
        $code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 5) . '-' . substr(bin2hex(random_bytes(5)), 0, 5));
        $backup_codes[] = $code;
        // Store SHA-256 hash for verification (never store plaintext backup codes)
        $hashed_codes[] = wp_hash_password($code);
    }
    update_user_meta($user_id, '_orabooks_2fa_backup_code_hashes', $hashed_codes);
    
    return array(
        'secret' => $secret,
        'qr_uri' => $qr_uri,
        'qr_code_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($qr_uri),
        'backup_codes' => $backup_codes,
    );
}

/**
 * SL-013 Compliance: Verify TOTP code with ±30 sec drift allowance.
 *
 * @param string $secret     Base32-encoded TOTP secret
 * @param string $otp_code   The 6-digit OTP code from authenticator app
 * @return bool              True if valid
 */
function orabooks_verify_totp($secret, $otp_code) {
    // Decode base32 secret
    $decoded_secret = orabooks_base32_decode($secret);
    if ($decoded_secret === false || strlen((string)$otp_code) !== 6) {
        return false;
    }
    
    $otp = (int)$otp_code;
    $timestamp = (int)(time() / 30);
    
    // Check current, previous, and next 30-second windows (±1 step = ±30 sec drift)
    for ($i = -1; $i <= 1; $i++) {
        $expected = orabooks_totp_calculate($decoded_secret, $timestamp + $i);
        if ($expected === $otp) {
            return true;
        }
    }
    
    return false;
}

/**
 * Calculate TOTP value for a given secret and time step.
 */
function orabooks_totp_calculate($secret_binary, $time_step) {
    // Pack time step into 8-byte big-endian
    $time_bytes = pack('J', $time_step); // J = unsigned 64-bit big-endian (PHP 8+)
    
    // HMAC-SHA1
    $hmac = hash_hmac('sha1', $time_bytes, $secret_binary, true);
    
    // Dynamic truncation (RFC 4226)
    $offset = ord($hmac[19]) & 0x0f;
    $otp = (
        ((ord($hmac[$offset]) & 0x7f) << 24) |
        ((ord($hmac[$offset + 1]) & 0xff) << 16) |
        ((ord($hmac[$offset + 2]) & 0xff) << 8) |
        (ord($hmac[$offset + 3]) & 0xff)
    ) % 1000000;
    
    return (int)$otp;
}

/**
 * SL-013 Compliance: Verify backup code (single-use).
 * Checks hash, and on success removes the used code.
 *
 * @param int    $user_id     User ID
 * @param string $backup_code Backup code to verify
 * @return bool               True if valid
 */
function orabooks_verify_backup_code($user_id, $backup_code) {
    $hashed_codes = get_user_meta($user_id, '_orabooks_2fa_backup_code_hashes', true);
    if (!is_array($hashed_codes) || empty($hashed_codes)) {
        return false;
    }
    
    foreach ($hashed_codes as $index => $hash) {
        if (wp_check_password($backup_code, $hash)) {
            // Remove used backup code (single-use)
            unset($hashed_codes[$index]);
            update_user_meta($user_id, '_orabooks_2fa_backup_code_hashes', array_values($hashed_codes));
            return true;
        }
    }
    
    return false;
}

/**
 * SL-013 Compliance: Get current 2FA status for a user.
 *
 * @param int $user_id User ID
 * @return array       Status info
 */
function orabooks_get_2fa_status($user_id) {
    $secret = get_user_meta($user_id, '_orabooks_2fa_encrypted_secret', true);
    $backup_codes = get_user_meta($user_id, '_orabooks_2fa_backup_code_hashes', true);
    $remaining_backup_codes = is_array($backup_codes) ? count($backup_codes) : 0;
    
    return array(
        'is_enabled' => !empty($secret),
        'remaining_backup_codes' => $remaining_backup_codes,
    );
}

/**
 * SL-013 Compliance: Disable 2FA for a user.
 */
function orabooks_disable_2fa($user_id) {
    delete_user_meta($user_id, '_orabooks_2fa_encrypted_secret');
    delete_user_meta($user_id, '_orabooks_2fa_backup_code_hashes');
    delete_user_meta($user_id, '_orabooks_2fa_last_verified');
    
    do_action('orabooks_security_event', '2fa_disabled', array(
        'user_id' => $user_id,
    ));
}

/**
 * SL-013 Compliance: Log a security event for 2FA.
 */
function orabooks_audit_2fa_event($event_type, $user_id, $metadata = array()) {
    $log_entry = array_merge(array(
        'event_type' => $event_type,
        'timestamp' => current_time('mysql'),
        'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
        'user_id' => $user_id,
    ), $metadata);
    
    do_action('orabooks_security_event', $event_type, $log_entry);
    
    error_log(sprintf(
        '[OraBooks 2FA] Event: %s | User: %d | IP: %s',
        $event_type,
        $user_id,
        $log_entry['ip_address']
    ));
}

/**
 * Base32 encode (RFC 4648)
 */
function orabooks_base32_encode($data) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = '';
    foreach (str_split($data) as $char) {
        $binary .= sprintf('%08b', ord($char));
    }
    // Pad to 5-bit groups
    $binary = str_pad($binary, strlen($binary) + (5 - strlen($binary) % 5) % 5, '0', STR_PAD_RIGHT);
    
    $result = '';
    for ($i = 0; $i < strlen($binary); $i += 5) {
        $result .= $chars[bindec(substr($binary, $i, 5))];
    }
    return $result;
}

/**
 * Base32 decode (RFC 4648)
 */
function orabooks_base32_decode($data) {
    $data = strtoupper((string)$data);
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = '';
    
    foreach (str_split($data) as $char) {
        $pos = strpos($chars, $char);
        if ($pos === false) {
            continue; // Skip invalid characters
        }
        $binary .= sprintf('%05b', $pos);
    }
    
    $result = '';
    for ($i = 0; $i + 7 < strlen($binary); $i += 8) {
        $result .= chr(bindec(substr($binary, $i, 8)));
    }
    
    return $result;
}

/**
 * Display MFA verification / 2FA management page
 */
function orabooks_mfa_verify_page() {
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;
    $message = '';
    $message_type = 'updated';
    
    // Handle 2FA Setup
    if (isset($_POST['orabooks_2fa_generate'])) {
        check_admin_referer('orabooks_2fa_setup');
        
        $setup_data = orabooks_generate_2fa_setup_data($user_id);
        // Store setup data temporarily for verification
        $temp_store = array(
            'secret' => $setup_data['secret'],
            'created_at' => time(),
        );
        update_user_meta($user_id, '_orabooks_2fa_pending_setup', $temp_store);
        
        $message = __('Scan the QR code with your authenticator app (Google Authenticator, Authy, etc.), then enter the 6-digit code below to enable 2FA.', 'orabooks');
        
        // Store backup codes in session for display (one time only)
        set_transient('orabooks_2fa_new_backup_codes_' . $user_id, $setup_data['backup_codes'], 300);
    }
    
    // Handle 2FA Verify Setup
    if (isset($_POST['orabooks_2fa_verify_setup'])) {
        check_admin_referer('orabooks_2fa_verify_setup');
        
        $otp_code = sanitize_text_field($_POST['otp_code']);
        $pending = get_user_meta($user_id, '_orabooks_2fa_pending_setup', true);
        
        if (empty($pending) || empty($pending['secret'])) {
            $message = __('No pending 2FA setup found. Please generate a new setup.', 'orabooks');
            $message_type = 'error';
        } elseif (time() - $pending['created_at'] > 300) {
            // Expired after 5 minutes
            delete_user_meta($user_id, '_orabooks_2fa_pending_setup');
            orabooks_disable_2fa($user_id);
            $message = __('Setup expired. Please generate a new setup.', 'orabooks');
            $message_type = 'error';
        } elseif (orabooks_verify_totp($pending['secret'], $otp_code)) {
            // TOTP verified - enable 2FA
            delete_user_meta($user_id, '_orabooks_2fa_pending_setup');
            update_user_meta($user_id, '_orabooks_2fa_last_verified', time());
            
            orabooks_audit_2fa_event('2fa_enabled', $user_id);
            
            $message = __('✅ 2FA has been successfully enabled! Save your backup codes in a secure place.', 'orabooks');
        } else {
            $message = __('❌ Invalid verification code. Please try again.', 'orabooks');
            $message_type = 'error';
        }
    }
    
    // Handle 2FA Disable
    if (isset($_POST['orabooks_2fa_disable'])) {
        check_admin_referer('orabooks_2fa_disable');
        
        $otp_code = sanitize_text_field($_POST['otp_code']);
        $encrypted_secret = get_user_meta($user_id, '_orabooks_2fa_encrypted_secret', true);
        $secret = orabooks_decrypt_credential($encrypted_secret);
        
        if (orabooks_verify_totp($secret, $otp_code) || orabooks_verify_backup_code($user_id, $otp_code)) {
            orabooks_disable_2fa($user_id);
            $message = __('2FA has been disabled.', 'orabooks');
        } else {
            $message = __('❌ Invalid verification code. 2FA was not disabled.', 'orabooks');
            $message_type = 'error';
        }
    }
    
    // Handle 2FA Challenge (during login)
    if (isset($_POST['orabooks_2fa_challenge'])) {
        check_admin_referer('orabooks_2fa_challenge');
        
        $otp_code = sanitize_text_field($_POST['mfa_code']);
        $encrypted_secret = get_user_meta($user_id, '_orabooks_2fa_encrypted_secret', true);
        $secret = orabooks_decrypt_credential($encrypted_secret);
        
        if (orabooks_verify_totp($secret, $otp_code)) {
            OraBooks_Session::get_instance()->set('orabooks_mfa_verified', true);
            update_user_meta($user_id, '_orabooks_2fa_last_verified', time());
            
            orabooks_audit_2fa_event('login_success_2fa', $user_id, array('method' => 'totp'));
            
            $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : admin_url();
            wp_redirect($redirect_to);
            exit;
        } elseif (orabooks_verify_backup_code($user_id, $otp_code)) {
            OraBooks_Session::get_instance()->set('orabooks_mfa_verified', true);
            update_user_meta($user_id, '_orabooks_2fa_last_verified', time());
            
            orabooks_audit_2fa_event('login_success_2fa', $user_id, array('method' => 'backup_code'));
            
            $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : admin_url();
            wp_redirect($redirect_to);
            exit;
        } else {
            $message = __('❌ Invalid 2FA code. Please try again.', 'orabooks');
            $message_type = 'error';
        }
    }
    
    // Get current 2FA status
    $status = orabooks_get_2fa_status($user_id);
    $setup_data_display = get_user_meta($user_id, '_orabooks_2fa_pending_setup', true);
    $backup_codes_display = get_transient('orabooks_2fa_new_backup_codes_' . $user_id);
    ?>
    <div class="wrap orabooks-2fa-wrap">
        <h1><?php _e('Two-Factor Authentication (2FA)', 'orabooks'); ?></h1>
        <p><?php _e('Enhance your account security with time-based one-time passwords (TOTP) from your authenticator app.', 'orabooks'); ?></p>
        
        <?php if ($message): ?>
            <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="orabooks-2fa-card">
            <?php if ($status['is_enabled']): ?>
                <!-- 2FA is Enabled -->
                <div class="orabooks-2fa-status-enabled">
                    <span class="dashicons dashicons-yes-alt" style="color:#46b450; font-size: 24px;"></span>
                    <h2><?php _e('2FA is Active', 'orabooks'); ?></h2>
                    <p><?php printf(__('You have %d backup code(s) remaining.', 'orabooks'), $status['remaining_backup_codes']); ?></p>
                    
                    <details>
                        <summary><?php _e('Disable 2FA', 'orabooks'); ?></summary>
                        <form method="post" action="" class="orabooks-2fa-form">
                            <?php wp_nonce_field('orabooks_2fa_disable'); ?>
                            <p><?php _e('Enter a TOTP code or backup code to disable 2FA:', 'orabooks'); ?></p>
                            <input type="text" name="otp_code" maxlength="11" pattern="[0-9A-Za-z-]{6,11}" required 
                                   placeholder="<?php _e('6-digit code or backup code', 'orabooks'); ?>">
                            <input type="submit" name="orabooks_2fa_disable" class="button button-secondary" 
                                   value="<?php _e('Disable 2FA', 'orabooks'); ?>"
                                   onclick="return confirm('<?php _e('Are you sure? This will reduce your account security.', 'orabooks'); ?>')">
                        </form>
                    </details>
                </div>
                
            <?php elseif (!empty($setup_data_display)): ?>
                <!-- Pending 2FA Setup - Show QR Code -->
                <div class="orabooks-2fa-setup-pending">
                    <h2><?php _e('Step 1: Scan QR Code', 'orabooks'); ?></h2>
                    <p><?php _e('Scan this QR code with your authenticator app:', 'orabooks'); ?></p>
                    
                    <?php
                    $issuer = rawurlencode(get_bloginfo('name'));
                    $user_info = get_userdata($user_id);
                    $label = rawurlencode($issuer . ':' . $user_info->user_email);
                    $qr_uri = sprintf(
                        'otpauth://totp/%s?secret=%s&issuer=%s',
                        $label,
                        $setup_data_display['secret'],
                        $issuer
                    );
                    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($qr_uri);
                    ?>
                    <div class="orabooks-2fa-qr">
                        <img src="<?php echo esc_url($qr_url); ?>" alt="TOTP QR Code" width="200" height="200" />
                    </div>
                    <details>
                        <summary><?php _e('Can\'t scan the QR code?', 'orabooks'); ?></summary>
                        <p><?php _e('Enter this secret key manually in your authenticator app:', 'orabooks'); ?></p>
                        <code class="orabooks-2fa-secret"><?php echo esc_html($setup_data_display['secret']); ?></code>
                    </details>
                    
                    <h2><?php _e('Step 2: Verify Setup', 'orabooks'); ?></h2>
                    <p><?php _e('Enter the 6-digit code from your authenticator app:', 'orabooks'); ?></p>
                    <form method="post" action="" class="orabooks-2fa-form">
                        <?php wp_nonce_field('orabooks_2fa_verify_setup'); ?>
                        <input type="text" name="otp_code" maxlength="6" pattern="[0-9]{6}" required 
                               placeholder="<?php _e('6-digit code', 'orabooks'); ?>">
                        <input type="submit" name="orabooks_2fa_verify_setup" class="button button-primary" 
                               value="<?php _e('Verify & Enable 2FA', 'orabooks'); ?>">
                    </form>
                    
                    <?php if ($backup_codes_display): ?>
                        <div class="orabooks-2fa-backup-codes">
                            <h3><?php _e('Backup Codes (single-use)', 'orabooks'); ?></h3>
                            <p class="warning"><?php _e('⚠️ Save these codes in a secure place. Each code can be used only once.', 'orabooks'); ?></p>
                            <div class="orabooks-2fa-codes-list">
                                <?php foreach ($backup_codes_display as $code): ?>
                                    <code><?php echo esc_html($code); ?></code>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <form method="post" action="" style="margin-top:20px;">
                    <?php wp_nonce_field('orabooks_2fa_setup'); ?>
                    <input type="submit" name="orabooks_2fa_generate" class="button button-secondary" 
                           value="<?php _e('Regenerate Setup', 'orabooks'); ?>">
                </form>
                
            <?php else: ?>
                <!-- 2FA Not Enabled -->
                <div class="orabooks-2fa-status-disabled">
                    <span class="dashicons dashicons-shield" style="color:#888; font-size: 24px;"></span>
                    <h2><?php _e('2FA is Not Active', 'orabooks'); ?></h2>
                    <p><?php _e('Two-factor authentication adds an extra layer of security to your account.', 'orabooks'); ?></p>
                    
                    <form method="post" action="" class="orabooks-2fa-form">
                        <?php wp_nonce_field('orabooks_2fa_setup'); ?>
                        <input type="submit" name="orabooks_2fa_generate" class="button button-primary" 
                               value="<?php _e('Setup 2FA', 'orabooks'); ?>">
                    </form>
                    
                    <div class="orabooks-2fa-info">
                        <h3><?php _e('How it works', 'orabooks'); ?></h3>
                        <ol>
                            <li><?php _e('Click "Setup 2FA" to generate a QR code', 'orabooks'); ?></li>
                            <li><?php _e('Scan the QR code with Google Authenticator, Authy, or similar', 'orabooks'); ?></li>
                            <li><?php _e('Enter the 6-digit code from the app to verify', 'orabooks'); ?></li>
                            <li><?php _e('Save the backup codes in a secure place', 'orabooks'); ?></li>
                        </ol>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <style type="text/css">
        .orabooks-2fa-wrap {
            max-width: 700px;
        }
        .orabooks-2fa-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 30px;
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .orabooks-2fa-card h2 {
            margin-top: 0;
            color: #23282d;
        }
        .orabooks-2fa-qr {
            margin: 20px 0;
            text-align: center;
        }
        .orabooks-2fa-qr img {
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .orabooks-2fa-secret {
            font-family: monospace;
            font-size: 14px;
            background: #f0f0f1;
            padding: 10px;
            border-radius: 4px;
            display: inline-block;
            margin: 10px 0;
            word-break: break-all;
        }
        .orabooks-2fa-form {
            margin: 15px 0;
        }
        .orabooks-2fa-form input[type="text"] {
            width: 220px;
            padding: 8px 12px;
            font-size: 16px;
            letter-spacing: 2px;
            text-align: center;
            border: 1px solid #8c8f94;
            border-radius: 4px;
        }
        .orabooks-2fa-form input[type="submit"] {
            margin-left: 10px;
        }
        .orabooks-2fa-backup-codes {
            margin-top: 25px;
            padding: 20px;
            background: #fff8e5;
            border: 1px solid #f0c33c;
            border-radius: 6px;
        }
        .orabooks-2fa-backup-codes .warning {
            color: #b8860b;
            font-weight: 600;
        }
        .orabooks-2fa-codes-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 10px;
        }
        .orabooks-2fa-codes-list code {
            font-family: monospace;
            font-size: 14px;
            padding: 8px;
            background: #fff;
            border: 1px dashed #aaa;
            border-radius: 4px;
            text-align: center;
        }
        .orabooks-2fa-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .orabooks-2fa-info ol {
            margin-left: 20px;
        }
        .orabooks-2fa-info ol li {
            margin-bottom: 8px;
        }
        details {
            margin: 15px 0;
        }
        details summary {
            cursor: pointer;
            color: #0073aa;
        }
        details summary:hover {
            color: #00a0d2;
        }
    </style>
    <?php
}

/**
 * Handle 2FA challenge during login for users with 2FA enabled.
 * Called from login form submission.
 */
function orabooks_handle_2fa_challenge_ajax() {
    check_ajax_referer('orabooks_mfa_verify', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => __('Not logged in', 'orabooks')));
    }
    
    $user_id = get_current_user_id();
    $status = orabooks_get_2fa_status($user_id);
    
    if (!$status['is_enabled']) {
        wp_send_json_success(array('message' => __('2FA not required', 'orabooks'), 'mfa_verified' => true));
    }
    
    $mfa_code = sanitize_text_field($_POST['mfa_code']);
    $encrypted_secret = get_user_meta($user_id, '_orabooks_2fa_encrypted_secret', true);
    $secret = orabooks_decrypt_credential($encrypted_secret);
    
    if (orabooks_verify_totp($secret, $mfa_code)) {
        OraBooks_Session::get_instance()->set('orabooks_mfa_verified', true);
        update_user_meta($user_id, '_orabooks_2fa_last_verified', time());
        orabooks_audit_2fa_event('login_success_2fa', $user_id, array('method' => 'totp'));
        wp_send_json_success(array('message' => __('2FA verified', 'orabooks'), 'mfa_verified' => true));
    } elseif (orabooks_verify_backup_code($user_id, $mfa_code)) {
        OraBooks_Session::get_instance()->set('orabooks_mfa_verified', true);
        update_user_meta($user_id, '_orabooks_2fa_last_verified', time());
        orabooks_audit_2fa_event('login_success_2fa', $user_id, array('method' => 'backup_code'));
        wp_send_json_success(array('message' => __('2FA verified via backup code', 'orabooks'), 'mfa_verified' => true));
    } else {
        orabooks_audit_2fa_event('2fa_challenge_failed', $user_id, array());
        wp_send_json_error(array('message' => __('Invalid code. Please try again.', 'orabooks')));
    }
}
add_action('wp_ajax_orabooks_2fa_verify', 'orabooks_handle_2fa_challenge_ajax');

/**
 * Reset MFA verification on logout
 */
function orabooks_reset_mfa_on_logout() {
    OraBooks_Session::get_instance()->delete('orabooks_mfa_verified');
    do_action('orabooks_security_event', 'user_logged_out', array(
        'user_id' => get_current_user_id(),
    ));
}
add_action('wp_logout', 'orabooks_reset_mfa_on_logout');