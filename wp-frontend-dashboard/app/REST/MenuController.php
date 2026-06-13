<?php

namespace WPFD\REST;

class MenuController {
    public function register() {
        $namespace = 'wpfd/v1';
        $base = '/menus';

        // Check permission callback for all endpoints
        $permission_callback = [$this, 'check_menu_permission'];

        register_rest_route($namespace, $base, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_items'],
                'permission_callback' => $permission_callback,
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_menu'],
                'permission_callback' => $permission_callback,
            ],
        ]);

        register_rest_route($namespace, $base . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_menu'],
            'permission_callback' => $permission_callback,
        ]);

        register_rest_route($namespace, $base . '/(?P<id>\d+)/items', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_menu_items'],
                'permission_callback' => $permission_callback,
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_menu_items'],
                'permission_callback' => $permission_callback,
            ],
        ]);

        register_rest_route($namespace, $base . '/(?P<id>\d+)/items/(?P<item_id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_menu_item'],
            'permission_callback' => $permission_callback,
        ]);

        register_rest_route($namespace, $base . '/(?P<id>\d+)/items/reorder', [
            'methods' => 'POST',
            'callback' => [$this, 'reorder_menu_items'],
            'permission_callback' => $permission_callback,
        ]);

        register_rest_route($namespace, $base . '/assign', [
            'methods' => 'POST',
            'callback' => [$this, 'assign_menu'],
            'permission_callback' => $permission_callback,
        ]);

        register_rest_route($namespace, $base . '/locations', [
            'methods' => 'GET',
            'callback' => [$this, 'get_locations'],
            'permission_callback' => $permission_callback,
        ]);

        register_rest_route($namespace, $base . '/(?P<id>\d+)/auto-add', [
            'methods' => 'POST',
            'callback' => [$this, 'set_auto_add'],
            'permission_callback' => $permission_callback,
        ]);

        // Endpoints for menu cloning and presets
        register_rest_route($namespace, $base . '/(?P<id>\d+)/clone', [
            'methods' => 'POST',
            'callback' => [$this, 'clone_menu'],
            'permission_callback' => $permission_callback,
        ]);

        register_rest_route($namespace, $base . '/presets', [
            'methods' => 'GET',
            'callback' => [$this, 'get_presets'],
            'permission_callback' => $permission_callback,
        ]);

        register_rest_route($namespace, $base . '/apply-preset', [
            'methods' => 'POST',
            'callback' => [$this, 'apply_preset'],
            'permission_callback' => $permission_callback,
        ]);
    }

    /**
     * Check if user has permission to manage menus
     * For multisite: Must be admin of CURRENT blog or Super Admin
     * For single site: Must have manage_options capability
     */
    public function check_menu_permission() {
        if (!is_user_logged_in()) {
            return new \WP_Error(
                'not_logged_in',
                'You must be logged in to manage menus',
                ['status' => 401]
            );
        }

        if (is_multisite()) {
            $current_user_id = get_current_user_id();
            $current_blog_id = get_current_blog_id();

            // Super Admins can manage any subsite's menus
            if (is_super_admin($current_user_id)) {
                return true;
            }

            // Check if user is member of current blog and has manage_options
            if (!is_user_member_of_blog($current_user_id, $current_blog_id)) {
                return new \WP_Error(
                    'not_blog_member',
                    'You are not a member of this site',
                    ['status' => 403]
                );
            }

            if (!current_user_can('manage_options')) {
                return new \WP_Error(
                    'insufficient_permissions',
                    'You must be an administrator to manage menus',
                    ['status' => 403]
                );
            }

            return true;
        }

        // Single site: just check manage_options
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'insufficient_permissions',
                'You must be an administrator to manage menus',
                ['status' => 403]
            );
        }

        return true;
    }

    public function get_items() {
        return wp_get_nav_menus();
    }

    public function create_menu($request) {
        $name = sanitize_text_field($request->get_param('name'));
        if (empty($name)) {
            return new \WP_Error('empty_name', 'Menu name is required', ['status' => 400]);
        }

        $menu_id = wp_create_nav_menu($name);
        if (is_wp_error($menu_id)) {
            return $menu_id;
        }

        // Store blog ID metadata for multisite tracking
        if (is_multisite()) {
            $blog_id = get_current_blog_id();
            update_term_meta($menu_id, 'wpfd_blog_id', $blog_id);
            // Verify it was saved
            error_log("WPFD: Created menu $menu_id for blog $blog_id. Stored: " . get_term_meta($menu_id, 'wpfd_blog_id', true));
        }

        return ['id' => $menu_id, 'name' => $name, 'message' => 'Menu created successfully'];
    }

    public function delete_menu($request) {
        $id = $request['id'];
        
        // Verify menu belongs to current blog if multisite
        if (is_multisite() && !$this->verify_menu_ownership($id)) {
            return new \WP_Error(
                'menu_not_found',
                'This menu does not belong to your site',
                ['status' => 403]
            );
        }

        $result = wp_delete_nav_menu($id);
        if (!$result) {
            return new \WP_Error('delete_failed', 'Failed to delete menu', ['status' => 500]);
        }
        return ['message' => 'Menu deleted successfully'];
    }

    public function get_menu_items($request) {
        $id = $request['id'];

        // Verify menu belongs to current blog if multisite
        if (is_multisite() && !$this->verify_menu_ownership($id)) {
            error_log("WPFD: Menu $id does not belong to blog " . get_current_blog_id());
            return new \WP_Error(
                'menu_not_found',
                'This menu does not belong to your site',
                ['status' => 403]
            );
        }

        $items = wp_get_nav_menu_items($id);
        
        // Debug logging
        error_log("WPFD: Fetching items for menu $id on blog " . get_current_blog_id());
        error_log("WPFD: wp_get_nav_menu_items returned: " . (is_array($items) ? count($items) . " items" : "null/false"));
        
        if (!$items) {
            error_log("WPFD: No items found for menu $id - returning empty array");
            return [];
        }

        return array_map(function($item) {
            return [
                'id' => $item->ID,
                'title' => $item->title,
                'url' => $item->url,
                'type' => $item->type_label,
                'object' => $item->object,
                'parent' => $item->menu_item_parent,
                'order' => $item->menu_order,
                'classes' => $item->classes ? implode(' ', $item->classes) : '',
                'description' => $item->description ?? '',
                'target' => $item->target ?? '',
                'icon' => get_post_meta($item->ID, '_wpfd_menu_icon', true) ?? '',
                'visibility' => get_post_meta($item->ID, '_wpfd_menu_visibility', true) ?? 'public',
            ];
        }, $items);
    }

    public function delete_menu_item($request) {
        $item_id = $request['item_id'];
        
        // Verify menu item exists and belongs to user's blog
        $item = get_post($item_id);
        if (!$item || $item->post_type !== 'nav_menu_item') {
            return new \WP_Error('not_found', 'Menu item not found', ['status' => 404]);
        }

        $result = wp_delete_post($item_id, true);
        if (!$result) {
            return new \WP_Error('delete_failed', 'Failed to delete menu item', ['status' => 500]);
        }
        return ['message' => 'Menu item deleted successfully'];
    }

    public function reorder_menu_items($request) {
        $menu_id = intval($request['id']);
        $orders = $request['orders']; // Array of {id, order}

        // Validate orders is array
        if (!is_array($orders) || empty($orders)) {
            return new \WP_Error('invalid_orders', 'Orders must be a non-empty array', ['status' => 400]);
        }

        // Verify menu belongs to current blog
        if (is_multisite() && !$this->verify_menu_ownership($menu_id)) {
            return new \WP_Error(
                'menu_not_found',
                'This menu does not belong to your site',
                ['status' => 403]
            );
        }

        $updated_count = 0;
        foreach ($orders as $order) {
            $item_id = isset($order['id']) ? absint($order['id']) : 0;
            $menu_order = isset($order['order']) ? absint($order['order']) : 0;
            
            if (!$item_id || !$menu_order) {
                continue; // Skip invalid entries
            }
            
            // Verify this item is a menu item (security: ensure we're only updating menu items)
            $item = get_post($item_id);
            if (!$item || $item->post_type !== 'nav_menu_item') {
                error_log("WPFD: Attempted to update non-menu-item post ID $item_id");
                continue; // Skip non-menu-items
            }
            
            wp_update_post(['ID' => $item_id, 'menu_order' => $menu_order]);
            $updated_count++;
        }

        return ['message' => "Menu items reordered successfully ($updated_count items updated)"];
    }

    public function update_menu_items($request) {
        $menu_id = intval($request['id']);
        $item_id = isset($request['item_id']) ? absint($request['item_id']) : 0;
        
        // Verify menu belongs to current blog
        if (is_multisite() && !$this->verify_menu_ownership($menu_id)) {
            return new \WP_Error(
                'menu_not_found',
                'This menu does not belong to your site',
                ['status' => 403]
            );
        }

        // Validate required fields
        if (empty($request['title'])) {
            return new \WP_Error('missing_title', 'Menu item title is required', ['status' => 400]);
        }

        // Sanitize and validate all input
        $item_data = [
            'menu-item-title' => sanitize_text_field($request['title']),
            'menu-item-url'   => esc_url_raw($request['url']),
            'menu-item-status' => 'publish',
            'menu-item-type'   => in_array($request['type'] ?? 'custom', ['post_type', 'post_type_archive', 'taxonomy', 'custom']) ? $request['type'] : 'custom',
            'menu-item-object' => sanitize_text_field($request['object'] ?? ''),
            'menu-item-object-id' => absint($request['object_id'] ?? 0),
            'menu-item-classes' => sanitize_text_field($request['classes'] ?? ''),
            'menu-item-description' => sanitize_textarea_field($request['description'] ?? ''),
            'menu-item-target' => in_array($request['target'] ?? '_self', ['_blank', '_self']) ? $request['target'] : '_self',
        ];

        if (isset($request['parent_id'])) {
            $item_data['menu-item-parent-id'] = absint($request['parent_id']);
        }

        $menu_item_db_id = wp_update_nav_menu_item($menu_id, $item_id, $item_data);

        if (is_wp_error($menu_item_db_id)) {
            return $menu_item_db_id;
        }

        // Save custom metadata with sanitization
        if (isset($request['icon']) && !empty($request['icon'])) {
            // Sanitize icon (emoji or safe icon string)
            $icon = sanitize_text_field($request['icon']);
            if (strlen($icon) <= 10) { // Reasonable length for emoji + spaces
                update_post_meta($menu_item_db_id, '_wpfd_menu_icon', $icon);
            }
        }
        if (isset($request['visibility'])) {
            // Validate visibility option
            $visibility = in_array($request['visibility'], ['public', 'private', 'restricted']) ? $request['visibility'] : 'public';
            update_post_meta($menu_item_db_id, '_wpfd_menu_visibility', $visibility);
        }

        return ['id' => $menu_item_db_id, 'message' => 'Menu item saved successfully'];
    }

    public function assign_menu($request) {
        $location = $request['location'];
        $menu_id = intval($request['menu_id']);

        // Verify menu belongs to current blog
        if (is_multisite() && !$this->verify_menu_ownership($menu_id)) {
            return new \WP_Error(
                'menu_not_found',
                'This menu does not belong to your site',
                ['status' => 403]
            );
        }

        // Handle virtual assignment if theme has no locations
        if ($location === 'wpfd_virtual_primary') {
            if (is_multisite()) {
                update_blog_option(get_current_blog_id(), 'wpfd_virtual_primary_menu', $menu_id);
            } else {
                update_option('wpfd_virtual_primary_menu', $menu_id);
            }
            return ['message' => 'Virtual Primary menu set successfully', 'location' => 'wpfd_virtual_primary'];
        }

        $locations = get_theme_mod('nav_menu_locations');
        if (!is_array($locations)) {
            $locations = [];
        }
        
        $locations[$location] = $menu_id;
        set_theme_mod('nav_menu_locations', $locations);

        $current_locations = get_nav_menu_locations();
        $current_locations[$location] = $menu_id;
        set_theme_mod('nav_menu_locations', $current_locations);

        return ['message' => 'Menu assigned successfully', 'locations' => $current_locations];
    }

    public function get_locations() {
        $locations = get_registered_nav_menus();
        $assigned = get_nav_menu_locations();
        
        // Get blog-specific option if multisite
        $nav_menu_options = is_multisite()
            ? get_blog_option(get_current_blog_id(), 'nav_menu_options', [])
            : get_option('nav_menu_options', []);
        
        $auto_add_menus = isset($nav_menu_options['auto_add']) ? (array) $nav_menu_options['auto_add'] : [];
        
        $response = [];
        foreach ($locations as $slug => $name) {
            $response[] = [
                'slug' => $slug,
                'name' => $name,
                'menu_id' => isset($assigned[$slug]) ? $assigned[$slug] : 0
            ];
        }

        // If theme has no locations, provide a virtual one for the user to select
        if (empty($response)) {
            $virtual_id = is_multisite()
                ? get_blog_option(get_current_blog_id(), 'wpfd_virtual_primary_menu', 0)
                : get_option('wpfd_virtual_primary_menu', 0);
            
            $response[] = [
                'slug' => 'wpfd_virtual_primary',
                'name' => 'Primary Menu (Virtual)',
                'menu_id' => $virtual_id
            ];
        }
        
        return [
            'locations' => $response,
            'auto_add_menus' => $auto_add_menus,
            'current_blog_id' => get_current_blog_id(),
        ];
    }

    public function set_auto_add($request) {
        $menu_id = intval($request['id']);
        $enabled = (bool) $request['enabled'];

        // Verify menu belongs to current blog
        if (is_multisite() && !$this->verify_menu_ownership($menu_id)) {
            return new \WP_Error(
                'menu_not_found',
                'This menu does not belong to your site',
                ['status' => 403]
            );
        }

        // Get blog-specific option if multisite
        if (is_multisite()) {
            $nav_menu_options = get_blog_option(get_current_blog_id(), 'nav_menu_options', []);
        } else {
            $nav_menu_options = get_option('nav_menu_options', []);
        }

        $auto_add = isset($nav_menu_options['auto_add']) ? (array) $nav_menu_options['auto_add'] : [];

        if ($enabled) {
            if (!in_array($menu_id, $auto_add)) {
                $auto_add[] = $menu_id;
            }
        } else {
            $auto_add = array_filter($auto_add, fn($id) => $id !== $menu_id);
        }

        $nav_menu_options['auto_add'] = array_values(array_unique($auto_add));
        
        if (is_multisite()) {
            update_blog_option(get_current_blog_id(), 'nav_menu_options', $nav_menu_options);
        } else {
            update_option('nav_menu_options', $nav_menu_options);
        }

        return ['message' => 'Auto-add setting updated successfully', 'auto_add' => $nav_menu_options['auto_add']];
    }

    /**
     * Clone an existing menu with all its items
     */
    public function clone_menu($request) {
        $menu_id = intval($request['id']);
        $new_name = $request->get_param('name') ?? 'Cloned Menu';

        // Verify menu belongs to current blog
        if (is_multisite() && !$this->verify_menu_ownership($menu_id)) {
            return new \WP_Error(
                'menu_not_found',
                'This menu does not belong to your site',
                ['status' => 403]
            );
        }

        // Get original menu
        $original_menu = wp_get_nav_menu_object($menu_id);
        if (!$original_menu) {
            return new \WP_Error('menu_not_found', 'Original menu not found', ['status' => 404]);
        }

        // Create new menu
        $new_menu_id = wp_create_nav_menu($new_name);
        if (is_wp_error($new_menu_id)) {
            return $new_menu_id;
        }

        // Store blog ID metadata for multisite
        if (is_multisite()) {
            update_term_meta($new_menu_id, 'wpfd_blog_id', get_current_blog_id());
        }

        // Get original menu items
        $items = wp_get_nav_menu_items($menu_id);
        $item_map = []; // Map old IDs to new IDs

        if ($items) {
            foreach ($items as $item) {
                // Skip parent items on first pass, handle them later
                if ($item->menu_item_parent != 0) continue;

                $new_item_id = $this->clone_menu_item($item, $new_menu_id, 0, $item_map);
                if (!is_wp_error($new_item_id)) {
                    $item_map[$item->ID] = $new_item_id;
                }
            }

            // Now handle nested items
            foreach ($items as $item) {
                if ($item->menu_item_parent != 0 && isset($item_map[$item->menu_item_parent])) {
                    $new_item_id = $this->clone_menu_item($item, $new_menu_id, $item_map[$item->menu_item_parent], $item_map);
                    if (!is_wp_error($new_item_id)) {
                        $item_map[$item->ID] = $new_item_id;
                    }
                }
            }
        }

        return [
            'id' => $new_menu_id,
            'name' => $new_name,
            'message' => 'Menu cloned successfully',
            'items_count' => count($item_map)
        ];
    }

    /**
     * Clone a single menu item
     */
    private function clone_menu_item($item, $menu_id, $parent_id, &$item_map) {
        $item_data = [
            'menu-item-title' => $item->title,
            'menu-item-url' => $item->url,
            'menu-item-status' => 'publish',
            'menu-item-type' => $item->type,
            'menu-item-object' => $item->object,
            'menu-item-object-id' => $item->object_id,
            'menu-item-parent-id' => $parent_id,
            'menu-item-classes' => implode(' ', $item->classes ?? []),
            'menu-item-description' => $item->description ?? '',
            'menu-item-target' => $item->target ?? '',
        ];

        $new_item_id = wp_update_nav_menu_item($menu_id, 0, $item_data);

        if (!is_wp_error($new_item_id)) {
            // Copy metadata
            $icon = get_post_meta($item->ID, '_wpfd_menu_icon', true);
            if ($icon) {
                update_post_meta($new_item_id, '_wpfd_menu_icon', $icon);
            }
            $visibility = get_post_meta($item->ID, '_wpfd_menu_visibility', true);
            if ($visibility) {
                update_post_meta($new_item_id, '_wpfd_menu_visibility', $visibility);
            }
        }

        return $new_item_id;
    }

    /**
     * Get available menu presets
     */
    public function get_presets() {
        $presets = [
            [
                'id' => 'basic',
                'name' => 'Basic Navigation',
                'description' => 'Simple menu with Home, About, Services, Contact',
                'items' => [
                    ['title' => 'Home', 'url' => home_url('/'), 'type' => 'custom', 'parent' => 0],
                    ['title' => 'About', 'url' => home_url('/about'), 'type' => 'custom', 'parent' => 0],
                    ['title' => 'Services', 'url' => home_url('/services'), 'type' => 'custom', 'parent' => 0],
                    ['title' => 'Contact', 'url' => home_url('/contact'), 'type' => 'custom', 'parent' => 0],
                ]
            ],
            [
                'id' => 'ecommerce',
                'name' => 'E-Commerce Menu',
                'description' => 'Menu suitable for online stores with Products submenu',
                'items' => [
                    ['title' => 'Home', 'url' => home_url('/'), 'type' => 'custom', 'parent' => 0],
                    ['title' => 'Shop', 'url' => home_url('/shop'), 'type' => 'custom', 'parent' => 0],
                    ['title' => 'Products', 'url' => '#', 'type' => 'custom', 'parent' => 0],
                    ['title' => 'New Arrivals', 'url' => home_url('/products/new'), 'type' => 'custom', 'parent' => 3],
                    ['title' => 'Sale Items', 'url' => home_url('/products/sale'), 'type' => 'custom', 'parent' => 3],
                    ['title' => 'About', 'url' => home_url('/about'), 'type' => 'custom', 'parent' => 0],
                    ['title' => 'Contact', 'url' => home_url('/contact'), 'type' => 'custom', 'parent' => 0],
                ]
            ],
            [
                'id' => 'blog',
                'name' => 'Blog Menu',
                'description' => 'Menu for blog sites with categories',
                'items' => [
                    ['title' => 'Home', 'url' => home_url('/'), 'type' => 'custom', 'parent' => 0],
                    ['title' => 'Blog', 'url' => home_url('/blog'), 'type' => 'custom', 'parent' => 0],
                    ['title' => 'Categories', 'url' => '#', 'type' => 'custom', 'parent' => 0],
                    ['title' => 'Technology', 'url' => home_url('/category/technology'), 'type' => 'custom', 'parent' => 3],
                    ['title' => 'Lifestyle', 'url' => home_url('/category/lifestyle'), 'type' => 'custom', 'parent' => 3],
                    ['title' => 'Travel', 'url' => home_url('/category/travel'), 'type' => 'custom', 'parent' => 3],
                    ['title' => 'About', 'url' => home_url('/about'), 'type' => 'custom', 'parent' => 0],
                    ['title' => 'Contact', 'url' => home_url('/contact'), 'type' => 'custom', 'parent' => 0],
                ]
            ]
        ];

        return $presets;
    }

    /**
     * Apply a preset menu template
     */
    public function apply_preset($request) {
        $preset_id = $request->get_param('preset_id');
        $menu_name = $request->get_param('menu_name') ?? 'Preset Menu';

        $presets = $this->get_presets();
        $preset = null;

        foreach ($presets as $p) {
            if ($p['id'] === $preset_id) {
                $preset = $p;
                break;
            }
        }

        if (!$preset) {
            return new \WP_Error('preset_not_found', 'Preset not found', ['status' => 404]);
        }

        // Create menu
        $menu_id = wp_create_nav_menu($menu_name);
        if (is_wp_error($menu_id)) {
            return $menu_id;
        }

        // Store blog ID metadata
        if (is_multisite()) {
            update_term_meta($menu_id, 'wpfd_blog_id', get_current_blog_id());
        }

        // Add items
        $item_map = [];
        foreach ($preset['items'] as $idx => $item) {
            $parent_id = 0;
            if ($item['parent'] > 0 && isset($item_map[$item['parent'] - 1])) {
                $parent_id = $item_map[$item['parent'] - 1];
            }

            $item_data = [
                'menu-item-title' => $item['title'],
                'menu-item-url' => $item['url'],
                'menu-item-status' => 'publish',
                'menu-item-type' => $item['type'],
                'menu-item-parent-id' => $parent_id,
            ];

            $item_id = wp_update_nav_menu_item($menu_id, 0, $item_data);
            if (!is_wp_error($item_id)) {
                $item_map[] = $item_id;
            }
        }

        return [
            'id' => $menu_id,
            'name' => $menu_name,
            'preset' => $preset_id,
            'message' => 'Menu created from preset successfully',
            'items_count' => count($item_map)
        ];
    }

    /**
     * Verify that a menu belongs to the current blog (multisite only)
     */
    private function verify_menu_ownership($menu_id) {
        if (!is_multisite()) {
            return true;
        }

        // Check if menu exists in current blog's menu list
        // WordPress automatically scopes wp_get_nav_menus() to current blog
        $menus = wp_get_nav_menus();
        foreach ($menus as $menu) {
            if (intval($menu->term_id) === intval($menu_id)) {
                return true;
            }
        }
        
        error_log("WPFD: Menu $menu_id not found in blog " . get_current_blog_id());
        return false;
    }
}
