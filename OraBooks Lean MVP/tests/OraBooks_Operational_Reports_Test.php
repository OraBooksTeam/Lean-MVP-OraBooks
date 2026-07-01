<?php
/**
 * Unit Tests for OraBooks_Operational_Reports (SL-075)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Operational_Reports_Test extends TestCase
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
        $GLOBALS['orabooks_test_cache'] = [];

        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
    }

    #[Test]
    public function test_schema_defines_sl075_read_models()
    {
        $sql = implode("\n", OraBooks_Operational_Reports::get_create_table_sql());

        $this->assertStringContainsString('orabooks_report_inventory_status', $sql);
        $this->assertStringContainsString('reorder_level', $sql);
        $this->assertStringContainsString('orabooks_report_bank_reconciliation_summary', $sql);
        $this->assertStringContainsString('total_unmatched_count', $sql);
        $this->assertStringContainsString('orabooks_report_sales_summary', $sql);
        $this->assertStringContainsString('net_sales', $sql);
        $this->assertStringContainsString('orabooks_report_purchase_summary', $sql);
    }

    #[Test]
    public function test_ar_aging_groups_bucket_rows_per_customer()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            return [
                (object) ['customer_id' => 10, 'bucket' => '30', 'amount' => 500.00],
                (object) ['customer_id' => 10, 'bucket' => '90_plus', 'amount' => 125.00],
                (object) ['customer_id' => 11, 'bucket' => 'current', 'amount' => 75.00],
            ];
        };

        $result = OraBooks_Operational_Reports::generate_report(5, 'ar_aging', [
            'as_of_date' => '2026-06-30',
            'correlation_id' => 'corr-ar',
        ]);

        $this->assertEquals('ar_aging', $result['report_type']);
        $this->assertFalse($result['from_cache']);
        $this->assertCount(2, $result['data']);
        $this->assertEquals(625.00, $result['data'][0]['total_due']);
        $this->assertEquals(75.00, $result['data'][1]['current']);
    }

    #[Test]
    public function test_inventory_status_marks_low_stock_and_filters()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            $this->assertStringContainsString("status = 'low'", str_replace('"', "'", $query));
            return [
                (object) [
                    'org_id' => 5,
                    'product_id' => 20,
                    'sku' => 'LP-01',
                    'product_name' => 'Laptop',
                    'current_stock' => 3,
                    'reorder_level' => 10,
                    'status' => 'low',
                ],
            ];
        };

        $result = OraBooks_Operational_Reports::generate_report(5, 'inventory_status', [
            'status' => 'low',
        ]);

        $this->assertEquals(1, $result['data']['low_stock_count']);
        $this->assertEquals('low', $result['data']['products'][0]->status);
    }

    #[Test]
    public function test_bank_reconciliation_summary_is_org_scoped()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            $this->assertStringContainsString('org_id = 5', $query);
            $this->assertStringContainsString('bank_account_id = 9', $query);
            return [
                (object) [
                    'org_id' => 5,
                    'bank_account_id' => 9,
                    'as_of_date' => '2026-06-30',
                    'total_unmatched_count' => 3,
                    'total_unmatched_amount' => 245.50,
                    'last_reconciled_at' => '2026-06-15 09:00:00',
                ],
            ];
        };

        $result = OraBooks_Operational_Reports::generate_report(5, 'bank_reconciliation', [
            'bank_account_id' => 9,
            'as_of_date' => '2026-06-30',
        ]);

        $this->assertCount(1, $result['data']);
        $this->assertEquals(3, $result['data'][0]->total_unmatched_count);
    }

    #[Test]
    public function test_sales_and_purchase_summaries_return_period_rows()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'report_sales_summary') !== false) {
                return [(object) ['period_date' => '2026-06-01', 'customer_id' => 10, 'total_sales' => 1000, 'total_returns' => 100, 'net_sales' => 900]];
            }
            return [(object) ['period_date' => '2026-06-01', 'vendor_id' => 7, 'total_purchases' => 450]];
        };

        $sales = OraBooks_Operational_Reports::generate_report(5, 'sales_summary', [
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ]);
        $purchases = OraBooks_Operational_Reports::generate_report(5, 'purchase_summary', [
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ]);

        $this->assertEquals(900, $sales['data'][0]->net_sales);
        $this->assertEquals(450, $purchases['data'][0]->total_purchases);
    }

    #[Test]
    public function test_invoice_and_bill_projectors_update_operational_read_models()
    {
        global $wpdb;
        $queries = [];

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'orabooks_invoices') !== false) {
                return (object) [
                    'id' => 100,
                    'org_id' => 5,
                    'customer_id' => 10,
                    'transaction_date' => '2026-06-10',
                    'due_date' => '2026-06-01',
                    'total_amount' => 1000,
                    'paid_amount' => 250,
                    'payment_status' => 'partial',
                    'workflow_status' => 'posted',
                ];
            }
            return (object) [
                'id' => 200,
                'org_id' => 5,
                'vendor_id' => 7,
                'bill_date' => '2026-06-11',
                'due_date' => '2026-05-01',
                'total_amount' => 600,
                'paid_amount' => 100,
                'payment_status' => 'partial',
                'workflow_status' => 'posted',
            ];
        };
        $wpdb->test_query_callback = function ($query) use (&$queries) {
            $queries[] = $query;
            return 1;
        };

        $invoiceResult = OraBooks_Operational_Reports::project_invoice_posted(100, ['org_id' => 5, 'event_id' => 501]);
        $billResult = OraBooks_Operational_Reports::project_bill_posted(200, ['org_id' => 5, 'event_id' => 502]);

        $this->assertTrue($invoiceResult);
        $this->assertTrue($billResult);
        $this->assertCount(4, $queries);
        $this->assertStringContainsString('report_ar_aging', $queries[0]);
        $this->assertStringContainsString('report_sales_summary', $queries[1]);
        $this->assertStringContainsString('report_ap_aging', $queries[2]);
        $this->assertStringContainsString('report_purchase_summary', $queries[3]);
    }

    #[Test]
    public function test_inventory_and_bank_projectors_update_read_models()
    {
        global $wpdb;
        $queries = [];
        $getVarCalls = 0;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'orabooks_products') !== false) {
                return (object) [
                    'id' => 20,
                    'org_id' => 5,
                    'sku' => 'LP-01',
                    'name' => 'Laptop',
                    'current_stock' => 3,
                    'low_stock_threshold' => 10,
                ];
            }
            return (object) ['unmatched_count' => 2, 'unmatched_amount' => 125.75];
        };
        $wpdb->test_get_var_callback = function ($query) use (&$getVarCalls) {
            $getVarCalls++;
            return '2026-06-20 12:00:00';
        };
        $wpdb->test_query_callback = function ($query) use (&$queries) {
            $queries[] = $query;
            return 1;
        };

        $inventoryResult = OraBooks_Operational_Reports::project_inventory_status(5, ['product_id' => 20, 'event_id' => 601]);
        $bankResult = OraBooks_Operational_Reports::project_bank_reconciliation_summary(5, ['bank_account_id' => 9, 'as_of_date' => '2026-06-30', 'event_id' => 602]);

        $this->assertTrue($inventoryResult);
        $this->assertTrue($bankResult);
        $this->assertStringContainsString('report_inventory_status', $queries[0]);
        $this->assertStringContainsString("'low'", $queries[0]);
        $this->assertStringContainsString('report_bank_reconciliation_summary', $queries[1]);
    }

    #[Test]
    public function test_low_stock_alert_job_counts_rows()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            return [
                (object) ['org_id' => 5, 'product_id' => 20, 'sku' => 'LP-01', 'product_name' => 'Laptop', 'current_stock' => 3, 'reorder_level' => 10],
                (object) ['org_id' => 5, 'product_id' => 21, 'sku' => 'KB-01', 'product_name' => 'Keyboard', 'current_stock' => 1, 'reorder_level' => 5],
            ];
        };

        $result = OraBooks_Operational_Reports::check_low_stock_alerts(5);

        $this->assertEquals(2, $result['alerts']);
    }

    #[Test]
    public function test_operational_report_uses_short_cache()
    {
        global $wpdb;
        $calls = 0;

        $wpdb->test_get_results_callback = function ($query) use (&$calls) {
            $calls++;
            return [(object) ['customer_id' => 10, 'bucket' => 'current', 'amount' => 100]];
        };

        $first = OraBooks_Operational_Reports::generate_report(5, 'ar_aging', ['as_of_date' => '2026-06-30']);
        $second = OraBooks_Operational_Reports::generate_report(5, 'ar_aging', ['as_of_date' => '2026-06-30']);

        $this->assertFalse($first['from_cache']);
        $this->assertTrue($second['from_cache']);
        $this->assertEquals(1, $calls);
    }

    #[Test]
    public function test_flatten_ar_aging_for_export()
    {
        $flat = OraBooks_Operational_Reports::flatten_for_export([
            'report_type' => 'ar_aging',
            'data' => [
                ['customer_id' => 10, 'current' => 100, '30' => 50, '60' => 0, '90' => 0, '90_plus' => 0, 'total_due' => 150],
            ],
        ]);

        $this->assertSame(['customer_id', 'current', '30', '60', '90', '90_plus', 'total_due'], $flat['columns']);
        $this->assertCount(1, $flat['rows']);
        $this->assertEquals(150, $flat['rows'][0]['total_due']);
    }

    #[Test]
    public function test_export_report_data_resolves_operational_export_type()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            return [(object) ['customer_id' => 10, 'bucket' => 'current', 'amount' => 200]];
        };

        $data = OraBooks_Operational_Reports::export_report_data([
            'org_id' => 5,
            'export_type' => 'operational_ar_aging',
            'as_of_date' => '2026-06-30',
        ]);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('rows', $data);
        $this->assertNotEmpty($data['rows']);
    }
}
