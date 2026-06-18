<?php
/**
 * Expense Category AJAX Handler Class
 * 
 * Manages CRUD operations for expense categories in Frontend-Accounting plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class OBN_Expense_Categories {

    public static function init() {
        add_action('wp_ajax_obn_insert_expense_category', [self::class, 'insert_expense_category']);
        add_action('wp_ajax_nopriv_obn_insert_expense_category', [self::class, 'insert_expense_category']);

        add_action('wp_ajax_obn_update_expense_category', [self::class, 'update_expense_category']);
        add_action('wp_ajax_nopriv_obn_update_expense_category', [self::class, 'update_expense_category']);

        add_action('wp_ajax_obn_delete_expense_category', [self::class, 'delete_expense_category']);
        add_action('wp_ajax_nopriv_obn_delete_expense_category', [self::class, 'delete_expense_category']);

        add_action('wp_ajax_obn_toggle_expense_category_status', [self::class, 'toggle_expense_category_status']);
        add_action('wp_ajax_nopriv_obn_toggle_expense_category_status', [self::class, 'toggle_expense_category_status']);

        add_action('wp_ajax_obn_get_expense_category', [self::class, 'get_expense_category']);
        add_action('wp_ajax_nopriv_obn_get_expense_category', [self::class, 'get_expense_category']);
    }

    public static function insert_expense_category() {
        check_ajax_referer('obn_expense_category_action_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;

        $category_name = sanitize_text_field($_POST['category_name'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');

        if (!$category_name) wp_send_json_error('Category name is required');

        $category_table = $wpdb->prefix . 'orabooks_db_expense_category';
        $insert_data = [
            'category_name' => $category_name,
            'description' => $description,
            'status' => 1,
        ];

        $inserted = $wpdb->insert($category_table, $insert_data);

        if (!$inserted) {
            wp_send_json_error('Failed to insert category: ' . $wpdb->last_error);
        }

        wp_send_json_success(['message' => 'Category saved successfully']);
    }

    public static function update_expense_category() {
        check_ajax_referer('obn_expense_category_action_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;

        $category_id = intval($_POST['id'] ?? 0);
        if (!$category_id) wp_send_json_error('Invalid category ID');

        $category_name = sanitize_text_field($_POST['category_name'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');

        if (!$category_name) wp_send_json_error('Category name is required');

        $category_table = $wpdb->prefix . 'orabooks_db_expense_category';
        $update_data = [
            'category_name' => $category_name,
            'description' => $description,
        ];

        $updated = $wpdb->update($category_table, $update_data, ['id' => $category_id]);

        if ($updated === false) {
            wp_send_json_error('Failed to update category: ' . $wpdb->last_error);
        }

        wp_send_json_success(['message' => 'Category updated successfully']);
    }

    public static function delete_expense_category() {
        check_ajax_referer('obn_expense_category_action_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;

        $category_id = intval($_POST['id'] ?? 0);
        if (!$category_id) wp_send_json_error('Invalid category ID');

        $category_table = $wpdb->prefix . 'orabooks_db_expense_category';
        $deleted = $wpdb->delete($category_table, ['id' => $category_id]);

        if ($deleted === false) {
            wp_send_json_error('Failed to delete category: ' . $wpdb->last_error);
        }

        wp_send_json_success('Category deleted successfully');
    }

    public static function toggle_expense_category_status() {
        check_ajax_referer('obn_expense_category_action_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;

        $category_id = intval($_POST['id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);

        if (!$category_id) wp_send_json_error('Invalid category ID');

        $category_table = $wpdb->prefix . 'orabooks_db_expense_category';
        $updated = $wpdb->update($category_table, ['status' => $status], ['id' => $category_id]);

        if ($updated === false) {
            wp_send_json_error('Failed to update status: ' . $wpdb->last_error);
        }

        wp_send_json_success('Status updated successfully');
    }

    public static function get_expense_category() {
        check_ajax_referer('obn_expense_category_action_nonce', 'security');

        $auth = new OBN_Auth();
        if ( ! is_user_logged_in() || ! $auth->can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;

        $category_id = intval($_POST['id'] ?? 0);
        if (!$category_id) wp_send_json_error('Invalid category ID');

        $category_table = $wpdb->prefix . 'orabooks_db_expense_category';
        $category = $wpdb->get_row($wpdb->prepare("SELECT * FROM $category_table WHERE id = %d", $category_id));

        if (!$category) wp_send_json_error('Category not found');

        wp_send_json_success($category);
    }
}

OBN_Expense_Categories::init();
