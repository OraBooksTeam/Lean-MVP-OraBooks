<?php
/**
 * Unit Tests for OraBooks_EventBus
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_EventBus_Test extends TestCase
{
 protected function setUp: void
 {
 parent::setUp;

 global $wpdb;
 $wpdb->test_get_var_callback = null;
 $wpdb->test_get_row_callback = null;
 $wpdb->test_get_results_callback = null;
 $wpdb->test_query_callback = null;
 $wpdb->test_insert_callback = null;
 $wpdb->test_update_callback = null;
 $wpdb->insert_id = 0;
 $wpdb->last_query = '';
 $wpdb->last_result = [];

 $GLOBALS['orabooks_test_log_events'] = [];

 $ref = new ReflectionProperty(OraBooks_EventBus::class, 'consumers');
 $ref->setAccessible(true);
 $ref->setValue(null, []);

 $moduleRef = new ReflectionProperty(OraBooks_Event_Module::class, 'consumers');
 $moduleRef->setAccessible(true);
 $moduleRef->setValue(null, []);
 }

 #[Test]
 public function test_publish_writes_pending_outbox_event_with_default_version
 {
 global $wpdb;

 $captured = [];
 $wpdb->test_insert_callback = function ($table, $data, $format) use (&$captured) {
 $captured[] = [$table, $data, $format];
 };
 $GLOBALS['orabooks_test_use_insert_id'] = 302;

 $id = OraBooks_EventBus::publish('journal_posted', 55, ['org_id' => 10]);

 $this->assertSame(302, $id);
 $this->assertCount(2, $captured);
 $this->assertStringContainsString('orabooks_outbox_messages', $captured[0][0]);
 $this->assertStringContainsString('gob_event_outbox_tob', $captured[1][0]);
 $this->assertSame('journal_posted', $captured[0][1]['event_type']);
 $this->assertSame(55, $captured[0][1]['aggregate_id']);
 $this->assertSame('pending', $captured[0][1]['status']);
 $this->assertSame(1, json_decode($captured[0][1]['payload'], true)['event_version']);
 $this->assertSame(1, json_decode($captured[1][1]['payload'], true)['event_version']);
 }

 #[Test]
 public function test_process_outbox_delivers_to_each_closure_consumer_once
 {
 global $wpdb;

 $event = (object) [
 'id' => 77,
 'event_type' => 'journal_posted',
 'aggregate_id' => 55,
 'payload' => json_encode(['org_id' => 10, 'event_version' => 1]),
 'status' => 'pending',
 'retry_count' => 0,
 ];
 $calls = [];
 $trackingInserts = [];

 OraBooks_EventBus::register_consumer('journal_posted', function ($event, $payload) use (&$calls) {
 $calls[] = 'projector';
 return $payload['org_id'] === 10 && (int) $event->id === 77;
 });
 OraBooks_EventBus::register_consumer('journal_posted', function use (&$calls) {
 $calls[] = 'notification';
 return true;
 });

 $wpdb->test_get_results_callback = fn($query) => stripos($query, "status = 'pending'") !== false ? [$event]: [];
 $wpdb->test_get_row_callback = fn($query) => stripos($query, 'FOR UPDATE') !== false ? clone $event: null;
 $wpdb->test_get_var_callback = fn($query) => 0;
 $wpdb->test_insert_callback = function ($table, $data) use (&$trackingInserts) {
 if (stripos($table, 'consumer_event_tracking') !== false) {
 $trackingInserts[] = $data;
 }
 };

 $result = (new OraBooks_EventBus)->process_outbox;

 $this->assertSame(['projector', 'notification'], $calls);
 $this->assertSame(1, $result['processed']);
 $this->assertSame(0, $result['failed']);
 $this->assertCount(2, $trackingInserts);
 $this->assertSame('outbox:77', $trackingInserts[0]['event_key']);
 $this->assertNotSame($trackingInserts[0]['consumer'], $trackingInserts[1]['consumer']);
 }

 #[Test]
 public function test_process_outbox_retries_failed_consumer_with_backoff
 {
 global $wpdb;

 $event = (object) [
 'id' => 88,
 'event_type' => 'invoice_approved',
 'aggregate_id' => 44,
 'payload' => json_encode(['event_version' => 1]),
 'status' => 'pending',
 'retry_count' => 1,
 ];
 $updates = [];

 OraBooks_EventBus::register_consumer('invoice_approved', function {
 return false;
 });

 $wpdb->test_get_results_callback = fn($query) => stripos($query, "status = 'pending'") !== false ? [$event]: [];
 $wpdb->test_get_row_callback = fn($query) => stripos($query, 'FOR UPDATE') !== false ? clone $event: null;
 $wpdb->test_get_var_callback = fn($query) => 0;
 $wpdb->test_update_callback = function ($table, $data) use (&$updates) {
 $updates[] = $data;
 return 1;
 };

 $result = (new OraBooks_EventBus)->process_outbox;

 $this->assertSame(1, $result['failed']);
 $this->assertSame('pending', $updates[1]['status']);
 $this->assertSame(2, $updates[1]['retry_count']);
 $this->assertArrayHasKey('next_retry_at', $updates[1]);
 }

 #[Test]
 public function test_process_outbox_moves_to_dead_letter_after_max_retries
 {
 global $wpdb;

 $event = (object) [
 'id' => 99,
 'event_type' => 'payment_recorded',
 'aggregate_id' => 12,
 'payload' => json_encode(['event_version' => 1]),
 'status' => 'pending',
 'retry_count' => 4,
 ];
 $updates = [];

 OraBooks_EventBus::register_consumer('payment_recorded', function {
 throw new RuntimeException('consumer failed');
 });

 $wpdb->test_get_results_callback = fn($query) => stripos($query, "status = 'pending'") !== false ? [$event]: [];
 $wpdb->test_get_row_callback = fn($query) => stripos($query, 'FOR UPDATE') !== false ? clone $event: null;
 $wpdb->test_get_var_callback = fn($query) => 0;
 $wpdb->test_update_callback = function ($table, $data) use (&$updates) {
 $updates[] = $data;
 return 1;
 };

 $result = (new OraBooks_EventBus)->process_outbox;

 $this->assertSame(1, $result['failed']);
 $this->assertSame('dead_letter', $updates[1]['status']);
 $this->assertSame(5, $updates[1]['retry_count']);
 $this->assertSame('consumer failed', $updates[1]['last_error']);
 }

 #[Test]
 public function test_schema_defines_consumer_tracking_idempotency_table
 {
 $sql = implode("\n", OraBooks_EventBus::get_create_table_sql);

 $this->assertStringContainsString('orabooks_consumer_event_tracking', $sql);
 $this->assertStringContainsString('UNIQUE KEY uk_event_consumer', $sql);
 $this->assertStringContainsString('FOREIGN KEY (outbox_id)', $sql);
 }

 #[Test]
 public function test_final_report_tables_use_gob_prefix_and_tob_suffix
 {
 $sql = implode("\n", OraBooks_Event_Module::get_create_table_sql);

 $this->assertStringContainsString('gob_event_outbox_tob', $sql);
 $this->assertStringContainsString('gob_event_consumer_log_tob', $sql);
 $this->assertStringContainsString('gob_event_dead_letter_tob', $sql);
 $this->assertStringContainsString('gob_event_notifications_tob', $sql);
 $this->assertStringContainsString('gob_event_notification_reads_tob', $sql);
 }

 #[Test]
 public function test_final_report_canonical_events_are_registered
 {
 $this->assertSame([
 'journal_posted',
 'sale_delivered',
 'purchase_received',
 'return_approved',
 'reimbursement_submitted',
 ], OraBooks_Event_Module::canonical_event_types);
 }

 #[Test]
 public function test_event_module_publish_validates_and_records_outbox
 {
 global $wpdb;

 $captured = null;
 $wpdb->test_insert_callback = function ($table, $data) use (&$captured) {
 $captured = [$table, $data];
 };
 $GLOBALS['orabooks_test_use_insert_id'] = 702;

 $id = OraBooks_Event_Module::publish('sale_delivered', 44, ['org_id' => 9]);

 $this->assertSame(702, $id);
 $this->assertStringContainsString('gob_event_outbox_tob', $captured[0]);
 $this->assertSame('sale_delivered', $captured[1]['event_type']);
 $this->assertSame('pending', $captured[1]['status']);
 $this->assertNotEmpty($captured[1]['payload_hash']);
 }

 #[Test]
 public function test_event_module_health_returns_dashboard_counts
 {
 global $wpdb;

 $values = [3, 1, 20, 2];
 $wpdb->test_get_var_callback = function use (&$values) {
 return array_shift($values);
 };

 $health = OraBooks_Event_Module::get_health;

 $this->assertSame(3, $health['pending']);
 $this->assertSame(20, $health['sent']);
 $this->assertSame(2, $health['dead_letter']);
 $this->assertSame('critical', $health['status']);
 $this->assertStringContainsString('orabooks-event-dead-letter', $health['dashboard_url']);
 }

 #[Test]
 public function test_event_dead_letters_filter_by_org_id_in_payload: void
 {
 global $wpdb;

 $query = '';
 $wpdb->test_get_results_callback = function ($sql) use (&$query) {
 $query = $sql;
 return [];
 };

 OraBooks_Event_Module::get_dead_letters(25, 7);

 $this->assertStringContainsString("JSON_EXTRACT(payload, '$.org_id')", $query);
 $this->assertStringContainsString('7', $query);
 }

 #[Test]
 public function test_replay_dead_letter_denies_cross_tenant_access: void
 {
 global $wpdb;

 $wpdb->test_get_row_callback = function {
 return (object) [
 'id' => 3,
 'outbox_id' => 88,
 'status' => 'open',
 'payload' => wp_json_encode(['org_id' => 99]),
 ];
 };

 $result = OraBooks_Event_Module::replay_dead_letter(3, 1, 9);

 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('forbidden', $result->get_error_code);
 }
}
