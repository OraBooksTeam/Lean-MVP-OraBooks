<?php
/**
 * SL-003 – RBAC / ABAC (Role-Based & Attribute-Based Access Control)
 *
 * Build Order: SL-004 → SL-013 → SL-003 (this) → SL-014 → SL-017 → SL-139 → SL-068
 *
 * This implements the permission system:
 * - Permission constants mapping roles → allowed actions
 * - requirePermission() middleware for org-scoped checks
 * - Deny-by-default: any permission not listed is denied
 * - Cross-tenant isolation (org_id matching)
 * - Partner org accounting restrictions (defensive check)
 * - partner_commission_access for Staff/Viewer via org config
 * - Permission cache invalidation on role change
 * - Audit logging for permission_denied events
 *
 * The permission system ties into membership levels via org_quotas/tier:
 * - An organization's tier (free/premium/enterprise/partner) determines 
 *   which features are available
 * - User's role within the org (owner/admin/approver/staff/viewer) determines
 *   which actions are permitted
 * - Feature availability = tier-based (from org_quotas) + role-based (from user_org)
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_RBAC {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Base permissions (org-scoped) per SL-003 §5.1
     * Deny-by-default: any permission not listed → deny
     */
    const PERMISSIONS = array(
        // Accounting & Financial
        'view_reports'              => array('owner', 'admin', 'approver', 'staff', 'viewer'),
        'submit_transaction'        => array('owner', 'admin', 'staff'),
        'approve_journal'           => array('owner', 'admin', 'approver'),
        'view_coa'                  => array('owner', 'admin'),
        'export_reports'            => array('owner', 'admin', 'staff'),
        
        // Team Management
        'invite_user'               => array('owner', 'admin'),
        'change_role'               => array('owner'),
        'remove_user'               => array('owner'),
        'view_team'                 => array('owner', 'admin', 'approver', 'staff', 'viewer'),
        'view_pending_invites'      => array('owner', 'admin'),
        'resend_invite'             => array('owner', 'admin'),
        'cancel_invite'             => array('owner', 'admin'),
        'transfer_ownership'        => array('owner'),
        
        // Commission / Partner
        'partner_commission_access' => array('owner', 'admin'), // Staff/Viewer via org config
        
        // Audit
        'view_audit_logs'           => array('owner', 'admin'),
        
        // Organization Settings
        'manage_org'                => array('owner'),
        'change_tier'               => array('owner'),
        'manage_teams'              => array('owner'),
        'manage_team_members'       => array('owner'),
        
        // Feature Access (tier-gated + role-gated)
        'access_invoicing'          => array('owner', 'admin', 'staff'),
        'access_inventory'          => array('owner', 'admin', 'staff'),
        'access_banking'            => array('owner', 'admin', 'staff'),
        'access_reports'            => array('owner', 'admin', 'approver', 'staff', 'viewer'),
        'access_ai_features'        => array('owner', 'admin', 'staff'),
    );

    /**
     * Partner org restricted permissions (accounting-related)
     * These are blocked for partner orgs even if role allows
     */
    const PARTNER_RESTRICTED_PERMISSIONS = array(
        'submit_transaction',
        'approve_journal',
        'view_coa',
        'view_reports',
        'export_reports',
        'access_invoicing',
        'access_inventory',
        'access_banking',
        'access_reports',
    );

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
     * Constructor
     */
    private function __construct() {
        add_filter('orabooks_check_permission', array($this, 'check_permission_filter'), 10, 4);
    }

    /**
     * SL-003 §5.2: requirePermission middleware.
     * Checks if a user has a specific permission in an org context.
     *
     * @param int    $user_id    User ID
     * @param int    $org_id     Organization ID
     * @param string $permission Permission key to check
     * @param array  $options    Additional options (e.g., allow_super_admin)
     * @return bool True if permitted
     */
    public function require_permission($user_id, $org_id, $permission, $options = array()) {
        try {
            $this->require_permission_or_fail($user_id, $org_id, $permission, $options);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * SL-003 §5.2: requirePermission that throws on failure.
     * Full middleware logic per the document specification.
     *
     * @param int    $user_id    User ID
     * @param int    $org_id     Organization ID
     * @param string $permission Permission key
     * @param array  $options    Options
     * @throws Exception When permission denied
     */
    public function require_permission_or_fail($user_id, $org_id, $permission, $options = array()) {
        $ip_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';

        // 1. Get user's role in the org
        $role = null;
        if (class_exists('OraBooks_Users_Teams')) {
            $role = OraBooks_Users_Teams::get_instance()->get_user_role($user_id, $org_id);
        } else {
            global $wpdb;
            $role = $wpdb->get_var($wpdb->prepare(
                "SELECT role FROM {$wpdb->base_prefix}orabooks_user_org WHERE user_id = %d AND org_id = %d",
                $user_id,
                $org_id
            ));
        }

        // 2. If no role or org_id, reject (should not happen for authenticated endpoints)
        if (!$role || !$org_id) {
            $this->log_permission_denied('permission_denied_missing_context', array(
                'user_id' => $user_id,
                'org_id' => $org_id,
                'permission' => $permission,
                'role' => $role,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
            ));
            throw new Exception('Missing role or org context');
        }

        // 3. Super admin bypass check (disabled by default in MVP per SL-003 §5.2)
        $allow_super_admin = isset($options['allow_super_admin']) && $options['allow_super_admin'];
        if ($allow_super_admin && user_can($user_id, 'manage_network')) {
            return; // Allow super admin (but this is disabled in MVP)
        }

        // 4. Get organization type for partner restrictions
        $org_type = 'customer';
        if (class_exists('OraBooks_Organizations')) {
            $org = OraBooks_Organizations::get_instance()->get_organization($org_id);
            if ($org) {
                $org_type = $org->organization_type;
            }
        }

        // 5. Partner org accounting restriction (SL-003 §5.6 defensive check)
        if ($org_type === 'partner' && in_array($permission, self::PARTNER_RESTRICTED_PERMISSIONS, true)) {
            $this->log_permission_denied('permission_denied_partner_restricted', array(
                'user_id' => $user_id,
                'org_id' => $org_id,
                'permission' => $permission,
                'role' => $role,
                'org_type' => $org_type,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
            ));
            throw new Exception('Accounting features are not available for partner organizations');
        }

        // 6. Check permission against role
        $allowed_roles = $this->get_allowed_roles($permission, $org_id);

        if (!in_array($role, $allowed_roles, true)) {
            $this->log_permission_denied('permission_denied', array(
                'user_id' => $user_id,
                'org_id' => $org_id,
                'permission' => $permission,
                'role' => $role,
                'org_type' => $org_type,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
            ));
            throw new Exception(sprintf('Permission denied for %s', $permission));
        }

        // 7. Check tier-based feature availability via org_quotas
        if (!$this->check_tier_feature_access($org_id, $permission)) {
            $this->log_permission_denied('permission_denied_tier_restricted', array(
                'user_id' => $user_id,
                'org_id' => $org_id,
                'permission' => $permission,
                'role' => $role,
                'org_type' => $org_type,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
            ));
            throw new Exception(sprintf('Feature not available in your current plan'));
        }

        // Permission granted
    }

    /**
     * SL-003 §5.1: Get allowed roles for a permission.
     * Handles special case: partner_commission_access for Staff/Viewer via org config.
     *
     * @param string $permission Permission key
     * @param int    $org_id     Organization ID
     * @return array List of allowed roles
     */
    public function get_allowed_roles($permission, $org_id = 0) {
        $allowed_roles = isset(self::PERMISSIONS[$permission]) 
            ? self::PERMISSIONS[$permission] 
            : array();

        // Special handling: partner_commission_access for Staff/Viewer
        if ($permission === 'partner_commission_access' && $org_id > 0) {
            $org_config = $this->get_org_config($org_id);
            if (!empty($org_config['partner_commission_for_staff_viewer'])) {
                $allowed_roles[] = 'staff';
                $allowed_roles[] = 'viewer';
            }
        }

        return $allowed_roles;
    }

    /**
     * SL-003: Get org config value (from organizations table or user_meta).
     * Supports partner_commission_for_staff_viewer config.
     *
     * @param int $org_id Organization ID
     * @return array Org config
     */
    public function get_org_config($org_id) {
        // Try to get from organizations table (via OraBooks_Organizations)
        if (class_exists('OraBooks_Organizations')) {
            $org = OraBooks_Organizations::get_instance()->get_organization($org_id);
            if ($org && isset($org->config)) {
                $config = json_decode($org->config, true);
                if (is_array($config)) {
                    return $config;
                }
            }
        }

        // Fallback: store in wp_options
        $config = get_option('orabooks_org_config_' . $org_id, array());
        if (!is_array($config)) {
            $config = array();
        }

        return $config;
    }

    /**
     * SL-003: Save org config value.
     *
     * @param int   $org_id Organization ID
     * @param array $config Config data
     */
    public function save_org_config($org_id, $config) {
        update_option('orabooks_org_config_' . $org_id, $config);
    }

    /**
     * SL-003: Check tier-based feature access for an org.
     * Uses org_quotas to determine if the org's tier supports the feature.
     *
     * @param int    $org_id     Organization ID
     * @param string $permission Permission key
     * @return bool
     */
    public function check_tier_feature_access($org_id, $permission) {
        // Get org tier
        $tier = 'free';
        if (class_exists('OraBooks_Organizations')) {
            $org = OraBooks_Organizations::get_instance()->get_organization($org_id);
            if ($org) {
                $tier = $org->tier;
            }
        }

        // Partner tier has unlimited access to partner features
        if ($tier === 'partner') {
            // Partner-specific permissions are always allowed
            $partner_permissions = array(
                'partner_commission_access',
                'view_team',
                'view_pending_invites',
            );
            if (in_array($permission, $partner_permissions, true)) {
                return true;
            }
        }

        // Check if feature is available for this tier via membership levels
        if (class_exists('OraBooks_Membership_Levels')) {
            $level_key = $this->map_tier_to_level_key($tier);
            if ($level_key) {
                return OraBooks_Membership_Levels::is_feature_available($level_key, $permission);
            }
        }

        // Default: allow (role-based check already passed)
        return true;
    }

    /**
     * Map org tier to membership level key.
     *
     * @param string $tier Organization tier
     * @return string|null Level key
     */
    private function map_tier_to_level_key($tier) {
        $map = array(
            'free'      => 'free',
            'premium'   => 'business_starter',
            'enterprise'=> 'enterprise',
            'partner'   => null, // No membership level needed for partners
        );
        return isset($map[$tier]) ? $map[$tier] : null;
    }

    /**
     * SL-003 §5.4: Log permission denied events.
     *
     * @param string $event_type Event type
     * @param array  $data       Event data
     */
    private function log_permission_denied($event_type, $data) {
        $log_data = array(
            'event_type' => $event_type,
            'user_id'    => isset($data['user_id']) ? $data['user_id'] : 0,
            'org_id'     => isset($data['org_id']) ? $data['org_id'] : 0,
            'permission' => isset($data['permission']) ? $data['permission'] : '',
            'role'       => isset($data['role']) ? $data['role'] : '',
            'ip_address' => isset($data['ip_address']) ? $data['ip_address'] : '',
            'user_agent' => isset($data['user_agent']) ? $data['user_agent'] : '',
            'timestamp'  => current_time('mysql'),
        );

        do_action('orabooks_security_event', $event_type, $log_data);

        error_log(sprintf(
            '[OraBooks RBAC] %s | User: %d | Org: %d | Permission: %s | Role: %s | IP: %s',
            $event_type,
            $log_data['user_id'],
            $log_data['org_id'],
            $log_data['permission'],
            $log_data['role'],
            $log_data['ip_address']
        ));
    }

    /**
     * SL-003 §5.3: Invalidate permission cache on role change.
     * Revokes all refresh tokens for a user in an org.
     *
     * @param int $user_id User ID
     * @param int $org_id  Organization ID
     */
    public function invalidate_permission_cache($user_id, $org_id) {
        do_action('orabooks_revoke_user_sessions', $user_id, $org_id);
        
        do_action('orabooks_security_event', 'permission_cache_invalidated', array(
            'user_id' => $user_id,
            'org_id'  => $org_id,
            'timestamp' => current_time('mysql'),
        ));
    }

    /**
     * SL-003: WordPress filter hook for permission checks.
     * Usage: apply_filters('orabooks_check_permission', true, $user_id, $org_id, $permission)
     *
     * @param bool   $allowed    Current allowed status
     * @param int    $user_id    User ID
     * @param int    $org_id     Organization ID
     * @param string $permission Permission key
     * @return bool
     */
    public function check_permission_filter($allowed, $user_id, $org_id, $permission) {
        return $this->require_permission($user_id, $org_id, $permission);
    }

    /**
     * SL-003: Get all permissions available for a role.
     *
     * @param string $role   Role name
     * @param int    $org_id Organization ID (for partner_commission_access check)
     * @return array List of permission keys
     */
    public function get_permissions_for_role($role, $org_id = 0) {
        $permissions = array();

        foreach (self::PERMISSIONS as $permission => $allowed_roles) {
            $effective_roles = $allowed_roles;

            // Check partner_commission_access extension for staff/viewer
            if ($permission === 'partner_commission_access' && $org_id > 0) {
                $config = $this->get_org_config($org_id);
                if (!empty($config['partner_commission_for_staff_viewer'])) {
                    $effective_roles = array_merge($effective_roles, array('staff', 'viewer'));
                }
            }

            if (in_array($role, $effective_roles, true)) {
                $permissions[] = $permission;
            }
        }

        return $permissions;
    }

    /**
     * SL-003: Check if a user has access to a specific feature based on their org's tier.
     * This links membership level (tier) to feature availability.
     *
     * @param int    $user_id     User ID
     * @param int    $org_id      Organization ID
     * @param string $feature_key Feature key to check
     * @return bool
     */
    public function user_has_feature_access($user_id, $org_id, $feature_key) {
        // First check org tier
        $tier = 'free';
        if (class_exists('OraBooks_Organizations')) {
            $org = OraBooks_Organizations::get_instance()->get_organization($org_id);
            if ($org) {
                $tier = $org->tier;
            }
        }

        // Partner orgs have no accounting features
        if ($tier === 'partner') {
            $partner_features = array('partner_commission_access', 'view_team');
            if (!in_array($feature_key, $partner_features, true)) {
                return false;
            }
            return true; // Partner features always allowed for partner orgs
        }

        // Check feature availability via membership levels
        $level_key = $this->map_tier_to_level_key($tier);
        if ($level_key && class_exists('OraBooks_Membership_Levels')) {
            return OraBooks_Membership_Levels::is_feature_available($level_key, $feature_key);
        }

        // Also check role-based permission
        return $this->require_permission($user_id, $org_id, $feature_key);
    }

    /**
     * SL-003: Get the permission matrix as an array (for UI display).
     *
     * @return array Permission matrix
     */
    public static function get_permission_matrix() {
        $matrix = array();
        $roles = array('owner', 'admin', 'approver', 'staff', 'viewer');

        foreach (self::PERMISSIONS as $permission => $allowed_roles) {
            $row = array(
                'permission' => $permission,
                'label'      => ucwords(str_replace('_', ' ', $permission)),
            );
            foreach ($roles as $role) {
                $row[$role] = in_array($role, $allowed_roles, true);
            }
            $matrix[] = $row;
        }

        return $matrix;
    }

    /**
     * SL-003: Get the partner-restricted permission matrix.
     *
     * @return array Partner restricted permissions
     */
    public static function get_partner_restricted_permissions() {
        $restricted = array();
        foreach (self::PARTNER_RESTRICTED_PERMISSIONS as $permission) {
            $restricted[] = array(
                'permission' => $permission,
                'label'      => ucwords(str_replace('_', ' ', $permission)),
                'reason'     => 'Accounting features are not available for partner organizations',
            );
        }
        return $restricted;
    }
}

// Initialize
OraBooks_RBAC::get_instance();