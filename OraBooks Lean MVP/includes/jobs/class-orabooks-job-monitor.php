<?php
/**
 * monitor facade.
 */
if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Job_Monitor {
 public static function stats($org_id = null) {
 if ($org_id === null) {
 $org_id = OraBooks_AsyncQueue::resolve_queue_org_scope;
 }
 return OraBooks_AsyncQueue::get_queue_stats($org_id);
 }

 public static function jobs($filters = []) {
 if (!isset($filters['org_id'])) {
 $filters['org_id'] = OraBooks_AsyncQueue::resolve_queue_org_scope;
 }
 return OraBooks_AsyncQueue::list_jobs($filters);
 }
}
