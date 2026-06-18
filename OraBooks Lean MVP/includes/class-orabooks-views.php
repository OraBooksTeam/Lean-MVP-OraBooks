<?php
/**
 * PHP view renderer for OraBooks frontend/admin templates.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Views {
    /**
     * Render a view file from includes/views/.
     *
     * @param string $view Relative path without .php (e.g. frontend/login).
     * @param array  $vars Variables extracted into the view scope.
     */
    public static function render($view, $vars = []) {
        $file = ORABOOKS_PLUGIN_DIR . 'includes/views/' . $view . '.php';
        if (!file_exists($file)) {
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
            '<p><a class="orabooks-btn orabooks-btn-primary" href="' . esc_url(home_url($redirect_path)) . '">' .
            esc_html__('Log in', 'orabooks') . '</a></p></div></div>';
    }
}
