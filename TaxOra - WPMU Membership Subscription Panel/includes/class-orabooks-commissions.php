<?php
/**
 * SL-068 – Partner Commissions System
 *
 * Manages commission tracking, qualification, and payout lifecycle.
 * Listens to partner_attribution_verified events to create pending commissions.
 * Commissions move through: pending → qualified → paid | cancelled | forfeited.
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

    /**
     * Commission status enum
     */
    const STATUS_PENDING   = 'pending';
    const STATUS_QUALIFIED = 'qualified';
    const STATUS_PAID      = 'paid';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_FORFEITED = 'forfeited';

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
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init_table_names'));

        // ── SL-068: Event listeners ─────────────────────────────────────────
        add_action('partner_attribution_verified', array($this, 'on_attribution_verified'), 10, 1);
        add_action('orabooks_partner_suspended', array($this, 'on_partner_suspended'), 10, 1);
        add_action('orabooks_partner_fraud_freeze', array($this, 'on_partner_fraud_freeze'), 10, 1);

        // ── SL-068: AJAX endpoints for partner dashboard ────────────────────
        add_action('wp_ajax_orabooks_commission_summary', array($this, 'ajax_commission_summary'));
        add_action('wp_ajax_orabooks_commission_history', array($this, 'ajax_commission_history'));
    }

    /**
     * SL-068: Initialize table names for multisite.
     */
    public function init_table_names() {
        global $wpdb;

        $prefix = is_multisite() ? $wpdb->base_prefix : $wpdb->prefix;
        $wpdb->orabooks_partner_commissions = $prefix . 'orabooks_partner_commissions';
    }

    /**
     * SL-068: Create commission table schema.
     * Run during plugin activation.
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

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

        // Add payout_batch_id column if missing (for upgrades)
        $column_check = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'payout_batch_id'");
        if (!$column_check) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN payout_batch_id BIGINT NULL AFTER paid_at, ADD INDEX idx_payout (payout_batch_id)");
        }

        error_log('[OraBooks SL-068] Partner commissions table created/verified.');
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
     * @param int   $commission_id   Commission record ID
     * @param float $qualified_amount The qualified revenue amount (e.g., first payment)
     * @param string $notes           Optional notes
     * @return true|WP_Error
     */
    public function qualify_commission($commission_id, $qualified_amount, $notes = '') {
        // Get the commission to determine the rate
        global $wpdb;
        $table = $wpdb->base_prefix . 'orabooks_partner_commissions';
        $commission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $commission_id
        ));

        $rate = $commission ? (float) $commission->commission_rate : self::DEFAULT_COMMISSION_RATE;
        $commission_amount = round($qualified_amount * ($rate / 100), 2);

        return $this->transition_status(
            $commission_id,
            self::STATUS_QUALIFIED,
            array(
                'commission_amount' => $commission_amount,
                'qualified_amount'  => $qualified_amount,
                'qualified_at'      => current_time('mysql'),
                'status_notes'      => $notes ?: sprintf(
                    __('Commission qualified: %s (%.2f%% of %s)', 'orabooks'),
                    number_format($commission_amount, 2),
                    $rate,
                    number_format($qualified_amount, 2)
                ),
            )
        );
    }

    /**
     * SL-068: Mark a commission as paid.
     *
     * @param int   $commission_id Commission record ID
     * @param float $paid_amount   Amount paid
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
    // COMMISSION DATA METHODS
    // ================================================================

    /**
     * SL-068: Get commission summary for a partner.
     *
     * @param int $partner_user_id Partner user ID
     * @return array {
     *     @type float total_pending
     *     @type float total_qualified
     *     @type float total_paid
     *     @type float total_cancelled
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
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN paid_amount ELSE 0 END), 0) as paid_total,
                    COALESCE(SUM(CASE WHEN status = 'qualified' THEN qualified_amount ELSE 0 END), 0) as qualified_total
             FROM {$table}
             WHERE partner_user_id = %d
             GROUP BY status",
            $partner_user_id
        ));

        $summary = array(
            'total_pending'   => 0,
            'total_qualified' => 0,
            'total_paid'      => 0,
            'total_cancelled' => 0,
            'count_pending'   => 0,
            'count_qualified' => 0,
            'count_paid'      => 0,
        );

        foreach ($results as $row) {
            switch ($row->status) {
                case self::STATUS_PENDING:
                    $summary['count_pending'] = (int) $row->cnt;
                    break;
                case self::STATUS_QUALIFIED:
                    $summary['total_qualified'] = (float) $row->qualified_total;
                    $summary['count_qualified'] = (int) $row->cnt;
                    break;
                case self::STATUS_PAID:
                    $summary['total_paid'] = (float) $row->paid_total;
                    $summary['count_paid'] = (int) $row->cnt;
                    break;
                case self::STATUS_CANCELLED:
                    $summary['total_cancelled'] += (int) $row->cnt;
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

        // Calculate estimated pending commission value
        $pending_estimated = 0;
        if ($summary['count_pending'] > 0 && $summary['count_qualified'] > 0) {
            $avg_qualified = $summary['total_qualified'] / $summary['count_qualified'];
            $pending_estimated = round($summary['count_pending'] * $avg_qualified * ($rate / 100), 2);
        }

        wp_send_json_success(array(
            'summary' => $summary,
            'rate'    => $rate,
            'pending_estimated' => $pending_estimated,
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
                'id'               => (int) $c->id,
                'customer_name'    => $c->customer_name ?: __('(unknown)', 'orabooks'),
                'customer_email'   => $c->customer_email,
                'commission_rate'  => (float) $c->commission_rate,
                'qualified_amount' => $c->qualified_amount ? (float) $c->qualified_amount : null,
                'paid_amount'      => $c->paid_amount ? (float) $c->paid_amount : null,
                'status'           => $c->status,
                'status_label'     => isset($status_labels[$c->status]) ? $status_labels[$c->status] : $c->status,
                'created_at'       => $c->created_at,
                'qualified_at'     => $c->qualified_at,
                'paid_at'          => $c->paid_at,
            );
        }

        wp_send_json_success(array(
            'commissions' => $formatted,
        ));
    }
}

// Initialize the commissions system
OraBooks_Commissions::get_instance();
