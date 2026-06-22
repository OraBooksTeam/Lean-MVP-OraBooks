<?php
/**
 * Unit Tests for post-deploy verification checks.
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if (!class_exists('OraBooks_AsyncQueue', false)) {
    class OraBooks_AsyncQueue {
        private static $handlers = [];

        public static function register_default_handlers() {
            self::$handlers['partner_activity_check'] = static function () {
                return true;
            };
        }

        public static function get_handler($job_type) {
            return self::$handlers[$job_type] ?? null;
        }
    }
}

require_once __DIR__ . '/../includes/class-orabooks-deploy-checks.php';

class OraBooks_Deploy_Checks_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $wpdb->test_get_var_callback = null;
        $wpdb->test_get_col_callback = null;
        $GLOBALS['orabooks_test_wp_next_scheduled_hooks'] = [];

        update_option('orabooks_jwt_secret', 'test-jwt-secret');
        update_option('orabooks_db_version', ORABOOKS_DB_VERSION);
        $GLOBALS['orabooks_test_secrets_status'] = [
            'production_mode' => false,
            'requires_tls' => false,
            'bootstrap_ready' => true,
            'jwt_secret_configured' => true,
            'encryption_key_configured' => true,
            'jwt_secret_length' => 64,
            'last_rotated' => '',
            'tls' => ['ok' => true, 'skipped' => true],
            'database_tls' => ['ok' => true, 'skipped' => true],
            'https_active' => true,
        ];
        $GLOBALS['orabooks_test_database_tls'] = [
            'ok' => true,
            'skipped' => true,
            'reason' => 'test_stub',
        ];

        OraBooks_AsyncQueue::register_default_handlers();
    }

    #[Test]
    public function test_deploy_checks_pass_when_core_tables_and_crons_exist()
    {
        global $wpdb;

        $GLOBALS['orabooks_test_wp_next_scheduled_hooks'] = [
            'orabooks_partner_activity_check' => time() + 3600,
            'orabooks_async_queue_process' => time() + 60,
            'orabooks_async_queue_archive' => time() + 86400,
            'orabooks_daily_active_status_refresh' => time() + 7200,
            'orabooks_team_cleanup_expired_invites' => time() + 86400,
            'orabooks_approval_expire_stale' => time() + 3600,
            'orabooks_approval_escalate_overdue' => time() + 3600,
            'orabooks_approval_expiry_reminders' => time() + 3600,
        ];

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'SHOW TABLES LIKE') !== false) {
                if (preg_match("/SHOW TABLES LIKE '([^']+)'/", $query, $matches)) {
                    return $matches[1];
                }
            }
            return 1;
        };

        $wpdb->test_get_col_callback = function ($query) {
            if (stripos($query, 'SHOW COLUMNS FROM') !== false) {
                return ['id', 'payment_status', 'paid_amount', 'total_amount'];
            }
            return [];
        };

        $result = OraBooks_DeployChecks::run();

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['checks']);
        $this->assertSame(ORABOOKS_DB_VERSION, $result['environment']['db_version_expected']);

        $jwt = array_values(array_filter($result['checks'], fn($row) => $row['id'] === 'jwt_secret'));
        $this->assertNotEmpty($jwt);
        $this->assertTrue($jwt[0]['ok']);
    }

    #[Test]
    public function test_deploy_checks_fail_when_db_version_mismatch()
    {
        global $wpdb;

        update_option('orabooks_db_version', '0.9.0');

        $GLOBALS['orabooks_test_wp_next_scheduled_hooks'] = [
            'orabooks_partner_activity_check' => time() + 3600,
            'orabooks_async_queue_process' => time() + 60,
            'orabooks_async_queue_archive' => time() + 86400,
            'orabooks_daily_active_status_refresh' => time() + 7200,
            'orabooks_team_cleanup_expired_invites' => time() + 86400,
            'orabooks_approval_expire_stale' => time() + 3600,
            'orabooks_approval_escalate_overdue' => time() + 3600,
            'orabooks_approval_expiry_reminders' => time() + 3600,
        ];

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'SHOW TABLES LIKE') !== false) {
                if (preg_match("/SHOW TABLES LIKE '([^']+)'/", $query, $matches)) {
                    return $matches[1];
                }
            }
            return 1;
        };

        $wpdb->test_get_col_callback = function () {
            return ['id', 'payment_status', 'paid_amount'];
        };

        $result = OraBooks_DeployChecks::run();

        $this->assertFalse($result['ok']);
        $db_check = array_values(array_filter($result['checks'], fn($row) => $row['id'] === 'db_version'));
        $this->assertNotEmpty($db_check);
        $this->assertFalse($db_check[0]['ok']);
    }

    #[Test]
    public function test_ensure_mvp_cron_schedules_repairs_missing_hooks()
    {
        $GLOBALS['orabooks_test_wp_next_scheduled_hooks'] = [];
        $GLOBALS['orabooks_test_wp_scheduled_events'] = [];

        $repaired = OraBooks_DeployChecks::ensure_mvp_cron_schedules();

        $this->assertCount(8, $repaired);
        $this->assertContains('orabooks_async_queue_process', $repaired);
        $this->assertContains('orabooks_async_queue_archive', $repaired);

        $again = OraBooks_DeployChecks::ensure_mvp_cron_schedules();
        $this->assertSame([], $again);
    }
}
