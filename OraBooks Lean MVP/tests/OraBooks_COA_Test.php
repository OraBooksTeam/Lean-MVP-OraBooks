<?php
/**
 * Unit Tests for OraBooks_COA (SL-017)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_COA_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['orabooks_test_log_events'] = [];

        global $wpdb;
        $wpdb->test_get_var_callback = null;
        $wpdb->test_get_row_callback = null;
        $wpdb->test_get_results_callback = null;
        $wpdb->test_query_callback = null;
        $wpdb->test_insert_callback = null;
        $wpdb->insert_id = 0;
        $wpdb->last_query = '';
        $wpdb->last_result = [];
    }

    #[Test]
    public function test_load_chart_of_accounts_skips_partner_orgs()
    {
        global $wpdb;

        $query_count = 0;
        $wpdb->test_query_callback = function () use (&$query_count) {
            $query_count++;
            return 1;
        };

        OraBooks_COA::load_chart_of_accounts(9, 'partner', 'partner');

        $this->assertSame(0, $query_count);
        $this->assertNotEmpty($GLOBALS['orabooks_test_log_events']);
        $this->assertSame('coa_skipped_partner', $GLOBALS['orabooks_test_log_events'][0]['event_type']);
    }

    #[Test]
    public function test_load_chart_of_accounts_inserts_free_tier_template()
    {
        global $wpdb;

        $inserts = [];
        $balance_inserts = 0;
        $next_id = 100;

        $wpdb->test_query_callback = function ($query) use (&$inserts, &$next_id) {
            if (stripos($query, 'INSERT IGNORE INTO') !== false) {
                $inserts[] = $query;
                $next_id++;
                $wpdb->insert_id = $next_id;
                return 1;
            }
            return 0;
        };

        $wpdb->test_insert_callback = function ($table, $data) use (&$balance_inserts) {
            if (stripos($table, 'account_balances') !== false) {
                $balance_inserts++;
            }
            return 1;
        };

        OraBooks_COA::load_chart_of_accounts(5, 'free', 'customer');

        $this->assertCount(5, $inserts);
        $this->assertSame(5, $balance_inserts);
        $this->assertStringContainsString("'1000'", $inserts[0]);
        $this->assertStringContainsString("'5000'", $inserts[4]);
    }

    #[Test]
    public function test_get_accounts_returns_empty_for_partner_org()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function () {
            return (object) ['organization_type' => 'partner'];
        };

        $queried = false;
        $wpdb->test_get_results_callback = function () use (&$queried) {
            $queried = true;
            return [];
        };

        $accounts = OraBooks_COA::get_accounts(3);
        $this->assertSame([], $accounts);
        $this->assertFalse($queried);
    }

    #[Test]
    public function test_get_accounts_includes_journal_usage_flag()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function () {
            return (object) ['organization_type' => 'customer'];
        };

        $wpdb->test_get_results_callback = function ($query) {
            $this->assertStringContainsString('has_journal_entries', $query);
            return [
                (object) [
                    'id' => 1,
                    'code' => '1000',
                    'name' => 'Cash',
                    'type' => 'asset',
                    'normal_balance' => 'debit',
                    'system_generated' => 1,
                    'is_active' => 1,
                    'has_journal_entries' => 1,
                ],
            ];
        };

        $accounts = OraBooks_COA::get_accounts(7);
        $this->assertCount(1, $accounts);
        $this->assertSame(1, (int) $accounts[0]->has_journal_entries);
    }

    #[Test]
    public function test_account_types_enum_is_centralized()
    {
        $this->assertSame(
            ['asset', 'liability', 'equity', 'revenue', 'expense'],
            OraBooks_COA::ACCOUNT_TYPES
        );
    }
}
