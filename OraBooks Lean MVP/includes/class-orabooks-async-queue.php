<?php
/**
 * OraBooks Async Queue & Job Governance (SL-303)
 *
 * Central async job queue engine that manages background jobs (email send,
 * report generation, webhook calls, bulk operations). Supports:
 * - Job enqueue with priority, delay, max_retries
 * - Worker pool with FOR UPDATE locking
 * - Exponential backoff retry (5s initial, factor 2, max 5 retries)
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

    /** Default retry config */
    const DEFAULT_MAX_RETRIES     = 5;
    const BACKOFF_INITIAL         = 5;  // seconds
    const BACKOFF_FACTOR          = 2;
    const WORKER_LIMIT            = 10; // max jobs per batch

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
            add_action('wp_ajax_orabooks_async_queue_stats', [self::$instance, 'ajax_queue_stats']);
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
    private static function get_handler($job_type) {
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

        $next_retry_at = $delay > 0
            ? date('Y-m-d H:i:s', time() + $delay)
            : current_time('mysql', true);

        $wpdb->insert($table, [
            'queue_name'   => $queue_name,
            'job_type'     => $job_type,
            'payload'      => json_encode($payload),
            'status'       => 'pending',
            'priority'     => $priority,
            'max_retries'  => $max_retries,
            'next_retry_at' => $next_retry_at,
            'created_at'   => current_time('mysql', true),
        ], ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']);

        $job_id = $wpdb->insert_id;

        if ($job_id) {
            orabooks_log_event('job_enqueued', "Job {$job_type} enqueued (job #{$job_id})", 'info', [
                'job_id'     => $job_id,
                'job_type'   => $job_type,
                'queue_name' => $queue_name,
                'priority'   => $priority,
                'delay'      => $delay,
            ]);
        }

        return $job_id;
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
                $wpdb->update(
                    $table,
                    [
                        'status'         => 'processing',
                        'started_at'     => current_time('mysql', true),
                        'heartbeat_at'   => current_time('mysql', true),
                    ],
                    ['id' => $job->id],
                    ['%s', '%s', '%s'],
                    ['%d']
                );

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
                    $wpdb->update(
                        $table,
                        [
                            'status'       => 'completed',
                            'completed_at' => current_time('mysql', true),
                            'retry_count'  => $new_retry,
                        ],
                        ['id' => $job->id],
                        ['%s', '%s', '%d'],
                        ['%d']
                    );

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
                        $wpdb->update(
                            $table,
                            [
                                'status'         => 'dead_letter',
                                'retry_count'    => $new_retry,
                                'last_error'     => $error_msg,
                                'last_attempt_at' => current_time('mysql', true),
                            ],
                            ['id' => $job->id],
                            ['%s', '%d', '%s', '%s'],
                            ['%d']
                        );

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
                        $delay = self::BACKOFF_INITIAL * pow(self::BACKOFF_FACTOR, $new_retry - 1);
                        $next_retry = date('Y-m-d H:i:s', time() + $delay);

                        $wpdb->update(
                            $table,
                            [
                                'status'         => 'pending',
                                'retry_count'    => $new_retry,
                                'last_error'     => $error_msg,
                                'last_attempt_at' => current_time('mysql', true),
                                'next_retry_at'  => $next_retry,
                            ],
                            ['id' => $job->id],
                            ['%s', '%d', '%s', '%s', '%s'],
                            ['%d']
                        );

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

        $wpdb->update(
            $table,
            [
                'status'        => 'pending',
                'retry_count'   => 0,
                'last_error'    => null,
                'next_retry_at' => current_time('mysql', true),
                'started_at'    => null,
                'completed_at'  => null,
            ],
            ['id' => $job->id],
            ['%s', '%d', null, '%s', null, null],
            ['%d']
        );

        orabooks_log_event('job_retry_manual', "Job #{$job->id} ({$job->job_type}) manually retried", 'info', [
            'job_id'   => $job->id,
            'job_type' => $job->job_type,
            'previous_status' => $job->status,
        ], get_current_user_id());

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

    // ================================================================
    // AJAX HANDLERS
    // ================================================================

    /**
     * AJAX: Manual replay of a dead-letter/failed job (admin only).
     */
    public function ajax_replay_job() {
        if (!current_user_can('manage_options')) {
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

    /**
     * AJAX: Get queue stats (admin only).
     */
    public function ajax_queue_stats() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }

        $stats = self::get_queue_stats();
        orabooks_json_success($stats);
    }

    // ================================================================
    // DEFAULT HANDLERS
    // ================================================================

    /**
     * Register default handlers for common job types.
     */
    public static function register_default_handlers() {
        // send_email handler stub
        self::register_handler('send_email', function($job, $payload) {
            $to      = $payload['to'] ?? '';
            $subject = $payload['subject'] ?? '';
            $message = $payload['message'] ?? '';
            $headers = $payload['headers'] ?? [];

            if (empty($to) || empty($subject)) {
                return 'Missing required fields: to, subject';
            }

            $sent = wp_mail($to, $subject, $message, $headers);
            if (!$sent) {
                return 'wp_mail failed';
            }

            orabooks_log_event('email_sent', "Email sent to {$to}: {$subject}", 'info', [
                'to'      => orabooks_mask_email($to),
                'subject' => $subject,
            ]);

            return true;
        });

        // webhook_call handler stub
        self::register_handler('webhook_call', function($job, $payload) {
            $url    = $payload['url'] ?? '';
            $method = strtoupper($payload['method'] ?? 'POST');
            $body   = $payload['body'] ?? [];
            $headers = $payload['headers'] ?? [];

            if (empty($url)) {
                return 'Webhook URL required';
            }

            if (class_exists('OraBooks_Security')) {
                $ssrf = OraBooks_Security::validate_outbound_url($url);
                if (is_wp_error($ssrf)) {
                    return $ssrf->get_error_message();
                }
            }

            $response = wp_remote_request($url, [
                'method'  => $method,
                'body'    => json_encode($body),
                'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                return $response->get_error_message();
            }

            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code < 200 || $status_code >= 300) {
                return "Webhook returned status {$status_code}";
            }

            orabooks_log_event('webhook_sent', "Webhook sent to {$url}", 'info', [
                'url'    => $url,
                'method' => $method,
                'status' => $status_code,
            ]);

            return true;
        });
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
function orabooks_enqueue_job($job_type, $payload = [], $opts = []) {
    if (class_exists('OraBooks_AsyncQueue')) {
        return OraBooks_AsyncQueue::enqueue($job_type, $payload, $opts);
    }
    return false;
}
