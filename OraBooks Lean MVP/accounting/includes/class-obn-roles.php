<?php
if (!defined('ABSPATH')) exit;

/**
 * OBN_Roles - Accounting-specific roles management
 * Handles CRUD for orabooks_db_roles table
 */
class OBN_Roles {

    private static $table = 'orabooks_db_roles';

    public static function init() {
        add_action('wp_ajax_obn_ac_get_roles', [__CLASS__, 'ajax_get_roles']);
        add_action('wp_ajax_obn_ac_add_role', [__CLASS__, 'ajax_add_role']);
        add_action('wp_ajax_obn_ac_update_role', [__CLASS__, 'ajax_update_role']);
        add_action('wp_ajax_obn_ac_toggle_role_status', [__CLASS__, 'ajax_toggle_role_status']);
        add_action('wp_ajax_obn_ac_get_role', [__CLASS__, 'ajax_get_role']);
    }

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . self::$table;
    }

    public static function get_roles($store_id = null) {
        global $wpdb;
        $table = self::table();
        $sql = "SELECT * FROM $table WHERE status != 2";
        if ($store_id) {
            $sql .= $wpdb->prepare(" AND store_id = %d", $store_id);
        }
        $sql .= " ORDER BY id DESC";
        return $wpdb->get_results($sql, ARRAY_A);
    }

    public static function get_role($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table() . " WHERE id = %d", $id), ARRAY_A);
    }

    public static function add_role($data) {
        global $wpdb;
        $result = $wpdb->insert(self::table(), [
            'store_id'    => isset($data['store_id']) ? intval($data['store_id']) : null,
            'role_name'   => sanitize_text_field($data['role_name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'status'      => isset($data['status']) ? intval($data['status']) : 1,
            'created_by'  => get_current_user_id(),
            'created_at'  => current_time('mysql'),
        ], ['%d', '%s', '%s', '%d', '%d', '%s']);
        if ($result === false) {
            return ['success' => false, 'message' => 'Failed to add role.'];
        }
        return ['success' => true, 'message' => 'Role added successfully.', 'id' => $wpdb->insert_id];
    }

    public static function update_role($id, $data) {
        global $wpdb;
        $result = $wpdb->update(self::table(), [
            'role_name'   => sanitize_text_field($data['role_name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'status'      => isset($data['status']) ? intval($data['status']) : 1,
            'updated_at'  => current_time('mysql'),
        ], ['id' => $id], ['%s', '%s', '%d', '%s'], ['%d']);
        if ($result === false) {
            return ['success' => false, 'message' => 'Failed to update role.'];
        }
        return ['success' => true, 'message' => 'Role updated successfully.'];
    }

    public static function toggle_role_status($id) {
        global $wpdb;
        $role = self::get_role($id);
        if (!$role) return ['success' => false, 'message' => 'Role not found.'];
        $new_status = $role['status'] == 1 ? 0 : 1;
        $wpdb->update(self::table(), ['status' => $new_status, 'updated_at' => current_time('mysql')], ['id' => $id], ['%d', '%s'], ['%d']);
        $status_text = $new_status == 1 ? 'activated' : 'deactivated';
        return ['success' => true, 'message' => "Role {$status_text} successfully.", 'status' => $new_status];
    }

    // --- AJAX Handlers ---

    public static function ajax_get_roles() {
        if (!orabooks_can_access_accounting()) wp_die('Access denied');
        $roles = self::get_roles();
        wp_send_json_success($roles);
    }

    public static function ajax_add_role() {
        if (!orabooks_can_access_accounting()) wp_die('Access denied');
        check_ajax_referer('frontend_ajax_nonce', 'nonce');
        if (empty($_POST['role_name'])) wp_send_json_error(['message' => 'Role name is required.']);
        $result = self::add_role($_POST);
        $result['success'] ? wp_send_json_success(['message' => $result['message'], 'id' => $result['id']]) : wp_send_json_error(['message' => $result['message']]);
    }

    public static function ajax_update_role() {
        if (!orabooks_can_access_accounting()) wp_die('Access denied');
        check_ajax_referer('frontend_ajax_nonce', 'nonce');
        $id = intval($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error(['message' => 'Role ID is required.']);
        if (empty($_POST['role_name'])) wp_send_json_error(['message' => 'Role name is required.']);
        $result = self::update_role($id, $_POST);
        $result['success'] ? wp_send_json_success(['message' => $result['message']]) : wp_send_json_error(['message' => $result['message']]);
    }

    public static function ajax_toggle_role_status() {
        if (!orabooks_can_access_accounting()) wp_die('Access denied');
        check_ajax_referer('frontend_ajax_nonce', 'nonce');
        $id = intval($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error(['message' => 'Role ID is required.']);
        $result = self::toggle_role_status($id);
        $result['success'] ? wp_send_json_success(['message' => $result['message'], 'status' => $result['status']]) : wp_send_json_error(['message' => $result['message']]);
    }

    public static function ajax_get_role() {
        if (!orabooks_can_access_accounting()) wp_die('Access denied');
        $id = intval($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error(['message' => 'Role ID is required.']);
        $role = self::get_role($id);
        $role ? wp_send_json_success($role) : wp_send_json_error(['message' => 'Role not found.']);
    }
}

// Auto-init
OBN_Roles::init();
