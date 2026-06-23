<?php
/**
 * send_notification_email handler wrapper.
 */
if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Job_Handler_Send_Notification_Email {
 public static function handle($job, $payload) {
 return OraBooks_AsyncQueue::handle_send_notification_email($job, $payload);
 }
}
