<?php
/**
 * OraBooks Partner Management (SL-013/139 extensions)
 * 
 * Partner code management, attribution processing, inactivity management,
 * partner dashboard, and partner program UI.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Partner {
    
    private static $instance = null;
    
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            add_action('orabooks_partner_activity_check', [self::$instance, 'process_partner_activity']);
            add_action('wp_ajax_orabooks_get_partner_info', [self::$instance, 'ajax_get_partner_info']);
            add_action('wp_ajax_nopriv_orabooks_get_partner_info', [self::$instance, 'ajax_get_partner_info']);
            add_action('wp_ajax_orabooks_partner_onboarding', [self::$instance, 'ajax_partner_onboarding']);
            add_action('wp_ajax_orabooks_request_reactivation', [self::$instance, 'ajax_request_reactivation']);
            add_action('wp_ajax_nopriv_orabooks_request_reactivation', [self::$instance, 'ajax_request_reactivation']);
            
            // SL-139: Partner Dashboard endpoints
            add_action('wp_ajax_orabooks_partner_dashboard', [self::$instance, 'ajax_partner_dashboard']);
            add_action('wp_ajax_orabooks_partner_code_copied', [self::$instance, 'ajax_code_copied']);
            add_action('wp_ajax_orabooks_partner_attributions', [self::$instance, 'ajax_partner_attributions']);
            add_action('wp_ajax_orabooks_partner_payment_settings', [self::$instance, 'ajax_payment_settings']);
            add_action('wp_ajax_orabooks_partner_application', [self::$instance, 'ajax_partner_application']);
            add_action('wp_ajax_nopriv_orabooks_partner_dashboard', [self::$instance, 'ajax_partner_dashboard']);
            add_action('wp_ajax_nopriv_orabooks_partner_code_copied', [self::$instance, 'ajax_code_copied']);
            
            // SL-003: Admin partner approval / rejection
            add_action('wp_ajax_orabooks_admin_approve_partner', [self::$instance, 'ajax_admin_approve_partner']);
            add_action('wp_ajax_orabooks_admin_reject_partner', [self::$instance, 'ajax_admin_reject_partner']);
            add_action('wp_ajax_orabooks_admin_list_pending_partners', [self::$instance, 'ajax_admin_list_pending_partners']);
            add_action('wp_ajax_orabooks_admin_list_active_partners', [self::$instance, 'ajax_admin_list_active_partners']);
            add_action('wp_ajax_orabooks_admin_list_reactivation_requests', [self::$instance, 'ajax_admin_list_reactivation_requests']);
            add_action('wp_ajax_orabooks_admin_review_reactivation', [self::$instance, 'ajax_admin_review_reactivation']);
        }
        return self::$instance;
    }
    
    /**
     * Get active customer count for a partner
     * Uses SL-021 customers.is_active as the source of truth
     */
    public static function get_active_customer_count($partner_user_id) {
        global $wpdb;
        
        $table_attributions = OraBooks_Database::table('partner_attributions');
        $table_customers = OraBooks_Database::table('customers');
        
        // Check if customers table exists (SL-021)
        $customers_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_customers}'");
        
        if ($customers_exists) {
            // SL-021 exists: use customers.is_active as truth source
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT c.user_id)
                 FROM {$table_attributions} pa
                 JOIN {$table_customers} c ON pa.customer_user_id = c.user_id
                 WHERE pa.partner_user_id = %d 
                   AND pa.status = 'verified'
                   AND c.is_active = 1",
                $partner_user_id
            ));
        }
        
        // Fallback: just count verified attributions (pre-SL-021)
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT pa.customer_user_id)
             FROM {$table_attributions} pa
             WHERE pa.partner_user_id = %d 
               AND pa.status = 'verified'",
            $partner_user_id
        ));
    }
    
    /**
      * Process partner activity (daily job) - Inactivity & Low-Activity management
      * Per SL-139 spec:
      * - 11 months: send deactivation warning (once)
      * - 12 months: deactivate to 'inactive'
      * - 6 months no attribution: send low-activity reminder (repeat every 3 months)
      */
    public static function process_partner_activity() {
        global $wpdb;
        
        $table_codes = OraBooks_Database::table('partner_codes');
        
        $partners = $wpdb->get_results(
            "SELECT id, user_id, last_attribution_at, deactivation_reminder_sent_at, low_activity_reminder_sent_at 
             FROM {$table_codes} WHERE status = 'active'"
        );
        
        foreach ($partners as $p) {
            $active_customers = self::get_active_customer_count($p->user_id);
            $last_attr_ts = $p->last_attribution_at ? strtotime($p->last_attribution_at) : 0;
            $now = time();
            
            // Deactivation logic (only if zero active customers)
            if ($active_customers == 0) {
                $months_since_attr = ($last_attr_ts === 0) ? 999 : ($now - $last_attr_ts) / (30 * 86400);
                
                // 11-12 months window: send deactivation warning (only once)
                if ($months_since_attr >= 11 && $months_since_attr < 12 && empty($p->deactivation_reminder_sent_at)) {
                    $wpdb->update(
                        $table_codes,
                        ['deactivation_reminder_sent_at' => current_time('mysql')],
                        ['id' => $p->id],
                        ['%s'],
                        ['%d']
                    );
                    
                    orabooks_log_event('partner_inactivity_reminder_sent', "Deactivation warning sent to partner {$p->user_id}", 'warning', [
                        'days' => 330,
                        'active_customer_count' => 0
                    ], $p->user_id, null);
                }
                
                // 12+ months: deactivate
                if ($months_since_attr >= 12) {
                    $wpdb->update(
                        $table_codes,
                        ['status' => 'inactive'],
                        ['id' => $p->id],
                        ['%s'],
                        ['%d']
                    );
                    
                    orabooks_log_event('partner_code_inactivated', "Partner code auto-deactivated for user {$p->user_id}", 'warning', [
                        'reason' => '12 months no attribution and zero active customers'
                    ], $p->user_id, null);
                }
            }
            
            // Low-activity reminder (regardless of active customers)
            // Send if no attribution for 6 months, repeat every 3 months
            $six_months = $now - (6 * 30 * 86400);
            $three_months_ago = $now - (3 * 30 * 86400);
            
            if ($last_attr_ts === 0 || $last_attr_ts < $six_months) {
                $reminder_sent = $p->low_activity_reminder_sent_at ? strtotime($p->low_activity_reminder_sent_at) : 0;
                if ($reminder_sent === 0 || $reminder_sent < $three_months_ago) {
                    $wpdb->update(
                        $table_codes,
                        ['low_activity_reminder_sent_at' => current_time('mysql')],
                        ['id' => $p->id],
                        ['%s'],
                        ['%d']
                    );
                    
                    orabooks_log_event('partner_low_activity_reminder_sent', "Low activity reminder sent to partner {$p->user_id}", 'info', [
                        'months' => 6
                    ], $p->user_id, null);
                }
            }
        }
    }
    
    /**
     * Get partner info (code, status, stats)
     */
    public static function get_partner_info($user_id) {
        global $wpdb;
        
        $table_codes = OraBooks_Database::table('partner_codes');
        $table_attributions = OraBooks_Database::table('partner_attributions');
        
        $code = $wpdb->get_row($wpdb->prepare(
            "SELECT pc.*, o.name as org_name, o.status as org_status
             FROM {$table_codes} pc
             JOIN {$wpdb->prefix}orabooks_organizations o ON pc.org_id = o.id
             WHERE pc.user_id = %d
             ORDER BY pc.created_at DESC
             LIMIT 1",
            $user_id
        ));
        
        if (!$code) {
            return null;
        }
        
        $active_customers = self::get_active_customer_count($user_id);
        
        $total_attributions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_attributions} WHERE partner_user_id = %d",
            $user_id
        ));
        
        $verified_attributions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_attributions} WHERE partner_user_id = %d AND status = 'verified'",
            $user_id
        ));
        
        return [
            'partner_code' => $code->partner_code,
            'partner_type' => $code->partner_type,
            'organization_name' => $code->organization_name,
            'status' => $code->status,
            'org_status' => $code->org_status,
            'created_at' => $code->created_at,
            'last_attribution_at' => $code->last_attribution_at,
            'active_customers' => $active_customers,
            'total_attributions' => $total_attributions,
            'verified_attributions' => $verified_attributions
        ];
    }

    public static function get_onboarding_info($user_id) {
        global $wpdb;

        $table_codes = OraBooks_Database::table('partner_codes');
        $table_orgs = OraBooks_Database::table('organizations');

        $code = $wpdb->get_row($wpdb->prepare(
            "SELECT pc.partner_code, pc.status as code_status, pc.partner_type, pc.organization_name,
                    pc.created_at, o.status as org_status, o.name as org_name
             FROM {$table_codes} pc
             JOIN {$table_orgs} o ON pc.org_id = o.id
             WHERE pc.user_id = %d
             ORDER BY pc.created_at DESC
             LIMIT 1",
            $user_id
        ));

        if (!$code) {
            return null;
        }

        return [
            'partner_code' => $code->partner_code,
            'code_status' => $code->code_status,
            'partner_type' => $code->partner_type,
            'organization_name' => $code->organization_name,
            'org_status' => $code->org_status,
            'org_name' => $code->org_name,
            'created_at' => $code->created_at,
            'status_message' => self::get_onboarding_status_message($code->code_status, $code->org_status),
            'bank_info_required' => false,
            'payment_settings_available' => false,
        ];
    }

    private static function get_onboarding_status_message($code_status, $org_status) {
        if ($org_status === 'suspended' || $code_status === 'disabled') {
            return 'Your partner code has been disabled. Contact support.';
        }

        switch ($code_status) {
            case 'pending_review':
                return 'Awaiting admin approval. Your code is not yet active.';
            case 'active':
                return 'Your code is active. Share it to earn commissions.';
            case 'inactive':
                return "Your partner code is inactive because you have no active customers and haven't brought any new customer in the last 12 months. Request reactivation from dashboard.";
            default:
                return 'Partner code status: ' . $code_status;
        }
    }
    
    /**
     * Request reactivation for inactive partner
     */
    public static function request_reactivation($user_id, $org_id, $reason) {
        global $wpdb;
        
        $table_codes = OraBooks_Database::table('partner_codes');
        
        $code = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_codes} WHERE user_id = %d AND status = 'inactive'",
            $user_id
        ));
        
        if (!$code) {
            return new WP_Error('not_inactive', 'Partner code is not inactive');
        }
        
        // Create reactivation review
        $result = OraBooks_Organization::request_partner_reactivation($org_id, $user_id, $reason);
        if (is_wp_error($result)) {
            return $result;
        }
        
        $wpdb->update(
            $table_codes,
            [
                'status' => 'pending_review',
                'disabled_at' => null,
                'disabled_reason' => null
            ],
            ['id' => $code->id],
            ['%s', null, null],
            ['%d']
        );
        
        return $result;
    }
    
    // ============================================================
    // SL-003: ADMIN PARTNER APPROVAL / REJECTION
    // ============================================================
    
    /**
     * Approve a pending partner code and activate the partner organization.
     *
     * Transitions: partner_codes.status: pending_review → active
     *              organizations.status: pending_setup → active
     *
     * @param int $partner_code_id ID of the partner_codes row
     * @param int $admin_id        Admin user ID performing the action
     * @return true|WP_Error
     */
    public static function approve_partner_code($partner_code_id, $admin_id) {
        global $wpdb;
        
        $table_codes = OraBooks_Database::table('partner_codes');
        $table_orgs = OraBooks_Database::table('organizations');
        
        $code = $wpdb->get_row($wpdb->prepare(
            "SELECT pc.*, o.status as org_status
             FROM {$table_codes} pc
             JOIN {$table_orgs} o ON pc.org_id = o.id
             WHERE pc.id = %d",
            $partner_code_id
        ));
        
        if (!$code) {
            return new WP_Error('not_found', 'Partner code not found.');
        }
        
        if ($code->status !== 'pending_review') {
            return new WP_Error('invalid_status', 'Partner code is not in pending_review status. Current status: ' . $code->status);
        }
        
        // Only update org status if it's in pending_setup
        if ($code->org_status === 'pending_setup') {
            $wpdb->update(
                $table_orgs,
                ['status' => 'active'],
                ['id' => $code->org_id],
                ['%s'],
                ['%d']
            );
        }
        
        // Update partner code to active
        $wpdb->update(
            $table_codes,
            [
                'status' => 'active',
                'approved_at' => current_time('mysql'),
                'approved_by' => $admin_id
            ],
            ['id' => $partner_code_id],
            ['%s', '%s', '%d'],
            ['%d']
        );
        
        // Audit log
        orabooks_log_event('partner_code_approved', "Partner code #{$code->partner_code} approved by admin #{$admin_id}", 'info', [
            'partner_code_id' => $partner_code_id,
            'partner_code' => $code->partner_code,
            'user_id' => $code->user_id,
            'org_id' => $code->org_id,
            'partner_type' => $code->partner_type
        ], $admin_id, $code->org_id);
        
        // Fire notification event
        do_action('orabooks_partner_code_approved', $code->org_id, [
            'partner_code_id' => $partner_code_id,
            'partner_code' => $code->partner_code,
            'user_id' => $code->user_id,
            'partner_type' => $code->partner_type
        ]);
        
        return true;
    }
    
    /**
     * Reject a pending partner code (disable with reason).
     *
     * Transitions: partner_codes.status: pending_review → disabled
     *
     * @param int    $partner_code_id ID of the partner_codes row
     * @param int    $admin_id        Admin user ID performing the action
     * @param string $reason          Reason for rejection
     * @return true|WP_Error
     */
    public static function reject_partner_code($partner_code_id, $admin_id, $reason = '') {
        global $wpdb;
        
        $table_codes = OraBooks_Database::table('partner_codes');
        $table_orgs = OraBooks_Database::table('organizations');
        
        $code = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_codes} WHERE id = %d",
            $partner_code_id
        ));
        
        if (!$code) {
            return new WP_Error('not_found', 'Partner code not found.');
        }
        
        if ($code->status !== 'pending_review') {
            return new WP_Error('invalid_status', 'Partner code is not in pending_review status. Current status: ' . $code->status);
        }
        
        $wpdb->update(
            $table_codes,
            [
                'status' => 'disabled',
                'disabled_at' => current_time('mysql'),
                'disabled_reason' => $reason ?: 'Rejected by administrator'
            ],
            ['id' => $partner_code_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        $wpdb->update(
            $table_orgs,
            ['status' => 'suspended'],
            ['id' => $code->org_id],
            ['%s'],
            ['%d']
        );
        
        // Audit log
        orabooks_log_event('partner_code_rejected', "Partner code #{$code->partner_code} rejected by admin #{$admin_id}: {$reason}", 'warning', [
            'partner_code_id' => $partner_code_id,
            'partner_code' => $code->partner_code,
            'user_id' => $code->user_id,
            'org_id' => $code->org_id,
            'reason' => $reason
        ], $admin_id, $code->org_id);
        
        return true;
    }
    
    /**
     * Get all pending partner codes (pending_review) for admin display
     */
    public static function get_pending_partners($args = []) {
        global $wpdb;
        
        $table_codes = OraBooks_Database::table('partner_codes');
        $table_users = OraBooks_Database::table('users');
        $table_orgs = OraBooks_Database::table('organizations');
        
        $limit = $args['limit'] ?? 50;
        $offset = $args['offset'] ?? 0;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pc.*, u.email, o.name as org_name, o.subdomain, o.status as org_status
                 FROM {$table_codes} pc
                 JOIN {$table_users} u ON pc.user_id = u.id
                 JOIN {$table_orgs} o ON pc.org_id = o.id
                 WHERE pc.status = 'pending_review'
                 ORDER BY pc.created_at DESC
                 LIMIT %d OFFSET %d",
                $limit, $offset
            )
        );
        
        return $results;
    }
    
    /**
     * Get active partners for admin display
     */
    public static function get_active_partners($args = []) {
        global $wpdb;
        
        $table_codes = OraBooks_Database::table('partner_codes');
        $table_users = OraBooks_Database::table('users');
        $table_orgs = OraBooks_Database::table('organizations');
        $table_attributions = OraBooks_Database::table('partner_attributions');
        
        $limit = $args['limit'] ?? 50;
        $offset = $args['offset'] ?? 0;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pc.*, u.email, o.name as org_name, o.subdomain, o.status as org_status,
                        (SELECT COUNT(*) FROM {$table_attributions} WHERE partner_user_id = pc.user_id AND status = 'verified') as verified_attributions
                 FROM {$table_codes} pc
                 JOIN {$table_users} u ON pc.user_id = u.id
                 JOIN {$table_orgs} o ON pc.org_id = o.id
                 WHERE pc.status = 'active'
                 ORDER BY pc.created_at DESC
                 LIMIT %d OFFSET %d",
                $limit, $offset
            )
        );
        
        return $results;
    }
    
    /**
     * Get pending reactivation requests for admin display
     */
    public static function get_reactivation_requests($args = []) {
        global $wpdb;
        
        $table_reviews = OraBooks_Database::table('partner_reactivation_reviews');
        $table_orgs = OraBooks_Database::table('organizations');
        $table_users = OraBooks_Database::table('users');
        $table_codes = OraBooks_Database::table('partner_codes');
        
        $limit = $args['limit'] ?? 50;
        $offset = $args['offset'] ?? 0;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, o.name as org_name, o.subdomain, u.email as requested_by_email,
                        pc.partner_code, pc.partner_type, pc.status as code_status
                 FROM {$table_reviews} r
                 JOIN {$table_orgs} o ON r.org_id = o.id
                 JOIN {$table_users} u ON r.requested_by = u.id
                 LEFT JOIN {$table_codes} pc ON pc.org_id = r.org_id
                 WHERE r.decision IS NULL
                 ORDER BY r.requested_at DESC
                 LIMIT %d OFFSET %d",
                $limit, $offset
            )
        );
        
        return $results;
    }
    
    // ============================================================
    // SL-139: PARTNER DASHBOARD DATA (unified endpoint)
    // ============================================================
    
    /**
     * Get full partner dashboard data (SL-139)
     * Combines: status flags, attribution stats, commission summary,
     * payout breakdown (gross/fee/net), attribution list, active customer count
     */
    public static function get_dashboard_data($user_id) {
        global $wpdb;
        
        $table_codes = OraBooks_Database::table('partner_codes');
        $table_orgs = OraBooks_Database::table('organizations');
        $table_attributions = OraBooks_Database::table('partner_attributions');
        $table_users = OraBooks_Database::table('users');
        $table_earned = OraBooks_Database::table('commissions_earned');
        $table_payouts = OraBooks_Database::table('commission_payouts');
        $table_active = OraBooks_Database::table('customer_active_status');
        
        // Get partner code and org info
        $partner = $wpdb->get_row($wpdb->prepare(
            "SELECT pc.*, o.status as org_status, o.name as org_name, o.organization_type
             FROM {$table_codes} pc
             JOIN {$table_orgs} o ON pc.org_id = o.id
             WHERE pc.user_id = %d
             ORDER BY pc.created_at DESC
             LIMIT 1",
            $user_id
        ));
        
        if (!$partner) {
            return null;
        }
        
        $org_id = $partner->org_id;
        
        // APPLY ACCESS RULES
        $is_blocked = false;
        $read_only = false;
        $payout_disabled = false;
        $can_reactivate = false;
        $org_status = $partner->org_status;
        $code_status = $partner->status;
        
        switch ($org_status) {
            case 'fraud_freeze':
                $is_blocked = true;
                break;
            case 'suspended':
                $read_only = true;
                break;
            case 'payout_hold':
                $payout_disabled = true;
                break;
        }
        
        if ($code_status === 'inactive') {
            $can_reactivate = true;
        }
        
        // Active customer count (using SL-021 customers.is_active as source of truth)
        $table_customers = OraBooks_Database::table('customers');
        $customers_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_customers}'");
        
        $active_customer_count = 0;
        if ($customers_table_exists) {
            $active_customer_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT pa.customer_user_id)
                 FROM {$table_attributions} pa
                 JOIN {$table_customers} c ON pa.customer_user_id = c.user_id
                 WHERE pa.partner_user_id = %d
                   AND pa.status = 'verified'
                   AND c.is_active = 1",
                $user_id
            ));
        }
        
        // Fallback if customers table doesn't exist or no active customers found
        if ($active_customer_count === 0) {
            if (!$customers_table_exists) {
                // Pre-SL-021: fallback to verified attribution count
                $active_customer_count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT pa.customer_user_id)
                     FROM {$table_attributions} pa
                     WHERE pa.partner_user_id = %d
                       AND pa.status = 'verified'",
                    $user_id
                ));
            }
        }
        
        // Compute dormant flag (single definition per spec)
        // Dormant = no active customers, but had attribution within 6-12 months ago
        $last_attr_ts = $partner->last_attribution_at ? strtotime($partner->last_attribution_at) : 0;
        $six_months_ago = time() - (6 * 30 * 86400);
        $twelve_months_ago = time() - (12 * 30 * 86400);
        
        $is_dormant = (
            $active_customer_count == 0 
            && $last_attr_ts > 0
            && $last_attr_ts < $six_months_ago
            && $last_attr_ts >= $twelve_months_ago
        );
        
        // ATTRIBUTION STATS
        $attribution_stats = (object) [
            'total' => 0,
            'verified' => 0,
            'pending' => 0
        ];
        
        $stats_raw = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
             FROM {$table_attributions}
             WHERE partner_user_id = %d",
            $user_id
        ));
        
        if ($stats_raw) {
            $attribution_stats = $stats_raw;
        }
        
        // COMMISSION SUMMARY (from SL-068)
        $commission_summary = [
            'total_earned' => 0,
            'pending_payout' => 0,
            'paid' => 0,
            'expired' => 0,
            'currency' => 'USD'
        ];
        
        if (class_exists('OraBooks_Commission') && method_exists('OraBooks_Commission', 'get_commission_stats')) {
            $stats = OraBooks_Commission::get_commission_stats($user_id);
            if ($stats) {
                $commission_summary = [
                    'total_earned' => $stats['total_earned'],
                    'pending_payout' => $stats['pending_payout'],
                    'paid' => $stats['total_paid'],
                    'expired' => $stats['total_expired'],
                    'currency' => 'USD'
                ];
            }
        }
        
        // PAYOUT BREAKDOWN (per payout batch with gross/fee/net)
        $payouts = [];
        if (class_exists('OraBooks_Commission') && method_exists('OraBooks_Commission', 'get_payouts')) {
            $payouts = OraBooks_Commission::get_payouts($user_id, ['limit' => 50]);
        }
        
        // Format payouts with period
        $payout_breakdown = [];
        foreach ($payouts as $p) {
            $payout_breakdown[] = [
                'period' => $p->payout_date ? date('Y-m', strtotime($p->payout_date)) : date('Y-m', strtotime($p->created_at)),
                'gross' => (float) $p->gross_amount,
                'fee' => (float) $p->fee_amount,
                'net' => (float) $p->net_amount,
                'status' => $p->status
            ];
        }
        
        // ATTRIBUTION LIST with masked emails
        $attributions = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                pa.id,
                pa.customer_email,
                pa.attribution_date,
                pa.status as attribution_status
             FROM {$table_attributions} pa
             WHERE pa.partner_user_id = %d
             ORDER BY pa.attribution_date DESC
             LIMIT 100",
            $user_id
        ));
        
        // Get commission status for each attribution
        $attr_with_commission = [];
        foreach ($attributions as $a) {
            $commission_status = '—';
            
            // Check if there's earned commission for this customer
            $earned = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM {$table_earned}
                 WHERE partner_user_id = %d AND customer_id = (SELECT id FROM {$table_users} WHERE email = %s)
                 ORDER BY earned_at DESC LIMIT 1",
                $user_id,
                $a->customer_email
            ));
            
            if ($earned) {
                $commission_status = $earned;
            } else {
                // Check if escrow exists
                $escrow_table = OraBooks_Database::table('commission_escrow_schedule');
                $customer_user = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table_users} WHERE email = %s",
                    $a->customer_email
                ));
                if ($customer_user) {
                    $escrow_exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$escrow_table} WHERE partner_user_id = %d AND customer_id = %d LIMIT 1",
                        $user_id, $customer_user
                    ));
                    if ($escrow_exists) {
                        $commission_status = 'qualified';
                    }
                }
            }
            
            $attr_with_commission[] = [
                'id' => $a->id,
                'customer_email_masked' => orabooks_mask_email($a->customer_email),
                'attribution_date' => $a->attribution_date,
                'attribution_status' => $a->attribution_status,
                'commission_status' => $commission_status
            ];
        }
        
        $is_inactive = ($code_status === 'inactive');
        $status_banner = self::get_dashboard_status_banner($org_status, $code_status, $is_dormant);

        return [
            'partner_code' => $partner->partner_code,
            'partner_type' => $partner->partner_type,
            'organization_name' => $partner->organization_name,
            'code_status' => $code_status,
            'org_status' => $org_status,
            'org_name' => $partner->org_name,
            'created_at' => $partner->created_at,
            'last_attribution_at' => $partner->last_attribution_at,
            'active_customer_count' => $active_customer_count,
            'is_dormant' => $is_dormant,
            'is_inactive' => $is_inactive,
            'is_blocked' => $is_blocked,
            'read_only' => $read_only,
            'payout_disabled' => $payout_disabled,
            'can_reactivate' => $can_reactivate,
            'new_attribution_blocked' => $is_inactive || $is_blocked,
            'status_banner' => $status_banner,
            'attribution_stats' => $attribution_stats,
            'commission_summary' => $commission_summary,
            'payout_breakdown' => $payout_breakdown,
            'attributions' => $attr_with_commission
        ];
    }

    private static function get_dashboard_status_banner($org_status, $code_status, $is_dormant) {
        if ($org_status === 'fraud_freeze') {
            return ['type' => 'blocked', 'message' => 'Partner program disabled.'];
        }

        if ($org_status === 'payout_hold') {
            return ['type' => 'warning', 'message' => 'Payout hold: commissions are being tracked but withdrawal is temporarily disabled.'];
        }

        if ($org_status === 'suspended') {
            return ['type' => 'readonly', 'message' => 'Partner program is readonly. Contact support for reactivation.'];
        }

        if ($code_status === 'inactive') {
            return ['type' => 'inactive', 'message' => 'Your partner program is inactive. You have no active customers and no new partner-code customer for 12 months. You cannot earn commissions until reactivated.'];
        }

        if ($is_dormant) {
            return ['type' => 'info', 'message' => 'You have no active customers. Share your partner code with new customers to earn commissions.'];
        }

        return null;
    }
    
    // ============================================================
    // SL-139: AJAX HANDLERS
    // ============================================================
    
    public function ajax_get_partner_info() {
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }
        
        // Rate limit: 60 per minute
        if (!orabooks_check_rate_limit('partner_info_' . $user_id, 60, 60)) {
            orabooks_json_error('Too many requests', 429);
        }
        
        $info = self::get_partner_info($user_id);
        
        if (!$info) {
            orabooks_json_error('No partner info found', 404);
        }
        
        // Audit event (MUST be before json_success which exits)
        orabooks_log_event('partner_onboarding_viewed', 'Partner viewed onboarding page', 'info', [
            'partner_user_id' => $user_id
        ], $user_id, null);
        
        orabooks_json_success($info);
    }

    public function ajax_partner_onboarding() {
        $user_id = get_current_user_id();

        if (!orabooks_check_rate_limit('partner_onboarding_' . $user_id, 60, 60)) {
            orabooks_json_error('Too many requests', 429);
        }

        $info = self::get_onboarding_info($user_id);

        if (!$info) {
            orabooks_json_error('No partner onboarding info found', 404);
        }

        orabooks_log_event('partner_onboarding_viewed', 'Partner viewed onboarding page', 'info', [
            'partner_user_id' => $user_id,
            'code_status' => $info['code_status']
        ], $user_id, null);

        orabooks_json_success($info);
    }
    
    public function ajax_request_reactivation() {
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }
        $org_id = intval($_POST['org_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');
        
        if (!orabooks_check_rate_limit('reactivate_' . $user_id, 5, 3600)) {
            orabooks_json_error('Too many reactivation attempts. Please try again later.', 429);
        }
        
        $result = self::request_reactivation($user_id, $org_id, $reason);
        
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        
        // Audit: reactivation requested
        orabooks_log_event('partner_reactivation_requested', 'Partner requested reactivation', 'info', [
            'reason' => $reason,
            'active_customer_count' => self::get_active_customer_count($user_id)
        ], $user_id, $org_id);
        
        orabooks_json_success([], 'Reactivation request submitted for review. An admin will review your request.');
    }
    
    /**
     * SL-139: Full partner dashboard data endpoint
     */
    public function ajax_partner_dashboard() {
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }
        
        // Rate limit: 60 per minute
        if (!orabooks_check_rate_limit('partner_dash_' . $user_id, 60, 60)) {
            orabooks_json_error('Too many requests', 429);
        }
        
        // RBAC check: must have partner_commission_access
        global $wpdb;
        $table_users = OraBooks_Database::table('users');
        $user_org = $wpdb->get_var($wpdb->prepare(
            "SELECT org_id FROM {$table_users} WHERE id = %d",
            $user_id
        ));
        
        if ($user_org) {
            if (!OraBooks_RBAC::require_permission($user_id, $user_org, 'partner_commission_access')) {
                orabooks_log_event('partner_dashboard_blocked', 'Dashboard access denied: missing partner_commission_access', 'warning', [
                    'user_id' => $user_id,
                    'org_id' => $user_org
                ], $user_id, $user_org);
                orabooks_json_error('Access denied. You do not have partner commission access.', 403);
            }
        }
        
        $data = self::get_dashboard_data($user_id);
        
        if ($data === null) {
            orabooks_json_error('No partner data found', 404);
        }
        
        // Apply access rules
        if ($data['is_blocked']) {
            orabooks_log_event('partner_dashboard_blocked', 'Dashboard access blocked: fraud_freeze', 'warning', [
                'org_status' => $data['org_status']
            ], $user_id, null);
            orabooks_json_error('Partner program disabled due to fraud detection. Contact support.', 403);
        }
        
        // Audit: dashboard viewed (MUST be before json_success which exits)
        orabooks_log_event('partner_dashboard_viewed', 'Partner viewed dashboard', 'info', [
            'partner_user_id' => $user_id,
            'org_status' => $data['org_status']
        ], $user_id, null);
        
        orabooks_json_success($data);
    }
    
    /**
     * Track partner code copy (audit event)
     */
    public function ajax_code_copied() {
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }
        $source = sanitize_text_field($_POST['source'] ?? 'dashboard');
        
        orabooks_log_event('partner_code_copied', 'Partner copied their code', 'info', [
            'source' => $source,
            'ip' => orabooks_get_client_ip()
        ], $user_id, null);
        
        orabooks_json_success([], 'copied');
    }
    
    /**
     * Get attribution list for the partner
     */
    public function ajax_partner_attributions() {
        $user_id = get_current_user_id();
        
        if (!orabooks_check_rate_limit('partner_attr_' . $user_id, 60, 60)) {
            orabooks_json_error('Too many requests', 429);
        }
        
        global $wpdb;
        $table = OraBooks_Database::table('partner_attributions');
        
        $attributions = $wpdb->get_results($wpdb->prepare(
            "SELECT id, customer_email, attribution_date, status, created_at
             FROM {$table}
             WHERE partner_user_id = %d
             ORDER BY attribution_date DESC
             LIMIT 100",
            $user_id
        ));
        
        foreach ($attributions as &$a) {
            $a->customer_email_masked = orabooks_mask_email($a->customer_email);
        }
        
        orabooks_json_success($attributions);
    }

    public function ajax_payment_settings() {
        orabooks_json_error('Partner payment settings are not implemented in MVP. This is reserved for SL-140.', 501);
    }

    public function ajax_partner_application() {
        orabooks_json_error('Partner applications for existing customers are not implemented in MVP. This is reserved for SL-140.', 501);
    }
    
    // ============================================================
    // SL-003: ADMIN AJAX HANDLERS
    // ============================================================
    
    /**
     * AJAX: Approve a pending partner code
     */
    public function ajax_admin_approve_partner() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $partner_code_id = intval($_POST['partner_code_id'] ?? 0);
        $admin_id = get_current_user_id();
        
        if (!$partner_code_id) {
            orabooks_json_error('Invalid partner code ID', 400);
        }
        
        $result = self::approve_partner_code($partner_code_id, $admin_id);
        
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        
        orabooks_json_success([], 'Partner code approved and organization activated.');
    }
    
    /**
     * AJAX: Reject a pending partner code
     */
    public function ajax_admin_reject_partner() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $partner_code_id = intval($_POST['partner_code_id'] ?? 0);
        $admin_id = get_current_user_id();
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');
        
        if (!$partner_code_id) {
            orabooks_json_error('Invalid partner code ID', 400);
        }
        
        $result = self::reject_partner_code($partner_code_id, $admin_id, $reason);
        
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        
        orabooks_json_success([], 'Partner code rejected.');
    }
    
    /**
     * AJAX: List pending partner codes for admin
     */
    public function ajax_admin_list_pending_partners() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $partners = self::get_pending_partners();
        orabooks_json_success($partners);
    }
    
    /**
     * AJAX: List active partners for admin
     */
    public function ajax_admin_list_active_partners() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $partners = self::get_active_partners();
        orabooks_json_success($partners);
    }
    
    /**
     * AJAX: List pending reactivation requests for admin
     */
    public function ajax_admin_list_reactivation_requests() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $requests = self::get_reactivation_requests();
        orabooks_json_success($requests);
    }
    
    /**
     * AJAX: Review (approve/deny) a reactivation request
     */
    public function ajax_admin_review_reactivation() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $review_id = intval($_POST['review_id'] ?? 0);
        $decision = sanitize_text_field($_POST['decision'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $admin_id = get_current_user_id();
        
        if (!$review_id || !in_array($decision, ['approved', 'denied'])) {
            orabooks_json_error('Invalid request', 400);
        }
        
        // Update the partner code status for reactivation
        if ($decision === 'approved') {
            global $wpdb;
            $table_reviews = OraBooks_Database::table('partner_reactivation_reviews');
            $review = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_reviews} WHERE id = %d", $review_id
            ));
            
            if ($review) {
                $table_codes = OraBooks_Database::table('partner_codes');
                $wpdb->update(
                    $table_codes,
                    [
                        'status' => 'active',
                        'approved_at' => current_time('mysql'),
                        'approved_by' => $admin_id
                    ],
                    ['org_id' => $review->org_id],
                    ['%s', '%s', '%d'],
                    ['%d']
                );
            }
        }
        
        $result = OraBooks_Organization::review_reactivation($review_id, $admin_id, $decision, $notes);
        
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        
        orabooks_json_success([], 'Reactivation request ' . ($decision === 'approved' ? 'approved.' : 'denied.'));
    }
}