<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Frontend_Inventory_Print_Labels {

    public static function init() {
        add_action( 'wp_ajax_frontend_search_items_for_labels', array( __CLASS__, 'handle_search_items' ) );
        add_action( 'wp_ajax_nopriv_frontend_search_items_for_labels', array( __CLASS__, 'handle_search_items' ) );
    }

    public static function handle_search_items() {
        check_ajax_referer( 'frontend_ajax_nonce', 'security' ); // Using general nonce

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_items';
        $term = isset($_POST['term']) ? sanitize_text_field( $_POST['term'] ) : '';

        if ( empty( $term ) ) {
            $query = "SELECT id, item_name, item_code, sku, price, stock FROM $table 
                      WHERE status=1 
                      LIMIT 20";
            $results = $wpdb->get_results( $query );
        } else {
            $query = "SELECT id, item_name, item_code, sku, price, stock FROM $table 
                      WHERE status=1 AND (item_name LIKE %s OR item_code LIKE %s OR sku LIKE %s) 
                      LIMIT 20";
            
            $like_term = '%' . $wpdb->esc_like( $term ) . '%';
            $results = $wpdb->get_results( $wpdb->prepare( $query, $like_term, $like_term, $like_term ) );
        }

        wp_send_json_success( $results );
    }
}

Frontend_Inventory_Print_Labels::init();
