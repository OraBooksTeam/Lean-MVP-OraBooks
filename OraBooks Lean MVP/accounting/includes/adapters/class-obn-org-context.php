<?php
/**
 * Org context helpers for legacy accounting tables (store_id / organization_id).
 */

if (!defined('ABSPATH')) {
    exit;
}

class OBN_Org_Context {
    public static function store_id() {
        return obn_current_org_id();
    }

    public static function org_id() {
        return obn_current_org_id();
    }

    public static function ajax_url() {
        return admin_url('admin-ajax.php');
    }

    /**
     * Standard guard for accounting AJAX/HTML handlers.
     */
    public static function require_accounting_access($permission = 'view_reports') {
        if (!is_user_logged_in()) {
            return new WP_Error('not_logged_in', 'Please log in to continue.');
        }

        if (class_exists('OBN_Lean_MVP_Bridge')) {
            $check = OBN_Lean_MVP_Bridge::require_customer_org();
            if (is_wp_error($check)) {
                return $check;
            }

            if (!current_user_can('manage_options') && class_exists('OraBooks_RBAC')) {
                $user_id = function_exists('orabooks_get_current_user_id')
                    ? orabooks_get_current_user_id()
                    : get_current_user_id();
                if (!OraBooks_RBAC::require_permission($user_id, self::org_id(), $permission)) {
                    return new WP_Error('permission_denied', 'Permission denied.');
                }
            }
        } elseif (!(new OBN_Auth())->can_access_accounting()) {
            return new WP_Error('access_denied', 'Access denied.');
        }

        return true;
    }

    public static function require_accounting_access_or_die($format = 'json', $permission = 'view_reports') {
        $check = self::require_accounting_access($permission);
        if (is_wp_error($check)) {
            $status = $check->get_error_code() === 'accounting_isolation' ? 403 : 401;
            if ($format === 'html') {
                echo '<tr><td colspan="8" class="px-6 py-10 text-center text-red-500">' .
                    esc_html($check->get_error_message()) . '</td></tr>';
                wp_die();
            }
            wp_send_json(['success' => false, 'message' => $check->get_error_message()], $status);
        }
        return true;
    }

    /**
     * SQL WHERE fragment for legacy tables using store_id and/or organization_id.
     */
    public static function legacy_org_where($alias = '') {
        global $wpdb;
        $org_id = self::org_id();
        $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

        return $wpdb->prepare(
            "({$prefix}store_id = %d OR {$prefix}organization_id = %d)",
            $org_id,
            $org_id
        );
    }

    public static function legacy_org_and($alias = '') {
        return ' AND ' . self::legacy_org_where($alias);
    }
}

if (!function_exists('obn_store_id')) {
    function obn_store_id() {
        return OBN_Org_Context::store_id();
    }
}

if (!function_exists('obn_ajax_admin_url')) {
    function obn_ajax_admin_url() {
        return OBN_Org_Context::ajax_url();
    }
}
