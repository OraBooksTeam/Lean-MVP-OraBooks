<?php
/**
 * Unit Tests for OraBooks_Workflow (SL-301)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Workflow_Test extends TestCase
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
    }

    #[Test]
    public function test_machines_include_journal_and_bill()
    {
        $machines = OraBooks_Workflow::get_machines();

        $this->assertArrayHasKey('journal', $machines);
        $this->assertArrayHasKey('bill', $machines);
        $this->assertContains('review_pending', $machines['journal']['states']);
        $this->assertArrayHasKey('submit', $machines['bill']['transitions']);
    }

    #[Test]
    public function test_validate_transition_accepts_valid_journal_submit()
    {
        $result = OraBooks_Workflow::validate_transition('journal', 'draft', 'submit');

        $this->assertTrue($result);
    }

    #[Test]
    public function test_validate_transition_rejects_invalid_state()
    {
        $result = OraBooks_Workflow::validate_transition('journal', 'posted', 'submit');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_state', $result->get_error_code());
    }

    #[Test]
    public function test_validate_transition_rejects_unknown_event()
    {
        $result = OraBooks_Workflow::validate_transition('journal', 'draft', 'fly');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_event', $result->get_error_code());
    }

    #[Test]
    public function test_record_transition_logs_without_updating_status()
    {
        global $wpdb;

        $journal = (object) [
            'id' => 42,
            'org_id' => 7,
            'status' => 'review_pending',
        ];

        $wpdb->test_get_row_callback = function ($query) use ($journal) {
            if (stripos($query, 'FOR UPDATE') !== false) {
                return $journal;
            }
            return null;
        };

        $captured = null;
        $wpdb->test_insert_callback = function ($table, $data) use (&$captured) {
            $captured = [$table, $data];
            return true;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 801;

        $result = OraBooks_Workflow::record_transition('journal', 42, 'approve', 5);

        $this->assertIsArray($result);
        $this->assertEquals('review_pending', $result['from_state']);
        $this->assertEquals('approved', $result['to_state']);
        $this->assertStringContainsString('state_machine_transitions', $captured[0]);
        $this->assertEquals('approve', $captured[1]['event']);
    }

    #[Test]
    public function test_transition_updates_status_when_enabled()
    {
        global $wpdb;

        $bill = (object) [
            'id' => 15,
            'org_id' => 3,
            'workflow_status' => 'draft',
        ];

        $updated = null;
        $wpdb->test_get_row_callback = function ($query) use ($bill) {
            if (stripos($query, 'FOR UPDATE') !== false) {
                return $bill;
            }
            return null;
        };
        $wpdb->test_update_callback = function ($table, $data, $where) use (&$updated) {
            $updated = [$table, $data, $where];
            return true;
        };
        $wpdb->test_insert_callback = function () {
            return true;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 802;

        $result = OraBooks_Workflow::transition('bill', 15, 'submit', [
            'user_id' => 9,
            'org_id'  => 3,
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('submitted', $result['to_state']);
        $this->assertNotNull($updated);
        $this->assertEquals('submitted', $updated[1]['workflow_status']);
    }

    #[Test]
    public function test_format_transition_row()
    {
        $row = (object) [
            'id' => 1,
            'record_type' => 'journal',
            'record_id' => 10,
            'from_state' => 'draft',
            'to_state' => 'review_pending',
            'event' => 'submit',
            'triggered_by' => 2,
            'reason' => null,
            'created_at' => '2026-06-18 12:00:00',
        ];

        $formatted = OraBooks_Workflow::format_transition_row($row);

        $this->assertEquals('journal', $formatted['record_type']);
        $this->assertEquals('submit', $formatted['event']);
        $this->assertEquals(2, $formatted['triggered_by']);
    }
}
