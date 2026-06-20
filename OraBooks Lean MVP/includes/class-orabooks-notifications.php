<?php
/**
 * OraBooks Notification Center (SL-250)
 *
 * Centralized notification orchestration engine with multi-region routing,
 * smart deduplication, cost governance, adaptive throttling, tenant policies,
 * cross-channel escalation, signed audit bundles, mobile device lifecycle,
 * and provider health scoring.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Notifications {

    private static $instance = null;
    private static $channel_costs = [
        'email' => 0.0001,
        'push'  => 0.00005,
        'inapp' => 0.00001,
    ];

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            add_action('init', [self::$instance, 'register_cron_hooks']);
            
            // Listen for partner commission events (SL-068 integration)
            add_action('orabooks_payout_batch_created', [self::$instance, 'on_payout_batch_created'], 10, 2);
            add_action('orabooks_payout_settled', [self::$instance, 'on_payout_settled'], 10, 2);
            
            // Listen for partner lifecycle events (SL-013/SL-139 integration)
            add_action('orabooks_partner_reactivation_requested', [self::$instance, 'on_partner_reactivation_requested'], 10, 2);
            add_action('orabooks_partner_reactivation_approved', [self::$instance, 'on_partner_reactivation_approved'], 10, 2);
            add_action('orabooks_partner_inactivity_reminder_sent', [self::$instance, 'on_partner_inactivity_reminder_sent'], 10, 2);
            add_action('orabooks_partner_code_inactivated', [self::$instance, 'on_partner_code_inactivated'], 10, 2);
            add_action('orabooks_partner_low_activity_reminder_sent', [self::$instance, 'on_partner_low_activity_reminder_sent'], 10, 2);

            // Listen for export events (SL-114 integration)
            add_action('orabooks_export_ready', [self::$instance, 'on_export_ready'], 10, 2);
            add_action('orabooks_export_failed', [self::$instance, 'on_export_failed'], 10, 2);

            // Operational / projection alerts (SL-075, SL-074 integration)
            add_action('orabooks_inventory_low_stock_alert', [self::$instance, 'on_inventory_low_stock_alert'], 10, 2);
            add_action('orabooks_projection_integrity_failed', [self::$instance, 'on_projection_integrity_failed'], 10, 2);

            // CSV import alerts (SL-113 integration)
            add_action('orabooks_csv_import_completed', [self::$instance, 'on_csv_import_completed'], 10, 2);
            add_action('orabooks_csv_import_failed', [self::$instance, 'on_csv_import_failed'], 10, 2);
            add_action('orabooks_csv_row_escalated', [self::$instance, 'on_csv_row_escalated'], 10, 2);

            // Listen for invoice events (SL-021 integration)
            add_action('orabooks_invoice_created', [self::$instance, 'on_invoice_created'], 10, 2);
            add_action('orabooks_payment_recorded', [self::$instance, 'on_payment_recorded'], 10, 2);
            add_action('orabooks_invoices_marked_overdue', [self::$instance, 'on_invoices_marked_overdue'], 10, 2);

            // AJAX: Notification Center
            add_action('wp_ajax_orabooks_notifications_list', [self::$instance, 'ajax_list_notifications']);
            add_action('wp_ajax_orabooks_notifications_mark_read', [self::$instance, 'ajax_mark_read']);
            add_action('wp_ajax_orabooks_notifications_mark_all_read', [self::$instance, 'ajax_mark_all_read']);
            add_action('wp_ajax_orabooks_notifications_unread_count', [self::$instance, 'ajax_unread_count']);
            add_action('wp_ajax_nopriv_orabooks_notifications_list', [self::$instance, 'ajax_list_notifications']);
            add_action('wp_ajax_nopriv_orabooks_notifications_mark_read', [self::$instance, 'ajax_mark_read']);
            add_action('wp_ajax_nopriv_orabooks_notifications_mark_all_read', [self::$instance, 'ajax_mark_all_read']);
            add_action('wp_ajax_nopriv_orabooks_notifications_unread_count', [self::$instance, 'ajax_unread_count']);

            // AJAX: Preferences
            add_action('wp_ajax_orabooks_notification_preferences_get', [self::$instance, 'ajax_get_preferences']);
            add_action('wp_ajax_nopriv_orabooks_notification_preferences_get', [self::$instance, 'ajax_get_preferences']);
            add_action('wp_ajax_orabooks_notification_preferences_save', [self::$instance, 'ajax_save_preferences']);
            add_action('wp_ajax_nopriv_orabooks_notification_preferences_save', [self::$instance, 'ajax_save_preferences']);

            // AJAX: Admin (Owner only)
            add_action('wp_ajax_orabooks_notification_admin_policy_get', [self::$instance, 'ajax_get_org_policy']);
            add_action('wp_ajax_nopriv_orabooks_notification_admin_policy_get', [self::$instance, 'ajax_get_org_policy']);
            add_action('wp_ajax_orabooks_notification_admin_policy_save', [self::$instance, 'ajax_save_org_policy']);
            add_action('wp_ajax_nopriv_orabooks_notification_admin_policy_save', [self::$instance, 'ajax_save_org_policy']);
            add_action('wp_ajax_orabooks_notification_admin_provider_health', [self::$instance, 'ajax_provider_health']);
            add_action('wp_ajax_nopriv_orabooks_notification_admin_provider_health', [self::$instance, 'ajax_provider_health']);
            add_action('wp_ajax_orabooks_notification_admin_audit_export', [self::$instance, 'ajax_audit_export']);
            add_action('wp_ajax_nopriv_orabooks_notification_admin_audit_export', [self::$instance, 'ajax_audit_export']);

            // AJAX: Mobile device registration
            add_action('wp_ajax_orabooks_register_device', [self::$instance, 'ajax_register_device']);
        }
        return self::$instance;
    }

    /**
     * Register cron hooks
     */
    public function register_cron_hooks() {
        add_action('orabooks_notification_provider_health_update', [self::$instance, 'cron_update_provider_health']);
        add_action('orabooks_notification_sla_check', [self::$instance, 'cron_sla_compliance_check']);
        add_action('orabooks_notification_device_cleanup', [self::$instance, 'cron_deactivate_stale_devices']);
        add_action('orabooks_notification_delivery_retry', [self::$instance, 'cron_retry_deliveries']);
        add_action('orabooks_daily_overdue_digest', [self::$instance, 'cron_send_overdue_digest']);
    }

    // ================================================================
    // CORE SEND NOTIFICATION
    // ================================================================

    /**
     * Main notification sending orchestrator.
     *
     * @param int    $user_id        Target user ID.
     * @param string $event_type     Event type (e.g. 'payout_batch_created').
     * @param array  $payload        {
     *     Optional. Payload data.
     *     @type string $title        Notification title.
     *     @type string $message      Notification message body.
     *     @type string $priority     'critical','high','normal','low'. Default 'normal'.
     *     @type string $correlation_id Correlation ID. Generated if omitted.
     * }
     * @param int    $org_id         Organization ID. Auto-resolved if omitted.
     * @return int|false Notification ID on success, false on dedup skip/error.
     */
    public static function send_notification($user_id, $event_type, $payload = [], $org_id = null) {
        global $wpdb;

        // Resolve org_id
        if (!$org_id) {
            $user = self::get_orabooks_user($user_id);
            $org_id = $user ? $user->org_id : 0;
        }

        $priority = !empty($payload['priority']) ? $payload['priority'] : 'normal';
        $correlation_id = !empty($payload['correlation_id']) ? $payload['correlation_id'] : orabooks_uuid();
        $title = !empty($payload['title']) ? $payload['title'] : '';
        $message = !empty($payload['message']) ? $payload['message'] : '';

        // Critical bypasses dedup & budget
        if ($priority !== 'critical') {
            // Smart deduplication (60s window)
            if (self::should_deduplicate($event_type, $user_id, $payload)) {
                return false;
            }

            // Budget check
            if (!self::check_budget($org_id, $priority)) {
                // Throttle: store as pending for later delivery
                $payload['_throttled'] = true;
            }
        }

        // Create notification record
        $table_notifications = OraBooks_Database::table('notifications');
        $wpdb->insert($table_notifications, [
            'org_id'           => $org_id,
            'user_id'          => $user_id,
            'event_type'       => $event_type,
            'priority'         => $priority,
            'title'            => $title,
            'message'          => $message,
            'payload'          => json_encode($payload),
            'correlation_id'   => $correlation_id,
            'status'           => 'pending',
            'created_at'       => current_time('mysql', true),
        ], ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);

        $notification_id = $wpdb->insert_id;
        if (!$notification_id) {
            return false;
        }

        // Resolve dependency graph
        self::resolve_dependencies($event_type, $user_id, $correlation_id);

        // Get user preferences and org policy
        $user_prefs = self::get_user_preferences($user_id);
        $org_policy = self::get_org_policy($org_id);
        $channels = self::resolve_channels($event_type, $user_prefs, $org_policy);
        $max_escalation = !empty($org_policy) ? (int)$org_policy->max_escalation_attempts : 3;

        // Attempt delivery with cross-channel escalation
        $delivered = false;
        $attempted_channels = [];
        foreach ($channels as $channel) {
            if (count($attempted_channels) >= $max_escalation) {
                break;
            }
            $attempted_channels[] = $channel;

            // Multi-region routing
            $provider = self::select_provider($channel, $org_id);

            $result = self::attempt_delivery($notification_id, $channel, $provider, $user_id, $title, $message, $payload);

            if ($result['success']) {
                // Log cost
                self::log_delivery_cost($org_id, $notification_id, $channel, $provider, true);

                $wpdb->update(
                    $table_notifications,
                    [
                        'status'           => 'delivered',
                        'delivered_at'     => current_time('mysql', true),
                        'delivery_channel' => $channel,
                        'delivery_proof'   => json_encode($result['proof']),
                    ],
                    ['id' => $notification_id],
                    ['%s', '%s', '%s', '%s'],
                    ['%d']
                );

                $delivered = true;
                break;
            } else {
                // Log cost for failed attempt
                self::log_delivery_cost($org_id, $notification_id, $channel, $provider, false);
            }
        }

        if (!$delivered) {
            $wpdb->update(
                $table_notifications,
                ['status' => 'dead_letter'],
                ['id' => $notification_id],
                ['%s'],
                ['%d']
            );

            orabooks_log_event('notification_dead_letter', "Notification {$notification_id} reached dead letter", 'warning', [
                'event_type'   => $event_type,
                'user_id'      => $user_id,
                'channels'     => $attempted_channels,
                'org_id'       => $org_id,
            ], null, $org_id);
        }

        // Log audit event
        orabooks_log_event('notification_sent', "Notification {$event_type} sent to user {$user_id}", 'info', [
            'notification_id' => $notification_id,
            'priority'        => $priority,
            'correlation_id'  => $correlation_id,
            'delivered'       => $delivered,
        ], null, $org_id);

        return $notification_id;
    }

    // ================================================================
    // SMART DEDUPLICATION
    // ================================================================

    /**
     * Check if a notification should be deduplicated.
     * Critical events bypass dedup.
     */
    private static function should_deduplicate($event_type, $user_id, $payload) {
        global $wpdb;

        if (!empty($payload['priority']) && $payload['priority'] === 'critical') {
            return false;
        }

        $canonical = self::canonicalize($payload);
        $dedup_key = hash('sha256', "{$event_type}:{$user_id}:{$canonical}");

        $table_dedup = OraBooks_Database::table('notification_dedup_log');
        $window = date('Y-m-d H:i:s', time() - 60);

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT suppressed_count FROM {$table_dedup} WHERE dedup_key = %s AND first_occurrence >= %s",
            $dedup_key, $window
        ));

        if ($existing) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_dedup} SET suppressed_count = suppressed_count + 1, last_occurrence = %s WHERE dedup_key = %s",
                current_time('mysql', true), $dedup_key
            ));
            return true;
        }

        $wpdb->insert($table_dedup, [
            'dedup_key'       => $dedup_key,
            'first_occurrence' => current_time('mysql', true),
            'last_occurrence'  => current_time('mysql', true),
            'suppressed_count' => 0,
        ], ['%s', '%s', '%s', '%d']);

        return false;
    }

    /**
     * Normalize payload for dedup comparison
     */
    private static function canonicalize($payload) {
        // Normalize whitespace, numbers, case
        $flat = '';
        if (is_array($payload)) {
            $flat = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            $flat = (string)$payload;
        }
        // Normalize whitespace
        $flat = preg_replace('/\s+/', ' ', trim($flat));
        // Lowercase
        $flat = strtolower($flat);
        return $flat;
    }

    // ================================================================
    // DEPENDENCY RESOLUTION
    // ================================================================

    private static function resolve_dependencies($event_type, $user_id, $correlation_id) {
        global $wpdb;
        $table_deps = OraBooks_Database::table('notification_dependencies');
        $table_notifications = OraBooks_Database::table('notifications');

        $deps = $wpdb->get_results($wpdb->prepare(
            "SELECT depends_on FROM {$table_deps} WHERE event_type = %s",
            $event_type
        ));

        if (empty($deps)) {
            return;
        }

        // Check dependent notifications are already delivered
        foreach ($deps as $dep) {
            $depends_on = is_object($dep) ? ($dep->depends_on ?? '') : '';
            if ($depends_on === '') {
                continue;
            }

            $delivered = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_notifications} WHERE event_type = %s AND user_id = %d AND correlation_id = %s AND status = 'delivered'",
                $depends_on, $user_id, $correlation_id
            ));

            if (!$delivered) {
                // Dependency not met, schedule this notification for later check
                // For MVP, we'll just proceed and mark the dependency gap in metadata
                orabooks_log_event('notification_dependency_miss', "Dependency {$depends_on} not met for {$event_type}", 'info', [
                    'user_id'        => $user_id,
                    'correlation_id' => $correlation_id,
                ]);
            }
        }
    }

    // ================================================================
    // CHANNEL RESOLUTION
    // ================================================================

    private static function decode_json_field($value, $default = []) {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return $default;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $default;
    }

    private static function resolve_channels($event_type, $user_prefs, $org_policy) {
        $default_channels = ['email', 'inapp'];

        // Org policy overrides
        if (!empty($org_policy)) {
            $mandatory = self::decode_json_field($org_policy->mandatory_event_types ?? null);
            $prohibited = self::decode_json_field($org_policy->prohibited_channels ?? null);
            $fallback = self::decode_json_field($org_policy->escalation_fallback_chain ?? null);

            if (in_array($event_type, $mandatory)) {
                return $fallback ?: ['email', 'push'];
            }

            // Filter out prohibited channels
            if (!empty($prohibited)) {
                $default_channels = array_values(array_diff($default_channels, $prohibited));
            }
        }

        // User preferences
        if (!empty($user_prefs) && !empty($user_prefs->channels)) {
            $pref_channels = self::decode_json_field($user_prefs->channels);
            if (!empty($pref_channels)) {
                // Use preferred channels, but respect prohibitions
                if (!empty($org_policy)) {
                    $prohibited = self::decode_json_field($org_policy->prohibited_channels ?? null);
                    $pref_channels = array_values(array_diff($pref_channels, $prohibited));
                }
                return $pref_channels;
            }
        }

        return $default_channels;
    }

    // ================================================================
    // MULTI-REGION PROVIDER SELECTION
    // ================================================================

    private static function select_provider($channel, $org_id) {
        global $wpdb;

        // Get org region
        $org_region = 'us-east';
        if ($org_id) {
            $table_orgs = OraBooks_Database::table('organizations');
            $org = $wpdb->get_var($wpdb->prepare(
                "SELECT region FROM {$table_orgs} WHERE id = %d", $org_id
            ));
            if ($org) {
                $org_region = $org;
            }
        }

        $table_health = OraBooks_Database::table('delivery_provider_health');

        // Try user/org region first with healthy providers
        $provider = $wpdb->get_row($wpdb->prepare(
            "SELECT provider_name, region, avg_latency_ms 
             FROM {$table_health} 
             WHERE channel = %s AND region = %s AND health_score > 50 
             ORDER BY avg_latency_ms ASC 
             LIMIT 1",
            $channel, $org_region
        ));

        if ($provider) {
            return $provider->provider_name;
        }

        // Fallback to any healthy provider
        $provider = $wpdb->get_row($wpdb->prepare(
            "SELECT provider_name, region 
             FROM {$table_health} 
             WHERE channel = %s AND health_score > 50 
             ORDER BY avg_latency_ms ASC 
             LIMIT 1",
            $channel
        ));

        if ($provider) {
            return $provider->provider_name;
        }

        // Ultimate fallback
        return self::default_provider($channel);
    }

    private static function default_provider($channel) {
        $defaults = [
            'email' => 'aws_ses',
            'push'  => 'fcm',
            'inapp' => 'local',
        ];
        return $defaults[$channel] ?? 'unknown';
    }

    // ================================================================
    // DELIVERY ATTEMPT (stub - real providers would be integrated)
    // ================================================================

    private static function attempt_delivery($notification_id, $channel, $provider, $user_id, $title, $message, $payload) {
        // MVP stub: mark as success for inapp, log for others
        // Future: integrate with AWS SES, FCM, etc.
        
        $proof = [
            'notification_id' => $notification_id,
            'channel'         => $channel,
            'provider'        => $provider,
            'timestamp'       => current_time('mysql', true),
            'signature'       => hash_hmac('sha256', "{$notification_id}:{$channel}:{$provider}", defined('ORABOOKS_JWT_SECRET') ? ORABOOKS_JWT_SECRET : 'orabooks-default'),
        ];

        $success = true; // MVP: assume success

        if ($channel === 'inapp') {
            // In-app delivery is always successful
            return ['success' => true, 'proof' => $proof];
        }

        // For external channels (email, push), log attempt
        orabooks_log_event('notification_delivery_attempt', "Delivery attempt: {$channel}/{$provider}", 'info', [
            'notification_id' => $notification_id,
            'channel'         => $channel,
            'provider'        => $provider,
            'success'         => $success,
        ]);

        return ['success' => $success, 'proof' => $proof];
    }

    // ================================================================
    // COST GOVERNANCE
    // ================================================================

    private static function check_budget($org_id, $priority) {
        if ($priority === 'critical') {
            return true;
        }

        $policy = self::get_org_policy($org_id);
        if (empty($policy) || empty($policy->monthly_budget)) {
            return true; // No budget cap
        }

        $budget = (float)$policy->monthly_budget;
        $current_cost = self::get_monthly_cost($org_id);

        // Estimate cost of this notification
        $estimated = 0.0001; // default estimate

        return ($current_cost + $estimated) <= $budget;
    }

    private static function get_monthly_cost($org_id) {
        global $wpdb;
        $table = OraBooks_Database::table('notification_cost');
        $first_of_month = date('Y-m-01 00:00:00');

        return (float)$wpdb->get_var($wpdb->prepare(
            "SELECT SUM(cost) FROM {$table} WHERE org_id = %s AND created_at >= %s",
            $org_id, $first_of_month
        ));
    }

    private static function log_delivery_cost($org_id, $notification_id, $channel, $provider, $success) {
        global $wpdb;

        if (empty(self::$channel_costs[$channel])) {
            return;
        }

        $cost = $success ? self::$channel_costs[$channel] : self::$channel_costs[$channel] * 0.5; // half cost for failed

        $table = OraBooks_Database::table('notification_cost');
        $wpdb->insert($table, [
            'org_id'          => $org_id,
            'notification_id' => $notification_id,
            'channel'         => $channel,
            'provider'        => $provider,
            'cost'            => $cost,
            'created_at'      => current_time('mysql', true),
        ], ['%d', '%d', '%s', '%s', '%f', '%s']);
    }

    // ================================================================
    // USER PREFERENCES
    // ================================================================

    /**
     * Get or initialize user notification preferences.
     * Preferences are stored as user meta prefixed with 'orabooks_notify_'.
     */
    private static function get_user_preferences($user_id) {
        $prefs = get_user_meta($user_id, 'orabooks_notification_prefs', true);
        if (!$prefs) {
            // Default preferences
            $prefs = [
                'channels'            => ['email', 'inapp'],
                'quiet_hours_start'   => '',
                'quiet_hours_end'     => '',
                'digest'              => 'none', // none, daily, weekly
                'language'            => 'en',
                'escalation_enabled'  => true,
                'updated_at'          => current_time('mysql', true),
            ];
            update_user_meta($user_id, 'orabooks_notification_prefs', $prefs);
        }
        return (object)$prefs;
    }

    /**
     * Save user notification preferences.
     */
    public static function save_user_preferences($user_id, $data) {
        $prefs = get_user_meta($user_id, 'orabooks_notification_prefs', true) ?: [];

        $allowed_fields = ['channels', 'quiet_hours_start', 'quiet_hours_end', 'digest', 'language', 'escalation_enabled'];
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                if ($field === 'channels' && is_array($data[$field])) {
                    $prefs[$field] = array_map('sanitize_text_field', $data[$field]);
                } elseif ($field === 'escalation_enabled') {
                    $prefs[$field] = (bool)$data[$field];
                } else {
                    $prefs[$field] = sanitize_text_field($data[$field]);
                }
            }
        }
        $prefs['updated_at'] = current_time('mysql', true);

        update_user_meta($user_id, 'orabooks_notification_prefs', $prefs);
        return $prefs;
    }

    // ================================================================
    // ORG POLICIES
    // ================================================================

    private static function get_org_policy($org_id) {
        global $wpdb;
        $table = OraBooks_Database::table('org_notification_policies');
        $policy = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d", $org_id
        ));
        return $policy;
    }

    /**
     * Save/update org notification policy (Owner only).
     */
    public static function save_org_policy($org_id, $data) {
        global $wpdb;
        $table = OraBooks_Database::table('org_notification_policies');

        $fields = [
            'monthly_budget'          => isset($data['monthly_budget']) ? (float)$data['monthly_budget'] : null,
            'mandatory_event_types'   => isset($data['mandatory_event_types']) ? json_encode($data['mandatory_event_types']) : null,
            'prohibited_channels'     => isset($data['prohibited_channels']) ? json_encode($data['prohibited_channels']) : null,
            'retention_override_days' => isset($data['retention_override_days']) ? (int)$data['retention_override_days'] : null,
            'max_escalation_attempts' => isset($data['max_escalation_attempts']) ? (int)$data['max_escalation_attempts'] : 3,
            'escalation_fallback_chain' => isset($data['escalation_fallback_chain']) ? json_encode($data['escalation_fallback_chain']) : null,
        ];

        $existing = $wpdb->get_var($wpdb->prepare("SELECT org_id FROM {$table} WHERE org_id = %d", $org_id));
        if ($existing) {
            $wpdb->update($table, $fields, ['org_id' => $org_id]);
        } else {
            $fields['org_id'] = $org_id;
            $wpdb->insert($table, $fields);
        }

        orabooks_log_event('org_notification_policy_updated', "Notification policy updated for org {$org_id}", 'info', [
            'fields' => $fields,
        ], null, $org_id);

        return true;
    }

    // ================================================================
    // PROVIDER HEALTH SCORING
    // ================================================================

    /**
     * Cron: Update provider health scores (every 5 minutes).
     */
    public function cron_update_provider_health() {
        global $wpdb;

        $table_notifications = OraBooks_Database::table('notifications');
        $table_health = OraBooks_Database::table('delivery_provider_health');

        $providers = $wpdb->get_results("SELECT DISTINCT delivery_channel AS channel, delivery_proof FROM {$table_notifications} WHERE delivery_channel IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");

        $aggregated = [];
        foreach ($providers as $p) {
            $proof = json_decode($p->delivery_proof, true);
            $provider_name = $proof['provider'] ?? 'unknown';
            $key = "{$p->channel}:{$provider_name}";
            if (!isset($aggregated[$key])) {
                $aggregated[$key] = ['channel' => $p->channel, 'provider' => $provider_name, 'total' => 0, 'success' => 0];
            }
        }

        if (empty($aggregated)) {
            return;
        }

        $one_hour_ago = date('Y-m-d H:i:s', time() - 3600);

        foreach ($aggregated as $key => &$agg) {
            $stats = $wpdb->get_row($wpdb->prepare(
                "SELECT COUNT(*) as total, SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as success 
                 FROM {$table_notifications} 
                 WHERE delivery_channel = %s AND created_at >= %s",
                $agg['channel'], $one_hour_ago
            ));

            $total = (int)($stats->total ?? 0);
            $success = (int)($stats->success ?? 0);
            $success_rate = $total > 0 ? ($success / $total) * 100 : 100;
            $avg_latency = self::get_avg_latency($agg['channel'], $agg['provider']);

            // Health score: 60% success rate, 40% latency (normalized)
            $latency_score = max(0, 100 - ($avg_latency / 10));
            $health_score = ($success_rate * 0.6) + ($latency_score * 0.4);

            $region = 'us-east';

            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$table_health} (channel, provider_name, region, success_rate, avg_latency_ms, health_score, updated_at)
                 VALUES (%s, %s, %s, %f, %d, %f, %s)
                 ON DUPLICATE KEY UPDATE
                     success_rate = VALUES(success_rate),
                     avg_latency_ms = VALUES(avg_latency_ms),
                     health_score = VALUES(health_score),
                     updated_at = VALUES(updated_at)",
                $agg['channel'], $agg['provider'], $region, $success_rate, $avg_latency, $health_score, current_time('mysql', true)
            ));
        }
    }

    private static function get_avg_latency($channel, $provider_name) {
        global $wpdb;
        $table = OraBooks_Database::table('notifications');
        $one_hour_ago = date('Y-m-d H:i:s', time() - 3600);
        $avg = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, delivered_at) * 1000)
             FROM {$table}
             WHERE delivery_channel = %s AND delivered_at IS NOT NULL AND created_at >= %s",
            $channel, $one_hour_ago
        ));
        return (int)($avg ?? 100);
    }

    // ================================================================
    // SLA COMPLIANCE CHECK
    // ================================================================

    /**
     * Cron: Check SLA compliance for critical notifications.
     */
    public function cron_sla_compliance_check() {
        global $wpdb;

        $table = OraBooks_Database::table('notifications');
        $one_day_ago = date('Y-m-d H:i:s', time() - 86400);

        $critical = $wpdb->get_results($wpdb->prepare(
            "SELECT id, org_id, delivered_at, created_at 
             FROM {$table} 
             WHERE priority = 'critical' AND status = 'delivered' AND created_at >= %s",
            $one_day_ago
        ));

        foreach ($critical as $n) {
            $created = strtotime($n->created_at);
            $delivered = strtotime($n->delivered_at);
            $latency_ms = ($delivered - $created) * 1000;

            if ($latency_ms > 5000) { // 5 second SLA for critical
                $org_id = (int)$n->org_id;
                $org_table = OraBooks_Database::table('organizations');
                $admin_email = $wpdb->get_var($wpdb->prepare(
                    "SELECT owner_id FROM {$org_table} WHERE id = %d", $org_id
                ));

                orabooks_log_event('notification_sla_breach', "Critical notification SLA breach: {$latency_ms}ms for notification {$n->id}", 'critical', [
                    'notification_id' => $n->id,
                    'latency_ms'      => $latency_ms,
                    'org_id'          => $org_id,
                ], null, $org_id);
            }
        }
    }

    // ================================================================
    // MOBILE DEVICE LIFECYCLE
    // ================================================================

    public static function register_device($user_id, $push_token, $device_type) {
        global $wpdb;
        $table = OraBooks_Database::table('mobile_devices');
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (user_id, push_token, device_type, last_seen, is_active) 
             VALUES (%d, %s, %s, %s, 1)
             ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen), is_active = 1",
            $user_id, $push_token, $device_type, current_time('mysql', true)
        ));
    }

    /**
     * Cron: Deactivate stale devices (30 days no activity).
     */
    public function cron_deactivate_stale_devices() {
        global $wpdb;
        $table = OraBooks_Database::table('mobile_devices');
        $cutoff = date('Y-m-d H:i:s', time() - (30 * 86400));
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET is_active = 0 WHERE last_seen < %s",
            $cutoff
        ));
        orabooks_log_event('device_cleanup', "Stale mobile devices deactivated", 'info');
    }

    /**
     * Cron: Retry pending/dead deliveries
     */
    public function cron_retry_deliveries() {
        global $wpdb;
        $table = OraBooks_Database::table('notifications');

        // Retry pending notifications older than 5 minutes
        $cutoff = date('Y-m-d H:i:s', time() - 300);
        $pending = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'pending' AND created_at < %s LIMIT 50",
            $cutoff
        ));

        foreach ($pending as $notif) {
            // Re-attempt delivery on the existing notification (don't create duplicates)
            // For MVP, requeue by setting status back to 'pending' with a delivery note
            $payload = json_decode($notif->payload, true) ?: [];
            
            $channels = self::resolve_channels(
                $notif->event_type,
                self::get_user_preferences($notif->user_id),
                self::get_org_policy($notif->org_id)
            );
            
            $provider = self::select_provider($notif->delivery_channel ?? 'email', $notif->org_id);
            $result = self::attempt_delivery($notif->id, $channels[0] ?? 'inapp', $provider, $notif->user_id, $notif->title, $notif->message, $payload);
            
            if ($result['success']) {
                $wpdb->update(
                    $table,
                    [
                        'status'           => 'delivered',
                        'delivered_at'     => current_time('mysql', true),
                        'delivery_channel' => $channels[0] ?? 'inapp',
                        'delivery_proof'   => json_encode($result['proof']),
                    ],
                    ['id' => $notif->id],
                    ['%s', '%s', '%s', '%s'],
                    ['%d']
                );
            }
        }
    }

    // ================================================================
    // SIGNED AUDIT EXPORT BUNDLE
    // ================================================================

    /**
     * Export signed audit bundle for compliance.
     * Permission: Owner only.
     */
    public static function export_audit_bundle($org_id, $start_date, $end_date, $user_id) {
        global $wpdb;

        $table = OraBooks_Database::table('notifications');
        $table_export_log = OraBooks_Database::table('audit_export_log');

        $notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT id, payload, delivered_at, delivery_proof, correlation_id 
             FROM {$table} 
             WHERE org_id = %d AND created_at BETWEEN %s AND %s
             ORDER BY created_at ASC",
            $org_id, $start_date, $end_date
        ));

        $bundle = [
            'bundle_id'     => orabooks_uuid(),
            'exported_at'   => current_time('mysql', true),
            'org_id'        => $org_id,
            'notifications' => [],
            'signatures'    => [],
        ];

        foreach ($notifications as $n) {
            $item = [
                'id'             => (int)$n->id,
                'payload'        => json_decode($n->payload, true),
                'delivered_at'   => $n->delivered_at,
                'delivery_proof' => json_decode($n->delivery_proof, true),
                'correlation_id' => $n->correlation_id,
            ];
            $bundle['notifications'][] = $item;
            $bundle['signatures'][] = [
                'notification_id' => (int)$n->id,
                'hmac'            => hash_hmac('sha256', json_encode($item), defined('ORABOOKS_JWT_SECRET') ? ORABOOKS_JWT_SECRET : 'orabooks-default'),
            ];
        }

        $bundle['bundle_hash'] = hash('sha256', json_encode($bundle));

        // Log export
        $wpdb->insert($table_export_log, [
            'org_id'       => $org_id,
            'exported_by'  => $user_id,
            'exported_at'  => current_time('mysql', true),
            'bundle_hash'  => $bundle['bundle_hash'],
            'record_count' => count($notifications),
        ], ['%d', '%d', '%s', '%s', '%d']);

        orabooks_log_event('audit_bundle_exported', "Signed audit bundle exported for org {$org_id}", 'info', [
            'bundle_id'    => $bundle['bundle_id'],
            'record_count' => count($notifications),
            'date_range'   => "{$start_date} to {$end_date}",
        ], $user_id, $org_id);

        return $bundle;
    }

    // ================================================================
    // HELPER: Get OraBooks user
    // ================================================================

    private static function get_orabooks_user($user_id) {
        global $wpdb;
        $table = OraBooks_Database::table('users');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $user_id));
    }

    // ================================================================
    // GET CREATE TABLE SQL (for database installer)
    // ================================================================

    public static function get_create_table_sql() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $tables = [];
        $table_orgs = OraBooks_Database::table('organizations');

        // 1. Notifications
        $table_notifications = OraBooks_Database::table('notifications');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_notifications} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(64) NOT NULL,
            priority ENUM('critical','high','normal','low') DEFAULT 'normal',
            title VARCHAR(255) DEFAULT '',
            message TEXT,
            payload JSON DEFAULT NULL,
            correlation_id VARCHAR(64) NOT NULL,
            status ENUM('pending','queued','delivering','delivered','failed','dead_letter') DEFAULT 'pending',
            delivered_at TIMESTAMP NULL,
            delivery_channel VARCHAR(20) DEFAULT NULL,
            delivery_proof JSON DEFAULT NULL,
            is_read TINYINT(1) DEFAULT 0,
            read_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
            INDEX idx_user_status (user_id, status),
            INDEX idx_correlation (correlation_id),
            INDEX idx_org_created (org_id, created_at)
        ) {$charset_collate};";

        // 2. Delivery Provider Health
        $table = OraBooks_Database::table('delivery_provider_health');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table} (
            channel VARCHAR(20) NOT NULL,
            provider_name VARCHAR(50) NOT NULL,
            region VARCHAR(20) NOT NULL,
            success_rate DECIMAL(5,2) DEFAULT 100.00,
            avg_latency_ms INT DEFAULT 0,
            last_outage_at TIMESTAMP NULL,
            health_score INT DEFAULT 100,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (channel, provider_name, region)
        ) {$charset_collate};";

        // 3. Org Notification Policies
        $table = OraBooks_Database::table('org_notification_policies');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table} (
            org_id BIGINT UNSIGNED PRIMARY KEY,
            monthly_budget DECIMAL(10,2) DEFAULT NULL,
            mandatory_event_types JSON DEFAULT NULL,
            prohibited_channels JSON DEFAULT NULL,
            retention_override_days INT DEFAULT NULL,
            max_escalation_attempts INT DEFAULT 3,
            escalation_fallback_chain JSON DEFAULT NULL,
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE
        ) {$charset_collate};";

        // 4. Dedup Log
        $table = OraBooks_Database::table('notification_dedup_log');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table} (
            dedup_key VARCHAR(128) PRIMARY KEY,
            first_occurrence TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_occurrence TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            suppressed_count INT DEFAULT 0
        ) {$charset_collate};";

        // 5. Notification Dependencies
        $table = OraBooks_Database::table('notification_dependencies');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table} (
            event_type VARCHAR(64) NOT NULL,
            depends_on VARCHAR(64) NOT NULL,
            PRIMARY KEY (event_type, depends_on)
        ) {$charset_collate};";

        // 6. Mobile Devices
        $table = OraBooks_Database::table('mobile_devices');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            push_token VARCHAR(255) DEFAULT NULL,
            device_type VARCHAR(20) DEFAULT NULL,
            last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active TINYINT(1) DEFAULT 1,
            UNIQUE KEY uk_user_token (user_id, push_token(100))
        ) {$charset_collate};";

        // 7. Notification Cost
        $table = OraBooks_Database::table('notification_cost');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            notification_id BIGINT UNSIGNED NOT NULL,
            channel VARCHAR(20) DEFAULT NULL,
            provider VARCHAR(50) DEFAULT NULL,
            cost DECIMAL(10,6) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
            FOREIGN KEY (notification_id) REFERENCES {$table_notifications}(id) ON DELETE CASCADE,
            INDEX idx_org_month (org_id, created_at)
        ) {$charset_collate};";

        // 8. Audit Export Log
        $table = OraBooks_Database::table('audit_export_log');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            exported_by INT NOT NULL,
            exported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            bundle_hash VARCHAR(64) DEFAULT NULL,
            record_count INT DEFAULT 0,
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
            INDEX idx_org (org_id)
        ) {$charset_collate};";

        return $tables;
    }

    // ================================================================
    // SEED DEFAULT DEPENDENCIES
    // ================================================================

    public static function seed_dependencies() {
        global $wpdb;
        $table = OraBooks_Database::table('notification_dependencies');

        $deps = [
            ['event_type' => 'partner_inactivity_reminder_sent', 'depends_on' => 'partner_low_activity_reminder_sent'],
            ['event_type' => 'partner_code_inactivated', 'depends_on' => 'partner_inactivity_reminder_sent'],
            ['event_type' => 'partner_reactivation_approved', 'depends_on' => 'partner_reactivation_requested'],
            ['event_type' => 'payout_settled', 'depends_on' => 'payout_batch_created'],
        ];

        foreach ($deps as $dep) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$table} (event_type, depends_on) VALUES (%s, %s)",
                $dep['event_type'], $dep['depends_on']
            ));
        }
    }

    // ================================================================
    // NOTIFICATION RETRIEVAL
    // ================================================================

    public static function get_notifications($user_id, $args = []) {
        global $wpdb;
        $table = OraBooks_Database::table('notifications');

        $where = 'user_id = %d';
        $params = [$user_id];

        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $params[] = $args['status'];
        }

        if (!empty($args['priority'])) {
            $where .= ' AND priority = %s';
            $params[] = $args['priority'];
        }

        if (!empty($args['event_type'])) {
            $where .= ' AND event_type = %s';
            $params[] = $args['event_type'];
        }

        if (!empty($args['correlation_id'])) {
            $where .= ' AND correlation_id = %s';
            $params[] = $args['correlation_id'];
        }

        if (!empty($args['from_date'])) {
            $where .= ' AND created_at >= %s';
            $params[] = $args['from_date'];
        }

        if (!empty($args['to_date'])) {
            $where .= ' AND created_at <= %s';
            $params[] = $args['to_date'];
        }

        if (!empty($args['unread_only'])) {
            $where .= " AND is_read = 0 AND status = 'delivered'";
        }

        $limit = min((int)($args['limit'] ?? 50), 200);
        $offset = (int)($args['offset'] ?? 0);

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    public static function get_unread_count($user_id) {
        global $wpdb;
        $table = OraBooks_Database::table('notifications');
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND status = 'delivered' AND is_read = 0",
            $user_id
        ));
    }

    public static function mark_read($notification_id, $user_id) {
        global $wpdb;
        $table = OraBooks_Database::table('notifications');
        return $wpdb->update(
            $table,
            ['is_read' => 1, 'read_at' => current_time('mysql', true)],
            ['id' => $notification_id, 'user_id' => $user_id],
            ['%d', '%s'],
            ['%d', '%d']
        );
    }

    public static function mark_all_read($user_id) {
        global $wpdb;
        $table = OraBooks_Database::table('notifications');
        return $wpdb->update(
            $table,
            ['is_read' => 1, 'read_at' => current_time('mysql', true)],
            ['user_id' => $user_id, 'status' => 'delivered', 'is_read' => 0],
            ['%d', '%s'],
            ['%d', '%d', '%d']
        );
    }

    // ============================================================
    // AJAX HANDLERS
    // ============================================================

    private function require_notification_user() {
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }
        return $user_id;
    }

    private function require_org_notification_admin($org_id) {
        $user_id = $this->require_notification_user();
        $org_id = intval($org_id);
        if (!$org_id) {
            orabooks_json_error('Organization required', 400);
        }
        if (!current_user_can('manage_options') && !OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_org_settings')) {
            orabooks_json_error('Permission denied', 403);
        }
        return $user_id;
    }

    public static function format_notification_for_api($row) {
        if (!$row) {
            return null;
        }

        $payload = [];
        if (!empty($row->payload)) {
            $decoded = json_decode($row->payload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $delivery_proof = null;
        if (!empty($row->delivery_proof)) {
            $delivery_proof = json_decode($row->delivery_proof, true);
        }

        $sla_breached = false;
        if (($row->priority ?? '') === 'critical' && !empty($row->delivered_at) && !empty($row->created_at)) {
            $latency_ms = (strtotime($row->delivered_at) - strtotime($row->created_at)) * 1000;
            $sla_breached = $latency_ms > 5000;
        }

        return [
            'id' => (int) $row->id,
            'event_type' => $row->event_type,
            'priority' => $row->priority,
            'title' => $row->title ?: ($payload['title'] ?? $row->event_type),
            'message' => $row->message ?: ($payload['message'] ?? ''),
            'status' => $row->status,
            'delivery_channel' => $row->delivery_channel,
            'correlation_id' => $row->correlation_id,
            'created_at' => $row->created_at,
            'delivered_at' => $row->delivered_at,
            'is_read' => !empty($row->is_read),
            'read_at' => $row->read_at,
            'sla_breached' => $sla_breached,
            'has_delivery_proof' => !empty($delivery_proof),
            'delivery_proof' => $delivery_proof,
            'payload' => $payload,
        ];
    }

    /**
     * AJAX: List notifications for current user.
     */
    public function ajax_list_notifications() {
        $user_id = $this->require_notification_user();

        $args = [
            'status'         => sanitize_text_field($_GET['status'] ?? ''),
            'priority'       => sanitize_text_field($_GET['priority'] ?? ''),
            'event_type'     => sanitize_text_field($_GET['event_type'] ?? ''),
            'correlation_id' => sanitize_text_field($_GET['correlation_id'] ?? ''),
            'from_date'      => sanitize_text_field($_GET['from_date'] ?? ''),
            'to_date'        => sanitize_text_field($_GET['to_date'] ?? ''),
            'unread_only'    => !empty($_GET['unread_only']),
            'limit'          => intval($_GET['limit'] ?? 50),
            'offset'         => intval($_GET['offset'] ?? 0),
        ];

        $rows = self::get_notifications($user_id, $args);
        $notifications = array_values(array_filter(array_map(
            [self::class, 'format_notification_for_api'],
            $rows ?: []
        )));
        $unread_count = self::get_unread_count($user_id);

        orabooks_json_success([
            'notifications' => $notifications,
            'unread_count'  => $unread_count,
            'total'         => count($notifications),
        ]);
    }

    /**
     * AJAX: Mark notification as read.
     */
    public function ajax_mark_read() {
        $user_id = $this->require_notification_user();
        $notification_id = intval($_POST['notification_id'] ?? 0);

        self::mark_read($notification_id, $user_id);
        orabooks_json_success([], 'Marked as read');
    }

    /**
     * AJAX: Mark all notifications as read.
     */
    public function ajax_mark_all_read() {
        $user_id = $this->require_notification_user();

        self::mark_all_read($user_id);
        orabooks_json_success([], 'All notifications marked as read');
    }

    /**
     * AJAX: Get unread count.
     */
    public function ajax_unread_count() {
        $user_id = $this->require_notification_user();

        $count = self::get_unread_count($user_id);
        orabooks_json_success(['count' => $count, 'unread_count' => $count]);
    }

    /**
     * AJAX: Get user notification preferences.
     */
    public function ajax_get_preferences() {
        $user_id = $this->require_notification_user();

        $prefs = self::get_user_preferences($user_id);
        orabooks_json_success($prefs);
    }

    /**
     * AJAX: Save user notification preferences.
     */
    public function ajax_save_preferences() {
        $user_id = $this->require_notification_user();

        $data = [
            'channels'          => isset($_POST['channels']) ? (array)$_POST['channels'] : [],
            'quiet_hours_start' => sanitize_text_field($_POST['quiet_hours_start'] ?? ''),
            'quiet_hours_end'   => sanitize_text_field($_POST['quiet_hours_end'] ?? ''),
            'digest'            => sanitize_text_field($_POST['digest'] ?? 'none'),
            'language'          => sanitize_text_field($_POST['language'] ?? 'en'),
            'escalation_enabled' => !empty($_POST['escalation_enabled']),
        ];

        $prefs = self::save_user_preferences($user_id, $data);

        orabooks_log_event('notification_preferences_updated', "Notification preferences updated for user {$user_id}", 'info', [
            'digest'   => $data['digest'],
            'language' => $data['language'],
        ], $user_id);

        orabooks_json_success($prefs, 'Preferences saved');
    }

    /**
     * AJAX: Get org notification policy (Owner only).
     */
    public function ajax_get_org_policy() {
        $org_id = intval($_GET['org_id'] ?? 0);
        $this->require_org_notification_admin($org_id);

        $policy = self::get_org_policy($org_id);
        orabooks_json_success($policy ?: []);
    }

    /**
     * AJAX: Save org notification policy (Owner only).
     */
    public function ajax_save_org_policy() {
        $org_id = intval($_POST['org_id'] ?? 0);
        $user_id = $this->require_org_notification_admin($org_id);

        $data = [
            'monthly_budget'          => floatval($_POST['monthly_budget'] ?? 0),
            'mandatory_event_types'   => isset($_POST['mandatory_event_types']) ? (array)$_POST['mandatory_event_types'] : [],
            'prohibited_channels'     => isset($_POST['prohibited_channels']) ? (array)$_POST['prohibited_channels'] : [],
            'retention_override_days' => intval($_POST['retention_override_days'] ?? 0),
            'max_escalation_attempts' => intval($_POST['max_escalation_attempts'] ?? 3),
            'escalation_fallback_chain' => isset($_POST['escalation_fallback_chain']) ? (array)$_POST['escalation_fallback_chain'] : [],
        ];

        self::save_org_policy($org_id, $data);
        orabooks_json_success([], 'Policy saved');
    }

    /**
     * AJAX: Get provider health scores (Owner only).
     */
    public function ajax_provider_health() {
        $org_id = intval($_GET['org_id'] ?? $_POST['org_id'] ?? 0);
        if ($org_id) {
            $this->require_org_notification_admin($org_id);
        } elseif (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }

        global $wpdb;
        $table = OraBooks_Database::table('delivery_provider_health');
        $health = $wpdb->get_results("SELECT * FROM {$table} ORDER BY health_score ASC");
        orabooks_json_success($health);
    }

    /**
     * AJAX: Export signed audit bundle (Owner only).
     */
    public function ajax_audit_export() {
        $org_id = intval($_GET['org_id'] ?? 0);
        $user_id = $this->require_org_notification_admin($org_id);
        $start_date = sanitize_text_field($_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
        $end_date = sanitize_text_field($_GET['end_date'] ?? date('Y-m-d'));

        $bundle = self::export_audit_bundle($org_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59', $user_id);

        wp_send_json($bundle, 200);
    }

    /**
     * AJAX: Register mobile device token.
     */
    public function ajax_register_device() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $push_token = sanitize_text_field($_POST['push_token'] ?? '');
        $device_type = sanitize_text_field($_POST['device_type'] ?? '');

        if (empty($push_token)) {
            orabooks_json_error('Push token required', 400);
        }

        self::register_device($user_id, $push_token, $device_type);
        orabooks_json_success([], 'Device registered');
    }

    // ================================================================
    // PARTNER COMMISSION EVENT HANDLERS
    // ================================================================

    /**
     * Handle payout_batch_created event from SL-068.
     * Sends notification to the partner.
     */
    public function on_payout_batch_created($payout_id, $data) {
        $user_id = !empty($data['partner_user_id']) ? (int)$data['partner_user_id'] : 0;
        $org_id = !empty($data['org_id']) ? (int)$data['org_id'] : 0;
        $gross = !empty($data['gross_amount']) ? $data['gross_amount'] : 0;
        $fee = !empty($data['fee_amount']) ? $data['fee_amount'] : 0;
        $net = !empty($data['net_amount']) ? $data['net_amount'] : $gross - $fee;

        if (!$user_id) {
            return;
        }

        self::send_notification($user_id, 'payout_batch_created', [
            'title'          => sprintf(__('Payout Created: $%s', 'orabooks'), number_format($net, 2)),
            'message'        => sprintf(
                __('Your monthly payout of $%s (net) has been initiated. Gross: $%s, Fee: $%s.', 'orabooks'),
                number_format($net, 2),
                number_format($gross, 2),
                number_format($fee, 2)
            ),
            'priority'       => $data['priority'] ?? 'high',
            'correlation_id' => 'payout_' . $payout_id,
            'payout_id'      => $payout_id,
            'gross_amount'   => $gross,
            'fee_amount'     => $fee,
            'net_amount'     => $net,
        ], $org_id);
    }

    /**
     * Handle payout_settled event from SL-068.
     */
    public function on_payout_settled($payout_id, $data) {
        $user_id = !empty($data['partner_user_id']) ? (int)$data['partner_user_id'] : 0;
        $org_id = !empty($data['org_id']) ? (int)$data['org_id'] : 0;
        $net = !empty($data['net_amount']) ? $data['net_amount'] : 0;

        if (!$user_id) {
            return;
        }

        self::send_notification($user_id, 'payout_settled', [
            'title'          => sprintf(__('Payout Settled: $%s', 'orabooks'), number_format($net, 2)),
            'message'        => sprintf(
                __('Your payout of $%s has been settled and transferred to your bank account.', 'orabooks'),
                number_format($net, 2)
            ),
            'priority'       => 'high',
            'correlation_id' => 'payout_' . $payout_id,
            'payout_id'      => $payout_id,
            'net_amount'     => $net,
        ], $org_id);
    }

    /**
     * Handle partner_reactivation_requested event.
     * Notifies admin/compliance team for review.
     */
    public function on_partner_reactivation_requested($org_id, $data) {
        $user_id = !empty($data['user_id']) ? (int)$data['user_id'] : 0;
        $reason = !empty($data['reason']) ? $data['reason'] : __('No reason provided', 'orabooks');

        if (!$user_id) {
            return;
        }

        // Notify the requesting partner
        self::send_notification($user_id, 'partner_reactivation_requested', [
            'title'          => __('Reactivation Requested', 'orabooks'),
            'message'        => __('Your reactivation request has been received and is pending review.', 'orabooks'),
            'priority'       => 'normal',
            'correlation_id' => 'reactivation_' . $org_id . '_' . $user_id,
            'reason'         => $reason,
        ], $org_id);

        // Notify org admins/owners
        self::notify_org_admins($org_id, 'partner_reactivation_requested', [
            'title'          => __('Partner Reactivation Request', 'orabooks'),
            'message'        => sprintf(__('Partner (User #%d) has requested reactivation. Reason: %s', 'orabooks'), $user_id, $reason),
            'priority'       => 'high',
            'correlation_id' => 'reactivation_' . $org_id . '_' . $user_id,
        ]);
    }

    /**
     * Handle partner_reactivation_approved event.
     */
    public function on_partner_reactivation_approved($org_id, $data) {
        $user_id = !empty($data['user_id']) ? (int)$data['user_id'] : 0;
        $approved_by = !empty($data['approved_by']) ? $data['approved_by'] : 0;

        if (!$user_id) {
            return;
        }

        self::send_notification($user_id, 'partner_reactivation_approved', [
            'title'          => __('Reactivation Approved', 'orabooks'),
            'message'        => __('Your partner program has been reactivated. You can now earn commissions again.', 'orabooks'),
            'priority'       => 'high',
            'correlation_id' => 'reactivation_' . $org_id . '_' . $user_id,
        ], $org_id);
    }

    public function on_partner_inactivity_reminder_sent($user_id, $data) {
        self::send_notification((int) $user_id, 'partner_inactivity_reminder_sent', [
            'title'    => __('Partner Inactivity Warning', 'orabooks'),
            'message'  => __('Your partner account may be deactivated soon due to inactivity.', 'orabooks'),
            'priority' => 'high',
        ] + (array) $data, null);
    }

    public function on_partner_code_inactivated($user_id, $data) {
        self::send_notification((int) $user_id, 'partner_code_inactivated', [
            'title'    => __('Partner Code Deactivated', 'orabooks'),
            'message'  => __('Your partner code has been deactivated due to inactivity.', 'orabooks'),
            'priority' => 'high',
        ] + (array) $data, null);
    }

    public function on_partner_low_activity_reminder_sent($user_id, $data) {
        self::send_notification((int) $user_id, 'partner_low_activity_reminder_sent', [
            'title'    => __('Partner Activity Reminder', 'orabooks'),
            'message'  => __('We have not seen new customer referrals recently. Keep sharing your partner code.', 'orabooks'),
            'priority' => 'normal',
        ] + (array) $data, null);
    }

    /**
     * Get the customer-facing workspace URL for notification view links.
     * Invoice notifications deep-link to the invoices page when an ID is present.
     */
    private static function get_customer_dashboard_url(int $invoice_id = 0, int $org_id = 0): string
    {
        $query = $invoice_id > 0 ? ['invoice_id' => $invoice_id] : [];
        $path = $invoice_id > 0 ? '/invoices/' : '/dashboard/';

        return orabooks_get_org_workspace_url($org_id, $path, $query);
    }

    /**
     * Get the org admin invoices page URL for view links in notifications.
     */
    private static function get_admin_invoices_url(int $invoice_id = 0, int $org_id = 0): string
    {
        $query = $invoice_id > 0 ? ['invoice_id' => $invoice_id] : [];

        return orabooks_get_org_workspace_url($org_id, '/invoices/', $query);
    }

    /**
     * Notify all org admins/owners about an event.
     */
    private static function notify_org_admins($org_id, $event_type, $payload) {
        global $wpdb;

        $table_user_org = OraBooks_Database::table('user_org');
        $admins = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id FROM {$table_user_org} WHERE org_id = %d AND role IN ('owner', 'admin')",
            $org_id
        ));

        foreach ($admins as $admin) {
            self::send_notification($admin->user_id, $event_type, $payload, $org_id);
        }
    }

    // ================================================================
    // STATIC HELPER: send_notification (for external use)
    // ================================================================

    /**
     * Cron: Send daily overdue invoice digest to org admins.
     *
     * Queries ALL currently-overdue invoices (regardless of when they were
     * first marked overdue) and sends a consolidated digest per org
     * via notify_org_admins().
     */
    public function cron_send_overdue_digest() {
        global $wpdb;

        $table_invoices = OraBooks_Database::table('invoices');
        $table_customers = OraBooks_Database::table('customers');

        // Fetch all currently overdue invoices with org context
        $overdue_invoices = $wpdb->get_results(
            "SELECT i.id, i.invoice_number, i.total_amount, i.due_date, i.org_id,
                    i.overdue_notified_at, c.user_id as customer_user_id
             FROM {$table_invoices} i
             JOIN {$table_customers} c ON i.customer_id = c.id
             WHERE i.payment_status = 'overdue'
               AND i.workflow_status IN ('sent', 'posted')
               AND i.due_date < CURDATE()
             ORDER BY i.org_id ASC, i.due_date ASC"
        );

        if (empty($overdue_invoices)) {
            return;
        }

        // Group by org
        $orgs = [];
        foreach ($overdue_invoices as $inv) {
            $org_id = (int)$inv->org_id;
            if (!isset($orgs[$org_id])) {
                $orgs[$org_id] = [
                    'count'          => 0,
                    'total'          => 0,
                    'oldest'         => $inv->due_date,
                    'newest'         => $inv->due_date,
                    'first_invoice_id' => (int)$inv->id,
                    'invoice_ids'    => [],
                ];
            }
            $orgs[$org_id]['count']++;
            $orgs[$org_id]['total'] += (float)$inv->total_amount;
            $orgs[$org_id]['invoice_ids'][] = (int)$inv->id;
            if ($inv->due_date < $orgs[$org_id]['oldest']) {
                $orgs[$org_id]['oldest'] = $inv->due_date;
            }
            if ($inv->due_date > $orgs[$org_id]['newest']) {
                $orgs[$org_id]['newest'] = $inv->due_date;
            }
        }

        // Send digest per org
        foreach ($orgs as $org_id => $agg) {
            $date_range = $agg['oldest'] === $agg['newest']
                ? $agg['oldest']
                : sprintf(__('%s to %s', 'orabooks'), $agg['oldest'], $agg['newest']);

            self::notify_org_admins($org_id, 'overdue_digest', [
                'title'          => sprintf(
                    __('Overdue Invoice Digest: %d invoice(s)', 'orabooks'),
                    $agg['count']
                ),
                'message'        => sprintf(
                    __('Daily digest — %d overdue invoice(s) totaling $%s. Due date range: %s.', 'orabooks'),
                    $agg['count'],
                    number_format($agg['total'], 2),
                    $date_range
                ),
                'priority'       => 'high',
                'correlation_id' => 'overdue_digest_' . $org_id . '_' . current_time('Ymd'),
                'overdue_count'  => $agg['count'],
                'total_amount'   => $agg['total'],
                'date_range'     => $date_range,
                'view_url'       => self::get_admin_invoices_url($agg['first_invoice_id'], $org_id),
                'invoice_ids'    => $agg['invoice_ids'],
            ]);
        }

        orabooks_log_event('overdue_digest_sent', sprintf(
            'Daily overdue digest sent to %d org(s)', count($orgs)
        ), 'info', [
            'org_count' => count($orgs),
            'total_invoices' => count($overdue_invoices),
        ]);
    }

    // ================================================================
    // INVOICE EVENT HANDLERS (SL-021 Integration)
    // ================================================================

    /**
     * Handle invoice_created event from SL-021.
     * Notify the customer that a new invoice has been issued.
     */
    public function on_invoice_created($invoice_id, $data) {
        $customer_id = !empty($data['customer_id']) ? (int)$data['customer_id'] : 0;
        $org_id = !empty($data['org_id']) ? (int)$data['org_id'] : 0;
        $invoice_number = !empty($data['invoice_number']) ? $data['invoice_number'] : '';
        $total_amount = !empty($data['total_amount']) ? $data['total_amount'] : 0;
        $due_date = !empty($data['due_date']) ? $data['due_date'] : '';

        if (!$customer_id || !$org_id) {
            return;
        }

        // Resolve customer's user_id from the customers table
        $customer = OraBooks_Customers::get_by_id($customer_id);
        if (!$customer) {
            return;
        }

        self::send_notification($customer->user_id, 'invoice_created', [
            'title'          => sprintf(__('New Invoice: %s', 'orabooks'), $invoice_number),
            'message'        => sprintf(
                __('A new invoice %s for $%s has been issued. Due date: %s.', 'orabooks'),
                $invoice_number,
                number_format((float)$total_amount, 2),
                $due_date ?: __('Not set', 'orabooks')
            ),
            'priority'       => 'normal',
            'correlation_id' => 'invoice_' . $invoice_id,
            'invoice_id'     => $invoice_id,
            'invoice_number' => $invoice_number,
            'total_amount'   => $total_amount,
            'due_date'       => $due_date,
            'view_url'       => self::get_customer_dashboard_url($invoice_id, $org_id),
        ], $org_id);

        // Notify org admins/owners about the new invoice
        self::notify_org_admins($org_id, 'invoice_created', [
            'title'          => sprintf(__('New Invoice Created: %s', 'orabooks'), $invoice_number),
            'message'        => sprintf(
                __('Invoice %s for $%s has been issued to customer #%d. Due date: %s.', 'orabooks'),
                $invoice_number,
                number_format((float)$total_amount, 2),
                $customer_id,
                $due_date ?: __('Not set', 'orabooks')
            ),
            'priority'       => 'high',
            'correlation_id' => 'invoice_' . $invoice_id,
            'invoice_id'     => $invoice_id,
            'invoice_number' => $invoice_number,
            'total_amount'   => $total_amount,
            'customer_id'    => $customer_id,
            'due_date'       => $due_date,
            'view_url'       => self::get_admin_invoices_url($invoice_id, $org_id),
        ]);
    }

    /**
     * Handle payment_recorded event from SL-021.
     * Notify the customer that a payment has been applied.
     */
    public function on_payment_recorded($payment_id, $data) {
        $customer_user_id = !empty($data['customer_user_id']) ? (int)$data['customer_user_id'] : 0;
        $org_id = !empty($data['org_id']) ? (int)$data['org_id'] : 0;
        $invoice_id = !empty($data['invoice_id']) ? (int)$data['invoice_id'] : 0;
        $invoice_number = !empty($data['invoice_number']) ? $data['invoice_number'] : '';
        $amount = !empty($data['amount']) ? $data['amount'] : 0;
        $new_status = !empty($data['new_status']) ? $data['new_status'] : '';

        if (!$customer_user_id || !$org_id) {
            return;
        }

        $status_label = [
            'paid'    => __('paid in full', 'orabooks'),
            'partial' => __('partially paid', 'orabooks'),
        ];

        self::send_notification($customer_user_id, 'payment_recorded', [
            'title'          => sprintf(__('Payment Received: $%s', 'orabooks'), number_format((float)$amount, 2)),
            'message'        => sprintf(
                __('A payment of $%s has been applied to invoice %s. The invoice is now %s.', 'orabooks'),
                number_format((float)$amount, 2),
                $invoice_number,
                $status_label[$new_status] ?? $new_status
            ),
            'priority'       => 'normal',
            'correlation_id' => 'payment_' . $payment_id,
            'payment_id'     => $payment_id,
            'invoice_number' => $invoice_number,
            'amount'         => $amount,
            'new_status'     => $new_status,
            'view_url'       => self::get_customer_dashboard_url($invoice_id, $org_id),
        ], $org_id);

        // Notify org admins/owners about the payment
        $status_desc = $status_label[$new_status] ?? $new_status;
        self::notify_org_admins($org_id, 'payment_recorded', [
            'title'          => sprintf(__('Payment Recorded: $%s', 'orabooks'), number_format((float)$amount, 2)),
            'message'        => sprintf(
                __('A payment of $%s has been recorded for invoice %s (status: %s) by customer #%d.', 'orabooks'),
                number_format((float)$amount, 2),
                $invoice_number,
                $status_desc,
                $customer_user_id
            ),
            'priority'       => 'high',
            'correlation_id' => 'payment_' . $payment_id,
            'payment_id'     => $payment_id,
            'invoice_number' => $invoice_number,
            'amount'         => $amount,
            'new_status'     => $new_status,
            'customer_id'    => $customer_user_id,
            'view_url'       => self::get_admin_invoices_url(),
        ]);
    }

    /**
     * Handle invoices_marked_overdue event from SL-021 daily cron.
     * For each overdue invoice, notify the customer.
     */
    public function on_invoices_marked_overdue($overdue_count, $data) {
        global $wpdb;

        if (!$overdue_count) {
            return;
        }

        // Fetch recently marked overdue invoices to notify each customer
        $table_invoices = OraBooks_Database::table('invoices');
        $table_customers = OraBooks_Database::table('customers');

        $overdue_invoices = $wpdb->get_results(
            "SELECT i.id, i.invoice_number, i.total_amount, i.due_date, i.org_id, i.overdue_notified_at, c.user_id as customer_user_id
             FROM {$table_invoices} i
             JOIN {$table_customers} c ON i.customer_id = c.id
             WHERE i.payment_status = 'overdue'
               AND i.workflow_status IN ('sent', 'posted')
               AND i.due_date < CURDATE()
               AND i.overdue_notified_at IS NULL
             ORDER BY i.due_date ASC
             LIMIT 50"
        );

        $notified_orgs = [];

        foreach ($overdue_invoices as $inv) {
            self::send_notification($inv->customer_user_id, 'invoice_overdue', [
                'title'          => sprintf(__('Invoice Overdue: %s', 'orabooks'), $inv->invoice_number),
                'message'        => sprintf(
                    __('Invoice %s for $%s was due on %s and is now overdue. Please arrange payment to avoid service interruption.', 'orabooks'),
                    $inv->invoice_number,
                    number_format((float)$inv->total_amount, 2),
                    $inv->due_date
                ),
                'priority'       => 'high',
                'correlation_id' => 'overdue_' . $inv->id,
                'invoice_id'     => (int)$inv->id,
                'invoice_number' => $inv->invoice_number,
                'total_amount'   => $inv->total_amount,
                'due_date'       => $inv->due_date,
                'view_url'       => self::get_customer_dashboard_url((int) $inv->id, (int) $inv->org_id),
            ], (int)$inv->org_id);

            // Track unique orgs for admin notification
            $org_id = (int)$inv->org_id;
            if (!isset($notified_orgs[$org_id])) {
                $notified_orgs[$org_id] = [
                    'count' => 0,
                    'total' => 0,
                ];
            }
            $notified_orgs[$org_id]['count']++;
            $notified_orgs[$org_id]['total'] += (float)$inv->total_amount;

            // Mark as notified so we never send a duplicate overdue reminder
            $wpdb->update(
                $table_invoices,
                ['overdue_notified_at' => current_time('mysql', true)],
                ['id' => (int)$inv->id],
                ['%s'],
                ['%d']
            );
        }

        // Notify org admins/owners about the batch of overdue invoices
        foreach ($notified_orgs as $org_id => $agg) {
            self::notify_org_admins($org_id, 'invoices_overdue', [
                'title'          => sprintf(__('%d Invoice(s) Overdue', 'orabooks'), $agg['count']),
                'message'        => sprintf(
                    __('%d invoice(s) totaling $%s are now overdue and require attention.', 'orabooks'),
                    $agg['count'],
                    number_format($agg['total'], 2)
                ),
                'priority'       => 'high',
                'correlation_id' => 'overdue_batch_' . $org_id . '_' . current_time('Ymd'),
                'overdue_count'  => $agg['count'],
                'total_amount'   => $agg['total'],
                'view_url'       => self::get_admin_invoices_url(),
            ]);
        }
    }

    /**
     * Handle inventory_low_stock_alert from SL-075.
     * Notifies org admins about low stock products.
     */
    public function on_inventory_low_stock_alert($product_id, $data) {
        $org_id = !empty($data['org_id']) ? (int) $data['org_id'] : 0;
        $sku = !empty($data['sku']) ? $data['sku'] : '';
        $product_name = !empty($data['product_name']) ? $data['product_name'] : $sku;

        if (!$org_id) {
            return;
        }

        self::notify_org_admins($org_id, 'inventory_low_stock_alert', [
            'title'          => sprintf(__('Low Stock: %s', 'orabooks'), $product_name),
            'message'        => sprintf(
                __('Product %s (%s) is below reorder level and requires restocking.', 'orabooks'),
                $product_name,
                $sku ?: ('#' . (int) $product_id)
            ),
            'priority'       => $data['priority'] ?? 'high',
            'correlation_id' => 'low_stock_' . $org_id . '_' . (int) $product_id,
            'product_id'     => (int) $product_id,
            'sku'            => $sku,
            'product_name'   => $product_name,
        ]);
    }

    /**
     * Handle projection_integrity_failed from SL-074.
     * Notifies org admins when ledger projections drift from source.
     */
    public function on_projection_integrity_failed($org_id, $data) {
        $org_id = (int) ($data['org_id'] ?? $org_id);
        $difference = !empty($data['difference']) ? (float) $data['difference'] : 0;

        if (!$org_id) {
            return;
        }

        self::notify_org_admins($org_id, 'projection_integrity_failed', [
            'title'          => __('Projection Integrity Alert', 'orabooks'),
            'message'        => sprintf(
                __('Financial projection integrity check failed with a difference of $%s. Review ledger projections.', 'orabooks'),
                number_format(abs($difference), 2)
            ),
            'priority'       => $data['priority'] ?? 'critical',
            'correlation_id' => 'projection_integrity_' . $org_id . '_' . current_time('Ymd'),
            'difference'     => $difference,
        ]);
    }

    // ================================================================
    // EXPORT EVENT HANDLERS (SL-114 Integration)
    // ================================================================

    /**
     * Handle export_ready event from SL-114.
     * Notify the requesting user that their export is ready for download.
     */
    public function on_export_ready($export_id, $data) {
        $user_id = !empty($data['user_id']) ? (int)$data['user_id'] : 0;
        $org_id = !empty($data['org_id']) ? (int)$data['org_id'] : 0;
        $export_type = !empty($data['export_type']) ? $data['export_type'] : 'report';
        $format = !empty($data['format']) ? strtoupper($data['format']) : 'CSV';
        $correlation_id = !empty($data['correlation_id']) ? $data['correlation_id'] : ('export_' . $export_id);

        if (!$user_id) {
            return;
        }

        self::send_notification($user_id, 'export_ready', [
            'title'          => sprintf(__('Export Ready: %s (%s)', 'orabooks'), strtoupper(str_replace('_', ' ', $export_type)), $format),
            'message'        => sprintf(
                __('Your %s export (%s) is ready for download. The download link will expire in 7 days.', 'orabooks'),
                strtoupper(str_replace('_', ' ', $export_type)),
                $format
            ),
            'priority'       => 'normal',
            'correlation_id' => $correlation_id,
            'export_id'      => $export_id,
            'export_type'    => $export_type,
            'format'         => $format,
        ], $org_id);
    }

    /**
     * Handle export_failed event from SL-114.
     * Notify the requesting user that their export failed.
     */
    public function on_export_failed($export_id, $data) {
        $user_id = !empty($data['user_id']) ? (int)$data['user_id'] : 0;
        $org_id = !empty($data['org_id']) ? (int)$data['org_id'] : 0;
        $export_type = !empty($data['export_type']) ? $data['export_type'] : 'report';
        $format = !empty($data['format']) ? strtoupper($data['format']) : 'CSV';
        $error = !empty($data['error']) ? $data['error'] : __('Unknown error', 'orabooks');

        if (!$user_id) {
            return;
        }

        self::send_notification($user_id, 'export_failed', [
            'title'          => __('Export Failed', 'orabooks'),
            'message'        => sprintf(
                __('Your %s export (%s) failed to generate. Error: %s. Please try again or contact support.', 'orabooks'),
                strtoupper(str_replace('_', ' ', $export_type)),
                $format,
                $error
            ),
            'priority'       => 'high',
            'correlation_id' => 'export_' . $export_id,
            'export_id'      => $export_id,
            'export_type'    => $export_type,
            'format'         => $format,
            'error'          => $error,
        ], $org_id);
    }

    /**
     * Handle csv_import_completed from SL-113.
     */
    public function on_csv_import_completed($import_id, $data) {
        $user_id = !empty($data['user_id']) ? (int) $data['user_id'] : 0;
        $org_id = !empty($data['org_id']) ? (int) $data['org_id'] : 0;
        $processed = !empty($data['processed']) ? (int) $data['processed'] : 0;
        $escalated = !empty($data['escalated']) ? (int) $data['escalated'] : 0;
        $resource_type = !empty($data['resource_type']) ? $data['resource_type'] : 'data';

        if (!$user_id) {
            return;
        }

        self::send_notification($user_id, 'csv_import_completed', [
            'title'          => __('CSV Import Complete', 'orabooks'),
            'message'        => sprintf(
                __('Your %s CSV import finished: %d rows processed, %d sent to review.', 'orabooks'),
                str_replace('_', ' ', $resource_type),
                $processed,
                $escalated
            ),
            'priority'       => $escalated > 0 ? 'high' : 'normal',
            'correlation_id' => 'csv_import_' . (int) $import_id,
            'import_id'      => (int) $import_id,
            'processed'      => $processed,
            'escalated'    => $escalated,
        ], $org_id);
    }

    /**
     * Handle csv_import_failed from SL-113.
     */
    public function on_csv_import_failed($import_id, $data) {
        $user_id = !empty($data['user_id']) ? (int) $data['user_id'] : 0;
        $org_id = !empty($data['org_id']) ? (int) $data['org_id'] : 0;
        $reason = !empty($data['reason']) ? $data['reason'] : __('Unknown error', 'orabooks');

        if (!$user_id) {
            return;
        }

        self::send_notification($user_id, 'csv_import_failed', [
            'title'          => __('CSV Import Failed', 'orabooks'),
            'message'        => sprintf(
                __('Your CSV import could not be processed. %s', 'orabooks'),
                $reason
            ),
            'priority'       => 'high',
            'correlation_id' => 'csv_import_' . (int) $import_id,
            'import_id'      => (int) $import_id,
            'reason'         => $reason,
        ], $org_id);
    }

    /**
     * Handle csv_row_escalated from SL-113 (SL-076 AI review placeholder).
     */
    public function on_csv_row_escalated($import_id, $data) {
        $org_id = !empty($data['org_id']) ? (int) $data['org_id'] : 0;
        $row_index = !empty($data['row_index']) ? (int) $data['row_index'] : 0;
        $confidence = !empty($data['confidence']) ? (float) $data['confidence'] : 0;

        if (!$org_id) {
            return;
        }

        self::notify_org_admins($org_id, 'csv_row_escalated', [
            'title'          => sprintf(__('CSV Row Needs Review (row %d)', 'orabooks'), $row_index + 1),
            'message'        => sprintf(
                __('Import #%d row %d has low confidence (%.0f%%) and was sent to AI review.', 'orabooks'),
                (int) $import_id,
                $row_index + 1,
                $confidence
            ),
            'priority'       => 'normal',
            'correlation_id' => 'csv_escalated_' . (int) $import_id . '_' . $row_index,
            'import_id'      => (int) $import_id,
            'row_index'      => $row_index,
            'confidence'     => $confidence,
        ]);
    }

    /**
     * Static wrapper for external use (e.g., from commission engine).
     */
    public static function notify($user_id, $event_type, $payload = [], $org_id = null) {
        return self::send_notification($user_id, $event_type, $payload, $org_id);
    }
}
