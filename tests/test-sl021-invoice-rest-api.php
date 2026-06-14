<?php
/**
 * SL-021 Invoice REST API — Standalone Integration Test
 *
 * Simulates POST /invoice (create) and GET /invoice (list/retrieve) via
 * the REST controller using mock WP_REST_Request objects.
 *
 * Run: php tests/test-sl021-invoice-rest-api.php
 * Requires: PHP 8.0+, SQLite (for in-memory testing)
 */

// ── Bootstrap: define ABSPATH and WP constants ──────────────────────────────
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
define('ORABOOKS_TEST_MODE', true);

// ════════════════════════════════════════════════════════════════════════════
// SECTION 1: WordPress Core Test Doubles
// ════════════════════════════════════════════════════════════════════════════

if (!function_exists('current_time')) {
    function current_time($type = 'mysql', $gmt = 0) {
        if ($type === 'mysql') return date('Y-m-d H:i:s');
        if ($type === 'timestamp') return time();
        return date('Y-m-d H:i:s');
    }
}
if (!function_exists('wp_verify_nonce')) { function wp_verify_nonce($nonce, $action) { return true; } }
if (!function_exists('is_user_logged_in')) { function is_user_logged_in() { return true; } }
if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        global $test_current_user_id;
        return isset($test_current_user_id) ? $test_current_user_id : 1;
    }
}
if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key, $single = false) {
        if ($key === 'is_partner') return true;
        return false;
    }
}
if (!function_exists('wp_next_scheduled')) { function wp_next_scheduled($hook) { return false; } }
if (!function_exists('wp_schedule_event')) { function wp_schedule_event($timestamp, $recurrence, $hook) {} }
if (!function_exists('wp_cache_get')) { function wp_cache_get($key, $group = '') { return false; } }
if (!function_exists('wp_cache_set')) { function wp_cache_set($key, $data, $group = '', $expire = 0) {} }
if (!function_exists('wp_cache_delete')) { function wp_cache_delete($key, $group = '') {} }
if (!function_exists('apply_filters')) { function apply_filters($tag, $value, ...$args) { return $value; } }
if (!function_exists('do_action')) {
    function do_action($tag, ...$args) {
        global $test_events;
        if (!isset($test_events)) $test_events = [];
        $test_events[] = ['tag' => $tag, 'args' => $args];
    }
}
if (!function_exists('add_action')) { function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {} }
if (!function_exists('add_filter')) { function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {} }
if (!function_exists('__')) { function __($text, $domain = 'default') { return $text; } }
if (!function_exists('esc_html__')) { function esc_html__($text, $domain = 'default') { return $text; } }
if (!function_exists('esc_attr__')) { function esc_attr__($text, $domain = 'default') { return $text; } }
if (!function_exists('esc_html')) { function esc_html($text) { return $text; } }
if (!function_exists('esc_attr')) { function esc_attr($text) { return $text; } }
if (!function_exists('esc_js')) { function esc_js($text) { return $text; } }
if (!function_exists('esc_url')) { function esc_url($url) { return $url; } }
if (!function_exists('wp_create_nonce')) { function wp_create_nonce($action) { return 'test-nonce'; } }
if (!function_exists('admin_url')) { function admin_url($path = '') { return '/wp-admin/' . $path; } }
if (!function_exists('home_url')) { function home_url($path = '') { return 'https://example.com/' . $path; } }
if (!function_exists('is_multisite')) { function is_multisite() { return false; } }
if (!function_exists('is_wp_error')) { function is_wp_error($thing) { return ($thing instanceof WP_Error); } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($str) { return $str; } }
if (!function_exists('sanitize_textarea_field')) { function sanitize_textarea_field($str) { return $str; } }
if (!function_exists('flush_rewrite_rules')) { function flush_rewrite_rules($soft = true) {} }
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $test_option_overrides;
        if (isset($test_option_overrides[$option])) {
            return $test_option_overrides[$option];
        }
        if (strpos($option, 'orabooks_org_config_') === 0) return [];
        return $default;
    }
}
if (!function_exists('update_option')) { function update_option($option, $value, $autoload = null) {} }
if (!function_exists('sanitize_key')) { function sanitize_key($key) { return $key; } }
if (!function_exists('get_user_by')) {
    function get_user_by($field, $value) {
        return (object) ['ID' => $value, 'display_name' => 'Test Customer', 'user_email' => 'customer@test.com'];
    }
}
if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []) {
        if (is_object($args)) $args = get_object_vars($args);
        return array_merge($defaults, (array) $args);
    }
}
if (!function_exists('wp_json_encode')) { function wp_json_encode($data) { return json_encode($data); } }

// ── Mock WP_Error ───────────────────────────────────────────────────────────
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code; private $message; private $data;
        public function __construct($code = '', $message = '', $data = '') {
            $this->code = $code; $this->message = $message; $this->data = $data;
        }
        public function get_error_code() { return $this->code; }
        public function get_error_message() { return $this->message; }
    }
}

// ── Mock WP_REST_Server ─────────────────────────────────────────────────────
if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server {
        const READABLE   = 'GET';
        const CREATABLE  = 'POST';
        const EDITABLE   = 'PUT';
        const DELETABLE  = 'DELETE';
        const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
    }
}

// ── Mock WP_REST_Request ────────────────────────────────────────────────────
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private $params = [];
        private $method = 'GET';
        private $route = '';

        public function __construct($method = 'GET', $route = '') {
            $this->method = $method;
            $this->route = $route;
        }

        public function set_param($key, $value) {
            $this->params[$key] = $value;
        }

        public function get_param($key) {
            return isset($this->params[$key]) ? $this->params[$key] : null;
        }

        public function get_params() {
            return $this->params;
        }

        public function has_param($key) {
            return array_key_exists($key, $this->params);
        }

        public function get_method() { return $this->method; }
        public function get_route() { return $this->route; }
    }
}

// ── Mock WP_REST_Response ───────────────────────────────────────────────────
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public $data;
        public $status;

        public function __construct($data = null, $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }

        public function get_data() { return $this->data; }
        public function get_status() { return $this->status; }
    }
}

// ── Mock register_rest_route ────────────────────────────────────────────────
if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args = [], $override = false) {
        global $test_rest_routes;
        if (!isset($test_rest_routes)) $test_rest_routes = [];
        $test_rest_routes[] = ['namespace' => $namespace, 'route' => $route, 'args' => $args];
    }
}

// ── Mock dbDelta ────────────────────────────────────────────────────────────
if (!function_exists('dbDelta')) {
    function dbDelta($sql) {
        global $wpdb;
        if ($wpdb && method_exists($wpdb, 'dbDelta')) {
            return $wpdb->dbDelta($sql);
        }
    }
}

// ── Mock WP_Rewrite ────────────────────────────────────────────────────────
if (!class_exists('WP_Rewrite')) {
    class WP_Rewrite {}
}

// ── Mock error_log to stdout ───────────────────────────────────────────────
if (!function_exists('error_log')) {
    function error_log($message) {
        echo "  [LOG] {$message}\n";
    }
}

// ── Define WordPress constants ─────────────────────────────────────────────
if (!defined('DAY_IN_SECONDS')) { define('DAY_IN_SECONDS', 86400); }
if (!defined('OBJECT')) { define('OBJECT', 'OBJECT'); }
if (!defined('OBJECT_K')) { define('OBJECT_K', 'OBJECT_K'); }
if (!defined('ARRAY_A')) { define('ARRAY_A', 'ARRAY_A'); }
if (!defined('ARRAY_N')) { define('ARRAY_N', 'ARRAY_N'); }

// ════════════════════════════════════════════════════════════════════════════
// SECTION 2: MySQL-to-SQLite Database Layer
// ════════════════════════════════════════════════════════════════════════════

function mysql_to_sqlite_ddl($sql) {
    // Strip comments
    $sql = preg_replace('#/\*.*?\*/#s', '', $sql);
    $sql = preg_replace('#-- .*$#m', '', $sql);

    // Remove CHARSET/COLLATE/CHARACTER SET
    $sql = preg_replace('/\s*(DEFAULT\s+)?(CHARACTER\s+SET|CHARSET|COLLATE)\s+\w+/i', '', $sql);

    // Remove COMMENT clauses
    $sql = preg_replace("/\s+COMMENT\s+'[^']*'/i", '', $sql);

    // Convert id AUTO_INCREMENT columns to INTEGER PRIMARY KEY AUTOINCREMENT
    // Handles both `id` (backticked) and id (bare column names)
    $sql = preg_replace(
        '/`?id`?\s+(?:bigint|int)(?:\(\d+\))?\s+(?:NOT\s+NULL\s+)?(?:UNIQUE\s+)?(?:PRIMARY\s+KEY\s+)?AUTO_INCREMENT/i',
        'id INTEGER PRIMARY KEY AUTOINCREMENT',
        $sql
    );

    // Remove standalone PRIMARY KEY (id) constraint
    $sql = preg_replace('/,\s*PRIMARY\s+KEY\s*\(\s*`?id`?\s*\)/i', '', $sql);

    // Remove UNIQUE KEY on id
    $sql = preg_replace('/,\s*UNIQUE\s+(KEY\s+)?\w+\s*\(\s*`?id`?\s*\)/i', '', $sql);

    // Remove remaining AUTO_INCREMENT keywords
    $sql = preg_replace('/\bAUTO_INCREMENT\b/i', '', $sql);

    // Normalize remaining bigint(n)/int(n) to INTEGER
    // Only match when not already processed (not part of `id INTEGER PRIMARY KEY`)
    $sql = preg_replace('/\bbigint\s*\(\d+\)\s*/i', 'INTEGER ', $sql);
    $sql = preg_replace('/\bint\s*\(\d+\)\s*/i', 'INTEGER ', $sql);

    // Handle ENUM → VARCHAR
    $sql = preg_replace('/\bENUM\s*\([^)]+\)/i', 'VARCHAR(20)', $sql);

    // MySQL function replacements
    $sql = str_replace('UTC_TIMESTAMP()', "datetime('now')", $sql);
    $sql = str_replace('CURDATE()', "date('now')", $sql);
    $sql = str_replace('NOW()', "datetime('now')", $sql);

    // Remove ON UPDATE CURRENT_TIMESTAMP
    $sql = preg_replace('/\bON\s+UPDATE\s+CURRENT_TIMESTAMP\b/i', '', $sql);

    // TIMESTAMP DEFAULT → DATETIME DEFAULT
    $sql = preg_replace('/TIMESTAMP\s+DEFAULT\s+CURRENT_TIMESTAMP/i', "DATETIME DEFAULT CURRENT_TIMESTAMP", $sql);

    // Remove FOREIGN KEY constraints
    $sql = preg_replace('/,\s*FOREIGN\s+KEY\s*\([^)]+\)\s*REFERENCES\s+\S+\s*\([^)]+\)/i', '', $sql);

    // Remove INDEX definitions
    $sql = preg_replace('/,\s*INDEX\s+\w+\s*\([^)]+\)/i', '', $sql);

    // Remove UNIQUE KEY definitions (non-id)
    $sql = preg_replace('/,\s*UNIQUE\s+(KEY\s+)?\w+\s*\([^)]+\)/i', '', $sql);

    // Remove KEY definitions
    $sql = preg_replace('/\bKEY\s+\w+\s*\([^)]+\)\s*,?\s*/i', '', $sql);

    // Already removed COMMENT above, but do again for safety
    $sql = preg_replace("/\s+COMMENT\s+'[^']*'/i", '', $sql);

    // Remove trailing comma before closing paren
    $sql = preg_replace('/,\s*\)/', ')', $sql);

    return $sql;
}

class WP_Mock_DB {
    public $prefix = 'wp_test_';
    public $base_prefix = 'wp_test_';
    public $last_error = '';
    public $insert_id = 0;

    private $pdo;
    private $table_aliases = [];
    private static $next_id = 1;
    private $created_tables = [];

    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function __get($name) {
        return isset($this->table_aliases[$name]) ? $this->table_aliases[$name] : null;
    }

    public function __set($name, $value) {
        if (is_string($value) && strpos($value, 'wp_test_') !== false) {
            $this->table_aliases[$name] = $value;
        }
    }

    public function dbDelta($sql) {
        $sql = mysql_to_sqlite_ddl($sql);
        if (preg_match("/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(\w+)/i", $sql, $m)) {
            $table = $m[1];
            if (!in_array($table, $this->created_tables)) {
                $this->pdo->exec($sql);
                $this->created_tables[] = $table;
                echo "  [DB] Created table: {$table}\n";
            }
        }
    }

    private function normalize_and_handle_special($query) {
        $query = $this->mysql_to_sqlite_query($query);
        if (preg_match('/^SHOW\s+TABLES\s+LIKE/i', $query)) {
            if (preg_match("/LIKE\s+'([^']+)'/i", $query, $m)) {
                try {
                    $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$m[1]}'");
                    return [$query, $stmt->fetch(PDO::FETCH_NUM)[0] ?? false];
                } catch (Exception $e) { return [$query, false]; }
            }
            return [$query, false];
        }
        if (preg_match('/^SHOW\s+COLUMNS\s+FROM/i', $query)) {
            if (preg_match("/FROM\s+(\w+)/i", $query, $m)) {
                try {
                    $stmt = $this->pdo->query("PRAGMA table_info({$m[1]})");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if (preg_match("/LIKE\s+'([^']+)'/i", $query, $like_m)) {
                        foreach ($rows as $row) {
                            if ($row['name'] === $like_m[1]) return [$query, $row['name']];
                        }
                    }
                    return [$query, false];
                } catch (Exception $e) { return [$query, false]; }
            }
            return [$query, false];
        }
        return [$query, null];
    }

    public function get_var($query, $column_offset = 0, $row_offset = 0, $return_type = null) {
        $this->last_error = '';
        try {
            list($normalized, $special) = $this->normalize_and_handle_special($query);
            if ($special !== null) return $special;
            $stmt = $this->pdo->query($normalized);
            $row = $stmt->fetch(PDO::FETCH_NUM);
            return $row ? $row[0] : null;
        } catch (Exception $e) { $this->last_error = $e->getMessage(); return null; }
    }

    public function get_row($query, $output = OBJECT, $y = 0) {
        $this->last_error = '';
        try {
            list($normalized, $special) = $this->normalize_and_handle_special($query);
            if ($special !== null) return $special;
            $row = $this->pdo->query($normalized)->fetch(PDO::FETCH_ASSOC);
            return $row ? (object) $row : null;
        } catch (Exception $e) { $this->last_error = $e->getMessage(); return null; }
    }

    public function get_results($query, $output = OBJECT) {
        $this->last_error = '';
        try {
            list($normalized, $special) = $this->normalize_and_handle_special($query);
            if ($special !== null) return $special;
            $rows = $this->pdo->query($normalized)->fetchAll(PDO::FETCH_ASSOC);
            return array_map(function($r) { return (object) $r; }, $rows);
        } catch (Exception $e) { $this->last_error = $e->getMessage(); return []; }
    }

    public function get_col($query, $x = 0) {
        $this->last_error = '';
        try {
            list($normalized, $special) = $this->normalize_and_handle_special($query);
            if ($special !== null) return $special;
            $rows = $this->pdo->query($normalized)->fetchAll(PDO::FETCH_NUM);
            return array_map(function($r) { return $r[0]; }, $rows);
        } catch (Exception $e) { $this->last_error = $e->getMessage(); return []; }
    }

    public function insert($table, $data, $formats = []) {
        $this->last_error = '';
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $values = array_values($data);
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
            $this->insert_id = (int) $this->pdo->lastInsertId();
            if (!$this->insert_id) { $this->insert_id = self::$next_id++; }
            return 1;
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            echo "  [DB INSERT ERROR] {$e->getMessage()}\n  SQL: {$sql}\n  Values: " . json_encode($values) . "\n";
            return false;
        }
    }

    public function update($table, $data, $where, $data_formats = [], $where_formats = []) {
        $this->last_error = '';
        $set = implode(', ', array_map(function($c) { return "{$c} = ?"; }, array_keys($data)));
        $w = implode(' AND ', array_map(function($c) { return "{$c} = ?"; }, array_keys($where)));
        $sql = "UPDATE {$table} SET {$set} WHERE {$w}";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge(array_values($data), array_values($where)));
            return $stmt->rowCount();
        } catch (Exception $e) { $this->last_error = $e->getMessage(); return false; }
    }

    private function mysql_to_sqlite_query($sql) {
        $sql = preg_replace(
            "/DATE_SUB\s*\(\s*(UTC_TIMESTAMP\(\)|NOW\(\)|CURDATE\(\)|\w+(?:\.\w+)?)\s*,\s*INTERVAL\s*(\d+)\s*(YEAR|MONTH|DAY)\s*\)/i",
            "datetime($1, '-$2 $3')", $sql
        );
        $sql = preg_replace_callback(
            "/CONCAT\s*\(\s*([^)]+)\s*\)/i",
            function ($m) {
                $args = explode(',', $m[1]);
                return implode(' || ', array_map(function($a) { return "COALESCE(" . trim($a) . ", '')"; }, $args));
            }, $sql
        );
        $sql = str_ireplace('UTC_TIMESTAMP()', "datetime('now')", $sql);
        $sql = str_ireplace('NOW()', "datetime('now')", $sql);
        $sql = str_ireplace('CURDATE()', "date('now')", $sql);
        $sql = preg_replace_callback(
            "/DATEDIFF\s*\(\s*([^,]+)\s*,\s*([^)]+)\s*\)/i",
            function ($m) { return "(julianday({$m[1]}) - julianday({$m[2]}))"; }, $sql
        );
        return $sql;
    }

    public function query($query) {
        $this->last_error = '';
        try {
            list($normalized, $special) = $this->normalize_and_handle_special($query);
            if ($special !== null) return $special;
            $stmt = $this->pdo->query($normalized);
            if (preg_match('/^\s*UPDATE/i', $normalized)) return $stmt->rowCount();
            if (preg_match('/^\s*INSERT/i', $normalized)) {
                $this->insert_id = (int) $this->pdo->lastInsertId();
                return $this->insert_id;
            }
            return $stmt->rowCount();
        } catch (Exception $e) { $this->last_error = $e->getMessage(); return false; }
    }

    public function prepare($query, ...$args) {
        $flat = [];
        foreach ($args as $a) {
            if (is_array($a)) $flat = array_merge($flat, $a);
            else $flat[] = $a;
        }
        $q = preg_replace('/%[dsf]/', '?', $query);
        if (empty($flat)) return $q;
        $parts = explode('?', $q);
        $r = '';
        foreach ($parts as $i => $p) {
            $r .= $p;
            if (isset($flat[$i])) {
                $v = $flat[$i];
                if (is_int($v) || is_float($v)) $r .= (string)$v;
                elseif (is_null($v)) $r .= 'NULL';
                else $r .= "'" . str_replace("'", "''", (string)$v) . "'";
            }
        }
        return $r;
    }

    public function get_charset_collate() { return ''; }

    public function cleanup() {
        foreach (array_reverse($this->created_tables) as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
        $this->created_tables = [];
    }
}

// ── Mock OraBooks_Chart_of_Accounts ─────────────────────────────────────────
if (!class_exists('OraBooks_Chart_of_Accounts')) {
    class OraBooks_Chart_of_Accounts {
        const TYPE_ASSET = 'A';
        const TYPE_LIABILITY = 'L';
        const TYPE_EQUITY = 'E';
        const TYPE_INCOME = 'I';
        const TYPE_EXPENSE = 'X';

        private static $instance = null;
        public static function get_instance() {
            if (self::$instance === null) self::$instance = new self();
            return self::$instance;
        }
        public function __construct() { global $wpdb; }
        public function create_coa_tables() {}
        public function get_account($account_id) {
            global $wpdb;
            $table = $wpdb->base_prefix . 'orabooks_chart_of_accounts';
            $stmt = $wpdb->pdo->query("SELECT * FROM {$table} WHERE id = {$account_id}");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (object) $row : null;
        }
        public function validate_mode_compatibility($account_id, $mode) { return true; }
    }
}

// ── Mock OraBooks_Journal_Entry ─────────────────────────────────────────────
if (!class_exists('OraBooks_Journal_Entry')) {
    class OraBooks_Journal_Entry {
        private static $instance = null;
        private $je_counter = 1000;

        public static function get_instance() {
            if (self::$instance === null) self::$instance = new self();
            return self::$instance;
        }

        public function create_je_tables() {
            global $wpdb;
            $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->base_prefix}orabooks_journal_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                je_number VARCHAR(50), description TEXT, mode VARCHAR(20) DEFAULT 'business',
                source_type VARCHAR(50), source_id INTEGER,
                entry_date DATETIME, total_debit REAL DEFAULT 0,
                total_credit REAL DEFAULT 0, status VARCHAR(20) DEFAULT 'draft',
                approval_status VARCHAR(20) DEFAULT 'pending', created_by INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP, posted_at DATETIME
            )");
            $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->base_prefix}orabooks_journal_entry_lines (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                journal_entry_id INTEGER, account_id INTEGER,
                line_type VARCHAR(10), amount REAL, description TEXT,
                line_order INTEGER DEFAULT 0
            )");
        }

        public function create_journal_entry($data) {
            global $wpdb;
            $je_table = $wpdb->base_prefix . 'orabooks_journal_entries';
            $jel_table = $wpdb->base_prefix . 'orabooks_journal_entry_lines';
            $je_number = 'JE-TEST-' . date('Y-m-') . str_pad($this->je_counter++, 5, '0', STR_PAD_LEFT);
            $total_debit = 0; $total_credit = 0;
            foreach ($data['lines'] as $line) {
                if ($line['line_type'] === 'debit') $total_debit += $line['amount'];
                else $total_credit += $line['amount'];
            }
            if (abs($total_debit - $total_credit) > 0.01) {
                return new WP_Error('double_entry_violation', "Debits ($total_debit) != Credits ($total_credit)");
            }
            $wpdb->insert($je_table, [
                'je_number' => $je_number, 'description' => $data['description'] ?? '',
                'mode' => $data['mode'] ?? 'business', 'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null, 'entry_date' => $data['entry_date'] ?? date('Y-m-d H:i:s'),
                'total_debit' => $total_debit, 'total_credit' => $total_credit,
                'status' => 'draft', 'approval_status' => 'pending', 'created_by' => $data['created_by'] ?? 0,
            ]);
            $je_id = $wpdb->insert_id;
            foreach ($data['lines'] as $idx => $line) {
                $wpdb->insert($jel_table, [
                    'journal_entry_id' => $je_id, 'account_id' => $line['account_id'],
                    'line_type' => $line['line_type'], 'amount' => $line['amount'],
                    'description' => $line['description'] ?? '', 'line_order' => $idx,
                ]);
            }
            return $je_id;
        }

        public function post_journal_entry($je_id, $approved_by = null) {
            global $wpdb;
            $je_table = $wpdb->base_prefix . 'orabooks_journal_entries';
            $wpdb->update($je_table, [
                'status' => 'posted', 'approval_status' => 'approved', 'posted_at' => date('Y-m-d H:i:s'),
            ], ['id' => $je_id]);
            return true;
        }

        public function get_je($je_id) {
            global $wpdb;
            $je_table = $wpdb->base_prefix . 'orabooks_journal_entries';
            return $wpdb->get_row("SELECT * FROM {$je_table} WHERE id = {$je_id}");
        }

        public function get_je_lines($je_id) {
            global $wpdb;
            $jel_table = $wpdb->base_prefix . 'orabooks_journal_entry_lines';
            return $wpdb->get_results("SELECT * FROM {$jel_table} WHERE journal_entry_id = {$je_id} ORDER BY line_order");
        }
    }
}

// ── Include the classes under test ──────────────────────────────────────────
require_once __DIR__ . '/../TaxOra - WPMU Membership Subscription Panel/includes/class-orabooks-invoices.php';
require_once __DIR__ . '/../TaxOra - WPMU Membership Subscription Panel/includes/class-orabooks-invoices-rest.php';

// ════════════════════════════════════════════════════════════════════════════
// SECTION 3: Test Harness
// ════════════════════════════════════════════════════════════════════════════

class SL021_Invoice_REST_API_Test {
    private $wpdb;
    private $invoices;
    private $rest;
    private $passed = 0;
    private $failed = 0;
    private $assertions = 0;

    public function __construct() {
        global $wpdb, $test_events;
        $wpdb = new WP_Mock_DB();
        $this->wpdb = $wpdb;
        $test_events = [];
        echo "\n" . str_repeat('=', 70) . "\n";
        echo " SL-021 Invoice REST API — Standalone Integration Test\n";
        echo str_repeat('=', 70) . "\n\n";
    }

    public function run() {
        try {
            $this->setup_environment();
            $this->test_create_invoice();
            $this->test_list_invoices();
            $this->test_get_invoice();
            $this->test_submit_invoice();
            $this->test_approve_invoice();
            $this->test_post_invoice();
            $this->test_record_payment();
            $this->test_wallet_updated();
            $this->test_audit_events();
            echo "\n" . str_repeat('-', 70) . "\n";
            echo " RESULTS: {$this->passed} passed, {$this->failed} failed, {$this->assertions} assertions\n";
            echo str_repeat('-', 70) . "\n\n";
        } finally {
            $this->wpdb->cleanup();
        }
        return $this->failed === 0;
    }

    private function setup_environment() {
        echo "--- Setup: Creating database tables ---\n";

        $coa_table = $this->wpdb->base_prefix . 'orabooks_chart_of_accounts';
        $this->wpdb->query("CREATE TABLE IF NOT EXISTS {$coa_table} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            org_id INTEGER DEFAULT 0,
            code VARCHAR(20), name VARCHAR(255), account_type VARCHAR(5),
            normal_balance VARCHAR(10) DEFAULT 'debit',
            mode_compatibility VARCHAR(100) DEFAULT 'all', description TEXT,
            system_generated INTEGER DEFAULT 1, is_active INTEGER DEFAULT 1,
            created_by INTEGER DEFAULT 0
        )");

        $accounts = [
            ['code' => 1100, 'name' => 'Accounts Receivable',     'account_type' => 'A', 'normal_balance' => 'debit'],
            ['code' => 4000, 'name' => 'Sales Revenue',           'account_type' => 'I', 'normal_balance' => 'credit'],
            ['code' => 4100, 'name' => 'Service Revenue',         'account_type' => 'I', 'normal_balance' => 'credit'],
            ['code' => 2500, 'name' => 'Sales Tax Payable',       'account_type' => 'L', 'normal_balance' => 'credit'],
            ['code' => 1000, 'name' => 'Cash - Operating Account','account_type' => 'A', 'normal_balance' => 'debit'],
        ];
        foreach ($accounts as $acct) {
            $exists = $this->wpdb->get_var("SELECT id FROM {$coa_table} WHERE code = '{$acct['code']}'");
            if (!$exists) $this->wpdb->insert($coa_table, $acct);
        }

        $je = OraBooks_Journal_Entry::get_instance();
        $je->create_je_tables();

        $this->invoices = OraBooks_Invoices::get_instance();
        $this->rest = OraBooks_Invoices_REST::get_instance();
        $this->invoices->create_invoice_tables();

        echo "--- Setup complete ---\n\n";
    }

    private function assert($condition, $message, $line) {
        $this->assertions++;
        if (!$condition) { $this->failed++; echo "  [FAIL] Line {$line}: {$message}\n"; }
        else { $this->passed++; }
    }

    private function assertEquals($expected, $actual, $message, $line) {
        $this->assertions++;
        if ($expected !== $actual) {
            $this->failed++;
            echo "  [FAIL] Line {$line}: {$message}\n";
            echo "         Expected: " . var_export($expected, true) . "\n";
            echo "         Actual:   " . var_export($actual, true) . "\n";
        } else { $this->passed++; }
    }

    // ── TEST 1: Create Invoice (POST /invoice) ─────────────────────────────

    private function test_create_invoice() {
        echo "TEST 1: Create invoice via POST /invoice (simulated)\n";
        echo str_repeat('-', 70) . "\n";
        try {
            global $test_events;
            $test_events = [];

            $request = new WP_REST_Request('POST', '/invoice');
            $request->set_param('org_id', 1);
            $request->set_param('customer_id', 100);
            $request->set_param('customer_name', 'Acme Corp');
            $request->set_param('customer_email', 'billing@acme.com');
            $request->set_param('subtotal', 1000.00);
            $request->set_param('tax_total', 80.00);
            $request->set_param('total', 1080.00);
            $request->set_param('currency', 'USD');
            $request->set_param('mode', 'business');
            $request->set_param('line_items', [
                ['description' => 'Consulting Services', 'quantity' => 10, 'rate' => 100.00, 'amount' => 1000.00],
            ]);
            $request->set_param('notes', 'Monthly consulting retainer');
            $request->set_param('terms', 'Net 30');
            $request->set_param('due_date', date('Y-m-d H:i:s', strtotime('+30 days')));

            $response = $this->rest->create_invoice($request);

            $this->assert($response instanceof WP_REST_Response, 'Response is WP_REST_Response', __LINE__);
            $data = $response->get_data();
            $this->assertEquals(201, $response->get_status(), 'Response status is 201 Created', __LINE__);
            $this->assert($data['success'] === true, 'Response success=true', __LINE__);
            $this->assert(isset($data['data']), 'Response has data', __LINE__);

            $invoice = $data['data'];
            $this->assert(isset($invoice->id), 'Invoice has ID', __LINE__);
            $this->assert(isset($invoice->invoice_number), 'Invoice has number', __LINE__);
            $this->assert(strpos($invoice->invoice_number, 'INV-') === 0, "Invoice number starts with INV-", __LINE__);
            $this->assertEquals('draft', $invoice->status, 'Invoice status is draft', __LINE__);
            $this->assertEquals('unpaid', $invoice->payment_status, 'Payment status is unpaid', __LINE__);
            $this->assertEquals(1000, (int)$invoice->subtotal, 'Subtotal = 1000', __LINE__);
            $this->assertEquals(80, (int)$invoice->tax_total, 'Tax total = 80', __LINE__);
            $this->assertEquals(1080, (int)$invoice->total, 'Total = 1080', __LINE__);
            $this->assertEquals(1080, (int)$invoice->balance_due, 'Balance due = 1080', __LINE__);

            global $test_invoice_id, $test_invoice_number;
            $test_invoice_id = $invoice->id;
            $test_invoice_number = $invoice->invoice_number;
            echo "  [INFO] Created invoice #{$invoice->id}: {$invoice->invoice_number}\n";
            $this->pass(__LINE__);
        } catch (Exception $e) { $this->fail(__LINE__, $e->getMessage()); }
        echo "\n";
    }

    // ── TEST 2: List Invoices (GET /invoice) ───────────────────────────────

    private function test_list_invoices() {
        echo "TEST 2: List invoices via GET /invoice (simulated)\n";
        echo str_repeat('-', 70) . "\n";
        try {
            $request = new WP_REST_Request('GET', '/invoice');
            $request->set_param('org_id', 1);
            $response = $this->rest->get_invoices($request);
            $data = $response->get_data();
            $this->assert($response instanceof WP_REST_Response, 'Response is WP_REST_Response', __LINE__);
            $this->assertEquals(200, $response->get_status(), 'Response status is 200', __LINE__);
            $this->assert($data['success'] === true, 'Response success=true', __LINE__);
            $this->assert(isset($data['data']['invoices']), 'Has invoices array', __LINE__);
            $this->assert(count($data['data']['invoices']) >= 1, 'At least 1 invoice', __LINE__);
            $this->assertEquals(1, $data['data']['total'], 'Total = 1', __LINE__);
            $this->assertEquals('Acme Corp', $data['data']['invoices'][0]->customer_name, 'Customer name matches', __LINE__);

            $r2 = new WP_REST_Request('GET', '/invoice');
            $r2->set_param('org_id', 999);
            $this->assertEquals(0, $this->rest->get_invoices($r2)->get_data()['data']['total'], 'Org 999 = 0 invoices', __LINE__);

            $r3 = new WP_REST_Request('GET', '/invoice');
            $r3->set_param('org_id', 1);
            $r3->set_param('status', 'draft');
            $this->assert($this->rest->get_invoices($r3)->get_data()['data']['total'] >= 1, 'Filter draft works', __LINE__);

            $r4 = new WP_REST_Request('GET', '/invoice');
            $r4->set_param('org_id', 1);
            $r4->set_param('status', 'posted');
            $this->assertEquals(0, $this->rest->get_invoices($r4)->get_data()['data']['total'], 'Filter posted = 0', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) { $this->fail(__LINE__, $e->getMessage()); }
        echo "\n";
    }

    // ── TEST 3: Get Single Invoice (GET /invoice/{id}) ─────────────────────

    private function test_get_invoice() {
        echo "TEST 3: Get single invoice via GET /invoice/{id}\n";
        echo str_repeat('-', 70) . "\n";
        try {
            global $test_invoice_id;

            $request = new WP_REST_Request('GET', '/invoice/' . $test_invoice_id);
            $request->set_param('id', $test_invoice_id);
            $response = $this->rest->get_invoice($request);
            $data = $response->get_data();

            $this->assert($response instanceof WP_REST_Response, 'Response is WP_REST_Response', __LINE__);
            $this->assertEquals(200, $response->get_status(), 'Status 200', __LINE__);
            $this->assert($data['success'] === true, 'Success=true', __LINE__);

            $invoice = $data['data'];
            $this->assertEquals($test_invoice_id, $invoice->id, 'ID matches', __LINE__);
            $this->assertEquals(1, $invoice->org_id, 'Org ID = 1', __LINE__);
            $this->assertEquals(100, $invoice->customer_id, 'Customer ID = 100', __LINE__);
            $this->assertEquals('Acme Corp', $invoice->customer_name, 'Customer name = Acme Corp', __LINE__);
            $this->assertEquals(1080, (int)$invoice->total, 'Total = 1080', __LINE__);

            $r2 = new WP_REST_Request('GET', '/invoice/99999');
            $r2->set_param('id', 99999);
            $this->rest->get_invoice($r2);
            // WP_Error should be returned — we tested it doesn't crash

            $this->pass(__LINE__);
        } catch (Exception $e) { $this->fail(__LINE__, $e->getMessage()); }
        echo "\n";
    }

    // ── TEST 4: Submit Invoice (POST /invoice/{id}/submit) ─────────────────

    private function test_submit_invoice() {
        echo "TEST 4: Submit invoice via POST /invoice/{id}/submit\n";
        echo str_repeat('-', 70) . "\n";
        try {
            global $test_invoice_id;
            $request = new WP_REST_Request('POST', '/invoice/' . $test_invoice_id . '/submit');
            $request->set_param('id', $test_invoice_id);
            $response = $this->rest->submit_invoice($request);
            $data = $response->get_data();
            $this->assert($data['success'] === true, 'Submit success', __LINE__);
            $this->assert($data['data']['success'] === true, 'Transition success', __LINE__);

            $invoice = $this->invoices->get_invoice($test_invoice_id);
            $this->assertEquals('submitted', $invoice->status, 'Status = submitted', __LINE__);
            $this->pass(__LINE__);
        } catch (Exception $e) { $this->fail(__LINE__, $e->getMessage()); }
        echo "\n";
    }

    // ── TEST 5: Approve Invoice (POST /invoice/{id}/approve) ───────────────

    private function test_approve_invoice() {
        echo "TEST 5: Approve invoice via POST /invoice/{id}/approve\n";
        echo str_repeat('-', 70) . "\n";
        try {
            global $test_invoice_id;
            $request = new WP_REST_Request('POST', '/invoice/' . $test_invoice_id . '/approve');
            $request->set_param('id', $test_invoice_id);
            $response = $this->rest->approve_invoice($request);
            $this->assert($response->get_data()['success'] === true, 'Approve success', __LINE__);
            $invoice = $this->invoices->get_invoice($test_invoice_id);
            $this->assertEquals('approved', $invoice->status, 'Status = approved', __LINE__);
            $this->pass(__LINE__);
        } catch (Exception $e) { $this->fail(__LINE__, $e->getMessage()); }
        echo "\n";
    }

    // ── TEST 6: Post Invoice (POST /invoice/{id}/post) ─────────────────────

    private function test_post_invoice() {
        echo "TEST 6: Post invoice via POST /invoice/{id}/post\n";
        echo str_repeat('-', 70) . "\n";
        try {
            global $test_invoice_id;
            $request = new WP_REST_Request('POST', '/invoice/' . $test_invoice_id . '/post');
            $request->set_param('id', $test_invoice_id);
            $request->set_param('posted_by', 1);
            $response = $this->rest->post_invoice($request);
            $this->assert($response->get_data()['success'] === true, 'Post success', __LINE__);

            $invoice = $this->invoices->get_invoice($test_invoice_id);
            $this->assertEquals('posted', $invoice->status, 'Status = posted', __LINE__);
            $this->assert($invoice->je_id > 0, 'JE ID set', __LINE__);
            $this->assert($invoice->posted_at !== null, 'posted_at set', __LINE__);
            $this->assert($invoice->snapshot !== null, 'Snapshot captured', __LINE__);

            $je = OraBooks_Journal_Entry::get_instance()->get_je($invoice->je_id);
            $this->assert($je !== null, 'JE exists', __LINE__);
            $this->assertEquals((float)$je->total_debit, (float)$je->total_credit, 'JE balanced', __LINE__);
            $this->assertEquals(1080.00, (float)$je->total_debit, 'JE total = 1080.00', __LINE__);

            $lines = OraBooks_Journal_Entry::get_instance()->get_je_lines($invoice->je_id);
            $this->assert(count($lines) >= 2, 'JE has >= 2 lines', __LINE__);
            $this->assertEquals('debit', $lines[0]->line_type, 'Line 1 = debit', __LINE__);
            $this->assertEquals('credit', $lines[1]->line_type, 'Line 2 = credit', __LINE__);
            $this->pass(__LINE__);
        } catch (Exception $e) { $this->fail(__LINE__, $e->getMessage()); }
        echo "\n";
    }

    // ── TEST 7: Record Payment (POST /invoice/{id}/payment) ────────────────

    private function test_record_payment() {
        echo "TEST 7: Record payment via POST /invoice/{id}/payment\n";
        echo str_repeat('-', 70) . "\n";
        try {
            global $test_invoice_id;
            $request = new WP_REST_Request('POST', '/invoice/' . $test_invoice_id . '/payment');
            $request->set_param('id', $test_invoice_id);
            $request->set_param('amount', 1080.00);
            $request->set_param('payment_method', 'bank_transfer');
            $request->set_param('gateway_ref', 'TXN-12345');
            $request->set_param('cash_account', 1000);
            $request->set_param('notes', 'Full payment received');

            $response = $this->rest->record_payment($request);
            $data = $response->get_data();
            $this->assert($data['success'] === true, 'Payment recorded', __LINE__);

            $invoice = $this->invoices->get_invoice($test_invoice_id);
            $this->assertEquals('paid', $invoice->payment_status, 'Status = paid', __LINE__);
            $this->assertEquals(1080, (int)$invoice->paid_amount, 'Paid = 1080', __LINE__);
            $this->assertEquals(0, (int)$invoice->balance_due, 'Balance due = 0', __LINE__);

            $allocations = $this->invoices->get_allocations($test_invoice_id);
            $this->assert(count($allocations) >= 1, 'Allocation recorded', __LINE__);
            $this->pass(__LINE__);
        } catch (Exception $e) { $this->fail(__LINE__, $e->getMessage()); }
        echo "\n";
    }

    // ── TEST 8: Wallet Updated ─────────────────────────────────────────────

    private function test_wallet_updated() {
        echo "TEST 8: Verify wallet reflects paid invoice\n";
        echo str_repeat('-', 70) . "\n";
        try {
            $wallet = $this->invoices->get_wallet(1, 100);
            $this->assert($wallet !== null, 'Wallet exists', __LINE__);
            $this->assertEquals(0, (int)$wallet->current_balance, 'Balance = 0 (paid off)', __LINE__);
            echo "  [INFO] Wallet: balance={$wallet->current_balance}, credit={$wallet->credit_balance}\n";
            $this->pass(__LINE__);
        } catch (Exception $e) { $this->fail(__LINE__, $e->getMessage()); }
        echo "\n";
    }

    // ── TEST 9: Audit Events ───────────────────────────────────────────────

    private function test_audit_events() {
        echo "TEST 9: Verify audit events fired\n";
        echo str_repeat('-', 70) . "\n";
        try {
            global $test_events;
            $this->assert(count($test_events) > 0, 'Events fired', __LINE__);

            $expected = ['invoice_created', 'invoice_submitted', 'invoice_approved', 'invoice_posted', 'payment_recorded'];
            foreach ($expected as $exp) {
                $found = false;
                foreach ($test_events as $ev) {
                    if ($ev['tag'] === 'orabooks_security_event' && $ev['args'][0] === $exp) { $found = true; break; }
                }
                $this->assert($found, "Event '{$exp}' fired", __LINE__);
            }
            echo "  [INFO] Total events: " . count($test_events) . "\n";
            $this->pass(__LINE__);
        } catch (Exception $e) { $this->fail(__LINE__, $e->getMessage()); }
        echo "\n";
    }

    private function pass($line) { $this->passed++; }
    private function fail($line, $msg = '') { $this->failed++; echo "  [FAIL] Line {$line}: {$msg}\n"; }
}

// ════════════════════════════════════════════════════════════════════════════
// MAIN
// ════════════════════════════════════════════════════════════════════════════

$test = new SL021_Invoice_REST_API_Test();
$result = $test->run();
exit($result ? 0 : 1);
