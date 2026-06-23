<?php
/**
 * OraBooks Two-Factor Authentication service
 *
 * TOTP setup, challenge, disable, backup codes, admin recovery, org-wide policy.
 */

if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_TwoFactor {

 private static $instance = null;

 public static function init() {
 if (self::$instance !== null) {
 return self::$instance;
 }

 self::$instance = new self;

 add_action('init', [self::$instance, 'maybe_enforce_ajax_2fa_compliance'], 1);

 add_action('wp_ajax_orabooks_disable_2fa', [self::$instance, 'ajax_disable_2fa']);
 add_action('wp_ajax_orabooks_regenerate_2fa_backup_codes', [self::$instance, 'ajax_regenerate_backup_codes']);
 add_action('wp_ajax_orabooks_reveal_2fa_backup_codes', [self::$instance, 'ajax_reveal_backup_codes']);
 add_action('wp_ajax_orabooks_2fa_status', [self::$instance, 'ajax_status']);
 add_action('wp_ajax_orabooks_admin_2fa_recover', [self::$instance, 'ajax_admin_recover']);
 add_action('wp_ajax_orabooks_org_2fa_policy_get', [self::$instance, 'ajax_org_policy_get']);
 add_action('wp_ajax_orabooks_org_2fa_policy_set', [self::$instance, 'ajax_org_policy_set']);

 return self::$instance;
 }

 /**
 * @return array<string, mixed>|WP_Error
 */
 public static function setup($orabooks_user_id) {
 global $wpdb;

 $orabooks_user_id = (int) $orabooks_user_id;
 if ($orabooks_user_id <= 0) {
 return new WP_Error('unauthorized', 'Authentication required', ['status' => 401]);
 }

 $table_users = OraBooks_Database::table('users');
 $user = $wpdb->get_row($wpdb->prepare(
 "SELECT id, email, is_2fa_enabled FROM {$table_users} WHERE id = %d",
 $orabooks_user_id
 ));

 if (!$user) {
 return new WP_Error('user_not_found', 'User not found', ['status' => 404]);
 }

 if (!empty($user->is_2fa_enabled)) {
 return new WP_Error('2fa_already_enabled', 'Two-factor authentication is already enabled', ['status' => 400]);
 }

 $wp_user_id = orabooks_ensure_wp_user_link_for_orabooks_user($orabooks_user_id);
 if ($wp_user_id <= 0) {
 return new WP_Error(
 'wp_user_link_failed',
 'Unable to link a WordPress account for 2FA setup. Contact support if this persists.',
 ['status' => 400]
 );
 }

 $secret = OraBooks_Secrets::generate_totp_secret;
 orabooks_set_2fa_temp_secret($wp_user_id, $secret);

 $email = get_userdata($wp_user_id)->user_email ?? (string) $user->email;
 $backup_codes = OraBooks_Secrets::generate_backup_codes;
 orabooks_set_2fa_temp_backup_codes($wp_user_id, $backup_codes);

 return [
 'secret' => $secret,
 'otpauth_uri' => OraBooks_Secrets::get_totp_provisioning_uri($secret, $email),
 'qr_code_url' => OraBooks_Secrets::get_totp_qr_url($secret, $email),
 'backup_codes' => $backup_codes,
 ];
 }

 /**
 * @return array<string, mixed>|WP_Error
 */
 public static function verify_setup($orabooks_user_id, $otp_code) {
 global $wpdb;

 $orabooks_user_id = (int) $orabooks_user_id;
 $wp_user_id = orabooks_ensure_wp_user_link_for_orabooks_user($orabooks_user_id);
 if ($wp_user_id <= 0) {
 return new WP_Error(
 'wp_user_link_failed',
 'Unable to link a WordPress account for 2FA setup. Contact support if this persists.',
 ['status' => 400]
 );
 }

 $table_users = OraBooks_Database::table('users');
 $user = $wpdb->get_row($wpdb->prepare(
 "SELECT id, is_2fa_enabled FROM {$table_users} WHERE id = %d",
 $orabooks_user_id
 ));

 if (!$user) {
 return new WP_Error('user_not_found', 'User not found', ['status' => 404]);
 }

 if (!empty($user->is_2fa_enabled)) {
 return new WP_Error('2fa_already_enabled', 'Two-factor authentication is already enabled', ['status' => 400]);
 }

 $otp = OraBooks_Secrets::normalize_totp_code($otp_code);
 $temp_secret = orabooks_get_2fa_temp_secret($wp_user_id);
 if ($temp_secret === '') {
 return new WP_Error('2fa_setup_not_initiated', '2FA setup not initiated', ['status' => 400]);
 }

 if (!OraBooks_Secrets::verify_totp($temp_secret, $otp)) {
 return new WP_Error('invalid_otp', 'Invalid OTP code', ['status' => 400]);
 }

 orabooks_set_2fa_secret($wp_user_id, $temp_secret);
 delete_user_meta($wp_user_id, 'orabooks_2fa_temp_secret');

 $backup_codes = orabooks_get_2fa_temp_backup_codes($wp_user_id);
 if ($backup_codes) {
 self::persist_backup_codes($orabooks_user_id, $backup_codes);
 delete_user_meta($wp_user_id, 'orabooks_2fa_temp_backup_codes');
 }

 $wpdb->update(
 $table_users,
 ['is_2fa_enabled' => 1],
 ['id' => $orabooks_user_id],
 ['%d'],
 ['%d']
 );

 orabooks_log_event('2fa_enabled', "2FA enabled for user {$orabooks_user_id}", 'info', [], $orabooks_user_id, null);

 return [
 'backup_codes' => $backup_codes,
 'is_2fa_enabled' => true,
 ];
 }

 /**
 * @return array<string, mixed>|WP_Error
 */
 public static function challenge($temp_token, $otp_code, $backup_code) {
 $temp_token = sanitize_text_field((string) $temp_token);
 $otp = OraBooks_Secrets::normalize_totp_code($otp_code);
 $backup_code = orabooks_normalize_backup_code($backup_code);

 $payload = OraBooks_Secrets::verify_jwt($temp_token);
 if (!$payload || ($payload['purpose'] ?? '') !== '2fa_challenge') {
 return new WP_Error('invalid_challenge_token', 'Invalid or expired challenge token', ['status' => 401]);
 }

 $user_id = (int) ($payload['user_id'] ?? 0);
 $rate_key = '2fa_challenge_'. orabooks_get_client_ip(). '_'. $user_id;

 $wp_user_id = orabooks_get_wp_user_id_for_orabooks_user($user_id);
 if ($wp_user_id <= 0) {
 $wp_user_id = orabooks_ensure_wp_user_link_for_orabooks_user($user_id);
 }

 $secret = orabooks_get_2fa_secret($wp_user_id);
 if ($secret === '') {
 return new WP_Error('2fa_not_configured', 'Two-factor authentication is not configured for this account', ['status' => 400]);
 }

 $verified = false;
 $method = '';

 if ($otp !== '' && OraBooks_Secrets::verify_totp($secret, $otp)) {
 $verified = true;
 $method = 'totp';
 } elseif ($backup_code !== '') {
 global $wpdb;
 $table = OraBooks_Database::table('2fa_backup_codes');
 $stored = $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM {$table} WHERE user_id = %d AND used = 0",
 $user_id
 ));

 foreach ($stored ?: [] as $row) {
 if (OraBooks_Secrets::verify_password($backup_code, $row->code_hash)) {
 $wpdb->update(
 $table,
 ['used' => 1],
 ['id' => $row->id],
 ['%d'],
 ['%d']
 );
 $verified = true;
 $method = 'backup_code';
 break;
 }
 }
 }

 if (!$verified) {
 if (!orabooks_check_rate_limit($rate_key, 5, 900)) {
 orabooks_log_event('login_failure', '2FA challenge rate limit exceeded', 'warning', [], $user_id, null);
 return new WP_Error('rate_limit', 'Too many failed verification attempts. Try again after 15 minutes.', ['status' => 429]);
 }

 orabooks_log_event('login_failure', 'Invalid 2FA verification attempt', 'warning', [
 'method' => $otp !== '' ? 'totp': 'backup_code',
 ], $user_id, null);

 return new WP_Error(
 'invalid_verification',
 $backup_code !== '' ? 'Invalid or already used backup code': 'Invalid verification code',
 ['status' => 401]
 );
 }

 global $wpdb;
 $table_users = OraBooks_Database::table('users');
 $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_users} WHERE id = %d", $user_id));
 if (!$user) {
 return new WP_Error('user_not_found', 'User not found', ['status' => 404]);
 }

 if (!$user->is_2fa_enabled) {
 return new WP_Error('2fa_not_enabled', 'Two-factor authentication is not enabled for this account', ['status' => 400]);
 }

 $expected_subdomain = (string) ($payload['expected_subdomain'] ?? '');
 $login_result = OraBooks_Auth::complete_authenticated_login($user, $expected_subdomain, [
 'via_2fa' => true,
 'auth_method' => $method,
 ]);
 if (is_wp_error($login_result)) {
 return $login_result;
 }

 orabooks_log_event(
 'login_success',
 "2FA login successful for user {$user_id}",
 'info',
 ['method' => $method],
 $user_id,
 $user->org_id
 );

 if (empty($login_result['needs_tier_selection']) && empty($login_result['needs_accept_invite'])) {
 orabooks_persist_login_session($login_result);
 }

 return $login_result;
 }

 /**
 * @return array<string, mixed>|WP_Error
 */
 public static function disable($orabooks_user_id, $otp_code) {
 global $wpdb;

 $orabooks_user_id = (int) $orabooks_user_id;
 $table_users = OraBooks_Database::table('users');
 $user = $wpdb->get_row($wpdb->prepare(
 "SELECT id, org_id, is_2fa_enabled FROM {$table_users} WHERE id = %d",
 $orabooks_user_id
 ));

 if (!$user || empty($user->is_2fa_enabled)) {
 return new WP_Error('2fa_not_enabled', 'Two-factor authentication is not enabled', ['status' => 400]);
 }

 if ((int) $user->org_id > 0 && self::org_requires_2fa((int) $user->org_id)) {
 return new WP_Error(
 'org_2fa_required',
 'Organization policy requires two-factor authentication. Contact an administrator to recover access.',
 ['status' => 403]
 );
 }

 $wp_user_id = orabooks_get_wp_user_id_for_orabooks_user($orabooks_user_id);
 $secret = $wp_user_id > 0 ? orabooks_get_2fa_secret($wp_user_id): '';
 $otp = OraBooks_Secrets::normalize_totp_code($otp_code);

 if ($secret === '' || !OraBooks_Secrets::verify_totp($secret, $otp)) {
 return new WP_Error('invalid_otp', 'Invalid OTP code', ['status' => 400]);
 }

 self::clear_2fa_credentials($orabooks_user_id, $wp_user_id);

 $wpdb->update(
 $table_users,
 ['is_2fa_enabled' => 0],
 ['id' => $orabooks_user_id],
 ['%d'],
 ['%d']
 );

 orabooks_log_event('2fa_disabled', "2FA disabled for user {$orabooks_user_id}", 'info', [], $orabooks_user_id, $user->org_id);

 return ['is_2fa_enabled' => false];
 }

 /**
 * @return array<string, mixed>|WP_Error
 */
 public static function regenerate_backup_codes($orabooks_user_id, $otp_code) {
 global $wpdb;

 $orabooks_user_id = (int) $orabooks_user_id;
 $table_users = OraBooks_Database::table('users');
 $user = $wpdb->get_row($wpdb->prepare(
 "SELECT id, org_id, is_2fa_enabled FROM {$table_users} WHERE id = %d",
 $orabooks_user_id
 ));

 if (!$user || empty($user->is_2fa_enabled)) {
 return new WP_Error('2fa_not_enabled', 'Two-factor authentication is not enabled', ['status' => 400]);
 }

 $wp_user_id = orabooks_get_wp_user_id_for_orabooks_user($orabooks_user_id);
 $secret = $wp_user_id > 0 ? orabooks_get_2fa_secret($wp_user_id): '';
 $otp = OraBooks_Secrets::normalize_totp_code($otp_code);

 if ($secret === '' || !OraBooks_Secrets::verify_totp($secret, $otp)) {
 return new WP_Error('invalid_otp', 'Invalid OTP code', ['status' => 400]);
 }

 $backup_codes = OraBooks_Secrets::generate_backup_codes;
 self::persist_backup_codes($orabooks_user_id, $backup_codes);

 orabooks_log_event(
 '2fa_backup_codes_regenerated',
 "Backup codes regenerated for user {$orabooks_user_id}",
 'info',
 [],
 $orabooks_user_id,
 $user->org_id
 );

 return [
 'backup_codes' => $backup_codes,
 'remaining_backup_codes' => count($backup_codes),
 ];
 }

 /**
 * Re-display unused backup codes after OTP verification (no regeneration).
 *
 * @return array<string, mixed>|WP_Error
 */
 public static function reveal_backup_codes($orabooks_user_id, $otp_code) {
 global $wpdb;

 $orabooks_user_id = (int) $orabooks_user_id;
 $table_users = OraBooks_Database::table('users');
 $user = $wpdb->get_row($wpdb->prepare(
 "SELECT id, org_id, is_2fa_enabled FROM {$table_users} WHERE id = %d",
 $orabooks_user_id
 ));

 if (!$user || empty($user->is_2fa_enabled)) {
 return new WP_Error('2fa_not_enabled', 'Two-factor authentication is not enabled', ['status' => 400]);
 }

 $wp_user_id = orabooks_get_wp_user_id_for_orabooks_user($orabooks_user_id);
 $secret = $wp_user_id > 0 ? orabooks_get_2fa_secret($wp_user_id): '';
 $otp = OraBooks_Secrets::normalize_totp_code($otp_code);

 if ($secret === '' || !OraBooks_Secrets::verify_totp($secret, $otp)) {
 return new WP_Error('invalid_otp', 'Invalid OTP code', ['status' => 400]);
 }

 $stored_codes = $wp_user_id > 0 ? orabooks_get_2fa_backup_codes_encrypted($wp_user_id): [];
 if (empty($stored_codes)) {
 return new WP_Error(
 'backup_codes_unavailable',
 'Backup codes cannot be retrieved. Regenerate new codes if needed.',
 ['status' => 404]
 );
 }

 $unused_codes = self::filter_unused_backup_codes($orabooks_user_id, $stored_codes);
 if (empty($unused_codes)) {
 return new WP_Error('no_backup_codes_remaining', 'No unused backup codes remain', ['status' => 404]);
 }

 orabooks_log_event(
 '2fa_backup_codes_revealed',
 "Backup codes viewed for user {$orabooks_user_id}",
 'info',
 [],
 $orabooks_user_id,
 $user->org_id
 );

 return [
 'backup_codes' => $unused_codes,
 'remaining_backup_codes' => count($unused_codes),
 ];
 }

 /**
 * @param string[] $codes
 * @return string[]
 */
 private static function filter_unused_backup_codes($orabooks_user_id, array $codes) {
 global $wpdb;

 $table = OraBooks_Database::table('2fa_backup_codes');
 $rows = $wpdb->get_results($wpdb->prepare(
 "SELECT code_hash FROM {$table} WHERE user_id = %d AND used = 0",
 (int) $orabooks_user_id
 ));

 if (empty($rows)) {
 return [];
 }

 $unused = [];
 foreach ($codes as $code) {
 $normalized = orabooks_normalize_backup_code($code);
 foreach ($rows as $row) {
 if (OraBooks_Secrets::verify_password($normalized, $row->code_hash)) {
 $unused[] = $code;
 break;
 }
 }
 }

 return $unused;
 }

 /**
 * @return array<string, mixed>
 */
 public static function get_status($orabooks_user_id) {
 global $wpdb;

 $orabooks_user_id = (int) $orabooks_user_id;
 $table_users = OraBooks_Database::table('users');
 $user = $wpdb->get_row($wpdb->prepare(
 "SELECT id, org_id, is_2fa_enabled FROM {$table_users} WHERE id = %d",
 $orabooks_user_id
 ));

 $org_id = $user ? (int) $user->org_id: 0;

 return [
 'is_2fa_enabled' => (bool) ($user->is_2fa_enabled ?? false),
 'remaining_backup_codes' => self::count_remaining_backup_codes($orabooks_user_id),
 'org_require_2fa' => $org_id > 0 ? self::org_requires_2fa($org_id): false,
 'needs_2fa_setup' => self::user_needs_2fa_setup($orabooks_user_id, $org_id),
 ];
 }

 /**
 * @return array<string, mixed>|WP_Error
 */
 public static function admin_recover($target_user_id, $actor_user_id, $justification) {
 global $wpdb;

 $target_user_id = (int) $target_user_id;
 $actor_user_id = (int) $actor_user_id;
 $justification = sanitize_textarea_field((string) $justification);

 if ($justification === '') {
 return new WP_Error('justification_required', 'Recovery justification is required', ['status' => 422]);
 }

 if ($target_user_id <= 0) {
 return new WP_Error('invalid_user', 'Target user is required', ['status' => 400]);
 }

 $table_users = OraBooks_Database::table('users');
 $target = $wpdb->get_row($wpdb->prepare(
 "SELECT id, org_id, email, is_2fa_enabled FROM {$table_users} WHERE id = %d",
 $target_user_id
 ));

 if (!$target) {
 return new WP_Error('user_not_found', 'User not found', ['status' => 404]);
 }

 if (empty($target->is_2fa_enabled)) {
 return new WP_Error('2fa_not_enabled', 'Two-factor authentication is not enabled for this user', ['status' => 400]);
 }

 $is_platform_admin = function_exists('current_user_can') && current_user_can('manage_options');
 $allowed = $is_platform_admin;

 if (!$allowed && (int) $target->org_id > 0) {
 $allowed = OraBooks_RBAC::require_permission($actor_user_id, (int) $target->org_id, 'manage_org_settings');
 }

 if (!$allowed) {
 return new WP_Error('forbidden', 'Permission denied for 2FA recovery', ['status' => 403]);
 }

 $wp_user_id = orabooks_get_wp_user_id_for_orabooks_user($target_user_id);
 self::clear_2fa_credentials($target_user_id, $wp_user_id);

 $wpdb->update(
 $table_users,
 ['is_2fa_enabled' => 0],
 ['id' => $target_user_id],
 ['%d'],
 ['%d']
 );

 orabooks_log_event(
 '2fa_admin_recovery',
 "Admin recovered 2FA for user {$target_user_id}",
 'warning',
 [
 'target_user_id' => $target_user_id,
 'actor_user_id' => $actor_user_id,
 'justification' => $justification,
 ],
 $actor_user_id,
 $target->org_id
 );

 return [
 'user_id' => $target_user_id,
 'is_2fa_enabled' => false,
 'message' => 'Two-factor authentication has been reset. The user must set up 2FA again.',
 ];
 }

 public static function org_requires_2fa($org_id) {
 $config = self::get_org_config((int) $org_id);
 return !empty($config['require_2fa']);
 }

 /**
 * @return array<string, mixed>|WP_Error
 */
 public static function set_org_requires_2fa($org_id, $enabled, $actor_user_id) {
 global $wpdb;

 $org_id = (int) $org_id;
 $actor_user_id = (int) $actor_user_id;
 $enabled = (bool) $enabled;

 if ($org_id <= 0) {
 return new WP_Error('org_required', 'Organization is required', ['status' => 400]);
 }

 if (!OraBooks_RBAC::require_permission($actor_user_id, $org_id, 'manage_org_settings')) {
 return new WP_Error('forbidden', 'Permission denied', ['status' => 403]);
 }

 $table_orgs = OraBooks_Database::table('organizations');
 $org = $wpdb->get_row($wpdb->prepare(
 "SELECT id, config FROM {$table_orgs} WHERE id = %d",
 $org_id
 ));

 if (!$org) {
 return new WP_Error('org_not_found', 'Organization not found', ['status' => 404]);
 }

 $config = self::decode_org_config($org->config);
 $config['require_2fa'] = $enabled;

 $wpdb->update(
 $table_orgs,
 ['config' => wp_json_encode($config)],
 ['id' => $org_id],
 ['%s'],
 ['%d']
 );

 orabooks_log_event(
 $enabled ? 'org_2fa_policy_enabled': 'org_2fa_policy_disabled',
 $enabled ? "Org {$org_id} now requires 2FA": "Org {$org_id} no longer requires 2FA",
 'info',
 ['org_id' => $org_id],
 $actor_user_id,
 $org_id
 );

 return [
 'org_id' => $org_id,
 'require_2fa' => $enabled,
 ];
 }

 public static function user_needs_2fa_setup($user_id, $org_id) {
 global $wpdb;

 $user_id = (int) $user_id;
 $org_id = (int) $org_id;
 if ($org_id <= 0 || $user_id <= 0) {
 return false;
 }

 if (!self::org_requires_2fa($org_id)) {
 return false;
 }

 $table_users = OraBooks_Database::table('users');
 $enabled = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT is_2fa_enabled FROM {$table_users} WHERE id = %d",
 $user_id
 ));

 return $enabled !== 1;
 }

 /**
 * @return true|WP_Error
 */
 public static function assert_org_compliance($user_id, $org_id) {
 if (!self::user_needs_2fa_setup((int) $user_id, (int) $org_id)) {
 return true;
 }

 return new WP_Error(
 '2fa_setup_required',
 'Two-factor authentication is required by your organization. Enable 2FA on the Security page before continuing.',
 ['status' => 403]
 );
 }

 /**
 * AJAX actions exempt from org-wide 2FA compliance enforcement.
 *
 * @return string[]
 */
 public static function get_2fa_compliance_exempt_actions() {
 return [
 'orabooks_login',
 'orabooks_register',
 'orabooks_verify_email',
 'orabooks_verify_email_token',
 'orabooks_resend_verification',
 'orabooks_forgot_password',
 'orabooks_reset_password',
 'orabooks_check_subdomain',
 'orabooks_get_org_by_subdomain',
 'orabooks_setup_2fa',
 'orabooks_verify_2fa_setup',
 'orabooks_2fa_challenge',
 'orabooks_disable_2fa',
 'orabooks_regenerate_2fa_backup_codes',
 'orabooks_reveal_2fa_backup_codes',
 'orabooks_2fa_status',
 'orabooks_admin_2fa_recover',
 'orabooks_org_2fa_policy_get',
 'orabooks_org_2fa_policy_set',
 'orabooks_logout',
 'orabooks_refresh_token',
 'orabooks_establish_session',
 'orabooks_frontend_context',
 'orabooks_oidc_initiate',
 'orabooks_oidc_callback',
 'orabooks_select_tier',
 ];
 }

 public static function is_2fa_compliance_exempt_action($action) {
 return in_array(sanitize_key((string) $action), self::get_2fa_compliance_exempt_actions, true);
 }

 public function maybe_enforce_ajax_2fa_compliance() {
 if (!wp_doing_ajax) {
 return;
 }

 $action = sanitize_key($_REQUEST['action'] ?? '');
 if ($action === '' || strpos($action, 'orabooks_') !== 0) {
 return;
 }

 if (self::is_2fa_compliance_exempt_action($action)) {
 return;
 }

 if (!orabooks_is_user_logged_in()) {
 return;
 }

 $user_id = orabooks_get_current_user_id();
 if ($user_id <= 0) {
 return;
 }

 global $wpdb;
 $org_id = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT org_id FROM ". OraBooks_Database::table('users'). " WHERE id = %d",
 $user_id
 ));

 if ($org_id <= 0) {
 return;
 }

 $compliance = self::assert_org_compliance($user_id, $org_id);
 if (is_wp_error($compliance)) {
 orabooks_json_error($compliance->get_error_message(), 403);
 }
 }

 /**
 * @param array<string, mixed> $login_result
 * @return array<string, mixed>
 */
 public static function enrich_login_response($login_result) {
 if (!is_array($login_result) || empty($login_result['user_id'])) {
 return $login_result;
 }

 $user_id = (int) $login_result['user_id'];
 $org_id = (int) ($login_result['org_id'] ?? 0);

 if ($org_id <= 0 && class_exists('OraBooks_Organization')) {
 global $wpdb;
 $org_id = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT org_id FROM ". OraBooks_Database::table('users'). " WHERE id = %d",
 $user_id
 ));
 }

 $needs_setup = self::user_needs_2fa_setup($user_id, $org_id);
 $login_result['needs_2fa_setup'] = $needs_setup;
 $login_result['org_require_2fa'] = $org_id > 0 ? self::org_requires_2fa($org_id): false;

 return $login_result;
 }

 public static function count_remaining_backup_codes($orabooks_user_id) {
 global $wpdb;

 $table = OraBooks_Database::table('2fa_backup_codes');
 return (int) $wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND used = 0",
 (int) $orabooks_user_id
 ));
 }

 /**
 * @return array<string, mixed>
 */
 public static function get_org_policy($org_id) {
 return [
 'org_id' => (int) $org_id,
 'require_2fa' => self::org_requires_2fa((int) $org_id),
 ];
 }

 /**
 * @return array<string, mixed>
 */
 private static function get_org_config($org_id) {
 global $wpdb;

 if ($org_id <= 0) {
 return [];
 }

 $table_orgs = OraBooks_Database::table('organizations');
 $raw = $wpdb->get_var($wpdb->prepare(
 "SELECT config FROM {$table_orgs} WHERE id = %d",
 $org_id
 ));

 return self::decode_org_config($raw);
 }

 /**
 * @param mixed $raw
 * @return array<string, mixed>
 */
 private static function decode_org_config($raw) {
 if (is_array($raw)) {
 return $raw;
 }

 if ($raw === null || $raw === '') {
 return [];
 }

 $decoded = json_decode((string) $raw, true);
 return is_array($decoded) ? $decoded: [];
 }

 private static function persist_backup_codes($orabooks_user_id, array $codes) {
 global $wpdb;

 $table = OraBooks_Database::table('2fa_backup_codes');
 $wpdb->delete($table, ['user_id' => (int) $orabooks_user_id], ['%d']);

 foreach ($codes as $code) {
 $wpdb->insert(
 $table,
 [
 'user_id' => (int) $orabooks_user_id,
 'code_hash' => OraBooks_Secrets::hash_password($code),
 ],
 ['%d', '%s']
 );
 }

 $wp_user_id = orabooks_get_wp_user_id_for_orabooks_user((int) $orabooks_user_id);
 if ($wp_user_id > 0) {
 orabooks_set_2fa_backup_codes_encrypted($wp_user_id, $codes);
 }
 }

 private static function clear_2fa_credentials($orabooks_user_id, $wp_user_id) {
 global $wpdb;

 $table = OraBooks_Database::table('2fa_backup_codes');
 $wpdb->delete($table, ['user_id' => (int) $orabooks_user_id], ['%d']);

 if ($wp_user_id > 0) {
 delete_user_meta($wp_user_id, 'orabooks_2fa_secret');
 delete_user_meta($wp_user_id, 'orabooks_2fa_temp_secret');
 delete_user_meta($wp_user_id, 'orabooks_2fa_temp_backup_codes');
 delete_user_meta($wp_user_id, 'orabooks_2fa_backup_codes_encrypted');
 }
 }

 public function ajax_disable_2fa() {
 if (!orabooks_is_user_logged_in()) {
 orabooks_json_error('Not authenticated', 401);
 }

 $result = self::disable(
 orabooks_get_current_user_id(),
 sanitize_text_field($_POST['otp_code'] ?? '')
 );

 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message(), (int) ($result->get_error_data['status'] ?? 400));
 }

 orabooks_json_success($result, 'Two-factor authentication disabled');
 }

 public function ajax_regenerate_backup_codes() {
 if (!orabooks_is_user_logged_in()) {
 orabooks_json_error('Not authenticated', 401);
 }

 $result = self::regenerate_backup_codes(
 orabooks_get_current_user_id(),
 sanitize_text_field($_POST['otp_code'] ?? '')
 );

 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message(), (int) ($result->get_error_data['status'] ?? 400));
 }

 orabooks_json_success($result, 'Backup codes regenerated');
 }

 public function ajax_reveal_backup_codes() {
 if (!orabooks_is_user_logged_in()) {
 orabooks_json_error('Not authenticated', 401);
 }

 $result = self::reveal_backup_codes(
 orabooks_get_current_user_id(),
 sanitize_text_field($_POST['otp_code'] ?? '')
 );

 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message(), (int) ($result->get_error_data['status'] ?? 400));
 }

 orabooks_json_success($result, 'Backup codes retrieved');
 }

 public function ajax_status() {
 if (!orabooks_is_user_logged_in()) {
 orabooks_json_error('Not authenticated', 401);
 }

 orabooks_json_success(self::get_status(orabooks_get_current_user_id()));
 }

 public function ajax_admin_recover() {
 if (!orabooks_is_user_logged_in()) {
 orabooks_json_error('Not authenticated', 401);
 }

 $result = self::admin_recover(
 (int) ($_POST['target_user_id'] ?? 0),
 orabooks_get_current_user_id(),
 sanitize_textarea_field($_POST['justification'] ?? '')
 );

 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message(), (int) ($result->get_error_data['status'] ?? 400));
 }

 orabooks_json_success($result, $result['message'] ?? '2FA recovered');
 }

 public function ajax_org_policy_get() {
 if (!orabooks_is_user_logged_in()) {
 orabooks_json_error('Not authenticated', 401);
 }

 $org_id = (int) ($_POST['org_id'] ?? orabooks_get_current_org_id);
 if ($org_id <= 0) {
 orabooks_json_error('Organization is required', 400);
 }

 if (!OraBooks_RBAC::require_permission(orabooks_get_current_user_id(), $org_id, 'manage_org_settings')) {
 orabooks_json_error('Permission denied', 403);
 }

 orabooks_json_success(self::get_org_policy($org_id));
 }

 public function ajax_org_policy_set() {
 if (!orabooks_is_user_logged_in()) {
 orabooks_json_error('Not authenticated', 401);
 }

 $org_id = (int) ($_POST['org_id'] ?? orabooks_get_current_org_id);
 $enabled = !empty($_POST['require_2fa']) && filter_var($_POST['require_2fa'], FILTER_VALIDATE_BOOLEAN);

 $result = self::set_org_requires_2fa($org_id, $enabled, orabooks_get_current_user_id());
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message(), (int) ($result->get_error_data['status'] ?? 400));
 }

 orabooks_json_success($result, $enabled ? 'Organization now requires 2FA': 'Organization 2FA requirement removed');
 }
}
