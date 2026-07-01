<?php
/**
 * OraBooks RBAC / ABAC (SL-003)
 * 
 * Role-based access control system with deny-by-default,
 * org-scoped permissions, and partner commission access configuration.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_RBAC {
    
    private static $instance = null;
    private static $permissions = [];
    
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            self::define_permissions();
        }
        return self::$instance;
    }
    
    /**
     * Define all system permissions and their allowed roles
     */
    private static function define_permissions() {
        self::$permissions = [
            'view_reports'                => ['owner', 'admin', 'approver', 'staff', 'viewer'],
            'view_bank_reconciliation'    => ['owner', 'admin', 'approver', 'staff', 'viewer'],
            'view_financial_reports'      => ['owner', 'admin', 'approver', 'staff', 'viewer'],
            'view_operational_reports'    => ['owner', 'admin', 'approver', 'staff', 'viewer'],
            'view_ai_review_queue'        => ['owner', 'admin', 'approver'],
            'view_expenses'               => ['owner', 'admin', 'approver', 'staff', 'viewer'],
            'manage_expenses'             => ['owner', 'admin', 'staff'],
            'view_classification'         => ['owner', 'admin', 'approver', 'staff', 'viewer'],
            'override_classification'     => ['owner', 'admin', 'staff'],
            'approve_expense'             => ['owner', 'admin', 'approver'],
            'view_voice_inputs'           => ['owner', 'admin', 'approver', 'staff', 'viewer'],
            'manage_voice_inputs'         => ['owner', 'admin', 'staff'],
            'submit_transaction'          => ['owner', 'admin', 'staff'],
            'match_transaction'           => ['owner', 'admin', 'approver', 'staff'],
            'approve_journal'             => ['owner', 'admin', 'approver'],
            'reconcile_bank'              => ['owner', 'admin'],
            'connect_bank_feed'           => ['owner', 'admin'],
            'reverse_journal'             => ['owner', 'admin'],
            'sign_report'                 => ['owner', 'admin'],
            'invite_user'                 => ['owner', 'admin'],
            'change_role'                 => ['owner'],
            'remove_user'                 => ['owner'],
            'manage_employees'            => ['owner', 'admin'],
            'manage_roles'                => ['owner'],
            'partner_commission_access'   => ['owner', 'admin'],
            'view_coa'                    => ['owner', 'admin'],
            'manage_coa'                  => ['owner', 'admin'],
            'manage_fiscal_periods'       => ['owner', 'admin'],
            'export_reports'              => ['owner', 'admin', 'staff'],
            'admin_replay'                => ['owner', 'admin'],
            'view_audit_logs'             => ['owner', 'admin'],
            'manage_org_settings'         => ['owner', 'admin'],
            'manage_settings'             => ['owner', 'admin'],
            'manage_billing'              => ['owner'],
            'manage_inventory'            => ['owner', 'admin', 'staff'],
            'create_invoice'              => ['owner', 'admin', 'staff'],
            'view_invoices'               => ['owner', 'admin', 'approver', 'staff', 'viewer'],
            'manage_partner_settings'     => ['owner', 'admin'],
        ];
    }
    
    /**
     * Check if a role has a specific permission in an org
     */
    public static function check_permission($role, $permission, $org_id = null) {
        if (class_exists('OBN_Access_Control')) {
            $permission = OBN_Access_Control::normalize_permission($permission);
        }

        if (empty($role)) {
            return false;
        }
        
        $allowed_roles = self::$permissions[$permission] ?? [];
        
        // Special handling for partner_commission_access for staff/viewer
        if ($permission === 'partner_commission_access' && in_array($role, ['staff', 'viewer'])) {
            if ($org_id) {
                $org = OraBooks_Organization::get($org_id);
                if ($org) {
                    if (!empty($org->partner_commission_for_staff_viewer)) {
                        return true;
                    }

                    $config = json_decode((string) ($org->config ?? ''), true);
                    if (is_array($config) && !empty($config['partner_commission_for_staff_viewer'])) {
                        return true;
                    }
                }
            }
        }
        
        return in_array($role, $allowed_roles);
    }
    
    /**
     * Middleware: require a specific permission
     * Returns true if allowed, false if denied.
     */
    public static function require_permission($user_id, $org_id, $permission, $options = []) {
        if (class_exists('OBN_Access_Control')) {
            return OBN_Access_Control::require_permission($user_id, $org_id, $permission, $options);
        }

        $org_id = intval($org_id);
        $user_id = intval($user_id);
        
        if (!$user_id || !$org_id) {
            orabooks_log_event('permission_denied_missing_context', 'Permission check missing user or org context', 'warning', [
                'permission' => $permission,
                'user_id' => $user_id,
                'org_id' => $org_id
            ], $user_id ?: null, $org_id ?: null);
            return false;
        }
        
        $org = OraBooks_Organization::get($org_id);
        if (!$org) {
            orabooks_log_event('permission_denied_missing_context', 'Permission check organization not found', 'warning', [
                'permission' => $permission,
                'user_id' => $user_id,
                'org_id' => $org_id
            ], $user_id, $org_id);
            return false;
        }
        
        if ($org->status !== 'active') {
            orabooks_log_event('permission_denied', "Organization is not active: {$org->status}", 'warning', [
                'permission' => $permission,
                'user_id' => $user_id,
                'org_id' => $org_id,
                'org_status' => $org->status
            ], $user_id, $org_id);
            return false;
        }
        
        $accounting_permissions = [
            'view_reports',
            'submit_transaction',
            'approve_journal',
            'reverse_journal',
        'view_coa',
        'manage_coa',
        'manage_fiscal_periods',
            'create_invoice',
            'view_invoices',
            'manage_billing',
        ];
        
        if ($org->organization_type === 'partner' && in_array($permission, $accounting_permissions, true)) {
            orabooks_log_event('permission_denied', "Partner org accounting permission denied: $permission", 'warning', [
                'permission' => $permission,
                'user_id' => $user_id,
                'org_id' => $org_id,
                'organization_type' => $org->organization_type
            ], $user_id, $org_id);
            return false;
        }
        
        $role = orabooks_get_user_role($user_id, $org_id);
        
        if (!$role) {
            orabooks_log_event('permission_denied_missing_context', "No role found for user $user_id in org $org_id", 'warning', [
                'permission' => $permission,
                'user_id' => $user_id,
                'org_id' => $org_id
            ], $user_id, $org_id);
            
            return false;
        }
        
        if (!self::check_permission($role, $permission, $org_id)) {
            orabooks_log_event('permission_denied', "Permission denied: $permission for role $role", 'warning', [
                'permission' => $permission,
                'role' => $role,
                'user_id' => $user_id,
                'org_id' => $org_id,
                'ip_address' => orabooks_get_client_ip(),
                'user_agent' => orabooks_get_user_agent()
            ], $user_id, $org_id);
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Get all permissions for a role (base matrix only; ignores org config).
     */
    public static function get_role_permissions($role) {
        $role_perms = [];
        foreach (self::$permissions as $perm => $roles) {
            if (in_array($role, $roles)) {
                $role_perms[] = $perm;
            }
        }
        return $role_perms;
    }

    /**
     * Effective permissions for a role in an org (includes partner_commission_for_staff_viewer).
     */
    public static function get_effective_permissions($role, $org_id = null) {
        if (empty($role)) {
            return [];
        }

        $effective = [];
        foreach (array_keys(self::$permissions) as $permission) {
            if (self::check_permission($role, $permission, $org_id)) {
                $effective[] = $permission;
            }
        }

        if (in_array('invite_user', $effective, true)) {
            $effective[] = 'manage_employees';
        }
        if (in_array('change_role', $effective, true)) {
            $effective[] = 'manage_roles';
        }
        if (in_array('manage_org_settings', $effective, true)) {
            $effective[] = 'manage_settings';
        }

        return array_values(array_unique($effective));
    }
    
    /**
     * Get all roles
     */
    public static function get_roles() {
        return ['owner', 'admin', 'approver', 'staff', 'viewer'];
    }
    
    /**
     * Get all defined permissions
     */
    public static function get_all_permissions() {
        return self::$permissions;
    }
    
    /**
     * Check if user has partner commission access
     */
    public static function has_partner_commission_access($user_id, $org_id) {
        return self::require_permission($user_id, $org_id, 'partner_commission_access');
    }
}