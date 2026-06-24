<?php
/**
 * Unit Tests for SL-014 Team Management (invites, roles, membership).
 *
 * Run: vendor/bin/phpunit --configuration tests/phpunit.xml --testsuite "OraBooks SL-014 Team Tests"
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-orabooks-team.php';

class OraBooks_Team_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        OraBooks_Team::init();

        global $wpdb;
        $wpdb->test_get_var_callback = null;
        $wpdb->test_get_row_callback = null;
        $wpdb->test_get_results_callback = null;
        $wpdb->test_query_callback = null;
        $wpdb->test_insert_callback = null;
        $wpdb->test_update_callback = null;
        $wpdb->test_delete_callback = null;
        $wpdb->insert_id = 0;
        $wpdb->last_query = '';
        $wpdb->last_result = [];

        $GLOBALS['orabooks_test_log_events'] = [];
        $GLOBALS['orabooks_test_org_callback'] = null;
        $GLOBALS['orabooks_test_get_user_role_callback'] = null;
        $GLOBALS['orabooks_test_current_user_id'] = 1;
        $GLOBALS['orabooks_test_current_user_can'] = true;
        unset($GLOBALS['orabooks_test_rate_limit_allowed']);

        $_POST = [];
        $_GET = [];
        $_REQUEST = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    /**
     * @return array<string, mixed>
     */
    private function captureJsonError(callable $callback): array
    {
        try {
            $callback();
            $this->fail('Expected JSON error response.');
        } catch (\RuntimeException $exception) {
            $payload = json_decode($exception->getMessage(), true);
            $this->assertIsArray($payload);
            $this->assertTrue($payload['error']);

            return $payload;
        }
    }

    #[Test]
    public function test_invite_user_rejects_invalid_email(): void
    {
        $result = OraBooks_Team::invite_user(10, 'not-an-email', 'staff', 1);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_email', $result->get_error_code());
    }

    #[Test]
    public function test_invite_user_rejects_invalid_role(): void
    {
        $result = OraBooks_Team::invite_user(10, 'staff@example.com', 'owner', 1);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_role', $result->get_error_code());
    }

    #[Test]
    public function test_invite_user_rejects_existing_member(): void
    {
        global $wpdb;

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'user_org') !== false && stripos($query, 'email') !== false) {
                return 99;
            }

            return null;
        };

        $result = OraBooks_Team::invite_user(10, 'member@example.com', 'staff', 1);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('already_member', $result->get_error_code());
    }

    #[Test]
    public function test_invite_user_rejects_rate_limit(): void
    {
        global $wpdb;

        $wpdb->test_get_var_callback = fn() => null;
        $GLOBALS['orabooks_test_rate_limit_allowed'] = false;

        $result = OraBooks_Team::invite_user(10, 'new@example.com', 'staff', 1);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('rate_limit', $result->get_error_code());
    }

    #[Test]
    public function test_invite_user_creates_invite_and_returns_link(): void
    {
        global $wpdb;

        $inserted = [];
        $wpdb->test_get_var_callback = fn() => null;
        $wpdb->test_insert_callback = function ($table, $data) use (&$inserted) {
            $inserted[] = [$table, $data];
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 501;

        $result = OraBooks_Team::invite_user(10, 'new@example.com', 'admin', 1);

        $this->assertIsArray($result);
        $this->assertSame(501, $result['invite_id']);
        $this->assertStringContainsString('accept-invite', $result['invite_link']);
        $this->assertNotEmpty(array_filter(
            $inserted,
            fn($row) => str_contains($row[0], 'org_invites')
                && $row[1]['email'] === 'new@example.com'
                && $row[1]['role'] === 'admin'
        ));
        $this->assertNotEmpty(array_filter(
            $GLOBALS['orabooks_test_log_events'],
            fn($event) => $event['event_type'] === 'invite_sent'
        ));
    }

    #[Test]
    public function test_preview_invite_rejects_empty_token(): void
    {
        $result = OraBooks_Team::preview_invite('');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_invite', $result->get_error_code());
    }

    #[Test]
    public function test_preview_invite_returns_details(): void
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function () {
            return (object) [
                'email' => 'invitee@example.com',
                'role' => 'viewer',
                'org_id' => 42,
                'expires_at' => '2099-01-01 00:00:00',
            ];
        };

        $result = OraBooks_Team::preview_invite('valid-token');

        $this->assertIsArray($result);
        $this->assertSame('invitee@example.com', $result['email']);
        $this->assertSame('viewer', $result['role']);
        $this->assertSame(42, $result['org_id']);
        $this->assertSame('Test Customer Org', $result['org_name']);
    }

    #[Test]
    public function test_accept_invite_rejects_invalid_token(): void
    {
        global $wpdb;

        $wpdb->test_get_row_callback = fn() => null;
        $wpdb->test_query_callback = fn() => 1;

        $result = OraBooks_Team::accept_invite('missing-token');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_invite', $result->get_error_code());
    }

    #[Test]
    public function test_accept_invite_rejects_email_mismatch(): void
    {
        global $wpdb;

        $invite = (object) [
            'id' => 3,
            'org_id' => 10,
            'email' => 'invitee@example.com',
            'role' => 'staff',
        ];
        $user = (object) [
            'id' => 5,
            'email' => 'invitee@example.com',
            'org_id' => null,
        ];

        $wpdb->test_get_row_callback = function ($query) use ($invite, $user) {
            if (stripos($query, 'org_invites') !== false) {
                return $invite;
            }
            if (stripos($query, 'users') !== false) {
                return $user;
            }

            return null;
        };
        $wpdb->test_get_var_callback = fn() => null;
        $wpdb->test_query_callback = fn() => 1;

        $result = OraBooks_Team::accept_invite('token-abc', 99);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invite_email_mismatch', $result->get_error_code());
    }

    #[Test]
    public function test_accept_pending_invite_for_user_assigns_invited_role(): void
    {
        global $wpdb;

        $invite = (object) [
            'id' => 9,
            'org_id' => 42,
            'email' => 'staff@example.com',
            'role' => 'staff',
        ];
        $user = (object) [
            'id' => 7,
            'email' => 'staff@example.com',
            'org_id' => null,
        ];

        $inserted = [];
        $wpdb->test_get_row_callback = function ($query) use ($invite, $user) {
            if (stripos($query, 'org_invites') !== false) {
                return $invite;
            }
            if (stripos($query, 'users') !== false) {
                return $user;
            }

            return null;
        };
        $wpdb->test_get_var_callback = fn() => null;
        $wpdb->test_insert_callback = function ($table, $data) use (&$inserted) {
            $inserted[] = [$table, $data];
        };
        $wpdb->test_update_callback = function () {
            return 1;
        };
        $wpdb->test_query_callback = fn() => 1;

        $result = OraBooks_Team::accept_pending_invite_for_user(7);

        $this->assertIsArray($result);
        $this->assertSame(42, $result['org_id']);
        $this->assertSame('staff', $result['role']);
        $this->assertSame(7, $result['user_id']);
        $this->assertNotEmpty(array_filter(
            $inserted,
            fn($row) => str_contains($row[0], 'user_org') && $row[1]['role'] === 'staff'
        ));
    }

    #[Test]
    public function test_update_role_rejects_self_change(): void
    {
        $result = OraBooks_Team::update_role(10, 5, 'admin', 5);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('self_change', $result->get_error_code());
    }

    #[Test]
    public function test_update_role_rejects_demoting_last_owner(): void
    {
        global $wpdb;

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, "role = 'owner'") !== false) {
                return 1;
            }
            if (stripos($query, 'SELECT role') !== false) {
                return 'owner';
            }

            return null;
        };

        $result = OraBooks_Team::update_role(10, 20, 'admin', 1);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('last_owner', $result->get_error_code());
    }

    #[Test]
    public function test_update_role_revokes_refresh_tokens_on_success(): void
    {
        global $wpdb;

        $queries = [];
        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, "role = 'owner'") !== false) {
                return 2;
            }
            if (stripos($query, 'SELECT role') !== false) {
                return 'staff';
            }

            return 1;
        };
        $wpdb->test_query_callback = function ($query) use (&$queries) {
            $queries[] = $query;

            return 1;
        };

        $result = OraBooks_Team::update_role(10, 20, 'admin', 1);

        $this->assertTrue($result);
        $this->assertNotEmpty(array_filter(
            $queries,
            fn($query) => str_contains($query, 'orabooks_refresh_tokens') && str_contains($query, 'revoked_at')
        ));
    }

    #[Test]
    public function test_remove_user_rejects_self_remove(): void
    {
        $result = OraBooks_Team::remove_user(10, 5, 5);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('self_remove', $result->get_error_code());
    }

    #[Test]
    public function test_remove_user_rejects_last_owner(): void
    {
        global $wpdb;

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, "role = 'owner'") !== false) {
                return 1;
            }
            if (stripos($query, 'SELECT role') !== false) {
                return 'owner';
            }

            return null;
        };

        $result = OraBooks_Team::remove_user(10, 20, 1);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('last_owner', $result->get_error_code());
    }

    #[Test]
    public function test_remove_user_deletes_membership_and_revokes_tokens(): void
    {
        global $wpdb;

        $deleted = [];
        $queries = [];
        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, "role = 'owner'") !== false) {
                return 2;
            }
            if (stripos($query, 'SELECT role') !== false) {
                return 'staff';
            }

            return null;
        };
        $wpdb->test_delete_callback = function ($table, $where) use (&$deleted) {
            $deleted[] = [$table, $where];

            return 1;
        };
        $wpdb->test_query_callback = function ($query) use (&$queries) {
            $queries[] = $query;

            return 1;
        };

        $result = OraBooks_Team::remove_user(10, 20, 1);

        $this->assertTrue($result);
        $this->assertNotEmpty(array_filter($deleted, fn($row) => str_contains($row[0], 'user_org')));
        $this->assertNotEmpty(array_filter(
            $queries,
            fn($query) => str_contains($query, 'orabooks_refresh_tokens')
        ));
        $this->assertNotEmpty(array_filter(
            $GLOBALS['orabooks_test_log_events'],
            fn($event) => $event['event_type'] === 'user_removed'
        ));
    }

    #[Test]
    public function test_cleanup_expired_invites_deletes_rows(): void
    {
        global $wpdb;

        $last_query = '';
        $wpdb->test_query_callback = function ($query) use (&$last_query) {
            $last_query = $query;

            return 4;
        };

        $deleted = OraBooks_Team::cleanup_expired_invites();

        $this->assertSame(4, $deleted);
        $this->assertStringContainsString('DELETE FROM', $last_query);
        $this->assertStringContainsString('expires_at < NOW()', $last_query);
    }

    #[Test]
    public function test_get_team_stats_counts_members_and_invites(): void
    {
        $members = [
            (object) ['role' => 'owner'],
            (object) ['role' => 'staff'],
            (object) ['role' => 'staff'],
        ];
        $invites = [
            (object) ['id' => 1],
            (object) ['id' => 2],
        ];

        global $wpdb;
        $wpdb->test_get_results_callback = function ($query) use ($members, $invites) {
            if (stripos($query, 'user_org') !== false) {
                return $members;
            }
            if (stripos($query, 'org_invites') !== false) {
                return $invites;
            }

            return [];
        };

        $stats = OraBooks_Team::get_team_stats(10);

        $this->assertSame(3, $stats['total_members']);
        $this->assertSame(2, $stats['pending_invites']);
        $this->assertSame(1, $stats['by_role']['owner']);
        $this->assertSame(2, $stats['by_role']['staff']);
    }

    #[Test]
    public function test_format_member_and_format_invite(): void
    {
        $member = (object) [
            'id' => 12,
            'email' => 'user@example.com',
            'role' => 'approver',
            'joined_at' => '2026-01-01 00:00:00',
            'status' => 'active',
        ];
        $invite = (object) [
            'id' => 3,
            'email' => 'invite@example.com',
            'role' => 'viewer',
            'created_at' => '2026-01-02 00:00:00',
            'expires_at' => '2026-01-09 00:00:00',
        ];

        $formatted_member = OraBooks_Team::format_member($member);
        $formatted_invite = OraBooks_Team::format_invite($invite);

        $this->assertSame(12, $formatted_member['id']);
        $this->assertSame('approver', $formatted_member['role']);
        $this->assertSame(3, $formatted_invite['id']);
        $this->assertSame('viewer', $formatted_invite['role']);
    }

    #[Test]
    public function test_transfer_ownership_returns_501(): void
    {
        $team = OraBooks_Team::init();

        $payload = $this->captureJsonError(function () use ($team) {
            $team->ajax_transfer_ownership();
        });

        $this->assertStringContainsString('not implemented', strtolower($payload['message']));
    }

    #[Test]
    public function test_accept_invite_ajax_requires_login(): void
    {
        $GLOBALS['orabooks_test_current_user_id'] = 0;
        $_POST['token'] = 'sample-invite-token';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $team = OraBooks_Team::init();

        $payload = $this->captureJsonError(function () use ($team) {
            $team->ajax_accept_invite_nopriv();
        });

        $this->assertStringContainsString('log in', strtolower($payload['message']));
    }
}
