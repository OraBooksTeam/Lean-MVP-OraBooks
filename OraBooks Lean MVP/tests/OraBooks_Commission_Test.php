<?php
/**
 * Unit Tests for OraBooks_Commission (SL-068)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Commission_Test extends TestCase
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
        $GLOBALS['orabooks_test_transients'] = [];

        $_GET = [];
        $_POST = [];
    }

    private function config(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 1,
            'base_monthly_amount' => 10.00,
            'max_years' => 6,
            'yearly_percentages' => [20, 15, 10, 5, 2.5, 1],
            'currency' => 'USD',
            'min_payout_threshold' => 25.00,
            'customer_active_window_days' => 30,
            'expiry_accounting_action' => 'reverse_expense',
            'payout_fee_type' => 'percentage',
            'payout_fee_rate' => 2.5,
        ], $overrides);
    }

    #[Test]
    public function test_schema_defines_sl068_tables_and_fee_columns()
    {
        $sql = implode("\n", OraBooks_Commission::get_create_table_sql());

        $this->assertStringContainsString('orabooks_partner_commission_config', $sql);
        $this->assertStringContainsString('orabooks_customer_active_status', $sql);
        $this->assertStringContainsString('orabooks_commission_escrow_schedule', $sql);
        $this->assertStringContainsString('orabooks_commission_release_schedule', $sql);
        $this->assertStringContainsString('orabooks_commissions_earned', $sql);
        $this->assertStringContainsString('orabooks_commission_event_consumptions', $sql);
        $this->assertStringContainsString('orabooks_commission_payouts', $sql);
        $this->assertStringContainsString('gross_amount', $sql);
        $this->assertStringContainsString('fee_amount', $sql);
        $this->assertStringContainsString('net_amount', $sql);
    }

    #[Test]
    public function test_seed_default_config_inserts_platform_defaults()
    {
        global $wpdb;
        $inserted = [];

        $wpdb->test_get_var_callback = function ($query) {
            return null;
        };
        $wpdb->test_insert_callback = function ($table, $data) use (&$inserted) {
            $inserted = [$table, $data];
        };

        OraBooks_Commission::seed_default_config();

        $this->assertEquals('wp_test_orabooks_partner_commission_config', $inserted[0]);
        $this->assertEquals(25.00, $inserted[1]['min_payout_threshold']);
        $this->assertEquals('percentage', $inserted[1]['payout_fee_type']);
        $this->assertEquals(2.5, $inserted[1]['payout_fee_rate']);
    }

    #[Test]
    public function test_create_escrow_from_verified_attribution_creates_full_schedule()
    {
        global $wpdb;
        $inserts = [];

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'commission_event_consumptions') !== false) {
                return null;
            }
            if (stripos($query, 'customer_active_status') !== false) {
                return 1;
            }
            return null;
        };
        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'partner_commission_config') !== false) {
                return $this->config();
            }
            if (stripos($query, 'orabooks_users') !== false) {
                return (object) ['org_id' => 10];
            }
            return null;
        };
        $wpdb->test_insert_callback = function ($table, $data) use (&$inserts) {
            $inserts[] = [$table, $data];
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 500;

        $result = OraBooks_Commission::create_escrow_from_attribution(77, (object) [
            'partner_user_id' => 5,
            'customer_user_id' => 9,
            'attribution_date' => '2026-01-15 10:00:00',
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(500, $result['escrow_id']);
        $this->assertEquals(72, $result['total_months']);
        $this->assertEquals(64.2, $result['total_amount']);
        $this->assertCount(74, $inserts); // escrow + 72 releases + event consumption
        $this->assertEquals('wp_test_orabooks_commission_escrow_schedule', $inserts[0][0]);
        $this->assertEquals('wp_test_orabooks_commission_event_consumptions', $inserts[73][0]);
    }

    #[Test]
    public function test_create_escrow_is_idempotent_when_event_consumed()
    {
        global $wpdb;
        $insertCount = 0;

        $wpdb->test_get_var_callback = function ($query) {
            return 'attribution_77';
        };
        $wpdb->test_insert_callback = function () use (&$insertCount) {
            $insertCount++;
        };

        $result = OraBooks_Commission::create_escrow_from_attribution(77, (object) [
            'partner_user_id' => 5,
            'customer_user_id' => 9,
            'attribution_date' => '2026-01-15 10:00:00',
        ]);

        $this->assertTrue($result);
        $this->assertEquals(0, $insertCount);
    }

    #[Test]
    public function test_forced_payout_batch_tracks_threshold_hold_and_fee()
    {
        global $wpdb;
        $insertedPayouts = [];

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'partner_commission_config') !== false) {
                return $this->config(['min_payout_threshold' => 25, 'payout_fee_rate' => 2.5]);
            }
            if (stripos($query, 'WHERE u.id = 5') !== false) {
                return (object) ['org_id' => 10, 'org_status' => 'active', 'organization_type' => 'partner'];
            }
            if (stripos($query, 'WHERE u.id = 6') !== false) {
                return (object) ['org_id' => 11, 'org_status' => 'payout_hold', 'organization_type' => 'partner'];
            }
            return null;
        };
        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'SUM(amount) as total_pending') !== false) {
                return [
                    (object) ['partner_user_id' => 5, 'total_pending' => 100.00],
                    (object) ['partner_user_id' => 6, 'total_pending' => 75.00],
                    (object) ['partner_user_id' => 7, 'total_pending' => 12.00],
                ];
            }
            return [];
        };
        $wpdb->test_insert_callback = function ($table, $data) use (&$insertedPayouts) {
            if ($table === 'wp_test_orabooks_commission_payouts') {
                $insertedPayouts[] = $data;
            }
        };

        $result = OraBooks_Commission::process_payout_batch(true);

        $this->assertEquals(1, $result['batches_created']);
        $this->assertEquals(1, $result['skipped_payout_hold']);
        $this->assertEquals(1, $result['skipped_below_threshold']);
        $this->assertCount(1, $insertedPayouts);
        $this->assertEquals(100.00, $insertedPayouts[0]['gross_amount']);
        $this->assertEquals(2.50, $insertedPayouts[0]['fee_amount']);
    }

    #[Test]
    public function test_stats_and_payouts_read_models_are_partner_scoped()
    {
        global $wpdb;
        $values = [125.0, 50.0, 10.0, 75.0, 300.0, 364.2, 3, 2];

        $wpdb->test_get_var_callback = function ($query) use (&$values) {
            return array_shift($values);
        };
        $wpdb->test_get_results_callback = function ($query) {
            return [(object) [
                'id' => 10,
                'partner_user_id' => 5,
                'gross_amount' => 100.00,
                'fee_amount' => 2.50,
                'net_amount' => 97.50,
                'status' => 'settled',
                'created_at' => '2026-02-01 00:00:00',
            ]];
        };

        $stats = OraBooks_Commission::get_commission_stats(5);
        $payouts = OraBooks_Commission::get_payouts(5);

        $this->assertEquals(125.0, $stats['total_earned']);
        $this->assertEquals(50.0, $stats['total_paid']);
        $this->assertEquals(10.0, $stats['total_expired']);
        $this->assertEquals(75.0, $stats['pending_payout']);
        $this->assertEquals(300.0, $stats['escrow_remaining']);
        $this->assertEquals(364.2, $stats['escrow_total']);
        $this->assertCount(1, $payouts);
        $this->assertEquals(2.50, $payouts[0]->fee_amount);
    }
}
