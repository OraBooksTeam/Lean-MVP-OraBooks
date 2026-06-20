<?php
/**
 * OraBooks Shortcodes
 * 
 * Frontend-facing shortcodes for login, registration, partner onboarding, etc.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Shortcodes {
    
    private static $instance = null;
    
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            add_shortcode('orabooks_login', [self::$instance, 'login_form']);
            add_shortcode('orabooks_register', [self::$instance, 'register_form']);
            add_shortcode('orabooks_verify_email', [self::$instance, 'verify_email']);
            add_shortcode('orabooks_reset_password', [self::$instance, 'reset_password']);
            add_shortcode('orabooks_partner_onboarding', [self::$instance, 'partner_onboarding']);
            add_shortcode('orabooks_tier_selection', [self::$instance, 'tier_selection']);
            add_shortcode('orabooks_dashboard', [self::$instance, 'dashboard']);
            add_shortcode('orabooks_commission', [self::$instance, 'commission_dashboard']);
            add_shortcode('orabooks_commission_admin', [self::$instance, 'commission_admin']);
            add_shortcode('orabooks_partner_dashboard', [self::$instance, 'partner_dashboard']);
            add_shortcode('orabooks_notification_center', [self::$instance, 'notification_center']);
            add_shortcode('orabooks_notification_preferences', [self::$instance, 'notification_preferences']);
            add_shortcode('orabooks_notification_admin', [self::$instance, 'notification_admin']);
            add_shortcode('orabooks_async_queue_dashboard', [self::$instance, 'async_queue_dashboard']);
            add_shortcode('orabooks_observability_dashboard', [self::$instance, 'observability_dashboard']);
            add_shortcode('orabooks_export_status', [self::$instance, 'export_status']);
            add_shortcode('orabooks_csv_import', [self::$instance, 'csv_import_page']);
            add_shortcode('orabooks_team', [self::$instance, 'team_page']);
            add_shortcode('orabooks_attachments', [self::$instance, 'attachments_page']);
            add_shortcode('orabooks_approvals', [self::$instance, 'approvals_page']);
            add_shortcode('orabooks_ai_review', [self::$instance, 'ai_review_page']);
            add_shortcode('orabooks_expenses', [self::$instance, 'expenses_page']);
            add_shortcode('orabooks_voice', [self::$instance, 'voice_page']);
            add_shortcode('orabooks_export_button', [self::$instance, 'export_button']);
            add_shortcode('orabooks_customers', [self::$instance, 'customers_page']);
            add_shortcode('orabooks_vendors', [self::$instance, 'vendors_page']);
            add_shortcode('orabooks_inventory', [self::$instance, 'inventory_page']);
            add_shortcode('orabooks_bank_reconciliation', [self::$instance, 'bank_reconciliation_page']);
            add_shortcode('orabooks_reports', [self::$instance, 'reports_page']);
            add_shortcode('orabooks_invoices', [self::$instance, 'invoices_page']);
            add_shortcode('orabooks_chart_of_accounts', [self::$instance, 'chart_of_accounts_page']);
            add_shortcode('orabooks_fiscal_periods', [self::$instance, 'fiscal_periods_page']);
            add_shortcode('orabooks_tax_settings', [self::$instance, 'tax_settings_page']);
            add_shortcode('orabooks_journals', [self::$instance, 'journals_page']);
            add_shortcode('orabooks_profile', [self::$instance, 'profile_page']);
        }
        return self::$instance;
    }

    private function render_view($view, $vars = []) {
        return OraBooks_Views::render('frontend/' . $view, $vars);
    }

    /**
     * Mount the React frontend SPA at a hash route.
     *
     * @param string $route Hash route e.g. /dashboard
     * @param bool   $require_login Whether a logged-in user is required.
     */
    private function react_page($route, $require_login = true) {
        return $this->render_view('react-app', [
            'initial_route' => $route,
            'require_login' => $require_login,
        ]);
    }

    /**
     * Choose React SPA or Divi-compatible PHP views / accounting workspace.
     *
     * @param array<string, mixed> $config route, accounting_view, php_view, require_login, react_only
     */
    private function resolve_frontend($config) {
        $require_login = !empty($config['require_login']);
        $react_only = !empty($config['react_only']);

        if ($require_login && !orabooks_is_user_logged_in()) {
            return OraBooks_Views::require_login_message();
        }

        if (
            !$react_only
            && function_exists('orabooks_should_use_react_frontend')
            && !orabooks_should_use_react_frontend()
        ) {
            if (!empty($config['php_view'])) {
                return $this->render_view($config['php_view']);
            }

            if (
                !empty($config['accounting_view'])
                && function_exists('orabooks_uses_merged_accounting_workspace')
                && orabooks_uses_merged_accounting_workspace()
            ) {
                return orabooks_render_merged_accounting_workspace($config['accounting_view']);
            }
        }

        return $this->react_page($config['route'], $require_login);
    }

    private function accounting_shortcode_page($shortcode_tag) {
        return $this->resolve_frontend([
            'route' => '/' . str_replace('orabooks_', '', str_replace('_', '-', $shortcode_tag)),
            'accounting_view' => orabooks_get_merged_accounting_view_for_shortcode($shortcode_tag),
        ]);
    }

    private function ajax_dashboard_page($title, $ajax_action, $description = '') {
        return $this->render_view('ajax-dashboard', [
            'title' => $title,
            'ajax_action' => $ajax_action,
            'description' => $description,
        ]);
    }

    /**
     * Customer workspace: React SPA elsewhere; PHP accounting workspace on Divi.
     */
    private function customer_workspace_page($shortcode_tag, $route) {
        return $this->resolve_frontend([
            'route' => $route,
            'accounting_view' => orabooks_get_merged_accounting_view_for_shortcode($shortcode_tag),
        ]);
    }

    public function login_form() {
        return $this->resolve_frontend([
            'route' => '/login',
            'php_view' => 'login',
            'require_login' => false,
        ]);
    }
    
    public function register_form() {
        return $this->resolve_frontend([
            'route' => '/register',
            'php_view' => 'register',
            'require_login' => false,
        ]);
    }
    
    public function verify_email() {
        return $this->resolve_frontend([
            'route' => '/verify-email',
            'php_view' => 'verify-email',
            'require_login' => false,
        ]);
    }
    
    public function reset_password() {
        return $this->resolve_frontend([
            'route' => '/reset-password',
            'php_view' => 'reset-password',
            'require_login' => false,
        ]);
    }
    
    public function partner_onboarding() {
        return $this->resolve_frontend([
            'route' => '/partner-onboarding',
            'php_view' => 'partner-onboarding',
        ]);
    }
    
    public function tier_selection() {
        return $this->resolve_frontend([
            'route' => '/tier-selection',
            'php_view' => 'tier-selection',
        ]);
    }
    
    public function dashboard() {
        if (
            function_exists('orabooks_should_use_react_frontend')
            && !orabooks_should_use_react_frontend()
            && function_exists('orabooks_uses_merged_accounting_workspace')
            && !orabooks_uses_merged_accounting_workspace()
        ) {
            return $this->resolve_frontend([
                'route' => '/partner-program',
                'php_view' => 'partner-program',
            ]);
        }

        return $this->customer_workspace_page('orabooks_dashboard', '/dashboard');
    }

    public function customers_page() {
        return $this->customer_workspace_page('orabooks_customers', '/customers');
    }

    public function vendors_page() {
        return $this->customer_workspace_page('orabooks_vendors', '/vendors');
    }

    public function inventory_page() {
        return $this->customer_workspace_page('orabooks_inventory', '/inventory');
    }

    public function bank_reconciliation_page() {
        return $this->customer_workspace_page('orabooks_bank_reconciliation', '/bank-reconciliation');
    }

    public function reports_page() {
        return $this->customer_workspace_page('orabooks_reports', '/reports');
    }

    public function invoices_page() {
        return $this->customer_workspace_page('orabooks_invoices', '/invoices');
    }

    public function chart_of_accounts_page() {
        return $this->customer_workspace_page('orabooks_chart_of_accounts', '/chart-of-accounts');
    }

    public function fiscal_periods_page() {
        return $this->customer_workspace_page('orabooks_fiscal_periods', '/fiscal-periods');
    }

    public function tax_settings_page() {
        return $this->customer_workspace_page('orabooks_tax_settings', '/tax-settings');
    }

    public function journals_page() {
        return $this->customer_workspace_page('orabooks_journals', '/journals');
    }

    public function profile_page() {
        return $this->resolve_frontend([
            'route' => '/profile',
            'accounting_view' => 'dashboard',
        ]);
    }

    public function team_page() {
        return $this->resolve_frontend([
            'route' => '/team',
            'accounting_view' => 'employees',
        ]);
    }

    public function attachments_page() {
        return $this->resolve_frontend([
            'route' => '/attachments',
            'accounting_view' => 'dashboard',
        ]);
    }

    public function approvals_page() {
        return $this->resolve_frontend([
            'route' => '/approvals',
            'accounting_view' => 'dashboard',
        ]);
    }

    public function ai_review_page() {
        return $this->resolve_frontend([
            'route' => '/ai-review',
            'accounting_view' => 'dashboard',
        ]);
    }

    public function expenses_page() {
        return $this->customer_workspace_page('orabooks_expenses', '/expenses');
    }

    public function voice_page() {
        return $this->resolve_frontend([
            'route' => '/voice',
            'accounting_view' => 'dashboard',
        ]);
    }
    
    /**
     * Partner Commission Dashboard Shortcode
     */
    public function commission_dashboard() {
        return $this->resolve_frontend([
            'route' => '/commissions',
            'php_view' => 'partner-program',
        ]);
    }
    
    /**
     * Partner Dashboard Shortcode (SL-139)
     */
    public function partner_dashboard() {
        return $this->resolve_frontend([
            'route' => '/partner-program',
            'php_view' => 'partner-program',
        ]);
    }
    
    /**
     * Commission Admin Shortcode (Super Admin - config management)
     */
    public function commission_admin() {
        return $this->resolve_frontend([
            'route' => '/commission-admin',
            'react_only' => true,
        ]);
    }

    // ================================================================
    // NOTIFICATION CENTER SHORTCODES (SL-250)
    // ================================================================

    public function notification_center() {
        return $this->resolve_frontend([
            'route' => '/notifications',
            'php_view' => 'notifications',
        ]);
    }

    public function notification_preferences() {
        return $this->resolve_frontend([
            'route' => '/notification-preferences',
            'php_view' => 'notification-preferences',
        ]);
    }

    public function notification_admin() {
        return $this->resolve_frontend([
            'route' => '/notification-admin',
            'react_only' => true,
        ]);
    }
    
    public function async_queue_dashboard() {
        return $this->resolve_frontend([
            'route' => '/job-queue',
            'react_only' => true,
        ]);
    }

    public function export_status() {
        return $this->resolve_frontend([
            'route' => '/my-exports',
            'php_view' => 'exports',
        ]);
    }

    public function csv_import_page() {
        return $this->customer_workspace_page('orabooks_csv_import', '/csv-imports');
    }

    /**
     * Export Button Shortcode
     * Renders a simple Export CSV / Export PDF button for use on report pages.
     */
    public function export_button($atts = []) {
        $atts = shortcode_atts([
            'type'   => 'report',
            'label'  => __('Export CSV', 'orabooks'),
            'format' => 'csv',
            'class'  => 'orabooks-btn orabooks-btn-secondary',
        ], $atts);

        return sprintf(
            '<span class="orabooks-export-trigger-root inline-block %s" data-export-type="%s" data-format="%s" data-label="%s"></span>',
            esc_attr($atts['class']),
            esc_attr($atts['type']),
            esc_attr($atts['format']),
            esc_attr($atts['label'])
        );
    }

    public function observability_dashboard() {
        return $this->resolve_frontend([
            'route' => '/observability',
            'react_only' => true,
        ]);
    }
}