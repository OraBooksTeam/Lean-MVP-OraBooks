<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Frontend_Inventory_Variants {

    public static function init() {
        add_action( 'wp_ajax_frontend_save_variant', array( __CLASS__, 'handle_save_variant' ) );
        add_action( 'wp_ajax_nopriv_frontend_save_variant', array( __CLASS__, 'handle_save_variant' ) );
        add_action( 'wp_ajax_frontend_delete_variant', array( __CLASS__, 'handle_delete_variant' ) );
        add_action( 'wp_ajax_nopriv_frontend_delete_variant', array( __CLASS__, 'handle_delete_variant' ) );
        add_action( 'wp_ajax_frontend_update_variant_status', array( __CLASS__, 'handle_update_status' ) );
        add_action( 'wp_ajax_nopriv_frontend_update_variant_status', array( __CLASS__, 'handle_update_status' ) );
        add_action( 'wp_ajax_frontend_generate_variant_code', array( __CLASS__, 'handle_generate_code' ) );
        add_action( 'wp_ajax_nopriv_frontend_generate_variant_code', array( __CLASS__, 'handle_generate_code' ) );
    }

    public static function handle_save_variant() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_variants';

        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        $variant_name = sanitize_text_field( $_POST['variant_name'] );
        $description = sanitize_textarea_field( $_POST['description'] );

        if ( empty( $variant_name ) ) {
            wp_send_json_error( array( 'message' => 'Variant name is required.' ) );
        }

        if ( $id > 0 ) {
            // Update
            $updated = $wpdb->update( $table, 
                array( 'variant_name' => $variant_name, 'description' => $description ), 
                array( 'id' => $id )
            );
            
            if ( $updated !== false ) {
                wp_send_json_success( array( 'message' => 'Variant updated successfully.' ) );
            } else {
                wp_send_json_error( array( 'message' => 'Failed to update variant.' ) );
            }
        } else {
            // Insert
            $variant_code = sanitize_text_field( $_POST['variant_code'] );
            if ( empty( $variant_code ) ) {
                // Generate if missing (fallback)
                $variant_code = self::generate_code();
            }

            $inserted = $wpdb->insert( $table, array(
                'store_id' => 1,
                'variant_code' => $variant_code,
                'variant_name' => $variant_name,
                'description' => $description,
                'status' => 1
            ));

            if ( $inserted ) {
                wp_send_json_success( array( 'message' => 'Variant added successfully.' ) );
            } else {
                wp_send_json_error( array( 'message' => 'Failed to add variant.' ) );
            }
        }
    }

    public static function handle_delete_variant() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_variants';
        $id = intval( $_POST['id'] );
        
        $deleted = $wpdb->delete( $table, array( 'id' => $id ) );
        
        if ( $deleted ) {
            wp_send_json_success( array( 'message' => 'Variant deleted.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to delete.' ) );
        }
    }

    public static function handle_update_status() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_variants';
        $id = intval( $_POST['id'] );
        $status = intval( $_POST['status'] );
        
        $wpdb->update( $table, array( 'status' => $status ), array( 'id' => $id ) );
        wp_send_json_success();
    }

    public static function handle_generate_code() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );
        $code = self::generate_code();
        wp_send_json_success( array( 'code' => $code ) );
    }

    private static function generate_code() {
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_variants';
        $last = $wpdb->get_var( "SELECT variant_code FROM $table ORDER BY id DESC LIMIT 1" );
        
        $next_num = 1;
        if ( $last && preg_match( '/VAR-(\d+)/', $last, $matches ) ) {
            $next_num = intval( $matches[1] ) + 1;
        }
        
        return 'VAR-' . str_pad( $next_num, 5, '0', STR_PAD_LEFT );
    }
}

Frontend_Inventory_Variants::init();
