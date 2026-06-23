<?php
/**
 * Unit Tests for OraBooks_Expenses (SL-028)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Expenses_Test extends TestCase
{
    #[Test]
    public function test_schema_defines_sl028_tables()
    {
        $sql = implode("\n", OraBooks_Expenses::get_create_table_sql());

        $this->assertStringContainsString('orabooks_expenses', $sql);
        $this->assertStringContainsString('orabooks_ocr_processing_queue', $sql);
        $this->assertStringContainsString('orabooks_expense_line_items', $sql);
        $this->assertStringContainsString("ENUM('draft','submitted','ai_review','approved','posted','locked')", $sql);
    }

    #[Test]
    public function test_ocr_stub_returns_structured_fields()
    {
        $ocr = OraBooks_Expenses::run_ocr_stub('office-supplies-receipt.pdf', 42);

        $this->assertNotEmpty($ocr['vendor']);
        $this->assertGreaterThan(0, $ocr['total_amount']);
        $this->assertArrayHasKey('ocr_confidence', $ocr);
        $this->assertArrayHasKey('ocr_risk_level', $ocr);
        $this->assertContains($ocr['ocr_risk_level'], ['low', 'medium', 'high']);
        $this->assertNotEmpty($ocr['line_items']);
    }

    #[Test]
    public function test_ocr_stub_elevates_risk_for_high_value_expense()
    {
        $ocr = OraBooks_Expenses::run_ocr_stub('enterprise-vendor-invoice.pdf', 95000);

        $this->assertGreaterThanOrEqual(5000, $ocr['total_amount']);
        $this->assertSame('high', $ocr['ocr_risk_level']);
    }

    #[Test]
    public function test_format_expense_maps_core_fields()
    {
        $row = (object) [
            'id' => 7,
            'org_id' => 1,
            'vendor' => 'Acme Supplies',
            'vendor_tax_id' => null,
            'invoice_number' => 'RCP-000123',
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
            'description' => 'Test expense',
            'ocr_confidence' => 88.5,
            'ocr_risk_level' => 'low',
            'ocr_data' => wp_json_encode(['fields' => []]),
            'ocr_provider' => 'mvp-stub',
            'ocr_model_version' => 'mvp-stub-1.0',
            'ocr_snapshot_hash' => 'abc123',
            'workflow_status' => 'draft',
            'payment_status' => 'unpaid',
            'lock_status' => 'unlocked',
            'attachment_id' => 3,
            'journal_id' => null,
            'created_by' => 1,
            'approved_by' => null,
            'posted_by' => null,
            'approved_at' => null,
            'posted_at' => null,
            'created_at' => '2026-06-18 09:00:00',
            'updated_at' => '2026-06-18 09:00:00',
        ];

        $formatted = OraBooks_Expenses::format_expense($row);

        $this->assertSame(7, $formatted['id']);
        $this->assertSame('Acme Supplies', $formatted['vendor']);
        $this->assertSame(100.0, $formatted['total_amount']);
        $this->assertSame('low', $formatted['ocr_risk_level']);
        $this->assertSame(88.5, $formatted['ocr_confidence']);
    }

    #[Test]
    public function test_ocr_stub_includes_extended_sl028_fields()
    {
        $ocr = OraBooks_Expenses::run_ocr_stub('vendor-receipt.pdf', 10);

        $this->assertArrayHasKey('vendor_tax_id', $ocr);
        $this->assertArrayHasKey('due_date', $ocr);
        $this->assertArrayHasKey('merchant_address', $ocr);
    }

    #[Test]
    public function test_run_live_checks_returns_expected_shape()
    {
        $result = OraBooks_Expenses::run_live_checks(0);

        $this->assertArrayHasKey('ok', $result);
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('environment', $result);
        $this->assertArrayHasKey('manual_steps', $result);
        $this->assertIsArray($result['checks']);
        $this->assertNotEmpty($result['checks']);
        $this->assertSame(70, $result['environment']['confidence_threshold']);
        $this->assertSame(10, $result['environment']['rate_limit_per_min']);
    }

    #[Test]
    public function test_async_ocr_handler_is_registered()
    {
        if (!class_exists('OraBooks_AsyncQueue')) {
            $this->markTestSkipped('OraBooks_AsyncQueue not loaded');
        }

        OraBooks_Expenses::init();
        $handler = OraBooks_AsyncQueue::get_handler('process_expense_ocr');
        $this->assertTrue(is_callable($handler));
    }
}
