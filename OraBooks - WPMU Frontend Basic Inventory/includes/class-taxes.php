<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Frontend_Inventory_Taxes {

    public static function init() {
        add_action( 'wp_ajax_frontend_save_tax', array( __CLASS__, 'handle_save_tax' ) );
        add_action( 'wp_ajax_nopriv_frontend_save_tax', array( __CLASS__, 'handle_save_tax' ) );
    }

    public static function handle_save_tax() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_tax';

        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        $tax_name = sanitize_text_field( $_POST['tax_name'] );
        $tax_percent = floatval( $_POST['tax_percent'] );

        if ( empty( $tax_name ) ) {
            wp_send_json_error( array( 'message' => 'Tax name is required.' ) );
        }

        if ( $id > 0 ) {
            // Update
            $updated = $wpdb->update( $table, 
                array( 'tax_name' => $tax_name, 'tax' => $tax_percent ), 
                array( 'id' => $id )
            );
            
            if ( $updated !== false ) {
                wp_send_json_success( array( 
                    'message' => 'Tax updated successfully.',
                    'id' => $id,
                    'tax_name' => $tax_name,
                    'tax' => $tax_percent
                ) );
            } else {
                wp_send_json_error( array( 'message' => 'Failed to update tax.' ) );
            }
        } else {
            // Insert
            $inserted = $wpdb->insert( $table, array(
                'store_id' => 1,
                'tax_name' => $tax_name,
                'tax' => $tax_percent,
                'status' => 1
            ));

            if ( $inserted ) {
                $new_id = $wpdb->insert_id;
                wp_send_json_success( array( 
                    'message' => 'Tax added successfully.',
                    'id' => $new_id,
                    'tax_name' => $tax_name,
                    'tax' => $tax_percent
                ) );
            } else {
                wp_send_json_error( array( 'message' => 'Failed to add tax.' ) );
            }
        }
    }
}

Frontend_Inventory_Taxes::init();
