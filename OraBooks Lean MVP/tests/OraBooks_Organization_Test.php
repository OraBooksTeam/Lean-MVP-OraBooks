<?php
/**
 * SL-004 Multi-tenant & Residency tests.
 */

use PHPUnit\Framework\TestCase;

class OraBooks_Organization_Test extends TestCase
{
    /** @var wpdb */
    private $wpdb;

    protected function setUp(): void
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->wpdb->test_get_row_callback = null;
        $this->wpdb->test_get_var_callback = null;
        $this->wpdb->test_update_callback = null;
        $GLOBALS['orabooks_test_actions'] = [];
        $GLOBALS['orabooks_test_jwt_payload'] = null;
    }

    protected function tearDown(): void
    {
        $this->wpdb->test_get_row_callback = null;
        $this->wpdb->test_get_var_callback = null;
        $this->wpdb->test_update_callback = null;
        $GLOBALS['orabooks_test_current_user_can'] = true;
        unset($GLOBALS['orabooks_test_jwt_payload']);
    }

    public function test_validate_org_region_enterprise_requires_selection()
    {
        $this->assertSame('Please select a data residency region.', orabooks_validate_org_region('', 'enterprise'));
    }

    public function test_validate_org_region_rejects_invalid_enterprise_region()
    {
        $this->assertSame('Invalid region selected.', orabooks_validate_org_region('invalid', 'enterprise'));
    }

    public function test_validate_org_region_free_tier_cannot_customize()
    {
        $this->assertSame('Region cannot be changed for this plan.', orabooks_validate_org_region('eu-west-1', 'free'));
    }

    public function test_get_active_by_subdomain_rejects_inactive_org()
    {
        $this->wpdb->test_get_row_callback = function () {
            return (object) [
                'id' => 10,
                'subdomain' => 'inactive-co',
                'status' => 'suspended',
                'organization_type' => 'customer',
                'tier' => 'premium',
                'region' => 'us-east',
            ];
        };

        $result = OraBooks_Organization::get_active_by_subdomain('inactive-co');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('org_inactive', $result->get_error_code());
    }

    public function test_get_active_by_subdomain_returns_active_org()
    {
        $this->wpdb->test_get_row_callback = function () {
            return (object) [
                'id' => 11,
                'name' => 'Active Co',
                'subdomain' => 'active-co',
                'status' => 'active',
                'organization_type' => 'customer',
                'tier' => 'enterprise',
                'region' => 'eu-west-1',
            ];
        };

        $org = OraBooks_Organization::get_active_by_subdomain('active-co');
        $this->assertIsObject($org);
        $this->assertSame(11, (int) $org->id);
        $this->assertSame('active', $org->status);
    }

    public function test_change_region_rejects_non_enterprise_customer()
    {
        $this->wpdb->test_get_row_callback = function () {
            return (object) [
                'id' => 20,
                'subdomain' => 'premium-co',
                'status' => 'active',
                'organization_type' => 'customer',
                'tier' => 'premium',
                'region' => 'us-east',
            ];
        };

        $result = OraBooks_Organization::change_region(20, 'eu-west-1', 1);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_type', $result->get_error_code());
    }

    public function test_change_region_updates_enterprise_org_and_queues_migration()
    {
        $updated = null;
        $this->wpdb->test_get_row_callback = function () {
            return (object) [
                'id' => 21,
                'subdomain' => 'enterprise-co',
                'status' => 'active',
                'organization_type' => 'customer',
                'tier' => 'enterprise',
                'region' => 'us-east',
            ];
        };
        $this->wpdb->test_update_callback = function ($table, $data, $where) use (&$updated) {
            $updated = compact('table', 'data', 'where');
            return 1;
        };

        $result = OraBooks_Organization::change_region(21, 'ap-southeast-1', 99);
        $this->assertTrue($result);
        $this->assertNotNull($updated);
        $this->assertSame('ap-southeast-1', $updated['data']['region']);
    }

    public function test_user_belongs_to_org_checks_membership_and_owner()
    {
        $this->wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'orabooks_user_org') !== false) {
                return null;
            }
            if (stripos($query, 'owner_id') !== false) {
                return 1;
            }
            return null;
        };

        $this->assertTrue(orabooks_user_belongs_to_org(5, 7));
    }

    public function test_assert_tenant_access_blocks_cross_tenant_org()
    {
        $GLOBALS['orabooks_test_current_user_can'] = false;
        $this->wpdb->test_get_var_callback = function () {
            return null;
        };

        $result = orabooks_assert_tenant_access(5, 99);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('tenant_isolation', $result->get_error_code());
    }

    public function test_resolve_request_org_id_blocks_foreign_org_id()
    {
        $GLOBALS['orabooks_test_current_user_can'] = false;
        $this->wpdb->test_get_var_callback = function () {
            return null;
        };

        $org_id = orabooks_resolve_request_org_id(5, 99);
        $this->assertSame(0, $org_id);
    }

    public function test_resolve_request_org_id_allows_member_org()
    {
        $this->wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'orabooks_user_org') !== false) {
                return 1;
            }
            return null;
        };

        $org_id = orabooks_resolve_request_org_id(5, 12);
        $this->assertSame(12, $org_id);
    }

    public function test_resolve_auth_org_id_prefers_latest_membership_without_id_column_dependency()
    {
        $membershipQuery = '';

        $this->wpdb->test_get_var_callback = function ($query) use (&$membershipQuery) {
            if (stripos($query, 'orabooks_user_org') !== false) {
                $membershipQuery = (string) $query;
                return 77;
            }

            return null;
        };

        $org_id = orabooks_resolve_auth_org_id(5, 0);

        $this->assertSame(77, $org_id);
        $this->assertStringContainsString('ORDER BY joined_at DESC', $membershipQuery);
        $this->assertStringNotContainsString('id DESC', $membershipQuery);
    }
}
