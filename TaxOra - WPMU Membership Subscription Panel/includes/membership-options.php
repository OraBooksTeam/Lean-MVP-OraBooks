<?php
/**
 * Network-wide membership options (payment gateways, etc.)
 * Stored on the main site so all subsites share the same configuration.
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main site ID for centralized membership data/options.
 */
function orabooks_get_main_site_id() {
    if (is_multisite() && function_exists('get_main_site_id')) {
        return (int) get_main_site_id();
    }
    return (int) get_current_blog_id();
}

/**
 * Option name prefixes stored on the main site in multisite.
 */
function orabooks_is_network_membership_option($option) {
    $prefixes = array(
        'orabooks_shurjopay_',
        'orabooks_sslcommerz_',
        'orabooks_paypal_',
        'orabooks_stripe_',
        'orabooks_bank_transfer_',
        'orabooks_payment_',
    );
    foreach ($prefixes as $prefix) {
        if (strpos((string) $option, $prefix) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Read a membership option (main site on multisite for payment/network keys).
 */
function orabooks_get_membership_option($option, $default = false) {
    if (!orabooks_is_network_membership_option($option) || !is_multisite()) {
        return get_option($option, $default);
    }

    $main_site_id = orabooks_get_main_site_id();
    if ((int) get_current_blog_id() === $main_site_id) {
        return get_option($option, $default);
    }

    switch_to_blog($main_site_id);
    $value = get_option($option, $default);
    restore_current_blog();
    return $value;
}

/**
 * Save a membership option (main site on multisite for payment/network keys).
 */
function orabooks_update_membership_option($option, $value) {
    if (!orabooks_is_network_membership_option($option) || !is_multisite()) {
        return update_option($option, $value);
    }

    $main_site_id = orabooks_get_main_site_id();
    if ((int) get_current_blog_id() === $main_site_id) {
        return update_option($option, $value);
    }

    switch_to_blog($main_site_id);
    $result = update_option($option, $value);
    restore_current_blog();
    return $result;
}

/**
 * Persist payment gateway settings from POST (admin save handlers).
 */
function orabooks_save_payment_settings_from_post($post = null) {
    if ($post === null) {
        $post = $_POST;
    }

    orabooks_update_membership_option('orabooks_sslcommerz_enabled', isset($post['sslcommerz_enabled']) ? 1 : 0);
    orabooks_update_membership_option('orabooks_sslcommerz_store_id', sanitize_text_field($post['sslcommerz_store_id'] ?? ''));
    if (!empty($post['sslcommerz_store_password'])) {
        orabooks_update_membership_option('orabooks_sslcommerz_store_password', sanitize_text_field($post['sslcommerz_store_password']));
    }
    orabooks_update_membership_option('orabooks_sslcommerz_test_mode', isset($post['sslcommerz_test_mode']) ? 1 : 0);

    orabooks_update_membership_option('orabooks_paypal_enabled', isset($post['paypal_enabled']) ? 1 : 0);
    orabooks_update_membership_option('orabooks_paypal_email', sanitize_email($post['paypal_email'] ?? ''));
    orabooks_update_membership_option('orabooks_paypal_sandbox', isset($post['paypal_sandbox']) ? 1 : 0);

    orabooks_update_membership_option('orabooks_stripe_enabled', isset($post['stripe_enabled']) ? 1 : 0);
    orabooks_update_membership_option('orabooks_stripe_publishable_key', sanitize_text_field($post['stripe_publishable_key'] ?? ''));
    if (!empty($post['stripe_secret_key'])) {
        orabooks_update_membership_option('orabooks_stripe_secret_key', sanitize_text_field($post['stripe_secret_key']));
    }
    orabooks_update_membership_option('orabooks_stripe_test_mode', isset($post['stripe_test_mode']) ? 1 : 0);

    orabooks_update_membership_option('orabooks_bank_transfer_enabled', isset($post['bank_transfer_enabled']) ? 1 : 0);
    orabooks_update_membership_option('orabooks_bank_transfer_instructions', wp_kses_post($post['bank_transfer_instructions'] ?? ''));

    orabooks_update_membership_option('orabooks_payment_currency', 'BDT');
    orabooks_update_membership_option('orabooks_payment_success_page', intval($post['payment_success_page'] ?? 0));
    orabooks_update_membership_option('orabooks_payment_failure_page', intval($post['payment_failure_page'] ?? 0));

    orabooks_update_membership_option('orabooks_shurjopay_enabled', isset($post['shurjopay_enabled']) ? 1 : 0);
    orabooks_update_membership_option('orabooks_shurjopay_api_url', sanitize_text_field($post['shurjopay_api_url'] ?? ''));
    orabooks_update_membership_option('orabooks_shurjopay_username', sanitize_text_field($post['shurjopay_username'] ?? ''));
    if (!empty($post['shurjopay_password'])) {
        orabooks_update_membership_option('orabooks_shurjopay_password', sanitize_text_field($post['shurjopay_password']));
    }
    orabooks_update_membership_option('orabooks_shurjopay_prefix', sanitize_text_field($post['shurjopay_prefix'] ?? ''));
    orabooks_update_membership_option('orabooks_shurjopay_test_mode', isset($post['shurjopay_test_mode']) ? 1 : 0);
}
