<?php
/**
 * OraBooks Audit Logging (SL-009)
 * 
 * Immutable audit trail for all system events with retention and archival.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Audit {
    
    private static $instance = null;
    
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            add_action('wp_ajax_orabooks_get_audit_logs', [self::$instance, 'ajax_get_logs']);
            add_action('wp_ajax_orabooks_export_audit_logs', [self::$instance, 'ajax_export_logs']);
            
            // Daily cleanup hook
            add_action('orabooks_daily_cleanup', [self::$instance, 'archive_old_logs']);
        }
        return self::$instance;
    }
    
    /**
     * Log an audit event
     */
    public static function log_event($event_type, $description, $severity = 'info', $metadata = null, $user_id = null, $org_id = null) {
        global $wpdb;
        
        $table = OraBooks_Database::table('audit_logs');
        
        if ($user_id === null && function_exists('orabooks_get_current_user_id')) {
            $user_id = orabooks_get_current_user_id();
        }
        
        // Sanitize metadata - remove sensitive data
        $sanitized = self::sanitize_metadata($metadata);
        
        $wpdb->insert(
            $table,
            [
                'org_id' => $org_id ?: 0,
                'user_id' => $user_id ?: null,
                'event_type' => $event_type,
                'severity' => $severity,
                'description' => $description,
                'ip_address' => orabooks_get_client_ip(),
                'user_agent' => orabooks_get_user_agent(),
                'correlation_id' => orabooks_uuid(),
                'metadata' => $sanitized ? json_encode($sanitized) : null
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Sanitize metadata to remove secrets and PII
     */
    private static function sanitize_metadata($metadata) {
        if (empty($metadata)) {
            return null;
        }
        
        $sensitive_keys = ['password', 'token', 'secret', 'key', 'authorization', 'credit_card', 'ssn'];
        $sanitized = [];
        
        foreach ($metadata as $key => $value) {
            $should_mask = false;
            foreach ($sensitive_keys as $sk) {
                if (stripos($key, $sk) !== false) {
                    $should_mask = true;
                    break;
                }
            }
            $sanitized[$key] = $should_mask ? '[REDACTED]' : $value;
        }
        
        return $sanitized;
    }
    
    /**
     * Get audit logs with filters
     */
    public static function get_logs($org_id, $args = []) {
        global $wpdb;
        
        $table = OraBooks_Database::table('audit_logs');
        $params = [];
        $all_orgs = !empty($args['all_orgs']);

        if ($all_orgs) {
            $where = '1=1';
        } elseif ($org_id > 0) {
            $where = 'org_id = %d';
            $params[] = $org_id;
        } else {
            $where = 'org_id = %d';
            $params[] = 0;
        }
        
        if (!empty($args['event_type'])) {
            $where .= ' AND event_type = %s';
            $params[] = $args['event_type'];
        }
        if (!empty($args['user_id'])) {
            $where .= ' AND user_id = %d';
            $params[] = $args['user_id'];
        }
        if (!empty($args['severity'])) {
            $where .= ' AND severity = %s';
            $params[] = $args['severity'];
        }
        if (!empty($args['from_date'])) {
            $where .= ' AND created_at >= %s';
            $params[] = $args['from_date'];
        }
        if (!empty($args['to_date'])) {
            $where .= ' AND created_at <= %s';
            $params[] = $args['to_date'];
        }
        if (!empty($args['correlation_id'])) {
            $where .= ' AND correlation_id = %s';
            $params[] = $args['correlation_id'];
        }
        
        $limit = $args['limit'] ?? 100;
        $offset = $args['offset'] ?? 0;
        
        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = min($limit, 1000);
        $params[] = $offset;
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        // Log that audit was viewed (avoid infinite loop)
        if (!empty($args['event_type']) && $args['event_type'] === 'audit_log_viewed') {
            // Skip logging view events
        } elseif (empty($args['skip_view_log'])) {
            self::log_event('audit_log_viewed', 'Audit log accessed', 'info', [
                'filters' => $args
            ], orabooks_get_current_user_id(), $all_orgs ? 0 : $org_id);
        }
        
        return $results;
    }
    
    /**
     * Archive old audit logs (retention 365 days by default)
     */
    public static function archive_old_logs() {
        global $wpdb;
        
        $retention_days = get_option('orabooks_audit_retention_days', 365);
        $table = OraBooks_Database::table('audit_logs');
        $archive_table = OraBooks_Database::table('audit_logs_archive');
        
        $cutoff = date('Y-m-d H:i:s', time() - ($retention_days * 86400));
        
        $wpdb->query("START TRANSACTION");
        
        $moved = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$archive_table} SELECT * FROM {$table} WHERE created_at < %s",
            $cutoff
        ));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s",
            $cutoff
        ));
        
        $wpdb->query("COMMIT");
        
        self::log_event('audit_log_archival', "Audit log archival completed", 'info', [
            'records_moved' => $moved,
            'cutoff_date' => $cutoff
        ], null, null);
    }
    
    /**
     * Export audit logs as CSV
     */
    public static function export_csv($org_id, $args = []) {
        $logs = self::get_logs($org_id, array_merge($args, ['limit' => 1000]));
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Timestamp', 'User ID', 'Event Type', 'Severity', 'Description', 'IP', 'Correlation ID', 'Metadata']);
        
        foreach ($logs as $log) {
            fputcsv($output, [
                $log->created_at,
                $log->user_id,
                $log->event_type,
                $log->severity,
                $log->description,
                $log->ip_address,
                $log->correlation_id,
                $log->metadata
            ]);
        }
        
        fclose($output);
        
        self::log_event('audit_log_exported', "Audit log exported as CSV", 'info', [
            'row_count' => count($logs),
            'format' => 'csv'
        ], orabooks_get_current_user_id(), $org_id);
        
        exit;
    }

    /**
     * Resolve org scope for audit API calls (JWT-aware frontend).
     */
    private static function resolve_audit_org_id($requested_org_id) {
        $org_id = intval($requested_org_id);
        if ($org_id > 0) {
            return $org_id;
        }

        if (function_exists('orabooks_get_current_org_id')) {
            $current_org_id = orabooks_get_current_org_id();
            if ($current_org_id) {
                return (int) $current_org_id;
            }
        }

        return 0;
    }
    
    // AJAX handlers
    public function ajax_get_logs() {
        $user_id = orabooks_get_current_user_id();
        $org_id = self::resolve_audit_org_id($_GET['org_id'] ?? 0);

        $args = [
            'event_type' => sanitize_text_field($_GET['event_type'] ?? ''),
            'user_id' => intval($_GET['user_id'] ?? 0),
            'severity' => sanitize_text_field($_GET['severity'] ?? ''),
            'from_date' => sanitize_text_field($_GET['from_date'] ?? ''),
            'to_date' => sanitize_text_field($_GET['to_date'] ?? ''),
            'correlation_id' => sanitize_text_field($_GET['correlation_id'] ?? ''),
            'limit' => intval($_GET['limit'] ?? 100),
            'offset' => intval($_GET['offset'] ?? 0),
        ];

        if (current_user_can('manage_options')) {
            if ($org_id <= 0) {
                $args['all_orgs'] = true;
            }
            $logs = self::get_logs($org_id, $args);
        } else {
            if (!$org_id) {
                orabooks_json_error('Organization is required', 400);
            }
            if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'view_audit_logs')) {
                orabooks_json_error('Permission denied', 403);
            }
            $logs = self::get_logs($org_id, $args);
        }

        orabooks_json_success($logs);
    }
    
    public function ajax_export_logs() {
        $user_id = orabooks_get_current_user_id();
        $org_id = self::resolve_audit_org_id($_GET['org_id'] ?? 0);

        $args = [
            'event_type' => sanitize_text_field($_GET['event_type'] ?? ''),
            'user_id' => intval($_GET['user_id'] ?? 0),
            'from_date' => sanitize_text_field($_GET['from_date'] ?? ''),
            'to_date' => sanitize_text_field($_GET['to_date'] ?? ''),
            'limit' => 1000,
            'skip_view_log' => true,
        ];

        if (current_user_can('manage_options')) {
            if ($org_id <= 0) {
                $args['all_orgs'] = true;
            }
            self::export_csv($org_id, $args);
        } else {
            if (!$org_id) {
                orabooks_json_error('Organization is required', 400);
            }
            if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'view_audit_logs')) {
                orabooks_json_error('Permission denied', 403);
            }
            self::export_csv($org_id, $args);
        }
    }
}