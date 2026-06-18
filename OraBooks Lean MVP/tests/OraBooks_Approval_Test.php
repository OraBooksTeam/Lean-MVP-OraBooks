<?php
/**
 * Unit Tests for OraBooks_Posting approval gate (SL-002)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Approval_Test extends TestCase
{
    #[Test]
    public function test_format_journal_maps_core_fields()
    {
        $journal = (object) [
            'id' => 10,
            'org_id' => 1,
            'journal_number' => 'JE-2026-000001',
            'status' => 'review_pending',
            'transaction_date' => '2026-06-18',
            'total_amount' => 1500.50,
            'source_type' => 'manual',
            'source_id' => null,
            'created_by' => 1,
            'approved_by' => null,
            'posted_by' => null,
            'approval_round' => 1,
            'approval_expires_at' => null,
            'rejected_reason' => null,
            'created_at' => '2026-06-18 09:00:00',
            'approved_at' => null,
            'posted_at' => null,
        ];

        $formatted = OraBooks_Posting::format_journal($journal);

        $this->assertSame(10, $formatted['id']);
        $this->assertSame('review_pending', $formatted['status']);
        $this->assertSame('JE-2026-000001', $formatted['journal_number']);
        $this->assertSame(1500.5, $formatted['total_amount']);
    }

    #[Test]
    public function test_format_approval_history_row()
    {
        $row = (object) [
            'id' => 3,
            'journal_id' => 10,
            'action' => 'approve',
            'performed_by' => 2,
            'approval_round' => 1,
            'revision_number' => 1,
            'reason' => null,
            'created_at' => '2026-06-18 10:00:00',
        ];

        $formatted = OraBooks_Posting::format_approval_history_row($row);

        $this->assertSame('approve', $formatted['action']);
        $this->assertSame(10, $formatted['journal_id']);
    }

    #[Test]
    public function test_reject_journal_requires_reason()
    {
        $result = OraBooks_Posting::reject_journal(1, 1, '');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('reason_required', $result->get_error_code());
    }
}
