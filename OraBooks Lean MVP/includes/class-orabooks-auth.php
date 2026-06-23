<?php
/**
 * OraBooks Authentication
 *
 * Handles registration, login, OIDC, 2FA, password reset,
 * session management, partner onboarding, and subdomain detection.
 */

if (!defined('ABSPATH')) {
 exit;
 * Handles registration, login, OIDC, 2FA, password reset,
 * session management, partner onboarding, and subdomain detection.
 */

if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Auth {

 private static $instance = null;

 public static function init() {
 if (self::$instance === null) {
 self::$instance = new self;
 add_action('wp_ajax_nopriv_orabooks_register', [self::$instance, 'ajax_register']);
 add_action('wp_ajax_orabooks_register', [self::$instance, 'ajax_register']);
 add_action('wp_ajax_nopriv_orabooks_login', [self::$instance, 'ajax_login']);
 add_action('wp_ajax_orabooks_login', [self::$instance, 'ajax_login']);
 add_action('wp_ajax_nopriv_orabooks_verify_email', [self::$instance, 'ajax_verify_email']);
 add_action('wp_ajax_orabooks_verify_email', [self::$instance, 'ajax_verify_email']);
 add_action('wp_ajax_nopriv_orabooks_verify_email_token', [self::$instance, 'ajax_verify_email_token']);
 add_action('wp_ajax_orabooks_verify_email_token', [self::$instance, 'ajax_verify_email_token']);
 add_action('wp_ajax_nopriv_orabooks_resend_verification', [self::$instance, 'ajax_resend_verification']);
 add_action('wp_ajax_orabooks_resend_verification', [self::$instance, 'ajax_resend_verification']);
 add_action('wp_ajax_nopriv_orabooks_forgot_password', [self::$instance, 'ajax_forgot_password']);
 add_action('wp_ajax_orabooks_forgot_password', [self::$instance, 'ajax_forgot_password']);
 add_action('wp_ajax_nopriv_orabooks_reset_password', [self::$instance, 'ajax_reset_password']);
 add_action('wp_ajax_orabooks_reset_password', [self::$instance, 'ajax_reset_password']);
 add_action('wp_ajax_nopriv_orabooks_check_subdomain', [self::$instance, 'ajax_check_subdomain']);
 add_action('wp_ajax_orabooks_check_subdomain', [self::$instance, 'ajax_check_subdomain']);
 add_action('wp_ajax_orabooks_setup_2fa', [self::$instance, 'ajax_setup_2fa']);
 add_action('wp_ajax_orabooks_verify_2fa_setup', [self::$instance, 'ajax_verify_2fa_setup']);
 add_action('wp_ajax_orabooks_2fa_challenge', [self::$instance, 'ajax_2fa_challenge']);
 add_action('wp_ajax_nopriv_orabooks_2fa_challenge', [self::$instance, 'ajax_2fa_challenge']);
 add_action('wp_ajax_orabooks_logout', [self::$instance, 'ajax_logout']);
 add_action('wp_ajax_nopriv_orabooks_logout', [self::$instance, 'ajax_logout']);
 add_action('wp_ajax_orabooks_select_tier', [self::$instance, 'ajax_select_tier']);
 add_action('wp_ajax_nopriv_orabooks_select_tier', [self::$instance, 'ajax_select_tier']);
 add_action('wp_ajax_nopriv_orabooks_refresh_token', [self::$instance, 'ajax_refresh_token']);
 add_action('wp_ajax_orabooks_refresh_token', [self::$instance, 'ajax_refresh_token']);
 add_action('wp_ajax_nopriv_orabooks_establish_session', [self::$instance, 'ajax_establish_session']);
 add_action('wp_ajax_orabooks_establish_session', [self::$instance, 'ajax_establish_session']);
 //: Google OIDC endpoints
 add_action('wp_ajax_nopriv_orabooks_oidc_initiate', [self::$instance, 'ajax_oidc_initiate']);
 add_action('wp_ajax_orabooks_oidc_initiate', [self::$instance, 'ajax_oidc_initiate']);
 add_action('wp_ajax_nopriv_orabooks_oidc_callback', [self::$instance, 'ajax_oidc_callback']);
 add_action('wp_ajax_orabooks_oidc_callback', [self::$instance, 'ajax_oidc_callback']);
 //: ingress-level partner accounting isolation
 add_action('template_redirect', [self::$instance, 'enforce_partner_accounting_isolation'], 1);
 add_filter('rest_pre_dispatch', [self::$instance, 'enforce_partner_accounting_isolation_rest'], 10, 3);
 //: Admin partner approval endpoints registered in OraBooks_Partner

 if (function_exists('is_multisite') && is_multisite()) {
 add_action('wpmu_activate_user', [self::$instance, 'handle_multisite_user_activation'], 10, 3);
 add_action('wpmu_activate_blog', [self::$instance, 'handle_multisite_blog_activation'], 10, 5);
 }
 }
 return self::$instance;
 }

 /**
 * After multisite signup activation, link pending OraBooks metadata if present.
 */
 public function handle_multisite_user_activation($user_id, $password, $meta) {
 self::sync_orabooks_user_after_wp_activation((int) $user_id, is_array($meta) ? $meta: []);
 }

 public function handle_multisite_blog_activation($blog_id, $user_id, $password, $title, $meta) {
 self::sync_orabooks_user_after_wp_activation((int) $user_id, is_array($meta) ? $meta: []);
 }

 /**
 * Handle user registration
 */
 public static function register($data) {
 global $wpdb;

 $table_users = OraBooks_Database::table('users');
 $email = sanitize_email($data['email']);
 $password = $data['password'] ?? '';
 $user_type = $data['user_type'] ?? 'customer';
 $partner_code = $data['partner_code'] ?? '';
 $accept_terms = !empty($data['accept_terms']);
 $terms_version = $data['terms_version'] ?? '';
 $partner_type = $data['partner_type'] ?? 'individual';
 $organization_name = $data['organization_name'] ?? '';

 // Validate email
 if (!is_email($email)) {
 return new WP_Error('invalid_email', 'Invalid email address');
 }

 // Validate password
 $password_check = orabooks_validate_password($password);
 if ($password_check !== true) {
 return new WP_Error('weak_password', $password_check);
 }

 // Rate limit: 5 registrations per hour per IP
 $ip = orabooks_get_client_ip;
 if (!orabooks_check_rate_limit('register_'. $ip, 5, 3600)) {
 return new WP_Error('rate_limit', 'Too many registration attempts. Please try again later.');
 }

 if (!orabooks_users_can_register()) {
 return new WP_Error('registration_disabled', 'User registration is disabled on this site.');
 }

 // Check if email already exists
 $existing = $wpdb->get_var($wpdb->prepare(
 "SELECT id FROM {$table_users} WHERE email = %s", $email
 ));
 if ($existing) {
 return new WP_Error('email_exists', 'This email is already registered');
 }

 if (function_exists('email_exists') && email_exists($email)) {
 return new WP_Error('email_exists', 'This email is already registered');
 }

 // Partner validation
 if ($user_type === 'partner') {
 if (!$accept_terms) {
 return new WP_Error('terms_required', 'Partner terms must be accepted');
 }
 if (!in_array($partner_type, ['individual', 'accountant', 'agency', 'reseller', 'strategic_partner'])) {
 $partner_type = 'individual';
 }
 if (in_array($partner_type, ['agency', 'reseller', 'strategic_partner']) && empty($organization_name)) {
 return new WP_Error('org_name_required', 'Organization name is required for this partner type');
 }
 }

 $password_hash = OraBooks_Secrets::hash_password($password);
 $verification_token = orabooks_random_string(32);
 $verification_expires = date('Y-m-d H:i:s', time() + 86400); // 24 hours
 $pending_wp_signup = orabooks_multisite_uses_signup_activation();
 $wp_user_id = null;

 // Create user
 $insert_data = [
 'email' => $email,
 'password_hash' => $password_hash,
 'is_active' => 1,
 'is_email_verified' => 0,
 'email_verification_token' => $verification_token,
 'email_verification_expires_at' => $verification_expires,
 'is_2fa_enabled' => 0,
 'auth_provider' => 'local',
 'org_id' => null,
 'is_partner' => ($user_type === 'partner') ? 1: 0,
 'wp_user_id' => null,
 ];
 $insert_format = ['%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', null, '%d', null];

 if ($user_type === 'partner') {
 $insert_data['pending_partner_type'] = $partner_type;
 $insert_data['pending_organization_name'] = in_array($partner_type, ['agency', 'reseller', 'strategic_partner'], true)
 ? $organization_name
: null;
 $insert_format[] = '%s';
 $insert_format[] = '%s';
 }

 $wpdb->insert($table_users, $insert_data, $insert_format);

 $user_id = $wpdb->insert_id;
 if (!$user_id) {
 $db_error = $wpdb->last_error ? ' Database: '. $wpdb->last_error: '';
 return new WP_Error(
 'creation_failed',
 'Failed to create user. Deactivate and reactivate the OraBooks plugin, then try again.'. $db_error
 );
 }

 $signup_meta = [
 'orabooks_user_id' => $user_id,
 'orabooks_user_type' => $user_type,
 'orabooks_partner_type' => $partner_type,
 'orabooks_organization_name' => $organization_name,
 'orabooks_verification_token' => $verification_token,
 ];

 $wp_result = orabooks_create_wp_user_for_registration($email, $password, $signup_meta);
 if (is_wp_error($wp_result)) {
 $wpdb->delete($table_users, ['id' => $user_id], ['%d']);
 return $wp_result;
 }

 if (is_array($wp_result) && !empty($wp_result['pending_signup'])) {
 $pending_wp_signup = true;
 } elseif ($wp_result) {
 $wp_user_id = (int) $wp_result;
 $wpdb->update(
 $table_users,
 ['wp_user_id' => $wp_user_id],
 ['id' => $user_id],
 ['%d'],
 ['%d']
 );
 }

 $email_warning = '';
 if ($pending_wp_signup) {
 $email_result = self::send_verification_email($email, $verification_token);
 if (is_wp_error($email_result)) {
 $email_warning = $email_result->get_error_message();
 }
 } else {
 $email_result = self::send_registration_emails($wp_user_id, $email, $verification_token);
 if (is_wp_error($email_result)) {
 $email_warning = $email_result->get_error_message();
 }
 }

 if ($email_warning !== '') {
 orabooks_log_event('verification_email_failed', "Verification email failed for $email", 'error', [
 'error' => $email_warning
 ], $user_id, null);
 }

 // Store partner type and org name in session for backward compatibility
 if ($user_type === 'partner') {
 $_SESSION['orabooks_partner_type'] = $partner_type;
 $_SESSION['orabooks_partner_org_name'] = $organization_name;

 // Record terms acceptance
 $table_terms = OraBooks_Database::table('partner_terms_acceptance');
 $wpdb->insert(
 $table_terms,
 [
 'user_id' => $user_id,
 'terms_version' => $terms_version ?: '1.0',
 'ip_address' => $ip,
 'user_agent' => orabooks_get_user_agent
 ],
 ['%d', '%s', '%s', '%s']
 );
 }

 // Handle partner attribution for customer signup with partner code
 if ($user_type === 'customer' && !empty($partner_code)) {
 $attribution_result = self::process_attribution($user_id, $partner_code, $email);
 if (is_wp_error($attribution_result)) {
 orabooks_log_event('partner_attribution_failed', $attribution_result->get_error_message(), 'warning', [
 'partner_code' => $partner_code
 ], $user_id, null);
 }
 }

 // Do not issue a session JWT until email is verified
 if (!in_array($tier, ['free', 'premium', 'enterprise'], true)) {
 orabooks_json_error('Invalid tier', 400);
 }

 $region_check = orabooks_validate_org_region($region_input, $tier);
 if ($region_check !== true) {
 orabooks_json_error($region_check, 400);
 }

 $region = ($tier === 'enterprise')
 ? strtolower(trim($region_input))
: orabooks_get_default_region_for_tier($tier);

 $validation = orabooks_validate_subdomain($subdomain);
 if ($validation !== true) {
 orabooks_json_error($validation, 400);
 }

 global $wpdb;
 $table_users = OraBooks_Database::table('users');
 $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_users} WHERE id = %d", $user_id));

 if (!$user || $user->is_partner) {
 orabooks_json_error('Only customers can select tiers', 400);
 }

 if (orabooks_user_has_any_pending_invite((int) $user_id)) {
 orabooks_json_error(
 'You have a pending team invitation. Log in again to join your team instead of creating a new organization.',
 403
 );
 }

 if (!empty($user->org_id)) {
 $org = OraBooks_Organization::get((int) $user->org_id);
 $role = orabooks_get_user_role($user_id, (int) $user->org_id);
 $jwt = OraBooks_Secrets::generate_jwt([
 'user_id' => $user_id,
 'email' => $user->email,
 'org_id' => (int) $user->org_id,
 'role' => $role,
 'subdomain' => $org ? $org->subdomain: '',
 'is_partner' => 0,
 ]);
 $refresh_token = orabooks_random_string(32);
 self::store_refresh_token($user_id, (int) $user->org_id, $refresh_token);

 $existing = orabooks_enrich_login_response([
 'token' => $jwt,
 'refresh_token' => $refresh_token,
 'user_id' => $user_id,
 'org_id' => (int) $user->org_id,
 'role' => $role,
 'subdomain' => $org ? $org->subdomain: '',
 'is_partner' => false,
 ]);
 orabooks_persist_login_session($existing);
 orabooks_json_success(orabooks_redact_client_auth_response($existing), 'Organization already exists');
 }

 $org_result = OraBooks_Organization::create([
 'owner_id' => $user_id,
 'organization_type' => 'customer',
 'tier' => $tier,
 'subdomain' => $subdomain,
 'name' => $subdomain,
 'region' => $region,
 ]);

 if (is_wp_error($org_result)) {
 orabooks_json_error($org_result->get_error_message, 400);
 }

 // Update attribution org_id if pending or already verified before tier selection
 $table_attributions = OraBooks_Database::table('partner_attributions');
 $wpdb->update(
 $table_attributions,
 ['org_id' => $org_result['org_id']],
 ['customer_user_id' => $user_id, 'status' => 'pending'],
 ['%d'],
 ['%d', '%s']
 );
 $wpdb->update(
 $table_attributions,
 ['org_id' => $org_result['org_id']],
 ['customer_user_id' => $user_id, 'status' => 'verified'],
 ['%d'],
 ['%d', '%s']
 );

 // Fire event for Commission Engine
 $pending_attr = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table_attributions} WHERE customer_user_id = %d AND status = 'verified' ORDER BY verified_at DESC LIMIT 1",
 $user_id
 ));
 if ($pending_attr) {
 do_action('orabooks_partner_attribution_verified', $pending_attr->id, $pending_attr);
 }

 $role = 'owner';
 $jwt = OraBooks_Secrets::generate_jwt([
 'user_id' => $user->id,
 'email' => $user->email,
 'org_id' => $org_result['org_id'],
 'role' => $role,
 'subdomain' => $org_result['subdomain'],
 'is_partner' => 0
 ]);

 $refresh_token = orabooks_random_string(32);
 self::store_refresh_token($user->id, $org_result['org_id'], $refresh_token);

 $tier_result = orabooks_enrich_login_response([
 'token' => $jwt,
 'refresh_token' => $refresh_token,
 'org_id' => $org_result['org_id'],
 'subdomain' => $org_result['subdomain'],
 'user_id' => $user->id,
 'role' => $role,
 'is_partner' => false,
 'redirect_to' => '/dashboard/',
 ]);

 orabooks_log_event('tier_selected', "Customer org created via tier selection: {$org_result['subdomain']}", 'info', [
 'tier' => $tier,
 'region' => $region,
 'subdomain' => $org_result['subdomain'],
 ], $user->id, $org_result['org_id']);

 orabooks_persist_login_session($tier_result);

 orabooks_json_success(orabooks_redact_client_auth_response($tier_result), 'Organization created successfully');
 }
}