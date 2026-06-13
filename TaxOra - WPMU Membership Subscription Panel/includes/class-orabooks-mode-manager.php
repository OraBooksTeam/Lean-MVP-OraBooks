<?php
/**
 * OraBooks Mode Manager
 * Implements Mode-Aware System as per build guide requirements
 * 
 * Based on ORABOOKS_ULTIMATE_BUILD_GUIDE_A_TO_Z.md requirements:
 * - Three Modes: Business, Law, Faith
 * - Every feature must explicitly declare Mode
 * - Mode must be resolved before any accounting impact
 * - Cross-mode data access is hard-blocked
 * - Mode switching during active transaction is forbidden
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Mode_Manager {
    
    // Core Modes (from build guide)
    const MODE_BUSINESS = 'business';
    const MODE_LAW = 'law';
    const MODE_FAITH = 'faith';
    
    /**
     * Initialize mode management system
     * Build Guide Compliance: Mode must be resolved before any accounting impact
     */
    public static function init() {
        // Hook into user session to track mode
        add_action('wp_login', [__CLASS__, 'initialize_user_mode'], 10, 2);
        add_action('init', [__CLASS__, 'handle_mode_switch']);
        
        // Add mode indicator to admin bar
        add_action('admin_bar_menu', [__CLASS__, 'add_mode_to_admin_bar'], 100);
        
        // Validate mode compatibility for actions
        add_filter('orabooks_validate_action', [__CLASS__, 'validate_mode_compatibility'], 10, 3);
        
        // Build Guide: Cross-mode data access is hard-blocked
        add_filter('orabooks_data_access_check', [__CLASS__, 'enforce_cross_mode_blocking'], 10, 4);
        
        // Build Guide: Mode switching during active transaction is forbidden
        add_action('orabooks_before_transaction', [__CLASS__, 'check_transaction_mode_safety'], 10, 2);
        
        // Initialize mode validation for all features
        add_action('orabooks_feature_init', [__CLASS__, 'validate_feature_mode_compatibility'], 10, 2);
    }
    
    /**
     * Get available modes
     */
    public static function get_available_modes() {
        return [
            self::MODE_BUSINESS => [
                'name' => 'Business Mode',
                'description' => 'Regular commercial accounting and business operations',
                'icon' => 'dashicons-building',
                'color' => '#2563eb',
                'allowed_actions' => [
                    'all_business_operations',
                    'commercial_transactions',
                    'regular_accounting',
                    'inventory_management',
                    'payroll',
                    'tax_filing'
                ]
            ],
            self::MODE_LAW => [
                'name' => 'Law Mode',
                'description' => 'Legal practice accounting, trust accounts, and client fund management',
                'icon' => 'dashicons-gavel',
                'color' => '#7c3aed',
                'allowed_actions' => [
                    'trust_account_management',
                    'client_fund_handling',
                    'legal_accounting',
                    'compliance_reporting',
                    'client_billing'
                ],
                'restricted_actions' => [
                    'regular_payroll',
                    'business_inventory',
                    'commercial_operations'
                ]
            ],
            self::MODE_FAITH => [
                'name' => 'Faith Mode',
                'description' => 'Faith-based organization accounting, restricted funds, and donations',
                'icon' => 'dashicons-heart',
                'color' => '#dc2626',
                'allowed_actions' => [
                    'restricted_fund_management',
                    'donation_tracking',
                    'faith_accounting',
                    'religious_reporting',
                    'fund_accounting'
                ],
                'restricted_actions' => [
                    'commercial_operations',
                    'business_inventory',
                    'regular_payroll'
                ]
            ]
        ];
    }
    
    /**
     * Get current mode for user
     * 
     * @param int $user_id User ID (optional, defaults to current user)
     * @return string Current mode
     */
    public static function get_current_mode($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return self::MODE_BUSINESS; // Default for non-logged in users
        }
        
        // Check if mode is stored in user meta
        $stored_mode = get_user_meta($user_id, 'orabooks_current_mode', true);
        if ($stored_mode && in_array($stored_mode, [self::MODE_BUSINESS, self::MODE_LAW, self::MODE_FAITH])) {
            return $stored_mode;
        }
        
        // Check organization default mode
        $org_mode = self::get_organization_default_mode($user_id);
        if ($org_mode) {
            // Store for future use
            update_user_meta($user_id, 'orabooks_current_mode', $org_mode);
            return $org_mode;
        }
        
        // Default to Business mode
        update_user_meta($user_id, 'orabooks_current_mode', self::MODE_BUSINESS);
        return self::MODE_BUSINESS;
    }
    
    /**
     * Set current mode for user
     * 
     * @param int $user_id User ID
     * @param string $mode New mode
     * @return bool Success
     */
    public static function set_current_mode($user_id, $mode) {
        // Validate mode
        if (!in_array($mode, [self::MODE_BUSINESS, self::MODE_LAW, self::MODE_FAITH])) {
            return false;
        }
        
        // Check if user has permission for this mode
        if (!self::user_can_access_mode($user_id, $mode)) {
            return false;
        }
        
        // Check if there's an active transaction
        if (self::has_active_transaction($user_id)) {
            return false; // Cannot switch mode during active transaction
        }
        
        // Update user meta
        $old_mode = self::get_current_mode($user_id);
        update_user_meta($user_id, 'orabooks_current_mode', $mode);
        
        // Log mode switch for audit trail
        self::log_mode_switch($user_id, $old_mode, $mode);
        
        // Trigger action for other systems to respond
        do_action('orabooks_mode_switched', $user_id, $old_mode, $mode);
        
        return true;
    }
    
    /**
     * Check if user can access specific mode
     * 
     * @param int $user_id User ID
     * @param string $mode Mode to check
     * @return bool Can access mode
     */
    public static function user_can_access_mode($user_id, $mode) {
        // Super admins can access all modes
        if (is_super_admin($user_id)) {
            return true;
        }
        
        // Check user capabilities
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        // Mode-specific permissions
        switch ($mode) {
            case self::MODE_BUSINESS:
                // All users can access Business mode by default
                return true;
                
            case self::MODE_LAW:
                // Only users with law_mode_capability can access Law mode
                return user_can($user, 'access_law_mode') || in_array('legal_practitioner', $user->roles);
                
            case self::MODE_FAITH:
                // Only users with faith_mode_capability can access Faith mode
                return user_can($user, 'access_faith_mode') || in_array('faith_administrator', $user->roles);
                
            default:
                return false;
        }
    }
    
    /**
     * Check if action is compatible with current mode
     * 
     * @param string $action Action to check
     * @param string $mode Current mode (optional)
     * @param int $user_id User ID (optional)
     * @return array Compatibility result
     */
    public static function validate_mode_compatibility($is_valid, $action, $context) {
        if (!$is_valid) {
            return $is_valid;
        }
        
        $user_id = isset($context['user_id']) ? $context['user_id'] : get_current_user_id();
        $mode = self::get_current_mode($user_id);
        
        $modes = self::get_available_modes();
        $mode_config = isset($modes[$mode]) ? $modes[$mode] : [];
        
        // Check if action is allowed in current mode
        $allowed_actions = isset($mode_config['allowed_actions']) ? $mode_config['allowed_actions'] : [];
        $restricted_actions = isset($mode_config['restricted_actions']) ? $mode_config['restricted_actions'] : [];
        
        if (in_array($action, $restricted_actions)) {
            return [
                'valid' => false,
                'reason' => "Action '{$action}' is not allowed in {$mode_config['name']}",
                'mode' => $mode,
                'action' => $action
            ];
        }
        
        // For Business mode, allow all actions unless explicitly restricted
        if ($mode === self::MODE_BUSINESS) {
            return [
                'valid' => true,
                'mode' => $mode,
                'action' => $action
            ];
        }
        
        // For Law and Faith modes, check if action is explicitly allowed
        if (!in_array($action, $allowed_actions) && !in_array('all_operations', $allowed_actions)) {
            return [
                'valid' => false,
                'reason' => "Action '{$action}' requires explicit permission in {$mode_config['name']}",
                'mode' => $mode,
                'action' => $action
            ];
        }
        
        return [
            'valid' => true,
            'mode' => $mode,
            'action' => $action
        ];
    }
    
    /**
     * Get organization default mode
     * 
     * @param int $user_id User ID
     * @return string|null Default mode
     */
    private static function get_organization_default_mode($user_id) {
        // Check if user belongs to an organization with a default mode
        // This would be implemented based on your organization structure
        
        // For now, check user meta for organization preference
        $org_mode = get_user_meta($user_id, 'orabooks_organization_mode', true);
        if ($org_mode && in_array($org_mode, [self::MODE_BUSINESS, self::MODE_LAW, self::MODE_FAITH])) {
            return $org_mode;
        }
        
        return null;
    }
    
    /**
     * Check if user has active transaction
     * 
     * @param int $user_id User ID
     * @return bool Has active transaction
     */
    private static function has_active_transaction($user_id) {
        // Check for active transaction flags in session or database
        if (OraBooks_Session::get_instance()->get('orabooks_active_transaction') === true) {
            return true;
        }
        
        // Check database for active transactions
        global $wpdb;
        $active_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}orabooks_transactions 
             WHERE user_id = %d AND status = 'active'",
            $user_id
        ));
        
        return $active_count > 0;
    }
    
    /**
     * Log mode switch for audit trail
     * 
     * @param int $user_id User ID
     * @param string $old_mode Previous mode
     * @param string $new_mode New mode
     */
    private static function log_mode_switch($user_id, $old_mode, $new_mode) {
        $log_entry = [
            'user_id' => $user_id,
            'action' => 'mode_switch',
            'old_mode' => $old_mode,
            'new_mode' => $new_mode,
            'timestamp' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        // Store in audit log
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'orabooks_audit_log',
            $log_entry
        );
        
        // Also log to error log for immediate visibility
        error_log(sprintf(
            '[OraBooks Mode Switch] User %d switched from %s to %s',
            $user_id,
            $old_mode,
            $new_mode
        ));
    }
    
    /**
     * Initialize user mode on login
     * 
     * @param string $user_login Username
     * @param WP_User $user User object
     */
    public static function initialize_user_mode($user_login, $user) {
        // Ensure user has a valid mode set
        $current_mode = self::get_current_mode($user->ID);
        
        // If user doesn't have access to current mode, switch to Business mode
        if (!self::user_can_access_mode($user->ID, $current_mode)) {
            self::set_current_mode($user->ID, self::MODE_BUSINESS);
        }
    }
    
    /**
     * Handle mode switch requests
     */
    public static function handle_mode_switch() {
        if (isset($_REQUEST['orabooks_switch_mode']) && is_user_logged_in()) {
            $nonce = $_REQUEST['_wpnonce'] ?? '';
            $new_mode = $_REQUEST['orabooks_switch_mode'];
            
            if (wp_verify_nonce($nonce, 'switch_mode_' . $new_mode)) {
                $user_id = get_current_user_id();
                if (self::set_current_mode($user_id, $new_mode)) {
                    // Redirect to prevent form resubmission
                    wp_redirect(remove_query_arg(['orabooks_switch_mode', '_wpnonce']));
                    exit;
                } else {
                    wp_die('Mode switch failed. You may not have permission or there is an active transaction.');
                }
            }
        }
    }
    
    /**
     * Add mode indicator to admin bar
     * 
     * @param WP_Admin_Bar $admin_bar Admin bar object
     */
    public static function add_mode_to_admin_bar($admin_bar) {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $current_mode = self::get_current_mode($user_id);
        $modes = self::get_available_modes();
        $mode_config = isset($modes[$current_mode]) ? $modes[$current_mode] : [];
        
        // Add current mode indicator
        $admin_bar->add_menu([
            'id' => 'orabooks-current-mode',
            'title' => sprintf(
                '<span style="color: %s;">%s: %s</span>',
                $mode_config['color'] ?? '#666',
                __('Mode', 'orabooks'),
                $mode_config['name'] ?? __('Unknown', 'orabooks')
            ),
            'href' => '#',
            'meta' => [
                'title' => $mode_config['description'] ?? ''
            ]
        ]);
        
        // Add mode switcher if user has multiple mode access
        $accessible_modes = [];
        foreach ([self::MODE_BUSINESS, self::MODE_LAW, self::MODE_FAITH] as $mode) {
            if (self::user_can_access_mode($user_id, $mode) && $mode !== $current_mode) {
                $accessible_modes[$mode] = $modes[$mode];
            }
        }
        
        if (!empty($accessible_modes)) {
            foreach ($accessible_modes as $mode => $config) {
                $admin_bar->add_menu([
                    'parent' => 'orabooks-current-mode',
                    'id' => 'orabooks-switch-' . $mode,
                    'title' => sprintf(
                        '<span style="color: %s;">%s</span>',
                        $config['color'] ?? '#666',
                        $config['name']
                    ),
                    'href' => wp_nonce_url(
                        add_query_arg('orabooks_switch_mode', $mode),
                        'switch_mode_' . $mode
                    ),
                    'meta' => [
                        'title' => $config['description'] ?? ''
                    ]
                ]);
            }
        }
    }
    
    /**
     * Get mode-specific validation rules
     * 
     * @param string $mode Mode
     * @return array Validation rules
     */
    public static function get_mode_validation_rules($mode) {
        $rules = [
            self::MODE_BUSINESS => [
                'required_fields' => [],
                'restricted_fields' => [],
                'validation_callbacks' => []
            ],
            self::MODE_LAW => [
                'required_fields' => ['client_reference', 'matter_number'],
                'restricted_fields' => ['business_expense_categories'],
                'validation_callbacks' => ['validate_trust_account_compliance']
            ],
            self::MODE_FAITH => [
                'required_fields' => ['fund_designation', 'purpose_code'],
                'restricted_fields' => ['commercial_expense_types'],
                'validation_callbacks' => ['validate_restricted_fund_compliance']
            ]
        ];
        
        return isset($rules[$mode]) ? $rules[$mode] : $rules[self::MODE_BUSINESS];
    }
    
    /**
     * Enforce cross-mode data access blocking (Build Guide Requirement)
     * 
     * @param bool $allowed Current access status
     * @param string $data_type Type of data being accessed
     * @param int $user_id User ID
     * @param array $context Access context
     * @return bool Whether access is allowed
     */
    public static function enforce_cross_mode_blocking($allowed, $data_type, $user_id, $context) {
        if (!$allowed) {
            return $allowed;
        }
        
        $current_mode = self::get_current_mode($user_id);
        $data_mode = isset($context['mode']) ? $context['mode'] : null;
        
        // If data has no mode, allow access
        if (!$data_mode) {
            return $allowed;
        }
        
        // Build Guide: Cross-mode data access is hard-blocked
        if ($data_mode !== $current_mode) {
            // Log cross-mode access attempt
            self::log_cross_mode_attempt($user_id, $current_mode, $data_mode, $data_type);
            
            return false;
        }
        
        return $allowed;
    }
    
    /**
     * Check transaction mode safety (Build Guide Requirement)
     * 
     * @param int $user_id User ID
     * @param array $transaction_data Transaction data
     * @return bool Whether transaction can proceed
     */
    public static function check_transaction_mode_safety($user_id, $transaction_data) {
        $current_mode = self::get_current_mode($user_id);
        
        // Check if transaction mode matches current mode
        $transaction_mode = isset($transaction_data['mode']) ? $transaction_data['mode'] : null;
        
        if ($transaction_mode && $transaction_mode !== $current_mode) {
            // Block transaction - mode mismatch
            return false;
        }
        
        // Set transaction flag to prevent mode switching
        self::set_active_transaction_flag($user_id);
        
        return true;
    }
    
    /**
     * Validate feature mode compatibility (Build Guide Requirement)
     * 
     * @param string $feature Feature name
     * @param array $feature_config Feature configuration
     */
    public static function validate_feature_mode_compatibility($feature, $feature_config) {
        // Every feature must explicitly declare Mode
        if (!isset($feature_config['mode'])) {
            // Add default mode requirement
            $feature_config['mode'] = [self::MODE_BUSINESS];
        }
        
        // Ensure mode is an array
        if (!is_array($feature_config['mode'])) {
            $feature_config['mode'] = [$feature_config['mode']];
        }
        
        // Validate mode values
        $valid_modes = [self::MODE_BUSINESS, self::MODE_LAW, self::MODE_FAITH];
        foreach ($feature_config['mode'] as $mode) {
            if (!in_array($mode, $valid_modes)) {
                // Invalid mode - remove it
                $feature_config['mode'] = array_intersect($feature_config['mode'], $valid_modes);
            }
        }
        
        return $feature_config;
    }
    
    /**
     * Set active transaction flag
     * 
     * @param int $user_id User ID
     */
    private static function set_active_transaction_flag($user_id) {
        OraBooks_Session::get_instance()->set('orabooks_active_transaction', true);
        
        // Also store in database for persistence
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'orabooks_transactions',
            [
                'user_id' => $user_id,
                'status' => 'active',
                'created_at' => current_time('mysql')
            ]
        );
    }
    
    /**
     * Clear active transaction flag
     * 
     * @param int $user_id User ID
     */
    public static function clear_active_transaction_flag($user_id) {
        OraBooks_Session::get_instance()->delete('orabooks_active_transaction');
        
        // Update database
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'orabooks_transactions',
            ['status' => 'completed'],
            ['user_id' => $user_id, 'status' => 'active']
        );
    }
    
    /**
     * Log cross-mode access attempt
     * 
     * @param int $user_id User ID
     * @param string $current_mode Current mode
     * @param string $data_mode Data mode
     * @param string $data_type Data type
     */
    private static function log_cross_mode_attempt($user_id, $current_mode, $data_mode, $data_type) {
        $log_entry = [
            'user_id' => $user_id,
            'action' => 'cross_mode_access_attempt',
            'current_mode' => $current_mode,
            'data_mode' => $data_mode,
            'data_type' => $data_type,
            'timestamp' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        // Store in audit log
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'orabooks_audit_log',
            $log_entry
        );
        
        // Log to error log
        error_log(sprintf(
            '[OraBooks Security] Cross-mode access blocked - User %d in %s mode attempted to access %s data in %s mode',
            $user_id,
            $current_mode,
            $data_type,
            $data_mode
        ));
    }
    
    /**
     * Get mode-specific data isolation rules
     * 
     * @param string $mode Mode
     * @return array Isolation rules
     */
    public static function get_mode_isolation_rules($mode) {
        $rules = [
            self::MODE_BUSINESS => [
                'allowed_data_types' => [
                    'commercial_transactions',
                    'business_accounts',
                    'regular_inventory',
                    'business_reports'
                ],
                'forbidden_data_types' => [
                    'trust_accounts',
                    'restricted_funds',
                    'client_matters',
                    'faith_donations'
                ]
            ],
            self::MODE_LAW => [
                'allowed_data_types' => [
                    'trust_accounts',
                    'client_matters',
                    'legal_transactions',
                    'compliance_reports'
                ],
                'forbidden_data_types' => [
                    'commercial_transactions',
                    'business_accounts',
                    'regular_inventory',
                    'faith_donations'
                ]
            ],
            self::MODE_FAITH => [
                'allowed_data_types' => [
                    'restricted_funds',
                    'faith_donations',
                    'religious_transactions',
                    'faith_reports'
                ],
                'forbidden_data_types' => [
                    'commercial_transactions',
                    'business_accounts',
                    'trust_accounts',
                    'regular_inventory'
                ]
            ]
        ];
        
        return isset($rules[$mode]) ? $rules[$mode] : $rules[self::MODE_BUSINESS];
    }
    
    /**
     * Validate mode compatibility for data operation
     * 
     * @param string $operation Operation type
     * @param string $data_type Data type
     * @param int $user_id User ID
     * @return array Validation result
     */
    public static function validate_data_operation_mode($operation, $data_type, $user_id) {
        $current_mode = self::get_current_mode($user_id);
        $isolation_rules = self::get_mode_isolation_rules($current_mode);
        
        $result = [
            'allowed' => false,
            'reason' => '',
            'mode' => $current_mode,
            'operation' => $operation,
            'data_type' => $data_type
        ];
        
        // Check if data type is forbidden in current mode
        if (in_array($data_type, $isolation_rules['forbidden_data_types'])) {
            $result['reason'] = "Data type '{$data_type}' is forbidden in {$current_mode} mode";
            return $result;
        }
        
        // Check if data type is allowed in current mode
        if (!in_array($data_type, $isolation_rules['allowed_data_types'])) {
            $result['reason'] = "Data type '{$data_type}' requires explicit permission in {$current_mode} mode";
            return $result;
        }
        
        $result['allowed'] = true;
        $result['reason'] = "Operation allowed in {$current_mode} mode";
        
        return $result;
    }
}
