<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * OraBooks Subscription Plans - Build Guide Compliant
 * 
 * Manages subscription plans according to ORABOOKS_ULTIMATE_BUILD_GUIDE_A_TO_Z.md
 * Implements proper pricing and features for each tier
 * Mode-aware subscription management
 * 
 * @package OraBooks_Membership
 * @since 2.0.0
 */

if ( ! class_exists( 'OraBooks_Subscription_Plans' ) ) {
    class OraBooks_Subscription_Plans {
        
        private static $instance = null;
        
        /**
         * Complete Subscription Plans matching tier plan document
         * All tiers included: Free, Business (Starter/Standard/Pro), Enterprise, Law, Faith
         * Features and pricing match tier_plan_FINAL_.txt specification
         */
        private static $subscription_plans = [
            'free' => [
                'name' => 'OraBooks Free',
                'price' => 0,
                'billing_period' => 'monthly',
                'currency' => 'USD',
                'trial_days' => 0,
                'setup_fee' => 0.00,
                'mode' => 'business',
                'tier_rank' => 0,
                'tier_key' => 'free',
                'features' => [
                    'Track income & expenses',
                    'Send 5 invoices & 3 quotes/month',
                    'Connect 1 bank account',
                    'Basic GST/VAT tracking',
                    'Basic insights & reports',
                    'Up to 50 Chart of Accounts items',
                    '1 user only',
                    'Limited AI entries (10/month)',
                    'Quick receipt capture',
                    'Offline data entry (sync when online)',
                    'Push notifications for invoice payments',
                    'Mobile-optimized dashboard',
                    '100MB storage limit',
                    '1000 API requests/month'
                ],
                'opt_in_features' => []
            ],
            'business_starter' => [
                'name' => 'Business Starter',
                'price' => 4.99,
                'billing_period' => 'monthly',
                'currency' => 'USD',
                'trial_days' => 14,
                'setup_fee' => 0.00,
                'mode' => 'business',
                'tier_rank' => 3,
                'tier_key' => 'starter',
                'features' => [
                    'Track income & expenses',
                    'Send unlimited custom invoices & quotes',
                    'Connect your bank — Region-aware',
                    'Track GST & VAT — Region-aware',
                    'Insights & reports',
                    'Progress invoicing',
                    'Up to 250 items in Chart of Accounts',
                    'Basic Time Tracking (timesheet + approve)',
                    'For Two users, plus your accountant',
                    'AI Entry Badge + Review Queue (approve-before-post)',
                    'PWA (installable) & Accessibility (WCAG AA)',
                    'DKIM Email Wizard (deliverability boost)'
                ],
                'opt_in_features' => [
                    'Payment Gateway',
                    'Smart Calendar Auto Reminders',
                    'Recurring Invoices (only)',
                    'FX-lite (home currency only + report disclosure)',
                    'Tags-lite filter (classes-lite)',
                    'Time-lite (CSV timesheet + one-click approve)',
                    'Basic Automation Reminders (late invoice follow-up)'
                ]
            ],
            'business_standard' => [
                'name' => 'Business Standard',
                'price' => 7.49,
                'billing_period' => 'monthly',
                'currency' => 'USD',
                'trial_days' => 14,
                'setup_fee' => 0.00,
                'mode' => 'business',
                'tier_rank' => 6,
                'tier_key' => 'standard',
                'features' => [
                    'Everything in Starter',
                    'Manage bills & payments',
                    'AR aging & reminders (basic dunning)',
                    'Bank rules + Reconcile',
                    'In-app Payment Link (Stripe/SSLCommerz integration)',
                    'Project-lite (Task & Timer Summary)',
                    'Auto Reorder Alert (Inventory trigger)',
                    'Track Inventory (Lite)',
                    'Up to 500 items in Chart of Accounts',
                    'For Five users, plus your accountant',
                    'Policy-based expense filters',
                    'Smart line-item tax hints',
                    'Configurable dunning + Notification Center'
                ],
                'opt_in_features' => [
                    'Recurring Transactions (invoices + bills)',
                    'FX-lite (multi-currency conversion without posting)',
                    'Classes/Locations-lite (filter view only)',
                    'Time Tracking (Lite UI version)',
                    'Automation Flows (Template-based triggers)',
                    'Budgeting (Basic Planning Tools)'
                ]
            ],
            'business_pro' => [
                'name' => 'Business Pro',
                'price' => 13.49,
                'billing_period' => 'monthly',
                'currency' => 'USD',
                'trial_days' => 14,
                'setup_fee' => 0.00,
                'mode' => 'business',
                'tier_rank' => 7,
                'tier_key' => 'pro',
                'features' => [
                    'Everything in Standard',
                    'Recurring (invoices + bills, multi-entity)',
                    'Classes & Locations (Full)',
                    'Track Inventory (Lite)',
                    'Up to 1,500 items in Chart of Accounts',
                    'Custom reporting fields & dashboards',
                    'For Eight users, plus your accountant',
                    'Sales-Ops kit: Territory & Agents, Deal Registration, Commission',
                    'AI speedups: OCR + tax hints (deeper)',
                    'Project (Full) – Billable Task + Time Sync',
                    'Payroll API-ready placeholder (future SL-010 hook)',
                    'Dashboard Templates (industry-based reporting)',
                    'AI Insight Panel (Forecast + Explain-Why)'
                ],
                'opt_in_features' => [
                    'FX (Full multi-currency extended)',
                    'Budgeting (Extended Controls)',
                    'Workflow Automations (Conditional rules)',
                    'Advanced Reporting (custom fields + dashboards)'
                ]
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'price' => 0,
                'billing_period' => 'monthly',
                'currency' => 'USD',
                'trial_days' => 7,
                'setup_fee' => 0.00,
                'mode' => 'business',
                'tier_rank' => 8,
                'tier_key' => 'enterprise',
                'features' => [
                    'Everything in Pro',
                    'UNLIMITED items in Chart of Accounts',
                    'UNLIMITED classes and locations',
                    'Data sync with Excel/Sheets',
                    'Custom role permissions; Manage users (up to 25)',
                    'Automate workflows',
                    'Custom reporting fields & dashboards',
                    'Backup online & restore data',
                    'OpenAPI 3.1 + HMAC Webhooks',
                    'Audit & Evidence export',
                    'Multi-tenant & Data Residency controls',
                    'DR/Resilience tested',
                    'Payments vault & PCI-scope minimization',
                    'Workflow Templates Library (ready-made automations)',
                    'AI Forecast Models (Revenue, Cost, Cashflow)',
                    'Scenario Simulation Dashboard (sandbox planning)',
                    'App Marketplace / Developer Portal'
                ],
                'opt_in_features' => [
                    'Advanced Workflow Scheduler (beyond base automation)',
                    'Cross-region Data Residency Expansion (multi-jurisdiction)',
                    'Multi-zone Backup & Legal Audit Trails (enhanced compliance)',
                    'Granular Webhook Routing (multi-org filters)',
                    'Advanced Visual Dashboards (custom report builder extensions)',
                    'Custom Role Templates (enterprise-grade configuration)'
                ]
            ],
            'law_starter' => [
                'name' => 'Law Starter',
                'price' => 3.99,
                'billing_period' => 'monthly',
                'currency' => 'USD',
                'trial_days' => 14,
                'setup_fee' => 0.00,
                'mode' => 'law',
                'tier_rank' => 2,
                'tier_key' => 'law_starter',
                'features' => [
                    'Matter Master (Law-Lite)',
                    'Audit & Evidence (export-ready)',
                    'Attachments & Versioning (AV scan, signed URLs)',
                    'Core Reports (IS/BS/CF/TB/SoCE)',
                    'PWA / Accessibility',
                    'DKIM/Email Ready',
                    'Hearing Calendar & Notifications',
                    'CoA up to 250; Two user + accountant'
                ],
                'opt_in_features' => [
                    'Case Analytics / Precedent Search (AI Doc Sync)',
                    'Offline Case Mode + Hearing-day capture pack',
                    'Webhook Events (client/matter)'
                ]
            ],
            'law_standard' => [
                'name' => 'Law Standard',
                'price' => 5.99,
                'billing_period' => 'monthly',
                'currency' => 'USD',
                'trial_days' => 14,
                'setup_fee' => 0.00,
                'mode' => 'law',
                'tier_rank' => 5,
                'tier_key' => 'law_standard',
                'features' => [
                    'All Law Starter features',
                    'AI Review Queue',
                    'OpenAPI 3.1 + Webhooks (HMAC/Idempotency)',
                    'Mobile-first Nav & Offline (hearing-day offline kit)',
                    'Policy-based Expense Filters (compliance guardrails)',
                    'Notification Center & Preferences',
                    'CoA up to 500; Five users + accountant'
                ],
                'opt_in_features' => [
                    'Case Analytics / Precedent Search (AI Doc Sync)',
                    'Advanced Automation Flows',
                    'Legal Calendar Sync',
                    'Legal Data Sync Templates (Law vertical extension)'
                ]
            ],
            'faith_starter' => [
                'name' => 'Faith Starter',
                'price' => 2.99,
                'billing_period' => 'monthly',
                'currency' => 'USD',
                'trial_days' => 14,
                'setup_fee' => 0.00,
                'mode' => 'faith',
                'tier_rank' => 1,
                'tier_key' => 'faith_starter',
                'features' => [
                    'Restricted Fund Accounting (Basics)',
                    'Fund/Program Reports (core statements alongside)',
                    'PWA / Accessibility',
                    'DKIM/Email Ready',
                    'Notification Basics',
                    'CoA up to 250; two user + accountant'
                ],
                'opt_in_features' => [
                    'Donation App Connector / Payment Gateway',
                    'Smart Calendar Auto Reminders',
                    'FX-lite (Faith vertical restricted mode)',
                    'Recurring for Pledges',
                    'Tags-lite filter (Fund tags only)'
                ]
            ],
            'faith_standard' => [
                'name' => 'Faith Standard',
                'price' => 4.99,
                'billing_period' => 'monthly',
                'currency' => 'USD',
                'trial_days' => 14,
                'setup_fee' => 0.00,
                'mode' => 'faith',
                'tier_rank' => 4,
                'tier_key' => 'faith_standard',
                'features' => [
                    'All Faith Starter features',
                    'AI Review Queue + Policy Filters (Sharia/compliance)',
                    'Regional & Religious Calendars (events/periods)',
                    'OpenAPI 3.1 + Webhooks',
                    'Mobile-first Nav & Offline (field activities)',
                    'CoA up to 500; 5 users + accountant'
                ],
                'opt_in_features' => [
                    'Donation App Connector / Payment Gateway',
                    'Smart Calendar Auto Reminders',
                    'Automation Flows for Donations',
                    'Multi-Currency (Full)',
                    'Sheets/Excel Sync',
                    'Event Campaign Budgeting'
                ]
            ]
        ];
        
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        public function __construct() {
            add_action( 'init', [ $this, 'init_subscription_plans' ] );
            add_action( 'admin_init', [ $this, 'sync_plans_with_levels' ] );
        }
        
        /**
         * Initialize subscription plans
         */
        public function init_subscription_plans() {
            // Ensure required classes are loaded
            if ( ! class_exists( 'OraBooks_Membership_Levels' ) ) {
                require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-membership-levels.php';
            }
        }
        
        /**
         * Get all subscription plans
         */
        public static function get_all_plans() {
            return self::$subscription_plans;
        }
        
        /**
         * Get plan by key
         */
        public static function get_plan( $plan_key ) {
            return isset( self::$subscription_plans[$plan_key] ) ? self::$subscription_plans[$plan_key] : null;
        }
        
        /**
         * Get plans by mode
         */
        public static function get_plans_by_mode( $mode ) {
            $plans = [];
            foreach ( self::$subscription_plans as $key => $plan ) {
                if ( $plan['mode'] === $mode ) {
                    $plans[$key] = $plan;
                }
            }
            
            // Sort by tier rank
            uasort( $plans, function( $a, $b ) {
                return $a['tier_rank'] - $b['tier_rank'];
            });
            
            return $plans;
        }
        
        /**
         * Get available plans for current mode
         */
        public static function get_available_plans() {
            $current_mode = class_exists( 'OraBooks_Mode_Manager' ) ? 
                OraBooks_Mode_Manager::get_current_mode() : 'business';
            
            return self::get_plans_by_mode( $current_mode );
        }
        
        /**
         * Get upgrade path for user
         */
        public static function get_upgrade_path( $current_plan_key ) {
            $current_plan = self::get_plan( $current_plan_key );
            if ( ! $current_plan ) {
                return [];
            }
            
            $available_plans = self::get_plans_by_mode( $current_plan['mode'] );
            $upgrade_path = [];
            
            foreach ( $available_plans as $plan_key => $plan ) {
                if ( $plan['tier_rank'] > $current_plan['tier_rank'] ) {
                    $upgrade_path[$plan_key] = $plan;
                }
            }
            
            return $upgrade_path;
        }
        
        /**
         * Calculate prorated amount for upgrade
         */
        public static function calculate_prorated_amount( $current_plan_key, $new_plan_key, $subscription_end_date ) {
            $current_plan = self::get_plan( $current_plan_key );
            $new_plan = self::get_plan( $new_plan_key );
            
            if ( ! $current_plan || ! $new_plan ) {
                return 0;
            }
            
            // Calculate remaining days in current billing period
            $now = new DateTime();
            $end_date = new DateTime( $subscription_end_date );
            $remaining_days = $now->diff( $end_date )->days;
            
            if ( $remaining_days <= 0 ) {
                return 0;
            }
            
            // Calculate daily rates
            $days_in_month = 30; // Approximate
            $current_daily_rate = $current_plan['price'] / $days_in_month;
            $new_daily_rate = $new_plan['price'] / $days_in_month;
            
            // Calculate prorated amount
            $prorated_amount = ( $new_daily_rate - $current_daily_rate ) * $remaining_days;
            
            return max( 0, $prorated_amount );
        }
        
        /**
         * Sync plans with database levels
         */
        public function sync_plans_with_levels() {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            $current_user_id = get_current_user_id();
            $current_mode = class_exists( 'OraBooks_Mode_Manager' ) ? 
                OraBooks_Mode_Manager::get_current_mode() : 'business';
            
            foreach ( self::$subscription_plans as $plan_key => $plan ) {
                // Check if level exists for this plan
                $level_id = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$wpdb->orabooks_levels} WHERE build_guide_level_key = %s",
                    $plan_key
                ) );
                
                if ( ! $level_id ) {
                    // Create level for this plan
                    $this->create_level_from_plan( $plan_key, $plan, $current_user_id );
                } else {
                    // Update existing level
                    $this->update_level_from_plan( $level_id, $plan_key, $plan, $current_user_id );
                }
            }
        }
        
        /**
         * Create level from plan
         */
        private function create_level_from_plan( $plan_key, $plan, $user_id ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Get or create group for this mode
            $group_id = $this->get_or_create_group( $plan['mode'], $user_id );
            
            // Create level
            $result = $wpdb->insert(
                $wpdb->orabooks_levels,
                [
                    'group_id' => $group_id,
                    'name' => $plan['name'],
                    'description' => $this->generate_plan_description( $plan ),
                    'price' => $plan['price'],
                    'billing_period' => $plan['billing_period'],
                    'trial_days' => $plan['trial_days'],
                    'currency' => $plan['currency'],
                    'currency_symbol' => '$',
                    'currency_position' => 'before',
                    'mode' => $plan['mode'],
                    'build_guide_level_key' => $plan_key,
                    'tier_rank' => $plan['tier_rank'],
                    'max_users' => $this->extract_max_users( $plan ),
                    'feature_limits' => json_encode( $this->extract_feature_limits( $plan ) ),
                    'is_active' => 1,
                    'created_by' => $user_id,
                    'updated_by' => $user_id
                ]
            );
            
            if ( $result ) {
                $level_id = $wpdb->insert_id;
                
                // Create feature assignments
                $this->create_feature_assignments_for_plan( $level_id, $plan_key, $plan, $user_id );
                
                // Log creation
                if ( class_exists( 'OraBooks_Audit_Logger' ) ) {
                    $logger = OraBooks_Audit_Logger::get_instance();
                    $logger->log_action([
                        'user_id' => $user_id,
                        'action_type' => 'subscription_plan_created',
                        'action_description' => sprintf( 'Created subscription plan: %s', $plan['name'] ),
                        'mode' => $plan['mode'],
                        'entity_type' => 'subscription_plan',
                        'entity_id' => $level_id,
                        'after_state' => $plan
                    ]);
                }
            }
        }
        
        /**
         * Update level from plan
         */
        private function update_level_from_plan( $level_id, $plan_key, $plan, $user_id ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            $wpdb->update(
                $wpdb->orabooks_levels,
                [
                    'name' => $plan['name'],
                    'description' => $this->generate_plan_description( $plan ),
                    'price' => $plan['price'],
                    'billing_period' => $plan['billing_period'],
                    'trial_days' => $plan['trial_days'],
                    'currency' => $plan['currency'],
                    'mode' => $plan['mode'],
                    'tier_rank' => $plan['tier_rank'],
                    'max_users' => $this->extract_max_users( $plan ),
                    'feature_limits' => json_encode( $this->extract_feature_limits( $plan ) ),
                    'updated_by' => $user_id
                ],
                [ 'id' => $level_id ]
            );
            
            // Update feature assignments
            $this->update_feature_assignments_for_plan( $level_id, $plan_key, $plan, $user_id );
        }
        
        /**
         * Get or create group for mode
         */
        private function get_or_create_group( $mode, $user_id ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Check if group exists
            $group_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->orabooks_groups} WHERE mode = %s",
                $mode
            ) );
            
            if ( $group_id ) {
                return $group_id;
            }
            
            // Create group
            $group_names = [
                'business' => 'Business Plans',
                'law' => 'Law Plans',
                'faith' => 'Faith Plans'
            ];
            
            $result = $wpdb->insert(
                $wpdb->orabooks_groups,
                [
                    'name' => isset( $group_names[$mode] ) ? $group_names[$mode] : 'Other Plans',
                    'description' => ucfirst( $mode ) . ' mode subscription plans',
                    'mode' => $mode,
                    'created_by' => $user_id,
                    'updated_by' => $user_id
                ]
            );
            
            return $result ? $wpdb->insert_id : 1;
        }
        
        /**
         * Generate plan description
         */
        private function generate_plan_description( $plan ) {
            $description = '';
            
            if ( isset( $plan['features'] ) && is_array( $plan['features'] ) ) {
                $feature_list = array_slice( $plan['features'], 0, 3 ); // First 3 features
                $description = implode( ', ', $feature_list );
                
                if ( count( $plan['features'] ) > 3 ) {
                    $description .= ' and more...';
                }
            }
            
            return $description;
        }
        
        /**
         * Extract max users from plan
         */
        private function extract_max_users( $plan ) {
            if ( isset( $plan['features'] ) && is_array( $plan['features'] ) ) {
                foreach ( $plan['features'] as $feature ) {
                    if ( strpos( $feature, 'users' ) !== false ) {
                        if ( strpos( $feature, 'unlimited' ) !== false ) {
                            return -1; // Unlimited
                        } elseif ( preg_match( '/(\d+)\s+users?/', $feature, $matches ) ) {
                            return (int) $matches[1];
                        }
                    }
                }
            }
            
            return 1; // Default to 1 user
        }
        
        /**
         * Extract feature limits from plan
         */
        private function extract_feature_limits( $plan ) {
            $limits = [];
            
            if ( isset( $plan['features'] ) && is_array( $plan['features'] ) ) {
                foreach ( $plan['features'] as $feature ) {
                    // Extract invoice limits
                    if ( strpos( $feature, 'invoices' ) !== false && preg_match( '/(\d+)\s+invoices?/', $feature, $matches ) ) {
                        $limits['invoices'] = (int) $matches[1];
                    }
                    
                    // Extract quote limits
                    if ( strpos( $feature, 'quotes' ) !== false && preg_match( '/(\d+)\s+quotes?/', $feature, $matches ) ) {
                        $limits['quotes'] = (int) $matches[1];
                    }
                    
                    // Extract bank account limits
                    if ( strpos( $feature, 'bank account' ) !== false && preg_match( '/(\d+)\s+bank/', $feature, $matches ) ) {
                        $limits['bank_accounts'] = (int) $matches[1];
                    }
                    
                    // Extract CoA limits
                    if ( strpos( $feature, 'Chart of Accounts' ) !== false && preg_match( '/up to\s+(\d+)/i', $feature, $matches ) ) {
                        $limits['chart_of_accounts'] = (int) $matches[1];
                    }
                    
                    // Extract AI entry limits
                    if ( strpos( $feature, 'AI entries' ) !== false && preg_match( '/(\d+)\s+\/\s*month/i', $feature, $matches ) ) {
                        $limits['ai_entries'] = (int) $matches[1];
                    }
                }
            }
            
            return $limits;
        }
        
        /**
         * Create feature assignments for plan using OraBooks_Tier_Features as source of truth
         */
        private function create_feature_assignments_for_plan( $level_id, $plan_key, $plan, $user_id ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Resolve tier_key (maps to OraBooks_Tier_Features tiers)
            $tier_key = isset( $plan['tier_key'] ) ? $plan['tier_key'] : $plan_key;
            
            // Get all tier-restricted features for this tier
            if ( class_exists( 'OraBooks_Tier_Features' ) ) {
                $tier_restrictions = OraBooks_Tier_Features::get_tier_restrictions( $tier_key );
            } else {
                $tier_restrictions = array();
            }
            
            // Build feature list from tier restrictions (source of truth) + plan features
            $all_feature_keys = array_keys( $tier_restrictions );
            
            // Also include plan features for text-based display
            if ( empty( $all_feature_keys ) ) {
                // Fallback keyword-based feature mappings
                $feature_mappings = [
                    'income_expense_tracking' => 'income',
                    'invoices' => 'invoices',
                    'quotes' => 'quotes',
                    'bank_accounts' => 'bank account',
                    'gst_vat_tracking' => 'GST/VAT',
                    'basic_reports' => 'reports',
                    'chart_of_accounts' => 'Chart of Accounts',
                    'time_tracking' => 'Time Tracking',
                    'unlimited_invoicing' => 'unlimited',
                    'insights_reports' => 'Insights',
                    'progress_invoicing' => 'Progress invoicing',
                    'pwa_accessibility' => 'PWA',
                    'dkim_email' => 'DKIM',
                    'ai_review_queue' => 'AI Review',
                    'ai_entries' => 'AI entries',
                    'receipt_capture' => 'receipt capture',
                    'offline_entry' => 'Offline data',
                    'payment_notifications' => 'notifications',
                    'mobile_dashboard' => 'mobile',
                    'storage' => 'storage',
                    'data_sync' => 'requests/month',
                    'bills_payments' => 'bills',
                    'ar_aging' => 'AR aging',
                    'bank_rules' => 'Bank rules',
                    'payment_links' => 'Payment Link',
                    'project_lite' => 'Project-lite',
                    'auto_reorder' => 'Auto Reorder',
                    'inventory_lite' => 'Inventory',
                    'policy_filters' => 'policy',
                    'tax_hints' => 'tax hints',
                    'notification_center' => 'Notification Center',
                    'recurring_transactions' => 'Recurring',
                    'classes_locations' => 'Classes & Locations',
                    'fx_multi_currency' => 'multi-currency',
                    'custom_reports' => 'Custom reporting',
                    'sales_ops_kit' => 'Sales-Ops',
                    'ai_speedups' => 'AI speedups',
                    'project_full' => 'Project (Full)',
                    'payroll_api' => 'Payroll',
                    'dashboard_templates' => 'Dashboard Templates',
                    'ai_insight_panel' => 'AI Insight',
                    'matter_master' => 'Matter Master',
                    'audit_evidence' => 'Audit & Evidence',
                    'attachments_versioning' => 'Attachments',
                    'core_reports' => 'Core Reports',
                    'hearing_calendar' => 'Hearing Calendar',
                    'openapi_webhooks' => 'OpenAPI',
                    'mobile_offline' => 'mobile-first',
                    'policy_expense_filters' => 'Expense Filters',
                    'restricted_fund_accounting' => 'Restricted Fund',
                    'fund_program_reports' => 'Fund/Program Reports',
                    'notification_basics' => 'Notification Basics',
                    'regional_calendars' => 'Calendars',
                    'unlimited_coa' => 'UNLIMITED items',
                    'unlimited_classes_locations' => 'UNLIMITED classes',
                    'excel_sheets_sync' => 'Excel/Sheets',
                    'custom_role_permissions' => 'Custom role permissions',
                    'automate_workflows' => 'Automate workflows',
                    'backup_online_restore' => 'Backup online',
                    'audit_evidence_export' => 'Audit & Evidence export',
                    'multi_tenant_data_residency' => 'Multi-tenant',
                    'dr_resilience' => 'DR/Resilience',
                    'payments_vault_pci' => 'Payments vault',
                    'workflow_templates_library' => 'Workflow Templates',
                    'ai_forecast_models' => 'AI Forecast',
                    'scenario_simulation' => 'Scenario Simulation',
                    'app_marketplace' => 'App Marketplace'
                ];
                
                foreach ( $feature_mappings as $feature_key => $keyword ) {
                    $available = false;
                    $limit = null;
                    
                    if ( isset( $plan['features'] ) && is_array( $plan['features'] ) ) {
                        foreach ( $plan['features'] as $feature ) {
                            if ( stripos( $feature, $keyword ) !== false ) {
                                $available = true;
                                
                                if ( $feature_key === 'invoices' && preg_match( '/(\d+)\s+invoices?/', $feature, $matches ) ) {
                                    $limit = (int) $matches[1];
                                } elseif ( $feature_key === 'quotes' && preg_match( '/(\d+)\s+quotes?/', $feature, $matches ) ) {
                                    $limit = (int) $matches[1];
                                } elseif ( $feature_key === 'bank_accounts' && preg_match( '/(\d+)\s+bank/', $feature, $matches ) ) {
                                    $limit = (int) $matches[1];
                                } elseif ( $feature_key === 'chart_of_accounts' && preg_match( '/up to\s+(\d+)/i', $feature, $matches ) ) {
                                    $limit = (int) $matches[1];
                                } elseif ( $feature_key === 'ai_entries' && preg_match( '/(\d+)\s+\/\s*month/i', $feature, $matches ) ) {
                                    $limit = (int) $matches[1];
                                } elseif ( $feature_key === 'users' && preg_match( '/(\d+)\s+users?/', $feature, $matches ) ) {
                                    $limit = (int) $matches[1];
                                }
                                break;
                            }
                        }
                    }
                    
                    $all_feature_keys[] = $feature_key;
                }
            }
            
            $all_feature_keys = array_unique( $all_feature_keys );
            
            foreach ( $all_feature_keys as $feature_key ) {
                // Check tier restrictions first (source of truth)
                if ( isset( $tier_restrictions[$feature_key] ) ) {
                    $restriction = $tier_restrictions[$feature_key];
                    $available = $restriction['enabled'];
                    $access_type = $restriction['enabled'] ? $restriction['access_level'] : 'none';
                    $limit = isset( $restriction['limit'] ) ? $restriction['limit'] : null;
                } else {
                    // Fallback: feature not in tier restrictions, set as not available
                    $available = false;
                    $access_type = 'none';
                    $limit = null;
                }
                
                $settings = json_encode( [
                    'available' => $available,
                    'limit' => $limit
                ] );
                
                $wpdb->insert(
                    $wpdb->orabooks_feature_assignments,
                    [
                        'level_id' => $level_id,
                        'feature_key' => $feature_key,
                        'feature_name' => ucwords( str_replace( '_', ' ', $feature_key ) ),
                        'access_type' => $access_type,
                        'mode' => $plan['mode'],
                        'settings' => $settings,
                        'created_by' => $user_id,
                        'updated_by' => $user_id
                    ]
                );
            }
        }
        
        /**
         * Update feature assignments for plan
         */
        private function update_feature_assignments_for_plan( $level_id, $plan_key, $plan, $user_id ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Remove existing assignments
            $wpdb->delete(
                $wpdb->orabooks_feature_assignments,
                [ 'level_id' => $level_id ]
            );
            
            // Create new assignments
            $this->create_feature_assignments_for_plan( $level_id, $plan_key, $plan, $user_id );
        }
        
        /**
         * Get plan comparison data
         */
        public static function get_plan_comparison( $mode = null ) {
            if ( ! $mode ) {
                $mode = class_exists( 'OraBooks_Mode_Manager' ) ? 
                    OraBooks_Mode_Manager::get_current_mode() : 'business';
            }
            
            $plans = self::get_plans_by_mode( $mode );
            $comparison = [];
            
            // Get all unique features across all plans
            $all_features = [];
            foreach ( $plans as $plan ) {
                if ( isset( $plan['features'] ) && is_array( $plan['features'] ) ) {
                    $all_features = array_merge( $all_features, $plan['features'] );
                }
            }
            $all_features = array_unique( $all_features );
            
            // Build comparison matrix
            foreach ( $plans as $plan_key => $plan ) {
                $comparison[$plan_key] = [
                    'name' => $plan['name'],
                    'price' => $plan['price'],
                    'features' => []
                ];
                
                foreach ( $all_features as $feature ) {
                    $has_feature = in_array( $feature, $plan['features'] );
                    $comparison[$plan_key]['features'][$feature] = $has_feature;
                }
            }
            
            return $comparison;
        }
    }
}

// Initialize the subscription plans system
OraBooks_Subscription_Plans::get_instance();
