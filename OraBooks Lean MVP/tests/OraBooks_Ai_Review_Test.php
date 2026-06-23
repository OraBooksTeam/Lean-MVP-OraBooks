<?php
/**
 * Unit Tests for OraBooks_Ai_Review
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Ai_Review_Test extends TestCase
{
 #[Test]
 public function test_schema_defines_sl076_tables() {
 $sql = implode("\n", OraBooks_Ai_Review::get_create_table_sql);

 $this->assertStringContainsString('orabooks_ai_review_queue', $sql);
 $this->assertStringContainsString('orabooks_ai_review_history', $sql);
 $this->assertStringContainsString('orabooks_ai_review_dead_letters', $sql);
 $this->assertStringContainsString("ENUM('pending','processing','escalated','resolved')", $sql);
 }

 #[Test]
 public function test_passes_threshold_requires_confidence_and_low_risk() {
 $this->assertTrue(OraBooks_Ai_Review::passes_threshold([
 'confidence' => 75,
 'risk_level' => 'low',
 ]));

 $this->assertFalse(OraBooks_Ai_Review::passes_threshold([
 'confidence' => 69,
 'risk_level' => 'low',
 ]));

 $this->assertFalse(OraBooks_Ai_Review::passes_threshold([
 'confidence' => 80,
 'risk_level' => 'medium',
 ]));
 }

 #[Test]
 public function test_format_queue_item_maps_expected_fields() {
 $row = (object) [
 'id' => 5,
 'org_id' => 1,
 'resource_type' => 'journal',
 'resource_id' => 10,
 'journal_id' => 10,
 'journal_number' => 'JE-2026-000010',
 'confidence_score' => 62.5,
 'risk_level' => 'medium',
 'escalation_reason' => 'low_confidence',
 'explanation' => 'High-value transaction',
 'total_amount' => 75000,
 'priority_score' => 120,
 'status' => 'escalated',
 'retry_count' => 3,
 'created_at' => '2026-06-18 09:00:00',
 'updated_at' => '2026-06-18 09:05:00',
 ];

 $formatted = OraBooks_Ai_Review::format_queue_item($row);

 $this->assertSame(5, $formatted['id']);
 $this->assertSame('escalated', $formatted['status']);
 $this->assertSame('JE-2026-000010', $formatted['journal_number']);
 $this->assertSame(62.5, $formatted['confidence_score']);
 $this->assertSame('medium', $formatted['risk_level']);
 }

 #[Test]
 public function test_evaluate_journal_flags_high_value_entries() {
 global $wpdb;

 $wpdb->test_get_row_callback = function ($query) {
 if (strpos($query, 'journals') !== false) {
 return (object) [
 'id' => 1,
 'org_id' => 1,
 'total_amount' => 100000,
 ];
 }
 return null;
 };

 $wpdb->test_get_results_callback = function {
 return [
 (object) ['description' => 'Office supplies'],
 (object) ['description' => 'Cash'],
 ];
 };

 $evaluation = OraBooks_Ai_Review::evaluate_journal(1, 1);

 $this->assertLessThan(OraBooks_Ai_Review::CONFIDENCE_THRESHOLD, $evaluation['confidence']);
 $this->assertContains($evaluation['risk_level'], ['medium', 'high']);
 }
}
