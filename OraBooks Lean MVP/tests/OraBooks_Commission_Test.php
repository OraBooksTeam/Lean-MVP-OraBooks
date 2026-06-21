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
        $wpdb->test_update_callback = null;
        $wpdb->insert_id = 0;
        $wpdb->last_query = '';

        $GLOBALS['orabooks_test_current_user_id'] = 1;
        $GLOBALS['orabooks_test_current_user_can'] = true;
        $GLOBALS['orabooks_test_use_insert_id'] = null;
        $GLOBALS['orabooks_test_transients'] = [];
        $GLOBALS['orabooks_test_cache'] = [];

        $_GET = [];
        $_POST = [];
        $GLOBALS['orabooks_test_commission_skip_posting'] = false;
        $GLOBALS['orabooks_test_commission_journal_posts'] = [];
    }

    private function assertLinesBalance(array $lines): void
    {
        $debits = 0.0;
        $credits = 0.0;
        foreach ($lines as $line) {
            $debits += (float) ($line['debit'] ?? 0);
            $credits += (float) ($line['credit'] ?? 0);
        }
        $this->assertEqualsWithDelta($debits, $credits, 0.001);
    }

    private function config(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 1,
            'base_monthly_amount' => 10.00,
            'max_years' => 6,
            'yearly_percentages' => json_encode([20, 15, 10, 5, 2.5, 1]),
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

    #[Test]
    public function test_payout_settlement_lines_balance_gross_net_and_fee()
    {
        $lines = OraBooks_Commission::build_payout_settlement_lines(100.00, 2.50);
        $this->assertLinesBalance($lines);
        $this->assertEquals(100.00, $lines[0]['debit']);
        $this->assertEquals(97.50, $lines[1]['credit']);
        $this->assertEquals(2.50, $lines[2]['credit']);
        $this->assertTrue(OraBooks_Commission::validate_journal_lines_balance($lines));
    }

    #[Test]
    public function test_gateway_fee_payment_lines_balance()
    {
        $lines = OraBooks_Commission::build_gateway_fee_payment_lines(2.50);
        $this->assertLinesBalance($lines);
        $this->assertEquals('2100', $lines[0]['account_code']);
        $this->assertEquals('1000', $lines[1]['account_code']);
    }

    #[Test]
    public function test_expiry_reversal_lines_support_income_and_expense_actions()
    {
        $expense = OraBooks_Commission::build_expiry_reversal_lines(25.00, 'reverse_expense');
        $income = OraBooks_Commission::build_expiry_reversal_lines(25.00, 'income');
        $this->assertLinesBalance($expense);
        $this->assertLinesBalance($income);
        $this->assertEquals('5000', $expense[1]['account_code']);
        $this->assertEquals('4000', $income[1]['account_code']);
    }

    #[Test]
    public function test_calculate_payout_fee_percentage_and_flat()
    {
        $config = $this->config(['payout_fee_type' => 'percentage', 'payout_fee_rate' => 2.5]);
        $this->assertEquals(2.50, OraBooks_Commission::calculate_payout_fee(100.00, $config));

        $flat = $this->config(['payout_fee_type' => 'flat', 'payout_fee_rate' => 3.75]);
        $this->assertEquals(3.75, OraBooks_Commission::calculate_payout_fee(100.00, $flat));
    }

    #[Test]
    public function test_payout_batch_skips_when_not_first_day_without_force()
    {
        if ((int) date('j') === 1) {
            $this->markTestSkipped('Cannot assert non-first-day skip on the 1st of the month.');
        }

        $result = OraBooks_Commission::process_payout_batch(false);
        $this->assertEquals(0, $result['batches_created']);
        $this->assertEquals(1, $result['skipped_not_first_day']);
    }

    #[Test]
    public function test_settle_payout_posts_journal_before_status_update()
    {
        global $wpdb;
        $updatedPayout = false;
        $updatedEarned = false;

        $GLOBALS['orabooks_test_commission_skip_posting'] = true;
        $GLOBALS['orabooks_test_commission_journal_posts'] = [];

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'commission_payouts') !== false) {
                return (object) [
                    'id' => 42,
                    'status' => 'initiated',
                    'gross_amount' => 100.00,
                    'fee_amount' => 2.50,
                    'partner_user_id' => 5,
                    'org_id' => 10,
                ];
            }
            if (stripos($query, 'FOR UPDATE') !== false && stripos($query, 'commissions_earned') !== false) {
                return (object) [
                    'id' => 99,
                    'org_id' => 10,
                    'status' => 'earned',
                ];
            }
            return null;
        };
        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'commissions_earned') !== false && stripos($query, 'payout_id') !== false) {
                return [(object) ['id' => 99, 'org_id' => 10]];
            }
            return [];
        };
        $wpdb->test_update_callback = function ($table, $data) use (&$updatedPayout, &$updatedEarned) {
            if ($table === 'wp_test_orabooks_commission_payouts') {
                $updatedPayout = true;
            }
            if ($table === 'wp_test_orabooks_commissions_earned' && ($data['status'] ?? '') === 'paid') {
                $updatedEarned = true;
            }
        };
        $wpdb->test_query_callback = function () {
            return true;
        };
        $wpdb->test_insert_callback = function () {
            return true;
        };

        $result = OraBooks_Commission::settle_payout(42, 9001, '2026-02-03');

        $this->assertTrue($result);
        $this->assertCount(1, $GLOBALS['orabooks_test_commission_journal_posts']);
        $journal = $GLOBALS['orabooks_test_commission_journal_posts'][0];
        $this->assertEquals('commission_payout_settlement', $journal['source_type']);
        $this->assertEquals(42, $journal['source_id']);
        $this->assertLinesBalance($journal['lines']);
        $this->assertTrue($updatedPayout);
        $this->assertTrue($updatedEarned);
    }

    #[Test]
    public function test_settle_payout_is_idempotent_when_already_settled()
    {
        global $wpdb;
        $GLOBALS['orabooks_test_commission_skip_posting'] = true;
        $GLOBALS['orabooks_test_commission_journal_posts'] = [];

        $wpdb->test_get_row_callback = function () {
            return (object) ['id' => 42, 'status' => 'settled', 'gross_amount' => 100, 'fee_amount' => 2.5];
        };

        $result = OraBooks_Commission::settle_payout(42, 9001);
        $this->assertTrue($result);
        $this->assertCount(0, $GLOBALS['orabooks_test_commission_journal_posts']);
    }

    #[Test]
    public function test_settle_gateway_fee_requires_settled_payout()
    {
        global $wpdb;
        $GLOBALS['orabooks_test_commission_skip_posting'] = true;
        $GLOBALS['orabooks_test_commission_journal_posts'] = [];

        $wpdb->test_get_row_callback = function () {
            return (object) [
                'id' => 42,
                'status' => 'initiated',
                'fee_amount' => 2.50,
                'partner_user_id' => 5,
                'org_id' => 10,
            ];
        };

        $result = OraBooks_Commission::settle_gateway_fee(42, 9002);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_status', $result->get_error_code());
    }

    #[Test]
    public function test_settle_gateway_fee_posts_fee_journal_for_settled_payout()
    {
        global $wpdb;
        $GLOBALS['orabooks_test_commission_skip_posting'] = true;
        $GLOBALS['orabooks_test_commission_journal_posts'] = [];

        $wpdb->test_get_row_callback = function () {
            return (object) [
                'id' => 42,
                'status' => 'settled',
                'fee_amount' => 2.50,
                'partner_user_id' => 5,
                'org_id' => 10,
            ];
        };

        $result = OraBooks_Commission::settle_gateway_fee(42, 9002, '2026-02-04');
        $this->assertTrue($result);
        $this->assertCount(1, $GLOBALS['orabooks_test_commission_journal_posts']);
        $journal = $GLOBALS['orabooks_test_commission_journal_posts'][0];
        $this->assertEquals('commission_gateway_fee', $journal['source_type']);
        $this->assertLinesBalance($journal['lines']);
    }

    #[Test]
    public function test_bank_manual_match_commission_payout_triggers_settlement()
    {
        global $wpdb;
        $GLOBALS['orabooks_test_commission_skip_posting'] = true;
        $GLOBALS['orabooks_test_commission_journal_posts'] = [];

        $payoutSeen = false;
        $wpdb->test_get_row_callback = function ($query) use (&$payoutSeen) {
            if (stripos($query, 'bank_transactions') !== false) {
                return (object) [
                    'id' => 77,
                    'status' => 'unmatched',
                    'transaction_date' => '2026-02-03',
                ];
            }
            if (stripos($query, 'commission_payouts') !== false) {
                $payoutSeen = true;
                return (object) [
                    'id' => 42,
                    'status' => 'initiated',
                    'gross_amount' => 50.00,
                    'fee_amount' => 1.25,
                    'partner_user_id' => 5,
                    'org_id' => 10,
                ];
            }
            return null;
        };

        $result = OraBooks_Bank_Reconciliation::manual_match(10, 77, 'commission_payout', 42, 1);

        $this->assertIsArray($result);
        $this->assertEquals('matched', $result['status']);
        $this->assertTrue($payoutSeen);
        $this->assertCount(1, $GLOBALS['orabooks_test_commission_journal_posts']);
    }

    #[Test]
    public function test_process_expiry_posts_system_journal_for_expired_earned()
    {
        global $wpdb;
        $GLOBALS['orabooks_test_commission_skip_posting'] = true;
        $GLOBALS['orabooks_test_commission_journal_posts'] = [];

        $expiredUpdated = false;
        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'partner_commission_config') !== false) {
                return $this->config(['expiry_accounting_action' => 'reverse_expense']);
            }
            if (stripos($query, 'FOR UPDATE') !== false && stripos($query, 'commissions_earned') !== false) {
                return (object) [
                    'id' => 88,
                    'org_id' => 10,
                    'partner_user_id' => 5,
                    'customer_id' => 9,
                    'amount' => 12.50,
                    'status' => 'earned',
                ];
            }
            return null;
        };
        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'commissions_earned') !== false && stripos($query, 'expires_at') !== false) {
                return [(object) [
                    'id' => 88,
                    'org_id' => 10,
                    'partner_user_id' => 5,
                    'customer_id' => 9,
                    'amount' => 12.50,
                ]];
            }
            return [];
        };
        $wpdb->test_update_callback = function ($table, $data) use (&$expiredUpdated) {
            if ($table === 'wp_test_orabooks_commissions_earned' && ($data['status'] ?? '') === 'expired') {
                $expiredUpdated = true;
            }
        };
        $wpdb->test_query_callback = function () {
            return true;
        };
        $wpdb->test_insert_callback = function () {
            return true;
        };

        $result = OraBooks_Commission::process_expiry();

        $this->assertEquals(1, $result['expired_earned']);
        $this->assertTrue($expiredUpdated);
        $this->assertCount(1, $GLOBALS['orabooks_test_commission_journal_posts']);
        $this->assertEquals('commission_expiry', $GLOBALS['orabooks_test_commission_journal_posts'][0]['source_type']);
    }

    #[Test]
    public function test_maybe_create_escrow_retries_when_customer_becomes_active()
    {
        global $wpdb;
        $inserts = [];

        $attribution = (object) [
            'id' => 88,
            'partner_user_id' => 5,
            'customer_user_id' => 50,
            'attribution_date' => '2026-01-15 10:00:00',
        ];

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'customer_active_status') !== false) {
                return 1;
            }
            if (stripos($query, 'commission_event_consumptions') !== false) {
                return null;
            }
            return null;
        };
        $wpdb->test_get_results_callback = function ($query) use ($attribution) {
            if (stripos($query, 'partner_attributions') !== false) {
                return [$attribution];
            }
            return [];
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
        $GLOBALS['orabooks_test_use_insert_id'] = 501;

        OraBooks_Commission::maybe_create_escrow_for_active_customer(50);

        $this->assertCount(74, $inserts);
        $this->assertEquals('wp_test_orabooks_commission_escrow_schedule', $inserts[0][0]);
    }

    #[Test]
    public function test_on_customer_active_status_changed_skips_inactive_customers()
    {
        global $wpdb;
        $insertCount = 0;

        $wpdb->test_get_var_callback = function () {
            return 0;
        };
        $wpdb->test_insert_callback = function () use (&$insertCount) {
            $insertCount++;
        };

        OraBooks_Commission::on_customer_active_status_changed(50, false, 10);

        $this->assertEquals(0, $insertCount);
    }

    #[Test]
    public function test_build_yearly_breakdown_uses_config_percentages()
    {
        $config = $this->config();
        $breakdown = OraBooks_Commission::build_yearly_breakdown($config, 64.2);

        $this->assertCount(6, $breakdown);
        $this->assertEquals(1, $breakdown[0]['year']);
        $this->assertEquals(20.0, $breakdown[0]['percentage']);
        $this->assertEquals(24.0, $breakdown[0]['amount']); // 10*12*20%
    }

    #[Test]
    public function test_get_commission_by_customer_masks_email_and_includes_breakdown()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'partner_commission_config') !== false) {
                return $this->config();
            }
            return null;
        };
        $wpdb->test_get_results_callback = function ($query) {
            return [(object) [
                'escrow_id' => 1,
                'customer_id' => 9,
                'customer_email' => 'rahul@gmail.com',
                'total_amount' => 64.2,
                'released_amount' => 10.0,
                'remaining_amount' => 54.2,
                'remaining_amount_status' => 'pending',
                'currency' => 'USD',
                'earned_to_date' => 10.0,
                'paid_to_date' => 0.0,
                'next_expiry' => '2032-01-31 00:00:00',
            ]];
        };

        $rows = OraBooks_Commission::get_commission_by_customer(5);

        $this->assertCount(1, $rows);
        $this->assertStringContainsString('***', $rows[0]->customer_email_masked);
        $this->assertCount(6, $rows[0]->yearly_breakdown);
        $this->assertEquals(64.2, (float) $rows[0]->total_amount);
    }
}
