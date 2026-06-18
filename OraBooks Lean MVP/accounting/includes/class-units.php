<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Frontend_Accounting_Units {

    public static function init() {
        add_action( 'wp_ajax_frontend_save_unit', array( __CLASS__, 'handle_save_unit' ) );
        add_action( 'wp_ajax_nopriv_frontend_save_unit', array( __CLASS__, 'handle_save_unit' ) );
        add_action( 'wp_ajax_frontend_delete_unit', array( __CLASS__, 'handle_delete_unit' ) );
        add_action( 'wp_ajax_nopriv_frontend_delete_unit', array( __CLASS__, 'handle_delete_unit' ) );
        add_action( 'wp_ajax_frontend_update_unit_status', array( __CLASS__, 'handle_update_status' ) );
        add_action( 'wp_ajax_nopriv_frontend_update_unit_status', array( __CLASS__, 'handle_update_status' ) );
    }

    public static function handle_save_unit() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_units';

        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        $unit_name = sanitize_text_field( $_POST['unit_name'] );
        $description = sanitize_textarea_field( $_POST['description'] );

        if ( empty( $unit_name ) ) {
            wp_send_json_error( array( 'message' => 'Warning: Unit name is required.' ) );
        }

        // Duplicate Check
        $where = $id > 0 ? $wpdb->prepare( " AND id != %d", $id ) : "";
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE unit_name = %s $where", $unit_name ) );
        if ( $exists ) {
            wp_send_json_error( array( 'message' => "Warning: The unit name '$unit_name' already exists. Duplicate entry not allowed." ) );
        }

        if ( $id > 0 ) {
            // Update
            $updated = $wpdb->update( $table, 
                array( 'unit_name' => $unit_name, 'description' => $description ), 
                array( 'id' => $id )
            );
            
            if ( $updated !== false ) {
                wp_send_json_success( array( 'message' => 'Unit updated successfully.' ) );
            } else {
                wp_send_json_error( array( 'message' => 'Failed to update unit.' ) );
            }
        } else {
            // Insert
            $inserted = $wpdb->insert( $table, array(
                'store_id' => obn_store_id(),
                'unit_name' => $unit_name,
                'description' => $description,
                'status' => 1
            ));

            if ( $inserted ) {
                $new_id = $wpdb->insert_id;
                wp_send_json_success( array( 
                    'message' => 'Unit added successfully.',
                    'id' => $new_id,
                    'unit_name' => $unit_name
                ) );
            } else {
                wp_send_json_error( array( 'message' => 'Failed to add unit.' ) );
            }
        }
    }
    public static function handle_delete_unit() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_units';
        $id = intval( $_POST['id'] );
        
        $deleted = $wpdb->delete( $table, array( 'id' => $id ) );
        
        if ( $deleted ) {
            wp_send_json_success( array( 'message' => 'Unit deleted successfully.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to delete unit.' ) );
        }
    }

    public static function handle_update_status() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_units';
        $id = intval( $_POST['id'] );
        $status = intval( $_POST['status'] );
        
        $updated = $wpdb->update( $table, array( 'status' => $status ), array( 'id' => $id ) );
        if ( $updated !== false ) {
            wp_send_json_success( array( 'message' => 'Unit status updated.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to update status.' ) );
        }
    }
}

Frontend_Accounting_Units::init();
