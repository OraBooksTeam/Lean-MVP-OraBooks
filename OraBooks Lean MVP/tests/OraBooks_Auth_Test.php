<?php
/**
 * Unit Tests for OraBooks_Auth SL-013 Methods
 *
 * Covers: Google OIDC flow (initiate + callback), require_customer_org middleware,
 * subdomain mismatch check in login(), 501 placeholder endpoints.
 *
 * Run: phpunit --configuration tests/phpunit.xml --testsuite="OraBooks SL-013 Auth Tests"
 */

use PHPUnit\Framework\TestCase;

class OraBooks_Auth_Test extends TestCase
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
        $GLOBALS['orabooks_test_secrets'] = [];
        $GLOBALS['orabooks_test_last_jwt_payload'] = null;
        $GLOBALS['orabooks_test_transients'] = [];
        $GLOBALS['orabooks_test_wp_remote_post_callback'] = null;
        $GLOBALS['orabooks_test_org_callback'] = null;
        $GLOBALS['orabooks_test_get_user_role_callback'] = null;
        $GLOBALS['orabooks_test_user_meta'] = [];
        $GLOBALS['orabooks_test_use_insert_id'] = null;

        // Reset superglobals
        $_POST = [];
        $_GET  = [];

        // Reset wpdb callbacks
        global $wpdb;
        $wpdb->test_get_var_callback    = null;
        $wpdb->test_get_row_callback    = null;
        $wpdb->test_get_results_callback = null;
        $wpdb->insert_id = 0;
        $wpdb->last_query = '';

        // Default Secrets config: OIDC enabled with test credentials
        $GLOBALS['orabooks_test_secrets'] = [
            'google_oauth_client_id'     => 'test-client-id-123.apps.googleusercontent.com',
            'google_oauth_client_secret' => 'test-client-secret',
        ];
        $GLOBALS['orabooks_test_jwt_token'] = 'test-jwt-token-' . time();
    }

    // ================================================================
    // Helper: capture JSON response from AJAX handlers
    // ================================================================

    /**
     * Invoke an AJAX handler and return the decoded JSON response.
     */
    private function callAjax(string $method): array
    {
        $auth = new OraBooks_Auth();
        try {
            $auth->$method();
            $this->fail("Expected RuntimeException (JSON response) was not thrown by {$method}");
        } catch (RuntimeException $e) {
            return json_decode($e->getMessage(), true);
        }
    }

    /**
     * Simulate a valid OIDC state being stored so handle_google_callback
     * passes the CSRF state validation.
     */
    private function storeValidOidcState(string $state): void
    {
        $state_hash = hash('sha256', $state);
        set_transient('orabooks_oidc_state_' . $state_hash, 1, 600);
    }

    // ================================================================
    // initiate_google_oauth()
    // ================================================================

    /** @test */
    public function test_initiate_google_oauth_returns_auth_url()
    {
        $url = OraBooks_Auth::initiate_google_oauth();

        $this->assertIsString($url);
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);

        $parts = parse_url($url);
        parse_str($parts['query'], $params);

        $this->assertEquals('test-client-id-123.apps.googleusercontent.com', $params['client_id']);
        $this->assertEquals('http://example.com/login', $params['redirect_uri']);
        $this->assertEquals('code', $params['response_type']);
        $this->assertEquals('openid email profile', $params['scope']);
        $this->assertArrayHasKey('state', $params);
        $this->assertNotEmpty($params['state']);
        $this->assertEquals('online', $params['access_type']);
        $this->assertEquals('select_account', $params['prompt']);
    }

    /** @test */
    public function test_initiate_google_oauth_returns_wp_error_when_not_configured()
    {
        $GLOBALS['orabooks_test_secrets'] = [];

        $result = OraBooks_Auth::initiate_google_oauth();

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('oauth_not_configured', $result->get_error_code());
    }

    /** @test */
    public function test_initiate_google_oauth_stores_state_transient()
    {
        $url = OraBooks_Auth::initiate_google_oauth();

        // Verify a state param was generated
        $parts = parse_url($url);
        parse_str($parts['query'], $params);
        $this->assertNotEmpty($params['state']);

        // Verify the state hash was stored as a transient
        $state_hash = hash('sha256', $params['state']);
        $stored = get_transient('orabooks_oidc_state_' . $state_hash);
        $this->assertEquals(1, $stored);
    }

    // ================================================================
    // handle_google_callback()
    // ================================================================

    /** @test */
    public function test_handle_google_callback_invalid_state()
    {
        // No state stored — get_transient returns false
        $result = OraBooks_Auth::handle_google_callback('auth-code-123', 'invalid-state');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_state', $result->get_error_code());
    }

    /** @test */
    public function test_handle_google_callback_oauth_not_configured()
    {
        $GLOBALS['orabooks_test_secrets'] = [];
        $this->storeValidOidcState('test-state-123');

        $result = OraBooks_Auth::handle_google_callback('auth-code-123', 'test-state-123');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('oauth_not_configured', $result->get_error_code());
    }

    /** @test */
    public function test_handle_google_callback_token_exchange_failure()
    {
        $this->storeValidOidcState('test-state-456');

        // Make wp_remote_post return a WP_Error
        $GLOBALS['orabooks_test_wp_remote_post_callback'] = function ($url, $args) {
            return new WP_Error('http_error', 'Connection timed out');
        };

        $result = OraBooks_Auth::handle_google_callback('auth-code-456', 'test-state-456');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('token_exchange_failed', $result->get_error_code());
    }

    /** @test */
    public function test_handle_google_callback_missing_id_token()
    {
        $this->storeValidOidcState('test-state-789');

        $GLOBALS['orabooks_test_wp_remote_post_callback'] = function ($url, $args) {
            return [
                'body' => json_encode([
                    'access_token' => 'ya29.mock',
                    'expires_in' => 3600,
                    // No id_token
                ]),
                'response' => ['code' => 200],
            ];
        };

        $result = OraBooks_Auth::handle_google_callback('auth-code-789', 'test-state-789');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('no_id_token', $result->get_error_code());
    }

    /** @test */
    public function test_handle_google_callback_invalid_id_token_format()
    {
        $this->storeValidOidcState('test-state-001');

        $GLOBALS['orabooks_test_wp_remote_post_callback'] = function ($url, $args) {
            return [
                'body' => json_encode([
                    'id_token' => 'not.a.valid.jwt.format.with.too.many.parts.so.it.will.error',
                    'access_token' => 'ya29.mock',
                ]),
                'response' => ['code' => 200],
            ];
        };

        $result = OraBooks_Auth::handle_google_callback('auth-code-001', 'test-state-001');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_id_token', $result->get_error_code());
    }

    /** @test */
    public function test_handle_google_callback_no_email_in_token()
    {
        $this->storeValidOidcState('test-state-002');

        $GLOBALS['orabooks_test_wp_remote_post_callback'] = function ($url, $args) {
            $payload = base64_encode(json_encode([
                'sub' => 'google-002',
                // No email field
                'name' => 'No Email User',
                'aud' => $GLOBALS['orabooks_test_secrets']['google_oauth_client_id'],
            ]));
            return [
                'body' => json_encode([
                    'id_token' => 'header.' . $payload . '.sig',
                    'access_token' => 'ya29.mock',
                ]),
                'response' => ['code' => 200],
            ];
        };

        $result = OraBooks_Auth::handle_google_callback('auth-code-002', 'test-state-002');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('no_email', $result->get_error_code());
    }

    /** @test */
    public function test_handle_google_callback_aud_mismatch()
    {
        $this->storeValidOidcState('test-state-003');

        $GLOBALS['orabooks_test_wp_remote_post_callback'] = function ($url, $args) {
            $payload = base64_encode(json_encode([
                'sub' => 'google-003',
                'email' => 'attacker@evil.com',
                'aud' => 'different-client-id', // Doesn't match our client_id
                'name' => 'Attacker',
            ]));
            return [
                'body' => json_encode([
                    'id_token' => 'header.' . $payload . '.sig',
                ]),
                'response' => ['code' => 200],
            ];
        };

        $result = OraBooks_Auth::handle_google_callback('auth-code-003', 'test-state-003');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_token', $result->get_error_code());
    }

    /** @test */
    public function test_handle_google_callback_new_user_success()
    {
        $this->storeValidOidcState('test-state-new-user');

        global $wpdb;

        // No existing user (get_row returns null)
        $wpdb->test_get_row_callback = function ($query) {
            // First call: check if user exists (SELECT * FROM ... WHERE email = ...)
            if (stripos($query, 'WHERE email') !== false) {
                return null; // No existing user
            }
            // Second call (if needed): not reached since user doesn't exist
            return null;
        };

        // Set insert_id for the new user creation
        $GLOBALS['orabooks_test_use_insert_id'] = 42;

        $result = OraBooks_Auth::handle_google_callback('auth-code-new', 'test-state-new-user');

        $this->assertNotInstanceOf(WP_Error::class, $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertEquals(42, $result['user_id']);
        $this->assertTrue($result['is_new']);
        $this->assertEquals('viewer', $result['role']);

        // Verify JWT payload
        $this->assertEquals(42, $GLOBALS['orabooks_test_last_jwt_payload']['user_id']);
        $this->assertEquals('googleuser@example.com', $GLOBALS['orabooks_test_last_jwt_payload']['email']);
    }

    /** @test */
    public function test_handle_google_callback_new_user_creation_failure()
    {
        $this->storeValidOidcState('test-state-create-fail');

        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return null; // No existing user
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 0; // Simulate insert failure

        $result = OraBooks_Auth::handle_google_callback('auth-code-create-fail', 'test-state-create-fail');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('creation_failed', $result->get_error_code());
    }

    /** @test */
    public function test_handle_google_callback_existing_user_success()
    {
        $this->storeValidOidcState('test-state-existing');

        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id' => 5,
                'email' => 'googleuser@example.com',
                'password_hash' => '',
                'is_active' => 1,
                'is_email_verified' => 1,
                'is_2fa_enabled' => 0,
                'org_id' => 1,
                'is_partner' => 0,
                'auth_provider' => 'google',
            ];
        };

        $result = OraBooks_Auth::handle_google_callback('auth-code-existing', 'test-state-existing');

        $this->assertNotInstanceOf(WP_Error::class, $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals(5, $result['user_id']);
        $this->assertFalse($result['is_new']);
        $this->assertEquals(1, $result['org_id']);
    }

    /** @test */
    public function test_handle_google_callback_existing_user_inactive()
    {
        $this->storeValidOidcState('test-state-inactive');

        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id' => 5,
                'email' => 'inactive@example.com',
                'password_hash' => '',
                'is_active' => 0, // Disabled account
                'is_email_verified' => 1,
                'is_2fa_enabled' => 0,
                'org_id' => null,
                'is_partner' => 0,
                'auth_provider' => 'local',
            ];
        };

        $result = OraBooks_Auth::handle_google_callback('auth-code-inactive', 'test-state-inactive');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('account_disabled', $result->get_error_code());
    }

    /** @test */
    public function test_handle_google_callback_existing_user_2fa_required()
    {
        $this->storeValidOidcState('test-state-2fa');

        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id' => 10,
                'email' => '2fa-user@example.com',
                'password_hash' => '',
                'is_active' => 1,
                'is_email_verified' => 1,
                'is_2fa_enabled' => 1, // 2FA enabled
                'org_id' => 1,
                'is_partner' => 0,
                'auth_provider' => 'local',
            ];
        };

        $result = OraBooks_Auth::handle_google_callback('auth-code-2fa', 'test-state-2fa');

        $this->assertNotInstanceOf(WP_Error::class, $result);
        $this->assertTrue($result['requires_2fa']);
        $this->assertArrayHasKey('temp_token', $result);
        $this->assertEquals(10, $result['user_id']);

        // Verify the JWT payload had purpose=2fa_challenge
        $this->assertEquals('2fa_challenge', $GLOBALS['orabooks_test_last_jwt_payload']['purpose']);
        $this->assertEquals(10, $GLOBALS['orabooks_test_last_jwt_payload']['user_id']);
    }

    /** @test */
    public function test_handle_google_callback_local_user_linked_to_google()
    {
        $this->storeValidOidcState('test-state-link');

        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            static $callCount = 0;
            $callCount++;

            if ($callCount === 1) {
                // First call: check if user exists
                return (object)[
                    'id' => 15,
                    'email' => 'localuser@example.com',
                    'password_hash' => '',
                    'is_active' => 1,
                    'is_email_verified' => 1, // Already verified
                    'is_2fa_enabled' => 0,
                    'org_id' => null,
                    'is_partner' => 0,
                    'auth_provider' => 'local', // Was local — will be linked
                ];
            }
            // Later calls (if any)
            return null;
        };

        $result = OraBooks_Auth::handle_google_callback('auth-code-link', 'test-state-link');

        $this->assertNotInstanceOf(WP_Error::class, $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals(15, $result['user_id']);

        // Verify the UPDATE query would set auth_provider to 'google' and is_email_verified to 1
        // The update() call returns 1 (success) from our mock
        $this->assertStringContainsString('auth_provider', $wpdb->last_query);
    }

    // ================================================================
    // require_customer_org()
    // ================================================================

    /** @test */
    public function test_require_customer_org_no_org_id()
    {
        $result = OraBooks_Auth::require_customer_org(1, 0);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('no_org', $result->get_error_code());
    }

    /** @test */
    public function test_require_customer_org_null_org_id()
    {
        $result = OraBooks_Auth::require_customer_org(1, null);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('no_org', $result->get_error_code());
    }

    /** @test */
    public function test_require_customer_org_org_not_found()
    {
        $GLOBALS['orabooks_test_org_callback'] = function ($org_id) {
            return null;
        };

        $result = OraBooks_Auth::require_customer_org(1, 999);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('org_not_found', $result->get_error_code());
    }

    /** @test */
    public function test_require_customer_org_partner_org_blocked()
    {
        $GLOBALS['orabooks_test_org_callback'] = function ($org_id) {
            return (object)[
                'id'                => $org_id,
                'organization_type' => 'partner',
                'tier'              => 'partner',
                'subdomain'         => 'partner-org',
                'status'            => 'active',
            ];
        };

        $result = OraBooks_Auth::require_customer_org(1, 5);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('accounting_isolation', $result->get_error_code());
        $this->assertStringContainsString('Partner organizations cannot access', $result->get_error_message());
    }

    /** @test */
    public function test_require_customer_org_customer_org_allowed()
    {
        $result = OraBooks_Auth::require_customer_org(1, 1);
        $this->assertTrue($result);
    }

    /** @test */
    public function test_require_customer_org_other_org_type_allowed()
    {
        $GLOBALS['orabooks_test_org_callback'] = function ($org_id) {
            return (object)[
                'id'                => $org_id,
                'organization_type' => 'internal', // Neither customer nor partner
                'subdomain'         => 'internal',
                'status'            => 'active',
            ];
        };

        // Only 'partner' type is blocked
        $result = OraBooks_Auth::require_customer_org(1, 10);
        $this->assertTrue($result);
    }

    // ================================================================
    // login() subdomain mismatch check
    // ================================================================

    private function setupLoginUserWithOrg(): void
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id' => 1,
                'email' => 'user@example.com',
                'password_hash' => password_hash('Password1', PASSWORD_DEFAULT),
                'is_email_verified' => 1,
                'is_active' => 1,
                'is_2fa_enabled' => 0,
                'org_id' => 1,
                'is_partner' => 0,
                'auth_provider' => 'local',
            ];
        };
    }

    /** @test */
    public function test_login_subdomain_mismatch()
    {
        $this->setupLoginUserWithOrg();

        $GLOBALS['orabooks_test_org_callback'] = function ($org_id) {
            return (object)[
                'id' => $org_id,
                'organization_type' => 'customer',
                'subdomain' => 'mycompany',
                'status' => 'active',
            ];
        };

        $result = OraBooks_Auth::login('user@example.com', 'Password1', 'wrongsubdomain');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('subdomain_mismatch', $result->get_error_code());
    }

    /** @test */
    public function test_login_subdomain_match()
    {
        $this->setupLoginUserWithOrg();

        $GLOBALS['orabooks_test_org_callback'] = function ($org_id) {
            return (object)[
                'id' => $org_id,
                'organization_type' => 'customer',
                'subdomain' => 'mycompany',
                'status' => 'active',
            ];
        };

        $result = OraBooks_Auth::login('user@example.com', 'Password1', 'mycompany');

        $this->assertNotInstanceOf(WP_Error::class, $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals(1, $result['org_id']);
    }

    /** @test */
    public function test_login_subdomain_case_insensitive()
    {
        $this->setupLoginUserWithOrg();

        $GLOBALS['orabooks_test_org_callback'] = function ($org_id) {
            return (object)[
                'id' => $org_id,
                'organization_type' => 'customer',
                'subdomain' => 'MyCompany', // Mixed case
                'status' => 'active',
            ];
        };

        $result = OraBooks_Auth::login('user@example.com', 'Password1', 'MYCOMPANY'); // Uppercase

        $this->assertNotInstanceOf(WP_Error::class, $result);
        $this->assertArrayHasKey('token', $result);
    }

    /** @test */
    public function test_login_no_expected_subdomain_still_succeeds()
    {
        $this->setupLoginUserWithOrg();

        $GLOBALS['orabooks_test_org_callback'] = function ($org_id) {
            return (object)[
                'id' => $org_id,
                'organization_type' => 'customer',
                'subdomain' => 'mycompany',
                'status' => 'active',
            ];
        };

        // No subdomain parameter means no check
        $result = OraBooks_Auth::login('user@example.com', 'Password1');

        $this->assertNotInstanceOf(WP_Error::class, $result);
        $this->assertArrayHasKey('token', $result);
    }

    // ================================================================
    // ajax_oidc_initiate()
    // ================================================================

    /** @test */
    public function test_ajax_oidc_initiate_success()
    {
        $response = $this->callAjax('ajax_oidc_initiate');

        $this->assertFalse($response['error']);
        $this->assertArrayHasKey('auth_url', $response['data']);
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $response['data']['auth_url']);
    }

    /** @test */
    public function test_ajax_oidc_initiate_not_configured()
    {
        $GLOBALS['orabooks_test_secrets'] = [];

        $response = $this->callAjax('ajax_oidc_initiate');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('not configured', $response['message']);
    }

    // ================================================================
    // ajax_oidc_callback()
    // ================================================================

    /** @test */
    public function test_ajax_oidc_callback_missing_code()
    {
        $_POST['state'] = 'some-state';

        $response = $this->callAjax('ajax_oidc_callback');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('Missing authorization code or state', $response['message']);
    }

    /** @test */
    public function test_ajax_oidc_callback_missing_state()
    {
        $_POST['code'] = 'some-code';

        $response = $this->callAjax('ajax_oidc_callback');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('Missing authorization code or state', $response['message']);
    }

    /** @test */
    public function test_ajax_oidc_callback_invalid_state()
    {
        $_POST['code'] = 'auth-code-123';
        $_POST['state'] = 'invalid-state';

        $response = $this->callAjax('ajax_oidc_callback');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('Invalid or expired OAuth state', $response['message']);
    }

    /** @test */
    public function test_ajax_oidc_callback_success()
    {
        $this->storeValidOidcState('valid-state-for-ajax');

        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return null; // No existing user — will create new
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 42;

        $_POST['code'] = 'auth-code-ajax-success';
        $_POST['state'] = 'valid-state-for-ajax';

        $response = $this->callAjax('ajax_oidc_callback');

        $this->assertFalse($response['error']);
        $this->assertEquals('Google login successful', $response['message']);
        $this->assertArrayHasKey('token', $response['data']);
        $this->assertEquals(42, $response['data']['user_id']);
    }

    // ================================================================
    // ajax_not_implemented()
    // ================================================================

    /** @test */
    public function test_ajax_not_implemented_returns_501()
    {
        $auth = new OraBooks_Auth();

        try {
            $auth->ajax_not_implemented();
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $response = json_decode($e->getMessage(), true);
            $this->assertTrue($response['error']);
            $this->assertEquals(501, $response['status_code'] ?? 501);
            $this->assertStringContainsString('not yet implemented', $response['message']);
        }
    }
}
