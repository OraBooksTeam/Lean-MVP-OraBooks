<?php
/**
 * Unit Tests for OraBooks_Rest_Api + OpenAPI
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Rest_Api_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists('OBN_Access_Control')) {
            OBN_Access_Control::init();
        } elseif (class_exists('OraBooks_RBAC')) {
            OraBooks_RBAC::init();
        }
    }

    #[Test]
    public function test_openapi_spec_loads_with_core_paths()
    {
        $spec = OraBooks_Rest_Api::load_openapi_spec();

        $this->assertSame('3.0.3', $spec['openapi'] ?? null);
        $this->assertArrayHasKey('/fiscal-periods', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/journals', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/auth/2fa/setup', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/org/security/2fa-policy', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/expenses/upload-receipt', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/expenses/settings', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/expenses/{id}/approve', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/expenses/{id}/reject', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/expenses/{id}/post', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/openapi.json', $spec['paths'] ?? []);
    }

    #[Test]
    public function test_openapi_spec_file_exists()
    {
        $this->assertFileExists(OraBooks_Rest_Api::openapi_spec_path());
    }

    #[Test]
    public function test_format_period_for_api_maps_status()
    {
        $formatted = OraBooks_Fiscal::format_period_for_api((object) [
            'id' => 3,
            'org_id' => 9,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'status' => 'soft_closed',
        ]);

        $this->assertSame('soft_closed', $formatted['status']);
        $this->assertSame('Soft Closed', $formatted['status_label']);
        $this->assertSame(3, $formatted['id']);
    }

    #[Test]
    public function test_rest_state_transition_requires_record_fields()
    {
        $request = new WP_REST_Request('POST', '/api/internal/state/transition');
        $request->set_header('X-OraBooks-Org-Id', '5');
        $request->set_param('org_id', 5);

        $GLOBALS['orabooks_test_current_user_id'] = 1;
        $GLOBALS['orabooks_test_has_permission'] = true;

        $result = OraBooks_Rest_Api::rest_state_transition($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_request', $result->get_error_code());
    }

    #[Test]
    public function test_rest_get_journal_requires_existing_journal()
    {
        global $wpdb;

        $request = new WP_REST_Request('GET', '/api/journals/999');
        $request->set_header('X-OraBooks-Org-Id', '5');
        $request->set_param('org_id', 5);
        $request->set_param('id', 999);

        $GLOBALS['orabooks_test_current_user_id'] = 1;
        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'user_org') !== false || stripos($query, 'owner_id') !== false) {
                return 1;
            }
            return null;
        };
        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'organizations') !== false) {
                return (object) [
                    'id' => 5,
                    'status' => 'active',
                    'organization_type' => 'customer',
                    'name' => 'Test Org',
                    'tier' => 'premium',
                    'subdomain' => 'testorg',
                    'owner_id' => 1,
                ];
            }
            if (stripos($query, 'journals') !== false) {
                return null;
            }
            return null;
        };

        $result = OraBooks_Rest_Api::rest_get_journal($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('not_found', $result->get_error_code());
    }
}
