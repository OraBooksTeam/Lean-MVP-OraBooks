<?php
/**
 * Unit Tests for OraBooks_Secrets
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Secrets_Test extends TestCase
{
 protected function setUp: void
 {
 parent::setUp;

 $this->reset_secrets_cache;
 $GLOBALS['orabooks_test_options'] = [];
 OraBooks_Secrets::set('jwt_secret', 'initial-jwt-secret-with-enough-length-for-tests');
 OraBooks_Secrets::set('encryption_key', 'initial-encryption-key-32chars-min');
 update_option('orabooks_jwt_secret_grace_until', time() + 3600);
 }

 private function reset_secrets_cache: void
 {
 $ref = new ReflectionClass(OraBooks_Secrets::class);
 foreach (['secrets_cache', 'access_logged', 'bootstrapped', 'bootstrap_error', 'file_secrets', 'instance'] as $property) {
 if (!$ref->hasProperty($property)) {
 continue;
 }
 $prop = $ref->getProperty($property);
 $prop->setAccessible(true);
 if ($property === 'bootstrapped') {
 $prop->setValue(null, false);
 } elseif ($property === 'instance') {
 $prop->setValue(null, null);
 } else {
 $prop->setValue(null, $property === 'bootstrap_error' ? null: []);
 }
 }
 }

 #[Test]
 public function test_mask_value_redacts_short_and_long_secrets() {
 $this->assertSame('****', OraBooks_Secrets::mask_value('short'));
 $this->assertSame('abcd…wxyz', OraBooks_Secrets::mask_value('abcdefghijklmnopwxyz'));
 }

 #[Test]
 public function test_redact_sensitive_removes_nested_secret_keys() {
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
 public function test_encrypt_sensitive_round_trip() {
 $encrypted = OraBooks_Secrets::encrypt_sensitive('totp-secret-value');
 $this->assertStringStartsWith('enc:', $encrypted);
 $this->assertSame('totp-secret-value', OraBooks_Secrets::decrypt_sensitive($encrypted));
 }

 #[Test]
 public function test_jwt_rotation_accepts_previous_secret_during_grace_period() {
 $payload = ['sub' => 42, 'type' => 'access'];
 $old_secret = OraBooks_Secrets::get_jwt_secret;
 $token = OraBooks_Secrets::generate_jwt($payload);

 OraBooks_Secrets::rotate_secret('jwt_secret', 'new-jwt-secret-with-enough-length-for-rotation-test');

 $this->assertSame($payload['sub'], OraBooks_Secrets::verify_jwt($token)['sub']);
 $this->assertNotSame($old_secret, OraBooks_Secrets::get_jwt_secret);
 }

 #[Test]
 public function test_check_tls_certificate_skips_localhost() {
 $result = OraBooks_Secrets::check_tls_certificate('localhost');

 $this->assertTrue($result['ok']);
 $this->assertTrue($result['skipped']);
 }

 #[Test]
 public function test_totp_round_trip_with_base32_secret() {
 $secret = OraBooks_Secrets::generate_totp_secret;
 $this->assertNotSame('', $secret);

 $time_slice = floor(time() / 30);
 $ref = new ReflectionClass(OraBooks_Secrets::class);
 $method = $ref->getMethod('generate_totp_code');
 $method->setAccessible(true);
 $decode = $ref->getMethod('decode_totp_secret');
 $decode->setAccessible(true);
 $key = $decode->invoke(null, $secret);
 $code = $method->invoke(null, $key, $time_slice);

 $this->assertSame(6, strlen($code));
 $this->assertTrue(OraBooks_Secrets::verify_totp($secret, $code));
 }

 #[Test]
 public function test_generate_jwt_respects_custom_expiry() {
 OraBooks_Secrets::set('jwt_secret', 'jwt-secret-with-enough-length-for-custom-exp-tests');
 $token = OraBooks_Secrets::generate_jwt([
 'user_id' => 1,
 'purpose' => '2fa_challenge',
 'exp' => time() + 300,
 ]);

 $payload = OraBooks_Secrets::verify_jwt($token);
 $this->assertIsArray($payload);
 $this->assertSame('2fa_challenge', $payload['purpose']);
 $this->assertLessThanOrEqual(time() + 301, (int) $payload['exp']);
 $this->assertGreaterThan(time() + 240, (int) $payload['exp']);
 }

 #[Test]
 public function test_get_hmac_signing_key_matches_jwt_secret() {
 $this->assertSame(OraBooks_Secrets::get_jwt_secret, OraBooks_Secrets::get_hmac_signing_key);
 }

 #[Test]
 public function test_check_database_tls_skips_non_production() {
 $off = static function() {
 return false;
 };
 add_filter('orabooks_is_production', $off);

 $result = OraBooks_Secrets::check_database_tls;

 $this->assertTrue($result['ok']);
 $this->assertTrue($result['skipped']);

 remove_filter('orabooks_is_production', $off);
 }

 #[Test]
 public function test_bootstrap_fails_in_production_when_secrets_invalid() {
 $prod = static function() {
 return true;
 };
 $db_tls = static function() {
 return true;
 };
 add_filter('orabooks_is_production', $prod);
 add_filter('orabooks_database_tls_verified', $db_tls);

 $this->reset_secrets_cache;
 OraBooks_Secrets::set('jwt_secret', 'short');
 OraBooks_Secrets::set('encryption_key', 'initial-encryption-key-32chars-min');

 $result = OraBooks_Secrets::bootstrap;

 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('secrets_invalid', $result->get_error_code());

 $this->reset_secrets_cache;
 OraBooks_Secrets::init;

 $this->assertFalse(OraBooks_Secrets::is_ready);
 $this->assertInstanceOf(WP_Error::class, OraBooks_Secrets::get_bootstrap_error);

 remove_filter('orabooks_is_production', $prod);
 remove_filter('orabooks_database_tls_verified', $db_tls);
 }

 #[Test]
 public function test_default_jwt_expiry_is_fifteen_minutes() {
 $this->assertSame(900, OraBooks_Secrets::get_default_jwt_expiry);
 }
}
