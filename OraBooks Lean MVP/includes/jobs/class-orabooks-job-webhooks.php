<?php
/**
 * webhook helpers.
 */
if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Job_Webhooks {
 public static function get_urls($org_id = 0) {
 return OraBooks_AsyncQueue::get_webhook_urls($org_id);
 }

 public static function save_urls($urls, $org_id = 0) {
 return OraBooks_AsyncQueue::save_webhook_urls($urls, $org_id);
 }

 public static function enqueue_dispatch($event, $org_id = 0) {
 return OraBooks_AsyncQueue::enqueue('webhook_dispatch', [
 'event' => $event,
 'org_id' => (int) $org_id,
 ], [
 'queue_name' => 'webhooks',
 'priority' => 5,
 'max_retries' => 5,
 ]);
 }
}
