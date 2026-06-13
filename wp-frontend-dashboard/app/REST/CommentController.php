<?php
namespace WPFD\REST;

class CommentController {
    public function register() {
        $namespace = 'wpfd/v1';
        $base = '/comments';

        // List comments
        register_rest_route($namespace, $base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_items'],
            'permission_callback' => [$this, 'moderate_permission'],
        ]);

        // Single comment operations
        register_rest_route($namespace, $base . '/(?P<id>[\d]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'moderate_permission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'moderate_permission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_item'],
                'permission_callback' => [$this, 'moderate_permission'],
            ],
        ]);

        // Approve comment
        register_rest_route($namespace, $base . '/(?P<id>[\d]+)/approve', [
            'methods' => 'POST',
            'callback' => [$this, 'approve_comment'],
            'permission_callback' => [$this, 'moderate_permission'],
        ]);

        // Spam comment
        register_rest_route($namespace, $base . '/(?P<id>[\d]+)/spam', [
            'methods' => 'POST',
            'callback' => [$this, 'spam_comment'],
            'permission_callback' => [$this, 'moderate_permission'],
        ]);

        // Trash comment
        register_rest_route($namespace, $base . '/(?P<id>[\d]+)/trash', [
            'methods' => 'POST',
            'callback' => [$this, 'trash_comment'],
            'permission_callback' => [$this, 'moderate_permission'],
        ]);

        // Reply to comment
        register_rest_route($namespace, $base . '/(?P<id>[\d]+)/reply', [
            'methods' => 'POST',
            'callback' => [$this, 'reply_comment'],
            'permission_callback' => [$this, 'moderate_permission'],
        ]);

        // Bulk actions
        register_rest_route($namespace, $base . '/bulk', [
            'methods' => 'POST',
            'callback' => [$this, 'bulk_action'],
            'permission_callback' => [$this, 'moderate_permission'],
        ]);
    }

    public function moderate_permission() {
        return current_user_can('moderate_comments');
    }

    public function get_items($request) {
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 20;
        $status = $request->get_param('status') ?: 'all';
        $search = $request->get_param('search') ?: '';
        $post_id = $request->get_param('post_id') ?: 0;

        $args = [
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'orderby' => 'comment_date_gmt',
            'order' => 'DESC',
        ];

        if ($status !== 'all') {
            $args['status'] = $status;
        }

        if ($search) {
            $args['search'] = $search;
        }

        if ($post_id) {
            $args['post_id'] = $post_id;
        }

        $comments_query = new \WP_Comment_Query($args);
        $comments = $comments_query->comments;

        // Get total count
        $count_args = $args;
        unset($count_args['number'], $count_args['offset']);
        $count_args['count'] = true;
        $total = (new \WP_Comment_Query($count_args))->comments;

        return [
            'items' => array_map([$this, 'format_comment'], $comments),
            'total' => $total,
            'total_pages' => ceil($total / $per_page),
        ];
    }

    public function get_item($request) {
        $comment_id = $request->get_param('id');
        $comment = get_comment($comment_id);

        if (!$comment) {
            return new \WP_Error('comment_not_found', 'Comment not found', ['status' => 404]);
        }

        return $this->format_comment($comment, true);
    }

    public function update_item($request) {
        $comment_id = $request->get_param('id');
        $params = $request->get_json_params();

        $commentdata = ['comment_ID' => $comment_id];

        if (isset($params['content'])) {
            $commentdata['comment_content'] = wp_kses_post($params['content']);
        }
        if (isset($params['author_name'])) {
            $commentdata['comment_author'] = sanitize_text_field($params['author_name']);
        }
        if (isset($params['author_email'])) {
            $commentdata['comment_author_email'] = sanitize_email($params['author_email']);
        }
        if (isset($params['status'])) {
            $commentdata['comment_approved'] = sanitize_text_field($params['status']);
        }

        $result = wp_update_comment($commentdata);

        if (!$result) {
            return new \WP_Error('update_failed', 'Failed to update comment', ['status' => 500]);
        }

        return [
            'success' => true,
            'message' => 'Comment updated successfully',
        ];
    }

    public function delete_item($request) {
        $comment_id = $request->get_param('id');
        $force = $request->get_param('force') === 'true';

        $result = wp_delete_comment($comment_id, $force);

        if (!$result) {
            return new \WP_Error('delete_failed', 'Failed to delete comment', ['status' => 500]);
        }

        return [
            'success' => true,
            'message' => 'Comment deleted successfully',
        ];
    }

    public function approve_comment($request) {
        $comment_id = $request->get_param('id');
        $result = wp_set_comment_status($comment_id, 'approve');

        if (!$result) {
            return new \WP_Error('approve_failed', 'Failed to approve comment', ['status' => 500]);
        }

        return [
            'success' => true,
            'message' => 'Comment approved',
        ];
    }

    public function spam_comment($request) {
        $comment_id = $request->get_param('id');
        $result = wp_spam_comment($comment_id);

        if (!$result) {
            return new \WP_Error('spam_failed', 'Failed to mark as spam', ['status' => 500]);
        }

        return [
            'success' => true,
            'message' => 'Comment marked as spam',
        ];
    }

    public function trash_comment($request) {
        $comment_id = $request->get_param('id');
        $result = wp_trash_comment($comment_id);

        if (!$result) {
            return new \WP_Error('trash_failed', 'Failed to trash comment', ['status' => 500]);
        }

        return [
            'success' => true,
            'message' => 'Comment moved to trash',
        ];
    }

    public function reply_comment($request) {
        $comment_id = $request->get_param('id');
        $params = $request->get_json_params();

        $parent_comment = get_comment($comment_id);
        if (!$parent_comment) {
            return new \WP_Error('comment_not_found', 'Parent comment not found', ['status' => 404]);
        }

        $current_user = wp_get_current_user();

        $commentdata = [
            'comment_post_ID' => $parent_comment->comment_post_ID,
            'comment_author' => $current_user->display_name,
            'comment_author_email' => $current_user->user_email,
            'comment_content' => wp_kses_post($params['content'] ?? ''),
            'comment_parent' => $comment_id,
            'user_id' => $current_user->ID,
            'comment_approved' => 1,
        ];

        $new_comment_id = wp_insert_comment($commentdata);

        if (!$new_comment_id) {
            return new \WP_Error('reply_failed', 'Failed to create reply', ['status' => 500]);
        }

        return [
            'success' => true,
            'comment_id' => $new_comment_id,
            'message' => 'Reply added successfully',
        ];
    }

    public function bulk_action($request) {
        $params = $request->get_json_params();
        $action = $params['action'] ?? '';
        $comment_ids = $params['comment_ids'] ?? [];

        if (empty($action) || empty($comment_ids)) {
            return new \WP_Error('invalid_params', 'Action and comment IDs are required', ['status' => 400]);
        }

        $success_count = 0;
        $failed_count = 0;

        foreach ($comment_ids as $comment_id) {
            $result = false;

            switch ($action) {
                case 'approve':
                    $result = wp_set_comment_status($comment_id, 'approve');
                    break;
                case 'spam':
                    $result = wp_spam_comment($comment_id);
                    break;
                case 'trash':
                    $result = wp_trash_comment($comment_id);
                    break;
                case 'delete':
                    $result = wp_delete_comment($comment_id, true);
                    break;
            }

            if ($result) {
                $success_count++;
            } else {
                $failed_count++;
            }
        }

        return [
            'success' => true,
            'processed' => $success_count,
            'failed' => $failed_count,
            'message' => sprintf('%d comments processed, %d failed', $success_count, $failed_count),
        ];
    }

    private function format_comment($comment, $detailed = false) {
        $post = get_post($comment->comment_post_ID);

        $data = [
            'id' => $comment->comment_ID,
            'post_id' => $comment->comment_post_ID,
            'post_title' => $post ? $post->post_title : '',
            'author_name' => $comment->comment_author,
            'author_email' => $comment->comment_author_email,
            'content' => $comment->comment_content,
            'date' => $comment->comment_date,
            'status' => wp_get_comment_status($comment->comment_ID),
            'parent' => $comment->comment_parent,
        ];

        if ($detailed) {
            $data['author_url'] = $comment->comment_author_url;
            $data['author_ip'] = $comment->comment_author_IP;
            $data['user_agent'] = $comment->comment_agent;
        }

        return $data;
    }
}
