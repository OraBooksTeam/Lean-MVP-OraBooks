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
        $GLOBALS['orabooks_test_use_insert_id'] = null;

        $ref = new ReflectionProperty(OraBooks_AsyncQueue::class, 'handlers');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
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
    public function enqueue_idempotency_key_is_scoped_to_org_id(): void
    {
        global $wpdb;

        $wpdb->test_get_var_callback = function ($query) {
            $sql = (string) $query;
            if (stripos($sql, 'idempotency_key') !== false && stripos($sql, "JSON_EXTRACT(payload, '$.org_id')") !== false) {
                return 0;
            }
            return 0;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 145;

        $jobId = OraBooks_AsyncQueue::enqueue('webhook_dispatch', ['event' => 'sale_delivered', 'org_id' => 77], [
            'queue_name' => 'webhooks',
            'idempotency_key' => 'evt-1',
        ]);

        $this->assertSame(145, $jobId);
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
                'payload' => wp_json_encode(['org_id' => 9]),
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
    public function retry_job_denies_cross_tenant_replay(): void
    {
        global $wpdb;
        $wpdb->test_get_row_callback = function () {
            return (object) [
                'id' => 7,
                'job_type' => 'webhook_dispatch',
                'status' => 'dead_letter',
                'payload' => wp_json_encode(['org_id' => 99]),
            ];
        };

        $result = OraBooks_AsyncQueue::retry_job(7, 9);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('forbidden', $result->get_error_code());
    }

    #[Test]
    public function list_jobs_filters_by_payload_org_id(): void
    {
        global $wpdb;
        $query = '';
        $wpdb->test_get_results_callback = function ($sql) use (&$query) {
            $query = $sql;
            return [];
        };

        OraBooks_AsyncQueue::list_jobs(['org_id' => 12, 'limit' => 10]);

        $this->assertStringContainsString("JSON_EXTRACT(payload, '$.org_id')", $query);
        $this->assertStringContainsString('12', $query);
    }

    #[Test]
    public function get_job_org_id_reads_payload_org_id(): void
    {
        $org_id = OraBooks_AsyncQueue::get_job_org_id((object) [
            'payload' => wp_json_encode(['org_id' => 42, 'event_type' => 'sale_delivered']),
        ]);

        $this->assertSame(42, $org_id);
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
        $GLOBALS['orabooks_test_use_insert_id'] = 101;
        $wpdb->test_insert_callback = function ($table, $data) use (&$inserted) {
            $inserted[] = $data;
        };

        OraBooks_Event_Module::consume_job_enqueue_bridge((object) [
            'id' => 55,
            'event_type' => 'sale_delivered',
            'aggregate_id' => 1001,
        ], ['amount' => 15, 'org_id' => 9]);

        $this->assertSame('webhooks', $inserted[0]['queue_name']);
        $this->assertSame('event_webhook_dispatch', $inserted[0]['job_type']);
        $this->assertSame('event-webhook-55', $inserted[0]['idempotency_key']);
        $payload = json_decode($inserted[0]['payload'], true);
        $this->assertSame(9, $payload['org_id']);
    }

    #[Test]
    public function process_queue_marks_job_completed_when_handler_succeeds(): void
    {
        global $wpdb;

        $job = (object) [
            'id' => 12,
            'queue_name' => 'default',
            'job_type' => 'unit_success',
            'payload' => wp_json_encode(['org_id' => 9]),
            'status' => 'pending',
            'priority' => 3,
            'retry_count' => 0,
            'max_retries' => 5,
        ];

        $updates = [];
        OraBooks_AsyncQueue::register_handler('unit_success', function () {
            return true;
        });

        $wpdb->test_get_results_callback = function ($query) use ($job) {
            return stripos((string) $query, "status = 'pending'") !== false ? [$job] : [];
        };
        $wpdb->test_get_row_callback = function ($query) use ($job) {
            return stripos((string) $query, 'FOR UPDATE') !== false ? clone $job : null;
        };
        $wpdb->test_update_callback = function ($table, $data) use (&$updates) {
            $updates[] = $data;
            return 1;
        };

        $result = (new OraBooks_AsyncQueue())->process_queue();

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['completed']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame('processing', $updates[0]['status']);
        $this->assertSame('completed', $updates[1]['status']);
    }

    #[Test]
    public function process_queue_schedules_retry_with_backoff_when_handler_fails(): void
    {
        global $wpdb;

        $job = (object) [
            'id' => 13,
            'queue_name' => 'default',
            'job_type' => 'unit_retry',
            'payload' => wp_json_encode(['org_id' => 9]),
            'status' => 'pending',
            'priority' => 4,
            'retry_count' => 1,
            'max_retries' => 5,
        ];

        $updates = [];
        OraBooks_AsyncQueue::register_handler('unit_retry', function () {
            return false;
        });

        $wpdb->test_get_results_callback = function ($query) use ($job) {
            return stripos((string) $query, "status = 'pending'") !== false ? [$job] : [];
        };
        $wpdb->test_get_row_callback = function ($query) use ($job) {
            return stripos((string) $query, 'FOR UPDATE') !== false ? clone $job : null;
        };
        $wpdb->test_update_callback = function ($table, $data) use (&$updates) {
            $updates[] = $data;
            return 1;
        };

        $result = (new OraBooks_AsyncQueue())->process_queue();

        $this->assertSame(1, $result['processed']);
        $this->assertSame(0, $result['completed']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame('pending', $updates[1]['status']);
        $this->assertSame(2, $updates[1]['retry_count']);
        $this->assertArrayHasKey('next_retry_at', $updates[1]);
    }

    #[Test]
    public function process_queue_moves_job_to_dead_letter_after_max_retries(): void
    {
        global $wpdb;

        $job = (object) [
            'id' => 14,
            'queue_name' => 'default',
            'job_type' => 'unit_dead',
            'payload' => wp_json_encode(['org_id' => 9]),
            'status' => 'pending',
            'priority' => 4,
            'retry_count' => 4,
            'max_retries' => 5,
        ];

        $updates = [];
        OraBooks_AsyncQueue::register_handler('unit_dead', function () {
            throw new RuntimeException('forced failure');
        });

        $wpdb->test_get_results_callback = function ($query) use ($job) {
            return stripos((string) $query, "status = 'pending'") !== false ? [$job] : [];
        };
        $wpdb->test_get_row_callback = function ($query) use ($job) {
            return stripos((string) $query, 'FOR UPDATE') !== false ? clone $job : null;
        };
        $wpdb->test_update_callback = function ($table, $data) use (&$updates) {
            $updates[] = $data;
            return 1;
        };

        $result = (new OraBooks_AsyncQueue())->process_queue();

        $this->assertSame(1, $result['failed']);
        $this->assertSame('dead_letter', $updates[1]['status']);
        $this->assertSame(5, $updates[1]['retry_count']);
        $this->assertSame('forced failure', $updates[1]['last_error']);
    }

    #[Test]
    public function heartbeat_recovery_returns_recovered_and_dead_counts(): void
    {
        global $wpdb;

        $wpdb->test_query_callback = function ($query) {
            if (stripos((string) $query, 'retry_count < max_retries') !== false) {
                return 2;
            }
            if (stripos((string) $query, 'retry_count >= max_retries') !== false) {
                return 1;
            }
            return 0;
        };

        $result = (new OraBooks_AsyncQueue())->heartbeat_recovery();

        $this->assertSame(2, $result['recovered']);
        $this->assertSame(1, $result['dead']);
    }

    #[Test]
    public function process_queue_skips_job_if_lock_row_is_not_pending(): void
    {
        global $wpdb;

        $job = (object) [
            'id' => 21,
            'queue_name' => 'default',
            'job_type' => 'unit_lock',
            'payload' => wp_json_encode(['org_id' => 9]),
            'status' => 'pending',
            'priority' => 5,
            'retry_count' => 0,
            'max_retries' => 5,
        ];

        OraBooks_AsyncQueue::register_handler('unit_lock', function () {
            return true;
        });

        $wpdb->test_get_results_callback = function ($query) use ($job) {
            return stripos((string) $query, "status = 'pending'") !== false ? [$job] : [];
        };
        $wpdb->test_get_row_callback = function ($query) use ($job) {
            if (stripos((string) $query, 'FOR UPDATE') !== false) {
                $locked = clone $job;
                $locked->status = 'processing';
                return $locked;
            }
            return null;
        };

        $result = (new OraBooks_AsyncQueue())->process_queue();

        $this->assertSame(0, $result['processed']);
        $this->assertSame(0, $result['completed']);
        $this->assertSame(0, $result['failed']);
    }

    #[Test]
    public function webhook_dispatch_adds_hmac_signature_headers_when_secret_is_present(): void
    {
        $captured = [];
        $GLOBALS['orabooks_test_wp_remote_request_callback'] = function ($url, $args) use (&$captured) {
            $captured = ['url' => $url, 'args' => $args];
            return [
                'response' => ['code' => 200],
                'body' => '{}',
            ];
        };

        $job = (object) ['id' => 222, 'job_type' => 'webhook_dispatch'];
        $result = OraBooks_AsyncQueue::handle_webhook_dispatch($job, [
            'url' => 'https://example.com/webhook',
            'body' => ['event' => 'invoice_posted', 'org_id' => 9],
            'signing_secret' => 'test-signing-secret',
        ]);

        $this->assertTrue($result);
        $this->assertSame('https://example.com/webhook', $captured['url']);
        $this->assertArrayHasKey('X-OraBooks-Webhook-Timestamp', $captured['args']['headers']);
        $this->assertArrayHasKey('X-OraBooks-Webhook-Job-Id', $captured['args']['headers']);
        $this->assertArrayHasKey('X-OraBooks-Webhook-Signature', $captured['args']['headers']);
        $this->assertSame('222', $captured['args']['headers']['X-OraBooks-Webhook-Job-Id']);

        $signed_payload = $captured['args']['headers']['X-OraBooks-Webhook-Timestamp'] . "\n" . $captured['args']['body'];
        $expected = hash_hmac('sha256', $signed_payload, 'test-signing-secret');
        $this->assertSame($expected, $captured['args']['headers']['X-OraBooks-Webhook-Signature']);

        unset($GLOBALS['orabooks_test_wp_remote_request_callback']);
    }
}
