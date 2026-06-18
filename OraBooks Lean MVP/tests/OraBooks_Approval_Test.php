<?php
/**
 * Unit Tests for OraBooks_Approval (SL-002)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Approval_Test extends TestCase
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
        $wpdb->test_update_callback = null;
        $wpdb->insert_id = 0;
        $wpdb->last_query = '';

        $GLOBALS['orabooks_test_current_user_id'] = 2;
        $GLOBALS['orabooks_test_current_user_can'] = true;
        $GLOBALS['orabooks_test_use_insert_id'] = null;
        $GLOBALS['orabooks_test_totp_secret'] = 'TESTSECRET123456';
        $GLOBALS['orabooks_test_verify_totp_result'] = true;
        $GLOBALS['orabooks_test_user_meta'] = [
            2 => ['orabooks_2fa_secret' => 'TESTSECRET123456'],
        ];

        $_GET = [];
        $_POST = [];
    }

    private function journal(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 10,
            'org_id' => 1,
            'status' => 'review_pending',
            'created_by' => 1,
            'total_amount' => 500.00,
            'approval_round' => 1,
            'revision_number' => 1,
            'transaction_date' => '2026-06-18',
            'metadata' => null,
            'source_type' => 'manual',
            'source_id' => null,
        ], $overrides);
    }

    private function policy(array $overrides = []): object
    {
        return (object) array_merge([
            'org_id' => 1,
            'approval_expiry_hours' => 72,
            'reminder_hours_before_expiry' => 24,
            'max_approval_rounds' => 5,
            'maker_checker_required' => 1,
            'mfa_amount_threshold' => 10000.00,
            'escalation_after_hours' => 48,
            'escalation_role' => 'admin',
        ], $overrides);
    }

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
    public function test_format_approval_history_row_includes_snapshot_hash()
    {
        $row = (object) [
            'id' => 3,
            'journal_id' => 10,
            'action' => 'approve',
            'performed_by' => 2,
            'approval_round' => 1,
            'revision_number' => 1,
            'snapshot_hash' => 'abc123',
            'reason' => null,
            'created_at' => '2026-06-18 10:00:00',
        ];

        $formatted = OraBooks_Posting::format_approval_history_row($row);

        $this->assertSame('approve', $formatted['action']);
        $this->assertSame('abc123', $formatted['snapshot_hash']);
    }

    #[Test]
    public function test_reject_journal_requires_reason()
    {
        $result = OraBooks_Posting::reject_journal(1, 1, '');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('reason_required', $result->get_error_code());
    }

    #[Test]
    public function test_validate_submit_rounds_blocks_when_max_reached()
    {
        $journal = $this->journal(['approval_round' => 5]);
        $policy = $this->policy(['max_approval_rounds' => 5]);

        $result = OraBooks_Approval::validate_submit_rounds($journal, $policy);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('max_rounds', $result->get_error_code());
    }

    #[Test]
    public function test_compute_snapshot_hash_is_deterministic()
    {
        global $wpdb;

        $journal = $this->journal();
        $line = (object) [
            'account_id' => 100,
            'debit_amount' => 100.00,
            'credit_amount' => 0,
            'description' => 'Office supplies',
            'currency_code' => 'USD',
        ];

        $wpdb->test_get_row_callback = function ($query) use ($journal) {
            if (stripos($query, 'journal_lines') !== false) {
                return null;
            }
            return $journal;
        };
        $wpdb->test_get_results_callback = function ($query) use ($line) {
            if (stripos($query, 'journal_lines') !== false) {
                return [$line];
            }
            return [];
        };

        $hash1 = OraBooks_Approval::compute_snapshot_hash(10);
        $hash2 = OraBooks_Approval::compute_snapshot_hash(10);

        $this->assertSame($hash1, $hash2);
        $this->assertSame(64, strlen($hash1));
    }

    #[Test]
    public function test_approve_journal_blocks_maker_checker()
    {
        global $wpdb;

        $journal = $this->journal(['created_by' => 2]);
        $wpdb->test_get_row_callback = function ($query) use ($journal) {
            if (stripos($query, 'approval_policies') !== false) {
                return $this->policy();
            }
            return $journal;
        };

        $result = OraBooks_Approval::approve_journal(10, 2);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('maker_checker', $result->get_error_code());
    }

    #[Test]
    public function test_approve_journal_requires_mfa_for_high_amount()
    {
        global $wpdb;

        $journal = $this->journal(['total_amount' => 25000, 'created_by' => 1]);
        $wpdb->test_get_row_callback = function ($query) use ($journal) {
            if (stripos($query, 'approval_policies') !== false) {
                return $this->policy();
            }
            return $journal;
        };

        $result = OraBooks_Approval::approve_journal(10, 2);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('mfa_required', $result->get_error_code());
    }

    #[Test]
    public function test_approve_journal_succeeds_with_mfa_otp()
    {
        global $wpdb;

        $journal = $this->journal(['total_amount' => 25000, 'created_by' => 1]);
        $line = (object) [
            'account_id' => 100,
            'debit_amount' => 25000,
            'credit_amount' => 0,
            'description' => 'Large entry',
            'currency_code' => 'USD',
        ];

        $wpdb->test_get_row_callback = function ($query) use ($journal) {
            if (stripos($query, 'approval_policies') !== false) {
                return $this->policy();
            }
            if (stripos($query, 'journal_lines') !== false) {
                return null;
            }
            return $journal;
        };
        $wpdb->test_get_results_callback = function ($query) use ($line) {
            if (stripos($query, 'journal_lines') !== false) {
                return [$line];
            }
            return [];
        };

        $result = OraBooks_Approval::approve_journal(10, 2, ['mfa_otp' => '123456']);
        $this->assertTrue($result);
    }

    #[Test]
    public function test_resubmit_requires_draft_with_prior_round()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function () {
            return $this->journal(['status' => 'review_pending', 'approval_round' => 1]);
        };

        $result = OraBooks_Approval::resubmit_journal(10, 2);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_status', $result->get_error_code());
    }

    #[Test]
    public function test_invalidate_on_edit_moves_approved_to_draft()
    {
        global $wpdb;
        $updated = [];

        $wpdb->test_get_row_callback = function () {
            return $this->journal(['status' => 'approved', 'revision_number' => 2]);
        };
        $wpdb->test_update_callback = function ($table, $data, $where) use (&$updated) {
            $updated = $data;
            return 1;
        };

        $result = OraBooks_Approval::invalidate_on_edit(10, 2);
        $this->assertTrue($result);
        $this->assertSame('draft', $updated['status']);
        $this->assertSame(3, $updated['revision_number']);
        $this->assertSame(1, $updated['approval_stale']);
    }

    #[Test]
    public function test_create_delegation_rejects_same_user()
    {
        $result = OraBooks_Approval::create_delegation(1, 2, 2, '2026-06-18 00:00:00', '2026-06-25 00:00:00', 2);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_delegate', $result->get_error_code());
    }
}
