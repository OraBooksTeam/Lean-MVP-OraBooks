<?php

namespace WPFD\REST;

class SettingsController {
    public function register() {
        $namespace = 'wpfd/v1';
        $base = '/settings';

        register_rest_route($namespace, $base, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'check_logo_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_settings'],
                'permission_callback' => [$this, 'check_logo_permission'],
            ],
        ]);

        // Logo upload endpoint
        register_rest_route($namespace, $base . '/logo', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'upload_logo'],
                'permission_callback' => [$this, 'check_logo_permission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'remove_logo'],
                'permission_callback' => [$this, 'check_logo_permission'],
            ],
        ]);

        // Icon upload endpoint
        register_rest_route($namespace, $base . '/icon', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'upload_icon'],
                'permission_callback' => [$this, 'check_logo_permission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'remove_icon'],
                'permission_callback' => [$this, 'check_logo_permission'],
            ],
        ]);

        // Debug endpoint for checking permissions
        register_rest_route($namespace, $base . '/permissions', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_permissions'],
                'permission_callback' => function() {
                    return is_user_logged_in();
                },
            ],
        ]);
    }

    /**
     * Check if user has permission to manage logos/icons
     * For multisite: Must be admin of CURRENT blog or Super Admin
     * For single site: Must have manage_options capability
     */
    public function check_logo_permission() {
        if (!is_user_logged_in()) {
            return new \WP_Error(
                'not_logged_in',
                'You must be logged in to manage branding',
                ['status' => 401]
            );
        }

        if (is_multisite()) {
            $current_user_id = get_current_user_id();
            $current_blog_id = get_current_blog_id();

            // Super Admins can manage any subsite
            if (is_super_admin($current_user_id)) {
                return true;
            }

            // Check if user is member of THIS blog
            if (!is_user_member_of_blog($current_user_id, $current_blog_id)) {
                return new \WP_Error(
                    'not_member',
                    'You are not a member of this site. You can only manage branding for sites where you are a member.',
                    ['status' => 403]
                );
            }

            // Check if user has manage_options for THIS blog
            if (!current_user_can('manage_options')) {
                return new \WP_Error(
                    'insufficient_permissions',
                    'You must be an administrator of this site to manage branding. Contact your site administrator for access.',
                    ['status' => 403]
                );
            }

            return true;
        }

        // Single site: just check manage_options
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'insufficient_permissions',
                'You must be an administrator to manage site branding.',
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Get current user permissions (for debugging and frontend use)
     */
    public function get_permissions() {
        if (!is_user_logged_in()) {
            return [
                'logged_in' => false,
                'can_manage_branding' => false,
                'message' => 'Not logged in',
            ];
        }

        $user = wp_get_current_user();
        $can_manage = $this->check_logo_permission();
        
        $response = [
            'logged_in' => true,
            'user_id' => get_current_user_id(),
            'user_login' => $user->user_login,
            'user_roles' => $user->roles,
            'can_manage_branding' => !is_wp_error($can_manage),
            'can_manage_options' => current_user_can('manage_options'),
        ];

        if (is_multisite()) {
            $response['is_multisite'] = true;
            $response['current_blog_id'] = get_current_blog_id();
            $response['is_super_admin'] = is_super_admin();
            $response['is_blog_member'] = is_user_member_of_blog(get_current_user_id(), get_current_blog_id());
        }

        if (is_wp_error($can_manage)) {
            $response['error'] = $can_manage->get_error_message();
        }

        return $response;
    }

    public function get_settings() {
        // Get logo ID
        $logo_id = get_theme_mod('custom_logo');
        
        // Get icon ID - multisite aware
        $icon_id = is_multisite() 
            ? get_blog_option(get_current_blog_id(), 'site_icon')
            : get_option('site_icon');

        // Check for Extra theme logo
        $current_theme = wp_get_theme();
        $extra_logo_url = null;
        if ($current_theme->get('Name') === 'Extra' || $current_theme->get_template() === 'Extra') {
            $extra_logo_url = et_get_option('extra_logo', '');
        }

        return [
            'site_title' => get_bloginfo('name'),
            'site_description' => get_bloginfo('description'),
            'logo' => $logo_id ? [
                'id' => $logo_id,
                'url' => wp_get_attachment_image_url($logo_id, 'full'),
            ] : ($extra_logo_url ? ['url' => $extra_logo_url] : null),
            'icon' => $icon_id ? [
                'id' => $icon_id,
                'url' => wp_get_attachment_image_url($icon_id, 'full'),
            ] : null,
        ];
    }

    public function update_settings($request) {
        if (isset($request['site_title'])) update_option('blogname', $request['site_title']);
        if (isset($request['site_description'])) update_option('blogdescription', $request['site_description']);
        if (isset($request['logo'])) set_theme_mod('custom_logo', $request['logo']);
        if (isset($request['icon'])) update_option('site_icon', $request['icon']);

        return ['message' => 'Settings updated successfully'];
    }

    public function upload_logo($request) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $files = $request->get_file_params();
        if (!isset($files['file'])) {
            return new \WP_Error('no_file', 'No file uploaded', ['status' => 400]);
        }

        $file = $files['file'];
        $upload_overrides = ['test_form' => false];
        
        $movefile = wp_handle_upload($file, $upload_overrides);

        if (isset($movefile['error'])) {
            return new \WP_Error('upload_error', $movefile['error'], ['status' => 500]);
        }

        $attachment = [
            'guid' => $movefile['url'],
            'post_mime_type' => $movefile['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($file['name'])),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attach_id = wp_insert_attachment($attachment, $movefile['file']);
        $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        // Standard WordPress logo
        set_theme_mod('custom_logo', $attach_id);

        // Extra theme compatibility: Save logo URL to Extra theme option
        $logo_url = wp_get_attachment_image_url($attach_id, 'full');
        $current_theme = wp_get_theme();
        if ($current_theme->get('Name') === 'Extra' || $current_theme->get_template() === 'Extra') {
            // Extra theme uses et_update_option which stores in wp_options
            // with the key format: et_{theme_name}
            et_update_option('extra_logo', $logo_url);
        }

        return [
            'success' => true,
            'id' => $attach_id,
            'url' => $logo_url,
        ];
    }

    public function upload_icon($request) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $files = $request->get_file_params();
        if (!isset($files['file'])) {
            return new \WP_Error('no_file', 'No file uploaded', ['status' => 400]);
        }

        $file = $files['file'];
        $upload_overrides = ['test_form' => false];
        
        $movefile = wp_handle_upload($file, $upload_overrides);

        if (isset($movefile['error'])) {
            return new \WP_Error('upload_error', $movefile['error'], ['status' => 500]);
        }

        $attachment = [
            'guid' => $movefile['url'],
            'post_mime_type' => $movefile['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($file['name'])),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attach_id = wp_insert_attachment($attachment, $movefile['file']);
        $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        // Save to site icon - use update_blog_option for multisite support
        if (is_multisite()) {
            update_blog_option(get_current_blog_id(), 'site_icon', $attach_id);
        } else {
            update_option('site_icon', $attach_id);
        }

        return [
            'success' => true,
            'id' => $attach_id,
            'url' => wp_get_attachment_image_url($attach_id, 'full'),
        ];
    }

    public function remove_logo($request) {
        remove_theme_mod('custom_logo');
        
        // Extra theme compatibility: Remove logo from Extra theme option
        $current_theme = wp_get_theme();
        if ($current_theme->get('Name') === 'Extra' || $current_theme->get_template() === 'Extra') {
            et_update_option('extra_logo', '');
        }
        
        return ['success' => true, 'message' => 'Logo removed'];
    }

    public function remove_icon($request) {
        if (is_multisite()) {
            delete_blog_option(get_current_blog_id(), 'site_icon');
        } else {
            delete_option('site_icon');
        }
        return ['success' => true, 'message' => 'Icon removed'];
    }
}
