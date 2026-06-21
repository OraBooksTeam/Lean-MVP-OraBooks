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
        $wpdb->test_update_callback = null;
        $wpdb->insert_id = 0;
        $wpdb->last_query = '';

        $GLOBALS['orabooks_test_current_user_id'] = 1;
        $GLOBALS['orabooks_test_current_user_can'] = true;
        $GLOBALS['orabooks_test_use_insert_id'] = null;
        $GLOBALS['orabooks_test_log_events'] = [];
        $GLOBALS['orabooks_test_filters'] = [];
        $GLOBALS['orabooks_test_actions'] = [];
        $GLOBALS['orabooks_test_publish_event_result'] = 100;
    }

    private function mock_journal_for_update(object $journal): void
    {
        global $wpdb;

        $queries = [];
        $wpdb->test_query_callback = function ($query) use (&$queries) {
            $queries[] = $query;
            return true;
        };
        $wpdb->test_get_row_callback = function ($query) use ($journal) {
            if (stripos($query, 'FOR UPDATE') !== false) {
                return $journal;
            }
            return null;
        };
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
    public function test_allowed_events_from_draft_journal()
    {
        $events = OraBooks_Workflow::allowed_events('journal', 'draft');

        $this->assertContains('submit', $events);
        $this->assertContains('edit', $events);
        $this->assertNotContains('approve', $events);
    }

    #[Test]
    public function test_validate_transition_accepts_valid_journal_submit()
    {
        $result = OraBooks_Workflow::validate_transition('journal', 'draft', 'submit');

        $this->assertTrue($result);
    }

    #[Test]
    public function test_validate_transition_rejects_invalid_state_and_audits()
    {
        $result = OraBooks_Workflow::validate_transition('journal', 'posted', 'submit');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_state', $result->get_error_code());
        $this->assertEquals(409, $result->get_error_data()['status']);

        $audit = end($GLOBALS['orabooks_test_log_events']);
        $this->assertEquals('invalid_state_transition', $audit['event_type']);
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

        $this->mock_journal_for_update($journal);

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
        $this->assertEquals(7, $captured[1]['org_id']);
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

        $tx_queries = [];
        $updated = null;
        $wpdb->test_query_callback = function ($query) use (&$tx_queries) {
            $tx_queries[] = $query;
            return true;
        };
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
        $this->assertEquals(3, $result['org_id']);
        $this->assertNotNull($updated);
        $this->assertEquals('submitted', $updated[1]['workflow_status']);
        $this->assertContains('START TRANSACTION', $tx_queries);
        $this->assertContains('COMMIT', $tx_queries);
    }

    #[Test]
    public function test_transition_rolls_back_on_precondition_failure()
    {
        global $wpdb;

        $journal = (object) [
            'id' => 5,
            'org_id' => 2,
            'status' => 'draft',
        ];

        $tx_queries = [];
        $wpdb->test_query_callback = function ($query) use (&$tx_queries) {
            $tx_queries[] = $query;
            return true;
        };
        $wpdb->test_get_row_callback = function ($query) use ($journal) {
            if (stripos($query, 'FOR UPDATE') !== false) {
                return $journal;
            }
            return null;
        };

        $GLOBALS['orabooks_test_filters']['orabooks_workflow_preconditions'][] = function ($ok, $record_type, $event) {
            return new WP_Error('fiscal_closed', 'Period closed', ['status' => 400]);
        };

        $result = OraBooks_Workflow::transition('journal', 5, 'submit', [
            'user_id' => 1,
            'org_id'  => 2,
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('fiscal_closed', $result->get_error_code());
        $this->assertContains('ROLLBACK', $tx_queries);
    }

    #[Test]
    public function test_transition_rolls_back_when_event_publish_fails_in_strict_mode()
    {
        global $wpdb;

        $journal = (object) [
            'id' => 8,
            'org_id' => 4,
            'status' => 'draft',
        ];

        $tx_queries = [];
        $wpdb->test_query_callback = function ($query) use (&$tx_queries) {
            $tx_queries[] = $query;
            return true;
        };
        $wpdb->test_get_row_callback = function ($query) use ($journal) {
            if (stripos($query, 'FOR UPDATE') !== false) {
                return $journal;
            }
            return null;
        };
        $wpdb->test_update_callback = function () {
            return true;
        };
        $wpdb->test_insert_callback = function () {
            return true;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 900;

        if (!function_exists('orabooks_publish_event')) {
            $this->markTestSkipped('orabooks_publish_event stub unavailable');
        }

        $GLOBALS['orabooks_test_publish_event_override'] = false;

        $result = OraBooks_Workflow::transition('journal', 8, 'submit', [
            'user_id' => 1,
            'org_id'  => 4,
            'require_event_publish' => true,
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('event_publish_failed', $result->get_error_code());
        $this->assertContains('ROLLBACK', $tx_queries);

        unset($GLOBALS['orabooks_test_publish_event_override']);
    }

    #[Test]
    public function test_transition_fires_after_transition_action()
    {
        global $wpdb;

        $journal = (object) [
            'id' => 11,
            'org_id' => 6,
            'status' => 'draft',
        ];

        $wpdb->test_query_callback = function () {
            return true;
        };
        $wpdb->test_get_row_callback = function ($query) use ($journal) {
            if (stripos($query, 'FOR UPDATE') !== false) {
                return $journal;
            }
            return null;
        };
        $wpdb->test_update_callback = function () {
            return true;
        };
        $wpdb->test_insert_callback = function () {
            return true;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 901;

        $seen = null;
        $GLOBALS['orabooks_test_actions']['orabooks_workflow_after_transition'][] = function (...$args) use (&$seen) {
            $seen = $args;
        };

        OraBooks_Workflow::transition('journal', 11, 'submit', [
            'user_id' => 3,
            'org_id'  => 6,
        ]);

        $this->assertNotNull($seen);
        $this->assertEquals('journal', $seen[0]);
        $this->assertEquals(11, $seen[1]);
        $this->assertEquals('submit', $seen[2]);
    }

    #[Test]
    public function test_format_transition_row_includes_org_id()
    {
        $row = (object) [
            'id' => 1,
            'org_id' => 99,
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
        $this->assertEquals(99, $formatted['org_id']);
    }

    #[Test]
    public function test_transition_uses_for_update_row_lock()
    {
        global $wpdb;

        $invoice = (object) [
            'id' => 20,
            'org_id' => 3,
            'workflow_status' => 'draft',
        ];

        $for_update_seen = false;
        $wpdb->test_query_callback = function () {
            return true;
        };
        $wpdb->test_get_row_callback = function ($query) use (&$for_update_seen, $invoice) {
            if (stripos($query, 'FOR UPDATE') !== false) {
                $for_update_seen = true;
                return $invoice;
            }
            return null;
        };
        $wpdb->test_update_callback = function () {
            return true;
        };
        $wpdb->test_insert_callback = function () {
            return true;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 902;

        $result = OraBooks_Workflow::transition('invoice', 20, 'send', [
            'user_id' => 1,
            'org_id'  => 3,
        ]);

        $this->assertIsArray($result);
        $this->assertTrue($for_update_seen, 'Expected SELECT ... FOR UPDATE during transition');
    }

    #[Test]
    public function test_invoice_cancel_transition_via_engine()
    {
        global $wpdb;

        $invoice = (object) [
            'id' => 21,
            'org_id' => 3,
            'workflow_status' => 'sent',
        ];

        $wpdb->test_query_callback = function () {
            return true;
        };
        $wpdb->test_get_row_callback = function ($query) use ($invoice) {
            if (stripos($query, 'FOR UPDATE') !== false) {
                return $invoice;
            }
            return null;
        };
        $wpdb->test_update_callback = function ($table, $data) {
            return isset($data['workflow_status']) && $data['workflow_status'] === 'cancelled';
        };
        $wpdb->test_insert_callback = function () {
            return true;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 903;

        $result = OraBooks_Workflow::transition('invoice', 21, 'cancel', [
            'user_id' => 1,
            'org_id'  => 3,
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('cancelled', $result['to_state']);
    }
}
