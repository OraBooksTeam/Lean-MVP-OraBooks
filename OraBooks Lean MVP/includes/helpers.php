<?php
/**
 * OraBooks Helper Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether the active theme is Divi (parent or child).
 */
function orabooks_is_divi_theme() {
    if (defined('ET_BUILDER_VERSION')) {
        return true;
    }

    $theme = wp_get_theme();
    if (!$theme) {
        return false;
    }

    $template = $theme->get_template();
    $stylesheet = $theme->get_stylesheet();

    return in_array('Divi', [$template, $stylesheet], true);
}

/**
 * Lean MVP frontend is React-only (see document_extracted.txt / Model List v5.2).
 */
function orabooks_should_use_react_frontend($user_id = 0) {
    return (bool) apply_filters('orabooks_use_react_frontend', true, $user_id);
}

/**
 * Legacy merged PHP accounting workspace — removed; always false.
 *
 * @deprecated
 */
function orabooks_uses_merged_accounting_workspace($user_id = 0) {
    return false;
}

/**
 * @deprecated
 */
function orabooks_render_merged_accounting_workspace($view = '') {
    if (!orabooks_is_user_logged_in()) {
        return OraBooks_Views::require_login_message();
    }

    return OraBooks_Views::render('react-app', ['initial_route' => '/dashboard']);
}

/**
 * @deprecated
 */
function orabooks_merged_accounting_shortcodes() {
    return [];
}

/**
 * Generate a cryptographically secure random string
 */
function orabooks_random_string($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Generate a secure partner code: PARTNER-XXXXXXXX
 */
function orabooks_generate_partner_code() {
    $random = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    return 'PARTNER-' . $random;
}

/**
 * Validate password policy
 * Min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special
 */
function orabooks_validate_password($password) {
    if (strlen($password) < 8) {
        return __('Password must be at least 8 characters', 'orabooks');
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return __('Password must contain at least one uppercase letter', 'orabooks');
    }
    if (!preg_match('/[a-z]/', $password)) {
        return __('Password must contain at least one lowercase letter', 'orabooks');
    }
    if (!preg_match('/[0-9]/', $password)) {
        return __('Password must contain at least one number', 'orabooks');
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        return __('Password must contain at least one special character', 'orabooks');
    }
    return true;
}

/**
 * Validate subdomain format
 */
function orabooks_validate_subdomain($subdomain) {
    // Reserved subdomains (case-insensitive)
    $reserved = ['admin', 'api', 'app', 'support', 'billing', 'partner', 'orabooks', 'www', 'root'];
    
    $subdomain = strtolower(trim($subdomain));
    
    if (in_array($subdomain, $reserved)) {
        return __('This subdomain is reserved', 'orabooks');
    }
    
    if (!preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/', $subdomain)) {
        return __('Subdomain must be 3-63 chars, lowercase alphanumeric with hyphens, no start/end hyphen', 'orabooks');
    }
    
    return true;
}

/**
 * Get the tenant base domain from the current request host.
 * Strips an org subdomain prefix when present (e.g. mycompany.example.com -> example.com).
 */
function orabooks_get_tenant_base_domain($host = '') {
    if ($host === '') {
        $host = $_SERVER['HTTP_HOST'] ?? '';
    }

    $host = strtolower(trim(preg_replace('/:\d+$/', '', $host)));
    if ($host === '') {
        return '';
    }

    $parts = explode('.', $host);
    $reserved = ['www', 'mail', 'admin'];

    if (count($parts) >= 3 && !in_array($parts[0], $reserved, true)) {
        return implode('.', array_slice($parts, 1));
    }

    return $host;
}

/**
 * Build a full organization URL from a stored subdomain identifier.
 */
function orabooks_build_org_url($subdomain, $path = '/') {
    $base_domain = orabooks_get_tenant_base_domain();
    if ($base_domain === '') {
        return home_url(ltrim($path, '/'));
    }

    $scheme = is_ssl() ? 'https' : 'http';
    $path = '/' . ltrim($path, '/');

    return $scheme . '://' . $subdomain . '.' . $base_domain . $path;
}

/**
 * Get client IP address
 */
function orabooks_get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Get current user agent
 */
function orabooks_get_user_agent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

/**
 * Whether public registration is allowed (single site or multisite network setting).
 */
function orabooks_users_can_register() {
    if (function_exists('is_multisite') && is_multisite() && function_exists('get_site_option')) {
        $registration = get_site_option('registration', 'none');
        return in_array($registration, ['user', 'all'], true);
    }

    if (function_exists('get_option')) {
        return (bool) get_option('users_can_register');
    }

    return true;
}

/**
 * Multisite networks that require email activation via wp-signup / wp-activate.
 */
function orabooks_multisite_uses_signup_activation() {
    if (!function_exists('is_multisite') || !is_multisite() || !function_exists('get_site_option')) {
        return false;
    }

    return in_array(get_site_option('registration', 'none'), ['user', 'all'], true);
}

/**
 * Build a unique WordPress username from an email address.
 */
function orabooks_generate_username_from_email($email) {
    $local = strstr($email, '@', true);
    $base = sanitize_user($local ?: 'user', true);
    if ($base === '') {
        $base = 'user';
    }

    if (!function_exists('username_exists')) {
        return $base;
    }

    $username = $base;
    $suffix = 1;
    while (username_exists($username)) {
        $username = $base . $suffix;
        $suffix++;
    }

    return $username;
}

/**
 * Create or queue a WordPress user using core registration APIs.
 *
 * @return int|array|WP_Error WordPress user ID, pending signup array, or error.
 */
function orabooks_create_wp_user_for_registration($email, $password, $meta = []) {
    if (!function_exists('wp_create_user')) {
        return 0;
    }

    if (!orabooks_users_can_register()) {
        return new WP_Error('registration_disabled', __('User registration is disabled on this site.', 'orabooks'));
    }

    if (function_exists('email_exists') && email_exists($email)) {
        return new WP_Error('email_exists', __('This email is already registered.', 'orabooks'));
    }

    $username = orabooks_generate_username_from_email($email);

    if (orabooks_multisite_uses_signup_activation() && function_exists('wpmu_signup_user')) {
        $signup_meta = array_merge($meta, [
            'password' => $password,
            'orabooks_signup' => 1,
        ]);

        $signup = wpmu_signup_user($username, $email, $signup_meta);
        if (is_wp_error($signup)) {
            return $signup;
        }

        return [
            'pending_signup' => true,
            'user_login' => $username,
        ];
    }

    if (function_exists('is_multisite') && is_multisite() && function_exists('wpmu_create_user')) {
        $wp_user_id = wpmu_create_user($username, $password, $email);
    } else {
        $wp_user_id = wp_create_user($username, $password, $email);
    }

    if (is_wp_error($wp_user_id)) {
        return $wp_user_id;
    }

    if (!$wp_user_id) {
        return new WP_Error('wp_user_failed', __('Could not create WordPress user.', 'orabooks'));
    }

    if (function_exists('is_multisite') && is_multisite() && function_exists('add_user_to_blog')) {
        $blog_id = get_current_blog_id();
        if (!is_user_member_of_blog($wp_user_id, $blog_id)) {
            add_user_to_blog($blog_id, $wp_user_id, 'subscriber');
        }
    }

    wp_update_user([
        'ID' => $wp_user_id,
        'display_name' => $email,
    ]);

    return (int) $wp_user_id;
}

/**
 * Resolve OraBooks user ID from a WordPress user ID or OraBooks ID.
 */
function orabooks_resolve_user_id($user_id = 0) {
    $user_id = $user_id ?: orabooks_get_current_user_id();
    if (!$user_id) {
        return 0;
    }

    global $wpdb;
    $table = OraBooks_Database::table('users');
    $resolved = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE wp_user_id = %d OR id = %d ORDER BY id ASC LIMIT 1",
        $user_id,
        $user_id
    ));

    return (int) $resolved;
}

/**
 * Return the first verified OraBooks JWT payload from request sources.
 *
 * @return array<string, mixed>|null
 */
function orabooks_get_verified_jwt_payload() {
    if (!class_exists('OraBooks_Secrets')) {
        return null;
    }

    $candidates = [];
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (stripos($auth_header, 'Bearer ') === 0) {
        $candidates[] = trim(substr($auth_header, 7));
    }
    if (!empty($_COOKIE['orabooks_token'])) {
        $candidates[] = sanitize_text_field(wp_unslash($_COOKIE['orabooks_token']));
    }
    if (isset($_REQUEST['orabooks_token'])) {
        $candidates[] = sanitize_text_field(wp_unslash($_REQUEST['orabooks_token']));
    }

    foreach (array_unique(array_filter($candidates)) as $token) {
        $payload = OraBooks_Secrets::verify_jwt($token);
        if ($payload && !empty($payload['user_id'])) {
            return $payload;
        }
    }

    return null;
}

/**
 * Resolve an OraBooks user from WordPress auth or a verified OraBooks JWT.
 */
function orabooks_get_current_user_id() {
    $wp_user_id = get_current_user_id();
    if ($wp_user_id) {
        $resolved = orabooks_resolve_user_id((int) $wp_user_id);
        if ($resolved > 0) {
            return $resolved;
        }
    }

    $payload = orabooks_get_verified_jwt_payload();
    if ($payload) {
        return (int) $payload['user_id'];
    }

    return 0;
}

/**
 * Whether an OraBooks user is authenticated (WordPress session or verified JWT).
 */
function orabooks_is_user_logged_in() {
    return orabooks_get_current_user_id() > 0;
}

/**
 * Cookie lifetime for the OraBooks auth token mirror.
 */
function orabooks_get_auth_token_cookie_ttl() {
    $jwt_expiry = (int) get_option('orabooks_jwt_expiry', 3600);

    return max(300, $jwt_expiry);
}

/**
 * Persist the OraBooks JWT in an HTTP-only cookie for full-page loads.
 */
function orabooks_set_auth_token_cookie($token) {
    if (empty($token) || headers_sent()) {
        return;
    }

    $expiry = time() + orabooks_get_auth_token_cookie_ttl();
    $path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
    $domain = defined('COOKIEDOMAIN') ? COOKIEDOMAIN : '';
    setcookie('orabooks_token', $token, $expiry, $path, $domain, is_ssl(), true);
    $_COOKIE['orabooks_token'] = $token;
}

/**
 * Clear the mirrored OraBooks auth token cookie.
 */
function orabooks_clear_auth_token_cookie() {
    if (headers_sent()) {
        return;
    }

    $path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
    $domain = defined('COOKIEDOMAIN') ? COOKIEDOMAIN : '';
    setcookie('orabooks_token', '', time() - 3600, $path, $domain, is_ssl(), true);
    unset($_COOKIE['orabooks_token']);
}

/**
 * Establish a WordPress session for a linked OraBooks user.
 */
function orabooks_establish_wp_session_for_orabooks_user($orabooks_user_id, $password = '') {
    if (!function_exists('wp_set_auth_cookie')) {
        require_once ABSPATH . 'wp-includes/pluggable.php';
    }

    global $wpdb;
    $table_users = OraBooks_Database::table('users');
    $user = $wpdb->get_row($wpdb->prepare(
        "SELECT id, email, wp_user_id FROM {$table_users} WHERE id = %d",
        (int) $orabooks_user_id
    ));

    if (!$user) {
        return 0;
    }

    if (!empty($user->wp_user_id)) {
        $wp_user = get_user_by('id', (int) $user->wp_user_id);
        if ($wp_user) {
            wp_set_current_user($wp_user->ID);
            wp_set_auth_cookie($wp_user->ID, true, is_ssl());
            do_action('wp_login', $wp_user->user_login, $wp_user);

            return (int) $wp_user->ID;
        }
    }

    if ($password === '' || !function_exists('wp_signon')) {
        return 0;
    }

    $signon_attempts = [$user->email];
    if (function_exists('orabooks_generate_username_from_email')) {
        $signon_attempts[] = orabooks_generate_username_from_email($user->email);
    }

    foreach (array_unique(array_filter($signon_attempts)) as $login_name) {
        $signed_on = wp_signon([
            'user_login'    => $login_name,
            'user_password' => $password,
            'remember'      => true,
        ], is_ssl());

        if (!is_wp_error($signed_on)) {
            if (empty($user->wp_user_id)) {
                $wpdb->update(
                    $table_users,
                    ['wp_user_id' => $signed_on->ID],
                    ['id' => $user->id],
                    ['%d'],
                    ['%d']
                );
            }

            return (int) $signed_on->ID;
        }
    }

    return 0;
}

/**
 * Mirror JWT auth into cookie + WordPress session after login flows.
 */
function orabooks_persist_login_session($login_result, $password = '') {
    if (!is_array($login_result)) {
        return;
    }

    if (!empty($login_result['token'])) {
        orabooks_set_auth_token_cookie($login_result['token']);
    }

    $user_id = !empty($login_result['user_id']) ? (int) $login_result['user_id'] : 0;
    if ($user_id > 0) {
        orabooks_establish_wp_session_for_orabooks_user($user_id, $password);
    }
}

/**
 * Sync WordPress auth when a valid OraBooks JWT cookie is present.
 */
function orabooks_sync_wp_session_from_auth_token() {
    if (is_user_logged_in()) {
        return;
    }

    $orabooks_user_id = orabooks_get_current_user_id();
    if ($orabooks_user_id > 0) {
        orabooks_establish_wp_session_for_orabooks_user($orabooks_user_id);
    }
}

add_action('init', 'orabooks_sync_wp_session_from_auth_token', 1);

/**
 * Check rate limit
 */
function orabooks_check_rate_limit($key, $max_attempts, $period_seconds = 3600) {
    $transient_key = 'orabooks_rate_' . md5($key);
    $attempts = get_transient($transient_key);
    
    if ($attempts === false) {
        set_transient($transient_key, 1, $period_seconds);
        return true;
    }
    
    if ($attempts >= $max_attempts) {
        if (class_exists('OraBooks_Security')) {
            OraBooks_Security::record_incident('rate_limit_exceeded', 'warning', [
                'key'    => sanitize_text_field(substr($key, 0, 64)),
                'max'    => (int) $max_attempts,
                'period' => (int) $period_seconds,
            ]);
        }
        return false;
    }
    
    set_transient($transient_key, $attempts + 1, $period_seconds);
    return true;
}

/**
 * Validate input against SL-099 schema; sends 400 JSON error on failure.
 *
 * @return true Exits via orabooks_json_error on invalid input.
 */
function orabooks_validate_schema($schema_key, $value) {
    if (!class_exists('OraBooks_Security')) {
        return true;
    }

    $result = OraBooks_Security::validate_input($schema_key, $value);
    if (is_wp_error($result)) {
        orabooks_json_error(__('Invalid input format.', 'orabooks'), 400);
    }

    return true;
}

/**
 * Log audit event
 */
function orabooks_log_event($event_type, $description, $severity = 'info', $metadata = null, $user_id = null, $org_id = null) {
    if (function_exists('OraBooks_Audit') && method_exists('OraBooks_Audit', 'log_event')) {
        return OraBooks_Audit::log_event($event_type, $description, $severity, $metadata, $user_id, $org_id);
    }
    return false;
}

/**
 * Get user role in organization
 */
function orabooks_get_user_role($user_id, $org_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'orabooks_user_org';
    return $wpdb->get_var($wpdb->prepare(
        "SELECT role FROM {$table} WHERE user_id = %d AND org_id = %d",
        $user_id,
        $org_id
    ));
}

/**
 * Check if user has permission
 */
function orabooks_has_permission($user_id, $org_id, $permission) {
    $role = orabooks_get_user_role($user_id, $org_id);
    if (!$role) {
        return false;
    }
    return OraBooks_RBAC::check_permission($role, $permission, $org_id);
}

/**
 * JSON response helper
 */
function orabooks_json_response($data, $status_code = 200) {
    wp_send_json($data, $status_code);
}

/**
 * Error response helper
 */
function orabooks_json_error($message, $status_code = 400) {
    if (class_exists('OraBooks_Security') && method_exists('OraBooks_Security', 'record_http_response')) {
        OraBooks_Security::record_http_response($status_code, $message);
    }
    wp_send_json(['error' => true, 'message' => $message], $status_code);
}

/**
 * Success response helper
 */
function orabooks_json_success($data = [], $message = '') {
    wp_send_json(['error' => false, 'message' => $message, 'data' => $data]);
}

/**
 * Hash a token for storage (SHA-256)
 */
function orabooks_hash_token($token) {
    return hash('sha256', $token);
}

/**
 * Generate a UUID v4
 */
function orabooks_uuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Get user display name
 */
function orabooks_get_user_email($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'orabooks_users';
    return $wpdb->get_var($wpdb->prepare("SELECT email FROM {$table} WHERE id = %d", $user_id));
}

/**
 * Mask email for display
 */
function orabooks_mask_email($email) {
    $parts = explode('@', $email);
    $name = $parts[0];
    $domain = $parts[1] ?? '';
    $masked = substr($name, 0, 2) . str_repeat('*', max(0, strlen($name) - 2));
    return $masked . '@' . $domain;
}

/**
 * Resolve the active organization ID for the current request (SL-004).
 *
 * Prefers the org mapped to the request subdomain, then falls back to the
 * user's primary org membership.
 */
function orabooks_get_current_org_id($user_id = 0) {
    $user_id = $user_id ?: orabooks_get_current_user_id();

    if (class_exists('OraBooks_Auth') && class_exists('OraBooks_Organization')) {
        $subdomain = OraBooks_Auth::detect_subdomain_from_host();
        if ($subdomain !== '') {
            $org = OraBooks_Organization::get_by_subdomain($subdomain);
            if ($org) {
                if (!$user_id) {
                    return (int) $org->id;
                }

                if ((int) $org->owner_id === (int) $user_id) {
                    return (int) $org->id;
                }

                global $wpdb;
                $table_user_org = OraBooks_Database::table('user_org');
                $membership = $wpdb->get_var($wpdb->prepare(
                    "SELECT org_id FROM {$table_user_org} WHERE user_id = %d AND org_id = %d LIMIT 1",
                    $user_id,
                    $org->id
                ));

                if ($membership) {
                    return (int) $org->id;
                }
            }
        }
    }

    if (!$user_id) {
        return 0;
    }

    global $wpdb;
    $table_users = OraBooks_Database::table('users');
    $org_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT org_id FROM {$table_users} WHERE id = %d",
        $user_id
    ));

    if (!$org_id) {
        $table_user_org = OraBooks_Database::table('user_org');
        $org_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT org_id FROM {$table_user_org} WHERE user_id = %d ORDER BY joined_at ASC LIMIT 1",
            $user_id
        ));
    }

    return $org_id;
}

/**
 * Register an OraBooks addon module (e.g. Frontend Accounting).
 */
function orabooks_register_addon($addon) {
    if (empty($addon['id'])) {
        return false;
    }

    $addons = get_option('orabooks_addons', []);
    if (!is_array($addons)) {
        $addons = [];
    }

    $addons[$addon['id']] = $addon;
    update_option('orabooks_addons', $addons, false);

    return true;
}

/**
 * Get all registered OraBooks addons.
 */
function orabooks_get_addons() {
    $addons = get_option('orabooks_addons', []);
    return is_array($addons) ? $addons : [];
}

/**
 * Legacy compatibility helper used by addon plugins.
 */
function orabooks_is_feature_enabled($feature_id) {
    $feature_id = sanitize_key($feature_id);
    $addons = orabooks_get_addons();

    foreach ($addons as $addon) {
        if (empty($addon['features']) || !is_array($addon['features'])) {
            continue;
        }

        if (!isset($addon['features'][$feature_id])) {
            continue;
        }

        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $org_id = orabooks_get_current_org_id($user_id);
        if (!$org_id) {
            return false;
        }

        if (class_exists('OraBooks_Auth')) {
            $allowed = OraBooks_Auth::require_customer_org($user_id, $org_id);
            if (is_wp_error($allowed)) {
                return false;
            }
        }

        return true;
    }

    return false;
}
