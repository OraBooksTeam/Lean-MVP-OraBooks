<?php
if (!defined('ABSPATH')) exit;

/**
 * Temporary diagnostic: log malformed nav menu items (missing properties)
 * Writes to both PHP error_log and uploads/orabooks-menu-diagnostic.log
 */
add_filter('wp_nav_menu_objects', function($items, $args){
    if (empty($items) || !is_array($items)) return $items;

    foreach ($items as $idx => $item) {
        // Accept WP_Post-like menu items; flag stdClass or missing keys
        $arr = (array) $item;
        $missing = array();
        if (!property_exists($item, 'db_id')) $missing[] = 'db_id';
        if (!property_exists($item, 'current')) $missing[] = 'current';

        if (!empty($missing) || get_class($item) === 'stdClass') {
            $menu_name = is_object($args) && !empty($args->menu) ? $args->menu : ($args->theme_location ?? 'unknown');
            $msg = 'Orabooks Menu Diagnostic: malformed item in menu="' . $menu_name . '" missing=[' . implode(',', $missing) . '] keys=[' . implode(',', array_keys($arr)) . ']';
            error_log($msg);

            // Write a small diagnostic file in uploads
            $upload = wp_upload_dir();
            $path = trailingslashit($upload['basedir']) . 'orabooks-menu-diagnostic.log';
            $entry = gmdate('Y-m-d H:i:s') . ' ' . $msg . "\n";
            $entry .= "Backtrace:\n" . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10), true) . "\n---\n";
            try { @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX); } catch (Exception $e) {}
        }
    }

    return $items;
}, 0, 2);
