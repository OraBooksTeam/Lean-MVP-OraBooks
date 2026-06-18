<?php
/**
 * Frontend-Accounting Journal Entries handlers
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class OBN_Journal_Entries {

    public function __construct() {
        add_action('wp_ajax_obn_insert_journal_entry', array( $this, 'insert_journal_entry' ) );
        add_action('wp_ajax_nopriv_obn_insert_journal_entry', array( $this, 'insert_journal_entry' ) );

        add_action('wp_ajax_obn_delete_journal_entry', array( $this, 'delete_journal_entry' ) );
        add_action('wp_ajax_nopriv_obn_delete_journal_entry', array( $this, 'delete_journal_entry' ) );

        add_action('wp_ajax_obn_get_journal_entry', array( $this, 'get_journal_entry' ) );
        add_action('wp_ajax_nopriv_obn_get_journal_entry', array( $this, 'get_journal_entry' ) );
    }

    public static function insert_journal_entry() {
        check_ajax_referer('obn_je_action_nonce', 'security');
        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) wp_send_json_error('Access denied.');

        $entry_date = sanitize_text_field( $_POST['entry_date'] ?? '' );
        $reference_no = sanitize_text_field( $_POST['reference_no'] ?? '' );
        $description = wp_kses_post( $_POST['description'] ?? '' );
        
        $accounts = $_POST['je_account'] ?? array();
        $descs = $_POST['je_desc'] ?? array();
        $debits = $_POST['je_debit'] ?? array();
        $credits = $_POST['je_credit'] ?? array();

        $lines = array();
        for($i=0; $i<count($accounts); $i++) {
            $acc_id = intval($accounts[$i]);
            if($acc_id > 0) {
                $lines[] = array(
                    'account_id' => $acc_id,
                    'description' => sanitize_text_field($descs[$i] ?? ''),
                    'debit' => floatval($debits[$i] ?? 0),
                    'credit' => floatval($credits[$i] ?? 0)
                );
            }
        }

        $result = self::add_entry(array(
            'entry_date'   => $entry_date,
            'reference_no' => $reference_no,
            'description'  => $description,
            'lines'        => $lines
        ));

        if ( is_wp_error($result) ) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success( array( 'message' => 'Journal Entry added successfully.' ) );
    }

    /**
     * Reusable method to add a balanced journal entry
     */
    public static function add_entry($data) {
        global $wpdb;
        $je_table = $wpdb->prefix . 'orabooks_ac_journal_entry';
        $je_line_table = $wpdb->prefix . 'orabooks_ac_journal_line';

        $entry_date   = $data['entry_date'] ?? current_time('Y-m-d');
        $reference_no = $data['reference_no'] ?? '';
        $description  = $data['description'] ?? '';
        $lines        = $data['lines'] ?? array();
        $source_type  = $data['source_type'] ?? '';
        $source_id    = $data['source_id'] ?? null;
        $org_id       = intval($data['organization_id'] ?? obn_current_org_id());

        if ( class_exists('OBN_Fiscal_Period_Posting_Guard') ) {
            $posting_allowed = OBN_Fiscal_Period_Posting_Guard::can_post($org_id, $entry_date);
            if ( is_wp_error($posting_allowed) ) {
                return $posting_allowed;
            }
        }

        if ( empty($lines) ) {
            return new WP_Error('je_error', 'At least one line is required.');
        }

        $total_debit = 0;
        $total_credit = 0;
        foreach($lines as $l) {
            $total_debit += floatval($l['debit'] ?? 0);
            $total_credit += floatval($l['credit'] ?? 0);
        }

        if ( abs($total_debit - $total_credit) > 0.001 ) {
            return new WP_Error('je_error', 'Journal Entry is not balanced (Debits: ' . $total_debit . ', Credits: ' . $total_credit . ').');
        }

        $inserted = $wpdb->insert( $je_table, array(
            'store_id'     => obn_current_org_id(),
            'organization_id' => $org_id,
            'entry_date'   => $entry_date,
            'posting_date' => $entry_date,
            'reference_no' => $reference_no,
            'description'  => $description,
            'total_debit'  => $total_debit,
            'total_credit' => $total_credit,
            'source_type'  => $source_type,
            'source_id'    => $source_id,
            'status'       => 'Posted',
            'created_by'   => get_current_user_id()
        ), array('%d','%d','%s','%s','%s','%s','%f','%f','%s','%d','%s','%s') );

        if ( ! $inserted ) {
            return new WP_Error('je_error', 'Failed to insert Journal Entry header.');
        }

        $je_id = $wpdb->insert_id;

        foreach($lines as $index => $l) {
            $wpdb->insert( $je_line_table, array(
                'journal_entry_id' => $je_id,
                'account_id'       => $l['account_id'],
                'debit'            => $l['debit'],
                'credit'           => $l['credit'],
                'debit_amt'        => $l['debit'],
                'credit_amt'       => $l['credit'],
                'description'      => $l['description'] ?? '',
                'line_number'      => $index + 1
            ), array('%d','%d','%f','%f','%f','%f','%s','%d') );
        }

        return $je_id;
    }

    public static function delete_journal_entry() {
        check_ajax_referer('obn_je_action_nonce', 'security');
        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) wp_send_json_error('Access denied.');

        global $wpdb;
        $je_table = $wpdb->prefix . 'orabooks_ac_journal_entry';
        $je_line_table = $wpdb->prefix . 'orabooks_ac_journal_line';
        
        $id = intval( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) wp_send_json_error('Invalid ID');

        $entry = $wpdb->get_row( $wpdb->prepare( "SELECT organization_id, store_id, entry_date FROM $je_table WHERE id = %d", $id ) );
        if ( ! $entry ) {
            wp_send_json_error('Journal Entry not found.');
        }

        if ( class_exists('OBN_Fiscal_Period_Posting_Guard') ) {
            $org_id = intval($entry->organization_id ?: $entry->store_id ?: obn_current_org_id());
            $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify($org_id, $entry->entry_date);
            if ( is_wp_error($modification_allowed) ) {
                wp_send_json_error($modification_allowed->get_error_message());
            }
        }

        $deleted = $wpdb->delete( $je_table, array( 'id' => $id ) );
        if ( $deleted ) {
            $wpdb->delete( $je_line_table, array( 'journal_entry_id' => $id ) );
            wp_send_json_success( array( 'message' => 'Journal Entry deleted successfully.' ) );
        }
        wp_send_json_error('Failed to delete Journal Entry.');
    }

    public function get_journal_entry() {
        check_ajax_referer('obn_je_action_nonce', 'security');
        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) wp_send_json_error('Access denied.');

        global $wpdb;
        $je_table = $wpdb->prefix . 'orabooks_ac_journal_entry';
        $je_line_table = $wpdb->prefix . 'orabooks_ac_journal_line';
        $accounts_table = $wpdb->prefix . 'orabooks_ac_coa_list';

        $id = intval( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) wp_send_json_error('Invalid ID');

        $entry = $wpdb->get_row( $wpdb->prepare( 
            "SELECT je.*, u.display_name as creator_name 
             FROM $je_table je 
             LEFT JOIN {$wpdb->users} u ON je.created_by = u.ID 
             WHERE je.id = %d", 
            $id 
        ) );
        if ( ! $entry ) wp_send_json_error('Journal Entry not found.');

        $lines = $wpdb->get_results( $wpdb->prepare( 
            "SELECT jl.*, a.account_name, a.account_code 
             FROM $je_line_table jl 
             LEFT JOIN $accounts_table a ON jl.account_id = a.id 
             WHERE jl.journal_entry_id = %d 
             ORDER BY jl.line_number ASC", 
            $id 
        ) );

        wp_send_json_success( array(
            'entry' => $entry,
            'lines' => $lines
        ) );
    }

}

new OBN_Journal_Entries();
