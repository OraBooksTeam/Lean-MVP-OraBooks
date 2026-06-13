<?php
/**
 * OraBooks Feature Access Manager (Simplified)
 * 
 * The single source of truth for all feature access checks.
 * Consolidates Tier-based, Role-based, Mode-based, and Usage-based restrictions.
 * 
 * @package OraBooks_Membership
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Feature_Access_Manager {
    
    private static $instance = null;
    private static $access_cache = [];
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - Initialize hooks
     */
    public function __construct() {
        // Main filter for feature access
        add_filter('orabooks_can_access_feature', [$this, 'check_feature_access'], 10, 3);
        
        // Legacy support filters
        add_filter('orabooks_check_access', [$this, 'legacy_check_access'], 10, 4);
        add_filter('orabooks_limited_access_check', [$this, 'legacy_limited_check'], 10, 4);
    }
    
    /**
     * The NEW Unified Access Check API
     * 
     * @param string $feature_key The feature slug (e.g., 'invoices')
     * @param string $action The action (e.g., 'view', 'create', 'delete')
     * @param int $user_id User ID (defaults to current)
     * @return array [ 'allowed' => bool, 'reason' => string, 'limit_reached' => bool ]
     */
    public static function validate_access($feature_key, $action = 'view', $user_id = 0) {
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return ['allowed' => false, 'reason' => 'user_not_logged_in'];
        
        $cache_key = "{$user_id}_{$feature_key}_{$action}";
        if (isset(self::$access_cache[$cache_key])) {
            return self::$access_cache[$cache_key];
        }
        
        $result = self::get_instance()->perform_unified_check($user_id, $feature_key, $action);
        self::$access_cache[$cache_key] = $result;
        
        return $result;
    }
    
    /**
     * Perform the actual multi-layered check
     */
    private function perform_unified_check($user_id, $feature_key, $action) {
    // 1. Membership Level Check
        $level_id = get_user_meta($user_id, 'orabooks_level', true);
        if (!$level_id) {
            return ['allowed' => false, 'reason' => 'no_membership_level'];
        }
        
        $level_key = $level_id;
        if (is_numeric($level_id)) {
            $level = function_exists('orabooks_get_level') ? orabooks_get_level($level_id) : null;
            if ($level && function_exists('orabooks_guess_tier_key_from_level')) {
                $level_key = orabooks_guess_tier_key_from_level($level);
            }
        }
        
        // 2. Tier Feature Availability (Consulting OraBooks_Tier_Features)
        if (class_exists('OraBooks_Tier_Features')) {
            if (!OraBooks_Tier_Features::has_feature_access($level_key, $feature_key)) {
                return ['allowed' => false, 'reason' => 'tier_not_supported'];
            }
        }
        
        // 3. Mode Compatibility (Consulting OraBooks_Mode_Manager)
        $current_mode = class_exists('OraBooks_Mode_Manager') ? OraBooks_Mode_Manager::get_current_mode() : 'business';
        if (class_exists('OraBooks_Membership_Levels')) {
            $level_info = OraBooks_Membership_Levels::get_level_info($level_key);
            if ($level_info && $level_info['mode'] !== $current_mode && $level_info['mode'] !== 'all') {
                return ['allowed' => false, 'reason' => 'mode_incompatible'];
            }
        }
        
        // 4. Role-based Permission (Consulting OraBooks_Permission_Matrix)
        if (class_exists('OraBooks_Permission_Matrix')) {
            $user_role = OraBooks_Permission_Matrix::get_user_role($user_id);
            // Map feature to matrix action if possible
            $matrix_action = $this->map_feature_to_matrix_action($feature_key, $action);
            $permission = OraBooks_Permission_Matrix::check_permission($user_id, $user_role, $current_mode, $matrix_action);
            
            if (!$permission['allowed']) {
                return ['allowed' => false, 'reason' => 'permission_denied', 'matrix_reason' => $permission['reason']];
            }
        }
        
        // 5. Usage Limits (Consulting DB Rules from Limited Access Manager)
        $limit_check = $this->check_database_usage_limits($user_id, $feature_key, $action);
        if (!$limit_check['allowed']) {
            return $limit_check;
        }
        
        return ['allowed' => true, 'reason' => 'access_granted'];
    }
    
    /**
     * Check database-stored usage rules (Amount/Quantity/Time)
     */
    private function check_database_usage_limits($user_id, $feature_key, $action) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_limited_access_rules';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return ['allowed' => true];
        }
        
        // Get active rules for this feature
        $rules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE feature_key = %s AND is_active = 1",
            $feature_key
        ));
        
        if (empty($rules)) {
            return ['allowed' => true];
        }
        
        foreach ($rules as $rule) {
            $config = json_decode($rule->rule_config, true);
            if (!$config) continue;
            
            switch ($rule->rule_type) {
                case 'quantity_limit':
                    if (!$this->enforce_quantity_limit($user_id, $feature_key, $config)) {
                        return ['allowed' => false, 'reason' => 'quantity_limit_reached', 'rule_name' => $rule->rule_name];
                    }
                    break;
                    
                case 'amount_limit':
                    if (isset($_REQUEST['amount']) && $_REQUEST['amount'] > $config['max_amount']) {
                        return ['allowed' => false, 'reason' => 'amount_limit_exceeded', 'rule_name' => $rule->rule_name];
                    }
                    break;
                    
                case 'time_restriction':
                    if (!$this->is_within_time_window($config)) {
                        return ['allowed' => false, 'reason' => 'time_restricted', 'rule_name' => $rule->rule_name];
                    }
                    break;
            }
        }
        
        return ['allowed' => true];
    }
    
    /**
     * Enforce quantity limit based on tracking table
     */
    private function enforce_quantity_limit($user_id, $feature_key, $config) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_limited_access_usage'; // Assumed tracking table
        
        // Treat 0 as unlimited
        $max = $config['max_quantity'] ?? 0;
        if ($max <= 0) {
            return true;
        }
        
        // Check if tracking table exists before querying
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return true;
        }
        
        $period = $config['period'] ?? 'day';
        
        $date_limit = date('Y-m-d H:i:s', strtotime("-1 $period"));
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND feature_key = %s AND created_at > %s",
            $user_id, $feature_key, $date_limit
        ));
        
        return $count < $max;
    }
    
    /**
     * Time window check
     */
    private function is_within_time_window($config) {
        $now = current_time('H:i');
        $day = strtolower(date('D'));
        
        if (!empty($config['days']) && !in_array($day, $config['days'])) return false;
        if (!empty($config['start_time']) && $now < $config['start_time']) return false;
        if (!empty($config['end_time']) && $now > $config['end_time']) return false;
        
        return true;
    }
    
    /**
     * Map feature to matrix action
     */
    private function map_feature_to_matrix_action($feature_key, $action) {
        // Simplified mapping logic
        $map = [
            'invoices' => 'create_transaction',
            'expenses' => 'create_transaction',
            'reports' => 'view_data_reports',
            'users' => 'user_management',
            'chart_of_accounts' => 'manage_chart_of_accounts'
        ];
        
        return $map[$feature_key] ?? 'view_data_reports';
    }
    
    /**
     * Filter implementation for orabooks_can_access_feature
     */
    public function check_feature_access($allowed, $user_id, $feature_key) {
        $check = self::validate_access($feature_key, 'view', $user_id);
        return $check['allowed'];
    }
    
    /**
     * Legacy support for orabooks_check_access
     */
    public function legacy_check_access($granted, $user_id, $resource, $action = 'view') {
        $check = self::validate_access($resource, $action, $user_id);
        return $check['allowed'];
    }
    
    /**
     * Legacy support for orabooks_limited_access_check
     */
    public function legacy_limited_check($status, $user_id, $resource, $data) {
        $check = self::validate_access($resource, 'view', $user_id);
        return $check['allowed'];
    }
    
    /**
     * Clear cache
     */
    public static function clear_cache() {
        self::$access_cache = [];
    }
}

// Initialize
OraBooks_Feature_Access_Manager::get_instance();
