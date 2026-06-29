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

        $wpdb->test_query_callback = null;
        $wpdb->test_get_row_callback = null;
        $wpdb->test_get_results_callback = null;
        $wpdb->test_insert_callback = null;
        $wpdb->test_update_callback = null;
    }

    protected function tearDown(): void
    {
        global $wpdb;

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
}
