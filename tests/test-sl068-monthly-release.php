<?php
/**
 * SL-068 Monthly Commission Release — Standalone Integration Test
 *
 * Tests the monthly commission release lifecycle using reflection to access
 * private accounting methods (book_commission_release_je, book_commission_reversal_je)
 * and public API methods (process_expiry_writeback, generate_escrow_schedule,
 * is_customer_active, refresh_customer_active_status).
 *
 * Run: php tests/test-sl068-monthly-release.php
 * Requires: PHP 8.0+, SQLite (for in-memory testing)
 */

// ── Bootstrap: define ABSPATH and WP constants ──────────────────────────────
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
define('ORABOOKS_TEST_MODE', true);

// ════════════════════════════════════════════════════════════════════════════
// SECTION 1: WordPress Core Test Doubles (MUST be defined BEFORE class include)
// ════════════════════════════════════════════════════════════════════════════

// ── Mock WordPress global functions ─────────────────────────────────────────
if (!function_exists('current_time')) {
    function current_time($type = 'mysql', $gmt = 0) {
        if ($type === 'mysql') return date('Y-m-d H:i:s');
        if ($type === 'timestamp') return time();
        return date('Y-m-d H:i:s');
    }
}
if (!function_exists('wp_verify_nonce')) { function wp_verify_nonce($nonce, $action) { return true; } }
if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null) {
        global $test_ajax_response;
        $test_ajax_response = ['success' => true, 'data' => $data];
        throw new Exception('AJAX_RESPONSE_SENT');
    }
}
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null) {
        global $test_ajax_response;
        $test_ajax_response = ['success' => false, 'data' => $data];
        throw new Exception('AJAX_RESPONSE_SENT');
    }
}
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

// ── Mock dbDelta (WordPress core function used by create_tables) ────────────
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
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}
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

// ── Include the classes under test ──────────────────────────────────────────
require_once __DIR__ . '/../TaxOra - WPMU Membership Subscription Panel/includes/class-orabooks-commissions.php';

// ════════════════════════════════════════════════════════════════════════════
// SECTION 2: MySQL-to-SQLite Database Layer
// ════════════════════════════════════════════════════════════════════════════

/**
 * Converts MySQL CREATE TABLE SQL to SQLite-compatible syntax.
 */
function mysql_to_sqlite_ddl($sql) {
    // Remove comments
    $sql = preg_replace('#/\*.*?\*/#s', '', $sql);
    $sql = preg_replace('#-- .*$#m', '', $sql);

    // Replace ENUM with VARCHAR
    $sql = preg_replace('/\bENUM\s*\([^)]+\)/i', 'VARCHAR(20)', $sql);

    // Replace BIGINT AUTO_INCREMENT with INTEGER PRIMARY KEY AUTOINCREMENT
    // Must handle: `id BIGINT AUTO_INCREMENT PRIMARY KEY` and variations
    $sql = preg_replace('/\bBIGINT\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace('/\bINT\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace('/\bBIGINT\s+AUTO_INCREMENT\b/i', 'INTEGER AUTOINCREMENT', $sql);
    $sql = preg_replace('/\bAUTO_INCREMENT\b/i', 'AUTOINCREMENT', $sql);

    // Replace MySQL function calls
    $sql = str_replace('UTC_TIMESTAMP()', "datetime('now')", $sql);
    $sql = str_replace('CURDATE()', "date('now')", $sql);
    $sql = str_replace('NOW()', "datetime('now')", $sql);

    // Remove ON UPDATE CURRENT_TIMESTAMP
    $sql = preg_replace('/\bON\s+UPDATE\s+CURRENT_TIMESTAMP\b/i', '', $sql);

    // Replace TIMESTAMP DEFAULT with proper SQLite syntax
    $sql = preg_replace('/TIMESTAMP\s+DEFAULT\s+CURRENT_TIMESTAMP/i', "DATETIME DEFAULT CURRENT_TIMESTAMP", $sql);

    // Remove FOREIGN KEY constraints (may reference missing tables)
    $sql = preg_replace('/,\s*FOREIGN\s+KEY\s*\([^)]+\)\s*REFERENCES\s+\S+\s*\([^)]+\)/i', '', $sql);

    // Remove INDEX and KEY definitions
    $sql = preg_replace('/,\s*INDEX\s+\w+\s*\([^)]+\)/i', '', $sql);
    $sql = preg_replace('/,\s*UNIQUE\s+(KEY\s+)?\w+\s*\([^)]+\)/i', '', $sql);
    $sql = preg_replace('/\bKEY\s+\w+\s*\([^)]+\)\s*,?\s*/i', '', $sql);

    // Remove COMMENT clauses (SQLite doesn't support them)
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

    /**
     * Handle MySQL-specific SHOW queries and normalize MySQL functions for SQLite.
     * Returns [normalized_sql, special_result]. If special_result is not null,
     * the caller should return it immediately.
     */
    private function normalize_and_handle_special($query) {
        // First: normalize MySQL functions
        $query = $this->mysql_to_sqlite_query($query);

        // Handle SHOW TABLES LIKE
        if (preg_match('/^SHOW\s+TABLES\s+LIKE/i', $query)) {
            if (preg_match("/LIKE\s+'([^']+)'/i", $query, $m)) {
                $table = $m[1];
                try {
                    $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
                    $row = $stmt->fetch(PDO::FETCH_NUM);
                    return [$query, $row ? $row[0] : false];
                } catch (Exception $e) {
                    return [$query, false];
                }
            }
            return [$query, false];
        }

        // Handle SHOW COLUMNS FROM
        if (preg_match('/^SHOW\s+COLUMNS\s+FROM/i', $query)) {
            if (preg_match("/FROM\s+(\w+)/i", $query, $m)) {
                $table = $m[1];
                try {
                    $stmt = $this->pdo->query("PRAGMA table_info({$table})");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if (preg_match("/LIKE\s+'([^']+)'/i", $query, $like_m)) {
                        $col_name = $like_m[1];
                        foreach ($rows as $row) {
                            if ($row['name'] === $col_name) return [$query, $row['name']];
                        }
                    }
                    return [$query, false];
                } catch (Exception $e) {
                    return [$query, false];
                }
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
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            return null;
        }
    }

    public function get_row($query, $output = OBJECT, $y = 0) {
        $this->last_error = '';
        try {
            list($normalized, $special) = $this->normalize_and_handle_special($query);
            if ($special !== null) return $special;
            $stmt = $this->pdo->query($normalized);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;
            return (object) $row;
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            return null;
        }
    }

    public function get_results($query, $output = OBJECT) {
        $this->last_error = '';
        try {
            list($normalized, $special) = $this->normalize_and_handle_special($query);
            if ($special !== null) return $special;
            $stmt = $this->pdo->query($normalized);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result = [];
            foreach ($rows as $row) {
                $result[] = (object) $row;
            }
            return $result;
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            return [];
        }
    }

    public function get_col($query, $x = 0) {
        $this->last_error = '';
        try {
            list($normalized, $special) = $this->normalize_and_handle_special($query);
            if ($special !== null) return $special;
            $stmt = $this->pdo->query($normalized);
            $rows = $stmt->fetchAll(PDO::FETCH_NUM);
            $result = [];
            foreach ($rows as $row) {
                $result[] = $row[0];
            }
            return $result;
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            return [];
        }
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
            if (!$this->insert_id) {
                $this->insert_id = self::$next_id++;
            }
            return 1;
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            echo "  [DB INSERT ERROR] {$e->getMessage()}\n  SQL: {$sql}\n  Values: " . json_encode($values) . "\n";
            return false;
        }
    }

    public function update($table, $data, $where, $data_formats = [], $where_formats = []) {
        $this->last_error = '';
        $set_parts = [];
        $values = [];
        foreach ($data as $col => $val) {
            $set_parts[] = "{$col} = ?";
            $values[] = $val;
        }
        foreach ($where as $col => $val) {
            $set_parts[] = "{$col} = ?";
            $values[] = $val;
        }
        // Move WHERE conditions after SET
        $where_clause = '';
        $set_sql = implode(', ', $set_parts);
        $where_idx = count($data);
        $where_conditions = [];
        $i = 0;
        foreach ($where as $col => $val) {
            $where_conditions[] = "{$col} = ?";
        }
        $sql = "UPDATE {$table} SET " . implode(', ', array_slice($set_parts, 0, count($data)))
              . " WHERE " . implode(' AND ', $where_conditions);

        try {
            $stmt = $this->pdo->prepare($sql);
            // Parameters: first data values, then where values
            $all_values = array_merge(array_values($data), array_values($where));
            $stmt->execute($all_values);
            return $stmt->rowCount();
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Convert MySQL-specific SQL functions to SQLite equivalents.
     */
    private function mysql_to_sqlite_query($sql) {
        // ORDER matters: DATE_SUB/CONCAT with UTC_TIMESTAMP/NOW args must
        // be processed BEFORE the function replacement changes parens.

        // DATE_SUB(x, INTERVAL n YEAR) -> datetime(x, '-n years')
        // Process BEFORE UTC_TIMESTAMP/CURDATE replacement to handle parentheses correctly
        $sql = preg_replace(
            "/DATE_SUB\s*\(\s*(UTC_TIMESTAMP\(\)|NOW\(\)|CURDATE\(\)|\w+(?:\.\w+)?)\s*,\s*INTERVAL\s*(\d+)\s*(YEAR|MONTH|DAY)\s*\)/i",
            "datetime($1, '-$2 $3')",
            $sql
        );

        // CONCAT(a, b, c) -> a || b || c  (COALESCE for NULL-safe)
        $sql = preg_replace_callback(
            "/CONCAT\s*\(\s*([^)]+)\s*\)/i",
            function ($m) {
                $args = explode(',', $m[1]);
                $parts = [];
                foreach ($args as $arg) {
                    $arg = trim($arg);
                    $parts[] = "COALESCE({$arg}, '')";
                }
                return implode(' || ', $parts);
            },
            $sql
        );

        // CONCAT_WS(' | ', a, b) -> a || ' | ' || b
        $sql = preg_replace_callback(
            "/CONCAT_WS\s*\(\s*'([^']*)'\s*,\s*([^)]+)\s*\)/i",
            function ($m) {
                $sep = $m[1];
                $args = explode(',', $m[2]);
                $parts = [];
                foreach ($args as $arg) {
                    $arg = trim($arg);
                    $parts[] = "COALESCE({$arg}, '')";
                }
                return implode(" || '{$sep}' || ", $parts);
            },
            $sql
        );
        // Fallback for remaining CONCAT_WS
        $sql = str_ireplace("CONCAT_WS(' | ', ", "COALESCE(", $sql);

        // MySQL functions not available in SQLite
        $sql = str_ireplace('UTC_TIMESTAMP()', "datetime('now')", $sql);
        $sql = str_ireplace('NOW()', "datetime('now')", $sql);
        $sql = str_ireplace('CURDATE()', "date('now')", $sql);

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
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    public function prepare($query, ...$args) {
        $flat_args = [];
        foreach ($args as $arg) {
            if (is_array($arg)) $flat_args = array_merge($flat_args, $arg);
            else $flat_args[] = $arg;
        }
        // WordPress wpdb::prepare substitutes values INTO the query string
        $query = preg_replace('/%[dsf]/', '?', $query);
        if (empty($flat_args)) return $query;
        $parts = explode('?', $query);
        $result = '';
        foreach ($parts as $i => $part) {
            $result .= $part;
            if (isset($flat_args[$i])) {
                $val = $flat_args[$i];
                if (is_int($val) || is_float($val)) {
                    $result .= (string)$val;
                } elseif (is_null($val)) {
                    $result .= 'NULL';
                } else {
                    $result .= "'" . str_replace("'", "''", (string)$val) . "'";
                }
            }
        }
        return $result;
    }

    public function get_charset_collate() { return ''; }

    public function cleanup() {
        foreach (array_reverse($this->created_tables) as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
        $this->created_tables = [];
    }
}

function create_mock_users_table($wpdb) {
    $wpdb->users = 'wp_test_users';
    $wpdb->query("CREATE TABLE IF NOT EXISTS wp_test_users (
        ID INTEGER PRIMARY KEY AUTOINCREMENT,
        user_email VARCHAR(255),
        display_name VARCHAR(255)
    )");
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
        private $wpdb;

        public static function get_instance() {
            if (self::$instance === null) self::$instance = new self();
            return self::$instance;
        }
        public function __construct() { global $wpdb; $this->wpdb = $wpdb; }
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
        private $wpdb;
        private $je_counter = 1000;

        public static function get_instance() {
            if (self::$instance === null) self::$instance = new self();
            return self::$instance;
        }
        public function __construct() { global $wpdb; $this->wpdb = $wpdb; }

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
    }
}

// ── Mock OraBooks_Partners ──────────────────────────────────────────────────
if (!class_exists('OraBooks_Partners')) {
    class OraBooks_Partners {
        private static $instance = null;
        private $codes = [];

        public static function get_instance() {
            if (self::$instance === null) self::$instance = new self();
            return self::$instance;
        }

        public function set_partner_code($user_id, $partner_type) {
            $this->codes[$user_id] = (object) ['partner_type' => $partner_type];
        }

        public function get_partner_code($user_id) {
            return isset($this->codes[$user_id]) ? $this->codes[$user_id] : null;
        }
    }
}

// ════════════════════════════════════════════════════════════════════════════
// SECTION 3: Test Harness
// ════════════════════════════════════════════════════════════════════════════

class SL068_MonthlyRelease_Test {
    /** @var WP_Mock_DB */
    private $wpdb;

    /** @var OraBooks_Commissions */
    private $commissions;

    /** @var ReflectionMethod[] */
    private $reflection_methods = [];

    private $passed = 0;
    private $failed = 0;

    public function __construct() {
        global $wpdb, $test_events;
        $wpdb = new WP_Mock_DB();
        $this->wpdb = $wpdb;
        $test_events = [];

        echo "\n" . str_repeat('=', 70) . "\n";
        echo " SL-068 Monthly Commission Release — Integration Tests\n";
        echo str_repeat('=', 70) . "\n\n";
    }

    private function call_private($method_name, ...$args) {
        if (!isset($this->reflection_methods[$method_name])) {
            $ref = new ReflectionMethod(OraBooks_Commissions::class, $method_name);
            $ref->setAccessible(true);
            $this->reflection_methods[$method_name] = $ref;
        }
        return $this->reflection_methods[$method_name]->invoke($this->commissions, ...$args);
    }

    public function run() {
        try {
            $this->setup_environment();

            $this->test_release_je_creation();
            $this->test_writeback_on_forfeiture();
            $this->test_idempotent_re_runs();
            $this->test_inactive_customer_skips();
            $this->test_double_entry_balancing();
            $this->test_escrow_schedule_generation();
            $this->test_customer_active_status_lifecycle();

            echo "\n";
            $this->test_expire_old_commissions();
            $this->test_expire_old_commissions_recent_kept();
            $this->test_expire_old_escrow_schedules();
            $this->test_process_daily_jobs();

            // Commission lifecycle tests
            echo "\n";
            $this->test_qualify_commission_basic();
            $this->test_qualify_commission_fee_net_calculation();
            $this->test_qualify_commission_custom_rate();
            $this->test_qualify_commission_invalid_transition();
            $this->test_qualify_commission_not_found();
            $this->test_qualify_commission_terminal_state();
            $this->test_mark_paid();
            $this->test_cancel_commission();

            echo "\n";
            $this->test_on_partner_suspended();
            $this->test_on_partner_suspended_no_codes();
            $this->test_on_partner_fraud_freeze();
            $this->test_on_partner_fraud_freeze_no_codes();

            echo "\n";
            $this->test_get_payout_summary_empty();
            $this->test_get_payout_summary_below_threshold();
            $this->test_get_payout_summary_meets_threshold();
            $this->test_get_payout_summary_threshold_boundary();
            $this->test_get_payout_summary_filters_status();

            echo "\n" . str_repeat('-', 70) . "\n";

            echo "\n";
            $this->test_get_commission_summary_empty();
            $this->test_get_commission_summary_only_pending();
            $this->test_get_commission_summary_only_qualified();
            $this->test_get_commission_summary_only_paid();
            $this->test_get_commission_summary_mixed_states();
            $this->test_get_commission_summary_cancelled_forfeited_ignored();
            $this->test_get_commission_summary_partner_isolation();

            echo "\n";
            $this->test_get_recent_commissions_empty();
            $this->test_get_recent_commissions_limit();
            $this->test_get_recent_commissions_default_limit();
            $this->test_get_recent_commissions_ordering();
            $this->test_get_recent_commissions_customer_join();
            $this->test_get_recent_commissions_partner_isolation();
            $this->test_get_recent_commissions_all_statuses();

            echo "\n";
            $this->test_get_partner_rate_default();
            $this->test_get_partner_rate_org_config();
            $this->test_get_partner_rate_no_rate_key();
            $this->test_get_partner_rate_partner_types();
            $this->test_get_partner_rate_type_overrides_org();
            $this->test_get_partner_rate_unknown_type();

            echo "\n";
            $this->test_ajax_commission_summary_empty();
            $this->test_ajax_commission_summary_with_pending_estimate();
            echo "\n";
            $this->test_ajax_commission_history_empty();
            $this->test_ajax_commission_history_with_data();
            $this->test_ajax_commission_history_custom_limit();

            echo "\n";
            $this->test_ajax_payout_summary_empty();
            $this->test_ajax_payout_summary_with_items();
            $this->test_ajax_payout_summary_meets_threshold();
            echo " RESULTS: {$this->passed} passed, {$this->failed} failed\n";
            echo str_repeat('-', 70) . "\n\n";
        } finally {
            $this->wpdb->cleanup();
        }
        return $this->failed === 0;
    }

    private function setup_environment() {
        echo "--- Setup: Creating database tables ---\n";

        create_mock_users_table($this->wpdb);

        // Create CoA table
        $coa_table = $this->wpdb->base_prefix . 'orabooks_chart_of_accounts';
        $this->wpdb->query("CREATE TABLE IF NOT EXISTS {$coa_table} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code VARCHAR(50), name VARCHAR(255), account_type VARCHAR(5),
            mode_compatibility VARCHAR(100) DEFAULT 'all', description TEXT,
            is_system INTEGER DEFAULT 1, is_active INTEGER DEFAULT 1, created_by INTEGER DEFAULT 0
        )");

        // Create CoA accounts for commission accounting
        $accounts = [
            ['code' => 5600, 'name' => 'Commission Expense', 'account_type' => 'X', 'mode_compatibility' => 'all', 'is_system' => 1],
            ['code' => 2400, 'name' => 'Commission Payable', 'account_type' => 'L', 'mode_compatibility' => 'all', 'is_system' => 1],
            ['code' => 2410, 'name' => 'Commission Fee Payable', 'account_type' => 'L', 'mode_compatibility' => 'all', 'is_system' => 1],
        ];
        foreach ($accounts as $acct) {
            $exists = $this->wpdb->get_var("SELECT id FROM {$coa_table} WHERE code = '{$acct['code']}'");
            if (!$exists) $this->wpdb->insert($coa_table, $acct);
        }

        // Create JE tables
        $je = OraBooks_Journal_Entry::get_instance();
        $je->create_je_tables();

        // Initialize OraBooks_Commissions and create tables
        $this->commissions = OraBooks_Commissions::get_instance();
        $this->commissions->init_table_names();
        $this->commissions->create_tables();

        echo "--- Setup complete ---\n\n";
    }

    private function create_test_commission($status = 'qualified', $amount = 100.00, $rate = 10.0) {
        global $test_events;
        $test_events = [];

        $partner_user_id = 10;
        $customer_user_id = 20;

        $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';
        $this->wpdb->insert($comm_table, [
            'attribution_id' => 1, 'partner_user_id' => $partner_user_id,
            'partner_org_id' => 1, 'customer_user_id' => $customer_user_id,
            'partner_code_used' => 'PARTNER-TEST',
            'attribution_date' => date('Y-m-d H:i:s', strtotime('-60 days')),
            'verified_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
            'commission_rate' => $rate, 'commission_amount' => $amount,
            'qualified_amount' => $amount * 100 / $rate,
            'fee_amount' => round($amount * 0.025, 2),
            'net_amount' => round($amount - ($amount * 0.025), 2),
            'status' => $status, 'qualified_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
            'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
        ]);
        $commission_id = $this->wpdb->insert_id;

        // Insert escrow schedule entry for current month
        $escrow_table = $this->wpdb->base_prefix . 'orabooks_commission_escrow_schedule';
        $projected = round(10 * ($rate / 100), 2);
        $this->wpdb->insert($escrow_table, [
            'commission_id' => $commission_id,
            'scheduled_release_date' => date('Y-m-d', strtotime('last day of this month')),
            'release_month' => date('Y-m'), 'projected_amount' => $projected,
            'status' => OraBooks_Commissions::ESCROW_PENDING,
        ]);
        $escrow_id = $this->wpdb->insert_id;

        // Set customer as active
        $status_table = $this->wpdb->base_prefix . 'orabooks_customer_active_status';
        $this->wpdb->query("INSERT INTO {$status_table} (customer_id, is_active, last_paid_invoice_date)
            VALUES ({$customer_user_id}, 1, datetime('now'))");

        return [$commission_id, $escrow_id, $customer_user_id, $partner_user_id];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 1: Release JE Creation
    // ═══════════════════════════════════════════════════════════════════════

    private function test_release_je_creation() {
        echo "TEST 1: Monthly release creates correct Journal Entry (Dr Commission Expense, Cr Commission Payable)\n";
        echo str_repeat('-', 70) . "\n";

        try {
            list($commission_id, $escrow_id, $customer_id, $partner_id) = $this->create_test_commission('qualified', 100.00, 10.0);

            $je_id = $this->call_private('book_commission_release_je', $commission_id, $partner_id, 10.00);
            $this->assert(!is_wp_error($je_id), 'book_commission_release_je() succeeded', __LINE__);

            $je_table = $this->wpdb->base_prefix . 'orabooks_journal_entries';
            $je = $this->wpdb->get_row("SELECT * FROM {$je_table} WHERE id = {$je_id}");
            $this->assert($je !== null, 'Journal Entry created', __LINE__);
            $this->assertEquals((float)$je->total_debit, (float)$je->total_credit, 'Double-entry: Debits = Credits', __LINE__);
            $this->assertEquals('posted', $je->status, 'JE is auto-posted', __LINE__);
            $this->assertEquals('commission_release', $je->source_type, 'JE source_type = commission_release', __LINE__);
            $this->assertEquals($commission_id, (int)$je->source_id, 'JE source_id = commission_id', __LINE__);

            $jel_table = $this->wpdb->base_prefix . 'orabooks_journal_entry_lines';
            $lines = $this->wpdb->get_results("SELECT * FROM {$jel_table} WHERE journal_entry_id = {$je_id} ORDER BY line_order");
            $this->assert(count($lines) === 2, 'JE has exactly 2 lines', __LINE__);
            $this->assertEquals('debit', $lines[0]->line_type, 'Line 1 is debit (Dr Commission Expense)', __LINE__);
            $this->assertEquals('credit', $lines[1]->line_type, 'Line 2 is credit (Cr Commission Payable)', __LINE__);
            $this->assertEquals((float)$lines[0]->amount, (float)$lines[1]->amount, 'Debit amount equals credit amount', __LINE__);

            global $test_events;
            $has_event = false;
            foreach ($test_events as $ev) {
                if ($ev['tag'] === 'orabooks_security_event' && isset($ev['args'][0]) && $ev['args'][0] === 'commission_monthly_release') {
                    $has_event = true;
                    $this->assertEquals($commission_id, $ev['args'][1]['commission_id'] ?? null, 'Audit event has correct commission_id', __LINE__);
                    break;
                }
            }
            $this->assert($has_event, 'Audit event commission_monthly_release fired', __LINE__);
            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 2: Writeback on Forfeiture
    // ═══════════════════════════════════════════════════════════════════════

    private function test_writeback_on_forfeiture() {
        echo "TEST 2: Writeback reverses liability when commission is forfeited\n";
        echo str_repeat('-', 70) . "\n";

        try {
            global $test_events;
            $test_events = [];

            list($commission_id, $escrow_id, $customer_id, $partner_id) = $this->create_test_commission('qualified', 100.00, 10.0);

            $je_id = $this->call_private('book_commission_release_je', $commission_id, $partner_id, 10.00);
            $this->assert(!is_wp_error($je_id), 'Release JE created', __LINE__);

            $released_table = $this->wpdb->base_prefix . 'orabooks_commissions_released';
            $this->wpdb->insert($released_table, [
                'commission_id' => $commission_id, 'escrow_schedule_id' => $escrow_id,
                'release_month' => date('Y-m'), 'customer_user_id' => $customer_id,
                'partner_user_id' => $partner_id, 'released_amount' => 10.00,
                'je_id' => $je_id,
            ]);
            $released_id = $this->wpdb->insert_id;

            $count = $this->commissions->forfeit_partner_commissions($partner_id, 'Test: fraud freeze');
            $this->assert($count > 0, "{$count} commissions forfeited", __LINE__);

            $writeback_count = $this->commissions->process_expiry_writeback();
            $this->assert($writeback_count >= 1, "Writeback processed ({$writeback_count})", __LINE__);

            $released = $this->wpdb->get_row("SELECT * FROM {$released_table} WHERE id = {$released_id}");
            $this->assert($released !== null, 'Released record found', __LINE__);
            $this->assert($released->writeback_je_id !== null, "Writeback JE ID is set ({$released->writeback_je_id})", __LINE__);

            $je_table = $this->wpdb->base_prefix . 'orabooks_journal_entries';
            $wb_je = $this->wpdb->get_row("SELECT * FROM {$je_table} WHERE id = {$released->writeback_je_id}");
            $this->assert($wb_je !== null, 'Writeback Journal Entry exists', __LINE__);
            $this->assertEquals((float)$wb_je->total_debit, (float)$wb_je->total_credit, 'Writeback double-entry: Debits = Credits', __LINE__);
            $this->assertEquals('commission_writeback', $wb_je->source_type, 'Writeback source_type = commission_writeback', __LINE__);

            $jel_table = $this->wpdb->base_prefix . 'orabooks_journal_entry_lines';
            $lines = $this->wpdb->get_results("SELECT * FROM {$jel_table} WHERE journal_entry_id = {$released->writeback_je_id} ORDER BY line_order");
            $this->assert(count($lines) === 2, 'Writeback JE has exactly 2 lines', __LINE__);
            $this->assertEquals('debit', $lines[0]->line_type, 'Writeback line 1 is debit (Dr Commission Payable)', __LINE__);
            $this->assertEquals('credit', $lines[1]->line_type, 'Writeback line 2 is credit (Cr Commission Expense)', __LINE__);
            $this->assertEquals((float)$lines[0]->amount, (float)$lines[1]->amount, 'Writeback debit = credit amount', __LINE__);

            $has_event = false;
            foreach ($test_events as $ev) {
                if ($ev['tag'] === 'orabooks_security_event' && isset($ev['args'][0]) && $ev['args'][0] === 'commission_expiry_writeback') {
                    $has_event = true; break;
                }
            }
            $this->assert($has_event, 'Audit event commission_expiry_writeback fired', __LINE__);
            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 3: Idempotent Re-runs
    // ═══════════════════════════════════════════════════════════════════════

    private function test_idempotent_re_runs() {
        echo "TEST 3: Expiry writeback is idempotent on re-runs (no duplicate JEs)\n";
        echo str_repeat('-', 70) . "\n";

        try {
            global $test_events;
            $test_events = [];

            list($commission_id, $escrow_id, $customer_id, $partner_id) = $this->create_test_commission('qualified', 100.00, 10.0);

            $je_id = $this->call_private('book_commission_release_je', $commission_id, $partner_id, 10.00);
            $released_table = $this->wpdb->base_prefix . 'orabooks_commissions_released';
            $this->wpdb->insert($released_table, [
                'commission_id' => $commission_id, 'escrow_schedule_id' => $escrow_id,
                'release_month' => date('Y-m'), 'customer_user_id' => $customer_id,
                'partner_user_id' => $partner_id, 'released_amount' => 10.00,
                'je_id' => $je_id,
            ]);

            $this->commissions->forfeit_partner_commissions($partner_id, 'Test forfeiture');

            $count1 = $this->commissions->process_expiry_writeback();
            $this->assert($count1 >= 1, "First writeback processed ({$count1})", __LINE__);

            $je_table = $this->wpdb->base_prefix . 'orabooks_journal_entries';
            $wb_after_first = (int)$this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$je_table} WHERE source_type = 'commission_writeback' AND source_id = {$commission_id}"
            );
            $released_after_first = $this->wpdb->get_row("SELECT * FROM {$released_table} WHERE commission_id = {$commission_id}");

            $count2 = $this->commissions->process_expiry_writeback();
            $this->assertEquals(0, $count2, 'Second writeback returns 0 (nothing to write back)', __LINE__);

            $wb_after_second = (int)$this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$je_table} WHERE source_type = 'commission_writeback' AND source_id = {$commission_id}"
            );
            $this->assertEquals($wb_after_first, $wb_after_second, "Writeback JE count unchanged on re-run", __LINE__);

            $released_after_second = $this->wpdb->get_row("SELECT * FROM {$released_table} WHERE commission_id = {$commission_id}");
            $this->assertEquals($released_after_first->writeback_je_id, $released_after_second->writeback_je_id, 'writeback_je_id unchanged', __LINE__);
            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 4: Inactive Customer Skip
    // ═══════════════════════════════════════════════════════════════════════

    private function test_inactive_customer_skips() {
        echo "TEST 4: Inactive customer causes skip (no release, escrow marked expired)\n";
        echo str_repeat('-', 70) . "\n";

        try {
            global $test_events;
            $test_events = [];

            $partner_user_id = 30;
            $customer_user_id = 40;

            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 2, 'partner_user_id' => $partner_user_id,
                'partner_org_id' => 1, 'customer_user_id' => $customer_user_id,
                'partner_code_used' => 'PARTNER-TEST2', 'commission_rate' => 10.0,
                'commission_amount' => 50.00, 'qualified_amount' => 500.00,
                'fee_amount' => 1.25, 'net_amount' => 48.75, 'status' => 'qualified',
                'qualified_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
            ]);
            $commission_id = $this->wpdb->insert_id;

            $escrow_table = $this->wpdb->base_prefix . 'orabooks_commission_escrow_schedule';
            $this->wpdb->insert($escrow_table, [
                'commission_id' => $commission_id,
                'scheduled_release_date' => date('Y-m-d', strtotime('last day of this month')),
                'release_month' => date('Y-m'), 'projected_amount' => 1.00,
                'status' => OraBooks_Commissions::ESCROW_PENDING,
            ]);
            $escrow_id = $this->wpdb->insert_id;

            // DO NOT create customer_active_status record — customer is inactive
            $is_active = $this->commissions->is_customer_active($customer_user_id);
            $this->assert($is_active === false, 'is_customer_active() returns false for inactive customer', __LINE__);

            $refreshed = $this->commissions->refresh_customer_active_status($customer_user_id);
            $this->assert($refreshed === false, 'refresh_customer_active_status() returns false', __LINE__);

            // Simulate what process_monthly_release does for inactive customers
            $this->wpdb->update(
                $escrow_table,
                ['status' => OraBooks_Commissions::ESCROW_EXPIRED, 'actual_amount' => 0],
                ['id' => $escrow_id],
                ['%s', '%f'], ['%d']
            );

            $escrow = $this->wpdb->get_row("SELECT * FROM {$escrow_table} WHERE id = {$escrow_id}");
            $this->assertEquals(OraBooks_Commissions::ESCROW_EXPIRED, $escrow->status, 'Escrow marked as expired for inactive customer', __LINE__);

            $je_table = $this->wpdb->base_prefix . 'orabooks_journal_entries';
            $jes = $this->wpdb->get_var("SELECT COUNT(*) FROM {$je_table} WHERE source_type = 'commission_release' AND source_id = {$commission_id}");
            $this->assertEquals(0, (int)$jes, 'No JE created for inactive customer', __LINE__);

            $released_table = $this->wpdb->base_prefix . 'orabooks_commissions_released';
            $released = $this->wpdb->get_var("SELECT COUNT(*) FROM {$released_table} WHERE commission_id = {$commission_id}");
            $this->assertEquals(0, (int)$released, 'No released record for inactive customer', __LINE__);

            // Test that adding a subscription makes the customer active
            $sub_table = $this->wpdb->base_prefix . 'orabooks_subscriptions';
            $this->wpdb->query("CREATE TABLE IF NOT EXISTS {$sub_table} (id INTEGER PRIMARY KEY, user_id INT, status VARCHAR(20), ends_at DATETIME)");
            $this->wpdb->query("INSERT INTO {$sub_table} (user_id, status, ends_at) VALUES ({$customer_user_id}, 'active', datetime('now', '+30 days'))");

            $refreshed_new = $this->commissions->refresh_customer_active_status($customer_user_id);
            $this->assert($refreshed_new === true, 'After subscription, refresh_customer_active_status() returns true', __LINE__);

            $is_active_new = $this->commissions->is_customer_active($customer_user_id);
            $this->assert($is_active_new === true, 'is_customer_active() returns true after subscription', __LINE__);
            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 5: Double-Entry Balancing
    // ═══════════════════════════════════════════════════════════════════════

    private function test_double_entry_balancing() {
        echo "TEST 5: All created JEs pass double-entry check (Dr = Cr)\n";
        echo str_repeat('-', 70) . "\n";

        try {
            for ($i = 0; $i < 3; $i++) {
                list($cid, $eid, $cust, $part) = $this->create_test_commission('qualified', 50.0 * ($i + 1), 10.0 + $i * 2);
                $je_id = $this->call_private('book_commission_release_je', $cid, $part, 10.0 + $i);
                $this->assert(!is_wp_error($je_id), "Release JE created for commission #{$cid}", __LINE__);
            }

            $je_table = $this->wpdb->base_prefix . 'orabooks_journal_entries';
            $jes = $this->wpdb->get_results("SELECT * FROM {$je_table} WHERE status = 'posted'");

            $all_balanced = true;
            foreach ($jes as $je) {
                $diff = abs((float)$je->total_debit - (float)$je->total_credit);
                if ($diff > 0.01) { $all_balanced = false; echo "  [FAIL] JE #{$je->id}: Dr={$je->total_debit}, Cr={$je->total_credit}, diff={$diff}\n"; }
            }
            $this->assert($all_balanced, "All {$jes} posted JEs are balanced", __LINE__);

            $jel_table = $this->wpdb->base_prefix . 'orabooks_journal_entry_lines';
            foreach ($jes as $je) {
                $debit_sum = (float)$this->wpdb->get_var("SELECT COALESCE(SUM(amount), 0) FROM {$jel_table} WHERE journal_entry_id = {$je->id} AND line_type = 'debit'");
                $credit_sum = (float)$this->wpdb->get_var("SELECT COALESCE(SUM(amount), 0) FROM {$jel_table} WHERE journal_entry_id = {$je->id} AND line_type = 'credit'");
                $this->assertEquals($debit_sum, $credit_sum, "JE #{$je->id} line-level balancing", __LINE__);
            }
            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 6: Escrow Schedule Generation
    // ═══════════════════════════════════════════════════════════════════════

    private function test_escrow_schedule_generation() {
        echo "TEST 6: Escrow schedule generates correct monthly projections (72 entries for 6 years)\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 3, 'partner_user_id' => 50,
                'partner_org_id' => 1, 'customer_user_id' => 60,
                'partner_code_used' => 'PARTNER-ESCROW', 'commission_rate' => 10.0,
                'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $commission_id = $this->wpdb->insert_id;

            $this->commissions->generate_escrow_schedule($commission_id, 10.0);

            $escrow_table = $this->wpdb->base_prefix . 'orabooks_commission_escrow_schedule';
            $count = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$escrow_table} WHERE commission_id = {$commission_id}");
            $this->assert($count === 72, "Escrow schedule has {$count} entries (expected 72)", __LINE__);

            $negative = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$escrow_table} WHERE commission_id = {$commission_id} AND projected_amount <= 0");
            $this->assertEquals(0, $negative, 'All projected amounts are positive', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 7: Customer Active Status Lifecycle
    // ═══════════════════════════════════════════════════════════════════════

    private function test_customer_active_status_lifecycle() {
        echo "TEST 7: Customer active status lifecycle — refresh, read, multi-customer\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $customer_id = 100;
            $partner_id = 200;

            $is_active = $this->commissions->is_customer_active($customer_id);
            $this->assert($is_active === false, 'Initially inactive for new customer', __LINE__);

            $last_paid = $this->commissions->get_customer_last_paid_date($customer_id);
            $this->assert($last_paid === null, 'get_customer_last_paid_date returns null', __LINE__);

            $attr_table = $this->wpdb->base_prefix . 'orabooks_partner_attributions';
            $this->wpdb->query("CREATE TABLE IF NOT EXISTS {$attr_table} (
                id INTEGER PRIMARY KEY AUTOINCREMENT, partner_user_id INT,
                customer_user_id INT, partner_code_used VARCHAR(50),
                status VARCHAR(20), attribution_date DATETIME, verified_at DATETIME
            )");
            $this->wpdb->query("INSERT INTO {$attr_table} (partner_user_id, customer_user_id,
                partner_code_used, status, attribution_date, verified_at)
                VALUES ({$partner_id}, {$customer_id}, 'TEST-CODE', 'verified',
                datetime('now', '-5 days'), datetime('now', '-5 days'))");

            $refreshed = $this->commissions->refresh_customer_active_status($customer_id);
            $this->assert($refreshed === true, 'Customer becomes active after verified attribution', __LINE__);

            $is_active_after = $this->commissions->is_customer_active($customer_id);
            $this->assert($is_active_after === true, 'is_customer_active returns true', __LINE__);

            $last_paid_after = $this->commissions->get_customer_last_paid_date($customer_id);
            $this->assert($last_paid_after !== null, 'get_customer_last_paid_date returns date', __LINE__);

            $customer_id2 = 101;
            $this->wpdb->query("INSERT INTO {$attr_table} (partner_user_id, customer_user_id,
                partner_code_used, status, attribution_date, verified_at)
                VALUES ({$partner_id}, {$customer_id2}, 'TEST-CODE', 'verified',
                datetime('now', '-3 days'), datetime('now', '-3 days'))");

            $this->commissions->refresh_partner_customers($partner_id);

            $is_active2 = $this->commissions->is_customer_active($customer_id2);
            $this->assert($is_active2 === true, 'Second customer active after batch refresh', __LINE__);

            $count = $this->commissions->get_active_customer_count_for_partner($partner_id);
            $this->assert($count >= 2, "Active customer count for partner >= 2 (got {$count})", __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 8: Expire Old Commissions — old ones get forfeited
    // ═══════════════════════════════════════════════════════════════════════

    private function test_expire_old_commissions() {
        echo "TEST 8: expire_old_commissions() — old pending/qualified commissions get forfeited\n";
        echo str_repeat('-', 70) . "\n";

        try {
            global $test_events;
            $test_events = [];

            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Commission A: pending, 7+ years old (should expire)
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 10, 'partner_user_id' => 1,
                'partner_org_id' => 1, 'customer_user_id' => 1001,
                'partner_code_used' => 'OLD1', 'commission_rate' => 10.0,
                'commission_amount' => 50.00, 'status' => 'pending',
                'created_at' => '2017-06-01 00:00:00',
            ]);
            $old_pending_id = $this->wpdb->insert_id;

            // Commission B: qualified, 7+ years old (should expire)
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 11, 'partner_user_id' => 1,
                'partner_org_id' => 1, 'customer_user_id' => 1002,
                'partner_code_used' => 'OLD2', 'commission_rate' => 10.0,
                'commission_amount' => 100.00, 'qualified_amount' => 1000.00,
                'fee_amount' => 2.50, 'net_amount' => 97.50, 'status' => 'qualified',
                'qualified_at' => '2017-06-01 00:00:00',
                'created_at' => '2017-06-01 00:00:00',
            ]);
            $old_qualified_id = $this->wpdb->insert_id;

            // Commission C: pending, 1 year old (should NOT expire with max_years=6)
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 12, 'partner_user_id' => 1,
                'partner_org_id' => 1, 'customer_user_id' => 1003,
                'partner_code_used' => 'RECENT', 'commission_rate' => 10.0,
                'commission_amount' => 25.00, 'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 year')),
            ]);
            $recent_pending_id = $this->wpdb->insert_id;

            // Commission D: already paid, 7+ years old (terminal — should NOT be touched)
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 13, 'partner_user_id' => 1,
                'partner_org_id' => 1, 'customer_user_id' => 1004,
                'partner_code_used' => 'PAID1', 'commission_rate' => 10.0,
                'commission_amount' => 30.00, 'paid_amount' => 30.00,
                'status' => 'paid', 'paid_at' => '2017-07-01 00:00:00',
                'created_at' => '2017-06-01 00:00:00',
            ]);
            $paid_old_id = $this->wpdb->insert_id;

            // Run expiry with max_years = 6
            $expired_count = $this->commissions->expire_old_commissions(6);
            $this->assert($expired_count >= 2, "expire_old_commissions() returned {$expired_count} (expected >= 2)", __LINE__);

            // Verify old pending commission is now forfeited
            $old_pending = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$old_pending_id}");
            $this->assertEquals(OraBooks_Commissions::STATUS_FORFEITED, $old_pending->status,
                'Old pending commission forfeited', __LINE__);
            $this->assert(strpos($old_pending->status_notes ?? '', 'expired') !== false,
                'Status notes mention expiry', __LINE__);

            // Verify old qualified commission is now forfeited
            $old_qualified = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$old_qualified_id}");
            $this->assertEquals(OraBooks_Commissions::STATUS_FORFEITED, $old_qualified->status,
                'Old qualified commission forfeited', __LINE__);

            // Verify recent pending commission is still pending
            $recent = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$recent_pending_id}");
            $this->assertEquals(OraBooks_Commissions::STATUS_PENDING, $recent->status,
                'Recent pending commission unchanged', __LINE__);

            // Verify old paid commission is still paid (terminal, not touched)
            $paid = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$paid_old_id}");
            $this->assertEquals(OraBooks_Commissions::STATUS_PAID, $paid->status,
                'Old paid commission unchanged (terminal)', __LINE__);

            // Verify audit event
            $has_event = false;
            foreach ($test_events as $ev) {
                if ($ev['tag'] === 'orabooks_security_event' && isset($ev['args'][0]) && $ev['args'][0] === 'commissions_expired') {
                    $has_event = true;
                    break;
                }
            }
            $this->assert($has_event, 'Audit event commissions_expired fired', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 9: Expire Old Commissions — none to expire returns 0
    // ═══════════════════════════════════════════════════════════════════════

    private function test_expire_old_commissions_recent_kept() {
        echo "TEST 9: expire_old_commissions() — recent commissions are NOT expired\n";
        echo str_repeat('-', 70) . "\n";

        try {
            global $test_events;
            $test_events = [];

            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Create only recent commissions (1 year old, well within 6-year limit)
            for ($i = 0; $i < 3; $i++) {
                $this->wpdb->insert($comm_table, [
                    'attribution_id' => 100 + $i, 'partner_user_id' => 2,
                    'partner_org_id' => 1, 'customer_user_id' => 2000 + $i,
                    'partner_code_used' => 'RECENT' . $i, 'commission_rate' => 10.0,
                    'commission_amount' => 10.0 * ($i + 1),
                    'status' => ($i % 2 === 0) ? 'pending' : 'qualified',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-1 year')),
                ]);
            }

            // Run expiry with max_years = 6
            $expired_count = $this->commissions->expire_old_commissions(6);
            $this->assertEquals(0, $expired_count,
                'No commissions expired when all are recent', __LINE__);

            // Verify all still in their original status
            $count_forfeited = (int)$this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$comm_table} WHERE partner_user_id = 2 AND status = 'forfeited'"
            );
            $this->assertEquals(0, $count_forfeited,
                'No commissions forfeited when all are recent', __LINE__);

            // No audit event should have been fired
            $has_event = false;
            foreach ($test_events as $ev) {
                if ($ev['tag'] === 'orabooks_security_event' && isset($ev['args'][0]) && $ev['args'][0] === 'commissions_expired') {
                    $has_event = true; break;
                }
            }
            $this->assert($has_event === false, 'No commissions_expired audit event for 0-expiry', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 10: Expire Old Escrow Schedules
    // ═══════════════════════════════════════════════════════════════════════

    private function test_expire_old_escrow_schedules() {
        echo "TEST 10: expire_old_escrow_schedules() — old pending schedules get expired\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';
            $escrow_table = $this->wpdb->base_prefix . 'orabooks_commission_escrow_schedule';

            // Create a recent commission as parent
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 20, 'partner_user_id' => 3,
                'partner_org_id' => 1, 'customer_user_id' => 3000,
                'partner_code_used' => 'ESCROW-PARENT', 'commission_rate' => 10.0,
                'status' => 'pending', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            ]);
            $parent_id = $this->wpdb->insert_id;

            // Escrow A: old date (7+ years ago) — should expire
            $old_date = date('Y-m-d', strtotime('-7 years'));
            $this->wpdb->insert($escrow_table, [
                'commission_id' => $parent_id,
                'scheduled_release_date' => $old_date,
                'release_month' => date('Y-m', strtotime('-7 years')),
                'projected_amount' => 1.00,
                'status' => OraBooks_Commissions::ESCROW_PENDING,
            ]);
            $old_escrow_id = $this->wpdb->insert_id;

            // Escrow B: already released — should NOT be touched
            $this->wpdb->insert($escrow_table, [
                'commission_id' => $parent_id,
                'scheduled_release_date' => $old_date,
                'release_month' => date('Y-m', strtotime('-7 years')),
                'projected_amount' => 1.00,
                'status' => OraBooks_Commissions::ESCROW_RELEASED,
            ]);
            $released_escrow_id = $this->wpdb->insert_id;

            // Escrow C: recent date (1 year ago) — should NOT expire with max_years=6
            $recent_date = date('Y-m-d', strtotime('-1 year'));
            $this->wpdb->insert($escrow_table, [
                'commission_id' => $parent_id,
                'scheduled_release_date' => $recent_date,
                'release_month' => date('Y-m', strtotime('-1 year')),
                'projected_amount' => 1.00,
                'status' => OraBooks_Commissions::ESCROW_PENDING,
            ]);
            $recent_escrow_id = $this->wpdb->insert_id;

            // Run expiry with max_years = 6
            $expired_count = $this->commissions->expire_old_escrow_schedules(6);
            $this->assert($expired_count >= 1, "expire_old_escrow_schedules() returned {$expired_count}", __LINE__);

            // Verify old pending escrow is now expired
            $old_escrow = $this->wpdb->get_row("SELECT * FROM {$escrow_table} WHERE id = {$old_escrow_id}");
            $this->assertEquals(OraBooks_Commissions::ESCROW_EXPIRED, $old_escrow->status,
                'Old pending escrow expired', __LINE__);

            // Verify released escrow is still released
            $released_escrow = $this->wpdb->get_row("SELECT * FROM {$escrow_table} WHERE id = {$released_escrow_id}");
            $this->assertEquals(OraBooks_Commissions::ESCROW_RELEASED, $released_escrow->status,
                'Released escrow unchanged', __LINE__);

            // Verify recent pending escrow is still pending
            $recent_escrow = $this->wpdb->get_row("SELECT * FROM {$escrow_table} WHERE id = {$recent_escrow_id}");
            $this->assertEquals(OraBooks_Commissions::ESCROW_PENDING, $recent_escrow->status,
                'Recent pending escrow unchanged', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 11: process_daily_jobs() orchestrates all sub-tasks
    // ═══════════════════════════════════════════════════════════════════════

    private function test_process_daily_jobs() {
        echo "TEST 11: process_daily_jobs() orchestrates expiry, writeback, and escrow cleanup\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';
            $escrow_table = $this->wpdb->base_prefix . 'orabooks_commission_escrow_schedule';

            // Create a mix of data that process_daily_jobs should process
            // 1. Old pending commission (should expire)
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 30, 'partner_user_id' => 4,
                'partner_org_id' => 1, 'customer_user_id' => 4001,
                'partner_code_used' => 'OLD-PENDING', 'commission_rate' => 10.0,
                'commission_amount' => 50.00, 'status' => 'pending',
                'created_at' => '2016-01-01 00:00:00',
            ]);
            $old_comm_id = $this->wpdb->insert_id;

            // 2. Recent qualified commission (should stay)
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 31, 'partner_user_id' => 4,
                'partner_org_id' => 1, 'customer_user_id' => 4002,
                'partner_code_used' => 'RECENT-QUAL', 'commission_rate' => 10.0,
                'commission_amount' => 100.00, 'qualified_amount' => 1000.00,
                'fee_amount' => 2.50, 'net_amount' => 97.50, 'status' => 'qualified',
                'qualified_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
            ]);
            $recent_comm_id = $this->wpdb->insert_id;

            // 3. Old escrow schedule for the recent commission (should expire)
            $old_escrow_date = date('Y-m-d', strtotime('-7 years'));
            $this->wpdb->insert($escrow_table, [
                'commission_id' => $recent_comm_id,
                'scheduled_release_date' => $old_escrow_date,
                'release_month' => date('Y-m', strtotime('-7 years')),
                'projected_amount' => 1.00,
                'status' => OraBooks_Commissions::ESCROW_PENDING,
            ]);
            $old_escrow_id = $this->wpdb->insert_id;

            // Run process_daily_jobs() — it orchestrates all sub-tasks
            $this->commissions->process_daily_jobs();

            // Verify old pending commission was expired
            $old_comm = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$old_comm_id}");
            $this->assertEquals(OraBooks_Commissions::STATUS_FORFEITED, $old_comm->status,
                'Old pending commission expired by process_daily_jobs()', __LINE__);

            // Verify recent qualified commission is still qualified
            $recent_comm = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$recent_comm_id}");
            $this->assertEquals(OraBooks_Commissions::STATUS_QUALIFIED, $recent_comm->status,
                'Recent qualified commission unchanged by process_daily_jobs()', __LINE__);

            // Verify old escrow schedule was expired
            $old_escrow = $this->wpdb->get_row("SELECT * FROM {$escrow_table} WHERE id = {$old_escrow_id}");
            $this->assertEquals(OraBooks_Commissions::ESCROW_EXPIRED, $old_escrow->status,
                'Old escrow expired by process_daily_jobs()', __LINE__);

            // Verify audit events were fired
            global $test_events;
            $has_expired_event = false;
            foreach ($test_events as $ev) {
                if ($ev['tag'] === 'orabooks_security_event' && isset($ev['args'][0])) {
                    if ($ev['args'][0] === 'commissions_expired') $has_expired_event = true;
                }
            }
            $this->assert($has_expired_event, 'commissions_expired audit event fired by process_daily_jobs()', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 12: qualify_commission() — basic lifecycle
    // ═══════════════════════════════════════════════════════════════════════

    private function test_qualify_commission_basic() {
        echo "TEST 12: qualify_commission() — pending → qualified with correct status\n";
        echo str_repeat('-', 70) . "\n";

        try {
            global $test_events;
            $test_events = [];

            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Create a pending commission
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 40, 'partner_user_id' => 100,
                'partner_org_id' => 1, 'customer_user_id' => 5001,
                'partner_code_used' => 'QUAL-TEST', 'commission_rate' => 10.0,
                'status' => OraBooks_Commissions::STATUS_PENDING,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $commission_id = $this->wpdb->insert_id;

            // Call qualify_commission
            $result = $this->commissions->qualify_commission($commission_id, 1000.00);
            $this->assert($result === true, 'qualify_commission() returns true', __LINE__);

            // Verify status changed to qualified
            $commission = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$commission_id}");
            $this->assertEquals(OraBooks_Commissions::STATUS_QUALIFIED, $commission->status,
                'Commission status is qualified', __LINE__);
            $this->assert($commission->qualified_at !== null, 'qualified_at is set', __LINE__);

            // Verify calculated amounts (rate=10%, fee_rate=2.5%)
            // commission_amount = 1000 * 10/100 = 100.00
            // fee_amount = 100 * 2.5/100 = 2.50
            // net_amount = 100 - 2.50 = 97.50
            $this->assertEquals(100.00, (float)$commission->commission_amount, 'commission_amount = 100.00 (10% of 1000)', __LINE__);
            $this->assertEquals(1000.00, (float)$commission->qualified_amount, 'qualified_amount = 1000.00', __LINE__);

            // Verify audit event was fired
            $has_event = false;
            foreach ($test_events as $ev) {
                if ($ev['tag'] === 'orabooks_security_event' && isset($ev['args'][0]) && $ev['args'][0] === 'commission_status_changed') {
                    $has_event = true;
                    $this->assertEquals($commission_id, $ev['args'][1]['commission_id'] ?? null, 'Audit event has correct commission_id', __LINE__);
                    $this->assertEquals('pending', $ev['args'][1]['from_status'] ?? '', 'Audit event from_status=pending', __LINE__);
                    $this->assertEquals('qualified', $ev['args'][1]['to_status'] ?? '', 'Audit event to_status=qualified', __LINE__);
                    break;
                }
            }
            $this->assert($has_event, 'Audit event commission_status_changed fired', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 13: qualify_commission() — fee/net calculation verification
    // ═══════════════════════════════════════════════════════════════════════

    private function test_qualify_commission_fee_net_calculation() {
        echo "TEST 13: qualify_commission() — fee and net amount calculation accuracy\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Create pending commission with 10% rate
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 41, 'partner_user_id' => 101,
                'partner_org_id' => 1, 'customer_user_id' => 5002,
                'partner_code_used' => 'CALC-TEST', 'commission_rate' => 10.0,
                'status' => OraBooks_Commissions::STATUS_PENDING,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $commission_id = $this->wpdb->insert_id;

            // Qualify with $750.33 qualified amount
            // commission_amount = 750.33 * 10/100 = 75.033 → 75.03
            // fee_amount = 75.03 * 2.5/100 = 1.87575 → 1.88
            // net_amount = 75.03 - 1.88 = 73.15
            $result = $this->commissions->qualify_commission($commission_id, 750.33);
            $this->assert($result === true, 'qualify_commission() succeeds', __LINE__);

            $commission = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$commission_id}");

            $expected_commission = round(750.33 * 0.10, 2);  // 75.03
            $expected_fee = round($expected_commission * 0.025, 2);  // 1.88
            $expected_net = round($expected_commission - $expected_fee, 2);  // 73.15

            $this->assertEquals($expected_commission, (float)$commission->commission_amount,
                "commission_amount = {$expected_commission}", __LINE__);
            $this->assertEquals($expected_fee, (float)$commission->fee_amount,
                "fee_amount = {$expected_fee}", __LINE__);
            $this->assertEquals($expected_net, (float)$commission->net_amount,
                "net_amount = {$expected_net}", __LINE__);

            // Verify net = gross - fee
            $this->assertEquals(
                round((float)$commission->commission_amount - (float)$commission->fee_amount, 2),
                (float)$commission->net_amount,
                'net_amount = commission_amount - fee_amount', __LINE__
            );

            // Verify status notes contain the expected format
            $expected_note = sprintf(
                'Commission qualified: %s gross, %s net (%.2f%% rate, %.2f%% fee)',
                number_format((float)$commission->commission_amount, 2),
                number_format((float)$commission->net_amount, 2),
                10.0, 2.5
            );
            $this->assert($commission->status_notes === $expected_note,
                'Status notes formatted correctly', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 14: qualify_commission() — custom commission rate
    // ═══════════════════════════════════════════════════════════════════════

    private function test_qualify_commission_custom_rate() {
        echo "TEST 14: qualify_commission() — custom commission rate (15%)\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Create pending commission with 15% custom rate
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 42, 'partner_user_id' => 102,
                'partner_org_id' => 1, 'customer_user_id' => 5003,
                'partner_code_used' => 'RATE-TEST', 'commission_rate' => 15.0,
                'status' => OraBooks_Commissions::STATUS_PENDING,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $commission_id = $this->wpdb->insert_id;

            // Qualify with $2000 qualified amount
            // commission_amount = 2000 * 15/100 = 300.00
            $result = $this->commissions->qualify_commission($commission_id, 2000.00);
            $this->assert($result === true, 'qualify_commission() succeeds with 15% rate', __LINE__);

            $commission = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$commission_id}");
            $this->assertEquals(300.00, (float)$commission->commission_amount,
                'commission_amount = 300.00 (15% of 2000)', __LINE__);
            $this->assertEquals(15.0, (float)$commission->commission_rate,
                'commission_rate preserved as 15%', __LINE__);

            // fee = 300 * 2.5/100 = 7.50
            $this->assertEquals(7.50, (float)$commission->fee_amount,
                'fee_amount = 7.50 (2.5% of 300)', __LINE__);

            // net = 300 - 7.50 = 292.50
            $this->assertEquals(292.50, (float)$commission->net_amount,
                'net_amount = 292.50 (300 - 7.50)', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 15: qualify_commission() — invalid transition (cannot re-qualify)
    // ═══════════════════════════════════════════════════════════════════════

    private function test_qualify_commission_invalid_transition() {
        echo "TEST 15: qualify_commission() — already qualified returns WP_Error\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Create an already qualified commission
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 43, 'partner_user_id' => 103,
                'partner_org_id' => 1, 'customer_user_id' => 5004,
                'partner_code_used' => 'DUP-TEST', 'commission_rate' => 10.0,
                'commission_amount' => 50.00, 'qualified_amount' => 500.00,
                'fee_amount' => 1.25, 'net_amount' => 48.75,
                'status' => OraBooks_Commissions::STATUS_QUALIFIED,
                'qualified_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
            ]);
            $commission_id = $this->wpdb->insert_id;

            // Try to qualify again — should fail
            // VALID_TRANSITIONS['qualified'] = ['paid', 'cancelled', 'forfeited'] — not 'qualified'
            $result = $this->commissions->qualify_commission($commission_id, 500.00);
            $this->assert(is_wp_error($result), 'qualify_commission() returns WP_Error for already qualified', __LINE__);
            $this->assertEquals('invalid_transition', $result->get_error_code(),
                'Error code is invalid_transition', __LINE__);

            // Verify commission status unchanged
            $commission = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$commission_id}");
            $this->assertEquals(OraBooks_Commissions::STATUS_QUALIFIED, $commission->status,
                'Commission still qualified', __LINE__);
            $this->assertEquals(50.00, (float)$commission->commission_amount,
                'commission_amount unchanged', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 16: qualify_commission() — non-existent commission
    // ═══════════════════════════════════════════════════════════════════════

    private function test_qualify_commission_not_found() {
        echo "TEST 16: qualify_commission() — non-existent commission returns WP_Error\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $result = $this->commissions->qualify_commission(999999, 1000.00);
            $this->assert(is_wp_error($result), 'qualify_commission() returns WP_Error for unknown ID', __LINE__);
            $this->assertEquals('not_found', $result->get_error_code(),
                'Error code is not_found', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 17: qualify_commission() — terminal states cannot be qualified
    // ═══════════════════════════════════════════════════════════════════════

    private function test_qualify_commission_terminal_state() {
        echo "TEST 17: qualify_commission() — cancelled/forfeited/paid states fail\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';
            $terminal_states = [
                'cancelled' => OraBooks_Commissions::STATUS_CANCELLED,
                'forfeited' => OraBooks_Commissions::STATUS_FORFEITED,
            ];

            foreach ($terminal_states as $label => $status) {
                $this->wpdb->insert($comm_table, [
                    'attribution_id' => 50 + strlen($label), 'partner_user_id' => 110,
                    'partner_org_id' => 1, 'customer_user_id' => 6000 + strlen($label),
                    'partner_code_used' => 'TERM-' . strtoupper($label),
                    'commission_rate' => 10.0, 'status' => $status,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $comm_id = $this->wpdb->insert_id;

                $result = $this->commissions->qualify_commission($comm_id, 500.00);
                $this->assert(is_wp_error($result),
                    "qualify_commission() fails for {$label} state", __LINE__);

                $commission = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$comm_id}");
                $this->assertEquals($status, $commission->status,
                    "Commission still {$label}", __LINE__);
            }

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 18: mark_paid() — qualified → paid lifecycle
    // ═══════════════════════════════════════════════════════════════════════

    private function test_mark_paid() {
        echo "TEST 18: mark_paid() — qualified commission moves to paid\n";
        echo str_repeat('-', 70) . "\n";

        try {
            global $test_events;
            $test_events = [];

            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Create qualified commission
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 60, 'partner_user_id' => 200,
                'partner_org_id' => 1, 'customer_user_id' => 7001,
                'partner_code_used' => 'PAY-TEST', 'commission_rate' => 10.0,
                'commission_amount' => 100.00, 'qualified_amount' => 1000.00,
                'fee_amount' => 2.50, 'net_amount' => 97.50,
                'status' => OraBooks_Commissions::STATUS_QUALIFIED,
                'qualified_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
            ]);
            $commission_id = $this->wpdb->insert_id;

            // Mark as paid with net amount
            $result = $this->commissions->mark_paid($commission_id, 97.50, 5001);
            $this->assert($result === true, 'mark_paid() returns true', __LINE__);

            // Verify status changed to paid
            $commission = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$commission_id}");
            $this->assertEquals(OraBooks_Commissions::STATUS_PAID, $commission->status,
                'Commission status is paid', __LINE__);
            $this->assertEquals(97.50, (float)$commission->paid_amount,
                'paid_amount = 97.50', __LINE__);
            $this->assertEquals(5001, (int)$commission->payout_batch_id,
                'payout_batch_id = 5001', __LINE__);
            $this->assert($commission->paid_at !== null, 'paid_at is set', __LINE__);

            // Verify audit event
            $has_event = false;
            foreach ($test_events as $ev) {
                if ($ev['tag'] === 'orabooks_security_event' && isset($ev['args'][0]) && $ev['args'][0] === 'commission_paid') {
                    $has_event = true;
                    $this->assertEquals($commission_id, $ev['args'][1]['commission_id'] ?? null, 'Audit event has correct commission_id', __LINE__);
                    $this->assertEquals(97.50, $ev['args'][1]['paid_amount'] ?? null, 'Audit event paid_amount=97.50', __LINE__);
                    break;
                }
            }
            $this->assert($has_event, 'Audit event commission_paid fired', __LINE__);

            // Verify cannot qualify a paid commission
            $requalify = $this->commissions->qualify_commission($commission_id, 500.00);
            $this->assert(is_wp_error($requalify), 'Cannot qualify a paid commission', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 19: cancel_commission() — qualified → cancelled
    // ═══════════════════════════════════════════════════════════════════════

    private function test_cancel_commission() {
        echo "TEST 19: cancel_commission() — qualified commission moves to cancelled\n";
        echo str_repeat('-', 70) . "\n";

        try {
            global $test_events;
            $test_events = [];

            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Create qualified commission
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 61, 'partner_user_id' => 201,
                'partner_org_id' => 1, 'customer_user_id' => 7002,
                'partner_code_used' => 'CANCEL-TEST', 'commission_rate' => 10.0,
                'commission_amount' => 50.00, 'qualified_amount' => 500.00,
                'fee_amount' => 1.25, 'net_amount' => 48.75,
                'status' => OraBooks_Commissions::STATUS_QUALIFIED,
                'qualified_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
            ]);
            $commission_id = $this->wpdb->insert_id;

            // Cancel with reason
            $result = $this->commissions->cancel_commission($commission_id, 'Customer refunded');
            $this->assert($result === true, 'cancel_commission() returns true', __LINE__);

            // Verify status changed to cancelled
            $commission = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$commission_id}");
            $this->assertEquals(OraBooks_Commissions::STATUS_CANCELLED, $commission->status,
                'Commission status is cancelled', __LINE__);
            $this->assertEquals('Customer refunded', $commission->status_notes,
                'Status notes preserved', __LINE__);

            // Verify cannot qualify a cancelled commission
            $requalify = $this->commissions->qualify_commission($commission_id, 500.00);
            $this->assert(is_wp_error($requalify), 'Cannot qualify a cancelled commission', __LINE__);

            // Verify can forfeit a cancelled commission? No, cancelled is terminal.
            $cancel_status = $this->wpdb->get_var(
                "SELECT status FROM {$comm_table} WHERE id = {$commission_id}"
            );
            $this->assertEquals(OraBooks_Commissions::STATUS_CANCELLED, $cancel_status,
                'Commission stays cancelled (terminal state)', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 20: on_partner_suspended() — cancels pending/qualified commissions
    // ═══════════════════════════════════════════════════════════════════════

    private function test_on_partner_suspended() {
        echo "TEST 20: on_partner_suspended() — cancels pending/qualified commissions for partner org\n";
        echo str_repeat('-', 70) . "\n";

        try {
            global $test_events;
            $test_events = [];

            $org_id = 500;
            $partner_user_id = 300;
            $partner_user_id_2 = 301;

            // Create partner_codes table and insert codes for this org
            $codes_table = $this->wpdb->base_prefix . 'orabooks_partner_codes';
            $this->wpdb->query("CREATE TABLE IF NOT EXISTS {$codes_table} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INT,
                org_id INT,
                partner_code VARCHAR(50),
                status VARCHAR(20)
            )");
            $this->wpdb->insert($codes_table, [
                'user_id' => $partner_user_id, 'org_id' => $org_id,
                'partner_code' => 'SUSPEND-TEST', 'status' => 'active'
            ]);
            $this->wpdb->insert($codes_table, [
                'user_id' => $partner_user_id_2, 'org_id' => $org_id,
                'partner_code' => 'SUSPEND-TEST-2', 'status' => 'active'
            ]);

            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Commission A: pending (should be cancelled)
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 70, 'partner_user_id' => $partner_user_id,
                'partner_org_id' => $org_id, 'customer_user_id' => 8001,
                'partner_code_used' => 'SUSPEND-TEST', 'commission_rate' => 10.0,
                'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $pending_id = $this->wpdb->insert_id;

            // Commission B: qualified (should be cancelled)
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 71, 'partner_user_id' => $partner_user_id,
                'partner_org_id' => $org_id, 'customer_user_id' => 8002,
                'partner_code_used' => 'SUSPEND-TEST', 'commission_rate' => 10.0,
                'commission_amount' => 50.00, 'qualified_amount' => 500.00,
                'fee_amount' => 1.25, 'net_amount' => 48.75,
                'status' => 'qualified', 'qualified_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
            ]);
            $qualified_id = $this->wpdb->insert_id;

            // Commission C: already paid (should NOT be touched — terminal)
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 72, 'partner_user_id' => $partner_user_id,
                'partner_org_id' => $org_id, 'customer_user_id' => 8003,
                'partner_code_used' => 'SUSPEND-TEST', 'commission_rate' => 10.0,
                'commission_amount' => 30.00, 'paid_amount' => 30.00,
                'status' => 'paid', 'paid_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
            ]);
            $paid_id = $this->wpdb->insert_id;

            // Commission D: pending for SECOND partner user (should also be cancelled)
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 73, 'partner_user_id' => $partner_user_id_2,
                'partner_org_id' => $org_id, 'customer_user_id' => 8004,
                'partner_code_used' => 'SUSPEND-TEST-2', 'commission_rate' => 10.0,
                'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $pending_id_2 = $this->wpdb->insert_id;

            // Call on_partner_suspended
            $this->commissions->on_partner_suspended($org_id);

            // Verify pending → cancelled
            $pending = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$pending_id}");
            $this->assertEquals('cancelled', $pending->status, 'Pending commission cancelled', __LINE__);
            $this->assert(strpos($pending->status_notes ?? '', 'suspended') !== false,
                'Status notes mention suspension', __LINE__);

            // Verify qualified → cancelled
            $qualified = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$qualified_id}");
            $this->assertEquals('cancelled', $qualified->status, 'Qualified commission cancelled', __LINE__);
            $this->assert(strpos($qualified->status_notes ?? '', 'suspended') !== false,
                'Qualified status notes mention suspension', __LINE__);

            // Verify paid unchanged (terminal state)
            $paid = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$paid_id}");
            $this->assertEquals('paid', $paid->status, 'Paid commission unchanged (terminal)', __LINE__);

            // Verify second partner's pending commission also cancelled
            $pending2 = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$pending_id_2}");
            $this->assertEquals('cancelled', $pending2->status, 'Second partner pending commission cancelled', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 21: on_partner_suspended() — no partner codes (empty org)
    // ═══════════════════════════════════════════════════════════════════════

    private function test_on_partner_suspended_no_codes() {
        echo "TEST 21: on_partner_suspended() — no partner codes found (empty org)\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';
            $existing_commission_count = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$comm_table}");

            // Call with org_id that has no partner codes
            $this->commissions->on_partner_suspended(99999);

            // Verify no commissions were modified
            $new_count = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$comm_table}");
            $this->assertEquals($existing_commission_count, $new_count,
                'No commissions were modified when org has no codes', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 22: on_partner_fraud_freeze() — forfeits all pending/qualified
    // ═══════════════════════════════════════════════════════════════════════

    private function test_on_partner_fraud_freeze() {
        echo "TEST 22: on_partner_fraud_freeze() — forfeits pending/qualified commissions\n";
        echo str_repeat('-', 70) . "\n";

        try {
            global $test_events;
            $test_events = [];

            $org_id = 501;
            $partner_user_id = 310;

            // Create partner codes for fraud org
            $codes_table = $this->wpdb->base_prefix . 'orabooks_partner_codes';
            $this->wpdb->insert($codes_table, [
                'user_id' => $partner_user_id, 'org_id' => $org_id,
                'partner_code' => 'FRAUD-TEST', 'status' => 'active'
            ]);

            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Commission A: pending (should be forfeited)
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 80, 'partner_user_id' => $partner_user_id,
                'partner_org_id' => $org_id, 'customer_user_id' => 9001,
                'partner_code_used' => 'FRAUD-TEST', 'commission_rate' => 10.0,
                'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $pending_id = $this->wpdb->insert_id;

            // Commission B: qualified (should be forfeited)
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 81, 'partner_user_id' => $partner_user_id,
                'partner_org_id' => $org_id, 'customer_user_id' => 9002,
                'partner_code_used' => 'FRAUD-TEST', 'commission_rate' => 10.0,
                'commission_amount' => 75.00, 'qualified_amount' => 750.00,
                'fee_amount' => 1.88, 'net_amount' => 73.12,
                'status' => 'qualified', 'qualified_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
            ]);
            $qualified_id = $this->wpdb->insert_id;

            // Commission C: already cancelled (terminal — should NOT be touched)
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 82, 'partner_user_id' => $partner_user_id,
                'partner_org_id' => $org_id, 'customer_user_id' => 9003,
                'partner_code_used' => 'FRAUD-TEST', 'commission_rate' => 10.0,
                'status' => 'cancelled', 'status_notes' => 'Cancelled earlier',
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
            ]);
            $cancelled_id = $this->wpdb->insert_id;

            // Call on_partner_fraud_freeze
            $this->commissions->on_partner_fraud_freeze($org_id);

            // Verify pending → forfeited
            $pending = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$pending_id}");
            $this->assertEquals('forfeited', $pending->status, 'Pending commission forfeited', __LINE__);
            $this->assert(strpos($pending->status_notes ?? '', 'fraud-frozen') !== false ||
                          strpos($pending->status_notes ?? '', 'fraud') !== false,
                'Status notes mention fraud freeze', __LINE__);

            // Verify qualified → forfeited
            $qualified = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$qualified_id}");
            $this->assertEquals('forfeited', $qualified->status, 'Qualified commission forfeited', __LINE__);

            // Verify cancelled unchanged (terminal state)
            $cancelled = $this->wpdb->get_row("SELECT * FROM {$comm_table} WHERE id = {$cancelled_id}");
            $this->assertEquals('cancelled', $cancelled->status, 'Already cancelled commission unchanged', __LINE__);
            $this->assertEquals('Cancelled earlier', $cancelled->status_notes,
                'Cancelled status_notes preserved', __LINE__);

            // Verify audit event was fired (from forfeit_partner_commissions called inside)
            $has_event = false;
            foreach ($test_events as $ev) {
                if ($ev['tag'] === 'orabooks_security_event' && isset($ev['args'][0]) && $ev['args'][0] === 'commissions_forfeited') {
                    $has_event = true;
                    $this->assertEquals($partner_user_id, $ev['args'][1]['partner_user_id'] ?? null,
                        'Audit event has correct partner_user_id', __LINE__);
                    $this->assert(($ev['args'][1]['count'] ?? 0) >= 2,
                        'Audit event count >= 2 for two forfeited commissions', __LINE__);
                    break;
                }
            }
            $this->assert($has_event, 'Audit event commissions_forfeited fired', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 23: on_partner_fraud_freeze() — no partner codes (empty org)
    // ═══════════════════════════════════════════════════════════════════════

    private function test_on_partner_fraud_freeze_no_codes() {
        echo "TEST 23: on_partner_fraud_freeze() — no partner codes (empty org)\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Check count before
            $count_before = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$comm_table}");

            // Call with org_id that has no partner codes
            $this->commissions->on_partner_fraud_freeze(88888);

            // Verify no commissions were modified
            $count_after = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$comm_table}");
            $this->assertEquals($count_before, $count_after,
                'No commissions modified for empty org', __LINE__);

            // Also check that no forfeited commissions were added
            $forfeited_count = (int)$this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$comm_table} WHERE partner_user_id = 999999 AND status = 'forfeited'"
            );
            $this->assertEquals(0, $forfeited_count,
                'No forfeited commissions for non-existent partner', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
        // ═══════════════════════════════════════════════════════════════════════
    // TEST 24: get_payout_summary() — no qualified commissions
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_payout_summary_empty() {
        echo "TEST 24: get_payout_summary() — no qualified commissions returns empty\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999001;

            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 90, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 20001,
                'partner_code_used' => 'EMPTY-TEST', 'commission_rate' => 10.0,
                'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'),
            ]);

            $result = $this->commissions->get_payout_summary($partner_id);

            $this->assert(is_array($result), 'get_payout_summary() returns an array', __LINE__);
            $this->assert(empty($result['items']), 'Items is empty when no qualified commissions', __LINE__);
            $this->assertEquals(0, $result['count'], 'count = 0', __LINE__);
            $this->assertEquals(0, $result['total_gross'], 'total_gross = 0', __LINE__);
            $this->assertEquals(0, $result['total_fee'], 'total_fee = 0', __LINE__);
            $this->assertEquals(0, $result['total_net'], 'total_net = 0', __LINE__);
            $this->assert($result['meets_threshold'] === false, 'meets_threshold = false', __LINE__);
            $this->assert($result['min_threshold'] > 0, 'min_threshold is positive', __LINE__);

            $result2 = $this->commissions->get_payout_summary(999999);
            $this->assert(is_array($result2), 'Empty result for non-existent partner', __LINE__);
            $this->assertEquals(0, $result2['count'], 'count = 0 for non-existent partner', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 25: get_payout_summary() — single commission below threshold
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_payout_summary_below_threshold() {
        echo "TEST 25: get_payout_summary() — single commission below $25 threshold\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999002;

            $this->wpdb->insert('wp_test_users', [
                'ID' => 20002, 'user_email' => 'customer@example.com',
                'display_name' => 'Test Customer'
            ]);

            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 91, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 20002,
                'partner_code_used' => 'LOW-TEST', 'commission_rate' => 10.0,
                'commission_amount' => 10.00, 'qualified_amount' => 100.00,
                'fee_amount' => 0.25, 'net_amount' => 9.75,
                'status' => 'qualified',
                'qualified_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
            ]);

            $result = $this->commissions->get_payout_summary($partner_id);

            $this->assertEquals(1, $result['count'], 'count = 1', __LINE__);
            $this->assertEquals(10.00, $result['total_gross'], 'total_gross = 10.00', __LINE__);
            $this->assertEquals(0.25, $result['total_fee'], 'total_fee = 0.25', __LINE__);
            $this->assertEquals(9.75, $result['total_net'], 'total_net = 9.75', __LINE__);
            $this->assert($result['meets_threshold'] === false,
                'meets_threshold = false (9.75 < 25)', __LINE__);
            $this->assertEquals(25.00, $result['min_threshold'],
                'min_threshold = 25.00 (default)', __LINE__);

            $this->assert(count($result['items']) === 1, 'Exactly 1 item', __LINE__);
            $item = $result['items'][0];
            $this->assertEquals('Test Customer', $item->customer_name ?? '',
                'Item includes customer_name from users join', __LINE__);
            $this->assertEquals(10.00, (float)$item->commission_amount,
                'Item commission_amount = 10.00', __LINE__);
            $this->assertEquals(0.25, (float)$item->fee_amount,
                'Item fee_amount = 0.25', __LINE__);
            $this->assertEquals(9.75, (float)$item->net_amount,
                'Item net_amount = 9.75', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 26: get_payout_summary() — multiple commissions meet threshold
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_payout_summary_meets_threshold() {
        echo "TEST 26: get_payout_summary() — multiple commissions meet $25 threshold\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999003;
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            $commissions_data = [
                ['gross' => 10.00, 'fee' => 0.25, 'net' => 9.75],
                ['gross' => 12.00, 'fee' => 0.30, 'net' => 11.70],
                ['gross' => 8.00, 'fee' => 0.20, 'net' => 7.80],
            ];
            $expected_gross = 30.00;
            $expected_fee = 0.75;
            $expected_net = 29.25;

            foreach ($commissions_data as $i => $d) {
                $this->wpdb->insert($comm_table, [
                    'attribution_id' => 100 + $i, 'partner_user_id' => $partner_id,
                    'partner_org_id' => 1, 'customer_user_id' => 30000 + $i,
                    'partner_code_used' => 'MULTI-' . $i, 'commission_rate' => 10.0,
                    'commission_amount' => $d['gross'], 'qualified_amount' => $d['gross'] * 10,
                    'fee_amount' => $d['fee'], 'net_amount' => $d['net'],
                    'status' => 'qualified',
                    'qualified_at' => date('Y-m-d H:i:s', strtotime(-30 + $i . ' days')),
                    'created_at' => date('Y-m-d H:i:s', strtotime(-60 + $i . ' days')),
                ]);
            }

            $result = $this->commissions->get_payout_summary($partner_id);

            $this->assertEquals(3, $result['count'], 'count = 3', __LINE__);
            $this->assertEquals($expected_gross, $result['total_gross'],
                "total_gross = {$expected_gross}", __LINE__);
            $this->assertEquals($expected_fee, $result['total_fee'],
                "total_fee = {$expected_fee}", __LINE__);
            $this->assertEquals($expected_net, $result['total_net'],
                "total_net = {$expected_net}", __LINE__);
            $this->assert($result['meets_threshold'] === true,
                'meets_threshold = true (29.25 >= 25)', __LINE__);
            $this->assertEquals(3, count($result['items']),
                'Exactly 3 items returned', __LINE__);

            $this->assert(
                strtotime($result['items'][0]->qualified_at) <= strtotime($result['items'][1]->qualified_at),
                'Items ordered by qualified_at ASC', __LINE__
            );

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 27: get_payout_summary() — threshold boundary ($25.00 exactly)
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_payout_summary_threshold_boundary() {
        echo "TEST 27: get_payout_summary() — exactly $25.00 meets threshold (boundary)\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999004;
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            $this->wpdb->insert($comm_table, [
                'attribution_id' => 110, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 40001,
                'partner_code_used' => 'BOUNDARY-1', 'commission_rate' => 10.0,
                'commission_amount' => 25.64, 'qualified_amount' => 256.40,
                'fee_amount' => 0.64, 'net_amount' => 25.00,
                'status' => 'qualified', 'created_at' => date('Y-m-d H:i:s'),
                'qualified_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
            ]);

            $result = $this->commissions->get_payout_summary($partner_id);

            $this->assertEquals(1, $result['count'], 'count = 1', __LINE__);
            $this->assertEquals(25.00, $result['total_net'],
                'total_net = 25.00 exactly (boundary)', __LINE__);
            $this->assert($result['meets_threshold'] === true,
                'meets_threshold = true (25.00 >= 25.00)', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 28: get_payout_summary() — only qualified commissions returned
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_payout_summary_filters_status() {
        echo "TEST 28: get_payout_summary() — only qualified commissions (not pending/paid/etc.)\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999005;
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            $this->wpdb->insert($comm_table, [
                'attribution_id' => 120, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 50001,
                'partner_code_used' => 'STATUS-PEND', 'commission_rate' => 10.0,
                'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->wpdb->insert($comm_table, [
                'attribution_id' => 121, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 50002,
                'partner_code_used' => 'STATUS-QUAL', 'commission_rate' => 10.0,
                'commission_amount' => 50.00, 'qualified_amount' => 500.00,
                'fee_amount' => 1.25, 'net_amount' => 48.75,
                'status' => 'qualified', 'qualified_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
            ]);
            $qualified_id = $this->wpdb->insert_id;

            $this->wpdb->insert($comm_table, [
                'attribution_id' => 122, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 50003,
                'partner_code_used' => 'STATUS-PAID', 'commission_rate' => 10.0,
                'commission_amount' => 30.00, 'paid_amount' => 30.00,
                'status' => 'paid', 'paid_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
            ]);

            $this->wpdb->insert($comm_table, [
                'attribution_id' => 123, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 50004,
                'partner_code_used' => 'STATUS-CANCEL', 'commission_rate' => 10.0,
                'status' => 'cancelled', 'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->wpdb->insert($comm_table, [
                'attribution_id' => 124, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 50005,
                'partner_code_used' => 'STATUS-FORF', 'commission_rate' => 10.0,
                'status' => 'forfeited', 'created_at' => date('Y-m-d H:i:s'),
            ]);

            $result = $this->commissions->get_payout_summary($partner_id);

            $this->assertEquals(1, $result['count'],
                'count = 1 (only qualified commission)', __LINE__);
            $this->assertEquals(50.00, $result['total_gross'],
                'total_gross = 50.00 (only qualified)', __LINE__);
            $this->assertEquals(48.75, $result['total_net'],
                'total_net = 48.75', __LINE__);

            $this->assertEquals(1, count($result['items']),
                'Exactly 1 item returned', __LINE__);
            $this->assertEquals($qualified_id, $result['items'][0]->id,
                'Returned item is the qualified commission', __LINE__);
            $this->assertEquals('qualified', $result['items'][0]->status,
                'Item status = qualified', __LINE__);

            $this->assert($result['meets_threshold'] === true,
                'meets_threshold = true (48.75 >= 25)', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }


    // ═══════════════════════════════════════════════════════════════════════
    // TEST 29: get_commission_summary() — empty (no commissions)
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_commission_summary_empty() {
        echo "TEST 29: get_commission_summary() — no commissions → all zeros\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999101;

            $result = $this->commissions->get_commission_summary($partner_id);

            $this->assertEquals(0, $result['total_pending'], 'total_pending = 0', __LINE__);
            $this->assertEquals(0, $result['total_qualified_gross'], 'total_qualified_gross = 0', __LINE__);
            $this->assertEquals(0, $result['total_qualified_net'], 'total_qualified_net = 0', __LINE__);
            $this->assertEquals(0, $result['total_qualified_fee'], 'total_qualified_fee = 0', __LINE__);
            $this->assertEquals(0, $result['total_paid'], 'total_paid = 0', __LINE__);
            $this->assertEquals(0, $result['count_pending'], 'count_pending = 0', __LINE__);
            $this->assertEquals(0, $result['count_qualified'], 'count_qualified = 0', __LINE__);
            $this->assertEquals(0, $result['count_paid'], 'count_paid = 0', __LINE__);

            // Verify the structure has all expected keys
            $expected_keys = ['total_pending', 'total_qualified_gross', 'total_qualified_net',
                'total_qualified_fee', 'total_paid', 'count_pending', 'count_qualified', 'count_paid'];
            foreach ($expected_keys as $key) {
                $this->assert(array_key_exists($key, $result),
                    "Result has key '{$key}'", __LINE__);
            }

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 30: get_commission_summary() — only pending commissions
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_commission_summary_only_pending() {
        echo "TEST 30: get_commission_summary() — only pending commissions\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999102;
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Create 2 pending commissions
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 130, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 61001,
                'partner_code_used' => 'PEND-1', 'commission_rate' => 10.0,
                'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->wpdb->insert($comm_table, [
                'attribution_id' => 131, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 61002,
                'partner_code_used' => 'PEND-2', 'commission_rate' => 10.0,
                'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'),
            ]);

            $result = $this->commissions->get_commission_summary($partner_id);

            $this->assertEquals(2, $result['count_pending'], 'count_pending = 2', __LINE__);
            $this->assertEquals(0, $result['total_pending'], 'total_pending = 0 (pending has no amount)', __LINE__);
            $this->assertEquals(0, $result['count_qualified'], 'count_qualified = 0', __LINE__);
            $this->assertEquals(0, $result['count_paid'], 'count_paid = 0', __LINE__);
            $this->assertEquals(0, $result['total_qualified_gross'], 'total_qualified_gross = 0', __LINE__);
            $this->assertEquals(0, $result['total_qualified_net'], 'total_qualified_net = 0', __LINE__);
            $this->assertEquals(0, $result['total_qualified_fee'], 'total_qualified_fee = 0', __LINE__);
            $this->assertEquals(0, $result['total_paid'], 'total_paid = 0', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 31: get_commission_summary() — only qualified commissions
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_commission_summary_only_qualified() {
        echo "TEST 31: get_commission_summary() — only qualified commissions\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999103;
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Commission A: gross=100.00, fee=2.50, net=97.50
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 132, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 62001,
                'partner_code_used' => 'QUAL-1', 'commission_rate' => 10.0,
                'commission_amount' => 100.00, 'qualified_amount' => 1000.00,
                'fee_amount' => 2.50, 'net_amount' => 97.50,
                'status' => 'qualified', 'qualified_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
            ]);

            // Commission B: gross=50.00, fee=1.25, net=48.75
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 133, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 62002,
                'partner_code_used' => 'QUAL-2', 'commission_rate' => 10.0,
                'commission_amount' => 50.00, 'qualified_amount' => 500.00,
                'fee_amount' => 1.25, 'net_amount' => 48.75,
                'status' => 'qualified', 'qualified_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-50 days')),
            ]);

            // Commission C: gross=200.00, fee=5.00, net=195.00
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 134, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 62003,
                'partner_code_used' => 'QUAL-3', 'commission_rate' => 10.0,
                'commission_amount' => 200.00, 'qualified_amount' => 2000.00,
                'fee_amount' => 5.00, 'net_amount' => 195.00,
                'status' => 'qualified', 'qualified_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-40 days')),
            ]);

            $result = $this->commissions->get_commission_summary($partner_id);

            $this->assertEquals(3, $result['count_qualified'], 'count_qualified = 3', __LINE__);
            $this->assertEquals(350.00, $result['total_qualified_gross'],
                'total_qualified_gross = 350.00 (100+50+200)', __LINE__);
            $this->assertEquals(8.75, $result['total_qualified_fee'],
                'total_qualified_fee = 8.75 (2.50+1.25+5.00)', __LINE__);
            $this->assertEquals(341.25, $result['total_qualified_net'],
                'total_qualified_net = 341.25 (97.50+48.75+195.00)', __LINE__);

            // Verify net = gross - fee
            $this->assertEquals(
                round($result['total_qualified_gross'] - $result['total_qualified_fee'], 2),
                $result['total_qualified_net'],
                'qualified net = qualified gross - qualified fee', __LINE__
            );

            $this->assertEquals(0, $result['count_pending'], 'count_pending = 0', __LINE__);
            $this->assertEquals(0, $result['count_paid'], 'count_paid = 0', __LINE__);
            $this->assertEquals(0, $result['total_paid'], 'total_paid = 0', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 32: get_commission_summary() — only paid commissions
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_commission_summary_only_paid() {
        echo "TEST 32: get_commission_summary() — only paid commissions\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999104;
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Commission A: paid 97.50
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 135, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 63001,
                'partner_code_used' => 'PAID-1', 'commission_rate' => 10.0,
                'commission_amount' => 100.00, 'paid_amount' => 97.50,
                'status' => 'paid', 'paid_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
            ]);

            // Commission B: paid 48.75
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 136, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 63002,
                'partner_code_used' => 'PAID-2', 'commission_rate' => 10.0,
                'commission_amount' => 50.00, 'paid_amount' => 48.75,
                'status' => 'paid', 'paid_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
            ]);

            $result = $this->commissions->get_commission_summary($partner_id);

            $this->assertEquals(2, $result['count_paid'], 'count_paid = 2', __LINE__);
            $this->assertEquals(146.25, $result['total_paid'],
                'total_paid = 146.25 (97.50 + 48.75)', __LINE__);

            $this->assertEquals(0, $result['count_pending'], 'count_pending = 0', __LINE__);
            $this->assertEquals(0, $result['count_qualified'], 'count_qualified = 0', __LINE__);
            $this->assertEquals(0, $result['total_qualified_gross'], 'total_qualified_gross = 0', __LINE__);
            $this->assertEquals(0, $result['total_qualified_net'], 'total_qualified_net = 0', __LINE__);
            $this->assertEquals(0, $result['total_qualified_fee'], 'total_qualified_fee = 0', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 33: get_commission_summary() — mixed states (pending + qualified + paid)
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_commission_summary_mixed_states() {
        echo "TEST 33: get_commission_summary() — mixed pending + qualified + paid\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999105;
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // 3 pending commissions
            for ($i = 0; $i < 3; $i++) {
                $this->wpdb->insert($comm_table, [
                    'attribution_id' => 140 + $i, 'partner_user_id' => $partner_id,
                    'partner_org_id' => 1, 'customer_user_id' => 64000 + $i,
                    'partner_code_used' => 'MIXED-P' . $i, 'commission_rate' => 10.0,
                    'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // 2 qualified commissions: total gross=150.00, fee=3.75, net=146.25
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 143, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 64003,
                'partner_code_used' => 'MIXED-Q1', 'commission_rate' => 10.0,
                'commission_amount' => 100.00, 'qualified_amount' => 1000.00,
                'fee_amount' => 2.50, 'net_amount' => 97.50,
                'status' => 'qualified', 'qualified_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
            ]);
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 144, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 64004,
                'partner_code_used' => 'MIXED-Q2', 'commission_rate' => 10.0,
                'commission_amount' => 50.00, 'qualified_amount' => 500.00,
                'fee_amount' => 1.25, 'net_amount' => 48.75,
                'status' => 'qualified', 'qualified_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-50 days')),
            ]);

            // 1 paid commission: total_paid=73.12
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 145, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 64005,
                'partner_code_used' => 'MIXED-PAID', 'commission_rate' => 10.0,
                'commission_amount' => 75.00, 'paid_amount' => 73.12,
                'status' => 'paid', 'paid_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
            ]);

            $result = $this->commissions->get_commission_summary($partner_id);

            $this->assertEquals(3, $result['count_pending'], 'count_pending = 3', __LINE__);
            $this->assertEquals(2, $result['count_qualified'], 'count_qualified = 2', __LINE__);
            $this->assertEquals(150.00, $result['total_qualified_gross'],
                'total_qualified_gross = 150.00 (100+50)', __LINE__);
            $this->assertEquals(3.75, $result['total_qualified_fee'],
                'total_qualified_fee = 3.75 (2.50+1.25)', __LINE__);
            $this->assertEquals(146.25, $result['total_qualified_net'],
                'total_qualified_net = 146.25 (97.50+48.75)', __LINE__);
            $this->assertEquals(1, $result['count_paid'], 'count_paid = 1', __LINE__);
            $this->assertEquals(73.12, $result['total_paid'],
                'total_paid = 73.12', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 34: get_commission_summary() — cancelled/forfeited not counted
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_commission_summary_cancelled_forfeited_ignored() {
        echo "TEST 34: get_commission_summary() — cancelled/forfeited commissions not counted\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999106;
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // 1 pending
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 150, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 65000,
                'partner_code_used' => 'ALL-STATES', 'commission_rate' => 10.0,
                'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'),
            ]);

            // 1 qualified
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 151, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 65001,
                'partner_code_used' => 'ALL-STATES', 'commission_rate' => 10.0,
                'commission_amount' => 100.00, 'qualified_amount' => 1000.00,
                'fee_amount' => 2.50, 'net_amount' => 97.50,
                'status' => 'qualified', 'qualified_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
            ]);

            // 1 paid
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 152, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 65002,
                'partner_code_used' => 'ALL-STATES', 'commission_rate' => 10.0,
                'commission_amount' => 50.00, 'paid_amount' => 48.75,
                'status' => 'paid', 'paid_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
            ]);

            // 1 cancelled
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 153, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 65003,
                'partner_code_used' => 'ALL-STATES', 'commission_rate' => 10.0,
                'status' => 'cancelled', 'created_at' => date('Y-m-d H:i:s'),
            ]);

            // 1 forfeited
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 154, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 65004,
                'partner_code_used' => 'ALL-STATES', 'commission_rate' => 10.0,
                'status' => 'forfeited', 'created_at' => date('Y-m-d H:i:s'),
            ]);

            // 5 total: pending+qualified+paid+cancelled+forfeited
            // Summary should only count pending(1)+qualified(1)+paid(1)=3
            $result = $this->commissions->get_commission_summary($partner_id);

            $total_counted = $result['count_pending'] + $result['count_qualified'] + $result['count_paid'];
            $this->assertEquals(3, $total_counted,
                "Total counted = 3 (5 total, 2 ignored)", __LINE__);

            $this->assertEquals(1, $result['count_pending'], 'count_pending = 1', __LINE__);
            $this->assertEquals(1, $result['count_qualified'], 'count_qualified = 1', __LINE__);
            $this->assertEquals(1, $result['count_paid'], 'count_paid = 1', __LINE__);
            $this->assertEquals(100.00, $result['total_qualified_gross'],
                'total_qualified_gross = 100.00', __LINE__);
            $this->assertEquals(48.75, $result['total_paid'],
                'total_paid = 48.75', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 35: get_commission_summary() — partner data isolation
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_commission_summary_partner_isolation() {
        echo "TEST 35: get_commission_summary() — data isolation between partners\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_a = 999107;
            $partner_b = 999108;
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Partner A: 2 pending
            for ($i = 0; $i < 2; $i++) {
                $this->wpdb->insert($comm_table, [
                    'attribution_id' => 160 + $i, 'partner_user_id' => $partner_a,
                    'partner_org_id' => 1, 'customer_user_id' => 66000 + $i,
                    'partner_code_used' => 'ISOLATE-A', 'commission_rate' => 10.0,
                    'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Partner B: 1 qualified + 1 paid
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 162, 'partner_user_id' => $partner_b,
                'partner_org_id' => 1, 'customer_user_id' => 66002,
                'partner_code_used' => 'ISOLATE-B', 'commission_rate' => 10.0,
                'commission_amount' => 200.00, 'qualified_amount' => 2000.00,
                'fee_amount' => 5.00, 'net_amount' => 195.00,
                'status' => 'qualified', 'qualified_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
            ]);
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 163, 'partner_user_id' => $partner_b,
                'partner_org_id' => 1, 'customer_user_id' => 66003,
                'partner_code_used' => 'ISOLATE-B', 'commission_rate' => 10.0,
                'commission_amount' => 30.00, 'paid_amount' => 29.25,
                'status' => 'paid', 'paid_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
            ]);

            // Partner A summary: only their 2 pending
            $result_a = $this->commissions->get_commission_summary($partner_a);
            $this->assertEquals(2, $result_a['count_pending'], 'Partner A: count_pending = 2', __LINE__);
            $this->assertEquals(0, $result_a['count_qualified'], 'Partner A: count_qualified = 0', __LINE__);
            $this->assertEquals(0, $result_a['count_paid'], 'Partner A: count_paid = 0', __LINE__);

            // Partner B summary: only their 1 qualified + 1 paid
            $result_b = $this->commissions->get_commission_summary($partner_b);
            $this->assertEquals(0, $result_b['count_pending'], 'Partner B: count_pending = 0', __LINE__);
            $this->assertEquals(1, $result_b['count_qualified'], 'Partner B: count_qualified = 1', __LINE__);
            $this->assertEquals(200.00, $result_b['total_qualified_gross'],
                'Partner B: total_qualified_gross = 200.00', __LINE__);
            $this->assertEquals(1, $result_b['count_paid'], 'Partner B: count_paid = 1', __LINE__);
            $this->assertEquals(29.25, $result_b['total_paid'],
                'Partner B: total_paid = 29.25', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 36: get_recent_commissions() — empty partner
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_recent_commissions_empty() {
        echo "TEST 36: get_recent_commissions() — empty partner returns []\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $result = $this->commissions->get_recent_commissions(999201, 5);
            $this->assert(is_array($result), 'Result is an array', __LINE__);
            $this->assert(count($result) === 0, 'Empty array for partner with no commissions', __LINE__);
            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 37: get_recent_commissions() — limit parameter
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_recent_commissions_limit() {
        echo "TEST 37: get_recent_commissions() — limit parameter restricts results\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999202;
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            for ($i = 0; $i < 5; $i++) {
                $this->wpdb->insert($comm_table, [
                    'attribution_id' => 200 + $i, 'partner_user_id' => $partner_id,
                    'partner_org_id' => 1, 'customer_user_id' => 60000 + $i,
                    'partner_code_used' => 'LIMIT-' . ($i + 1), 'commission_rate' => 10.0,
                    'commission_amount' => 10.0 * ($i + 1),
                    'status' => 'qualified',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-' . ($i * 5) . ' days')),
                    'qualified_at' => date('Y-m-d H:i:s', strtotime('-' . ($i * 5) . ' days')),
                ]);
            }

            $result = $this->commissions->get_recent_commissions($partner_id, 2);
            $this->assert(count($result) === 2, 'limit=2 returns 2 results', __LINE__);

            $result_all = $this->commissions->get_recent_commissions($partner_id, 10);
            $this->assert(count($result_all) === 5, 'limit=10 returns all 5 available', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 38: get_recent_commissions() — default limit (10)
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_recent_commissions_default_limit() {
        echo "TEST 38: get_recent_commissions() — default limit = 10\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999203;
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            for ($i = 0; $i < 12; $i++) {
                $this->wpdb->insert($comm_table, [
                    'attribution_id' => 210 + $i, 'partner_user_id' => $partner_id,
                    'partner_org_id' => 1, 'customer_user_id' => 61000 + $i,
                    'partner_code_used' => 'DEFAULT-' . ($i + 1), 'commission_rate' => 10.0,
                    'commission_amount' => 10.0,
                    'status' => 'qualified',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-' . $i . ' days')),
                    'qualified_at' => date('Y-m-d H:i:s', strtotime('-' . $i . ' days')),
                ]);
            }

            $result = $this->commissions->get_recent_commissions($partner_id);
            $this->assert(count($result) === 10, 'Default limit returns 10 results (got ' . count($result) . ')', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 39: get_recent_commissions() — ordering (DESC by created_at)
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_recent_commissions_ordering() {
        echo "TEST 39: get_recent_commissions() — ordered by created_at DESC (newest first)\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999204;
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Oldest first in insert order
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 220, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 62001,
                'partner_code_used' => 'ORDER-1', 'commission_rate' => 10.0,
                'commission_amount' => 30.00,
                'status' => 'qualified',
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                'qualified_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
            ]);
            $id_old = $this->wpdb->insert_id;

            $this->wpdb->insert($comm_table, [
                'attribution_id' => 221, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 62002,
                'partner_code_used' => 'ORDER-2', 'commission_rate' => 10.0,
                'commission_amount' => 20.00,
                'status' => 'qualified',
                'created_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
                'qualified_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
            ]);
            $id_mid = $this->wpdb->insert_id;

            $this->wpdb->insert($comm_table, [
                'attribution_id' => 222, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 62003,
                'partner_code_used' => 'ORDER-3', 'commission_rate' => 10.0,
                'commission_amount' => 10.00,
                'status' => 'qualified',
                'created_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
                'qualified_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
            ]);
            $id_new = $this->wpdb->insert_id;

            $result = $this->commissions->get_recent_commissions($partner_id, 5);

            $this->assert(count($result) === 3, 'Returns all 3 commissions', __LINE__);
            $this->assertEquals((int)$result[0]->id, $id_new, 'First result is newest', __LINE__);
            $this->assertEquals((int)$result[1]->id, $id_mid, 'Second result is middle', __LINE__);
            $this->assertEquals((int)$result[2]->id, $id_old, 'Third result is oldest', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 40: get_recent_commissions() — customer_name and customer_email join
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_recent_commissions_customer_join() {
        echo "TEST 40: get_recent_commissions() — customer_name and customer_email from LEFT JOIN\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999205;
            $customer_id = 63001;
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            $this->wpdb->insert('wp_test_users', [
                'ID' => $customer_id,
                'user_email' => 'customer@example.com',
                'display_name' => 'Test Customer Name',
            ]);

            $this->wpdb->insert($comm_table, [
                'attribution_id' => 230, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => $customer_id,
                'partner_code_used' => 'JOIN-TEST', 'commission_rate' => 10.0,
                'commission_amount' => 50.00, 'qualified_amount' => 500.00,
                'fee_amount' => 1.25, 'net_amount' => 48.75,
                'status' => 'qualified',
                'created_at' => date('Y-m-d H:i:s'),
                'qualified_at' => date('Y-m-d H:i:s'),
            ]);

            $result = $this->commissions->get_recent_commissions($partner_id, 5);

            $this->assert(count($result) === 1, 'Returns 1 commission', __LINE__);
            $this->assertEquals('Test Customer Name', $result[0]->customer_name ?? '',
                'customer_name from LEFT JOIN', __LINE__);
            $this->assertEquals('customer@example.com', $result[0]->customer_email ?? '',
                'customer_email from LEFT JOIN', __LINE__);

            $this->assert(isset($result[0]->id), 'id field present', __LINE__);
            $this->assert(isset($result[0]->status), 'status field present', __LINE__);
            $this->assert(isset($result[0]->commission_amount), 'commission_amount field present', __LINE__);
            $this->assert(isset($result[0]->created_at), 'created_at field present', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 41: get_recent_commissions() — partner isolation
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_recent_commissions_partner_isolation() {
        echo "TEST 41: get_recent_commissions() — partner isolation (each sees only their own)\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_a = 999206;
            $partner_b = 999207;
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            for ($i = 0; $i < 2; $i++) {
                $this->wpdb->insert($comm_table, [
                    'attribution_id' => 240 + $i, 'partner_user_id' => $partner_a,
                    'partner_org_id' => 1, 'customer_user_id' => 64000 + $i,
                    'partner_code_used' => 'ISO-A' . ($i + 1), 'commission_rate' => 10.0,
                    'commission_amount' => 25.0 * ($i + 1),
                    'status' => 'qualified',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-' . ($i * 5) . ' days')),
                    'qualified_at' => date('Y-m-d H:i:s', strtotime('-' . ($i * 5) . ' days')),
                ]);
            }

            $this->wpdb->insert($comm_table, [
                'attribution_id' => 242, 'partner_user_id' => $partner_b,
                'partner_org_id' => 1, 'customer_user_id' => 64002,
                'partner_code_used' => 'ISO-B1', 'commission_rate' => 10.0,
                'commission_amount' => 100.00,
                'status' => 'qualified',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'qualified_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            ]);

            $result_a = $this->commissions->get_recent_commissions($partner_a, 10);
            $this->assert(count($result_a) === 2, 'Partner A sees 2 commissions', __LINE__);
            foreach ($result_a as $c) {
                $this->assertEquals($partner_a, (int)$c->partner_user_id,
                    'Partner A commission belongs to Partner A', __LINE__);
            }

            $result_b = $this->commissions->get_recent_commissions($partner_b, 10);
            $this->assert(count($result_b) === 1, 'Partner B sees 1 commission', __LINE__);
            $this->assertEquals($partner_b, (int)$result_b[0]->partner_user_id,
                'Partner B commission belongs to Partner B', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 42: get_recent_commissions() — all statuses included (no filter)
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_recent_commissions_all_statuses() {
        echo "TEST 42: get_recent_commissions() — all statuses are returned (no status filter)\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999208;
            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';
            $statuses = ['pending', 'qualified', 'paid', 'cancelled', 'forfeited'];

            foreach ($statuses as $i => $status) {
                $this->wpdb->insert($comm_table, [
                    'attribution_id' => 250 + $i, 'partner_user_id' => $partner_id,
                    'partner_org_id' => 1, 'customer_user_id' => 65000 + $i,
                    'partner_code_used' => 'STATUS-' . strtoupper($status), 'commission_rate' => 10.0,
                    'commission_amount' => 10.0 * ($i + 1),
                    'status' => $status,
                    'created_at' => date('Y-m-d H:i:s', strtotime('-' . $i . ' days')),
                ]);
                if ($status === 'qualified' || $status === 'paid') {
                    $this->wpdb->query("UPDATE {$comm_table} SET qualified_at = created_at WHERE id = " . $this->wpdb->insert_id);
                }
                if ($status === 'paid') {
                    $this->wpdb->query("UPDATE {$comm_table} SET paid_at = created_at WHERE id = " . $this->wpdb->insert_id);
                }
            }

            $result = $this->commissions->get_recent_commissions($partner_id, 10);
            $this->assert(count($result) === 5, 'All 5 statuses returned', __LINE__);

            $returned_statuses = [];
            foreach ($result as $c) {
                $returned_statuses[] = $c->status;
            }
            sort($returned_statuses);
            $expected = $statuses;
            sort($expected);
            $this->assert($returned_statuses === $expected,
                'All 5 statuses present: ' . implode(', ', $returned_statuses), __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }
    // ═══════════════════════════════════════════════════════════════════════
    // TEST 43: get_partner_commission_rate() — default rate (10%)
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_partner_rate_default() {
        echo "TEST 43: get_partner_commission_rate() — default 10% (no org, no partner type)\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $rate = $this->commissions->get_partner_commission_rate(999301, 0);
            $this->assertEquals(10.0, $rate, 'Default rate is 10.0%', __LINE__);

            // Without second arg (org_id defaults to 0)
            $rate2 = $this->commissions->get_partner_commission_rate(999301);
            $this->assertEquals(10.0, $rate2, 'Default rate with no org_id arg is 10.0%', __LINE__);

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 44: get_partner_commission_rate() — org config override
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_partner_rate_org_config() {
        echo "TEST 44: get_partner_commission_rate() — org config overrides default\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $GLOBALS['test_option_overrides']['orabooks_org_config_500'] = ['commission_rate' => 8.0];
            $rate = $this->commissions->get_partner_commission_rate(999302, 500);
            $this->assertEquals(8.0, $rate, 'Org config rate = 8.0% overrides default 10.0%', __LINE__);

            $GLOBALS['test_option_overrides']['orabooks_org_config_500'] = ['commission_rate' => 12.5];
            $rate2 = $this->commissions->get_partner_commission_rate(999302, 500);
            $this->assertEquals(12.5, $rate2, 'Org config rate = 12.5% override', __LINE__);

            unset($GLOBALS['test_option_overrides']['orabooks_org_config_500']);
            $this->pass(__LINE__);
        } catch (Exception $e) {
            unset($GLOBALS['test_option_overrides']['orabooks_org_config_500']);
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 45: get_partner_commission_rate() — org config without rate key
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_partner_rate_no_rate_key() {
        echo "TEST 45: get_partner_commission_rate() — org config without commission_rate key\n";
        echo str_repeat('-', 70) . "\n";

        try {
            // Override but with no commission_rate key
            $GLOBALS['test_option_overrides']['orabooks_org_config_600'] = ['other_setting' => 'value'];
            $rate = $this->commissions->get_partner_commission_rate(999303, 600);
            $this->assertEquals(10.0, $rate, 'No commission_rate key → default 10.0%', __LINE__);

            unset($GLOBALS['test_option_overrides']['orabooks_org_config_600']);
            $this->pass(__LINE__);
        } catch (Exception $e) {
            unset($GLOBALS['test_option_overrides']['orabooks_org_config_600']);
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 46: get_partner_commission_rate() — partner type rates
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_partner_rate_partner_types() {
        echo "TEST 46: get_partner_commission_rate() — partner type rates\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partners = OraBooks_Partners::get_instance();
            $type_rates = [
                'individual'        => 10.0,
                'accountant'        => 12.0,
                'agency'            => 15.0,
                'reseller'          => 20.0,
                'strategic_partner' => 25.0,
            ];

            foreach ($type_rates as $type => $expected_rate) {
                $user_id = 999401 + array_search($type, array_keys($type_rates));
                $partners->set_partner_code($user_id, $type);
                $rate = $this->commissions->get_partner_commission_rate($user_id, 0);
                $this->assertEquals($expected_rate, $rate,
                    "{$type} rate = {$expected_rate}% (got {$rate})", __LINE__);
            }

            $this->pass(__LINE__);
        } catch (Exception $e) {
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 47: get_partner_commission_rate() — partner type overrides org config
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_partner_rate_type_overrides_org() {
        echo "TEST 47: get_partner_commission_rate() — partner type rate overrides org config\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $user_id = 999501;

            // Set org config to 8.0
            $GLOBALS['test_option_overrides']['orabooks_org_config_700'] = ['commission_rate' => 8.0];

            // Set partner type to agency (15.0)
            $partners = OraBooks_Partners::get_instance();
            $partners->set_partner_code($user_id, 'agency');

            $rate = $this->commissions->get_partner_commission_rate($user_id, 700);
            $this->assertEquals(15.0, $rate,
                'Partner type (agency=15.0%) overrides org config (8.0%)', __LINE__);

            unset($GLOBALS['test_option_overrides']['orabooks_org_config_700']);
            $this->pass(__LINE__);
        } catch (Exception $e) {
            unset($GLOBALS['test_option_overrides']['orabooks_org_config_700']);
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 48: get_partner_commission_rate() — unknown partner type
    // ═══════════════════════════════════════════════════════════════════════

    private function test_get_partner_rate_unknown_type() {
        echo "TEST 48: get_partner_commission_rate() — unknown partner type falls through\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $user_id = 999601;

            // Set org config to 8.0
            $GLOBALS['test_option_overrides']['orabooks_org_config_800'] = ['commission_rate' => 8.0];

            // Set unknown partner type (not in the type_rates map)
            $partners = OraBooks_Partners::get_instance();
            $partners->set_partner_code($user_id, 'premium_partner');

            $rate = $this->commissions->get_partner_commission_rate($user_id, 800);
            $this->assertEquals(8.0, $rate,
                'Unknown partner type falls through to org config (8.0%)', __LINE__);

            unset($GLOBALS['test_option_overrides']['orabooks_org_config_800']);
            $this->pass(__LINE__);
        } catch (Exception $e) {
            unset($GLOBALS['test_option_overrides']['orabooks_org_config_800']);
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }
    // ═══════════════════════════════════════════════════════════════════════
    // TEST 49: ajax_commission_summary() — empty partner
    // ═══════════════════════════════════════════════════════════════════════

    private function test_ajax_commission_summary_empty() {
        echo "TEST 49: ajax_commission_summary() — empty partner response structure\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999701;
            $GLOBALS['test_current_user_id'] = $partner_id;
            global $test_ajax_response;
            $test_ajax_response = null;

            $_POST['nonce'] = 'test-nonce';

            try {
                $this->commissions->ajax_commission_summary();
                $this->fail(__LINE__, 'Expected AJAX_RESPONSE_SENT exception');
            } catch (Exception $e) {
                if ($e->getMessage() !== 'AJAX_RESPONSE_SENT') {
                    throw $e;
                }
            }

            $this->assert($test_ajax_response !== null, 'AJAX response was captured', __LINE__);
            $this->assert($test_ajax_response['success'] === true, 'Response is success', __LINE__);

            $data = $test_ajax_response['data'];
            $this->assert(isset($data['summary']), 'summary key present', __LINE__);
            $this->assert(isset($data['rate']), 'rate key present', __LINE__);
            $this->assert(isset($data['payout']), 'payout key present', __LINE__);
            $this->assert(isset($data['config']), 'config key present', __LINE__);
            $this->assert(isset($data['pending_estimated']), 'pending_estimated key present', __LINE__);
            $this->assert(isset($data['customer_count']), 'customer_count key present', __LINE__);

            $this->assertEquals(10.0, $data['rate'], 'Default rate is 10.0%', __LINE__);
            $this->assertEquals(0, $data['pending_estimated'], 'pending_estimated = 0 for empty partner', __LINE__);
            $this->assertEquals(0, $data['customer_count'], 'customer_count = 0 for empty partner', __LINE__);
            $this->assertEquals(25.0, $data['config']['min_payout_threshold'], 'min_payout_threshold = 25.0', __LINE__);
            $this->assertEquals(2.5, $data['config']['payout_fee_rate'], 'payout_fee_rate = 2.5', __LINE__);

            // Summary should be all zeros
            $s = $data['summary'];
            $this->assertEquals(0, $s['total_pending'], 'summary total_pending = 0', __LINE__);
            $this->assertEquals(0, $s['total_qualified_gross'], 'summary total_qualified_gross = 0', __LINE__);
            $this->assertEquals(0, $s['total_qualified_net'], 'summary total_qualified_net = 0', __LINE__);
            $this->assertEquals(0, $s['total_paid'], 'summary total_paid = 0', __LINE__);
            $this->assertEquals(0, $s['count_pending'], 'summary count_pending = 0', __LINE__);
            $this->assertEquals(0, $s['count_qualified'], 'summary count_qualified = 0', __LINE__);
            $this->assertEquals(0, $s['count_paid'], 'summary count_paid = 0', __LINE__);

            // Payout should be empty
            $this->assert(count($data['payout']['items']) === 0, 'payout items empty', __LINE__);
            $this->assertEquals(0, $data['payout']['total_gross'], 'payout total_gross = 0', __LINE__);
            $this->assert($data['payout']['meets_threshold'] === false, 'payout meets_threshold = false', __LINE__);

            unset($GLOBALS['test_current_user_id']);
            $this->pass(__LINE__);
        } catch (Exception $e) {
            unset($GLOBALS['test_current_user_id']);
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 50: ajax_commission_summary() — pending estimate calculation
    // ═══════════════════════════════════════════════════════════════════════

    private function test_ajax_commission_summary_with_pending_estimate() {
        echo "TEST 50: ajax_commission_summary() — pending estimated from qualified average\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999702;
            $GLOBALS['test_current_user_id'] = $partner_id;
            global $test_ajax_response;
            $test_ajax_response = null;

            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Insert 2 pending commissions
            for ($i = 0; $i < 2; $i++) {
                $this->wpdb->insert($comm_table, [
                    'attribution_id' => 300 + $i, 'partner_user_id' => $partner_id,
                    'partner_org_id' => 1, 'customer_user_id' => 70000 + $i,
                    'partner_code_used' => 'AJAX-PEND-' . ($i + 1), 'commission_rate' => 10.0,
                    'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Insert 3 qualified commissions (100+100+100=300 gross)
            for ($i = 0; $i < 3; $i++) {
                $this->wpdb->insert($comm_table, [
                    'attribution_id' => 310 + $i, 'partner_user_id' => $partner_id,
                    'partner_org_id' => 1, 'customer_user_id' => 70100 + $i,
                    'partner_code_used' => 'AJAX-QUAL-' . ($i + 1), 'commission_rate' => 10.0,
                    'commission_amount' => 100.00, 'qualified_amount' => 1000.00,
                    'fee_amount' => 2.50, 'net_amount' => 97.50,
                    'status' => 'qualified',
                    'qualified_at' => date('Y-m-d H:i:s', strtotime('-' . ($i * 10) . ' days')),
                    'created_at' => date('Y-m-d H:i:s', strtotime('-' . ($i * 10 + 30) . ' days')),
                ]);
            }

            $_POST['nonce'] = 'test-nonce';

            try {
                $this->commissions->ajax_commission_summary();
                $this->fail(__LINE__, 'Expected AJAX_RESPONSE_SENT exception');
            } catch (Exception $e) {
                if ($e->getMessage() !== 'AJAX_RESPONSE_SENT') {
                    throw $e;
                }
            }

            $data = $test_ajax_response['data'];

            // Summary: 2 pending, 3 qualified (300 gross)
            $this->assertEquals(2, $data['summary']['count_pending'], 'count_pending = 2', __LINE__);
            $this->assertEquals(3, $data['summary']['count_qualified'], 'count_qualified = 3', __LINE__);
            $this->assertEquals(300.0, $data['summary']['total_qualified_gross'], 'total_qualified_gross = 300', __LINE__);

            // pending_estimated = count_pending * (total_qualified_gross / count_qualified)
            // = 2 * (300 / 3) = 2 * 100 = 200
            $expected_estimate = round(2 * (300.0 / 3), 2);
            $this->assertEquals($expected_estimate, $data['pending_estimated'],
                "pending_estimated = {$expected_estimate}", __LINE__);

            unset($GLOBALS['test_current_user_id']);
            $this->pass(__LINE__);
        } catch (Exception $e) {
            unset($GLOBALS['test_current_user_id']);
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 51: ajax_commission_history() — empty partner
    // ═══════════════════════════════════════════════════════════════════════

    private function test_ajax_commission_history_empty() {
        echo "TEST 51: ajax_commission_history() — empty partner returns empty array\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999703;
            $GLOBALS['test_current_user_id'] = $partner_id;
            global $test_ajax_response;
            $test_ajax_response = null;

            $_POST['nonce'] = 'test-nonce';
            unset($_POST['limit']);

            try {
                $this->commissions->ajax_commission_history();
                $this->fail(__LINE__, 'Expected AJAX_RESPONSE_SENT exception');
            } catch (Exception $e) {
                if ($e->getMessage() !== 'AJAX_RESPONSE_SENT') {
                    throw $e;
                }
            }

            $this->assert($test_ajax_response !== null, 'AJAX response captured', __LINE__);
            $this->assert($test_ajax_response['success'] === true, 'Response is success', __LINE__);
            $this->assert(is_array($test_ajax_response['data']['commissions']), 'commissions is array', __LINE__);
            $this->assert(count($test_ajax_response['data']['commissions']) === 0, 'Empty commissions array', __LINE__);

            unset($GLOBALS['test_current_user_id']);
            $this->pass(__LINE__);
        } catch (Exception $e) {
            unset($GLOBALS['test_current_user_id']);
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 52: ajax_commission_history() — formatted response with data
    // ═══════════════════════════════════════════════════════════════════════

    private function test_ajax_commission_history_with_data() {
        echo "TEST 52: ajax_commission_history() — formatted commissions with status labels\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999704;
            $GLOBALS['test_current_user_id'] = $partner_id;
            global $test_ajax_response;
            $test_ajax_response = null;

            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Insert a user for customer_name join
            $this->wpdb->insert('wp_test_users', [
                'ID' => 71001, 'user_email' => 'customer@test.com',
                'display_name' => 'Test Customer',
            ]);

            // Commission 1: qualified (with customer)
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 320, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 71001,
                'partner_code_used' => 'HIST-QUAL', 'commission_rate' => 10.0,
                'commission_amount' => 75.00, 'qualified_amount' => 750.00,
                'fee_amount' => 1.88, 'net_amount' => 73.12,
                'status' => 'qualified',
                'qualified_at' => date('Y-m-d H:i:s', strtotime('-15 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
            ]);
            $qual_id = $this->wpdb->insert_id;

            // Commission 2: paid (without customer in users table)
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 321, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 99999,
                'partner_code_used' => 'HIST-PAID', 'commission_rate' => 10.0,
                'commission_amount' => 50.00, 'paid_amount' => 48.75,
                'fee_amount' => 1.25, 'net_amount' => 48.75,
                'status' => 'paid',
                'paid_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
                'qualified_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
            ]);
            $paid_id = $this->wpdb->insert_id;

            // Commission 3: cancelled
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 322, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 71003,
                'partner_code_used' => 'HIST-CANCEL', 'commission_rate' => 10.0,
                'commission_amount' => 25.00,
                'status' => 'cancelled', 'status_notes' => 'Test cancellation',
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
            ]);

            $_POST['nonce'] = 'test-nonce';

            try {
                $this->commissions->ajax_commission_history();
                $this->fail(__LINE__, 'Expected AJAX_RESPONSE_SENT exception');
            } catch (Exception $e) {
                if ($e->getMessage() !== 'AJAX_RESPONSE_SENT') {
                    throw $e;
                }
            }

            $commissions = $test_ajax_response['data']['commissions'];

            $this->assertEquals(3, count($commissions), '3 commissions returned', __LINE__);

            // Check qualified commission
            $qual = $commissions[0]; // newest first (ordered by created_at DESC)
            $this->assertEquals('qualified', $qual['status'], 'Status = qualified', __LINE__);
            $this->assertEquals('Qualified', $qual['status_label'], 'Status label = Qualified', __LINE__);
            $this->assertEquals('Test Customer', $qual['customer_name'], 'customer_name from join', __LINE__);
            $this->assertEquals('customer@test.com', $qual['customer_email'], 'customer_email from join', __LINE__);
            $this->assertEquals(75.0, $qual['commission_amount'], 'commission_amount = 75.0', __LINE__);
            $this->assertEquals(1.88, $qual['fee_amount'], 'fee_amount = 1.88', __LINE__);
            $this->assertEquals(73.12, $qual['net_amount'], 'net_amount = 73.12', __LINE__);

            // Check paid commission
            $paid = $commissions[1];
            $this->assertEquals('paid', $paid['status'], 'Status = paid', __LINE__);
            $this->assertEquals('Paid', $paid['status_label'], 'Status label = Paid', __LINE__);
            $this->assertEquals(48.75, $paid['paid_amount'], 'paid_amount = 48.75', __LINE__);

            // Check cancelled commission — unknown customer
            $cancelled = $commissions[2];
            $this->assertEquals('cancelled', $cancelled['status'], 'Status = cancelled', __LINE__);
            $this->assertEquals('Cancelled', $cancelled['status_label'], 'Status label = Cancelled', __LINE__);
            $this->assertEquals('(unknown)', $cancelled['customer_name'],
                'Unknown customer gets (unknown) fallback', __LINE__);
            $this->assert($cancelled['paid_amount'] === null,
                'Cancelled commission has null paid_amount', __LINE__);

            unset($GLOBALS['test_current_user_id']);
            $this->pass(__LINE__);
        } catch (Exception $e) {
            unset($GLOBALS['test_current_user_id']);
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 53: ajax_commission_history() — custom limit
    // ═══════════════════════════════════════════════════════════════════════

    private function test_ajax_commission_history_custom_limit() {
        echo "TEST 53: ajax_commission_history() — custom limit parameter\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999705;
            $GLOBALS['test_current_user_id'] = $partner_id;
            global $test_ajax_response;
            $test_ajax_response = null;

            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Insert 5 commissions with different created_at
            for ($i = 0; $i < 5; $i++) {
                $this->wpdb->insert($comm_table, [
                    'attribution_id' => 330 + $i, 'partner_user_id' => $partner_id,
                    'partner_org_id' => 1, 'customer_user_id' => 72000 + $i,
                    'partner_code_used' => 'LIMIT-HIST-' . ($i + 1), 'commission_rate' => 10.0,
                    'commission_amount' => 10.0 * ($i + 1),
                    'status' => ($i % 2 === 0) ? 'qualified' : 'pending',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-' . $i . ' days')),
                ]);
                if ($i % 2 === 0) {
                    $this->wpdb->query("UPDATE {$comm_table} SET qualified_at = created_at WHERE id = " . $this->wpdb->insert_id);
                }
            }

            // Default limit (10) — returns all 5
            $_POST['nonce'] = 'test-nonce';
            unset($_POST['limit']);

            try {
                $this->commissions->ajax_commission_history();
                $this->fail(__LINE__, 'Expected AJAX_RESPONSE_SENT exception');
            } catch (Exception $e) {
                if ($e->getMessage() !== 'AJAX_RESPONSE_SENT') { throw $e; }
            }

            $this->assertEquals(5, count($test_ajax_response['data']['commissions']),
                'Default limit returns all 5', __LINE__);

            // Custom limit = 2
            $test_ajax_response = null;
            $_POST['limit'] = 2;

            try {
                $this->commissions->ajax_commission_history();
                $this->fail(__LINE__, 'Expected AJAX_RESPONSE_SENT exception');
            } catch (Exception $e) {
                if ($e->getMessage() !== 'AJAX_RESPONSE_SENT') { throw $e; }
            }

            $this->assertEquals(2, count($test_ajax_response['data']['commissions']),
                'limit=2 returns 2 commissions', __LINE__);

            // Custom limit capped at 50
            $test_ajax_response = null;
            $_POST['limit'] = 100;

            try {
                $this->commissions->ajax_commission_history();
                $this->fail(__LINE__, 'Expected AJAX_RESPONSE_SENT exception');
            } catch (Exception $e) {
                if ($e->getMessage() !== 'AJAX_RESPONSE_SENT') { throw $e; }
            }

            $this->assertEquals(5, count($test_ajax_response['data']['commissions']),
                'limit=100 capped at 50, returns all 5', __LINE__);

            unset($GLOBALS['test_current_user_id']);
            unset($_POST['limit']);
            $this->pass(__LINE__);
        } catch (Exception $e) {
            unset($GLOBALS['test_current_user_id']);
            unset($_POST['limit']);
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }
    // ═══════════════════════════════════════════════════════════════════════
    // TEST 54: ajax_payout_summary() — empty (no qualified commissions)
    // ═══════════════════════════════════════════════════════════════════════

    private function test_ajax_payout_summary_empty() {
        echo "TEST 54: ajax_payout_summary() — empty (no qualified commissions)\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999801;
            $GLOBALS['test_current_user_id'] = $partner_id;
            global $test_ajax_response;
            $test_ajax_response = null;

            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Insert a pending commission (not qualified — excluded from payout)
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 400, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 80001,
                'partner_code_used' => 'PAYOUT-EMPTY', 'commission_rate' => 10.0,
                'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'),
            ]);

            $_POST['nonce'] = 'test-nonce';

            try {
                $this->commissions->ajax_payout_summary();
                $this->fail(__LINE__, 'Expected AJAX_RESPONSE_SENT exception');
            } catch (Exception $e) {
                if ($e->getMessage() !== 'AJAX_RESPONSE_SENT') { throw $e; }
            }

            $data = $test_ajax_response['data'];

            $this->assert(is_array($data['items']), 'items is array', __LINE__);
            $this->assertEquals(0, $data['count'], 'count = 0', __LINE__);
            $this->assertEquals(0, $data['total_gross'], 'total_gross = 0', __LINE__);
            $this->assertEquals(0, $data['total_fee'], 'total_fee = 0', __LINE__);
            $this->assertEquals(0, $data['total_net'], 'total_net = 0', __LINE__);
            $this->assert($data['meets_threshold'] === false, 'meets_threshold = false', __LINE__);
            $this->assertEquals(25.0, $data['min_threshold'], 'min_threshold = 25.0', __LINE__);

            unset($GLOBALS['test_current_user_id']);
            $this->pass(__LINE__);
        } catch (Exception $e) {
            unset($GLOBALS['test_current_user_id']);
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 55: ajax_payout_summary() — with qualified items
    // ═══════════════════════════════════════════════════════════════════════

    private function test_ajax_payout_summary_with_items() {
        echo "TEST 55: ajax_payout_summary() — qualified commissions with customer_name\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999802;
            $GLOBALS['test_current_user_id'] = $partner_id;
            global $test_ajax_response;
            $test_ajax_response = null;

            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // Insert user for customer_name join
            $this->wpdb->insert('wp_test_users', [
                'ID' => 80101, 'user_email' => 'payout@test.com',
                'display_name' => 'Payout Customer',
            ]);

            // Commission 1: $10 net (below $25 threshold individually)
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 410, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 80101,
                'partner_code_used' => 'PAYOUT-1', 'commission_rate' => 10.0,
                'commission_amount' => 10.26, 'qualified_amount' => 102.60,
                'fee_amount' => 0.26, 'net_amount' => 10.00,
                'status' => 'qualified',
                'qualified_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
            ]);
            $comm1_id = $this->wpdb->insert_id;

            // Commission 2: $5 net (below $25 threshold)
            $this->wpdb->insert($comm_table, [
                'attribution_id' => 411, 'partner_user_id' => $partner_id,
                'partner_org_id' => 1, 'customer_user_id' => 99999,
                'partner_code_used' => 'PAYOUT-2', 'commission_rate' => 10.0,
                'commission_amount' => 5.13, 'qualified_amount' => 51.30,
                'fee_amount' => 0.13, 'net_amount' => 5.00,
                'status' => 'qualified',
                'qualified_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
            ]);
            $comm2_id = $this->wpdb->insert_id;

            $_POST['nonce'] = 'test-nonce';

            try {
                $this->commissions->ajax_payout_summary();
                $this->fail(__LINE__, 'Expected AJAX_RESPONSE_SENT exception');
            } catch (Exception $e) {
                if ($e->getMessage() !== 'AJAX_RESPONSE_SENT') { throw $e; }
            }

            $data = $test_ajax_response['data'];

            // Total: 10.26 + 5.13 = 15.39 gross, 0.26 + 0.13 = 0.39 fee, 10.00 + 5.00 = 15.00 net
            $this->assertEquals(2, $data['count'], 'count = 2', __LINE__);
            $this->assertEquals(15.39, $data['total_gross'], 'total_gross = 15.39', __LINE__);
            $this->assertEquals(0.39, $data['total_fee'], 'total_fee = 0.39', __LINE__);
            $this->assertEquals(15.0, $data['total_net'], 'total_net = 15.00', __LINE__);
            $this->assert($data['meets_threshold'] === false, 'meets_threshold = false (15.00 < 25.00)', __LINE__);

            // Items ordered by qualified_at ASC
            $items = $data['items'];
            $this->assertEquals(2, count($items), '2 items in response', __LINE__);

            // First item: older qualified_at
            $this->assertEquals($comm1_id, $items[0]['commission_id'], 'Item 1 commission_id', __LINE__);
            $this->assertEquals('Payout Customer', $items[0]['customer_name'], 'Item 1 customer_name from join', __LINE__);
            $this->assertEquals(10.26, $items[0]['commission_amount'], 'Item 1 commission_amount', __LINE__);
            $this->assertEquals(0.26, $items[0]['fee_amount'], 'Item 1 fee_amount', __LINE__);
            $this->assertEquals(10.0, $items[0]['net_amount'], 'Item 1 net_amount', __LINE__);
            $this->assertEquals(10.0, $items[0]['commission_rate'], 'Item 1 commission_rate', __LINE__);

            // Second item: newer qualified_at, unknown customer
            $this->assertEquals($comm2_id, $items[1]['commission_id'], 'Item 2 commission_id', __LINE__);
            $this->assertEquals('(unknown)', $items[1]['customer_name'], 'Item 2 customer_name = (unknown)', __LINE__);

            unset($GLOBALS['test_current_user_id']);
            $this->pass(__LINE__);
        } catch (Exception $e) {
            unset($GLOBALS['test_current_user_id']);
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 56: ajax_payout_summary() — meets_threshold
    // ═══════════════════════════════════════════════════════════════════════

    private function test_ajax_payout_summary_meets_threshold() {
        echo "TEST 56: ajax_payout_summary() — meets payout threshold\n";
        echo str_repeat('-', 70) . "\n";

        try {
            $partner_id = 999803;
            $GLOBALS['test_current_user_id'] = $partner_id;
            global $test_ajax_response;
            $test_ajax_response = null;

            $comm_table = $this->wpdb->base_prefix . 'orabooks_partner_commissions';

            // 3 commissions totalling above $25 net
            $comms_data = [
                ['attribution_id' => 420, 'amount' => 20.00, 'fee' => 0.50, 'net' => 19.50],
                ['attribution_id' => 421, 'amount' => 10.00, 'fee' => 0.25, 'net' => 9.75],
                ['attribution_id' => 422, 'amount' => 5.00, 'fee' => 0.13, 'net' => 4.87],
            ];

            foreach ($comms_data as $i => $cd) {
                $this->wpdb->insert($comm_table, [
                    'attribution_id' => $cd['attribution_id'],
                    'partner_user_id' => $partner_id,
                    'partner_org_id' => 1,
                    'customer_user_id' => 80200 + $i,
                    'partner_code_used' => 'THRESH-' . ($i + 1),
                    'commission_rate' => 10.0,
                    'commission_amount' => $cd['amount'],
                    'qualified_amount' => $cd['amount'] * 10,
                    'fee_amount' => $cd['fee'],
                    'net_amount' => $cd['net'],
                    'status' => 'qualified',
                    'qualified_at' => date('Y-m-d H:i:s', strtotime('-' . ($i * 10) . ' days')),
                    'created_at' => date('Y-m-d H:i:s', strtotime('-' . ($i * 10 + 30) . ' days')),
                ]);
            }

            $_POST['nonce'] = 'test-nonce';

            try {
                $this->commissions->ajax_payout_summary();
                $this->fail(__LINE__, 'Expected AJAX_RESPONSE_SENT exception');
            } catch (Exception $e) {
                if ($e->getMessage() !== 'AJAX_RESPONSE_SENT') { throw $e; }
            }

            $data = $test_ajax_response['data'];

            // Expected: 20 + 10 + 5 = 35 gross, 0.50 + 0.25 + 0.13 = 0.88 fee, 19.50 + 9.75 + 4.87 = 34.12 net
            $expected_gross = 20.00 + 10.00 + 5.00;
            $expected_fee   = round(0.50 + 0.25 + 0.13, 2);
            $expected_net   = round(19.50 + 9.75 + 4.87, 2);

            $this->assertEquals(3, $data['count'], 'count = 3', __LINE__);
            $this->assertEquals($expected_gross, $data['total_gross'], "total_gross = {$expected_gross}", __LINE__);
            $this->assertEquals($expected_fee, $data['total_fee'], "total_fee = {$expected_fee}", __LINE__);
            $this->assertEquals($expected_net, $data['total_net'], "total_net = {$expected_net}", __LINE__);
            $this->assert($data['meets_threshold'] === true,
                'meets_threshold = true (' . $expected_net . ' >= 25.00)', __LINE__);

            unset($GLOBALS['test_current_user_id']);
            $this->pass(__LINE__);
        } catch (Exception $e) {
            unset($GLOBALS['test_current_user_id']);
            $this->fail(__LINE__, $e->getMessage());
        }
        echo "\n";
    }
// Assertion Helpers
    // ═══════════════════════════════════════════════════════════════════════

    private function assert($condition, $message, $line) {
        if ($condition) { $this->passed++; echo "  [PASS] Line {$line}: {$message}\n"; }
        else { $this->failed++; echo "  [FAIL] Line {$line}: {$message}\n"; }
    }

    private function assertEquals($expected, $actual, $message, $line) {
        $epsilon = 0.01;
        $condition = (is_float($expected) || is_float($actual))
            ? abs((float)$expected - (float)$actual) < $epsilon
            : $expected == $actual;
        if ($condition) { $this->passed++; echo "  [PASS] Line {$line}: {$message}\n"; }
        else { $this->failed++; echo "  [FAIL] Line {$line}: {$message} (expected: " . json_encode($expected) . ", got: " . json_encode($actual) . ")\n"; }
    }

    private function pass($line) {}
    private function fail($line, $message) { $this->failed++; echo "  [ERROR] Line {$line}: Exception: {$message}\n"; }
}

// ════════════════════════════════════════════════════════════════════════════
// SECTION 4: Run Tests
// ════════════════════════════════════════════════════════════════════════════

$test = new SL068_MonthlyRelease_Test();
$success = $test->run();
exit($success ? 0 : 1);