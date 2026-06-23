<?php
/**
 * Unit Tests for OraBooks_Posting
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Posting_Test extends TestCase
{
 protected function setUp: void
 {
 parent::setUp;

 $GLOBALS['orabooks_test_log_events'] = [];

 global $wpdb;
 $wpdb->test_get_var_callback = null;
 $wpdb->test_get_row_callback = null;
 $wpdb->test_get_results_callback = null;
 $wpdb->test_query_callback = null;
 $wpdb->test_insert_callback = null;
 $wpdb->test_update_callback = null;
 $wpdb->insert_id = 0;
 $wpdb->last_query = '';
 $wpdb->last_result = [];
 }

 #[Test]
 public function test_compute_canonical_hash_is_deterministic
 {
 $lines = [
 (object) [
 'account_id' => 2,
 'account_code' => '2000',
 'debit_amount' => 0,
 'credit_amount' => 100,
 ],
 (object) [
 'account_id' => 1,
 'account_code' => '1000',
 'debit_amount' => 100,
 'credit_amount' => 0,
 ],
 ];

 $hashA = OraBooks_Posting::compute_canonical_hash(5, '2026-06-15', $lines, null, 'JE-2026-000001');
 $hashB = OraBooks_Posting::compute_canonical_hash(5, '2026-06-15', array_reverse($lines), null, 'JE-2026-000001');
 $hashC = OraBooks_Posting::compute_canonical_hash(5, '2026-06-15', $lines, null, 'JE-2026-999999');

 $this->assertSame($hashA, $hashB);
 $this->assertSame($hashA, $hashC, 'Canonical journal hash must not depend on mutable sequence numbers.');
 $this->assertSame(64, strlen($hashA));
 }

 #[Test]
 public function test_map_external_transaction_returns_canonical_payload
 {
 $mapped = OraBooks_Posting::map_external_transaction('Stripe', [
 'id' => 'evt_123',
 'created_at' => '2026-06-20T08:00:00Z',
 'lines' => [
 ['account' => '1010', 'debit_amount' => '25.125', 'description' => 'Cash'],
 ['account_code' => '4010', 'credit' => '25.125', 'description' => 'Revenue'],
 ],
 ]);

 $this->assertSame('stripe', $mapped['source_type']);
 $this->assertSame('evt_123', $mapped['source_id']);
 $this->assertSame(64, strlen($mapped['source_hash']));
 $this->assertSame('2026-06-20', $mapped['transaction_date']);
 $this->assertSame(25.13, $mapped['lines'][0]['debit']);
 $this->assertSame('4010', $mapped['lines'][1]['account_code']);
 }

 #[Test]
 public function test_reverse_journal_requires_reason
 {
 $result = OraBooks_Posting::reverse_journal(1, 2, 9, ' ');
 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('reason_required', $result->get_error_code);
 }

 #[Test]
 public function test_reverse_journal_rejects_non_posted_status
 {
 global $wpdb;

 $wpdb->test_get_row_callback = function {
 return (object) [
 'id' => 1,
 'org_id' => 2,
 'status' => 'approved',
 'journal_number' => 'JE-2026-000001',
 'transaction_date' => '2026-06-15',
 'posted_at' => null,
 ];
 };

 $result = OraBooks_Posting::reverse_journal(1, 2, 9, 'Correction needed');
 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('invalid_status', $result->get_error_code);
 }

 #[Test]
 public function test_validate_ledger_integrity_reports_hash_chain_break
 {
 global $wpdb;

 $wpdb->test_get_results_callback = function ($query) {
 if (stripos($query, 'HAVING ABS') !== false) {
 return [];
 }
 if (stripos($query, 'status IN') !== false && stripos($query, 'journal_hash') !== false) {
 return [
 (object) ['id' => 10, 'journal_hash' => 'hash-b', 'previous_hash' => 'hash-a', 'journal_number' => 'JE-1'],
 (object) ['id' => 11, 'journal_hash' => 'hash-c', 'previous_hash' => 'wrong', 'journal_number' => 'JE-2'],
 ];
 }
 if (stripos($query, 'orabooks_accounts') !== false) {
 return [];
 }
 return [];
 };

 $result = OraBooks_Posting::validate_ledger_integrity(4);
 $this->assertFalse($result['ok']);
 $this->assertNotEmpty($result['issues']);
 $this->assertSame('hash_chain_break', $result['issues'][0]['type']);
 }

 #[Test]
 public function test_seed_read_model_versions_creates_all_projections
 {
 global $wpdb;
 $inserted = [];

 $wpdb->test_get_var_callback = function {
 return null;
 };
 $wpdb->test_insert_callback = function ($table, $data) use (&$inserted) {
 $inserted[] = $data['projection_name'];
 };

 OraBooks_Posting::seed_read_model_versions;

 $this->assertCount(count(OraBooks_Posting::read_model_projection_names), $inserted);
 $this->assertContains('ledger_summary', $inserted);
 $this->assertContains('account_balances', $inserted);
 }

 #[Test]
 public function test_bump_read_model_version_increments_requested_counters
 {
 global $wpdb;

 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'read_model_versions') !== false && stripos($query, 'SELECT projection_name') !== false) {
 return 'ledger_summary';
 }
 return null;
 };
 $wpdb->test_get_row_callback = function {
 return (object) [
 'projection_name' => 'ledger_summary',
 'version' => 2,
 'rebuild_version' => 1,
 'schema_version' => 1,
 ];
 };
 $wpdb->test_query_callback = function ($query) {
 $this->assertStringContainsString('version = version + 1', $query);
 $this->assertStringContainsString('rebuild_version = rebuild_version + 1', $query);
 return 1;
 };

 $row = OraBooks_Posting::bump_read_model_version('ledger_summary', ['version', 'rebuild_version']);
 $this->assertEquals('ledger_summary', $row->projection_name);
 }

 #[Test]
 public function test_bump_read_models_for_journal_posted_targets_ledger_projections
 {
 global $wpdb;
 $updated = [];

 $wpdb->test_get_var_callback = function {
 return 'ledger_summary';
 };
 $wpdb->test_get_row_callback = function ($query) use (&$updated) {
 if (stripos($query, 'read_model_versions') !== false) {
 preg_match("/projection_name = '([^']+)'/", $query, $matches);
 $name = $matches[1] ?? 'ledger_summary';
 $updated[] = $name;
 return (object) [
 'projection_name' => $name,
 'version' => 2,
 'rebuild_version' => 1,
 'schema_version' => 1,
 ];
 }
 return null;
 };
 $wpdb->test_query_callback = function {
 return 1;
 };

 $versions = OraBooks_Posting::bump_read_models_for_journal_posted(4);

 $this->assertArrayHasKey('ledger_summary', $versions);
 $this->assertArrayHasKey('trial_balance', $versions);
 $this->assertArrayHasKey('account_balances', $versions);
 $this->assertCount(3, $updated);
 }

 #[Test]
 public function test_capture_balance_snapshot_persists_all_account_balances
 {
 global $wpdb;
 $snapshots = [];

 $wpdb->test_get_results_callback = function ($query) {
 if (stripos($query, 'account_balances') !== false) {
 return [
 (object) ['account_id' => 1, 'balance' => 100.00],
 (object) ['account_id' => 2, 'balance' => -25.50],
 ];
 }
 return [];
 };
 $wpdb->test_insert_callback = function ($table, $data) use (&$snapshots) {
 if ($table === 'wp_test_orabooks_balance_snapshots') {
 $snapshots[] = $data;
 }
 };

 $result = OraBooks_Posting::capture_balance_snapshot(4, '2026-01-31');

 $this->assertEquals(2, $result['accounts']);
 $this->assertEquals('2026-01-31', $result['snapshot_date']);
 $this->assertCount(2, $snapshots);
 $this->assertEquals(100.00, $snapshots[0]['balance']);
 }

 #[Test]
 public function test_replay_balances_from_snapshot_applies_post_checkpoint_ledger_deltas
 {
 global $wpdb;

 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'MAX(snapshot_date)') !== false) {
 return '2026-01-31';
 }
 return null;
 };
 $wpdb->test_get_results_callback = function ($query) {
 if (stripos($query, 'balance_snapshots') !== false) {
 return [
 (object) ['account_id' => 1, 'balance' => 100.00],
 ];
 }
 if (stripos($query, 'ledger_entries') !== false) {
 return [
 (object) [
 'account_id' => 1,
 'debit_amount' => 50.00,
 'credit_amount' => 0,
 'normal_balance' => 'debit',
 ],
 ];
 }
 return [];
 };

 $replay = OraBooks_Posting::replay_balances_from_snapshot(4, '2026-02-15');

 $this->assertEquals('2026-01-31', $replay['snapshot_date']);
 $this->assertEquals(150.00, $replay['balances'][1]);
 }

 #[Test]
 public function test_validate_snapshot_replay_flags_mismatched_balances
 {
 global $wpdb;

 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'MAX(snapshot_date)') !== false) {
 return '2026-01-31';
 }
 return null;
 };
 $wpdb->test_get_results_callback = function ($query) {
 if (stripos($query, 'balance_snapshots') !== false) {
 return [(object) ['account_id' => 1, 'balance' => 100.00]];
 }
 if (stripos($query, 'ledger_entries') !== false) {
 return [];
 }
 if (stripos($query, 'account_balances') !== false) {
 return [(object) ['account_id' => 1, 'balance' => 90.00]];
 }
 return [];
 };

 $result = OraBooks_Posting::validate_snapshot_replay(4);

 $this->assertFalse($result['ok']);
 $this->assertSame('snapshot_replay_mismatch', $result['issues'][0]['type']);
 $this->assertEquals(100.00, $result['issues'][0]['replayed_balance']);
 $this->assertEquals(90.00, $result['issues'][0]['stored_balance']);
 }

 #[Test]
 public function test_validate_ledger_integrity_includes_snapshot_replay_issues
 {
 global $wpdb;

 $wpdb->test_get_results_callback = function ($query) {
 if (stripos($query, 'HAVING ABS') !== false) {
 return [];
 }
 if (stripos($query, 'status IN') !== false && stripos($query, 'journal_hash') !== false) {
 return [];
 }
 if (stripos($query, 'orabooks_accounts') !== false && stripos($query, 'stored_balance') !== false) {
 return [];
 }
 if (stripos($query, 'balance_snapshots') !== false) {
 return [(object) ['account_id' => 1, 'balance' => 100.00]];
 }
 if (stripos($query, 'ledger_entries') !== false && stripos($query, 'normal_balance') !== false) {
 return [];
 }
 if (stripos($query, 'account_balances') !== false) {
 return [(object) ['account_id' => 1, 'balance' => 50.00]];
 }
 return [];
 };
 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'MAX(snapshot_date)') !== false) {
 return '2026-01-31';
 }
 return null;
 };

 $result = OraBooks_Posting::validate_ledger_integrity(4);
 $types = array_column($result['issues'], 'type');
 $this->assertContains('snapshot_replay_mismatch', $types);
 }

 #[Test]
 public function test_create_journal_returns_existing_for_duplicate_idempotency_key
 {
 global $wpdb;

 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'idempotency_key') !== false) {
 return 42;
 }
 return null;
 };

 $result = OraBooks_Posting::create_journal([
 'org_id' => 2,
 'idempotency_key' => 'client-key-001',
 ], 1);

 $this->assertSame(42, $result);
 }

 #[Test]
 public function test_post_journal_atomic_is_idempotent_when_already_locked
 {
 global $wpdb;

 $wpdb->test_get_row_callback = function {
 return (object) [
 'id' => 10,
 'org_id' => 2,
 'status' => 'locked',
 'journal_number' => 'JE-2026-000001',
 'journal_hash' => str_repeat('a', 64),
 'approval_stale' => 0,
 ];
 };

 $method = new ReflectionMethod(OraBooks_Posting::class, 'post_journal_atomic');
 $method->setAccessible(true);
 $result = $method->invoke(null, 10, 1);

 $this->assertTrue($result['already_posted']);
 $this->assertSame('JE-2026-000001', $result['journal_number']);
 }
}
