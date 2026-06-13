<?php
/**
 * Orabooks Addon System
 * 
 * This system allows other plugins to register as addons to the Orabooks Membership plugin.
 * Addons can add new features that integrate seamlessly with the membership system.
 * 
 * @package OraBooks_Membership
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Addon_Registry {
    
    private static $instance = null;
    private static $addons = array();
    private static $addon_features = array();
    
    /**
     * Initialize the addon system
     */
    public static function init() {
        // Hook to allow addons to register themselves - lower priority to prevent conflicts
        add_action('plugins_loaded', array(__CLASS__, 'load_addons'), 20);
        add_action('orabooks_register_addons', array(__CLASS__, 'process_addon_registrations'));
        
        // Merge addon features into available features (runs in both admin and REST API contexts)
        add_filter('orabooks_available_features', array(__CLASS__, 'merge_addon_features'));
        
        // AJAX handlers - only in admin
        if (is_admin()) {
            add_action('wp_ajax_orabooks_toggle_addon', array(__CLASS__, 'ajax_toggle_addon'));
        }
    }
    
    /**
     * Load and detect addons
     */
    public static function load_addons($force = false) {
        // Prevent multiple loading attempts unless forced
        static $loaded = false;
        if ($loaded && !$force) {
            return;
        }
        $loaded = true;
        
        // Memory monitoring
        $start_memory = memory_get_usage(true);
        
        self::scan_for_addons();
        self::process_addon_registrations();
        
        // Auto-enable addons if their plugins are active
        self::auto_enable_active_addons();
        
        $end_memory = memory_get_usage(true);
        $memory_used = $end_memory - $start_memory;
        
        // Log memory usage for debugging
        if ($memory_used > 10 * 1024 * 1024) { // More than 10MB
            error_log("Orabooks Addon Registry: High memory usage detected - " . round($memory_used / 1024 / 1024, 2) . "MB");
        }
    }
    
    /**
     * Scan for addons in the WordPress plugins directory
     */
    public static function scan_for_addons() {
        // Memory optimization: Check if already scanned
        static $scanned = false;
        if ($scanned) {
            return;
        }
        
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // Memory optimization: Clear any existing data
        self::$addons = array();
        
        $all_plugins = get_plugins();
        
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            // Memory optimization: Check only first 100 chars of description
            $description = substr($plugin_data['Description'], 0, 100);
            
            // Check if plugin has "Orabooks Addon" in the description or has custom header
            $is_orabooks_addon = false;
            
            // Check various header key formats (WordPress lowercases and underscores header names)
            $addon_header_keys = array('orabooks_addon', 'Orabooks Addon', 'orabooks-addon');
            foreach ($addon_header_keys as $key) {
                if (isset($plugin_data[$key]) && (strtolower($plugin_data[$key]) === 'yes' || strtolower($plugin_data[$key]) === 'true')) {
                    $is_orabooks_addon = true;
                    break;
                }
            }
            
            // Check description for membership-related plugins
            if (!$is_orabooks_addon) {
                $description_lower = strtolower($description);
                if (strpos($description_lower, 'orabooks addon') !== false || 
                    strpos($description_lower, 'orabooks membership') !== false ||
                    strpos($description_lower, 'orabooks system') !== false) {
                    $is_orabooks_addon = true;
                }
            }
            
            // Also auto-register known addon plugins by folder name
            $plugin_dir = sanitize_key(dirname($plugin_file));
            $known_addons = array('accounting', 'inventory', 'orabooks-accounting', 'orabooks-inventory');
            if (in_array($plugin_dir, $known_addons) || preg_match('/accounting|inventory/i', $plugin_dir)) {
                $is_orabooks_addon = true;
            }
            
            if ($is_orabooks_addon) {
                // Extract addon ID from plugin file
                $addon_id = sanitize_key(dirname($plugin_file));
                
                // Check if plugin is active
                $is_active = is_plugin_active($plugin_file);
                
                // Register addon with minimal data
                self::$addons[$addon_id] = array(
                    'id' => $addon_id,
                    'name' => $plugin_data['Name'],
                    'description' => substr($plugin_data['Description'], 0, 200), // Limit description length
                    'version' => $plugin_data['Version'],
                    'author' => $plugin_data['Author'] ?? '',
                    'plugin_file' => $plugin_file,
                    'enabled' => $is_active,
                    'features' => array()
                );
                
                error_log("Orabooks Addon Registry: Found addon - {$plugin_data['Name']} (ID: $addon_id, Active: " . ($is_active ? 'Yes' : 'No') . ")");
            }
        }
        
        $scanned = true;
    }
    
    /**
     * Process addon registrations
     */
    public static function process_addon_registrations() {
        // Prevent duplicate registration calls
        static $processed = false;
        if ($processed) {
            return;
        }
        $processed = true;
        
        do_action('orabooks_register_addons');
        
        // Auto-register features for known addons that don't explicitly register themselves
        self::auto_register_known_addon_features();
    }
    
    /**
     * Auto-register features for known addon plugins
     * This ensures addons like Accounting and Inventory show up on the dashboard
     * even if they don't explicitly call orabooks_register_addon_features()
     */
    public static function auto_register_known_addon_features() {
        $known_features = array(
            'accounting' => array(
                'name' => 'Accounting',
                'description' => 'Complete accounting dashboard with financial management, invoicing, and expense tracking.',
                'icon' => 'calculator',
                'url' => home_url('/accounting'),
                'category' => 'Business',
                'limitations' => array(
                    'invoices' => array('name' => 'Invoices Limit', 'type' => 'number', 'placeholder' => 'e.g. 50', 'description' => 'Maximum invoices per month'),
                    'expenses' => array('name' => 'Expenses Limit', 'type' => 'number', 'placeholder' => 'e.g. 100', 'description' => 'Maximum expense entries per month'),
                    'customers' => array('name' => 'Customers Limit', 'type' => 'number', 'placeholder' => 'e.g. 20', 'description' => 'Maximum customers allowed'),
                )
            ),
            'inventory' => array(
                'name' => 'Inventory',
                'description' => 'Inventory management system with stock tracking, product management, and reporting.',
                'icon' => 'package',
                'url' => home_url('/inventory'),
                'category' => 'Business',
                'limitations' => array(
                    'items' => array('name' => 'Items Limit', 'type' => 'number', 'placeholder' => 'e.g. 100', 'description' => 'Maximum products/items in catalog'),
                    'warehouses' => array('name' => 'Warehouses Limit', 'type' => 'number', 'placeholder' => 'e.g. 2', 'description' => 'Maximum warehouse locations'),
                    'suppliers' => array('name' => 'Suppliers Limit', 'type' => 'number', 'placeholder' => 'e.g. 10', 'description' => 'Maximum suppliers allowed'),
                    'sales' => array('name' => 'Sales Limit', 'type' => 'number', 'placeholder' => 'e.g. 50', 'description' => 'Maximum sales transactions per month'),
                    'purchases' => array('name' => 'Purchases Limit', 'type' => 'number', 'placeholder' => 'e.g. 50', 'description' => 'Maximum purchase transactions per month'),
                )
            ),
        );
        
        foreach (self::$addons as $addon_id => $addon_data) {
            // Check if this addon ID or plugin folder matches known features
            $matched_features = array();
            foreach ($known_features as $feature_key => $feature_data) {
                if (
                    $addon_id === $feature_key ||
                    strpos($addon_id, $feature_key) !== false ||
                    strpos($feature_key, $addon_id) !== false ||
                    stripos($addon_data['name'], $feature_data['name']) !== false ||
                    stripos($feature_data['name'], $addon_data['name']) !== false
                ) {
                    $matched_features[$feature_key] = $feature_data;
                }
            }
            
            if (!empty($matched_features)) {
                // Register features for this addon
                foreach ($matched_features as $feature_key => $feature_data) {
                    if (!isset(self::$addon_features[$feature_key])) {
                        self::$addon_features[$feature_key] = array_merge($feature_data, array(
                            'addon_id' => $addon_id
                        ));
                        
                        // Add to addon's features list
                        self::$addons[$addon_id]['features'][$feature_key] = $feature_data;
                        
                        error_log("Orabooks Addon Registry: Auto-registered feature '{$feature_key}' for addon '{$addon_id}'");
                    }
                }
            }
        }
    }
    
    /**
     * Register an addon
     * 
     * @param array $addon_data Addon data
     * @return bool True on success, false on failure
     */
    public static function register_addon($addon_data) {
        error_log("Orabooks Addon Registry: Attempting to register addon " . ($addon_data['id'] ?? 'unknown'));
        
        // Validate required fields
        $required = array('id', 'name', 'description', 'version', 'plugin_file');
        foreach ($required as $field) {
            if (empty($addon_data[$field])) {
                error_log("Orabooks Addon Registration Error: Missing required field '{$field}'");
                return false;
            }
        }
        
        $addon_id = $addon_data['id'];
        
        // Check if addon already exists
        if (isset(self::$addons[$addon_id])) {
            error_log("Orabooks Addon Registry: Addon '$addon_id' already registered, updating");
        }
        
        // Merge with existing addon data or create new
        if (isset(self::$addons[$addon_id])) {
            self::$addons[$addon_id] = array_merge(self::$addons[$addon_id], $addon_data);
        } else {
            self::$addons[$addon_id] = array_merge($addon_data, array(
                'enabled' => false,
                'features' => array()
            ));
        }
        
        // Auto-detect enabled state if plugin file is provided
        if (isset($addon_data['plugin_file'])) {
            $plugin_file = $addon_data['plugin_file'];
            // Handle both absolute and relative paths
            $plugin_path = (strpos($plugin_file, ABSPATH) === 0) ? plugin_basename($plugin_file) : $plugin_file;
            if (is_plugin_active($plugin_path)) {
                self::$addons[$addon_id]['enabled'] = true;
            }
        }
        
        // Automatically register features if provided in the addon data
        if (isset($addon_data['features']) && is_array($addon_data['features'])) {
            self::register_features($addon_id, $addon_data['features']);
        }
        
        error_log("Orabooks Addon Registry: Successfully registered addon '$addon_id' (Enabled: " . (self::$addons[$addon_id]['enabled'] ? 'Yes' : 'No') . ")");
        return true;
    }
    
    /**
     * Register addon features
     * 
     * @param string $addon_id Addon ID
     * @param array $features Features to register
     */
    public static function register_features($addon_id, $features) {
        if (!isset(self::$addons[$addon_id])) {
            error_log("Orabooks Addon Registry: Cannot register features for unregistered addon '$addon_id'");
            return;
        }
        
        foreach ($features as $feature_key => $feature) {
            // Validate feature data
            if (!isset($feature['name']) || !isset($feature['description'])) {
                continue;
            }
            
            self::$addon_features[$feature_key] = array_merge($feature, array(
                'addon_id' => $addon_id
            ));
            
            // Add to addon's features list
            self::$addons[$addon_id]['features'][$feature_key] = $feature;
        }
        
        error_log("Orabooks Addon Registry: Registered " . count($features) . " features for addon '$addon_id'");
    }
    
    /**
     * Get all registered addons
     * 
     * @return array List of addons
     */
    public static function get_addons() {
        return self::$addons;
    }
    
    /**
     * Get addon by ID
     * 
     * @param string $addon_id Addon ID
     * @return array|null Addon data or null if not found
     */
    public static function get_addon($addon_id) {
        return isset(self::$addons[$addon_id]) ? self::$addons[$addon_id] : null;
    }
    
    /**
     * Check if addon is enabled
     * 
     * @param string $addon_id Addon ID
     * @return bool True if enabled, false otherwise
     */
    public static function is_addon_enabled($addon_id) {
        if (!isset(self::$addons[$addon_id])) {
            error_log("Orabooks Addon Registry: is_addon_enabled check for unregistered addon: $addon_id");
            return false;
        }
        $enabled = (bool) self::$addons[$addon_id]['enabled'];
        error_log("Orabooks Addon Registry: is_addon_enabled check for $addon_id: " . ($enabled ? 'true' : 'false'));
        return $enabled;
    }
    
    /**
     * Enable addon
     * 
     * @param string $addon_id Addon ID
     * @return bool True on success, false on failure
     */
    public static function enable_addon($addon_id) {
        if (!isset(self::$addons[$addon_id])) {
            return false;
        }
        
        $plugin_file = self::$addons[$addon_id]['plugin_file'];
        
        if (is_plugin_active($plugin_file)) {
            self::$addons[$addon_id]['enabled'] = true;
            return true;
        }
        
        $result = activate_plugin($plugin_file);
        
        if (is_wp_error($result)) {
            error_log("Orabooks Addon Registry: Failed to activate addon '$addon_id': " . $result->get_error_message());
            return false;
        }
        
        self::$addons[$addon_id]['enabled'] = true;
        error_log("Orabooks Addon Registry: Successfully enabled addon '$addon_id'");
        return true;
    }
    
    /**
     * Disable addon
     * 
     * @param string $addon_id Addon ID
     * @return bool True on success, false on failure
     */
    public static function disable_addon($addon_id) {
        if (!isset(self::$addons[$addon_id])) {
            return false;
        }
        
        $plugin_file = self::$addons[$addon_id]['plugin_file'];
        
        if (!is_plugin_active($plugin_file)) {
            self::$addons[$addon_id]['enabled'] = false;
            return true;
        }
        
        deactivate_plugins($plugin_file);
        
        self::$addons[$addon_id]['enabled'] = false;
        error_log("Orabooks Addon Registry: Successfully disabled addon '$addon_id'");
        return true;
    }
    
    /**
     * Auto-enable addons if their plugins are active
     * Core addons (Accounting, Inventory) should always be enabled when their plugins are active
     * Regular features should be managed by superadmin per membership plan
     */
    public static function auto_enable_active_addons() {
        foreach (self::$addons as $addon_id => $addon_data) {
            $plugin_file = $addon_data['plugin_file'] ?? null;
            
            // Check if this is a core addon (Accounting, Inventory, etc.)
            $is_core_addon = in_array($addon_id, ['accounting', 'inventory', 'payroll', 'hr', 'crm']);
            
            if ($plugin_file && is_plugin_active($plugin_file)) {
                self::enable_addon($addon_id);
                if ($is_core_addon) {
                    error_log("Orabooks Addon Registry: Auto-enabled CORE addon '$addon_id' because plugin '$plugin_file' is active");
                } else {
                    error_log("Orabooks Addon Registry: Auto-enabled FEATURE addon '$addon_id' because plugin '$plugin_file' is active");
                }
            }
        }
    }
    
    /**
     * Merge addon features with existing features
     * 
     * @param array $features Existing features
     * @return array Merged features
     */
    public static function merge_addon_features($features) {
        // Ensure addons are loaded
        self::load_addons();
        
        error_log("Orabooks Addon Registry: Merging addon features. Current addon_features count: " . count(self::$addon_features));
        
        // Include features from all registered addons
        // If an addon has registered features, we should show them
        foreach (self::$addon_features as $feature_key => $feature_data) {
            $features[$feature_key] = $feature_data;
        }
        
        return $features;
    }
    
    /**
     * AJAX handler for toggling addon status
     */
    public static function ajax_toggle_addon() {
        check_ajax_referer('orabooks-addon-toggle', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $addon_id = sanitize_key($_POST['addon_id']);
        $action = sanitize_text_field($_POST['toggle_action']);
        
        if ($action === 'enable') {
            $result = self::enable_addon($addon_id);
        } else {
            $result = self::disable_addon($addon_id);
        }
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to toggle addon');
        }
    }
}

// Helper function for addon registration
function orabooks_register_addon($addon_data) {
    return OraBooks_Addon_Registry::register_addon($addon_data);
}

// Helper function for feature registration
function orabooks_register_addon_features($addon_id, $features) {
    return OraBooks_Addon_Registry::register_features($addon_id, $features);
}

// Initialize the addon system
OraBooks_Addon_Registry::init();
