<?php
/**
 * SL-303 handler registry facade.
 */
if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Job_Registry {
    public static function register($job_type, $handler) {
        OraBooks_AsyncQueue::register_handler($job_type, $handler);
    }

    public static function resolve($job_type) {
        return OraBooks_AsyncQueue::get_handler($job_type);
    }
}
