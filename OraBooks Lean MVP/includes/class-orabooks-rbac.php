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
            'submit_transaction'          => ['owner', 'admin', 'staff'],
            'approve_journal'             => ['owner', 'admin', 'approver'],
            'invite_user'                 => ['owner', 'admin'],
            'change_role'                 => ['owner'],
            'remove_user'                 => ['owner'],
            'partner_commission_access'   => ['owner', 'admin'],
            'view_coa'                    => ['owner', 'admin'],
            'export_reports'              => ['owner', 'admin', 'staff'],
            'view_audit_logs'             => ['owner', 'admin'],
            'manage_org_settings'         => ['owner', 'admin'],
            'manage_billing'              => ['owner'],
            'create_invoice'              => ['owner', 'admin', 'staff'],
            'view_invoices'               => ['owner', 'admin', 'approver', 'staff', 'viewer'],
            'manage_partner_settings'     => ['owner', 'admin'],
        ];
    }
    
    /**
     * Check if a role has a specific permission in an org
     */
    public static function check_permission($role, $permission, $org_id = null) {
        if (empty($role)) {
            return false;
        }
        
        $allowed_roles = self::$permissions[$permission] ?? [];
        
        // Special handling for partner_commission_access for staff/viewer
        if ($permission === 'partner_commission_access' && in_array($role, ['staff', 'viewer'])) {
            if ($org_id) {
                $org = OraBooks_Organization::get($org_id);
                if ($org && !empty($org->partner_commission_for_staff_viewer)) {
                    return true;
                }
            }
            // Fallback to global option
            if (get_option('orabooks_partner_commission_for_staff_viewer', 0)) {
                return true;
            }
        }
        
        return in_array($role, $allowed_roles);
    }
    
    /**
     * Middleware: require a specific permission
     * Returns true if allowed, exits with 403 if denied
     */
    public static function require_permission($user_id, $org_id, $permission) {
        $role = orabooks_get_user_role($user_id, $org_id);
        
        if (!$role) {
            orabooks_log_event('permission_denied', "No role found for user $user_id in org $org_id", 'warning', [
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
                'org_id' => $org_id
            ], $user_id, $org_id);
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Get all permissions for a role
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