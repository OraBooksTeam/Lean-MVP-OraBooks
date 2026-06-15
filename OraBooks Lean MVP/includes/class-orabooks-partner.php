<?php
/**
 * OraBooks Partner Management (SL-013/139 extensions)
 * 
 * Partner code management, attribution processing, inactivity management,
 * and partner dashboard data.
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
    
    // AJAX handlers
    public function ajax_get_partner_info() {
        $user_id = get_current_user_id();
        $info = self::get_partner_info($user_id);
        
        if (!$info) {
            orabooks_json_error('No partner info found', 404);
        }
        
        orabooks_json_success($info);
    }
    
    public function ajax_request_reactivation() {
        $user_id = get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');
        
        if (empty($reason)) {
            orabooks_json_error('Reason is required', 400);
        }
        
        $result = self::request_reactivation($user_id, $org_id, $reason);
        
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        
        orabooks_json_success([], 'Reactivation request submitted for review');
    }
}