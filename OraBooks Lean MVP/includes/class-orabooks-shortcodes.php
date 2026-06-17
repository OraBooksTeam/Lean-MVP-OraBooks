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
            add_shortcode('orabooks_export_status', [self::$instance, 'export_status']);
            add_shortcode('orabooks_export_button', [self::$instance, 'export_button']);
            add_shortcode('orabooks_customers', [self::$instance, 'customers_page']);
            add_shortcode('orabooks_invoices', [self::$instance, 'invoices_page']);
            add_shortcode('orabooks_chart_of_accounts', [self::$instance, 'chart_of_accounts_page']);
            add_shortcode('orabooks_journals', [self::$instance, 'journals_page']);
            add_shortcode('orabooks_profile', [self::$instance, 'profile_page']);
        }
        return self::$instance;
    }

    private function react_app($route = '/dashboard', $loading_text = 'Loading OraBooks...') {
        ob_start();
        ?>
        <div class="orabooks-react-page">
            <div id="orabooks-app-root" data-initial-route="<?php echo esc_attr($route); ?>">
                <p><?php echo esc_html__($loading_text, 'orabooks'); ?></p>
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
        ob_start();
        ?>
        <div class="orabooks-partner-dashboard">
            <!-- Export Action Bar -->
            <div class="orabooks-coa-export-actions" style="margin-bottom:16px;padding:12px 16px;background:#f0f6fc;border:1px solid #c5d9ed;border-radius:6px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <span style="font-weight:600;color:#1d2327;font-size:13px;">📊 <?php _e('Export:', 'orabooks'); ?></span>
                <button class="orabooks-btn orabooks-btn-secondary orabooks-btn-sm orabooks-partner-export-trigger" data-export-type="commission_data" data-format="csv"><?php _e('Export Commissions CSV', 'orabooks'); ?></button>
                <button class="orabooks-btn orabooks-btn-sm orabooks-partner-export-trigger" data-export-type="commission_data" data-format="pdf"><?php _e('Export Commissions PDF', 'orabooks'); ?></button>
                <span style="color:#666;font-size:12px;margin-left:4px;">📁 <?php _e('Async — you\'ll get a notification when ready.', 'orabooks'); ?></span>
            </div>
            <div id="orabooks-partner-export-msg" class="orabooks-message" style="display:none;"></div>

            <!-- Status Banners -->
            <div id="orabooks-status-banners" class="orabooks-status-banners"></div>
            
            <!-- Partner Info Header -->
            <div class="orabooks-partner-header">
                <div class="orabooks-partner-code-section">
                    <h2><?php _e('Partner Program', 'orabooks'); ?></h2>
                    <div class="orabooks-partner-code-box">
                        <label><?php _e('Your Partner Code', 'orabooks'); ?></label>
                        <div class="orabooks-code-display">
                            <input type="text" id="orabooks-dash-partner-code" readonly class="orabooks-code-input">
                            <button id="orabooks-dash-copy-code" class="orabooks-btn orabooks-btn-secondary">
                                <?php _e('Copy Code', 'orabooks'); ?>
                            </button>
                        </div>
                    </div>
                    <div id="orabooks-partner-type-display" class="orabooks-partner-type-info"></div>
                </div>
            </div>
            
            <!-- Stats Cards Row 1: Attribution -->
            <div class="orabooks-stats-row">
                <div class="orabooks-stats-group-label">📊 <?php _e('Attribution', 'orabooks'); ?></div>
                <div class="orabooks-partner-stats">
                    <div class="orabooks-stat-card">
                        <h3><?php _e('Total Attributions', 'orabooks'); ?></h3>
                        <p class="orabooks-stat-number" id="orabooks-attr-total">0</p>
                    </div>
                    <div class="orabooks-stat-card orabooks-stat-verified">
                        <h3><?php _e('Verified', 'orabooks'); ?></h3>
                        <p class="orabooks-stat-number" id="orabooks-attr-verified">0</p>
                    </div>
                    <div class="orabooks-stat-card orabooks-stat-pending">
                        <h3><?php _e('Pending', 'orabooks'); ?></h3>
                        <p class="orabooks-stat-number" id="orabooks-attr-pending">0</p>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards Row 2: Commission -->
            <div class="orabooks-stats-row">
                <div class="orabooks-stats-group-label">💰 <?php _e('Commission (Accrued)', 'orabooks'); ?></div>
                <div class="orabooks-partner-stats">
                    <div class="orabooks-stat-card">
                        <h3><?php _e('Total Earned', 'orabooks'); ?></h3>
                        <p class="orabooks-stat-amount" id="orabooks-comm-earned">$0.00</p>
                    </div>
                    <div class="orabooks-stat-card">
                        <h3><?php _e('Pending Payout', 'orabooks'); ?></h3>
                        <p class="orabooks-stat-amount" id="orabooks-comm-pending">$0.00</p>
                    </div>
                    <div class="orabooks-stat-card orabooks-stat-paid">
                        <h3><?php _e('Paid', 'orabooks'); ?></h3>
                        <p class="orabooks-stat-amount" id="orabooks-comm-paid">$0.00</p>
                    </div>
                    <div class="orabooks-stat-card orabooks-stat-expired">
                        <h3><?php _e('Expired', 'orabooks'); ?></h3>
                        <p class="orabooks-stat-amount" id="orabooks-comm-expired">$0.00</p>
                    </div>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="orabooks-tabs">
                <button class="orabooks-tab orabooks-tab-active" data-tab="attributions">
                    <?php _e('Attribution List', 'orabooks'); ?>
                </button>
                <button class="orabooks-tab" data-tab="payouts">
                    <?php _e('Commission Payouts', 'orabooks'); ?>
                </button>
            </div>
            
            <!-- Tab: Attribution List -->
            <div id="orabooks-tab-attributions" class="orabooks-tab-content orabooks-tab-content-active">
                <table class="orabooks-table">
                    <thead>
                        <tr>
                            <th><?php _e('Customer', 'orabooks'); ?></th>
                            <th><?php _e('Attribution Date', 'orabooks'); ?></th>
                            <th><?php _e('Attribution Status (SL-013)', 'orabooks'); ?></th>
                            <th><?php _e('Commission Status (SL-068)', 'orabooks'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="orabooks-attr-table-body">
                        <tr><td colspan="4"><?php _e('Loading...', 'orabooks'); ?></td></tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Tab: Commission Payout Breakdown (Gross/Fee/Net) -->
            <div id="orabooks-tab-payouts" class="orabooks-tab-content">
                <table class="orabooks-table">
                    <thead>
                        <tr>
                            <th><?php _e('Period', 'orabooks'); ?></th>
                            <th><?php _e('Gross Commission', 'orabooks'); ?></th>
                            <th><?php _e('Transaction Fee', 'orabooks'); ?></th>
                            <th><?php _e('Net Payout', 'orabooks'); ?></th>
                            <th><?php _e('Status', 'orabooks'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="orabooks-payout-breakdown-body">
                        <tr><td colspan="5"><?php _e('Loading...', 'orabooks'); ?></td></tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Reactivation Modal -->
            <div id="orabooks-reactivation-modal" class="orabooks-modal" style="display:none;">
                <div class="orabooks-modal-content">
                    <span class="orabooks-modal-close">&times;</span>
                    <h3><?php _e('Request Reactivation', 'orabooks'); ?></h3>
                    <p><?php _e('Please provide a reason for reactivation (optional).', 'orabooks'); ?></p>
                    <textarea id="orabooks-reactivation-reason" rows="3" placeholder="<?php esc_attr_e('Your reason for reactivation...', 'orabooks'); ?>"></textarea>
                    <button id="orabooks-submit-reactivation" class="orabooks-btn orabooks-btn-primary">
                        <?php _e('Submit Request', 'orabooks'); ?>
                    </button>
                    <div id="orabooks-reactivation-message" class="orabooks-message"></div>
                </div>
            </div>
            
            <div id="orabooks-partner-dashboard-message"></div>
        </div>
        <?php
        return ob_get_clean();
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
        ob_start();
        ?>
        <div class="orabooks-notification-center">
            <div class="orabooks-nc-header">
                <h2><?php _e('Notifications', 'orabooks'); ?></h2>
                <div class="orabooks-nc-actions">
                    <button id="orabooks-nc-mark-all-read" class="orabooks-btn orabooks-btn-secondary orabooks-btn-sm">
                        <?php _e('Mark All Read', 'orabooks'); ?>
                    </button>
                    <a href="<?php echo esc_url(add_query_arg('tab', 'preferences')); ?>" class="orabooks-btn orabooks-btn-secondary orabooks-btn-sm">
                        ⚙️ <?php _e('Preferences', 'orabooks'); ?>
                    </a>
                    <button class="orabooks-btn orabooks-btn-secondary orabooks-btn-sm orabooks-notif-export-trigger" data-export-type="notification_log" data-format="csv">📊 <?php _e('Export CSV', 'orabooks'); ?></button>
                    <button class="orabooks-btn orabooks-btn-sm orabooks-notif-export-trigger" data-export-type="notification_log" data-format="pdf">📄 <?php _e('Export PDF', 'orabooks'); ?></button>
                </div>
            </div>
            <div id="orabooks-notif-export-msg" class="orabooks-message" style="display:none;"></div>
            
            <!-- Filters -->
            <div class="orabooks-nc-filters">
                <select id="orabooks-nc-filter-priority">
                    <option value=""><?php _e('All Priorities', 'orabooks'); ?></option>
                    <option value="critical">🔴 <?php _e('Critical', 'orabooks'); ?></option>
                    <option value="high">🟠 <?php _e('High', 'orabooks'); ?></option>
                    <option value="normal">🔵 <?php _e('Normal', 'orabooks'); ?></option>
                    <option value="low">⚪ <?php _e('Low', 'orabooks'); ?></option>
                </select>
                <select id="orabooks-nc-filter-status">
                    <option value=""><?php _e('All Status', 'orabooks'); ?></option>
                    <option value="unread"><?php _e('Unread', 'orabooks'); ?></option>
                    <option value="delivered"><?php _e('Read', 'orabooks'); ?></option>
                </select>
                <input type="text" id="orabooks-nc-filter-event" placeholder="<?php esc_attr_e('Event type...', 'orabooks'); ?>">
                <button id="orabooks-nc-filter-apply" class="orabooks-btn orabooks-btn-sm"><?php _e('Filter', 'orabooks'); ?></button>
            </div>
            
            <!-- Unread Badge -->
            <div id="orabooks-nc-unread-badge" class="orabooks-nc-unread-badge"></div>
            
            <!-- Notification List -->
            <div id="orabooks-nc-list" class="orabooks-nc-list">
                <p class="orabooks-loading"><?php _e('Loading notifications...', 'orabooks'); ?></p>
            </div>
            
            <!-- Delivery proof modal -->
            <div id="orabooks-nc-proof-modal" class="orabooks-modal" style="display:none;">
                <div class="orabooks-modal-content">
                    <span class="orabooks-modal-close">&times;</span>
                    <h3><?php _e('Delivery Proof', 'orabooks'); ?></h3>
                    <pre id="orabooks-nc-proof-content" class="orabooks-proof-content"></pre>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Notification Preferences Shortcode
     * Allows user to configure notification channels, quiet hours, digest, language, escalation.
     */
    public function notification_preferences() {
        if (!get_current_user_id()) {
            return '<p>' . __('Please log in to manage notification preferences.', 'orabooks') . '</p>';
        }
        ob_start();
        ?>
        <div class="orabooks-notification-preferences">
            <h2><?php _e('Notification Preferences', 'orabooks'); ?></h2>
            
            <form id="orabooks-nc-prefs-form" class="orabooks-form">
                <div class="orabooks-form-group">
                    <label><?php _e('Notification Channels', 'orabooks'); ?></label>
                    <div class="orabooks-checkbox-group">
                        <label><input type="checkbox" name="channels[]" value="email"> <?php _e('Email', 'orabooks'); ?></label>
                        <label><input type="checkbox" name="channels[]" value="push"> <?php _e('Push', 'orabooks'); ?></label>
                        <label><input type="checkbox" name="channels[]" value="inapp"> <?php _e('In-App', 'orabooks'); ?></label>
                    </div>
                    <small><?php _e('🌍 "Email routed via nearest region for faster delivery."', 'orabooks'); ?></small>
                </div>
                
                <div class="orabooks-form-group">
                    <label><?php _e('Quiet Hours', 'orabooks'); ?></label>
                    <div class="orabooks-time-range">
                        <input type="time" id="prefs-quiet-start" name="quiet_hours_start" placeholder="22:00">
                        <span><?php _e('to', 'orabooks'); ?></span>
                        <input type="time" id="prefs-quiet-end" name="quiet_hours_end" placeholder="08:00">
                    </div>
                    <small>🌙 <?php _e('No notifications during this period (except critical).', 'orabooks'); ?></small>
                </div>
                
                <div class="orabooks-form-group">
                    <label for="prefs-digest"><?php _e('Digest Frequency', 'orabooks'); ?></label>
                    <select id="prefs-digest" name="digest">
                        <option value="none"><?php _e('None (Real-time)', 'orabooks'); ?></option>
                        <option value="daily"><?php _e('Daily Summary', 'orabooks'); ?></option>
                        <option value="weekly"><?php _e('Weekly Summary', 'orabooks'); ?></option>
                    </select>
                </div>
                
                <div class="orabooks-form-group">
                    <label for="prefs-language"><?php _e('Language', 'orabooks'); ?></label>
                    <select id="prefs-language" name="language">
                        <option value="en">English</option>
                        <option value="bn">বাংলা</option>
                        <option value="ar">العربية</option>
                    </select>
                </div>
                
                <div class="orabooks-form-group">
                    <label>
                        <input type="checkbox" name="escalation_enabled" value="1">
                        <?php _e('Enable Cross-Channel Escalation', 'orabooks'); ?>
                    </label>
                    <small>🔁 <?php _e('"If email not read in 10 min, send push."', 'orabooks'); ?></small>
                </div>
                
                <div class="orabooks-form-actions">
                    <button type="submit" class="orabooks-btn orabooks-btn-primary"><?php _e('Save Preferences', 'orabooks'); ?></button>
                </div>
            </form>
            <div id="orabooks-nc-prefs-message" class="orabooks-message"></div>
        </div>
        <?php
        return ob_get_clean();
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
        
        ob_start();
        ?>
        <div class="orabooks-export-status">
            <div class="orabooks-nc-header">
                <h2><?php _e('My Exports', 'orabooks'); ?></h2>
                <div class="orabooks-nc-provider-actions">
                    <button id="orabooks-export-refresh" class="orabooks-btn orabooks-btn-secondary orabooks-btn-sm">
                        🔄 <?php _e('Refresh', 'orabooks'); ?>
                    </button>
                </div>
            </div>
            
            <div class="orabooks-info-banner">
                📁 <?php _e('Export to CSV/PDF. Large exports may take a few minutes. Download link expires in 7 days. Encrypted and watermarked for security.', 'orabooks'); ?>
            </div>
            
            <!-- Stats summary -->
            <div class="orabooks-commission-stats" id="orabooks-export-stats-summary">
                <div class="orabooks-stat-card">
                    <h3><?php _e('Total Exports', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-number" id="orabooks-export-total">—</p>
                </div>
                <div class="orabooks-stat-card">
                    <h3><?php _e('Pending', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-number" id="orabooks-export-pending">—</p>
                </div>
                <div class="orabooks-stat-card">
                    <h3><?php _e('Ready', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-number" id="orabooks-export-ready">—</p>
                </div>
            </div>
            
            <!-- Exports Table -->
            <table class="orabooks-table">
                <thead>
                    <tr>
                        <th><?php _e('Export Type', 'orabooks'); ?></th>
                        <th><?php _e('Format', 'orabooks'); ?></th>
                        <th><?php _e('Status', 'orabooks'); ?></th>
                        <th><?php _e('Size', 'orabooks'); ?></th>
                        <th><?php _e('Expires', 'orabooks'); ?></th>
                        <th><?php _e('Downloads', 'orabooks'); ?></th>
                        <th><?php _e('Actions', 'orabooks'); ?></th>
                    </tr>
                </thead>
                <tbody id="orabooks-export-table-body">
                    <tr><td colspan="7"><?php _e('Loading exports...', 'orabooks'); ?></td></tr>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <div id="orabooks-export-pagination" class="orabooks-pagination"></div>
        </div>
        <?php
        return ob_get_clean();
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
}