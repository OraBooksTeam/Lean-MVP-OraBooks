<?php
/**
 * Fix method calls without parentheses in OraBooks files.
 * Run: php fix_method_calls.php
 */

$files_to_fix = [
    __DIR__ . '/includes/class-orabooks-secrets.php',
    __DIR__ . '/includes/class-orabooks-exports.php',
];

// Known methods in OraBooks_Secrets
$secrets_methods = [
    'init', 'is_ready', 'get_bootstrap_error', 'get_default_jwt_expiry',
    'get_hmac_signing_key', 'clear_secrets_cache', 'bootstrap',
    'is_production', 'maybe_enforce_https', 'requires_tls',
    'register_failure_handlers', 'render_bootstrap_admin_notice',
    'block_orabooks_ajax_when_not_ready', 'check_database_tls',
    'load_file_secrets', 'migrate_legacy_secrets', 'mask_value',
    'redact_sensitive', 'log_secret_access', 'get', 'load_secret',
    'with_shared_options', 'set', 'rotate_secret', 'get_cipher_key',
    'get_legacy_cipher_key', 'encrypt', 'encrypt_with_key', 'decrypt',
    'decrypt_with_key', 'encrypt_sensitive', 'decrypt_sensitive',
    'get_jwt_secret', 'get_jwt_verification_secrets', 'check_tls_certificate',
    'get_status', 'get_encryption_key', 'generate_jwt', 'verify_jwt',
    'base64url_encode', 'base64url_decode', 'hash_password', 'verify_password',
    'generate_totp_secret', 'get_totp_provisioning_uri', 'get_totp_qr_url',
    'normalize_totp_code', 'verify_totp', 'generate_totp_code', 'pack_counter',
    'decode_totp_secret', 'base32_encode', 'base32_decode', 'generate_backup_codes',
    'verify_google_id_token', 'get_google_oidc_public_key', 'google_jwk_to_pem',
    'encode_asn1_length', 'encode_asn1_integer', 'encode_asn1_sequence',
];

// Known methods in OraBooks_Exports
$exports_methods = [
    'init', 'get_create_table_sql', 'register_report_provider',
    'get_report_data', 'register_default_providers', 'request_export',
    'generate_export_job', 'generate_csv', 'generate_pdf_html',
    'get_org_name', 'download_export', 'cancel_export', 'cleanup_expired',
    'get_user_exports', 'get_export_stats', 'ajax_request_export',
    'ajax_exports_list', 'ajax_download_export', 'ajax_cancel_export',
    'ajax_exports_stats', 'get_user_org_id', 'format_file_size',
    'time_remaining',
];

foreach ($files_to_fix as $filepath) {
    if (!file_exists($filepath)) {
        echo "SKIP: $filepath not found\n";
        continue;
    }

    $content = file_get_contents($filepath);
    $original = $content;
    $basename = basename($filepath);

    // Determine which method list to use
    $method_list = [];
    if (strpos($basename, 'secrets') !== false) {
        $method_list = $secrets_methods;
    } elseif (strpos($basename, 'exports') !== false) {
        $method_list = $exports_methods;
    }

    // Fix self::methodName without ()
    foreach ($method_list as $method) {
        // Pattern: self::methodName followed by ;  (assignment or statement)
        $content = preg_replace(
            '/self::' . preg_quote($method, '/') . '\s*;/',
            'self::' . $method . '();',
            $content
        );
        // Pattern: self::methodName followed by ,  (array element)
        $content = preg_replace(
            '/self::' . preg_quote($method, '/') . '\s*,/',
            'self::' . $method . '(),',
            $content
        );
        // Pattern: self::methodName followed by )  (function argument or array end)
        $content = preg_replace(
            '/self::' . preg_quote($method, '/') . '\s*\)/',
            'self::' . $method . '())',
            $content
        );
        // Pattern: self::methodName followed by as (foreach)
        $content = preg_replace(
            '/self::' . preg_quote($method, '/') . '\s+as\s+/',
            'self::' . $method . '() as ',
            $content
        );
        // Pattern: self::methodName followed by space then operator like || && etc.
        $content = preg_replace(
            '/self::' . preg_quote($method, '/') . '\s+(\|\||&&|\?)/',
            'self::' . $method . '() $1',
            $content
        );
        // Pattern: self::methodName at end of line
        $content = preg_replace(
            '/self::' . preg_quote($method, '/') . '\s*$/m',
            'self::' . $method . '()',
            $content
        );
    }

    // Fix $e->getMessage, $result->get_error_message, etc. - only if followed by ; or ) or ,
    $content = preg_replace('/->getMessage\s*;/', '->getMessage();', $content);
    $content = preg_replace('/->getMessage\s*,/', '->getMessage(),', $content);
    $content = preg_replace('/->getMessage\s*\)/', '->getMessage())', $content);
    $content = preg_replace('/->get_error_message\s*;/', '->get_error_message();', $content);
    $content = preg_replace('/->get_error_message\s*,/', '->get_error_message(),', $content);
    $content = preg_replace('/->get_error_message\s*\)/', '->get_error_message())', $content);
    $content = preg_replace('/->get_error_data\s*;/', '->get_error_data();', $content);
    $content = preg_replace('/->get_error_data\s*,/', '->get_error_data(),', $content);
    $content = preg_replace('/->get_error_data\s*\)/', '->get_error_data())', $content);

    // For exports.php: fix $wpdb->get_charset_collate inside strings
    if (strpos($basename, 'exports') !== false) {
        $content = str_replace('{$wpdb->get_charset_collate}', '{$wpdb->get_charset_collate()}', $content);
    }

    // Fix OraBooks_Exports::get_export_stats without ()
    $content = preg_replace('/OraBooks_Exports::get_export_stats\s*;/', 'OraBooks_Exports::get_export_stats();', $content);

    // Fix OraBooks_Exports::register_default_providers without ()
    $content = preg_replace('/self::register_default_providers\s*;/', 'self::register_default_providers();', $content);

    // Fix orabooks_uuid without ()
    $content = preg_replace('/\borabooks_uuid\s*;/', 'orabooks_uuid();', $content);
    $content = preg_replace('/\borabooks_uuid\s*,/', 'orabooks_uuid(),', $content);
    $content = preg_replace('/\borabooks_uuid\s*\)/', 'orabooks_uuid())', $content);

    // Fix orabooks_get_current_user_id without ()
    $content = preg_replace('/\borabooks_get_current_user_id\s*;/', 'orabooks_get_current_user_id();', $content);

    // Fix $upload_dir = wp_upload_dir; etc.
    $content = preg_replace('/\bwp_upload_dir\s*;/', 'wp_upload_dir();', $content);
    $content = preg_replace('/\bwp_upload_dir\s+\./', 'wp_upload_dir() .', $content);

    if ($content !== $original) {
        file_put_contents($filepath, $content);
        echo "FIXED: $filepath\n";
    } else {
        echo "NO CHANGE: $filepath\n";
    }
}

// Now fix get_charset_collate across ALL includes files
echo "\n--- Fixing \$wpdb->get_charset_collate across all files ---\n";
$includes_dir = __DIR__ . '/includes';
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($includes_dir, RecursiveDirectoryIterator::SKIP_DOTS)
);
$count = 0;
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    $content = file_get_contents($path);
    $original = $content;

    // Fix $wpdb->get_charset_collate without () - both regular and inside strings
    $content = str_replace('$wpdb->get_charset_collate', '$wpdb->get_charset_collate()', $content);

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "FIXED: $path\n";
        $count++;
    }
}
echo "Fixed $count files for get_charset_collate\n";

echo "\nDONE\n";
