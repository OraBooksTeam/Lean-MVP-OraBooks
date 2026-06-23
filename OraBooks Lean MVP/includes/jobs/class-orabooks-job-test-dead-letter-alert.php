<?php
/**
 * smoke helper for dead-letter alert verification.
 */
if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Job_Test_Dead_Letter_Alert {
 public static function trigger($job_id = 0) {
 do_action('orabooks_async_queue_dead_letter', (int) $job_id, [
 'job_type' => 'sl303_smoke_dead_letter',
 'error' => 'Smoke test dead letter alert',
 ]);
 }
}
