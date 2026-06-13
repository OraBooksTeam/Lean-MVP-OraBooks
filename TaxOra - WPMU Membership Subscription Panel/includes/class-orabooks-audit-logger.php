<?php
/**
 * OraBooks Audit Logger
 * Implements audit-ready logging as per ORABOOKS_ULTIMATE_BUILD_GUIDE_A_TO_Z.md
 * 
 * Core Principles:
 * - All actions must be logged with: User ID, Timestamp, Mode, Action type, Before/after state, Approval chain
 * - Logs are immutable (append-only)
 * - Logs are audit-grade evidence
 * - 7+ year retention policy for financial data
 * 
 * @package OraBooks_Membership
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Audit_Logger {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Log retention period in years (from build guide)
     */
    const RETENTION_YEARS = 7;
    
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
        // Schedule cleanup task for old logs
        add_action('init', array($this, 'schedule_cleanup'));
        add_action('orabooks_audit_cleanup', array($this, 'cleanup_old_logs'));
        
        // Log mode switches
        add_action('orabooks_mode_switched', array($this, 'log_mode_switch'), 10, 3);
        
        // Log permission denials
        add_action('orabooks_permission_denied', array($this, 'log_permission_denial'), 10, 4);
    }
    
    /**
     * Ensure audit table exists
     */
    private function ensure_audit_table_exists() {
        global $wpdb;
        
        // Ensure table names are properly set
        if (function_exists('orabooks_handle_multisite_tables')) {
            orabooks_handle_multisite_tables();
        }
        
        $table_name = $wpdb->orabooks_audit_log;
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        
        if (!$table_exists) {
            $this->create_audit_tables();
        }
    }
    
    /**
     * Create audit log database table
     */
    public function create_audit_tables() {
        global $wpdb;
        
        // Ensure table names are properly set
        if (function_exists('orabooks_handle_multisite_tables')) {
            orabooks_handle_multisite_tables();
        }
        
        $table_name = $wpdb->orabooks_audit_log;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action_type varchar(100) NOT NULL,
            action_description text,
            mode varchar(20) DEFAULT NULL,
            entity_type varchar(50) DEFAULT NULL,
            entity_id bigint(20) DEFAULT NULL,
            before_state longtext DEFAULT NULL,
            after_state longtext DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            approval_chain longtext DEFAULT NULL,
            approval_status varchar(20) DEFAULT 'none',
            approved_by bigint(20) DEFAULT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY action_type (action_type),
            KEY mode (mode),
            KEY entity (entity_type, entity_id),
            KEY timestamp (timestamp),
            KEY retention_cleanup (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Log an action
     * 
     * @param array $data Log data
     * @return int|false Log entry ID or false on failure
     */
    public function log_action($data) {
        global $wpdb;
        
        // Ensure table exists before logging
        $this->ensure_audit_table_exists();
        
        // Ensure table names are properly set
        if (function_exists('orabooks_handle_multisite_tables')) {
            orabooks_handle_multisite_tables();
        }
        
        $table_name = $wpdb->orabooks_audit_log;
        
        $defaults = array(
            'user_id' => get_current_user_id(),
            'action_type' => 'unknown',
            'action_description' => '',
            'mode' => null,
            'entity_type' => null,
            'entity_id' => null,
            'before_state' => null,
            'after_state' => null,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'approval_chain' => null,
            'approval_status' => 'none',
            'approved_by' => null,
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Get current mode if not provided
        if ($data['mode'] === null && class_exists('OraBooks_Mode_Manager')) {
            $data['mode'] = OraBooks_Mode_Manager::get_current_mode($data['user_id']);
        }
        
        // Serialize state data if it's an array
        if (is_array($data['before_state'])) {
            $data['before_state'] = json_encode($data['before_state']);
        }
        if (is_array($data['after_state'])) {
            $data['after_state'] = json_encode($data['after_state']);
        }
        if (is_array($data['approval_chain'])) {
            $data['approval_chain'] = json_encode($data['approval_chain']);
        }
        
        // Insert log entry
        $result = $wpdb->insert(
            $table_name,
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
        );
        
        if ($result !== false) {
            $log_id = $wpdb->insert_id;
            
            // Also log to error log for immediate visibility
            error_log(sprintf(
                '[OraBooks Audit] User: %d, Action: %s, Mode: %s, Entity: %s/%d',
                $data['user_id'],
                $data['action_type'],
                $data['mode'],
                $data['entity_type'],
                $data['entity_id']
            ));
            
            return $log_id;
        }
        
        return false;
    }
    
    /**
     * Log mode switch
     * 
     * @param int $user_id User ID
     * @param string $old_mode Previous mode
     * @param string $new_mode New mode
     */
    public function log_mode_switch($user_id, $old_mode, $new_mode) {
        $this->log_action(array(
            'user_id' => $user_id,
            'action_type' => 'mode_switch',
            'action_description' => sprintf('User switched from %s mode to %s mode', $old_mode, $new_mode),
            'mode' => $new_mode,
            'before_state' => array('mode' => $old_mode),
            'after_state' => array('mode' => $new_mode),
        ));
    }
    
    /**
     * Log permission denial
     * 
     * @param int $user_id User ID
     * @param string $role Role
     * @param string $mode Mode
     * @param string $action Action
     */
    public function log_permission_denial($user_id, $role, $mode, $action) {
        $this->log_action(array(
            'user_id' => $user_id,
            'action_type' => 'permission_denied',
            'action_description' => sprintf('Permission denied for action: %s', $action),
            'mode' => $mode,
            'before_state' => array(
                'role' => $role,
                'mode' => $mode,
                'action' => $action
            ),
        ));
    }
    
    /**
     * Log journal entry posting
     * 
     * @param int $user_id User ID
     * @param int $je_id Journal Entry ID
     * @param array $je_data Journal Entry data
     */
    public function log_journal_entry($user_id, $je_id, $je_data) {
        $this->log_action(array(
            'user_id' => $user_id,
            'action_type' => 'journal_entry_post',
            'action_description' => sprintf('Journal entry posted: JE-%d', $je_id),
            'mode' => $je_data['mode'] ?? null,
            'entity_type' => 'journal_entry',
            'entity_id' => $je_id,
            'after_state' => $je_data,
            'approval_status' => $je_data['approval_status'] ?? 'approved',
            'approved_by' => $je_data['approved_by'] ?? null,
        ));
    }
    
    /**
     * Log user management action
     * 
     * @param int $actor_id Actor user ID
     * @param int $target_id Target user ID
     * @param string $action Action (create, update, delete)
     * @param array $before_state Before state
     * @param array $after_state After state
     */
    public function log_user_management($actor_id, $target_id, $action, $before_state = null, $after_state = null) {
        $this->log_action(array(
            'user_id' => $actor_id,
            'action_type' => 'user_' . $action,
            'action_description' => sprintf('User %s: User ID %d', $action, $target_id),
            'entity_type' => 'user',
            'entity_id' => $target_id,
            'before_state' => $before_state,
            'after_state' => $after_state,
        ));
    }
    
    /**
     * Log Chart of Accounts change
     * 
     * @param int $user_id User ID
     * @param int $account_id Account ID
     * @param string $action Action (create, update, lock)
     * @param array $before_state Before state
     * @param array $after_state After state
     */
    public function log_coa_change($user_id, $account_id, $action, $before_state = null, $after_state = null) {
        $this->log_action(array(
            'user_id' => $user_id,
            'action_type' => 'coa_' . $action,
            'action_description' => sprintf('Chart of Accounts %s: Account ID %d', $action, $account_id),
            'entity_type' => 'chart_of_accounts',
            'entity_id' => $account_id,
            'before_state' => $before_state,
            'after_state' => $after_state,
        ));
    }
    
    /**
     * Get audit log entries
     * 
     * @param array $args Query arguments
     * @return array Log entries
     */
    public function get_logs($args = array()) {
        global $wpdb;
        
        // Ensure table names are properly set
        if (function_exists('orabooks_handle_multisite_tables')) {
            orabooks_handle_multisite_tables();
        }
        
        $table_name = $wpdb->orabooks_audit_log;
        
        $defaults = array(
            'user_id' => null,
            'action_type' => null,
            'mode' => null,
            'entity_type' => null,
            'entity_id' => null,
            'limit' => 100,
            'offset' => 0,
            'order' => 'DESC',
            'orderby' => 'timestamp',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $values = array();
        
        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $values[] = $args['user_id'];
        }
        
        if ($args['action_type']) {
            $where[] = 'action_type = %s';
            $values[] = $args['action_type'];
        }
        
        if ($args['mode']) {
            $where[] = 'mode = %s';
            $values[] = $args['mode'];
        }
        
        if ($args['entity_type']) {
            $where[] = 'entity_type = %s';
            $values[] = $args['entity_type'];
        }
        
        if ($args['entity_id']) {
            $where[] = 'entity_id = %d';
            $values[] = $args['entity_id'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Whitelist orderby and order to prevent SQL injection
        $allowed_orderby = array('id', 'user_id', 'action_type', 'action_description', 'mode', 'entity_type', 'entity_id', 'timestamp');
        $allowed_order = array('ASC', 'DESC');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'timestamp';
        $order = in_array(strtoupper($args['order']), $allowed_order) ? strtoupper($args['order']) : 'DESC';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d",
            array_merge($values, array($args['limit'], $args['offset']))
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Schedule cleanup task
     */
    public function schedule_cleanup() {
        if (!wp_next_scheduled('orabooks_audit_cleanup')) {
            wp_schedule_event(time(), 'daily', 'orabooks_audit_cleanup');
        }
    }
    
    /**
     * Cleanup old logs (retention policy)
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        // Ensure table names are properly set
        if (function_exists('orabooks_handle_multisite_tables')) {
            orabooks_handle_multisite_tables();
        }
        
        $table_name = $wpdb->orabooks_audit_log;
        
        // Calculate cutoff date (7 years ago)
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-' . self::RETENTION_YEARS . ' years'));
        
        // Delete old logs
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE timestamp < %s",
            $cutoff_date
        ));
        
        error_log(sprintf('[OraBooks Audit Cleanup] Deleted %d log entries older than %s', $deleted, $cutoff_date));
    }
    
    /**
     * Get client IP address
     * 
     * @return string IP address
     */
    private function get_client_ip() {
        $ip = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
    
    /**
     * Export audit log for compliance
     * 
     * @param array $args Filter arguments
     * @return string CSV export
     */
    public function export_logs($args = array()) {
        $logs = $this->get_logs($args);
        
        $csv = "ID,User ID,Action Type,Description,Mode,Entity Type,Entity ID,IP Address,User Agent,Timestamp\n";
        
        foreach ($logs as $log) {
            $csv .= sprintf(
                "%d,%d,%s,%s,%s,%s,%d,%s,%s,%s\n",
                $log->id,
                $log->user_id,
                $log->action_type,
                str_replace(',', ' ', $log->action_description),
                $log->mode,
                $log->entity_type,
                $log->entity_id,
                $log->ip_address,
                str_replace(',', ' ', $log->user_agent),
                $log->timestamp
            );
        }
        
        return $csv;
    }
}

// Initialize the audit logger
OraBooks_Audit_Logger::get_instance();
