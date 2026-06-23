<?php
/**
 * OraBooks Customers / Invoices / AR Module
 *
 * Manages the customer lifecycle, invoicing, and payment tracking.
 * Serves as the truth source for customer active status used by the
 * commission engine.
 *
 * Key features:
 * - customers.is_active as the authoritative truth source
 * - Full invoice lifecycle (draft → sent → posted → paid/overdue)
 * - Payment tracking with partial payment support
 * - Automatic customer_active_status read model update via
 * - Revenue recognition via posting engine integration
 */

if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Customers {

 private static $instance = null;

 public static function init() {
 if (self::$instance === null) {
 self::$instance = new self;

 // AJAX endpoints
 add_action('wp_ajax_orabooks_customers_list', [self::$instance, 'ajax_customers_list']);
 add_action('wp_ajax_nopriv_orabooks_customers_list', [self::$instance, 'ajax_customers_list']);
 add_action('wp_ajax_orabooks_customer_get', [self::$instance, 'ajax_customer_get']);
 add_action('wp_ajax_nopriv_orabooks_customer_get', [self::$instance, 'ajax_customer_get']);
 add_action('wp_ajax_orabooks_customer_update', [self::$instance, 'ajax_customer_update']);
 add_action('wp_ajax_orabooks_customer_create', [self::$instance, 'ajax_customer_create']);
 add_action('wp_ajax_nopriv_orabooks_customer_create', [self::$instance, 'ajax_customer_create']);
 add_action('wp_ajax_orabooks_invoices_list', [self::$instance, 'ajax_invoices_list']);
 add_action('wp_ajax_nopriv_orabooks_invoices_list', [self::$instance, 'ajax_invoices_list']);
 add_action('wp_ajax_orabooks_invoice_create', [self::$instance, 'ajax_invoice_create']);
 add_action('wp_ajax_nopriv_orabooks_invoice_create', [self::$instance, 'ajax_invoice_create']);
 add_action('wp_ajax_orabooks_invoice_get', [self::$instance, 'ajax_invoice_get']);
 add_action('wp_ajax_nopriv_orabooks_invoice_get', [self::$instance, 'ajax_invoice_get']);
 add_action('wp_ajax_orabooks_invoice_override_tax', [self::$instance, 'ajax_invoice_override_tax']);
 add_action('wp_ajax_nopriv_orabooks_invoice_override_tax', [self::$instance, 'ajax_invoice_override_tax']);
 add_action('wp_ajax_orabooks_invoice_clear_tax_override', [self::$instance, 'ajax_invoice_clear_tax_override']);
 add_action('wp_ajax_nopriv_orabooks_invoice_clear_tax_override', [self::$instance, 'ajax_invoice_clear_tax_override']);
 add_action('wp_ajax_orabooks_invoice_send', [self::$instance, 'ajax_invoice_send']);
 add_action('wp_ajax_nopriv_orabooks_invoice_send', [self::$instance, 'ajax_invoice_send']);
 add_action('wp_ajax_orabooks_invoice_post', [self::$instance, 'ajax_invoice_post']);
 add_action('wp_ajax_nopriv_orabooks_invoice_post', [self::$instance, 'ajax_invoice_post']);
 add_action('wp_ajax_orabooks_invoice_cancel', [self::$instance, 'ajax_invoice_cancel']);
 add_action('wp_ajax_nopriv_orabooks_invoice_cancel', [self::$instance, 'ajax_invoice_cancel']);
 add_action('wp_ajax_orabooks_invoice_record_payment', [self::$instance, 'ajax_record_payment']);
 add_action('wp_ajax_nopriv_orabooks_invoice_record_payment', [self::$instance, 'ajax_record_payment']);
 add_action('wp_ajax_orabooks_customer_stats', [self::$instance, 'ajax_customer_stats']);
 add_action('wp_ajax_nopriv_orabooks_customer_stats', [self::$instance, 'ajax_customer_stats']);

 // Cron jobs
 add_action('orabooks_daily_customer_status_check', [self::$instance, 'daily_customer_status_check']);
 add_action('orabooks_daily_invoice_overdue_check', [self::$instance, 'daily_invoice_overdue_check']);
 }
 return self::$instance;
 }

 // ================================================================
 // DATABASE SCHEMA
 // ================================================================

 public static function get_create_table_sql() {
 global $wpdb;

 $charset_collate = $wpdb->get_charset_collate;
 $tables = [];
 $table_users = OraBooks_Database::table('users');
 $table_orgs = OraBooks_Database::table('organizations');

 //: customers table (is_active truth source for commission engine)
 $table_customers = OraBooks_Database::table('customers');
 $tables[] = "CREATE TABLE IF NOT EXISTS {$table_customers} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NULL,
 org_id BIGINT UNSIGNED NOT NULL,
 customer_code VARCHAR(30) NULL,
 display_name VARCHAR(255) NULL,
 contact_email VARCHAR(255) NULL,
 mobile VARCHAR(50) NULL,
 phone VARCHAR(50) NULL,
 gstin VARCHAR(64) NULL,
 tax_number VARCHAR(64) NULL,
 opening_balance DECIMAL(20,2) DEFAULT 0,
 country_id VARCHAR(100) NULL,
 state_id VARCHAR(100) NULL,
 city VARCHAR(100) NULL,
 postcode VARCHAR(30) NULL,
 address TEXT NULL,
 location_link VARCHAR(500) NULL,
 ship_country_id VARCHAR(100) NULL,
 ship_state_id VARCHAR(100) NULL,
 ship_city VARCHAR(100) NULL,
 ship_postcode VARCHAR(30) NULL,
 ship_address TEXT NULL,
 price_level_type ENUM('Increase','Decrease') DEFAULT 'Increase',
 price_level DECIMAL(10,2) DEFAULT 0,
 is_active TINYINT(1) DEFAULT 0 COMMENT 'Authoritative truth source for commission engine ',
 last_paid_invoice_date DATE NULL,
 lifetime_value DECIMAL(20,2) DEFAULT 0,
 payment_terms INT DEFAULT 30,
 default_currency CHAR(3) DEFAULT 'USD',
 credit_limit DECIMAL(20,2) DEFAULT 0,
 credit_hold TINYINT(1) DEFAULT 0,
 auto_apply_credit TINYINT(1) DEFAULT 1,
 credit_balance DECIMAL(20,2) DEFAULT 0,
 notes TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uk_user (user_id),
 UNIQUE KEY uk_org_contact_email (org_id, contact_email),
 UNIQUE KEY uk_org_customer_code (org_id, customer_code),
 FOREIGN KEY (user_id) REFERENCES {$table_users}(id) ON DELETE CASCADE,
 FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
 INDEX idx_org (org_id),
 INDEX idx_active (is_active)
 ) {$charset_collate};";

 //: invoices table
 $table_invoices = OraBooks_Database::table('invoices');
 $tables[] = "CREATE TABLE IF NOT EXISTS {$table_invoices} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 org_id BIGINT UNSIGNED NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL,
 invoice_number VARCHAR(50) NOT NULL,
 invoice_date DATE NOT NULL,
 transaction_date DATE NOT NULL,
 due_date DATE NOT NULL,
 description TEXT NULL,
 total_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
 tax_amount DECIMAL(20,2) DEFAULT 0,
 tax_rate DECIMAL(8,4) DEFAULT 0,
 tax_jurisdiction VARCHAR(32) NULL,
 tax_type VARCHAR(32) NULL,
 tax_override_reason VARCHAR(64) NULL,
 tax_override_by BIGINT UNSIGNED NULL,
 tax_override_at TIMESTAMP NULL,
 currency CHAR(3) DEFAULT 'USD',
 payment_status ENUM('unpaid','partial','paid','overdue','cancelled') DEFAULT 'unpaid',
 workflow_status ENUM('draft','sent','posted','cancelled') DEFAULT 'draft',
 paid_amount DECIMAL(20,2) DEFAULT 0,
 paid_at TIMESTAMP NULL,
 last_payment_date DATE NULL,
 metadata JSON NULL,
 idempotency_key VARCHAR(128),
 overdue_notified_at TIMESTAMP NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
 FOREIGN KEY (customer_id) REFERENCES {$table_customers}(id) ON DELETE CASCADE,
 UNIQUE KEY uk_org_invoice (org_id, invoice_number),
 UNIQUE KEY uk_idempotency (idempotency_key),
 INDEX idx_payment_status (payment_status),
 INDEX idx_workflow (workflow_status),
 INDEX idx_due_date (due_date),
 INDEX idx_transaction_date (transaction_date),
 INDEX idx_overdue_notified (payment_status, overdue_notified_at)
 ) {$charset_collate};";

 $table_invoice_lines = OraBooks_Database::table('invoice_line_items');
 $tables[] = "CREATE TABLE IF NOT EXISTS {$table_invoice_lines} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 org_id BIGINT UNSIGNED NOT NULL,
 invoice_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NULL,
 description TEXT NULL,
 quantity DECIMAL(20,4) NOT NULL DEFAULT 0,
 unit_price DECIMAL(20,6) NOT NULL DEFAULT 0,
 line_total DECIMAL(20,2) NOT NULL DEFAULT 0,
 sort_order INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
 FOREIGN KEY (invoice_id) REFERENCES {$table_invoices}(id) ON DELETE CASCADE,
 INDEX idx_invoice (invoice_id),
 INDEX idx_product (product_id)
 ) {$charset_collate};";

 //: payments table
 $table_payments = OraBooks_Database::table('payments');
 $tables[] = "CREATE TABLE IF NOT EXISTS {$table_payments} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 org_id BIGINT UNSIGNED NOT NULL,
 invoice_id BIGINT UNSIGNED NOT NULL,
 payment_date DATE NOT NULL,
 amount DECIMAL(20,2) NOT NULL,
 payment_method ENUM('bank_transfer','credit_card','cash','check','other') DEFAULT 'bank_transfer',
 reference VARCHAR(255) NULL,
 notes TEXT NULL,
 idempotency_key VARCHAR(128),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
 FOREIGN KEY (invoice_id) REFERENCES {$table_invoices}(id) ON DELETE CASCADE,
 UNIQUE KEY uk_idempotency_payment (idempotency_key),
 INDEX idx_invoice (invoice_id),
 INDEX idx_payment_date (payment_date)
 ) {$charset_collate};";

 // Seed customers for existing users who are partners' customers
 // Done in seed_default_customers

 return $tables;
 }

 /**
 * Idempotent schema upgrades for existing installs.
 * dbDelta CREATE TABLE IF NOT EXISTS does not add missing columns.
 */
 public static function ensure_schema() {
 global $wpdb;

 if (
 self::schema_is_ready
 && self::get_schema_flag('orabooks_sl021_schema_v2') === '1'
 && self::get_schema_flag('orabooks_sl021_customer_contacts_v1') === '1'
 && self::get_schema_flag('orabooks_sl021_customer_credit_v1') === '1'
 && self::get_schema_flag('orabooks_sl021_customer_profile_v1') === '1'
 ) {
 return;
 }

 $upgrade = ABSPATH. 'wp-admin/includes/upgrade.php';
 if (!file_exists($upgrade)) {
 self::ensure_customer_contact_schema;
 self::ensure_customer_credit_schema;
 self::ensure_customer_profile_schema;
 return;
 }

 require_once $upgrade;

 self::ensure_payments_table;
 self::ensure_customer_contact_schema;
 self::ensure_customer_credit_schema;
 self::ensure_customer_profile_schema;
 if (class_exists('OraBooks_AR')) {
 OraBooks_AR::ensure_schema;
 }

 $table_invoices = OraBooks_Database::table('invoices');
 if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_invoices)) !== $table_invoices) {
 return;
 }

 $fields = self::get_table_column_names($table_invoices);
 $had_amount = in_array('amount', $fields, true);

 $additions = [
 'transaction_date' => "ALTER TABLE {$table_invoices} ADD COLUMN transaction_date DATE NULL",
 'description' => "ALTER TABLE {$table_invoices} ADD COLUMN description TEXT NULL",
 'total_amount' => "ALTER TABLE {$table_invoices} ADD COLUMN total_amount DECIMAL(20,2) NOT NULL DEFAULT 0",
 'tax_amount' => "ALTER TABLE {$table_invoices} ADD COLUMN tax_amount DECIMAL(20,2) DEFAULT 0",
 'tax_rate' => "ALTER TABLE {$table_invoices} ADD COLUMN tax_rate DECIMAL(8,4) DEFAULT 0",
 'tax_jurisdiction' => "ALTER TABLE {$table_invoices} ADD COLUMN tax_jurisdiction VARCHAR(32) NULL",
 'tax_type' => "ALTER TABLE {$table_invoices} ADD COLUMN tax_type VARCHAR(32) NULL",
 'tax_override_reason' => "ALTER TABLE {$table_invoices} ADD COLUMN tax_override_reason VARCHAR(64) NULL",
 'tax_override_by' => "ALTER TABLE {$table_invoices} ADD COLUMN tax_override_by BIGINT UNSIGNED NULL",
 'tax_override_at' => "ALTER TABLE {$table_invoices} ADD COLUMN tax_override_at TIMESTAMP NULL",
 'currency' => "ALTER TABLE {$table_invoices} ADD COLUMN currency CHAR(3) DEFAULT 'USD'",
 'payment_status' => "ALTER TABLE {$table_invoices} ADD COLUMN payment_status ENUM('unpaid','partial','paid','overdue','cancelled') DEFAULT 'unpaid'",
 'workflow_status' => "ALTER TABLE {$table_invoices} ADD COLUMN workflow_status ENUM('draft','sent','posted','cancelled') DEFAULT 'draft'",
 'paid_amount' => "ALTER TABLE {$table_invoices} ADD COLUMN paid_amount DECIMAL(20,2) DEFAULT 0",
 'paid_at' => "ALTER TABLE {$table_invoices} ADD COLUMN paid_at TIMESTAMP NULL",
 'last_payment_date' => "ALTER TABLE {$table_invoices} ADD COLUMN last_payment_date DATE NULL",
 'metadata' => "ALTER TABLE {$table_invoices} ADD COLUMN metadata JSON NULL",
 'idempotency_key' => "ALTER TABLE {$table_invoices} ADD COLUMN idempotency_key VARCHAR(128) NULL",
 'overdue_notified_at' => "ALTER TABLE {$table_invoices} ADD COLUMN overdue_notified_at TIMESTAMP NULL",
 ];

 foreach ($additions as $column => $sql) {
 if (!in_array($column, $fields, true)) {
 if ($wpdb->query($sql) !== false) {
 $fields[] = $column;
 }
 }
 }

 $fields = self::get_table_column_names($table_invoices);

 if (in_array('transaction_date', $fields, true) && in_array('invoice_date', $fields, true)) {
 $wpdb->query(
 "UPDATE {$table_invoices}
 SET transaction_date = invoice_date
 WHERE (transaction_date IS NULL OR transaction_date = '0000-00-00')
 AND invoice_date IS NOT NULL
 AND invoice_date != '0000-00-00'"
 );
 }

 if ($had_amount && in_array('total_amount', $fields, true)) {
 $wpdb->query(
 "UPDATE {$table_invoices}
 SET total_amount = amount
 WHERE (total_amount IS NULL OR total_amount = 0)
 AND amount IS NOT NULL
 AND amount > 0"
 );
 }

 $indexes = $wpdb->get_results("SHOW INDEX FROM {$table_invoices}");
 $index_names = array_map(function ($idx) {
 return $idx->Key_name;
 }, $indexes ?: []);

 if (!in_array('idx_transaction_date', $index_names, true) && in_array('transaction_date', $fields, true)) {
 $wpdb->query("ALTER TABLE {$table_invoices} ADD INDEX idx_transaction_date (transaction_date)");
 }

 if (self::schema_is_ready) {
 self::set_schema_flag('orabooks_sl021_schema_v2', '1');
 }

 self::ensure_invoice_line_items_schema;
 }

 /**
 *: invoice line items for inventory integration.
 */
 private static function ensure_invoice_line_items_schema() {
 if (self::get_schema_flag('orabooks_sl034_invoice_line_items_v1') === '1') {
 return;
 }

 global $wpdb;
 $table = OraBooks_Database::table('invoice_line_items');
 if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
 self::set_schema_flag('orabooks_sl034_invoice_line_items_v1', '1');
 return;
 }

 $upgrade = ABSPATH. 'wp-admin/includes/upgrade.php';
 if (!file_exists($upgrade)) {
 return;
 }

 require_once $upgrade;
 foreach (self::get_create_table_sql as $sql) {
 if (strpos($sql, 'invoice_line_items') !== false) {
 dbDelta($sql);
 break;
 }
 }

 if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
 self::set_schema_flag('orabooks_sl034_invoice_line_items_v1', '1');
 }
 }

 /**
 * Run schema migration before queries when bootstrap missed it.
 */
 private static function maybe_ensure_schema() {
 static $ran = false;
 if ($ran) {
 return;
 }
 $ran = true;

 if (
 self::schema_is_ready
 && self::get_schema_flag('orabooks_sl021_schema_v2') === '1'
 && self::get_schema_flag('orabooks_sl021_customer_contacts_v1') === '1'
 && self::get_schema_flag('orabooks_sl021_customer_credit_v1') === '1'
 && self::get_schema_flag('orabooks_sl021_customer_profile_v1') === '1'
 ) {
 return;
 }

 if (!file_exists(ABSPATH. 'wp-admin/includes/upgrade.php')) {
 return;
 }

 if (function_exists('orabooks_with_data_blog')) {
 orabooks_with_data_blog([self::class, 'ensure_schema']);
 return;
 }

 self::ensure_schema;
 }

 /**
 * Add org-scoped AR contact fields so customers can be created without a platform user.
 */
 private static function ensure_customer_contact_schema() {
 global $wpdb;

 if (self::get_schema_flag('orabooks_sl021_customer_contacts_v1') === '1') {
 return;
 }

 $table = OraBooks_Database::table('customers');
 if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
 return;
 }

 $fields = self::get_table_column_names($table);

 if (!in_array('display_name', $fields, true)) {
 $wpdb->query("ALTER TABLE {$table} ADD COLUMN display_name VARCHAR(255) NULL AFTER org_id");
 $fields[] = 'display_name';
 }

 if (!in_array('contact_email', $fields, true)) {
 $wpdb->query("ALTER TABLE {$table} ADD COLUMN contact_email VARCHAR(255) NULL AFTER display_name");
 $fields[] = 'contact_email';
 }

 $user_col = $wpdb->get_row("SHOW COLUMNS FROM {$table} LIKE 'user_id'");
 if ($user_col && strtoupper((string) $user_col->Null) === 'NO') {
 $wpdb->query("ALTER TABLE {$table} MODIFY user_id BIGINT UNSIGNED NULL");
 }

 $indexes = $wpdb->get_results("SHOW INDEX FROM {$table}");
 $index_names = array_map(function ($idx) {
 return $idx->Key_name;
 }, $indexes ?: []);

 if (!in_array('uk_org_contact_email', $index_names, true) && in_array('contact_email', $fields, true)) {
 $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY uk_org_contact_email (org_id, contact_email)");
 }

 self::set_schema_flag('orabooks_sl021_customer_contacts_v1', '1');
 }

 /**
 * credit/wallet columns on customers (credit_limit, credit_hold, etc.).
 */
 private static function ensure_customer_credit_schema() {
 global $wpdb;

 if (self::get_schema_flag('orabooks_sl021_customer_credit_v1') === '1') {
 return;
 }

 $table = OraBooks_Database::table('customers');
 if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
 return;
 }

 $fields = self::get_table_column_names($table);
 $additions = [
 'payment_terms' => "ALTER TABLE {$table} ADD COLUMN payment_terms INT DEFAULT 30 AFTER lifetime_value",
 'default_currency' => "ALTER TABLE {$table} ADD COLUMN default_currency CHAR(3) DEFAULT 'USD' AFTER payment_terms",
 'credit_limit' => "ALTER TABLE {$table} ADD COLUMN credit_limit DECIMAL(20,2) DEFAULT 0 AFTER default_currency",
 'credit_hold' => "ALTER TABLE {$table} ADD COLUMN credit_hold TINYINT(1) DEFAULT 0 AFTER credit_limit",
 'auto_apply_credit' => "ALTER TABLE {$table} ADD COLUMN auto_apply_credit TINYINT(1) DEFAULT 1 AFTER credit_hold",
 'credit_balance' => "ALTER TABLE {$table} ADD COLUMN credit_balance DECIMAL(20,2) DEFAULT 0 AFTER auto_apply_credit",
 ];

 foreach ($additions as $column => $sql) {
 if (!in_array($column, $fields, true)) {
 if ($wpdb->query($sql) !== false) {
 $fields[] = $column;
 }
 }
 }

 self::set_schema_flag('orabooks_sl021_customer_credit_v1', '1');
 }

 /**
 * Extended customer profile fields used by the React add/edit modal.
 */
 private static function ensure_customer_profile_schema() {
 global $wpdb;

 if (self::get_schema_flag('orabooks_sl021_customer_profile_v1') === '1') {
 return;
 }

 $table = OraBooks_Database::table('customers');
 if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
 return;
 }

 $fields = self::get_table_column_names($table);
 $additions = [
 'customer_code' => "ALTER TABLE {$table} ADD COLUMN customer_code VARCHAR(30) NULL AFTER org_id",
 'mobile' => "ALTER TABLE {$table} ADD COLUMN mobile VARCHAR(50) NULL AFTER contact_email",
 'phone' => "ALTER TABLE {$table} ADD COLUMN phone VARCHAR(50) NULL AFTER mobile",
 'gstin' => "ALTER TABLE {$table} ADD COLUMN gstin VARCHAR(64) NULL AFTER phone",
 'tax_number' => "ALTER TABLE {$table} ADD COLUMN tax_number VARCHAR(64) NULL AFTER gstin",
 'opening_balance' => "ALTER TABLE {$table} ADD COLUMN opening_balance DECIMAL(20,2) DEFAULT 0 AFTER tax_number",
 'country_id' => "ALTER TABLE {$table} ADD COLUMN country_id VARCHAR(100) NULL AFTER opening_balance",
 'state_id' => "ALTER TABLE {$table} ADD COLUMN state_id VARCHAR(100) NULL AFTER country_id",
 'city' => "ALTER TABLE {$table} ADD COLUMN city VARCHAR(100) NULL AFTER state_id",
 'postcode' => "ALTER TABLE {$table} ADD COLUMN postcode VARCHAR(30) NULL AFTER city",
 'address' => "ALTER TABLE {$table} ADD COLUMN address TEXT NULL AFTER postcode",
 'location_link' => "ALTER TABLE {$table} ADD COLUMN location_link VARCHAR(500) NULL AFTER address",
 'ship_country_id' => "ALTER TABLE {$table} ADD COLUMN ship_country_id VARCHAR(100) NULL AFTER location_link",
 'ship_state_id' => "ALTER TABLE {$table} ADD COLUMN ship_state_id VARCHAR(100) NULL AFTER ship_country_id",
 'ship_city' => "ALTER TABLE {$table} ADD COLUMN ship_city VARCHAR(100) NULL AFTER ship_state_id",
 'ship_postcode' => "ALTER TABLE {$table} ADD COLUMN ship_postcode VARCHAR(30) NULL AFTER ship_city",
 'ship_address' => "ALTER TABLE {$table} ADD COLUMN ship_address TEXT NULL AFTER ship_postcode",
 'price_level_type' => "ALTER TABLE {$table} ADD COLUMN price_level_type ENUM('Increase','Decrease') DEFAULT 'Increase' AFTER ship_address",
 'price_level' => "ALTER TABLE {$table} ADD COLUMN price_level DECIMAL(10,2) DEFAULT 0 AFTER price_level_type",
 ];

 foreach ($additions as $column => $sql) {
 if (!in_array($column, $fields, true)) {
 if ($wpdb->query($sql) !== false) {
 $fields[] = $column;
 }
 }
 }

 $indexes = $wpdb->get_results("SHOW INDEX FROM {$table}");
 $index_names = array_map(function ($idx) {
 return $idx->Key_name;
 }, $indexes ?: []);

 if (!in_array('uk_org_customer_code', $index_names, true) && in_array('customer_code', $fields, true)) {
 $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY uk_org_customer_code (org_id, customer_code)");
 }

 self::set_schema_flag('orabooks_sl021_customer_profile_v1', '1');
 }

 /**
 * Create payments table without relying on dbDelta foreign keys.
 */
 private static function ensure_payments_table() {
 global $wpdb;

 $table_payments = OraBooks_Database::table('payments');
 if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_payments)) === $table_payments) {
 return;
 }

 $upgrade = ABSPATH. 'wp-admin/includes/upgrade.php';
 if (file_exists($upgrade)) {
 require_once $upgrade;

 $payments_sql = array_values(array_filter(
 self::get_create_table_sql,
 function ($sql) {
 return stripos($sql, 'orabooks_payments') !== false;
 }
 ));

 if (!empty($payments_sql[0]) && function_exists('dbDelta')) {
 dbDelta($payments_sql[0]);
 }
 }

 if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_payments)) === $table_payments) {
 return;
 }

 $charset_collate = $wpdb->get_charset_collate;
 $wpdb->query(
 "CREATE TABLE IF NOT EXISTS {$table_payments} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 org_id BIGINT UNSIGNED NOT NULL,
 invoice_id BIGINT UNSIGNED NOT NULL,
 payment_date DATE NOT NULL,
 amount DECIMAL(20,2) NOT NULL,
 payment_method ENUM('bank_transfer','credit_card','cash','check','other') DEFAULT 'bank_transfer',
 reference VARCHAR(255) NULL,
 notes TEXT NULL,
 idempotency_key VARCHAR(128) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_invoice (invoice_id),
 INDEX idx_payment_date (payment_date)
 ) {$charset_collate}"
 );
 }

 /**
 * Whether invoice/payment tables have the columns dashboard queries need.
 */
 public static function schema_is_ready() {
 global $wpdb;

 $table_invoices = OraBooks_Database::table('invoices');
 $table_payments = OraBooks_Database::table('payments');

 if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_payments)) !== $table_payments) {
 return false;
 }

 if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_invoices)) !== $table_invoices) {
 return false;
 }

 $fields = self::get_table_column_names($table_invoices);
 foreach (['total_amount', 'transaction_date', 'paid_amount', 'payment_status'] as $required) {
 if (!in_array($required, $fields, true)) {
 return false;
 }
 }

 return true;
 }

 private static function get_schema_flag($key) {
 if (function_exists('is_multisite') && is_multisite && function_exists('get_site_option')) {
 return get_site_option($key);
 }

 return get_option($key);
 }

 private static function set_schema_flag($key, $value) {
 if (function_exists('is_multisite') && is_multisite && function_exists('update_site_option')) {
 update_site_option($key, $value);
 return;
 }

 update_option($key, $value, false);
 }

 /**
 * @return string[]
 */
 private static function get_table_column_names($table) {
 global $wpdb;

 $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table}");
 if (empty($columns)) {
 return [];
 }

 return array_map(function ($col) {
 return $col->Field;
 }, $columns);
 }

 private static function next_customer_code($org_id) {
 global $wpdb;

 $table = OraBooks_Database::table('customers');
 $last_number = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT COALESCE(MAX(CAST(SUBSTRING(customer_code, 5) AS UNSIGNED)), 0)
 FROM {$table}
 WHERE org_id = %d AND customer_code LIKE 'CUS-______'",
 (int) $org_id
 ));

 return 'CUS-'. str_pad((string) ($last_number + 1), 6, '0', STR_PAD_LEFT);
 }

 /**
 * @return array{updates: array<string,mixed>, formats: string[]}
 */
 private static function customer_profile_payload(array $data, $include_code = false, $org_id = 0) {
 $updates = [];
 $formats = [];

 if ($include_code) {
 $updates['customer_code'] = self::next_customer_code((int) $org_id);
 $formats[] = '%s';
 }

 $text_fields = [
 'mobile' => '%s',
 'phone' => '%s',
 'gstin' => '%s',
 'tax_number' => '%s',
 'country_id' => '%s',
 'state_id' => '%s',
 'city' => '%s',
 'postcode' => '%s',
 'location_link' => '%s',
 'ship_country_id' => '%s',
 'ship_state_id' => '%s',
 'ship_city' => '%s',
 'ship_postcode' => '%s',
 ];

 foreach ($text_fields as $field => $format) {
 if (array_key_exists($field, $data)) {
 $updates[$field] = sanitize_text_field($data[$field]);
 $formats[] = $format;
 }
 }

 foreach (['address', 'ship_address'] as $field) {
 if (array_key_exists($field, $data)) {
 $updates[$field] = sanitize_textarea_field($data[$field]);
 $formats[] = '%s';
 }
 }

 if (array_key_exists('opening_balance', $data)) {
 $updates['opening_balance'] = round((float) $data['opening_balance'], 2);
 $formats[] = '%f';
 }

 if (array_key_exists('price_level_type', $data)) {
 $type = sanitize_text_field($data['price_level_type']);
 $updates['price_level_type'] = $type === 'Decrease' ? 'Decrease': 'Increase';
 $formats[] = '%s';
 }

 if (array_key_exists('price_level', $data)) {
 $updates['price_level'] = round((float) $data['price_level'], 2);
 $formats[] = '%f';
 }

 return ['updates' => $updates, 'formats' => $formats];
 }

 // ================================================================
 // SEED DEFAULT CUSTOMERS
 // ================================================================

 /**
 * Seed customer records for users referenced in partner_attributions
 * that don't already have a customers row.
 */
 public static function seed_default_customers() {
 global $wpdb;

 $table_customers = OraBooks_Database::table('customers');
 $table_attributions = OraBooks_Database::table('partner_attributions');
 $table_users = OraBooks_Database::table('users');

 // Find users referenced as customers in attributions but not yet in customers table
 $missing_customers = $wpdb->get_results(
 "SELECT DISTINCT pa.customer_user_id, u.org_id
 FROM {$table_attributions} pa
 JOIN {$table_users} u ON pa.customer_user_id = u.id
 LEFT JOIN {$table_customers} c ON c.user_id = pa.customer_user_id
 WHERE c.id IS NULL"
 );

 foreach ($missing_customers as $mc) {
 $wpdb->insert(
 $table_customers,
 [
 'user_id' => $mc->customer_user_id,
 'org_id' => $mc->org_id,
 'is_active' => 0,
 ],
 ['%d', '%d', '%d']
 );
 }

 return count($missing_customers);
 }

 // ================================================================
 // CUSTOMER METHODS
 // ================================================================

 /**
 * Get or create a customer record for a user.
 */
 public static function get_or_create($user_id, $org_id) {
 global $wpdb;

 $table = OraBooks_Database::table('customers');
 $customer = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table} WHERE user_id = %d",
 $user_id
 ));

 if ($customer) {
 return $customer;
 }

 $wpdb->insert(
 $table,
 [
 'user_id' => $user_id,
 'org_id' => $org_id,
 'is_active' => 0,
 ],
 ['%d', '%d', '%d']
 );

 return $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table} WHERE id = %d",
 $wpdb->insert_id
 ));
 }

 /**
 * Create an org-scoped AR customer profile (manual entry, ).
 *
 * @param int $org_id
 * @param array $data
 * @return object|WP_Error
 */
 public static function create_customer($org_id, $data) {
 global $wpdb;

 self::maybe_ensure_schema;

 $org_id = (int) $org_id;
 $display_name = sanitize_text_field($data['display_name'] ?? $data['name'] ?? '');
 $contact_email = sanitize_email($data['email'] ?? $data['contact_email'] ?? '');
 $notes = isset($data['notes']) ? sanitize_textarea_field($data['notes']): null;
 $payment_terms = max(0, intval($data['payment_terms'] ?? 30));
 $default_currency = strtoupper(sanitize_text_field($data['default_currency'] ?? 'USD'));
 $credit_limit = round(floatval($data['credit_limit'] ?? 0), 2);
 $credit_hold = isset($data['credit_hold']) ? (int) (bool) $data['credit_hold']: 0;
 $auto_apply_credit = array_key_exists('auto_apply_credit', $data)
 ? (int) (bool) $data['auto_apply_credit']
: 1;
 $profile_payload = self::customer_profile_payload($data, true, $org_id);

 if ($org_id <= 0 || $display_name === '') {
 return new WP_Error('missing_field', 'Organization and customer name are required');
 }

 if ($contact_email !== '' && !is_email($contact_email)) {
 return new WP_Error('invalid_email', 'Please enter a valid email address');
 }

 $table = OraBooks_Database::table('customers');
 $customer_code = $profile_payload['updates']['customer_code'] ?? '';

 if ($contact_email !== '') {
 $existing = $wpdb->get_var($wpdb->prepare(
 "SELECT id FROM {$table}
 WHERE org_id = %d AND contact_email IS NOT NULL AND LOWER(contact_email) = LOWER(%s)
 LIMIT 1",
 $org_id,
 $contact_email
 ));

 if ($existing) {
 return new WP_Error('duplicate', 'A customer with this email already exists for your organization.');
 }
 }

 if ($customer_code !== '') {
 $existing_code = $wpdb->get_var($wpdb->prepare(
 "SELECT id FROM {$table}
 WHERE org_id = %d AND customer_code IS NOT NULL AND LOWER(customer_code) = LOWER(%s)
 LIMIT 1",
 $org_id,
 $customer_code
 ));

 if ($existing_code) {
 return new WP_Error('duplicate_code', 'A customer with this code already exists for your organization.');
 }
 }

 $insert_data = array_merge(
 [
 'org_id' => $org_id,
 'display_name' => $display_name,
 'contact_email' => $contact_email !== '' ? $contact_email: null,
 'notes' => $notes,
 'payment_terms' => $payment_terms,
 'default_currency' => $default_currency !== '' ? $default_currency: 'USD',
 'credit_limit' => $credit_limit,
 'credit_hold' => $credit_hold,
 'auto_apply_credit' => $auto_apply_credit,
 'credit_balance' => 0,
 'is_active' => 0,
 ],
 $profile_payload['updates']
 );

 $insert_formats = array_merge(
 ['%d', '%s', '%s', '%s', '%d', '%s', '%f', '%d', '%d', '%f', '%d'],
 $profile_payload['formats']
 );

 $inserted = $wpdb->insert(
 $table,
 $insert_data,
 $insert_formats
 );

 if ($inserted === false) {
 return new WP_Error('db_error', 'Unable to create customer profile.');
 }

 $customer_id = (int) $wpdb->insert_id;

 orabooks_log_event(
 'customer_created',
 "Customer profile created: {$display_name}",
 'info',
 ['customer_id' => $customer_id, 'contact_email' => $contact_email],
 orabooks_get_current_user_id,
 $org_id
 );

 return self::get_by_id($customer_id);
 }

 /**
 * Get customer by user ID.
 */
 public static function get_by_user_id($user_id) {
 global $wpdb;
 $table = OraBooks_Database::table('customers');
 $table_users = OraBooks_Database::table('users');
 return $wpdb->get_row($wpdb->prepare(
 "SELECT c.*, u.email, u.is_email_verified, u.created_at as user_created_at
 FROM {$table} c
 JOIN {$table_users} u ON c.user_id = u.id
 WHERE c.user_id = %d",
 $user_id
 ));
 }

 /**
 * Get customer by customers table ID.
 */
 public static function get_by_id($customer_id) {
 global $wpdb;
 $table = OraBooks_Database::table('customers');
 $table_users = OraBooks_Database::table('users');
 return $wpdb->get_row($wpdb->prepare(
 "SELECT c.*,
 COALESCE(NULLIF(c.contact_email, ''), u.email) AS email
 FROM {$table} c
 LEFT JOIN {$table_users} u ON c.user_id = u.id
 WHERE c.id = %d",
 $customer_id
 ));
 }

 /**
 * List customers with optional filters.
 * Pass org_id = 0 for global admin view (all orgs).
 */
 public static function get_list($org_id, $args = []) {
 global $wpdb;

 self::maybe_ensure_schema;

 $table = OraBooks_Database::table('customers');
 $table_users = OraBooks_Database::table('users');
 $table_invoices = OraBooks_Database::table('invoices');
 $table_orgs = OraBooks_Database::table('organizations');

 $where = '1=1';
 $params = [];

 if ($org_id > 0) {
 $where = 'c.org_id = %d';
 $params[] = $org_id;
 }

 if (isset($args['is_active'])) {
 $where.= ' AND c.is_active = %d';
 $params[] = (int) $args['is_active'];
 }

 if (!empty($args['search'])) {
 $where.= ' AND (
 u.email LIKE %s OR c.contact_email LIKE %s OR c.display_name LIKE %s OR c.notes LIKE %s
 OR c.customer_code LIKE %s OR c.mobile LIKE %s OR c.phone LIKE %s OR c.city LIKE %s
 )';
 $search = '%'. $wpdb->esc_like($args['search']). '%';
 $params[] = $search;
 $params[] = $search;
 $params[] = $search;
 $params[] = $search;
 $params[] = $search;
 $params[] = $search;
 $params[] = $search;
 $params[] = $search;
 }

 $limit = $args['limit'] ?? 50;
 $offset = $args['offset'] ?? 0;

 $sql = "SELECT c.*,
 COALESCE(NULLIF(c.contact_email, ''), u.email) AS email,
 o.name as org_name,
 (SELECT COUNT(*) FROM {$table_invoices} WHERE customer_id = c.id) as invoice_count,
 (SELECT COALESCE(SUM(total_amount), 0) FROM {$table_invoices} WHERE customer_id = c.id AND payment_status IN ('paid', 'partial')) as total_paid,
 (COALESCE(c.opening_balance, 0) + (SELECT COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0)
 FROM {$table_invoices}
 WHERE customer_id = c.id AND payment_status IN ('unpaid', 'partial', 'overdue'))) as wallet_balance
 FROM {$table} c
 LEFT JOIN {$table_users} u ON c.user_id = u.id
 LEFT JOIN {$table_orgs} o ON c.org_id = o.id
 WHERE {$where}
 ORDER BY c.updated_at DESC
 LIMIT %d OFFSET %d";
 $params[] = $limit;
 $params[] = $offset;

 $results = $wpdb->get_results($wpdb->prepare($sql, $params));

 // Get total count
 $count_params = $params;
 array_pop($count_params); // remove limit
 array_pop($count_params); // remove offset
 $count_sql = "SELECT COUNT(*) FROM {$table} c LEFT JOIN {$table_users} u ON c.user_id = u.id LEFT JOIN {$table_orgs} o ON c.org_id = o.id WHERE {$where}";
 $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $count_params));

 return [
 'customers' => $results,
 'total' => $total,
 'page' => ($limit > 0) ? floor($offset / $limit) + 1: 1,
 'per_page' => $limit,
 ];
 }

 /**
 * Recompute customer is_active from posted paid invoices ( source of truth).
 */
 public static function recompute_active_status($customer_id) {
 global $wpdb;

 $table = OraBooks_Database::table('customers');
 $table_invoices = OraBooks_Database::table('invoices');
 $table_payments = OraBooks_Database::table('payments');
 $config_table = OraBooks_Database::table('partner_commission_config');

 $customer = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table} WHERE id = %d",
 $customer_id
 ));

 if (!$customer) {
 return new WP_Error('not_found', 'Customer not found');
 }

 $window_days = 30;
 $config = $wpdb->get_row("SELECT customer_active_window_days FROM {$config_table} WHERE id = 1");
 if ($config && !empty($config->customer_active_window_days)) {
 $window_days = (int) $config->customer_active_window_days;
 }

 $last_paid = null;
 $payments_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_payments}'");
 if ($payments_exists) {
 $payment = $wpdb->get_row($wpdb->prepare(
 "SELECT MAX(p.payment_date) AS last_paid
 FROM {$table_payments} p
 INNER JOIN {$table_invoices} i ON i.id = p.invoice_id
 WHERE i.customer_id = %d
 AND i.workflow_status = 'posted'
 AND i.payment_status IN ('paid', 'partial')
 AND p.payment_date >= DATE_SUB(CURDATE, INTERVAL %d DAY)",
 $customer_id,
 $window_days
 ));
 $last_paid = ($payment && $payment->last_paid) ? $payment->last_paid: null;
 }

 if (!$last_paid) {
 $invoice = $wpdb->get_row($wpdb->prepare(
 "SELECT MAX(COALESCE(last_payment_date, DATE(paid_at), transaction_date)) AS last_paid
 FROM {$table_invoices}
 WHERE customer_id = %d
 AND payment_status IN ('paid', 'partial')
 AND workflow_status = 'posted'
 AND COALESCE(last_payment_date, DATE(paid_at), transaction_date) >= DATE_SUB(CURDATE, INTERVAL %d DAY)",
 $customer_id,
 $window_days
 ));
 $last_paid = ($invoice && $invoice->last_paid) ? $invoice->last_paid: null;
 }

 $is_active = $last_paid ? 1: 0;

 return self::update_active_status($customer_id, (bool) $is_active, $last_paid);
 }

 /**
 * Update customer is_active status — system use only (invoice-driven via recompute_active_status).
 */
 public static function update_active_status($customer_id, $is_active, $last_paid_invoice_date = null) {
 global $wpdb;

 $table = OraBooks_Database::table('customers');
 $customer = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table} WHERE id = %d",
 $customer_id
 ));

 if (!$customer) {
 return new WP_Error('not_found', 'Customer not found');
 }

 $old_status = (bool) $customer->is_active;

 $update_data = ['is_active' => $is_active ? 1: 0];
 $update_format = ['%d'];
 if ($last_paid_invoice_date !== null) {
 $update_data['last_paid_invoice_date'] = $last_paid_invoice_date;
 $update_format[] = '%s';
 }

 $wpdb->update(
 $table,
 $update_data,
 ['id' => $customer_id],
 $update_format,
 ['%d']
 );

 // Synchronize the commission engine's customer_active_status read model
 if (
 !empty($customer->user_id)
 && class_exists('OraBooks_Commission')
 && method_exists('OraBooks_Commission', 'refresh_customer_active_status')
 ) {
 OraBooks_Commission::refresh_customer_active_status((int) $customer->user_id);
 }

 // Audit log
 if ($old_status !== (bool) $is_active) {
 $customer_ref = !empty($customer->user_id)
 ? (int) $customer->user_id
: (int) $customer->id;

 orabooks_log_event(
 $is_active ? 'customer_activated': 'customer_deactivated',
 "Customer #{$customer_ref} ". ($is_active ? 'activated': 'deactivated'),
 'info',
 ['customer_id' => $customer->id, 'user_id' => $customer->user_id, 'org_id' => $customer->org_id],
 orabooks_get_current_user_id,
 $customer->org_id
 );

 if (!empty($customer->user_id)) {
 do_action('orabooks_customer_active_status_changed', (int) $customer->user_id, (bool) $is_active, $customer->org_id);
 }
 }

 return true;
 }

 // ================================================================
 // INVOICE METHODS
 // ================================================================

 /**
 * Create a new invoice.
 */
 public static function create_invoice($org_id, $data) {
 global $wpdb;

 $table = OraBooks_Database::table('invoices');

 // Validate required fields
 if (empty($data['customer_id'])) {
 return new WP_Error('missing_field', 'Customer ID is required');
 }

 $line_items = class_exists('OraBooks_Vendors')
 ? OraBooks_Vendors::parse_line_items($data)
: [];
 if (!empty($line_items)) {
 $subtotal_from_lines = OraBooks_Vendors::calculate_line_items_subtotal($line_items, 'unit_price');
 if ($subtotal_from_lines > 0) {
 $data['subtotal_amount'] = $subtotal_from_lines;
 }
 }

 $credit_check = self::validate_customer_credit_for_invoice((object) [
 'customer_id' => (int) $data['customer_id'],
 'total_amount' => (float) ($data['total_amount'] ?? $data['subtotal_amount'] ?? 0),
 ]);
 if (is_wp_error($credit_check)) {
 return $credit_check;
 }

 if (empty($data['total_amount']) || $data['total_amount'] <= 0) {
 if (!empty($data['subtotal_amount']) && floatval($data['subtotal_amount']) > 0) {
 $subtotal = round(floatval($data['subtotal_amount']), 2);
 $jurisdiction = strtoupper(sanitize_text_field($data['jurisdiction'] ?? 'US'));

 if (class_exists('OraBooks_Tax')) {
 $tax_result = OraBooks_Tax::calculate([
 'org_id' => $org_id,
 'amount' => $subtotal,
 'jurisdiction' => $jurisdiction,
 'customer_tax_status' => sanitize_text_field($data['customer_tax_status'] ?? 'taxable'),
 ]);

 if (!is_wp_error($tax_result)) {
 $data['tax_amount'] = $tax_result['tax_amount'];
 $data['tax_rate'] = $tax_result['tax_rate'];
 $data['tax_jurisdiction'] = $jurisdiction;
 $data['tax_type'] = $tax_result['tax_type'] ?? 'Sales Tax';
 $data['total_amount'] = round($subtotal + $tax_result['tax_amount'], 2);
 } else {
 $data['total_amount'] = $subtotal;
 $data['tax_amount'] = 0;
 $data['tax_rate'] = 0;
 $data['tax_jurisdiction'] = $jurisdiction;
 $data['tax_type'] = 'Sales Tax';
 }
 } else {
 $data['total_amount'] = $subtotal;
 $data['tax_amount'] = 0;
 $data['tax_rate'] = 0;
 }
 } else {
 return new WP_Error('invalid_amount', 'Total amount must be greater than 0');
 }
 }

 if (empty($data['total_amount']) || $data['total_amount'] <= 0) {
 return new WP_Error('invalid_amount', 'Total amount must be greater than 0');
 }

 // Generate invoice number if not provided
 if (empty($data['invoice_number'])) {
 $data['invoice_number'] = self::generate_invoice_number($org_id);
 }

 // Verify unique invoice number within org
 $existing = $wpdb->get_var($wpdb->prepare(
 "SELECT id FROM {$table} WHERE org_id = %d AND invoice_number = %s",
 $org_id, $data['invoice_number']
 ));
 if ($existing) {
 return new WP_Error('duplicate', 'Invoice number already exists for this organization');
 }

 $invoice_date = $data['invoice_date'] ?? current_time('Y-m-d');
 $due_days = $data['due_days'] ?? 30;

 $wpdb->insert(
 $table,
 [
 'org_id' => $org_id,
 'customer_id' => (int) $data['customer_id'],
 'invoice_number' => $data['invoice_number'],
 'invoice_date' => $invoice_date,
 'transaction_date' => $data['transaction_date'] ?? $invoice_date,
 'due_date' => $data['due_date'] ?? date('Y-m-d', strtotime($invoice_date. " +{$due_days} days")),
 'description' => $data['description'] ?? '',
 'total_amount' => $data['total_amount'],
 'tax_amount' => $data['tax_amount'] ?? 0,
 'tax_rate' => $data['tax_rate'] ?? 0,
 'tax_jurisdiction'=> !empty($data['tax_jurisdiction']) ? strtoupper(sanitize_text_field($data['tax_jurisdiction'])): null,
 'tax_type' => !empty($data['tax_type']) ? sanitize_text_field($data['tax_type']): null,
 'tax_override_reason' => !empty($data['tax_override_reason']) ? sanitize_text_field($data['tax_override_reason']): null,
 'tax_override_by' => !empty($data['tax_override_reason']) ? orabooks_get_current_user_id: null,
 'tax_override_at' => !empty($data['tax_override_reason']) ? current_time('mysql'): null,
 'currency' => $data['currency'] ?? 'USD',
 'payment_status' => 'unpaid',
 'workflow_status' => $data['workflow_status'] ?? 'draft',
 'idempotency_key' => $data['idempotency_key'] ?? orabooks_uuid,
 ],
 ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
 );

 $invoice_id = $wpdb->insert_id;

 if (!empty($line_items)) {
 self::save_invoice_line_items((int) $org_id, (int) $invoice_id, $line_items);
 }

 // Ensure customer record exists
 $table_customers = OraBooks_Database::table('customers');
 $customer = $wpdb->get_row($wpdb->prepare(
 "SELECT id FROM {$table_customers} WHERE id = %d",
 (int) $data['customer_id']
 ));

 orabooks_log_event('invoice_created', "Invoice #{$data['invoice_number']} created for customer #{$data['customer_id']}", 'info', [
 'invoice_id' => $invoice_id,
 'invoice_number' => $data['invoice_number'],
 'customer_id' => (int) $data['customer_id'],
 'total_amount' => $data['total_amount'],
 'org_id' => $org_id,
 ], orabooks_get_current_user_id, $org_id);

 // Fire event for notification system
 do_action('orabooks_invoice_created', $invoice_id, [
 'customer_id' => (int) $data['customer_id'],
 'invoice_number' => $data['invoice_number'],
 'total_amount' => $data['total_amount'],
 'due_date' => $data['due_date'] ?? date('Y-m-d', strtotime($invoice_date. " +{$due_days} days")),
 'org_id' => $org_id,
 ]);

 if (class_exists('OraBooks_Classification')) {
 OraBooks_Classification::maybe_request('invoice', (int) $invoice_id, (int) $org_id);
 }

 return self::get_invoice($invoice_id);
 }

 public static function format_invoice($invoice) {
 if (!$invoice) {
 return null;
 }

 $formatted = [
 'id' => (int) $invoice->id,
 'org_id' => (int) $invoice->org_id,
 'customer_id' => (int) $invoice->customer_id,
 'invoice_number' => $invoice->invoice_number,
 'invoice_date' => $invoice->invoice_date,
 'due_date' => $invoice->due_date,
 'description' => $invoice->description ?? '',
 'total_amount' => (float) $invoice->total_amount,
 'tax_amount' => isset($invoice->tax_amount) ? (float) $invoice->tax_amount: null,
 'tax_rate' => isset($invoice->tax_rate) ? (float) $invoice->tax_rate: null,
 'tax_jurisdiction'=> $invoice->tax_jurisdiction ?? null,
 'tax_type' => $invoice->tax_type ?? null,
 'tax_override_reason' => $invoice->tax_override_reason ?? null,
 'tax_override_by' => !empty($invoice->tax_override_by) ? (int) $invoice->tax_override_by: null,
 'tax_override_at' => $invoice->tax_override_at ?? null,
 'currency' => $invoice->currency ?? 'USD',
 'payment_status' => $invoice->payment_status,
 'workflow_status' => $invoice->workflow_status,
 'paid_amount' => isset($invoice->total_paid_amount)
 ? (float) $invoice->total_paid_amount
: (float) ($invoice->paid_amount ?? 0),
 'lock_status' => $invoice->lock_status ?? 'unlocked',
 'dunning_stage' => $invoice->dunning_stage ?? 'none',
 'journal_id' => !empty($invoice->journal_id) ? (int) $invoice->journal_id: null,
 'approved_by' => !empty($invoice->approved_by) ? (int) $invoice->approved_by: null,
 'approved_at' => $invoice->approved_at ?? null,
 'posted_at' => $invoice->posted_at ?? null,
 'rendered_copy' => !empty($invoice->rendered_copy)
 ? (is_string($invoice->rendered_copy) ? json_decode($invoice->rendered_copy, true): $invoice->rendered_copy)
: null,
 ];

 if (class_exists('OraBooks_Classification')) {
 $formatted['classification'] = OraBooks_Classification::format_classification($invoice);
 }

 if (!empty($invoice->payments)) {
 $formatted['payments'] = array_map(static function ($payment) {
 $type = $payment->type ?? 'payment';
 return [
 'id' => (int) $payment->id,
 'amount' => (float) $payment->amount,
 'payment_date' => $payment->payment_date,
 'payment_method' => $payment->payment_method,
 'type' => $type,
 'reference' => $payment->reference ?? '',
 'notes' => $payment->notes ?? '',
 'reverses_payment_id' => !empty($payment->reverses_payment_id) ? (int) $payment->reverses_payment_id: null,
 'can_reverse' => $type === 'payment' && (float) $payment->amount > 0,
 ];
 }, $invoice->payments);
 }

 return $formatted;
 }

 public static function override_invoice_tax($org_id, $invoice_id, $new_tax_rate, $reason_code, $user_id, $jurisdiction = 'US') {
 global $wpdb;

 $org_id = intval($org_id);
 $invoice_id = intval($invoice_id);
 $user_id = intval($user_id);
 $new_tax_rate = round(floatval($new_tax_rate), 4);
 $reason_code = sanitize_text_field($reason_code);
 $jurisdiction = strtoupper(sanitize_text_field($jurisdiction ?: 'US'));

 $table = OraBooks_Database::table('invoices');
 $invoice = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table} WHERE id = %d AND org_id = %d",
 $invoice_id,
 $org_id
 ));

 if (!$invoice) {
 return new WP_Error('not_found', 'Invoice not found');
 }

 if (!in_array($invoice->workflow_status, ['draft', 'sent'], true)) {
 return new WP_Error('invalid_status', 'Tax can only be overridden before posting');
 }

 if (class_exists('OraBooks_Tax') && OraBooks_Tax::is_tax_locked($org_id, [
 'transaction_date' => $invoice->invoice_date ?? current_time('Y-m-d'),
 ])) {
 return new WP_Error('tax_locked', 'Tax is locked for this transaction period');
 }

 if (class_exists('OraBooks_Tax')) {
 $validation = OraBooks_Tax::validate_override($org_id, $jurisdiction, $new_tax_rate, $reason_code);
 if (is_wp_error($validation)) {
 return $validation;
 }
 }

 $tax_base = self::get_invoice_tax_base($invoice);
 $old_tax_rate = isset($invoice->tax_rate) ? floatval($invoice->tax_rate): self::infer_tax_rate($invoice);
 $old_tax_amount = floatval($invoice->tax_amount ?? 0);
 $new_tax_amount = round($tax_base * ($new_tax_rate / 100), 2);
 $new_total = round($tax_base + $new_tax_amount, 2);

 $wpdb->update(
 $table,
 [
 'tax_rate' => $new_tax_rate,
 'tax_amount' => $new_tax_amount,
 'total_amount' => $new_total,
 'tax_jurisdiction' => $jurisdiction,
 'tax_override_reason' => $reason_code,
 'tax_override_by' => $user_id,
 'tax_override_at' => current_time('mysql'),
 ],
 ['id' => $invoice_id, 'org_id' => $org_id],
 ['%f', '%f', '%f', '%s', '%s', '%d', '%s'],
 ['%d', '%d']
 );

 orabooks_log_event('tax_override', 'Invoice tax overridden', 'info', [
 'transaction_type' => 'invoice',
 'transaction_id' => $invoice_id,
 'old_tax_rate' => $old_tax_rate,
 'new_tax_rate' => $new_tax_rate,
 'old_tax_amount' => $old_tax_amount,
 'new_tax_amount' => $new_tax_amount,
 'reason_code' => $reason_code,
 ], $user_id, $org_id);

 return [
 'invoice_id' => $invoice_id,
 'tax_rate' => $new_tax_rate,
 'tax_amount' => $new_tax_amount,
 'total_amount' => $new_total,
 'tax_override_reason' => $reason_code,
 'tax_override_by' => $user_id,
 ];
 }

 /**
 * Clear manual tax override and recalculate from ( §5.4).
 */
 public static function clear_invoice_tax_override($org_id, $invoice_id, $user_id, $jurisdiction = 'US') {
 global $wpdb;

 $org_id = (int) $org_id;
 $invoice_id = (int) $invoice_id;
 $user_id = (int) $user_id;
 $jurisdiction = strtoupper(sanitize_text_field($jurisdiction ?: 'US'));

 $table = OraBooks_Database::table('invoices');
 $invoice = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table} WHERE id = %d AND org_id = %d",
 $invoice_id,
 $org_id
 ));

 if (!$invoice) {
 return new WP_Error('not_found', 'Invoice not found');
 }

 if (!in_array($invoice->workflow_status, ['draft', 'sent'], true)) {
 return new WP_Error('invalid_status', 'Override can only be cleared before posting');
 }

 if (class_exists('OraBooks_Tax') && OraBooks_Tax::is_tax_locked($org_id, [
 'transaction_date' => $invoice->invoice_date ?? current_time('Y-m-d'),
 ])) {
 return new WP_Error('tax_locked', 'Tax is locked for this transaction period');
 }

 $tax_base = self::get_invoice_tax_base($invoice);
 $tax_rate = 0.0;
 $tax_amount = 0.0;
 $total = $tax_base;
 $tax_type = $invoice->tax_type ?? 'Sales Tax';

 if (class_exists('OraBooks_Tax') && $tax_base > 0) {
 $calc = OraBooks_Tax::calculate([
 'org_id' => $org_id,
 'amount' => $tax_base,
 'jurisdiction' => $jurisdiction,
 ]);
 if (!is_wp_error($calc)) {
 $tax_rate = (float) ($calc['tax_rate'] ?? 0);
 $tax_amount = (float) ($calc['tax_amount'] ?? 0);
 $tax_type = $calc['tax_type'] ?? $tax_type;
 $total = round($tax_base + $tax_amount, 2);
 }
 }

 $wpdb->update(
 $table,
 [
 'tax_rate' => $tax_rate,
 'tax_amount' => $tax_amount,
 'total_amount' => $total,
 'tax_jurisdiction' => $jurisdiction,
 'tax_type' => $tax_type,
 'tax_override_reason' => null,
 'tax_override_by' => null,
 'tax_override_at' => null,
 ],
 ['id' => $invoice_id, 'org_id' => $org_id],
 ['%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s'],
 ['%d', '%d']
 );

 orabooks_log_event('tax_override_cleared', 'Invoice tax override cleared', 'info', [
 'transaction_type' => 'invoice',
 'transaction_id' => $invoice_id,
 ], $user_id, $org_id);

 return self::format_invoice(self::get_invoice($invoice_id));
 }

 /**
 * Send invoice to customer (draft → sent, ).
 */
 public static function send_invoice($org_id, $invoice_id, $user_id) {
 global $wpdb;

 $org_id = (int) $org_id;
 $invoice_id = (int) $invoice_id;
 $user_id = (int) $user_id;

 $invoice = self::get_invoice($invoice_id);
 if (!$invoice || (int) $invoice->org_id !== $org_id) {
 return new WP_Error('not_found', 'Invoice not found');
 }

 if ($invoice->workflow_status !== 'draft') {
 return new WP_Error('invalid_status', 'Only draft invoices can be sent');
 }

 $credit_check = self::validate_customer_credit_for_invoice($invoice);
 if (is_wp_error($credit_check)) {
 return $credit_check;
 }

 if (!class_exists('OraBooks_Workflow')) {
 return new WP_Error('workflow_unavailable', 'Workflow engine unavailable');
 }

 $result = OraBooks_Workflow::transition('invoice', $invoice_id, 'send', [
 'user_id' => $user_id,
 'org_id' => $org_id,
 ]);
 if (is_wp_error($result)) {
 return $result;
 }

 orabooks_log_event('invoice_sent', "Invoice {$invoice->invoice_number} sent", 'info', [
 'invoice_id' => $invoice_id,
 ], $user_id, $org_id);

 do_action('orabooks_invoice_sent', $invoice_id, [
 'org_id' => $org_id,
 'customer_id' => (int) $invoice->customer_id,
 'invoice_number' => $invoice->invoice_number,
 'total_amount' => floatval($invoice->total_amount),
 ]);

 return self::get_invoice($invoice_id);
 }

 /**
 * Post invoice to AR (draft/sent → posted, ).
 */
 public static function post_invoice($org_id, $invoice_id, $user_id) {
 global $wpdb;

 $org_id = (int) $org_id;
 $invoice_id = (int) $invoice_id;
 $user_id = (int) $user_id;

 $invoice = self::get_invoice($invoice_id);
 if (!$invoice || (int) $invoice->org_id !== $org_id) {
 return new WP_Error('not_found', 'Invoice not found');
 }

 if (!in_array($invoice->workflow_status, ['draft', 'sent'], true)) {
 return new WP_Error('invalid_status', 'Only draft or sent invoices can be posted');
 }

 $credit_check = self::validate_customer_credit_for_invoice($invoice);
 if (is_wp_error($credit_check)) {
 return $credit_check;
 }

 $inventory_items = self::get_inventory_items_for_invoice($invoice_id, $org_id);
 if (!empty($inventory_items) && class_exists('OraBooks_Inventory')) {
 $stock_check = OraBooks_Inventory::validate_sale_items($org_id, $inventory_items);
 if (is_wp_error($stock_check)) {
 return $stock_check;
 }
 }

 $journal_id = self::create_invoice_journal($invoice, $user_id);
 if (is_wp_error($journal_id)) {
 return $journal_id;
 }

 if ($journal_id && class_exists('OraBooks_Posting')) {
 $submit = OraBooks_Posting::submit_journal((int) $journal_id, $user_id);
 if (is_wp_error($submit)) {
 return $submit;
 }
 }

 if (!class_exists('OraBooks_Workflow')) {
 return new WP_Error('workflow_unavailable', 'Workflow engine unavailable');
 }

 $rendered = class_exists('OraBooks_AR')
 ? OraBooks_AR::build_invoice_rendered_copy($invoice)
: null;

 $result = OraBooks_Workflow::transition('invoice', $invoice_id, 'post', [
 'user_id' => $user_id,
 'org_id' => $org_id,
 'row_updates' => [
 'lock_status' => 'locked',
 'journal_id' => is_numeric($journal_id) ? (int) $journal_id: null,
 'posted_at' => current_time('mysql'),
 'rendered_copy' => $rendered ? wp_json_encode($rendered): null,
 ],
 ]);
 if (is_wp_error($result)) {
 return $result;
 }

 if (class_exists('OraBooks_Tax')
 && (floatval($invoice->tax_amount) > 0 || !empty($invoice->tax_override_reason))) {
 OraBooks_Tax::snapshot_for_invoice($invoice, $user_id);
 }

 if (class_exists('OraBooks_AR')) {
 OraBooks_AR::apply_customer_credit_to_invoice($org_id, (int) $invoice->customer_id, $invoice_id, $user_id);
 }

 orabooks_log_event('invoice_posted', "Invoice {$invoice->invoice_number} posted", 'info', [
 'invoice_id' => $invoice_id,
 'journal_id' => $journal_id,
 ], $user_id, $org_id);

 do_action('orabooks_invoice_posted', $invoice_id, [
 'org_id' => $org_id,
 'customer_id' => (int) $invoice->customer_id,
 'invoice_number' => $invoice->invoice_number,
 'total_amount' => floatval($invoice->total_amount),
 'journal_id' => $journal_id,
 'inventory_items' => $inventory_items,
 'user_id' => $user_id,
 ]);

 return self::get_invoice($invoice_id);
 }

 public static function save_invoice_line_items($org_id, $invoice_id, array $lines) {
 global $wpdb;

 $table = OraBooks_Database::table('invoice_line_items');
 $wpdb->delete($table, ['invoice_id' => (int) $invoice_id, 'org_id' => (int) $org_id], ['%d', '%d']);

 $sort = 0;
 foreach ($lines as $line) {
 if (!is_array($line)) {
 continue;
 }
 $qty = round(floatval($line['quantity'] ?? 0), 4);
 if ($qty <= 0) {
 continue;
 }
 $unit_price = round(floatval($line['unit_price'] ?? $line['unit_cost'] ?? 0), 6);
 $line_total = round(floatval($line['line_total'] ?? ($qty * $unit_price)), 2);
 $wpdb->insert(
 $table,
 [
 'org_id' => (int) $org_id,
 'invoice_id' => (int) $invoice_id,
 'product_id' => !empty($line['product_id']) ? (int) $line['product_id']: null,
 'description' => isset($line['description']) ? sanitize_textarea_field($line['description']): null,
 'quantity' => $qty,
 'unit_price' => $unit_price,
 'line_total' => $line_total,
 'sort_order' => $sort++,
 ],
 ['%d', '%d', '%d', '%s', '%f', '%f', '%f', '%d']
 );
 }
 }

 public static function get_invoice_line_items($invoice_id, $org_id) {
 global $wpdb;

 return $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM ". OraBooks_Database::table('invoice_line_items'). "
 WHERE invoice_id = %d AND org_id = %d
 ORDER BY sort_order ASC, id ASC",
 (int) $invoice_id,
 (int) $org_id
 )) ?: [];
 }

 public static function get_inventory_items_for_invoice($invoice_id, $org_id) {
 $items = [];
 foreach (self::get_invoice_line_items($invoice_id, $org_id) as $line) {
 if (empty($line->product_id)) {
 continue;
 }
 $items[] = [
 'product_id' => (int) $line->product_id,
 'quantity' => floatval($line->quantity),
 ];
 }
 return $items;
 }

 /**
 * Cancel an invoice (draft/sent → cancelled, / ).
 */
 public static function cancel_invoice($org_id, $invoice_id, $user_id, $reason = null) {
 $org_id = (int) $org_id;
 $invoice_id = (int) $invoice_id;
 $user_id = (int) $user_id;

 $invoice = self::get_invoice($invoice_id);
 if (!$invoice || (int) $invoice->org_id !== $org_id) {
 return new WP_Error('not_found', 'Invoice not found');
 }

 if (!in_array($invoice->workflow_status, ['draft', 'sent'], true)) {
 return new WP_Error('invalid_status', 'Only draft or sent invoices can be cancelled');
 }

 if ($invoice->workflow_status === 'cancelled') {
 return new WP_Error('already_cancelled', 'Invoice is already cancelled');
 }

 $paid_amount = (float) ($invoice->paid_amount ?? 0);
 if ($paid_amount > 0 || in_array($invoice->payment_status, ['paid', 'partial'], true)) {
 return new WP_Error('has_payments', 'Cannot cancel an invoice with recorded payments');
 }

 if (!class_exists('OraBooks_Workflow')) {
 return new WP_Error('workflow_unavailable', 'Workflow engine unavailable');
 }

 $result = OraBooks_Workflow::transition('invoice', $invoice_id, 'cancel', [
 'user_id' => $user_id,
 'org_id' => $org_id,
 'reason' => $reason,
 'row_updates' => [
 'payment_status' => 'cancelled',
 ],
 ]);
 if (is_wp_error($result)) {
 return $result;
 }

 orabooks_log_event('invoice_cancelled', "Invoice {$invoice->invoice_number} cancelled", 'info', [
 'invoice_id' => $invoice_id,
 'reason' => $reason,
 ], $user_id, $org_id);

 do_action('orabooks_invoice_cancelled', $invoice_id, [
 'org_id' => $org_id,
 'customer_id' => (int) $invoice->customer_id,
 'invoice_number' => $invoice->invoice_number,
 'reason' => $reason,
 ]);

 return self::get_invoice($invoice_id);
 }

 /**
 * AR journal on invoice post: Dr AR, Cr Revenue (+ tax liability when configured).
 */
 private static function create_invoice_journal($invoice, $user_id) {
 if (!class_exists('OraBooks_Posting')) {
 return null;
 }

 $org_id = (int) $invoice->org_id;
 $total = round(floatval($invoice->total_amount), 2);
 $tax = round(floatval($invoice->tax_amount ?? 0), 2);
 $revenue = round(max(0, $total - $tax), 2);

 $ar_code = '1100';
 if (class_exists('OraBooks_COA') && !OraBooks_COA::get_account_by_code($org_id, $ar_code)) {
 $ar_code = '1000';
 }

 $journal_id = OraBooks_Posting::create_journal([
 'org_id' => $org_id,
 'transaction_date' => $invoice->transaction_date ?? $invoice->invoice_date,
 'source_type' => 'invoice',
 'source_id' => (int) $invoice->id,
 'idempotency_key' => 'invoice_post_'. (int) $invoice->id,
 'metadata' => [
 'invoice_number' => $invoice->invoice_number,
 'customer_id' => (int) $invoice->customer_id,
 ],
 ], (int) $user_id);

 if (is_wp_error($journal_id)) {
 return $journal_id;
 }

 $lines = [
 [
 'account_code' => $ar_code,
 'debit' => $total,
 'credit' => 0,
 'description' => 'AR for invoice '. $invoice->invoice_number,
 ],
 [
 'account_code' => '4000',
 'debit' => 0,
 'credit' => $revenue > 0 ? $revenue: $total,
 'description' => 'Revenue for invoice '. $invoice->invoice_number,
 ],
 ];

 if ($tax > 0 && class_exists('OraBooks_COA') && OraBooks_COA::get_account_by_code($org_id, '2100')) {
 $lines[1]['credit'] = $revenue;
 $lines[] = [
 'account_code' => '2100',
 'debit' => 0,
 'credit' => $tax,
 'description' => 'Tax on invoice '. $invoice->invoice_number,
 ];
 }

 OraBooks_Posting::add_lines($journal_id, $lines);

 return $journal_id;
 }

 private static function get_invoice_tax_base($invoice) {
 $total = floatval($invoice->total_amount ?? 0);
 $tax = floatval($invoice->tax_amount ?? 0);
 return max(0, round($total - $tax, 2));
 }

 private static function infer_tax_rate($invoice) {
 $tax_base = self::get_invoice_tax_base($invoice);
 if ($tax_base <= 0) {
 return 0.0;
 }

 return round((floatval($invoice->tax_amount ?? 0) / $tax_base) * 100, 4);
 }

 /**
 * Get a single invoice by ID.
 */
 public static function get_invoice($invoice_id) {
 global $wpdb;

 $table = OraBooks_Database::table('invoices');
 $table_customers = OraBooks_Database::table('customers');
 $table_users = OraBooks_Database::table('users');
 $table_payments = OraBooks_Database::table('payments');

 $invoice = $wpdb->get_row($wpdb->prepare(
 "SELECT i.*, c.user_id as customer_user_id,
 COALESCE(NULLIF(c.contact_email, ''), u.email) as customer_email,
 COALESCE(NULLIF(c.display_name, ''), u.email, c.contact_email) as customer_name
 FROM {$table} i
 JOIN {$table_customers} c ON i.customer_id = c.id
 LEFT JOIN {$table_users} u ON c.user_id = u.id
 WHERE i.id = %d",
 $invoice_id
 ));

 if (!$invoice) {
 return null;
 }

 // Get payments for this invoice
 $invoice->payments = $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM {$table_payments} WHERE invoice_id = %d ORDER BY payment_date DESC",
 $invoice_id
 ));

 return $invoice;
 }

 /**
 * List invoices with filters.
 * Pass org_id = 0 for global admin view (all orgs).
 */
 public static function get_invoices_list($org_id, $args = []) {
 global $wpdb;

 self::maybe_ensure_schema;

 $table = OraBooks_Database::table('invoices');
 $table_customers = OraBooks_Database::table('customers');
 $table_users = OraBooks_Database::table('users');
 $table_orgs = OraBooks_Database::table('organizations');

 $where = '1=1';
 $params = [];

 if ($org_id > 0) {
 $where = 'i.org_id = %d';
 $params[] = $org_id;
 }

 if (!empty($args['customer_id'])) {
 $where.= ' AND i.customer_id = %d';
 $params[] = (int) $args['customer_id'];
 }

 if (!empty($args['payment_status'])) {
 $where.= ' AND i.payment_status = %s';
 $params[] = $args['payment_status'];
 }

 if (!empty($args['workflow_status'])) {
 $where.= ' AND i.workflow_status = %s';
 $params[] = $args['workflow_status'];
 }

 if (!empty($args['from_date'])) {
 $where.= ' AND i.transaction_date >= %s';
 $params[] = $args['from_date'];
 }

 if (!empty($args['to_date'])) {
 $where.= ' AND i.transaction_date <= %s';
 $params[] = $args['to_date'];
 }

 $limit = $args['limit'] ?? 50;
 $offset = $args['offset'] ?? 0;

 $table_payments = OraBooks_Database::table('payments');
 $sql = "SELECT i.*,
 COALESCE(NULLIF(c.contact_email, ''), u.email) as customer_email,
 COALESCE(NULLIF(c.display_name, ''), u.email, c.contact_email) as customer_name,
 o.name as org_name,
 (SELECT COALESCE(SUM(amount), 0) FROM {$table_payments} WHERE invoice_id = i.id) as total_paid_amount
 FROM {$table} i
 JOIN {$table_customers} c ON i.customer_id = c.id
 LEFT JOIN {$table_users} u ON c.user_id = u.id
 LEFT JOIN {$table_orgs} o ON i.org_id = o.id
 WHERE {$where}
 ORDER BY i.transaction_date DESC
 LIMIT %d OFFSET %d";
 $params[] = $limit;
 $params[] = $offset;

 $results = $wpdb->get_results($wpdb->prepare($sql, $params));

 // Total count
 $count_params = array_slice($params, 0, count($params) - 2);
 $count_sql = "SELECT COUNT(*) FROM {$table} i WHERE {$where}";
 $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $count_params));

 return [
 'invoices' => array_map([self::class, 'format_invoice'], $results ?: []),
 'total' => $total,
 'page' => ($limit > 0) ? floor($offset / $limit) + 1: 1,
 'per_page' => $limit,
 ];
 }

 /**
 * Record a payment against an invoice.
 * Automatically updates invoice payment_status and the customer's
 * last_paid_invoice_date and is_active status.
 */
 public static function record_payment($org_id, $invoice_id, $data) {
 global $wpdb;

 $table_invoices = OraBooks_Database::table('invoices');
 $table_payments = OraBooks_Database::table('payments');
 $table_customers = OraBooks_Database::table('customers');

 $invoice = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table_invoices} WHERE id = %d AND org_id = %d",
 $invoice_id, $org_id
 ));

 if (!$invoice) {
 return new WP_Error('not_found', 'Invoice not found');
 }

 if ($invoice->payment_status === 'cancelled') {
 return new WP_Error('cancelled', 'Cannot pay a cancelled invoice');
 }

 $payment_amount = (float) ($data['amount'] ?? 0);
 if ($payment_amount <= 0) {
 return new WP_Error('invalid_amount', 'Payment amount must be greater than 0');
 }

 $payment_date = $data['payment_date'] ?? current_time('Y-m-d');

 // Record the payment
 $previous_paid = (float) ($invoice->paid_amount ?? 0);
 $wpdb->insert(
 $table_payments,
 [
 'org_id' => $org_id,
 'customer_id' => (int) $invoice->customer_id,
 'invoice_id' => $invoice_id,
 'payment_date' => $payment_date,
 'amount' => $payment_amount,
 'payment_method' => $data['payment_method'] ?? 'bank_transfer',
 'type' => 'payment',
 'reference' => $data['reference'] ?? '',
 'notes' => $data['notes'] ?? '',
 'idempotency_key'=> $data['idempotency_key'] ?? orabooks_uuid,
 ],
 ['%d', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s']
 );

 $payment_id = $wpdb->insert_id;

 $applied_to_invoice = min(
 $payment_amount,
 max(0, round((float) $invoice->total_amount - $previous_paid, 2))
 );
 if ($applied_to_invoice > 0 && class_exists('OraBooks_AR')) {
 OraBooks_AR::insert_allocation_public(
 (int) $org_id,
 (int) $invoice->customer_id,
 (int) $payment_id,
 (int) $invoice_id,
 $applied_to_invoice,
 sanitize_text_field($data['allocation_method'] ?? 'manual')
 );
 }

 $overpayment = max(0, round($previous_paid + $payment_amount - (float) $invoice->total_amount, 2));
 if ($overpayment > 0 && class_exists('OraBooks_AR')) {
 OraBooks_AR::adjust_customer_credit_balance((int) $invoice->customer_id, (int) $org_id, $overpayment);
 }

 $total_paid = min(round($previous_paid + $payment_amount, 2), (float) $invoice->total_amount);

 // Determine new payment status
 if ($total_paid >= $invoice->total_amount) {
 $new_status = 'paid';
 $paid_at = current_time('mysql');
 } elseif ($total_paid > 0) {
 $new_status = 'partial';
 $paid_at = null;
 } else {
 $new_status = 'unpaid';
 $paid_at = null;
 }

 $recorded_by = (int) ($data['recorded_by'] ?? $data['user_id'] ?? 0);
 if (($new_status === 'paid' || $new_status === 'partial')
 && in_array($invoice->workflow_status, ['draft', 'sent'], true)
 && class_exists('OraBooks_Workflow')) {
 OraBooks_Workflow::transition('invoice', (int) $invoice_id, 'post', [
 'org_id' => (int) $org_id,
 'user_id' => $recorded_by,
 ]);
 }

 // Update invoice payment fields (workflow_status handled by engine when applicable)
 $wpdb->update(
 $table_invoices,
 [
 'payment_status' => $new_status,
 'paid_amount' => $total_paid,
 'paid_at' => $paid_at,
 'last_payment_date' => $payment_date,
 ],
 ['id' => $invoice_id],
 ['%s', '%f', '%s', '%s'],
 ['%d']
 );

 // Recompute customer active status from invoice activity
 self::recompute_active_status($invoice->customer_id);

 $customer = $wpdb->get_row($wpdb->prepare(
 "SELECT user_id, is_active FROM {$table_customers} WHERE id = %d",
 $invoice->customer_id
 ));
 $customer_is_active = $customer ? (bool) $customer->is_active: false;

 do_action('orabooks_customer_active_status_changed', $customer->user_id ?? 0, $customer_is_active, $org_id);

 // Create journal entry via posting engine (Dr Cash / Cr AR)
 self::create_payment_journal_for_ar(
 $org_id,
 $payment_id,
 $applied_to_invoice,
 $payment_date,
 $invoice->invoice_number,
 orabooks_get_current_user_id
 );

 if (in_array($new_status, ['paid', 'partial'], true) && class_exists('OraBooks_Tax')) {
 $posted_invoice = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table_invoices} WHERE id = %d AND org_id = %d",
 $invoice_id,
 $org_id
 ));
 if ($posted_invoice) {
 OraBooks_Tax::snapshot_for_invoice($posted_invoice, orabooks_get_current_user_id);
 }
 }

 // Fire event for notification system
 do_action('orabooks_payment_recorded', $payment_id, [
 'invoice_id' => $invoice_id,
 'invoice_number' => $invoice->invoice_number,
 'customer_id' => $invoice->customer_id,
 'customer_user_id' => $customer->user_id ?? 0,
 'amount' => $payment_amount,
 'new_status' => $new_status,
 'payment_date' => $payment_date,
 'org_id' => $org_id,
 ]);

 orabooks_log_event('payment_recorded', "Payment of {$payment_amount} recorded for invoice #{$invoice->invoice_number}", 'info', [
 'payment_id' => $payment_id,
 'invoice_id' => $invoice_id,
 'invoice_number'=> $invoice->invoice_number,
 'amount' => $payment_amount,
 'payment_status'=> $new_status,
 'customer_id' => $invoice->customer_id,
 'org_id' => $org_id,
 ], orabooks_get_current_user_id, $org_id);

 return [
 'payment_id' => $payment_id,
 'invoice_id' => $invoice_id,
 'amount' => $payment_amount,
 'new_status' => $new_status,
 'total_paid' => $total_paid,
 'payment_date' => $payment_date,
 ];
 }

 /**
 * Get customer stats for an organization (for dashboards).
 */
 public static function get_customer_stats($org_id) {
 global $wpdb;

 self::maybe_ensure_schema;

 $table_customers = OraBooks_Database::table('customers');
 $table_invoices = OraBooks_Database::table('invoices');
 $table_payments = OraBooks_Database::table('payments');

 $stats = [];

 // Total customers
 $stats['total_customers'] = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM {$table_customers} WHERE org_id = %d",
 $org_id
 ));

 // Active customers
 $stats['active_customers'] = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM {$table_customers} WHERE org_id = %d AND is_active = 1",
 $org_id
 ));

 // Inactive customers
 $stats['inactive_customers'] = $stats['total_customers'] - $stats['active_customers'];

 // Total invoices
 $stats['total_invoices'] = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM {$table_invoices} WHERE org_id = %d",
 $org_id
 ));

 // Invoices by payment status
 $stats['paid_invoices'] = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM {$table_invoices} WHERE org_id = %d AND payment_status = 'paid'",
 $org_id
 ));
 $stats['unpaid_invoices'] = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM {$table_invoices} WHERE org_id = %d AND payment_status = 'unpaid'",
 $org_id
 ));
 $stats['overdue_invoices'] = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM {$table_invoices} WHERE org_id = %d AND payment_status = 'overdue'",
 $org_id
 ));

 // Total revenue (paid amounts)
 $stats['total_revenue'] = (float) $wpdb->get_var($wpdb->prepare(
 "SELECT COALESCE(SUM(amount), 0) FROM {$table_payments} WHERE org_id = %d",
 $org_id
 ));

 // Outstanding AR (opening balances + unpaid invoice totals)
 $stats['outstanding_ar'] = (float) $wpdb->get_var($wpdb->prepare(
 "SELECT
 COALESCE((SELECT SUM(opening_balance) FROM {$table_customers} WHERE org_id = %d), 0)
 + COALESCE((
 SELECT SUM(total_amount - COALESCE(paid_amount, 0))
 FROM {$table_invoices}
 WHERE org_id = %d AND payment_status IN ('unpaid', 'partial')
 ), 0)",
 $org_id,
 $org_id
 ));

 return $stats;
 }

 // ================================================================
 // CRON JOBS
 // ================================================================

 /**
 * Daily check: mark customers as inactive if they have no paid
 * invoices within the active window. Also recalc lifetime_value.
 */
 public function daily_customer_status_check() {
 global $wpdb;

 $table_customers = OraBooks_Database::table('customers');
 $table_invoices = OraBooks_Database::table('invoices');
 $config_table = OraBooks_Database::table('partner_commission_config');

 $config = $wpdb->get_row("SELECT customer_active_window_days FROM {$config_table} WHERE id = 1");
 $window_days = $config ? (int) $config->customer_active_window_days: 30;

 // Find customers whose last_paid_invoice_date is outside the window
 $inactive_count = $wpdb->query($wpdb->prepare(
 "UPDATE {$table_customers}
 SET is_active = 0
 WHERE is_active = 1
 AND (last_paid_invoice_date IS NULL
 OR last_paid_invoice_date < DATE_SUB(CURDATE, INTERVAL %d DAY))",
 $window_days
 ));

 // Recalculate lifetime_value for all customers
 $wpdb->query(
 "UPDATE {$table_customers} c
 JOIN (
 SELECT customer_id, COALESCE(SUM(total_amount), 0) as total
 FROM {$table_invoices}
 WHERE payment_status IN ('paid', 'partial')
 GROUP BY customer_id
 ) i ON c.id = i.customer_id
 SET c.lifetime_value = i.total"
 );

 orabooks_log_event('customer_status_check',
 "Daily customer status check: {$inactive_count} customers marked inactive",
 'info', ['inactivated' => $inactive_count], null, null);

 // Sync with commission engine read model
 if (class_exists('OraBooks_Commission') && method_exists('OraBooks_Commission', 'refresh_all_customer_active_status')) {
 OraBooks_Commission::refresh_all_customer_active_status;
 }

 return $inactive_count;
 }

 /**
 * Daily check: mark invoices as overdue if past due_date and unpaid/partial.
 */
 public function daily_invoice_overdue_check() {
 global $wpdb;

 $table = OraBooks_Database::table('invoices');

 $overdue_count = $wpdb->query(
 "UPDATE {$table}
 SET payment_status = 'overdue',
 overdue_notified_at = NOW
 WHERE payment_status IN ('unpaid', 'partial')
 AND workflow_status IN ('sent', 'posted')
 AND due_date < CURDATE
 AND overdue_notified_at IS NULL"
 );

 if ($overdue_count > 0) {
 orabooks_log_event('invoice_overdue_check',
 "Daily overdue check: {$overdue_count} invoices marked overdue",
 'info', ['marked_overdue' => $overdue_count], null, null);

 // Fire event for notification system
 do_action('orabooks_invoices_marked_overdue', $overdue_count, []);
 }

 return $overdue_count;
 }

 // ================================================================
 // POSTING ENGINE INTEGRATION
 // ================================================================

 /**
 * Create a journal entry for an invoice payment (Dr Cash / Cr AR).
 */
 public static function create_payment_journal_for_ar($org_id, $payment_id, $amount, $payment_date, $reference, $user_id) {
 $system_user = 0;
 if (!class_exists('OraBooks_Posting') || $amount <= 0) {
 return null;
 }

 if (!class_exists('OraBooks_COA') || !method_exists('OraBooks_COA', 'get_account_by_code')) {
 return null;
 }

 $cash_account = OraBooks_COA::get_account_by_code($org_id, '1000');
 $ar_code = OraBooks_COA::get_account_by_code($org_id, '1100') ? '1100': '1000';
 $ar_account = OraBooks_COA::get_account_by_code($org_id, $ar_code);

 if (!$cash_account || !$ar_account) {
 return null;
 }

 $journal_id = OraBooks_Posting::create_journal([
 'org_id' => $org_id,
 'transaction_date' => $payment_date,
 'source_type' => 'invoice_payment',
 'source_id' => $payment_id,
 'idempotency_key' => 'payment_'. $payment_id,
 'metadata' => [
 'payment_id' => $payment_id,
 'reference' => $reference,
 'amount' => $amount,
 ],
 ], $system_user);

 if (is_wp_error($journal_id)) {
 return $journal_id;
 }

 $description = sprintf('Payment for %s', $reference);
 OraBooks_Posting::add_lines($journal_id, [
 ['account_code' => '1000', 'debit' => (float) $amount, 'credit' => 0, 'description' => $description],
 ['account_code' => $ar_code, 'debit' => 0, 'credit' => (float) $amount, 'description' => $description],
 ]);

 OraBooks_Posting::submit_journal($journal_id, $system_user);
 OraBooks_Posting::approve_journal($journal_id, $system_user);
 OraBooks_Posting::post_journal($journal_id, $system_user);

 global $wpdb;
 $wpdb->update(
 OraBooks_Database::table('payments'),
 ['journal_id' => (int) $journal_id],
 ['id' => (int) $payment_id],
 ['%d'],
 ['%d']
 );

 return $journal_id;
 }

 /**
 * @deprecated Use create_payment_journal_for_ar
 */
 private static function create_payment_journal_entry($org_id, $payment_id, $amount, $payment_date, $invoice_number, $user_id) {
 return self::create_payment_journal_for_ar($org_id, $payment_id, $amount, $payment_date, $invoice_number, $user_id);
 }

 /**
 * Validate customer credit hold and credit limit before creating, sending, or posting an invoice.
 */
 public static function validate_customer_credit_for_invoice($invoice_like) {
 global $wpdb;

 $customer_id = (int) ($invoice_like->customer_id ?? 0);
 if ($customer_id <= 0) {
 return new WP_Error('missing_field', 'Customer ID is required');
 }

 $table = OraBooks_Database::table('customers');
 $fields = self::get_table_column_names($table);
 if (!in_array('credit_hold', $fields, true)) {
 return true;
 }

 $customer = self::get_by_id($customer_id);
 if (!$customer) {
 return new WP_Error('not_found', 'Customer not found');
 }

 if (!empty($customer->credit_hold)) {
 return new WP_Error('credit_hold', 'New invoices blocked until credit hold is removed.');
 }

 $credit_limit = isset($customer->credit_limit) ? (float) $customer->credit_limit: 0.0;
 if ($credit_limit <= 0) {
 return true;
 }

 $table_invoices = OraBooks_Database::table('invoices');
 $invoice_id = (int) ($invoice_like->id ?? 0);
 if ($invoice_id > 0) {
 $outstanding = (float) $wpdb->get_var($wpdb->prepare(
 "SELECT COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0)
 FROM {$table_invoices}
 WHERE customer_id = %d
 AND payment_status IN ('unpaid', 'partial', 'overdue')
 AND workflow_status != 'cancelled'
 AND id != %d",
 $customer_id,
 $invoice_id
 ));
 $projected = $outstanding + (float) ($invoice_like->total_amount ?? 0) - (float) ($invoice_like->paid_amount ?? 0);
 } else {
 $outstanding = (float) $wpdb->get_var($wpdb->prepare(
 "SELECT COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0)
 FROM {$table_invoices}
 WHERE customer_id = %d
 AND payment_status IN ('unpaid', 'partial', 'overdue')
 AND workflow_status IN ('sent', 'posted', 'approved', 'submitted')",
 $customer_id
 ));
 $projected = $outstanding + (float) ($invoice_like->total_amount ?? 0);
 }

 if ($projected > $credit_limit) {
 return new WP_Error(
 'credit_limit_exceeded',
 sprintf('Invoice would exceed customer credit limit of %s.', number_format($credit_limit, 2))
 );
 }

 return true;
 }

 /**
 * Generate a unique invoice number for an organization.
 */
 private static function generate_invoice_number($org_id) {
 global $wpdb;

 $table = OraBooks_Database::table('invoices');
 $year = date('Y');
 $month = date('m');

 $max_num = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '-', -1) AS UNSIGNED))
 FROM {$table}
 WHERE org_id = %d AND invoice_number LIKE %s",
 $org_id,
 "INV-{$year}{$month}-%"
 ));

 $next_num = str_pad($max_num + 1, 4, '0', STR_PAD_LEFT);
 return "INV-{$year}{$month}-{$next_num}";
 }

 // ================================================================
 // AJAX HANDLERS
 // ================================================================

 private function require_customer_access($user_id, $org_id, $permission = 'view_invoices') {
 if (!$user_id) {
 orabooks_json_error('Not authenticated', 401);
 }

 $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
 if (is_wp_error($isolation)) {
 orabooks_json_error($isolation->get_error_message, 403);
 }

 if (!current_user_can('manage_options') && !OraBooks_RBAC::require_permission($user_id, $org_id, $permission)) {
 orabooks_json_error('Permission denied', 403);
 }
 }

 private function resolve_request_org_id($user_id, $org_id) {
 $org_id = (int) $org_id;
 if ($org_id > 0) {
 return $org_id;
 }

 if (function_exists('orabooks_get_current_org_id')) {
 $current_org_id = (int) orabooks_get_current_org_id($user_id);
 if ($current_org_id > 0) {
 return $current_org_id;
 }
 }

 global $wpdb;
 $table_users = OraBooks_Database::table('users');
 return (int) $wpdb->get_var($wpdb->prepare(
 "SELECT org_id FROM {$table_users} WHERE id = %d",
 $user_id
 ));
 }

 /**
 * List customers for admin.
 */
 public function ajax_customers_list() {
 $user_id = orabooks_get_current_user_id;
 $org_id = intval($_GET['org_id'] ?? $_POST['org_id'] ?? 0);
 $org_id = $this->resolve_request_org_id($user_id, $org_id);

 if (!$org_id) {
 orabooks_json_error('Organization ID required', 400);
 }

 $this->require_customer_access($user_id, $org_id, 'view_invoices');

 $args = [
 'is_active' => isset($_GET['is_active']) ? intval($_GET['is_active']): null,
 'search' => sanitize_text_field($_GET['search'] ?? ''),
 'limit' => intval($_GET['limit'] ?? 50),
 'offset' => intval($_GET['offset'] ?? 0),
 ];

 $result = self::get_list($org_id, $args);
 orabooks_json_success($result);
 }

 /**
 * Create a customer profile for the current organization.
 */
 public function ajax_customer_create() {
 $user_id = orabooks_get_current_user_id;
 $org_id = intval($_POST['org_id'] ?? 0);

 if (!$user_id) {
 orabooks_json_error('Not authenticated', 401);
 }

 if (!$org_id) {
 orabooks_json_error('Organization ID required', 400);
 }

 $this->require_customer_access($user_id, $org_id, 'create_invoice');

 $result = self::create_customer($org_id, $_POST);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message, 400);
 }

 orabooks_json_success(['customer' => $result], 'Customer created');
 }

 /**
 * Get a single customer. Accepts either customer_id (customers.id)
 * or user_id (users table ID).
 */
 public function ajax_customer_get() {
 $user_id = orabooks_get_current_user_id;
 if (!$user_id) {
 orabooks_json_error('Not authenticated', 401);
 }

 $customer_id = intval($_GET['customer_id'] ?? 0);
 $user_lookup_id = intval($_GET['user_id'] ?? 0);

 if ($customer_id) {
 $customer = self::get_by_id($customer_id);
 } elseif ($user_lookup_id) {
 $customer = self::get_by_user_id($user_lookup_id);
 } else {
 orabooks_json_error('customer_id or user_id required', 400);
 }

 if (!$customer) {
 orabooks_json_error('Customer not found', 404);
 }

 $this->require_customer_access($user_id, (int) $customer->org_id, 'view_invoices');

 orabooks_json_success($customer);
 }

 /**
 * Update customer notes (is_active is derived from invoice activity per ).
 */
 public function ajax_customer_update() {
 $user_id = orabooks_get_current_user_id;
 $customer_id = intval($_POST['customer_id'] ?? 0);
 if (!$customer_id) {
 orabooks_json_error('Customer ID required', 400);
 }

 $customer = self::get_by_id($customer_id);
 if (!$customer) {
 orabooks_json_error('Customer not found', 404);
 }

 $this->require_customer_access($user_id, (int) $customer->org_id, 'create_invoice');

 if (isset($_POST['is_active'])) {
 orabooks_json_error('Customer active status is derived from invoice activity and cannot be changed manually.', 400);
 }

 global $wpdb;
 $table = OraBooks_Database::table('customers');
 $updates = [];
 $formats = [];

 if (isset($_POST['display_name']) || isset($_POST['name'])) {
 $display_name = sanitize_text_field($_POST['display_name'] ?? $_POST['name'] ?? '');
 if ($display_name === '') {
 orabooks_json_error('Customer name is required', 400);
 }
 $updates['display_name'] = $display_name;
 $formats[] = '%s';
 }

 if (isset($_POST['email']) || isset($_POST['contact_email'])) {
 $contact_email = sanitize_email($_POST['email'] ?? $_POST['contact_email'] ?? '');
 if ($contact_email !== '' && !is_email($contact_email)) {
 orabooks_json_error('Please enter a valid email address', 400);
 }
 if ($contact_email !== '') {
 $existing = $wpdb->get_var($wpdb->prepare(
 "SELECT id FROM {$table}
 WHERE org_id = %d AND id != %d AND contact_email IS NOT NULL AND LOWER(contact_email) = LOWER(%s)
 LIMIT 1",
 (int) $customer->org_id,
 $customer_id,
 $contact_email
 ));
 if ($existing) {
 orabooks_json_error('A customer with this email already exists for your organization.', 400);
 }
 }
 $updates['contact_email'] = $contact_email !== '' ? $contact_email: null;
 $formats[] = '%s';
 }

 if (isset($_POST['notes'])) {
 $updates['notes'] = sanitize_textarea_field($_POST['notes']);
 $formats[] = '%s';
 }

 if (isset($_POST['payment_terms'])) {
 $updates['payment_terms'] = max(0, intval($_POST['payment_terms']));
 $formats[] = '%d';
 }

 if (isset($_POST['default_currency'])) {
 $updates['default_currency'] = strtoupper(sanitize_text_field($_POST['default_currency']));
 $formats[] = '%s';
 }

 if (isset($_POST['credit_limit'])) {
 $updates['credit_limit'] = round(floatval($_POST['credit_limit']), 2);
 $formats[] = '%f';
 }

 if (isset($_POST['credit_hold'])) {
 $updates['credit_hold'] = (int) (bool) $_POST['credit_hold'];
 $formats[] = '%d';
 }

 if (isset($_POST['auto_apply_credit'])) {
 $updates['auto_apply_credit'] = (int) (bool) $_POST['auto_apply_credit'];
 $formats[] = '%d';
 }

 $profile_payload = self::customer_profile_payload($_POST, false);
 $updates = array_merge($updates, $profile_payload['updates']);
 $formats = array_merge($formats, $profile_payload['formats']);

 if (!empty($updates)) {
 $wpdb->update(
 $table,
 $updates,
 ['id' => $customer_id],
 $formats,
 ['%d']
 );
 }

 orabooks_json_success(['customer' => self::get_by_id($customer_id)], 'Customer updated');
 }

 /**
 * List invoices.
 */
 public function ajax_invoices_list() {
 $user_id = orabooks_get_current_user_id;
 $org_id = intval($_GET['org_id'] ?? $_POST['org_id'] ?? 0);

 if (!$org_id) {
 $org_id = $this->resolve_request_org_id($user_id, $org_id);
 }

 if (!$org_id) {
 orabooks_json_error('Organization ID required', 400);
 }

 $this->require_customer_access($user_id, $org_id, 'view_invoices');

 $args = [
 'customer_id' => intval($_GET['customer_id'] ?? 0),
 'payment_status' => sanitize_text_field($_GET['payment_status'] ?? ''),
 'workflow_status' => sanitize_text_field($_GET['workflow_status'] ?? ''),
 'from_date' => sanitize_text_field($_GET['from_date'] ?? ''),
 'to_date' => sanitize_text_field($_GET['to_date'] ?? ''),
 'limit' => intval($_GET['limit'] ?? 50),
 'offset' => intval($_GET['offset'] ?? 0),
 ];

 $result = self::get_invoices_list($org_id, $args);
 orabooks_json_success($result);
 }

 /**
 * Create an invoice.
 */
 public function ajax_invoice_create() {
 global $wpdb;
 $user_id = orabooks_get_current_user_id;
 $org_id = intval($_POST['org_id'] ?? 0);

 if (!$user_id) {
 orabooks_json_error('Not authenticated', 401);
 }

 // Admin global mode: resolve org_id from the customer
 if (!$org_id && !empty($_POST['customer_id'])) {
 $table_customers = OraBooks_Database::table('customers');
 $org_id = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT org_id FROM {$table_customers} WHERE id = %d",
 intval($_POST['customer_id'])
 ));
 }

 if (!$org_id) {
 orabooks_json_error('Organization ID required', 400);
 }

 $this->require_customer_access($user_id, $org_id, 'create_invoice');

 $data = [
 'customer_id' => intval($_POST['customer_id'] ?? 0),
 'invoice_number' => sanitize_text_field($_POST['invoice_number'] ?? ''),
 'invoice_date' => sanitize_text_field($_POST['invoice_date'] ?? ''),
 'transaction_date' => sanitize_text_field($_POST['transaction_date'] ?? ''),
 'due_date' => sanitize_text_field($_POST['due_date'] ?? ''),
 'description' => sanitize_textarea_field($_POST['description'] ?? ''),
 'subtotal_amount' => floatval($_POST['subtotal_amount'] ?? 0),
 'jurisdiction' => sanitize_text_field($_POST['jurisdiction'] ?? 'US'),
 'total_amount' => floatval($_POST['total_amount'] ?? 0),
 'tax_amount' => floatval($_POST['tax_amount'] ?? 0),
 'tax_rate' => floatval($_POST['tax_rate'] ?? 0),
 'currency' => sanitize_text_field($_POST['currency'] ?? 'USD'),
 'workflow_status' => sanitize_text_field($_POST['workflow_status'] ?? 'draft'),
 'due_days' => intval($_POST['due_days'] ?? 30),
 'idempotency_key' => sanitize_text_field($_POST['idempotency_key'] ?? ''),
 ];

 $result = self::create_invoice($org_id, $data);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message, 400);
 }

 orabooks_json_success(['invoice' => self::format_invoice($result)], 'Invoice created');
 }

 /**
 * Get single invoice details.
 */
 public function ajax_invoice_get() {
 $user_id = orabooks_get_current_user_id;
 $invoice_id = intval($_GET['invoice_id'] ?? $_POST['invoice_id'] ?? 0);

 if (!$user_id) {
 orabooks_json_error('Not authenticated', 401);
 }

 if (!$invoice_id) {
 orabooks_json_error('Invoice ID required', 400);
 }

 $invoice = self::get_invoice($invoice_id);
 if (!$invoice) {
 orabooks_json_error('Invoice not found', 404);
 }

 $this->require_customer_access($user_id, (int) $invoice->org_id, 'view_invoices');

 orabooks_json_success(['invoice' => self::format_invoice($invoice)]);
 }

 /**
 * Override invoice tax before posting.
 */
 public function ajax_invoice_override_tax() {
 global $wpdb;

 $user_id = orabooks_get_current_user_id;
 $invoice_id = intval($_POST['invoice_id'] ?? 0);
 $org_id = intval($_POST['org_id'] ?? 0);

 if (!$user_id) {
 orabooks_json_error('Not authenticated', 401);
 }

 if (!$invoice_id) {
 orabooks_json_error('Invoice ID required', 400);
 }

 if (!$org_id) {
 $table_invoices = OraBooks_Database::table('invoices');
 $org_id = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT org_id FROM {$table_invoices} WHERE id = %d",
 $invoice_id
 ));
 }

 if (!$org_id) {
 orabooks_json_error('Organization ID required', 400);
 }

 $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
 if (is_wp_error($isolation)) {
 orabooks_json_error($isolation->get_error_message, 403);
 }

 $can_override = class_exists('OraBooks_Tax')
 ? OraBooks_Tax::user_can_override_tax($user_id, $org_id)
: (current_user_can('manage_options')
 || OraBooks_RBAC::require_permission($user_id, $org_id, 'override_tax'));

 if (!$can_override) {
 orabooks_json_error('Permission denied', 403);
 }

 $result = self::override_invoice_tax(
 $org_id,
 $invoice_id,
 floatval($_POST['new_tax_rate'] ?? 0),
 sanitize_text_field($_POST['reason_code'] ?? ''),
 $user_id,
 sanitize_text_field($_POST['jurisdiction'] ?? 'US')
 );

 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message, 400);
 }

 orabooks_json_success($result, 'Tax override applied');
 }

 /**
 * Clear invoice tax override and recalculate from ( §5.4).
 */
 public function ajax_invoice_clear_tax_override() {
 global $wpdb;

 $user_id = orabooks_get_current_user_id;
 $invoice_id = intval($_POST['invoice_id'] ?? 0);
 $org_id = intval($_POST['org_id'] ?? 0);

 if (!$user_id) {
 orabooks_json_error('Not authenticated', 401);
 }

 if (!$invoice_id) {
 orabooks_json_error('Invoice ID required', 400);
 }

 if (!$org_id) {
 $table_invoices = OraBooks_Database::table('invoices');
 $org_id = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT org_id FROM {$table_invoices} WHERE id = %d",
 $invoice_id
 ));
 }

 if (!$org_id) {
 orabooks_json_error('Organization ID required', 400);
 }

 $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
 if (is_wp_error($isolation)) {
 orabooks_json_error($isolation->get_error_message, 403);
 }

 $can_override = class_exists('OraBooks_Tax')
 ? OraBooks_Tax::user_can_override_tax($user_id, $org_id)
: OraBooks_RBAC::require_permission($user_id, $org_id, 'override_tax');

 if (!$can_override) {
 orabooks_json_error('Permission denied', 403);
 }

 $result = self::clear_invoice_tax_override(
 $org_id,
 $invoice_id,
 $user_id,
 sanitize_text_field($_POST['jurisdiction'] ?? 'US')
 );

 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message, 400);
 }

 orabooks_json_success(['invoice' => $result], 'Tax override cleared');
 }

 /**
 * Send a draft invoice to the customer.
 */
 public function ajax_invoice_send() {
 global $wpdb;
 $user_id = orabooks_get_current_user_id;
 $org_id = intval($_POST['org_id'] ?? 0);
 $invoice_id = intval($_POST['invoice_id'] ?? 0);

 if (!$user_id) {
 orabooks_json_error('Not authenticated', 401);
 }

 if (!$org_id && $invoice_id) {
 $table_invoices = OraBooks_Database::table('invoices');
 $org_id = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT org_id FROM {$table_invoices} WHERE id = %d",
 $invoice_id
 ));
 }

 if (!$org_id || !$invoice_id) {
 orabooks_json_error('Organization and invoice ID required', 400);
 }

 $this->require_customer_access($user_id, $org_id, 'create_invoice');

 $result = self::send_invoice($org_id, $invoice_id, $user_id);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message, 400);
 }

 orabooks_json_success(['invoice' => $result], 'Invoice sent');
 }

 /**
 * Post an invoice to accounts receivable.
 */
 public function ajax_invoice_post() {
 global $wpdb;
 $user_id = orabooks_get_current_user_id;
 $org_id = intval($_POST['org_id'] ?? 0);
 $invoice_id = intval($_POST['invoice_id'] ?? 0);

 if (!$user_id) {
 orabooks_json_error('Not authenticated', 401);
 }

 if (!$org_id && $invoice_id) {
 $table_invoices = OraBooks_Database::table('invoices');
 $org_id = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT org_id FROM {$table_invoices} WHERE id = %d",
 $invoice_id
 ));
 }

 if (!$org_id || !$invoice_id) {
 orabooks_json_error('Organization and invoice ID required', 400);
 }

 $this->require_customer_access($user_id, $org_id, 'create_invoice');

 $result = self::post_invoice($org_id, $invoice_id, $user_id);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message, 400);
 }

 orabooks_json_success(['invoice' => $result], 'Invoice posted');
 }

 /**
 * Cancel a draft or sent invoice.
 */
 public function ajax_invoice_cancel() {
 global $wpdb;
 $user_id = orabooks_get_current_user_id;
 $org_id = intval($_POST['org_id'] ?? 0);
 $invoice_id = intval($_POST['invoice_id'] ?? 0);
 $reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])): null;

 if (!$user_id) {
 orabooks_json_error('Not authenticated', 401);
 }

 if (!$org_id && $invoice_id) {
 $table_invoices = OraBooks_Database::table('invoices');
 $org_id = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT org_id FROM {$table_invoices} WHERE id = %d",
 $invoice_id
 ));
 }

 if (!$org_id || !$invoice_id) {
 orabooks_json_error('Organization and invoice ID required', 400);
 }

 $this->require_customer_access($user_id, $org_id, 'create_invoice');

 $result = self::cancel_invoice($org_id, $invoice_id, $user_id, $reason);
 if (is_wp_error($result)) {
 $status = 400;
 $data = $result->get_error_data;
 if (is_array($data) && isset($data['status'])) {
 $status = (int) $data['status'];
 }
 orabooks_json_error($result->get_error_message, $status);
 }

 orabooks_json_success(['invoice' => $result], 'Invoice cancelled');
 }

 /**
 * Record a payment against an invoice.
 */
 public function ajax_record_payment() {
 global $wpdb;
 $user_id = orabooks_get_current_user_id;
 $org_id = intval($_POST['org_id'] ?? 0);

 if (!$user_id) {
 orabooks_json_error('Not authenticated', 401);
 }

 // Admin global mode: resolve org_id from the invoice
 if (!$org_id && !empty($_POST['invoice_id'])) {
 $table_invoices = OraBooks_Database::table('invoices');
 $org_id = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT org_id FROM {$table_invoices} WHERE id = %d",
 intval($_POST['invoice_id'])
 ));
 }

 if (!$org_id) {
 orabooks_json_error('Organization ID required', 400);
 }

 $this->require_customer_access($user_id, $org_id, 'create_invoice');

 $invoice_id = intval($_POST['invoice_id'] ?? 0);
 if (!$invoice_id) {
 orabooks_json_error('Invoice ID required', 400);
 }

 $data = [
 'amount' => floatval($_POST['amount'] ?? 0),
 'payment_date' => sanitize_text_field($_POST['payment_date'] ?? ''),
 'payment_method' => sanitize_text_field($_POST['payment_method'] ?? 'bank_transfer'),
 'reference' => sanitize_text_field($_POST['reference'] ?? ''),
 'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
 'idempotency_key' => sanitize_text_field($_POST['idempotency_key'] ?? ''),
 ];

 $result = self::record_payment($org_id, $invoice_id, $data);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message, 400);
 }

 orabooks_json_success($result, 'Payment recorded');
 }

 /**
 * Customer stats for admin dashboard.
 */
 public function ajax_customer_stats() {
 $user_id = orabooks_get_current_user_id;
 $org_id = intval($_GET['org_id'] ?? $_POST['org_id'] ?? 0);
 $org_id = $this->resolve_request_org_id($user_id, $org_id);

 if (!$user_id) {
 orabooks_json_error('Not authenticated', 401);
 }

 if ($org_id > 0) {
 $this->require_customer_access($user_id, $org_id, 'view_reports');
 orabooks_json_success(self::get_customer_stats($org_id));
 return;
 }

 if (!current_user_can('manage_options')) {
 orabooks_json_error('Organization ID required', 400);
 }

 // If no org specified, aggregate across all orgs
 if (!$org_id) {
 global $wpdb;
 $table_orgs = OraBooks_Database::table('organizations');
 $orgs = $wpdb->get_col("SELECT id FROM {$table_orgs}");
 $aggregate = [
 'total_customers' => 0,
 'active_customers' => 0,
 'inactive_customers' => 0,
 'total_invoices' => 0,
 'paid_invoices' => 0,
 'unpaid_invoices' => 0,
 'overdue_invoices' => 0,
 'total_revenue' => 0,
 'outstanding_ar' => 0,
 ];
 foreach ($orgs as $oid) {
 $stats = self::get_customer_stats($oid);
 foreach ($aggregate as $key => &$val) {
 $val += $stats[$key] ?? 0;
 }
 }
 orabooks_json_success($aggregate);
 }

 $stats = self::get_customer_stats($org_id);
 orabooks_json_success($stats);
 }
}
