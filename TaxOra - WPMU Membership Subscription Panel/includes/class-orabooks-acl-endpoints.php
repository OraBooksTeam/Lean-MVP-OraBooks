<?php
/**
 * SL-003 – RBAC / ABAC: ACL Check Endpoints & Middleware
 *
 * This provides:
 * - AJAX endpoints for frontend permission checking
 * - requireCustomerOrg() middleware for accounting endpoint isolation
 * - Permission matrix API for UI display
 * - Ingress subdomain blocking helper
 *
 * Build Order: SL-004 → SL-013 → SL-003 → SL-014 → SL-017 → SL-139 → SL-068
 *
 * Dependencies:
 * - OraBooks_RBAC (class-orabooks-rbac.php)
 * - OraBooks_Organizations (class-orabooks-organizations.php)
 * - OraBooks_Users_Teams (class-orabooks-users-teams.php)
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_ACL_Endpoints {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - register all hooks
     */
    private function __construct() {
        // Permission check endpoints (both logged-in and non-logged-in where appropriate)
        add_action('wp_ajax_orabooks_check_permission', array($this, 'ajax_check_permission'));
        add_action('wp_ajax_orabooks_get_user_permissions', array($this, 'ajax_get_user_permissions'));
        add_action('wp_ajax_orabooks_get_permission_matrix', array($this, 'ajax_get_permission_matrix'));
        add_action('wp_ajax_orabooks_require_customer_org', array($this, 'ajax_require_customer_org'));
        add_action('wp_ajax_orabooks_get_accessible_permissions', array($this, 'ajax_get_accessible_permissions'));
        add_action('wp_ajax_orabooks_check_feature_access_by_role', array($this, 'ajax_check_feature_access_by_role'));
        add_action('wp_ajax_orabooks_get_org_type', array($this, 'ajax_get_org_type'));

        // Filter for external plugin integration
        add_filter('orabooks_require_customer_org', array($this, 'filter_require_customer_org'), 10, 3);
    }

    // ================================================================
    // SECURITY HELPERS
    // ================================================================

    /**
     * Verify AJAX nonce and return current user/org context.
     *
     * @return array|WP_Error { user_id, org_id, role } or error
     */
    private function get_error_status($error, $default = 403) {
        $error_data = $error->get_error_data($error->get_error_code());
        if (is_array($error_data) && isset($error_data['status'])) {
            return (int) $error_data['status'];
        }
        return $default;
    }

    /**
     * Get authenticated user context with nonce verification.
     */
    private function get_authenticated_context() {
        if (!is_user_logged_in()) {
            return new WP_Error('not_logged_in', __('Authentication required.', 'orabooks'), array('status' => 401));
        }

        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'orabooks_acl_nonce')) {
            return new WP_Error('invalid_nonce', __('Security check failed.', 'orabooks'), array('status' => 403));
        }

        $user_id = get_current_user_id();
        $org_id = isset($_POST['org_id']) ? intval($_POST['org_id']) : 0;

        // If no org_id specified, try to get from user meta or JWT
        if ($org_id <= 0) {
            // Check if JWT class provides org_id from the current request
            $org_id = $this->get_current_user_org_id($user_id);
        }

        // Get role
        $role = '';
        if ($org_id > 0 && class_exists('OraBooks_Users_Teams')) {
            $role = OraBooks_Users_Teams::get_instance()->get_user_role($user_id, $org_id);
        }

        return array(
            'user_id' => $user_id,
            'org_id'  => $org_id,
            'role'    => $role,
        );
    }

    /**
     * Get the current user's active organization ID.
     * Checks user_org first, then falls back to user meta.
     *
     * @param int $user_id User ID
     * @return int Organization ID or 0
     */
    private function get_current_user_org_id($user_id) {
        // Try to get from user_org table (first membership)
        if (class_exists('OraBooks_Users_Teams')) {
            $orgs = OraBooks_Users_Teams::get_instance()->get_user_organizations($user_id);
            if (!empty($orgs)) {
                return (int)$orgs[0]->org_id;
            }
        }

        // Fallback: check user meta
        $org_id = get_user_meta($user_id, 'orabooks_org_id', true);
        if (!empty($org_id)) {
            return (int)$org_id;
        }

        return 0;
    }

    // ================================================================
    // AJAX ENDPOINTS
    // ================================================================

    /**
     * AJAX: Check if the current user has a specific permission in their org.
     *
     * Request: { nonce, permission, org_id? }
     * Response: { allowed: bool, role: string, reason: string }
     */
    public function ajax_check_permission() {
        $context = $this->get_authenticated_context();
        if (is_wp_error($context)) {
            $status = $this->get_error_status($context, 403);
            wp_send_json_error($context->get_error_message(), $status);
        }

        $permission = isset($_POST['permission']) ? sanitize_text_field($_POST['permission']) : '';
        if (empty($permission)) {
            wp_send_json_error(__('Permission key is required.', 'orabooks'), 400);
        }

        $user_id = $context['user_id'];
        $org_id  = $context['org_id'];

        if ($org_id <= 0) {
            wp_send_json_success(array(
                'allowed'   => false,
                'role'      => $context['role'],
                'org_id'    => 0,
                'reason'    => __('No organization context.', 'orabooks'),
                'permission'=> $permission,
            ));
        }

        $allowed = false;
        $reason = '';

        if (class_exists('OraBooks_RBAC')) {
            try {
                OraBooks_RBAC::get_instance()->require_permission_or_fail($user_id, $org_id, $permission);
                $allowed = true;
            } catch (Exception $e) {
                $allowed = false;
                $reason = $e->getMessage();
            }
        } else {
            // Fallback: check role-based permissions from Users_Teams
            if (class_exists('OraBooks_Users_Teams')) {
                $allowed = OraBooks_Users_Teams::get_instance()->has_permission($user_id, $org_id, $permission);
            }
            if (!$allowed) {
                $reason = __('Permission denied for this role.', 'orabooks');
            }
        }

        wp_send_json_success(array(
            'allowed'    => $allowed,
            'role'       => $context['role'],
            'org_id'     => $org_id,
            'reason'     => $reason,
            'permission' => $permission,
        ));
    }

    /**
     * AJAX: Get all permissions for the current user in their org.
     * Used by frontend to enable/disable UI elements.
     *
     * Request: { nonce, org_id? }
     * Response: { permissions: string[], role: string, org_type: string }
     */
    public function ajax_get_user_permissions() {
        $context = $this->get_authenticated_context();
        if (is_wp_error($context)) {
            $status = $this->get_error_status($context, 403);
            wp_send_json_error($context->get_error_message(), $status);
        }

        $user_id = $context['user_id'];
        $org_id  = $context['org_id'];
        $role    = $context['role'];

        if (empty($role) || $org_id <= 0) {
            wp_send_json_success(array(
                'permissions' => array(),
                'role'        => $role,
                'org_id'      => $org_id,
                'org_type'    => '',
            ));
        }

        $permissions = array();

        if (class_exists('OraBooks_RBAC')) {
            $permissions = OraBooks_RBAC::get_instance()->get_permissions_for_role($role, $org_id);
        } elseif (class_exists('OraBooks_Users_Teams')) {
            // Fallback: build from Users_Teams simplified permission list
            $permission_map = array(
                'owner' => array(
                    'invite_user', 'change_role', 'remove_user', 'view_team',
                    'view_pending_invites', 'resend_invite', 'cancel_invite',
                    'transfer_ownership', 'manage_org', 'view_all',
                    'manage_teams', 'manage_team_members', 'partner_commission_access',
                    'view_reports', 'submit_transaction', 'approve_journal',
                    'view_coa', 'export_reports', 'view_audit_logs',
                    'access_invoicing', 'access_inventory', 'access_banking', 'access_reports',
                ),
                'admin' => array(
                    'invite_user', 'view_team', 'view_pending_invites',
                    'resend_invite', 'cancel_invite', 'partner_commission_access',
                    'view_reports', 'submit_transaction', 'approve_journal',
                    'export_reports', 'view_audit_logs',
                    'access_invoicing', 'access_inventory', 'access_banking', 'access_reports',
                ),
                'approver' => array(
                    'view_team', 'approve_journal', 'view_reports', 'access_reports',
                ),
                'staff' => array(
                    'view_team', 'submit_transaction', 'view_reports',
                    'export_reports', 'access_invoicing', 'access_inventory', 'access_banking', 'access_reports',
                ),
                'viewer' => array(
                    'view_team', 'view_reports', 'access_reports',
                ),
            );
            $permissions = isset($permission_map[$role]) ? $permission_map[$role] : array();
        }

        // Get org type for UI context
        $org_type = '';
        if ($org_id > 0 && class_exists('OraBooks_Organizations')) {
            $org = OraBooks_Organizations::get_instance()->get_organization($org_id);
            if ($org) {
                $org_type = $org->organization_type;
            }
        }

        wp_send_json_success(array(
            'permissions' => $permissions,
            'role'        => $role,
            'org_id'      => $org_id,
            'org_type'    => $org_type,
            'is_partner'  => $org_type === 'partner',
        ));
    }

    /**
     * AJAX: Get the full permission matrix with role-to-action mapping.
     * Used by UI to display "Permissions" tab (Roles & Access).
     *
     * Request: { nonce, org_id? }
     * Response: { matrix: array[], partner_restricted: array[] }
     */
    public function ajax_get_permission_matrix() {
        // Less restrictive: any logged-in user can view the matrix
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Authentication required.', 'orabooks'), 401);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'orabooks_acl_nonce')) {
            wp_send_json_error(__('Security check failed.', 'orabooks'), 403);
        }

        $matrix = array();
        $partner_restricted = array();

        if (class_exists('OraBooks_RBAC')) {
            $matrix = OraBooks_RBAC::get_permission_matrix();
            $partner_restricted = OraBooks_RBAC::get_partner_restricted_permissions();
        }

        wp_send_json_success(array(
            'matrix'            => $matrix,
            'partner_restricted'=> $partner_restricted,
            'roles'             => array('owner', 'admin', 'approver', 'staff', 'viewer'),
        ));
    }

    /**
     * AJAX: requireCustomerOrg() middleware endpoint.
     * Blocks partner orgs from accessing accounting endpoints.
     * Returns 403 if blocked with reason.
     *
     * Request: { nonce, org_id? }
     * Response: { allowed: bool, reason: string, org_type: string }
     */
    public function ajax_require_customer_org() {
        $context = $this->get_authenticated_context();
        if (is_wp_error($context)) {
            $status = $this->get_error_status($context, 403);
            wp_send_json_error($context->get_error_message(), $status);
        }

        $org_id = $context['org_id'];
        $user_id = $context['user_id'];

        $result = $this->check_customer_org($user_id, $org_id);

        if (!$result['allowed']) {
            // Error response matching 403 pattern
            wp_send_json_error(array(
                'message'  => $result['reason'],
                'org_type' => $result['org_type'],
                'org_id'   => $org_id,
            ), 403);
        }

        wp_send_json_success(array(
            'allowed'  => true,
            'org_type' => $result['org_type'],
            'org_id'   => $org_id,
        ));
    }

    /**
     * AJAX: Get only the permissions the current user can actually exercise.
     * Filters out accounting permissions for partner orgs.
     *
     * Request: { nonce, org_id?, context? (e.g., 'accounting', 'team', 'partner') }
     * Response: { permissions: string[], role: string, org_type: string }
     */
    public function ajax_get_accessible_permissions() {
        $context = $this->get_authenticated_context();
        if (is_wp_error($context)) {
            $status = $this->get_error_status($context, 403);
            wp_send_json_error($context->get_error_message(), $status);
        }

        $user_id    = $context['user_id'];
        $org_id     = $context['org_id'];
        $role       = $context['role'];
        $req_context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : '';

        if (empty($role) || $org_id <= 0) {
            wp_send_json_success(array(
                'permissions' => array(),
                'role'        => $role,
                'org_id'      => $org_id,
            ));
        }

        // Get base permissions from RBAC
        $all_permissions = array();
        if (class_exists('OraBooks_RBAC')) {
            $all_permissions = OraBooks_RBAC::get_instance()->get_permissions_for_role($role, $org_id);
        }

        // Get org type for filtering
        $org_type = '';
        $is_partner = false;
        if ($org_id > 0 && class_exists('OraBooks_Organizations')) {
            $org = OraBooks_Organizations::get_instance()->get_organization($org_id);
            if ($org) {
                $org_type = $org->organization_type;
                $is_partner = ($org_type === 'partner');
            }
        }

        // Filter permissions based on context and org type
        $filtered_permissions = $all_permissions;

        if ($is_partner && class_exists('OraBooks_RBAC')) {
            // Remove accounting-related permissions for partner orgs
            $restricted = OraBooks_RBAC::PARTNER_RESTRICTED_PERMISSIONS;
            $filtered_permissions = array_values(array_diff($all_permissions, $restricted));
        }

        // Further filter by request context if specified
        if (!empty($req_context)) {
            $context_map = array(
                'accounting' => array(
                    'view_reports', 'submit_transaction', 'approve_journal',
                    'view_coa', 'export_reports', 'access_invoicing',
                    'access_inventory', 'access_banking', 'access_reports',
                ),
                'team' => array(
                    'invite_user', 'change_role', 'remove_user', 'view_team',
                    'view_pending_invites', 'resend_invite', 'cancel_invite',
                    'transfer_ownership',
                ),
                'partner' => array(
                    'partner_commission_access',
                ),
                'audit' => array(
                    'view_audit_logs',
                ),
                'admin' => array(
                    'manage_org', 'change_tier', 'manage_teams', 'manage_team_members',
                    'view_audit_logs', 'change_role', 'invite_user', 'remove_user',
                ),
            );

            if (isset($context_map[$req_context])) {
                $filtered_permissions = array_values(
                    array_intersect($filtered_permissions, $context_map[$req_context])
                );
            }
        }

        wp_send_json_success(array(
            'permissions' => $filtered_permissions,
            'role'        => $role,
            'org_id'      => $org_id,
            'org_type'    => $org_type,
            'is_partner'  => $is_partner,
            'context'     => $req_context,
        ));
    }

    /**
     * AJAX: Check if a feature is accessible based on the user's role.
     * This bridges SL-003 RBAC with feature-level access control.
     *
     * Request: { nonce, feature_key, org_id? }
     * Response: { allowed: bool, reason: string }
     */
    public function ajax_check_feature_access_by_role() {
        $context = $this->get_authenticated_context();
        if (is_wp_error($context)) {
            $status = $this->get_error_status($context, 403);
            wp_send_json_error($context->get_error_message(), $status);
        }

        $feature_key = isset($_POST['feature_key']) ? sanitize_text_field($_POST['feature_key']) : '';
        if (empty($feature_key)) {
            wp_send_json_error(__('Feature key is required.', 'orabooks'), 400);
        }

        $user_id = $context['user_id'];
        $org_id  = $context['org_id'];

        $allowed = false;
        $reason  = '';

        // Use the enhanced feature access check
        if (class_exists('OraBooks_RBAC')) {
            $rbac = OraBooks_RBAC::get_instance();

            // Check if the feature maps to a known permission
            $feature_permission_map = array(
                'invoices'      => 'access_invoicing',
                'expenses'      => 'submit_transaction',
                'reports'       => 'access_reports',
                'inventory'     => 'access_inventory',
                'banking'       => 'access_banking',
                'chart_of_accounts' => 'view_coa',
                'audit_logs'    => 'view_audit_logs',
                'team'          => 'view_team',
                'commissions'   => 'partner_commission_access',
                'ai_features'   => 'access_ai_features',
            );

            $permission = isset($feature_permission_map[$feature_key])
                ? $feature_permission_map[$feature_key]
                : $feature_key;

            try {
                $rbac->require_permission_or_fail($user_id, $org_id, $permission);
                $allowed = true;
            } catch (Exception $e) {
                $allowed = false;
                $reason = $e->getMessage();
            }
        } elseif (class_exists('OraBooks_Permission_Matrix')) {
            // Fallback to permission matrix check
            $role = OraBooks_Permission_Matrix::get_user_role($user_id, $org_id);
            $mode = OraBooks_Permission_Matrix::get_current_mode($user_id);
            $action = $this->map_feature_to_matrix_action($feature_key);
            $result = OraBooks_Permission_Matrix::check_permission($user_id, $role, $mode, $action);
            $allowed = $result['allowed'];
            $reason  = $result['reason'];
        }

        // Also check if feature access manager has a say
        if (!$allowed && class_exists('OraBooks_Feature_Access_Manager')) {
            $access = OraBooks_Feature_Access_Manager::validate_access($feature_key, 'view', $user_id);
            $allowed = $access['allowed'];
            $reason  = $access['reason'];
        }

        wp_send_json_success(array(
            'allowed'     => $allowed,
            'feature_key' => $feature_key,
            'reason'      => $reason,
            'role'        => $context['role'],
            'org_id'      => $org_id,
        ));
    }

    /**
     * AJAX: Get the organization type for the current user's context.
     * Quick endpoint for frontend to know if user is in a partner org.
     *
     * Request: { nonce, org_id? }
     * Response: { org_type: string, is_partner: bool, tier: string, status: string }
     */
    public function ajax_get_org_type() {
        $context = $this->get_authenticated_context();
        if (is_wp_error($context)) {
            // Allow unauthenticated to check public org info by explicit org_id
            $org_id = isset($_POST['org_id']) ? intval($_POST['org_id']) : 0;
            if ($org_id > 0 && class_exists('OraBooks_Organizations')) {
                $org = OraBooks_Organizations::get_instance()->get_organization($org_id);
                if ($org) {
                    wp_send_json_success(array(
                        'org_type'    => $org->organization_type,
                        'is_partner'  => $org->organization_type === 'partner',
                        'tier'        => $org->tier,
                        'status'      => $org->status,
                        'subdomain'   => $org->subdomain,
                        'org_id'      => $org->id,
                    ));
                }
            }
            wp_send_json_error(__('Authentication required.', 'orabooks'), 401);
        }

        $org_id = $context['org_id'];

        if ($org_id <= 0) {
            wp_send_json_success(array(
                'org_type'    => '',
                'is_partner'  => false,
                'org_id'      => 0,
            ));
        }

        if (class_exists('OraBooks_Organizations')) {
            $org = OraBooks_Organizations::get_instance()->get_organization($org_id);
            if ($org) {
                wp_send_json_success(array(
                    'org_type'    => $org->organization_type,
                    'is_partner'  => $org->organization_type === 'partner',
                    'tier'        => $org->tier,
                    'status'      => $org->status,
                    'subdomain'   => $org->subdomain,
                    'org_id'      => $org->id,
                ));
            }
        }

        wp_send_json_success(array(
            'org_type'   => '',
            'is_partner' => false,
            'org_id'     => $org_id,
        ));
    }

    // ================================================================
    // MIDDLEWARE HELPERS (for other plugins to call)
    // ================================================================

    /**
     * SL-003 §5.6: requireCustomerOrg() middleware.
     * Checks if an org is allowed to access accounting APIs.
     * Blocks partner orgs and returns 403 with audit log.
     *
     * Use this at the top of accounting API endpoints:
     *   $check = OraBooks_ACL_Endpoints::require_customer_org($user_id, $org_id);
     *   if (is_wp_error($check)) { return $check; }
     *
     * @param int  $user_id User ID
     * @param int  $org_id  Organization ID
     * @param bool $silent  If true, don't trigger wp_send_json_error (return WP_Error instead)
     * @return true|WP_Error True if allowed, WP_Error if blocked
     */
    public static function require_customer_org($user_id, $org_id, $silent = false) {
        $instance = self::get_instance();
        $result = $instance->check_customer_org($user_id, $org_id);

        if (!$result['allowed']) {
            $error = new WP_Error(
                'partner_org_blocked',
                $result['reason'],
                array('status' => 403, 'org_type' => $result['org_type'])
            );

            if (!$silent) {
                wp_send_json_error($error->get_error_message(), 403);
            }

            return $error;
        }

        return true;
    }

    /**
     * SL-003 §5.15: Ingress subdomain blocking helper.
     * Extracts subdomain from host, looks up org, and blocks
     * partner orgs from accounting endpoints.
     *
     * Use at ingress/request level:
     *   $block = OraBooks_ACL_Endpoints::check_ingress_block($host, $request_path);
     *   if ($block) { return 403; }
     *
     * @param string $host         HTTP Host header (e.g., "mycompany.orabooks.app")
     * @param string $request_path Request URI path
     * @return array Block result { blocked: bool, reason: string, org_type: string }
     */
    public static function check_ingress_block($host, $request_path) {
        $result = array(
            'blocked'   => false,
            'reason'    => '',
            'org_type'  => '',
        );

        // Define accounting endpoint patterns (SL-003 §5.15)
        $accounting_paths = array(
            '/api/accounting/', '/api/journal/', '/api/invoice/',
            '/api/bill/', '/api/expense/', '/api/coa/',
            '/api/report/', '/api/trial-balance/', '/api/ledger/',
        );

        // Check if this is an accounting request
        $is_accounting = false;
        foreach ($accounting_paths as $prefix) {
            if (stripos($request_path, $prefix) === 0) {
                $is_accounting = true;
                break;
            }
        }

        if (!$is_accounting) {
            return $result; // Not an accounting endpoint, allow
        }

        // Extract subdomain from host
        $host = strtolower(trim($host));
        $main_domain_parts = explode('.', $host);

        // Expect at least 3 parts: subdomain.domain.tld
        if (count($main_domain_parts) < 3) {
            return $result; // No subdomain, can't determine org
        }

        $subdomain = $main_domain_parts[0];

        // Look up org by subdomain
        if (class_exists('OraBooks_Organizations')) {
            $org = OraBooks_Organizations::get_instance()->get_organization_by_subdomain($subdomain);
            if ($org && $org->organization_type === 'partner') {
                // Accounting Isolation Rule: partner orgs cannot access accounting APIs
                $result['blocked']  = true;
                $result['reason']   = 'Accounting features are not available for partner organizations.';
                $result['org_type'] = 'partner';

                do_action('orabooks_security_event', 'ingress_accounting_blocked', array(
                    'subdomain' => $subdomain,
                    'org_id'    => $org->id,
                    'path'      => $request_path,
                    'host'      => $host,
                ));
            }
        }

        return $result;
    }

    /**
     * Filter hook for other plugins: orabooks_require_customer_org.
     * Usage: apply_filters('orabooks_require_customer_org', true, $user_id, $org_id)
     *
     * @param bool $allowed Current allowed status
     * @param int  $user_id User ID
     * @param int  $org_id  Organization ID
     * @return bool
     */
    public function filter_require_customer_org($allowed, $user_id, $org_id) {
        $result = $this->check_customer_org($user_id, $org_id);
        return $result['allowed'];
    }

    // ================================================================
    // INTERNAL HELPERS
    // ================================================================

    /**
     * Core check for requireCustomerOrg logic.
     *
     * @param int $user_id User ID
     * @param int $org_id  Organization ID
     * @return array { allowed: bool, reason: string, org_type: string }
     */
    private function check_customer_org($user_id, $org_id) {
        $result = array(
            'allowed'  => false,
            'reason'   => '',
            'org_type' => '',
        );

        if ($org_id <= 0) {
            $result['reason'] = __('No organization context.', 'orabooks');
            return $result;
        }

        // Get org info
        if (class_exists('OraBooks_Organizations')) {
            $org = OraBooks_Organizations::get_instance()->get_organization($org_id);
            if (!$org) {
                $result['reason'] = __('Organization not found.', 'orabooks');
                return $result;
            }

            $result['org_type'] = $org->organization_type;

            // Check if org is active
            if ($org->status !== 'active' && $org->status !== 'payout_hold') {
                $result['reason'] = sprintf(
                    __('Organization is %s. Access denied.', 'orabooks'),
                    $org->status
                );
                return $result;
            }

            // SL-013 Accounting Isolation Rule: partner orgs cannot access accounting
            if ($org->organization_type === 'partner') {
                $result['reason'] = __('Accounting features are not available for partner organizations.', 'orabooks');
                return $result;
            }

            $result['allowed'] = true;
        } else {
            // No organizations system - allow by default
            $result['allowed'] = true;
        }

        return $result;
    }

    /**
     * Map feature key to permission matrix action.
     *
     * @param string $feature_key Feature key
     * @return string Matrix action
     */
    private function map_feature_to_matrix_action($feature_key) {
        if (class_exists('OraBooks_Permission_Matrix')) {
            $map = array(
                'invoices'          => OraBooks_Permission_Matrix::ACTION_CREATE_TRANSACTION,
                'expenses'          => OraBooks_Permission_Matrix::ACTION_CREATE_TRANSACTION,
                'reports'           => OraBooks_Permission_Matrix::ACTION_VIEW_DATA_REPORTS,
                'chart_of_accounts' => OraBooks_Permission_Matrix::ACTION_MANAGE_CHART_OF_ACCOUNTS,
                'users'             => OraBooks_Permission_Matrix::ACTION_USER_MANAGEMENT,
                'journal'           => OraBooks_Permission_Matrix::ACTION_POST_JOURNAL_ENTRY,
                'payroll'           => OraBooks_Permission_Matrix::ACTION_RUN_PAYROLL,
                'tax'               => OraBooks_Permission_Matrix::ACTION_FILE_SUBMIT_TAX_VAT,
            );
            return isset($map[$feature_key]) ? $map[$feature_key] : OraBooks_Permission_Matrix::ACTION_VIEW_DATA_REPORTS;
        }
        return 'view_data_reports';
    }
}

// Initialize
OraBooks_ACL_Endpoints::get_instance();
