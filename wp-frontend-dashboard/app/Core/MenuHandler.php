<?php
namespace WPFD\Core;

class MenuHandler {
    public function register() {
        // Filter the menu arguments before the menu is displayed
        add_filter('wp_nav_menu_args', [$this, 'force_menu_fallback']);
        
        // Listen for new page publication to auto-add to menus
        add_action('publish_page', [$this, 'auto_add_new_pages'], 10, 2);
    }

    /**
     * Automatically adds newly published pages to menus that have the 'auto_add' setting enabled.
     */
    public function auto_add_new_pages($post_id, $post) {
        // Check if this is a top-level page
        if ($post->post_parent !== 0) {
            return;
        }

        // Get menus that have auto-add enabled
        // This is stored in a theme mod or option. WP uses a theme mod nav_menu_options['auto_add']
        $nav_menu_options = get_option('nav_menu_options');
        if (!isset($nav_menu_options['auto_add']) || !is_array($nav_menu_options['auto_add'])) {
            return;
        }

        foreach ($nav_menu_options['auto_add'] as $menu_id) {
            // Check if page is already in menu
            $menu_items = wp_get_nav_menu_items($menu_id);
            $already_in = false;
            if ($menu_items) {
                foreach ($menu_items as $item) {
                    if ($item->object === 'page' && intval($item->object_id) === intval($post_id)) {
                        $already_in = true;
                        break;
                    }
                }
            }

            if (!$already_in) {
                wp_update_nav_menu_item($menu_id, 0, [
                    'menu-item-object-id' => $post_id,
                    'menu-item-object' => 'page',
                    'menu-item-type' => 'post_type',
                    'menu-item-status' => 'publish',
                ]);
            }
        }
    }

    /**
     * Ensures that if no menu is assigned, we try to show a "Primary" menu
     * or the first available menu as a fallback.
     */
    public function force_menu_fallback($args) {
        // 1. Check if a theme location is specified
        $location = isset($args['theme_location']) ? $args['theme_location'] : '';
        
        // 2. Get currently assigned locations
        $locations = get_nav_menu_locations();
        
        // 3. If there is a location but no menu assigned, OR no location at all
        if (($location && !isset($locations[$location])) || !$location) {
            
            // Try to find a suitable menu
            $fallback_menu = $this->get_fallback_menu();
            
            if ($fallback_menu) {
                // Force the menu ID into the arguments
                $args['menu'] = $fallback_menu->term_id;
            }
        }

        return $args;
    }

    /**
     * Logic to find a fallback menu.
     * Priority: 
     * 1. Menu explicitly assigned as Virtual Primary
     * 2. Menu named "Primary" or "Main"
     * 3. First available menu
     */
    private function get_fallback_menu() {
        // 1. Check if user explicitly set a virtual primary
        $virtual_id = get_option('wpfd_virtual_primary_menu', 0);
        if ($virtual_id) {
            $menu = wp_get_nav_menu_object($virtual_id);
            if ($menu) return $menu;
        }

        $menus = wp_get_nav_menus();
        if (empty($menus)) {
            return null;
        }

        // 2. Search for specific names
        foreach ($menus as $menu) {
            $name = strtolower($menu->name);
            if ($name === 'primary' || $name === 'main' || $name === 'main menu' || $name === 'header' || $name === 'header menu') {
                return $menu;
            }
        }

        // 3. Just return the first one
        return $menus[0];
    }
}
