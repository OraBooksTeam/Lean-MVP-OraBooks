<?php
/**
 * Unit Tests for OraBooks_Fiscal (SL-304)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Fiscal_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['orabooks_test_log_events'] = [];

        global $wpdb;
        $wpdb->test_get_var_callback = null;
        $wpdb->test_get_row_callback = null;
        $wpdb->test_get_results_callback = null;
        $wpdb->test_query_callback = null;
        $wpdb->test_update_callback = null;
        $wpdb->insert_id = 0;
        $wpdb->last_query = '';
        $wpdb->last_result = [];
    }

    #[Test]
    public function test_can_post_blocks_closed_period()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'fiscal_periods') !== false) {
                return (object) ['status' => 'soft_closed'];
            }
            return null;
        };

        $result = OraBooks_Fiscal::can_post(4, '2026-06-15');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('fiscal_closed', $result->get_error_code());
    }

    #[Test]
    public function test_can_post_allows_open_period()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'fiscal_periods') !== false) {
                return (object) ['status' => 'open'];
            }
            return null;
        };

        $result = OraBooks_Fiscal::can_post(4, '2026-06-15');
        $this->assertTrue($result);
    }

    #[Test]
    public function test_close_period_rejects_non_open_status()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function () {
            return (object) [
                'id' => 10,
                'org_id' => 2,
                'period_start' => '2026-06-01',
                'period_end' => '2026-06-30',
                'status' => 'soft_closed',
            ];
        };

        $result = OraBooks_Fiscal::close_period(10, 2, 'soft', 1);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_status', $result->get_error_code());
    }

    #[Test]
    public function test_reopen_period_requires_reason()
    {
        $result = OraBooks_Fiscal::reopen_period(10, 2, 1, '   ');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('reason_required', $result->get_error_code());
    }

    #[Test]
    public function test_reopen_period_blocks_hard_closed()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function () {
            return (object) [
                'id' => 10,
                'org_id' => 2,
                'period_start' => '2026-05-01',
                'period_end' => '2026-05-31',
                'status' => 'hard_closed',
            ];
        };

        $result = OraBooks_Fiscal::reopen_period(10, 2, 1, 'Correction needed');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('hard_closed', $result->get_error_code());
    }

    #[Test]
    public function test_is_period_hard_closed()
    {
        global $wpdb;

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'fiscal_periods') !== false) {
                return 'hard_closed';
            }
            return null;
        };

        $this->assertTrue(OraBooks_Fiscal::is_period_hard_closed(2, '2026-05-15'));
    }
}
