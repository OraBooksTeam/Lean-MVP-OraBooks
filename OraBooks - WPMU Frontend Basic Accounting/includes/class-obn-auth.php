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

    public function can_access_accounting() {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        if ( current_user_can('manage_options') ) {
            return true;
        }

        // Check if Orabooks Membership plugin is active and feature is enabled
        if ( function_exists('orabooks_is_feature_enabled') ) {
            return orabooks_is_feature_enabled('accounting');
        }

		return false;
	}

	public function get_current_user() {
		return wp_get_current_user();
	}

    // Helpers not needed for WP Auth
	private function username_exists( $username ) { return true; }
	private function email_exists( $email ) { return true; }
}
