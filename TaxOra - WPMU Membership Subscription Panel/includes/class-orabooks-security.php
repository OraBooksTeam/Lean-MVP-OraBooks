<?php
/**
 * OraBooks Security & Privacy
 * Implements security enhancements as per ORABOOKS_ULTIMATE_BUILD_GUIDE_A_TO_Z.md
 * 
 * Core Principles:
 * - PII must be minimized
 * - Access control enforced at all levels
 * - Encryption at rest and in transit
 * - Security incidents are legal incidents
 * 
 * @package OraBooks_Membership
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Security {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Encryption key
     */
    private $encryption_key;
    
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
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     * Build Guide: Security incidents are legal incidents
     */
    private function init_hooks() {
        // Enforce SSL
        add_action('init', array($this, 'enforce_ssl'));
        
        // Add security headers
        add_action('send_headers', array($this, 'add_security_headers'));
        
        // Mask PII in logs
        add_filter('orabooks_log_data', array($this, 'mask_pii_in_logs'), 10, 1);
        
        // Log security events
        add_action('wp_login_failed', array($this, 'log_failed_login'), 10, 2);
        
        // Build Guide: Multi-factor authentication for sensitive roles
        add_action('wp_login', array($this, 'check_mfa_requirement'), 10, 2);
        
        // Build Guide: Session timeout and concurrent session limits
        add_action('wp_login', array($this, 'manage_user_sessions'), 10, 2);
        
        // Build Guide: Audit logging for all security events
        add_action('orabooks_security_event', array($this, 'log_security_event'), 10, 2);
        
        // Build Guide: Data encryption at rest and in transit
        add_filter('orabooks_encrypt_data', array($this, 'encrypt'), 10, 1);
        add_filter('orabooks_decrypt_data', array($this, 'decrypt'), 10, 1);
        
        // Build Guide: PII minimization
        add_filter('orabooks_data_store', array($this, 'minimize_pii_data'), 10, 2);
        
        // Build Guide: Access control enforcement
        add_filter('orabooks_access_check', array($this, 'enforce_access_control'), 10, 3);
    }
    
    /**
     * Enforce SSL for sensitive operations
     */
    public function enforce_ssl() {
        if (!is_ssl() && !is_admin()) {
            // Check if this is a sensitive page
            $sensitive_pages = array('register', 'login', 'checkout', 'account', 'dashboard');
            $current_url = home_url($_SERVER['REQUEST_URI']);
            
            foreach ($sensitive_pages as $page) {
                if (strpos($current_url, $page) !== false) {
                    wp_redirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
                    exit;
                }
            }
        }
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        // X-Content-Type-Options
        header('X-Content-Type-Options: nosniff');
        
        // X-Frame-Options
        header('X-Frame-Options: SAMEORIGIN');
        
        // X-XSS-Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Strict-Transport-Security (only if SSL)
        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Referrer-Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions-Policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
    
    /**
     * Encrypt data
     * 
     * @param string $data Data to encrypt
     * @return string|false Encrypted data or false on failure
     */
    public function encrypt($data) {
        if (empty($data)) {
            return $data;
        }
        
        $key = $this->get_encryption_key();
        if (!$key) {
            return $data; // Return as-is if no key available
        }
        
        $method = 'AES-256-CBC';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
        
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
        
        if ($encrypted === false) {
            return false;
        }
        
        // Combine IV and encrypted data
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt data
     * 
     * @param string $data Data to decrypt
     * @return string|false Decrypted data or false on failure
     */
    public function decrypt($data) {
        if (empty($data)) {
            return $data;
        }
        
        $key = $this->get_encryption_key();
        if (!$key) {
            return $data; // Return as-is if no key available
        }
        
        $method = 'AES-256-CBC';
        $data = base64_decode($data);
        
        $iv_length = openssl_cipher_iv_length($method);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        $decrypted = openssl_decrypt($encrypted, $method, $key, 0, $iv);
        
        if ($decrypted === false) {
            return false;
        }
        
        return $decrypted;
    }
    
    /**
     * Get encryption key
     * 
     * @return string Encryption key
     */
    private function get_encryption_key() {
        if ($this->encryption_key) {
            return $this->encryption_key;
        }
        
        // Try to get from WordPress options
        $key = get_option('orabooks_encryption_key');
        
        if (!$key) {
            // Generate new key
            $key = wp_generate_password(32, true, true);
            update_option('orabooks_encryption_key', $key);
        }
        
        $this->encryption_key = $key;
        return $key;
    }
    
    /**
     * Mask PII in logs
     * 
     * @param array $data Log data
     * @return array Masked data
     */
    public function mask_pii_in_logs($data) {
        if (!is_array($data)) {
            return $data;
        }
        
        $pii_fields = array(
            'email',
            'phone',
            'credit_card',
            'ssn',
            'bank_account',
            'address',
            'password',
        );
        
        foreach ($data as $key => $value) {
            foreach ($pii_fields as $field) {
                if (stripos($key, $field) !== false) {
                    $data[$key] = $this->mask_value($value);
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Mask a value (show first 2 and last 2 characters)
     * 
     * @param string $value Value to mask
     * @return string Masked value
     */
    private function mask_value($value) {
        if (empty($value) || !is_string($value)) {
            return $value;
        }
        
        $length = strlen($value);
        
        if ($length <= 4) {
            return str_repeat('*', $length);
        }
        
        $start = substr($value, 0, 2);
        $end = substr($value, -2);
        $middle = str_repeat('*', $length - 4);
        
        return $start . $middle . $end;
    }
    
    /**
     * Sanitize input to prevent XSS
     * 
     * @param string $input Input to sanitize
     * @return string Sanitized input
     */
    public function sanitize_input($input) {
        if (is_array($input)) {
            return array_map(array($this, 'sanitize_input'), $input);
        }
        
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email address
     * 
     * @param string $email Email to validate
     * @return bool Is valid
     */
    public function validate_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Log failed login attempt
     * 
     * @param string $username Username
     * @param WP_Error $error Error object
     */
    public function log_failed_login($username, $error) {
        if (class_exists('OraBooks_Audit_Logger')) {
            $logger = OraBooks_Audit_Logger::get_instance();
            $logger->log_action(array(
                'action_type' => 'login_failed',
                'action_description' => sprintf('Failed login attempt for username: %s', $username),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ));
        }
    }
    
    /**
     * Check for brute force attempts
     * 
     * @param string $identifier User identifier (email or IP)
     * @return bool Is blocked
     */
    public function check_brute_force($identifier) {
        $transient_name = 'orabooks_failed_login_' . md5($identifier);
        $attempts = get_transient($transient_name);
        
        if ($attempts === false) {
            return false;
        }
        
        // Block after 5 failed attempts
        if ($attempts >= 5) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Record failed attempt
     * 
     * @param string $identifier User identifier
     */
    public function record_failed_attempt($identifier) {
        $transient_name = 'orabooks_failed_login_' . md5($identifier);
        $attempts = get_transient($transient_name);
        
        if ($attempts === false) {
            $attempts = 0;
        }
        
        $attempts++;
        
        // Set transient for 15 minutes
        set_transient($transient_name, $attempts, 15 * MINUTE_IN_SECONDS);
    }
    
    /**
     * Get client IP address
     * 
     * @return string IP address
     */
    private function get_client_ip() {
        $ip = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
    
    /**
     * Generate secure random token
     * 
     * @param int $length Token length
     * @return string Random token
     */
    public function generate_token($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Verify nonce with additional security checks
     * 
     * @param string $nonce Nonce to verify
     * @param string $action Action context
     * @return bool Is valid
     */
    public function verify_nonce($nonce, $action, $single_use = true) {
        if (!wp_verify_nonce($nonce, $action)) {
            return false;
        }

        // Payment/checkout nonces must remain valid for retries within the WP nonce lifetime.
        if (!$single_use || $action === 'orabooks_payment_nonce' || $action === 'orabooks_free_checkout') {
            return true;
        }
        
        // Check for nonce reuse (timing attack protection)
        $used_nonces = get_transient('orabooks_used_nonces');
        if (!$used_nonces) {
            $used_nonces = array();
        }
        
        if (in_array($nonce, $used_nonces)) {
            return false; // Nonce already used
        }
        
        // Add to used nonces (expire after 12 hours)
        $used_nonces[] = $nonce;
        set_transient('orabooks_used_nonces', $used_nonces, 12 * HOUR_IN_SECONDS);
        
        return true;
    }
    
    /**
     * Hash password securely
     * 
     * @param string $password Password to hash
     * @return string Hashed password
     */
    public function hash_password($password) {
        return wp_hash_password($password);
    }
    
    /**
     * Verify password
     * 
     * @param string $password Password to verify
     * @param string $hash Hash to verify against
     * @return bool Is valid
     */
    public function verify_password($password, $hash) {
        return wp_check_password($password, $hash);
    }
    
    /**
     * Check MFA requirement for sensitive roles
     * Build Guide: Multi-factor authentication for sensitive roles
     * 
     * @param string $user_login Username
     * @param WP_User $user User object
     */
    public function check_mfa_requirement($user_login, $user) {
        // Skip MFA for superadmins (network administrators)
        if (user_can($user, 'manage_network')) {
            return;
        }
        
        $sensitive_roles = ['administrator', 'company_owner', 'manager'];
        
        // Check if user has sensitive role
        if (array_intersect($user->roles, $sensitive_roles)) {
            // Check if MFA is already verified
            if (OraBooks_Session::get_instance()->get('orabooks_mfa_verified') !== true) {
                // Check if MFA verification page exists
                if (current_user_can('manage_options')) {
                    // Try to redirect to MFA verification page
                    $mfa_url = admin_url('admin.php?page=orabooks-mfa-verify');
                    
                    // Check if the page exists before redirecting
                    global $submenu;
                    $page_exists = false;
                    if (isset($submenu['orabooks-membership'])) {
                        foreach ($submenu['orabooks-membership'] as $menu_item) {
                            if ($menu_item[2] === 'orabooks-mfa-verify') {
                                $page_exists = true;
                                break;
                            }
                        }
                    }
                    
                    if ($page_exists) {
                        wp_redirect($mfa_url);
                        exit;
                    }
                }
                
                // If MFA page doesn't exist or user doesn't have permission, skip MFA for now
                // Log this for future MFA implementation
                error_log('[OraBooks Security] MFA verification page not available, skipping MFA for user: ' . $user->ID);
            }
        }
    }
    
    /**
     * Manage user sessions
     * Build Guide: Session timeout and concurrent session limits
     * 
     * @param string $user_login Username
     * @param WP_User $user User object
     */
    public function manage_user_sessions($user_login, $user) {
        $user_id = $user->ID;
        
        // Check for concurrent sessions
        $active_sessions = get_user_meta($user_id, 'orabooks_active_sessions', true);
        if (!$active_sessions) {
            $active_sessions = [];
        }
        
        $session_id = session_id();
        $current_time = current_time('timestamp');
        
        // Clean up expired sessions (older than 2 hours)
        foreach ($active_sessions as $sid => $data) {
            if ($current_time - $data['last_activity'] > 2 * HOUR_IN_SECONDS) {
                unset($active_sessions[$sid]);
            }
        }
        
        // Add current session
        $active_sessions[$session_id] = [
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'login_time' => $current_time,
            'last_activity' => $current_time
        ];
        
        // Limit concurrent sessions (max 3 per user)
        if (count($active_sessions) > 3) {
            // Remove oldest session
            $oldest = min(array_column($active_sessions, 'login_time'));
            foreach ($active_sessions as $sid => $data) {
                if ($data['login_time'] === $oldest) {
                    unset($active_sessions[$sid]);
                    break;
                }
            }
        }
        
        update_user_meta($user_id, 'orabooks_active_sessions', $active_sessions);
    }
    
    /**
     * Log security event
     * Build Guide: Audit logging for all security events
     * 
     * @param string $event_type Event type
     * @param array $event_data Event data
     */
    public function log_security_event($event_type, $event_data) {
        $log_entry = [
            'event_type' => $event_type,
            'timestamp' => current_time('mysql'),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'user_id' => get_current_user_id(),
            'event_data' => serialize($event_data),
            'severity' => $this->get_event_severity($event_type)
        ];
        
        // Store in database
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'orabooks_security_log',
            $log_entry
        );
        
        // Log to error log for immediate visibility
        error_log(sprintf(
            '[OraBooks Security Event] Type: %s, User: %d, IP: %s, Severity: %s',
            $event_type,
            $log_entry['user_id'],
            $log_entry['ip_address'],
            $log_entry['severity']
        ));
        
        // Check for security incident escalation
        $this->check_security_incident($event_type, $event_data);
    }
    
    /**
     * Minimize PII data
     * Build Guide: PII must be minimized
     * 
     * @param array $data Data to store
     * @param string $context Context of data storage
     * @return array Minimized data
     */
    public function minimize_pii_data($data, $context) {
        if (!is_array($data)) {
            return $data;
        }
        
        // Define PII fields to remove or mask based on context
        $pii_fields = [
            'user_registration' => ['ssn', 'credit_card', 'bank_account'],
            'transaction' => ['full_credit_card', 'bank_routing'],
            'report' => ['email', 'phone', 'address']
        ];
        
        $fields_to_remove = isset($pii_fields[$context]) ? $pii_fields[$context] : [];
        
        foreach ($data as $key => $value) {
            foreach ($fields_to_remove as $field) {
                if (stripos($key, $field) !== false) {
                    // Remove or mask the field
                    $data[$key] = $this->mask_value($value);
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Enforce access control
     * Build Guide: Access control enforced at all levels
     * 
     * @param bool $access Current access status
     * @param string $resource Resource being accessed
     * @param int $user_id User ID
     * @return bool Whether access is allowed
     */
    public function enforce_access_control($access, $resource, $user_id) {
        if (!$access) {
            return $access;
        }
        
        // Check if user is locked out
        if ($this->is_user_locked_out($user_id)) {
            $this->log_security_event('access_denied_locked_out', [
                'user_id' => $user_id,
                'resource' => $resource
            ]);
            return false;
        }
        
        // Check IP restrictions
        if (!$this->is_ip_allowed($this->get_client_ip())) {
            $this->log_security_event('access_denied_ip_restricted', [
                'user_id' => $user_id,
                'ip_address' => $this->get_client_ip(),
                'resource' => $resource
            ]);
            return false;
        }
        
        // Check time-based restrictions
        if (!$this->is_time_allowed()) {
            $this->log_security_event('access_denied_time_restricted', [
                'user_id' => $user_id,
                'resource' => $resource,
                'time' => current_time('mysql')
            ]);
            return false;
        }
        
        return $access;
    }
    
    /**
     * Get event severity
     * 
     * @param string $event_type Event type
     * @return string Severity level
     */
    private function get_event_severity($event_type) {
        $severity_map = [
            'login_failed' => 'medium',
            'cross_mode_access_attempt' => 'high',
            'access_denied_locked_out' => 'high',
            'access_denied_ip_restricted' => 'high',
            'mfa_failed' => 'high',
            'data_breach_attempt' => 'critical',
            'privilege_escalation_attempt' => 'critical',
            'suspicious_activity' => 'high'
        ];
        
        return isset($severity_map[$event_type]) ? $severity_map[$event_type] : 'low';
    }
    
    /**
     * Check for security incident escalation
     * 
     * @param string $event_type Event type
     * @param array $event_data Event data
     */
    private function check_security_incident($event_type, $event_data) {
        $severity = $this->get_event_severity($event_type);
        
        if ($severity === 'critical' || $severity === 'high') {
            // Immediate notification for critical incidents
            $this->send_security_alert($event_type, $event_data, $severity);
            
            // Consider automatic lockout for critical incidents
            if ($severity === 'critical') {
                $user_id = get_current_user_id();
                if ($user_id) {
                    $this->lock_user_account($user_id, 30 * MINUTE_IN_SECONDS);
                }
            }
        }
    }
    
    /**
     * Send security alert
     * 
     * @param string $event_type Event type
     * @param array $event_data Event data
     * @param string $severity Severity level
     */
    private function send_security_alert($event_type, $event_data, $severity) {
        $admin_email = get_option('admin_email');
        $subject = sprintf('[OraBooks Security Alert] %s - %s', strtoupper($severity), $event_type);
        
        $message = sprintf(
            "Security Event Detected:\n\n" .
            "Type: %s\n" .
            "Severity: %s\n" .
            "Time: %s\n" .
            "IP Address: %s\n" .
            "User ID: %d\n\n" .
            "Event Data:\n%s\n\n" .
            "This is an automated security alert from OraBooks.",
            $event_type,
            $severity,
            current_time('mysql'),
            $this->get_client_ip(),
            get_current_user_id(),
            print_r($event_data, true)
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Check if user is locked out
     * 
     * @param int $user_id User ID
     * @return bool Is locked out
     */
    private function is_user_locked_out($user_id) {
        $lockout_until = get_user_meta($user_id, 'orabooks_locked_out_until', true);
        
        if (!$lockout_until) {
            return false;
        }
        
        return current_time('timestamp') < $lockout_until;
    }
    
    /**
     * Lock user account
     * 
     * @param int $user_id User ID
     * @param int $duration Lockout duration in seconds
     */
    public function lock_user_account($user_id, $duration = 900) {
        $lockout_until = current_time('timestamp') + $duration;
        update_user_meta($user_id, 'orabooks_locked_out_until', $lockout_until);
        
        $this->log_security_event('account_locked', [
            'user_id' => $user_id,
            'duration' => $duration,
            'locked_until' => $lockout_until
        ]);
    }
    
    /**
     * Check if IP is allowed
     * 
     * @param string $ip IP address
     * @return bool Is allowed
     */
    private function is_ip_allowed($ip) {
        // Get allowed IP ranges from settings
        $allowed_ips = get_option('orabooks_allowed_ips', []);
        
        if (empty($allowed_ips)) {
            return true; // No restrictions if no allowed IPs set
        }
        
        foreach ($allowed_ips as $allowed_ip) {
            if ($this->ip_in_range($ip, $allowed_ip)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if current time is allowed
     * 
     * @return bool Is allowed
     */
    private function is_time_allowed() {
        $time_restrictions = get_option('orabooks_time_restrictions', []);
        
        if (empty($time_restrictions)) {
            return true; // No restrictions if none set
        }
        
        $current_hour = (int)date('H');
        $current_day = date('N'); // 1-7 (Monday-Sunday)
        
        // Check if current time is in allowed window
        if (isset($time_restrictions[$current_day])) {
            $allowed_hours = $time_restrictions[$current_day];
            return in_array($current_hour, $allowed_hours);
        }
        
        return false;
    }
    
    /**
     * Check if IP is in range
     * 
     * @param string $ip IP to check
     * @param string $range IP range (CIDR notation)
     * @return bool Is in range
     */
    private function ip_in_range($ip, $range) {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        list($range_ip, $netmask) = explode('/', $range);
        $range_decimal = ip2long($range_ip);
        $ip_decimal = ip2long($ip);
        $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
        $netmask_decimal = ~$wildcard_decimal;
        
        return ($ip_decimal & $netmask_decimal) === ($range_decimal & $netmask_decimal);
    }
    
    /**
     * Generate secure session token
     * 
     * @return string Session token
     */
    public function generate_session_token() {
        return $this->generate_token(64);
    }
    
    /**
     * Validate session token
     * 
     * @param string $token Token to validate
     * @param int $user_id User ID
     * @return bool Is valid
     */
    public function validate_session_token($token, $user_id) {
        $stored_token = get_user_meta($user_id, 'orabooks_session_token', true);
        
        if (!$stored_token || $stored_token !== $token) {
            return false;
        }
        
        // Check token age (max 24 hours)
        $token_time = get_user_meta($user_id, 'orabooks_session_token_time', true);
        if (!$token_time || (current_time('timestamp') - $token_time) > 24 * HOUR_IN_SECONDS) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Set session token
     * 
     * @param int $user_id User ID
     * @return string Session token
     */
    public function set_session_token($user_id) {
        $token = $this->generate_session_token();
        
        update_user_meta($user_id, 'orabooks_session_token', $token);
        update_user_meta($user_id, 'orabooks_session_token_time', current_time('timestamp'));
        
        return $token;
    }
}

// Initialize the security system
OraBooks_Security::get_instance();
