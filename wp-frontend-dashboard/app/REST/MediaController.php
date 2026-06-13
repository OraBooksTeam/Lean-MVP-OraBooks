<?php

namespace WPFD\REST;

class MediaController {
    public function register() {
        $namespace = 'wpfd/v1';
        $base = '/media';

        register_rest_route($namespace, $base, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'media_read_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'media_write_permission'],
            ],
        ]);

        // Single item routes: delete and update metadata
        register_rest_route($namespace, $base . '/(?P<id>[\d]+)', [
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_item'],
                'permission_callback' => [$this, 'media_delete_permission'],
            ],
            [
                'methods' => ['PUT','PATCH','POST'],
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'media_update_permission'],
            ],
        ]);

        // Bulk delete
        register_rest_route($namespace, $base . '/bulk-delete', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'bulk_delete'],
                'permission_callback' => [$this, 'media_write_permission'],
            ],
        ]);
    }

    private function is_allowed_on_site() {
        if (!is_user_logged_in()) {
            return false;
        }

        if (is_multisite() && !is_user_member_of_blog(get_current_user_id(), get_current_blog_id())) {
            return false;
        }

        return true;
    }

    public function media_read_permission() {
        return $this->is_allowed_on_site() && current_user_can('upload_files');
    }

    public function media_write_permission() {
        return $this->is_allowed_on_site() && current_user_can('upload_files');
    }

    public function media_update_permission($request) {
        if (!$this->is_allowed_on_site() || !current_user_can('upload_files')) {
            return false;
        }

        $id = isset($request['id']) ? intval($request['id']) : 0;
        if (!$id) {
            return new \WP_Error('invalid_id', 'Invalid media ID', ['status' => 400]);
        }

        return current_user_can('edit_post', $id);
    }

    public function media_delete_permission($request) {
        if (!$this->is_allowed_on_site() || !current_user_can('upload_files')) {
            return false;
        }

        $id = isset($request['id']) ? intval($request['id']) : 0;
        if (!$id) {
            return new \WP_Error('invalid_id', 'Invalid media ID', ['status' => 400]);
        }

        return current_user_can('delete_post', $id);
    }

    public function get_items($request) {
        $page = max(1, intval($request->get_param('page') ?: 1));
        $per_page = max(1, min(100, intval($request->get_param('per_page') ?: 20)));
        $search = $request->get_param('search') ? sanitize_text_field($request->get_param('search')) : '';
        $mime = $request->get_param('mime') ? sanitize_text_field($request->get_param('mime')) : '';

        $args = [
            'post_type' => 'attachment',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => 'inherit',
        ];

        if (!empty($search)) {
            $args['s'] = $search;
        }

        if (!empty($mime)) {
            $args['post_mime_type'] = $mime;
        }

        $query = new \WP_Query($args);
        $media = $query->posts;
        $total = (int) $query->found_posts;

        $items = array_map(function($item) {
            return [
                'id' => $item->ID,
                'title' => $item->post_title,
                'url' => wp_get_attachment_url($item->ID),
                'mime' => $item->post_mime_type,
            ];
        }, $media);

        return new \WP_REST_Response([
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'items' => $items,
        ], 200);
    }

    public function update_item($request) {
        $id = intval($request['id']);
        if (!$id) return new \WP_Error('invalid_id', 'Invalid media ID', ['status' => 400]);

        $title = $request->get_param('title');
        $updated = [];

        if (!is_null($title)) {
            $title = sanitize_text_field($title);
            $result = wp_update_post([
                'ID' => $id,
                'post_title' => $title,
            ], true);

            if (is_wp_error($result)) return $result;

            $updated['title'] = $title;
        }

        return new \WP_REST_Response(array_merge(['id' => $id, 'message' => 'Updated successfully'], $updated), 200);
    }

    public function bulk_delete($request) {
        $ids = $request->get_param('ids');
        if (!is_array($ids)) return new \WP_Error('invalid_ids', 'IDs must be an array', ['status' => 400]);

        $ids = array_map('intval', $ids);
        $deleted = [];
        $errors = [];

        foreach ($ids as $id) {
            if (!current_user_can('delete_post', $id)) {
                $errors[] = $id;
                continue;
            }

            $res = wp_delete_attachment($id, true);
            if ($res) $deleted[] = $id;
            else $errors[] = $id;
        }

        return new \WP_REST_Response(['deleted' => $deleted, 'errors' => $errors], 200);
    }

    public function create_item($request) {
        if (empty($_FILES['file'])) {
            return new \WP_Error('no_file', 'No file uploaded', ['status' => 400]);
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('file', 0);

        if (is_wp_error($attachment_id)) return $attachment_id;

        return [
            'id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
            'message' => 'File uploaded successfully'
        ];
    }

    public function delete_item($request) {
        $id = intval($request['id']);
        if (!$id) return new \WP_Error('invalid_id', 'Invalid media ID', ['status' => 400]);

        if (!current_user_can('delete_post', $id)) {
            return new \WP_Error('rest_forbidden', 'You do not have permission to delete this media.', ['status' => 403]);
        }

        $result = wp_delete_attachment($id, true);
        if (!$result) {
            return new \WP_Error('delete_failed', 'Delete failed', ['status' => 500]);
        }

        return ['message' => 'Media deleted successfully'];
    }
}
