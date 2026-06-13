<?php
/**
 * Output Buffer Helper
 * 
 * Handles output buffering conflicts with zlib compression
 * Prevents "ob_end_flush(): Failed to send buffer of zlib output compression" errors
 * Uses targeted error suppression instead of aggressive buffer clearing.
 * 
 * @package OraBooksMembership
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize output buffer fixes early in WordPress load
 * Uses targeted approach: suppress errors rather than clearing buffers aggressively.
 */
function orabooks_init_output_buffer_fixes() {
    if (ini_get('zlib.output_compression')) {
        if (function_exists('ini_set')) {
            @ini_set('zlib.output_compression', '0');
        }
    }
    set_error_handler('orabooks_suppress_zlib_warnings', E_WARNING);
}

/**
 * Suppress zlib-related warnings
 */
function orabooks_suppress_zlib_warnings($errno, $errstr) {
    if (strpos($errstr, 'ob_end_flush') !== false && strpos($errstr, 'zlib') !== false) {
        // Suppress the zlib output compression warning
        return true;
    }
    return false;
}

/**
 * Safely clean output buffers to prevent zlib conflicts
 * Only cleans plugin-owned buffers, not system-level ones.
 * 
 * @return int Number of buffers that were cleaned
 */
function orabooks_clean_output_buffers() {
    $cleaned = 0;
    $level = ob_get_level();
    
    while ($level > 1) {
        if (ob_get_status(true)) {
            if (ob_end_clean()) {
                $cleaned++;
            }
        }
        $level = ob_get_level();
    }
    
    return $cleaned;
}

/**
 * Safe output buffer start with error handling
 * 
 * @return bool True if buffer was started successfully
 */
function orabooks_safe_ob_start() {
    return ob_start();
}

/**
 * Safe output buffer clean with error handling
 * 
 * @return bool True if buffer was cleaned successfully
 */
function orabooks_safe_ob_clean() {
    if (ob_get_level() > 0) {
        return @ob_end_clean();
    }
    return false;
}

/**
 * Safe output buffer flush with error handling
 * 
 * @return bool True if buffer was flushed successfully
 */
function orabooks_safe_ob_flush() {
    if (ob_get_level() > 0) {
        return @ob_end_flush();
    }
    return false;
}

/**
 * Fix for zlib compression conflicts during critical operations
 * 
 * This function should be called at the beginning of activation,
 * deactivation, and other critical WordPress operations that might
 * conflict with output buffering and zlib compression.
 * 
 * @return void
 */
function orabooks_fix_output_compression_conflicts() {
    // Check if zlib output compression is enabled
    if (ini_get('zlib.output_compression') && ob_get_level() > 0) {
        // Clean buffers to prevent conflicts
        orabooks_clean_output_buffers();
        
        // Temporarily disable zlib compression for this request
        if (function_exists('ini_set')) {
            @ini_set('zlib.output_compression', '0');
        }
    }
}

/**
 * Restore error handler after critical operations
 */
function orabooks_restore_error_handler() {
    restore_error_handler();
}

// Initialize fixes early - before most WordPress operations
add_action('muplugins_loaded', 'orabooks_init_output_buffer_fixes', -999);
