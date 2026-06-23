<?php
/**
 * SL-028 Phase 5 — observability metrics and production confidence tests.
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Expenses_Phase5_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $wpdb->test_get_var_callback = null;
        $wpdb->test_get_row_callback = null;
        $wpdb->test_get_results_callback = null;

        $GLOBALS['orabooks_test_log_events'] = [];
        $GLOBALS['orabooks_test_publish_event_result'] = 100;
        unset($GLOBALS['orabooks_test_rate_limit_allowed']);
    }

    #[Test]
    public function test_get_observability_stats_returns_expected_shape(): void
    {
        global $wpdb;

        $queue_table = OraBooks_Database::table('ocr_processing_queue');
        $expenses_table = OraBooks_Database::table('expenses');

        $wpdb->test_get_var_callback = function ($query) use ($queue_table, $expenses_table) {
            if (stripos($query, $queue_table) !== false && stripos($query, "status = 'pending'") !== false) {
                return 3;
            }
            if (stripos($query, $queue_table) !== false && stripos($query, "status = 'processing'") !== false) {
                return 1;
            }
            if (stripos($query, $queue_table) !== false && stripos($query, "status = 'completed'") !== false) {
                return 12;
            }
            if (stripos($query, $queue_table) !== false && stripos($query, "status = 'failed'") !== false) {
                return 2;
            }
            if (stripos($query, $expenses_table) !== false && stripos($query, 'AVG(ocr_confidence)') !== false) {
                return 84.5;
            }
            return null;
        };

        $stats = OraBooks_Expenses::get_observability_stats();

        $this->assertSame(4, $stats['queue_depth']);
        $this->assertSame(3, $stats['pending_count']);
        $this->assertSame(1, $stats['processing_count']);
        $this->assertSame(12, $stats['completed_24h']);
        $this->assertSame(2, $stats['failed_24h']);
        $this->assertSame(0.8571, $stats['success_rate_24h']);
        $this->assertSame(84.5, $stats['avg_confidence_24h']);
        $this->assertSame(24, $stats['window_hours']);
        $this->assertNull($stats['org_id']);
    }

    #[Test]
    public function test_get_observability_stats_scoped_to_org(): void
    {
        global $wpdb;

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'org_id = 9') !== false && stripos($query, "status = 'pending'") !== false) {
                return 1;
            }
            if (stripos($query, 'org_id = 9') !== false && stripos($query, "status = 'processing'") !== false) {
                return 0;
            }
            if (stripos($query, 'org_id = 9') !== false && stripos($query, "status = 'completed'") !== false) {
                return 4;
            }
            if (stripos($query, 'org_id = 9') !== false && stripos($query, "status = 'failed'") !== false) {
                return 0;
            }
            if (stripos($query, 'org_id = 9') !== false && stripos($query, 'AVG(ocr_confidence)') !== false) {
                return 91.2;
            }
            return null;
        };

        $stats = OraBooks_Expenses::get_observability_stats(9);

        $this->assertSame(9, $stats['org_id']);
        $this->assertSame(1, $stats['queue_depth']);
        $this->assertSame(1.0, $stats['success_rate_24h']);
    }

    #[Test]
    public function test_upload_receipt_returns_rate_limit_error(): void
    {
        $GLOBALS['orabooks_test_rate_limit_allowed'] = false;

        $result = OraBooks_Expenses::upload_receipt(9, 1, 'receipt.pdf', '%PDF-1.4', 'application/pdf');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('rate_limit', $result->get_error_code());
    }

    #[Test]
    public function test_ocr_stub_produces_hashable_ocr_data(): void
    {
        $ocr = OraBooks_Expenses::run_ocr_stub('vendor-receipt.pdf', 21);

        $this->assertNotEmpty($ocr['ocr_data']['fields']);
        $hash = hash('sha256', wp_json_encode($ocr['ocr_data']));
        $this->assertSame(64, strlen($hash));
        $this->assertArrayHasKey('vendor_tax_id', $ocr);
        $this->assertArrayHasKey('merchant_address', $ocr);
    }

    #[Test]
    public function test_confirm_submit_audit_includes_snapshot_hash(): void
    {
        global $wpdb;

        $expense = (object) [
            'id'                 => 55,
            'org_id'             => 9,
            'vendor'             => 'Office Depot',
            'vendor_tax_id'      => null,
            'invoice_number'     => 'RCP-000055',
            'transaction_date'   => '2026-06-18',
            'due_date'           => null,
            'subtotal'           => 95.00,
            'tax_amount'         => 5.00,
            'tax_rate'           => 5.00,
            'total_amount'       => 100.00,
            'currency'           => 'USD',
            'payment_method'     => 'Credit Card',
            'category'           => 'Office Supplies',
            'merchant_address'   => null,
            'description'        => 'Printer paper',
            'ocr_confidence'     => 88.0,
            'ocr_risk_level'     => 'low',
            'ocr_data'           => wp_json_encode(['fields' => []]),
            'ocr_provider'       => 'mvp-stub',
            'ocr_model_version'  => 'mvp-stub-1.0',
            'ocr_snapshot_hash'  => 'hash-submit-55',
            'workflow_status'    => 'draft',
            'payment_status'     => 'unpaid',
            'lock_status'        => 'unlocked',
            'idempotency_key'    => null,
            'attachment_id'      => 3,
            'journal_id'         => null,
            'created_by'         => 1,
            'approved_by'        => null,
            'posted_by'          => null,
            'approved_at'        => null,
            'posted_at'          => null,
            'created_at'         => '2026-06-18 09:00:00',
            'updated_at'         => '2026-06-18 09:00:00',
        ];

        $wpdb->test_get_row_callback = function ($query) use (&$expense) {
            if (stripos($query, 'ocr_processing_queue') !== false) {
                return (object) ['status' => 'completed', 'error_message' => null];
            }
            if (stripos($query, 'expenses') !== false) {
                return $expense;
            }
            return null;
        };
        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'idempotency_key') !== false) {
                return null;
            }
            return null;
        };
        $wpdb->test_update_callback = function ($table, $data, $where) use (&$expense) {
            foreach ($data as $key => $value) {
                $expense->$key = $value;
            }
            return 1;
        };

        OraBooks_Expenses::confirm_submit(55, 9, 1, 'idem-audit-1');

        $submit_events = array_values(array_filter(
            $GLOBALS['orabooks_test_log_events'],
            fn ($row) => ($row['event_type'] ?? '') === 'expense_submitted'
        ));

        $this->assertCount(1, $submit_events);
        $metadata = $submit_events[0]['metadata'] ?? [];
        $this->assertSame('hash-submit-55', $metadata['ocr_snapshot_hash'] ?? null);
        $this->assertSame('low', $metadata['risk_level'] ?? null);
    }

    #[Test]
    public function test_collect_platform_metrics_includes_expense_ocr(): void
    {
        global $wpdb;

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'ocr_processing_queue') !== false && stripos($query, "status = 'pending'") !== false) {
                return 2;
            }
            if (stripos($query, 'ocr_processing_queue') !== false && stripos($query, "status = 'processing'") !== false) {
                return 1;
            }
            if (stripos($query, 'ocr_processing_queue') !== false && stripos($query, "status = 'completed'") !== false) {
                return 8;
            }
            if (stripos($query, 'ocr_processing_queue') !== false && stripos($query, "status = 'failed'") !== false) {
                return 1;
            }
            if (stripos($query, 'AVG(ocr_confidence)') !== false) {
                return 79.3;
            }
            if (stripos($query, 'outbox_messages') !== false) {
                return 0;
            }
            if (stripos($query, 'notifications') !== false) {
                return 0;
            }
            if (stripos($query, 'export_requests') !== false) {
                return 0;
            }
            return null;
        };
        $wpdb->test_get_results_callback = function () {
            return [];
        };
        $wpdb->test_insert_callback = function () {
            return true;
        };

        $result = OraBooks_Observability::collect_platform_metrics();

        $this->assertArrayHasKey('expense_ocr', $result['snapshots']);
        $this->assertSame(3, $result['snapshots']['expense_ocr']['queue_depth']);
        $this->assertSame(79.3, $result['snapshots']['expense_ocr']['avg_confidence_24h']);
        $this->assertContains($result['snapshots']['expense_ocr']['status'], ['healthy', 'degraded', 'critical']);
    }

    #[Test]
    public function test_evaluate_thresholds_alerts_on_expense_ocr_failures(): void
    {
        $snapshots = [
            'eventbus' => ['pending' => 0, 'dead_letter' => 0, 'status' => 'healthy'],
            'async_queue' => [
                'pending' => 0,
                'dead_letter' => 0,
                'failure_rate_24h' => 0,
                'avg_latency_seconds' => 0,
                'status' => 'healthy',
            ],
            'notifications' => ['pending' => 0, 'dead_letter' => 0, 'status' => 'healthy'],
            'exports' => ['pending' => 0, 'failed_24h' => 0, 'status' => 'healthy'],
            'workflow' => ['transitions_24h' => 0, 'failures_24h' => 0, 'status' => 'healthy'],
            'expense_ocr' => [
                'queue_depth' => 60,
                'failed_24h' => 15,
                'completed_24h' => 5,
                'status' => 'critical',
            ],
        ];

        $result = OraBooks_Observability::evaluate_thresholds($snapshots);

        $types = array_column($result['alerts'], 'event_type');
        $this->assertContains('expense_ocr_lag', $types);
        $this->assertContains('expense_ocr_failures', $types);
    }

    #[Test]
    public function test_handle_async_ocr_job_requires_queue_id(): void
    {
        $result = OraBooks_Expenses::handle_async_ocr_job((object) ['id' => 1], []);

        $this->assertSame('Missing OCR queue id', $result);
    }

    #[Test]
    public function test_schema_includes_ocr_queue_status_enum(): void
    {
        $sql = implode("\n", OraBooks_Expenses::get_create_table_sql());

        $this->assertStringContainsString("ENUM('pending','processing','completed','failed')", $sql);
    }

    #[Test]
    public function test_run_live_checks_includes_expense_settings_and_ocr_metrics(): void
    {
        global $wpdb;

        $settings_table = OraBooks_Database::table('expense_settings');
        $wpdb->test_get_var_callback = function ($query) use ($settings_table) {
            if (stripos($query, 'SHOW TABLES LIKE') !== false && stripos($query, 'expense_settings') !== false) {
                return $settings_table;
            }
            if (stripos($query, 'SHOW TABLES LIKE') !== false) {
                return 'orabooks_expenses';
            }
            if (stripos($query, 'ocr_processing_queue') !== false) {
                return 0;
            }
            if (stripos($query, 'AVG(ocr_confidence)') !== false) {
                return null;
            }
            return 1;
        };
        $wpdb->test_get_col_callback = function () {
            return ['ocr_confidence', 'ocr_risk_level', 'ocr_snapshot_hash'];
        };
        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'expenses') !== false && stripos($query, 'ORDER BY id DESC') !== false) {
                return (object) [
                    'id' => 1,
                    'workflow_status' => 'draft',
                    'ocr_confidence' => null,
                    'ocr_risk_level' => 'low',
                    'ocr_snapshot_hash' => null,
                ];
            }
            return null;
        };

        $result = OraBooks_Expenses::run_live_checks(9);

        $check_ids = array_column($result['checks'], 'id');
        $this->assertContains('table_expense_settings', $check_ids);
        $this->assertArrayHasKey('ocr_observability', $result['environment']);
        $this->assertArrayHasKey('queue_depth', $result['environment']['ocr_observability']);
    }
}
