<?php
/**
 * Unit Tests for OraBooks_Security (SL-099)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Security_Test extends TestCase
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
        $GLOBALS['orabooks_test_user_meta'] = [];
        $GLOBALS['orabooks_test_transients'] = [];
    }

    #[Test]
    public function test_schema_defines_sl099_tables()
    {
        $sql = implode("\n", OraBooks_Security::get_create_table_sql());

        $this->assertStringContainsString('orabooks_security_controls', $sql);
        $this->assertStringContainsString('orabooks_security_incidents', $sql);
        $this->assertStringContainsString('orabooks_security_scan_results', $sql);
        $this->assertStringContainsString('owasp_id', $sql);
    }

    #[Test]
    public function test_validate_outbound_url_blocks_private_hosts()
    {
        $result = OraBooks_Security::validate_outbound_url('https://127.0.0.1/hook');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('ssrf_private', $result->get_error_code());
    }

    #[Test]
    public function test_validate_outbound_url_allows_slack_hooks()
    {
        $result = OraBooks_Security::validate_outbound_url('https://hooks.slack.com/services/T00/B00/xxx');

        $this->assertTrue($result);
    }

    #[Test]
    public function test_validate_outbound_url_rejects_non_https()
    {
        $result = OraBooks_Security::validate_outbound_url('http://hooks.slack.com/services/x');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('ssrf_scheme', $result->get_error_code());
    }

    #[Test]
    public function test_validate_input_rejects_invalid_email()
    {
        $result = OraBooks_Security::validate_input('email', 'not-an-email');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_input', $result->get_error_code());
    }

    #[Test]
    public function test_validate_input_accepts_valid_uuid()
    {
        $result = OraBooks_Security::validate_input('uuid', '550e8400-e29b-41d4-a716-446655440000');

        $this->assertTrue($result);
    }

    #[Test]
    public function test_record_incident_inserts_row()
    {
        global $wpdb;

        $captured = null;
        $wpdb->test_insert_callback = function ($table, $data) use (&$captured) {
            $captured = [$table, $data];
            return true;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 901;

        $id = OraBooks_Security::record_incident('access_denied', 'warning', ['status' => 403]);

        $this->assertEquals(901, $id);
        $this->assertNotNull($captured);
        $this->assertStringContainsString('security_incidents', $captured[0]);
        $this->assertEquals('access_denied', $captured[1]['incident_type']);
    }

    #[Test]
    public function test_get_rate_limit_config_returns_centralized_limits()
    {
        $config = OraBooks_Security::get_rate_limit_config();

        $this->assertArrayHasKey('registration_per_ip', $config);
        $this->assertEquals(5, $config['registration_per_ip']['max']);
        $this->assertArrayHasKey('export_per_user', $config);
    }

    #[Test]
    public function test_get_headers_status_reports_configured_headers()
    {
        $status = OraBooks_Security::get_headers_status();

        $this->assertArrayHasKey('configured', $status);
        $this->assertArrayHasKey('X-Frame-Options', $status['configured']);
        $this->assertArrayHasKey('X-Content-Type-Options', $status['configured']);
    }

    #[Test]
    public function test_get_owasp_catalog_has_all_ten_controls()
    {
        $catalog = OraBooks_Security::get_owasp_catalog();

        $this->assertCount(10, $catalog);
        $this->assertArrayHasKey('A01', $catalog);
        $this->assertArrayHasKey('A10', $catalog);
        $this->assertEquals('Broken Access Control', $catalog['A01']['control_name']);
        $this->assertEquals('SSRF', $catalog['A10']['control_name']);
        $this->assertNotEmpty($catalog['A01']['mitigations']);
    }

    #[Test]
    public function test_get_secret_rotation_status_returns_structure()
    {
        $status = OraBooks_Security::get_secret_rotation_status();

        $this->assertArrayHasKey('last_rotated', $status);
        $this->assertArrayHasKey('days_since', $status);
        $this->assertArrayHasKey('due', $status);
        $this->assertArrayHasKey('days_until', $status);
    }

    #[Test]
    public function test_store_scan_result_inserts_scan()
    {
        global $wpdb;

        $captured = null;
        $wpdb->test_insert_callback = function ($table, $data) use (&$captured) {
            $captured = [$table, $data];
            return true;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 502;

        $id = OraBooks_Security::store_scan_result('dependency_scan', 'pass', 'OK', ['vulnerabilities' => 0]);

        $this->assertEquals(502, $id);
        $this->assertStringContainsString('security_scan_results', $captured[0]);
        $this->assertEquals('dependency_scan', $captured[1]['scan_type']);
    }
}
