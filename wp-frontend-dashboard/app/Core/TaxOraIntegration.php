<?php
namespace WPFD\Core;

/**
 * TaxOra Integration Handler
 * 
 * Provides enhanced integration with TaxOra Membership Plugin
 * for seamless dashboard and membership functionality
 */
class TaxOraIntegration {
    
    private static $instance = null;
    private $taxora_active = false;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->taxora_active = defined('ORABOOKS_VERSION');
        
        if ($this->taxora_active) {
            $this->init_integration();
        }
    }
    
    /**
     * Initialize TaxOra integration
     */
    private function init_integration() {
        // Dashboard enhancements
        add_filter('wpfd_dashboard_widgets', [$this, 'add_membership_widgets'], 10, 1);
        add_filter('wpfd_navigation_items', [$this, 'add_taxora_navigation'], 10, 1);
        add_filter('wpfd_user_data', [$this, 'enrich_user_data'], 10, 1);
        
        // Asset coordination
        add_action('wp_enqueue_scripts', [$this, 'coordinate_taxora_assets'], 15);
        add_action('admin_enqueue_scripts', [$this, 'coordinate_taxora_admin_assets'], 15);
        
        // Route coordination
        add_filter('wpfd_route_access_check', [$this, 'check_membership_access'], 10, 2);
        
        // REST API enhancements
        add_action('rest_api_init', [$this, 'register_taxora_endpoints'], 15);
        
        // Menu coordination
        add_filter('wpfd_menu_badges', [$this, 'add_membership_badges'], 10, 1);
        
        // Template enhancements
        add_filter('wpfd_template_data', [$this, 'add_taxora_template_data'], 10, 1);
    }
    
    /**
     * Add membership-related widgets to dashboard
     */
    public function add_membership_widgets($widgets) {
        if (!$this->taxora_active) {
            return $widgets;
        }
        
        $user_id = get_current_user_id();
        $membership_data = $this->get_user_membership_data($user_id);
        
        // Membership Status Widget
        $widgets['membership_status'] = [
            'title' => 'Membership Status',
            'template' => 'widgets/membership-status',
            'data' => $membership_data,
            'priority' => 5
        ];
        
        // Features Widget
        if (!empty($membership_data['features'])) {
            $widgets['features'] = [
                'title' => 'Your Features',
                'template' => 'widgets/features',
                'data' => [
                    'features' => $membership_data['features'],
                    'level_name' => $membership_data['level_name'] ?? 'Unknown'
                ],
                'priority' => 10
            ];
        }
        
        // Site Management Widget (if user has a site)
        if (!empty($membership_data['site_info'])) {
            $widgets['site_management'] = [
                'title' => 'Site Management',
                'template' => 'widgets/site-management',
                'data' => $membership_data['site_info'],
                'priority' => 15
            ];
        }
        
        return $widgets;
    }
    
    /**
     * Add TaxOra navigation items
     */
    public function add_taxora_navigation($items) {
        if (!$this->taxora_active) {
            return $items;
        }
        
        $user_id = get_current_user_id();
        $features = $this->get_user_features($user_id);
        
        // Add Accounting navigation if enabled
        if (in_array('accounting', $features)) {
            $items[] = [
                'title' => 'Accounting',
                'url' => home_url('/accounting'),
                'icon' => 'dashicons-calculator',
                'badge' => null
            ];
        }
        
        // Add Inventory navigation if enabled
        if (in_array('inventory', $features)) {
            $items[] = [
                'title' => 'Inventory',
                'url' => home_url('/inventory'),
                'icon' => 'dashicons-archive',
                'badge' => null
            ];
        }
        
        // Add Upgrade/Plan navigation
        $membership_data = $this->get_user_membership_data($user_id);
        if ($membership_data['subscription_active']) {
            $items[] = [
                'title' => 'Upgrade Plan',
                'url' => home_url('/upgrade-plan'),
                'icon' => 'dashicons-star-filled',
                'badge' => null
            ];
        } else {
            $items[] = [
                'title' => 'Choose Plan',
                'url' => home_url('/pricing'),
                'icon' => 'dashicons-star-empty',
                'badge' => 'Required'
            ];
        }
        
        return $items;
    }
    
    /**
     * Enrich user data with TaxOra information
     */
    public function enrich_user_data($user_data) {
        if (!$this->taxora_active) {
            return $user_data;
        }
        
        $user_id = get_current_user_id();
        $membership_data = $this->get_user_membership_data($user_id);
        
        $user_data['taxora'] = $membership_data;
        
        return $user_data;
    }
    
    /**
     * Get user membership data
     */
    private function get_user_membership_data($user_id) {
        $data = [
            'subscription_active' => false,
            'level_name' => null,
            'features' => [],
            'site_info' => null
        ];
        
        // Ensure multisite tables are initialized
        if (function_exists('orabooks_handle_multisite_tables')) {
            orabooks_handle_multisite_tables();
        }
        
        global $wpdb;
        
        // Safety check: verify table exists before querying
        $table_name = isset($wpdb->orabooks_subscriptions) ? $wpdb->orabooks_subscriptions : null;
        if (!$table_name) {
            return $data;
        }
        
        // Get subscription
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->orabooks_subscriptions} WHERE user_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1",
            $user_id
        ));
        
        if ($subscription) {
            $data['subscription_active'] = true;
            $data['subscription_id'] = $subscription->id;
            
            // Get level
            $user_level = get_user_meta($user_id, 'orabooks_level', true);
            if ($user_level) {
                $level = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->orabooks_levels} WHERE id = %d",
                    $user_level
                ));
                
                if ($level) {
                    $data['level_name'] = $level->name;
                    $data['level_id'] = $level->id;
                }
            }
        }
        
        // Get features
        $data['features'] = $this->get_user_features($user_id);
        
        // Get site info
        $user_site_id = get_user_meta($user_id, 'orabooks_site_id', true);
        if ($user_site_id && (int)$user_site_id > 1) {
            switch_to_blog($user_site_id);
            $posts_count = wp_count_posts();
            $pages_count = wp_count_posts('page');
            $data['site_info'] = [
                'site_id' => $user_site_id,
                'site_url' => get_site_url(),
                'site_title' => get_bloginfo('name'),
                'admin_url' => admin_url(),
                'posts_count' => isset($posts_count->publish) ? $posts_count->publish : 0,
                'pages_count' => isset($pages_count->publish) ? $pages_count->publish : 0
            ];
            restore_current_blog();
        }
        
        return $data;
    }
    
    /**
     * Get user features
     */
    private function get_user_features($user_id) {
        $features = [];
        
        // Check membership-level features from TaxOra feature_assignments table
        global $wpdb;
        $user_level = get_user_meta($user_id, 'orabooks_level', true);
        if ($user_level && isset($wpdb->orabooks_feature_assignments)) {
            $assigned_features = $wpdb->get_results($wpdb->prepare(
                "SELECT feature_key FROM {$wpdb->orabooks_feature_assignments} WHERE level_id = %d",
                $user_level
            ));
            foreach ($assigned_features as $af) {
                $features[] = $af->feature_key;
            }
        }
        
        // Fallback: Use legacy feature check function
        if (empty($features) && function_exists('orabooks_user_has_feature_access')) {
            $taxora_features = ['accounting', 'inventory', 'analytics', 'customization', 'reporting'];
            foreach ($taxora_features as $feature) {
                if (orabooks_user_has_feature_access($user_id, $feature)) {
                    $features[] = $feature;
                }
            }
        }
        
        return $features;
    }
    
    /**
     * Coordinate TaxOra assets
     */
    public function coordinate_taxora_assets() {
        if (!$this->taxora_active) {
            return;
        }
        
        $route = get_query_var('wpfd_route');
        if ($route !== 'dashboard') {
            return;
        }
        
        // Ensure TaxOra CSS doesn't conflict with dashboard
        wp_dequeue_style('orabooks-tailwind');
        wp_dequeue_style('orabooks-frontend');
        
        // Load TaxOra-specific dashboard enhancements
        $base_dir = dirname(dirname(dirname(__FILE__)));
        $assets_url = plugin_dir_url($base_dir . '/wp-frontend-dashboard.php') . 'assets/';
        
        if (file_exists($base_dir . '/assets/css/taxora-integration.css')) {
            wp_enqueue_style(
                'wpfd-taxora-integration',
                $assets_url . 'css/taxora-integration.css',
                ['wpfd-dashboard'],
                '1.0.0'
            );
        }
    }
    
    /**
     * Coordinate TaxOra admin assets
     */
    public function coordinate_taxora_admin_assets() {
        if (!$this->taxora_active) {
            return;
        }
        
        // Allow TaxOra assets in iframe mode
        if (isset($_GET['wpfd_iframe']) && $_GET['wpfd_iframe'] === '1') {
            // Keep essential TaxOra admin functionality
            if (class_exists('Orabooks_Client_Dashboard_Manager')) {
                // Allow selective loading
                add_action('admin_head', function() {
                    echo '<style>
                        /* Preserve TaxOra admin functionality in iframe */
                        .orabooks-admin-tabs { display: block !important; }
                        .wrap > h1:first-child { display: block !important; }
                    </style>';
                });
            }
        }
    }
    
    /**
     * Check membership access for routes
     */
    public function check_membership_access($access_granted, $route) {
        if (!$this->taxora_active || $access_granted) {
            return $access_granted;
        }
        
        if ($route === 'dashboard' && is_multisite() && !is_main_site()) {
            $user_id = get_current_user_id();
            $membership_data = $this->get_user_membership_data($user_id);
            
            // Require active subscription for dashboard access on subsites
            if (!$membership_data['subscription_active']) {
                return false;
            }
        }
        
        return $access_granted;
    }
    
    /**
     * Register TaxOra-specific REST endpoints
     */
    public function register_taxora_endpoints() {
        // Membership status endpoint
        register_rest_route('wpfd/v1', '/taxora/membership', [
            'methods' => 'GET',
            'callback' => [$this, 'get_membership_status'],
            'permission_callback' => function() {
                return is_user_logged_in() && wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'wp_rest');
            }
        ]);
        
        // Features endpoint
        register_rest_route('wpfd/v1', '/taxora/features', [
            'methods' => 'GET',
            'callback' => [$this, 'get_features'],
            'permission_callback' => function() {
                return is_user_logged_in() && wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'wp_rest');
            }
        ]);
        
        // Site stats endpoint
        register_rest_route('wpfd/v1', '/taxora/site-stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_site_stats'],
            'permission_callback' => function() {
                return is_user_logged_in() && wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'wp_rest');
            }
        ]);
    }
    
    /**
     * REST endpoint: Get membership status
     */
    public function get_membership_status($request) {
        $user_id = get_current_user_id();
        $membership_data = $this->get_user_membership_data($user_id);
        
        return new \WP_REST_Response([
            'membership' => $membership_data,
            'can_upgrade' => $membership_data['subscription_active'],
            'upgrade_url' => home_url('/upgrade-plan')
        ]);
    }
    
    /**
     * REST endpoint: Get features
     */
    public function get_features($request) {
        $user_id = get_current_user_id();
        $features = $this->get_user_features($user_id);
        
        $feature_details = [];
        foreach ($features as $feature) {
            $feature_details[$feature] = [
                'name' => ucfirst($feature),
                'url' => home_url('/' . $feature),
                'icon' => $this->get_feature_icon($feature)
            ];
        }
        
        return new \WP_REST_Response([
            'features' => $feature_details
        ]);
    }
    
    /**
     * REST endpoint: Get site statistics
     */
    public function get_site_stats($request) {
        $user_id = get_current_user_id();
        $membership_data = $this->get_user_membership_data($user_id);
        
        if (empty($membership_data['site_info'])) {
            return new \WP_REST_Response(['error' => 'No site found'], 404);
        }
        
        return new \WP_REST_Response([
            'site' => $membership_data['site_info']
        ]);
    }
    
    /**
     * Get feature icon
     */
    private function get_feature_icon($feature) {
        $icons = [
            'accounting' => 'dashicons-calculator',
            'inventory' => 'dashicons-archive',
            'analytics' => 'dashicons-chart-bar',
            'customization' => 'dashicons-admin-customizer',
            'reporting' => 'dashicons-media-spreadsheet'
        ];
        
        return $icons[$feature] ?? 'dashicons-admin-plugins';
    }
    
    /**
     * Add membership badges to menu items
     */
    public function add_membership_badges($badges) {
        if (!$this->taxora_active) {
            return $badges;
        }
        
        $user_id = get_current_user_id();
        $membership_data = $this->get_user_membership_data($user_id);
        
        if (!$membership_data['subscription_active']) {
            $badges['dashboard'] = [
                'text' => 'Upgrade Required',
                'class' => 'bg-warning'
            ];
        }
        
        return $badges;
    }
    
    /**
     * Add TaxOra data to templates
     */
    public function add_taxora_template_data($data) {
        if (!$this->taxora_active) {
            return $data;
        }
        
        $user_id = get_current_user_id();
        $data['taxora'] = $this->get_user_membership_data($user_id);
        
        return $data;
    }
}
