<?php
/**
 * OraBooks Authentication (SL-013)
 * 
 * Handles registration, login, OIDC, 2FA, password reset,
 * session management, partner onboarding, and subdomain detection.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Auth {
    
    private static $instance = null;
    
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            add_action('wp_ajax_nopriv_orabooks_register', [self::$instance, 'ajax_register']);
            add_action('wp_ajax_orabooks_register', [self::$instance, 'ajax_register']);
            add_action('wp_ajax_nopriv_orabooks_login', [self::$instance, 'ajax_login']);
            add_action('wp_ajax_orabooks_login', [self::$instance, 'ajax_login']);
            add_action('wp_ajax_nopriv_orabooks_verify_email', [self::$instance, 'ajax_verify_email']);
            add_action('wp_ajax_orabooks_verify_email', [self::$instance, 'ajax_verify_email']);
            add_action('wp_ajax_nopriv_orabooks_verify_email_token', [self::$instance, 'ajax_verify_email_token']);
            add_action('wp_ajax_orabooks_verify_email_token', [self::$instance, 'ajax_verify_email_token']);
            add_action('wp_ajax_nopriv_orabooks_resend_verification', [self::$instance, 'ajax_resend_verification']);
            add_action('wp_ajax_orabooks_resend_verification', [self::$instance, 'ajax_resend_verification']);
            add_action('wp_ajax_nopriv_orabooks_forgot_password', [self::$instance, 'ajax_forgot_password']);
            add_action('wp_ajax_orabooks_forgot_password', [self::$instance, 'ajax_forgot_password']);
            add_action('wp_ajax_nopriv_orabooks_reset_password', [self::$instance, 'ajax_reset_password']);
            add_action('wp_ajax_orabooks_reset_password', [self::$instance, 'ajax_reset_password']);
            add_action('wp_ajax_nopriv_orabooks_check_subdomain', [self::$instance, 'ajax_check_subdomain']);
            add_action('wp_ajax_orabooks_check_subdomain', [self::$instance, 'ajax_check_subdomain']);
            add_action('wp_ajax_orabooks_setup_2fa', [self::$instance, 'ajax_setup_2fa']);
            add_action('wp_ajax_orabooks_verify_2fa_setup', [self::$instance, 'ajax_verify_2fa_setup']);
            add_action('wp_ajax_orabooks_2fa_challenge', [self::$instance, 'ajax_2fa_challenge']);
            add_action('wp_ajax_nopriv_orabooks_2fa_challenge', [self::$instance, 'ajax_2fa_challenge']);
            add_action('wp_ajax_orabooks_logout', [self::$instance, 'ajax_logout']);
            add_action('wp_ajax_orabooks_select_tier', [self::$instance, 'ajax_select_tier']);
            add_action('wp_ajax_nopriv_orabooks_select_tier', [self::$instance, 'ajax_select_tier']);
            // SL-013: Google OIDC endpoints
            add_action('wp_ajax_nopriv_orabooks_oidc_initiate', [self::$instance, 'ajax_oidc_initiate']);
            add_action('wp_ajax_orabooks_oidc_initiate', [self::$instance, 'ajax_oidc_initiate']);
            add_action('wp_ajax_nopriv_orabooks_oidc_callback', [self::$instance, 'ajax_oidc_callback']);
            add_action('wp_ajax_orabooks_oidc_callback', [self::$instance, 'ajax_oidc_callback']);
            // SL-013: ingress-level partner accounting isolation
            add_action('template_redirect', [self::$instance, 'enforce_partner_accounting_isolation'], 1);
            add_filter('rest_pre_dispatch', [self::$instance, 'enforce_partner_accounting_isolation_rest'], 10, 3);
            // SL-003: Admin partner approval endpoints registered in OraBooks_Partner

            if (function_exists('is_multisite') && is_multisite()) {
                add_action('wpmu_activate_user', [self::$instance, 'handle_multisite_user_activation'], 10, 3);
                add_action('wpmu_activate_blog', [self::$instance, 'handle_multisite_blog_activation'], 10, 5);
            }
        }
        return self::$instance;
    }

    /**
     * After multisite signup activation, link pending OraBooks metadata if present.
     */
    public function handle_multisite_user_activation($user_id, $password, $meta) {
        self::sync_orabooks_user_after_wp_activation((int) $user_id, is_array($meta) ? $meta : []);
    }

    public function handle_multisite_blog_activation($blog_id, $user_id, $password, $title, $meta) {
        self::sync_orabooks_user_after_wp_activation((int) $user_id, is_array($meta) ? $meta : []);
    }
    
    /**
     * Handle user registration
     */
    public static function register($data) {
        global $wpdb;
        
        $table_users = OraBooks_Database::table('users');
        $email = sanitize_email($data['email']);
        $password = $data['password'] ?? '';
        $user_type = $data['user_type'] ?? 'customer';
        $partner_code = $data['partner_code'] ?? '';
        $accept_terms = !empty($data['accept_terms']);
        $terms_version = $data['terms_version'] ?? '';
        $partner_type = $data['partner_type'] ?? 'individual';
        $organization_name = $data['organization_name'] ?? '';
        
        // Validate email
        if (!is_email($email)) {
            return new WP_Error('invalid_email', 'Invalid email address');
        }
        
        // Validate password
        $password_check = orabooks_validate_password($password);
        if ($password_check !== true) {
            return new WP_Error('weak_password', $password_check);
        }
        
        // Rate limit: 5 registrations per hour per IP
        $ip = orabooks_get_client_ip();
        if (!orabooks_check_rate_limit('register_' . $ip, 5, 3600)) {
            return new WP_Error('rate_limit', 'Too many registration attempts. Please try again later.');
        }

        if (!orabooks_users_can_register()) {
            return new WP_Error('registration_disabled', 'User registration is disabled on this site.');
        }
        
        // Check if email already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_users} WHERE email = %s", $email
        ));
        if ($existing) {
            return new WP_Error('email_exists', 'This email is already registered');
        }

        if (function_exists('email_exists') && email_exists($email)) {
            return new WP_Error('email_exists', 'This email is already registered');
        }
        
        // Partner validation
        if ($user_type === 'partner') {
            if (!$accept_terms) {
                return new WP_Error('terms_required', 'Partner terms must be accepted');
            }
            if (!in_array($partner_type, ['individual', 'accountant', 'agency', 'reseller', 'strategic_partner'])) {
                $partner_type = 'individual';
            }
            if (in_array($partner_type, ['agency', 'reseller', 'strategic_partner']) && empty($organization_name)) {
                return new WP_Error('org_name_required', 'Organization name is required for this partner type');
            }
        }
        
        $password_hash = OraBooks_Secrets::hash_password($password);
        $verification_token = orabooks_random_string(32);
        $verification_expires = date('Y-m-d H:i:s', time() + 86400); // 24 hours
        $pending_wp_signup = orabooks_multisite_uses_signup_activation();
        $wp_user_id = null;
        
        // Create user
        $insert_data = [
            'email' => $email,
            'password_hash' => $password_hash,
            'is_active' => 1,
            'is_email_verified' => 0,
            'email_verification_token' => $verification_token,
            'email_verification_expires_at' => $verification_expires,
            'is_2fa_enabled' => 0,
            'auth_provider' => 'local',
            'org_id' => null,
            'is_partner' => ($user_type === 'partner') ? 1 : 0,
            'wp_user_id' => null,
        ];
        $insert_format = ['%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', null, '%d', null];

        if ($user_type === 'partner') {
            $insert_data['pending_partner_type'] = $partner_type;
            $insert_data['pending_organization_name'] = in_array($partner_type, ['agency', 'reseller', 'strategic_partner'], true)
                ? $organization_name
                : null;
            $insert_format[] = '%s';
            $insert_format[] = '%s';
        }

        $wpdb->insert($table_users, $insert_data, $insert_format);
        
        $user_id = $wpdb->insert_id;
        if (!$user_id) {
            $db_error = $wpdb->last_error ? ' Database: ' . $wpdb->last_error : '';
            return new WP_Error(
                'creation_failed',
                'Failed to create user. Deactivate and reactivate the OraBooks plugin, then try again.' . $db_error
            );
        }

        $signup_meta = [
            'orabooks_user_id' => $user_id,
            'orabooks_user_type' => $user_type,
            'orabooks_partner_type' => $partner_type,
            'orabooks_organization_name' => $organization_name,
            'orabooks_verification_token' => $verification_token,
        ];

        $wp_result = orabooks_create_wp_user_for_registration($email, $password, $signup_meta);
        if (is_wp_error($wp_result)) {
            $wpdb->delete($table_users, ['id' => $user_id], ['%d']);
            return $wp_result;
        }

        if (is_array($wp_result) && !empty($wp_result['pending_signup'])) {
            $pending_wp_signup = true;
        } elseif ($wp_result) {
            $wp_user_id = (int) $wp_result;
            $wpdb->update(
                $table_users,
                ['wp_user_id' => $wp_user_id],
                ['id' => $user_id],
                ['%d'],
                ['%d']
            );
        }
        
        $email_warning = '';
        if ($pending_wp_signup) {
            $email_result = self::send_verification_email($email, $verification_token);
            if (is_wp_error($email_result)) {
                $email_warning = $email_result->get_error_message();
            }
        } else {
            $email_result = self::send_registration_emails($wp_user_id, $email, $verification_token);
            if (is_wp_error($email_result)) {
                $email_warning = $email_result->get_error_message();
            }
        }

        if ($email_warning !== '') {
            orabooks_log_event('verification_email_failed', "Verification email failed for $email", 'error', [
                'error' => $email_warning
            ], $user_id, null);
        }
        
        // Store partner type and org name in session for backward compatibility
        if ($user_type === 'partner') {
            $_SESSION['orabooks_partner_type'] = $partner_type;
            $_SESSION['orabooks_partner_org_name'] = $organization_name;
            
            // Record terms acceptance
            $table_terms = OraBooks_Database::table('partner_terms_acceptance');
            $wpdb->insert(
                $table_terms,
                [
                    'user_id' => $user_id,
                    'terms_version' => $terms_version ?: '1.0',
                    'ip_address' => $ip,
                    'user_agent' => orabooks_get_user_agent()
                ],
                ['%d', '%s', '%s', '%s']
            );
        }
        
        // Handle partner attribution for customer signup with partner code
        if ($user_type === 'customer' && !empty($partner_code)) {
            $attribution_result = self::process_attribution($user_id, $partner_code, $email);
            if (is_wp_error($attribution_result)) {
                orabooks_log_event('partner_attribution_failed', $attribution_result->get_error_message(), 'warning', [
                    'partner_code' => $partner_code
                ], $user_id, null);
            }
        }
        
        // Do not issue a session JWT until email is verified (SL-013)
        orabooks_log_event('user_registered', "User registered: $email ($user_type)", 'info', [
            'user_type' => $user_type
        ], $user_id, null);
        
        return [
            'user_id' => $user_id,
            'email' => $email,
            'message' => $email_warning
                ? 'Account created, but verification email could not be sent.'
                : ($pending_wp_signup
                    ? 'Registration started. Check your email to activate your WordPress account and verify OraBooks.'
                    : 'Verification email sent'),
            'email_warning' => $email_warning,
            'requires_email_verification' => true,
            'is_partner' => ($user_type === 'partner') ? 1 : 0,
            'pending_wp_activation' => $pending_wp_signup ? 1 : 0,
            'wp_user_id' => $wp_user_id,
        ];
    }

    /**
     * Link an OraBooks user after WordPress multisite signup activation.
     */
    private static function sync_orabooks_user_after_wp_activation($wp_user_id, $meta) {
        if (!$wp_user_id || empty($meta['orabooks_user_id'])) {
            return;
        }

        global $wpdb;
        $table_users = OraBooks_Database::table('users');
        $orabooks_user_id = (int) $meta['orabooks_user_id'];

        $wpdb->update(
            $table_users,
            ['wp_user_id' => $wp_user_id],
            ['id' => $orabooks_user_id],
            ['%d'],
            ['%d']
        );

        if (function_exists('wp_update_user')) {
            wp_update_user([
                'ID' => $wp_user_id,
                'role' => 'subscriber',
            ]);
        }

        if (function_exists('is_multisite') && is_multisite() && function_exists('add_user_to_blog')) {
            $blog_id = get_current_blog_id();
            if (!is_user_member_of_blog($wp_user_id, $blog_id)) {
                add_user_to_blog($blog_id, $wp_user_id, 'subscriber');
            }
        }

        orabooks_log_event('wp_user_activated', 'WordPress multisite user activated for OraBooks account', 'info', [
            'wp_user_id' => $wp_user_id,
        ], $orabooks_user_id, null);
    }
    
    /**
     * Process partner attribution during signup
     */
    private static function process_attribution($customer_user_id, $partner_code, $customer_email) {
        global $wpdb;
        
        $table_codes = OraBooks_Database::table('partner_codes');
        $table_attributions = OraBooks_Database::table('partner_attributions');
        
        $normalized = strtoupper(trim($partner_code));
        $code = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_codes} WHERE partner_code_normalized = %s AND status = 'active'",
            $normalized
        ));
        
        if (!$code) {
            return new WP_Error('invalid_code', 'Invalid or inactive partner code');
        }
        
        // Self-attribution check
        if ($code->user_id == $customer_user_id) {
            return new WP_Error('self_attribution', 'You cannot use your own partner code');
        }
        
        // Same email domain check (if enabled)
        if (get_option('orabooks_block_same_email_domain', 0)) {
            $partner_email = orabooks_get_user_email($code->user_id);
            $partner_domain = self::get_email_domain($partner_email);
            $customer_domain = self::get_email_domain($customer_email);
            if ($partner_domain && $customer_domain && strtolower($partner_domain) === strtolower($customer_domain)) {
                return new WP_Error('same_domain', 'Same email domain not allowed for partner attribution');
            }
        }
        
        $idempotency_key = hash('sha256', $normalized . $customer_email);
        
        // Check for existing attribution
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_attributions} WHERE idempotency_key = %s",
            $idempotency_key
        ));
        if ($existing) {
            return true; // Already exists, skip
        }
        
        $wpdb->insert(
            $table_attributions,
            [
                'org_id' => null,
                'partner_user_id' => $code->user_id,
                'customer_user_id' => $customer_user_id,
                'customer_email' => $customer_email,
                'partner_code_used' => $code->partner_code,
                'status' => 'pending',
                'idempotency_key' => $idempotency_key
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%s']
        );
        
        orabooks_log_event('partner_attribution_created', "Partner attribution created (pending)", 'info', [
            'partner_user_id' => $code->user_id,
            'partner_code' => $code->partner_code
        ], $customer_user_id, null);
        
        return true;
    }
    
    private static function get_email_domain($email) {
        if (!is_string($email) || strpos($email, '@') === false) {
            return '';
        }

        return strtolower(trim(substr(strrchr($email, '@'), 1)));
    }
    
    private static function site_name() {
        if (function_exists('get_bloginfo')) {
            $name = get_bloginfo('name');
            if (!empty($name)) {
                return function_exists('wp_specialchars_decode') ? wp_specialchars_decode($name, ENT_QUOTES) : html_entity_decode($name, ENT_QUOTES);
            }
        }
        
        return 'OraBooks';
    }
    
    private static function verification_url($token) {
        return home_url('/verify-email/?token=' . rawurlencode($token));
    }
    
    private static function reset_password_url($token) {
        return home_url('/reset-password/?token=' . rawurlencode($token));
    }
    
    private static function send_verification_email($email, $token) {
        $verify_url = self::verification_url($token);
        $subject = sprintf(__('[%s] Verify your email address', 'orabooks'), self::site_name());
        $message = sprintf(
            __("Welcome to %1\$s.\n\nPlease verify your email address to activate your OraBooks account:\n%2\$s\n\nThis link expires in 24 hours. If you did not create an account, you can ignore this email.", 'orabooks'),
            self::site_name(),
            $verify_url
        );

        return self::send_email($email, $subject, $message, 'verification_email_send_failed');
    }

    /**
     * Send WordPress-native registration notifications plus OraBooks verification.
     */
    private static function send_registration_emails($wp_user_id, $email, $verification_token) {
        if ($wp_user_id && function_exists('wp_send_new_user_notifications')) {
            wp_send_new_user_notifications($wp_user_id, 'user');
        }

        return self::send_verification_email($email, $verification_token);
    }
    
    private static function send_password_reset_email($email, $token) {
        $reset_url = self::reset_password_url($token);
        $subject = sprintf('[%s] Reset your password', self::site_name());
        $message = "We received a request to reset your OraBooks password.\n\n";
        $message .= "Use this secure link to choose a new password:\n";
        $message .= $reset_url . "\n\n";
        $message .= "This link expires in 1 hour. If you did not request a password reset, you can ignore this email.";
        
        return self::send_email($email, $subject, $message, 'password_reset_email_send_failed');
    }
    
    private static function send_email($to, $subject, $message, $error_code) {
        if (!function_exists('wp_mail')) {
            return true;
        }

        $from_email = function_exists('get_option') ? get_option('admin_email') : '';
        if (function_exists('is_multisite') && is_multisite() && function_exists('get_site_option')) {
            $network_email = get_site_option('admin_email');
            if (!empty($network_email) && is_email($network_email)) {
                $from_email = $network_email;
            }
        }
        if (empty($from_email) || !is_email($from_email)) {
            $from_email = 'wordpress@' . (isset($_SERVER['HTTP_HOST']) ? preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']) : 'localhost');
        }

        $from_name = self::site_name();
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            sprintf('From: %s <%s>', $from_name, $from_email),
        ];

        $content_type_filter = static function () {
            return 'text/plain';
        };
        add_filter('wp_mail_content_type', $content_type_filter);

        $sent = wp_mail($to, $subject, $message, $headers);

        remove_filter('wp_mail_content_type', $content_type_filter);

        if (!$sent) {
            global $phpmailer;
            $detail = '';
            if (isset($phpmailer) && is_object($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                $detail = ' ' . $phpmailer->ErrorInfo;
            }

            return new WP_Error(
                $error_code,
                __('Email could not be sent. Configure WordPress mail (SMTP plugin recommended) or check spam filters.', 'orabooks') . $detail
            );
        }

        return true;
    }
    
    /**
     * Handle login
     */
    public static function login($email, $password, $expected_subdomain = '') {
        global $wpdb;
        
        $table_users = OraBooks_Database::table('users');
        $ip = orabooks_get_client_ip();
        
        // Rate limit: 5 failures per 15 min per IP+email
        $rate_key = 'login_' . $ip . '_' . $email;
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_users} WHERE email = %s", $email
        ));
        
        if (!$user || !OraBooks_Secrets::verify_password($password, $user->password_hash)) {
            if (!orabooks_check_rate_limit($rate_key, 5, 900)) {
                orabooks_log_event('login_failure', "Failed login attempt for $email", 'warning', [], null, null);
                return new WP_Error('rate_limit', 'Too many failed login attempts. Try again after 15 minutes.');
            }
            orabooks_log_event('login_failure', "Failed login attempt for $email", 'warning', [], null, null);
            return new WP_Error('invalid_credentials', 'Invalid email or password');
        }
        
        if (!$user->is_email_verified) {
            return new WP_Error('email_not_verified', 'Please verify your email before logging in.');
        }
        
        if (!$user->is_active) {
            return new WP_Error('account_disabled', 'Your account has been disabled.');
        }
        
        // Check if 2FA is required
        if ($user->is_2fa_enabled) {
            $temp_token = OraBooks_Secrets::generate_jwt([
                'user_id' => $user->id,
                'purpose' => '2fa_challenge',
                'exp' => time() + 300 // 5 min
            ]);
            return [
                'requires_2fa' => true,
                'temp_token' => $temp_token,
                'user_id' => $user->id
            ];
        }
        
        // Auto-create partner org on first login
        if ($user->is_partner && !$user->org_id) {
            return self::handle_partner_first_login($user);
        }
        
        // Customer first login - needs tier selection
        if (!$user->is_partner && !$user->org_id) {
            $jwt = OraBooks_Secrets::generate_jwt([
                'user_id' => $user->id,
                'email' => $user->email,
                'is_partner' => 0,
                'needs_tier_selection' => true,
                'org_id' => null
            ]);
            
            return [
                'needs_tier_selection' => true,
                'token' => $jwt,
                'message' => 'Please select a tier to continue'
            ];
        }
        
        // Normal login with org
        $org = OraBooks_Organization::get($user->org_id);
        if ($org && $org->status !== 'active') {
            $partner_pending = $user->is_partner
                && $org->organization_type === 'partner'
                && $org->status === 'pending_setup';
            if (!$partner_pending) {
                return new WP_Error('org_inactive', 'Your organization is not active. Please contact support.');
            }
        }
        
        // Subdomain mismatch check (if expected_subdomain is provided)
        if ($org && !empty($expected_subdomain)) {
            if (strtolower(trim($expected_subdomain)) !== strtolower(trim($org->subdomain))) {
                orabooks_log_event('login_subdomain_mismatch', "Subdomain mismatch for {$email}: expected {$expected_subdomain}, org has {$org->subdomain}", 'warning', [
                    'expected' => $expected_subdomain,
                    'actual' => $org->subdomain
                ], $user->id, $user->org_id);
                return new WP_Error('subdomain_mismatch', 'This account does not belong to the specified organization subdomain.');
            }
        }
        
        $role = $user->org_id ? orabooks_get_user_role($user->id, $user->org_id) : 'viewer';
        $jwt = OraBooks_Secrets::generate_jwt([
            'user_id' => $user->id,
            'email' => $user->email,
            'org_id' => $user->org_id,
            'role' => $role,
            'subdomain' => $org ? $org->subdomain : '',
            'is_partner' => $user->is_partner
        ]);
        
        $refresh_token = orabooks_random_string(32);
        self::store_refresh_token($user->id, $user->org_id, $refresh_token);
        
        orabooks_log_event('login_success', "User logged in: $email", 'info', [], $user->id, $user->org_id);
        
        return orabooks_enrich_login_response([
            'token' => $jwt,
            'refresh_token' => $refresh_token,
            'user_id' => $user->id,
            'org_id' => $user->org_id,
            'role' => $role,
            'subdomain' => $org ? $org->subdomain : '',
            'is_partner' => $user->is_partner
        ]);
    }
    
    /**
     * Handle partner first login - auto create org
     */
    private static function handle_partner_first_login($user) {
        $partner_type = $user->pending_partner_type
            ?? $_SESSION['orabooks_partner_type']
            ?? 'individual';
        $organization_name = $user->pending_organization_name
            ?? $_SESSION['orabooks_partner_org_name']
            ?? '';
        
        // Create partner org
        $org_name = !empty($organization_name) ? $organization_name : 'Partner ' . $user->id;
        $org_result = OraBooks_Organization::create([
            'owner_id' => $user->id,
            'organization_type' => 'partner',
            'tier' => 'partner',
            'name' => $org_name,
            'subdomain' => 'partner-' . $user->id
        ]);
        
        if (is_wp_error($org_result)) {
            return $org_result;
        }
        
        // Generate partner code
        self::generate_partner_code($user->id, $org_result['org_id'], $partner_type, $organization_name);

        global $wpdb;
        $table_users = OraBooks_Database::table('users');
        $wpdb->update(
            $table_users,
            [
                'pending_partner_type' => null,
                'pending_organization_name' => null,
            ],
            ['id' => $user->id],
            [null, null],
            ['%d']
        );
        unset($_SESSION['orabooks_partner_type'], $_SESSION['orabooks_partner_org_name']);
        
        $jwt = OraBooks_Secrets::generate_jwt([
            'user_id' => $user->id,
            'email' => $user->email,
            'org_id' => $org_result['org_id'],
            'role' => 'owner',
            'subdomain' => $org_result['subdomain'],
            'is_partner' => 1
        ]);
        
        $refresh_token = orabooks_random_string(32);
        self::store_refresh_token($user->id, $org_result['org_id'], $refresh_token);
        
        orabooks_log_event('partner_org_created', "Partner org auto-created for user {$user->id}", 'info', [
            'partner_type' => $partner_type,
            'organization_name' => $organization_name
        ], $user->id, $org_result['org_id']);
        
        return orabooks_enrich_login_response([
            'token' => $jwt,
            'refresh_token' => $refresh_token,
            'user_id' => $user->id,
            'org_id' => $org_result['org_id'],
            'role' => 'owner',
            'is_partner' => 1,
            'subdomain' => $org_result['subdomain'],
            'redirect_to' => '/partner-onboarding/'
        ]);
    }
    
    /**
     * Generate partner code
     */
    private static function generate_partner_code($user_id, $org_id, $partner_type, $organization_name) {
        global $wpdb;
        $table_codes = OraBooks_Database::table('partner_codes');
        
        // Disable any previous active codes
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_codes} SET status = 'disabled', disabled_at = NOW() WHERE user_id = %d AND status = 'active'",
            $user_id
        ));
        
        $code = orabooks_generate_partner_code();
        for ($i = 0; $i < 10; $i++) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_codes} WHERE partner_code = %s",
                $code
            ));
            if (!$exists) {
                break;
            }
            $code = orabooks_generate_partner_code();
        }
        
        $wpdb->insert(
            $table_codes,
            [
                'org_id' => $org_id,
                'user_id' => $user_id,
                'partner_code' => $code,
                'partner_type' => $partner_type,
                'organization_name' => !empty($organization_name) ? $organization_name : null,
                'status' => 'pending_review'
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s']
        );
        
        orabooks_log_event('partner_code_generated', "Partner code generated: $code", 'info', [
            'partner_type' => $partner_type
        ], $user_id, $org_id);
    }
    
    /**
     * Handle email verification
     */
    public static function verify_email($token) {
        global $wpdb;
        
        $table_users = OraBooks_Database::table('users');
        
        // Token is stored in plaintext in the database (not hashed), per spec
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_users} WHERE email_verification_token = %s AND email_verification_expires_at > NOW()",
            $token
        ));
        
        if (!$user) {
            return new WP_Error('invalid_token', 'Invalid or expired verification token');
        }
        
        $wpdb->update(
            $table_users,
            ['is_email_verified' => 1, 'email_verification_token' => null, 'email_verification_expires_at' => null],
            ['id' => $user->id],
            ['%d', null, null],
            ['%d']
        );

        if (!empty($user->wp_user_id) && function_exists('wp_update_user')) {
            wp_update_user([
                'ID' => (int) $user->wp_user_id,
                'user_activation_key' => '',
            ]);
        } elseif (function_exists('get_user_by')) {
            $wp_user = get_user_by('email', $user->email);
            if ($wp_user) {
                $wpdb->update(
                    $table_users,
                    ['wp_user_id' => (int) $wp_user->ID],
                    ['id' => $user->id],
                    ['%d'],
                    ['%d']
                );
                wp_update_user([
                    'ID' => (int) $wp_user->ID,
                    'user_activation_key' => '',
                ]);
            }
        }
        
        orabooks_log_event('email_verified', "Email verified: {$user->email}", 'info', [], $user->id, $user->org_id);
        
        // If attribution pending, verify it
        $table_attributions = OraBooks_Database::table('partner_attributions');
        $pending = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_attributions} WHERE customer_user_id = %d AND status = 'pending'",
            $user->id
        ));
        
        if ($pending) {
            $wpdb->update(
                $table_attributions,
                ['status' => 'verified', 'verified_at' => current_time('mysql')],
                ['id' => $pending->id],
                ['%s', '%s'],
                ['%d']
            );
            
            // Update partner's last attribution and reset reminders
            $table_codes = OraBooks_Database::table('partner_codes');
            $wpdb->update(
                $table_codes,
                [
                    'last_attribution_at' => current_time('mysql'),
                    'deactivation_reminder_sent_at' => null,
                    'low_activity_reminder_sent_at' => null
                ],
                ['user_id' => $pending->partner_user_id],
                ['%s', null, null],
                ['%d']
            );
            
            // Update attribution org_id if user has org now
            if ($user->org_id) {
                $wpdb->update(
                    $table_attributions,
                    ['org_id' => $user->org_id],
                    ['id' => $pending->id],
                    ['%d'],
                    ['%d']
                );
            }
            
            orabooks_log_event('partner_attribution_verified', "Partner attribution verified", 'info', [
                'attribution_id' => $pending->id,
                'partner_user_id' => $pending->partner_user_id
            ], $user->id, $user->org_id);
            
            // Fire event for SL-068 Commission Engine
            do_action('orabooks_partner_attribution_verified', $pending->id, $pending);
            orabooks_publish_event('partner_attribution_verified', $pending->id, [
                'attribution_id' => $pending->id,
                'partner_user_id' => $pending->partner_user_id,
                'customer_user_id' => $pending->customer_user_id,
                'verified_at' => $pending->verified_at,
            ]);
        }
        
        return true;
    }
    
    /**
     * Store refresh token
     */
    private static function store_refresh_token($user_id, $org_id, $token) {
        global $wpdb;
        $table = OraBooks_Database::table('refresh_tokens');
        $expires = date('Y-m-d H:i:s', time() + 604800); // 7 days
        
        $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'org_id' => $org_id,
                'token_hash' => orabooks_hash_token($token),
                'expires_at' => $expires,
                'device_metadata' => json_encode([
                    'ip' => orabooks_get_client_ip(),
                    'user_agent' => orabooks_get_user_agent()
                ])
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );
    }
    
    /**
     * Refresh token rotation
     */
    public static function refresh_token($old_token) {
        global $wpdb;
        $table = OraBooks_Database::table('refresh_tokens');
        $hash = orabooks_hash_token($old_token);
        
        $token = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE token_hash = %s AND expires_at > NOW() AND revoked_at IS NULL",
            $hash
        ));
        
        if (!$token) {
            return new WP_Error('invalid_token', 'Invalid or expired refresh token');
        }
        
        // Revoke old token
        $wpdb->update(
            $table,
            ['revoked_at' => current_time('mysql')],
            ['id' => $token->id],
            ['%s'],
            ['%d']
        );
        
        // Get user info
        $table_users = OraBooks_Database::table('users');
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_users} WHERE id = %d",
            $token->user_id
        ));
        
        if (!$user) {
            return new WP_Error('user_not_found', 'User not found');
        }
        
        $role = $user->org_id ? orabooks_get_user_role($user->id, $user->org_id) : 'viewer';
        
        // Generate new tokens
        $new_jwt = OraBooks_Secrets::generate_jwt([
            'user_id' => $user->id,
            'email' => $user->email,
            'org_id' => $user->org_id,
            'role' => $role,
            'is_partner' => $user->is_partner
        ]);
        
        $new_refresh = orabooks_random_string(32);
        self::store_refresh_token($user->id, $user->org_id, $new_refresh);
        
        orabooks_log_event('refresh_token_rotated', "Refresh token rotated for user {$user->id}", 'info', [], $user->id, $user->org_id);
        
        return [
            'token' => $new_jwt,
            'refresh_token' => $new_refresh
        ];
    }
    
    /**
     * Revoke all refresh tokens for a user in an org
     */
    public static function revoke_user_tokens($user_id, $org_id = null) {
        global $wpdb;
        $table = OraBooks_Database::table('refresh_tokens');
        
        $where = 'user_id = %d';
        $params = [$user_id];
        
        if ($org_id !== null) {
            $where .= ' AND org_id = %d';
            $params[] = $org_id;
        }
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET revoked_at = NOW() WHERE {$where} AND revoked_at IS NULL",
            $params
        ));
    }
    
    /**
     * Handle password reset request
     */
    public static function forgot_password($email) {
        global $wpdb;
        
        $table_users = OraBooks_Database::table('users');
        $ip = orabooks_get_client_ip();
        
        // Rate limit: 3 per hour per email
        if (!orabooks_check_rate_limit('forgot_' . $email, 3, 3600)) {
            return new WP_Error('rate_limit', 'Too many requests. Please try again later.');
        }
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, email FROM {$table_users} WHERE email = %s", $email
        ));
        
        if (!$user) {
            // Don't reveal if email exists
            return true;
        }
        
        $reset_token = orabooks_random_string(32);
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        
        $wpdb->update(
            $table_users,
            [
                'password_reset_token' => $reset_token,
                'password_reset_expires_at' => $expires
            ],
            ['id' => $user->id],
            ['%s', '%s'],
            ['%d']
        );
        
        $email_result = self::send_password_reset_email($email, $reset_token);
        if (is_wp_error($email_result)) {
            orabooks_log_event('password_reset_email_failed', "Password reset email failed for $email", 'error', [
                'error' => $email_result->get_error_message()
            ], $user->id, null);
            return $email_result;
        }
        
        orabooks_log_event('password_reset_requested', "Password reset requested for $email", 'info', [], $user->id, null);
        
        return true;
    }
    
    /**
     * Handle password reset
     */
    public static function reset_password($token, $new_password) {
        global $wpdb;
        
        $table_users = OraBooks_Database::table('users');
        
        $password_check = orabooks_validate_password($new_password);
        if ($password_check !== true) {
            return new WP_Error('weak_password', $password_check);
        }
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_users} WHERE password_reset_token = %s AND password_reset_expires_at > NOW()",
            $token
        ));
        
        if (!$user) {
            return new WP_Error('invalid_token', 'Invalid or expired reset token');
        }
        
        $password_hash = OraBooks_Secrets::hash_password($new_password);
        
        $wpdb->update(
            $table_users,
            [
                'password_hash' => $password_hash,
                'password_reset_token' => null,
                'password_reset_expires_at' => null
            ],
            ['id' => $user->id],
            ['%s', null, null],
            ['%d']
        );
        
        // Revoke all refresh tokens - password reset revokes ALL devices
        self::revoke_user_tokens($user->id);
        
        orabooks_log_event('password_reset_completed', "Password reset completed for user {$user->id}", 'info', [], $user->id, $user->org_id);
        
        return true;
    }
    
    // ============= AJAX HANDLERS =============
    
    public function ajax_register() {
        try {
            $result = self::register($_POST);
        } catch (Throwable $e) {
            orabooks_log_event('registration_exception', $e->getMessage(), 'error');
            orabooks_json_error('Registration failed due to a server error. Please try again.', 200);
        }

        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 200);
        }

        orabooks_json_success($result, $result['message'] ?? 'Registration successful. Verification email sent.');
    }
    
    public function ajax_login() {
        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Auto-detect subdomain from HTTP host (e.g., "mycompany.orabooks.app")
        // Fall back to explicit POST param if provided by the frontend
        $subdomain = self::detect_subdomain_from_host();
        if (empty($subdomain)) {
            $subdomain = strtolower(trim($_POST['subdomain'] ?? ''));
        }
        
        $result = self::login($email, $password, $subdomain);
        
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 401);
        }
        
        // Include detected subdomain in response so the frontend can use it
        if (is_array($result) && !empty($subdomain)) {
            $result['detected_subdomain'] = $subdomain;
        }

        $result = orabooks_enrich_login_response($result);

        orabooks_persist_login_session($result, $password);
        
        orabooks_json_success($result, 'Login successful');
    }
    
    public function ajax_verify_email() {
        $token = $_GET['token'] ?? '';
        
        if (empty($token)) {
            wp_die('Invalid verification link.');
        }
        
        $result = self::verify_email($token);
        
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
        
        wp_redirect(home_url('/login?verified=1'));
        exit;
    }

    public function ajax_verify_email_token() {
        $token = sanitize_text_field($_POST['token'] ?? $_GET['token'] ?? '');

        if (empty($token)) {
            orabooks_json_error(__('Invalid verification link.', 'orabooks'), 400);
        }

        $result = self::verify_email($token);

        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success([], __('Email verified successfully. You can now log in.', 'orabooks'));
    }
    
    public function ajax_resend_verification() {
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (!orabooks_check_rate_limit('resend_' . $email, 3, 3600)) {
            orabooks_json_error('Too many requests. Please try again later.', 429);
        }
        
        // Regenerate token and resend
        global $wpdb;
        $table_users = OraBooks_Database::table('users');
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_users} WHERE email = %s", $email
        ));
        
        if ($user) {
            $new_token = orabooks_random_string(32);
            $expires = date('Y-m-d H:i:s', time() + 86400);
            
            $wpdb->update(
                $table_users,
                ['email_verification_token' => $new_token, 'email_verification_expires_at' => $expires],
                ['id' => $user->id],
                ['%s', '%s'],
                ['%d']
            );
            
            $email_result = self::send_verification_email($email, $new_token);
            if (is_wp_error($email_result)) {
                orabooks_json_error($email_result->get_error_message(), 500);
            }
            
            orabooks_log_event('verification_resent', "Verification email resent to $email", 'info', [], $user->id, null);
        }
        
        orabooks_json_success([], 'Verification email resent');
    }
    
    public function ajax_forgot_password() {
        $email = sanitize_email($_POST['email'] ?? '');
        $result = self::forgot_password($email);
        if (is_wp_error($result)) {
            $status_code = ($result->get_error_code() === 'rate_limit') ? 429 : 500;
            orabooks_json_error($result->get_error_message(), $status_code);
        }
        orabooks_json_success([], 'If the email exists, a reset link has been sent.');
    }
    
    public function ajax_reset_password() {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $result = self::reset_password($token, $password);
        
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        
        orabooks_json_success([], 'Password reset successfully');
    }
    
    public function ajax_check_subdomain() {
        $subdomain = strtolower(trim($_POST['subdomain'] ?? ''));
        
        // Rate limit: 10 per minute
        $ip = orabooks_get_client_ip();
        if (!orabooks_check_rate_limit('subdomain_check_' . $ip, 10, 60)) {
            orabooks_json_error('Too many availability checks. Please wait.', 429);
        }
        
        $validation = orabooks_validate_subdomain($subdomain);
        if ($validation !== true) {
            orabooks_json_success(['available' => false, 'message' => $validation]);
        }
        
        global $wpdb;
        $table = OraBooks_Database::table('organizations');
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE subdomain = %s", $subdomain
        ));
        $site_taken = function_exists('orabooks_multisite_subdomain_taken')
            ? orabooks_multisite_subdomain_taken($subdomain)
            : false;
        $taken = (bool) $exists || $site_taken;
        
        orabooks_json_success([
            'available' => !$taken,
            'message' => $taken ? 'Subdomain already taken' : 'Subdomain is available'
        ]);
    }
    
    public function ajax_setup_2fa() {
        if (!is_user_logged_in()) {
            orabooks_json_error('Not authenticated', 401);
        }
        
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Authentication required', 401);
        }
        $secret = OraBooks_Secrets::generate_totp_secret();
        
        // Store secret temporarily
        update_user_meta($user_id, 'orabooks_2fa_temp_secret', $secret);
        
        $email = wp_get_current_user()->user_email;
        $qr_url = OraBooks_Secrets::get_totp_qr_url($secret, $email);
        $backup_codes = OraBooks_Secrets::generate_backup_codes();
        
        // Store backup codes temporarily
        update_user_meta($user_id, 'orabooks_2fa_temp_backup_codes', $backup_codes);
        
        orabooks_json_success([
            'secret' => $secret,
            'qr_code_url' => $qr_url,
            'backup_codes' => $backup_codes
        ]);
    }
    
    public function ajax_verify_2fa_setup() {
        if (!is_user_logged_in()) {
            orabooks_json_error('Not authenticated', 401);
        }
        
        $user_id = get_current_user_id();
        $otp = $_POST['otp_code'] ?? '';
        $temp_secret = get_user_meta($user_id, 'orabooks_2fa_temp_secret', true);
        
        if (!$temp_secret) {
            orabooks_json_error('2FA setup not initiated', 400);
        }
        
        if (!OraBooks_Secrets::verify_totp($temp_secret, $otp)) {
            orabooks_json_error('Invalid OTP code', 400);
        }
        
        // Store 2FA secret permanently
        update_user_meta($user_id, 'orabooks_2fa_secret', $temp_secret);
        delete_user_meta($user_id, 'orabooks_2fa_temp_secret');
        
        // Store backup code hashes
        $backup_codes = get_user_meta($user_id, 'orabooks_2fa_temp_backup_codes', true);
        if ($backup_codes) {
            global $wpdb;
            $table = OraBooks_Database::table('2fa_backup_codes');
            foreach ($backup_codes as $code) {
                $wpdb->insert(
                    $table,
                    ['user_id' => $user_id, 'code_hash' => OraBooks_Secrets::hash_password($code)],
                    ['%d', '%s']
                );
            }
            delete_user_meta($user_id, 'orabooks_2fa_temp_backup_codes');
        }
        
        // Enable 2FA
        global $wpdb;
        $table_users = OraBooks_Database::table('users');
        $wpdb->update(
            $table_users,
            ['is_2fa_enabled' => 1],
            ['id' => $user_id],
            ['%d'],
            ['%d']
        );
        
        orabooks_log_event('2fa_enabled', "2FA enabled for user $user_id", 'info', [], $user_id, null);
        orabooks_json_success([], '2FA enabled successfully');
    }
    
    public function ajax_2fa_challenge() {
        $temp_token = $_POST['temp_token'] ?? '';
        $otp = $_POST['otp_code'] ?? '';
        $backup_code = $_POST['backup_code'] ?? '';
        
        $payload = OraBooks_Secrets::verify_jwt($temp_token);
        if (!$payload || ($payload['purpose'] ?? '') !== '2fa_challenge') {
            orabooks_json_error('Invalid or expired challenge token', 401);
        }
        
        $user_id = $payload['user_id'];
        $secret = get_user_meta($user_id, 'orabooks_2fa_secret', true);
        
        if (!empty($otp) && OraBooks_Secrets::verify_totp($secret, $otp)) {
            // Valid OTP - proceed with login
        } elseif (!empty($backup_code)) {
            // Check backup code
            global $wpdb;
            $table = OraBooks_Database::table('2fa_backup_codes');
            $stored = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d AND used = 0",
                $user_id
            ));
            
            $valid = false;
            foreach ($stored as $row) {
                if (OraBooks_Secrets::verify_password($backup_code, $row->code_hash)) {
                    $wpdb->update(
                        $table,
                        ['used' => 1],
                        ['id' => $row->id],
                        ['%d'],
                        ['%d']
                    );
                    $valid = true;
                    break;
                }
            }
            
            if (!$valid) {
                orabooks_json_error('Invalid or already used backup code', 401);
            }
        } else {
            orabooks_json_error('Invalid verification code', 401);
        }
        
        // Complete login
        global $wpdb;
        $table_users = OraBooks_Database::table('users');
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_users} WHERE id = %d", $user_id));
        
        if (!$user) {
            orabooks_json_error('User not found', 404);
        }
        
        $role = $user->org_id ? orabooks_get_user_role($user->id, $user->org_id) : 'viewer';
        $jwt = OraBooks_Secrets::generate_jwt([
            'user_id' => $user->id,
            'email' => $user->email,
            'org_id' => $user->org_id,
            'role' => $role,
            'is_partner' => $user->is_partner
        ]);
        
        $refresh_token = orabooks_random_string(32);
        self::store_refresh_token($user->id, $user->org_id, $refresh_token);
        
        orabooks_log_event('login_success', "2FA login successful for user $user_id", 'info', ['method' => !empty($otp) ? 'totp' : 'backup_code'], $user_id, $user->org_id);

        $login_result = orabooks_enrich_login_response([
            'token' => $jwt,
            'refresh_token' => $refresh_token,
            'user_id' => $user->id,
            'org_id' => $user->org_id,
            'role' => $role,
            'is_partner' => $user->is_partner,
            'subdomain' => $org ? $org->subdomain : '',
        ]);

        orabooks_persist_login_session($login_result);
        
        orabooks_json_success($login_result);
    }
    
    public function ajax_logout() {
        if (!orabooks_is_user_logged_in()) {
            orabooks_clear_auth_token_cookie();
            orabooks_json_success([], 'Logged out');
        }
        
        $user_id = orabooks_get_current_user_id();
        
        // Revoke all refresh tokens
        self::revoke_user_tokens($user_id);

        orabooks_clear_auth_token_cookie();
        if (function_exists('wp_logout')) {
            wp_logout();
        }
        
        orabooks_log_event('logout', "User logged out", 'info', [], $user_id, null);
        orabooks_json_success([], 'Logged out successfully');
    }
    
    // ============= OIDC (Google Auth) — SL-013 =============

    /**
     * Initiate Google OAuth flow
     * Returns the Google authorization URL for frontend redirect
     */
    public static function initiate_google_oauth($state_data = []) {
        $client_id = OraBooks_Secrets::get('google_oauth_client_id');
        if (!$client_id) {
            return new WP_Error('oauth_not_configured', 'Google OAuth is not configured. Contact the administrator.');
        }

        $state = orabooks_random_string(32);
        $state_hash = orabooks_hash_token($state);
        $encoded_state_data = self::encode_oidc_state_data(self::sanitize_oidc_state_data($state_data));

        // Generate state for CSRF protection
        set_transient('orabooks_oidc_state_' . $state_hash, 1, 600);
        if ($encoded_state_data !== '') {
            set_transient('orabooks_oidc_state_data_' . $state_hash, $encoded_state_data, 600);
        }

        $redirect_uri = home_url('/login');
        $params = http_build_query([
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect_uri,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'access_type'   => 'online',
            'prompt'        => 'select_account',
        ]);

        if ($encoded_state_data !== '') {
            $params .= '&state_data=' . rawurlencode($encoded_state_data);
        }

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
    }

    private static function sanitize_oidc_state_data($data) {
        if (!is_array($data)) {
            return [];
        }

        $user_type = ($data['user_type'] ?? 'customer') === 'partner' ? 'partner' : 'customer';
        $partner_type = $data['partner_type'] ?? 'individual';
        if (!in_array($partner_type, ['individual', 'accountant', 'agency', 'reseller', 'strategic_partner'], true)) {
            $partner_type = 'individual';
        }

        $organization_name = isset($data['organization_name']) ? sanitize_text_field($data['organization_name']) : '';

        return [
            'user_type'      => $user_type,
            'partner_code'   => isset($data['partner_code']) ? strtoupper(trim(sanitize_text_field($data['partner_code']))) : '',
            'accept_terms'   => !empty($data['accept_terms']),
            'terms_version'  => isset($data['terms_version']) ? sanitize_text_field($data['terms_version']) : '1.0',
            'partner_type'   => $partner_type,
            'organization_name' => $organization_name,
        ];
    }

    private static function encode_oidc_state_data($data) {
        if (empty($data)) {
            return '';
        }

        return rtrim(strtr(base64_encode(wp_json_encode($data)), '+/', '-_'), '=');
    }

    private static function decode_oidc_state_data($state) {
        $stored = get_transient('orabooks_oidc_state_data_' . orabooks_hash_token($state));
        if (!$stored) {
            return [];
        }

        $decoded = json_decode(base64_decode(strtr($stored, '-_', '+/')), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Handle Google OIDC callback after user authorizes
     * Exchanges authorization code for tokens, fetches user info, logs in or registers
     */
    public static function handle_google_callback($code, $state) {
        global $wpdb;

        // Verify state parameter (CSRF protection)
        $state_hash = orabooks_hash_token($state);
        $stored_state = get_transient('orabooks_oidc_state_' . $state_hash);
        if (!$stored_state) {
            orabooks_log_event('oidc_state_mismatch', 'OIDC state parameter mismatch or expired', 'warning', []);
            return new WP_Error('invalid_state', 'Invalid or expired OAuth state. Please try again.');
        }
        delete_transient('orabooks_oidc_state_' . $state_hash);
        $state_data = self::decode_oidc_state_data($state);
        $user_type = $state_data['user_type'] ?? 'customer';
        $partner_type = $state_data['partner_type'] ?? 'individual';
        $organization_name = $state_data['organization_name'] ?? '';
        $accept_terms = !empty($state_data['accept_terms']);
        $terms_version = $state_data['terms_version'] ?? '1.0';

        $client_id = OraBooks_Secrets::get('google_oauth_client_id');
        $client_secret = OraBooks_Secrets::get('google_oauth_client_secret');

        if (!$client_id || !$client_secret) {
            return new WP_Error('oauth_not_configured', 'Google OAuth is not configured.');
        }

        // Exchange authorization code for tokens
        $token_response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => home_url('/login'),
                'grant_type'    => 'authorization_code',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($token_response)) {
            orabooks_log_event('oidc_token_error', 'OIDC token exchange failed: ' . $token_response->get_error_message(), 'error', []);
            return new WP_Error('token_exchange_failed', 'Failed to authenticate with Google. Please try again.');
        }

        $token_body = json_decode(wp_remote_retrieve_body($token_response), true);
        if (empty($token_body['id_token'])) {
            orabooks_log_event('oidc_no_id_token', 'OIDC response missing id_token', 'error', ['response' => $token_body]);
            return new WP_Error('no_id_token', 'Failed to get user info from Google.');
        }

        // Decode id_token JWT to get user info (verify with Google's public keys not needed for MVP)
        $id_token_parts = explode('.', $token_body['id_token']);
        if (count($id_token_parts) !== 3) {
            return new WP_Error('invalid_id_token', 'Invalid ID token from Google.');
        }

        $userinfo = json_decode(base64_decode(strtr($id_token_parts[1], '-_', '+/')), true);
        if (!$userinfo || empty($userinfo['email'])) {
            return new WP_Error('no_email', 'Could not retrieve email from Google.');
        }

        // Verify audience (aud) matches our client_id — critical security check
        if (!isset($userinfo['aud']) || $userinfo['aud'] !== $client_id) {
            orabooks_log_event('oidc_aud_mismatch', 'OIDC id_token audience mismatch', 'critical', [
                'expected' => $client_id,
                'actual' => $userinfo['aud'] ?? 'none'
            ]);
            return new WP_Error('invalid_token', 'Invalid authentication token from Google.');
        }

        $google_email = sanitize_email($userinfo['email']);
        $google_sub = $userinfo['sub'] ?? '';
        $google_name = $userinfo['name'] ?? $google_email;

        // Check if user already exists by email
        $table_users = OraBooks_Database::table('users');
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_users} WHERE email = %s",
            $google_email
        ));

        if ($existing) {
            // User exists — log them in
            $user_id = $existing->id;

            if ($state_data && $existing->auth_provider === 'local') {
                return new WP_Error('oidc_email_conflict', 'This email is already registered with a password. Please log in with password.');
            }

            // Update auth_provider to google if was local (link account)
            if ($existing->auth_provider === 'local') {
                $wpdb->update(
                    $table_users,
                    ['auth_provider' => 'google', 'is_email_verified' => 1],
                    ['id' => $user_id],
                    ['%s', '%d'],
                    ['%d']
                );
            }

            // Check account active
            if (!$existing->is_active) {
                return new WP_Error('account_disabled', 'Your account has been disabled.');
            }

            // Ensure email is verified
            if (!$existing->is_email_verified) {
                $wpdb->update(
                    $table_users,
                    ['is_email_verified' => 1],
                    ['id' => $user_id],
                    ['%d'],
                    ['%d']
                );
            }

            if ($state_data) {
                $_SESSION['orabooks_partner_type'] = $partner_type;
                $_SESSION['orabooks_partner_org_name'] = $organization_name;
            }

            // Check if 2FA is required for this existing user
            if ($existing->is_2fa_enabled) {
                $temp_token = OraBooks_Secrets::generate_jwt([
                    'user_id' => $existing->id,
                    'purpose' => '2fa_challenge',
                    'exp' => time() + 300 // 5 min
                ]);
                return [
                    'requires_2fa' => true,
                    'temp_token' => $temp_token,
                    'user_id' => $existing->id
                ];
            }

            if ($existing->is_partner && !$existing->org_id) {
                return self::handle_partner_first_login($existing);
            }

            if (!$existing->is_partner && !$existing->org_id) {
                $jwt = OraBooks_Secrets::generate_jwt([
                    'user_id' => $existing->id,
                    'email' => $existing->email,
                    'is_partner' => 0,
                    'needs_tier_selection' => true,
                    'org_id' => null
                ]);

                return [
                    'needs_tier_selection' => true,
                    'token' => $jwt,
                    'user_id' => $existing->id,
                    'message' => 'Please select a tier to continue'
                ];
            }
        } else {
            // User doesn't exist — create account via Google
            $is_partner = ($user_type === 'partner') ? 1 : 0;

            if ($is_partner && !$accept_terms) {
                return new WP_Error('terms_required', 'Partner terms must be accepted');
            }

            if ($is_partner && in_array($partner_type, ['agency', 'reseller', 'strategic_partner'], true) && empty($organization_name)) {
                return new WP_Error('org_name_required', 'Organization name is required for this partner type');
            }

            $wpdb->insert(
                $table_users,
                [
                    'email' => $google_email,
                    'password_hash' => '',
                    'is_active' => 1,
                    'is_email_verified' => 1,
                    'auth_provider' => 'google',
                    'org_id' => null,
                    'is_partner' => $is_partner
                ],
                ['%s', '%s', '%d', '%d', '%s', null, '%d']
            );

            $user_id = $wpdb->insert_id;
            if (!$user_id) {
                return new WP_Error('creation_failed', 'Failed to create account from Google profile.');
            }

            $created_user = (object) [
                'id' => $user_id,
                'email' => $google_email,
                'password_hash' => '',
                'is_active' => 1,
                'is_email_verified' => 1,
                'is_2fa_enabled' => 0,
                'auth_provider' => 'google',
                'org_id' => null,
                'is_partner' => $is_partner,
            ];

            if ($is_partner) {
                $_SESSION['orabooks_partner_type'] = $partner_type;
                $_SESSION['orabooks_partner_org_name'] = $organization_name;

                $table_terms = OraBooks_Database::table('partner_terms_acceptance');
                $wpdb->insert(
                    $table_terms,
                    [
                        'user_id' => $user_id,
                        'terms_version' => $terms_version,
                        'ip_address' => orabooks_get_client_ip(),
                        'user_agent' => orabooks_get_user_agent()
                    ],
                    ['%d', '%s', '%s', '%s']
                );
            } else {
                $partner_code = $state_data['partner_code'] ?? '';
                if (!empty($partner_code)) {
                    $attribution_result = self::process_attribution($user_id, $partner_code, $google_email);
                    if (is_wp_error($attribution_result)) {
                        return $attribution_result;
                    }
                }
            }

            orabooks_log_event('user_registered_oidc', "User registered via Google: $google_email", 'info', [
                'auth_provider' => 'google',
                'user_type' => $user_type
            ], $user_id, null);

            if ($is_partner) {
                return self::handle_partner_first_login($created_user);
            }
        }

        // Generate JWT and refresh token
        $org = null;
        $org_id = null;
        if ($existing && $existing->org_id) {
            $org = OraBooks_Organization::get($existing->org_id);
            $org_id = $existing->org_id;
        }

        $role = $org_id ? orabooks_get_user_role($user_id, $org_id) : 'viewer';
        $is_partner = $existing ? (int) $existing->is_partner : (($user_type === 'partner') ? 1 : 0);
        $jwt = OraBooks_Secrets::generate_jwt([
            'user_id' => $user_id,
            'email' => $google_email,
            'org_id' => $org_id,
            'role' => $role,
            'subdomain' => $org ? $org->subdomain : '',
            'is_partner' => $is_partner
        ]);

        $refresh_token = orabooks_random_string(32);
        self::store_refresh_token($user_id, $org_id, $refresh_token);

        orabooks_log_event('login_success_oidc', "User logged in via Google: $google_email", 'info', [], $user_id, $org_id);

        return [
            'token' => $jwt,
            'refresh_token' => $refresh_token,
            'user_id' => $user_id,
            'org_id' => $org_id,
            'role' => $role,
            'subdomain' => $org ? $org->subdomain : '',
            'is_partner' => $is_partner,
            'is_new' => !$existing,
            'needs_tier_selection' => (!$existing && $user_type === 'customer') || ($existing && !$existing->is_partner && !$existing->org_id)
        ];
    }

    /**
     * AJAX: Initiate Google OAuth — returns the Google auth URL
     */
    public function ajax_oidc_initiate() {
        $state_data = [];
        if (!empty($_POST['state_data'])) {
            $decoded = json_decode(base64_decode(strtr($_POST['state_data'], '-_', '+/')), true);
            if (is_array($decoded)) {
                $state_data = $decoded;
            }
        }

        $url = self::initiate_google_oauth($state_data);

        if (is_wp_error($url)) {
            orabooks_json_error($url->get_error_message(), 400);
        }

        orabooks_json_success(['auth_url' => $url]);
    }

    /**
     * AJAX: Handle Google OAuth callback (exchange code for token, login)
     */
    public function ajax_oidc_callback() {
        $code = $_POST['code'] ?? '';
        $state = $_POST['state'] ?? '';

        if (empty($code) || empty($state)) {
            orabooks_json_error('Missing authorization code or state', 400);
        }

        $result = self::handle_google_callback($code, $state);

        if (is_wp_error($result)) {
            $status_code = ($result->get_error_code() === 'oidc_email_conflict') ? 409 : 401;
            orabooks_json_error($result->get_error_message(), $status_code);
        }

        orabooks_persist_login_session($result);

        orabooks_json_success($result, 'Google login successful');
    }

    /**
     * 501 placeholder for reserved MVP endpoints
     */
    public function ajax_not_implemented() {
        wp_send_json(['error' => true, 'message' => 'This endpoint is not yet implemented (MVP placeholder).'], 501);
    }

    /**
     * SL-013 / SL-004: block partner subdomains from accounting frontend pages at ingress.
     */
    public function enforce_partner_accounting_isolation() {
        if (!function_exists('is_singular') || !is_singular('page')) {
            return;
        }

        $subdomain = self::detect_subdomain_from_host($_SERVER['HTTP_HOST'] ?? '');
        if ($subdomain === '') {
            return;
        }

        $org = OraBooks_Organization::get_by_subdomain($subdomain);
        if (!$org || $org->organization_type !== 'partner') {
            return;
        }

        $post = get_queried_object();
        if (!$post || empty($post->post_name)) {
            return;
        }

        if (!in_array($post->post_name, orabooks_get_accounting_page_slugs(), true)) {
            return;
        }

        orabooks_log_event('accounting_isolation_blocked', "Partner subdomain blocked from accounting page: {$post->post_name}", 'warning', [
            'subdomain' => $subdomain,
            'page' => $post->post_name,
        ], orabooks_get_current_user_id(), (int) $org->id);

        status_header(403);
        wp_die(
            esc_html__('Partner organizations cannot access accounting features.', 'orabooks'),
            esc_html__('Forbidden', 'orabooks'),
            ['response' => 403]
        );
    }

    /**
     * SL-013: block partner org REST accounting routes at ingress.
     */
    public function enforce_partner_accounting_isolation_rest($result, $server, $request) {
        if (!($request instanceof WP_REST_Request)) {
            return $result;
        }

        $route = (string) $request->get_route();
        if (strpos($route, '/orabooks/v1/') === false) {
            return $result;
        }

        $accounting_prefixes = [
            '/orabooks/v1/customers',
            '/orabooks/v1/invoices',
            '/orabooks/v1/vendors',
            '/orabooks/v1/inventory',
            '/orabooks/v1/expenses',
            '/orabooks/v1/journals',
            '/orabooks/v1/reports',
            '/orabooks/v1/coa',
        ];

        $is_accounting = false;
        foreach ($accounting_prefixes as $prefix) {
            if (strpos($route, $prefix) === 0) {
                $is_accounting = true;
                break;
            }
        }
        if (!$is_accounting) {
            return $result;
        }

        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            return $result;
        }

        global $wpdb;
        $table_users = OraBooks_Database::table('users');
        $org_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT org_id FROM {$table_users} WHERE id = %d",
            $user_id
        ));

        $check = self::require_customer_org($user_id, $org_id);
        if (is_wp_error($check)) {
            return new WP_Error($check->get_error_code(), $check->get_error_message(), ['status' => 403]);
        }

        return $result;
    }

    /**
     * requireCustomerOrg middleware — blocks partner orgs and inactive orgs from accounting APIs
     *
     * Checks:
     * - Org must exist and have a valid org_id
     * - Partner orgs are always blocked from accounting endpoints
     * - Suspended/inactive orgs are blocked
     *
     * Usage: $check = OraBooks_Auth::require_customer_org($user_id, $org_id);
     *
     * @param int      $user_id Current user ID
     * @param int|null $org_id  Organization ID to check
     * @return true|WP_Error    True if allowed, WP_Error with descriptive code if blocked
     */
    public static function require_customer_org($user_id, $org_id) {
        if (!$org_id) {
            return new WP_Error('no_org', 'No organization found.');
        }

        $org = OraBooks_Organization::get($org_id);
        if (!$org) {
            return new WP_Error('org_not_found', 'Organization not found.');
        }

        // Block partner orgs from accounting features
        if ($org->organization_type === 'partner') {
            orabooks_log_event('accounting_isolation_blocked', "Partner org {$org_id} blocked from accounting endpoint", 'warning', [
                'user_id' => $user_id,
                'org_id' => $org_id
            ], $user_id, $org_id);

            return new WP_Error('accounting_isolation', 'Partner organizations cannot access accounting features.');
        }

        // Block inactive/suspended orgs
        if ($org->status !== 'active') {
            orabooks_log_event('accounting_isolation_blocked', "Non-active org {$org_id} blocked from accounting endpoint (status: {$org->status})", 'warning', [
                'user_id' => $user_id,
                'org_id' => $org_id,
                'status' => $org->status
            ], $user_id, $org_id);

            return new WP_Error('org_inactive', 'Your organization is not active. Please contact support.');
        }

        return true;
    }

    /**
     * Detect subdomain from the HTTP host header.
     *
     * Extracts the subdomain from the request host (e.g., "mycompany" from "mycompany.orabooks.app").
     * Returns empty string if no subdomain is detected or if the host is the root domain.
     *
     * @return string The detected subdomain, lowercased and trimmed
     */
    public static function detect_subdomain_from_host() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (empty($host)) {
            return '';
        }

        $host = strtolower(trim($host));

        // Check if this is a multi-level host (contains a dot before the main domain)
        $parts = explode('.', $host);

        // If there are at least 3 parts (e.g., "mycompany.orabooks.app"), the first is the subdomain
        // If there are exactly 2 parts, it's a root domain (e.g., "localhost" or "example.com")
        // Ignore common patterns: www, localhost, IP addresses
        if (count($parts) >= 3) {
            $possible_subdomain = $parts[0];
            if ($possible_subdomain !== 'www' && $possible_subdomain !== 'mail' && $possible_subdomain !== 'admin') {
                return $possible_subdomain;
            }
        }

        return '';
    }

    public function ajax_select_tier() {
        $tier = $_POST['tier'] ?? '';
        $subdomain = strtolower(trim($_POST['subdomain'] ?? ''));
        $user_id = orabooks_get_current_user_id();
        
        if (!$user_id) {
            orabooks_json_error('Please log in before selecting a plan.', 401);
        }
        
        if (!in_array($tier, ['free', 'premium', 'enterprise'])) {
            orabooks_json_error('Invalid tier', 400);
        }
        
        $validation = orabooks_validate_subdomain($subdomain);
        if ($validation !== true) {
            orabooks_json_error($validation, 400);
        }
        
        global $wpdb;
        $table_users = OraBooks_Database::table('users');
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_users} WHERE id = %d", $user_id));
        
        if (!$user || $user->is_partner) {
            orabooks_json_error('Only customers can select tiers', 400);
        }
        
        $org_result = OraBooks_Organization::create([
            'owner_id' => $user_id,
            'organization_type' => 'customer',
            'tier' => $tier,
            'subdomain' => $subdomain,
            'name' => $subdomain
        ]);
        
        if (is_wp_error($org_result)) {
            orabooks_json_error($org_result->get_error_message(), 400);
        }
        
        // Update attribution org_id if pending or already verified before tier selection
        $table_attributions = OraBooks_Database::table('partner_attributions');
        $wpdb->update(
            $table_attributions,
            ['org_id' => $org_result['org_id']],
            ['customer_user_id' => $user_id, 'status' => 'pending'],
            ['%d'],
            ['%d', '%s']
        );
        $wpdb->update(
            $table_attributions,
            ['org_id' => $org_result['org_id']],
            ['customer_user_id' => $user_id, 'status' => 'verified'],
            ['%d'],
            ['%d', '%s']
        );
        
        // Fire event for SL-068 Commission Engine
        $pending_attr = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_attributions} WHERE customer_user_id = %d AND status = 'verified' ORDER BY verified_at DESC LIMIT 1",
            $user_id
        ));
        if ($pending_attr) {
            do_action('orabooks_partner_attribution_verified', $pending_attr->id, $pending_attr);
        }
        
        $role = 'owner';
        $jwt = OraBooks_Secrets::generate_jwt([
            'user_id' => $user->id,
            'email' => $user->email,
            'org_id' => $org_result['org_id'],
            'role' => $role,
            'subdomain' => $org_result['subdomain'],
            'is_partner' => 0
        ]);
        
        $refresh_token = orabooks_random_string(32);
        self::store_refresh_token($user->id, $org_result['org_id'], $refresh_token);
        
        $tier_result = [
            'token' => $jwt,
            'refresh_token' => $refresh_token,
            'org_id' => $org_result['org_id'],
            'subdomain' => $org_result['subdomain'],
            'user_id' => $user->id,
            'redirect_to' => '/dashboard/',
        ];

        orabooks_persist_login_session($tier_result);

        orabooks_json_success($tier_result, 'Organization created successfully');
    }
}