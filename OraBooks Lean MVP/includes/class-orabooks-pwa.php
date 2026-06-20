<?php
/**
 * OraBooks Progressive Web App (SL-028 mobile / offline receipt queue)
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Pwa {

    public static function init() {
        add_action('wp_head', [__CLASS__, 'output_head_tags'], 5);
        add_filter('orabooks_ajax_config', [__CLASS__, 'extend_ajax_config']);
    }

    public static function is_enabled() {
        return apply_filters('orabooks_pwa_enabled', true);
    }

    public static function asset_url($file) {
        return ORABOOKS_PLUGIN_URL . 'assets/pwa/' . ltrim($file, '/');
    }

    public static function get_manifest() {
        $start = home_url('/');

        return [
            'name'             => 'OraBooks',
            'short_name'       => 'OraBooks',
            'description'      => 'Multi-tenant accounting — receipts, expenses, and approvals on the go.',
            'start_url'        => $start,
            'scope'            => $start,
            'display'          => 'standalone',
            'orientation'      => 'portrait-primary',
            'background_color' => '#f4f8fc',
            'theme_color'      => '#1A69B4',
            'categories'       => ['finance', 'business', 'productivity'],
            'icons'            => [
                [
                    'src'     => self::asset_url('icons/icon.svg'),
                    'sizes'   => 'any',
                    'type'    => 'image/svg+xml',
                    'purpose' => 'any maskable',
                ],
            ],
            'shortcuts'        => [
                [
                    'name'        => 'Expenses',
                    'short_name'  => 'Expenses',
                    'description' => 'Scan or upload a receipt',
                    'url'         => trailingslashit($start) . '#/expenses',
                ],
            ],
        ];
    }

    public static function extend_ajax_config($config) {
        if (!self::is_enabled()) {
            return $config;
        }

        $config['pwa'] = [
            'enabled'            => true,
            'manifest_url'       => rest_url('api/pwa/manifest'),
            'service_worker_url' => self::asset_url('service-worker.js'),
            'service_worker_scope' => self::asset_url(''),
            'offline_queue'      => true,
        ];

        return $config;
    }

    public static function output_head_tags() {
        if (!self::is_enabled() || !is_singular('page')) {
            return;
        }

        $post = get_post();
        if (!$post || strpos($post->post_content, '[orabooks_') === false) {
            return;
        }

        $manifest = esc_url(rest_url('api/pwa/manifest'));
        $theme = esc_attr('#1A69B4');

        echo '<link rel="manifest" href="' . $manifest . '" />' . "\n";
        echo '<meta name="theme-color" content="' . $theme . '" />' . "\n";
        echo '<meta name="mobile-web-app-capable" content="yes" />' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes" />' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="default" />' . "\n";
    }

    public static function rest_manifest() {
        return rest_ensure_response(self::get_manifest());
    }
}
