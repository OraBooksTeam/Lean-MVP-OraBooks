<?php
/**
 * Unit Tests for OraBooks_Classification (SL-022)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Classification_Test extends TestCase
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
        $GLOBALS['orabooks_test_transients'] = [];
    }

    #[Test]
    public function test_schema_includes_classification_rules_table()
    {
        $sql = implode("\n", OraBooks_Classification::get_create_table_sql());
        $this->assertStringContainsString('orabooks_classification_rules', $sql);
        $this->assertStringContainsString('match_value', $sql);
    }

    #[Test]
    public function test_validate_transition_accepts_valid_journal_submit()
    {
        $result = OraBooks_Classification::format_classification((object) [
            'classification_status' => 'processed',
            'suggested_account_code' => '5100',
            'account_confidence' => 92.5,
            'tax_hints' => wp_json_encode(['tax_rate' => 5, 'tax_type' => 'Sales Tax']),
            'classification_reason' => 'Matched keyword rule',
        ]);

        $this->assertEquals('processed', $result['status']);
        $this->assertEquals('5100', $result['suggested_account_code']);
        $this->assertEquals(92.5, $result['account_confidence']);
        $this->assertFalse($result['low_confidence']);
    }

    #[Test]
    public function test_format_flags_low_confidence()
    {
        $result = OraBooks_Classification::format_classification((object) [
            'classification_status' => 'processed',
            'account_confidence' => 55,
        ]);

        $this->assertTrue($result['low_confidence']);
    }

    #[Test]
    public function test_run_classification_updates_expense()
    {
        global $wpdb;

        $expense = (object) [
            'id' => 12,
            'org_id' => 3,
            'vendor' => 'Office Depot',
            'category' => 'Office Supplies',
            'description' => 'Printer paper',
            'total_amount' => 120.00,
            'classification_status' => 'pending',
        ];

        $wpdb->test_get_row_callback = function ($query) use ($expense) {
            if (stripos($query, 'expenses') !== false) {
                return $expense;
            }
            return null;
        };

        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'classification_rules') !== false) {
                return [(object) [
                    'rule_type' => 'vendor',
                    'match_value' => 'office',
                    'account_code' => '5100',
                    'tax_jurisdiction' => 'US',
                    'priority' => 10,
                ]];
            }
            return [];
        };

        $updated = null;
        $wpdb->test_update_callback = function ($table, $data, $where) use (&$updated) {
            $updated = [$table, $data, $where];
            return 1;
        };

        $wpdb->test_insert_callback = function () {
            return true;
        };

        $result = OraBooks_Classification::run('expense', 12, 3);

        $this->assertIsArray($result);
        $this->assertEquals('5100', $result['suggested_account_code']);
        $this->assertNotNull($updated);
        $this->assertEquals('processed', $updated[1]['classification_status']);
    }

    #[Test]
    public function test_override_marks_status_overridden()
    {
        global $wpdb;

        $expense = (object) [
            'id' => 20,
            'org_id' => 2,
            'classification_status' => 'processed',
            'suggested_account_code' => '5100',
        ];

        $wpdb->test_get_row_callback = function () use ($expense) {
            return $expense;
        };

        $updates = [];
        $wpdb->test_update_callback = function ($table, $data, $where) use (&$updates) {
            $updates[] = $data;
            return 1;
        };

        OraBooks_Classification::override('expense', 20, 2, 5, '5300', 5.0);

        $this->assertNotEmpty($updates);
        $this->assertEquals('overridden', $updates[0]['classification_status']);
        $this->assertEquals('5300', $updates[0]['suggested_account_code']);
    }

    #[Test]
    public function test_request_short_circuits_same_idempotency_key()
    {
        global $wpdb;

        $expense = (object) [
            'id' => 5,
            'org_id' => 1,
            'vendor' => 'Acme',
            'classification_status' => 'processed',
            'classification_idempotency_key' => 'hash-abc',
        ];

        $wpdb->test_get_row_callback = function () use ($expense) {
            return $expense;
        };

        $result = OraBooks_Classification::request('expense', 5, 1, ['idempotency_key' => 'hash-abc']);

        $this->assertIsArray($result);
        $this->assertEquals('processed', $result['status']);
        $this->assertEquals('hash-abc', $result['idempotency_key']);
    }

    #[Test]
    public function test_request_returns_duplicate_error_for_conflicting_key()
    {
        global $wpdb;

        $expense = (object) [
            'id' => 8,
            'org_id' => 1,
            'vendor' => 'Acme',
            'classification_status' => 'pending',
            'classification_idempotency_key' => 'old-key',
        ];

        $wpdb->test_get_row_callback = function () use ($expense) {
            return $expense;
        };

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'classification_idempotency_key') !== false) {
                return 99;
            }
            return null;
        };

        $result = OraBooks_Classification::request('expense', 8, 1, ['idempotency_key' => 'new-key']);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('duplicate', $result->get_error_code());
        $this->assertEquals(409, $result->get_error_data()['status'] ?? null);
    }

    #[Test]
    public function test_handle_async_job_returns_error_message_on_failure()
    {
        $result = OraBooks_Classification::handle_async_job(null, [
            'record_type' => 'invalid_type',
            'record_id' => 1,
            'org_id' => 1,
        ]);

        $this->assertIsString($result);
        $this->assertNotSame(true, $result);
    }

    #[Test]
    public function test_rule_precedence_helpers_sync_both_option_keys()
    {
        OraBooks_Classification::set_rule_precedence_over_ai(true);
        $this->assertTrue(OraBooks_Classification::rule_precedence_over_ai_enabled());

        OraBooks_Classification::set_rule_precedence_over_ai(false);
        $this->assertFalse(OraBooks_Classification::rule_precedence_over_ai_enabled());
    }

    #[Test]
    public function test_journal_line_record_type_is_registered()
    {
        $types = OraBooks_Classification::$record_types;
        $this->assertArrayHasKey('journal_line', $types);
        $this->assertEquals('journal_lines', $types['journal_line']['table']);
    }

    #[Test]
    public function test_format_classification_decodes_json_reason()
    {
        $result = OraBooks_Classification::format_classification((object) [
            'classification_status' => 'failed',
            'classification_reason' => wp_json_encode(['summary' => 'Provider timeout', 'source' => 'system']),
        ]);

        $this->assertEquals('failed', $result['status']);
        $this->assertStringContainsString('timeout', strtolower($result['reason']));
    }

    #[Test]
    public function test_dry_run_returns_suggestion_without_db_update()
    {
        global $wpdb;

        $expense = (object) [
            'id' => 30,
            'org_id' => 2,
            'vendor' => 'Staples',
            'category' => 'Office',
            'description' => 'Pens',
            'total_amount' => 45.00,
        ];

        $wpdb->test_get_row_callback = function () use ($expense) {
            return $expense;
        };

        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'classification_rules') !== false) {
                return [];
            }
            return [];
        };

        $update_called = false;
        $wpdb->test_update_callback = function () use (&$update_called) {
            $update_called = true;
            return 1;
        };

        $result = OraBooks_Classification::dry_run('expense', 30, 2);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('suggested_account_code', $result);
        $this->assertFalse($update_called);
    }
}
