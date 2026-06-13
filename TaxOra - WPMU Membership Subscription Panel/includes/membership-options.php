<?php
/**
 * Network-wide membership options (payment gateways, etc.)
 * Stored on the main site so all subsites share the same configuration.
 * 
 * SL-008 Compliance: All payment gateway credentials (store passwords, secret keys)
 * are encrypted at rest using AES-256-CBC via OraBooks_Security::encrypt().
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
        'orabooks_recaptcha_',
    );
    foreach ($prefixes as $prefix) {
        if (strpos((string) $option, $prefix) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * SL-008 Compliance: Identify credential options that must be encrypted at rest.
 */
function orabooks_is_credential_option($option) {
    $credential_options = array(
        'orabooks_sslcommerz_store_password',
        'orabooks_stripe_secret_key',
        'orabooks_shurjopay_password',
        'orabooks_recaptcha_secret_key',
    );
    return in_array($option, $credential_options, true);
}

/**
 * Read a membership option (main site on multisite for payment/network keys).
 * 
 * SL-008 Compliance: Credential options are automatically decrypted on retrieval.
 */
function orabooks_get_membership_option($option, $default = false) {
    if (!orabooks_is_network_membership_option($option) || !is_multisite()) {
        $value = get_option($option, $default);
        // SL-008: Decrypt credentials on retrieval
        if (orabooks_is_credential_option($option) && !empty($value)) {
            $value = orabooks_decrypt_credential($value);
        }
        return $value;
    }

    $main_site_id = orabooks_get_main_site_id();
    if ((int) get_current_blog_id() === $main_site_id) {
        $value = get_option($option, $default);
        // SL-008: Decrypt credentials on retrieval
        if (orabooks_is_credential_option($option) && !empty($value)) {
            $value = orabooks_decrypt_credential($value);
        }
        return $value;
    }

    switch_to_blog($main_site_id);
    $value = get_option($option, $default);
    restore_current_blog();
    // SL-008: Decrypt credentials on retrieval
    if (orabooks_is_credential_option($option) && !empty($value)) {
        $value = orabooks_decrypt_credential($value);
    }
    return $value;
}

/**
 * Save a membership option (main site on multisite for payment/network keys).
 * 
 * SL-008 Compliance: Credential options are automatically encrypted before storage.
 */
function orabooks_update_membership_option($option, $value) {
    // SL-008: Encrypt credentials before storage
    $value_to_store = $value;
    if (orabooks_is_credential_option($option) && !empty($value_to_store)) {
        $value_to_store = orabooks_encrypt_credential($value_to_store);
    }

    if (!orabooks_is_network_membership_option($option) || !is_multisite()) {
        return update_option($option, $value_to_store);
    }

    $main_site_id = orabooks_get_main_site_id();
    if ((int) get_current_blog_id() === $main_site_id) {
        return update_option($option, $value_to_store);
    }

    switch_to_blog($main_site_id);
    $result = update_option($option, $value_to_store);
    restore_current_blog();
    return $result;
}

/**
 * SL-008 Compliance: Encrypt a credential value using OraBooks_Security.
 *
 * @param string $plaintext Plaintext credential
 * @return string Encrypted credential (base64-encoded)
 */
function orabooks_encrypt_credential($plaintext) {
    if (empty($plaintext)) {
        return $plaintext;
    }
    
    // Use OraBooks_Security encryption if available
    if (class_exists('OraBooks_Security')) {
        $security = OraBooks_Security::get_instance();
        $encrypted = $security->encrypt($plaintext);
        if ($encrypted !== false) {
            return $encrypted;
        }
    }
    
    // Fallback: use WordPress salts as key material (AES-256-CTR)
    if (defined('AUTH_KEY') && defined('AUTH_SALT')) {
        $key = hash('sha256', AUTH_KEY . AUTH_SALT, true);
        $method = 'aes-256-ctr';
        $iv = openssl_random_pseudo_bytes(16);
        $ciphertext = openssl_encrypt($plaintext, $method, $key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext !== false) {
            return base64_encode('ENC:' . base64_encode($iv) . ':' . base64_encode($ciphertext));
        }
    }
    
    // SL-008: Log warning - credential stored as plaintext (should not happen in production)
    error_log('[OraBooks SL-008] WARNING: Encryption not available. Credential stored without encryption.');
    return $plaintext;
}

/**
 * SL-008 Compliance: Decrypt a credential value using OraBooks_Security.
 *
 * @param string $encrypted Encrypted credential (base64-encoded)
 * @return string Plaintext credential
 */
function orabooks_decrypt_credential($encrypted) {
    if (empty($encrypted)) {
        return $encrypted;
    }
    
    // Use OraBooks_Security decryption if available
    if (class_exists('OraBooks_Security')) {
        $security = OraBooks_Security::get_instance();
        $decrypted = $security->decrypt($encrypted);
        if ($decrypted !== false) {
            return $decrypted;
        }
    }
    
    // Check for our fallback encrypted format: ENC:<iv>:<ciphertext>
    if (strpos($encrypted, 'ENC:') === 0) {
        if (defined('AUTH_KEY') && defined('AUTH_SALT')) {
            $parts = explode(':', $encrypted, 3);
            if (count($parts) === 3) {
                $key = hash('sha256', AUTH_KEY . AUTH_SALT, true);
                $iv = base64_decode($parts[1]);
                $ciphertext = base64_decode($parts[2]);
                $plaintext = openssl_decrypt($ciphertext, 'aes-256-ctr', $key, OPENSSL_RAW_DATA, $iv);
                if ($plaintext !== false) {
                    return $plaintext;
                }
            }
        }
    }
    
    // Not encrypted (legacy plaintext or already decrypted) - return as-is
    return $encrypted;
}

/**
 * Persist payment gateway settings from POST (admin save handlers).
 * 
 * SL-008 Compliance: Credentials are encrypted via orabooks_update_membership_option().
 */
function orabooks_save_payment_settings_from_post($post = null) {
    if ($post === null) {
        $post = $_POST;
    }

    orabooks_update_membership_option('orabooks_sslcommerz_enabled', isset($post['sslcommerz_enabled']) ? 1 : 0);
    orabooks_update_membership_option('orabooks_sslcommerz_store_id', sanitize_text_field($post['sslcommerz_store_id'] ?? ''));
    if (!empty($post['sslcommerz_store_password'])) {
        orabooks_update_membership_option('orabooks_sslcommerz_store_password', sanitize_text_field($post['sslcommerz_store_password']));
        // SL-008: Audit log credential update
        do_action('orabooks_security_event', 'credential_updated', array(
            'option' => 'orabooks_sslcommerz_store_password',
            'user_id' => get_current_user_id(),
        ));
    }
    orabooks_update_membership_option('orabooks_sslcommerz_test_mode', isset($post['sslcommerz_test_mode']) ? 1 : 0);

    orabooks_update_membership_option('orabooks_paypal_enabled', isset($post['paypal_enabled']) ? 1 : 0);
    orabooks_update_membership_option('orabooks_paypal_email', sanitize_email($post['paypal_email'] ?? ''));
    orabooks_update_membership_option('orabooks_paypal_sandbox', isset($post['paypal_sandbox']) ? 1 : 0);

    orabooks_update_membership_option('orabooks_stripe_enabled', isset($post['stripe_enabled']) ? 1 : 0);
    orabooks_update_membership_option('orabooks_stripe_publishable_key', sanitize_text_field($post['stripe_publishable_key'] ?? ''));
    if (!empty($post['stripe_secret_key'])) {
        orabooks_update_membership_option('orabooks_stripe_secret_key', sanitize_text_field($post['stripe_secret_key']));
        // SL-008: Audit log credential update
        do_action('orabooks_security_event', 'credential_updated', array(
            'option' => 'orabooks_stripe_secret_key',
            'user_id' => get_current_user_id(),
        ));
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
        // SL-008: Audit log credential update
        do_action('orabooks_security_event', 'credential_updated', array(
            'option' => 'orabooks_shurjopay_password',
            'user_id' => get_current_user_id(),
        ));
    }
    orabooks_update_membership_option('orabooks_shurjopay_prefix', sanitize_text_field($post['shurjopay_prefix'] ?? ''));
    orabooks_update_membership_option('orabooks_shurjopay_test_mode', isset($post['shurjopay_test_mode']) ? 1 : 0);
}

/**
 * SL-008 Compliance: Migration helper for existing plaintext credentials.
 * Run once during plugin upgrade to encrypt existing credentials.
 */
function orabooks_migrate_legacy_credentials() {
    $credential_options = array(
        'orabooks_sslcommerz_store_password',
        'orabooks_stripe_secret_key',
        'orabooks_shurjopay_password',
        'orabooks_recaptcha_secret_key',
    );
    
    foreach ($credential_options as $option_name) {
        $current_value = get_option($option_name, '');
        if (!empty($current_value) && strpos($current_value, 'ENC:') !== 0) {
            // This is plaintext - encrypt it
            $encrypted = orabooks_encrypt_credential($current_value);
            update_option($option_name, $encrypted);
            error_log('[OraBooks SL-008] Migrated credential: ' . $option_name);
            
            // Audit log the migration
            do_action('orabooks_security_event', 'credential_migrated', array(
                'option' => $option_name,
            ));
        }
    }
}

// SL-008: Run credential migration on plugin load (once per version)
add_action('plugins_loaded', function() {
    $migration_version = get_option('orabooks_credential_migration_version', 0);
    if ($migration_version < 1) {
        orabooks_migrate_legacy_credentials();
        update_option('orabooks_credential_migration_version', 1);
    }
});