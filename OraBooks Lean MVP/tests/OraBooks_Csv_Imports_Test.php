<?php
/**
 * Unit Tests for OraBooks_Csv_Imports (SL-113)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Csv_Imports_Test extends TestCase
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

        if (class_exists('OraBooks_RBAC')) {
            OraBooks_RBAC::init();
        }
    }

    #[Test]
    public function test_schema_defines_sl113_tables()
    {
        $sql = implode("\n", OraBooks_Csv_Imports::get_create_table_sql());

        $this->assertStringContainsString('orabooks_csv_imports', $sql);
        $this->assertStringContainsString('orabooks_csv_import_rows', $sql);
        $this->assertStringContainsString("ENUM('uploaded','parsing','mapping','pending_confirm','confirmed','failed')", $sql);
        $this->assertStringContainsString("ENUM('pending','processed','failed','escalated')", $sql);
    }

    #[Test]
    public function test_parse_csv_content_extracts_headers_and_rows()
    {
        $csv = "SKU,Name,Stock\nABC-1,Widget,10\nABC-2,Gadget,5\n";
        $result = OraBooks_Csv_Imports::parse_csv_content($csv);

        $this->assertIsArray($result);
        $this->assertEquals(['SKU', 'Name', 'Stock'], $result['headers']);
        $this->assertCount(2, $result['rows']);
        $this->assertEquals(2, $result['row_count']);
        $this->assertEquals('Widget', $result['rows'][0][1]);
    }

    #[Test]
    public function test_suggest_header_mapping_for_inventory()
    {
        $headers = ['Product SKU', 'Product Name', 'Quantity'];
        $mapping = OraBooks_Csv_Imports::suggest_header_mapping($headers, 'inventory_item');

        $this->assertArrayHasKey('0', $mapping);
        $this->assertEquals('sku', $mapping['0']);
        $this->assertEquals('name', $mapping['1']);
        $this->assertEquals('initial_stock', $mapping['2']);
    }

    #[Test]
    public function test_compute_row_confidence_high_for_complete_inventory_row()
    {
        $parsed = [
            'sku'  => 'SKU-100',
            'name' => 'Test Product',
        ];

        $confidence = OraBooks_Csv_Imports::compute_row_confidence($parsed, 'inventory_item');

        $this->assertGreaterThanOrEqual(70, $confidence['avg']);
        $this->assertEmpty($confidence['risks']);
    }

    #[Test]
    public function test_compute_row_confidence_low_when_required_fields_missing()
    {
        $parsed = ['name' => 'Only Name'];
        $confidence = OraBooks_Csv_Imports::compute_row_confidence($parsed, 'inventory_item');

        $this->assertLessThan(70, $confidence['avg']);
        $this->assertContains('missing_sku', $confidence['risks']);
    }

    #[Test]
    public function test_normalize_parsed_row_maps_amount_alias()
    {
        $normalized = OraBooks_Csv_Imports::normalize_parsed_row([
            'vendor_name' => 'Acme',
            'amount'      => 99.5,
        ], 'expense');

        $this->assertEquals(99.5, $normalized['total_amount']);
    }

    #[Test]
    public function test_apply_mapping_builds_parsed_fields()
    {
        $raw = ['col0' => 'SKU-1', 'col1' => 'Chair', 'col2' => '12'];
        $mapping = ['0' => 'sku', '1' => 'name', '2' => 'initial_stock'];

        $parsed = OraBooks_Csv_Imports::apply_mapping($raw, $mapping, 'inventory_item');

        $this->assertEquals('SKU-1', $parsed['sku']);
        $this->assertEquals('Chair', $parsed['name']);
        $this->assertEquals(12.0, $parsed['initial_stock']);
    }

    #[Test]
    public function test_confirm_import_rejects_duplicate_confirm_key()
    {
        global $wpdb;

        $GLOBALS['orabooks_test_has_permission'] = true;

        $import = (object) [
            'id'                       => 10,
            'org_id'                   => 1,
            'user_id'                  => 1,
            'resource_type'            => 'inventory_item',
            'status'                   => 'pending_confirm',
            'confirm_idempotency_key'  => null,
            'header_mapping'         => '{}',
            'total_rows'               => 1,
        ];

        $wpdb->test_get_row_callback = function ($query) use ($import) {
            if (stripos($query, 'csv_imports') !== false) {
                return $import;
            }
            return (object) [
                'id'                => 1,
                'status'            => 'active',
                'organization_type' => 'customer',
                'owner_id'          => 1,
            ];
        };

        $wpdb->test_get_var_callback = function ($query) {
            if (stripos($query, 'confirm_idempotency_key') !== false) {
                return 99;
            }
            if (stripos($query, 'user_org') !== false) {
                return 'owner';
            }
            return null;
        };

        $result = OraBooks_Csv_Imports::confirm_import(10, 1, 1, 'dup-key-123');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('duplicate_confirm', $result->get_error_code());
    }

    #[Test]
    public function test_resource_types_include_sl113_extended_types()
    {
        $types = OraBooks_Csv_Imports::RESOURCE_TYPES;
        $this->assertContains('journal', $types);
        $this->assertContains('price_list', $types);
        $this->assertContains('payroll', $types);
    }

    #[Test]
    public function test_get_mappable_field_options_for_journal()
    {
        $options = OraBooks_Csv_Imports::get_mappable_field_options('journal');
        $ids = array_column($options, 'id');
        $this->assertContains('debit_account', $ids);
        $this->assertContains('total_amount', $ids);
    }

    #[Test]
    public function test_compute_row_confidence_flags_unusual_tax_ratio()
    {
        $parsed = [
            'vendor_name'  => 'Acme',
            'total_amount' => 100,
            'tax_amount'   => 80,
        ];
        $confidence = OraBooks_Csv_Imports::compute_row_confidence($parsed, 'expense');
        $this->assertContains('tax_amount_unusual', $confidence['risks']);
    }

    #[Test]
    public function test_suggest_header_mapping_for_journal_debit_column()
    {
        $headers = ['Date', 'Debit Account', 'Amount', 'Memo'];
        $mapping = OraBooks_Csv_Imports::suggest_header_mapping($headers, 'journal');
        $this->assertEquals('debit_account', $mapping['1'] ?? null);
        $this->assertEquals('total_amount', $mapping['2'] ?? null);
    }
}
