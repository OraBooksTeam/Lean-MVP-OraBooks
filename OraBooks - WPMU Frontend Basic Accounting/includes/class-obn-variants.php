<?php
/**
 * Variants AJAX handlers for the Accounting module
 */
if (!defined('ABSPATH'))
    exit;

class OBN_Variants
{

    public static function init()
    {
        add_action('wp_ajax_obn_save_variant', [__CLASS__, 'handle_save_variant']);
        add_action('wp_ajax_nopriv_obn_save_variant', [__CLASS__, 'handle_save_variant']);
        add_action('wp_ajax_obn_delete_variant', [__CLASS__, 'handle_delete_variant']);
        add_action('wp_ajax_nopriv_obn_delete_variant', [__CLASS__, 'handle_delete_variant']);
        add_action('wp_ajax_obn_update_variant_status', [__CLASS__, 'handle_update_variant_status']);
        add_action('wp_ajax_nopriv_obn_update_variant_status', [__CLASS__, 'handle_update_variant_status']);
        add_action('wp_ajax_obn_generate_variant_code', [__CLASS__, 'handle_generate_variant_code']);
        add_action('wp_ajax_nopriv_obn_generate_variant_code', [__CLASS__, 'handle_generate_variant_code']);

    }

    public static function handle_save_variant()
    {
        check_ajax_referer('obn_auth_nonce', 'security');
        if (!is_user_logged_in())
            wp_die('Unauthorized');

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_variants';
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        $data = [
            'variant_name' => sanitize_text_field($_POST['variant_name']),
            'variant_code' => sanitize_text_field($_POST['variant_code']),
            'description' => sanitize_textarea_field($_POST['description']),
            'status' => 1,
        ];

        if ($id > 0) {
            $result = $wpdb->update($table, $data, ['id' => $id]);
            if ($result !== false) {
                wp_send_json_success(['message' => 'Variant updated successfully.', 'id' => $id]);
            } else {
                wp_send_json_error('Failed to update variant.');
            }
        } else {
            $result = $wpdb->insert($table, $data);
            if ($result) {
                wp_send_json_success(['message' => 'Variant added successfully.', 'id' => $wpdb->insert_id]);
            } else {
                wp_send_json_error('Failed to add variant.');
            }
        }
    }

    public static function handle_delete_variant()
    {
        check_ajax_referer('obn_auth_nonce', 'security');
        if (!is_user_logged_in())
            wp_die('Unauthorized');

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_variants';
        $id = intval($_POST['id']);

        $result = $wpdb->delete($table, ['id' => $id]);
        if ($result) {
            wp_send_json_success(['message' => 'Variant deleted successfully.']);
        } else {
            wp_send_json_error('Failed to delete variant.');
        }
    }

    public static function handle_update_variant_status()
    {
        check_ajax_referer('obn_auth_nonce', 'security');
        if (!is_user_logged_in())
            wp_die('Unauthorized');

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_variants';
        $id = intval($_POST['id']);
        $status = intval($_POST['status']);

        $wpdb->update($table, ['status' => $status], ['id' => $id]);
        wp_send_json_success(['message' => 'Status updated.']);
    }


    public static function handle_generate_variant_code()
    {
        check_ajax_referer('obn_auth_nonce', 'security');
        if (!is_user_logged_in())
            wp_die('Unauthorized');

        global $wpdb;
        $variants_table = $wpdb->prefix . 'orabooks_db_variants';
        $max_code = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(variant_code) FROM {$variants_table} WHERE variant_code LIKE %s",
            $wpdb->esc_like('VAR-%') . '%'
        ));
        if ($max_code) {
            if (preg_match('/(.*?)(\d+)$/', $max_code, $matches)) {
                $prefix = $matches[1];
                $number = $matches[2];
                $new_number = str_pad(intval($number) + 1, strlen($number), '0', STR_PAD_LEFT);
                $code = $prefix . $new_number;
            } else {
                $code = 'VAR-000001';
            }
        } else {
            $code = 'VAR-000001';
        }
        wp_send_json_success(['code' => $code]);
    }


}
