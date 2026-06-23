<?php
/**
 * Unit Tests for OraBooks_AP (SL-027 extension)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_AP_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['orabooks_test_current_user_id'] = 1;
        $_POST = [];
        $_GET = [];
    }

    #[Test]
    public function get_ap_config_returns_defaults_when_missing_row(): void
    {
        global $wpdb;
        $wpdb->test_get_row_callback = static function ($query) {
            if (strpos($query, 'vendor_ap_configs') !== false) {
                return null;
            }
            return null;
        };

        $config = OraBooks_AP::get_ap_config(5);
        $this->assertEquals(5, (int) $config->org_id);
        $this->assertEquals(1, (int) $config->auto_post_bill_on_approve);
        $this->assertEquals('5000', $config->vendor_adjustment_account);
    }

    #[Test]
    public function submit_credit_note_moves_draft_to_submitted(): void
    {
        global $wpdb;
        $wpdb->test_get_row_callback = static function ($query) {
            if (strpos($query, 'vendor_credit_notes') !== false && strpos($query, 'WHERE id') !== false) {
                return (object) [
                    'id' => 12,
                    'org_id' => 5,
                    'vendor_id' => 10,
                    'credit_note_number' => 'VCN-2026-000001',
                    'credit_date' => '2026-06-01',
                    'amount' => '100.00',
                    'reason' => 'Return',
                    'is_adjustment' => 0,
                    'workflow_status' => 'draft',
                ];
            }
            return null;
        };

        $result = OraBooks_AP::submit_credit_note(5, 12, 1);
        $this->assertIsArray($result);
        $this->assertEquals('submitted', $result['workflow_status']);
    }

    #[Test]
    public function list_payments_marks_reversed_payments(): void
    {
        global $wpdb;
        $wpdb->test_get_results_callback = static function ($query) {
            if (strpos($query, 'vendor_payments') !== false && strpos($query, 'ORDER BY') !== false) {
                return [
                    (object) [
                        'id' => 50,
                        'org_id' => 5,
                        'vendor_id' => 10,
                        'payment_date' => '2026-06-01',
                        'amount' => '200.00',
                        'payment_method' => 'bank_transfer',
                        'type' => 'payment',
                        'reference' => 'CHK-1',
                        'notes' => '',
                        'reverses_payment_id' => null,
                        'created_at' => '2026-06-01 10:00:00',
                    ],
                ];
            }
            return [];
        };
        $wpdb->test_get_col_callback = static function ($query) {
            if (strpos($query, 'reverses_payment_id') !== false) {
                return ['50'];
            }
            return [];
        };

        $payments = OraBooks_AP::list_payments(5, ['vendor_id' => 10]);
        $this->assertCount(1, $payments);
        $this->assertFalse($payments[0]['can_reverse']);
    }

    #[Test]
    public function format_credit_note_exposes_adjustment_fields(): void
    {
        $formatted = OraBooks_AP::format_credit_note((object) [
            'id' => 3,
            'org_id' => 5,
            'vendor_id' => 10,
            'bill_id' => 100,
            'credit_note_number' => 'VCN-2026-000003',
            'credit_date' => '2026-06-01',
            'amount' => '750.00',
            'reason' => 'Adjustment',
            'is_adjustment' => 1,
            'adjustment_account_code' => '5300',
            'requires_second_approval' => 1,
            'workflow_status' => 'draft',
            'created_at' => '2026-06-01',
        ]);

        $this->assertEquals(1, $formatted['is_adjustment']);
        $this->assertEquals(1, $formatted['requires_second_approval']);
        $this->assertEquals('5300', $formatted['adjustment_account_code']);
    }
}
