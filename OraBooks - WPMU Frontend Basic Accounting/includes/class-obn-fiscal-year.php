<?php
/**
 * Frontend-Accounting Fiscal Year handlers
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class OBN_Fiscal_Year {

    public function __construct() {
        add_action('init', array( $this, 'ensure_table_exists' ), 10);
        
        add_action('wp_ajax_obn_insert_fiscal_year', array( $this, 'insert_fiscal_year' ) );
        add_action('wp_ajax_nopriv_obn_insert_fiscal_year', array( $this, 'insert_fiscal_year' ) );

        add_action('wp_ajax_obn_update_fiscal_year', array( $this, 'update_fiscal_year' ) );
        add_action('wp_ajax_nopriv_obn_update_fiscal_year', array( $this, 'update_fiscal_year' ) );

        add_action('wp_ajax_obn_delete_fiscal_year', array( $this, 'delete_fiscal_year' ) );
        add_action('wp_ajax_nopriv_obn_delete_fiscal_year', array( $this, 'delete_fiscal_year' ) );

        add_action('wp_ajax_obn_toggle_fiscal_year_status', array( $this, 'toggle_status' ) );
        add_action('wp_ajax_nopriv_obn_toggle_fiscal_year_status', array( $this, 'toggle_status' ) );

        add_action('wp_ajax_obn_get_fiscal_year', array( $this, 'get_fiscal_year' ) );
        add_action('wp_ajax_nopriv_obn_get_fiscal_year', array( $this, 'get_fiscal_year' ) );
    }

    public function ensure_table_exists() {
        // Table creation moved to OBN_Activator::activate()
    }

    private function check_overlap($start_date, $end_date, $ignore_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_fiscal_years';
        
        $query = "SELECT id FROM $table WHERE (start_date <= %s AND end_date >= %s) AND id != %d";
        $prepared = $wpdb->prepare($query, $end_date, $start_date, $ignore_id);
        
        return $wpdb->get_var($prepared) !== null;
    }

    public function insert_fiscal_year() {
        check_ajax_referer('obn_fiscal_year_nonce', 'security');
        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_fiscal_years';

        $name = sanitize_text_field( $_POST['fiscal_year_name'] ?? '' );
        $start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
        $end_date = sanitize_text_field( $_POST['end_date'] ?? '' );
        $status = isset($_POST['status']) ? intval($_POST['status']) : 1;
        $description = sanitize_textarea_field( $_POST['description'] ?? '' );

        if ( empty($name) || empty($start_date) || empty($end_date) ) {
            wp_send_json_error('Name, Start Date, and End Date are required.');
        }

        if ( strtotime($start_date) >= strtotime($end_date) ) {
            wp_send_json_error('Start Date must be earlier than End Date.');
        }

        if ( $this->check_overlap($start_date, $end_date) ) {
            wp_send_json_error('Dates overlap with an existing fiscal year.');
        }

        $inserted = $wpdb->insert( $table, [
            'store_id' => get_current_blog_id(),
            'fiscal_year_name' => $name,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'description' => $description,
            'status' => $status,
            'created_by' => get_current_user_id()
        ], ['%d', '%s', '%s', '%s', '%s', '%d', '%d'] );

        if ( $inserted ) {
            wp_send_json_success([ 'message' => 'Fiscal Year added successfully.' ]);
        }
        
        wp_send_json_error('Failed to add Fiscal Year.');
    }

    public function update_fiscal_year() {
        check_ajax_referer('obn_fiscal_year_nonce', 'security');
        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_fiscal_years';
        
        $id = intval( $_POST['id'] ?? 0 );
        $name = sanitize_text_field( $_POST['fiscal_year_name'] ?? '' );
        $start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
        $end_date = sanitize_text_field( $_POST['end_date'] ?? '' );
        $status = isset($_POST['status']) ? intval($_POST['status']) : 1;
        $description = sanitize_textarea_field( $_POST['description'] ?? '' );

        if ( $id <= 0 || empty($name) || empty($start_date) || empty($end_date) ) {
            wp_send_json_error('Required fields are missing.');
        }

        if ( strtotime($start_date) >= strtotime($end_date) ) {
            wp_send_json_error('Start Date must be earlier than End Date.');
        }

        if ( $this->check_overlap($start_date, $end_date, $id) ) {
            wp_send_json_error('Dates overlap with an existing fiscal year.');
        }

        $updated = $wpdb->update( $table, [
            'fiscal_year_name' => $name,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'description' => $description,
            'status' => $status
        ], ['id' => $id], ['%s', '%s', '%s', '%s', '%d'], ['%d'] );

        if ( $updated !== false ) {
            wp_send_json_success([ 'message' => 'Fiscal Year updated successfully.' ]);
        }
        
        wp_send_json_error('Failed to update Fiscal Year.');
    }

    public function delete_fiscal_year() {
        check_ajax_referer('obn_fiscal_year_nonce', 'security');
        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_fiscal_years';
        $id = intval( $_POST['id'] ?? 0 );
        
        if ( $id <= 0 ) wp_send_json_error('Invalid ID');

        $deleted = $wpdb->delete( $table, ['id' => $id], ['%d'] );
        
        if ( $deleted ) {
            wp_send_json_success([ 'message' => 'Fiscal Year deleted successfully.' ]);
        }
        
        wp_send_json_error('Failed to delete Fiscal Year.');
    }

    public function toggle_status() {
        check_ajax_referer('obn_fiscal_year_nonce', 'security');
        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_fiscal_years';
        
        $id = intval( $_POST['id'] ?? 0 );
        $status = intval( $_POST['status'] ?? 0 );
        
        if ( $id <= 0 ) wp_send_json_error('Invalid ID');

        $updated = $wpdb->update( $table, ['status' => $status], ['id' => $id], ['%d'], ['%d'] );
        
        if ( $updated !== false ) {
            wp_send_json_success([ 'new_status' => $status ]);
        }
        
        wp_send_json_error('Failed to toggle status.');
    }

    public function get_fiscal_year() {
        check_ajax_referer('obn_fiscal_year_nonce', 'security');
        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_fiscal_years';
        
        $id = intval( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) wp_send_json_error('Invalid ID');

        $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id) );
        
        if ( $row ) {
            wp_send_json_success($row);
        }
        
        wp_send_json_error('Fiscal Year not found.');
    }
}

new OBN_Fiscal_Year();
