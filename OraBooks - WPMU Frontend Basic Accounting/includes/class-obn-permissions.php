<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OBN_Permissions {

    public static function init() {
        add_action( 'wp_ajax_obn_save_user_permissions', [ __CLASS__, 'save_user_permissions' ] );
        add_action( 'wp_ajax_obn_get_user_permissions', [ __CLASS__, 'get_user_permissions' ] );
        add_action( 'wp_ajax_obn_get_all_assigned_permissions', [ __CLASS__, 'get_all_assigned_permissions' ] );
        add_action( 'wp_ajax_obn_delete_user_permissions', [ __CLASS__, 'delete_user_permissions' ] );
    }

    public static function get_all_users() {
        global $wpdb;

        $users = get_users( [
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'fields'  => [ 'ID', 'display_name', 'user_email', 'user_login' ],
        ] );

        if ( empty($users) ) {
            $users = $wpdb->get_results( "SELECT ID, display_name, user_email, user_login FROM $wpdb->users ORDER BY display_name ASC", OBJECT );
        }

        return $users;
    }

    public static function get_all_sidebar_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_db_sidebar';

        self::ensure_purchase_sidebar_items( $wpdb, $table_name );

        return $wpdb->get_results( "SELECT * FROM $table_name WHERE status = 1 ORDER BY module ASC, sort_order ASC", ARRAY_A );
    }

    private static function ensure_purchase_sidebar_items( $wpdb, $table_name ) {
        if ( class_exists( 'OBN_Sidebar' ) && method_exists( 'OBN_Sidebar', 'ensure_purchase_items' ) ) {
            OBN_Sidebar::ensure_purchase_items( $wpdb, $table_name );
            return;
        }

        $parent_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE menu_slug = %s AND module = %s", 'purchase', 'accounting' ) );
        if ( ! $parent_id ) {
            $wpdb->insert( $table_name, [
                'module'     => 'accounting',
                'parent'     => 0,
                'menu_title' => 'Purchase',
                'menu_slug'  => 'purchase',
                'icon'       => 'fa-solid fa-bag-shopping',
                'sort_order' => 6,
                'status'     => 1,
            ] );
            $parent_id = $wpdb->insert_id;
        }

        $items = [
            ['menu_title' => 'View Purchase', 'menu_slug' => 'view-purchase', 'icon' => 'fa-solid fa-file-invoice-dollar', 'sort_order' => 1],
            ['menu_title' => 'Add Purchase', 'menu_slug' => 'add-purchase', 'icon' => 'fa-solid fa-bag-shopping', 'sort_order' => 2],
            ['menu_title' => 'Edit Purchase', 'menu_slug' => 'edit-purchase', 'icon' => 'fa-solid fa-pen-to-square', 'sort_order' => 3],
            ['menu_title' => 'Purchase Orders', 'menu_slug' => 'purchase-ordered-list', 'icon' => 'fa-solid fa-clipboard-list', 'sort_order' => 4],
            ['menu_title' => 'Pending Purchases', 'menu_slug' => 'purchase-pending-list', 'icon' => 'fa-solid fa-clock', 'sort_order' => 5],
            ['menu_title' => 'Purchase Invoice', 'menu_slug' => 'purchase-invoice', 'icon' => 'fa-solid fa-file-invoice', 'sort_order' => 6],
            ['menu_title' => 'Purchase Return List', 'menu_slug' => 'purchase-return-list', 'icon' => 'fa-solid fa-arrow-rotate-left', 'sort_order' => 7],
            ['menu_title' => 'Add Purchase Return', 'menu_slug' => 'add-purchase-return', 'icon' => 'fa-solid fa-plus', 'sort_order' => 8],
            ['menu_title' => 'Edit Purchase Return', 'menu_slug' => 'edit-purchase-return', 'icon' => 'fa-solid fa-pen-to-square', 'sort_order' => 9],
            ['menu_title' => 'Purchase Return Invoice', 'menu_slug' => 'purchase-return-invoice', 'icon' => 'fa-solid fa-file-invoice-dollar', 'sort_order' => 10],
        ];

        foreach ( $items as $item ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE menu_slug = %s AND module = %s AND parent = %d", $item['menu_slug'], 'accounting', $parent_id ) );
            if ( ! $exists ) {
                $wpdb->insert( $table_name, [
                    'module'     => 'accounting',
                    'parent'     => $parent_id,
                    'menu_title' => $item['menu_title'],
                    'menu_slug'  => $item['menu_slug'],
                    'icon'       => $item['icon'],
                    'sort_order' => $item['sort_order'],
                    'status'     => 1,
                ] );
            }
        }
    }

    public static function save_user_permissions() {
        check_ajax_referer( 'frontend_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized access.' ] );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_user_permissions';
        
        $user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
        $sidebar_ids = isset( $_POST['sidebar_ids'] ) ? $_POST['sidebar_ids'] : [];

        if ( ! $user_id ) {
            wp_send_json_error( [ 'message' => 'Invalid User ID.' ] );
        }

        // Merge with existing permissions from other modules (e.g. inventory)
        $table_sidebar = $wpdb->prefix . 'orabooks_db_sidebar';
        $new_ids = array_map( 'intval', $sidebar_ids );

        $existing_row = $wpdb->get_row( $wpdb->prepare( "SELECT sidebar_ids FROM $table_name WHERE user_id = %d", $user_id ) );
        if ( $existing_row && ! empty( $existing_row->sidebar_ids ) ) {
            $existing_ids = json_decode( $existing_row->sidebar_ids, true );
            if ( is_array( $existing_ids ) ) {
                // Get all sidebar IDs belonging to the accounting module
                $accounting_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table_sidebar WHERE module = %s", 'accounting' ) );
                $accounting_ids = array_map( 'intval', $accounting_ids );

                // Keep only non-accounting IDs from existing permissions
                $existing_ids = array_diff( $existing_ids, $accounting_ids );

                // Merge remaining (non-accounting) with the new accounting IDs
                $new_ids = array_values( array_unique( array_merge( $existing_ids, $new_ids ) ) );
            }
        }

        $sidebar_ids_json = json_encode( $new_ids );

        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE user_id = %d", $user_id ) );

        try {
            if ( $existing ) {
                $updated = $wpdb->update( 
                    $table_name, 
                    [ 'sidebar_ids' => $sidebar_ids_json ], 
                    [ 'user_id' => $user_id ],
                    [ '%s' ],
                    [ '%d' ]
                );
            } else {
                $updated = $wpdb->insert( 
                    $table_name, 
                    [ 'user_id' => $user_id, 'sidebar_ids' => $sidebar_ids_json ],
                    [ '%d', '%s' ]
                );
            }

            if ( $updated !== false ) {
                wp_send_json_success( [ 'message' => 'Permissions saved successfully.' ] );
            } else {
                wp_send_json_error( [ 'message' => 'Failed to save permissions: ' . $wpdb->last_error ] );
            }
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => 'An error occurred: ' . $e->getMessage() ] );
        }
    }

    public static function get_user_permissions() {
        check_ajax_referer( 'frontend_ajax_nonce', 'nonce' );

        $user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;

        if ( ! $user_id ) {
            wp_send_json_error( [ 'message' => 'Invalid User ID.' ] );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_user_permissions';
        $sidebar_ids_json = $wpdb->get_var( $wpdb->prepare( "SELECT sidebar_ids FROM $table_name WHERE user_id = %d", $user_id ) );

        $sidebar_ids = $sidebar_ids_json ? json_decode( $sidebar_ids_json, true ) : [];

        wp_send_json_success( [ 'sidebar_ids' => $sidebar_ids ] );
    }

    public static function get_all_assigned_permissions() {
        check_ajax_referer( 'frontend_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized access.' ] );
        }

        global $wpdb;
        $table_permissions = $wpdb->prefix . 'orabooks_user_permissions';
        
        $results = $wpdb->get_results( "SELECT * FROM $table_permissions" );

        $data = [];
        foreach ( $results as $row ) {
            $user = $wpdb->get_row( $wpdb->prepare( "SELECT display_name, user_email, user_login FROM $wpdb->users WHERE ID = %d", $row->user_id ) );
            if ( ! $user ) {
                // Fallback to get_userdata just in case
                $wp_user = get_userdata( $row->user_id );
                if ( ! $wp_user ) continue;
                $display_name = !empty($wp_user->display_name) ? $wp_user->display_name : $wp_user->user_login;
                $user_email = $wp_user->user_email;
            } else {
                $display_name = !empty($user->display_name) ? $user->display_name : $user->user_login;
                $user_email = $user->user_email;
            }

            $sidebar_ids = json_decode( $row->sidebar_ids, true );
            $feature_count = is_array( $sidebar_ids ) ? count( $sidebar_ids ) : 0;

            $data[] = [
                'user_id'       => $row->user_id,
                'display_name'  => $display_name,
                'user_email'    => $user_email,
                'feature_count' => $feature_count
            ];
        }

        wp_send_json_success( $data );
    }

    public static function delete_user_permissions() {
        check_ajax_referer( 'frontend_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized access.' ] );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_user_permissions';
        $user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;

        if ( ! $user_id ) {
            wp_send_json_error( [ 'message' => 'Invalid User ID.' ] );
        }

        $deleted = $wpdb->delete( $table_name, [ 'user_id' => $user_id ], [ '%d' ] );

        if ( $deleted !== false ) {
            wp_send_json_success( [ 'message' => 'Permissions deleted successfully.' ] );
        } else {
            wp_send_json_error( [ 'message' => 'Failed to delete permissions.' ] );
        }
    }

    public static function get_user_permitted_ids( $user_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_user_permissions';
        
        // If user has a row in the permission table, use those specific IDs
        $sidebar_ids_json = $wpdb->get_var( $wpdb->prepare( "SELECT sidebar_ids FROM $table_name WHERE user_id = %d", $user_id ) );

        if ( $sidebar_ids_json ) {
            $ids = json_decode( $sidebar_ids_json, true );
            return is_array($ids) ? $ids : [];
        }

        // Fallback for admins who are not specifically restricted in the table
        if ( user_can( $user_id, 'manage_options' ) ) {
            return true;
        }

        return [];
    }

    public static function has_view_permission( $view_slug ) {
        // Dashboard is usually always accessible
        if ( empty( $view_slug ) || $view_slug === 'dashboard' ) {
            return true;
        }

        $user_id = get_current_user_id();
        $permitted_ids = self::get_user_permitted_ids( $user_id );
        
        if ( $permitted_ids === true ) {
            return true;
        }

        if ( empty( $permitted_ids ) ) {
            return false;
        }

        global $wpdb;
        $table_sidebar = $wpdb->prefix . 'orabooks_db_sidebar';
        $item_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_sidebar WHERE menu_slug = %s LIMIT 1", $view_slug ) );
        
        return $item_id && in_array( $item_id, $permitted_ids );
    }
}

OBN_Permissions::init();
