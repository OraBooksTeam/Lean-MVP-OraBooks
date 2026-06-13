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
     *
     * @param string $partner_code Partner code to validate
     * @param int    $customer_user_id Customer user ID (for fraud check)
     * @return array|WP_Error Partner info or error
     */
    public function validate_partner_code($partner_code, $customer_user_id) {
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

        // Fraud check: self-attribution blocked
        if ((int)$partner_code_row->user_id === (int)$customer_user_id) {
            return new WP_Error('self_attribution', __('Cannot attribute to yourself.', 'orabooks'));
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
        ));

        // Trigger partner_attribution_verified event for SL-068
        do_action('partner_attribution_verified', array(
            'attribution_id' => $attribution->id,
            'partner_user_id' => $attribution->partner_user_id,
            'customer_user_id' => $customer_user_id,
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
}

// Initialize the partners system
OraBooks_Partners::get_instance();
