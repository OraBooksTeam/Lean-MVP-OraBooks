<?php
/**
 * schema facade.
 */
if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Job_Schema {
 public static function jobs_table {
 return OraBooks_Database::table(OraBooks_AsyncQueue::TABLE_JOBS);
 }

 public static function audit_table {
 return OraBooks_Database::table(OraBooks_AsyncQueue::TABLE_AUDIT);
 }
}
