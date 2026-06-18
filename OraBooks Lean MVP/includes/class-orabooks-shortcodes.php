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
        if ($require_login && !orabooks_is_user_logged_in()) {
            return OraBooks_Views::require_login_message();
        }

        return $this->render_view('react-app', [
            'initial_route' => $route,
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
     * Route customer orgs to merged accounting workspace; partners/admins keep React routes.
     */
    private function merged_or_react($accounting_view, $react_route, $require_login = true) {
        if ($require_login && !orabooks_is_user_logged_in()) {
            return OraBooks_Views::require_login_message();
        }

        if (function_exists('orabooks_uses_merged_accounting_workspace') && orabooks_uses_merged_accounting_workspace()) {
            return orabooks_render_merged_accounting_workspace($accounting_view);
        }

        return $this->react_page($react_route, $require_login);
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
    
    public function reset_password() {
        return $this->react_page('/reset-password', false);
    }
    
    public function partner_onboarding() {
        return $this->react_page('/partner-onboarding');
    }
    
    public function tier_selection() {
        return $this->react_page('/tier-selection');
    }
    
    public function dashboard() {
        if (function_exists('orabooks_uses_merged_accounting_workspace') && orabooks_uses_merged_accounting_workspace()) {
            return orabooks_render_merged_accounting_workspace();
        }

        return $this->react_page('/partner-program');
    }

    public function customers_page() {
        return $this->merged_or_react('customers', '/customers');
    }

    public function vendors_page() {
        return $this->merged_or_react('suppliers', '/vendors');
    }

    public function inventory_page() {
        return $this->merged_or_react('view-items', '/inventory');
    }

    public function bank_reconciliation_page() {
        return $this->react_page('/bank-reconciliation');
    }

    public function reports_page() {
        return $this->merged_or_react('journal-report', '/reports');
    }

    public function invoices_page() {
        return $this->merged_or_react('view-sales', '/invoices');
    }

    public function chart_of_accounts_page() {
        return $this->merged_or_react('coa-list', '/chart-of-accounts');
    }

    public function fiscal_periods_page() {
        return $this->merged_or_react('fiscal-periods', '/fiscal-periods');
    }

    public function tax_settings_page() {
        return $this->merged_or_react('setting-tax-list', '/tax-settings');
    }

    public function journals_page() {
        return $this->merged_or_react('journal-entry-list', '/journals');
    }

    public function profile_page() {
        return $this->react_page('/profile');
    }

    public function team_page() {
        return $this->react_page('/team');
    }

    public function attachments_page() {
        return $this->react_page('/attachments');
    }

    public function approvals_page() {
        return $this->react_page('/approvals');
    }

    public function ai_review_page() {
        return $this->react_page('/ai-review');
    }

    public function expenses_page() {
        return $this->merged_or_react('expense-list', '/expenses');
    }

    public function voice_page() {
        return $this->react_page('/voice');
    }
    
    /**
     * Partner Commission Dashboard Shortcode
     */
    public function commission_dashboard() {
        return $this->react_page('/commissions');
    }
    
    /**
     * Partner Dashboard Shortcode (SL-139)
     * Unified "Partner Program" page with status banners, attribution + commission stats,
     * payout breakdown (Gross/Fee/Net), attribution table, and reactivation.
     */
    public function partner_dashboard() {
        return $this->react_page('/partner-program');
    }
    
    /**
     * Commission Admin Shortcode (Super Admin - config management)
     */
    // ================================================================
    // NOTIFICATION CENTER SHORTCODES (SL-250)
    // ================================================================

    /**
     * Notification Center Shortcode
     * Shows all notifications for the current user with filtering.
     */
    public function notification_center() {
        return $this->react_page('/notifications');
    }

    /**
     * Notification Preferences Shortcode
     * Allows user to configure notification channels, quiet hours, digest, language, escalation.
     */
    public function notification_preferences() {
        return $this->react_page('/notification-preferences');
    }

    /**
     * Notification Admin Shortcode (Owner only)
     * Shows org policies, provider health, audit export.
     */
    public function notification_admin() {
        return $this->react_page('/notification-admin');
    }
    
    /**
     * Async Queue Dashboard Shortcode (Admin only)
     */
    public function async_queue_dashboard() {
        return $this->react_page('/job-queue');
    }

    // ================================================================
    // EXPORT STATUS SHORTCODE (SL-114)
    // ================================================================

    /**
     * Export Status Page Shortcode
     * Shows "My Exports" table with status, download, cancel.
     */
    public function export_status() {
        return $this->react_page('/my-exports');
    }

    /**
     * CSV Import page shortcode (SL-113).
     */
    public function csv_import_page() {
        return $this->merged_or_react('import-customers', '/csv-imports');
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

    public function commission_admin() {
        return $this->react_page('/commission-admin');
    }

    /**
     * Platform observability dashboard (SL-093, admin only).
     */
    public function observability_dashboard() {
        return $this->react_page('/observability');
    }
}