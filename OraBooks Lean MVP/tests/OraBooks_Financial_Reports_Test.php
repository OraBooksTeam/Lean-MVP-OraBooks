<?php
/**
 * Unit Tests for OraBooks_Financial_Reports (SL-074)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Financial_Reports_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $wpdb->test_get_var_callback = null;
        $wpdb->test_get_row_callback = null;
        $wpdb->test_get_results_callback = null;
        $wpdb->test_query_callback = null;
        $wpdb->test_insert_callback = null;
        $wpdb->insert_id = 0;
        $wpdb->last_query = '';

        $GLOBALS['orabooks_test_current_user_id'] = 1;
        $GLOBALS['orabooks_test_current_user_can'] = true;
        $GLOBALS['orabooks_test_use_insert_id'] = null;

        $_GET = [];
        $_POST = [];
    }

    private function ledgerRows(): array
    {
        return [
            (object) [
                'account_id' => 1,
                'code' => '4000',
                'name' => 'Sales Revenue',
                'type' => 'revenue',
                'normal_balance' => 'credit',
                'debit_sum' => 0,
                'credit_sum' => 1000,
                'balance' => -1000,
            ],
            (object) [
                'account_id' => 2,
                'code' => '5000',
                'name' => 'Operating Expenses',
                'type' => 'expense',
                'normal_balance' => 'debit',
                'debit_sum' => 350,
                'credit_sum' => 0,
                'balance' => 350,
            ],
        ];
    }

    #[Test]
    public function test_schema_defines_sl074_reporting_tables()
    {
        $sql = implode("\n", OraBooks_Financial_Reports::get_create_table_sql());

        $this->assertStringContainsString('orabooks_report_ledger_summary', $sql);
        $this->assertStringContainsString('orabooks_report_ar_aging', $sql);
        $this->assertStringContainsString('orabooks_report_ap_aging', $sql);
        $this->assertStringContainsString('orabooks_report_inventory_valuation', $sql);
        $this->assertStringContainsString('orabooks_cash_flow_mappings', $sql);
        $this->assertStringContainsString('orabooks_report_snapshots', $sql);
        $this->assertStringContainsString('orabooks_report_signatures', $sql);
        $this->assertStringContainsString('orabooks_projection_dependencies', $sql);
        $this->assertStringContainsString('orabooks_projector_checkpoints', $sql);
        $this->assertStringContainsString('orabooks_projection_integrity_checks', $sql);
    }

    #[Test]
    public function test_generate_profit_loss_creates_snapshot_from_read_model()
    {
        global $wpdb;
        $inserted = [];

        $wpdb->test_get_var_callback = fn($query) => null;
        $wpdb->test_get_row_callback = fn($query) => null;
        $wpdb->test_get_results_callback = fn($query) => $this->ledgerRows();
        $wpdb->test_insert_callback = function ($table, $data) use (&$inserted) {
            $inserted[] = [$table, $data];
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 101;

        $result = OraBooks_Financial_Reports::generate_report(10, 'profit_loss', '2026-01-01', '2026-01-31', [
            'generated_by' => 5,
            'correlation_id' => 'corr-pl',
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(101, $result['snapshot_id']);
        $this->assertEquals('profit_loss', $result['report_type']);
        $this->assertEquals(1000.0, $result['report']['total_revenue']);
        $this->assertEquals(350.0, $result['report']['total_expenses']);
        $this->assertEquals(650.0, $result['report']['net_income']);
        $this->assertFalse($result['from_cache']);
        $this->assertEquals('wp_test_orabooks_report_snapshots', $inserted[0][0]);
        $this->assertEquals('corr-pl', $inserted[0][1]['correlation_id']);
    }

    #[Test]
    public function test_generate_balance_sheet_uses_normal_balances()
    {
        global $wpdb;

        $wpdb->test_get_var_callback = fn($query) => null;
        $wpdb->test_get_row_callback = fn($query) => null;
        $wpdb->test_get_results_callback = function ($query) {
            return [
                (object) ['account_id' => 1, 'code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'normal_balance' => 'debit', 'debit_sum' => 1000, 'credit_sum' => 0, 'balance' => 1000],
                (object) ['account_id' => 2, 'code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'normal_balance' => 'credit', 'debit_sum' => 0, 'credit_sum' => 300, 'balance' => -300],
                (object) ['account_id' => 3, 'code' => '3000', 'name' => 'Equity', 'type' => 'equity', 'normal_balance' => 'credit', 'debit_sum' => 0, 'credit_sum' => 700, 'balance' => -700],
            ];
        };

        $result = OraBooks_Financial_Reports::generate_report(10, 'balance_sheet', '2026-01-01', '2026-01-31');

        $this->assertEquals(1000.0, $result['report']['total_assets']);
        $this->assertEquals(300.0, $result['report']['total_liabilities']);
        $this->assertEquals(700.0, $result['report']['total_equity']);
        $this->assertTrue($result['report']['balanced']);
    }

    #[Test]
    public function test_hard_closed_period_returns_frozen_snapshot()
    {
        global $wpdb;

        $payload = wp_json_encode([
            'report' => ['report_type' => 'profit_loss', 'net_income' => 123.45],
            'correlation_id' => 'frozen-corr',
        ]);

        $wpdb->test_get_var_callback = function ($query) {
            return stripos($query, 'fiscal_periods') !== false ? 'hard_closed' : null;
        };
        $wpdb->test_get_row_callback = function ($query) use ($payload) {
            if (stripos($query, 'report_snapshots') !== false) {
                return (object) [
                    'id' => 55,
                    'report_type' => 'profit_loss',
                    'period_start' => '2026-01-01',
                    'period_end' => '2026-01-31',
                    'snapshot_data' => $payload,
                    'snapshot_hash' => 'abc',
                    'correlation_id' => 'frozen-corr',
                    'frozen' => 1,
                ];
            }
            return null;
        };

        $result = OraBooks_Financial_Reports::generate_report(10, 'profit_loss', '2026-01-01', '2026-01-31');

        $this->assertEquals(55, $result['snapshot_id']);
        $this->assertTrue($result['from_cache']);
        $this->assertTrue($result['frozen']);
        $this->assertEquals(123.45, $result['report']['net_income']);
    }

    #[Test]
    public function test_project_journal_posted_updates_ledger_summary_and_checkpoint()
    {
        global $wpdb;
        $queries = [];

        $wpdb->test_get_row_callback = function ($query) {
            return (object) [
                'id' => 20,
                'org_id' => 10,
                'transaction_date' => '2026-01-15',
                'status' => 'posted',
            ];
        };
        $wpdb->test_get_results_callback = function ($query) {
            return [
                (object) ['account_id' => 1, 'debit_sum' => 100, 'credit_sum' => 0],
                (object) ['account_id' => 2, 'debit_sum' => 0, 'credit_sum' => 100],
            ];
        };
        $wpdb->test_query_callback = function ($query) use (&$queries) {
            $queries[] = $query;
            return 1;
        };

        $result = OraBooks_Financial_Reports::project_journal_posted(20, ['org_id' => 10, 'event_id' => 99]);

        $this->assertEquals(2, $result['projected_lines']);
        $this->assertEquals(99, $result['last_event_id']);
        $this->assertGreaterThanOrEqual(3, count($queries));
        $this->assertStringContainsString('report_ledger_summary', $queries[0]);
        $this->assertStringContainsString('projector_checkpoints', $queries[2]);
    }

    #[Test]
    public function test_sign_report_creates_signature_and_approved_watermark()
    {
        global $wpdb;
        $inserted = [];

        $wpdb->test_get_row_callback = function ($query) {
            return (object) [
                'id' => 44,
                'org_id' => 10,
                'snapshot_data' => '{"report":{"net_income":1}}',
                'snapshot_hash' => 'snap-hash',
            ];
        };
        $wpdb->test_insert_callback = function ($table, $data) use (&$inserted) {
            $inserted = [$table, $data];
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 77;

        $result = OraBooks_Financial_Reports::sign_report(44, 5, 'BOARD-2026-01');

        $this->assertEquals(77, $result['signature_id']);
        $this->assertEquals('APPROVED', $result['watermark']);
        $this->assertEquals('wp_test_orabooks_report_signatures', $inserted[0]);
        $this->assertEquals('BOARD-2026-01', $inserted[1]['board_approval_reference']);
        $this->assertNotEmpty($result['signature_hash']);
    }

    #[Test]
    public function test_archive_and_integrity_governance()
    {
        global $wpdb;
        $updates = [];
        $inserts = [];
        $getVarCalls = 0;

        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'report_snapshots') !== false) {
                return [(object) ['id' => 1, 'org_id' => 10, 'snapshot_hash' => 'abc']];
            }
            return [(object) ['org_id' => 10]];
        };
        $wpdb->test_get_var_callback = function ($query) use (&$getVarCalls) {
            $getVarCalls++;
            return $getVarCalls === 1 ? 100.00 : 99.50;
        };
        $wpdb->test_insert_callback = function ($table, $data) use (&$inserts) {
            $inserts[] = [$table, $data];
        };
        $originalUpdate = [$wpdb, 'update'];

        $archive = OraBooks_Financial_Reports::archive_old_snapshots(365);
        $integrity = OraBooks_Financial_Reports::run_integrity_checks(10, '2026-01-31');

        $this->assertEquals(1, $archive['archived']);
        $this->assertEquals('fail', $integrity['checks'][0]['status']);
        $this->assertEquals(0.5, $integrity['checks'][0]['difference']);
        $this->assertEquals('wp_test_orabooks_projection_integrity_checks', $inserts[0][0]);
        $this->assertTrue(is_callable($originalUpdate));
    }

    #[Test]
    public function test_flatten_profit_loss_for_export()
    {
        $flat = OraBooks_Financial_Reports::flatten_for_export([
            'report' => [
                'report_type' => 'profit_loss',
                'revenue' => [
                    ['account_id' => 1, 'code' => '4000', 'name' => 'Sales', 'type' => 'revenue', 'amount' => 1000],
                ],
                'expenses' => [
                    ['account_id' => 2, 'code' => '5000', 'name' => 'Rent', 'type' => 'expense', 'amount' => 350],
                ],
                'net_income' => 650,
            ],
        ]);

        $this->assertSame(['section', 'code', 'name', 'type', 'amount'], $flat['columns']);
        $this->assertCount(3, $flat['rows']);
        $this->assertEquals('Summary', $flat['rows'][2]['section']);
        $this->assertEquals(650, $flat['rows'][2]['amount']);
    }

    #[Test]
    public function test_export_report_data_resolves_financial_export_type()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'fiscal_periods') !== false) {
                return null;
            }
            if (stripos($query, 'report_snapshots') !== false) {
                return null;
            }
            return null;
        };
        $wpdb->test_get_results_callback = function ($query) {
            return [
                (object) [
                    'account_id' => 1,
                    'code' => '4000',
                    'name' => 'Sales Revenue',
                    'type' => 'revenue',
                    'normal_balance' => 'credit',
                    'debit_sum' => 0,
                    'credit_sum' => 500,
                    'balance' => -500,
                ],
            ];
        };
        $wpdb->test_insert_callback = function ($table, $data) {
            return true;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 9001;

        $data = OraBooks_Financial_Reports::export_report_data([
            'org_id' => 10,
            'export_type' => 'financial_profit_loss',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
        ]);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('columns', $data);
        $this->assertArrayHasKey('rows', $data);
        $this->assertNotEmpty($data['rows']);
    }
}
