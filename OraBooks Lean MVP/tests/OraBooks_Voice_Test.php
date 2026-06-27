<?php
/**
 * Unit Tests for OraBooks_Voice (SL-052)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Voice_Test extends TestCase
{
    #[Test]
    public function test_schema_defines_sl052_table()
    {
        $sql = implode("\n", OraBooks_Voice::get_create_table_sql());

        $this->assertStringContainsString('orabooks_voice_inputs', $sql);
        $this->assertStringContainsString('overall_risk_level', $sql);
        $this->assertStringContainsString("ENUM('pending','processed','failed','escalated','dead_letter')", $sql);
        $this->assertStringContainsString('idx_org_status_created', $sql);
        $this->assertStringContainsString('idx_org_risk_created', $sql);
    }

    #[Test]
    public function test_contract_defines_extended_transaction_types_and_statuses()
    {
        $this->assertContains('support_ticket', OraBooks_Voice::TRANSACTION_TYPES);
        $this->assertContains('workflow_command', OraBooks_Voice::TRANSACTION_TYPES);
        $this->assertSame('dead_letter', OraBooks_Voice::STATUS_DEAD_LETTER);
    }

    #[Test]
    public function test_nlu_stub_returns_transcript_and_extracted_data()
    {
        $result = OraBooks_Voice::run_nlu_stub('expense-command.webm', 12);

        $this->assertNotEmpty($result['transcript']);
        $this->assertArrayHasKey('extracted_data', $result);
        $this->assertContains($result['extracted_data']['transaction_type'], OraBooks_Voice::TRANSACTION_TYPES);
        $this->assertGreaterThan(0, $result['extracted_data']['amount']);
        $this->assertArrayHasKey('confidence_avg', $result);
        $this->assertArrayHasKey('risk_scores', $result);
        $this->assertContains($result['overall_risk_level'], ['low', 'medium', 'high']);
    }

    #[Test]
    public function test_nlu_stub_elevates_risk_for_unclear_audio()
    {
        $result = OraBooks_Voice::run_nlu_stub('unclear-audio.webm', 1);

        $this->assertContains($result['overall_risk_level'], ['medium', 'high']);
        $this->assertLessThan(OraBooks_Voice::CONFIDENCE_THRESHOLD, $result['confidence_avg'] + 0.01);
    }

    #[Test]
    public function test_format_voice_input_maps_core_fields()
    {
        $row = (object) [
            'id' => 3,
            'org_id' => 1,
            'user_id' => 2,
            'audio_file_id' => 5,
            'audio_hash' => 'abc123',
            'original_transcript' => 'Create expense for vendor',
            'edited_transcript' => null,
            'extracted_data' => wp_json_encode([
                'transaction_type' => 'expense',
                'amount' => 120,
                '_voice_ai' => [
                    'provider' => 'speech-webhook',
                    'model_version' => 'faster-whisper-large-v3',
                ],
            ]),
            'language_detected' => 'en',
            'confidence_avg' => 88.5,
            'risk_scores' => wp_json_encode(['amount_risk' => 10]),
            'overall_risk_level' => 'low',
            'status' => 'processed',
            'derived_resource_type' => null,
            'derived_resource_id' => null,
            'created_at' => '2026-06-18 09:00:00',
            'updated_at' => '2026-06-18 09:00:00',
        ];

        $formatted = OraBooks_Voice::format_voice_input($row);

        $this->assertSame(3, $formatted['id']);
        $this->assertSame('processed', $formatted['status']);
        $this->assertSame('expense', $formatted['extracted_data']['transaction_type']);
        $this->assertSame(88.5, $formatted['confidence_avg']);
        $this->assertSame('speech-webhook', $formatted['ai_provider']);
        $this->assertSame('faster-whisper-large-v3', $formatted['ai_model_version']);
    }

    #[Test]
    public function test_compute_overall_risk_level_rules()
    {
        $this->assertSame('low', OraBooks_Voice::compute_overall_risk_level([
            'amount_risk' => 10,
            'vendor_risk' => 5,
        ], 88));

        $this->assertSame('medium', OraBooks_Voice::compute_overall_risk_level([
            'amount_risk' => 35,
        ], 75));

        $this->assertSame('high', OraBooks_Voice::compute_overall_risk_level([
            'amount_risk' => 75,
        ], 80));
    }

    #[Test]
    public function test_nlu_stub_includes_tax_and_due_date_fields()
    {
        $result = OraBooks_Voice::run_nlu_stub('expense-command.webm', 12);
        $extracted = $result['extracted_data'];

        $this->assertArrayHasKey('due_date', $extracted);
        $this->assertArrayHasKey('vendor_tax_id', $extracted);
        $this->assertArrayHasKey('subtotal', $extracted);
        $this->assertArrayHasKey('tax_registration_number', $extracted);
        $this->assertNotEmpty($extracted['line_items']);
    }
}
