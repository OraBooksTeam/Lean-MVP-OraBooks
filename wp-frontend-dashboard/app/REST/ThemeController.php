<?php

namespace WPFD\REST;

class ThemeController {
    public function register() {
        $namespace = 'wpfd/v1';
        $base = '/themes';

        register_rest_route($namespace, $base, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_themes'],
                'permission_callback' => [$this, 'themes_permission'],
            ],
        ]);

        register_rest_route($namespace, $base . '/activate', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'activate_theme'],
                'permission_callback' => [$this, 'activate_permission'],
            ],
        ]);
    }

    public function themes_permission() {
        if (!is_user_logged_in()) {
            return false;
        }

        if (is_multisite() && !is_user_member_of_blog(get_current_user_id(), get_current_blog_id())) {
            return false;
        }

        return current_user_can('switch_themes') || current_user_can('manage_options');
    }

    public function activate_permission() {
        if (!$this->themes_permission()) {
            return false;
        }

        return current_user_can('switch_themes');
    }

    public function get_themes() {
        $themes = wp_get_themes();
        $data = [];

        foreach ($themes as $slug => $theme) {
            $data[] = [
                'name' => $theme->get('Name'),
                'slug' => $slug,
                'description' => $theme->get('Description'),
                'author' => $theme->get('Author'),
                'version' => $theme->get('Version'),
                'screenshot' => $theme->get_screenshot() ?: '',
                'active' => get_stylesheet() === $slug,
            ];
        }

        return $data;
    }

    public function activate_theme($request) {
        if (!current_user_can('switch_themes')) {
            return new \WP_Error('rest_forbidden', 'You do not have permission to switch themes.', ['status' => 403]);
        }

        $slug = $request->get_param('slug');
        
        if (!$slug) {
            return new \WP_Error('missing_slug', 'Theme slug is required', ['status' => 400]);
        }

        $theme = wp_get_theme($slug);
        if (!$theme->exists()) {
            return new \WP_Error('invalid_theme', 'Theme does not exist', ['status' => 404]);
        }

        switch_theme($slug);

        return [
            'success' => true,
            'message' => sprintf('Theme "%s" activated successfully', $theme->get('Name')),
        ];
    }
}
