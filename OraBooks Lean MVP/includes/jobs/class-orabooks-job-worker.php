<?php
/**
 * worker facade.
 */
if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Job_Worker {
 public static function process() {
 return OraBooks_AsyncQueue::init->process_queue();
 }

 public static function heartbeat_recovery() {
 return OraBooks_AsyncQueue::init->heartbeat_recovery();
 }
}
