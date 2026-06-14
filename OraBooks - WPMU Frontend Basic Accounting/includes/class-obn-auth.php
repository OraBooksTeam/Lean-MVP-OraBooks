<?php

class OBN_Auth {

	public function __construct() {
		// No separate login/register handlers needed as we use main WP Auth
	}

	public function handle_register() {
        // Deprecated - Use main site registration
	}

	public function handle_login() {
        // Deprecated - Use main site login
	}

	public function handle_logout() {
		wp_logout();
		wp_redirect( home_url() ); // Or login page
		exit;
	}

    /**
     * SL-013/SL-003: Check if the current user can access accounting features.
     *
     * Enforces:
     * - SL-013 §5.14: Partner orgs blocked (requireCustomerOrg)
     * - SL-003: Role-based permission check (owner/admin/staff via orabooks_check_permission)
     * - Legacy: WordPress manage_options capability
     * - Legacy: orabooks_is_feature_enabled check
     */
    public function can_access_accounting() {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $user_id = get_current_user_id();

        // ── SL-003 RBAC Check ────────────────────────────────────────────
        // Use the new RBAC system if available
        if ( class_exists('OraBooks_RBAC') ) {
            // Get the user's org_id from user_meta
            $org_id = get_user_meta($user_id, 'org_id', true);

            if ( ! empty( $org_id ) ) {
                // SL-013 §5.14: Partner orgs cannot access accounting
                if ( class_exists('OraBooks_Organizations') ) {
                    $require_customer = OraBooks_Organizations::get_instance()->requireCustomerOrg($org_id);
                    if ( is_wp_error($require_customer) ) {
                        return false;
                    }
                }

                // SL-003: Check role-based permission for accounting access
                // Use 'view_reports' as a broad accounting permission since this
                // is the gateway to the entire accounting module, not just invoicing.
                return OraBooks_RBAC::get_instance()->require_permission(
                    $user_id,
                    $org_id,
                    'view_reports'
                );
            }
        }

        // ── Legacy Checks (fallback for users without org_id) ────────────
        if ( current_user_can('manage_options') ) {
            return true;
        }

        // Check if Orabooks Membership plugin is active and feature is enabled
        if ( function_exists('orabooks_is_feature_enabled') ) {
            return orabooks_is_feature_enabled('accounting');
        }

        return false;
    }

    /**
     * SL-013 §5.14: Alias for can_access_accounting with explicit org_id.
     * Used by accounting API routes to enforce partner restriction.
     *
     * @param int $org_id Organization ID
     * @return bool True if accounting is allowed for this org
     */
    public function org_can_access_accounting($org_id) {
        if ( class_exists('OraBooks_Organizations') ) {
            $require_customer = OraBooks_Organizations::get_instance()->requireCustomerOrg($org_id);
            if ( is_wp_error($require_customer) ) {
                return false;
            }
        }
        return true;
    }

	public function get_current_user() {
		return wp_get_current_user();
	}

    // Helpers not needed for WP Auth
	private function username_exists( $username ) { return true; }
	private function email_exists( $email ) { return true; }
}
