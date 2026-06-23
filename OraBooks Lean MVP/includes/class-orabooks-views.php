<?php
/**
 * PHP view renderer for OraBooks frontend/admin templates.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Views {
    /**
     * Resolve a view file path with legacy aliases and normalized slashes.
     *
     * @param string $view Relative path without .php (e.g. frontend/react-app).
     * @return string|null Absolute path when found.
     */
    public static function resolve_view_file($view) {
        if (!defined('ORABOOKS_PLUGIN_DIR')) {
            return null;
        }

        $view = str_replace('\\', '/', trim((string) $view, '/'));
        if ($view === '') {
            return null;
        }

        $base = wp_normalize_path(trailingslashit(ORABOOKS_PLUGIN_DIR) . 'includes/views/');
        $candidates = [$view];

        if (strpos($view, 'frontend/') !== 0 && strpos($view, 'admin/') !== 0) {
            $candidates[] = 'frontend/' . $view;
        }

        if ($view === 'react-app' || $view === 'frontend/react-app') {
            $candidates[] = 'frontend/react-app';
            $candidates[] = 'react-app';
        }

        $candidates = array_values(array_unique($candidates));

        foreach ($candidates as $candidate) {
            $file = wp_normalize_path($base . $candidate . '.php');
            if (is_readable($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Whether a view template exists on disk.
     */
    public static function exists($view) {
        return self::resolve_view_file($view) !== null;
    }

    /**
     * Render a view file from includes/views/.
     *
     * @param string $view Relative path without .php (e.g. frontend/login).
     * @param array  $vars Variables extracted into the view scope.
     */
    public static function render($view, $vars = []) {
        $file = self::resolve_view_file($view);
        if ($file === null) {
            return '<div class="orabooks-message error" style="display:block;">' .
                esc_html(sprintf(__('OraBooks view missing: %s', 'orabooks'), $view)) .
                '</div>';
        }

        if (!empty($vars)) {
            extract($vars, EXTR_SKIP);
        }

        ob_start();
        include $file;
        return ob_get_clean();
    }

    public static function require_login_message($redirect_path = '/login/') {
        return '<div class="orabooks-auth-shell"><div class="orabooks-form-container">' .
            '<p>' . esc_html__('Please log in to continue.', 'orabooks') . '</p>' .
            '<p><a class="orabooks-btn orabooks-btn-primary" href="' . esc_url(orabooks_get_network_login_url('login')) . '">' .
            esc_html__('Log in', 'orabooks') . '</a></p></div></div>';
    }
}
