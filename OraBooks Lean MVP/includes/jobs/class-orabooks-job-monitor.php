<?php
/**
 * SL-303 monitor facade.
 */
if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Job_Monitor {
    public static function stats() {
        return OraBooks_AsyncQueue::get_queue_stats();
    }

    public static function jobs($filters = []) {
        return OraBooks_AsyncQueue::list_jobs($filters);
    }
}
