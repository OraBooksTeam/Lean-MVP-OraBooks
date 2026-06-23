<?php
/**
 * Unit Tests for OraBooks_Observability (SL-093)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Observability_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $wpdb->test_get_var_callback = null;
        $wpdb->test_get_row_callback = null;
        $wpdb->test_get_results_callback = null;
        $wpdb->test_query_callback = null;
        $wpdb->test_insert_callback = null;
        $wpdb->insert_id = 0;
        $wpdb->last_query = '';

        $GLOBALS['orabooks_test_current_user_id'] = 1;
        $GLOBALS['orabooks_test_current_user_can'] = true;
        $GLOBALS['orabooks_test_use_insert_id'] = null;
        $GLOBALS['orabooks_test_user_meta'] = [];
    }

    #[Test]
    public function test_schema_defines_sl093_tables()
    {
        $sql = implode("\n", OraBooks_Observability::get_create_table_sql());

        $this->assertStringContainsString('orabooks_platform_metrics', $sql);
        $this->assertStringContainsString('orabooks_health_check_runs', $sql);
        $this->assertStringContainsString("ENUM('healthy','degraded','critical')", $sql);
    }

    #[Test]
    public function test_record_metric_inserts_sample()
    {
        global $wpdb;

        $captured = null;
        $wpdb->test_insert_callback = function ($table, $data) use (&$captured) {
            $captured = [$table, $data];
            return true;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 501;

        $id = OraBooks_Observability::record_metric('eventbus', 'queue_depth', 42, null, ['pending' => 42]);

        $this->assertEquals(501, $id);
        $this->assertNotNull($captured);
        $this->assertStringContainsString('platform_metrics', $captured[0]);
        $this->assertEquals('eventbus', $captured[1]['service']);
        $this->assertEquals(42.0, $captured[1]['metric_value']);
    }

    #[Test]
    public function test_collect_platform_metrics_snapshots_services()
    {
        global $wpdb;

        $call = 0;
        $wpdb->test_get_var_callback = function ($query) use (&$call) {
            $call++;
            if (stripos($query, 'dead_letter') !== false) {
                return 2;
            }
            if (stripos($query, 'failed') !== false) {
                return 1;
            }
            return 5;
        };
        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'GROUP BY status') !== false) {
                return [(object) ['status' => 'pending', 'count' => 3]];
            }
            return [];
        };
        $wpdb->test_insert_callback = function () {
            return true;
        };

        $result = OraBooks_Observability::collect_platform_metrics();

        $this->assertArrayHasKey('snapshots', $result);
        $this->assertArrayHasKey('eventbus', $result['snapshots']);
        $this->assertArrayHasKey('async_queue', $result['snapshots']);
        $this->assertArrayHasKey('notifications', $result['snapshots']);
        $this->assertArrayHasKey('exports', $result['snapshots']);
        $this->assertArrayHasKey('workflow', $result['snapshots']);
        $this->assertArrayHasKey('expense_ocr', $result['snapshots']);
        $this->assertContains($result['snapshots']['eventbus']['status'], ['healthy', 'degraded', 'critical']);
    }

    #[Test]
    public function test_evaluate_thresholds_identifies_backlog()
    {
        $snapshots = [
            'eventbus' => ['pending' => 500, 'dead_letter' => 15, 'status' => 'critical'],
            'async_queue' => [
                'pending' => 300,
                'dead_letter' => 25,
                'failure_rate_24h' => 12,
                'avg_latency_seconds' => 4,
                'status' => 'critical',
            ],
            'notifications' => ['pending' => 10, 'dead_letter' => 30, 'status' => 'critical'],
            'exports' => ['pending' => 5, 'failed_24h' => 20, 'status' => 'critical'],
            'workflow' => ['transitions_24h' => 100, 'failures_24h' => 25, 'status' => 'degraded'],
            'expense_ocr' => ['queue_depth' => 0, 'failed_24h' => 0, 'status' => 'healthy'],
        ];

        $result = OraBooks_Observability::evaluate_thresholds($snapshots);

        $this->assertGreaterThan(0, $result['alerts_sent']);
        $this->assertNotEmpty($result['alerts']);
    }

    #[Test]
    public function test_status_from_counts_marks_critical_when_dead_letters_high()
    {
        $ref = new ReflectionMethod(OraBooks_Observability::class, 'status_from_counts');
        $status = $ref->invoke(null, 10, 100, 25, 20);

        $this->assertEquals('critical', $status);
    }

    #[Test]
    public function test_get_workflow_health_supports_org_scope()
    {
        global $wpdb;

        $queries = [];
        $wpdb->test_get_var_callback = function ($query) use (&$queries) {
            $queries[] = $query;
            return stripos($query, 'state_machine_transitions') !== false ? 12 : 2;
        };

        $health = OraBooks_Observability::get_workflow_health(7);

        $this->assertEquals(7, $health['org_id']);
        $this->assertEquals(12, $health['transitions_24h']);
        $this->assertEquals(2, $health['failures_24h']);
        $this->assertTrue(
            (bool) array_filter($queries, fn($q) => stripos($q, 'org_id =') !== false)
        );
    }

    #[Test]
    public function test_slo_dashboard_calculates_error_budget()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'notifications') !== false && stripos($query, 'critical') !== false) {
                return (object) ['total' => 100, 'good' => 99, 'bad' => 1];
            }
            if (stripos($query, 'notifications') !== false) {
                return (object) ['total' => 1000, 'good' => 998, 'bad' => 2];
            }
            if (stripos($query, 'async_jobs') !== false) {
                return (object) ['total' => 200, 'good' => 198, 'bad' => 2];
            }
            if (stripos($query, 'outbox_messages') !== false) {
                return (object) ['total' => 500, 'good' => 495, 'bad' => 5];
            }
            return null;
        };
        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'state_machine_transitions') !== false) {
                return 980;
            }
            if (stripos($query, 'audit_logs') !== false) {
                return 5;
            }
            return 0;
        };
        $wpdb->test_insert_callback = function () {
            return true;
        };

        $dashboard = OraBooks_Observability::get_slo_dashboard(30);

        $this->assertArrayHasKey('objectives', $dashboard);
        $this->assertArrayHasKey('notifications_delivery', $dashboard['objectives']);
        $delivery = $dashboard['objectives']['notifications_delivery'];
        $this->assertSame('healthy', $delivery['status']);
        $this->assertGreaterThan(0, $delivery['error_budget_remaining_percent']);
        $this->assertTrue($delivery['meets_slo']);
    }

    #[Test]
    public function test_evaluate_error_budgets_alerts_on_breach()
    {
        $ref = new ReflectionMethod(OraBooks_Observability::class, 'build_slo_status');
        $definition = [
            'name' => 'Test SLO',
            'description' => 'Test',
            'target_percent' => 99.5,
            'window_days' => 30,
        ];
        $sli = ['total' => 100, 'good' => 90, 'bad' => 10, 'current_percent' => 90.0];
        $status = $ref->invoke(null, 'test_slo', $definition, $sli, 30);

        $this->assertSame('breached', $status['status']);
        $this->assertFalse($status['meets_slo']);

        $alerts = OraBooks_Observability::evaluate_error_budgets();
        $this->assertIsArray($alerts);
    }
}
