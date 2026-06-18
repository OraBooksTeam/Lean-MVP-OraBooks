<?php
/**
 * OraBooks AI / OCR / Voice provider integrations (SL-028, SL-052, SL-021)
 *
 * Azure Document Intelligence (receipt OCR), OpenAI Whisper (speech),
 * Azure OpenAI / OpenAI chat (NLU + account classification).
 * Falls back to deterministic stubs when credentials are not configured.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Ai_Providers {

    const STUB_PROVIDER      = 'mvp-stub';
    const STUB_MODEL_VERSION = 'mvp-stub-1.0';

    const PROVIDER_AZURE_DI  = 'azure-document-intelligence';
    const PROVIDER_OPENAI    = 'openai';
    const PROVIDER_AZURE_OAI = 'azure-openai';

    /**
     * Active provider id for a capability: ocr | speech | classification.
     */
    public static function provider_name($capability) {
        switch ($capability) {
            case 'ocr':
                return self::is_document_intelligence_configured()
                    ? self::PROVIDER_AZURE_DI
                    : self::STUB_PROVIDER;
            case 'speech':
            case 'classification':
                if (self::is_azure_openai_configured()) {
                    return self::PROVIDER_AZURE_OAI;
                }
                if (self::is_openai_configured()) {
                    return self::PROVIDER_OPENAI;
                }
                return self::STUB_PROVIDER;
            default:
                return self::STUB_PROVIDER;
        }
    }

    public static function model_version($capability) {
        switch (self::provider_name($capability)) {
            case self::PROVIDER_AZURE_DI:
                return 'prebuilt-receipt-2023-07-31';
            case self::PROVIDER_OPENAI:
                return $capability === 'speech'
                    ? self::config('openai_whisper_model', 'whisper-1')
                    : self::config('openai_chat_model', 'gpt-4o-mini');
            case self::PROVIDER_AZURE_OAI:
                return self::config('azure_openai_deployment', 'gpt-4o-mini');
            default:
                return self::STUB_MODEL_VERSION;
        }
    }

    public static function is_document_intelligence_configured() {
        return self::config('azure_document_intelligence_endpoint') !== ''
            && self::config('azure_document_intelligence_key') !== '';
    }

    public static function is_openai_configured() {
        return self::config('openai_api_key') !== '';
    }

    public static function is_azure_openai_configured() {
        return self::config('azure_openai_endpoint') !== ''
            && self::config('azure_openai_key') !== ''
            && self::config('azure_openai_deployment') !== '';
    }

    public static function config($key, $default = '') {
        $env_key = 'ORABOOKS_' . strtoupper($key);
        $env = getenv($env_key);
        if ($env !== false && $env !== '') {
            return $env;
        }

        if (class_exists('OraBooks_Secrets')) {
            $secret = OraBooks_Secrets::get($key);
            if ($secret !== null && $secret !== '') {
                return $secret;
            }
        }

        return $default;
    }

    /**
     * Run receipt OCR — Azure Document Intelligence or MVP stub.
     *
     * @param array $context filename, expense_id, file_bytes (optional), mime_type
     */
    public static function run_ocr(array $context) {
        $filename = (string) ($context['filename'] ?? 'receipt.pdf');
        $expense_id = (int) ($context['expense_id'] ?? 0);
        $file_bytes = $context['file_bytes'] ?? null;

        if (self::is_document_intelligence_configured() && $file_bytes) {
            $result = self::azure_document_intelligence_ocr($file_bytes, $filename);
            if (!is_wp_error($result)) {
                return $result;
            }
            orabooks_log_event('ocr_provider_fallback', 'Document Intelligence failed; using stub', 'warning', [
                'error' => $result->get_error_message(),
                'expense_id' => $expense_id,
            ], null, null);
        }

        return OraBooks_Expenses::run_ocr_stub($filename, $expense_id);
    }

    /**
     * Transcribe + NLU for voice input.
     *
     * @param array $context filename, voice_id, file_bytes (optional), mime_type
     */
    public static function run_voice_nlu(array $context) {
        $filename = (string) ($context['filename'] ?? 'recording.webm');
        $voice_id = (int) ($context['voice_id'] ?? 0);
        $file_bytes = $context['file_bytes'] ?? null;

        if ($file_bytes && (self::is_openai_configured() || self::is_azure_openai_configured())) {
            $transcript = self::transcribe_audio($file_bytes, $filename, $context['mime_type'] ?? 'audio/webm');
            if (!is_wp_error($transcript) && $transcript !== '') {
                $nlu = self::extract_voice_intent($transcript);
                if (!is_wp_error($nlu)) {
                    $nlu['transcript'] = $transcript;
                    return $nlu;
                }
            }
            if (is_wp_error($transcript)) {
                orabooks_log_event('voice_provider_fallback', 'Speech transcription failed; using stub', 'warning', [
                    'error' => $transcript->get_error_message(),
                    'voice_id' => $voice_id,
                ], null, null);
            }
        }

        return OraBooks_Voice::run_nlu_stub($filename, $voice_id);
    }

    /**
     * Suggest GL account for expense/invoice/journal lines.
     */
    public static function classify_record($record_type, $record, $text, $amount, $org_id) {
        if (self::is_openai_configured() || self::is_azure_openai_configured()) {
            $accounts = self::org_account_codes((int) $org_id);
            $result = self::chat_classify_account($record_type, $text, $amount, $record, $accounts);
            if (!is_wp_error($result)) {
                return $result;
            }
            orabooks_log_event('classification_provider_fallback', 'AI classification failed; using stub', 'warning', [
                'error' => $result->get_error_message(),
                'record_type' => $record_type,
            ], null, (int) $org_id);
        }

        return OraBooks_Classification::run_classification_stub($record_type, $record, $text, $amount);
    }

    public static function resolve_attachment_bytes($attachment_id, $org_id) {
        if (!class_exists('OraBooks_Attachments') || !$attachment_id) {
            return null;
        }

        $attachment = OraBooks_Attachments::get_attachment((int) $attachment_id, (int) $org_id);
        if (!$attachment || empty($attachment->current_version_id)) {
            return null;
        }

        $version = OraBooks_Attachments::get_version((int) $attachment->current_version_id, (int) $org_id);
        if (!$version || empty($version->storage_path)) {
            return null;
        }

        return OraBooks_Attachments::read_stored_file($version->storage_path);
    }

    private static function azure_document_intelligence_ocr($file_bytes, $filename) {
        $endpoint = rtrim(self::config('azure_document_intelligence_endpoint'), '/');
        $key = self::config('azure_document_intelligence_key');
        $api_version = self::config('azure_document_intelligence_api_version', '2023-07-31');
        $model = self::config('azure_document_intelligence_model', 'prebuilt-receipt');

        $analyze_url = $endpoint . '/formrecognizer/documentModels/' . rawurlencode($model) . ':analyze?api-version=' . rawurlencode($api_version);

        $start = self::http_request($analyze_url, [
            'method'  => 'POST',
            'headers' => [
                'Ocp-Apim-Subscription-Key' => $key,
                'Content-Type'              => 'application/octet-stream',
            ],
            'body'    => $file_bytes,
            'timeout' => 60,
        ]);

        if (is_wp_error($start)) {
            return $start;
        }

        $operation_location = '';
        if (!empty($start['headers']['operation-location'])) {
            $operation_location = $start['headers']['operation-location'];
        } elseif (!empty($start['headers']['Operation-Location'])) {
            $operation_location = $start['headers']['Operation-Location'];
        }

        if ($operation_location === '') {
            return new WP_Error('ocr_no_operation', 'Document Intelligence did not return an operation URL.');
        }

        $result_body = null;
        for ($attempt = 0; $attempt < 15; $attempt++) {
            if ($attempt > 0) {
                usleep(500000);
            }

            $poll = self::http_request($operation_location, [
                'method'  => 'GET',
                'headers' => ['Ocp-Apim-Subscription-Key' => $key],
                'timeout' => 30,
            ]);

            if (is_wp_error($poll)) {
                return $poll;
            }

            $payload = json_decode($poll['body'] ?? '', true);
            $status = $payload['status'] ?? '';
            if ($status === 'succeeded') {
                $result_body = $payload;
                break;
            }
            if ($status === 'failed') {
                return new WP_Error('ocr_failed', $payload['error']['message'] ?? 'Document Intelligence analyze failed.');
            }
        }

        if (!$result_body) {
            return new WP_Error('ocr_timeout', 'Document Intelligence analyze timed out.');
        }

        return self::normalize_receipt_result($result_body, $filename);
    }

    public static function normalize_receipt_result(array $payload, $filename) {
        $doc = $payload['analyzeResult']['documents'][0] ?? [];
        $fields = $doc['fields'] ?? [];

        $vendor = self::field_value($fields, 'MerchantName') ?: self::field_value($fields, 'MerchantAddress');
        $total = self::field_number($fields, 'Total') ?: self::field_number($fields, 'TotalAmount');
        $tax = self::field_number($fields, 'TotalTax');
        $subtotal = self::field_number($fields, 'Subtotal');
        $date = self::field_date($fields, 'TransactionDate') ?: current_time('Y-m-d');

        if ($subtotal === null && $total !== null && $tax !== null) {
            $subtotal = round($total - $tax, 2);
        }
        if ($total === null && $subtotal !== null) {
            $total = round($subtotal + (float) ($tax ?? 0), 2);
        }
        $total = $total ?? 0.0;
        $subtotal = $subtotal ?? max(0, round($total - (float) ($tax ?? 0), 2));
        $tax = $tax ?? max(0, round($total - $subtotal, 2));
        $tax_rate = $subtotal > 0 ? round(($tax / $subtotal) * 100, 2) : 0.0;

        $field_confidences = [
            'vendor'           => self::field_confidence($fields, 'MerchantName', 85),
            'invoice_number'   => self::field_confidence($fields, 'TransactionId', 75),
            'transaction_date' => self::field_confidence($fields, 'TransactionDate', 90),
            'total_amount'     => self::field_confidence($fields, 'Total', 88),
            'tax_amount'       => self::field_confidence($fields, 'TotalTax', 72),
            'category'         => 78,
        ];

        $avg = array_sum($field_confidences) / count($field_confidences);
        $risk = self::risk_from_confidence($avg, $field_confidences, $total);

        $ocr_data = ['fields' => []];
        foreach ($field_confidences as $field => $confidence) {
            $ocr_data['fields'][$field] = [
                'confidence' => round($confidence, 2),
                'risk'       => $confidence >= 80 ? 'low' : ($confidence >= 65 ? 'medium' : 'high'),
            ];
        }

        return [
            'vendor'           => $vendor ? sanitize_text_field($vendor) : 'Unknown Vendor',
            'invoice_number'   => sanitize_text_field(self::field_value($fields, 'TransactionId') ?: ''),
            'transaction_date' => $date,
            'subtotal'           => round((float) $subtotal, 2),
            'tax_amount'         => round((float) $tax, 2),
            'tax_rate'           => $tax_rate,
            'total_amount'       => round((float) $total, 2),
            'currency'           => sanitize_text_field(self::field_value($fields, 'Currency') ?: 'USD'),
            'payment_method'     => sanitize_text_field(self::field_value($fields, 'PaymentMethod') ?: 'Card'),
            'category'           => 'General',
            'description'        => 'OCR extracted expense from ' . sanitize_text_field($filename),
            'ocr_confidence'     => round($avg, 2),
            'ocr_risk_level'     => $risk,
            'ocr_data'           => $ocr_data,
            'line_items'         => self::normalize_receipt_items($fields),
            'provider'           => self::PROVIDER_AZURE_DI,
            'model_version'      => self::model_version('ocr'),
        ];
    }

    private static function normalize_receipt_items(array $fields) {
        $items = [];
        $raw_items = $fields['Items']['valueArray'] ?? $fields['Items']['value'] ?? [];
        if (!is_array($raw_items)) {
            return [[
                'description'     => 'Receipt total',
                'quantity'        => 1,
                'unit_price'      => self::field_number($fields, 'Subtotal') ?? self::field_number($fields, 'Total') ?? 0,
                'total_amount'    => self::field_number($fields, 'Total') ?? 0,
                'line_confidence' => self::field_confidence($fields, 'Total', 80),
            ]];
        }

        foreach ($raw_items as $item) {
            $item_fields = $item['valueObject'] ?? $item;
            $description = self::field_value($item_fields, 'Description') ?: 'Line item';
            $qty = self::field_number($item_fields, 'Quantity') ?? 1;
            $price = self::field_number($item_fields, 'Price') ?? self::field_number($item_fields, 'TotalPrice') ?? 0;
            $items[] = [
                'description'     => sanitize_text_field($description),
                'quantity'        => (float) $qty,
                'unit_price'      => round((float) $price, 2),
                'total_amount'    => round((float) $price * (float) $qty, 2),
                'line_confidence' => 80,
            ];
        }

        return $items ?: [[
            'description'     => 'Receipt total',
            'quantity'        => 1,
            'unit_price'      => self::field_number($fields, 'Total') ?? 0,
            'total_amount'    => self::field_number($fields, 'Total') ?? 0,
            'line_confidence' => 80,
        ]];
    }

    private static function transcribe_audio($file_bytes, $filename, $mime_type) {
        $boundary = 'orabooks-' . wp_generate_password(16, false);
        $body = self::multipart_body($boundary, [
            [
                'name'     => 'file',
                'filename' => $filename,
                'type'     => $mime_type ?: 'audio/webm',
                'contents' => $file_bytes,
            ],
            [
                'name'     => 'model',
                'contents' => self::config('openai_whisper_model', 'whisper-1'),
            ],
        ]);

        if (self::is_azure_openai_configured()) {
            $endpoint = rtrim(self::config('azure_openai_endpoint'), '/');
            $deployment = self::config('azure_openai_whisper_deployment', self::config('azure_openai_deployment'));
            $api_version = self::config('azure_openai_api_version', '2024-06-01');
            $url = $endpoint . '/openai/deployments/' . rawurlencode($deployment) . '/audio/transcriptions?api-version=' . rawurlencode($api_version);
            $headers = [
                'api-key'      => self::config('azure_openai_key'),
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ];
        } else {
            $url = rtrim(self::config('openai_api_base', 'https://api.openai.com/v1'), '/') . '/audio/transcriptions';
            $headers = [
                'Authorization' => 'Bearer ' . self::config('openai_api_key'),
                'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
            ];
        }

        $response = self::http_request($url, [
            'method'  => 'POST',
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 90,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $payload = json_decode($response['body'] ?? '', true);
        return trim((string) ($payload['text'] ?? ''));
    }

    private static function extract_voice_intent($transcript) {
        $system = 'Extract accounting voice command fields as JSON with keys: transaction_type (expense|invoice|journal|task|reminder), vendor, customer, amount, total_amount, currency, transaction_date (YYYY-MM-DD), tax_amount, tax_rate, tax_type, tax_jurisdiction, category, description, field_confidences (object mapping field names to 0-100 confidence). Use today if date missing.';

        $payload = self::chat_completion([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $transcript],
        ], true);

        if (is_wp_error($payload)) {
            return $payload;
        }

        $content = $payload['choices'][0]['message']['content'] ?? '';
        $data = json_decode($content, true);
        if (!is_array($data)) {
            return new WP_Error('nlu_parse_failed', 'Could not parse voice NLU response.');
        }

        $field_confidences = is_array($data['field_confidences'] ?? null) ? $data['field_confidences'] : [];
        $confidence_avg = !empty($field_confidences)
            ? array_sum($field_confidences) / count($field_confidences)
            : 80.0;

        $amount = (float) ($data['amount'] ?? $data['total_amount'] ?? 0);
        $risk_scores = [
            'amount_risk'             => $amount >= 5000 ? 75 : 15,
            'vendor_risk'             => ($field_confidences['vendor'] ?? 80) < 65 ? 60 : 10,
            'anomaly_risk'            => 20,
            'language_ambiguity_risk' => $confidence_avg < 65 ? 65 : 10,
            'spoofing_risk'           => 5,
        ];
        $max_risk = max($risk_scores);
        $overall_risk = 'low';
        if ($max_risk >= 70 || $confidence_avg < 60) {
            $overall_risk = 'high';
        } elseif ($max_risk >= 30 || $confidence_avg < 70) {
            $overall_risk = 'medium';
        }

        $extracted = [
            'transaction_type'  => in_array($data['transaction_type'] ?? '', OraBooks_Voice::TRANSACTION_TYPES, true) ? $data['transaction_type'] : 'expense',
            'vendor'            => sanitize_text_field($data['vendor'] ?? ''),
            'customer'          => sanitize_text_field($data['customer'] ?? ''),
            'amount'            => $amount,
            'total_amount'      => (float) ($data['total_amount'] ?? $amount),
            'currency'          => sanitize_text_field($data['currency'] ?? 'USD'),
            'transaction_date'  => sanitize_text_field($data['transaction_date'] ?? current_time('Y-m-d')),
            'tax_amount'        => isset($data['tax_amount']) ? (float) $data['tax_amount'] : null,
            'tax_rate'          => isset($data['tax_rate']) ? (float) $data['tax_rate'] : null,
            'tax_type'          => sanitize_text_field($data['tax_type'] ?? ''),
            'tax_jurisdiction'  => sanitize_text_field($data['tax_jurisdiction'] ?? 'US'),
            'category'          => sanitize_text_field($data['category'] ?? 'General'),
            'description'       => sanitize_textarea_field($data['description'] ?? $transcript),
            'field_confidences' => $field_confidences,
        ];

        return [
            'extracted_data'     => $extracted,
            'language_detected'  => 'en',
            'confidence_avg'     => round($confidence_avg, 2),
            'risk_scores'        => $risk_scores,
            'overall_risk_level' => $overall_risk,
            'provider'           => self::provider_name('speech'),
            'model_version'      => self::model_version('speech'),
        ];
    }

    private static function chat_classify_account($record_type, $text, $amount, $record, array $accounts) {
        $account_list = array_map(function ($row) {
            return $row['code'] . ' — ' . $row['name'] . ' (' . $row['type'] . ')';
        }, $accounts);

        $system = 'You are an accounting classifier. Reply with JSON only: account_code, confidence (0-100), reason, tax_jurisdiction. Pick exactly one code from the provided chart of accounts.';
        $user = wp_json_encode([
            'record_type' => $record_type,
            'description' => $text,
            'amount'      => $amount,
            'category'    => $record->category ?? null,
            'accounts'    => $account_list,
        ]);

        $payload = self::chat_completion([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], true);

        if (is_wp_error($payload)) {
            return $payload;
        }

        $content = $payload['choices'][0]['message']['content'] ?? '';
        $data = json_decode($content, true);
        if (!is_array($data) || empty($data['account_code'])) {
            return new WP_Error('classification_parse_failed', 'Could not parse classification response.');
        }

        $valid_codes = array_column($accounts, 'code');
        if (!in_array($data['account_code'], $valid_codes, true) && !empty($valid_codes)) {
            $data['account_code'] = $valid_codes[0];
            $data['confidence'] = min((float) ($data['confidence'] ?? 60), 60);
            $data['reason'] = 'Model suggested unknown account; defaulted to ' . $data['account_code'];
        }

        return [
            'account_code'     => sanitize_text_field($data['account_code']),
            'confidence'       => (float) ($data['confidence'] ?? 70),
            'source'           => self::provider_name('classification'),
            'reason'           => sanitize_text_field($data['reason'] ?? 'AI classification'),
            'tax_jurisdiction' => sanitize_text_field($data['tax_jurisdiction'] ?? 'US'),
            'model_version'    => self::model_version('classification'),
        ];
    }

    private static function org_account_codes($org_id) {
        global $wpdb;

        if ($org_id <= 0) {
            return [];
        }

        if (class_exists('OraBooks_COA')) {
            $accounts = OraBooks_COA::list_accounts($org_id, ['is_active' => 1]);
            if (!empty($accounts)) {
                return array_map(function ($account) {
                    return [
                        'code' => $account->code,
                        'name' => $account->name,
                        'type' => $account->type,
                    ];
                }, $accounts);
            }
        }

        $table = OraBooks_Database::table('accounts');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT code, name, type FROM {$table} WHERE org_id = %d AND is_active = 1 ORDER BY code ASC",
            $org_id
        ));

        $accounts = [];
        foreach ($rows ?: [] as $row) {
            $accounts[] = [
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
            ];
        }

        return $accounts;
    }

    private static function chat_completion(array $messages, $json_mode = false) {
        if (self::is_azure_openai_configured()) {
            $endpoint = rtrim(self::config('azure_openai_endpoint'), '/');
            $deployment = self::config('azure_openai_deployment');
            $api_version = self::config('azure_openai_api_version', '2024-06-01');
            $url = $endpoint . '/openai/deployments/' . rawurlencode($deployment) . '/chat/completions?api-version=' . rawurlencode($api_version);
            $headers = [
                'api-key'       => self::config('azure_openai_key'),
                'Content-Type'  => 'application/json',
            ];
        } else {
            $url = rtrim(self::config('openai_api_base', 'https://api.openai.com/v1'), '/') . '/chat/completions';
            $headers = [
                'Authorization' => 'Bearer ' . self::config('openai_api_key'),
                'Content-Type'  => 'application/json',
            ];
        }

        $body = [
            'messages'    => $messages,
            'temperature' => 0.1,
        ];
        if ($json_mode) {
            $body['response_format'] = ['type' => 'json_object'];
        }
        if (!self::is_azure_openai_configured()) {
            $body['model'] = self::config('openai_chat_model', 'gpt-4o-mini');
        }

        $response = self::http_request($url, [
            'method'  => 'POST',
            'headers' => $headers,
            'body'    => wp_json_encode($body),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $payload = json_decode($response['body'] ?? '', true);
        if (!is_array($payload)) {
            return new WP_Error('chat_invalid_response', 'Invalid chat completion response.');
        }

        return $payload;
    }

    private static function http_request($url, array $args) {
        if (!function_exists('wp_remote_request')) {
            return new WP_Error('http_unavailable', 'HTTP client unavailable.');
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);
        $header_array = is_array($headers) ? $headers : (array) $headers;

        if ($code < 200 || $code >= 300) {
            return new WP_Error('http_error', 'Provider request failed with HTTP ' . $code, [
                'body' => $body,
                'code' => $code,
            ]);
        }

        return [
            'code'    => $code,
            'body'    => $body,
            'headers' => $header_array,
        ];
    }

    private static function multipart_body($boundary, array $parts) {
        $body = '';
        foreach ($parts as $part) {
            $body .= '--' . $boundary . "\r\n";
            if (!empty($part['filename'])) {
                $body .= 'Content-Disposition: form-data; name="' . $part['name'] . '"; filename="' . $part['filename'] . "\"\r\n";
                $body .= 'Content-Type: ' . ($part['type'] ?? 'application/octet-stream') . "\r\n\r\n";
            } else {
                $body .= 'Content-Disposition: form-data; name="' . $part['name'] . "\"\r\n\r\n";
            }
            $body .= $part['contents'] . "\r\n";
        }
        $body .= '--' . $boundary . "--\r\n";
        return $body;
    }

    private static function field_value(array $fields, $name) {
        $field = $fields[$name] ?? null;
        if (!$field) {
            return null;
        }
        return $field['valueString'] ?? $field['content'] ?? $field['value'] ?? null;
    }

    private static function field_number(array $fields, $name) {
        $field = $fields[$name] ?? null;
        if (!$field) {
            return null;
        }
        if (isset($field['valueNumber'])) {
            return (float) $field['valueNumber'];
        }
        if (isset($field['valueCurrency']['amount'])) {
            return (float) $field['valueCurrency']['amount'];
        }
        return is_numeric($field['value'] ?? null) ? (float) $field['value'] : null;
    }

    private static function field_date(array $fields, $name) {
        $field = $fields[$name] ?? null;
        if (!$field) {
            return null;
        }
        $value = $field['valueDate'] ?? $field['value'] ?? null;
        if (is_array($value)) {
            $value = sprintf('%04d-%02d-%02d', $value['year'], $value['month'], $value['day']);
        }
        return $value ? sanitize_text_field((string) $value) : null;
    }

    private static function field_confidence(array $fields, $name, $default) {
        $field = $fields[$name] ?? null;
        if (!$field) {
            return $default;
        }
        if (isset($field['confidence'])) {
            return round((float) $field['confidence'] * 100, 2);
        }
        return $default;
    }

    private static function risk_from_confidence($avg, array $field_confidences, $total) {
        $risk = 'low';
        if ($avg < 70 || min($field_confidences) < 55) {
            $risk = $avg < 60 ? 'high' : 'medium';
        }
        if ($total >= 10000) {
            $risk = 'high';
        }
        return $risk;
    }
}
