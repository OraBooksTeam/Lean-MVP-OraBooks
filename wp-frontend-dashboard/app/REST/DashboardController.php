<?php

namespace WPFD\REST;

use WPFD\Core\ErrorHandler;

class DashboardController {
    public function register() {
        $namespace = 'wpfd/v1';

        register_rest_route($namespace, '/dashboard/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search'],
            'permission_callback' => [$this, 'dashboard_permission'],
        ]);

        register_rest_route($namespace, '/dashboard/notifications', [
            'methods' => 'GET',
            'callback' => [$this, 'get_notifications'],
            'permission_callback' => [$this, 'dashboard_permission'],
        ]);
    }

    public function dashboard_permission() {
        if (!is_user_logged_in()) {
            return false;
        }

        if (is_multisite() && !is_user_member_of_blog(get_current_user_id(), get_current_blog_id())) {
            return false;
        }

        return current_user_can('read');
    }

    public function get_overview() {
        $posts_count = wp_count_posts('post');
        $pages_count = wp_count_posts('page');

        $recent_posts_query = new \WP_Query([
            'post_type' => 'post',
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $recent_posts = array_map(function($post) {
            return [
                'id' => $post->ID,
                'title' => get_the_title($post),
                'status' => $post->post_status,
                'date' => $post->post_date,
                'author' => get_the_author_meta('display_name', (int) $post->post_author),
            ];
        }, $recent_posts_query->posts);

        wp_reset_postdata();

        $media_count = wp_count_attachments();
        $users_count = count_users();

        return [
            'stats' => [
                'posts' => (int) ($posts_count->publish ?? 0) + (int) ($posts_count->draft ?? 0),
                'pages' => (int) ($pages_count->publish ?? 0) + (int) ($pages_count->draft ?? 0),
                'media' => (int) ($media_count->inherit ?? 0),
                'users' => (int) ($users_count['total_users'] ?? 0),
            ],
            'recent_posts' => $recent_posts,
        ];
    }

    public function search($request) {
        try {
            $query = sanitize_text_field($request->get_param('q') ?: '');
            $limit = max(1, min(15, intval($request->get_param('limit') ?: 8)));

            if ($query === '') {
                return [
                    'query' => '',
                    'posts' => [],
                    'pages' => [],
                    'media' => [],
                    'users' => [],
                ];
            }

        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            's' => $query,
            'numberposts' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            's' => $query,
            'numberposts' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $media_query = new \WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            's' => $query,
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $users = get_users([
            'search' => '*' . $query . '*',
            'search_columns' => ['user_login', 'display_name', 'user_email'],
            'number' => $limit,
        ]);

        return [
            'query' => sanitize_text_field($query),
            'posts' => array_map(function($post) {
                return [
                    'id' => intval($post->ID),
                    'title' => sanitize_text_field(get_the_title($post)),
                    'status' => sanitize_text_field($post->post_status),
                    'date' => sanitize_text_field($post->post_date),
                    'url' => esc_url('/dashboard/posts/' . $post->ID),
                ];
            }, $posts),
            'pages' => array_map(function($page) {
                return [
                    'id' => intval($page->ID),
                    'title' => sanitize_text_field(get_the_title($page)),
                    'status' => sanitize_text_field($page->post_status),
                    'date' => sanitize_text_field($page->post_date),
                    'url' => esc_url('/dashboard/pages/' . $page->ID),
                ];
            }, $pages),
            'media' => array_map(function($media) {
                return [
                    'id' => intval($media->ID),
                    'title' => sanitize_text_field(get_the_title($media)),
                    'mime_type' => sanitize_text_field($media->post_mime_type),
                    'date' => sanitize_text_field($media->post_date),
                    'url' => esc_url('/dashboard/media'),
                ];
            }, $media_query->posts),
            'users' => array_map(function($user) {
                return [
                    'id' => intval($user->ID),
                    'name' => sanitize_text_field($user->display_name),
                    'email' => sanitize_email($user->user_email),
                    'url' => esc_url('/dashboard/users'),
                ];
            }, $users),
        ];
        } catch (Exception $e) {
            return ErrorHandler::handle_rest_error('Search failed: ' . $e->getMessage(), 'search_error', 500);
        }
    }

    public function get_notifications($request) {
        $notifications = [];
        
        // 1. Recent Comments
        $comments = get_comments([
            'number' => 5,
            'status' => 'hold', // Pending comments are "notifications" for admins
        ]);
        
        if (empty($comments)) {
            $comments = get_comments(['number' => 3]); // Fallback to recent comments
        }

        foreach ($comments as $comment) {
            $notifications[] = [
                'id' => 'comment_' . $comment->comment_ID,
                'type' => 'comment',
                'title' => 'New Comment',
                'message' => substr(strip_tags($comment->comment_content), 0, 60) . '...',
                'time' => $comment->comment_date,
                'url' => '/dashboard/comments',
                'read' => false
            ];
        }

        // 2. Recent Posts Updates
        $recent_posts = get_posts([
            'post_type' => 'post',
            'posts_per_page' => 3,
            'orderby' => 'modified',
        ]);

        foreach ($recent_posts as $post) {
            $notifications[] = [
                'id' => 'post_' . $post->ID,
                'type' => 'post',
                'title' => 'Content Update',
                'message' => 'Post "' . get_the_title($post) . '" was modified.',
                'time' => $post->post_modified,
                'url' => '/dashboard/posts/' . $post->ID,
                'read' => true
            ];
        }

        // Sort by time
        usort($notifications, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });

        return [
            'notifications' => array_slice($notifications, 0, 8),
            'unread_count' => count(array_filter($notifications, function($n) { return !$n['read']; }))
        ];
    }


    public function get_addon_features() {
        // Debug logging
        error_log('WPFD: get_addon_features called');
        
        // Try to include TaxOra functions directly if not available
        if (!function_exists('orabooks_get_available_features')) {
            // Try to find and include the features file
            $possible_paths = array(
                WP_PLUGIN_DIR . '/taxora-wpmu-membership-subscription-panel/includes/features.php',
                WP_PLUGIN_DIR . '/TaxOra - WPMU Membership Subscription Panel/includes/features.php',
                dirname(WP_PLUGIN_DIR) . '/TaxOra - WPMU Membership Subscription Panel/includes/features.php',
            );
            
            foreach ($possible_paths as $path) {
                if (file_exists($path)) {
                    include_once $path;
                    error_log('WPFD: Included features.php from: ' . $path);
                    break;
                }
            }
        }
        
        // Check if TaxOra membership plugin is active
        if (!function_exists('orabooks_get_available_features')) {
            error_log('WPFD: orabooks_get_available_features function not found');
            
            // Try direct database query as fallback
            global $wpdb;
            $user_id = get_current_user_id();
            $level_id = get_user_meta($user_id, 'orabooks_level', true);
            
            if ($level_id) {
                $table_name = $wpdb->prefix . 'orabooks_feature_assignments';
                $features = $wpdb->get_results($wpdb->prepare(
                    "SELECT feature_key FROM $table_name WHERE level_id = %d",
                    $level_id
                ));
                
                $activated_features = array();
                $sample_features = array(
                    'accounting' => array('name' => 'Accounting', 'description' => 'Complete accounting system', 'category' => 'Accounting', 'icon' => 'calculator'),
                    'inventory' => array('name' => 'Inventory', 'description' => 'Stock management system', 'category' => 'Inventory', 'icon' => 'database')
                );
                
                foreach ($features as $feature) {
                    if (isset($sample_features[$feature->feature_key])) {
                        $feature_data = $sample_features[$feature->feature_key];
                        $activated_features[] = array(
                            'key' => $feature->feature_key,
                            'name' => $feature_data['name'],
                            'description' => $feature_data['description'],
                            'icon' => $feature_data['icon'],
                            'url' => home_url('/' . $feature->feature_key),
                            'category' => $feature_data['category'],
                            'status' => 'active'
                        );
                    }
                }
                
                return array(
                    'features' => $activated_features,
                    'total_count' => count($activated_features),
                    'debug' => array(
                        'method' => 'direct_database',
                        'user_id' => $user_id,
                        'level_id' => $level_id,
                        'features_found' => count($features)
                    )
                );
            }
            
            return array(
                'features' => array(),
                'message' => 'TaxOra Membership plugin not found and no user level assigned',
                'debug' => array(
                    'function_exists' => false,
                    'user_id' => get_current_user_id(),
                    'level_id' => $level_id
                )
            );
        }

        $user_id = get_current_user_id();
        error_log('WPFD: Current user ID: ' . $user_id);
        
        $available_features = orabooks_get_available_features();
        error_log('WPFD: Available features count: ' . count($available_features));
        error_log('WPFD: Available features: ' . print_r(array_keys($available_features), true));
        
        // Filter to show only main addon features, not granular sub-features
        $main_addon_features = array();
        foreach ($available_features as $feature_key => $feature_data) {
            // Exclude features that have a 'parent' field (granular sub-features)
            if (isset($feature_data['parent'])) {
                continue;
            }
            // Only include features where the key matches an addon ID (main addon features)
            if (isset($feature_data['addon_id']) && $feature_key === $feature_data['addon_id']) {
                $main_addon_features[$feature_key] = $feature_data;
            }
            // Also include features that don't have a parent addon (core features)
            elseif (!isset($feature_data['addon_id'])) {
                $main_addon_features[$feature_key] = $feature_data;
            }
        }
        
        error_log('WPFD: Main addon features count: ' . count($main_addon_features));
        error_log('WPFD: Main addon features: ' . print_r(array_keys($main_addon_features), true));
        
        $activated_features = [];

        foreach ($main_addon_features as $feature_key => $feature_data) {
            error_log('WPFD: Checking feature: ' . $feature_key);
            
            // TEMPORARY FIX: Show all features regardless of access
            // This is a workaround until features are properly assigned to membership levels
            // TODO: Re-enable access checking once membership level assignments are configured
            /*
            // Check if user has access to this feature
            if (function_exists('orabooks_user_has_feature_access')) {
                $has_access = orabooks_user_has_feature_access($user_id, $feature_key);
                error_log('WPFD: User ' . $user_id . ' access to ' . $feature_key . ': ' . ($has_access ? 'YES' : 'NO'));
                
                if ($has_access) {
                    $activated_features[] = [
                        'key' => $feature_key,
                        'name' => $feature_data['name'] ?? ucfirst($feature_key),
                        'description' => $feature_data['description'] ?? 'No description available',
                        'icon' => $feature_data['icon'] ?? 'package',
                        'url' => $feature_data['url'] ?? '#',
                        'category' => $feature_data['category'] ?? 'General',
                        'status' => 'active'
                    ];
                    error_log('WPFD: Added feature: ' . $feature_key);
                }
            } else {
                error_log('WPFD: orabooks_user_has_feature_access function not found');
            }
            */
            
            // Show all main addon features (temporary workaround)
            $activated_features[] = [
                'key' => $feature_key,
                'name' => $feature_data['name'] ?? ucfirst($feature_key),
                'description' => $feature_data['description'] ?? 'No description available',
                'icon' => $feature_data['icon'] ?? 'package',
                'url' => $feature_data['url'] ?? '#',
                'category' => $feature_data['category'] ?? 'General',
                'status' => 'active'
            ];
            error_log('WPFD: Added feature (no access check): ' . $feature_key);
        }

        $result = [
            'features' => $activated_features,
            'total_count' => count($activated_features),
            'debug' => [
                'method' => 'normal',
                'user_id' => $user_id,
                'available_count' => count($available_features),
                'activated_count' => count($activated_features),
                'functions_exist' => [
                    'orabooks_get_available_features' => function_exists('orabooks_get_available_features'),
                    'orabooks_user_has_feature_access' => function_exists('orabooks_user_has_feature_access')
                ]
            ]
        ];
        
        error_log('WPFD: Returning result: ' . print_r($result, true));
        return $result;
    }
}
