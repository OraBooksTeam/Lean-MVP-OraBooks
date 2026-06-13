<?php
/**
 * OraBooks Permission Matrix
 * Implements Role × Mode × Action permission system as per build guide
 * 
 * Based on ORABOOKS_ULTIMATE_BUILD_GUIDE_A_TO_Z.md requirements:
 * - Role gives capability
 * - Mode gives boundary  
 * - Action gives legality
 * - All three must be satisfied or the action is blocked
 * - Permission Matrix overrides any role narrative or UI behavior
 * 
 * INTEGRATION WITH SL-003 RBAC:
 * This class now bridges the existing build guide permission matrix 
 * with the new SL-003 RBAC (OraBooks_RBAC) and SL-014 user_org roles.
 * 
 * Role Mapping (Build Guide → SL-014/SL-003):
 * - company_owner, customer_primary_owner → owner
 * - manager → admin
 * - staff_operator → staff
 * - viewer → viewer
 * - external_accountant → external_accountant (custom)
 * - ai_system_assistant → ai_system_assistant (system)
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Permission_Matrix {
    
    // Core Roles (from build guide)
    const ROLE_COMPANY_OWNER = 'company_owner';
    const ROLE_CUSTOMER_PRIMARY_OWNER = 'customer_primary_owner';
    const ROLE_MANAGER = 'manager';
    const ROLE_STAFF_OPERATOR = 'staff_operator';
    const ROLE_VIEWER = 'viewer';
    const ROLE_EXTERNAL_ACCOUNTANT = 'external_accountant';
    const ROLE_SUPPORT_INTERNAL = 'support_internal';
    const ROLE_AI_SYSTEM_ASSISTANT = 'ai_system_assistant';
    
    /**
     * SL-003/SL-014: Map build guide roles to SL-014 user_org roles.
     */
    const ROLE_MAP_BUILD_GUIDE_TO_ORG = array(
        self::ROLE_COMPANY_OWNER          => 'owner',
        self::ROLE_CUSTOMER_PRIMARY_OWNER => 'owner',
        self::ROLE_MANAGER                 => 'admin',
        self::ROLE_STAFF_OPERATOR          => 'staff',
        self::ROLE_VIEWER                  => 'viewer',
        self::ROLE_EXTERNAL_ACCOUNTANT     => 'staff',  // External accountants get staff-level access
        self::ROLE_SUPPORT_INTERNAL        => 'viewer', // Support gets read-only
        self::ROLE_AI_SYSTEM_ASSISTANT     => 'viewer', // AI gets read-only
    );

    /**
     * SL-003/SL-014: Map SL-014 user_org roles to build guide roles.
     */
    const ROLE_MAP_ORG_TO_BUILD_GUIDE = array(
        'owner'   => self::ROLE_COMPANY_OWNER,
        'admin'   => self::ROLE_MANAGER,
        'approver'=> self::ROLE_MANAGER,
        'staff'   => self::ROLE_STAFF_OPERATOR,
        'viewer'  => self::ROLE_VIEWER,
    );
    
    // Core Modes (from build guide)
    const MODE_BUSINESS = 'business';
    const MODE_LAW = 'law';
    const MODE_FAITH = 'faith';
    
    // Action Categories (from build guide)
    const ACTION_VIEW_DATA_REPORTS = 'view_data_reports';
    const ACTION_CREATE_TRANSACTION = 'create_transaction';
    const ACTION_POST_JOURNAL_ENTRY = 'post_journal_entry';
    const ACTION_APPROVE_TRANSACTION = 'approve_transaction';
    const ACTION_ADJUST_CORRECT = 'adjust_correct';
    const ACTION_MANAGE_CHART_OF_ACCOUNTS = 'manage_chart_of_accounts';
    const ACTION_ACCESS_TRUST_RESTRICTED_FUNDS = 'access_trust_restricted_funds';
    const ACTION_RUN_PAYROLL = 'run_payroll';
    const ACTION_FILE_SUBMIT_TAX_VAT = 'file_submit_tax_vat';
    const ACTION_GENERATE_SHARE_REPORTS = 'generate_share_reports';
    const ACTION_USER_MANAGEMENT = 'user_management';
    const ACTION_OVERRIDE_EXCEPTION_APPROVAL = 'override_exception_approval';
    const ACTION_SYSTEM_MIGRATION_APPROVAL = 'system_migration_approval';
    
    // Permission Matrix Legend
    const PERMISSION_ALLOWED = 'allowed';
    const PERMISSION_APPROVAL_REQUIRED = 'approval_required';
    const PERMISSION_FORBIDDEN = 'forbidden';
    
    /**
     * Get permission matrix based on build guide requirements.
     * This is the AUTHORITATIVE permission mapping for the accounting domain.
     * SL-003 RBAC handles org-scoped permissions (invite, team, etc.).
     */
    public static function get_permission_matrix() {
        return array(
            // Company Owner / Super Admin
            self::ROLE_COMPANY_OWNER => array(
                self::MODE_BUSINESS => array(
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_ALLOWED,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_ALLOWED,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_ALLOWED,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_ALLOWED,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_ALLOWED,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_ALLOWED,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_ALLOWED,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_ALLOWED,
                ),
                self::MODE_LAW => array(
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_ALLOWED,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_ALLOWED,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_ALLOWED,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_ALLOWED,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_ALLOWED,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_ALLOWED,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_ALLOWED,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_ALLOWED,
                ),
                self::MODE_FAITH => array(
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_ALLOWED,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_ALLOWED,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_ALLOWED,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_ALLOWED,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_ALLOWED,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_ALLOWED,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_ALLOWED,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_ALLOWED,
                ),
            ),
            
            // Manager
            self::ROLE_MANAGER => array(
                self::MODE_BUSINESS => array(
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_ALLOWED,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_ALLOWED,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ),
                self::MODE_LAW => array(
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_ALLOWED,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_ALLOWED,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ),
                self::MODE_FAITH => array(
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_ALLOWED,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_ALLOWED,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ),
            ),
            
            // Staff / Operator
            self::ROLE_STAFF_OPERATOR => array(
                self::MODE_BUSINESS => array(
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_FORBIDDEN,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ),
                self::MODE_LAW => array(
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_FORBIDDEN,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ),
                self::MODE_FAITH => array(
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_FORBIDDEN,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ),
            ),
            
            // Viewer
            self::ROLE_VIEWER => array(
                self::MODE_BUSINESS => array(
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_FORBIDDEN,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ),
                self::MODE_LAW => array(
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_FORBIDDEN,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ),
                self::MODE_FAITH => array(
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_FORBIDDEN,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ),
            ),
            
            // External Accountant / Advisor
            self::ROLE_EXTERNAL_ACCOUNTANT => array(
                self::MODE_BUSINESS => array(
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_ALLOWED,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_ALLOWED,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ),
                self::MODE_LAW => array(
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_ALLOWED,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_ALLOWED,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_ALLOWED,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ),
                self::MODE_FAITH => array(
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_ALLOWED,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_ALLOWED,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_ALLOWED,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ),
            ),
            
            // AI System Assistant (Non-Authority)
            self::ROLE_AI_SYSTEM_ASSISTANT => array(
                self::MODE_BUSINESS => array(
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_FORBIDDEN,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ),
                self::MODE_LAW => array(
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_FORBIDDEN,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ),
                self::MODE_FAITH => array(
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_FORBIDDEN,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ),
            ),
        );
    }
    
    /**
     * SL-003 Integration: Check if user has permission for specific action in specific mode.
     * Uses SL-014 user_org role via get_user_org_role, maps to build guide role.
     * 
     * @param int    $user_id User ID
     * @param string $role    Role (build guide or SL-014 org role - auto-detected)
     * @param string $mode    Mode constant
     * @param string $action  Action constant
     * @return array Permission result
     */
    public static function check_permission($user_id, $role, $mode, $action) {
        // Auto-map SL-014 org roles to build guide roles
        if (isset(self::ROLE_MAP_ORG_TO_BUILD_GUIDE[$role])) {
            $role = self::ROLE_MAP_ORG_TO_BUILD_GUIDE[$role];
        }
        
        $matrix = self::get_permission_matrix();
        
        if (!isset($matrix[$role])) {
            return array(
                'allowed' => false,
                'reason' => 'Invalid role',
                'approval_required' => false,
                'permission_level' => self::PERMISSION_FORBIDDEN
            );
        }
        
        if (!isset($matrix[$role][$mode])) {
            return array(
                'allowed' => false,
                'reason' => 'Invalid mode for role',
                'approval_required' => false,
                'permission_level' => self::PERMISSION_FORBIDDEN
            );
        }
        
        if (!isset($matrix[$role][$mode][$action])) {
            return array(
                'allowed' => false,
                'reason' => 'Invalid action for role/mode',
                'approval_required' => false,
                'permission_level' => self::PERMISSION_FORBIDDEN
            );
        }
        
        $permission_level = $matrix[$role][$mode][$action];
        
        $result = array(
            'allowed' => false,
            'reason' => '',
            'approval_required' => false,
            'permission_level' => $permission_level
        );
        
        switch ($permission_level) {
            case self::PERMISSION_ALLOWED:
                $result['allowed'] = true;
                $result['reason'] = 'Permission granted by matrix';
                break;
                
            case self::PERMISSION_APPROVAL_REQUIRED:
                $result['allowed'] = false;
                $result['approval_required'] = true;
                $result['reason'] = 'Approval required by higher role';
                break;
                
            case self::PERMISSION_FORBIDDEN:
                $result['allowed'] = false;
                $result['reason'] = 'Action forbidden by permission matrix';
                break;
        }
        
        return $result;
    }
    
    /**
     * SL-003 Integration: Get user's role from SL-014 user_org.
     * Falls back to WordPress capabilities if not available.
     *
     * @param int $user_id User ID
     * @param int $org_id  Organization ID
     * @return string Build guide role
     */
    public static function get_user_role($user_id, $org_id = 0) {
        // Try SL-014 role first
        if ($org_id > 0 && class_exists('OraBooks_Users_Teams')) {
            $org_role = OraBooks_Users_Teams::get_instance()->get_user_role($user_id, $org_id);
            if ($org_role && isset(self::ROLE_MAP_ORG_TO_BUILD_GUIDE[$org_role])) {
                return self::ROLE_MAP_ORG_TO_BUILD_GUIDE[$org_role];
            }
        }
        
        // Fallback to WordPress user roles
        $user = get_userdata($user_id);
        if (!$user) {
            return self::ROLE_VIEWER;
        }
        
        if (is_super_admin($user_id)) {
            return self::ROLE_COMPANY_OWNER;
        }
        if (in_array('administrator', (array)$user->roles)) {
            return self::ROLE_MANAGER;
        }
        if (in_array('editor', (array)$user->roles)) {
            return self::ROLE_STAFF_OPERATOR;
        }
        if (in_array('author', (array)$user->roles)) {
            return self::ROLE_STAFF_OPERATOR;
        }
        if (in_array('subscriber', (array)$user->roles)) {
            return self::ROLE_VIEWER;
        }
        if (in_array('external_accountant', (array)$user->roles)) {
            return self::ROLE_EXTERNAL_ACCOUNTANT;
        }
        
        return self::ROLE_VIEWER;
    }
    
    /**
     * Get current mode for context.
     */
    public static function get_current_mode($user_id = null) {
        if (class_exists('OraBooks_Mode_Manager')) {
            return OraBooks_Mode_Manager::get_current_mode($user_id);
        }
        return self::MODE_BUSINESS;
    }
    
    /**
     * SL-003: Full access check using both RBAC and Permission Matrix.
     * This is the ENTRY POINT for all permission checks in the system.
     *
     * @param int    $user_id     User ID
     * @param int    $org_id      Organization ID
     * @param string $permission  SL-003 permission key
     * @param string $action      Build guide action (for matrix check)
     * @return bool
     */
    public static function check_full_access($user_id, $org_id, $permission, $action = '') {
        // 1. Check SL-003 RBAC first (org-scoped, role-based permissions)
        $rbac_allowed = true;
        if (class_exists('OraBooks_RBAC')) {
            $rbac_allowed = OraBooks_RBAC::get_instance()->require_permission($user_id, $org_id, $permission);
        }
        
        if (!$rbac_allowed) {
            return false;
        }
        
        // 2. If action specified, check permission matrix (mode-based, accounting-specific)
        if (!empty($action)) {
            $role = self::get_user_role($user_id, $org_id);
            $mode = self::get_current_mode($user_id);
            $matrix_result = self::check_permission($user_id, $role, $mode, $action);
            
            if (!$matrix_result['allowed']) {
                if (class_exists('OraBooks_RBAC')) {
                    OraBooks_RBAC::get_instance()->require_permission_or_fail(
                        $user_id, 
                        $org_id, 
                        'permission_denied_matrix_' . $action,
                        array('force_fail' => true)
                    );
                }
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Initialize permission matrix system.
     * SL-003 Compliance: Permission Matrix overrides UI behavior.
     */
    public static function init() {
        add_filter('user_has_cap', array(__CLASS__, 'override_wordpress_capabilities'), 10, 4);
        add_filter('orabooks_can_access_data', array(__CLASS__, 'enforce_permission_matrix'), 10, 4);
        add_filter('orabooks_can_use_feature', array(__CLASS__, 'check_feature_permission'), 10, 4);
        add_action('init', array(__CLASS__, 'validate_all_permissions'));
    }
    
    /**
     * Override WordPress capabilities with permission matrix.
     */
    public static function override_wordpress_capabilities($allcaps, $caps, $args, $user) {
        if (empty($caps)) {
            return $allcaps;
        }
        
        $user_id = $user->ID;
        $role = self::get_user_role($user_id);
        $mode = self::get_current_mode($user_id);
        
        foreach ($caps as $cap) {
            $action = self::map_capability_to_action($cap);
            if ($action) {
                $permission = self::check_permission($user_id, $role, $mode, $action);
                $allcaps[$cap] = $permission['allowed'];
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Enforce permission matrix on data access.
     */
    public static function enforce_permission_matrix($access, $data_type, $operation, $user_id) {
        if (!$access) return $access;
        
        $action = self::map_data_operation_to_action($data_type, $operation);
        if (!$action) return $access;
        
        $role = self::get_user_role($user_id);
        $mode = self::get_current_mode($user_id);
        $permission = self::check_permission($user_id, $role, $mode, $action);
        
        return $permission['allowed'];
    }
    
    /**
     * Check feature permission.
     */
    public static function check_feature_permission($access, $feature, $operation, $user_id) {
        if (!$access) return $access;
        
        $action = self::map_feature_operation_to_action($feature, $operation);
        if (!$action) return $access;
        
        $role = self::get_user_role($user_id);
        $mode = self::get_current_mode($user_id);
        $permission = self::check_permission($user_id, $role, $mode, $action);
        
        return $permission['allowed'];
    }
    
    /**
     * Map WordPress capability to OraBooks action.
     */
    private static function map_capability_to_action($capability) {
        $mapping = array(
            'manage_options' => self::ACTION_USER_MANAGEMENT,
            'edit_posts' => self::ACTION_CREATE_TRANSACTION,
            'publish_posts' => self::ACTION_POST_JOURNAL_ENTRY,
            'edit_others_posts' => self::ACTION_APPROVE_TRANSACTION,
            'manage_categories' => self::ACTION_MANAGE_CHART_OF_ACCOUNTS,
            'export' => self::ACTION_GENERATE_SHARE_REPORTS,
            'import' => self::ACTION_SYSTEM_MIGRATION_APPROVAL,
        );
        return isset($mapping[$capability]) ? $mapping[$capability] : null;
    }
    
    /**
     * Map data operation to action.
     */
    private static function map_data_operation_to_action($data_type, $operation) {
        switch ($operation) {
            case 'read':   return self::ACTION_VIEW_DATA_REPORTS;
            case 'create':
            case 'insert': return self::ACTION_CREATE_TRANSACTION;
            case 'update':
            case 'modify':
            case 'delete': return self::ACTION_ADJUST_CORRECT;
            default:       return null;
        }
    }
    
    /**
     * Map feature operation to action.
     */
    private static function map_feature_operation_to_action($feature, $operation) {
        if (strpos($feature, 'trust') !== false || strpos($feature, 'restricted') !== false) {
            return self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS;
        }
        if ($feature === 'payroll') return self::ACTION_RUN_PAYROLL;
        if ($feature === 'tax' || $feature === 'vat') return self::ACTION_FILE_SUBMIT_TAX_VAT;
        if ($feature === 'chart_of_accounts') return self::ACTION_MANAGE_CHART_OF_ACCOUNTS;
        return self::map_data_operation_to_action($feature, $operation);
    }
    
    /**
     * Validate all permissions on initialization.
     */
    public static function validate_all_permissions() {
        $matrix = self::get_permission_matrix();
        if (empty($matrix)) {
            error_log('[OraBooks Permission Matrix] ERROR: Permission matrix is empty');
            return;
        }
        foreach ($matrix as $role => $role_data) {
            if (!isset($role_data[self::MODE_BUSINESS]) || 
                !isset($role_data[self::MODE_LAW]) || 
                !isset($role_data[self::MODE_FAITH])) {
                error_log("[OraBooks Permission Matrix] ERROR: Role {$role} missing mode definitions");
            }
        }
    }
}