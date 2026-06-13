<?php
/**
 * OraBooks Chart of Accounts
 * Implements Chart of Accounts system as per ORABOOKS_ULTIMATE_BUILD_GUIDE_A_TO_Z.md
 * 
 * Core Principles:
 * - Every JE line must reference a valid CoA ID
 * - Account type (A/L/E/I/X) is immutable after use
 * - Trust accounts → Law Mode only
 * - Restricted funds → Faith Mode only
 * - Used accounts can be locked, never deleted
 * - Hierarchical structure with parent-child relationships
 * 
 * @package OraBooks_Membership
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Chart_of_Accounts {
    
    /**
     * Account Types (from build guide)
     */
    const TYPE_ASSET = 'A';
    const TYPE_LIABILITY = 'L';
    const TYPE_EQUITY = 'E';
    const TYPE_INCOME = 'I';
    const TYPE_EXPENSE = 'X';
    
    /**
     * Single instance
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
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Create CoA tables on activation
        add_action('init', array($this, 'create_coa_tables'));
    }
    
    /**
     * Create Chart of Accounts database tables
     */
    public function create_coa_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'orabooks_chart_of_accounts';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL,
            name varchar(255) NOT NULL,
            account_type varchar(5) NOT NULL,
            parent_id bigint(20) DEFAULT NULL,
            mode_compatibility varchar(100) DEFAULT 'all',
            description text DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            is_locked tinyint(1) DEFAULT 0,
            is_system tinyint(1) DEFAULT 0,
            balance decimal(20,2) DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY account_type (account_type),
            KEY parent_id (parent_id),
            KEY mode (mode_compatibility),
            KEY active (is_active)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Load default CoA template if table is empty
        $this->load_default_coa_template();
    }
    
    /**
     * Load default Chart of Accounts template (IFRS-lite)
     */
    private function load_default_coa_template() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_chart_of_accounts';
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        if ($count > 0) {
            return; // Already has accounts
        }
        
        // Default IFRS-lite CoA template
        $default_accounts = array(
            // Assets
            array('code' => '1000', 'name' => 'Cash and Cash Equivalents', 'type' => self::TYPE_ASSET, 'parent' => null),
            array('code' => '1100', 'name' => 'Accounts Receivable', 'type' => self::TYPE_ASSET, 'parent' => null),
            array('code' => '1200', 'name' => 'Inventory', 'type' => self::TYPE_ASSET, 'parent' => null),
            array('code' => '1300', 'name' => 'Prepaid Expenses', 'type' => self::TYPE_ASSET, 'parent' => null),
            array('code' => '1400', 'name' => 'Fixed Assets', 'type' => self::TYPE_ASSET, 'parent' => null),
            array('code' => '1500', 'name' => 'Accumulated Depreciation', 'type' => self::TYPE_ASSET, 'parent' => null),
            
            // Liabilities
            array('code' => '2000', 'name' => 'Accounts Payable', 'type' => self::TYPE_LIABILITY, 'parent' => null),
            array('code' => '2100', 'name' => 'Accrued Expenses', 'type' => self::TYPE_LIABILITY, 'parent' => null),
            array('code' => '2200', 'name' => 'Short-term Debt', 'type' => self::TYPE_LIABILITY, 'parent' => null),
            array('code' => '2300', 'name' => 'Long-term Debt', 'type' => self::TYPE_LIABILITY, 'parent' => null),
            
            // Equity
            array('code' => '3000', 'name' => 'Owner\'s Equity', 'type' => self::TYPE_EQUITY, 'parent' => null),
            array('code' => '3100', 'name' => 'Retained Earnings', 'type' => self::TYPE_EQUITY, 'parent' => null),
            
            // Income
            array('code' => '4000', 'name' => 'Sales Revenue', 'type' => self::TYPE_INCOME, 'parent' => null),
            array('code' => '4100', 'name' => 'Service Revenue', 'type' => self::TYPE_INCOME, 'parent' => null),
            array('code' => '4200', 'name' => 'Other Income', 'type' => self::TYPE_INCOME, 'parent' => null),
            
            // Expenses
            array('code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => self::TYPE_EXPENSE, 'parent' => null),
            array('code' => '5100', 'name' => 'Salaries and Wages', 'type' => self::TYPE_EXPENSE, 'parent' => null),
            array('code' => '5200', 'name' => 'Rent Expense', 'type' => self::TYPE_EXPENSE, 'parent' => null),
            array('code' => '5300', 'name' => 'Utilities Expense', 'type' => self::TYPE_EXPENSE, 'parent' => null),
            array('code' => '5400', 'name' => 'Marketing Expense', 'type' => self::TYPE_EXPENSE, 'parent' => null),
            array('code' => '5500', 'name' => 'Other Expenses', 'type' => self::TYPE_EXPENSE, 'parent' => null),
        );
        
        foreach ($default_accounts as $account) {
            $wpdb->insert(
                $table_name,
                array(
                    'code' => $account['code'],
                    'name' => $account['name'],
                    'account_type' => $account['type'],
                    'parent_id' => $account['parent'],
                    'mode_compatibility' => 'all',
                    'is_system' => 1,
                    'created_by' => 0,
                )
            );
        }
    }
    
    /**
     * Create a new account
     * 
     * @param array $data Account data
     * @return int|false Account ID or false on failure
     */
    public function create_account($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_chart_of_accounts';
        
        $defaults = array(
            'code' => '',
            'name' => '',
            'account_type' => '',
            'parent_id' => null,
            'mode_compatibility' => 'all',
            'description' => '',
            'is_system' => 0,
            'created_by' => get_current_user_id(),
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate account type
        if (!in_array($data['account_type'], array(self::TYPE_ASSET, self::TYPE_LIABILITY, self::TYPE_EQUITY, self::TYPE_INCOME, self::TYPE_EXPENSE))) {
            return new WP_Error('invalid_type', 'Invalid account type');
        }
        
        // Validate mode compatibility
        if (!in_array($data['mode_compatibility'], array('all', 'business', 'law', 'faith'))) {
            return new WP_Error('invalid_mode', 'Invalid mode compatibility');
        }
        
        // Check for duplicate code
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE code = %s",
            $data['code']
        ));
        
        if ($exists) {
            return new WP_Error('duplicate_code', 'Account code already exists');
        }
        
        // Insert account
        $result = $wpdb->insert(
            $table_name,
            $data,
            array('%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d')
        );
        
        if ($result !== false) {
            $account_id = $wpdb->insert_id;
            
            // Log account creation
            if (class_exists('OraBooks_Audit_Logger')) {
                $logger = OraBooks_Audit_Logger::get_instance();
                $logger->log_coa_change($data['created_by'], $account_id, 'create', null, $data);
            }
            
            return $account_id;
        }
        
        return false;
    }
    
    /**
     * Get account by ID
     * 
     * @param int $account_id Account ID
     * @return object|null Account object
     */
    public function get_account($account_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_chart_of_accounts';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $account_id
        ));
    }
    
    /**
     * Get all accounts
     * 
     * @param array $args Filter arguments
     * @return array Accounts
     */
    public function get_accounts($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_chart_of_accounts';
        
        $defaults = array(
            'account_type' => null,
            'mode' => null,
            'is_active' => null,
            'parent_id' => null,
            'orderby' => 'code',
            'order' => 'ASC',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $values = array();
        
        if ($args['account_type']) {
            $where[] = 'account_type = %s';
            $values[] = $args['account_type'];
        }
        
        if ($args['mode']) {
            if ($args['mode'] === 'all') {
                $where[] = 'mode_compatibility = %s';
            } else {
                $where[] = '(mode_compatibility = %s OR mode_compatibility = %s)';
                $values[] = 'all';
                $values[] = $args['mode'];
            }
        }
        
        if ($args['is_active'] !== null) {
            $where[] = 'is_active = %d';
            $values[] = $args['is_active'] ? 1 : 0;
        }
        
        if ($args['parent_id'] !== null) {
            $where[] = 'parent_id = %d';
            $values[] = $args['parent_id'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE $where_clause ORDER BY {$args['orderby']} {$args['order']}",
            $values
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Update account
     * 
     * @param int $account_id Account ID
     * @param array $data Account data
     * @return bool Success status
     */
    public function update_account($account_id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_chart_of_accounts';
        
        $account = $this->get_account($account_id);
        
        if (!$account) {
            return false;
        }
        
        // Check if account is locked
        if ($account->is_locked) {
            return new WP_Error('account_locked', 'Account is locked and cannot be modified');
        }
        
        // Check if account type is being changed after use
        if (isset($data['account_type']) && $data['account_type'] !== $account->account_type) {
            $has_transactions = $this->account_has_transactions($account_id);
            if ($has_transactions) {
                return new WP_Error('type_immutable', 'Account type cannot be changed after use');
            }
        }
        
        // Validate mode compatibility change
        if (isset($data['mode_compatibility'])) {
            if (!in_array($data['mode_compatibility'], array('all', 'business', 'law', 'faith'))) {
                return new WP_Error('invalid_mode', 'Invalid mode compatibility');
            }
        }
        
        // Get before state for audit
        $before_state = (array) $account;
        
        // Update account
        $result = $wpdb->update(
            $table_name,
            $data,
            array('id' => $account_id)
        );
        
        if ($result !== false) {
            // Log account update
            if (class_exists('OraBooks_Audit_Logger')) {
                $logger = OraBooks_Audit_Logger::get_instance();
                $logger->log_coa_change(get_current_user_id(), $account_id, 'update', $before_state, $data);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Lock account (prevent further modifications)
     * 
     * @param int $account_id Account ID
     * @return bool Success status
     */
    public function lock_account($account_id) {
        return $this->update_account($account_id, array('is_locked' => 1));
    }
    
    /**
     * Deactivate account (soft delete)
     * 
     * @param int $account_id Account ID
     * @return bool Success status
     */
    public function deactivate_account($account_id) {
        $account = $this->get_account($account_id);
        
        if (!$account) {
            return false;
        }
        
        // Cannot deactivate system accounts
        if ($account->is_system) {
            return new WP_Error('system_account', 'Cannot deactivate system account');
        }
        
        return $this->update_account($account_id, array('is_active' => 0));
    }
    
    /**
     * Check if account has transactions
     * 
     * @param int $account_id Account ID
     * @return bool Has transactions
     */
    public function account_has_transactions($account_id) {
        global $wpdb;
        $je_lines_table = $wpdb->prefix . 'orabooks_journal_entry_lines';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $je_lines_table WHERE account_id = %d",
            $account_id
        ));
        
        return $count > 0;
    }
    
    /**
     * Validate account mode compatibility
     * 
     * @param int $account_id Account ID
     * @param string $mode Mode
     * @return bool Is compatible
     */
    public function validate_mode_compatibility($account_id, $mode) {
        $account = $this->get_account($account_id);
        
        if (!$account) {
            return false;
        }
        
        // If mode is 'all', it's compatible with everything
        if ($account->mode_compatibility === 'all') {
            return true;
        }
        
        // Check if account mode matches current mode
        return $account->mode_compatibility === $mode;
    }
    
    /**
     * Get account type name
     * 
     * @param string $type Account type
     * @return string Type name
     */
    public function get_type_name($type) {
        $types = array(
            self::TYPE_ASSET => 'Asset',
            self::TYPE_LIABILITY => 'Liability',
            self::TYPE_EQUITY => 'Equity',
            self::TYPE_INCOME => 'Income',
            self::TYPE_EXPENSE => 'Expense',
        );
        
        return isset($types[$type]) ? $types[$type] : 'Unknown';
    }
    
    /**
     * Get hierarchical accounts (tree structure)
     * 
     * @param int $parent_id Parent ID
     * @return array Hierarchical accounts
     */
    public function get_hierarchical_accounts($parent_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_chart_of_accounts';
        
        $accounts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE parent_id %s AND is_active = 1 ORDER BY code ASC",
            $parent_id ? '= ' . intval($parent_id) : 'IS NULL'
        ));
        
        $result = array();
        foreach ($accounts as $account) {
            $account->children = $this->get_hierarchical_accounts($account->id);
            $result[] = $account;
        }
        
        return $result;
    }
    
    /**
     * Validate trust account mode restriction
     * 
     * @param string $account_name Account name
     * @param string $mode Mode
     * @return bool Is valid
     */
    public function validate_trust_account_mode($account_name, $mode) {
        // Trust accounts only allowed in Law mode
        if (stripos($account_name, 'trust') !== false) {
            return $mode === OraBooks_Mode_Manager::MODE_LAW;
        }
        
        return true;
    }
    
    /**
     * Validate restricted fund mode restriction
     * 
     * @param string $account_name Account name
     * @param string $mode Mode
     * @return bool Is valid
     */
    public function validate_restricted_fund_mode($account_name, $mode) {
        // Restricted funds only allowed in Faith mode
        if (stripos($account_name, 'restricted') !== false || 
            stripos($account_name, 'fund') !== false) {
            return $mode === OraBooks_Mode_Manager::MODE_FAITH;
        }
        
        return true;
    }
}

// Initialize the Chart of Accounts system
OraBooks_Chart_of_Accounts::get_instance();
