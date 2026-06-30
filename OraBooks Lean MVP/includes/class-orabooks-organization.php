<?php
/**
 * OraBooks Organization Management (SL-004)
 * 
 * Multi-tenant organization foundation with tier management,
 * subdomain governance, residency, and partner org support.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Organization {
    
    private static $instance = null;
    
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Create a new organization
     */
    public static function create($data) {
        global $wpdb;
        
        $table_orgs = OraBooks_Database::table('organizations');
        $table_quotas = OraBooks_Database::table('org_quotas');
        
        $organization_type = $data['organization_type'] ?? 'customer';
        $tier = $data['tier'] ?? ($organization_type === 'partner' ? 'partner' : 'free');
        
        // Validate partner consistency
        if ($organization_type === 'partner' && $tier !== 'partner') {
            return new WP_Error('invalid_tier', 'Partner orgs must have tier=partner');
        }
        if ($organization_type === 'customer' && $tier === 'partner') {
            return new WP_Error('invalid_tier', 'Customer orgs cannot have tier=partner');
        }
        
        // Validate subdomain
        if (!empty($data['subdomain'])) {
            $subdomain_check = orabooks_validate_subdomain($data['subdomain']);
            if ($subdomain_check !== true) {
                return new WP_Error('invalid_subdomain', $subdomain_check);
            }
            
            // Check uniqueness
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_orgs} WHERE subdomain = %s",
                strtolower(trim($data['subdomain']))
            ));
            if ($exists) {
                return new WP_Error('subdomain_taken', 'Subdomain already taken');
            }

            if (function_exists('orabooks_multisite_subdomain_taken') && orabooks_multisite_subdomain_taken($data['subdomain'])) {
                return new WP_Error('subdomain_taken', 'Subdomain already taken');
            }
        }
        
        $owner_id = $data['owner_id'];
        $name = $data['name'] ?? ($organization_type === 'partner' ? 'Partner ' . $owner_id : 'Org_' . orabooks_random_string(8));
        $subdomain = $data['subdomain'] ?? ($organization_type === 'partner' ? 'partner-' . $owner_id : 'org-' . substr(md5(uniqid()), 0, 8));

        if ($organization_type === 'partner') {
            $region = orabooks_get_default_region_for_tier('partner');
        } else {
            $tier_for_region = in_array($tier, ['free', 'premium', 'enterprise'], true) ? $tier : 'free';
            $region_input = isset($data['region']) ? strtolower(trim((string) $data['region'])) : '';
            $region_check = orabooks_validate_org_region(
                $tier_for_region === 'enterprise' ? $region_input : orabooks_get_default_region_for_tier($tier_for_region),
                $tier_for_region
            );
            if ($region_check !== true) {
                return new WP_Error('invalid_region', $region_check);
            }
            $region = ($tier_for_region === 'enterprise')
                ? $region_input
                : orabooks_get_default_region_for_tier($tier_for_region);
        }

        $status = $organization_type === 'partner' ? 'pending_setup' : 'active';
        
        $wpdb->insert(
            $table_orgs,
            [
                'name' => $name,
                'tier' => $tier,
                'subdomain' => strtolower(trim($subdomain)),
                'owner_id' => $owner_id,
                'region' => $region,
                'status' => $status,
                'organization_type' => $organization_type
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );
        
        $org_id = $wpdb->insert_id;
        if (!$org_id) {
            return new WP_Error('creation_failed', 'Failed to create organization');
        }
        
        // Insert quotas
        if ($organization_type === 'partner') {
            $wpdb->insert(
                $table_quotas,
                ['org_id' => $org_id],
                ['%d']
            );
        } else {
            $limits = self::get_tier_limits($tier);
            $wpdb->insert(
                $table_quotas,
                ['org_id' => $org_id, 'api_calls_limit' => $limits['api_calls'], 'storage_limit_mb' => $limits['storage_mb'], 'user_limit' => $limits['users']],
                ['%d', '%d', '%d', '%d']
            );
        }
        
        if ($organization_type === 'customer' && class_exists('OraBooks_Approval')) {
            OraBooks_Approval::ensure_policy($org_id);
        }
        
        // Update user's org_id
        $table_users = OraBooks_Database::table('users');
        $wpdb->update(
            $table_users,
            ['org_id' => $org_id],
            ['id' => $owner_id],
            ['%d'],
            ['%d']
        );
        
        // Add owner role
        $table_user_org = OraBooks_Database::table('user_org');
        $wpdb->insert(
            $table_user_org,
            ['user_id' => $owner_id, 'org_id' => $org_id, 'role' => 'owner'],
            ['%d', '%d', '%s']
        );
        
        if ($organization_type === 'customer' && class_exists('OraBooks_COA') && method_exists('OraBooks_COA', 'load_chart_of_accounts')) {
            OraBooks_COA::load_chart_of_accounts($org_id, $tier, $organization_type);
        }

        if ($organization_type === 'customer' && class_exists('OraBooks_Fiscal') && method_exists('OraBooks_Fiscal', 'ensure_periods_for_org')) {
            OraBooks_Fiscal::ensure_periods_for_org($org_id);
        }

        if ($organization_type === 'customer' && class_exists('OraBooks_Tax') && method_exists('OraBooks_Tax', 'seed_default_org_configs')) {
            $country = !empty($data['country']) ? sanitize_text_field($data['country']) : null;
            OraBooks_Tax::seed_default_org_configs($org_id, $country);
        }
        
        // Audit log
        $event_type = $organization_type === 'partner' ? 'partner_org_created' : 'org_created';
        $audit_meta = [
            'organization_type' => $organization_type,
            'tier' => $tier,
            'org_id' => $org_id,
        ];
        if ($organization_type === 'partner') {
            if (!empty($data['partner_type'])) {
                $audit_meta['partner_type'] = $data['partner_type'];
            }
            if (!empty($data['organization_name'])) {
                $audit_meta['organization_name'] = $data['organization_name'];
            }
        }
        orabooks_log_event($event_type, "Organization created: $name ($subdomain)", 'info', $audit_meta, $owner_id, $org_id);

        if ($organization_type === 'customer' && function_exists('orabooks_provision_org_multisite')) {
            orabooks_provision_org_multisite($org_id, $subdomain, $name, $owner_id);
        }
        
        return [
            'org_id' => $org_id,
            'subdomain' => $subdomain,
            'status' => $status,
            'organization_type' => $organization_type,
            'tier' => $tier
        ];
    }
    
    /**
     * Get organization by ID
     */
    public static function get($org_id) {
        global $wpdb;
        $table = OraBooks_Database::table('organizations');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d", $org_id
        ));
    }
    
    /**
     * Get organization by subdomain
     */
    public static function get_by_subdomain($subdomain) {
        global $wpdb;
        $table = OraBooks_Database::table('organizations');
        $subdomain = strtolower(trim($subdomain));
        
        $org = wp_cache_get("org_subdomain_$subdomain", 'orabooks');
        if ($org === false) {
            $org = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE subdomain = %s", $subdomain
            ));
            if ($org) {
                wp_cache_set("org_subdomain_$subdomain", $org, 'orabooks', 300);
            }
        }
        return $org;
    }

    /**
     * Public lookup by subdomain for auth/routing (SL-004 §5.2).
     * Returns WP_Error when org is missing or not active.
     *
     * @return object|WP_Error
     */
    public static function get_active_by_subdomain($subdomain) {
        $org = self::get_by_subdomain($subdomain);
        if (!$org) {
            return new WP_Error('org_not_found', 'Organization not found.');
        }

        if (($org->status ?? '') !== 'active') {
            return new WP_Error('org_inactive', 'This organization is not active.');
        }

        return $org;
    }

    /**
     * Change data residency for enterprise customer orgs (SL-004 §5.6).
     *
     * @return true|WP_Error
     */
    public static function change_region($org_id, $region, $admin_id) {
        global $wpdb;

        $org = self::get($org_id);
        if (!$org) {
            return new WP_Error('not_found', 'Organization not found');
        }

        $frozen = self::assert_not_fraud_frozen($org);
        if (is_wp_error($frozen)) {
            return $frozen;
        }

        if ($org->organization_type !== 'customer' || $org->tier !== 'enterprise') {
            return new WP_Error('invalid_type', 'Region changes are only allowed for enterprise customer organizations.');
        }

        $region = strtolower(trim((string) $region));
        $region_check = orabooks_validate_org_region($region, 'enterprise');
        if ($region_check !== true) {
            return new WP_Error('invalid_region', $region_check);
        }

        if ($org->region === $region) {
            return true;
        }

        $old_region = $org->region;
        $wpdb->update(
            OraBooks_Database::table('organizations'),
            ['region' => $region],
            ['id' => (int) $org_id],
            ['%s'],
            ['%d']
        );

        wp_cache_delete("org_subdomain_{$org->subdomain}", 'orabooks');

        orabooks_log_event('org_region_changed', 'Organization data residency region changed', 'warning', [
            'old_region' => $old_region,
            'new_region' => $region,
            'migration' => 'queued',
        ], (int) $admin_id, (int) $org_id);

        do_action('orabooks_org_region_migration_requested', (int) $org_id, $old_region, $region);

        return true;
    }
    
    /**
     * Update organization tier
     */
    public static function update_tier($org_id, $new_tier, $user_id) {
        global $wpdb;
        
        $org = self::get($org_id);
        if (!$org) {
            return new WP_Error('not_found', 'Organization not found');
        }
        if ($org->organization_type !== 'customer') {
            return new WP_Error('invalid_type', 'Only customer orgs can change tier');
        }
        
        $old_tier = $org->tier;
        $wpdb->update(
            OraBooks_Database::table('organizations'),
            ['tier' => $new_tier],
            ['id' => $org_id],
            ['%s'],
            ['%d']
        );
        
        // Update quotas
        $limits = self::get_tier_limits($new_tier);
        $wpdb->update(
            OraBooks_Database::table('org_quotas'),
            ['api_calls_limit' => $limits['api_calls'], 'storage_limit_mb' => $limits['storage_mb'], 'user_limit' => $limits['users']],
            ['org_id' => $org_id],
            ['%d', '%d', '%d'],
            ['%d']
        );
        
        orabooks_log_event('org_tier_changed', "Tier changed: $old_tier -> $new_tier", 'info', [
            'old_tier' => $old_tier,
            'new_tier' => $new_tier
        ], $user_id, $org_id);
        
        // Invalidate cache
        wp_cache_delete("org_subdomain_{$org->subdomain}", 'orabooks');
        
        return true;
    }
    
    /**
     * fraud_freeze is terminal — no status transitions allowed (SL-004).
     */
    private static function assert_not_fraud_frozen($org) {
        if ($org && ($org->status ?? '') === 'fraud_freeze') {
            return new WP_Error('fraud_freeze_terminal', 'Organization is permanently frozen and cannot be modified.');
        }
        return true;
    }
    
    /**
     * Suspend organization
     */
    public static function suspend($org_id, $admin_id) {
        global $wpdb;
        
        $org = self::get($org_id);
        if (!$org) {
            return new WP_Error('not_found', 'Organization not found');
        }

        $frozen = self::assert_not_fraud_frozen($org);
        if (is_wp_error($frozen)) {
            return $frozen;
        }
        
        $wpdb->update(
            OraBooks_Database::table('organizations'),
            ['status' => 'suspended'],
            ['id' => $org_id],
            ['%s'],
            ['%d']
        );
        
        orabooks_log_event('org_suspended', "Organization suspended", 'warning', [
            'organization_type' => $org->organization_type
        ], $admin_id, $org_id);
        
        wp_cache_delete("org_subdomain_{$org->subdomain}", 'orabooks');
        return true;
    }
    
    /**
     * Reactivate customer organization
     */
    public static function reactivate_customer($org_id, $admin_id) {
        global $wpdb;
        
        $org = self::get($org_id);
        if (!$org || $org->organization_type !== 'customer') {
            return new WP_Error('invalid', 'Only customer orgs can be directly reactivated');
        }

        $frozen = self::assert_not_fraud_frozen($org);
        if (is_wp_error($frozen)) {
            return $frozen;
        }
        
        $wpdb->update(
            OraBooks_Database::table('organizations'),
            ['status' => 'active'],
            ['id' => $org_id],
            ['%s'],
            ['%d']
        );
        
        orabooks_log_event('org_reactivated', "Organization reactivated", 'info', [], $admin_id, $org_id);
        wp_cache_delete("org_subdomain_{$org->subdomain}", 'orabooks');
        return true;
    }
    
    /**
     * Request partner reactivation review
     */
    public static function request_partner_reactivation($org_id, $requested_by, $reason) {
        global $wpdb;
        
        $org = self::get($org_id);
        if (!$org || $org->organization_type !== 'partner') {
            return new WP_Error('invalid', 'Only partner orgs use this workflow');
        }

        $frozen = self::assert_not_fraud_frozen($org);
        if (is_wp_error($frozen)) {
            return $frozen;
        }
        
        $table = OraBooks_Database::table('partner_reactivation_reviews');
        $wpdb->insert(
            $table,
            ['org_id' => $org_id, 'requested_by' => $requested_by, 'reason' => $reason],
            ['%d', '%d', '%s']
        );
        
        orabooks_log_event('partner_reactivation_requested', "Partner reactivation requested", 'info', [
            'reason' => $reason
        ], $requested_by, $org_id);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Review partner reactivation
     */
    public static function review_reactivation($review_id, $admin_id, $decision, $notes = '') {
        global $wpdb;
        
        $table = OraBooks_Database::table('partner_reactivation_reviews');
        $review = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d", $review_id
        ));
        
        if (!$review) {
            return new WP_Error('not_found', 'Review request not found');
        }

        $org = self::get((int) $review->org_id);
        $frozen = self::assert_not_fraud_frozen($org);
        if (is_wp_error($frozen)) {
            return $frozen;
        }
        
        $wpdb->update(
            $table,
            ['reviewed_by' => $admin_id, 'reviewed_at' => current_time('mysql'), 'decision' => $decision, 'reviewer_notes' => $notes],
            ['id' => $review_id],
            ['%d', '%s', '%s', '%s'],
            ['%d']
        );
        
        if ($decision === 'approved') {
            $org_table = OraBooks_Database::table('organizations');
            $wpdb->update(
                $org_table,
                ['status' => 'active'],
                ['id' => $review->org_id],
                ['%s'],
                ['%d']
            );
            
            orabooks_log_event('partner_reactivation_approved', "Partner reactivation approved", 'info', [], $admin_id, $review->org_id);
            orabooks_log_event('partner_reactivated', 'Partner organization reactivated', 'info', [
                'review_id' => (int) $review_id,
            ], $admin_id, $review->org_id);
        } else {
            orabooks_log_event('partner_reactivation_denied', "Partner reactivation denied: $notes", 'warning', [
                'reason' => $notes
            ], $admin_id, $review->org_id);
        }
        
        return true;
    }
    
    /**
     * Get tier limits
     */
    private static function get_tier_limits($tier) {
        $limits = [
            'free' => ['api_calls' => 1000, 'storage_mb' => 100, 'users' => 3],
            'premium' => ['api_calls' => 10000, 'storage_mb' => 1024, 'users' => 20],
            'enterprise' => ['api_calls' => 100000, 'storage_mb' => 10240, 'users' => 100],
            'partner' => ['api_calls' => null, 'storage_mb' => null, 'users' => null],
        ];
        return $limits[$tier] ?? $limits['free'];
    }
    
    /**
     * Get all organizations (admin)
     */
    public static function get_all($args = []) {
        global $wpdb;
        
        $table = OraBooks_Database::table('organizations');
        $where = '1=1';
        $params = [];
        
        if (!empty($args['type'])) {
            $where .= ' AND organization_type = %s';
            $params[] = $args['type'];
        }
        if (!empty($args['tier'])) {
            $where .= ' AND tier = %s';
            $params[] = $args['tier'];
        }
        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $params[] = $args['status'];
        }
        if (!empty($args['search'])) {
            $where .= ' AND (name LIKE %s OR subdomain LIKE %s)';
            $params[] = '%' . $args['search'] . '%';
            $params[] = '%' . $args['search'] . '%';
        }
        
        $limit = $args['limit'] ?? 20;
        $offset = $args['offset'] ?? 0;
        
        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
}