<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Frontend_Accounting_Brands {

    public static function init() {
        add_action( 'wp_ajax_frontend_save_brand', array( __CLASS__, 'handle_save_brand' ) );
        add_action( 'wp_ajax_nopriv_frontend_save_brand', array( __CLASS__, 'handle_save_brand' ) );
        add_action( 'wp_ajax_frontend_bulk_save_brands', array( __CLASS__, 'handle_bulk_save_brands' ) );
        add_action( 'wp_ajax_nopriv_frontend_bulk_save_brands', array( __CLASS__, 'handle_bulk_save_brands' ) );
        add_action( 'wp_ajax_frontend_delete_brand', array( __CLASS__, 'handle_delete_brand' ) );
        add_action( 'wp_ajax_nopriv_frontend_delete_brand', array( __CLASS__, 'handle_delete_brand' ) );
        add_action( 'wp_ajax_frontend_update_brand_status', array( __CLASS__, 'handle_update_status' ) );
        add_action( 'wp_ajax_nopriv_frontend_update_brand_status', array( __CLASS__, 'handle_update_status' ) );
        add_action( 'wp_ajax_frontend_generate_brand_code', array( __CLASS__, 'handle_generate_brand_code' ) );
        add_action( 'wp_ajax_nopriv_frontend_generate_brand_code', array( __CLASS__, 'handle_generate_brand_code' ) );
    }

    public static function handle_generate_brand_code() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_brands';
        $last_id = $wpdb->get_var( "SELECT id FROM $table ORDER BY id DESC LIMIT 1" );
        $next_id = $last_id ? intval( $last_id ) + 1 : 1;
        $code = 'IBRAND-' . str_pad( $next_id, 5, '0', STR_PAD_LEFT );
        wp_send_json_success( array( 'code' => $code ) );
    }

    public static function handle_save_brand() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_brands';

        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        $brand_name = sanitize_text_field( $_POST['brand_name'] );
        $brand_code = sanitize_text_field( $_POST['brand_code'] ?? '' );
        $description = sanitize_textarea_field( $_POST['description'] );

        if ( empty( $brand_name ) ) {
            wp_send_json_error( array( 'message' => 'Warning: Brand name is required.' ) );
        }

        // Duplicate Check
        $where = $id > 0 ? $wpdb->prepare( " AND id != %d", $id ) : "";
        
        $name_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE brand_name = %s $where", $brand_name ) );
        if ( $name_exists ) {
            wp_send_json_error( array( 'message' => "Warning: The brand name '$brand_name' already exists. Duplicate entry not allowed." ) );
        }

        if ( ! empty( $brand_code ) ) {
            $code_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE brand_code = %s $where", $brand_code ) );
            if ( $code_exists ) {
                wp_send_json_error( array( 'message' => "Warning: The brand code '$brand_code' already exists. Duplicate entry not allowed." ) );
            }
        }

        if ( $id > 0 ) {
            // Update
            $updated = $wpdb->update( $table, 
                array( 
                    'brand_name' => $brand_name, 
                    'brand_code' => $brand_code,
                    'description' => $description 
                ), 
                array( 'id' => $id )
            );
            
            if ( $updated !== false ) {
                wp_send_json_success( array( 'message' => 'Brand updated successfully.' ) );
            } else {
                wp_send_json_error( array( 'message' => 'Failed to update brand.' ) );
            }
        } else {
            // Insert
            // If code empty, generate it
            if ( empty( $brand_code ) ) {
                $last_id = $wpdb->get_var( "SELECT id FROM $table ORDER BY id DESC LIMIT 1" );
                $next_id = $last_id ? intval( $last_id ) + 1 : 1;
                $brand_code = 'IBRAND-' . str_pad( $next_id, 5, '0', STR_PAD_LEFT );
            }

            $inserted = $wpdb->insert( $table, array(
                'store_id' => 1,
                'brand_name' => $brand_name,
                'brand_code' => $brand_code,
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
    public static function handle_bulk_save_brands() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_brands';

        $brands = isset( $_POST['brands'] ) ? $_POST['brands'] : array();

        if ( empty( $brands ) || ! is_array( $brands ) ) {
            wp_send_json_error( array( 'message' => 'No brand data received.' ) );
        }

        $inserted = 0;
        $skipped  = 0;
        $errors   = array();

        foreach ( $brands as $row ) {
            $brand_name = isset( $row['brand_name'] ) ? sanitize_text_field( $row['brand_name'] ) : '';
            $brand_code = isset( $row['brand_code'] ) ? sanitize_text_field( $row['brand_code'] ) : '';
            $description = isset( $row['description'] ) ? sanitize_textarea_field( $row['description'] ) : '';

            if ( empty( $brand_name ) ) {
                $skipped++;
                continue;
            }

            // Check duplicate name
            $name_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE brand_name = %s", $brand_name ) );
            if ( $name_exists ) {
                $errors[] = "'{$brand_name}' already exists (duplicate name).";
                $skipped++;
                continue;
            }

            // Auto-generate code if empty
            if ( empty( $brand_code ) ) {
                $last_id = $wpdb->get_var( "SELECT id FROM $table ORDER BY id DESC LIMIT 1" );
                $next_id = $last_id ? intval( $last_id ) + 1 : 1;
                $brand_code = 'IBRAND-' . str_pad( $next_id, 5, '0', STR_PAD_LEFT );
            } else {
                // Check duplicate code
                $code_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE brand_code = %s", $brand_code ) );
                if ( $code_exists ) {
                    $errors[] = "Brand code '{$brand_code}' already exists (duplicate code).";
                    $skipped++;
                    continue;
                }
            }

            $wpdb->insert( $table, array(
                'store_id'   => 1,
                'brand_name' => $brand_name,
                'brand_code' => $brand_code,
                'description' => $description,
                'status'     => 1
            ) );

            if ( $wpdb->insert_id ) {
                $inserted++;
            } else {
                $errors[] = "Failed to insert '{$brand_name}'.";
                $skipped++;
            }
        }

        $message = sprintf( '%d brand(s) added successfully.', $inserted );
        if ( ! empty( $errors ) ) {
            $message .= ' Skipped: ' . implode( ' ', $errors );
        }

        wp_send_json_success( array( 'message' => $message, 'inserted' => $inserted, 'skipped' => $skipped ) );
    }

    public static function handle_delete_brand() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_brands';
        $id = intval( $_POST['id'] );
        
        $deleted = $wpdb->delete( $table, array( 'id' => $id ) );
        
        if ( $deleted ) {
            wp_send_json_success( array( 'message' => 'Brand deleted successfully.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to delete brand.' ) );
        }
    }

    public static function handle_update_status() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_brands';
        $id = intval( $_POST['id'] );
        $status = intval( $_POST['status'] );
        
        $updated = $wpdb->update( $table, array( 'status' => $status ), array( 'id' => $id ) );
        if ( $updated !== false ) {
            wp_send_json_success( array( 'message' => 'Brand status updated.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to update status.' ) );
        }
    }
}

Frontend_Accounting_Brands::init();
