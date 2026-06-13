<?php

if (!class_exists('Login_Widget_Admin_Security')) {
    class Login_Widget_Admin_Security {

        public function __construct() {
            $captcha_on_admin_login = (get_option('captcha_on_admin_login') == 'Yes' ? true : false);
            if ($captcha_on_admin_login and in_array($GLOBALS['pagenow'], array('wp-login.php'))) {
                add_action('login_form', array($this, 'security_add'));
            }

            $login_ap_forgot_pass_link = get_option('login_ap_forgot_pass_link');
            if ($login_ap_forgot_pass_link and !in_array($GLOBALS['pagenow'], array('wp-login.php'))) {
                add_filter('lostpassword_url', array($this, 'ap_lost_password_url_filter'), 10, 2);
            }

            add_action('ap_login_log_front', array($this, 'ap_login_log_front_action'), 1, 1);
            add_filter('authenticate', array($this, 'myplugin_auth_signon'), 30, 3);

            $captcha_on_user_login = (get_option('captcha_on_user_login') == 'Yes' ? true : false);
            if ($captcha_on_user_login) {
                add_action('login_ap_form', array($this, 'security_add_user'));
            }

            if (in_array($GLOBALS['pagenow'], array('wp-login.php'))) {
                add_action('wp_login', array($this, 'check_ap_login_success'));
                add_filter('login_errors', array($this, 'check_ap_login_failed'));
            }
        }

        public function ap_lost_password_url_filter($lostpassword_url, $redirect) {
            $login_ap_forgot_pass_link = get_option('login_ap_forgot_pass_link');
            return esc_url(get_permalink($login_ap_forgot_pass_link));
        }

        public function check_ap_login_success() {
            $lla = new Login_Log_Adds;
            $lla->log_add(apply_filters('lwws_log_ip', $_SERVER['REMOTE_ADDR']), 'Login success', date("Y-m-d H:i:s"), 'success');
        }

        public function check_ap_login_failed($error) {
            global $errors;
            $lla = new Login_Log_Adds;

            if (is_wp_error($errors)) {
                $err_codes = $errors->get_error_codes();
            } else {
                return $error;
            }

            if (in_array('invalid_username', $err_codes) or in_array('invalid_email', $err_codes) or in_array('incorrect_password', $err_codes)) {
                $lla->log_add(apply_filters('lwws_log_ip', $_SERVER['REMOTE_ADDR']), 'Error in login', date("Y-m-d H:i:s"), 'failed');
            }

            // compatibility added for google authenticator plugin
            if (in_array('invalid_google_authenticator_token', $err_codes)) {
                $lla->log_add(apply_filters('lwws_log_ip', $_SERVER['REMOTE_ADDR']), 'Invalid google authenticator code', date("Y-m-d H:i:s"), 'failed');
            }

            return $error;
        }

        public function ap_login_log_front_action($error) {
            $lla = new Login_Log_Adds;
            $err_codes = $error->get_error_codes();
            if (in_array('invalid_username', $err_codes) or in_array('invalid_email', $err_codes) or in_array('incorrect_password', $err_codes)) {
                $lla->log_add(apply_filters('lwws_log_ip', $_SERVER['REMOTE_ADDR']), 'Error in login', date("Y-m-d H:i:s"), 'failed');
            }

            // compatibility added for google authenticator plugin
            if (in_array('invalid_google_authenticator_token', $err_codes)) {
                $lla->log_add(apply_filters('lwws_log_ip', $_SERVER['REMOTE_ADDR']), 'Invalid google authenticator code', date("Y-m-d H:i:s"), 'failed');
            }

        }

        /**
         * SL-008 Compliance: Decrypt reCAPTCHA private key on retrieval.
         * Secrets must never be stored in plaintext per SL-008 doctrine.
         */
        public static function get_recaptcha_public_key() {
            return get_option('lsw_google_recaptcha_public_key');
        }

        /**
         * SL-008 Compliance: Decrypt reCAPTCHA private key on retrieval.
         * Secrets must be encrypted at rest per SL-008 doctrine.
         */
        public static function get_recaptcha_private_key() {
            $encrypted = get_option('lsw_google_recaptcha_private_key_encrypted');
            if (!empty($encrypted)) {
                return self::decrypt_secret($encrypted);
            }
            // Fallback for legacy plaintext storage (will be migrated on save)
            return get_option('lsw_google_recaptcha_private_key');
        }

        /**
         * SL-008 Compliance: Save reCAPTCHA private key with encryption at rest.
         */
        public static function save_recaptcha_private_key($plaintext_key) {
            if (empty($plaintext_key)) {
                delete_option('lsw_google_recaptcha_private_key');
                delete_option('lsw_google_recaptcha_private_key_encrypted');
                return;
            }
            $encrypted = self::encrypt_secret($plaintext_key);
            update_option('lsw_google_recaptcha_private_key_encrypted', $encrypted);
            // Remove legacy plaintext
            delete_option('lsw_google_recaptcha_private_key');
        }

        /**
         * SL-008 Compliance: Encrypt a secret using WP salts as key material.
         * Uses AUTH_KEY + AUTH_SALT as encryption key with AES-256-CTR.
         */
        private static function encrypt_secret($plaintext) {
            if (!defined('AUTH_KEY') || !defined('AUTH_SALT')) {
                // Fallback: if salts not defined, store as-is (should not happen in production)
                return $plaintext;
            }
            $key = hash('sha256', AUTH_KEY . AUTH_SALT, true);
            $iv = openssl_random_pseudo_bytes(16);
            $ciphertext = openssl_encrypt($plaintext, 'aes-256-ctr', $key, OPENSSL_RAW_DATA, $iv);
            if ($ciphertext === false) {
                return $plaintext;
            }
            return base64_encode($iv . $ciphertext);
        }

        /**
         * SL-008 Compliance: Decrypt a secret that was encrypted with encrypt_secret().
         */
        private static function decrypt_secret($encoded) {
            if (!defined('AUTH_KEY') || !defined('AUTH_SALT')) {
                return $encoded;
            }
            $key = hash('sha256', AUTH_KEY . AUTH_SALT, true);
            $data = base64_decode($encoded);
            if ($data === false || strlen($data) < 16) {
                return $encoded;
            }
            $iv = substr($data, 0, 16);
            $ciphertext = substr($data, 16);
            $plaintext = openssl_decrypt($ciphertext, 'aes-256-ctr', $key, OPENSSL_RAW_DATA, $iv);
            if ($plaintext === false) {
                return $encoded;
            }
            return $plaintext;
        }

        /**
         * SL-008 Compliance: Migrate legacy plaintext reCAPTCHA secret to encrypted storage.
         */
        public static function migrate_recaptcha_secret() {
            $legacy = get_option('lsw_google_recaptcha_private_key');
            if (!empty($legacy) && !get_option('lsw_google_recaptcha_private_key_encrypted')) {
                self::save_recaptcha_private_key($legacy);
                do_action('lsws_secret_migrated', 'recaptcha_private_key');
            }
        }

        public function google_recaptcha_put_v2() {
            require_once LSW_DIR_PATH . '/recaptcha/recaptchalib_i_am_not_robot.php';
            $publickey = self::get_recaptcha_public_key();
            $privatekey = self::get_recaptcha_private_key();

            if ($publickey == '' or $privatekey == '') {
                _e('Google Recaptcha not configured.', 'contact-form-with-shortcode');
                return;
            }
            ?>
			<div class="g-recaptcha" data-sitekey="<?php echo esc_attr($publickey); ?>"></div>
			<script src='https://www.google.com/recaptcha/api.js' async defer></script>
			<?php
        }

        public function security_add() {

            if (get_option('captcha_type_in_lsw') == 'recaptcha') {
                include LSW_DIR_PATH . '/view/admin/recaptcha.php';
            } else {
                include LSW_DIR_PATH . '/view/admin/captcha.php';
            }

        }

        public function myplugin_auth_signon($user, $username, $password) {
            start_session_if_not_started();
            $lla = new Login_Log_Adds;

            // ── SL-013: Login Rate Limit (5 failures/15 min per IP+email) ──
            if (!empty($username) && class_exists('OraBooks_Rate_Limiter')) {
                $ip = OraBooks_Rate_Limiter::get_client_ip();
                $rate_key = $ip . '|' . strtolower(trim($username));
                $limiter = OraBooks_Rate_Limiter::get_instance();

                // Check before processing (increment only on failure below)
                $status = $limiter->check_rate_limit('login', $rate_key, 5, 900); // 5 attempts / 15 min

                if (!$status['allowed']) {
                    $lla->log_add(
                        apply_filters('lwws_log_ip', $_SERVER['REMOTE_ADDR']),
                        'Login rate limited - ' . $username,
                        date("Y-m-d H:i:s"),
                        'failed'
                    );
                    do_action('orabooks_security_event', 'login_rate_limited', array(
                        'username' => $username,
                        'ip_address' => $ip,
                    ));
                    return new WP_Error(
                        'rate_limit_exceeded',
                        __('Too many failed login attempts. Please try again in 15 minutes.', 'login-sidebar-widget')
                    );
                }
            }

            $captcha_on_admin_login = (get_option('captcha_on_admin_login') == 'Yes' ? true : false);
            if ($captcha_on_admin_login and in_array($GLOBALS['pagenow'], array('wp-login.php'))) {

                if (get_option('captcha_type_in_lsw') == 'default') {

                    if (isset($_POST['admin_captcha']) and sanitize_text_field($_POST['admin_captcha']) != $_SESSION['lsw_captcha_code']) {
                        $lla->log_add(apply_filters('lwws_log_ip', $_SERVER['REMOTE_ADDR']), 'Security code do not match', date("Y-m-d H:i:s"), 'failed');
                        return new WP_Error('error_security_code', __("Security code do not match.", "login-sidebar-widget"));
                    }

                } else {
                    require_once LSW_DIR_PATH . '/recaptcha/recaptchalib_i_am_not_robot.php';
                    $publickey = self::get_recaptcha_public_key();
                    $privatekey = self::get_recaptcha_private_key();

                    $reCaptcha = new ReCaptcha($privatekey);

                    if ($publickey == '' or $privatekey == '') {
                        wp_die('Google Recaptcha not configured!');
                    }
                    $resp = $reCaptcha->verifyResponse(@$_SERVER["REMOTE_ADDR"], @$_POST["g-recaptcha-response"]);
                    if ($resp == null || !empty($resp->errorCodes)) {
                        $lla->log_add(apply_filters('lwws_log_ip', $_SERVER['REMOTE_ADDR']), 'Recaptcha error', date("Y-m-d H:i:s"), 'failed');
                        return new WP_Error('error_security_code', __("Recaptcha error!", "login-sidebar-widget"));
                    }
                }
            }

            $captcha_on_user_login = (get_option('captcha_on_user_login') == 'Yes' ? true : false);
            if ($captcha_on_user_login and !in_array($GLOBALS['pagenow'], array('wp-login.php'))) {

                if (get_option('captcha_type_in_lsw') == 'default') {

                    if ($captcha_on_user_login and (isset($_POST['user_captcha']) and sanitize_text_field($_POST['user_captcha']) != $_SESSION['lsw_captcha_code'])) {
                        $lla->log_add(apply_filters('lwws_log_ip', $_SERVER['REMOTE_ADDR']), 'Security code do not match', date("Y-m-d H:i:s"), 'failed');
                        return new WP_Error('error_security_code', __("Security code do not match.", "login-sidebar-widget"));
                    }

                } else {
                    require_once LSW_DIR_PATH . '/recaptcha/recaptchalib_i_am_not_robot.php';
                    $publickey = self::get_recaptcha_public_key();
                    $privatekey = self::get_recaptcha_private_key();

                    $reCaptcha = new ReCaptcha($privatekey);

                    if ($publickey == '' or $privatekey == '') {
                        wp_die('Google Recaptcha not configured!');
                    }
                    $resp = $reCaptcha->verifyResponse($_SERVER["REMOTE_ADDR"], $_POST["g-recaptcha-response"]);
                    if ($resp == null || !empty($resp->errorCodes)) {
                        $lla->log_add(apply_filters('lwws_log_ip', $_SERVER['REMOTE_ADDR']), 'Recaptcha error', date("Y-m-d H:i:s"), 'failed');
                        return new WP_Error('error_security_code', __("Recaptcha error!", "login-sidebar-widget"));
                    }
                }
            }

            // All In One WP Security //
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
            if (is_plugin_active('all-in-one-wp-security-and-firewall/wp-security.php')) {
                global $aio_wp_security;
                if ($aio_wp_security->configs->get_value('aiowps_enable_login_captcha') == '1') {
                    $captcha_error = new WP_Error('authentication_failed', __('<strong>ERROR</strong>: Your answer was incorrect - please try again.', 'all-in-one-wp-security-and-firewall'));
                    $captcha_answer = filter_input(INPUT_POST, 'aiowps-captcha-answer', FILTER_VALIDATE_INT);

                    $captcha_temp_string = filter_input(INPUT_POST, 'aiowps-captcha-temp-string', FILTER_SANITIZE_STRING);
                    if (is_null($captcha_temp_string)) {
                        $lla->log_add(apply_filters('lwws_log_ip', $_SERVER['REMOTE_ADDR']), 'Security answer is incorrect', date("Y-m-d H:i:s"), 'failed');
                        return $captcha_error;
                    }
                    $captcha_secret_string = $aio_wp_security->configs->get_value('aiowps_captcha_secret_key');
                    $submitted_encoded_string = base64_encode($captcha_temp_string . $captcha_secret_string . $captcha_answer);
                    $trans_handle = sanitize_text_field(filter_input(INPUT_POST, 'aiowps-captcha-string-info', FILTER_SANITIZE_STRING));
                    $captcha_string_info_trans = (AIOWPSecurity_Utility::is_multisite_install() ? get_site_transient('aiowps_captcha_string_info_' . $trans_handle) : get_transient('aiowps_captcha_string_info_' . $trans_handle));
                    if ($submitted_encoded_string !== $captcha_string_info_trans) {
                        $lla->log_add(apply_filters('lwws_log_ip', $_SERVER['REMOTE_ADDR']), 'Security answer is incorrect', date("Y-m-d H:i:s"), 'failed');
                        return $captcha_error;
                    }
                }
            }
            // All In One WP Security //

            // ── SL-013: On failure, increment rate limit counter ──────────
            if (is_wp_error($user) && !empty($username) && class_exists('OraBooks_Rate_Limiter')) {
                $ip = OraBooks_Rate_Limiter::get_client_ip();
                $rate_key = $ip . '|' . strtolower(trim($username));
                OraBooks_Rate_Limiter::get_instance()->increment('login', $rate_key, 5, 900);
            }

            // ── SL-013: On success, reset rate limit counter ──────────────
            if (!is_wp_error($user) && !empty($username) && class_exists('OraBooks_Rate_Limiter')) {
                $ip = OraBooks_Rate_Limiter::get_client_ip();
                $rate_key = $ip . '|' . strtolower(trim($username));
                OraBooks_Rate_Limiter::get_instance()->reset('login', $rate_key);
            }

            return $user;
        }

        public function security_add_user() {

            if (get_option('captcha_type_in_lsw') == 'recaptcha') {
                include LSW_DIR_PATH . '/view/frontend/recaptcha.php';
            } else {
                include LSW_DIR_PATH . '/view/frontend/captcha.php';
            }

        }
    }
}

if (!function_exists('security_init')) {
    function security_init() {
        new Login_Widget_Admin_Security;
        // SL-008 Compliance: Migrate any legacy plaintext secrets to encrypted storage
        Login_Widget_Admin_Security::migrate_recaptcha_secret();
    }
}

/**
 * SL-013 – 2FA (TOTP) Helper Class
 *
 * Implements TOTP (RFC 6238) two-factor authentication with:
 *   - ±30 second clock drift tolerance
 *   - 8 single-use backup codes (bcrypt hashed)
 *   - TOTP secret encrypted at rest (AES-256-CTR, same key material as SL-008)
 *   - All secrets stored in WordPress user meta (no schema migration required)
 *   - Audit logging via do_action('orabooks_security_event', ...)
 *
 * User meta keys:
 *   lsw_totp_secret_enc   – AES-256-CTR encrypted Base32 TOTP secret
 *   lsw_totp_backup_codes – JSON array of bcrypt-hashed single-use backup codes
 *   lsw_2fa_enabled       – '1' when 2FA is active, '' when not
 */
if (!class_exists('OraBooks_2FA')) {
    class OraBooks_2FA {

        // ──────────────────────────────────────────────────────────────────
        // Base32 alphabet (RFC 4648)
        // ──────────────────────────────────────────────────────────────────
        private static $BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

        // ──────────────────────────────────────────────────────────────────
        // Secret generation & storage
        // ──────────────────────────────────────────────────────────────────

        /**
         * Generate a cryptographically secure 160-bit Base32 TOTP secret.
         *
         * @return string 32-character uppercase Base32 string.
         */
        public static function generate_totp_secret() {
            $bytes  = random_bytes(20); // 160 bits
            $secret = '';
            $chars  = self::$BASE32_CHARS;
            $len    = strlen($bytes);

            for ($i = 0; $i < $len; $i += 5) {
                $chunk = substr($bytes . "\0\0\0\0", $i, 5);
                $b     = array_values(unpack('C5', $chunk));
                $secret .= $chars[($b[0] & 0xF8) >> 3];
                $secret .= $chars[(($b[0] & 0x07) << 2) | (($b[1] & 0xC0) >> 6)];
                $secret .= $chars[($b[1] & 0x3E) >> 1];
                $secret .= $chars[(($b[1] & 0x01) << 4) | (($b[2] & 0xF0) >> 4)];
                $secret .= $chars[(($b[2] & 0x0F) << 1) | (($b[3] & 0x80) >> 7)];
                $secret .= $chars[($b[3] & 0x7C) >> 2];
                $secret .= $chars[(($b[3] & 0x03) << 3) | (($b[4] & 0xE0) >> 5)];
                $secret .= $chars[$b[4] & 0x1F];
            }
            return substr($secret, 0, 32); // 160 bits → 32 Base32 chars
        }

        /**
         * Build an otpauth:// URI for QR code generation.
         * The QR code can be rendered using any client-side library (e.g. qrcode.js).
         *
         * @param  string $user_email  The user's email address (account label).
         * @param  string $secret      Base32 TOTP secret.
         * @param  string $issuer      Issuer name shown in authenticator app.
         * @return string              otpauth URI.
         */
        public static function generate_qr_uri($user_email, $secret, $issuer = 'OraBooks') {
            $label = rawurlencode($issuer . ':' . $user_email);
            return sprintf(
                'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
                $label,
                rawurlencode($secret),
                rawurlencode($issuer)
            );
        }

        /**
         * Encrypt and store the TOTP secret in user meta.
         *
         * @param  int    $user_id WP user ID.
         * @param  string $secret  Plaintext Base32 secret.
         */
        public static function save_totp_secret($user_id, $secret) {
            $encrypted = self::encrypt_secret($secret);
            update_user_meta($user_id, 'lsw_totp_secret_enc', $encrypted);
        }

        /**
         * Retrieve and decrypt the TOTP secret from user meta.
         *
         * @param  int         $user_id WP user ID.
         * @return string|null          Plaintext Base32 secret, or null if not set.
         */
        public static function get_totp_secret($user_id) {
            $encrypted = get_user_meta($user_id, 'lsw_totp_secret_enc', true);
            if (empty($encrypted)) {
                return null;
            }
            return self::decrypt_secret($encrypted);
        }

        // ──────────────────────────────────────────────────────────────────
        // TOTP verification (RFC 6238, ±30 second drift window)
        // ──────────────────────────────────────────────────────────────────

        /**
         * Decode a Base32 string to raw bytes.
         *
         * @param  string $input Base32-encoded string (uppercase).
         * @return string        Raw binary string.
         */
        private static function base32_decode($input) {
            $input  = strtoupper($input);
            $chars  = self::$BASE32_CHARS;
            $output = '';
            $buffer = 0;
            $bits   = 0;

            for ($i = 0; $i < strlen($input); $i++) {
                $pos = strpos($chars, $input[$i]);
                if ($pos === false) continue;
                $buffer = ($buffer << 5) | $pos;
                $bits += 5;
                if ($bits >= 8) {
                    $bits  -= 8;
                    $output .= chr(($buffer >> $bits) & 0xFF);
                }
            }
            return $output;
        }

        /**
         * Verify a 6-digit TOTP code against the stored secret.
         * Allows ±1 time step (±30 seconds) per SL-013.
         *
         * @param  string $secret   Base32 TOTP secret (plaintext).
         * @param  string $otp      The 6-digit code entered by the user.
         * @return bool             True if valid.
         */
        public static function verify_totp($secret, $otp) {
            $otp        = str_pad(trim($otp), 6, '0', STR_PAD_LEFT);
            $secret_bin = self::base32_decode($secret);
            $timestamp  = (int) floor(time() / 30);

            for ($offset = -1; $offset <= 1; $offset++) {
                $T    = pack('N*', 0) . pack('N*', $timestamp + $offset);
                $hash = hash_hmac('sha1', $T, $secret_bin, true);
                $ob   = ord($hash[19]) & 0x0f;
                $code = (unpack('N', substr($hash, $ob, 4))[1] & 0x7fffffff) % 1000000;
                if (str_pad($code, 6, '0', STR_PAD_LEFT) === $otp) {
                    return true;
                }
            }
            return false;
        }

        // ──────────────────────────────────────────────────────────────────
        // Backup codes
        // ──────────────────────────────────────────────────────────────────

        /**
         * Generate 8 cryptographically random, single-use backup codes (8 alphanumeric chars each).
         *
         * @return string[] Array of 8 plaintext backup codes.
         */
        public static function generate_backup_codes() {
            $codes    = [];
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // unambiguous chars
            for ($i = 0; $i < 8; $i++) {
                $code = '';
                for ($j = 0; $j < 8; $j++) {
                    $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
                $codes[] = $code;
            }
            return $codes;
        }

        /**
         * Hash and save backup codes to user meta (bcrypt via wp_hash_password).
         *
         * @param  int      $user_id WP user ID.
         * @param  string[] $codes   Plaintext backup codes.
         */
        public static function save_backup_codes($user_id, $codes) {
            $hashed = array_map('wp_hash_password', $codes);
            update_user_meta($user_id, 'lsw_totp_backup_codes', wp_json_encode($hashed));
        }

        /**
         * Verify a backup code against stored hashes.
         * If valid, the code is immediately invalidated (single-use per SL-013).
         *
         * @param  int    $user_id WP user ID.
         * @param  string $code    Plaintext backup code entered by the user.
         * @return bool            True if valid and successfully consumed.
         */
        public static function verify_backup_code($user_id, $code) {
            $raw = get_user_meta($user_id, 'lsw_totp_backup_codes', true);
            if (empty($raw)) {
                return false;
            }
            $hashes = json_decode($raw, true);
            if (!is_array($hashes)) {
                return false;
            }

            $code = strtoupper(trim($code));
            foreach ($hashes as $idx => $hash) {
                if (wp_check_password($code, $hash)) {
                    // Invalidate the used code (single-use)
                    unset($hashes[$idx]);
                    update_user_meta($user_id, 'lsw_totp_backup_codes', wp_json_encode(array_values($hashes)));
                    return true;
                }
            }
            return false;
        }

        // ──────────────────────────────────────────────────────────────────
        // 2FA enabled flag
        // ──────────────────────────────────────────────────────────────────

        /**
         * Check whether 2FA is enabled for a given user.
         *
         * @param  int  $user_id WP user ID.
         * @return bool
         */
        public static function is_enabled($user_id) {
            return (bool) get_user_meta($user_id, 'lsw_2fa_enabled', true);
        }

        /**
         * Enable 2FA for a user (sets the flag in user meta).
         *
         * @param int $user_id WP user ID.
         */
        public static function enable($user_id) {
            update_user_meta($user_id, 'lsw_2fa_enabled', '1');
        }

        /**
         * Disable 2FA for a user.
         *
         * @param int $user_id WP user ID.
         */
        public static function disable($user_id) {
            update_user_meta($user_id, 'lsw_2fa_enabled', '');
            delete_user_meta($user_id, 'lsw_totp_secret_enc');
            delete_user_meta($user_id, 'lsw_totp_backup_codes');
        }

        // ──────────────────────────────────────────────────────────────────
        // Pending 2FA challenge transient helpers
        // ──────────────────────────────────────────────────────────────────

        /**
         * Store a pending 2FA challenge (called after password auth succeeds).
         * Returns a unique challenge token that must be included in the challenge form.
         *
         * @param  int    $user_id  WP user ID.
         * @param  bool   $remember Whether to set a persistent auth cookie.
         * @return string           Nonce-like challenge token.
         */
        public static function create_challenge($user_id, $remember = false) {
            $token = bin2hex(random_bytes(32));
            set_transient(
                'lsw_2fa_challenge_' . $token,
                ['user_id' => (int) $user_id, 'remember' => (bool) $remember],
                5 * MINUTE_IN_SECONDS  // 5-minute expiry per SL-013 spec
            );
            return $token;
        }

        /**
         * Retrieve and validate a pending 2FA challenge token.
         *
         * @param  string     $token Challenge token from form.
         * @return array|null        ['user_id' => int, 'remember' => bool] or null if expired/invalid.
         */
        public static function get_challenge($token) {
            if (empty($token) || !ctype_xdigit($token)) {
                return null;
            }
            return get_transient('lsw_2fa_challenge_' . sanitize_text_field($token));
        }

        /**
         * Delete a challenge transient (after successful or failed verification).
         *
         * @param string $token Challenge token.
         */
        public static function clear_challenge($token) {
            delete_transient('lsw_2fa_challenge_' . sanitize_text_field($token));
        }

        // ──────────────────────────────────────────────────────────────────
        // Encryption helpers (same AES-256-CTR pattern as SL-008)
        // ──────────────────────────────────────────────────────────────────

        /**
         * Encrypt a plaintext string using AES-256-CTR with WP salt key material.
         *
         * @param  string $plaintext
         * @return string Base64-encoded ciphertext (IV prepended).
         */
        private static function encrypt_secret($plaintext) {
            if (!defined('AUTH_KEY') || !defined('AUTH_SALT')) {
                return $plaintext;
            }
            $key        = hash('sha256', AUTH_KEY . AUTH_SALT . '2fa', true); // domain-separated from reCAPTCHA
            $iv         = random_bytes(16);
            $ciphertext = openssl_encrypt($plaintext, 'aes-256-ctr', $key, OPENSSL_RAW_DATA, $iv);
            if ($ciphertext === false) {
                return $plaintext;
            }
            return base64_encode($iv . $ciphertext);
        }

        /**
         * Decrypt a secret encrypted by encrypt_secret().
         *
         * @param  string $encoded Base64-encoded IV + ciphertext.
         * @return string          Decrypted plaintext.
         */
        private static function decrypt_secret($encoded) {
            if (!defined('AUTH_KEY') || !defined('AUTH_SALT')) {
                return $encoded;
            }
            $key  = hash('sha256', AUTH_KEY . AUTH_SALT . '2fa', true);
            $data = base64_decode($encoded);
            if ($data === false || strlen($data) < 16) {
                return $encoded;
            }
            $iv         = substr($data, 0, 16);
            $ciphertext = substr($data, 16);
            $plaintext  = openssl_decrypt($ciphertext, 'aes-256-ctr', $key, OPENSSL_RAW_DATA, $iv);
            return ($plaintext !== false) ? $plaintext : $encoded;
        }

        // ──────────────────────────────────────────────────────────────────
        // Audit logging
        // ──────────────────────────────────────────────────────────────────

        /**
         * Log a 2FA security audit event.
         *
         * @param string $event   Event name (e.g. '2fa_enabled', 'login_success').
         * @param array  $context Additional context data.
         */
        public static function audit($event, $context = []) {
            // Fire centralised OraBooks security event action (SL-008 pattern)
            do_action('orabooks_security_event', $event, $context);

            // Also log to the existing login log table if available
            if (class_exists('Login_Log_Adds')) {
                $lla = new Login_Log_Adds;
                $msg = 'SL-013 2FA: ' . $event;
                if (!empty($context['user_id'])) {
                    $msg .= ' [user_id:' . (int) $context['user_id'] . ']';
                }
                $ip = apply_filters('lwws_log_ip', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
                $lla->log_add($ip, $msg, date('Y-m-d H:i:s'), 'success');
            }
        }
    }
}