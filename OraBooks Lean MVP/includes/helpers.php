<?php
/**
 * OraBooks Helper Functions
 */

if (!defined('ABSPATH')) {
    exit;
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
 * Resolve an OraBooks user from WordPress auth or a verified OraBooks JWT.
 */
function orabooks_get_current_user_id() {
    $wp_user_id = get_current_user_id();
    if ($wp_user_id) {
        return (int) $wp_user_id;
    }

    $token = '';
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (stripos($auth_header, 'Bearer ') === 0) {
        $token = trim(substr($auth_header, 7));
    }

    if (!$token && isset($_REQUEST['orabooks_token'])) {
        $token = sanitize_text_field(wp_unslash($_REQUEST['orabooks_token']));
    }

    if (!$token || !class_exists('OraBooks_Secrets')) {
        return 0;
    }

    $payload = OraBooks_Secrets::verify_jwt($token);
    if (!$payload || empty($payload['user_id'])) {
        return 0;
    }

    return (int) $payload['user_id'];
}

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
        return false;
    }
    
    set_transient($transient_key, $attempts + 1, $period_seconds);
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