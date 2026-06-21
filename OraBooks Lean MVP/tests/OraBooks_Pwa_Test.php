<?php
/**
 * Unit Tests for OraBooks_Pwa
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Pwa_Test extends TestCase
{
    #[Test]
    public function test_manifest_contains_required_fields()
    {
        $manifest = OraBooks_Pwa::get_manifest();

        $this->assertSame('OraBooks', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertNotEmpty($manifest['icons']);
        $this->assertNotEmpty($manifest['shortcuts']);
        $this->assertSame(home_url('/dashboard/'), $manifest['start_url']);
        $this->assertSame(home_url('/'), $manifest['scope']);
    }

    #[Test]
    public function test_extend_ajax_config_adds_pwa_block()
    {
        $config = OraBooks_Pwa::extend_ajax_config(['ajax_url' => '/wp-admin/admin-ajax.php']);

        $this->assertTrue($config['pwa']['enabled']);
        $this->assertStringContainsString('/wp-json/api/pwa/manifest', $config['pwa']['manifest_url']);
        $this->assertStringContainsString('/wp-json/api/pwa/service-worker', $config['pwa']['service_worker_url']);
        $this->assertSame(home_url('/'), $config['pwa']['service_worker_scope']);
        $this->assertTrue($config['pwa']['offline_queue']);
    }

    #[Test]
    public function test_rest_service_worker_serves_javascript_with_allowed_header()
    {
        $response = OraBooks_Pwa::rest_service_worker();

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $this->assertStringContainsString('install', (string) $response->get_data());
        $this->assertSame('application/javascript; charset=utf-8', $response->get_headers()['Content-Type']);
        $this->assertSame('/', $response->get_headers()['Service-Worker-Allowed']);
    }
}
