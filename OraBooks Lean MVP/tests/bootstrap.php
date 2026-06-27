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

if (!defined('ORABOOKS_PLUGIN_DIR')) {
    define('ORABOOKS_PLUGIN_DIR', __DIR__ . '/../');
}

if (!defined('ORABOOKS_PLUGIN_URL')) {
    define('ORABOOKS_PLUGIN_URL', 'http://example.com/wp-content/plugins/orabooks/');
}

if (!defined('ORABOOKS_DB_VERSION')) {
    define('ORABOOKS_DB_VERSION', '1.0.1');
}

if (PHP_SAPI === 'cli' && !empty($argv)) {
    $cli_args = implode(' ', $argv);
    if (strpos($cli_args, 'OraBooks_Organization_Test') !== false || strpos($cli_args, 'phpunit-organization.xml') !== false) {
        $GLOBALS['ORABOOKS_LOAD_REAL_ORGANIZATION'] = true;
    }
    if (strpos($cli_args, 'OraBooks_Audit_Test') !== false || strpos($cli_args, 'phpunit-audit.xml') !== false) {
        $GLOBALS['ORABOOKS_LOAD_REAL_AUDIT'] = true;
    }
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
    public $test_update_callback      = null;
    public $test_delete_callback      = null;
    public $test_get_col_callback     = null;

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

        public function get_col($query = null, $x = 0) {
            if ($query !== null) {
                $this->last_query = $query;
            }
            if ($this->test_get_col_callback !== null) {
                return ($this->test_get_col_callback)($query, $x);
            }
            if ($this->test_get_results_callback !== null) {
                $rows = ($this->test_get_results_callback)($query, OBJECT);
                return array_map(function ($row) {
                    if (is_object($row)) {
                        $values = array_values(get_object_vars($row));
                        return $values[0] ?? null;
                    }
                    if (is_array($row)) {
                        $values = array_values($row);
                        return $values[0] ?? null;
                    }
                    return $row;
                }, is_array($rows) ? $rows : []);
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

        public function replace($table, $data, $format = []) {
            if ($this->test_insert_callback !== null) {
                ($this->test_insert_callback)($table, $data, $format);
            }
            $this->insert_id = rand(100, 999);
            return 1;
        }

        public function update($table, $data, $where, $format = [], $where_format = []) {
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

            if ($this->test_update_callback !== null) {
                $result = ($this->test_update_callback)($table, $data, $where, $format, $where_format);
                return $result !== null ? $result : 1;
            }

            $this->last_result = [];
            return 1; // 1 row affected
        }

        public function delete($table, $where, $where_format = []) {
            $where_clauses = [];
            foreach ($where as $col => $val) {
                $where_clauses[] = is_null($val) ? "{$col} IS NULL" : "{$col} = '" . addslashes((string) $val) . "'";
            }
            $this->last_query = sprintf(
                'DELETE FROM %s WHERE %s',
                $table,
                implode(' AND ', $where_clauses)
            );

            if ($this->test_delete_callback !== null) {
                return ($this->test_delete_callback)($table, $where, $where_format);
            }

            return 1;
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

if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '') {
        $cache_key = $group . ':' . $key;
        return $GLOBALS['orabooks_test_cache'][$cache_key] ?? false;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0) {
        $cache_key = $group . ':' . $key;
        $GLOBALS['orabooks_test_cache'][$cache_key] = $data;
        return true;
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '') {
        $cache_key = $group . ':' . $key;
        unset($GLOBALS['orabooks_test_cache'][$cache_key]);
        return true;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return is_string($str) ? trim($str) : ''; }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false) {
        return substr(str_repeat('a1b2c3d4', max(1, (int) ceil($length / 8))), 0, (int) $length);
    }
}

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax() { return false; }
}

if (!function_exists('wp_doing_cron')) {
    function wp_doing_cron() { return false; }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '') { throw new RuntimeException((string) $message); }
}

if (!function_exists('wp_trim_words')) {
    function wp_trim_words($text, $num_words = 55) {
        $words = preg_split('/\s+/', trim((string) $text));
        if (count($words) <= $num_words) {
            return (string) $text;
        }
        return implode(' ', array_slice($words, 0, $num_words)) . '...';
    }
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

if (!function_exists('get_userdata')) {
    function get_userdata($user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }

        return (object) [
            'ID' => $user_id,
            'user_email' => $GLOBALS['orabooks_test_user_email'] ?? 'test@example.com',
            'user_login' => $GLOBALS['orabooks_test_user_login'] ?? 'testuser',
        ];
    }
}

if (!function_exists('orabooks_get_current_user_id')) {
    function orabooks_get_current_user_id() {
        return get_current_user_id();
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
    function do_action($tag, ...$args) {
        if (empty($GLOBALS['orabooks_test_actions'][$tag])) {
            return;
        }
        foreach ($GLOBALS['orabooks_test_actions'][$tag] as $callback) {
            call_user_func_array($callback, $args);
        }
    }
}

if (!function_exists('add_action')) {
    function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['orabooks_test_actions'][$tag][] = $callback;
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['orabooks_test_filters'][$tag][] = $callback;
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) {
        if (empty($GLOBALS['orabooks_test_filters'][$tag])) {
            return $value;
        }
        foreach ($GLOBALS['orabooks_test_filters'][$tag] as $callback) {
            $value = call_user_func_array($callback, array_merge([$value], $args));
        }
        return $value;
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') { return 'http://example.com' . $path; }
}

if (!function_exists('rest_url')) {
    function rest_url($path = '') {
        $path = ltrim((string) $path, '/');
        return home_url('/wp-json/' . $path);
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($string) {
        return rtrim($string, '/\\') . '/';
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server {
        const READABLE = 'GET';
        const CREATABLE = 'POST';
        const EDITABLE = 'POST, PUT, PATCH';
        const DELETABLE = 'DELETE';
        const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private $params = [];
        private $headers = [];

        public function __construct($method = 'GET', $route = '', $attributes = []) {
            $this->params = is_array($attributes) ? $attributes : [];
        }

        public function __get($key) {
            return $this->params[$key] ?? null;
        }

        public function get_param($key) {
            return $this->params[$key] ?? null;
        }

        public function set_param($key, $value) {
            $this->params[$key] = $value;
        }

        public function get_header($key) {
            return $this->headers[$key] ?? null;
        }

        public function set_header($key, $value) {
            $this->headers[$key] = $value;
        }

        public function get_file_params() {
            return $_FILES ?? [];
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        private $data;
        private $status;
        private $headers = [];

        public function __construct($data = null, $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }

        public function get_data() {
            return $this->data;
        }

        public function get_status() {
            return $this->status;
        }

        public function header($key, $value) {
            $this->headers[$key] = $value;
            return $this;
        }

        public function get_headers() {
            return $this->headers;
        }
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args = [], $override = false) {
        return true;
    }
}

if (!function_exists('rest_ensure_response')) {
    function rest_ensure_response($data) {
        return $data;
    }
}

if (!function_exists('is_ssl')) {
    function is_ssl() {
        return false;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = []) {
        if (isset($GLOBALS['orabooks_test_wp_next_scheduled_hooks'][$hook])) {
            return $GLOBALS['orabooks_test_wp_next_scheduled_hooks'][$hook];
        }
        return false;
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) {
        $GLOBALS['orabooks_test_wp_next_scheduled_hooks'][$hook] = $timestamp;
        $GLOBALS['orabooks_test_wp_scheduled_events'][] = [
            'hook' => $hook,
            'recurrence' => $recurrence,
            'timestamp' => $timestamp,
        ];
        return true;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') { return 'http://example.com/wp-admin/' . $path; }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($key, $value = null, $url = null) {
        if (is_array($key)) {
            $query = $key;
            $url = $value ?? '';
            $separator = strpos($url, '?') === false ? '?' : '&';
            return $url . $separator . http_build_query($query);
        }

        $separator = strpos($url, '?') === false ? '?' : '&';
        return $url . $separator . urlencode((string) $key) . '=' . urlencode((string) $value);
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
        private $error_data = [];

        public function __construct($code = '', $message = '', $data = '') {
            if ($code) {
                $this->errors[$code][] = $message;
                if ($data !== '') {
                    $this->error_data[$code] = $data;
                }
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

        public function get_error_data($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return $this->error_data[$code] ?? null;
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
        if (array_key_exists('orabooks_test_rate_limit_allowed', $GLOBALS)) {
            return (bool) $GLOBALS['orabooks_test_rate_limit_allowed'];
        }
        return true; // Allow all requests in tests
    }
}

if (!function_exists('orabooks_get_accept_invite_url')) {
    function orabooks_get_accept_invite_url($token = '') {
        $url = 'https://example.com/accept-invite';
        $token = trim((string) $token);
        if ($token === '') {
            return $url;
        }

        return $url . '?token=' . rawurlencode($token);
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
        if (class_exists('OraBooks_AsyncQueue')) {
            return OraBooks_AsyncQueue::enqueue($job_type, $payload, $options);
        }
        return 42; // Mock job ID
    }
}

if (!function_exists('orabooks_log_event')) {
    function orabooks_log_event($event_type, $description, $severity = 'info', $metadata = null, $user_id = null, $org_id = null) {
        $GLOBALS['orabooks_test_log_events'][] = [
            'event_type' => $event_type,
            'description' => $description,
            'severity' => $severity,
            'metadata' => $metadata,
            'user_id' => $user_id,
            'org_id' => $org_id,
        ];
        return true;
    }
}

if (!function_exists('orabooks_publish_event')) {
    function orabooks_publish_event($event_type, $aggregate_id, $payload = []) {
        if (array_key_exists('orabooks_test_publish_event_override', $GLOBALS)) {
            $override = $GLOBALS['orabooks_test_publish_event_override'];
            if ($override === false) {
                return false;
            }
            if ($override instanceof WP_Error) {
                return $override;
            }
            return $override;
        }
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
    if (!empty($GLOBALS['ORABOOKS_LOAD_REAL_SECRETS'])) {
        require_once __DIR__ . '/../includes/class-orabooks-secrets.php';
    } else {
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

        public static function get_totp_provisioning_uri($secret, $email) {
            return 'otpauth://totp/OraBooks%3A' . rawurlencode((string) $email) . '?secret=' . strtoupper((string) $secret) . '&issuer=OraBooks';
        }

        public static function get_totp_qr_url($secret, $email) {
            return 'https://quickchart.io/qr?size=200&text=' . rawurlencode(self::get_totp_provisioning_uri($secret, $email));
        }

        public static function generate_backup_codes() {
            return ['backup-code-001', 'backup-code-002', 'backup-code-003', 'backup-code-004', 'backup-code-005', 'backup-code-006', 'backup-code-007', 'backup-code-008'];
        }

        public static function normalize_totp_code($code) {
            $code = preg_replace('/\D+/', '', (string) $code);
            return strlen($code) === 6 ? $code : '';
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

        public static function verify_google_id_token($id_token, $client_id) {
            if (array_key_exists('orabooks_test_verify_google_id_token_result', $GLOBALS)) {
                return $GLOBALS['orabooks_test_verify_google_id_token_result'];
            }

            $parts = explode('.', (string) $id_token);
            if (count($parts) !== 3) {
                return false;
            }

            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            if (!is_array($payload) || empty($payload['email'])) {
                return false;
            }

            if ((string) ($payload['aud'] ?? '') !== (string) $client_id) {
                return false;
            }

            if (empty($payload['exp']) || (int) $payload['exp'] < time()) {
                // Test tokens omit exp — treat missing exp as valid in unit tests.
                if (!empty($payload['exp'])) {
                    return false;
                }
            }

            return $payload;
        }

        public static function get_jwt_secret() {
            return $GLOBALS['orabooks_test_jwt_secret'] ?? 'test-jwt-secret-with-sufficient-length-for-sl008';
        }

        public static function get_encryption_key() {
            return $GLOBALS['orabooks_test_encryption_key'] ?? 'test-encryption-key-32chars-min!!';
        }

        public static function get_status() {
            if (isset($GLOBALS['orabooks_test_secrets_status'])) {
                return $GLOBALS['orabooks_test_secrets_status'];
            }

            return [
                'production_mode' => false,
                'requires_tls' => false,
                'jwt_secret_configured' => true,
                'encryption_key_configured' => true,
                'jwt_secret_length' => strlen(self::get_jwt_secret()),
                'last_rotated' => '',
                'tls' => ['ok' => true, 'skipped' => true],
                'https_active' => true,
            ];
        }

        public static function requires_tls() {
            return (bool) ($GLOBALS['orabooks_test_requires_tls'] ?? false);
        }

        public static function check_tls_certificate($host = null, $port = 443) {
            return $GLOBALS['orabooks_test_tls_certificate'] ?? [
                'ok' => true,
                'skipped' => true,
                'host' => $host ?: 'localhost',
            ];
        }

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

        public static function redact_sensitive($data) {
            if (!is_array($data)) {
                return $data;
            }
            $redacted = [];
            foreach ($data as $key => $value) {
                if (stripos((string) $key, 'secret') !== false || stripos((string) $key, 'token') !== false) {
                    $redacted[$key] = '[REDACTED]';
                } elseif (is_array($value)) {
                    $redacted[$key] = self::redact_sensitive($value);
                } else {
                    $redacted[$key] = $value;
                }
            }
            return $redacted;
        }

        public static function encrypt_sensitive($plaintext) {
            return 'enc:' . (string) $plaintext;
        }

        public static function decrypt_sensitive($stored) {
            if (strpos((string) $stored, 'enc:') === 0) {
                return substr((string) $stored, 4);
            }
            return (string) $stored;
        }
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
    if (!empty($GLOBALS['ORABOOKS_LOAD_REAL_ORGANIZATION'])) {
        require_once __DIR__ . '/../includes/class-orabooks-organization.php';
    } else {
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

            global $wpdb;
            if ($wpdb->test_get_row_callback !== null) {
                $table = OraBooks_Database::table('organizations');
                $from_db = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table} WHERE id = %d",
                    (int) $org_id
                ));
                if ($from_db && (
                    isset($from_db->organization_type)
                    || isset($from_db->status)
                    || isset($from_db->subdomain)
                    || isset($from_db->name)
                )) {
                    if (!isset($from_db->id)) {
                        $from_db->id = (int) $org_id;
                    }
                    if (!isset($from_db->owner_id)) {
                        $from_db->owner_id = 1;
                    }
                    if (!isset($from_db->organization_type)) {
                        $from_db->organization_type = 'customer';
                    }
                    if (!isset($from_db->tier)) {
                        $from_db->tier = 'free';
                    }
                    if (!isset($from_db->subdomain)) {
                        $from_db->subdomain = 'testorg';
                    }
                    if (!isset($from_db->status)) {
                        $from_db->status = 'active';
                    }
                    if (!isset($from_db->name)) {
                        $from_db->name = 'Test Customer Org';
                    }

                    return $from_db;
                }
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

        public static function get_active_by_subdomain($subdomain) {
            if (isset($GLOBALS['orabooks_test_org_by_subdomain_callback'])) {
                $org = ($GLOBALS['orabooks_test_org_by_subdomain_callback'])($subdomain);
            } else {
                global $wpdb;
                if ($wpdb->test_get_row_callback !== null) {
                    $table = OraBooks_Database::table('organizations');
                    $org = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$table} WHERE subdomain = %s",
                        strtolower(trim((string) $subdomain))
                    ));
                } else {
                    $org = self::get(1);
                    if ($org) {
                        $org->subdomain = strtolower(trim((string) $subdomain));
                    }
                }
            }

            if (!$org) {
                return new WP_Error('org_not_found', 'Organization not found.');
            }

            if (($org->status ?? '') !== 'active') {
                return new WP_Error('org_inactive', 'This organization is not active.');
            }

            return $org;
        }

        public static function change_region($org_id, $region, $admin_id) {
            $org = self::get($org_id);
            if (!$org) {
                return new WP_Error('not_found', 'Organization not found');
            }

            if (($org->organization_type ?? '') !== 'customer' || ($org->tier ?? '') !== 'enterprise') {
                return new WP_Error('invalid_type', 'Region changes are only allowed for enterprise customer organizations.');
            }

            global $wpdb;
            $table = OraBooks_Database::table('organizations');
            $wpdb->update(
                $table,
                ['region' => strtolower(trim((string) $region))],
                ['id' => (int) $org_id],
                ['%s'],
                ['%d']
            );

            return true;
        }

        public static function request_partner_reactivation($org_id, $user_id, $reason) {
            return rand(100, 999); // Returns review ID
        }

        public static function review_reactivation($review_id, $admin_id, $decision, $notes) {
            return true;
        }
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
        $args['method'] = 'POST';
        return wp_remote_request($url, $args);
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = []) {
        $args['method'] = 'GET';
        return wp_remote_request($url, $args);
    }
}

if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = []) {
        if (isset($GLOBALS['orabooks_test_wp_remote_request_callback'])) {
            return ($GLOBALS['orabooks_test_wp_remote_request_callback'])($url, $args);
        }
        if (isset($GLOBALS['orabooks_test_wp_remote_post_callback'])) {
            return ($GLOBALS['orabooks_test_wp_remote_post_callback'])($url, $args);
        }
        // Default: return a mock Google token response for auth tests
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

if (!function_exists('wp_remote_retrieve_headers')) {
    function wp_remote_retrieve_headers($response) {
        if (!is_array($response)) {
            return [];
        }
        if (isset($response['headers'])) {
            return $response['headers'];
        }
        return [];
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
        $reserved = ['admin', 'api', 'app', 'support', 'billing', 'partner', 'orabooks', 'www', 'root'];
        $subdomain = strtolower(trim($subdomain));

        if (in_array($subdomain, $reserved, true)) {
            return 'This subdomain is reserved';
        }
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/', $subdomain)) {
            return 'Subdomain must be 3-63 chars, lowercase alphanumeric with hyphens, no start/end hyphen';
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
            return 'Password must contain at least one uppercase letter';
        }
        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must contain at least one lowercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Password must contain at least one number';
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            return 'Password must contain at least one special character';
        }
        return true;
    }
}

if (!function_exists('orabooks_is_user_logged_in')) {
    function orabooks_is_user_logged_in() {
        return orabooks_get_current_user_id() > 0;
    }
}

if (!function_exists('orabooks_set_auth_token_cookie')) {
    function orabooks_set_auth_token_cookie($token) {
        if (!empty($token)) {
            $_COOKIE['orabooks_token'] = $token;
        }
    }
}

if (!function_exists('orabooks_clear_auth_token_cookie')) {
    function orabooks_clear_auth_token_cookie() {
        unset($_COOKIE['orabooks_token']);
    }
}

if (!function_exists('orabooks_establish_wp_session_for_orabooks_user')) {
    function orabooks_establish_wp_session_for_orabooks_user($orabooks_user_id, $password = '') {
        $GLOBALS['orabooks_test_current_user_id'] = (int) $orabooks_user_id;
        return (int) $orabooks_user_id;
    }
}

if (!function_exists('orabooks_persist_login_session')) {
    function orabooks_persist_login_session($login_result, $password = '') {
        if (!is_array($login_result)) {
            return;
        }
        if (!empty($login_result['token'])) {
            orabooks_set_auth_token_cookie($login_result['token']);
        }
        if (!empty($login_result['refresh_token']) && function_exists('orabooks_set_refresh_token_cookie')) {
            orabooks_set_refresh_token_cookie($login_result['refresh_token']);
        }
        $user_id = !empty($login_result['user_id']) ? (int) $login_result['user_id'] : 0;
        if ($user_id > 0) {
            orabooks_establish_wp_session_for_orabooks_user($user_id, $password);
        }
    }
}

if (!function_exists('orabooks_get_refresh_token_cookie_ttl')) {
    function orabooks_get_refresh_token_cookie_ttl() {
        return 604800;
    }
}

if (!function_exists('orabooks_set_refresh_token_cookie')) {
    function orabooks_set_refresh_token_cookie($token) {
        $GLOBALS['orabooks_test_refresh_token_cookie'] = $token;
    }
}

if (!function_exists('orabooks_clear_refresh_token_cookie')) {
    function orabooks_clear_refresh_token_cookie() {
        unset($GLOBALS['orabooks_test_refresh_token_cookie']);
    }
}

if (!function_exists('orabooks_get_refresh_token_from_request')) {
    function orabooks_get_refresh_token_from_request() {
        return $GLOBALS['orabooks_test_refresh_token_cookie'] ?? '';
    }
}

if (!function_exists('orabooks_redact_client_auth_response')) {
    function orabooks_redact_client_auth_response($payload) {
        if (!is_array($payload)) {
            return $payload;
        }
        unset($payload['refresh_token']);
        return $payload;
    }
}

if (!function_exists('orabooks_set_2fa_secret')) {
    function orabooks_set_2fa_secret($wp_user_id, $secret) {
        $GLOBALS['orabooks_test_user_meta'][$wp_user_id]['orabooks_2fa_secret'] = $secret;
    }
}

if (!function_exists('orabooks_get_2fa_secret')) {
    function orabooks_get_2fa_secret($wp_user_id) {
        return $GLOBALS['orabooks_test_user_meta'][$wp_user_id]['orabooks_2fa_secret'] ?? '';
    }
}

if (!function_exists('orabooks_set_2fa_temp_secret')) {
    function orabooks_set_2fa_temp_secret($wp_user_id, $secret) {
        $GLOBALS['orabooks_test_user_meta'][$wp_user_id]['orabooks_2fa_temp_secret'] = $secret;
    }
}

if (!function_exists('orabooks_get_2fa_temp_secret')) {
    function orabooks_get_2fa_temp_secret($wp_user_id) {
        return $GLOBALS['orabooks_test_user_meta'][$wp_user_id]['orabooks_2fa_temp_secret'] ?? '';
    }
}

if (!function_exists('orabooks_set_2fa_temp_backup_codes')) {
    function orabooks_set_2fa_temp_backup_codes($wp_user_id, array $codes) {
        $GLOBALS['orabooks_test_user_meta'][$wp_user_id]['orabooks_2fa_temp_backup_codes'] = $codes;
    }
}

if (!function_exists('orabooks_get_2fa_temp_backup_codes')) {
    function orabooks_get_2fa_temp_backup_codes($wp_user_id) {
        $codes = $GLOBALS['orabooks_test_user_meta'][$wp_user_id]['orabooks_2fa_temp_backup_codes'] ?? [];
        return is_array($codes) ? $codes : [];
    }
}

if (!function_exists('orabooks_set_2fa_backup_codes_encrypted')) {
    function orabooks_set_2fa_backup_codes_encrypted($wp_user_id, array $codes) {
        $GLOBALS['orabooks_test_user_meta'][$wp_user_id]['orabooks_2fa_backup_codes_encrypted'] = $codes;
    }
}

if (!function_exists('orabooks_get_2fa_backup_codes_encrypted')) {
    function orabooks_get_2fa_backup_codes_encrypted($wp_user_id) {
        $codes = $GLOBALS['orabooks_test_user_meta'][$wp_user_id]['orabooks_2fa_backup_codes_encrypted'] ?? [];
        return is_array($codes) ? $codes : [];
    }
}

if (!function_exists('orabooks_normalize_backup_code')) {
    function orabooks_normalize_backup_code($code) {
        return strtoupper(preg_replace('/\s+/', '', (string) $code));
    }
}

if (!function_exists('orabooks_resolve_tier_selection_user_id')) {
    function orabooks_resolve_tier_selection_user_id($token = '') {
        return 0;
    }
}

if (!function_exists('orabooks_clear_logout_landing_cookie')) {
    function orabooks_clear_logout_landing_cookie() {
        unset($GLOBALS['orabooks_test_logout_cookie']);
    }
}

if (!function_exists('wp_logout')) {
    function wp_logout() {
        $GLOBALS['orabooks_test_current_user_id'] = 0;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $GLOBALS['orabooks_test_options'][$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        $GLOBALS['orabooks_test_options'][$option] = $value;
        return true;
    }
}

if (!function_exists('orabooks_get_org_workspace_url')) {
    function orabooks_get_org_workspace_url($org_id, $path = '/dashboard/', $query_args = []) {
        $url = home_url('/' . ltrim((string) $path, '/'));
        if (!empty($query_args)) {
            $url = add_query_arg($query_args, $url);
        }
        return $url;
    }
}

if (!function_exists('orabooks_get_network_login_url')) {
    function orabooks_get_network_login_url($path = 'login') {
        return home_url('/' . ltrim((string) $path, '/') . '/');
    }
}

if (!function_exists('orabooks_get_wp_user_id_for_orabooks_user')) {
    function orabooks_get_wp_user_id_for_orabooks_user($orabooks_user_id) {
        return (int) ($GLOBALS['orabooks_test_current_user_id'] ?? 0);
    }
}

if (!function_exists('orabooks_ensure_wp_user_link_for_orabooks_user')) {
    function orabooks_ensure_wp_user_link_for_orabooks_user($orabooks_user_id) {
        return (int) ($GLOBALS['orabooks_test_current_user_id'] ?? 0);
    }
}

if (!function_exists('orabooks_get_logout_redirect_url')) {
    function orabooks_get_logout_redirect_url() {
        return add_query_arg('logged_out', '1', orabooks_get_network_login_url('login'));
    }
}

if (!function_exists('orabooks_destroy_auth_session')) {
    function orabooks_destroy_auth_session($user_id = 0, $log = true) {
        orabooks_clear_auth_token_cookie();
        $GLOBALS['orabooks_test_current_user_id'] = 0;
    }
}

if (!function_exists('orabooks_build_org_url')) {
    function orabooks_build_org_url($subdomain, $path = '/') {
        $path = '/' . ltrim((string) $path, '/');
        return 'https://' . $subdomain . '.orabooks.app' . $path;
    }
}

if (!function_exists('orabooks_ensure_org_multisite_site')) {
    function orabooks_ensure_org_multisite_site($org_id) {
        return true;
    }
}

if (!function_exists('orabooks_is_network_auth_host')) {
    function orabooks_is_network_auth_host() {
        return true;
    }
}

if (!function_exists('orabooks_get_platform_admin_url')) {
    function orabooks_get_platform_admin_url() {
        return admin_url('admin.php?page=orabooks');
    }
}

if (!function_exists('orabooks_orabooks_user_can_manage_platform')) {
    function orabooks_orabooks_user_can_manage_platform($orabooks_user_id) {
        return !empty($GLOBALS['orabooks_test_platform_admin_user_ids'][$orabooks_user_id]);
    }
}

if (!function_exists('orabooks_auth_error_status_code')) {
    function orabooks_auth_error_status_code($error_code) {
        $map = [
            'subdomain_mismatch' => 403,
            'email_not_verified' => 403,
            'rate_limit' => 429,
            'invalid_credentials' => 401,
        ];
        return $map[(string) $error_code] ?? 400;
    }
}

if (!function_exists('orabooks_resolve_authenticated_user_id')) {
    function orabooks_resolve_authenticated_user_id() {
        return orabooks_get_current_user_id();
    }
}

if (!function_exists('orabooks_has_completed_partner_onboarding')) {
    function orabooks_has_completed_partner_onboarding($user_id) {
        $user_id = (int) $user_id;
        $wp_user_id = orabooks_get_wp_user_id_for_orabooks_user($user_id);
        if ($wp_user_id <= 0) {
            return false;
        }
        return (bool) get_user_meta($wp_user_id, 'orabooks_partner_onboarding_completed', true);
    }
}

if (!function_exists('orabooks_mark_partner_onboarding_completed')) {
    function orabooks_mark_partner_onboarding_completed($user_id) {
        $user_id = (int) $user_id;
        $wp_user_id = orabooks_get_wp_user_id_for_orabooks_user($user_id);
        if ($wp_user_id <= 0) {
            return false;
        }
        update_user_meta($wp_user_id, 'orabooks_partner_onboarding_completed', '1');
        return true;
    }
}

if (!function_exists('orabooks_get_partner_post_login_path')) {
    function orabooks_get_partner_post_login_path($user_id) {
        return orabooks_has_completed_partner_onboarding($user_id)
            ? '/partner-program/'
            : '/partner/onboarding/';
    }
}

if (!function_exists('orabooks_enrich_login_response')) {
    function orabooks_enrich_login_response($login_result) {
        if (!is_array($login_result)) {
            return $login_result;
        }
        if (!isset($login_result['redirect_to'])) {
            $login_result['redirect_to'] = orabooks_get_network_login_url('dashboard');
        }
        return $login_result;
    }
}

if (!function_exists('orabooks_get_allowed_regions')) {
    function orabooks_get_allowed_regions() {
        return ['us-east', 'eu-west-1', 'ap-southeast-1'];
    }
}

if (!function_exists('orabooks_get_default_region_for_tier')) {
    function orabooks_get_default_region_for_tier($tier) {
        return 'us-east';
    }
}

if (!function_exists('orabooks_validate_org_region')) {
    function orabooks_validate_org_region($region, $tier) {
        $region = strtolower(trim((string) $region));
        $allowed = orabooks_get_allowed_regions();

        if ($tier === 'enterprise') {
            if ($region === '') {
                return 'Please select a data residency region.';
            }
            if (!in_array($region, $allowed, true)) {
                return 'Invalid region selected.';
            }
            return true;
        }

        if ($region !== '' && $region !== orabooks_get_default_region_for_tier($tier)) {
            return 'Region cannot be changed for this plan.';
        }

        return true;
    }
}

if (!function_exists('orabooks_org_allows_subdomain_access')) {
    function orabooks_org_allows_subdomain_access($org) {
        if (!$org) {
            return false;
        }
        if (($org->organization_type ?? '') === 'partner') {
            return in_array($org->status ?? '', ['active', 'pending_setup', 'payout_hold'], true);
        }
        return ($org->status ?? '') === 'active';
    }
}

if (!function_exists('orabooks_get_verified_jwt_payload')) {
    function orabooks_get_verified_jwt_payload() {
        return $GLOBALS['orabooks_test_jwt_payload'] ?? null;
    }
}

if (!function_exists('orabooks_user_belongs_to_org')) {
    function orabooks_user_belongs_to_org($user_id, $org_id) {
        $user_id = (int) $user_id;
        $org_id = (int) $org_id;
        if ($user_id <= 0 || $org_id <= 0) {
            return false;
        }

        global $wpdb;
        $table_user_org = OraBooks_Database::table('user_org');
        $member = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$table_user_org} WHERE user_id = %d AND org_id = %d LIMIT 1",
            $user_id,
            $org_id
        ));
        if ($member) {
            return true;
        }

        $table_orgs = OraBooks_Database::table('organizations');
        $owner = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$table_orgs} WHERE id = %d AND owner_id = %d LIMIT 1",
            $org_id,
            $user_id
        ));

        return (bool) $owner;
    }
}

if (!function_exists('orabooks_assert_tenant_access')) {
    function orabooks_assert_tenant_access($user_id, $org_id, $require_active = false) {
        $user_id = (int) $user_id;
        $org_id = (int) $org_id;

        if ($org_id <= 0) {
            return new WP_Error('no_org', 'No organization found.');
        }

        if ($user_id <= 0) {
            return new WP_Error('not_authenticated', 'Authentication required.');
        }

        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            if (!orabooks_user_belongs_to_org($user_id, $org_id)) {
                return new WP_Error('tenant_isolation', 'You do not have access to this organization.');
            }

            $payload = orabooks_get_verified_jwt_payload();
            if ($payload && !empty($payload['org_id']) && (int) $payload['org_id'] !== $org_id) {
                return new WP_Error('tenant_isolation', 'Organization context mismatch.');
            }
        }

        if ($require_active && class_exists('OraBooks_Organization')) {
            $org = OraBooks_Organization::get($org_id);
            if (!$org || ($org->status ?? '') !== 'active') {
                return new WP_Error('org_inactive', 'Your organization is not active. Please contact support.');
            }
        }

        return true;
    }
}

if (!function_exists('orabooks_resolve_request_org_id')) {
    function orabooks_resolve_request_org_id($user_id, $requested_org_id = 0) {
        $user_id = (int) $user_id;
        $requested_org_id = (int) $requested_org_id;
        $is_platform_admin = function_exists('current_user_can') && current_user_can('manage_options');

        if ($requested_org_id > 0) {
            if (!$is_platform_admin) {
                $allowed = orabooks_assert_tenant_access($user_id, $requested_org_id, false);
                if (is_wp_error($allowed)) {
                    return 0;
                }
            }
            return $requested_org_id;
        }

        if ($user_id > 0 && function_exists('orabooks_get_current_org_id')) {
            return (int) orabooks_get_current_org_id($user_id);
        }

        return 0;
    }
}

if (!function_exists('orabooks_get_pending_invite_for_email')) {
    function orabooks_get_pending_invite_for_email($email) {
        if (isset($GLOBALS['orabooks_test_pending_invite_for_email_callback'])) {
            return ($GLOBALS['orabooks_test_pending_invite_for_email_callback'])($email);
        }

        return null;
    }
}

if (!function_exists('orabooks_user_has_any_pending_invite')) {
    function orabooks_user_has_any_pending_invite($user_id) {
        if (isset($GLOBALS['orabooks_test_user_has_pending_invite_callback'])) {
            return (bool) ($GLOBALS['orabooks_test_user_has_pending_invite_callback'])($user_id);
        }

        return false;
    }
}

if (!function_exists('orabooks_resolve_auth_org_id')) {
    function orabooks_resolve_auth_org_id($user_id, $stored_org_id = 0) {
        $user_id = (int) $user_id;
        $stored_org_id = (int) $stored_org_id;

        if ($user_id > 0 && function_exists('orabooks_get_current_org_id')) {
            $resolved = (int) orabooks_get_current_org_id($user_id);
            if ($resolved > 0) {
                return $resolved;
            }
        }

        return max(0, $stored_org_id);
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

if (!function_exists('orabooks_users_can_register')) {
    function orabooks_users_can_register() {
        return true;
    }
}

if (!function_exists('orabooks_multisite_uses_signup_activation')) {
    function orabooks_multisite_uses_signup_activation() {
        return false;
    }
}

if (!function_exists('orabooks_generate_username_from_email')) {
    function orabooks_generate_username_from_email($email) {
        $local = strstr($email, '@', true);
        return sanitize_user($local ?: 'user', true) ?: 'user';
    }
}

if (!function_exists('orabooks_create_wp_user_for_registration')) {
    function orabooks_create_wp_user_for_registration($email, $password, $meta = []) {
        return 0;
    }
}

if (!function_exists('orabooks_resolve_user_id')) {
    function orabooks_resolve_user_id($user_id = 0) {
        return (int) ($user_id ?: orabooks_get_current_user_id());
    }
}

// ---------------------------------------------------------------------------
// 9. Load the actual classes under test
// ---------------------------------------------------------------------------
$rbac_file = __DIR__ . '/../includes/class-orabooks-rbac.php';
if (!file_exists($rbac_file)) {
    echo "ERROR: Cannot find class-orabooks-rbac.php at {$rbac_file}\n";
    exit(1);
}
require_once $rbac_file;

$access_control_file = __DIR__ . '/../includes/class-obn-access-control.php';
if (!file_exists($access_control_file)) {
    echo "ERROR: Cannot find class-obn-access-control.php at {$access_control_file}\n";
    exit(1);
}
require_once $access_control_file;

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

$two_factor_file = __DIR__ . '/../includes/class-orabooks-two-factor.php';
if (file_exists($two_factor_file)) {
    require_once $two_factor_file;
}

$customers_file = __DIR__ . '/../includes/class-orabooks-customers.php';
if (!file_exists($customers_file)) {
    echo "ERROR: Cannot find class-orabooks-customers.php at {$customers_file}\n";
    exit(1);
}
require_once $customers_file;

$ar_wallet_file = __DIR__ . '/../includes/class-orabooks-ar-wallet.php';
if (file_exists($ar_wallet_file)) {
    require_once $ar_wallet_file;
}

$invoice_document_file = __DIR__ . '/../includes/class-orabooks-invoice-document.php';
if (file_exists($invoice_document_file)) {
    require_once $invoice_document_file;
}

$notifications_file = __DIR__ . '/../includes/class-orabooks-notifications.php';
if (!file_exists($notifications_file)) {
    echo "ERROR: Cannot find class-orabooks-notifications.php at {$notifications_file}\n";
    exit(1);
}
require_once $notifications_file;

$commission_file = __DIR__ . '/../includes/class-orabooks-commission.php';
if (!file_exists($commission_file)) {
    echo "ERROR: Cannot find class-orabooks-commission.php at {$commission_file}\n";
    exit(1);
}
require_once $commission_file;

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

$financial_reports_file = __DIR__ . '/../includes/class-orabooks-financial-reports.php';
if (!file_exists($financial_reports_file)) {
    echo "ERROR: Cannot find class-orabooks-financial-reports.php at {$financial_reports_file}\n";
    exit(1);
}
require_once $financial_reports_file;

$operational_reports_file = __DIR__ . '/../includes/class-orabooks-operational-reports.php';
if (!file_exists($operational_reports_file)) {
    echo "ERROR: Cannot find class-orabooks-operational-reports.php at {$operational_reports_file}\n";
    exit(1);
}
require_once $operational_reports_file;

$observability_file = __DIR__ . '/../includes/class-orabooks-observability.php';
if (!file_exists($observability_file)) {
    echo "ERROR: Cannot find class-orabooks-observability.php at {$observability_file}\n";
    exit(1);
}
require_once $observability_file;

$workflow_file = __DIR__ . '/../includes/class-orabooks-workflow.php';
if (!file_exists($workflow_file)) {
    echo "ERROR: Cannot find class-orabooks-workflow.php at {$workflow_file}\n";
    exit(1);
}
require_once $workflow_file;

$workflow_integration_file = __DIR__ . '/../includes/class-orabooks-workflow-integration.php';
if (file_exists($workflow_integration_file)) {
    require_once $workflow_integration_file;
}

if (class_exists('OraBooks_Workflow')) {
    OraBooks_Workflow::init();
    $GLOBALS['orabooks_workflow_bootstrap_filters'] = [];
    foreach (['orabooks_workflow_preconditions', 'orabooks_workflow_after_transition', 'orabooks_workflow_row_updates', 'orabooks_workflow_state_machines'] as $tag) {
        if (!empty($GLOBALS['orabooks_test_filters'][$tag])) {
            $GLOBALS['orabooks_workflow_bootstrap_filters'][$tag] = $GLOBALS['orabooks_test_filters'][$tag];
        }
    }
}

$eventbus_file = __DIR__ . '/../includes/class-orabooks-event-bus.php';
if (!file_exists($eventbus_file)) {
    echo "ERROR: Cannot find class-orabooks-event-bus.php at {$eventbus_file}\n";
    exit(1);
}
require_once $eventbus_file;

$async_queue_file = __DIR__ . '/../includes/class-orabooks-async-queue.php';
if (!file_exists($async_queue_file)) {
    echo "ERROR: Cannot find class-orabooks-async-queue.php at {$async_queue_file}\n";
    exit(1);
}
require_once $async_queue_file;

$jobs_loader_file = __DIR__ . '/../includes/jobs/loader.php';
if (file_exists($jobs_loader_file)) {
    require_once $jobs_loader_file;
}

$event_module_file = __DIR__ . '/../includes/events/loader.php';
if (!file_exists($event_module_file)) {
    echo "ERROR: Cannot find SL-302 event module at {$event_module_file}\n";
    exit(1);
}
require_once $event_module_file;

$classification_file = __DIR__ . '/../includes/class-orabooks-classification.php';
if (!file_exists($classification_file)) {
    echo "ERROR: Cannot find class-orabooks-classification.php at {$classification_file}\n";
    exit(1);
}
require_once $classification_file;

$csv_imports_file = __DIR__ . '/../includes/class-orabooks-csv-imports.php';
if (!file_exists($csv_imports_file)) {
    echo "ERROR: Cannot find class-orabooks-csv-imports.php at {$csv_imports_file}\n";
    exit(1);
}
require_once $csv_imports_file;

$attachments_file = __DIR__ . '/../includes/class-orabooks-attachments.php';
if (!file_exists($attachments_file)) {
    echo "ERROR: Cannot find class-orabooks-attachments.php at {$attachments_file}\n";
    exit(1);
}
require_once $attachments_file;

$ai_review_file = __DIR__ . '/../includes/class-orabooks-ai-review.php';
if (!file_exists($ai_review_file)) {
    echo "ERROR: Cannot find class-orabooks-ai-review.php at {$ai_review_file}\n";
    exit(1);
}
require_once $ai_review_file;

$expenses_file = __DIR__ . '/../includes/class-orabooks-expenses.php';
if (!file_exists($expenses_file)) {
    echo "ERROR: Cannot find class-orabooks-expenses.php at {$expenses_file}\n";
    exit(1);
}
require_once $expenses_file;

$voice_file = __DIR__ . '/../includes/class-orabooks-voice.php';
if (!file_exists($voice_file)) {
    echo "ERROR: Cannot find class-orabooks-voice.php at {$voice_file}\n";
    exit(1);
}
require_once $voice_file;

$ai_providers_file = __DIR__ . '/../includes/class-orabooks-ai-providers.php';
if (!file_exists($ai_providers_file)) {
    echo "ERROR: Cannot find class-orabooks-ai-providers.php at {$ai_providers_file}\n";
    exit(1);
}
require_once $ai_providers_file;

$security_file = __DIR__ . '/../includes/class-orabooks-security.php';
if (!file_exists($security_file)) {
    echo "ERROR: Cannot find class-orabooks-security.php at {$security_file}\n";
    exit(1);
}
require_once $security_file;

$posting_file = __DIR__ . '/../includes/class-orabooks-posting.php';
if (!file_exists($posting_file)) {
    echo "ERROR: Cannot find class-orabooks-posting.php at {$posting_file}\n";
    exit(1);
}

$approval_file = __DIR__ . '/../includes/class-orabooks-approval.php';
if (!file_exists($approval_file)) {
    echo "ERROR: Cannot find class-orabooks-approval.php at {$approval_file}\n";
    exit(1);
}
require_once $approval_file;
require_once $posting_file;

$coa_file = __DIR__ . '/../includes/class-orabooks-coa.php';
if (!file_exists($coa_file)) {
    echo "ERROR: Cannot find class-orabooks-coa.php at {$coa_file}\n";
    exit(1);
}
require_once $coa_file;

$fiscal_file = __DIR__ . '/../includes/class-orabooks-fiscal.php';
if (!file_exists($fiscal_file)) {
    echo "ERROR: Cannot find class-orabooks-fiscal.php at {$fiscal_file}\n";
    exit(1);
}
require_once $fiscal_file;

$pwa_file = __DIR__ . '/../includes/class-orabooks-pwa.php';
if (!file_exists($pwa_file)) {
    echo "ERROR: Cannot find class-orabooks-pwa.php at {$pwa_file}\n";
    exit(1);
}
require_once $pwa_file;

$rest_api_file = __DIR__ . '/../includes/class-orabooks-rest-api.php';
if (!file_exists($rest_api_file)) {
    echo "ERROR: Cannot find class-orabooks-rest-api.php at {$rest_api_file}\n";
    exit(1);
}
require_once $rest_api_file;

if (!function_exists('get_users')) {
    function get_users($args = []) {
        return [];
    }
}

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

if (!function_exists('orabooks_hash_email')) {
    function orabooks_hash_email($email) {
        $email = strtolower(trim((string) $email));
        return $email === '' ? '' : hash('sha256', $email);
    }
}

if (!function_exists('orabooks_get_correlation_id')) {
    function orabooks_get_correlation_id($generate = true) {
        if (!empty($GLOBALS['orabooks_correlation_id'])) {
            return (string) $GLOBALS['orabooks_correlation_id'];
        }
        if (!$generate) {
            return '';
        }
        $GLOBALS['orabooks_correlation_id'] = orabooks_uuid();
        return $GLOBALS['orabooks_correlation_id'];
    }
}

if (!function_exists('orabooks_set_correlation_id')) {
    function orabooks_set_correlation_id($correlation_id) {
        $correlation_id = trim((string) $correlation_id);
        if ($correlation_id !== '') {
            $GLOBALS['orabooks_correlation_id'] = $correlation_id;
        }
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title) {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string) $title));
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
    if (!empty($GLOBALS['ORABOOKS_LOAD_REAL_AUDIT'])) {
        require_once __DIR__ . '/../includes/class-orabooks-audit.php';
    } else {
    class OraBooks_Audit {
        public static function log_event($event_type, $description, $severity = 'info', $metadata = null, $user_id = null, $org_id = null, $correlation_id = null) {
            global $wpdb;

            if ($correlation_id === null || trim((string) $correlation_id) === '') {
                $correlation_id = function_exists('orabooks_get_correlation_id')
                    ? orabooks_get_correlation_id(true)
                    : orabooks_uuid();
            }

            $masked = [];
            if (is_array($metadata)) {
                foreach ($metadata as $key => $value) {
                    $key_lower = strtolower((string) $key);
                    if (strpos($key_lower, 'password') !== false
                        || strpos($key_lower, 'token') !== false
                        || strpos($key_lower, 'secret') !== false
                        || strpos($key_lower, 'key') !== false) {
                        $masked[$key] = '[REDACTED]';
                        continue;
                    }

                    if (preg_match('/(^|_)email$/', $key_lower) && is_string($value) && $value !== '') {
                        $masked[$key . '_masked'] = orabooks_mask_email($value);
                        $masked[$key . '_hash'] = orabooks_hash_email($value);
                        continue;
                    }

                    $masked[$key] = $value;
                }
            }

            $row = [
                'org_id' => (int) ($org_id ?: 0),
                'user_id' => $user_id,
                'event_type' => (string) $event_type,
                'severity' => (string) $severity,
                'description' => (string) $description,
                'correlation_id' => (string) $correlation_id,
                'metadata' => !empty($masked) ? wp_json_encode($masked) : null,
            ];

            $wpdb->insert(OraBooks_Database::table('audit_logs'), $row);

            return true;
        }

        public static function get_logs($org_id, $args = []) {
            global $wpdb;

            $rows = $wpdb->get_results('SELECT * FROM ' . OraBooks_Database::table('audit_logs'));

            if (empty($args['skip_view_log'])) {
                self::log_event('audit_log_viewed', 'Audit log accessed', 'info', [], orabooks_get_current_user_id(), $org_id);
            }

            return $rows;
        }

        public static function archive_old_logs() {
            global $wpdb;

            $wpdb->query('SET @orabooks_audit_archival = 1');
            self::log_event('audit_log_archival', 'Audit log archival completed', 'info', ['records_moved' => 0], null, 0);
            $wpdb->query('SET @orabooks_audit_archival = NULL');

            return true;
        }
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

// OraBooks_COA and OraBooks_Fiscal are loaded from includes/ above.

// Stub OraBooks_Posting
if (!class_exists('OraBooks_Posting', false)) {
    class OraBooks_Posting {
        public static function create_journal($data, $user_id) { return 1; }
        public static function add_lines($journal_id, $lines) { return true; }
        public static function approve_journal($journal_id, $user_id) { return true; }
        public static function post_journal($journal_id, $user_id) { return true; }
    }
}
