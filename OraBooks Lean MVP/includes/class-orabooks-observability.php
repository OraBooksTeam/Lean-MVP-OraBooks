<?php
/**
 * OraBooks Observability & Monitoring
 *
 * Central metrics ingestion, health snapshots, threshold evaluation,
 * and alerting for platform subsystems.
 */

if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Observability {

 const METRIC_RETENTION_DAYS = 90;
 const HEALTH_RETENTION_DAYS = 30;

 private static $instance = null;

 /** Default alert thresholds (can be filtered). */
 private static $thresholds = [
 'eventbus_pending' => 100,
 'eventbus_dead_letter' => 10,
 'async_queue_pending' => 200,
 'async_queue_dead' => 20,
 'notification_dead' => 25,
 'export_failed_24h' => 15,
 'workflow_failures_24h' => 20,
 'expense_ocr_pending' => 50,
 'expense_ocr_failed_24h'=> 10,
 'slo_error_budget_min' => 10,
 ];

 /** Platform SLO targets ( / SLA governance). */
 private static $slos = [
 'notifications_delivery' => [
 'name' => 'Notification delivery success',
 'description' => 'Delivered notifications vs terminal failures in the rolling window.',
 'target_percent' => 99.5,
 'window_days' => 30,
 ],
 'notifications_critical_latency' => [
 'name' => 'Critical notification latency',
 'description' => 'Critical notifications delivered within 5 seconds ( SLA).',
 'target_percent' => 99.0,
 'window_days' => 30,
 'latency_ms' => 5000,
 ],
 'async_queue_success' => [
 'name' => 'Async queue job success',
 'description' => 'Completed async jobs vs failed/dead-letter jobs.',
 'target_percent' => 99.0,
 'window_days' => 30,
 ],
 'workflow_transitions' => [
 'name' => 'Workflow transition success',
 'description' => 'Successful workflow transitions vs failed preconditions.',
 'target_percent' => 99.5,
 'window_days' => 30,
 ],
 'eventbus_processing' => [
 'name' => 'Event bus processing success',
 'description' => 'Processed outbox messages vs dead-letter backlog.',
 'target_percent' => 99.0,
 'window_days' => 30,
 ],
 ];

 public static function init() {
 if (self::$instance === null) {
 self::$instance = new self;

 add_action('orabooks_observability_collect', [self::$instance, 'cron_collect_metrics']);
 add_action('orabooks_observability_purge', [self::$instance, 'cron_purge_old_metrics']);
 add_action('orabooks_observability_evaluate', [self::$instance, 'cron_evaluate_thresholds']);

 add_action('orabooks_eventbus_lag_alert', [self::$instance, 'on_eventbus_lag'], 10, 1);
 add_action('orabooks_eventbus_dead_letter_alert', [self::$instance, 'on_eventbus_dead_letter'], 10, 1);
 add_action('orabooks_async_queue_lag_alert', [self::$instance, 'on_async_queue_lag'], 10, 1);
 add_action('orabooks_async_queue_dead_letter_alert', [self::$instance, 'on_async_queue_dead_letter'], 10, 1);

 add_action('wp_ajax_orabooks_observability_dashboard', [self::$instance, 'ajax_dashboard']);
 add_action('wp_ajax_orabooks_observability_metrics', [self::$instance, 'ajax_metrics']);
 }

 return self::$instance;
 }

 public static function get_create_table_sql() {
 global $wpdb;
 $charset_collate = $wpdb->get_charset_collate;
 $tables = [];

 $table_metrics = OraBooks_Database::table('platform_metrics');
 $tables[] = "CREATE TABLE IF NOT EXISTS {$table_metrics} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 org_id BIGINT UNSIGNED NULL,
 service VARCHAR(50) NOT NULL,
 metric_name VARCHAR(64) NOT NULL,
 metric_value DECIMAL(20,4) NOT NULL DEFAULT 0,
 labels JSON DEFAULT NULL,
 recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_service_metric (service, metric_name, recorded_at),
 INDEX idx_org_recorded (org_id, recorded_at)
 ) {$charset_collate};";

 $table_health = OraBooks_Database::table('health_check_runs');
 $tables[] = "CREATE TABLE IF NOT EXISTS {$table_health} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 service VARCHAR(50) NOT NULL,
 status ENUM('healthy','degraded','critical') NOT NULL DEFAULT 'healthy',
 details JSON DEFAULT NULL,
 recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_service_recorded (service, recorded_at),
 INDEX idx_status_recorded (status, recorded_at)
 ) {$charset_collate};";

 return $tables;
 }

 /**
 * Record a single metric sample.
 */
 public static function record_metric($service, $metric_name, $value, $org_id = null, $labels = []) {
 global $wpdb;

 $table = OraBooks_Database::table('platform_metrics');
 $wpdb->insert($table, [
 'org_id' => $org_id ? intval($org_id): null,
 'service' => sanitize_text_field($service),
 'metric_name' => sanitize_text_field($metric_name),
 'metric_value' => (float) $value,
 'labels' => !empty($labels) ? wp_json_encode($labels): null,
 'recorded_at' => current_time('mysql', true),
 ], ['%d', '%s', '%s', '%f', '%s', '%s']);

 return (int) $wpdb->insert_id;
 }

 /**
 * Persist a health snapshot for a subsystem.
 */
 public static function record_health_check($service, $status, $details = []) {
 global $wpdb;

 $status = in_array($status, ['healthy', 'degraded', 'critical'], true) ? $status: 'healthy';
 $table = OraBooks_Database::table('health_check_runs');

 $wpdb->insert($table, [
 'service' => sanitize_text_field($service),
 'status' => $status,
 'details' => wp_json_encode($details),
 'recorded_at' => current_time('mysql', true),
 ], ['%s', '%s', '%s', '%s']);

 return (int) $wpdb->insert_id;
 }

 /**
 * Collect metrics from event bus, async queue, notifications, and exports.
 */
 public static function collect_platform_metrics() {
 global $wpdb;

 $snapshots = [];

 // Event bus
 if (class_exists('OraBooks_Event_Module')) {
 $event_health = OraBooks_Event_Module::get_health;
 $event_pending = (int) ($event_health['pending'] ?? 0);
 $event_dead = (int) ($event_health['dead_letter'] ?? 0);
 } else {
 $outbox = OraBooks_Database::table('outbox_messages');
 $event_pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$outbox} WHERE status = 'pending'");
 $event_dead = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$outbox} WHERE status = 'dead_letter'");
 }
 self::record_metric('eventbus', 'queue_depth', $event_pending);
 self::record_metric('eventbus', 'dead_letter_count', $event_dead);
 $event_status = self::status_from_counts($event_pending, self::$thresholds['eventbus_pending'], $event_dead, self::$thresholds['eventbus_dead_letter']);
 self::record_health_check('eventbus', $event_status, [
 'pending' => $event_pending,
 'dead_letter' => $event_dead,
 ]);
 $snapshots['eventbus'] = ['pending' => $event_pending, 'dead_letter' => $event_dead, 'status' => $event_status];

 // Async queue
 $async_pending = 0;
 $async_dead = 0;
 $async_failure_rate = 0;
 $async_latency = 0;
 if (class_exists('OraBooks_AsyncQueue') && method_exists('OraBooks_AsyncQueue', 'get_queue_stats')) {
 $queue_stats = OraBooks_AsyncQueue::get_queue_stats;
 $async_pending = (int) ($queue_stats['pending_count'] ?? 0);
 $async_dead = (int) ($queue_stats['dead_letter_count'] ?? 0);
 $async_failure_rate = (float) ($queue_stats['failure_rate_24h'] ?? 0);
 $async_latency = (float) ($queue_stats['avg_latency_seconds'] ?? 0);
 }
 self::record_metric('async_queue', 'queue_depth', $async_pending);
 self::record_metric('async_queue', 'dead_letter_count', $async_dead);
 self::record_metric('async_queue', 'failure_rate_24h', $async_failure_rate);
 self::record_metric('async_queue', 'avg_latency_seconds', $async_latency);
 $async_status = self::status_from_counts($async_pending, self::$thresholds['async_queue_pending'], $async_dead, self::$thresholds['async_queue_dead']);
 self::record_health_check('async_queue', $async_status, [
 'pending' => $async_pending,
 'dead_letter' => $async_dead,
 'failure_rate_24h' => $async_failure_rate,
 'avg_latency_seconds' => $async_latency,
 ]);
 $snapshots['async_queue'] = [
 'pending' => $async_pending,
 'dead_letter' => $async_dead,
 'failure_rate_24h' => $async_failure_rate,
 'avg_latency_seconds' => $async_latency,
 'status' => $async_status,
 ];

 // Notifications
 $notifications = OraBooks_Database::table('notifications');
 $notif_pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$notifications} WHERE status = 'pending'");
 $notif_dead = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$notifications} WHERE status = 'dead_letter'");
 self::record_metric('notifications', 'pending_count', $notif_pending);
 self::record_metric('notifications', 'dead_letter_count', $notif_dead);
 $notif_status = $notif_dead >= self::$thresholds['notification_dead'] ? 'critical': ($notif_pending > 50 ? 'degraded': 'healthy');
 self::record_health_check('notifications', $notif_status, [
 'pending' => $notif_pending,
 'dead_letter' => $notif_dead,
 ]);
 $snapshots['notifications'] = ['pending' => $notif_pending, 'dead_letter' => $notif_dead, 'status' => $notif_status];

 // Exports
 $exports = OraBooks_Database::table('export_requests');
 $export_pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$exports} WHERE status IN ('pending','generating')");
 $export_failed_24h = (int) $wpdb->get_var(
 "SELECT COUNT(*) FROM {$exports} WHERE status = 'failed' AND created_at >= DATE_SUB(NOW, INTERVAL 24 HOUR)"
 );
 self::record_metric('exports', 'pending_count', $export_pending);
 self::record_metric('exports', 'failed_24h', $export_failed_24h);
 $export_status = $export_failed_24h >= self::$thresholds['export_failed_24h'] ? 'critical': ($export_pending > 25 ? 'degraded': 'healthy');
 self::record_health_check('exports', $export_status, [
 'pending' => $export_pending,
 'failed_24h' => $export_failed_24h,
 ]);
 $snapshots['exports'] = ['pending' => $export_pending, 'failed_24h' => $export_failed_24h, 'status' => $export_status];

 // Workflow engine
 $workflow_health = self::get_workflow_health;
 self::record_metric('workflow', 'transition_success_24h', (float) ($workflow_health['transitions_24h'] ?? 0));
 self::record_metric('workflow', 'transition_failure_24h', (float) ($workflow_health['failures_24h'] ?? 0));
 $workflow_status = ($workflow_health['failures_24h'] ?? 0) >= self::$thresholds['workflow_failures_24h'] ? 'degraded': 'healthy';
 self::record_health_check('workflow', $workflow_status, $workflow_health);
 $snapshots['workflow'] = array_merge($workflow_health, ['status' => $workflow_status]);

 $classification_health = self::get_classification_health;
 self::record_metric('classification', 'processed_24h', (float) ($classification_health['processed_24h'] ?? 0));
 self::record_metric('classification', 'override_rate_24h', (float) ($classification_health['override_rate'] ?? 0));
 $classification_status = ($classification_health['failed_24h'] ?? 0) >= 5 ? 'degraded': 'healthy';
 self::record_health_check('classification', $classification_status, $classification_health);
 $snapshots['classification'] = array_merge($classification_health, ['status' => $classification_status]);

 if (class_exists('OraBooks_Expenses') && method_exists('OraBooks_Expenses', 'get_observability_stats')) {
 $ocr_health = OraBooks_Expenses::get_observability_stats;
 self::record_metric('expense_ocr', 'queue_depth', (float) ($ocr_health['queue_depth'] ?? 0));
 self::record_metric('expense_ocr', 'failed_24h', (float) ($ocr_health['failed_24h'] ?? 0));
 self::record_metric('expense_ocr', 'completed_24h', (float) ($ocr_health['completed_24h'] ?? 0));
 self::record_metric('expense_ocr', 'success_rate_24h', (float) ($ocr_health['success_rate_24h'] ?? 1));
 if ($ocr_health['avg_confidence_24h'] !== null) {
 self::record_metric('expense_ocr', 'avg_confidence_24h', (float) $ocr_health['avg_confidence_24h']);
 }
 $ocr_status = 'healthy';
 if (($ocr_health['failed_24h'] ?? 0) >= self::$thresholds['expense_ocr_failed_24h']) {
 $ocr_status = 'critical';
 } elseif (($ocr_health['queue_depth'] ?? 0) >= self::$thresholds['expense_ocr_pending']) {
 $ocr_status = 'degraded';
 }
 self::record_health_check('expense_ocr', $ocr_status, $ocr_health);
 $snapshots['expense_ocr'] = array_merge($ocr_health, ['status' => $ocr_status]);
 }

 orabooks_log_event('observability_metrics_collected', 'Platform metrics collected', 'info', [
 'services' => array_keys($snapshots),
 ]);

 return ['snapshots' => $snapshots, 'collected_at' => current_time('mysql', true)];
 }

 /**
 * Evaluate latest snapshots and alert platform admins via.
 */
 public static function evaluate_thresholds($snapshots = null) {
 if ($snapshots === null) {
 $result = self::collect_platform_metrics;
 $snapshots = $result['snapshots'];
 }

 $thresholds = apply_filters('orabooks_observability_thresholds', self::$thresholds);
 $alerts = [];

 if (($snapshots['eventbus']['pending'] ?? 0) > $thresholds['eventbus_pending']) {
 $alerts[] = self::build_alert('eventbus_lag', 'Event bus queue lag', $snapshots['eventbus']);
 }
 if (($snapshots['eventbus']['dead_letter'] ?? 0) > $thresholds['eventbus_dead_letter']) {
 $alerts[] = self::build_alert('eventbus_dead_letter', 'Event bus dead-letter backlog', $snapshots['eventbus']);
 }
 if (($snapshots['async_queue']['pending'] ?? 0) > $thresholds['async_queue_pending']) {
 $alerts[] = self::build_alert('async_queue_lag', 'Async queue lag', $snapshots['async_queue']);
 }
 if (($snapshots['async_queue']['dead_letter'] ?? 0) > $thresholds['async_queue_dead']) {
 $alerts[] = self::build_alert('async_queue_dead_letter', 'Async queue dead-letter backlog', $snapshots['async_queue']);
 }
 if (($snapshots['notifications']['dead_letter'] ?? 0) > $thresholds['notification_dead']) {
 $alerts[] = self::build_alert('notification_dead_letter', 'Notification dead-letter backlog', $snapshots['notifications']);
 }
 if (($snapshots['exports']['failed_24h'] ?? 0) > $thresholds['export_failed_24h']) {
 $alerts[] = self::build_alert('export_failures', 'Export failure rate elevated', $snapshots['exports']);
 }
 if (($snapshots['workflow']['failures_24h'] ?? 0) > $thresholds['workflow_failures_24h']) {
 $alerts[] = self::build_alert('workflow_failures', 'Workflow transition failure rate elevated', $snapshots['workflow']);
 }
 if (($snapshots['expense_ocr']['queue_depth'] ?? 0) > $thresholds['expense_ocr_pending']) {
 $alerts[] = self::build_alert('expense_ocr_lag', 'Expense OCR queue backlog', $snapshots['expense_ocr']);
 }
 if (($snapshots['expense_ocr']['failed_24h'] ?? 0) > $thresholds['expense_ocr_failed_24h']) {
 $alerts[] = self::build_alert('expense_ocr_failures', 'Expense OCR failure rate elevated', $snapshots['expense_ocr']);
 }

 $slo_alerts = self::evaluate_error_budgets;
 foreach ($slo_alerts as $slo_alert) {
 $alerts[] = $slo_alert;
 }

 foreach ($alerts as $alert) {
 self::notify_platform_admins($alert['event_type'], $alert['payload']);
 }

 return ['alerts_sent' => count($alerts), 'alerts' => $alerts];
 }

 /**
 * Dashboard payload for admin UI / AJAX.
 */
 public static function get_dashboard($hours = 24) {
 global $wpdb;

 $hours = max(1, min(168, intval($hours)));
 $since = date('Y-m-d H:i:s', time - ($hours * 3600));

 $health_table = OraBooks_Database::table('health_check_runs');
 $latest_health = $wpdb->get_results(
 "SELECT h1.*
 FROM {$health_table} h1
 INNER JOIN (
 SELECT service, MAX(id) AS max_id
 FROM {$health_table}
 GROUP BY service
 ) h2 ON h1.id = h2.max_id
 ORDER BY h1.service ASC"
 );

 $metrics_table = OraBooks_Database::table('platform_metrics');
 $recent_metrics = $wpdb->get_results($wpdb->prepare(
 "SELECT service, metric_name, AVG(metric_value) AS avg_value, MAX(metric_value) AS max_value
 FROM {$metrics_table}
 WHERE recorded_at >= %s
 GROUP BY service, metric_name
 ORDER BY service ASC, metric_name ASC",
 $since
 ));

 $collection = self::collect_platform_metrics;

 return [
 'collected_at' => $collection['collected_at'],
 'snapshots' => $collection['snapshots'],
 'latest_health' => $latest_health,
 'aggregates_24h' => $recent_metrics,
 'thresholds' => apply_filters('orabooks_observability_thresholds', self::$thresholds),
 'workflow_by_org' => self::get_workflow_health,
 'classification_by_org' => self::get_classification_health,
 'slos' => self::get_slo_dashboard,
 ];
 }

 public static function get_slo_definitions() {
 return apply_filters('orabooks_observability_slos', self::$slos);
 }

 /**
 * Compute SLO compliance and error budget for all platform objectives.
 */
 public static function get_slo_dashboard($window_days = null) {
 $definitions = self::get_slo_definitions;
 $results = [];

 foreach ($definitions as $slo_id => $definition) {
 $days = $window_days ?? (int) ($definition['window_days'] ?? 30);
 $sli = self::collect_sli($slo_id, $days);
 $results[$slo_id] = self::build_slo_status($slo_id, $definition, $sli, $days);
 }

 return [
 'window_days' => $window_days ?? 30,
 'objectives' => $results,
 'summary' => self::summarize_slo_dashboard($results),
 ];
 }

 public static function evaluate_error_budgets() {
 $dashboard = self::get_slo_dashboard;
 $thresholds = apply_filters('orabooks_observability_thresholds', self::$thresholds);
 $min_budget = (float) ($thresholds['slo_error_budget_min'] ?? 10);
 $alerts = [];

 foreach ($dashboard['objectives'] as $slo_id => $objective) {
 $remaining = (float) ($objective['error_budget_remaining_percent'] ?? 0);
 if ($objective['status'] === 'breached') {
 $alerts[] = self::build_alert(
 'slo_breach_'. $slo_id,
 sprintf('%s SLO breached', $objective['name']),
 $objective
 );
 continue;
 }

 if ($remaining <= $min_budget) {
 $alerts[] = self::build_alert(
 'slo_budget_low_'. $slo_id,
 sprintf('%s error budget nearly exhausted', $objective['name']),
 $objective
 );
 }
 }

 return $alerts;
 }

 private static function collect_sli($slo_id, $window_days) {
 global $wpdb;

 $window_days = max(1, min(90, (int) $window_days));
 $since = gmdate('Y-m-d H:i:s', time - ($window_days * 86400));

 switch ($slo_id) {
 case 'notifications_delivery':
 $table = OraBooks_Database::table('notifications');
 $row = $wpdb->get_row($wpdb->prepare(
 "SELECT
 COUNT(*) AS total,
 SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS good,
 SUM(CASE WHEN status IN ('failed', 'dead_letter') THEN 1 ELSE 0 END) AS bad
 FROM {$table}
 WHERE created_at >= %s",
 $since
 ));
 return self::normalize_sli_row($row);

 case 'notifications_critical_latency':
 $table = OraBooks_Database::table('notifications');
 $latency_ms = (int) (self::$slos[$slo_id]['latency_ms'] ?? 5000);
 $row = $wpdb->get_row($wpdb->prepare(
 "SELECT
 COUNT(*) AS total,
 SUM(CASE WHEN TIMESTAMPDIFF(MICROSECOND, created_at, delivered_at) / 1000 <= %d THEN 1 ELSE 0 END) AS good,
 SUM(CASE WHEN TIMESTAMPDIFF(MICROSECOND, created_at, delivered_at) / 1000 > %d THEN 1 ELSE 0 END) AS bad
 FROM {$table}
 WHERE priority = 'critical'
 AND status = 'delivered'
 AND delivered_at IS NOT NULL
 AND created_at >= %s",
 $latency_ms,
 $latency_ms,
 $since
 ));
 return self::normalize_sli_row($row);

 case 'async_queue_success':
 if (class_exists('OraBooks_AsyncQueue')) {
 $table = OraBooks_Database::table('async_jobs');
 $row = $wpdb->get_row($wpdb->prepare(
 "SELECT
 COUNT(*) AS total,
 SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS good,
 SUM(CASE WHEN status IN ('failed', 'dead_letter') THEN 1 ELSE 0 END) AS bad
 FROM {$table}
 WHERE created_at >= %s",
 $since
 ));
 return self::normalize_sli_row($row);
 }
 return self::empty_sli;

 case 'workflow_transitions':
 $health = self::get_workflow_health(0);
 $total = (int) ($health['transitions_24h'] ?? 0) + (int) ($health['failures_24h'] ?? 0);
 if ($window_days > 1) {
 $transitions_table = OraBooks_Database::table('state_machine_transitions');
 $audit_table = OraBooks_Database::table('audit_logs');
 $good = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM {$transitions_table} WHERE created_at >= %s",
 $since
 ));
 $bad = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM {$audit_table}
 WHERE created_at >= %s
 AND event_type IN ('invalid_state_transition', 'workflow_transition_failed', 'workflow_precondition_failed')",
 $since
 ));
 $total = $good + $bad;
 } else {
 $good = (int) ($health['transitions_24h'] ?? 0);
 $bad = (int) ($health['failures_24h'] ?? 0);
 $total = $good + $bad;
 }

 return [
 'total' => $total,
 'good' => $good,
 'bad' => $bad,
 'current_percent' => $total > 0 ? round(($good / $total) * 100, 4): 100.0,
 ];

 case 'eventbus_processing':
 $outbox = OraBooks_Database::table('outbox_messages');
 $row = $wpdb->get_row($wpdb->prepare(
 "SELECT
 COUNT(*) AS total,
 SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS good,
 SUM(CASE WHEN status IN ('failed', 'dead_letter') THEN 1 ELSE 0 END) AS bad
 FROM {$outbox}
 WHERE created_at >= %s",
 $since
 ));
 return self::normalize_sli_row($row);

 default:
 return self::empty_sli;
 }
 }

 private static function normalize_sli_row($row) {
 $total = (int) ($row->total ?? 0);
 $good = (int) ($row->good ?? 0);
 $bad = (int) ($row->bad ?? 0);

 if ($total <= 0 && ($good + $bad) > 0) {
 $total = $good + $bad;
 }

 return [
 'total' => $total,
 'good' => $good,
 'bad' => $bad,
 'current_percent' => $total > 0 ? round(($good / $total) * 100, 4): 100.0,
 ];
 }

 private static function empty_sli() {
 return [
 'total' => 0,
 'good' => 0,
 'bad' => 0,
 'current_percent' => 100.0,
 ];
 }

 private static function build_slo_status($slo_id, $definition, $sli, $window_days) {
 $target = (float) ($definition['target_percent'] ?? 99.0);
 $current = (float) ($sli['current_percent'] ?? 100.0);
 $total = (int) ($sli['total'] ?? 0);
 $allowed_bad = $total > 0 ? ($total * ((100 - $target) / 100)): 0.0;
 $actual_bad = (float) ($sli['bad'] ?? 0);
 $budget_used = $allowed_bad > 0 ? min(100, round(($actual_bad / $allowed_bad) * 100, 2)): 0.0;
 $budget_remaining = max(0, round(100 - $budget_used, 2));
 $burn_rate = $window_days > 0 ? round($budget_used / $window_days, 2): 0.0;

 $status = 'healthy';
 if ($current < $target || ($allowed_bad > 0 && $actual_bad > $allowed_bad)) {
 $status = 'breached';
 } elseif ($budget_remaining <= 25) {
 $status = 'at_risk';
 }

 self::record_metric('slo', $slo_id. '_compliance_percent', $current);
 self::record_metric('slo', $slo_id. '_error_budget_remaining', $budget_remaining);

 return [
 'id' => $slo_id,
 'name' => $definition['name'] ?? $slo_id,
 'description' => $definition['description'] ?? '',
 'target_percent' => $target,
 'current_percent' => $current,
 'window_days' => $window_days,
 'sample_total' => $total,
 'sample_good' => (int) ($sli['good'] ?? 0),
 'sample_bad' => (int) ($sli['bad'] ?? 0),
 'error_budget_allowed_failures' => round($allowed_bad, 2),
 'error_budget_used_failures' => round($actual_bad, 2),
 'error_budget_remaining_percent' => $budget_remaining,
 'error_budget_burn_rate_per_day' => $burn_rate,
 'status' => $status,
 'meets_slo' => $current >= $target,
 ];
 }

 private static function summarize_slo_dashboard($objectives) {
 $total = count($objectives);
 $healthy = 0;
 $at_risk = 0;
 $breached = 0;

 foreach ($objectives as $objective) {
 switch ($objective['status'] ?? 'healthy') {
 case 'breached':
 $breached++;
 break;
 case 'at_risk':
 $at_risk++;
 break;
 default:
 $healthy++;
 break;
 }
 }

 return [
 'total' => $total,
 'healthy' => $healthy,
 'at_risk' => $at_risk,
 'breached' => $breached,
 ];
 }

 /**
 * Workflow transition health (global or org-scoped).
 */
 public static function get_workflow_health($org_id = 0) {
 global $wpdb;

 $org_id = (int) $org_id;
 $since = gmdate('Y-m-d H:i:s', time - 86400);
 $transitions_table = OraBooks_Database::table('state_machine_transitions');
 $audit_table = OraBooks_Database::table('audit_logs');

 $transition_sql = "SELECT COUNT(*) FROM {$transitions_table} WHERE created_at >= %s";
 $transition_params = [$since];
 if ($org_id > 0) {
 $transition_sql.= ' AND org_id = %d';
 $transition_params[] = $org_id;
 }
 $transitions_24h = (int) $wpdb->get_var($wpdb->prepare($transition_sql,...$transition_params));

 $failure_sql = "SELECT COUNT(*) FROM {$audit_table}
 WHERE created_at >= %s
 AND event_type IN ('invalid_state_transition', 'workflow_transition_failed', 'workflow_precondition_failed')";
 $failure_params = [$since];
 if ($org_id > 0) {
 $failure_sql.= ' AND org_id = %d';
 $failure_params[] = $org_id;
 }
 $failures_24h = (int) $wpdb->get_var($wpdb->prepare($failure_sql,...$failure_params));

 $total = $transitions_24h + $failures_24h;

 return [
 'org_id' => $org_id > 0 ? $org_id: null,
 'transitions_24h' => $transitions_24h,
 'failures_24h' => $failures_24h,
 'failure_rate' => $total > 0 ? round($failures_24h / $total, 4): 0.0,
 'window_hours' => 24,
 ];
 }

 /**
 * classification health (processed vs failed/overridden).
 */
 public static function get_classification_health($org_id = 0) {
 global $wpdb;

 $org_id = (int) $org_id;
 $since = gmdate('Y-m-d H:i:s', time - 86400);
 $audit_table = OraBooks_Database::table('audit_logs');

 $events = [
 'classification_suggested',
 'classification_failed',
 'classification_override',
 ];

 $placeholders = implode(',', array_fill(0, count($events), '%s'));
 $params = array_merge([$since], $events);
 $sql = "SELECT event_type, COUNT(*) AS total FROM {$audit_table}
 WHERE created_at >= %s AND event_type IN ({$placeholders})";

 if ($org_id > 0) {
 $sql.= ' AND org_id = %d';
 $params[] = $org_id;
 }

 $sql.= ' GROUP BY event_type';
 $rows = $wpdb->get_results($wpdb->prepare($sql,...$params));

 $counts = [
 'processed' => 0,
 'failed' => 0,
 'overridden' => 0,
 ];

 foreach ($rows ?: [] as $row) {
 if ($row->event_type === 'classification_suggested') {
 $counts['processed'] = (int) $row->total;
 } elseif ($row->event_type === 'classification_failed') {
 $counts['failed'] = (int) $row->total;
 } elseif ($row->event_type === 'classification_override') {
 $counts['overridden'] = (int) $row->total;
 }
 }

 $total = array_sum($counts);

 return [
 'org_id' => $org_id > 0 ? $org_id: null,
 'processed_24h' => $counts['processed'],
 'failed_24h' => $counts['failed'],
 'overridden_24h' => $counts['overridden'],
 'override_rate' => $total > 0 ? round($counts['overridden'] / $total, 4): 0.0,
 'window_hours' => 24,
 ];
 }

 /**
 * Query raw metric series.
 */
 public static function get_metric_series($service, $metric_name, $hours = 24, $limit = 500) {
 global $wpdb;

 $since = date('Y-m-d H:i:s', time - (max(1, intval($hours)) * 3600));
 $table = OraBooks_Database::table('platform_metrics');

 return $wpdb->get_results($wpdb->prepare(
 "SELECT metric_value, recorded_at, labels
 FROM {$table}
 WHERE service = %s AND metric_name = %s AND recorded_at >= %s
 ORDER BY recorded_at ASC
 LIMIT %d",
 sanitize_text_field($service),
 sanitize_text_field($metric_name),
 $since,
 max(1, min(2000, intval($limit)))
 ));
 }

 public function cron_collect_metrics() {
 self::collect_platform_metrics;
 }

 public function cron_evaluate_thresholds() {
 self::evaluate_thresholds;
 }

 public function cron_purge_old_metrics() {
 global $wpdb;

 $metric_cutoff = date('Y-m-d H:i:s', time - (self::METRIC_RETENTION_DAYS * 86400));
 $health_cutoff = date('Y-m-d H:i:s', time - (self::HEALTH_RETENTION_DAYS * 86400));

 $metrics_table = OraBooks_Database::table('platform_metrics');
 $health_table = OraBooks_Database::table('health_check_runs');

 $deleted_metrics = $wpdb->query($wpdb->prepare(
 "DELETE FROM {$metrics_table} WHERE recorded_at < %s",
 $metric_cutoff
 ));
 $deleted_health = $wpdb->query($wpdb->prepare(
 "DELETE FROM {$health_table} WHERE recorded_at < %s",
 $health_cutoff
 ));

 orabooks_log_event('observability_purge', 'Old observability metrics purged', 'info', [
 'metrics_deleted' => (int) $deleted_metrics,
 'health_deleted' => (int) $deleted_health,
 ]);
 }

 public function on_eventbus_lag($data) {
 self::record_metric('eventbus', 'queue_depth', (float) ($data['pending_count'] ?? 0), null, $data);
 self::notify_platform_admins('platform_eventbus_lag', [
 'title' => __('Event Bus Queue Lag', 'orabooks'),
 'message' => sprintf(__('Event bus has %d pending messages.', 'orabooks'), (int) ($data['pending_count'] ?? 0)),
 'priority' => 'high',
 'correlation_id' => 'obs_eventbus_lag_'. current_time('YmdHi'),
 'metrics' => $data,
 ]);
 }

 public function on_eventbus_dead_letter($data) {
 self::record_metric('eventbus', 'dead_letter_count', (float) ($data['dead_count'] ?? 0), null, $data);
 self::notify_platform_admins('platform_eventbus_dead_letter', [
 'title' => __('Event Bus Dead Letters', 'orabooks'),
 'message' => sprintf(__('Event bus has %d dead-letter events requiring review.', 'orabooks'), (int) ($data['dead_count'] ?? 0)),
 'priority' => 'critical',
 'correlation_id' => 'obs_eventbus_dl_'. current_time('YmdHi'),
 'metrics' => $data,
 ]);
 }

 public function on_async_queue_lag($data) {
 self::record_metric('async_queue', 'queue_depth', (float) ($data['pending'] ?? 0), null, $data);
 self::notify_platform_admins('platform_async_queue_lag', [
 'title' => __('Async Queue Lag', 'orabooks'),
 'message' => sprintf(__('Async queue has %d pending jobs.', 'orabooks'), (int) ($data['pending'] ?? 0)),
 'priority' => 'high',
 'correlation_id' => 'obs_async_lag_'. current_time('YmdHi'),
 'metrics' => $data,
 ]);
 }

 public function on_async_queue_dead_letter($data) {
 self::record_metric('async_queue', 'dead_letter_count', (float) ($data['dead'] ?? 0), null, $data);
 self::notify_platform_admins('platform_async_queue_dead_letter', [
 'title' => __('Async Queue Dead Letters', 'orabooks'),
 'message' => sprintf(__('Async queue has %d dead-letter jobs.', 'orabooks'), (int) ($data['dead'] ?? 0)),
 'priority' => 'critical',
 'correlation_id' => 'obs_async_dl_'. current_time('YmdHi'),
 'metrics' => $data,
 ]);
 }

 public function ajax_dashboard() {
 if (!current_user_can('manage_options')) {
 orabooks_json_error('Permission denied', 403);
 }

 $hours = intval($_REQUEST['hours'] ?? 24);
 orabooks_json_success(self::get_dashboard($hours));
 }

 public function ajax_metrics() {
 if (!current_user_can('manage_options')) {
 orabooks_json_error('Permission denied', 403);
 }

 $service = sanitize_text_field($_REQUEST['service'] ?? '');
 $metric = sanitize_text_field($_REQUEST['metric_name'] ?? '');
 $hours = intval($_REQUEST['hours'] ?? 24);

 if ($service === '' || $metric === '') {
 orabooks_json_error('service and metric_name are required', 400);
 }

 orabooks_json_success([
 'service' => $service,
 'metric_name' => $metric,
 'series' => self::get_metric_series($service, $metric, $hours),
 ]);
 }

 private static function status_from_counts($primary, $primary_threshold, $secondary, $secondary_threshold) {
 if ($secondary >= $secondary_threshold || $primary >= ($primary_threshold * 2)) {
 return 'critical';
 }
 if ($primary >= $primary_threshold || $secondary > 0) {
 return 'degraded';
 }
 return 'healthy';
 }

 private static function build_alert($event_type, $title, $metrics) {
 return [
 'event_type' => 'platform_'. $event_type,
 'payload' => [
 'title' => __($title, 'orabooks'),
 'message' => wp_json_encode($metrics),
 'priority' => 'high',
 'correlation_id' => 'obs_'. $event_type. '_'. current_time('YmdHi'),
 'metrics' => $metrics,
 ],
 ];
 }

 private static function notify_platform_admins($event_type, $payload) {
 if (!class_exists('OraBooks_Notifications')) {
 return;
 }

 $admin_ids = get_users([
 'role' => 'administrator',
 'fields' => ['ID'],
 ]);

 foreach ($admin_ids as $admin) {
 OraBooks_Notifications::notify((int) $admin->ID, $event_type, $payload, 0);
 }
 }
}
