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
        if ($require_login && !get_current_user_id()) {
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
        return $this->react_page('/dashboard');
    }

    public function customers_page() {
        return $this->react_page('/customers');
    }

    public function vendors_page() {
        return $this->react_page('/vendors');
    }

    public function inventory_page() {
        return $this->react_page('/inventory');
    }

    public function bank_reconciliation_page() {
        return $this->react_page('/bank-reconciliation');
    }

    public function reports_page() {
        return $this->react_page('/reports');
    }

    public function invoices_page() {
        return $this->react_page('/invoices');
    }

    public function chart_of_accounts_page() {
        return $this->react_page('/chart-of-accounts');
    }

    public function fiscal_periods_page() {
        return $this->react_page('/fiscal-periods');
    }

    public function tax_settings_page() {
        return $this->react_page('/tax-settings');
    }

    public function journals_page() {
        return $this->react_page('/journals');
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
        return $this->react_page('/expenses');
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
        return $this->react_page('/csv-imports');
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
        
        ob_start();
        ?>
        <button class="orabooks-export-trigger <?php echo esc_attr($atts['class']); ?> orabooks-btn-sm"
                data-export-type="<?php echo esc_attr($atts['type']); ?>"
                data-format="<?php echo esc_attr($atts['format']); ?>">
            <?php echo esc_html($atts['label']); ?>
        </button>
        <?php
        return ob_get_clean();
    }

    public function commission_admin() {
        if (!current_user_can('manage_options')) {
            return '<p>' . __('Access denied.', 'orabooks') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="orabooks-commission-admin">
            <h2><?php _e('Commission Platform Configuration', 'orabooks'); ?></h2>
            
            <!-- SL-114 Export Action Bar -->
            <div class="orabooks-coa-export-actions" style="margin-bottom:16px;padding:12px 16px;background:#f0f6fc;border:1px solid #c5d9ed;border-radius:6px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <span style="font-weight:600;color:#1d2327;font-size:13px;">📊 <?php _e('Export Config:', 'orabooks'); ?></span>
                <button class="orabooks-btn orabooks-btn-secondary orabooks-btn-sm orabooks-commconfig-export-trigger" data-export-type="commission_config" data-format="csv"><?php _e('Export Config CSV', 'orabooks'); ?></button>
                <button class="orabooks-btn orabooks-btn-sm orabooks-commconfig-export-trigger" data-export-type="commission_config" data-format="pdf"><?php _e('Export Config PDF', 'orabooks'); ?></button>
                <span style="color:#666;font-size:12px;margin-left:4px;">📁 <?php _e('Async — you\'ll get a notification when ready.', 'orabooks'); ?></span>
            </div>
            <div id="orabooks-commconfig-export-msg" class="orabooks-message" style="display:none;"></div>
            
            <form id="orabooks-commission-config-form" class="orabooks-form">
                <div class="orabooks-form-group">
                    <label for="config-base-monthly"><?php _e('Base Monthly Amount', 'orabooks'); ?></label>
                    <input type="number" id="config-base-monthly" name="base_monthly_amount" step="0.01" min="0">
                </div>
                <div class="orabooks-form-group">
                    <label for="config-max-years"><?php _e('Max Years', 'orabooks'); ?></label>
                    <input type="number" id="config-max-years" name="max_years" min="1" max="10">
                </div>
                <div class="orabooks-form-group">
                    <label for="config-yearly-pcts"><?php _e('Yearly Percentages (JSON array)', 'orabooks'); ?></label>
                    <input type="text" id="config-yearly-pcts" name="yearly_percentages" placeholder="[20,15,10,5,2.5,1]">
                </div>
                <div class="orabooks-form-group">
                    <label for="config-min-payout"><?php _e('Minimum Payout Threshold', 'orabooks'); ?></label>
                    <input type="number" id="config-min-payout" name="min_payout_threshold" step="0.01" min="0">
                </div>
                <div class="orabooks-form-group">
                    <label for="config-active-window"><?php _e('Customer Active Window (Days)', 'orabooks'); ?></label>
                    <input type="number" id="config-active-window" name="customer_active_window_days" min="1" max="365">
                </div>
                <div class="orabooks-form-group">
                    <label for="config-expiry-action"><?php _e('Expiry Accounting Action', 'orabooks'); ?></label>
                    <select id="config-expiry-action" name="expiry_accounting_action">
                        <option value="reverse_expense"><?php _e('Reverse Expense', 'orabooks'); ?></option>
                        <option value="income"><?php _e('Expired Commission Income', 'orabooks'); ?></option>
                    </select>
                </div>
                <div class="orabooks-form-group">
                    <label for="config-fee-type"><?php _e('Payout Fee Type', 'orabooks'); ?></label>
                    <select id="config-fee-type" name="payout_fee_type">
                        <option value="percentage"><?php _e('Percentage', 'orabooks'); ?></option>
                        <option value="flat"><?php _e('Flat', 'orabooks'); ?></option>
                    </select>
                </div>
                <div class="orabooks-form-group">
                    <label for="config-fee-rate"><?php _e('Payout Fee Rate', 'orabooks'); ?></label>
                    <input type="number" id="config-fee-rate" name="payout_fee_rate" step="0.0001" min="0">
                    <small><?php _e('If percentage: e.g., 2.5 = 2.5%. If flat: dollar amount.', 'orabooks'); ?></small>
                </div>
                <div class="orabooks-form-actions">
                    <button type="submit" class="orabooks-btn orabooks-btn-primary"><?php _e('Update Configuration', 'orabooks'); ?></button>
                </div>
            </form>
            <div id="orabooks-commission-config-message" class="orabooks-message"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Platform observability dashboard (SL-093, admin only).
     */
    public function observability_dashboard() {
        if (!current_user_can('manage_options')) {
            return '<p>' . __('Access denied.', 'orabooks') . '</p>';
        }

        ob_start();
        ?>
        <div class="orabooks-observability-dashboard">
            <h2><?php _e('Platform Observability', 'orabooks'); ?></h2>
            <p style="color:#666;"><?php _e('Queue depth, lag, failure rates, and subsystem health snapshots.', 'orabooks'); ?></p>
            <button id="orabooks-obs-refresh" class="orabooks-btn orabooks-btn-secondary orabooks-btn-sm">🔄 <?php _e('Refresh', 'orabooks'); ?></button>
            <div class="orabooks-commission-stats" id="orabooks-obs-stats" style="margin-top:16px;">
                <div class="orabooks-stat-card"><h3><?php _e('Event Bus', 'orabooks'); ?></h3><p class="orabooks-stat-number" id="obs-eventbus">—</p></div>
                <div class="orabooks-stat-card"><h3><?php _e('Async Queue', 'orabooks'); ?></h3><p class="orabooks-stat-number" id="obs-async">—</p></div>
                <div class="orabooks-stat-card"><h3><?php _e('Notifications', 'orabooks'); ?></h3><p class="orabooks-stat-number" id="obs-notifications">—</p></div>
                <div class="orabooks-stat-card"><h3><?php _e('Exports', 'orabooks'); ?></h3><p class="orabooks-stat-number" id="obs-exports">—</p></div>
            </div>
            <table class="orabooks-table" style="margin-top:16px;">
                <thead>
                    <tr>
                        <th><?php _e('Service', 'orabooks'); ?></th>
                        <th><?php _e('Status', 'orabooks'); ?></th>
                        <th><?php _e('Details', 'orabooks'); ?></th>
                    </tr>
                </thead>
                <tbody id="orabooks-obs-health-body">
                    <tr><td colspan="3"><?php _e('Loading...', 'orabooks'); ?></td></tr>
                </tbody>
            </table>
        </div>
        <script>
        (function($){
            function renderObservability(data) {
                var s = data.snapshots || {};
                $('#obs-eventbus').text((s.eventbus && s.eventbus.status) ? s.eventbus.status.toUpperCase() : '—');
                $('#obs-async').text((s.async_queue && s.async_queue.status) ? s.async_queue.status.toUpperCase() : '—');
                $('#obs-notifications').text((s.notifications && s.notifications.status) ? s.notifications.status.toUpperCase() : '—');
                $('#obs-exports').text((s.exports && s.exports.status) ? s.exports.status.toUpperCase() : '—');
                var rows = '';
                Object.keys(s).forEach(function(key){
                    rows += '<tr><td>' + key + '</td><td>' + (s[key].status || '—') + '</td><td><code>' + JSON.stringify(s[key]) + '</code></td></tr>';
                });
                $('#orabooks-obs-health-body').html(rows || '<tr><td colspan="3"><?php echo esc_js(__('No data', 'orabooks')); ?></td></tr>');
            }
            function loadObservability() {
                $.post(orabooks_ajax.ajax_url, { action: 'orabooks_observability_dashboard', nonce: orabooks_ajax.nonce, hours: 24 }, function(resp){
                    if (resp && !resp.error && resp.data) { renderObservability(resp.data); }
                    else { $('#orabooks-obs-health-body').html('<tr><td colspan="3"><?php echo esc_js(__('Unable to load observability data.', 'orabooks')); ?></td></tr>'); }
                }).fail(function() {
                    $('#orabooks-obs-health-body').html('<tr><td colspan="3"><?php echo esc_js(__('Unable to load observability data.', 'orabooks')); ?></td></tr>');
                });
            }
            $('#orabooks-obs-refresh').on('click', loadObservability);
            loadObservability();
        })(jQuery);
        </script>
        <?php
        return ob_get_clean();
    }
}