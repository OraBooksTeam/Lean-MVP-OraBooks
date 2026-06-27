<?php
/**
 * Unit Tests for SL-003 RBAC / ABAC.
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-orabooks-team.php';

class OraBooks_RBAC_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        OraBooks_RBAC::init();

        global $wpdb;
        $wpdb->test_get_var_callback = null;
        $wpdb->test_get_row_callback = null;
        $wpdb->test_get_results_callback = null;
        $wpdb->test_query_callback = null;
        $wpdb->test_insert_callback = null;
        $wpdb->test_update_callback = null;
        $wpdb->insert_id = 0;
        $wpdb->last_query = '';
        $wpdb->last_result = [];

        $GLOBALS['orabooks_test_log_events'] = [];
        $GLOBALS['orabooks_test_org_callback'] = null;
        $GLOBALS['orabooks_test_get_user_role_callback'] = null;
    }

    #[Test]
    public function test_fixed_roles_and_deny_by_default()
    {
        $this->assertSame(['owner', 'admin', 'approver', 'staff', 'viewer'], OraBooks_RBAC::get_roles());
        $this->assertFalse(OraBooks_RBAC::check_permission('owner', 'undefined_permission', 10));
    }

    #[Test]
    public function test_permission_aliases_match_final_spec()
    {
        $this->assertTrue(OraBooks_RBAC::check_permission('owner', 'manage_roles', 10));
        $this->assertFalse(OraBooks_RBAC::check_permission('admin', 'manage_roles', 10));
        $this->assertTrue(OraBooks_RBAC::check_permission('admin', 'manage_employees', 10));
        $this->assertTrue(OraBooks_RBAC::check_permission('admin', 'manage_settings', 10));
    }

    #[Test]
    public function test_partner_commission_access_can_be_enabled_for_staff_viewer()
    {
        $GLOBALS['orabooks_test_org_callback'] = function () {
            return (object) [
                'id' => 10,
                'status' => 'active',
                'organization_type' => 'partner',
                'partner_commission_for_staff_viewer' => 1,
            ];
        };

        $this->assertTrue(OraBooks_RBAC::check_permission('staff', 'partner_commission_access', 10));
        $this->assertTrue(OraBooks_RBAC::check_permission('viewer', 'partner_commission_access', 10));
    }

    #[Test]
    public function test_require_permission_blocks_partner_accounting_even_for_owner()
    {
        $GLOBALS['orabooks_test_org_callback'] = function () {
            return (object) [
                'id' => 10,
                'status' => 'active',
                'organization_type' => 'partner',
                'partner_commission_for_staff_viewer' => 0,
            ];
        };
        $GLOBALS['orabooks_test_get_user_role_callback'] = fn() => 'owner';

        $this->assertFalse(OraBooks_RBAC::require_permission(5, 10, 'submit_transaction'));
        $this->assertSame('permission_denied', $GLOBALS['orabooks_test_log_events'][0]['event_type']);
    }

    #[Test]
    public function test_require_permission_allows_platform_admin_when_option_enabled()
    {
        $GLOBALS['orabooks_test_org_callback'] = function () {
            return (object) [
                'id' => 10,
                'status' => 'active',
                'organization_type' => 'customer',
            ];
        };
        $GLOBALS['orabooks_test_get_user_role_callback'] = fn() => 'platform_admin';

        $this->assertTrue(OraBooks_RBAC::require_permission(5, 10, 'change_role', ['allowSuperAdmin' => true]));
        $this->assertFalse(OraBooks_RBAC::require_permission(5, 10, 'change_role'));
    }

    #[Test]
    public function test_require_permission_logs_dedicated_permission_audit_row()
    {
        global $wpdb;

        $inserted = [];
        $wpdb->test_insert_callback = function ($table, $data) use (&$inserted) {
            $inserted[] = [$table, $data];
        };
        $GLOBALS['orabooks_test_get_user_role_callback'] = fn() => 'viewer';

        $this->assertFalse(OraBooks_RBAC::require_permission(7, 10, 'approve_journal'));

        $audit = array_values(array_filter($inserted, fn($row) => str_contains($row[0], 'permission_audit_log')));
        $this->assertCount(1, $audit);
        $this->assertSame('permission_denied', $audit[0][1]['event_type']);
        $this->assertSame('approve_journal', $audit[0][1]['permission']);
        $this->assertSame('viewer', $audit[0][1]['role']);
    }

    #[Test]
    public function test_role_change_revokes_refresh_tokens()
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
        $this->assertNotEmpty(array_filter($queries, fn($query) => str_contains($query, 'orabooks_refresh_tokens') && str_contains($query, 'revoked_at')));
    }

    #[Test]
    public function test_effective_permissions_include_public_aliases()
    {
        $permissions = OBN_Access_Control::get_effective_permissions('owner', 10);

        $this->assertContains('manage_employees', $permissions);
        $this->assertContains('manage_roles', $permissions);
        $this->assertContains('manage_settings', $permissions);
    }

    #[Test]
    public function test_accept_invite_ajax_returns_json_for_unauthenticated_post()
    {
        $GLOBALS['orabooks_test_current_user_id'] = 0;
        $_POST['token'] = 'sample-invite-token';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $team = OraBooks_Team::init();

        try {
            $team->ajax_accept_invite_nopriv();
            $this->fail('Expected JSON error for unauthenticated invite acceptance.');
        } catch (\RuntimeException $exception) {
            $payload = json_decode($exception->getMessage(), true);
            $this->assertIsArray($payload);
            $this->assertTrue($payload['error']);
            $this->assertStringContainsString('log in', strtolower($payload['message']));
        }
    }

    #[Test]
    public function test_accept_pending_invite_for_user_assigns_invited_role()
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
        $updated = [];
        $wpdb->test_get_row_callback = function ($query) use ($invite, $user) {
            if (stripos($query, 'FROM') !== false && stripos($query, 'org_invites') !== false) {
                return $invite;
            }
            if (stripos($query, 'FROM') !== false && stripos($query, 'users') !== false) {
                return $user;
            }
            return null;
        };
        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'user_org') !== false) {
                return null;
            }
            return null;
        };
        $wpdb->test_insert_callback = function ($table, $data) use (&$inserted) {
            $inserted[] = [$table, $data];
            return 1;
        };
        $wpdb->test_update_callback = function ($table, $data) use (&$updated) {
            $updated[] = [$table, $data];
            return 1;
        };
        $wpdb->test_query_callback = function () {
            return 1;
        };

        $result = OraBooks_Team::accept_pending_invite_for_user(7);

        $this->assertIsArray($result);
        $this->assertSame(42, $result['org_id']);
        $this->assertSame('staff', $result['role']);
        $this->assertSame(7, $result['user_id']);
        $this->assertNotEmpty(array_filter($inserted, fn($row) => str_contains($row[0], 'user_org') && $row[1]['role'] === 'staff'));
    }
}
