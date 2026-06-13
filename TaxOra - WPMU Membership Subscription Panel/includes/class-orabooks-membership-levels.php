<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * OraBooks Membership Levels - Build Guide Compliant
 * 
 * Implements membership levels according to ORABOOKS_ULTIMATE_BUILD_GUIDE_A_TO_Z.md
 * Mode-aware operations with Business/Law/Faith modes
 * Permission matrix integration
 * Audit-ready implementation
 * 
 * @package OraBooks_Membership
 * @since 2.0.0
 */

if ( ! class_exists( 'OraBooks_Membership_Levels' ) ) {
    class OraBooks_Membership_Levels {
        
        private static $instance = null;
        
        /**
         * Build Guide Compliant Membership Levels
         * Based on tier plan document — ALL tiers included (Free, Starter, Standard, Pro, Enterprise, Law, Faith)
         * Feature availability is delegated to OraBooks_Tier_Features as the source of truth.
         * These level definitions are used for DB sync and plan management.
         */
        private static $build_guide_levels = [
            'free' => [
                'name' => 'OraBooks Free',
                'description' => 'Basic accounting features for individuals — $0 forever',
                'price' => 0,
                'billing_period' => 'monthly',
                'mode' => 'business',
                'tier_key' => 'free',
                'features' => [
                    'income_expense_tracking' => ['available' => true, 'limit' => null],
                    'invoices' => ['available' => true, 'limit' => 5],
                    'quotes' => ['available' => true, 'limit' => 3],
                    'bank_accounts' => ['available' => true, 'limit' => 1],
                    'gst_vat_tracking' => ['available' => true, 'limit' => null],
                    'basic_reports' => ['available' => true, 'limit' => null],
                    'chart_of_accounts' => ['available' => true, 'limit' => 50],
                    'users' => ['available' => true, 'limit' => 1],
                    'ai_entries' => ['available' => true, 'limit' => 10],
                    'receipt_capture' => ['available' => true, 'limit' => null],
                    'offline_entry' => ['available' => true, 'limit' => null],
                    'payment_notifications' => ['available' => true, 'limit' => null],
                    'mobile_dashboard' => ['available' => true, 'limit' => null],
                    'data_sync' => ['available' => true, 'limit' => 1000],
                    'storage' => ['available' => true, 'limit' => 100]
                ]
            ],
            'business_starter' => [
                'name' => 'Business Starter',
                'description' => 'Essential accounting and business features — $4.99/mo',
                'price' => 4.99,
                'billing_period' => 'monthly',
                'mode' => 'business',
                'tier_key' => 'starter',
                'features' => [
                    'income_expense_tracking' => ['available' => true, 'limit' => null],
                    'unlimited_invoicing' => ['available' => true, 'limit' => null],
                    'bank_connection' => ['available' => true, 'limit' => null],
                    'gst_vat_tracking' => ['available' => true, 'limit' => null],
                    'insights_reports' => ['available' => true, 'limit' => null],
                    'progress_invoicing' => ['available' => true, 'limit' => null],
                    'chart_of_accounts' => ['available' => true, 'limit' => 250],
                    'time_tracking' => ['available' => true, 'limit' => 'basic'],
                    'users' => ['available' => true, 'limit' => 2],
                    'ai_review_queue' => ['available' => true, 'limit' => null],
                    'pwa_accessibility' => ['available' => true, 'limit' => null],
                    'dkim_email' => ['available' => true, 'limit' => null]
                ]
            ],
            'business_standard' => [
                'name' => 'Business Standard',
                'description' => 'Complete business accounting with inventory — $7.49/mo',
                'price' => 7.49,
                'billing_period' => 'monthly',
                'mode' => 'business',
                'tier_key' => 'standard',
                'features' => [
                    'bills_payments' => ['available' => true, 'limit' => null],
                    'ar_aging' => ['available' => true, 'limit' => null],
                    'bank_rules' => ['available' => true, 'limit' => null],
                    'payment_links' => ['available' => true, 'limit' => null],
                    'project_lite' => ['available' => true, 'limit' => null],
                    'auto_reorder' => ['available' => true, 'limit' => null],
                    'inventory_lite' => ['available' => true, 'limit' => null],
                    'chart_of_accounts' => ['available' => true, 'limit' => 500],
                    'users' => ['available' => true, 'limit' => 5],
                    'policy_filters' => ['available' => true, 'limit' => null],
                    'tax_hints' => ['available' => true, 'limit' => null],
                    'notification_center' => ['available' => true, 'limit' => null]
                ]
            ],
            'business_pro' => [
                'name' => 'Business Pro',
                'description' => 'Advanced features with AI and automation — $13.49/mo',
                'price' => 13.49,
                'billing_period' => 'monthly',
                'mode' => 'business',
                'tier_key' => 'pro',
                'features' => [
                    'recurring_transactions' => ['available' => true, 'limit' => null],
                    'classes_locations' => ['available' => true, 'limit' => null],
                    'fx_multi_currency' => ['available' => true, 'limit' => null],
                    'inventory_lite' => ['available' => true, 'limit' => null],
                    'chart_of_accounts' => ['available' => true, 'limit' => 1500],
                    'custom_reports' => ['available' => true, 'limit' => null],
                    'users' => ['available' => true, 'limit' => 8],
                    'sales_ops_kit' => ['available' => true, 'limit' => null],
                    'ai_speedups' => ['available' => true, 'limit' => null],
                    'project_full' => ['available' => true, 'limit' => null],
                    'payroll_api' => ['available' => true, 'limit' => null],
                    'dashboard_templates' => ['available' => true, 'limit' => null],
                    'ai_insight_panel' => ['available' => true, 'limit' => null]
                ]
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'description' => 'Enterprise-grade accounting with unlimited everything',
                'price' => 0,
                'billing_period' => 'monthly',
                'mode' => 'business',
                'tier_key' => 'enterprise',
                'features' => [
                    'unlimited_coa' => ['available' => true, 'limit' => null],
                    'unlimited_classes_locations' => ['available' => true, 'limit' => null],
                    'excel_sheets_sync' => ['available' => true, 'limit' => null],
                    'custom_role_permissions' => ['available' => true, 'limit' => null],
                    'users' => ['available' => true, 'limit' => 25],
                    'automate_workflows' => ['available' => true, 'limit' => null],
                    'custom_reporting_dashboards' => ['available' => true, 'limit' => null],
                    'backup_online_restore' => ['available' => true, 'limit' => null],
                    'openapi_webhooks' => ['available' => true, 'limit' => null],
                    'audit_evidence_export' => ['available' => true, 'limit' => null],
                    'multi_tenant_data_residency' => ['available' => true, 'limit' => null],
                    'dr_resilience' => ['available' => true, 'limit' => null],
                    'payments_vault_pci' => ['available' => true, 'limit' => null],
                    'workflow_templates_library' => ['available' => true, 'limit' => null],
                    'ai_forecast_models' => ['available' => true, 'limit' => null],
                    'scenario_simulation' => ['available' => true, 'limit' => null],
                    'app_marketplace' => ['available' => true, 'limit' => null]
                ]
            ],
            'law_starter' => [
                'name' => 'Law Starter',
                'description' => 'Legal practice management basics — $3.99/mo',
                'price' => 3.99,
                'billing_period' => 'monthly',
                'mode' => 'law',
                'tier_key' => 'law_starter',
                'features' => [
                    'matter_master' => ['available' => true, 'limit' => null],
                    'audit_evidence' => ['available' => true, 'limit' => null],
                    'attachments_versioning' => ['available' => true, 'limit' => null],
                    'core_reports' => ['available' => true, 'limit' => null],
                    'pwa_accessibility' => ['available' => true, 'limit' => null],
                    'dkim_email' => ['available' => true, 'limit' => null],
                    'hearing_calendar' => ['available' => true, 'limit' => null],
                    'chart_of_accounts' => ['available' => true, 'limit' => 250],
                    'users' => ['available' => true, 'limit' => 2]
                ]
            ],
            'law_standard' => [
                'name' => 'Law Standard',
                'description' => 'Complete legal practice management — $5.99/mo',
                'price' => 5.99,
                'billing_period' => 'monthly',
                'mode' => 'law',
                'tier_key' => 'law_standard',
                'features' => [
                    'ai_review_queue' => ['available' => true, 'limit' => null],
                    'openapi_webhooks' => ['available' => true, 'limit' => null],
                    'mobile_offline' => ['available' => true, 'limit' => null],
                    'policy_expense_filters' => ['available' => true, 'limit' => null],
                    'notification_center' => ['available' => true, 'limit' => null],
                    'chart_of_accounts' => ['available' => true, 'limit' => 500],
                    'users' => ['available' => true, 'limit' => 5]
                ]
            ],
            'faith_starter' => [
                'name' => 'Faith Starter',
                'description' => 'Religious organization accounting basics — $2.99/mo',
                'price' => 2.99,
                'billing_period' => 'monthly',
                'mode' => 'faith',
                'tier_key' => 'faith_starter',
                'features' => [
                    'restricted_fund_accounting' => ['available' => true, 'limit' => null],
                    'fund_program_reports' => ['available' => true, 'limit' => null],
                    'pwa_accessibility' => ['available' => true, 'limit' => null],
                    'dkim_email' => ['available' => true, 'limit' => null],
                    'notification_basics' => ['available' => true, 'limit' => null],
                    'chart_of_accounts' => ['available' => true, 'limit' => 250],
                    'users' => ['available' => true, 'limit' => 2]
                ]
            ],
            'faith_standard' => [
                'name' => 'Faith Standard',
                'description' => 'Complete religious organization management — $4.99/mo',
                'price' => 4.99,
                'billing_period' => 'monthly',
                'mode' => 'faith',
                'tier_key' => 'faith_standard',
                'features' => [
                    'ai_review_queue' => ['available' => true, 'limit' => null],
                    'regional_calendars' => ['available' => true, 'limit' => null],
                    'openapi_webhooks' => ['available' => true, 'limit' => null],
                    'mobile_offline' => ['available' => true, 'limit' => null],
                    'chart_of_accounts' => ['available' => true, 'limit' => 500],
                    'users' => ['available' => true, 'limit' => 5]
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
            add_action( 'init', [ $this, 'init_build_guide_levels' ] );
            add_action( 'admin_init', [ $this, 'create_default_levels_if_needed' ] );
        }
        
        /**
         * Initialize build guide compliant membership levels
         */
        public function init_build_guide_levels() {
            // Ensure mode manager is available
            if ( ! class_exists( 'OraBooks_Mode_Manager' ) ) {
                require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-mode-manager.php';
            }
            
            // Ensure permission matrix is available
            if ( ! class_exists( 'OraBooks_Permission_Matrix' ) ) {
                require_once TAXORA_MEMBERSHIP_DIR . 'includes/class-orabooks-permission-matrix.php';
            }
        }
        
        /**
         * Get all build guide compliant levels
         */
        public static function get_build_guide_levels() {
            return self::$build_guide_levels;
        }
        
        /**
         * Get level information by key
         */
        public static function get_level_info( $level_key ) {
            return isset( self::$build_guide_levels[$level_key] ) ? self::$build_guide_levels[$level_key] : null;
        }
        
        /**
         * Get levels by mode
         */
        public static function get_levels_by_mode( $mode ) {
            $levels = [];
            foreach ( self::$build_guide_levels as $key => $level ) {
                if ( $level['mode'] === $mode ) {
                    $levels[$key] = $level;
                }
            }
            return $levels;
        }
        
        /**
         * Check if feature is available for level.
         * Delegates to OraBooks_Tier_Features as the source of truth.
         */
        public static function is_feature_available( $level_key, $feature_key ) {
            $level = self::get_level_info( $level_key );
            if ( ! $level ) {
                return false;
            }
            
            // Get the tier_key from level definition (maps to OraBooks_Tier_Features tiers)
            $tier_key = isset( $level['tier_key'] ) ? $level['tier_key'] : $level_key;
            
            // Delegate to OraBooks_Tier_Features as source of truth
            if ( class_exists( 'OraBooks_Tier_Features' ) ) {
                return OraBooks_Tier_Features::is_feature_available( $tier_key, $feature_key );
            }
            
            // Fallback: check direct feature in level definition
            if ( isset( $level['features'][$feature_key] ) ) {
                return $level['features'][$feature_key]['available'];
            }
            
            return false;
        }
        
        /**
         * Get feature limit for level.
         * Delegates to OraBooks_Tier_Features as the source of truth.
         */
        public static function get_feature_limit( $level_key, $feature_key ) {
            $level = self::get_level_info( $level_key );
            if ( ! $level ) {
                return false;
            }
            
            $tier_key = isset( $level['tier_key'] ) ? $level['tier_key'] : $level_key;
            
            // Delegate to OraBooks_Tier_Features as source of truth
            if ( class_exists( 'OraBooks_Tier_Features' ) ) {
                $limit = OraBooks_Tier_Features::get_feature_limit( $tier_key, $feature_key );
                if ( $limit !== false ) {
                    return $limit;
                }
            }
            
            // Fallback: check level definition
            if ( isset( $level['features'][$feature_key]['limit'] ) ) {
                return $level['features'][$feature_key]['limit'];
            }
            
            return false;
        }
        
        /**
         * Create default levels in database if they don't exist
         */
        public function create_default_levels_if_needed() {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Check if levels already exist
            $existing_levels = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->orabooks_levels}" );
            
            if ( $existing_levels > 0 ) {
                return; // Levels already exist
            }
            
            // Get current user for audit trail
            $current_user_id = get_current_user_id();
            $current_mode = class_exists( 'OraBooks_Mode_Manager' ) ? 
                OraBooks_Mode_Manager::get_current_mode() : 'business';
            
            // Create groups for each mode
            $groups = [
                'business' => $this->create_group( 'Business Plans', 'Business mode subscription plans', 'business', $current_user_id ),
                'law' => $this->create_group( 'Law Plans', 'Law mode subscription plans', 'law', $current_user_id ),
                'faith' => $this->create_group( 'Faith Plans', 'Faith mode subscription plans', 'faith', $current_user_id )
            ];
            
            // Create levels
            foreach ( self::$build_guide_levels as $level_key => $level_data ) {
                $group_id = isset( $groups[$level_data['mode']] ) ? $groups[$level_data['mode']] : $groups['business'];
                
                $result = $wpdb->insert(
                    $wpdb->orabooks_levels,
                    [
                        'group_id' => $group_id,
                        'name' => $level_data['name'],
                        'description' => $level_data['description'],
                        'price' => $level_data['price'],
                        'billing_period' => $level_data['billing_period'],
                        'currency' => 'USD',
                        'currency_symbol' => '$',
                        'currency_position' => 'before',
                        'mode' => $level_data['mode'],
                        'is_active' => 1,
                        'created_by' => $current_user_id,
                        'updated_by' => $current_user_id
                    ]
                );
                
                if ( $result ) {
                    $level_id = $wpdb->insert_id;
                    
                    // Create feature assignments for this level
                    $this->create_feature_assignments_for_level( $level_id, $level_key, $current_mode, $current_user_id );
                    
                    // Log creation for audit trail
                    if ( class_exists( 'OraBooks_Audit_Logger' ) ) {
                        $logger = OraBooks_Audit_Logger::get_instance();
                        $logger->log_action([
                            'user_id' => $current_user_id,
                            'action_type' => 'membership_level_created',
                            'action_description' => sprintf( 'Created membership level: %s', $level_data['name'] ),
                            'mode' => $current_mode,
                            'entity_type' => 'membership_level',
                            'entity_id' => $level_id,
                            'after_state' => $level_data
                        ]);
                    }
                }
            }
        }
        
        /**
         * Create group for levels
         */
        private function create_group( $name, $description, $mode, $user_id ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            $result = $wpdb->insert(
                $wpdb->orabooks_groups,
                [
                    'name' => $name,
                    'description' => $description,
                    'mode' => $mode,
                    'created_by' => $user_id,
                    'updated_by' => $user_id
                ]
            );
            
            return $result ? $wpdb->insert_id : 1; // Default to group 1 if creation fails
        }
        
        /**
         * Create feature assignments for a level using OraBooks_Tier_Features as source of truth
         */
        private function create_feature_assignments_for_level( $level_id, $level_key, $mode, $user_id ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            $level_info = self::get_level_info( $level_key );
            if ( ! $level_info ) {
                return;
            }
            
            $tier_key = isset( $level_info['tier_key'] ) ? $level_info['tier_key'] : $level_key;
            
            // Get all features from tier features for this tier
            if ( class_exists( 'OraBooks_Tier_Features' ) ) {
                $tier_restrictions = OraBooks_Tier_Features::get_tier_restrictions( $tier_key );
            } else {
                $tier_restrictions = array();
            }
            
            // Merge: use tier restrictions as source of truth, level definition as fallback
            $feature_keys = array_unique( array_merge(
                array_keys( $level_info['features'] ),
                array_keys( $tier_restrictions )
            ) );
            
            foreach ( $feature_keys as $feature_key ) {
                // Check tier features first
                if ( isset( $tier_restrictions[$feature_key] ) ) {
                    $restriction = $tier_restrictions[$feature_key];
                    $available = $restriction['enabled'];
                    $access_type = $restriction['enabled'] ? $restriction['access_level'] : 'none';
                    $limit = isset( $restriction['limit'] ) ? $restriction['limit'] : null;
                } elseif ( isset( $level_info['features'][$feature_key] ) ) {
                    // Fallback to level definition
                    $feature_data = $level_info['features'][$feature_key];
                    $available = $feature_data['available'];
                    $access_type = $available ? 'full' : 'none';
                    $limit = isset( $feature_data['limit'] ) ? $feature_data['limit'] : null;
                } else {
                    continue;
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
                        'mode' => $mode,
                        'settings' => $settings,
                        'created_by' => $user_id,
                        'updated_by' => $user_id
                    ]
                );
            }
        }
        
        /**
         * Validate user access based on membership level and mode
         * Build Guide Compliance: Role × Mode × Action validation
         * Uses OraBooks_Tier_Features as source of truth for feature access
         */
        public static function validate_user_access( $user_id, $feature_key, $action = 'view' ) {
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
            
            // Get level info to resolve tier_key
            $level_info = self::get_level_info( $level_key );
            $tier_key = $level_info && isset( $level_info['tier_key'] ) ? $level_info['tier_key'] : $level_key;
            
            // Check if feature is available via tier features (source of truth)
            if ( class_exists( 'OraBooks_Tier_Features' ) ) {
                if ( ! OraBooks_Tier_Features::is_feature_available( $tier_key, $feature_key ) ) {
                    return false;
                }
            } else {
                // Fallback to level definition
                if ( ! self::is_feature_available( $level_key, $feature_key ) ) {
                    return false;
                }
            }
            
            // Get current mode
            $current_mode = class_exists( 'OraBooks_Mode_Manager' ) ? 
                OraBooks_Mode_Manager::get_current_mode() : 'business';
            
            // Get level info to check mode compatibility
            if ( $level_info && $level_info['mode'] !== $current_mode ) {
                // Cross-mode access is forbidden in build guide
                return false;
            }
            
            // Check permission matrix if available
            if ( class_exists( 'OraBooks_Permission_Matrix' ) ) {
                $user_role = get_user_meta( $user_id, 'orabooks_role', true );
                if ( ! $user_role ) {
                    $user_role = 'staff'; // Default role
                }
                
                return OraBooks_Permission_Matrix::check_permission( $user_role, $current_mode, $action );
            }
            
            return true;
        }
        
        /**
         * Get available levels for current mode
         */
        public static function get_available_levels_for_current_mode() {
            $current_mode = class_exists( 'OraBooks_Mode_Manager' ) ? 
                OraBooks_Mode_Manager::get_current_mode() : 'business';
            
            return self::get_levels_by_mode( $current_mode );
        }
        
        /**
         * Update existing levels to be build guide compliant
         */
        public function update_existing_levels_to_build_guide() {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            $current_user_id = get_current_user_id();
            $current_mode = class_exists( 'OraBooks_Mode_Manager' ) ? 
                OraBooks_Mode_Manager::get_current_mode() : 'business';
            
            // Get existing levels
            $existing_levels = $wpdb->get_results( "SELECT * FROM {$wpdb->orabooks_levels}" );
            
            foreach ( $existing_levels as $level ) {
                // Try to map existing level to build guide level
                $mapped_level = $this->map_existing_level_to_build_guide( $level );
                
                if ( $mapped_level ) {
                    // Update level to be build guide compliant
                    $wpdb->update(
                        $wpdb->orabooks_levels,
                        [
                            'mode' => $mapped_level['mode'],
                            'updated_by' => $current_user_id
                        ],
                        [ 'id' => $level->id ]
                    );
                    
                    // Update or create feature assignments
                    $this->update_feature_assignments_for_level( $level->id, $mapped_level['key'], $current_mode, $current_user_id );
                    
                    // Log update for audit trail
                    if ( class_exists( 'OraBooks_Audit_Logger' ) ) {
                        $logger = OraBooks_Audit_Logger::get_instance();
                        $logger->log_action([
                            'user_id' => $current_user_id,
                            'action_type' => 'membership_level_updated',
                            'action_description' => sprintf( 'Updated membership level to build guide compliance: %s', $level->name ),
                            'mode' => $current_mode,
                            'entity_type' => 'membership_level',
                            'entity_id' => $level->id,
                            'after_state' => $mapped_level
                        ]);
                    }
                }
            }
        }
        
        /**
         * Map existing level to build guide level (all tiers supported)
         */
        private function map_existing_level_to_build_guide( $level ) {
            $name_lower = strtolower( $level->name );
            
            // Mapping logic based on level name and price
            if ( strpos( $name_lower, 'enterprise' ) !== false ) {
                return [ 'key' => 'enterprise', 'mode' => 'business' ];
            } elseif ( strpos( $name_lower, 'free' ) !== false || $level->price == 0 ) {
                return [ 'key' => 'free', 'mode' => 'business' ];
            } elseif ( strpos( $name_lower, 'starter' ) !== false ) {
                if ( strpos( $name_lower, 'law' ) !== false ) {
                    return [ 'key' => 'law_starter', 'mode' => 'law' ];
                } elseif ( strpos( $name_lower, 'faith' ) !== false ) {
                    return [ 'key' => 'faith_starter', 'mode' => 'faith' ];
                } else {
                    return [ 'key' => 'business_starter', 'mode' => 'business' ];
                }
            } elseif ( strpos( $name_lower, 'standard' ) !== false ) {
                if ( strpos( $name_lower, 'law' ) !== false ) {
                    return [ 'key' => 'law_standard', 'mode' => 'law' ];
                } elseif ( strpos( $name_lower, 'faith' ) !== false ) {
                    return [ 'key' => 'faith_standard', 'mode' => 'faith' ];
                } else {
                    return [ 'key' => 'business_standard', 'mode' => 'business' ];
                }
            } elseif ( strpos( $name_lower, 'pro' ) !== false ) {
                return [ 'key' => 'business_pro', 'mode' => 'business' ];
            }
            
            // Default mapping based on price
            if ( $level->price == 0 ) {
                return [ 'key' => 'free', 'mode' => 'business' ];
            } elseif ( $level->price <= 3.50 ) {
                return [ 'key' => 'faith_starter', 'mode' => 'faith' ];
            } elseif ( $level->price <= 5 ) {
                return [ 'key' => 'business_starter', 'mode' => 'business' ];
            } elseif ( $level->price <= 10 ) {
                return [ 'key' => 'business_standard', 'mode' => 'business' ];
            } elseif ( $level->price <= 15 ) {
                return [ 'key' => 'business_pro', 'mode' => 'business' ];
            } else {
                return [ 'key' => 'enterprise', 'mode' => 'business' ];
            }
        }
        
        /**
         * Update feature assignments for level
         */
        private function update_feature_assignments_for_level( $level_id, $level_key, $mode, $user_id ) {
            global $wpdb;
            orabooks_handle_multisite_tables();
            
            // Remove existing assignments
            $wpdb->delete(
                $wpdb->orabooks_feature_assignments,
                [ 'level_id' => $level_id ]
            );
            
            // Create new assignments
            $this->create_feature_assignments_for_level( $level_id, $level_key, $mode, $user_id );
        }
    }
}

// Initialize the membership levels system
OraBooks_Membership_Levels::get_instance();
