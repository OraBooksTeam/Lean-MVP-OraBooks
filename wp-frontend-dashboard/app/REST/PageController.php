<?php
namespace WPFD\REST;

class PageController {
    public function register() {
        $namespace = 'wpfd/v1';
        $base = '/pages';

        register_rest_route($namespace, $base, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'check_read_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'check_create_permission'],
            ],
        ]);

        register_rest_route($namespace, $base . '/(?P<id>[\d]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_item'],
                'permission_callback' => function($request) {
                    return $this->check_read_single_permission($request);
                },
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_item'],
                'permission_callback' => function($request) {
                    return $this->check_edit_permission($request);
                },
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_item'],
                'permission_callback' => function($request) {
                    return $this->check_delete_permission($request);
                },
            ],
        ]);

        // Templates endpoint
        register_rest_route($namespace, $base . '/templates', [
            'methods' => 'GET',
            'callback' => [$this, 'get_templates'],
            'permission_callback' => [$this, 'check_read_permission'],
        ]);

        // Parent pages endpoint
        register_rest_route($namespace, $base . '/parents', [
            'methods' => 'GET',
            'callback' => [$this, 'get_parent_pages'],
            'permission_callback' => [$this, 'check_read_permission'],
        ]);

        // Authors endpoint
        register_rest_route($namespace, $base . '/authors', [
            'methods' => 'GET',
            'callback' => [$this, 'get_authors'],
            'permission_callback' => [$this, 'check_read_permission'],
        ]);

        // Slug validation endpoint
        register_rest_route($namespace, $base . '/validate-slug', [
            'methods' => 'POST',
            'callback' => [$this, 'validate_slug'],
            'permission_callback' => [$this, 'check_read_permission'],
        ]);

        // Bulk actions endpoint
        register_rest_route($namespace, $base . '/bulk', [
            'methods' => 'POST',
            'callback' => [$this, 'bulk_action'],
            'permission_callback' => [$this, 'check_create_permission'],
        ]);

        // Duplicate page endpoint
        register_rest_route($namespace, $base . '/(?P<id>[\d]+)/duplicate', [
            'methods' => 'POST',
            'callback' => [$this, 'duplicate_page'],
            'permission_callback' => function($request) {
                return $this->check_create_permission();
            },
        ]);

        // Trash endpoint (move to trash)
        register_rest_route($namespace, $base . '/(?P<id>[\d]+)/trash', [
            'methods' => 'POST',
            'callback' => [$this, 'trash_page'],
            'permission_callback' => function($request) {
                return $this->check_delete_permission($request);
            },
        ]);

        // Restore endpoint (restore from trash)
        register_rest_route($namespace, $base . '/(?P<id>[\d]+)/restore', [
            'methods' => 'POST',
            'callback' => [$this, 'restore_page'],
            'permission_callback' => function($request) {
                return $this->check_delete_permission($request);
            },
        ]);

        // Revisions endpoint
        register_rest_route($namespace, $base . '/(?P<id>[\d]+)/revisions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_revisions'],
            'permission_callback' => function($request) {
                return $this->check_read_single_permission($request);
            },
        ]);

        // Restore revision endpoint
        register_rest_route($namespace, $base . '/(?P<id>[\d]+)/revisions/(?P<revision_id>[\d]+)/restore', [
            'methods' => 'POST',
            'callback' => [$this, 'restore_revision'],
            'permission_callback' => function($request) {
                return $this->check_edit_permission($request);
            },
        ]);

        // Preview endpoint
        register_rest_route($namespace, $base . '/(?P<id>[\d]+)/preview', [
            'methods' => 'GET',
            'callback' => [$this, 'get_preview_link'],
            'permission_callback' => function($request) {
                return $this->check_read_single_permission($request);
            },
        ]);
    }

    public function check_read_permission() {
        if (!is_user_logged_in()) return false;
        if (is_multisite() && !is_user_member_of_blog(get_current_user_id(), get_current_blog_id())) {
            return false;
        }
        
        $current_user_id = get_current_user_id();
        $is_super_admin = is_multisite() ? is_super_admin($current_user_id) : current_user_can('manage_options');
        
        // Superadmins can access all pages
        if ($is_super_admin) {
            return true;
        }
        
        // Regular users need edit_pages capability
        return current_user_can('edit_pages');
    }

    public function check_create_permission() {
        if (!is_user_logged_in()) return false;
        if (is_multisite() && !is_user_member_of_blog(get_current_user_id(), get_current_blog_id())) {
            return false;
        }
        return current_user_can('edit_pages');
    }

    public function check_read_single_permission($request) {
        $page_id = isset($request['id']) ? intval($request['id']) : 0;
        if (!$page_id) return false;
        
        if (!is_user_logged_in()) return false;
        if (is_multisite() && !is_user_member_of_blog(get_current_user_id(), get_current_blog_id())) {
            return false;
        }

        $current_user_id = get_current_user_id();
        $is_super_admin = is_multisite() ? is_super_admin($current_user_id) : current_user_can('manage_options');
        
        // Superadmins can access any page
        if ($is_super_admin) {
            return true;
        }

        // Get the page to check ownership
        $page = get_post($page_id);
        if (!$page || $page->post_type !== 'page') {
            return false;
        }

        // Regular users can only access their own pages
        if ($page->post_author != $current_user_id) {
            return false;
        }

        if ($this->is_restricted_client_page($page_id)) {
            return current_user_can('edit_post', $page_id);
        }
        
        return current_user_can('edit_post', $page_id) || current_user_can('read_post', $page_id);
    }

    public function check_edit_permission($request) {
        $page_id = isset($request['id']) ? intval($request['id']) : 0;
        if (!$page_id) return false;
        
        if (!is_user_logged_in()) return false;
        if (is_multisite() && !is_user_member_of_blog(get_current_user_id(), get_current_blog_id())) {
            return false;
        }
        
        $page = get_post($page_id);
        if (!$page || $page->post_type !== 'page') {
            return false;
        }

        if ($this->is_restricted_client_page($page_id)) {
            return current_user_can('edit_post', $page_id);
        }
        
        // Check if user can edit this specific page
        if (!current_user_can('edit_post', $page_id)) {
            return false;
        }
        
        // If page belongs to someone else, check edit_others_pages capability
        if ($page->post_author != get_current_user_id() && !current_user_can('edit_others_pages')) {
            return false;
        }
        
        return true;
    }

    public function check_delete_permission($request) {
        $page_id = isset($request['id']) ? intval($request['id']) : 0;
        if (!$page_id) return false;
        
        if (!is_user_logged_in()) return false;
        if (is_multisite() && !is_user_member_of_blog(get_current_user_id(), get_current_blog_id())) {
            return false;
        }
        
        $page = get_post($page_id);
        if (!$page || $page->post_type !== 'page') {
            return false;
        }

        if ($this->is_restricted_client_page($page_id)) {
            return current_user_can('delete_post', $page_id);
        }
        
        // Check if user can delete this specific page
        if (!current_user_can('delete_post', $page_id)) {
            return false;
        }
        
        // If page belongs to someone else, check delete_others_pages capability
        if ($page->post_author != get_current_user_id() && !current_user_can('delete_others_pages')) {
            return false;
        }
        
        return true;
    }

    private function is_restricted_client_page($page_id) {
        if (!is_multisite() || get_current_blog_id() === 1) {
            return false;
        }

        // Check if it's a default Orabooks page
        if (get_post_meta($page_id, '_orabooks_default_page', true)) {
            return true;
        }

        // Check if it's a protected Orabooks system page
        return $this->is_orabooks_system_page($page_id);
    }

    private function is_orabooks_system_page($page_id) {
        $page = get_post($page_id);
        if (!$page || $page->post_type !== 'page') {
            return false;
        }

        $page_title = strtolower($page->post_title);
        $protected_pages = [
            'orabooks my account',
            'my account',
            'orabooks confirmation',
            'confirmation',
            'orabooks checkout',
            'checkout'
        ];

        return in_array($page_title, $protected_pages);
    }

    public function get_items($request) {
        $per_page = max(1, min(100, intval($request->get_param('per_page') ?: 20)));
        $paged = max(1, intval($request->get_param('page') ?: 1));
        $search = $request->get_param('search') ? sanitize_text_field($request->get_param('search')) : '';
        $status = $request->get_param('status') ? sanitize_text_field($request->get_param('status')) : '';
        $author = $request->get_param('author') ? intval($request->get_param('author')) : 0;
        $orderby = $request->get_param('orderby') ? sanitize_text_field($request->get_param('orderby')) : 'date';
        $order = $request->get_param('order') ? sanitize_text_field($request->get_param('order')) : 'DESC';
        
        $args = [
            'post_type' => 'page',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'post_status' => 'any',
            'orderby' => in_array($orderby, ['date', 'title', 'modified'], true) ? $orderby : 'date',
            'order' => strtoupper($order) === 'ASC' ? 'ASC' : 'DESC',
        ];

        // Filter pages based on user role
        $current_user_id = get_current_user_id();
        $is_super_admin = is_multisite() ? is_super_admin($current_user_id) : current_user_can('manage_options');
        
        if (!$is_super_admin) {
            // On subsites, show all pages the user has access to (not just their own)
            // This allows clients to see pages like Landing Page created by admin
            if (!is_multisite() || get_current_blog_id() === 1) {
                $args['author'] = $current_user_id;
            }
        }

        if (is_multisite() && get_current_blog_id() !== 1) {
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key' => '_orabooks_default_page',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_orabooks_default_page',
                    'value' => '0',
                    'compare' => '=',
                ],
            ];
        }
        
        // Exclude protected Orabooks system pages for regular users
        if (!$is_super_admin) {
            $protected_page_ids = [];
            $protected_pages = ['orabooks my account', 'my account', 'orabooks confirmation', 'confirmation', 'orabooks checkout', 'checkout'];
            
            foreach ($protected_pages as $page_title) {
                $page = get_page_by_title($page_title, OBJECT, 'page');
                if ($page) {
                    $protected_page_ids[] = $page->ID;
                }
            }
            
            if (!empty($protected_page_ids)) {
                if (!isset($args['post__not_in'])) {
                    $args['post__not_in'] = $protected_page_ids;
                } else {
                    $args['post__not_in'] = array_merge($args['post__not_in'], $protected_page_ids);
                }
            }
        }
        
        if (!empty($search)) {
            $args['s'] = $search;
        }

        if (!empty($status) && $status !== 'any') {
            $args['post_status'] = $status;
        }

        // Only allow explicit author filter for superadmins
        if (!empty($author) && $is_super_admin) {
            $args['author'] = $author;
        }
        
        $query = new \WP_Query($args);
        $pages = $query->posts;
        $total = $query->found_posts;

        $items = array_map(function($page) {
            $author = get_userdata($page->post_author);
            $thumbnail_id = get_post_thumbnail_id($page->ID);
            $permalink = get_permalink($page->ID);
            
            return [
                'id' => $page->ID,
                'title' => $page->post_title,
                'status' => $page->post_status,
                'date' => $page->post_date,
                'modified' => $page->post_modified,
                'author' => $author ? $author->display_name : 'Unknown',
                'author_id' => $page->post_author,
                'parent' => $page->post_parent,
                'menu_order' => $page->menu_order,
                'featured_image' => $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : null,
                'permalink' => $permalink,
                'visibility' => $page->post_password ? 'password' : $page->post_status,
            ];
        }, $pages);
        
        return new \WP_REST_Response([
            'items' => $items,
            'total' => $total,
            'page' => $paged,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
        ], 200);
    }

    public function create_item($request) {
        $post_data = [
            'post_title' => sanitize_text_field($request['title']),
            'post_content' => wp_kses_post($request['content'] ?: ''),
            'post_status' => sanitize_text_field($request['status'] ?: 'draft'),
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
        ];
        
        if (isset($request['excerpt'])) {
            $post_data['post_excerpt'] = sanitize_textarea_field($request['excerpt']);
        }
        
        if (isset($request['parent_id'])) {
            $post_data['post_parent'] = intval($request['parent_id']);
        }
        
        if (isset($request['menu_order'])) {
            $post_data['menu_order'] = intval($request['menu_order']);
        }
        
        if (isset($request['slug'])) {
            $post_data['post_name'] = sanitize_title($request['slug']);
        }

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) return $post_id;
        
        // Handle featured image
        if (isset($request['featured_image_id']) && $request['featured_image_id']) {
            set_post_thumbnail($post_id, intval($request['featured_image_id']));
        }
        
        // Handle page template
        if (isset($request['template']) && $request['template']) {
            update_post_meta($post_id, '_wp_page_template', sanitize_text_field($request['template']));
        }

        // Handle scheduled publish date
        if (isset($request['publish_date']) && !empty($request['publish_date'])) {
            $time = strtotime($request['publish_date']);
            if ($time) {
                wp_update_post(['ID' => $post_id, 'post_date' => date('Y-m-d H:i:s', $time)]);
            }
        }

        // Handle SEO meta
        if (isset($request['meta_title'])) {
            update_post_meta($post_id, '_wpfd_meta_title', sanitize_text_field($request['meta_title']));
        }
        if (isset($request['meta_description'])) {
            update_post_meta($post_id, '_wpfd_meta_description', sanitize_textarea_field($request['meta_description']));
        }
        if (isset($request['meta_keywords'])) {
            update_post_meta($post_id, '_wpfd_meta_keywords', sanitize_text_field($request['meta_keywords']));
        }

        // Handle page customization
        if (isset($request['page_bg_color'])) {
            update_post_meta($post_id, '_wpfd_bg_color', sanitize_text_field($request['page_bg_color']));
        }
        if (isset($request['page_text_color'])) {
            update_post_meta($post_id, '_wpfd_text_color', sanitize_text_field($request['page_text_color']));
        }

        // Handle gallery
        if (isset($request['gallery_ids']) && is_array($request['gallery_ids'])) {
            update_post_meta($post_id, '_wpfd_gallery_ids', array_map('intval', $request['gallery_ids']));
        }

        // Handle sections
        if (isset($request['sections']) && is_array($request['sections'])) {
            $sanitized_sections = array_map(function($section) {
                return [
                    'id' => sanitize_text_field($section['id'] ?? ''),
                    'title' => sanitize_text_field($section['title'] ?? ''),
                    'content' => wp_kses_post($section['content'] ?? '')
                ];
            }, $request['sections']);
            update_post_meta($post_id, '_wpfd_sections', $sanitized_sections);
        }

        // Handle password-protected visibility
        if (isset($request['visibility']) && $request['visibility'] === 'password' && isset($request['post_password'])) {
            wp_update_post(['ID' => $post_id, 'post_password' => sanitize_text_field($request['post_password'])]);
        }
        
        return ['id' => $post_id, 'message' => 'Page created successfully'];
    }

    public function get_item($request) {
        $page = get_post($request['id']);
        if (!$page || $page->post_type !== 'page') {
            return new \WP_Error('no_page', 'Page not found', ['status' => 404]);
        }
        
        $author = get_userdata($page->post_author);
        $thumbnail_id = get_post_thumbnail_id($page->ID);
        $template = get_post_meta($page->ID, '_wp_page_template', true);
        $gallery_ids = get_post_meta($page->ID, '_wpfd_gallery_ids', true) ?: [];
        $gallery_urls = array_map(fn($id) => wp_get_attachment_url($id), $gallery_ids);

        return [
            'id' => $page->ID,
            'title' => $page->post_title,
            'content' => $page->post_content,
            'excerpt' => $page->post_excerpt,
            'status' => $page->post_status,
            'slug' => $page->post_name,
            'parent_id' => $page->post_parent,
            'menu_order' => $page->menu_order,
            'author' => $author ? $author->display_name : 'Unknown',
            'author_id' => $page->post_author,
            'featured_image_id' => $thumbnail_id ?: null,
            'featured_image_url' => $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : null,
            'template' => $template ?: 'default',
            'date' => $page->post_date,
            'modified' => $page->post_modified,
            'publish_date' => $page->post_date,
            'post_password' => $page->post_password,
            'visibility' => $page->post_password ? 'password' : $page->post_status,
            'meta_title' => get_post_meta($page->ID, '_wpfd_meta_title', true) ?: '',
            'meta_description' => get_post_meta($page->ID, '_wpfd_meta_description', true) ?: '',
            'meta_keywords' => get_post_meta($page->ID, '_wpfd_meta_keywords', true) ?: '',
            'page_bg_color' => get_post_meta($page->ID, '_wpfd_bg_color', true) ?: '#ffffff',
            'page_text_color' => get_post_meta($page->ID, '_wpfd_text_color', true) ?: '#000000',
            'gallery_ids' => $gallery_ids,
            'gallery_urls' => $gallery_urls,
            'sections' => get_post_meta($page->ID, '_wpfd_sections', true) ?: [],
        ];
    }

    public function update_item($request) {
        $post_data = [
            'ID' => intval($request['id']),
            'post_title' => sanitize_text_field($request['title']),
            'post_content' => wp_kses_post($request['content']),
            'post_status' => sanitize_text_field($request['status']),
        ];
        
        if (isset($request['excerpt'])) {
            $post_data['post_excerpt'] = sanitize_textarea_field($request['excerpt']);
        }
        
        if (isset($request['parent_id'])) {
            $post_data['post_parent'] = intval($request['parent_id']);
        }
        
        if (isset($request['menu_order'])) {
            $post_data['menu_order'] = intval($request['menu_order']);
        }
        
        if (isset($request['slug'])) {
            $post_data['post_name'] = sanitize_title($request['slug']);
        }

        $post_id = wp_update_post($post_data);

        if (is_wp_error($post_id)) return $post_id;
        
        // Handle featured image
        if (isset($request['featured_image_id'])) {
            if ($request['featured_image_id']) {
                set_post_thumbnail($post_id, intval($request['featured_image_id']));
            } else {
                delete_post_thumbnail($post_id);
            }
        }
        
        // Handle page template
        if (isset($request['template'])) {
            update_post_meta($post_id, '_wp_page_template', sanitize_text_field($request['template']));
        }

        // Handle scheduled publish date
        if (isset($request['publish_date']) && !empty($request['publish_date'])) {
            $time = strtotime($request['publish_date']);
            if ($time) {
                wp_update_post(['ID' => $post_id, 'post_date' => date('Y-m-d H:i:s', $time)]);
            }
        }

        // Handle SEO meta
        if (isset($request['meta_title'])) {
            update_post_meta($post_id, '_wpfd_meta_title', sanitize_text_field($request['meta_title']));
        }
        if (isset($request['meta_description'])) {
            update_post_meta($post_id, '_wpfd_meta_description', sanitize_textarea_field($request['meta_description']));
        }
        if (isset($request['meta_keywords'])) {
            update_post_meta($post_id, '_wpfd_meta_keywords', sanitize_text_field($request['meta_keywords']));
        }

        // Handle page customization
        if (isset($request['page_bg_color'])) {
            update_post_meta($post_id, '_wpfd_bg_color', sanitize_text_field($request['page_bg_color']));
        }
        if (isset($request['page_text_color'])) {
            update_post_meta($post_id, '_wpfd_text_color', sanitize_text_field($request['page_text_color']));
        }

        // Handle gallery
        if (isset($request['gallery_ids']) && is_array($request['gallery_ids'])) {
            update_post_meta($post_id, '_wpfd_gallery_ids', array_map('intval', $request['gallery_ids']));
        }

        // Handle sections
        if (isset($request['sections']) && is_array($request['sections'])) {
            $sanitized_sections = array_map(function($section) {
                return [
                    'id' => sanitize_text_field($section['id'] ?? ''),
                    'title' => sanitize_text_field($section['title'] ?? ''),
                    'content' => wp_kses_post($section['content'] ?? '')
                ];
            }, $request['sections']);
            update_post_meta($post_id, '_wpfd_sections', $sanitized_sections);
        }

        // Handle password-protected visibility
        if (isset($request['visibility'])) {
            if ($request['visibility'] === 'password' && isset($request['post_password'])) {
                wp_update_post(['ID' => $post_id, 'post_password' => sanitize_text_field($request['post_password'])]);
            } else {
                wp_update_post(['ID' => $post_id, 'post_password' => '']);
            }
        }
        
        return ['id' => $post_id, 'message' => 'Page updated successfully'];
    }

    public function delete_item($request) {
        $page_id = intval($request['id']);
        $result = wp_delete_post($page_id, true);
        if (!$result) return new \WP_Error('delete_failed', 'Delete failed', ['status' => 500]);
        return ['message' => 'Page deleted successfully'];
    }
    
    public function get_templates() {
        $templates = wp_get_theme()->get_page_templates();
        $result = [['value' => 'default', 'label' => 'Default Template']];
        
        foreach ($templates as $filename => $template_name) {
            $result[] = [
                'value' => $filename,
                'label' => $template_name,
            ];
        }
        
        return $result;
    }

    public function get_parent_pages() {
        $args = [
            'post_type' => 'page',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
        ];

        if (is_multisite() && get_current_blog_id() !== 1) {
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key' => '_orabooks_default_page',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_orabooks_default_page',
                    'value' => '0',
                    'compare' => '=',
                ],
            ];
        }

        $query = new \WP_Query($args);
        $items = array_map(function($page_id) {
            return [
                'id' => $page_id,
                'title' => get_the_title($page_id),
            ];
        }, $query->posts ?: []);

        return $items;
    }

    public function get_authors() {
        $args = [
            'role__in' => ['administrator', 'editor', 'author', 'contributor'],
            'orderby' => 'display_name',
            'order' => 'ASC',
        ];

        $users = get_users($args);
        $items = array_map(function($user) {
            return [
                'id' => $user->ID,
                'name' => $user->display_name,
            ];
        }, $users);

        return $items;
    }

    public function validate_slug($request) {
        $slug = isset($request['slug']) ? sanitize_title($request['slug']) : '';
        $post_id = isset($request['id']) ? intval($request['id']) : 0;

        if ($slug === '') {
            return [
                'is_unique' => true,
                'unique_slug' => '',
            ];
        }

        $unique = wp_unique_post_slug($slug, $post_id, 'draft', 'page', 0);
        return [
            'is_unique' => $unique === $slug,
            'unique_slug' => $unique,
        ];
    }

    public function bulk_action($request) {
        $action = isset($request['action']) ? sanitize_text_field($request['action']) : '';
        $ids = isset($request['ids']) && is_array($request['ids']) ? array_map('intval', $request['ids']) : [];
        $status = isset($request['status']) ? sanitize_text_field($request['status']) : '';

        if (empty($action) || empty($ids)) {
            return new \WP_Error('invalid_request', 'Action and ids are required', ['status' => 400]);
        }

        $results = [];

        foreach ($ids as $id) {
            $page = get_post($id);
            if (!$page || $page->post_type !== 'page') {
                $results[] = ['id' => $id, 'status' => 'error', 'message' => 'Page not found'];
                continue;
            }

            if ($action === 'delete') {
                if (!current_user_can('delete_post', $id)) {
                    $results[] = ['id' => $id, 'status' => 'error', 'message' => 'Permission denied'];
                    continue;
                }
                $deleted = wp_delete_post($id, true);
                $results[] = $deleted ? ['id' => $id, 'status' => 'success'] : ['id' => $id, 'status' => 'error', 'message' => 'Delete failed'];
                continue;
            }

            if ($action === 'trash') {
                if (!current_user_can('delete_post', $id)) {
                    $results[] = ['id' => $id, 'status' => 'error', 'message' => 'Permission denied'];
                    continue;
                }
                $trashed = wp_trash_post($id);
                $results[] = $trashed ? ['id' => $id, 'status' => 'success'] : ['id' => $id, 'status' => 'error', 'message' => 'Trash failed'];
                continue;
            }

            if ($action === 'duplicate') {
                if (!current_user_can('create_posts')) {
                    $results[] = ['id' => $id, 'status' => 'error', 'message' => 'Permission denied'];
                    continue;
                }

                if (get_post_meta($id, '_orabooks_default_page', true)) {
                    $results[] = ['id' => $id, 'status' => 'error', 'message' => 'Cannot duplicate default pages'];
                    continue;
                }

                $new_page_data = [
                    'post_title' => $page->post_title . ' (Copy)',
                    'post_content' => $page->post_content,
                    'post_excerpt' => $page->post_excerpt,
                    'post_status' => 'draft',
                    'post_type' => 'page',
                    'post_author' => get_current_user_id(),
                ];

                $new_id = wp_insert_post($new_page_data, true);
                if (is_wp_error($new_id)) {
                    $results[] = ['id' => $id, 'status' => 'error', 'message' => $new_id->get_error_message()];
                    continue;
                }

                // Copy meta data
                $meta_keys = ['_wp_page_template', 'meta_title', 'meta_description', 'meta_keywords', 'page_bg_color', 'page_text_color'];
                foreach ($meta_keys as $key) {
                    $meta_value = get_post_meta($id, $key, true);
                    if ($meta_value) {
                        update_post_meta($new_id, $key, $meta_value);
                    }
                }

                $thumbnail_id = get_post_thumbnail_id($id);
                if ($thumbnail_id) {
                    set_post_thumbnail($new_id, $thumbnail_id);
                }

                $results[] = ['id' => $id, 'new_id' => $new_id, 'status' => 'success'];
                continue;
            }

            if ($action === 'status') {
                if (!current_user_can('edit_post', $id)) {
                    $results[] = ['id' => $id, 'status' => 'error', 'message' => 'Permission denied'];
                    continue;
                }
                if (!in_array($status, ['draft', 'publish', 'private'], true)) {
                    $results[] = ['id' => $id, 'status' => 'error', 'message' => 'Invalid status'];
                    continue;
                }

                $updated = wp_update_post([
                    'ID' => $id,
                    'post_status' => $status,
                ], true);

                $results[] = is_wp_error($updated)
                    ? ['id' => $id, 'status' => 'error', 'message' => $updated->get_error_message()]
                    : ['id' => $id, 'status' => 'success'];
                continue;
            }

            $results[] = ['id' => $id, 'status' => 'error', 'message' => 'Unknown action'];
        }

        return [
            'results' => $results,
        ];
    }

    public function duplicate_page($request) {
        $original_id = isset($request['id']) ? intval($request['id']) : 0;
        if (!$original_id) return new \WP_Error('invalid_id', 'Page ID is required', ['status' => 400]);

        $original = get_post($original_id);
        if (!$original || $original->post_type !== 'page') {
            return new \WP_Error('not_found', 'Page not found', ['status' => 404]);
        }

        if (get_post_meta($original_id, '_orabooks_default_page', true) && !current_user_can('edit_post', $original_id)) {
            return new \WP_Error('permission_denied', 'Cannot duplicate default pages', ['status' => 403]);
        }

        $new_page_data = [
            'post_title' => $original->post_title . ' (Copy)',
            'post_content' => $original->post_content,
            'post_excerpt' => $original->post_excerpt,
            'post_status' => 'draft',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
            'meta_input' => [],
        ];

        $new_id = wp_insert_post($new_page_data);
        if (is_wp_error($new_id)) return $new_id;

        // Copy meta data
        $meta_keys = ['_wp_page_template', 'meta_title', 'meta_description', 'meta_keywords', 'page_bg_color', 'page_text_color'];
        foreach ($meta_keys as $key) {
            $meta_value = get_post_meta($original_id, $key, true);
            if ($meta_value) {
                update_post_meta($new_id, $key, $meta_value);
            }
        }

        // Copy featured image
        $thumbnail_id = get_post_thumbnail_id($original_id);
        if ($thumbnail_id) {
            set_post_thumbnail($new_id, $thumbnail_id);
        }

        return ['id' => $new_id, 'title' => $new_page_data['post_title'], 'message' => 'Page duplicated successfully'];
    }

    public function trash_page($request) {
        $page_id = isset($request['id']) ? intval($request['id']) : 0;
        if (!$page_id) return new \WP_Error('invalid_id', 'Page ID is required', ['status' => 400]);

        $page = get_post($page_id);
        if (!$page || $page->post_type !== 'page') {
            return new \WP_Error('not_found', 'Page not found', ['status' => 404]);
        }

        $trashed = wp_trash_post($page_id);
        if (!$trashed) {
            return new \WP_Error('trash_failed', 'Failed to move page to trash', ['status' => 500]);
        }

        return ['id' => $page_id, 'status' => 'trashed', 'message' => 'Page moved to trash'];
    }

    public function restore_page($request) {
        $page_id = isset($request['id']) ? intval($request['id']) : 0;
        if (!$page_id) return new \WP_Error('invalid_id', 'Page ID is required', ['status' => 400]);

        $page = get_post($page_id);
        if (!$page || $page->post_type !== 'page') {
            return new \WP_Error('not_found', 'Page not found', ['status' => 404]);
        }

        $restored = wp_untrash_post($page_id);
        if (!$restored) {
            return new \WP_Error('restore_failed', 'Failed to restore page', ['status' => 500]);
        }

        return ['id' => $page_id, 'status' => 'restored', 'message' => 'Page restored from trash'];
    }

    public function get_revisions($request) {
        $page_id = isset($request['id']) ? intval($request['id']) : 0;
        if (!$page_id) return new \WP_Error('invalid_id', 'Page ID is required', ['status' => 400]);

        $page = get_post($page_id);
        if (!$page || $page->post_type !== 'page') {
            return new \WP_Error('not_found', 'Page not found', ['status' => 404]);
        }

        $revisions = wp_get_post_revisions($page_id, ['order' => 'DESC']);
        
        $items = array_map(function($rev) {
            $author = get_userdata($rev->post_author);
            return [
                'id' => $rev->ID,
                'parent_id' => $rev->post_parent,
                'author' => $rev->post_author,
                'author_name' => $author ? $author->display_name : 'Unknown',
                'date' => $rev->post_date_gmt,
                'title' => $rev->post_title,
                'excerpt' => substr($rev->post_content, 0, 100),
            ];
        }, $revisions);

        return ['revisions' => $items];
    }

    public function restore_revision($request) {
        $page_id = isset($request['id']) ? intval($request['id']) : 0;
        $revision_id = isset($request['revision_id']) ? intval($request['revision_id']) : 0;

        if (!$page_id || !$revision_id) {
            return new \WP_Error('invalid_ids', 'Page ID and Revision ID are required', ['status' => 400]);
        }

        $revision = get_post($revision_id);
        if (!$revision || $revision->post_type !== 'revision') {
            return new \WP_Error('not_found', 'Revision not found', ['status' => 404]);
        }

        $restored = wp_restore_post_revision($revision_id);
        if (!$restored) {
            return new \WP_Error('restore_failed', 'Failed to restore revision', ['status' => 500]);
        }

        return ['id' => $page_id, 'revision_id' => $revision_id, 'message' => 'Page restored to this revision'];
    }

    public function get_preview_link($request) {
        $page_id = isset($request['id']) ? intval($request['id']) : 0;
        if (!$page_id) return new \WP_Error('invalid_id', 'Page ID is required', ['status' => 400]);

        $page = get_post($page_id);
        if (!$page || $page->post_type !== 'page') {
            return new \WP_Error('not_found', 'Page not found', ['status' => 404]);
        }

        // Generate a preview URL
        $preview_url = get_permalink($page_id);
        if ($page->post_status !== 'publish') {
            // For unpublished pages, use preview link
            $preview_url = add_query_arg('preview', 'true', get_permalink($page_id));
        }

        return [
            'preview_url' => $preview_url,
            'page_id' => $page_id,
            'status' => $page->post_status,
        ];
    }
}
