<?php
/**
 * Unit Tests for OraBooks_Tax (SL-305)
 *
 * Covers tax schema SQL, default jurisdiction calculation, org config,
 * override governance, and immutable transaction snapshots.
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Tax_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['orabooks_test_current_user_id'] = 1;
        $GLOBALS['orabooks_test_use_insert_id'] = null;

        $_POST = [];
        $_GET = [];

        global $wpdb;
        $wpdb->test_get_var_callback = null;
        $wpdb->test_get_row_callback = null;
        $wpdb->test_get_results_callback = null;
        $wpdb->test_insert_callback = null;
        $wpdb->insert_id = 0;
        $wpdb->last_query = '';
        $wpdb->last_result = [];
    }

    #[Test]
    public function test_get_create_table_sql_contains_required_tax_tables()
    {
        $sql = implode("\n", OraBooks_Tax::get_create_table_sql());

        $this->assertStringContainsString('orabooks_tax_configs', $sql);
        $this->assertStringContainsString('orabooks_tax_jurisdictions', $sql);
        $this->assertStringContainsString('orabooks_tax_snapshots', $sql);
        $this->assertStringContainsString('UNIQUE KEY uk_transaction', $sql);
    }

    #[Test]
    public function test_calculate_uses_default_jurisdiction_rule()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'tax_configs') !== false) {
                return null;
            }
            if (stripos($query, 'tax_jurisdictions') !== false) {
                return (object) [
                    'tax_rules' => json_encode([
                        'default_rate' => 15,
                        'tax_type' => 'VAT',
                    ]),
                ];
            }
            return null;
        };

        $result = OraBooks_Tax::calculate([
            'org_id' => 5,
            'amount' => 1000,
            'jurisdiction' => 'BD',
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(15.0, $result['tax_rate']);
        $this->assertEquals(150.0, $result['tax_amount']);
        $this->assertEquals('BD', $result['jurisdiction_applied']);
        $this->assertEquals('VAT', $result['tax_type']);
    }

    #[Test]
    public function test_calculate_prefers_active_org_config()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'tax_configs') !== false) {
                return (object) [
                    'id' => 22,
                    'default_tax_rate' => '7.2500',
                    'tax_type' => 'Sales Tax',
                    'override_reasons' => null,
                ];
            }
            return null;
        };

        $result = OraBooks_Tax::calculate([
            'org_id' => 7,
            'amount' => 200,
            'jurisdiction' => 'US-CA',
        ]);

        $this->assertEquals(7.25, $result['tax_rate']);
        $this->assertEquals(14.5, $result['tax_amount']);
        $this->assertEquals('org_config_22', $result['rule_id']);
    }

    #[Test]
    public function test_calculate_exempt_customer_returns_zero_tax()
    {
        $result = OraBooks_Tax::calculate([
            'org_id' => 5,
            'amount' => 500,
            'jurisdiction' => 'IN',
            'customer_tax_status' => 'exempt',
        ]);

        $this->assertEquals(0.0, $result['tax_rate']);
        $this->assertEquals(0.0, $result['tax_amount']);
        $this->assertEquals('customer_exempt', $result['rule_id']);
    }

    #[Test]
    public function test_create_snapshot_requires_override_reason()
    {
        $result = OraBooks_Tax::create_snapshot([
            'org_id' => 5,
            'transaction_id' => 100,
            'transaction_type' => 'invoice',
            'amount' => 100,
            'jurisdiction' => 'US',
            'override' => true,
            'override_tax_rate' => 5,
        ], 1);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('override_reason_required', $result->get_error_code());
    }

    #[Test]
    public function test_create_snapshot_inserts_override_snapshot()
    {
        global $wpdb;

        $captured = [];
        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'tax_configs') !== false) {
                return (object) [
                    'id' => 1,
                    'default_tax_rate' => '6.0000',
                    'tax_type' => 'Sales Tax',
                    'override_reasons' => json_encode(['LOCAL_TAX_RULE']),
                ];
            }
            return null;
        };
        $wpdb->test_insert_callback = function ($table, $data, $format) use (&$captured) {
            $captured = [
                'table' => $table,
                'data' => $data,
                'format' => $format,
            ];
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 77;

        $result = OraBooks_Tax::create_snapshot([
            'org_id' => 5,
            'transaction_id' => 100,
            'transaction_type' => 'invoice',
            'amount' => 1000,
            'jurisdiction' => 'US',
            'override' => true,
            'override_tax_rate' => 8.25,
            'override_reason' => 'LOCAL_TAX_RULE',
        ], 3);

        $this->assertIsArray($result);
        $this->assertEquals(77, $result['snapshot_id']);
        $this->assertEquals('wp_test_orabooks_tax_snapshots', $captured['table']);
        $this->assertEquals(82.5, $captured['data']['tax_amount']);
        $this->assertEquals('LOCAL_TAX_RULE', $captured['data']['override_reason']);
        $this->assertEquals(3, $captured['data']['calculated_by']);
    }

    #[Test]
    public function test_save_config_updates_existing_config()
    {
        global $wpdb;

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'tax_configs') !== false) {
                return 123;
            }
            return null;
        };
        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'WHERE id') !== false) {
                return (object) [
                    'id' => 123,
                    'org_id' => 5,
                    'jurisdiction' => 'BD',
                    'default_tax_rate' => '15.0000',
                    'tax_type' => 'VAT',
                    'is_active' => 1,
                ];
            }
            return null;
        };

        $result = OraBooks_Tax::save_config(5, [
            'jurisdiction' => 'bd',
            'default_tax_rate' => 15,
            'tax_type' => 'VAT',
            'is_active' => 1,
        ], 1);

        $this->assertIsObject($result);
        $this->assertEquals(123, $result->id);
        $this->assertStringContainsString('UPDATE wp_test_orabooks_tax_configs', $wpdb->last_query);
    }
}
