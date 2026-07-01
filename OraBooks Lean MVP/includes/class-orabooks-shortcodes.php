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
            add_shortcode('orabooks_accept_invite', [self::$instance, 'accept_invite']);
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
            add_shortcode('orabooks_approval_settings', [self::$instance, 'approval_settings_page']);
            add_shortcode('orabooks_ai_review', [self::$instance, 'ai_review_page']);
            add_shortcode('orabooks_expenses', [self::$instance, 'expenses_page']);
            add_shortcode('orabooks_voice', [self::$instance, 'voice_page']);
            add_shortcode('orabooks_export_button', [self::$instance, 'export_button']);
            add_shortcode('orabooks_customers', [self::$instance, 'customers_page']);
            add_shortcode('orabooks_vendors', [self::$instance, 'vendors_page']);
            add_shortcode('orabooks_ap_aging', [self::$instance, 'ap_aging_page']);
            add_shortcode('orabooks_inventory', [self::$instance, 'inventory_page']);
            add_shortcode('orabooks_bank_reconciliation', [self::$instance, 'bank_reconciliation_page']);
            add_shortcode('orabooks_reports', [self::$instance, 'reports_page']);
            add_shortcode('orabooks_financial_reports', [self::$instance, 'financial_reports_page']);
            add_shortcode('orabooks_invoices', [self::$instance, 'invoices_page']);
            add_shortcode('orabooks_chart_of_accounts', [self::$instance, 'chart_of_accounts_page']);
            add_shortcode('orabooks_fiscal_periods', [self::$instance, 'fiscal_periods_page']);
            add_shortcode('orabooks_tax_settings', [self::$instance, 'tax_settings_page']);
            add_shortcode('orabooks_journals', [self::$instance, 'journals_page']);
            add_shortcode('orabooks_profile', [self::$instance, 'profile_page']);
            add_shortcode('orabooks_security_2fa', [self::$instance, 'security_2fa_page']);
            add_shortcode('orabooks_audit_log', [self::$instance, 'audit_log_page']);
            add_shortcode('orabooks_webhook_settings', [self::$instance, 'webhook_settings_page']);
        }
        return self::$instance;
    }

    private function render_view($view, $vars = []) {
        return OraBooks_Views::render('frontend/' . $view, $vars);
    }

    /**
     * Mount the React frontend page for a WordPress route path.
     * @param bool   $require_login Whether a logged-in user is required.
     */
    private function react_page($route, $require_login = true) {
        OraBooks_Assets::mark_frontend_shortcode_rendered();

        return $this->render_view('react-app', [
            'initial_route' => $route,
            'require_login' => $require_login,
        ]);
    }

    /**
     * Lean MVP customer workspace pages — always React.
     */
    private function customer_react_page($route, $require_login = true) {
        return $this->react_page($route, $require_login);
    }

    public function login_form() {
        return $this->react_page('/login', false);
    }
    
    public function register_form() {
        return $this->react_page('/register', false);
    }
    
    public function verify_email() {
        return $this->react_page('/verify-email', false);
    }

    public function accept_invite() {
        return $this->react_page('/accept-invite', false);
    }
    
    public function reset_password() {
        return $this->react_page('/reset-password', false);
    }
    
    public function partner_onboarding() {
        return $this->react_page('/onboarding');
    }
    
    public function tier_selection() {
        return $this->react_page('/tier-selection');
    }
    
    public function dashboard() {
        return $this->customer_react_page('/dashboard');
    }

    public function customers_page() {
        return $this->customer_react_page('/customers');
    }

    public function vendors_page() {
        return $this->customer_react_page('/vendors');
    }

    public function ap_aging_page() {
        return $this->customer_react_page('/ap-aging');
    }

    public function inventory_page() {
        return $this->customer_react_page('/inventory');
    }

    public function bank_reconciliation_page() {
        return $this->customer_react_page('/bank-reconciliation');
    }

    public function reports_page() {
        return $this->customer_react_page('/reports');
    }

    public function financial_reports_page() {
        return $this->customer_react_page('/financial-reports');
    }

    public function invoices_page() {
        return $this->customer_react_page('/invoices');
    }

    public function chart_of_accounts_page() {
        return $this->customer_react_page('/chart-of-accounts');
    }

    public function fiscal_periods_page() {
        return $this->customer_react_page('/fiscal-periods');
    }

    public function tax_settings_page() {
        return $this->customer_react_page('/tax-settings');
    }

    public function journals_page() {
        return $this->customer_react_page('/journals');
    }

    public function profile_page() {
        return $this->customer_react_page('/profile');
    }

    public function security_2fa_page() {
        return $this->customer_react_page('/security/2fa');
    }

    public function audit_log_page() {
        return $this->customer_react_page('/audit-log');
    }

    public function webhook_settings_page() {
        return $this->customer_react_page('/webhook-settings');
    }

    public function team_page() {
        return $this->customer_react_page('/team');
    }

    public function attachments_page() {
        return $this->customer_react_page('/attachments');
    }

    public function approvals_page() {
        return $this->customer_react_page('/approvals');
    }

    public function approval_settings_page() {
        return $this->customer_react_page('/approval-settings');
    }

    public function ai_review_page() {
        return $this->customer_react_page('/ai-review');
    }

    public function expenses_page() {
        return $this->customer_react_page('/expenses');
    }

    public function voice_page() {
        return $this->customer_react_page('/voice');
    }
    
    /**
     * Partner Commission Dashboard Shortcode
     */
    public function commission_dashboard() {
        return $this->react_page('/commissions');
    }
    
    /**
     * Partner Dashboard Shortcode (SL-139)
     */
    public function partner_dashboard() {
        return $this->react_page('/partner/dashboard');
    }
    
    /**
     * Commission Admin Shortcode (Super Admin - config management)
     */
    public function commission_admin() {
        return $this->react_page('/commission-admin');
    }

    // ================================================================
    // NOTIFICATION CENTER SHORTCODES (SL-250)
    // ================================================================

    public function notification_center() {
        return $this->react_page('/notifications');
    }

    public function notification_preferences() {
        return $this->react_page('/notification-preferences');
    }

    public function notification_admin() {
        return $this->react_page('/notification-admin');
    }
    
    public function async_queue_dashboard() {
        return $this->react_page('/job-queue');
    }

    public function export_status() {
        return $this->react_page('/my-exports');
    }

    public function csv_import_page() {
        return $this->customer_react_page('/csv-imports');
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
        return $this->react_page('/observability');
    }
}