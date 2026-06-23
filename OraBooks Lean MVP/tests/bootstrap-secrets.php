<?php
/**
 * PHPUnit bootstrap for Secrets/TLS tests (loads real OraBooks_Secrets).
 */

$GLOBALS['ORABOOKS_LOAD_REAL_SECRETS'] = true;

if (!defined('LOGGED_IN_KEY')) {
 define('LOGGED_IN_KEY', 'test-logged-in-key-for-orabooks-secrets');
}

require __DIR__. '/bootstrap.php';

if (!function_exists('wp_salt')) {
 function wp_salt($scheme = 'auth') {
 return 'test-wp-salt-'. $scheme;
 }
}
