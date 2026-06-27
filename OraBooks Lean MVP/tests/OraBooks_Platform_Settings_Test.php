<?php
/**
 * Unit Tests for OraBooks platform settings AJAX handlers.
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Platform_Settings_Test extends TestCase
{
    private $ajax;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ajax = new OraBooks_Ajax();
        $GLOBALS['orabooks_test_current_user_can'] = true;
        $GLOBALS['orabooks_test_current_user_id'] = 1;
        $GLOBALS['orabooks_test_wp_remote_request_callback'] = null;
        $_POST = [];

        $this->resetOptions = [
            'orabooks_block_same_email_domain' => get_option('orabooks_block_same_email_domain', null),
            'orabooks_partner_commission_for_staff_viewer' => get_option('orabooks_partner_commission_for_staff_viewer', null),
            'orabooks_audit_retention_days' => get_option('orabooks_audit_retention_days', null),
            'orabooks_jwt_expiry' => get_option('orabooks_jwt_expiry', null),
            'orabooks_refresh_token_expiry' => get_option('orabooks_refresh_token_expiry', null),
            'orabooks_speech_webhook_url' => get_option('orabooks_speech_webhook_url', null),
            'orabooks_speech_webhook_token' => get_option('orabooks_speech_webhook_token', null),
            'orabooks_speech_webhook_model' => get_option('orabooks_speech_webhook_model', null),
            'orabooks_speech_webhook_healthcheck_enabled' => get_option('orabooks_speech_webhook_healthcheck_enabled', null),
            'orabooks_speech_webhook_health_url' => get_option('orabooks_speech_webhook_health_url', null),
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->resetOptions as $option => $value) {
            update_option($option, $value);
        }

        $GLOBALS['orabooks_test_wp_remote_request_callback'] = null;

        parent::tearDown();
    }

    private function callAjax(string $method): array
    {
        try {
            $this->ajax->$method();
            $this->fail("Expected RuntimeException (JSON response) was not thrown by {$method}");
        } catch (RuntimeException $exception) {
            return json_decode($exception->getMessage(), true);
        }
    }

    #[Test]
    public function test_platform_settings_get_includes_speech_webhook_fields()
    {
        update_option('orabooks_speech_webhook_url', 'https://speech.example.internal/transcribe');
        update_option('orabooks_speech_webhook_model', 'webhook-v1');
        update_option('orabooks_speech_webhook_healthcheck_enabled', 1);
        update_option('orabooks_speech_webhook_health_url', 'https://speech.example.internal/health');

        $response = $this->callAjax('ajax_platform_settings_get');

        $this->assertFalse($response['error']);
        $this->assertSame('https://speech.example.internal/transcribe', $response['data']['speech_webhook_url']);
        $this->assertSame('webhook-v1', $response['data']['speech_webhook_model']);
        $this->assertTrue((bool) $response['data']['speech_webhook_healthcheck_enabled']);
        $this->assertSame('https://speech.example.internal/health', $response['data']['speech_webhook_health_url']);
    }

    #[Test]
    public function test_platform_settings_save_persists_speech_webhook_fields()
    {
        $_POST = [
            'block_same_email_domain' => 1,
            'partner_commission_for_staff_viewer' => 1,
            'audit_retention_days' => 730,
            'jwt_expiry' => 1200,
            'refresh_token_expiry' => 86400,
            'speech_webhook_url' => 'https://speech.example.internal/transcribe',
            'speech_webhook_token' => 'secret-token',
            'speech_webhook_model' => 'whisper-large-v3',
            'speech_webhook_healthcheck_enabled' => 1,
            'speech_webhook_health_url' => 'https://speech.example.internal/health',
        ];

        $response = $this->callAjax('ajax_platform_settings_save');

        $this->assertFalse($response['error']);
        $this->assertSame('https://speech.example.internal/transcribe', get_option('orabooks_speech_webhook_url'));
        $this->assertSame('secret-token', get_option('orabooks_speech_webhook_token'));
        $this->assertSame('whisper-large-v3', get_option('orabooks_speech_webhook_model'));
        $this->assertSame(1, get_option('orabooks_speech_webhook_healthcheck_enabled'));
        $this->assertSame('https://speech.example.internal/health', get_option('orabooks_speech_webhook_health_url'));
        $this->assertSame('Settings saved', $response['message']);
    }

    #[Test]
    public function test_speech_webhook_check_returns_not_configured_without_webhook_url()
    {
        update_option('orabooks_speech_webhook_url', '');

        $response = $this->callAjax('ajax_speech_webhook_check');

        $this->assertFalse($response['error']);
        $this->assertFalse((bool) $response['data']['speech_webhook_configured']);
        $this->assertSame('not_configured', $response['data']['speech_webhook_health']['status']);
    }

    #[Test]
    public function test_speech_webhook_check_returns_up_when_health_endpoint_is_reachable()
    {
        update_option('orabooks_speech_webhook_url', 'https://speech.example.internal/transcribe');
        update_option('orabooks_speech_webhook_model', 'whisper-large-v3');
        update_option('orabooks_speech_webhook_healthcheck_enabled', 1);
        update_option('orabooks_speech_webhook_health_url', 'https://speech.example.internal/health');

        $GLOBALS['orabooks_test_wp_remote_request_callback'] = function ($url, $args) {
            if (strpos($url, '/health') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => wp_json_encode([
                        'status' => 'up',
                        'version' => 'whisper-large-v3',
                    ]),
                ];
            }

            return new WP_Error('unexpected_request', 'Unexpected URL in test');
        };

        $response = $this->callAjax('ajax_speech_webhook_check');

        $this->assertFalse($response['error']);
        $this->assertTrue((bool) $response['data']['speech_webhook_configured']);
        $this->assertSame('speech-webhook', $response['data']['speech_provider']);
        $this->assertSame('whisper-large-v3', $response['data']['speech_model_version']);
        $this->assertSame('up', $response['data']['speech_webhook_health']['status']);
        $this->assertSame('whisper-large-v3', $response['data']['speech_webhook_health']['version']);
    }
}
