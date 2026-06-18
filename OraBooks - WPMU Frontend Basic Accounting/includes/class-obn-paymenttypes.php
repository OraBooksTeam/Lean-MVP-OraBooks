<?php
/**
 * Frontend-Accounting Payment Types handlers (copied / adapted from Orabooks Accounts)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class OBN_PaymentTypes {

    public function __construct() {
        add_action('wp_ajax_obn_insert_payment_type', array( $this, 'insert_payment_type' ) );
        add_action('wp_ajax_nopriv_obn_insert_payment_type', array( $this, 'insert_payment_type' ) );

        add_action('wp_ajax_obn_update_payment_type', array( $this, 'update_payment_type' ) );
        add_action('wp_ajax_nopriv_obn_update_payment_type', array( $this, 'update_payment_type' ) );

        add_action('wp_ajax_obn_delete_payment_type', array( $this, 'delete_payment_type' ) );
        add_action('wp_ajax_nopriv_obn_delete_payment_type', array( $this, 'delete_payment_type' ) );
        
        add_action('wp_ajax_obn_toggle_payment_status', array( $this, 'toggle_status' ) );
        add_action('wp_ajax_nopriv_obn_toggle_payment_status', array( $this, 'toggle_status' ) );

        // Frontend (nonce: frontend_ajax_nonce) for Add Sale page quick-add
        add_action('wp_ajax_frontend_insert_payment_type', array( $this, 'frontend_insert_payment_type' ) );
        add_action('wp_ajax_nopriv_frontend_insert_payment_type', array( $this, 'frontend_insert_payment_type' ) );
    }

    public static function insert_payment_type() {
        check_ajax_referer('obn_auth_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_paymenttypes';

        $payment_type_data = $_POST['payment_type'] ?? '';

        // Debug: Log what we received
        error_log('Payment type data received: ' . $payment_type_data);

        if ( empty( $payment_type_data ) ) {
            wp_send_json_error('Payment type is required.');
        }

        // Parse the JSON array from multi-value input
        $payment_types = json_decode( wp_unslash( $payment_type_data ), true );
        
        // Debug: Log parsed result
        error_log('Parsed payment types: ' . print_r($payment_types, true));
        
        if ( ! is_array( $payment_types ) || empty( $payment_types ) ) {
            // Fallback to single value handling for backward compatibility
            $payment_type = sanitize_text_field( $payment_type_data );
            $payment_types = array( $payment_type );
            error_log('Fallback to single payment type: ' . $payment_type);
        }

        $added_count = 0;
        $duplicate_count = 0;
        $error_count = 0;
        $errors = array();

        // Debug: Log the foreach loop start
        error_log('Starting foreach loop for ' . count($payment_types) . ' payment types');

        foreach ( $payment_types as $index => $payment_type ) {
            $original_payment_type = $payment_type;
            $payment_type = sanitize_text_field( trim( $payment_type ) );
            
            // Debug: Log each iteration
            error_log("Processing payment type [$index]: '$original_payment_type' -> '$payment_type'");
            
            if ( empty( $payment_type ) ) {
                error_log("Skipping empty payment type at index $index");
                continue; // Skip empty values
            }

            // Check for duplicates
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE payment_type = %s", $payment_type ) );
            error_log("Duplicate check for '$payment_type': " . ($exists > 0 ? 'EXISTS' : 'NEW'));
            
            if ( $exists > 0 ) {
                $duplicate_count++;
                error_log("Duplicate found: '$payment_type', skipping");
                continue;
            }

            // Insert the payment type
            error_log("Attempting to insert: '$payment_type'");
            $inserted = $wpdb->insert( $table, array(
                'payment_type' => $payment_type,
                'status' => 1
            ) );

            error_log("Insert result for '$payment_type': " . ($inserted ? 'SUCCESS' : 'FAILED'));

            if ( $inserted ) {
                $added_count++;
                error_log("Successfully inserted payment type: '$payment_type'");
            } else {
                $error_count++;
                $error_msg = "Failed to insert: " . $payment_type;
                $errors[] = $error_msg;
                error_log($error_msg);
            }
        }

        // Debug: Log final results
        error_log("Final results - Added: $added_count, Duplicates: $duplicate_count, Errors: $error_count");

        // Prepare response message
        if ( $added_count > 0 ) {
            $message_parts = array();
            if ( $added_count === 1 ) {
                $message_parts[] = "1 payment type added successfully.";
            } else {
                $message_parts[] = "{$added_count} payment types added successfully.";
            }
            
            if ( $duplicate_count > 0 ) {
                $message_parts[] = "{$duplicate_count} duplicate(s) skipped.";
            }
            
            if ( $error_count > 0 ) {
                $message_parts[] = "{$error_count} error(s) occurred.";
            }
            
            wp_send_json_success( array( 
                'message' => implode( ' ', $message_parts ),
                'added_count' => $added_count,
                'duplicate_count' => $duplicate_count,
                'error_count' => $error_count,
                'errors' => $errors
            ) );
        } else {
            if ( $duplicate_count > 0 ) {
                wp_send_json_error( 'All payment types already exist.' );
            } else {
                wp_send_json_error( 'No payment types were added.' );
            }
        }
    }

    public static function update_payment_type() {
        check_ajax_referer('obn_auth_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_paymenttypes';

        $id = intval( $_POST['id'] ?? 0 );
        $payment_type = sanitize_text_field( $_POST['payment_type'] ?? '' );

        if ( ! $id || empty( $payment_type ) ) {
            wp_send_json_error('All fields are required.');
        }

        $updated = $wpdb->update( $table, array( 'payment_type' => $payment_type ), array( 'id' => $id ) );

        if ( $updated !== false ) {
            wp_send_json_success( array( 'message' => 'Payment type updated successfully.' ) );
        } else {
            wp_send_json_error('Failed to update payment type.');
        }
    }

    public static function delete_payment_type() {
        check_ajax_referer('obn_auth_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_paymenttypes';
        $id = intval( $_POST['id'] ?? 0 );

        if ( $id <= 0 ) {
            wp_send_json_error('Invalid payment type ID.');
        }

        $deleted = $wpdb->delete( $table, array( 'id' => $id ) );
        if ( $deleted ) {
            wp_send_json_success( array( 'message' => 'Payment type deleted successfully.' ) );
        } else {
            wp_send_json_error('Failed to delete payment type.');
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
        $expected_table = $wpdb->prefix . 'orabooks_db_paymenttypes';
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

    /**
     * Frontend AJAX: Insert a single payment type (uses frontend_ajax_nonce).
     * Used by the Add Sale page quick-add modal.
     */
    public static function frontend_insert_payment_type() {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_paymenttypes';

        $payment_type = isset( $_POST['payment_type'] ) ? sanitize_text_field( trim( $_POST['payment_type'] ) ) : '';

        if ( empty( $payment_type ) ) {
            wp_send_json_error( 'Payment type name is required.' );
        }

        // Check for duplicate
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE payment_type = %s", $payment_type ) );
        if ( $exists > 0 ) {
            wp_send_json_error( 'This payment type already exists.' );
        }

        $inserted = $wpdb->insert( $table, array(
            'payment_type' => $payment_type,
            'status'       => 1
        ) );

        if ( $inserted ) {
            $new_id = $wpdb->insert_id;
            wp_send_json_success( array(
                'id'           => $new_id,
                'payment_type' => $payment_type,
                'message'      => 'Payment type added successfully.'
            ) );
        } else {
            wp_send_json_error( 'Failed to add payment type.' );
        }
    }

}

// instantiate to register actions
new OBN_PaymentTypes();
