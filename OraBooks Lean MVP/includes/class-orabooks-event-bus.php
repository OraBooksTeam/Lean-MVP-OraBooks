<?php
/**
 * OraBooks Event Bus & Domain Events
 *
 * Central event bus with transactional outbox pattern for decoupled
 * event publishing. Supports idempotent consumers, retry/dead-letter,
 * consumer registry, and monitoring alerts.
 *
 * Key flows:
 * Publisher → INSERT into outbox_messages (same TX as business logic)
 * Publisher Worker (cron) → polls pending → delivers to consumers
 * Consumer → idempotent handler → acks or retries
 */

if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_EventBus {

 const OUTBOX_TABLE = 'outbox_messages';
 const TRACKING_TABLE = 'consumer_event_tracking';
 const MAX_RETRIES = 5;
 const BACKOFF_INITIAL = 5; // seconds
 const BACKOFF_FACTOR = 2;

 private static $instance = null;

 /** Registered consumers: event_type => [handler,...] */
 private static $consumers = [];

 public static function init() {
 if (self::$instance === null) {
 self::$instance = new self;

 // Cron: publisher worker runs every minute to process outbox
 add_action('orabooks_eventbus_process_outbox', [self::$instance, 'process_outbox']);

 // Cron: retry dead-letter events (hourly)
 add_action('orabooks_eventbus_retry_deadletter', [self::$instance, 'retry_dead_letter']);

 // Cron: monitoring heartbeat (hourly)
 add_action('orabooks_eventbus_monitor', [self::$instance, 'monitor_health']);
 }
 return self::$instance;
 }

 // ================================================================
 // CONSUMER REGISTRY
 // ================================================================

 /**
 * Register a consumer for an event type.
 *
 * @param string $event_type Event type to subscribe to.
 * @param callable $handler Callback: function($event, $payload) { }. Must return true on success.
 */
 public static function register_consumer($event_type, $handler) {
 if (!isset(self::$consumers[$event_type])) {
 self::$consumers[$event_type] = [];
 }
 self::$consumers[$event_type][] = $handler;
 }

 /**
 * Register all known consumers for OraBooks domain events.
 * Called during plugin init.
 */
 public static function register_consumers() {
 // journal_posted → updates read models, triggers notifications
 self::register_consumer('journal_posted', function($event, $payload) {
 orabooks_log_event('eventbus_consumer_journal', 'Journal posted event consumed', 'info', [
 'journal_id' => $event->aggregate_id,
 ]);
 return true;
 });

 // partner_attribution_verified → commission engine creates escrow
 self::register_consumer('partner_attribution_verified', function($event, $payload) {
 if (class_exists('OraBooks_Commission') && !empty($payload['attribution_id'])) {
 return !is_wp_error(OraBooks_Commission::on_attribution_verified(
 $payload['attribution_id'],
 $payload
 ));
 }
 return true;
 });

 // payout_batch_created → notification
 self::register_consumer('payout_batch_created', function($event, $payload) {
 if (!empty($payload['partner_user_id'])) {
 do_action('orabooks_payout_batch_created', $event->aggregate_id, $payload);
 }
 return true;
 });

 // payout_settled → notification
 self::register_consumer('payout_settled', function($event, $payload) {
 if (!empty($payload['partner_user_id'])) {
 do_action('orabooks_payout_settled', $event->aggregate_id, $payload);
 }
 return true;
 });

 // export_ready / export_failed → notification
 self::register_consumer('export_ready', function($event, $payload) {
 do_action('orabooks_export_ready', $event->aggregate_id, $payload);
 return true;
 });

 self::register_consumer('export_failed', function($event, $payload) {
 do_action('orabooks_export_failed', $event->aggregate_id, $payload);
 return true;
 });

 // inventory_low_stock_alert → notification
 self::register_consumer('inventory_low_stock_alert', function($event, $payload) {
 do_action('orabooks_inventory_low_stock_alert', $event->aggregate_id, $payload);
 return true;
 });

 // projection_integrity_failed → notification
 self::register_consumer('projection_integrity_failed', function($event, $payload) {
 $org_id = intval($payload['org_id'] ?? $event->aggregate_id);
 do_action('orabooks_projection_integrity_failed', $org_id, $payload);
 return true;
 });

 // csv_import_completed / csv_import_failed → notification
 self::register_consumer('csv_import_completed', function($event, $payload) {
 do_action('orabooks_csv_import_completed', $event->aggregate_id, $payload);
 return true;
 });

 self::register_consumer('csv_import_failed', function($event, $payload) {
 do_action('orabooks_csv_import_failed', $event->aggregate_id, $payload);
 return true;
 });

 self::register_consumer('csv_row_escalated', function($event, $payload) {
 do_action('orabooks_csv_row_escalated', $event->aggregate_id, $payload);
 return true;
 });

 if (class_exists('OraBooks_Classification')) {
 OraBooks_Classification::register_event_consumer;
 }

 if (class_exists('OraBooks_Expenses')) {
 OraBooks_Expenses::register_event_consumer;
 }
 }

 /**
 * Get all registered consumers for an event type.
 */
 public static function get_consumers($event_type) {
 return self::$consumers[$event_type] ?? [];
 }

 /**
 * Build a stable-enough consumer identity for idempotency tracking.
 */
 private static function consumer_name($event_type, $handler, $index) {
 if (is_array($handler)) {
 $class = is_object($handler[0]) ? get_class($handler[0]): (string) $handler[0];
 return $class. '::'. $handler[1];
 }

 if (is_string($handler)) {
 return $handler;
 }

 return $event_type. ':closure:'. (int) $index;
 }

 // ================================================================
 // PUBLISH API
 // ================================================================

 /**
 * Publish an event to the transactional outbox.
 * This is the primary method for all domain events.
 *
 * @param string $event_type Event type (e.g. 'journal_posted', 'payout_batch_created').
 * @param int $aggregate_id The ID of the entity this event relates to.
 * @param array $payload Event payload data.
 * @return int|false The outbox message ID, or false on failure.
 */
 public static function publish($event_type, $aggregate_id, $payload = []) {
 global $wpdb;

 $table = OraBooks_Database::table(self::OUTBOX_TABLE);
 $payload = is_array($payload) ? $payload: [];
 if (!isset($payload['event_version'])) {
 $payload['event_version'] = 1;
 }

 $wpdb->insert($table, [
 'event_type' => $event_type,
 'aggregate_id' => $aggregate_id,
 'payload' => json_encode($payload),
 'status' => 'pending',
 'created_at' => current_time('mysql', true),
 ], ['%s', '%d', '%s', '%s', '%s']);

 $id = $wpdb->insert_id;

 if ($id) {
 orabooks_log_event('event_published', "Event {$event_type} published (outbox #{$id})", 'info', [
 'event_type' => $event_type,
 'aggregate_id' => $aggregate_id,
 'outbox_id' => $id,
 ]);

 if (class_exists('OraBooks_Event_Module')) {
 OraBooks_Event_Module::publish($event_type, $aggregate_id, $payload);
 }
 }

 return $id;
 }

 // ================================================================
 // PUBLISHER WORKER (Cron)
 // ================================================================

 /**
 * Process pending outbox events.
 * Runs every minute via WordPress cron.
 */
 public function process_outbox() {
 global $wpdb;

 $table = OraBooks_Database::table(self::OUTBOX_TABLE);
 $track_table = OraBooks_Database::table(self::TRACKING_TABLE);

 // Get pending events that are due for retry
 $events = $wpdb->get_results(
 "SELECT * FROM {$table}
 WHERE status = 'pending'
 AND (next_retry_at IS NULL OR next_retry_at <= NOW)
 ORDER BY created_at ASC
 LIMIT 50"
 );

 $processed = 0;
 $failed = 0;

 foreach ($events as $event) {
 $wpdb->query("START TRANSACTION");

 try {
 // Lock the row for processing
 $locked = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM {$table} WHERE id = %d FOR UPDATE",
 $event->id
 ));

 if (!$locked || $locked->status !== 'pending') {
 $wpdb->query("ROLLBACK");
 continue;
 }

 // Mark as processing
 $wpdb->update(
 $table,
 [
 'status' => 'sent',
 'sent_at' => current_time('mysql', true),
 ],
 ['id' => $event->id],
 ['%s', '%s'],
 ['%d']
 );

 $wpdb->query("COMMIT");

 // Now deliver to registered consumers (outside TX)
 $payload = json_decode($event->payload, true) ?: [];
 $consumers = self::get_consumers($event->event_type);

 if (empty($consumers)) {
 // No consumers registered — still mark as sent so it's not re-polled
 $processed++;
 continue;
 }

 foreach ($consumers as $consumer_index => $handler) {
 $consumer_name = self::consumer_name($event->event_type, $handler, $consumer_index);

 // Idempotency check
 $event_key = 'outbox:'. (int) $event->id;
 $already_processed = $wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM {$track_table} WHERE event_key = %s AND consumer = %s",
 $event_key, $consumer_name
 ));

 if ($already_processed) {
 continue; // Already consumed this event
 }

 try {
 $result = call_user_func($handler, $event, $payload);

 if ($result !== false) {
 // Record successful consumption
 $wpdb->insert($track_table, [
 'event_key' => $event_key,
 'consumer' => $consumer_name,
 'event_type' => $event->event_type,
 'aggregate_id' => $event->aggregate_id,
 'outbox_id' => $event->id,
 'processed_at' => current_time('mysql', true),
 ], ['%s', '%s', '%s', '%d', '%d', '%s']);

 orabooks_log_event('event_consumed', "Event {$event->event_type} consumed by {$consumer_name}", 'info', [
 'outbox_id' => $event->id,
 'consumer' => $consumer_name,
 ]);
 } else {
 throw new \Exception("Consumer returned false");
 }
 } catch (\Exception $e) {
 // Consumer failed — increment retry on the outbox record
 $new_retry = (int)$event->retry_count + 1;
 if ($new_retry >= self::MAX_RETRIES) {
 $wpdb->update(
 $table,
 [
 'status' => 'dead_letter',
 'retry_count' => $new_retry,
 'last_error' => $e->getMessage,
 'last_attempt_at' => current_time('mysql', true),
 ],
 ['id' => $event->id],
 ['%s', '%d', '%s', '%s'],
 ['%d']
 );

 orabooks_log_event('event_dead_letter', "Event {$event->event_type} moved to dead_letter after {$new_retry} retries", 'warning', [
 'outbox_id' => $event->id,
 'consumer' => $consumer_name,
 'error' => $e->getMessage,
 ]);
 } else {
 $delay = self::BACKOFF_INITIAL * pow(self::BACKOFF_FACTOR, $new_retry - 1);
 $wpdb->update(
 $table,
 [
 'status' => 'pending',
 'retry_count' => $new_retry,
 'last_error' => $e->getMessage,
 'last_attempt_at'=> current_time('mysql', true),
 'next_retry_at' => date('Y-m-d H:i:s', time + $delay),
 ],
 ['id' => $event->id],
 ['%s', '%d', '%s', '%s', '%s'],
 ['%d']
 );
 }

 $failed++;
 }
 }

 $processed++;

 } catch (\Exception $e) {
 $wpdb->query("ROLLBACK");
 $failed++;
 orabooks_log_event('event_bus_error', "Outbox processing error: ". $e->getMessage, 'warning', [
 'outbox_id' => $event->id,
 ]);
 }
 }

 // Log batch stats
 if ($processed > 0 || $failed > 0) {
 orabooks_log_event('eventbus_batch', "EventBus outbox batch: {$processed} processed, {$failed} failed", 'info', [
 'processed' => $processed,
 'failed' => $failed,
 ]);
 }

 return ['processed' => $processed, 'failed' => $failed];
 }

 // ================================================================
 // DEAD-LETTER RETRY (Hourly Cron)
 // ================================================================

 /**
 * Retry dead-letter events — resets status to pending.
 */
 public function retry_dead_letter() {
 global $wpdb;

 $table = OraBooks_Database::table(self::OUTBOX_TABLE);

 // Reset dead-letter events older than 1 hour back to pending
 $cutoff = date('Y-m-d H:i:s', time - 3600);
 $updated = $wpdb->query($wpdb->prepare(
 "UPDATE {$table}
 SET status = 'pending', retry_count = 0, last_error = NULL, next_retry_at = NOW
 WHERE status = 'dead_letter' AND last_attempt_at < %s",
 $cutoff
 ));

 if ($updated > 0) {
 orabooks_log_event('eventbus_deadletter_retry', "{$updated} dead-letter events re-queued for retry", 'info', [
 'count' => $updated,
 ]);
 }
 }

 // ================================================================
 // MONITORING (Hourly Cron)
 // ================================================================

 /**
 * Monitor event bus health and alert via if needed.
 */
 public function monitor_health() {
 global $wpdb;

 $table = OraBooks_Database::table(self::OUTBOX_TABLE);

 // Queue depth
 $pending_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'");
 $dead_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'dead_letter'");

 // Alert thresholds
 if ($pending_count > 100) {
 orabooks_log_event('eventbus_high_lag', "EventBus queue lag: {$pending_count} pending events", 'warning', [
 'pending_count' => $pending_count,
 'dead_count' => $dead_count,
 ]);

 // Fire hook for notification
 do_action('orabooks_eventbus_lag_alert', [
 'pending_count' => $pending_count,
 'dead_count' => $dead_count,
 ]);
 }

 if ($dead_count > 10) {
 orabooks_log_event('eventbus_dead_letter_alert', "EventBus has {$dead_count} dead-letter events", 'warning', [
 'pending_count' => $pending_count,
 'dead_count' => $dead_count,
 ]);

 do_action('orabooks_eventbus_dead_letter_alert', [
 'pending_count' => $pending_count,
 'dead_count' => $dead_count,
 ]);
 }
 }

 // ================================================================
 // CREATE TABLE SQL
 // ================================================================

 /**
 * Get CREATE TABLE SQL for the consumer_event_tracking table.
 * (outbox_messages table is already created by )
 */
 public static function get_create_table_sql() {
 global $wpdb;
 $charset_collate = $wpdb->get_charset_collate();
 $tables = [];

 // Consumer event tracking for idempotency
 $table = OraBooks_Database::table(self::TRACKING_TABLE);
 $table_outbox = OraBooks_Database::table(self::OUTBOX_TABLE);
 $tables[] = "CREATE TABLE IF NOT EXISTS {$table} (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 event_key VARCHAR(128) NOT NULL,
 consumer VARCHAR(128) NOT NULL,
 event_type VARCHAR(100) NOT NULL,
 aggregate_id BIGINT UNSIGNED NOT NULL,
 outbox_id BIGINT UNSIGNED NOT NULL,
 processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (outbox_id) REFERENCES {$table_outbox}(id) ON DELETE CASCADE,
 UNIQUE KEY uk_event_consumer (event_key(100), consumer(80)),
 INDEX idx_event_type (event_type),
 INDEX idx_consumer (consumer(64))
 ) {$charset_collate};";

 return $tables;
 }
}

// ================================================================
// GLOBAL HELPER
// ================================================================

/**
 * Convenience function for publishing events.
 */
if (!function_exists('orabooks_publish_event')) {
 function orabooks_publish_event($event_type, $aggregate_id, $payload = []) {
 if (class_exists('OraBooks_EventBus')) {
 return OraBooks_EventBus::publish($event_type, $aggregate_id, $payload);
 }
 return false;
 }
}
