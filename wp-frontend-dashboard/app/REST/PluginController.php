<?php
namespace WPFD\REST;

class PluginController {
    public function register() {
        $namespace = 'wpfd/v1';
        $base = '/plugins';

        // List plugins
        register_rest_route($namespace, $base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_items'],
            'permission_callback' => [$this, 'admin_permission'],
        ]);

        // Activate plugin
        register_rest_route($namespace, $base . '/activate', [
            'methods' => 'POST',
            'callback' => [$this, 'activate_plugin'],
            'permission_callback' => [$this, 'admin_permission'],
        ]);

        // Deactivate plugin
        register_rest_route($namespace, $base . '/deactivate', [
            'methods' => 'POST',
            'callback' => [$this, 'deactivate_plugin'],
            'permission_callback' => [$this, 'admin_permission'],
        ]);

    }

    public function admin_permission() {
        // In multisite, check if user can activate plugins for this site
        if (is_multisite()) {
            return current_user_can('activate_plugins') || is_super_admin();
        }
        return current_user_can('activate_plugins');
    }

    public function get_items($request) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);

        // In multisite, also get network active plugins
        $network_active = [];
        if (is_multisite()) {
            $network_active = get_site_option('active_sitewide_plugins', []);
        }

        $plugins = [];
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $is_active = in_array($plugin_file, $active_plugins);
            $is_network_active = isset($network_active[$plugin_file]);

            $plugins[] = [
                'name' => $plugin_data['Name'],
                'description' => $plugin_data['Description'],
                'version' => $plugin_data['Version'],
                'author' => $plugin_data['Author'],
                'plugin_file' => $plugin_file,
                'is_active' => $is_active,
                'is_network_active' => $is_network_active,
                'can_deactivate' => $is_active && !$is_network_active,
            ];
        }

        return $plugins;
    }

    public function activate_plugin($request) {
        $params = $request->get_json_params();
        $plugin_file = $params['plugin_file'] ?? '';

        if (empty($plugin_file)) {
            return new \WP_Error('invalid_plugin', 'Plugin file is required', ['status' => 400]);
        }

        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $result = activate_plugin($plugin_file);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'success' => true,
            'message' => 'Plugin activated successfully',
        ];
    }

    public function deactivate_plugin($request) {
        $params = $request->get_json_params();
        $plugin_file = $params['plugin_file'] ?? '';

        if (empty($plugin_file)) {
            return new \WP_Error('invalid_plugin', 'Plugin file is required', ['status' => 400]);
        }

        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        deactivate_plugins($plugin_file);

        return [
            'success' => true,
            'message' => 'Plugin deactivated successfully',
        ];
    }

    public function get_themes($request) {
        $themes = wp_get_themes();
        $current_theme = wp_get_theme()->get_stylesheet();

        $formatted_themes = [];
        foreach ($themes as $theme_slug => $theme) {
            $formatted_themes[] = [
                'name' => $theme->get('Name'),
                'description' => $theme->get('Description'),
                'version' => $theme->get('Version'),
                'author' => $theme->get('Author'),
                'screenshot' => $theme->get_screenshot(),
                'stylesheet' => $theme_slug,
                'is_active' => $theme_slug === $current_theme,
            ];
        }

        return $formatted_themes;
    }

    public function switch_theme($request) {
        if (!current_user_can('switch_themes')) {
            return new \WP_Error('forbidden', 'You do not have permission to switch themes', ['status' => 403]);
        }

        $params = $request->get_json_params();
        $stylesheet = $params['stylesheet'] ?? '';

        if (empty($stylesheet)) {
            return new \WP_Error('invalid_theme', 'Theme stylesheet is required', ['status' => 400]);
        }

        $theme = wp_get_theme($stylesheet);
        if (!$theme->exists()) {
            return new \WP_Error('theme_not_found', 'Theme not found', ['status' => 404]);
        }

        switch_theme($stylesheet);

        return [
            'success' => true,
            'message' => 'Theme activated successfully',
        ];
    }
}
