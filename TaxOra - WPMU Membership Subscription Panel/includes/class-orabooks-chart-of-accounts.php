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
            org_id bigint(20) NOT NULL,
            code varchar(20) NOT NULL,
            name varchar(255) NOT NULL,
            account_type varchar(5) NOT NULL,
            normal_balance varchar(10) NOT NULL COMMENT 'debit or credit',
            parent_id bigint(20) DEFAULT NULL,
            mode_compatibility varchar(100) DEFAULT 'all',
            description text DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            is_locked tinyint(1) DEFAULT 0,
            system_generated tinyint(1) DEFAULT 1,
            balance decimal(20,2) DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uk_org_code (org_id, code),
            KEY account_type (account_type),
            KEY parent_id (parent_id),
            KEY mode (mode_compatibility),
            KEY active (is_active)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Note: Template loading is now deferred to org creation (call load_default_coa_template($org_id))
        // No longer loaded automatically on init since we need an org context.
    }
    
    /**
     * Load default Chart of Accounts template (IFRS-lite) for a specific organization.
     *
     * @param int $org_id Organization ID to load accounts for
     * @param string $org_type Optional. Organization type ('customer' or 'partner'). Partners are skipped.
     * @return bool True if accounts were loaded, false if skipped or already loaded
     */
    public function load_default_coa_template($org_id = 0, $org_type = 'customer') {
        global $wpdb;
        
        // SL-017: Partner orgs skip CoA entirely — they have no accounting features
        if ($org_type === 'partner') {
            return false;
        }
        
        if (empty($org_id)) {
            return false;
        }
        
        $table_name = $wpdb->prefix . 'orabooks_chart_of_accounts';
        
        // Check if this org already has accounts loaded
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE org_id = %d",
            $org_id
        ));
        
        if ($count > 0) {
            return false; // Already has accounts for this org
        }
        
        // Default IFRS-lite CoA template
        $default_accounts = array(
            // Assets (normal_balance = debit)
            array('code' => '1000', 'name' => 'Cash and Cash Equivalents',     'type' => self::TYPE_ASSET,   'parent' => null, 'normal_balance' => 'debit'),
            array('code' => '1100', 'name' => 'Accounts Receivable',           'type' => self::TYPE_ASSET,   'parent' => null, 'normal_balance' => 'debit'),
            array('code' => '1200', 'name' => 'Inventory',                     'type' => self::TYPE_ASSET,   'parent' => null, 'normal_balance' => 'debit'),
            array('code' => '1300', 'name' => 'Prepaid Expenses',              'type' => self::TYPE_ASSET,   'parent' => null, 'normal_balance' => 'debit'),
            array('code' => '1400', 'name' => 'Fixed Assets',                  'type' => self::TYPE_ASSET,   'parent' => null, 'normal_balance' => 'debit'),
            array('code' => '1500', 'name' => 'Accumulated Depreciation',      'type' => self::TYPE_ASSET,   'parent' => null, 'normal_balance' => 'debit'),
            
            // Liabilities (normal_balance = credit)
            array('code' => '2000', 'name' => 'Accounts Payable',              'type' => self::TYPE_LIABILITY, 'parent' => null, 'normal_balance' => 'credit'),
            array('code' => '2100', 'name' => 'Accrued Expenses',              'type' => self::TYPE_LIABILITY, 'parent' => null, 'normal_balance' => 'credit'),
            array('code' => '2200', 'name' => 'Short-term Debt',               'type' => self::TYPE_LIABILITY, 'parent' => null, 'normal_balance' => 'credit'),
            array('code' => '2300', 'name' => 'Long-term Debt',                'type' => self::TYPE_LIABILITY, 'parent' => null, 'normal_balance' => 'credit'),
            
            // Equity (normal_balance = credit)
            array('code' => '3000', 'name' => 'Owner\'s Equity',                'type' => self::TYPE_EQUITY,   'parent' => null, 'normal_balance' => 'credit'),
            array('code' => '3100', 'name' => 'Retained Earnings',             'type' => self::TYPE_EQUITY,   'parent' => null, 'normal_balance' => 'credit'),
            
            // Income (normal_balance = credit)
            array('code' => '4000', 'name' => 'Sales Revenue',                 'type' => self::TYPE_INCOME,   'parent' => null, 'normal_balance' => 'credit'),
            array('code' => '4100', 'name' => 'Service Revenue',               'type' => self::TYPE_INCOME,   'parent' => null, 'normal_balance' => 'credit'),
            array('code' => '4200', 'name' => 'Other Income',                  'type' => self::TYPE_INCOME,   'parent' => null, 'normal_balance' => 'credit'),
            
            // Expenses (normal_balance = debit)
            array('code' => '5000', 'name' => 'Cost of Goods Sold',            'type' => self::TYPE_EXPENSE,  'parent' => null, 'normal_balance' => 'debit'),
            array('code' => '5100', 'name' => 'Salaries and Wages',            'type' => self::TYPE_EXPENSE,  'parent' => null, 'normal_balance' => 'debit'),
            array('code' => '5200', 'name' => 'Rent Expense',                  'type' => self::TYPE_EXPENSE,  'parent' => null, 'normal_balance' => 'debit'),
            array('code' => '5300', 'name' => 'Utilities Expense',             'type' => self::TYPE_EXPENSE,  'parent' => null, 'normal_balance' => 'debit'),
            array('code' => '5400', 'name' => 'Marketing Expense',             'type' => self::TYPE_EXPENSE,  'parent' => null, 'normal_balance' => 'debit'),
            array('code' => '5500', 'name' => 'Other Expenses',                'type' => self::TYPE_EXPENSE,  'parent' => null, 'normal_balance' => 'debit'),
        );
        
        foreach ($default_accounts as $account) {
            $wpdb->insert(
                $table_name,
                array(
                    'org_id'          => $org_id,
                    'code'            => $account['code'],
                    'name'            => $account['name'],
                    'account_type'    => $account['type'],
                    'normal_balance'  => $account['normal_balance'],
                    'parent_id'       => $account['parent'],
                    'mode_compatibility' => 'all',
                    'system_generated' => 1,
                    'created_by'      => 0,
                )
            );
        }
        
        return true;
    }
    
    /**
     * Get the default normal_balance for a given account type.
     *
     * @param string $type Account type constant
     * @return string 'debit' or 'credit'
     */
    public static function get_default_normal_balance($type) {
        $map = array(
            self::TYPE_ASSET    => 'debit',
            self::TYPE_EXPENSE  => 'debit',
            self::TYPE_LIABILITY => 'credit',
            self::TYPE_EQUITY   => 'credit',
            self::TYPE_INCOME   => 'credit',
        );
        return isset($map[$type]) ? $map[$type] : 'debit';
    }

    /**
     * Create a new account
     * 
     * @param array $data Account data
     * @return int|WP_Error Account ID or error
     */
    public function create_account($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_chart_of_accounts';
        
        $defaults = array(
            'org_id' => 0,
            'code' => '',
            'name' => '',
            'account_type' => '',
            'normal_balance' => '',
            'parent_id' => null,
            'mode_compatibility' => 'all',
            'description' => '',
            'system_generated' => 0,
            'created_by' => get_current_user_id(),
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate org_id (required)
        if (empty($data['org_id'])) {
            return new WP_Error('missing_org_id', 'Organization ID is required');
        }
        
        // Validate account type
        if (!in_array($data['account_type'], array(self::TYPE_ASSET, self::TYPE_LIABILITY, self::TYPE_EQUITY, self::TYPE_INCOME, self::TYPE_EXPENSE))) {
            return new WP_Error('invalid_type', 'Invalid account type');
        }
        
        // Auto-derive normal_balance if not provided
        if (empty($data['normal_balance'])) {
            $data['normal_balance'] = self::get_default_normal_balance($data['account_type']);
        }
        
        // Validate normal_balance
        if (!in_array($data['normal_balance'], array('debit', 'credit'))) {
            return new WP_Error('invalid_normal_balance', 'Normal balance must be debit or credit');
        }
        
        // Validate mode compatibility
        if (!in_array($data['mode_compatibility'], array('all', 'business', 'law', 'faith'))) {
            return new WP_Error('invalid_mode', 'Invalid mode compatibility');
        }
        
        // Check for duplicate code within the same org
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE org_id = %d AND code = %s",
            $data['org_id'],
            $data['code']
        ));
        
        if ($exists) {
            return new WP_Error('duplicate_code', 'Account code already exists for this organization');
        }
        
        // Insert account
        $result = $wpdb->insert(
            $table_name,
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d')
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
        
        return new WP_Error('insert_failed', 'Failed to create account: ' . $wpdb->last_error);
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
            'org_id' => null,
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
        
        if ($args['org_id']) {
            $where[] = 'org_id = %d';
            $values[] = $args['org_id'];
        }
        
        if ($args['account_type']) {
            $where[] = 'account_type = %s';
            $values[] = $args['account_type'];
        }
        
        if ($args['mode']) {
            if ($args['mode'] === 'all') {
                $where[] = 'mode_compatibility = %s';
                $values[] = $args['mode'];
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
     * @return bool|WP_Error Success status
     */
    public function update_account($account_id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_chart_of_accounts';
        
        $account = $this->get_account($account_id);
        
        if (!$account) {
            return new WP_Error('not_found', 'Account not found');
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
        
        // Check if normal_balance is being changed after use (immutable after posting)
        if (isset($data['normal_balance']) && $data['normal_balance'] !== $account->normal_balance) {
            $has_transactions = $this->account_has_transactions($account_id);
            if ($has_transactions) {
                return new WP_Error('normal_balance_immutable', 'Normal balance cannot be changed after account has been used');
            }
        }
        
        // Validate normal_balance if provided
        if (isset($data['normal_balance']) && !in_array($data['normal_balance'], array('debit', 'credit'))) {
            return new WP_Error('invalid_normal_balance', 'Normal balance must be debit or credit');
        }
        
        // Validate mode compatibility change
        if (isset($data['mode_compatibility'])) {
            if (!in_array($data['mode_compatibility'], array('all', 'business', 'law', 'faith'))) {
                return new WP_Error('invalid_mode', 'Invalid mode compatibility');
            }
        }
        
        // Prevent changing system_generated flag
        if (isset($data['system_generated'])) {
            unset($data['system_generated']);
        }
        
        // Prevent changing org_id
        if (isset($data['org_id'])) {
            unset($data['org_id']);
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
     * @return bool|WP_Error Success status
     */
    public function deactivate_account($account_id) {
        $account = $this->get_account($account_id);
        
        if (!$account) {
            return new WP_Error('not_found', 'Account not found');
        }
        
        // Cannot deactivate system-generated accounts
        if ($account->system_generated) {
            return new WP_Error('system_account', 'Cannot deactivate system-generated account');
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
     * @param int|null $parent_id Parent ID
     * @param int|null $org_id Organization ID to scope the tree
     * @return array Hierarchical accounts
     */
    public function get_hierarchical_accounts($parent_id = null, $org_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_chart_of_accounts';
        
        $org_condition = '';
        $values = array();
        if ($org_id) {
            $org_condition = ' AND org_id = %d';
            $values[] = $org_id;
        }
        
        $parent_condition = $parent_id ? '= %d' : 'IS NULL';
        if ($parent_id) {
            $values[] = intval($parent_id);
        }
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE parent_id $parent_condition AND is_active = 1$org_condition ORDER BY code ASC",
            $values
        );
        
        $accounts = $wpdb->get_results($sql);
        
        $result = array();
        foreach ($accounts as $account) {
            $account->children = $this->get_hierarchical_accounts($account->id, $org_id);
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
