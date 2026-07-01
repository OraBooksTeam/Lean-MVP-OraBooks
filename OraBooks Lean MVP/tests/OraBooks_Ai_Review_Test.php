<?php
/**
 * Unit Tests for OraBooks_Ai_Review (SL-076)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Ai_Review_Test extends TestCase
{
    protected function setUp(): void
    {
        global $wpdb;

        $GLOBALS['orabooks_test_current_user_id'] = 1;
        $GLOBALS['orabooks_test_org_callback'] = null;
        $wpdb->test_query_callback = null;
        $wpdb->test_get_row_callback = null;
        $wpdb->test_get_results_callback = null;
        $wpdb->test_insert_callback = null;
        $wpdb->test_update_callback = null;
    }

    protected function tearDown(): void
    {
        global $wpdb;

        $GLOBALS['orabooks_test_current_user_id'] = 1;
        $GLOBALS['orabooks_test_org_callback'] = null;
        $wpdb->test_query_callback = null;
        $wpdb->test_get_row_callback = null;
        $wpdb->test_get_results_callback = null;
        $wpdb->test_insert_callback = null;
        $wpdb->test_update_callback = null;
    }

    #[Test]
    public function test_schema_defines_sl076_tables()
    {
        $sql = implode("\n", OraBooks_Ai_Review::get_create_table_sql());

        $this->assertStringContainsString('orabooks_ai_review_queue', $sql);
        $this->assertStringContainsString('orabooks_ai_review_history', $sql);
        $this->assertStringContainsString('orabooks_ai_review_dead_letters', $sql);
        $this->assertStringContainsString("ENUM('pending','processing','escalated','resolved')", $sql);
    }

    #[Test]
    public function test_passes_threshold_requires_confidence_and_low_risk()
    {
        $this->assertTrue(OraBooks_Ai_Review::passes_threshold([
            'confidence' => 75,
            'risk_level' => 'low',
        ]));

        $this->assertFalse(OraBooks_Ai_Review::passes_threshold([
            'confidence' => 69,
            'risk_level' => 'low',
        ]));

        $this->assertFalse(OraBooks_Ai_Review::passes_threshold([
            'confidence' => 80,
            'risk_level' => 'medium',
        ]));
    }

    #[Test]
    public function test_format_queue_item_maps_expected_fields()
    {
        $row = (object) [
            'id' => 5,
            'org_id' => 1,
            'resource_type' => 'journal',
            'resource_id' => 10,
            'journal_id' => 10,
            'journal_number' => 'JE-2026-000010',
            'confidence_score' => 62.5,
            'risk_level' => 'medium',
            'escalation_reason' => 'low_confidence',
            'explanation' => 'High-value transaction',
            'total_amount' => 75000,
            'priority_score' => 120,
            'status' => 'escalated',
            'retry_count' => 3,
            'created_at' => '2026-06-18 09:00:00',
            'updated_at' => '2026-06-18 09:05:00',
        ];

        $formatted = OraBooks_Ai_Review::format_queue_item($row);

        $this->assertSame(5, $formatted['id']);
        $this->assertSame('escalated', $formatted['status']);
        $this->assertSame('JE-2026-000010', $formatted['journal_number']);
        $this->assertSame(62.5, $formatted['confidence_score']);
        $this->assertSame('medium', $formatted['risk_level']);
    }

    #[Test]
    public function test_evaluate_journal_flags_high_value_entries()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (strpos($query, 'journals') !== false) {
                return (object) [
                    'id' => 1,
                    'org_id' => 1,
                    'total_amount' => 100000,
                ];
            }
            return null;
        };

        $wpdb->test_get_results_callback = function () {
            return [
                (object) ['description' => 'Office supplies'],
                (object) ['description' => 'Cash'],
            ];
        };

        $evaluation = OraBooks_Ai_Review::evaluate_journal(1, 1);

        $this->assertLessThan(OraBooks_Ai_Review::CONFIDENCE_THRESHOLD, $evaluation['confidence']);
        $this->assertContains($evaluation['risk_level'], ['medium', 'high']);
    }

    #[Test]
    public function test_max_retry_escalation_copies_item_to_dead_letters_and_logs_history()
    {
        global $wpdb;

        $history_table = OraBooks_Database::table(OraBooks_Ai_Review::TABLE_HISTORY);
        $dead_table = OraBooks_Database::table(OraBooks_Ai_Review::TABLE_DEAD_LETTERS);

        $pending_item = (object) [
            'id' => 11,
            'org_id' => 3,
            'resource_type' => 'csv_import',
            'resource_id' => 77,
            'journal_id' => null,
            'confidence_score' => 42.0,
            'risk_level' => 'high',
            'explanation' => 'Persistent low confidence',
            'model_version' => 'mvp-stub-1.0',
            'retry_count' => OraBooks_Ai_Review::MAX_RETRIES,
            'priority_score' => 120,
            'total_amount' => 0,
            'status' => 'pending',
            'created_at' => '2026-06-01 00:00:00',
            'updated_at' => '2026-06-01 00:05:00',
        ];

        $insert_calls = [];
        $claim_calls = 0;

        $wpdb->test_get_row_callback = function ($query) use ($pending_item, &$claim_calls) {
            if (strpos((string) $query, 'FOR UPDATE SKIP LOCKED') !== false) {
                $claim_calls++;
                return $claim_calls === 1 ? $pending_item : null;
            }
            return null;
        };

        $wpdb->test_insert_callback = function ($table, $data) use (&$insert_calls) {
            $insert_calls[] = ['table' => $table, 'data' => $data];
        };

        OraBooks_Ai_Review::init()->cron_process_queue();

        $dead_letter_call = null;
        $dead_letter_history = null;

        foreach ($insert_calls as $call) {
            if ($call['table'] === $dead_table) {
                $dead_letter_call = $call;
            }
            if ($call['table'] === $history_table && ($call['data']['action'] ?? '') === 'dead_letter') {
                $dead_letter_history = $call;
            }
        }

        $this->assertNotNull($dead_letter_call, 'Expected dead-letter insert to run for terminal retries.');
        $this->assertSame(11, (int) ($dead_letter_call['data']['queue_id'] ?? 0));
        $this->assertSame(3, (int) ($dead_letter_call['data']['org_id'] ?? 0));

        $this->assertNotNull($dead_letter_history, 'Expected dead_letter action in history log.');
        $this->assertSame(11, (int) ($dead_letter_history['data']['queue_id'] ?? 0));
    }

    #[Test]
    public function test_cron_process_queue_uses_atomic_claim_query()
    {
        global $wpdb;

        $pending_item = (object) [
            'id' => 19,
            'org_id' => 4,
            'resource_type' => 'journal',
            'resource_id' => 19,
            'journal_id' => 19,
            'confidence_score' => 84.0,
            'risk_level' => 'low',
            'explanation' => 'Ready for review',
            'model_version' => 'mvp-stub-1.0',
            'retry_count' => 0,
            'priority_score' => 15,
            'total_amount' => 1000,
            'status' => 'pending',
            'created_at' => '2026-06-01 00:00:00',
            'updated_at' => '2026-06-01 00:00:00',
        ];

        $queries = [];
        $claim_calls = 0;

        $wpdb->test_query_callback = function ($query) use (&$queries) {
            $queries[] = $query;
            return 1;
        };

        $wpdb->test_get_row_callback = function ($query) use ($pending_item, &$claim_calls, &$queries) {
            $queries[] = $query;
            if (strpos((string) $query, 'FOR UPDATE SKIP LOCKED') !== false) {
                $claim_calls++;
                return $claim_calls === 1 ? $pending_item : null;
            }

            if (strpos((string) $query, 'FROM ' . OraBooks_Database::table('journals')) !== false) {
                return (object) [
                    'id' => 19,
                    'org_id' => 4,
                    'total_amount' => 1000,
                ];
            }

            return null;
        };

        $wpdb->test_get_results_callback = function ($query) use (&$queries) {
            $queries[] = $query;
            return [
                (object) ['description' => 'Office supplies'],
                (object) ['description' => 'Cash'],
            ];
        };

        OraBooks_Ai_Review::init()->cron_process_queue();

        $claim_query = null;
        foreach ($queries as $query) {
            if (strpos((string) $query, 'FOR UPDATE SKIP LOCKED') !== false) {
                $claim_query = $query;
                break;
            }
        }

        $this->assertNotNull($claim_query, 'Expected atomic claim query to be executed.');
        $this->assertStringContainsString('FOR UPDATE SKIP LOCKED', $claim_query);
    }

    #[Test]
    public function test_claim_next_item_only_allows_one_worker_to_claim_same_row()
    {
        global $wpdb;

        $item = (object) [
            'id' => 21,
            'org_id' => 8,
            'resource_type' => 'journal',
            'resource_id' => 21,
            'journal_id' => 21,
            'status' => 'pending',
            'created_at' => '2026-06-01 00:00:00',
            'updated_at' => '2026-06-01 00:00:00',
        ];

        $claim_calls = 0;
        $history_actions = [];

        $wpdb->test_get_row_callback = function ($query) use ($item, &$claim_calls) {
            if (strpos((string) $query, 'FOR UPDATE SKIP LOCKED') !== false) {
                $claim_calls++;
                return $claim_calls === 1 ? $item : null;
            }
            return null;
        };

        $wpdb->test_insert_callback = function ($table, $data) use (&$history_actions) {
            if (strpos((string) $table, 'ai_review_history') !== false) {
                $history_actions[] = $data['action'] ?? null;
            }
        };

        $instance = OraBooks_Ai_Review::init();
        $method = new ReflectionMethod(OraBooks_Ai_Review::class, 'claim_next_item');
        $method->setAccessible(true);

        $first = $method->invoke($instance);
        $second = $method->invoke($instance);

        $this->assertNotNull($first);
        $this->assertSame(21, (int) $first->id);
        $this->assertNull($second);
        $this->assertSame(['claim'], $history_actions);
    }

    #[Test]
    public function test_retry_helpers_are_deterministic()
    {
        $this->assertSame(1, OraBooks_Ai_Review::next_retry_count(0));
        $this->assertSame(2, OraBooks_Ai_Review::next_retry_count(1));
        $this->assertSame(4, OraBooks_Ai_Review::next_retry_count(3));

        $this->assertSame(10, OraBooks_Ai_Review::backoff_seconds_for_retry(1));
        $this->assertSame(20, OraBooks_Ai_Review::backoff_seconds_for_retry(2));
        $this->assertSame(40, OraBooks_Ai_Review::backoff_seconds_for_retry(3));

        $this->assertFalse(OraBooks_Ai_Review::should_escalate_after_retry(3));
        $this->assertTrue(OraBooks_Ai_Review::should_escalate_after_retry(4));
    }

    #[Test]
    public function test_process_queue_item_schedules_retry_with_expected_backoff()
    {
        global $wpdb;

        $item = (object) [
            'id' => 41,
            'org_id' => 5,
            'resource_type' => 'csv_import',
            'resource_id' => 201,
            'journal_id' => null,
            'confidence_score' => 45.0,
            'risk_level' => 'high',
            'explanation' => 'Needs retry',
            'model_version' => 'mvp-stub-1.0',
            'retry_count' => 0,
            'status' => 'processing',
        ];

        $retry_update = null;
        $history_actions = [];
        $before = time();

        $wpdb->test_update_callback = function ($table, $data) use (&$retry_update) {
            if (($data['status'] ?? null) === 'pending' && isset($data['next_retry_at'])) {
                $retry_update = $data;
            }
            return 1;
        };

        $wpdb->test_insert_callback = function ($table, $data) use (&$history_actions) {
            if (strpos((string) $table, 'ai_review_history') !== false) {
                $history_actions[] = $data['action'] ?? null;
            }
        };

        $instance = OraBooks_Ai_Review::init();
        $method = new ReflectionMethod(OraBooks_Ai_Review::class, 'process_queue_item');
        $method->setAccessible(true);
        $method->invoke($instance, $item);

        $this->assertNotNull($retry_update);
        $this->assertSame(1, (int) $retry_update['retry_count']);
        $delta = strtotime((string) $retry_update['next_retry_at']) - $before;
        $this->assertGreaterThanOrEqual(9, $delta);
        $this->assertLessThanOrEqual(11, $delta);
        $this->assertContains('retry', $history_actions);
    }

    #[Test]
    public function test_list_queue_uses_org_and_status_filters()
    {
        global $wpdb;

        $captured_query = null;
        $wpdb->test_get_results_callback = function ($query) use (&$captured_query) {
            $captured_query = $query;
            return [
                (object) [
                    'id' => 1,
                    'org_id' => 9,
                    'resource_type' => 'journal',
                    'resource_id' => 88,
                    'journal_id' => 88,
                    'journal_number' => 'JE-88',
                    'confidence_score' => 61.0,
                    'risk_level' => 'high',
                    'escalation_reason' => 'low_confidence',
                    'explanation' => 'Ambiguous journal',
                    'total_amount' => 500,
                    'priority_score' => 101,
                    'status' => 'escalated',
                    'retry_count' => 2,
                    'created_at' => '2026-06-01 00:00:00',
                    'updated_at' => '2026-06-01 00:00:00',
                ],
            ];
        };

        $rows = OraBooks_Ai_Review::list_queue(9, ['statuses' => ['escalated'], 'limit' => 5]);

        $this->assertCount(1, $rows);
        $this->assertStringContainsString("q.org_id = 9", (string) $captured_query);
        $this->assertStringContainsString("q.status IN ('escalated')", (string) $captured_query);
    }

    #[Test]
    public function test_get_queue_stats_aggregates_status_counts()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            if (strpos((string) $query, 'GROUP BY status') !== false) {
                return [
                    (object) ['status' => 'pending', 'total' => 2],
                    (object) ['status' => 'processing', 'total' => 1],
                    (object) ['status' => 'escalated', 'total' => 3],
                    (object) ['status' => 'resolved', 'total' => 4],
                ];
            }
            return [];
        };

        $stats = OraBooks_Ai_Review::get_queue_stats(7);

        $this->assertSame(2, $stats['pending']);
        $this->assertSame(1, $stats['processing']);
        $this->assertSame(3, $stats['escalated']);
        $this->assertSame(4, $stats['resolved']);
        $this->assertSame(6, $stats['total_open']);
    }

    #[Test]
    public function test_resolve_ai_review_marks_all_matching_items_resolved()
    {
        global $wpdb;

        $items = [
            (object) ['id' => 31, 'org_id' => 4, 'journal_id' => 12, 'status' => 'pending'],
            (object) ['id' => 32, 'org_id' => 4, 'journal_id' => 12, 'status' => 'escalated'],
        ];
        $updated_ids = [];
        $history_actions = [];

        $wpdb->test_get_results_callback = function ($query) use ($items) {
            if (strpos((string) $query, 'journal_id') !== false) {
                return $items;
            }
            return [];
        };

        $wpdb->test_update_callback = function ($table, $data, $where) use (&$updated_ids) {
            $updated_ids[] = (int) ($where['id'] ?? 0);
            return 1;
        };

        $wpdb->test_insert_callback = function ($table, $data) use (&$history_actions) {
            if (strpos((string) $table, 'ai_review_history') !== false) {
                $history_actions[] = $data['action'] ?? null;
            }
        };

        $resolved = OraBooks_Ai_Review::resolve_ai_review(12, 4, 99);

        sort($updated_ids);
        $this->assertSame(2, $resolved);
        $this->assertSame([31, 32], $updated_ids);
        $this->assertSame(['resolve', 'resolve'], $history_actions);
    }

    #[Test]
    public function test_resolve_ai_review_by_resource_uses_org_scope()
    {
        global $wpdb;

        $captured_query = null;
        $updated_ids = [];

        $wpdb->test_get_results_callback = function ($query) use (&$captured_query) {
            $captured_query = $query;
            return [
                (object) ['id' => 51, 'org_id' => 7, 'resource_type' => 'voice_input', 'resource_id' => 99, 'status' => 'pending'],
            ];
        };

        $wpdb->test_update_callback = function ($table, $data, $where) use (&$updated_ids) {
            $updated_ids[] = (int) ($where['id'] ?? 0);
            return 1;
        };

        $resolved = OraBooks_Ai_Review::resolve_ai_review_by_resource(7, 'voice_input', 99, 101);

        $this->assertSame(1, $resolved);
        $this->assertSame([51], $updated_ids);
        $this->assertStringContainsString("org_id = 7", (string) $captured_query);
        $this->assertStringContainsString("resource_type = 'voice_input'", (string) $captured_query);
        $this->assertStringContainsString("resource_id = 99", (string) $captured_query);
    }

    #[Test]
    public function test_ajax_list_requires_authentication()
    {
        $GLOBALS['orabooks_test_current_user_id'] = 0;
        $_GET = ['org_id' => 1];
        $_POST = [];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not authenticated');

        OraBooks_Ai_Review::init()->ajax_list();
    }
}
