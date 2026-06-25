<?php
/**
 * Unit Tests for OraBooks_Ai_Providers (SL-028, SL-052, SL-021)
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OraBooks_Ai_Providers_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['orabooks_test_secrets'] = [];
        $GLOBALS['orabooks_test_wp_remote_request_callback'] = null;
        $GLOBALS['orabooks_test_wp_remote_post_callback'] = null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['orabooks_test_secrets'] = [];
        $GLOBALS['orabooks_test_wp_remote_request_callback'] = null;
        $GLOBALS['orabooks_test_wp_remote_post_callback'] = null;

        parent::tearDown();
    }

    #[Test]
    public function test_provider_name_defaults_to_stub_without_credentials()
    {
        $this->assertSame('mvp-stub', OraBooks_Ai_Providers::provider_name('ocr'));
        $this->assertSame('mvp-stub', OraBooks_Ai_Providers::provider_name('speech'));
        $this->assertSame('mvp-stub', OraBooks_Ai_Providers::provider_name('classification'));
        $this->assertSame('mvp-stub-1.0', OraBooks_Ai_Providers::model_version('ocr'));
    }

    #[Test]
    public function test_provider_name_uses_azure_document_intelligence_when_configured()
    {
        $GLOBALS['orabooks_test_secrets'] = [
            'azure_document_intelligence_endpoint' => 'https://example.cognitiveservices.azure.com',
            'azure_document_intelligence_key'    => 'test-key',
        ];

        $this->assertSame('azure-document-intelligence', OraBooks_Ai_Providers::provider_name('ocr'));
        $this->assertSame('prebuilt-receipt-2023-07-31', OraBooks_Ai_Providers::model_version('ocr'));
    }

    #[Test]
    public function test_run_ocr_falls_back_to_stub_without_file_bytes()
    {
        $ocr = OraBooks_Ai_Providers::run_ocr([
            'filename'   => 'office-supplies.pdf',
            'expense_id' => 12,
            'file_bytes' => null,
        ]);

        $this->assertSame('mvp-stub', $ocr['provider']);
        $this->assertSame('mvp-stub-1.0', $ocr['model_version']);
        $this->assertNotEmpty($ocr['vendor']);
        $this->assertGreaterThan(0, $ocr['total_amount']);
    }

    #[Test]
    public function test_normalize_receipt_result_maps_azure_fields()
    {
        $payload = [
            'analyzeResult' => [
                'documents' => [[
                    'fields' => [
                        'MerchantName' => ['valueString' => 'Contoso Supplies', 'confidence' => 0.92],
                        'TransactionDate' => ['valueDate' => '2026-06-18', 'confidence' => 0.9],
                        'Total' => ['valueCurrency' => ['amount' => 105.0], 'confidence' => 0.88],
                        'TotalTax' => ['valueNumber' => 5.0, 'confidence' => 0.8],
                        'Subtotal' => ['valueNumber' => 100.0, 'confidence' => 0.85],
                        'TransactionId' => ['valueString' => 'INV-1001', 'confidence' => 0.77],
                    ],
                ]],
            ],
        ];

        $result = OraBooks_Ai_Providers::normalize_receipt_result($payload, 'receipt.jpg');

        $this->assertSame('Contoso Supplies', $result['vendor']);
        $this->assertSame(105.0, $result['total_amount']);
        $this->assertSame(100.0, $result['subtotal']);
        $this->assertSame(5.0, $result['tax_amount']);
        $this->assertSame('azure-document-intelligence', $result['provider']);
        $this->assertNotEmpty($result['line_items']);
    }

    #[Test]
    public function test_run_ocr_uses_document_intelligence_when_configured()
    {
        $GLOBALS['orabooks_test_secrets'] = [
            'azure_document_intelligence_endpoint' => 'https://example.cognitiveservices.azure.com',
            'azure_document_intelligence_key'    => 'test-key',
        ];

        $poll_payload = [
            'status' => 'succeeded',
            'analyzeResult' => [
                'documents' => [[
                    'fields' => [
                        'MerchantName' => ['valueString' => 'Azure Vendor', 'confidence' => 0.95],
                        'Total' => ['valueCurrency' => ['amount' => 42.5], 'confidence' => 0.9],
                    ],
                ]],
            ],
        ];

        $call = 0;
        $GLOBALS['orabooks_test_wp_remote_request_callback'] = function ($url, $args) use (&$call, $poll_payload) {
            $call++;
            if ($call === 1) {
                $this->assertSame('POST', $args['method']);
                return [
                    'response' => ['code' => 202],
                    'headers'  => ['operation-location' => 'https://example.cognitiveservices.azure.com/operations/1'],
                    'body'     => '',
                ];
            }

            return [
                'response' => ['code' => 200],
                'headers'  => [],
                'body'     => wp_json_encode($poll_payload),
            ];
        };

        $ocr = OraBooks_Ai_Providers::run_ocr([
            'filename'   => 'receipt.pdf',
            'expense_id' => 5,
            'file_bytes' => '%PDF-1.4 mock',
        ]);

        $this->assertSame('azure-document-intelligence', $ocr['provider']);
        $this->assertSame('Azure Vendor', $ocr['vendor']);
        $this->assertSame(42.5, $ocr['total_amount']);
    }

    #[Test]
    public function test_run_ocr_uses_prebuilt_document_model_for_salary_voucher_files()
    {
        $GLOBALS['orabooks_test_secrets'] = [
            'azure_document_intelligence_endpoint' => 'https://example.cognitiveservices.azure.com',
            'azure_document_intelligence_key'    => 'test-key',
            'azure_document_intelligence_model'  => 'prebuilt-receipt',
        ];

        $poll_payload = [
            'status' => 'succeeded',
            'analyzeResult' => [
                'documents' => [[
                    'fields' => [
                        'MerchantName' => ['valueString' => 'Voucher Vendor', 'confidence' => 0.9],
                        'Total' => ['valueCurrency' => ['amount' => 2900.0], 'confidence' => 0.88],
                    ],
                ]],
            ],
        ];

        $call = 0;
        $GLOBALS['orabooks_test_wp_remote_request_callback'] = function ($url, $args) use (&$call, $poll_payload) {
            $call++;
            if ($call === 1) {
                $this->assertStringContainsString('/documentModels/prebuilt-document:analyze', $url);
                return [
                    'response' => ['code' => 202],
                    'headers'  => ['operation-location' => 'https://example.cognitiveservices.azure.com/operations/3'],
                    'body'     => '',
                ];
            }

            return [
                'response' => ['code' => 200],
                'headers'  => [],
                'body'     => wp_json_encode($poll_payload),
            ];
        };

        $ocr = OraBooks_Ai_Providers::run_ocr([
            'filename'   => 'salary-voucher-jan.png',
            'expense_id' => 6,
            'file_bytes' => '%PDF-1.4 mock',
        ]);

        $this->assertSame('Voucher Vendor', $ocr['vendor']);
        $this->assertSame(2900.0, $ocr['total_amount']);
    }

    #[Test]
    public function test_run_ocr_merges_stub_when_document_intelligence_is_low_signal()
    {
        $GLOBALS['orabooks_test_secrets'] = [
            'azure_document_intelligence_endpoint' => 'https://example.cognitiveservices.azure.com',
            'azure_document_intelligence_key'    => 'test-key',
        ];

        $poll_payload = [
            'status' => 'succeeded',
            'analyzeResult' => [
                'documents' => [[
                    'fields' => [
                        // Intentionally weak output for non-receipt voucher templates.
                    ],
                ]],
            ],
        ];

        $call = 0;
        $GLOBALS['orabooks_test_wp_remote_request_callback'] = function ($url, $args) use (&$call, $poll_payload) {
            $call++;
            if ($call === 1) {
                return [
                    'response' => ['code' => 202],
                    'headers'  => ['operation-location' => 'https://example.cognitiveservices.azure.com/operations/2'],
                    'body'     => '',
                ];
            }

            return [
                'response' => ['code' => 200],
                'headers'  => [],
                'body'     => wp_json_encode($poll_payload),
            ];
        };

        $text = 'SALARY VOUCHER Company Name : ABC Garments Ltd. Date : 23-06-2026 Total Amount 60,000.00 Amount (BDT) 60,000.00';
        $ocr = OraBooks_Ai_Providers::run_ocr([
            'filename'   => 'salary-voucher.png',
            'expense_id' => 77,
            'file_bytes' => $text,
        ]);

        $this->assertSame('azure-document-intelligence', $ocr['provider']);
        $this->assertSame('ABC Garments Ltd.', $ocr['vendor']);
        $this->assertSame('BDT', $ocr['currency']);
        $this->assertSame('Salary', $ocr['category']);
        $this->assertGreaterThan(0, (float) $ocr['total_amount']);
    }

    #[Test]
    public function test_run_ocr_uses_vision_chat_fallback_for_images_when_openai_configured()
    {
        $GLOBALS['orabooks_test_secrets'] = [
            'openai_api_key' => 'test-openai-key',
            'openai_chat_model' => 'gpt-4o-mini',
        ];

        $GLOBALS['orabooks_test_wp_remote_request_callback'] = function ($url, $args) {
            $this->assertStringContainsString('/chat/completions', $url);
            return [
                'response' => ['code' => 200],
                'headers'  => [],
                'body'     => wp_json_encode([
                    'choices' => [[
                        'message' => [
                            'content' => wp_json_encode([
                                'vendor' => 'Jolie Cassin',
                                'invoice_number' => 'EXP-100',
                                'transaction_date' => '2060-01-30',
                                'total_amount' => 100,
                                'subtotal' => 100,
                                'tax_amount' => 0,
                                'tax_rate' => 0,
                                'currency' => 'USD',
                                'payment_method' => 'Credit Card',
                                'category' => 'Meals',
                                'description' => 'Business Lunch, Taxi Fare, Office Supplies',
                                'line_items' => [
                                    ['description' => 'Business Lunch', 'quantity' => 1, 'unit_price' => 50, 'total_amount' => 50, 'line_confidence' => 90],
                                    ['description' => 'Taxi Fare', 'quantity' => 1, 'unit_price' => 30, 'total_amount' => 30, 'line_confidence' => 88],
                                    ['description' => 'Office Supplies', 'quantity' => 1, 'unit_price' => 20, 'total_amount' => 20, 'line_confidence' => 86],
                                ],
                                'field_confidences' => [
                                    'vendor' => 91,
                                    'total_amount' => 94,
                                    'currency' => 93,
                                    'category' => 85,
                                ],
                            ]),
                        ],
                    ]],
                ]),
            ];
        };

        $ocr = OraBooks_Ai_Providers::run_ocr([
            'filename'   => 'expense-voucher.png',
            'expense_id' => 501,
            'file_bytes' => 'mock-image-bytes',
        ]);

        $this->assertSame('Jolie Cassin', $ocr['vendor']);
        $this->assertSame('USD', $ocr['currency']);
        $this->assertSame(100.0, $ocr['total_amount']);
        $this->assertSame('Meals', $ocr['category']);
        $this->assertContains($ocr['provider'], ['openai', 'azure-openai']);
        $this->assertNotEmpty($ocr['line_items']);
    }

    #[Test]
    public function test_classify_record_falls_back_to_stub_without_credentials()
    {
        $record = (object) ['category' => 'meals'];
        $suggestion = OraBooks_Ai_Providers::classify_record('expense', $record, 'team lunch', 85.0, 1);

        $this->assertSame('5200', $suggestion['account_code']);
        $this->assertSame('mvp-stub-1.0', $suggestion['model_version']);
    }

    #[Test]
    public function test_run_voice_nlu_falls_back_to_stub_without_audio()
    {
        $result = OraBooks_Ai_Providers::run_voice_nlu([
            'filename'   => 'recording.webm',
            'voice_id'   => 3,
            'file_bytes' => null,
        ]);

        $this->assertArrayHasKey('transcript', $result);
        $this->assertArrayHasKey('extracted_data', $result);
        $this->assertSame('mvp-stub', $result['provider']);
    }
}
