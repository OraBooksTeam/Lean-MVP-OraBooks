<?php
/**
 * PHPUnit bootstrap for Audit & Evidence tests (loads real OraBooks_Audit).
 */

$GLOBALS['ORABOOKS_LOAD_REAL_AUDIT'] = true;

require __DIR__. '/bootstrap.php';

if (!function_exists('get_option')) {
 function get_option($key, $default = false) {
 return $GLOBALS['orabooks_test_options'][$key] ?? $default;
 }
}

if (!function_exists('current_time')) {
 function current_time($type = 'mysql', $gmt = false) {
 return gmdate('Y-m-d H:i:s');
 }
}
