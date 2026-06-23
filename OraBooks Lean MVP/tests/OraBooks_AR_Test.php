<?php
/**
 * Unit Tests for OraBooks_AR (SL-021 extension)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_AR_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['orabooks_test_current_user_id'] = 1;
        $_POST = [];
        $_GET = [];
    }

    #[Test]
    public function get_ar_config_returns_defaults_when_missing_row(): void
    {
        global $wpdb;
        $wpdb->test_get_row_callback = static function ($query) {
            if (strpos($query, 'customer_ar_configs') !== false) {
                return null;
            }
            return null;
        };

        $config = OraBooks_AR::get_ar_config(5);
        $this->assertSame(5, (int) $config->org_id);
        $this->assertSame(1, (int) $config->auto_post_on_approve);
        $this->assertSame(100.0, (float) $config->write_off_threshold);
    }

    #[Test]
    public function validate_customer_credit_blocks_credit_hold(): void
    {
        global $wpdb;
        $wpdb->test_get_results_callback = static function ($query) {
            if (strpos($query, 'SHOW COLUMNS') !== false) {
                return [
                    (object) ['Field' => 'id'],
                    (object) ['Field' => 'credit_hold'],
                    (object) ['Field' => 'credit_limit'],
                ];
            }
            return [];
        };
        $wpdb->test_get_row_callback = static function ($query) {
            if (strpos($query, 'FROM ' . OraBooks_Database::table('customers')) !== false) {
                return (object) [
                    'id' => 1,
                    'org_id' => 5,
                    'credit_hold' => 1,
                    'credit_limit' => 0,
                    'credit_balance' => 0,
                    'contact_email' => 'hold@example.com',
                    'display_name' => 'Hold Customer',
                ];
            }
            return null;
        };

        $result = OraBooks_Customers::validate_customer_credit_for_invoice((object) [
            'customer_id' => 1,
            'total_amount' => 100,
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('credit_hold', $result->get_error_code());
    }

    #[Test]
    public function format_credit_note_maps_core_fields(): void
    {
        $row = (object) [
            'id' => 9,
            'org_id' => 5,
            'customer_id' => 1,
            'invoice_id' => 100,
            'credit_note_number' => 'CN-2026-000001',
            'credit_date' => '2026-06-01',
            'amount' => '50.00',
            'reason' => 'Return',
            'is_write_off' => 0,
            'requires_second_approval' => 0,
            'workflow_status' => 'draft',
            'journal_id' => null,
            'created_at' => '2026-06-01 12:00:00',
        ];

        $formatted = OraBooks_AR::format_credit_note($row);
        $this->assertSame(9, $formatted['id']);
        $this->assertSame('CN-2026-000001', $formatted['credit_note_number']);
        $this->assertSame(50.0, $formatted['amount']);
        $this->assertSame('draft', $formatted['workflow_status']);
    }

    #[Test]
    public function submit_credit_note_moves_draft_to_submitted(): void
    {
        global $wpdb;
        $note = (object) [
            'id' => 5,
            'org_id' => 1,
            'credit_note_number' => 'CN-2026-000001',
            'workflow_status' => 'draft',
        ];
        $calls = 0;
        $wpdb->test_get_row_callback = static function ($query) use ($note, &$calls) {
            if (strpos($query, 'credit_notes') !== false) {
                $calls++;
                if ($calls >= 2) {
                    return (object) array_merge((array) $note, ['workflow_status' => 'submitted']);
                }
                return $note;
            }
            return null;
        };

        $result = OraBooks_AR::submit_credit_note(1, 5, 10);
        $this->assertSame('submitted', $result['workflow_status']);
    }

    #[Test]
    public function format_payment_marks_reversal_type(): void
    {
        $row = (object) [
            'id' => 3,
            'org_id' => 1,
            'customer_id' => 2,
            'invoice_id' => 10,
            'payment_date' => '2026-06-01',
            'amount' => -50.0,
            'payment_method' => 'bank_transfer',
            'type' => 'reversal',
            'reference' => 'Correction',
            'notes' => '',
            'reverses_payment_id' => 2,
        ];
        $formatted = OraBooks_AR::format_payment($row);
        $this->assertSame('reversal', $formatted['type']);
        $this->assertFalse($formatted['can_reverse']);
    }
}
