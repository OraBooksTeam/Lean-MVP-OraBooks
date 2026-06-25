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
    public function test_request_returns_duplicate_error_with_status_409()
    {
        global $wpdb;

        $invoice = (object) [
            'id' => 77,
            'org_id' => 9,
            'description' => 'Monthly retainer invoice',
            'total_amount' => 5000.00,
        ];

        $wpdb->test_get_row_callback = function () use ($invoice) {
            return $invoice;
        };

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'classification_idempotency_key') !== false) {
                return 11;
            }
            return null;
        };

        $result = OraBooks_Classification::request('invoice', 77, 9, [
            'idempotency_key' => 'dup-key-1',
        ]);

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('duplicate', $result->get_error_code());
        $this->assertEquals(409, (int) ($result->get_error_data()['status'] ?? 0));
    }

    #[Test]
    public function test_preview_returns_non_persistent_classification_payload()
    {
        global $wpdb;

        $invoice = (object) [
            'id' => 88,
            'org_id' => 4,
            'description' => 'Consulting service invoice',
            'total_amount' => 2500.00,
        ];

        $wpdb->test_get_row_callback = function () use ($invoice) {
            return $invoice;
        };

        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'classification_rules') !== false) {
                return [(object) [
                    'rule_type' => 'keyword',
                    'match_value' => 'consulting',
                    'account_code' => '4000',
                    'tax_jurisdiction' => 'US',
                    'priority' => 20,
                ]];
            }
            return [];
        };

        $preview = OraBooks_Classification::preview('invoice', 88, 4);

        $this->assertIsArray($preview);
        $this->assertEquals('preview', $preview['status']);
        $this->assertEquals('4000', $preview['suggested_account_code']);
        $this->assertArrayHasKey('tax_hints', $preview);
    }
}
