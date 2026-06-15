<?php
/**
 * OraBooks Secrets & TLS Management (SL-008)
 * 
 * Handles secure storage and retrieval of secrets, encryption keys,
 * and TLS configuration. No secrets are hardcoded.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Secrets {
    
    private static $instance = null;
    private static $secrets_cache = [];
    
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get a secret value from secure storage
     * Falls back to WordPress options (encrypted in production)
     */
    public static function get($key, $default = null) {
        if (isset(self::$secrets_cache[$key])) {
            return self::$secrets_cache[$key];
        }
        
        $value = self::load_secret($key);
        if ($value === null) {
            $value = $default;
        }
        
        self::$secrets_cache[$key] = $value;
        return $value;
    }
    
    /**
     * Load secret from environment or WordPress options
     */
    private static function load_secret($key) {
        $env_key = 'ORABOOKS_' . strtoupper($key);
        $env_value = getenv($env_key);
        if ($env_value !== false && $env_value !== '') {
            return $env_value;
        }
        
        $option_key = 'orabooks_secret_' . md5($key);
        $stored = get_option($option_key);
        if ($stored) {
            return self::decrypt($stored);
        }
        
        return null;
    }
    
    /**
     * Store a secret
     */
    public static function set($key, $value) {
        $option_key = 'orabooks_secret_' . md5($key);
        $encrypted = self::encrypt($value);
        update_option($option_key, $encrypted);
        self::$secrets_cache[$key] = $value;
    }
    
    /**
     * Simple encryption for stored secrets (use in production: vault/KMS)
     */
    private static function encrypt($data) {
        $method = 'aes-256-cbc';
        $key = defined('LOGGED_IN_KEY') ? LOGGED_IN_KEY : wp_salt('logged_in');
        $iv = substr(hash('sha256', $key . '_iv'), 0, 16);
        return base64_encode(openssl_encrypt($data, $method, $key, 0, $iv));
    }
    
    /**
     * Decrypt stored secret
     */
    private static function decrypt($data) {
        $method = 'aes-256-cbc';
        $key = defined('LOGGED_IN_KEY') ? LOGGED_IN_KEY : wp_salt('logged_in');
        $iv = substr(hash('sha256', $key . '_iv'), 0, 16);
        return openssl_decrypt(base64_decode($data), $method, $key, 0, $iv);
    }
    
    /**
     * Get JWT secret key
     */
    public static function get_jwt_secret() {
        $secret = self::get('jwt_secret');
        if (!$secret) {
            $secret = wp_generate_password(64, true, true);
            self::set('jwt_secret', $secret);
        }
        return $secret;
    }
    
    /**
     * Get encryption key for sensitive data (2FA, backup codes)
     */
    public static function get_encryption_key() {
        $key = self::get('encryption_key');
        if (!$key) {
            $key = wp_generate_password(32, true, true);
            self::set('encryption_key', $key);
        }
        return $key;
    }
    
    /**
     * Generate a JWT token
     */
    public static function generate_jwt($payload) {
        $secret = self::get_jwt_secret();
        $header = self::base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['iat'] = time();
        $payload['exp'] = time() + (int) get_option('orabooks_jwt_expiry', 900);
        $payload_encoded = self::base64url_encode(json_encode($payload));
        $signature = self::base64url_encode(
            hash_hmac('sha256', "$header.$payload_encoded", $secret, true)
        );
        return "$header.$payload_encoded.$signature";
    }
    
    /**
     * Verify and decode a JWT token
     */
    public static function verify_jwt($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        $secret = self::get_jwt_secret();
        $header = $parts[0];
        $payload = $parts[1];
        $signature = $parts[2];
        
        $expected = self::base64url_encode(
            hash_hmac('sha256', "$header.$payload", $secret, true)
        );
        
        if (!hash_equals($expected, $signature)) {
            return false;
        }
        
        $data = json_decode(self::base64url_decode($payload), true);
        if (!$data || !isset($data['exp']) || $data['exp'] < time()) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * Base64URL encode
     */
    private static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64URL decode
     */
    private static function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    /**
     * Hash a password with bcrypt
     */
    public static function hash_password($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }
    
    /**
     * Verify a password against hash
     */
    public static function verify_password($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate TOTP secret for 2FA
     */
    public static function generate_totp_secret() {
        return bin2hex(random_bytes(20));
    }
    
    /**
     * Generate QR code URL for TOTP setup
     */
    public static function get_totp_qr_url($secret, $email) {
        $issuer = 'OraBooks';
        $encoded = urlencode("otpauth://totp/$issuer:$email?secret=$secret&issuer=$issuer");
        return "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=$encoded";
    }
    
    /**
     * Verify TOTP code (+/- 30 sec drift)
     */
    public static function verify_totp($secret, $code) {
        // For MVP: simplified check using time-based OTP
        // Production: use dedicated TOTP library
        $time_slice = floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            $expected = self::generate_totp_code($secret, $time_slice + $i);
            if (hash_equals($expected, $code)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Generate TOTP code (RFC 6238 simplified)
     */
    private static function generate_totp_code($secret, $time_slice) {
        $key = pack('H*', $secret);
        $counter = pack('J', $time_slice);
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Generate backup codes (8 codes)
     */
    public static function generate_backup_codes() {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }
        return $codes;
    }
}