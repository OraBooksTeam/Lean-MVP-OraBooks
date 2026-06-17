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
        $this->assertContains($result['snapshots']['eventbus']['status'], ['healthy', 'degraded', 'critical']);
    }

    #[Test]
    public function test_evaluate_thresholds_sends_platform_alert()
    {
        global $wpdb;

        $this->setUserNotifPrefs(1);

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'dead_letter') !== false) {
                return 50;
            }
            return 250;
        };
        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'GROUP BY status') !== false) {
                return [(object) ['status' => 'pending', 'count' => 250]];
            }
            return [];
        };
        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'org_notification_policies') !== false) {
                return null;
            }
            if (stripos($query, 'orabooks_users') !== false) {
                return (object) ['org_id' => 0];
            }
            return null;
        };
        $wpdb->test_insert_callback = function () {
            return true;
        };

        $GLOBALS['orabooks_test_use_insert_id'] = 900;

        OraBooks_Notifications::init();
        $result = OraBooks_Observability::evaluate_thresholds();

        $this->assertGreaterThan(0, $result['alerts_sent']);
    }

    #[Test]
    public function test_status_from_counts_marks_critical_when_dead_letters_high()
    {
        $ref = new ReflectionMethod(OraBooks_Observability::class, 'status_from_counts');
        $status = $ref->invoke(null, 10, 100, 25, 20);

        $this->assertEquals('critical', $status);
    }

    private function setUserNotifPrefs(int $user_id): void
    {
        update_user_meta($user_id, 'orabooks_notification_prefs', [
            'channels'           => ['email', 'inapp'],
            'quiet_hours_start'  => '',
            'quiet_hours_end'    => '',
            'digest'             => 'none',
            'language'           => 'en',
            'escalation_enabled' => true,
            'updated_at'         => current_time('mysql', true),
        ]);
    }
}
