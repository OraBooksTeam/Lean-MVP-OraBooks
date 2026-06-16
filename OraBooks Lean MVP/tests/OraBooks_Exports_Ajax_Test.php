<?php
/**
 * Unit Tests for OraBooks_Exports AJAX Handler Methods (SL-114)
 *
 * Covers all 5 AJAX endpoints: request, list, download, cancel, stats.
 *
 * Run: phpunit --configuration tests/phpunit.xml
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Exports_Ajax_Test extends TestCase
{
    /** @var OraBooks_Exports Instance under test */
    private $exports;

    protected function setUp(): void
    {
        parent::setUp();

        // Fresh instance for each test
        $this->exports = new OraBooks_Exports();

        // Reset auth globals to default (authenticated admin)
        $GLOBALS['orabooks_test_current_user_id'] = 1;
        $GLOBALS['orabooks_test_current_user_can'] = true;
        $GLOBALS['orabooks_test_has_permission'] = true;

        // Reset superglobals
        $_POST = [];
        $_GET  = [];

        // Reset wpdb callbacks
        global $wpdb;
        $wpdb->test_get_var_callback    = null;
        $wpdb->test_get_row_callback    = null;
        $wpdb->test_get_results_callback = null;

        // Reset provider registry
        $refl = new ReflectionClass('OraBooks_Exports');
        $providersProp = $refl->getProperty('report_providers');
        $providersProp->setValue(null, []);
    }

    // ================================================================
    // Helper: call an AJAX handler and capture the JSON response
    // ================================================================

    /**
     * Invoke an AJAX handler and return the decoded JSON response.
     *
     * @param string $method  Handler method name (e.g. 'ajax_request_export')
     * @return array  Decoded JSON response from orabooks_json_error/success
     */
    private function callAjax(string $method): array
    {
        try {
            $this->exports->$method();
            // If no exception thrown, the handler didn't call json_error/success
            $this->fail("Expected RuntimeException (JSON response) was not thrown by {$method}");
        } catch (RuntimeException $e) {
            return json_decode($e->getMessage(), true);
        }
    }

    // ================================================================
    // ajax_request_export
    // ================================================================

    #[Test]
    public function test_ajax_request_export_no_auth()
    {
        $GLOBALS['orabooks_test_current_user_id'] = 0;
        $_POST['export_type'] = 'test';
        $_POST['format']      = 'csv';

        $response = $this->callAjax('ajax_request_export');

        $this->assertTrue($response['error']);
        $this->assertEquals('Authentication required', $response['message']);
    }

    #[Test]
    public function test_ajax_request_export_missing_type()
    {
        $_POST['format'] = 'csv';

        $response = $this->callAjax('ajax_request_export');

        $this->assertTrue($response['error']);
        $this->assertEquals('Export type required', $response['message']);
    }

    #[Test]
    public function test_ajax_request_export_no_org()
    {
        global $wpdb;
        // Make get_user_org_id return 0 (no org found)
        $wpdb->test_get_var_callback = function ($query) {
            return null; // No org
        };

        $_POST['export_type'] = 'test';
        $_POST['format']      = 'csv';

        $response = $this->callAjax('ajax_request_export');

        $this->assertTrue($response['error']);
        $this->assertEquals('No organization found', $response['message']);
    }

    #[Test]
    public function test_ajax_request_export_permission_denied()
    {
        $GLOBALS['orabooks_test_has_permission'] = false;

        $_POST['export_type'] = 'test';
        $_POST['format']      = 'csv';

        $response = $this->callAjax('ajax_request_export');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('export_reports', $response['message']);
    }

    #[Test]
    public function test_ajax_request_export_invalid_format_returns_error()
    {
        $_POST['export_type'] = 'test';
        $_POST['format']      = 'xml'; // Invalid format

        $response = $this->callAjax('ajax_request_export');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('Format must be csv or pdf', $response['message']);
    }

    #[Test]
    public function test_ajax_request_export_success()
    {
        $_POST['export_type'] = 'test_report';
        $_POST['format']      = 'csv';
        $_POST['parameters']  = json_encode(['filter' => 'all']);

        $response = $this->callAjax('ajax_request_export');

        $this->assertFalse($response['error']);
        $this->assertEquals('Export requested successfully', $response['message']);
        $this->assertIsArray($response['data']);
        $this->assertArrayHasKey('id', $response['data']);
        $this->assertArrayHasKey('status', $response['data']);
        $this->assertArrayHasKey('correlation_id', $response['data']);
        $this->assertEquals('pending', $response['data']['status']);
    }

    #[Test]
    public function test_ajax_request_export_defaults_to_csv()
    {
        $_POST['export_type'] = 'test_report';
        // No format specified — should default to 'csv'

        $response = $this->callAjax('ajax_request_export');

        $this->assertFalse($response['error']);
        $this->assertEquals('pending', $response['data']['status']);
    }

    #[Test]
    public function test_ajax_request_export_without_parameters()
    {
        $_POST['export_type'] = 'test_report';
        $_POST['format']      = 'pdf';
        // No parameters

        $response = $this->callAjax('ajax_request_export');

        $this->assertFalse($response['error']);
        $this->assertEquals('pending', $response['data']['status']);
    }

    // ================================================================
    // ajax_exports_list
    // ================================================================

    #[Test]
    public function test_ajax_exports_list_no_auth()
    {
        $GLOBALS['orabooks_test_current_user_id'] = 0;

        $response = $this->callAjax('ajax_exports_list');

        $this->assertTrue($response['error']);
        $this->assertEquals('Authentication required', $response['message']);
    }

    #[Test]
    public function test_ajax_exports_list_no_org()
    {
        global $wpdb;
        $wpdb->test_get_var_callback = function ($query) {
            return null; // No org
        };

        $response = $this->callAjax('ajax_exports_list');

        $this->assertFalse($response['error']);
        $this->assertIsArray($response['data']);
        $this->assertEquals([], $response['data']['exports']);
        $this->assertEquals(0, $response['data']['total']);
    }

    #[Test]
    public function test_ajax_exports_list_default_page()
    {
        $response = $this->callAjax('ajax_exports_list');

        $this->assertFalse($response['error']);
        $this->assertIsArray($response['data']);
        $this->assertArrayHasKey('exports', $response['data']);
        $this->assertArrayHasKey('total', $response['data']);
        $this->assertArrayHasKey('page', $response['data']);
        $this->assertArrayHasKey('total_pages', $response['data']);
        $this->assertEquals(1, $response['data']['page']); // Default page = 1
    }

    #[Test]
    public function test_ajax_exports_list_custom_page()
    {
        $_GET['page'] = 2;

        $response = $this->callAjax('ajax_exports_list');

        $this->assertFalse($response['error']);
        $this->assertEquals(2, $response['data']['page']);
    }

    #[Test]
    public function test_ajax_exports_list_export_shape()
    {
        $response = $this->callAjax('ajax_exports_list');

        $this->assertFalse($response['error']);
        if (count($response['data']['exports']) > 0) {
            $export = $response['data']['exports'][0];
            $this->assertArrayHasKey('id', $export);
            $this->assertArrayHasKey('export_type', $export);
            $this->assertArrayHasKey('format', $export);
            $this->assertArrayHasKey('status', $export);
            $this->assertArrayHasKey('file_url', $export);
            $this->assertArrayHasKey('file_size', $export);
            $this->assertArrayHasKey('file_hash', $export);
            $this->assertArrayHasKey('can_download', $export);
            $this->assertArrayHasKey('can_cancel', $export);
            $this->assertArrayHasKey('created_at', $export);
        }
    }

    #[Test]
    public function test_ajax_exports_list_page_at_least_one()
    {
        // Even if page is 0 or negative, it should be clamped to 1
        $_GET['page'] = -1;

        $response = $this->callAjax('ajax_exports_list');

        $this->assertFalse($response['error']);
        $this->assertEquals(1, $response['data']['page'], 'Page should be clamped to minimum 1');
    }

    // ================================================================
    // ajax_download_export
    // ================================================================

    #[Test]
    public function test_ajax_download_export_no_auth()
    {
        $GLOBALS['orabooks_test_current_user_id'] = 0;

        $response = $this->callAjax('ajax_download_export');

        $this->assertTrue($response['error']);
        $this->assertEquals('Authentication required', $response['message']);
    }

    #[Test]
    public function test_ajax_download_export_missing_id()
    {
        // No export_id in $_GET

        $response = $this->callAjax('ajax_download_export');

        $this->assertTrue($response['error']);
        $this->assertEquals('Export ID required', $response['message']);
    }

    #[Test]
    public function test_ajax_download_export_not_found()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) { return null; };

        $_GET['export_id'] = 999;

        $response = $this->callAjax('ajax_download_export');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('not found', $response['message']);
    }

    #[Test]
    public function test_ajax_download_export_success()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id'             => 1,
                'user_id'        => 1,
                'org_id'         => 1,
                'export_type'    => 'test_report',
                'format'         => 'csv',
                'status'         => 'ready',
                'file_url'       => 'http://example.com/test.csv',
                'file_size'      => 2048,
                'file_hash'      => 'sha256abc',
                'expires_at'     => date('Y-m-d H:i:s', time() + 86400 * 7),
                'download_count' => 0,
                'correlation_id' => 'corr-001',
                'created_at'     => date('Y-m-d H:i:s'),
            ];
        };

        $_GET['export_id'] = 1;

        $response = $this->callAjax('ajax_download_export');

        $this->assertFalse($response['error']);
        $this->assertArrayHasKey('file_url', $response['data']);
        $this->assertArrayHasKey('file_size', $response['data']);
        $this->assertArrayHasKey('file_hash', $response['data']);
        $this->assertArrayHasKey('filename', $response['data']);
        $this->assertEquals('http://example.com/test.csv', $response['data']['file_url']);
        $this->assertEquals(2048, $response['data']['file_size']);
    }

    #[Test]
    public function test_ajax_download_export_not_ready()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id'             => 1,
                'user_id'        => 1,
                'org_id'         => 1,
                'export_type'    => 'test',
                'format'         => 'csv',
                'status'         => 'pending',
                'file_url'       => null,
                'file_size'      => null,
                'file_hash'      => null,
                'expires_at'     => null,
                'download_count' => 0,
                'correlation_id' => 'c1',
                'created_at'     => date('Y-m-d H:i:s'),
            ];
        };

        $_GET['export_id'] = 1;

        $response = $this->callAjax('ajax_download_export');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('not ready', $response['message']);
    }

    // ================================================================
    // ajax_cancel_export
    // ================================================================

    #[Test]
    public function test_ajax_cancel_export_no_auth()
    {
        $GLOBALS['orabooks_test_current_user_id'] = 0;

        $response = $this->callAjax('ajax_cancel_export');

        $this->assertTrue($response['error']);
        $this->assertEquals('Authentication required', $response['message']);
    }

    #[Test]
    public function test_ajax_cancel_export_missing_id()
    {
        $response = $this->callAjax('ajax_cancel_export');

        $this->assertTrue($response['error']);
        $this->assertEquals('Export ID required', $response['message']);
    }

    #[Test]
    public function test_ajax_cancel_export_not_found()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) { return null; };

        $_POST['export_id'] = 999;

        $response = $this->callAjax('ajax_cancel_export');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('not found', $response['message']);
    }

    #[Test]
    public function test_ajax_cancel_export_success()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id'      => 1,
                'user_id' => 1,
                'org_id'  => 1,
                'status'  => 'pending',
                'export_type' => 'test',
                'format'  => 'csv',
                'correlation_id' => 'c1',
                'created_at' => date('Y-m-d H:i:s'),
            ];
        };

        $_POST['export_id'] = 1;

        $response = $this->callAjax('ajax_cancel_export');

        $this->assertFalse($response['error']);
        $this->assertEquals('Export cancelled', $response['message']);
    }

    #[Test]
    public function test_ajax_cancel_export_invalid_status()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id'      => 1,
                'user_id' => 1,
                'org_id'  => 1,
                'status'  => 'ready',
                'export_type' => 'test',
                'format'  => 'csv',
                'correlation_id' => 'c1',
                'created_at' => date('Y-m-d H:i:s'),
            ];
        };

        $_POST['export_id'] = 1;

        $response = $this->callAjax('ajax_cancel_export');

        $this->assertTrue($response['error']);
        $this->assertStringContainsString('pending', $response['message']);
    }

    // ================================================================
    // ajax_exports_stats
    // ================================================================

    #[Test]
    public function test_ajax_exports_stats_permission_denied()
    {
        $GLOBALS['orabooks_test_current_user_can'] = false;

        $response = $this->callAjax('ajax_exports_stats');

        $this->assertTrue($response['error']);
        $this->assertEquals('Permission denied', $response['message']);
    }

    #[Test]
    public function test_ajax_exports_stats_success()
    {
        $response = $this->callAjax('ajax_exports_stats');

        $this->assertFalse($response['error']);
        $this->assertIsArray($response['data']);
        $this->assertArrayHasKey('total', $response['data']);
        $this->assertArrayHasKey('total_downloads', $response['data']);
        $this->assertArrayHasKey('last_24h', $response['data']);
        $this->assertArrayHasKey('by_format', $response['data']);
        $this->assertArrayHasKey('by_type', $response['data']);
    }

    #[Test]
    public function test_ajax_exports_stats_counts()
    {
        $response = $this->callAjax('ajax_exports_stats');

        $this->assertEquals(42, $response['data']['total_downloads']);
        $this->assertEquals(3, $response['data']['last_24h']);
        $this->assertCount(2, $response['data']['by_format']);
        $this->assertCount(3, $response['data']['by_type']);
    }
}
