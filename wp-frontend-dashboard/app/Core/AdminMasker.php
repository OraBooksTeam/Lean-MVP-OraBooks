<?php
namespace WPFD\Core;

/**
 * AdminMasker - Hides all WordPress identity/branding from wp-admin pages
 * for client (non-super-admin) dashboard users.
 *
 * Covers: CSS injection, PHP text filters, meta tag removal, login rebranding,
 * admin bar cleanup, footer replacement, and head cleanup.
 */
class AdminMasker {

    private $brand_name = '';

    public function register() {
        // Defer all hooks to 'init' so user functions (is_super_admin, etc.) are available
        add_action('init', [$this, 'init_hooks']);
    }

    public function init_hooks() {
        // Resolve the brand name from the site title
        $this->brand_name = get_bloginfo('name') ?: 'Dashboard';

        // 1. Enqueue masking CSS on admin pages
        add_action('admin_enqueue_scripts', [$this, 'enqueue_mask_css']);

        // 2. Login page CSS enqueuing removed to use WordPress default styling
        // add_action('login_enqueue_scripts', [$this, 'enqueue_mask_css']);
        
        // 2.1. Dequeue any wpfd CSS on login page
        add_action('login_enqueue_scripts', function() {
            wp_dequeue_style('wpfd-admin-mask');
            wp_dequeue_style('wpfd-admin-custom-theme');
            wp_dequeue_style('wpfd-tailwind');
        }, 999);
        
        // 2.2. Remove wpfd styles from login page using wp_head
        add_action('login_head', function() {
            wp_dequeue_style('wpfd-admin-mask');
            wp_dequeue_style('wpfd-admin-custom-theme');
            wp_dequeue_style('wpfd-tailwind');
            
            // Remove any inline styles that might be affecting the login page
            echo '<style>
                .login {
                    background: #f1f1f1 !important;
                }
                .login form {
                    background: #fff !important;
                    border: 1px solid #ccd0d4 !important;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.04) !important;
                }
                .login h1 a {
                    background-image: url(/wp-admin/images/w-logo-blue.png) !important;
                    background-size: 84px !important;
                    width: 84px !important;
                    height: 84px !important;
                }
            </style>';
        }, 1);

        // 3. Replace admin footer text
        add_filter('admin_footer_text', [$this, 'replace_footer_text'], 999);
        add_filter('update_footer', [$this, 'replace_footer_version'], 999);

        // 4. Remove WordPress generator meta tag
        remove_action('wp_head', 'wp_generator');
        add_filter('the_generator', '__return_empty_string');

        // 5. Remove WordPress meta/links from <head>
        $this->remove_head_links();

        // 6. Replace "Howdy" greeting in admin bar
        add_filter('admin_bar_menu', [$this, 'replace_howdy_greeting'], 999);

        // 7. Login page logo modifications removed to use WordPress defaults
        // add_filter('login_headerurl', [$this, 'replace_login_logo_url']);
        // add_filter('login_headertext', [$this, 'replace_login_logo_title']);

        // 8. Replace admin page titles that contain "WordPress"
        add_filter('admin_title', [$this, 'replace_admin_title'], 999, 2);
        add_filter('document_title_parts', [$this, 'replace_document_title_parts'], 999);

        // 9. Remove WordPress version from right-now dashboard widget
        add_filter('update_right_now_text', [$this, 'replace_right_now_text'], 999);

        // 10. Remove WordPress from update core messages
        add_filter('update_core_overrides', [$this, 'suppress_core_update_notices'], 999);

        // 11. Remove emoji scripts that leak WordPress
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');

        // 12. Remove REST API link from <head>
        remove_action('wp_head', 'rest_output_link_wp_head', 10);
        remove_action('template_redirect', 'rest_output_link_header', 11);

        // 13. Remove oEmbed discovery links
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');

        // 14. Remove X-Pingback header
        add_filter('wp_headers', [$this, 'remove_pingback_header']);

        // 15. Replace login page title
        add_filter('login_title', [$this, 'replace_login_title'], 999);

        // 16. Remove admin bar "About WordPress" node
        add_action('admin_bar_menu', [$this, 'remove_wp_logo_node'], 999);

        // 17. Remove "WordPress" from update messages
        add_filter('gettext', [$this, 'replace_wp_text_strings'], 999, 3);
        add_filter('ngettext', [$this, 'replace_wp_text_strings'], 999, 4);

        // 18. Hide dashboard widgets that reveal WordPress
        add_action('wp_dashboard_setup', [$this, 'remove_dashboard_widgets']);

        // 19. Remove welcome panel
        remove_action('welcome_panel', 'wp_welcome_panel');

        // 20. Remove admin color scheme picker from profile page
        remove_action('admin_color_scheme_picker', 'admin_color_scheme_picker');

        // 21. Replace site health "WordPress" references
        add_filter('site_status_tests', [$this, 'filter_site_health_tests'], 999);

        // 22. Remove RSS feed links that expose WordPress
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);

        // 23. Remove wp-embed script
        remove_action('wp_head', 'wp_enqueue_embed_script', 1);

        // 24. Remove global dashboard widget
        add_filter('wp_dashboard_widgets', [$this, 'filter_dashboard_widgets'], 999);

        // 25. Remove favicon for non-admins
        add_filter('get_site_icon_url', '__return_empty_string', 999);
        
        // 26. Remove WP version from scripts/styles
        add_filter('style_loader_src', [$this, 'remove_wp_version'], 9999);
        add_filter('script_loader_src', [$this, 'remove_wp_version'], 9999);
    }

    /**
     * Remove WP version from scripts and styles
     */
    public function remove_wp_version($src) {
        if (strpos($src, 'ver=' . get_bloginfo('version'))) {
            $src = remove_query_arg('ver', $src);
        }
        return $src;
    }

    /**
     * Enqueue the wp-admin-mask.css stylesheet.
     */
    public function enqueue_mask_css() {
        global $pagenow;
        // Don't load custom CSS for super-admins (keep default WP admin appearance)
        if (is_super_admin()) {
            return;
        }

        // Don't load custom CSS on login page
        if ($pagenow === 'wp-login.php') {
            return;
        }
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false) {
            return;
        }
        if (function_exists('login_header')) {
            return;
        }
        
        $base_dir = dirname(dirname(dirname(__FILE__)));
        $assets_url = plugin_dir_url($base_dir . '/index.php') . 'assets/';
        wp_enqueue_style(
            'wpfd-admin-mask',
            $assets_url . 'css/wp-admin-mask.css',
            [],
            '1.0.0'
        );

        // Enqueue custom theme CSS for complete reskin
        wp_enqueue_style(
            'wpfd-admin-custom-theme',
            $assets_url . 'css/wp-admin-custom-theme.css',
            ['wpfd-admin-mask'], // Load after mask CSS
            '1.0.0'
        );

        // Inline CSS to replace login logo with brand name
        $custom_css = "
            .login .wpfd-login-brand {
                text-align: center;
                margin-bottom: 20px;
            }
            .login .wpfd-login-brand h1 {
                font-size: 28px;
                font-weight: 700;
                color: #1e293b;
                margin: 0;
                padding: 30px 0 10px 0;
            }
            body.login {
                background: #f8fafc;
            }
            #loginform,
            #registerform,
            #lostpasswordform {
                border-radius: 12px;
                border: 1px solid #e2e8f0;
                box-shadow: 0 8px 30px rgba(0,0,0,0.04);
            }
        ";
        wp_add_inline_style('wpfd-admin-mask', $custom_css);
    }

    /**
     * Replace admin footer "Thank you for creating with WordPress" text.
     */
    public function replace_footer_text($text) {
        return '';
    }

    /**
     * Replace admin footer version number.
     */
    public function replace_footer_version($text) {
        return '';
    }

    /**
     * Remove WordPress <head> links that expose the CMS.
     */
    private function remove_head_links() {
        // RSD (Really Simple Discovery) link
        remove_action('wp_head', 'rsd_link');

        // WLW Manifest link
        remove_action('wp_head', 'wlwmanifest_link');

        // WordPress version in head
        remove_action('wp_head', 'wp_generator');

        // REST API link
        remove_action('wp_head', 'rest_output_link_wp_head', 10);

        // Shortlink
        remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);

        // Adjacent posts rel links
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);

        // Parent post link
        remove_action('wp_head', 'parent_post_rel_link', 10, 2);
    }

    /**
     * Replace "Howdy" greeting in admin bar with a simple greeting.
     */
    public function replace_howdy_greeting($wp_admin_bar) {
        $my_account = $wp_admin_bar->get_node('my-account');
        if ($my_account) {
            $new_text = str_replace('Howdy, ', 'Hello, ', $my_account->title);
            $wp_admin_bar->add_node([
                'id' => 'my-account',
                'title' => $new_text,
            ]);
        }
    }

    /**
     * Replace login logo URL to point to the site home instead of wordpress.org.
     */
    public function replace_login_logo_url($url) {
        return home_url('/');
    }

    /**
     * Replace login logo title attribute.
     */
    public function replace_login_logo_title($title) {
        return $this->brand_name;
    }

    /**
     * Replace login page <title> text.
     */
    public function replace_login_title($title) {
        return $this->brand_name . ' - Login';
    }

    /**
     * Replace admin page title - remove "WordPress" from title.
     */
    public function replace_admin_title($admin_title, $title) {
        $admin_title = str_ireplace('WordPress', $this->brand_name, $admin_title);
        $title = str_ireplace('WordPress', $this->brand_name, $title);
        return $admin_title;
    }

    /**
     * Replace document title parts - remove "WordPress" from any part.
     */
    public function replace_document_title_parts($title) {
        if (isset($title['site'])) {
            $title['site'] = str_ireplace('WordPress', $this->brand_name, $title['site']);
        }
        if (isset($title['title'])) {
            $title['title'] = str_ireplace('WordPress', $this->brand_name, $title['title']);
        }
        if (isset($title['tagline'])) {
            $title['tagline'] = str_ireplace('WordPress', $this->brand_name, $title['tagline']);
        }
        return $title;
    }

    /**
     * Replace "WordPress" in the Right Now dashboard widget text.
     */
    public function replace_right_now_text($content) {
        return str_ireplace('WordPress', $this->brand_name, $content);
    }

    /**
     * Suppress core update notices for non-super-admins.
     */
    public function suppress_core_update_notices($override) {
        return true;
    }

    /**
     * Remove X-Pingback header from HTTP responses.
     */
    public function remove_pingback_header($headers) {
        if (isset($headers['X-Pingback'])) {
            unset($headers['X-Pingback']);
        }
        return $headers;
    }

    /**
     * Remove the WP logo node from the admin bar entirely.
     */
    public function remove_wp_logo_node($wp_admin_bar) {
        $wp_admin_bar->remove_node('wp-logo');
        $wp_admin_bar->remove_node('about');
        $wp_admin_bar->remove_node('wporg');
        $wp_admin_bar->remove_node('documentation');
        $wp_admin_bar->remove_node('support-forums');
        $wp_admin_bar->remove_node('feedback');
    }

    /**
     * Replace WordPress text strings in gettext translations.
     */
    public function replace_wp_text_strings($translation, $text, $domain, $plural = null) {
        // Only target default WordPress text domain and core
        if ($domain !== 'default') {
            return $translation;
        }

        $replacements = [
            'Thank you for creating with WordPress.' => '',
            'Proudly powered by WordPress' => '',
            'Powered by WordPress' => '',
            'WordPress' => $this->brand_name,
        ];

        foreach ($replacements as $search => $replace) {
            if ($translation === $search || $text === $search) {
                return $replace;
            }
            // Also handle partial matches in longer strings
            if (strpos($translation, $search) !== false) {
                $translation = str_replace($search, $replace, $translation);
            }
        }

        return $translation;
    }

    /**
     * Remove dashboard widgets that expose WordPress branding.
     */
    public function remove_dashboard_widgets() {
        // WordPress Events and News
        remove_meta_box('dashboard_primary', 'dashboard', 'side');

        // WordPress Planet feed
        remove_meta_box('dashboard_secondary', 'dashboard', 'side');

        // Quick Draft (mentions WordPress)
        remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
    }

    /**
     * Filter dashboard widgets array to remove WordPress-branded ones.
     */
    public function filter_dashboard_widgets($widgets) {
        unset($widgets['dashboard_primary']);
        unset($widgets['dashboard_secondary']);
        return $widgets;
    }

    /**
     * Filter site health tests to remove WordPress-branded checks.
     */
    public function filter_site_health_tests($tests) {
        // Remove "WordPress version" check from site status
        if (isset($tests['direct']['wordpress_version'])) {
            unset($tests['direct']['wordpress_version']);
        }
        if (isset($tests['async']['wordpress_version'])) {
            unset($tests['async']['wordpress_version']);
        }
        return $tests;
    }
}
