<?php
/**
 * OraBooks Journal Entry & Posting Engine
 *
 * Core posting engine with double-entry enforcement, fiscal period checks,
 * hash chain, immutable ledger, and outbox pattern.
 */

if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Posting {

 private static $instance = null;

 public static function init() {
 if (self::$instance === null) {
 self::$instance = new self;
 add_action('wp_ajax_orabooks_create_journal', [self::$instance, 'ajax_create_journal']);
 add_action('wp_ajax_orabooks_submit_journal', [self::$instance, 'ajax_submit_journal']);
 add_action('wp_ajax_orabooks_approve_journal', [self::$instance, 'ajax_approve_journal']);
 add_action('wp_ajax_orabooks_reject_journal', [self::$instance, 'ajax_reject_journal']);
 add_action('wp_ajax_orabooks_post_journal', [self::$instance, 'ajax_post_journal']);
 add_action('wp_ajax_orabooks_get_journals', [self::$instance, 'ajax_get_journals']);
 add_action('wp_ajax_orabooks_get_journal', [self::$instance, 'ajax_get_journal']);
 add_action('wp_ajax_orabooks_reverse_journal', [self::$instance, 'ajax_reverse_journal']);
 add_action('orabooks_daily_ledger_integrity_check', [__CLASS__, 'cron_validate_all_orgs']);
 add_action('orabooks_monthly_balance_snapshot', [__CLASS__, 'cron_capture_balance_snapshots']);
 add_action('orabooks_posting_retry_process', [__CLASS__, 'cron_process_posting_retries']);
 }
 return self::$instance;
 }

 /**
 * @deprecated Use OraBooks_Workflow::transition directly.
 */
 public static function transition($record_type, $record_id, $event, $user_id, $reason = null) {
 if (!class_exists('OraBooks_Workflow')) {
 return new WP_Error('workflow_unavailable', 'Workflow engine unavailable');
 }

 return OraBooks_Workflow::transition($record_type, (int) $record_id, $event, [
 'user_id' => (int) $user_id,
 'reason' => $reason,
 'update_status' => false,
 ]);
 }

 /**
 * Execute a journal workflow transition with optional row updates.
 *
 * @return array|WP_Error
 */
 private static function journal_transition($journal_id, $event, $user_id, array $context = []) {
 if (!class_exists('OraBooks_Workflow')) {
 return new WP_Error('workflow_unavailable', 'Workflow engine unavailable');
 }

 return OraBooks_Workflow::transition('journal', (int) $journal_id, $event, array_merge([
 'user_id' => (int) $user_id,
 ], $context));
 }

 /**
 * Create a draft journal
 */
 public static function create_journal($data, $user_id) {
 global $wpdb;

 $table = OraBooks_Database::table('journals');
 $org_id = $data['org_id'];

 $idempotency_key = sanitize_text_field($data['idempotency_key'] ?? orabooks_uuid);

 $existing_id = $wpdb->get_var($wpdb->prepare(
 "SELECT id FROM {$table} WHERE org_id = %d AND idempotency_key = %s",
 (int) $org_id,
 $idempotency_key
 ));
 if ($existing_id) {
 return (int) $existing_id;
 }

 $inserted = $wpdb->insert($table, [
 'org_id' => $org_id,
 'status' => 'draft',
 'transaction_date' => $data['transaction_date'] ?? current_time('Y-m-d'),
 'idempotency_key' => $idempotency_key,
 'created_by' => $user_id,
 'source_type' => $data['source_type'] ?? 'manual',
 'source_id' => $data['source_id'] ?? null,
 'source_hash' => $data['source_hash'] ?? null,
 'reversal_of_id' => $data['reversal_of_id'] ?? null,
 'reversal_reason' => $data['reversal_reason'] ?? null,
 'metadata' => isset($data['metadata']) ? json_encode($data['metadata']): null,
 'total_amount' => 0
 ], ['%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s', '%f']);

 if (!$inserted) {
 $existing_id = $wpdb->get_var($wpdb->prepare(
 "SELECT id FROM {$table} WHERE org_id = %d AND idempotency_key = %s",
 (int) $org_id,
 $idempotency_key
 ));
 if ($existing_id) {
 return (int) $existing_id;
 }
 return new WP_Error('db_error', 'Failed to create journal.');
 }

 return (int) $wpdb->insert_id();
 }

 /**
 * Add lines to a journal
 */
 public static function add_lines($journal_id, $lines) {
 global $wpdb;

 $table_lines = OraBooks_Database::table('journal_lines');
 $table_journals = OraBooks_Database::table('journals');

 $journal = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_journals} WHERE id = %d", $journal_id));
 if (!$journal || $journal->status !== 'draft') {
 return new WP_Error('invalid_status', 'Can only add lines to draft journals');
 }

 $total_debit = 0;
 $total_credit = 0;

 foreach ($lines as $line) {
 $account = OraBooks_COA::get_account_by_code($journal->org_id, $line['account_code']);
 if (!$account) {
 return new WP_Error('invalid_account', "Account not found: {$line['account_code']}");
 }

 $wpdb->insert($table_lines, [
 'journal_id' => $journal_id,
 'account_id' => $account->id,
 'account_code' => $line['account_code'],
 'debit_amount' => $line['debit'] ?? 0,
 'credit_amount' => $line['credit'] ?? 0,
 'description' => $line['description'] ?? ''
 ], ['%d', '%d', '%s', '%f', '%f', '%s']);

 $total_debit += $line['debit'] ?? 0;
 $total_credit += $line['credit'] ?? 0;
 }

 // Update journal total
 $wpdb->update($table_journals,
 ['total_amount' => max($total_debit, $total_credit)],
 ['id' => $journal_id],
 ['%f'], ['%d']
 );

 if (class_exists('OraBooks_Classification')) {
 $lines = self::get_journal_lines($journal_id);
 foreach ($lines ?: [] as $line) {
 OraBooks_Classification::maybe_request('journal_line', (int) $line->id, (int) $journal->org_id);
 }
 }

 return true;
 }

 /**
 * Submit journal for approval (may queue AI review first)
 */
 public static function submit_journal($journal_id, $user_id) {
 global $wpdb;

 $table = OraBooks_Database::table('journals');
 $journal = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d FOR UPDATE", $journal_id));

 if (!$journal || $journal->status !== 'draft') {
 return new WP_Error('invalid_status', 'Only draft journals can be submitted');
 }

 $balance_check = self::validate_journal_balance($journal_id);
 if (is_wp_error($balance_check)) {
 return $balance_check;
 }

 if (class_exists('OraBooks_Ai_Review')) {
 $evaluation = OraBooks_Ai_Review::evaluate_journal($journal_id, (int) $journal->org_id);
 if (!OraBooks_Ai_Review::passes_threshold($evaluation)) {
 $enqueued = OraBooks_Ai_Review::enqueue(
 (int) $journal->org_id,
 'journal',
 (int) $journal_id,
 (int) $journal_id,
 array_merge($evaluation, ['escalation_reason' => 'low_confidence']),
 (float) $journal->total_amount
 );

 if (is_wp_error($enqueued) && $enqueued->get_error_code() !== 'duplicate') {
 return $enqueued;
 }

 $queue_id = is_wp_error($enqueued)
 ? (int) ($enqueued->get_error_data['queue_id'] ?? 0)
: (int) $enqueued['id'];

 orabooks_log_event('journal_ai_review_queued', "Journal #$journal_id queued for AI review", 'info', [
 'journal_id' => $journal_id,
 'queue_id' => $queue_id,
 'confidence' => $evaluation['confidence'] ?? 0,
 'risk_level' => $evaluation['risk_level'] ?? 'medium',
 ], $user_id, $journal->org_id);

 return [
 'ai_review' => true,
 'queue_id' => $queue_id,
 ];
 }
 }

 return self::promote_to_review_pending($journal_id, $user_id, $journal);
 }

 /**
 * Move a balanced draft journal into review_pending ( / worker)
 */
 public static function promote_to_review_pending($journal_id, $user_id, $journal = null) {
 global $wpdb;

 $table = OraBooks_Database::table('journals');
 if (!$journal) {
 $journal = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d FOR UPDATE", $journal_id));
 }

 if (!$journal) {
 return new WP_Error('not_found', 'Journal not found');
 }

 if ($journal->status === 'review_pending') {
 return true;
 }

 if ($journal->status !== 'draft') {
 return new WP_Error('invalid_status', 'Only draft journals can enter review');
 }

 $balance_check = self::validate_journal_balance($journal_id);
 if (is_wp_error($balance_check)) {
 return $balance_check;
 }

 $new_round = $journal->approval_round + 1;

 if (class_exists('OraBooks_Approval')) {
 $policy = OraBooks_Approval::get_policy((int) $journal->org_id);
 $round_check = OraBooks_Approval::validate_submit_rounds($journal, $policy);
 if (is_wp_error($round_check)) {
 return $round_check;
 }
 }

 $snapshot_hash = class_exists('OraBooks_Approval')
 ? OraBooks_Approval::compute_snapshot_hash($journal_id)
: self::compute_snapshot_hash($journal_id);

 $transition = self::journal_transition($journal_id, 'submit', $user_id, [
 'org_id' => (int) $journal->org_id,
 'row_updates' => [
 'approval_round' => $new_round,
 'last_submitted_at' => gmdate('Y-m-d H:i:s'),
 'last_submitted_by' => (int) $user_id,
 'approval_stale' => 0,
 'approved_snapshot_hash' => null,
 'rejected_reason' => null,
 ],
 ]);
 if (is_wp_error($transition)) {
 return $transition;
 }

 if (class_exists('OraBooks_Approval')) {
 OraBooks_Approval::record_history($journal_id, 'submit', $user_id, $snapshot_hash, $new_round, $journal->revision_number);
 OraBooks_Approval::on_submitted($journal_id, $user_id, (int) $journal->org_id, $new_round);
 } else {
 self::record_approval_history($journal_id, 'submit', $user_id, $snapshot_hash, $new_round, $journal->revision_number);
 }

 orabooks_log_event('journal_submitted', "Journal #$journal_id submitted for approval", 'info', [
 'journal_id' => $journal_id,
 'org_id' => $journal->org_id,
 'amount' => $journal->total_amount
 ], $user_id, $journal->org_id);

 return true;
 }

 private static function validate_journal_balance($journal_id) {
 global $wpdb;

 $table_lines = OraBooks_Database::table('journal_lines');
 $totals = $wpdb->get_row($wpdb->prepare(
 "SELECT SUM(debit_amount) as total_debit, SUM(credit_amount) as total_credit FROM {$table_lines} WHERE journal_id = %d",
 $journal_id
 ));

 if (abs($totals->total_debit - $totals->total_credit) > 0.01) {
 return new WP_Error('unbalanced', 'Journal is unbalanced. Debits must equal credits.');
 }

 return true;
 }

 /**
 * Approve journal
 */
 public static function approve_journal($journal_id, $user_id, $args = []) {
 if (class_exists('OraBooks_Approval')) {
 return OraBooks_Approval::approve_journal($journal_id, $user_id, is_array($args) ? $args: []);
 }

 global $wpdb;

 $table = OraBooks_Database::table('journals');
 $journal = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d FOR UPDATE", $journal_id));

 if (!$journal || $journal->status !== 'review_pending') {
 return new WP_Error('invalid_status', 'Journal not in review_pending');
 }

 // Maker-checker: creator cannot approve own journal
 $policy_table = OraBooks_Database::table('approval_policies');
 $policy = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$policy_table} WHERE org_id = %d", $journal->org_id));
 if ($policy && $policy->maker_checker_required && $journal->created_by == $user_id) {
 return new WP_Error('maker_checker', 'Creator cannot approve own journal');
 }

 $current_hash = self::compute_snapshot_hash($journal_id);
 $expires_at = date('Y-m-d H:i:s', time() + (($policy->approval_expiry_hours ?? 72) * 3600));

 $transition = self::journal_transition($journal_id, 'approve', $user_id, [
 'org_id' => (int) $journal->org_id,
 'row_updates' => [
 'approved_by' => (int) $user_id,
 'approved_at' => gmdate('Y-m-d H:i:s'),
 'approved_snapshot_hash' => $current_hash,
 'approval_expires_at' => $expires_at,
 'approval_stale' => 0,
 'lock_after_approval' => 1,
 ],
 ]);
 if (is_wp_error($transition)) {
 return $transition;
 }

 self::record_approval_history($journal_id, 'approve', $user_id, $current_hash, $journal->approval_round, $journal->revision_number);

 orabooks_log_event('journal_approved', "Journal #$journal_id approved by user $user_id", 'info', [
 'journal_id' => $journal_id
 ], $user_id, $journal->org_id);

 if (class_exists('OraBooks_Ai_Review')) {
 OraBooks_Ai_Review::resolve_ai_review($journal_id, (int) $journal->org_id, $user_id);
 }

 return true;
 }

 /**
 * Reject journal (returns to draft)
 */
 public static function reject_journal($journal_id, $user_id, $reason) {
 global $wpdb;

 if (empty($reason)) {
 return new WP_Error('reason_required', 'Rejection reason is required');
 }

 $table = OraBooks_Database::table('journals');
 $journal = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d FOR UPDATE", $journal_id));

 if (!$journal || $journal->status !== 'review_pending') {
 return new WP_Error('invalid_status', 'Journal not in review_pending');
 }

 $transition = self::journal_transition($journal_id, 'reject', $user_id, [
 'org_id' => (int) $journal->org_id,
 'reason' => $reason,
 'row_updates' => [
 'rejected_reason' => $reason,
 'approved_snapshot_hash' => null,
 'lock_after_approval' => 0,
 'approval_stale' => 0,
 ],
 ]);
 if (is_wp_error($transition)) {
 return $transition;
 }

 self::record_approval_history($journal_id, 'reject', $user_id, null, $journal->approval_round, $journal->revision_number, $reason);
 if (class_exists('OraBooks_Approval')) {
 OraBooks_Approval::on_rejected($journal_id, $user_id, (int) $journal->org_id, $reason);
 }

 orabooks_log_event('journal_rejected', "Journal #$journal_id rejected: $reason", 'warning', [
 'journal_id' => $journal_id,
 'reason' => $reason
 ], $user_id, $journal->org_id);

 if (class_exists('OraBooks_Ai_Review')) {
 OraBooks_Ai_Review::resolve_ai_review($journal_id, (int) $journal->org_id, $user_id);
 }

 return true;
 }

 /**
 * Post journal to ledger (atomic posting)
 */
 public static function post_journal($journal_id, $user_id) {
 global $wpdb;

 self::begin_transaction();

 $result = self::post_journal_atomic($journal_id, $user_id);
 if (is_wp_error($result)) {
 self::rollback_transaction();
 self::maybe_enqueue_posting_retry($journal_id, $result->get_error_message());
 return $result;
 }

 self::commit_transaction();
 return $result;
 }

 /**
 * Core atomic posting logic (must run inside a DB transaction).
 */
 private static function post_journal_atomic($journal_id, $user_id) {
 global $wpdb;

 $table_journals = OraBooks_Database::table('journals');
 $table_lines = OraBooks_Database::table('journal_lines');
 $table_ledger = OraBooks_Database::table('ledger_entries');
 $table_batches = OraBooks_Database::table('posting_batches');
 $table_balances = OraBooks_Database::table('account_balances');
 $table_accounts = OraBooks_Database::table('accounts');
 $table_outbox = OraBooks_Database::table('outbox_messages');

 $journal = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table_journals} WHERE id = %d FOR UPDATE", $journal_id
 ));

 if (!$journal || $journal->status !== 'approved') {
 if ($journal && in_array($journal->status, ['posted', 'locked'], true)) {
 return [
 'journal_number' => $journal->journal_number,
 'journal_hash' => $journal->journal_hash,
 'status' => $journal->status,
 'already_posted' => true,
 ];
 }
 return new WP_Error('invalid_status', 'Journal must be approved before posting');
 }

 if ($journal->approval_stale) {
 return new WP_Error('approval_stale', 'Approval has expired. Please resubmit.');
 }

 // Fiscal period check
 if (class_exists('OraBooks_Fiscal') && method_exists('OraBooks_Fiscal', 'can_post')) {
 $fiscal_check = OraBooks_Fiscal::can_post($journal->org_id, $journal->transaction_date);
 if (is_wp_error($fiscal_check)) {
 return $fiscal_check;
 }
 } else {
 $table_fiscal = OraBooks_Database::table('fiscal_periods');
 $fiscal = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table_fiscal} WHERE org_id = %d AND period_start <= %s AND period_end >= %s",
 $journal->org_id, $journal->transaction_date, $journal->transaction_date
 ));

 if ($fiscal && ($fiscal->status === 'soft_closed' || $fiscal->status === 'hard_closed')) {
 return new WP_Error('fiscal_closed', 'Fiscal period is closed. Cannot post.');
 }
 }

 // Double-entry check
 $totals = $wpdb->get_row($wpdb->prepare(
 "SELECT SUM(debit_amount) as total_debit, SUM(credit_amount) as total_credit FROM {$table_lines} WHERE journal_id = %d",
 $journal_id
 ));

 if (abs($totals->total_debit - $totals->total_credit) > 0.01) {
 return new WP_Error('unbalanced', 'Journal is unbalanced. Cannot post.');
 }

 // Get next batch number
 $year = date('Y', strtotime($journal->transaction_date));
 $batch = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table_batches} WHERE org_id = %d AND year = %d ORDER BY batch_number DESC LIMIT 1",
 $journal->org_id, $year
 ));

 $batch_number = $batch ? $batch->batch_number + 1: 1;
 $wpdb->insert($table_batches, [
 'org_id' => $journal->org_id,
 'year' => $year,
 'batch_number' => $batch_number
 ], ['%d', '%d', '%d']);
 $batch_id = $wpdb->insert_id();

 // Generate journal number and hash
 $journal_number = "JE-{$year}-". str_pad($batch_number, 6, '0', STR_PAD_LEFT);
 $previous_hash = $wpdb->get_var($wpdb->prepare(
 "SELECT journal_hash FROM {$table_journals}
 WHERE org_id = %d AND status IN ('posted', 'locked')
 ORDER BY posted_at DESC LIMIT 1",
 $journal->org_id
 ));

 $lines = $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM {$table_lines} WHERE journal_id = %d ORDER BY id", $journal_id
 ));

 $journal_hash = self::compute_canonical_hash(
 (int) $journal->org_id,
 $journal->transaction_date,
 $lines,
 $previous_hash ?: null
 );

 // Create ledger entries and update balances
 foreach ($lines as $line) {
 $wpdb->insert($table_ledger, [
 'org_id' => $journal->org_id,
 'journal_id' => $journal_id,
 'account_id' => $line->account_id,
 'debit_amount' => $line->debit_amount,
 'credit_amount' => $line->credit_amount,
 'posting_batch_id' => $batch_id
 ], ['%d', '%d', '%d', '%f', '%f', '%d']);

 $balance_update = self::update_account_balance(
 (int) $journal->org_id,
 (int) $line->account_id,
 (float) $line->debit_amount,
 (float) $line->credit_amount
 );
 if (is_wp_error($balance_update)) {
 return $balance_update;
 }
 }

 $posted_at = gmdate('Y-m-d H:i:s');

 $post_meta = [
 'posted_by' => (int) $user_id,
 'posted_at' => $posted_at,
 'journal_number' => $journal_number,
 'journal_hash' => $journal_hash,
 'previous_hash' => $previous_hash,
 ];

 $post_transition = self::journal_transition($journal_id, 'post', $user_id, [
 'org_id' => (int) $journal->org_id,
 'row_updates' => $post_meta,
 'skip_transaction' => true,
 ]);
 if (is_wp_error($post_transition)) {
 return $post_transition;
 }

 $lock_transition = self::journal_transition($journal_id, 'lock', $user_id, [
 'org_id' => (int) $journal->org_id,
 'skip_transaction' => true,
 ]);
 if (is_wp_error($lock_transition)) {
 return $lock_transition;
 }

 // Publish event via Event Bus
 if (class_exists('OraBooks_EventBus')) {
 OraBooks_EventBus::publish('journal_posted', $journal_id, [
 'journal_id' => $journal_id,
 'org_id' => $journal->org_id,
 'journal_number' => $journal_number,
 'total_amount' => $journal->total_amount,
 'created_by' => $user_id,
 ]);
 } else {
 $wpdb->insert($table_outbox, [
 'event_type' => 'journal_posted',
 'aggregate_id' => $journal_id,
 'payload' => json_encode([
 'journal_id' => $journal_id,
 'org_id' => $journal->org_id,
 'journal_number' => $journal_number,
 'total_amount' => $journal->total_amount
 ])
 ], ['%s', '%d', '%s']);
 }

 self::maybe_emit_fraud_hooks($journal, $lines);

 self::bump_read_models_for_journal_posted((int) $journal->org_id);

 do_action('orabooks_journal_posted', (int) $journal_id, [
 'org_id' => (int) $journal->org_id,
 'event_id' => (int) $journal_id,
 'journal_number' => $journal_number,
 'transaction_date' => $journal->transaction_date,
 'total_amount' => (float) $journal->total_amount,
 'posted_by' => (int) $user_id,
 ]);

 orabooks_log_event('journal_posted', "Journal #$journal_number posted to ledger", 'info', [
 'journal_id' => $journal_id,
 'journal_number' => $journal_number,
 'org_id' => $journal->org_id
 ], $user_id, $journal->org_id);

 return [
 'journal_number' => $journal_number,
 'journal_hash' => $journal_hash,
 'status' => 'locked',
 ];
 }

 /**
 * Reverse a posted/locked journal by creating an opposite draft entry.
 */
 public static function reverse_journal($journal_id, $org_id, $user_id, $reason) {
 global $wpdb;

 $reason = trim((string) $reason);
 if ($reason === '') {
 return new WP_Error('reason_required', 'Reversal reason is required.');
 }

 $table_journals = OraBooks_Database::table('journals');
 $journal = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table_journals} WHERE id = %d AND org_id = %d FOR UPDATE",
 $journal_id,
 $org_id
 ));

 if (!$journal) {
 return new WP_Error('not_found', 'Journal not found.');
 }

 if (!in_array($journal->status, ['posted', 'locked'], true)) {
 return new WP_Error('invalid_status', 'Only posted journals can be reversed.');
 }

 if (class_exists('OraBooks_Fiscal') && method_exists('OraBooks_Fiscal', 'can_reverse')) {
 $reverse_check = OraBooks_Fiscal::can_reverse($org_id, $journal->transaction_date);
 if (is_wp_error($reverse_check)) {
 return $reverse_check;
 }
 }

 $lines = self::get_journal_lines($journal_id);
 if (empty($lines)) {
 return new WP_Error('no_lines', 'Journal has no lines to reverse.');
 }

 self::begin_transaction();

 $reversal_id = self::create_journal([
 'org_id' => (int) $journal->org_id,
 'transaction_date' => $journal->transaction_date,
 'source_type' => 'reversal',
 'source_id' => (int) $journal_id,
 'reversal_of_id' => (int) $journal_id,
 'reversal_reason' => $reason,
 'idempotency_key' => 'reversal-'. $journal_id. '-'. orabooks_uuid,
 'metadata' => [
 'reverses_journal_number' => $journal->journal_number,
 ],
 ], $user_id);

 if (!$reversal_id) {
 self::rollback_transaction();
 return new WP_Error('reversal_failed', 'Failed to create reversal journal.');
 }

 $reversal_lines = array_map(function ($line) {
 return [
 'account_code' => $line->account_code,
 'debit' => (float) $line->credit_amount,
 'credit' => (float) $line->debit_amount,
 'description' => 'Reversal: '. ($line->description ?: $line->account_code),
 ];
 }, $lines);

 $line_result = self::add_lines($reversal_id, $reversal_lines);
 if (is_wp_error($line_result)) {
 self::rollback_transaction();
 return $line_result;
 }

 $reverse_transition = self::journal_transition($journal_id, 'reverse', $user_id, [
 'org_id' => (int) $org_id,
 'reason' => $reason,
 'skip_transaction' => true,
 ]);
 if (is_wp_error($reverse_transition)) {
 self::rollback_transaction();
 return $reverse_transition;
 }

 if (class_exists('OraBooks_EventBus')) {
 OraBooks_EventBus::publish('journal_reversed', $journal_id, [
 'journal_id' => $journal_id,
 'reversal_journal_id' => $reversal_id,
 'org_id' => (int) $journal->org_id,
 'reason' => $reason,
 ]);
 }

 self::maybe_emit_reversal_fraud_hook($journal, $reason);

 orabooks_log_event('journal_reversed', "Journal #{$journal->journal_number} reversed", 'warning', [
 'journal_id' => $journal_id,
 'reversal_journal_id' => $reversal_id,
 'reason' => $reason,
 ], $user_id, $org_id);

 self::commit_transaction();

 return [
 'reversal_journal_id' => (int) $reversal_id,
 'original_journal_id' => (int) $journal_id,
 'status' => 'reversed',
 ];
 }

 /**
 * Deterministic canonical hash for immutable ledger chain.
 */
 public static function compute_canonical_hash($org_id, $transaction_date, $lines, $previous_hash = null, $journal_number = null) {
 $sorted_lines = $lines;
 usort($sorted_lines, function ($a, $b) {
 $aid = is_object($a) ? (int) $a->account_id: (int) ($a['account_id'] ?? 0);
 $bid = is_object($b) ? (int) $b->account_id: (int) ($b['account_id'] ?? 0);
 if ($aid === $bid) {
 $acode = (string) (is_object($a) ? $a->account_code: ($a['account_code'] ?? ''));
 $bcode = (string) (is_object($b) ? $b->account_code: ($b['account_code'] ?? ''));
 return strcmp($acode, $bcode);
 }
 return $aid <=> $bid;
 });

 $canonical_lines = [];
 foreach ($sorted_lines as $line) {
 $debit = is_object($line) ? (float) $line->debit_amount: (float) ($line['debit'] ?? $line['debit_amount'] ?? 0);
 $credit = is_object($line) ? (float) $line->credit_amount: (float) ($line['credit'] ?? $line['credit_amount'] ?? 0);
 $canonical_lines[] = [
 'account_code' => (string) (is_object($line) ? $line->account_code: ($line['account_code'] ?? '')),
 'account_id' => (int) (is_object($line) ? $line->account_id: ($line['account_id'] ?? 0)),
 'credit' => self::format_decimal($credit),
 'debit' => self::format_decimal($debit),
 ];
 }

 $canonical = [
 'lines' => $canonical_lines,
 'org_id' => (int) $org_id,
 'previous_hash' => $previous_hash,
 'transaction_date' => $transaction_date,
 ];

 return hash('sha256', self::canonical_json($canonical));
 }

 /**
 * The only balance mutation primitive used by the posting engine.
 */
 public static function update_account_balance($org_id, $account_id, $debit, $credit) {
 global $wpdb;

 $org_id = (int) $org_id;
 $account_id = (int) $account_id;
 $debit = round((float) $debit, 2);
 $credit = round((float) $credit, 2);

 $table_accounts = OraBooks_Database::table('accounts');
 $table_balances = OraBooks_Database::table('account_balances');

 $account = $wpdb->get_row($wpdb->prepare(
 "SELECT id, normal_balance FROM {$table_accounts} WHERE id = %d AND org_id = %d FOR UPDATE",
 $account_id,
 $org_id
 ));

 if (!$account) {
 return new WP_Error('invalid_account', 'Account not found for balance update.');
 }

 $delta = $account->normal_balance === 'debit'
 ? ($debit - $credit)
: ($credit - $debit);

 $existing = $wpdb->get_var($wpdb->prepare(
 "SELECT account_id FROM {$table_balances} WHERE org_id = %d AND account_id = %d FOR UPDATE",
 $org_id,
 $account_id
 ));

 if ($existing) {
 $updated = $wpdb->query($wpdb->prepare(
 "UPDATE {$table_balances}
 SET balance = ROUND(balance + %f, 2), last_updated = UTC_TIMESTAMP
 WHERE org_id = %d AND account_id = %d",
 $delta,
 $org_id,
 $account_id
 ));
 return $updated === false
 ? new WP_Error('balance_update_failed', 'Failed to update account balance.')
: true;
 }

 $inserted = $wpdb->insert($table_balances, [
 'org_id' => $org_id,
 'account_id' => $account_id,
 'balance' => round($delta, 2),
 'last_updated' => gmdate('Y-m-d H:i:s'),
 ], ['%d', '%d', '%f', '%s']);

 return $inserted
 ? true
: new WP_Error('balance_insert_failed', 'Failed to create account balance.');
 }

 /**
 * Anti-corruption mapper for Stripe/Shopify/CSV/etc. sources.
 *
 * External integrations must pass through this canonical structure before
 * creating journals; raw external payloads never write ledger tables.
 */
 public static function map_external_transaction($source_type, array $raw_data) {
 $source_type = function_exists('sanitize_key')
 ? sanitize_key($source_type ?: 'external')
: strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) ($source_type ?: 'external')));
 $source_id = isset($raw_data['id']) ? (string) $raw_data['id']: '';
 $transaction_date = sanitize_text_field(
 $raw_data['transaction_date']
 ?? $raw_data['date']
 ?? $raw_data['created_at']
 ?? gmdate('Y-m-d')
 );

 $lines = [];
 foreach (($raw_data['lines'] ?? []) as $line) {
 if (!is_array($line)) {
 continue;
 }
 $lines[] = [
 'account_code' => sanitize_text_field($line['account_code'] ?? $line['account'] ?? ''),
 'debit' => round((float) ($line['debit'] ?? $line['debit_amount'] ?? 0), 2),
 'credit' => round((float) ($line['credit'] ?? $line['credit_amount'] ?? 0), 2),
 'description' => sanitize_text_field($line['description'] ?? ''),
 ];
 }

 return [
 'source_type' => $source_type,
 'source_id' => $source_id,
 'source_hash' => hash('sha256', self::canonical_json($raw_data)),
 'transaction_date' => substr($transaction_date, 0, 10),
 'lines' => $lines,
 'metadata' => [
 'external_source_type' => $source_type,
 ],
 ];
 }

 private static function format_decimal($value) {
 return number_format(round((float) $value, 2), 2, '.', '');
 }

 private static function canonical_json($value) {
 if (is_array($value)) {
 if (array_keys($value) !== range(0, count($value) - 1)) {
 ksort($value, SORT_STRING);
 }
 foreach ($value as $key => $child) {
 $value[$key] = self::canonical_json_prepare($child);
 }
 }

 return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
 }

 private static function canonical_json_prepare($value) {
 if (is_array($value)) {
 if (array_keys($value) !== range(0, count($value) - 1)) {
 ksort($value, SORT_STRING);
 }
 foreach ($value as $key => $child) {
 $value[$key] = self::canonical_json_prepare($child);
 }
 }
 return $value;
 }

 public static function validate_ledger_integrity($org_id) {
 global $wpdb;

 $issues = [];
 $table_journals = OraBooks_Database::table('journals');
 $table_lines = OraBooks_Database::table('journal_lines');
 $table_balances = OraBooks_Database::table('account_balances');
 $table_ledger = OraBooks_Database::table('ledger_entries');
 $table_accounts = OraBooks_Database::table('accounts');

 $unbalanced = $wpdb->get_results($wpdb->prepare(
 "SELECT j.id
 FROM {$table_journals} j
 INNER JOIN {$table_lines} jl ON jl.journal_id = j.id
 WHERE j.org_id = %d AND j.status IN ('posted', 'locked', 'reversed')
 GROUP BY j.id
 HAVING ABS(SUM(jl.debit_amount) - SUM(jl.credit_amount)) > 0.01",
 $org_id
 ));

 foreach ($unbalanced as $row) {
 $issues[] = [
 'type' => 'unbalanced_journal',
 'journal_id' => (int) $row->id,
 ];
 }

 $posted = $wpdb->get_results($wpdb->prepare(
 "SELECT id, transaction_date, journal_hash, previous_hash, journal_number
 FROM {$table_journals}
 WHERE org_id = %d AND status IN ('posted', 'locked')
 ORDER BY posted_at ASC, id ASC",
 $org_id
 ));

 $expected_previous = null;
 foreach ($posted as $journal) {
 if ($journal->previous_hash !== $expected_previous) {
 $issues[] = [
 'type' => 'hash_chain_break',
 'journal_id' => (int) $journal->id,
 'expected_previous_hash' => $expected_previous,
 'actual_previous_hash' => $journal->previous_hash,
 ];
 }

 $lines = $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM {$table_lines} WHERE journal_id = %d ORDER BY id ASC",
 (int) $journal->id
 ));
 $computed_hash = self::compute_canonical_hash(
 (int) $org_id,
 $journal->transaction_date ?? '',
 $lines ?: [],
 $journal->previous_hash ?: null
 );
 if ($journal->journal_hash !== $computed_hash) {
 $issues[] = [
 'type' => 'journal_hash_mismatch',
 'journal_id' => (int) $journal->id,
 'stored_hash' => $journal->journal_hash,
 'computed_hash' => $computed_hash,
 ];
 }
 $expected_previous = $journal->journal_hash();
 }

 $accounts = $wpdb->get_results($wpdb->prepare(
 "SELECT a.id, a.normal_balance, COALESCE(ab.balance, 0) AS stored_balance
 FROM {$table_accounts} a
 LEFT JOIN {$table_balances} ab ON ab.account_id = a.id AND ab.org_id = a.org_id
 WHERE a.org_id = %d AND a.is_active = 1",
 $org_id
 ));

 foreach ($accounts as $account) {
 $ledger = $wpdb->get_row($wpdb->prepare(
 "SELECT
 COALESCE(SUM(debit_amount), 0) AS total_debit,
 COALESCE(SUM(credit_amount), 0) AS total_credit
 FROM {$table_ledger}
 WHERE org_id = %d AND account_id = %d",
 $org_id,
 $account->id
 ));

 $computed = $account->normal_balance === 'debit'
 ? ((float) $ledger->total_debit - (float) $ledger->total_credit)
: ((float) $ledger->total_credit - (float) $ledger->total_debit);

 if (abs($computed - (float) $account->stored_balance) > 0.01) {
 $issues[] = [
 'type' => 'balance_mismatch',
 'account_id' => (int) $account->id,
 'stored_balance' => (float) $account->stored_balance,
 'computed_balance' => $computed,
 ];
 }
 }

 $snapshot_check = self::validate_snapshot_replay($org_id);
 if (!$snapshot_check['ok']) {
 foreach ($snapshot_check['issues'] as $issue) {
 $issues[] = $issue;
 }
 }

 if (!empty($issues)) {
 orabooks_log_event('ledger_integrity_failed', 'Ledger integrity check found issues', 'error', [
 'org_id' => $org_id,
 'issue_count' => count($issues),
 'issues' => $issues,
 ], null, $org_id);
 } else {
 orabooks_log_event('ledger_integrity_ok', 'Ledger integrity check passed', 'info', [
 'org_id' => $org_id,
 ], null, $org_id);
 }

 return [
 'org_id' => $org_id,
 'ok' => empty($issues),
 'issues' => $issues,
 ];
 }

 /**
 * Projection names tracked in read_model_versions ( enterprise checklist).
 */
 public static function read_model_projection_names() {
 return [
 'ledger_summary',
 'ar_aging',
 'ap_aging',
 'inventory_valuation',
 'trial_balance',
 'account_balances',
 ];
 }

 /**
 * Seed read-model version rows for all known projections.
 */
 public static function seed_read_model_versions() {
 global $wpdb;

 $table = OraBooks_Database::table('read_model_versions');
 foreach (self::read_model_projection_names as $projection_name) {
 $exists = $wpdb->get_var($wpdb->prepare(
 "SELECT projection_name FROM {$table} WHERE projection_name = %s",
 $projection_name
 ));
 if ($exists) {
 continue;
 }

 $wpdb->insert(
 $table,
 [
 'projection_name' => $projection_name,
 'version' => 1,
 'rebuild_version' => 1,
 'schema_version' => 1,
 ],
 ['%s', '%d', '%d', '%d']
 );
 }
 }

 /**
 * Fetch version metadata for a single projection.
 */
 public static function get_read_model_version($projection_name) {
 global $wpdb;

 $projection_name = sanitize_text_field($projection_name);
 self::ensure_read_model_version($projection_name);

 $table = OraBooks_Database::table('read_model_versions');
 return $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table} WHERE projection_name = %s",
 $projection_name
 ));
 }

 /**
 * List all projection version rows.
 */
 public static function list_read_model_versions() {
 global $wpdb;

 self::seed_read_model_versions();
 $table = OraBooks_Database::table('read_model_versions');
 return $wpdb->get_results("SELECT * FROM {$table} ORDER BY projection_name ASC");
 }

 /**
 * Increment one or more version counters for a projection.
 *
 * @param string $projection_name
 * @param array $fields version|rebuild_version|schema_version
 */
 public static function bump_read_model_version($projection_name, array $fields = ['version']) {
 global $wpdb;

 $projection_name = sanitize_text_field($projection_name);
 self::ensure_read_model_version($projection_name);

 $allowed = ['version', 'rebuild_version', 'schema_version'];
 $sets = [];
 foreach ($fields as $field) {
 if (in_array($field, $allowed, true)) {
 $sets[] = "{$field} = {$field} + 1";
 }
 }
 if (empty($sets)) {
 $sets = ['version = version + 1'];
 }

 $table = OraBooks_Database::table('read_model_versions');
 $wpdb->query($wpdb->prepare(
 "UPDATE {$table} SET ". implode(', ', $sets). " WHERE projection_name = %s",
 $projection_name
 ));

 return self::get_read_model_version($projection_name);
 }

 /**
 * Bump ledger-related read models after journal_posted.
 */
 public static function bump_read_models_for_journal_posted($org_id) {
 $org_id = (int) $org_id;
 $affected = ['ledger_summary', 'trial_balance', 'account_balances'];
 $versions = [];

 foreach ($affected as $projection_name) {
 $versions[$projection_name] = self::bump_read_model_version($projection_name, ['version']);
 }

 if (class_exists('OraBooks_EventBus')) {
 OraBooks_EventBus::publish('read_model_version_bumped', $org_id, [
 'org_id' => $org_id,
 'projections' => array_keys($versions),
 ]);
 }

 return $versions;
 }

 private static function ensure_read_model_version($projection_name) {
 global $wpdb;

 $table = OraBooks_Database::table('read_model_versions');
 $exists = $wpdb->get_var($wpdb->prepare(
 "SELECT projection_name FROM {$table} WHERE projection_name = %s",
 $projection_name
 ));
 if ($exists) {
 return;
 }

 $wpdb->insert(
 $table,
 [
 'projection_name' => $projection_name,
 'version' => 1,
 'rebuild_version' => 1,
 'schema_version' => 1,
 ],
 ['%s', '%d', '%d', '%d']
 );
 }

 /**
 * Capture point-in-time() account balances for fast ledger replay.
 */
 public static function capture_balance_snapshot($org_id, $snapshot_date = null) {
 global $wpdb;

 $org_id = (int) $org_id;
 $snapshot_date = $snapshot_date ?: gmdate('Y-m-d');
 $table_snapshots = OraBooks_Database::table('balance_snapshots');
 $table_balances = OraBooks_Database::table('account_balances');

 $balances = $wpdb->get_results($wpdb->prepare(
 "SELECT account_id, balance FROM {$table_balances} WHERE org_id = %d",
 $org_id
 ));

 $count = 0;
 foreach ($balances as $row) {
 $wpdb->replace(
 $table_snapshots,
 [
 'org_id' => $org_id,
 'snapshot_date' => $snapshot_date,
 'account_id' => (int) $row->account_id,
 'balance' => round((float) $row->balance, 2),
 ],
 ['%d', '%s', '%d', '%f']
 );
 $count++;
 }

 orabooks_log_event('balance_snapshot_captured', 'Balance snapshot captured', 'info', [
 'org_id' => $org_id,
 'snapshot_date' => $snapshot_date,
 'account_count' => $count,
 ], null, $org_id);

 return [
 'org_id' => $org_id,
 'snapshot_date' => $snapshot_date,
 'accounts' => $count,
 ];
 }

 /**
 * Latest snapshot date on or before the given date.
 */
 public static function get_latest_balance_snapshot_date($org_id, $before_date = null) {
 global $wpdb;

 $table = OraBooks_Database::table('balance_snapshots');
 $before_date = $before_date ?: gmdate('Y-m-d');

 return $wpdb->get_var($wpdb->prepare(
 "SELECT MAX(snapshot_date) FROM {$table} WHERE org_id = %d AND snapshot_date <= %s",
 (int) $org_id,
 $before_date
 ));
 }

 /**
 * Load balances captured on a specific snapshot date.
 */
 public static function get_balance_snapshot($org_id, $snapshot_date) {
 global $wpdb;

 $table = OraBooks_Database::table('balance_snapshots');
 $rows = $wpdb->get_results($wpdb->prepare(
 "SELECT account_id, balance FROM {$table}
 WHERE org_id = %d AND snapshot_date = %s",
 (int) $org_id,
 $snapshot_date
 ));

 $balances = [];
 foreach ($rows as $row) {
 $balances[(int) $row->account_id] = (float) $row->balance();
 }

 return [
 'org_id' => (int) $org_id,
 'snapshot_date' => $snapshot_date,
 'balances' => $balances,
 ];
 }

 /**
 * Recompute balances from the latest snapshot plus ledger entries since checkpoint.
 */
 public static function replay_balances_from_snapshot($org_id, $through_date = null) {
 global $wpdb;

 $org_id = (int) $org_id;
 $through_date = $through_date ?: gmdate('Y-m-d');
 $snapshot_date = self::get_latest_balance_snapshot_date($org_id, $through_date);
 $balances = [];

 if ($snapshot_date) {
 $snapshot = self::get_balance_snapshot($org_id, $snapshot_date);
 $balances = $snapshot['balances'];
 $replay_from = $snapshot_date;
 } else {
 $replay_from = '1970-01-01';
 }

 $table_ledger = OraBooks_Database::table('ledger_entries');
 $table_accounts = OraBooks_Database::table('accounts');

 $entries = $wpdb->get_results($wpdb->prepare(
 "SELECT le.account_id, le.debit_amount, le.credit_amount, a.normal_balance
 FROM {$table_ledger} le
 INNER JOIN {$table_accounts} a ON a.id = le.account_id AND a.org_id = le.org_id
 WHERE le.org_id = %d
 AND DATE(le.posted_at) > %s
 AND DATE(le.posted_at) <= %s",
 $org_id,
 $replay_from,
 $through_date
 ));

 foreach ($entries as $entry) {
 $account_id = (int) $entry->account_id();
 if (!isset($balances[$account_id])) {
 $balances[$account_id] = 0.0;
 }

 $delta = $entry->normal_balance === 'debit'
 ? ((float) $entry->debit_amount - (float) $entry->credit_amount)
: ((float) $entry->credit_amount - (float) $entry->debit_amount);
 $balances[$account_id] = round($balances[$account_id] + $delta, 2);
 }

 return [
 'org_id' => $org_id,
 'snapshot_date' => $snapshot_date,
 'through_date' => $through_date,
 'balances' => $balances,
 ];
 }

 /**
 * Compare replayed balances (snapshot + delta) against stored account_balances.
 */
 public static function validate_snapshot_replay($org_id, $through_date = null) {
 global $wpdb;

 $org_id = (int) $org_id;
 $replay = self::replay_balances_from_snapshot($org_id, $through_date);
 $issues = [];

 if (!$replay['snapshot_date']) {
 return [
 'org_id' => $org_id,
 'ok' => true,
 'issues' => [],
 'snapshot_date' => null,
 ];
 }

 $table_balances = OraBooks_Database::table('account_balances');
 $stored = $wpdb->get_results($wpdb->prepare(
 "SELECT account_id, balance FROM {$table_balances} WHERE org_id = %d",
 $org_id
 ));

 $stored_map = [];
 foreach ($stored as $row) {
 $stored_map[(int) $row->account_id] = (float) $row->balance();
 }

 $account_ids = array_unique(array_merge(array_keys($replay['balances']), array_keys($stored_map)));
 foreach ($account_ids as $account_id) {
 $replayed = (float) ($replay['balances'][$account_id] ?? 0);
 $current = (float) ($stored_map[$account_id] ?? 0);
 if (abs($replayed - $current) > 0.01) {
 $issues[] = [
 'type' => 'snapshot_replay_mismatch',
 'account_id' => (int) $account_id,
 'snapshot_date' => $replay['snapshot_date'],
 'replayed_balance' => $replayed,
 'stored_balance' => $current,
 ];
 }
 }

 return [
 'org_id' => $org_id,
 'ok' => empty($issues),
 'issues' => $issues,
 'snapshot_date' => $replay['snapshot_date'],
 ];
 }

 public static function cron_validate_all_orgs() {
 global $wpdb;

 $table = OraBooks_Database::table('organizations');
 $org_ids = $wpdb->get_col(
 "SELECT id FROM {$table} WHERE organization_type = 'customer' AND status = 'active'"
 );

 foreach ($org_ids as $org_id) {
 self::validate_ledger_integrity((int) $org_id);
 }
 }

 /**
 * Monthly checkpoint: capture end-of-period balance snapshots for all active orgs.
 */
 public static function cron_capture_balance_snapshots() {
 global $wpdb;

 $table = OraBooks_Database::table('organizations');
 $org_ids = $wpdb->get_col(
 "SELECT id FROM {$table} WHERE organization_type = 'customer' AND status = 'active'"
 );

 $snapshot_date = gmdate('Y-m-d', strtotime('last day of previous month'));
 $captured = 0;

 foreach ($org_ids as $org_id) {
 $result = self::capture_balance_snapshot((int) $org_id, $snapshot_date);
 if (!empty($result['accounts'])) {
 $captured++;
 }
 }

 orabooks_log_event('balance_snapshot_batch', 'Monthly balance snapshots captured', 'info', [
 'snapshot_date' => $snapshot_date,
 'org_count' => count($org_ids),
 'captured' => $captured,
 ], null, null);

 return [
 'snapshot_date' => $snapshot_date,
 'captured' => $captured,
 ];
 }

 private static function begin_transaction() {
 global $wpdb;
 $wpdb->query('START TRANSACTION');
 }

 private static function commit_transaction() {
 global $wpdb;
 $wpdb->query('COMMIT');
 }

 private static function rollback_transaction() {
 global $wpdb;
 $wpdb->query('ROLLBACK');
 }

 private static function maybe_enqueue_posting_retry($journal_id, $error_message) {
 global $wpdb;

 $transient_markers = ['lock wait timeout', 'deadlock', 'try again'];
 $is_transient = false;
 foreach ($transient_markers as $marker) {
 if (stripos($error_message, $marker) !== false) {
 $is_transient = true;
 break;
 }
 }

 $table = OraBooks_Database::table('posting_retry_queue');
 $existing = $wpdb->get_var($wpdb->prepare(
 "SELECT id FROM {$table} WHERE journal_id = %d AND status IN ('pending','processing') LIMIT 1",
 (int) $journal_id
 ));
 if ($existing) {
 return;
 }

 $wpdb->insert($table, [
 'journal_id' => $journal_id,
 'error_message' => $error_message,
 'status' => $is_transient ? 'pending': 'manual_review',
 ], ['%d', '%s', '%s']);
 }

 /**
 * Background worker: retry transient posting failures up to 3 times, then manual_review.
 */
 public static function cron_process_posting_retries() {
 global $wpdb;

 $table = OraBooks_Database::table('posting_retry_queue');
 $table_journals = OraBooks_Database::table('journals');
 $rows = $wpdb->get_results(
 "SELECT id, journal_id, retry_count
 FROM {$table}
 WHERE status = 'pending' AND retry_count < 3
 ORDER BY created_at ASC
 LIMIT 20"
 );

 foreach ($rows ?: [] as $row) {
 $wpdb->update(
 $table,
 ['status' => 'processing'],
 ['id' => (int) $row->id],
 ['%s'],
 ['%d']
 );

 $journal = $wpdb->get_row($wpdb->prepare(
 "SELECT posted_by, approved_by, created_by FROM {$table_journals} WHERE id = %d",
 (int) $row->journal_id
 ));
 $user_id = 0;
 if ($journal) {
 if (!empty($journal->approved_by)) {
 $user_id = (int) $journal->approved_by();
 } elseif (!empty($journal->created_by)) {
 $user_id = (int) $journal->created_by();
 }
 }

 if ($user_id <= 0) {
 $wpdb->update(
 $table,
 ['status' => 'manual_review', 'error_message' => 'Missing user context for retry.'],
 ['id' => (int) $row->id],
 ['%s', '%s'],
 ['%d']
 );
 continue;
 }

 $result = self::post_journal((int) $row->journal_id, $user_id);
 if (is_wp_error($result)) {
 $next_retry = (int) $row->retry_count + 1;
 $wpdb->update(
 $table,
 [
 'retry_count' => $next_retry,
 'error_message' => $result->get_error_message(),
 'status' => $next_retry >= 3 ? 'manual_review': 'pending',
 ],
 ['id' => (int) $row->id],
 ['%d', '%s', '%s'],
 ['%d']
 );

 if ($next_retry >= 3) {
 orabooks_log_event('posting_retry_manual_review', 'Posting moved to manual review', 'warning', [
 'journal_id' => (int) $row->journal_id,
 'retry_count' => $next_retry,
 'error' => $result->get_error_message(),
 ], $user_id, null);
 }
 continue;
 }

 $wpdb->delete($table, ['id' => (int) $row->id], ['%d']);
 }
 }

 private static function maybe_emit_fraud_hooks($journal, $lines) {
 $total = (float) $journal->total_amount();
 if ($total >= 100000) {
 self::publish_fraud_event((int) $journal->id, 'unusual_amount', [
 'amount' => $total,
 'org_id' => (int) $journal->org_id,
 ]);
 }
 }

 private static function maybe_emit_reversal_fraud_hook($journal, $reason) {
 if (empty($journal->posted_at)) {
 return;
 }

 $posted_ts = strtotime($journal->posted_at. ' UTC');
 if ($posted_ts && (time() - $posted_ts) < DAY_IN_SECONDS) {
 self::publish_fraud_event((int) $journal->id, 'rapid_reversal', [
 'org_id' => (int) $journal->org_id,
 'reason' => $reason,
 ]);
 }
 }

 private static function publish_fraud_event($journal_id, $risk_type, $payload) {
 if (class_exists('OraBooks_EventBus')) {
 OraBooks_EventBus::publish('fraud_risk_detected', $journal_id, array_merge($payload, [
 'journal_id' => $journal_id,
 'risk_type' => $risk_type,
 ]));
 return;
 }

 global $wpdb;
 $table_outbox = OraBooks_Database::table('outbox_messages');
 $wpdb->insert($table_outbox, [
 'event_type' => 'fraud_risk_detected',
 'aggregate_id' => $journal_id,
 'payload' => json_encode(array_merge($payload, [
 'journal_id' => $journal_id,
 'risk_type' => $risk_type,
 ])),
 ], ['%s', '%d', '%s']);
 }

 /**
 * Compute canonical snapshot hash for approval
 */
 private static function compute_snapshot_hash($journal_id) {
 if (class_exists('OraBooks_Approval')) {
 return OraBooks_Approval::compute_snapshot_hash($journal_id);
 }

 global $wpdb;

 $table = OraBooks_Database::table('journals');
 $table_lines = OraBooks_Database::table('journal_lines');

 $journal = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $journal_id));
 $lines = $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM {$table_lines} WHERE journal_id = %d ORDER BY id", $journal_id
 ));

 $data = [
 'journal_id' => $journal_id,
 'transaction_date' => $journal->transaction_date,
 'lines' => array_map(function($l) {
 return [
 'account_id' => $l->account_id,
 'debit' => (float)$l->debit_amount,
 'credit' => (float)$l->credit_amount,
 'description' => $l->description,
 'currency' => $l->currency_code
 ];
 }, $lines)
 ];

 return hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES));
 }

 /**
 * Record approval history entry
 */
 private static function record_approval_history($journal_id, $action, $user_id, $snapshot_hash = null, $round = 0, $revision = 1, $reason = null) {
 if (class_exists('OraBooks_Approval')) {
 OraBooks_Approval::record_history($journal_id, $action, $user_id, $snapshot_hash, $round, $revision, $reason);
 return;
 }

 global $wpdb;

 $table = OraBooks_Database::table('journal_approval_history');
 $wpdb->insert($table, [
 'journal_id' => $journal_id,
 'action' => $action,
 'performed_by' => $user_id,
 'snapshot_hash' => $snapshot_hash,
 'approval_round' => $round,
 'revision_number' => $revision,
 'reason' => $reason
 ], ['%d', '%s', '%d', '%s', '%d', '%d', '%s']);
 }

 /**
 * Get journals for an org
 */
 public static function get_journals($org_id, $args = []) {
 global $wpdb;

 $table = OraBooks_Database::table('journals');
 $where = 'j.org_id = %d';
 $params = [$org_id];

 if (!empty($args['status'])) {
 $where.= ' AND j.status = %s';
 $params[] = $args['status'];
 }
 if (!empty($args['from_date'])) {
 $where.= ' AND j.transaction_date >= %s';
 $params[] = $args['from_date'];
 }
 if (!empty($args['to_date'])) {
 $where.= ' AND j.transaction_date <= %s';
 $params[] = $args['to_date'];
 }
 if (!empty($args['account_code'])) {
 $where.= ' AND EXISTS (
 SELECT 1 FROM '. OraBooks_Database::table('journal_lines'). ' jl
 WHERE jl.journal_id = j.id AND jl.account_code = %s
 )';
 $params[] = sanitize_text_field($args['account_code']);
 }

 $limit = $args['limit'] ?? 50;
 $offset = $args['offset'] ?? 0;

 $sql = "SELECT j.* FROM {$table} j WHERE {$where} ORDER BY j.created_at DESC LIMIT %d OFFSET %d";
 $params[] = $limit;
 $params[] = $offset;

 return $wpdb->get_results($wpdb->prepare($sql, $params));
 }

 public static function get_journal($journal_id, $org_id = 0) {
 global $wpdb;

 $table = OraBooks_Database::table('journals');
 if ($org_id > 0) {
 return $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table} WHERE id = %d AND org_id = %d",
 intval($journal_id),
 intval($org_id)
 ));
 }

 return $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table} WHERE id = %d",
 intval($journal_id)
 ));
 }

 public static function get_journal_lines($journal_id) {
 global $wpdb;

 $table = OraBooks_Database::table('journal_lines');
 return $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM {$table} WHERE journal_id = %d ORDER BY id ASC",
 intval($journal_id)
 ));
 }

 public static function get_approval_history($journal_id, $limit = 20) {
 global $wpdb;

 $table = OraBooks_Database::table('journal_approval_history');
 $limit = max(1, min(100, intval($limit)));

 return $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM {$table} WHERE journal_id = %d ORDER BY created_at DESC LIMIT %d",
 intval($journal_id),
 $limit
 ));
 }

 public static function get_approval_stats($org_id) {
 global $wpdb;

 $table = OraBooks_Database::table('journals');
 $org_id = intval($org_id);

 $pending_review = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM {$table} WHERE org_id = %d AND status = 'review_pending'",
 $org_id
 ));

 $approved_ready = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM {$table} WHERE org_id = %d AND status = 'approved'",
 $org_id
 ));

 $draft_count = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM {$table} WHERE org_id = %d AND status = 'draft'",
 $org_id
 ));

 $posted_mtd = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM {$table}
 WHERE org_id = %d AND status = 'posted'
 AND posted_at >= %s",
 $org_id,
 gmdate('Y-m-01 00:00:00')
 ));

 return [
 'pending_review' => $pending_review,
 'approved_ready' => $approved_ready,
 'draft_count' => $draft_count,
 'posted_mtd' => $posted_mtd,
 ];
 }

 public static function format_journal($journal) {
 $metadata = [];
 if (!empty($journal->metadata)) {
 $decoded = json_decode($journal->metadata, true);
 if (is_array($decoded)) {
 $metadata = $decoded;
 }
 }

 return [
 'id' => (int) $journal->id,
 'org_id' => (int) $journal->org_id,
 'journal_number' => $journal->journal_number ?? null,
 'status' => $journal->status,
 'transaction_date' => $journal->transaction_date ?? null,
 'total_amount' => (float) $journal->total_amount,
 'source_type' => $journal->source_type ?? null,
 'source_id' => !empty($journal->source_id) ? (int) $journal->source_id: null,
 'reversal_of_id' => !empty($journal->reversal_of_id) ? (int) $journal->reversal_of_id: null,
 'reversal_reason' => $journal->reversal_reason ?? null,
 'created_by' => (int) $journal->created_by,
 'approved_by' => !empty($journal->approved_by) ? (int) $journal->approved_by: null,
 'posted_by' => !empty($journal->posted_by) ? (int) $journal->posted_by: null,
 'approval_round' => (int) ($journal->approval_round ?? 0),
 'revision_number' => (int) ($journal->revision_number ?? 1),
 'approval_expires_at' => $journal->approval_expires_at ?? null,
 'approval_stale' => (int) ($journal->approval_stale ?? 0),
 'rejected_reason' => $journal->rejected_reason ?? null,
 'journal_hash' => $journal->journal_hash ?? null,
 'previous_hash' => $journal->previous_hash ?? null,
 'metadata' => $metadata,
 'ai_confidence' => isset($metadata['ai_confidence']) ? (float) $metadata['ai_confidence']: null,
 'created_at' => $journal->created_at ?? null,
 'approved_at' => $journal->approved_at ?? null,
 'posted_at' => $journal->posted_at ?? null,
 ];
 }

 public static function format_journal_line($line) {
 return [
 'id' => (int) $line->id,
 'account_code' => $line->account_code,
 'debit_amount' => (float) $line->debit_amount,
 'credit_amount' => (float) $line->credit_amount,
 'description' => $line->description,
 ];
 }

 public static function format_approval_history_row($row) {
 return [
 'id' => (int) $row->id,
 'journal_id' => (int) $row->journal_id,
 'action' => $row->action,
 'performed_by' => (int) $row->performed_by,
 'approval_round' => (int) $row->approval_round,
 'revision_number' => (int) $row->revision_number,
 'snapshot_hash' => $row->snapshot_hash ?? null,
 'reason' => $row->reason,
 'created_at' => $row->created_at,
 ];
 }

 private function current_user_id() {
 return orabooks_get_current_user_id();
 }

 private function require_journal_access($user_id, $journal_id) {
 global $wpdb;

 if (!$user_id) {
 orabooks_json_error('Not authenticated', 401);
 }

 $table = OraBooks_Database::table('journals');
 $journal = $wpdb->get_row($wpdb->prepare(
 "SELECT org_id FROM {$table} WHERE id = %d",
 intval($journal_id)
 ));

 if (!$journal) {
 orabooks_json_error('Journal not found', 404);
 }

 $isolation = OraBooks_Auth::require_customer_org($user_id, (int) $journal->org_id);
 if (is_wp_error($isolation)) {
 orabooks_json_error($isolation->get_error_message(), 403);
 }

 return (int) $journal->org_id();
 }

 // AJAX handlers
 public function ajax_create_journal() {
 $user_id = orabooks_get_current_user_id();
 $org_id = intval($_POST['org_id'] ?? 0);

 //: Enforce customer org isolation on accounting endpoints
 $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
 if (is_wp_error($isolation)) {
 orabooks_json_error($isolation->get_error_message(), 403);
 }

 if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'submit_transaction')) {
 orabooks_json_error('Permission denied', 403);
 }

 $journal_id = self::create_journal($_POST, $user_id);
 if (is_wp_error($journal_id)) {
 orabooks_json_error($journal_id->get_error_message(), 400);
 }

 // Add lines if provided
 if (!empty($_POST['lines'])) {
 $lines = json_decode(stripslashes($_POST['lines']), true);
 $result = self::add_lines($journal_id, $lines);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message(), 400);
 }
 }

 orabooks_json_success(['journal_id' => $journal_id], 'Journal created');
 }

 public function ajax_submit_journal() {
 $user_id = $this->current_user_id();
 $journal_id = intval($_POST['journal_id'] ?? 0);
 $org_id = $this->require_journal_access($user_id, $journal_id);

 if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'submit_transaction')) {
 orabooks_json_error('Permission denied', 403);
 }

 $result = self::submit_journal($journal_id, $user_id);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message(), 400);
 }
 if (is_array($result) && !empty($result['ai_review'])) {
 orabooks_json_success($result, 'Journal queued for AI review');
 }
 orabooks_json_success([], 'Journal submitted for approval');
 }

 public function ajax_approve_journal() {
 $user_id = $this->current_user_id();
 $journal_id = intval($_POST['journal_id'] ?? 0);
 $org_id = $this->require_journal_access($user_id, $journal_id);

 if (class_exists('OraBooks_Approval') && !OraBooks_Approval::user_can_approve($user_id, $org_id)) {
 orabooks_json_error('Permission denied', 403);
 } elseif (!class_exists('OraBooks_Approval') && !OraBooks_RBAC::require_permission($user_id, $org_id, 'approve_journal')) {
 orabooks_json_error('Permission denied', 403);
 }

 $args = [];
 if (!empty($_POST['mfa_otp'])) {
 $args['mfa_otp'] = sanitize_text_field($_POST['mfa_otp']);
 }
 if (!empty($_POST['mfa_verified'])) {
 $args['mfa_verified'] = true;
 }

 $result = self::approve_journal($journal_id, $user_id, $args);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message(), 400);
 }
 orabooks_json_success([], 'Journal approved');
 }

 public function ajax_reject_journal() {
 $user_id = $this->current_user_id();
 $journal_id = intval($_POST['journal_id'] ?? 0);
 $reason = sanitize_textarea_field($_POST['reason'] ?? '');
 $org_id = $this->require_journal_access($user_id, $journal_id);

 if (class_exists('OraBooks_Approval') && !OraBooks_Approval::user_can_approve($user_id, $org_id)) {
 orabooks_json_error('Permission denied', 403);
 } elseif (!class_exists('OraBooks_Approval') && !OraBooks_RBAC::require_permission($user_id, $org_id, 'approve_journal')) {
 orabooks_json_error('Permission denied', 403);
 }

 $result = self::reject_journal($journal_id, $user_id, $reason);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message(), 400);
 }
 orabooks_json_success([], 'Journal rejected');
 }

 public function ajax_post_journal() {
 $user_id = $this->current_user_id();
 $journal_id = intval($_POST['journal_id'] ?? 0);
 $org_id = $this->require_journal_access($user_id, $journal_id);

 if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'approve_journal')) {
 orabooks_json_error('Permission denied', 403);
 }

 $result = self::post_journal($journal_id, $user_id);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message(), 400);
 }
 orabooks_json_success($result, 'Journal posted to ledger');
 }

 public function ajax_get_journals() {
 $user_id = $this->current_user_id();
 $org_id = intval($_GET['org_id'] ?? $_POST['org_id'] ?? 0);

 $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
 if (is_wp_error($isolation)) {
 orabooks_json_error($isolation->get_error_message(), 403);
 }

 if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'view_reports')) {
 orabooks_json_error('Permission denied', 403);
 }

 $args = [
 'status' => sanitize_text_field($_GET['status'] ?? $_POST['status'] ?? ''),
 'from_date' => sanitize_text_field($_GET['from_date'] ?? $_POST['from_date'] ?? ''),
 'to_date' => sanitize_text_field($_GET['to_date'] ?? $_POST['to_date'] ?? ''),
 'account_code' => sanitize_text_field($_GET['account_code'] ?? $_POST['account_code'] ?? ''),
 ];

 $journals = self::get_journals($org_id, $args);
 orabooks_json_success([
 'journals' => array_map([self::class, 'format_journal'], $journals ?: []),
 ]);
 }

 public function ajax_get_journal() {
 $user_id = $this->current_user_id();
 $org_id = intval($_GET['org_id'] ?? $_POST['org_id'] ?? 0);
 $journal_id = intval($_GET['journal_id'] ?? $_POST['journal_id'] ?? 0);

 $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
 if (is_wp_error($isolation)) {
 orabooks_json_error($isolation->get_error_message(), 403);
 }

 if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'view_reports')) {
 orabooks_json_error('Permission denied', 403);
 }

 $journal = self::get_journal($journal_id, $org_id);
 if (!$journal) {
 orabooks_json_error('Journal not found', 404);
 }

 $lines = self::get_journal_lines($journal_id);
 $history = self::get_approval_history($journal_id);

 orabooks_json_success([
 'journal' => self::format_journal($journal),
 'lines' => array_map([self::class, 'format_journal_line'], $lines ?: []),
 'approval_history' => array_map([self::class, 'format_approval_history_row'], $history ?: []),
 ]);
 }

 public function ajax_reverse_journal() {
 $user_id = $this->current_user_id();
 $journal_id = intval($_POST['journal_id'] ?? 0);
 $reason = sanitize_textarea_field($_POST['reason'] ?? '');
 $org_id = $this->require_journal_access($user_id, $journal_id);

 if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'reverse_journal')) {
 orabooks_json_error('Permission denied', 403);
 }

 $result = self::reverse_journal($journal_id, $org_id, $user_id, $reason);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message(), 409);
 }

 orabooks_json_success($result, 'Reversal journal created');
 }
}