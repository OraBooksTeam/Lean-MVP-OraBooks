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
     * Get permission matrix based on build guide requirements
     */
    public static function get_permission_matrix() {
        return [
            // Company Owner / Super Admin
            self::ROLE_COMPANY_OWNER => [
                self::MODE_BUSINESS => [
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_ALLOWED,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_ALLOWED,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_ALLOWED,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN, // Only in Law/Faith modes
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_ALLOWED,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_ALLOWED,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_ALLOWED,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_ALLOWED,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_ALLOWED,
                ],
                self::MODE_LAW => [
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_ALLOWED,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_ALLOWED,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_ALLOWED,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_ALLOWED, // Trust accounts in Law mode
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN, // Not in Law mode
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_ALLOWED,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_ALLOWED,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_ALLOWED,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_ALLOWED,
                ],
                self::MODE_FAITH => [
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_ALLOWED,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_ALLOWED,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_ALLOWED,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_ALLOWED, // Restricted funds in Faith mode
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN, // Not in Faith mode
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_ALLOWED,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_ALLOWED,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_ALLOWED,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_ALLOWED,
                ],
            ],
            
            // Manager
            self::ROLE_MANAGER => [
                self::MODE_BUSINESS => [
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
                ],
                self::MODE_LAW => [
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_ALLOWED, // Trust accounts in Law mode
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_ALLOWED,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ],
                self::MODE_FAITH => [
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_ALLOWED, // Restricted funds in Faith mode
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_ALLOWED,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ],
            ],
            
            // Staff / Operator
            self::ROLE_STAFF_OPERATOR => [
                self::MODE_BUSINESS => [
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED, // Draft only
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_FORBIDDEN, // Needs approval
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN, // Cannot approve own
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED, // Read-only
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ],
                self::MODE_LAW => [
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED, // Draft only
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_FORBIDDEN,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN, // Only higher roles
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ],
                self::MODE_FAITH => [
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED, // Draft only
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_FORBIDDEN,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN, // Only higher roles
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ],
            ],
            
            // Viewer
            self::ROLE_VIEWER => [
                self::MODE_BUSINESS => [
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_FORBIDDEN,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED, // Read-only
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ],
                self::MODE_LAW => [
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
                ],
                self::MODE_FAITH => [
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
                ],
            ],
            
            // External Accountant / Advisor
            self::ROLE_EXTERNAL_ACCOUNTANT => [
                self::MODE_BUSINESS => [
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_ALLOWED,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN, // Cannot approve
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_ALLOWED,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ],
                self::MODE_LAW => [
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_ALLOWED,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_ALLOWED, // Trust accounts in Law mode
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_ALLOWED,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ],
                self::MODE_FAITH => [
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_ALLOWED,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_ALLOWED,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_APPROVAL_REQUIRED,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_ALLOWED, // Restricted funds in Faith mode
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_ALLOWED,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_ALLOWED,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ],
            ],
            
            // AI System Assistant (Non-Authority)
            self::ROLE_AI_SYSTEM_ASSISTANT => [
                self::MODE_BUSINESS => [
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED, // Read-only for analysis
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_FORBIDDEN, // AI cannot create
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_FORBIDDEN, // AI cannot post
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN, // AI cannot approve
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_FORBIDDEN, // AI cannot adjust
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_FORBIDDEN, // AI cannot generate official reports
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ],
                self::MODE_LAW => [
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED, // Read-only for analysis
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_FORBIDDEN,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN, // AI cannot access trust funds
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ],
                self::MODE_FAITH => [
                    self::ACTION_VIEW_DATA_REPORTS => self::PERMISSION_ALLOWED, // Read-only for analysis
                    self::ACTION_CREATE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_POST_JOURNAL_ENTRY => self::PERMISSION_FORBIDDEN,
                    self::ACTION_APPROVE_TRANSACTION => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ADJUST_CORRECT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_MANAGE_CHART_OF_ACCOUNTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS => self::PERMISSION_FORBIDDEN, // AI cannot access restricted funds
                    self::ACTION_RUN_PAYROLL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_FILE_SUBMIT_TAX_VAT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_GENERATE_SHARE_REPORTS => self::PERMISSION_FORBIDDEN,
                    self::ACTION_USER_MANAGEMENT => self::PERMISSION_FORBIDDEN,
                    self::ACTION_OVERRIDE_EXCEPTION_APPROVAL => self::PERMISSION_FORBIDDEN,
                    self::ACTION_SYSTEM_MIGRATION_APPROVAL => self::PERMISSION_FORBIDDEN,
                ],
            ],
        ];
    }
    
    /**
     * Check if user has permission for specific action in specific mode
     * 
     * @param int $user_id User ID
     * @param string $role Role constant
     * @param string $mode Mode constant  
     * @param string $action Action constant
     * @return array Permission result with details
     */
    public static function check_permission($user_id, $role, $mode, $action) {
        $matrix = self::get_permission_matrix();
        
        // Check if role exists in matrix
        if (!isset($matrix[$role])) {
            return [
                'allowed' => false,
                'reason' => 'Invalid role',
                'approval_required' => false,
                'permission_level' => self::PERMISSION_FORBIDDEN
            ];
        }
        
        // Check if mode exists for role
        if (!isset($matrix[$role][$mode])) {
            return [
                'allowed' => false,
                'reason' => 'Invalid mode for role',
                'approval_required' => false,
                'permission_level' => self::PERMISSION_FORBIDDEN
            ];
        }
        
        // Check if action exists for role/mode
        if (!isset($matrix[$role][$mode][$action])) {
            return [
                'allowed' => false,
                'reason' => 'Invalid action for role/mode',
                'approval_required' => false,
                'permission_level' => self::PERMISSION_FORBIDDEN
            ];
        }
        
        $permission_level = $matrix[$role][$mode][$action];
        
        $result = [
            'allowed' => false,
            'reason' => '',
            'approval_required' => false,
            'permission_level' => $permission_level
        ];
        
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
     * Get user's role for permission checking
     * Maps WordPress user roles to OraBooks roles
     */
    public static function get_user_role($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return null;
        }
        
        // Map WordPress capabilities to OraBooks roles
        if (is_super_admin($user_id)) {
            return self::ROLE_COMPANY_OWNER;
        }
        
        if (in_array('administrator', $user->roles)) {
            return self::ROLE_MANAGER;
        }
        
        if (in_array('editor', $user->roles)) {
            return self::ROLE_STAFF_OPERATOR;
        }
        
        if (in_array('author', $user->roles)) {
            return self::ROLE_STAFF_OPERATOR;
        }
        
        if (in_array('subscriber', $user->roles)) {
            return self::ROLE_VIEWER;
        }
        
        // Check for custom roles
        if (in_array('external_accountant', $user->roles)) {
            return self::ROLE_EXTERNAL_ACCOUNTANT;
        }
        
        // Default to viewer for safety
        return self::ROLE_VIEWER;
    }
    
    /**
     * Get current mode for context
     * Uses the mode manager for proper mode detection
     */
    public static function get_current_mode($user_id = null) {
        // Use mode manager for proper mode detection
        if (class_exists('OraBooks_Mode_Manager')) {
            return OraBooks_Mode_Manager::get_current_mode($user_id);
        }
        
        // Fallback to Business mode if mode manager not available
        return self::MODE_BUSINESS;
    }
    
    /**
     * Log permission check for audit trail
     */
    public static function log_permission_check($user_id, $role, $mode, $action, $result) {
        if (function_exists('error_log')) {
            $log_entry = sprintf(
                '[OraBooks Permission Check] User: %d, Role: %s, Mode: %s, Action: %s, Result: %s, Reason: %s',
                $user_id,
                $role,
                $mode,
                $action,
                $result['allowed'] ? 'ALLOWED' : 'DENIED',
                $result['reason']
            );
            error_log($log_entry);
        }
    }
    
    /**
     * Initialize permission matrix system
     * Build Guide: Permission Matrix overrides UI behavior
     */
    public static function init() {
        // Hook into WordPress capabilities
        add_filter('user_has_cap', [__CLASS__, 'override_wordpress_capabilities'], 10, 4);
        
        // Hook into menu access
        add_filter('menu_access', [__CLASS__, 'check_menu_access'], 10, 3);
        
        // Hook into all data operations
        add_filter('orabooks_can_access_data', [__CLASS__, 'enforce_permission_matrix'], 10, 4);
        
        // Hook into feature access
        add_filter('orabooks_can_use_feature', [__CLASS__, 'check_feature_permission'], 10, 4);
        
        // Initialize permission validation
        add_action('init', [__CLASS__, 'validate_all_permissions']);
    }
    
    /**
     * Override WordPress capabilities with permission matrix
     * Build Guide: Permission Matrix overrides any role narrative or UI behavior
     * 
     * @param array $allcaps All capabilities
     * @param array $caps Required capabilities
     * @param array $args Additional arguments
     * @param WP_User $user User object
     * @return array Modified capabilities
     */
    public static function override_wordpress_capabilities($allcaps, $caps, $args, $user) {
        if (empty($caps)) {
            return $allcaps;
        }
        
        $user_id = $user->ID;
        $role = self::get_user_role($user_id);
        $mode = self::get_current_mode($user_id);
        
        foreach ($caps as $cap) {
            // Map WordPress capabilities to OraBooks actions
            $action = self::map_capability_to_action($cap);
            
            if ($action) {
                $permission = self::check_permission($user_id, $role, $mode, $action);
                $allcaps[$cap] = $permission['allowed'];
                
                // Log permission override
                self::log_permission_override($user_id, $role, $mode, $action, $cap, $permission);
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Check menu access based on permission matrix
     * 
     * @param bool $access Current access status
     * @param string $menu_slug Menu slug
     * @param int $user_id User ID
     * @return bool Whether access is allowed
     */
    public static function check_menu_access($access, $menu_slug, $user_id) {
        if (!$access) {
            return $access;
        }
        
        // Map menu to action
        $action = self::map_menu_to_action($menu_slug);
        
        if (!$action) {
            return $access; // No mapping, allow by default
        }
        
        $role = self::get_user_role($user_id);
        $mode = self::get_current_mode($user_id);
        
        $permission = self::check_permission($user_id, $role, $mode, $action);
        
        return $permission['allowed'];
    }
    
    /**
     * Enforce permission matrix on data access
     * Build Guide: Role gives capability, Mode gives boundary, Action gives legality
     * 
     * @param bool $access Current access status
     * @param string $data_type Data type
     * @param string $operation Operation type
     * @param int $user_id User ID
     * @return bool Whether access is allowed
     */
    public static function enforce_permission_matrix($access, $data_type, $operation, $user_id) {
        if (!$access) {
            return $access;
        }
        
        // Map data operation to action
        $action = self::map_data_operation_to_action($data_type, $operation);
        
        if (!$action) {
            return $access; // No mapping, allow by default
        }
        
        $role = self::get_user_role($user_id);
        $mode = self::get_current_mode($user_id);
        
        $permission = self::check_permission($user_id, $role, $mode, $action);
        
        // Log data access attempt
        self::log_data_access_attempt($user_id, $role, $mode, $action, $data_type, $operation, $permission);
        
        return $permission['allowed'];
    }
    
    /**
     * Check feature permission
     * 
     * @param bool $access Current access status
     * @param string $feature Feature name
     * @param string $operation Operation on feature
     * @param int $user_id User ID
     * @return bool Whether access is allowed
     */
    public static function check_feature_permission($access, $feature, $operation, $user_id) {
        if (!$access) {
            return $access;
        }
        
        // Map feature operation to action
        $action = self::map_feature_operation_to_action($feature, $operation);
        
        if (!$action) {
            return $access; // No mapping, allow by default
        }
        
        $role = self::get_user_role($user_id);
        $mode = self::get_current_mode($user_id);
        
        $permission = self::check_permission($user_id, $role, $mode, $action);
        
        // Log feature access attempt
        self::log_feature_access_attempt($user_id, $role, $mode, $action, $feature, $operation, $permission);
        
        return $permission['allowed'];
    }
    
    /**
     * Map WordPress capability to OraBooks action
     * 
     * @param string $capability WordPress capability
     * @return string|null OraBooks action
     */
    private static function map_capability_to_action($capability) {
        $mapping = [
            'manage_options' => self::ACTION_USER_MANAGEMENT,
            'edit_posts' => self::ACTION_CREATE_TRANSACTION,
            'publish_posts' => self::ACTION_POST_JOURNAL_ENTRY,
            'edit_others_posts' => self::ACTION_APPROVE_TRANSACTION,
            'manage_categories' => self::ACTION_MANAGE_CHART_OF_ACCOUNTS,
            'export' => self::ACTION_GENERATE_SHARE_REPORTS,
            'import' => self::ACTION_SYSTEM_MIGRATION_APPROVAL,
        ];
        
        return isset($mapping[$capability]) ? $mapping[$capability] : null;
    }
    
    /**
     * Map menu slug to action
     * 
     * @param string $menu_slug Menu slug
     * @return string|null OraBooks action
     */
    private static function map_menu_to_action($menu_slug) {
        $mapping = [
            'orabooks-dashboard' => self::ACTION_VIEW_DATA_REPORTS,
            'orabooks-transactions' => self::ACTION_CREATE_TRANSACTION,
            'orabooks-journal-entries' => self::ACTION_POST_JOURNAL_ENTRY,
            'orabooks-approval' => self::ACTION_APPROVE_TRANSACTION,
            'orabooks-chart-of-accounts' => self::ACTION_MANAGE_CHART_OF_ACCOUNTS,
            'orabooks-trust-funds' => self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS,
            'orabooks-payroll' => self::ACTION_RUN_PAYROLL,
            'orabooks-tax' => self::ACTION_FILE_SUBMIT_TAX_VAT,
            'orabooks-reports' => self::ACTION_GENERATE_SHARE_REPORTS,
            'orabooks-users' => self::ACTION_USER_MANAGEMENT,
        ];
        
        return isset($mapping[$menu_slug]) ? $mapping[$menu_slug] : null;
    }
    
    /**
     * Map data operation to action
     * 
     * @param string $data_type Data type
     * @param string $operation Operation type
     * @return string|null OraBooks action
     */
    private static function map_data_operation_to_action($data_type, $operation) {
        // Map based on data type and operation
        switch ($operation) {
            case 'read':
                return self::ACTION_VIEW_DATA_REPORTS;
                
            case 'create':
            case 'insert':
                return self::ACTION_CREATE_TRANSACTION;
                
            case 'update':
            case 'modify':
                return self::ACTION_ADJUST_CORRECT;
                
            case 'delete':
                return self::ACTION_ADJUST_CORRECT;
                
            default:
                return null;
        }
    }
    
    /**
     * Map feature operation to action
     * 
     * @param string $feature Feature name
     * @param string $operation Operation type
     * @return string|null OraBooks action
     */
    private static function map_feature_operation_to_action($feature, $operation) {
        // Map specific features to actions
        if (strpos($feature, 'trust') !== false || strpos($feature, 'restricted') !== false) {
            return self::ACTION_ACCESS_TRUST_RESTRICTED_FUNDS;
        }
        
        if ($feature === 'payroll') {
            return self::ACTION_RUN_PAYROLL;
        }
        
        if ($feature === 'tax' || $feature === 'vat') {
            return self::ACTION_FILE_SUBMIT_TAX_VAT;
        }
        
        if ($feature === 'chart_of_accounts') {
            return self::ACTION_MANAGE_CHART_OF_ACCOUNTS;
        }
        
        // Default mapping based on operation
        return self::map_data_operation_to_action($feature, $operation);
    }
    
    /**
     * Log permission override
     * 
     * @param int $user_id User ID
     * @param string $role Role
     * @param string $mode Mode
     * @param string $action Action
     * @param string $capability WordPress capability
     * @param array $permission Permission result
     */
    private static function log_permission_override($user_id, $role, $mode, $action, $capability, $permission) {
        $log_entry = sprintf(
            '[OraBooks Permission Override] User: %d, Role: %s, Mode: %s, Action: %s, Capability: %s, Result: %s',
            $user_id,
            $role,
            $mode,
            $action,
            $capability,
            $permission['allowed'] ? 'ALLOWED' : 'DENIED'
        );
        
        error_log($log_entry);
        
        // Store in audit log if available
        if (class_exists('OraBooks_Audit_Logger')) {
            $logger = OraBooks_Audit_Logger::get_instance();
            $logger->log_action([
                'action_type' => 'permission_override',
                'action_description' => $log_entry,
                'user_id' => $user_id,
                'mode' => $mode,
                'role' => $role,
                'action' => $action,
                'result' => $permission['allowed'] ? 'allowed' : 'denied',
                'reason' => $permission['reason']
            ]);
        }
    }
    
    /**
     * Log data access attempt
     * 
     * @param int $user_id User ID
     * @param string $role Role
     * @param string $mode Mode
     * @param string $action Action
     * @param string $data_type Data type
     * @param string $operation Operation
     * @param array $permission Permission result
     */
    private static function log_data_access_attempt($user_id, $role, $mode, $action, $data_type, $operation, $permission) {
        $log_entry = sprintf(
            '[OraBooks Data Access] User: %d, Role: %s, Mode: %s, Action: %s, Data: %s, Operation: %s, Result: %s',
            $user_id,
            $role,
            $mode,
            $action,
            $data_type,
            $operation,
            $permission['allowed'] ? 'ALLOWED' : 'DENIED'
        );
        
        error_log($log_entry);
    }
    
    /**
     * Log feature access attempt
     * 
     * @param int $user_id User ID
     * @param string $role Role
     * @param string $mode Mode
     * @param string $action Action
     * @param string $feature Feature
     * @param string $operation Operation
     * @param array $permission Permission result
     */
    private static function log_feature_access_attempt($user_id, $role, $mode, $action, $feature, $operation, $permission) {
        $log_entry = sprintf(
            '[OraBooks Feature Access] User: %d, Role: %s, Mode: %s, Action: %s, Feature: %s, Operation: %s, Result: %s',
            $user_id,
            $role,
            $mode,
            $action,
            $feature,
            $operation,
            $permission['allowed'] ? 'ALLOWED' : 'DENIED'
        );
        
        error_log($log_entry);
    }
    
    /**
     * Validate all permissions on initialization
     */
    public static function validate_all_permissions() {
        // Ensure permission matrix is properly loaded
        $matrix = self::get_permission_matrix();
        
        if (empty($matrix)) {
            error_log('[OraBooks Permission Matrix] ERROR: Permission matrix is empty');
            return;
        }
        
        // Validate matrix structure
        foreach ($matrix as $role => $role_data) {
            if (!isset($role_data[self::MODE_BUSINESS]) || 
                !isset($role_data[self::MODE_LAW]) || 
                !isset($role_data[self::MODE_FAITH])) {
                error_log("[OraBooks Permission Matrix] ERROR: Role {$role} missing mode definitions");
            }
        }
        
        error_log('[OraBooks Permission Matrix] Validation completed');
    }
    
    /**
     * Get permission summary for user
     * 
     * @param int $user_id User ID
     * @return array Permission summary
     */
    public static function get_user_permission_summary($user_id) {
        $role = self::get_user_role($user_id);
        $mode = self::get_current_mode($user_id);
        $matrix = self::get_permission_matrix();
        
        $summary = [
            'user_id' => $user_id,
            'role' => $role,
            'mode' => $mode,
            'permissions' => []
        ];
        
        if (isset($matrix[$role][$mode])) {
            $summary['permissions'] = $matrix[$role][$mode];
        }
        
        return $summary;
    }
}
