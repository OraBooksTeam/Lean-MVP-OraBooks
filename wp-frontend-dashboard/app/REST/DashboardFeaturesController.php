<?php
namespace WPFD\REST;

/**
 * Dashboard Features Controller - WordPress Native Implementation
 * Provides REST API endpoints for dashboard features
 */

class DashboardFeaturesController {
    
    /**
     * Register REST routes
     */
    public function register() {
        register_rest_route('wpfd/v1', '/dashboard/features', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_features'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'toggle_feature'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
        ]);
        
        register_rest_route('wpfd/v1', '/dashboard/overview', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_overview'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
        ]);
        
        register_rest_route('wpfd/v1', '/dashboard/stats', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_stats'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
        ]);

        
        register_rest_route('wpfd/v1', '/dashboard/upgrade', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_upgrade_plan'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
        ]);
        
        register_rest_route('wpfd/v1', '/dashboard/checkout', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_checkout_plan'],
                'permission_callback' => [$this, 'check_permissions'],
                'args' => [
                    'join_level' => [
                        'required' => false,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);

        register_rest_route('wpfd/v1', '/dashboard/levels', [
            'methods' => 'GET',
            'callback' => [$this, 'get_levels'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
    }
    
    /**
     * Check permissions
     */
    public function check_permissions() {
        return current_user_can('read');
    }
    
    /**
     * Get dashboard features
     */
    public function get_features($request) {
        // Clear cache to ensure fresh data
        $user_id = get_current_user_id();
        wp_cache_delete('wpfd_addon_features_' . get_current_blog_id() . '_' . $user_id);
        wp_cache_delete('wpfd_dashboard_overview_' . $user_id);
        delete_option('wpfd_dashboard_features');
        
        $features = $this->get_dashboard_features();
        
        error_log('WPFD: Features loaded: ' . json_encode($features));
        
        return rest_ensure_response([
            'features' => $features,
            'total_count' => count($features),
            'message' => 'Features loaded successfully',
        ]);
    }
    
    /**
     * Toggle feature status
     */
    public function toggle_feature($request) {
        $feature_key = $request->get_param('feature_key');
        
        if (!$feature_key) {
            return new \WP_Error(
                'invalid_feature',
                'Feature key is required',
                ['status' => 400]
            );
        }
        
        // Get current features
        $features = $this->get_dashboard_features();
        $updated_feature = null;
        
        foreach ($features as &$feature) {
            if ($feature['key'] === $feature_key) {
                $feature['status'] = ($feature['status'] === 'active') ? 'inactive' : 'active';
                $updated_feature = $feature;
                break;
            }
        }
        
        if (!$updated_feature) {
            return new \WP_Error(
                'feature_not_found',
                'Feature not found',
                ['status' => 404]
            );
        }
        
        // Update stored features (you might want to store this in options)
        update_option('wpfd_dashboard_features', $features);
        
        return rest_ensure_response([
            'success' => true,
            'message' => "Feature '{$updated_feature['name']}' " . 
                        (($updated_feature['status'] === 'active') ? 'activated' : 'deactivated') . " successfully",
            'new_status' => $updated_feature['status'],
        ]);
    }
    
    /**
     * Get dashboard overview
     */
    public function get_overview($request) {
        $template_renderer = new \WPFD\Core\TemplateRenderer();
        $overview_data = $template_renderer->get_dashboard_overview();
        
        return rest_ensure_response($overview_data);
    }
    
    /**
     * Get dashboard stats
     */
    public function get_stats($request) {
        $template_renderer = new \WPFD\Core\TemplateRenderer();
        $overview_data = $template_renderer->get_dashboard_overview();
        
        return rest_ensure_response([
            'stats' => $overview_data['stats'],
            'last_updated' => current_time('mysql'),
        ]);
    }

    /**
     * Get Upgrade Plan HTML (integrates with TaxOra membership shortcode)
     */
    public function get_upgrade_plan($request) {
        // Forward query params to $_GET so the shortcode can see them
        if ($request->get_param('orabooks_group')) {
            $_GET['orabooks_group'] = $request->get_param('orabooks_group');
        }
        if ($request->get_param('free_plan_activated')) {
            $_GET['free_plan_activated'] = $request->get_param('free_plan_activated');
        }
        
        // Ensure the TaxOra shortcode is registered
        if (!shortcode_exists('orabooks_upgrade_plan')) {
            return rest_ensure_response([
                'html' => '<div class="p-8 text-center text-gray-500">Upgrade plans are currently unavailable. Please check back later.</div>',
                'title' => 'Upgrade Plan'
            ]);
        }

        // Run the upgrade plan shortcode from TaxOra plugin
        $html = do_shortcode('[orabooks_upgrade_plan]');
        
        // Fix multiple URL issues:
        $current_dashboard_url = home_url('/dashboard');
        $current_site_url = home_url('/');
        
        // 1. Replace REST URL with dashboard URL
        $rest_url = rest_url('wpfd/v1/dashboard/upgrade');
        $html = str_replace($rest_url, $current_dashboard_url, $html);
        
        // 2. Replace any localhost/ redirects with current site
        $html = preg_replace('/https?:\/\/localhost\//', $current_site_url, $html);
        
        // 3. Replace orabooks_get_feature_access_url() output with current dashboard
        if (function_exists('orabooks_get_feature_access_url')) {
            $feature_access_url = orabooks_get_feature_access_url();
            $html = str_replace($feature_access_url, $current_dashboard_url, $html);
        }
        
        // 4. Replace any hardcoded localhost URLs
        $html = str_replace('https://localhost/', $current_site_url, $html);
        $html = str_replace('http://localhost/', $current_site_url, $html);
        
        // 5. Replace any site URL mismatches
        $html = preg_replace('/https?:\/\/localhost\/[^\/]*\//', $current_site_url, $html);

        return rest_ensure_response([
            'html' => $html,
            'title' => 'Upgrade Your Plan'
        ]);
    }

    
    /**
     * Get membership levels data for custom upgrade page
     */
    public function get_levels($request) {
        global $wpdb;

        if (function_exists('orabooks_handle_multisite_tables')) {
            orabooks_handle_multisite_tables();
        }

        $groups = $wpdb->get_results("
            SELECT g.*,
                   (SELECT COUNT(*) FROM {$wpdb->orabooks_levels} l WHERE l.group_id = g.id AND l.is_active = 1) as levels_count,
                   (SELECT MIN(price) FROM {$wpdb->orabooks_levels} l WHERE l.group_id = g.id AND l.price > 0 AND l.is_active = 1) as min_price,
                   (SELECT MAX(price) FROM {$wpdb->orabooks_levels} l WHERE l.group_id = g.id AND l.is_active = 1) as max_price
            FROM {$wpdb->orabooks_groups} g
            ORDER BY g.name
        ") ?: [];

        $result = [];
        foreach ($groups as $group) {
            $levels = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM {$wpdb->orabooks_levels}
                WHERE group_id = %d AND is_active = 1
                ORDER BY price ASC
            ", $group->id)) ?: [];

            $result[] = [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'levels_count' => $group->levels_count,
                'min_price' => $group->min_price,
                'max_price' => $group->max_price,
                'levels' => array_map(function($l) {
                    return [
                        'id' => $l->id,
                        'name' => $l->name,
                        'description' => $l->description,
                        'price' => $l->price,
                        'billing_period' => $l->billing_period,
                        'label' => $l->label,
                        // Force BDT/Taka symbol for frontend dashboard display
                        'currency' => 'BDT',
                        'currency_symbol' => '৳',
                    ];
                }, $levels),
            ];
        }

        return rest_ensure_response(['groups' => $result]);
    }

    /**
     * Get Checkout Plan HTML (renders membership shortcode)
     */
    public function get_checkout_plan($request) {
        global $wpdb;

        if (!function_exists('orabooks_handle_multisite_tables')) {
            return new \WP_Error('function_missing', 'Membership plugin not active', ['status' => 500]);
        }

        orabooks_handle_multisite_tables();
        $level_id = $request->get_param('join_level') ? intval($request->get_param('join_level')) : 0;

        if (!$level_id) {
            return new \WP_Error('invalid_level', 'No plan selected', ['status' => 400]);
        }

        $level = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->orabooks_levels} WHERE id=%d AND is_active=1",
            $level_id
        ));

        if (!$level) {
            return new \WP_Error('not_found', 'Plan not found', ['status' => 404]);
        }

        $is_free = $level->price == 0;
        $user_id = get_current_user_id();
        $gateways_html = '';
        $gateways_list = [];
        // Force Taka symbol for all dashboard checkout displays
        $price_display = number_format($level->price, 2) . '৳';

        if (!is_user_logged_in()) {
            $login_url = home_url('/login/?redirect_to=' . urlencode(home_url('/dashboard')));
            return rest_ensure_response([
                'html' => '<div class="p-8 text-center"><h3 class="text-xl font-bold text-gray-900 mb-3">Login Required</h3><p class="text-gray-600 mb-6">Please log in to complete your purchase.</p><a href="' . esc_url($login_url) . '" class="inline-block py-3 px-6 bg-primary-600 text-white rounded-xl font-bold">Log In</a></div>',
                'title' => 'Login Required',
                'level' => $level_id,
                'payment_required' => false,
            ]);
        }

        if ($is_free) {
            return rest_ensure_response([
                'html' => '',
                'title' => 'Activate Free Plan',
                'level' => $level_id,
                'payment_required' => false,
                'free' => true,
                'level_name' => $level->name,
                'level_price' => 0,
                'free_nonce' => wp_create_nonce('orabooks_free_checkout'),
            ]);
        }

        // Get payment gateways
        if (function_exists('orabooks_init_payment_gateways')) {
            $gateways = orabooks_init_payment_gateways();
            foreach ($gateways as $gateway_id => $gateway) {
                // Always show ShurjoPay if configured (matches shortcode behavior)
                if ($gateway->is_available() || $gateway_id === 'shurjopay') {
                    $gateways_list[] = [
                        'id' => $gateway_id,
                        'title' => $gateway->get_title(),
                        'description' => $gateway->get_description(),
                    ];
                }
            }
        }

        return rest_ensure_response([
            'html' => '',
            'title' => 'Complete Purchase',
            'level' => $level_id,
            'payment_required' => true,
            'free' => false,
            'level_name' => $level->name,
            'level_price' => $level->price,
            'level_price_display' => $price_display,
            'billing_period' => $level->billing_period,
            'level_description' => $level->description,
            'gateways' => $gateways_list,
            'nonce' => wp_create_nonce('orabooks_payment_nonce'),
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }
    
    /**
     * Get dashboard features from various sources
     */
    private function get_dashboard_features() {
        // Only get membership subscription panel features
        $features = [];
        
        // Get features from TaxOra membership plugin ONLY
        $membership_features = $this->get_membership_features();
        $features = array_merge($features, $membership_features);
        
        return $features;
    }
    
    /**
     * Get WordPress core features
     */
    private function get_core_features() {
        $features = [];
        
        // Posts
        if (current_user_can('edit_posts')) {
            $features[] = [
                'key' => 'posts',
                'name' => 'Posts Management',
                'description' => 'Create and manage blog posts with advanced editing features.',
                'icon' => 'filetext',
                'url' => admin_url('edit.php'),
                'category' => 'Content',
                'status' => 'active',
            ];
        }
        
        // Pages
        if (current_user_can('edit_pages')) {
            $features[] = [
                'key' => 'pages',
                'name' => 'Pages Management',
                'description' => 'Create and manage static pages for your website.',
                'icon' => 'filetext',
                'url' => admin_url('edit.php?post_type=page'),
                'category' => 'Content',
                'status' => 'active',
            ];
        }
        
        // Media
        if (current_user_can('upload_files')) {
            $features[] = [
                'key' => 'media',
                'name' => 'Media Library',
                'description' => 'Upload and manage images, videos, and other media files.',
                'icon' => 'database',
                'url' => admin_url('upload.php'),
                'category' => 'Content',
                'status' => 'active',
            ];
        }
        
        // Comments
        if (current_user_can('moderate_comments')) {
            $features[] = [
                'key' => 'comments',
                'name' => 'Comments Management',
                'description' => 'Moderate and manage user comments on your content.',
                'icon' => 'users',
                'url' => admin_url('edit-comments.php'),
                'category' => 'Content',
                'status' => 'active',
            ];
        }
        
        // Users
        if (current_user_can('list_users')) {
            $features[] = [
                'key' => 'users',
                'name' => 'User Management',
                'description' => 'Manage user accounts, roles, and permissions.',
                'icon' => 'users',
                'url' => admin_url('users.php'),
                'category' => 'Administration',
                'status' => 'active',
            ];
        }
        
        // Settings
        if (current_user_can('manage_options')) {
            $features[] = [
                'key' => 'settings',
                'name' => 'Site Settings',
                'description' => 'Configure site settings, plugins, and themes.',
                'icon' => 'settings',
                'url' => admin_url('options-general.php'),
                'category' => 'Administration',
                'status' => 'active',
            ];
        }
        
        return $features;
    }
    
    /**
     * Get plugin features
     */
    private function get_plugin_features() {
        $features = [];
        
        // Check for common plugins
        if (is_plugin_active('woocommerce/woocommerce.php')) {
            $features[] = [
                'key' => 'woocommerce',
                'name' => 'WooCommerce',
                'description' => 'Manage your online store and products.',
                'icon' => 'package',
                'url' => admin_url('admin.php?page=wc-admin'),
                'category' => 'E-commerce',
                'status' => 'active',
            ];
        }
        
        if (is_plugin_active('jetpack/jetpack.php')) {
            $features[] = [
                'key' => 'jetpack',
                'name' => 'Jetpack',
                'description' => 'Enhance your site with Jetpack features.',
                'icon' => 'zap',
                'url' => admin_url('admin.php?page=jetpack'),
                'category' => 'Enhancements',
                'status' => 'active',
            ];
        }
        
        if (is_plugin_active('wordfence/wordfence.php')) {
            $features[] = [
                'key' => 'wordfence',
                'name' => 'Wordfence Security',
                'description' => 'Protect your site with Wordfence security.',
                'icon' => 'shield',
                'url' => admin_url('admin.php?page=Wordfence'),
                'category' => 'Security',
                'status' => 'active',
            ];
        }
        
        // Add more plugin checks as needed
        
        return $features;
    }
    
    /**
     * Get theme features
     */
    private function get_theme_features() {
        $features = [];
        $theme = wp_get_theme();
        
        // Theme customizer
        $features[] = [
            'key' => 'theme_customizer',
            'name' => 'Theme Customizer',
            'description' => 'Customize your ' . $theme->get('Name') . ' theme appearance.',
            'icon' => 'settings',
            'url' => admin_url('customize.php'),
            'category' => 'Appearance',
            'status' => 'active',
        ];
        
        // Widgets
        $features[] = [
            'key' => 'widgets',
            'name' => 'Widget Management',
            'description' => 'Manage sidebar widgets and layout elements.',
            'icon' => 'package',
            'url' => admin_url('widgets.php'),
            'category' => 'Appearance',
            'status' => 'active',
        ];
        
        // Menus
        $features[] = [
            'key' => 'menus',
            'name' => 'Menu Management',
            'description' => 'Create and manage navigation menus.',
            'icon' => 'globe',
            'url' => admin_url('nav-menus.php'),
            'category' => 'Appearance',
            'status' => 'active',
        ];
        
        return $features;
    }
    
    /**
     * Get custom features
     */
    private function get_custom_features() {
        $features = [];
        
        // Get features from TaxOra membership plugin ONLY
        $membership_features = $this->get_membership_features();
        $features = array_merge($features, $membership_features);
        
        return $features;
    }
    
    /**
     * Get features from TaxOra membership plugin
     */
    private function get_membership_features() {
        $features = [];
        $user_id = get_current_user_id();
        
        // Include debug functions for testing (Removed to prevent false feature granting)
        $debug_path = dirname(dirname(dirname(__DIR__))) . '/TaxOra - WPMU Membership Subscription Panel/includes/debug-features.php';
        // if (file_exists($debug_path)) {
        //     include_once $debug_path;
        // }
        
        // Include sample features (Removed to rely on actual plugin detection)
        $sample_path = dirname(dirname(dirname(__DIR__))) . '/TaxOra - WPMU Membership Subscription Panel/includes/sample-addon-features.php';
        // if (file_exists($sample_path)) {
        //     include_once $sample_path;
        // }
        
        // Get user's activated features from database
        $activated_features = $this->get_user_activated_features($user_id);
        
        // Get available features from TaxOra
        $available_features = [];
        if (function_exists('orabooks_register_addon')) {
            $available_features = apply_filters('orabooks_available_features', $available_features);
        }
        
        // Filter to show only main addon features, not granular sub-features
        $main_addon_features = array();
        foreach ($available_features as $feature_key => $feature_data) {
            // Exclude features that have a 'parent' field (granular sub-features)
            if (isset($feature_data['parent'])) {
                continue;
            }
            // Include all top-level features: core features and main addon features
            $main_addon_features[$feature_key] = $feature_data;
        }

        $available_features = $main_addon_features;

        error_log('WPFD: Available features: ' . json_encode($available_features));
        error_log('WPFD: Main addon features count: ' . count($available_features));

        // Fallback: if no activated features found, show all available features
        if (empty($activated_features)) {
            error_log('WPFD: No activated features found - showing all available features');
            $activated_features = array_keys($available_features);
        }

        // Only show features that are both available AND activated for the user
        foreach ($activated_features as $feature_key) {
            if (isset($available_features[$feature_key])) {
                $feature = $available_features[$feature_key];
                
                // Special handling for inventory URL
                $feature_url = $feature['url'] ?? '#';
                if ($feature_key === 'inventory') {
                    // Check if inventory page exists
                    $inventory_page = get_page_by_path('inventory');
                    if ($inventory_page) {
                        $feature_url = get_permalink($inventory_page->ID);
                    } else {
                        // Create inventory page automatically
                        $this->create_inventory_page();
                        $inventory_page = get_page_by_path('inventory');
                        if ($inventory_page) {
                            $feature_url = get_permalink($inventory_page->ID);
                        }
                    }
                }
                
                // Special handling for accounting URL
                if ($feature_key === 'accounting') {
                    // Check if accounting page exists
                    $accounting_page = get_page_by_path('accounting');
                    if ($accounting_page) {
                        $feature_url = get_permalink($accounting_page->ID);
                    } else {
                        // Create accounting page automatically
                        $this->create_accounting_page();
                        $accounting_page = get_page_by_path('accounting');
                        if ($accounting_page) {
                            $feature_url = get_permalink($accounting_page->ID);
                        }
                    }
                }
                
                $features[] = [
                    'key' => $feature_key,
                    'name' => $feature['name'],
                    'description' => $feature['description'],
                    'icon' => $feature['icon'] ?? 'package',
                    'url' => $feature_url,
                    'category' => $feature['category'] ?? 'Membership',
                    'status' => 'active',
                ];
            }
        }
        
        error_log('WPFD: Final features to return: ' . json_encode($features));
        
        return $features;
    }
    
    /**
     * Create inventory page automatically if it doesn't exist
     */
    private function create_inventory_page() {
        // Check if page already exists
        $existing_page = get_page_by_path('inventory');
        
        if (!$existing_page) {
            // Create the page
            $page_data = array(
                'post_title'    => 'Inventory Management',
                'post_content'  => '<!-- wp:shortcode -->[orabooks_inventory]<!-- /wp:shortcode -->',
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'post_author'   => get_current_user_id(),
                'post_name'     => 'inventory'
            );
            
            $page_id = wp_insert_post($page_data);
        }
    }
    
    /**
     * Create accounting page automatically if it doesn't exist
     */
    private function create_accounting_page() {
        // Check if page already exists
        $existing_page = get_page_by_path('accounting');
        
        if (!$existing_page) {
            // Create the page
            $page_data = array(
                'post_title'    => 'Accounting Management',
                'post_content'  => '<!-- wp:shortcode -->[orabooks_accounting]<!-- /wp:shortcode -->',
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'post_author'   => get_current_user_id(),
                'post_name'     => 'accounting'
            );
            
            $page_id = wp_insert_post($page_data);
        }
    }
    
    /**
     * Get user's activated features from database
     */
    private function get_user_activated_features($user_id) {
        global $wpdb;
        
        // Handle multisite table naming
        if (function_exists('orabooks_handle_multisite_tables')) {
            orabooks_handle_multisite_tables();
            $table_name = $wpdb->orabooks_feature_assignments;
        } else {
            $table_name = $wpdb->prefix . 'orabooks_feature_assignments';
        }
        
        // Get user's level
        $level_id = get_user_meta($user_id, 'orabooks_level', true);
        
        if (!$level_id) {
            error_log("WPFD: User $user_id has no level assigned");
            return array();
        }
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            error_log("WPFD: Feature assignments table does not exist: $table_name");
            return array();
        }
        
        // Get user's features
        $user_features = $wpdb->get_col($wpdb->prepare(
            "SELECT feature_key FROM $table_name WHERE level_id = %d",
            $level_id
        ));
        
        error_log('WPFD: User ' . $user_id . ' level ' . $level_id . ' features from DB: ' . json_encode($user_features));
        
        return $user_features;
    }
}
