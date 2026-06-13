<?php
/**
 * SL-013 §5.8-5.10 – 2FA (TOTP) Handler
 *
 * Implements the 2FA workflow using the OraBooks_2FA helper class
 * (defined in login-sidebar-widget/includes/class-security.php):
 *
 *   §5.8  POST /api/auth/2fa/setup         → orabooks_2fa_setup
 *   §5.9  POST /api/auth/2fa/verify-setup   → orabooks_2fa_verify_setup
 *   §5.9  POST /api/auth/2fa/disable        → orabooks_2fa_disable
 *   §5.10 POST /api/auth/2fa/challenge      → orabooks_2fa_challenge
 *
 * Also provides:
 *   - Login interception for 2FA-enabled users (wp_login hook)
 *   - [orabooks_2fa_challenge] shortcode
 *   - [orabooks_2fa_settings]  shortcode
 *   - /2fa-challenge/ rewrite route
 *   - JWT issuance after successful 2FA challenge
 *
 * Build Order: SL-004 → SL-013 → SL-003 → SL-017 → SL-139 → SL-068
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_2FA_Handler {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // ── §5.8: 2FA Setup ──
        add_action('wp_ajax_orabooks_2fa_setup', array($this, 'ajax_setup'));

        // ── §5.9: Verify & Enable ──
        add_action('wp_ajax_orabooks_2fa_verify_setup', array($this, 'ajax_verify_setup'));

        // ── §5.9: Disable ──
        add_action('wp_ajax_orabooks_2fa_disable', array($this, 'ajax_disable'));

        // ── §5.10: Challenge (authenticated + unauthenticated) ──
        add_action('wp_ajax_nopriv_orabooks_2fa_challenge', array($this, 'ajax_challenge'));
        add_action('wp_ajax_orabooks_2fa_challenge',        array($this, 'ajax_challenge'));

        // ── Login interception for 2FA-enabled users ──
        add_action('wp_login', array($this, 'intercept_2fa_login'), 1, 2);

        // ── Shortcodes ──
        add_shortcode('orabooks_2fa_challenge', array($this, 'render_challenge_form'));
        add_shortcode('orabooks_2fa_settings',  array($this, 'render_settings_form'));

        // ── Rewrite rule for /2fa-challenge/ ──
        add_action('init', array($this, 'register_challenge_rewrite'));
        add_filter('query_vars', array($this, 'add_challenge_query_var'));
        add_action('template_redirect', array($this, 'handle_challenge_page'));

        // ── Enqueue jQuery on 2FA pages ──
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    // ================================================================
    // LOGIN INTERCEPTION (SL-013 §5.4)
    // ================================================================

    /**
     * Intercept login for 2FA-enabled users.
     * Runs early on wp_login (priority 1). Creates a challenge token,
     * logs out the session, and redirects to /2fa-challenge/.
     *
     * @param string   $user_login User login name
     * @param WP_User  $user       WP_User object
     */
    public function intercept_2fa_login($user_login, $user) {
        if (!class_exists('OraBooks_2FA')) {
            return;
        }

        if (!OraBooks_2FA::is_enabled($user->ID)) {
            return;
        }

        // Capture remember-me from POST
        $remember = isset($_POST['rememberme']) && $_POST['rememberme'];

        // Create challenge token (5-minute expiry)
        $token = OraBooks_2FA::create_challenge($user->ID, $remember);

        // Logout — destroy the session just created by wp_signon
        wp_logout();

        // Redirect to 2FA challenge page
        $challenge_url = add_query_arg(array(
            'token' => $token,
        ), home_url('/2fa-challenge/'));

        OraBooks_2FA::audit('2fa_challenge_created', array(
            'user_id' => $user->ID,
            'email'   => $user->user_email,
            'ip'      => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0',
        ));

        wp_redirect(esc_url_raw($challenge_url));
        exit;
    }

    // ================================================================
    // §5.8 – 2FA SETUP
    // ================================================================

    /**
     * AJAX: Generate TOTP secret, QR URI, and 8 backup codes.
     * Secret is NOT saved until verify-setup succeeds (pending transient).
     */
    public function ajax_setup() {
        check_ajax_referer('orabooks_2fa_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Authentication required.', 'orabooks')), 401);
        }

        if (!class_exists('OraBooks_2FA')) {
            wp_send_json_error(array('message' => __('2FA system is not available.', 'orabooks')), 503);
        }

        $user   = wp_get_current_user();
        $user_id = $user->ID;
        $secret = OraBooks_2FA::generate_totp_secret();
        $qr_uri = OraBooks_2FA::generate_qr_uri($user->user_email, $secret);
        $codes  = OraBooks_2FA::generate_backup_codes();

        // Store pending setup (10-minute expiry)
        set_transient('orabooks_2fa_pending_secret_' . $user_id, $secret, 10 * MINUTE_IN_SECONDS);
        set_transient('orabooks_2fa_pending_codes_' . $user_id,  $codes,  10 * MINUTE_IN_SECONDS);

        wp_send_json_success(array(
            'secret'       => $secret,
            'qr_uri'       => $qr_uri,
            'backup_codes' => $codes,
            'message'      => __('Scan the QR code with your authenticator app, then enter the 6-digit code below to enable 2FA.', 'orabooks'),
        ));
    }

    // ================================================================
    // §5.9 – 2FA VERIFY & ENABLE
    // ================================================================

    /**
     * AJAX: Verify OTP (±30 sec), save encrypted secret + backup code hashes,
     * set is_2fa_enabled = true. Audit: 2fa_enabled.
     */
    public function ajax_verify_setup() {
        check_ajax_referer('orabooks_2fa_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Authentication required.', 'orabooks')), 401);
        }

        if (!class_exists('OraBooks_2FA')) {
            wp_send_json_error(array('message' => __('2FA system is not available.', 'orabooks')), 503);
        }

        $user   = wp_get_current_user();
        $user_id = $user->ID;
        $otp    = isset($_POST['otp_code']) ? sanitize_text_field($_POST['otp_code']) : '';

        if (empty($otp) || !preg_match('/^\d{6}$/', $otp)) {
            wp_send_json_error(array('message' => __('Please enter a valid 6-digit code.', 'orabooks')));
        }

        $secret = get_transient('orabooks_2fa_pending_secret_' . $user_id);
        if (empty($secret)) {
            wp_send_json_error(array('message' => __('Setup session expired. Please restart the setup process.', 'orabooks')));
        }

        if (!OraBooks_2FA::verify_totp($secret, $otp)) {
            wp_send_json_error(array('message' => __('Invalid code. Please check your authenticator app and try again.', 'orabooks')));
        }

        // Retrieve or regenerate backup codes
        $codes = get_transient('orabooks_2fa_pending_codes_' . $user_id);
        if (!is_array($codes)) {
            $codes = OraBooks_2FA::generate_backup_codes();
        }

        // Persist secret and backup codes
        OraBooks_2FA::save_totp_secret($user_id, $secret);
        OraBooks_2FA::save_backup_codes($user_id, $codes);
        OraBooks_2FA::enable($user_id);

        // Update is_2fa_enabled user meta for consistency with registration flow
        update_user_meta($user_id, 'is_2fa_enabled', 1);

        // Clean up pending transients
        delete_transient('orabooks_2fa_pending_secret_' . $user_id);
        delete_transient('orabooks_2fa_pending_codes_' . $user_id);

        // Audit
        OraBooks_2FA::audit('2fa_enabled', array(
            'user_id' => $user_id,
            'email'   => $user->user_email,
            'ip'      => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0',
        ));

        wp_send_json_success(array(
            'message' => __('Two-factor authentication has been enabled successfully!', 'orabooks'),
        ));
    }

    /**
     * AJAX: Disable 2FA.
     * Removes encrypted secret, backup codes, and clears 2FA flag.
     */
    public function ajax_disable() {
        check_ajax_referer('orabooks_2fa_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Authentication required.', 'orabooks')), 401);
        }

        if (!class_exists('OraBooks_2FA')) {
            wp_send_json_error(array('message' => __('2FA system is not available.', 'orabooks')), 503);
        }

        $user    = wp_get_current_user();
        $user_id = $user->ID;
        $code    = isset($_POST['verify_code']) ? sanitize_text_field($_POST['verify_code']) : '';

        // A valid TOTP or backup code is required to disable 2FA
        if (empty($code)) {
            wp_send_json_error(array('message' => __('A verification code is required to disable 2FA.', 'orabooks')));
        }

        $secret = OraBooks_2FA::get_totp_secret($user_id);
        $valid  = false;

        if ($secret && OraBooks_2FA::verify_totp($secret, $code)) {
            $valid = true;
        } elseif (OraBooks_2FA::verify_backup_code($user_id, $code)) {
            $valid = true;
        }

        if (!$valid) {
            wp_send_json_error(array('message' => __('Invalid code. 2FA was not disabled.', 'orabooks')));
        }

        OraBooks_2FA::disable($user_id);
        update_user_meta($user_id, 'is_2fa_enabled', 0);

        OraBooks_2FA::audit('2fa_disabled', array(
            'user_id' => $user_id,
            'email'   => $user->user_email,
            'ip'      => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0',
        ));

        wp_send_json_success(array(
            'message' => __('Two-factor authentication has been disabled.', 'orabooks'),
        ));
    }

    // ================================================================
    // §5.10 – 2FA CHALLENGE
    // ================================================================

    /**
     * AJAX: Verify OTP or backup code against a pending challenge token.
     * On success: set auth cookie, issue JWT tokens, return redirect URL.
     * Audit: login_success (2fa method).
     */
    public function ajax_challenge() {
        check_ajax_referer('orabooks_2fa_challenge_nonce', 'nonce');

        if (!class_exists('OraBooks_2FA')) {
            wp_send_json_error(array('message' => __('2FA system is not available.', 'orabooks')), 503);
        }

        $token       = isset($_POST['challenge_token'])   ? sanitize_text_field($_POST['challenge_token'])   : '';
        $otp         = isset($_POST['otp_code'])          ? sanitize_text_field($_POST['otp_code'])          : '';
        $backup_code = isset($_POST['backup_code'])       ? strtoupper(sanitize_text_field($_POST['backup_code'])) : '';

        $challenge = OraBooks_2FA::get_challenge($token);
        if (empty($challenge) || empty($challenge['user_id'])) {
            wp_send_json_error(array(
                'message'  => __('Your session has expired. Please log in again.', 'orabooks'),
                'redirect' => wp_login_url(),
            ));
        }

        $user_id  = (int) $challenge['user_id'];
        $remember = (bool) $challenge['remember'];
        $user     = get_user_by('ID', $user_id);

        if (!$user) {
            OraBooks_2FA::clear_challenge($token);
            wp_send_json_error(array('message' => __('Invalid session. Please log in again.', 'orabooks')));
        }

        $verified = false;
        $method   = '';

        // Try TOTP code
        if (!empty($otp)) {
            $secret = OraBooks_2FA::get_totp_secret($user_id);
            if ($secret && OraBooks_2FA::verify_totp($secret, $otp)) {
                $verified = true;
                $method   = 'totp';
            }
        }

        // Try backup code
        if (!$verified && !empty($backup_code)) {
            if (OraBooks_2FA::verify_backup_code($user_id, $backup_code)) {
                // Re-generate new backup codes when only 1 remains
                $raw_codes = get_user_meta($user_id, 'lsw_totp_backup_codes', true);
                if (!empty($raw_codes)) {
                    $remaining = count(json_decode($raw_codes, true));
                    if ($remaining <= 1) {
                        $new_codes = OraBooks_2FA::generate_backup_codes();
                        OraBooks_2FA::save_backup_codes($user_id, $new_codes);
                    }
                }
                $verified = true;
                $method   = 'backup_code';
            }
        }

        if (!$verified) {
            OraBooks_2FA::audit('2fa_challenge_failed', array(
                'user_id' => $user_id,
                'ip'      => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0',
            ));
            wp_send_json_error(array('message' => __('Invalid code. Please try again.', 'orabooks')));
        }

        // Challenge passed
        OraBooks_2FA::clear_challenge($token);

        // Set WordPress auth cookie
        wp_set_auth_cookie($user_id, $remember);
        wp_set_current_user($user_id);

        // Issue JWT tokens if JWT system is available
        $token_set = null;
        if (class_exists('OraBooks_JWT')) {
            $org_id = (int) get_user_meta($user_id, 'org_id', true);
            $role   = '';
            $subdomain = '';

            if ($org_id && class_exists('OraBooks_Users_Teams')) {
                $role = OraBooks_Users_Teams::get_instance()->get_user_role($user_id, $org_id);
            }
            if ($org_id && class_exists('OraBooks_Organizations')) {
                $org = OraBooks_Organizations::get_instance()->get_organization($org_id);
                if ($org) {
                    $subdomain = $org->subdomain;
                }
            }

            $result = OraBooks_JWT::get_instance()->issue_and_set_cookie($user_id, $org_id, $role, $subdomain, '2fa_login');
            if (!is_wp_error($result)) {
                $token_set = $result;
            }
        }

        // Audit
        OraBooks_2FA::audit('login_success', array(
            'user_id' => $user_id,
            'method'  => '2fa_' . $method,
            'ip'      => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0',
        ));

        // Determine redirect URL
        $redirect = home_url('/dashboard/');
        $redirect = apply_filters('orabooks_login_redirect', $redirect, $user_id);
        $redirect = apply_filters('lwws_login_redirect', $redirect, $user_id);

        wp_send_json_success(array(
            'message'      => __('Login successful.', 'orabooks'),
            'redirect'     => esc_url_raw($redirect),
            'access_token' => $token_set ? $token_set['access_token'] : null,
        ));
    }

    // ================================================================
    // SHORTCODES
    // ================================================================

    /**
     * Render 2FA challenge form — [orabooks_2fa_challenge]
     */
    public function render_challenge_form() {
        if (!class_exists('OraBooks_2FA')) {
            return '<p>' . __('2FA system is not available.', 'orabooks') . '</p>';
        }

        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        $challenge = OraBooks_2FA::get_challenge($token);

        if (empty($challenge)) {
            return '<div class="orabooks-2fa-error"><p>' . __('Invalid or expired challenge.', 'orabooks')
                . ' <a href="' . esc_url(wp_login_url()) . '">' . __('Log in again', 'orabooks') . '</a>.</p></div>';
        }

        ob_start();
        ?>
        <div class="orabooks-2fa-challenge-wrap">
            <div class="orabooks-2fa-card">
                <h2><?php esc_html_e('Two-Factor Authentication', 'orabooks'); ?></h2>
                <p><?php esc_html_e('Enter the 6-digit code from your authenticator app, or use a backup code.', 'orabooks'); ?></p>

                <div id="orabooks-2fa-challenge-message"></div>

                <form id="orabooks-2fa-challenge-form" class="orabooks-2fa-form" method="post">
                    <?php wp_nonce_field('orabooks_2fa_challenge_nonce', 'nonce'); ?>
                    <input type="hidden" name="action" value="orabooks_2fa_challenge">
                    <input type="hidden" name="challenge_token" value="<?php echo esc_attr($token); ?>">

                    <div class="orabooks-2fa-field">
                        <label for="otp_code"><?php esc_html_e('Authenticator Code', 'orabooks'); ?></label>
                        <input type="text" id="otp_code" name="otp_code" maxlength="6" pattern="[0-9]{6}"
                               placeholder="<?php esc_attr_e('6-digit code', 'orabooks'); ?>"
                               inputmode="numeric" autocomplete="one-time-code" autofocus>
                    </div>

                    <button type="submit" class="orabooks-2fa-button orabooks-2fa-button-primary">
                        <?php esc_html_e('Verify', 'orabooks'); ?>
                    </button>

                    <details class="orabooks-2fa-backup-toggle">
                        <summary><?php esc_html_e('Use a backup code instead', 'orabooks'); ?></summary>
                        <div class="orabooks-2fa-field">
                            <label for="backup_code"><?php esc_html_e('Backup Code', 'orabooks'); ?></label>
                            <input type="text" id="backup_code" name="backup_code" maxlength="11"
                                   placeholder="<?php esc_attr_e('Enter backup code', 'orabooks'); ?>">
                        </div>
                        <button type="submit" class="orabooks-2fa-button orabooks-2fa-button-secondary">
                            <?php esc_html_e('Verify Backup Code', 'orabooks'); ?>
                        </button>
                    </details>
                </form>
            </div>
        </div>

        <style>
            .orabooks-2fa-challenge-wrap { max-width: 480px; margin: 40px auto; }
            .orabooks-2fa-card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
            .orabooks-2fa-card h2 { margin: 0 0 10px; color: #1d2327; font-size: 22px; }
            .orabooks-2fa-card p { color: #555; margin-bottom: 20px; }
            .orabooks-2fa-field { margin: 15px 0; }
            .orabooks-2fa-field label { display: block; margin-bottom: 5px; font-weight: 600; color: #3c434a; }
            .orabooks-2fa-field input[type="text"] { width: 100%; padding: 10px 14px; font-size: 20px; letter-spacing: 4px; text-align: center; border: 1px solid #8c8f94; border-radius: 4px; box-sizing: border-box; }
            .orabooks-2fa-button { display: inline-block; padding: 10px 24px; font-size: 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
            .orabooks-2fa-button-primary { background: #2271b1; color: #fff; }
            .orabooks-2fa-button-primary:hover { background: #135e96; }
            .orabooks-2fa-button-secondary { background: #f0f0f1; color: #3c434a; border: 1px solid #c3c4c7; }
            .orabooks-2fa-backup-toggle { margin-top: 15px; }
            .orabooks-2fa-backup-toggle summary { cursor: pointer; color: #2271b1; font-weight: 500; }
            .orabooks-2fa-error { max-width: 480px; margin: 40px auto; padding: 20px; background: #fcf0f1; border: 1px solid #dc3232; border-radius: 6px; text-align: center; }
        </style>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#orabooks-2fa-challenge-form').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                var $msg  = $('#orabooks-2fa-challenge-message');
                var data  = $form.serialize();

                $msg.html('<p style="color:#666"><?php echo esc_js(__('Verifying\u2026', 'orabooks')); ?></p>');

                $.post('<?php echo admin_url('admin-ajax.php'); ?>', data, function(response) {
                    if (response.success) {
                        $msg.html('<p style="color:#46b450;font-weight:600">' + response.data.message + '</p>');
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                    } else {
                        $msg.html('<p style="color:#dc3232">' + response.data.message + '</p>');
                        $('#otp_code').val('').focus();
                    }
                }).fail(function() {
                    $msg.html('<p style="color:#dc3232"><?php echo esc_js(__('Connection error. Please try again.', 'orabooks')); ?></p>');
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render 2FA settings page — [orabooks_2fa_settings]
     */
    public function render_settings_form() {
        if (!class_exists('OraBooks_2FA')) {
            return '<p>' . __('2FA system is not available.', 'orabooks') . '</p>';
        }

        if (!is_user_logged_in()) {
            return '<p>' . sprintf(
                __('Please <a href="%s">log in</a> to manage 2FA settings.', 'orabooks'),
                esc_url(wp_login_url())
            ) . '</p>';
        }

        $user    = wp_get_current_user();
        $user_id = $user->ID;
        $is_enabled = OraBooks_2FA::is_enabled($user_id);

        // Count remaining backup codes
        $remaining_codes = 0;
        $raw_codes = get_user_meta($user_id, 'lsw_totp_backup_codes', true);
        if (!empty($raw_codes)) {
            $hashes = json_decode($raw_codes, true);
            if (is_array($hashes)) {
                $remaining_codes = count($hashes);
            }
        }

        ob_start();
        ?>
        <div class="orabooks-2fa-settings-wrap">
            <div class="orabooks-2fa-card">
                <h2><?php esc_html_e('Two-Factor Authentication (2FA)', 'orabooks'); ?></h2>
                <p><?php esc_html_e('Add an extra layer of security to your account by requiring a one-time code from your authenticator app at login.', 'orabooks'); ?></p>

                <div id="orabooks-2fa-settings-message"></div>

                <?php if ($is_enabled): ?>
                    <div class="orabooks-2fa-status-enabled">
                        <p style="color:#46b450;font-weight:600">✅ <?php esc_html_e('2FA is currently active.', 'orabooks'); ?></p>
                        <p><?php printf(esc_html__('Remaining backup codes: %d', 'orabooks'), $remaining_codes); ?></p>

                        <?php if ($remaining_codes <= 2): ?>
                        <p style="color:#b8860b;">
                            ⚠️ <?php esc_html_e('You are running low on backup codes. Disable and re-enable 2FA to generate new ones.', 'orabooks'); ?>
                        </p>
                        <?php endif; ?>

                        <hr>
                        <h3><?php esc_html_e('Disable 2FA', 'orabooks'); ?></h3>
                        <p><?php esc_html_e('Enter a code from your authenticator app or a backup code to disable:', 'orabooks'); ?></p>
                        <form id="orabooks-2fa-disable-form" class="orabooks-2fa-form">
                            <?php wp_nonce_field('orabooks_2fa_nonce', 'nonce'); ?>
                            <input type="hidden" name="action" value="orabooks_2fa_disable">
                            <div class="orabooks-2fa-field">
                                <input type="text" name="verify_code" maxlength="11"
                                       placeholder="<?php esc_attr_e('6-digit code or backup code', 'orabooks'); ?>"
                                       style="width:220px" required>
                            </div>
                            <button type="submit" class="orabooks-2fa-button orabooks-2fa-button-secondary"
                                    onclick="return confirm('<?php echo esc_js(__('Are you sure? This will reduce your account security.', 'orabooks')); ?>')">
                                <?php esc_html_e('Disable 2FA', 'orabooks'); ?>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="orabooks-2fa-setup-ui" id="orabooks-2fa-setup-ui">
                        <button id="orabooks-2fa-start-setup" class="orabooks-2fa-button orabooks-2fa-button-primary">
                            <?php esc_html_e('Setup 2FA', 'orabooks'); ?>
                        </button>

                        <div id="orabooks-2fa-setup-steps" style="display:none; margin-top:20px;">
                            <div id="orabooks-2fa-step-qr">
                                <h3><?php esc_html_e('Step 1: Scan QR Code', 'orabooks'); ?></h3>
                                <p><?php esc_html_e('Scan this code with Google Authenticator, Authy, or similar:', 'orabooks'); ?></p>
                                <div style="text-align:center;margin:15px 0;">
                                    <div id="orabooks-2fa-qr-code"></div>
                                </div>
                                <details>
                                    <summary><?php esc_html_e("Can't scan the QR code?", 'orabooks'); ?></summary>
                                    <p><?php esc_html_e('Enter this secret manually:', 'orabooks'); ?></p>
                                    <code id="orabooks-2fa-secret-text" style="font-size:14px;word-break:break-all;background:#f0f0f1;padding:8px;border-radius:4px;display:inline-block;"></code>
                                </details>
                            </div>

                            <div style="margin-top:20px;">
                                <h3><?php esc_html_e('Backup Codes (single-use)', 'orabooks'); ?></h3>
                                <p style="color:#b8860b;font-weight:600;">
                                    ⚠️ <?php esc_html_e('Save these codes in a secure place. Each can be used only once.', 'orabooks'); ?>
                                </p>
                                <div id="orabooks-2fa-codes-list" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:10px 0;"></div>
                            </div>

                            <div style="margin-top:20px;">
                                <h3><?php esc_html_e('Step 2: Verify Setup', 'orabooks'); ?></h3>
                                <p><?php esc_html_e('Enter the 6-digit code from your authenticator app:', 'orabooks'); ?></p>
                                <form id="orabooks-2fa-verify-form" class="orabooks-2fa-form">
                                    <?php wp_nonce_field('orabooks_2fa_nonce', 'nonce'); ?>
                                    <input type="hidden" name="action" value="orabooks_2fa_verify_setup">
                                    <div class="orabooks-2fa-field">
                                        <input type="text" name="otp_code" maxlength="6" pattern="[0-9]{6}"
                                               placeholder="<?php esc_attr_e('6-digit code', 'orabooks'); ?>"
                                               inputmode="numeric" style="width:200px;font-size:18px;letter-spacing:3px;" required>
                                    </div>
                                    <button type="submit" class="orabooks-2fa-button orabooks-2fa-button-primary">
                                        <?php esc_html_e('Verify & Enable', 'orabooks'); ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
            .orabooks-2fa-settings-wrap { max-width: 560px; margin: 20px auto; }
            .orabooks-2fa-card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
            .orabooks-2fa-card h2 { margin: 0 0 10px; color: #1d2327; font-size: 22px; }
            .orabooks-2fa-card h3 { margin: 15px 0 5px; color: #2c3338; font-size: 16px; }
            .orabooks-2fa-card p { color: #555; }
            .orabooks-2fa-card hr { border: none; border-top: 1px solid #eee; margin: 20px 0; }
            .orabooks-2fa-field { margin: 12px 0; }
            .orabooks-2fa-field label { display: block; margin-bottom: 5px; font-weight: 600; color: #3c434a; }
            .orabooks-2fa-field input[type="text"] { padding: 8px 12px; font-size: 16px; border: 1px solid #8c8f94; border-radius: 4px; box-sizing: border-box; }
            .orabooks-2fa-button { display: inline-block; padding: 10px 24px; font-size: 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
            .orabooks-2fa-button-primary { background: #2271b1; color: #fff; }
            .orabooks-2fa-button-primary:hover { background: #135e96; }
            .orabooks-2fa-button-secondary { background: #f0f0f1; color: #3c434a; border: 1px solid #c3c4c7; }
            .orabooks-2fa-card details { margin: 10px 0; }
            .orabooks-2fa-card details summary { cursor: pointer; color: #2271b1; font-weight: 500; }
        </style>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            <?php if (!$is_enabled): ?>
            $('#orabooks-2fa-start-setup').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php echo esc_js(__('Generating\u2026', 'orabooks')); ?>');

                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'orabooks_2fa_setup',
                    nonce: '<?php echo wp_create_nonce('orabooks_2fa_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#orabooks-2fa-setup-steps').show();
                        $btn.hide();

                        // Generate QR code image using Google Chart API
                        var qrUrl = 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl='
                                  + encodeURIComponent(response.data.qr_uri);
                        $('#orabooks-2fa-qr-code').html(
                            '<img src="' + qrUrl + '" alt="QR Code" width="200" height="200" style="border:1px solid #ddd;border-radius:4px;">'
                        );

                        $('#orabooks-2fa-secret-text').text(response.data.secret);

                        var codesHtml = '';
                        $.each(response.data.backup_codes, function(i, code) {
                            codesHtml += '<code style="padding:8px;background:#fff;border:1px dashed #aaa;border-radius:4px;text-align:center;font-size:13px;">' + code + '</code>';
                        });
                        $('#orabooks-2fa-codes-list').html(codesHtml);
                    } else {
                        alert(response.data.message || '<?php echo esc_js(__('Setup failed.', 'orabooks')); ?>');
                        $btn.prop('disabled', false).text('<?php echo esc_js(__('Setup 2FA', 'orabooks')); ?>');
                    }
                }).fail(function() {
                    alert('<?php echo esc_js(__('Connection error.', 'orabooks')); ?>');
                    $btn.prop('disabled', false).text('<?php echo esc_js(__('Setup 2FA', 'orabooks')); ?>');
                });
            });

            $('#orabooks-2fa-verify-form').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                var $msg  = $('#orabooks-2fa-settings-message');

                $msg.html('<p style="color:#666"><?php echo esc_js(__('Verifying\u2026', 'orabooks')); ?></p>');

                $.post('<?php echo admin_url('admin-ajax.php'); ?>', $form.serialize(), function(response) {
                    if (response.success) {
                        $msg.html('<p style="color:#46b450;font-weight:600">' + response.data.message + '</p>');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $msg.html('<p style="color:#dc3232">' + response.data.message + '</p>');
                    }
                }).fail(function() {
                    $msg.html('<p style="color:#dc3232"><?php echo esc_js(__('Connection error.', 'orabooks')); ?></p>');
                });
            });
            <?php else: ?>
            $('#orabooks-2fa-disable-form').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                var $msg  = $('#orabooks-2fa-settings-message');

                $msg.html('<p style="color:#666"><?php echo esc_js(__('Processing\u2026', 'orabooks')); ?></p>');

                $.post('<?php echo admin_url('admin-ajax.php'); ?>', $form.serialize(), function(response) {
                    if (response.success) {
                        $msg.html('<p style="color:#46b450;font-weight:600">' + response.data.message + '</p>');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $msg.html('<p style="color:#dc3232">' + response.data.message + '</p>');
                    }
                }).fail(function() {
                    $msg.html('<p style="color:#dc3232"><?php echo esc_js(__('Connection error.', 'orabooks')); ?></p>');
                });
            });
            <?php endif; ?>
        });
        </script>
        <?php
        return ob_get_clean();
    }

    // ================================================================
    // REWRITE RULES & ASSETS
    // ================================================================

    /**
     * Register rewrite rule for /2fa-challenge/
     */
    public function register_challenge_rewrite() {
        add_rewrite_rule(
            '^2fa-challenge/?$',
            'index.php?orabooks_2fa_challenge_page=1',
            'top'
        );

        // First-run soft flush so the rewrite rule takes effect immediately.
        if (!get_option('orabooks_2fa_rewrite_flushed')) {
            flush_rewrite_rules(false);
            update_option('orabooks_2fa_rewrite_flushed', true);
        }
    }

    /**
     * Add challenge query var
     */
    public function add_challenge_query_var($vars) {
        $vars[] = 'orabooks_2fa_challenge_page';
        return $vars;
    }

    /**
     * Handle /2fa-challenge/ template redirect.
     * Renders a standalone page with the 2FA challenge form.
     */
    public function handle_challenge_page() {
        if (!get_query_var('orabooks_2fa_challenge_page')) {
            return;
        }

        wp_enqueue_script('jquery');
        remove_filter('template_redirect', 'redirect_canonical');

        status_header(200);
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php _e('Two-Factor Authentication', 'orabooks'); ?></title>
            <?php wp_head(); ?>
        </head>
        <body style="background:#f0f0f1;display:flex;align-items:center;min-height:100vh;margin:0;padding:20px;box-sizing:border-box;">
            <div style="width:100%;">
                <?php echo $this->render_challenge_form(); ?>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * Enqueue jQuery on pages with 2FA shortcodes
     */
    public function enqueue_assets() {
        global $post;
        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'orabooks_2fa_challenge') ||
            has_shortcode($post->post_content, 'orabooks_2fa_settings')
        )) {
            wp_enqueue_script('jquery');
        }
    }
}

// Initialize
OraBooks_2FA_Handler::get_instance();
