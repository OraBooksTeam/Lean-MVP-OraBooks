<?php
/**
 * Frontend-Accounting Advances handlers (Customer Advance)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class OBN_Advances {

    public function __construct() {
        add_action('wp_ajax_obn_insert_advance', array( $this, 'insert_advance' ) );
        add_action('wp_ajax_nopriv_obn_insert_advance', array( $this, 'insert_advance' ) );

        add_action('wp_ajax_obn_update_advance', array( $this, 'update_advance' ) );
        add_action('wp_ajax_nopriv_obn_update_advance', array( $this, 'update_advance' ) );

        add_action('wp_ajax_obn_delete_advance', array( $this, 'delete_advance' ) );
        add_action('wp_ajax_nopriv_obn_delete_advance', array( $this, 'delete_advance' ) );

        add_action('wp_ajax_obn_toggle_advance_status', array( $this, 'toggle_status' ) );
        add_action('wp_ajax_nopriv_obn_toggle_advance_status', array( $this, 'toggle_status' ) );
        
        add_action('wp_ajax_obn_get_advance', array( $this, 'get_advance' ) );
        add_action('wp_ajax_nopriv_obn_get_advance', array( $this, 'get_advance' ) );
    }

    public static function insert_advance() {
        check_ajax_referer('obn_advance_action_nonce', 'security');
        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_custadvance';

        $payment_date = sanitize_text_field( $_POST['payment_date'] ?? '' );
        $customer_id = intval( $_POST['customer_id'] ?? 0 );
        $amount = floatval( $_POST['amount'] ?? 0 );
        $payment_type = sanitize_text_field( $_POST['payment_type'] ?? '' );
        $note = sanitize_textarea_field( $_POST['note'] ?? '' );

        if ( empty( $payment_date ) || ! $customer_id || $amount <= 0 || empty( $payment_type ) ) {
            wp_send_json_error('All required fields must be provided.');
        }

        $inserted = $wpdb->insert( $table, array(
            'payment_date' => $payment_date,
            'customer_id' => $customer_id,
            'amount' => $amount,
            'payment_type' => $payment_type,
            'note' => $note,
            'created_by' => get_current_user_id(),
            'created_date' => date('Y-m-d'),
            'created_time' => date('H:i:s'),
            'system_ip' => $_SERVER['REMOTE_ADDR'],
            'system_name' => gethostname(),
            'status' => 1,
        ), array('%s','%d','%f','%s','%s','%d','%d','%s','%s','%s','%s') );

        if ( $inserted ) wp_send_json_success( array( 'message' => 'Advance added successfully.' ) );
        wp_send_json_error('Failed to add advance.');
    }

    public static function update_advance() {
        check_ajax_referer('obn_advance_action_nonce', 'security');
        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_custadvance';

        $id = intval( $_POST['id'] ?? 0 );
        $payment_date = sanitize_text_field( $_POST['payment_date'] ?? '' );
        $customer_id = intval( $_POST['customer_id'] ?? 0 );
        $amount = floatval( $_POST['amount'] ?? 0 );
        $payment_type = sanitize_text_field( $_POST['payment_type'] ?? '' );
        $note = sanitize_textarea_field( $_POST['note'] ?? '' );

        if ( $id <= 0 || empty( $payment_date ) || ! $customer_id || $amount <= 0 || empty( $payment_type ) ) {
            wp_send_json_error('All required fields must be provided.');
        }

        $updated = $wpdb->update( $table, array(
            'payment_date' => $payment_date,
            'customer_id' => $customer_id,
            'amount' => $amount,
            'payment_type' => $payment_type,
            'note' => $note,
        ), array( 'id' => $id ), array('%s','%d','%f','%s','%s'), array('%d') );

        if ( $updated !== false ) wp_send_json_success( array( 'message' => 'Advance updated successfully.' ) );
        wp_send_json_error('Failed to update advance.');
    }

    public static function delete_advance() {
        check_ajax_referer('obn_advance_action_nonce', 'security');
        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_custadvance';
        $id = intval( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) wp_send_json_error('Invalid ID');

        $deleted = $wpdb->delete( $table, array( 'id' => $id ) );
        if ( $deleted ) wp_send_json_success( array( 'message' => 'Advance deleted successfully.' ) );
        wp_send_json_error('Failed to delete advance.');
    }

    public static function toggle_status() {
        check_ajax_referer('obn_advance_action_nonce', 'security');
        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_custadvance';
        $id = intval( $_POST['id'] ?? 0 );
        $status = intval( $_POST['status'] ?? 0 );
        if ( $id <= 0 ) wp_send_json_error('Invalid ID');

        $updated = $wpdb->update( $table, array( 'status' => $status ), array( 'id' => $id ) );
        if ( $updated !== false ) wp_send_json_success( array( 'new_status' => $status ) );
        wp_send_json_error('Failed to toggle status.');
    }

    public static function get_advance() {
        check_ajax_referer('obn_advance_action_nonce', 'security');
        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_custadvance';
        $id = intval( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) wp_send_json_error('Invalid ID');

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
        if ( $row ) wp_send_json_success( $row );
        wp_send_json_error('Not found');
    }

}

new OBN_Advances();
