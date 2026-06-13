<?php
/**
 * SL-013 – Forgot & Reset Password
 *
 * Implements the forgot/reset password flow with:
 *   - Forgot Password (rate limited: 3/hr per email)
 *   - Reset Password (validate token, enforce password policy, revoke JWT)
 *   - Audit events: password_reset_requested, password_reset_completed
 *   - Integration with OraBooks_JWT for token revocation on password reset
 *   - Password policy enforcement (SL-013: 8+ chars, upper, lower, digit, special)
 *
 * Build Order: SL-004 → SL-013 → SL-003 → SL-017 → SL-139 → SL-068
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Password_Reset {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Rate limit: max forgot password requests per email per hour
     */
    const FORGOT_RATE_LIMIT = 3;
    const FORGOT_RATE_WINDOW = 3600; // 1 hour in seconds

    /**
     * Reset token expiry (1 hour)
     */
    const RESET_TOKEN_EXPIRY = 3600;

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
        // AJAX endpoint: forgot password (send reset link)
        add_action('wp_ajax_nopriv_orabooks_forgot_password', array($this, 'ajax_forgot_password'));
        add_action('wp_ajax_orabooks_forgot_password',        array($this, 'ajax_forgot_password'));

        // AJAX endpoint: reset password (set new password)
        add_action('wp_ajax_nopriv_orabooks_reset_password',  array($this, 'ajax_reset_password'));
        add_action('wp_ajax_orabooks_reset_password',         array($this, 'ajax_reset_password'));

        // Register shortcode for the reset password form
        add_shortcode('orabooks_reset_password_form', array($this, 'render_reset_form'));

        // Register shortcode for the forgot password form
        add_shortcode('orabooks_forgot_form', array(__CLASS__, 'render_forgot_form'));

        // Handle reset password form submission via POST
        add_action('init', array($this, 'handle_reset_form_submit'));

        // Enqueue assets on pages with the reset form shortcode
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    // ================================================================
    // FORGOT PASSWORD
    // ================================================================

    /**
     * AJAX handler: Send password reset link.
     * Rate limited to 3 requests per hour per email.
     *
     * POST parameters:
     *   - email: User's email address
     *   - nonce: WordPress nonce (orabooks_forgot_nonce)
     */
    public function ajax_forgot_password() {
        check_ajax_referer('orabooks_forgot_nonce', 'nonce');

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array(
                'message' => __('Please enter a valid email address.', 'orabooks'),
            ));
        }

        // Rate limit check: 3/hr per email
        if (class_exists('OraBooks_Rate_Limiter')) {
            $rate_key = 'forgot_' . md5($email);
            $rate_check = OraBooks_Rate_Limiter::get_instance()->check_and_increment(
                'forgot_password',
                $rate_key,
                self::FORGOT_RATE_LIMIT,
                self::FORGOT_RATE_WINDOW
            );

            if (is_wp_error($rate_check)) {
                wp_send_json_error(array(
                    'message' => __('Too many password reset requests. Please try again in an hour.', 'orabooks'),
                ));
            }
        }

        $user = get_user_by('email', $email);

        // Always return success to prevent email enumeration attacks
        if (!$user) {
            wp_send_json_success(array(
                'message' => __('If an account with that email exists, a password reset link has been sent.', 'orabooks'),
            ));
        }

        // Generate reset key using WordPress core function
        $reset_key = get_password_reset_key($user);

        if (is_wp_error($reset_key)) {
            wp_send_json_error(array(
                'message' => __('Unable to generate password reset link. Please try again.', 'orabooks'),
            ));
        }

        // Build reset URL
        $reset_url = add_query_arg(array(
            'action'   => 'orabooks_reset_pwd',
            'key'      => $reset_key,
            'login'    => rawurlencode($user->user_login),
        ), home_url('/'));

        // Send email
        $subject = __('Password Reset Request', 'orabooks');
        $message = sprintf(
            __("Hello %s,\n\nYou requested a password reset for your account.\n\nClick here to reset your password:\n%s\n\nThis link will expire in 1 hour.\n\nIf you didn't request this, please ignore this email.\n\nBest regards,\nThe %s Team", 'orabooks'),
            $user->display_name,
            $reset_url,
            get_bloginfo('name')
        );

        $sent = wp_mail($email, $subject, $message);

        if (!$sent) {
            // Don't reveal email failure to prevent enumeration
            error_log('[OraBooks Password Reset] Failed to send reset email to: ' . $email);
        }

        // Audit event
        do_action('orabooks_security_event', 'password_reset_requested', array(
            'user_id'    => $user->ID,
            'email'      => $email,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
        ));

        wp_send_json_success(array(
            'message' => __('If an account with that email exists, a password reset link has been sent.', 'orabooks'),
        ));
    }

    // ================================================================
    // RESET PASSWORD — AJAX
    // ================================================================

    /**
     * AJAX handler: Reset password with a new password.
     *
     * POST parameters:
     *   - key:        Reset key from the email link
     *   - login:      User login (from the email link)
     *   - password:   New password
     *   - nonce:      WordPress nonce (orabooks_reset_nonce)
     */
    public function ajax_reset_password() {
        check_ajax_referer('orabooks_reset_nonce', 'nonce');

        $key      = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
        $login    = isset($_POST['login']) ? sanitize_user($_POST['login']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($key) || empty($login)) {
            wp_send_json_error(array(
                'message' => __('Invalid reset link.', 'orabooks'),
            ));
        }

        if (empty($password)) {
            wp_send_json_error(array(
                'message' => __('Please enter a new password.', 'orabooks'),
            ));
        }

        // Validate password policy
        $policy_errors = $this->validate_password_policy($password);

        if (!empty($policy_errors)) {
            wp_send_json_error(array(
                'message' => implode('<br>', $policy_errors),
            ));
        }

        // Check the reset key
        $user = check_password_reset_key($key, $login);

        if (is_wp_error($user)) {
            $code = $user->get_error_code();

            if ($code === 'expired_key') {
                wp_send_json_error(array(
                    'message' => __('Your reset link has expired. Please request a new one.', 'orabooks'),
                ));
            }

            wp_send_json_error(array(
                'message' => __('Invalid reset link. Please request a new one.', 'orabooks'),
            ));
        }

        // Reset the password
        reset_password($user, $password);

        // Audit event
        do_action('orabooks_security_event', 'password_reset_completed', array(
            'user_id'    => $user->ID,
            'email'      => $user->user_email,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
        ));

        // JWT revocation is handled by the password_reset hook in class-orabooks-jwt.php

        wp_send_json_success(array(
            'message'  => __('Your password has been reset successfully. You can now log in with your new password.', 'orabooks'),
            'redirect' => wp_login_url(),
        ));
    }

    // ================================================================
    // RESET PASSWORD — SHORTCODE + POST HANDLER
    // ================================================================

    /**
     * Handle the reset password form submission via traditional POST.
     * Validates key, login, and new password, then resets.
     */
    public function handle_reset_form_submit() {
        if (!isset($_POST['orabooks_do_reset']) || $_POST['orabooks_do_reset'] !== '1') {
            return;
        }

        if (!isset($_POST['orabooks_reset_key']) || !isset($_POST['orabooks_reset_login'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['orabooks_reset_nonce'] ?? '', 'orabooks_reset_action')) {
            return;
        }

        $key      = sanitize_text_field($_POST['orabooks_reset_key']);
        $login    = sanitize_user($_POST['orabooks_reset_login']);
        $password = $_POST['orabooks_new_password'] ?? '';

        // Validate password policy
        $policy_errors = $this->validate_password_policy($password);
        if (!empty($policy_errors)) {
            // Store errors in URL params
            $error_url = add_query_arg(array(
                'action'        => 'orabooks_reset_pwd',
                'key'           => $key,
                'login'         => rawurlencode($login),
                'reset_errors'  => urlencode(implode('|', $policy_errors)),
            ), home_url('/'));
            wp_safe_redirect($error_url);
            exit;
        }

        $user = check_password_reset_key($key, $login);

        if (is_wp_error($user)) {
            $error_url = add_query_arg(array(
                'action'       => 'orabooks_reset_pwd',
                'reset_errors' => urlencode($user->get_error_message()),
            ), home_url('/'));
            wp_safe_redirect($error_url);
            exit;
        }

        reset_password($user, $password);

        do_action('orabooks_security_event', 'password_reset_completed', array(
            'user_id'    => $user->ID,
            'email'      => $user->user_email,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
        ));

        // Redirect to login with success message
        $success_url = add_query_arg('password_reset', 'success', wp_login_url());
        wp_safe_redirect($success_url);
        exit;
    }

    /**
     * Shortcode: [orabooks_reset_password_form]
     * Displays the reset password form when valid key + login are present in the URL.
     */
    public function render_reset_form() {
        // Check for key and login in URL
        $key   = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
        $login = isset($_GET['login']) ? sanitize_user($_GET['login']) : '';

        // Also check action=orabooks_reset_pwd which is our custom reset trigger
        $is_reset_page = isset($_GET['action']) && $_GET['action'] === 'orabooks_reset_pwd';

        if ($is_reset_page && !empty($key) && !empty($login)) {
            // Validate key silently — don't reveal validity
            $user = check_password_reset_key($key, $login);
            $key_valid = !is_wp_error($user);

            // Get any errors from previous submission attempt
            $reset_errors = array();
            if (isset($_GET['reset_errors'])) {
                $error_string = sanitize_text_field($_GET['reset_errors']);
                $reset_errors = explode('|', $error_string);
            }

            ob_start();
            ?>
            <div class="orabooks-reset-password-wrap" style="max-width: 480px; margin: 40px auto; padding: 30px; background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <h2 style="text-align: center; margin-bottom: 10px; color: #333;"><?php esc_html_e('Reset Your Password', 'orabooks'); ?></h2>

                <?php if (!empty($reset_errors)): ?>
                    <div style="padding: 12px; border-radius: 6px; margin-bottom: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;">
                        <strong><?php esc_html_e('Please fix the following:', 'orabooks'); ?></strong>
                        <ul style="margin: 8px 0 0 16px; padding: 0;">
                            <?php foreach ($reset_errors as $error): ?>
                                <li><?php echo esc_html($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($key_valid): ?>
                    <p style="text-align: center; color: #666; margin-bottom: 25px;">
                        <?php esc_html_e('Enter your new password below.', 'orabooks'); ?>
                    </p>
                    <p style="text-align: center; color: #888; font-size: 13px; margin-bottom: 20px;">
                        <?php esc_html_e('Minimum 8 characters, with uppercase, lowercase, number, and special character.', 'orabooks'); ?>
                    </p>

                    <form method="post" action="<?php echo esc_url(home_url('/')); ?>" style="display: flex; flex-direction: column; gap: 15px;">
                        <input type="hidden" name="orabooks_do_reset" value="1">
                        <input type="hidden" name="orabooks_reset_key" value="<?php echo esc_attr($key); ?>">
                        <input type="hidden" name="orabooks_reset_login" value="<?php echo esc_attr($login); ?>">
                        <?php wp_nonce_field('orabooks_reset_action', 'orabooks_reset_nonce'); ?>

                        <div>
                            <label for="orabooks_new_password" style="display: block; margin-bottom: 5px; font-weight: 600; color: #3c434a;">
                                <?php esc_html_e('New Password', 'orabooks'); ?>
                            </label>
                            <input type="password" id="orabooks_new_password" name="orabooks_new_password"
                                   required minlength="8"
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; box-sizing: border-box;"
                                   placeholder="<?php esc_attr_e('Enter new password', 'orabooks'); ?>">
                        </div>

                        <div>
                            <label for="orabooks_confirm_password" style="display: block; margin-bottom: 5px; font-weight: 600; color: #3c434a;">
                                <?php esc_html_e('Confirm Password', 'orabooks'); ?>
                            </label>
                            <input type="password" id="orabooks_confirm_password" name="orabooks_confirm_password"
                                   required minlength="8"
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; box-sizing: border-box;"
                                   placeholder="<?php esc_attr_e('Confirm new password', 'orabooks'); ?>">
                        </div>

                        <button type="submit"
                                style="padding: 12px; background: #43a62d; color: white; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; font-weight: 600;">
                            <?php esc_html_e('Reset Password', 'orabooks'); ?>
                        </button>
                    </form>

                    <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('form').on('submit', function(e) {
                            var password = $('#orabooks_new_password').val();
                            var confirm  = $('#orabooks_confirm_password').val();

                            if (password !== confirm) {
                                e.preventDefault();
                                alert('<?php echo esc_js(__('Passwords do not match.', 'orabooks')); ?>');
                                return;
                            }
                        });
                    });
                    </script>
                <?php else: ?>
                    <div style="padding: 20px; text-align: center; color: #721c24; background: #f8d7da; border-radius: 6px;">
                        <p style="font-weight: 600;"><?php esc_html_e('Invalid or expired reset link.', 'orabooks'); ?></p>
                        <p style="margin-top: 10px;">
                            <a href="<?php echo esc_url(wp_login_url()); ?>" style="color: #43a62d;">
                                <?php esc_html_e('Back to Login', 'orabooks'); ?>
                            </a>
                        </p>
                    </div>
                <?php endif; ?>

                <p style="text-align: center; margin-top: 20px;">
                    <a href="<?php echo esc_url(wp_login_url()); ?>" style="color: #43a62d;">
                        <?php esc_html_e('Back to Login', 'orabooks'); ?>
                    </a>
                </p>
            </div>
            <?php
            return ob_get_clean();
        }

        // Not a reset page — show empty state (the shortcode will redirect via the legacy system)
        return '<p>' . __('Use the "Lost your password?" link on the login page to request a reset link.', 'orabooks') . '</p>';
    }

    // ================================================================
    // PASSWORD POLICY
    // ================================================================

    /**
     * Validate password against SL-013 policy.
     * Delegates to OraBooks_Registration::validate_password_policy() to
     * keep the policy definition in one place.
     *
     * Requirements: minimum 8 characters, at least one uppercase, one lowercase,
     * one digit, and one special character.
     *
     * @param string $password The password to validate.
     * @return array List of error messages (empty if valid).
     */
    public static function validate_password_policy($password) {
        if (class_exists('OraBooks_Registration')) {
            return OraBooks_Registration::validate_password_policy($password);
        }

        // Fallback (should not happen in production)
        $errors = array();
        if (strlen($password) < 8) {
            $errors[] = __('Password must be at least 8 characters long.', 'orabooks');
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = __('Password must contain at least one uppercase letter.', 'orabooks');
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = __('Password must contain at least one lowercase letter.', 'orabooks');
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = __('Password must contain at least one number.', 'orabooks');
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = __('Password must contain at least one special character (e.g. !@#$%).', 'orabooks');
        }
        return $errors;
    }

    // ================================================================
    // ASSETS
    // ================================================================

    /**
     * Enqueue jQuery on pages with the reset password shortcode.
     */
    public function enqueue_assets() {
        global $post;

        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'orabooks_reset_password_form')) {
            wp_enqueue_script('jquery');
        }
    }

    // ================================================================
    // FORGOT PASSWORD FORM SHORTCODE (wrapper for AJAX)
    // ================================================================

    /**
     * Render the forgot password form (uses AJAX to submit).
     * Can be called independently or via do_shortcode.
     */
    public static function render_forgot_form() {
        ob_start();
        ?>
        <div class="orabooks-forgot-wrap" style="max-width: 480px; margin: 40px auto; padding: 30px; background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h2 style="text-align: center; margin-bottom: 10px; color: #333;"><?php esc_html_e('Forgot Password', 'orabooks'); ?></h2>
            <p style="text-align: center; color: #666; margin-bottom: 25px;"><?php esc_html_e('Enter your email to receive a reset link.', 'orabooks'); ?></p>

            <div id="orabooks-forgot-message"></div>

            <form id="orabooks-forgot-form" style="display: flex; flex-direction: column; gap: 15px;">
                <?php wp_nonce_field('orabooks_forgot_nonce', 'nonce'); ?>
                <input type="hidden" name="action" value="orabooks_forgot_password">

                <div>
                    <label for="orabooks-forgot-email" style="display: block; margin-bottom: 5px; font-weight: 600; color: #3c434a;">
                        <?php esc_html_e('Email Address', 'orabooks'); ?>
                    </label>
                    <input type="email" id="orabooks-forgot-email" name="email" required
                           style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; box-sizing: border-box;"
                           placeholder="<?php esc_attr_e('Enter your email', 'orabooks'); ?>">
                </div>

                <button type="submit" id="orabooks-forgot-submit"
                        style="padding: 12px; background: #43a62d; color: white; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; font-weight: 600;">
                    <?php esc_html_e('Send Reset Link', 'orabooks'); ?>
                </button>
            </form>

            <p style="text-align: center; margin-top: 20px;">
                <a href="<?php echo esc_url(wp_login_url()); ?>" style="color: #43a62d;"><?php esc_html_e('Back to Login', 'orabooks'); ?></a>
            </p>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#orabooks-forgot-form').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                var $msg  = $('#orabooks-forgot-message');
                var $btn  = $('#orabooks-forgot-submit');

                $btn.prop('disabled', true).text('<?php echo esc_js(__('Sending...', 'orabooks')); ?>');
                $msg.html('');

                $.post('<?php echo admin_url('admin-ajax.php'); ?>', $form.serialize(), function(response) {
                    if (response.success) {
                        $msg.html('<p style="color: #46b450; font-weight: 600; text-align: center;">' + response.data.message + '</p>');
                    } else {
                        $msg.html('<p style="color: #dc3232; text-align: center;">' + response.data.message + '</p>');
                    }
                    $btn.prop('disabled', false).text('<?php echo esc_js(__('Send Reset Link', 'orabooks')); ?>');
                }).fail(function() {
                    $msg.html('<p style="color: #dc3232; text-align: center;"><?php echo esc_js(__('Connection error. Please try again.', 'orabooks')); ?></p>');
                    $btn.prop('disabled', false).text('<?php echo esc_js(__('Send Reset Link', 'orabooks')); ?>');
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}

// Initialize
OraBooks_Password_Reset::get_instance();
