<?php
/**
 * OraBooks Membership Permissions - Build Guide Compliant
 * 
 * Integrates membership levels with permission matrix for access control
 * Implements Role × Mode × Action validation as per build guide
 * Provides limited access system integration
 * 
 * @package OraBooks_Membership
 * @since 2.0.0
 */

if ( ! class_exists( 'OraBooks_Membership_Permissions' ) ) {
    class OraBooks_Membership_Permissions {
        
        private static $instance = null;
        
        /**
         * Membership Level to Role Mapping
         * Based on build guide hierarchy and permission matrix
         * All tiers supported: Free, Starter, Standard, Pro, Enterprise, Law, Faith
         */
        private static $level_role_mapping = [
            'free' => 'staff',
            'business_starter' => 'staff',
            'business_standard' => 'manager',
            'business_pro' => 'manager',
            'enterprise' => 'admin',
            'law_starter' => 'staff',
            'law_standard' => 'manager',
            'faith_starter' => 'staff',
            'faith_standard' => 'manager'
        ];
        
        /**
         * Feature to Action Mapping
         * Maps features to permission matrix actions
         * All features from tier plan document included
         */
        private static $feature_action_mapping = [
            'income_expense_tracking' => 'create_transaction',
            'unlimited_invoicing' => 'create_transaction',
            'invoices' => 'create_transaction',
            'quotes' => 'view_data',
            'bank_accounts' => 'view_data',
            'bank_connection' => 'view_data',
            'gst_vat_tracking' => 'view_data',
            'basic_reports' => 'generate_reports',
            'insights_reports' => 'generate_reports',
            'chart_of_accounts' => 'manage_chart_of_accounts',
            'time_tracking' => 'create_transaction',
            'progress_invoicing' => 'create_transaction',
            'ai_review_queue' => 'system_approval',
            'pwa_accessibility' => 'view_data',
            'dkim_email' => 'system_approval',
            'payment_gateway' => 'system_approval',
            'calendar_reminders' => 'system_approval',
            'recurring_invoices' => 'create_transaction',
            'fx_lite' => 'view_data',
            'tags_lite' => 'view_data',
            'time_lite' => 'create_transaction',
            'automation_reminders' => 'system_approval',
            'bills_payments' => 'create_transaction',
            'ar_aging' => 'view_data',
            'bank_rules' => 'system_approval',
            'payment_links' => 'create_transaction',
            'project_lite' => 'view_data',
            'auto_reorder' => 'system_approval',
            'inventory_lite' => 'view_data',
            'policy_filters' => 'system_approval',
            'tax_hints' => 'view_data',
            'notification_center' => 'view_data',
            'recurring_transactions' => 'create_transaction',
            'classes_locations' => 'manage_chart_of_accounts',
            'fx_multi_currency' => 'create_transaction',
            'custom_reports' => 'generate_reports',
            'sales_ops_kit' => 'view_data',
            'ai_speedups' => 'system_approval',
            'project_full' => 'create_transaction',
            'payroll_api' => 'system_approval',
            'dashboard_templates' => 'generate_reports',
            'ai_insight_panel' => 'generate_reports',
            'workflow_automations' => 'system_approval',
            'budgeting' => 'generate_reports',
            'extended_budgeting' => 'generate_reports',
            'advanced_reporting' => 'generate_reports',
            'matter_master' => 'view_data',
            'audit_evidence' => 'view_data',
            'attachments_versioning' => 'view_data',
            'core_reports' => 'generate_reports',
            'hearing_calendar' => 'view_data',
            'openapi_webhooks' => 'system_approval',
            'mobile_offline' => 'view_data',
            'policy_expense_filters' => 'system_approval',
            'case_analytics' => 'generate_reports',
            'automation_flows' => 'system_approval',
            'legal_calendar_sync' => 'view_data',
            'legal_data_sync' => 'view_data',
            'restricted_fund_accounting' => 'access_restricted_funds',
            'fund_program_reports' => 'generate_reports',
            'notification_basics' => 'view_data',
            'donation_gateway' => 'create_transaction',
            'recurring_pledges' => 'create_transaction',
            'regional_calendars' => 'view_data',
            'multi_currency' => 'create_transaction',
            'sheets_sync' => 'view_data',
            'event_campaign_budgeting' => 'generate_reports',
            'unlimited_coa' => 'manage_chart_of_accounts',
            'unlimited_classes_locations' => 'manage_chart_of_accounts',
            'excel_sheets_sync' => 'view_data',
            'custom_role_permissions' => 'user_management',
            'manage_users' => 'user_management',
            'automate_workflows' => 'system_approval',
            'custom_reporting_dashboards' => 'generate_reports',
            'backup_online_restore' => 'system_approval',
            'audit_evidence_export' => 'view_data',
            'multi_tenant_data_residency' => 'system_approval',
            'dr_resilience' => 'system_approval',
            'payments_vault_pci' => 'system_approval',
            'workflow_templates_library' => 'system_approval',
            'ai_forecast_models' => 'generate_reports',
            'scenario_simulation' => 'generate_reports',
            'app_marketplace' => 'system_approval',
            'receipt_capture' => 'create_transaction',
            'offline_entry' => 'create_transaction',
            'payment_notifications' => 'view_data',
            'mobile_dashboard' => 'view_data',
            'data_sync' => 'view_data',
            'storage' => 'view_data',
            'single_user' => 'user_management',
            'two_users' => 'user_management',
            'five_users' => 'user_management',
            'eight_users' => 'user_management',
            'ai_entries' => 'system_approval'
        ];
        
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        public function __construct() {
            add_action( 'init', [ $this, 'init_permission_integration' ] );
            add_filter( 'orabooks_user_can_access_feature', [ $this, 'check_feature_access' ], 10, 3 );
            add_filter( 'orabooks_get_user_features', [ $this, 'get_user_features' ], 10, 2 );
        }
        
        /**
         * Initialize permission integration
         */
        public function init_permission_integration() {
            // Ensure required classes are loaded
            if ( ! class_exists( 'OraBooks_Membership_Levels' ) ) {
                require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-membership-levels.php';
            }
            
            if ( ! class_exists( 'OraBooks_Permission_Matrix' ) ) {
                require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-permission-matrix.php';
            }
            
            if ( ! class_exists( 'OraBooks_Mode_Manager' ) ) {
                require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-mode-manager.php';
            }
        }
        
        /**
         * Check if user can access a feature
         * Build Guide Compliance: Role × Mode × Action validation
         * Uses OraBooks_Tier_Features as source of truth for feature availability
         */
        public function check_feature_access( $can_access, $user_id, $feature_key ) {
            // Get user's membership level
            $level_id = get_user_meta( $user_id, 'orabooks_level', true );
            if ( ! $level_id ) {
                return false;
            }
            
            $level_key = $level_id;
            if (is_numeric($level_id)) {
                $level = function_exists('orabooks_get_level') ? orabooks_get_level($level_id) : null;
                if ($level && function_exists('orabooks_guess_tier_key_from_level')) {
                    $level_key = orabooks_guess_tier_key_from_level($level);
                }
            }
            
            // Check if feature is available via Tier Features (source of truth)
            if ( class_exists( 'OraBooks_Tier_Features' ) ) {
                if ( ! OraBooks_Tier_Features::has_feature_access( $level_key, $feature_key ) ) {
                    return false;
                }
            } elseif ( ! OraBooks_Membership_Levels::is_feature_available( $level_key, $feature_key ) ) {
                return false;
            }
            
            // Get current mode
            $current_mode = OraBooks_Mode_Manager::get_current_mode();
            
            // Get level info to check mode compatibility
            $level_info = OraBooks_Membership_Levels::get_level_info( $level_key );
            if ( $level_info && $level_info['mode'] !== $current_mode && $level_info['mode'] !== 'all' ) {
                // Cross-mode access is forbidden (unless tier has mode 'all' i.e. free/enterprise)
                return false;
            }
            
            // Get user's role based on membership level
            $user_role = $this->get_user_role_from_level( $level_key );
            
            // Get action for this feature
            $action = $this->get_action_for_feature( $feature_key );
            if ( ! $action ) {
                $action = 'view_data'; // Default action
            }
            
            // Check permission matrix
            if ( class_exists( 'OraBooks_Permission_Matrix' ) ) {
                $result = OraBooks_Permission_Matrix::check_permission( $user_id, $user_role, $current_mode, $action );
                return $result['allowed'];
            }
            
            return true;
        }
        
        /**
         * Get user's role based on membership level
         */
        public function get_user_role_from_level( $level_key ) {
            return isset( self::$level_role_mapping[$level_key] ) ? 
                self::$level_role_mapping[$level_key] : 'staff';
        }
        
        /**
         * Get action for feature
         */
        public function get_action_for_feature( $feature_key ) {
            return isset( self::$feature_action_mapping[$feature_key] ) ? 
                self::$feature_action_mapping[$feature_key] : 'view_data';
        }
        
        /**
         * Get available features for user
         * Uses OraBooks_Tier_Features as source of truth
         */
        public function get_user_features( $features, $user_id ) {
            // Get user's membership level
            $level_id = get_user_meta( $user_id, 'orabooks_level', true );
            if ( ! $level_id ) {
                return [];
            }
            
            $level_key = $level_id;
            if (is_numeric($level_id)) {
                $level = function_exists('orabooks_get_level') ? orabooks_get_level($level_id) : null;
                if ($level && function_exists('orabooks_guess_tier_key_from_level')) {
                    $level_key = orabooks_guess_tier_key_from_level($level);
                }
            }
            
            // Get current mode
            $current_mode = OraBooks_Mode_Manager::get_current_mode();
            
            $user_features = [];
            
            // Get all available features from tier features (source of truth)
            if ( class_exists( 'OraBooks_Tier_Features' ) ) {
                $tier_restrictions = OraBooks_Tier_Features::get_tier_restrictions( $level_key );
                
                foreach ( $tier_restrictions as $feature_key => $restriction ) {
                    if ( $restriction['enabled'] && $restriction['access_level'] !== 'none' ) {
                        // Check permission matrix
                        if ( $this->check_feature_access( true, $user_id, $feature_key ) ) {
                            $user_features[$feature_key] = [
                                'name' => ucwords( str_replace( '_', ' ', $feature_key ) ),
                                'limit' => isset( $restriction['limit'] ) ? $restriction['limit'] : null,
                                'access_level' => $restriction['access_level'],
                                'description' => $this->get_feature_description( $feature_key )
                            ];
                        }
                    }
                }
            }
            
            return $user_features;
        }
        
        /**
         * Get feature description
         */
        private function get_feature_description( $feature_key ) {
            $descriptions = [
                'income_expense_tracking' => 'Track income and expenses',
                'unlimited_invoicing' => 'Send unlimited custom invoices',
                'invoices' => 'Create and manage invoices',
                'quotes' => 'Create and send quotes',
                'bank_accounts' => 'Connect and manage bank accounts',
                'bank_connection' => 'Connect your bank accounts',
                'gst_vat_tracking' => 'Track GST/VAT',
                'basic_reports' => 'Generate basic financial reports',
                'insights_reports' => 'Generate insights and reports',
                'chart_of_accounts' => 'Manage chart of accounts',
                'time_tracking' => 'Track time and generate timesheets',
                'progress_invoicing' => 'Create progress invoices',
                'ai_review_queue' => 'AI-powered review queue',
                'pwa_accessibility' => 'PWA installable & WCAG AA accessibility',
                'dkim_email' => 'DKIM email deliverability wizard',
                'payment_gateway' => 'Payment gateway integration',
                'calendar_reminders' => 'Smart calendar auto reminders',
                'recurring_invoices' => 'Recurring invoices',
                'fx_lite' => 'FX-lite (home currency)',
                'tags_lite' => 'Tags-lite filter (classes-lite)',
                'time_lite' => 'Time-lite CSV export',
                'automation_reminders' => 'Basic automation reminders',
                'bills_payments' => 'Manage bills and payments',
                'ar_aging' => 'AR aging and dunning',
                'bank_rules' => 'Bank rules and reconciliation',
                'payment_links' => 'In-app payment links',
                'project_lite' => 'Project-lite (task & timer)',
                'auto_reorder' => 'Auto reorder alerts',
                'inventory_lite' => 'Track inventory (lite)',
                'policy_filters' => 'Policy-based expense filters',
                'tax_hints' => 'Smart line-item tax hints',
                'notification_center' => 'Notification center',
                'recurring_transactions' => 'Recurring transactions',
                'classes_locations' => 'Classes and locations (full)',
                'fx_multi_currency' => 'FX multi-currency',
                'custom_reports' => 'Custom reporting fields',
                'sales_ops_kit' => 'Sales-ops kit (territory, agents, commission)',
                'ai_speedups' => 'AI speedups (OCR + tax hints)',
                'project_full' => 'Full project management',
                'payroll_api' => 'Payroll API-ready placeholder',
                'dashboard_templates' => 'Industry-based dashboard templates',
                'ai_insight_panel' => 'AI insight panel (forecast + explain-why)',
                'workflow_automations' => 'Workflow automations',
                'budgeting' => 'Basic budgeting tools',
                'extended_budgeting' => 'Extended budgeting controls',
                'advanced_reporting' => 'Advanced reporting',
                'matter_master' => 'Matter master (law-lite)',
                'audit_evidence' => 'Audit and evidence export',
                'attachments_versioning' => 'Attachments and versioning',
                'core_reports' => 'Core financial reports',
                'hearing_calendar' => 'Hearing calendar and notifications',
                'openapi_webhooks' => 'OpenAPI 3.1 + webhooks',
                'mobile_offline' => 'Mobile-first and offline mode',
                'policy_expense_filters' => 'Policy expense filters',
                'case_analytics' => 'Case analytics and precedent search',
                'automation_flows' => 'Advanced automation flows',
                'legal_calendar_sync' => 'Legal calendar sync',
                'legal_data_sync' => 'Legal data sync templates',
                'restricted_fund_accounting' => 'Restricted fund accounting',
                'fund_program_reports' => 'Fund and program reports',
                'notification_basics' => 'Basic notifications',
                'donation_gateway' => 'Donation gateway integration',
                'recurring_pledges' => 'Recurring pledges',
                'regional_calendars' => 'Regional and religious calendars',
                'multi_currency' => 'Full multi-currency',
                'sheets_sync' => 'Sheets/Excel sync',
                'event_campaign_budgeting' => 'Event campaign budgeting',
                'unlimited_coa' => 'Unlimited chart of accounts',
                'unlimited_classes_locations' => 'Unlimited classes and locations',
                'excel_sheets_sync' => 'Data sync with Excel/Sheets',
                'custom_role_permissions' => 'Custom role permissions',
                'manage_users' => 'Manage users (up to 25)',
                'automate_workflows' => 'Automate workflows',
                'custom_reporting_dashboards' => 'Custom reporting dashboards',
                'backup_online_restore' => 'Backup online and restore',
                'audit_evidence_export' => 'Audit and evidence export',
                'multi_tenant_data_residency' => 'Multi-tenant and data residency',
                'dr_resilience' => 'DR/resilience tested',
                'payments_vault_pci' => 'Payments vault and PCI minimization',
                'workflow_templates_library' => 'Workflow templates library',
                'ai_forecast_models' => 'AI forecast models',
                'scenario_simulation' => 'Scenario simulation dashboard',
                'app_marketplace' => 'App marketplace / developer portal',
                'receipt_capture' => 'Quick receipt capture',
                'offline_entry' => 'Offline data entry',
                'payment_notifications' => 'Push payment notifications',
                'mobile_dashboard' => 'Mobile-optimized dashboard',
                'data_sync' => 'Data sync',
                'storage' => 'Cloud storage',
                'single_user' => 'Single user',
                'two_users' => 'Two users plus accountant',
                'five_users' => 'Five users plus accountant',
                'eight_users' => 'Eight users plus accountant',
                'ai_entries' => 'AI-powered entries'
            ];
            
            return isset( $descriptions[$feature_key] ) ? $descriptions[$feature_key] : ucwords( str_replace( '_', ' ', $feature_key ) );
        }
        
        /**
         * Update user role when membership level changes
         */
        public function update_user_role_on_level_change( $user_id, $new_level_key ) {
            $new_role = $this->get_user_role_from_level( $new_level_key );
            
            // Update user meta
            update_user_meta( $user_id, 'orabooks_role', $new_role );
            
            // Log role change for audit trail
            if ( class_exists( 'OraBooks_Audit_Logger' ) ) {
                $logger = OraBooks_Audit_Logger::get_instance();
                $current_mode = OraBooks_Mode_Manager::get_current_mode();
                
                $logger->log_action([
                    'user_id' => $user_id,
                    'action_type' => 'role_updated',
                    'action_description' => sprintf( 'User role updated to %s due to membership level change', $new_role ),
                    'mode' => $current_mode,
                    'entity_type' => 'user',
                    'entity_id' => $user_id,
                    'after_state' => [
                        'new_role' => $new_role,
                        'level_key' => $new_level_key
                    ]
                ]);
            }
        }
        
        /**
         * Validate subscription access based on mode
         */
        public function validate_subscription_access( $user_id, $subscription_id ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Get subscription details
            $subscription = $wpdb->get_row( $wpdb->prepare(
                "SELECT s.*, l.mode, l.build_guide_level_key 
                 FROM {$wpdb->orabooks_subscriptions} s
                 LEFT JOIN {$wpdb->orabooks_levels} l ON s.level_id = l.id
                 WHERE s.id = %d",
                $subscription_id
            ) );
            
            if ( ! $subscription ) {
                return false;
            }
            
            // Check if subscription belongs to user
            if ( $subscription->user_id != $user_id ) {
                return false;
            }
            
            // Check if subscription is active
            if ( $subscription->status !== 'active' ) {
                return false;
            }
            
            // Check mode compatibility
            $current_mode = OraBooks_Mode_Manager::get_current_mode();
            if ( $subscription->mode && $subscription->mode !== $current_mode ) {
                return false; // Cross-mode access forbidden
            }
            
            return true;
        }
        
        /**
         * Get available levels for user based on current mode
         */
        public function get_available_levels_for_user( $user_id ) {
            $current_mode = OraBooks_Mode_Manager::get_current_mode();
            $available_levels = OraBooks_Membership_Levels::get_levels_by_mode( $current_mode );
            
            // Filter levels based on user's current permissions
            $user_accessible_levels = [];
            
            foreach ( $available_levels as $level_key => $level_info ) {
                // Check if user can access this level (for upgrades)
                if ( $this->can_user_access_level( $user_id, $level_key ) ) {
                    $user_accessible_levels[$level_key] = $level_info;
                }
            }
            
            return $user_accessible_levels;
        }
        
        /**
         * Check if user can access a specific level
         */
        private function can_user_access_level( $user_id, $level_key ) {
            // Get user's current level
            $current_level = get_user_meta( $user_id, 'orabooks_level', true );
            
            // If user has no current level, they can access free level
            if ( ! $current_level ) {
                return $level_key === 'free';
            }
            
            // Get tier ranks for comparison
            $current_rank = $this->get_level_tier_rank( $current_level );
            $target_rank = $this->get_level_tier_rank( $level_key );
            
            // Users can only access same or higher tiers (for upgrades)
            return $target_rank >= $current_rank;
        }
        
        /**
         * Get tier rank for level (all tiers supported)
         */
        private function get_level_tier_rank( $level_key ) {
            $ranks = [
                'free' => 0,
                'faith_starter' => 1,
                'law_starter' => 2,
                'business_starter' => 3,
                'faith_standard' => 4,
                'law_standard' => 5,
                'business_standard' => 6,
                'business_pro' => 7,
                'enterprise' => 8
            ];
            
            return isset( $ranks[$level_key] ) ? $ranks[$level_key] : 0;
        }
        
        /**
         * Enforce limited access based on membership level
         */
        public function enforce_limited_access( $user_id, $feature_key, $requested_count = 1 ) {
            // Get user's membership level
            $level_id = get_user_meta( $user_id, 'orabooks_level', true );
            if ( ! $level_id ) {
                return false;
            }
            
            $level_key = $level_id;
            if (is_numeric($level_id)) {
                $level = function_exists('orabooks_get_level') ? orabooks_get_level($level_id) : null;
                if ($level && function_exists('orabooks_guess_tier_key_from_level')) {
                    $level_key = orabooks_guess_tier_key_from_level($level);
                }
            }
            
            // Get feature limit
            $limit = OraBooks_Membership_Levels::get_feature_limit( $level_key, $feature_key );
            
            // If no limit, allow access
            if ( $limit === null || $limit === false ) {
                return true;
            }
            
            // If limit is 0, deny access
            if ( $limit === 0 ) {
                return false;
            }
            
            // Check current usage
            $current_usage = $this->get_feature_usage( $user_id, $feature_key );
            
            return $current_usage + $requested_count <= $limit;
        }
        
        /**
         * Get current feature usage for user
         */
        private function get_feature_usage( $user_id, $feature_key ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // This would need to be implemented based on specific feature tracking
            // For now, return 0 as placeholder
            return 0;
        }
        
        /**
         * Record feature usage for tracking
         */
        public function record_feature_usage( $user_id, $feature_key, $count = 1 ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // This would need to be implemented based on specific feature tracking
            // For now, just log the action
            if ( class_exists( 'OraBooks_Audit_Logger' ) ) {
                $logger = OraBooks_Audit_Logger::get_instance();
                $current_mode = OraBooks_Mode_Manager::get_current_mode();
                
                $logger->log_action([
                    'user_id' => $user_id,
                    'action_type' => 'feature_usage',
                    'action_description' => sprintf( 'Feature usage recorded: %s (%d units)', $feature_key, $count ),
                    'mode' => $current_mode,
                    'entity_type' => 'feature_usage',
                    'entity_id' => 0,
                    'after_state' => [
                        'feature_key' => $feature_key,
                        'count' => $count
                    ]
                ]);
            }
        }
    }
}

// Initialize the membership permissions system
OraBooks_Membership_Permissions::get_instance();
