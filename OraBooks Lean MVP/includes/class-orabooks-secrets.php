<?php
/**
 * OraBooks Secrets & TLS Management
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
 /** @var WP_Error|null */
 private static $bootstrap_error = null;
 private static $file_secrets = null;

 /** Grace period after JWT secret rotation (seconds). */
 const JWT_ROTATION_GRACE_SECONDS = 86400;
 const MIN_JWT_SECRET_LENGTH = 32;
 const MIN_ENCRYPTION_KEY_LENGTH = 32;
 const TLS_EXPIRY_WARN_DAYS = 30;
 const DEFAULT_JWT_EXPIRY_SECONDS = 900;

 public static function init() {
 if (self::$instance === null) {
 self::$instance = new self;
 $boot = self::bootstrap;
 if (is_wp_error($boot)) {
 if (!(self::$bootstrap_error instanceof WP_Error)) {
 self::$bootstrap_error = $boot;
 }
 self::register_failure_handlers;
 } else {
 add_action('template_redirect', [self::class, 'maybe_enforce_https'], 1);
 add_action('admin_init', [self::class, 'maybe_enforce_https'], 1);
 }
 }
 return self::$instance;
 }

 /**
 * Whether bootstrap completed without blocking errors.
 */
 public static function is_ready() {
 return self::$bootstrap_error === null;
 }

 /**
 * @return WP_Error|null
 */
 public static function get_bootstrap_error() {
 return self::$bootstrap_error;
 }

 /**
 * Default JWT access-token lifetime (: 15 minutes).
 */
 public static function get_default_jwt_expiry() {
 return (int) apply_filters('orabooks_default_jwt_expiry', self::DEFAULT_JWT_EXPIRY_SECONDS);
 }

 /**
 * Shared HMAC signing material for internal integrity proofs ( §5.6).
 */
 public static function get_hmac_signing_key() {
 return (string) self::get_jwt_secret;
 }

 /**
 * Clear cached secrets after rotation or external secret-manager reload.
 */
 public static function clear_secrets_cache($key = null) {
 if ($key === null) {
 self::$secrets_cache = [];
 self::$access_logged = [];
 self::$file_secrets = null;
 return;
 }

 unset(self::$secrets_cache[$key], self::$access_logged[$key]);
 }

 /**
 * Load secrets, migrate legacy options, and ensure required keys exist.
 */
 public static function bootstrap() {
 if (self::$bootstrapped) {
 return self::$bootstrap_error instanceof WP_Error ? self::$bootstrap_error: true;
 }

 self::$bootstrapped = true;
 self::migrate_legacy_secrets;

 $jwt = self::get_jwt_secret;
 $encryption = self::get_encryption_key;

 if (self::is_production) {
 $issues = [];
 if (strlen((string) $jwt) < self::MIN_JWT_SECRET_LENGTH) {
 $issues[] = 'jwt_secret too short';
 }
 if (strlen((string) $encryption) < self::MIN_ENCRYPTION_KEY_LENGTH) {
 $issues[] = 'encryption_key too short';
 }

 $db_tls = self::check_database_tls;
 if (empty($db_tls['ok']) && empty($db_tls['skipped'])) {
 $issues[] = 'database connection is not using TLS';
 }

 if (!empty($issues) && function_exists('orabooks_log_event')) {
 orabooks_log_event('secrets_bootstrap_failed', 'Required secrets failed validation', 'critical', [
 'issues' => $issues,
 ]);
 }
 if (!empty($issues)) {
 self::$bootstrap_error = new WP_Error(
 'secrets_invalid',
 'Required secrets or TLS configuration failed validation for production.',
 ['issues' => $issues]
 );
 return self::$bootstrap_error;
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
 * Redirect HTTP to HTTPS in production ( §5.3).
 */
 public static function maybe_enforce_https() {
 if (is_ssl || !self::requires_tls) {
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
 $target = 'https://'. $host. $request_uri;

 if (function_exists('orabooks_log_event')) {
 orabooks_log_event('tls_http_redirect', 'Redirected insecure HTTP request to HTTPS', 'info', [
 'host' => $host,
 ]);
 }

 wp_safe_redirect($target, 301);
 exit;
 }

 public static function requires_tls() {
 return (bool) apply_filters('orabooks_require_tls', self::is_production);
 }

 /**
 * Block OraBooks runtime when bootstrap failed in production ( §10).
 */
 private static function register_failure_handlers() {
 add_action('admin_notices', [self::class, 'render_bootstrap_admin_notice']);
 add_action('init', [self::class, 'block_orabooks_ajax_when_not_ready'], 0);
 }

 public static function render_bootstrap_admin_notice() {
 if (!current_user_can('manage_options') || !self::$bootstrap_error) {
 return;
 }

 $message = esc_html(self::$bootstrap_error->get_error_message);
 $issues = self::$bootstrap_error->get_error_data;
 if (is_array($issues) && !empty($issues['issues'])) {
 $message.= ' '. esc_html(implode('; ', (array) $issues['issues']));
 }

 echo '<div class="notice notice-error"><p><strong>OraBooks:</strong> '. $message. '</p></div>';
 }

 public static function block_orabooks_ajax_when_not_ready() {
 if (self::is_ready || !defined('DOING_AJAX') || !DOING_AJAX) {
 return;
 }

 $action = sanitize_text_field($_REQUEST['action'] ?? '');
 if ($action === '' || strpos($action, 'orabooks_') !== 0) {
 return;
 }

 if (function_exists('orabooks_json_error')) {
 orabooks_json_error(
 self::$bootstrap_error ? self::$bootstrap_error->get_error_message: 'OraBooks secrets bootstrap failed.',
 503
 );
 }

 wp_send_json([
 'success' => false,
 'error' => true,
 'message' => self::$bootstrap_error ? self::$bootstrap_error->get_error_message: 'OraBooks secrets bootstrap failed.',
 ], 503);
 }

 /**
 * Verify database client TLS indicators ( §5.3 / checklist §10).
 *
 * @return array<string, mixed>
 */
 public static function check_database_tls() {
 if (!self::is_production) {
 return [
 'ok' => true,
 'skipped' => true,
 'reason' => 'non_production',
 ];
 }

 $indicators = [];

 if (defined('MYSQL_CLIENT_FLAGS') && defined('MYSQLI_CLIENT_SSL')) {
 if (((int) MYSQL_CLIENT_FLAGS & (int) MYSQLI_CLIENT_SSL) !== 0) {
 $indicators[] = 'MYSQL_CLIENT_FLAGS';
 }
 }
 if (defined('MYSQL_SSL_CA') && MYSQL_SSL_CA) {
 $indicators[] = 'MYSQL_SSL_CA';
 }
 if (defined('MYSQL_SSL_CERT') && MYSQL_SSL_CERT) {
 $indicators[] = 'MYSQL_SSL_CERT';
 }
 if (defined('MYSQL_SSL_KEY') && MYSQL_SSL_KEY) {
 $indicators[] = 'MYSQL_SSL_KEY';
 }
 if (defined('DB_SSL') && DB_SSL) {
 $indicators[] = 'DB_SSL';
 }
 if (getenv('ORABOOKS_DB_SSL') === '1' || getenv('MYSQL_SSL_CA')) {
 $indicators[] = 'env_ssl';
 }

 global $wpdb;
 if (isset($wpdb->dbh) && $wpdb->dbh instanceof mysqli) {
 $result = @mysqli_query($wpdb->dbh, "SHOW SESSION STATUS LIKE 'Ssl_cipher'");
 if ($result instanceof mysqli_result) {
 $row = mysqli_fetch_assoc($result);
 mysqli_free_result($result);
 if (!empty($row['Value'])) {
 $indicators[] = 'mysqli_ssl_cipher';
 }
 }
 }

 $verified = (bool) apply_filters('orabooks_database_tls_verified', false, $indicators);
 $ok = $verified || !empty($indicators);

 if (!$ok && function_exists('orabooks_log_event')) {
 orabooks_log_event('database_tls_not_configured', 'Database TLS not detected in production', 'critical', [
 'indicators' => $indicators,
 ]);
 }

 return [
 'ok' => $ok,
 'skipped' => false,
 'indicators' => array_values(array_unique($indicators)),
 'verified' => $verified,
 ];
 }

 /**
 * Load secrets from ORABOOKS_SECRETS_FILE (JSON) for Vault/secret-manager sidecars.
 *
 * @return array<string, string>
 */
 private static function load_file_secrets() {
 if (self::$file_secrets !== null) {
 return self::$file_secrets;
 }

 self::$file_secrets = [];
 $path = getenv('ORABOOKS_SECRETS_FILE');
 if (!$path || !is_readable($path)) {
 return self::$file_secrets;
 }

 $raw = file_get_contents($path);
 if ($raw === false) {
 return self::$file_secrets;
 }

 $decoded = json_decode($raw, true);
 if (!is_array($decoded)) {
 return self::$file_secrets;
 }

 foreach ($decoded as $name => $value) {
 if (is_scalar($value) && $value !== '') {
 self::$file_secrets[sanitize_key((string) $name)] = (string) $value;
 }
 }

 return self::$file_secrets;
 }

 /**
 * Migrate plaintext legacy options into encrypted secret storage.
 */
 private static function migrate_legacy_secrets() {
 $legacy_jwt = self::with_shared_options(function() {
 return get_option('orabooks_jwt_secret', '');
 });

 if ($legacy_jwt && !self::load_secret('jwt_secret')) {
 self::set('jwt_secret', $legacy_jwt);
 }
 }

 /**
 * Mask a secret for logs and API output ( §5.6).
 */
 public static function mask_value($value) {
 $value = (string) $value;
 if ($value === '') {
 return '';
 }
 if (strlen($value) <= 8) {
 return '****';
 }

 return substr($value, 0, 4). '…'. substr($value, -4);
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
 $filtered = apply_filters('orabooks_load_secret', null, $key);
 if ($filtered !== null && $filtered !== '') {
 return (string) $filtered;
 }

 $file_secrets = self::load_file_secrets;
 if (!empty($file_secrets[$key])) {
 return $file_secrets[$key];
 }

 $env_key = 'ORABOOKS_'. strtoupper($key);
 $env_value = getenv($env_key);
 if ($env_value !== false && $env_value !== '') {
 return $env_value;
 }

 $option_key = 'orabooks_secret_'. md5($key);
 $stored = self::with_shared_options(function() use ($option_key) {
 return get_option($option_key, false);
 });
 if ($stored) {
 if ($key === 'encryption_key') {
 return self::decrypt_with_key($stored, self::get_legacy_cipher_key);
 }
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

 return $callback;
 }

 /**
 * Store a secret (encrypted at rest).
 */
 public static function set($key, $value) {
 $option_key = 'orabooks_secret_'. md5($key);
 if ($key === 'encryption_key') {
 $encrypted = self::encrypt_with_key($value, self::get_legacy_cipher_key);
 } else {
 $encrypted = self::encrypt($value);
 }
 self::with_shared_options(function use ($option_key, $encrypted) {
 update_option($option_key, $encrypted);
 });
 self::$secrets_cache[$key] = $value;
 }

 /**
 * Rotate a secret with optional grace period for JWT verification ( §5.2).
 */
 public static function rotate_secret($key, $new_value, $grace_seconds = null) {
 $key = sanitize_key((string) $key);
 $new_value = (string) $new_value;
 if ($key === '' || $new_value === '') {
 return new WP_Error('invalid_rotation', 'Secret key and value are required.');
 }

 $current = self::get($key);
 if ($current) {
 self::set($key. '_previous', $current);
 }

 self::set($key, $new_value);
 self::clear_secrets_cache($key);
 unset(self::$secrets_cache[$key. '_previous']);

 if ($key === 'jwt_secret') {
 $grace_seconds = $grace_seconds ?? self::JWT_ROTATION_GRACE_SECONDS;
 self::with_shared_options(function use ($grace_seconds) {
 update_option('orabooks_jwt_secret_grace_until', time + max(300, (int) $grace_seconds));
 update_option('orabooks_secrets_last_rotated', current_time('mysql', true));
 });
 }

 if (function_exists('orabooks_log_event')) {
 $meta = ['secret_key' => $key];
 if ($key === 'jwt_secret') {
 $meta['grace_seconds'] = $grace_seconds ?? self::JWT_ROTATION_GRACE_SECONDS;
 }
 orabooks_log_event('secret_rotated', 'Secret rotated successfully', 'warning', $meta);
 }

 return true;
 }

 /**
 * Derive AES key from the master encryption key ( §5.4).
 */
 private static function get_cipher_key() {
 $master = self::$secrets_cache['encryption_key'] ?? null;
 if ($master === null || $master === '') {
 $master = self::load_secret('encryption_key');
 }
 if ($master === null || $master === '') {
 return hash('sha256', self::get_legacy_cipher_key, true);
 }

 return hash('sha256', (string) $master, true);
 }

 /**
 * Legacy cipher key for secrets encrypted before master-key migration.
 */
 private static function get_legacy_cipher_key() {
 return defined('LOGGED_IN_KEY') ? LOGGED_IN_KEY: wp_salt('logged_in');
 }

 /**
 * Encrypt stored secrets using the master encryption key.
 */
 private static function encrypt($data) {
 return self::encrypt_with_key($data, self::get_cipher_key);
 }

 private static function encrypt_with_key($data, $raw_key) {
 $method = 'aes-256-cbc';
 $key = is_string($raw_key) && strlen($raw_key) === 32
 ? $raw_key
: hash('sha256', (string) $raw_key, true);
 $iv = substr(hash('sha256', $key. '_iv'), 0, 16);
 return base64_encode(openssl_encrypt($data, $method, $key, 0, $iv));
 }

 /**
 * Decrypt stored secret (supports legacy LOGGED_IN_KEY ciphertext).
 */
 private static function decrypt($data) {
 $decoded = base64_decode((string) $data, true);
 if ($decoded === false) {
 return false;
 }

 foreach ([self::get_cipher_key, hash('sha256', self::get_legacy_cipher_key, true)] as $key) {
 $plaintext = self::decrypt_with_key($data, $key);
 if ($plaintext !== false) {
 return $plaintext;
 }
 }

 return false;
 }

 private static function decrypt_with_key($data, $raw_key) {
 $method = 'aes-256-cbc';
 $decoded = base64_decode((string) $data, true);
 if ($decoded === false) {
 return false;
 }

 $key = is_string($raw_key) && strlen($raw_key) === 32
 ? $raw_key
: hash('sha256', (string) $raw_key, true);
 $iv = substr(hash('sha256', $key. '_iv'), 0, 16);
 $plaintext = openssl_decrypt($decoded, $method, $key, 0, $iv);
 return $plaintext !== false ? $plaintext: false;
 }

 /**
 * Encrypt sensitive at-rest values (2FA secrets, etc.).
 */
 public static function encrypt_sensitive($plaintext) {
 if ($plaintext === '' || $plaintext === null) {
 return '';
 }

 return 'enc:'. self::encrypt((string) $plaintext);
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
 return $decrypted !== false ? $decrypted: '';
 }

 /**
 * Get JWT secret key
 */
 public static function get_jwt_secret() {
 $secret = self::get('jwt_secret');
 if (!$secret) {
 $secret = wp_generate_password(64, true, true);
 self::set('jwt_secret', $secret);
 self::with_shared_options(function use ($secret) {
 if (!get_option('orabooks_secrets_last_rotated')) {
 update_option('orabooks_secrets_last_rotated', current_time('mysql', true));
 }
 });
 }
 return $secret;
 }

 /**
 * JWT secrets valid for verification (current + grace-period previous).
 *
 * @return string[]
 */
 private static function get_jwt_verification_secrets() {
 $secrets = [self::get_jwt_secret];
 $grace_until = (int) self::with_shared_options(function() {
 return (int) get_option('orabooks_jwt_secret_grace_until', 0);
 });

 if ($grace_until > time) {
 $previous = self::get('jwt_secret_previous');
 if ($previous) {
 $secrets[] = $previous;
 }
 }

 return array_values(array_unique(array_filter($secrets)));
 }

 /**
 * Check remote TLS certificate expiry for the site host ( §5.5).
 */
 public static function check_tls_certificate($host = null, $port = 443) {
 $host = strtolower(trim((string) ($host ?: parse_url(home_url, PHP_URL_HOST))));
 if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
 return [
 'ok' => true,
 'skipped' => true,
 'host' => $host,
 'reason' => 'local_or_missing_host',
 ];
 }

 if (!function_exists('stream_socket_client')) {
 return [
 'ok' => true,
 'skipped' => true,
 'host' => $host,
 'reason' => 'stream_socket_client_unavailable',
 ];
 }

 $context = stream_context_create([
 'ssl' => [
 'capture_peer_cert' => true,
 'verify_peer' => false,
 'verify_peer_name' => false,
 ],
 ]);

 $client = @stream_socket_client(
 'ssl://'. $host. ':'. (int) $port,
 $errno,
 $errstr,
 10,
 STREAM_CLIENT_CONNECT,
 $context
 );

 if (!$client) {
 if (function_exists('orabooks_log_event')) {
 orabooks_log_event('tls_certificate_check_failed', 'Unable to inspect TLS certificate', 'warning', [
 'host' => $host,
 'error' => $errstr,
 'errno' => $errno,
 ]);
 }

 return [
 'ok' => false,
 'host' => $host,
 'error' => $errstr ?: 'connection_failed',
 ];
 }

 $params = stream_context_get_params($client);
 fclose($client);

 $cert = $params['options']['ssl']['peer_certificate'] ?? null;
 if (!$cert) {
 return [
 'ok' => false,
 'host' => $host,
 'error' => 'peer_certificate_missing',
 ];
 }

 $parsed = openssl_x509_parse($cert);
 $expires_at = isset($parsed['validTo_time_t']) ? (int) $parsed['validTo_time_t']: 0;
 $days_remaining = $expires_at > 0 ? (int) floor(($expires_at - time) / DAY_IN_SECONDS): null;
 $expired = $days_remaining !== null && $days_remaining < 0;
 $expiring_soon = $days_remaining !== null && $days_remaining >= 0 && $days_remaining <= self::TLS_EXPIRY_WARN_DAYS;

 if ($expired && function_exists('orabooks_log_event')) {
 orabooks_log_event('tls_certificate_expired', 'TLS certificate has expired', 'critical', [
 'host' => $host,
 'expires_at' => gmdate('c', $expires_at),
 ]);
 } elseif ($expiring_soon && function_exists('orabooks_log_event')) {
 orabooks_log_event('tls_certificate_expiring', 'TLS certificate expiring soon', 'warning', [
 'host' => $host,
 'days_remaining' => $days_remaining,
 'expires_at' => gmdate('c', $expires_at),
 ]);
 }

 return [
 'ok' => !$expired,
 'host' => $host,
 'expires_at' => $expires_at ? gmdate('c', $expires_at): null,
 'days_remaining' => $days_remaining,
 'expired' => $expired,
 'expiring_soon' => $expiring_soon,
 ];
 }

 /**
 * Health snapshot for deploy/security dashboards.
 */
 public static function get_status() {
 $jwt = self::get_jwt_secret;
 $encryption = self::get_encryption_key;
 $tls = self::check_tls_certificate;
 $db_tls = self::check_database_tls;

 return [
 'production_mode' => self::is_production,
 'requires_tls' => self::requires_tls,
 'bootstrap_ready' => self::is_ready,
 'jwt_secret_configured' => strlen((string) $jwt) >= self::MIN_JWT_SECRET_LENGTH,
 'encryption_key_configured' => strlen((string) $encryption) >= self::MIN_ENCRYPTION_KEY_LENGTH,
 'jwt_secret_length' => strlen((string) $jwt),
 'last_rotated' => get_option('orabooks_secrets_last_rotated', ''),
 'tls' => $tls,
 'database_tls' => $db_tls,
 'https_active' => function_exists('is_ssl') ? is_ssl: false,
 ];
 }

 /**
 * Get encryption key for sensitive data (2FA, backup codes)
 */
 public static function get_encryption_key() {
 if (!empty(self::$secrets_cache['encryption_key'])) {
 return self::$secrets_cache['encryption_key'];
 }

 $key = self::load_secret('encryption_key');
 if (!$key) {
 $key = wp_generate_password(32, true, true);
 self::set('encryption_key', $key);
 }

 self::$secrets_cache['encryption_key'] = $key;
 return $key;
 }

 /**
 * Generate a JWT token
 */
 public static function generate_jwt($payload) {
 $secret = self::get_jwt_secret;
 $header = self::base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
 $payload['iat'] = time;
 $jwt_expiry = self::with_shared_options(function() {
 return (int) get_option('orabooks_jwt_expiry', self::get_default_jwt_expiry);
 });
 if (empty($payload['exp']) || (int) $payload['exp'] <= time) {
 $payload['exp'] = time + max(300, $jwt_expiry);
 }
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

 $header = $parts[0];
 $payload = $parts[1];
 $signature = $parts[2];

 $verified = false;
 foreach (self::get_jwt_verification_secrets as $secret) {
 $expected = self::base64url_encode(
 hash_hmac('sha256', "$header.$payload", $secret, true)
 );
 if (hash_equals($expected, $signature)) {
 $verified = true;
 break;
 }
 }

 if (!$verified) {
 return false;
 }

 $data = json_decode(self::base64url_decode($payload), true);
 if (!$data || !isset($data['exp']) || $data['exp'] < time) {
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
 * Generate TOTP secret for 2FA (Base32, RFC 6238).
 */
 public static function generate_totp_secret() {
 return self::base32_encode(random_bytes(20));
 }

 /**
 * Build the otpauth provisioning URI for authenticator apps (RFC 6238).
 */
 public static function get_totp_provisioning_uri($secret, $email) {
 $issuer = 'OraBooks';
 $label = $issuer. ':'. (string) $email;

 return sprintf(
 'otpauth://totp/%s?secret=%s&issuer=%s',
 rawurlencode($label),
 strtoupper((string) $secret),
 rawurlencode($issuer)
 );
 }

 /**
 * Generate QR code image URL for TOTP setup.
 */
 public static function get_totp_qr_url($secret, $email) {
 $otpauth = self::get_totp_provisioning_uri($secret, $email);

 return 'https://quickchart.io/qr?size=200&margin=1&text='. rawurlencode($otpauth);
 }

 /**
 * Normalize a user-entered OTP to six digits.
 */
 public static function normalize_totp_code($code) {
 $code = preg_replace('/\D+/', '', (string) $code);
 return strlen($code) === 6 ? $code: '';
 }

 /**
 * Verify TOTP code (+/- 30 sec drift)
 */
 public static function verify_totp($secret, $code) {
 $code = self::normalize_totp_code($code);
 if ($code === '') {
 return false;
 }

 $key = self::decode_totp_secret($secret);
 if ($key === '') {
 return false;
 }

 $time_slice = floor(time / 30);
 for ($i = -1; $i <= 1; $i++) {
 $expected = self::generate_totp_code($key, $time_slice + $i);
 if (hash_equals($expected, $code)) {
 return true;
 }
 }

 return false;
 }

 /**
 * Generate TOTP code (RFC 6238)
 */
 private static function generate_totp_code($key, $time_slice) {
 $counter = self::pack_counter($time_slice);
 $hash = hash_hmac('sha1', $counter, $key, true);
 $offset = ord($hash[19]) & 0xf;
 $value = (
 ((ord($hash[$offset]) & 0x7f) << 24) |
 ((ord($hash[$offset + 1]) & 0xff) << 16) |
 ((ord($hash[$offset + 2]) & 0xff) << 8) |
 (ord($hash[$offset + 3]) & 0xff)
 ) % 1000000;

 return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
 }

 private static function pack_counter($time_slice) {
 $time_slice = (int) $time_slice;
 if (PHP_INT_SIZE >= 8) {
 $packed = pack('J', $time_slice);
 if ($packed !== false) {
 return $packed;
 }
 }

 return pack('N*', 0, $time_slice);
 }

 /**
 * Decode stored/generated TOTP secrets (Base32, hex, or legacy ASCII test values).
 */
 private static function decode_totp_secret($secret) {
 $secret = strtoupper(preg_replace('/\s+/', '', (string) $secret));
 if ($secret === '') {
 return '';
 }

 if (preg_match('/^[A-Z2-7=]+$/', $secret)) {
 $decoded = self::base32_decode($secret);
 if ($decoded !== '') {
 return $decoded;
 }
 }

 if (preg_match('/^[0-9A-F]+$/', $secret) && (strlen($secret) % 2) === 0) {
 $decoded = hex2bin($secret);
 if ($decoded !== false) {
 return $decoded;
 }
 }

 return (string) $secret;
 }

 private static function base32_encode($data) {
 $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
 $binary = (string) $data;
 $buffer = 0;
 $bits = 0;
 $output = '';

 for ($i = 0, $len = strlen($binary); $i < $len; $i++) {
 $buffer = ($buffer << 8) | ord($binary[$i]);
 $bits += 8;
 while ($bits >= 5) {
 $bits -= 5;
 $output.= $alphabet[($buffer >> $bits) & 31];
 }
 }

 if ($bits > 0) {
 $output.= $alphabet[($buffer << (5 - $bits)) & 31];
 }

 return $output;
 }

 private static function base32_decode($data) {
 $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
 $data = strtoupper(rtrim((string) $data, '='));
 $buffer = 0;
 $bits = 0;
 $output = '';

 for ($i = 0, $len = strlen($data); $i < $len; $i++) {
 $pos = strpos($alphabet, $data[$i]);
 if ($pos === false) {
 return '';
 }
 $buffer = ($buffer << 5) | $pos;
 $bits += 5;
 if ($bits >= 8) {
 $bits -= 8;
 $output.= chr(($buffer >> $bits) & 255);
 }
 }

 return $output;
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

 $signed = $parts[0]. '.'. $parts[1];
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

 if (empty($payload['exp']) || (int) $payload['exp'] < time) {
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

 $cache_key = 'orabooks_google_jwk_'. md5($kid);
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
 * Convert a Google RSA JWK to PEM for openssl_verify.
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
 self::encode_asn1_integer($modulus). self::encode_asn1_integer($exponent)
 );
 $bit_string = "\x00". $rsa_sequence;
 $bit_string_encoded = "\x03". self::encode_asn1_length(strlen($bit_string)). $bit_string;
 $oid = hex2bin('300d06092a864886f70d0101010500');
 $public_key_info = self::encode_asn1_sequence($oid. $bit_string_encoded);
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
 return chr(0x80 | strlen($temp)). $temp;
 }

 private static function encode_asn1_integer($data) {
 if ($data === '') {
 $data = "\x00";
 } elseif ($data[0] === "\x00" || (ord($data[0]) & 0x80)) {
 $data = "\x00". $data;
 }

 return "\x02". self::encode_asn1_length(strlen($data)). $data;
 }

 private static function encode_asn1_sequence($data) {
 return "\x30". self::encode_asn1_length(strlen($data)). $data;
 }
}