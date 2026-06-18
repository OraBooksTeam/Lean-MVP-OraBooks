<?php
/**
 * Unit Tests for OraBooks_Rest_Api + OpenAPI
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Rest_Api_Test extends TestCase
{
    #[Test]
    public function test_openapi_spec_loads_with_core_paths()
    {
        $spec = OraBooks_Rest_Api::load_openapi_spec();

        $this->assertSame('3.0.3', $spec['openapi'] ?? null);
        $this->assertArrayHasKey('/fiscal-periods', $spec['paths'] ?? []);
        $this->assertArrayHasKey('/expenses/upload-receipt', $spec['paths'] ?? []);
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

        $this->assertSame('SOFT_CLOSED', $formatted['status']);
        $this->assertSame(3, $formatted['id']);
    }
}
