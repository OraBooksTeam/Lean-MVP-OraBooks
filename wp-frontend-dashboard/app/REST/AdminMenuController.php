<?php
// Cache-busting: Modified 2026-02-09 11:02:30 - Disabled get_core_updates() temporarily
namespace WPFD\REST;

class AdminMenuController {
    public function register() {
        $namespace = 'wpfd/v1';
        
        \register_rest_route($namespace, '/admin-menu', [
            'methods' => 'GET',
            'callback' => [$this, 'get_admin_menu'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        \register_rest_route($namespace, '/admin-menu/counts', [
            'methods' => 'GET',
            'callback' => [$this, 'get_menu_counts'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        \register_rest_route($namespace, '/admin-menu/post-types', [
            'methods' => 'GET',
            'callback' => [$this, 'get_post_types'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        \register_rest_route($namespace, '/admin-menu/taxonomies', [
            'methods' => 'GET',
            'callback' => [$this, 'get_taxonomies'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        \register_rest_route($namespace, '/admin-menu/config', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_admin_menu_config'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_admin_menu_config'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
    }

    /**
     * Check if user has permission to access menu
     */
    public function check_permission() {
        return \is_user_logged_in();
    }

    /**
     * Get complete admin menu structure
     */
    public function get_admin_menu($request) {
        try {
            $user = \wp_get_current_user();
            $current_page = $request->get_param('current_page') ?? '';

            // Load WordPress admin menu system
            $this->load_admin_menu();

            // Build menu from WordPress globals
            $menu_items = $this->build_menu_items($user, $current_page);

            // Apply custom overrides
            $menu_items = $this->apply_menu_overrides($menu_items);

            return \rest_ensure_response([
                'menu_items' => $menu_items,
                'current_page' => $current_page,
                'user_id' => $user->ID,
                'user_capabilities' => $user->get_role(),
            ]);
        } catch (\Exception $e) {
            error_log('AdminMenuController Error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            return new \WP_Error(
                'menu_error',
                'Failed to load admin menu: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get management configuration for dashboard menu
     */
    public function get_admin_menu_config($request) {
        try {
            $user = \wp_get_current_user();
            $this->load_admin_menu();

            // Get the "raw" build (all items available to the user)
            $all_items = $this->build_menu_items($user, '');
            
            // Get current settings
            $settings = \get_option('wpfd_dashboard_menu_settings', []);

            return \rest_ensure_response([
                'available_items' => $all_items,
                'settings' => $settings,
            ]);
        } catch (\Exception $e) {
            return new \WP_Error('config_error', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Update dashboard menu configuration
     */
    public function update_admin_menu_config($request) {
        $settings = $request->get_json_params();
        
        if (!is_array($settings)) {
            return new \WP_Error('invalid_data', 'Invalid settings format', ['status' => 400]);
        }

        \update_option('wpfd_dashboard_menu_settings', $settings);

        return \rest_ensure_response(['success' => true, 'message' => 'Dashboard menu configuration saved']);
    }

    /**
     * Apply custom overrides to the built menu items
     */
    private function apply_menu_overrides($menu_items) {
        $settings = \get_option('wpfd_dashboard_menu_settings', []);
        
        if (empty($settings)) {
            return $menu_items;
        }

        $overrides = [];
        foreach ($settings as $setting) {
            if (isset($setting['menu_slug'])) {
                $overrides[$setting['menu_slug']] = $setting;
            }
        }

        $new_menu = [];
        foreach ($menu_items as $item) {
            $slug = $item['menu_slug'];
            
            if (isset($overrides[$slug])) {
                $override = $overrides[$slug];
                
                // Visibility
                if (isset($override['hidden']) && $override['hidden']) {
                    continue;
                }

                // Label
                if (!empty($override['label'])) {
                    $item['label'] = $override['label'];
                }

                // Icon
                if (!empty($override['icon'])) {
                    $item['icon'] = $override['icon'];
                }

                // Temporary storage for sorting
                $item['custom_order'] = isset($override['order']) ? (int) $override['order'] : 999;
            } else {
                $item['custom_order'] = 999;
            }

            $new_menu[] = $item;
        }

        // Sort by custom order
        usort($new_menu, function($a, $b) {
            if ($a['custom_order'] === $b['custom_order']) {
                return $a['position'] - $b['position'];
            }
            return $a['custom_order'] - $b['custom_order'];
        });

        return $new_menu;
    }

    /**
     * Load WordPress admin menu
     */
    private function load_admin_menu() {
        global $menu, $submenu, $_wp_submenu_nopriv, $_wp_menu_nopriv, $admin_page_hooks, $_registered_pages, $_parent_pages;

        // Only load if not already loaded
        if (!empty($menu)) {
            return;
        }

        // Set up admin context
        if (!defined('WP_ADMIN')) {
            define('WP_ADMIN', true);
        }

        // Initialize admin globals
        if (!isset($admin_page_hooks)) {
            $admin_page_hooks = [];
        }
        if (!isset($_registered_pages)) {
            $_registered_pages = [];
        }
        if (!isset($_parent_pages)) {
            $_parent_pages = [];
        }

        // Load admin functions if not already loaded
        if (!function_exists('get_admin_page_title')) {
            require_once ABSPATH . 'wp-admin/includes/admin.php';
        }

        // Include WordPress admin menu
        require_once ABSPATH . 'wp-admin/menu.php';
    }

    /**
     * Build complete menu hierarchy
     */
    private function build_menu_items($user, $current_page) {
        global $menu, $submenu;

        $menu_items = [];
        $icon_map = $this->get_icon_map();

        // If WordPress menu is empty, create basic menu structure
        if (empty($menu) || !is_array($menu)) {
            return $this->get_basic_menu_structure();
        }

        // Include WordPress menus
        foreach ($menu as $position => $menu_item) {
            // Skip separators and empty items
            if (!is_array($menu_item) || $menu_item[2] === '#' || empty($menu_item[0])) {
                continue;
            }

            // Check capability
            $capability = $menu_item[1] ?? 'manage_options';
            if (!\current_user_can($capability)) {
                continue;
            }

            $menu_slug = $menu_item[2];
            $normalized_menu_slug = $this->get_slug_base($menu_slug);

            if ($this->is_projects_menu_slug($menu_slug)) {
                continue;
            }

            // Remove WordPress Tools menu from sidebar.
            if ($normalized_menu_slug === 'tools.php') {
                continue;
            }

            $icon = $menu_item[6] ?? '';

            $item = [
                'id' => \sanitize_title($menu_slug),
                'label' => $this->sanitize_menu_label($menu_item[0]),
                'icon' => $this->map_icon($icon, $icon_map),
                'url' => $this->get_menu_url($menu_slug),
                'position' => $position,
                'capability' => $capability,
                'menu_slug' => $menu_slug,
                'badge_count' => $this->get_menu_badge_count($menu_slug),
                'is_current' => \strpos($current_page, $menu_slug) === 0,
                'submenu' => [],
            ];

            // Add submenus
            if (isset($submenu[$menu_slug]) && is_array($submenu[$menu_slug])) {
                $item['submenu'] = $this->build_submenus($submenu[$menu_slug], $menu_slug, $icon_map);
            }

            $menu_items[] = $item;
        }

        return $menu_items;
    }

    /**
     * Get basic menu structure as fallback
     */
    private function get_basic_menu_structure() {
        $menu_items = [];

        // Dashboard
        if (\current_user_can('read')) {
            $menu_items[] = [
                'id' => 'dashboard',
                'label' => 'Dashboard',
                'icon' => 'LayoutDashboard',
                'url' => '/dashboard',
                'position' => 2,
                'capability' => 'read',
                'menu_slug' => 'index.php',
                'badge_count' => null,
                'is_current' => false,
                'submenu' => [],
            ];
        }

        // Posts
        if (\current_user_can('edit_posts')) {
            $menu_items[] = [
                'id' => 'posts',
                'label' => 'Posts',
                'icon' => 'FileText',
                'url' => '/dashboard/posts',
                'position' => 5,
                'capability' => 'edit_posts',
                'menu_slug' => 'edit.php',
                'badge_count' => null,
                'is_current' => false,
                'submenu' => [],
            ];
        }

        // Media
        if (\current_user_can('upload_files')) {
            $menu_items[] = [
                'id' => 'media',
                'label' => 'Media',
                'icon' => 'Image',
                'url' => '/dashboard/media',
                'position' => 10,
                'capability' => 'upload_files',
                'menu_slug' => 'upload.php',
                'badge_count' => null,
                'is_current' => false,
                'submenu' => [],
            ];
        }

        // Pages
        if (\current_user_can('edit_pages')) {
            $menu_items[] = [
                'id' => 'pages',
                'label' => 'Pages',
                'icon' => 'FileText',
                'url' => '/dashboard/pages',
                'position' => 20,
                'capability' => 'edit_pages',
                'menu_slug' => 'edit.php?post_type=page',
                'badge_count' => null,
                'is_current' => false,
                'submenu' => [],
            ];
        }

        // Comments
        if (\current_user_can('moderate_comments')) {
            $menu_items[] = [
                'id' => 'comments',
                'label' => 'Comments',
                'icon' => 'MessageSquare',
                'url' => '/dashboard/comments',
                'position' => 25,
                'capability' => 'moderate_comments',
                'menu_slug' => 'edit-comments.php',
                'badge_count' => null,
                'is_current' => false,
                'submenu' => [],
            ];
        }

        // Themes
        if (\current_user_can('switch_themes')) {
            $menu_items[] = [
                'id' => 'themes',
                'label' => 'Appearance',
                'icon' => 'Palette',
                'url' => '/dashboard/themes',
                'position' => 60,
                'capability' => 'switch_themes',
                'menu_slug' => 'themes.php',
                'badge_count' => null,
                'is_current' => false,
                'submenu' => [],
            ];
        }

        // Plugins
        if (\current_user_can('activate_plugins')) {
            $menu_items[] = [
                'id' => 'plugins',
                'label' => 'Plugins',
                'icon' => 'Puzzle',
                'url' => '/dashboard/plugins',
                'position' => 50,
                'capability' => 'activate_plugins',
                'menu_slug' => 'plugins.php',
                'badge_count' => null,
                'is_current' => false,
                'submenu' => [],
            ];
        }

        // Users
        if (\current_user_can('list_users')) {
            $menu_items[] = [
                'id' => 'users',
                'label' => 'Users',
                'icon' => 'Users',
                'url' => '/dashboard/users',
                'position' => 70,
                'capability' => 'list_users',
                'menu_slug' => 'users.php',
                'badge_count' => null,
                'is_current' => false,
                'submenu' => [],
            ];
        }

        // Settings
        if (\current_user_can('manage_options')) {
            $menu_items[] = [
                'id' => 'settings',
                'label' => 'Settings',
                'icon' => 'Settings',
                'url' => '/dashboard/settings',
                'position' => 80,
                'capability' => 'manage_options',
                'menu_slug' => 'options-general.php',
                'badge_count' => null,
                'is_current' => false,
                'submenu' => [],
            ];
        }

        return $menu_items;
    }

    /**
     * Build submenu items
     */
    private function build_submenus($submenus, $parent_slug, $icon_map) {
        $submenu_items = [];
        $normalized_parent = $this->get_slug_base($parent_slug);

        if ($this->is_projects_menu_slug($parent_slug)) {
            return $submenu_items;
        }

        // Remove all Tools submenu items from sidebar.
        if ($normalized_parent === 'tools.php') {
            return $submenu_items;
        }

        foreach ($submenus as $submenu) {
            $capability = $submenu[1] ?? 'manage_options';

            if (!\current_user_can($capability)) {
                continue;
            }

            if ($this->is_projects_menu_slug($submenu[2] ?? '')) {
                continue;
            }

            // Remove "My Sites" from Dashboard submenu in multisite.
            if ($normalized_parent === 'index.php' && $this->get_slug_base($submenu[2] ?? '') === 'my-sites.php') {
                continue;
            }

            // Remove "Add Media" submenu under Media.
            if ($normalized_parent === 'upload.php' && $this->get_slug_base($submenu[2] ?? '') === 'media-new.php') {
                continue;
            }

            // Remove "Design" / Site Editor submenu under Appearance.
            if ($normalized_parent === 'themes.php') {
                $submenu_base = $this->get_slug_base($submenu[2] ?? '');
                $submenu_page = $this->get_slug_query_param($submenu[2] ?? '', 'page');

                if (
                    $submenu_base === 'site-editor.php'
                    || ($submenu_base === 'themes.php' && $submenu_page === 'gutenberg-edit-site')
                    || $submenu_base === 'customize.php'
                    || $submenu_base === 'custom-background.php'
                ) {
                    continue;
                }
            }

            $submenu_items[] = [
                'id' => \sanitize_title($this->normalize_wp_admin_slug($submenu[2])),
                'label' => $this->sanitize_menu_label($submenu[0]),
                'url' => $this->get_submenu_url($parent_slug, $submenu[2]),
                'capability' => $capability,
                'menu_slug' => $submenu[2],
                'badge_count' => null, // Submenus don't typically have badges
            ];
        }

        return $submenu_items;
    }

    /**
     * Detect Projects menu slugs and related submenu slugs.
     */
    private function is_projects_menu_slug($slug) {
        if (!is_string($slug) || $slug === '') {
            return false;
        }

        $normalized_slug = strtolower($slug);

        return strpos($normalized_slug, 'post_type=project') !== false
            || strpos($normalized_slug, 'post_type=projects') !== false;
    }

    /**
     * Convert WordPress menu labels with HTML badges into plain text labels.
     */
    private function sanitize_menu_label($label) {
        if (!is_string($label)) {
            return '';
        }

        $decoded = \html_entity_decode($label, ENT_QUOTES, 'UTF-8');
        $base_label = \preg_replace('/<.*$/s', '', $decoded);
        return \sanitize_text_field(\trim($base_label));
    }

    /**
     * Get menu badge count (e.g., pending posts, comments)
     */
    private function get_menu_badge_count($menu_slug) {
        $counts = [];

        // Posts
        if ($menu_slug === 'edit.php') {
            $args = ['post_type' => 'post', 'post_status' => 'draft'];
            $count = \wp_count_posts('post');
            return isset($count->draft) ? (int) $count->draft : 0;
        }

        // Pages
        if ($menu_slug === 'edit.php?post_type=page') {
            $count = \wp_count_posts('page');
            return isset($count->draft) ? (int) $count->draft : 0;
        }

        // Comments
        if ($menu_slug === 'edit-comments.php') {
            $comment_count = \wp_count_comments();
            return isset($comment_count->moderated) ? (int) $comment_count->moderated : 0;
        }

        // Users - pending users
        if ($menu_slug === 'users.php') {
            global $wpdb;
            $pending = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users WHERE user_registered > DATE_SUB(NOW(), INTERVAL 7 DAY)");
            return (int) $pending;
        }

        // Plugins - available updates
        if ($menu_slug === 'plugins.php') {
            $update_plugins = \get_site_transient('update_plugins');
            return (isset($update_plugins->response) && \is_array($update_plugins->response)) ? \count($update_plugins->response) : 0;
        }

        // Updates available - TEMPORARILY DISABLED DUE TO CACHING ISSUE
        if ($menu_slug === 'update-core.php') {
            // $updates = \get_core_updates();
            // return \count($updates);
            return 0;  // Temporarily return 0
        }

        return null;
    }

    /**
     * Get dashboard, profile, or admin menu counts
     */
    public function get_menu_counts($request) {
        $counts = [
            'pending_posts' => 0,
            'pending_pages' => 0,
            'pending_comments' => 0,
            'pending_users' => 0,
            'pending_updates' => 0,
            'pending_plugin_updates' => 0,
        ];

        // Pending posts
        if (\current_user_can('edit_posts')) {
            $posts = \wp_count_posts('post');
            $counts['pending_posts'] = isset($posts->draft) ? (int) $posts->draft : 0;
        }

        // Pending pages
        if (\current_user_can('edit_pages')) {
            $pages = \wp_count_posts('page');
            $counts['pending_pages'] = isset($pages->draft) ? (int) $pages->draft : 0;
        }

        // Pending comments
        if (\current_user_can('moderate_comments')) {
            $comments = \wp_count_comments();
            $counts['pending_comments'] = isset($comments->moderated) ? (int) $comments->moderated : 0;
        }

        // Pending users
        if (\current_user_can('list_users')) {
            global $wpdb;
            $pending = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users WHERE user_registered > DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $counts['pending_users'] = (int) $pending;
        }

        // Available updates - TEMPORARILY DISABLED DUE TO CACHING ISSUE
        // TODO: Re-enable after cache is properly cleared
        $counts['pending_updates'] = 0;

        // Plugin updates
        $update_plugins = \get_site_transient('update_plugins');
        $counts['pending_plugin_updates'] = (isset($update_plugins->response) && \is_array($update_plugins->response)) ? \count($update_plugins->response) : 0;

        return $counts;
    }

    /**
     * Get all registered post types
     */
    public function get_post_types($request) {
        $post_types = \get_post_types(['_builtin' => false], 'objects');
        $result = [];

        foreach ($post_types as $post_type) {
            if (\current_user_can($post_type->cap->edit_posts)) {
                $result[] = [
                    'name' => $post_type->name,
                    'label' => $post_type->label,
                    'singular' => $post_type->labels->singular_name ?? $post_type->label,
                    'public' => $post_type->public,
                    'icon' => isset($post_type->menu_icon) ? $post_type->menu_icon : 'dashicons-admin-post',
                ];
            }
        }

        return $result;
    }

    /**
     * Get all registered taxonomies
     */
    public function get_taxonomies($request) {
        $taxonomies = \get_taxonomies(['_builtin' => false], 'objects');
        $result = [];

        foreach ($taxonomies as $taxonomy) {
            if (\current_user_can($taxonomy->cap->manage_terms)) {
                $result[] = [
                    'name' => $taxonomy->name,
                    'label' => $taxonomy->label,
                    'object_types' => $taxonomy->object_type,
                    'public' => $taxonomy->public,
                ];
            }
        }

        return $result;
    }

    /**
     * Map WordPress Dashicons to React icon names
     */
    private function get_icon_map() {
        return [
            'dashicons-dashboard' => 'LayoutDashboard',
            'dashicons-admin-post' => 'FileText',
            'dashicons-admin-page' => 'FileText',
            'dashicons-media-default' => 'Image',
            'dashicons-admin-comments' => 'MessageSquare',
            'dashicons-admin-appearance' => 'Palette',
            'dashicons-plugins' => 'Puzzle',
            'dashicons-admin-users' => 'Users',
            'dashicons-admin-tools' => 'Wrench',
            'dashicons-admin-settings' => 'Settings',
            'dashicons-admin-multisite' => 'Network',
            'dashicons-admin-home' => 'Home',
            'dashicons-admin-generic' => 'Box',
            'dashicons-calendar' => 'Calendar',
            'dashicons-tag' => 'Tag',
            'dashicons-category' => 'Layers',
            'dashicons-archive' => 'Archive',
            'dashicons-text' => 'FileText',
            'dashicons-portfolio' => 'Briefcase',
            'dashicons-cart' => 'ShoppingCart',
            'dashicons-money' => 'DollarSign',
            'dashicons-smiley' => 'Smile',
            'dashicons-thumbs-up' => 'ThumbsUp',
            'dashicons-star-filled' => 'Star',
            'dashicons-star-empty' => 'Star',
            'dashicons-star-half' => 'StarHalf',
            'dashicons-book' => 'Book',
            'dashicons-lightning' => 'Zap',
            'dashicons-sos' => 'LifeBuoy',
            'dashicons-video-alt3' => 'Video',
            'dashicons-migrate' => 'ArrowRightLeft',
            'dashicons-plus-alt' => 'PlusCircle',
        ];
    }

    /**
     * Map WordPress Dashicon to Lucide React icon
     */
    private function map_icon($dashicon, $icon_map) {
        if (empty($dashicon)) {
            return 'Box';
        }

        // Check if it's a URL (custom icon)
        if (\strpos($dashicon, 'http') === 0 || \strpos($dashicon, 'data:') === 0) {
            return $dashicon;
        }

        // Remove 'dashicons-' prefix if present
        $dashicon_clean = \str_replace('dashicons-', '', $dashicon);

        // Check mapping
        foreach ($icon_map as $dash => $lucide) {
            if (\strpos($dash, $dashicon_clean) !== false || $dash === $dashicon) {
                return $lucide;
            }
        }

        return 'Box'; // Default icon
    }

    /**
     * Get URL for main menu item
     */
    private function get_menu_url($menu_slug) {
        $base_slug = $this->get_slug_base($menu_slug);
        $post_type = $this->get_slug_query_param($menu_slug, 'post_type');

        // Handle special cases
        if ($base_slug === 'index.php') {
            return '/dashboard';
        }

        if ($base_slug === 'edit.php' && $post_type === 'page') {
            return '/dashboard/pages';
        }

        if ($base_slug === 'edit.php') {
            return '/dashboard/posts';
        }

        if ($base_slug === 'upload.php') {
            return '/dashboard/media';
        }

        if ($base_slug === 'edit-comments.php') {
            return '/dashboard/comments';
        }

        if ($base_slug === 'themes.php') {
            return '/dashboard/themes';
        }

        if ($base_slug === 'plugins.php') {
            return '/dashboard/plugins';
        }

        if ($base_slug === 'users.php') {
            return '/dashboard/users';
        }

        if ($base_slug === 'my-sites.php') {
            return '/dashboard';
        }

        if ($base_slug === 'options-general.php') {
            return '/dashboard/settings';
        }

        if ($base_slug === 'nav-menus.php') {
            return '/dashboard/menus';
        }

        if ($base_slug === 'customize.php') {
            return '/dashboard/customize';
        }

        if ($base_slug === 'widgets.php') {
            return '/dashboard/widgets';
        }

        // Default: use slug
        return '/dashboard/' . sanitize_title($base_slug ?: $menu_slug);
    }

    /**
     * Get URL for submenu item
     */
    private function get_submenu_url($parent_slug, $submenu_slug) {
        $parent_base = $this->get_slug_base($parent_slug);
        $parent_post_type = $this->get_slug_query_param($parent_slug, 'post_type');
        $submenu_base = $this->get_slug_base($submenu_slug);
        $submenu_taxonomy = $this->get_slug_query_param($submenu_slug, 'taxonomy');
        $submenu_post_type = $this->get_slug_query_param($submenu_slug, 'post_type');

        // Dashboard submenus
        if ($parent_base === 'index.php') {
            if ($submenu_base === 'index.php') {
                return '/dashboard';
            }
            if ($submenu_base === 'update-core.php') {
                return '/dashboard/update-core-php';
            }
            if ($submenu_base === 'my-sites.php') {
                return '/dashboard';
            }
        }

        // Posts submenus
        if ($parent_base === 'edit.php' && $parent_post_type !== 'page') {
            if ($submenu_base === 'edit.php' && $submenu_post_type !== 'page') {
                return '/dashboard/posts';
            }
            if ($submenu_base === 'post-new.php' && $submenu_post_type !== 'page') {
                return '/dashboard/posts/new';
            }
            if ($submenu_base === 'edit-tags.php' && $submenu_taxonomy === 'category') {
                return '/dashboard/posts/categories';
            }
            if ($submenu_base === 'edit-tags.php' && $submenu_taxonomy === 'post_tag') {
                return '/dashboard/posts/tags';
            }
        }

        // Pages submenus
        if ($parent_base === 'edit.php' && $parent_post_type === 'page') {
            if ($submenu_base === 'edit.php' && $submenu_post_type === 'page') {
                return '/dashboard/pages';
            }
            if ($submenu_base === 'post-new.php' && $submenu_post_type === 'page') {
                return '/dashboard/pages/new';
            }
        }

        // Settings submenus
        if ($parent_base === 'options-general.php') {
            if ($submenu_base === 'options-general.php') {
                return '/dashboard/settings/general';
            }
            if ($submenu_base === 'options-writing.php') {
                return '/dashboard/settings/writing';
            }
            if ($submenu_base === 'options-media.php') {
                return '/dashboard/settings/media';
            }
            if ($submenu_base === 'options-reading.php') {
                return '/dashboard/settings/reading';
            }
            if ($submenu_base === 'options-discussion.php') {
                return '/dashboard/settings/discussion';
            }
            if ($submenu_base === 'options-permalink.php') {
                return '/dashboard/settings/permalinks';
            }
        }

        // Appearance submenus
        if ($parent_base === 'themes.php') {
            if ($submenu_base === 'themes.php') {
                return '/dashboard/themes';
            }
            if ($submenu_base === 'customize.php') {
                return '/dashboard/customize';
            }
            if ($submenu_base === 'widgets.php') {
                return '/dashboard/widgets';
            }
            if ($submenu_base === 'nav-menus.php') {
                return '/dashboard/menus';
            }
        }

        // Users submenus
        if ($parent_base === 'users.php') {
            if ($submenu_base === 'users.php') {
                return '/dashboard/users';
            }
            if ($submenu_base === 'user-new.php') {
                return '/dashboard/users/new';
            }
            if ($submenu_base === 'profile.php') {
                return '/dashboard/profile';
            }
        }

        // Default submenu URL
        return '/dashboard/' . sanitize_title($submenu_base ?: $submenu_slug);
    }

    private function normalize_wp_admin_slug($slug) {
        if (!is_string($slug)) {
            return '';
        }

        $normalized = trim(html_entity_decode($slug, ENT_QUOTES, 'UTF-8'));

        // Decode encoded query separators (e.g. customize.php%3Freturn%3D...)
        if (stripos($normalized, '%3f') !== false || stripos($normalized, '%26') !== false) {
            $normalized = rawurldecode($normalized);
        }

        return $normalized;
    }

    private function get_slug_base($slug) {
        $normalized = $this->normalize_wp_admin_slug($slug);
        if ($normalized === '') {
            return '';
        }

        $parts = explode('?', $normalized, 2);
        return strtolower($parts[0]);
    }

    private function get_slug_query_param($slug, $param_name) {
        $normalized = $this->normalize_wp_admin_slug($slug);
        if ($normalized === '' || strpos($normalized, '?') === false) {
            return '';
        }

        $parts = explode('?', $normalized, 2);
        if (!isset($parts[1])) {
            return '';
        }

        $query_params = [];
        parse_str($parts[1], $query_params);

        if (!isset($query_params[$param_name])) {
            return '';
        }

        return sanitize_key((string) $query_params[$param_name]);
    }
}
