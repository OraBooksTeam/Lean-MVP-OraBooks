<?php
/**
 * SL-013 – Partner Codes, Attributions, and Terms Acceptance
 *
 * Manages partner program functionality including:
 * - partner_codes table: Partner code generation, validation, lifecycle
 * - partner_attributions table: Customer attribution to partners
 * - partner_terms_acceptance table: Partner terms acceptance tracking
 *
 * Build Order: SL-004 → SL-013 → SL-003 → SL-017 → SL-139 → SL-068
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Partners {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Valid partner types
     */
    const PARTNER_TYPES = array('individual', 'accountant', 'agency', 'reseller', 'strategic_partner');

    /**
     * Partner code status enum
     */
    const CODE_STATUS = array('pending_review', 'active', 'disabled', 'expired', 'inactive');

    /**
     * Attribution status enum
     */
    const ATTRIBUTION_STATUS = array('pending', 'verified', 'blocked');

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

        // ── SL-013: Partner inactivity & low-activity daily job ──────────
        add_action('init', array($this, 'schedule_inactivity_job'));
        add_action('orabooks_partner_inactivity_daily', array($this, 'process_inactivity_check'));

        // ── SL-013 §5.7: Partner onboarding page ─────────────────────────
        add_shortcode('orabooks_partner_onboarding', array($this, 'render_onboarding_page'));
        add_action('init', array($this, 'register_onboarding_rewrite'));
        add_filter('query_vars', array($this, 'add_onboarding_query_var'));
        add_action('template_redirect', array($this, 'handle_onboarding_page'));

        // ── SL-139: Partner Dashboard ───────────────────────────────────────
        add_shortcode('orabooks_partner_dashboard', array($this, 'render_dashboard_page'));
        add_action('init', array($this, 'register_dashboard_rewrite'));
        add_filter('query_vars', array($this, 'add_dashboard_query_var'));
        add_action('template_redirect', array($this, 'handle_dashboard_page'));

        // ── SL-139: AJAX – Partner reactivation request ────────────────────
        add_action('wp_ajax_orabooks_partner_reactivation_request', array($this, 'ajax_reactivation_request'));

        // ── SL-139: AJAX – Partner code copy tracking ────────────────────────
        add_action('wp_ajax_orabooks_partner_code_copied', array($this, 'ajax_code_copied'));

        // ── SL-139: REST API endpoints for partner onboarding & dashboard ─────
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * SL-013: Initialize table names for multisite.
     */
    public function init_table_names() {
        global $wpdb;

        $prefix = is_multisite() ? $wpdb->base_prefix : $wpdb->prefix;
        $wpdb->orabooks_partner_codes = $prefix . 'orabooks_partner_codes';
        $wpdb->orabooks_partner_attributions = $prefix . 'orabooks_partner_attributions';
        $wpdb->orabooks_partner_terms_acceptance = $prefix . 'orabooks_partner_terms_acceptance';
    }

    /**
     * SL-013 §5.1: Create partner-related tables and add users table columns.
     * Run during plugin activation.
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Add users table columns for SL-013
        self::add_users_table_columns();

        // ============================================================
        // PARTNER CODES
        // ============================================================
        $partner_codes_table = $wpdb->base_prefix . 'orabooks_partner_codes';
        $sql_codes = "CREATE TABLE IF NOT EXISTS {$partner_codes_table} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            org_id INT NOT NULL,
            user_id INT NOT NULL,
            partner_code VARCHAR(32) UNIQUE NOT NULL,
            partner_code_normalized VARCHAR(32) GENERATED ALWAYS AS (UPPER(TRIM(partner_code))) STORED,
            partner_type ENUM('individual','accountant','agency','reseller','strategic_partner') NOT NULL DEFAULT 'individual',
            organization_name VARCHAR(255) NULL,
            status ENUM('pending_review','active','disabled','expired','inactive') DEFAULT 'pending_review',
            is_active_code BOOLEAN GENERATED ALWAYS AS (status = 'active') STORED,
            created_at TIMESTAMP DEFAULT (UTC_TIMESTAMP()),
            updated_at TIMESTAMP DEFAULT (UTC_TIMESTAMP()) ON UPDATE CURRENT_TIMESTAMP,
            approved_at TIMESTAMP NULL,
            approved_by INT NULL,
            disabled_at TIMESTAMP NULL,
            disabled_reason TEXT NULL,
            expires_at TIMESTAMP NULL,
            last_attribution_at TIMESTAMP NULL,
            deactivation_reminder_sent_at TIMESTAMP NULL,
            low_activity_reminder_sent_at TIMESTAMP NULL,
            FOREIGN KEY (org_id) REFERENCES {$wpdb->base_prefix}orabooks_organizations(id),
            INDEX idx_code_normalized (partner_code_normalized),
            INDEX idx_user (user_id),
            INDEX idx_inactive (status, last_attribution_at),
            UNIQUE KEY uk_user_active_code (user_id, is_active_code)
        ) {$charset_collate};";
        dbDelta($sql_codes);

        // ============================================================
        // PARTNER ATTRIBUTIONS
        // ============================================================
        $attributions_table = $wpdb->base_prefix . 'orabooks_partner_attributions';
        $sql_attributions = "CREATE TABLE IF NOT EXISTS {$attributions_table} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            org_id INT NULL,
            partner_user_id INT NOT NULL,
            customer_user_id INT NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            partner_code_used VARCHAR(32) NOT NULL,
            partner_code_normalized VARCHAR(32) GENERATED ALWAYS AS (UPPER(TRIM(partner_code_used))) STORED,
            attribution_date TIMESTAMP DEFAULT (UTC_TIMESTAMP()),
            status ENUM('pending','verified','blocked') DEFAULT 'pending',
            verified_at TIMESTAMP NULL,
            blocked_at TIMESTAMP NULL,
            blocked_reason TEXT NULL,
            idempotency_key VARCHAR(128) UNIQUE,
            INDEX idx_partner (partner_user_id),
            INDEX idx_customer (customer_user_id),
            INDEX idx_status (status)
        ) {$charset_collate};";
        dbDelta($sql_attributions);

        // ============================================================
        // PARTNER TERMS ACCEPTANCE
        // ============================================================
        $terms_table = $wpdb->base_prefix . 'orabooks_partner_terms_acceptance';
        $sql_terms = "CREATE TABLE IF NOT EXISTS {$terms_table} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            terms_version VARCHAR(20) NOT NULL,
            accepted_at TIMESTAMP DEFAULT (UTC_TIMESTAMP()),
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(id),
            INDEX idx_user (user_id)
        ) {$charset_collate};";
        dbDelta($sql_terms);

        error_log('[OraBooks SL-013] Partner codes, attributions, and terms acceptance tables created/verified.');
    }

    /**
     * SL-013: Add required columns to WordPress users table.
     * Adds: is_partner, is_email_verified, is_2fa_enabled, org_id
     */
    private static function add_users_table_columns() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $users_table = $wpdb->users;

        // Check if columns exist, add if not
        $columns_to_add = array(
            'is_partner' => "BOOLEAN DEFAULT FALSE",
            'is_email_verified' => "BOOLEAN DEFAULT FALSE",
            'is_2fa_enabled' => "BOOLEAN DEFAULT FALSE",
            'org_id' => "INT NULL",
            'email_verification_token' => "VARCHAR(64) NULL",
            'email_verification_expires_at' => "TIMESTAMP NULL",
            'auth_provider' => "VARCHAR(20) DEFAULT 'local'",
        );

        foreach ($columns_to_add as $column => $definition) {
            $column_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = %s 
                 AND COLUMN_NAME = %s",
                $users_table,
                $column
            ));

            if (!$column_exists) {
                $wpdb->query("ALTER TABLE {$users_table} ADD COLUMN {$column} {$definition}");
                error_log('[OraBooks SL-013] Added column ' . $column . ' to users table');
            }
        }

        // Add indexes for performance
        $wpdb->query("ALTER TABLE {$users_table} ADD INDEX idx_org_id (org_id)");
        $wpdb->query("ALTER TABLE {$users_table} ADD INDEX idx_is_partner (is_partner)");
        $wpdb->query("ALTER TABLE {$users_table} ADD INDEX idx_email_verified (is_email_verified)");

        error_log('[OraBooks SL-013] Users table columns verified/added.');
    }

    // ============================================================
    // PARTNER CODE MANAGEMENT
    // ============================================================

    /**
     * SL-013: Generate a cryptographically secure partner code.
     * Format: PARTNER-XXXXXXXX (8 uppercase alphanumeric)
     *
     * @return string Partner code
     */
    public static function generate_partner_code() {
        $random_bytes = random_bytes(8);
        $hash = hash('sha256', $random_bytes, false);
        $code = 'PARTNER-' . strtoupper(substr($hash, 0, 8));
        return $code;
    }

    /**
     * SL-013: Create a partner code for a user.
     *
     * @param int    $org_id          Organization ID
     * @param int    $user_id         User ID
     * @param string $partner_type    Partner type
     * @param string $organization_name Organization name (for org partners)
     * @return array|WP_Error Result with partner_code, or error
     */
    public function create_partner_code($org_id, $user_id, $partner_type = 'individual', $organization_name = null) {
        global $wpdb;

        // Validate partner type
        if (!in_array($partner_type, self::PARTNER_TYPES, true)) {
            return new WP_Error('invalid_partner_type', __('Invalid partner type.', 'orabooks'));
        }

        // Organization name required for agency/reseller/strategic_partner
        if (in_array($partner_type, array('agency', 'reseller', 'strategic_partner'), true) && empty($organization_name)) {
            return new WP_Error('org_name_required', __('Organization name is required for this partner type.', 'orabooks'));
        }

        // Disable any previous active code for this user
        $codes_table = $wpdb->base_prefix . 'orabooks_partner_codes';
        $wpdb->update(
            $codes_table,
            array('status' => 'disabled', 'disabled_at' => current_time('mysql')),
            array('user_id' => $user_id, 'status' => 'active'),
            array('%s', '%s'),
            array('%d', '%s')
        );

        // Generate new code
        $partner_code = self::generate_partner_code();

        $wpdb->insert(
            $codes_table,
            array(
                'org_id' => $org_id,
                'user_id' => $user_id,
                'partner_code' => $partner_code,
                'partner_type' => $partner_type,
                'organization_name' => $organization_name,
                'status' => 'pending_review',
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );

        if ($wpdb->last_error) {
            return new WP_Error('db_error', $wpdb->last_error);
        }

        // Audit event
        do_action('orabooks_security_event', 'partner_code_generated', array(
            'org_id' => $org_id,
            'user_id' => $user_id,
            'partner_code' => $partner_code,
            'partner_type' => $partner_type,
        ));

        return array(
            'partner_code' => $partner_code,
            'status' => 'pending_review',
        );
    }

    /**
     * SL-013: Validate a partner code during customer signup.
     * Includes:
     * - Self-attribution fraud check (always blocked)
     * - Email domain fraud check (configurable via block_same_email_domain)
     * - Inactive/disabled codes rejected
     *
     * @param string $partner_code Partner code to validate
     * @param int    $customer_user_id Customer user ID (for fraud check)
     * @param string $customer_email Customer email (for email domain check)
     * @return array|WP_Error Partner info or error
     */
    public function validate_partner_code($partner_code, $customer_user_id, $customer_email = '') {
        global $wpdb;

        $partner_code_normalized = strtoupper(trim($partner_code));
        $codes_table = $wpdb->base_prefix . 'orabooks_partner_codes';

        $partner_code_row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$codes_table} WHERE partner_code_normalized = %s AND status = 'active'",
            $partner_code_normalized
        ));

        if (!$partner_code_row) {
            return new WP_Error('invalid_code', __('Invalid or inactive partner code.', 'orabooks'));
        }

        // Fraud check: self-attribution blocked (always enforced)
        if ((int)$partner_code_row->user_id === (int)$customer_user_id) {
            return new WP_Error('self_attribution', __('Cannot attribute to yourself.', 'orabooks'));
        }

        // Email domain fraud check (SL-013: configurable, default OFF)
        if (!empty($customer_email)) {
            $org_id = $partner_code_row->org_id;
            $block_same_domain = $this->get_block_same_email_domain_config($org_id);

            if ($block_same_domain) {
                $customer_domain = strtolower(trim(substr($customer_email, strpos($customer_email, '@') + 1)));
                
                // Get partner's email to extract their domain
                $partner_user = get_userdata($partner_code_row->user_id);
                if ($partner_user && !empty($partner_user->user_email)) {
                    $partner_domain = strtolower(trim(substr($partner_user->user_email, strpos($partner_user->user_email, '@') + 1)));

                    if ($customer_domain === $partner_domain) {
                        do_action('orabooks_security_event', 'partner_attribution_blocked_same_domain', array(
                            'partner_user_id' => $partner_code_row->user_id,
                            'customer_user_id' => $customer_user_id,
                            'customer_email' => $customer_email,
                            'partner_domain' => $partner_domain,
                        ));
                        return new WP_Error('same_domain_blocked', __('Attribution from the same email domain is not allowed.', 'orabooks'));
                    }
                }
            }
        }

        return array(
            'partner_user_id' => $partner_code_row->user_id,
            'partner_code' => $partner_code_row->partner_code,
            'partner_type' => $partner_code_row->partner_type,
        );
    }

    /**
     * SL-013: Create a pending attribution for a customer.
     *
     * @param int    $partner_user_id Partner user ID
     * @param int    $customer_user_id Customer user ID
     * @param string $customer_email Customer email
     * @param string $partner_code Partner code used
     * @return true|WP_Error
     */
    public function create_attribution($partner_user_id, $customer_user_id, $customer_email, $partner_code) {
        global $wpdb;

        $attributions_table = $wpdb->base_prefix . 'orabooks_partner_attributions';

        // Generate idempotency key
        $idempotency_key = hash('sha256', $partner_code . $customer_email);

        // Check if attribution already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$attributions_table} WHERE idempotency_key = %s",
            $idempotency_key
        ));

        if ($existing) {
            return new WP_Error('attribution_exists', __('Attribution already exists.', 'orabooks'));
        }

        $wpdb->insert(
            $attributions_table,
            array(
                'partner_user_id' => $partner_user_id,
                'customer_user_id' => $customer_user_id,
                'customer_email' => $customer_email,
                'partner_code_used' => $partner_code,
                'status' => 'pending',
                'attribution_date' => current_time('mysql'),
                'idempotency_key' => $idempotency_key,
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );

        if ($wpdb->last_error) {
            return new WP_Error('db_error', $wpdb->last_error);
        }

        // Audit event
        do_action('orabooks_security_event', 'partner_attribution_created', array(
            'partner_user_id' => $partner_user_id,
            'customer_user_id' => $customer_user_id,
            'partner_code' => $partner_code,
        ));

        return true;
    }

    /**
     * SL-013: Verify attribution when customer email is verified.
     *
     * @param int $customer_user_id Customer user ID
     * @return true|WP_Error
     */
    public function verify_attribution($customer_user_id) {
        global $wpdb;

        $attributions_table = $wpdb->base_prefix . 'orabooks_partner_attributions';
        $codes_table = $wpdb->base_prefix . 'orabooks_partner_codes';

        // Get pending attribution
        $attribution = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$attributions_table} WHERE customer_user_id = %d AND status = 'pending'",
            $customer_user_id
        ));

        if (!$attribution) {
            return true; // No pending attribution, nothing to do
        }

        // Update attribution to verified
        $wpdb->update(
            $attributions_table,
            array('status' => 'verified', 'verified_at' => current_time('mysql')),
            array('id' => $attribution->id),
            array('%s', '%s'),
            array('%d')
        );

        // Get partner org_id from partner_codes for event payload
        $partner_code = $wpdb->get_row($wpdb->prepare(
            "SELECT org_id FROM {$codes_table} WHERE user_id = %d AND status = 'active' LIMIT 1",
            $attribution->partner_user_id
        ));
        $org_id = $partner_code ? (int) $partner_code->org_id : 0;

        // Update partner's last_attribution_at and reset reminder flags
        $wpdb->update(
            $codes_table,
            array(
                'last_attribution_at' => current_time('mysql'),
                'deactivation_reminder_sent_at' => NULL,
                'low_activity_reminder_sent_at' => NULL,
            ),
            array('user_id' => $attribution->partner_user_id),
            array('%s', '%s', '%s'),
            array('%d')
        );

        // Audit events
        do_action('orabooks_security_event', 'partner_attribution_verified', array(
            'attribution_id' => $attribution->id,
            'partner_user_id' => $attribution->partner_user_id,
            'customer_user_id' => $customer_user_id,
            'org_id' => $org_id,
        ));

        // Trigger partner_attribution_verified event for SL-068
        do_action('partner_attribution_verified', array(
            'attribution_id' => $attribution->id,
            'partner_user_id' => $attribution->partner_user_id,
            'customer_user_id' => $customer_user_id,
            'verified_at' => current_time('mysql'),
            'org_id' => $org_id,
            'verified_at' => current_time('mysql'),
        ));

        return true;
    }

    /**
     * SL-013: Record partner terms acceptance.
     *
     * @param int    $user_id User ID
     * @param string $terms_version Terms version
     * @return true|WP_Error
     */
    public function record_terms_acceptance($user_id, $terms_version = '1.0') {
        global $wpdb;

        $terms_table = $wpdb->base_prefix . 'orabooks_partner_terms_acceptance';

        $wpdb->insert(
            $terms_table,
            array(
                'user_id' => $user_id,
                'terms_version' => $terms_version,
                'accepted_at' => current_time('mysql'),
                'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );

        if ($wpdb->last_error) {
            return new WP_Error('db_error', $wpdb->last_error);
        }

        // Audit event
        do_action('orabooks_security_event', 'partner_terms_accepted', array(
            'user_id' => $user_id,
            'terms_version' => $terms_version,
        ));

        return true;
    }

    /**
     * SL-013: Get partner code info for a user.
     *
     * @param int $user_id User ID
     * @return object|false Partner code object or false
     */
    public function get_partner_code($user_id) {
        global $wpdb;

        $codes_table = $wpdb->base_prefix . 'orabooks_partner_codes';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$codes_table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));
    }

    /**
     * SL-013: Get partner's active code.
     *
     * @param int $user_id User ID
     * @return object|false Active partner code or false
     */
    public function get_active_partner_code($user_id) {
        global $wpdb;

        $codes_table = $wpdb->base_prefix . 'orabooks_partner_codes';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$codes_table} WHERE user_id = %d AND status = 'active'",
            $user_id
        ));
    }

    /**
     * SL-013: Approve a partner code (admin action, reserved for SL-140).
     *
     * @param int $user_id User ID
     * @param int $approved_by Admin user ID
     * @param string $note Optional note
     * @return true|WP_Error
     */
    public function approve_partner_code($user_id, $approved_by, $note = '') {
        global $wpdb;

        $codes_table = $wpdb->base_prefix . 'orabooks_partner_codes';
        $orgs_table = $wpdb->base_prefix . 'orabooks_organizations';

        // Get partner code
        $partner_code = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$codes_table} WHERE user_id = %d AND status = 'pending_review'",
            $user_id
        ));

        if (!$partner_code) {
            return new WP_Error('code_not_found', __('Pending partner code not found.', 'orabooks'));
        }

        // Update partner code status
        $wpdb->update(
            $codes_table,
            array('status' => 'active', 'approved_at' => current_time('mysql'), 'approved_by' => $approved_by),
            array('id' => $partner_code->id),
            array('%s', '%s', '%d'),
            array('%d')
        );

        // Update organization status
        $wpdb->update(
            $orgs_table,
            array('status' => 'active'),
            array('id' => $partner_code->org_id),
            array('%s'),
            array('%d')
        );

        // Audit events
        do_action('orabooks_security_event', 'partner_code_approved', array(
            'user_id' => $user_id,
            'approved_by' => $approved_by,
            'note' => $note,
        ));

        return true;
    }

    /**
     * SL-013: Reject a partner code (admin action, reserved for SL-140).
     *
     * @param int $user_id User ID
     * @param int $rejected_by Admin user ID
     * @param string $reason Rejection reason
     * @return true|WP_Error
     */
    public function reject_partner_code($user_id, $rejected_by, $reason) {
        global $wpdb;

        $codes_table = $wpdb->base_prefix . 'orabooks_partner_codes';

        $wpdb->update(
            $codes_table,
            array('status' => 'disabled', 'disabled_at' => current_time('mysql'), 'disabled_reason' => $reason),
            array('user_id' => $user_id, 'status' => 'pending_review'),
            array('%s', '%s', '%s'),
            array('%d', '%s')
        );

        // Audit event
        do_action('orabooks_security_event', 'partner_code_rejected', array(
            'user_id' => $user_id,
            'rejected_by' => $rejected_by,
            'reason' => $reason,
        ));

        return true;
    }

    // ================================================================
    // INACTIVITY & LOW-ACTIVITY GOVERNANCE (SL-013 §5.18)
    // ================================================================

    /**
     * SL-013 §5.18: Schedule the daily inactivity background job.
     * Runs via WordPress cron at approximately 2 AM daily.
     */
    public function schedule_inactivity_job() {
        if (!wp_next_scheduled('orabooks_partner_inactivity_daily')) {
            wp_schedule_event(
                strtotime('tomorrow 02:00'), // First run at 2 AM tomorrow
                'daily',
                'orabooks_partner_inactivity_daily'
            );
        }
    }

    /**
     * SL-013 §5.18: Get count of active customers attributed to a partner.
     * Source of truth: SL-021 customers table (is_active = TRUE).
     * For MVP: uses partner_attributions with verified status.
     *
     * @param int $partner_user_id Partner user ID
     * @return int Active customer count
     */
    public function get_active_customer_count($partner_user_id) {
        global $wpdb;

        $attributions_table = $wpdb->base_prefix . 'orabooks_partner_attributions';

        // For MVP: count verified attributions that have active WordPress users
        // In production (SL-021): JOIN with customers table and check is_active flag
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT pa.customer_user_id)
             FROM {$attributions_table} pa
             WHERE pa.partner_user_id = %d
               AND pa.status = 'verified'",
            $partner_user_id
        ));

        return $count;
    }

    /**
     * SL-013 §5.18: Process inactivity & low-activity for all active partners.
     * Designed to run daily via cron (orabooks_partner_inactivity_daily).
     *
     * Logic per spec:
     *   active with zero active customers AND no attribution for 11 months → send deactivation warning
     *   active with zero active customers AND no attribution for 12 months → deactivate (inactive)
     *   active (any) with no attribution for 6 months → send low-activity reminder (repeat every 3 months)
     *   active with new attribution → reset both reminder timestamps (handled in verify_attribution)
     */
    public function process_inactivity_check() {
        global $wpdb;

        $codes_table = $wpdb->base_prefix . 'orabooks_partner_codes';

        // Fetch all active partner codes
        $partners = $wpdb->get_results(
            "SELECT id, user_id, last_attribution_at, 
                    deactivation_reminder_sent_at, low_activity_reminder_sent_at
             FROM {$codes_table}
             WHERE status = 'active'"
        );

        if (empty($partners)) {
            return;
        }

        $now = current_time('mysql');

        foreach ($partners as $partner) {
            $active_customers = $this->get_active_customer_count($partner->user_id);

            // ── Deactivation logic (only if zero active customers) ──────
            if ($active_customers === 0) {
                $last_attribution = $partner->last_attribution_at;

                // 11 months: send deactivation warning (if not already sent)
                if ($this->is_older_than($last_attribution, 11, 'months') && empty($partner->deactivation_reminder_sent_at)) {
                    $this->send_inactivity_notification(
                        $partner->user_id,
                        'partner_inactivity_reminder',
                        array('days' => 330, 'partner_code_id' => $partner->id)
                    );

                    $wpdb->update(
                        $codes_table,
                        array('deactivation_reminder_sent_at' => $now),
                        array('id' => $partner->id),
                        array('%s'),
                        array('%d')
                    );

                    do_action('orabooks_security_event', 'partner_inactivity_reminder_sent', array(
                        'partner_user_id' => $partner->user_id,
                        'partner_code_id' => $partner->id,
                    ));
                }

                // 12 months: deactivate
                if ($this->is_older_than($last_attribution, 12, 'months')) {
                    $wpdb->update(
                        $codes_table,
                        array('status' => 'inactive'),
                        array('id' => $partner->id),
                        array('%s'),
                        array('%d')
                    );

                    $this->send_inactivity_notification(
                        $partner->user_id,
                        'partner_code_inactivated',
                        array(
                            'reason' => '12 months no attribution and zero active customers',
                            'partner_code_id' => $partner->id,
                        )
                    );

                    do_action('orabooks_security_event', 'partner_code_inactivated', array(
                        'partner_user_id' => $partner->user_id,
                        'partner_code_id' => $partner->id,
                    ));
                }
            }

            // ── Low-activity reminder (regardless of active customers) ──
            $last_attribution = $partner->last_attribution_at;
            if ($this->is_older_than($last_attribution, 6, 'months')) {
                $last_reminder = $partner->low_activity_reminder_sent_at;
                $should_remind = false;

                if (empty($last_reminder)) {
                    $should_remind = true;
                } elseif ($this->is_older_than($last_reminder, 3, 'months')) {
                    $should_remind = true;
                }

                if ($should_remind) {
                    $this->send_inactivity_notification(
                        $partner->user_id,
                        'partner_low_activity_reminder',
                        array('months' => 6, 'partner_code_id' => $partner->id)
                    );

                    $wpdb->update(
                        $codes_table,
                        array('low_activity_reminder_sent_at' => $now),
                        array('id' => $partner->id),
                        array('%s'),
                        array('%d')
                    );

                    do_action('orabooks_security_event', 'partner_low_activity_reminder_sent', array(
                        'partner_user_id' => $partner->user_id,
                        'partner_code_id' => $partner->id,
                    ));
                }
            }
        }

        error_log('[OraBooks SL-013] Partner inactivity check completed for ' . count($partners) . ' active partners.');
    }

    /**
     * SL-013: Check if a timestamp is older than a given interval from now.
     *
     * @param string|null $timestamp MySQL datetime or null
     * @param int         $amount    Number of units
     * @param string      $unit      'months' or 'days'
     * @return bool True if timestamp is null or older than the interval
     */
    private function is_older_than($timestamp, $amount, $unit = 'months') {
        if (empty($timestamp)) {
            return true; // Null timestamps are effectively "forever ago"
        }

        $interval = sprintf('-%d %s', $amount, $unit);
        $threshold = strtotime($interval);
        $ts = strtotime($timestamp);

        return $ts < $threshold;
    }

    /**
     * SL-013: Send an inactivity/activity notification to a partner.
     * Uses error_log for MVP (console log per spec).
     * Future: integrate with SL-250 notification system.
     *
     * @param int    $user_id     Partner user ID
     * @param string $event_type  Notification/event type
     * @param array  $context     Additional context data
     */
    private function send_inactivity_notification($user_id, $event_type, $context = array()) {
        $user = get_userdata($user_id);
        $email = $user ? $user->user_email : 'unknown';

        // Log the notification attempt
        error_log(sprintf(
            '[OraBooks SL-013 Partner Inactivity] %s | User: %d (%s) | Context: %s',
            $event_type,
            $user_id,
            $email,
            wp_json_encode($context)
        ));

        // Fire action for SL-250 notification system integration
        do_action('orabooks_send_notification', $event_type, array(
            'user_id' => $user_id,
            'email' => $email,
            'context' => $context,
        ));
    }

    /**
     * SL-013: Get the block_same_email_domain config for a partner org.
     * Checks the organizational config (default OFF).
     *
     * @param int $org_id Organization ID
     * @return bool True if same-email-domain attribution is blocked
     */
    private function get_block_same_email_domain_config($org_id) {
        // Check org config via RBAC's get_org_config if available
        if (class_exists('OraBooks_RBAC')) {
            $config = OraBooks_RBAC::get_instance()->get_org_config($org_id);
            if (!empty($config['block_same_email_domain'])) {
                return (bool) $config['block_same_email_domain'];
            }
        }

        // Fallback: check wp_options
        $config = get_option('orabooks_org_config_' . $org_id, array());
        if (!empty($config['block_same_email_domain'])) {
            return (bool) $config['block_same_email_domain'];
        }

        return false; // Default: OFF
    }

    // ================================================================
    // SL-139: EMAIL MASKING HELPER
    // ================================================================

    /**
     * SL-139: Mask a customer email for privacy.
     * Shows first character + *** + domain (e.g. j***@example.com).
     *
     * @param string $email The full email address
     * @return string Masked email
     */
    public static function mask_email($email) {
        if (empty($email) || !is_email($email)) {
            return $email ?: '';
        }
        $parts = explode('@', $email);
        $name = $parts[0];
        $domain = $parts[1];
        $first_char = mb_substr($name, 0, 1);
        $masked_name = $first_char . str_repeat('*', max(0, strlen($name) - 1));
        return $masked_name . '@' . $domain;
    }

    // ================================================================
    // SL-013 §5.7: Partner Onboarding Page
    // ================================================================

    /**
     * Register rewrite rule for /partner/onboarding/ URL.
     */
    public function register_onboarding_rewrite() {
        add_rewrite_rule(
            '^partner/onboarding/?$',
            'index.php?orabooks_partner_onboarding=1',
            'top'
        );

        // First-run soft flush so the rewrite rule takes effect immediately.
        // Soft flush (false) only recalculates the rewrite_rules option in the
        // database — no .htaccess write, so no performance concern.
        if (!get_option('orabooks_onboarding_rewrite_flushed')) {
            flush_rewrite_rules(false);
            update_option('orabooks_onboarding_rewrite_flushed', true);
        }
    }

    /**
     * Add onboarding query var.
     */
    public function add_onboarding_query_var($query_vars) {
        $query_vars[] = 'orabooks_partner_onboarding';
        return $query_vars;
    }

    /**
     * Handle template redirect for /partner/onboarding/.
     * Serves a standalone page when the URL is accessed.
     */
    public function handle_onboarding_page() {
        if (!get_query_var('orabooks_partner_onboarding')) {
            return;
        }

        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(home_url('/partner/onboarding/')));
            exit;
        }

        $user_id = get_current_user_id();
        $is_partner = get_user_meta($user_id, 'is_partner', true);
        if (!$is_partner) {
            wp_redirect(home_url('/'));
            exit;
        }

        // SL-139: Fire partner_onboarding_viewed audit event + public action hook
        do_action('orabooks_partner_onboarding_viewed', $user_id, current_time('mysql'));
        do_action('orabooks_security_event', 'partner_onboarding_viewed', array(
            'user_id'   => $user_id,
            'timestamp' => current_time('mysql'),
        ));

        // Render the onboarding page
        ?><!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php _e('Partner Onboarding', 'orabooks'); ?> - <?php bloginfo('name'); ?></title>
            <?php wp_head(); ?>
            <style>
                body {
                    font-family: 'Inter', system-ui, -apple-system, sans-serif;
                    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #f0fdf4 100%);
                    min-height: 100vh;
                    margin: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .onboarding-card {
                    background: #ffffff;
                    border-radius: 24px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.04);
                    max-width: 540px;
                    width: 100%;
                    padding: 48px 40px;
                    text-align: center;
                }
                .onboarding-icon {
                    font-size: 56px;
                    margin-bottom: 8px;
                }
                .onboarding-card h1 {
                    font-size: 28px;
                    font-weight: 700;
                    color: #0f172a;
                    margin: 0 0 8px 0;
                }
                .onboarding-card p.subtitle {
                    font-size: 15px;
                    color: #64748b;
                    line-height: 1.6;
                    margin: 0 0 32px 0;
                }
                .partner-code-section {
                    background: #f8fafc;
                    border: 2px dashed #cbd5e1;
                    border-radius: 12px;
                    padding: 24px;
                    margin-bottom: 24px;
                }
                .partner-code-section label {
                    display: block;
                    font-size: 12px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    color: #64748b;
                    margin-bottom: 8px;
                }
                .partner-code-display {
                    font-family: 'SF Mono', 'Fira Code', 'Courier New', monospace;
                    font-size: 24px;
                    font-weight: 700;
                    color: #0f172a;
                    background: #ffffff;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 12px 16px;
                    width: 100%;
                    text-align: center;
                    letter-spacing: 2px;
                    box-sizing: border-box;
                    cursor: text;
                    margin-bottom: 12px;
                }
                .copy-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    background: #0f172a;
                    color: #ffffff;
                    border: none;
                    border-radius: 8px;
                    padding: 10px 24px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.15s ease;
                }
                .copy-btn:hover {
                    background: #1e293b;
                    transform: translateY(-1px);
                }
                .copy-btn:active {
                    transform: translateY(0);
                }
                .copy-btn.copied {
                    background: #059669;
                }
                .info-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 12px 0;
                    border-bottom: 1px solid #f1f5f9;
                    font-size: 14px;
                }
                .info-row:last-child {
                    border-bottom: none;
                }
                .info-label {
                    color: #64748b;
                    font-weight: 500;
                }
                .info-value {
                    color: #0f172a;
                    font-weight: 600;
                }
                .status-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 6px 14px;
                    border-radius: 20px;
                    font-size: 13px;
                    font-weight: 600;
                    margin-top: 20px;
                }
                .status-pending {
                    background: #fef3c7;
                    color: #92400e;
                }
                .status-active {
                    background: #d1fae5;
                    color: #065f46;
                }
                .status-disabled {
                    background: #fee2e2;
                    color: #991b1b;
                }
                .status-inactive {
                    background: #f1f5f9;
                    color: #475569;
                }
                .status-message {
                    font-size: 13px;
                    color: #64748b;
                    margin-top: 6px;
                    line-height: 1.5;
                }
                .continue-btn {
                    display: block;
                    width: 100%;
                    padding: 14px;
                    background: #2563eb;
                    color: #ffffff;
                    border: none;
                    border-radius: 12px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    text-decoration: none;
                    transition: all 0.15s ease;
                    margin-top: 28px;
                    box-sizing: border-box;
                }
                .continue-btn:hover {
                    background: #1d4ed8;
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
                }
                @media (max-width: 480px) {
                    .onboarding-card { padding: 32px 20px; }
                    .partner-code-display { font-size: 18px; padding: 10px 12px; }
                }
            </style>
        </head>
        <body>
            <?php echo do_shortcode('[orabooks_partner_onboarding]'); ?>
            <script>
            var orabooksDash = {
                ajaxUrl: '<?php echo esc_url(admin_url("admin-ajax.php")); ?>',
                commissionNonce: '<?php echo esc_js(wp_create_nonce("orabooks_commission_dashboard")); ?>',
            };
            document.addEventListener('DOMContentLoaded', function() {
                var copyBtn = document.querySelector('.copy-btn');
                if (copyBtn) {
                    copyBtn.addEventListener('click', function() {
                        var codeInput = document.querySelector('.partner-code-display');
                        if (codeInput) {
                            codeInput.select();
                            codeInput.setSelectionRange(0, 99999);
                            navigator.clipboard.writeText(codeInput.value).then(function() {
                                // SL-139: Track code copy
                                var fd = new FormData();
                                fd.append('action', 'orabooks_partner_code_copied');
                                fd.append('nonce', orabooksDash.commissionNonce);
                                fd.append('source', 'onboarding');
                                fetch(orabooksDash.ajaxUrl, { method: 'POST', body: fd });

                                copyBtn.textContent = '\u2705 Copied!';
                                copyBtn.classList.add('copied');
                                setTimeout(function() {
                                    copyBtn.textContent = '\u{1F4CB} Copy Code';
                                    copyBtn.classList.remove('copied');
                                }, 2500);
                            });
                        }
                    });
                }
            });
            </script>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * SL-013 §5.7: Render partner onboarding page (shortcode [orabooks_partner_onboarding]).
     *
     * Shows:
     * - Partner Code (read-only)
     * - Copy Code button
     * - Partner type & organization name
     * - Status message based on code status
     * - Continue to Dashboard button
     */
    public function render_onboarding_page() {
        if (!is_user_logged_in()) {
            return '<div class="onboarding-card"><p>' . __('Please log in to view your partner information.', 'orabooks') . '</p></div>';
        }

        $user_id = get_current_user_id();
        $is_partner = get_user_meta($user_id, 'is_partner', true);
        if (!$is_partner) {
            return '<div class="onboarding-card"><p>' . __('This page is for partners only.', 'orabooks') . '</p></div>';
        }

        // Get partner code info
        $partner_code_obj = $this->get_partner_code($user_id);
        if (!$partner_code_obj) {
            return '<div class="onboarding-card"><p>' . __('Partner code not found. Please contact support.', 'orabooks') . '</p></div>';
        }

        $partner_code = $partner_code_obj->partner_code;
        $partner_type = $partner_code_obj->partner_type;
        $organization_name = $partner_code_obj->organization_name;
        $status = $partner_code_obj->status;

        // Partner type labels
        $type_labels = array(
            'individual'         => __('Individual', 'orabooks'),
            'accountant'         => __('Accountant', 'orabooks'),
            'agency'             => __('Agency', 'orabooks'),
            'reseller'           => __('Reseller', 'orabooks'),
            'strategic_partner'  => __('Strategic Partner', 'orabooks'),
        );
        $type_label = isset($type_labels[$partner_type]) ? $type_labels[$partner_type] : $partner_type;

        // Status message
        $status_classes = array(
            'pending_review' => 'status-pending',
            'active'         => 'status-active',
            'disabled'       => 'status-disabled',
            'expired'        => 'status-disabled',
            'inactive'       => 'status-inactive',
        );
        $status_class = isset($status_classes[$status]) ? $status_classes[$status] : 'status-pending';

        $status_icons = array(
            'pending_review' => '⏳',
            'active'         => '✅',
            'disabled'       => '🚫',
            'expired'        => '🚫',
            'inactive'       => '🚫',
        );
        $status_icon = isset($status_icons[$status]) ? $status_icons[$status] : '⏳';

        $status_labels = array(
            'pending_review' => __('Awaiting Approval', 'orabooks'),
            'active'         => __('Active', 'orabooks'),
            'disabled'       => __('Disabled', 'orabooks'),
            'expired'        => __('Expired', 'orabooks'),
            'inactive'       => __('Inactive', 'orabooks'),
        );
        $status_label = isset($status_labels[$status]) ? $status_labels[$status] : $status;

        $status_messages = array(
            'pending_review' => __('Awaiting admin approval. Your code is not yet active.', 'orabooks'),
            'active'         => __('Your code is active. Share it to earn commissions.', 'orabooks'),
            'disabled'       => __('Your code has been disabled. Contact support.', 'orabooks'),
            'expired'        => __('Your code has expired. Contact support.', 'orabooks'),
            'inactive'       => __('Your partner code is inactive because you have no active customers and have not brought any new customer in the last 12 months. Contact support to reactivate.', 'orabooks'),
        );
        $status_message = isset($status_messages[$status]) ? $status_messages[$status] : '';

        ob_start();
        ?>
        <div class="onboarding-card">
            <div class="onboarding-icon">🤝</div>
            <h1><?php esc_html_e('Welcome, Partner!', 'orabooks'); ?></h1>
            <p class="subtitle"><?php esc_html_e('Your partner account is ready. Share your unique code to earn commissions on qualified customer referrals.', 'orabooks'); ?></p>

            <div class="partner-code-section">
                <label><?php esc_html_e('Your Partner Code', 'orabooks'); ?></label>
                <input type="text" class="partner-code-display" value="<?php echo esc_attr($partner_code); ?>" readonly onclick="this.select();">
                <button type="button" class="copy-btn">📋 <?php esc_html_e('Copy Code', 'orabooks'); ?></button>
            </div>

            <div class="info-row">
                <span class="info-label"><?php esc_html_e('Partner Type', 'orabooks'); ?></span>
                <span class="info-value"><?php echo esc_html($type_label); ?></span>
            </div>

            <?php if (!empty($organization_name)) : ?>
            <div class="info-row">
                <span class="info-label"><?php esc_html_e('Organization', 'orabooks'); ?></span>
                <span class="info-value"><?php echo esc_html($organization_name); ?></span>
            </div>
            <?php endif; ?>

            <div class="info-row">
                <span class="info-label"><?php esc_html_e('Code Status', 'orabooks'); ?></span>
                <span class="info-value">
                    <span class="status-badge <?php echo esc_attr($status_class); ?>">
                        <?php echo esc_html($status_icon . ' ' . $status_label); ?>
                    </span>
                </span>
            </div>

            <?php if (!empty($status_message)) : ?>
            <p class="status-message"><?php echo esc_html($status_message); ?></p>
            <?php endif; ?>

            <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="continue-btn">
                <?php esc_html_e('Continue to Dashboard', 'orabooks'); ?> →
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    // ================================================================
    // SL-139: Partner Dashboard Data Methods
    // ================================================================

    /**
     * SL-139: Get recent customer attributions for a partner.
     *
     * @param int $partner_user_id Partner user ID
     * @param int $limit           Max number of results
     * @return array Array of attribution objects with customer display info
     */
    public function get_recent_attributions($partner_user_id, $limit = 10) {
        global $wpdb;

        $attributions_table = $wpdb->base_prefix . 'orabooks_partner_attributions';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT pa.*, u.display_name, u.user_email
             FROM {$attributions_table} pa
             LEFT JOIN {$wpdb->users} u ON pa.customer_user_id = u.ID
             WHERE pa.partner_user_id = %d
             ORDER BY pa.attribution_date DESC
             LIMIT %d",
            $partner_user_id,
            $limit
        ));

        if ($results) {
            foreach ($results as $row) {
                if (!empty($row->user_email)) {
                    $row->user_email = self::mask_email($row->user_email);
                }
            }
        }

        return $results ? $results : array();
    }

    /**
     * SL-139: Get total count of attributions by status for a partner.
     *
     * @param int $partner_user_id Partner user ID
     * @return array { verified: int, pending: int, blocked: int }
     */
    public function get_attribution_counts($partner_user_id) {
        global $wpdb;

        $attributions_table = $wpdb->base_prefix . 'orabooks_partner_attributions';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as cnt
             FROM {$attributions_table}
             WHERE partner_user_id = %d
             GROUP BY status",
            $partner_user_id
        ));

        $counts = array('verified' => 0, 'pending' => 0, 'blocked' => 0);
        foreach ($results as $row) {
            if (isset($counts[$row->status])) {
                $counts[$row->status] = (int) $row->cnt;
            }
        }

        return $counts;
    }

    // ================================================================
    // SL-139: Partner Dashboard Page – Rewrite, Query Var, Template
    // ================================================================

    /**
     * Register rewrite rule for /partner/dashboard/ URL.
     */
    public function register_dashboard_rewrite() {
        add_rewrite_rule(
            '^partner/dashboard/?$',
            'index.php?orabooks_partner_dashboard=1',
            'top'
        );

        if (!get_option('orabooks_dashboard_rewrite_flushed')) {
            flush_rewrite_rules(false);
            update_option('orabooks_dashboard_rewrite_flushed', true);
        }
    }

    /**
     * Add dashboard query var.
     */
    public function add_dashboard_query_var($query_vars) {
        $query_vars[] = 'orabooks_partner_dashboard';
        return $query_vars;
    }

    /**
     * Handle template redirect for /partner/dashboard/.
     */
    public function handle_dashboard_page() {
        if (!get_query_var('orabooks_partner_dashboard')) {
            return;
        }

        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(home_url('/partner/dashboard/')));
            exit;
        }

        $user_id = get_current_user_id();
        $is_partner = get_user_meta($user_id, 'is_partner', true);
        if (!$is_partner) {
            wp_redirect(home_url('/'));
            exit;
        }

        // SL-139: Fire partner_dashboard_viewed audit event + public action hook
        do_action('orabooks_partner_dashboard_viewed', $user_id, current_time('mysql'));
        do_action('orabooks_security_event', 'partner_dashboard_viewed', array(
            'user_id'   => $user_id,
            'timestamp' => current_time('mysql'),
        ));

        ?><!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php _e('Partner Dashboard', 'orabooks'); ?> - <?php bloginfo('name'); ?></title>
            <?php wp_head(); ?>
            <style>
                :root {
                    --bg-page: #f1f5f9;
                    --bg-card: #ffffff;
                    --text-primary: #0f172a;
                    --text-secondary: #475569;
                    --text-muted: #94a3b8;
                    --border: #e2e8f0;
                    --accent: #2563eb;
                    --accent-hover: #1d4ed8;
                    --success: #059669;
                    --warning: #d97706;
                    --danger: #dc2626;
                    --radius-card: 12px;
                    --radius-sm: 8px;
                    --shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
                    --shadow-lg: 0 10px 30px rgba(0,0,0,0.06), 0 2px 6px rgba(0,0,0,0.04);
                }
                body {
                    font-family: 'Inter', system-ui, -apple-system, sans-serif;
                    background: var(--bg-page);
                    min-height: 100vh;
                    margin: 0;
                    color: var(--text-primary);
                }
                .dash-container {
                    max-width: 960px;
                    margin: 0 auto;
                    padding: 32px 20px 60px;
                }
                .dash-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    margin-bottom: 28px;
                    flex-wrap: wrap;
                    gap: 16px;
                }
                .dash-header h1 {
                    font-size: 26px;
                    font-weight: 700;
                    margin: 0;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .dash-header .nav-links {
                    display: flex;
                    gap: 12px;
                }
                .dash-header .nav-links a {
                    color: var(--text-secondary);
                    text-decoration: none;
                    font-size: 14px;
                    font-weight: 500;
                    padding: 8px 16px;
                    border-radius: var(--radius-sm);
                    transition: all 0.15s ease;
                }
                .dash-header .nav-links a:hover {
                    background: #e2e8f0;
                    color: var(--text-primary);
                }

                /* ── Status Banner ── */
                .status-banner {
                    border-radius: var(--radius-card);
                    padding: 20px 24px;
                    margin-bottom: 24px;
                    display: flex;
                    align-items: flex-start;
                    gap: 14px;
                    box-shadow: var(--shadow);
                }
                .status-banner .icon {
                    font-size: 24px;
                    flex-shrink: 0;
                    margin-top: 2px;
                }
                .status-banner .content {
                    flex: 1;
                }
                .status-banner .title {
                    font-weight: 600;
                    font-size: 16px;
                    margin-bottom: 4px;
                }
                .status-banner .message {
                    font-size: 14px;
                    line-height: 1.5;
                    margin: 0;
                }
                .status-banner .action-btn {
                    display: inline-block;
                    margin-top: 10px;
                    padding: 8px 20px;
                    border-radius: var(--radius-sm);
                    font-size: 14px;
                    font-weight: 600;
                    text-decoration: none;
                    transition: all 0.15s ease;
                    border: none;
                    cursor: pointer;
                }
                .status-banner.active   { background: #ecfdf5; border-left: 4px solid var(--success); }
                .status-banner.pending  { background: #fffbeb; border-left: 4px solid var(--warning); }
                .status-banner.inactive { background: #fef2f2; border-left: 4px solid var(--danger); }
                .status-banner.disabled { background: #f1f5f9; border-left: 4px solid var(--text-muted); }
                .status-banner.active .action-btn   { background: var(--success); color: #fff; }
                .status-banner.active .action-btn:hover { background: #047857; }
                .status-banner.pending .action-btn  { background: var(--warning); color: #fff; }
                .status-banner.pending .action-btn:hover { background: #b45309; }
                .status-banner.inactive .action-btn { background: var(--danger); color: #fff; }
                .status-banner.inactive .action-btn:hover { background: #b91c1c; }

                /* ── Card Grid ── */
                .card-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                    gap: 16px;
                    margin-bottom: 24px;
                }
                .stat-card {
                    background: var(--bg-card);
                    border-radius: var(--radius-card);
                    padding: 24px;
                    box-shadow: var(--shadow);
                }
                .stat-card .stat-value {
                    font-size: 32px;
                    font-weight: 700;
                    color: var(--text-primary);
                    line-height: 1.2;
                }
                .stat-card .stat-label {
                    font-size: 13px;
                    color: var(--text-muted);
                    font-weight: 500;
                    text-transform: uppercase;
                    letter-spacing: 0.04em;
                    margin-top: 4px;
                }
                .stat-card .stat-icon {
                    font-size: 28px;
                    margin-bottom: 8px;
                }

                /* ── Partner Code Card ── */
                .code-card {
                    background: var(--bg-card);
                    border-radius: var(--radius-card);
                    padding: 24px;
                    box-shadow: var(--shadow);
                    margin-bottom: 24px;
                }
                .code-card .code-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    margin-bottom: 16px;
                    flex-wrap: wrap;
                    gap: 12px;
                }
                .code-card .code-header h2 {
                    font-size: 16px;
                    font-weight: 600;
                    margin: 0;
                }
                .code-card .code-display-area {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    flex-wrap: wrap;
                }
                .code-card .code-display {
                    font-family: 'SF Mono', 'Fira Code', 'Courier New', monospace;
                    font-size: 20px;
                    font-weight: 700;
                    color: var(--text-primary);
                    background: #f8fafc;
                    border: 1px solid var(--border);
                    border-radius: var(--radius-sm);
                    padding: 10px 16px;
                    letter-spacing: 2px;
                    min-width: 200px;
                    text-align: center;
                }
                .code-card .copy-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    background: var(--accent);
                    color: #fff;
                    border: none;
                    border-radius: var(--radius-sm);
                    padding: 10px 20px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.15s ease;
                }
                .code-card .copy-btn:hover {
                    background: var(--accent-hover);
                    transform: translateY(-1px);
                }
                .code-card .copy-btn.copied {
                    background: var(--success);
                }
                .code-card .code-meta {
                    display: flex;
                    gap: 24px;
                    margin-top: 14px;
                    flex-wrap: wrap;
                    font-size: 14px;
                }
                .code-card .code-meta span {
                    color: var(--text-secondary);
                }
                .code-card .code-meta strong {
                    color: var(--text-primary);
                    font-weight: 600;
                }

                /* ── Section ── */
                .section-card {
                    background: var(--bg-card);
                    border-radius: var(--radius-card);
                    box-shadow: var(--shadow);
                    margin-bottom: 24px;
                    overflow: hidden;
                }
                .section-card .section-header {
                    padding: 20px 24px;
                    border-bottom: 1px solid var(--border);
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                }
                .section-card .section-header h2 {
                    font-size: 16px;
                    font-weight: 600;
                    margin: 0;
                }
                .section-card .section-body {
                    padding: 20px 24px;
                }

                /* ── Attributions Table ── */
                .attributions-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .attributions-table th {
                    text-align: left;
                    font-size: 12px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.04em;
                    color: var(--text-muted);
                    padding: 8px 12px;
                    border-bottom: 1px solid var(--border);
                }
                .attributions-table td {
                    padding: 10px 12px;
                    font-size: 14px;
                    border-bottom: 1px solid #f1f5f9;
                    color: var(--text-secondary);
                }
                .attributions-table tr:last-child td {
                    border-bottom: none;
                }
                .attributions-table .status-badge-sm {
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                    padding: 2px 10px;
                    border-radius: 12px;
                    font-size: 12px;
                    font-weight: 600;
                }
                .attributions-table .status-badge-sm.verified { background: #d1fae5; color: #065f46; }
                .attributions-table .status-badge-sm.pending  { background: #fef3c7; color: #92400e; }
                .attributions-table .status-badge-sm.blocked  { background: #fee2e2; color: #991b1b; }

                /* ── Reactivation Modal ── */
                .modal-overlay {
                    display: none;
                    position: fixed;
                    inset: 0;
                    background: rgba(0,0,0,0.5);
                    align-items: center;
                    justify-content: center;
                    z-index: 9999;
                    padding: 20px;
                }
                .modal-overlay.open { display: flex; }
                .modal-box {
                    background: var(--bg-card);
                    border-radius: var(--radius-card);
                    max-width: 480px;
                    width: 100%;
                    padding: 32px;
                    box-shadow: var(--shadow-lg);
                }
                .modal-box h3 {
                    font-size: 18px;
                    font-weight: 700;
                    margin: 0 0 8px;
                }
                .modal-box p {
                    font-size: 14px;
                    color: var(--text-secondary);
                    margin: 0 0 20px;
                    line-height: 1.5;
                }
                .modal-box textarea {
                    width: 100%;
                    min-height: 100px;
                    padding: 12px;
                    border: 1px solid var(--border);
                    border-radius: var(--radius-sm);
                    font-size: 14px;
                    font-family: inherit;
                    resize: vertical;
                    box-sizing: border-box;
                }
                .modal-box textarea:focus {
                    outline: none;
                    border-color: var(--accent);
                    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
                }
                .modal-actions {
                    display: flex;
                    gap: 12px;
                    margin-top: 20px;
                    justify-content: flex-end;
                }
                .modal-actions button {
                    padding: 10px 24px;
                    border-radius: var(--radius-sm);
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.15s ease;
                    border: none;
                }
                .modal-actions .btn-primary { background: var(--accent); color: #fff; }
                .modal-actions .btn-primary:hover { background: var(--accent-hover); }
                .modal-actions .btn-secondary { background: #f1f5f9; color: var(--text-secondary); }
                .modal-actions .btn-secondary:hover { background: #e2e8f0; }

                .reactivation-link {
                    background: none;
                    border: none;
                    color: var(--accent);
                    font-weight: 600;
                    cursor: pointer;
                    padding: 0;
                    font-size: inherit;
                    text-decoration: underline;
                    text-underline-offset: 2px;
                }
                .reactivation-link:hover { color: var(--accent-hover); }

                .empty-state {
                    text-align: center;
                    padding: 40px 20px;
                    color: var(--text-muted);
                }
                .empty-state .icon {
                    font-size: 40px;
                    margin-bottom: 12px;
                }
                .empty-state p {
                    margin: 0;
                    font-size: 15px;
                }

                .toast {
                    position: fixed;
                    bottom: 24px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: var(--text-primary);
                    color: #fff;
                    padding: 12px 24px;
                    border-radius: var(--radius-sm);
                    font-size: 14px;
                    font-weight: 500;
                    box-shadow: var(--shadow-lg);
                    opacity: 0;
                    transition: opacity 0.3s ease;
                    z-index: 99999;
                    pointer-events: none;
                }
                .toast.show { opacity: 1; }
                .toast.success { background: var(--success); }
                .toast.error { background: var(--danger); }

                @media (max-width: 640px) {
                    .dash-container { padding: 20px 16px; }
                    .stat-card .stat-value { font-size: 26px; }
                    .code-card .code-display { font-size: 16px; min-width: 140px; }
                }
            </style>
        </head>
        <body>
            <?php echo do_shortcode('[orabooks_partner_dashboard]'); ?>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * SL-139: Render partner dashboard (shortcode [orabooks_partner_dashboard]).
     *
     * Shows:
     * - Status banner with contextual message + action
     * - Partner code display with copy button
     * - Stats cards (active customers, total attributions, pending)
     * - Recent customer attributions table
     * - Inactive/reactivation section with modal
     * - Commission summary placeholder
     */
    public function render_dashboard_page() {
        if (!is_user_logged_in()) {
            return '<div class="dash-container"><p>' . __('Please log in to view your partner dashboard.', 'orabooks') . '</p></div>';
        }

        $user_id = get_current_user_id();
        $is_partner = get_user_meta($user_id, 'is_partner', true);
        if (!$is_partner) {
            return '<div class="dash-container"><p>' . __('This page is for partners only.', 'orabooks') . '</p></div>';
        }

        // Get partner data
        $partner_code_obj = $this->get_partner_code($user_id);
        if (!$partner_code_obj) {
            return '<div class="dash-container"><p>' . __('Partner information not found. Please contact support.', 'orabooks') . '</p></div>';
        }

        $active_code = $this->get_active_partner_code($user_id);
        $partner_code = $partner_code_obj->partner_code;
        $partner_type = $partner_code_obj->partner_type;
        $organization_name = $partner_code_obj->organization_name;
        $status = $partner_code_obj->status;

        // Stats
        $active_customers = $this->get_active_customer_count($user_id);
        $attribution_counts = $this->get_attribution_counts($user_id);
        $recent_attributions = $this->get_recent_attributions($user_id, 10);

        // Partner type labels
        $type_labels = array(
            'individual'         => __('Individual', 'orabooks'),
            'accountant'         => __('Accountant', 'orabooks'),
            'agency'             => __('Agency', 'orabooks'),
            'reseller'           => __('Reseller', 'orabooks'),
            'strategic_partner'  => __('Strategic Partner', 'orabooks'),
        );
        $type_label = isset($type_labels[$partner_type]) ? $type_labels[$partner_type] : $partner_type;

        // Status configuration
        $status_config = array(
            'pending_review' => array(
                'class'    => 'pending',
                'icon'     => '⏳',
                'title'    => __('Pending Approval', 'orabooks'),
                'message'  => __('Your partner application is being reviewed. Your code is not yet active — we will notify you once approved.', 'orabooks'),
            ),
            'active' => array(
                'class'    => 'active',
                'icon'     => '✅',
                'title'    => __('Active Partner', 'orabooks'),
                'message'  => __('Your partner code is active. Share it with customers to earn commissions on qualified referrals.', 'orabooks'),
            ),
            'disabled' => array(
                'class'    => 'disabled',
                'icon'     => '🚫',
                'title'    => __('Code Disabled', 'orabooks'),
                'message'  => __('Your partner code has been disabled. Please contact support for assistance.', 'orabooks'),
            ),
            'expired' => array(
                'class'    => 'disabled',
                'icon'     => '⌛',
                'title'    => __('Code Expired', 'orabooks'),
                'message'  => __('Your partner code has expired. Please contact support for assistance.', 'orabooks'),
            ),
            'inactive' => array(
                'class'    => 'inactive',
                'icon'     => '🔴',
                'title'    => __('Inactive Partner', 'orabooks'),
                'message'  => __('Your partner code is inactive because you have no active customers and have not brought any new customer in the last 12 months. Click below to request reactivation.', 'orabooks'),
            ),
        );

        $cfg = isset($status_config[$status]) ? $status_config[$status] : $status_config['pending_review'];

        ob_start();
        ?>
        <div class="dash-container">
            <!-- Header -->
            <div class="dash-header">
                <h1><?php esc_html_e('Partner Dashboard', 'orabooks'); ?></h1>
                <div class="nav-links">
                    <a href="<?php echo esc_url(home_url('/partner/onboarding/')); ?>"><?php esc_html_e('Onboarding', 'orabooks'); ?></a>
                    <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'orabooks'); ?></a>
                </div>
            </div>

            <!-- Status Banner -->
            <div class="status-banner <?php echo esc_attr($cfg['class']); ?>">
                <span class="icon"><?php echo esc_html($cfg['icon']); ?></span>
                <div class="content">
                    <div class="title"><?php echo esc_html($cfg['title']); ?></div>
                    <p class="message"><?php echo esc_html($cfg['message']); ?></p>
                    <?php if ($status === 'inactive') : ?>
                        <button type="button" class="action-btn" id="open-reactivation-btn">
                            <?php esc_html_e('Request Reactivation', 'orabooks'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Partner Code Card -->
            <div class="code-card">
                <div class="code-header">
                    <h2><?php esc_html_e('Your Partner Code', 'orabooks'); ?></h2>
                    <span style="font-size:13px;color:var(--text-muted);">
                        <?php esc_html_e('Share this code with new customers', 'orabooks'); ?>
                    </span>
                </div>
                <div class="code-display-area">
                    <span class="code-display"><?php echo esc_html($partner_code); ?></span>
                    <button type="button" class="copy-btn copy-btn-dash">📋 <?php esc_html_e('Copy Code', 'orabooks'); ?></button>
                </div>
                <div class="code-meta">
                    <span><?php esc_html_e('Type:', 'orabooks'); ?> <strong><?php echo esc_html($type_label); ?></strong></span>
                    <?php if (!empty($organization_name)) : ?>
                        <span><?php esc_html_e('Organization:', 'orabooks'); ?> <strong><?php echo esc_html($organization_name); ?></strong></span>
                    <?php endif; ?>
                    <?php if ($active_code && !empty($active_code->approved_at)) : ?>
                        <span><?php esc_html_e('Approved:', 'orabooks'); ?> <strong><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($active_code->approved_at))); ?></strong></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="card-grid">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-value"><?php echo esc_html($active_customers); ?></div>
                    <div class="stat-label"><?php esc_html_e('Active Customers', 'orabooks'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value"><?php echo esc_html($attribution_counts['verified']); ?></div>
                    <div class="stat-label"><?php esc_html_e('Verified Attributions', 'orabooks'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-value"><?php echo esc_html($attribution_counts['pending']); ?></div>
                    <div class="stat-label"><?php esc_html_e('Pending Attributions', 'orabooks'); ?></div>
                </div>
                <div class="stat-card" id="commission-earned-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-value" id="commission-earned-value"><?php esc_html_e('Loading...', 'orabooks'); ?></div>
                    <div class="stat-label"><?php esc_html_e('Commission Earned (Net)', 'orabooks'); ?></div>
                </div>
            </div>

            <!-- Recent Attributions -->
            <div class="section-card">
                <div class="section-header">
                    <h2><?php esc_html_e('Recent Customer Attributions', 'orabooks'); ?></h2>
                    <span style="font-size:13px;color:var(--text-muted);">
                        <?php echo esc_html(sprintf(__('Showing last %d', 'orabooks'), count($recent_attributions))); ?>
                    </span>
                </div>
                <div class="section-body" style="padding:0;">
                    <?php if (empty($recent_attributions)) : ?>
                        <div class="empty-state">
                            <div class="icon">📋</div>
                            <p><?php esc_html_e('No customer attributions yet. Share your partner code to start earning commissions.', 'orabooks'); ?></p>
                        </div>
                    <?php else : ?>
                        <table class="attributions-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Customer', 'orabooks'); ?></th>
                                    <th><?php esc_html_e('Email', 'orabooks'); ?></th>
                                    <th><?php esc_html_e('Date', 'orabooks'); ?></th>
                                    <th><?php esc_html_e('Status', 'orabooks'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_attributions as $att) : ?>
                                    <tr>
                                        <td>
                                            <?php echo esc_html($att->display_name ? $att->display_name : __('(unknown)', 'orabooks')); ?>
                                        </td>
                                        <td><?php echo esc_html($att->customer_email); ?></td>
                                        <td>
                                            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($att->attribution_date))); ?>
                                        </td>
                                        <td>
                                            <span class="status-badge-sm <?php echo esc_attr($att->status); ?>">
                                                <?php
                                                $status_labels = array(
                                                    'verified' => __('Verified', 'orabooks'),
                                                    'pending'  => __('Pending', 'orabooks'),
                                                    'blocked'  => __('Blocked', 'orabooks'),
                                                );
                                                echo esc_html(isset($status_labels[$att->status]) ? $status_labels[$att->status] : $att->status);
                                                ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Commission Summary (SL-068) -->
            <div class="section-card" id="commission-summary-section">
                <div class="section-header">
                    <h2><?php esc_html_e('Commission Summary', 'orabooks'); ?></h2>
                    <span style="font-size:13px;color:var(--text-muted);"><?php esc_html_e('SL-068 Active', 'orabooks'); ?></span>
                </div>
                <div class="section-body" id="commission-summary-body">
                    <div class="empty-state" id="commission-loading">
                        <div class="icon">⏳</div>
                        <p><?php esc_html_e('Loading commission data...', 'orabooks'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Payout Summary (SL-068) -->
            <div class="section-card" id="payout-summary-section">
                <div class="section-header">
                    <h2><?php esc_html_e('Payout Summary', 'orabooks'); ?></h2>
                    <span style="font-size:13px;color:var(--text-muted);" id="payout-status"><?php esc_html_e('Checking...', 'orabooks'); ?></span>
                </div>
                <div class="section-body" id="payout-summary-body">
                    <div class="empty-state" id="payout-loading">
                        <div class="icon">⏳</div>
                        <p><?php esc_html_e('Loading payout data...', 'orabooks'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Reactivation Modal -->
            <div class="modal-overlay" id="reactivation-modal">
                <div class="modal-box">
                    <h3><?php esc_html_e('Request Partner Reactivation', 'orabooks'); ?></h3>
                    <p><?php esc_html_e('Please explain why you would like your partner account to be reactivated. Our team will review your request.', 'orabooks'); ?></p>
                    <textarea id="reactivation-reason" placeholder="<?php esc_attr_e('Explain why you want to reactivate your partner account...', 'orabooks'); ?>"></textarea>
                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" id="close-reactivation-btn"><?php esc_html_e('Cancel', 'orabooks'); ?></button>
                        <button type="button" class="btn-primary" id="confirm-reactivation-btn"><?php esc_html_e('Submit Request', 'orabooks'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var orabooksDash = {
            ajaxUrl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            nonce: '<?php echo esc_js(wp_create_nonce('orabooks_partner_reactivation')); ?>',
            commissionNonce: '<?php echo esc_js(wp_create_nonce('orabooks_commission_dashboard')); ?>',
        };

        document.addEventListener('DOMContentLoaded', function() {
            // ── Toast helper ──
            function showToast(msg, type) {
                var toast = document.getElementById('dash-toast');
                if (!toast) return;
                toast.textContent = msg;
                toast.className = 'toast ' + (type || '') + ' show';
                setTimeout(function() { toast.classList.remove('show'); }, 4000);
            }
            // ── Copy Code ──
            var copyBtn = document.querySelector('.copy-btn-dash');
            if (copyBtn) {
                copyBtn.addEventListener('click', function() {
                    var codeDisplay = document.querySelector('.code-card .code-display');
                    if (codeDisplay) {
                        var code = codeDisplay.textContent || codeDisplay.innerText;
                        navigator.clipboard.writeText(code.trim()).then(function() {
                            // SL-139: Track code copy
                            var fd = new FormData();
                            fd.append('action', 'orabooks_partner_code_copied');
                            fd.append('nonce', orabooksDash.commissionNonce);
                            fd.append('source', 'dashboard');
                            fetch(orabooksDash.ajaxUrl, { method: 'POST', body: fd });

                            copyBtn.textContent = '\u2705 Copied!';
                            copyBtn.classList.add('copied');
                            setTimeout(function() {
                                copyBtn.textContent = '\u{1F4CB} Copy Code';
                                copyBtn.classList.remove('copied');
                            }, 2500);
                        });
                    }
                });
            }

            // ── Reactivation Modal ──
            var modal = document.getElementById('reactivation-modal');
            var openBtn = document.getElementById('open-reactivation-btn');
            var closeBtn = document.getElementById('close-reactivation-btn');
            var confirmBtn = document.getElementById('confirm-reactivation-btn');

            if (openBtn && modal) {
                openBtn.addEventListener('click', function() { modal.classList.add('open'); });
            }
            if (closeBtn && modal) {
                closeBtn.addEventListener('click', function() { modal.classList.remove('open'); });
            }
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) { modal.classList.remove('open'); }
                });
            }

            // ── Submit Reactivation ──
            if (confirmBtn) {
                confirmBtn.addEventListener('click', function() {
                    var reason = document.getElementById('reactivation-reason').value.trim();
                    if (!reason) {
                        showToast('Please provide a reason for reactivation.', 'error');
                        return;
                    }
                    confirmBtn.disabled = true;
                    confirmBtn.textContent = 'Submitting...';

                    var formData = new FormData();
                    formData.append('action', 'orabooks_partner_reactivation_request');
                    formData.append('nonce', orabooksDash.nonce);
                    formData.append('reason', reason);

                    fetch(orabooksDash.ajaxUrl, { method: 'POST', body: formData })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.success) {
                                showToast(data.data.message, 'success');
                                modal.classList.remove('open');
                                document.getElementById('reactivation-reason').value = '';
                                setTimeout(function() { location.reload(); }, 2000);
                            } else {
                                showToast(data.data.message || 'Request failed.', 'error');
                                confirmBtn.disabled = false;
                                confirmBtn.textContent = 'Submit Request';
                            }
                        })
                        .catch(function() {
                            showToast('Network error. Please try again.', 'error');
                            confirmBtn.disabled = false;
                            confirmBtn.textContent = 'Submit Request';
                        });
                });
            }

            // ════════════════════════════════════════════════════════════════
            // SL-068: Load Commission Summary
            // ════════════════════════════════════════════════════════════════
            function loadCommissionSummary() {
                var formData = new FormData();
                formData.append('action', 'orabooks_commission_summary');
                formData.append('nonce', orabooksDash.commissionNonce);

                fetch(orabooksDash.ajaxUrl, { method: 'POST', body: formData })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.success) {
                            document.getElementById('commission-summary-body').innerHTML =
                                '<div class="empty-state"><div class="icon">📊</div><p>' + (data.data.message || 'Unable to load commissions.') + '</p></div>';
                            return;
                        }

                        var s = data.data.summary;
                        var rate = data.data.rate;
                        var payout = data.data.payout;
                        var config = data.data.config;

                        // Update commission earned stat card
                        var earnedEl = document.getElementById('commission-earned-value');
                        if (earnedEl) {
                            var totalNet = s.total_qualified_net || 0;
                            var totalPaid = s.total_paid || 0;
                            earnedEl.textContent = '$' + (totalNet + totalPaid).toFixed(2);
                        }

                        // Build commission summary HTML
                        var html = '';
                        html += '<div class="card-grid">';
                        html += '  <div class="stat-card">';
                        html += '    <div class="stat-icon">⏳</div>';
                        html += '    <div class="stat-value">' + s.count_pending + '</div>';
                        html += '    <div class="stat-label">Pending</div>';
                        html += '  </div>';
                        html += '  <div class="stat-card">';
                        html += '    <div class="stat-icon">✅</div>';
                        html += '    <div class="stat-value">$' + (s.total_qualified_net || 0).toFixed(2) + '</div>';
                        html += '    <div class="stat-label">Qualified (Net)</div>';
                        html += '  </div>';
                        html += '  <div class="stat-card">';
                        html += '    <div class="stat-icon">💰</div>';
                        html += '    <div class="stat-value">$' + (s.total_paid || 0).toFixed(2) + '</div>';
                        html += '    <div class="stat-label">Paid</div>';
                        html += '  </div>';
                        html += '</div>';

                        html += '<div style="font-size:13px; color:var(--text-muted); padding:0 24px 16px;">';
                        html += 'Commission rate: <strong>' + rate.toFixed(1) + '%</strong> | ';
                        html += 'Payout fee: <strong>' + (config.payout_fee_rate || 2.5).toFixed(1) + '%</strong> | ';
                        html += 'Min payout: <strong>$' + (config.min_payout_threshold || 25).toFixed(2) + '</strong>';
                        html += '</div>';

                        // Add recent commission history mini-table
                        html += '<div style="padding:0 24px 20px;">';
                        html += '<h3 style="font-size:14px; font-weight:600; margin:0 0 10px;">Recent Commissions</h3>';
                        html += '<table class="attributions-table">';
                        html += '<thead><tr><th>Customer</th><th>Gross</th><th>Fee</th><th>Net</th><th>Status</th><th>Date</th></tr></thead>';
                        html += '<tbody id="commission-history-tbody">';
                        html += '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Loading history...</td></tr>';
                        html += '</tbody></table>';
                        html += '</div>';

                        document.getElementById('commission-summary-body').innerHTML = html;

                        // Now load commission history
                        loadCommissionHistory();

                        // Load payout summary
                        loadPayoutSummary(payout, config);
                    })
                    .catch(function() {
                        document.getElementById('commission-summary-body').innerHTML =
                            '<div class="empty-state"><div class="icon">⚠️</div><p>Network error loading commissions.</p></div>';
                    });
            }

            function loadCommissionHistory() {
                var formData = new FormData();
                formData.append('action', 'orabooks_commission_history');
                formData.append('nonce', orabooksDash.commissionNonce);
                formData.append('limit', '10');

                fetch(orabooksDash.ajaxUrl, { method: 'POST', body: formData })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var tbody = document.getElementById('commission-history-tbody');
                        if (!tbody) return;

                        if (!data.success || !data.data.commissions || data.data.commissions.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">No commission history yet.</td></tr>';
                            return;
                        }

                        var html = '';
                        data.data.commissions.forEach(function(c) {
                            html += '<tr>';
                            html += '<td>' + escHtml(c.customer_name) + '</td>';
                            html += '<td>' + (c.commission_amount != null ? '$' + c.commission_amount.toFixed(2) : '-') + '</td>';
                            html += '<td>' + (c.fee_amount != null ? '$' + c.fee_amount.toFixed(2) : '-') + '</td>';
                            html += '<td>' + (c.net_amount != null ? '$' + c.net_amount.toFixed(2) : '-') + '</td>';
                            html += '<td><span class="status-badge-sm ' + c.status + '">' + escHtml(c.status_label) + '</span></td>';
                            html += '<td>' + (c.created_at ? c.created_at.substring(0, 10) : '-') + '</td>';
                            html += '</tr>';
                        });
                        tbody.innerHTML = html;
                    })
                    .catch(function() {
                        var tbody = document.getElementById('commission-history-tbody');
                        if (tbody) {
                            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Error loading history.</td></tr>';
                        }
                    });
            }

            function loadPayoutSummary(payout, config) {
                var statusEl = document.getElementById('payout-status');
                var bodyEl = document.getElementById('payout-summary-body');

                if (!bodyEl) return;

                var html = '';

                if (payout.count === 0) {
                    html += '<div class="empty-state">';
                    html += '  <div class="icon">✅</div>';
                    html += '  <p>No pending payouts. Commission earnings will appear here once customers qualify.</p>';
                    html += '</div>';
                    if (statusEl) statusEl.textContent = 'Ready';
                } else {
                    var meetsThreshold = payout.meets_threshold;
                    html += '<div class="card-grid">';
                    html += '  <div class="stat-card">';
                    html += '    <div class="stat-icon">💰</div>';
                    html += '    <div class="stat-value">$' + payout.total_gross.toFixed(2) + '</div>';
                    html += '    <div class="stat-label">Gross Commission</div>';
                    html += '  </div>';
                    html += '  <div class="stat-card">';
                    html += '    <div class="stat-icon">💸</div>';
                    html += '    <div class="stat-value">$' + payout.total_fee.toFixed(2) + '</div>';
                    html += '    <div class="stat-label">Gateway Fee</div>';
                    html += '  </div>';
                    html += '  <div class="stat-card">';
                    html += '    <div class="stat-icon">🏦</div>';
                    html += '    <div class="stat-value">$' + payout.total_net.toFixed(2) + '</div>';
                    html += '    <div class="stat-label">Net Payout</div>';
                    html += '  </div>';
                    html += '</div>';

                    if (meetsThreshold) {
                        html += '<div style="padding:0 24px 20px;display:flex;align-items:center;gap:8px;">';
                        html += '<span style="color:var(--success);font-size:20px;">✅</span>';
                        html += '<span style="font-size:14px;color:var(--text-secondary);">Meets minimum payout threshold of $' + (payout.min_threshold || 25).toFixed(2) + '.</span>';
                        html += '</div>';
                        if (statusEl) statusEl.textContent = payout.count + ' items ready';
                    } else {
                        html += '<div style="padding:0 24px 20px;display:flex;align-items:center;gap:8px;">';
                        html += '<span style="color:var(--warning);font-size:20px;">⚠️</span>';
                        html += '<span style="font-size:14px;color:var(--text-secondary);">Below minimum payout threshold of $' + (payout.min_threshold || 25).toFixed(2) + '. Accumulate more commissions to request payout.</span>';
                        html += '</div>';
                        if (statusEl) statusEl.textContent = 'Below threshold';
                    }
                }

                bodyEl.innerHTML = html;
            }

            function escHtml(str) {
                if (!str) return '-';
                var div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            }

            // ── Load commission data from SL-068 ──
            loadCommissionSummary();

        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * SL-139: AJAX handler for partner reactivation request.
     * POST action: orabooks_partner_reactivation_request
     * Requires: nonce, reason
     */
    public function ajax_reactivation_request() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'orabooks_partner_reactivation')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'orabooks')));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'orabooks')));
        }

        $user_id = get_current_user_id();
        $is_partner = get_user_meta($user_id, 'is_partner', true);
        if (!$is_partner) {
            wp_send_json_error(array('message' => __('Only partners can request reactivation.', 'orabooks')));
        }

        $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';
        if (empty($reason)) {
            wp_send_json_error(array('message' => __('Please provide a reason for reactivation.', 'orabooks')));
        }

        // Get org_id from partner code
        $partner_code = $this->get_partner_code($user_id);
        if (!$partner_code || empty($partner_code->org_id)) {
            wp_send_json_error(array('message' => __('Partner organization not found.', 'orabooks')));
        }

        // Submit reactivation request via OraBooks_Organizations
        if (class_exists('OraBooks_Organizations')) {
            $orgs = OraBooks_Organizations::get_instance();
            $result = $orgs->request_partner_reactivation($partner_code->org_id, $user_id, $reason);

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }

            wp_send_json_success(array(
                'message' => __('Reactivation request submitted. Our team will review it shortly.', 'orabooks'),
            ));
        } else {
            wp_send_json_error(array('message' => __('Organizations system not available.', 'orabooks')));
        }
    }

    /**
     * SL-139: AJAX handler for partner code copy tracking.
     * POST action: orabooks_partner_code_copied
     * Fires partner_code_copied audit event.
     */
    public function ajax_code_copied() {
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

        $source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : 'unknown';

        do_action('orabooks_security_event', 'partner_code_copied', array(
            'user_id'   => $user_id,
            'source'    => $source,
            'timestamp' => current_time('mysql'),
        ));

        wp_send_json_success(array('message' => __('Code copy tracked.', 'orabooks')));
    }
}

    // ================================================================
    // SL-139: REST API ENDPOINTS
    // ================================================================

    /**
     * SL-139: Register REST API routes for partner onboarding and dashboard.
     * Routes:
     * - GET /orabooks/v1/partner/onboarding
     * - GET /orabooks/v1/partner/dashboard
     */
    public function register_rest_routes() {
        register_rest_route('orabooks/v1', '/partner/onboarding', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'rest_get_onboarding'),
            'permission_callback' => array($this, 'rest_check_partner_auth'),
        ));

        register_rest_route('orabooks/v1', '/partner/dashboard', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'rest_get_dashboard'),
            'permission_callback' => array($this, 'rest_check_partner_auth'),
        ));
    }

    /**
     * SL-139: REST permission callback — must be logged in and have is_partner meta.
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error
     */
    public function rest_check_partner_auth($request) {
        if (!is_user_logged_in()) {
            return new WP_Error('rest_not_logged_in', __('You must be logged in.', 'orabooks'), array('status' => 401));
        }

        $user_id = get_current_user_id();
        $is_partner = get_user_meta($user_id, 'is_partner', true);
        if (!$is_partner) {
            return new WP_Error('rest_not_partner', __('Partners only.', 'orabooks'), array('status' => 403));
        }

        return true;
    }

    /**
     * SL-139: GET /orabooks/v1/partner/onboarding
     *
     * Returns partner onboarding data:
     * - partner_code: The partner's unique code
     * - partner_type: Type label (Individual, Agency, etc.)
     * - organization_name: Organization name (if applicable)
     * - status: Code status (pending_review, active, disabled, etc.)
     * - status_label: Human-readable status label
     * - access: Dashboard access flags (for org status checks)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function rest_get_onboarding($request) {
        $user_id = get_current_user_id();

        // Fire audit event
        do_action('orabooks_partner_onboarding_viewed', $user_id, current_time('mysql'), 'rest_api');
        do_action('orabooks_security_event', 'partner_onboarding_viewed', array(
            'user_id'   => $user_id,
            'timestamp' => current_time('mysql'),
            'source'    => 'rest_api',
        ));

        // Get partner code info
        $partner_code_obj = $this->get_partner_code($user_id);
        if (!$partner_code_obj) {
            return new WP_Error('code_not_found', __('Partner code not found. Contact support.', 'orabooks'), array('status' => 404));
        }

        // Partner type labels
        $type_labels = array(
            'individual'         => __('Individual', 'orabooks'),
            'accountant'         => __('Accountant', 'orabooks'),
            'agency'             => __('Agency', 'orabooks'),
            'reseller'           => __('Reseller', 'orabooks'),
            'strategic_partner'  => __('Strategic Partner', 'orabooks'),
        );

        $status_labels = array(
            'pending_review' => __('Awaiting Approval', 'orabooks'),
            'active'         => __('Active', 'orabooks'),
            'disabled'       => __('Disabled', 'orabooks'),
            'expired'        => __('Expired', 'orabooks'),
            'inactive'       => __('Inactive', 'orabooks'),
        );

        $access = array();
        if (class_exists('OraBooks_Commissions')) {
            $comm = OraBooks_Commissions::get_instance();
            if (method_exists($comm, 'get_partner_dashboard_access')) {
                $access = $comm->get_partner_dashboard_access($user_id);
            }
        }

        $data = array(
            'partner_code'      => $partner_code_obj->partner_code,
            'partner_type'      => $partner_code_obj->partner_type,
            'partner_type_label' => isset($type_labels[$partner_code_obj->partner_type]) ? $type_labels[$partner_code_obj->partner_type] : $partner_code_obj->partner_type,
            'organization_name' => $partner_code_obj->organization_name,
            'status'            => $partner_code_obj->status,
            'status_label'      => isset($status_labels[$partner_code_obj->status]) ? $status_labels[$partner_code_obj->status] : $partner_code_obj->status,
            'created_at'        => $partner_code_obj->created_at,
            'access'            => $access,
        );

        return new WP_REST_Response(array('success' => true, 'data' => $data), 200);
    }

    /**
     * SL-139: GET /orabooks/v1/partner/dashboard
     *
     * Returns partner dashboard data including:
     * - summary: Commission summary (pending/qualified/paid counts + totals)
     * - payout: Payout summary (items, totals, threshold check)
     * - recent_commissions: Recent commission history
     * - attributions: Recent customer attributions with masked emails
     * - attribution_counts: Counts by status (verified/pending/blocked)
     * - active_customers: Active customer count
     * - partner_code: Partner code info
     * - config: Platform config values
     * - access: Dashboard access flags
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function rest_get_dashboard($request) {
        $user_id = get_current_user_id();

        // SL-139: Check org status-based access control via Commissions class
        $access = array();
        if (class_exists('OraBooks_Commissions')) {
            $comm = OraBooks_Commissions::get_instance();
            if (method_exists($comm, 'get_partner_dashboard_access')) {
                $access = $comm->get_partner_dashboard_access($user_id);
            }
        }

        if (isset($access['allowed']) && !$access['allowed']) {
            do_action('orabooks_security_event', 'partner_dashboard_blocked', array(
                'user_id' => $user_id,
                'status'  => $access['status'] ?? 'unknown',
                'reason'  => $access['message'] ?? '',
                'source'  => 'rest_api',
            ));
            return new WP_Error('dashboard_blocked', $access['message'] ?? __('Dashboard access denied.', 'orabooks'), array('status' => 403));
        }

        // Fire audit event
        do_action('orabooks_partner_dashboard_viewed', $user_id, current_time('mysql'), 'rest_api');
        do_action('orabooks_security_event', 'partner_dashboard_viewed', array(
            'user_id'   => $user_id,
            'timestamp' => current_time('mysql'),
            'source'    => 'rest_api',
        ));

        // Get partner code info
        $partner_code_obj = $this->get_partner_code($user_id);
        $active_code = $this->get_active_partner_code($user_id);

        $type_labels = array(
            'individual'         => __('Individual', 'orabooks'),
            'accountant'         => __('Accountant', 'orabooks'),
            'agency'             => __('Agency', 'orabooks'),
            'reseller'           => __('Reseller', 'orabooks'),
            'strategic_partner'  => __('Strategic Partner', 'orabooks'),
        );

        // Collect all data
        $data = array(
            'partner_code'   => $partner_code_obj ? $partner_code_obj->partner_code : null,
            'partner_type'   => $partner_code_obj ? $partner_code_obj->partner_type : null,
            'partner_type_label' => ($partner_code_obj && isset($type_labels[$partner_code_obj->partner_type])) ? $type_labels[$partner_code_obj->partner_type] : null,
            'organization_name' => $partner_code_obj ? $partner_code_obj->organization_name : null,
            'status'         => $partner_code_obj ? $partner_code_obj->status : null,
            'approved_at'    => ($active_code && !empty($active_code->approved_at)) ? $active_code->approved_at : null,
            'active_customers' => $this->get_active_customer_count($user_id),
            'attribution_counts' => $this->get_attribution_counts($user_id),
            'recent_attributions' => $this->get_recent_attributions($user_id, 10),
            'access'         => $access,
        );

        // Add commission data if available
        if (class_exists('OraBooks_Commissions')) {
            $comm = OraBooks_Commissions::get_instance();

            $data['commission_summary'] = $comm->get_commission_summary($user_id);
            $data['payout_summary']     = $comm->get_payout_summary($user_id);
            $data['recent_commissions'] = $comm->get_recent_commissions($user_id, 10);
            $data['commission_rate']    = $comm->get_partner_commission_rate($user_id);

            $data['config'] = array(
                'min_payout_threshold'      => (float) $comm->get_platform_config('min_payout_threshold'),
                'payout_fee_rate'           => (float) $comm->get_platform_config('payout_fee_rate'),
                'customer_active_window_days' => (int) $comm->get_platform_config('customer_active_window_days'),
            );

            // Estimated pending commission value
            $summary = $data['commission_summary'];
            $pending_estimated = 0;
            if ($summary['count_pending'] > 0 && $summary['count_qualified'] > 0) {
                $avg_qualified = $summary['total_qualified_gross'] / $summary['count_qualified'];
                $pending_estimated = round($summary['count_pending'] * $avg_qualified, 2);
            }
            $data['pending_estimated'] = $pending_estimated;
            $data['customer_count']    = $comm->get_active_customer_count_for_partner($user_id);
        }

        return new WP_REST_Response(array('success' => true, 'data' => $data), 200);
    }

}
