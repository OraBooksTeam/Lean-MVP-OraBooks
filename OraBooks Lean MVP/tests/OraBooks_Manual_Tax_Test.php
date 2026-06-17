<?php
/**
 * Unit Tests for Manual Tax Override on Invoices (SL-081)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Manual_Tax_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

    private function mockInvoice(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 100,
            'org_id' => 5,
            'customer_id' => 10,
            'invoice_number' => 'INV-202606-0001',
            'invoice_date' => '2026-06-01',
            'transaction_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'total_amount' => '115.00',
            'tax_amount' => '15.00',
            'tax_rate' => '15.0000',
            'workflow_status' => 'draft',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
        ], $overrides);
    }

    #[Test]
    public function test_invoice_schema_contains_manual_tax_override_columns()
    {
        $sql = implode("\n", OraBooks_Customers::get_create_table_sql());

        $this->assertStringContainsString('tax_rate DECIMAL(8,4)', $sql);
        $this->assertStringContainsString('tax_override_reason VARCHAR(64)', $sql);
        $this->assertStringContainsString('tax_override_by BIGINT UNSIGNED', $sql);
        $this->assertStringContainsString('tax_override_at TIMESTAMP NULL', $sql);
    }

    #[Test]
    public function test_override_invoice_tax_updates_draft_invoice()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'orabooks_invoices') !== false) {
                return $this->mockInvoice();
            }
            if (stripos($query, 'orabooks_tax_configs') !== false) {
                return (object) [
                    'id' => 1,
                    'default_tax_rate' => '15.0000',
                    'tax_type' => 'VAT',
                    'override_reasons' => json_encode(['WRONG_AI_CLASSIFICATION']),
                ];
            }
            return null;
        };

        $result = OraBooks_Customers::override_invoice_tax(
            5,
            100,
            5,
            'WRONG_AI_CLASSIFICATION',
            7,
            'BD'
        );

        $this->assertIsArray($result);
        $this->assertEquals(5.0, $result['tax_rate']);
        $this->assertEquals(5.0, $result['tax_amount']);
        $this->assertEquals(105.0, $result['total_amount']);
        $this->assertEquals('WRONG_AI_CLASSIFICATION', $result['tax_override_reason']);
        $this->assertEquals(7, $result['tax_override_by']);
    }

    #[Test]
    public function test_override_invoice_tax_rejects_invalid_reason()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'orabooks_invoices') !== false) {
                return $this->mockInvoice();
            }
            if (stripos($query, 'orabooks_tax_configs') !== false) {
                return (object) [
                    'id' => 1,
                    'default_tax_rate' => '15.0000',
                    'tax_type' => 'VAT',
                    'override_reasons' => json_encode(['LOCAL_TAX_RULE']),
                ];
            }
            return null;
        };

        $result = OraBooks_Customers::override_invoice_tax(
            5,
            100,
            5,
            'NOT_ALLOWED',
            7,
            'BD'
        );

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_override_reason', $result->get_error_code());
    }

    #[Test]
    public function test_override_invoice_tax_blocks_posted_invoice()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'orabooks_invoices') !== false) {
                return $this->mockInvoice(['workflow_status' => 'posted']);
            }
            return null;
        };

        $result = OraBooks_Customers::override_invoice_tax(
            5,
            100,
            5,
            'LOCAL_TAX_RULE',
            7,
            'BD'
        );

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_status', $result->get_error_code());
    }
}
