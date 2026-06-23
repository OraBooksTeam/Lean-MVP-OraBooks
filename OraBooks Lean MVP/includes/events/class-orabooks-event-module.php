<?php
/**
 * Event Bus MVP module.
 *
 * Uses gob_*_tob tables for the requested final-report naming convention while
 * remaining compatible with the existing OraBooks_EventBus facade.
 */

if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Event_Module {
 const MAX_RETRIES = 5;
 const LOCK_TTL_SECONDS = 300;

 private static $consumers = [];
 private static $processing = false;

 public static function init() {
 self::register_default_consumers;

 add_action('orabooks_events_process_outbox', [__CLASS__, 'process_outbox']);
 add_action('shutdown', [__CLASS__, 'shutdown_poll']);
 add_action('wp_loaded', [__CLASS__, 'maybe_poll_pending_events'], 99);

 add_action('wp_ajax_orabooks_eventbus_dead_letters', [__CLASS__, 'ajax_dead_letters']);
 add_action('wp_ajax_orabooks_eventbus_replay', [__CLASS__, 'ajax_replay']);
 add_action('wp_ajax_orabooks_eventbus_replay_all', [__CLASS__, 'ajax_replay_all']);
 add_action('wp_ajax_orabooks_eventbus_discard', [__CLASS__, 'ajax_discard']);
 add_action('wp_ajax_orabooks_eventbus_poll_now', [__CLASS__, 'ajax_poll_now']);

 foreach (self::canonical_event_types as $event_type) {
 add_action('orabooks_'. $event_type, function ($aggregate_id, $payload = []) use ($event_type) {
 self::publish($event_type, $aggregate_id, is_array($payload) ? $payload: []);
 }, 10, 2);
 }
 }

 public static function canonical_event_types() {
 return [
 'journal_posted',
 'sale_delivered',
 'purchase_received',
 'return_approved',
 'reimbursement_submitted',
 ];
 }

 public static function table($name) {
 global $wpdb;
 return $wpdb->prefix. 'gob_'. $name. '_tob';
 }

 public static function get_create_table_sql() {
 global $wpdb;
 $charset_collate = $wpdb->get_charset_collate;

 $outbox = self::table('event_outbox');
 $consumer_log = self::table('event_consumer_log');
 $dead_letter = self::table('event_dead_letter');
 $notifications = self::table('event_notifications');
 $reads = self::table('event_notification_reads');
 $dues = self::table('read_model_dues');

 return [
 "CREATE TABLE IF NOT EXISTS {$outbox} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 event_type VARCHAR(100) NOT NULL,
 aggregate_id BIGINT UNSIGNED NOT NULL,
 payload JSON NOT NULL,
 payload_hash CHAR(64) NOT NULL,
 status ENUM('pending','processing','sent','failed','dead_letter','discarded') DEFAULT 'pending',
 retry_count INT NOT NULL DEFAULT 0,
 max_retries INT NOT NULL DEFAULT 5,
 available_at TIMESTAMP NULL,
 locked_at TIMESTAMP NULL,
 lock_token VARCHAR(64) NULL,
 sent_at TIMESTAMP NULL,
 last_attempt_at TIMESTAMP NULL,
 last_error TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_status_available (status, available_at, created_at),
 INDEX idx_event_type (event_type),
 INDEX idx_aggregate (aggregate_id)
 ) {$charset_collate};",
 "CREATE TABLE IF NOT EXISTS {$consumer_log} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 outbox_id BIGINT UNSIGNED NOT NULL,
 consumer_key VARCHAR(100) NOT NULL,
 event_type VARCHAR(100) NOT NULL,
 aggregate_id BIGINT UNSIGNED NOT NULL,
 processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uk_outbox_consumer (outbox_id, consumer_key),
 INDEX idx_consumer (consumer_key),
 INDEX idx_event_type (event_type)
 ) {$charset_collate};",
 "CREATE TABLE IF NOT EXISTS {$dead_letter} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 outbox_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(100) NOT NULL,
 aggregate_id BIGINT UNSIGNED NOT NULL,
 payload JSON NOT NULL,
 error_message TEXT NULL,
 retry_count INT NOT NULL DEFAULT 0,
 status ENUM('open','replayed','discarded') DEFAULT 'open',
 replayed_by BIGINT UNSIGNED NULL,
 replayed_at TIMESTAMP NULL,
 discarded_by BIGINT UNSIGNED NULL,
 discarded_at TIMESTAMP NULL,
 audit_log JSON DEFAULT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uk_outbox (outbox_id),
 INDEX idx_status_created (status, created_at)
 ) {$charset_collate};",
 "CREATE TABLE IF NOT EXISTS {$notifications} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 outbox_id BIGINT UNSIGNED NULL,
 org_id BIGINT UNSIGNED NULL,
 user_id BIGINT UNSIGNED NULL,
 event_type VARCHAR(100) NOT NULL,
 title VARCHAR(255) NOT NULL,
 body TEXT NULL,
 severity ENUM('info','warning','error') DEFAULT 'info',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_user_created (user_id, created_at),
 INDEX idx_org_created (org_id, created_at)
 ) {$charset_collate};",
 "CREATE TABLE IF NOT EXISTS {$reads} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 notification_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uk_notification_user (notification_id, user_id),
 INDEX idx_user_read (user_id, read_at)
 ) {$charset_collate};",
 "CREATE TABLE IF NOT EXISTS {$dues} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 org_id BIGINT UNSIGNED NOT NULL,
 party_type ENUM('customer','supplier') NOT NULL,
 party_id BIGINT UNSIGNED NOT NULL,
 total_due DECIMAL(20,2) NOT NULL DEFAULT 0,
 source_event_id BIGINT UNSIGNED NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uk_party (org_id, party_type, party_id),
 INDEX idx_org_due (org_id, total_due)
 ) {$charset_collate};",
 ];
 }

 public static function schedule() {
 if (!wp_next_scheduled('orabooks_events_process_outbox')) {
 wp_schedule_event(time(), 'every_minute', 'orabooks_events_process_outbox');
 }
 }

 public static function publish($event_type, $aggregate_id, array $payload = []) {
 global $wpdb;

 $event_type = sanitize_key($event_type);
 if (!in_array($event_type, self::canonical_event_types, true) && !preg_match('/^[a-z0-9_]+$/', $event_type)) {
 return new WP_Error('invalid_event_type', 'Invalid event type.');
 }

 $payload['event_version'] = (int) ($payload['event_version'] ?? 1);
 $payload['event_type'] = $payload['event_type'] ?? $event_type;
 $payload_json = wp_json_encode($payload);
 $validation = self::validate_payload($event_type, $payload);
 if (is_wp_error($validation)) {
 return $validation;
 }

 $wpdb->insert(self::table('event_outbox'), [
 'event_type' => $event_type,
 'aggregate_id' => (int) $aggregate_id,
 'payload' => $payload_json,
 'payload_hash' => hash('sha256', $payload_json),
 'status' => 'pending',
 'max_retries' => self::MAX_RETRIES,
 'available_at' => current_time('mysql', true),
 'created_at' => current_time('mysql', true),
 ], ['%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s']);

 $id = (int) $wpdb->insert_id;
 if ($id > 0) {
 orabooks_log_event('event_outbox_recorded', "Event {$event_type} recorded in outbox", 'info', [
 'outbox_id' => $id,
 'aggregate_id' => (int) $aggregate_id,
 ]);
 }

 return $id ?: false;
 }

 public static function validate_payload($event_type, array $payload) {
 $required = [
 'journal_posted' => ['event_version'],
 'sale_delivered' => ['event_version'],
 'purchase_received' => ['event_version'],
 'return_approved' => ['event_version'],
 'reimbursement_submitted' => ['event_version'],
 'state_transition' => ['event_version', 'org_id', 'record_type', 'event', 'from_state', 'to_state'],
 ];

 foreach (($required[$event_type] ?? ['event_version']) as $field) {
 if (!array_key_exists($field, $payload)) {
 return new WP_Error('invalid_event_payload', "Event payload is missing {$field}.");
 }
 }

 return true;
 }

 public static function register_consumer($event_type, $consumer_key, $handler) {
 if (!isset(self::$consumers[$event_type])) {
 self::$consumers[$event_type] = [];
 }
 self::$consumers[$event_type][$consumer_key] = $handler;
 }

 public static function register_default_consumers() {
 self::register_consumer('journal_posted', 'journal_read_model', [__CLASS__, 'consume_journal_read_model']);

 foreach (self::canonical_event_types as $event_type) {
 self::register_consumer($event_type, 'due_read_model', [__CLASS__, 'consume_due_read_model']);
 self::register_consumer($event_type, 'job_enqueue_bridge', [__CLASS__, 'consume_job_enqueue_bridge']);
 }

 self::register_consumer('return_approved', 'approver_notifications', [__CLASS__, 'consume_approver_notifications']);
 self::register_consumer('reimbursement_submitted', 'approver_notifications', [__CLASS__, 'consume_approver_notifications']);

 self::register_consumer('state_transition', 'workflow_read_model', [__CLASS__, 'consume_state_transition_read_model']);
 self::register_consumer('state_transition', 'workflow_notifications', [__CLASS__, 'consume_state_transition_notifications']);
 self::register_consumer('state_transition', 'job_enqueue_bridge', [__CLASS__, 'consume_job_enqueue_bridge']);
 }

 public static function process_outbox($limit = 25) {
 if (self::$processing) {
 return ['processed' => 0, 'failed' => 0, 'skipped' => 0];
 }

 self::$processing = true;
 global $wpdb;

 self::recover_stale_processing_locks;
 $outbox = self::table('event_outbox');
 $events = $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM {$outbox}
 WHERE status = 'pending'
 AND (available_at IS NULL OR available_at <= UTC_TIMESTAMP)
 ORDER BY created_at ASC
 LIMIT %d",
 (int) $limit
 ));

 $processed = 0;
 $failed = 0;
 $skipped = 0;

 foreach ($events ?: [] as $event) {
 $lock_token = wp_generate_password(32, false, false);
 $wpdb->update($outbox, [
 'status' => 'processing',
 'locked_at' => current_time('mysql', true),
 'lock_token' => $lock_token,
 'last_attempt_at' => current_time('mysql', true),
 ], ['id' => (int) $event->id], ['%s', '%s', '%s', '%s'], ['%d']);

 $payload = json_decode((string) $event->payload, true);
 $payload = is_array($payload) ? $payload: [];
 $consumers = self::$consumers[$event->event_type] ?? [];

 if (empty($consumers)) {
 $wpdb->update($outbox, [
 'status' => 'sent',
 'sent_at' => current_time('mysql', true),
 'lock_token' => null,
 ], ['id' => (int) $event->id], ['%s', '%s', '%s'], ['%d']);
 $processed++;
 continue;
 }

 $event_failed = false;
 foreach ($consumers as $consumer_key => $handler) {
 if (self::consumer_was_processed((int) $event->id, $consumer_key)) {
 continue;
 }

 try {
 $result = call_user_func($handler, $event, $payload);
 if ($result === false || is_wp_error($result)) {
 $message = is_wp_error($result) ? $result->get_error_message(): 'Consumer returned false.';
 throw new RuntimeException($message);
 }
 self::record_consumer_success($event, $consumer_key);
 } catch (Throwable $e) {
 $event_failed = true;
 self::mark_failed($event, $e->getMessage());
 break;
 }
 }

 if ($event_failed) {
 $failed++;
 continue;
 }

 $wpdb->update($outbox, [
 'status' => 'sent',
 'sent_at' => current_time('mysql', true),
 'lock_token' => null,
 'last_error' => null,
 ], ['id' => (int) $event->id], ['%s', '%s', '%s', '%s'], ['%d']);
 $processed++;
 }

 self::$processing = false;
 return ['processed' => $processed, 'failed' => $failed, 'skipped' => $skipped];
 }

 private static function consumer_was_processed($outbox_id, $consumer_key) {
 global $wpdb;
 $table = self::table('event_consumer_log');
 return (int) $wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM {$table} WHERE outbox_id = %d AND consumer_key = %s",
 (int) $outbox_id,
 $consumer_key
 )) > 0;
 }

 private static function record_consumer_success($event, $consumer_key) {
 global $wpdb;
 $wpdb->insert(self::table('event_consumer_log'), [
 'outbox_id' => (int) $event->id,
 'consumer_key' => $consumer_key,
 'event_type' => $event->event_type,
 'aggregate_id' => (int) $event->aggregate_id,
 'processed_at' => current_time('mysql', true),
 ], ['%d', '%s', '%s', '%d', '%s']);
 }

 private static function mark_failed($event, $message) {
 global $wpdb;
 $outbox = self::table('event_outbox');
 $retry_count = (int) $event->retry_count + 1;

 if ($retry_count >= (int) ($event->max_retries ?: self::MAX_RETRIES)) {
 $wpdb->update($outbox, [
 'status' => 'dead_letter',
 'retry_count' => $retry_count,
 'last_error' => $message,
 'lock_token' => null,
 'locked_at' => null,
 ], ['id' => (int) $event->id], ['%s', '%d', '%s', '%s', '%s'], ['%d']);
 self::record_dead_letter($event, $message, $retry_count);
 do_action('orabooks_eventbus_dead_letter_alert', self::get_health);
 return;
 }

 $delay = min(3600, 30 * (2 ** max(0, $retry_count - 1)));
 $wpdb->update($outbox, [
 'status' => 'pending',
 'retry_count' => $retry_count,
 'last_error' => $message,
 'available_at' => gmdate('Y-m-d H:i:s', time() + $delay),
 'lock_token' => null,
 'locked_at' => null,
 ], ['id' => (int) $event->id], ['%s', '%d', '%s', '%s', '%s', '%s'], ['%d']);
 }

 private static function record_dead_letter($event, $message, $retry_count) {
 global $wpdb;
 $wpdb->replace(self::table('event_dead_letter'), [
 'outbox_id' => (int) $event->id,
 'event_type' => $event->event_type,
 'aggregate_id' => (int) $event->aggregate_id,
 'payload' => $event->payload,
 'error_message' => $message,
 'retry_count' => $retry_count,
 'status' => 'open',
 'audit_log' => wp_json_encode([[
 'action' => 'dead_letter',
 'at' => current_time('mysql', true),
 'message' => $message,
 ]]),
 'created_at' => current_time('mysql', true),
 ], ['%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s']);
 }

 public static function recover_stale_processing_locks() {
 global $wpdb;
 $outbox = self::table('event_outbox');
 $cutoff = gmdate('Y-m-d H:i:s', time() - self::LOCK_TTL_SECONDS);
 return $wpdb->query($wpdb->prepare(
 "UPDATE {$outbox}
 SET status = 'pending', lock_token = NULL, locked_at = NULL
 WHERE status = 'processing' AND locked_at < %s",
 $cutoff
 ));
 }

 public static function maybe_poll_pending_events() {
 if (is_admin || wp_doing_ajax || wp_doing_cron) {
 return;
 }
 global $wpdb;
 $outbox = self::table('event_outbox');
 $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$outbox} WHERE status = 'pending' LIMIT 1");
 if ($pending > 0) {
 self::process_outbox(5);
 }
 }

 public static function shutdown_poll() {
 if (self::$processing || wp_doing_ajax || wp_doing_cron) {
 return;
 }
 self::process_outbox(5);
 }

 public static function get_health($org_id = 0) {
 global $wpdb;
 $outbox = self::table('event_outbox');
 $org_filter = self::sql_payload_org_clause($org_id);
 $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$outbox} WHERE status = 'pending'{$org_filter}");
 $processing = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$outbox} WHERE status = 'processing'{$org_filter}");
 $sent = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$outbox} WHERE status = 'sent'{$org_filter}");
 $dead = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$outbox} WHERE status = 'dead_letter'{$org_filter}");
 $status = $dead > 0 ? 'critical': ($pending > 50 || $processing > 10 ? 'degraded': 'healthy');

 return [
 'pending' => $pending,
 'processing' => $processing,
 'sent' => $sent,
 'dead_letter' => $dead,
 'status' => $status,
 'dashboard_url' => admin_url('admin.php?page=orabooks-event-dead-letter'),
 ];
 }

 public static function get_dead_letters($limit = 50, $org_id = 0) {
 global $wpdb;
 $table = self::table('event_dead_letter');
 $org_filter = self::sql_payload_org_clause($org_id);
 return $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM {$table} WHERE status = 'open'{$org_filter} ORDER BY created_at DESC LIMIT %d",
 (int) $limit
 ));
 }

 public static function resolve_event_org_scope() {
 if (current_user_can('manage_options')) {
 return 0;
 }
 if (!function_exists('orabooks_get_current_user_id()')) {
 return 0;
 }
 $user_id = (int) orabooks_get_current_user_id();
 if ($user_id <= 0) {
 return 0;
 }
 if (function_exists('orabooks_resolve_request_org_id')) {
 return (int) orabooks_resolve_request_org_id($user_id, $_REQUEST['org_id'] ?? 0);
 }
 return function_exists('orabooks_get_current_org_id')
 ? (int) orabooks_get_current_org_id($user_id)
: 0;
 }

 public static function get_event_org_id($record) {
 $payload_raw = is_array($record) ? ($record['payload'] ?? ''): ($record->payload ?? '');
 $payload = is_string($payload_raw) ? (json_decode($payload_raw, true) ?: []): (array) $payload_raw;
 return (int) ($payload['org_id'] ?? 0);
 }

 public static function assert_event_org_scope($record, $org_scope) {
 if ($org_scope === null || (int) $org_scope <= 0) {
 return true;
 }
 if (self::get_event_org_id($record) !== (int) $org_scope) {
 return new WP_Error('forbidden', 'Event belongs to another organization');
 }
 return true;
 }

 private static function sql_payload_org_clause($org_id) {
 if ((int) $org_id <= 0) {
 return '';
 }
 global $wpdb;
 return $wpdb->prepare(
 " AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.org_id')) AS UNSIGNED) = %d",
 (int) $org_id
 );
 }

 public static function replay_dead_letter($dead_letter_id, $user_id = 0, $org_scope = null) {
 global $wpdb;
 $dead_table = self::table('event_dead_letter');
 $outbox = self::table('event_outbox');
 $dead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$dead_table} WHERE id = %d", (int) $dead_letter_id));
 if (!$dead || $dead->status !== 'open') {
 return new WP_Error('not_found', 'Dead letter event was not found.');
 }

 $access = self::assert_event_org_scope($dead, $org_scope);
 if (is_wp_error($access)) {
 return $access;
 }

 $wpdb->update($outbox, [
 'status' => 'pending',
 'retry_count' => 0,
 'available_at' => current_time('mysql', true),
 'last_error' => null,
 'locked_at' => null,
 'lock_token' => null,
 ], ['id' => (int) $dead->outbox_id], ['%s', '%d', '%s', '%s', '%s', '%s'], ['%d']);

 $wpdb->update($dead_table, [
 'status' => 'replayed',
 'replayed_by' => (int) $user_id,
 'replayed_at' => current_time('mysql', true),
 ], ['id' => (int) $dead_letter_id], ['%s', '%d', '%s'], ['%d']);

 orabooks_log_event('event_dead_letter_replayed', 'Event dead letter replayed', 'info', [
 'dead_letter_id' => (int) $dead_letter_id,
 'outbox_id' => (int) $dead->outbox_id,
 ], $user_id ?: null, null);

 return true;
 }

 public static function discard_dead_letter($dead_letter_id, $user_id = 0, $org_scope = null) {
 global $wpdb;
 $dead_table = self::table('event_dead_letter');
 $outbox = self::table('event_outbox');
 $dead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$dead_table} WHERE id = %d", (int) $dead_letter_id));
 if (!$dead || $dead->status !== 'open') {
 return new WP_Error('not_found', 'Dead letter event was not found.');
 }

 $access = self::assert_event_org_scope($dead, $org_scope);
 if (is_wp_error($access)) {
 return $access;
 }

 $wpdb->update($outbox, ['status' => 'discarded'], ['id' => (int) $dead->outbox_id], ['%s'], ['%d']);
 $wpdb->update($dead_table, [
 'status' => 'discarded',
 'discarded_by' => (int) $user_id,
 'discarded_at' => current_time('mysql', true),
 ], ['id' => (int) $dead_letter_id], ['%s', '%d', '%s'], ['%d']);

 orabooks_log_event('event_dead_letter_discarded', 'Event dead letter discarded', 'warning', [
 'dead_letter_id' => (int) $dead_letter_id,
 'outbox_id' => (int) $dead->outbox_id,
 ], $user_id ?: null, null);

 return true;
 }

 public static function render_dead_letter_replay_page() {
 if (!self::current_user_can_manage_events) {
 wp_die(__('You do not have permission to view this page.', 'orabooks'));
 }
 $org_scope = self::resolve_event_org_scope;
 $health = self::get_health($org_scope);
 $dead_letters = self::get_dead_letters(50, $org_scope);
 include ORABOOKS_PLUGIN_DIR. 'templates/events/dead-letter-replay.php';
 }

 private static function json_error_for_event_action($result) {
 if (!is_wp_error($result)) {
 return;
 }
 $code = $result->get_error_code() === 'forbidden' ? 403: 404;
 orabooks_json_error($result->get_error_message(), $code);
 }

 public static function ajax_dead_letters() {
 self::require_owner_ajax;
 $org_scope = self::resolve_event_org_scope;
 orabooks_json_success([
 'health' => self::get_health($org_scope),
 'dead_letters' => self::get_dead_letters(50, $org_scope),
 ]);
 }

 public static function ajax_replay() {
 self::require_owner_ajax;
 $org_scope = self::resolve_event_org_scope;
 $scope = $org_scope > 0 ? $org_scope: null;
 $result = self::replay_dead_letter((int) ($_POST['dead_letter_id'] ?? 0), get_current_user_id, $scope);
 if (is_wp_error($result)) {
 self::json_error_for_event_action($result);
 }
 orabooks_json_success(['health' => self::get_health($org_scope)]);
 }

 public static function ajax_replay_all() {
 self::require_owner_ajax;
 $org_scope = self::resolve_event_org_scope;
 $scope = $org_scope > 0 ? $org_scope: null;
 $count = 0;
 foreach (self::get_dead_letters(200, $org_scope) as $dead) {
 $result = self::replay_dead_letter((int) $dead->id, get_current_user_id, $scope);
 if (!is_wp_error($result)) {
 $count++;
 }
 }
 orabooks_json_success(['replayed' => $count, 'health' => self::get_health($org_scope)]);
 }

 public static function ajax_discard() {
 self::require_owner_ajax;
 $org_scope = self::resolve_event_org_scope;
 $scope = $org_scope > 0 ? $org_scope: null;
 $result = self::discard_dead_letter((int) ($_POST['dead_letter_id'] ?? 0), get_current_user_id, $scope);
 if (is_wp_error($result)) {
 self::json_error_for_event_action($result);
 }
 orabooks_json_success(['health' => self::get_health($org_scope)]);
 }

 public static function ajax_poll_now() {
 self::require_owner_ajax;
 $org_scope = self::resolve_event_org_scope;
 orabooks_json_success(['result' => self::process_outbox(50), 'health' => self::get_health($org_scope)]);
 }

 private static function require_owner_ajax() {
 if (!self::current_user_can_manage_events) {
 orabooks_json_error('Permission denied', 403);
 }
 }

 private static function current_user_can_manage_events() {
 if (current_user_can('manage_options')) {
 return true;
 }

 if (!function_exists('orabooks_get_current_user_id()') || !function_exists('orabooks_get_current_org_id')) {
 return false;
 }

 $user_id = (int) orabooks_get_current_user_id();
 if ($user_id <= 0) {
 return false;
 }

 global $wpdb;
 $org_id = (int) orabooks_get_current_org_id($user_id);
 if ($org_id <= 0) {
 return false;
 }

 $table = OraBooks_Database::table('organizations');
 $owner_id = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT owner_id FROM {$table} WHERE id = %d",
 $org_id
 ));

 return $owner_id === $user_id;
 }

 public static function consume_journal_read_model($event, array $payload) {
 if (class_exists('OraBooks_Posting') && method_exists('OraBooks_Posting', 'bump_read_models_for_journal_posted')) {
 $org_id = (int) ($payload['org_id'] ?? 0);
 if ($org_id > 0) {
 OraBooks_Posting::bump_read_models_for_journal_posted($org_id);
 }
 }
 return true;
 }

 public static function consume_due_read_model($event, array $payload) {
 global $wpdb;
 $org_id = (int) ($payload['org_id'] ?? 0);
 $party_id = (int) ($payload['customer_id'] ?? $payload['supplier_id'] ?? $payload['vendor_id'] ?? 0);
 if ($org_id <= 0 || $party_id <= 0) {
 return true;
 }

 $party_type = !empty($payload['supplier_id']) || !empty($payload['vendor_id']) ? 'supplier': 'customer';
 $total_due = (float) ($payload['total_due'] ?? $payload['amount_due'] ?? $payload['amount'] ?? 0);
 $table = self::table('read_model_dues');
 $wpdb->replace($table, [
 'org_id' => $org_id,
 'party_type' => $party_type,
 'party_id' => $party_id,
 'total_due' => $total_due,
 'source_event_id' => (int) $event->id,
 ], ['%d', '%s', '%d', '%f', '%d']);
 return true;
 }

 public static function consume_approver_notifications($event, array $payload) {
 global $wpdb;
 $wpdb->insert(self::table('event_notifications'), [
 'outbox_id' => (int) $event->id,
 'org_id' => (int) ($payload['org_id'] ?? 0),
 'user_id' => (int) ($payload['approver_user_id'] ?? $payload['submitted_by'] ?? 0),
 'event_type' => $event->event_type,
 'title' => self::notification_title($event->event_type),
 'body' => wp_json_encode($payload),
 'severity' => 'info',
 ], ['%d', '%d', '%d', '%s', '%s', '%s', '%s']);
 return true;
 }

 public static function consume_job_enqueue_bridge($event, array $payload) {
 if (function_exists('orabooks_enqueue_job')) {
 orabooks_enqueue_job('event_webhook_dispatch', [
 'event_type' => $event->event_type,
 'outbox_id' => (int) $event->id,
 'aggregate_id' => (int) $event->aggregate_id,
 'org_id' => (int) ($payload['org_id'] ?? 0),
 'payload' => $payload,
 ], [
 'queue_name' => 'webhooks',
 'priority' => 5,
 'idempotency_key' => 'event-webhook-'. (int) $event->id,
 ]);
 }
 return true;
 }

 public static function consume_state_transition_read_model($event, array $payload) {
 global $wpdb;

 $org_id = (int) ($payload['org_id'] ?? 0);
 $record_type = sanitize_key($payload['record_type'] ?? '');
 if ($org_id <= 0 || $record_type === '') {
 return true;
 }

 $table = self::table('read_model_dues');
 $party_type = in_array($record_type, ['bill', 'expense'], true) ? 'supplier': 'customer';
 $party_id = (int) ($payload['record_id'] ?? $event->aggregate_id);

 if ($party_id <= 0) {
 return true;
 }

 $wpdb->replace($table, [
 'org_id' => $org_id,
 'party_type' => $party_type,
 'party_id' => $party_id,
 'total_due' => (float) ($payload['amount_due'] ?? 0),
 'source_event_id' => (int) $event->id,
 ], ['%d', '%s', '%d', '%f', '%d']);

 return true;
 }

 public static function consume_state_transition_notifications($event, array $payload) {
 $org_id = (int) ($payload['org_id'] ?? 0);
 $record_type = sanitize_key($payload['record_type'] ?? '');
 $wf_event = sanitize_key($payload['event'] ?? '');

 if ($org_id <= 0 || $record_type === '' || $wf_event === '') {
 return true;
 }

 if ($record_type === 'journal') {
 return true;
 }

 $notify_map = [
 'bill' => ['submit', 'approve', 'post', 'void'],
 'invoice' => ['send', 'post', 'cancel'],
 'expense' => ['submit', 'ai_review', 'approve', 'post'],
 'commission' => ['pay', 'expire'],
 ];

 if (empty($notify_map[$record_type]) || !in_array($wf_event, $notify_map[$record_type], true)) {
 return true;
 }

 global $wpdb;
 $wpdb->insert(self::table('event_notifications'), [
 'outbox_id' => (int) $event->id,
 'org_id' => $org_id,
 'user_id' => (int) ($payload['triggered_by'] ?? 0),
 'event_type' => 'state_transition',
 'title' => self::workflow_notification_title($record_type, $wf_event),
 'body' => wp_json_encode($payload),
 'severity' => in_array($wf_event, ['void', 'cancel', 'expire', 'reject'], true) ? 'warning': 'info',
 ], ['%d', '%d', '%d', '%s', '%s', '%s', '%s']);

 if (class_exists('OraBooks_Notifications')) {
 self::notify_org_admins_state_transition($org_id, $record_type, $wf_event, $payload);
 }

 return true;
 }

 private static function workflow_notification_title($record_type, $wf_event) {
 return sprintf('%s %s', ucfirst($record_type), str_replace('_', ' ', $wf_event));
 }

 private static function notify_org_admins_state_transition($org_id, $record_type, $wf_event, array $payload) {
 global $wpdb;

 $table = OraBooks_Database::table('user_org');
 $admins = $wpdb->get_results($wpdb->prepare(
 "SELECT user_id FROM {$table} WHERE org_id = %d AND role IN ('owner','admin','approver')",
 (int) $org_id
 ));

 $record_id = (int) ($payload['record_id'] ?? 0);
 $title = self::workflow_notification_title($record_type, $wf_event);

 foreach ($admins ?: [] as $admin) {
 OraBooks_Notifications::notify((int) $admin->user_id, 'state_transition', [
 'title' => $title,
 'message' => sprintf(
 '%s #%d moved to %s via %s',
 $record_type,
 $record_id,
 $payload['to_state'] ?? '',
 $wf_event
 ),
 'record_type' => $record_type,
 'record_id' => $record_id,
 'event' => $wf_event,
 'org_id' => (int) $org_id,
 'priority' => 'normal',
 ], (int) $org_id);
 }
 }

 private static function notification_title($event_type) {
 $titles = [
 'return_approved' => 'Return approved',
 'reimbursement_submitted' => 'Reimbursement submitted',
 ];
 return $titles[$event_type] ?? 'Domain event notification';
 }
}

function orabooks_events_publish($event_type, $aggregate_id, $payload = []) {
 return OraBooks_Event_Module::publish($event_type, $aggregate_id, is_array($payload) ? $payload: []);
}
