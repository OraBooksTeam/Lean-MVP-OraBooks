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
            add_shortcode('orabooks_export_button', [self::$instance, 'export_button']);
            add_shortcode('orabooks_customers', [self::$instance, 'customers_page']);
            add_shortcode('orabooks_vendors', [self::$instance, 'vendors_page']);
            add_shortcode('orabooks_inventory', [self::$instance, 'inventory_page']);
            add_shortcode('orabooks_bank_reconciliation', [self::$instance, 'bank_reconciliation_page']);
            add_shortcode('orabooks_reports', [self::$instance, 'reports_page']);
            add_shortcode('orabooks_invoices', [self::$instance, 'invoices_page']);
            add_shortcode('orabooks_chart_of_accounts', [self::$instance, 'chart_of_accounts_page']);
            add_shortcode('orabooks_journals', [self::$instance, 'journals_page']);
            add_shortcode('orabooks_profile', [self::$instance, 'profile_page']);
        }
        return self::$instance;
    }

    private function react_app($route = '/dashboard', $loading_text = 'Loading OraBooks...') {
        if (!file_exists(ORABOOKS_PLUGIN_DIR . 'assets/react/frontend.js')) {
            return '<div class="orabooks-message error" style="display:block;">' .
                esc_html__('OraBooks frontend assets are missing. Build locally with npm run build and upload the assets/react folder with the plugin.', 'orabooks') .
                '</div>';
        }

        ob_start();
        ?>
        <div class="orabooks-react-page">
            <div id="orabooks-app-root" class="orabooks-app-root" data-initial-route="<?php echo esc_attr($route); ?>">
                <p class="orabooks-app-root-loading"><?php echo esc_html__($loading_text, 'orabooks'); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function login_form() {
        return $this->react_app('/login', 'Loading OraBooks login...');
    }
    
    public function register_form() {
        return $this->react_app('/register', 'Loading OraBooks registration...');
    }
    
    public function verify_email() {
        $token = sanitize_text_field($_GET['token'] ?? '');
        
        if (empty($token)) {
            return '<div class="orabooks-message error" style="display:block;">' .
                esc_html__('Invalid verification link.', 'orabooks') .
                '</div>';
        }
        
        $result = OraBooks_Auth::verify_email($token);
        
        if (is_wp_error($result)) {
            return '<div class="orabooks-message error" style="display:block;">' .
                esc_html($result->get_error_message()) .
                '</div>';
        }
        
        return '<div class="orabooks-message success" style="display:block;">' .
            esc_html__('Email verified successfully. You can now log in.', 'orabooks') .
            ' <a href="' . esc_url(home_url('/login/?verified=1')) . '">' .
            esc_html__('Go to login', 'orabooks') .
            '</a></div>';
    }
    
    public function reset_password() {
        $token = sanitize_text_field($_GET['token'] ?? '');
        ob_start();
        ?>
        <div class="orabooks-auth-shell">
        <div class="orabooks-form-container">
            <h2><?php esc_html_e('Reset Password', 'orabooks'); ?></h2>
            <?php if (empty($token)) : ?>
                <p><?php esc_html_e('Enter your email address and we will send you a password reset link.', 'orabooks'); ?></p>
                <form id="orabooks-forgot-password-form" class="orabooks-form">
                    <div class="orabooks-form-group">
                        <label for="forgot-email"><?php esc_html_e('Email', 'orabooks'); ?></label>
                        <input type="email" id="forgot-email" required autocomplete="email">
                    </div>
                    <div class="orabooks-form-actions">
                        <button type="submit" class="orabooks-btn orabooks-btn-primary">
                            <?php esc_html_e('Send Reset Link', 'orabooks'); ?>
                        </button>
                    </div>
                </form>
                <div id="orabooks-forgot-password-message" class="orabooks-message"></div>
            <?php else : ?>
                <form id="orabooks-reset-password-form" class="orabooks-form">
                    <input type="hidden" id="reset-token" value="<?php echo esc_attr($token); ?>">
                    <div class="orabooks-form-group">
                        <label for="reset-password"><?php esc_html_e('New Password', 'orabooks'); ?></label>
                        <input type="password" id="reset-password" required autocomplete="new-password">
                    </div>
                    <div class="orabooks-form-group">
                        <label for="reset-confirm-password"><?php esc_html_e('Confirm New Password', 'orabooks'); ?></label>
                        <input type="password" id="reset-confirm-password" required autocomplete="new-password">
                    </div>
                    <div class="orabooks-form-actions">
                        <button type="submit" class="orabooks-btn orabooks-btn-primary">
                            <?php esc_html_e('Reset Password', 'orabooks'); ?>
                        </button>
                    </div>
                </form>
                <div id="orabooks-reset-password-message" class="orabooks-message"></div>
            <?php endif; ?>
        </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function partner_onboarding() {
        return $this->react_app('/partner-onboarding', 'Loading partner onboarding...');
    }
    
    public function tier_selection() {
        return $this->react_app('/tier-selection', 'Loading plan selection...');
    }
    
    public function dashboard() {
        return $this->react_app('/dashboard', 'Loading OraBooks dashboard...');
    }

    public function customers_page() {
        return $this->react_app('/customers', 'Loading customers...');
    }

    public function vendors_page() {
        return $this->react_app('/vendors', 'Loading vendors & bills...');
    }

    public function inventory_page() {
        return $this->react_app('/inventory', 'Loading inventory...');
    }

    public function bank_reconciliation_page() {
        return $this->react_app('/bank-reconciliation', 'Loading bank reconciliation...');
    }

    public function reports_page() {
        return $this->react_app('/reports', 'Loading reports...');
    }

    public function invoices_page() {
        return $this->react_app('/invoices', 'Loading invoices...');
    }

    public function chart_of_accounts_page() {
        return $this->react_app('/chart-of-accounts', 'Loading chart of accounts...');
    }

    public function journals_page() {
        return $this->react_app('/journals', 'Loading journals...');
    }

    public function profile_page() {
        return $this->react_app('/profile', 'Loading profile...');
    }

    public function team_page() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return '<p>' . __('Please log in to manage your team.', 'orabooks') . '</p>';
        }
        return $this->react_app('/team', 'Loading team...');
    }

    public function attachments_page() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return '<p>' . __('Please log in to manage attachments.', 'orabooks') . '</p>';
        }
        return $this->react_app('/attachments', 'Loading attachments...');
    }
    
    /**
     * Partner Commission Dashboard Shortcode
     */
    public function commission_dashboard() {
        ob_start();
        ?>
        <div class="orabooks-commission-dashboard">
            <h2><?php _e('Commission Dashboard', 'orabooks'); ?></h2>
            
            <!-- Stats Cards -->
            <div class="orabooks-commission-stats">
                <div class="orabooks-stat-card">
                    <h3><?php _e('Total Earned (Accrued)', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-amount" id="orabooks-total-earned">$0.00</p>
                </div>
                <div class="orabooks-stat-card">
                    <h3><?php _e('Pending Payout', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-amount" id="orabooks-pending-payout">$0.00</p>
                </div>
                <div class="orabooks-stat-card">
                    <h3><?php _e('Paid', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-amount" id="orabooks-total-paid">$0.00</p>
                </div>
                <div class="orabooks-stat-card">
                    <h3><?php _e('Expired', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-amount" id="orabooks-total-expired">$0.00</p>
                </div>
                <div class="orabooks-stat-card">
                    <h3><?php _e('Escrow Remaining', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-amount" id="orabooks-escrow-remaining">$0.00</p>
                </div>
            </div>
            
            <!-- Info Banner -->
            <div class="orabooks-info-banner">
                <p>💰 <?php _e('Commission is accrued monthly when customer is active. Paid via bank transfer (minimum payout threshold applies). Transaction fee deducted from gross.', 'orabooks'); ?></p>
                <p>⏳ <?php _e('Commission expires 6 years after each monthly release.', 'orabooks'); ?></p>
            </div>
            
            <!-- Tabs -->
            <div class="orabooks-tabs">
                <button class="orabooks-tab orabooks-tab-active" data-tab="earned"><?php _e('Earned Commissions', 'orabooks'); ?></button>
                <button class="orabooks-tab" data-tab="payouts"><?php _e('Payout History', 'orabooks'); ?></button>
                <button class="orabooks-tab" data-tab="aging"><?php _e('Payable Aging', 'orabooks'); ?></button>
                <button class="orabooks-tab" data-tab="escrow"><?php _e('Escrow Schedule', 'orabooks'); ?></button>
            </div>
            
            <!-- Tab: Earned Commissions -->
            <div id="orabooks-tab-earned" class="orabooks-tab-content orabooks-tab-content-active">
                <table class="orabooks-table">
                    <thead>
                        <tr>
                            <th><?php _e('Customer', 'orabooks'); ?></th>
                            <th><?php _e('Release Month', 'orabooks'); ?></th>
                            <th><?php _e('Amount', 'orabooks'); ?></th>
                            <th><?php _e('Status', 'orabooks'); ?></th>
                            <th><?php _e('Expires', 'orabooks'); ?></th>
                            <th><?php _e('Earned At', 'orabooks'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="orabooks-earned-table-body">
                        <tr><td colspan="6"><?php _e('Loading...', 'orabooks'); ?></td></tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Tab: Payout History -->
            <div id="orabooks-tab-payouts" class="orabooks-tab-content">
                <table class="orabooks-table">
                    <thead>
                        <tr>
                            <th><?php _e('Date', 'orabooks'); ?></th>
                            <th><?php _e('Gross', 'orabooks'); ?></th>
                            <th><?php _e('Fee', 'orabooks'); ?></th>
                            <th><?php _e('Net', 'orabooks'); ?></th>
                            <th><?php _e('Status', 'orabooks'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="orabooks-payouts-table-body">
                        <tr><td colspan="5"><?php _e('Loading...', 'orabooks'); ?></td></tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Tab: Payable Aging -->
            <div id="orabooks-tab-aging" class="orabooks-tab-content">
                <table class="orabooks-table">
                    <thead>
                        <tr>
                            <th><?php _e('Bucket', 'orabooks'); ?></th>
                            <th><?php _e('Amount', 'orabooks'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="orabooks-aging-table-body">
                        <tr><td colspan="2"><?php _e('Loading...', 'orabooks'); ?></td></tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Tab: Escrow Schedule -->
            <div id="orabooks-tab-escrow" class="orabooks-tab-content">
                <table class="orabooks-table">
                    <thead>
                        <tr>
                            <th><?php _e('Customer', 'orabooks'); ?></th>
                            <th><?php _e('Total', 'orabooks'); ?></th>
                            <th><?php _e('Released', 'orabooks'); ?></th>
                            <th><?php _e('Remaining', 'orabooks'); ?></th>
                            <th><?php _e('Progress', 'orabooks'); ?></th>
                            <th><?php _e('Status', 'orabooks'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="orabooks-escrow-table-body">
                        <tr><td colspan="6"><?php _e('Loading...', 'orabooks'); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Partner Dashboard Shortcode (SL-139)
     * Unified "Partner Program" page with status banners, attribution + commission stats,
     * payout breakdown (Gross/Fee/Net), attribution table, and reactivation.
     */
    public function partner_dashboard() {
        return $this->react_app('/dashboard', 'Loading partner program...');
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
        if (!get_current_user_id()) {
            return '<p>' . __('Please log in to view notifications.', 'orabooks') . '</p>';
        }
        return $this->react_app('/notifications', 'Loading notifications...');
    }

    /**
     * Notification Preferences Shortcode
     * Allows user to configure notification channels, quiet hours, digest, language, escalation.
     */
    public function notification_preferences() {
        if (!get_current_user_id()) {
            return '<p>' . __('Please log in to manage notification preferences.', 'orabooks') . '</p>';
        }
        return $this->react_app('/notification-preferences', 'Loading notification preferences...');
    }

    /**
     * Notification Admin Shortcode (Owner only)
     * Shows org policies, provider health, audit export.
     */
    public function notification_admin() {
        if (!current_user_can('manage_options')) {
            return '<p>' . __('Access denied.', 'orabooks') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="orabooks-notification-admin">
            <h2><?php _e('Notification Settings (Admin)', 'orabooks'); ?></h2>
            
            <div class="orabooks-tabs">
                <button class="orabooks-tab orabooks-tab-active" data-tab="policy"><?php _e('Org Policy', 'orabooks'); ?></button>
                <button class="orabooks-tab" data-tab="provider-health"><?php _e('Provider Health', 'orabooks'); ?></button>
                <button class="orabooks-tab" data-tab="audit-export"><?php _e('Audit Export', 'orabooks'); ?></button>
            </div>
            
            <!-- Tab: Org Policy -->
            <div id="orabooks-tab-policy" class="orabooks-tab-content orabooks-tab-content-active">
                <form id="orabooks-nc-policy-form" class="orabooks-form">
                    <div class="orabooks-form-group">
                        <label for="policy-monthly-budget"><?php _e('Monthly Budget ($)', 'orabooks'); ?></label>
                        <input type="number" id="policy-monthly-budget" name="monthly_budget" step="0.01" min="0">
                        <small>💰 <?php _e('Notification cost budget for this org.', 'orabooks'); ?></small>
                    </div>
                    <div class="orabooks-form-group">
                        <label><?php _e('Mandatory Event Types (always sent)', 'orabooks'); ?></label>
                        <div class="orabooks-checkbox-group">
                            <label><input type="checkbox" name="mandatory_event_types[]" value="security_alert"> <?php _e('Security Alert', 'orabooks'); ?></label>
                            <label><input type="checkbox" name="mandatory_event_types[]" value="system_maintenance"> <?php _e('System Maintenance', 'orabooks'); ?></label>
                        </div>
                        <small>⚙️ <?php _e('Set mandatory alerts, budget, prohibited channels.', 'orabooks'); ?></small>
                    </div>
                    <div class="orabooks-form-group">
                        <label><?php _e('Prohibited Channels', 'orabooks'); ?></label>
                        <div class="orabooks-checkbox-group">
                            <label><input type="checkbox" name="prohibited_channels[]" value="push"> <?php _e('Push', 'orabooks'); ?></label>
                            <label><input type="checkbox" name="prohibited_channels[]" value="email"> <?php _e('Email', 'orabooks'); ?></label>
                        </div>
                    </div>
                    <div class="orabooks-form-group">
                        <label for="policy-retention"><?php _e('Retention Override (Days)', 'orabooks'); ?></label>
                        <input type="number" id="policy-retention" name="retention_override_days" min="30" max="3650">
                    </div>
                    <div class="orabooks-form-group">
                        <label for="policy-max-escalation"><?php _e('Max Escalation Attempts', 'orabooks'); ?></label>
                        <input type="number" id="policy-max-escalation" name="max_escalation_attempts" min="1" max="10" value="3">
                    </div>
                    <div class="orabooks-form-group">
                        <label><?php _e('Escalation Fallback Chain', 'orabooks'); ?></label>
                        <div class="orabooks-escalation-chain">
                            <label><input type="checkbox" name="escalation_fallback_chain[]" value="email" checked> <?php _e('Email', 'orabooks'); ?></label>
                            <label><input type="checkbox" name="escalation_fallback_chain[]" value="push"> <?php _e('Push', 'orabooks'); ?></label>
                            <label><input type="checkbox" name="escalation_fallback_chain[]" value="inapp" checked> <?php _e('In-App', 'orabooks'); ?></label>
                        </div>
                    </div>
                    <div class="orabooks-form-actions">
                        <button type="submit" class="orabooks-btn orabooks-btn-primary"><?php _e('Save Policy', 'orabooks'); ?></button>
                    </div>
                </form>
                <div id="orabooks-nc-policy-message" class="orabooks-message"></div>
            </div>
            
            <!-- Tab: Provider Health -->
            <div id="orabooks-tab-provider-health" class="orabooks-tab-content">
                <div class="orabooks-nc-provider-actions">
                    <button id="orabooks-nc-refresh-health" class="orabooks-btn orabooks-btn-secondary orabooks-btn-sm">
                        🔄 <?php _e('Refresh', 'orabooks'); ?>
                    </button>
                </div>
                <p class="orabooks-nc-provider-note">📊 <?php _e('Auto-ranked providers. Unhealthy ones avoided. Outage penalty applied (score ≤40 after outage).', 'orabooks'); ?></p>
                <table class="orabooks-table">
                    <thead>
                        <tr>
                            <th><?php _e('Channel', 'orabooks'); ?></th>
                            <th><?php _e('Provider', 'orabooks'); ?></th>
                            <th><?php _e('Region', 'orabooks'); ?></th>
                            <th><?php _e('Success Rate', 'orabooks'); ?></th>
                            <th><?php _e('Latency', 'orabooks'); ?></th>
                            <th><?php _e('Health Score', 'orabooks'); ?></th>
                            <th><?php _e('Last Outage', 'orabooks'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="orabooks-nc-provider-health-body">
                        <tr><td colspan="7"><?php _e('Loading...', 'orabooks'); ?></td></tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Tab: Audit Export -->
            <div id="orabooks-tab-audit-export" class="orabooks-tab-content">
                <p>📦 <?php _e('Download signed JSON bundle with delivery proofs. Export is audited.', 'orabooks'); ?></p>
                <form id="orabooks-nc-audit-export-form" class="orabooks-form">
                    <div class="orabooks-form-group">
                        <label for="audit-start-date"><?php _e('Start Date', 'orabooks'); ?></label>
                        <input type="date" id="audit-start-date" name="start_date" value="<?php echo esc_attr(date('Y-m-d', strtotime('-30 days'))); ?>">
                    </div>
                    <div class="orabooks-form-group">
                        <label for="audit-end-date"><?php _e('End Date', 'orabooks'); ?></label>
                        <input type="date" id="audit-end-date" name="end_date" value="<?php echo esc_attr(date('Y-m-d')); ?>">
                    </div>
                    <div class="orabooks-form-actions">
                        <button type="submit" class="orabooks-btn orabooks-btn-primary"><?php _e('Export Audit Bundle', 'orabooks'); ?></button>
                    </div>
                </form>
                <div id="orabooks-nc-audit-result" class="orabooks-message"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Async Queue Dashboard Shortcode (Admin only)
     */
    public function async_queue_dashboard() {
        if (!current_user_can('manage_options')) {
            return '<p>' . __('Access denied.', 'orabooks') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="orabooks-async-queue-dashboard">
            <h2><?php _e('Async Job Queue', 'orabooks'); ?></h2>
            
            <div class="orabooks-nc-provider-actions" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <button id="orabooks-aq-refresh" class="orabooks-btn orabooks-btn-secondary orabooks-btn-sm">
                    🔄 <?php _e('Refresh Stats', 'orabooks'); ?>
                </button>
                <button class="orabooks-btn orabooks-btn-primary orabooks-btn-sm orabooks-aq-export-trigger" data-export-type="async_queue_data" data-format="csv">📊 <?php _e('Export CSV', 'orabooks'); ?></button>
                <button class="orabooks-btn orabooks-btn-sm orabooks-aq-export-trigger" data-export-type="async_queue_data" data-format="pdf">📄 <?php _e('Export PDF', 'orabooks'); ?></button>
                <span style="color:#666;font-size:12px;">📁 <?php _e('Async export of queue data.', 'orabooks'); ?></span>
            </div>
            <div id="orabooks-aq-export-msg" class="orabooks-message" style="display:none;"></div>
            
            <!-- Stats Summary -->
            <div class="orabooks-commission-stats" id="orabooks-aq-stats">
                <div class="orabooks-stat-card">
                    <h3><?php _e('Total Jobs', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-number" id="aq-total">—</p>
                </div>
                <div class="orabooks-stat-card">
                    <h3><?php _e('Pending', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-number" id="aq-pending">—</p>
                </div>
                <div class="orabooks-stat-card">
                    <h3><?php _e('Processing', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-number" id="aq-processing">—</p>
                </div>
                <div class="orabooks-stat-card">
                    <h3><?php _e('Completed', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-number" id="aq-completed">—</p>
                </div>
                <div class="orabooks-stat-card">
                    <h3><?php _e('Failed', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-number" id="aq-failed">—</p>
                </div>
                <div class="orabooks-stat-card">
                    <h3><?php _e('Dead Letter', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-number" id="aq-dead">—</p>
                </div>
            </div>
            
            <!-- Performance Metrics -->
            <div class="orabooks-commission-stats">
                <div class="orabooks-stat-card">
                    <h3><?php _e('Avg Latency (24h)', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-amount" id="aq-latency">—</p>
                </div>
                <div class="orabooks-stat-card">
                    <h3><?php _e('Failure Rate (24h)', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-amount" id="aq-failure-rate">—</p>
                </div>
            </div>
            
            <!-- Recent Failures -->
            <h3><?php _e('Recent Failures / Dead Letters', 'orabooks'); ?></h3>
            <table class="orabooks-table">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'orabooks'); ?></th>
                        <th><?php _e('Type', 'orabooks'); ?></th>
                        <th><?php _e('Retries', 'orabooks'); ?></th>
                        <th><?php _e('Error', 'orabooks'); ?></th>
                        <th><?php _e('Last Attempt', 'orabooks'); ?></th>
                        <th><?php _e('Action', 'orabooks'); ?></th>
                    </tr>
                </thead>
                <tbody id="orabooks-aq-failures-body">
                    <tr><td colspan="6"><?php _e('Loading...', 'orabooks'); ?></td></tr>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    // ================================================================
    // EXPORT STATUS SHORTCODE (SL-114)
    // ================================================================

    /**
     * Export Status Page Shortcode
     * Shows "My Exports" table with status, download, cancel.
     */
    public function export_status() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return '<p>' . __('Please log in to view your exports.', 'orabooks') . '</p>';
        }
        return $this->react_app('/my-exports', 'Loading exports...');
    }

    /**
     * CSV Import page shortcode (SL-113).
     */
    public function csv_import_page() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return '<p>' . __('Please log in to import CSV data.', 'orabooks') . '</p>';
        }
        return $this->react_app('/csv-imports', 'Loading CSV imports...');
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