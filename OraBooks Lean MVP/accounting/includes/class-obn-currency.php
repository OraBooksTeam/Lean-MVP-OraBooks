<?php
/**
 * Frontend-Accounting Currency handlers (copied functionality from Orabooks Accounts)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class OBN_Currency {

    public function __construct() {
        add_action('wp_ajax_obn_insert_currency', array( $this, 'insert_currency' ) );
        add_action('wp_ajax_nopriv_obn_insert_currency', array( $this, 'insert_currency' ) );
        
        add_action('wp_ajax_obn_update_currency', array( $this, 'update_currency' ) );
        add_action('wp_ajax_nopriv_obn_update_currency', array( $this, 'update_currency' ) );
        
        add_action('wp_ajax_obn_delete_currency', array( $this, 'delete_currency' ) );
        add_action('wp_ajax_nopriv_obn_delete_currency', array( $this, 'delete_currency' ) );
        
        add_action('wp_ajax_obn_toggle_status', array( $this, 'toggle_status' ) );
        add_action('wp_ajax_nopriv_obn_toggle_status', array( $this, 'toggle_status' ) );
    }

    public static function insert_currency() {
        check_ajax_referer('obn_auth_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }
        if ( ! current_user_can('manage_options') && ! ( function_exists('is_super_admin') && is_super_admin() ) ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_currency';

        $names = isset( $_POST['currency_name'] ) ? (array) $_POST['currency_name'] : [];
        $codes = isset( $_POST['currency_code'] ) ? (array) $_POST['currency_code'] : [];
        $symbols = isset( $_POST['symbol'] ) ? (array) $_POST['symbol'] : [];

        $success_count = 0;
        $errors = [];

        foreach ( $names as $index => $name_raw ) {
            $name = sanitize_text_field( $name_raw );
            if ( empty( $name ) ) continue;

            $code = strtoupper( sanitize_text_field( $codes[$index] ?? '' ) );
            $symbol = sanitize_text_field( $symbols[$index] ?? '' );

            if ( empty( $code ) || empty( $symbol ) ) {
                $errors[] = "Missing code or symbol for '{$name}'.";
                continue;
            }

            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE currency_code = %s OR currency_name = %s", $code, $name ) );
            if ( $exists > 0 ) {
                $errors[] = "'{$name}' ({$code}) already exists.";
                continue;
            }

            $inserted = $wpdb->insert( $table, array(
                'currency_name' => $name,
                'currency_code' => $code,
                'symbol' => $symbol,
                'status' => 1
            ) );

            if ( $inserted ) {
                $success_count++;
            } else {
                $errors[] = "Failed to add '{$name}'.";
            }
        }

        if ( $success_count > 0 && empty($errors) ) {
            wp_send_json_success( array( 'message' => "Successfully added {$success_count} currency(ies)." ) );
        } elseif ( $success_count > 0 && !empty($errors) ) {
            wp_send_json_success( array( 'message' => "Added {$success_count} currency(ies), but: " . implode(' ', $errors) ) );
        } else {
            $msg = !empty($errors) ? implode(' ', $errors) : 'No valid currency provided.';
            wp_send_json_error( $msg );
        }
    }

    public static function update_currency() {
        check_ajax_referer('obn_auth_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }
        if ( ! current_user_can('manage_options') && ! ( function_exists('is_super_admin') && is_super_admin() ) ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_currency';

        $id = intval( $_POST['id'] ?? 0 );
        $name = sanitize_text_field( $_POST['currency_name'] ?? '' );
        $code = sanitize_text_field( $_POST['currency_code'] ?? '' );
        $symbol = sanitize_text_field( $_POST['symbol'] ?? '' );

        if ( ! $id || ! $name || ! $code || ! $symbol ) {
            wp_send_json_error('All fields are required.');
        }

        $updated = $wpdb->update( $table, array(
            'currency_name' => $name,
            'currency_code' => $code,
            'symbol' => $symbol
        ), array( 'id' => $id ) );

        if ( $updated !== false ) {
            wp_send_json_success( array( 'message' => 'Currency updated successfully.' ) );
        } else {
            wp_send_json_error('Failed to update currency.');
        }
    }

    public static function delete_currency() {
        check_ajax_referer('obn_auth_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }
        if ( ! current_user_can('manage_options') && ! ( function_exists('is_super_admin') && is_super_admin() ) ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_currency';
        $id = intval( $_POST['id'] ?? 0 );

        if ( $id <= 0 ) {
            wp_send_json_error('Invalid currency ID.');
        }

        $deleted = $wpdb->delete( $table, array( 'id' => $id ) );
        if ( $deleted ) {
            wp_send_json_success( array( 'message' => 'Currency deleted successfully.' ) );
        } else {
            wp_send_json_error('Failed to delete currency.');
        }
    }

    public static function toggle_status() {
        check_ajax_referer('obn_auth_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }
        if ( ! current_user_can('manage_options') && ! ( function_exists('is_super_admin') && is_super_admin() ) ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = sanitize_text_field( $_POST['table'] ?? '' );
        $id = intval( $_POST['id'] ?? 0 );
        $status = intval( $_POST['status'] ?? 0 );

        // ensure table is expected
        $expected_table = $wpdb->prefix . 'orabooks_db_currency';
        if ( $table !== $expected_table ) {
            $table = $expected_table;
        }

        if ( $id <= 0 ) {
            wp_send_json_error('Invalid ID.');
        }

        $new_status = $status == 1 ? 0 : 1;
        $updated = $wpdb->update( $table, array( 'status' => $new_status ), array( 'id' => $id ) );

        if ( $updated !== false ) {
            wp_send_json_success( array( 'new_status' => $new_status ) );
        } else {
            wp_send_json_error('Failed to toggle status.');
        }
    }

}

// instantiate to register actions
new OBN_Currency();
