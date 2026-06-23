<?php
/**
 * OraBooks Audit Logging
 *
 * Immutable audit trail for all system events with retention and archival.
 */

if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Audit {

 /** Event types that must not trigger audit_log_viewed recursion. */
 private static $meta_event_types = [
 'audit_log_viewed',
 'audit_log_exported',
 'audit_log_archival',
 ];

 private static $instance = null;

 public static function init() {
 if (self::$instance === null) {
 self::$instance = new self;
 add_action('wp_ajax_orabooks_get_audit_logs', [self::$instance, 'ajax_get_logs']);
 add_action('wp_ajax_nopriv_orabooks_get_audit_logs', [self::$instance, 'ajax_get_logs']);
 add_action('wp_ajax_orabooks_export_audit_logs', [self::$instance, 'ajax_export_logs']);
 add_action('wp_ajax_nopriv_orabooks_export_audit_logs', [self::$instance, 'ajax_export_logs']);

 add_action('orabooks_daily_cleanup', [self::$instance, 'archive_old_logs']);
 }
 return self::$instance;
 }

 /**
 * Log an audit event ( §5.2).
 */
 public static function log_event($event_type, $description, $severity = 'info', $metadata = null, $user_id = null, $org_id = null, $correlation_id = null) {
 global $wpdb;

 $table = OraBooks_Database::table('audit_logs');

 if ($user_id === null && function_exists('orabooks_get_current_user_id')) {
 $user_id = orabooks_get_current_user_id;
 }

 $severity = in_array($severity, ['info', 'warning', 'critical'], true) ? $severity: 'info';
 if ($correlation_id === null || trim((string) $correlation_id) === '') {
 $correlation_id = function_exists('orabooks_get_correlation_id')
 ? orabooks_get_correlation_id(true)
: orabooks_uuid;
 }

 $sanitized = self::sanitize_metadata($metadata);

 $wpdb->insert(
 $table,
 [
 'org_id' => $org_id ?: 0,
 'user_id' => $user_id ?: null,
 'event_type' => (string) $event_type,
 'severity' => $severity,
 'description' => $description,
 'ip_address' => orabooks_get_client_ip,
 'user_agent' => orabooks_get_user_agent,
 'correlation_id' => (string) $correlation_id,
 'metadata' => $sanitized ? wp_json_encode($sanitized): null,
 ],
 ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
 );

 return $wpdb->insert_id;
 }

 /**
 * Sanitize metadata to remove secrets and PII ( §5.1).
 */
 private static function sanitize_metadata($metadata) {
 if (empty($metadata) || !is_array($metadata)) {
 return null;
 }

 if (class_exists('OraBooks_Secrets') && method_exists('OraBooks_Secrets', 'redact_sensitive')) {
 $metadata = OraBooks_Secrets::redact_sensitive($metadata);
 }

 $sensitive_keys = ['password', 'token', 'secret', 'key', 'authorization', 'credit_card', 'ssn', 'backup_code', 'totp'];
 $sanitized = [];

 foreach ($metadata as $key => $value) {
 $key_lower = strtolower((string) $key);
 $should_mask = false;
 foreach ($sensitive_keys as $sk) {
 if (stripos($key_lower, $sk) !== false) {
 $should_mask = true;
 break;
 }
 }

 if ($should_mask) {
 $sanitized[$key] = '[REDACTED]';
 continue;
 }

 if (self::is_email_metadata_key($key_lower) && is_string($value) && $value !== '') {
 $sanitized[$key. '_masked'] = orabooks_mask_email($value);
 $sanitized[$key. '_hash'] = orabooks_hash_email($value);
 continue;
 }

 if (is_array($value)) {
 $sanitized[$key] = self::sanitize_metadata($value);
 } else {
 $sanitized[$key] = $value;
 }
 }

 return $sanitized;
 }

 private static function is_email_metadata_key($key_lower) {
 if (in_array($key_lower, ['email', 'customer_email', 'user_email', 'partner_email'], true)) {
 return true;
 }
 return (bool) preg_match('/_email$/', $key_lower);
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
 $where.= ' AND event_type = %s';
 $params[] = $args['event_type'];
 }
 if (!empty($args['user_id'])) {
 $where.= ' AND user_id = %d';
 $params[] = $args['user_id'];
 }
 if (!empty($args['severity'])) {
 $where.= ' AND severity = %s';
 $params[] = $args['severity'];
 }
 if (!empty($args['from_date'])) {
 $where.= ' AND created_at >= %s';
 $params[] = $args['from_date'];
 }
 if (!empty($args['to_date'])) {
 $where.= ' AND created_at <= %s';
 $params[] = $args['to_date'];
 }
 if (!empty($args['correlation_id'])) {
 $where.= ' AND correlation_id = %s';
 $params[] = $args['correlation_id'];
 }

 $limit = $args['limit'] ?? 100;
 $offset = $args['offset'] ?? 0;

 $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
 $params[] = min((int) $limit, 1000);
 $params[] = (int) $offset;

 $results = $wpdb->get_results($wpdb->prepare($sql, $params));

 if (empty($args['skip_view_log']) && !self::should_skip_view_audit($args)) {
 self::log_event('audit_log_viewed', 'Audit log accessed', 'info', [
 'filters' => array_diff_key($args, ['skip_view_log' => true]),
 ], orabooks_get_current_user_id, $all_orgs ? 0: $org_id);
 }

 return $results;
 }

 private static function should_skip_view_audit($args) {
 $event_type = $args['event_type'] ?? '';
 return in_array($event_type, self::$meta_event_types, true);
 }

 /**
 * Archive old audit logs (retention 365 days by default, §5.5).
 */
 public static function archive_old_logs() {
 global $wpdb;

 $retention_days = (int) get_option('orabooks_audit_retention_days', 365);
 $retention_days = max(30, $retention_days);
 $table = OraBooks_Database::table('audit_logs');
 $archive_table = OraBooks_Database::table('audit_logs_archive');

 $cutoff = gmdate('Y-m-d H:i:s', time - ($retention_days * DAY_IN_SECONDS));

 $wpdb->query('START TRANSACTION');
 $wpdb->query('SET @orabooks_audit_archival = 1');

 $moved = $wpdb->query($wpdb->prepare(
 "INSERT INTO {$archive_table} SELECT * FROM {$table} WHERE created_at < %s",
 $cutoff
 ));

 $wpdb->query($wpdb->prepare(
 "DELETE FROM {$table} WHERE created_at < %s",
 $cutoff
 ));

 $wpdb->query('SET @orabooks_audit_archival = NULL');
 $wpdb->query('COMMIT');

 self::log_event('audit_log_archival', 'Audit log archival completed', 'info', [
 'records_moved' => (int) $moved,
 'cutoff_date' => $cutoff,
 'retention_days' => $retention_days,
 ], null, 0);
 }

 /**
 * Export audit logs as CSV ( §5.4).
 */
 public static function export_csv($org_id, $args = []) {
 $logs = self::get_logs($org_id, array_merge($args, [
 'limit' => 1000,
 'skip_view_log' => true,
 ]));

 $org_slug = 'platform';
 if ($org_id > 0 && class_exists('OraBooks_Organization')) {
 $org = OraBooks_Organization::get($org_id);
 if ($org && !empty($org->subdomain)) {
 $org_slug = sanitize_title($org->subdomain);
 }
 }

 $filename = 'audit_logs_'. $org_slug. '_'. gmdate('Y-m-d'). '.csv';

 header('Content-Type: text/csv; charset=utf-8');
 header('Content-Disposition: attachment; filename="'. $filename. '"');

 $output = fopen('php://output', 'w');
 fputcsv($output, [
 'timestamp',
 'user_id',
 'user_email',
 'event_type',
 'severity',
 'description',
 'ip_address',
 'user_agent',
 'correlation_id',
 'metadata',
 ]);

 foreach ($logs as $log) {
 $user_email = '';
 if (!empty($log->user_id) && function_exists('orabooks_get_user_email')) {
 $raw = orabooks_get_user_email((int) $log->user_id);
 $user_email = $raw ? orabooks_mask_email($raw): '';
 }

 fputcsv($output, [
 $log->created_at,
 $log->user_id,
 $user_email,
 $log->event_type,
 $log->severity,
 $log->description,
 $log->ip_address,
 $log->user_agent,
 $log->correlation_id,
 $log->metadata,
 ]);
 }

 fclose($output);

 self::log_event('audit_log_exported', 'Audit log exported as CSV', 'info', [
 'row_count' => count($logs),
 'format' => 'csv',
 'filename' => $filename,
 ], orabooks_get_current_user_id, $org_id ?: 0);

 exit;
 }

 private static function resolve_audit_org_id($requested_org_id) {
 $user_id = function_exists('orabooks_get_current_user_id') ? (int) orabooks_get_current_user_id: 0;
 if (function_exists('orabooks_resolve_request_org_id')) {
 return (int) orabooks_resolve_request_org_id($user_id, $requested_org_id);
 }

 $org_id = intval($requested_org_id);
 if ($org_id > 0) {
 return $org_id;
 }

 if (function_exists('orabooks_get_current_org_id')) {
 $current_org_id = orabooks_get_current_org_id($user_id);
 if ($current_org_id) {
 return (int) $current_org_id;
 }
 }

 return 0;
 }

 private static function assert_audit_access($user_id, $org_id) {
 if (function_exists('current_user_can') && current_user_can('manage_options')) {
 return true;
 }

 if ($user_id <= 0) {
 orabooks_json_error('Not authenticated', 401);
 }

 if ($org_id <= 0) {
 orabooks_json_error('Organization is required', 400);
 }

 if (function_exists('orabooks_assert_tenant_access')) {
 $tenant = orabooks_assert_tenant_access($user_id, $org_id, false);
 if (is_wp_error($tenant)) {
 orabooks_json_error($tenant->get_error_message, 403);
 }
 }

 if (class_exists('OBN_Access_Control')) {
 if (!OBN_Access_Control::require_permission($user_id, $org_id, 'view_audit_logs')) {
 orabooks_json_error('You do not have permission to view audit logs. Contact Owner or Admin.', 403);
 }
 return true;
 }

 if (!orabooks_has_permission($user_id, $org_id, 'view_audit_logs')) {
 orabooks_json_error('You do not have permission to view audit logs. Contact Owner or Admin.', 403);
 }

 return true;
 }

 public function ajax_get_logs() {
 $user_id = orabooks_get_current_user_id;
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
 self::assert_audit_access($user_id, $org_id);
 $logs = self::get_logs($org_id, $args);
 }

 orabooks_json_success($logs);
 }

 public function ajax_export_logs() {
 $user_id = orabooks_get_current_user_id;
 $org_id = self::resolve_audit_org_id($_GET['org_id'] ?? 0);

 $args = [
 'event_type' => sanitize_text_field($_GET['event_type'] ?? ''),
 'user_id' => intval($_GET['user_id'] ?? 0),
 'severity' => sanitize_text_field($_GET['severity'] ?? ''),
 'from_date' => sanitize_text_field($_GET['from_date'] ?? ''),
 'to_date' => sanitize_text_field($_GET['to_date'] ?? ''),
 'correlation_id' => sanitize_text_field($_GET['correlation_id'] ?? ''),
 'limit' => 1000,
 'skip_view_log' => true,
 ];

 if (current_user_can('manage_options')) {
 if ($org_id <= 0) {
 $args['all_orgs'] = true;
 }
 self::export_csv($org_id, $args);
 } else {
 self::assert_audit_access($user_id, $org_id);
 self::export_csv($org_id, $args);
 }
 }
}
