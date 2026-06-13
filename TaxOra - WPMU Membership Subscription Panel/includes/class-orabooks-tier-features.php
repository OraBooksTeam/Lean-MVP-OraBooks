<?php
/**
 * OraBooks Tier Feature Mapping System
 * 
 * Maps features to tiers based on tier_plan_FINAL_.txt specifications
 * Each tier has specific SL (Service Level) codes that determine available features
 * 
 * @package OraBooks_Membership
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Tier_Features {
    
    /**
     * Get tier feature mappings based on SL codes from tier_plan_FINAL_.txt
     * 
     * @return array Tier configurations with feature mappings
     */
    public static function get_tier_feature_mappings() {
        return array(
            // OraBooks Free ($0 forever)
            'free' => array(
                'name' => 'OraBooks Free',
                'price' => 0,
                'description' => 'Basic accounting features for individuals forever',
                'mode' => 'all',
                'sl_codes' => array('SL-001', 'SL-021', 'SL-023', 'SL-031', 'SL-018', 'SL-081', 'SL-074', 'SL-075', 'SL-017', 'SL-014', 'SL-002', 'SL-028', 'SL-012', 'SL-250', 'SL-104'),
                'features' => array(
                    'income_expense_tracking' => array('enabled' => true, 'access_level' => 'full'),
                    'basic_invoicing' => array('enabled' => true, 'access_level' => 'limited', 'limit' => 5),
                    'basic_quotes' => array('enabled' => true, 'access_level' => 'limited', 'limit' => 3),
                    'bank_connection' => array('enabled' => true, 'access_level' => 'limited', 'limit' => 1),
                    'basic_gst_vat' => array('enabled' => true, 'access_level' => 'limited'),
                    'basic_reports' => array('enabled' => true, 'access_level' => 'readonly'),
                    'chart_of_accounts' => array('enabled' => true, 'access_level' => 'limited', 'limit' => 50),
                    'single_user' => array('enabled' => true, 'access_level' => 'full'),
                    'ai_entries' => array('enabled' => true, 'access_level' => 'limited', 'limit' => 10),
                    'receipt_capture' => array('enabled' => true, 'access_level' => 'full'),
                    'offline_entry' => array('enabled' => true, 'access_level' => 'full'),
                    'payment_notifications' => array('enabled' => true, 'access_level' => 'full'),
                    'mobile_dashboard' => array('enabled' => true, 'access_level' => 'full'),
                    'data_sync' => array('enabled' => true, 'access_level' => 'limited', 'limit' => 1000),
                    'storage' => array('enabled' => true, 'access_level' => 'limited', 'limit' => 100)
                )
            ),
            
            // Ora Starter ($4.99/month) — also accessible as 'business_starter'
            'starter' => array(
                'name' => 'Ora Starter',
                'price' => 4.99,
                'description' => 'Essential accounting and business features',
                'mode' => 'business',
                'aliases' => array('business_starter'),
                'sl_codes' => array('SL-001', 'SL-021', 'SL-023', 'SL-031', 'SL-018', 'SL-081', 'SL-074', 'SL-075', 'SL-017', 'SL-085', 'SL-002', 'SL-012', 'SL-011', 'SL-117'),
                'features' => array(
                    'income_expense_tracking' => array('enabled' => true, 'access_level' => 'full'),
                    'unlimited_invoicing' => array('enabled' => true, 'access_level' => 'full'),
                    'bank_connection' => array('enabled' => true, 'access_level' => 'full'),
                    'gst_vat_tracking' => array('enabled' => true, 'access_level' => 'full'),
                    'insights_reports' => array('enabled' => true, 'access_level' => 'full'),
                    'progress_invoicing' => array('enabled' => true, 'access_level' => 'full'),
                    'chart_of_accounts' => array('enabled' => true, 'access_level' => 'limited', 'limit' => 250),
                    'time_tracking' => array('enabled' => true, 'access_level' => 'limited'),
                    'two_users' => array('enabled' => true, 'access_level' => 'full'),
                    'ai_review_queue' => array('enabled' => true, 'access_level' => 'full'),
                    'pwa_accessibility' => array('enabled' => true, 'access_level' => 'full'),
                    'dkim_email' => array('enabled' => true, 'access_level' => 'full'),
                    'payment_gateway' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'calendar_reminders' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'recurring_invoices' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'fx_lite' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'tags_lite' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'time_lite' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'automation_reminders' => array('enabled' => false, 'access_level' => 'none') // Opt-in
                )
            ),
            
            // Ora Standard ($7.49/month) — also accessible as 'business_standard'
            'standard' => array(
                'name' => 'Ora Standard',
                'price' => 7.49,
                'description' => 'Complete business accounting with inventory',
                'mode' => 'business',
                'aliases' => array('business_standard'),
                'sl_codes' => array('SL-027', 'SL-023', 'SL-031', 'SL-018', 'SL-081', 'SL-074', 'SL-075', 'SL-085', 'SL-097', 'SL-034', 'SL-047', 'SL-022', 'SL-250', 'SL-219'),
                'features' => array(
                    'bills_payments' => array('enabled' => true, 'access_level' => 'full'),
                    'ar_aging' => array('enabled' => true, 'access_level' => 'full'),
                    'bank_rules' => array('enabled' => true, 'access_level' => 'full'),
                    'payment_links' => array('enabled' => true, 'access_level' => 'full'),
                    'project_lite' => array('enabled' => true, 'access_level' => 'limited'),
                    'auto_reorder' => array('enabled' => true, 'access_level' => 'full'),
                    'inventory_lite' => array('enabled' => true, 'access_level' => 'limited'),
                    'chart_of_accounts' => array('enabled' => true, 'access_level' => 'limited', 'limit' => 500),
                    'five_users' => array('enabled' => true, 'access_level' => 'full'),
                    'policy_filters' => array('enabled' => true, 'access_level' => 'full'),
                    'tax_hints' => array('enabled' => true, 'access_level' => 'full'),
                    'notification_center' => array('enabled' => true, 'access_level' => 'full'),
                    'recurring_transactions' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'fx_lite' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'classes_locations' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'time_tracking' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'automation_flows' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'budgeting' => array('enabled' => false, 'access_level' => 'none') // Opt-in
                )
            ),
            
            // Ora Pro ($13.49/month) — also accessible as 'business_pro'
            'pro' => array(
                'name' => 'Ora Pro',
                'price' => 13.49,
                'description' => 'Advanced features with AI and automation',
                'mode' => 'business',
                'aliases' => array('business_pro'),
                'sl_codes' => array('SL-021', 'SL-023', 'SL-018', 'SL-081', 'SL-017', 'SL-085', 'SL-097', 'SL-066', 'SL-067', 'SL-068', 'SL-028', 'SL-076'),
                'features' => array(
                    'recurring_transactions' => array('enabled' => true, 'access_level' => 'full'),
                    'classes_locations' => array('enabled' => true, 'access_level' => 'full'),
                    'fx_multi_currency' => array('enabled' => true, 'access_level' => 'full'),
                    'inventory_lite' => array('enabled' => true, 'access_level' => 'limited'),
                    'chart_of_accounts' => array('enabled' => true, 'access_level' => 'limited', 'limit' => 1500),
                    'custom_reports' => array('enabled' => true, 'access_level' => 'full'),
                    'eight_users' => array('enabled' => true, 'access_level' => 'full'),
                    'sales_ops_kit' => array('enabled' => true, 'access_level' => 'full'),
                    'ai_speedups' => array('enabled' => true, 'access_level' => 'full'),
                    'project_full' => array('enabled' => true, 'access_level' => 'full'),
                    'payroll_api' => array('enabled' => true, 'access_level' => 'full'),
                    'dashboard_templates' => array('enabled' => true, 'access_level' => 'full'),
                    'ai_insight_panel' => array('enabled' => true, 'access_level' => 'full'),
                    'workflow_automations' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'extended_budgeting' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'advanced_reporting' => array('enabled' => false, 'access_level' => 'none') // Opt-in
                )
            ),
            
            // Law Starter ($3.99/month)
            'law_starter' => array(
                'name' => 'Law Starter',
                'price' => 3.99,
                'description' => 'Legal practice management basics',
                'mode' => 'law',
                'sl_codes' => array('SL-037', 'SL-009', 'SL-203', 'SL-074', 'SL-075', 'SL-012', 'SL-011', 'SL-117', 'SL-250'),
                'features' => array(
                    'matter_master' => array('enabled' => true, 'access_level' => 'full'),
                    'audit_evidence' => array('enabled' => true, 'access_level' => 'full'),
                    'attachments_versioning' => array('enabled' => true, 'access_level' => 'full'),
                    'core_reports' => array('enabled' => true, 'access_level' => 'full'),
                    'pwa_accessibility' => array('enabled' => true, 'access_level' => 'full'),
                    'dkim_email' => array('enabled' => true, 'access_level' => 'full'),
                    'hearing_calendar' => array('enabled' => true, 'access_level' => 'full'),
                    'chart_of_accounts' => array('enabled' => true, 'access_level' => 'limited', 'limit' => 250),
                    'two_users' => array('enabled' => true, 'access_level' => 'full'),
                    'case_analytics' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'offline_case_mode' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'webhook_events' => array('enabled' => false, 'access_level' => 'none') // Opt-in
                )
            ),
            
            // Law Standard ($5.99/month)
            'law_standard' => array(
                'name' => 'Law Standard',
                'price' => 5.99,
                'description' => 'Complete legal practice management',
                'mode' => 'law',
                'sl_codes' => array('SL-037', 'SL-009', 'SL-203', 'SL-076', 'SL-010', 'SL-104', 'SL-047', 'SL-250'),
                'features' => array(
                    'ai_review_queue' => array('enabled' => true, 'access_level' => 'full'),
                    'openapi_webhooks' => array('enabled' => true, 'access_level' => 'full'),
                    'mobile_offline' => array('enabled' => true, 'access_level' => 'full'),
                    'policy_expense_filters' => array('enabled' => true, 'access_level' => 'full'),
                    'notification_center' => array('enabled' => true, 'access_level' => 'full'),
                    'chart_of_accounts' => array('enabled' => true, 'access_level' => 'limited', 'limit' => 500),
                    'five_users' => array('enabled' => true, 'access_level' => 'full'),
                    'case_analytics' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'automation_flows' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'legal_calendar_sync' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'legal_data_sync' => array('enabled' => false, 'access_level' => 'none') // Opt-in
                )
            ),
            
            // Faith Starter ($2.99/month)
            'faith_starter' => array(
                'name' => 'Faith Starter',
                'price' => 2.99,
                'description' => 'Religious organization accounting basics',
                'mode' => 'faith',
                'sl_codes' => array('SL-045', 'SL-074', 'SL-075', 'SL-012', 'SL-011', 'SL-117', 'SL-250'),
                'features' => array(
                    'restricted_fund_accounting' => array('enabled' => true, 'access_level' => 'full'),
                    'fund_program_reports' => array('enabled' => true, 'access_level' => 'full'),
                    'pwa_accessibility' => array('enabled' => true, 'access_level' => 'full'),
                    'dkim_email' => array('enabled' => true, 'access_level' => 'full'),
                    'notification_basics' => array('enabled' => true, 'access_level' => 'limited'),
                    'chart_of_accounts' => array('enabled' => true, 'access_level' => 'limited', 'limit' => 250),
                    'two_users' => array('enabled' => true, 'access_level' => 'full'),
                    'donation_gateway' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'calendar_reminders' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'fx_lite' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'recurring_pledges' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'tags_lite' => array('enabled' => false, 'access_level' => 'none') // Opt-in
                )
            ),
            
            // Faith Standard ($4.99/month)
            'faith_standard' => array(
                'name' => 'Faith Standard',
                'price' => 4.99,
                'description' => 'Complete religious organization management',
                'mode' => 'faith',
                'sl_codes' => array('SL-045', 'SL-074', 'SL-075', 'SL-076', 'SL-047', 'SL-282', 'SL-010', 'SL-104'),
                'features' => array(
                    'ai_review_queue' => array('enabled' => true, 'access_level' => 'full'),
                    'regional_calendars' => array('enabled' => true, 'access_level' => 'full'),
                    'openapi_webhooks' => array('enabled' => true, 'access_level' => 'full'),
                    'mobile_offline' => array('enabled' => true, 'access_level' => 'full'),
                    'chart_of_accounts' => array('enabled' => true, 'access_level' => 'limited', 'limit' => 500),
                    'five_users' => array('enabled' => true, 'access_level' => 'full'),
                    'donation_gateway' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'calendar_reminders' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'automation_flows' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'multi_currency' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'sheets_sync' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'event_campaign_budgeting' => array('enabled' => false, 'access_level' => 'none') // Opt-in
                )
            ),

            // Ora Enterprise (Contact sales)
            'enterprise' => array(
                'name' => 'Ora Enterprise',
                'price' => 0, // Contact sales
                'description' => 'Enterprise-grade accounting with unlimited everything',
                'mode' => 'all',
                'sl_codes' => array('SL-017', 'SL-004', 'SL-085', 'SL-074', 'SL-075', 'SL-095', 'SL-010', 'SL-009', 'SL-219', 'SL-076', 'SL-203'),
                'features' => array(
                    'unlimited_coa' => array('enabled' => true, 'access_level' => 'full'),
                    'unlimited_classes_locations' => array('enabled' => true, 'access_level' => 'full'),
                    'excel_sheets_sync' => array('enabled' => true, 'access_level' => 'full'),
                    'custom_role_permissions' => array('enabled' => true, 'access_level' => 'full'),
                    'manage_users' => array('enabled' => true, 'access_level' => 'full', 'limit' => 25),
                    'automate_workflows' => array('enabled' => true, 'access_level' => 'full'),
                    'custom_reporting_dashboards' => array('enabled' => true, 'access_level' => 'full'),
                    'backup_online_restore' => array('enabled' => true, 'access_level' => 'full'),
                    'openapi_webhooks' => array('enabled' => true, 'access_level' => 'full'),
                    'audit_evidence_export' => array('enabled' => true, 'access_level' => 'full'),
                    'multi_tenant_data_residency' => array('enabled' => true, 'access_level' => 'full'),
                    'dr_resilience' => array('enabled' => true, 'access_level' => 'full'),
                    'payments_vault_pci' => array('enabled' => true, 'access_level' => 'full'),
                    'workflow_templates_library' => array('enabled' => true, 'access_level' => 'full'),
                    'ai_forecast_models' => array('enabled' => true, 'access_level' => 'full'),
                    'scenario_simulation' => array('enabled' => true, 'access_level' => 'full'),
                    'app_marketplace' => array('enabled' => true, 'access_level' => 'full'),
                    'advanced_workflow_scheduler' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'cross_region_data_residency' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'multi_zone_backup_audit' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'granular_webhook_routing' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'advanced_visual_dashboards' => array('enabled' => false, 'access_level' => 'none'), // Opt-in
                    'custom_role_templates' => array('enabled' => false, 'access_level' => 'none') // Opt-in
                )
            )
        );
    }
    
    /**
     * Get feature restrictions for a specific tier (resolves aliases)
     * 
     * @param string $tier_key Tier identifier
     * @return array Feature restrictions for the tier
     */
    public static function get_tier_restrictions($tier_key) {
        $canonical = self::resolve_alias($tier_key);
        $mappings = self::get_tier_feature_mappings();
        $base = isset($mappings[$canonical]) ? $mappings[$canonical]['features'] : array();

        $overrides = get_option('orabooks_tier_restrictions_' . $canonical, array());
        if (empty($overrides) && $canonical !== $tier_key) {
            $overrides = get_option('orabooks_tier_restrictions_' . $tier_key, array());
        }

        if (empty($overrides) || !is_array($overrides)) {
            return $base;
        }

        $merged = $base;
        foreach ($overrides as $feature_key => $override) {
            if (!is_array($override)) {
                continue;
            }
            if (isset($merged[$feature_key])) {
                if (array_key_exists('enabled', $override)) {
                    $merged[$feature_key]['enabled'] = (bool) $override['enabled'];
                }
                if (!empty($override['access_level'])) {
                    $merged[$feature_key]['access_level'] = sanitize_text_field($override['access_level']);
                }
            } else {
                $merged[$feature_key] = array(
                    'enabled' => !empty($override['enabled']),
                    'access_level' => !empty($override['access_level']) ? sanitize_text_field($override['access_level']) : 'full',
                );
            }
        }

        return $merged;
    }

    /**
     * All tier keys that map to the same canonical tier (canonical + aliases).
     *
     * @param string $tier_key Tier identifier
     * @return array Unique tier keys
     */
    public static function get_equivalent_tier_keys($tier_key) {
        $canonical = self::resolve_alias($tier_key);
        $keys = array($canonical, $tier_key);
        $mappings = self::get_tier_feature_mappings();

        if (isset($mappings[$canonical]['aliases']) && is_array($mappings[$canonical]['aliases'])) {
            $keys = array_merge($keys, $mappings[$canonical]['aliases']);
        }

        return array_values(array_unique(array_filter($keys)));
    }
    
    /**
     * Check if a feature is available in a tier (resolves aliases)
     * 
     * @param string $tier_key Tier identifier
     * @param string $feature_key Feature identifier
     * @return bool Whether feature is available
     */
    public static function is_feature_available($tier_key, $feature_key) {
        $restrictions = self::get_tier_restrictions($tier_key);
        return isset($restrictions[$feature_key]) && $restrictions[$feature_key]['enabled'];
    }
    
    /**
     * Get access level for a feature in a tier (resolves aliases)
     * 
     * @param string $tier_key Tier identifier
     * @param string $feature_key Feature identifier
     * @return string Access level (full, limited, readonly, none)
     */
    public static function get_feature_access_level($tier_key, $feature_key) {
        $restrictions = self::get_tier_restrictions($tier_key);
        return isset($restrictions[$feature_key]) ? $restrictions[$feature_key]['access_level'] : 'none';
    }
    
    /**
     * Get feature limit for a tier (if applicable, resolves aliases)
     * 
     * @param string $tier_key Tier identifier
     * @param string $feature_key Feature identifier
     * @return int|false Limit value or false if no limit
     */
    public static function get_feature_limit($tier_key, $feature_key) {
        $restrictions = self::get_tier_restrictions($tier_key);
        return isset($restrictions[$feature_key]['limit']) ? $restrictions[$feature_key]['limit'] : false;
    }
    
    /**
     * Apply tier restrictions to available features (resolves aliases)
     * 
     * @param string $tier_key Tier identifier
     * @param array $available_features Available features from addons
     * @return array Filtered features based on tier restrictions
     */
    public static function apply_tier_restrictions($tier_key, $available_features) {
        $canonical = self::resolve_alias($tier_key);
        $tier_restrictions = self::get_tier_restrictions($canonical);
        $filtered_features = array();
        
        foreach ($available_features as $feature_key => $feature_data) {
            // Check if this feature should be restricted based on tier
            if (isset($tier_restrictions[$feature_key])) {
                $restriction = $tier_restrictions[$feature_key];
                
                if ($restriction['enabled']) {
                    $filtered_features[$feature_key] = $feature_data;
                    $filtered_features[$feature_key]['tier_access_level'] = $restriction['access_level'];
                    $filtered_features[$feature_key]['tier_limit'] = isset($restriction['limit']) ? $restriction['limit'] : false;
                }
            }
        }
        
        return $filtered_features;
    }
    
    /**
     * Get all tier configurations for admin interface
     * 
     * @return array All tier configurations
     */
    public static function get_all_tiers() {
        $mappings = self::get_tier_feature_mappings();
        $tiers = array();
        
        foreach ($mappings as $tier_key => $tier_data) {
            $tiers[$tier_key] = array(
                'name' => $tier_data['name'],
                'price' => $tier_data['price'],
                'description' => $tier_data['description'],
                'mode' => $tier_data['mode'],
                'sl_codes' => $tier_data['sl_codes'],
                'rank' => self::get_tier_rank($tier_key)
            );
        }
        
        return $tiers;
    }
    
    /**
     * Get tier rank for ordering (higher = better)
     * 
     * @param string $tier_key Tier identifier
     * @return int Rank value
     */
    public static function get_tier_rank($tier_key) {
        $ranks = array(
            'free' => 0,
            'faith_starter' => 1,
            'law_starter' => 2,
            'starter' => 3,
            'faith_standard' => 4,
            'law_standard' => 5,
            'standard' => 6,
            'pro' => 7,
            'enterprise' => 8
        );
        
        return isset($ranks[$tier_key]) ? $ranks[$tier_key] : 0;
    }
    
    /**
     * Check if a user's tier has access to a specific feature by feature key
     * 
     * @param string $tier_key Tier identifier
     * @param string $feature_key Feature identifier
     * @return bool Whether feature is available (enabled and access_level != 'none')
     */
    public static function has_feature_access($tier_key, $feature_key) {
        $restrictions = self::get_tier_restrictions($tier_key);
        
        // Direct check
        if (isset($restrictions[$feature_key])) {
            return $restrictions[$feature_key]['enabled'] && $restrictions[$feature_key]['access_level'] !== 'none';
        }
        
        return false;
    }
    
    /**
     * Resolve a tier key to its canonical form, checking aliases.
     * e.g. 'business_starter' -> 'starter', 'business_standard' -> 'standard', 'business_pro' -> 'pro'
     * 
     * @param string $tier_key Possibly-aliased tier key
     * @return string Canonical tier key (unchanged if no alias found)
     */
    public static function resolve_alias($tier_key) {
        $mappings = self::get_tier_feature_mappings();
        
        // Direct match first
        if (isset($mappings[$tier_key])) {
            return $tier_key;
        }
        
        // Check aliases
        foreach ($mappings as $canonical_key => $tier_data) {
            if (isset($tier_data['aliases']) && in_array($tier_key, $tier_data['aliases'])) {
                return $canonical_key;
            }
        }
        
        return $tier_key;
    }
    
    /**
     * Get tier data, resolving aliases automatically
     * 
     * @param string $tier_key Tier identifier (canonical or alias)
     * @return array|null Tier data or null if not found
     */
    public static function get_tier_data($tier_key) {
        $canonical = self::resolve_alias($tier_key);
        $mappings = self::get_tier_feature_mappings();
        return isset($mappings[$canonical]) ? $mappings[$canonical] : null;
    }
    
    /**
     * Get all unique feature keys across all tiers
     * 
     * @return array All feature keys
     */
    public static function get_all_feature_keys() {
        $mappings = self::get_tier_feature_mappings();
        $all_keys = array();
        
        foreach ($mappings as $tier_data) {
            foreach ($tier_data['features'] as $feature_key => $feature_data) {
                if (!in_array($feature_key, $all_keys)) {
                    $all_keys[] = $feature_key;
                }
            }
        }
        
        return $all_keys;
    }
    
    /**
     * Get the highest tier that has a feature
     * 
     * @param string $feature_key Feature identifier
     * @return string|null Tier key or null if not found
     */
    public static function get_minimum_tier_for_feature($feature_key) {
        $mappings = self::get_tier_feature_mappings();
        $lowest_rank = PHP_INT_MAX;
        $lowest_tier = null;
        
        foreach ($mappings as $tier_key => $tier_data) {
            if (isset($tier_data['features'][$feature_key]) && 
                $tier_data['features'][$feature_key]['enabled'] &&
                $tier_data['features'][$feature_key]['access_level'] !== 'none') {
                $rank = self::get_tier_rank($tier_key);
                if ($rank < $lowest_rank) {
                    $lowest_rank = $rank;
                    $lowest_tier = $tier_key;
                }
            }
        }
        
        return $lowest_tier;
    }
}
