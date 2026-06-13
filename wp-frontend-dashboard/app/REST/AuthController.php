<?php
namespace WPFD\REST;

use WP_REST_Request;

class AuthController {
    public function register() {
        register_rest_route('wpfd/v1', '/login', [
            'methods' => 'POST',
            'callback' => [$this, 'login'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('wpfd/v1', '/register', [
            'methods' => 'POST',
            'callback' => [$this, 'createUser'],
            'permission_callback' => [$this, 'register_permission'],
        ]);

        register_rest_route('wpfd/v1', '/me', [
            'methods' => 'GET',
            'callback' => fn() => wp_get_current_user(),
            'permission_callback' => fn() => is_user_logged_in(),
        ]);
    }

    public function register_permission() {
        if (is_multisite()) {
            return is_user_logged_in() && (is_super_admin() || current_user_can('create_users'));
        }

        return (bool) get_option('users_can_register');
    }

    public function login(WP_REST_Request $req) {
        $user = wp_signon([
            'user_login' => $req['username'],
            'user_password' => $req['password'],
            'remember' => true,
        ]);

        if (is_wp_error($user)) {
            return new \WP_Error('login_failed', 'Invalid credentials', ['status' => 401]);
        }

        // Multisite: Ensure user is a member of THIS blog and is an administrator
        if (is_multisite()) {
            if (!is_user_member_of_blog($user->ID, get_current_blog_id())) {
                wp_logout();
                return new \WP_Error('access_denied', 'You do not have access to this site', ['status' => 403]);
            }
            
            // Check if user is Super Admin or has admin role for THIS blog
            if (!is_super_admin($user->ID) && !user_can($user->ID, 'manage_options')) {
                wp_logout();
                return new \WP_Error(
                    'insufficient_permissions',
                    'Only site administrators can access the frontend dashboard',
                    ['status' => 403]
                );
            }
        } else {
            // Single site: Check if user is administrator
            if (!user_can($user->ID, 'manage_options')) {
                wp_logout();
                return new \WP_Error(
                    'insufficient_permissions',
                    'Only administrators can access the frontend dashboard',
                    ['status' => 403]
                );
            }
        }

        return [
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email
        ];
    }

    public function createUser(WP_REST_Request $req) {
        $username = $req['username'];
        $password = $req['password'];
        $email = $req['email'];

        if (username_exists($username) || email_exists($email)) {
            return new \WP_Error('registration_failed', 'User already exists', ['status' => 400]);
        }

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        $default_role = get_option('default_role', 'subscriber');

        if (!get_role($default_role)) {
            $default_role = 'subscriber';
        }

        // For multisite, enforce blog membership with a non-privileged default role.
        if (is_multisite()) {
            add_user_to_blog(get_current_blog_id(), $user_id, $default_role);
        } else {
            $user = new \WP_User($user_id);
            $user->set_role($default_role);
        }

        // Return user data after creation
        $user = get_user_by('id', $user_id);
        return [
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email
        ];
    }
}
