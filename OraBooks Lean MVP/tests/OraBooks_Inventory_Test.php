<?php
/**
 * Unit Tests for OraBooks_Inventory (SL-034)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Inventory_Test extends TestCase
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
        $wpdb->test_query_callback = null;
        $wpdb->insert_id = 0;
        $wpdb->last_query = '';
        $wpdb->last_result = [];
    }

    private function mockProduct(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 10,
            'org_id' => 5,
            'sku' => 'SKU-001',
            'name' => 'Test Product',
            'unit' => 'piece',
            'current_stock' => '10.0000',
            'average_cost' => '5.000000',
            'is_active' => 1,
        ], $overrides);
    }

    #[Test]
    public function test_get_create_table_sql_contains_inventory_tables()
    {
        $sql = implode("\n", OraBooks_Inventory::get_create_table_sql());

        $this->assertStringContainsString('orabooks_products', $sql);
        $this->assertStringContainsString('orabooks_inventory_movements', $sql);
        $this->assertStringContainsString('orabooks_inventory_lookups', $sql);
        $this->assertStringContainsString('UNIQUE KEY uk_org_sku', $sql);
        $this->assertStringContainsString("ENUM('opening','purchase','sale','adjustment')", $sql);
    }

    #[Test]
    public function test_create_product_rejects_duplicate_sku()
    {
        global $wpdb;

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'orabooks_products') !== false) {
                return 99;
            }
            return null;
        };

        $result = OraBooks_Inventory::create_product(5, [
            'sku' => 'SKU-001',
            'name' => 'Duplicate',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('duplicate_sku', $result->get_error_code());
    }

    #[Test]
    public function test_create_product_with_opening_stock_adds_movement()
    {
        global $wpdb;

        $movementInserted = false;
        $wpdb->test_get_var_callback = function ($query) {
            return 0;
        };
        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'orabooks_products') !== false) {
                return $this->mockProduct(['id' => 42, 'current_stock' => '25.0000', 'average_cost' => '4.000000']);
            }
            return null;
        };
        $wpdb->test_insert_callback = function ($table, $data) use (&$movementInserted) {
            if (stripos($table, 'inventory_movements') !== false) {
                $movementInserted = true;
                $this->assertEquals('opening', $data['reference_type']);
                $this->assertEquals(25.0, $data['quantity_change']);
            }
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 42;

        $product = OraBooks_Inventory::create_product(5, [
            'sku' => 'sku-001',
            'name' => 'Opening Product',
            'initial_stock' => 25,
            'initial_cost' => 4,
        ]);

        $this->assertIsObject($product);
        $this->assertEquals(42, $product->id);
        $this->assertTrue($movementInserted);
    }

    #[Test]
    public function test_receive_purchase_recalculates_weighted_average_cost()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'orabooks_products') !== false) {
                return $this->mockProduct([
                    'current_stock' => '10.0000',
                    'average_cost' => '5.000000',
                ]);
            }
            return null;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 77;

        $result = OraBooks_Inventory::receive_purchase(5, 10, 10, 7, 200, 1);

        $this->assertIsArray($result);
        $this->assertEquals(10.0, $result['stock_before']);
        $this->assertEquals(20.0, $result['stock_after']);
        $this->assertEquals(6.0, $result['average_cost']);
    }

    #[Test]
    public function test_record_sale_reduces_stock_and_returns_cogs()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            if (stripos($query, 'orabooks_products') !== false) {
                return $this->mockProduct([
                    'current_stock' => '10.0000',
                    'average_cost' => '5.000000',
                ]);
            }
            return null;
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 88;

        $result = OraBooks_Inventory::record_sale(5, 10, 3, 300, 1);

        $this->assertIsArray($result);
        $this->assertEquals(10.0, $result['stock_before']);
        $this->assertEquals(7.0, $result['stock_after']);
        $this->assertEquals(15.0, $result['cogs_amount']);
        $this->assertArrayHasKey('journal_id', $result);
    }

    #[Test]
    public function test_record_sale_blocks_negative_stock()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return $this->mockProduct(['current_stock' => '2.0000']);
        };

        $result = OraBooks_Inventory::record_sale(5, 10, 3, 300, 1);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('negative_stock', $result->get_error_code());
    }

    #[Test]
    public function test_adjust_stock_requires_reason()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return $this->mockProduct();
        };

        $result = OraBooks_Inventory::adjust_stock(5, 10, -1, '', 1);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('reason_required', $result->get_error_code());
    }

    #[Test]
    public function test_adjust_stock_updates_stock_and_logs_movement()
    {
        global $wpdb;

        $wpdb->test_get_row_callback = function ($query) {
            return $this->mockProduct(['current_stock' => '10.0000', 'average_cost' => '5.000000']);
        };
        $GLOBALS['orabooks_test_use_insert_id'] = 91;

        $result = OraBooks_Inventory::adjust_stock(5, 10, -2, 'Physical count', 1, 'Counted shelf stock');

        $this->assertIsArray($result);
        $this->assertEquals(10.0, $result['stock_before']);
        $this->assertEquals(8.0, $result['stock_after']);
        $this->assertEquals(91, $result['movement_id']);
    }

    #[Test]
    public function test_update_product_current_stock_persists_and_logs_adjustment_movement()
    {
        global $wpdb;

        $movementInserted = false;
        $productSelectCalls = 0;

        $wpdb->test_get_row_callback = function ($query) use (&$productSelectCalls) {
            if (stripos($query, 'orabooks_products') !== false) {
                $productSelectCalls++;
                if ($productSelectCalls === 1) {
                    return $this->mockProduct([
                        'id' => 10,
                        'org_id' => 5,
                        'sku' => 'SKU-001',
                        'name' => 'Widget',
                        'current_stock' => '10.0000',
                        'average_cost' => '5.000000',
                    ]);
                }

                return $this->mockProduct([
                    'id' => 10,
                    'org_id' => 5,
                    'sku' => 'SKU-001',
                    'name' => 'Widget Renamed',
                    'current_stock' => '14.0000',
                    'average_cost' => '5.000000',
                ]);
            }
            return null;
        };

        $wpdb->test_insert_callback = function ($table, $data) use (&$movementInserted) {
            if (stripos($table, 'inventory_movements') !== false) {
                $movementInserted = true;
                $this->assertEquals(4.0, $data['quantity_change']);
                $this->assertEquals(10.0, $data['stock_before']);
                $this->assertEquals(14.0, $data['stock_after']);
                $this->assertEquals('adjustment', $data['reference_type']);
            }
        };

        $result = OraBooks_Inventory::update_product(5, 10, [
            'name' => 'Widget Renamed',
            'price' => '10',
            'current_stock' => '14',
        ]);

        $this->assertIsObject($result);
        $this->assertEquals('14.0000', $result->current_stock);
        $this->assertTrue($movementInserted);
    }

    #[Test]
    public function test_get_recent_movements_joins_product_details()
    {
        global $wpdb;

        $wpdb->test_get_results_callback = function ($query) {
            if (stripos($query, 'inventory_movements') !== false) {
                return [
                    (object) [
                        'id' => 1,
                        'product_id' => 10,
                        'quantity_change' => '5.0000',
                        'stock_after' => '15.0000',
                        'reference_type' => 'purchase',
                        'movement_value' => '25.00',
                        'created_at' => '2026-06-01 10:00:00',
                        'sku' => 'SKU-001',
                        'product_name' => 'Widget',
                    ],
                ];
            }
            return [];
        };

        $movements = OraBooks_Inventory::get_recent_movements(5, ['limit' => 10]);

        $this->assertCount(1, $movements);
        $this->assertEquals('Widget', $movements[0]->product_name);
        $this->assertEquals('SKU-001', $movements[0]->sku);
    }
}
