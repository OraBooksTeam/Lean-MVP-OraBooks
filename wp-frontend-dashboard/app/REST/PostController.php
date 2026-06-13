<?php
namespace WPFD\REST;

class PostController {
    public function register() {
        $namespace = 'wpfd/v1';
        $base = '/posts';

        register_rest_route($namespace, $base, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route($namespace, $base . '/(?P<id>[\d]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_item'],
                // Require per-post read capability
                'permission_callback' => function($request) {
                    $id = isset($request['id']) ? intval($request['id']) : 0;
                    if (!$id) return new \WP_Error('invalid_id', 'Invalid post ID', ['status' => 400]);
                    if (!current_user_can('read_post', $id)) {
                        return new \WP_Error('rest_forbidden', 'You do not have permission to view this post.', ['status' => 403]);
                    }
                    return true;
                },
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_item'],
                // Require per-post edit capability
                'permission_callback' => function($request) {
                    $id = isset($request['id']) ? intval($request['id']) : 0;
                    if (!$id) return new \WP_Error('invalid_id', 'Invalid post ID', ['status' => 400]);
                    if (!current_user_can('edit_post', $id)) {
                        return new \WP_Error('rest_forbidden', 'You do not have permission to edit this post.', ['status' => 403]);
                    }
                    return true;
                },
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_item'],
                // Require per-post delete capability
                'permission_callback' => function($request) {
                    $id = isset($request['id']) ? intval($request['id']) : 0;
                    if (!$id) return new \WP_Error('invalid_id', 'Invalid post ID', ['status' => 400]);
                    if (!current_user_can('delete_post', $id)) {
                        return new \WP_Error('rest_forbidden', 'You do not have permission to delete this post.', ['status' => 403]);
                    }
                    return true;
                },
            ],
        ]);
    }

    /**
     * Check if user is logged in and is a member of the current subsite.
     */
    public function check_permission($request = null) {
        if (!is_user_logged_in()) return false;
        
        // In multisite, ensure they belong to this specific site
        if (is_multisite() && !is_user_member_of_blog(get_current_user_id(), get_current_blog_id())) {
            return false;
        }

        $method = $request ? $request->get_method() : 'GET';

        // For write operations, require edit_posts capability
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return current_user_can('edit_posts');
        }

        // For read operations, only require read capability
        return current_user_can('read');
    }

    public function get_items($request) {
        $page = max(1, intval($request->get_param('page') ?: 1));
        $per_page = max(1, min(100, intval($request->get_param('per_page') ?: 20)));
        $search = sanitize_text_field($request->get_param('search') ?: '');
        $status = sanitize_text_field($request->get_param('status') ?: 'any');

        $query_args = [
            'post_type' => 'post',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => $status && $status !== 'all' ? $status : 'any',
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if (!empty($search)) {
            $query_args['s'] = $search;
        }

        $query = new \WP_Query($query_args);
        $posts = $query->posts;

        $items = array_map(function($post) {
            return [
                'id' => $post->ID,
                'title' => $post->post_title,
                'status' => $post->post_status,
                'date' => $post->post_date,
                'author' => get_the_author_meta('display_name', $post->post_author),
            ];
        }, $posts);

        return [
            'items' => $items,
            'total' => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
            'page' => $page,
            'per_page' => $per_page,
        ];
    }

    public function create_item($request) {
        $post_data = [
            'post_title' => sanitize_text_field($request['title']),
            'post_content' => isset($request['content']) ? wp_kses_post($request['content']) : '',
            'post_excerpt' => isset($request['excerpt']) ? sanitize_textarea_field($request['excerpt']) : '',
            'post_status' => $request['status'] ?: 'draft',
            'post_type' => 'post',
            'post_author' => get_current_user_id(),
        ];

        if (isset($request['slug']) && $request['slug'] !== '') {
            $post_data['post_name'] = sanitize_title($request['slug']);
        }

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) return $post_id;
        if (!$post_id) {
            return new \WP_Error('creation_failed', 'Failed to create post in the database', ['status' => 500]);
        }
        
        return ['id' => $post_id, 'message' => 'Post created successfully'];
    }

    public function get_item($request) {
        $post = get_post($request['id']);
        if (!$post) return new \WP_Error('no_post', 'Post not found', ['status' => 404]);

        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'slug' => $post->post_name,
            'status' => $post->post_status,
        ];
    }

    public function update_item($request) {
        $post_data = [
            'ID' => $request['id'],
            'post_title' => sanitize_text_field($request['title']),
            'post_content' => isset($request['content']) ? wp_kses_post($request['content']) : '',
            'post_excerpt' => isset($request['excerpt']) ? sanitize_textarea_field($request['excerpt']) : '',
            'post_status' => $request['status'],
        ];

        if (isset($request['slug'])) {
            $post_data['post_name'] = sanitize_title($request['slug']);
        }

        $post_id = wp_update_post($post_data);

        if (is_wp_error($post_id)) return $post_id;
        return ['id' => $post_id, 'message' => 'Post updated successfully'];
    }

    public function delete_item($request) {
        $id = intval($request['id'] ?? 0);
        if (!$id) return new \WP_Error('invalid_id', 'Invalid post ID', ['status' => 400]);

        if (!current_user_can('delete_post', $id)) {
            return new \WP_Error('rest_forbidden', 'You do not have permission to delete this post.', ['status' => 403]);
        }

        $result = wp_delete_post($id, true);
        if (!$result) return new \WP_Error('delete_failed', 'Delete failed', ['status' => 500]);
        return ['message' => 'Post deleted successfully'];
    }
}
