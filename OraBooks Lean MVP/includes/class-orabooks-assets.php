<?php
/**
 * React bundle enqueue helpers for OraBooks frontend and wp-admin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Assets {
    /** @var bool */
    private static $frontend_shortcode_rendered = false;

    /**
     * Called when an OraBooks frontend shortcode renders (late asset fallback).
     */
    public static function mark_frontend_shortcode_rendered() {
        self::$frontend_shortcode_rendered = true;

        if (!did_action('wp_enqueue_scripts')) {
            return;
        }

        if (wp_script_is('orabooks-react-frontend', 'enqueued') || wp_script_is('orabooks-react-frontend', 'done')) {
            return;
        }

        self::enqueue_frontend_react(self::get_ajax_config('frontend'));
    }

    /**
     * Print styles in the footer when shortcodes rendered after wp_head.
     */
    public static function print_late_frontend_styles() {
        if (!self::$frontend_shortcode_rendered) {
            return;
        }

        $handles = ['orabooks-frontend', 'orabooks-theme-compat', 'orabooks-react', 'orabooks-divi-compat'];
        foreach ($handles as $handle) {
            if (wp_style_is($handle, 'enqueued') && !wp_style_is($handle, 'done')) {
                wp_print_styles($handle);
            }
        }
    }

    /**
     * Last-chance script enqueue if a shortcode rendered but assets were missed.
     */
    public static function maybe_enqueue_missed_frontend_assets() {
        if (!self::$frontend_shortcode_rendered) {
            return;
        }

        if (wp_script_is('orabooks-react-frontend', 'enqueued') || wp_script_is('orabooks-react-frontend', 'done')) {
            return;
        }

        self::enqueue_frontend_react(self::get_ajax_config('frontend'));
    }

    /**
     * WordPress script handles that load the React bundles.
     *
     * @return string[]
     */
    public static function get_react_script_handles() {
        return [
            'orabooks-react-frontend',
            'orabooks-react-admin',
        ];
    }

    /**
     * Load React bundles as classic scripts (IIFE output, no import.meta).
     *
     * @param string $tag    Script tag HTML.
     * @param string $handle Script handle.
     * @param string $src    Script source URL.
     */
    public static function filter_react_script_tag($tag, $handle, $src) {
        if (!in_array($handle, self::get_react_script_handles(), true)) {
            return $tag;
        }

        if (strpos($tag, ' type=') === false) {
            $tag = str_replace('<script ', '<script defer ', $tag);
        }

        return $tag;
    }

    /**
     * Shortcodes that mount the React frontend SPA.
     *
     * @return string[]
     */
    public static function get_react_shortcode_tags() {
        return [
            'orabooks_login',
            'orabooks_register',
            'orabooks_reset_password',
            'orabooks_tier_selection',
            'orabooks_dashboard',
            'orabooks_customers',
            'orabooks_vendors',
            'orabooks_inventory',
            'orabooks_bank_reconciliation',
            'orabooks_reports',
            'orabooks_csv_import',
            'orabooks_team',
            'orabooks_attachments',
            'orabooks_invoices',
            'orabooks_chart_of_accounts',
            'orabooks_fiscal_periods',
            'orabooks_tax_settings',
            'orabooks_journals',
            'orabooks_profile',
            'orabooks_approvals',
            'orabooks_ai_review',
            'orabooks_expenses',
            'orabooks_voice',
            'orabooks_partner_onboarding',
            'orabooks_partner_dashboard',
            'orabooks_commission',
            'orabooks_notification_center',
            'orabooks_notification_preferences',
            'orabooks_export_status',
            'orabooks_verify_email',
            'orabooks_commission_admin',
            'orabooks_notification_admin',
            'orabooks_async_queue_dashboard',
            'orabooks_observability_dashboard',
            'orabooks_export_button',
        ];
    }

    /**
     * Shortcodes that still rely on legacy jQuery frontend.js.
     *
     * @return string[]
     */
    public static function get_legacy_shortcode_tags() {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function get_react_manifest() {
        static $manifest = null;

        if ($manifest !== null) {
            return $manifest;
        }

        $path = ORABOOKS_PLUGIN_DIR . 'assets/react/deploy-manifest.json';
        if (!file_exists($path)) {
            return $manifest = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        return $manifest = is_array($decoded) ? $decoded : [];
    }

    public static function react_bundle_url($file) {
        return ORABOOKS_PLUGIN_URL . 'assets/react/' . ltrim($file, '/');
    }

    public static function get_react_stylesheet() {
        $files = self::get_react_manifest()['files'] ?? [];

        foreach ($files as $file) {
            if (preg_match('/\.css$/i', $file)) {
                return $file;
            }
        }

        return 'assets/style-gwG2VvaX.css';
    }

    /**
     * @param string $content Post content.
     */
    public static function should_enqueue_frontend_react($content) {
        if (function_exists('orabooks_should_use_react_frontend') && !orabooks_should_use_react_frontend()) {
            return false;
        }

        foreach (self::get_react_shortcode_tags() as $tag) {
            if (has_shortcode($content, $tag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @deprecated Legacy PHP frontend removed; Lean MVP uses React only.
     *
     * @param string $content Post content.
     */
    public static function should_enqueue_php_frontend($content) {
        return false;
    }

    /**
     * @param string $content Post content.
     */
    public static function should_enqueue_legacy_frontend($content) {
        foreach (self::get_legacy_shortcode_tags() as $tag) {
            if (has_shortcode($content, $tag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $context frontend|admin
     * @return array<string, mixed>
     */
    public static function get_ajax_config($context = 'frontend') {
        $config = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('orabooks_nonce'),
            'current_user_id' => get_current_user_id(),
            'logout_url' => wp_logout_url(home_url('/login/')),
            'admin_base' => admin_url('admin.php'),
            'is_admin' => current_user_can('manage_options'),
            'accounting_url' => home_url('/dashboard/'),
        ];

        if ($context === 'admin' && function_exists('orabooks_get_admin_nav_items')) {
            $config['admin_nav'] = orabooks_get_admin_nav_items();
        }

        return apply_filters('orabooks_ajax_config', $config);
    }

    /**
     * @param array<string, mixed>|null $ajax_config
     */
    public static function enqueue_frontend_react($ajax_config = null) {
        $ajax_config = $ajax_config ?: self::get_ajax_config('frontend');
        $style = self::get_react_stylesheet();
        $version = ORABOOKS_VERSION . '-' . (self::get_react_manifest()['generated_at'] ?? 'dev');

        wp_enqueue_style(
            'orabooks-react',
            self::react_bundle_url($style),
            ['orabooks-frontend'],
            $version
        );

        wp_enqueue_script(
            'orabooks-react-frontend',
            self::react_bundle_url('frontend.js'),
            [],
            $version,
            true
        );
        wp_localize_script('orabooks-react-frontend', 'orabooks_ajax', $ajax_config);

        self::enqueue_theme_compat();

        if (function_exists('orabooks_is_divi_theme') && orabooks_is_divi_theme()) {
            self::enqueue_divi_compat();
        }
    }

    /**
     * Universal theme overrides for OraBooks pages.
     */
    public static function enqueue_theme_compat() {
        $deps = ['orabooks-frontend'];
        if (wp_style_is('orabooks-react', 'registered') || wp_style_is('orabooks-react', 'enqueued')) {
            $deps[] = 'orabooks-react';
        }

        wp_enqueue_style(
            'orabooks-theme-compat',
            ORABOOKS_PLUGIN_URL . 'assets/css/theme-compat.css',
            $deps,
            ORABOOKS_VERSION
        );
    }

    /**
     * Divi-specific overrides (extends theme-compat).
     */
    public static function enqueue_divi_compat() {
        $deps = ['orabooks-theme-compat'];
        if (!wp_style_is('orabooks-theme-compat', 'enqueued') && !wp_style_is('orabooks-theme-compat', 'registered')) {
            $deps = ['orabooks-frontend'];
            if (wp_style_is('orabooks-react', 'registered') || wp_style_is('orabooks-react', 'enqueued')) {
                $deps[] = 'orabooks-react';
            }
        }

        wp_enqueue_style(
            'orabooks-divi-compat',
            ORABOOKS_PLUGIN_URL . 'assets/css/divi-compat.css',
            $deps,
            ORABOOKS_VERSION
        );
    }

    /**
     * @param array<string, mixed>|null $ajax_config
     */
    public static function enqueue_admin_react($ajax_config = null) {
        $ajax_config = $ajax_config ?: self::get_ajax_config('admin');
        $style = self::get_react_stylesheet();
        $version = ORABOOKS_VERSION . '-' . (self::get_react_manifest()['generated_at'] ?? 'dev');

        wp_enqueue_style(
            'orabooks-react',
            self::react_bundle_url($style),
            ['orabooks-admin', 'orabooks-frontend'],
            $version
        );

        wp_enqueue_script(
            'orabooks-react-admin',
            self::react_bundle_url('admin.js'),
            [],
            $version,
            true
        );
        wp_localize_script('orabooks-react-admin', 'orabooks_ajax', $ajax_config);
    }

    /**
     * @param array<string, mixed>|null $ajax_config
     */
    public static function enqueue_legacy_admin_scripts($ajax_config = null) {
        $ajax_config = $ajax_config ?: self::get_ajax_config('admin');

        wp_enqueue_script(
            'orabooks-admin-legacy',
            ORABOOKS_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            ORABOOKS_VERSION,
            true
        );
        wp_localize_script('orabooks-admin-legacy', 'orabooks_ajax', $ajax_config);

        wp_enqueue_script(
            'orabooks-frontend-jquery',
            ORABOOKS_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery', 'orabooks-admin-legacy'],
            ORABOOKS_VERSION,
            true
        );
        wp_localize_script('orabooks-frontend-jquery', 'orabooks_ajax', $ajax_config);
    }

    /**
     * @param array<string, mixed>|null $ajax_config
     */
    public static function enqueue_legacy_frontend_scripts($ajax_config = null) {
        $ajax_config = $ajax_config ?: self::get_ajax_config('frontend');

        wp_enqueue_script(
            'orabooks-frontend-legacy',
            ORABOOKS_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            ORABOOKS_VERSION,
            true
        );
        wp_localize_script('orabooks-frontend-legacy', 'orabooks_ajax', $ajax_config);
    }

    /**
     * @param string $hook_suffix
     */
    public static function should_enqueue_admin_react($hook_suffix) {
        return strpos($hook_suffix, 'orabooks') !== false;
    }
}
