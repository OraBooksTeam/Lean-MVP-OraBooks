<?php
/**
 * Unit Tests for OraBooks_Bank_Reconciliation
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Bank_Reconciliation_Test extends TestCase
{
 protected function setUp: void
 {
 parent::setUp;

 $GLOBALS['orabooks_test_current_user_id'] = 1;
 $GLOBALS['orabooks_test_use_insert_id'] = null;

 $_POST = [];
 $_GET = [];

 global $wpdb;
 $wpdb->test_get_var_callback = null;
 $wpdb->test_get_row_callback = null;
 $wpdb->test_get_results_callback = null;
 $wpdb->test_insert_callback = null;
 $wpdb->test_query_callback = null;
 $wpdb->insert_id = 0;
 $wpdb->last_query = '';
 $wpdb->last_result = [];
 }

 private function mockBankTransaction(array $overrides = []): object
 {
 return (object) array_merge([
 'id' => 100,
 'org_id' => 5,
 'bank_account_id' => 10,
 'transaction_date' => '2026-06-15',
 'amount' => '250.00',
 'description' => 'Customer payment',
 'reference' => 'PAY-001',
 'status' => 'unmatched',
 ], $overrides);
 }

 #[Test]
 public function test_get_create_table_sql_contains_reconciliation_tables() {
 $sql = implode("\n", OraBooks_Bank_Reconciliation::get_create_table_sql);

 $this->assertStringContainsString('orabooks_bank_accounts', $sql);
 $this->assertStringContainsString('orabooks_bank_transactions', $sql);
 $this->assertStringContainsString('orabooks_bank_feeds', $sql);
 $this->assertStringContainsString('orabooks_reconciliation_matches', $sql);
 $this->assertStringContainsString('orabooks_reconciliation_log', $sql);
 $this->assertStringContainsString('liquidity_pool_id', $sql);
 }

 #[Test]
 public function test_create_bank_account_inserts_and_returns_account() {
 global $wpdb;

 $wpdb->test_get_row_callback = function ($query) {
 if (stripos($query, 'bank_accounts') !== false) {
 return (object) [
 'id' => 44,
 'org_id' => 5,
 'account_name' => 'Operating Bank',
 'currency' => 'USD',
 'current_balance' => '100.00',
 ];
 }
 return null;
 };
 $GLOBALS['orabooks_test_use_insert_id'] = 44;

 $account = OraBooks_Bank_Reconciliation::create_bank_account(5, [
 'account_name' => 'Operating Bank',
 'current_balance' => 100,
 ]);

 $this->assertIsObject($account);
 $this->assertEquals(44, $account->id);
 $this->assertEquals('Operating Bank', $account->account_name);
 }

 #[Test]
 public function test_import_rows_inserts_new_rows_and_skips_duplicates() {
 global $wpdb;

 $getVarCalls = 0;
 $wpdb->test_get_var_callback = function ($query) use (&$getVarCalls) {
 $getVarCalls++;
 if (stripos($query, 'bank_transactions') !== false && $getVarCalls === 2) {
 return 123;
 }
 return 0;
 };
 $wpdb->test_get_row_callback = function ($query) {
 return null;
 };
 $GLOBALS['orabooks_test_use_insert_id'] = 55;

 $summary = OraBooks_Bank_Reconciliation::import_rows(5, 10, [
 ['date' => '2026-06-01', 'amount' => 100, 'description' => 'Deposit', 'reference' => 'A'],
 ['date' => '2026-06-02', 'amount' => 200, 'description' => 'Duplicate', 'reference' => 'B'],
 ], 1);

 $this->assertEquals(2, $summary['total_rows']);
 $this->assertEquals(1, $summary['inserted']);
 $this->assertEquals(1, $summary['duplicates']);
 }

 #[Test]
 public function test_suggest_match_creates_rule_based_payment_suggestion() {
 global $wpdb;

 $wpdb->test_get_row_callback = function ($query) {
 if (stripos($query, 'orabooks_payments') !== false) {
 return (object) ['id' => 501, 'reference' => 'PAY-001'];
 }
 return null;
 };
 $GLOBALS['orabooks_test_use_insert_id'] = 77;

 $result = OraBooks_Bank_Reconciliation::suggest_match(5, 100, [
 'transaction_date' => '2026-06-15',
 'amount' => 250,
 'description' => 'Customer payment',
 'reference' => 'PAY-001',
 ]);

 $this->assertTrue($result['suggested']);
 $this->assertEquals(77, $result['match_id']);
 $this->assertEquals('payment', $result['candidate']['transaction_type']);
 $this->assertEquals(501, $result['candidate']['transaction_id']);
 }

 #[Test]
 public function test_manual_match_blocks_already_matched_transaction() {
 global $wpdb;

 $wpdb->test_get_row_callback = function ($query) {
 if (stripos($query, 'bank_transactions') !== false) {
 return $this->mockBankTransaction(['status' => 'matched']);
 }
 return null;
 };

 $result = OraBooks_Bank_Reconciliation::manual_match(5, 100, 'payment', 501, 9);

 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertEquals('invalid_status', $result->get_error_code());
 }

 #[Test]
 public function test_manual_match_sets_transaction_matched() {
 global $wpdb;

 $wpdb->test_get_row_callback = function ($query) {
 if (stripos($query, 'bank_transactions') !== false) {
 return $this->mockBankTransaction(['status' => 'unmatched']);
 }
 return null;
 };
 $GLOBALS['orabooks_test_use_insert_id'] = 88;

 $result = OraBooks_Bank_Reconciliation::manual_match(5, 100, 'payment', 501, 9);

 $this->assertEquals(88, $result['match_id']);
 $this->assertEquals('matched', $result['status']);
 }

 #[Test]
 public function test_skip_transaction_blocks_reconciled_transaction() {
 global $wpdb;

 $wpdb->test_get_row_callback = function ($query) {
 return $this->mockBankTransaction(['status' => 'reconciled']);
 };

 $result = OraBooks_Bank_Reconciliation::skip_transaction(5, 100, 'Ignore', 9);

 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertEquals('invalid_status', $result->get_error_code());
 }

 #[Test]
 public function test_finalize_reconciliation_rejects_unmatched_transactions() {
 global $wpdb;

 $wpdb->test_get_results_callback = function ($query) {
 if (stripos($query, "status = 'unmatched'") !== false) {
 return [(object) ['id' => 100]];
 }
 return [];
 };

 $result = OraBooks_Bank_Reconciliation::finalize_reconciliation(5, 10, '2026-06-30', 1000, 9);

 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertEquals('unmatched_transactions', $result->get_error_code());
 }

 #[Test]
 public function test_finalize_reconciliation_allows_force_balance_difference() {
 global $wpdb;

 $wpdb->test_get_results_callback = function ($query) {
 return [];
 };
 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'SUM(amount)') !== false) {
 return 900;
 }
 return 0;
 };
 $GLOBALS['orabooks_test_use_insert_id'] = 66;

 $result = OraBooks_Bank_Reconciliation::finalize_reconciliation(5, 10, '2026-06-30', 1000, 9, true, 'Bank fee timing');

 $this->assertEquals(66, $result['reconciliation_log_id']);
 $this->assertEquals(1000.0, $result['ending_balance']);
 $this->assertEquals(900.0, $result['system_balance']);
 $this->assertEquals(100.0, $result['difference']);
 }

 #[Test]
 public function test_get_transactions_list_without_account_filter_returns_org_transactions() {
 global $wpdb;

 $seen_query = '';
 $wpdb->test_get_results_callback = function ($query) use (&$seen_query) {
 $seen_query = $query;
 return [
 $this->mockBankTransaction(['bank_account_id' => 10]),
 $this->mockBankTransaction(['id' => 101, 'bank_account_id' => 11]),
 ];
 };

 $rows = OraBooks_Bank_Reconciliation::get_transactions_list(5, 0, ['limit' => 50, 'offset' => 0]);

 $this->assertCount(2, $rows);
 $this->assertStringContainsString('WHERE org_id =', $seen_query);
 }

 #[Test]
 public function test_parse_csv_content_parses_header_and_rows() {
 $csv = "date,amount,description,reference\n2026-06-01,100.50,Deposit,REF-1\n2026-06-02,-25.00,Fee,";
 $rows = OraBooks_Bank_Reconciliation::parse_csv_content($csv);

 $this->assertIsArray($rows);
 $this->assertCount(2, $rows);
 $this->assertEquals('2026-06-01', $rows[0]['date']);
 $this->assertEquals('100.50', $rows[0]['amount']);
 $this->assertEquals('Deposit', $rows[0]['description']);
 }

 #[Test]
 public function test_parse_csv_content_rejects_empty_file() {
 $result = OraBooks_Bank_Reconciliation::parse_csv_content('');
 $this->assertInstanceOf(WP_Error::class, $result);
 }

 #[Test]
 public function test_confirm_suggested_match_updates_status() {
 global $wpdb;

 $wpdb->test_get_row_callback = function ($query) {
 if (stripos($query, 'bank_transactions') !== false && stripos($query, 'reconciliation_matches') === false) {
 return $this->mockBankTransaction;
 }
 if (stripos($query, 'reconciliation_matches') !== false) {
 return (object) [
 'id' => 7,
 'bank_transaction_id' => 100,
 'transaction_type' => 'payment',
 'transaction_id' => 55,
 'match_status' => 'suggested',
 ];
 }
 return null;
 };

 $result = OraBooks_Bank_Reconciliation::confirm_suggested_match(5, 100, 7, 1);

 $this->assertEquals('matched', $result['status']);
 $this->assertEquals(7, $result['match_id']);
 }

 #[Test]
 public function test_suggest_match_uses_expense_candidate() {
 global $wpdb;

 $wpdb->test_get_row_callback = function ($query) {
 if (stripos($query, 'bank_transactions') !== false && stripos($query, 'FROM') !== false) {
 return $this->mockBankTransaction(['amount' => '-45.00', 'description' => 'Office supplies']);
 }
 if (stripos($query, 'orabooks_payments') !== false) {
 return null;
 }
 if (stripos($query, 'vendor_payments') !== false) {
 return null;
 }
 if (stripos($query, 'expenses') !== false) {
 return (object) [
 'id' => 33,
 'vendor' => 'Office Depot',
 'description' => 'Office supplies',
 ];
 }
 return null;
 };
 $wpdb->test_get_var_callback = function {
 return 0;
 };
 $GLOBALS['orabooks_test_use_insert_id'] = 88;

 $result = OraBooks_Bank_Reconciliation::suggest_match(5, 100, [
 'transaction_date' => '2026-06-15',
 'amount' => -45,
 'description' => 'Office supplies purchase',
 'reference' => '',
 ]);

 $this->assertTrue($result['suggested']);
 $this->assertEquals('expense', $result['candidate']['transaction_type']);
 }
}
