<?php

/**
 * SL-013 – 2FA AJAX Handlers
 *
 * Registers WordPress AJAX endpoints for:
 *   - 2FA Setup   (lsw_2fa_setup)        → POST /api/auth/2fa/setup
 *   - 2FA Enable  (lsw_2fa_verify_setup) → POST /api/auth/2fa/verify-setup
 *   - 2FA Disable (lsw_2fa_disable)      → POST /api/auth/2fa/disable
 *   - 2FA Challenge (lsw_2fa_challenge)  → POST /api/auth/2fa/challenge
 *
 * Also provides:
 *   - Login interception for 2FA-enabled users
 *   - [orabooks_2fa_challenge] shortcode
 *   - [orabooks_2fa_settings]  shortcode
 *   - /2fa-challenge/ rewrite route
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

            // SL-013 §5.4: Login interception for 2FA-enabled users
            add_action('wp_login', [$this, 'intercept_2fa_login'], 1, 2);

            // Register 2FA shortcodes
            add_shortcode('orabooks_2fa_challenge', [$this, 'render_challenge_form']);
            add_shortcode('orabooks_2fa_settings',  [$this, 'render_settings_form']);

            // Enqueue assets for 2FA pages
            add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

            // Register rewrite rule for /2fa-challenge
            add_action('init', [$this, 'register_challenge_rewrite']);
            add_filter('query_vars', [$this, 'add_challenge_query_var']);
            add_action('template_redirect', [$this, 'handle_challenge_page']);
        }

        // ================================================================
        // LOGIN INTERCEPTION
        // ================================================================

        /**
         * SL-013 §5.4: Intercept login for 2FA-enabled users.
         * Runs early in wp_login hook (priority 1).
         * Destroys the session just created by wp_signon and redirects
         * to the 2FA challenge page with a temporary token.
         */
        public function intercept_2fa_login($user_login, $user) {
            if (!class_exists('OraBooks_2FA')) {
                return;
            }

            if (!OraBooks_2FA::is_enabled($user->ID)) {
                return; // No 2FA required for this user
            }

            // Capture remember-me from POST (it was already processed by wp_signon)
            $remember = isset($_POST['remember']) && $_POST['remember'] === 'Yes';

            // Create a challenge token (stored in transient with 5-min expiry)
            $token = OraBooks_2FA::create_challenge($user->ID, $remember);

            // Logout — destroy the session just created by wp_signon
            wp_logout();

            // Redirect to the 2FA challenge page with token
            $challenge_url = add_query_arg([
                'token' => $token,
            ], home_url('/2fa-challenge/'));

            OraBooks_2FA::audit('2fa_challenge_created', [
                'user_id' => $user->ID,
                'email'   => $user->user_email,
                'ip'      => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ]);

            wp_redirect(esc_url_raw($challenge_url));
            exit;
        }

        // ================================================================
        // 5.8 – 2FA SETUP
        // ================================================================

        /**
         * Generates TOTP secret, QR URI, and 8 backup codes.
         * The secret is NOT yet saved/enabled until verify-setup succeeds.
         */
        public function handle_setup() {
            check_ajax_referer('lsw_2fa_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => __('Authentication required.', 'login-sidebar-widget')], 401);
            }

            $user    = wp_get_current_user();
            $secret  = OraBooks_2FA::generate_totp_secret();
            $qr_uri  = OraBooks_2FA::generate_qr_uri($user->user_email, $secret);
            $codes   = OraBooks_2FA::generate_backup_codes();

            set_transient('lsw_2fa_pending_secret_' . $user->ID, $secret, 10 * MINUTE_IN_SECONDS);
            set_transient('lsw_2fa_pending_codes_' . $user->ID,  $codes,  10 * MINUTE_IN_SECONDS);

            wp_send_json_success([
                'secret'       => $secret,
                'qr_uri'       => $qr_uri,
                'backup_codes' => $codes,
                'message'      => __('Scan the QR code with your authenticator app, then enter the 6-digit code below to enable 2FA.', 'login-sidebar-widget'),
            ]);
        }

        // ================================================================
        // 5.9 – 2FA VERIFY & ENABLE
        // ================================================================

        /**
         * Verify OTP (±30 sec). Set is_2fa_enabled=true, store secret and backup code hashes.
         * Audit: 2fa_enabled.
         */
        public function handle_verify_setup() {
            check_ajax_referer('lsw_2fa_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => __('Authentication required.', 'login-sidebar-widget')], 401);
            }

            $user = wp_get_current_user();
            $otp  = isset($_POST['otp_code']) ? sanitize_text_field($_POST['otp_code']) : '';

            if (empty($otp) || !preg_match('/^\d{6}$/', $otp)) {
                wp_send_json_error(['message' => __('Please enter a valid 6-digit code.', 'login-sidebar-widget')]);
            }

            $secret = get_transient('lsw_2fa_pending_secret_' . $user->ID);
            if (empty($secret)) {
                wp_send_json_error(['message' => __('Setup session expired. Please restart the setup process.', 'login-sidebar-widget')]);
            }

            if (!OraBooks_2FA::verify_totp($secret, $otp)) {
                wp_send_json_error(['message' => __('Invalid code. Please check your authenticator app and try again.', 'login-sidebar-widget')]);
            }

            $codes = get_transient('lsw_2fa_pending_codes_' . $user->ID);
            if (!is_array($codes)) {
                $codes = OraBooks_2FA::generate_backup_codes();
            }

            OraBooks_2FA::save_totp_secret($user->ID, $secret);
            OraBooks_2FA::save_backup_codes($user->ID, $codes);
            OraBooks_2FA::enable($user->ID);

            delete_transient('lsw_2fa_pending_secret_' . $user->ID);
            delete_transient('lsw_2fa_pending_codes_' . $user->ID);

            OraBooks_2FA::audit('2fa_enabled', [
                'user_id' => $user->ID,
                'email'   => $user->user_email,
                'ip'      => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ]);

            wp_send_json_success([
                'message' => __('Two-factor authentication has been enabled successfully!', 'login-sidebar-widget'),
            ]);
        }

        // ================================================================
        // DISABLE 2FA
        // ================================================================

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

        // ================================================================
        // 5.10 – 2FA CHALLENGE
        // ================================================================

        /**
         * Input: challenge_token, otp_code or backup_code.
         * On success: set auth cookie, redirect to dashboard.
         * Audit: login_success (2fa method).
         */
        public function handle_challenge() {
            check_ajax_referer('lsw_2fa_challenge_nonce', 'nonce');

            $token       = isset($_POST['challenge_token'])   ? sanitize_text_field($_POST['challenge_token'])   : '';
            $otp         = isset($_POST['otp_code'])          ? sanitize_text_field($_POST['otp_code'])          : '';
            $backup_code = isset($_POST['backup_code'])       ? strtoupper(sanitize_text_field($_POST['backup_code'])) : '';

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
                if (class_exists('Login_Log_Adds')) {
                    $lla = new Login_Log_Adds;
                    $ip  = apply_filters('lwws_log_ip', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
                    $lla->log_add($ip, 'SL-013 2FA: challenge_failed [user_id:' . $user_id . ']', date('Y-m-d H:i:s'), 'failed');
                }
                wp_send_json_error(['message' => __('Invalid code. Please try again.', 'login-sidebar-widget')]);
            }

            // Challenge passed
            OraBooks_2FA::clear_challenge($token);

            wp_set_auth_cookie($user_id, $remember);
            wp_set_current_user($user_id);

            OraBooks_2FA::audit('login_success', [
                'user_id' => $user_id,
                'method'  => '2fa',
                'ip'      => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ]);

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

        // ================================================================
        // 2FA CHALLENGE FORM (Shortcode: [orabooks_2fa_challenge])
        // ================================================================

        public function render_challenge_form() {
            if (!class_exists('OraBooks_2FA')) {
                return '<p>' . __('2FA system is not available.', 'login-sidebar-widget') . '</p>';
            }

            $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
            $challenge = OraBooks_2FA::get_challenge($token);

            if (empty($challenge)) {
                return '<div class="orabooks-2fa-error"><p>' . __('Invalid or expired challenge.', 'login-sidebar-widget') . ' <a href="' . esc_url(wp_login_url()) . '">' . __('Log in again', 'login-sidebar-widget') . '</a>.</p></div>';
            }

            ob_start();
            ?>
            <div class="orabooks-2fa-challenge-wrap">
                <div class="orabooks-2fa-card">
                    <h2><?php esc_html_e('Two-Factor Authentication', 'login-sidebar-widget'); ?></h2>
                    <p><?php esc_html_e('Enter the 6-digit code from your authenticator app, or use a backup code.', 'login-sidebar-widget'); ?></p>

                    <div id="orabooks-2fa-challenge-message"></div>

                    <form id="orabooks-2fa-challenge-form" class="orabooks-2fa-form" method="post">
                        <?php wp_nonce_field('lsw_2fa_challenge_nonce', 'nonce'); ?>
                        <input type="hidden" name="action" value="lsw_2fa_challenge">
                        <input type="hidden" name="challenge_token" value="<?php echo esc_attr($token); ?>">

                        <div class="orabooks-2fa-field">
                            <label for="otp_code"><?php esc_html_e('Authenticator Code', 'login-sidebar-widget'); ?></label>
                            <input type="text" id="otp_code" name="otp_code" maxlength="6" pattern="[0-9]{6}"
                                   placeholder="<?php esc_attr_e('6-digit code', 'login-sidebar-widget'); ?>"
                                   inputmode="numeric" autocomplete="one-time-code" autofocus>
                        </div>

                        <button type="submit" class="orabooks-2fa-button orabooks-2fa-button-primary">
                            <?php esc_html_e('Verify', 'login-sidebar-widget'); ?>
                        </button>

                        <details class="orabooks-2fa-backup-toggle">
                            <summary><?php esc_html_e('Use a backup code instead', 'login-sidebar-widget'); ?></summary>
                            <div class="orabooks-2fa-field">
                                <label for="backup_code"><?php esc_html_e('Backup Code', 'login-sidebar-widget'); ?></label>
                                <input type="text" id="backup_code" name="backup_code" maxlength="11"
                                       placeholder="<?php esc_attr_e('Enter backup code', 'login-sidebar-widget'); ?>">
                            </div>
                            <button type="submit" class="orabooks-2fa-button orabooks-2fa-button-secondary">
                                <?php esc_html_e('Verify Backup Code', 'login-sidebar-widget'); ?>
                            </button>
                        </details>
                    </form>
                </div>
            </div>
            <script type="text/javascript">
jQuery(document).ready(function($) {
    $('#orabooks-2fa-challenge-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $msg  = $('#orabooks-2fa-challenge-message');
        var data  = $form.serialize();

        $msg.html('<p style="color:#666">Verifying&#8230;</p>');

        $.post('<?php echo admin_url('admin-ajax.php'); ?>', data, function(response) {
            if (response.success) {
                $msg.html('<p style="color:#46b450;font-weight:600">' + response.data.message + '</p>');
                window.location.href = response.data.redirect;
            } else {
                $msg.html('<p style="color:#dc3232">' + response.data.message + '</p>');
                $('#otp_code').val('').focus();
            }
        }).fail(function() {
            $msg.html('<p style="color:#dc3232"><?php echo esc_js(__('Connection error. Please try again.', 'login-sidebar-widget')); ?></p>');
        });
    });
});
</script>
            <style>
                .orabooks-2fa-challenge-wrap { max-width: 480px; margin: 40px auto; }
                .orabooks-2fa-card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
                .orabooks-2fa-card h2 { margin: 0 0 10px; color: #1d2327; }
                .orabooks-2fa-field { margin: 15px 0; }
                .orabooks-2fa-field label { display: block; margin-bottom: 5px; font-weight: 600; color: #3c434a; }
                .orabooks-2fa-field input[type="text"] { width: 100%; padding: 10px 14px; font-size: 20px; letter-spacing: 4px; text-align: center; border: 1px solid #8c8f94; border-radius: 4px; box-sizing: border-box; }
                .orabooks-2fa-button { display: inline-block; padding: 10px 24px; font-size: 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
                .orabooks-2fa-button-primary { background: #2271b1; color: #fff; }
                .orabooks-2fa-button-primary:hover { background: #135e96; }
                .orabooks-2fa-button-secondary { background: #f0f0f1; color: #3c434a; border: 1px solid #c3c4c7; }
                .orabooks-2fa-backup-toggle { margin-top: 15px; }
                .orabooks-2fa-backup-toggle summary { cursor: pointer; color: #2271b1; }
                .orabooks-2fa-error { max-width: 480px; margin: 40px auto; padding: 20px; background: #fcf0f1; border: 1px solid #dc3232; border-radius: 6px; text-align: center; }
            </style>
            <?php
            return ob_get_clean();
        }

        // ================================================================
        // 2FA SETTINGS PAGE (Shortcode: [orabooks_2fa_settings])
        // ================================================================

        public function render_settings_form() {
            if (!class_exists('OraBooks_2FA')) {
                return '<p>' . __('2FA system is not available.', 'login-sidebar-widget') . '</p>';
            }

            if (!is_user_logged_in()) {
                return '<p>' . __('Please <a href="' . esc_url(wp_login_url()) . '">log in</a> to manage 2FA settings.', 'login-sidebar-widget') . '</p>';
            }

            $user = wp_get_current_user();
            $is_enabled = OraBooks_2FA::is_enabled($user->ID);
            $backup_codes_meta = get_user_meta($user->ID, 'lsw_totp_backup_codes', true);
            $remaining_codes = 0;
            if (!empty($backup_codes_meta)) {
                $hashes = json_decode($backup_codes_meta, true);
                if (is_array($hashes)) {
                    $remaining_codes = count($hashes);
                }
            }

            ob_start();
            ?>
            <div class="orabooks-2fa-settings-wrap">
                <div class="orabooks-2fa-card">
                    <h2><?php esc_html_e('Two-Factor Authentication (2FA)', 'login-sidebar-widget'); ?></h2>
                    <p><?php esc_html_e('Add an extra layer of security to your account by requiring a one-time code from your authenticator app at login.', 'login-sidebar-widget'); ?></p>

                    <div id="orabooks-2fa-settings-message"></div>

                    <?php if ($is_enabled): ?>
                        <div class="orabooks-2fa-status-enabled">
                            <p style="color:#46b450;font-weight:600">✅ <?php esc_html_e('2FA is currently active.', 'login-sidebar-widget'); ?></p>
                            <p><?php printf(esc_html__('Remaining backup codes: %d', 'login-sidebar-widget'), $remaining_codes); ?></p>

                            <hr>
                            <h3><?php esc_html_e('Disable 2FA', 'login-sidebar-widget'); ?></h3>
                            <p><?php esc_html_e('Enter a code from your authenticator app or a backup code to disable:', 'login-sidebar-widget'); ?></p>
                            <form id="orabooks-2fa-disable-form" class="orabooks-2fa-form">
                                <?php wp_nonce_field('lsw_2fa_nonce', 'nonce'); ?>
                                <input type="hidden" name="action" value="lsw_2fa_disable">
                                <div class="orabooks-2fa-field">
                                    <input type="text" name="otp_code" maxlength="11"
                                           placeholder="<?php esc_attr_e('6-digit code or backup code', 'login-sidebar-widget'); ?>"
                                           style="width:220px" required>
                                </div>
                                <button type="submit" class="orabooks-2fa-button orabooks-2fa-button-secondary"
                                        onclick="return confirm('<?php echo esc_js(__('Are you sure? This will reduce your account security.', 'login-sidebar-widget')); ?>')">
                                    <?php esc_html_e('Disable 2FA', 'login-sidebar-widget'); ?>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="orabooks-2fa-setup-ui" id="orabooks-2fa-setup-ui">
                            <button id="orabooks-2fa-start-setup" class="orabooks-2fa-button orabooks-2fa-button-primary">
                                <?php esc_html_e('Setup 2FA', 'login-sidebar-widget'); ?>
                            </button>

                            <div id="orabooks-2fa-setup-steps" style="display:none; margin-top:20px;">
                                <div id="orabooks-2fa-step-qr">
                                    <h3><?php esc_html_e('Step 1: Scan QR Code', 'login-sidebar-widget'); ?></h3>
                                    <p><?php esc_html_e('Scan this code with Google Authenticator, Authy, or similar:', 'login-sidebar-widget'); ?></p>
                                    <div style="text-align:center;margin:15px 0;">
                                        <div id="orabooks-2fa-qr-code"></div>
                                    </div>
                                    <details>
                                        <summary><?php esc_html_e("Can't scan the QR code?", 'login-sidebar-widget'); ?></summary>
                                        <p><?php esc_html_e('Enter this secret manually:', 'login-sidebar-widget'); ?></p>
                                        <code id="orabooks-2fa-secret-text" style="font-size:14px;word-break:break-all;"></code>
                                    </details>
                                </div>

                                <div style="margin-top:20px;">
                                    <h3><?php esc_html_e('Backup Codes (single-use)', 'login-sidebar-widget'); ?></h3>
                                    <p style="color:#b8860b;font-weight:600;">⚠️ <?php esc_html_e('Save these codes in a secure place. Each can be used only once.', 'login-sidebar-widget'); ?></p>
                                    <div id="orabooks-2fa-codes-list" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:10px 0;"></div>
                                </div>

                                <div style="margin-top:20px;">
                                    <h3><?php esc_html_e('Step 2: Verify Setup', 'login-sidebar-widget'); ?></h3>
                                    <p><?php esc_html_e('Enter the 6-digit code from your authenticator app:', 'login-sidebar-widget'); ?></p>
                                    <form id="orabooks-2fa-verify-form" class="orabooks-2fa-form">
                                        <?php wp_nonce_field('lsw_2fa_nonce', 'nonce'); ?>
                                        <input type="hidden" name="action" value="lsw_2fa_verify_setup">
                                        <div class="orabooks-2fa-field">
                                            <input type="text" name="otp_code" maxlength="6" pattern="[0-9]{6}"
                                                   placeholder="<?php esc_attr_e('6-digit code', 'login-sidebar-widget'); ?>"
                                                   inputmode="numeric" style="width:200px;font-size:18px;letter-spacing:3px;" required>
                                        </div>
                                        <button type="submit" class="orabooks-2fa-button orabooks-2fa-button-primary">
                                            <?php esc_html_e('Verify & Enable', 'login-sidebar-widget'); ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top:25px;padding-top:20px;border-top:1px solid #eee;">
                            <h3><?php esc_html_e('How it works', 'login-sidebar-widget'); ?></h3>
                            <ol style="margin-left:18px;">
                                <li><?php esc_html_e('Click "Setup 2FA" to generate a QR code', 'login-sidebar-widget'); ?></li>
                                <li><?php esc_html_e('Scan the QR code with your authenticator app', 'login-sidebar-widget'); ?></li>
                                <li><?php esc_html_e('Enter the 6-digit code from the app to verify', 'login-sidebar-widget'); ?></li>
                                <li><?php esc_html_e('Save the backup codes somewhere safe', 'login-sidebar-widget'); ?></li>
                            </ol>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <script type="text/javascript">
jQuery(document).ready(function($) {
    <?php if (!$is_enabled): ?>
    $('#orabooks-2fa-start-setup').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Generating&#8230;', 'login-sidebar-widget')); ?>');

        $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'lsw_2fa_setup',
            nonce: '<?php echo wp_create_nonce('lsw_2fa_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                $('#orabooks-2fa-setup-steps').show();
                $btn.hide();

                var qrUrl = 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' + encodeURIComponent(response.data.qr_uri);
                $('#orabooks-2fa-qr-code').html('<img src="' + qrUrl + '" alt="QR Code" width="200" height="200" style="border:1px solid #ddd;border-radius:4px;">');

                $('#orabooks-2fa-secret-text').text(response.data.secret);

                var codesHtml = '';
                $.each(response.data.backup_codes, function(i, code) {
                    codesHtml += '<code style="padding:8px;background:#fff;border:1px dashed #aaa;border-radius:4px;text-align:center;">' + code + '</code>';
                });
                $('#orabooks-2fa-codes-list').html(codesHtml);
            } else {
                alert(response.data.message || '<?php echo esc_js(__('Setup failed.', 'login-sidebar-widget')); ?>');
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Setup 2FA', 'login-sidebar-widget')); ?>');
            }
        }).fail(function() {
            alert('<?php echo esc_js(__('Connection error.', 'login-sidebar-widget')); ?>');
            $btn.prop('disabled', false).text('<?php echo esc_js(__('Setup 2FA', 'login-sidebar-widget')); ?>');
        });
    });

    $('#orabooks-2fa-verify-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $msg  = $('#orabooks-2fa-settings-message');

        $msg.html('<p style="color:#666"><?php echo esc_js(__('Verifying&#8230;', 'login-sidebar-widget')); ?></p>');

        $.post('<?php echo admin_url('admin-ajax.php'); ?>', $form.serialize(), function(response) {
            if (response.success) {
                $msg.html('<p style="color:#46b450;font-weight:600">' + response.data.message + '</p>');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                $msg.html('<p style="color:#dc3232">' + response.data.message + '</p>');
            }
        }).fail(function() {
            $msg.html('<p style="color:#dc3232"><?php echo esc_js(__('Connection error.', 'login-sidebar-widget')); ?></p>');
        });
    });
    <?php else: ?>
    $('#orabooks-2fa-disable-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $msg  = $('#orabooks-2fa-settings-message');

        $msg.html('<p style="color:#666"><?php echo esc_js(__('Processing&#8230;', 'login-sidebar-widget')); ?></p>');

        $.post('<?php echo admin_url('admin-ajax.php'); ?>', $form.serialize(), function(response) {
            if (response.success) {
                $msg.html('<p style="color:#46b450;font-weight:600">' + response.data.message + '</p>');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                $msg.html('<p style="color:#dc3232">' + response.data.message + '</p>');
            }
        }).fail(function() {
            $msg.html('<p style="color:#dc3232"><?php echo esc_js(__('Connection error.', 'login-sidebar-widget')); ?></p>');
        });
    });
    <?php endif; ?>
});
</script>
            <?php
            return ob_get_clean();
        }

        // ================================================================
        // ASSETS & REWRITE RULES
        // ================================================================

        public function enqueue_assets() {
            global $post;
            if (is_a($post, 'WP_Post') && (
                has_shortcode($post->post_content, 'orabooks_2fa_challenge') ||
                has_shortcode($post->post_content, 'orabooks_2fa_settings')
            )) {
                wp_enqueue_script('jquery');
            }
        }

        public function register_challenge_rewrite() {
            add_rewrite_rule(
                '^2fa-challenge/?$',
                'index.php?orabooks_2fa_challenge=1',
                'top'
            );
        }

        public function add_challenge_query_var($vars) {
            $vars[] = 'orabooks_2fa_challenge';
            return $vars;
        }

        public function handle_challenge_page() {
            if (!get_query_var('orabooks_2fa_challenge')) {
                return;
            }

            // Ensure jQuery is available for the inline AJAX code
            wp_enqueue_script('jquery');

            // Prevent WordPress canonical redirect from interfering
            remove_filter('template_redirect', 'redirect_canonical');

            status_header(200);
            ?>
            <!DOCTYPE html>
            <html <?php language_attributes(); ?>>
            <head>
                <meta charset="<?php bloginfo('charset'); ?>">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title><?php _e('Two-Factor Authentication', 'login-sidebar-widget'); ?></title>
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
    }
}
