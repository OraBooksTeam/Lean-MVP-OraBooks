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
    private static $access_logged = [];
    private static $bootstrapped = false;

    /** Grace period after JWT secret rotation (seconds). */
    const JWT_ROTATION_GRACE_SECONDS = 86400;
    const MIN_JWT_SECRET_LENGTH = 32;
    const MIN_ENCRYPTION_KEY_LENGTH = 32;
    const TLS_EXPIRY_WARN_DAYS = 30;
    
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            self::bootstrap();
            add_action('template_redirect', [self::class, 'maybe_enforce_https'], 1);
            add_action('admin_init', [self::class, 'maybe_enforce_https'], 1);
        }
        return self::$instance;
    }

    /**
     * Load secrets, migrate legacy options, and ensure required keys exist (SL-008).
     */
    public static function bootstrap() {
        if (self::$bootstrapped) {
            return true;
        }

        self::$bootstrapped = true;
        self::migrate_legacy_secrets();

        $jwt = self::get_jwt_secret();
        $encryption = self::get_encryption_key();

        if (self::is_production()) {
            $issues = [];
            if (strlen((string) $jwt) < self::MIN_JWT_SECRET_LENGTH) {
                $issues[] = 'jwt_secret too short';
            }
            if (strlen((string) $encryption) < self::MIN_ENCRYPTION_KEY_LENGTH) {
                $issues[] = 'encryption_key too short';
            }
            if (!empty($issues) && function_exists('orabooks_log_event')) {
                orabooks_log_event('secrets_bootstrap_failed', 'Required secrets failed validation', 'critical', [
                    'issues' => $issues,
                ]);
            }
            if (!empty($issues)) {
                return new WP_Error('secrets_invalid', 'Required secrets are missing or too short for production.');
            }
        }

        if (!get_option('orabooks_installed_at')) {
            update_option('orabooks_installed_at', current_time('mysql', true));
        }

        return true;
    }

    /**
     * Whether the deployment should enforce production-grade TLS/secrets rules.
     */
    public static function is_production() {
        if (defined('ORABOOKS_ENV') && ORABOOKS_ENV === 'production') {
            return true;
        }
        if (defined('WP_ENV') && WP_ENV === 'production') {
            return true;
        }

        return (bool) apply_filters(
            'orabooks_is_production',
            !defined('WP_DEBUG') || !WP_DEBUG
        );
    }

    /**
     * Redirect HTTP to HTTPS in production (SL-008 §5.3).
     */
    public static function maybe_enforce_https() {
        if (is_ssl() || !self::requires_tls()) {
            return;
        }

        if (defined('WP_CLI') && WP_CLI) {
            return;
        }
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '' || in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)) {
            return;
        }

        $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
        $target = 'https://' . $host . $request_uri;

        if (function_exists('orabooks_log_event')) {
            orabooks_log_event('tls_http_redirect', 'Redirected insecure HTTP request to HTTPS', 'info', [
                'host' => $host,
            ]);
        }

        wp_safe_redirect($target, 301);
        exit;
    }

    public static function requires_tls() {
        return (bool) apply_filters('orabooks_require_tls', self::is_production());
    }

    /**
     * Migrate plaintext legacy options into encrypted secret storage.
     */
    private static function migrate_legacy_secrets() {
        $legacy_jwt = self::with_shared_options(function () {
            return get_option('orabooks_jwt_secret', '');
        });

        if ($legacy_jwt && !self::load_secret('jwt_secret')) {
            self::set('jwt_secret', $legacy_jwt);
        }
    }

    /**
     * Mask a secret for logs and API output (SL-008 §5.6).
     */
    public static function mask_value($value) {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        if (strlen($value) <= 8) {
            return '****';
        }

        return substr($value, 0, 4) . '…' . substr($value, -4);
    }

    /**
     * Recursively redact sensitive keys from arrays before logging.
     *
     * @param mixed $data
     * @return mixed
     */
    public static function redact_sensitive($data) {
        if (!is_array($data)) {
            return $data;
        }

        $sensitive = [
            'password', 'token', 'secret', 'authorization', 'api_key', 'apikey',
            'jwt', 'refresh_token', 'client_secret', 'encryption_key', 'private_key',
            'backup_code', 'totp', 'credit_card', 'ssn',
        ];

        $redacted = [];
        foreach ($data as $key => $value) {
            $key_lower = strtolower((string) $key);
            $should_mask = false;
            foreach ($sensitive as $needle) {
                if (strpos($key_lower, $needle) !== false) {
                    $should_mask = true;
                    break;
                }
            }

            if ($should_mask) {
                $redacted[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $redacted[$key] = self::redact_sensitive($value);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    private static function log_secret_access($key) {
        if (isset(self::$access_logged[$key])) {
            return;
        }
        self::$access_logged[$key] = true;

        if (function_exists('orabooks_log_event')) {
            orabooks_log_event('secret_accessed', 'Secret retrieved from secure storage', 'info', [
                'secret_key' => sanitize_key((string) $key),
            ]);
        }
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

        if ($value !== null && $value !== '') {
            self::log_secret_access($key);
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
        $stored = self::with_shared_options(function () use ($option_key) {
            return get_option($option_key, false);
        });
        if ($stored) {
            return self::decrypt($stored);
        }
        
        return null;
    }

    /**
     * Read/write options on the main network site so JWT secrets match every tenant blog.
     *
     * @return mixed
     */
    private static function with_shared_options(callable $callback) {
        if (function_exists('orabooks_with_data_blog')) {
            return orabooks_with_data_blog($callback);
        }

        return $callback();
    }
    
    /**
     * Store a secret
     */
    public static function set($key, $value) {
        $option_key = 'orabooks_secret_' . md5($key);
        $encrypted = self::encrypt($value);
        self::with_shared_options(function () use ($option_key, $encrypted) {
            update_option($option_key, $encrypted);
        });
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
     * Encrypt sensitive at-rest values (2FA secrets, etc.).
     */
    public static function encrypt_sensitive($plaintext) {
        if ($plaintext === '' || $plaintext === null) {
            return '';
        }

        return 'enc:' . self::encrypt((string) $plaintext);
    }

    /**
     * Decrypt sensitive at-rest values; returns legacy plaintext unchanged.
     */
    public static function decrypt_sensitive($stored) {
        if ($stored === '' || $stored === null) {
            return '';
        }

        $stored = (string) $stored;
        if (strpos($stored, 'enc:') !== 0) {
            return $stored;
        }

        $decrypted = self::decrypt(substr($stored, 4));
        return $decrypted !== false ? $decrypted : '';
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
        $jwt_expiry = self::with_shared_options(function () {
            return (int) get_option('orabooks_jwt_expiry', 3600);
        });
        $payload['exp'] = time() + max(300, $jwt_expiry);
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

    /**
     * Verify a Google OIDC id_token (RS256 + JWKS) and return claims.
     *
     * @param string $id_token
     * @param string $client_id
     * @return array<string, mixed>|false
     */
    public static function verify_google_id_token($id_token, $client_id) {
        $parts = explode('.', (string) $id_token);
        if (count($parts) !== 3) {
            return false;
        }

        $header = json_decode(self::base64url_decode($parts[0]), true);
        $payload = json_decode(self::base64url_decode($parts[1]), true);
        if (!is_array($header) || !is_array($payload)) {
            return false;
        }

        if (($header['alg'] ?? '') !== 'RS256') {
            return false;
        }

        $public_key = self::get_google_oidc_public_key((string) ($header['kid'] ?? ''));
        if (!$public_key) {
            return false;
        }

        $signed = $parts[0] . '.' . $parts[1];
        $signature = self::base64url_decode($parts[2]);
        if ($signature === false || openssl_verify($signed, $signature, $public_key, OPENSSL_ALGO_SHA256) !== 1) {
            return false;
        }

        $issuer = (string) ($payload['iss'] ?? '');
        if (!in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            return false;
        }

        if ((string) ($payload['aud'] ?? '') !== (string) $client_id) {
            return false;
        }

        if (empty($payload['exp']) || (int) $payload['exp'] < time()) {
            return false;
        }

        if (empty($payload['email'])) {
            return false;
        }

        return $payload;
    }

    /**
     * Resolve Google's RSA public key for a JWKS key id.
     *
     * @param string $kid
     * @return resource|OpenSSLAsymmetricKey|false
     */
    private static function get_google_oidc_public_key($kid) {
        if ($kid === '') {
            return false;
        }

        $cache_key = 'orabooks_google_jwk_' . md5($kid);
        $cached_pem = get_transient($cache_key);
        if (is_string($cached_pem) && $cached_pem !== '') {
            return openssl_pkey_get_public($cached_pem);
        }

        $response = wp_remote_get('https://www.googleapis.com/oauth2/v3/certs', [
            'timeout' => 10,
        ]);
        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['keys']) || !is_array($body['keys'])) {
            return false;
        }

        foreach ($body['keys'] as $jwk) {
            if (!is_array($jwk) || (string) ($jwk['kid'] ?? '') !== $kid) {
                continue;
            }

            $pem = self::google_jwk_to_pem($jwk);
            if (!$pem) {
                return false;
            }

            set_transient($cache_key, $pem, HOUR_IN_SECONDS);
            return openssl_pkey_get_public($pem);
        }

        return false;
    }

    /**
     * Convert a Google RSA JWK to PEM for openssl_verify().
     *
     * @param array<string, mixed> $jwk
     */
    private static function google_jwk_to_pem(array $jwk) {
        if (($jwk['kty'] ?? '') !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) {
            return false;
        }

        $modulus = self::base64url_decode((string) $jwk['n']);
        $exponent = self::base64url_decode((string) $jwk['e']);
        if ($modulus === false || $exponent === false) {
            return false;
        }

        $rsa_sequence = self::encode_asn1_sequence(
            self::encode_asn1_integer($modulus) . self::encode_asn1_integer($exponent)
        );
        $bit_string = "\x00" . $rsa_sequence;
        $bit_string_encoded = "\x03" . self::encode_asn1_length(strlen($bit_string)) . $bit_string;
        $oid = hex2bin('300d06092a864886f70d0101010500');
        $public_key_info = self::encode_asn1_sequence($oid . $bit_string_encoded);
        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($public_key_info), 64, "\n")
            . "-----END PUBLIC KEY-----\n";

        return $pem;
    }

    private static function encode_asn1_length($length) {
        if ($length < 0x80) {
            return chr($length);
        }

        $temp = ltrim(pack('N', $length), "\x00");
        return chr(0x80 | strlen($temp)) . $temp;
    }

    private static function encode_asn1_integer($data) {
        if ($data === '') {
            $data = "\x00";
        } elseif ($data[0] === "\x00" || (ord($data[0]) & 0x80)) {
            $data = "\x00" . $data;
        }

        return "\x02" . self::encode_asn1_length(strlen($data)) . $data;
    }

    private static function encode_asn1_sequence($data) {
        return "\x30" . self::encode_asn1_length(strlen($data)) . $data;
    }
}