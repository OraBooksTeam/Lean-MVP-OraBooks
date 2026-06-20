<?php
/**
 * SL-303 Job Monitor template.
 */
if (!defined('ABSPATH')) {
    exit;
}

$stats = class_exists('OraBooks_AsyncQueue') ? OraBooks_AsyncQueue::get_queue_stats() : [];
$jobs = class_exists('OraBooks_AsyncQueue') ? OraBooks_AsyncQueue::list_jobs(['limit' => 50]) : [];
?>
<div class="wrap orabooks-job-monitor">
    <h1>Async Job Monitor</h1>
    <p>Monitor pending, processing, completed, and dead-letter background jobs.</p>
    <p>
        <button class="button button-primary" data-orabooks-queue-poll>Poll Now</button>
    </p>
    <ul>
        <li>Pending: <?php echo esc_html($stats['pending_count'] ?? 0); ?></li>
        <li>Processing: <?php echo esc_html($stats['processing_count'] ?? 0); ?></li>
        <li>Completed: <?php echo esc_html($stats['completed_count'] ?? 0); ?></li>
        <li>Dead Letter: <?php echo esc_html($stats['dead_letter_count'] ?? 0); ?></li>
    </ul>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Queue</th>
                <th>Type</th>
                <th>Status</th>
                <th>Retries</th>
                <th>Next Retry</th>
                <th>Download</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($jobs as $job) : $payload = json_decode((string) $job->payload, true) ?: []; ?>
                <tr>
                    <td>#<?php echo esc_html($job->id); ?></td>
                    <td><?php echo esc_html($job->queue_name); ?></td>
                    <td><?php echo esc_html($job->job_type); ?></td>
                    <td><?php echo esc_html($job->status === 'pending' && !empty($job->next_retry_at) && strtotime($job->next_retry_at) > time() ? 'pending (retry wait)' : $job->status); ?></td>
                    <td><?php echo esc_html($job->retry_count . '/' . $job->max_retries); ?></td>
                    <td><?php echo esc_html($job->next_retry_at ?: '-'); ?></td>
                    <td>
                        <?php if (!empty($payload['file_url'])) : ?>
                            <a class="button" href="<?php echo esc_url($payload['file_url']); ?>">Download CSV</a>
                        <?php else : ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
