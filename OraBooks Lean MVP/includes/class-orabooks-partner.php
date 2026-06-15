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
            add_action('wp_ajax_orabooks_request_reactivation', [self::$instance, 'ajax_request_reactivation']);
            
            // SL-139: Partner Dashboard endpoints
            add_action('wp_ajax_orabooks_partner_dashboard', [self::$instance, 'ajax_partner_dashboard']);
            add_action('wp_ajax_orabooks_partner_code_copied', [self::$instance, 'ajax_code_copied']);
            add_action('wp_ajax_orabooks_partner_attributions', [self::$instance, 'ajax_partner_attributions']);
        }
        return self::$instance;
    }
    
    /**
     * Get active customer count for a partner
     */
    public static function get_active_customer_count($partner_user_id) {
        global $wpdb;
        
        $table_attributions = OraBooks_Database::table('partner_attributions');
        
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
            $last_attr = $p->last_attribution_at ? strtotime($p->last_attribution_at) : 0;
            $now = time();
            
            // Deactivation logic (only if zero active customers)
            if ($active_customers == 0) {
                $eleven_months = $now - (11 * 30 * 86400);
                $twelve_months = $now - (12 * 30 * 86400);
                
                // 11 months - send deactivation warning
                if ($last_attr < $eleven_months && empty($p->deactivation_reminder_sent_at)) {
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
                
                // 12 months - deactivate
                if ($last_attr < $twelve_months) {
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
            $six_months = $now - (6 * 30 * 86400);
            $three_months_ago = $now - (3 * 30 * 86400);
            
            if ($last_attr < $six_months) {
                $reminder_sent = $p->low_activity_reminder_sent_at ? strtotime($p->low_activity_reminder_sent_at) : 0;
                if ($reminder_sent < $three_months_ago) {
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
        
        return $result;
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
        
        // Active customer count (using SL-068's customer_active_status read model)
        $active_customer_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT pa.customer_user_id)
             FROM {$table_attributions} pa
             JOIN {$table_active} cas ON cas.customer_id = pa.customer_user_id
             WHERE pa.partner_user_id = %d
               AND pa.status = 'verified'
               AND cas.is_active = 1",
            $user_id
        ));
        
        // Fallback if customer_active_status table is empty
        if ($active_customer_count === 0) {
            $active_customer_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT pa.customer_user_id)
                 FROM {$table_attributions} pa
                 WHERE pa.partner_user_id = %d
                   AND pa.status = 'verified'",
                $user_id
            ));
        }
        
        // Compute dormant flag (single definition per spec)
        $last_attr_ts = $partner->last_attribution_at ? strtotime($partner->last_attribution_at) : 0;
        $six_months_ago = time() - (6 * 30 * 86400);
        $twelve_months_ago = time() - (12 * 30 * 86400);
        
        $is_dormant = (
            $active_customer_count == 0 
            && $last_attr_ts > 0
            && $last_attr_ts >= $six_months_ago 
            && $last_attr_ts < $twelve_months_ago
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
            'is_blocked' => $is_blocked,
            'read_only' => $read_only,
            'payout_disabled' => $payout_disabled,
            'can_reactivate' => $can_reactivate,
            'attribution_stats' => $attribution_stats,
            'commission_summary' => $commission_summary,
            'payout_breakdown' => $payout_breakdown,
            'attributions' => $attr_with_commission
        ];
    }
    
    // ============================================================
    // SL-139: AJAX HANDLERS
    // ============================================================
    
    public function ajax_get_partner_info() {
        $user_id = get_current_user_id();
        
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
    
    public function ajax_request_reactivation() {
        $user_id = get_current_user_id();
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
        $user_id = get_current_user_id();
        
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
        $user_id = get_current_user_id();
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
}