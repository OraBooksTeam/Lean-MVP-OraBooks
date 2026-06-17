<?php
/**
 * OraBooks Partner Commission Engine (SL-068)
 *
 * Manages the complete partner commission lifecycle:
 * - Platform-wide commission configuration
 * - Customer active status read model (owned by SL-068)
 * - Escrow schedule creation on partner_attribution_verified
 * - Monthly accrual release (journal entries via SL-001)
 * - Monthly payout batches (with fee tracking, min threshold, payout_hold)
 * - Expiry jobs (6-year liability reversal + escrow expiry)
 * - Commission payable aging report
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Commission {

    private static $instance = null;

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            
            // AJAX endpoints
            add_action('wp_ajax_orabooks_commission_stats', [self::$instance, 'ajax_commission_stats']);
            add_action('wp_ajax_orabooks_commission_earned', [self::$instance, 'ajax_commission_earned']);
            add_action('wp_ajax_orabooks_commission_payouts', [self::$instance, 'ajax_commission_payouts']);
            add_action('wp_ajax_orabooks_commission_aging', [self::$instance, 'ajax_commission_aging']);
            add_action('wp_ajax_orabooks_commission_config', [self::$instance, 'ajax_commission_config']);
            add_action('wp_ajax_orabooks_commission_update_config', [self::$instance, 'ajax_update_config']);
            add_action('wp_ajax_orabooks_commission_escrow_schedule', [self::$instance, 'ajax_escrow_schedule']);
            
            // Cron jobs
            add_action('orabooks_monthly_commission_release', [self::$instance, 'process_monthly_release']);
            add_action('orabooks_monthly_payout_batch', [self::$instance, 'process_payout_batch']);
            add_action('orabooks_daily_commission_expiry', [self::$instance, 'process_expiry']);
            add_action('orabooks_daily_active_status_refresh', [self::$instance, 'refresh_all_customer_active_status']);
            
            // Listen for attribution verified events (via outbox or direct call)
            add_action('orabooks_partner_attribution_verified', [self::$instance, 'on_attribution_verified'], 10, 2);
        }
        return self::$instance;
    }

    /**
     * ============================================================
     * DATABASE SCHEMA (called from OraBooks_Database::install)
     * ============================================================
     */
    public static function get_create_table_sql() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $tables = [];
        $table_orgs = OraBooks_Database::table('organizations');
        $table_users = OraBooks_Database::table('users');
        $table_attributions = OraBooks_Database::table('partner_attributions');
        
        $table_config = OraBooks_Database::table('partner_commission_config');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_config} (
            id INT PRIMARY KEY,
            base_monthly_amount DECIMAL(20,2) NOT NULL DEFAULT 10.00,
            max_years INT NOT NULL DEFAULT 6,
            yearly_percentages JSON NOT NULL,
            currency CHAR(3) DEFAULT 'USD',
            min_payout_threshold DECIMAL(20,2) DEFAULT 25.00,
            customer_active_window_days INT DEFAULT 30,
            expiry_accounting_action ENUM('reverse_expense','income') DEFAULT 'reverse_expense',
            payout_fee_type ENUM('flat','percentage') DEFAULT 'percentage',
            payout_fee_rate DECIMAL(10,4) DEFAULT 2.5000,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) {$charset_collate};";

        $table_active = OraBooks_Database::table('customer_active_status');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_active} (
            customer_id BIGINT UNSIGNED PRIMARY KEY,
            is_active TINYINT(1) DEFAULT 0,
            last_paid_invoice_date DATE NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES {$table_users}(id) ON DELETE CASCADE
        ) {$charset_collate};";

        $table_escrow = OraBooks_Database::table('commission_escrow_schedule');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_escrow} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            attribution_id BIGINT UNSIGNED NOT NULL,
            partner_user_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            total_amount DECIMAL(20,2) NOT NULL,
            released_amount DECIMAL(20,2) DEFAULT 0,
            remaining_amount DECIMAL(20,2) NOT NULL,
            remaining_amount_status ENUM('pending','expired') DEFAULT 'pending',
            currency CHAR(3) DEFAULT 'USD',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (attribution_id) REFERENCES {$table_attributions}(id) ON DELETE CASCADE,
            FOREIGN KEY (partner_user_id) REFERENCES {$table_users}(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES {$table_users}(id) ON DELETE CASCADE,
            INDEX idx_partner (partner_user_id),
            INDEX idx_customer (customer_id),
            INDEX idx_attribution (attribution_id)
        ) {$charset_collate};";

        $table_release = OraBooks_Database::table('commission_release_schedule');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_release} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            escrow_id BIGINT UNSIGNED NOT NULL,
            release_month DATE NOT NULL,
            amount DECIMAL(20,2) NOT NULL,
            status ENUM('pending','released','skipped','expired') DEFAULT 'pending',
            released_at TIMESTAMP NULL,
            expires_at TIMESTAMP NULL,
            FOREIGN KEY (escrow_id) REFERENCES {$table_escrow}(id) ON DELETE CASCADE,
            UNIQUE KEY uk_escrow_month (escrow_id, release_month)
        ) {$charset_collate};";

        $table_earned = OraBooks_Database::table('commissions_earned');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_earned} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            partner_user_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            release_schedule_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(20,2) NOT NULL,
            earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            status ENUM('earned','paid','expired') DEFAULT 'earned',
            payout_id BIGINT UNSIGNED NULL,
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
            FOREIGN KEY (partner_user_id) REFERENCES {$table_users}(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES {$table_users}(id) ON DELETE CASCADE,
            FOREIGN KEY (release_schedule_id) REFERENCES {$table_release}(id),
            INDEX idx_partner_status (partner_user_id, status),
            INDEX idx_expires (expires_at),
            INDEX idx_org (org_id)
        ) {$charset_collate};";

        $table_events = OraBooks_Database::table('commission_event_consumptions');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_events} (
            event_id VARCHAR(128) PRIMARY KEY,
            attribution_id BIGINT UNSIGNED NOT NULL,
            processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (attribution_id) REFERENCES {$table_attributions}(id) ON DELETE CASCADE
        ) {$charset_collate};";

        $table_payouts = OraBooks_Database::table('commission_payouts');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_payouts} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            partner_user_id BIGINT UNSIGNED NOT NULL,
            gross_amount DECIMAL(20,2) NOT NULL,
            fee_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
            net_amount DECIMAL(20,2) GENERATED ALWAYS AS (gross_amount - fee_amount) STORED,
            payout_date DATE NULL,
            status ENUM('initiated','processing','settled','failed') DEFAULT 'initiated',
            bank_transaction_id BIGINT UNSIGNED NULL,
            reconciliation_id BIGINT UNSIGNED NULL,
            initiated_by BIGINT UNSIGNED NULL,
            settled_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
            FOREIGN KEY (partner_user_id) REFERENCES {$table_users}(id) ON DELETE CASCADE,
            INDEX idx_partner (partner_user_id),
            INDEX idx_status (status),
            INDEX idx_org (org_id)
        ) {$charset_collate};";

        $table_payout_items = OraBooks_Database::table('commission_payout_items');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_payout_items} (
            payout_id BIGINT UNSIGNED NOT NULL,
            earned_commission_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (payout_id, earned_commission_id),
            FOREIGN KEY (payout_id) REFERENCES {$table_payouts}(id) ON DELETE CASCADE,
            FOREIGN KEY (earned_commission_id) REFERENCES {$table_earned}(id) ON DELETE CASCADE
        ) {$charset_collate};";

        return $tables;
    }

    /**
     * ============================================================
     * SEED DEFAULT CONFIG
     * ============================================================
     */
    public static function seed_default_config() {
        global $wpdb;
        $table = OraBooks_Database::table('partner_commission_config');
        
        $exists = $wpdb->get_var("SELECT id FROM {$table} WHERE id = 1");
        if (!$exists) {
            $wpdb->insert(
                $table,
                [
                    'id' => 1,
                    'base_monthly_amount' => 10.00,
                    'max_years' => 6,
                    'yearly_percentages' => json_encode([20, 15, 10, 5, 2.5, 1]),
                    'currency' => 'USD',
                    'min_payout_threshold' => 25.00,
                    'customer_active_window_days' => 30,
                    'expiry_accounting_action' => 'reverse_expense',
                    'payout_fee_type' => 'percentage',
                    'payout_fee_rate' => 2.5000
                ],
                ['%d', '%f', '%d', '%s', '%s', '%f', '%d', '%s', '%s', '%f']
            );
        }
    }

    /**
     * ============================================================
     * GET PLATFORM CONFIG
     * ============================================================
     */
    public static function get_config() {
        global $wpdb;
        $table = OraBooks_Database::table('partner_commission_config');
        $config = wp_cache_get('orabooks_commission_config', 'orabooks');
        if ($config === false) {
            $config = $wpdb->get_row("SELECT * FROM {$table} WHERE id = 1");
            if ($config) {
                $config->base_monthly_amount = isset($config->base_monthly_amount) ? (float) $config->base_monthly_amount : 10.00;
                $config->max_years = isset($config->max_years) ? (int) $config->max_years : 6;
                $config->currency = $config->currency ?? 'USD';
                $config->min_payout_threshold = isset($config->min_payout_threshold) ? (float) $config->min_payout_threshold : 25.00;
                $config->customer_active_window_days = isset($config->customer_active_window_days) ? (int) $config->customer_active_window_days : 30;
                $config->expiry_accounting_action = $config->expiry_accounting_action ?? 'reverse_expense';
                $config->payout_fee_type = $config->payout_fee_type ?? 'percentage';
                $config->payout_fee_rate = isset($config->payout_fee_rate) ? (float) $config->payout_fee_rate : 2.5;

                if (isset($config->yearly_percentages) && is_string($config->yearly_percentages)) {
                    $decoded = json_decode($config->yearly_percentages, true);
                    $config->yearly_percentages = is_array($decoded) ? $decoded : [20, 15, 10, 5, 2.5, 1];
                } elseif (!isset($config->yearly_percentages) || !is_array($config->yearly_percentages)) {
                    $config->yearly_percentages = [20, 15, 10, 5, 2.5, 1];
                }
                wp_cache_set('orabooks_commission_config', $config, 'orabooks', 300);
            }
        }
        return $config;
    }

    /**
     * ============================================================
     * CUSTOMER ACTIVE STATUS READ MODEL (Owned by SL-068)
     * ============================================================
     */
    
    /**
     * Check if a customer is active based on the read model
     */
    public static function is_customer_active($customer_id) {
        global $wpdb;
        $table = OraBooks_Database::table('customer_active_status');
        
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT is_active FROM {$table} WHERE customer_id = %d",
            $customer_id
        ));
        
        if ($status === null) {
            // Not in read model yet; compute on the fly
            self::refresh_customer_active_status($customer_id);
            $status = $wpdb->get_var($wpdb->prepare(
                "SELECT is_active FROM {$table} WHERE customer_id = %d",
                $customer_id
            ));
        }
        
        return (bool) $status;
    }

    /**
     * Refresh active status for a single customer
     * Uses SL-021 customers.is_active as the authoritative truth source.
     * Falls back to SL-021 invoices table if customers table not yet seeded,
     * then to optimistic active if neither is available.
     */
    public static function refresh_customer_active_status($customer_id) {
        global $wpdb;
        
        $table_active = OraBooks_Database::table('customer_active_status');
        $table_customers = OraBooks_Database::table('customers');
        $table_invoices = OraBooks_Database::table('invoices');
        $config = self::get_config();
        $window_days = $config ? $config->customer_active_window_days : 30;
        
        $is_active = 0;
        $last_paid_date = null;
        
        // Check if SL-021 customers table exists
        $customers_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_customers}'");
        
        if ($customers_exists) {
            // SL-021 exists: use customers.is_active as truth source
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT is_active, last_paid_invoice_date FROM {$table_customers} WHERE id = %d",
                $customer_id
            ));
            
            if ($customer) {
                $is_active = (int) $customer->is_active;
                $last_paid_date = $customer->last_paid_invoice_date;
            } else {
                // Customer not yet in SL-021 customers table — check invoices directly
                $invoice = $wpdb->get_row($wpdb->prepare(
                    "SELECT MAX(transaction_date) as last_paid
                     FROM {$table_invoices} 
                     WHERE customer_id = %d 
                       AND payment_status IN ('paid', 'partial') 
                       AND workflow_status = 'posted'
                       AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)",
                    $customer_id,
                    $window_days
                ));
                
                if ($invoice && $invoice->last_paid) {
                    $is_active = 1;
                    $last_paid_date = $invoice->last_paid;
                }
            }
        } else {
            // SL-021 not yet installed: treat as active (optimistic)
            $is_active = 1;
        }
        
        // Upsert into read model
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_active} (customer_id, is_active, last_paid_invoice_date, updated_at)
             VALUES (%d, %d, %s, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                is_active = VALUES(is_active),
                last_paid_invoice_date = VALUES(last_paid_invoice_date),
                updated_at = VALUES(updated_at)",
            $customer_id,
            $is_active,
            $last_paid_date ?: null
        ));
        
        return (bool) $is_active;
    }

    /**
     * Refresh all customer active statuses (daily cron)
     */
    public static function refresh_all_customer_active_status() {
        global $wpdb;
        
        $table_active = OraBooks_Database::table('customer_active_status');
        $attrib_table = OraBooks_Database::table('partner_attributions');
        
        // Get all customers from active status table and attributions
        $customer_ids = $wpdb->get_col(
            "SELECT DISTINCT customer_id FROM {$attrib_table} WHERE status = 'verified'"
        );
        
        $count = 0;
        foreach ($customer_ids as $cid) {
            self::refresh_customer_active_status($cid);
            $count++;
        }
        
        orabooks_log_event('customer_active_status_refreshed', 
            "Active customer status read model refreshed for {$count} customers", 
            'info', ['count' => $count], null, null);
    }

    /**
     * ============================================================
     * EVENT CONSUMER: partner_attribution_verified
     * ============================================================
     */
    public static function on_attribution_verified($attribution_id, $attribution_data) {
        return self::create_escrow_from_attribution($attribution_id, $attribution_data);
    }

    /**
     * Create escrow schedule when attribution is verified
     */
    public static function create_escrow_from_attribution($attribution_id, $attribution_data = null) {
        global $wpdb;
        
        if (!$attribution_data) {
            $table_attributions = OraBooks_Database::table('partner_attributions');
            $attribution_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_attributions} WHERE id = %d",
                $attribution_id
            ));
        }
        
        if (!$attribution_data) {
            return new WP_Error('not_found', 'Attribution not found');
        }
        
        // Idempotency check
        $event_id = 'attribution_' . $attribution_id;
        $table_events = OraBooks_Database::table('commission_event_consumptions');
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT event_id FROM {$table_events} WHERE event_id = %s",
            $event_id
        ));
        
        if ($existing) {
            return true; // Already processed
        }
        
        $config = self::get_config();
        if (!$config) {
            return new WP_Error('no_config', 'Commission configuration not found');
        }
        
        $partner_user_id = $attribution_data->partner_user_id;
        $customer_user_id = $attribution_data->customer_user_id;
        
        // Find partner's org_id
        $table_users = OraBooks_Database::table('users');
        $partner = $wpdb->get_row($wpdb->prepare(
            "SELECT org_id FROM {$table_users} WHERE id = %d",
            $partner_user_id
        ));
        
        if (!$partner || !$partner->org_id) {
            return new WP_Error('partner_no_org', 'Partner has no organization');
        }
        
        // Check customer active status
        $is_active = self::is_customer_active($customer_user_id);
        if (!$is_active) {
            orabooks_log_event('commission_escrow_skipped_inactive', 
                "Escrow skipped: customer {$customer_user_id} is not active", 
                'info', [
                    'attribution_id' => $attribution_id,
                    'partner_user_id' => $partner_user_id
                ], $partner_user_id, $partner->org_id);
            return true;
        }
        
        // Calculate total commission based on yearly percentages
        $yearly_pcts = $config->yearly_percentages;
        if (!is_array($yearly_pcts)) {
            $yearly_pcts = json_decode(json_encode($yearly_pcts), true);
        }
        
        $total_amount = 0;
        foreach ($yearly_pcts as $pct) {
            $total_amount += ($config->base_monthly_amount * 12) * ($pct / 100);
        }
        
        // Create escrow schedule
        $table_escrow = OraBooks_Database::table('commission_escrow_schedule');
        $wpdb->insert(
            $table_escrow,
            [
                'attribution_id' => $attribution_id,
                'partner_user_id' => $partner_user_id,
                'customer_id' => $customer_user_id,
                'total_amount' => $total_amount,
                'released_amount' => 0,
                'remaining_amount' => $total_amount,
                'remaining_amount_status' => 'pending',
                'currency' => $config->currency
            ],
            ['%d', '%d', '%d', '%f', '%f', '%f', '%s', '%s']
        );
        
        $escrow_id = $wpdb->insert_id;
        
        // Precompute monthly release schedule
        $table_release = OraBooks_Database::table('commission_release_schedule');
        $attribution_date = $attribution_data->attribution_date ?: current_time('mysql');
        $start_month = new DateTime($attribution_date);
        $start_month->modify('first day of next month');
        $start_month->setTime(0, 0, 0);
        
        $total_months = $config->max_years * 12;
        $monthly_amount = $total_amount / $total_months;
        
        for ($i = 0; $i < $total_months; $i++) {
            $release_date = clone $start_month;
            $release_date->modify("+{$i} months");
            
            // Last day of the month
            $last_day = clone $release_date;
            $last_day->modify('last day of this month');
            
            $expires_at = clone $last_day;
            $expires_at->modify('+6 years');
            
            $wpdb->insert(
                $table_release,
                [
                    'escrow_id' => $escrow_id,
                    'release_month' => $last_day->format('Y-m-d'),
                    'amount' => $monthly_amount,
                    'status' => 'pending',
                    'expires_at' => $expires_at->format('Y-m-d H:i:s')
                ],
                ['%d', '%s', '%f', '%s', '%s']
            );
        }
        
        // Record event consumption
        $wpdb->insert(
            $table_events,
            [
                'event_id' => $event_id,
                'attribution_id' => $attribution_id
            ],
            ['%s', '%d']
        );
        
        orabooks_log_event('commission_escrow_created', 
            "Commission escrow #{$escrow_id} created for partner #{$partner_user_id}, customer #{$customer_user_id}, total: {$total_amount}", 
            'info', [
                'escrow_id' => $escrow_id,
                'attribution_id' => $attribution_id,
                'partner_user_id' => $partner_user_id,
                'customer_id' => $customer_user_id,
                'total_amount' => $total_amount
            ], $partner_user_id, $partner->org_id);
        
        return [
            'escrow_id' => $escrow_id,
            'total_amount' => $total_amount,
            'total_months' => $total_months,
            'monthly_amount' => $monthly_amount
        ];
    }

    /**
     * ============================================================
     * MONTHLY RELEASE JOB (Accrual Entries)
     * ============================================================
     */
    public static function process_monthly_release() {
        global $wpdb;
        
        $table_release = OraBooks_Database::table('commission_release_schedule');
        $table_escrow = OraBooks_Database::table('commission_escrow_schedule');
        $table_earned = OraBooks_Database::table('commissions_earned');
        $table_active = OraBooks_Database::table('customer_active_status');
        $table_users = OraBooks_Database::table('users');
        
        $config = self::get_config();
        
        // Find pending releases where release_month <= last month
        $releases = $wpdb->get_results(
            "SELECT r.*, e.partner_user_id, e.customer_id, e.attribution_id, e.remaining_amount, e.released_amount
             FROM {$table_release} r
             JOIN {$table_escrow} e ON r.escrow_id = e.id
             WHERE r.status = 'pending'
               AND r.release_month <= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
             ORDER BY r.release_month ASC
             LIMIT 1000"
        );
        
        $released_count = 0;
        $skipped_count = 0;
        
        foreach ($releases as $release) {
            // Check customer active status
            $is_active = self::is_customer_active($release->customer_id);
            
            if ($is_active) {
                // Find partner's org_id
                $partner = $wpdb->get_row($wpdb->prepare(
                    "SELECT org_id FROM {$table_users} WHERE id = %d",
                    $release->partner_user_id
                ));
                
                if (!$partner || !$partner->org_id) {
                    continue;
                }
                
                $org_id = $partner->org_id;
                $amount = $release->amount;
                $release_month = $release->release_month;
                
                // Begin transaction for journal entry
                $wpdb->query("START TRANSACTION");
                
                try {
                    // Create journal entry via SL-001 posting engine
                    $journal_result = self::create_commission_journal_entry(
                        $org_id,
                        $release_month,
                        $amount,
                        $release->partner_user_id,
                        'Commission monthly release'
                    );
                    
                    if (is_wp_error($journal_result)) {
                        $wpdb->query("ROLLBACK");
                        continue;
                    }
                    
                    // Insert earned commission record
                    $expires_at = date('Y-m-d H:i:s', strtotime($release_month . ' +6 years'));
                    $wpdb->insert(
                        $table_earned,
                        [
                            'org_id' => $org_id,
                            'partner_user_id' => $release->partner_user_id,
                            'customer_id' => $release->customer_id,
                            'release_schedule_id' => $release->id,
                            'amount' => $amount,
                            'expires_at' => $expires_at,
                            'status' => 'earned'
                        ],
                        ['%d', '%d', '%d', '%d', '%f', '%s', '%s']
                    );
                    
                    // Update release schedule
                    $wpdb->update(
                        $table_release,
                        [
                            'status' => 'released',
                            'released_at' => current_time('mysql')
                        ],
                        ['id' => $release->id],
                        ['%s', '%s'],
                        ['%d']
                    );
                    
                    // Update escrow schedule
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$table_escrow} 
                         SET released_amount = released_amount + %f,
                             remaining_amount = remaining_amount - %f
                         WHERE id = %d",
                        $amount,
                        $amount,
                        $release->escrow_id
                    ));
                    
                    $wpdb->query("COMMIT");
                    
                    orabooks_log_event('commission_monthly_released', 
                        "Monthly commission released: {$amount} for partner #{$release->partner_user_id}", 
                        'info', [
                            'release_id' => $release->id,
                            'escrow_id' => $release->escrow_id,
                            'amount' => $amount,
                            'partner_user_id' => $release->partner_user_id,
                            'customer_id' => $release->customer_id
                        ], $release->partner_user_id, $org_id);
                    
                    $released_count++;
                    
                } catch (Exception $e) {
                    $wpdb->query("ROLLBACK");
                    orabooks_log_event('commission_release_failed', 
                        "Monthly release failed: " . $e->getMessage(), 
                        'warning', [
                            'release_id' => $release->id,
                            'error' => $e->getMessage()
                        ], $release->partner_user_id, null);
                }
            } else {
                // Customer inactive - skip release
                $wpdb->update(
                    $table_release,
                    ['status' => 'skipped'],
                    ['id' => $release->id],
                    ['%s'],
                    ['%d']
                );
                
                orabooks_log_event('commission_release_skipped_inactive', 
                    "Commission release skipped: customer #{$release->customer_id} inactive", 
                    'info', [
                        'release_id' => $release->id,
                        'customer_id' => $release->customer_id
                    ], $release->partner_user_id, null);
                
                $skipped_count++;
            }
        }
        
        $total = $released_count + $skipped_count;
        if ($total > 0) {
            orabooks_log_event('commission_monthly_release_batch', 
                "Monthly release batch completed: {$released_count} released, {$skipped_count} skipped", 
                'info', [
                    'released' => $released_count,
                    'skipped' => $skipped_count
                ], null, null);
        }
        
        return ['released' => $released_count, 'skipped' => $skipped_count];
    }

    /**
     * Create commission journal entry (Dr Commission Expense, Cr Commission Payable)
     */
    /**
     * Find or create a system org for platform-level accounting entries.
     * Commission entries (Dr Expense, Cr Payable) are made in this org
     * because partner orgs do not have a Chart of Accounts (per SL-017).
     */
    private static function get_or_create_system_org() {
        global $wpdb;
        
        $table_orgs = OraBooks_Database::table('organizations');
        $table_accounts = OraBooks_Database::table('accounts');
        $table_balances = OraBooks_Database::table('account_balances');
        
        // Look for existing system org
        $system_org = $wpdb->get_row(
            "SELECT id FROM {$table_orgs} WHERE subdomain = 'orabooks-system' LIMIT 1"
        );
        
        if ($system_org) {
            return $system_org->id;
        }
        
        // Create system org for platform accounting
        $wpdb->insert(
            $table_orgs,
            [
                'name' => 'OraBooks Platform',
                'tier' => 'enterprise',
                'subdomain' => 'orabooks-system',
                'owner_id' => 0,
                'status' => 'active',
                'organization_type' => 'customer'
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s']
        );
        
        $system_org_id = $wpdb->insert_id;
        
        // Create essential accounts for commission tracking
        $accounts = [
            ['code' => '5000', 'name' => 'Commission Expense', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '2000', 'name' => 'Commission Payable', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2100', 'name' => 'Commission Fee Payable', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '4000', 'name' => 'Expired Commission Income', 'type' => 'revenue', 'normal_balance' => 'credit'],
        ];
        
        foreach ($accounts as $acc) {
            $wpdb->insert(
                $table_accounts,
                [
                    'org_id' => $system_org_id,
                    'code' => $acc['code'],
                    'name' => $acc['name'],
                    'type' => $acc['type'],
                    'normal_balance' => $acc['normal_balance'],
                    'system_generated' => 1,
                    'is_active' => 1
                ],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%d']
            );
            
            $account_id = $wpdb->insert_id;
            $wpdb->insert(
                $table_balances,
                ['org_id' => $system_org_id, 'account_id' => $account_id, 'balance' => 0],
                ['%d', '%d', '%f']
            );
        }
        
        return $system_org_id;
    }

    /**
     * Create commission journal entry in the system org.
     * Commission accrual entries go to the platform-level system org,
     * not the partner's org (which has no CoA per SL-017).
     */
    private static function create_commission_journal_entry($org_id, $release_month, $amount, $partner_user_id, $description) {
        $system_org_id = self::get_or_create_system_org();
        
        // Check if posting engine exists
        if (class_exists('OraBooks_Posting') && method_exists('OraBooks_Posting', 'create_journal')) {
            // Find commission expense account
            $expense_account = OraBooks_COA::get_account_by_code($system_org_id, '5000');
            if (!$expense_account) {
                return new WP_Error('no_account', 'Commission expense account not found in system org');
            }
            
            // Find commission payable account
            $payable_account = OraBooks_COA::get_account_by_code($system_org_id, '2000');
            if (!$payable_account) {
                return new WP_Error('no_account', 'Commission payable account not found in system org');
            }
            
            // Create journal via posting engine
            $journal_id = OraBooks_Posting::create_journal([
                'org_id' => $system_org_id,
                'transaction_date' => $release_month,
                'source_type' => 'commission_release',
                'source_id' => $partner_user_id,
                'metadata' => [
                    'description' => $description,
                    'amount' => $amount,
                    'partner_user_id' => $partner_user_id,
                    'original_org_id' => $org_id
                ]
            ], 0); // System user
            
            if (is_wp_error($journal_id)) {
                return $journal_id;
            }
            
            // Add double-entry lines
            $result = OraBooks_Posting::add_lines($journal_id, [
                [
                    'account_code' => '5000', // Commission Expense
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => $description
                ],
                [
                    'account_code' => '2000', // Commission Payable
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => $description
                ]
            ]);
            
            if (is_wp_error($result)) {
                return $result;
            }
            
            // Auto-approve and post (system journal)
            $approve = OraBooks_Posting::approve_journal($journal_id, 0);
            if (is_wp_error($approve)) {
                return $approve;
            }
            
            $post = OraBooks_Posting::post_journal($journal_id, 0);
            if (is_wp_error($post)) {
                return $post;
            }
            
            return $post;
        }
        
        // Fallback: direct ledger entry when posting engine not available
        return self::create_direct_ledger_entry($system_org_id, $release_month, $amount, $partner_user_id, $description);
    }

    /**
     * Fallback: direct ledger entry when posting engine not available
     */
    private static function create_direct_ledger_entry($org_id, $release_month, $amount, $partner_user_id, $description) {
        global $wpdb;
        
        $table_balances = OraBooks_Database::table('account_balances');
        $table_accounts = $wpdb->prefix . 'orabooks_accounts';
        
        // Find or create commission expense account (5000)
        $expense_account = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_accounts} WHERE org_id = %d AND code = '5000'",
            $org_id
        ));
        
        // Find or create commission payable account (2000)
        $payable_account = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_accounts} WHERE org_id = %d AND code = '2000'",
            $org_id
        ));
        
        if (!$expense_account || !$payable_account) {
            return new WP_Error('no_accounts', 'Required accounts not found for commission entry in system org');
        }
        
        // Update balances directly (simplified for MVP)
        // Dr Commission Expense (debit normal → increase debit)
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_balances} (org_id, account_id, balance) 
             VALUES (%d, %d, %f)
             ON DUPLICATE KEY UPDATE balance = balance + %f",
            $org_id, $expense_account->id, $amount, $amount
        ));
        
        // Cr Commission Payable (credit normal → increase credit)
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_balances} (org_id, account_id, balance) 
             VALUES (%d, %d, %f)
             ON DUPLICATE KEY UPDATE balance = balance - %f",
            $org_id, $payable_account->id, -$amount, -$amount
        ));
        
        orabooks_log_event('commission_direct_ledger', 
            "Direct ledger entry: Dr Expense {$amount}, Cr Payable {$amount}", 
            'info', [
                'org_id' => $org_id,
                'amount' => $amount,
                'expense_account' => $expense_account->id,
                'payable_account' => $payable_account->id
            ], $partner_user_id, $org_id);
        
        return ['expense_account' => $expense_account->id, 'payable_account' => $payable_account->id];
    }

    /**
     * ============================================================
     * MONTHLY PAYOUT BATCH JOB
     * ============================================================
     */
    public static function process_payout_batch($force = false) {
        global $wpdb;
        
        // Only run on the 1st day of the month (spec: "Monthly payout batch job (1st of month)")
        if (!$force && date('j') != 1) {
            return ['batches_created' => 0, 'skipped_not_first_day' => 1, 'skipped_payout_hold' => 0, 'skipped_below_threshold' => 0];
        }
        
        $table_earned = OraBooks_Database::table('commissions_earned');
        $table_payouts = OraBooks_Database::table('commission_payouts');
        $table_payout_items = OraBooks_Database::table('commission_payout_items');
        $table_orgs = OraBooks_Database::table('organizations');
        $table_users = OraBooks_Database::table('users');
        
        $config = self::get_config();
        if (!$config) {
            return new WP_Error('no_config', 'Commission config not found');
        }
        
        $min_threshold = (float) $config->min_payout_threshold;
        
        // Group earned commissions by partner
        $earned_summary = $wpdb->get_results(
            "SELECT partner_user_id, SUM(amount) as total_pending
             FROM {$table_earned}
             WHERE status = 'earned'
               AND expires_at > NOW()
             GROUP BY partner_user_id"
        );
        
        $batches_created = 0;
        $skipped_payout_hold = 0;
        $skipped_below_threshold = 0;
        
        foreach ($earned_summary as $summary) {
            $partner_user_id = $summary->partner_user_id;
            $total_pending = (float) $summary->total_pending;
            
            if ($total_pending < $min_threshold) {
                orabooks_log_event('commission_payout_skipped_threshold',
                    "Payout skipped: partner #{$partner_user_id} pending total {$total_pending} below threshold {$min_threshold}",
                    'info', [
                        'partner_user_id' => $partner_user_id,
                        'pending_total' => $total_pending,
                        'min_threshold' => $min_threshold
                    ], $partner_user_id, null);
                $skipped_below_threshold++;
                continue;
            }
            
            // Check partner org status
            $partner = $wpdb->get_row($wpdb->prepare(
                "SELECT u.org_id, o.status as org_status, o.organization_type
                 FROM {$table_users} u
                 JOIN {$table_orgs} o ON u.org_id = o.id
                 WHERE u.id = %d",
                $partner_user_id
            ));
            
            // Block payout if org is on hold, fraud_freeze, or suspended (per SL-004 spec)
            $blocked_statuses = ['payout_hold', 'fraud_freeze', 'suspended'];
            $partner_org_status = $partner ? $partner->org_status : 'no_org';
            if (!$partner || in_array($partner_org_status, $blocked_statuses)) {
                orabooks_log_event('commission_payout_skipped_hold', 
                    "Payout skipped: partner #{$partner_user_id} status={$partner_org_status}", 
                    'info', [
                        'partner_user_id' => $partner_user_id,
                        'org_status' => $partner_org_status
                    ], $partner_user_id, $partner ? $partner->org_id : null);
                $skipped_payout_hold++;
                continue;
            }
            
            // Compute fee
            $gross_amount = $total_pending;
            $fee_amount = 0;
            
            if ($config->payout_fee_type === 'percentage') {
                $fee_amount = round($gross_amount * ($config->payout_fee_rate / 100), 2);
            } else {
                // Flat fee per payout
                $fee_amount = $config->payout_fee_rate;
            }
            
            // Create payout record
            $wpdb->insert(
                $table_payouts,
                [
                    'org_id' => $partner->org_id,
                    'partner_user_id' => $partner_user_id,
                    'gross_amount' => $gross_amount,
                    'fee_amount' => $fee_amount,
                    'payout_date' => current_time('Y-m-d'),
                    'status' => 'initiated'
                ],
                ['%d', '%d', '%f', '%f', '%s', '%s']
            );
            
            $payout_id = $wpdb->insert_id;
            
            // Link earned commissions to this payout
            $earned_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$table_earned}
                 WHERE partner_user_id = %d
                   AND status = 'earned'
                   AND expires_at > NOW()",
                $partner_user_id
            ));
            
            foreach ($earned_ids as $earned_id) {
                $wpdb->insert(
                    $table_payout_items,
                    [
                        'payout_id' => $payout_id,
                        'earned_commission_id' => $earned_id
                    ],
                    ['%d', '%d']
                );
                
                // Mark as pending payout
                $wpdb->update(
                    $table_earned,
                    ['payout_id' => $payout_id],
                    ['id' => $earned_id],
                    ['%d'],
                    ['%d']
                );
            }
            
            orabooks_log_event('payout_batch_created', 
                "Payout batch #{$payout_id} created for partner #{$partner_user_id}: gross={$gross_amount}, fee={$fee_amount}", 
                'info', [
                    'payout_id' => $payout_id,
                    'partner_user_id' => $partner_user_id,
                    'gross_amount' => $gross_amount,
                    'fee_amount' => $fee_amount,
                    'net_amount' => $gross_amount - $fee_amount,
                    'earned_count' => count($earned_ids)
                ], $partner_user_id, $partner->org_id);
            
            // Publish via EventBus (SL-302)
            $event_payload = [
                'payout_id' => $payout_id,
                'partner_user_id' => $partner_user_id,
                'gross_amount' => $gross_amount,
                'fee_amount' => $fee_amount,
                'net_amount' => $gross_amount - $fee_amount,
                'earned_count' => count($earned_ids),
                'org_id' => $partner->org_id,
                'priority' => 'high',
            ];

            if (function_exists('orabooks_publish_event')) {
                orabooks_publish_event('payout_batch_created', $payout_id, $event_payload);
            }

            // Fire notification event for SL-250 (direct, for immediate delivery)
            do_action('orabooks_payout_batch_created', $payout_id, $event_payload);
            
            $batches_created++;
        }
        
        orabooks_log_event('payout_batch_summary', 
            "Monthly payout batch completed: {$batches_created} created, {$skipped_payout_hold} hold, {$skipped_below_threshold} below threshold", 
            'info', [
                'batches_created' => $batches_created,
                'skipped_payout_hold' => $skipped_payout_hold,
                'skipped_below_threshold' => $skipped_below_threshold
            ], null, null);
        
        return [
            'batches_created' => $batches_created,
            'skipped_payout_hold' => $skipped_payout_hold,
            'skipped_below_threshold' => $skipped_below_threshold
        ];
    }

    /**
     * ============================================================
     * SETTLE PAYOUT (Called from SL-031 reconciliation)
     * ============================================================
     */
    public static function settle_payout($payout_id, $bank_transaction_id, $settlement_date = null) {
        global $wpdb;
        
        $table_payouts = OraBooks_Database::table('commission_payouts');
        $table_earned = OraBooks_Database::table('commissions_earned');
        
        $payout = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_payouts} WHERE id = %d",
            $payout_id
        ));
        
        if (!$payout) {
            return new WP_Error('not_found', 'Payout not found');
        }
        
        if ($payout->status === 'settled') {
            return true; // Already settled
        }
        
        $settlement_date = $settlement_date ?: current_time('Y-m-d');
        
        // Journal entry: Dr Commission Payable (gross), Cr Bank (net), Cr Commission Fee Payable (fee)
        // In MVP, this is handled via direct balance update
        // Full SL-031 integration will use proper journal entries
        
        // Update payout status
        $wpdb->update(
            $table_payouts,
            [
                'status' => 'settled',
                'bank_transaction_id' => $bank_transaction_id,
                'settled_at' => current_time('mysql')
            ],
            ['id' => $payout_id],
            ['%s', '%d', '%s'],
            ['%d']
        );
        
        // Update linked earned commissions
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_earned} 
             SET status = 'paid' 
             WHERE payout_id = %d AND status = 'earned'",
            $payout_id
        ));        orabooks_log_event('payout_settled', 
                "Payout #{$payout_id} settled: gross={$payout->gross_amount}, fee={$payout->fee_amount}, net=" . ($payout->gross_amount - $payout->fee_amount), 
                'info', [
                    'payout_id' => $payout_id,
                    'bank_transaction_id' => $bank_transaction_id,
                    'gross_amount' => $payout->gross_amount,
                    'fee_amount' => $payout->fee_amount,
                    'net_amount' => $payout->gross_amount - $payout->fee_amount
                ], $payout->partner_user_id, $payout->org_id);
        
        // Publish via EventBus (SL-302)
        $event_payload = [
            'payout_id' => $payout_id,
            'partner_user_id' => $payout->partner_user_id,
            'gross_amount' => $payout->gross_amount,
            'fee_amount' => $payout->fee_amount,
            'net_amount' => $payout->gross_amount - $payout->fee_amount,
            'org_id' => $payout->org_id,
            'priority' => 'high',
        ];

        if (function_exists('orabooks_publish_event')) {
            orabooks_publish_event('payout_settled', $payout_id, $event_payload);
        }

        // Fire notification event for SL-250 (direct, for immediate delivery)
        do_action('orabooks_payout_settled', $payout_id, $event_payload);
        
        return true;
    }

    /**
     * ============================================================
     * EXPIRY JOB (Daily)
     * ============================================================
     */
    public static function process_expiry() {
        global $wpdb;
        
        $table_earned = OraBooks_Database::table('commissions_earned');
        $table_escrow = OraBooks_Database::table('commission_escrow_schedule');
        $table_release = OraBooks_Database::table('commission_release_schedule');
        $table_balances = OraBooks_Database::table('account_balances');
        $table_accounts = OraBooks_Database::table('accounts');
        
        $config = self::get_config();
        
        // PART A: Expire earned commissions where expires_at has passed
        $expired_earned = $wpdb->get_results(
            "SELECT ce.* FROM {$table_earned} ce
             WHERE ce.status = 'earned'
               AND ce.expires_at < NOW()
             LIMIT 500"
        );
        
        $expired_count = 0;
        foreach ($expired_earned as $earned) {
            // Reverse the liability
            if ($config && $config->expiry_accounting_action === 'reverse_expense') {
                // Dr Commission Payable, Cr Commission Expense (reverse)
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$table_balances} (org_id, account_id, balance) 
                     VALUES (%d, (SELECT id FROM {$table_accounts} WHERE org_id = %d AND code = '2000'), %f)
                     ON DUPLICATE KEY UPDATE balance = balance + %f",
                    $earned->org_id, $earned->org_id, $earned->amount, $earned->amount
                ));
                
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$table_balances} (org_id, account_id, balance) 
                     VALUES (%d, (SELECT id FROM {$table_accounts} WHERE org_id = %d AND code = '5000'), %f)
                     ON DUPLICATE KEY UPDATE balance = balance - %f",
                    $earned->org_id, $earned->org_id, $earned->amount, $earned->amount
                ));
            } else {
                // Dr Commission Payable, Cr Expired Commission Income
                // Use account 3000 (Owner's Equity) as income proxy for MVP
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$table_balances} (org_id, account_id, balance) 
                     VALUES (%d, (SELECT id FROM {$table_accounts} WHERE org_id = %d AND code = '2000'), %f)
                     ON DUPLICATE KEY UPDATE balance = balance + %f",
                    $earned->org_id, $earned->org_id, $earned->amount, $earned->amount
                ));
                
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$table_balances} (org_id, account_id, balance) 
                     VALUES (%d, (SELECT id FROM {$table_accounts} WHERE org_id = %d AND code = '3000'), %f)
                     ON DUPLICATE KEY UPDATE balance = balance - %f",
                    $earned->org_id, $earned->org_id, $earned->amount, $earned->amount
                ));
            }
            
            $wpdb->update(
                $table_earned,
                ['status' => 'expired'],
                ['id' => $earned->id],
                ['%s'],
                ['%d']
            );
            
            $expired_count++;
        }
        
        // PART B: Expire escrow remaining amounts
        if ($config) {
            $max_years = $config->max_years;
            $expired_escrow = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table_escrow}
                 WHERE remaining_amount > 0
                   AND remaining_amount_status = 'pending'
                   AND created_at < DATE_SUB(NOW(), INTERVAL %d YEAR)",
                $max_years
            ));
            
            foreach ($expired_escrow as $escrow) {
                $wpdb->update(
                    $table_escrow,
                    [
                        'remaining_amount' => 0,
                        'remaining_amount_status' => 'expired'
                    ],
                    ['id' => $escrow->id],
                    ['%f', '%s'],
                    ['%d']
                );
                
                // Also expire pending release schedules
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_release}
                     SET status = 'expired'
                     WHERE escrow_id = %d AND status = 'pending'",
                    $escrow->id
                ));
                
                orabooks_log_event('escrow_remaining_expired', 
                    "Escrow #{$escrow->id} remaining {$escrow->remaining_amount} expired", 
                    'info', [
                        'escrow_id' => $escrow->id,
                        'remaining_amount' => $escrow->remaining_amount,
                        'partner_user_id' => $escrow->partner_user_id
                    ], $escrow->partner_user_id, null);
            }
        }
        
        if ($expired_count > 0) {
            orabooks_log_event('commission_expired_batch', 
                "Commission expiry batch: {$expired_count} earned commissions expired", 
                'info', ['count' => $expired_count], null, null);
        }
        
        return ['expired_earned' => $expired_count, 'expired_escrow' => count($expired_escrow ?? [])];
    }

    /**
     * ============================================================
     * COMMISSION STATS
     * ============================================================
     */
    public static function get_commission_stats($partner_user_id) {
        global $wpdb;
        
        $table_earned = OraBooks_Database::table('commissions_earned');
        $table_escrow = OraBooks_Database::table('commission_escrow_schedule');
        $table_payouts = OraBooks_Database::table('commission_payouts');
        $table_attributions = OraBooks_Database::table('partner_attributions');
        
        $stats = [];
        
        // Total earned (accrued)
        $stats['total_earned'] = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$table_earned} WHERE partner_user_id = %d AND status = 'earned'",
            $partner_user_id
        ));
        
        // Total paid
        $stats['total_paid'] = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$table_earned} WHERE partner_user_id = %d AND status = 'paid'",
            $partner_user_id
        ));
        
        // Total expired
        $stats['total_expired'] = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$table_earned} WHERE partner_user_id = %d AND status = 'expired'",
            $partner_user_id
        ));
        
        // Pending payout (earned but not in a settled payout)
        $stats['pending_payout'] = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(ce.amount), 0) 
             FROM {$table_earned} ce
             LEFT JOIN {$table_payouts} cp ON ce.payout_id = cp.id
             WHERE ce.partner_user_id = %d 
               AND ce.status = 'earned'
               AND (cp.status IS NULL OR cp.status != 'settled')",
            $partner_user_id
        ));
        
        // Escrow remaining
        $stats['escrow_remaining'] = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(remaining_amount), 0) 
             FROM {$table_escrow} 
             WHERE partner_user_id = %d AND remaining_amount_status = 'pending'",
            $partner_user_id
        ));
        
        // Escrow total
        $stats['escrow_total'] = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total_amount), 0) 
             FROM {$table_escrow} 
             WHERE partner_user_id = %d",
            $partner_user_id
        ));
        
        // Verified attributions count
        $stats['verified_attributions'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_attributions} WHERE partner_user_id = %d AND status = 'verified'",
            $partner_user_id
        ));
        
        // Active customers count
        $stats['active_customers'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT customer_id) 
             FROM {$table_escrow} 
             WHERE partner_user_id = %d",
            $partner_user_id
        ));
        
        return $stats;
    }

    /**
     * Get earned commissions list
     */
    public static function get_earned_commissions($partner_user_id, $args = []) {
        global $wpdb;
        
        $table_earned = OraBooks_Database::table('commissions_earned');
        $table_release = OraBooks_Database::table('commission_release_schedule');
        $table_escrow = OraBooks_Database::table('commission_escrow_schedule');
        $table_users = OraBooks_Database::table('users');
        
        $where = 'ce.partner_user_id = %d';
        $params = [$partner_user_id];
        
        if (!empty($args['status'])) {
            $where .= ' AND ce.status = %s';
            $params[] = $args['status'];
        }
        
        if (!empty($args['from_date'])) {
            $where .= ' AND ce.earned_at >= %s';
            $params[] = $args['from_date'];
        }
        
        if (!empty($args['to_date'])) {
            $where .= ' AND ce.earned_at <= %s';
            $params[] = $args['to_date'];
        }
        
        $limit = $args['limit'] ?? 50;
        $offset = $args['offset'] ?? 0;
        
        $sql = "SELECT ce.*, rs.release_month, u.email as customer_email
                FROM {$table_earned} ce
                JOIN {$table_release} rs ON ce.release_schedule_id = rs.id
                JOIN {$table_users} u ON ce.customer_id = u.id
                WHERE {$where}
                ORDER BY ce.earned_at DESC
                LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        // Mask customer emails
        foreach ($results as &$row) {
            $row->customer_email_masked = orabooks_mask_email($row->customer_email);
        }
        
        return $results;
    }

    /**
     * Get payout history
     */
    public static function get_payouts($partner_user_id, $args = []) {
        global $wpdb;
        
        $table = OraBooks_Database::table('commission_payouts');
        
        $where = 'partner_user_id = %d';
        $params = [$partner_user_id];
        
        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $params[] = $args['status'];
        }
        
        $limit = $args['limit'] ?? 20;
        $offset = $args['offset'] ?? 0;
        
        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Get commission payable aging
     */
    public static function get_aging_report($partner_user_id) {
        global $wpdb;
        
        $table = OraBooks_Database::table('commissions_earned');
        $table_payouts = OraBooks_Database::table('commission_payouts');
        
        $now = current_time('mysql');
        
        $aging = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(CASE WHEN ce.earned_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND ce.status = 'earned' THEN ce.amount ELSE 0 END) as bucket_0_30,
                SUM(CASE WHEN ce.earned_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND ce.earned_at < DATE_SUB(NOW(), INTERVAL 30 DAY) AND ce.status = 'earned' THEN ce.amount ELSE 0 END) as bucket_31_60,
                SUM(CASE WHEN ce.earned_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) AND ce.earned_at < DATE_SUB(NOW(), INTERVAL 60 DAY) AND ce.status = 'earned' THEN ce.amount ELSE 0 END) as bucket_61_90,
                SUM(CASE WHEN ce.earned_at < DATE_SUB(NOW(), INTERVAL 90 DAY) AND ce.status = 'earned' THEN ce.amount ELSE 0 END) as bucket_90_plus,
                SUM(CASE WHEN ce.status = 'expired' THEN ce.amount ELSE 0 END) as expired_total
             FROM {$table} ce
             WHERE ce.partner_user_id = %d",
            $partner_user_id
        ));
        
        return $aging;
    }

    /**
     * Get escrow schedule for a partner
     */
    public static function get_escrow_schedule($partner_user_id) {
        global $wpdb;
        
        $table_escrow = OraBooks_Database::table('commission_escrow_schedule');
        $table_release = OraBooks_Database::table('commission_release_schedule');
        $table_users = OraBooks_Database::table('users');
        
        $escrows = $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, u.email as customer_email,
                    (SELECT COUNT(*) FROM {$table_release} WHERE escrow_id = e.id AND status = 'released') as releases_completed,
                    (SELECT COUNT(*) FROM {$table_release} WHERE escrow_id = e.id) as total_releases
             FROM {$table_escrow} e
             JOIN {$table_users} u ON e.customer_id = u.id
             WHERE e.partner_user_id = %d
             ORDER BY e.created_at DESC",
            $partner_user_id
        ));
        
        foreach ($escrows as &$e) {
            $e->customer_email_masked = orabooks_mask_email($e->customer_email);
            $e->progress_pct = $e->total_releases > 0 ? round(($e->releases_completed / $e->total_releases) * 100, 1) : 0;
        }
        
        return $escrows;
    }

    /**
     * ============================================================
     * UPDATE CONFIG (Super Admin only)
     * ============================================================
     */
    public static function update_config($data) {
        global $wpdb;
        
        $table = OraBooks_Database::table('partner_commission_config');
        $allowed_fields = [
            'base_monthly_amount', 'max_years', 'yearly_percentages',
            'min_payout_threshold', 'customer_active_window_days',
            'expiry_accounting_action', 'payout_fee_type', 'payout_fee_rate'
        ];
        
        $update = [];
        $formats = [];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update[$field] = $data[$field];
                if (in_array($field, ['base_monthly_amount', 'min_payout_threshold', 'payout_fee_rate'])) {
                    $formats[] = '%f';
                } elseif (in_array($field, ['max_years', 'customer_active_window_days'])) {
                    $formats[] = '%d';
                } else {
                    $formats[] = '%s';
                }
            }
        }
        
        if (empty($update)) {
            return new WP_Error('no_data', 'No valid fields to update');
        }
        
        // Validate yearly_percentages if provided
        if (isset($update['yearly_percentages']) && is_array($update['yearly_percentages'])) {
            $update['yearly_percentages'] = json_encode($update['yearly_percentages']);
        }
        
        $wpdb->update($table, $update, ['id' => 1], $formats, ['%d']);
        
        // Clear cache
        wp_cache_delete('orabooks_commission_config', 'orabooks');
        
        orabooks_log_event('commission_config_updated', 
            'Commission platform configuration updated', 
            'info', ['fields' => array_keys($update)], get_current_user_id(), null);
        
        return true;
    }

    // ============================================================
    // AJAX HANDLERS
    // ============================================================

    public function ajax_commission_stats() {
        $user_id = get_current_user_id();
        $org_id = intval($_GET['org_id'] ?? 0);
        
        // Check permission: partner commission access
        if (!empty($org_id) && !OraBooks_RBAC::require_permission($user_id, $org_id, 'partner_commission_access')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        // Determine which partner to show stats for
        $partner_user_id = intval($_GET['partner_user_id'] ?? $user_id);
        
        $stats = self::get_commission_stats($partner_user_id);
        $config = self::get_config();
        
        if ($config) {
            $stats['min_payout_threshold'] = $config->min_payout_threshold;
            $stats['yearly_percentages'] = $config->yearly_percentages;
            $stats['max_years'] = $config->max_years;
            $stats['currency'] = $config->currency;
        }
        
        orabooks_json_success($stats);
    }

    public function ajax_commission_earned() {
        $user_id = get_current_user_id();
        $org_id = intval($_GET['org_id'] ?? 0);
        
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'partner_commission_access')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $partner_user_id = intval($_GET['partner_user_id'] ?? $user_id);
        
        $args = [
            'status' => sanitize_text_field($_GET['status'] ?? ''),
            'from_date' => sanitize_text_field($_GET['from_date'] ?? ''),
            'to_date' => sanitize_text_field($_GET['to_date'] ?? ''),
            'limit' => intval($_GET['limit'] ?? 50),
            'offset' => intval($_GET['offset'] ?? 0)
        ];
        
        $earned = self::get_earned_commissions($partner_user_id, $args);
        orabooks_json_success($earned);
    }

    public function ajax_commission_payouts() {
        $user_id = get_current_user_id();
        $org_id = intval($_GET['org_id'] ?? 0);
        
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'partner_commission_access')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $partner_user_id = intval($_GET['partner_user_id'] ?? $user_id);
        
        $args = [
            'status' => sanitize_text_field($_GET['status'] ?? ''),
            'limit' => intval($_GET['limit'] ?? 20),
            'offset' => intval($_GET['offset'] ?? 0)
        ];
        
        $payouts = self::get_payouts($partner_user_id, $args);
        orabooks_json_success($payouts);
    }

    public function ajax_commission_aging() {
        $user_id = get_current_user_id();
        $org_id = intval($_GET['org_id'] ?? 0);
        
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'partner_commission_access')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $partner_user_id = intval($_GET['partner_user_id'] ?? $user_id);
        
        $aging = self::get_aging_report($partner_user_id);
        orabooks_json_success($aging);
    }

    public function ajax_commission_config() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $config = self::get_config();
        orabooks_json_success($config);
    }

    public function ajax_update_config() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $data = [];
        $fields = [
            'base_monthly_amount' => FILTER_VALIDATE_FLOAT,
            'max_years' => FILTER_VALIDATE_INT,
            'yearly_percentages' => FILTER_DEFAULT,
            'min_payout_threshold' => FILTER_VALIDATE_FLOAT,
            'customer_active_window_days' => FILTER_VALIDATE_INT,
            'expiry_accounting_action' => FILTER_DEFAULT,
            'payout_fee_type' => FILTER_DEFAULT,
            'payout_fee_rate' => FILTER_VALIDATE_FLOAT,
        ];
        
        foreach ($fields as $field => $filter) {
            if (isset($_POST[$field])) {
                $data[$field] = $_POST[$field];
            }
        }
        
        // Parse yearly_percentages from JSON string if provided
        if (isset($data['yearly_percentages']) && is_string($data['yearly_percentages'])) {
            $data['yearly_percentages'] = json_decode(stripslashes($data['yearly_percentages']), true);
        }
        
        $result = self::update_config($data);
        
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        
        orabooks_json_success([], 'Configuration updated');
    }

    public function ajax_escrow_schedule() {
        $user_id = get_current_user_id();
        $org_id = intval($_GET['org_id'] ?? 0);
        
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'partner_commission_access')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $partner_user_id = intval($_GET['partner_user_id'] ?? $user_id);
        $escrows = self::get_escrow_schedule($partner_user_id);
        
        orabooks_json_success($escrows);
    }
}
