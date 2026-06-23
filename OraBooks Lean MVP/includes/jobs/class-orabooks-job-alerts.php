<?php
/**
 * alert facade.
 */
if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Job_Alerts {
 public static function dead_letter($job_id, $data = []) {
 OraBooks_AsyncQueue::init->send_dead_letter_alert($job_id, $data);
 }
}
