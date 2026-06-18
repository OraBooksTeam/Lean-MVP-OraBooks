<?php
if (!defined('ABSPATH')) exit;

/**
 * OBN_Accounting_Permissions - Role-based permissions for Accounting module
 * Select Role → Check Accounting Features → Save
 */
class OBN_Accounting_Permissions {

    private static $table = 'orabooks_ac_role_permissions';

    public static function init() {
        add_action('wp_ajax_obn_ac_save_permissions', [__CLASS__, 'save_permissions']);
        add_action('wp_ajax_obn_ac_get_permissions', [__CLASS__, 'get_permissions']);
        add_action('wp_ajax_obn_ac_get_all_assigned_permissions', [__CLASS__, 'get_all_assigned_permissions']);
        add_action('wp_ajax_obn_ac_delete_permissions', [__CLASS__, 'delete_permissions']);
    }

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . self::$table;
    }

    /**
     * Get all roles for the permission form
     */
    public static function get_all_roles() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT id, role_name FROM " . $wpdb->prefix . "orabooks_ac_roles WHERE status = 1 ORDER BY role_name ASC",
            OBJECT
        );
    }

    /**
     * Get sidebar items for accounting module
     */
    public static function get_accounting_sidebar_items() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM " . $wpdb->prefix . "orabooks_db_sidebar WHERE status = 1 AND module = 'accounting' ORDER BY sort_order ASC",
            ARRAY_A
        );
    }

    /**
     * Save role permissions
     */
    public static function save_permissions() {
        check_ajax_referer('frontend_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access.']);
        }

        global $wpdb;
        $table = self::table();
        $role_id = intval($_POST['role_id'] ?? 0);
        $sidebar_ids = isset($_POST['sidebar_ids']) ? $_POST['sidebar_ids'] : [];

        if (!$role_id) {
            wp_send_json_error(['message' => 'Please select a role.']);
        }

        // Normalize: ensure parent IDs are included when children are selected
        $sidebar_ids = self::normalize_sidebar_ids_with_parents($sidebar_ids);
        $sidebar_ids_json = json_encode($sidebar_ids);

        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE role_id = %d", $role_id));

        try {
            if ($existing) {
                $wpdb->update($table, ['sidebar_ids' => $sidebar_ids_json], ['role_id' => $role_id], ['%s'], ['%d']);
            } else {
                $wpdb->insert($table, ['role_id' => $role_id, 'sidebar_ids' => $sidebar_ids_json], ['%d', '%s']);
            }
            wp_send_json_success(['message' => 'Permissions saved successfully.']);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Get permissions for a specific role
     */
    public static function get_permissions() {
        check_ajax_referer('frontend_ajax_nonce', 'nonce');
        $role_id = intval($_POST['role_id'] ?? 0);
        if (!$role_id) wp_send_json_error(['message' => 'Invalid Role ID.']);

        global $wpdb;
        $sidebar_ids_json = $wpdb->get_var($wpdb->prepare("SELECT sidebar_ids FROM " . self::table() . " WHERE role_id = %d", $role_id));
        $sidebar_ids = $sidebar_ids_json ? json_decode($sidebar_ids_json, true) : [];
        wp_send_json_success(['sidebar_ids' => $sidebar_ids]);
    }

    /**
     * Get all assigned permissions (for the list table)
     */
    public static function get_all_assigned_permissions() {
        check_ajax_referer('frontend_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access.']);
        }

        global $wpdb;
        $results = $wpdb->get_results("SELECT * FROM " . self::table());
        $data = [];

        foreach ($results as $row) {
            $role = $wpdb->get_row($wpdb->prepare("SELECT role_name FROM " . $wpdb->prefix . "orabooks_ac_roles WHERE id = %d", $row->role_id));
            $sidebar_ids = json_decode($row->sidebar_ids, true);
            $data[] = [
                'role_id'       => $row->role_id,
                'role_name'     => $role ? $role->role_name : 'Unknown',
                'feature_count' => is_array($sidebar_ids) ? count($sidebar_ids) : 0,
            ];
        }
        wp_send_json_success($data);
    }

    /**
     * Delete permissions for a role
     */
    public static function delete_permissions() {
        check_ajax_referer('frontend_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access.']);
        }

        global $wpdb;
        $role_id = intval($_POST['role_id'] ?? 0);
        if (!$role_id) wp_send_json_error(['message' => 'Invalid Role ID.']);

        $wpdb->delete(self::table(), ['role_id' => $role_id], ['%d']);
        wp_send_json_success(['message' => 'Permissions deleted successfully.']);
    }

    /**
     * Check if current user's role has access to a specific view
     */
    public static function has_view_permission($view_slug) {
        if (empty($view_slug) || $view_slug === 'dashboard') return true;
        if (current_user_can('manage_options')) return true;

        $user_id = get_current_user_id();
        if (!$user_id) return false;

        // Get the user's accounting role from the employees table
        global $wpdb;
        $emp_table = $wpdb->prefix . 'orabooks_ac_employees';
        $role_id = $wpdb->get_var($wpdb->prepare("SELECT role_id FROM $emp_table WHERE wp_user_id = %d AND status = 1 LIMIT 1", $user_id));

        if (!$role_id) return false;

        // Get permitted sidebar IDs for this role
        $sidebar_ids_json = $wpdb->get_var($wpdb->prepare("SELECT sidebar_ids FROM " . self::table() . " WHERE role_id = %d", $role_id));
        if (!$sidebar_ids_json) return false;

        $permitted_ids = json_decode($sidebar_ids_json, true);
        if (!is_array($permitted_ids) || empty($permitted_ids)) return false;

        // Check if the view_slug matches any permitted sidebar item
        $table_sidebar = $wpdb->prefix . 'orabooks_db_sidebar';
        $item_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_sidebar WHERE menu_slug = %s LIMIT 1", $view_slug));
        return $item_id && in_array(intval($item_id), array_map('intval', $permitted_ids));
    }

    /**
     * Get permitted sidebar IDs for the current user (merges per-user + per-role)
     */
    public static function get_user_permitted_ids($user_id) {
        global $wpdb;
        $table_permissions = $wpdb->prefix . 'orabooks_user_permissions';

        // Check per-user permissions first (existing system)
        $sidebar_ids_json = $wpdb->get_var($wpdb->prepare("SELECT sidebar_ids FROM $table_permissions WHERE user_id = %d", $user_id));
        if ($sidebar_ids_json) {
            $ids = json_decode($sidebar_ids_json, true);
            return is_array($ids) ? array_map('intval', $ids) : [];
        }

        // Admin fallback
        if (user_can($user_id, 'manage_options')) return true;

        // Check role-based permissions
        $emp_table = $wpdb->prefix . 'orabooks_ac_employees';
        $role_id = $wpdb->get_var($wpdb->prepare("SELECT role_id FROM $emp_table WHERE wp_user_id = %d AND status = 1 LIMIT 1", $user_id));
        if (!$role_id) return [];

        $perm_table = self::table();
        $perm_json = $wpdb->get_var($wpdb->prepare("SELECT sidebar_ids FROM $perm_table WHERE role_id = %d", $role_id));
        if ($perm_json) {
            $ids = json_decode($perm_json, true);
            return is_array($ids) ? array_map('intval', $ids) : [];
        }

        return [];
    }

    /**
     * Normalize sidebar IDs to include parent IDs
     */
    private static function normalize_sidebar_ids_with_parents($sidebar_ids) {
        global $wpdb;
        $sidebar_ids = array_values(array_unique(array_filter(array_map('intval', (array)$sidebar_ids))));
        if (empty($sidebar_ids)) return [];

        $table_sidebar = $wpdb->prefix . 'orabooks_db_sidebar';
        $id_list = implode(',', $sidebar_ids);
        $parents = $wpdb->get_col("SELECT DISTINCT parent FROM $table_sidebar WHERE id IN ($id_list) AND parent > 0");

        if (!empty($parents)) {
            $sidebar_ids = array_values(array_unique(array_merge($sidebar_ids, array_map('intval', $parents))));
        }
        sort($sidebar_ids);
        return $sidebar_ids;
    }
}

// Auto-init
OBN_Accounting_Permissions::init();
