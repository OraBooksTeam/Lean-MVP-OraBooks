<?php
/**
 * Unit Tests for OraBooks_Workflow_Integration (SL-301 Phase 3)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Workflow_Integration_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['orabooks_test_has_permission'] = true;
        $GLOBALS['orabooks_test_log_events'] = [];
        $GLOBALS['orabooks_test_filters'] = [];
        $GLOBALS['orabooks_test_actions'] = [];
    }

    #[Test]
    public function test_journal_submit_requires_submit_transaction_permission()
    {
        $GLOBALS['orabooks_test_has_permission'] = false;

        $record = (object) [
            'org_id' => 5,
            'transaction_date' => '2026-06-18',
            'created_by' => 1,
        ];

        $result = OraBooks_Workflow_Integration::apply_preconditions(true, 'journal', 'submit', $record, [
            'user_id' => 2,
            'org_id' => 5,
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('precondition_failed', $result->get_error_code());
    }

    #[Test]
    public function test_journal_approve_allows_when_permission_granted()
    {
        $GLOBALS['orabooks_test_has_permission'] = true;

        $record = (object) [
            'org_id' => 5,
            'transaction_date' => '2026-06-18',
            'created_by' => 1,
        ];

        $result = OraBooks_Workflow_Integration::apply_preconditions(true, 'journal', 'approve', $record, [
            'user_id' => 2,
            'org_id' => 5,
        ]);

        $this->assertTrue($result);
    }

    #[Test]
    public function test_track_failure_logs_audit_and_metric()
    {
        global $wpdb;

        $inserted = [];
        $wpdb->test_insert_callback = function ($table, $data) use (&$inserted) {
            $inserted[] = [$table, $data];
            return true;
        };

        OraBooks_Workflow_Integration::track_failure('bill', 'post', 9, 'invalid_state', [
            'user_id' => 3,
        ]);

        $this->assertNotEmpty($GLOBALS['orabooks_test_log_events']);
        $this->assertEquals('workflow_transition_failed', $GLOBALS['orabooks_test_log_events'][0]['event_type']);
        $this->assertStringContainsString('platform_metrics', $inserted[0][0]);
    }

    #[Test]
    public function test_state_transition_event_consumer_skips_journal_notifications()
    {
        $event = (object) [
            'id' => 501,
            'event_type' => 'state_transition',
            'aggregate_id' => 10,
        ];

        $result = OraBooks_Event_Module::consume_state_transition_notifications($event, [
            'org_id' => 1,
            'record_type' => 'journal',
            'event' => 'submit',
            'to_state' => 'review_pending',
        ]);

        $this->assertTrue($result);
    }

    #[Test]
    public function test_journal_approve_blocks_maker_checker_in_preconditions()
    {
        global $wpdb;

        $GLOBALS['orabooks_test_has_permission'] = true;
        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'approval_policies') !== false) {
                return (object) [
                    'maker_checker_required' => 1,
                    'mfa_amount_threshold' => 10000,
                ];
            }
            return null;
        };

        $record = (object) [
            'org_id' => 5,
            'created_by' => 2,
            'total_amount' => 500,
        ];

        $result = OraBooks_Workflow_Integration::apply_preconditions(true, 'journal', 'approve', $record, [
            'user_id' => 2,
            'org_id' => 5,
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('maker_checker', $result->get_error_code());
    }

    #[Test]
    public function test_expense_lock_precondition_allows_internal_lock()
    {
        $result = OraBooks_Workflow_Integration::apply_preconditions(true, 'expense', 'lock', (object) [
            'org_id' => 5,
        ], [
            'user_id' => 0,
            'org_id' => 5,
        ]);

        $this->assertTrue($result);
    }
}
