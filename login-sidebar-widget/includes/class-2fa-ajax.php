<?php

/**
 * SL-013 – 2FA AJAX Handlers
 *
 * Registers WordPress AJAX endpoints for:
 *   - 2FA Setup   (lsw_2fa_setup)        → POST /api/auth/2fa/setup
 *   - 2FA Enable  (lsw_2fa_verify_setup) → POST /api/auth/2fa/verify-setup
 *   - 2FA Challenge (lsw_2fa_challenge)  → POST /api/auth/2fa/challenge
 *
 * All handlers require a valid WordPress nonce for CSRF protection.
 */

if (!class_exists('LSW_2FA_Ajax')) {
    class LSW_2FA_Ajax {

        public function __construct() {
            // Setup: generate secret + QR code + backup codes (authenticated users)
            add_action('wp_ajax_lsw_2fa_setup', [$this, 'handle_setup']);

            // Verify OTP and enable 2FA (authenticated users)
            add_action('wp_ajax_lsw_2fa_verify_setup', [$this, 'handle_verify_setup']);

            // Disable 2FA (authenticated users)
            add_action('wp_ajax_lsw_2fa_disable', [$this, 'handle_disable']);

            // Challenge: verify OTP during login (non-authenticated – pending challenge token)
            add_action('wp_ajax_nopriv_lsw_2fa_challenge', [$this, 'handle_challenge']);
            add_action('wp_ajax_lsw_2fa_challenge',        [$this, 'handle_challenge']);
        }

        // ──────────────────────────────────────────────────────────────────
        // 5.8 – 2FA Setup
        // Generates TOTP secret, QR URI, and 8 backup codes.
        // The secret is NOT yet saved/enabled until verify-setup succeeds.
        // ──────────────────────────────────────────────────────────────────
        public function handle_setup() {
            check_ajax_referer('lsw_2fa_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => __('Authentication required.', 'login-sidebar-widget')], 401);
            }

            $user    = wp_get_current_user();
            $secret  = OraBooks_2FA::generate_totp_secret();
            $qr_uri  = OraBooks_2FA::generate_qr_uri($user->user_email, $secret);
            $codes   = OraBooks_2FA::generate_backup_codes();

            // Store secret temporarily in a short-lived transient (not yet committed as enabled)
            // The secret is committed only after the user successfully verifies an OTP.
            set_transient(
                'lsw_2fa_pending_secret_' . $user->ID,
                $secret,
                10 * MINUTE_IN_SECONDS
            );

            // Store pending backup codes in transient too (committed on verify)
            set_transient(
                'lsw_2fa_pending_codes_' . $user->ID,
                $codes,
                10 * MINUTE_IN_SECONDS
            );

            wp_send_json_success([
                'secret'       => $secret,
                'qr_uri'       => $qr_uri,
                'backup_codes' => $codes,
                'message'      => __('Scan the QR code with your authenticator app, then enter the 6-digit code below to enable 2FA.', 'login-sidebar-widget'),
            ]);
        }

        // ──────────────────────────────────────────────────────────────────
        // 5.9 – 2FA Verify & Enable
        // Input: otp_code. Verify OTP (±30 sec).
        // Set is_2fa_enabled=true, store encrypted secret and backup code hashes.
        // Audit: 2fa_enabled.
        // ──────────────────────────────────────────────────────────────────
        public function handle_verify_setup() {
            check_ajax_referer('lsw_2fa_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => __('Authentication required.', 'login-sidebar-widget')], 401);
            }

            $user    = wp_get_current_user();
            $otp     = isset($_POST['otp_code']) ? sanitize_text_field($_POST['otp_code']) : '';

            if (empty($otp) || !preg_match('/^\d{6}$/', $otp)) {
                wp_send_json_error(['message' => __('Please enter a valid 6-digit code.', 'login-sidebar-widget')]);
            }

            // Retrieve the pending secret from transient
            $secret = get_transient('lsw_2fa_pending_secret_' . $user->ID);
            if (empty($secret)) {
                wp_send_json_error(['message' => __('Setup session expired. Please restart the setup process.', 'login-sidebar-widget')]);
            }

            // Verify the OTP against the pending secret (±30 sec per SL-013)
            if (!OraBooks_2FA::verify_totp($secret, $otp)) {
                wp_send_json_error(['message' => __('Invalid code. Please check your authenticator app and try again.', 'login-sidebar-widget')]);
            }

            // OTP verified — commit the secret and backup codes
            $codes = get_transient('lsw_2fa_pending_codes_' . $user->ID);
            if (!is_array($codes)) {
                $codes = OraBooks_2FA::generate_backup_codes();
            }

            OraBooks_2FA::save_totp_secret($user->ID, $secret);
            OraBooks_2FA::save_backup_codes($user->ID, $codes);
            OraBooks_2FA::enable($user->ID);

            // Clean up transients
            delete_transient('lsw_2fa_pending_secret_' . $user->ID);
            delete_transient('lsw_2fa_pending_codes_' . $user->ID);

            // Audit: 2fa_enabled (SL-013)
            OraBooks_2FA::audit('2fa_enabled', [
                'user_id' => $user->ID,
                'email'   => $user->user_email,
                'ip'      => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ]);

            wp_send_json_success([
                'message' => __('Two-factor authentication has been enabled successfully!', 'login-sidebar-widget'),
            ]);
        }

        // ──────────────────────────────────────────────────────────────────
        // Disable 2FA
        // ──────────────────────────────────────────────────────────────────
        public function handle_disable() {
            check_ajax_referer('lsw_2fa_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => __('Authentication required.', 'login-sidebar-widget')], 401);
            }

            $user = wp_get_current_user();
            OraBooks_2FA::disable($user->ID);

            OraBooks_2FA::audit('2fa_disabled', [
                'user_id' => $user->ID,
                'email'   => $user->user_email,
                'ip'      => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ]);

            wp_send_json_success([
                'message' => __('Two-factor authentication has been disabled.', 'login-sidebar-widget'),
            ]);
        }

        // ──────────────────────────────────────────────────────────────────
        // 5.10 – 2FA Challenge
        // Input: challenge_token, otp_code or backup_code.
        // On success: set auth cookie, redirect to dashboard.
        // Audit: login_success (2fa method).
        // ──────────────────────────────────────────────────────────────────
        public function handle_challenge() {
            check_ajax_referer('lsw_2fa_challenge_nonce', 'nonce');

            $token       = isset($_POST['challenge_token'])   ? sanitize_text_field($_POST['challenge_token'])   : '';
            $otp         = isset($_POST['otp_code'])          ? sanitize_text_field($_POST['otp_code'])          : '';
            $backup_code = isset($_POST['backup_code'])       ? strtoupper(sanitize_text_field($_POST['backup_code'])) : '';

            // Retrieve pending challenge
            $challenge = OraBooks_2FA::get_challenge($token);
            if (empty($challenge) || empty($challenge['user_id'])) {
                wp_send_json_error([
                    'message'  => __('Your session has expired. Please log in again.', 'login-sidebar-widget'),
                    'redirect' => wp_login_url(),
                ]);
            }

            $user_id  = (int) $challenge['user_id'];
            $remember = (bool) $challenge['remember'];
            $user     = get_user_by('ID', $user_id);

            if (!$user) {
                OraBooks_2FA::clear_challenge($token);
                wp_send_json_error(['message' => __('Invalid session. Please log in again.', 'login-sidebar-widget')]);
            }

            $verified = false;

            // Try TOTP code first
            if (!empty($otp)) {
                $secret = OraBooks_2FA::get_totp_secret($user_id);
                if ($secret && OraBooks_2FA::verify_totp($secret, $otp)) {
                    $verified = true;
                }
            }

            // Try backup code if TOTP didn't verify
            if (!$verified && !empty($backup_code)) {
                if (OraBooks_2FA::verify_backup_code($user_id, $backup_code)) {
                    $verified = true;
                }
            }

            if (!$verified) {
                // Log failed 2FA attempt
                if (class_exists('Login_Log_Adds')) {
                    $lla = new Login_Log_Adds;
                    $ip  = apply_filters('lwws_log_ip', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
                    $lla->log_add($ip, 'SL-013 2FA: challenge_failed [user_id:' . $user_id . ']', date('Y-m-d H:i:s'), 'failed');
                }
                wp_send_json_error(['message' => __('Invalid code. Please try again.', 'login-sidebar-widget')]);
            }

            // ── Challenge passed ──
            OraBooks_2FA::clear_challenge($token);

            // Set WordPress auth cookie (completes the login)
            wp_set_auth_cookie($user_id, $remember);
            wp_set_current_user($user_id);

            // Audit: login_success (2fa method) per SL-013
            OraBooks_2FA::audit('login_success', [
                'user_id' => $user_id,
                'method'  => '2fa',
                'ip'      => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ]);

            // Determine redirect URL
            $redirect = apply_filters(
                'lwws_login_redirect',
                get_option('redirect_page_url') ?: admin_url(),
                $user_id
            );

            wp_send_json_success([
                'message'  => __('Login successful.', 'login-sidebar-widget'),
                'redirect' => esc_url_raw($redirect),
            ]);
        }
    }
}
