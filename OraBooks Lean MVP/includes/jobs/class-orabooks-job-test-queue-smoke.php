<?php
/**
 * smoke helper for enqueue/worker verification.
 */
if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Job_Test_Queue_Smoke {
 public static function enqueue_noop() {
 OraBooks_AsyncQueue::register_handler('sl303_noop', '__return_true');
 return OraBooks_AsyncQueue::enqueue('sl303_noop', ['source' => 'smoke'], [
 'queue_name' => 'default',
 'priority' => 5,
 'idempotency_key' => 'sl303-noop-smoke',
 ]);
 }
}
