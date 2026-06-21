<?php
/**
 * PHPUnit bootstrap for SL-004 Multi-tenant & Residency tests (loads real OraBooks_Organization).
 */

$GLOBALS['ORABOOKS_LOAD_REAL_ORGANIZATION'] = true;

require __DIR__ . '/bootstrap.php';

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        $GLOBALS['orabooks_test_actions'][$hook][] = $args;
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '') {
        return true;
    }
}
