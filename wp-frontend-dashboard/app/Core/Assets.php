<?php
namespace WPFD\Core;

class Assets {
    public function register() {
        // Enqueue Tailwind CSS for admin pages
        add_action('admin_enqueue_scripts', [$this, 'enqueue_tailwind_css']);

        add_action('wp_enqueue_scripts', function () {
            $route = get_query_var('wpfd_route');
            if (!$route) return;

            // Isolate our routes from theme conflicts
            // We dequeue everything EXCEPT essential core and our plugin assets
            $clean_slate = function() {
                global $wp_scripts, $wp_styles;
                
                // Essential handles to keep
                $keep_scripts = ['jquery', 'jquery-core', 'jquery-migrate', 'wpfd-navigation', 'wpfd-dashboard'];
                $keep_styles = ['dashicons', 'admin-bar', 'wpfd-dashboard'];
                
                // Dynamically identify our plugin's style handles (wpfd-*)
                foreach ($wp_styles->registered as $handle => $style) {
                    if (str_starts_with($handle, 'wpfd-') || str_starts_with($handle, 'orabooks-') || str_contains($handle, 'et-builder') || str_contains($handle, 'divi')) {
                        $keep_styles[] = $handle;
                    }
                }

                // Dynamically identify our plugin's script handles (wpfd-* and orabooks-*)
                // Also whitelist Divi/ET Builder assets if they exist
                foreach ($wp_scripts->registered as $handle => $script) {
                    if (str_starts_with($handle, 'wpfd-') || str_starts_with($handle, 'orabooks-') || str_contains($handle, 'et-builder') || str_contains($handle, 'divi')) {
                        $keep_scripts[] = $handle;
                    }
                }

                // De-enqueue non-essential scripts
                foreach ($wp_scripts->queue as $handle) {
                    if (!in_array($handle, $keep_scripts)) {
                        wp_dequeue_script($handle);
                    }
                }

                // De-enqueue non-essential styles (This is the key to preventing "Broken Badly" CSS)
                foreach ($wp_styles->queue as $handle) {
                    if (!in_array($handle, $keep_styles)) {
                        wp_dequeue_style($handle);
                    }
                }
            };

            // Hook late to ensure theme assets are already enqueued and then removed
            add_action('wp_print_scripts', $clean_slate, 1);
            add_action('wp_print_styles', $clean_slate, 1);

            $base_dir = dirname(dirname(dirname(__FILE__))); // wp-frontend-dashboard root
            $assets_url = plugin_dir_url($base_dir . '/index.php') . 'assets/';

            // Enqueue dashboard styles
            if ($route === 'dashboard') {
                // Preload critical CSS
                wp_enqueue_style(
                    'wpfd-dashboard',
                    $assets_url . 'css/dashboard.css',
                    [],
                    '1.0.3'
                );
                
                // Enqueue professional search results CSS
                wp_enqueue_style(
                    'wpfd-search-results',
                    $assets_url . 'css/search-results.css',
                    ['wpfd-dashboard'],
                    '1.0.4'
                );

                // Enqueue notifications CSS
                wp_enqueue_style(
                    'wpfd-notifications',
                    $assets_url . 'css/notifications.css',
                    ['wpfd-dashboard'],
                    '1.0.0'
                );
                

                // Only enqueue essential scripts - disabled AJAX navigation scripts
                wp_enqueue_script(
                    'wpfd-navigation',
                    $assets_url . 'js/navigation.js',
                    [],
                    '1.0.3',
                    true
                );

                // Enqueue modular dashboard scripts
                wp_enqueue_script(
                    'wpfd-dashboard-search',
                    $assets_url . 'js/dashboard-search.js',
                    ['jquery'],
                    '1.0.3',
                    true
                );

                wp_enqueue_script(
                    'wpfd-dashboard-navigation',
                    $assets_url . 'js/dashboard-navigation.js',
                    ['jquery'],
                    '1.0.3',
                    true
                );

                wp_enqueue_script(
                    'wpfd-dashboard',
                    $assets_url . 'js/dashboard.js',
                    ['jquery', 'wpfd-dashboard-search', 'wpfd-dashboard-navigation'],
                    '1.0.3',
                    true
                );

                // Attach to wpfd-navigation so wpfdVars exists before navigation.js runs
                wp_localize_script('wpfd-navigation', 'wpfdVars', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('wpfd_nonce'),
                    'restUrl' => esc_url_raw(rest_url('wpfd/v1')),
                    'restNonce' => wp_create_nonce('wp_rest'),
                    'homeUrl' => home_url('/'),
                    'adminBase' => admin_url(),
                    'logoutUrl' => esc_url_raw(wp_logout_url(home_url('/'))),
                    'isAdmin' => current_user_can('manage_options'),
                    'paymentNonce' => wp_create_nonce('orabooks_payment_nonce'),
                ]);
            }
        });

        // Add AJAX handlers for dashboard functionality
        add_action('wp_ajax_wpfd_toggle_feature', [$this, 'handle_toggle_feature']);
        add_action('wp_ajax_wpfd_refresh_stats', [$this, 'handle_refresh_stats']);
        add_action('wp_ajax_wpfd_search', [$this, 'handle_search']);
        add_action('wp_ajax_wpfd_create_post', [$this, 'handle_create_post']);
        add_action('wp_ajax_wpfd_delete_item', [$this, 'handle_delete_item']);

        // Hide frontend header/footer when loaded in dashboard iframe
        add_action('wp_head', function() {
            if (isset($_GET['wpfd_iframe']) && $_GET['wpfd_iframe'] === '1') {
                echo '<style>
                    header, footer, .sidebar, .nav-container, #header, #footer { display: none !important; }
                    body { background: white !important; padding: 20px !important; }
                    .orabooks-checkout-container { margin: 0 !important; padding: 0 !important; }
                </style>';
            }
        });

        // Divi Builder Support - Ensure assets are NOT stripped for builder
        add_action('admin_head', function() {
            if (isset($_GET['et_fb']) || (isset($_GET['action']) && $_GET['action'] === 'edit')) {
                // Add a class to body to allow targeted CSS overrides
                echo '<script>document.body.classList.add("wpfd-editor-view");</script>';
                
                // If it's Divi, we need to allow more assets
                if (isset($_GET['et_fb'])) {
                    echo '<style>
                        #wpadminbar { display: flex !important; }
                        #adminmenumain { display: none !important; }
                        #wpcontent { margin-left: 0 !important; }
                    </style>';
                }
            }
        });

        // Custom wp_die handler for beautiful error pages
        add_filter('wp_die_handler', function($handler) {
            // Only use our custom handler if it's an AJAX/Iframe request or we are on the dashboard
            if (isset($_GET['wpfd_iframe']) || (defined('REST_REQUEST') && REST_REQUEST)) {
                return [$this, 'custom_die_handler'];
            }
            return $handler;
        });
    }

    /**
     * Custom die handler for PWA style errors
     */
    public function custom_die_handler($message, $title = '', $args = []) {
        $title = $title ?: 'Notice';
        $message = wp_kses_post($message);
        
        // If it's a REST request, return JSON
        if (defined('REST_REQUEST') && REST_REQUEST) {
            wp_send_json([
                'code' => 'wpfd_notice',
                'message' => $message,
                'data' => ['status' => 200]
            ], 200);
            exit;
        }

        // For Iframe/Normal requests, show beautiful HTML
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($title); ?></title>
            <style>
                :root {
                    --primary: #3b82f6;
                    --bg: #f8fafc;
                }
                body {
                    font-family: 'Inter', -apple-system, sans-serif;
                    background: var(--bg);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    height: 100vh;
                    margin: 0;
                    padding: 20px;
                    box-sizing: border-box;
                }
                .notice-card {
                    background: rgba(255, 255, 255, 0.8);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(255, 255, 255, 0.3);
                    border-radius: 32px;
                    padding: 40px;
                    max-width: 450px;
                    width: 100%;
                    text-align: center;
                    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.05);
                }
                .icon {
                    width: 64px;
                    height: 64px;
                    background: #eff6ff;
                    color: var(--primary);
                    border-radius: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 24px;
                }
                h1 {
                    font-size: 1.5rem;
                    font-weight: 800;
                    color: #0f172a;
                    margin: 0 0 12px;
                }
                p {
                    font-size: 1rem;
                    color: #64748b;
                    line-height: 1.6;
                    margin: 0 0 32px;
                }
                .btn {
                    display: inline-block;
                    background: var(--primary);
                    color: white;
                    padding: 14px 28px;
                    border-radius: 16px;
                    text-decoration: none;
                    font-weight: 700;
                    transition: all 0.3s ease;
                    box-shadow: 0 10px 20px rgba(59, 130, 246, 0.2);
                }
                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 15px 30px rgba(59, 130, 246, 0.3);
                }
            </style>
        </head>
        <body>
            <div class="notice-card">
                <div class="icon">
                    <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h1><?php echo esc_html($title); ?></h1>
                <p><?php echo $message; ?></p>
                <a href="javascript:history.back()" class="btn">Go Back</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * Handle toggle feature AJAX request
     */
    public function handle_toggle_feature() {
        check_ajax_referer('wpfd_nonce', 'nonce');

        if (!current_user_can('read')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $feature_key = sanitize_text_field($_POST['feature_key']);
        
        // Use the DashboardFeaturesController to handle this
        $controller = new \WPFD\REST\DashboardFeaturesController();
        
        // Create a mock request
        $request = new \WP_REST_Request('POST', '/wpfd/v1/dashboard/features');
        $request->set_param('feature_key', $feature_key);
        
        $response = $controller->toggle_feature($request);
        
        if ($response instanceof \WP_REST_Response) {
            wp_send_json_success($response->get_data());
        } elseif ($response instanceof \WP_Error) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        } else {
            wp_send_json_success($response);
        }
    }

    /**
     * Handle refresh stats AJAX request
     */
    public function handle_refresh_stats() {
        check_ajax_referer('wpfd_nonce', 'nonce');

        if (!current_user_can('read')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $template_renderer = new \WPFD\Core\TemplateRenderer();
        $overview_data = $template_renderer->get_dashboard_overview();

        wp_send_json_success([
            'stats' => $overview_data['stats'],
            'recent_posts' => $overview_data['recent_posts'],
            'last_updated' => current_time('mysql'),
        ]);
    }


    /**
     * Handle search AJAX request
     */
    public function handle_search() {
        check_ajax_referer('wpfd_nonce', 'nonce');

        if (!current_user_can('read')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $query = sanitize_text_field($_POST['query']);

        if (empty($query) || strlen($query) < 2) {
            wp_send_json_success(['results' => []]);
        }

        $results = [];

        // Search posts
        $post_args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            's' => $query,
            'posts_per_page' => 5,
        ];
        $posts_query = new \WP_Query($post_args);

        if ($posts_query->have_posts()) {
            while ($posts_query->have_posts()) {
                $posts_query->the_post();
                $results[] = [
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'type' => 'post',
                    'url' => get_permalink(),
                ];
            }
        }
        wp_reset_postdata();

        // Search pages
        $page_args = [
            'post_type' => 'page',
            'post_status' => 'publish',
            's' => $query,
            'posts_per_page' => 5,
        ];
        $pages_query = new \WP_Query($page_args);

        if ($pages_query->have_posts()) {
            while ($pages_query->have_posts()) {
                $pages_query->the_post();
                $results[] = [
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'type' => 'page',
                    'url' => get_permalink(),
                ];
            }
        }
        wp_reset_postdata();

        // Search media
        $media_args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            's' => $query,
            'posts_per_page' => 5,
        ];
        $media_query = new \WP_Query($media_args);

        if ($media_query->have_posts()) {
            while ($media_query->have_posts()) {
                $media_query->the_post();
                $results[] = [
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'type' => 'media',
                    'url' => admin_url('upload.php?item=' . get_the_ID()),
                ];
            }
        }
        wp_reset_postdata();

        wp_send_json_success(['results' => $results]);
    }

    /**
     * Handle create post AJAX request
     */
    public function handle_create_post() {
        check_ajax_referer('wpfd_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions to create posts']);
        }

        $post_data = json_decode(stripslashes($_POST['post_data']), true);
        
        if (!$post_data || empty($post_data['title'])) {
            wp_send_json_error(['message' => 'Title is required']);
        }

        $post_args = [
            'post_title' => sanitize_text_field($post_data['title']),
            'post_content' => wp_kses_post($post_data['content'] ?? ''),
            'post_status' => in_array($post_data['status'], ['draft', 'publish', 'private']) ? $post_data['status'] : 'draft',
            'post_type' => in_array($post_data['type'], ['post', 'page']) ? $post_data['type'] : 'post',
            'post_author' => get_current_user_id()
        ];

        // Check specific capabilities for post type
        if ($post_args['post_type'] === 'page' && !current_user_can('edit_pages')) {
            wp_send_json_error(['message' => 'Insufficient permissions to create pages']);
        }

        $post_id = wp_insert_post($post_args, true);

        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => $post_id->get_error_message()]);
        }

        // Set featured image if provided
        if (!empty($post_data['featured_image'])) {
            set_post_thumbnail($post_id, intval($post_data['featured_image']));
        }

        // Set categories for posts
        if ($post_args['post_type'] === 'post' && !empty($post_data['categories'])) {
            wp_set_post_categories($post_id, array_map('intval', $post_data['categories']));
        }

        // Set tags for posts
        if ($post_args['post_type'] === 'post' && !empty($post_data['tags'])) {
            wp_set_post_tags($post_id, array_map('sanitize_text_field', $post_data['tags']));
        }

        wp_send_json_success([
            'message' => ucfirst($post_args['post_type']) . ' created successfully',
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link($post_id),
            'view_url' => get_permalink($post_id)
        ]);
    }

    /**
     * Handle delete item AJAX request
     */
    public function handle_delete_item() {
        check_ajax_referer('wpfd_nonce', 'nonce');

        $item_id = intval($_POST['item_id']);
        $item_type = sanitize_text_field($_POST['item_type']);

        if (!$item_id || !$item_type) {
            wp_send_json_error(['message' => 'Invalid request']);
        }

        // Check permissions based on item type
        if ($item_type === 'page') {
            if (!current_user_can('delete_pages')) {
                wp_send_json_error(['message' => 'Insufficient permissions to delete pages']);
            }
        } elseif ($item_type === 'post') {
            if (!current_user_can('delete_posts')) {
                wp_send_json_error(['message' => 'Insufficient permissions to delete posts']);
            }
        } elseif ($item_type === 'media') {
            if (!current_user_can('delete_posts')) {
                wp_send_json_error(['message' => 'Insufficient permissions to delete media']);
            }
        } else {
            wp_send_json_error(['message' => 'Invalid item type']);
        }

        // Perform deletion
        if ($item_type === 'media') {
            $result = wp_delete_attachment($item_id, true);
            if ($result === false) {
                wp_send_json_error(['message' => 'Failed to delete media file']);
            }
        } else {
            $result = wp_delete_post($item_id, true);
            if ($result === false || is_wp_error($result)) {
                wp_send_json_error(['message' => 'Failed to delete item']);
            }
        }

        wp_send_json_success([
            'message' => ucfirst($item_type) . ' deleted successfully',
            'item_id' => $item_id
        ]);
    }

    /**
     * Enqueue Tailwind CSS for admin pages
     */
    public function enqueue_tailwind_css() {
        // Only load for non-super-admins to apply custom styling
        if (is_super_admin()) {
            return;
        }
        
        // Skip on post/page edit screens to avoid breaking the editor (Divi, Classic, Block)
        global $pagenow;
        if (in_array($pagenow, ['post.php', 'post-new.php', 'page.php', 'page-new.php'])) {
            return;
        }

        $base_dir = dirname(dirname(dirname(__FILE__)));
        $assets_url = plugin_dir_url($base_dir . '/index.php') . 'assets/';

        // Enqueue Tailwind CSS via CDN
        wp_enqueue_style(
            'wpfd-tailwind',
            'https://cdn.tailwindcss.com',
            [],
            '3.4.0'
        );

        // Enqueue UI enhancements JavaScript
        wp_enqueue_script(
            'wpfd-ui-enhancements',
            $assets_url . 'js/ui-enhancements.js',
            ['jquery'],
            '1.0.0',
            true
        );

        // Add Tailwind config inline
        wp_add_inline_style('wpfd-tailwind', '
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            primary: {
                                50: "#eff6ff",
                                100: "#dbeafe",
                                200: "#bfdbfe",
                                300: "#93c5fd",
                                400: "#60a5fa",
                                500: "#3b82f6",
                                600: "#2563eb",
                                700: "#1d4ed8",
                                800: "#1e40af",
                                900: "#1e3a8a",
                            },
                            secondary: {
                                50: "#eef2ff",
                                100: "#e0e7ff",
                                200: "#c7d2fe",
                                300: "#a5b4fc",
                                400: "#818cf8",
                                500: "#6366f1",
                                600: "#4f46e5",
                                700: "#4338ca",
                                800: "#3730a3",
                                900: "#312e81",
                            },
                        },
                        backgroundImage: {
                            "gradient-primary": "linear-gradient(135deg, #3b82f6 0%, #6366f1 100%)",
                            "gradient-secondary": "linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)",
                            "gradient-success": "linear-gradient(135deg, #10b981 0%, #059669 100%)",
                            "gradient-warning": "linear-gradient(135deg, #f59e0b 0%, #d97706 100%)",
                            "gradient-danger": "linear-gradient(135deg, #ef4444 0%, #dc2626 100%)",
                            "gradient-sidebar": "linear-gradient(180deg, #1e293b 0%, #0f172a 100%)",
                        }
                    }
                }
            }
        ');
    }
}
