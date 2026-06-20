<?php
/**
 * Unit Tests for OraBooks_Partner (SL-013/139)
 *
 * Covers: partner info, active customer count, request reactivation,
 * approve/reject partner code, admin list queries, dashboard data,
 * and activity processing.
 *
 * Run: phpunit --configuration tests/phpunit.xml --testsuite="OraBooks SL-013 Partner Tests"
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Partner_Test extends TestCase
{
    // -----------------------------------------------------------------------
    // Lifecycle
    // -----------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        // Reset all test globals to defaults
        $GLOBALS['orabooks_test_current_user_id'] = 1;
        $GLOBALS['orabooks_test_current_user_can'] = true;
        $GLOBALS['orabooks_test_org_callback'] = null;
        $GLOBALS['orabooks_test_get_user_role_callback'] = null;
        $GLOBALS['orabooks_test_user_meta'] = [];
        $GLOBALS['orabooks_test_use_insert_id'] = null;
        $GLOBALS['orabooks_test_transients'] = [];

        // Reset superglobals
        $_POST = [];
        $_GET  = [];
        $_SESSION = [];

        // Reset wpdb callbacks
        global $wpdb;
        $wpdb->test_get_var_callback     = null;
        $wpdb->test_get_row_callback     = null;
        $wpdb->test_get_results_callback = null;
        $wpdb->test_query_callback       = null;
        $wpdb->test_insert_callback      = null;
        $wpdb->test_update_callback      = null;
        $wpdb->insert_id = 0;
        $wpdb->last_query = '';
    }

    // ================================================================
    // Helpers
    // ================================================================

    /**
     * Build a mock partner code row as returned by get_row queries.
     */
    private function mockPartnerCode(array $overrides = []): object
    {
        return (object) array_merge([
            'id'                        => 1,
            'org_id'                    => 10,
            'user_id'                   => 5,
            'partner_code'              => 'PARTNER-ABC123',
            'partner_type'              => 'individual',
            'organization_name'         => null,
            'status'                    => 'active',
            'org_status'                => 'active',
            'approved_at'               => null,
            'approved_by'               => null,
            'disabled_at'               => null,
            'disabled_reason'           => null,
            'last_attribution_at'       => null,
            'deactivation_reminder_sent_at' => null,
            'low_activity_reminder_sent_at' => null,
            'org_name'                  => 'Test Partner Org',
            'organization_type'         => 'partner',
            'subdomain'                 => 'partner-5',
            'created_at'                => '2026-01-15 10:00:00',
        ], $overrides);
    }

    // ================================================================
    // Helper: capture JSON response from AJAX handlers
    // ================================================================

    /**
     * Invoke an AJAX handler method and return the decoded JSON response.
     */
    private function callAjax(string $method, array $post = []): array
    {
        $_POST = array_merge($_POST, $post);
        $partner = new OraBooks_Partner();
        try {
            $partner->$method();
            $this->fail("Expected RuntimeException (JSON response) was not thrown by {$method}");
        } catch (RuntimeException $e) {
            return json_decode($e->getMessage(), true);
        }
    }

    // ================================================================
    // Helper: orabooks_generate_partner_code()
    // ================================================================

    #[Test]
    public function test_generate_partner_code_format()
    {
        $code = orabooks_generate_partner_code();
        $this->assertIsString($code);
        $this->assertStringStartsWith('PARTNER-', $code);
        // Should be PARTNER- + 8 uppercase hex chars = 16 chars total
        $this->assertEquals(16, strlen($code));
        $this->assertMatchesRegularExpression('/^PARTNER-[A-F0-9]{8}$/', $code);
    }

    // ================================================================
    // get_partner_info()
    // ================================================================

    #[Test]
    public function test_get_partner_info_success()
    {
        global $wpdb;

        $getRowCalls = 0;
        $wpdb->test_get_row_callback = function ($query) use (&$getRowCalls) {
            $getRowCalls++;
            // First call: partner code + org join
            return $this->mockPartnerCode([
                'id'                        => 1,
                'partner_code'              => 'PARTNER-ABC',
                'partner_type'              => 'individual',
                'organization_name'         => null,
                'status'                    => 'active',
                'org_status'                => 'active',
                'created_at'                => '2026-01-15 10:00:00',
                'last_attribution_at'       => '2026-05-01 12:00:00',
                'org_name'                  => 'My Partner Org',
            ]);
        };

        // get_var: first call SHOW TABLES (returns table name = truthy),
        // then active_customer_count queries, then total/verified counts
        $getVarCalls = 0;
        $wpdb->test_get_var_callback = function ($query) use (&$getVarCalls) {
            $getVarCalls++;
            if ($getVarCalls === 1) {
                return 'wp_test_orabooks_customers'; // SHOW TABLES returns table name
            }
            // All COUNT queries return 5
            return 5;
        };

        $result = OraBooks_Partner::get_partner_info(5);

        $this->assertIsArray($result);
        $this->assertEquals('PARTNER-ABC', $result['partner_code']);
        $this->assertEquals('individual', $result['partner_type']);
        $this->assertEquals('active', $result['status']);
        $this->assertEquals('active', $result['org_status']);
        $this->assertNull($result['organization_name']);
        $this->assertEquals(5, $result['active_customers']);
        $this->assertEquals(5, $result['total_attributions']);
        $this->assertEquals(5, $result['verified_attributions']);
        $this->assertEquals('2026-01-15 10:00:00', $result['created_at']);
        $this->assertEquals('2026-05-01 12:00:00', $result['last_attribution_at']);
    }

    #[Test]
    public function test_get_partner_info_not_found()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return null; // No partner code found
        };

        $result = OraBooks_Partner::get_partner_info(999);
        $this->assertNull($result);
    }

    #[Test]
    public function test_get_onboarding_info_contract()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return (object) [
                'partner_code'      => 'PARTNER-ONBOARD',
                'code_status'       => 'pending_review',
                'partner_type'      => 'agency',
                'organization_name' => 'ABC Consulting',
                'org_status'        => 'pending_setup',
                'org_name'          => 'ABC Consulting',
                'created_at'        => '2026-06-01 10:00:00',
            ];
        };

        $result = OraBooks_Partner::get_onboarding_info(5);

        $this->assertIsArray($result);
        $this->assertEquals('PARTNER-ONBOARD', $result['partner_code']);
        $this->assertEquals('pending_review', $result['code_status']);
        $this->assertEquals('agency', $result['partner_type']);
        $this->assertEquals('ABC Consulting', $result['organization_name']);
        $this->assertEquals('pending_setup', $result['org_status']);
        $this->assertFalse($result['bank_info_required']);
        $this->assertFalse($result['payment_settings_available']);
        $this->assertStringContainsString('Awaiting admin approval', $result['status_message']);
    }

    // ================================================================
    // get_active_customer_count()
    // ================================================================

    #[Test]
    public function test_get_active_customer_count_with_customers_table()
    {
        global $wpdb;

        $getVarCalls = 0;
        $wpdb->test_get_var_callback = function ($query) use (&$getVarCalls) {
            $getVarCalls++;
            if ($getVarCalls === 1) {
                return 'wp_test_orabooks_customers'; // Table exists
            }
            return 3; // Active customer count
        };

        $count = OraBooks_Partner::get_active_customer_count(5);
        $this->assertEquals(3, $count);
    }

    #[Test]
    public function test_get_active_customer_count_fallback_no_customers_table()
    {
        global $wpdb;

        $getVarCalls = 0;
        $wpdb->test_get_var_callback = function ($query) use (&$getVarCalls) {
            $getVarCalls++;
            if ($getVarCalls === 1) {
                return false; // SHOW TABLES returns false (table doesn't exist)
            }
            return 7; // Fallback verified attribution count
        };

        $count = OraBooks_Partner::get_active_customer_count(5);
        $this->assertEquals(7, $count);
    }

    #[Test]
    public function test_get_active_customer_count_zero()
    {
        global $wpdb;

        $wpdb->test_get_var_callback = function ($query) {
            return 0;
        };

        $count = OraBooks_Partner::get_active_customer_count(999);
        $this->assertEquals(0, $count);
    }

    // ================================================================
    // request_reactivation()
    // ================================================================

    #[Test]
    public function test_request_reactivation_success()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            // Return inactive code
            return (object)['id' => 1];
        };

        $result = OraBooks_Partner::request_reactivation(5, 10, 'Want to restart partnership');

        $this->assertIsInt($result); // Returns review ID from Organization stub
    }

    #[Test]
    public function test_request_reactivation_not_inactive()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return null; // No inactive code found
        };

        $result = OraBooks_Partner::request_reactivation(5, 10, 'Reactivate me');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('not_inactive', $result->get_error_code());
    }

    // ================================================================
    // approve_partner_code()
    // ================================================================

    #[Test]
    public function test_approve_partner_code_success()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return $this->mockPartnerCode([
                'status'     => 'pending_review',
                'org_status' => 'pending_setup',
            ]);
        };

        $result = OraBooks_Partner::approve_partner_code(1, 1);

        $this->assertTrue($result);
        $this->assertStringContainsString('status', $wpdb->last_query);
        $this->assertStringContainsString('active', $wpdb->last_query);
    }

    #[Test]
    public function test_approve_partner_code_not_found()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return null;
        };

        $result = OraBooks_Partner::approve_partner_code(999, 1);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('not_found', $result->get_error_code());
    }

    #[Test]
    public function test_approve_partner_code_invalid_status()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return $this->mockPartnerCode([
                'status'     => 'active', // Already active — not pending_review
                'org_status' => 'active',
            ]);
        };

        $result = OraBooks_Partner::approve_partner_code(1, 1);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_status', $result->get_error_code());
    }

    #[Test]
    public function test_approve_partner_code_skips_org_update_when_not_pending()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return $this->mockPartnerCode([
                'status'     => 'pending_review',
                'org_status' => 'active', // Already active — org update skipped
            ]);
        };

        $result = OraBooks_Partner::approve_partner_code(1, 1);
        $this->assertTrue($result);
        $this->assertStringContainsString('partner_codes', $wpdb->last_query);
        $this->assertStringContainsString('active', $wpdb->last_query);
        $this->assertStringNotContainsString('organizations', $wpdb->last_query);
    }

    // ================================================================
    // reject_partner_code()
    // ================================================================

    #[Test]
    public function test_reject_partner_code_success()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return $this->mockPartnerCode([
                'status'     => 'pending_review',
                'org_id'     => 10,
            ]);
        };

        $result = OraBooks_Partner::reject_partner_code(1, 1, 'Invalid documents');

        $this->assertTrue($result);
        // Last query should be the org suspension UPDATE
        $this->assertStringContainsString('orabooks_organizations', $wpdb->last_query);
        $this->assertStringContainsString('suspended', $wpdb->last_query);
    }

    #[Test]
    public function test_reject_partner_code_not_found()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return null;
        };

        $result = OraBooks_Partner::reject_partner_code(999, 1);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('not_found', $result->get_error_code());
    }

    #[Test]
    public function test_reject_partner_code_invalid_status()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return $this->mockPartnerCode([
                'status' => 'active', // Already active — not pending_review
            ]);
        };

        $result = OraBooks_Partner::reject_partner_code(1, 1);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_status', $result->get_error_code());
    }

    #[Test]
    public function test_reject_partner_code_default_reason()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return $this->mockPartnerCode([
                'status' => 'pending_review',
                'org_id' => 10,
            ]);
        };

        // No reason provided — should use default
        $result = OraBooks_Partner::reject_partner_code(1, 1);

        $this->assertTrue($result);
        // The org UPDATE is last (last_query = organizations UPDATE),
        // but we verify the function completed successfully
        $this->assertStringContainsString('orabooks_organizations', $wpdb->last_query);
    }

    // ================================================================
    // Admin list queries — pagination args
    // ================================================================

    #[Test]
    public function test_get_pending_partners_with_pagination_args()
    {
        global $wpdb;

        $capturedLimit = null;
        $capturedOffset = null;
        $wpdb->test_get_results_callback = function ($query) use (&$capturedLimit, &$capturedOffset) {
            // Parse LIMIT and OFFSET from the query
            if (preg_match('/LIMIT (\d+) OFFSET (\d+)/i', $query, $m)) {
                $capturedLimit = (int) $m[1];
                $capturedOffset = (int) $m[2];
            }
            return [
                (object)['id' => 1, 'partner_code' => 'PG-1', 'status' => 'pending_review', 'email' => 'p1@t.com'],
            ];
        };

        $results = OraBooks_Partner::get_pending_partners(['limit' => 10, 'offset' => 20]);

        $this->assertCount(1, $results);
        $this->assertEquals(10, $capturedLimit);
        $this->assertEquals(20, $capturedOffset);
    }

    #[Test]
    public function test_get_active_partners_with_pagination_args()
    {
        global $wpdb;

        $capturedLimit = null;
        $capturedOffset = null;
        $wpdb->test_get_results_callback = function ($query) use (&$capturedLimit, &$capturedOffset) {
            if (preg_match('/LIMIT (\d+) OFFSET (\d+)/i', $query, $m)) {
                $capturedLimit = (int) $m[1];
                $capturedOffset = (int) $m[2];
            }
            return [
                (object)['id' => 1, 'partner_code' => 'ACT-1', 'status' => 'active', 'email' => 'a1@t.com', 'verified_attributions' => 3],
            ];
        };

        $results = OraBooks_Partner::get_active_partners(['limit' => 25, 'offset' => 50]);

        $this->assertCount(1, $results);
        $this->assertEquals(25, $capturedLimit);
        $this->assertEquals(50, $capturedOffset);
    }

    // ================================================================
    // Admin list queries
    // ================================================================

    #[Test]
    public function test_get_pending_partners_returns_list()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            return [
                $this->mockPartnerCode(['id' => 1, 'partner_code' => 'PENDING-1', 'status' => 'pending_review', 'email' => 'p1@test.com']),
                $this->mockPartnerCode(['id' => 2, 'partner_code' => 'PENDING-2', 'status' => 'pending_review', 'email' => 'p2@test.com']),
            ];
        };

        $results = OraBooks_Partner::get_pending_partners();

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertEquals('PENDING-1', $results[0]->partner_code);
    }

    #[Test]
    public function test_get_pending_partners_empty()
    {
        global $wpdb;
        $wpdb->test_get_results_callback = function ($query) {
            return [];
        };

        $results = OraBooks_Partner::get_pending_partners();
        $this->assertIsArray($results);
        $this->assertCount(0, $results);
    }

    #[Test]
    public function test_get_active_partners_returns_list()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            return [
                $this->mockPartnerCode(['id' => 1, 'partner_code' => 'ACTIVE-1', 'status' => 'active', 'email' => 'a1@test.com', 'verified_attributions' => 5]),
                $this->mockPartnerCode(['id' => 2, 'partner_code' => 'ACTIVE-2', 'status' => 'active', 'email' => 'a2@test.com', 'verified_attributions' => 2]),
            ];
        };

        $results = OraBooks_Partner::get_active_partners();

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertEquals('ACTIVE-1', $results[0]->partner_code);
    }

    #[Test]
    public function test_get_reactivation_requests_returns_list()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            return [
                (object)[
                    'id'                  => 1,
                    'org_id'              => 10,
                    'requested_by'        => 5,
                    'requested_by_email'  => 'owner@test.com',
                    'org_name'            => 'React Corp',
                    'subdomain'           => 'react',
                    'partner_code'        => 'REACT-001',
                    'partner_type'        => 'agency',
                    'code_status'         => 'pending_review',
                    'reason'              => 'Need to reactivate',
                    'requested_at'        => '2026-06-01 10:00:00',
                    'decision'            => null,
                ],
            ];
        };

        $results = OraBooks_Partner::get_reactivation_requests();

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals('REACT-001', $results[0]->partner_code);
        $this->assertEquals('owner@test.com', $results[0]->requested_by_email);
    }

    // ================================================================
    // get_dashboard_data()
    // ================================================================

    /**
     * Build a comprehensive mock for get_dashboard_data queries.
     * This is a complex function with multiple get_row, get_var, and get_results calls.
     */
    private function setupDashboardDataMocks(array $overrides = [])
    {
        global $wpdb;

        $defaultPartner = [
            'partner_code'              => 'PARTNER-DASH',
            'partner_type'              => 'individual',
            'organization_name'         => null,
            'status'                    => 'active',
            'org_status'                => 'active',
            'org_name'                  => 'My Dash Org',
            'organization_type'         => 'partner',
            'org_id'                    => 10,
            'last_attribution_at'       => '2026-04-01 12:00:00',
            'created_at'                => '2026-01-15 10:00:00',
        ];

        $partner = array_merge($defaultPartner, $overrides);

        $getRowCalls = 0;
        $wpdb->test_get_row_callback = function ($query) use ($partner, &$getRowCalls) {
            $getRowCalls++;
            if ($getRowCalls === 1) {
                return (object) $partner;
            }
            // Second get_row: attribution stats query
            if ($getRowCalls === 2) {
                return (object)[
                    'total'    => 20,
                    'verified' => 15,
                    'pending'  => 4,
                ];
            }
            return null;
        };

        $getVarCalls = 0;
        $wpdb->test_get_var_callback = function ($query) use (&$getVarCalls) {
            $getVarCalls++;
            if ($getVarCalls === 1) {
                return 'wp_test_orabooks_customers'; // SHOW TABLES
            }
            if ($getVarCalls === 2) {
                return 3; // active_customer_count with customers table
            }
            // Subsequent get_var calls: fallback verified count not needed
            // earned commission status queries
            return null;
        };

        $getResultsCalls = 0;
        $wpdb->test_get_results_callback = function ($query) use (&$getResultsCalls) {
            $getResultsCalls++;
            // Attribution list — column names match the SQL alias (pa.status as attribution_status)
            return [
                (object)[
                    'id'                 => 1,
                    'customer_email'     => 'customer@example.com',
                    'attribution_date'   => '2026-03-15',
                    'attribution_status' => 'verified',
                ],
                (object)[
                    'id'                 => 2,
                    'customer_email'     => 'pending@example.com',
                    'attribution_date'   => '2026-05-01',
                    'attribution_status' => 'pending',
                ],
            ];
        };

        return $partner;
    }

    #[Test]
    public function test_get_dashboard_data_success()
    {
        $this->setupDashboardDataMocks();

        $result = OraBooks_Partner::get_dashboard_data(5);

        $this->assertIsArray($result);
        $this->assertEquals('PARTNER-DASH', $result['partner_code']);
        $this->assertEquals('individual', $result['partner_type']);
        $this->assertEquals('active', $result['code_status']);
        $this->assertEquals('active', $result['org_status']);
        $this->assertEquals('My Dash Org', $result['org_name']);
        $this->assertEquals(3, $result['active_customer_count']);
        $this->assertFalse($result['is_blocked']);
        $this->assertFalse($result['read_only']);
        $this->assertFalse($result['payout_disabled']);
        $this->assertFalse($result['is_inactive']);
        $this->assertFalse($result['new_attribution_blocked']);
        $this->assertNull($result['status_banner']);
        $this->assertFalse($result['can_reactivate']);
        $this->assertFalse($result['is_dormant']);

        // Attribution stats
        $this->assertEquals(20, $result['attribution_stats']->total);
        $this->assertEquals(15, $result['attribution_stats']->verified);
        $this->assertEquals(4, $result['attribution_stats']->pending);

        // Commission summary (from stub)
        $this->assertEquals(0, $result['commission_summary']['total_earned']);
        $this->assertEquals('USD', $result['commission_summary']['currency']);

        // Payout breakdown (empty from stub)
        $this->assertIsArray($result['payout_breakdown']);
        $this->assertCount(0, $result['payout_breakdown']);

        // Attributions
        $this->assertCount(2, $result['attributions']);
        $this->assertStringContainsString('***', $result['attributions'][0]['customer_email_masked']);
    }

    #[Test]
    public function test_get_dashboard_data_null_no_partner()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return null; // No partner code found
        };

        $result = OraBooks_Partner::get_dashboard_data(999);
        $this->assertNull($result);
    }

    #[Test]
    public function test_get_dashboard_data_fraud_freeze_blocked()
    {
        $this->setupDashboardDataMocks(['org_status' => 'fraud_freeze']);

        $result = OraBooks_Partner::get_dashboard_data(5);

        $this->assertTrue($result['is_blocked']);
        $this->assertFalse($result['read_only']);
        $this->assertFalse($result['payout_disabled']);
    }

    #[Test]
    public function test_get_dashboard_data_suspended_read_only()
    {
        $this->setupDashboardDataMocks(['org_status' => 'suspended']);

        $result = OraBooks_Partner::get_dashboard_data(5);

        $this->assertFalse($result['is_blocked']);
        $this->assertTrue($result['read_only']);
        $this->assertFalse($result['payout_disabled']);
    }

    #[Test]
    public function test_get_dashboard_data_payout_hold()
    {
        $this->setupDashboardDataMocks(['org_status' => 'payout_hold']);

        $result = OraBooks_Partner::get_dashboard_data(5);

        $this->assertFalse($result['is_blocked']);
        $this->assertFalse($result['read_only']);
        $this->assertTrue($result['payout_disabled']);
    }

    #[Test]
    public function test_get_dashboard_data_can_reactivate_when_inactive()
    {
        $this->setupDashboardDataMocks(['status' => 'inactive']);

        $result = OraBooks_Partner::get_dashboard_data(5);

        $this->assertTrue($result['can_reactivate']);
        $this->assertTrue($result['is_inactive']);
        $this->assertTrue($result['new_attribution_blocked']);
        $this->assertEquals('inactive', $result['status_banner']['type']);
    }

    #[Test]
    public function test_get_dashboard_data_is_dormant()
    {
        // Dormant: no active customers, last attribution 6-12 months ago
        $sixMonthsAgo = date('Y-m-d H:i:s', time() - (7 * 30 * 86400)); // ~7 months ago
        $this->setupDashboardDataMocks([
            'last_attribution_at' => $sixMonthsAgo,
        ]);

        // Override the active_customer_count to return 0
        global $wpdb;
        $getVarCalls = 0;
        $wpdb->test_get_var_callback = function ($query) use (&$getVarCalls) {
            $getVarCalls++;
            if ($getVarCalls === 1) {
                return 'wp_test_orabooks_customers'; // SHOW TABLES
            }
            if ($getVarCalls === 2) {
                return 0; // active_customer_count = 0 (triggers dormant check)
            }
            // Fallback verified attribution count = 0
            return 0;
        };

        $result = OraBooks_Partner::get_dashboard_data(5);

        $this->assertTrue($result['is_dormant']);
    }

    #[Test]
    public function test_get_dashboard_data_attributions_with_commission_status()
    {
        $this->setupDashboardDataMocks();

        $result = OraBooks_Partner::get_dashboard_data(5);

        $this->assertCount(2, $result['attributions']);
        // Commission status: first attribution uses customer_email query
        // Since get_var returns null (no earned record found), and then
        // get_var for escrow check also returns null, the commission_status
        // falls through to '—' (em dash). The get_var mock returns null
        // for the email-based queries after our active_customer_count overrides.
        // The attribution list has customer_email values that get masked.
        foreach ($result['attributions'] as $attr) {
            $this->assertArrayHasKey('customer_email_masked', $attr);
            $this->assertArrayHasKey('attribution_status', $attr);
            $this->assertArrayHasKey('commission_status', $attr);
            $this->assertArrayHasKey('attribution_date', $attr);
        }
    }

    // ================================================================
    // process_partner_activity()
    // ================================================================

    #[Test]
    public function test_process_partner_activity_deactivation_warning()
    {
        global $wpdb;

        // Partner with last_attribution_at ~11.5 months ago, 0 active customers
        // Set low_activity_reminder_sent_at recently so low-activity update doesn't override last_query
        $elevenMonthsAgo = date('Y-m-d H:i:s', time() - (345 * 86400)); // ~11.5 months
        $recently = date('Y-m-d H:i:s', time() - (86400)); // 1 day ago
        $wpdb->test_get_results_callback = function ($query) use ($elevenMonthsAgo, $recently) {
            return [
                (object)[
                    'id'                            => 1,
                    'user_id'                       => 5,
                    'last_attribution_at'           => $elevenMonthsAgo,
                    'deactivation_reminder_sent_at'  => null,
                    'low_activity_reminder_sent_at'  => $recently, // Recently sent — prevents repeat reminder
                ],
            ];
        };

        // get_var: SHOW TABLES, then COUNT queries (return 0 active customers)
        $getVarCalls = 0;
        $wpdb->test_get_var_callback = function ($query) use (&$getVarCalls) {
            $getVarCalls++;
            return 0; // No customers table, 0 active customers
        };

        OraBooks_Partner::process_partner_activity();

        // Should have updated deactivation_reminder_sent_at
        $this->assertStringContainsString('deactivation_reminder_sent_at', $wpdb->last_query);
    }

    #[Test]
    public function test_process_partner_activity_deactivation()
    {
        global $wpdb;

        // Partner with last_attribution_at 13 months ago, 0 active customers
        // Set low_activity_reminder_sent_at recently so low-activity update doesn't override last_query
        $thirteenMonthsAgo = date('Y-m-d H:i:s', time() - (395 * 86400));
        $recently = date('Y-m-d H:i:s', time() - (86400)); // 1 day ago
        $wpdb->test_get_results_callback = function ($query) use ($thirteenMonthsAgo, $recently) {
            return [
                (object)[
                    'id'                            => 1,
                    'user_id'                       => 5,
                    'last_attribution_at'           => $thirteenMonthsAgo,
                    'deactivation_reminder_sent_at'  => null,
                    'low_activity_reminder_sent_at'  => $recently, // Recently sent — prevents repeat reminder
                ],
            ];
        };

        $getVarCalls = 0;
        $wpdb->test_get_var_callback = function ($query) use (&$getVarCalls) {
            $getVarCalls++;
            return 0;
        };

        OraBooks_Partner::process_partner_activity();

        // Should have updated status to 'inactive'
        $this->assertStringContainsString('inactive', $wpdb->last_query);
    }

    #[Test]
    public function test_process_partner_activity_low_activity_reminder()
    {
        global $wpdb;

        // Partner with last_attribution_at 7 months ago (triggers low-activity),
        // no deactivation warning yet (0 active customers, but < 11 months)
        $sevenMonthsAgo = date('Y-m-d H:i:s', time() - (210 * 86400));
        $wpdb->test_get_results_callback = function ($query) use ($sevenMonthsAgo) {
            return [
                (object)[
                    'id'                            => 1,
                    'user_id'                       => 5,
                    'last_attribution_at'           => $sevenMonthsAgo,
                    'deactivation_reminder_sent_at'  => null,
                    'low_activity_reminder_sent_at'  => null,
                ],
            ];
        };

        $getVarCalls = 0;
        $wpdb->test_get_var_callback = function ($query) use (&$getVarCalls) {
            $getVarCalls++;
            return 0;
        };

        OraBooks_Partner::process_partner_activity();

        // Should have updated low_activity_reminder_sent_at
        $this->assertStringContainsString('low_activity_reminder_sent_at', $wpdb->last_query);
    }

    #[Test]
    public function test_process_partner_activity_active_partner_no_action()
    {
        global $wpdb;

        // Active partner with recent attribution
        $recent = date('Y-m-d H:i:s', time() - (86400 * 30)); // 30 days ago
        $wpdb->test_get_results_callback = function ($query) use ($recent) {
            return [
                (object)[
                    'id'                            => 1,
                    'user_id'                       => 5,
                    'last_attribution_at'           => $recent,
                    'deactivation_reminder_sent_at'  => null,
                    'low_activity_reminder_sent_at'  => null,
                ],
            ];
        };

        $getVarCalls = 0;
        $wpdb->test_get_var_callback = function ($query) use (&$getVarCalls) {
            $getVarCalls++;
            return 3; // 3 active customers — no deactivation
        };

        OraBooks_Partner::process_partner_activity();

        // No updates should have been made (last_query still the SELECT from get_results)
        // The get_results sets last_query to the SELECT, and no UPDATE should occur
        $this->assertStringContainsString('SELECT', $wpdb->last_query);
        $this->assertStringNotContainsString('UPDATE', $wpdb->last_query);
    }

    // ================================================================
    // process_partner_activity — reminder repeat
    // ================================================================

    #[Test]
    public function test_process_partner_activity_reminder_already_sent_no_repeat()
    {
        global $wpdb;

        // Partner with 7 months inactivity, but reminder already sent 2 months ago
        $sevenMonthsAgo = date('Y-m-d H:i:s', time() - (210 * 86400));
        $twoMonthsAgo = date('Y-m-d H:i:s', time() - (60 * 86400));
        $wpdb->test_get_results_callback = function ($query) use ($sevenMonthsAgo, $twoMonthsAgo) {
            return [
                (object)[
                    'id'                            => 1,
                    'user_id'                       => 5,
                    'last_attribution_at'           => $sevenMonthsAgo,
                    'deactivation_reminder_sent_at'  => null,
                    'low_activity_reminder_sent_at'  => $twoMonthsAgo, // Already sent
                ],
            ];
        };

        $getVarCalls = 0;
        $wpdb->test_get_var_callback = function ($query) use (&$getVarCalls) {
            $getVarCalls++;
            return 0;
        };

        OraBooks_Partner::process_partner_activity();

        // If reminder was sent 2 months ago (< 3 months), no new reminder
        $this->assertStringNotContainsString('low_activity_reminder_sent_at', $wpdb->last_query);
    }

    // ================================================================
    // ADMIN AJAX: ajax_admin_approve_partner()
    // ================================================================

    #[Test]
    public function test_ajax_admin_approve_partner_success()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return $this->mockPartnerCode([
                'status'     => 'pending_review',
                'org_status' => 'pending_setup',
            ]);
        };

        $response = $this->callAjax('ajax_admin_approve_partner', [
            'partner_code_id' => 1,
        ]);

        $this->assertFalse($response['error']);
        $this->assertStringContainsString('approved', $response['message']);
    }

    #[Test]
    public function test_ajax_admin_approve_partner_permission_denied()
    {
        $GLOBALS['orabooks_test_current_user_can'] = false;

        $response = $this->callAjax('ajax_admin_approve_partner', [
            'partner_code_id' => 1,
        ]);

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('Permission denied', $response['message']);
    }

    #[Test]
    public function test_ajax_admin_approve_partner_missing_id()
    {
        // No partner_code_id in POST
        $response = $this->callAjax('ajax_admin_approve_partner');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('Invalid partner code ID', $response['message']);
    }

    // ================================================================
    // ADMIN AJAX: ajax_admin_reject_partner()
    // ================================================================

    #[Test]
    public function test_ajax_admin_reject_partner_success()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return $this->mockPartnerCode([
                'status' => 'pending_review',
                'org_id' => 10,
            ]);
        };

        $response = $this->callAjax('ajax_admin_reject_partner', [
            'partner_code_id' => 1,
            'reason'          => 'Incomplete documentation',
        ]);

        $this->assertFalse($response['error']);
        $this->assertStringContainsString('rejected', $response['message']);
    }

    #[Test]
    public function test_ajax_admin_reject_partner_permission_denied()
    {
        $GLOBALS['orabooks_test_current_user_can'] = false;

        $response = $this->callAjax('ajax_admin_reject_partner', [
            'partner_code_id' => 1,
        ]);

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('Permission denied', $response['message']);
    }

    // ================================================================
    // ADMIN AJAX: ajax_admin_review_reactivation()
    // ================================================================

    #[Test]
    public function test_ajax_admin_review_reactivation_approve()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id'             => 5,
                'org_id'         => 10,
                'requested_by'   => 42,
                'reason'         => 'Need to reactivate',
            ];
        };

        $response = $this->callAjax('ajax_admin_review_reactivation', [
            'review_id' => 5,
            'decision'  => 'approved',
            'notes'     => 'Approved after review',
        ]);

        $this->assertFalse($response['error']);
        $this->assertStringContainsString('approved', $response['message']);
    }

    #[Test]
    public function test_ajax_admin_review_reactivation_deny()
    {
        $response = $this->callAjax('ajax_admin_review_reactivation', [
            'review_id' => 5,
            'decision'  => 'denied',
            'notes'     => 'Does not qualify',
        ]);

        $this->assertFalse($response['error']);
        $this->assertStringContainsString('denied', $response['message']);
    }

    #[Test]
    public function test_ajax_admin_review_reactivation_invalid_decision()
    {
        $response = $this->callAjax('ajax_admin_review_reactivation', [
            'review_id' => 5,
            'decision'  => 'invalid_decision',
        ]);

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('Invalid request', $response['message']);
    }

    // ================================================================
    // ADMIN AJAX: List handlers
    // ================================================================

    #[Test]
    public function test_ajax_admin_list_pending_partners_success()
    {
        global $wpdb;
        $wpdb->test_get_results_callback = function ($query) {
            return [
                (object)['id' => 1, 'partner_code' => 'P1', 'status' => 'pending_review', 'email' => 'p1@t.com'],
            ];
        };

        $response = $this->callAjax('ajax_admin_list_pending_partners');

        $this->assertFalse($response['error']);
        $this->assertCount(1, $response['data']);
        $this->assertEquals('P1', $response['data'][0]['partner_code']);
    }

    #[Test]
    public function test_ajax_admin_list_active_partners_success()
    {
        global $wpdb;
        $wpdb->test_get_results_callback = function ($query) {
            return [
                (object)['id' => 1, 'partner_code' => 'A1', 'status' => 'active', 'email' => 'a1@t.com', 'verified_attributions' => 5],
            ];
        };

        $response = $this->callAjax('ajax_admin_list_active_partners');

        $this->assertFalse($response['error']);
        $this->assertCount(1, $response['data']);
        $this->assertEquals('A1', $response['data'][0]['partner_code']);
    }

    #[Test]
    public function test_ajax_admin_list_reactivation_requests_success()
    {
        global $wpdb;
        $wpdb->test_get_results_callback = function ($query) {
            return [
                (object)[
                    'id'                 => 1,
                    'org_id'             => 10,
                    'requested_by'       => 5,
                    'requested_by_email' => 'owner@t.com',
                    'org_name'           => 'React Corp',
                    'subdomain'          => 'react',
                    'partner_code'       => 'R1',
                    'partner_type'       => 'agency',
                    'code_status'        => 'pending_review',
                    'reason'             => 'Need to restart',
                    'requested_at'       => '2026-06-01 10:00:00',
                    'decision'           => null,
                ],
            ];
        };

        $response = $this->callAjax('ajax_admin_list_reactivation_requests');

        $this->assertFalse($response['error']);
        $this->assertCount(1, $response['data']);
        $this->assertEquals('R1', $response['data'][0]['partner_code']);
    }

    #[Test]
    public function test_ajax_partner_onboarding_success()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return (object) [
                'partner_code'      => 'PARTNER-ONBOARD',
                'code_status'       => 'active',
                'partner_type'      => 'individual',
                'organization_name' => null,
                'org_status'        => 'active',
                'org_name'          => 'Partner Org',
                'created_at'        => '2026-06-01 10:00:00',
            ];
        };

        $response = $this->callAjax('ajax_partner_onboarding');

        $this->assertFalse($response['error']);
        $this->assertEquals('PARTNER-ONBOARD', $response['data']['partner_code']);
        $this->assertEquals('active', $response['data']['code_status']);
        $this->assertFalse($response['data']['bank_info_required']);
    }

    #[Test]
    public function test_future_partner_endpoints_return_501()
    {
        $paymentResponse = $this->callAjax('ajax_payment_settings');
        $applicationResponse = $this->callAjax('ajax_partner_application');

        $this->assertTrue($paymentResponse['error']);
        $this->assertStringContainsString('not implemented in MVP', $paymentResponse['message']);
        $this->assertStringContainsString('SL-140', $paymentResponse['message']);
        $this->assertTrue($applicationResponse['error']);
        $this->assertStringContainsString('not implemented in MVP', $applicationResponse['message']);
        $this->assertStringContainsString('SL-140', $applicationResponse['message']);
    }

    // ================================================================
    // get_dashboard_data — commission summary from OraBooks_Commission stub
    // ================================================================

    #[Test]
    public function test_get_dashboard_data_commission_summary_from_stub()
    {
        $this->setupDashboardDataMocks();

        $result = OraBooks_Partner::get_dashboard_data(5);

        // Commission data comes from the OraBooks_Commission stub
        $this->assertIsArray($result['commission_summary']);
        $this->assertArrayHasKey('total_earned', $result['commission_summary']);
        $this->assertArrayHasKey('pending_payout', $result['commission_summary']);
        $this->assertArrayHasKey('paid', $result['commission_summary']);
        $this->assertArrayHasKey('expired', $result['commission_summary']);
        $this->assertArrayHasKey('currency', $result['commission_summary']);
    }
}
