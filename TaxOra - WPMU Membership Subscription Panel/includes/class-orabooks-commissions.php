<?php
/**
 * SL-068 – Partner Commissions System
 *
 * Manages commission tracking, qualification, and payout lifecycle.
 * Listens to partner_attribution_verified events to create pending commissions.
 * Commissions move through: pending → qualified → paid | cancelled | forfeited.
 *
 * Owns the customer_active_status read model (doctrine: SL-068 is single source of truth
 * for "is this customer currently active?"). SL-013 and SL-139 query this table.
 *
 * Build Order: SL-004 → SL-013 → SL-003 → SL-017 → SL-139 → SL-068 (this)
 *
 * Dependencies:
 *   - SL-013 (partner_attributions, partner_codes tables)
 *   - SL-139 (partner dashboard)
 *   - SL-021 / subscription system (customer activation for qualification)
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Commissions {

    /**
     * Singleton instance
     */
    private static $instance = null;

    // ── Commission statuses ──────────────────────────────────────────────────
    const STATUS_PENDING   = 'pending';
    const STATUS_QUALIFIED = 'qualified';
    const STATUS_PAID      = 'paid';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_FORFEITED = 'forfeited';

    // ── Escrow schedule statuses ────────────────────────────────────────────
    const ESCROW_PENDING  = 'pending';
    const ESCROW_RELEASED = 'released';
    const ESCROW_EXPIRED  = 'expired';

    /**
     * Valid status transitions
     */
    const VALID_TRANSITIONS = array(
        self::STATUS_PENDING   => array(self::STATUS_QUALIFIED, self::STATUS_CANCELLED, self::STATUS_FORFEITED),
        self::STATUS_QUALIFIED => array(self::STATUS_PAID, self::STATUS_CANCELLED, self::STATUS_FORFEITED),
        self::STATUS_PAID      => array(),  // Terminal
        self::STATUS_CANCELLED => array(),  // Terminal
        self::STATUS_FORFEITED => array(),  // Terminal
    );

    /**
     * Default commission rate (percentage of customer payment)
     */
    const DEFAULT_COMMISSION_RATE = 10.0; // 10%

    /**
     * Default platform config values
     */
    const DEFAULT_BASE_MONTHLY_AMOUNT      = 10;
    const DEFAULT_MAX_YEARS                = 6;
    const DEFAULT_MIN_PAYOUT_THRESHOLD      = 25.00;
    const DEFAULT_CUSTOMER_ACTIVE_WINDOW    = 30; // days
    const DEFAULT_PAYOUT_FEE_RATE           = 2.5; // percentage

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * CoA account codes for commission accounting
     */
    const COA_COMMISSION_EXPENSE     = 5600;
    const COA_COMMISSION_PAYABLE     = 2400;
    const COA_COMMISSION_FEE_PAYABLE = 2410;

    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init_table_names'));

        // ── SL-068: Event listeners ─────────────────────────────────────────
        add_action('partner_attribution_verified', array($this, 'on_attribution_verified'), 10, 1);
        add_action('orabooks_partner_suspended', array($this, 'on_partner_suspended'), 10, 1);
        add_action('orabooks_partner_fraud_freeze', array($this, 'on_partner_fraud_freeze'), 10, 1);

        // ── SL-068: Daily cron jobs (expiry, cleanup) ───────────────────────
        add_action('init', array($this, 'schedule_daily_jobs'));
        add_action('orabooks_commissions_daily', array($this, 'process_daily_jobs'));

        // ── SL-068 §5.22: Monthly release cron (last day of month) ──────────
        add_action('init', array($this, 'schedule_monthly_release'));
        add_action('orabooks_commission_monthly_release', array($this, 'process_monthly_release'));

        // ── SL-068: AJAX endpoints for partner dashboard ────────────────────
        add_action('wp_ajax_orabooks_commission_summary', array($this, 'ajax_commission_summary'));
        add_action('wp_ajax_orabooks_commission_history', array($this, 'ajax_commission_history'));
        add_action('wp_ajax_orabooks_payout_summary', array($this, 'ajax_payout_summary'));
    }

    /**
     * SL-068: Initialize table names for multisite.
     * Registers: partner_commissions, partner_commission_config,
     *            customer_active_status, commission_escrow_schedule,
     *            commissions_released
     */
    public function init_table_names() {
        global $wpdb;

        $prefix = is_multisite() ? $wpdb->base_prefix : $wpdb->prefix;
        $wpdb->orabooks_partner_commissions       = $prefix . 'orabooks_partner_commissions';
        $wpdb->orabooks_partner_commission_config   = $prefix . 'orabooks_partner_commission_config';
        $wpdb->orabooks_customer_active_status      = $prefix . 'orabooks_customer_active_status';
        $wpdb->orabooks_commission_escrow_schedule   = $prefix . 'orabooks_commission_escrow_schedule';
        $wpdb->orabooks_commissions_released         = $prefix . 'orabooks_commissions_released';
    }

    /**
     * SL-068: Create all commission-related tables.
     * Run during plugin activation.
     *
     * Creates:
     * - orabooks_partner_commissions (existing)
     * - orabooks_partner_commission_config (platform-wide settings)
     * - orabooks_customer_active_status (read model for customer activity)
     * - orabooks_commission_escrow_schedule (future monthly release projections)
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // ================================================================
        // PARTNER COMMISSIONS (existing)
        // ================================================================
        $table = $wpdb->base_prefix . 'orabooks_partner_commissions';
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            attribution_id BIGINT NOT NULL,
            partner_user_id INT NOT NULL,
            partner_org_id INT NOT NULL,
            customer_user_id INT NOT NULL,
            partner_code_used VARCHAR(32) NOT NULL,
            attribution_date TIMESTAMP NULL,
            verified_at TIMESTAMP NULL,
            commission_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,
            commission_amount DECIMAL(12,2) NULL DEFAULT NULL,
            qualified_amount DECIMAL(12,2) NULL DEFAULT NULL,
            fee_amount DECIMAL(12,2) NULL DEFAULT NULL,
            net_amount DECIMAL(12,2) NULL DEFAULT NULL,
            qualified_at TIMESTAMP NULL,
            paid_amount DECIMAL(12,2) NULL DEFAULT NULL,
            paid_at TIMESTAMP NULL,
            payout_batch_id BIGINT NULL,
            status ENUM('pending','qualified','paid','cancelled','forfeited') NOT NULL DEFAULT 'pending',
            status_notes TEXT NULL,
            created_at TIMESTAMP DEFAULT (UTC_TIMESTAMP()),
            updated_at TIMESTAMP DEFAULT (UTC_TIMESTAMP()) ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (attribution_id) REFERENCES {$wpdb->base_prefix}orabooks_partner_attributions(id),
            INDEX idx_partner (partner_user_id),
            INDEX idx_customer (customer_user_id),
            INDEX idx_status (status),
            INDEX idx_payout (payout_batch_id)
        ) {$charset_collate};";
        dbDelta($sql);

        // Add new columns if missing (upgrade path)
        $fee_col = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'fee_amount'");
        if (!$fee_col) {
            $wpdb->query("ALTER TABLE {$table}
                ADD COLUMN fee_amount DECIMAL(12,2) NULL DEFAULT NULL AFTER qualified_amount,
                ADD COLUMN net_amount DECIMAL(12,2) NULL DEFAULT NULL AFTER fee_amount");
        }
        $batch_col = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'payout_batch_id'");
        if (!$batch_col) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN payout_batch_id BIGINT NULL AFTER paid_at, ADD INDEX idx_payout (payout_batch_id)");
        }

        // Ensure commission-related CoA accounts exist (idempotent)
        self::ensure_coa_accounts();

        // ================================================================
        // COMMISSIONS RELEASED (monthly accrual tracking)
        // ================================================================
        $released_table = $wpdb->base_prefix . 'orabooks_commissions_released';
        $sql_released = "CREATE TABLE IF NOT EXISTS {$released_table} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            commission_id BIGINT NOT NULL,
            escrow_schedule_id BIGINT NULL,
            release_month VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
            customer_user_id INT NOT NULL,
            partner_user_id INT NOT NULL,
            released_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            je_id BIGINT NULL COMMENT 'Journal Entry ID for release',
            writeback_je_id BIGINT NULL COMMENT 'Journal Entry ID for reversal (writeback)',
            released_at TIMESTAMP DEFAULT (UTC_TIMESTAMP()),
            FOREIGN KEY (commission_id) REFERENCES {$table}(id),
            INDEX idx_commission (commission_id),
            INDEX idx_release_month (release_month),
            INDEX idx_je (je_id),
            INDEX idx_writeback (writeback_je_id)
        ) {$charset_collate};";
        // Add writeback_je_id column if missing (upgrade path)
        $writeback_col = $wpdb->get_var("SHOW COLUMNS FROM {$released_table} LIKE 'writeback_je_id'");
        if (!$writeback_col) {
            $wpdb->query("ALTER TABLE {$released_table} ADD COLUMN writeback_je_id BIGINT NULL AFTER je_id, ADD INDEX idx_writeback (writeback_je_id)");
        }
        dbDelta($sql_released);

        // ================================================================
        // PARTNER COMMISSION CONFIG (Singleton, id=1)
        // ================================================================
        $config_table = $wpdb->base_prefix . 'orabooks_partner_commission_config';
        $sql_config = "CREATE TABLE IF NOT EXISTS {$config_table} (
            id INT PRIMARY KEY DEFAULT 1,
            base_monthly_amount DECIMAL(12,2) NOT NULL DEFAULT 10.00,
            max_years INT NOT NULL DEFAULT 6,
            min_payout_threshold DECIMAL(12,2) NOT NULL DEFAULT 25.00,
            customer_active_window_days INT NOT NULL DEFAULT 30,
            payout_fee_rate DECIMAL(5,2) NOT NULL DEFAULT 2.50,
            payout_fee_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
            created_at TIMESTAMP DEFAULT (UTC_TIMESTAMP()),
            updated_at TIMESTAMP DEFAULT (UTC_TIMESTAMP()) ON UPDATE CURRENT_TIMESTAMP
        ) {$charset_collate};";
        dbDelta($sql_config);

        // Insert default singleton row if missing
        $config_exists = $wpdb->get_var("SELECT id FROM {$config_table} WHERE id = 1");
        if (!$config_exists) {
            $wpdb->insert(
                $config_table,
                array(
                    'id'                       => 1,
                    'base_monthly_amount'      => self::DEFAULT_BASE_MONTHLY_AMOUNT,
                    'max_years'                => self::DEFAULT_MAX_YEARS,
                    'min_payout_threshold'     => self::DEFAULT_MIN_PAYOUT_THRESHOLD,
                    'customer_active_window_days' => self::DEFAULT_CUSTOMER_ACTIVE_WINDOW,
                    'payout_fee_rate'          => self::DEFAULT_PAYOUT_FEE_RATE,
                    'payout_fee_type'          => 'percentage',
                )
            );
        }

        // ================================================================
        // CUSTOMER ACTIVE STATUS (Read model owned by SL-068)
        // ================================================================
        $status_table = $wpdb->base_prefix . 'orabooks_customer_active_status';
        $sql_status = "CREATE TABLE IF NOT EXISTS {$status_table} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            last_paid_invoice_date TIMESTAMP NULL,
            updated_at TIMESTAMP DEFAULT (UTC_TIMESTAMP()) ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_customer (customer_id),
            INDEX idx_active (is_active)
        ) {$charset_collate};";
        dbDelta($sql_status);

        // ================================================================
        // COMMISSION ESCROW SCHEDULE (monthly release projections)
        // ================================================================
        $escrow_table = $wpdb->base_prefix . 'orabooks_commission_escrow_schedule';
        $sql_escrow = "CREATE TABLE IF NOT EXISTS {$escrow_table} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            commission_id BIGINT NOT NULL,
            scheduled_release_date DATE NOT NULL,
            release_month VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
            projected_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            actual_amount DECIMAL(12,2) NULL DEFAULT NULL,
            status ENUM('pending','released','expired') NOT NULL DEFAULT 'pending',
            released_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT (UTC_TIMESTAMP()),
            FOREIGN KEY (commission_id) REFERENCES {$table}(id),
            INDEX idx_commission (commission_id),
            INDEX idx_release_month (release_month),
            INDEX idx_status (status)
        ) {$charset_collate};";
        dbDelta($sql_escrow);

        error_log('[OraBooks SL-068] All commission tables created/verified.');
    }

    /**
     * SL-068 §5.22: Ensure commission-related CoA accounts exist.
     * Creates accounts in orabooks_chart_of_accounts if they don't exist.
     * Runs idempotently during create_tables().
     */
    private static function ensure_coa_accounts() {
        global $wpdb;

        $coa_table = $wpdb->base_prefix . 'orabooks_chart_of_accounts';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$coa_table}'");
        if ($table_exists !== $coa_table) {
            error_log('[OraBooks SL-068] CoA table not found — skipping account creation.');
            return;
        }

        $accounts = array(
            array(
                'code'               => self::COA_COMMISSION_EXPENSE,
                'name'               => 'Commission Expense',
                'account_type'       => OraBooks_Chart_of_Accounts::TYPE_EXPENSE,
                'description'        => 'SL-068: Partner commission expense recognized monthly upon release',
                'mode_compatibility' => 'all',
                'is_system'          => 1,
            ),
            array(
                'code'               => self::COA_COMMISSION_PAYABLE,
                'name'               => 'Commission Payable',
                'account_type'       => OraBooks_Chart_of_Accounts::TYPE_LIABILITY,
                'description'        => 'SL-068: Accrued commission liability to partners',
                'mode_compatibility' => 'all',
                'is_system'          => 1,
            ),
            array(
                'code'               => self::COA_COMMISSION_FEE_PAYABLE,
                'name'               => 'Commission Fee Payable',
                'account_type'       => OraBooks_Chart_of_Accounts::TYPE_LIABILITY,
                'description'        => 'SL-068: Payment gateway fee liability on partner commissions',
                'mode_compatibility' => 'all',
                'is_system'          => 1,
            ),
        );

        foreach ($accounts as $acct) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$coa_table} WHERE code = %s",
                $acct['code']
            ));
            if (!$exists) {
                $wpdb->insert(
                    $coa_table,
                    array(
                        'code'               => $acct['code'],
                        'name'               => $acct['name'],
                        'account_type'       => $acct['account_type'],
                        'mode_compatibility' => $acct['mode_compatibility'],
                        'description'        => $acct['description'],
                        'is_system'          => $acct['is_system'],
                        'is_active'          => 1,
                        'created_by'         => 0,
                    ),
                    array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d')
                );
            }
        }

        error_log('[OraBooks SL-068] Commission CoA accounts verified.');
    }

    // ================================================================
    // COMMISSION LIFECYCLE
    // ================================================================

    /**
     * SL-068: Handle partner_attribution_verified event.
     * Creates a pending commission record linked to the verified attribution.
     *
     * @param array $event_data {
     *     @type int $attribution_id
     *     @type int $partner_user_id
     *     @type int $customer_user_id
     *     @type string $verified_at
     * }
     * @return int|false Commission ID or false on failure
     */
    public function on_attribution_verified($event_data) {
        global $wpdb;

        $attribution_id  = isset($event_data['attribution_id'])  ? (int) $event_data['attribution_id'] : 0;
        $partner_user_id = isset($event_data['partner_user_id']) ? (int) $event_data['partner_user_id'] : 0;
        $customer_user_id = isset($event_data['customer_user_id']) ? (int) $event_data['customer_user_id'] : 0;
        $verified_at     = isset($event_data['verified_at']) ? $event_data['verified_at'] : current_time('mysql');

        if (!$attribution_id || !$partner_user_id || !$customer_user_id) {
            return false;
        }

        // Get the partner code used from the attribution
        $attributions_table = $wpdb->base_prefix . 'orabooks_partner_attributions';
        $attribution = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$attributions_table} WHERE id = %d",
            $attribution_id
        ));

        if (!$attribution) {
            return false;
        }

        // Get partner org_id from partner codes
        $codes_table = $wpdb->base_prefix . 'orabooks_partner_codes';
        $partner_code = $wpdb->get_row($wpdb->prepare(
            "SELECT org_id FROM {$codes_table} WHERE user_id = %d AND partner_code = %s",
            $partner_user_id,
            $attribution->partner_code_used
        ));

        $partner_org_id = $partner_code ? (int) $partner_code->org_id : 0;

        // Check if commission already exists for this attribution (idempotency)
        $table = $wpdb->base_prefix . 'orabooks_partner_commissions';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE attribution_id = %d",
            $attribution_id
        ));

        if ($existing) {
            return (int) $existing;
        }

        // Get commission rate from partner config (or use default)
        $commission_rate = $this->get_partner_commission_rate($partner_user_id, $partner_org_id);

        $wpdb->insert(
            $table,
            array(
                'attribution_id'    => $attribution_id,
                'partner_user_id'   => $partner_user_id,
                'partner_org_id'    => $partner_org_id,
                'customer_user_id'  => $customer_user_id,
                'partner_code_used' => $attribution->partner_code_used,
                'attribution_date'  => $attribution->attribution_date,
                'verified_at'       => $verified_at,
                'commission_rate'   => $commission_rate,
                'status'            => self::STATUS_PENDING,
                'created_at'        => current_time('mysql'),
            ),
            array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s')
        );

        if ($wpdb->last_error) {
            error_log('[OraBooks SL-068] Failed to create commission: ' . $wpdb->last_error);
            return false;
        }

        $commission_id = $wpdb->insert_id;

        // ── Pre-compute escrow schedule for business planning ───────────────
        $this->generate_escrow_schedule($commission_id, $commission_rate);

        // Audit event
        do_action('orabooks_security_event', 'commission_created', array(
            'commission_id'   => $commission_id,
            'attribution_id'  => $attribution_id,
            'partner_user_id' => $partner_user_id,
            'customer_user_id' => $customer_user_id,
            'commission_rate' => $commission_rate,
        ));

        return (int) $commission_id;
    }

    /**
     * SL-068: Qualify a commission — mark it as eligible for payout.
     * Called when a referred customer has their first paid subscription/invoice.
     *
     * @param int    $commission_id   Commission record ID
     * @param float  $qualified_amount The qualified revenue amount (e.g., first payment)
     * @param string $notes           Optional notes
     * @return true|WP_Error
     */
    public function qualify_commission($commission_id, $qualified_amount, $notes = '') {
        global $wpdb;
        $table = $wpdb->base_prefix . 'orabooks_partner_commissions';
        $commission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $commission_id
        ));

        $rate = $commission ? (float) $commission->commission_rate : self::DEFAULT_COMMISSION_RATE;
        $commission_amount = round($qualified_amount * ($rate / 100), 2);
        $fee_rate = $this->get_platform_config('payout_fee_rate');
        $fee_amount = round($commission_amount * ($fee_rate / 100), 2);
        $net_amount = round($commission_amount - $fee_amount, 2);

        // Refresh customer active status
        if ($commission) {
            $this->refresh_customer_active_status($commission->customer_user_id);
        }

        return $this->transition_status(
            $commission_id,
            self::STATUS_QUALIFIED,
            array(
                'commission_amount' => $commission_amount,
                'qualified_amount'  => $qualified_amount,
                'fee_amount'        => $fee_amount,
                'net_amount'        => $net_amount,
                'qualified_at'      => current_time('mysql'),
                'status_notes'      => $notes ?: sprintf(
                    __('Commission qualified: %s gross, %s net (%.2f%% rate, %.2f%% fee)', 'orabooks'),
                    number_format($commission_amount, 2),
                    number_format($net_amount, 2),
                    $rate,
                    $fee_rate
                ),
            )
        );
    }

    /**
     * SL-068: Mark a commission as paid — also refreshes customer active status.
     *
     * @param int   $commission_id   Commission record ID
     * @param float $paid_amount     Amount paid (typically net_amount)
     * @param int   $payout_batch_id Payout batch ID (optional)
     * @return true|WP_Error
     */
    public function mark_paid($commission_id, $paid_amount, $payout_batch_id = null) {
        $data = array(
            'paid_amount' => $paid_amount,
            'paid_at'     => current_time('mysql'),
        );

        if ($payout_batch_id) {
            $data['payout_batch_id'] = (int) $payout_batch_id;
        }

        if (empty($data['status_notes'])) {
            $data['status_notes'] = sprintf(
                __('Commission paid: %s at %s', 'orabooks'),
                number_format($paid_amount, 2),
                current_time('mysql')
            );
        }

        // Audit event
        do_action('orabooks_security_event', 'commission_paid', array(
            'commission_id'   => $commission_id,
            'paid_amount'     => $paid_amount,
            'payout_batch_id' => $payout_batch_id,
        ));

        return $this->transition_status($commission_id, self::STATUS_PAID, $data);
    }

    /**
     * SL-068: Cancel a commission (e.g., attribution blocked/refunded).
     *
     * @param int    $commission_id Commission record ID
     * @param string $reason        Cancellation reason
     * @return true|WP_Error
     */
    public function cancel_commission($commission_id, $reason = '') {
        return $this->transition_status(
            $commission_id,
            self::STATUS_CANCELLED,
            array(
                'status_notes' => $reason ?: __('Commission cancelled', 'orabooks'),
            )
        );
    }

    /**
     * SL-068: Forfeit commissions (e.g., partner fraud-frozen).
     * Forfeits ALL pending and qualified commissions for a partner.
     *
     * @param int    $partner_user_id Partner user ID
     * @param string $reason          Forfeiture reason
     * @return int Count of commissions forfeited
     */
    public function forfeit_partner_commissions($partner_user_id, $reason = '') {
        global $wpdb;

        $table = $wpdb->base_prefix . 'orabooks_partner_commissions';

        $count = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET status = %s,
                 status_notes = CONCAT_WS(' | ', status_notes, %s),
                 updated_at = UTC_TIMESTAMP()
             WHERE partner_user_id = %d
               AND status IN (%s, %s)",
            self::STATUS_FORFEITED,
            $reason ?: __('Partner fraud-frozen', 'orabooks'),
            $partner_user_id,
            self::STATUS_PENDING,
            self::STATUS_QUALIFIED
        ));

        if ($count && $count > 0) {
            do_action('orabooks_security_event', 'commissions_forfeited', array(
                'partner_user_id' => $partner_user_id,
                'count'           => $count,
                'reason'          => $reason,
            ));
        }

        return $count ?: 0;
    }

    /**
     * SL-068: Transition a commission's status with validation.
     *
     * @param int    $commission_id Commission ID
     * @param string $new_status    Target status
     * @param array  $extra_data    Additional column data to set
     * @return true|WP_Error
     */
    private function transition_status($commission_id, $new_status, $extra_data = array()) {
        global $wpdb;

        $table = $wpdb->base_prefix . 'orabooks_partner_commissions';
        $commission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $commission_id
        ));

        if (!$commission) {
            return new WP_Error('not_found', __('Commission not found.', 'orabooks'));
        }

        // Validate transition
        $allowed = self::VALID_TRANSITIONS;
        $current_status = $commission->status;

        if (!isset($allowed[$current_status]) || !in_array($new_status, $allowed[$current_status], true)) {
            return new WP_Error(
                'invalid_transition',
                sprintf(
                    __('Cannot transition commission from %s to %s.', 'orabooks'),
                    $current_status,
                    $new_status
                )
            );
        }

        $data = array_merge($extra_data, array(
            'status'     => $new_status,
            'updated_at' => current_time('mysql'),
        ));

        $wpdb->update(
            $table,
            $data,
            array('id' => $commission_id),
            array_fill(0, count($data), '%s'),
            array('%d')
        );

        if ($wpdb->last_error) {
            return new WP_Error('db_error', $wpdb->last_error);
        }

        // Audit event
        do_action('orabooks_security_event', 'commission_status_changed', array(
            'commission_id'   => $commission_id,
            'from_status'     => $current_status,
            'to_status'       => $new_status,
            'partner_user_id' => $commission->partner_user_id,
        ));

        return true;
    }

    // ================================================================
    // EVENT HANDLERS
    // ================================================================

    /**
     * SL-068: Handle partner suspension — cancel pending commissions.
     *
     * @param int $org_id Partner organization ID
     */
    public function on_partner_suspended($org_id) {
        global $wpdb;

        $table = $wpdb->base_prefix . 'orabooks_partner_commissions';

        // Find partner user IDs for this org
        $codes_table = $wpdb->base_prefix . 'orabooks_partner_codes';
        $partner_users = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$codes_table} WHERE org_id = %d",
            $org_id
        ));

        if (empty($partner_users)) {
            return;
        }

        $user_ids = implode(',', array_map('intval', $partner_users));

        // Cancel all pending and qualified commissions
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET status = %s,
                 status_notes = CONCAT_WS(' | ', status_notes, 'Partner suspended — commission cancelled'),
                 updated_at = UTC_TIMESTAMP()
             WHERE partner_user_id IN ({$user_ids})
               AND status IN (%s, %s)",
            self::STATUS_CANCELLED,
            self::STATUS_PENDING,
            self::STATUS_QUALIFIED
        ));

        error_log(sprintf(
            '[OraBooks SL-068] Commissions cancelled for partner org %d (users: %s)',
            $org_id,
            implode(',', $partner_users)
        ));
    }

    /**
     * SL-068: Handle partner fraud freeze — forfeit all commissions.
     *
     * @param int $org_id Partner organization ID
     */
    public function on_partner_fraud_freeze($org_id) {
        global $wpdb;

        $codes_table = $wpdb->base_prefix . 'orabooks_partner_codes';
        $partner_users = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$codes_table} WHERE org_id = %d",
            $org_id
        ));

        if (empty($partner_users)) {
            return;
        }

        foreach ($partner_users as $user_id) {
            $this->forfeit_partner_commissions(
                (int) $user_id,
                __('Partner organization fraud-frozen', 'orabooks')
            );
        }
    }

    // ================================================================
    // CUSTOMER ACTIVE STATUS (SL-068 Owns This Read Model)
    // ================================================================

    /**
     * SL-068: Check if a customer is currently active.
     * Queries the customer_active_status read model (owned by SL-068).
     * SL-013 and SL-139 should call this method rather than querying directly.
     *
     * @param int $customer_user_id Customer user ID
     * @return bool True if customer is active
     */
    public function is_customer_active($customer_user_id) {
        global $wpdb;

        $status_table = $wpdb->base_prefix . 'orabooks_customer_active_status';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT is_active, last_paid_invoice_date FROM {$status_table} WHERE customer_id = %d",
            $customer_user_id
        ));

        if (!$row) {
            return false;
        }

        return (bool) $row->is_active;
    }

    /**
     * SL-068: Get the last paid invoice date for a customer.
     *
     * @param int $customer_user_id Customer user ID
     * @return string|null MySQL datetime or null if never paid
     */
    public function get_customer_last_paid_date($customer_user_id) {
        global $wpdb;

        $status_table = $wpdb->base_prefix . 'orabooks_customer_active_status';
        $row = $wpdb->get_var($wpdb->prepare(
            "SELECT last_paid_invoice_date FROM {$status_table} WHERE customer_id = %d",
            $customer_user_id
        ));

        return $row;
    }

    /**
     * SL-068: Refresh a customer's active status.
     * Queries SL-021 invoice data (or subscription data) to determine if
     * the customer has a recent paid invoice within the active window.
     *
     * For MVP: checks subscription table for active status.
     * Production: queries SL-021 invoices for paid invoices within window.
     *
     * @param int $customer_user_id Customer user ID
     * @param int $org_id Organization ID (optional, for invoice queries)
     * @return bool New active status
     */
    public function refresh_customer_active_status($customer_user_id, $org_id = 0) {
        global $wpdb;

        $window_days = (int) $this->get_platform_config('customer_active_window_days');
        $status_table = $wpdb->base_prefix . 'orabooks_customer_active_status';

        // MVP: Check subscription table for active status
        // (Production: JOIN with SL-021 invoices and check paid invoices within window)
        $is_active = false;
        $last_paid_date = null;

        // Try subscription table first (SL-021 invoices in production)
        $subscription_table = $wpdb->base_prefix . 'orabooks_subscriptions';
        $has_sub_table = $wpdb->get_var("SHOW TABLES LIKE '{$subscription_table}'");
        if ($has_sub_table === $subscription_table) {
            $subscription = $wpdb->get_row($wpdb->prepare(
                "SELECT status, ends_at FROM {$subscription_table} WHERE user_id = %d AND status = 'active' LIMIT 1",
                $customer_user_id
            ));
            if ($subscription) {
                $is_active = true;
                $last_paid_date = current_time('mysql');
            }
        }

        // Fallback: check if user has any verified attribution (at minimum)
        if (!$is_active) {
            $attributions_table = $wpdb->base_prefix . 'orabooks_partner_attributions';
            $attribution = $wpdb->get_row($wpdb->prepare(
                "SELECT verified_at FROM {$attributions_table}
                 WHERE customer_user_id = %d AND status = 'verified'
                 ORDER BY verified_at DESC LIMIT 1",
                $customer_user_id
            ));
            if ($attribution && !empty($attribution->verified_at)) {
                $days_since = floor((time() - strtotime($attribution->verified_at)) / DAY_IN_SECONDS);
                if ($days_since <= $window_days) {
                    $is_active = true;
                    $last_paid_date = $attribution->verified_at;
                }
            }
        }

        // Upsert into customer_active_status
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$status_table} WHERE customer_id = %d",
            $customer_user_id
        ));

        if ($existing) {
            $wpdb->update(
                $status_table,
                array(
                    'is_active'              => $is_active ? 1 : 0,
                    'last_paid_invoice_date' => $last_paid_date,
                ),
                array('customer_id' => $customer_user_id),
                array('%d', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $status_table,
                array(
                    'customer_id'            => $customer_user_id,
                    'is_active'              => $is_active ? 1 : 0,
                    'last_paid_invoice_date' => $last_paid_date,
                ),
                array('%d', '%d', '%s')
            );
        }

        return $is_active;
    }

    /**
     * SL-068: Batch refresh customer active statuses for a partner.
     * Runs during daily cron to keep the read model up to date.
     *
     * @param int $partner_user_id Partner user ID
     */
    public function refresh_partner_customers($partner_user_id) {
        global $wpdb;

        $attributions_table = $wpdb->base_prefix . 'orabooks_partner_attributions';
        $customers = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT customer_user_id FROM {$attributions_table}
             WHERE partner_user_id = %d AND status = 'verified'",
            $partner_user_id
        ));

        foreach ($customers as $customer_id) {
            $this->refresh_customer_active_status((int) $customer_id);
        }
    }

    // ================================================================
    // COMMISSION ESCROW SCHEDULE
    // ================================================================

    /**
     * SL-068: Generate escrow schedule for a newly created commission.
     * Pre-computes monthly release projections for business planning.
     *
     * @param int   $commission_id  Commission ID
     * @param float $commission_rate Commission rate percentage
     */
    public function generate_escrow_schedule($commission_id, $commission_rate) {
        global $wpdb;

        $escrow_table = $wpdb->base_prefix . 'orabooks_commission_escrow_schedule';
        $config_table = $wpdb->base_prefix . 'orabooks_partner_commission_config';

        $base_monthly = (float) $wpdb->get_var("SELECT base_monthly_amount FROM {$config_table} WHERE id = 1");
        if (!$base_monthly) {
            $base_monthly = self::DEFAULT_BASE_MONTHLY_AMOUNT;
        }

        $max_years = (int) $wpdb->get_var("SELECT max_years FROM {$config_table} WHERE id = 1");
        if (!$max_years) {
            $max_years = self::DEFAULT_MAX_YEARS;
        }

        $projected_monthly = round($base_monthly * ($commission_rate / 100), 2);
        if ($projected_monthly <= 0) {
            return;
        }

        $start = new DateTime('first day of next month');
        $end = new DateTime("+{$max_years} years");
        $interval = new DateInterval('P1M');

        for ($date = clone $start; $date <= $end; $date->add($interval)) {
            $wpdb->insert(
                $escrow_table,
                array(
                    'commission_id'          => $commission_id,
                    'scheduled_release_date' => $date->format('Y-m-d'),
                    'release_month'          => $date->format('Y-m'),
                    'projected_amount'       => $projected_monthly,
                    'status'                 => self::ESCROW_PENDING,
                ),
                array('%d', '%s', '%s', '%f', '%s')
            );
        }
    }

    // ================================================================
    // PLATFORM CONFIG (Singleton Table)
    // ================================================================

    /**
     * SL-068: Get a platform config value from the singleton table.
     *
     * @param string $key Config key (column name)
     * @return mixed Config value
     */
    public function get_platform_config($key) {
        global $wpdb;

        $config_table = $wpdb->base_prefix . 'orabooks_partner_commission_config';

        $cache_key = 'orabooks_commission_config_' . $key;
        $cached = wp_cache_get($cache_key, 'orabooks');
        if ($cached !== false) {
            return $cached;
        }

        $row = $wpdb->get_row("SELECT * FROM {$config_table} WHERE id = 1");
        if (!$row || !isset($row->$key)) {
            // Fallback to defaults
            $defaults = array(
                'base_monthly_amount'       => self::DEFAULT_BASE_MONTHLY_AMOUNT,
                'max_years'                 => self::DEFAULT_MAX_YEARS,
                'min_payout_threshold'       => self::DEFAULT_MIN_PAYOUT_THRESHOLD,
                'customer_active_window_days' => self::DEFAULT_CUSTOMER_ACTIVE_WINDOW,
                'payout_fee_rate'           => self::DEFAULT_PAYOUT_FEE_RATE,
                'payout_fee_type'           => 'percentage',
            );
            $value = isset($defaults[$key]) ? $defaults[$key] : null;
            wp_cache_set($cache_key, $value, 'orabooks', 3600);
            return $value;
        }

        $value = $row->$key;
        wp_cache_set($cache_key, $value, 'orabooks', 3600);
        return $value;
    }

    /**
     * SL-068: Update a platform config value.
     *
     * @param string $key   Config key (column name)
     * @param mixed  $value New value
     */
    public function update_platform_config($key, $value) {
        global $wpdb;

        $config_table = $wpdb->base_prefix . 'orabooks_partner_commission_config';
        $allowed_keys = array(
            'base_monthly_amount', 'max_years', 'min_payout_threshold',
            'customer_active_window_days', 'payout_fee_rate', 'payout_fee_type',
        );

        if (!in_array($key, $allowed_keys, true)) {
            return;
        }

        $wpdb->update(
            $config_table,
            array($key => $value),
            array('id' => 1),
            array('%s'),
            array('%d')
        );

        wp_cache_delete('orabooks_commission_config_' . $key, 'orabooks');
    }

    // ================================================================
    // DAILY CRON JOBS (expiry, cleanup)
    // ================================================================

    /**
     * SL-068: Schedule daily cron job.
     * Runs at 00:30 daily for:
     * - Expire commissions older than max_years (6 years)
     * - Refresh customer active statuses
     * - Escrow schedule maintenance
     */
    public function schedule_daily_jobs() {
        if (!wp_next_scheduled('orabooks_commissions_daily')) {
            wp_schedule_event(
                strtotime('tomorrow 00:30:00'),
                'daily',
                'orabooks_commissions_daily'
            );
        }
    }

    /**
     * SL-068: Process daily jobs.
     * 1. Expire commissions older than max_years from last activity
     * 2. Process expiry writeback (reverse liability for expired commissions)
     * 3. Expire commission_escrow_schedule entries past max_years
     * 4. Log summary
     */
    public function process_daily_jobs() {
        $max_years = (int) $this->get_platform_config('max_years');

        // ── Expire old pending commissions ──────────────────────────
        $expired_count = $this->expire_old_commissions($max_years);

        // ── Write back liability for newly forfeited/expired commissions ──
        $writeback_count = $this->process_expiry_writeback();

        // ── Expire old escrow schedules ─────────────────────────────
        $expired_escrow = $this->expire_old_escrow_schedules($max_years);

        error_log(sprintf(
            '[OraBooks SL-068] Daily jobs: %d commissions expired, %d liability writebacks, %d escrow schedules expired (max_years=%d)',
            $expired_count,
            $writeback_count,
            $expired_escrow,
            $max_years
        ));
    }

    // ================================================================
    // SL-068 §5.22: MONTHLY COMMISSION RELEASE (Accrual Accounting)
    // ================================================================

    /**
     * SL-068 §5.22: Schedule the monthly commission release cron.
     * Runs on the last day of every month at 23:59.
     */
    public function schedule_monthly_release() {
        if (!wp_next_scheduled('orabooks_commission_monthly_release')) {
            // Schedule for the last day of this month
            $last_day = strtotime('last day of this month 23:59:59');
            wp_schedule_event(
                $last_day,
                'daily',
                'orabooks_commission_monthly_release'
            );
        }
    }

    /**
     * SL-068 §5.22: Process monthly commission release.
     *
     * For each escrow schedule entry with status='pending' where
     * scheduled_release_date <= CURRENT_DATE:
     *   - Refresh customer active status
     *   - If active: record commission, book JE, update escrow to released
     *   - If inactive: skip (mark as skipped/no liability)
     *
     * Journal Entry per commission released:
     *   Dr Commission Expense   (liability account 2400 -> debit = expense increase)
     *   Cr Commission Payable   (liability account 2400 -> credit = liability increase)
     */
    public function process_monthly_release() {
        global $wpdb;

        // Early exit: only process on the last 3 days of the month (to avoid
        // daily processing waste, while being safe about timezone offsets)
        $day     = (int) date('j');
        $last_day = (int) date('t');
        if ($day < $last_day - 2) {
            return;
        }

        $escrow_table = $wpdb->base_prefix . 'orabooks_commission_escrow_schedule';
        $comm_table   = $wpdb->base_prefix . 'orabooks_partner_commissions';
        $released_table = $wpdb->base_prefix . 'orabooks_commissions_released';
        $current_month = date('Y-m');

        error_log('[OraBooks SL-068] Starting monthly commission release for ' . $current_month);

        // Find pending escrow schedules for the current month (or earlier that weren't released)
        $pending_escrows = $wpdb->get_results($wpdb->prepare(
            "SELECT es.*, c.partner_user_id, c.customer_user_id, c.commission_rate,
                    c.commission_amount, c.net_amount, c.fee_amount, c.status as commission_status
             FROM {$escrow_table} es
             INNER JOIN {$comm_table} c ON es.commission_id = c.id
             WHERE es.status = %s
               AND es.scheduled_release_date <= CURDATE()
               AND c.status IN (%s, %s)
             ORDER BY es.scheduled_release_date ASC",
            self::ESCROW_PENDING,
            self::STATUS_QUALIFIED,
            self::STATUS_PAID
        ));

        if (empty($pending_escrows)) {
            error_log('[OraBooks SL-068] No pending escrow schedules found to release.');
            return;
        }

        $released_count = 0;
        $skipped_count  = 0;
        $errors         = 0;

        foreach ($pending_escrows as $escrow) {
            // Refresh customer active status
            $this->refresh_customer_active_status($escrow->customer_user_id);
            $is_active = $this->is_customer_active($escrow->customer_user_id);

            if ($is_active && $escrow->commission_status === self::STATUS_QUALIFIED) {
                // ── Customer active: Book the release ─────────────────
                $released_amount = (float) $escrow->projected_amount;
                if ($released_amount <= 0) {
                    $released_amount = (float) $escrow->net_amount ?: 10.00;
                }

                // Book journal entry using canonical JE engine
                $je_id = $this->book_commission_release_je(
                    $escrow->commission_id,
                    $escrow->partner_user_id,
                    $released_amount
                );

                if (is_wp_error($je_id)) {
                    error_log(sprintf(
                        '[OraBooks SL-068] Failed to book JE for commission %d: %s',
                        $escrow->commission_id,
                        $je_id->get_error_message()
                    ));
                    $errors++;
                    continue;
                }

                // Insert released record
                $wpdb->insert(
                    $released_table,
                    array(
                        'commission_id'       => $escrow->commission_id,
                        'escrow_schedule_id'  => $escrow->id,
                        'release_month'       => $current_month,
                        'customer_user_id'    => $escrow->customer_user_id,
                        'partner_user_id'     => $escrow->partner_user_id,
                        'released_amount'     => $released_amount,
                        'je_id'               => $je_id,
                    ),
                    array('%d', '%d', '%s', '%d', '%d', '%f', '%d')
                );

                // Update escrow schedule to released
                $wpdb->update(
                    $escrow_table,
                    array(
                        'status'        => self::ESCROW_RELEASED,
                        'actual_amount' => $released_amount,
                        'released_at'   => current_time('mysql'),
                    ),
                    array('id' => $escrow->id),
                    array('%s', '%f', '%s'),
                    array('%d')
                );

                $released_count++;

            } else {
                // ── Customer inactive or commission not qualified: skip ──
                // Mark escrow as expired (no liability recognized)
                $wpdb->update(
                    $escrow_table,
                    array(
                        'status'      => self::ESCROW_EXPIRED,
                        'actual_amount' => 0,
                        'released_at' => current_time('mysql'),
                    ),
                    array('id' => $escrow->id),
                    array('%s', '%f', '%s'),
                    array('%d')
                );
                $skipped_count++;
            }
        }

        error_log(sprintf(
            '[OraBooks SL-068] Monthly release %s: %d released, %d skipped (inactive), %d errors',
            $current_month,
            $released_count,
            $skipped_count,
            $errors
        ));
    }

    /**
     * SL-068 §5.22: Book a Journal Entry for commission release.
     *
     * Dr Commission Expense (P&L) — expense recognized
     * Cr Commission Payable (Liability) — accrued liability to partner
     *
     * @param int   $commission_id   Commission ID
     * @param int   $partner_user_id Partner user ID
     * @param float $amount          Released amount
     * @return int|WP_Error JE ID or error
     */
    private function book_commission_release_je($commission_id, $partner_user_id, $amount) {
        if (!class_exists('OraBooks_Journal_Entry')) {
            error_log('[OraBooks SL-068] Journal Entry engine not available.');
            return new WP_Error('missing_je_engine', 'Journal Entry engine not available');
        }

        $je = OraBooks_Journal_Entry::get_instance();

        $result = $je->create_journal_entry(array(
            'description' => sprintf(
                __('SL-068: Commission release for commission #%d (partner #%d, month %s)', 'orabooks'),
                $commission_id,
                $partner_user_id,
                date('Y-m')
            ),
            'mode'        => 'business',
            'source_type' => 'commission_release',
            'source_id'   => $commission_id,
            'entry_date'  => current_time('mysql'),
            'lines'       => array(
                array(
                    'account_id' => self::COA_COMMISSION_EXPENSE,
                    'line_type'  => 'debit',
                    'amount'     => $amount,
                    'description' => sprintf(
                        __('Commission expense — commission #%d', 'orabooks'),
                        $commission_id
                    ),
                ),
                array(
                    'account_id' => self::COA_COMMISSION_PAYABLE,
                    'line_type'  => 'credit',
                    'amount'     => $amount,
                    'description' => sprintf(
                        __('Commission payable — partner #%d', 'orabooks'),
                        $partner_user_id
                    ),
                ),
            ),
            'created_by'  => 0, // System-generated
        ));

        if (is_wp_error($result)) {
            return $result;
        }

        // Post the JE immediately (auto-approve system entries)
        $post_result = $je->post_journal_entry($result, 0);
        if (is_wp_error($post_result)) {
            error_log(sprintf(
                '[OraBooks SL-068] Failed to post JE #%d: %s',
                $result,
                $post_result->get_error_message()
            ));
            // Return JE ID anyway — the entry was created even if auto-posting failed
        }

        do_action('orabooks_security_event', 'commission_monthly_release', array(
            'commission_id'   => $commission_id,
            'partner_user_id' => $partner_user_id,
            'amount'          => $amount,
            'je_id'           => $result,
        ));

        return $result;
    }

    /**
     * SL-068 §5.22: Process expiry writeback.
     * When commissions are forfeited/expired that had previously been released
     * (and thus have a liability booked), reverse the liability.
     *
     * Journal Entry:
     *   Dr Commission Payable (Liability) — liability reduced
     *   Cr Commission Expense (P&L) — expense reversed
     *
     * Alternative (per spec): Cr Expired Commission Income (Income)
     *
     * @return int Count of writebacks processed
     */
    public function process_expiry_writeback() {
        global $wpdb;

        $released_table = $wpdb->base_prefix . 'orabooks_commissions_released';
        $comm_table     = $wpdb->base_prefix . 'orabooks_partner_commissions';

        // Find released entries where:
        // - Commission has been forfeited/cancelled
        // - Release has an active JE (je_id IS NOT NULL)
        // - No writeback has been done yet (writeback_je_id IS NULL)
        $to_writeback = $wpdb->get_results(
            "SELECT r.*, c.status as commission_status, c.partner_user_id
             FROM {$released_table} r
             INNER JOIN {$comm_table} c ON r.commission_id = c.id
             WHERE c.status IN ('forfeited', 'cancelled')
               AND r.je_id IS NOT NULL
               AND r.writeback_je_id IS NULL
             LIMIT 100"
        );

        if (empty($to_writeback)) {
            return 0;
        }

        $count = 0;

        foreach ($to_writeback as $release) {
            // Book reverse JE: Dr Commission Payable, Cr Commission Expense
            $reverse_je_id = $this->book_commission_reversal_je(
                $release->commission_id,
                $release->partner_user_id,
                (float) $release->released_amount
            );

            if (is_wp_error($reverse_je_id)) {
                error_log(sprintf(
                    '[OraBooks SL-068] Failed to book reversal JE for commission %d: %s',
                    $release->commission_id,
                    $reverse_je_id->get_error_message()
                ));
                continue;
            }

            // Store the reversal JE ID to prevent duplicate writebacks
            $wpdb->update(
                $released_table,
                array('writeback_je_id' => $reverse_je_id),
                array('id' => $release->id),
                array('%d'),
                array('%d')
            );

            $count++;
        }

        if ($count > 0) {
            do_action('orabooks_security_event', 'commission_expiry_writeback', array(
                'count' => $count,
            ));
        }

        return $count;
    }

    /**
     * SL-068 §5.22: Book a reversal Journal Entry for expired/forfeited commission.
     *
     * Dr Commission Payable (Liability) — reduce liability
     * Cr Commission Expense (P&L) — reverse expense
     *
     * @param int   $commission_id   Commission ID
     * @param int   $partner_user_id Partner user ID
     * @param float $amount          Amount to reverse
     * @return int|WP_Error JE ID or error
     */
    private function book_commission_reversal_je($commission_id, $partner_user_id, $amount) {
        if (!class_exists('OraBooks_Journal_Entry')) {
            return new WP_Error('missing_je_engine', 'Journal Entry engine not available');
        }

        $je = OraBooks_Journal_Entry::get_instance();

        $result = $je->create_journal_entry(array(
            'description' => sprintf(
                __('SL-068: Writeback — commission #%d expired/forfeited (partner #%d)', 'orabooks'),
                $commission_id,
                $partner_user_id
            ),
            'mode'        => 'business',
            'source_type' => 'commission_writeback',
            'source_id'   => $commission_id,
            'entry_date'  => current_time('mysql'),
            'lines'       => array(
                array(
                    'account_id' => self::COA_COMMISSION_PAYABLE,
                    'line_type'  => 'debit',
                    'amount'     => $amount,
                    'description' => sprintf(
                        __('Commission payable reversed — commission #%d', 'orabooks'),
                        $commission_id
                    ),
                ),
                array(
                    'account_id' => self::COA_COMMISSION_EXPENSE,
                    'line_type'  => 'credit',
                    'amount'     => $amount,
                    'description' => sprintf(
                        __('Commission expense reversal — partner #%d', 'orabooks'),
                        $partner_user_id
                    ),
                ),
            ),
            'created_by'  => 0, // System-generated
        ));

        if (is_wp_error($result)) {
            return $result;
        }

        // Post immediately
        $je->post_journal_entry($result, 0);

        return $result;
    }

    /**
     * SL-068: Expire commissions older than the configured max years.
     * Only expires pending and qualified commissions (paid/cancelled/forfeited are terminal).
     *
     * @param int $max_years Number of years before expiry
     * @return int Count of commissions expired
     */
    public function expire_old_commissions($max_years = 6) {
        global $wpdb;

        $table = $wpdb->base_prefix . 'orabooks_partner_commissions';

        $count = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET status = %s,
                 status_notes = CONCAT(status_notes, ' | ', 'Commission expired after %d years'),
                 updated_at = UTC_TIMESTAMP()
             WHERE status IN (%s, %s)
               AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d YEAR)",
            self::STATUS_FORFEITED,
            $max_years,
            self::STATUS_PENDING,
            self::STATUS_QUALIFIED,
            $max_years
        ));

        if ($count && $count > 0) {
            do_action('orabooks_security_event', 'commissions_expired', array(
                'count'      => $count,
                'max_years'  => $max_years,
            ));
        }

        return $count ?: 0;
    }

    /**
     * SL-068: Expire escrow schedules older than max years.
     *
     * @param int $max_years Number of years before expiry
     * @return int Count of schedules expired
     */
    public function expire_old_escrow_schedules($max_years = 6) {
        global $wpdb;

        $escrow_table = $wpdb->base_prefix . 'orabooks_commission_escrow_schedule';

        $count = $wpdb->query($wpdb->prepare(
            "UPDATE {$escrow_table}
             SET status = %s
             WHERE status = %s
               AND scheduled_release_date < DATE_SUB(CURDATE(), INTERVAL %d YEAR)",
            self::ESCROW_EXPIRED,
            self::ESCROW_PENDING,
            $max_years
        ));

        return $count ?: 0;
    }

    // ================================================================
    // PAYOUT CALCULATION
    // ================================================================

    /**
     * SL-068: Get payout summary for a partner — qualified commissions that
     * are ready to be included in a payout batch.
     *
     * Calculates gross/fee/net per commission and checks min_payout_threshold.
     *
     * @param int $partner_user_id Partner user ID
     * @return array {
     *     @type array  items        Qualified commission records
     *     @type float  total_gross  Sum of commission_amount
     *     @type float  total_fee    Sum of fee_amount
     *     @type float  total_net    Sum of net_amount
     *     @type int    count        Number of qualified commissions
     *     @type bool   meets_threshold Whether total_net >= min_payout_threshold
     * }
     */
    public function get_payout_summary($partner_user_id) {
        global $wpdb;

        $table = $wpdb->base_prefix . 'orabooks_partner_commissions';
        $min_threshold = (float) $this->get_platform_config('min_payout_threshold');

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, u.display_name as customer_name
             FROM {$table} c
             LEFT JOIN {$wpdb->users} u ON c.customer_user_id = u.ID
             WHERE c.partner_user_id = %d
               AND c.status = %s
             ORDER BY c.qualified_at ASC",
            $partner_user_id,
            self::STATUS_QUALIFIED
        ));

        $total_gross = 0;
        $total_fee   = 0;
        $total_net   = 0;

        foreach ($items as $item) {
            $total_gross += (float) $item->commission_amount;
            $total_fee   += (float) $item->fee_amount;
            $total_net   += (float) $item->net_amount;
        }

        return array(
            'items'           => $items ?: array(),
            'total_gross'     => round($total_gross, 2),
            'total_fee'       => round($total_fee, 2),
            'total_net'       => round($total_net, 2),
            'count'           => count($items),
            'meets_threshold' => $total_net >= $min_threshold,
            'min_threshold'   => $min_threshold,
        );
    }

    // ================================================================
    // COMMISSION DATA METHODS
    // ================================================================

    /**
     * SL-068: Get commission summary for a partner.
     * Includes gross/fee/net breakdown for qualified and paid commissions.
     *
     * @param int $partner_user_id Partner user ID
     * @return array {
     *     @type float total_pending
     *     @type float total_qualified_gross
     *     @type float total_qualified_net
     *     @type float total_paid
     *     @type int   count_pending
     *     @type int   count_qualified
     *     @type int   count_paid
     * }
     */
    public function get_commission_summary($partner_user_id) {
        global $wpdb;

        $table = $wpdb->base_prefix . 'orabooks_partner_commissions';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT status,
                    COUNT(*) as cnt,
                    COALESCE(SUM(commission_amount), 0) as total_gross,
                    COALESCE(SUM(fee_amount), 0) as total_fee,
                    COALESCE(SUM(net_amount), 0) as total_net,
                    COALESCE(SUM(paid_amount), 0) as total_paid
             FROM {$table}
             WHERE partner_user_id = %d
             GROUP BY status",
            $partner_user_id
        ));

        $summary = array(
            'total_pending'         => 0,
            'total_qualified_gross' => 0,
            'total_qualified_net'   => 0,
            'total_qualified_fee'   => 0,
            'total_paid'            => 0,
            'count_pending'         => 0,
            'count_qualified'       => 0,
            'count_paid'            => 0,
        );

        foreach ($results as $row) {
            switch ($row->status) {
                case self::STATUS_PENDING:
                    $summary['count_pending'] = (int) $row->cnt;
                    break;
                case self::STATUS_QUALIFIED:
                    $summary['total_qualified_gross'] = (float) $row->total_gross;
                    $summary['total_qualified_net']   = (float) $row->total_net;
                    $summary['total_qualified_fee']   = (float) $row->total_fee;
                    $summary['count_qualified']       = (int) $row->cnt;
                    break;
                case self::STATUS_PAID:
                    $summary['total_paid'] = (float) $row->total_paid;
                    $summary['count_paid'] = (int) $row->cnt;
                    break;
            }
        }

        return $summary;
    }

    /**
     * SL-068: Get recent commission records for a partner.
     *
     * @param int $partner_user_id Partner user ID
     * @param int $limit           Max results
     * @return array Array of commission objects with customer display info
     */
    public function get_recent_commissions($partner_user_id, $limit = 10) {
        global $wpdb;

        $table = $wpdb->base_prefix . 'orabooks_partner_commissions';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, u.display_name as customer_name, u.user_email as customer_email
             FROM {$table} c
             LEFT JOIN {$wpdb->users} u ON c.customer_user_id = u.ID
             WHERE c.partner_user_id = %d
             ORDER BY c.created_at DESC
             LIMIT %d",
            $partner_user_id,
            $limit
        ));

        return $results ? $results : array();
    }

    /**
     * SL-068: Get a partner's commission rate.
     * Checks partner type and org config for rate; falls back to default.
     *
     * @param int $user_id User ID
     * @param int $org_id  Organization ID
     * @return float Commission rate percentage
     */
    public function get_partner_commission_rate($user_id, $org_id = 0) {
        $rate = self::DEFAULT_COMMISSION_RATE;

        // Check org config for custom rate
        if ($org_id > 0) {
            $config = get_option('orabooks_org_config_' . $org_id, array());
            if (!empty($config['commission_rate'])) {
                $rate = (float) $config['commission_rate'];
            }
        }

        // Check partner type for different rates
        if (class_exists('OraBooks_Partners')) {
            $partners = OraBooks_Partners::get_instance();
            $code = $partners->get_partner_code($user_id);
            if ($code && !empty($code->partner_type)) {
                // Agency/reseller/strategic partners get higher rate
                $type_rates = array(
                    'individual'         => 10.0,
                    'accountant'         => 12.0,
                    'agency'             => 15.0,
                    'reseller'           => 20.0,
                    'strategic_partner'  => 25.0,
                );
                if (isset($type_rates[$code->partner_type])) {
                    $rate = $type_rates[$code->partner_type];
                }
            }
        }

        return apply_filters('orabooks_commission_rate', $rate, $user_id, $org_id);
    }

    /**
     * SL-068: Get active customer count for a partner (from the read model).
     *
     * @param int $partner_user_id Partner user ID
     * @return int Count of active customers
     */
    public function get_active_customer_count_for_partner($partner_user_id) {
        global $wpdb;

        $attributions_table = $wpdb->base_prefix . 'orabooks_partner_attributions';
        $status_table = $wpdb->base_prefix . 'orabooks_customer_active_status';

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT pa.customer_user_id)
             FROM {$attributions_table} pa
             INNER JOIN {$status_table} cas ON cas.customer_id = pa.customer_user_id AND cas.is_active = 1
             WHERE pa.partner_user_id = %d
               AND pa.status = 'verified'",
            $partner_user_id
        ));

        return $count;
    }

    // ================================================================
    // AJAX ENDPOINTS
    // ================================================================

    /**
     * SL-068: AJAX — Get commission summary for the partner dashboard.
     * POST action: orabooks_commission_summary
     */
    public function ajax_commission_summary() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'orabooks_commission_dashboard')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'orabooks')));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'orabooks')));
        }

        $user_id = get_current_user_id();
        $is_partner = get_user_meta($user_id, 'is_partner', true);
        if (!$is_partner) {
            wp_send_json_error(array('message' => __('Partners only.', 'orabooks')));
        }

        $summary = $this->get_commission_summary($user_id);
        $rate = $this->get_partner_commission_rate($user_id);
        $payout = $this->get_payout_summary($user_id);
        $config = array(
            'min_payout_threshold' => (float) $this->get_platform_config('min_payout_threshold'),
            'payout_fee_rate'      => (float) $this->get_platform_config('payout_fee_rate'),
            'customer_active_window_days' => (int) $this->get_platform_config('customer_active_window_days'),
        );

        // Calculate estimated pending commission value
        $pending_estimated = 0;
        if ($summary['count_pending'] > 0 && $summary['count_qualified'] > 0) {
            $avg_qualified = $summary['total_qualified_gross'] / $summary['count_qualified'];
            $pending_estimated = round($summary['count_pending'] * $avg_qualified, 2);
        }

        wp_send_json_success(array(
            'summary'            => $summary,
            'rate'               => $rate,
            'payout'             => $payout,
            'config'             => $config,
            'pending_estimated'  => $pending_estimated,
            'customer_count'     => $this->get_active_customer_count_for_partner($user_id),
        ));
    }

    /**
     * SL-068: AJAX — Get commission history for the partner dashboard.
     * POST action: orabooks_commission_history
     */
    public function ajax_commission_history() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'orabooks_commission_dashboard')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'orabooks')));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'orabooks')));
        }

        $user_id = get_current_user_id();
        $is_partner = get_user_meta($user_id, 'is_partner', true);
        if (!$is_partner) {
            wp_send_json_error(array('message' => __('Partners only.', 'orabooks')));
        }

        $limit = isset($_POST['limit']) ? min((int) $_POST['limit'], 50) : 10;
        $commissions = $this->get_recent_commissions($user_id, $limit);

        $status_labels = array(
            self::STATUS_PENDING   => __('Pending', 'orabooks'),
            self::STATUS_QUALIFIED => __('Qualified', 'orabooks'),
            self::STATUS_PAID      => __('Paid', 'orabooks'),
            self::STATUS_CANCELLED => __('Cancelled', 'orabooks'),
            self::STATUS_FORFEITED => __('Forfeited', 'orabooks'),
        );

        $formatted = array();
        foreach ($commissions as $c) {
            $formatted[] = array(
                'id'                => (int) $c->id,
                'customer_name'     => $c->customer_name ?: __('(unknown)', 'orabooks'),
                'customer_email'    => $c->customer_email,
                'commission_rate'   => (float) $c->commission_rate,
                'commission_amount' => $c->commission_amount ? (float) $c->commission_amount : null,
                'fee_amount'        => $c->fee_amount ? (float) $c->fee_amount : null,
                'net_amount'        => $c->net_amount ? (float) $c->net_amount : null,
                'qualified_amount'  => $c->qualified_amount ? (float) $c->qualified_amount : null,
                'paid_amount'       => $c->paid_amount ? (float) $c->paid_amount : null,
                'status'            => $c->status,
                'status_label'      => isset($status_labels[$c->status]) ? $status_labels[$c->status] : $c->status,
                'created_at'        => $c->created_at,
                'qualified_at'      => $c->qualified_at,
                'paid_at'           => $c->paid_at,
            );
        }

        wp_send_json_success(array(
            'commissions' => $formatted,
        ));
    }

    /**
     * SL-068: AJAX — Get payout summary for the partner dashboard.
     * POST action: orabooks_payout_summary
     */
    public function ajax_payout_summary() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'orabooks_commission_dashboard')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'orabooks')));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'orabooks')));
        }

        $user_id = get_current_user_id();
        $is_partner = get_user_meta($user_id, 'is_partner', true);
        if (!$is_partner) {
            wp_send_json_error(array('message' => __('Partners only.', 'orabooks')));
        }

        $payout = $this->get_payout_summary($user_id);

        $items = array();
        foreach ($payout['items'] as $item) {
            $items[] = array(
                'commission_id'     => (int) $item->id,
                'customer_name'     => $item->customer_name ?: __('(unknown)', 'orabooks'),
                'commission_amount' => (float) $item->commission_amount,
                'fee_amount'        => (float) $item->fee_amount,
                'net_amount'        => (float) $item->net_amount,
                'qualified_at'      => $item->qualified_at,
                'commission_rate'   => (float) $item->commission_rate,
            );
        }

        wp_send_json_success(array(
            'items'           => $items,
            'total_gross'     => $payout['total_gross'],
            'total_fee'       => $payout['total_fee'],
            'total_net'       => $payout['total_net'],
            'count'           => $payout['count'],
            'meets_threshold' => $payout['meets_threshold'],
            'min_threshold'   => $payout['min_threshold'],
        ));
    }
}

// Initialize the commissions system
OraBooks_Commissions::get_instance();
