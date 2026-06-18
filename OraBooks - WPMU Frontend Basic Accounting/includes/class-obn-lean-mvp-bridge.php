<?php
/**
 * Lean MVP integration bridge for Frontend Basic Accounting.
 *
 * Aligns legacy accounting UI with SL-004 org tenancy and SL-013 partner isolation.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OBN_Lean_MVP_Bridge {
    public static function init() {
        add_action('init', [__CLASS__, 'guard_accounting_ajax'], 0);
    }

    public static function is_available() {
        return defined('ORABOOKS_VERSION')
            && class_exists('OraBooks_Database')
            && class_exists('OraBooks_Auth')
            && class_exists('OraBooks_Organization');
    }

    /**
     * Resolve org_id from Lean MVP tenant context, with legacy blog fallback.
     */
    public static function current_org_id() {
        if (self::is_available() && function_exists('orabooks_get_current_org_id')) {
            $org_id = (int) orabooks_get_current_org_id();
            if ($org_id > 0) {
                return $org_id;
            }
        }

        return (int) get_current_blog_id();
    }

    public static function current_organization() {
        if (!self::is_available()) {
            return null;
        }

        $org_id = self::current_org_id();
        if ($org_id <= 0) {
            return null;
        }

        return OraBooks_Organization::get($org_id);
    }

    public static function is_partner_org($org_id = 0) {
        $org = $org_id ? OraBooks_Organization::get($org_id) : self::current_organization();
        return $org && ($org->organization_type ?? '') === 'partner';
    }

    /**
     * SL-013 requireCustomerOrg middleware for accounting surfaces.
     */
    public static function require_customer_org($user_id = 0, $org_id = 0) {
        if (!self::is_available()) {
            return true;
        }

        $user_id = $user_id ?: (function_exists('orabooks_get_current_user_id') ? orabooks_get_current_user_id() : get_current_user_id());
        $org_id = $org_id ?: self::current_org_id();

        if (!$user_id) {
            return new WP_Error('not_logged_in', 'Please log in to continue.');
        }

        if (!$org_id) {
            return new WP_Error('no_org', 'Organization is not set up yet.');
        }

        return OraBooks_Auth::require_customer_org($user_id, $org_id);
    }

    public static function can_access_accounting($user_id = 0) {
        if (!is_user_logged_in() && !$user_id) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        if (self::is_available()) {
            $user_id = $user_id ?: orabooks_get_current_user_id();
            $org_id = self::current_org_id();
            $allowed = self::require_customer_org($user_id, $org_id);
            if (is_wp_error($allowed)) {
                return false;
            }

            if (class_exists('OraBooks_RBAC')) {
                return OraBooks_RBAC::require_permission($user_id, $org_id, 'submit_transaction')
                    || OraBooks_RBAC::require_permission($user_id, $org_id, 'view_reports')
                    || OraBooks_RBAC::require_permission($user_id, $org_id, 'view_financial_reports');
            }

            return true;
        }

        if (function_exists('orabooks_is_feature_enabled')) {
            return orabooks_is_feature_enabled('accounting');
        }

        return false;
    }

    public static function guard_accounting_ajax() {
        if (!wp_doing_ajax()) {
            return;
        }

        $action = sanitize_key($_REQUEST['action'] ?? '');
        if ($action === '' || (strpos($action, 'obn_') !== 0 && strpos($action, 'orabooks_accounting') !== 0)) {
            return;
        }

        $check = self::require_customer_org();
        if (is_wp_error($check)) {
            $status = $check->get_error_code() === 'accounting_isolation' ? 403 : 401;
            wp_send_json([
                'success' => false,
                'message' => $check->get_error_message(),
            ], $status);
        }
    }
}

if (!function_exists('obn_current_org_id')) {
    function obn_current_org_id() {
        return OBN_Lean_MVP_Bridge::current_org_id();
    }
}

OBN_Lean_MVP_Bridge::init();
