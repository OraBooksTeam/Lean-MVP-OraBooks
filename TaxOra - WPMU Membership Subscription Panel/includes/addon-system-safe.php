<?php
/**
 * Safe Orabooks Addon System - Memory Optimized Version
 * 
 * This system only loads addons when specifically requested to prevent memory exhaustion.
 * 
 * @package OraBooks_Membership
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Addon_Registry_Safe {
    
    private static $instance = null;
    private static $addons = array();
    private static $loaded = false;
    
    /**
     * Initialize the addon system only when needed
     */
    public static function init() {
        // Do NOT auto-initialize - only load when explicitly called
        // This prevents memory exhaustion on every page load
    }
    
    /**
     * Load addons only when specifically requested (e.g., in Settings page)
     */
    public static function load_when_needed() {
        if (self::$loaded) {
            return self::$addons;
        }
        
        // Memory check before loading
        $current_memory = memory_get_usage(true);
        $memory_limit = 256 * 1024 * 1024; // 256MB limit
        
        if ($current_memory > $memory_limit) {
            error_log("Orabooks Addon Registry: Memory usage too high, skipping addon loading");
            return array();
        }
        
        self::scan_for_addons_safe();
        self::$loaded = true;
        
        return self::$addons;
    }
    
    /**
     * Safe addon scanning with memory limits
     */
    public static function scan_for_addons_safe() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // Clear existing data
        self::$addons = array();
        
        try {
            $all_plugins = get_plugins();
            
            // Debug: Log total plugins found
            error_log("Orabooks Safe Addon Registry: Total plugins found: " . count($all_plugins));
            
            foreach ($all_plugins as $plugin_file => $plugin_data) {
                // Debug: Log each plugin check
                error_log("Orabooks Safe Addon Registry: Checking plugin - {$plugin_data['Name']} (File: $plugin_file)");
                
                // Check for Orabooks Addon header
                $has_orabooks_header = isset($plugin_data['Orabooks Addon']);
                $header_value = $has_orabooks_header ? $plugin_data['Orabooks Addon'] : 'not set';
                
                error_log("Orabooks Safe Addon Registry: Plugin '{$plugin_data['Name']}' - Orabooks Addon header: $header_value");
                
                if ($has_orabooks_header && (strtolower($plugin_data['Orabooks Addon']) === 'yes' || strtolower($plugin_data['Orabooks Addon']) === 'true')) {
                    $addon_id = sanitize_key(dirname($plugin_file));
                    $is_active = is_plugin_active($plugin_file);
                    
                    self::$addons[$addon_id] = array(
                        'id' => $addon_id,
                        'name' => $plugin_data['Name'],
                        'description' => substr($plugin_data['Description'], 0, 150),
                        'version' => $plugin_data['Version'],
                        'author' => $plugin_data['Author'] ?? '',
                        'plugin_file' => $plugin_file,
                        'enabled' => $is_active,
                        'features' => array()
                    );
                    
                    error_log("Orabooks Safe Addon Registry: SUCCESS - Found addon - {$plugin_data['Name']} (ID: $addon_id, Active: " . ($is_active ? 'Yes' : 'No') . ")");
                    
                    // Early exit if we have too many addons
                    if (count(self::$addons) >= 20) {
                        break;
                    }
                }
            }
            
            // Debug: Log final results
            error_log("Orabooks Safe Addon Registry: Final addon count: " . count(self::$addons));
            
        } catch (Exception $e) {
            error_log("Orabooks Addon Registry: Error scanning addons - " . $e->getMessage());
            self::$addons = array();
        }
    }
    
    /**
     * Get addons (loads them if needed)
     */
    public static function get_addons() {
        return self::load_when_needed();
    }
    
    /**
     * Check if addon exists
     */
    public static function get_addon($addon_id) {
        $addons = self::get_addons();
        return isset($addons[$addon_id]) ? $addons[$addon_id] : null;
    }
    
    /**
     * Check if addon is enabled
     */
    public static function is_addon_enabled($addon_id) {
        $addon = self::get_addon($addon_id);
        return $addon ? $addon['enabled'] : false;
    }
    
    /**
     * Merge addon features (safe version)
     */
    public static function merge_addon_features($features) {
        // Only load addons if we're in admin and on settings page
        if (!is_admin() || !isset($_GET['page']) || $_GET['page'] !== 'orabooks-membership-settings') {
            return $features;
        }
        
        $addons = self::get_addons();
        
        foreach ($addons as $addon_id => $addon) {
            if (self::is_addon_enabled($addon_id)) {
                // Add basic addon feature if enabled
                $features['addon_' . $addon_id] = array(
                    'name' => $addon['name'],
                    'description' => $addon['description'],
                    'addon_id' => $addon_id
                );
            }
        }
        
        return $features;
    }
}

// Initialize the safe addon system (but don't auto-load)
OraBooks_Addon_Registry_Safe::init();

// Helper functions for backward compatibility
function orabooks_get_addons_safe() {
    return OraBooks_Addon_Registry_Safe::get_addons();
}

function orabooks_is_addon_enabled_safe($addon_id) {
    return OraBooks_Addon_Registry_Safe::is_addon_enabled($addon_id);
}
