<?php
/**
 * Master Coupons AJAX Handler Class
 * 
 * Manages CRUD operations for master discount coupons in Frontend-Basic-Accounting plugin
 * Uses orabooks_db_coupons table (occasion/master coupons)
 */

if (!defined('ABSPATH')) {
    exit;
}

class OBN_Coupons
{

    public static function init()
    {
        // AJAX endpoints
        add_action('wp_ajax_obn_insert_coupon', [self::class, 'insert_coupon']);
        add_action('wp_ajax_nopriv_obn_insert_coupon', [self::class, 'insert_coupon']);

        add_action('wp_ajax_obn_update_coupon', [self::class, 'update_coupon']);
        add_action('wp_ajax_nopriv_obn_update_coupon', [self::class, 'update_coupon']);

        add_action('wp_ajax_obn_delete_coupon', [self::class, 'delete_coupon']);
        add_action('wp_ajax_nopriv_obn_delete_coupon', [self::class, 'delete_coupon']);

        add_action('wp_ajax_obn_search_coupons_master', [self::class, 'search_coupons_master']);
        add_action('wp_ajax_nopriv_obn_search_coupons_master', [self::class, 'search_coupons_master']);

        add_action('wp_ajax_obn_toggle_coupon_status', [self::class, 'toggle_coupon_status']);
        add_action('wp_ajax_nopriv_obn_toggle_coupon_status', [self::class, 'toggle_coupon_status']);

        add_action('wp_ajax_obn_get_coupon', [self::class, 'get_coupon']);
        add_action('wp_ajax_nopriv_obn_get_coupon', [self::class, 'get_coupon']);

        // Customer Coupon Actions
        add_action('wp_ajax_obn_insert_customer_coupon', [self::class, 'insert_customer_coupon']);
        add_action('wp_ajax_nopriv_obn_insert_customer_coupon', [self::class, 'insert_customer_coupon']);

        add_action('wp_ajax_obn_search_customer_coupons', [self::class, 'search_customer_coupons']);
        add_action('wp_ajax_nopriv_obn_search_customer_coupons', [self::class, 'search_customer_coupons']);

        add_action('wp_ajax_obn_delete_customer_coupon', [self::class, 'delete_customer_coupon']);
        add_action('wp_ajax_nopriv_obn_delete_customer_coupon', [self::class, 'delete_customer_coupon']);

        add_action('wp_ajax_obn_get_customer_coupon', [self::class, 'get_customer_coupon']);
        add_action('wp_ajax_nopriv_obn_get_customer_coupon', [self::class, 'get_customer_coupon']);

        add_action('wp_ajax_obn_update_customer_coupon', [self::class, 'update_customer_coupon']);
        add_action('wp_ajax_nopriv_obn_update_customer_coupon', [self::class, 'update_customer_coupon']);

        add_action('wp_ajax_obn_toggle_customer_coupon_status', [self::class, 'toggle_customer_coupon_status']);
        add_action('wp_ajax_nopriv_obn_toggle_customer_coupon_status', [self::class, 'toggle_customer_coupon_status']);
    }

    /**
     * Insert Master Coupon
     */
    public static function insert_coupon()
    {
        check_ajax_referer('obn_coupon_action_nonce', 'security');

        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting()) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;

        $occasion_name = sanitize_text_field($_POST['occasion_name'] ?? '');
        $expire_input = sanitize_text_field($_POST['expire_date'] ?? '');
        $coupon_value = floatval($_POST['coupon_value'] ?? 0);
        $coupon_type = sanitize_text_field($_POST['coupon_type'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');

        if (!$occasion_name || !$expire_input || $coupon_value <= 0 || !$coupon_type) {
            wp_send_json_error('All required fields must be filled');
            return;
        }

        // Parse date (dd-mm-yy to Y-m-d)
        $expire_date = self::parse_date($expire_input);
        if (!$expire_date) {
            wp_send_json_error('Invalid expiry date format');
            return;
        }

        $coupons_table = $wpdb->prefix . 'orabooks_db_coupons';
        $insert_data = [
            'name' => $occasion_name,
            'value' => $coupon_value,
            'type' => $coupon_type,
            'expire_date' => $expire_date,
            'description' => $description,
            'status' => 1,
            'created_date' => date('Y-m-d'),
            'created_time' => date('H:i:s'),
            'system_name' => gethostname(),
            'system_ip' => $_SERVER['REMOTE_ADDR'],
        ];

        $inserted = $wpdb->insert($coupons_table, $insert_data);

        if (!$inserted) {
            wp_send_json_error('Failed to insert coupon: ' . $wpdb->last_error);
            return;
        }

        $coupon_id = $wpdb->insert_id;

        wp_send_json_success([
            'message' => 'Coupon saved successfully',
            'coupon_id' => $coupon_id,
        ]);
    }

    /**
     * Update Master Coupon
     */
    public static function update_coupon()
    {
        check_ajax_referer('obn_coupon_action_nonce', 'security');

        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting()) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;

        $coupon_id = intval($_POST['id'] ?? 0);
        $occasion_name = sanitize_text_field($_POST['occasion_name'] ?? '');
        $expire_input = sanitize_text_field($_POST['expire_date'] ?? '');
        $coupon_value = floatval($_POST['coupon_value'] ?? 0);
        $coupon_type = sanitize_text_field($_POST['coupon_type'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');

        if (!$coupon_id || !$occasion_name || !$expire_input || $coupon_value <= 0 || !$coupon_type) {
            wp_send_json_error('All required fields must be filled');
            return;
        }

        // Parse date
        $expire_date = self::parse_date($expire_input);
        if (!$expire_date) {
            wp_send_json_error('Invalid expiry date format');
            return;
        }

        $coupons_table = $wpdb->prefix . 'orabooks_db_coupons';
        $update_data = [
            'name' => $occasion_name,
            'value' => $coupon_value,
            'type' => $coupon_type,
            'expire_date' => $expire_date,
            'description' => $description,
        ];

        $updated = $wpdb->update($coupons_table, $update_data, ['id' => $coupon_id]);

        if ($updated === false) {
            wp_send_json_error('Failed to update coupon: ' . $wpdb->last_error);
            return;
        }

        wp_send_json_success([
            'message' => 'Coupon updated successfully',
            'coupon_id' => $coupon_id,
        ]);
    }

    /**
     * Delete Master Coupon
     */
    public static function delete_coupon()
    {
        check_ajax_referer('obn_coupon_action_nonce', 'security');

        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting()) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;

        $coupon_id = intval($_POST['id'] ?? 0);
        if (!$coupon_id) {
            wp_send_json_error('Invalid coupon ID');
            return;
        }

        $coupons_table = $wpdb->prefix . 'orabooks_db_coupons';
        $deleted = $wpdb->delete($coupons_table, ['id' => $coupon_id]);

        if ($deleted === false) {
            wp_send_json_error('Failed to delete coupon: ' . $wpdb->last_error);
            return;
        }

        wp_send_json_success('Coupon deleted successfully');
    }

    /**
     * Search/List Master Coupons
     */
    public static function search_coupons_master()
    {
        check_ajax_referer('obn_coupon_action_nonce', 'security');

        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting()) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;

        $search_term = sanitize_text_field($_POST['search_term'] ?? '');
        $filter_type = sanitize_text_field($_POST['filter_type'] ?? '');
        $filter_status = isset($_POST['filter_status']) ? intval($_POST['filter_status']) : '';

        $coupons_table = $wpdb->prefix . 'orabooks_db_coupons';
        $query = "SELECT * FROM $coupons_table WHERE 1=1";

        if ($search_term) {
            $query .= $wpdb->prepare(" AND (name LIKE %s OR description LIKE %s)", '%' . $search_term . '%', '%' . $search_term . '%');
        }
        if ($filter_type) {
            $query .= $wpdb->prepare(" AND type = %s", $filter_type);
        }
        if ($filter_status !== '') {
            $query .= $wpdb->prepare(" AND status = %d", $filter_status);
        }

        $query .= " ORDER BY id DESC";
        $coupons = $wpdb->get_results($query);

        if (empty($coupons)) {
            echo '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No coupons found</td></tr>';
            wp_die();
        }

        $nonce = wp_create_nonce('obn_coupon_action_nonce');
        foreach ($coupons as $coupon) {
            $expire_date = date('d M Y', strtotime($coupon->expire_date));
            $status_badge = $coupon->status ? '<span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded">Active</span>' : '<span class="px-3 py-1 bg-gray-100 text-gray-800 text-xs font-semibold rounded">Inactive</span>';
            $type_badge = $coupon->type === 'Percentage' ? '📊 ' . $coupon->type : '💰 ' . $coupon->type;

            echo '<tr data-id="' . esc_attr($coupon->id) . '" class="hover:bg-gray-50 border-b">
                <td data-col="0" class="px-4 py-3">' . esc_html($coupon->id) . '</td>
                <td data-col="1" class="px-4 py-3 font-medium">' . esc_html($coupon->name) . '</td>
                <td data-col="2" class="px-4 py-3">' . esc_html($expire_date) . '</td>
                <td data-col="3" class="px-4 py-3">' . esc_html(number_format($coupon->value, 2)) . '</td>
                <td data-col="4" class="px-4 py-3">' . $type_badge . '</td>
                <td data-col="5" class="px-4 py-3 text-center">' . $status_badge . '</td>
                <td data-col="6" class="px-4 py-3 no-export flex gap-2 justify-end">
                    <button class="obn-coupon-edit px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium" data-id="' . esc_attr($coupon->id) . '" data-nonce="' . esc_attr($nonce) . '">Edit</button>
                    <button class="obn-coupon-delete px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium" data-id="' . esc_attr($coupon->id) . '" data-nonce="' . esc_attr($nonce) . '">Delete</button>
                </td>
            </tr>';
        }
        wp_die();
    }

    /**
     * Toggle Coupon Status
     */
    public static function toggle_coupon_status()
    {
        check_ajax_referer('obn_coupon_action_nonce', 'security');

        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting()) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;

        $coupon_id = intval($_POST['id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);

        if (!$coupon_id) {
            wp_send_json_error('Invalid coupon ID');
            return;
        }

        $coupons_table = $wpdb->prefix . 'orabooks_db_coupons';
        $updated = $wpdb->update($coupons_table, ['status' => $status], ['id' => $coupon_id]);

        if ($updated === false) {
            wp_send_json_error('Failed to update status');
            return;
        }

        wp_send_json_success('Status updated successfully');
    }

    /**
     * Get Single Coupon (for editing)
     */
    public static function get_coupon()
    {
        check_ajax_referer('obn_coupon_action_nonce', 'security');

        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting()) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;

        $coupon_id = intval($_POST['id'] ?? 0);
        if (!$coupon_id) {
            wp_send_json_error('Invalid coupon ID');
            return;
        }

        $coupons_table = $wpdb->prefix . 'orabooks_db_coupons';
        $coupon = $wpdb->get_row($wpdb->prepare("SELECT * FROM $coupons_table WHERE id = %d", $coupon_id));

        if (!$coupon) {
            wp_send_json_error('Coupon not found');
            return;
        }

        wp_send_json_success($coupon);
    }

    /**
     * Insert Customer Coupon
     */
    public static function insert_customer_coupon()
    {
        check_ajax_referer('obn_coupon_action_nonce', 'security');

        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting()) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;

        $customer_id = intval($_POST['customer_id'] ?? 0);
        $coupon_id = intval($_POST['coupon_id'] ?? 0);
        $coupon_code = sanitize_text_field($_POST['coupon_code'] ?? '');
        $expire_input = sanitize_text_field($_POST['expire_date'] ?? '');
        $coupon_value = floatval($_POST['coupon_value'] ?? 0);
        $coupon_type = sanitize_text_field($_POST['coupon_type'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');

        if ($customer_id <= 0 || $coupon_id <= 0 || $coupon_code === '' || $expire_input === '' || $coupon_value <= 0 || $coupon_type === '') {
            wp_send_json_error('All required fields must be provided');
            return;
        }

        // Validate master coupon reference
        $master_table = $wpdb->prefix . 'orabooks_db_coupons';
        $master_coupon = $wpdb->get_row($wpdb->prepare("SELECT id, name FROM $master_table WHERE id = %d", $coupon_id));
        if (!$master_coupon) {
            wp_send_json_error('Selected occasion/coupon does not exist');
            return;
        }

        // Parse expiry date
        $expire_date = self::parse_date($expire_input);
        if (!$expire_date) {
            wp_send_json_error('Invalid expiry date format');
            return;
        }

        $table = $wpdb->prefix . 'orabooks_db_customer_coupons';

        // Check duplicate active code for same customer
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE customer_id = %d AND code = %s",
            $customer_id,
            $coupon_code
        ));
        if ($existing) {
            wp_send_json_error('This coupon code is already assigned to this customer');
            return;
        }

        $system_ip = sanitize_text_field(getHostByName(getHostName()));
        $system_name = sanitize_text_field(gethostname());

        $data = [
            'store_id' => 1, // Default store ID
            'code' => $coupon_code,
            'name' => $master_coupon->name,
            'description' => $description,
            'value' => $coupon_value,
            'type' => $coupon_type,
            'expire_date' => $expire_date,
            'status' => 1,
            'created_by' => get_current_user_id(),
            'created_date' => current_time('Y-m-d'),
            'created_time' => current_time('H:i:s'),
            'system_name' => $system_name,
            'system_ip' => $system_ip,
            'customer_id' => $customer_id,
            'coupon_id' => $coupon_id,
        ];

        $inserted = $wpdb->insert($table, $data);

        if (!$inserted) {
            wp_send_json_error('Failed to save customer coupon: ' . $wpdb->last_error);
            return;
        }

        wp_send_json_success(['message' => 'Customer coupon created successfully', 'id' => $wpdb->insert_id]);
    }

    /**
     * Search Customer Coupons
     */
    public static function search_customer_coupons()
    {
        check_ajax_referer('obn_coupon_action_nonce', 'security');

        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting()) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;

        $cc_table = $wpdb->prefix . 'orabooks_db_customer_coupons';
        $c_table = $wpdb->prefix . 'orabooks_db_customers';
        $cp_table = $wpdb->prefix . 'orabooks_db_coupons';

        $search = sanitize_text_field($_POST['search_term'] ?? '');
        $customer = intval($_POST['filter_customer'] ?? 0);
        $type = sanitize_text_field($_POST['filter_type'] ?? '');
        $status = isset($_POST['filter_status']) ? $_POST['filter_status'] : '';

        $sql = "SELECT cc.*, cu.customer_name, cp.name as occasion_name 
                FROM $cc_table cc
                LEFT JOIN $c_table cu ON cc.customer_id = cu.id
                LEFT JOIN $cp_table cp ON cc.coupon_id = cp.id
                WHERE 1=1";

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $sql .= $wpdb->prepare(" AND (cc.code LIKE %s OR cc.description LIKE %s OR cu.customer_name LIKE %s OR cp.name LIKE %s)", $like, $like, $like, $like);
        }

        if ($customer > 0) {
            $sql .= $wpdb->prepare(" AND cc.customer_id = %d", $customer);
        }

        if ($type !== '') {
            $sql .= $wpdb->prepare(" AND cc.type = %s", $type);
        }

        if ($status !== '') {
            $sql .= $wpdb->prepare(" AND cc.status = %d", intval($status));
        }

        $sql .= " ORDER BY cc.id DESC";

        $rows = $wpdb->get_results($sql);
        $nonce = wp_create_nonce('obn_coupon_action_nonce');

        if ($rows) {
            foreach ($rows as $row) {
                $status_checked = ($row->status == 1) ? 'checked' : '';
                $expire_class = $row->expire_date && strtotime($row->expire_date) < time() ? 'text-red-500' : 'text-green-600';
                $type_badge = $row->type === 'Percentage'
                    ? '<span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">' . esc_html($row->type) . '</span>'
                    : '<span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">' . esc_html($row->type) . '</span>';

                $expire_display = $row->expire_date ? date('d-m-Y', strtotime($row->expire_date)) : '-';

                echo "<tr class='hover:bg-gray-50 transition-colors'>
                    <td data-col='0' class='px-4 py-3 text-sm font-medium text-gray-900'>" . esc_html($row->customer_name ?? '-') . "</td>
                    <td data-col='1' class='px-4 py-3 text-sm text-gray-700'>" . esc_html($row->occasion_name ?? $row->name ?? '-') . "</td>
                    <td data-col='2' class='px-4 py-3 text-sm font-mono text-indigo-600'>" . esc_html($row->code) . "</td>
                    <td data-col='3' class='px-4 py-3 text-sm {$expire_class}'>" . esc_html($expire_display) . "</td>
                    <td data-col='4' class='px-4 py-3 text-sm font-semibold text-gray-900'>" . number_format($row->value ?? 0, 2) . "</td>
                    <td data-col='5' class='px-4 py-3 text-sm'>{$type_badge}</td>
                    <td data-col='6' class='px-4 py-3 text-center'>
                        <label class='relative inline-flex items-center cursor-pointer'>
                            <input type='checkbox' class='obn-toggle-customer-coupon-status sr-only peer' data-id='" . esc_attr($row->id) . "' " . $status_checked . " data-nonce='" . esc_attr($nonce) . "'>
                            <div class='w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[\"\"] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600'></div>
                        </label>
                    </td>
                    <td data-col='7' class='px-4 py-3 no-export'>
                        <div class='flex gap-2 justify-end'>
                            <button class='obn-customer-coupon-edit px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium transition' data-id='" . esc_attr($row->id) . "' data-nonce='" . esc_attr($nonce) . "'>Edit</button>
                            <button class='obn-customer-coupon-delete px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition' data-id='" . esc_attr($row->id) . "' data-nonce='" . esc_attr($nonce) . "'>Delete</button>
                        </div>
                    </td>
                </tr>";
            }
        } else {
            echo "<tr><td colspan='8' class='px-4 py-8 text-center text-gray-500'>No customer coupons found</td></tr>";
        }
        wp_die();
    }

    /**
     * Delete Customer Coupon
     */
    public static function delete_customer_coupon()
    {
        check_ajax_referer('obn_coupon_action_nonce', 'security');

        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting()) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $table = $wpdb->prefix . 'orabooks_db_customer_coupons';

        $deleted = $wpdb->delete($table, ['id' => $id]);

        if ($deleted) {
            wp_send_json_success('Customer coupon deleted successfully');
        } else {
            wp_send_json_error('Failed to delete coupon');
        }
    }

    /**
     * Get Customer Coupon (for edit)
     */
    public static function get_customer_coupon()
    {
        check_ajax_referer('obn_coupon_action_nonce', 'security');

        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting()) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $table = $wpdb->prefix . 'orabooks_db_customer_coupons';

        $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if ($data) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error('Coupon not found');
        }
    }

    /**
     * Update Customer Coupon
     */
    public static function update_customer_coupon()
    {
        check_ajax_referer('obn_coupon_action_nonce', 'security');

        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting()) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;

        $id = intval($_POST['id'] ?? 0);
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $coupon_code = sanitize_text_field($_POST['coupon_code'] ?? '');
        $expire_input = sanitize_text_field($_POST['expire_date'] ?? '');
        $coupon_value = floatval($_POST['coupon_value'] ?? 0);
        $coupon_type = sanitize_text_field($_POST['coupon_type'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');

        if (!$id || $customer_id <= 0 || $coupon_code === '' || $expire_input === '' || $coupon_value <= 0 || $coupon_type === '') {
            wp_send_json_error('All required fields must be provided');
            return;
        }

        // Parse expiry date
        $expire_date = self::parse_date($expire_input);
        if (!$expire_date) {
            wp_send_json_error('Invalid expiry date format');
            return;
        }

        $table = $wpdb->prefix . 'orabooks_db_customer_coupons';

        // Check duplicate code (excluding self)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE customer_id = %d AND code = %s AND id != %d",
            $customer_id,
            $coupon_code,
            $id
        ));
        if ($existing) {
            wp_send_json_error('This coupon code is already assigned to this customer');
            return;
        }

        $data = [
            'customer_id' => $customer_id,
            'code' => $coupon_code,
            'value' => $coupon_value,
            'type' => $coupon_type,
            'expire_date' => $expire_date,
            'description' => $description
        ];

        $updated = $wpdb->update($table, $data, ['id' => $id]);

        if ($updated !== false) {
            wp_send_json_success('Customer coupon updated successfully');
        } else {
            wp_send_json_error('Failed to update coupon');
        }
    }

    /**
     * Toggle Customer Coupon Status
     */
    public static function toggle_customer_coupon_status()
    {
        check_ajax_referer('obn_coupon_action_nonce', 'security');

        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting()) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);
        $table = $wpdb->prefix . 'orabooks_db_customer_coupons';

        $updated = $wpdb->update($table, ['status' => $status], ['id' => $id]);

        if ($updated !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to update status');
        }
    }

    /**
     * Parse date in dd-mm-yy or Y-m-d format to Y-m-d
     */
    private static function parse_date($date_str)
    {
        $date_obj = DateTime::createFromFormat('d-m-Y', $date_str);
        if ($date_obj instanceof DateTime) {
            return $date_obj->format('Y-m-d');
        }

        $date_obj_alt = DateTime::createFromFormat('Y-m-d', $date_str);
        if ($date_obj_alt instanceof DateTime) {
            return $date_obj_alt->format('Y-m-d');
        }

        return false;
    }
}

// Initialize hooks
OBN_Coupons::init();
