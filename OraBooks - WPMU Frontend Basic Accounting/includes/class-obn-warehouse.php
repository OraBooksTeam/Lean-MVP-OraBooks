<?php
/**
 * Warehouse Database and Form Handler Class
 * File: includes/class-obn-warehouse.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OBN_Warehouse {

    public static function init() {
        // Register AJAX status toggle hook
        add_action( 'wp_ajax_obn_toggle_warehouse_status', [ self::class, 'handle_toggle_status' ] );
        add_action( 'wp_ajax_nopriv_obn_toggle_warehouse_status', [ self::class, 'handle_toggle_status' ] );

        // Hook into init to process form submissions before headers are sent
        add_action( 'init', [ self::class, 'process_warehouse_actions' ] );
    }

    /**
     * Intercept and handle CRUD form submissions (Insert, Update, Delete)
     */
    public static function process_warehouse_actions() {
        // Only run when view is warehouse
        if ( ! isset( $_GET['view'] ) || $_GET['view'] !== 'warehouse' ) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_db_warehouse';
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

        // 1. Handle DELETE Action
        if ( $action === 'delete' && isset( $_GET['id'] ) && isset( $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_warehouse_' . $_GET['id'] ) ) {
                $id = intval( $_GET['id'] );
                $deleted = $wpdb->delete( $table_name, [ 'id' => $id ] );
                if ( $deleted ) {
                    $redirect_url = add_query_arg( [ 'action' => 'list', 'message' => 'deleted' ], remove_query_arg( [ 'action', 'id', 'message', '_wpnonce' ] ) );
                    wp_redirect( $redirect_url );
                    exit;
                } else {
                    $redirect_url = add_query_arg( [ 'action' => 'list', 'message' => 'delete_failed' ], remove_query_arg( [ 'action', 'id', 'message', '_wpnonce' ] ) );
                    wp_redirect( $redirect_url );
                    exit;
                }
            } else {
                $redirect_url = add_query_arg( [ 'action' => 'list', 'message' => 'security_failed' ], remove_query_arg( [ 'action', 'id', 'message', '_wpnonce' ] ) );
                wp_redirect( $redirect_url );
                exit;
            }
        }

        // 2. Handle SAVE Action (Insert/Update)
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['save_warehouse_nonce'] ) && wp_verify_nonce( $_POST['save_warehouse_nonce'], 'save_warehouse_action' ) ) {
            
            $id = isset( $_POST['warehouse_id'] ) ? intval( $_POST['warehouse_id'] ) : 0;

            if ( $id > 0 ) {
                // --- UPDATE MODE (Single Row) ---
                $warehouse_name = sanitize_text_field( $_POST['warehouse_name'] );
                $mobile         = sanitize_text_field( $_POST['mobile'] );
                $email          = sanitize_email( $_POST['email'] );
                $address        = sanitize_textarea_field( $_POST['address'] );

                // Duplicate Check
                $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE warehouse_name = %s AND id != %d", $warehouse_name, $id ) );

                if ( empty( $warehouse_name ) ) {
                    set_transient( 'obn_warehouse_error_' . get_current_user_id(), 'Warehouse Name is required.', 30 );
                    $redirect_url = add_query_arg( [ 'action' => 'edit', 'id' => $id ], remove_query_arg( [ 'action', 'id', 'message' ] ) );
                    wp_redirect( $redirect_url );
                    exit;
                } elseif ( $existing ) {
                    set_transient( 'obn_warehouse_error_' . get_current_user_id(), 'Duplicate Entry: A warehouse with this name already exists.', 30 );
                    $redirect_url = add_query_arg( [ 'action' => 'edit', 'id' => $id ], remove_query_arg( [ 'action', 'id', 'message' ] ) );
                    wp_redirect( $redirect_url );
                    exit;
                } else {
                    $data = [
                        'warehouse_name' => $warehouse_name,
                        'mobile'         => $mobile,
                        'email'          => $email,
                        'address'        => $address,
                    ];

                    $updated = $wpdb->update( $table_name, $data, [ 'id' => $id ] );
                    if ( $updated !== false ) {
                        $redirect_url = add_query_arg( [ 'action' => 'list', 'message' => 'updated' ], remove_query_arg( [ 'action', 'id', 'message' ] ) );
                        wp_redirect( $redirect_url );
                        exit;
                    } else {
                        set_transient( 'obn_warehouse_error_' . get_current_user_id(), 'Failed to update warehouse.', 30 );
                        $redirect_url = add_query_arg( [ 'action' => 'edit', 'id' => $id ], remove_query_arg( [ 'action', 'id', 'message' ] ) );
                        wp_redirect( $redirect_url );
                        exit;
                    }
                }
            } else {
                // --- INSERT MODE (Multiple Rows) ---
                $names     = isset( $_POST['warehouse_name'] ) ? (array) $_POST['warehouse_name'] : [];
                $mobiles   = isset( $_POST['mobile'] ) ? (array) $_POST['mobile'] : [];
                $emails    = isset( $_POST['email'] ) ? (array) $_POST['email'] : [];
                $addresses = isset( $_POST['address'] ) ? (array) $_POST['address'] : [];

                $success_count = 0;
                $error_msgs    = [];

                foreach ( $names as $index => $name ) {
                    $warehouse_name = sanitize_text_field( $name );
                    if ( empty( $warehouse_name ) ) {
                        continue; // Skip empty rows
                    }

                    $mobile  = isset( $mobiles[$index] ) ? sanitize_text_field( $mobiles[$index] ) : '';
                    $email   = isset( $emails[$index] ) ? sanitize_email( $emails[$index] ) : '';
                    $address = isset( $addresses[$index] ) ? sanitize_textarea_field( $addresses[$index] ) : '';

                    // Duplicate Check
                    $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE warehouse_name = %s", $warehouse_name ) );

                    if ( $existing ) {
                        $error_msgs[] = "Warehouse '{$warehouse_name}' already exists.";
                    } else {
                        $data = [
                            'warehouse_name' => $warehouse_name,
                            'mobile'         => $mobile,
                            'email'          => $email,
                            'address'        => $address,
                            'store_id'       => obn_store_id(),
                            'warehouse_type' => 'custom',
                            'status'         => 1,
                            'created_date'   => current_time( 'mysql' ),
                        ];

                        $inserted = $wpdb->insert( $table_name, $data );
                        if ( $inserted ) {
                            $success_count++;
                        } else {
                            $error_msgs[] = "Failed to add '{$warehouse_name}'.";
                        }
                    }
                }

                if ( $success_count > 0 && empty( $error_msgs ) ) {
                    $redirect_url = add_query_arg( [ 'action' => 'list', 'message' => 'added' ], remove_query_arg( [ 'action', 'id', 'message' ] ) );
                    wp_redirect( $redirect_url );
                    exit;
                } elseif ( $success_count > 0 && ! empty( $error_msgs ) ) {
                    set_transient( 'obn_warehouse_error_' . get_current_user_id(), 'Added some warehouses, but: ' . implode( ' ', $error_msgs ), 30 );
                    $redirect_url = add_query_arg( [ 'action' => 'list' ], remove_query_arg( [ 'action', 'id', 'message' ] ) );
                    wp_redirect( $redirect_url );
                    exit;
                } else {
                    $msg = ! empty( $error_msgs ) ? implode( ' ', $error_msgs ) : 'No valid warehouses provided.';
                    set_transient( 'obn_warehouse_error_' . get_current_user_id(), $msg, 30 );
                    $redirect_url = add_query_arg( [ 'action' => 'add' ], remove_query_arg( [ 'action', 'id', 'message' ] ) );
                    wp_redirect( $redirect_url );
                    exit;
                }
            }
        }
    }

    /**
     * AJAX handler to toggle warehouse status
     */
    public static function handle_toggle_status() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' );

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error( array( 'message' => 'Access denied.' ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_warehouse';

        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        $status = isset( $_POST['status'] ) ? intval( $_POST['status'] ) : 0;

        if ( $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid Warehouse ID.' ) );
        }

        // Toggle status: 1 -> 0, 0 -> 1
        $new_status = ( $status === 1 ) ? 0 : 1;
        $updated = $wpdb->update( $table, array( 'status' => $new_status ), array( 'id' => $id ) );

        if ( $updated !== false ) {
            wp_send_json_success( array( 'new_status' => $new_status, 'message' => 'Status updated successfully.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to update warehouse status.' ) );
        }
    }
}

OBN_Warehouse::init();
