<?php
/**
 * SL-013 – JWT Token Management
 *
 * Implements JWT access tokens (15 min) and refresh tokens (7 days)
 * with mandatory rotation, hashed storage, and device metadata.
 *
 * Features:
 *   - JWT generation and validation (HMAC-SHA256, RFC 7519)
 *   - JWT claims: user_id, org_id, role, subdomain, exp, iat
 *   - Refresh token storage (SHA-256 hashed) with device metadata
 *   - Mandatory refresh token rotation on each use
 *   - Logout: revoke refresh token, clear cookies
 *   - Password reset: revoke ALL refresh tokens for user
 *   - Role change: revoke all refresh tokens for user in org
 *   - Audit events: refresh_token_rotated, user_logged_out
 *
 * Build Order: SL-004 → SL-013 → SL-003 → SL-017 → SL-139 → SL-068
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_JWT {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * JWT access token expiry (15 minutes)
     */
    const ACCESS_TOKEN_EXPIRY = 900; // 15 min in seconds

    /**
     * Refresh token expiry (7 days)
     */
    const REFRESH_TOKEN_EXPIRY = 604800; // 7 days in seconds

    /**
     * Algorithm used for JWT signing
     */
    const JWT_ALGORITHM = 'HS256';

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
        // Register hooks (AJAX-based, no rewrite rules needed)
        add_action('plugins_loaded', array($this, 'init_table_names'));

        // AJAX endpoints for token refresh and logout
        add_action('wp_ajax_nopriv_orabooks_refresh_token', array($this, 'ajax_refresh_token'));
        add_action('wp_ajax_orabooks_refresh_token',        array($this, 'ajax_refresh_token'));
        add_action('wp_ajax_nopriv_orabooks_jwt_logout',    array($this, 'ajax_logout'));
        add_action('wp_ajax_orabooks_jwt_logout',           array($this, 'ajax_logout'));

        // Hook into password reset to revoke all tokens
        add_action('password_reset', array($this, 'revoke_all_user_tokens'), 10, 2);
        
        // Hook into role change to revoke org tokens
        add_action('orabooks_revoke_user_sessions', array($this, 'revoke_user_org_tokens'), 10, 2);
    }

    /**
     * Initialize table names for multisite.
     */
    public function init_table_names() {
        global $wpdb;

        $prefix = is_multisite() ? $wpdb->base_prefix : $wpdb->prefix;
        $wpdb->orabooks_refresh_tokens = $prefix . 'orabooks_refresh_tokens';
    }

    // No rewrite rules needed — all JWT operations use AJAX endpoints
    // consistent with the existing codebase pattern (e.g., class-2fa-ajax.php).

    /**
     * SL-013: Create the refresh_tokens table.
     * Call during plugin activation.
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $prefix = is_multisite() ? $wpdb->base_prefix : $wpdb->prefix;
        $table_name = $prefix . 'orabooks_refresh_tokens';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            org_id INT NULL,
            token_hash VARCHAR(64) NOT NULL,
            device_metadata TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            revoked_at TIMESTAMP NULL,
            INDEX idx_user (user_id),
            INDEX idx_hash (token_hash),
            INDEX idx_expires (expires_at),
            INDEX idx_user_revoked (user_id, revoked_at)
        ) {$charset_collate};";

        dbDelta($sql);

        error_log('[OraBooks SL-013 JWT] Refresh tokens table created/verified.');
    }

    // ================================================================
    // JWT CORE: Encoding / Decoding
    // ================================================================

    /**
     * Get the JWT signing key (domain-separated for JWT use).
     *
     * @return string HMAC key
     */
    private static function get_signing_key() {
        if (!defined('AUTH_KEY') || !defined('AUTH_SALT')) {
            // Fallback: use a generated key (should not happen in production)
            $key = get_option('orabooks_jwt_secret', '');
            if (empty($key)) {
                $key = wp_generate_password(64, true, true);
                update_option('orabooks_jwt_secret', $key);
            }
            return $key;
        }
        return hash('sha256', AUTH_KEY . AUTH_SALT . 'orabooks-jwt', true);
    }

    /**
     * Base64 URL-safe encode.
     *
     * @param  string $data Raw binary data
     * @return string       URL-safe Base64 string
     */
    private static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-safe decode.
     *
     * @param  string      $data URL-safe Base64 string
     * @return string|false      Raw binary data or false on failure
     */
    private static function base64url_decode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Generate a JWT access token.
     *
     * @param  array $payload Claims to include (user_id, org_id, role, subdomain)
     * @return string         Encoded JWT string
     */
    public static function generate_access_token($payload) {
        $header = array(
            'alg' => self::JWT_ALGORITHM,
            'typ' => 'JWT',
        );

        // Add standard claims
        $now = time();
        $token_payload = array_merge($payload, array(
            'iat' => $now,
            'exp' => $now + self::ACCESS_TOKEN_EXPIRY,
            'jti' => bin2hex(random_bytes(16)), // Unique token ID
        ));

        // Encode header and payload
        $segments = array();
        $segments[] = self::base64url_encode(json_encode($header));
        $segments[] = self::base64url_encode(json_encode($token_payload));

        // Sign
        $signing_input = implode('.', $segments);
        $key = self::get_signing_key();
        $signature = hash_hmac('sha256', $signing_input, $key, true);
        $segments[] = self::base64url_encode($signature);

        return implode('.', $segments);
    }

    /**
     * Validate and decode a JWT access token.
     * Returns the payload if valid, or a WP_Error if invalid/expired.
     *
     * @param  string $token  JWT string to validate
     * @return array|WP_Error Payload array or error
     */
    public static function validate_access_token($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return new WP_Error('invalid_token', __('Invalid token format.', 'orabooks'));
        }

        list($header_b64, $payload_b64, $signature_b64) = $parts;

        // Verify signature
        $signing_input = $header_b64 . '.' . $payload_b64;
        $key = self::get_signing_key();
        $expected_signature = hash_hmac('sha256', $signing_input, $key, true);
        $provided_signature = self::base64url_decode($signature_b64);

        if ($provided_signature === false || !hash_equals($expected_signature, $provided_signature)) {
            return new WP_Error('invalid_signature', __('Invalid token signature.', 'orabooks'));
        }

        // Decode payload
        $payload = json_decode(self::base64url_decode($payload_b64), true);
        if (empty($payload)) {
            return new WP_Error('invalid_payload', __('Invalid token payload.', 'orabooks'));
        }

        // Check expiry
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return new WP_Error('token_expired', __('Token has expired.', 'orabooks'));
        }

        // Ensure required claims exist
        if (empty($payload['user_id'])) {
            return new WP_Error('missing_claim', __('Missing required claims.', 'orabooks'));
        }

        return $payload;
    }

    // ================================================================
    // REFRESH TOKEN MANAGEMENT
    // ================================================================

    /**
     * Generate a refresh token, store its hash, and return the raw token.
     *
     * @param  int    $user_id           User ID
     * @param  int    $org_id            Organization ID (optional)
     * @param  string $device_metadata   Device description (optional)
     * @return array                     { token: raw_token, expires_at: timestamp }
     */
    public function create_refresh_token($user_id, $org_id = null, $device_metadata = '') {
        global $wpdb;

        $table = $wpdb->base_prefix . 'orabooks_refresh_tokens';

        // Generate cryptographically secure raw token
        $raw_token = bin2hex(random_bytes(32)); // 64 hex chars
        $token_hash = hash('sha256', $raw_token);
        $expires_at = date('Y-m-d H:i:s', time() + self::REFRESH_TOKEN_EXPIRY);

        $wpdb->insert(
            $table,
            array(
                'user_id'         => (int) $user_id,
                'org_id'          => $org_id ? (int) $org_id : null,
                'token_hash'      => $token_hash,
                'device_metadata' => sanitize_text_field($device_metadata),
                'ip_address'      => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
                'user_agent'      => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
                'expires_at'      => $expires_at,
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );

        if ($wpdb->last_error) {
            error_log('[OraBooks JWT] Failed to create refresh token: ' . $wpdb->last_error);
            return new WP_Error('db_error', __('Failed to create refresh token.', 'orabooks'));
        }

        return array(
            'token'      => $raw_token,
            'expires_at' => $expires_at,
        );
    }

    /**
     * Validate a refresh token (raw) against stored hash.
     * If valid and not expired/revoked, returns the token record.
     *
     * @param  string     $raw_token Raw refresh token from cookie
     * @return object|false          Token row or false
     */
    public function validate_refresh_token($raw_token) {
        global $wpdb;

        $table = $wpdb->base_prefix . 'orabooks_refresh_tokens';
        $token_hash = hash('sha256', $raw_token);

        $token = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE token_hash = %s AND revoked_at IS NULL AND expires_at > NOW()",
            $token_hash
        ));

        return $token;
    }

    /**
     * Revoke a specific refresh token by its raw value.
     *
     * @param string $raw_token Raw refresh token
     */
    public function revoke_refresh_token($raw_token) {
        global $wpdb;

        $table = $wpdb->base_prefix . 'orabooks_refresh_tokens';
        $token_hash = hash('sha256', $raw_token);

        $wpdb->update(
            $table,
            array('revoked_at' => current_time('mysql')),
            array('token_hash' => $token_hash),
            array('%s'),
            array('%s')
        );
    }

    /**
     * Revoke ALL refresh tokens for a user (e.g., on password reset).
     *
     * @param int    $user_id    User ID
     * @param string $exclude_token_hash Optional hash to exclude (current token)
     */
    public function revoke_all_user_tokens($user_id, $exclude_token_hash = '') {
        global $wpdb;

        $table = $wpdb->base_prefix . 'orabooks_refresh_tokens';
        $now = current_time('mysql');

        if (empty($exclude_token_hash)) {
            $wpdb->update(
                $table,
                array('revoked_at' => $now),
                array('user_id' => (int) $user_id, 'revoked_at' => null),
                array('%s'),
                array('%d', '%s')
            );
        } else {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET revoked_at = %s WHERE user_id = %d AND revoked_at IS NULL AND token_hash != %s",
                $now,
                (int) $user_id,
                $exclude_token_hash
            ));
        }

        do_action('orabooks_security_event', 'user_tokens_revoked', array(
            'user_id' => $user_id,
            'reason'  => empty($exclude_token_hash) ? 'password_reset' : 'bulk_revocation',
        ));
    }

    /**
     * Revoke all refresh tokens for a user in a specific org (on role change/removal).
     * Hooks into orabooks_revoke_user_sessions action.
     *
     * @param int $user_id User ID
     * @param int $org_id  Organization ID
     */
    public function revoke_user_org_tokens($user_id, $org_id) {
        global $wpdb;

        $table = $wpdb->base_prefix . 'orabooks_refresh_tokens';
        $now = current_time('mysql');

        $wpdb->update(
            $table,
            array('revoked_at' => $now),
            array('user_id' => (int) $user_id, 'org_id' => (int) $org_id, 'revoked_at' => null),
            array('%s'),
            array('%d', '%d', '%s')
        );

        do_action('orabooks_security_event', 'org_tokens_revoked', array(
            'user_id' => $user_id,
            'org_id'  => $org_id,
        ));
    }

    /**
     * Clean up expired refresh tokens (optional maintenance job).
     */
    public function cleanup_expired_tokens() {
        global $wpdb;

        $table = $wpdb->base_prefix . 'orabooks_refresh_tokens';

        $deleted = $wpdb->query(
            "DELETE FROM {$table} WHERE expires_at < NOW() OR revoked_at IS NOT NULL"
        );

        if ($deleted > 0) {
            error_log('[OraBooks JWT] Cleaned up ' . $deleted . ' expired/revoked refresh tokens.');
        }

        return $deleted;
    }

    // ================================================================
    // FULL TOKEN ISSUANCE (Access + Refresh)
    // ================================================================

    /**
     * Issue a full token set (JWT access token + refresh token) for a user.
     * Called after successful authentication (email/password, OIDC, 2FA).
     *
     * @param  int    $user_id         User ID
     * @param  int    $org_id          Organization ID (optional)
     * @param  string $role            User role in org (optional)
     * @param  string $subdomain       Organization subdomain (optional)
     * @param  string $device_metadata Device description (optional)
     * @return array|WP_Error          Token set or error
     */
    public function issue_token_set($user_id, $org_id = null, $role = '', $subdomain = '', $device_metadata = '') {
        // Build JWT claims per SL-003 §5.5
        $jwt_payload = array(
            'user_id'   => (int) $user_id,
        );

        if ($org_id) {
            $jwt_payload['org_id'] = (int) $org_id;
        }
        if (!empty($role)) {
            $jwt_payload['role'] = $role;
        }
        if (!empty($subdomain)) {
            $jwt_payload['subdomain'] = $subdomain;
        }

        // Do NOT include full permissions array per spec (SL-003 §5.5)

        $access_token = self::generate_access_token($jwt_payload);

        $refresh = $this->create_refresh_token($user_id, $org_id, $device_metadata);
        if (is_wp_error($refresh)) {
            return $refresh;
        }

        return array(
            'access_token'          => $access_token,
            'refresh_token'         => $refresh['token'],
            'expires_in'            => self::ACCESS_TOKEN_EXPIRY,
            'refresh_expires_in'    => self::REFRESH_TOKEN_EXPIRY,
            'token_type'            => 'Bearer',
        );
    }

    /**
     * Refresh an access token using a valid refresh token (mandatory rotation).
     *
     * @param  string $raw_refresh_token Raw refresh token
     * @return array|WP_Error            New token set or error
     */
    public function refresh_access_token($raw_refresh_token) {
        $token = $this->validate_refresh_token($raw_refresh_token);
        if (!$token) {
            return new WP_Error('invalid_refresh_token', __('Invalid or expired refresh token.', 'orabooks'));
        }

        // Revoke the old refresh token (mandatory rotation per spec)
        $this->revoke_refresh_token($raw_refresh_token);

        // Get current role and org info
        $role = '';
        $subdomain = '';
        $org_id = $token->org_id ? (int) $token->org_id : null;

        if ($org_id && class_exists('OraBooks_Users_Teams')) {
            $role = OraBooks_Users_Teams::get_instance()->get_user_role($token->user_id, $org_id);
        }

        if ($org_id && class_exists('OraBooks_Organizations')) {
            $org = OraBooks_Organizations::get_instance()->get_organization($org_id);
            if ($org) {
                $subdomain = $org->subdomain;
            }
        }

        // Issue new token set
        $result = $this->issue_token_set(
            $token->user_id,
            $org_id,
            $role,
            $subdomain,
            __('Session refresh', 'orabooks')
        );

        if (is_wp_error($result)) {
            return $result;
        }

        // Audit event
        do_action('orabooks_security_event', 'refresh_token_rotated', array(
            'user_id' => $token->user_id,
            'org_id'  => $org_id,
        ));

        return $result;
    }

    /**
     * Logout: revoke the given refresh token and clear cookies.
     *
     * @param string $raw_refresh_token Raw refresh token to revoke
     */
    public function logout($raw_refresh_token) {
        global $wpdb;

        if (!empty($raw_refresh_token)) {
            $this->revoke_refresh_token($raw_refresh_token);
        }

        // Clear WordPress auth cookie
        wp_logout();

        // Set cookie expiration to clear it
        if (!headers_sent()) {
            setcookie('orabooks_refresh_token', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        }

        do_action('orabooks_security_event', 'user_logged_out', array(
            'user_id' => get_current_user_id(),
        ));
    }

    /**
     * Issue refresh token as an HTTP-only cookie for automatic refresh.
     *
     * @param int    $user_id           User ID
     * @param int    $org_id            Organization ID
     * @param string $role              User role
     * @param string $subdomain         Organization subdomain
     * @param string $device_metadata   Device description
     * @return array Token set (access_token used for Bearer auth)
     */
    public function issue_and_set_cookie($user_id, $org_id = null, $role = '', $subdomain = '', $device_metadata = '') {
        $token_set = $this->issue_token_set($user_id, $org_id, $role, $subdomain, $device_metadata);

        if (is_wp_error($token_set)) {
            return $token_set;
        }

        // Set refresh token as HTTP-only cookie (7 days)
        if (!headers_sent()) {
            setcookie(
                'orabooks_refresh_token',
                $token_set['refresh_token'],
                time() + self::REFRESH_TOKEN_EXPIRY,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true // HTTP-only
            );
        }

        return $token_set;
    }

    // ================================================================
    // AJAX ENDPOINTS
    // ================================================================

    /**
     * AJAX handler: Refresh access token using the refresh token cookie.
     * POST /api/auth/refresh
     */
    public function ajax_refresh_token() {
        $raw_refresh = '';

        // Try from POST body first, then from cookie
        if (!empty($_POST['refresh_token'])) {
            $raw_refresh = sanitize_text_field($_POST['refresh_token']);
        } elseif (!empty($_COOKIE['orabooks_refresh_token'])) {
            $raw_refresh = sanitize_text_field($_COOKIE['orabooks_refresh_token']);
        }

        if (empty($raw_refresh)) {
            wp_send_json_error(array(
                'message' => __('No refresh token provided.', 'orabooks'),
                'code'    => 'missing_token',
            ), 401);
        }

        $result = $this->refresh_access_token($raw_refresh);

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'code'    => $result->get_error_code(),
            ), 401);
        }

        // Set new refresh token cookie
        if (!headers_sent()) {
            setcookie(
                'orabooks_refresh_token',
                $result['refresh_token'],
                time() + self::REFRESH_TOKEN_EXPIRY,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
        }

        wp_send_json_success(array(
            'access_token' => $result['access_token'],
            'expires_in'   => $result['expires_in'],
            'token_type'   => $result['token_type'],
        ));
    }

    /**
     * AJAX handler: Logout — revoke refresh token.
     * POST /api/auth/logout
     */
    public function ajax_logout() {
        $raw_refresh = '';

        if (!empty($_POST['refresh_token'])) {
            $raw_refresh = sanitize_text_field($_POST['refresh_token']);
        } elseif (!empty($_COOKIE['orabooks_refresh_token'])) {
            $raw_refresh = sanitize_text_field($_COOKIE['orabooks_refresh_token']);
        }

        $this->logout($raw_refresh);

        wp_send_json_success(array(
            'message' => __('Logged out successfully.', 'orabooks'),
        ));
    }
}

// Initialize the JWT system
OraBooks_JWT::get_instance();
