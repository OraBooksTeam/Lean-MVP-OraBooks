<?php
/**
 * internal API facade.
 */
if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Job_Api {
 public static function enqueue_internal($request) {
 $data = is_array($request) ? $request: (array) $request;
 return OraBooks_AsyncQueue::enqueue($data['job_type'] ?? '', $data['payload'] ?? [], [
 'queue_name' => $data['queue_name'] ?? 'default',
 'priority' => intval($data['priority'] ?? 5),
 'max_retries' => intval($data['max_retries'] ?? 5),
 'delay_seconds' => intval($data['delay_seconds'] ?? 0),
 'idempotency_key' => $data['idempotency_key'] ?? '',
 ]);
 }
}
