<?php
if (!defined('ABSPATH')) exit;

/**
 * OBN_Employees - Accounting-specific employees management
 * Handles CRUD for orabooks_ac_employees table
 */
class OBN_Employees {

    private static $table = 'orabooks_ac_employees';

    public static function init() {
        add_action('wp_ajax_obn_ac_get_employees', [__CLASS__, 'ajax_get_employees']);
        add_action('wp_ajax_obn_ac_add_employee', [__CLASS__, 'ajax_add_employee']);
        add_action('wp_ajax_obn_ac_update_employee', [__CLASS__, 'ajax_update_employee']);
        add_action('wp_ajax_obn_ac_toggle_employee_status', [__CLASS__, 'ajax_toggle_employee_status']);
        add_action('wp_ajax_obn_ac_get_employee', [__CLASS__, 'ajax_get_employee']);
        add_action('wp_ajax_obn_ac_get_roles_for_select', [__CLASS__, 'ajax_get_roles_for_select']);
    }

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . self::$table;
    }

    public static function get_employees($store_id = null) {
        global $wpdb;
        $table = self::table();
        $roles_table = $wpdb->prefix . 'orabooks_ac_roles';
        $sql = "SELECT e.*, r.role_name FROM $table e LEFT JOIN $roles_table r ON e.role_id = r.id WHERE e.status != 2";
        if ($store_id) $sql .= $wpdb->prepare(" AND e.store_id = %d", $store_id);
        $sql .= " ORDER BY e.id DESC";
        return $wpdb->get_results($sql, ARRAY_A);
    }

    public static function get_employee($id) {
        global $wpdb;
        $table = self::table();
        $roles_table = $wpdb->prefix . 'orabooks_ac_roles';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT e.*, r.role_name FROM $table e LEFT JOIN $roles_table r ON e.role_id = r.id WHERE e.id = %d", $id
        ), ARRAY_A);
    }

    public static function generate_employee_code() {
        global $wpdb;
        $table = self::table();
        $prefix = 'AC-EMP';
        $year = date('Y');
        $last = $wpdb->get_var($wpdb->prepare("SELECT employee_code FROM $table WHERE employee_code LIKE %s ORDER BY id DESC LIMIT 1", $prefix . $year . '%'));
        $number = $last ? (int)substr($last, -4) + 1 : 1;
        return $prefix . $year . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    public static function add_employee($data) {
        global $wpdb;
        $emp_code = empty($data['employee_code']) ? self::generate_employee_code() : sanitize_text_field($data['employee_code']);
        $result = $wpdb->insert(self::table(), [
            'store_id'       => isset($data['store_id']) ? intval($data['store_id']) : null,
            'role_id'        => !empty($data['role_id']) ? intval($data['role_id']) : null,
            'wp_user_id'     => !empty($data['wp_user_id']) ? intval($data['wp_user_id']) : null,
            'employee_code'  => $emp_code,
            'first_name'     => sanitize_text_field($data['first_name']),
            'last_name'      => sanitize_text_field($data['last_name'] ?? ''),
            'email'          => sanitize_email($data['email'] ?? ''),
            'mobile'         => sanitize_text_field($data['mobile'] ?? ''),
            'phone'          => sanitize_text_field($data['phone'] ?? ''),
            'address'        => sanitize_textarea_field($data['address'] ?? ''),
            'city'           => sanitize_text_field($data['city'] ?? ''),
            'state'          => sanitize_text_field($data['state'] ?? ''),
            'postcode'       => sanitize_text_field($data['postcode'] ?? ''),
            'country'        => sanitize_text_field($data['country'] ?? ''),
            'hire_date'      => !empty($data['hire_date']) ? date('Y-m-d', strtotime($data['hire_date'])) : null,
            'salary'         => !empty($data['salary']) ? floatval($data['salary']) : null,
            'status'         => isset($data['status']) ? intval($data['status']) : 1,
            'created_by'     => get_current_user_id(),
            'system_ip'      => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'system_name'    => @gethostbyaddr($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'created_at'     => current_time('mysql'),
        ], ['%d','%d','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%f','%d','%d','%s','%s','%s']);
        if ($result === false) return ['success' => false, 'message' => 'Failed to add employee.'];
        return ['success' => true, 'message' => 'Employee added successfully.', 'id' => $wpdb->insert_id];
    }

    public static function update_employee($id, $data) {
        global $wpdb;
        $update = [
            'role_id'    => !empty($data['role_id']) ? intval($data['role_id']) : null,
            'first_name' => sanitize_text_field($data['first_name']),
            'last_name'  => sanitize_text_field($data['last_name'] ?? ''),
            'email'      => sanitize_email($data['email'] ?? ''),
            'mobile'     => sanitize_text_field($data['mobile'] ?? ''),
            'phone'      => sanitize_text_field($data['phone'] ?? ''),
            'address'    => sanitize_textarea_field($data['address'] ?? ''),
            'city'       => sanitize_text_field($data['city'] ?? ''),
            'state'      => sanitize_text_field($data['state'] ?? ''),
            'postcode'   => sanitize_text_field($data['postcode'] ?? ''),
            'country'    => sanitize_text_field($data['country'] ?? ''),
            'hire_date'  => !empty($data['hire_date']) ? date('Y-m-d', strtotime($data['hire_date'])) : null,
            'salary'     => !empty($data['salary']) ? floatval($data['salary']) : null,
            'status'     => isset($data['status']) ? intval($data['status']) : 1,
            'updated_at' => current_time('mysql'),
        ];
        $result = $wpdb->update(self::table(), $update, ['id' => $id]);
        if ($result === false) return ['success' => false, 'message' => 'Failed to update employee.'];
        return ['success' => true, 'message' => 'Employee updated successfully.'];
    }

    public static function toggle_employee_status($id) {
        global $wpdb;
        $emp = self::get_employee($id);
        if (!$emp) return ['success' => false, 'message' => 'Employee not found.'];
        $new_status = $emp['status'] == 1 ? 0 : 1;
        $wpdb->update(self::table(), ['status' => $new_status, 'updated_at' => current_time('mysql')], ['id' => $id], ['%d', '%s'], ['%d']);
        $status_text = $new_status == 1 ? 'activated' : 'deactivated';
        return ['success' => true, 'message' => "Employee {$status_text} successfully.", 'status' => $new_status];
    }

    // --- AJAX ---

    public static function ajax_get_employees() {
        if (!orabooks_can_access_accounting()) wp_die('Access denied');
        wp_send_json_success(self::get_employees());
    }

    public static function ajax_add_employee() {
        if (!orabooks_can_access_accounting()) wp_die('Access denied');
        check_ajax_referer('frontend_ajax_nonce', 'nonce');
        if (empty($_POST['first_name'])) wp_send_json_error(['message' => 'First name is required.']);
        $result = self::add_employee($_POST);
        $result['success'] ? wp_send_json_success(['message' => $result['message'], 'id' => $result['id']]) : wp_send_json_error(['message' => $result['message']]);
    }

    public static function ajax_update_employee() {
        if (!orabooks_can_access_accounting()) wp_die('Access denied');
        check_ajax_referer('frontend_ajax_nonce', 'nonce');
        $id = intval($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error(['message' => 'Employee ID is required.']);
        if (empty($_POST['first_name'])) wp_send_json_error(['message' => 'First name is required.']);
        $result = self::update_employee($id, $_POST);
        $result['success'] ? wp_send_json_success(['message' => $result['message']]) : wp_send_json_error(['message' => $result['message']]);
    }

    public static function ajax_toggle_employee_status() {
        if (!orabooks_can_access_accounting()) wp_die('Access denied');
        check_ajax_referer('frontend_ajax_nonce', 'nonce');
        $id = intval($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error(['message' => 'Employee ID is required.']);
        $result = self::toggle_employee_status($id);
        $result['success'] ? wp_send_json_success(['message' => $result['message'], 'status' => $result['status']]) : wp_send_json_error(['message' => $result['message']]);
    }

    public static function ajax_get_employee() {
        if (!orabooks_can_access_accounting()) wp_die('Access denied');
        $id = intval($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error(['message' => 'Employee ID is required.']);
        $emp = self::get_employee($id);
        $emp ? wp_send_json_success($emp) : wp_send_json_error(['message' => 'Employee not found.']);
    }

    public static function ajax_get_roles_for_select() {
        if (!orabooks_can_access_accounting()) wp_die('Access denied');
        global $wpdb;
        $roles = $wpdb->get_results("SELECT id, role_name FROM " . $wpdb->prefix . "orabooks_ac_roles WHERE status = 1 ORDER BY role_name ASC", ARRAY_A);
        wp_send_json_success($roles);
    }
}

// Auto-init
OBN_Employees::init();
