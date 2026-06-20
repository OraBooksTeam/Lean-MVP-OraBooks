<?php
/**
 * OraBooks Async Queue & Job Governance (SL-303)
 *
 * Central async job queue engine that manages background jobs (email send,
 * report generation, webhook calls, bulk operations). Supports:
 * - Job enqueue with priority, delay, max_retries
 * - Worker pool with FOR UPDATE locking
 * - Exponential backoff retry (30s initial, factor 2, max 5 retries)
 * - Dead-letter after max retries
 * - Manual replay/retry API
 * - Heartbeat monitoring for long-running jobs
 * - Queue depth, failure rate, latency monitoring
 * - SL-250 notification integration for alerts
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_AsyncQueue {

    const TABLE_JOBS = 'async_jobs';
    const TABLE_AUDIT = 'async_job_audit_log';

    /** Default retry config */
    const DEFAULT_MAX_RETRIES     = 5;
    const BACKOFF_INITIAL         = 30;  // seconds
    const BACKOFF_FACTOR          = 2;
    const BACKOFF_MAX             = 3600; // seconds
    const WORKER_LIMIT            = 10; // max jobs per batch
    const STALE_LOCK_SECONDS      = 300;
    const ARCHIVE_AFTER_DAYS      = 30;

    private static $instance = null;

    /** Registered job handlers: job_type => callable */
    private static $handlers = [];

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();

            // Cron: process pending jobs (every minute)
            add_action('orabooks_async_queue_process', [self::$instance, 'process_queue']);

            // Cron: heartbeat stale job recovery (every 5 minutes)
            add_action('orabooks_async_queue_heartbeat', [self::$instance, 'heartbeat_recovery']);

            // Cron: monitoring (hourly)
            add_action('orabooks_async_queue_monitor', [self::$instance, 'monitor_health']);

            // AJAX: manual replay (admin)
            add_action('wp_ajax_orabooks_async_queue_replay', [self::$instance, 'ajax_replay_job']);
            add_action('wp_ajax_nopriv_orabooks_async_queue_replay', [self::$instance, 'ajax_replay_job']);
            add_action('wp_ajax_orabooks_async_queue_discard', [self::$instance, 'ajax_discard_job']);
            add_action('wp_ajax_nopriv_orabooks_async_queue_discard', [self::$instance, 'ajax_discard_job']);
            add_action('wp_ajax_orabooks_async_queue_cancel', [self::$instance, 'ajax_cancel_job']);
            add_action('wp_ajax_nopriv_orabooks_async_queue_cancel', [self::$instance, 'ajax_cancel_job']);
            add_action('wp_ajax_orabooks_async_queue_poll_now', [self::$instance, 'ajax_poll_now']);
            add_action('wp_ajax_nopriv_orabooks_async_queue_poll_now', [self::$instance, 'ajax_poll_now']);
            add_action('wp_ajax_orabooks_async_queue_stats', [self::$instance, 'ajax_queue_stats']);
            add_action('wp_ajax_nopriv_orabooks_async_queue_stats', [self::$instance, 'ajax_queue_stats']);
            add_action('wp_ajax_orabooks_webhook_settings_get', [self::$instance, 'ajax_webhook_settings_get']);
            add_action('wp_ajax_nopriv_orabooks_webhook_settings_get', [self::$instance, 'ajax_webhook_settings_get']);
            add_action('wp_ajax_orabooks_webhook_settings_save', [self::$instance, 'ajax_webhook_settings_save']);
            add_action('wp_ajax_nopriv_orabooks_webhook_settings_save', [self::$instance, 'ajax_webhook_settings_save']);
            add_action('wp_ajax_orabooks_report_async_export', [self::$instance, 'ajax_report_async_export']);
            add_action('wp_ajax_nopriv_orabooks_report_async_export', [self::$instance, 'ajax_report_async_export']);
            add_action('orabooks_async_queue_archive', [self::$instance, 'archive_completed_jobs']);
            add_action('orabooks_async_queue_dead_letter', [self::$instance, 'send_dead_letter_alert'], 10, 2);
        }
        return self::$instance;
    }

    // ================================================================
    // JOB HANDLER REGISTRY
    // ================================================================

    /**
     * Register a handler for a job type.
     *
     * @param string   $job_type Job type (e.g. 'send_email', 'generate_report').
     * @param callable $handler  Callback: function($job, $payload) { }. Return true on success.
     */
    public static function register_handler($job_type, $handler) {
        self::$handlers[$job_type] = $handler;
    }

    /**
     * Get handler for a job type.
     */
    public static function get_handler($job_type) {
        return self::$handlers[$job_type] ?? null;
    }

    // ================================================================
    // ENQUEUE
    // ================================================================

    /**
     * Enqueue a new background job.
     *
     * @param string $job_type      Job type (e.g. 'send_email', 'generate_report').
     * @param array  $payload       Job payload data.
     * @param array  $opts          {
     *     Optional. Job options.
     *     @type string $queue_name    Queue name. Default 'default'.
     *     @type int    $priority      Priority 0-10 (lower = higher). Default 5.
     *     @type int    $max_retries   Max retry attempts. Default 5.
     *     @type int    $delay_seconds Delay before first execution. Default 0.
     * }
     * @return int|false Job ID on success, false on failure.
     */
    public static function enqueue($job_type, $payload = [], $opts = []) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_JOBS);

        $queue_name   = !empty($opts['queue_name']) ? $opts['queue_name'] : 'default';
        $priority     = isset($opts['priority']) ? max(0, min(10, (int)$opts['priority'])) : 5;
        $max_retries  = isset($opts['max_retries']) ? (int)$opts['max_retries'] : self::DEFAULT_MAX_RETRIES;
        $delay        = isset($opts['delay_seconds']) ? (int)$opts['delay_seconds'] : 0;
        $idempotency_key = !empty($opts['idempotency_key']) ? sanitize_text_field($opts['idempotency_key']) : '';

        if (empty($payload['org_id']) && function_exists('orabooks_get_current_org_id')) {
            $scoped_org_id = (int) orabooks_get_current_org_id();
            if ($scoped_org_id > 0) {
                $payload['org_id'] = $scoped_org_id;
            }
        }

        $next_retry_at = $delay > 0
            ? date('Y-m-d H:i:s', time() + $delay)
            : current_time('mysql', true);

        if ($idempotency_key !== '') {
            $existing = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table}
                 WHERE idempotency_key = %s AND status IN ('pending','processing','completed')
                 ORDER BY id DESC LIMIT 1",
                $idempotency_key
            ));
            if ($existing > 0) {
                self::audit($existing, $job_type, 'deduped', 'pending', [
                    'idempotency_key' => $idempotency_key,
                    'queue_name' => $queue_name,
                ]);
                return $existing;
            }
        }

        $wpdb->insert($table, [
            'queue_name'   => $queue_name,
            'job_type'     => $job_type,
            'payload'      => json_encode($payload),
            'status'       => 'pending',
            'priority'     => $priority,
            'max_retries'  => $max_retries,
            'next_retry_at' => $next_retry_at,
            'idempotency_key' => $idempotency_key ?: null,
            'created_at'   => current_time('mysql', true),
        ], ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s']);

        $job_id = $wpdb->insert_id;

        if ($job_id) {
            orabooks_log_event('job_enqueued', "Job {$job_type} enqueued (job #{$job_id})", 'info', [
                'job_id'     => $job_id,
                'job_type'   => $job_type,
                'queue_name' => $queue_name,
                'priority'   => $priority,
                'delay'      => $delay,
                'idempotency_key' => $idempotency_key,
            ]);
            self::audit($job_id, $job_type, 'enqueued', 'pending', [
                'queue_name' => $queue_name,
                'priority' => $priority,
                'delay' => $delay,
                'idempotency_key' => $idempotency_key,
            ]);
        }

        return $job_id;
    }

    public static function audit($job_id, $job_type, $transition, $to_status, $metadata = [], $from_status = null) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_AUDIT);
        $wpdb->insert($table, [
            'job_id' => (int) $job_id,
            'job_type' => (string) $job_type,
            'transition' => (string) $transition,
            'from_status' => $from_status,
            'to_status' => (string) $to_status,
            'metadata' => !empty($metadata) ? wp_json_encode($metadata) : null,
            'created_by' => function_exists('get_current_user_id') ? (int) get_current_user_id() : null,
            'created_at' => current_time('mysql', true),
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s']);

        if (function_exists('orabooks_log_event')) {
            orabooks_log_event('async_job_' . $transition, "Async job #{$job_id} {$transition}", 'info', [
                'job_id' => (int) $job_id,
                'job_type' => $job_type,
                'to_status' => $to_status,
                'from_status' => $from_status,
            ] + (array) $metadata);
        }
    }

    private static function transition_job($job, $to_status, $data = [], $transition = '') {
        global $wpdb;
        $table = OraBooks_Database::table(self::TABLE_JOBS);
        $from_status = is_object($job) ? (string) $job->status : '';
        $job_id = is_object($job) ? (int) $job->id : (int) $job;
        $job_type = is_object($job) ? (string) $job->job_type : '';

        $data = array_merge(['status' => $to_status], $data);
        $formats = [];
        foreach ($data as $value) {
            $formats[] = is_int($value) ? '%d' : (is_float($value) ? '%f' : '%s');
        }

        $updated = $wpdb->update($table, $data, ['id' => $job_id], $formats, ['%d']);
        self::audit($job_id, $job_type, $transition ?: $to_status, $to_status, $data, $from_status ?: null);
        return $updated;
    }

    // ================================================================
    // WORKER — PROCESS QUEUE
    // ================================================================

    /**
     * Process pending jobs. Runs every minute via cron.
     * Picks highest priority jobs first, locks them with FOR UPDATE.
     */
    public function process_queue() {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_JOBS);

        // Pick pending jobs ordered by priority (lowest number = highest priority)
        // then by created_at (FIFO for same priority), respecting backoff
        $jobs = $wpdb->get_results(
            "SELECT * FROM {$table}
             WHERE status = 'pending'
               AND (next_retry_at IS NULL OR next_retry_at <= NOW())
             ORDER BY priority ASC, created_at ASC
             LIMIT " . self::WORKER_LIMIT
        );

        if (empty($jobs)) {
            return ['processed' => 0, 'failed' => 0, 'completed' => 0];
        }

        $processed = 0;
        $completed = 0;
        $failed    = 0;

        foreach ($jobs as $job) {
            $wpdb->query("START TRANSACTION");

            try {
                // Pessimistic lock: prevent duplicate processing
                $locked = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table} WHERE id = %d FOR UPDATE",
                    $job->id
                ));

                if (!$locked || $locked->status !== 'pending') {
                    $wpdb->query("ROLLBACK");
                    continue;
                }

                // Mark as processing
                self::transition_job($locked, 'processing', [
                    'started_at'     => current_time('mysql', true),
                    'heartbeat_at'   => current_time('mysql', true),
                ], 'claimed');

                $wpdb->query("COMMIT");

                // --- Process the job (outside TX) ---
                $payload = json_decode($job->payload, true) ?: [];
                $handler = self::get_handler($job->job_type);

                $job_success = false;
                $error_msg   = null;

                if ($handler) {
                    try {
                        $result = call_user_func($handler, $job, $payload);
                        $job_success = ($result !== false);
                        if (!$job_success) {
                            $error_msg = is_string($result) ? $result : 'Handler returned false';
                        }
                    } catch (\Exception $e) {
                        $job_success = false;
                        $error_msg   = $e->getMessage();
                    }
                } else {
                    // No handler registered — mark as failed with clear message
                    $job_success = false;
                    $error_msg   = "No handler registered for job type: {$job->job_type}";
                }

                // --- Update job status based on result ---
                $new_retry = (int)$job->retry_count + 1;

                if ($job_success) {
                    self::transition_job($job, 'completed', [
                        'completed_at' => current_time('mysql', true),
                        'last_error'   => null,
                    ], 'completed');

                    orabooks_log_event('job_completed', "Job #{$job->id} ({$job->job_type}) completed", 'info', [
                        'job_id'   => $job->id,
                        'job_type' => $job->job_type,
                    ]);

                    // Publish job_completed event via EventBus (SL-302)
                    if (function_exists('orabooks_publish_event')) {
                        orabooks_publish_event('job_completed', $job->id, [
                            'job_id'     => $job->id,
                            'job_type'   => $job->job_type,
                            'queue_name' => $job->queue_name,
                            'payload'    => $payload,
                        ]);
                    }

                    $completed++;

                } else {
                    // Job failed
                    if ($new_retry >= (int)$job->max_retries) {
                        // Max retries exceeded — dead letter
                        self::transition_job($job, 'dead_letter', [
                            'retry_count'    => $new_retry,
                            'last_error'     => $error_msg,
                            'last_attempt_at' => current_time('mysql', true),
                        ], 'dead_letter');

                        orabooks_log_event('job_dead_letter', "Job #{$job->id} ({$job->job_type}) dead-lettered after {$new_retry} retries", 'warning', [
                            'job_id'   => $job->id,
                            'job_type' => $job->job_type,
                            'error'    => $error_msg,
                        ]);

                        // Fire hook for SL-250 alert
                        do_action('orabooks_async_queue_dead_letter', $job->id, [
                            'job_id'     => $job->id,
                            'job_type'   => $job->job_type,
                            'error'      => $error_msg,
                        ]);

                    } else {
                        // Schedule retry with exponential backoff
                        $delay = min(self::BACKOFF_MAX, self::BACKOFF_INITIAL * pow(self::BACKOFF_FACTOR, $new_retry - 1));
                        $next_retry = date('Y-m-d H:i:s', time() + $delay);

                        self::transition_job($job, 'pending', [
                            'retry_count'    => $new_retry,
                            'last_error'     => $error_msg,
                            'last_attempt_at' => current_time('mysql', true),
                            'next_retry_at'  => $next_retry,
                        ], 'retry_scheduled');

                        orabooks_log_event('job_retry', "Job #{$job->id} ({$job->job_type}) retry {$new_retry}/{$job->max_retries} scheduled", 'info', [
                            'job_id'    => $job->id,
                            'job_type'  => $job->job_type,
                            'retry'     => $new_retry,
                            'max'       => $job->max_retries,
                            'delay'     => $delay,
                            'error'     => $error_msg,
                        ]);
                    }

                    $failed++;
                }

                $processed++;

            } catch (\Exception $e) {
                $wpdb->query("ROLLBACK");
                $failed++;
                orabooks_log_event('async_queue_error', "Async queue processing error: " . $e->getMessage(), 'warning', [
                    'job_id' => $job->id,
                ]);
            }
        }

        // Log batch summary
        orabooks_log_event('async_queue_batch', "Async queue batch: {$processed} processed, {$completed} completed, {$failed} failed", 'info', [
            'processed' => $processed,
            'completed' => $completed,
            'failed'    => $failed,
        ]);

        return [
            'processed' => $processed,
            'completed' => $completed,
            'failed'    => $failed,
        ];
    }

    // ================================================================
    // HEARTBEAT RECOVERY (Every 5 minutes)
    // ================================================================

    /**
     * Recover jobs stuck in 'processing' state (worker crashed).
     * If heartbeat_at is older than 5 minutes, reset to pending.
     */
    public function heartbeat_recovery() {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_JOBS);
        $cutoff = date('Y-m-d H:i:s', time() - 300); // 5 minutes

        $recovered = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET status = 'pending',
                 last_error = 'Recovered from stalled processing (no heartbeat)',
                 retry_count = retry_count + 1,
                 next_retry_at = NOW()
             WHERE status = 'processing'
               AND (heartbeat_at IS NULL OR heartbeat_at < %s)
               AND retry_count < max_retries",
            $cutoff
        ));

        $dead = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET status = 'dead_letter',
                 last_error = 'Stalled processing — max retries exceeded'
             WHERE status = 'processing'
               AND (heartbeat_at IS NULL OR heartbeat_at < %s)
               AND retry_count >= max_retries",
            $cutoff
        ));

        if ($recovered > 0 || $dead > 0) {
            orabooks_log_event('async_queue_heartbeat', "Heartbeat recovery: {$recovered} recovered, {$dead} dead-lettered", 'info', [
                'recovered' => $recovered,
                'dead'      => $dead,
            ]);
        }

        return ['recovered' => $recovered, 'dead' => $dead];
    }

    // ================================================================
    // MONITORING (Hourly)
    // ================================================================

    /**
     * Monitor queue health and alert via SL-250 if thresholds exceeded.
     */
    public function monitor_health() {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_JOBS);

        $pending_count  = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'");
        $processing_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'processing'");
        $dead_count     = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'dead_letter'");
        $failed_count   = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'failed'");

        $total_active = $pending_count + $processing_count;

        // Alert: high queue depth
        if ($pending_count > 200) {
            orabooks_log_event('async_queue_high_lag', "Async queue lag: {$pending_count} pending jobs", 'warning', [
                'pending'  => $pending_count,
                'dead'     => $dead_count,
                'failed'   => $failed_count,
            ]);

            do_action('orabooks_async_queue_lag_alert', [
                'pending'  => $pending_count,
                'dead'     => $dead_count,
            ]);
        }

        // Alert: too many dead-letter
        if ($dead_count > 20) {
            orabooks_log_event('async_queue_dead_letter_alert', "Async queue has {$dead_count} dead-letter jobs", 'warning', [
                'dead' => $dead_count,
            ]);

            do_action('orabooks_async_queue_dead_letter_alert', [
                'dead' => $dead_count,
            ]);
        }
    }

    // ================================================================
    // MANUAL REPLAY / RETRY API
    // ================================================================

    /**
     * Retry a dead-letter or failed job — resets to pending.
     */
    public static function retry_job($job_id) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_JOBS);
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $job_id
        ));

        if (!$job) {
            return new \WP_Error('not_found', 'Job not found');
        }

        if (!in_array($job->status, ['dead_letter', 'failed', 'completed'])) {
            return new \WP_Error('invalid_status', 'Job can only be retried from dead_letter, failed, or completed status');
        }

        self::transition_job($job, 'pending', [
            'retry_count'   => 0,
            'last_error'    => null,
            'next_retry_at' => current_time('mysql', true),
            'started_at'    => null,
            'completed_at'  => null,
            'cancelled_at'  => null,
        ], 'manual_replay');

        orabooks_log_event('job_retry_manual', "Job #{$job->id} ({$job->job_type}) manually retried", 'info', [
            'job_id'   => $job->id,
            'job_type' => $job->job_type,
            'previous_status' => $job->status,
        ], get_current_user_id());

        return true;
    }

    public static function discard_job($job_id) {
        global $wpdb;
        $table = OraBooks_Database::table(self::TABLE_JOBS);
        $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $job_id));
        if (!$job) {
            return new \WP_Error('not_found', 'Job not found');
        }
        if (!in_array($job->status, ['dead_letter', 'failed'], true)) {
            return new \WP_Error('invalid_status', 'Only failed or dead-letter jobs can be discarded');
        }
        self::transition_job($job, 'discarded', ['cancelled_at' => current_time('mysql', true)], 'discarded');
        return true;
    }

    public static function cancel_job($job_id) {
        global $wpdb;
        $table = OraBooks_Database::table(self::TABLE_JOBS);
        $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $job_id));
        if (!$job) {
            return new \WP_Error('not_found', 'Job not found');
        }
        if ($job->status !== 'pending') {
            return new \WP_Error('invalid_status', 'Only pending jobs can be cancelled');
        }
        self::transition_job($job, 'cancelled', [
            'cancelled_at' => current_time('mysql', true),
            'last_error' => 'Cancelled by user',
        ], 'cancelled');
        return true;
    }

    /**
     * Heartbeat update for long-running jobs.
     * Called periodically by the worker to mark the job as alive.
     */
    public static function heartbeat($job_id) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_JOBS);
        return $wpdb->update(
            $table,
            ['heartbeat_at' => current_time('mysql', true)],
            ['id' => $job_id, 'status' => 'processing'],
            ['%s'],
            ['%d', '%s']
        );
    }

    // ================================================================
    // QUEUE STATS
    // ================================================================

    public static function get_queue_stats() {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_JOBS);

        $stats = [];

        // Status counts
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$table} GROUP BY status"
        );
        foreach ($status_counts as $row) {
            $stats[$row->status . '_count'] = (int)$row->count;
        }

        // Total
        $stats['total'] = array_sum([
            $stats['pending_count'] ?? 0,
            $stats['processing_count'] ?? 0,
            $stats['completed_count'] ?? 0,
            $stats['failed_count'] ?? 0,
            $stats['dead_letter_count'] ?? 0,
        ]);

        // Queue depth by priority
        $stats['by_priority'] = $wpdb->get_results(
            "SELECT priority, COUNT(*) as count 
             FROM {$table} WHERE status = 'pending' 
             GROUP BY priority ORDER BY priority ASC"
        );

        // Queue depth by queue_name
        $stats['by_queue'] = $wpdb->get_results(
            "SELECT queue_name, COUNT(*) as count, 
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing
             FROM {$table} 
             GROUP BY queue_name"
        );

        // Recent failures (last 24h)
        $stats['recent_failures'] = $wpdb->get_results(
            "SELECT id, job_type, retry_count, last_error, created_at, last_attempt_at 
             FROM {$table} 
             WHERE status IN ('dead_letter', 'failed') 
               AND (last_attempt_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) OR created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR))
             ORDER BY last_attempt_at DESC 
             LIMIT 20"
        );

        // Avg latency for completed jobs (last 24h)
        $avg_latency = $wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) 
             FROM {$table} 
             WHERE status = 'completed' AND completed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $stats['avg_latency_seconds'] = $avg_latency ? round((float)$avg_latency, 1) : 0;

        // Failure rate (last 24h)
        $last_24h_total = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $last_24h_failed = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
               AND status IN ('dead_letter', 'failed')"
        );
        $stats['failure_rate_24h'] = $last_24h_total > 0
            ? round(($last_24h_failed / $last_24h_total) * 100, 1)
            : 0;

        return $stats;
    }

    public static function list_jobs($args = []) {
        global $wpdb;
        $table = OraBooks_Database::table(self::TABLE_JOBS);
        $where = '1=1';
        $params = [];

        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $params[] = sanitize_text_field($args['status']);
        }
        if (!empty($args['job_type'])) {
            $where .= ' AND job_type = %s';
            $params[] = sanitize_text_field($args['job_type']);
        }
        if (!empty($args['queue_name'])) {
            $where .= ' AND queue_name = %s';
            $params[] = sanitize_text_field($args['queue_name']);
        }

        $limit = min(100, max(1, (int) ($args['limit'] ?? 50)));
        $sql = "SELECT id, queue_name, job_type, payload, status, priority, retry_count, max_retries,
                       next_retry_at, created_at, started_at, completed_at, last_error, heartbeat_at,
                       idempotency_key, cancelled_at
                FROM {$table}
                WHERE {$where}
                ORDER BY FIELD(status, 'processing','pending','dead_letter','failed','completed','cancelled','discarded'),
                         priority ASC, created_at DESC
                LIMIT {$limit}";
        return !empty($params) ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);
    }

    public static function archive_completed_jobs() {
        global $wpdb;
        $table = OraBooks_Database::table(self::TABLE_JOBS);
        $cutoff = date('Y-m-d H:i:s', time() - self::ARCHIVE_AFTER_DAYS * DAY_IN_SECONDS);
        $jobs = $wpdb->get_results($wpdb->prepare(
            "SELECT id, job_type, payload FROM {$table}
             WHERE status = 'completed' AND completed_at < %s AND archived_at IS NULL
             LIMIT 200",
            $cutoff
        ));

        foreach ($jobs ?: [] as $job) {
            $payload = json_decode((string) $job->payload, true) ?: [];
            if (!empty($payload['file_path']) && file_exists($payload['file_path'])) {
                @unlink($payload['file_path']);
            }
            $wpdb->update($table, ['archived_at' => current_time('mysql', true)], ['id' => (int) $job->id], ['%s'], ['%d']);
            self::audit((int) $job->id, $job->job_type, 'archived', 'completed', ['cutoff' => $cutoff], 'completed');
        }

        return ['archived' => count($jobs ?: [])];
    }

    public static function get_webhook_urls($org_id = 0) {
        $key = $org_id ? 'orabooks_webhook_urls_' . (int) $org_id : 'orabooks_webhook_urls';
        $raw = get_option($key, '');
        if (is_array($raw)) {
            return array_values(array_filter(array_map('trim', $raw)));
        }
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $raw))));
    }

    public static function save_webhook_urls($urls, $org_id = 0) {
        $clean = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $urls) as $url) {
            $url = trim($url);
            if ($url !== '') {
                $clean[] = esc_url_raw($url);
            }
        }
        $key = $org_id ? 'orabooks_webhook_urls_' . (int) $org_id : 'orabooks_webhook_urls';
        update_option($key, implode("\n", $clean));
        return $clean;
    }

    private static function current_user_can_manage_queue() {
        if (current_user_can('manage_options')) {
            return true;
        }
        if (!function_exists('orabooks_get_current_user_id') || !function_exists('orabooks_get_current_org_id')) {
            return false;
        }
        $user_id = (int) orabooks_get_current_user_id();
        $org_id = (int) orabooks_get_current_org_id($user_id);
        if (!$user_id || !$org_id) {
            return false;
        }
        return orabooks_has_permission($user_id, $org_id, 'manage_settings')
            || orabooks_has_permission($user_id, $org_id, 'manage_employees');
    }

    private static function current_user_can_manage_webhooks() {
        if (current_user_can('manage_options')) {
            return true;
        }
        if (!function_exists('orabooks_get_current_user_id')) {
            return false;
        }
        $user_id = (int) orabooks_get_current_user_id();
        if (!$user_id) {
            return false;
        }
        $org_id = function_exists('orabooks_resolve_request_org_id')
            ? (int) orabooks_resolve_request_org_id($user_id, $_REQUEST['org_id'] ?? 0)
            : (int) orabooks_get_current_org_id($user_id);
        if (!$org_id) {
            return false;
        }
        return orabooks_has_permission($user_id, $org_id, 'manage_settings');
    }

    private static function resolve_webhook_org_id() {
        $user_id = function_exists('orabooks_get_current_user_id') ? (int) orabooks_get_current_user_id() : 0;
        if (function_exists('orabooks_resolve_request_org_id')) {
            return (int) orabooks_resolve_request_org_id($user_id, $_REQUEST['org_id'] ?? 0);
        }
        return function_exists('orabooks_get_current_org_id') ? (int) orabooks_get_current_org_id($user_id) : 0;
    }

    // ================================================================
    // AJAX HANDLERS
    // ================================================================

    /**
     * AJAX: Manual replay of a dead-letter/failed job (admin only).
     */
    public function ajax_replay_job() {
        if (!self::current_user_can_manage_queue()) {
            orabooks_json_error('Permission denied', 403);
        }

        $job_id = intval($_POST['job_id'] ?? 0);
        if (!$job_id) {
            orabooks_json_error('Job ID required', 400);
        }

        $result = self::retry_job($job_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success([], 'Job retried successfully');
    }

    public function ajax_discard_job() {
        if (!self::current_user_can_manage_queue()) {
            orabooks_json_error('Permission denied', 403);
        }
        $job_id = intval($_POST['job_id'] ?? 0);
        if (!$job_id) {
            orabooks_json_error('Job ID required', 400);
        }
        $result = self::discard_job($job_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success([], 'Job discarded successfully');
    }

    public function ajax_cancel_job() {
        if (!self::current_user_can_manage_queue()) {
            orabooks_json_error('Permission denied', 403);
        }
        $job_id = intval($_POST['job_id'] ?? 0);
        if (!$job_id) {
            orabooks_json_error('Job ID required', 400);
        }
        $result = self::cancel_job($job_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success([], 'Job cancelled successfully');
    }

    public function ajax_poll_now() {
        if (!self::current_user_can_manage_queue()) {
            orabooks_json_error('Permission denied', 403);
        }
        $result = $this->process_queue();
        orabooks_json_success($result, 'Queue worker ran successfully');
    }

    /**
     * AJAX: Get queue stats (admin only).
     */
    public function ajax_queue_stats() {
        if (!self::current_user_can_manage_queue()) {
            orabooks_json_error('Permission denied', 403);
        }

        $stats = self::get_queue_stats();
        $stats['jobs'] = self::list_jobs([
            'status' => sanitize_text_field($_REQUEST['status'] ?? ''),
            'job_type' => sanitize_text_field($_REQUEST['job_type'] ?? ''),
            'queue_name' => sanitize_text_field($_REQUEST['queue_name'] ?? ''),
            'limit' => intval($_REQUEST['limit'] ?? 50),
        ]);
        orabooks_json_success($stats);
    }

    public function ajax_webhook_settings_get() {
        if (!self::current_user_can_manage_webhooks()) {
            orabooks_json_error('Permission denied', 403);
        }
        $org_id = self::resolve_webhook_org_id();
        orabooks_json_success([
            'urls' => implode("\n", self::get_webhook_urls($org_id)),
            'localhost_warning' => 'Localhost URLs are useful for tests only; hosted webhooks cannot call your local machine/port.',
        ]);
    }

    public function ajax_webhook_settings_save() {
        if (!self::current_user_can_manage_webhooks()) {
            orabooks_json_error('Permission denied', 403);
        }
        $org_id = self::resolve_webhook_org_id();
        $urls = self::save_webhook_urls(wp_unslash($_POST['urls'] ?? ''), $org_id);
        orabooks_json_success(['urls' => implode("\n", $urls)], 'Webhook settings saved');
    }

    public function ajax_report_async_export() {
        if (!self::current_user_can_manage_queue()) {
            orabooks_json_error('Permission denied', 403);
        }
        $report_type = sanitize_key($_POST['report_type'] ?? '');
        $allowed = ['journal', 'trial_balance', 'ledger', 'income_statement', 'balance_sheet'];
        if (!in_array($report_type, $allowed, true)) {
            orabooks_json_error('Unsupported report type', 400);
        }
        $org_id = function_exists('orabooks_get_current_org_id') ? (int) orabooks_get_current_org_id(get_current_user_id()) : 0;
        $payload = [
            'report_type' => $report_type,
            'org_id' => $org_id,
            'user_id' => get_current_user_id(),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? $_POST['start_date'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? $_POST['end_date'] ?? ''),
            'account_id' => intval($_POST['account_id'] ?? 0),
        ];
        $job_id = self::enqueue('export_report_async', $payload, [
            'queue_name' => 'reports',
            'priority' => 4,
            'max_retries' => self::DEFAULT_MAX_RETRIES,
            'idempotency_key' => hash('sha256', 'export:' . $report_type . ':' . wp_json_encode($payload)),
        ]);
        if (!$job_id) {
            orabooks_json_error('Failed to queue export', 500);
        }
        orabooks_json_success(['job_id' => $job_id], 'Background CSV export queued');
    }

    // ================================================================
    // DEFAULT HANDLERS
    // ================================================================

    /**
     * Register default handlers for common job types.
     */
    public static function register_default_handlers() {
        self::register_handler('send_notification_email', [__CLASS__, 'handle_send_notification_email']);
        self::register_handler('send_email', [__CLASS__, 'handle_send_notification_email']);
        self::register_handler('export_report_async', [__CLASS__, 'handle_export_report_async']);
        self::register_handler('webhook_dispatch', [__CLASS__, 'handle_webhook_dispatch']);
        self::register_handler('event_webhook_dispatch', [__CLASS__, 'handle_webhook_dispatch']);
        self::register_handler('webhook_call', [__CLASS__, 'handle_webhook_dispatch']);

        if (class_exists('OraBooks_Exports') && method_exists('OraBooks_Exports', 'generate_export_job')) {
            self::register_handler('generate_export', ['OraBooks_Exports', 'generate_export_job']);
        }

        self::register_handler('partner_activity_check', function($job, $payload) {
            if (!class_exists('OraBooks_Partner')) {
                return 'OraBooks_Partner not available';
            }

            OraBooks_Partner::process_partner_activity();

            orabooks_log_event('partner_activity_job_completed', 'Partner activity check completed', 'info', [
                'job_id' => $job->id ?? null,
                'source' => $payload['source'] ?? 'async_queue',
            ], null, null);

            return true;
        });
    }

    public static function handle_send_notification_email($job, $payload) {
            $to      = $payload['to'] ?? '';
            $subject = $payload['subject'] ?? '';
            $message = $payload['message'] ?? '';
            $headers = $payload['headers'] ?? [];

            if (empty($to) || empty($subject)) {
                return 'Missing required fields: to, subject';
            }

            $sent = wp_mail($to, $subject, $message, $headers);
            if (!$sent) {
                $host = wp_parse_url(home_url(), PHP_URL_HOST);
                if (in_array($host, ['localhost', '127.0.0.1'], true)) {
                    orabooks_log_event('email_soft_completed', "Email soft-completed on localhost: {$subject}", 'info', [
                        'to' => is_array($to) ? array_map('orabooks_mask_email', $to) : orabooks_mask_email($to),
                        'job_id' => $job->id ?? null,
                    ]);
                    return true;
                }
                return 'wp_mail failed';
            }

            $masked_to = is_array($to) ? array_map('orabooks_mask_email', $to) : orabooks_mask_email($to);
            orabooks_log_event('email_sent', 'Email sent: ' . $subject, 'info', [
                'to'      => $masked_to,
                'subject' => $subject,
            ]);

            return true;
    }

    public static function handle_webhook_dispatch($job, $payload) {
            $urls   = [];
            if (!empty($payload['url'])) {
                $urls[] = $payload['url'];
            }
            if (!empty($payload['urls']) && is_array($payload['urls'])) {
                $urls = array_merge($urls, $payload['urls']);
            }
            if (empty($urls)) {
                $org_id = intval($payload['org_id'] ?? 0);
                $urls = self::get_webhook_urls($org_id);
            }
            $method = strtoupper($payload['method'] ?? 'POST');
            $body   = $payload['body'] ?? $payload['event'] ?? $payload;
            $headers = $payload['headers'] ?? [];

            if (empty($urls)) {
                return 'Webhook URL required';
            }

            $errors = [];
            foreach ($urls as $url) {
                $url = esc_url_raw($url);
            if (class_exists('OraBooks_Security')) {
                $ssrf = OraBooks_Security::validate_outbound_url($url);
                if (is_wp_error($ssrf)) {
                    $errors[] = $ssrf->get_error_message();
                    continue;
                }
            }

            $response = wp_remote_request($url, [
                'method'  => $method,
                'body'    => json_encode($body),
                'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                $errors[] = $response->get_error_message();
                continue;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code < 200 || $status_code >= 300) {
                $errors[] = "Webhook {$url} returned status {$status_code}";
                continue;
            }

            orabooks_log_event('webhook_sent', "Webhook sent to {$url}", 'info', [
                'url'    => $url,
                'method' => $method,
                'status' => $status_code,
            ]);
            }

            if (!empty($errors)) {
                return implode('; ', $errors);
            }

            return true;
    }

    public static function handle_export_report_async($job, $payload) {
        $report_type = sanitize_key($payload['report_type'] ?? $payload['export_type'] ?? '');
        $allowed = ['journal', 'trial_balance', 'ledger', 'income_statement', 'balance_sheet'];
        if (!in_array($report_type, $allowed, true)) {
            return 'Unsupported report_type';
        }
        $org_id = intval($payload['org_id'] ?? 0);
        if (!$org_id && function_exists('orabooks_get_current_org_id')) {
            $org_id = (int) orabooks_get_current_org_id(get_current_user_id());
        }
        if (!$org_id) {
            return 'Missing org_id';
        }

        $rows = self::build_report_rows($report_type, $payload + ['org_id' => $org_id]);
        $upload_dir = wp_upload_dir();
        $base_dir = trailingslashit($upload_dir['basedir']) . 'orabooks-exports/' . $org_id;
        $base_url = trailingslashit($upload_dir['baseurl']) . 'orabooks-exports/' . $org_id;
        if (!file_exists($base_dir)) {
            wp_mkdir_p($base_dir);
        }
        $filename = sanitize_file_name($report_type . '_' . date('Ymd_His') . '_job_' . (int) $job->id . '.csv');
        $file_path = trailingslashit($base_dir) . $filename;
        $fh = fopen($file_path, 'w');
        if (!$fh) {
            return 'Unable to create CSV export file';
        }
        if (!empty($rows)) {
            fputcsv($fh, array_keys((array) $rows[0]));
            foreach ($rows as $row) {
                fputcsv($fh, array_values((array) $row));
            }
        } else {
            fputcsv($fh, ['message']);
            fputcsv($fh, ['No rows found for selected filters']);
        }
        fclose($fh);

        global $wpdb;
        $table = OraBooks_Database::table(self::TABLE_JOBS);
        $payload['file_path'] = $file_path;
        $payload['file_url'] = trailingslashit($base_url) . $filename;
        $payload['completed_export_at'] = current_time('mysql', true);
        $wpdb->update($table, ['payload' => wp_json_encode($payload)], ['id' => (int) $job->id], ['%s'], ['%d']);

        return true;
    }

    private static function build_report_rows($report_type, $payload) {
        global $wpdb;
        $org_id = intval($payload['org_id'] ?? 0);
        $date_from = sanitize_text_field($payload['date_from'] ?? $payload['start_date'] ?? '');
        $date_to = sanitize_text_field($payload['date_to'] ?? $payload['end_date'] ?? '');
        $journal = OraBooks_Database::table('journals');
        $lines = OraBooks_Database::table('journal_lines');
        $accounts = OraBooks_Database::table('accounts');

        $date_clause = '';
        $params = [$org_id];
        if ($date_from !== '') {
            $date_clause .= ' AND je.transaction_date >= %s';
            $params[] = $date_from;
        }
        if ($date_to !== '') {
            $date_clause .= ' AND je.transaction_date <= %s';
            $params[] = $date_to;
        }

        if ($report_type === 'journal') {
            $sql = "SELECT je.transaction_date, je.journal_number, je.source_type, coa.code AS account_code, coa.name AS account_name,
                           jl.debit_amount AS debit, jl.credit_amount AS credit, jl.description
                    FROM {$journal} je
                    JOIN {$lines} jl ON jl.journal_id = je.id
                    LEFT JOIN {$accounts} coa ON coa.id = jl.account_id
                    WHERE je.org_id = %d {$date_clause}
                    ORDER BY je.transaction_date ASC, je.id ASC";
            return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
        }

        $account_filter = '';
        if ($report_type === 'ledger' && !empty($payload['account_id'])) {
            $account_filter = ' AND jl.account_id = %d';
            $params[] = intval($payload['account_id']);
        }
        $sql = "SELECT coa.code AS account_code, coa.name AS account_name, coa.type AS account_type,
                       SUM(jl.debit_amount) AS debit, SUM(jl.credit_amount) AS credit,
                       SUM(jl.debit_amount - jl.credit_amount) AS net_balance
                FROM {$journal} je
                JOIN {$lines} jl ON jl.journal_id = je.id
                LEFT JOIN {$accounts} coa ON coa.id = jl.account_id
                WHERE je.org_id = %d {$date_clause} {$account_filter}
                GROUP BY jl.account_id, coa.code, coa.name, coa.type
                ORDER BY coa.code ASC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
        if ($report_type === 'income_statement') {
            return array_values(array_filter($rows, function($row) {
                return in_array(strtolower((string) ($row['account_type'] ?? '')), ['income', 'revenue', 'expense', 'expenses'], true);
            }));
        }
        if ($report_type === 'balance_sheet') {
            return array_values(array_filter($rows, function($row) {
                return in_array(strtolower((string) ($row['account_type'] ?? '')), ['asset', 'assets', 'liability', 'liabilities', 'equity'], true);
            }));
        }
        return $rows;
    }

    public function send_dead_letter_alert($job_id, $data = []) {
        $alert_key = 'orabooks_job_dead_letter_alerted_' . (int) $job_id;
        if (get_option($alert_key)) {
            return;
        }
        update_option($alert_key, current_time('mysql', true), false);

        $payload = [
            'title' => 'Async job reached dead letter',
            'message' => sprintf('Job #%d (%s) reached dead letter: %s', (int) $job_id, $data['job_type'] ?? 'unknown', $data['error'] ?? 'unknown error'),
            'priority' => 'high',
            'job_id' => (int) $job_id,
            'event_type' => 'job_dead_letter',
        ];

        if (class_exists('OraBooks_Notifications')) {
            foreach (self::get_alert_user_ids() as $user_id) {
                OraBooks_Notifications::send_notification((int) $user_id, 'job_dead_letter', $payload, intval($data['org_id'] ?? 0));
            }
        }

        $emails = [];
        foreach (self::get_alert_user_ids() as $user_id) {
            $user = get_userdata((int) $user_id);
            if ($user && !empty($user->user_email)) {
                $emails[] = $user->user_email;
            }
        }
        if (!empty($emails)) {
            self::enqueue('send_notification_email', [
                'to' => array_unique($emails),
                'subject' => '[OraBooks] Async job dead letter',
                'message' => $payload['message'],
            ], [
                'queue_name' => 'default',
                'priority' => 1,
                'max_retries' => 2,
                'idempotency_key' => 'dead-letter-email-' . (int) $job_id,
            ]);
        }
    }

    private static function get_alert_user_ids() {
        $users = get_users(['role__in' => ['administrator', 'owner', 'admin'], 'fields' => 'ID']);
        return array_map('intval', $users ?: []);
    }

    // ================================================================
    // GLOBAL HELPER
    // ================================================================

    /**
     * Enqueue a job using the shorthand helper.
     */
    public static function enqueue_job($job_type, $payload = [], $opts = []) {
        return self::enqueue($job_type, $payload, $opts);
    }
}

/**
 * Shorthand helper for enqueuing async jobs from anywhere.
 */
if (!function_exists('orabooks_enqueue_job')) {
    function orabooks_enqueue_job($job_type, $payload = [], $opts = []) {
        if (class_exists('OraBooks_AsyncQueue')) {
            return OraBooks_AsyncQueue::enqueue($job_type, $payload, $opts);
        }
        return false;
    }
}
