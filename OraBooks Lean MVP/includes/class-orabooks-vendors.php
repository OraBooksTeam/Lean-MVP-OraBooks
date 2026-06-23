<?php
/**
 * OraBooks Vendors / Bills / AP Module
 *
 * Vendor master, bill lifecycle, AP balances, FIFO payments, credit notes,
 * and aging read models.
 */

if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Vendors {

 private static $instance = null;

 public static function init() {
 if (self::$instance === null) {
 self::$instance = new self;
 add_action('wp_ajax_orabooks_vendors_list', [self::$instance, 'ajax_vendors_list']);
 add_action('wp_ajax_nopriv_orabooks_vendors_list', [self::$instance, 'ajax_vendors_list']);
 add_action('wp_ajax_orabooks_vendor_create', [self::$instance, 'ajax_vendor_create']);
 add_action('wp_ajax_nopriv_orabooks_vendor_create', [self::$instance, 'ajax_vendor_create']);
 add_action('wp_ajax_orabooks_vendor_update', [self::$instance, 'ajax_vendor_update']);
 add_action('wp_ajax_nopriv_orabooks_vendor_update', [self::$instance, 'ajax_vendor_update']);
 add_action('wp_ajax_orabooks_bills_list', [self::$instance, 'ajax_bills_list']);
 add_action('wp_ajax_nopriv_orabooks_bills_list', [self::$instance, 'ajax_bills_list']);
 add_action('wp_ajax_orabooks_bill_create', [self::$instance, 'ajax_bill_create']);
 add_action('wp_ajax_nopriv_orabooks_bill_create', [self::$instance, 'ajax_bill_create']);
 add_action('wp_ajax_orabooks_bill_submit', [self::$instance, 'ajax_bill_submit']);
 add_action('wp_ajax_nopriv_orabooks_bill_submit', [self::$instance, 'ajax_bill_submit']);
 add_action('wp_ajax_orabooks_bill_approve', [self::$instance, 'ajax_bill_approve']);
 add_action('wp_ajax_nopriv_orabooks_bill_approve', [self::$instance, 'ajax_bill_approve']);
 add_action('wp_ajax_orabooks_bill_post', [self::$instance, 'ajax_bill_post']);
 add_action('wp_ajax_nopriv_orabooks_bill_post', [self::$instance, 'ajax_bill_post']);
 add_action('wp_ajax_orabooks_bill_void', [self::$instance, 'ajax_bill_void']);
 add_action('wp_ajax_nopriv_orabooks_bill_void', [self::$instance, 'ajax_bill_void']);
 add_action('wp_ajax_orabooks_vendor_payment_record', [self::$instance, 'ajax_record_payment']);
 add_action('wp_ajax_nopriv_orabooks_vendor_payment_record', [self::$instance, 'ajax_record_payment']);
 add_action('wp_ajax_orabooks_vendor_credit_note_create', [self::$instance, 'ajax_create_credit_note']);
 add_action('wp_ajax_nopriv_orabooks_vendor_credit_note_create', [self::$instance, 'ajax_create_credit_note']);
 add_action('wp_ajax_orabooks_ap_aging', [self::$instance, 'ajax_ap_aging']);
 add_action('wp_ajax_nopriv_orabooks_ap_aging', [self::$instance, 'ajax_ap_aging']);
 }
 return self::$instance;
 }

 public static function get_create_table_sql() {
 global $wpdb;

 $charset_collate = $wpdb->get_charset_collate;
 $tables = [];

 $table_vendors = OraBooks_Database::table('vendors');
 $table_bills = OraBooks_Database::table('bills');
 $table_payments = OraBooks_Database::table('vendor_payments');
 $table_allocations = OraBooks_Database::table('vendor_payment_allocations');
 $table_credit_notes = OraBooks_Database::table('vendor_credit_notes');
 $table_configs = OraBooks_Database::table('vendor_ap_configs');
 $table_snapshots = OraBooks_Database::table('vendor_statement_snapshots');

 $tables[] = "CREATE TABLE IF NOT EXISTS {$table_vendors} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 org_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(255) NOT NULL,
 email VARCHAR(255) NULL,
 tax_id VARCHAR(100) NULL,
 payment_terms INT DEFAULT 30,
 default_currency CHAR(3) DEFAULT 'USD',
 auto_apply_credit TINYINT(1) DEFAULT 1,
 payable_balance DECIMAL(20,2) DEFAULT 0,
 credit_balance DECIMAL(20,2) DEFAULT 0,
 notes TEXT NULL,
 is_active TINYINT(1) DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (org_id) REFERENCES {$wpdb->prefix}orabooks_organizations(id) ON DELETE CASCADE,
 INDEX idx_org (org_id),
 INDEX idx_active (is_active)
 ) {$charset_collate};";

 $tables[] = "CREATE TABLE IF NOT EXISTS {$table_bills} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 org_id BIGINT UNSIGNED NOT NULL,
 vendor_id BIGINT UNSIGNED NOT NULL,
 bill_number VARCHAR(50) NOT NULL,
 bill_date DATE NOT NULL,
 transaction_date DATE NOT NULL,
 due_date DATE NOT NULL,
 description TEXT NULL,
 subtotal_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
 tax_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
 total_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
 paid_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
 currency CHAR(3) DEFAULT 'USD',
 exchange_rate DECIMAL(20,8) DEFAULT 1,
 workflow_status ENUM('draft','submitted','approved','posted','void') DEFAULT 'draft',
 payment_status ENUM('unpaid','partial','paid','credited') DEFAULT 'unpaid',
 lock_status ENUM('unlocked','locked') DEFAULT 'unlocked',
 journal_id BIGINT UNSIGNED NULL,
 tax_snapshot_id BIGINT UNSIGNED NULL,
 rendered_copy JSON NULL,
 idempotency_key VARCHAR(128),
 created_by BIGINT UNSIGNED NULL,
 approved_by BIGINT UNSIGNED NULL,
 approved_at TIMESTAMP NULL,
 posted_at TIMESTAMP NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (org_id) REFERENCES {$wpdb->prefix}orabooks_organizations(id) ON DELETE CASCADE,
 FOREIGN KEY (vendor_id) REFERENCES {$table_vendors}(id) ON DELETE CASCADE,
 UNIQUE KEY uk_org_bill (org_id, bill_number),
 UNIQUE KEY uk_bill_idempotency (idempotency_key),
 INDEX idx_vendor (vendor_id),
 INDEX idx_workflow (workflow_status),
 INDEX idx_payment (payment_status),
 INDEX idx_due_date (due_date)
 ) {$charset_collate};";

 $tables[] = "CREATE TABLE IF NOT EXISTS {$table_payments} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 org_id BIGINT UNSIGNED NOT NULL,
 vendor_id BIGINT UNSIGNED NOT NULL,
 payment_date DATE NOT NULL,
 amount DECIMAL(20,2) NOT NULL,
 unapplied_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
 payment_method ENUM('bank_transfer','credit_card','cash','check','other') DEFAULT 'bank_transfer',
 type ENUM('payment','reversal','refund') DEFAULT 'payment',
 reference VARCHAR(255) NULL,
 notes TEXT NULL,
 reverses_payment_id BIGINT UNSIGNED NULL,
 journal_id BIGINT UNSIGNED NULL,
 idempotency_key VARCHAR(128),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (org_id) REFERENCES {$wpdb->prefix}orabooks_organizations(id) ON DELETE CASCADE,
 FOREIGN KEY (vendor_id) REFERENCES {$table_vendors}(id) ON DELETE CASCADE,
 UNIQUE KEY uk_vendor_payment_idempotency (idempotency_key),
 INDEX idx_vendor_date (vendor_id, payment_date)
 ) {$charset_collate};";

 $tables[] = "CREATE TABLE IF NOT EXISTS {$table_allocations} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 org_id BIGINT UNSIGNED NOT NULL,
 vendor_id BIGINT UNSIGNED NOT NULL,
 payment_id BIGINT UNSIGNED NOT NULL,
 bill_id BIGINT UNSIGNED NOT NULL,
 amount DECIMAL(20,2) NOT NULL,
 allocation_method ENUM('FIFO','manual','auto_credit') DEFAULT 'FIFO',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (org_id) REFERENCES {$wpdb->prefix}orabooks_organizations(id) ON DELETE CASCADE,
 FOREIGN KEY (vendor_id) REFERENCES {$table_vendors}(id) ON DELETE CASCADE,
 FOREIGN KEY (payment_id) REFERENCES {$table_payments}(id) ON DELETE CASCADE,
 FOREIGN KEY (bill_id) REFERENCES {$table_bills}(id) ON DELETE CASCADE,
 INDEX idx_bill (bill_id),
 INDEX idx_vendor (vendor_id)
 ) {$charset_collate};";

 $tables[] = "CREATE TABLE IF NOT EXISTS {$table_credit_notes} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 org_id BIGINT UNSIGNED NOT NULL,
 vendor_id BIGINT UNSIGNED NOT NULL,
 bill_id BIGINT UNSIGNED NULL,
 credit_note_number VARCHAR(50) NOT NULL,
 credit_date DATE NOT NULL,
 amount DECIMAL(20,2) NOT NULL,
 reason TEXT NOT NULL,
 is_adjustment TINYINT(1) DEFAULT 0,
 adjustment_account_code VARCHAR(50) NULL,
 requires_second_approval TINYINT(1) DEFAULT 0,
 workflow_status ENUM('draft','submitted','approved','posted','void') DEFAULT 'draft',
 journal_id BIGINT UNSIGNED NULL,
 created_by BIGINT UNSIGNED NULL,
 approved_by BIGINT UNSIGNED NULL,
 approved_at TIMESTAMP NULL,
 posted_at TIMESTAMP NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (org_id) REFERENCES {$wpdb->prefix}orabooks_organizations(id) ON DELETE CASCADE,
 FOREIGN KEY (vendor_id) REFERENCES {$table_vendors}(id) ON DELETE CASCADE,
 FOREIGN KEY (bill_id) REFERENCES {$table_bills}(id) ON DELETE SET NULL,
 UNIQUE KEY uk_org_credit_note (org_id, credit_note_number),
 INDEX idx_vendor (vendor_id),
 INDEX idx_bill (bill_id),
 INDEX idx_status (workflow_status)
 ) {$charset_collate};";

 $tables[] = "CREATE TABLE IF NOT EXISTS {$table_configs} (
 org_id BIGINT UNSIGNED PRIMARY KEY,
 auto_post_bill_on_approve TINYINT(1) DEFAULT 1,
 auto_apply_vendor_credit TINYINT(1) DEFAULT 1,
 adjustment_threshold DECIMAL(20,2) DEFAULT 1000,
 vendor_adjustment_account VARCHAR(50) DEFAULT '5000',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (org_id) REFERENCES {$wpdb->prefix}orabooks_organizations(id) ON DELETE CASCADE
 ) {$charset_collate};";

 $tables[] = "CREATE TABLE IF NOT EXISTS {$table_snapshots} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 org_id BIGINT UNSIGNED NOT NULL,
 vendor_id BIGINT UNSIGNED NOT NULL,
 statement_month CHAR(7) NOT NULL,
 payable_balance DECIMAL(20,2) NOT NULL DEFAULT 0,
 credit_balance DECIMAL(20,2) NOT NULL DEFAULT 0,
 aging_json JSON NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (org_id) REFERENCES {$wpdb->prefix}orabooks_organizations(id) ON DELETE CASCADE,
 FOREIGN KEY (vendor_id) REFERENCES {$table_vendors}(id) ON DELETE CASCADE,
 UNIQUE KEY uk_vendor_month (vendor_id, statement_month),
 INDEX idx_org_month (org_id, statement_month)
 ) {$charset_collate};";

 $table_bill_lines = OraBooks_Database::table('bill_line_items');
 $tables[] = "CREATE TABLE IF NOT EXISTS {$table_bill_lines} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 org_id BIGINT UNSIGNED NOT NULL,
 bill_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NULL,
 description TEXT NULL,
 quantity DECIMAL(20,4) NOT NULL DEFAULT 0,
 unit_cost DECIMAL(20,6) NOT NULL DEFAULT 0,
 line_total DECIMAL(20,2) NOT NULL DEFAULT 0,
 sort_order INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (org_id) REFERENCES {$wpdb->prefix}orabooks_organizations(id) ON DELETE CASCADE,
 FOREIGN KEY (bill_id) REFERENCES {$table_bills}(id) ON DELETE CASCADE,
 INDEX idx_bill (bill_id),
 INDEX idx_product (product_id)
 ) {$charset_collate};";

 return $tables;
 }

 /**
 * Idempotent line-item table for existing installs ( integration).
 */
 public static function ensure_schema() {
 if (self::get_schema_flag('orabooks_sl027_bill_line_items_v1') === '1') {
 return;
 }

 global $wpdb;
 $table = OraBooks_Database::table('bill_line_items');
 if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
 self::set_schema_flag('orabooks_sl027_bill_line_items_v1', '1');
 return;
 }

 $upgrade = ABSPATH. 'wp-admin/includes/upgrade.php';
 if (!file_exists($upgrade)) {
 return;
 }

 require_once $upgrade;
 foreach (self::get_create_table_sql as $sql) {
 if (strpos($sql, 'bill_line_items') !== false) {
 dbDelta($sql);
 break;
 }
 }

 if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
 self::set_schema_flag('orabooks_sl027_bill_line_items_v1', '1');
 }
 }

 private static function get_schema_flag($key) {
 if (function_exists('is_multisite()') && is_multisite() && function_exists('get_site_option')) {
 return get_site_option($key);
 }
 return get_option($key);
 }

 private static function set_schema_flag($key, $value) {
 if (function_exists('is_multisite()') && is_multisite() && function_exists('update_site_option')) {
 update_site_option($key, $value);
 return;
 }
 update_option($key, $value, false);
 }

 public static function create_vendor($org_id, $data) {
 global $wpdb;

 $org_id = intval($org_id);
 $name = sanitize_text_field($data['name'] ?? '');
 if ($org_id <= 0 || $name === '') {
 return new WP_Error('missing_field', 'Organization and vendor name are required');
 }

 $table = OraBooks_Database::table('vendors');
 $wpdb->insert(
 $table,
 [
 'org_id' => $org_id,
 'name' => $name,
 'email' => !empty($data['email']) ? sanitize_email($data['email']): null,
 'tax_id' => !empty($data['tax_id']) ? sanitize_text_field($data['tax_id']): null,
 'payment_terms' => intval($data['payment_terms'] ?? 30),
 'default_currency' => strtoupper(sanitize_text_field($data['default_currency'] ?? 'USD')),
 'auto_apply_credit' => isset($data['auto_apply_credit']) ? (int) (bool) $data['auto_apply_credit']: 1,
 'notes' => isset($data['notes']) ? sanitize_textarea_field($data['notes']): null,
 'is_active' => 1,
 ],
 ['%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d']
 );

 $vendor_id = intval($wpdb->insert_id);
 orabooks_log_event('vendor_created', "Vendor created: {$name}", 'info', [
 'vendor_id' => $vendor_id,
 ], orabooks_get_current_user_id(), $org_id);

 return self::get_vendor($vendor_id, $org_id);
 }

 public static function update_vendor($org_id, $vendor_id, $data) {
 global $wpdb;

 $org_id = intval($org_id);
 $vendor_id = intval($vendor_id);
 $vendor = self::get_vendor($vendor_id, $org_id);
 if (!$vendor) {
 return new WP_Error('not_found', 'Vendor not found');
 }

 $table = OraBooks_Database::table('vendors');
 $updates = [];
 $formats = [];

 if (isset($data['name'])) {
 $name = sanitize_text_field($data['name']);
 if ($name === '') {
 return new WP_Error('missing_field', 'Vendor name is required');
 }
 $updates['name'] = $name;
 $formats[] = '%s';
 }

 if (array_key_exists('email', $data)) {
 $updates['email'] = !empty($data['email']) ? sanitize_email($data['email']): null;
 $formats[] = '%s';
 }

 if (isset($data['tax_id'])) {
 $updates['tax_id'] = !empty($data['tax_id']) ? sanitize_text_field($data['tax_id']): null;
 $formats[] = '%s';
 }

 if (isset($data['payment_terms'])) {
 $updates['payment_terms'] = max(0, intval($data['payment_terms']));
 $formats[] = '%d';
 }

 if (isset($data['default_currency'])) {
 $updates['default_currency'] = strtoupper(sanitize_text_field($data['default_currency']));
 $formats[] = '%s';
 }

 if (isset($data['auto_apply_credit'])) {
 $updates['auto_apply_credit'] = (int) (bool) $data['auto_apply_credit'];
 $formats[] = '%d';
 }

 if (isset($data['notes'])) {
 $updates['notes'] = sanitize_textarea_field($data['notes']);
 $formats[] = '%s';
 }

 if (empty($updates)) {
 return self::get_vendor($vendor_id, $org_id);
 }

 $wpdb->update(
 $table,
 $updates,
 ['id' => $vendor_id, 'org_id' => $org_id],
 $formats,
 ['%d', '%d']
 );

 orabooks_log_event('vendor_updated', "Vendor updated: {$vendor->name}", 'info', [
 'vendor_id' => $vendor_id,
 ], orabooks_get_current_user_id(), $org_id);

 return self::get_vendor($vendor_id, $org_id);
 }

 public static function get_vendor($vendor_id, $org_id) {
 global $wpdb;

 $table = OraBooks_Database::table('vendors');
 return $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table} WHERE id = %d AND org_id = %d",
 intval($vendor_id),
 intval($org_id)
 ));
 }

 public static function get_vendors_list($org_id, $args = []) {
 global $wpdb;

 $table = OraBooks_Database::table('vendors');
 $where = 'org_id = %d';
 $params = [intval($org_id)];

 if (!empty($args['search'])) {
 $where.= ' AND (name LIKE %s OR email LIKE %s)';
 $search = '%'. $wpdb->esc_like($args['search']). '%';
 $params[] = $search;
 $params[] = $search;
 }

 if (isset($args['is_active'])) {
 $where.= ' AND is_active = %d';
 $params[] = (int) $args['is_active'];
 }

 $limit = intval($args['limit'] ?? 50);
 $offset = intval($args['offset'] ?? 0);
 $params[] = $limit;
 $params[] = $offset;

 $count_params = $params;
 array_pop($count_params);
 array_pop($count_params);
 $total = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM {$table} WHERE {$where}",
 $count_params
 ));

 $vendors = $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM {$table} WHERE {$where} ORDER BY name ASC LIMIT %d OFFSET %d",
 $params
 ));

 return [
 'vendors' => $vendors,
 'total' => $total,
 'page' => ($limit > 0) ? floor($offset / $limit) + 1: 1,
 'per_page' => $limit,
 ];
 }

 public static function create_bill($org_id, $data) {
 global $wpdb;

 $org_id = intval($org_id);
 $vendor_id = intval($data['vendor_id'] ?? 0);
 $line_items = self::parse_line_items($data);
 $subtotal = round(floatval($data['subtotal_amount'] ?? $data['total_amount'] ?? 0), 2);

 if (!empty($line_items)) {
 $subtotal = self::calculate_line_items_subtotal($line_items, 'unit_cost');
 }

 if ($org_id <= 0 || $vendor_id <= 0) {
 return new WP_Error('missing_field', 'Vendor is required');
 }

 $tax_amount = array_key_exists('tax_amount', $data)
 ? round(floatval($data['tax_amount']), 2)
: null;

 if ($subtotal > 0 && $tax_amount === null && class_exists('OraBooks_Tax')) {
 $jurisdiction = strtoupper(sanitize_text_field($data['jurisdiction'] ?? 'US'));
 $tax_result = OraBooks_Tax::calculate([
 'org_id' => $org_id,
 'amount' => $subtotal,
 'jurisdiction' => $jurisdiction,
 ]);

 if (!is_wp_error($tax_result)) {
 $tax_amount = round(floatval($tax_result['tax_amount']), 2);
 } else {
 $tax_amount = 0.0;
 }
 } else {
 $tax_amount = $tax_amount ?? 0.0;
 }

 $total = round(floatval($data['total_amount'] ?? ($subtotal + $tax_amount)), 2);

 if ($total <= 0) {
 return new WP_Error('invalid_amount', 'Bill total must be greater than 0');
 }

 $bill_date = $data['bill_date'] ?? current_time('Y-m-d');
 $due_days = intval($data['due_days'] ?? self::get_vendor_payment_terms($vendor_id, $org_id));
 $bill_number = !empty($data['bill_number']) ? sanitize_text_field($data['bill_number']): self::generate_bill_number($org_id, $bill_date);
 $table = OraBooks_Database::table('bills');

 $existing = $wpdb->get_var($wpdb->prepare(
 "SELECT id FROM {$table} WHERE org_id = %d AND bill_number = %s",
 $org_id,
 $bill_number
 ));
 if ($existing) {
 return new WP_Error('duplicate', 'Bill number already exists for this organization');
 }

 $wpdb->insert(
 $table,
 [
 'org_id' => $org_id,
 'vendor_id' => $vendor_id,
 'bill_number' => $bill_number,
 'bill_date' => $bill_date,
 'transaction_date' => $data['transaction_date'] ?? $bill_date,
 'due_date' => $data['due_date'] ?? date('Y-m-d', strtotime($bill_date. " +{$due_days} days")),
 'description' => isset($data['description']) ? sanitize_textarea_field($data['description']): '',
 'subtotal_amount' => $subtotal,
 'tax_amount' => $tax_amount,
 'total_amount' => $total,
 'paid_amount' => 0,
 'currency' => strtoupper(sanitize_text_field($data['currency'] ?? 'USD')),
 'exchange_rate' => floatval($data['exchange_rate'] ?? 1),
 'workflow_status' => 'draft',
 'payment_status' => 'unpaid',
 'lock_status' => 'unlocked',
 'idempotency_key' => $data['idempotency_key'] ?? orabooks_uuid,
 'created_by' => orabooks_get_current_user_id(),
 'rendered_copy' => !empty($data['rendered_copy']) ? wp_json_encode($data['rendered_copy']): null,
 ],
 ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%s']
 );

 $bill_id = intval($wpdb->insert_id);
 if (!empty($line_items)) {
 self::save_bill_line_items($org_id, $bill_id, $line_items);
 }
 orabooks_log_event('vendor_bill_created', "Bill {$bill_number} created", 'info', [
 'bill_id' => $bill_id,
 'vendor_id' => $vendor_id,
 'total_amount' => $total,
 ], orabooks_get_current_user_id(), $org_id);

 return self::get_bill($bill_id, $org_id);
 }

 public static function get_bill($bill_id, $org_id) {
 global $wpdb;

 $table_bills = OraBooks_Database::table('bills');
 $table_vendors = OraBooks_Database::table('vendors');
 return $wpdb->get_row($wpdb->prepare(
 "SELECT b.*, v.name as vendor_name, v.email as vendor_email
 FROM {$table_bills} b
 JOIN {$table_vendors} v ON b.vendor_id = v.id
 WHERE b.id = %d AND b.org_id = %d",
 intval($bill_id),
 intval($org_id)
 ));
 }

 public static function get_bills_list($org_id, $args = []) {
 global $wpdb;

 $table_bills = OraBooks_Database::table('bills');
 $table_vendors = OraBooks_Database::table('vendors');
 $where = 'b.org_id = %d';
 $params = [intval($org_id)];

 if (!empty($args['vendor_id'])) {
 $where.= ' AND b.vendor_id = %d';
 $params[] = intval($args['vendor_id']);
 }
 if (!empty($args['workflow_status'])) {
 $where.= ' AND b.workflow_status = %s';
 $params[] = sanitize_text_field($args['workflow_status']);
 }
 if (!empty($args['payment_status'])) {
 $where.= ' AND b.payment_status = %s';
 $params[] = sanitize_text_field($args['payment_status']);
 }

 $limit = intval($args['limit'] ?? 50);
 $offset = intval($args['offset'] ?? 0);
 $params[] = $limit;
 $params[] = $offset;

 $count_params = $params;
 array_pop($count_params);
 array_pop($count_params);
 $total = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*)
 FROM {$table_bills} b
 JOIN {$table_vendors} v ON b.vendor_id = v.id
 WHERE {$where}",
 $count_params
 ));

 $bills = $wpdb->get_results($wpdb->prepare(
 "SELECT b.*, v.name as vendor_name
 FROM {$table_bills} b
 JOIN {$table_vendors} v ON b.vendor_id = v.id
 WHERE {$where}
 ORDER BY b.due_date ASC
 LIMIT %d OFFSET %d",
 $params
 ));

 return [
 'bills' => $bills,
 'total' => $total,
 'page' => ($limit > 0) ? floor($offset / $limit) + 1: 1,
 'per_page' => $limit,
 ];
 }

 public static function submit_bill($org_id, $bill_id, $user_id) {
 global $wpdb;

 $bill = self::get_bill($bill_id, $org_id);
 if (!$bill || $bill->workflow_status !== 'draft') {
 return new WP_Error('invalid_status', 'Only draft bills can be submitted');
 }

 if (!class_exists('OraBooks_Workflow')) {
 return new WP_Error('workflow_unavailable', 'Workflow engine unavailable');
 }

 $result = OraBooks_Workflow::transition('bill', $bill_id, 'submit', [
 'user_id' => (int) $user_id,
 'org_id' => (int) $org_id,
 ]);
 if (is_wp_error($result)) {
 return $result;
 }

 orabooks_log_event('vendor_bill_submitted', "Bill {$bill->bill_number} submitted", 'info', [
 'bill_id' => intval($bill_id),
 ], intval($user_id), intval($org_id));

 return true;
 }

 public static function approve_bill($org_id, $bill_id, $user_id) {
 global $wpdb;

 $bill = self::get_bill($bill_id, $org_id);
 if (!$bill || $bill->workflow_status !== 'submitted') {
 return new WP_Error('invalid_status', 'Only submitted bills can be approved');
 }

 if (!class_exists('OraBooks_Workflow')) {
 return new WP_Error('workflow_unavailable', 'Workflow engine unavailable');
 }

 $result = OraBooks_Workflow::transition('bill', $bill_id, 'approve', [
 'user_id' => (int) $user_id,
 'org_id' => (int) $org_id,
 'row_updates' => [
 'approved_by' => (int) $user_id,
 'approved_at' => current_time('mysql'),
 ],
 ]);
 if (is_wp_error($result)) {
 return $result;
 }

 orabooks_log_event('vendor_bill_approved', "Bill {$bill->bill_number} approved", 'info', [
 'bill_id' => intval($bill_id),
 ], intval($user_id), intval($org_id));

 $config = class_exists('OraBooks_AP') ? OraBooks_AP::get_ap_config($org_id): self::get_ap_config($org_id);
 if (!empty($config->auto_post_bill_on_approve)) {
 return self::post_bill($org_id, $bill_id, $user_id);
 }

 return true;
 }

 public static function void_bill($org_id, $bill_id, $user_id, $reason = null) {
 $bill = self::get_bill($bill_id, $org_id);
 if (!$bill) {
 return new WP_Error('not_found', 'Bill not found');
 }

 if (!in_array($bill->workflow_status, ['draft', 'submitted', 'approved'], true)) {
 return new WP_Error('invalid_status', 'Only draft, submitted, or approved bills can be voided');
 }

 $paid_amount = (float) ($bill->paid_amount ?? 0);
 if ($paid_amount > 0 || in_array($bill->payment_status, ['paid', 'partial'], true)) {
 return new WP_Error('has_payments', 'Cannot void a bill with recorded payments');
 }

 if (!class_exists('OraBooks_Workflow')) {
 return new WP_Error('workflow_unavailable', 'Workflow engine unavailable');
 }

 $result = OraBooks_Workflow::transition('bill', $bill_id, 'void', [
 'user_id' => (int) $user_id,
 'org_id' => (int) $org_id,
 'reason' => $reason,
 ]);
 if (is_wp_error($result)) {
 return $result;
 }

 orabooks_log_event('vendor_bill_voided', "Bill {$bill->bill_number} voided", 'info', [
 'bill_id' => (int) $bill_id,
 'reason' => $reason,
 ], (int) $user_id, (int) $org_id);

 do_action('orabooks_vendor_bill_voided', (int) $bill_id, [
 'org_id' => (int) $org_id,
 'vendor_id' => (int) $bill->vendor_id,
 'reason' => $reason,
 ]);

 return self::get_bill($bill_id, $org_id);
 }

 public static function post_bill($org_id, $bill_id, $user_id) {
 global $wpdb;

 $bill = self::get_bill($bill_id, $org_id);
 if (!$bill || !in_array($bill->workflow_status, ['approved', 'submitted'], true)) {
 return new WP_Error('invalid_status', 'Bill must be approved before posting');
 }

 $journal_id = self::create_bill_journal($bill, $user_id);
 $tax_snapshot_id = self::snapshot_bill_tax($bill, $user_id);

 if (!class_exists('OraBooks_Workflow')) {
 return new WP_Error('workflow_unavailable', 'Workflow engine unavailable');
 }

 $rendered_copy = class_exists('OraBooks_AP')
 ? OraBooks_AP::build_bill_rendered_copy($bill)
: null;

 $result = OraBooks_Workflow::transition('bill', $bill_id, 'post', [
 'user_id' => (int) $user_id,
 'org_id' => (int) $org_id,
 'row_updates' => [
 'lock_status' => 'locked',
 'journal_id' => is_wp_error($journal_id) ? null: (int) $journal_id,
 'tax_snapshot_id' => is_wp_error($tax_snapshot_id) ? null: (int) $tax_snapshot_id,
 'posted_at' => current_time('mysql'),
 'rendered_copy' => $rendered_copy ? wp_json_encode($rendered_copy): null,
 ],
 ]);
 if (is_wp_error($result)) {
 return $result;
 }

 self::adjust_vendor_balance($bill->vendor_id, $org_id, floatval($bill->total_amount), 0);

 if (class_exists('OraBooks_AP')) {
 OraBooks_AP::apply_vendor_credit_to_bill((int) $org_id, (int) $bill->vendor_id, (int) $bill_id, (int) $user_id);
 }

 orabooks_log_event('vendor_bill_posted', "Bill {$bill->bill_number} posted", 'info', [
 'bill_id' => intval($bill_id),
 'journal_id' => is_wp_error($journal_id) ? null: $journal_id,
 'tax_snapshot_id' => is_wp_error($tax_snapshot_id) ? null: $tax_snapshot_id,
 ], intval($user_id), intval($org_id));

 do_action('orabooks_vendor_bill_posted', intval($bill_id), [
 'org_id' => intval($org_id),
 'vendor_id' => intval($bill->vendor_id),
 'total_amount' => floatval($bill->total_amount),
 'inventory_items' => self::get_inventory_items_for_bill(intval($bill_id), intval($org_id)),
 'user_id' => intval($user_id),
 ]);

 return true;
 }

 public static function record_payment($org_id, $vendor_id, $data) {
 global $wpdb;

 $org_id = intval($org_id);
 $vendor_id = intval($vendor_id);
 $amount = round(floatval($data['amount'] ?? 0), 2);
 if ($amount <= 0) {
 return new WP_Error('invalid_amount', 'Payment amount must be greater than 0');
 }

 $table_payments = OraBooks_Database::table('vendor_payments');
 $wpdb->insert(
 $table_payments,
 [
 'org_id' => $org_id,
 'vendor_id' => $vendor_id,
 'payment_date' => $data['payment_date'] ?? current_time('Y-m-d'),
 'amount' => $amount,
 'unapplied_amount' => 0,
 'payment_method' => $data['payment_method'] ?? 'bank_transfer',
 'type' => $data['type'] ?? 'payment',
 'reference' => isset($data['reference']) ? sanitize_text_field($data['reference']): null,
 'notes' => isset($data['notes']) ? sanitize_textarea_field($data['notes']): null,
 'idempotency_key' => $data['idempotency_key'] ?? orabooks_uuid,
 ],
 ['%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s']
 );

 $payment_id = intval($wpdb->insert_id);
 $remaining = self::allocate_payment_fifo($org_id, $vendor_id, $payment_id, $amount);

 if ($remaining > 0) {
 self::adjust_vendor_balance($vendor_id, $org_id, 0, $remaining);
 $wpdb->update(
 $table_payments,
 ['unapplied_amount' => $remaining],
 ['id' => $payment_id],
 ['%f'],
 ['%d']
 );
 }

 $allocated = round($amount - $remaining, 2);
 if (class_exists('OraBooks_AP') && $allocated > 0) {
 OraBooks_AP::create_payment_journal_for_ap(
 $org_id,
 $payment_id,
 $allocated,
 $data['payment_date'] ?? current_time('Y-m-d'),
 sanitize_text_field($data['reference'] ?? 'Vendor payment'),
 (int) ($data['user_id'] ?? orabooks_get_current_user_id())
 );
 }

 orabooks_log_event('vendor_payment_recorded', 'Vendor payment recorded', 'info', [
 'payment_id' => $payment_id,
 'vendor_id' => $vendor_id,
 'amount' => $amount,
 'unapplied_amount' => $remaining,
 ], orabooks_get_current_user_id(), $org_id);

 return [
 'payment_id' => $payment_id,
 'allocated_amount' => $allocated,
 'unapplied_amount' => round($remaining, 2),
 ];
 }

 public static function create_credit_note($org_id, $data) {
 global $wpdb;

 $org_id = intval($org_id);
 $vendor_id = intval($data['vendor_id'] ?? 0);
 $amount = round(floatval($data['amount'] ?? 0), 2);
 $reason = sanitize_textarea_field($data['reason'] ?? '');

 if ($vendor_id <= 0 || $amount <= 0 || $reason === '') {
 return new WP_Error('invalid_credit_note', 'Vendor, amount, and reason are required');
 }

 $config = class_exists('OraBooks_AP') ? OraBooks_AP::get_ap_config($org_id): self::get_ap_config($org_id);
 $is_adjustment = !empty($data['is_adjustment']);
 $adjustment_account = $is_adjustment
 ? sanitize_text_field($data['adjustment_account_code'] ?? $config->vendor_adjustment_account)
: null;
 $requires_second = $is_adjustment && $amount > (float) $config->adjustment_threshold;

 $number = !empty($data['credit_note_number'])
 ? sanitize_text_field($data['credit_note_number'])
: self::generate_credit_note_number($org_id, $data['credit_date'] ?? current_time('Y-m-d'));

 $wpdb->insert(
 OraBooks_Database::table('vendor_credit_notes'),
 [
 'org_id' => $org_id,
 'vendor_id' => $vendor_id,
 'bill_id' => !empty($data['bill_id']) ? intval($data['bill_id']): null,
 'credit_note_number' => $number,
 'credit_date' => $data['credit_date'] ?? current_time('Y-m-d'),
 'amount' => $amount,
 'reason' => $reason,
 'is_adjustment' => $is_adjustment ? 1: 0,
 'adjustment_account_code' => $adjustment_account,
 'requires_second_approval' => $requires_second ? 1: 0,
 'workflow_status' => 'draft',
 'created_by' => orabooks_get_current_user_id(),
 ],
 ['%d', '%d', '%d', '%s', '%s', '%f', '%s', '%d', '%s', '%d', '%s', '%d']
 );

 $credit_note_id = intval($wpdb->insert_id);
 orabooks_log_event('vendor_credit_note_created', "Vendor credit note {$number} created", 'info', [
 'credit_note_id' => $credit_note_id,
 'vendor_id' => $vendor_id,
 'amount' => $amount,
 'is_adjustment' => $is_adjustment,
 ], orabooks_get_current_user_id(), $org_id);

 if (class_exists('OraBooks_AP')) {
 $note = OraBooks_AP::get_credit_note($credit_note_id, $org_id);
 if ($note) {
 return OraBooks_AP::format_credit_note($note);
 }
 }

 return [
 'id' => $credit_note_id,
 'credit_note_id' => $credit_note_id,
 'credit_note_number' => $number,
 'requires_second_approval' => $requires_second ? 1: 0,
 'is_adjustment' => $is_adjustment ? 1: 0,
 'workflow_status' => 'draft',
 'amount' => $amount,
 ];
 }

 public static function get_ap_aging($org_id, $as_of_date = null) {
 global $wpdb;

 $as_of_date = $as_of_date ?: current_time('Y-m-d');
 $table_bills = OraBooks_Database::table('bills');
 $bills = $wpdb->get_results($wpdb->prepare(
 "SELECT id, vendor_id, bill_number, due_date, total_amount, paid_amount, payment_status
 FROM {$table_bills}
 WHERE org_id = %d AND workflow_status = 'posted' AND payment_status IN ('unpaid','partial')
 ORDER BY due_date ASC",
 intval($org_id)
 ));

 $buckets = [
 'current' => 0.0,
 '30' => 0.0,
 '60' => 0.0,
 '90_plus' => 0.0,
 ];

 foreach ($bills as $bill) {
 $outstanding = max(0, floatval($bill->total_amount) - floatval($bill->paid_amount));
 $days = floor((strtotime($as_of_date) - strtotime($bill->due_date)) / DAY_IN_SECONDS);
 if ($days <= 0) {
 $bucket = 'current';
 } elseif ($days <= 30) {
 $bucket = '30';
 } elseif ($days <= 60) {
 $bucket = '60';
 } else {
 $bucket = '90_plus';
 }
 $buckets[$bucket] += $outstanding;
 }

 return $buckets;
 }

 private static function get_ap_config($org_id) {
 if (class_exists('OraBooks_AP')) {
 return OraBooks_AP::get_ap_config($org_id);
 }
 global $wpdb;

 $table = OraBooks_Database::table('vendor_ap_configs');
 $config = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table} WHERE org_id = %d",
 intval($org_id)
 ));

 if ($config) {
 return $config;
 }

 return (object) [
 'org_id' => intval($org_id),
 'auto_post_bill_on_approve' => 1,
 'auto_apply_vendor_credit' => 1,
 'adjustment_threshold' => 1000,
 'vendor_adjustment_account' => '5000',
 ];
 }

 private static function get_vendor_payment_terms($vendor_id, $org_id) {
 $vendor = self::get_vendor($vendor_id, $org_id);
 return $vendor ? intval($vendor->payment_terms): 30;
 }

 private static function generate_bill_number($org_id, $date) {
 global $wpdb;

 $year = date('Y', strtotime($date));
 $table = OraBooks_Database::table('bills');
 $last = $wpdb->get_var($wpdb->prepare(
 "SELECT MAX(CAST(SUBSTRING_INDEX(bill_number, '-', -1) AS UNSIGNED))
 FROM {$table}
 WHERE org_id = %d AND bill_number LIKE %s",
 intval($org_id),
 'BILL-'. $year. '-%'
 ));

 return sprintf('BILL-%s-%06d', $year, intval($last) + 1);
 }

 private static function generate_credit_note_number($org_id, $date) {
 global $wpdb;

 $year = date('Y', strtotime($date));
 $table = OraBooks_Database::table('vendor_credit_notes');
 $last = $wpdb->get_var($wpdb->prepare(
 "SELECT MAX(CAST(SUBSTRING_INDEX(credit_note_number, '-', -1) AS UNSIGNED))
 FROM {$table}
 WHERE org_id = %d AND credit_note_number LIKE %s",
 intval($org_id),
 'VCN-'. $year. '-%'
 ));

 return sprintf('VCN-%s-%06d', $year, intval($last) + 1);
 }

 private static function allocate_payment_fifo($org_id, $vendor_id, $payment_id, $amount) {
 global $wpdb;

 $table_bills = OraBooks_Database::table('bills');
 $table_allocations = OraBooks_Database::table('vendor_payment_allocations');
 $remaining = round(floatval($amount), 2);

 $bills = $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM {$table_bills}
 WHERE org_id = %d AND vendor_id = %d AND workflow_status = 'posted' AND payment_status IN ('unpaid','partial')
 ORDER BY due_date ASC, id ASC",
 intval($org_id),
 intval($vendor_id)
 ));

 foreach ($bills as $bill) {
 if ($remaining <= 0) {
 break;
 }

 $outstanding = max(0, floatval($bill->total_amount) - floatval($bill->paid_amount));
 if ($outstanding <= 0) {
 continue;
 }

 $applied = min($remaining, $outstanding);
 $new_paid = round(floatval($bill->paid_amount) + $applied, 2);
 $new_status = ($new_paid >= floatval($bill->total_amount)) ? 'paid': 'partial';

 $wpdb->insert(
 $table_allocations,
 [
 'org_id' => intval($org_id),
 'vendor_id' => intval($vendor_id),
 'payment_id' => intval($payment_id),
 'bill_id' => intval($bill->id),
 'amount' => $applied,
 'allocation_method' => 'FIFO',
 ],
 ['%d', '%d', '%d', '%d', '%f', '%s']
 );

 $wpdb->update(
 $table_bills,
 [
 'paid_amount' => $new_paid,
 'payment_status' => $new_status,
 'lock_status' => $new_status === 'paid' ? 'locked': $bill->lock_status,
 ],
 ['id' => intval($bill->id)],
 ['%f', '%s', '%s'],
 ['%d']
 );

 self::adjust_vendor_balance($vendor_id, $org_id, -$applied, 0);
 $remaining = round($remaining - $applied, 2);
 }

 return max(0, $remaining);
 }

 private static function adjust_vendor_balance($vendor_id, $org_id, $payable_delta, $credit_delta) {
 global $wpdb;

 $table = OraBooks_Database::table('vendors');
 $wpdb->query($wpdb->prepare(
 "UPDATE {$table}
 SET payable_balance = GREATEST(0, payable_balance + %f),
 credit_balance = GREATEST(0, credit_balance + %f)
 WHERE id = %d AND org_id = %d",
 floatval($payable_delta),
 floatval($credit_delta),
 intval($vendor_id),
 intval($org_id)
 ));
 }

 private static function create_bill_journal($bill, $user_id) {
 if (!class_exists('OraBooks_Posting')) {
 return null;
 }

 $journal_id = OraBooks_Posting::create_journal([
 'org_id' => intval($bill->org_id),
 'transaction_date' => $bill->transaction_date,
 'source_type' => 'vendor_bill',
 'source_id' => intval($bill->id),
 'metadata' => ['bill_number' => $bill->bill_number],
 ], intval($user_id));

 if (is_wp_error($journal_id)) {
 return $journal_id;
 }

 $inventory_total = self::get_bill_inventory_total(intval($bill->id), intval($bill->org_id));
 $expense_debit = max(0, round(floatval($bill->subtotal_amount) - $inventory_total + floatval($bill->tax_amount), 2));
 $journal_lines = [];

 if ($inventory_total > 0) {
 $journal_lines[] = [
 'account_code' => '1200',
 'debit' => $inventory_total,
 'credit' => 0,
 'description' => 'Inventory purchase '. $bill->bill_number,
 ];
 }
 if ($expense_debit > 0) {
 $journal_lines[] = [
 'account_code' => '5000',
 'debit' => $expense_debit,
 'credit' => 0,
 'description' => 'Vendor bill expense '. $bill->bill_number,
 ];
 }
 if (empty($journal_lines)) {
 $journal_lines[] = [
 'account_code' => '5000',
 'debit' => floatval($bill->subtotal_amount) + floatval($bill->tax_amount),
 'credit' => 0,
 'description' => 'Vendor bill '. $bill->bill_number,
 ];
 }
 $journal_lines[] = [
 'account_code' => '2000',
 'debit' => 0,
 'credit' => floatval($bill->total_amount),
 'description' => 'AP for '. $bill->bill_number,
 ];

 OraBooks_Posting::add_lines($journal_id, $journal_lines);

 return $journal_id;
 }

 public static function parse_line_items($data) {
 $lines = $data['line_items'] ?? [];
 if (is_string($lines)) {
 $decoded = json_decode(wp_unslash($lines), true);
 $lines = is_array($decoded) ? $decoded: [];
 }
 return is_array($lines) ? $lines: [];
 }

 public static function calculate_line_items_subtotal(array $lines, $cost_field = 'unit_cost') {
 $subtotal = 0.0;
 foreach ($lines as $line) {
 if (!is_array($line)) {
 continue;
 }
 $qty = floatval($line['quantity'] ?? 0);
 $unit = floatval($line[$cost_field] ?? $line['unit_price'] ?? 0);
 $subtotal += round($qty * $unit, 2);
 }
 return round($subtotal, 2);
 }

 public static function save_bill_line_items($org_id, $bill_id, array $lines) {
 global $wpdb;

 $table = OraBooks_Database::table('bill_line_items');
 $wpdb->delete($table, ['bill_id' => (int) $bill_id, 'org_id' => (int) $org_id], ['%d', '%d']);

 $sort = 0;
 foreach ($lines as $line) {
 if (!is_array($line)) {
 continue;
 }
 $qty = round(floatval($line['quantity'] ?? 0), 4);
 if ($qty <= 0) {
 continue;
 }
 $unit_cost = round(floatval($line['unit_cost'] ?? 0), 6);
 $line_total = round(floatval($line['line_total'] ?? ($qty * $unit_cost)), 2);
 $wpdb->insert(
 $table,
 [
 'org_id' => (int) $org_id,
 'bill_id' => (int) $bill_id,
 'product_id' => !empty($line['product_id']) ? (int) $line['product_id']: null,
 'description' => isset($line['description']) ? sanitize_textarea_field($line['description']): null,
 'quantity' => $qty,
 'unit_cost' => $unit_cost,
 'line_total' => $line_total,
 'sort_order' => $sort++,
 ],
 ['%d', '%d', '%d', '%s', '%f', '%f', '%f', '%d']
 );
 }
 }

 public static function get_bill_line_items($bill_id, $org_id) {
 global $wpdb;

 return $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM ". OraBooks_Database::table('bill_line_items'). "
 WHERE bill_id = %d AND org_id = %d
 ORDER BY sort_order ASC, id ASC",
 (int) $bill_id,
 (int) $org_id
 )) ?: [];
 }

 public static function get_bill_inventory_total($bill_id, $org_id) {
 $total = 0.0;
 foreach (self::get_bill_line_items($bill_id, $org_id) as $line) {
 if (!empty($line->product_id)) {
 $total += floatval($line->line_total);
 }
 }
 return round($total, 2);
 }

 public static function get_inventory_items_for_bill($bill_id, $org_id) {
 $items = [];
 foreach (self::get_bill_line_items($bill_id, $org_id) as $line) {
 if (empty($line->product_id)) {
 continue;
 }
 $items[] = [
 'product_id' => (int) $line->product_id,
 'quantity' => floatval($line->quantity),
 'unit_cost' => floatval($line->unit_cost),
 ];
 }
 return $items;
 }

 private static function snapshot_bill_tax($bill, $user_id) {
 if (!class_exists('OraBooks_Tax') || floatval($bill->tax_amount) <= 0) {
 return null;
 }

 $snapshot = OraBooks_Tax::create_snapshot([
 'org_id' => intval($bill->org_id),
 'transaction_id' => intval($bill->id),
 'transaction_type' => 'expense',
 'amount' => floatval($bill->subtotal_amount),
 'jurisdiction' => 'US',
 'override' => true,
 'override_tax_rate' => floatval($bill->subtotal_amount) > 0
 ? round((floatval($bill->tax_amount) / floatval($bill->subtotal_amount)) * 100, 4)
: 0,
 'override_reason' => 'LOCAL_TAX_RULE',
 'transaction_date' => $bill->transaction_date,
 'metadata' => ['bill_number' => $bill->bill_number],
 ], intval($user_id));

 if (is_wp_error($snapshot)) {
 return $snapshot;
 }

 return intval($snapshot['snapshot_id']);
 }

 private function current_user_id() {
 return orabooks_get_current_user_id();
 }

 private function require_customer_org_access($user_id, $org_id) {
 if (!$user_id) {
 orabooks_json_error('Not authenticated', 401);
 }

 $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
 if (is_wp_error($isolation)) {
 orabooks_json_error($isolation->get_error_message(), 403);
 }
 }

 private function require_ap_permission($user_id, $org_id, $permissions) {
 if (!$user_id) {
 orabooks_json_error('Not authenticated', 401);
 }

 if ($org_id <= 0) {
 orabooks_json_error('Organization ID required', 400);
 }

 $this->require_customer_org_access($user_id, $org_id);

 if (current_user_can('manage_options')) {
 return;
 }

 foreach ((array) $permissions as $permission) {
 if (OraBooks_RBAC::require_permission($user_id, $org_id, $permission)) {
 return;
 }
 }

 orabooks_json_error('Permission denied', 403);
 }

 public function ajax_vendors_list() {
 $user_id = $this->current_user_id;
 $org_id = intval($_GET['org_id'] ?? 0);
 $this->require_ap_permission($user_id, $org_id, ['view_reports']);
 orabooks_json_success(self::get_vendors_list($org_id, $_GET));
 }

 public function ajax_vendor_create() {
 $user_id = $this->current_user_id;
 $org_id = intval($_POST['org_id'] ?? 0);
 $this->require_ap_permission($user_id, $org_id, ['manage_org_settings']);
 $result = self::create_vendor($org_id, $_POST);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message(), 400);
 }
 orabooks_json_success(['vendor' => $result]);
 }

 public function ajax_vendor_update() {
 $user_id = $this->current_user_id;
 $org_id = intval($_POST['org_id'] ?? 0);
 $vendor_id = intval($_POST['vendor_id'] ?? 0);
 $this->require_ap_permission($user_id, $org_id, ['manage_org_settings']);
 $result = self::update_vendor($org_id, $vendor_id, $_POST);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message(), 400);
 }
 orabooks_json_success(['vendor' => $result], 'Vendor updated');
 }

 public function ajax_bills_list() {
 $user_id = $this->current_user_id;
 $org_id = intval($_GET['org_id'] ?? 0);
 $this->require_ap_permission($user_id, $org_id, ['view_reports']);
 orabooks_json_success(self::get_bills_list($org_id, $_GET));
 }

 public function ajax_bill_create() {
 $user_id = $this->current_user_id;
 $org_id = intval($_POST['org_id'] ?? 0);
 $this->require_ap_permission($user_id, $org_id, ['submit_transaction']);
 $result = self::create_bill($org_id, $_POST);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message(), 400);
 }
 orabooks_json_success(['bill' => $result]);
 }

 public function ajax_bill_submit() {
 $user_id = $this->current_user_id;
 $org_id = intval($_POST['org_id'] ?? 0);
 $this->require_ap_permission($user_id, $org_id, ['submit_transaction']);
 $result = self::submit_bill($org_id, intval($_POST['bill_id'] ?? 0), $user_id);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message(), 400);
 }
 orabooks_json_success([], 'Bill submitted');
 }

 public function ajax_bill_approve() {
 $user_id = $this->current_user_id;
 $org_id = intval($_POST['org_id'] ?? 0);
 $this->require_ap_permission($user_id, $org_id, ['approve_journal']);
 $result = self::approve_bill($org_id, intval($_POST['bill_id'] ?? 0), $user_id);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message(), 400);
 }
 orabooks_json_success([], 'Bill approved');
 }

 public function ajax_bill_post() {
 $user_id = $this->current_user_id;
 $org_id = intval($_POST['org_id'] ?? 0);
 $this->require_ap_permission($user_id, $org_id, ['approve_journal', 'manage_org_settings']);
 $result = self::post_bill($org_id, intval($_POST['bill_id'] ?? 0), $user_id);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message(), 400);
 }
 orabooks_json_success([], 'Bill posted');
 }

 public function ajax_bill_void() {
 $user_id = $this->current_user_id;
 $org_id = intval($_POST['org_id'] ?? 0);
 $reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])): null;
 $this->require_ap_permission($user_id, $org_id, ['submit_transaction', 'manage_org_settings']);
 $result = self::void_bill($org_id, intval($_POST['bill_id'] ?? 0), $user_id, $reason);
 if (is_wp_error($result)) {
 $status = 400;
 $data = $result->get_error_data;
 if (is_array($data) && isset($data['status'])) {
 $status = (int) $data['status'];
 }
 orabooks_json_error($result->get_error_message(), $status);
 }
 orabooks_json_success(['bill' => $result], 'Bill voided');
 }

 public function ajax_record_payment() {
 $user_id = $this->current_user_id;
 $org_id = intval($_POST['org_id'] ?? 0);
 $this->require_ap_permission($user_id, $org_id, ['manage_org_settings', 'approve_journal', 'submit_transaction', 'manage_billing']);
 $result = self::record_payment($org_id, intval($_POST['vendor_id'] ?? 0), $_POST);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message(), 400);
 }
 orabooks_json_success($result);
 }

 public function ajax_create_credit_note() {
 $user_id = $this->current_user_id;
 $org_id = intval($_POST['org_id'] ?? 0);
 $this->require_ap_permission($user_id, $org_id, ['manage_org_settings', 'approve_journal', 'manage_billing']);
 $result = self::create_credit_note($org_id, $_POST);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message(), 400);
 }
 orabooks_json_success(['credit_note' => $result]);
 }

 public function ajax_ap_aging() {
 $user_id = $this->current_user_id;
 $org_id = intval($_GET['org_id'] ?? 0);
 $this->require_ap_permission($user_id, $org_id, ['view_reports']);
 orabooks_json_success(self::get_ap_aging($org_id, $_GET['as_of_date'] ?? null));
 }
}
