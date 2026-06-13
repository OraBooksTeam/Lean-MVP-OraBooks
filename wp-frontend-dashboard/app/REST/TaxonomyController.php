<?php

namespace WPFD\REST;

class TaxonomyController {
    public function register() {
        $namespace = 'wpfd/v1';

        register_rest_route($namespace, '/taxonomies/(?P<taxonomy>[a-zA-Z0-9_-]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_terms'],
                'permission_callback' => [$this, 'taxonomy_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_term'],
                'permission_callback' => [$this, 'taxonomy_permission'],
            ],
        ]);

        register_rest_route($namespace, '/taxonomies/(?P<taxonomy>[a-zA-Z0-9_-]+)/(?P<id>[\d]+)', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_term'],
                'permission_callback' => [$this, 'taxonomy_permission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_term'],
                'permission_callback' => [$this, 'taxonomy_permission'],
            ],
        ]);
    }

    public function taxonomy_permission() {
        if (!is_user_logged_in()) {
            return false;
        }

        if (is_multisite() && !is_user_member_of_blog(get_current_user_id(), get_current_blog_id())) {
            return false;
        }

        return current_user_can('manage_categories');
    }

    private function normalize_taxonomy($taxonomy) {
        $taxonomy = sanitize_key((string) $taxonomy);
        return in_array($taxonomy, ['category', 'post_tag'], true) ? $taxonomy : '';
    }

    public function get_terms($request) {
        $taxonomy = $this->normalize_taxonomy($request['taxonomy']);
        if (!$taxonomy) {
            return new \WP_Error('invalid_taxonomy', 'Invalid taxonomy requested.', ['status' => 400]);
        }

        $search = sanitize_text_field($request->get_param('search') ?: '');

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'search' => $search,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (is_wp_error($terms)) {
            return $terms;
        }

        return array_map(function($term) {
            return [
                'id' => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
                'count' => (int) $term->count,
            ];
        }, $terms);
    }

    public function create_term($request) {
        $taxonomy = $this->normalize_taxonomy($request['taxonomy']);
        if (!$taxonomy) {
            return new \WP_Error('invalid_taxonomy', 'Invalid taxonomy requested.', ['status' => 400]);
        }

        $name = sanitize_text_field($request->get_param('name') ?: '');
        $slug = sanitize_title($request->get_param('slug') ?: '');
        $description = sanitize_textarea_field($request->get_param('description') ?: '');

        if ($name === '') {
            return new \WP_Error('missing_name', 'Term name is required.', ['status' => 400]);
        }

        $result = wp_insert_term($name, $taxonomy, [
            'slug' => $slug,
            'description' => $description,
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'success' => true,
            'id' => (int) $result['term_id'],
            'message' => 'Term created successfully.',
        ];
    }

    public function update_term($request) {
        $taxonomy = $this->normalize_taxonomy($request['taxonomy']);
        $id = intval($request['id']);

        if (!$taxonomy || !$id) {
            return new \WP_Error('invalid_request', 'Invalid taxonomy or term ID.', ['status' => 400]);
        }

        $name = sanitize_text_field($request->get_param('name') ?: '');
        $slug = sanitize_title($request->get_param('slug') ?: '');
        $description = sanitize_textarea_field($request->get_param('description') ?: '');

        if ($name === '') {
            return new \WP_Error('missing_name', 'Term name is required.', ['status' => 400]);
        }

        $result = wp_update_term($id, $taxonomy, [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'success' => true,
            'id' => $id,
            'message' => 'Term updated successfully.',
        ];
    }

    public function delete_term($request) {
        $taxonomy = $this->normalize_taxonomy($request['taxonomy']);
        $id = intval($request['id']);

        if (!$taxonomy || !$id) {
            return new \WP_Error('invalid_request', 'Invalid taxonomy or term ID.', ['status' => 400]);
        }

        $result = wp_delete_term($id, $taxonomy);
        if (is_wp_error($result)) {
            return $result;
        }

        if (!$result) {
            return new \WP_Error('delete_failed', 'Failed to delete term.', ['status' => 500]);
        }

        return [
            'success' => true,
            'message' => 'Term deleted successfully.',
        ];
    }
}
