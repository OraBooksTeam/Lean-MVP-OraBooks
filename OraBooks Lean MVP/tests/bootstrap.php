<?php
/**
 * OraBooks Plugin Unit Tests Bootstrap
 *
 * Sets up the test environment by defining ABSPATH, creating a mock
 * $wpdb global, and stubbing the WordPress functions used by the
 * OraBooks_Exports class.
 *
 * Run with: vendor/bin/phpunit --configuration tests/phpunit.xml
 * Or: phpunit --configuration tests/phpunit.xml
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

// Define WordPress constants used by the exports class
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!defined('OBJECT_K')) {
    define('OBJECT_K', 'OBJECT_K');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

// ---------------------------------------------------------------------------
// 1. Stub global $wpdb
// ---------------------------------------------------------------------------
global $wpdb;

if (!class_exists('wpdb', false)) {
    /**
     * Minimal wpdb stub used by OraBooks classes.
     */
    class wpdb
    {
        public $prefix = 'wp_test_';
        public $users  = 'wp_test_users';

    /** Simulated storage for query results */
    public $insert_id      = 0;
    public $last_query     = '';
    public $last_result    = [];

    /**
     * Test callback hooks — tests can set these to override the default mock
     * behavior without relying on dynamic property assignment (which doesn't
     * work for class methods in PHP 8+).
     *
     * Each callback receives (query, ...args) and should return the desired value.
     * Set to null (default) to use the built-in mock logic.
     *
     * @var callable|null
     */
    public $test_get_var_callback     = null;
    public $test_get_row_callback     = null;
    public $test_get_results_callback = null;
    public $test_query_callback       = null;
    public $test_insert_callback      = null;

    /** Register SHOW COLUMNS results so dbDelta calls don't crash */
    private $show_columns_cache = [];

    public function __construct() {}

        public function get_charset_collate() {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        public function esc_like($text) {
            return addslashes($text);
        }

        public function prepare($query, ...$args) {
            // Flatten if a single array is passed (wpdb::prepare accepts array of params)
            if (count($args) === 1 && is_array($args[0])) {
                $args = $args[0];
            }
            if (strpos($query, '%s') !== false || strpos($query, '%d') !== false || strpos($query, '%f') !== false) {
                $escaped = [];
                foreach ($args as $arg) {
                    if (is_int($arg) || is_float($arg)) {
                        $escaped[] = $arg;
                    } elseif ($arg === null) {
                        $escaped[] = "''";
                    } elseif (is_array($arg)) {
                        $escaped[] = "'" . addslashes(implode(',', $arg)) . "'";
                    } else {
                        $escaped[] = "'" . addslashes((string)$arg) . "'";
                    }
                }
                $i = 0;
                return preg_replace_callback('/%(d|f|s)/', function ($m) use (&$i, $escaped) {
                    return $escaped[$i++] ?? $m[0];
                }, $query);
            }
            return $query;
        }

        public function get_var($query = null, $x = 0, $y = 0) {
            if ($query !== null) {
                $this->last_query = $query;
            }
            // Allow test override
            if ($this->test_get_var_callback !== null) {
                return ($this->test_get_var_callback)($query, $x, $y);
            }
            // Check most specific patterns first, then fall through to generic ones
            // 24h range query (most specific)
            if (stripos($this->last_query, 'INTERVAL 24 HOUR') !== false) {
                return 3;
            }
            // SUM(download_count)
            if (stripos($this->last_query, 'SUM(download_count)') !== false) {
                return 42;
            }
            // orabooks_user_org lookups
            if (stripos($this->last_query, 'orabooks_user_org') !== false) {
                return 1; // org_id = 1
            }
            // Generic SELECT COUNT (covers total exports, user export counts)
            if (stripos($this->last_query, 'SELECT COUNT') !== false || stripos($this->last_query, 'COUNT(*)') !== false) {
                return 5;
            }
            // Organization name lookup
            if (stripos($this->last_query, 'orabooks_organizations') !== false) {
                return 'Test Org';
            }
            // User email lookup
            if (stripos($this->last_query, 'orabooks_users') !== false && stripos($this->last_query, 'SELECT email') !== false) {
                return 'user@example.com';
            }
            return 0;
        }

        public function get_row($query = null, $output = OBJECT, $y = 0) {
            if ($query !== null) {
                $this->last_query = $query;
            }
            // Allow test override
            if ($this->test_get_row_callback !== null) {
                return ($this->test_get_row_callback)($query, $output, $y);
            }
            // Return a mock export row object
            if (stripos($this->last_query, 'SELECT * FROM') !== false) {
                return (object)[
                    'id'             => 1,
                    'org_id'         => 1,
                    'user_id'        => 1,
                    'export_type'    => 'test_report',
                    'format'         => 'csv',
                    'parameters'     => '{"columns":["name","value"]}',
                    'status'         => 'pending',
                    'file_url'       => 'http://example.com/exports/test.csv',
                    'file_size'      => 1024,
                    'file_hash'      => 'abc123',
                    'expires_at'     => date('Y-m-d H:i:s', time() + 86400 * 7),
                    'download_count' => 0,
                    'correlation_id' => '550e8400-e29b-41d4-a716-446655440000',
                    'error_message'  => null,
                    'generated_at'   => null,
                    'created_at'     => date('Y-m-d H:i:s'),
                ];
            }
            return null;
        }

        public function get_results($query = null, $output = OBJECT) {
            if ($query !== null) {
                $this->last_query = $query;
            }
            // Allow test override
            if ($this->test_get_results_callback !== null) {
                return ($this->test_get_results_callback)($query, $output);
            }
            // Return empty for expired cleanup query
            if (stripos($this->last_query, 'r.expires_at < NOW()') !== false) {
                return [];
            }
            // Return status counts for stats
            if (stripos($this->last_query, 'GROUP BY status') !== false) {
                return [
                    (object)['status' => 'pending',    'count' => 3],
                    (object)['status' => 'generating', 'count' => 1],
                    (object)['status' => 'ready',      'count' => 5],
                    (object)['status' => 'failed',     'count' => 2],
                    (object)['status' => 'expired',    'count' => 1],
                    (object)['status' => 'cancelled',  'count' => 0],
                ];
            }
            // Return by_format
            if (stripos($this->last_query, 'GROUP BY format') !== false) {
                return [
                    (object)['format' => 'csv', 'count' => 8],
                    (object)['format' => 'pdf', 'count' => 4],
                ];
            }
            // Return by_type
            if (stripos($this->last_query, 'GROUP BY export_type') !== false) {
                return [
                    (object)['export_type' => 'coa',             'count' => 5],
                    (object)['export_type' => 'audit_log',       'count' => 3],
                    (object)['export_type' => 'notification_log', 'count' => 2],
                ];
            }
            // Return user exports list
            if (stripos($this->last_query, 'ORDER BY created_at DESC') !== false) {
                $rows = [];
                for ($i = 1; $i <= 3; $i++) {
                    $rows[] = (object)[
                        'id'             => $i,
                        'org_id'         => 1,
                        'user_id'        => 1,
                        'export_type'    => 'test_report',
                        'format'         => 'csv',
                        'parameters'     => '{}',
                        'status'         => $i === 1 ? 'ready' : ($i === 2 ? 'pending' : 'failed'),
                        'file_url'       => $i === 1 ? 'http://example.com/exports/test.csv' : null,
                        'file_size'      => $i === 1 ? 2048 : null,
                        'file_hash'      => $i === 1 ? 'def456' : null,
                        'expires_at'     => $i === 1 ? date('Y-m-d H:i:s', time() + 86400 * 7) : null,
                        'download_count' => $i === 1 ? 3 : 0,
                        'correlation_id' => 'uuid-' . $i,
                        'error_message'  => $i === 3 ? 'Generation failed' : null,
                        'generated_at'   => $i === 1 ? date('Y-m-d H:i:s', time() - 3600) : null,
                        'created_at'     => date('Y-m-d H:i:s', time() - ($i * 3600)),
                    ];
                }
                return $rows;
            }
            return [];
        }

        public function insert($table, $data, $format = []) {
            // Allow test override via callback
            if ($this->test_insert_callback !== null) {
                ($this->test_insert_callback)($table, $data, $format);
            }
            // Allow tests to set insert_id before calling to control the mock
            if (isset($GLOBALS['orabooks_test_use_insert_id']) && $GLOBALS['orabooks_test_use_insert_id'] !== null) {
                $this->insert_id = $GLOBALS['orabooks_test_use_insert_id'];
                $GLOBALS['orabooks_test_use_insert_id'] = null;
                return $this->insert_id;
            }
            $this->insert_id = rand(100, 999);
            return $this->insert_id;
        }

        public function update($table, $data, $where, $format = [], $where_format = []) {
            // Build a representative last_query so tests can inspect column names
            $set_clauses = [];
            foreach ($data as $col => $val) {
                $set_clauses[] = is_null($val) ? "{$col} = NULL" : "{$col} = '" . addslashes((string)$val) . "'";
            }
            $where_clauses = [];
            foreach ($where as $col => $val) {
                $where_clauses[] = is_null($val) ? "{$col} IS NULL" : "{$col} = '" . addslashes((string)$val) . "'";
            }
            $this->last_query = sprintf(
                "UPDATE %s SET %s WHERE %s",
                $table,
                implode(', ', $set_clauses),
                implode(' AND ', $where_clauses)
            );
            $this->last_result = [];
            return 1; // 1 row affected
        }

        public function query($query) {
            $this->last_query = $query;
            // Allow test override
            if ($this->test_query_callback !== null) {
                return ($this->test_query_callback)($query);
            }
            // Return 0 for ALTER TABLE (schema changes)
            if (stripos($query, 'ALTER TABLE') !== false) {
                return 0;
            }
            return 1;
        }

        public function get_charset() { return 'utf8mb4'; }
        public function get_collate() { return 'utf8mb4_unicode_ci'; }
    }
}

$wpdb = new wpdb();

// ---------------------------------------------------------------------------
// 2. Stub OraBooks_Database::table()
// ---------------------------------------------------------------------------
if (!class_exists('OraBooks_Database', false)) {
    class OraBooks_Database
    {
        public static function init() { return new self(); }

        public static function table($name) {
            global $wpdb;
            return $wpdb->prefix . 'orabooks_' . $name;
        }
    }
}

// ---------------------------------------------------------------------------
// 3. Stub WordPress functions used by OraBooks_Exports
// ---------------------------------------------------------------------------

if (!function_exists('current_time')) {
    function current_time($type, $gmt = false) {
        if ($type === 'mysql') {
            return date('Y-m-d H:i:s', $gmt ? time() : time());
        }
        if ($type === 'timestamp') {
            return time();
        }
        return date($type);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return is_string($str) ? trim($str) : ''; }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) { return is_string($str) ? trim($str) : ''; }
}

if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name($name) {
        return preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $name);
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        $dir = sys_get_temp_dir() . '/orabooks-test-uploads';
        return [
            'basedir' => $dir,
            'baseurl' => 'http://example.com/wp-content/uploads',
            'path'    => $dir,
            'url'     => 'http://example.com/wp-content/uploads',
            'subdir'  => '',
            'error'   => false,
        ];
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($path) {
        if (!is_dir($path)) {
            return mkdir($path, 0777, true);
        }
        return true;
    }
}

if (!function_exists('wp_send_json')) {
    function wp_send_json($data, $status_code = 200) {
        throw new \RuntimeException(json_encode($data));
    }
}

if (!function_exists('get_current_user_id')) {
    /**
     * Mock get_current_user_id.
     * Set $GLOBALS['orabooks_test_current_user_id'] to override the return value.
     * Default: 1 (authenticated user). Set to 0 to simulate unauthenticated.
     */
    function get_current_user_id() {
        return $GLOBALS['orabooks_test_current_user_id'] ?? 1;
    }
}
// Initialize the default
$GLOBALS['orabooks_test_current_user_id'] = 1;

if (!function_exists('current_user_can')) {
    /**
     * Mock current_user_can.
     * Set $GLOBALS['orabooks_test_current_user_can'] to false to deny all capabilities.
     */
    function current_user_can($capability) {
        return $GLOBALS['orabooks_test_current_user_can'] ?? true;
    }
}
$GLOBALS['orabooks_test_current_user_can'] = true;

if (!function_exists('__')) {
    function __($text, $domain = 'orabooks') { return $text; }
}

if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('esc_url')) {
    function esc_url($url) { return $url; }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) { return $url; }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) { return json_encode($data); }
}

if (!function_exists('get_transient')) {
    function get_transient($key) {
        if (!isset($GLOBALS['orabooks_test_transients'])) {
            $GLOBALS['orabooks_test_transients'] = [];
        }
        $entry = $GLOBALS['orabooks_test_transients'][$key] ?? null;
        if ($entry === null) {
            return false;
        }
        // Check expiry
        if ($entry['expires_at'] !== null && $entry['expires_at'] < time()) {
            unset($GLOBALS['orabooks_test_transients'][$key]);
            return false;
        }
        return $entry['value'];
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0) {
        if (!isset($GLOBALS['orabooks_test_transients'])) {
            $GLOBALS['orabooks_test_transients'] = [];
        }
        $GLOBALS['orabooks_test_transients'][$key] = [
            'value'      => $value,
            'expires_at' => $expiration > 0 ? time() + $expiration : null,
        ];
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        if (!isset($GLOBALS['orabooks_test_transients'])) {
            $GLOBALS['orabooks_test_transients'] = [];
        }
        unset($GLOBALS['orabooks_test_transients'][$key]);
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action($tag, ...$args) { /* no-op */ }
}

if (!function_exists('add_action')) {
    function add_action($tag, $callback, $priority = 10, $accepted_args = 1) { /* no-op */ }
}

if (!function_exists('home_url')) {
    function home_url($path = '') { return 'http://example.com' . $path; }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') { return 'http://example.com/wp-admin/' . $path; }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($key, $value, $url) {
        $separator = strpos($url, '?') === false ? '?' : '&';
        return $url . $separator . urlencode($key) . '=' . urlencode($value);
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action) { return md5($action . time()); }
}

if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, $decimals = 0) {
        return number_format($number, $decimals);
    }
}

if (!function_exists('wp_hash_password')) {
    function wp_hash_password($password) { return password_hash($password, PASSWORD_DEFAULT); }
}

if (!class_exists('WP_Error', false)) {
    class WP_Error
    {
        private $errors = [];

        public function __construct($code = '', $message = '', $data = '') {
            if ($code) {
                $this->errors[$code][] = $message;
            }
        }

        public function get_error_code() {
            $codes = array_keys($this->errors);
            return $codes[0] ?? '';
        }

        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return $this->errors[$code][0] ?? '';
        }

        public function get_error_messages($code = '') {
            if (empty($code)) {
                $all = [];
                foreach ($this->errors as $msgs) {
                    $all = array_merge($all, $msgs);
                }
                return $all;
            }
            return $this->errors[$code] ?? [];
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return ($thing instanceof WP_Error);
    }
}

// ---------------------------------------------------------------------------
// 4. Stub the OraBooks helper functions used by the exports class
//    (redefine so they work without the rest of the plugin)
// ---------------------------------------------------------------------------

if (!function_exists('orabooks_check_rate_limit')) {
    function orabooks_check_rate_limit($key, $max_attempts, $period_seconds = 3600) {
        return true; // Allow all requests in tests
    }
}

if (!function_exists('orabooks_uuid')) {
    function orabooks_uuid() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('orabooks_enqueue_job')) {
    function orabooks_enqueue_job($job_type, $payload, $options = []) {
        return 42; // Mock job ID
    }
}

if (!function_exists('orabooks_log_event')) {
    function orabooks_log_event($event_type, $description, $severity = 'info', $metadata = null, $user_id = null, $org_id = null) {
        return true;
    }
}

if (!function_exists('orabooks_publish_event')) {
    function orabooks_publish_event($event_type, $aggregate_id, $payload = []) {
        return 100; // Mock outbox message ID
    }
}

if (!function_exists('orabooks_get_user_email')) {
    function orabooks_get_user_email($user_id) {
        return 'user@example.com';
    }
}

if (!function_exists('orabooks_has_permission')) {
    /**
     * Mock orabooks_has_permission.
     * Set $GLOBALS['orabooks_test_has_permission'] to false to deny all permissions.
     */
    function orabooks_has_permission($user_id, $org_id, $permission) {
        return $GLOBALS['orabooks_test_has_permission'] ?? true;
    }
}
$GLOBALS['orabooks_test_has_permission'] = true;

if (!function_exists('orabooks_json_error')) {
    function orabooks_json_error($message, $status_code = 400) {
        throw new \RuntimeException(json_encode(['error' => true, 'message' => $message]));
    }
}

if (!function_exists('orabooks_json_success')) {
    function orabooks_json_success($data = [], $message = '') {
        throw new \RuntimeException(json_encode(['error' => false, 'message' => $message, 'data' => $data]));
    }
}

// ---------------------------------------------------------------------------
// 5. Stub OraBooks_Secrets
// ---------------------------------------------------------------------------
if (!class_exists('OraBooks_Secrets', false)) {
    /**
     * Minimal OraBooks_Secrets stub for testing.
     *
     * Tests control values via $GLOBALS:
     * - $GLOBALS['orabooks_test_secrets'] - array of config values (e.g. google_oauth_client_id)
     * - $GLOBALS['orabooks_test_jwt_token'] - token returned by generate_jwt()
     * - $GLOBALS['orabooks_test_verify_jwt_result'] - payload returned by verify_jwt()
     * - $GLOBALS['orabooks_test_last_jwt_payload'] - populated with the payload passed to generate_jwt()
     * - $GLOBALS['orabooks_test_totp_secret'] - secret returned by generate_totp_secret()
     */
    class OraBooks_Secrets
    {
        public static function get($key) {
            return $GLOBALS['orabooks_test_secrets'][$key] ?? null;
        }

        public static function generate_jwt($payload) {
            $GLOBALS['orabooks_test_last_jwt_payload'] = $payload;
            return $GLOBALS['orabooks_test_jwt_token'] ?? 'test-jwt-token-' . time();
        }

        public static function hash_password($password) {
            $hash = $GLOBALS['orabooks_test_password_hash'] ?? null;
            if ($hash) {
                return $hash;
            }
            return password_hash($password, PASSWORD_DEFAULT);
        }

        public static function verify_password($password, $hash) {
            // If test provided a fixed hash, compare directly
            if (isset($GLOBALS['orabooks_test_verify_password_result'])) {
                return $GLOBALS['orabooks_test_verify_password_result'];
            }
            return password_verify($password, $hash);
        }

        public static function generate_totp_secret() {
            return $GLOBALS['orabooks_test_totp_secret'] ?? 'TESTSECRET123456';
        }

        public static function get_totp_qr_url($secret, $email) {
            return 'otpauth://totp/' . urlencode($email) . '?secret=' . $secret . '&issuer=OraBooks';
        }

        public static function generate_backup_codes() {
            return ['backup-code-001', 'backup-code-002', 'backup-code-003'];
        }

        public static function verify_totp($secret, $otp) {
            if (isset($GLOBALS['orabooks_test_verify_totp_result'])) {
                return $GLOBALS['orabooks_test_verify_totp_result'];
            }
            return $otp === '123456';
        }

        public static function verify_jwt($token) {
            return $GLOBALS['orabooks_test_verify_jwt_result'] ?? false;
        }
    }
}

// Initialize defaults
$GLOBALS['orabooks_test_secrets'] = [];
$GLOBALS['orabooks_test_last_jwt_payload'] = null;

// ---------------------------------------------------------------------------
// 6. Stub OraBooks_Organization
// ---------------------------------------------------------------------------
if (!class_exists('OraBooks_Organization', false)) {
    /**
     * Minimal OraBooks_Organization stub for testing.
     *
     * Tests control get() via $GLOBALS['orabooks_test_org_callback'] callback.
     * Default returns a customer org with status='active'.
     */
    class OraBooks_Organization
    {
        public static function get($org_id) {
            if (isset($GLOBALS['orabooks_test_org_callback'])) {
                return ($GLOBALS['orabooks_test_org_callback'])($org_id);
            }
            if (!$org_id) {
                return null;
            }
            return (object)[
                'id'                => $org_id,
                'owner_id'          => 1,
                'organization_type' => 'customer',
                'tier'              => 'free',
                'subdomain'         => 'testorg',
                'status'            => 'active',
                'name'              => 'Test Customer Org',
            ];
        }

        public static function create($data) {
            return [
                'org_id'    => rand(100, 999),
                'subdomain' => $data['subdomain'] ?? 'testorg',
                'name'      => $data['name'] ?? 'Test Org',
            ];
        }

        public static function request_partner_reactivation($org_id, $user_id, $reason) {
            return rand(100, 999); // Returns review ID
        }

        public static function review_reactivation($review_id, $admin_id, $decision, $notes) {
            return true;
        }
    }
}

// ---------------------------------------------------------------------------
// 7. Stub WordPress functions used by OraBooks_Auth
// ---------------------------------------------------------------------------

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('is_email')) {
    function is_email($email) {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        $GLOBALS['orabooks_test_deleted_transients'][] = $key;
        return true;
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        if (isset($GLOBALS['orabooks_test_wp_remote_post_callback'])) {
            return ($GLOBALS['orabooks_test_wp_remote_post_callback'])($url, $args);
        }
        // Default: return a mock Google token response
        $client_id = $GLOBALS['orabooks_test_secrets']['google_oauth_client_id'] ?? 'test-client-id';
        $id_token_payload = base64_encode(json_encode([
            'sub'   => 'google-12345',
            'email' => 'googleuser@example.com',
            'aud'   => $client_id,
            'name'  => 'Google User',
        ]));
        return [
            'body'     => json_encode([
                'id_token'     => 'header.' . $id_token_payload . '.signature',
                'access_token' => 'ya29.mock-access-token',
                'expires_in'   => 3600,
            ]),
            'response' => ['code' => 200],
        ];
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return is_array($response) && isset($response['body']) ? $response['body'] : '';
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return is_array($response) && isset($response['response']['code']) ? $response['response']['code'] : 500;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() {
        return ($GLOBALS['orabooks_test_current_user_id'] ?? 1) > 0;
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $meta_key, $meta_value) {
        $GLOBALS['orabooks_test_user_meta'][$user_id][$meta_key] = $meta_value;
        return true;
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $meta_key, $single = true) {
        $value = $GLOBALS['orabooks_test_user_meta'][$user_id][$meta_key] ?? null;
        return $single ? $value : [$value];
    }
}

if (!function_exists('delete_user_meta')) {
    function delete_user_meta($user_id, $meta_key) {
        unset($GLOBALS['orabooks_test_user_meta'][$user_id][$meta_key]);
        return true;
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        static $user;
        if (!$user) {
            $user = new stdClass();
            $user->ID = $GLOBALS['orabooks_test_current_user_id'] ?? 1;
            $user->user_email = 'admin@example.com';
        }
        return $user;
    }
}

// ---------------------------------------------------------------------------
// 8. Stub OraBooks helper functions used by OraBooks_Auth
// ---------------------------------------------------------------------------

if (!function_exists('orabooks_random_string')) {
    function orabooks_random_string($length = 32) {
        return substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz0123456789', $length)), 0, $length);
    }
}

if (!function_exists('orabooks_hash_token')) {
    function orabooks_hash_token($token) {
        return hash('sha256', $token);
    }
}

if (!function_exists('orabooks_validate_subdomain')) {
    function orabooks_validate_subdomain($subdomain) {
        if (strlen($subdomain) < 3) {
            return 'Subdomain must be at least 3 characters';
        }
        if (strlen($subdomain) > 32) {
            return 'Subdomain must be at most 32 characters';
        }
        if (!preg_match('/^[a-z0-9-]+$/', $subdomain)) {
            return 'Subdomain can only contain lowercase letters, numbers, and hyphens';
        }
        return true;
    }
}

if (!function_exists('orabooks_validate_password')) {
    function orabooks_validate_password($password) {
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must contain an uppercase letter';
        }
        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must contain a lowercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Password must contain a number';
        }
        return true;
    }
}

if (!function_exists('orabooks_get_user_role')) {
    function orabooks_get_user_role($user_id, $org_id) {
        if (isset($GLOBALS['orabooks_test_get_user_role_callback'])) {
            return ($GLOBALS['orabooks_test_get_user_role_callback'])($user_id, $org_id);
        }
        return 'owner';
    }
}

if (!function_exists('orabooks_get_client_ip')) {
    function orabooks_get_client_ip() {
        return '127.0.0.1';
    }
}

if (!function_exists('orabooks_get_user_agent')) {
    function orabooks_get_user_agent() {
        return 'PHPUnit Test/1.0';
    }
}

if (!function_exists('orabooks_generate_partner_code')) {
    function orabooks_generate_partner_code() {
        return 'PARTNER-' . strtoupper(substr(md5(time()), 0, 8));
    }
}

// ---------------------------------------------------------------------------
// 9. Load the actual classes under test
// ---------------------------------------------------------------------------
$exports_file = __DIR__ . '/../includes/class-orabooks-exports.php';
if (!file_exists($exports_file)) {
    echo "ERROR: Cannot find class-orabooks-exports.php at {$exports_file}\n";
    exit(1);
}
require_once $exports_file;

$auth_file = __DIR__ . '/../includes/class-orabooks-auth.php';
if (!file_exists($auth_file)) {
    echo "ERROR: Cannot find class-orabooks-auth.php at {$auth_file}\n";
    exit(1);
}
require_once $auth_file;

$customers_file = __DIR__ . '/../includes/class-orabooks-customers.php';
if (!file_exists($customers_file)) {
    echo "ERROR: Cannot find class-orabooks-customers.php at {$customers_file}\n";
    exit(1);
}
require_once $customers_file;

$notifications_file = __DIR__ . '/../includes/class-orabooks-notifications.php';
if (!file_exists($notifications_file)) {
    echo "ERROR: Cannot find class-orabooks-notifications.php at {$notifications_file}\n";
    exit(1);
}
require_once $notifications_file;

$partner_file = __DIR__ . '/../includes/class-orabooks-partner.php';
if (!file_exists($partner_file)) {
    echo "ERROR: Cannot find class-orabooks-partner.php at {$partner_file}\n";
    exit(1);
}
require_once $partner_file;

$tax_file = __DIR__ . '/../includes/class-orabooks-tax.php';
if (!file_exists($tax_file)) {
    echo "ERROR: Cannot find class-orabooks-tax.php at {$tax_file}\n";
    exit(1);
}
require_once $tax_file;

$vendors_file = __DIR__ . '/../includes/class-orabooks-vendors.php';
if (!file_exists($vendors_file)) {
    echo "ERROR: Cannot find class-orabooks-vendors.php at {$vendors_file}\n";
    exit(1);
}
require_once $vendors_file;

$inventory_file = __DIR__ . '/../includes/class-orabooks-inventory.php';
if (!file_exists($inventory_file)) {
    echo "ERROR: Cannot find class-orabooks-inventory.php at {$inventory_file}\n";
    exit(1);
}
require_once $inventory_file;

$bank_reconciliation_file = __DIR__ . '/../includes/class-orabooks-bank-reconciliation.php';
if (!file_exists($bank_reconciliation_file)) {
    echo "ERROR: Cannot find class-orabooks-bank-reconciliation.php at {$bank_reconciliation_file}\n";
    exit(1);
}
require_once $bank_reconciliation_file;

// orabooks_mask_email — used by OraBooks_Partner::get_dashboard_data
if (!function_exists('orabooks_mask_email')) {
    function orabooks_mask_email($email) {
        $parts = explode('@', $email);
        $name = $parts[0];
        $domain = $parts[1] ?? '';
        $masked = substr($name, 0, 2) . str_repeat('*', max(0, strlen($name) - 2));
        return $masked . '@' . $domain;
    }
}

// Stub OraBooks_RBAC
if (!class_exists('OraBooks_RBAC', false)) {
    class OraBooks_RBAC {
        public static function require_permission($user_id, $org_id, $permission) {
            return true;
        }
        public static function check_permission($role, $permission, $org_id) {
            return true;
        }
    }
}

// Stub OraBooks_Audit
if (!class_exists('OraBooks_Audit', false)) {
    class OraBooks_Audit {
        public static function log_event($event_type, $description, $severity = 'info', $metadata = null, $user_id = null, $org_id = null) {
            return true;
        }
    }
}

// Stub OraBooks_Commission
if (!class_exists('OraBooks_Commission', false)) {
    class OraBooks_Commission {
        public static function init() { return new self(); }
        public static function get_config() { return (object)['min_payout_threshold' => 25.00, 'customer_active_window_days' => 30, 'base_monthly_amount' => 10.00, 'max_years' => 6, 'yearly_percentages' => [20, 15, 10, 5, 2.5, 1], 'currency' => 'USD']; }
        public static function get_commission_stats($partner_user_id) { return ['total_earned' => 0, 'pending_payout' => 0, 'total_paid' => 0, 'total_expired' => 0]; }
        public static function get_payouts($partner_user_id, $args = []) { return []; }
        public static function refresh_customer_active_status($customer_id) { return true; }
    }
}

// Stub OraBooks_COA
if (!class_exists('OraBooks_COA', false)) {
    class OraBooks_COA {
        public static function init() { return new self(); }
        public static function load_chart_of_accounts($org_id, $tier, $org_type) { return true; }
        public static function get_account_by_code($org_id, $code) { return null; }
    }
}

// Stub OraBooks_Posting
if (!class_exists('OraBooks_Posting', false)) {
    class OraBooks_Posting {
        public static function create_journal($data, $user_id) { return 1; }
        public static function add_lines($journal_id, $lines) { return true; }
        public static function approve_journal($journal_id, $user_id) { return true; }
        public static function post_journal($journal_id, $user_id) { return true; }
    }
}
