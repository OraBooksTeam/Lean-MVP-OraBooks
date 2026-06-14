<?php
/**
 * SL-013 – Enhanced Registration with Partner Support
 *
 * Extends WordPress registration to support:
 * - Customer vs Partner user type selection
 * - Partner type selection (individual, accountant, agency, reseller, strategic_partner)
 * - Organization name for org partners
 * - Partner code attribution for customers
 * - Email verification workflow
 * - Auto-creation of partner org on first login
 *
 * Build Order: SL-004 → SL-013 → SL-003 → SL-017 → SL-139 → SL-068
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Registration {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Valid user types
     */
    const USER_TYPES = array('customer', 'partner');

    /**
     * Valid partner types
     */
    const PARTNER_TYPES = array('individual', 'accountant', 'agency', 'reseller', 'strategic_partner');

    /**
     * Partner types that require organization name
     */
    const ORG_PARTNER_TYPES = array('agency', 'reseller', 'strategic_partner');

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
        // Hook into WordPress registration
        add_action('user_register', array($this, 'handle_user_register'), 10, 1);
        add_action('registration_errors', array($this, 'validate_registration'), 10, 3);
        add_filter('registration_redirect', array($this, 'redirect_after_registration'), 10, 1);
        
        // Handle email verification
        add_action('init', array($this, 'handle_email_verification'));
        
        // SL-013 §5.3: Resend verification email AJAX endpoint
        add_action('wp_ajax_orabooks_resend_verification', array($this, 'ajax_resend_verification'));
        add_action('wp_ajax_nopriv_orabooks_resend_verification', array($this, 'ajax_resend_verification'));
        
        // Store temporary partner data during registration
        add_action('init', array($this, 'start_session'));
    }

    /**
     * Start session for storing temporary registration data
     */
    public function start_session() {
        if (!session_id()) {
            session_start();
        }
    }

    /**
     * SL-013: Validate registration form data
     * Rate limit: 5 attempts per hour per IP (SL-013 spec)
     * Password policy: 8+ chars, upper, lower, number, special (SL-013 spec)
     */
    public function validate_registration($errors, $sanitized_user_login, $user_email) {
        // Check if this is our custom registration
        if (!isset($_POST['orabooks_user_type'])) {
            return $errors;
        }

        // ── SL-013: Registration Rate Limit (5/hour per IP) ──────────────
        if (class_exists('OraBooks_Rate_Limiter')) {
            $ip = OraBooks_Rate_Limiter::get_client_ip();
            $rate_check = OraBooks_Rate_Limiter::get_instance()->check_and_increment(
                'register',
                $ip,
                5,       // limit
                3600     // window: 1 hour
            );
            if (is_wp_error($rate_check)) {
                do_action('orabooks_security_event', 'registration_rate_limited', array(
                    'ip_address' => $ip,
                    'user_email' => $user_email,
                ));
                $errors->add('rate_limit_exceeded', __('Too many registration attempts from this IP address. Please try again later.', 'orabooks'));
                return $errors;
            }
        }

        $user_type = sanitize_text_field($_POST['orabooks_user_type']);

        // Validate user type
        if (!in_array($user_type, self::USER_TYPES, true)) {
            $errors->add('invalid_user_type', __('Invalid user type.', 'orabooks'));
        }

        // ── SL-013: Password Policy Enforcement ─────────────────────────
        if (isset($_POST['password'])) {
            $password = $_POST['password'];
            $policy_errors = self::validate_password_policy($password);
            foreach ($policy_errors as $policy_error) {
                $errors->add('password_policy', $policy_error);
            }
        }

        // Partner-specific validation
        if ($user_type === 'partner') {
            // Partner type is required
            if (empty($_POST['orabooks_partner_type'])) {
                $errors->add('partner_type_required', __('Partner type is required.', 'orabooks'));
            } else {
                $partner_type = sanitize_text_field($_POST['orabooks_partner_type']);
                if (!in_array($partner_type, self::PARTNER_TYPES, true)) {
                    $errors->add('invalid_partner_type', __('Invalid partner type.', 'orabooks'));
                }

                // Organization name required for org partners
                if (in_array($partner_type, self::ORG_PARTNER_TYPES, true)) {
                    if (empty($_POST['orabooks_organization_name'])) {
                        $errors->add('org_name_required', __('Organization name is required for this partner type.', 'orabooks'));
                    }
                }

                // Terms acceptance is required
                if (empty($_POST['orabooks_accept_terms'])) {
                    $errors->add('terms_required', __('You must accept the partner terms.', 'orabooks'));
                }
            }

            // Store partner data in session for first login
            $_SESSION['orabooks_pending_partner'] = array(
                'partner_type' => $partner_type,
                'organization_name' => isset($_POST['orabooks_organization_name']) ? sanitize_text_field($_POST['orabooks_organization_name']) : null,
                'terms_version' => isset($_POST['orabooks_terms_version']) ? sanitize_text_field($_POST['orabooks_terms_version']) : '1.0',
            );
        }

        // Customer-specific validation
        if ($user_type === 'customer') {
            // Optional partner code
            if (!empty($_POST['orabooks_partner_code'])) {
                $partner_code = sanitize_text_field($_POST['orabooks_partner_code']);
                
                // Validate partner code format
                if (!preg_match('/^PARTNER-[A-Z0-9]{8}$/', $partner_code)) {
                    $errors->add('invalid_partner_code_format', __('Invalid partner code format.', 'orabooks'));
                }

                // Store partner code for after registration
                $_SESSION['orabooks_pending_partner_code'] = $partner_code;
            }
        }

        return $errors;
    }

    /**
     * SL-013: Validate password policy.
     * Requirements: minimum 8 characters, at least one uppercase, one lowercase,
     * one digit, and one special character.
     *
     * @param string $password The password to validate.
     * @return array List of error messages (empty if valid).
     */
    public static function validate_password_policy($password) {
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

    /**
     * SL-013: Handle user registration
     */
    public function handle_user_register($user_id) {
        if (!isset($_POST['orabooks_user_type'])) {
            return;
        }

        $user_type = sanitize_text_field($_POST['orabooks_user_type']);

        // Add custom fields to user meta (temporary until columns are added)
        update_user_meta($user_id, 'is_partner', ($user_type === 'partner') ? 1 : 0);
        update_user_meta($user_id, 'is_email_verified', 0);
        update_user_meta($user_id, 'is_2fa_enabled', 0);
        update_user_meta($user_id, 'auth_provider', 'local');

        // Generate email verification token
        $verification_token = wp_generate_password(32, false);
        $verification_expires = date('Y-m-d H:i:s', time() + DAY_IN_SECONDS);

        update_user_meta($user_id, 'email_verification_token', $verification_token);
        update_user_meta($user_id, 'email_verification_expires_at', $verification_expires);

        // Partner-specific handling
        if ($user_type === 'partner' && isset($_SESSION['orabooks_pending_partner'])) {
            $partner_data = $_SESSION['orabooks_pending_partner'];
            update_user_meta($user_id, 'partner_type', $partner_data['partner_type']);
            update_user_meta($user_id, 'organization_name', $partner_data['organization_name']);
            
            // Record terms acceptance
            if (class_exists('OraBooks_Partners')) {
                $partners = OraBooks_Partners::get_instance();
                $partners->record_terms_acceptance($user_id, $partner_data['terms_version']);
            }

            // Clear session data
            unset($_SESSION['orabooks_pending_partner']);
        }

        // Customer partner code handling
        if ($user_type === 'customer' && isset($_SESSION['orabooks_pending_partner_code'])) {
            update_user_meta($user_id, 'pending_partner_code', $_SESSION['orabooks_pending_partner_code']);
            unset($_SESSION['orabooks_pending_partner_code']);
        }

        // Send verification email
        $this->send_verification_email($user_id, $verification_token);

        // Audit event
        do_action('orabooks_security_event', 'user_registered', array(
            'user_id' => $user_id,
            'user_type' => $user_type,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
        ));
    }

    /**
     * SL-013: Send email verification
     */
    private function send_verification_email($user_id, $token) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $verification_url = add_query_arg(array(
            'orabooks_verify_email' => '1',
            'token' => $token,
            'user_id' => $user_id,
        ), home_url());

        $subject = __('Verify your email address', 'orabooks');
        $message = sprintf(
            __('Hello %s,

Please verify your email address by clicking the link below:

%s

This link will expire in 24 hours.

If you did not create an account, please ignore this email.', 'orabooks'),
            $user->display_name,
            $verification_url
        );

        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * SL-013: Handle email verification
     */
    public function handle_email_verification() {
        if (!isset($_GET['orabooks_verify_email']) || $_GET['orabooks_verify_email'] !== '1') {
            return;
        }

        if (!isset($_GET['token']) || !isset($_GET['user_id'])) {
            wp_die(__('Invalid verification link.', 'orabooks'));
        }

        $token = sanitize_text_field($_GET['token']);
        $user_id = intval($_GET['user_id']);

        $stored_token = get_user_meta($user_id, 'email_verification_token', true);
        $expires_at = get_user_meta($user_id, 'email_verification_expires_at', true);

        if ($stored_token !== $token) {
            wp_die(__('Invalid verification token.', 'orabooks'));
        }

        if (strtotime($expires_at) < time()) {
            wp_die(__('Verification link has expired.', 'orabooks'));
        }

        // Mark email as verified
        update_user_meta($user_id, 'is_email_verified', 1);
        delete_user_meta($user_id, 'email_verification_token');
        delete_user_meta($user_id, 'email_verification_expires_at');

        // Handle partner attribution if customer using the full state machine
        $is_partner = get_user_meta($user_id, 'is_partner', true);
        if (!$is_partner) {
            $pending_partner_code = get_user_meta($user_id, 'pending_partner_code', true);
            if (!empty($pending_partner_code) && class_exists('OraBooks_Partners')) {
                $partners = OraBooks_Partners::get_instance();
                
                // SL-013 §5.2: Validate partner code with fraud checks
                $customer_email = get_userdata($user_id)->user_email;
                $validation = $partners->validate_partner_code($pending_partner_code, $user_id, $customer_email);
                
                if (!is_wp_error($validation)) {
                    // Create attribution (status='pending')
                    $attribution_result = $partners->create_attribution(
                        $validation['partner_user_id'],
                        $user_id,
                        $customer_email,
                        $pending_partner_code
                    );

                    if (!is_wp_error($attribution_result)) {
                        // SL-013 §5.2: Verify attribution (pending → verified)
                        // This also publishes partner_attribution_verified event
                        // and updates partner_codes.last_attribution_at + resets reminder flags
                        $partners->verify_attribution($user_id);
                    }
                }
                
                delete_user_meta($user_id, 'pending_partner_code');
            }
        }

        // Audit event
        do_action('orabooks_security_event', 'email_verified', array(
            'user_id' => $user_id,
        ));

        // Redirect to login with success message
        wp_redirect(add_query_arg('verified', '1', wp_login_url()));
        exit;
    }

    // ================================================================
    // SL-013 §5.3: Resend Verification Email
    // ================================================================

    /**
     * AJAX: Resend verification email with rate limiting.
     * Rate limit: 3 times per hour per email (SL-013 §5.3).
     * Revokes old token, generates new one (24h expiry), sends email.
     *
     * Request: { nonce, user_id? (or email) }
     * Response: { message: string }
     */
    public function ajax_resend_verification() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'orabooks_resend_verification')) {
            wp_send_json_error(__('Security check failed.', 'orabooks'), 403);
        }

        // Determine target user: from logged-in user or explicit user_id/email
        $user_id = 0;
        $email = '';

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $user = get_userdata($user_id);
            $email = $user ? $user->user_email : '';
        } elseif (!empty($_POST['user_id'])) {
            $user_id = intval($_POST['user_id']);
            $user = get_userdata($user_id);
            if (!$user) {
                wp_send_json_error(__('User not found.', 'orabooks'), 404);
            }
            $email = $user->user_email;
        } elseif (!empty($_POST['email'])) {
            $email = sanitize_email($_POST['email']);
            $user = get_user_by('email', $email);
            if (!$user) {
                // Don't reveal whether the email exists (generic message)
                wp_send_json_success(array(
                    'message' => __('If an account exists with this email, a verification link has been sent.', 'orabooks'),
                ));
            }
            $user_id = $user->ID;
        } else {
            wp_send_json_error(__('User ID or email is required.', 'orabooks'), 400);
        }

        // Rate limit: 3 per hour per email (SL-013 §5.3)
        if (class_exists('OraBooks_Rate_Limiter')) {
            $rate_key = 'resend_verification_' . $email;
            $rate_check = OraBooks_Rate_Limiter::get_instance()->check_and_increment(
                $rate_key,
                $email,
                3,       // limit: 3 requests
                3600     // window: 1 hour
            );

            if (is_wp_error($rate_check)) {
                do_action('orabooks_security_event', 'verification_resend_rate_limited', array(
                    'user_id' => $user_id,
                    'email' => $email,
                ));
                wp_send_json_error(__('Too many requests. Please try again later.', 'orabooks'), 429);
            }
        }

        // Check if email is already verified
        $is_verified = get_user_meta($user_id, 'is_email_verified', true);
        if ($is_verified) {
            wp_send_json_error(__('Email is already verified.', 'orabooks'), 400);
        }

        // Revoke old token and generate new one
        $verification_token = wp_generate_password(32, false);
        $verification_expires = date('Y-m-d H:i:s', time() + DAY_IN_SECONDS);

        update_user_meta($user_id, 'email_verification_token', $verification_token);
        update_user_meta($user_id, 'email_verification_expires_at', $verification_expires);

        // Send verification email
        $this->send_verification_email($user_id, $verification_token);

        // Audit event
        do_action('orabooks_security_event', 'verification_resent', array(
            'user_id' => $user_id,
            'email' => $email,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
        ));

        wp_send_json_success(array(
            'message' => __('Verification email resent. Please check your inbox.', 'orabooks'),
        ));
    }

    /**
     * SL-013: Redirect after registration
     */
    public function redirect_after_registration($redirect) {
        if (isset($_POST['orabooks_user_type'])) {
            // Redirect to login page with verification message
            return add_query_arg('checkemail', 'confirm', wp_login_url());
        }
        return $redirect;
    }

    /**
     * SL-013: Handle first login for partners - auto-create partner org
     */
    /**
     * SL-013 §5.7: Redirect partners to onboarding page on first login.
     *
     * @param string    $redirect_to The redirect destination URL.
     * @param string    $request     The requested redirect destination URL.
     * @param WP_User|WP_Error $user WP_User object on success, WP_Error on failure.
     * @return string Modified redirect URL.
     */
    public static function partner_login_redirect($redirect_to, $request, $user) {
        if (is_wp_error($user) || !$user) {
            return $redirect_to;
        }

        $user_id = $user->ID;
        $transient_key = 'orabooks_partner_onboarding_' . $user_id;
        $should_redirect = get_transient($transient_key);

        if ($should_redirect) {
            delete_transient($transient_key);
            return home_url('/partner/onboarding/');
        }

        return $redirect_to;
    }

    /**
     * SL-013 §5.7: Handle first login for partners - auto-create partner org
     *
     * Per SL-013 spec:
     * - Disables any previous active partner code for this user
     * - Creates partner org with status='pending_setup'
     * - Generates new partner code with partner_type and organization_name
     * - Issues JWT with org_id and subdomain claim = partner-{user_id}
     * - Response includes redirect_to: '/partner/onboarding'
     * - Uses provided organization_name for org name (or "Partner {user_id}" for individuals)
     */
    public static function handle_partner_first_login($user_id) {
        $is_partner = get_user_meta($user_id, 'is_partner', true);
        if (!$is_partner) {
            return;
        }

        $org_id = get_user_meta($user_id, 'org_id', true);
        if (!empty($org_id)) {
            return; // Org already exists
        }

        // Get partner data from meta
        $partner_type = get_user_meta($user_id, 'partner_type', true);
        $organization_name = get_user_meta($user_id, 'organization_name', true);

        if (empty($partner_type)) {
            $partner_type = 'individual';
        }

        // Create partner org per SL-013 §5.7 SQL spec
        if (class_exists('OraBooks_Organizations')) {
            $orgs = OraBooks_Organizations::get_instance();
            
            $subdomain = 'partner-' . $user_id;
            $org_name = !empty($organization_name) ? $organization_name : sprintf('Partner %d', $user_id);
            
            $result = $orgs->create_organization(array(
                'tier' => 'partner',
                'subdomain' => $subdomain,
                'owner_id' => $user_id,
                'organization_type' => 'partner',
                'organization_name' => $org_name,
            ));

            if (!is_wp_error($result)) {
                $org_id = $result['org_id'];
                update_user_meta($user_id, 'org_id', $org_id);

                // Add user to org as owner
                if (class_exists('OraBooks_Users_Teams')) {
                    $teams = OraBooks_Users_Teams::get_instance();
                    $teams->add_owner($user_id, $org_id);
                }

                // Generate partner code per SL-013 §5.7:
                // 1. Disables any previous active code for this user
                // 2. Creates new code with pending_review status
                // 3. Uses partner_type and organization_name from registration
                if (class_exists('OraBooks_Partners')) {
                    $partners = OraBooks_Partners::get_instance();
                    $partners->create_partner_code($org_id, $user_id, $partner_type, $organization_name);
                }

                // Audit events: partner_org_created, partner_code_generated, partner_onboarding_started
                do_action('orabooks_security_event', 'partner_org_created', array(
                    'user_id' => $user_id,
                    'org_id' => $org_id,
                ));
                do_action('orabooks_security_event', 'partner_code_generated', array(
                    'user_id' => $user_id,
                    'org_id' => $org_id,
                    'partner_type' => $partner_type,
                ));
                do_action('orabooks_security_event', 'partner_onboarding_started', array(
                    'user_id' => $user_id,
                    'org_id' => $org_id,
                ));

                // Set transient for onboarding redirect (SL-013 §5.7: response includes redirect_to: '/partner/onboarding')
                set_transient('orabooks_partner_onboarding_' . $user_id, true, 600);
            }
        }
    }
}

// Initialize the registration system
OraBooks_Registration::get_instance();

// Hook partner org creation to first login
add_action('wp_login', function($user_login, $user) {
    OraBooks_Registration::handle_partner_first_login($user->ID);
}, 10, 2);

// Redirect partners to onboarding page on first login (SL-013 §5.7)
add_filter('login_redirect', array('OraBooks_Registration', 'partner_login_redirect'), 10, 3);
