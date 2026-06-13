<?php
/**
 * OraBooks Canonical Journal Entry Engine
 * Implements Canonical Journal Entry system as per ORABOOKS_ULTIMATE_BUILD_GUIDE_A_TO_Z.md
 * 
 * Core Principles:
 * - Every economic event → exactly one canonical JE
 * - No balance update without JE
 * - No UI-calculated balances
 * - JE must be: Double-entry (Dr = Cr), Append-only, CoA-bound, Source-linked
 * - Balances calculated on-demand from ledger, never stored separately
 * 
 * @package OraBooks_Membership
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Journal_Entry {
    
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
        add_action('init', array($this, 'create_je_tables'));
    }
    
    /**
     * Create Journal Entry database tables
     */
    public function create_je_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Journal Entries table (canonical JEs)
        $je_table = $wpdb->prefix . 'orabooks_journal_entries';
        $sql1 = "CREATE TABLE IF NOT EXISTS $je_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            je_number varchar(50) NOT NULL,
            description text NOT NULL,
            mode varchar(20) NOT NULL,
            source_type varchar(50) DEFAULT NULL,
            source_id bigint(20) DEFAULT NULL,
            entry_date datetime DEFAULT CURRENT_TIMESTAMP,
            total_debit decimal(20,2) DEFAULT 0.00,
            total_credit decimal(20,2) DEFAULT 0.00,
            status enum('draft','posted','reversed') DEFAULT 'draft',
            approval_status enum('pending','approved','rejected') DEFAULT 'pending',
            approved_by bigint(20) DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            created_by bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            posted_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY je_number (je_number),
            KEY mode (mode),
            KEY status (status),
            KEY source (source_type, source_id),
            KEY entry_date (entry_date)
        ) $charset_collate;";
        
        // Journal Entry Lines table
        $jel_table = $wpdb->prefix . 'orabooks_journal_entry_lines';
        $sql2 = "CREATE TABLE IF NOT EXISTS $jel_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            journal_entry_id bigint(20) NOT NULL,
            account_id bigint(20) NOT NULL,
            line_type enum('debit','credit') NOT NULL,
            amount decimal(20,2) NOT NULL,
            description text DEFAULT NULL,
            line_order int DEFAULT 0,
            PRIMARY KEY  (id),
            KEY je_id (journal_entry_id),
            KEY account_id (account_id),
            KEY line_type (line_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
    }
    
    /**
     * Create a canonical journal entry
     * 
     * @param array $data Journal Entry data
     * @return int|false JE ID or WP_Error on failure
     */
    public function create_journal_entry($data) {
        global $wpdb;
        $je_table = $wpdb->prefix . 'orabooks_journal_entries';
        $jel_table = $wpdb->prefix . 'orabooks_journal_entry_lines';
        
        $defaults = array(
            'description' => '',
            'mode' => 'business',
            'source_type' => null,
            'source_id' => null,
            'entry_date' => current_time('mysql'),
            'lines' => array(),
            'created_by' => get_current_user_id(),
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate mode
        if (!in_array($data['mode'], array('business', 'law', 'faith'))) {
            return new WP_Error('invalid_mode', 'Invalid mode specified');
        }
        
        // Validate lines
        if (empty($data['lines']) || !is_array($data['lines'])) {
            return new WP_Error('no_lines', 'Journal entry must have at least two lines');
        }
        
        // Validate double-entry (Dr = Cr)
        $total_debit = 0;
        $total_credit = 0;
        
        foreach ($data['lines'] as $line) {
            if (!isset($line['account_id']) || !isset($line['amount']) || !isset($line['line_type'])) {
                return new WP_Error('invalid_line', 'Each line must have account_id, amount, and line_type');
            }
            
            // Validate account exists
            if (class_exists('OraBooks_Chart_of_Accounts')) {
                $coa = OraBooks_Chart_of_Accounts::get_instance();
                $account = $coa->get_account($line['account_id']);
                if (!$account) {
                    return new WP_Error('invalid_account', 'Account does not exist');
                }
                
                // Validate mode compatibility
                if (!$coa->validate_mode_compatibility($line['account_id'], $data['mode'])) {
                    return new WP_Error('mode_incompatible', 'Account is not compatible with current mode');
                }
            }
            
            if ($line['line_type'] === 'debit') {
                $total_debit += floatval($line['amount']);
            } elseif ($line['line_type'] === 'credit') {
                $total_credit += floatval($line['amount']);
            } else {
                return new WP_Error('invalid_line_type', 'Line type must be debit or credit');
            }
        }
        
        // Enforce double-entry (Dr must equal Cr)
        if (abs($total_debit - $total_credit) > 0.01) {
            return new WP_Error('double_entry_violation', sprintf(
                'Double-entry violation: Debits (%.2f) must equal Credits (%.2f)',
                $total_debit,
                $total_credit
            ));
        }
        
        // Generate JE number
        $je_number = $this->generate_je_number();
        
        // Insert journal entry
        $wpdb->query('START TRANSACTION');
        
        try {
            $je_result = $wpdb->insert(
                $je_table,
                array(
                    'je_number' => $je_number,
                    'description' => $data['description'],
                    'mode' => $data['mode'],
                    'source_type' => $data['source_type'],
                    'source_id' => $data['source_id'],
                    'entry_date' => $data['entry_date'],
                    'total_debit' => $total_debit,
                    'total_credit' => $total_credit,
                    'status' => 'draft',
                    'approval_status' => 'pending',
                    'created_by' => $data['created_by'],
                ),
                array('%s', '%s', '%s', '%s', '%d', '%s', '%f', '%f', '%s', '%s', '%d')
            );
            
            if ($je_result === false) {
                throw new Exception('Failed to insert journal entry');
            }
            
            $je_id = $wpdb->insert_id;
            
            // Insert journal entry lines
            foreach ($data['lines'] as $index => $line) {
                $line_result = $wpdb->insert(
                    $jel_table,
                    array(
                        'journal_entry_id' => $je_id,
                        'account_id' => $line['account_id'],
                        'line_type' => $line['line_type'],
                        'amount' => $line['amount'],
                        'description' => $line['description'] ?? '',
                        'line_order' => $index,
                    ),
                    array('%d', '%d', '%s', '%f', '%s', '%d')
                );
                
                if ($line_result === false) {
                    throw new Exception('Failed to insert journal entry line');
                }
            }
            
            $wpdb->query('COMMIT');
            
            // Log JE creation
            if (class_exists('OraBooks_Audit_Logger')) {
                $logger = OraBooks_Audit_Logger::get_instance();
                $logger->log_journal_entry($data['created_by'], $je_id, array(
                    'je_number' => $je_number,
                    'description' => $data['description'],
                    'mode' => $data['mode'],
                    'total_debit' => $total_debit,
                    'total_credit' => $total_credit,
                    'status' => 'draft',
                ));
            }
            
            return $je_id;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('je_creation_failed', $e->getMessage());
        }
    }
    
    /**
     * Post journal entry (immutable once posted)
     * 
     * @param int $je_id Journal Entry ID
     * @param int $approved_by User ID of approver
     * @return bool Success status
     */
    public function post_journal_entry($je_id, $approved_by = null) {
        global $wpdb;
        $je_table = $wpdb->prefix . 'orabooks_journal_entries';
        
        $je = $this->get_journal_entry($je_id);
        
        if (!$je) {
            return new WP_Error('je_not_found', 'Journal entry not found');
        }
        
        if ($je->status === 'posted') {
            return new WP_Error('already_posted', 'Journal entry is already posted and cannot be modified');
        }
        
        if ($je->status === 'reversed') {
            return new WP_Error('already_reversed', 'Journal entry is reversed and cannot be posted');
        }
        
        // Update JE as posted
        $result = $wpdb->update(
            $je_table,
            array(
                'status' => 'posted',
                'approval_status' => 'approved',
                'approved_by' => $approved_by ?: get_current_user_id(),
                'approved_at' => current_time('mysql'),
                'posted_at' => current_time('mysql'),
            ),
            array('id' => $je_id)
        );
        
        if ($result !== false) {
            // Log JE posting
            if (class_exists('OraBooks_Audit_Logger')) {
                $logger = OraBooks_Audit_Logger::get_instance();
                $logger->log_journal_entry($approved_by ?: get_current_user_id(), $je_id, array(
                    'je_number' => $je->je_number,
                    'action' => 'posted',
                    'status' => 'posted',
                ));
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get journal entry by ID
     * 
     * @param int $je_id Journal Entry ID
     * @return object|null Journal Entry
     */
    public function get_journal_entry($je_id) {
        global $wpdb;
        $je_table = $wpdb->prefix . 'orabooks_journal_entries';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $je_table WHERE id = %d",
            $je_id
        ));
    }
    
    /**
     * Get journal entry lines
     * 
     * @param int $je_id Journal Entry ID
     * @return array Journal Entry Lines
     */
    public function get_journal_entry_lines($je_id) {
        global $wpdb;
        $jel_table = $wpdb->prefix . 'orabooks_journal_entry_lines';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $jel_table WHERE journal_entry_id = %d ORDER BY line_order ASC",
            $je_id
        ));
    }
    
    /**
     * Calculate account balance on-demand from ledger
     * 
     * @param int $account_id Account ID
     * @param string|null $date Optional date cutoff
     * @return float Account balance
     */
    public function calculate_account_balance($account_id, $date = null) {
        global $wpdb;
        $jel_table = $wpdb->prefix . 'orabooks_journal_entry_lines';
        $je_table = $wpdb->prefix . 'orabooks_journal_entries';
        
        $coa = OraBooks_Chart_of_Accounts::get_instance();
        $account = $coa->get_account($account_id);
        
        if (!$account) {
            return 0;
        }
        
        // Build query with date filter
        $date_clause = '';
        if ($date) {
            $date_clause = $wpdb->prepare(" AND je.entry_date <= %s", $date);
        }
        
        $sql = $wpdb->prepare(
            "SELECT 
                SUM(CASE WHEN jel.line_type = 'debit' THEN jel.amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN jel.line_type = 'credit' THEN jel.amount ELSE 0 END) as total_credit
            FROM $jel_table jel
            INNER JOIN $je_table je ON jel.journal_entry_id = je.id
            WHERE jel.account_id = %d 
                AND je.status = 'posted'
                $date_clause",
            $account_id
        );
        
        $result = $wpdb->get_row($sql);
        
        if (!$result) {
            return 0;
        }
        
        $debit = floatval($result->total_debit);
        $credit = floatval($result->total_credit);
        
        // Calculate balance based on account type
        // Assets and Expenses increase with debit
        // Liabilities, Equity, and Income increase with credit
        if (in_array($account->account_type, array(OraBooks_Chart_of_Accounts::TYPE_ASSET, OraBooks_Chart_of_Accounts::TYPE_EXPENSE))) {
            return $debit - $credit;
        } else {
            return $credit - $debit;
        }
    }
    
    /**
     * Generate unique JE number
     * 
     * @return string JE number
     */
    private function generate_je_number() {
        global $wpdb;
        $je_table = $wpdb->prefix . 'orabooks_journal_entries';
        
        $prefix = 'JE-' . date('Y-m-');
        
        // Get last JE number for this month
        $last_je = $wpdb->get_row($wpdb->prepare(
            "SELECT je_number FROM $je_table WHERE je_number LIKE %s ORDER BY id DESC LIMIT 1",
            $prefix . '%'
        ));
        
        if ($last_je) {
            // Extract sequence number and increment
            $parts = explode('-', $last_je->je_number);
            $sequence = intval(end($parts)) + 1;
        } else {
            $sequence = 1;
        }
        
        return $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);
    }
    
    /**
     * Get trial balance
     * 
     * @param string|null $date Optional date cutoff
     * @return array Trial balance data
     */
    public function get_trial_balance($date = null) {
        global $wpdb;
        $jel_table = $wpdb->prefix . 'orabooks_journal_entry_lines';
        $je_table = $wpdb->prefix . 'orabooks_journal_entries';
        $coa_table = $wpdb->prefix . 'orabooks_chart_of_accounts';
        
        $date_clause = '';
        if ($date) {
            $date_clause = $wpdb->prepare(" AND je.entry_date <= %s", $date);
        }
        
        $sql = $wpdb->prepare(
            "SELECT 
                coa.id as account_id,
                coa.code,
                coa.name,
                coa.account_type,
                SUM(CASE WHEN jel.line_type = 'debit' THEN jel.amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN jel.line_type = 'credit' THEN jel.amount ELSE 0 END) as total_credit
            FROM $coa_table coa
            INNER JOIN $jel_table jel ON coa.id = jel.account_id
            INNER JOIN $je_table je ON jel.journal_entry_id = je.id
            WHERE je.status = 'posted'
                AND coa.is_active = 1
                $date_clause
            GROUP BY coa.id
            ORDER BY coa.code ASC"
        );
        
        $accounts = $wpdb->get_results($sql);
        
        $total_debits = 0;
        $total_credits = 0;
        
        foreach ($accounts as $account) {
            $account->balance = 0;
            
            if (in_array($account->account_type, array(OraBooks_Chart_of_Accounts::TYPE_ASSET, OraBooks_Chart_of_Accounts::TYPE_EXPENSE))) {
                $account->balance = floatval($account->total_debit) - floatval($account->total_credit);
            } else {
                $account->balance = floatval($account->total_credit) - floatval($account->total_debit);
            }
            
            $total_debits += floatval($account->total_debit);
            $total_credits += floatval($account->total_credit);
        }
        
        return array(
            'accounts' => $accounts,
            'total_debits' => $total_debits,
            'total_credits' => $total_credits,
            'is_balanced' => abs($total_debits - $total_credits) < 0.01,
        );
    }
}

// Initialize the Journal Entry system
OraBooks_Journal_Entry::get_instance();
