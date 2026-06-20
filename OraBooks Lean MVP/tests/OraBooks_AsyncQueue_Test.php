<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OraBooks_AsyncQueue_Test extends TestCase
{
    protected function setUp(): void
    {
        global $wpdb;
        $wpdb->insert_id = 0;
        $wpdb->test_get_var_callback = null;
        $wpdb->test_get_row_callback = null;
        $wpdb->test_get_results_callback = null;
        $wpdb->test_query_callback = null;
        $wpdb->test_insert_callback = null;
        $wpdb->test_update_callback = null;
        $GLOBALS['orabooks_test_options'] = [];
    }

    #[Test]
    public function enqueue_uses_idempotency_key_to_return_existing_job(): void
    {
        global $wpdb;
        $wpdb->test_get_var_callback = function ($query) {
            return stripos((string) $query, 'idempotency_key') !== false ? 44 : 0;
        };

        $jobId = OraBooks_AsyncQueue::enqueue('webhook_dispatch', ['event' => 'sale_delivered'], [
            'queue_name' => 'webhooks',
            'idempotency_key' => 'evt-1',
        ]);

        $this->assertSame(44, $jobId);
    }

    #[Test]
    public function manual_replay_resets_dead_letter_job_to_pending(): void
    {
        global $wpdb;
        $updated = [];
        $wpdb->test_get_row_callback = function () {
            return (object) [
                'id' => 7,
                'job_type' => 'webhook_dispatch',
                'status' => 'dead_letter',
            ];
        };
        $wpdb->test_update_callback = function ($table, $data) use (&$updated) {
            $updated[] = $data;
            return 1;
        };

        $result = OraBooks_AsyncQueue::retry_job(7);

        $this->assertTrue($result);
        $this->assertSame('pending', $updated[0]['status']);
        $this->assertSame(0, $updated[0]['retry_count']);
    }

    #[Test]
    public function webhook_settings_normalize_one_url_per_line(): void
    {
        $urls = OraBooks_AsyncQueue::save_webhook_urls("https://example.com/a\n\nhttps://example.com/b", 9);

        $this->assertSame(['https://example.com/a', 'https://example.com/b'], $urls);
        $this->assertSame($urls, OraBooks_AsyncQueue::get_webhook_urls(9));
    }

    #[Test]
    public function sl302_bridge_enqueues_webhook_queue_with_idempotency(): void
    {
        global $wpdb;
        $inserted = [];
        $wpdb->test_get_var_callback = fn () => 0;
        $wpdb->test_insert_callback = function ($table, $data) use (&$inserted, $wpdb) {
            $inserted[] = $data;
            $wpdb->insert_id = 101;
            return true;
        };

        OraBooks_Event_Module::consume_job_enqueue_bridge((object) [
            'id' => 55,
            'event_type' => 'sale_delivered',
            'aggregate_id' => 1001,
        ], ['amount' => 15]);

        $this->assertSame('webhooks', $inserted[0]['queue_name']);
        $this->assertSame('event_webhook_dispatch', $inserted[0]['job_type']);
        $this->assertSame('event-webhook-55', $inserted[0]['idempotency_key']);
    }
}
