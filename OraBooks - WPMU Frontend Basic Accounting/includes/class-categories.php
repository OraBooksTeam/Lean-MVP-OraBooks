<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Frontend_Accounting_Categories {

    public static function init() {
        add_action( 'wp_ajax_frontend_save_category', array( __CLASS__, 'handle_save_category' ) );
        add_action( 'wp_ajax_nopriv_frontend_save_category', array( __CLASS__, 'handle_save_category' ) );
        add_action( 'wp_ajax_frontend_bulk_save_categories', array( __CLASS__, 'handle_bulk_save_categories' ) );
        add_action( 'wp_ajax_nopriv_frontend_bulk_save_categories', array( __CLASS__, 'handle_bulk_save_categories' ) );
        add_action( 'wp_ajax_frontend_delete_category', array( __CLASS__, 'handle_delete_category' ) );
        add_action( 'wp_ajax_nopriv_frontend_delete_category', array( __CLASS__, 'handle_delete_category' ) );
        add_action( 'wp_ajax_frontend_update_category_status', array( __CLASS__, 'handle_update_category_status' ) );
        add_action( 'wp_ajax_nopriv_frontend_update_category_status', array( __CLASS__, 'handle_update_category_status' ) );
        add_action( 'wp_ajax_frontend_generate_category_code', array( __CLASS__, 'handle_generate_category_code' ) );
        add_action( 'wp_ajax_nopriv_frontend_generate_category_code', array( __CLASS__, 'handle_generate_category_code' ) );
        add_action( 'wp_ajax_frontend_generate_bulk_category_codes', array( __CLASS__, 'handle_generate_bulk_category_codes' ) );
        add_action( 'wp_ajax_nopriv_frontend_generate_bulk_category_codes', array( __CLASS__, 'handle_generate_bulk_category_codes' ) );
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
            wp_send_json_error( array( 'message' => 'Failed to delete category.' ) );
        }
    }

    public static function handle_update_category_status() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_category';
        $id = intval( $_POST['id'] );
        $status = intval( $_POST['status'] );
        
        $wpdb->update( $table, array( 'status' => $status ), array( 'id' => $id ) );
        wp_send_json_success();
    }

    public static function handle_generate_category_code() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_category';
        $last_id = $wpdb->get_var( "SELECT id FROM $table ORDER BY id DESC LIMIT 1" );
        $next_id = $last_id ? intval( $last_id ) + 1 : 1;
        $code = 'ICAT-' . str_pad( $next_id, 5, '0', STR_PAD_LEFT );
        wp_send_json_success( array( 'code' => $code ) );
    }

    /**
     * Generate multiple sequential category codes at once (for bulk form rows).
     */
    public static function handle_generate_bulk_category_codes() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_category';
        $count = isset( $_POST['count'] ) ? max( 1, intval( $_POST['count'] ) ) : 1;

        $last_id = $wpdb->get_var( "SELECT id FROM $table ORDER BY id DESC LIMIT 1" );
        $next_id = $last_id ? intval( $last_id ) + 1 : 1;

        $codes = array();
        for ( $i = 0; $i < $count; $i++ ) {
            $codes[] = 'ICAT-' . str_pad( $next_id + $i, 5, '0', STR_PAD_LEFT );
        }

        wp_send_json_success( array( 'codes' => $codes ) );
    }

    /**
     * Bulk save multiple categories at once.
     */
    public static function handle_bulk_save_categories() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_category';

        $categories = isset( $_POST['categories'] ) ? array_values( (array) $_POST['categories'] ) : array();

        if ( empty( $categories ) ) {
            wp_send_json_error( array( 'message' => 'No categories to save.' ) );
        }

        $inserted = 0;
        $errors  = array();
        $names_seen = array();
        $codes_seen = array();

        // Compute base for auto-generated codes
        $last_id = $wpdb->get_var( "SELECT id FROM $table ORDER BY id DESC LIMIT 1" );
        $next_id = $last_id ? intval( $last_id ) + 1 : 1;
        $auto_code_counter = 0;

        foreach ( $categories as $row ) {
            $category_name = isset( $row['category_name'] ) ? sanitize_text_field( $row['category_name'] ) : '';
            $category_code = isset( $row['category_code'] ) ? sanitize_text_field( $row['category_code'] ) : '';
            $description   = isset( $row['description'] ) ? sanitize_textarea_field( $row['description'] ) : '';

            if ( empty( $category_name ) ) {
                continue; // skip empty rows
            }

            // Prevent duplicate names within the batch
            $name_key = strtolower( trim( $category_name ) );
            if ( in_array( $name_key, $names_seen, true ) ) {
                $errors[] = "Duplicate name '$category_name' in form rows.";
                continue;
            }
            $names_seen[] = $name_key;

            // Prevent duplicate codes within the batch
            if ( ! empty( $category_code ) ) {
                $code_key = strtolower( trim( $category_code ) );
                if ( in_array( $code_key, $codes_seen, true ) ) {
                    $errors[] = "Duplicate code '$category_code' in form rows.";
                    continue;
                }
                $codes_seen[] = $code_key;
            }

            // Duplicate Check in database
            $name_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE category_name = %s", $category_name ) );
            if ( $name_exists ) {
                $errors[] = "Category name '$category_name' already exists.";
                continue;
            }

            if ( ! empty( $category_code ) ) {
                $code_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE category_code = %s", $category_code ) );
                if ( $code_exists ) {
                    $errors[] = "Category code '$category_code' already exists.";
                    continue;
                }
            }

            // Auto-generate code if empty (track counter to avoid duplicates in batch)
            if ( empty( $category_code ) ) {
                $auto_code_counter++;
                $category_code = 'ICAT-' . str_pad( $next_id + $auto_code_counter, 5, '0', STR_PAD_LEFT );
            }

            $wpdb->insert( $table, array(
                'store_id'      => 1,
                'category_name' => $category_name,
                'category_code' => $category_code,
                'description'   => $description,
                'status'        => 1,
            ) );

            if ( $wpdb->insert_id ) {
                $inserted++;
            } else {
                $errors[] = "Failed to insert '$category_name'.";
            }
        }

        if ( $inserted > 0 && empty( $errors ) ) {
            wp_send_json_success( array( 'message' => "$inserted categories added successfully." ) );
        } elseif ( $inserted > 0 && ! empty( $errors ) ) {
            wp_send_json_success( array(
                'message' => "$inserted categories added. Warnings: " . implode( '; ', $errors ),
            ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to add categories. ' . implode( ' ', $errors ) ) );
        }
    }

    public static function handle_save_category() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_category';

        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        $category_name = sanitize_text_field( $_POST['category_name'] );
        $category_code = sanitize_text_field( $_POST['category_code'] ?? '' );
        $description = sanitize_textarea_field( $_POST['description'] );

        if ( empty( $category_name ) ) {
            wp_send_json_error( array( 'message' => 'Warning: Category name is required.' ) );
        }

        // Duplicate Check
        $where = $id > 0 ? $wpdb->prepare( " AND id != %d", $id ) : "";
        
        $name_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE category_name = %s $where", $category_name ) );
        if ( $name_exists ) {
            wp_send_json_error( array( 'message' => "Warning: The category name '$category_name' already exists. Duplicate entry not allowed." ) );
        }

        if ( ! empty( $category_code ) ) {
            $code_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE category_code = %s $where", $category_code ) );
            if ( $code_exists ) {
                wp_send_json_error( array( 'message' => "Warning: The category code '$category_code' already exists. Duplicate entry not allowed." ) );
            }
        }

        if ( $id > 0 ) {
            // Update
            $updated = $wpdb->update( $table, 
                array( 
                    'category_name' => $category_name, 
                    'category_code' => $category_code,
                    'description' => $description 
                ), 
                array( 'id' => $id )
            );
            
            if ( $updated !== false ) {
                wp_send_json_success( array( 'message' => 'Category updated successfully.' ) );
            } else {
                wp_send_json_error( array( 'message' => 'Failed to update category.' ) );
            }
        } else {
            // Insert
            // If code empty, generate it
            if ( empty( $category_code ) ) {
                $last_id = $wpdb->get_var( "SELECT id FROM $table ORDER BY id DESC LIMIT 1" );
                $next_id = $last_id ? intval( $last_id ) + 1 : 1;
                $category_code = 'ICAT-' . str_pad( $next_id, 5, '0', STR_PAD_LEFT );
            }

            $inserted = $wpdb->insert( $table, array(
                'store_id' => obn_store_id(),
                'category_name' => $category_name,
                'category_code' => $category_code,
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
}

Frontend_Accounting_Categories::init();
