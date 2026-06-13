<?php
/**
 * Client Dashboard Manager
 * 
 * Handles frontend dashboard with backend menus and restricted admin access.
 */

if (!defined('ABSPATH')) exit;

// Enqueue WordPress dashicons
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('dashicons');
});

class Orabooks_Client_Dashboard_Manager {

    public function __construct() {
        // Redirect wp-admin for clients
        add_action('admin_init', array($this, 'restrict_admin_access'));
        
        // Hide admin UI elements when loaded in iframe
        add_action('admin_head', array($this, 'hide_admin_ui_in_iframe'));
        
        // Handle shortcode - register both early and on init
        $this->register_shortcode();
        add_action('init', array($this, 'register_shortcode'));
        
        // Ensure shortcodes are parsed in widgets if needed
        add_filter('widget_text', 'do_shortcode');

        // Enqueue dashicons for frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_dashicons'));
        
        // Ensure shortcode is registered even if class is instantiated late
        add_action('init', array($this, 'register_shortcode'));
    }

    /**
     * Explicitly register shortcode on init
     */
    public function register_shortcode() {
        add_shortcode('orabooks_client_dashboard', array($this, 'render_dashboard'));
    }

    /**
     * Enqueue Dashicons for frontend
     */
    public function enqueue_dashicons() {
        wp_enqueue_style('dashicons');
    }

    /**
     * Restrict admin access for clients
     */
    public function restrict_admin_access() {
        if (defined('DOING_AJAX') && DOING_AJAX) return;
        
        // Only on client sites (subsites in multisite)
        if (get_current_blog_id() == 1) return;
        
        // Check if user is logged in
        if (!is_user_logged_in()) return;
        
        // Super admins can always access wp-admin
        if (is_super_admin()) return;
        
        // Allow iframe requests
        if (isset($_GET['frontend_dashboard'])) return;
        
        // Redirect to frontend dashboard if trying to access wp-admin directly
        if (is_admin()) {
            $dashboard_url = home_url('/dashboard/');
            wp_redirect($dashboard_url);
            exit;
        }
    }

    /**
     * Hide admin UI elements when loaded in iframe
     */
    public function hide_admin_ui_in_iframe() {
        if (isset($_GET['frontend_dashboard'])) {
            echo '<style>
                /* Hide admin sidebar and top bar */
                #adminmenumain, #wpadminbar, #footer-thankyou, #footer-upgrade, #screen-meta, #screen-meta-links { display: none !important; }
                #wpcontent { margin-left: 0 !important; padding-top: 10px !important; }
                html.wp-toolbar { padding-top: 0 !important; }
                #wpbody-content { padding-bottom: 20px !important; }
                .wrap { margin: 10px 20px 0 2px !important; }
                .wrap > h1:first-child, .wrap > h2:first-child { display: none !important; } /* Hide main admin page titles */
                .notice, .updated, .error { margin: 10px 0 !important; }
                .orabooks-admin-tabs { display: none !important; } /* Hide plugin internal tabs in iframe */
                
                /* Hide Block Editor Sidebar Panels */
                .components-panel__header,
                .interface-complementary-area-header,
                .editor-sidebar__panel-tabs,
                .interface-complementary-area,
                .edit-post-sidebar,
                .edit-site-sidebar,
                .interface-interface-skeleton__sidebar { display: none !important; }
                
                /* Hide Block Editor Top Header */
                .interface-interface-skeleton__header,
                .edit-post-header,
                .edit-site-header,
                .editor-header { display: none !important; }
                
                /* Adjust content area to full width */
                .interface-interface-skeleton__content,
                .edit-post-layout__content,
                .edit-site-layout__content { margin-left: 0 !important; margin-right: 0 !important; }
                
                /* Hide admin notices in block editor */
                .components-notice-list { display: none !important; }
            </style>';
            
            // Script to handle links inside iframe
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    // Make sure all links inside iframe also carry the frontend_dashboard param
                    const links = document.querySelectorAll("a");
                    links.forEach(link => {
                        try {
                            const url = new URL(link.href, window.location.origin);
                            // Keep in iframe
                            if (link.target === "_top" || link.target === "_parent") {
                                link.target = "_self";
                            }
                            
                            if (url.pathname.includes("wp-admin")) {
                                url.searchParams.set("frontend_dashboard", "1");
                                link.href = url.toString();
                            }
                        } catch(e) {}
                    });
                });
            </script>';
        }
    }

    /**
     * Get the WordPress admin menu filtered for current user
     */
    public function get_admin_menu() {
        global $menu, $submenu;
        
        // We need to force load the menu
        if (!is_admin()) {
            if (!function_exists('is_plugin_active_for_network')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            require_once ABSPATH . 'wp-admin/includes/admin.php';
            
            // Mock some globals if needed
            global $_parent_pages, $_registered_pages, $admin_page_hooks, $plugin_page_hooks, $pagenow, $menu, $submenu, $_wp_last_object_menu, $_wp_last_utility_menu, $_wp_submenu_nopriv, $_wp_menu_nopriv;
            
            if (empty($pagenow)) {
                $pagenow = 'index.php';
            }

            // Initialize as arrays if not already set to prevent TypeErrors in WP core
            if (!is_array($menu)) $menu = array();
            if (!is_array($submenu)) $submenu = array();
            if (!is_array($_parent_pages)) $_parent_pages = array();
            if (!is_array($_registered_pages)) $_registered_pages = array();
            
            // This will populate $menu and $submenu
            require_once ABSPATH . 'wp-admin/includes/menu.php';
            
            // Trigger admin_menu action for plugins to add their menus
            do_action('admin_menu');
        }
        
        return array('menu' => $menu, 'submenu' => $submenu);
    }

    /**
     * Render the frontend dashboard
     */
    public function render_dashboard($atts) {
        if (!is_user_logged_in()) {
            return do_shortcode('[orabooks_client_home]');
        }

        // Get current user info
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        $user_site_id = get_user_meta($user_id, 'orabooks_site_id', true);
        $user_level = get_user_meta($user_id, 'orabooks_level', true);

        // Get user subscription info
        global $wpdb;
        orabooks_handle_multisite_tables();
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->orabooks_subscriptions} WHERE user_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1",
            $user_id
        ));

        $level = null;
        if ($user_level) {
            $level = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->orabooks_levels} WHERE id = %d",
                $user_level
            ));
        }

        // Get site statistics
        $site_stats = array();
        if ($user_site_id) {
            switch_to_blog($user_site_id);
            
            $site_stats['posts'] = wp_count_posts('publish');
            $site_stats['pages'] = wp_count_posts('page');
            $site_stats['comments'] = wp_count_comments();
            $site_stats['site_url'] = get_site_url();
            $site_stats['site_title'] = get_bloginfo('name');
            $site_stats['admin_url'] = admin_url();
            
            restore_current_blog();
        }

        ob_start();
        ?>
        <div class="client-dashboard-container">
            <!-- Sidebar -->
            <aside class="dashboard-sidebar">
                <div class="sidebar-header">
                    <div class="user-avatar">
                        <?php echo get_avatar($user_id, 80); ?>
                    </div>
                    <div class="user-info">
                        <h3><?php echo esc_html($current_user->display_name); ?></h3>
                        <p class="user-email"><?php echo esc_html($current_user->user_email); ?></p>
                        <?php if ($level): ?>
                            <span class="user-level"><?php echo esc_html($level->name); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <nav class="sidebar-nav">
                    <ul class="nav-menu">
                        <li class="nav-item active">
                            <a href="#" class="nav-link">
                                <i class="dashicons dashicons-dashboard"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo esc_url(add_query_arg('frontend_dashboard', '1', admin_url('admin.php?page=orabooks-site-customization'))); ?>" class="nav-link">
                                <i class="dashicons dashicons-admin-customizer"></i>
                                <span>Customize Site</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo esc_url(add_query_arg('frontend_dashboard', '1', admin_url('themes.php'))); ?>" class="nav-link">
                                <i class="dashicons dashicons-admin-appearance"></i>
                                <span>Themes</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo esc_url(add_query_arg('frontend_dashboard', '1', admin_url('edit.php'))); ?>" class="nav-link">
                                <i class="dashicons dashicons-admin-post"></i>
                                <span>Posts</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo esc_url(add_query_arg('frontend_dashboard', '1', admin_url('nav-menus.php'))); ?>" class="nav-link">
                                <i class="dashicons dashicons-menu"></i>
                                <span>Menus</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo esc_url(add_query_arg('frontend_dashboard', '1', admin_url('media.php'))); ?>" class="nav-link">
                                <i class="dashicons dashicons-admin-media"></i>
                                <span>Media</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php
                                $landing_page_id = get_option('page_on_front');
                                if (!$landing_page_id) {
                                    $landing_page = get_page_by_path('landing-page');
                                    $landing_page_id = $landing_page ? $landing_page->ID : 0;
                                }
                                if ($landing_page_id) {
                                    echo esc_url(home_url('ora-dashboard/pages/edit/' . $landing_page_id));
                                } else {
                                    echo '#';
                                }
                            ?>" class="nav-link <?php echo !$landing_page_id ? 'disabled' : ''; ?>" <?php echo !$landing_page_id ? 'onclick="alert(\'No landing page found. Please set a landing page first.\'); return false;"' : ''; ?>>
                                <i class="dashicons dashicons-edit-page"></i>
                                <span>Edit Landing Page</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" data-toggle="site-settings-modal">
                                <i class="dashicons dashicons-homepage"></i>
                                <span>Set Landing Page</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo esc_url(add_query_arg('frontend_dashboard', '1', admin_url('options-general.php'))); ?>" class="nav-link">
                                <i class="dashicons dashicons-admin-settings"></i>
                                <span>Settings</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                
                <div class="sidebar-footer">
                    <a href="<?php echo wp_logout_url(home_url('/')); ?>" class="logout-btn">
                        <i class="dashicons dashicons-migrate"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </aside>
            
            <!-- Main Content -->
            <main class="dashboard-main">
                <div class="dashboard-header">
                    <h1>Welcome <?php echo esc_html($current_user->display_name); ?>,</h1>
                    <p>Here is your Ora Dashboard.</p>
                </div>
                
                <!-- Status Cards -->
                <div class="status-cards-grid">
                    <!-- Subscription Status -->
                    <div class="status-card subscription-card">
                        <div class="card-icon">
                            <i class="dashicons dashicons-star-filled"></i>
                        </div>
                        <div class="card-content">
                            <h3>Subscription</h3>
                            <p class="card-value">
                                <?php if ($subscription && $level): ?>
                                    <?php echo esc_html($level->name); ?>
                                <?php else: ?>
                                    No Active Plan
                                <?php endif; ?>
                            </p>
                            <p class="card-status <?php echo $subscription ? 'active' : 'inactive'; ?>">
                                <?php echo $subscription ? 'Active' : 'Inactive'; ?>
                            </p>
                        </div>
                        <div class="card-action">
                            <a href="<?php echo home_url('/upgrade-plan'); ?>" class="btn btn-primary">
                                <?php echo $subscription ? 'Upgrade' : 'Choose Plan'; ?>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Features Access -->
                    <div class="status-card features-card">
                        <div class="card-icon">
                            <i class="dashicons dashicons-admin-plugins"></i>
                        </div>
                        <div class="card-content">
                            <h3>Your Features</h3>
                            <p class="card-value">Access Your Tools</p>
                            <p class="card-description">View and manage your purchased features</p>
                        </div>
                        <div class="card-action">
                            <a href="<?php echo home_url('/features'); ?>" class="btn btn-primary">
                                View My Features
                            </a>
                        </div>
                    </div>
                    
                    <!-- Content Stats -->
                    <div class="status-card content-card">
                        <div class="card-icon">
                            <i class="dashicons dashicons-text-page"></i>
                        </div>
                        <div class="card-content">
                            <h3>Content</h3>
                            <div class="content-stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo isset($site_stats['posts']->publish) ? $site_stats['posts']->publish : 0; ?></span>
                                    <span class="stat-label">Posts</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo isset($site_stats['pages']->publish) ? $site_stats['pages']->publish : 0; ?></span>
                                    <span class="stat-label">Pages</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-action">
                            <a href="<?php echo esc_url(add_query_arg('frontend_dashboard', '1', admin_url('edit.php'))); ?>" class="btn btn-secondary">
                                Manage Content
                            </a>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="status-card actions-card">
                        <div class="card-icon">
                            <i class="dashicons dashicons-lightning"></i>
                        </div>
                        <div class="card-content">
                            <h3>Quick Actions</h3>
                            <div class="quick-actions">
                                <a href="<?php echo esc_url(add_query_arg('frontend_dashboard', '1', admin_url('post-new.php'))); ?>" class="quick-action-btn">
                                    <i class="dashicons dashicons-plus-alt"></i>
                                    New Post
                                </a>
                                <a href="<?php echo esc_url(add_query_arg('frontend_dashboard', '1', admin_url('customize.php'))); ?>" class="quick-action-btn">
                                    <i class="dashicons dashicons-admin-customizer"></i>
                                    Customize
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="dashboard-section">
                    <h2>Recent Activity</h2>
                    <div class="activity-feed">
                        <?php if ($subscription): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="dashicons dashicons-star-filled"></i>
                                </div>
                                <div class="activity-content">
                                    <p><strong>Subscription Active</strong></p>
                                    <p>You are subscribed to <?php echo esc_html($level->name); ?> plan</p>
                                    <span class="activity-date"><?php echo date('F j, Y', strtotime($subscription->created_at)); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($site_stats['site_title'])): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="dashicons dashicons-admin-home"></i>
                                </div>
                                <div class="activity-content">
                                    <p><strong>Site Created</strong></p>
                                    <p>Your site <?php echo esc_html($site_stats['site_title']); ?> is ready</p>
                                    <span class="activity-date">Recently</span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="dashicons dashicons-admin-users"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong>Welcome!</strong></p>
                                <p>Your account has been created successfully</p>
                                <span class="activity-date"><?php echo date('F j, Y', strtotime($current_user->user_registered)); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Help Section -->
                <div class="dashboard-section help-section">
                    <h2>Need Help?</h2>
                    <div class="help-cards">
                        <div class="help-card">
                            <div class="help-icon">
                                <i class="dashicons dashicons-book"></i>
                            </div>
                            <h3>Documentation</h3>
                            <p>Learn how to use all features</p>
                            <a href="<?php echo network_site_url('/documentation'); ?>" class="help-link">View Docs</a>
                        </div>
                        <div class="help-card">
                            <div class="help-icon">
                                <i class="dashicons dashicons-sos"></i>
                            </div>
                            <h3>Support</h3>
                            <p>Get help from our support team</p>
                            <a href="<?php echo network_site_url('/support'); ?>" class="help-link">Contact Support</a>
                        </div>
                        <div class="help-card">
                            <div class="help-icon">
                                <i class="dashicons dashicons-video-alt3"></i>
                            </div>
                            <h3>Tutorials</h3>
                            <p>Watch video tutorials</p>
                            <a href="<?php echo network_site_url('/tutorials'); ?>" class="help-link">Watch Videos</a>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <!-- Landing Page Settings Modal -->
        <div id="site-settings-modal" class="site-settings-modal" style="display: none;">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Set Your Landing Page</h2>
                    <button class="modal-close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="modal-description">Choose which page displays when visitors land on your site's home page.</p>
                    
                    <form id="landing-page-form">
                        <?php 
                        // Get available pages
                        if ($user_site_id) {
                            switch_to_blog($user_site_id);
                            
                            $pages = get_pages(array(
                                'post_status' => 'publish',
                                'numberposts' => -1,
                                'orderby' => 'menu_order',
                                'order' => 'ASC'
                            ));
                            
                            $current_page_on_front = get_option('page_on_front');
                            ?>
                            
                            <fieldset class="page-options">
                                <legend>Select Landing Page:</legend>
                                
                                <label class="page-option">
                                    <input type="radio" name="landing_page" value="0" <?php checked($current_page_on_front, 0); ?>>
                                    <span class="option-label">Default Landing Page</span>
                                    <span class="option-description">Shows the default site landing page</span>
                                </label>
                                
                                <?php if (!empty($pages)): ?>
                                    <?php foreach ($pages as $page): ?>
                                        <label class="page-option">
                                            <input type="radio" name="landing_page" value="<?php echo intval($page->ID); ?>" <?php checked($current_page_on_front, $page->ID); ?>>
                                            <span class="option-label"><?php echo esc_html($page->post_title); ?></span>
                                            <span class="option-description"><?php echo esc_html(wp_trim_words($page->post_content, 12)); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="no-pages">No pages available. <a href="<?php echo esc_url(add_query_arg('frontend_dashboard', '1', admin_url('post-new.php?post_type=page'))); ?>" target="_blank">Create a page first</a></p>
                                <?php endif; ?>
                            </fieldset>
                            
                            <div class="preview-section">
                                <p class="preview-info">This page will be displayed at: <strong><?php echo esc_url(home_url('/')); ?></strong></p>
                            </div>
                            
                            <div class="modal-actions">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary" id="save-landing-page">Save Changes</button>
                            </div>
                            
                            <?php wp_nonce_field('orabooks_set_landing_page', 'nonce'); ?>
                            
                            <?php
                            restore_current_blog();
                        } else {
                        ?>
                            <div class="alert alert-warning">
                                <p>Your site is not yet set up. Please contact support.</p>
                            </div>
                        <?php
                        }
                        ?>
                    </form>
                </div>
            </div>
        </div>

        <style>
        /* Landing Page Modal Styles */
        .site-settings-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .site-settings-modal.show {
            display: flex;
        }

        .modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            cursor: pointer;
        }

        .modal-content {
            position: relative;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 30px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 24px;
            color: #2c3e50;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #6c757d;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            color: #2c3e50;
            transform: scale(1.1);
        }

        .modal-body {
            padding: 30px;
        }

        .modal-description {
            color: #6c757d;
            margin: 0 0 25px 0;
            font-size: 14px;
        }

        .page-options {
            border: none;
            padding: 0;
            margin: 0 0 25px 0;
        }

        .page-options legend {
            font-size: 14px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
            display: block;
        }

        .page-option {
            display: flex;
            align-items: flex-start;
            padding: 15px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .page-option:hover {
            background: #f0f2f5;
            border-color: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
        }

        .page-option input[type="radio"] {
            margin-top: 3px;
            margin-right: 12px;
            cursor: pointer;
            flex-shrink: 0;
        }

        .page-option input[type="radio"]:checked + .option-label {
            color: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
            font-weight: 600;
        }

        .option-label {
            display: block;
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 3px;
        }

        .option-description {
            display: block;
            font-size: 12px;
            color: #6c757d;
            line-height: 1.4;
        }

        .no-pages {
            padding: 20px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            color: #856404;
            margin: 0;
        }

        .no-pages a {
            color: #856404;
            text-decoration: underline;
        }

        .preview-section {
            padding: 15px;
            background: #e7f3ff;
            border: 1px solid #b3deff;
            border-radius: 6px;
            margin-bottom: 25px;
        }

        .preview-info {
            margin: 0;
            color: #004085;
            font-size: 14px;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin: 0;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }
        
        /* Client Dashboard Styles */
        .client-dashboard-container {
            display: flex;
            min-height: calc(100vh - 200px);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            padding: 20px;
            border-radius: 24px;
        }
        
        /* Sidebar Styles */
        .dashboard-sidebar {
            width: 280px;
            background: #0f172a;
            color: white;
            position: sticky;
            top: 20px;
            height: calc(100vh - 40px);
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            border-radius: 20px;
            margin-bottom: 20px;
        }

        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-avatar img {
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 15px;
        }

        .user-info h3 {
            margin: 0 0 5px 0;
            font-size: 18px;
            font-weight: 600;
            color: white;
        }

        .user-info p {
            margin: 0 0 10px 0;
            font-size: 14px;
            opacity: 0.8;
            color: white;
        }

        .user-level {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-menu {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .nav-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .nav-link.disabled:hover {
            background: none;
            color: rgba(255, 255, 255, 0.5);
        }

        .nav-link i {
            margin-right: 12px;
            font-size: 18px;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: auto;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            background: rgba(231, 76, 60, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(231, 76, 60, 0.3);
            color: white;
        }

        .logout-btn i {
            margin-right: 10px;
        }

        /* Main Content Styles */
        .dashboard-main {
            flex: 1;
            padding: 0 0 0 40px;
            background: transparent;
        }

        .dashboard-header {
            margin-bottom: 40px;
        }

        .dashboard-header h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
            color: #2c3e50;
            font-weight: 700;
        }

        .dashboard-header p {
            margin: 0;
            font-size: 16px;
            color: #7f8c8d;
        }

        /* Status Cards Grid */
        .status-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .status-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .status-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .status-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, <?php echo ORABOOKS_PRIMARY_COLOR; ?>, <?php echo ORABOOKS_SECONDARY_COLOR; ?>);
        }

        .card-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, <?php echo ORABOOKS_PRIMARY_COLOR; ?>, <?php echo ORABOOKS_SECONDARY_COLOR; ?>);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .card-icon i {
            color: white;
            font-size: 24px;
            opacity: 1 !important;
            visibility: visible !important;
            display: block !important;
        }
        
        /* Ensure all dashicons are loaded and visible */
        .dashicons {
            font-family: dashicons !important;
            font-weight: 400;
            font-style: normal;
            font-variant: normal;
            text-transform: none;
            line-height: 1;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            speak: none;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        .dashicons-lightning:before {
            content: "\f139" !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        .card-content {
            flex: 1;
        }

        .card-content h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
            color: #2c3e50;
            font-weight: 600;
        }

        .card-value {
            margin: 0 0 8px 0;
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
        }

        .card-url {
            margin: 0 0 8px 0;
            font-size: 14px;
            color: #7f8c8d;
            word-break: break-all;
        }

        .card-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .card-status.active {
            background: #d4edda;
            color: #155724;
        }

        .card-status.inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .content-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            display: block;
            font-size: 24px;
            font-weight: 700;
            color: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
        }

        .stat-label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
        }

        .quick-action-btn {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            text-decoration: none;
            color: #495057;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .quick-action-btn:hover {
            background: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
            color: white;
            border-color: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
        }

        .quick-action-btn i {
            margin-right: 8px;
            font-size: 16px;
        }

        .card-action {
            margin-top: 20px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
            color: white;
        }

        .btn-primary:hover {
            background: <?php echo ORABOOKS_SECONDARY_COLOR; ?>;
            transform: translateY(-2px);
            color: white;
        }

        .btn-secondary {
            background: white;
            color: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
            border: 2px solid <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
        }

        .btn-secondary:hover {
            background: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
            color: white;
        }

        /* Dashboard Sections */
        .dashboard-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
        }

        .dashboard-section h2 {
            margin: 0 0 25px 0;
            font-size: 24px;
            color: #2c3e50;
            font-weight: 600;
        }

        /* Activity Feed */
        .activity-feed {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .activity-icon i {
            color: white;
            font-size: 18px;
        }

        .activity-content p {
            margin: 0 0 5px 0;
            color: #495057;
        }

        .activity-content strong {
            color: #2c3e50;
        }

        .activity-date {
            font-size: 12px;
            color: #6c757d;
        }

        /* Help Section */
        .help-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .help-card {
            text-align: center;
            padding: 30px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .help-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .help-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, <?php echo ORABOOKS_PRIMARY_COLOR; ?>, <?php echo ORABOOKS_SECONDARY_COLOR; ?>);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
        }

        .help-icon i {
            color: white;
            font-size: 24px;
        }

        .help-card h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
            color: #2c3e50;
            font-weight: 600;
        }

        .help-card p {
            margin: 0 0 20px 0;
            color: #6c757d;
            font-size: 14px;
        }

        .help-link {
            display: inline-block;
            padding: 8px 16px;
            background: <?php echo ORABOOKS_PRIMARY_COLOR; ?>;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .help-link:hover {
            background: <?php echo ORABOOKS_SECONDARY_COLOR; ?>;
            transform: translateY(-2px);
            color: white;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .client-dashboard-container {
                flex-direction: column;
            }
            .dashboard-sidebar {
                width: 100%;
                height: auto;
                position: relative;
                top: 0;
            }
            .dashboard-main {
                padding: 20px 0;
            }
        }

        @media (max-width: 768px) {
            .status-cards-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('site-settings-modal');
            const form = document.getElementById('landing-page-form');
            const toggleBtn = document.querySelector('[data-toggle="site-settings-modal"]');
            const closeBtn = document.querySelector('[data-dismiss="modal"]');
            const submitBtn = document.getElementById('save-landing-page');
            const overlay = document.querySelector('.modal-overlay');

            // Open modal
            function openModal() {
                modal.classList.add('show');
                modal.style.display = 'flex';
            }

            // Close modal
            function closeModal() {
                modal.classList.remove('show');
                modal.style.display = 'none';
            }

            // Toggle modal
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    openModal();
                });
            }

            // Close on button click
            if (closeBtn) {
                closeBtn.addEventListener('click', closeModal);
            }

            // Close on overlay click
            if (overlay) {
                overlay.addEventListener('click', closeModal);
            }

            // Handle form submission
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const selectedPageId = document.querySelector('input[name="landing_page"]:checked').value;
                    const nonce = document.querySelector('input[name="nonce"]').value;

                    // Disable submit button
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Saving...';
                    }

                    // Send AJAX request
                    fetch(<?php echo json_encode(admin_url('admin-ajax.php')); ?>, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'orabooks_set_landing_page',
                            page_id: selectedPageId,
                            nonce: nonce
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            alert('Landing page updated successfully!');
                            closeModal();
                            // Optional: reload page to see changes
                            // location.reload();
                        } else {
                            alert('Error: ' + (data.data || 'Failed to update landing page'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                    })
                    .finally(() => {
                        // Re-enable submit button
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Save Changes';
                        }
                    });
                });
            }

            // Close modal on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal();
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
