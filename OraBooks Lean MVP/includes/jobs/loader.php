<?php
/**
 * SL-303 module loader.
 */
if (!defined('ABSPATH')) {
    exit;
}

$orabooks_jobs_files = [
    'schema',
    'queue',
    'worker',
    'registry',
    'monitor',
    'exports',
    'webhooks',
    'handler-send-notification-email',
    'handler-export-report-async',
    'handler-webhook-dispatch',
    'api',
    'maintenance',
    'alerts',
    'test-queue-smoke',
    'test-dead-letter-alert',
];

foreach ($orabooks_jobs_files as $orabooks_jobs_file) {
    require_once __DIR__ . '/class-orabooks-job-' . $orabooks_jobs_file . '.php';
}
