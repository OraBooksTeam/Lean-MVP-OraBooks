<?php
/**
 * Tailwind CSS Integration for Main Site and Client Sites
 * Ensures proper loading of Tailwind CSS across all sites
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue Tailwind CSS on all sites (main and client)
 */
function orabooks_enqueue_tailwind_css() {
    // Only enqueue on frontend pages
    if (is_admin()) {
        return;
    }
    
    // Check if we're on a page with our shortcodes
    global $post;
    $has_shortcode = false;
    
    if ($post && isset($post->post_content)) {
        $shortcodes_to_check = [
            'orabooks_levels',
            'orabooks_checkout', 
            'orabooks_my_account',
            'orabooks_confirmation',
            'orabooks_client_home',
            'forgot_password',
            'wpfd_dashboard',
            'orabooks_client_features',
            'orabooks_payment_success',
            'orabooks_payment_failed',
            'orabooks_upgrade_plan'
        ];
        
        foreach ($shortcodes_to_check as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                $has_shortcode = true;
                break;
            }
        }
    }
    
    // Also check if we're on specific URL paths
    $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $tailwind_paths = ['/levels', '/checkout', '/my-account', '/features', '/dashboard', '/upgrade-plan'];
    
    if ($has_shortcode || in_array($current_path, $tailwind_paths)) {
        // Enqueue Tailwind CSS via CDN
        wp_enqueue_style('tailwind-css', 'https://cdn.tailwindcss.com', [], '3.4.0');
        
        // Enqueue DaisyUI for enhanced components
        wp_enqueue_style('daisyui-css', 'https://cdn.jsdelivr.net/npm/daisyui@4.4.0/dist/full.min.css', [], '4.4.0');
        
        // Custom Tailwind config
        wp_add_inline_script('tailwind-css', '
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            orabooks: {
                                primary: "#43a62d",
                                secondary: "#2d7a1d"
                            }
                        }
                    }
                }
            }
        ');
        
        // Enqueue custom CSS for Tailwind overrides
        wp_enqueue_style('orabooks-tailwind-custom', plugins_url('assets/css/tailwind-custom.css', dirname(dirname(__FILE__))), [], '1.0.0');
    }
}
add_action('wp_enqueue_scripts', 'orabooks_enqueue_tailwind_css');

/**
 * Ensure Tailwind is loaded on client dashboard
 */
function orabooks_client_dashboard_tailwind() {
    // Check if we're on a client site (not main site)
    if (is_multisite() && get_current_blog_id() != 1) {
        // Enqueue Tailwind on client dashboard
        if (is_page('dashboard') || (isset($_GET['page']) && $_GET['page'] === 'dashboard')) {
            wp_enqueue_style('tailwind-css-client', 'https://cdn.tailwindcss.com', [], '3.4.0');
            wp_enqueue_style('daisyui-css-client', 'https://cdn.jsdelivr.net/npm/daisyui@4.4.0/dist/full.min.css', [], '4.4.0');
        }
    }
}
add_action('wp_enqueue_scripts', 'orabooks_client_dashboard_tailwind');

/**
 * Update wpfd_dashboard shortcode to use Tailwind
 */
function orabooks_update_dashboard_shortcode_integration() {
    // Remove existing dashboard shortcode if it exists
    global $shortcode_tags;
    if (isset($shortcode_tags['wpfd_dashboard'])) {
        remove_shortcode('wpfd_dashboard');
    }
    
    // Re-register with our Tailwind version
    add_shortcode('wpfd_dashboard', 'orabooks_tailwind_dashboard_shortcode');
}
add_action('init', 'orabooks_update_dashboard_shortcode_integration');

/**
 * Enhanced dashboard shortcode with better integration
 */
function orabooks_tailwind_dashboard_shortcode($atts) {
    // Check if we're on main site or client site
    $is_main_site = !is_multisite() || get_current_blog_id() == 1;
    $is_client_site = is_multisite() && get_current_blog_id() != 1;
    
    if (!is_user_logged_in()) {
        // Default to frontend login route on current site
        $login_url = home_url('/login/?redirect_to=' . urlencode(home_url('/dashboard/')));
        if ($is_client_site) {
            // On client site, redirect to main site frontend login
            $main_domain = defined('DOMAIN_CURRENT_SITE') ? DOMAIN_CURRENT_SITE : parse_url(network_home_url(), PHP_URL_HOST);
            $protocol = is_ssl() ? 'https' : 'http';
            $login_url = $protocol . '://' . $main_domain . '/login/?redirect_to=' . urlencode(home_url('/dashboard/'));
        }
        
        return '<div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center px-4">
                <div class="bg-white rounded-xl shadow-lg p-8 max-w-md w-full text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Login Required</h2>
                    <p class="text-gray-600 mb-6">Please login to access your dashboard.</p>
                    <a href="' . esc_url($login_url) . '" class="bg-green-600 hover:bg-green-700 text-white py-3 px-6 rounded-lg font-semibold transition-colors duration-200 inline-block">
                        Login to Dashboard
                    </a>
                </div>
              </div>';
    }
    
    $current_user = wp_get_current_user();
    $user_level = get_user_meta($current_user->ID, 'orabooks_level', true);
    
    // Get user's site URL if they have one
    $user_site_url = function_exists('orabooks_get_user_site_url') ? orabooks_get_user_site_url($current_user->ID) : false;
    
    // If on main site and user has a client site, show redirect option
    if ($is_main_site && $user_site_url) {
        return '<div class="min-h-screen bg-gradient-to-br from-blue-50 to-green-50 py-12 px-4">
                <div class="max-w-4xl mx-auto">
                    <div class="bg-white rounded-xl shadow-lg p-8 text-center">
                        <div class="w-20 h-20 bg-green-600 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                            </svg>
                        </div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-4">Your Workspace is Ready</h1>
                        <p class="text-xl text-gray-600 mb-8">
                            You have a dedicated workspace. Click below to access your personalized dashboard.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <a href="' . esc_url($user_site_url) . '/dashboard/" class="bg-green-600 hover:bg-green-700 text-white py-3 px-8 rounded-lg font-semibold transition-colors duration-200">
                                Go to My Workspace
                            </a>
                            <a href="' . esc_url(home_url('/my-account/')) . '" class="border border-gray-300 hover:border-gray-400 text-gray-700 py-3 px-8 rounded-lg font-semibold transition-colors duration-200">
                                Stay on Main Site
                            </a>
                        </div>
                    </div>
                </div>
            </div>';
    }
    
    // Load the regular dashboard content
    return orabooks_wpfd_dashboard_content($atts, $is_main_site, $is_client_site);
}

/**
 * Main dashboard content function
 */
function orabooks_wpfd_dashboard_content($atts, $is_main_site, $is_client_site) {
    $current_user = wp_get_current_user();
    $user_level = get_user_meta($current_user->ID, 'orabooks_level', true);
    
    global $wpdb;
    if (function_exists('orabooks_handle_multisite_tables')) {
        orabooks_handle_multisite_tables();
    }
    
    $level = null;
    if ($user_level) {
        $level = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->orabooks_levels} WHERE id = %d", $user_level));
    }
    
    // Get dashboard stats
    $stats = array();
    $recent_orders = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->orabooks_orders} WHERE user_id = %d ORDER BY created_at DESC LIMIT 5",
        $current_user->ID
    ));
    $active_subscriptions = $wpdb->get_results($wpdb->prepare(
        "SELECT s.*, l.name as level_name FROM {$wpdb->orabooks_subscriptions} s 
         LEFT JOIN {$wpdb->orabooks_levels} l ON s.level_id = l.id 
         WHERE s.user_id = %d AND s.status = 'active'",
        $current_user->ID
    ));
    
    ob_start();
    ?>
    <div class="min-h-screen bg-gray-50">
        <!-- Header with site context -->
        <header class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-900">Dashboard</h1>
                            <?php if ($is_client_site): ?>
                                <p class="text-sm text-gray-600">Client Workspace</p>
                            <?php else: ?>
                                <p class="text-sm text-gray-600">Main Site</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <div class="text-right">
                            <p class="text-sm text-gray-600">Welcome back,</p>
                            <p class="font-semibold text-gray-900"><?php echo esc_html($current_user->display_name); ?></p>
                        </div>
                        <div class="relative">
                            <button class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Site Context Alert -->
            <?php if ($is_client_site): ?>
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-8">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-blue-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <h3 class="font-semibold text-blue-900">You are in your Client Workspace</h3>
                            <p class="text-blue-800 text-sm">This is your dedicated workspace. Access main site features from the navigation.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Welcome Section -->
            <div class="bg-gradient-to-r from-blue-600 to-green-600 rounded-xl p-8 mb-8 text-white">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <div>
                        <h2 class="text-3xl font-bold mb-2">Welcome to Your Dashboard</h2>
                        <p class="text-blue-100">
                            <?php if ($level): ?>
                                You're on <strong><?php echo esc_html($level->name); ?></strong> plan
                            <?php else: ?>
                                Get started by choosing a plan
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <?php if (!$level): ?>
                            <a href="<?php echo home_url('/levels/'); ?>" class="bg-white text-blue-600 hover:bg-gray-100 py-3 px-6 rounded-lg font-semibold transition-colors duration-200">
                                Choose a Plan
                            </a>
                        <?php else: ?>
                            <a href="<?php echo home_url('/upgrade-plan/'); ?>" class="bg-white text-blue-600 hover:bg-gray-100 py-3 px-6 rounded-lg font-semibold transition-colors duration-200">
                                Upgrade Plan
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                            </svg>
                        </div>
                        <span class="text-sm text-gray-500">Active</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900"><?php echo count($active_subscriptions); ?></h3>
                    <p class="text-gray-600 text-sm">Subscriptions</p>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <span class="text-sm text-gray-500">Total</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900">$<?php echo number_format(array_sum(array_column($recent_orders, 'amount')), 2); ?></h3>
                    <p class="text-gray-600 text-sm">Total Spent</p>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7a1 1 0 00-1 1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <span class="text-sm text-gray-500">This Month</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900"><?php echo count($recent_orders); ?></h3>
                    <p class="text-gray-600 text-sm">Orders</p>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <span class="text-sm text-gray-500">Support</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900">24/7</h3>
                    <p class="text-gray-600 text-sm">Help Available</p>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <a href="<?php echo home_url('/my-account/'); ?>" class="flex items-center p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors duration-200">
                            <svg class="w-5 h-5 text-blue-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                            </svg>
                            <span class="font-medium text-gray-900">My Account</span>
                        </a>
                        
                        <a href="<?php echo home_url('/features/'); ?>" class="flex items-center p-3 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors duration-200">
                            <svg class="w-5 h-5 text-purple-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 00-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                            </svg>
                            <span class="font-medium text-gray-900">Features</span>
                        </a>
                        
                        <?php if ($is_main_site): ?>
                        <a href="#" class="flex items-center p-3 bg-green-50 rounded-lg hover:bg-green-100 transition-colors duration-200">
                            <svg class="w-5 h-5 text-green-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                            </svg>
                            <span class="font-medium text-gray-900">Get Help</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="lg:col-span-2 bg-white rounded-xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-bold text-gray-900">Recent Activity</h3>
                        <a href="<?php echo home_url('/my-account/'); ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            View All
                        </a>
                    </div>
                    
                    <div class="space-y-4">
                        <?php if ($recent_orders): ?>
                            <?php foreach ($recent_orders as $order): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-4">
                                            <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900">Order #<?php echo esc_html($order->id); ?></p>
                                            <p class="text-sm text-gray-600"><?php echo date('M j, Y', strtotime($order->created_at)); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-gray-900">$<?php echo number_format($order->amount, 2); ?></p>
                                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-medium">
                                            <?php echo esc_html(ucfirst($order->status)); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5 5a3 3 0 015-2.236A3 3 0 0114.83 6H16a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2h1.17C5.06 5.687 5 5.35 5 5zm4 1V5a1 1 0 10-1 1v1h1zm3 0a1 1 0 10-1 1v1h1z" clip-rule="evenodd"/>
                                </svg>
                                <p>No recent activity</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <?php
    
    return ob_get_clean();
}

/**
 * Create custom CSS file for Tailwind overrides
 */
function orabooks_create_tailwind_custom_css() {
    $css_dir = plugin_dir_path(__FILE__) . '../../assets/css/';
    $css_file = $css_dir . 'tailwind-custom.css';
    
    if (!file_exists($css_file)) {
        $custom_css = "
/* OraBooks Tailwind Custom Styles */
.orabooks-tailwind-container {
    @apply min-h-screen bg-gray-50;
}

/* Custom animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.fade-in {
    animation: fadeIn 0.5s ease-out;
}

/* Custom card hover effects */
.orabooks-card {
    @apply bg-white rounded-xl shadow-lg transition-all duration-300;
}

.orabooks-card:hover {
    @apply shadow-xl transform scale-105;
}

/* Custom button styles */
.orabooks-btn-primary {
    @apply bg-green-600 hover:bg-green-700 text-white py-3 px-6 rounded-lg font-semibold transition-colors duration-200;
}

.orabooks-btn-secondary {
    @apply border border-gray-300 hover:border-gray-400 text-gray-700 py-3 px-6 rounded-lg font-semibold transition-colors duration-200;
}

/* Responsive fixes */
@media (max-width: 768px) {
    .orabooks-mobile-full {
        @apply w-full;
    }
    
    .orabooks-mobile-center {
        @apply text-center;
    }
}
";
        
        // Create directory if it doesn't exist
        if (!file_exists($css_dir)) {
            wp_mkdir_p($css_dir);
        }
        
        // Write CSS file
        file_put_contents($css_file, $custom_css);
    }
}
add_action('init', 'orabooks_create_tailwind_custom_css');
