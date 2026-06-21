<?php
/**
 * Unit Tests for OraBooks_Secrets (SL-008)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Secrets_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->reset_secrets_cache();
        $GLOBALS['orabooks_test_options'] = [];
        OraBooks_Secrets::set('jwt_secret', 'initial-jwt-secret-with-enough-length-for-tests');
        OraBooks_Secrets::set('encryption_key', 'initial-encryption-key-32chars-min');
        update_option('orabooks_jwt_secret_grace_until', time() + 3600);
    }

    private function reset_secrets_cache(): void
    {
        $ref = new ReflectionClass(OraBooks_Secrets::class);
        foreach (['secrets_cache', 'access_logged', 'bootstrapped'] as $property) {
            if (!$ref->hasProperty($property)) {
                continue;
            }
            $prop = $ref->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue(null, $property === 'bootstrapped' ? false : []);
        }
    }

    #[Test]
    public function test_mask_value_redacts_short_and_long_secrets()
    {
        $this->assertSame('****', OraBooks_Secrets::mask_value('short'));
        $this->assertSame('abcd…wxyz', OraBooks_Secrets::mask_value('abcdefghijklmnopwxyz'));
    }

    #[Test]
    public function test_redact_sensitive_removes_nested_secret_keys()
    {
        $redacted = OraBooks_Secrets::redact_sensitive([
            'user' => 'alice',
            'jwt_token' => 'abc123',
            'nested' => ['client_secret' => 'hidden'],
        ]);

        $this->assertSame('alice', $redacted['user']);
        $this->assertSame('[REDACTED]', $redacted['jwt_token']);
        $this->assertSame('[REDACTED]', $redacted['nested']['client_secret']);
    }

    #[Test]
    public function test_encrypt_sensitive_round_trip()
    {
        $encrypted = OraBooks_Secrets::encrypt_sensitive('totp-secret-value');
        $this->assertStringStartsWith('enc:', $encrypted);
        $this->assertSame('totp-secret-value', OraBooks_Secrets::decrypt_sensitive($encrypted));
    }

    #[Test]
    public function test_jwt_rotation_accepts_previous_secret_during_grace_period()
    {
        $payload = ['sub' => 42, 'type' => 'access'];
        $old_secret = OraBooks_Secrets::get_jwt_secret();
        $token = OraBooks_Secrets::generate_jwt($payload);

        OraBooks_Secrets::rotate_secret('jwt_secret', 'new-jwt-secret-with-enough-length-for-rotation-test');

        $this->assertSame($payload['sub'], OraBooks_Secrets::verify_jwt($token)['sub']);
        $this->assertNotSame($old_secret, OraBooks_Secrets::get_jwt_secret());
    }

    #[Test]
    public function test_check_tls_certificate_skips_localhost()
    {
        $result = OraBooks_Secrets::check_tls_certificate('localhost');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['skipped']);
    }

    #[Test]
    public function test_get_status_reports_configured_secrets()
    {
        $status = OraBooks_Secrets::get_status();

        $this->assertTrue($status['jwt_secret_configured']);
        $this->assertTrue($status['encryption_key_configured']);
        $this->assertArrayHasKey('tls', $status);
    }
}
