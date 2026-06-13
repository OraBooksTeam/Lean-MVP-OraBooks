<?php
namespace WPFD\REST;

class UserController {
    public function register() {
        $namespace = 'wpfd/v1';
        $base = '/users';

        // List users
        register_rest_route($namespace, $base, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'admin_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'admin_permission'],
            ],
        ]);

        // Single user operations
        register_rest_route($namespace, $base . '/(?P<id>[\d]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'admin_permission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'admin_permission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_item'],
                'permission_callback' => [$this, 'admin_permission'],
            ],
        ]);

        // Get available roles
        register_rest_route($namespace, $base . '/roles', [
            'methods' => 'GET',
            'callback' => [$this, 'get_roles'],
            'permission_callback' => [$this, 'admin_permission'],
        ]);

        // Current user profile
        register_rest_route($namespace, $base . '/me', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_current_user_profile'],
                'permission_callback' => [$this, 'profile_permission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_current_user_profile'],
                'permission_callback' => [$this, 'profile_permission'],
            ],
        ]);

        // Avatar upload endpoint
        register_rest_route($namespace, $base . '/me/avatar', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'upload_avatar'],
                'permission_callback' => [$this, 'profile_permission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'remove_avatar'],
                'permission_callback' => [$this, 'profile_permission'],
            ],
        ]);
    }

    public function admin_permission() {
        return current_user_can('list_users') || current_user_can('edit_users');
    }

    public function profile_permission() {
        if (!is_user_logged_in()) {
            return false;
        }

        if (is_multisite() && !is_user_member_of_blog(get_current_user_id(), get_current_blog_id()) && !is_super_admin()) {
            return false;
        }

        return true;
    }

    public function get_items($request) {
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 20;
        $role = $request->get_param('role') ?: '';
        $search = $request->get_param('search') ?: '';

        $args = [
            'number' => $per_page,
            'paged' => $page,
            'orderby' => 'registered',
            'order' => 'DESC',
        ];

        // In multisite, get users for current blog only
        if (is_multisite()) {
            $args['blog_id'] = get_current_blog_id();
        }

        if ($role) {
            $args['role'] = $role;
        }

        if ($search) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'user_nicename', 'display_name'];
        }

        $user_query = new \WP_User_Query($args);
        $users = $user_query->get_results();
        $total = $user_query->get_total();

        return [
            'items' => array_map([$this, 'format_user'], $users),
            'total' => $total,
            'total_pages' => ceil($total / $per_page),
        ];
    }

    public function get_item($request) {
        $user_id = $request->get_param('id');
        $user = get_userdata($user_id);

        if (!$user) {
            return new \WP_Error('user_not_found', 'User not found', ['status' => 404]);
        }

        return $this->format_user($user, true);
    }

    public function create_item($request) {
        if (!current_user_can('create_users')) {
            return new \WP_Error('forbidden', 'You do not have permission to create users', ['status' => 403]);
        }

        $params = $request->get_json_params();
        
        $userdata = [
            'user_login' => sanitize_user($params['username'] ?? ''),
            'user_email' => sanitize_email($params['email'] ?? ''),
            'user_pass' => $params['password'] ?? wp_generate_password(),
            'display_name' => sanitize_text_field($params['display_name'] ?? ''),
            'first_name' => sanitize_text_field($params['first_name'] ?? ''),
            'last_name' => sanitize_text_field($params['last_name'] ?? ''),
            'role' => sanitize_text_field($params['role'] ?? 'subscriber'),
        ];

        // Validate required fields
        if (empty($userdata['user_login']) || empty($userdata['user_email'])) {
            return new \WP_Error('missing_fields', 'Username and email are required', ['status' => 400]);
        }

        $user_id = wp_insert_user($userdata);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // In multisite, add user to current blog
        if (is_multisite()) {
            add_user_to_blog(get_current_blog_id(), $user_id, $userdata['role']);
        }

        return [
            'success' => true,
            'user_id' => $user_id,
            'message' => 'User created successfully',
        ];
    }

    public function update_item($request) {
        $user_id = $request->get_param('id');
        
        if (!current_user_can('edit_user', $user_id)) {
            return new \WP_Error('forbidden', 'You do not have permission to edit this user', ['status' => 403]);
        }

        $params = $request->get_json_params();
        
        $userdata = ['ID' => $user_id];

        if (isset($params['email'])) {
            $userdata['user_email'] = sanitize_email($params['email']);
        }
        if (isset($params['display_name'])) {
            $userdata['display_name'] = sanitize_text_field($params['display_name']);
        }
        if (isset($params['first_name'])) {
            $userdata['first_name'] = sanitize_text_field($params['first_name']);
        }
        if (isset($params['last_name'])) {
            $userdata['last_name'] = sanitize_text_field($params['last_name']);
        }
        if (isset($params['password']) && !empty($params['password'])) {
            $userdata['user_pass'] = $params['password'];
        }
        if (isset($params['role'])) {
            $userdata['role'] = sanitize_text_field($params['role']);
        }

        $result = wp_update_user($userdata);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'success' => true,
            'message' => 'User updated successfully',
        ];
    }

    public function delete_item($request) {
        $user_id = $request->get_param('id');
        
        if (!current_user_can('delete_users')) {
            return new \WP_Error('forbidden', 'You do not have permission to delete users', ['status' => 403]);
        }

        // Don't allow deleting yourself
        if ($user_id == get_current_user_id()) {
            return new \WP_Error('forbidden', 'You cannot delete yourself', ['status' => 400]);
        }

        // In multisite, remove from blog instead of deleting
        if (is_multisite()) {
            $removed = remove_user_from_blog($user_id, get_current_blog_id());
            if (!$removed) {
                return new \WP_Error('delete_failed', 'Failed to remove user from site', ['status' => 500]);
            }
        } else {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            $deleted = wp_delete_user($user_id);
            if (!$deleted) {
                return new \WP_Error('delete_failed', 'Failed to delete user', ['status' => 500]);
            }
        }

        return [
            'success' => true,
            'message' => is_multisite() ? 'User removed from site' : 'User deleted successfully',
        ];
    }

    public function get_roles() {
        if (!function_exists('get_editable_roles')) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
        }

        $roles = get_editable_roles();
        $formatted_roles = [];

        foreach ($roles as $role_key => $role_info) {
            $formatted_roles[] = [
                'value' => $role_key,
                'label' => $role_info['name'],
            ];
        }

        return $formatted_roles;
    }

    public function get_current_user_profile() {
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        if (!$user) {
            return new \WP_Error('user_not_found', 'Current user not found', ['status' => 404]);
        }

        if (!current_user_can('read')) {
            return new \WP_Error('forbidden', 'You do not have permission to view this profile', ['status' => 403]);
        }

        return $this->format_user($user, true);
    }

    public function update_current_user_profile($request) {
        $user_id = get_current_user_id();

        if (!current_user_can('edit_user', $user_id)) {
            return new \WP_Error('forbidden', 'You do not have permission to update this profile', ['status' => 403]);
        }

        $params = $request->get_json_params();
        $userdata = ['ID' => $user_id];

        if (isset($params['email'])) {
            $userdata['user_email'] = sanitize_email($params['email']);
        }
        if (isset($params['display_name'])) {
            $userdata['display_name'] = sanitize_text_field($params['display_name']);
        }
        if (isset($params['first_name'])) {
            $userdata['first_name'] = sanitize_text_field($params['first_name']);
        }
        if (isset($params['last_name'])) {
            $userdata['last_name'] = sanitize_text_field($params['last_name']);
        }
        if (isset($params['password']) && !empty($params['password'])) {
            $userdata['user_pass'] = $params['password'];
        }

        $result = wp_update_user($userdata);
        if (is_wp_error($result)) {
            return $result;
        }

        // Update custom meta fields
        if (isset($params['description'])) {
            update_user_meta($user_id, 'description', sanitize_textarea_field($params['description']));
        }
        if (isset($params['phone'])) {
            update_user_meta($user_id, 'phone', sanitize_text_field($params['phone']));
        }
        if (isset($params['designation'])) {
            update_user_meta($user_id, 'designation', sanitize_text_field($params['designation']));
        }
        if (isset($params['department'])) {
            update_user_meta($user_id, 'department', sanitize_text_field($params['department']));
        }

        $updated_user = get_userdata($user_id);

        return [
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => $this->format_user($updated_user, true),
        ];
    }

    public function upload_avatar($request) {
        $user_id = get_current_user_id();

        if (!current_user_can('edit_user', $user_id)) {
            return new \WP_Error('forbidden', 'You do not have permission to update this profile', ['status' => 403]);
        }

        if (empty($_FILES['file'])) {
            return new \WP_Error('no_file', 'No file uploaded', ['status' => 400]);
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Upload the file
        $attachment_id = media_handle_upload('file', 0);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Delete old avatar if exists
        $old_avatar_id = get_user_meta($user_id, 'wpfd_avatar_id', true);
        if ($old_avatar_id && $old_avatar_id != $attachment_id) {
            wp_delete_attachment($old_avatar_id, true);
        }

        // Save new avatar attachment ID
        update_user_meta($user_id, 'wpfd_avatar_id', $attachment_id);

        $avatar_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');

        return [
            'success' => true,
            'message' => 'Avatar uploaded successfully',
            'avatar_id' => $attachment_id,
            'avatar_url' => $avatar_url,
        ];
    }

    public function remove_avatar($request) {
        $user_id = get_current_user_id();

        if (!current_user_can('edit_user', $user_id)) {
            return new \WP_Error('forbidden', 'You do not have permission to update this profile', ['status' => 403]);
        }

        $avatar_id = get_user_meta($user_id, 'wpfd_avatar_id', true);

        if ($avatar_id) {
            wp_delete_attachment($avatar_id, true);
            delete_user_meta($user_id, 'wpfd_avatar_id');
        }

        return [
            'success' => true,
            'message' => 'Avatar removed successfully',
        ];
    }

    private function format_user($user, $detailed = false) {
        $data = [
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'roles' => $user->roles,
            'registered' => $user->user_registered,
        ];

        if ($detailed) {
            $data['first_name'] = get_user_meta($user->ID, 'first_name', true);
            $data['last_name'] = get_user_meta($user->ID, 'last_name', true);
            $data['description'] = get_user_meta($user->ID, 'description', true);
            $data['phone'] = get_user_meta($user->ID, 'phone', true);
            $data['designation'] = get_user_meta($user->ID, 'designation', true);
            $data['department'] = get_user_meta($user->ID, 'department', true);
            
            $avatar_id = get_user_meta($user->ID, 'wpfd_avatar_id', true);
            $data['avatar_url'] = $avatar_id ? wp_get_attachment_image_url($avatar_id, 'thumbnail') : null;
        }

        return $data;
    }
}
