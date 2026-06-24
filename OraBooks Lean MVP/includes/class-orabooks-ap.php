<?php
/**
 * OraBooks AP Extension
 *
 * Vendor credit notes, payment reversals, AP config, auto-apply vendor credit,
 * payment journals, and vendor statement snapshots.
 */

if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_AP {

 private static $instance = null;

 public static function init() {
 if (self::$instance === null) {
 self::$instance = new self;

 add_action('wp_ajax_orabooks_vendor_payment_reverse', [self::$instance, 'ajax_reverse_payment']);
 add_action('wp_ajax_nopriv_orabooks_vendor_payment_reverse', [self::$instance, 'ajax_reverse_payment']);
 add_action('wp_ajax_orabooks_vendor_payments_list', [self::$instance, 'ajax_payments_list']);
 add_action('wp_ajax_nopriv_orabooks_vendor_payments_list', [self::$instance, 'ajax_payments_list']);
 add_action('wp_ajax_orabooks_vendor_credit_note_submit', [self::$instance, 'ajax_submit_credit_note']);
 add_action('wp_ajax_nopriv_orabooks_vendor_credit_note_submit', [self::$instance, 'ajax_submit_credit_note']);
 add_action('wp_ajax_orabooks_vendor_credit_note_approve', [self::$instance, 'ajax_approve_credit_note']);
 add_action('wp_ajax_nopriv_orabooks_vendor_credit_note_approve', [self::$instance, 'ajax_approve_credit_note']);
 add_action('wp_ajax_orabooks_vendor_credit_note_post', [self::$instance, 'ajax_post_credit_note']);
 add_action('wp_ajax_nopriv_orabooks_vendor_credit_note_post', [self::$instance, 'ajax_post_credit_note']);
 add_action('wp_ajax_orabooks_vendor_credit_note_void', [self::$instance, 'ajax_void_credit_note']);
 add_action('wp_ajax_nopriv_orabooks_vendor_credit_note_void', [self::$instance, 'ajax_void_credit_note']);
 add_action('wp_ajax_orabooks_vendor_credit_notes_list', [self::$instance, 'ajax_credit_notes_list']);
 add_action('wp_ajax_nopriv_orabooks_vendor_credit_notes_list', [self::$instance, 'ajax_credit_notes_list']);
 add_action('wp_ajax_orabooks_ap_config_get', [self::$instance, 'ajax_ap_config_get']);
 add_action('wp_ajax_nopriv_orabooks_ap_config_get', [self::$instance, 'ajax_ap_config_get']);
 add_action('wp_ajax_orabooks_ap_config_save', [self::$instance, 'ajax_ap_config_save']);
 add_action('wp_ajax_nopriv_orabooks_ap_config_save', [self::$instance, 'ajax_ap_config_save']);
 add_action('wp_ajax_orabooks_vendor_statements_list', [self::$instance, 'ajax_statements_list']);
 add_action('wp_ajax_nopriv_orabooks_vendor_statements_list', [self::$instance, 'ajax_statements_list']);
 add_action('wp_ajax_orabooks_vendor_get', [self::$instance, 'ajax_vendor_get']);
 add_action('wp_ajax_nopriv_orabooks_vendor_get', [self::$instance, 'ajax_vendor_get']);
 add_action('wp_ajax_orabooks_bill_get', [self::$instance, 'ajax_bill_get']);
 add_action('wp_ajax_nopriv_orabooks_bill_get', [self::$instance, 'ajax_bill_get']);

 add_action('orabooks_daily_ap_aging_snapshot', [self::$instance, 'daily_vendor_statement_snapshot']);
 }
 return self::$instance;
 }

 public static function ensure_schema() {
 global $wpdb;

 static $ran = false;
 if ($ran) {
 return;
 }
 $ran = true;

 if (class_exists('OraBooks_Vendors')) {
 foreach (OraBooks_Vendors::get_create_table_sql as $sql) {
 $wpdb->query($sql);
 }
 }

 $table = OraBooks_Database::table('vendor_credit_notes');
 if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
 $cols = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
 $add = [
 'requires_second_approval' => "ALTER TABLE {$table} ADD COLUMN requires_second_approval TINYINT(1) DEFAULT 0",
 'approved_at' => "ALTER TABLE {$table} ADD COLUMN approved_at TIMESTAMP NULL",
 'posted_at' => "ALTER TABLE {$table} ADD COLUMN posted_at TIMESTAMP NULL",
 ];
 foreach ($add as $col => $sql) {
 if (!in_array($col, $cols, true)) {
 $wpdb->query($sql);
 }
 }
 }
 }

 public static function get_ap_config($org_id) {
 global $wpdb;
 self::ensure_schema();

 $table = OraBooks_Database::table('vendor_ap_configs');
 $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE org_id = %d", (int) $org_id));
 if ($row) {
 return $row;
 }

 return (object) [
 'org_id' => (int) $org_id,
 'auto_post_bill_on_approve' => 1,
 'auto_apply_vendor_credit' => 1,
 'adjustment_threshold' => 1000,
 'vendor_adjustment_account' => '5000',
 ];
 }

 public static function save_ap_config($org_id, array $data) {
 global $wpdb;
 self::ensure_schema();

 $table = OraBooks_Database::table('vendor_ap_configs');
 $payload = [
 'org_id' => (int) $org_id,
 'auto_post_bill_on_approve' => !empty($data['auto_post_bill_on_approve']) ? 1: 0,
 'auto_apply_vendor_credit' => !empty($data['auto_apply_vendor_credit']) ? 1: 0,
 'adjustment_threshold' => round((float) ($data['adjustment_threshold'] ?? 1000), 2),
 'vendor_adjustment_account' => sanitize_text_field($data['vendor_adjustment_account'] ?? '5000'),
 ];

 $existing = $wpdb->get_var($wpdb->prepare("SELECT org_id FROM {$table} WHERE org_id = %d", (int) $org_id));
 if ($existing) {
 unset($payload['org_id']);
 $wpdb->update($table, $payload, ['org_id' => (int) $org_id]);
 } else {
 $wpdb->insert($table, $payload);
 }

 orabooks_log_event('ap_config_updated', 'Vendor AP configuration updated', 'info', $payload, orabooks_get_current_user_id, (int) $org_id);
 return self::get_ap_config($org_id);
 }

 public static function apply_vendor_credit_to_bill($org_id, $vendor_id, $bill_id, $user_id) {
 global $wpdb;
 self::ensure_schema();

 $config = self::get_ap_config($org_id);
 if (empty($config->auto_apply_vendor_credit)) {
 return 0.0;
 }

 $vendor = OraBooks_Vendors::get_vendor((int) $vendor_id, (int) $org_id);
 if (!$vendor || (float) $vendor->credit_balance <= 0 || !(int) $vendor->auto_apply_credit) {
 return 0.0;
 }

 $bill = OraBooks_Vendors::get_bill((int) $bill_id, (int) $org_id);
 if (!$bill || (int) $bill->vendor_id !== (int) $vendor_id || $bill->workflow_status !== 'posted') {
 return 0.0;
 }

 $outstanding = max(0, round((float) $bill->total_amount - (float) ($bill->paid_amount ?? 0), 2));
 if ($outstanding <= 0) {
 return 0.0;
 }

 $applied = min((float) $vendor->credit_balance, $outstanding);
 if ($applied <= 0) {
 return 0.0;
 }

 $table_payments = OraBooks_Database::table('vendor_payments');
 $wpdb->insert(
 $table_payments,
 [
 'org_id' => (int) $org_id,
 'vendor_id' => (int) $vendor_id,
 'payment_date' => current_time('Y-m-d'),
 'amount' => $applied,
 'unapplied_amount' => 0,
 'payment_method' => 'other',
 'type' => 'payment',
 'reference' => 'AUTO_CREDIT',
 'notes' => 'Auto-applied vendor credit',
 'idempotency_key' => orabooks_uuid,
 ],
 ['%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s']
 );
 $payment_id = (int) $wpdb->insert_id;

 self::insert_allocation((int) $org_id, (int) $vendor_id, $payment_id, (int) $bill_id, $applied, 'auto_credit');
 self::update_bill_paid_amount((int) $bill_id, $applied);
 self::adjust_vendor_credit_balance((int) $vendor_id, (int) $org_id, -$applied);

 orabooks_log_event('vendor_credit_applied', 'Vendor credit auto-applied to bill', 'info', [
 'bill_id' => (int) $bill_id,
 'amount' => $applied,
 ], (int) $user_id, (int) $org_id);

 return $applied;
 }

 public static function create_payment_journal_for_ap($org_id, $payment_id, $amount, $payment_date, $reference, $user_id) {
 if (!class_exists('OraBooks_Posting') || $amount <= 0) {
 return null;
 }

 $ap_code = '2000';
 $cash_code = '1000';

 $journal_id = OraBooks_Posting::create_journal([
 'org_id' => (int) $org_id,
 'transaction_date' => $payment_date,
 'source_type' => 'vendor_payment',
 'source_id' => (int) $payment_id,
 'idempotency_key' => 'vendor_payment_'. (int) $payment_id,
 'metadata' => [
 'payment_id' => (int) $payment_id,
 'reference' => $reference,
 'amount' => (float) $amount,
 ],
 ], 0);

 if (is_wp_error($journal_id)) {
 return $journal_id;
 }

 $description = sprintf('Vendor payment %s', $reference);
 OraBooks_Posting::add_lines($journal_id, [
 ['account_code' => $ap_code, 'debit' => (float) $amount, 'credit' => 0, 'description' => $description],
 ['account_code' => $cash_code, 'debit' => 0, 'credit' => (float) $amount, 'description' => $description],
 ]);

 OraBooks_Posting::submit_journal($journal_id, 0);
 OraBooks_Posting::approve_journal($journal_id, 0);
 OraBooks_Posting::post_journal($journal_id, 0);

 global $wpdb;
 $wpdb->update(
 OraBooks_Database::table('vendor_payments'),
 ['journal_id' => (int) $journal_id],
 ['id' => (int) $payment_id],
 ['%d'],
 ['%d']
 );

 return $journal_id;
 }

 public static function reverse_vendor_payment($org_id, $payment_id, $user_id, $reason = '') {
 global $wpdb;
 self::ensure_schema();

 $table = OraBooks_Database::table('vendor_payments');
 $payment = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table} WHERE id = %d AND org_id = %d",
 (int) $payment_id,
 (int) $org_id
 ));
 if (!$payment || $payment->type !== 'payment') {
 return new WP_Error('not_found', 'Payment not found');
 }

 $existing_reversal = $wpdb->get_var($wpdb->prepare(
 "SELECT id FROM {$table} WHERE org_id = %d AND reverses_payment_id = %d LIMIT 1",
 (int) $org_id,
 (int) $payment_id
 ));
 if ($existing_reversal) {
 return new WP_Error('already_reversed', 'This payment has already been reversed');
 }

 $amount = (float) $payment->amount;
 $wpdb->insert(
 $table,
 [
 'org_id' => (int) $org_id,
 'vendor_id' => (int) $payment->vendor_id,
 'payment_date' => current_time('Y-m-d'),
 'amount' => -abs($amount),
 'unapplied_amount' => 0,
 'payment_method' => $payment->payment_method,
 'type' => 'reversal',
 'reference' => sanitize_text_field($reason),
 'notes' => 'Reversal of vendor payment #'. (int) $payment_id,
 'reverses_payment_id' => (int) $payment_id,
 'idempotency_key' => orabooks_uuid,
 ],
 ['%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s']
 );
 $reversal_id = (int) $wpdb->insert_id;

 $alloc_table = OraBooks_Database::table('vendor_payment_allocations');
 $allocations = $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM {$alloc_table} WHERE payment_id = %d",
 (int) $payment_id
 ));
 foreach ($allocations ?: [] as $alloc) {
 self::update_bill_paid_amount((int) $alloc->bill_id, -(float) $alloc->amount);
 self::adjust_vendor_payable_balance((int) $payment->vendor_id, (int) $org_id, (float) $alloc->amount);
 }

 if ((float) ($payment->unapplied_amount ?? 0) > 0) {
 self::adjust_vendor_credit_balance((int) $payment->vendor_id, (int) $org_id, -(float) $payment->unapplied_amount);
 }

 if (!empty($payment->journal_id) && class_exists('OraBooks_Posting')) {
 OraBooks_Posting::reverse_journal((int) $payment->journal_id, (int) $org_id, (int) $user_id, $reason);
 }

 orabooks_log_event('vendor_payment_reversed', 'Vendor payment reversed', 'info', [
 'payment_id' => (int) $payment_id,
 'reversal_id' => $reversal_id,
 'reason' => $reason,
 ], (int) $user_id, (int) $org_id);

 return ['reversal_id' => $reversal_id];
 }

 public static function submit_credit_note($org_id, $credit_note_id, $user_id) {
 $note = self::get_credit_note((int) $credit_note_id, (int) $org_id);
 if (!$note) {
 return new WP_Error('not_found', 'Credit note not found');
 }
 if ($note->workflow_status !== 'draft') {
 return new WP_Error('invalid_status', 'Only draft credit notes can be submitted');
 }

 global $wpdb;
 $wpdb->update(
 OraBooks_Database::table('vendor_credit_notes'),
 ['workflow_status' => 'submitted'],
 ['id' => (int) $credit_note_id],
 ['%s'],
 ['%d']
 );

 orabooks_log_event('vendor_credit_note_submitted', "Vendor credit note {$note->credit_note_number} submitted", 'info', [
 'credit_note_id' => (int) $credit_note_id,
 ], (int) $user_id, (int) $org_id);

 return self::format_credit_note(self::get_credit_note((int) $credit_note_id, (int) $org_id));
 }

 public static function approve_credit_note($org_id, $credit_note_id, $user_id) {
 $note = self::get_credit_note((int) $credit_note_id, (int) $org_id);
 if (!$note) {
 return new WP_Error('not_found', 'Credit note not found');
 }
 if ($note->workflow_status !== 'submitted') {
 return new WP_Error('invalid_status', 'Only submitted credit notes can be approved');
 }

 global $wpdb;
 $wpdb->update(
 OraBooks_Database::table('vendor_credit_notes'),
 [
 'workflow_status' => 'approved',
 'approved_by' => (int) $user_id,
 'approved_at' => current_time('mysql'),
 ],
 ['id' => (int) $credit_note_id],
 ['%s', '%d', '%s'],
 ['%d']
 );

 orabooks_log_event('vendor_credit_note_approved', "Vendor credit note {$note->credit_note_number} approved", 'info', [
 'credit_note_id' => (int) $credit_note_id,
 ], (int) $user_id, (int) $org_id);

 return self::format_credit_note(self::get_credit_note((int) $credit_note_id, (int) $org_id));
 }

 public static function post_credit_note($org_id, $credit_note_id, $user_id) {
 global $wpdb;

 $note = self::get_credit_note((int) $credit_note_id, (int) $org_id);
 if (!$note) {
 return new WP_Error('not_found', 'Credit note not found');
 }
 if ($note->workflow_status !== 'approved') {
 return new WP_Error('invalid_status', 'Only approved credit notes can be posted');
 }

 if ((int) ($note->requires_second_approval ?? 0) === 1 && empty($note->approved_by)) {
 return new WP_Error('approval_required', 'Adjustment above threshold requires manager approval');
 }

 $journal_id = self::create_credit_note_journal($note, (int) $user_id);
 if (is_wp_error($journal_id)) {
 return $journal_id;
 }

 $wpdb->update(
 OraBooks_Database::table('vendor_credit_notes'),
 [
 'workflow_status' => 'posted',
 'journal_id' => is_numeric($journal_id) ? (int) $journal_id: null,
 'posted_at' => current_time('mysql'),
 ],
 ['id' => (int) $credit_note_id],
 ['%s', '%d', '%s'],
 ['%d']
 );

 if (!empty($note->bill_id)) {
 $bill = OraBooks_Vendors::get_bill((int) $note->bill_id, (int) $org_id);
 if ($bill) {
 $new_paid = min(
 (float) $bill->total_amount,
 max(0, round((float) ($bill->paid_amount ?? 0) + (float) $note->amount, 2))
 );
 if ($new_paid >= (float) $bill->total_amount) {
 $new_status = 'credited';
 } elseif ($new_paid > 0) {
 $new_status = 'partial';
 } else {
 $new_status = 'unpaid';
 }
 $wpdb->update(
 OraBooks_Database::table('bills'),
 [
 'paid_amount' => $new_paid,
 'payment_status' => $new_status,
 ],
 ['id' => (int) $note->bill_id],
 ['%f', '%s'],
 ['%d']
 );
 self::adjust_vendor_payable_balance((int) $note->vendor_id, (int) $org_id, -(float) $note->amount);
 }
 } else {
 self::adjust_vendor_payable_balance((int) $note->vendor_id, (int) $org_id, -(float) $note->amount);
 }

 orabooks_log_event('vendor_credit_note_posted', "Vendor credit note {$note->credit_note_number} posted", 'info', [
 'credit_note_id' => (int) $credit_note_id,
 'journal_id' => $journal_id,
 ], (int) $user_id, (int) $org_id);

 return self::format_credit_note(self::get_credit_note((int) $credit_note_id, (int) $org_id));
 }

 public static function void_credit_note($org_id, $credit_note_id, $user_id, $reason = '') {
 $note = self::get_credit_note((int) $credit_note_id, (int) $org_id);
 if (!$note) {
 return new WP_Error('not_found', 'Credit note not found');
 }
 if (!in_array($note->workflow_status, ['draft', 'submitted'], true)) {
 return new WP_Error('invalid_status', 'Only draft or submitted credit notes can be voided');
 }

 global $wpdb;
 $wpdb->update(
 OraBooks_Database::table('vendor_credit_notes'),
 ['workflow_status' => 'void'],
 ['id' => (int) $credit_note_id],
 ['%s'],
 ['%d']
 );

 orabooks_log_event('vendor_credit_note_voided', "Vendor credit note {$note->credit_note_number} voided", 'info', [
 'credit_note_id' => (int) $credit_note_id,
 'reason' => $reason,
 ], (int) $user_id, (int) $org_id);

 return self::format_credit_note(self::get_credit_note((int) $credit_note_id, (int) $org_id));
 }

 public static function build_bill_rendered_copy($bill) {
 if (!$bill) {
 return null;
 }

 return [
 'bill_number' => $bill->bill_number,
 'bill_date' => $bill->bill_date,
 'due_date' => $bill->due_date,
 'vendor_id' => (int) $bill->vendor_id,
 'description' => $bill->description ?? '',
 'subtotal' => (float) ($bill->subtotal_amount ?? 0),
 'tax_amount' => (float) ($bill->tax_amount ?? 0),
 'total_amount' => (float) ($bill->total_amount ?? 0),
 'currency' => $bill->currency ?? 'USD',
 'rendered_at' => current_time('mysql'),
 ];
 }

 public static function list_credit_notes($org_id, $vendor_id = 0, $bill_id = 0) {
 global $wpdb;
 self::ensure_schema();

 $table = OraBooks_Database::table('vendor_credit_notes');
 if ($bill_id > 0) {
 $rows = $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM {$table} WHERE org_id = %d AND bill_id = %d ORDER BY created_at DESC LIMIT 50",
 (int) $org_id,
 (int) $bill_id
 ));
 } elseif ($vendor_id > 0) {
 $rows = $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM {$table} WHERE org_id = %d AND vendor_id = %d ORDER BY created_at DESC LIMIT 50",
 (int) $org_id,
 (int) $vendor_id
 ));
 } else {
 $rows = $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM {$table} WHERE org_id = %d ORDER BY created_at DESC LIMIT 50",
 (int) $org_id
 ));
 }

 return array_map([self::class, 'format_credit_note'], $rows ?: []);
 }

 public static function list_payments($org_id, $args = []) {
 global $wpdb;
 self::ensure_schema();

 $table = OraBooks_Database::table('vendor_payments');
 $where = ['org_id = %d'];
 $params = [(int) $org_id];

 if (!empty($args['vendor_id'])) {
 $where[] = 'vendor_id = %d';
 $params[] = (int) $args['vendor_id'];
 }
 if (!empty($args['bill_id'])) {
 $alloc_table = OraBooks_Database::table('vendor_payment_allocations');
 $where[] = "id IN (SELECT payment_id FROM {$alloc_table} WHERE bill_id = %d)";
 $params[] = (int) $args['bill_id'];
 }

 $sql = "SELECT * FROM {$table} WHERE ". implode(' AND ', $where). " ORDER BY payment_date DESC, id DESC LIMIT 100";
 $rows = $wpdb->get_results($wpdb->prepare($sql, $params));

 $reversed_ids = [];
 if ($rows) {
 $reversed_ids = array_map('intval', $wpdb->get_col($wpdb->prepare(
 "SELECT DISTINCT reverses_payment_id FROM {$table}
 WHERE org_id = %d AND reverses_payment_id IS NOT NULL",
 (int) $org_id
 )) ?: []);
 }

 return array_map(static function ($row) use ($reversed_ids) {
 $formatted = self::format_payment($row);
 if ($formatted && in_array((int) $formatted['id'], $reversed_ids, true)) {
 $formatted['can_reverse'] = false;
 }
 return $formatted;
 }, $rows ?: []);
 }

 public function daily_vendor_statement_snapshot() {
 global $wpdb;
 self::ensure_schema();

 $month = current_time('Y-m');
 $vendors = $wpdb->get_results("SELECT * FROM ". OraBooks_Database::table('vendors'). " WHERE is_active = 1");
 $snap_table = OraBooks_Database::table('vendor_statement_snapshots');
 $bills_table = OraBooks_Database::table('bills');

 foreach ($vendors ?: [] as $vendor) {
 $open = $wpdb->get_results($wpdb->prepare(
 "SELECT id, bill_number, due_date, total_amount, paid_amount, payment_status
 FROM {$bills_table}
 WHERE org_id = %d AND vendor_id = %d AND workflow_status = 'posted'
 AND payment_status IN ('unpaid','partial')",
 (int) $vendor->org_id,
 (int) $vendor->id
 ));
 $payable = 0.0;
 foreach ($open ?: [] as $bill) {
 $payable += max(0, (float) $bill->total_amount - (float) ($bill->paid_amount ?? 0));
 }

 $wpdb->replace(
 $snap_table,
 [
 'org_id' => (int) $vendor->org_id,
 'vendor_id' => (int) $vendor->id,
 'statement_month' => $month,
 'payable_balance' => $payable,
 'credit_balance' => (float) ($vendor->credit_balance ?? 0),
 'aging_json' => wp_json_encode(OraBooks_Vendors::get_ap_aging((int) $vendor->org_id)),
 ],
 ['%d', '%d', '%s', '%f', '%f', '%s']
 );
 }
 }

 public static function format_vendor($row) {
 if (!$row) {
 return null;
 }
 return [
 'id' => (int) $row->id,
 'org_id' => (int) $row->org_id,
 'name' => $row->name,
 'email' => $row->email ?? '',
 'tax_id' => $row->tax_id ?? '',
 'payment_terms' => (int) ($row->payment_terms ?? 30),
 'default_currency' => $row->default_currency ?? 'USD',
 'auto_apply_credit' => (int) ($row->auto_apply_credit ?? 1),
 'payable_balance' => (float) ($row->payable_balance ?? 0),
 'credit_balance' => (float) ($row->credit_balance ?? 0),
 'notes' => $row->notes ?? '',
 'is_active' => (int) ($row->is_active ?? 1),
 ];
 }

 public static function format_bill($row) {
 if (!$row) {
 return null;
 }
 return [
 'id' => (int) $row->id,
 'org_id' => (int) $row->org_id,
 'vendor_id' => (int) $row->vendor_id,
 'vendor_name' => $row->vendor_name ?? '',
 'bill_number' => $row->bill_number,
 'bill_date' => $row->bill_date,
 'due_date' => $row->due_date,
 'description' => $row->description ?? '',
 'subtotal_amount' => (float) ($row->subtotal_amount ?? 0),
 'tax_amount' => (float) ($row->tax_amount ?? 0),
 'total_amount' => (float) ($row->total_amount ?? 0),
 'paid_amount' => (float) ($row->paid_amount ?? 0),
 'currency' => $row->currency ?? 'USD',
 'workflow_status' => $row->workflow_status,
 'payment_status' => $row->payment_status,
 'lock_status' => $row->lock_status ?? 'unlocked',
 'journal_id' => !empty($row->journal_id) ? (int) $row->journal_id: null,
 'posted_at' => $row->posted_at ?? null,
 ];
 }

 public static function format_payment($row) {
 if (!$row) {
 return null;
 }
 $type = $row->type ?? 'payment';
 return [
 'id' => (int) $row->id,
 'org_id' => (int) $row->org_id,
 'vendor_id' => (int) $row->vendor_id,
 'payment_date' => $row->payment_date,
 'amount' => (float) $row->amount,
 'payment_method' => $row->payment_method,
 'type' => $type,
 'reference' => $row->reference ?? '',
 'notes' => $row->notes ?? '',
 'reverses_payment_id' => !empty($row->reverses_payment_id) ? (int) $row->reverses_payment_id: null,
 'can_reverse' => $type === 'payment' && (float) $row->amount > 0,
 'created_at' => $row->created_at ?? null,
 ];
 }

 public static function format_credit_note($row) {
 if (!$row) {
 return null;
 }
 return [
 'id' => (int) $row->id,
 'org_id' => (int) $row->org_id,
 'vendor_id' => (int) $row->vendor_id,
 'bill_id' => !empty($row->bill_id) ? (int) $row->bill_id: null,
 'credit_note_number' => $row->credit_note_number,
 'credit_date' => $row->credit_date,
 'amount' => (float) $row->amount,
 'reason' => $row->reason,
 'is_adjustment' => (int) ($row->is_adjustment ?? 0),
 'adjustment_account_code' => $row->adjustment_account_code ?? null,
 'requires_second_approval' => (int) ($row->requires_second_approval ?? 0),
 'approved_by' => !empty($row->approved_by) ? (int) $row->approved_by: null,
 'approved_at' => $row->approved_at ?? null,
 'workflow_status' => $row->workflow_status,
 'journal_id' => !empty($row->journal_id) ? (int) $row->journal_id: null,
 'created_at' => $row->created_at ?? null,
 ];
 }

 public static function get_credit_note($id, $org_id) {
 global $wpdb;
 return $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM ". OraBooks_Database::table('vendor_credit_notes'). " WHERE id = %d AND org_id = %d",
 (int) $id,
 (int) $org_id
 ));
 }

 private static function create_credit_note_journal($note, $user_id) {
 if (!class_exists('OraBooks_Posting')) {
 return null;
 }

 $amount = (float) $note->amount;
 $config = self::get_ap_config((int) $note->org_id);
 $credit_code = (int) $note->is_adjustment === 1
 ? (!empty($note->adjustment_account_code) ? $note->adjustment_account_code: $config->vendor_adjustment_account)
: '5000';

 $journal_id = OraBooks_Posting::create_journal([
 'org_id' => (int) $note->org_id,
 'transaction_date' => $note->credit_date,
 'source_type' => 'vendor_credit_note',
 'source_id' => (int) $note->id,
 'idempotency_key' => 'vendor_credit_note_'. (int) $note->id,
 ], (int) $user_id);

 if (is_wp_error($journal_id)) {
 return $journal_id;
 }

 OraBooks_Posting::add_lines($journal_id, [
 ['account_code' => '2000', 'debit' => $amount, 'credit' => 0, 'description' => 'Vendor credit note '. $note->credit_note_number],
 ['account_code' => $credit_code, 'debit' => 0, 'credit' => $amount, 'description' => 'Vendor credit note '. $note->credit_note_number],
 ]);

 OraBooks_Posting::submit_journal($journal_id, (int) $user_id);
 OraBooks_Posting::approve_journal($journal_id, (int) $user_id);
 OraBooks_Posting::post_journal($journal_id, (int) $user_id);

 return $journal_id;
 }

 private static function insert_allocation($org_id, $vendor_id, $payment_id, $bill_id, $amount, $method) {
 global $wpdb;
 $wpdb->insert(
 OraBooks_Database::table('vendor_payment_allocations'),
 [
 'org_id' => (int) $org_id,
 'vendor_id' => (int) $vendor_id,
 'payment_id' => (int) $payment_id,
 'bill_id' => (int) $bill_id,
 'amount' => round((float) $amount, 2),
 'allocation_method' => sanitize_text_field($method),
 ],
 ['%d', '%d', '%d', '%d', '%f', '%s']
 );
 }

 private static function update_bill_paid_amount($bill_id, $delta) {
 global $wpdb;
 $table = OraBooks_Database::table('bills');
 $bill = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $bill_id));
 if (!$bill) {
 return;
 }

 $paid = max(0, round((float) ($bill->paid_amount ?? 0) + (float) $delta, 2));
 $total = (float) $bill->total_amount;
 if ($paid >= $total) {
 $status = 'paid';
 $lock = 'locked';
 } elseif ($paid > 0) {
 $status = 'partial';
 $lock = $bill->lock_status ?? 'unlocked';
 } else {
 $status = 'unpaid';
 $lock = $bill->lock_status ?? 'unlocked';
 }

 $wpdb->update(
 $table,
 [
 'paid_amount' => $paid,
 'payment_status' => $status,
 'lock_status' => $lock,
 ],
 ['id' => (int) $bill_id],
 ['%f', '%s', '%s'],
 ['%d']
 );
 }

 private static function adjust_vendor_credit_balance($vendor_id, $org_id, $delta) {
 global $wpdb;
 $table = OraBooks_Database::table('vendors');
 $wpdb->query($wpdb->prepare(
 "UPDATE {$table} SET credit_balance = GREATEST(0, credit_balance + %f) WHERE id = %d AND org_id = %d",
 (float) $delta,
 (int) $vendor_id,
 (int) $org_id
 ));
 }

 private static function adjust_vendor_payable_balance($vendor_id, $org_id, $delta) {
 global $wpdb;
 $table = OraBooks_Database::table('vendors');
 $wpdb->query($wpdb->prepare(
 "UPDATE {$table} SET payable_balance = GREATEST(0, payable_balance + %f) WHERE id = %d AND org_id = %d",
 (float) $delta,
 (int) $vendor_id,
 (int) $org_id
 ));
 }

 private function require_ap_access($user_id, $org_id, $permissions = ['view_reports']) {
 if (!$user_id) {
 orabooks_json_error('Not authenticated', 401);
 }

 $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
 if (is_wp_error($isolation)) {
 orabooks_json_error($isolation->get_error_message, 403);
 }

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

 public function ajax_reverse_payment() {
 $user_id = orabooks_get_current_user_id;
 $org_id = (int) ($_POST['org_id'] ?? 0);
 $payment_id = (int) ($_POST['payment_id'] ?? 0);
 $this->require_ap_access($user_id, $org_id, ['manage_org_settings', 'approve_journal']);
 $result = self::reverse_vendor_payment($org_id, $payment_id, $user_id, sanitize_text_field($_POST['reason'] ?? ''));
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message, 400);
 }
 orabooks_json_success($result);
 }

 public function ajax_payments_list() {
 $user_id = orabooks_get_current_user_id;
 $org_id = (int) ($_GET['org_id'] ?? $_POST['org_id'] ?? 0);
 $this->require_ap_access($user_id, $org_id);
 orabooks_json_success(['payments' => self::list_payments($org_id, [
 'vendor_id' => (int) ($_GET['vendor_id'] ?? $_POST['vendor_id'] ?? 0),
 'bill_id' => (int) ($_GET['bill_id'] ?? $_POST['bill_id'] ?? 0),
 ])]);
 }

 public function ajax_submit_credit_note() {
 $user_id = orabooks_get_current_user_id;
 $org_id = (int) ($_POST['org_id'] ?? 0);
 $this->require_ap_access($user_id, $org_id, ['manage_org_settings', 'approve_journal']);
 $result = self::submit_credit_note($org_id, (int) ($_POST['credit_note_id'] ?? 0), $user_id);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message, 400);
 }
 orabooks_json_success(['credit_note' => $result]);
 }

 public function ajax_approve_credit_note() {
 $user_id = orabooks_get_current_user_id;
 $org_id = (int) ($_POST['org_id'] ?? 0);
 $this->require_ap_access($user_id, $org_id, ['approve_journal']);
 $result = self::approve_credit_note($org_id, (int) ($_POST['credit_note_id'] ?? 0), $user_id);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message, 400);
 }
 orabooks_json_success(['credit_note' => $result]);
 }

 public function ajax_post_credit_note() {
 $user_id = orabooks_get_current_user_id;
 $org_id = (int) ($_POST['org_id'] ?? 0);
 $this->require_ap_access($user_id, $org_id, ['approve_journal', 'manage_org_settings']);
 $result = self::post_credit_note($org_id, (int) ($_POST['credit_note_id'] ?? 0), $user_id);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message, 400);
 }
 orabooks_json_success(['credit_note' => $result]);
 }

 public function ajax_void_credit_note() {
 $user_id = orabooks_get_current_user_id;
 $org_id = (int) ($_POST['org_id'] ?? 0);
 $this->require_ap_access($user_id, $org_id, ['manage_org_settings', 'approve_journal']);
 $result = self::void_credit_note($org_id, (int) ($_POST['credit_note_id'] ?? 0), $user_id, sanitize_text_field($_POST['reason'] ?? ''));
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message, 400);
 }
 orabooks_json_success(['credit_note' => $result]);
 }

 public function ajax_credit_notes_list() {
 $user_id = orabooks_get_current_user_id;
 $org_id = (int) ($_GET['org_id'] ?? $_POST['org_id'] ?? 0);
 $this->require_ap_access($user_id, $org_id);
 orabooks_json_success(['credit_notes' => self::list_credit_notes(
 $org_id,
 (int) ($_GET['vendor_id'] ?? $_POST['vendor_id'] ?? 0),
 (int) ($_GET['bill_id'] ?? $_POST['bill_id'] ?? 0)
 )]);
 }

 public function ajax_ap_config_get() {
 $user_id = orabooks_get_current_user_id;
 $org_id = (int) ($_GET['org_id'] ?? 0);
 $this->require_ap_access($user_id, $org_id);
 $config = self::get_ap_config($org_id);
 orabooks_json_success([
 'config' => [
 'auto_post_bill_on_approve' => (int) ($config->auto_post_bill_on_approve ?? 1),
 'auto_apply_vendor_credit' => (int) ($config->auto_apply_vendor_credit ?? 1),
 'adjustment_threshold' => (float) ($config->adjustment_threshold ?? 1000),
 'vendor_adjustment_account' => $config->vendor_adjustment_account ?? '5000',
 ],
 ]);
 }

 public function ajax_ap_config_save() {
 $user_id = orabooks_get_current_user_id;
 $org_id = (int) ($_POST['org_id'] ?? 0);
 $this->require_ap_access($user_id, $org_id, ['manage_org_settings']);
 orabooks_json_success(['config' => self::save_ap_config($org_id, $_POST)]);
 }

 public function ajax_statements_list() {
 $user_id = orabooks_get_current_user_id;
 $org_id = (int) ($_GET['org_id'] ?? 0);
 $vendor_id = (int) ($_GET['vendor_id'] ?? 0);
 $this->require_ap_access($user_id, $org_id);
 global $wpdb;
 $table = OraBooks_Database::table('vendor_statement_snapshots');
 $rows = $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM {$table} WHERE org_id = %d AND vendor_id = %d ORDER BY statement_month DESC LIMIT 24",
 $org_id,
 $vendor_id
 ));
 orabooks_json_success(['statements' => $rows ?: []]);
 }

 public function ajax_vendor_get() {
 $user_id = orabooks_get_current_user_id;
 $org_id = (int) ($_GET['org_id'] ?? 0);
 $vendor_id = (int) ($_GET['vendor_id'] ?? 0);
 $this->require_ap_access($user_id, $org_id);
 $vendor = OraBooks_Vendors::get_vendor($vendor_id, $org_id);
 if (!$vendor) {
 orabooks_json_error('Vendor not found', 404);
 }
 orabooks_json_success(['vendor' => self::format_vendor($vendor)]);
 }

 public function ajax_bill_get() {
 $user_id = orabooks_get_current_user_id;
 $org_id = (int) ($_GET['org_id'] ?? 0);
 $bill_id = (int) ($_GET['bill_id'] ?? 0);
 $this->require_ap_access($user_id, $org_id);
 $bill = OraBooks_Vendors::get_bill($bill_id, $org_id);
 if (!$bill) {
 orabooks_json_error('Bill not found', 404);
 }
 orabooks_json_success(['bill' => self::format_bill($bill)]);
 }
}
