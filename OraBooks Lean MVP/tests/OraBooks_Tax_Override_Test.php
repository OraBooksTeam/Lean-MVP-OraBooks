<?php
/**
 * Unit Tests for SL-081 Manual Tax Override
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Tax_Override_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['orabooks_test_current_user_id'] = 1;
        $_POST = [];
        $_GET = [];

        global $wpdb;
        $wpdb->test_get_var_callback = null;
        $wpdb->test_get_row_callback = null;
        $wpdb->test_get_results_callback = null;
        $wpdb->test_update_callback = null;
        $wpdb->insert_id = 0;
    }

    #[Test]
    public function test_validate_override_requires_reason_code()
    {
        $result = OraBooks_Tax::validate_override(2, 'US', 5, '');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('override_reason_required', $result->get_error_code());
    }

    #[Test]
    public function test_validate_override_rejects_unknown_reason()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'tax_configs') !== false) {
                return (object) [
                    'override_reasons' => json_encode(['LOCAL_TAX_RULE']),
                ];
            }
            return null;
        };

        $result = OraBooks_Tax::validate_override(2, 'US', 5, 'NOT_A_REAL_REASON');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_override_reason', $result->get_error_code());
    }

    #[Test]
    public function test_validate_override_accepts_allowed_reason_and_rate()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'tax_configs') !== false) {
                return null;
            }
            return null;
        };

        $result = OraBooks_Tax::validate_override(2, 'US', 5, 'LOCAL_TAX_RULE');

        $this->assertTrue($result);
    }

    #[Test]
    public function test_override_invoice_tax_blocks_posted_invoice()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function () {
            return (object) [
                'id' => 10,
                'org_id' => 2,
                'workflow_status' => 'posted',
                'invoice_date' => '2026-06-01',
                'total_amount' => 115,
                'tax_amount' => 15,
                'tax_rate' => 15,
            ];
        };

        $result = OraBooks_Customers::override_invoice_tax(2, 10, 5, 'LOCAL_TAX_RULE', 1, 'US');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_status', $result->get_error_code());
    }

    #[Test]
    public function test_clear_invoice_tax_override_clears_reason_fields()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'fiscal_periods') !== false) {
                return (object) ['status' => 'open'];
            }
            if (stripos($query, 'tax_configs') !== false) {
                return (object) [
                    'id' => 1,
                    'default_tax_rate' => '8.0000',
                    'tax_type' => 'Sales Tax',
                    'override_reasons' => null,
                ];
            }
            if (stripos($query, 'payments') !== false) {
                return [];
            }
            if (stripos($query, 'customers') !== false && stripos($query, 'JOIN') !== false) {
                return (object) [
                    'id' => 10,
                    'org_id' => 2,
                    'workflow_status' => 'draft',
                    'invoice_date' => '2026-06-01',
                    'invoice_number' => 'INV-202606-0001',
                    'total_amount' => 108,
                    'tax_amount' => 8,
                    'tax_rate' => 8,
                    'tax_override_reason' => null,
                    'tax_jurisdiction' => 'US',
                    'tax_type' => 'Sales Tax',
                    'currency' => 'USD',
                    'customer_id' => 1,
                    'due_date' => '2026-07-01',
                    'payment_status' => 'unpaid',
                    'paid_amount' => 0,
                ];
            }
            return (object) [
                'id' => 10,
                'org_id' => 2,
                'workflow_status' => 'draft',
                'invoice_date' => '2026-06-01',
                'invoice_number' => 'INV-202606-0001',
                'total_amount' => 105,
                'tax_amount' => 5,
                'tax_rate' => 5,
                'tax_override_reason' => 'LOCAL_TAX_RULE',
                'tax_jurisdiction' => 'US',
                'tax_type' => 'Sales Tax',
                'currency' => 'USD',
                'customer_id' => 1,
                'due_date' => '2026-07-01',
                'payment_status' => 'unpaid',
            ];
        };
        $wpdb->test_get_results_callback = function () {
            return [];
        };
        $wpdb->test_update_callback = function () {
            return 1;
        };

        $result = OraBooks_Customers::clear_invoice_tax_override(2, 10, 1, 'US');

        $this->assertIsArray($result);
        $this->assertNull($result['tax_override_reason']);
        $this->assertArrayHasKey('tax_rate', $result);
    }

    #[Test]
    public function test_format_invoice_includes_override_metadata()
    {
        $invoice = (object) [
            'id' => 3,
            'org_id' => 2,
            'customer_id' => 1,
            'invoice_number' => 'INV-1',
            'invoice_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'description' => '',
            'total_amount' => 105,
            'tax_amount' => 5,
            'tax_rate' => 5,
            'tax_jurisdiction' => 'US',
            'tax_type' => 'VAT',
            'tax_override_reason' => 'WRONG_AI_CLASSIFICATION',
            'tax_override_by' => 9,
            'tax_override_at' => '2026-06-01 12:00:00',
            'currency' => 'USD',
            'payment_status' => 'unpaid',
            'workflow_status' => 'draft',
            'paid_amount' => 0,
        ];

        $formatted = OraBooks_Customers::format_invoice($invoice);

        $this->assertSame('WRONG_AI_CLASSIFICATION', $formatted['tax_override_reason']);
        $this->assertSame(9, $formatted['tax_override_by']);
        $this->assertSame('US', $formatted['tax_jurisdiction']);
        $this->assertSame('VAT', $formatted['tax_type']);
    }
}
