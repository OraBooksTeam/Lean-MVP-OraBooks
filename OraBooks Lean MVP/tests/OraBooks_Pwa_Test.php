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
    }

    #[Test]
    public function test_extend_ajax_config_adds_pwa_block()
    {
        $config = OraBooks_Pwa::extend_ajax_config(['ajax_url' => '/wp-admin/admin-ajax.php']);

        $this->assertTrue($config['pwa']['enabled']);
        $this->assertStringContainsString('/wp-json/api/pwa/manifest', $config['pwa']['manifest_url']);
        $this->assertTrue($config['pwa']['offline_queue']);
    }
}
