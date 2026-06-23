<?php
/**
 * maintenance facade.
 */
if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Job_Maintenance {
 public static function archive_completed_jobs() {
 return OraBooks_AsyncQueue::archive_completed_jobs();
 }
}
