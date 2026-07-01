<?php
/**
 * Unit Tests for OraBooks_Auth SL-013 Methods
 *
 * Covers: Google OIDC flow (initiate + callback), require_customer_org middleware,
 * subdomain mismatch check in login(), 501 placeholder endpoints.
 *
 * Run: phpunit --configuration tests/phpunit.xml --testsuite="OraBooks SL-013 Auth Tests"
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// Stub for get_option used by process_attribution()
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

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
        $_SESSION = [];

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

    /**
     * Store OIDC state data transient for handle_google_callback.
     * Encodes data using the same base64url scheme as encode_oidc_state_data().
     */
    private function storeOidcStateData(string $state, array $data): void
    {
        $state_hash = hash('sha256', $state);
        $encoded = rtrim(strtr(base64_encode(wp_json_encode($data)), '+/', '-_'), '=');
        set_transient('orabooks_oidc_state_data_' . $state_hash, $encoded, 600);
    }

    // ================================================================
    // initiate_google_oauth()
    // ================================================================

    #[Test]
    public function test_initiate_google_oauth_returns_auth_url()
    {
        $url = OraBooks_Auth::initiate_google_oauth();

        $this->assertIsString($url);
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);

        $parts = parse_url($url);
        parse_str($parts['query'], $params);

        $this->assertEquals('test-client-id-123.apps.googleusercontent.com', $params['client_id']);
        $this->assertEquals('http://example.com/login/', $params['redirect_uri']);
        $this->assertEquals('code', $params['response_type']);
        $this->assertEquals('openid email profile', $params['scope']);
        $this->assertArrayHasKey('state', $params);
        $this->assertNotEmpty($params['state']);
        $this->assertEquals('online', $params['access_type']);
        $this->assertEquals('select_account', $params['prompt']);
    }

    #[Test]
    public function test_initiate_google_oauth_returns_wp_error_when_not_configured()
    {
        $GLOBALS['orabooks_test_secrets'] = [];

        $result = OraBooks_Auth::initiate_google_oauth();

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('oauth_not_configured', $result->get_error_code());
    }

    #[Test]
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

    #[Test]
    public function test_initiate_google_oauth_with_partner_state_data()
    {
        $state_data = [
            'user_type'         => 'partner',
            'accept_terms'      => true,
            'partner_type'      => 'agency',
            'organization_name' => 'My Agency',
            'terms_version'     => '2.0',
        ];

        $url = OraBooks_Auth::initiate_google_oauth($state_data);

        $parts = parse_url($url);
        parse_str($parts['query'], $params);

        // Verify state_data parameter is present in the URL
        $this->assertArrayHasKey('state_data', $params);
        $this->assertNotEmpty($params['state_data']);

        // Decode and verify the encoded state_data matches what we passed
        $decoded = json_decode(base64_decode(strtr($params['state_data'], '-_', '+/')), true);
        $this->assertIsArray($decoded);
        $this->assertEquals('partner', $decoded['user_type']);
        $this->assertTrue($decoded['accept_terms']);
        $this->assertEquals('agency', $decoded['partner_type']);
        $this->assertEquals('My Agency', $decoded['organization_name']);
        $this->assertEquals('2.0', $decoded['terms_version']);

        // Verify the state_data transient was stored
        $state_hash = hash('sha256', $params['state']);
        $stored = get_transient('orabooks_oidc_state_data_' . $state_hash);
        $this->assertNotEmpty($stored, 'State data transient should be stored');

        // Verify stored transient decodes correctly
        $stored_decoded = json_decode(base64_decode(strtr($stored, '-_', '+/')), true);
        $this->assertEquals('partner', $stored_decoded['user_type']);
        $this->assertEquals('agency', $stored_decoded['partner_type']);
    }

    #[Test]
    public function test_initiate_google_oauth_sanitizes_invalid_partner_type()
    {
        $state_data = [
            'user_type'    => 'partner',
            'partner_type' => 'nonexistent_type',
        ];

        $url = OraBooks_Auth::initiate_google_oauth($state_data);

        $parts = parse_url($url);
        parse_str($parts['query'], $params);

        $this->assertArrayHasKey('state_data', $params);
        $decoded = json_decode(base64_decode(strtr($params['state_data'], '-_', '+/')), true);
        $this->assertEquals('individual', $decoded['partner_type'], 'Invalid partner_type should default to individual');
    }

    // ================================================================
    // handle_google_callback()
    // ================================================================

    #[Test]
    public function test_handle_google_callback_invalid_state()
    {
        // No state stored — get_transient returns false
        $result = OraBooks_Auth::handle_google_callback('auth-code-123', 'invalid-state');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_state', $result->get_error_code());
    }

    #[Test]
    public function test_handle_google_callback_oauth_not_configured()
    {
        $GLOBALS['orabooks_test_secrets'] = [];
        $this->storeValidOidcState('test-state-123');

        $result = OraBooks_Auth::handle_google_callback('auth-code-123', 'test-state-123');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('oauth_not_configured', $result->get_error_code());
    }

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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
        $this->assertEquals('invalid_id_token', $result->get_error_code());
    }

    #[Test]
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
        $this->assertEquals('invalid_id_token', $result->get_error_code());
    }

    #[Test]
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
        $this->assertArrayHasKey('user_id', $result);
        $this->assertEquals(42, $result['user_id']);
        $this->assertTrue($result['needs_tier_selection']);
        $this->assertArrayHasKey('tier_selection_token', $result);
        $this->assertArrayNotHasKey('token', $result);
        $this->assertArrayNotHasKey('refresh_token', $result);

        // Verify JWT payload
        $this->assertEquals(42, $GLOBALS['orabooks_test_last_jwt_payload']['user_id']);
        $this->assertEquals('tier_selection', $GLOBALS['orabooks_test_last_jwt_payload']['purpose']);
    }

    #[Test]
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

    #[Test]
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
        $this->assertEquals(1, $result['org_id']);
    }

    #[Test]
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

    #[Test]
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

    #[Test]
    public function test_handle_google_callback_local_user_with_password_conflicts()
    {
        $this->storeValidOidcState('test-state-link');

        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id' => 15,
                'email' => 'localuser@example.com',
                'password_hash' => 'hashed-password',
                'is_active' => 1,
                'is_email_verified' => 1,
                'is_2fa_enabled' => 0,
                'org_id' => null,
                'is_partner' => 0,
                'auth_provider' => 'local',
            ];
        };

        $result = OraBooks_Auth::handle_google_callback('auth-code-link', 'test-state-link');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('oidc_email_conflict', $result->get_error_code());
    }

    // ================================================================
    // require_customer_org()
    // ================================================================

    #[Test]
    public function test_require_customer_org_no_org_id()
    {
        $result = OraBooks_Auth::require_customer_org(1, 0);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('no_org', $result->get_error_code());
    }

    #[Test]
    public function test_require_customer_org_null_org_id()
    {
        $result = OraBooks_Auth::require_customer_org(1, null);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('no_org', $result->get_error_code());
    }

    #[Test]
    public function test_require_customer_org_org_not_found()
    {
        $GLOBALS['orabooks_test_org_callback'] = function ($org_id) {
            return null;
        };

        $result = OraBooks_Auth::require_customer_org(1, 999);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('org_not_found', $result->get_error_code());
    }

    #[Test]
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

    #[Test]
    public function test_require_customer_org_customer_org_allowed()
    {
        $result = OraBooks_Auth::require_customer_org(1, 1);
        $this->assertTrue($result);
    }

    #[Test]
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
                'password_hash' => password_hash('Password1!', PASSWORD_DEFAULT),
                'is_email_verified' => 1,
                'is_active' => 1,
                'is_2fa_enabled' => 0,
                'org_id' => 1,
                'is_partner' => 0,
                'auth_provider' => 'local',
            ];
        };
    }

    #[Test]
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

        $result = OraBooks_Auth::login('user@example.com', 'Password1!', 'wrongsubdomain');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('subdomain_mismatch', $result->get_error_code());
    }

    #[Test]
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

        $result = OraBooks_Auth::login('user@example.com', 'Password1!', 'mycompany');

        $this->assertNotInstanceOf(WP_Error::class, $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals(1, $result['org_id']);
    }

    #[Test]
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

        $result = OraBooks_Auth::login('user@example.com', 'Password1!', 'MYCOMPANY'); // Uppercase

        $this->assertNotInstanceOf(WP_Error::class, $result);
        $this->assertArrayHasKey('token', $result);
    }

    #[Test]
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
        $result = OraBooks_Auth::login('user@example.com', 'Password1!');

        $this->assertNotInstanceOf(WP_Error::class, $result);
        $this->assertArrayHasKey('token', $result);
    }

    // ================================================================
    // ajax_oidc_initiate()
    // ================================================================

    #[Test]
    public function test_ajax_oidc_initiate_success()
    {
        $response = $this->callAjax('ajax_oidc_initiate');

        $this->assertFalse($response['error']);
        $this->assertArrayHasKey('auth_url', $response['data']);
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $response['data']['auth_url']);
    }

    #[Test]
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

    #[Test]
    public function test_ajax_oidc_callback_missing_code()
    {
        $_POST['state'] = 'some-state';

        $response = $this->callAjax('ajax_oidc_callback');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('Missing authorization code or state', $response['message']);
    }

    #[Test]
    public function test_ajax_oidc_callback_missing_state()
    {
        $_POST['code'] = 'some-code';

        $response = $this->callAjax('ajax_oidc_callback');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('Missing authorization code or state', $response['message']);
    }

    #[Test]
    public function test_ajax_oidc_callback_invalid_state()
    {
        $_POST['code'] = 'auth-code-123';
        $_POST['state'] = 'invalid-state';

        $response = $this->callAjax('ajax_oidc_callback');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('Invalid or expired OAuth state', $response['message']);
    }

    #[Test]
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
        $this->assertTrue($response['data']['needs_tier_selection']);
        $this->assertArrayHasKey('tier_selection_token', $response['data']);
        $this->assertArrayNotHasKey('token', $response['data']);
        $this->assertEquals(42, $response['data']['user_id']);
    }

    // ================================================================
    // ajax_not_implemented()
    // ================================================================

    #[Test]
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

    // ================================================================
    // REGISTRATION — register()
    // ================================================================

    #[Test]
    public function test_register_customer_success()
    {
        global $wpdb;
        $wpdb->test_get_var_callback = function ($query) {
            return 0; // No existing email
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 42;

        $result = OraBooks_Auth::register([
            'email'    => 'newuser@example.com',
            'password' => 'Password1!',
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(42, $result['user_id']);
        $this->assertEquals('newuser@example.com', $result['email']);
        $this->assertArrayHasKey('requires_email_verification', $result);
        $this->assertTrue($result['requires_email_verification']);
        $this->assertEquals(0, $result['is_partner']);
        $this->assertStringContainsString('Verification', $result['message']);
    }

    #[Test]
    public function test_register_partner_with_terms_success()
    {
        global $wpdb;
        $wpdb->test_get_var_callback = function ($query) {
            return 0;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 50;

        $result = OraBooks_Auth::register([
            'email'       => 'partner@example.com',
            'password'    => 'Password1!',
            'user_type'   => 'partner',
            'accept_terms' => true,
            'terms_version' => '2.0',
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(50, $result['user_id']);
        $this->assertEquals(1, $result['is_partner']);
        $this->assertArrayHasKey('requires_email_verification', $result);
        $this->assertTrue($result['requires_email_verification']);
    }

    #[Test]
    public function test_register_invalid_email()
    {
        $result = OraBooks_Auth::register([
            'email'    => 'not-an-email',
            'password' => 'Password1!',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_email', $result->get_error_code());
    }

    #[Test]
    public function test_register_weak_password()
    {
        $result = OraBooks_Auth::register([
            'email'    => 'test@example.com',
            'password' => 'short',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('weak_password', $result->get_error_code());
    }

    #[Test]
    public function test_register_duplicate_email()
    {
        global $wpdb;
        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'WHERE email') !== false) {
                return 5; // Existing email found
            }
            return 0;
        };

        $result = OraBooks_Auth::register([
            'email'    => 'existing@example.com',
            'password' => 'Password1!',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('email_exists', $result->get_error_code());
    }

    #[Test]
    public function test_register_partner_no_terms()
    {
        $result = OraBooks_Auth::register([
            'email'      => 'partner@example.com',
            'password'   => 'Password1!',
            'user_type'  => 'partner',
            'accept_terms' => false,
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('terms_required', $result->get_error_code());
    }

    #[Test]
    public function test_register_partner_agency_no_org_name()
    {
        $result = OraBooks_Auth::register([
            'email'        => 'agency@example.com',
            'password'     => 'Password1!',
            'user_type'    => 'partner',
            'accept_terms' => true,
            'partner_type' => 'agency',
            'organization_name' => '',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('org_name_required', $result->get_error_code());
    }

    #[Test]
    public function test_register_partner_invalid_type_defaults_to_individual()
    {
        global $wpdb;
        $wpdb->test_get_var_callback = function ($query) {
            return 0;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 60;

        $result = OraBooks_Auth::register([
            'email'        => 'unknown@example.com',
            'password'     => 'Password1!',
            'user_type'    => 'partner',
            'accept_terms' => true,
            'partner_type' => 'nonexistent_type',
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(60, $result['user_id']);
        $this->assertEquals(1, $result['is_partner']);
    }

    #[Test]
    public function test_register_customer_with_valid_partner_code()
    {
        global $wpdb;

        $getVarCalls = 0;
        $wpdb->test_get_var_callback = function ($query) use (&$getVarCalls) {
            $getVarCalls++;
            return 0; // Email not taken, no duplicate attribution
        };

        $wpdb->test_get_row_callback = function ($query) {
            // Return active partner code for attribution
            return (object)[
                'user_id'     => 5,
                'partner_code' => 'PARTNER-TEST',
                'status'       => 'active',
            ];
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 70;

        $result = OraBooks_Auth::register([
            'email'       => 'customer@example.com',
            'password'    => 'Password1!',
            'user_type'   => 'customer',
            'partner_code' => 'PARTNER-TEST',
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(70, $result['user_id']);
        $this->assertEquals(0, $result['is_partner']);
        $this->assertArrayHasKey('requires_email_verification', $result);
        $this->assertTrue($result['requires_email_verification']);
    }

    #[Test]
    public function test_register_customer_invalid_partner_code()
    {
        global $wpdb;

        $wpdb->test_get_var_callback = function ($query) {
            return 0;
        };

        $wpdb->test_get_row_callback = function ($query) {
            return null; // Partner code not found
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 80;

        $result = OraBooks_Auth::register([
            'email'       => 'customer@example.com',
            'password'    => 'Password1!',
            'user_type'   => 'customer',
            'partner_code' => 'INVALID-CODE',
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(80, $result['user_id']);
        $this->assertEquals(0, $result['is_partner']);
    }

    // ================================================================
    // MULTISITE ACTIVATION LINKAGE
    // ================================================================

    #[Test]
    public function test_handle_multisite_user_activation_links_wp_user_to_orabooks_user()
    {
        global $wpdb;

        $before_log_count = count($GLOBALS['orabooks_test_log_events'] ?? []);
        $updates = [];
        $wpdb->test_update_callback = function ($table, $data, $where) use (&$updates) {
            $updates[] = [$table, $data, $where];
            return 1;
        };

        $auth = OraBooks_Auth::init();
        $auth->handle_multisite_user_activation(321, 'unused-password', [
            'orabooks_user_id' => 44,
        ]);

        $matching_updates = array_values(array_filter($updates, function ($row) {
            [$table, $data, $where] = $row;
            return str_contains($table, 'orabooks_users')
                && isset($data['wp_user_id'])
                && (int) $data['wp_user_id'] === 321
                && isset($where['id'])
                && (int) $where['id'] === 44;
        }));

        $this->assertCount(1, $matching_updates);
        $new_logs = array_slice($GLOBALS['orabooks_test_log_events'] ?? [], $before_log_count);
        $this->assertNotEmpty($new_logs);
        $events = array_map(static function ($row) { return $row['event_type'] ?? ''; }, $new_logs);
        $this->assertContains('wp_user_activated', $events);
    }

    #[Test]
    public function test_handle_multisite_blog_activation_links_wp_user_to_orabooks_user()
    {
        global $wpdb;

        $before_log_count = count($GLOBALS['orabooks_test_log_events'] ?? []);
        $updates = [];
        $wpdb->test_update_callback = function ($table, $data, $where) use (&$updates) {
            $updates[] = [$table, $data, $where];
            return 1;
        };

        $auth = OraBooks_Auth::init();
        $auth->handle_multisite_blog_activation(9, 654, 'unused-password', 'Site Title', [
            'orabooks_user_id' => 99,
        ]);

        $matching_updates = array_values(array_filter($updates, function ($row) {
            [$table, $data, $where] = $row;
            return str_contains($table, 'orabooks_users')
                && isset($data['wp_user_id'])
                && (int) $data['wp_user_id'] === 654
                && isset($where['id'])
                && (int) $where['id'] === 99;
        }));

        $this->assertCount(1, $matching_updates);
        $new_logs = array_slice($GLOBALS['orabooks_test_log_events'] ?? [], $before_log_count);
        $this->assertNotEmpty($new_logs);
        $events = array_map(static function ($row) { return $row['event_type'] ?? ''; }, $new_logs);
        $this->assertContains('wp_user_activated', $events);
    }

    #[Test]
    public function test_handle_multisite_activation_skips_when_orabooks_user_id_missing()
    {
        global $wpdb;

        $before_log_count = count($GLOBALS['orabooks_test_log_events'] ?? []);
        $updates = [];
        $wpdb->test_update_callback = function ($table, $data, $where) use (&$updates) {
            $updates[] = [$table, $data, $where];
            return 1;
        };

        $auth = OraBooks_Auth::init();
        $auth->handle_multisite_user_activation(777, 'unused-password', []);

        $this->assertCount(0, $updates);
        $new_logs = array_slice($GLOBALS['orabooks_test_log_events'] ?? [], $before_log_count);
        $this->assertCount(0, $new_logs);
    }

    // ================================================================
    // LOGIN — login()
    // ================================================================

    private function makeLoginUser(array $overrides = []): object
    {
        return (object) array_merge([
            'id'                => 1,
            'email'             => 'user@example.com',
            'password_hash'     => password_hash('Password1!', PASSWORD_DEFAULT),
            'is_email_verified' => 1,
            'is_active'         => 1,
            'is_2fa_enabled'    => 0,
            'org_id'            => 1,
            'is_partner'        => 0,
            'auth_provider'     => 'local',
        ], $overrides);
    }

    #[Test]
    public function test_login_invalid_credentials()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return $this->makeLoginUser();
        };

        $result = OraBooks_Auth::login('user@example.com', 'WrongPassword99');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_credentials', $result->get_error_code());
    }

    #[Test]
    public function test_login_email_not_verified()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return $this->makeLoginUser(['is_email_verified' => 0]);
        };

        $result = OraBooks_Auth::login('user@example.com', 'Password1!');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('email_not_verified', $result->get_error_code());
    }

    #[Test]
    public function test_login_account_disabled()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return $this->makeLoginUser(['is_active' => 0]);
        };

        $result = OraBooks_Auth::login('user@example.com', 'Password1!');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('account_disabled', $result->get_error_code());
    }

    #[Test]
    public function test_login_2fa_required()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return $this->makeLoginUser(['is_2fa_enabled' => 1]);
        };

        $result = OraBooks_Auth::login('user@example.com', 'Password1!');

        $this->assertNotInstanceOf(WP_Error::class, $result);
        $this->assertTrue($result['requires_2fa']);
        $this->assertArrayHasKey('temp_token', $result);
        $this->assertEquals(1, $result['user_id']);
        $this->assertEquals('2fa_challenge', $GLOBALS['orabooks_test_last_jwt_payload']['purpose']);
        $this->assertLessThanOrEqual(time() + 301, (int) $GLOBALS['orabooks_test_last_jwt_payload']['exp']);
        $this->assertGreaterThan(time() + 240, (int) $GLOBALS['orabooks_test_last_jwt_payload']['exp']);
    }

    #[Test]
    public function test_login_partner_first_login()
    {
        global $wpdb;

        $getRowCalls = 0;
        $wpdb->test_get_row_callback = function ($query) use (&$getRowCalls) {
            $getRowCalls++;
            if ($getRowCalls === 1) {
                // First call: authenticate user
                return $this->makeLoginUser([
                    'is_partner' => 1,
                    'org_id'     => null,
                    'pending_partner_type' => 'individual',
                    'pending_organization_name' => 'My Partner Firm',
                ]);
            }
            return null; // Subsequent calls
        };

        $result = OraBooks_Auth::login('partner@example.com', 'Password1!');

        $this->assertNotInstanceOf(WP_Error::class, $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertEquals(1, $result['user_id']);
        $this->assertEquals('owner', $result['role']);
        $this->assertEquals(1, $result['is_partner']);
        $this->assertEquals('/partner/onboarding/', $result['redirect_to']);
    }

    #[Test]
    public function test_login_customer_needs_tier_selection()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return $this->makeLoginUser([
                'is_partner' => 0,
                'org_id'     => null,
            ]);
        };

        $result = OraBooks_Auth::login('customer@example.com', 'Password1!');

        $this->assertNotInstanceOf(WP_Error::class, $result);
        $this->assertTrue($result['needs_tier_selection']);
        $this->assertArrayHasKey('tier_selection_token', $result);
        $this->assertArrayNotHasKey('token', $result);
        $this->assertArrayNotHasKey('refresh_token', $result);
    }

    #[Test]
    public function test_login_success_with_org()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return $this->makeLoginUser();
        };

        $GLOBALS['orabooks_test_get_user_role_callback'] = function () {
            return 'admin';
        };

        $result = OraBooks_Auth::login('user@example.com', 'Password1!');

        $this->assertNotInstanceOf(WP_Error::class, $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertEquals(1, $result['user_id']);
        $this->assertEquals(1, $result['org_id']);
        $this->assertEquals('admin', $result['role']);
        $this->assertEquals('testorg', $result['subdomain']);
        $this->assertEquals(0, $result['is_partner']);
    }

    #[Test]
    public function test_login_resolves_org_from_membership_when_user_org_id_is_stale()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return $this->makeLoginUser([
                'org_id' => null,
            ]);
        };

        $wpdb->test_get_var_callback = function ($query) {
            if (strpos($query, 'FROM wp_orabooks_user_org') !== false) {
                return 7;
            }

            return 0;
        };

        $GLOBALS['orabooks_test_get_user_role_callback'] = function ($user_id, $org_id) {
            return ((int) $org_id === 7) ? 'staff' : 'viewer';
        };

        $result = OraBooks_Auth::login('user@example.com', 'Password1!');

        $this->assertNotInstanceOf(WP_Error::class, $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertEquals(7, (int) $result['org_id']);
        $this->assertEquals('staff', $result['role']);
        $this->assertArrayNotHasKey('needs_accept_invite', $result);
        $this->assertArrayNotHasKey('needs_tier_selection', $result);
    }

    // ================================================================
    // EMAIL VERIFICATION — verify_email()
    // ================================================================

    #[Test]
    public function test_verify_email_valid_token()
    {
        global $wpdb;
        $getRowCalls = 0;
        $wpdb->test_get_row_callback = function ($query) use (&$getRowCalls) {
            $getRowCalls++;
            if ($getRowCalls === 1) {
                // First call: find user by token
                return (object)[
                    'id'                => 1,
                    'email'             => 'user@example.com',
                    'org_id'            => null,
                    'is_email_verified' => 0,
                ];
            }
            // Second call: attribution fetch — return null (no pending attribution)
            return null;
        };
        // get_var for pending attribution check — returns 0 (no pending)
        $wpdb->test_get_var_callback = function ($query) {
            return 0;
        };
        // get_results for attribution query — returns empty
        $wpdb->test_get_results_callback = function ($query) {
            return [];
        };

        $result = OraBooks_Auth::verify_email('valid-token-123');

        $this->assertTrue($result);
    }

    #[Test]
    public function test_verify_email_invalid_token()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return null; // No user with that token
        };

        $result = OraBooks_Auth::verify_email('invalid-token');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_token', $result->get_error_code());
    }

    #[Test]
    public function test_verify_email_with_pending_attribution()
    {
        global $wpdb;

        $getRowCalls = 0;
        $wpdb->test_get_row_callback = function ($query) use (&$getRowCalls) {
            $getRowCalls++;
            if ($getRowCalls === 1) {
                // First call: find user by token
                return (object)[
                    'id'                => 1,
                    'email'             => 'customer@example.com',
                    'org_id'            => null,
                    'is_email_verified' => 0,
                ];
            }
            if ($getRowCalls === 2) {
                // Second call: find pending attribution
                return (object)[
                    'id'              => 100,
                    'partner_user_id' => 5,
                    'customer_user_id' => 1,
                    'customer_email'  => 'customer@example.com',
                    'status'          => 'pending',
                    'verified_at'     => null,
                ];
            }
            return null;
        };

        $wpdb->test_get_var_callback = function ($query) {
            return 0;
        };
        $wpdb->test_get_results_callback = function ($query) {
            return [];
        };

        $result = OraBooks_Auth::verify_email('valid-token');

        $this->assertTrue($result);
    }

    // ================================================================
    // PASSWORD RESET — forgot_password() / reset_password()
    // ================================================================

    #[Test]
    public function test_forgot_password_success()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)['id' => 1, 'email' => 'user@example.com'];
        };

        $result = OraBooks_Auth::forgot_password('user@example.com');

        $this->assertTrue($result);
    }

    #[Test]
    public function test_forgot_password_nonexistent_email()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return null; // Email doesn't exist
        };

        // Should still return true to avoid leaking email existence
        $result = OraBooks_Auth::forgot_password('ghost@example.com');

        $this->assertTrue($result);
    }

    #[Test]
    public function test_reset_password_success()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id'     => 1,
                'email'  => 'user@example.com',
                'org_id' => 1,
            ];
        };

        $result = OraBooks_Auth::reset_password('valid-reset-token', 'NewPassword1!');

        $this->assertTrue($result);
    }

    #[Test]
    public function test_reset_password_invalid_token()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return null; // No user with that token
        };

        $result = OraBooks_Auth::reset_password('bad-token', 'NewPassword1!');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_token', $result->get_error_code());
    }

    #[Test]
    public function test_reset_password_weak_new_password()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id'     => 1,
                'email'  => 'user@example.com',
                'org_id' => 1,
            ];
        };

        $result = OraBooks_Auth::reset_password('valid-token', 'weak');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('weak_password', $result->get_error_code());
    }

    // ================================================================
    // SUBDOMAIN DETECTION — detect_subdomain_from_host()
    // ================================================================

    #[Test]
    public function test_detect_subdomain_from_host_valid()
    {
        $_SERVER['HTTP_HOST'] = 'mycompany.orabooks.app';
        $this->assertEquals('mycompany', OraBooks_Auth::detect_subdomain_from_host());
    }

    #[Test]
    public function test_detect_subdomain_from_host_root_domain()
    {
        $_SERVER['HTTP_HOST'] = 'orabooks.app';
        $this->assertEquals('', OraBooks_Auth::detect_subdomain_from_host());
    }

    #[Test]
    public function test_detect_subdomain_from_host_www()
    {
        $_SERVER['HTTP_HOST'] = 'www.orabooks.app';
        $this->assertEquals('', OraBooks_Auth::detect_subdomain_from_host());
    }

    #[Test]
    public function test_detect_subdomain_from_host_localhost()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $this->assertEquals('', OraBooks_Auth::detect_subdomain_from_host());
    }

    #[Test]
    public function test_detect_subdomain_from_host_empty()
    {
        $_SERVER['HTTP_HOST'] = '';
        $this->assertEquals('', OraBooks_Auth::detect_subdomain_from_host());
    }

    // ================================================================
    // 2FA AJAX — ajax_setup_2fa()
    // ================================================================

    #[Test]
    public function test_ajax_setup_2fa_success()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function () {
            return (object) [
                'id' => 1,
                'email' => 'user@example.com',
                'is_2fa_enabled' => 0,
            ];
        };

        $response = $this->callAjax('ajax_setup_2fa');

        $this->assertFalse($response['error']);
        $this->assertArrayHasKey('secret', $response['data']);
        $this->assertArrayHasKey('otpauth_uri', $response['data']);
        $this->assertArrayHasKey('qr_code_url', $response['data']);
        $this->assertArrayHasKey('backup_codes', $response['data']);
        $this->assertStringContainsString('otpauth://', $response['data']['otpauth_uri']);
        $this->assertStringContainsString('quickchart.io/qr', $response['data']['qr_code_url']);
        $this->assertCount(8, $response['data']['backup_codes']);
    }

    #[Test]
    public function test_ajax_setup_2fa_unauthenticated()
    {
        $GLOBALS['orabooks_test_current_user_id'] = 0;

        $response = $this->callAjax('ajax_setup_2fa');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('Not authenticated', $response['message']);
    }

    // ================================================================
    // 2FA AJAX — ajax_verify_2fa_setup()
    // ================================================================

    #[Test]
    public function test_ajax_verify_2fa_setup_success()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function () {
            return (object) [
                'id' => 1,
                'email' => 'user@example.com',
                'is_2fa_enabled' => 0,
            ];
        };

        // Set up temp secret and backup codes
        $GLOBALS['orabooks_test_user_meta'][1]['orabooks_2fa_temp_secret'] = 'TESTSECRET123456';
        $GLOBALS['orabooks_test_user_meta'][1]['orabooks_2fa_temp_backup_codes'] = ['bc1', 'bc2', 'bc3'];
        $_POST['otp_code'] = '123456';

        $response = $this->callAjax('ajax_verify_2fa_setup');

        $this->assertFalse($response['error']);
        $this->assertStringContainsString('2FA enabled', $response['message']);

        // Verify permanent secret was stored and temp secret was cleaned up
        $this->assertArrayHasKey('orabooks_2fa_secret', $GLOBALS['orabooks_test_user_meta'][1]);
        $this->assertArrayNotHasKey('orabooks_2fa_temp_secret', $GLOBALS['orabooks_test_user_meta'][1]);
        $this->assertArrayNotHasKey('orabooks_2fa_temp_backup_codes', $GLOBALS['orabooks_test_user_meta'][1]);
    }

    #[Test]
    public function test_ajax_verify_2fa_setup_invalid_otp()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function () {
            return (object) ['id' => 1, 'is_2fa_enabled' => 0];
        };

        $GLOBALS['orabooks_test_user_meta'][1]['orabooks_2fa_temp_secret'] = 'TESTSECRET123456';
        $_POST['otp_code'] = 'wrong-otp';

        $response = $this->callAjax('ajax_verify_2fa_setup');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('Invalid OTP', $response['message']);
    }

    #[Test]
    public function test_ajax_verify_2fa_setup_not_initiated()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function () {
            return (object) ['id' => 1, 'is_2fa_enabled' => 0];
        };

        $_POST['otp_code'] = '123456';

        $response = $this->callAjax('ajax_verify_2fa_setup');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('2FA setup not initiated', $response['message']);
    }

    // ================================================================
    // 2FA AJAX — ajax_2fa_challenge()
    // ================================================================

    #[Test]
    public function test_ajax_2fa_challenge_valid_otp()
    {
        global $wpdb;

        $GLOBALS['orabooks_test_verify_jwt_result'] = [
            'purpose' => '2fa_challenge',
            'user_id' => 1,
            'exp'     => time() + 300,
        ];
        $GLOBALS['orabooks_test_user_meta'][1]['orabooks_2fa_secret'] = 'TESTSECRET123456';
        $_POST['temp_token'] = 'valid-temp-token';
        $_POST['otp_code']   = '123456';

        // Mock user fetch after challenge verification — returns a proper user object with all properties
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id'         => 1,
                'email'      => 'admin@example.com',
                'org_id'     => 1,
                'is_partner' => 0,
                'is_2fa_enabled' => 1,
            ];
        };

        $response = $this->callAjax('ajax_2fa_challenge');

        $this->assertFalse($response['error']);
        $this->assertArrayHasKey('token', $response['data']);
        $this->assertArrayNotHasKey('refresh_token', $response['data']);
        $this->assertEquals(1, $response['data']['user_id']);
    }

    #[Test]
    public function test_ajax_2fa_challenge_invalid_otp()
    {
        $GLOBALS['orabooks_test_verify_jwt_result'] = [
            'purpose' => '2fa_challenge',
            'user_id' => 1,
            'exp'     => time() + 300,
        ];
        $GLOBALS['orabooks_test_user_meta'][1]['orabooks_2fa_secret'] = 'TESTSECRET123456';
        $_POST['temp_token'] = 'valid-temp-token';
        $_POST['otp_code']   = 'wrong-otp';

        $response = $this->callAjax('ajax_2fa_challenge');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('Invalid verification code', $response['message']);
    }

    #[Test]
    public function test_ajax_2fa_challenge_invalid_temp_token()
    {
        $GLOBALS['orabooks_test_verify_jwt_result'] = false; // Invalid token
        $_POST['temp_token'] = 'expired-token';
        $_POST['otp_code']   = '123456';

        $response = $this->callAjax('ajax_2fa_challenge');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('Invalid or expired challenge token', $response['message']);
    }

    #[Test]
    public function test_ajax_2fa_challenge_wrong_purpose()
    {
        $GLOBALS['orabooks_test_verify_jwt_result'] = [
            'purpose' => 'email_verification', // Wrong purpose
            'user_id' => 1,
        ];
        $_POST['temp_token'] = 'wrong-purpose-token';
        $_POST['otp_code']   = '123456';

        $response = $this->callAjax('ajax_2fa_challenge');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('Invalid or expired challenge token', $response['message']);
    }

    #[Test]
    public function test_ajax_2fa_challenge_valid_backup_code()
    {
        global $wpdb;

        $GLOBALS['orabooks_test_verify_jwt_result'] = [
            'purpose' => '2fa_challenge',
            'user_id' => 1,
            'exp'     => time() + 300,
        ];
        $GLOBALS['orabooks_test_user_meta'][1]['orabooks_2fa_secret'] = 'TESTSECRET123456';
        $_POST['temp_token']  = 'valid-temp-token';
        $_POST['backup_code'] = 'valid-backup-001';
        $_POST['otp_code']    = ''; // No OTP — using backup code

        // Mock get_results to return a stored backup code
        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'orabooks_2fa_backup_codes') !== false) {
                return [(object)[
                    'id'        => 1,
                    'code_hash' => password_hash('VALID-BACKUP-001', PASSWORD_DEFAULT),
                    'used'      => 0,
                ]];
            }
            return [];
        };

        // Mock user fetch after challenge verification — returns a proper user object with all properties
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id'         => 1,
                'email'      => 'admin@example.com',
                'org_id'     => 1,
                'is_partner' => 0,
                'is_2fa_enabled' => 1,
            ];
        };

        $response = $this->callAjax('ajax_2fa_challenge');

        $this->assertFalse($response['error']);
        $this->assertArrayHasKey('token', $response['data']);
        $this->assertArrayNotHasKey('refresh_token', $response['data']);
        $this->assertEquals(1, $response['data']['user_id']);
    }

    // ================================================================
    // LOGOUT — ajax_logout()
    // ================================================================

    #[Test]
    public function test_ajax_logout_success()
    {
        $response = $this->callAjax('ajax_logout');

        $this->assertFalse($response['error']);
        $this->assertStringContainsString('Logged out', $response['message']);
        $this->assertArrayHasKey('redirect_to', $response['data']);
        $this->assertStringContainsString('logged_out=1', $response['data']['redirect_to']);
    }

    #[Test]
    public function test_ajax_logout_unauthenticated()
    {
        $GLOBALS['orabooks_test_current_user_id'] = 0;

        $response = $this->callAjax('ajax_logout');

        $this->assertFalse($response['error']);
        $this->assertStringContainsString('Logged out', $response['message']);
        $this->assertArrayHasKey('redirect_to', $response['data']);
        $this->assertStringContainsString('logged_out=1', $response['data']['redirect_to']);
    }

    // ================================================================
    // OIDC EDGE CASES — handle_google_callback() extensions
    // ================================================================

    #[Test]
    public function test_handle_google_callback_new_partner_success()
    {
        $this->storeValidOidcState('test-state-partner');
        $this->storeOidcStateData('test-state-partner', [
            'user_type'      => 'partner',
            'accept_terms'   => true,
            'partner_type'   => 'individual',
            'terms_version'  => '1.0',
        ]);

        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            // First call: no existing user (will create new)
            if (stripos($query, 'WHERE email') !== false) {
                return null;
            }
            // Subsequent calls (partner code, etc.)
            return null;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 100;

        $result = OraBooks_Auth::handle_google_callback('auth-code-partner', 'test-state-partner');

        $this->assertNotInstanceOf(WP_Error::class, $result);
        $this->assertEquals(1, $result['is_partner']);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertEquals('/partner/onboarding/', $result['redirect_to']);
    }

    #[Test]
    public function test_handle_google_callback_existing_user_email_conflict()
    {
        $this->storeValidOidcState('test-state-conflict');
        $this->storeOidcStateData('test-state-conflict', [
            'user_type' => 'customer',
        ]);

        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id'                => 5,
                'email'             => 'localuser@example.com',
                'password_hash'     => 'hashed-password-value',
                'is_active'         => 1,
                'is_email_verified' => 1,
                'is_2fa_enabled'    => 0,
                'org_id'            => null,
                'is_partner'        => 0,
                'auth_provider'     => 'local',
            ];
        };

        $result = OraBooks_Auth::handle_google_callback('auth-code-conflict', 'test-state-conflict');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('oidc_email_conflict', $result->get_error_code());
    }

    // ================================================================
    // require_customer_org() extensions
    // ================================================================

    #[Test]
    public function test_require_customer_org_inactive_org_blocked()
    {
        $GLOBALS['orabooks_test_org_callback'] = function ($org_id) {
            return (object)[
                'id'                => $org_id,
                'organization_type' => 'customer',
                'subdomain'         => 'inactive-org',
                'status'            => 'suspended',
            ];
        };

        $result = OraBooks_Auth::require_customer_org(1, 10);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('org_inactive', $result->get_error_code());
    }

    // ================================================================
    // LOGIN — subdomain mismatch extensions (via detect_subdomain_from_host)
    // ================================================================

    #[Test]
    public function test_login_inactive_org_blocked()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return $this->makeLoginUser();
        };

        $GLOBALS['orabooks_test_org_callback'] = function ($org_id) {
            return (object)[
                'id'                => $org_id,
                'organization_type' => 'customer',
                'subdomain'         => 'testorg',
                'status'            => 'suspended',
            ];
        };

        $result = OraBooks_Auth::login('user@example.com', 'Password1!');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('org_inactive', $result->get_error_code());
    }

    // ================================================================
    // REFRESH TOKEN — refresh_token()
    // ================================================================

    #[Test]
    public function test_refresh_token_success()
    {
        global $wpdb;

        $getRowCalls = 0;
        $wpdb->test_get_row_callback = function ($query) use (&$getRowCalls) {
            $getRowCalls++;
            if ($getRowCalls === 1) {
                // First call: find refresh token
                return (object)[
                    'id'      => 1,
                    'user_id' => 1,
                    'org_id'  => 1,
                ];
            }
            if ($getRowCalls === 2) {
                // Second call: find user by id
                return (object)[
                    'id'         => 1,
                    'email'      => 'user@example.com',
                    'org_id'     => 1,
                    'is_partner' => 0,
                ];
            }
            return null;
        };

        $result = OraBooks_Auth::refresh_token('valid-refresh-token');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
    }

    #[Test]
    public function test_refresh_token_invalid()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return null; // Token not found
        };

        $result = OraBooks_Auth::refresh_token('invalid-token');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_token', $result->get_error_code());
    }

    // ================================================================
    // PARTNER LOGIN OIDC — partner via OIDC
    // ================================================================

    #[Test]
    public function test_handle_google_callback_existing_partner_first_login()
    {
        $this->storeValidOidcState('test-state-existing-fl');
        $this->storeOidcStateData('test-state-existing-fl', [
            'user_type'      => 'partner',
            'accept_terms'   => true,
            'partner_type'   => 'accountant',
            'organization_name' => 'My Accounting Firm',
            'terms_version'  => '1.0',
        ]);

        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id'                => 30,
                'email'             => 'existing-partner@example.com',
                'password_hash'     => '',
                'is_active'         => 1,
                'is_email_verified' => 1,
                'is_2fa_enabled'    => 0,
                'org_id'            => null,   // No org yet — first login
                'is_partner'        => 1,      // Already a partner user
                'auth_provider'     => 'google', // Already Google-linked
            ];
        };

        $result = OraBooks_Auth::handle_google_callback('auth-code-fl-partner', 'test-state-existing-fl');

        // Should not be an error
        $this->assertNotInstanceOf(WP_Error::class, $result);

        // Should have the partner first login response shape
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertEquals(30, $result['user_id']);
        $this->assertArrayHasKey('org_id', $result);
        $this->assertNotNull($result['org_id']);
        $this->assertEquals('owner', $result['role']);
        $this->assertEquals(1, $result['is_partner']);
        $this->assertEquals('/partner/onboarding/', $result['redirect_to']);
    }

    #[Test]
    public function test_handle_google_callback_existing_partner_2fa()
    {
        $this->storeValidOidcState('test-state-partner-2fa');

        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id'                => 20,
                'email'             => 'partner@example.com',
                'password_hash'     => '',
                'is_active'         => 1,
                'is_email_verified' => 1,
                'is_2fa_enabled'    => 1, // Has 2FA
                'org_id'            => 10,
                'is_partner'        => 1,
                'auth_provider'     => 'google',
            ];
        };

        $result = OraBooks_Auth::handle_google_callback('auth-code-p2fa', 'test-state-partner-2fa');

        $this->assertNotInstanceOf(WP_Error::class, $result);
        $this->assertTrue($result['requires_2fa']);
        $this->assertEquals(20, $result['user_id']);
        $this->assertArrayHasKey('temp_token', $result);
    }
}
