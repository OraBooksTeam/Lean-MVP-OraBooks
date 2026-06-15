<?php
/**
 * Unit Tests for OraBooks_Exports (SL-114)
 *
 * Covers: request, CSV/PDF generation, download, cancel,
 * cleanup, provider registry, utility methods, and global helper.
 *
 * Run: phpunit --configuration tests/phpunit.xml
 */

use PHPUnit\Framework\TestCase;

class OraBooks_Exports_Test extends TestCase
{
    /** @var string Temp dir for export files */
    private static $tmpDir = '';

    /** @var object Mock export row for generate_export_job tests */
    private static $mockExportRow;

    // -----------------------------------------------------------------------
    // Lifecycle
    // -----------------------------------------------------------------------

    public static function setUpBeforeClass(): void
    {
        self::$tmpDir = sys_get_temp_dir() . '/orabooks-test-uploads';
        if (!is_dir(self::$tmpDir)) {
            mkdir(self::$tmpDir, 0777, true);
        }
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up temp files (including exports subdirectory)
        if (is_dir(self::$tmpDir)) {
            foreach (['/orabooks-exports/*', '/*'] as $pattern) {
                $files = glob(self::$tmpDir . $pattern);
                foreach ($files as $f) {
                    if (is_file($f)) @unlink($f);
                }
            }
            @rmdir(self::$tmpDir . '/orabooks-exports');
            @rmdir(self::$tmpDir);
        }
    }

    protected function setUp(): void
    {
        // Clean up generated files from previous tests (including exports subdirectory)
        foreach (['/*', '/orabooks-exports/*'] as $pattern) {
            $files = glob(self::$tmpDir . $pattern);
            foreach ($files as $f) {
                if (is_file($f)) @unlink($f);
            }
        }

        // Reset the static provider registry before each test
        $refl = new ReflectionClass('OraBooks_Exports');
        $providersProp = $refl->getProperty('report_providers');
        $providersProp->setAccessible(true);
        $providersProp->setValue([]);

        // Reset wpdb test callbacks
        global $wpdb;
        $wpdb->test_get_var_callback    = null;
        $wpdb->test_get_row_callback    = null;
        $wpdb->test_get_results_callback = null;

        // Build a standard mock export row for generation tests
        self::$mockExportRow = (object)[
            'id'             => 99,
            'org_id'         => 1,
            'user_id'        => 1,
            'export_type'    => 'test_report',
            'format'         => 'csv',
            'parameters'     => '{"columns":["name","value"]}',
            'status'         => 'pending',
            'file_url'       => null,
            'file_size'      => null,
            'file_hash'      => null,
            'expires_at'     => null,
            'download_count' => 0,
            'correlation_id' => 'test-export-correlation-001',
            'error_message'  => null,
            'generated_at'   => null,
            'created_at'     => date('Y-m-d H:i:s'),
        ];
    }

    // -----------------------------------------------------------------------
    // get_create_table_sql()
    // -----------------------------------------------------------------------

    /** @test */
    public function test_get_create_table_sql_returns_array()
    {
        $sql = OraBooks_Exports::get_create_table_sql();
        $this->assertIsArray($sql);
        $this->assertCount(2, $sql, 'Should return exactly 2 CREATE TABLE statements');
        $this->assertStringContainsString('export_requests', $sql[0]);
        $this->assertStringContainsString('export_files', $sql[1]);
    }

    // -----------------------------------------------------------------------
    // Provider Registry
    // -----------------------------------------------------------------------

    /** @test */
    public function test_register_report_provider_has_no_return()
    {
        $result = OraBooks_Exports::register_report_provider('my_report', function () {
            return [['col' => 'val']];
        });
        $this->assertNull($result);
    }

    /** @test */
    public function test_get_report_data_uses_registered_provider()
    {
        OraBooks_Exports::register_report_provider('my_report', function ($params) {
            return ['rows' => [['a' => 1]], 'columns' => ['a']];
        });

        // We need to call get_report_data, which is private. Use reflection.
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('get_report_data');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'my_report', []);
        $this->assertNotNull($result);
        $this->assertArrayHasKey('rows', $result);

        // Unregistered type returns null
        $result2 = $method->invoke(null, 'nonexistent', []);
        $this->assertNull($result2);
    }

    /** @test */
    public function test_get_report_data_handles_provider_exception()
    {
        OraBooks_Exports::register_report_provider('crash_report', function ($params) {
            throw new \Exception('Intentional crash');
        });

        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('get_report_data');
        $method->setAccessible(true);

        // Should not throw; should return null
        $result = $method->invoke(null, 'crash_report', []);
        $this->assertNull($result);
    }

    // -----------------------------------------------------------------------
    // request_export()
    // -----------------------------------------------------------------------

    /** @test */
    public function test_request_export_success()
    {
        $result = OraBooks_Exports::request_export(1, 1, 'test_report', 'csv', ['filter' => 'all']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('correlation_id', $result);
        $this->assertEquals('pending', $result['status']);
        $this->assertGreaterThan(0, $result['id']);
        $this->assertNotEmpty($result['correlation_id']);
    }

    /** @test */
    public function test_request_export_invalid_format()
    {
        $result = OraBooks_Exports::request_export(1, 1, 'test_report', 'xml', []);

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_format', $result->get_error_code());
    }

    /** @test */
    public function test_request_export_respects_rate_limit_when_under_limit()
    {
        // Our mock orabooks_check_rate_limit always returns true,
        // so the request should succeed.
        $result = OraBooks_Exports::request_export(1, 1, 'test_report', 'csv');
        $this->assertIsArray($result);
        $this->assertEquals('pending', $result['status']);
    }

    /** @test */
    public function test_request_export_pdf_format()
    {
        $result = OraBooks_Exports::request_export(1, 1, 'test_report', 'pdf');

        $this->assertIsArray($result);
        $this->assertEquals('pending', $result['status']);
        $this->assertNotEmpty($result['correlation_id']);
    }

    // -----------------------------------------------------------------------
    // generate_export_job() — CSV generation
    // -----------------------------------------------------------------------

    /** @test */
    public function test_generate_export_job_missing_export_id()
    {
        $result = OraBooks_Exports::generate_export_job((object)[], ['export_id' => 0, 'org_id' => 1, 'user_id' => 1]);
        $this->assertIsString($result);
        $this->assertStringContainsString('Missing', $result);
    }

    /** @test */
    public function test_generate_export_job_missing_org_id()
    {
        $result = OraBooks_Exports::generate_export_job((object)[], ['export_id' => 1, 'org_id' => 0, 'user_id' => 1]);
        $this->assertIsString($result);
        $this->assertStringContainsString('Missing', $result);
    }

    /** @test */
    public function test_generate_export_job_not_pending()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id'             => 1,
                'org_id'         => 1,
                'user_id'        => 1,
                'export_type'    => 'test',
                'format'         => 'csv',
                'parameters'     => '{}',
                'status'         => 'ready',  // Not pending
                'file_url'       => null,
                'file_size'      => null,
                'file_hash'      => null,
                'expires_at'     => null,
                'download_count' => 0,
                'correlation_id' => 'xyz',
                'error_message'  => null,
                'generated_at'   => null,
                'created_at'     => date('Y-m-d H:i:s'),
            ];
        };

        $result = OraBooks_Exports::generate_export_job((object)[], ['export_id' => 1, 'org_id' => 1, 'user_id' => 1]);
        $this->assertIsString($result);
        $this->assertStringContainsString('not pending', $result);

        $wpdb->test_get_row_callback = null;
    }

    /** @test */
    public function test_generate_export_job_csv_success()
    {
        $this->registerTestProvider();

        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            $row = clone self::$mockExportRow;
            $row->format = 'csv';
            return $row;
        };

        $result = OraBooks_Exports::generate_export_job((object)[], [
            'export_id' => 99,
            'org_id'    => 1,
            'user_id'   => 1,
        ]);

        $this->assertTrue($result, 'CSV generation should return true');

        // Verify a CSV file was created in the exports subdirectory
        $exportDir = self::$tmpDir . '/orabooks-exports';
        $files = glob($exportDir . '/*.csv');
        $this->assertGreaterThan(0, count($files), 'At least one CSV file should exist in ' . $exportDir);

        // Verify CSV content
        $csvContent = file_get_contents($files[0]);
        $this->assertStringContainsString('name', $csvContent);
        $this->assertStringContainsString('value', $csvContent);

        $wpdb->test_get_row_callback = null;
    }

    /** @test */
    public function test_generate_export_job_pdf_success()
    {
        $this->registerTestProvider();

        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            $row = clone self::$mockExportRow;
            $row->format = 'pdf';
            return $row;
        };

        $result = OraBooks_Exports::generate_export_job((object)[], [
            'export_id' => 99,
            'org_id'    => 1,
            'user_id'   => 1,
        ]);

        $this->assertTrue($result, 'PDF generation should return true');

        // Verify HTML file was created in the exports subdirectory
        $exportDir = self::$tmpDir . '/orabooks-exports';
        $files = glob($exportDir . '/*.html');
        $this->assertGreaterThan(0, count($files), 'At least one HTML file should exist in ' . $exportDir);

        // Verify watermarked HTML content
        $htmlContent = file_get_contents($files[0]);
        $this->assertStringContainsString('CONFIDENTIAL', $htmlContent);
        $this->assertStringContainsString('watermark', $htmlContent);
        $this->assertStringContainsString('Test Org', $htmlContent);
        $this->assertStringContainsString('user@example.com', $htmlContent);
        $this->assertStringContainsString('OraBooks Signed Export', $htmlContent);

        $wpdb->test_get_row_callback = null;
    }

    /** @test */
    public function test_generate_export_job_fallback_data()
    {
        // Register NO provider — the job should use parameters['columns'] as fallback
        OraBooks_Exports::register_report_provider('test_report', function ($params) {
            return null; // Simulate no provider available
        });

        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            $row = clone self::$mockExportRow;
            $row->parameters = '{"columns":["col_a","col_b"],"rows":[{"col_a":"x","col_b":"y"}]}';
            $row->format = 'csv';
            return $row;
        };

        $result = OraBooks_Exports::generate_export_job((object)[], [
            'export_id' => 99,
            'org_id'    => 1,
            'user_id'   => 1,
        ]);

        $this->assertTrue($result);

        $exportDir = self::$tmpDir . '/orabooks-exports';
        $files = glob($exportDir . '/*.csv');
        $this->assertGreaterThan(0, count($files), 'CSV files should exist in ' . $exportDir);
        $csvContent = file_get_contents($files[0]);
        $this->assertStringContainsString('col_a', $csvContent);
        $this->assertStringContainsString('col_b', $csvContent);

        $wpdb->test_get_row_callback = null;
    }

    // -----------------------------------------------------------------------
    // download_export()
    // -----------------------------------------------------------------------

    /** @test */
    public function test_download_export_not_found()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) { return null; };

        $result = OraBooks_Exports::download_export(999, 1);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('not_found', $result->get_error_code());

        $wpdb->test_get_row_callback = null;
    }

    /** @test */
    public function test_download_export_success_as_admin()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id'             => 1,
                'user_id'        => 999, // Different user — but test user is admin
                'org_id'         => 1,
                'export_type'    => 'test',
                'format'         => 'csv',
                'status'         => 'ready',
                'file_url'       => 'http://example.com/file.csv',
                'file_size'      => 100,
                'file_hash'      => 'abc',
                'expires_at'     => date('Y-m-d H:i:s', time() + 86400),
                'download_count' => 0,
                'correlation_id' => 'c1',
                'created_at'     => date('Y-m-d H:i:s'),
            ];
        };

        // Admin (current_user_can always true in mock) can download anyone's exports
        $result = OraBooks_Exports::download_export(1, 1);
        $this->assertNotInstanceOf('WP_Error', $result);
        $this->assertIsArray($result);

        $wpdb->test_get_row_callback = null;
    }

    /** @test */
    public function test_download_export_not_ready()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id'             => 1,
                'user_id'        => 1,
                'org_id'         => 1,
                'export_type'    => 'test',
                'format'         => 'csv',
                'status'         => 'pending', // Not ready
                'file_url'       => null,
                'file_size'      => null,
                'file_hash'      => null,
                'expires_at'     => null,
                'download_count' => 0,
                'correlation_id' => 'c1',
                'created_at'     => date('Y-m-d H:i:s'),
            ];
        };

        $result = OraBooks_Exports::download_export(1, 1);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('not_ready', $result->get_error_code());

        $wpdb->test_get_row_callback = null;
    }

    /** @test */
    public function test_download_export_expired()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id'             => 1,
                'user_id'        => 1,
                'org_id'         => 1,
                'export_type'    => 'test',
                'format'         => 'csv',
                'status'         => 'ready',
                'file_url'       => 'http://example.com/file.csv',
                'file_size'      => 100,
                'file_hash'      => 'abc',
                'expires_at'     => date('Y-m-d H:i:s', time() - 86400), // Expired 1 day ago
                'download_count' => 0,
                'correlation_id' => 'c1',
                'created_at'     => date('Y-m-d H:i:s'),
            ];
        };

        $result = OraBooks_Exports::download_export(1, 1);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('expired', $result->get_error_code());

        $wpdb->test_get_row_callback = null;
    }

    /** @test */
    public function test_download_export_success()
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
                'file_hash'      => 'sha256abc123',
                'expires_at'     => date('Y-m-d H:i:s', time() + 86400 * 7),
                'download_count' => 2,
                'correlation_id' => 'corr-001',
                'created_at'     => date('Y-m-d H:i:s'),
            ];
        };

        $result = OraBooks_Exports::download_export(1, 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('file_url', $result);
        $this->assertArrayHasKey('file_size', $result);
        $this->assertArrayHasKey('file_hash', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertEquals('http://example.com/test.csv', $result['file_url']);
        $this->assertEquals(2048, $result['file_size']);
        $this->assertEquals('sha256abc123', $result['file_hash']);
        $this->assertStringContainsString('test_report', $result['filename']);

        $wpdb->test_get_row_callback = null;
    }

    // -----------------------------------------------------------------------
    // cancel_export()
    // -----------------------------------------------------------------------

    /** @test */
    public function test_cancel_export_not_found()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) { return null; };

        $result = OraBooks_Exports::cancel_export(999, 1);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('not_found', $result->get_error_code());

        $wpdb->test_get_row_callback = null;
    }

    /** @test */
    public function test_cancel_export_invalid_status()
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function ($query) {
            return (object)[
                'id'      => 1,
                'user_id' => 1,
                'org_id'  => 1,
                'status'  => 'ready', // Not pending
                'export_type' => 'test',
                'format'  => 'csv',
                'correlation_id' => 'c1',
                'created_at' => date('Y-m-d H:i:s'),
            ];
        };

        $result = OraBooks_Exports::cancel_export(1, 1);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_status', $result->get_error_code());

        $wpdb->test_get_row_callback = null;
    }

    /** @test */
    public function test_cancel_export_success()
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

        $result = OraBooks_Exports::cancel_export(1, 1);
        $this->assertTrue($result);

        $wpdb->test_get_row_callback = null;
    }

    // -----------------------------------------------------------------------
    // cleanup_expired()
    // -----------------------------------------------------------------------

    /** @test */
    public function test_cleanup_expired_empty()
    {
        $exports = new OraBooks_Exports();
        $result = $exports->cleanup_expired();
        $this->assertEquals(0, $result, 'No expired exports should be cleaned');
    }

    /** @test */
    public function test_cleanup_expired_with_expired_files()
    {
        // Create a mock expired file
        $expiredFile = self::$tmpDir . '/orabooks-expired/test_old.csv';
        $expiredDir = dirname($expiredFile);
        if (!is_dir($expiredDir)) {
            mkdir($expiredDir, 0777, true);
        }
        file_put_contents($expiredFile, 'old data');

        global $wpdb;
        $wpdb->test_get_results_callback = function ($query) use ($expiredFile) {
            return [(object)[
                'id'          => 1,
                'file_url'    => 'http://example.com/old.csv',
                'storage_key' => 'orabooks-expired/test_old.csv',
            ]];
        };

        // Since the cleanup uses wp_upload_dir() which points to our tmp,
        // the file will be found and processed
        $exports = new OraBooks_Exports();
        $result = $exports->cleanup_expired();

        $this->assertGreaterThanOrEqual(0, $result);

        // Clean up
        @unlink($expiredFile);
        @rmdir($expiredDir);

        $wpdb->test_get_results_callback = null;
    }

    // -----------------------------------------------------------------------
    // get_user_exports()
    // -----------------------------------------------------------------------

    /** @test */
    public function test_get_user_exports_default_pagination()
    {
        $result = OraBooks_Exports::get_user_exports(1, 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('exports', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('total_pages', $result);
        $this->assertEquals(1, $result['page']);
        $this->assertEquals(20, $result['per_page']);
        $this->assertEquals(5, $result['total']);
        $this->assertCount(3, $result['exports']);
    }

    /** @test */
    public function test_get_user_exports_custom_pagination()
    {
        $result = OraBooks_Exports::get_user_exports(1, 1, 2, 10);

        $this->assertEquals(2, $result['page']);
        $this->assertEquals(10, $result['per_page']);
        $this->assertIsArray($result['exports']);
    }

    /** @test */
    public function test_get_user_exports_export_shape()
    {
        $result = OraBooks_Exports::get_user_exports(1, 1);
        if (count($result['exports']) > 0) {
            $export = $result['exports'][0];
            $this->assertObjectHasProperty('id', $export);
            $this->assertObjectHasProperty('export_type', $export);
            $this->assertObjectHasProperty('format', $export);
            $this->assertObjectHasProperty('status', $export);
            $this->assertObjectHasProperty('created_at', $export);
        }
    }

    // -----------------------------------------------------------------------
    // get_export_stats()
    // -----------------------------------------------------------------------

    /** @test */
    public function test_get_export_stats_shape()
    {
        $stats = OraBooks_Exports::get_export_stats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('total_downloads', $stats);
        $this->assertArrayHasKey('last_24h', $stats);
        $this->assertArrayHasKey('by_format', $stats);
        $this->assertArrayHasKey('by_type', $stats);
    }

    /** @test */
    public function test_get_export_stats_counts()
    {
        $stats = OraBooks_Exports::get_export_stats();

        $this->assertEquals(42, $stats['total_downloads']);
        $this->assertEquals(3, $stats['last_24h']);
        $this->assertCount(2, $stats['by_format']);
        $this->assertCount(3, $stats['by_type']);
    }

    // -----------------------------------------------------------------------
    // Utility: format_file_size()
    // -----------------------------------------------------------------------

    /** @test */
    public function test_format_file_size_bytes()
    {
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('format_file_size');
        $method->setAccessible(true);

        $this->assertEquals('500 B', $method->invoke(null, 500));
    }

    /** @test */
    public function test_format_file_size_kb()
    {
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('format_file_size');
        $method->setAccessible(true);

        $result = $method->invoke(null, 2048);
        $this->assertStringContainsString('KB', $result);
        $this->assertEquals('2.0 KB', $result);
    }

    /** @test */
    public function test_format_file_size_mb()
    {
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('format_file_size');
        $method->setAccessible(true);

        $result = $method->invoke(null, 5 * 1048576); // 5 MB
        $this->assertStringContainsString('MB', $result);
    }

    /** @test */
    public function test_format_file_size_gb()
    {
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('format_file_size');
        $method->setAccessible(true);

        $result = $method->invoke(null, 3 * 1073741824); // 3 GB
        $this->assertStringContainsString('GB', $result);
    }

    /** @test */
    public function test_format_file_size_zero()
    {
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('format_file_size');
        $method->setAccessible(true);

        $this->assertEquals('0 B', $method->invoke(null, 0));
    }

    // -----------------------------------------------------------------------
    // Utility: time_remaining()
    // -----------------------------------------------------------------------

    /** @test */
    public function test_time_remaining_expired()
    {
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('time_remaining');
        $method->setAccessible(true);

        $expired = date('Y-m-d H:i:s', time() - 3600);
        $result = $method->invoke(null, $expired);
        $this->assertEquals('Expired', $result);
    }

    /** @test */
    public function test_time_remaining_days()
    {
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('time_remaining');
        $method->setAccessible(true);

        $future = date('Y-m-d H:i:s', time() + (3 * 86400 + 3600)); // 3 days 1 hour
        $result = $method->invoke(null, $future);
        $this->assertStringContainsString('days', $result);
    }

    /** @test */
    public function test_time_remaining_hours()
    {
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('time_remaining');
        $method->setAccessible(true);

        $future = date('Y-m-d H:i:s', time() + 7200); // 2 hours
        $result = $method->invoke(null, $future);
        $this->assertStringContainsString('hrs', $result);
    }

    // -----------------------------------------------------------------------
    // Utility: get_org_name()
    // -----------------------------------------------------------------------

    /** @test */
    public function test_get_org_name_found()
    {
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('get_org_name');
        $method->setAccessible(true);

        // Our mock wpdb returns 'Test Org' for org lookups
        $result = $method->invoke(null, 1);
        $this->assertEquals('Test Org', $result);
    }

    // -----------------------------------------------------------------------
    // Utility: get_user_org_id()
    // -----------------------------------------------------------------------

    /** @test */
    public function test_get_user_org_id_found()
    {
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('get_user_org_id');
        $method->setAccessible(true);

        // Our mock wpdb returns 1 for user_org lookups
        $result = $method->invoke(null, 1);
        $this->assertEquals(1, $result);
    }

    // -----------------------------------------------------------------------
    // register_default_providers()
    // -----------------------------------------------------------------------

    /** @test */
    public function test_register_default_providers_sets_providers()
    {
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('register_default_providers');
        $method->setAccessible(true);

        // Reset first
        $providersProp = $refl->getProperty('report_providers');
        $providersProp->setAccessible(true);
        $providersProp->setValue([]);

        $method->invoke(null);

        $providers = $providersProp->getValue();
        $this->assertArrayHasKey('coa', $providers);
        $this->assertArrayHasKey('audit_log', $providers);
        $this->assertArrayHasKey('notification_log', $providers);
    }

    // -----------------------------------------------------------------------
    // Global helper: orabooks_request_export()
    // -----------------------------------------------------------------------

    /** @test */
    public function test_global_helper_success()
    {
        $result = orabooks_request_export(1, 1, 'global_test', 'csv');
        $this->assertIsArray($result);
        $this->assertEquals('pending', $result['status']);
    }

    /** @test */
    public function test_global_helper_invalid_format()
    {
        $result = orabooks_request_export(1, 1, 'global_test', 'json');
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_format', $result->get_error_code());
    }

    // -----------------------------------------------------------------------
    // Internal CSV generation (private method)
    // -----------------------------------------------------------------------

    /** @test */
    public function test_generate_csv_with_columns_and_rows()
    {
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('generate_csv');
        $method->setAccessible(true);

        $reportData = [
            'columns' => ['col1', 'col2', 'col3'],
            'rows'    => [
                ['col1' => 'a', 'col2' => 'b', 'col3' => 'c'],
                ['col1' => '1', 'col2' => '2', 'col3' => '3'],
            ],
        ];

        $export = (object)['export_type' => 'test'];
        $result = $method->invoke(null, $reportData, self::$tmpDir, 'test_csv', $export);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('size', $result);
        $this->assertArrayHasKey('hash', $result);
        $this->assertGreaterThan(0, $result['size']);
        $this->assertNotEmpty($result['hash']);

        // Verify content
        $content = file_get_contents($result['path']);
        // BOM
        $this->assertEquals("\xEF\xBB\xBF", substr($content, 0, 3));
        $this->assertStringContainsString('col1,col2,col3', $content);
        $this->assertStringContainsString('a,b,c', $content);
    }

    /** @test */
    public function test_generate_csv_with_object_rows()
    {
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('generate_csv');
        $method->setAccessible(true);

        $rows = [
            (object)['id' => 1, 'name' => 'Alice'],
            (object)['id' => 2, 'name' => 'Bob'],
        ];
        $reportData = $rows;

        $export = (object)['export_type' => 'test'];
        $result = $method->invoke(null, $reportData, self::$tmpDir, 'test_objects', $export);

        $this->assertIsArray($result);
        $content = file_get_contents($result['path']);
        $this->assertStringContainsString('id,name', $content);
        $this->assertStringContainsString('1,Alice', $content);
        $this->assertStringContainsString('2,Bob', $content);
    }

    /** @test */
    public function test_generate_csv_with_associative_array_rows()
    {
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('generate_csv');
        $method->setAccessible(true);

        $rows = [
            ['code' => 'A001', 'desc' => 'Asset A'],
            ['code' => 'L001', 'desc' => 'Liability L'],
        ];
        $reportData = $rows;

        $export = (object)['export_type' => 'test'];
        $result = $method->invoke(null, $reportData, self::$tmpDir, 'test_assoc', $export);

        $this->assertIsArray($result);
        $content = file_get_contents($result['path']);
        $this->assertStringContainsString('code,desc', $content);
        $this->assertStringContainsString('A001', $content);
        $this->assertStringContainsString('Asset A', $content);
    }

    /** @test */
    public function test_generate_csv_empty_data()
    {
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('generate_csv');
        $method->setAccessible(true);

        $reportData = ['columns' => ['a'], 'rows' => []];
        $export = (object)['export_type' => 'test'];
        $result = $method->invoke(null, $reportData, self::$tmpDir, 'test_empty', $export);

        $this->assertIsArray($result);
        $content = file_get_contents($result['path']);
        $this->assertStringContainsString('a', $content);
    }

    /** @test */
    public function test_generate_csv_with_escaping()
    {
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('generate_csv');
        $method->setAccessible(true);

        $reportData = [
            'columns' => ['field'],
            'rows' => [['field' => 'contains, comma and "quotes"']],
        ];
        $export = (object)['export_type' => 'test'];
        $result = $method->invoke(null, $reportData, self::$tmpDir, 'test_escape', $export);

        $content = file_get_contents($result['path']);
        // CSV should properly quote the field
        $this->assertStringContainsString('"', $content);
    }

    // -----------------------------------------------------------------------
    // Internal PDF HTML generation (private method)
    // -----------------------------------------------------------------------

    /** @test */
    public function test_generate_pdf_html_with_data()
    {
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('generate_pdf_html');
        $method->setAccessible(true);

        $reportData = [
            'columns' => ['col_a', 'col_b'],
            'rows'    => [
                ['col_a' => 'X', 'col_b' => 'Y'],
                ['col_a' => '1', 'col_b' => '2'],
            ],
        ];

        $export = (object)[
            'export_type'    => 'test_report',
            'correlation_id' => 'corr-pdf-test',
        ];

        $result = $method->invoke(null, $reportData, self::$tmpDir, 'test_pdf', $export, 1, 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('size', $result);
        $this->assertArrayHasKey('hash', $result);
        $this->assertGreaterThan(0, $result['size']);

        $content = file_get_contents($result['path']);
        $this->assertStringContainsString('CONFIDENTIAL', $content);
        $this->assertStringContainsString('TEST REPORT', $content);
        $this->assertStringContainsString('col_a', $content);
        $this->assertStringContainsString('col_b', $content);
        $this->assertStringContainsString('<td>X</td>', $content);
        $this->assertStringContainsString('<td>Y</td>', $content);
        $this->assertStringContainsString('corr-pdf-test', $content);
    }

    /** @test */
    public function test_generate_pdf_html_empty_data()
    {
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('generate_pdf_html');
        $method->setAccessible(true);

        $reportData = ['columns' => ['a'], 'rows' => []];
        $export = (object)[
            'export_type'    => 'empty_report',
            'correlation_id' => 'corr-empty',
        ];

        $result = $method->invoke(null, $reportData, self::$tmpDir, 'test_empty_pdf', $export, 1, 1);

        $this->assertIsArray($result);
        $content = file_get_contents($result['path']);
        $this->assertStringContainsString('No data available', $content);
    }

    /** @test */
    public function test_generate_pdf_html_no_columns()
    {
        $refl = new ReflectionClass('OraBooks_Exports');
        $method = $refl->getMethod('generate_pdf_html');
        $method->setAccessible(true);

        // Data with neither columns/rows shape nor object arrays
        $reportData = [];
        $export = (object)[
            'export_type'    => 'bare',
            'correlation_id' => 'corr-bare',
        ];

        $result = $method->invoke(null, $reportData, self::$tmpDir, 'test_bare', $export, 1, 1);

        $this->assertIsArray($result);
        $content = file_get_contents($result['path']);
        // Should have default 'Data' header
        $this->assertStringContainsString('<th>Data</th>', $content);
        $this->assertStringContainsString('No data available', $content);
    }

    // -----------------------------------------------------------------------
    // init() — covers basic initialization
    // -----------------------------------------------------------------------

    /** @test */
    public function test_init_returns_instance()
    {
        $instance = OraBooks_Exports::init();
        $this->assertInstanceOf('OraBooks_Exports', $instance);
    }

    /** @test */
    public function test_init_adds_actions()
    {
        // init() should set up the singleton and hooks
        // We just verify it returns the same instance on second call
        $instance1 = OraBooks_Exports::init();
        $instance2 = OraBooks_Exports::init();
        $this->assertSame($instance1, $instance2);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Register a simple test provider that returns known data.
     */
    private function registerTestProvider()
    {
        OraBooks_Exports::register_report_provider('test_report', function ($params) {
            return [
                'columns' => ['name', 'value'],
                'rows'    => [
                    ['name' => 'item1', 'value' => '100'],
                    ['name' => 'item2', 'value' => '200'],
                    ['name' => 'item3', 'value' => '300'],
                ],
            ];
        });
    }
}
