<?php

use PHPUnit\Framework\TestCase;

class OraBooks_Audit_Test extends TestCase
{
    /** @var wpdb */
    private $wpdb;

    protected function setUp(): void
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->wpdb->test_insert_callback = null;
        $this->wpdb->test_get_results_callback = null;
        $this->wpdb->test_query_callback = null;
        unset($GLOBALS['orabooks_correlation_id']);
        $GLOBALS['orabooks_test_last_audit_insert'] = null;
    }

    protected function tearDown(): void
    {
        $this->wpdb->test_insert_callback = null;
        $this->wpdb->test_get_results_callback = null;
        $this->wpdb->test_query_callback = null;
        unset($GLOBALS['orabooks_correlation_id'], $GLOBALS['orabooks_test_last_audit_insert']);
    }

    public function test_log_event_uses_provided_correlation_id()
    {
        $this->wpdb->test_insert_callback = function ($table, $data) {
            $GLOBALS['orabooks_test_last_audit_insert'] = $data;
            return 1;
        };

        $cid = '11111111-1111-4111-8111-111111111111';
        OraBooks_Audit::log_event('login_success', 'User logged in', 'info', [], 5, 10, $cid);

        $this->assertSame($cid, $GLOBALS['orabooks_test_last_audit_insert']['correlation_id']);
    }

    public function test_log_event_reuses_request_correlation_id()
    {
        $this->wpdb->test_insert_callback = function ($table, $data) {
            $GLOBALS['orabooks_test_last_audit_insert'] = $data;
            return 1;
        };

        orabooks_set_correlation_id('22222222-2222-4222-8222-222222222222');
        OraBooks_Audit::log_event('journal_posted', 'Journal posted', 'info', [], 5, 10);

        $this->assertSame('22222222-2222-4222-8222-222222222222', $GLOBALS['orabooks_test_last_audit_insert']['correlation_id']);
    }

    public function test_log_event_strips_password_and_masks_email_metadata()
    {
        $this->wpdb->test_insert_callback = function ($table, $data) {
            $GLOBALS['orabooks_test_last_audit_insert'] = $data;
            return 1;
        };

        OraBooks_Audit::log_event('partner_attribution_created', 'Attribution created', 'info', [
            'password' => 'secret123',
            'customer_email' => 'customer@example.com',
            'partner_type' => 'agency',
        ], 1, 0);

        $meta = json_decode($GLOBALS['orabooks_test_last_audit_insert']['metadata'], true);
        $this->assertSame('[REDACTED]', $meta['password']);
        $this->assertSame('agency', $meta['partner_type']);
        $this->assertArrayNotHasKey('customer_email', $meta);
        $this->assertSame(orabooks_mask_email('customer@example.com'), $meta['customer_email_masked']);
        $this->assertSame(orabooks_hash_email('customer@example.com'), $meta['customer_email_hash']);
    }

    public function test_get_logs_skips_view_event_when_requested()
    {
        $inserts = [];
        $this->wpdb->test_insert_callback = function ($table, $data) use (&$inserts) {
            $inserts[] = $data;
            return 1;
        };
        $this->wpdb->test_get_results_callback = function () {
            return [];
        };

        OraBooks_Audit::get_logs(1, ['skip_view_log' => true]);
        $this->assertCount(0, $inserts);

        OraBooks_Audit::get_logs(1, []);
        $this->assertCount(1, $inserts);
        $this->assertSame('audit_log_viewed', $inserts[0]['event_type']);
    }

    public function test_archive_old_logs_sets_archival_session_var()
    {
        $queries = [];
        $this->wpdb->test_query_callback = function ($query) use (&$queries) {
            $queries[] = $query;
            return 1;
        };
        $this->wpdb->test_insert_callback = function ($table, $data) {
            $GLOBALS['orabooks_test_last_audit_insert'] = $data;
            return 1;
        };

        OraBooks_Audit::archive_old_logs();

        $this->assertTrue((bool) preg_grep('/@orabooks_audit_archival = 1/', $queries));
        $this->assertSame('audit_log_archival', $GLOBALS['orabooks_test_last_audit_insert']['event_type'] ?? null);
    }

    public function test_mask_and_hash_email_helpers()
    {
        $this->assertSame('cu******@example.com', orabooks_mask_email('customer@example.com'));
        $this->assertSame(64, strlen(orabooks_hash_email('customer@example.com')));
    }
}
