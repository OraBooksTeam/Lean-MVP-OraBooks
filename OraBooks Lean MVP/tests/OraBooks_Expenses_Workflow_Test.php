<?php
/**
 * Workflow integration tests for OraBooks_Expenses ( Phase 2)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Expenses_Workflow_Test extends TestCase
{
 protected function setUp: void
 {
 parent::setUp;

 global $wpdb;
 $wpdb->test_get_var_callback = null;
 $wpdb->test_get_row_callback = null;
 $wpdb->test_get_results_callback = null;
 $wpdb->test_query_callback = null;
 $wpdb->test_insert_callback = null;
 $wpdb->test_update_callback = null;
 $wpdb->insert_id = 0;
 $wpdb->last_query = '';

 $GLOBALS['orabooks_test_current_user_id'] = 1;
 $GLOBALS['orabooks_test_current_user_can'] = true;
 $GLOBALS['orabooks_test_use_insert_id'] = null;
 $GLOBALS['orabooks_test_options'] = [
 'orabooks_expense_auto_post_on_approve' => false,
 ];
 $GLOBALS['orabooks_test_log_events'] = [];
 $GLOBALS['orabooks_test_publish_event_result'] = 100;
 unset($GLOBALS['orabooks_test_publish_event_override']);
 }

 private function expense(array $overrides = []): object
 {
 return (object) array_merge([
 'id' => 55,
 'org_id' => 9,
 'vendor' => 'Office Depot',
 'vendor_tax_id' => null,
 'invoice_number' => 'RCP-000055',
 'transaction_date' => '2026-06-18',
 'due_date' => null,
 'subtotal' => 95.00,
 'tax_amount' => 5.00,
 'tax_rate' => 5.00,
 'total_amount' => 100.00,
 'currency' => 'USD',
 'payment_method' => 'Credit Card',
 'category' => 'Office Supplies',
 'merchant_address' => null,
 'description' => 'Printer paper',
 'ocr_confidence' => 88.0,
 'ocr_risk_level' => 'low',
 'ocr_data' => wp_json_encode(['fields' => []]),
 'ocr_provider' => 'mvp-stub',
 'ocr_model_version' => 'mvp-stub-1.0',
 'ocr_snapshot_hash' => 'hash-55',
 'workflow_status' => 'draft',
 'payment_status' => 'unpaid',
 'lock_status' => 'unlocked',
 'idempotency_key' => null,
 'attachment_id' => 3,
 'journal_id' => null,
 'created_by' => 1,
 'approved_by' => null,
 'posted_by' => null,
 'approved_at' => null,
 'posted_at' => null,
 'created_at' => '2026-06-18 09:00:00',
 'updated_at' => '2026-06-18 09:00:00',
 ], $overrides);
 }

 /**
 * @return array{ai_review_inserts:array<int,array<string,mixed>>}
 */
 private function mock_expense_db(object &$expense): array
 {
 global $wpdb;

 $side_effects = ['ai_review_inserts' => []];
 $insert_seq = 900;

 $wpdb->test_query_callback = function {
 return true;
 };
 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'idempotency_key') !== false && stripos($query, 'classification') === false) {
 return null;
 }
 if (stripos($query, 'classification_rules') !== false && stripos($query, 'COUNT') !== false) {
 return 1;
 }
 if (stripos($query, 'ai_review_queue') !== false) {
 return null;
 }
 if (stripos($query, 'classification_idempotency_key') !== false) {
 return null;
 }
 return null;
 };
 $wpdb->test_get_row_callback = function ($query) use (&$expense) {
 if (stripos($query, 'expenses') !== false) {
 return $expense;
 }
 return null;
 };
 $wpdb->test_update_callback = function ($table, $data, $where) use (&$expense) {
 foreach ($data as $key => $value) {
 $expense->$key = $value;
 }
 return 1;
 };
 $wpdb->test_insert_callback = function ($table, $data) use (&$side_effects, &$insert_seq) {
 if (($data['resource_type'] ?? '') === 'expense' && array_key_exists('confidence_score', $data)) {
 $side_effects['ai_review_inserts'][] = $data;
 }
 global $wpdb;
 $wpdb->insert_id = ++$insert_seq;
 };

 return $side_effects;
 }

 #[Test]
 public function test_resolve_submit_route_high_confidence_low_risk_goes_to_approval() {
 $route = OraBooks_Expenses::resolve_submit_route(85.0, 'low');

 $this->assertSame('submit', $route['workflow_event']);
 $this->assertSame('submitted', $route['target_status']);
 $this->assertSame('expense_submitted', $route['event']);
 }

 #[Test]
 public function test_resolve_submit_route_threshold_boundary_submits_at_seventy() {
 $route = OraBooks_Expenses::resolve_submit_route(70.0, 'low');

 $this->assertSame('submitted', $route['target_status']);
 $this->assertSame('submit', $route['workflow_event']);
 }

 #[Test]
 public function test_resolve_submit_route_low_confidence_escalates_to_ai_review() {
 $route = OraBooks_Expenses::resolve_submit_route(69.9, 'low');

 $this->assertSame('ai_review', $route['target_status']);
 $this->assertSame('ai_review', $route['workflow_event']);
 $this->assertSame('expense_escalated', $route['event']);
 }

 #[Test]
 public function test_resolve_submit_route_elevated_risk_escalates_even_with_high_confidence() {
 foreach (['medium', 'high'] as $risk) {
 $route = OraBooks_Expenses::resolve_submit_route(95.0, $risk);
 $this->assertSame('ai_review', $route['target_status'], "Expected ai_review for risk={$risk}");
 }
 }

 #[Test]
 public function test_confirm_submit_routes_high_confidence_expense_to_submitted() {
 $expense = $this->expense(['ocr_confidence' => 88.0, 'ocr_risk_level' => 'low']);
 $this->mock_expense_db($expense);

 $result = OraBooks_Expenses::confirm_submit(55, 9, 1, 'idem-submit-1');

 $this->assertIsArray($result);
 $this->assertSame('submitted', $result['workflow_status']);
 $this->assertSame('submitted', $expense->workflow_status);
 }

 #[Test]
 public function test_ai_review_enqueue_inserts_expense_queue_row() {
 global $wpdb;

 $inserts = [];
 $wpdb->test_get_var_callback = function {
 return null;
 };
 $wpdb->test_insert_callback = function ($table, $data) use (&$inserts) {
 $inserts[] = $data;
 global $wpdb;
 $wpdb->insert_id = 501;
 };

 $result = OraBooks_Ai_Review::enqueue(
 9,
 'expense',
 55,
 null,
 [
 'confidence' => 62.0,
 'risk_level' => 'low',
 'escalation_reason' => 'expense_low_confidence',
 ],
 100.0
 );

 $this->assertIsArray($result);
 $queue_rows = array_values(array_filter($inserts, fn ($row) => ($row['resource_type'] ?? '') === 'expense'));
 $this->assertCount(1, $queue_rows);
 $this->assertSame('expense', $queue_rows[0]['resource_type']);
 }

 #[Test]
 public function test_confirm_submit_routes_low_confidence_expense_to_ai_review_queue() {
 $expense = $this->expense(['ocr_confidence' => 62.0, 'ocr_risk_level' => 'low']);
 $this->mock_expense_db($expense);

 $result = OraBooks_Expenses::confirm_submit(55, 9, 1, 'idem-review-1');

 $this->assertIsArray($result);
 $this->assertSame('ai_review', $result['workflow_status']);
 $this->assertSame('ai_review', $expense->workflow_status);

 $event_types = array_column($GLOBALS['orabooks_test_log_events'], 'event_type');
 $this->assertContains('expense_escalated_to_ai_review', $event_types);
 $this->assertContains('ai_review_enqueued', $event_types);
 }

 #[Test]
 public function test_confirm_submit_rejects_when_ocr_is_pending() {
 $expense = $this->expense(['ocr_confidence' => null]);
 $this->mock_expense_db($expense);

 $result = OraBooks_Expenses::confirm_submit(55, 9, 1, 'idem-pending');

 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('ocr_pending', $result->get_error_code());
 }

 #[Test]
 public function test_confirm_submit_rejects_non_draft_expense() {
 $expense = $this->expense(['workflow_status' => 'submitted']);
 $this->mock_expense_db($expense);

 $result = OraBooks_Expenses::confirm_submit(55, 9, 1, 'idem-invalid');

 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('invalid_status', $result->get_error_code());
 }

 #[Test]
 public function test_confirm_submit_rejects_duplicate_idempotency_key() {
 global $wpdb;

 $expense = $this->expense;
 $this->mock_expense_db($expense);
 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'idempotency_key') !== false) {
 return 99;
 }
 return null;
 };

 $result = OraBooks_Expenses::confirm_submit(55, 9, 1, 'duplicate-key');

 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('duplicate', $result->get_error_code());
 }

 #[Test]
 public function test_approve_expense_transitions_submitted_to_approved_without_auto_post() {
 $expense = $this->expense([
 'workflow_status' => 'submitted',
 'ocr_confidence' => 88.0,
 ]);
 $this->mock_expense_db($expense);

 $result = OraBooks_Expenses::approve_expense(55, 9, 2);

 $this->assertIsArray($result);
 $this->assertSame('approved', $result['workflow_status']);
 $this->assertSame('approved', $expense->workflow_status);
 $this->assertSame(2, (int) $expense->approved_by);
 $this->assertNotEmpty($expense->approved_at);
 }

 #[Test]
 public function test_approve_expense_accepts_ai_review_status() {
 $expense = $this->expense([
 'workflow_status' => 'ai_review',
 'ocr_confidence' => 55.0,
 'ocr_risk_level' => 'medium',
 ]);
 $this->mock_expense_db($expense);

 $result = OraBooks_Expenses::approve_expense(55, 9, 2);

 $this->assertIsArray($result);
 $this->assertSame('approved', $result['workflow_status']);
 }

 #[Test]
 public function test_reject_expense_returns_draft_from_submitted() {
 $expense = $this->expense(['workflow_status' => 'submitted']);
 $this->mock_expense_db($expense);

 $result = OraBooks_Expenses::reject_expense(55, 9, 2, 'Missing receipt details');

 $this->assertIsArray($result);
 $this->assertSame('draft', $result['workflow_status']);
 $this->assertSame('draft', $expense->workflow_status);
 }

 #[Test]
 public function test_post_expense_requires_approved_status() {
 $expense = $this->expense(['workflow_status' => 'submitted']);
 $this->mock_expense_db($expense);

 $result = OraBooks_Expenses::post_expense(55, 9, 2);

 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('invalid_status', $result->get_error_code());
 }

 #[Test]
 public function test_confirm_submit_allows_manual_entry_after_ocr_failure() {
 global $wpdb;

 $expense = $this->expense([
 'ocr_confidence' => null,
 'vendor' => 'Manual Vendor',
 'total_amount' => 150.00,
 'transaction_date' => '2026-06-18',
 ]);

 $side_effects = $this->mock_expense_db($expense);

 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'idempotency_key') !== false && stripos($query, 'classification') === false) {
 return null;
 }
 if (stripos($query, 'classification_rules') !== false && stripos($query, 'COUNT') !== false) {
 return 1;
 }
 if (stripos($query, 'ai_review_queue') !== false) {
 return null;
 }
 if (stripos($query, 'classification_idempotency_key') !== false) {
 return null;
 }
 return null;
 };

 $wpdb->test_get_row_callback = function ($query) use (&$expense) {
 if (stripos($query, 'ocr_processing_queue') !== false && stripos($query, 'ORDER BY id DESC') !== false) {
 return (object) ['status' => 'failed', 'error_message' => 'Provider timeout'];
 }
 if (stripos($query, 'expenses') !== false) {
 return $expense;
 }
 return null;
 };

 $result = OraBooks_Expenses::confirm_submit(55, 9, 1, 'idem-manual-fail');

 $this->assertIsArray($result);
 $this->assertSame('ai_review', $result['workflow_status']);

 $event_types = array_column($GLOBALS['orabooks_test_log_events'], 'event_type');
 $this->assertContains('expense_escalated_to_ai_review', $event_types);
 $this->assertContains('ai_review_enqueued', $event_types);
 }
}
