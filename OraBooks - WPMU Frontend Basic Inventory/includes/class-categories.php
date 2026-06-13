<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Frontend_Inventory_Categories {

    public static function init() {
        add_action( 'wp_ajax_frontend_save_category', array( __CLASS__, 'handle_save_category' ) );
        add_action( 'wp_ajax_nopriv_frontend_save_category', array( __CLASS__, 'handle_save_category' ) );
        add_action( 'wp_ajax_frontend_delete_category', array( __CLASS__, 'handle_delete_category' ) );
        add_action( 'wp_ajax_nopriv_frontend_delete_category', array( __CLASS__, 'handle_delete_category' ) );
        add_action( 'wp_ajax_frontend_update_category_status', array( __CLASS__, 'handle_update_status' ) );
        add_action( 'wp_ajax_nopriv_frontend_update_category_status', array( __CLASS__, 'handle_update_status' ) );
        add_action( 'wp_ajax_frontend_generate_category_code', array( __CLASS__, 'handle_generate_code' ) );
        add_action( 'wp_ajax_nopriv_frontend_generate_category_code', array( __CLASS__, 'handle_generate_code' ) );
    }

    public static function handle_save_category() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_category';

        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        $category_name = sanitize_text_field( $_POST['category_name'] );
        $description = sanitize_textarea_field( $_POST['description'] );

        if ( empty( $category_name ) ) {
            wp_send_json_error( array( 'message' => 'Category name is required.' ) );
        }

        if ( $id > 0 ) {
            // Update
            $updated = $wpdb->update( $table, 
                array( 'category_name' => $category_name, 'description' => $description ), 
                array( 'id' => $id )
            );
            
            if ( $updated !== false ) {
                wp_send_json_success( array( 'message' => 'Category updated successfully.' ) );
            } else {
                wp_send_json_error( array( 'message' => 'Failed to update category.' ) );
            }
        } else {
            // Insert
            $category_code = sanitize_text_field( $_POST['category_code'] );
            if ( empty( $category_code ) ) {
                // Generate if missing (fallback)
                $category_code = self::generate_code();
            }

            $inserted = $wpdb->insert( $table, array(
                'store_id' => 1,
                'category_code' => $category_code,
                'category_name' => $category_name,
                'description' => $description,
                'status' => 1
            ));

            if ( $inserted ) {
                $new_id = $wpdb->insert_id;
                wp_send_json_success( array( 
                    'message' => 'Category added successfully.',
                    'id' => $new_id,
                    'category_name' => $category_name
                ) );
            } else {
                wp_send_json_error( array( 'message' => 'Failed to add category.' ) );
            }
        }
    }

    public static function handle_delete_category() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_category';
        $id = intval( $_POST['id'] );
        
        $deleted = $wpdb->delete( $table, array( 'id' => $id ) );
        
        if ( $deleted ) {
            wp_send_json_success( array( 'message' => 'Category deleted.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to delete.' ) );
        }
    }

    public static function handle_update_status() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_category';
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
        $table = $wpdb->prefix . 'orabooks_db_category';
        $last = $wpdb->get_var( "SELECT category_code FROM $table ORDER BY id DESC LIMIT 1" );
        
        $next_num = 1;
        if ( $last && preg_match( '/ICAT-(\d+)/', $last, $matches ) ) {
            $next_num = intval( $matches[1] ) + 1;
        }
        
        return 'ICAT-' . str_pad( $next_num, 5, '0', STR_PAD_LEFT );
    }
}

Frontend_Inventory_Categories::init();
