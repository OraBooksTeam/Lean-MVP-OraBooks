<?php
/**
 * Partner Onboarding E2E Integration Test (SL-013 / SL-139)
 *
 * Tests the full partner onboarding flow from registration through
 * to dashboard access, verifying all intermediate states:
 *
 * 1. Register partner user with partner_type, accept_terms, org name
 * 2. Verify email
 * 3. First login — triggers auto-org creation + partner code generation
 * 4. Onboarding info endpoint returns code, type, org name, status message
 * 5. Copy code audit event
 * 6. Onboarding complete marks done and redirects to /partner-program/
 * 7. Post-onboarding redirect resolves to dashboard, not onboarding
 *
 * Run: phpunit --configuration tests/phpunit.xml --testsuite="OraBooks Partner Tests"
 *       --filter="test_partner_onboarding_e2e_full_flow"
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Partner_Onboarding_E2E_Test extends TestCase
{
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
        $GLOBALS['orabooks_test_log_events'] = [];

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
     * Invoke an AJAX handler method and return the decoded JSON response.
     */
    private function callAjax(string $method, array $post = []): array
    {
        $_POST = array_merge($_POST, $post);
        $handler = new OraBooks_Partner();
        try {
            $handler->$method();
            $this->fail("Expected RuntimeException (JSON response) was not thrown by {$method}");
        } catch (RuntimeException $e) {
            return json_decode($e->getMessage(), true);
        }
    }

    /**
     * Invoke OraBooks_Auth AJAX handler and return the decoded JSON response.
     */
    private function callAuthAjax(string $method, array $post = []): array
    {
        $_POST = array_merge($_POST, $post);
        $auth = new OraBooks_Auth();
        try {
            $auth->$method();
            $this->fail("Expected RuntimeException (JSON response) was not thrown by {$method}");
        } catch (RuntimeException $e) {
            return json_decode($e->getMessage(), true);
        }
    }

    // ================================================================
    // E2E: Full partner onboarding flow
    // ================================================================

    #[Test]
    public function test_partner_onboarding_e2e_full_flow(): void
    {
        // ----------------------------------------------------------------
        // STEP 1: Register partner user (agency with organization name)
        // ----------------------------------------------------------------
        global $wpdb;

        // Mock no existing email (get_var returns 0)
        $wpdb->test_get_var_callback = function ($query) {
            return 0;
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 42;

        $registerData = [
            'email'             => 'agency@example.com',
            'password'          => 'SecurePass1!',
            'user_type'         => 'partner',
            'accept_terms'      => true,
            'terms_version'     => '2.0',
            'partner_type'      => 'agency',
            'organization_name' => 'My Agency LLC',
        ];

        $registerResult = OraBooks_Auth::register($registerData);

        $this->assertIsArray($registerResult, 'Step 1: Registration should succeed');
        $this->assertEquals(42, $registerResult['user_id']);
        $this->assertEquals(1, $registerResult['is_partner']);
        $this->assertTrue($registerResult['requires_email_verification']);
        $this->assertStringContainsString('Verification', $registerResult['message']);

        // Generate a verification token and store it in user_meta (simulating
        // what the register() method does internally before sending the email).
        $wp_user_id = orabooks_get_wp_user_id_for_orabooks_user(42);
        $verificationToken = 'test-verification-token-e2e-' . time();
        update_user_meta($wp_user_id, 'orabooks_verification_token', orabooks_hash_token($verificationToken));

        // ----------------------------------------------------------------
        // STEP 2: Verify email
        // ----------------------------------------------------------------
        // Mock: find user by verification token
        $getRowCalls = 0;
        $wpdb->test_get_row_callback = function ($query) use (&$getRowCalls) {
            $getRowCalls++;
            if ($getRowCalls === 1) {
                // First get_row: find user by verification token
                return (object)[
                    'id'                => 42,
                    'email'             => 'agency@example.com',
                    'org_id'            => null,
                    'is_email_verified' => 0,
                    'pending_partner_type'          => 'agency',
                    'pending_organization_name'      => 'My Agency LLC',
                ];
            }
            // Subsequent get_row calls: no pending attribution
            return null;
        };

        // get_var for pending attribution check — returns 0 (none)
        $getVarCalls = 0;
        $wpdb->test_get_var_callback = function ($query) use (&$getVarCalls) {
            $getVarCalls++;
            return 0;
        };

        // get_results for attribution list — empty
        $wpdb->test_get_results_callback = function ($query) {
            return [];
        };

        // Store the verification token via update_user_meta
        // Verification token is already stored from Step 1.

        $verifyResult = OraBooks_Auth::verify_email($verificationToken);

        $this->assertTrue($verifyResult, 'Step 2: Email verification should succeed');

        // ----------------------------------------------------------------
        // STEP 3: First login — should auto-create org + generate partner code
        // ----------------------------------------------------------------
        // Now the user exists with is_partner=1, org_id=null.
        // login() should detect first login and call handle_partner_first_login().
        //
        // We need to mock:
        //   - get_row: first call returns the user
        //   - OraBooks_Organization::create is stubbed in bootstrap
        //   - wpdb->insert for generate_partner_code
        //   - wpdb->update for clearing pending fields
        //   - store_refresh_token uses wpdb->insert

        $loginGetRowCalls = 0;
        $wpdb->test_get_row_callback = function ($query) use (&$loginGetRowCalls) {
            $loginGetRowCalls++;

            if ($loginGetRowCalls === 1) {
                // First call: authenticate user by email
                return (object)[
                    'id'                => 42,
                    'email'             => 'agency@example.com',
                    'password_hash'     => password_hash('SecurePass1!', PASSWORD_DEFAULT),
                    'is_email_verified' => 1,
                    'is_active'         => 1,
                    'is_2fa_enabled'    => 0,
                    'org_id'            => null,
                    'is_partner'        => 1,
                    'auth_provider'     => 'local',
                    'pending_partner_type'          => 'agency',
                    'pending_organization_name'      => 'My Agency LLC',
                ];
            }

            // Subsequent calls: return null (no org yet from OraBooks_Organization::get)
            return null;
        };

        // get_var for org membership, user queries, etc.
        $loginGetVarCalls = 0;
        $wpdb->test_get_var_callback = function ($query) use (&$loginGetVarCalls) {
            $loginGetVarCalls++;
            // For refresh token generation etc.
            return null;
        };

        // Control the insert_id for the partner code insert
        $GLOBALS['orabooks_test_use_insert_id'] = 101;

        $loginResult = OraBooks_Auth::login('agency@example.com', 'SecurePass1!');

        $this->assertNotInstanceOf(WP_Error::class, $loginResult, 'Step 3: Login should succeed');
        $this->assertIsArray($loginResult);

        // Verify login response shape for partner first login
        $this->assertArrayHasKey('token', $loginResult);
        $this->assertArrayHasKey('refresh_token', $loginResult);
        $this->assertEquals(42, $loginResult['user_id']);
        $this->assertArrayHasKey('org_id', $loginResult);
        $this->assertNotNull($loginResult['org_id'], 'Step 3: Org should have been created');
        $this->assertGreaterThan(0, $loginResult['org_id'], 'Step 3: Org ID should be positive');
        $this->assertEquals('owner', $loginResult['role']);
        $this->assertEquals(1, $loginResult['is_partner']);
        $this->assertEquals('/partner/onboarding/', $loginResult['redirect_to'],
            'Step 3: Should redirect to onboarding page');

        // Verify onboarding events were logged
        $eventTypes = array_column($GLOBALS['orabooks_test_log_events'] ?? [], 'event_type');
        $this->assertContains('partner_org_created', $eventTypes,
            'Step 3: partner_org_created audit event should be logged');
        $this->assertContains('partner_onboarding_started', $eventTypes,
            'Step 3: partner_onboarding_started audit event should be logged');

        $orgId = $loginResult['org_id'];

        // ----------------------------------------------------------------
        // STEP 4: Onboarding info — fetch after first login
        // ----------------------------------------------------------------
        // Set current user to the partner user for AJAX handlers
        $GLOBALS['orabooks_test_current_user_id'] = 42;
        $GLOBALS['orabooks_test_org_callback'] = function ($id) use ($orgId) {
            return (object)[
                'id'                => $orgId,
                'owner_id'          => 42,
                'organization_type' => 'partner',
                'tier'              => 'partner',
                'subdomain'         => 'partner-42',
                'status'            => 'pending_setup',
                'name'              => 'My Agency LLC',
            ];
        };

        // Mock get_row for ajax_partner_onboarding:
        //   - First call: assert_partner_org_member (users + orgs join)
        //   - Second call: get_onboarding_info (partner_codes + orgs join)
        $onboardingGetRowCalls = 0;
        $wpdb->test_get_row_callback = function ($query) use (&$onboardingGetRowCalls, $orgId) {
            $onboardingGetRowCalls++;

            if ($onboardingGetRowCalls === 1) {
                // assert_partner_org_member: user + org join
                return (object)[
                    'id'                  => 42,
                    'org_id'              => $orgId,
                    'is_partner'          => 1,
                    'organization_type'   => 'partner',
                ];
            }

            if ($onboardingGetRowCalls === 2) {
                // get_onboarding_info: partner_codes + orgs join
                return (object)[
                    'partner_code'      => 'PARTNER-E2ETEST',
                    'code_status'       => 'pending_review',
                    'partner_type'      => 'agency',
                    'organization_name' => 'My Agency LLC',
                    'org_status'        => 'pending_setup',
                    'org_name'          => 'My Agency LLC',
                    'created_at'        => '2026-06-30 10:00:00',
                    'organization_type' => 'partner',
                ];
            }

            return (object)[
                'id'                  => 42,
                'org_id'              => $orgId,
                'is_partner'          => 1,
                'organization_type'   => 'partner',
            ];
        };

        // get_var for assert_partner_org_member (user_org / owner_id check)
        $wpdb->test_get_var_callback = function ($query) {
            return 1;
        };

        $onboardingResponse = $this->callAjax('ajax_partner_onboarding');

        $this->assertFalse($onboardingResponse['error'], 'Step 4: Onboarding API should not error');
        $this->assertArrayHasKey('data', $onboardingResponse);
        $data = $onboardingResponse['data'];

        $this->assertEquals('PARTNER-E2ETEST', $data['partner_code'], 'Step 4: Should show partner code');
        $this->assertEquals('pending_review', $data['code_status'], 'Step 4: Code status should be pending_review');
        $this->assertEquals('agency', $data['partner_type'], 'Step 4: Should show partner type');
        $this->assertEquals('Agency', $data['partner_type_label'], 'Step 4: Should show formatted type label');
        $this->assertEquals('My Agency LLC', $data['organization_name'], 'Step 4: Should show org name');
        $this->assertEquals('pending_setup', $data['org_status'], 'Step 4: Org status should be pending_setup');
        $this->assertFalse($data['bank_info_required'], 'Step 4: Bank info should not be required in MVP');
        $this->assertStringContainsString('⏳', $data['status_message'],
            'Step 4: Status message should show pending approval');
        $this->assertStringContainsString('Awaiting admin approval', $data['status_message'],
            'Step 4: Status message should say awaiting approval');

        // Verify the onboarding viewed/started events were audited
        $onboardingEvents = array_column(
            array_filter($GLOBALS['orabooks_test_log_events'] ?? [], function ($e) {
                return in_array($e['event_type'], ['partner_onboarding_viewed', 'partner_onboarding_started']);
            }),
            'event_type'
        );
        $this->assertContains('partner_onboarding_viewed', $onboardingEvents,
            'Step 4: partner_onboarding_viewed should be logged');
        $this->assertContains('partner_onboarding_started', $onboardingEvents,
            'Step 4: partner_onboarding_started should be logged');

        // ----------------------------------------------------------------
        // STEP 5: Copy partner code (audit event)
        // ----------------------------------------------------------------
        $_POST['source'] = 'onboarding';
        $codeCopiedResponse = $this->callAjax('ajax_code_copied');

        $this->assertFalse($codeCopiedResponse['error'], 'Step 5: Code copy should succeed');
        $this->assertEquals('copied', $codeCopiedResponse['message']);

        $copyEvents = array_filter($GLOBALS['orabooks_test_log_events'] ?? [], function ($e) {
            return $e['event_type'] === 'partner_code_copied';
        });
        $this->assertNotEmpty($copyEvents, 'Step 5: partner_code_copied audit event should be logged');
        $copyEvent = reset($copyEvents);
        $this->assertEquals('onboarding', $copyEvent['metadata']['source'],
            'Step 5: Should record copy source as "onboarding"');

        // ----------------------------------------------------------------
        // STEP 6: Complete onboarding
        // ----------------------------------------------------------------
        // Still need assert_partner_org_member mock for the AJAX handler
        $completeGetRowCalls = 0;
        $wpdb->test_get_row_callback = function ($query) use (&$completeGetRowCalls, $orgId) {
            $completeGetRowCalls++;
            return (object)[
                'id'                  => 42,
                'org_id'              => $orgId,
                'is_partner'          => 1,
                'organization_type'   => 'partner',
            ];
        };

        $completeResponse = $this->callAjax('ajax_partner_onboarding_complete');

        $this->assertFalse($completeResponse['error'], 'Step 6: Onboarding complete should not error');
        $this->assertArrayHasKey('data', $completeResponse);
        $this->assertTrue($completeResponse['data']['onboarding_completed'], 'Step 6: Should mark as completed');
        $this->assertEquals('/partner-program/', $completeResponse['data']['redirect_to'],
            'Step 6: Should redirect to partner program dashboard');

        // Verify user meta was set
        $this->assertTrue(orabooks_has_completed_partner_onboarding(42),
            'Step 6: Partner onboarding should be marked as completed');

        // Verify completion audit event
        $completeEvents = array_filter($GLOBALS['orabooks_test_log_events'] ?? [], function ($e) {
            return $e['event_type'] === 'partner_onboarding_completed';
        });
        $this->assertNotEmpty($completeEvents, 'Step 6: partner_onboarding_completed audit event should be logged');

        // ----------------------------------------------------------------
        // STEP 7: Post-onboarding redirect resolves correctly
        // ----------------------------------------------------------------
        $postLoginPath = orabooks_get_partner_post_login_path(42);
        $this->assertEquals('/partner-program/', $postLoginPath,
            'Step 7: After onboarding, redirect should be to partner program dashboard');

        // Before onboarding (simulate not completed yet), it should redirect to onboarding
        // Clear the meta and verify
        delete_user_meta(
            orabooks_get_wp_user_id_for_orabooks_user(42),
            'orabooks_partner_onboarding_completed'
        );
        $preCompletePath = orabooks_get_partner_post_login_path(42);
        $this->assertEquals('/partner/onboarding/', $preCompletePath,
            'Step 7: Before onboarding completes, should redirect to onboarding');

        // Restore the meta
        update_user_meta(
            orabooks_get_wp_user_id_for_orabooks_user(42),
            'orabooks_partner_onboarding_completed',
            '1'
        );

        // ----------------------------------------------------------------
        // STEP 8: Dashboard accessible after onboarding
        // ----------------------------------------------------------------
        // Mock get_dashboard_data queries for the partner
        $this->setupDashboardDataMocks([
            'status'     => 'pending_review',
            'org_status' => 'pending_setup',
        ], $orgId);

        $dashboardResponse = $this->callAjax('ajax_partner_dashboard');

        $this->assertFalse($dashboardResponse['error'], 'Step 8: Dashboard API should succeed');
        $this->assertArrayHasKey('data', $dashboardResponse);
        $dashData = $dashboardResponse['data'];

        // Dashboard banner is null for pending states (no matching condition in
        // get_dashboard_status_banner(); pending partners see the message on the
        // onboarding page instead)
        $this->assertArrayHasKey('status_banner', $dashData,
            'Step 8: Dashboard should include status_banner key');
        $this->assertNull($dashData['status_banner'],
            'Step 8: No dashboard banner for pending_setup/pending_review');

        $this->assertArrayHasKey('code_status', $dashData);
        $this->assertEquals('pending_review', $dashData['code_status']);
        $this->assertArrayHasKey('org_status', $dashData);
        $this->assertEquals('pending_setup', $dashData['org_status']);

        // Verify dashboard audit event
        $dashEvents = array_filter($GLOBALS['orabooks_test_log_events'] ?? [], function ($e) {
            return $e['event_type'] === 'partner_dashboard_viewed';
        });
        $this->assertNotEmpty($dashEvents, 'Step 8: partner_dashboard_viewed audit event should be logged');
    }

    // ================================================================
    // E2E: Partner onboarding for individual partner (no org name)
    // ================================================================

    #[Test]
    public function test_partner_onboarding_e2e_individual(): void
    {
        global $wpdb;

        // --- Register individual partner ---
        $wpdb->test_get_var_callback = function ($query) {
            return 0;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 55;

        $registerResult = OraBooks_Auth::register([
            'email'             => 'individual@example.com',
            'password'          => 'SecurePass1!',
            'user_type'         => 'partner',
            'accept_terms'      => true,
            'terms_version'     => '1.0',
            'partner_type'      => 'individual',
            'organization_name' => '', // Individual has no org name
        ]);

        $this->assertIsArray($registerResult);
        $this->assertEquals(55, $registerResult['user_id']);
        $this->assertEquals(1, $registerResult['is_partner']);

        // --- Verify email ---
        $getRowCalls = 0;
        $wpdb->test_get_row_callback = function ($query) use (&$getRowCalls) {
            $getRowCalls++;
            if ($getRowCalls === 1) {
                return (object)[
                    'id'                => 55,
                    'email'             => 'individual@example.com',
                    'org_id'            => null,
                    'is_email_verified' => 0,
                    'pending_partner_type'          => 'individual',
                    'pending_organization_name'      => '',
                ];
            }
            return null;
        };
        $wpdb->test_get_var_callback = function ($query) { return 0; };
        $wpdb->test_get_results_callback = function ($query) { return []; };

        // Store verification token for individual partner
        $wp_user_id = orabooks_get_wp_user_id_for_orabooks_user(55);
        $verificationToken = 'test-verification-indiv-' . time();
        update_user_meta($wp_user_id, 'orabooks_verification_token', orabooks_hash_token($verificationToken));

        $this->assertTrue(OraBooks_Auth::verify_email($verificationToken));

        // --- First login (no org yet) ---
        $loginGetRowCalls = 0;
        $wpdb->test_get_row_callback = function ($query) use (&$loginGetRowCalls) {
            $loginGetRowCalls++;
            if ($loginGetRowCalls === 1) {
                return (object)[
                    'id'                => 55,
                    'email'             => 'individual@example.com',
                    'password_hash'     => password_hash('SecurePass1!', PASSWORD_DEFAULT),
                    'is_email_verified' => 1,
                    'is_active'         => 1,
                    'is_2fa_enabled'    => 0,
                    'org_id'            => null,
                    'is_partner'        => 1,
                    'auth_provider'     => 'local',
                    'pending_partner_type'          => 'individual',
                    'pending_organization_name'      => '',
                ];
            }
            return null;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 201;

        $loginResult = OraBooks_Auth::login('individual@example.com', 'SecurePass1!');

        $this->assertNotInstanceOf(WP_Error::class, $loginResult);
        $this->assertEquals(55, $loginResult['user_id']);
        $this->assertEquals(1, $loginResult['is_partner']);
        $this->assertEquals('/partner/onboarding/', $loginResult['redirect_to']);

        $orgId = $loginResult['org_id'];

        // --- Onboarding info for individual ---
        $GLOBALS['orabooks_test_current_user_id'] = 55;
        $GLOBALS['orabooks_test_org_callback'] = function ($id) use ($orgId) {
            return (object)[
                'id'                => $orgId,
                'owner_id'          => 55,
                'organization_type' => 'partner',
                'tier'              => 'partner',
                'subdomain'         => 'partner-55',
                'status'            => 'pending_setup',
                'name'              => 'Partner 55',
            ];
        };

        $onboardingGetRowCalls = 0;
        $wpdb->test_get_row_callback = function ($query) use (&$onboardingGetRowCalls, $orgId) {
            $onboardingGetRowCalls++;
            if ($onboardingGetRowCalls === 1) {
                return (object)[
                    'id'                  => 55,
                    'org_id'              => $orgId,
                    'is_partner'          => 1,
                    'organization_type'   => 'partner',
                ];
            }
            if ($onboardingGetRowCalls === 2) {
                return (object)[
                    'partner_code'      => 'PARTNER-INDIV',
                    'code_status'       => 'pending_review',
                    'partner_type'      => 'individual',
                    'organization_name' => null,
                    'org_status'        => 'pending_setup',
                    'org_name'          => 'Partner 55',
                    'created_at'        => '2026-06-30 10:00:00',
                    'organization_type' => 'partner',
                ];
            }
            return (object)[
                'id'                  => 55,
                'org_id'              => $orgId,
                'is_partner'          => 1,
                'organization_type'   => 'partner',
            ];
        };
        $wpdb->test_get_var_callback = function ($query) { return 1; };

        $onboardingResponse = $this->callAjax('ajax_partner_onboarding');
        $data = $onboardingResponse['data'];

        $this->assertFalse($onboardingResponse['error']);
        $this->assertEquals('PARTNER-INDIV', $data['partner_code']);
        $this->assertEquals('individual', $data['partner_type']);
        $this->assertEquals('Individual', $data['partner_type_label']);
        $this->assertNull($data['organization_name'],
            'Individual partners should have null organization_name');
    }

    // ================================================================
    // E2E: Non-partner rejected from onboarding
    // ================================================================

    #[Test]
    public function test_partner_onboarding_e2e_customer_rejected(): void
    {
        global $wpdb;

        $GLOBALS['orabooks_test_current_user_id'] = 10;

        // Mock as a customer user (is_partner=0)
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id'                  => 10,
                'org_id'              => 30,
                'is_partner'          => 0,
                'organization_type'   => 'customer',
            ];
        };

        $response = $this->callAjax('ajax_partner_onboarding');

        $this->assertTrue($response['error'], 'Customer should be denied access to onboarding API');
        $this->assertStringContainsString('Partner organization membership', $response['message']);
    }

    // ================================================================
    // E2E: Non-authenticated rejected from all endpoints
    // ================================================================

    #[Test]
    public function test_partner_onboarding_e2e_unauthenticated_rejected(): void
    {
        $GLOBALS['orabooks_test_current_user_id'] = 0;

        // All partner endpoints should reject unauthenticated users
        foreach (['ajax_partner_onboarding', 'ajax_partner_onboarding_complete', 'ajax_code_copied', 'ajax_partner_dashboard'] as $method) {
            $partner = new OraBooks_Partner();
            try {
                $partner->$method();
                $this->fail("Expected RuntimeException for {$method}");
            } catch (RuntimeException $e) {
                $response = json_decode($e->getMessage(), true);
                $this->assertTrue($response['error'], "{$method} should error for unauthenticated");
                $this->assertStringContainsString('Not authenticated', $response['message'],
                    "{$method} should say 'Not authenticated'");
            }
        }
    }

    // ================================================================
    // Helper: Setup dashboard data mocks (adapted from OraBooks_Partner_Test)
    // ================================================================

    private function setupDashboardDataMocks(array $overrides = [], ?int $expectedOrgId = null): void
    {
        global $wpdb;

        $defaultPartner = [
            'partner_code'              => 'PARTNER-E2ETEST',
            'partner_type'              => 'agency',
            'organization_name'         => 'My Agency LLC',
            'status'                    => 'active',
            'org_status'                => 'active',
            'org_name'                  => 'My Agency LLC',
            'organization_type'         => 'partner',
            'org_id'                    => $expectedOrgId ?? 100,
            'last_attribution_at'       => '2026-04-01 12:00:00',
            'created_at'                => '2026-06-30 10:00:00',
        ];

        $partner = array_merge($defaultPartner, $overrides);

        $getRowCalls = 0;
        $wpdb->test_get_row_callback = function ($query) use ($partner, &$getRowCalls) {
            $getRowCalls++;
            if ($getRowCalls === 1) {
                return (object) $partner;
            }
            if ($getRowCalls === 2) {
                return (object)[
                    'total'    => 0,
                    'verified' => 0,
                    'pending'  => 0,
                ];
            }
            return null;
        };

        $orgId = $partner['org_id'];
        $wpdb->test_get_var_callback = function ($query) use ($orgId) {
            $q = strtoupper(trim((string) $query));

            // ajax_partner_dashboard() RBAC check: SELECT org_id FROM users WHERE id = ?
            if (strpos($q, 'SELECT ORG_ID') !== false && strpos($q, 'ORABOOKS_USERS') !== false) {
                return $orgId;
            }

            // get_dashboard_data(): SHOW TABLES check
            if (strpos($q, 'SHOW TABLES') !== false) {
                return 'wp_test_orabooks_customers';
            }

            // get_dashboard_data(): active_customer_count (customers table exists path)
            if (strpos($q, 'ORABOOKS_CUSTOMERS') !== false && strpos($q, 'IS_ACTIVE') !== false) {
                return 0;
            }

            // Fallback verified count (if customers table doesn't exist)
            if (strpos($q, 'ORABOOKS_PARTNER_ATTRIBUTIONS') !== false && strpos($q, 'VERIFIED') !== false) {
                return 0;
            }

            // Commission/earned queries — return null
            return null;
        };

        $wpdb->test_get_results_callback = function ($query) {
            return [];
        };
    }
}
