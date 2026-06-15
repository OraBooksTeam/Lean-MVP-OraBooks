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
    public $test_get_var_callback    = null;
    public $test_get_row_callback    = null;
    public $test_get_results_callback = null;

    /** Register SHOW COLUMNS results so dbDelta calls don't crash */
    private $show_columns_cache = [];

    public function __construct() {}

        public function get_charset_collate() {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        public function prepare($query, ...$args) {
            if (strpos($query, '%s') !== false || strpos($query, '%d') !== false || strpos($query, '%f') !== false) {
                $escaped = [];
                foreach ($args as $arg) {
                    if (is_int($arg) || is_float($arg)) {
                        $escaped[] = $arg;
                    } elseif ($arg === null) {
                        $escaped[] = "''";
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
            // Return mock org_id for user_org lookups
            if (stripos($this->last_query, 'orabooks_user_org') !== false) {
                return 1; // org_id = 1
            }
            if (stripos($this->last_query, 'SELECT COUNT') !== false) {
                return 5; // total exports
            }
            if (stripos($this->last_query, 'orabooks_organizations') !== false) {
                return 'Test Org';
            }
            if (stripos($this->last_query, 'orabooks_users') !== false && stripos($this->last_query, 'SELECT email') !== false) {
                return 'user@example.com';
            }
            if (stripos($this->last_query, 'SUM(download_count)') !== false) {
                return 42;
            }
            if (stripos($this->last_query, 'COUNT(*)') !== false && stripos($this->last_query, 'INTERVAL 24 HOUR') !== false) {
                return 3;
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
            $this->insert_id = rand(100, 999);
            return $this->insert_id;
        }

        public function update($table, $data, $where, $format = [], $where_format = []) {
            return 1; // 1 row affected
        }

        public function query($query) {
            $this->last_query = $query;
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
    function get_current_user_id() { return 1; }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        // Admins can do everything
        return $capability === 'manage_options' ? true : true;
    }
}

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

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) { return json_encode($data); }
}

if (!function_exists('get_transient')) {
    function get_transient($key) { return false; }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0) { return true; }
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
    function orabooks_has_permission($user_id, $org_id, $permission) {
        return true; // Allow all in tests
    }
}

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
// 5. Load the actual class under test
// ---------------------------------------------------------------------------
$exports_file = __DIR__ . '/../includes/class-orabooks-exports.php';
if (!file_exists($exports_file)) {
    echo "ERROR: Cannot find class-orabooks-exports.php at {$exports_file}\n";
    exit(1);
}
require_once $exports_file;
