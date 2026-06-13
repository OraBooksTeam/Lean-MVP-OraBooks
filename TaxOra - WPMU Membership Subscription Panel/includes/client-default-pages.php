<?php
/**
 * Client Default Pages Setup
 * 
 * Creates default pages on client sites with same content as main site
 * but with client-specific functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create all required pages for the plugin (Main site and Client sites)
 */
function orabooks_create_required_pages() {
    $current_blog_id = get_current_blog_id();
    
    if ($current_blog_id == 1) {
        // Main Site Pages
        $pages = array(
            'pricing' => array(
                'title' => 'Membership Plans',
                'content' => '[orabooks_levels]',
                'option' => 'orabooks_pricing_page_id'
            ),
            'checkout' => array(
                'title' => 'Checkout',
                'content' => '[orabooks_checkout]',
                'option' => 'orabooks_checkout_page_id'
            ),
            'my-account' => array(
                'title' => 'My Account',
                'content' => '[orabooks_my_account]',
                'option' => 'orabooks_account_page_id'
            ),
            'features' => array(
                'title' => 'Feature Access',
                'content' => '[orabooks_features]',
                'option' => 'orabooks_features_page_id'
            ),
            'confirmation' => array(
                'title' => 'Confirmation',
                'content' => '[orabooks_confirmation]',
                'option' => 'orabooks_confirmation_page_id'
            ),
            'payment-success' => array(
                'title' => 'Payment Success',
                'content' => '<div class="orabooks-page-wrapper" style="padding: 40px 0; max-width: 800px; margin: 0 auto;">[orabooks_payment_success]</div>',
                'option' => 'orabooks_payment_success_page'
            ),
            'payment-failed' => array(
                'title' => 'Payment Failed',
                'content' => '<div class="orabooks-page-wrapper" style="padding: 40px 0; max-width: 800px; margin: 0 auto;">[orabooks_payment_failed]</div>',
                'option' => 'orabooks_payment_failure_page'
            ),
            'accounting' => array(
                'title' => 'Accounting',
                'content' => '[orabooks_accounting]',
                'option' => ''
            ),
            'inventory' => array(
                'title' => 'Inventory',
                'content' => '[orabooks_inventory]',
                'option' => ''
            ),
            'landing-page' => array(
                'title' => 'Landing Page',
                'content' => '[orabooks_client_home]',
                'option' => ''
            ),
            'dashboard' => array(
                'title' => 'Dashboard',
                'content' => '[wpfd_dashboard]',
                'option' => 'orabooks_dashboard_page'
            ),
            'forgot-password' => array(
                'title' => 'Forget Password',
                'content' => '[forgot_password]',
                'option' => ''
            ),
            'login' => array(
                'title' => 'Login',
                'content' => '[login_widget]',
                'option' => ''
            ),
            'register' => array(
                'title' => 'Register',
                'content' => '[orabooks_register]',
                'option' => ''
            )
        );
    } else {
        // Client Site Pages
        $pages = array(
            'landing-page' => array(
                'title' => 'Landing Page',
                'content' => '[orabooks_client_home]',
                'option' => '' // Don't set as page_on_front to avoid redirect
            ),
            'dashboard' => array(
                'title' => 'Dashboard',
                'content' => '[wpfd_dashboard]',
                'option' => 'orabooks_dashboard_page'
            ),
            'forgot-password' => array(
                'title' => 'Forget Password',
                'content' => '[forgot_password]',
                'option' => ''
            ),
            'my-account' => array(
                'title' => 'My Account',
                'content' => '[orabooks_my_account]',
                'option' => ''
            ),
            'features' => array(
                'title' => 'Features',
                'content' => '[orabooks_client_features]',
                'option' => ''
            ),
            'payment-success' => array(
                'title' => 'Payment Success',
                'content' => '<div class="orabooks-page-wrapper" style="padding: 40px 0; max-width: 800px; margin: 0 auto;">[orabooks_payment_success]</div>',
                'option' => 'orabooks_payment_success_page'
            ),
            'payment-failed' => array(
                'title' => 'Payment Failed',
                'content' => '<div class="orabooks-page-wrapper" style="padding: 40px 0; max-width: 800px; margin: 0 auto;">[orabooks_payment_failed]</div>',
                'option' => 'orabooks_payment_failure_page'
            ),
            'upgrade-plan' => array(
                'title' => 'Upgrade Plan',
                'content' => '[orabooks_upgrade_plan]',
                'option' => ''
            ),
            'login' => array(
                'title' => 'Login',
                'content' => '[login_widget]',
                'option' => ''
            )
        );
    }

    foreach ($pages as $slug => $data) {
        $existing_page = get_page_by_path($slug);
        $page_id = 0;
        
        if (!$existing_page) {
            $page_id = wp_insert_post(array(
                'post_title' => $data['title'],
                'post_name' => $slug,
                'post_content' => $data['content'],
                'post_status' => 'publish',
                'post_type' => 'page'
            ));
        } else {
            $page_id = $existing_page->ID;

            // Preserve custom landing page edits, but ensure a default exists if the page is empty.
            if ($slug === 'landing-page') {
                if (trim($existing_page->post_content) === '') {
                    wp_update_post(array(
                        'ID' => $page_id,
                        'post_content' => $data['content']
                    ));
                }
            } elseif ($existing_page->post_content !== $data['content']) {
                wp_update_post(array(
                    'ID' => $page_id,
                    'post_content' => $data['content']
                ));
            }
        }
        
        if ($page_id && !empty($data['option'])) {
            update_option($data['option'], $page_id);
        }

        if ($page_id && get_current_blog_id() !== 1) {
            // Mark default client pages as hidden from the client page editor list.
            if ($slug !== 'landing-page') {
                update_post_meta($page_id, '_orabooks_default_page', '1');
            } else {
                delete_post_meta($page_id, '_orabooks_default_page');
            }
        }
    }
}

/**
 * Filter client page lists so clients only see landing page and pages they create.
 */
add_action('pre_get_posts', 'orabooks_filter_client_page_list', 20);
function orabooks_filter_client_page_list($query) {
    if (!get_current_user_id()) {
        return;
    }

    if (get_current_blog_id() === 1) {
        return;
    }

    if (!is_admin() && !(defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    if ($query->get('post_type') === 'page') {
        $meta_query = $query->get('meta_query');
        if (!is_array($meta_query)) {
            $meta_query = array();
        }

        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key' => '_orabooks_default_page',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => '_orabooks_default_page',
                'value' => '0',
                'compare' => '='
            )
        );

        $query->set('meta_query', $meta_query);
    }
}

add_filter('user_has_cap', 'orabooks_restrict_client_default_page_editing', 10, 4);
function orabooks_restrict_client_default_page_editing($allcaps, $caps, $args, $user) {
    if (get_current_blog_id() === 1 || is_super_admin($user->ID)) {
        return $allcaps;
    }

    $cap = isset($args[0]) ? $args[0] : '';
    $post_id = isset($args[2]) ? absint($args[2]) : 0;
    $restricted_caps = array('edit_post', 'delete_post', 'edit_page', 'delete_page');

    if (!$post_id || !in_array($cap, $restricted_caps, true)) {
        return $allcaps;
    }

    if (get_post_meta($post_id, '_orabooks_default_page', true)) {
        foreach ($caps as $primitive_cap) {
            $allcaps[$primitive_cap] = false;
        }
    }

    return $allcaps;
}

/**
 * Hook into admin_init to ensure pages exist
 */
add_action('admin_init', 'orabooks_ensure_pages_exist');
function orabooks_ensure_pages_exist() {
    if (get_option('orabooks_pages_created_v2')) {
        return;
    }
    orabooks_create_required_pages();
    update_option('orabooks_pages_created_v2', 1);
}

/**
 * Create default pages when new site is created
 */
add_action('wpmu_new_blog', 'orabooks_create_client_default_pages', 50, 6);

function orabooks_create_client_default_pages($blog_id, $user_id, $domain, $path, $site_id, $meta) {
    switch_to_blog($blog_id);
    orabooks_create_required_pages();
    
    // Set to show landing page as front page
    update_option('show_on_front', 'page');
    
    // Set landing page as front page
    $landing_page = get_page_by_path('landing-page');
    if ($landing_page) {
        update_option('page_on_front', $landing_page->ID);
    }
    
    // Ensure dashboard page option is set
    $dashboard_page = get_page_by_path('ora-dashboard');
    if ($dashboard_page) {
        update_option('orabooks_dashboard_page', $dashboard_page->ID);
    }
    
    restore_current_blog();
}

/**
 * Create default menu for client site
 */
function orabooks_create_client_default_menu($blog_id, $user_id) {
    // Check if default menu already exists
    $menu_name = 'Default Menu';
    $menu_exists = wp_get_nav_menu_object($menu_name);
    
    if (!$menu_exists) {
        // Create the menu
        $menu_id = wp_create_nav_menu($menu_name);
        
        // Get page IDs
        $features_page = get_page_by_path('features');
        $my_account_page = get_page_by_path('my-account');
        $register_page = get_page_by_path('register');
        
        // Add menu items
        $menu_items = array();

        $dashboard_page = get_page_by_path('ora-dashboard');
        if ($dashboard_page) {
            $menu_items[] = array(
                'menu-item-title' => 'Dashboard',
                'menu-item-object-id' => $dashboard_page->ID,
                'menu-item-object' => 'page',
                'menu-item-type' => 'post_type',
                'menu-item-status' => 'publish',
                'menu-item-position' => 0,
                'menu-item-url' => get_permalink($dashboard_page->ID)
            );
        }

        // 3. My Account
        if ($my_account_page) {
            $menu_items[] = array(
                'menu-item-title' => 'My Account',
                'menu-item-object-id' => $my_account_page->ID,
                'menu-item-object' => 'page',
                'menu-item-type' => 'post_type',
                'menu-item-status' => 'publish',
                'menu-item-position' => 2,
                'menu-item-classes' => 'logged-in-only'
            );
        }


        

        // Register page removed as requested - registration only from main site

        
        // Insert menu items
        foreach ($menu_items as $item) {
            wp_update_nav_menu_item($menu_id, 0, $item);
        }
        
        // Set as primary menu
        $locations = get_theme_mod('nav_menu_locations');
        if (!is_array($locations)) {
            $locations = array();
        }
        $locations['primary'] = $menu_id;
        // Assign only to primary to avoid double-rendering in themes that output 'header' and 'primary'
        set_theme_mod('nav_menu_locations', $locations);
    }
}


    /*
     * Dashboard Shortcode
     * Renders a simple user dashboard with account links and feature summary.
     * Usage: [orabooks_client_dashboard]
     * Handled by Orabooks_Client_Dashboard_Manager in includes/frontend/client-dashboard-manager.php
     */
    /*
    if (!function_exists('orabooks_client_dashboard_shortcode')) {
    add_shortcode('orabooks_client_dashboard', 'orabooks_client_dashboard_shortcode');
    function orabooks_client_dashboard_shortcode($atts = array(), $content = '') {
        if (!is_user_logged_in()) {
            return do_shortcode('[orabooks_client_home]');
        }
        ...
    }
    }
    */

/**
 * Client Features Shortcode
 * Shows client's purchased features with direct access
 */
function orabooks_client_features_shortcode() {
    if (!is_user_logged_in()) {
        return '<div class="orabooks-message info">
            <p>Please <a href="' . wp_login_url(get_permalink()) . '">login</a> to view your features.</p>
        </div>';
    }
    
    $user_id = get_current_user_id();
    $user_level = get_user_meta($user_id, 'orabooks_level', true);
    
    if (!$user_level) {
        return '<div class="orabooks-message warning">
            <p>You don\'t have an active subscription. <a href="' . network_site_url('/pricing') . '">View Plans</a></p>
        </div>';
    }
    
    // Get assigned features
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    $assignments = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->orabooks_feature_assignments} WHERE level_id = %d",
        $user_level
    ));

    $available_features = orabooks_get_available_features();
    $features = array();

    if ($assignments) {
        foreach ($assignments as $assignment) {
            $key = $assignment->feature_key;
            $feat = new stdClass();
            
            if (isset($available_features[$key])) {
                $def = $available_features[$key];
                $feat->name = $def['name'];
                $feat->description = isset($def['description']) ? $def['description'] : '';
                $feat->icon = isset($def['icon']) ? $def['icon'] : '📦';
                $feat->slug = $key;
                $feat->category = isset($def['category']) ? $def['category'] : 'default';
                $feat->url = isset($def['url']) ? $def['url'] : ''; // Some might have custom URLs
            } else {
                // Fallback from DB assignment
                $feat->name = $assignment->feature_name;
                $feat->description = '';
                $feat->icon = '📦';
                $feat->slug = $key;
                $feat->category = 'default';
                $feat->url = '';
            }
            
            // Special overrides for standard features to point to internal pages
            if ($key === 'accounting') {
                $feat->url = home_url('/accounting');
            }
            
            if ($key === 'inventory') {
                $feat->url = home_url('/inventory');
            }
            
            $features[] = $feat;
        }
    }
    
    ob_start();
    ?>
    <div class="orabooks-client-features">
        <div class="features-header">
            <h2>Your Features</h2>
            <p>Click any feature to access it directly</p>
        </div>
        
        <div class="features-grid">
            <?php if (!empty($features)): ?>
                <?php foreach ($features as $feature): ?>
                    <?php
                    // Feature URL - direct access
                    $feature_url = home_url('/' . $feature->slug);
                    if (!empty($feature->url)) {
                        $feature_url = $feature->url;
                    }
                    
                    // Icon
                    $icon = !empty($feature->icon) ? $feature->icon : '📦';
                    
                    // Category color
                    $category_colors = array(
                        'business' => '#43a62d',
                        'productivity' => '#2d7a1d',
                        'analytics' => '#ff9800',
                        'communication' => '#9c27b0',
                        'default' => '#607d8b'
                    );
                    $color = isset($category_colors[$feature->category]) ? $category_colors[$feature->category] : $category_colors['default'];
                    ?>
                    
                    <a href="<?php echo esc_url($feature_url); ?>" class="feature-card">
                        <div class="feature-icon" style="background: <?php echo esc_attr($color); ?>;">
                            <?php echo $icon; ?>
                        </div>
                        <div class="feature-content">
                            <h3><?php echo esc_html($feature->name); ?></h3>
                            <?php if (!empty($feature->description)): ?>
                                <p><?php echo esc_html($feature->description); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="feature-access">
                            <span class="access-badge">Access Now →</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-features">
                    <p>No features available in your plan.</p>
                    <a href="<?php echo network_site_url('/pricing'); ?>" class="upgrade-btn">Upgrade Plan</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Upgrade Section -->
        <div class="orabooks-features-upgrade">
            <div class="upgrade-header">
                <h2>Upgrade Your Plan</h2>
                <p>Unlock more powerful features for your business</p>
            </div>
            <?php echo do_shortcode('[orabooks_levels]'); ?>
        </div>
    </div>
    
    <style>
    .orabooks-client-features {
        width: 100%;
        max-width: 100%;
        margin: 0;
        padding: 40px 20px;
        box-sizing: border-box;
    }
    
    .features-header, .upgrade-header {
        text-align: center;
        margin-bottom: 40px;
    }

    .orabooks-features-upgrade {
        margin-top: 60px;
        padding-top: 40px;
        border-top: 1px solid #e0e0e0;
    }
    
    .features-header h2, .upgrade-header h2 {
        font-size: 32px;
        margin: 0 0 10px 0;
        color: #333;
    }
    
    .features-header p, .upgrade-header p {
        font-size: 18px;
        color: #666;
        margin: 0;
    }
    
    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 25px;
    }
    
    .feature-card {
        background: white;
        border: none;
        border-radius: 12px;
        padding: 25px;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        text-decoration: none;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .feature-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }
    
    .feature-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        /* border-color removed */
    }
    
    .feature-card:hover::before {
        transform: scaleX(1);
    }
    
    .feature-icon {
        width: 80px;
        height: 80px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 36px;
        margin-bottom: 20px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .feature-content h3 {
        margin: 0 0 10px 0;
        font-size: 20px;
        font-weight: 600;
        color: #333;
    }
    
    .feature-content p {
        margin: 0;
        font-size: 14px;
        color: #666;
        line-height: 1.6;
    }
    
    .feature-access {
        margin-top: 20px;
    }
    
    .access-badge {
        display: inline-block;
        padding: 10px 20px;
        background: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
        color: white;
        border-radius: 6px;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s ease;
    }
    
    .feature-card:hover .access-badge {
        background: <?php echo ORABOOKS_SECONDARY_COLOR; ?>;
        transform: translateX(5px);
    }
    
    .no-features {
        grid-column: 1 / -1;
        text-align: center;
        padding: 60px 20px;
        background: #f8f9fa;
        border-radius: 12px;
    }
    
    .upgrade-btn {
        display: inline-block;
        padding: 12px 30px;
        background: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .upgrade-btn:hover {
        background: <?php echo ORABOOKS_SECONDARY_COLOR; ?>;
        transform: translateY(-2px);
    }
    
    @media (max-width: 768px) {
        .features-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
    <?php
    
    return ob_get_clean();
}
add_shortcode('orabooks_client_features', 'orabooks_client_features_shortcode');

/**
 * Merge default menu with client's custom menus
 */
add_filter('wp_nav_menu_args', 'orabooks_merge_default_and_custom_menus', 10, 1);

function orabooks_merge_default_and_custom_menus($args) {
    // Only on client sites
    if (get_current_blog_id() == 1) {
        return $args;
    }
    
    $default_menu = wp_get_nav_menu_object('Default Menu');
    if ($default_menu) {
        $args['menu'] = $default_menu;
        $args['fallback_cb'] = false;
    }
    return $args;
}

/**
 * Add default menu items to any menu
 */
 // DISABLED: Causes duplicate menu items
 // The Default Menu should be the ONLY menu used on client sites
 // add_filter('wp_nav_menu_objects', 'orabooks_add_default_menu_items_to_all_menus', 20, 2);

// Ensure menu items are deduped and respect logged-in/logged-out classes at render time
add_filter('wp_nav_menu_objects', 'orabooks_render_filter_menu_items', 15, 2);
function orabooks_render_filter_menu_items($items, $args) {
    // Only on client sites
    if (get_current_blog_id() == 1) {
        return $items;
    }

    $is_logged_in = is_user_logged_in();
    $seen = array();
    $filtered = array();

    foreach ($items as $item) {
        $title_lower = strtolower(trim((string) $item->title));

        if (($title_lower === 'login' || $title_lower === 'sign in') && !is_user_logged_in()) {
            $item->url = add_query_arg('redirect_to', home_url('/dashboard/'), network_site_url('/login/'));
        }

        // Determine a stable key to detect duplicates: prefer URL, fallback to title+object_id
        $key = '';
        if (!empty($item->url)) {
            $key = esc_url_raw($item->url);
        } else {
            $key = sanitize_text_field($item->title) . '::' . intval($item->object_id);
        }

        // Skip duplicates
        if (isset($seen[$key])) {
            continue;
        }

        // Normalize classes - sometimes it's an array, sometimes string
        $classes = array();
        if (is_array($item->classes)) {
            $classes = $item->classes;
        } else {
            $classes = preg_split('/\s+/', trim((string) $item->classes));
        }

        // Remove items based on auth classes
        if ($is_logged_in && in_array('logged-out-only', $classes)) {
            // logged-in users should not see logged-out-only items
            continue;
        }
        if (! $is_logged_in && in_array('logged-in-only', $classes)) {
            // logged-out users should not see logged-in-only items
            continue;
        }

        $allowed_titles = array('home','login','register','logout','my account','log in','log out','sign in','sign up','edit landing page','set landing page','landing page','customize site','themes','pages','posts','menus','media','settings','dashboard');
        $url = (string) $item->url;
        $current_base = rtrim(home_url(), '/');
        $network_base = function_exists('network_home_url') ? rtrim(network_home_url(), '/') : $current_base;
        if ($url !== '') {
            $u = rtrim($url, '/');
            $is_network = strpos($u, $network_base) === 0;
            $is_current = strpos($u, $current_base) === 0 || (strlen($u) > 0 && $u[0] === '/');
            if ($is_network && !$is_current && !in_array($title_lower, $allowed_titles, true)) {
                continue;
            }
        }
        if (!in_array($title_lower, $allowed_titles, true)) {
            continue;
        }

        // Keep and mark seen
        $seen[$key] = true;
        $filtered[] = $item;
    }

    // Ensure essential auth-related items exist for the current user state.
    $has_home = false;
    $has_logout = false;
    $has_login = false;
    $has_register = false;
    $has_my_account = false;

    foreach ($filtered as $fitem) {
        $title_lower = strtolower(trim((string) $fitem->title));
        if ($title_lower === 'home' || $title_lower === 'dashboard') $has_home = true;
        if ($title_lower === 'logout') $has_logout = true;
        if ($title_lower === 'sign out' || $title_lower === 'sign out' ) $has_logout = true;
        if ($title_lower === 'login' || $title_lower === 'sign in') $has_login = true;
        if ($title_lower === 'register' || $title_lower === 'sign up') $has_register = true;
        if ($title_lower === 'my account') $has_my_account = true;
    }

    if (! $has_home) {
        $home = new stdClass();
        $home->ID = 0;
        $home->db_id = 0;
        $home->title = 'Home';
        $home->url = home_url('/');
        $home->classes = array('menu-item');
        $home->menu_order = 0;
        $home->menu_item_parent = 0;
        $home->object_id = 0;
        $home->object = 'custom';
        $home->type = 'custom';
        $home->type_label = 'Custom Link';
        $home->target = '';
        $home->attr_title = '';
        $home->description = '';
        $home->xfn = '';
        $home->current = false;
        $home->current_item_ancestor = false;
        $home->current_item_parent = false;
        array_unshift($filtered, $home);
    }

    // If user is logged in but there's no Logout item, append one.
    if ($is_logged_in && ! $has_logout) {
        $logout = new stdClass();
        $logout->ID = 0;
        $logout->db_id = 0;
        $logout->title = 'Logout';
        $logout->url = wp_logout_url( home_url() );
        $logout->classes = array('menu-item', 'logged-in-only');
        $logout->menu_order = count($filtered) ? max(array_map(function($i){ return isset($i->menu_order) ? (int)$i->menu_order : 0; }, $filtered)) + 1 : 1000;
        $logout->menu_item_parent = 0;
        $logout->object_id = 0;
        $logout->object = 'custom';
        $logout->type = 'custom';
        $logout->type_label = 'Custom Link';
        $logout->target = '';
        $logout->attr_title = '';
        $logout->description = '';
        $logout->xfn = '';
        $logout->current = false;
        $logout->current_item_ancestor = false;
        $logout->current_item_parent = false;
        $filtered[] = $logout;
    }

    if ($is_logged_in && ! $has_my_account) {
        $myacc_page = get_page_by_path('my-account');
        $myacc = new stdClass();
        $myacc->ID = 0;
        $myacc->db_id = 0;
        $myacc->title = 'My Account';
        $myacc->url = $myacc_page ? get_permalink($myacc_page->ID) : home_url('/my-account');
        $myacc->classes = array('menu-item', 'logged-in-only');
        $myacc->menu_order = 1;
        $myacc->menu_item_parent = 0;
        $myacc->object_id = 0;
        $myacc->object = 'custom';
        $myacc->type = 'custom';
        $myacc->type_label = 'Custom Link';
        $myacc->target = '';
        $myacc->attr_title = '';
        $myacc->description = '';
        $myacc->xfn = '';
        $myacc->current = false;
        $myacc->current_item_ancestor = false;
        $myacc->current_item_parent = false;
        $insert_at = 0;
        foreach ($filtered as $idx => $it) {
            if (strtolower(trim((string) $it->title)) === 'home') {
                $insert_at = $idx + 1;
                break;
            }
        }
        array_splice($filtered, $insert_at, 0, array($myacc));
    }

    // If user is logged out, ensure Login exists
    if (! $is_logged_in) {
        if (! $has_login) {
            $login = new stdClass();
            $login->ID = 0;
            $login->db_id = 0;
            $login->title = 'Login';
            $login->url = add_query_arg('redirect_to', home_url('/dashboard/'), network_site_url('/login/'));
            $login->classes = array('menu-item', 'logged-out-only');
            $login->menu_order = count($filtered) ? max(array_map(function($i){ return isset($i->menu_order) ? (int)$i->menu_order : 0; }, $filtered)) + 1 : 1000;
            $login->menu_item_parent = 0;
            $login->object_id = 0;
            $login->object = 'custom';
            $login->type = 'custom';
            $login->type_label = 'Custom Link';
            $login->target = '';
            $login->attr_title = '';
            $login->description = '';
            $login->xfn = '';
            $login->current = false;
            $login->current_item_ancestor = false;
            $login->current_item_parent = false;
            $filtered[] = $login;
        }
        
        // Register button removed - registration only from main site
    }


    return $filtered;
}

function orabooks_add_default_menu_items_to_all_menus($items, $args) {
    // Only on client sites
    if (get_current_blog_id() == 1) {
        return $items;
    }
    
    // Get default menu
    $default_menu = wp_get_nav_menu_object('Default Menu');
    
    if ($default_menu) {
        // Get default menu items
        $default_items = wp_get_nav_menu_items($default_menu->term_id);
        
        if ($default_items) {
            // Add default items to current menu
            foreach ($default_items as $item) {
                // Check if item already exists (by title)
                $exists = false;
                foreach ($items as $existing_item) {
                    if ($existing_item->title === $item->title) {
                        $exists = true;
                        break;
                    }
                }
                
                // Add if doesn't exist
                if (!$exists) {
                    $items[] = $item;
                }
            }
            
            // Re-sort items by menu_order
            usort($items, function($a, $b) {
                return $a->menu_order - $b->menu_order;
            });
        }
    }
    
    return $items;
}

// Auto-fix default menu on existing client sites (Runs once)
add_action('init', 'orabooks_auto_fix_client_menu');
function orabooks_auto_fix_client_menu() {
    // Only on client sites
    if (get_current_blog_id() == 1) {
        return;
    }
    
    // Check if already fixed
    if (get_option('orabooks_menu_fixed_v18')) {
        return;
    }
    
    // 1. Create/Update Landing Page
    $landing_page = get_page_by_path('landing-page');
    if (!$landing_page) {
        $page_id = wp_insert_post(array(
            'post_title' => 'Landing Page',
            'post_content' => '[orabooks_client_home]',
            'post_name' => 'landing-page',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
        $landing_page = get_post($page_id);
    } elseif (trim($landing_page->post_content) === '') {
        wp_update_post(array(
            'ID' => $landing_page->ID,
            'post_content' => '[orabooks_client_home]'
        ));
    }

    // 1.5 Ensure Dashboard Page exists
    $dashboard_page = get_page_by_path('dashboard');
    if (!$dashboard_page) {
        $page_id = wp_insert_post(array(
            'post_title' => 'Dashboard',
            'post_content' => '[wpfd_dashboard]',
            'post_name' => 'dashboard',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
        $dashboard_page = get_post($page_id);
    }
    
    // 2. Set landing page as front page for existing sites
    if ($landing_page) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $landing_page->ID);
        flush_rewrite_rules(); // Ensure permalinks work
    }
    
    // 3. Update Upgrade Plan page content
    $upgrade_page = get_page_by_path('upgrade-plan');
    if ($upgrade_page) {
        wp_update_post(array(
            'ID' => $upgrade_page->ID,
            'post_content' => '[orabooks_levels]'
        ));
    } else {
        // Create if missing
        wp_insert_post(array(
            'post_title' => 'Upgrade Plan',
            'post_content' => '[orabooks_levels]',
            'post_name' => 'upgrade-plan',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
    }
    
    // 3.5 Create Accounting Page
    $accounting_page = get_page_by_path('accounting');
    if (!$accounting_page) {
        wp_insert_post(array(
            'post_title' => 'Accounting',
            'post_content' => '[orabooks_accounting]',
            'post_name' => 'accounting',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
    }
    
    // 3.5.1 Create Inventory Page
    $inventory_page = get_page_by_path('inventory');
    if (!$inventory_page) {
        wp_insert_post(array(
            'post_title' => 'Inventory',
            'post_content' => '[orabooks_inventory]',
            'post_name' => 'inventory',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
    }
    
    // 3.6 Remove invalid blank template assignments to avoid empty page rendering
    $features_slugs = array('features');
    foreach ($features_slugs as $f_slug) {
        $f_page = get_page_by_path($f_slug);
        if ($f_page) {
            $page_template = get_page_template_slug($f_page->ID);
            if ($page_template === 'template-orabooks-blank.php') {
                delete_post_meta($f_page->ID, '_wp_page_template');
            }
        }
    }
    
    $landing_page_obj = get_page_by_path('landing-page');
    if ($landing_page_obj) {
        $landing_template = get_page_template_slug($landing_page_obj->ID);
        if ($landing_template === 'template-orabooks-blank.php') {
            delete_post_meta($landing_page_obj->ID, '_wp_page_template');
        }
        delete_post_meta($landing_page_obj->ID, '_orabooks_default_page');
    }

    // Mark remaining default client pages as hidden from the client page editor list.
    $default_slugs = array('ora-dashboard','login','forgot-password','my-account','features','payment-success','payment-failed','register','upgrade-plan','accounting','inventory');
    foreach ($default_slugs as $slug) {
        $page = get_page_by_path($slug);
        if ($page) {
            update_post_meta($page->ID, '_orabooks_default_page', '1');
        }
    }

    // 4. Delete existing Default Menu to force recreation
    $menu_name = 'Default Menu';
    $menu_exists = wp_get_nav_menu_object($menu_name);
    if ($menu_exists) {
        wp_delete_nav_menu($menu_exists->term_id);
    }
    
    // 5. Run the creation function
    orabooks_create_client_default_menu(get_current_blog_id(), get_current_user_id());
    
    // Mark as fixed
    update_option('orabooks_menu_fixed_v18', true);
}

/**
 * One-time dedupe of Default Menu items to remove leftover duplicates
 */
add_action('init', 'orabooks_dedupe_default_menu_once');
function orabooks_dedupe_default_menu_once() {
    if (get_current_blog_id() == 1) {
        return;
    }

    // Run only once per site
    $flag = get_option('orabooks_default_menu_deduped');
    if ($flag) {
        return;
    }

    $default_menu = wp_get_nav_menu_object('Default Menu');
    if (!$default_menu) {
        update_option('orabooks_default_menu_deduped', 1);
        return;
    }

    $items = wp_get_nav_menu_items($default_menu->term_id);
    if (!$items || count($items) <= 1) {
        update_option('orabooks_default_menu_deduped', 1);
        return;
    }

    $seen = array();
    foreach ($items as $item) {
        // Use URL if available, otherwise title+object_id
        $key = '';
        if (!empty($item->url)) {
            $key = esc_url_raw($item->url);
        } else {
            $key = sanitize_text_field($item->title) . '::' . intval($item->object_id);
        }

        if (isset($seen[$key])) {
            // Duplicate found, delete this menu item post
            wp_delete_post($item->ID, true);
        } else {
            $seen[$key] = true;
        }
    }

    // Mark done
    update_option('orabooks_default_menu_deduped', 1);
}

/**
 * Fix front page redirect issue for existing client sites
 */
add_action('init', 'orabooks_fix_front_page_redirect');
function orabooks_fix_front_page_redirect() {
    // Only on client sites
    if (get_current_blog_id() == 1) {
        return;
    }
    
    // Check if already fixed
    if (get_option('orabooks_front_page_fixed_v2')) {
        return;
    }
    
    // Set to show latest posts instead of static page to avoid redirect
    update_option('show_on_front', 'posts');
    // Clear any static front page setting
    delete_option('page_on_front');
    delete_option('page_for_posts');
    flush_rewrite_rules();
    update_option('orabooks_front_page_fixed_v2', 1);
}


