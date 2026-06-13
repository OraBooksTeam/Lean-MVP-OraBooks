<?php
namespace WPFD\Core;

/**
 * Template Renderer - WordPress Native Implementation
 * Handles rendering of dashboard templates with data
 */

class TemplateRenderer {
    
    /**
     * Get dashboard overview data
     */
    public function get_dashboard_overview() {
        // Security: Validate blog ID
        $blog_id = get_current_blog_id();
        if ($blog_id <= 0) {
            return $this->get_empty_overview();
        }
        
        $cache_key = 'wpfd_dashboard_overview_' . $blog_id;
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }

        $data = [
            'stats' => [
                'posts' => $this->count_posts(),
                'pages' => $this->count_pages(),
                'media' => $this->count_media(),
                'users' => $this->count_users(),
            ],
            'recent_posts' => $this->get_recent_posts(),
        ];

        // Cache for 5 minutes
        set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);
        
        return $data;
    }

    /**
     * Get addon features data
     */
    public function get_addon_features() {
        $cache_key = 'wpfd_addon_features_' . get_current_blog_id();
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }

        // Get features from the DashboardFeaturesController
        try {
            $controller = new \WPFD\REST\DashboardFeaturesController();
            $request = new \WP_REST_Request();
            $response = $controller->get_features($request);

            if ($response instanceof \WP_REST_Response) {
                $data = $response->get_data();
                $features = $data['features'] ?? [];
            } else {
                $features = $this->get_default_features();
            }
        } catch (Exception $e) {
            // Fallback to default features
            $features = $this->get_default_features();
        }

        $data = [
            'features' => $features,
            'total_count' => count($features),
        ];

        // Cache for 15 minutes
        set_transient($cache_key, $data, 15 * MINUTE_IN_SECONDS);
        
        return $data;
    }
    
    /**
     * Render feature icon
     */
    public function render_feature_icon($icon_name = 'package') {
        // Security: Sanitize icon name
        $icon_name = sanitize_text_field($icon_name);
        
        $icons = [
            'package' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>',
            'settings' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M12 1v6m0 6v6m4.22-13.22l4.24 4.24M1.54 1.54l4.24 4.24M20.46 20.46l-4.24-4.24M1.54 20.46l4.24-4.24"></path></svg>',
            'users' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
            'filetext' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14,2 14,8 20,8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10,9 9,9 8,9"></polyline></svg>',
            'calculator' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="9" y1="6" x2="15" y2="6"></line><line x1="9" y1="10" x2="15" y2="10"></line><line x1="9" y1="14" x2="15" y2="14"></line><line x1="9" y1="18" x2="15" y2="18"></line></svg>',
            'database' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>',
            'globe' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>',
            'shield' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
            'zap' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13,2 3,14 12,14 11,22 21,10 12,10 13,2"></polygon></svg>',
            'trendingup' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23,6 13.5,15.5 8.5,10.5 1,18"></polyline><polyline points="17,6 23,6 23,12"></polyline></svg>',
            'archive' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8"></path><path d="M1 3h22v5H1z"></path><line x1="10" y1="12" x2="14" y2="12"></line></svg>',
        ];
        
        // Output the icon SVG directly (hardcoded SVGs are safe, wp_kses_post strips SVG tags)
        echo $icons[$icon_name] ?? $icons['package'];
    }
    
    /**
     * Count posts
     */
    private function count_posts() {
        $count_posts = wp_count_posts('post');
        return (int) $count_posts->publish;
    }
    
    /**
     * Count pages
     */
    private function count_pages() {
        $count_pages = wp_count_posts('page');
        return (int) $count_pages->publish;
    }
    
    /**
     * Count media items
     */
    private function count_media() {
        $count_attachments = wp_count_posts('attachment');
        return (int) $count_attachments->inherit;
    }
    
    /**
     * Count users
     */
    private function count_users() {
        $user_count = count_users();
        return (int) $user_count['total_users'];
    }
    
    /**
     * Get recent posts
     */
    private function get_recent_posts($limit = 5) {
        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true, // Optimization: don't count total rows
        ];
        
        $posts_query = new \WP_Query($args);
        $recent_posts = [];
        
        if ($posts_query->have_posts()) {
            $post_ids = wp_list_pluck($posts_query->posts, 'ID');
            
            // Get author data in one query to avoid N+1
            $authors_data = [];
            if (!empty($post_ids)) {
                $author_ids = array_unique(wp_list_pluck($posts_query->posts, 'post_author'));
                $authors = get_users([
                    'include' => $author_ids,
                    'fields' => ['ID', 'display_name']
                ]);
                
                foreach ($authors as $author) {
                    $authors_data[$author->ID] = $author->display_name;
                }
            }
            
            foreach ($posts_query->posts as $post) {
                $recent_posts[] = [
                    'id' => intval($post->ID),
                    'title' => get_the_title($post),
                    'status' => $post->post_status,
                    'date' => $post->post_date,
                    'author' => $authors_data[$post->post_author] ?? 'Unknown',
                ];
            }
        }
        
        wp_reset_postdata();
        return $recent_posts;
    }
    
    /**
     * Get default features (fallback)
     */
    private function get_default_features() {
        return [
            [
                'key' => 'posts',
                'name' => 'Posts Management',
                'description' => 'Create and manage blog posts with advanced editing features.',
                'icon' => 'filetext',
                'url' => admin_url('edit.php'),
                'category' => 'Content',
                'status' => 'active',
            ],
            [
                'key' => 'media',
                'name' => 'Media Library',
                'description' => 'Upload and manage images, videos, and other media files.',
                'icon' => 'database',
                'url' => admin_url('upload.php'),
                'category' => 'Content',
                'status' => 'active',
            ],
            [
                'key' => 'users',
                'name' => 'User Management',
                'description' => 'Manage user accounts, roles, and permissions.',
                'icon' => 'users',
                'url' => admin_url('users.php'),
                'category' => 'Administration',
                'status' => 'active',
            ],
        ];
    }
    
    /**
     * Render dashboard template
     */
    public function render_dashboard() {
        // Security: Validate template path
        $template_path = dirname(dirname(__DIR__)) . '/templates/dashboard.php';
        $template_path = realpath($template_path);
        
        if ($template_path && file_exists($template_path) && is_readable($template_path)) {
            // Make template renderer available in template
            global $wpfd_template_renderer;
            $wpfd_template_renderer = $this;
            
            include $template_path;
        } else {
            // Fallback to basic output
            wp_die(__('Dashboard template not found.', 'wp-frontend-dashboard'));
        }
    }
    
    /**
     * Get empty overview data (fallback)
     */
    private function get_empty_overview() {
        return [
            'stats' => [
                'posts' => 0,
                'pages' => 0,
                'media' => 0,
                'users' => 0,
            ],
            'recent_posts' => [],
        ];
    }
}
