<?php
/**
 * report export helpers.
 */
if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Job_Exports {
 public static function enqueue_report($report_type, $parameters = [], $options = []) {
 return OraBooks_AsyncQueue::enqueue('export_report_async', array_merge($parameters, [
 'report_type' => sanitize_key($report_type),
 ]), array_merge([
 'queue_name' => 'reports',
 'priority' => 4,
 'max_retries' => 5,
 ], $options));
 }
}
