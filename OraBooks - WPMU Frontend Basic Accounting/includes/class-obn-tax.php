<?php
/**
 * Frontend-Accounting Tax handlers (modeled after currency handlers)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class OBN_Tax {

    public function __construct() {
        add_action('wp_ajax_obn_insert_tax', array( $this, 'insert_tax' ) );
        add_action('wp_ajax_nopriv_obn_insert_tax', array( $this, 'insert_tax' ) );

        add_action('wp_ajax_obn_update_tax', array( $this, 'update_tax' ) );
        add_action('wp_ajax_nopriv_obn_update_tax', array( $this, 'update_tax' ) );

        add_action('wp_ajax_obn_delete_tax', array( $this, 'delete_tax' ) );
        add_action('wp_ajax_nopriv_obn_delete_tax', array( $this, 'delete_tax' ) );

        add_action('wp_ajax_obn_toggle_tax_status', array( $this, 'toggle_status' ) );
        add_action('wp_ajax_nopriv_obn_toggle_tax_status', array( $this, 'toggle_status' ) );
    }

    public static function insert_tax() {
        check_ajax_referer('obn_auth_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_tax';

        $names = isset( $_POST['tax_name'] ) ? (array) $_POST['tax_name'] : array();
        $rates = isset( $_POST['tax'] )      ? (array) $_POST['tax']      : array();

        if ( empty( $names ) ) {
            wp_send_json_error('At least one tax row is required.');
        }

        $inserted_count = 0;
        $skipped        = array();
        $errors         = array();

        foreach ( $names as $index => $raw_name ) {
            $name = sanitize_text_field( $raw_name );
            $rate = isset( $rates[ $index ] ) ? floatval( $rates[ $index ] ) : null;

            if ( empty( $name ) || $rate === null ) {
                $errors[] = 'Row ' . ( $index + 1 ) . ': Name and rate are required.';
                continue;
            }

            if ( ! is_numeric( $rate ) ) {
                $errors[] = 'Row ' . ( $index + 1 ) . ': Tax rate must be a number.';
                continue;
            }

            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE tax_name = %s", $name ) );
            if ( $exists > 0 ) {
                $skipped[] = $name;
                continue;
            }

            $result = $wpdb->insert( $table, array(
                'store_id' => 1,
                'tax_name' => $name,
                'tax'      => $rate,
                'status'   => 1
            ) );

            if ( $result ) {
                $inserted_count++;
            } else {
                $errors[] = 'Row ' . ( $index + 1 ) . ': Database insert failed for "' . $name . '".';
            }
        }

        if ( $inserted_count === 0 && empty( $errors ) && ! empty( $skipped ) ) {
            wp_send_json_error( 'All entries already exist: ' . implode( ', ', $skipped ) );
        }

        $msg = $inserted_count . ' tax' . ( $inserted_count !== 1 ? 'es' : '' ) . ' added successfully.';
        if ( ! empty( $skipped ) ) {
            $msg .= ' Skipped (duplicates): ' . implode( ', ', $skipped ) . '.';
        }
        if ( ! empty( $errors ) ) {
            $msg .= ' Errors: ' . implode( ' | ', $errors );
        }

        if ( $inserted_count > 0 ) {
            wp_send_json_success( array( 'message' => $msg ) );
        } else {
            wp_send_json_error( $msg );
        }
    }

    public static function update_tax() {
        check_ajax_referer('obn_auth_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_tax';

        $id = intval( $_POST['id'] ?? 0 );
        $name = sanitize_text_field( $_POST['tax_name'] ?? '' );
        $rate = isset( $_POST['rate'] ) ? floatval( $_POST['rate'] ) : ( isset( $_POST['tax'] ) ? floatval( $_POST['tax'] ) : null );
        $type = sanitize_text_field( $_POST['type'] ?? 'Percentage' );

        if ( ! $id || empty( $name ) || $rate === null ) {
            wp_send_json_error('All fields are required.');
        }

        if ( ! is_numeric( $rate ) ) {
            wp_send_json_error('Tax rate must be a number.');
        }

        $updated = $wpdb->update( $table, array(
            'tax_name' => $name,
            'tax' => $rate,
        ), array( 'id' => $id ) );

        if ( $updated !== false ) {
            wp_send_json_success( array( 'message' => 'Tax updated successfully.' ) );
        } else {
            wp_send_json_error('Failed to update tax.');
        }
    }

    public static function delete_tax() {
        check_ajax_referer('obn_auth_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_tax';
        $id = intval( $_POST['id'] ?? 0 );

        if ( $id <= 0 ) {
            wp_send_json_error('Invalid tax ID.');
        }

        $deleted = $wpdb->delete( $table, array( 'id' => $id ) );
        if ( $deleted ) {
            wp_send_json_success( array( 'message' => 'Tax deleted successfully.' ) );
        } else {
            wp_send_json_error('Failed to delete tax.');
        }
    }

    public static function toggle_status() {
        check_ajax_referer('obn_auth_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = sanitize_text_field( $_POST['table'] ?? '' );
        $id = intval( $_POST['id'] ?? 0 );
        $status = intval( $_POST['status'] ?? 0 );

        // ensure table is expected
        $expected_table = $wpdb->prefix . 'orabooks_db_tax';
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
new OBN_Tax();
