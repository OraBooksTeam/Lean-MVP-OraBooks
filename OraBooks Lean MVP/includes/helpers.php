<?php
/**
 * OraBooks Helper Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether the active theme is Divi (parent or child).
 */
function orabooks_is_divi_theme() {
    if (defined('ET_BUILDER_VERSION')) {
        return true;
    }

    $theme = wp_get_theme();
    if (!$theme) {
        return false;
    }

    $template = $theme->get_template();
    $stylesheet = $theme->get_stylesheet();

    return in_array('Divi', [$template, $stylesheet], true);
}

function orabooks_get_accounting_page_slugs() {
    return [
        'dashboard',
        'customers',
        'invoices',
        'vendors',
        'inventory',
        'reports',
        'expenses',
        'csv-imports',
        'attachments',
        'approvals',
        'ai-review',
        'voice',
        'chart-of-accounts',
        'fiscal-periods',
        'tax-settings',
        'journals',
        'bank-reconciliation',
        'team',
        'audit-log',
        'notifications',
        'notification-preferences',
        'job-queue',
        'webhook-settings',
        'my-exports',
    ];
}

/**
 * Lean MVP frontend page definitions (SL-004 through SL-139 locked build).
 *
 * @return array<string, array{0: string, 1: string, 2?: string}>
 */
function orabooks_get_lean_mvp_page_definitions() {
    return [
        'login' => ['Login', '[orabooks_login]'],
        'register' => ['Register', '[orabooks_register]'],
        'verify-email' => ['Verify Email', '[orabooks_verify_email]'],
        'accept-invite' => ['Accept Invitation', '[orabooks_accept_invite]'],
        'reset-password' => ['Reset Password', '[orabooks_reset_password]'],
        'tier-selection' => ['Choose Your Plan', '[orabooks_tier_selection]'],
        'partner' => ['Partner', ''],
        'onboarding' => ['Partner Onboarding', '[orabooks_partner_onboarding]', 'partner'],
        'partner-program' => ['Partner Program', '[orabooks_partner_dashboard]'],
        'dashboard' => ['Dashboard', '[orabooks_dashboard]'],
        'customers' => ['Customers', '[orabooks_customers]'],
        'invoices' => ['Invoices', '[orabooks_invoices]'],
        'vendors' => ['Vendors & Bills', '[orabooks_vendors]'],
        'inventory' => ['Inventory', '[orabooks_inventory]'],
        'reports' => ['Reports', '[orabooks_reports]'],
        'csv-imports' => ['CSV Imports', '[orabooks_csv_import]'],
        'expenses' => ['Expenses', '[orabooks_expenses]'],
        'attachments' => ['Attachments', '[orabooks_attachments]'],
        'approvals' => ['Approvals', '[orabooks_approvals]'],
        'ai-review' => ['AI Review', '[orabooks_ai_review]'],
        'voice' => ['Voice Input', '[orabooks_voice]'],
        'job-queue' => ['Job Queue', '[orabooks_async_queue_dashboard]'],
        'chart-of-accounts' => ['Chart of Accounts', '[orabooks_chart_of_accounts]'],
        'fiscal-periods' => ['Fiscal Periods', '[orabooks_fiscal_periods]'],
        'tax-settings' => ['Tax Settings', '[orabooks_tax_settings]'],
        'journals' => ['Journals', '[orabooks_journals]'],
        'bank-reconciliation' => ['Bank Reconciliation', '[orabooks_bank_reconciliation]'],
        'team' => ['Team', '[orabooks_team]'],
        'commissions' => ['Commissions', '[orabooks_commission]'],
        'profile' => ['Profile', '[orabooks_profile]'],
        'audit-log' => ['Audit Log', '[orabooks_audit_log]'],
        'notifications' => ['Notifications', '[orabooks_notification_center]'],
        'notification-preferences' => ['Notification Preferences', '[orabooks_notification_preferences]'],
        'webhook-settings' => ['Webhook Settings', '[orabooks_webhook_settings]'],
        'my-exports' => ['My Exports', '[orabooks_export_status]'],
    ];
}

/**
 * Allowed data residency regions (SL-004).
 *
 * @return string[]
 */
function orabooks_get_allowed_regions() {
    return ['us-east', 'eu-west-1', 'ap-southeast-1'];
}

/**
 * System-assigned region for non-enterprise customer tiers.
 */
function orabooks_get_default_region_for_tier($tier) {
    $regions = orabooks_get_allowed_regions();
    return $regions[0];
}

/**
 * Validate a residency region for org creation.
 */
function orabooks_validate_org_region($region, $tier) {
    $region = strtolower(trim((string) $region));
    $allowed = orabooks_get_allowed_regions();

    if ($tier === 'enterprise') {
        if ($region === '') {
            return __('Please select a data residency region.', 'orabooks');
        }
        if (!in_array($region, $allowed, true)) {
            return __('Invalid region selected.', 'orabooks');
        }
        return true;
    }

    if ($region !== '' && $region !== orabooks_get_default_region_for_tier($tier)) {
        return __('Region cannot be changed for this plan.', 'orabooks');
    }

    return true;
}

/**
 * Whether an organization subdomain may be used for tenant context (SL-004).
 */
function orabooks_org_allows_subdomain_access($org) {
    if (!$org) {
        return false;
    }

    if ($org->organization_type === 'partner') {
        return in_array($org->status, ['active', 'pending_setup', 'payout_hold'], true);
    }

    return $org->status === 'active';
}

/**
 * Slugs for WordPress pages created by OraBooks (Lean MVP frontend).
 *
 * @return string[]
 */
function orabooks_get_required_page_slugs() {
    return array_keys(orabooks_get_lean_mvp_page_definitions());
}

/**
 * Whether the current (or given) page is an OraBooks frontend page.
 *
 * Detects shortcodes in post content, known page slugs, and stored page IDs.
 *
 * @param WP_Post|null $post
 */
function orabooks_is_registered_frontend_page($post = null) {
    if ($post === null) {
        if (!is_singular('page')) {
            return false;
        }
        $post = get_queried_object();
    }

    if (!$post instanceof WP_Post || $post->post_type !== 'page') {
        return false;
    }

    $content = (string) $post->post_content;
    if ($content !== '' && strpos($content, '[orabooks_') !== false) {
        return true;
    }

    if (in_array($post->post_name, orabooks_get_required_page_slugs(), true)) {
        return true;
    }

    $page_ids = get_option('orabooks_pages', []);
    if (is_array($page_ids) && in_array((int) $post->ID, array_map('intval', $page_ids), true)) {
        return true;
    }

    return false;
}

/**
 * Whether the page should load OraBooks frontend assets.
 *
 * @param WP_Post|null $post
 */
function orabooks_page_needs_frontend_assets($post = null) {
    return orabooks_is_registered_frontend_page($post);
}

/**
 * Lean MVP frontend is React-only (see document_extracted.txt / Model List v5.2).
 */
function orabooks_should_use_react_frontend($user_id = 0) {
    return (bool) apply_filters('orabooks_use_react_frontend', true, $user_id);
}

/**
 * Legacy merged PHP accounting workspace — removed; always false.
 *
 * @deprecated
 */
function orabooks_uses_merged_accounting_workspace($user_id = 0) {
    return false;
}

/**
 * @deprecated
 */
function orabooks_render_merged_accounting_workspace($view = '') {
    if (!orabooks_is_user_logged_in()) {
        return OraBooks_Views::require_login_message();
    }

    return OraBooks_Views::render('react-app', ['initial_route' => '/dashboard']);
}

/**
 * @deprecated
 */
function orabooks_merged_accounting_shortcodes() {
    return [];
}

/**
 * @deprecated
 */
function orabooks_page_uses_merged_accounting_workspace($content) {
    return false;
}

/**
 * @deprecated
 */
function orabooks_get_merged_accounting_view_for_shortcode($shortcode_tag) {
    return 'dashboard';
}

/**
 * Generate a cryptographically secure random string
 */
function orabooks_random_string($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Generate a secure partner code: PARTNER-XXXXXXXX
 */
function orabooks_generate_partner_code() {
    $random = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    return 'PARTNER-' . $random;
}

/**
 * Validate password policy
 * Min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special
 */
function orabooks_validate_password($password) {
    if (strlen($password) < 8) {
        return __('Password must be at least 8 characters', 'orabooks');
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return __('Password must contain at least one uppercase letter', 'orabooks');
    }
    if (!preg_match('/[a-z]/', $password)) {
        return __('Password must contain at least one lowercase letter', 'orabooks');
    }
    if (!preg_match('/[0-9]/', $password)) {
        return __('Password must contain at least one number', 'orabooks');
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        return __('Password must contain at least one special character', 'orabooks');
    }
    return true;
}

/**
 * Validate subdomain format
 */
function orabooks_validate_subdomain($subdomain) {
    // Reserved subdomains (case-insensitive)
    $reserved = ['admin', 'api', 'app', 'support', 'billing', 'partner', 'orabooks', 'www', 'root'];
    
    $subdomain = strtolower(trim($subdomain));
    
    if (in_array($subdomain, $reserved)) {
        return __('This subdomain is reserved', 'orabooks');
    }
    
    if (!preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/', $subdomain)) {
        return __('Subdomain must be 3-63 chars, lowercase alphanumeric with hyphens, no start/end hyphen', 'orabooks');
    }
    
    return true;
}

/**
 * Get the tenant base domain from the current request host.
 * Strips an org subdomain prefix when present (e.g. mycompany.example.com -> example.com).
 */
function orabooks_get_tenant_base_domain($host = '') {
    if ($host === '') {
        $host = $_SERVER['HTTP_HOST'] ?? '';
    }

    $host = strtolower(trim(preg_replace('/:\d+$/', '', $host)));
    if ($host === '') {
        return '';
    }

    $parts = explode('.', $host);
    $reserved = ['www', 'mail', 'admin'];

    if (count($parts) >= 3 && !in_array($parts[0], $reserved, true)) {
        return implode('.', array_slice($parts, 1));
    }

    return $host;
}

/**
 * Build a full organization URL from a stored subdomain identifier.
 */
function orabooks_build_org_url($subdomain, $path = '/') {
    $base_domain = orabooks_get_tenant_base_domain();
    if ($base_domain === '') {
        return home_url(ltrim($path, '/'));
    }

    $scheme = is_ssl() ? 'https' : 'http';
    $path = '/' . ltrim($path, '/');

    return $scheme . '://' . $subdomain . '.' . $base_domain . $path;
}

/**
 * Resolve a workspace URL for an organization (tenant subdomain when available).
 */
function orabooks_get_org_workspace_url($org_id, $path = '/dashboard/', $query_args = []) {
    $org_id = (int) $org_id;
    $path = '/' . ltrim((string) $path, '/');

    if ($org_id > 0 && class_exists('OraBooks_Organization')) {
        $org = OraBooks_Organization::get($org_id);
        if ($org && !empty($org->subdomain)) {
            $url = orabooks_build_org_url($org->subdomain, $path);
            if (!empty($query_args)) {
                $url = add_query_arg($query_args, $url);
            }
            return $url;
        }
    }

    $url = home_url($path);
    if (!empty($query_args)) {
        $url = add_query_arg($query_args, $url);
    }

    return $url;
}

/**
 * Blog ID that stores shared OraBooks tenant data on multisite networks.
 */
function orabooks_get_data_blog_id() {
    if (function_exists('is_multisite') && is_multisite() && function_exists('get_main_site_id')) {
        return (int) get_main_site_id();
    }

    return function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
}

/**
 * Table prefix for shared OraBooks data (always the main network site on multisite).
 */
function orabooks_get_table_prefix() {
    global $wpdb;

    if (function_exists('is_multisite') && is_multisite() && function_exists('get_main_site_id')) {
        $main_id = (int) get_main_site_id();
        if ((int) get_current_blog_id() !== $main_id) {
            return $wpdb->get_blog_prefix($main_id);
        }
    }

    return $wpdb->prefix;
}

/**
 * Run a callback while switched to the OraBooks data blog (no-op on single site).
 *
 * @return mixed
 */
function orabooks_with_data_blog(callable $callback) {
    $switched = false;
    $target_blog = orabooks_get_data_blog_id();

    if (function_exists('is_multisite') && is_multisite() && function_exists('switch_to_blog')) {
        if ((int) get_current_blog_id() !== $target_blog) {
            switch_to_blog($target_blog);
            $switched = true;
        }
    }

    try {
        return $callback();
    } finally {
        if ($switched && function_exists('restore_current_blog')) {
            restore_current_blog();
        }
    }
}

/**
 * Run post-deploy health checks for multisite table prefix, schema, crons, and auth config.
 *
 * @return array{ok:bool,checks:array<int,array{id:string,label:string,ok:bool,detail:string}>,timestamp:string,environment:array<string,mixed>}
 */
function orabooks_run_deploy_checks() {
    return OraBooks_DeployChecks::run();
}

/**
 * Main network site URL for shared auth pages (login, register, tier selection).
 */
function orabooks_get_network_login_url($path = 'login') {
    $path = trim((string) $path, '/');
    if ($path === '') {
        $path = 'login';
    }

    if (function_exists('is_multisite') && is_multisite() && function_exists('get_site_url')) {
        return trailingslashit(get_site_url(get_main_site_id(), $path));
    }

    return home_url('/' . $path . '/');
}

/**
 * Frontend URL for accepting a team invitation (SL-014).
 */
function orabooks_get_accept_invite_url($token = '') {
    $url = orabooks_get_network_login_url('accept-invite');
    $token = trim((string) $token);
    if ($token === '') {
        return $url;
    }

    return add_query_arg('token', rawurlencode($token), $url);
}

/**
 * Whether the current host is the network main site (no tenant subdomain).
 */
function orabooks_is_network_auth_host() {
    if (!class_exists('OraBooks_Auth')) {
        return true;
    }

    return OraBooks_Auth::detect_subdomain_from_host() === '';
}

/**
 * WordPress OraBooks super-admin panel URL (network main site).
 */
function orabooks_get_platform_admin_url() {
    if (function_exists('is_multisite') && is_multisite() && function_exists('get_main_site_id') && function_exists('get_admin_url')) {
        return get_admin_url(get_main_site_id(), 'admin.php?page=orabooks');
    }

    return admin_url('admin.php?page=orabooks');
}

/**
 * Whether an OraBooks user is a platform super-admin (manage_options on main site).
 */
function orabooks_orabooks_user_can_manage_platform($orabooks_user_id) {
    global $wpdb;

    $orabooks_user_id = (int) $orabooks_user_id;
    if ($orabooks_user_id <= 0) {
        return false;
    }

    $table_users = OraBooks_Database::table('users');
    $wp_user_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT wp_user_id FROM {$table_users} WHERE id = %d",
        $orabooks_user_id
    ));

    if ($wp_user_id <= 0) {
        return false;
    }

    if (function_exists('is_multisite') && is_multisite() && function_exists('switch_to_blog') && function_exists('get_main_site_id')) {
        switch_to_blog(get_main_site_id());
        $can_manage = user_can($wp_user_id, 'manage_options');
        restore_current_blog();

        return $can_manage;
    }

    return user_can($wp_user_id, 'manage_options');
}

/**
 * Map auth error codes to HTTP status codes (SL-013).
 */
function orabooks_auth_error_status_code($error_code) {
    $map = [
        'subdomain_mismatch'     => 403,
        'email_not_verified'     => 403,
        'account_disabled'       => 403,
        'org_inactive'           => 403,
        'terms_required'         => 400,
        'org_name_required'      => 400,
        'weak_password'          => 400,
        'email_exists'           => 409,
        'registration_disabled'  => 403,
        'invalid_token'          => 401,
        'oidc_email_conflict'    => 409,
        'rate_limit'             => 429,
        'invalid_credentials'    => 401,
    ];

    return $map[(string) $error_code] ?? 400;
}

/**
 * Whether a WordPress multisite blog already exists for an org subdomain.
 */
function orabooks_multisite_subdomain_taken($subdomain) {
    if (!function_exists('is_multisite') || !is_multisite() || !function_exists('get_blog_details')) {
        return false;
    }

    $base_domain = orabooks_get_tenant_base_domain();
    if ($base_domain === '') {
        return false;
    }

    $domain = strtolower(trim($subdomain)) . '.' . $base_domain;

    return (bool) get_blog_details(['domain' => $domain, 'path' => '/'], false);
}

/**
 * Resolve the linked WordPress user for an OraBooks user.
 */
function orabooks_get_wp_user_id_for_orabooks_user($orabooks_user_id) {
    global $wpdb;

    $table_users = OraBooks_Database::table('users');
    $wp_user_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT wp_user_id FROM {$table_users} WHERE id = %d",
        (int) $orabooks_user_id
    ));

    if ($wp_user_id > 0) {
        return $wp_user_id;
    }

    $current = get_current_user_id();
    if ($current > 0) {
        return (int) $current;
    }

    return 0;
}

/**
 * Create (or reuse) a WordPress multisite blog for a customer organization subdomain.
 *
 * @return int|true|WP_Error Blog ID, true when multisite is disabled, or error.
 */
function orabooks_provision_org_multisite($org_id, $subdomain, $title, $owner_user_id) {
    if (!function_exists('is_multisite') || !is_multisite() || !function_exists('wpmu_create_blog')) {
        return true;
    }

    $org_id = (int) $org_id;
    $subdomain = strtolower(trim((string) $subdomain));
    $title = $title !== '' ? $title : $subdomain;
    $base_domain = orabooks_get_tenant_base_domain();

    if ($subdomain === '' || $base_domain === '') {
        return new WP_Error('invalid_subdomain', __('Unable to provision organization site.', 'orabooks'));
    }

    $domain = $subdomain . '.' . $base_domain;
    $existing = get_blog_details(['domain' => $domain, 'path' => '/'], false);
    if ($existing && !empty($existing->blog_id)) {
        $blog_id = (int) $existing->blog_id;
    } else {
        $wp_user_id = orabooks_get_wp_user_id_for_orabooks_user($owner_user_id);
        $blog_id = wpmu_create_blog($domain, '/', $title, $wp_user_id, ['public' => 1], get_current_network_id());

        if (is_wp_error($blog_id)) {
            orabooks_log_event('org_site_provision_failed', $blog_id->get_error_message(), 'error', [
                'org_id' => $org_id,
                'subdomain' => $subdomain,
            ], (int) $owner_user_id, $org_id);

            return $blog_id;
        }

        $blog_id = (int) $blog_id;
    }

    if ($blog_id > 0 && function_exists('orabooks_create_required_pages')) {
        switch_to_blog($blog_id);
        orabooks_create_required_pages();
        restore_current_blog();
    }

    if ($org_id > 0) {
        global $wpdb;
        $table_orgs = OraBooks_Database::table('organizations');
        $org = OraBooks_Organization::get($org_id);
        $config = [];
        if ($org && !empty($org->config)) {
            $decoded = json_decode($org->config, true);
            if (is_array($decoded)) {
                $config = $decoded;
            }
        }
        $config['wp_blog_id'] = $blog_id;
        $wpdb->update(
            $table_orgs,
            ['config' => wp_json_encode($config)],
            ['id' => $org_id],
            ['%s'],
            ['%d']
        );
    }

    orabooks_log_event('org_site_provisioned', 'Organization WordPress site provisioned', 'info', [
        'org_id' => $org_id,
        'subdomain' => $subdomain,
        'blog_id' => $blog_id,
    ], (int) $owner_user_id, $org_id);

    return $blog_id;
}

/**
 * Ensure a multisite blog exists for an organization (idempotent).
 */
function orabooks_ensure_org_multisite_site($org_id) {
    $org = OraBooks_Organization::get((int) $org_id);
    if (!$org || empty($org->subdomain)) {
        return true;
    }

    if (!empty($org->config)) {
        $config = json_decode($org->config, true);
        if (is_array($config) && !empty($config['wp_blog_id']) && get_blog_details((int) $config['wp_blog_id'])) {
            return (int) $config['wp_blog_id'];
        }
    }

    return orabooks_provision_org_multisite((int) $org->id, $org->subdomain, $org->name, (int) $org->owner_id);
}

/**
 * Append cross-origin auth token query params for multisite subdomain handoff.
 */
function orabooks_append_auth_tokens_to_url($url, $token = '', $refresh_token = '') {
    if ($token === '' && !empty($_COOKIE['orabooks_token'])) {
        $token = sanitize_text_field(wp_unslash($_COOKIE['orabooks_token']));
    }

    if ($token === '') {
        return $url;
    }

    $parsed = wp_parse_url($url);
    if (!$parsed || empty($parsed['host'])) {
        return $url;
    }

    $current_host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $target_host = strtolower((string) $parsed['host']);
    if ($target_host === $current_host) {
        return $url;
    }

    $query = [];
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $query);
    }

    $query['ob_t'] = $token;
    if ($refresh_token !== '') {
        $query['ob_rt'] = $refresh_token;
    }

    $scheme = $parsed['scheme'] ?? (is_ssl() ? 'https' : 'http');
    $path = $parsed['path'] ?? '/';
    return $scheme . '://' . $target_host . $path . '?' . http_build_query($query);
}

/**
 * Add subdomain + absolute redirect URL to login/auth API payloads.
 */
function orabooks_enrich_login_response($login_result) {
    if (!is_array($login_result)) {
        return $login_result;
    }

    if (!empty($login_result['needs_tier_selection'])) {
        $login_result['redirect_to'] = orabooks_get_network_login_url('tier-selection');
        return $login_result;
    }

    $user_id = !empty($login_result['user_id']) ? (int) $login_result['user_id'] : 0;
    if (
        $user_id > 0
        && orabooks_is_network_auth_host()
        && orabooks_orabooks_user_can_manage_platform($user_id)
    ) {
        $login_result['is_platform_admin'] = true;
        $login_result['redirect_to'] = orabooks_get_platform_admin_url();

        if (!empty($login_result['token'])) {
            $login_result['redirect_to'] = orabooks_append_auth_tokens_to_url(
                $login_result['redirect_to'],
                (string) $login_result['token'],
                (string) ($login_result['refresh_token'] ?? '')
            );
        }

        return $login_result;
    }

    if (empty($login_result['subdomain']) && !empty($login_result['org_id']) && class_exists('OraBooks_Organization')) {
        $org = OraBooks_Organization::get((int) $login_result['org_id']);
        if ($org && !empty($org->subdomain)) {
            $login_result['subdomain'] = $org->subdomain;
        }
    }

    if (!empty($login_result['org_id'])) {
        orabooks_ensure_org_multisite_site((int) $login_result['org_id']);
    }

    if (!empty($login_result['redirect_to'])) {
        if (strpos($login_result['redirect_to'], 'http') !== 0 && strpos($login_result['redirect_to'], '/') === 0) {
            if (!empty($login_result['subdomain'])) {
                $login_result['redirect_to'] = orabooks_build_org_url(
                    $login_result['subdomain'],
                    $login_result['redirect_to']
                );
            } else {
                $login_result['redirect_to'] = orabooks_get_network_login_url(
                    trim($login_result['redirect_to'], '/')
                );
            }
        }
    } elseif (!empty($login_result['subdomain'])) {
        $path = !empty($login_result['is_partner']) ? '/partner/onboarding/' : '/dashboard/';
        $login_result['redirect_to'] = orabooks_build_org_url($login_result['subdomain'], $path);
    } else {
        $login_result['redirect_to'] = orabooks_get_network_login_url('dashboard');
    }

    if (!empty($login_result['redirect_to']) && !empty($login_result['token'])) {
        $login_result['redirect_to'] = orabooks_append_auth_tokens_to_url(
            $login_result['redirect_to'],
            (string) $login_result['token'],
            (string) ($login_result['refresh_token'] ?? '')
        );
    }

    return $login_result;
}

/**
 * Redirect auth pages on tenant subdomains to the main network login/register URLs.
 */
function orabooks_redirect_tenant_auth_to_network() {
    if (!is_singular('page') || !class_exists('OraBooks_Auth')) {
        return;
    }

    $subdomain = OraBooks_Auth::detect_subdomain_from_host();
    if ($subdomain === '') {
        return;
    }

    $post = get_queried_object();
    if (!$post || empty($post->post_name)) {
        return;
    }

    $shared_auth_slugs = ['login', 'register', 'reset-password', 'verify-email', 'tier-selection', 'accept-invite'];
    if (!in_array($post->post_name, $shared_auth_slugs, true)) {
        return;
    }

    if ($post->post_name === 'login' && function_exists('orabooks_is_user_logged_in') && orabooks_is_user_logged_in()) {
        if (orabooks_is_explicit_logout_request()) {
            wp_redirect(orabooks_get_logout_redirect_url());
            exit;
        }

        wp_redirect(orabooks_append_auth_tokens_to_url(home_url('/dashboard/')));
        exit;
    }

    $target = orabooks_get_network_login_url($post->post_name);
    if ($post->post_name === 'login' && orabooks_is_explicit_logout_request()) {
        $target = orabooks_get_logout_redirect_url();
    }

    wp_redirect($target);
    exit;
}

add_action('template_redirect', 'orabooks_redirect_tenant_auth_to_network', 2);

/**
 * Handle Google OAuth callback on the network login page (server-side redirect).
 */
function orabooks_handle_login_oidc_callback() {
    if (orabooks_is_explicit_logout_request()) {
        return;
    }

    if (!is_singular('page')) {
        return;
    }

    $post = get_queried_object();
    if (!$post || $post->post_name !== 'login') {
        return;
    }

    $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
    $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
    if ($code === '' || $state === '') {
        return;
    }

    if (!class_exists('OraBooks_Auth')) {
        return;
    }

    $result = OraBooks_Auth::handle_google_callback($code, $state);
    if (is_wp_error($result)) {
        wp_redirect(add_query_arg(
            'oidc_error',
            rawurlencode($result->get_error_message()),
            orabooks_get_network_login_url('login')
        ));
        exit;
    }

    if (!empty($result['requires_2fa'])) {
        return;
    }

    if (!empty($result['needs_tier_selection'])) {
        orabooks_clear_logout_landing_cookie();
        $target = orabooks_get_network_login_url('tier-selection');
        if (!empty($result['tier_selection_token'])) {
            $target = add_query_arg(
                'tier_selection_token',
                rawurlencode($result['tier_selection_token']),
                $target
            );
        }
        wp_redirect($target);
        exit;
    }

    $result = orabooks_enrich_login_response($result);
    orabooks_persist_login_session($result);

    wp_redirect($result['redirect_to'] ?? orabooks_get_org_workspace_url(
        (int) ($result['org_id'] ?? 0),
        '/dashboard/'
    ));
    exit;
}

add_action('template_redirect', 'orabooks_handle_login_oidc_callback', 1);

/**
 * Redirect logged-in customers from the main site to their org subdomain workspace.
 */
function orabooks_maybe_redirect_to_org_subdomain() {
    if (orabooks_is_explicit_logout_request()) {
        return;
    }

    if (!function_exists('orabooks_is_user_logged_in') || !orabooks_is_user_logged_in()) {
        return;
    }

    if (!is_singular('page')) {
        return;
    }

    $post = get_queried_object();
    if (!$post || empty($post->post_name)) {
        return;
    }

    $user_id = orabooks_get_current_user_id();
    if ($user_id > 0 && orabooks_is_network_auth_host() && orabooks_orabooks_user_can_manage_platform($user_id)) {
        return;
    }

    $org_id = orabooks_get_current_org_id($user_id);
    if (!$org_id || !class_exists('OraBooks_Organization') || !class_exists('OraBooks_Auth')) {
        return;
    }

    $org = OraBooks_Organization::get($org_id);
    if (!$org || empty($org->subdomain)) {
        return;
    }

    $current_subdomain = OraBooks_Auth::detect_subdomain_from_host();
    if (strtolower($current_subdomain) === strtolower($org->subdomain)) {
        return;
    }

    $shared_auth_slugs = ['login', 'register', 'reset-password', 'verify-email', 'tier-selection', 'accept-invite'];
    if (in_array($post->post_name, $shared_auth_slugs, true)) {
        if ($post->post_name === 'login') {
            if (orabooks_is_network_auth_host() && orabooks_orabooks_user_can_manage_platform($user_id)) {
                wp_redirect(orabooks_get_platform_admin_url());
                exit;
            }

            $destination = orabooks_build_org_url(
                $org->subdomain,
                $org->organization_type === 'partner' ? '/dashboard/' : '/dashboard/'
            );
            wp_redirect(orabooks_append_auth_tokens_to_url($destination));
            exit;
        }
        return;
    }

    if (!function_exists('orabooks_get_accounting_page_slugs') || !in_array($post->post_name, orabooks_get_accounting_page_slugs(), true)) {
        return;
    }

    $destination = orabooks_build_org_url($org->subdomain, '/' . $post->post_name . '/');
    wp_redirect(orabooks_append_auth_tokens_to_url($destination));
    exit;
}

add_action('template_redirect', 'orabooks_maybe_redirect_to_org_subdomain', 5);

/**
 * SL-004: block tenant subdomain access when org status does not allow it.
 */
function orabooks_enforce_subdomain_org_access() {
    if (!class_exists('OraBooks_Auth') || !class_exists('OraBooks_Organization')) {
        return;
    }

    $subdomain = OraBooks_Auth::detect_subdomain_from_host($_SERVER['HTTP_HOST'] ?? '');
    if ($subdomain === '') {
        return;
    }

    $org = OraBooks_Organization::get_by_subdomain($subdomain);
    if (!$org || orabooks_org_allows_subdomain_access($org)) {
        return;
    }

    status_header(403);
    wp_die(
        esc_html__('This organization is not active.', 'orabooks'),
        esc_html__('Forbidden', 'orabooks'),
        ['response' => 403]
    );
}

add_action('template_redirect', 'orabooks_enforce_subdomain_org_access', 0);

/**
 * Get client IP address
 */
function orabooks_get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Get current user agent
 */
function orabooks_get_user_agent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

/**
 * Whether public registration is allowed (single site or multisite network setting).
 */
function orabooks_users_can_register() {
    if (function_exists('is_multisite') && is_multisite() && function_exists('get_site_option')) {
        $registration = get_site_option('registration', 'none');
        return in_array($registration, ['user', 'all'], true);
    }

    if (function_exists('get_option')) {
        return (bool) get_option('users_can_register');
    }

    return true;
}

/**
 * Multisite networks that require email activation via wp-signup / wp-activate.
 */
function orabooks_multisite_uses_signup_activation() {
    if (!function_exists('is_multisite') || !is_multisite() || !function_exists('get_site_option')) {
        return false;
    }

    return in_array(get_site_option('registration', 'none'), ['user', 'all'], true);
}

/**
 * Build a unique WordPress username from an email address.
 */
function orabooks_generate_username_from_email($email) {
    $local = strstr($email, '@', true);
    $base = sanitize_user($local ?: 'user', true);
    if ($base === '') {
        $base = 'user';
    }

    if (!function_exists('username_exists')) {
        return $base;
    }

    $username = $base;
    $suffix = 1;
    while (username_exists($username)) {
        $username = $base . $suffix;
        $suffix++;
    }

    return $username;
}

/**
 * Create or queue a WordPress user using core registration APIs.
 *
 * @return int|array|WP_Error WordPress user ID, pending signup array, or error.
 */
function orabooks_create_wp_user_for_registration($email, $password, $meta = []) {
    if (!function_exists('wp_create_user')) {
        return 0;
    }

    if (!orabooks_users_can_register()) {
        return new WP_Error('registration_disabled', __('User registration is disabled on this site.', 'orabooks'));
    }

    if (function_exists('email_exists') && email_exists($email)) {
        return new WP_Error('email_exists', __('This email is already registered.', 'orabooks'));
    }

    $username = orabooks_generate_username_from_email($email);

    if (orabooks_multisite_uses_signup_activation() && function_exists('wpmu_signup_user')) {
        $signup_meta = array_merge($meta, [
            'password' => $password,
            'orabooks_signup' => 1,
        ]);

        $signup = wpmu_signup_user($username, $email, $signup_meta);
        if (is_wp_error($signup)) {
            return $signup;
        }

        return [
            'pending_signup' => true,
            'user_login' => $username,
        ];
    }

    if (function_exists('is_multisite') && is_multisite() && function_exists('wpmu_create_user')) {
        $wp_user_id = wpmu_create_user($username, $password, $email);
    } else {
        $wp_user_id = wp_create_user($username, $password, $email);
    }

    if (is_wp_error($wp_user_id)) {
        return $wp_user_id;
    }

    if (!$wp_user_id) {
        return new WP_Error('wp_user_failed', __('Could not create WordPress user.', 'orabooks'));
    }

    if (function_exists('is_multisite') && is_multisite() && function_exists('add_user_to_blog')) {
        $blog_id = get_current_blog_id();
        if (!is_user_member_of_blog($wp_user_id, $blog_id)) {
            add_user_to_blog($blog_id, $wp_user_id, 'subscriber');
        }
    }

    wp_update_user([
        'ID' => $wp_user_id,
        'display_name' => $email,
    ]);

    return (int) $wp_user_id;
}

/**
 * Resolve OraBooks user ID from a WordPress user ID or OraBooks ID.
 */
function orabooks_resolve_user_id($user_id = 0) {
    $user_id = $user_id ?: orabooks_get_current_user_id();
    if (!$user_id) {
        return 0;
    }

    global $wpdb;
    $table = OraBooks_Database::table('users');
    $resolved = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE wp_user_id = %d OR id = %d ORDER BY id ASC LIMIT 1",
        $user_id,
        $user_id
    ));

    return (int) $resolved;
}

/**
 * Return the first verified OraBooks JWT payload from request sources.
 *
 * @return array<string, mixed>|null
 */
function orabooks_get_verified_jwt_payload() {
    if (!class_exists('OraBooks_Secrets')) {
        return null;
    }

    $candidates = [];
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (stripos($auth_header, 'Bearer ') === 0) {
        $candidates[] = trim(substr($auth_header, 7));
    }
    if (!empty($_COOKIE['orabooks_token'])) {
        $candidates[] = sanitize_text_field(wp_unslash($_COOKIE['orabooks_token']));
    }
    if (isset($_REQUEST['orabooks_token'])) {
        $candidates[] = sanitize_text_field(wp_unslash($_REQUEST['orabooks_token']));
    }

    foreach (array_unique(array_filter($candidates)) as $token) {
        $payload = OraBooks_Secrets::verify_jwt($token);
        if ($payload && !empty($payload['user_id'])) {
            return $payload;
        }
    }

    return null;
}

/**
 * Domain variants used when setting or clearing shared auth cookies.
 *
 * @return string[]
 */
function orabooks_get_auth_cookie_domains() {
    $domains = [''];

    $configured = orabooks_get_auth_cookie_domain();
    if ($configured !== '') {
        $domains[] = $configured;
    }

    $base_domain = function_exists('orabooks_get_tenant_base_domain') ? orabooks_get_tenant_base_domain() : '';
    if ($base_domain !== '') {
        $shared = '.' . ltrim($base_domain, '.');
        if (!in_array($shared, $domains, true)) {
            $domains[] = $shared;
        }
    }

    return array_values(array_unique($domains));
}

/**
 * Resolve an OraBooks user from WordPress auth or a verified OraBooks JWT.
 */
function orabooks_resolve_authenticated_user_id() {
    $wp_user_id = get_current_user_id();
    if ($wp_user_id) {
        $resolved = orabooks_resolve_user_id((int) $wp_user_id);
        if ($resolved > 0) {
            return $resolved;
        }
    }

    $payload = orabooks_get_verified_jwt_payload();
    if ($payload) {
        return (int) $payload['user_id'];
    }

    return 0;
}

/**
 * Resolve the active OraBooks user, ignoring auth during post-logout landing.
 */
function orabooks_get_current_user_id() {
    if (orabooks_is_explicit_logout_request()) {
        return 0;
    }

    return orabooks_resolve_authenticated_user_id();
}

/**
 * Whether an OraBooks user is authenticated (WordPress session or verified JWT).
 */
function orabooks_is_user_logged_in() {
    return orabooks_get_current_user_id() > 0;
}

/**
 * Cookie lifetime for the OraBooks auth token mirror.
 */
function orabooks_get_auth_token_cookie_ttl() {
    $jwt_expiry = (int) get_option('orabooks_jwt_expiry', 3600);

    return max(300, $jwt_expiry);
}

/**
 * Cookie domain shared across tenant subdomains on multisite networks.
 */
function orabooks_get_auth_cookie_domain() {
    if (defined('COOKIEDOMAIN') && COOKIEDOMAIN) {
        return COOKIEDOMAIN;
    }

    if (function_exists('is_multisite') && is_multisite()) {
        $base_domain = orabooks_get_tenant_base_domain();
        if ($base_domain !== '') {
            return '.' . ltrim($base_domain, '.');
        }
    }

    return '';
}

/**
 * Persist the OraBooks JWT in an HTTP-only cookie for full-page loads.
 */
function orabooks_set_auth_token_cookie($token) {
    if (empty($token) || headers_sent()) {
        return;
    }

    $expiry = time() + orabooks_get_auth_token_cookie_ttl();
    $path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
    $secure = is_ssl();

    foreach (orabooks_get_auth_cookie_domains() as $domain) {
        if (PHP_VERSION_ID >= 70300) {
            setcookie('orabooks_token', $token, [
                'expires'  => $expiry,
                'path'     => $path,
                'domain'   => $domain,
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie('orabooks_token', $token, $expiry, $path, $domain, $secure, true);
        }
    }

    $_COOKIE['orabooks_token'] = $token;
}

/**
 * Clear the mirrored OraBooks auth token cookie.
 */
function orabooks_clear_auth_token_cookie() {
    $path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
    $secure = is_ssl();

    if (!headers_sent()) {
        foreach (orabooks_get_auth_cookie_domains() as $domain) {
            if (PHP_VERSION_ID >= 70300) {
                setcookie('orabooks_token', '', [
                    'expires'  => time() - 3600,
                    'path'     => $path,
                    'domain'   => $domain,
                    'secure'   => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                setcookie('orabooks_token', '', time() - 3600, $path, $domain, $secure, true);
            }
        }
    }

    unset($_COOKIE['orabooks_token']);
}

/**
 * Refresh token cookie lifetime (SL-013: default 7 days).
 */
function orabooks_get_refresh_token_cookie_ttl() {
    return max(3600, (int) get_option('orabooks_refresh_token_expiry', 604800));
}

/**
 * Persist refresh token in an HTTP-only cookie (SL-013).
 */
function orabooks_set_refresh_token_cookie($token) {
    if (empty($token) || headers_sent()) {
        return;
    }

    $expiry = time() + orabooks_get_refresh_token_cookie_ttl();
    $path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
    $secure = is_ssl();

    foreach (orabooks_get_auth_cookie_domains() as $domain) {
        if (PHP_VERSION_ID >= 70300) {
            setcookie('orabooks_refresh', $token, [
                'expires'  => $expiry,
                'path'     => $path,
                'domain'   => $domain,
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie('orabooks_refresh', $token, $expiry, $path, $domain, $secure, true);
        }
    }

    $_COOKIE['orabooks_refresh'] = $token;
}

/**
 * Clear the HTTP-only refresh token cookie.
 */
function orabooks_clear_refresh_token_cookie() {
    $path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
    $secure = is_ssl();

    if (!headers_sent()) {
        foreach (orabooks_get_auth_cookie_domains() as $domain) {
            if (PHP_VERSION_ID >= 70300) {
                setcookie('orabooks_refresh', '', [
                    'expires'  => time() - 3600,
                    'path'     => $path,
                    'domain'   => $domain,
                    'secure'   => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                setcookie('orabooks_refresh', '', time() - 3600, $path, $domain, $secure, true);
            }
        }
    }

    unset($_COOKIE['orabooks_refresh']);
}

/**
 * Resolve refresh token from HTTP-only cookie or legacy request param.
 */
function orabooks_get_refresh_token_from_request() {
    if (!empty($_COOKIE['orabooks_refresh'])) {
        return sanitize_text_field(wp_unslash($_COOKIE['orabooks_refresh']));
    }

    if (isset($_REQUEST['refresh_token'])) {
        return sanitize_text_field(wp_unslash($_REQUEST['refresh_token']));
    }

    return '';
}

/**
 * Remove refresh tokens from API payloads — they belong in HTTP-only cookies.
 *
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function orabooks_redact_client_auth_response($payload) {
    if (!is_array($payload)) {
        return $payload;
    }

    unset($payload['refresh_token']);
    return $payload;
}

/**
 * Store encrypted TOTP secret for a linked WordPress user.
 */
function orabooks_set_2fa_secret($wp_user_id, $secret) {
    if ($wp_user_id <= 0 || $secret === '') {
        return;
    }

    $stored = class_exists('OraBooks_Secrets')
        ? OraBooks_Secrets::encrypt_sensitive($secret)
        : $secret;

    update_user_meta((int) $wp_user_id, 'orabooks_2fa_secret', $stored);
}

/**
 * Read TOTP secret for a linked WordPress user.
 */
function orabooks_get_2fa_secret($wp_user_id) {
    if ($wp_user_id <= 0) {
        return '';
    }

    $stored = get_user_meta((int) $wp_user_id, 'orabooks_2fa_secret', true);
    if ($stored === '' || $stored === null) {
        return '';
    }

    if (class_exists('OraBooks_Secrets')) {
        return OraBooks_Secrets::decrypt_sensitive($stored);
    }

    return (string) $stored;
}

/**
 * Resolve user ID from a tier-selection JWT (SL-013: no session until org exists).
 */
function orabooks_resolve_tier_selection_user_id($token = '') {
    if ($token === '') {
        $token = sanitize_text_field(wp_unslash($_REQUEST['tier_selection_token'] ?? ''));
    }

    if ($token === '' || !class_exists('OraBooks_Secrets')) {
        return 0;
    }

    $payload = OraBooks_Secrets::verify_jwt($token);
    if (!$payload || ($payload['purpose'] ?? '') !== 'tier_selection' || empty($payload['user_id'])) {
        return 0;
    }

    return (int) $payload['user_id'];
}

/**
 * Whether the current request landed with an explicit logout / session-reset query flag.
 */
function orabooks_has_logout_query_flag() {
    if (isset($_GET['logged_out']) && (string) $_GET['logged_out'] === '1') {
        return true;
    }

    if (isset($_GET['auth_reset']) && (string) $_GET['auth_reset'] === '1') {
        return true;
    }

    if (isset($_GET['session_expired']) && (string) $_GET['session_expired'] === '1') {
        return true;
    }

    return false;
}

/**
 * Whether the current request is landing on login after an explicit logout.
 * Query flags only — the orabooks_logout cookie must not block new logins.
 */
function orabooks_is_explicit_logout_request() {
    return orabooks_has_logout_query_flag();
}

/**
 * Whether stale auth should be cleared (query flag or one-shot logout cookie).
 */
function orabooks_needs_logout_cleanup() {
    if (orabooks_has_logout_query_flag()) {
        return true;
    }

    return !empty($_COOKIE['orabooks_logout']);
}

/**
 * Short-lived cookie so logout cleanup survives URL param stripping in the browser.
 */
function orabooks_set_logout_landing_cookie() {
    if (headers_sent()) {
        $_COOKIE['orabooks_logout'] = '1';
        return;
    }

    $path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
    $secure = is_ssl();

    foreach (orabooks_get_auth_cookie_domains() as $domain) {
        if (PHP_VERSION_ID >= 70300) {
            setcookie('orabooks_logout', '1', [
                'expires'  => time() + 600,
                'path'     => $path,
                'domain'   => $domain,
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie('orabooks_logout', '1', time() + 600, $path, $domain, $secure, true);
        }
    }

    $_COOKIE['orabooks_logout'] = '1';
}

/**
 * Clear the post-logout landing marker cookie.
 */
function orabooks_clear_logout_landing_cookie() {
    $path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
    $secure = is_ssl();

    if (!headers_sent()) {
        foreach (orabooks_get_auth_cookie_domains() as $domain) {
            if (PHP_VERSION_ID >= 70300) {
                setcookie('orabooks_logout', '', [
                    'expires'  => time() - 3600,
                    'path'     => $path,
                    'domain'   => $domain,
                    'secure'   => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                setcookie('orabooks_logout', '', time() - 3600, $path, $domain, $secure, true);
            }
        }
    }

    unset($_COOKIE['orabooks_logout']);
}

/**
 * Login URL used after logout — includes a flag so redirects do not re-authenticate.
 */
function orabooks_get_logout_redirect_url() {
    return add_query_arg('logged_out', '1', orabooks_get_network_login_url('login'));
}

/**
 * Clear WordPress logged-in cookies across multisite domain variants.
 */
function orabooks_clear_wp_auth_cookies() {
    if (function_exists('wp_clear_auth_cookie')) {
        wp_clear_auth_cookie();
    }

    if (headers_sent()) {
        return;
    }

    $cookie_hash = defined('COOKIEHASH') ? COOKIEHASH : '';
    if ($cookie_hash === '') {
        return;
    }

    $names = [
        'wordpress_logged_in_' . $cookie_hash,
        'wordpress_' . $cookie_hash,
        'wordpress_sec_' . $cookie_hash,
    ];
    $paths = array_values(array_unique(array_filter([
        defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/',
        defined('SITECOOKIEPATH') && SITECOOKIEPATH ? SITECOOKIEPATH : '/',
        '/',
    ])));
    $secure = is_ssl();
    $expired = time() - 3600;

    foreach (orabooks_get_auth_cookie_domains() as $domain) {
        foreach ($names as $name) {
            foreach ($paths as $path) {
                if (PHP_VERSION_ID >= 70300) {
                    setcookie($name, '', [
                        'expires'  => $expired,
                        'path'     => $path,
                        'domain'   => $domain,
                        'secure'   => $secure,
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                } else {
                    setcookie($name, '', $expired, $path, $domain, $secure, true);
                }
            }
            unset($_COOKIE[$name]);
        }
    }
}

/**
 * Fully tear down OraBooks + WordPress auth state.
 */
function orabooks_destroy_auth_session($user_id = 0, $log = true, $set_landing_cookie = null) {
    if ($set_landing_cookie === null) {
        $set_landing_cookie = $log;
    }

    if ($user_id <= 0) {
        $user_id = orabooks_resolve_authenticated_user_id();
    }

    if ($user_id > 0 && class_exists('OraBooks_Auth')) {
        OraBooks_Auth::revoke_user_tokens($user_id);
    }

    orabooks_clear_auth_token_cookie();
    orabooks_clear_refresh_token_cookie();
    orabooks_clear_wp_auth_cookies();

    if (function_exists('wp_logout')) {
        wp_logout();
    }

    if (function_exists('wp_set_current_user')) {
        wp_set_current_user(0);
    }

    unset($_COOKIE['orabooks_token']);
    if ($set_landing_cookie) {
        orabooks_set_logout_landing_cookie();
    }

    if ($log && $user_id > 0) {
        orabooks_log_event('user_logged_out', 'User logged out', 'info', [], $user_id, null);
    }
}

/**
 * On post-logout landing, force-clear any lingering cookies before redirect guards run.
 */
function orabooks_force_logout_cleanup() {
    if (!orabooks_needs_logout_cleanup()) {
        return;
    }

    orabooks_destroy_auth_session(0, false, false);
    orabooks_clear_logout_landing_cookie();
}

add_action('init', 'orabooks_force_logout_cleanup', 0);

/**
 * Establish a WordPress session for a linked OraBooks user.
 */
function orabooks_establish_wp_session_for_orabooks_user($orabooks_user_id, $password = '') {
    if (!function_exists('wp_set_auth_cookie')) {
        require_once ABSPATH . 'wp-includes/pluggable.php';
    }

    global $wpdb;
    $table_users = OraBooks_Database::table('users');
    $user = $wpdb->get_row($wpdb->prepare(
        "SELECT id, email, wp_user_id FROM {$table_users} WHERE id = %d",
        (int) $orabooks_user_id
    ));

    if (!$user) {
        return 0;
    }

    if (!empty($user->wp_user_id)) {
        $wp_user = get_user_by('id', (int) $user->wp_user_id);
        if ($wp_user) {
            if (function_exists('is_multisite') && is_multisite() && function_exists('add_user_to_blog')) {
                $blog_id = get_current_blog_id();
                if ($blog_id > 0 && !is_user_member_of_blog($wp_user->ID, $blog_id)) {
                    add_user_to_blog($blog_id, $wp_user->ID, 'subscriber');
                }
            }

            wp_set_current_user($wp_user->ID);
            wp_set_auth_cookie($wp_user->ID, true, is_ssl());
            do_action('wp_login', $wp_user->user_login, $wp_user);

            return (int) $wp_user->ID;
        }
    }

    if ($password === '' || !function_exists('wp_signon')) {
        return 0;
    }

    $signon_attempts = [$user->email];
    if (function_exists('orabooks_generate_username_from_email')) {
        $signon_attempts[] = orabooks_generate_username_from_email($user->email);
    }

    foreach (array_unique(array_filter($signon_attempts)) as $login_name) {
        $signed_on = wp_signon([
            'user_login'    => $login_name,
            'user_password' => $password,
            'remember'      => true,
        ], is_ssl());

        if (!is_wp_error($signed_on)) {
            if (empty($user->wp_user_id)) {
                $wpdb->update(
                    $table_users,
                    ['wp_user_id' => $signed_on->ID],
                    ['id' => $user->id],
                    ['%d'],
                    ['%d']
                );
            }

            return (int) $signed_on->ID;
        }
    }

    return 0;
}

/**
 * Mirror JWT auth into cookie + WordPress session after login flows.
 */
function orabooks_persist_login_session($login_result, $password = '') {
    if (!is_array($login_result)) {
        return;
    }

    orabooks_clear_logout_landing_cookie();

    if (!empty($login_result['token'])) {
        orabooks_set_auth_token_cookie($login_result['token']);
    }

    if (!empty($login_result['refresh_token'])) {
        orabooks_set_refresh_token_cookie($login_result['refresh_token']);
    }

    $user_id = !empty($login_result['user_id']) ? (int) $login_result['user_id'] : 0;
    if ($user_id > 0) {
        orabooks_establish_wp_session_for_orabooks_user($user_id, $password);
    }
}

/**
 * Sync WordPress auth when a valid OraBooks JWT cookie is present.
 */
function orabooks_sync_wp_session_from_auth_token() {
    if (orabooks_is_explicit_logout_request()) {
        return;
    }

    if (is_user_logged_in()) {
        return;
    }

    $orabooks_user_id = orabooks_get_current_user_id();
    if ($orabooks_user_id > 0) {
        orabooks_establish_wp_session_for_orabooks_user($orabooks_user_id);
    }
}

add_action('init', 'orabooks_sync_wp_session_from_auth_token', 1);

/**
 * Check rate limit
 */
function orabooks_check_rate_limit($key, $max_attempts, $period_seconds = 3600) {
    $transient_key = 'orabooks_rate_' . md5($key);
    $attempts = get_transient($transient_key);
    
    if ($attempts === false) {
        set_transient($transient_key, 1, $period_seconds);
        return true;
    }
    
    if ($attempts >= $max_attempts) {
        if (class_exists('OraBooks_Security')) {
            OraBooks_Security::record_incident('rate_limit_exceeded', 'warning', [
                'key'    => sanitize_text_field(substr($key, 0, 64)),
                'max'    => (int) $max_attempts,
                'period' => (int) $period_seconds,
            ]);
        }
        return false;
    }
    
    set_transient($transient_key, $attempts + 1, $period_seconds);
    return true;
}

/**
 * Validate input against SL-099 schema; sends 400 JSON error on failure.
 *
 * @return true Exits via orabooks_json_error on invalid input.
 */
function orabooks_validate_schema($schema_key, $value) {
    if (!class_exists('OraBooks_Security')) {
        return true;
    }

    $result = OraBooks_Security::validate_input($schema_key, $value);
    if (is_wp_error($result)) {
        orabooks_json_error(__('Invalid input format.', 'orabooks'), 400);
    }

    return true;
}

/**
 * Log audit event
 */
function orabooks_log_event($event_type, $description, $severity = 'info', $metadata = null, $user_id = null, $org_id = null) {
    if (function_exists('OraBooks_Audit') && method_exists('OraBooks_Audit', 'log_event')) {
        return OraBooks_Audit::log_event($event_type, $description, $severity, $metadata, $user_id, $org_id);
    }
    return false;
}

/**
 * Get user role in organization
 */
function orabooks_get_user_role($user_id, $org_id) {
    global $wpdb;
    $table = orabooks_get_table_prefix() . 'orabooks_user_org';
    return $wpdb->get_var($wpdb->prepare(
        "SELECT role FROM {$table} WHERE user_id = %d AND org_id = %d",
        $user_id,
        $org_id
    ));
}

/**
 * Check if user has permission
 */
function orabooks_has_permission($user_id, $org_id, $permission) {
    $role = orabooks_get_user_role($user_id, $org_id);
    if (!$role) {
        return false;
    }
    return OraBooks_RBAC::check_permission($role, $permission, $org_id);
}

/**
 * JSON response helper
 */
function orabooks_json_response($data, $status_code = 200) {
    wp_send_json($data, $status_code);
}

/**
 * Error response helper
 */
function orabooks_json_error($message, $status_code = 400) {
    if (class_exists('OraBooks_Security') && method_exists('OraBooks_Security', 'record_http_response')) {
        OraBooks_Security::record_http_response($status_code, $message);
    }
    wp_send_json(['error' => true, 'message' => $message], $status_code);
}

/**
 * Success response helper
 */
function orabooks_json_success($data = [], $message = '') {
    wp_send_json(['error' => false, 'message' => $message, 'data' => $data]);
}

/**
 * Hash a token for storage (SHA-256)
 */
function orabooks_hash_token($token) {
    return hash('sha256', $token);
}

/**
 * Generate a UUID v4
 */
function orabooks_uuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Get user display name
 */
function orabooks_get_user_email($user_id) {
    global $wpdb;
    $table = orabooks_get_table_prefix() . 'orabooks_users';
    return $wpdb->get_var($wpdb->prepare("SELECT email FROM {$table} WHERE id = %d", $user_id));
}

/**
 * Mask email for display
 */
function orabooks_mask_email($email) {
    $parts = explode('@', $email);
    $name = $parts[0];
    $domain = $parts[1] ?? '';
    $masked = substr($name, 0, 2) . str_repeat('*', max(0, strlen($name) - 2));
    return $masked . '@' . $domain;
}

/**
 * Resolve the active organization ID for the current request (SL-004).
 *
 * Prefers the org mapped to the request subdomain, then falls back to the
 * user's primary org membership.
 */
function orabooks_get_current_org_id($user_id = 0) {
    $user_id = $user_id ?: orabooks_get_current_user_id();

    if (class_exists('OraBooks_Auth') && class_exists('OraBooks_Organization')) {
        $subdomain = OraBooks_Auth::detect_subdomain_from_host();
        if ($subdomain !== '') {
            $org = OraBooks_Organization::get_by_subdomain($subdomain);
            if ($org && !orabooks_org_allows_subdomain_access($org)) {
                return 0;
            }
            if ($org) {
                if (!$user_id) {
                    return (int) $org->id;
                }

                if ((int) $org->owner_id === (int) $user_id) {
                    return (int) $org->id;
                }

                global $wpdb;
                $table_user_org = OraBooks_Database::table('user_org');
                $membership = $wpdb->get_var($wpdb->prepare(
                    "SELECT org_id FROM {$table_user_org} WHERE user_id = %d AND org_id = %d LIMIT 1",
                    $user_id,
                    $org->id
                ));

                if ($membership) {
                    return (int) $org->id;
                }
            }
        }
    }

    if (!$user_id) {
        return 0;
    }

    global $wpdb;
    $table_users = OraBooks_Database::table('users');
    $org_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT org_id FROM {$table_users} WHERE id = %d",
        $user_id
    ));

    if (!$org_id) {
        $table_user_org = OraBooks_Database::table('user_org');
        $org_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT org_id FROM {$table_user_org} WHERE user_id = %d ORDER BY joined_at ASC LIMIT 1",
            $user_id
        ));
    }

    return $org_id;
}

/**
 * Register an OraBooks addon module (e.g. Frontend Accounting).
 */
function orabooks_register_addon($addon) {
    if (empty($addon['id'])) {
        return false;
    }

    $addons = get_option('orabooks_addons', []);
    if (!is_array($addons)) {
        $addons = [];
    }

    $addons[$addon['id']] = $addon;
    update_option('orabooks_addons', $addons, false);

    return true;
}

/**
 * Get all registered OraBooks addons.
 */
function orabooks_get_addons() {
    $addons = get_option('orabooks_addons', []);
    return is_array($addons) ? $addons : [];
}

/**
 * Legacy compatibility helper used by addon plugins.
 */
function orabooks_is_feature_enabled($feature_id) {
    $feature_id = sanitize_key($feature_id);
    $addons = orabooks_get_addons();

    foreach ($addons as $addon) {
        if (empty($addon['features']) || !is_array($addon['features'])) {
            continue;
        }

        if (!isset($addon['features'][$feature_id])) {
            continue;
        }

        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $org_id = orabooks_get_current_org_id($user_id);
        if (!$org_id) {
            return false;
        }

        if (class_exists('OraBooks_Auth')) {
            $allowed = OraBooks_Auth::require_customer_org($user_id, $org_id);
            if (is_wp_error($allowed)) {
                return false;
            }
        }

        return true;
    }

    return false;
}
