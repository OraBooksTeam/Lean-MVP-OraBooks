<?php
namespace WPFD\REST;

class SiteHealthController {
    public function register() {
        $namespace = 'wpfd/v1';
        $base = '/site-health';

        // Get site health info
        register_rest_route($namespace, $base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_health_info'],
            'permission_callback' => [$this, 'admin_permission'],
        ]);

        // Get system info
        register_rest_route($namespace, $base . '/system', [
            'methods' => 'GET',
            'callback' => [$this, 'get_system_info'],
            'permission_callback' => [$this, 'admin_permission'],
        ]);

        // Get site settings (general, reading, discussion, permalinks)
        register_rest_route($namespace, '/site-settings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_site_settings'],
            'permission_callback' => [$this, 'admin_permission'],
        ]);

        // Update site settings
        register_rest_route($namespace, '/site-settings', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_site_settings'],
            'permission_callback' => [$this, 'admin_permission'],
        ]);
    }

    public function admin_permission() {
        return current_user_can('manage_options');
    }

    public function get_health_info($request) {
        global $wpdb;

        // Basic site info
        $site_info = [
            'site_title' => get_bloginfo('name'),
            'site_url' => get_site_url(),
            'admin_email' => get_option('admin_email'),
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'mysql_version' => $wpdb->db_version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'is_multisite' => is_multisite(),
            'active_theme' => wp_get_theme()->get('Name'),
            'active_plugins_count' => count(get_option('active_plugins', [])),
        ];

        // Memory info
        $memory_limit = ini_get('memory_limit');
        $memory_usage = function_exists('memory_get_usage') ? round(memory_get_usage() / 1024 / 1024, 2) : 0;

        // Disk space
        $upload_dir = wp_upload_dir();
        $disk_free = function_exists('disk_free_space') ? disk_free_space($upload_dir['basedir']) : 0;
        $disk_total = function_exists('disk_total_space') ? disk_total_space($upload_dir['basedir']) : 0;

        $site_info['memory_limit'] = $memory_limit;
        $site_info['memory_usage'] = $memory_usage . ' MB';
        $site_info['disk_free'] = $disk_free ? round($disk_free / 1024 / 1024 / 1024, 2) . ' GB' : 'Unknown';
        $site_info['disk_total'] = $disk_total ? round($disk_total / 1024 / 1024 / 1024, 2) . ' GB' : 'Unknown';

        // Check for updates
        $updates = [
            'wordpress' => $this->check_wordpress_updates(),
            'plugins' => $this->check_plugin_updates(),
            'themes' => $this->check_theme_updates(),
        ];

        return [
            'site_info' => $site_info,
            'updates' => $updates,
        ];
    }

    public function get_system_info($request) {
        global $wpdb;

        $info = [
            'environment' => [
                'WordPress Version' => get_bloginfo('version'),
                'PHP Version' => phpversion(),
                'MySQL Version' => $wpdb->db_version(),
                'Web Server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'User Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'Memory Limit' => ini_get('memory_limit'),
                'Max Upload Size' => size_format(wp_max_upload_size()),
                'Post Max Size' => ini_get('post_max_size'),
                'Max Execution Time' => ini_get('max_execution_time') . 's',
                'HTTPS' => is_ssl() ? 'Yes' : 'No',
            ],
            'database' => [
                'Database Charset' => DB_CHARSET,
                'Database Collation' => defined('DB_COLLATE') && DB_COLLATE ? DB_COLLATE : 'Not set',
                'Multisite' => is_multisite() ? 'Yes' : 'No',
            ],
            'wordpress' => [
                'Home URL' => get_home_url(),
                'Site URL' => get_site_url(),
                'Multisite' => is_multisite() ? 'Yes' : 'No',
                'Debug Mode' => defined('WP_DEBUG') && WP_DEBUG ? 'Yes' : 'No',
            ],
        ];

        return $info;
    }

    public function get_site_settings($request) {
        $timezone_value = get_option('timezone_string');
        if (empty($timezone_value)) {
            $timezone_value = (string) get_option('gmt_offset');
        }

        $settings = [
            'general' => [
                'site_title' => get_option('blogname'),
                'tagline' => get_option('blogdescription'),
                'wordpress_address' => get_option('siteurl'),
                'site_address' => get_option('home'),
                'admin_email' => get_option('admin_email'),
                'timezone' => $timezone_value,
                'date_format' => get_option('date_format'),
                'time_format' => get_option('time_format'),
                'start_of_week' => get_option('start_of_week'),
                'users_can_register' => (int) get_option('users_can_register'),
                'default_role' => get_option('default_role'),
                'site_language' => get_locale(),
                'timezone_options' => timezone_identifiers_list(),
                'date_format_options' => [
                    'F j, Y',
                    'Y-m-d',
                    'm/d/Y',
                    'd/m/Y',
                ],
                'time_format_options' => [
                    'g:i a',
                    'g:i A',
                    'H:i',
                ],
                'week_starts_options' => [
                    ['value' => 0, 'label' => 'Sunday'],
                    ['value' => 1, 'label' => 'Monday'],
                    ['value' => 2, 'label' => 'Tuesday'],
                    ['value' => 3, 'label' => 'Wednesday'],
                    ['value' => 4, 'label' => 'Thursday'],
                    ['value' => 5, 'label' => 'Friday'],
                    ['value' => 6, 'label' => 'Saturday'],
                ],
                'role_options' => $this->get_role_options(),
                'language_options' => $this->get_language_options(),
            ],
            'reading' => [
                'show_on_front' => get_option('show_on_front'),
                'page_on_front' => get_option('page_on_front'),
                'page_for_posts' => get_option('page_for_posts'),
                'posts_per_page' => get_option('posts_per_page'),
                'posts_per_rss' => get_option('posts_per_rss'),
                'rss_use_excerpt' => get_option('rss_use_excerpt'),
                'blog_public' => get_option('blog_public'),
                'page_options' => $this->get_page_options(),
            ],
            'media' => [
                'thumbnail_size_w' => (int) get_option('thumbnail_size_w'),
                'thumbnail_size_h' => (int) get_option('thumbnail_size_h'),
                'thumbnail_crop' => (int) get_option('thumbnail_crop'),
                'medium_size_w' => (int) get_option('medium_size_w'),
                'medium_size_h' => (int) get_option('medium_size_h'),
                'large_size_w' => (int) get_option('large_size_w'),
                'large_size_h' => (int) get_option('large_size_h'),
                'uploads_use_yearmonth_folders' => (int) get_option('uploads_use_yearmonth_folders'),
            ],
            'writing' => [
                'default_category' => (int) get_option('default_category'),
                'default_post_format' => get_option('default_post_format') ?: '0',
                'use_smilies' => (int) get_option('use_smilies'),
                'use_balanceTags' => (int) get_option('use_balanceTags'),
                'mailserver_url' => get_option('mailserver_url'),
                'mailserver_port' => get_option('mailserver_port'),
                'default_email_category' => (int) get_option('default_email_category'),
                'ping_sites' => get_option('ping_sites'),
                'category_options' => $this->get_category_options(),
                'post_format_options' => $this->get_post_format_options(),
            ],
            'discussion' => [
                'default_comment_status' => get_option('default_comment_status'),
                'default_ping_status' => get_option('default_ping_status'),
                'comment_registration' => get_option('comment_registration'),
                'close_comments_for_old_posts' => get_option('close_comments_for_old_posts'),
                'close_comments_days_old' => get_option('close_comments_days_old'),
                'thread_comments' => get_option('thread_comments'),
                'thread_comments_depth' => get_option('thread_comments_depth'),
                'page_comments' => get_option('page_comments'),
                'comments_per_page' => get_option('comments_per_page'),
                'comment_moderation' => get_option('comment_moderation'),
                'comment_previously_approved' => get_option('comment_previously_approved'),
            ],
            'permalinks' => [
                'permalink_structure' => get_option('permalink_structure'),
                'category_base' => get_option('category_base'),
                'tag_base' => get_option('tag_base'),
            ],
        ];

        return $settings;
    }

    public function update_site_settings($request) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error('forbidden', 'You do not have permission to update settings', ['status' => 403]);
        }

        $params = $request->get_json_params();

        // Update general settings
        if (isset($params['general'])) {
            $general = $params['general'];
            if (isset($general['site_title'])) {
                update_option('blogname', sanitize_text_field($general['site_title']));
            }
            if (isset($general['tagline'])) {
                update_option('blogdescription', sanitize_text_field($general['tagline']));
            }
            if (isset($general['wordpress_address'])) {
                update_option('siteurl', esc_url_raw($general['wordpress_address']));
            }
            if (isset($general['site_address'])) {
                update_option('home', esc_url_raw($general['site_address']));
            }
            if (isset($general['admin_email'])) {
                update_option('admin_email', sanitize_email($general['admin_email']));
            }
            if (isset($general['timezone'])) {
                $timezone = sanitize_text_field($general['timezone']);
                if (is_numeric($timezone)) {
                    update_option('timezone_string', '');
                    update_option('gmt_offset', (float) $timezone);
                } else {
                    update_option('timezone_string', $timezone);
                }
            }
            if (isset($general['date_format'])) {
                update_option('date_format', sanitize_text_field($general['date_format']));
            }
            if (isset($general['time_format'])) {
                update_option('time_format', sanitize_text_field($general['time_format']));
            }
            if (isset($general['start_of_week'])) {
                $start_of_week = absint($general['start_of_week']);
                update_option('start_of_week', min(6, max(0, $start_of_week)));
            }
            if (isset($general['users_can_register'])) {
                update_option('users_can_register', absint($general['users_can_register']) ? 1 : 0);
            }
            if (isset($general['default_role'])) {
                $role = sanitize_key($general['default_role']);
                $editable_roles = get_editable_roles();
                if (isset($editable_roles[$role])) {
                    update_option('default_role', $role);
                }
            }
            if (isset($general['site_language'])) {
                update_option('WPLANG', sanitize_text_field($general['site_language']));
            }
        }

        // Update reading settings
        if (isset($params['reading'])) {
            $reading = $params['reading'];
            if (isset($reading['show_on_front'])) {
                update_option('show_on_front', sanitize_text_field($reading['show_on_front']));
            }
            if (isset($reading['page_on_front'])) {
                update_option('page_on_front', absint($reading['page_on_front']));
            }
            if (isset($reading['page_for_posts'])) {
                update_option('page_for_posts', absint($reading['page_for_posts']));
            }
            if (isset($reading['posts_per_page'])) {
                update_option('posts_per_page', absint($reading['posts_per_page']));
            }
            if (isset($reading['posts_per_rss'])) {
                update_option('posts_per_rss', absint($reading['posts_per_rss']));
            }
            if (isset($reading['rss_use_excerpt'])) {
                update_option('rss_use_excerpt', absint($reading['rss_use_excerpt']) ? 1 : 0);
            }
            if (isset($reading['blog_public'])) {
                update_option('blog_public', absint($reading['blog_public']));
            }
        }

        // Update media settings
        if (isset($params['media'])) {
            $media = $params['media'];
            if (isset($media['thumbnail_size_w'])) {
                update_option('thumbnail_size_w', absint($media['thumbnail_size_w']));
            }
            if (isset($media['thumbnail_size_h'])) {
                update_option('thumbnail_size_h', absint($media['thumbnail_size_h']));
            }
            if (isset($media['thumbnail_crop'])) {
                update_option('thumbnail_crop', absint($media['thumbnail_crop']) ? 1 : 0);
            }
            if (isset($media['medium_size_w'])) {
                update_option('medium_size_w', absint($media['medium_size_w']));
            }
            if (isset($media['medium_size_h'])) {
                update_option('medium_size_h', absint($media['medium_size_h']));
            }
            if (isset($media['large_size_w'])) {
                update_option('large_size_w', absint($media['large_size_w']));
            }
            if (isset($media['large_size_h'])) {
                update_option('large_size_h', absint($media['large_size_h']));
            }
            if (isset($media['uploads_use_yearmonth_folders'])) {
                update_option('uploads_use_yearmonth_folders', absint($media['uploads_use_yearmonth_folders']) ? 1 : 0);
            }
        }

        // Update writing settings
        if (isset($params['writing'])) {
            $writing = $params['writing'];
            if (isset($writing['default_category'])) {
                update_option('default_category', absint($writing['default_category']));
            }
            if (isset($writing['default_post_format'])) {
                update_option('default_post_format', sanitize_text_field($writing['default_post_format']));
            }
            if (isset($writing['use_smilies'])) {
                update_option('use_smilies', absint($writing['use_smilies']) ? 1 : 0);
            }
            if (isset($writing['use_balanceTags'])) {
                update_option('use_balanceTags', absint($writing['use_balanceTags']) ? 1 : 0);
            }
            if (isset($writing['mailserver_url'])) {
                update_option('mailserver_url', sanitize_text_field($writing['mailserver_url']));
            }
            if (isset($writing['mailserver_login'])) {
                update_option('mailserver_login', sanitize_text_field($writing['mailserver_login']));
            }
            if (isset($writing['mailserver_pass'])) {
                update_option('mailserver_pass', sanitize_text_field($writing['mailserver_pass']));
            }
            if (isset($writing['mailserver_port'])) {
                update_option('mailserver_port', absint($writing['mailserver_port']));
            }
            if (isset($writing['default_email_category'])) {
                update_option('default_email_category', absint($writing['default_email_category']));
            }
            if (isset($writing['ping_sites'])) {
                update_option('ping_sites', sanitize_textarea_field($writing['ping_sites']));
            }
        }

        // Update discussion settings
        if (isset($params['discussion'])) {
            $discussion = $params['discussion'];
            if (isset($discussion['default_comment_status'])) {
                update_option('default_comment_status', sanitize_text_field($discussion['default_comment_status']));
            }
            if (isset($discussion['default_ping_status'])) {
                update_option('default_ping_status', sanitize_text_field($discussion['default_ping_status']));
            }
            if (isset($discussion['comment_registration'])) {
                update_option('comment_registration', absint($discussion['comment_registration']));
            }
            if (isset($discussion['close_comments_for_old_posts'])) {
                update_option('close_comments_for_old_posts', absint($discussion['close_comments_for_old_posts']));
            }
            if (isset($discussion['close_comments_days_old'])) {
                update_option('close_comments_days_old', absint($discussion['close_comments_days_old']));
            }
            if (isset($discussion['thread_comments'])) {
                update_option('thread_comments', absint($discussion['thread_comments']));
            }
            if (isset($discussion['thread_comments_depth'])) {
                update_option('thread_comments_depth', absint($discussion['thread_comments_depth']));
            }
            if (isset($discussion['page_comments'])) {
                update_option('page_comments', absint($discussion['page_comments']));
            }
            if (isset($discussion['comments_per_page'])) {
                update_option('comments_per_page', absint($discussion['comments_per_page']));
            }
            if (isset($discussion['comment_moderation'])) {
                update_option('comment_moderation', absint($discussion['comment_moderation']));
            }
            if (isset($discussion['comment_previously_approved'])) {
                update_option('comment_previously_approved', absint($discussion['comment_previously_approved']));
            }
        }

        // Update permalink settings
        if (isset($params['permalinks'])) {
            $permalinks = $params['permalinks'];
            if (isset($permalinks['permalink_structure'])) {
                update_option('permalink_structure', sanitize_text_field($permalinks['permalink_structure']));
                flush_rewrite_rules();
            }
            if (isset($permalinks['category_base'])) {
                update_option('category_base', sanitize_text_field($permalinks['category_base']));
            }
            if (isset($permalinks['tag_base'])) {
                update_option('tag_base', sanitize_text_field($permalinks['tag_base']));
            }
        }

        return [
            'success' => true,
            'message' => 'Settings updated successfully',
        ];
    }

    private function get_role_options() {
        $roles = [];
        foreach (get_editable_roles() as $role_key => $role_data) {
            $roles[] = [
                'value' => $role_key,
                'label' => isset($role_data['name']) ? translate_user_role($role_data['name']) : $role_key,
            ];
        }

        return $roles;
    }

    private function get_language_options() {
        $languages = get_available_languages();
        $current_locale = get_locale();

        if (!in_array($current_locale, $languages, true)) {
            $languages[] = $current_locale;
        }
        if (!in_array('en_US', $languages, true)) {
            $languages[] = 'en_US';
        }

        sort($languages);

        return array_map(function($locale) {
            return [
                'value' => $locale,
                'label' => $locale,
            ];
        }, $languages);
    }

    private function get_category_options() {
        $categories = get_categories([
            'hide_empty' => false,
        ]);

        $options = [];
        foreach ($categories as $category) {
            $options[] = [
                'value' => (int) $category->term_id,
                'label' => $category->name,
            ];
        }

        return $options;
    }

    private function get_post_format_options() {
        $options = [
            [
                'value' => '0',
                'label' => 'Standard',
            ],
        ];

        if (function_exists('get_post_format_strings')) {
            $formats = get_post_format_strings();
            foreach ($formats as $format_key => $format_label) {
                $options[] = [
                    'value' => (string) $format_key,
                    'label' => (string) $format_label,
                ];
            }
        }

        return $options;
    }

    private function get_page_options() {
        $pages = get_pages([
            'sort_column' => 'post_title',
            'sort_order' => 'ASC',
        ]);

        $options = [
            ['value' => 0, 'label' => '- Select -']
        ];

        foreach ($pages as $page) {
            $options[] = [
                'value' => $page->ID,
                'label' => $page->post_title,
            ];
        }

        return $options;
    }

    private function check_wordpress_updates() {
        $updates = get_core_updates();
        if (empty($updates) || !is_array($updates)) {
            return null;
        }

        $update = $updates[0];
        if ($update->response == 'latest') {
            return null;
        }

        return [
            'current_version' => get_bloginfo('version'),
            'new_version' => $update->version ?? 'Unknown',
            'available' => true,
        ];
    }

    private function check_plugin_updates() {
        $updates = get_plugin_updates();
        return count($updates);
    }

    private function check_theme_updates() {
        $updates = get_theme_updates();
        return count($updates);
    }
}
