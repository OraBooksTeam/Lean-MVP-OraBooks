<?php
/**
 * SL-303 queue facade.
 */
if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Job_Queue {
    public static function enqueue($job_type, $payload = [], $options = []) {
        return OraBooks_AsyncQueue::enqueue($job_type, $payload, $options);
    }

    public static function replay($job_id) {
        return OraBooks_AsyncQueue::retry_job($job_id);
    }

    public static function cancel($job_id) {
        return OraBooks_AsyncQueue::cancel_job($job_id);
    }

    public static function discard($job_id) {
        return OraBooks_AsyncQueue::discard_job($job_id);
    }
}
