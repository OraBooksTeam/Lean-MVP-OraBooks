<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Frontend_Inventory_Brands {

    public static function init() {
        add_action( 'wp_ajax_frontend_save_brand', array( __CLASS__, 'handle_save_brand' ) );
        add_action( 'wp_ajax_nopriv_frontend_save_brand', array( __CLASS__, 'handle_save_brand' ) );
        add_action( 'wp_ajax_frontend_delete_brand', array( __CLASS__, 'handle_delete_brand' ) );
        add_action( 'wp_ajax_nopriv_frontend_delete_brand', array( __CLASS__, 'handle_delete_brand' ) );
        add_action( 'wp_ajax_frontend_update_brand_status', array( __CLASS__, 'handle_update_status' ) );
        add_action( 'wp_ajax_nopriv_frontend_update_brand_status', array( __CLASS__, 'handle_update_status' ) );
        add_action( 'wp_ajax_frontend_generate_brand_code', array( __CLASS__, 'handle_generate_code' ) );
        add_action( 'wp_ajax_nopriv_frontend_generate_brand_code', array( __CLASS__, 'handle_generate_code' ) );
    }

    public static function handle_save_brand() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_brands';

        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        $brand_name = sanitize_text_field( $_POST['brand_name'] );
        $description = sanitize_textarea_field( $_POST['description'] );

        if ( empty( $brand_name ) ) {
            wp_send_json_error( array( 'message' => 'Brand name is required.' ) );
        }

        if ( $id > 0 ) {
            // Update
            $updated = $wpdb->update( $table, 
                array( 'brand_name' => $brand_name, 'description' => $description ), 
                array( 'id' => $id )
            );
            
            if ( $updated !== false ) {
                wp_send_json_success( array( 'message' => 'Brand updated successfully.' ) );
            } else {
                wp_send_json_error( array( 'message' => 'Failed to update brand.' ) );
            }
        } else {
            // Insert
            $brand_code = sanitize_text_field( $_POST['brand_code'] );
            if ( empty( $brand_code ) ) {
                // Generate if missing (fallback)
                $brand_code = self::generate_code();
            }

            $inserted = $wpdb->insert( $table, array(
                'store_id' => 1,
                'brand_code' => $brand_code,
                'brand_name' => $brand_name,
                'description' => $description,
                'status' => 1
            ));

            if ( $inserted ) {
                $new_id = $wpdb->insert_id;
                wp_send_json_success( array( 
                    'message' => 'Brand added successfully.',
                    'id' => $new_id,
                    'brand_name' => $brand_name
                ) );
            } else {
                wp_send_json_error( array( 'message' => 'Failed to add brand.' ) );
            }
        }
    }

    public static function handle_delete_brand() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_brands';
        $id = intval( $_POST['id'] );
        
        $deleted = $wpdb->delete( $table, array( 'id' => $id ) );
        
        if ( $deleted ) {
            wp_send_json_success( array( 'message' => 'Brand deleted.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to delete.' ) );
        }
    }

    public static function handle_update_status() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_brands';
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
        $table = $wpdb->prefix . 'orabooks_db_brands';
        $last = $wpdb->get_var( "SELECT brand_code FROM $table ORDER BY id DESC LIMIT 1" );
        
        $next_num = 1;
        if ( $last && preg_match( '/IBRAND-(\d+)/', $last, $matches ) ) {
            $next_num = intval( $matches[1] ) + 1;
        }
        
        return 'IBRAND-' . str_pad( $next_num, 5, '0', STR_PAD_LEFT );
    }
}

Frontend_Inventory_Brands::init();
