<?php
/**
 * Unit Tests for OraBooks_TwoFactor
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_TwoFactor_Test extends TestCase
{
 protected function setUp: void
 {
 parent::setUp;

 $GLOBALS['orabooks_test_log_events'] = [];
 $GLOBALS['orabooks_test_user_meta'] = [];

 global $wpdb;
 $wpdb->test_get_row_callback = null;
 $wpdb->test_get_var_callback = null;
 $wpdb->test_get_results_callback = null;
 $wpdb->test_update_callback = null;
 $wpdb->test_query_callback = null;
 $wpdb->insert_id = 0;
 }

 #[Test]
 public function test_org_requires_2fa_reads_config_json() {
 global $wpdb;

 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'config') !== false) {
 return wp_json_encode(['require_2fa' => true]);
 }
 return null;
 };

 $this->assertTrue(OraBooks_TwoFactor::org_requires_2fa(5));
 }

 #[Test]
 public function test_user_needs_2fa_setup_when_org_requires_and_user_disabled() {
 global $wpdb;

 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'config') !== false) {
 return wp_json_encode(['require_2fa' => true]);
 }
 if (stripos($query, 'is_2fa_enabled') !== false) {
 return 0;
 }
 return null;
 };

 $this->assertTrue(OraBooks_TwoFactor::user_needs_2fa_setup(3, 5));
 }

 #[Test]
 public function test_disable_blocked_when_org_requires_2fa() {
 global $wpdb;

 $wpdb->test_get_row_callback = function {
 return (object) [
 'id' => 3,
 'org_id' => 5,
 'is_2fa_enabled' => 1,
 ];
 };
 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'config') !== false) {
 return wp_json_encode(['require_2fa' => true]);
 }
 return null;
 };

 $result = OraBooks_TwoFactor::disable(3, '123456');
 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('org_2fa_required', $result->get_error_code());
 }

 #[Test]
 public function test_admin_recover_requires_justification() {
 $result = OraBooks_TwoFactor::admin_recover(2, 1, ' ');
 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('justification_required', $result->get_error_code());
 }

 #[Test]
 public function test_admin_recover_platform_admin_succeeds() {
 global $wpdb;

 $GLOBALS['orabooks_test_current_user_can'] = true;
 $wpdb->test_get_row_callback = function {
 return (object) [
 'id' => 2,
 'org_id' => 5,
 'email' => 'locked@example.com',
 'is_2fa_enabled' => 1,
 ];
 };
 $wpdb->test_update_callback = function {
 return 1;
 };

 $result = OraBooks_TwoFactor::admin_recover(2, 1, 'User lost authenticator device');
 $this->assertIsArray($result);
 $this->assertFalse($result['is_2fa_enabled']);
 }

 #[Test]
 public function test_get_org_policy_reflects_config() {
 global $wpdb;

 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'config') !== false) {
 return wp_json_encode(['require_2fa' => true]);
 }
 return null;
 };

 $policy = OraBooks_TwoFactor::get_org_policy(5);
 $this->assertTrue($policy['require_2fa']);
 $this->assertSame(5, $policy['org_id']);
 }

 #[Test]
 public function test_assert_org_compliance_blocks_when_setup_required() {
 global $wpdb;

 $wpdb->test_get_var_callback = function ($query) {
 if (stripos($query, 'config') !== false) {
 return wp_json_encode(['require_2fa' => true]);
 }
 if (stripos($query, 'is_2fa_enabled') !== false) {
 return 0;
 }
 return null;
 };

 $result = OraBooks_TwoFactor::assert_org_compliance(3, 5);
 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('2fa_setup_required', $result->get_error_code());
 }

 #[Test]
 public function test_compliance_exempt_actions_include_2fa_setup() {
 $this->assertTrue(OraBooks_TwoFactor::is_2fa_compliance_exempt_action('orabooks_setup_2fa'));
 $this->assertTrue(OraBooks_TwoFactor::is_2fa_compliance_exempt_action('orabooks_reveal_2fa_backup_codes'));
 $this->assertFalse(OraBooks_TwoFactor::is_2fa_compliance_exempt_action('orabooks_customer_dashboard'));
 }

 #[Test]
 public function test_reveal_backup_codes_requires_valid_otp() {
 global $wpdb;

 $wpdb->test_get_row_callback = function {
 return (object) [
 'id' => 3,
 'org_id' => 5,
 'is_2fa_enabled' => 1,
 ];
 };

 $result = OraBooks_TwoFactor::reveal_backup_codes(3, '000000');
 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('invalid_otp', $result->get_error_code());
 }
}
