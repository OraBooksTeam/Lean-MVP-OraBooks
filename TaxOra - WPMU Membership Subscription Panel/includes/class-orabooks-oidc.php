<?php
/**
 * SL-013 – OIDC / Google Login
 *
 * Implements OpenID Connect authentication via Google OAuth 2.0.
 * Features:
 *   - "Sign in with Google" button for login & signup forms
 *   - OAuth2 authorization code flow with state parameter
 *   - State parameter carries: user_type, partner_code, accept_terms,
 *     terms_version, partner_type, organization_name
 *   - Local account conflict detection (409 Conflict)
 *   - New users created with is_email_verified = true
 *   - Partner: terms acceptance record; partner_type & organization_name
 *     stored for auto-org creation on first login
 *   - No org → redirect to tier selection (customer) or auto-create
 *     partner org (if partner)
 *
 * Build Order: SL-004 → SL-013 → SL-003 → SL-017 → SL-139 → SL-068
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_OIDC {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Option keys for storing OIDC configuration
     */
    const OPTION_CLIENT_ID     = 'orabooks_oidc_client_id';
    const OPTION_CLIENT_SECRET = 'orabooks_oidc_client_secret_enc'; // encrypted at rest (SL-008)
    const OPTION_ENABLED       = 'orabooks_oidc_enabled';

    /**
     * Google OAuth endpoints
     */
    const GOOGLE_AUTH_URL    = 'https://accounts.google.com/o/oauth2/v2/auth';
    const GOOGLE_TOKEN_URL   = 'https://oauth2.googleapis.com/token';
    const GOOGLE_USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    /**
     * State transient expiry (10 minutes)
     */
    const STATE_EXPIRY = 600;

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
        // AJAX endpoint to initiate Google login
        add_action('wp_ajax_nopriv_orabooks_oidc_redirect', array($this, 'ajax_redirect_to_google'));
        add_action('wp_ajax_orabooks_oidc_redirect',        array($this, 'ajax_redirect_to_google'));

        // Register callback rewrite rule
        add_action('init', array($this, 'register_callback_rewrite'));
        add_filter('query_vars', array($this, 'add_callback_query_var'));
        add_action('template_redirect', array($this, 'handle_callback'));

        // Admin settings
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_settings_menu'), 20);

        // Show button on login form via hooks
        add_action('lwws_after_login_form_end', array($this, 'render_google_button'));
    }

    // ================================================================
    // SETTINGS & CONFIGURATION
    // ================================================================

    /**
     * Register OIDC settings in WordPress options.
     */
    public function register_settings() {
        register_setting('orabooks_oidc_settings', self::OPTION_CLIENT_ID, 'sanitize_text_field');
        register_setting('orabooks_oidc_settings', self::OPTION_ENABLED, 'intval');
        // Client secret is encrypted on save
        register_setting('orabooks_oidc_settings', self::OPTION_CLIENT_SECRET, array($this, 'sanitize_encrypt_secret'));
    }

    /**
     * Sanitize and encrypt the client secret before storing (SL-008 compliance).
     */
    public function sanitize_encrypt_secret($value) {
        if (empty($value)) {
            return '';
        }
        // Encrypt using the same SL-008 pattern as class-security.php
        return self::encrypt_secret($value);
    }

    /**
     * Add OIDC settings submenu page under OraBooks settings.
     */
    public function add_settings_menu() {
        add_submenu_page(
            'orabooks-settings',
            __('Google OIDC Login', 'orabooks'),
            __('Google OIDC', 'orabooks'),
            'manage_options',
            'orabooks-oidc-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Render the OIDC settings page.
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Access denied.', 'orabooks'));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Google OIDC Login Settings', 'orabooks'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('orabooks_oidc_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Google Login', 'orabooks'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_ENABLED); ?>" value="1"
                                    <?php checked(get_option(self::OPTION_ENABLED, 0), 1); ?>>
                                <?php esc_html_e('Allow users to sign in with Google', 'orabooks'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Google Client ID', 'orabooks'); ?></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr(self::OPTION_CLIENT_ID); ?>"
                                   value="<?php echo esc_attr(get_option(self::OPTION_CLIENT_ID, '')); ?>"
                                   class="regular-text" placeholder="1234567890-xxxxx.apps.googleusercontent.com">
                            <p class="description">
                                <?php esc_html_e('Create a Google OAuth 2.0 client ID at the', 'orabooks'); ?>
                                <a href="https://console.cloud.google.com/apis/credentials" target="_blank">
                                    <?php esc_html_e('Google Cloud Console', 'orabooks'); ?>
                                </a>.
                                <?php esc_html_e('Add the callback URL below as an authorized redirect URI.', 'orabooks'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Google Client Secret', 'orabooks'); ?></th>
                        <td>
                            <input type="password" name="<?php echo esc_attr(self::OPTION_CLIENT_SECRET); ?>"
                                   value="<?php echo esc_attr(get_option(self::OPTION_CLIENT_SECRET, '')); ?>"
                                   class="regular-text" placeholder="GOCSPX-xxxxxxxxxxxx">
                            <p class="description">
                                <?php esc_html_e('Encrypted at rest (SL-008 compliance). Leave empty to keep existing value.', 'orabooks'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Callback URL', 'orabooks'); ?></th>
                        <td>
                            <code><?php echo esc_url($this->get_callback_url()); ?></code>
                            <p class="description">
                                <?php esc_html_e('Add this URL as an Authorized Redirect URI in your Google Cloud Console OAuth 2.0 client.', 'orabooks'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Get the full callback URL for Google redirect.
     *
     * @return string Callback URL
     */
    public function get_callback_url() {
        return home_url('index.php?orabooks_oidc_callback=1');
    }

    /**
     * Check if OIDC is enabled and configured.
     *
     * @return bool
     */
    public function is_enabled() {
        if (!get_option(self::OPTION_ENABLED, 0)) {
            return false;
        }
        $client_id = get_option(self::OPTION_CLIENT_ID, '');
        $client_secret_enc = get_option(self::OPTION_CLIENT_SECRET, '');
        if (empty($client_id) || empty($client_secret_enc)) {
            return false;
        }
        return true;
    }

    /**
     * Get decrypted client secret.
     *
     * @return string Decrypted secret or empty string
     */
    public function get_client_secret() {
        $encrypted = get_option(self::OPTION_CLIENT_SECRET, '');
        if (empty($encrypted)) {
            return '';
        }
        return self::decrypt_secret($encrypted);
    }

    /**
     * Get client ID.
     *
     * @return string
     */
    public function get_client_id() {
        return get_option(self::OPTION_CLIENT_ID, '');
    }

    // ================================================================
    // CRYPTOGRAPHIC HELPERS (SL-008 domain-separated)
    // ================================================================

    /**
     * Encrypt a plaintext string using AES-256-CTR with WP salt key material.
     * Domain-separated from reCAPTCHA and 2FA by appending 'oidc' to the key.
     *
     * @param  string $plaintext
     * @return string Base64-encoded ciphertext (IV prepended).
     */
    private static function encrypt_secret($plaintext) {
        if (!defined('AUTH_KEY') || !defined('AUTH_SALT')) {
            return $plaintext;
        }
        $key        = hash('sha256', AUTH_KEY . AUTH_SALT . 'oidc', true);
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
        $key  = hash('sha256', AUTH_KEY . AUTH_SALT . 'oidc', true);
        $data = base64_decode($encoded);
        if ($data === false || strlen($data) < 16) {
            return $encoded;
        }
        $iv         = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $plaintext  = openssl_decrypt($ciphertext, 'aes-256-ctr', $key, OPENSSL_RAW_DATA, $iv);
        return ($plaintext !== false) ? $plaintext : $encoded;
    }

    // ================================================================
    // REWRITE RULES FOR CALLBACK
    // ================================================================

    /**
     * Register the rewrite rule for the OIDC callback endpoint.
     * URL: /api/auth/google/callback
     * Flushes rules once via rules_flushed option guard (same pattern as login-integration.php).
     */
    public function register_callback_rewrite() {
        add_rewrite_rule(
            '^api/auth/google/callback/?$',
            'index.php?orabooks_oidc_callback=1',
            'top'
        );

        if (!get_option('orabooks_oidc_rules_flushed')) {
            flush_rewrite_rules();
            update_option('orabooks_oidc_rules_flushed', true);
        }
    }

    /**
     * Add the callback query variable.
     */
    public function add_callback_query_var($vars) {
        $vars[] = 'orabooks_oidc_callback';
        return $vars;
    }

    // ================================================================
    // AJAX: INITIATE GOOGLE LOGIN
    // ================================================================

    /**
     * AJAX handler: Generate Google OAuth authorization URL and return it.
     * Called when user clicks "Sign in with Google" button.
     *
     * The state parameter carries user_type, partner_code, accept_terms,
     * terms_version, partner_type, and organization_name per SL-013 §5.5.
     */
    public function ajax_redirect_to_google() {
        // Verify AJAX nonce for CSRF protection
        check_ajax_referer('orabooks_oidc_nonce', 'nonce');

        if (!$this->is_enabled()) {
            wp_send_json_error(array('message' => __('Google login is not configured.', 'orabooks')));
        }

        $client_id = $this->get_client_id();

        // Build state payload from request parameters
        $state_payload = array(
            'nonce'         => wp_create_nonce('orabooks_oidc_state'),
            'redirect_to'   => isset($_REQUEST['redirect_to']) ? esc_url_raw($_REQUEST['redirect_to']) : home_url('/dashboard/'),
            'user_type'     => isset($_REQUEST['user_type']) ? sanitize_text_field($_REQUEST['user_type']) : '',
            'partner_code'  => isset($_REQUEST['partner_code']) ? sanitize_text_field($_REQUEST['partner_code']) : '',
            'accept_terms'  => isset($_REQUEST['accept_terms']) ? (int)$_REQUEST['accept_terms'] : 0,
            'terms_version' => isset($_REQUEST['terms_version']) ? sanitize_text_field($_REQUEST['terms_version']) : '',
            'partner_type'  => isset($_REQUEST['partner_type']) ? sanitize_text_field($_REQUEST['partner_type']) : '',
            'org_name'      => isset($_REQUEST['organization_name']) ? sanitize_text_field($_REQUEST['organization_name']) : '',
        );

        // Generate state token (random string) and store payload in transient
        $state_token = bin2hex(random_bytes(32));
        set_transient(
            'orabooks_oidc_state_' . $state_token,
            $state_payload,
            self::STATE_EXPIRY
        );

        // Build authorization URL
        $auth_url = add_query_arg(array(
            'client_id'     => $client_id,
            'redirect_uri'  => $this->get_callback_url(),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state_token,
            'access_type'   => 'online',
            'prompt'        => 'select_account',
        ), self::GOOGLE_AUTH_URL);

        wp_send_json_success(array('redirect_url' => $auth_url));
    }

    // ================================================================
    // CALLBACK HANDLER
    // ================================================================

    /**
     * Handle the OIDC callback from Google.
     * Exchanges authorization code for tokens, verifies ID token,
     * finds or creates user, and sets auth cookie.
     */
    public function handle_callback() {
        if (!get_query_var('orabooks_oidc_callback') && !isset($_GET['orabooks_oidc_callback'])) {
            return;
        }

        // Check for errors from Google
        if (isset($_GET['error'])) {
            $error = sanitize_text_field($_GET['error']);
            $this->redirect_with_error(
                sprintf(__('Google login failed: %s', 'orabooks'), $error)
            );
        }

        // Validate state parameter
        $state_token = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';
        if (empty($state_token) || !ctype_xdigit($state_token)) {
            $this->redirect_with_error(__('Invalid state parameter. Please try again.', 'orabooks'));
        }

        $state_payload = get_transient('orabooks_oidc_state_' . $state_token);
        if (empty($state_payload)) {
            $this->redirect_with_error(__('Session expired. Please try signing in again.', 'orabooks'));
        }

        // Verify nonce
        if (!wp_verify_nonce($state_payload['nonce'], 'orabooks_oidc_state')) {
            $this->redirect_with_error(__('Security check failed. Please try again.', 'orabooks'));
        }

        // Clean up state transient
        delete_transient('orabooks_oidc_state_' . $state_token);

        // Verify authorization code
        $auth_code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';
        if (empty($auth_code)) {
            $this->redirect_with_error(__('No authorization code received.', 'orabooks'));
        }

        // Exchange authorization code for tokens
        $token_data = $this->exchange_code_for_tokens($auth_code);
        if (is_wp_error($token_data)) {
            $this->redirect_with_error($token_data->get_error_message());
        }

        // Verify ID token and extract user info
        $user_info = $this->extract_user_info($token_data);
        if (is_wp_error($user_info)) {
            $this->redirect_with_error($user_info->get_error_message());
        }

        // Find existing user or create new one
        $result = $this->find_or_create_user($user_info, $state_payload);
        if (is_wp_error($result)) {
            $this->redirect_with_error($result->get_error_message());
        }

        $user_id  = $result['user_id'];
        $is_new   = $result['is_new'];
        $redirect = $state_payload['redirect_to'];

        // Set auth cookie
        wp_set_auth_cookie($user_id, true);
        wp_set_current_user($user_id);

        // Audit event
        do_action('orabooks_security_event', $is_new ? 'user_oidc_registered' : 'user_oidc_login', array(
            'user_id'    => $user_id,
            'email'      => $user_info['email'],
            'auth_provider' => 'google',
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
        ));

        // Determine redirect based on org status
        $org_id = get_user_meta($user_id, 'org_id', true);
        $is_partner = (bool) get_user_meta($user_id, 'is_partner', true);

        if ($is_new && $is_partner && empty($org_id)) {
            // Partner: auto-create org via the existing handler
            // The wp_login hook in class-orabooks-registration.php handles this
            $redirect = home_url('/partner/onboarding/');
        } elseif ($is_new && !$is_partner && empty($org_id)) {
            // Customer: needs tier selection
            $redirect = home_url('/select-tier/');
        }

        wp_safe_redirect(esc_url_raw($redirect));
        exit;
    }

    /**
     * Exchange the authorization code for access + ID tokens.
     *
     * @param  string $auth_code Authorization code from Google.
     * @return array|WP_Error    Token data or error.
     */
    private function exchange_code_for_tokens($auth_code) {
        $client_id     = $this->get_client_id();
        $client_secret = $this->get_client_secret();

        $response = wp_remote_post(self::GOOGLE_TOKEN_URL, array(
            'body' => array(
                'code'          => $auth_code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $this->get_callback_url(),
                'grant_type'    => 'authorization_code',
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return new WP_Error('token_exchange_failed', __('Failed to contact Google. Please try again.', 'orabooks'));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || !empty($data['error'])) {
            $error_msg = isset($data['error_description']) ? $data['error_description'] : __('Token exchange failed.', 'orabooks');
            return new WP_Error('token_exchange_error', $error_msg);
        }

        return $data;
    }

    /**
     * Extract user info from the ID token or userinfo endpoint.
     *
     * Per SL-013: Get email, name, and Google UID from the ID token.
     * Falls back to userinfo endpoint if ID token is not present.
     *
     * @param  array $token_data Token response from Google.
     * @return array|WP_Error    User info array or error.
     */
    private function extract_user_info($token_data) {
        // Try to decode ID token first (JWT)
        if (!empty($token_data['id_token'])) {
            $parts = explode('.', $token_data['id_token']);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
                if (!empty($payload) && !empty($payload['email'])) {
                    return array(
                        'sub'           => $payload['sub'],
                        'email'         => sanitize_email($payload['email']),
                        'email_verified' => !empty($payload['email_verified']),
                        'name'          => isset($payload['name']) ? sanitize_text_field($payload['name']) : '',
                        'given_name'    => isset($payload['given_name']) ? sanitize_text_field($payload['given_name']) : '',
                        'family_name'   => isset($payload['family_name']) ? sanitize_text_field($payload['family_name']) : '',
                        'picture'       => isset($payload['picture']) ? esc_url_raw($payload['picture']) : '',
                    );
                }
            }
        }

        // Fallback: call userinfo endpoint with access token
        if (!empty($token_data['access_token'])) {
            $response = wp_remote_get(self::GOOGLE_USERINFO_URL, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token_data['access_token'],
                ),
                'timeout' => 10,
            ));

            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                if (!empty($data) && !empty($data['email'])) {
                    return array(
                        'sub'           => $data['sub'],
                        'email'         => sanitize_email($data['email']),
                        'email_verified' => true,
                        'name'          => isset($data['name']) ? sanitize_text_field($data['name']) : '',
                        'given_name'    => isset($data['given_name']) ? sanitize_text_field($data['given_name']) : '',
                        'family_name'   => isset($data['family_name']) ? sanitize_text_field($data['family_name']) : '',
                        'picture'       => isset($data['picture']) ? esc_url_raw($data['picture']) : '',
                    );
                }
            }
        }

        return new WP_Error('userinfo_failed', __('Could not retrieve user information from Google.', 'orabooks'));
    }

    /**
     * Find an existing user by Google sub (subject) identifier or email,
     * or create a new user if not found.
     *
     * SL-013 §5.5 rules:
     * - If email matches an existing user with auth_provider='local' → 409 Conflict
     * - If email matches an existing user with auth_provider='google' → login
     * - If email doesn't exist → create new user with is_email_verified=true
     *
     * @param  array $user_info    User info from Google.
     * @param  array $state_payload State payload with signup parameters.
     * @return array|WP_Error      Result with user_id and is_new, or error.
     */
    private function find_or_create_user($user_info, $state_payload) {
        $email = $user_info['email'];
        $sub   = $user_info['sub'];

        // Try to find user by Google sub identifier first
        $users_by_sub = get_users(array(
            'meta_key'   => 'google_sub',
            'meta_value' => $sub,
            'number'     => 1,
            'fields'     => 'ID',
        ));

        if (!empty($users_by_sub)) {
            // Existing Google user — log them in
            return array(
                'user_id' => (int) $users_by_sub[0],
                'is_new'  => false,
            );
        }

        // Try to find by email
        $existing_user = get_user_by('email', $email);

        if ($existing_user) {
            // Check auth provider
            $auth_provider = get_user_meta($existing_user->ID, 'auth_provider', true);

            if ($auth_provider === 'local') {
                // SL-013 §5.5: Local account conflict → 409 Conflict
                return new WP_Error(
                    'account_conflict',
                    sprintf(
                        __('An account with email %s already exists. Please sign in with your email and password, or reset your password.', 'orabooks'),
                        $email
                    )
                );
            }

            if ($auth_provider === 'google' || empty($auth_provider)) {
                // Update Google sub if not set
                update_user_meta($existing_user->ID, 'google_sub', $sub);
                return array(
                    'user_id' => $existing_user->ID,
                    'is_new'  => false,
                );
            }
        }

        // ── Create new user ──────────────────────────────────────────

        $username = $this->generate_unique_username($email, $user_info['name']);
        $password = wp_generate_password(24, true, true);

        $user_id = wp_insert_user(array(
            'user_login'   => $username,
            'user_email'   => $email,
            'display_name' => !empty($user_info['name']) ? $user_info['name'] : $email,
            'first_name'   => !empty($user_info['given_name']) ? $user_info['given_name'] : '',
            'last_name'    => !empty($user_info['family_name']) ? $user_info['family_name'] : '',
            'user_pass'    => $password,
            'role'         => 'subscriber',
        ));

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // Set user meta for OIDC user
        update_user_meta($user_id, 'auth_provider', 'google');
        update_user_meta($user_id, 'google_sub', $sub);
        update_user_meta($user_id, 'google_picture', $user_info['picture']);
        update_user_meta($user_id, 'is_email_verified', 1); // SL-013: OIDC users are pre-verified
        update_user_meta($user_id, 'is_2fa_enabled', 0);

        // Determine user_type from state payload
        $user_type = !empty($state_payload['user_type']) ? $state_payload['user_type'] : 'customer';
        $is_partner = ($user_type === 'partner');
        update_user_meta($user_id, 'is_partner', $is_partner ? 1 : 0);

        // Partner-specific handling (SL-013 §5.5)
        if ($is_partner) {
            $partner_type = 'individual';
            if (!empty($state_payload['partner_type'])) {
                if (in_array($state_payload['partner_type'], array('individual', 'accountant', 'agency', 'reseller', 'strategic_partner'), true)) {
                    $partner_type = $state_payload['partner_type'];
                }
            }

            update_user_meta($user_id, 'partner_type', $partner_type);

            if (!empty($state_payload['org_name'])) {
                update_user_meta($user_id, 'organization_name', sanitize_text_field($state_payload['org_name']));
            }

            // Record terms acceptance if provided
            if (!empty($state_payload['accept_terms']) && !empty($state_payload['terms_version'])) {
                if (class_exists('OraBooks_Partners')) {
                    $partners = OraBooks_Partners::get_instance();
                    $partners->record_terms_acceptance($user_id, $state_payload['terms_version']);
                }
            }
        }

        // Customer with partner code
        if (!$is_partner && !empty($state_payload['partner_code'])) {
            update_user_meta($user_id, 'pending_partner_code', sanitize_text_field($state_payload['partner_code']));
        }

        // Audit
        do_action('orabooks_security_event', 'user_oidc_registered', array(
            'user_id'    => $user_id,
            'email'      => $email,
            'user_type'  => $user_type,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
        ));

        return array(
            'user_id' => $user_id,
            'is_new'  => true,
        );
    }

    /**
     * Generate a unique username from email or display name.
     *
     * @param  string $email      User's email.
     * @param  string $display_name User's display name (optional).
     * @return string              Unique username.
     */
    private function generate_unique_username($email, $display_name = '') {
        // Try to extract from email
        $base = strtolower(sanitize_user(strstr($email, '@', true), true));
        if (empty($base)) {
            $base = 'user';
        }

        // Remove non-alphanumeric characters
        $base = preg_replace('/[^a-z0-9]/', '', $base);
        if (empty($base)) {
            $base = 'googleuser';
        }

        // Truncate to 50 chars (WP max is 60)
        $base = substr($base, 0, 50);

        $username = $base;
        $counter = 1;

        while (username_exists($username)) {
            $suffix = (string) $counter;
            $username = substr($base, 0, 60 - strlen($suffix)) . $suffix;
            $counter++;
        }

        return $username;
    }

    // ================================================================
    // GOOGLE SIGN-IN BUTTON
    // ================================================================

    /**
     * Render the "Sign in with Google" button on login/signup forms.
     * Hooked to lwws_after_login_form_end action.
     */
    public function render_google_button() {
        if (!$this->is_enabled()) {
            return;
        }

        $client_id = $this->get_client_id();
        ?>
        <div class="orabooks-oidc-divider" style="margin: 20px 0; text-align: center; position: relative;">
            <div style="border-top: 1px solid #ddd; margin: 15px 0;"></div>
            <span style="background: #fff; padding: 0 10px; color: #888; font-size: 13px; position: relative; top: -22px;">
                <?php esc_html_e('OR', 'orabooks'); ?>
            </span>
        </div>

        <div class="orabooks-oidc-button-wrap" style="text-align: center; margin-bottom: 15px;">
            <button type="button" id="orabooks-google-signin"
                    class="orabooks-oidc-google-button"
                    style="display: inline-flex; align-items: center; justify-content: center; gap: 10px;
                           padding: 10px 24px; border: 1px solid #dadce0; border-radius: 4px;
                           background: #fff; color: #3c4043; font-size: 14px; font-weight: 500;
                           font-family: 'Roboto', sans-serif; cursor: pointer; width: 100%;
                           transition: background 0.2s, box-shadow 0.2s;"
                    onmouseover="this.style.background='#f8f9fa'; this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)';"
                    onmouseout="this.style.background='#fff'; this.style.boxShadow='none';">
                <svg width="18" height="18" viewBox="0 0 48 48">
                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                    <path fill="#FBBC05" d="M10.54 28.59A14.5 14.5 0 019.5 24c0-1.59.28-3.14.76-4.59l-7.98-6.19A23.94 23.94 0 000 24c0 3.77.87 7.35 2.56 10.46l7.98-5.87z"/>
                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 5.87C6.51 42.62 14.62 48 24 48z"/>
                </svg>
                <span><?php esc_html_e('Sign in with Google', 'orabooks'); ?></span>
            </button>
            <div id="orabooks-oidc-error" style="color: #dc3232; margin-top: 8px; display: none;"></div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#orabooks-google-signin').on('click', function() {
                var $btn = $(this);
                var $error = $('#orabooks-oidc-error');
                $btn.prop('disabled', true).css('opacity', '0.7');
                $error.hide().text('');

                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'orabooks_oidc_redirect',
                    nonce: '<?php echo wp_create_nonce('orabooks_oidc_nonce'); ?>',
                    redirect_to: '<?php echo esc_js($this->get_current_page_url()); ?>',
                    <?php if (isset($_POST['orabooks_user_type'])): ?>
                    user_type: '<?php echo esc_js(sanitize_text_field($_POST['orabooks_user_type'])); ?>',
                    <?php endif; ?>
                    <?php if (!empty($_POST['orabooks_partner_code'])): ?>
                    partner_code: '<?php echo esc_js(sanitize_text_field($_POST['orabooks_partner_code'])); ?>',
                    <?php endif; ?>
                    accept_terms: <?php echo isset($_POST['orabooks_accept_terms']) && $_POST['orabooks_accept_terms'] ? 1 : 0; ?>,
                    terms_version: '<?php echo isset($_POST['orabooks_terms_version']) ? esc_js(sanitize_text_field($_POST['orabooks_terms_version'])) : ''; ?>',
                    partner_type: '<?php echo isset($_POST['orabooks_partner_type']) ? esc_js(sanitize_text_field($_POST['orabooks_partner_type'])) : ''; ?>',
                    organization_name: '<?php echo isset($_POST['orabooks_organization_name']) ? esc_js(sanitize_text_field($_POST['orabooks_organization_name'])) : ''; ?>'
                }, function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        $error.text(response.data.message || '<?php echo esc_js(__('An error occurred. Please try again.', 'orabooks')); ?>').show();
                        $btn.prop('disabled', false).css('opacity', '1');
                    }
                }).fail(function() {
                    $error.text('<?php echo esc_js(__('Connection error. Please try again.', 'orabooks')); ?>').show();
                    $btn.prop('disabled', false).css('opacity', '1');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Get the current page URL for redirect after login.
     * Uses WordPress home_url() for reliability across multisite/reverse-proxy setups.
     *
     * @return string Current page URL
     */
    private function get_current_page_url() {
        global $wp;
        if (isset($wp->request)) {
            return home_url($wp->request);
        }
        // Fallback: reconstruct from server vars
        return home_url(add_query_arg(array()));
    }

    // ================================================================
    // ERROR HANDLING
    // ================================================================

    /**
     * Redirect to login page with an error message.
     *
     * @param string $message Error message
     */
    private function redirect_with_error($message) {
        $login_url = add_query_arg(
            'oidc_error',
            urlencode($message),
            wp_login_url()
        );
        wp_safe_redirect(esc_url_raw($login_url));
        exit;
    }
}

// Initialize the OIDC system
OraBooks_OIDC::get_instance();
