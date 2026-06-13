<?php

namespace WPFD\REST;

class ToolsController {
    public function register() {
        $namespace = 'wpfd/v1';

        register_rest_route($namespace, '/tools/privacy', [
            'methods' => 'GET',
            'callback' => [$this, 'get_privacy_status'],
            'permission_callback' => [$this, 'tools_permission'],
        ]);

        register_rest_route($namespace, '/tools/privacy/export', [
            'methods' => 'POST',
            'callback' => [$this, 'create_export_request'],
            'permission_callback' => [$this, 'tools_permission'],
        ]);

        register_rest_route($namespace, '/tools/privacy/erase', [
            'methods' => 'POST',
            'callback' => [$this, 'create_erase_request'],
            'permission_callback' => [$this, 'tools_permission'],
        ]);

        register_rest_route($namespace, '/tools/flush-rewrite', [
            'methods' => 'POST',
            'callback' => [$this, 'flush_rewrite'],
            'permission_callback' => [$this, 'tools_permission'],
        ]);
    }

    public function tools_permission() {
        if (!is_user_logged_in()) {
            return false;
        }

        if (is_multisite() && !is_user_member_of_blog(get_current_user_id(), get_current_blog_id())) {
            return false;
        }

        return current_user_can('manage_options');
    }

    public function get_privacy_status() {
        $privacy_page_id = (int) get_option('wp_page_for_privacy_policy', 0);
        $privacy_page = $privacy_page_id ? get_post($privacy_page_id) : null;

        $export_requests = get_posts([
            'post_type' => 'user_request',
            'post_status' => 'any',
            'posts_per_page' => 25,
            'meta_key' => '_wp_user_request_action_name',
            'meta_value' => 'export_personal_data',
        ]);

        $erase_requests = get_posts([
            'post_type' => 'user_request',
            'post_status' => 'any',
            'posts_per_page' => 25,
            'meta_key' => '_wp_user_request_action_name',
            'meta_value' => 'remove_personal_data',
        ]);

        return [
            'privacy_policy_page' => $privacy_page ? [
                'id' => $privacy_page_id,
                'title' => $privacy_page->post_title,
            ] : null,
            'export_requests' => count($export_requests),
            'erase_requests' => count($erase_requests),
        ];
    }

    public function create_export_request($request) {
        return $this->create_privacy_request($request, 'export_personal_data');
    }

    public function create_erase_request($request) {
        return $this->create_privacy_request($request, 'remove_personal_data');
    }

    private function create_privacy_request($request, $action_name) {
        $email = sanitize_email($request->get_param('email') ?: '');

        if (!is_email($email)) {
            return new \WP_Error('invalid_email', 'A valid email is required.', ['status' => 400]);
        }

        if (!function_exists('wp_create_user_request')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        $request_id = wp_create_user_request($email, $action_name);
        if (is_wp_error($request_id)) {
            return $request_id;
        }

        wp_send_user_request($request_id);

        return [
            'success' => true,
            'request_id' => (int) $request_id,
            'message' => 'Privacy request created and confirmation email sent.',
        ];
    }

    public function flush_rewrite() {
        flush_rewrite_rules();

        return [
            'success' => true,
            'message' => 'Rewrite rules flushed successfully.',
        ];
    }
}
