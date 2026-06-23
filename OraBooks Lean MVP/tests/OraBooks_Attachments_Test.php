<?php
/**
 * Unit Tests for OraBooks_Attachments
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Attachments_Test extends TestCase
{
 protected function setUp: void
 {
 parent::setUp;

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
 }

 #[Test]
 public function test_schema_defines_sl203_tables() {
 $sql = implode("\n", OraBooks_Attachments::get_create_table_sql);

 $this->assertStringContainsString('orabooks_attachments', $sql);
 $this->assertStringContainsString('orabooks_attachment_versions', $sql);
 $this->assertStringContainsString("ENUM('standard','legal_hold')", $sql);
 $this->assertStringContainsString("ENUM('pending','clean','infected','error')", $sql);
 }

 #[Test]
 public function test_resource_types_include_common_entities() {
 $this->assertContains('invoice', OraBooks_Attachments::RESOURCE_TYPES);
 $this->assertContains('bill', OraBooks_Attachments::RESOURCE_TYPES);
 $this->assertContains('inventory_item', OraBooks_Attachments::RESOURCE_TYPES);
 $this->assertContains('bank_account', OraBooks_Attachments::RESOURCE_TYPES);
 $this->assertContains('expense', OraBooks_Attachments::RESOURCE_TYPES);
 $this->assertContains('csv_import', OraBooks_Attachments::RESOURCE_TYPES);
 $this->assertContains('general', OraBooks_Attachments::RESOURCE_TYPES);
 }

 #[Test]
 public function test_format_version_maps_expected_fields() {
 $version = (object) [
 'id' => 9,
 'attachment_id' => 3,
 'version_number' => 2,
 'file_name' => 'receipt.pdf',
 'file_size' => 2048,
 'file_hash' => 'abc123',
 'mime_type' => 'application/pdf',
 'uploaded_by' => 1,
 'uploaded_at' => '2026-06-18 10:00:00',
 'virus_scan_status' => 'clean',
 ];

 $formatted = OraBooks_Attachments::format_version($version);

 $this->assertSame(9, $formatted['id']);
 $this->assertSame(3, $formatted['attachment_id']);
 $this->assertSame(2, $formatted['version_number']);
 $this->assertSame('receipt.pdf', $formatted['file_name']);
 $this->assertSame('clean', $formatted['virus_scan_status']);
 }

 #[Test]
 public function test_upload_rejects_invalid_resource_type() {
 $result = OraBooks_Attachments::upload_attachment(
 1,
 1,
 'not_a_real_type',
 10,
 'file.txt',
 'hello',
 'text/plain'
 );

 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('invalid_resource_type', $result->get_error_code());
 }

 #[Test]
 public function test_upload_rejects_empty_file() {
 $result = OraBooks_Attachments::upload_attachment(
 1,
 1,
 'general',
 10,
 'file.txt',
 '',
 'text/plain'
 );

 $this->assertInstanceOf(WP_Error::class, $result);
 $this->assertSame('invalid_file', $result->get_error_code());
 }

 #[Test]
 public function test_format_attachment_row_includes_current_version_fields() {
 $row = (object) [
 'id' => 5,
 'org_id' => 1,
 'resource_type' => 'invoice',
 'resource_id' => 42,
 'current_version_id' => 9,
 'deleted_at' => null,
 'retention_class' => 'standard',
 'created_at' => '2026-06-18 09:00:00',
 'updated_at' => '2026-06-18 10:00:00',
 'version_id' => 9,
 'version_number' => 1,
 'file_name' => 'invoice.pdf',
 'file_size' => 4096,
 'file_hash' => 'hash123',
 'mime_type' => 'application/pdf',
 'uploaded_by' => 1,
 'uploaded_at' => '2026-06-18 10:00:00',
 'virus_scan_status' => 'clean',
 ];

 $formatted = OraBooks_Attachments::format_attachment_row($row);

 $this->assertSame(5, $formatted['id']);
 $this->assertSame('invoice.pdf', $formatted['file_name']);
 $this->assertSame(1, $formatted['version_number']);
 $this->assertSame('clean', $formatted['virus_scan_status']);
 }
}
