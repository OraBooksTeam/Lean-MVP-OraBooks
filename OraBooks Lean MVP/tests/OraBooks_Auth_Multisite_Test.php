<?php
/**
 * Unit tests for multisite activation linkage in OraBooks_Auth.
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Auth_Multisite_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $wpdb->test_update_callback = null;

        $GLOBALS['orabooks_test_log_events'] = [];
    }

    #[Test]
    public function test_handle_multisite_user_activation_links_wp_user_to_orabooks_user()
    {
        global $wpdb;

        $updates = [];
        $wpdb->test_update_callback = function ($table, $data, $where) use (&$updates) {
            $updates[] = [$table, $data, $where];
            return 1;
        };

        $auth = OraBooks_Auth::init();
        $auth->handle_multisite_user_activation(321, 'unused-password', [
            'orabooks_user_id' => 44,
        ]);

        $matching_updates = array_values(array_filter($updates, function ($row) {
            [$table, $data, $where] = $row;
            return str_contains($table, 'orabooks_users')
                && isset($data['wp_user_id'])
                && (int) $data['wp_user_id'] === 321
                && isset($where['id'])
                && (int) $where['id'] === 44;
        }));

        $this->assertCount(1, $matching_updates);
        $this->assertNotEmpty($GLOBALS['orabooks_test_log_events']);
        $this->assertSame('wp_user_activated', $GLOBALS['orabooks_test_log_events'][0]['event_type']);
    }

    #[Test]
    public function test_handle_multisite_blog_activation_links_wp_user_to_orabooks_user()
    {
        global $wpdb;

        $updates = [];
        $wpdb->test_update_callback = function ($table, $data, $where) use (&$updates) {
            $updates[] = [$table, $data, $where];
            return 1;
        };

        $auth = OraBooks_Auth::init();
        $auth->handle_multisite_blog_activation(9, 654, 'unused-password', 'Site Title', [
            'orabooks_user_id' => 99,
        ]);

        $matching_updates = array_values(array_filter($updates, function ($row) {
            [$table, $data, $where] = $row;
            return str_contains($table, 'orabooks_users')
                && isset($data['wp_user_id'])
                && (int) $data['wp_user_id'] === 654
                && isset($where['id'])
                && (int) $where['id'] === 99;
        }));

        $this->assertCount(1, $matching_updates);
        $this->assertNotEmpty($GLOBALS['orabooks_test_log_events']);
        $this->assertSame('wp_user_activated', $GLOBALS['orabooks_test_log_events'][0]['event_type']);
    }

    #[Test]
    public function test_handle_multisite_activation_skips_when_orabooks_user_id_missing()
    {
        global $wpdb;

        $updates = [];
        $wpdb->test_update_callback = function ($table, $data, $where) use (&$updates) {
            $updates[] = [$table, $data, $where];
            return 1;
        };

        $auth = OraBooks_Auth::init();
        $auth->handle_multisite_user_activation(777, 'unused-password', []);

        $this->assertCount(0, $updates);
        $this->assertCount(0, $GLOBALS['orabooks_test_log_events']);
    }
}
