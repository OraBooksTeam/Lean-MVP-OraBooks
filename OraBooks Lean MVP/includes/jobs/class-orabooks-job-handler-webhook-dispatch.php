<?php
/**
 * SL-303 webhook_dispatch handler wrapper.
 */
if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Job_Handler_Webhook_Dispatch {
    public static function handle($job, $payload) {
        return OraBooks_AsyncQueue::handle_webhook_dispatch($job, $payload);
    }
}
