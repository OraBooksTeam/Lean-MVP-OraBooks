<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Frontend_Inventory_Permissions {

    public static function init() {
        add_action( 'wp_ajax_save_user_permissions', [ __CLASS__, 'save_user_permissions' ] );
        add_action( 'wp_ajax_get_user_permissions', [ __CLASS__, 'get_user_permissions' ] );
        add_action( 'wp_ajax_get_all_assigned_permissions', [ __CLASS__, 'get_all_assigned_permissions' ] );
        add_action( 'wp_ajax_delete_user_permissions', [ __CLASS__, 'delete_user_permissions' ] );
    }

    public static function get_all_users() {
        global $wpdb;
        $users = $wpdb->get_results( "SELECT ID, display_name, user_login, user_email FROM $wpdb->users ORDER BY display_name ASC" );
        if ( empty($users) ) {
            return get_users();
        }
        return $users;
    }

    public static function get_all_sidebar_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_db_sidebar';
        return $wpdb->get_results( "SELECT * FROM $table_name WHERE status = 1 ORDER BY module ASC, sort_order ASC", ARRAY_A );
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

        $sidebar_ids = self::normalize_sidebar_ids_with_parents( $sidebar_ids );
        $sidebar_ids_json = json_encode( $sidebar_ids );

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

        // If a permission row exists, it is the source of truth for every user,
        // including administrators. Admin fallback only applies when no row exists.
        $sidebar_ids_json = $wpdb->get_var( $wpdb->prepare( "SELECT sidebar_ids FROM $table_name WHERE user_id = %d", $user_id ) );

        if ( null !== $sidebar_ids_json ) {
            $sidebar_ids = json_decode( $sidebar_ids_json, true );
            return is_array( $sidebar_ids ) ? array_map( 'intval', $sidebar_ids ) : [];
        }

        if ( user_can( $user_id, 'manage_options' ) ) {
            return true; 
        }

        return [];
    }

    private static function normalize_sidebar_ids_with_parents( $sidebar_ids ) {
        global $wpdb;

        $sidebar_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $sidebar_ids ) ) ) );

        if ( empty( $sidebar_ids ) ) {
            return [];
        }

        $table_sidebar = $wpdb->prefix . 'orabooks_db_sidebar';
        $id_list = implode( ',', $sidebar_ids );
        $parents = $wpdb->get_col( "SELECT DISTINCT parent FROM $table_sidebar WHERE id IN ($id_list) AND parent > 0" );

        if ( ! empty( $parents ) ) {
            $sidebar_ids = array_values( array_unique( array_merge( $sidebar_ids, array_map( 'intval', $parents ) ) ) );
        }

        sort( $sidebar_ids );

        return $sidebar_ids;
    }

    /**
     * SL-003 Integration: Check if user has access to inventory via SL-003 RBAC.
     * Wraps both legacy custom permissions table and new SL-003 RBAC system.
     *
     * @param int    $user_id     User ID
     * @param int    $org_id      Organization ID (optional, from user_meta)
     * @return bool True if user can access inventory
     */
    public static function has_rbac_access($user_id = 0, $org_id = 0) {
        if (empty($user_id)) {
            $user_id = get_current_user_id();
        }

        if (empty($org_id)) {
            $org_id = (int) get_user_meta($user_id, 'org_id', true);
        }

        // Use SL-003 RBAC if available
        // Note: Partner org blocking is handled by requireCustomerOrg() middleware
        // in the accounting plugin and membership API layer.
        if (class_exists('OraBooks_RBAC') && !empty($org_id)) {
            // Check role-based permission
            return OraBooks_RBAC::get_instance()->require_permission(
                $user_id,
                $org_id,
                'access_inventory'
            );
        }

        // Fallback to legacy access check
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        return false;
    }

    public static function has_view_permission( $view_slug ) {
        // Dashboard is usually always accessible as the landing page
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
        
        return $item_id && in_array( (int) $item_id, array_map( 'intval', (array) $permitted_ids ), true );
    }
}
