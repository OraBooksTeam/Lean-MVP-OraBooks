<?php
/**
 * SL-004 - Multi-Tenant & Residency (Organizations)
 * 
 * This is the foundation domain layer for tenant isolation, tier-based resources,
 * subdomain governance, residency, and localization.
 * 
 * Build Order: SL-004 → SL-013 → SL-003 → SL-017 → SL-139 → SL-068
 * 
 * SL-004 provides:
 * - organizations table with tier, organization_type, status, subdomain, region
 * - Row-level isolation (every query must filter by org_id)
 * - Quota framework (customers) and unlimited quotas for partners
 * - Subdomain validation and uniqueness
 * - Partner org lifecycle: pending_setup → active | suspended | fraud_freeze (terminal)
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Organizations {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Cache for organization data
     */
    private $org_cache = array();

    /**
     * Reserved subdomains that MUST be blocked globally (SL-004 mandatory)
     */
    const RESERVED_SUBDOMAINS = array(
        'admin', 'api', 'app', 'support', 'billing', 
        'partner', 'orabooks', 'www', 'root'
    );

    /**
     * Valid organization statuses
     */
    const ORG_STATUSES = array(
        'pending_setup', 'active', 'suspended', 'payout_hold', 'fraud_freeze'
    );

    /**
     * Valid organization tiers
     */
    const TIERS = array('free', 'premium', 'enterprise', 'partner');

    /**
     * Valid organization types
     */
    const ORG_TYPES = array('customer', 'partner');

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
        // Initialize table names on multisite
        add_action('plugins_loaded', array($this, 'init_table_names'));
        
        // Register hooks
        add_action('init', array($this, 'register_rewrite_rules'));
    }

    /**
     * SL-004: Initialize table names for multisite compatibility.
     */
    public function init_table_names() {
        global $wpdb;
        
        if (is_multisite()) {
            $prefix = $wpdb->base_prefix;
        } else {
            $prefix = $wpdb->prefix;
        }
        
        $wpdb->orabooks_organizations = $prefix . 'orabooks_organizations';
        $wpdb->orabooks_org_quotas = $prefix . 'orabooks_org_quotas';
        $wpdb->orabooks_partner_reactivation_reviews = $prefix . 'orabooks_partner_reactivation_reviews';
    }

    /**
     * Register rewrite rules for subdomain-based routing.
     */
    public function register_rewrite_rules() {
        // No rewrite rules needed - subdomain routing is handled at the web server level.
    }

    /**
     * SL-004 §5.1: Create organizations table schema.
     * Run during plugin activation.
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $table_name = $wpdb->base_prefix . 'orabooks_organizations';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            tier ENUM('free','premium','enterprise','partner') NOT NULL,
            subdomain VARCHAR(63) UNIQUE NOT NULL,
            owner_id INT NOT NULL,
            region VARCHAR(20) DEFAULT 'us-east',
            status ENUM('pending_setup','active','suspended','payout_hold','fraud_freeze') DEFAULT 'active',
            organization_type ENUM('customer','partner') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_subdomain (subdomain),
            INDEX idx_owner (owner_id),
            INDEX idx_status (status),
            INDEX idx_type_tier (organization_type, tier)
        ) {$charset_collate};";
        
        dbDelta($sql);

        // Quotas table
        $quotas_table = $wpdb->base_prefix . 'orabooks_org_quotas';
        $sql_quotas = "CREATE TABLE IF NOT EXISTS {$quotas_table} (
            org_id BIGINT PRIMARY KEY,
            api_calls_limit INT NULL,
            storage_limit_mb INT NULL,
            user_limit INT NULL,
            FOREIGN KEY (org_id) REFERENCES {$table_name}(id) ON DELETE CASCADE
        ) {$charset_collate};";
        
        dbDelta($sql_quotas);

        // Partner reactivation reviews table (SL-004 §5.4.3)
        $reviews_table = $wpdb->base_prefix . 'orabooks_partner_reactivation_reviews';
        $sql_reviews = "CREATE TABLE IF NOT EXISTS {$reviews_table} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT NOT NULL,
            requested_by INT NOT NULL,
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reason TEXT NOT NULL,
            reviewed_by INT NULL,
            reviewed_at TIMESTAMP NULL,
            decision ENUM('approved','denied') NULL,
            reviewer_notes TEXT NULL,
            FOREIGN KEY (org_id) REFERENCES {$table_name}(id),
            INDEX idx_org (org_id)
        ) {$charset_collate};";
        
        dbDelta($sql_reviews);
        
        error_log('[OraBooks SL-004] Organizations tables created/verified.');
    }

    /**
     * SL-004 §5.1: Create a new organization.
     * 
     * @param array $args {
     *     Organization creation arguments.
     *     @type string $tier              'free'|'premium'|'enterprise'|'partner'
     *     @type string $subdomain         Unique subdomain identifier
     *     @type int    $owner_id          User ID of the owner
     *     @type string $region            Region (default 'us-east')
     *     @type string $organization_type 'customer'|'partner'
     *     @type string $organization_name Optional display name
     * }
     * @return array|WP_Error Result with org_id and subdomain, or error
     */
    public function create_organization($args) {
        global $wpdb;

        $tier = isset($args['tier']) ? sanitize_text_field($args['tier']) : '';
        $subdomain = isset($args['subdomain']) ? sanitize_text_field($args['subdomain']) : '';
        $owner_id = isset($args['owner_id']) ? intval($args['owner_id']) : 0;
        $region = isset($args['region']) ? sanitize_text_field($args['region']) : 'us-east';
        $org_type = isset($args['organization_type']) ? sanitize_text_field($args['organization_type']) : '';
        $org_name = isset($args['organization_name']) ? sanitize_text_field($args['organization_name']) : '';

        // Validate tier
        if (!in_array($tier, self::TIERS, true)) {
            return new WP_Error('invalid_tier', __('Invalid organization tier.', 'orabooks'));
        }

        // Validate organization_type
        if (!in_array($org_type, self::ORG_TYPES, true)) {
            return new WP_Error('invalid_org_type', __('Invalid organization type.', 'orabooks'));
        }

        // Partner consistency check (DB invariant: chk_partner_consistency)
        if ($org_type === 'partner' && $tier !== 'partner') {
            return new WP_Error('partner_tier_mismatch', __('Partner organizations must have tier=partner.', 'orabooks'));
        }
        if ($org_type === 'customer' && $tier === 'partner') {
            return new WP_Error('customer_tier_mismatch', __('Customer organizations cannot have tier=partner.', 'orabooks'));
        }

        // Validate subdomain
        $subdomain_validation = $this->validate_subdomain($subdomain);
        if (is_wp_error($subdomain_validation)) {
            return $subdomain_validation;
        }

        // Check subdomain uniqueness
        $existing = $this->get_organization_by_subdomain($subdomain);
        if ($existing) {
            return new WP_Error('subdomain_taken', __('This subdomain is already taken.', 'orabooks'));
        }

        // Determine name
        if (!empty($org_name)) {
            $name = $org_name;
        } elseif ($org_type === 'partner') {
            $name = sprintf('Partner %d', $owner_id);
        } else {
            $name = sprintf('Org_%s', $subdomain);
        }

        // Determine status
        $status = ($org_type === 'partner') ? 'pending_setup' : 'active';

        // Determine region (SL-004: free/premium system-assigned, enterprise selectable, partner system-assigned)
        if ($tier !== 'enterprise') {
            $region = 'us-east'; // System-assigned for non-enterprise
        }

        $table_name = $wpdb->base_prefix . 'orabooks_organizations';

        $wpdb->insert(
            $table_name,
            array(
                'name' => $name,
                'tier' => $tier,
                'subdomain' => $subdomain,
                'owner_id' => $owner_id,
                'region' => $region,
                'status' => $status,
                'organization_type' => $org_type,
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );

        if ($wpdb->last_error) {
            return new WP_Error('db_insert_error', $wpdb->last_error);
        }

        $org_id = $wpdb->insert_id;

        // Create quota entry (SL-004: partners get NULL limits = unlimited)
        $this->create_org_quotas($org_id, $org_type, $tier);

        // Audit event
        $event = ($org_type === 'partner') ? 'partner_org_created' : 'org_created';
        do_action('orabooks_security_event', $event, array(
            'org_id' => $org_id,
            'organization_type' => $org_type,
            'tier' => $tier,
            'owner_id' => $owner_id,
        ));

        // Clear cache
        $this->clear_cache($subdomain);

        return array(
            'org_id' => $org_id,
            'subdomain' => $subdomain,
        );
    }

    /**
     * SL-004: Create org_quotas entry for an organization.
     * Partners have NULL limits (unlimited). Customers get limits based on tier.
     *
     * @param int    $org_id  Organization ID
     * @param string $org_type 'customer' or 'partner'
     * @param string $tier    Tier name
     */
    private function create_org_quotas($org_id, $org_type, $tier) {
        global $wpdb;
        
        $quotas_table = $wpdb->base_prefix . 'orabooks_org_quotas';

        if ($org_type === 'partner') {
            // SL-004: Partners get NULL limits = unlimited
            $wpdb->insert(
                $quotas_table,
                array(
                    'org_id' => $org_id,
                    'api_calls_limit' => null,
                    'storage_limit_mb' => null,
                    'user_limit' => null,
                ),
                array('%d', null, null, null)
            );
        } else {
            // Customers get tier-based limits
            $limits = $this->get_tier_limits($tier);
            $wpdb->insert(
                $quotas_table,
                array_merge(array('org_id' => $org_id), $limits),
                array('%d', '%d', '%d', '%d')
            );
        }
    }

    /**
     * SL-004: Get resource limits for a given customer tier.
     *
     * @param string $tier Tier name
     * @return array Limits array
     */
    private function get_tier_limits($tier) {
        $limits = array(
            'free' => array(
                'api_calls_limit' => 1000,
                'storage_limit_mb' => 100,
                'user_limit' => 5,
            ),
            'premium' => array(
                'api_calls_limit' => 10000,
                'storage_limit_mb' => 1000,
                'user_limit' => 25,
            ),
            'enterprise' => array(
                'api_calls_limit' => 100000,
                'storage_limit_mb' => 10000,
                'user_limit' => 100,
            ),
        );

        return isset($limits[$tier]) ? $limits[$tier] : $limits['free'];
    }

    /**
     * SL-004: Validate subdomain format and reserved words.
     *
     * @param string $subdomain Subdomain to validate
     * @return true|WP_Error
     */
    public function validate_subdomain($subdomain) {
        $subdomain = trim(strtolower($subdomain));

        if (empty($subdomain)) {
            return new WP_Error('empty_subdomain', __('Subdomain cannot be empty.', 'orabooks'));
        }

        // Format: lowercase alphanumeric + hyphen, 3-63 chars
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/', $subdomain)) {
            return new WP_Error('invalid_subdomain_format', __('Subdomain must be 3-63 characters, lowercase alphanumeric with hyphens, and cannot start/end with hyphen.', 'orabooks'));
        }

        // Check reserved words (SL-004 mandatory, case-insensitive)
        if (in_array($subdomain, self::RESERVED_SUBDOMAINS, true)) {
            return new WP_Error('reserved_subdomain', sprintf(
                __('The subdomain "%s" is reserved and cannot be used.', 'orabooks'),
                $subdomain
            ));
        }

        return true;
    }

    /**
     * SL-004 §5.2: Get organization by subdomain.
     *
     * @param string $subdomain Subdomain identifier
     * @return object|false Organization object or false
     */
    public function get_organization_by_subdomain($subdomain) {
        global $wpdb;

        $subdomain = trim(strtolower($subdomain));

        // Check cache
        if (isset($this->org_cache[$subdomain])) {
            return $this->org_cache[$subdomain];
        }

        $table_name = $wpdb->base_prefix . 'orabooks_organizations';
        $org = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE subdomain = %s",
            $subdomain
        ));

        if ($org) {
            $this->org_cache[$subdomain] = $org;
        }

        return $org;
    }

    /**
     * SL-004 §5.2: Get organization by ID.
     *
     * @param int $org_id Organization ID
     * @return object|false Organization object or false
     */
    public function get_organization($org_id) {
        global $wpdb;
        
        $table_name = $wpdb->base_prefix . 'orabooks_organizations';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $org_id
        ));
    }

    /**
     * SL-004 §5.3: Update organization tier (customer only).
     *
     * @param int    $org_id   Organization ID
     * @param string $new_tier New tier (premium/enterprise)
     * @return true|WP_Error
     */
    public function update_tier($org_id, $new_tier) {
        global $wpdb;

        $org = $this->get_organization($org_id);
        if (!$org) {
            return new WP_Error('org_not_found', __('Organization not found.', 'orabooks'));
        }

        // Only customers can change tier
        if ($org->organization_type !== 'customer') {
            return new WP_Error('partner_tier_change', __('Partner organizations cannot change tier.', 'orabooks'));
        }

        // Validate new tier
        if (!in_array($new_tier, array('premium', 'enterprise'), true)) {
            return new WP_Error('invalid_tier', __('Invalid tier. Allowed: premium, enterprise.', 'orabooks'));
        }

        // Downgrade not allowed in MVP (SL-004 §5.3)
        $tier_rank = array('free' => 0, 'premium' => 1, 'enterprise' => 2, 'partner' => 0);
        if ($tier_rank[$new_tier] <= $tier_rank[$org->tier]) {
            return new WP_Error('downgrade_not_allowed', __('Downgrade is not allowed in MVP.', 'orabooks'));
        }

        $table_name = $wpdb->base_prefix . 'orabooks_organizations';
        $wpdb->update(
            $table_name,
            array('tier' => $new_tier),
            array('id' => $org_id),
            array('%s'),
            array('%d')
        );

        // Update quotas for new tier
        $quotas_table = $wpdb->base_prefix . 'orabooks_org_quotas';
        $limits = $this->get_tier_limits($new_tier);
        $wpdb->update(
            $quotas_table,
            $limits,
            array('org_id' => $org_id),
            array('%d', '%d', '%d'),
            array('%d')
        );

        // Audit
        do_action('orabooks_security_event', 'org_tier_changed', array(
            'org_id' => $org_id,
            'old_tier' => $org->tier,
            'new_tier' => $new_tier,
        ));

        $this->clear_cache($org->subdomain);

        return true;
    }

    /**
     * SL-004 §5.4: Suspend an organization (Super Admin only).
     *
     * @param int    $org_id Organization ID
     * @param string $reason Optional reason
     * @return true|WP_Error
     */
    public function suspend_organization($org_id, $reason = '') {
        global $wpdb;

        $org = $this->get_organization($org_id);
        if (!$org) {
            return new WP_Error('org_not_found', __('Organization not found.', 'orabooks'));
        }

        if ($org->status === 'fraud_freeze') {
            return new WP_Error('fraud_freeze_terminal', __('Fraud-frozen organizations cannot be modified.', 'orabooks'));
        }

        $table_name = $wpdb->base_prefix . 'orabooks_organizations';
        $wpdb->update(
            $table_name,
            array('status' => 'suspended'),
            array('id' => $org_id),
            array('%s'),
            array('%d')
        );

        do_action('orabooks_security_event', 'org_suspended', array(
            'org_id' => $org_id,
            'organization_type' => $org->organization_type,
            'reason' => $reason,
        ));

        // For partner orgs, notify SL-139/SL-068 to disable commission accrual
        if ($org->organization_type === 'partner') {
            do_action('orabooks_partner_suspended', $org_id);
        }

        $this->clear_cache($org->subdomain);

        return true;
    }

    /**
     * SL-004 §5.4.2: Reactivate a customer organization (Super Admin only).
     *
     * @param int $org_id Organization ID
     * @return true|WP_Error
     */
    public function reactivate_customer_org($org_id) {
        global $wpdb;

        $org = $this->get_organization($org_id);
        if (!$org) {
            return new WP_Error('org_not_found', __('Organization not found.', 'orabooks'));
        }

        if ($org->organization_type !== 'customer') {
            return new WP_Error('not_customer_org', __('Use partner reactivation workflow for partner orgs.', 'orabooks'));
        }

        if ($org->status === 'fraud_freeze') {
            return new WP_Error('fraud_freeze_terminal', __('Fraud-frozen organizations cannot be reactivated.', 'orabooks'));
        }

        $table_name = $wpdb->base_prefix . 'orabooks_organizations';
        $wpdb->update(
            $table_name,
            array('status' => 'active'),
            array('id' => $org_id),
            array('%s'),
            array('%d')
        );

        do_action('orabooks_security_event', 'org_reactivated', array(
            'org_id' => $org_id,
            'organization_type' => 'customer',
        ));

        $this->clear_cache($org->subdomain);

        return true;
    }

    /**
     * SL-004 §5.4.3: Request partner reactivation review.
     *
     * @param int    $org_id Organization ID
     * @param int    $user_id Requesting user ID
     * @param string $reason  Reason for reactivation
     * @return true|WP_Error
     */
    public function request_partner_reactivation($org_id, $user_id, $reason) {
        global $wpdb;

        $org = $this->get_organization($org_id);
        if (!$org) {
            return new WP_Error('org_not_found', __('Organization not found.', 'orabooks'));
        }

        if ($org->organization_type !== 'partner') {
            return new WP_Error('not_partner_org', __('Only partner organizations use this workflow.', 'orabooks'));
        }

        if ($org->status === 'fraud_freeze') {
            return new WP_Error('fraud_freeze_terminal', __('Fraud-frozen partners cannot request reactivation.', 'orabooks'));
        }

        if ($org->status !== 'suspended') {
            return new WP_Error('not_suspended', __('Only suspended partners can request reactivation.', 'orabooks'));
        }

        $reviews_table = $wpdb->base_prefix . 'orabooks_partner_reactivation_reviews';
        $wpdb->insert(
            $reviews_table,
            array(
                'org_id' => $org_id,
                'requested_by' => $user_id,
                'reason' => $reason,
                'requested_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s')
        );

        do_action('orabooks_security_event', 'partner_reactivation_requested', array(
            'org_id' => $org_id,
            'user_id' => $user_id,
        ));

        // Notify compliance team (SL-250)
        do_action('orabooks_notify_compliance_team', array(
            'type' => 'partner_reactivation_request',
            'org_id' => $org_id,
            'org_name' => $org->name,
        ));

        return true;
    }

    /**
     * SL-004 §5.4.3: Review a partner reactivation request.
     *
     * @param int    $review_id     Review request ID
     * @param int    $reviewer_id   Admin user ID
     * @param string $decision      'approved' or 'denied'
     * @param string $notes         Reviewer notes
     * @return true|WP_Error
     */
    public function review_partner_reactivation($review_id, $reviewer_id, $decision, $notes = '') {
        global $wpdb;

        if (!in_array($decision, array('approved', 'denied'), true)) {
            return new WP_Error('invalid_decision', __('Decision must be approved or denied.', 'orabooks'));
        }

        $reviews_table = $wpdb->base_prefix . 'orabooks_partner_reactivation_reviews';
        $review = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$reviews_table} WHERE id = %d",
            $review_id
        ));

        if (!$review) {
            return new WP_Error('review_not_found', __('Review request not found.', 'orabooks'));
        }

        $wpdb->update(
            $reviews_table,
            array(
                'reviewed_by' => $reviewer_id,
                'reviewed_at' => current_time('mysql'),
                'decision' => $decision,
                'reviewer_notes' => $notes,
            ),
            array('id' => $review_id),
            array('%d', '%s', '%s', '%s'),
            array('%d')
        );

        if ($decision === 'approved') {
            // Reactivate the org
            $table_name = $wpdb->base_prefix . 'orabooks_organizations';
            $wpdb->update(
                $table_name,
                array('status' => 'active'),
                array('id' => $review->org_id),
                array('%s'),
                array('%d')
            );

            do_action('orabooks_security_event', 'partner_reactivation_approved', array(
                'org_id' => $review->org_id,
                'reviewed_by' => $reviewer_id,
            ));
        } else {
            do_action('orabooks_security_event', 'partner_reactivation_denied', array(
                'org_id' => $review->org_id,
                'reviewed_by' => $reviewer_id,
                'notes' => $notes,
            ));
        }

        $org = $this->get_organization($review->org_id);
        if ($org) {
            $this->clear_cache($org->subdomain);
        }

        return true;
    }

    /**
     * SL-004 §5.5: Set partner organization to payout_hold.
     *
     * @param int $org_id Organization ID
     * @return true|WP_Error
     */
    public function set_payout_hold($org_id) {
        global $wpdb;

        $org = $this->get_organization($org_id);
        if (!$org) {
            return new WP_Error('org_not_found', __('Organization not found.', 'orabooks'));
        }

        if ($org->organization_type !== 'partner') {
            return new WP_Error('not_partner_org', __('Only partner organizations can be set to payout_hold.', 'orabooks'));
        }

        $table_name = $wpdb->base_prefix . 'orabooks_organizations';
        $wpdb->update(
            $table_name,
            array('status' => 'payout_hold'),
            array('id' => $org_id),
            array('%s'),
            array('%d')
        );

        do_action('orabooks_security_event', 'partner_payout_hold', array(
            'org_id' => $org_id,
        ));

        $this->clear_cache($org->subdomain);

        return true;
    }

    /**
     * SL-004 §5.5: Set partner organization to fraud_freeze (terminal state).
     *
     * @param int $org_id Organization ID
     * @return true|WP_Error
     */
    public function set_fraud_freeze($org_id) {
        global $wpdb;

        $org = $this->get_organization($org_id);
        if (!$org) {
            return new WP_Error('org_not_found', __('Organization not found.', 'orabooks'));
        }

        if ($org->organization_type !== 'partner') {
            return new WP_Error('not_partner_org', __('Only partner organizations can be fraud-frozen.', 'orabooks'));
        }

        $table_name = $wpdb->base_prefix . 'orabooks_organizations';
        $wpdb->update(
            $table_name,
            array('status' => 'fraud_freeze'),
            array('id' => $org_id),
            array('%s'),
            array('%d')
        );

        do_action('orabooks_security_event', 'partner_fraud_freeze', array(
            'org_id' => $org_id,
        ));

        $this->clear_cache($org->subdomain);

        return true;
    }

    /**
     * SL-004 §5.7: Tenant isolation middleware.
     * Checks that the JWT org_id matches the requested resource's org_id.
     *
     * @param int  $request_org_id        Organization ID from the request context
     * @param int  $user_org_id           Organization ID from the authenticated user's JWT
     * @param bool $is_accounting_endpoint Whether this is an accounting API call (optional)
     * @return bool True if allowed, false if blocked
     */
    public function check_tenant_isolation($request_org_id, $user_org_id, $is_accounting_endpoint = false) {
        if ((int)$request_org_id !== (int)$user_org_id) {
            do_action('orabooks_security_event', 'tenant_isolation_violation', array(
                'request_org_id' => $request_org_id,
                'user_org_id' => $user_org_id,
                'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
            ));
            return false;
        }

        // Also check that the org status is active
        $org = $this->get_organization($request_org_id);
        if (!$org || $org->status !== 'active') {
            return false;
        }

        // Block partner orgs from accounting endpoints
        if ($org->organization_type === 'partner' && $is_accounting_endpoint) {
            return false;
        }

        return true;
    }

    /**
     * SL-004: Check if an organization is blocked from accessing accounting APIs.
     *
     * @param int $org_id Organization ID
     * @return bool True if blocked (partner org or suspended)
     */
    public function is_accounting_blocked($org_id) {
        $org = $this->get_organization($org_id);
        if (!$org) {
            return true; // Block if not found
        }

        // Partner orgs cannot access accounting (SL-013 middleware + defensive check)
        if ($org->organization_type === 'partner') {
            return true;
        }

        // Suspended/fraud-frozen orgs cannot access accounting
        if (!in_array($org->status, array('active', 'payout_hold'), true)) {
            return true;
        }

        return false;
    }

    /**
     * SL-004: Get quota for an organization.
     *
     * @param int $org_id Organization ID
     * @return object|false Quota object or false
     */
    public function get_org_quotas($org_id) {
        global $wpdb;
        
        $quotas_table = $wpdb->base_prefix . 'orabooks_org_quotas';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$quotas_table} WHERE org_id = %d",
            $org_id
        ));
    }

    /**
     * SL-004: List all organizations (Super Admin).
     *
     * @param array $args Query arguments
     * @return array Array of organization objects
     */
    public function list_organizations($args = array()) {
        global $wpdb;
        
        $table_name = $wpdb->base_prefix . 'orabooks_organizations';
        
        $where = array('1=1');
        $params = array();
        
        if (!empty($args['organization_type'])) {
            $where[] = 'organization_type = %s';
            $params[] = $args['organization_type'];
        }
        
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }
        
        if (!empty($args['tier'])) {
            $where[] = 'tier = %s';
            $params[] = $args['tier'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $limit = isset($args['limit']) ? intval($args['limit']) : 50;
        $offset = isset($args['offset']) ? intval($args['offset']) : 0;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            array_merge($params, array($limit, $offset))
        );
        
        return $wpdb->get_results($query);
    }

    /**
     * SL-004: Clear organization cache.
     *
     * @param string $subdomain Subdomain to clear (optional, clears all if empty)
     */
    public function clear_cache($subdomain = '') {
        if (!empty($subdomain) && isset($this->org_cache[$subdomain])) {
            unset($this->org_cache[$subdomain]);
        } elseif (empty($subdomain)) {
            $this->org_cache = array();
        }
    }

    /**
     * SL-004: Partner badge wording helper.
     * Returns "Partner Account (Commission)" - no referral language.
     */
    public static function get_partner_badge() {
        return array(
            'text' => __('Partner Account (Commission)', 'orabooks'),
            'tooltip' => __('You earn commissions from qualified customers attributed to your Partner Code. No accounting features.', 'orabooks'),
        );
    }
}

// Initialize the organizations system
OraBooks_Organizations::get_instance();