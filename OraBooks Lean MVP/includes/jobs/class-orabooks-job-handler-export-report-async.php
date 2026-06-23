<?php
/**
 * export_report_async handler wrapper.
 */
if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Job_Handler_Export_Report_Async {
 public static function handle($job, $payload) {
 return OraBooks_AsyncQueue::handle_export_report_async($job, $payload);
 }
}
