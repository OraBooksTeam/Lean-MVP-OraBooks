<?php
/**
 * OraBooks Voice-to-Text (SL-052)
 *
 * Voice input channel — never writes directly to accounting tables.
 * Creates derived resources via owning SL public APIs.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Voice {

    const TABLE_VOICE = 'voice_inputs';

    const STATUS_PENDING     = 'pending';
    const STATUS_PROCESSED   = 'processed';
    const STATUS_FAILED      = 'failed';
    const STATUS_ESCALATED   = 'escalated';
    const STATUS_DEAD_LETTER = 'dead_letter';

    const EVENT_TRANSCRIPTION_REQUESTED = 'voice_transcription_requested';
    const EVENT_ESCALATED               = 'voice_escalated';
    const EVENT_RESOURCE_SUBMITTED      = 'resource_submitted';

    const CONFIDENCE_THRESHOLD = 70.0;
    const MAX_AUDIO_SIZE       = 10485760; // 10 MB
    const MAX_RECORDING_SECONDS = 120;
    const MAX_RETRIES          = 3;
    const RATE_LIMIT_MAX       = 5;
    const RATE_LIMIT_PERIOD    = 60;
    const RETENTION_DAYS       = 90;
    const NLU_MODEL_VERSION    = 'mvp-stub-1.0';

    const TRANSACTION_TYPES = ['expense', 'invoice', 'journal', 'task', 'reminder', 'support_ticket', 'workflow_command'];

    private static $instance = null;

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();

            add_action('orabooks_voice_transcription_process', [self::$instance, 'cron_process_pending']);
            add_action('orabooks_voice_purge', [self::$instance, 'cron_purge_old']);

            add_action('wp_ajax_orabooks_voice_upload', [self::$instance, 'ajax_upload']);
            add_action('wp_ajax_orabooks_voice_get', [self::$instance, 'ajax_get']);
            add_action('wp_ajax_orabooks_voice_confirm', [self::$instance, 'ajax_confirm']);
            add_action('wp_ajax_orabooks_voice_list', [self::$instance, 'ajax_list']);
        }

        return self::$instance;
    }

    public static function get_create_table_sql() {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_VOICE);
        $table_orgs = OraBooks_Database::table('organizations');
        $table_attachments = OraBooks_Database::table('attachments');
        $charset = $wpdb->get_charset_collate();

        return [
            "CREATE TABLE IF NOT EXISTS {$table} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                audio_file_id BIGINT UNSIGNED NULL,
                audio_hash VARCHAR(64) NOT NULL,
                original_transcript TEXT DEFAULT NULL,
                edited_transcript TEXT DEFAULT NULL,
                extracted_data JSON DEFAULT NULL,
                language_detected VARCHAR(10) DEFAULT 'en',
                locale_preference VARCHAR(10) DEFAULT NULL,
                confidence_avg DECIMAL(5,2) DEFAULT NULL,
                risk_scores JSON DEFAULT NULL,
                overall_risk_level ENUM('low','medium','high') NOT NULL DEFAULT 'low',
                status ENUM('pending','processed','failed','escalated','dead_letter') NOT NULL DEFAULT 'pending',
                idempotency_key VARCHAR(128) DEFAULT NULL,
                processing_retry_count INT UNSIGNED NOT NULL DEFAULT 0,
                dead_letter_reason TEXT DEFAULT NULL,
                derived_resource_type VARCHAR(50) DEFAULT NULL,
                derived_resource_id BIGINT UNSIGNED NULL,
                retention_class ENUM('standard','legal_hold') NOT NULL DEFAULT 'standard',
                encrypted_storage TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
                FOREIGN KEY (audio_file_id) REFERENCES {$table_attachments}(id) ON DELETE SET NULL,
                UNIQUE KEY uk_idempotency (idempotency_key),
                INDEX idx_org_status (org_id, status),
                INDEX idx_org_status_created (org_id, status, created_at),
                INDEX idx_org_risk_created (org_id, overall_risk_level, created_at),
                INDEX idx_risk (overall_risk_level),
                INDEX idx_created (created_at)
            ) {$charset};",
        ];
    }

    public static function get_voice_stats($org_id) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_VOICE);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) AS total FROM {$table} WHERE org_id = %d GROUP BY status",
            intval($org_id)
        ));

        $stats = [
            'total'     => 0,
            'pending'   => 0,
            'processed' => 0,
            'failed'    => 0,
            'escalated' => 0,
            'dead_letter' => 0,
        ];

        foreach ($rows ?: [] as $row) {
            if (isset($stats[$row->status])) {
                $stats[$row->status] = (int) $row->total;
            }
            $stats['total'] += (int) $row->total;
        }

        return $stats;
    }

    public static function list_voice_inputs($org_id, $args = []) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_VOICE);
        $limit = max(1, min(100, intval($args['limit'] ?? 25)));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d ORDER BY created_at DESC LIMIT %d",
            intval($org_id),
            $limit
        ));
    }

    public static function get_voice_input($voice_id, $org_id) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_VOICE);
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND org_id = %d",
            intval($voice_id),
            intval($org_id)
        ));
    }

    public static function format_voice_input($row) {
        $extracted = [];
        $risk_scores = [];

        if (!empty($row->extracted_data)) {
            $decoded = json_decode($row->extracted_data, true);
            $extracted = is_array($decoded) ? $decoded : [];
        }
        if (!empty($row->risk_scores)) {
            $decoded = json_decode($row->risk_scores, true);
            $risk_scores = is_array($decoded) ? $decoded : [];
        }

        $voice_ai = is_array($extracted['_voice_ai'] ?? null) ? $extracted['_voice_ai'] : [];

        return [
            'id'                    => (int) $row->id,
            'org_id'                => (int) $row->org_id,
            'user_id'               => (int) $row->user_id,
            'audio_file_id'         => $row->audio_file_id ? (int) $row->audio_file_id : null,
            'audio_hash'            => $row->audio_hash,
            'original_transcript'   => $row->original_transcript,
            'edited_transcript'     => $row->edited_transcript,
            'extracted_data'        => $extracted,
            'language_detected'     => $row->language_detected,
            'confidence_avg'        => $row->confidence_avg !== null ? (float) $row->confidence_avg : null,
            'risk_scores'           => $risk_scores,
            'overall_risk_level'    => $row->overall_risk_level,
            'status'                => $row->status,
            'ai_provider'           => sanitize_text_field((string) ($voice_ai['provider'] ?? '')),
            'ai_model_version'      => sanitize_text_field((string) ($voice_ai['model_version'] ?? '')),
            'derived_resource_type' => $row->derived_resource_type,
            'derived_resource_id'   => $row->derived_resource_id ? (int) $row->derived_resource_id : null,
            'idempotency_key'       => $row->idempotency_key ?? null,
            'processing_retry_count'=> isset($row->processing_retry_count) ? (int) $row->processing_retry_count : 0,
            'dead_letter_reason'    => $row->dead_letter_reason ?? null,
            'field_confidences'     => $extracted['field_confidences'] ?? [],
            'created_at'            => $row->created_at,
            'updated_at'            => $row->updated_at,
        ];
    }

    public static function compute_overall_risk_level(array $risk_scores, $confidence_avg) {
        $max_risk = empty($risk_scores) ? 0 : max(array_map('floatval', $risk_scores));
        $confidence_avg = (float) $confidence_avg;

        if ($max_risk >= 70 || $confidence_avg < 60) {
            return 'high';
        }
        if ($max_risk >= 30 || $confidence_avg < self::CONFIDENCE_THRESHOLD) {
            return 'medium';
        }

        return 'low';
    }

    public static function upload_voice($org_id, $user_id, $filename, $content, $mime_type = '', $idempotency_key = '') {
        global $wpdb;

        $org_id = intval($org_id);
        $user_id = intval($user_id);

        if (!orabooks_check_rate_limit("voice_upload_{$user_id}", self::RATE_LIMIT_MAX, self::RATE_LIMIT_PERIOD)) {
            return new WP_Error('rate_limit', sprintf('Rate limit exceeded. Max %d voice uploads per minute.', self::RATE_LIMIT_MAX));
        }

        if ($content === '' || strlen($content) > self::MAX_AUDIO_SIZE) {
            return new WP_Error('invalid_file', 'Audio is empty or exceeds 10MB limit');
        }

        $allowed_prefixes = ['audio/', 'video/webm'];
        $mime_type = sanitize_text_field($mime_type ?: 'application/octet-stream');
        $allowed = false;
        foreach ($allowed_prefixes as $prefix) {
            if (strpos($mime_type, $prefix) === 0) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed && !preg_match('/\.(webm|mp3|wav|ogg|m4a)$/i', $filename)) {
            return new WP_Error('invalid_mime', 'Supported audio formats: WEBM, MP3, WAV, OGG. Max 10MB.');
        }

        $audio_hash = hash('sha256', $content);
        $table = OraBooks_Database::table(self::TABLE_VOICE);

        $wpdb->insert($table, [
            'org_id'     => $org_id,
            'user_id'    => $user_id,
            'audio_hash' => $audio_hash,
            'status'     => self::STATUS_PENDING,
        ], ['%d', '%d', '%s', '%s']);

        $voice_id = (int) $wpdb->insert_id;
        if (!$voice_id) {
            return new WP_Error('db_error', 'Failed to create voice input record');
        }

        if (!class_exists('OraBooks_Attachments')) {
            return new WP_Error('missing_module', 'Attachments module unavailable');
        }

        if ($idempotency_key === '') {
            $idempotency_key = orabooks_uuid();
        }

        $upload = OraBooks_Attachments::upload_attachment(
            $org_id,
            $user_id,
            'voice_input',
            $voice_id,
            $filename,
            $content,
            $mime_type,
            0,
            $idempotency_key
        );

        if (is_wp_error($upload)) {
            $wpdb->delete($table, ['id' => $voice_id], ['%d']);
            return $upload;
        }

        $wpdb->update($table, [
            'audio_file_id' => (int) ($upload['attachment_id'] ?? 0),
        ], ['id' => $voice_id], ['%d'], ['%d']);

        self::init()->process_voice_input($voice_id, $org_id, $filename);

        if (function_exists('orabooks_publish_event')) {
            orabooks_publish_event(self::EVENT_TRANSCRIPTION_REQUESTED, $voice_id, [
                'voice_input_id' => $voice_id,
                'org_id'         => $org_id,
            ]);
        }

        orabooks_log_event('voice_uploaded', "Voice input #{$voice_id} uploaded", 'info', [
            'voice_input_id' => $voice_id,
            'correlation_id' => function_exists('orabooks_get_correlation_id') ? orabooks_get_correlation_id() : '',
        ], $user_id, $org_id);

        $row = self::get_voice_input($voice_id, $org_id);
        return self::format_voice_input($row);
    }

    private function process_voice_input($voice_id, $org_id, $filename = 'recording.webm') {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_VOICE);
        $row = self::get_voice_input($voice_id, $org_id);
        if (!$row || $row->status !== self::STATUS_PENDING) {
            return;
        }

        try {
            $audio_context = self::resolve_voice_audio_context($row, $org_id, $filename);

            $result = OraBooks_Ai_Providers::run_voice_nlu([
                'filename'   => $audio_context['filename'],
                'voice_id'   => $voice_id,
                'file_bytes' => $audio_context['file_bytes'],
                'mime_type'  => $audio_context['mime_type'],
            ]);
        } catch (Exception $e) {
            $retry = (int) $row->processing_retry_count + 1;
            if ($retry > self::MAX_RETRIES) {
                $wpdb->update($table, [
                    'status'             => self::STATUS_DEAD_LETTER,
                    'processing_retry_count' => $retry,
                    'dead_letter_reason' => $e->getMessage(),
                ], ['id' => $voice_id], ['%s', '%d', '%s'], ['%d']);

                self::notify_transcription_failure($voice_id, $org_id, (int) $row->user_id, $e->getMessage());

                orabooks_log_event('voice_transcription_failed', "Voice input #{$voice_id} dead-lettered", 'warning', [
                    'voice_input_id' => $voice_id,
                    'reason' => $e->getMessage(),
                    'correlation_id' => function_exists('orabooks_get_correlation_id') ? orabooks_get_correlation_id() : '',
                ], (int) $row->user_id, $org_id);
            } else {
                $wpdb->update($table, [
                    'processing_retry_count' => $retry,
                ], ['id' => $voice_id], ['%d'], ['%d']);
            }
            return;
        }

        $extracted_payload = is_array($result['extracted_data'] ?? null) ? $result['extracted_data'] : [];
        $extracted_payload['_voice_ai'] = [
            'provider' => sanitize_text_field((string) ($result['provider'] ?? OraBooks_Ai_Providers::STUB_PROVIDER)),
            'model_version' => sanitize_text_field((string) ($result['model_version'] ?? OraBooks_Ai_Providers::STUB_MODEL_VERSION)),
        ];

        $wpdb->update($table, [
            'original_transcript' => sanitize_textarea_field($result['transcript']),
            'extracted_data'      => wp_json_encode($extracted_payload),
            'language_detected'   => sanitize_text_field($result['language_detected']),
            'confidence_avg'      => (float) $result['confidence_avg'],
            'risk_scores'         => wp_json_encode($result['risk_scores']),
            'overall_risk_level'  => sanitize_text_field($result['overall_risk_level']),
            'status'              => self::STATUS_PROCESSED,
        ], ['id' => $voice_id]);

        orabooks_log_event('voice_transcribed', "Voice input #{$voice_id} transcribed", 'info', [
            'voice_input_id' => $voice_id,
            'confidence_avg' => $result['confidence_avg'],
            'overall_risk_level' => $result['overall_risk_level'],
            'provider' => $result['provider'] ?? OraBooks_Ai_Providers::STUB_PROVIDER,
            'model_version' => $result['model_version'] ?? OraBooks_Ai_Providers::STUB_MODEL_VERSION,
            'language_detected' => sanitize_text_field($result['language_detected'] ?? 'en'),
            'correlation_id' => function_exists('orabooks_get_correlation_id') ? orabooks_get_correlation_id() : '',
        ], (int) $row->user_id, $org_id);
    }

    private static function resolve_voice_audio_context($row, $org_id, $default_filename = 'recording.webm') {
        $context = [
            'filename' => sanitize_file_name((string) $default_filename ?: 'recording.webm'),
            'mime_type' => 'audio/webm',
            'file_bytes' => null,
        ];

        if (empty($row->audio_file_id) || !class_exists('OraBooks_Attachments')) {
            return $context;
        }

        $attachment = OraBooks_Attachments::get_attachment((int) $row->audio_file_id, (int) $org_id);
        if (!$attachment || empty($attachment->current_version_id)) {
            return $context;
        }

        $version = OraBooks_Attachments::get_version((int) $attachment->current_version_id, (int) $org_id);
        if ($version) {
            $version_name = sanitize_file_name((string) ($version->file_name ?? ''));
            if ($version_name !== '') {
                $context['filename'] = $version_name;
            }
            $version_mime = sanitize_text_field((string) ($version->mime_type ?? ''));
            if ($version_mime !== '') {
                $context['mime_type'] = $version_mime;
            }
        }

        $bytes = OraBooks_Ai_Providers::resolve_attachment_bytes((int) $row->audio_file_id, (int) $org_id);
        if ($bytes !== null && $bytes !== false && $bytes !== '') {
            $context['file_bytes'] = $bytes;
        }

        return $context;
    }

    private static function notify_transcription_failure($voice_id, $org_id, $user_id, $reason) {
        if (!class_exists('OraBooks_Notifications') || $user_id <= 0) {
            return;
        }

        OraBooks_Notifications::send_notification($user_id, 'voice_transcription_failed', [
            'title' => 'Voice transcription failed',
            'message' => 'Your voice input could not be transcribed after multiple attempts. Please try again or enter the transaction manually.',
            'voice_input_id' => (int) $voice_id,
            'reason' => $reason,
        ], (int) $org_id);
    }

    public function cron_process_pending() {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_VOICE);
        $rows = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE status = '" . self::STATUS_PENDING . "' AND processing_retry_count <= " . self::MAX_RETRIES . " ORDER BY created_at ASC LIMIT 5"
        );

        foreach ($rows ?: [] as $row) {
            $this->process_voice_input((int) $row->id, (int) $row->org_id, 'recording.webm');
        }
    }

    public static function run_nlu_stub($filename, $voice_id) {
        $seed = crc32($filename . $voice_id);
        $types = ['expense', 'invoice', 'journal'];
        $type = $types[$seed % count($types)];

        $amount = round(25 + (($seed % 50000) / 100), 2);
        $vendor = 'Voice Vendor ' . (($seed % 900) + 100);
        $customer = 'Voice Customer ' . (($seed % 800) + 200);

        $filename_lc = strtolower((string) $filename);
        $category = 'General';
        if (preg_match('/rent|rental|lease/', $filename_lc)) {
            $category = 'Rent';
        } elseif (preg_match('/travel|flight|hotel|uber|transport/', $filename_lc)) {
            $category = 'Travel';
        } elseif (preg_match('/meal|food|restaurant|cafe/', $filename_lc)) {
            $category = 'Meals';
        } elseif (preg_match('/salary|payroll|wage/', $filename_lc)) {
            $category = 'Salary';
        } elseif (preg_match('/software|subscription|license|saas/', $filename_lc)) {
            $category = 'Software';
        } elseif (preg_match('/office|stationery|printer|paper/', $filename_lc)) {
            $category = 'Office Supplies';
        }

        $field_confidences = [
            'transaction_type' => 58,
            'vendor'           => 52 + ($seed % 8),
            'amount'           => 56 + ($seed % 8),
            'transaction_date' => 60,
            'category'         => 50,
        ];

        if (stripos($filename, 'unclear') !== false) {
            $field_confidences['amount'] = 48;
            $field_confidences['vendor'] = 45;
            $field_confidences['category'] = 50;
            $field_confidences['transaction_type'] = 55;
        }

        $confidence_avg = array_sum($field_confidences) / count($field_confidences);

        $risk_scores = [
            'amount_risk'              => $amount >= 5000 ? 75 : 30,
            'vendor_risk'              => $field_confidences['vendor'] < 65 ? 70 : 30,
            'anomaly_risk'             => 25,
            'language_ambiguity_risk'  => stripos($filename, 'unclear') !== false ? 85 : 75,
            'spoofing_risk'            => 5,
        ];

        $overall_risk = self::compute_overall_risk_level($risk_scores, $confidence_avg);

        $subtotal = round($amount / 1.05, 2);
        $tax_amount = round($amount - $subtotal, 2);
        $due_date = gmdate('Y-m-d', strtotime('+30 days'));

        $transcript = 'Fallback transcription mode is active. Configure OpenAI/Azure speech provider to transcribe real audio.';

        $extracted = [
            'transaction_type'  => $type,
            'vendor'            => $vendor,
            'vendor_tax_id'     => 'TAX-' . (($seed % 900000) + 100000),
            'customer'          => $customer,
            'amount'            => $amount,
            'total_amount'      => $amount,
            'subtotal'          => $subtotal,
            'currency'          => 'USD',
            'transaction_date'  => current_time('Y-m-d'),
            'due_date'          => $due_date,
            'tax_amount'        => $tax_amount,
            'tax_rate'          => 5.0,
            'tax_type'          => 'Sales Tax',
            'tax_jurisdiction'  => 'US-CA',
            'tax_registration_number' => 'REG-' . (($seed % 90000) + 10000),
            'category'          => $category,
            'description'       => 'Fallback extraction output. Verify and edit fields before confirming.',
            'line_items'        => [[
                'description' => 'Fallback line item',
                'quantity'    => 1,
                'unit_price'  => $subtotal,
                'total'       => $subtotal,
                'tax_rate'    => 5.0,
            ]],
            'field_confidences' => $field_confidences,
        ];

        return [
            'transcript'          => $transcript,
            'extracted_data'      => $extracted,
            'language_detected'   => 'en',
            'confidence_avg'      => round($confidence_avg, 2),
            'risk_scores'         => $risk_scores,
            'overall_risk_level'  => $overall_risk,
            'provider'            => OraBooks_Ai_Providers::STUB_PROVIDER,
            'model_version'       => OraBooks_Ai_Providers::STUB_MODEL_VERSION,
        ];
    }

    public static function confirm_voice($voice_id, $org_id, $user_id, $idempotency_key, array $edited_fields = []) {
        global $wpdb;

        $voice = self::get_voice_input($voice_id, $org_id);
        if (!$voice) {
            return new WP_Error('not_found', 'Voice input not found');
        }

        if ($voice->status !== self::STATUS_PROCESSED) {
            return new WP_Error('invalid_status', 'Voice input must be processed before confirm');
        }

        if (!empty($voice->derived_resource_id)) {
            return new WP_Error('already_submitted', 'This voice input has already been submitted.', ['status' => 409]);
        }

        $table = OraBooks_Database::table(self::TABLE_VOICE);
        $idempotency_key = sanitize_text_field($idempotency_key);

        if ($idempotency_key !== '' && !empty($voice->idempotency_key) && $voice->idempotency_key === $idempotency_key) {
            return self::format_voice_input($voice);
        }

        if ($idempotency_key !== '') {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE idempotency_key = %s AND id != %d",
                $idempotency_key,
                intval($voice_id)
            ));
            if ($existing) {
                return new WP_Error('duplicate', 'Duplicate submission detected', ['status' => 409]);
            }
        } else {
            $idempotency_key = orabooks_uuid();
        }

        $extracted = json_decode($voice->extracted_data ?: '{}', true);
        if (!is_array($extracted)) {
            $extracted = [];
        }
        foreach ($edited_fields as $key => $value) {
            $extracted[$key] = $value;
        }

        $confidence = (float) ($voice->confidence_avg ?? 0);
        $risk = $voice->overall_risk_level ?: 'medium';
        $update = [
            'idempotency_key'   => $idempotency_key,
            'extracted_data'    => wp_json_encode($extracted),
        ];

        if (!empty($edited_fields['edited_transcript'])) {
            $update['edited_transcript'] = sanitize_textarea_field($edited_fields['edited_transcript']);
        }

        if ($confidence >= self::CONFIDENCE_THRESHOLD && $risk === 'low') {
            $derived = self::create_derived_resource($extracted, $org_id, $user_id, $voice_id);
            if (is_wp_error($derived)) {
                return $derived;
            }

            $update['derived_resource_type'] = $derived['type'];
            $update['derived_resource_id'] = (int) $derived['id'];

            if (function_exists('orabooks_publish_event')) {
                orabooks_publish_event(self::EVENT_RESOURCE_SUBMITTED, (int) $derived['id'], [
                    'resource_type' => $derived['type'],
                    'resource_id'   => (int) $derived['id'],
                    'org_id'        => (int) $org_id,
                    'source'        => 'voice',
                    'voice_input_id'=> (int) $voice_id,
                ]);
            }

            orabooks_log_event('voice_confirmed_with_resource', "Voice #{$voice_id} created {$derived['type']} #{$derived['id']}", 'info', [
                'voice_input_id' => (int) $voice_id,
                'resource_type'  => $derived['type'],
                'resource_id'    => (int) $derived['id'],
                'overall_risk_level' => $risk,
                'confidence_avg' => $confidence,
                'correlation_id' => function_exists('orabooks_get_correlation_id') ? orabooks_get_correlation_id() : '',
            ], $user_id, $org_id);
        } else {
            $update['status'] = self::STATUS_ESCALATED;

            if (class_exists('OraBooks_Ai_Review')) {
                OraBooks_Ai_Review::enqueue(
                    intval($org_id),
                    'voice_input',
                    intval($voice_id),
                    null,
                    [
                        'confidence'        => $confidence,
                        'risk_level'        => $risk,
                        'explanation'       => 'Voice NLU confidence below threshold or elevated risk',
                        'model_version'     => OraBooks_Ai_Providers::model_version('speech'),
                        'escalation_reason' => 'voice_low_confidence',
                    ],
                    (float) ($extracted['amount'] ?? $extracted['total_amount'] ?? 0)
                );
            }

            if (function_exists('orabooks_publish_event')) {
                orabooks_publish_event(self::EVENT_ESCALATED, intval($voice_id), [
                    'voice_input_id' => intval($voice_id),
                    'org_id'         => intval($org_id),
                    'confidence'     => $confidence,
                    'risk_level'     => $risk,
                ]);
            }

            orabooks_log_event('voice_escalated_to_ai_review', "Voice #{$voice_id} escalated to AI review", 'warning', [
                'voice_input_id' => (int) $voice_id,
                'confidence'     => $confidence,
                'risk_level'     => $risk,
                'correlation_id' => function_exists('orabooks_get_correlation_id') ? orabooks_get_correlation_id() : '',
            ], $user_id, $org_id);
        }

        $wpdb->update($table, $update, ['id' => intval($voice_id)]);

        $updated = self::get_voice_input($voice_id, $org_id);
        return self::format_voice_input($updated);
    }

    public static function create_derived_resource(array $extracted, $org_id, $user_id, $voice_id) {
        $type = sanitize_text_field($extracted['transaction_type'] ?? 'expense');
        $idempotency = 'voice-' . intval($voice_id) . '-' . orabooks_uuid();

        switch ($type) {
            case 'expense':
                return self::create_derived_expense($extracted, $org_id, $user_id, $idempotency);
            case 'invoice':
                return self::create_derived_invoice($extracted, $org_id, $user_id, $idempotency);
            case 'journal':
                return self::create_derived_journal($extracted, $org_id, $user_id, $idempotency);
            default:
                return new WP_Error('unsupported_type', 'Unsupported transaction type: ' . $type);
        }
    }

    private static function create_derived_expense(array $extracted, $org_id, $user_id, $idempotency_key) {
        if (!class_exists('OraBooks_Expenses')) {
            return new WP_Error('missing_module', 'Expenses module unavailable');
        }

        $confidence = (float) ($extracted['field_confidences']['amount'] ?? 80);
        $risk = 'low';

        $created = OraBooks_Expenses::create_draft_from_voice($org_id, $user_id, $extracted, $confidence, $risk);
        if (is_wp_error($created)) {
            return $created;
        }

        $submitted = OraBooks_Expenses::confirm_submit((int) $created['id'], $org_id, $user_id, $idempotency_key, $extracted);
        if (is_wp_error($submitted)) {
            return $submitted;
        }

        return ['type' => 'expense', 'id' => (int) $created['id']];
    }

    private static function create_derived_invoice(array $extracted, $org_id, $user_id, $idempotency_key) {
        if (!class_exists('OraBooks_Customers')) {
            return new WP_Error('missing_module', 'Customers module unavailable');
        }

        $customer_id = self::resolve_customer_id($org_id, $extracted);
        if (!$customer_id) {
            return new WP_Error('missing_customer', 'No customer found. Create a customer first or edit the customer field.');
        }

        $amount = (float) ($extracted['amount'] ?? $extracted['total_amount'] ?? 0);
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'Amount must be greater than zero');
        }

        $invoice = OraBooks_Customers::create_invoice($org_id, [
            'customer_id'     => $customer_id,
            'total_amount'    => $amount,
            'tax_amount'      => (float) ($extracted['tax_amount'] ?? 0),
            'tax_rate'        => (float) ($extracted['tax_rate'] ?? 0),
            'description'     => $extracted['description'] ?? 'Voice-created invoice',
            'transaction_date'=> $extracted['transaction_date'] ?? current_time('Y-m-d'),
            'idempotency_key' => $idempotency_key,
        ]);

        if (is_wp_error($invoice)) {
            return $invoice;
        }

        $sent = OraBooks_Customers::send_invoice($org_id, (int) $invoice->id, (int) $user_id);
        if (is_wp_error($sent)) {
            return $sent;
        }

        return ['type' => 'invoice', 'id' => (int) $invoice->id];
    }

    private static function create_derived_journal(array $extracted, $org_id, $user_id, $idempotency_key) {
        if (!class_exists('OraBooks_Posting')) {
            return new WP_Error('missing_module', 'Posting module unavailable');
        }

        $amount = (float) ($extracted['amount'] ?? $extracted['total_amount'] ?? 0);
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'Amount must be greater than zero');
        }

        $journal_id = OraBooks_Posting::create_journal([
            'org_id'           => intval($org_id),
            'transaction_date' => $extracted['transaction_date'] ?? current_time('Y-m-d'),
            'source_type'      => 'voice_input',
            'source_id'        => null,
            'metadata'         => ['voice' => true, 'description' => $extracted['description'] ?? ''],
            'idempotency_key'  => $idempotency_key,
        ], $user_id);

        if (!$journal_id) {
            return new WP_Error('journal_error', 'Failed to create journal from voice');
        }

        $desc = sanitize_text_field($extracted['description'] ?? 'Voice journal entry');
        $lines = OraBooks_Posting::add_lines($journal_id, [
            ['account_code' => '5000', 'debit' => $amount, 'credit' => 0, 'description' => $desc],
            ['account_code' => '1000', 'debit' => 0, 'credit' => $amount, 'description' => $desc],
        ]);

        if (is_wp_error($lines)) {
            return $lines;
        }

        $submit = OraBooks_Posting::submit_journal($journal_id, $user_id);
        if (is_wp_error($submit)) {
            return $submit;
        }

        return ['type' => 'journal', 'id' => (int) $journal_id];
    }

    private static function resolve_customer_id($org_id, array $extracted) {
        global $wpdb;

        if (!empty($extracted['customer_id'])) {
            return (int) $extracted['customer_id'];
        }

        $table = OraBooks_Database::table('customers');
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE org_id = %d ORDER BY id ASC LIMIT 1",
            intval($org_id)
        ));
    }

    public function cron_purge_old() {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_VOICE);
        $cutoff = gmdate('Y-m-d H:i:s', time() - (self::RETENTION_DAYS * DAY_IN_SECONDS));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE retention_class = 'standard' AND created_at < %s AND status IN ('processed','failed','escalated','dead_letter')",
            $cutoff
        ));
    }

    private function require_voice_access($user_id, $org_id, $permission = 'view_voice_inputs') {
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $org_id = (int) $org_id;
        if ($org_id <= 0) {
            orabooks_json_error('Organization is required', 400);
        }

        $tenant = orabooks_assert_tenant_access($user_id, $org_id, false);
        if (is_wp_error($tenant)) {
            orabooks_json_error($tenant->get_error_message(), 403);
        }

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, $permission)) {
            orabooks_json_error('Permission denied', 403);
        }
    }

    public function ajax_upload() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_voice_access($user_id, $org_id, 'manage_voice_inputs');

        if (empty($_FILES['voice_file'])) {
            orabooks_json_error('Voice audio file is required', 400);
        }

        $file = $_FILES['voice_file'];
        if (!empty($file['error'])) {
            orabooks_json_error('Upload failed', 400);
        }

        $content = file_get_contents($file['tmp_name']);
        $result = self::upload_voice(
            $org_id,
            $user_id,
            sanitize_file_name($file['name'] ?: 'recording.webm'),
            $content,
            sanitize_text_field($file['type'] ?? 'audio/webm'),
            sanitize_text_field($_POST['idempotency_key'] ?? '')
        );

        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success(['voice_input' => $result], 'Voice uploaded and transcribed');
    }

    public function ajax_get() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_GET['org_id'] ?? $_POST['org_id'] ?? 0);
        $voice_id = intval($_GET['voice_id'] ?? $_POST['voice_id'] ?? 0);

        $this->require_voice_access($user_id, $org_id);

        $voice = self::get_voice_input($voice_id, $org_id);
        if (!$voice) {
            orabooks_json_error('Voice input not found', 404);
        }

        orabooks_json_success(['voice_input' => self::format_voice_input($voice)]);
    }

    public function ajax_confirm() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $voice_id = intval($_POST['voice_id'] ?? 0);
        $idempotency_key = sanitize_text_field($_POST['idempotency_key'] ?? '');

        $this->require_voice_access($user_id, $org_id, 'manage_voice_inputs');

        $edited = [];
        if (!empty($_POST['edited_fields'])) {
            $decoded = json_decode(stripslashes((string) $_POST['edited_fields']), true);
            if (is_array($decoded)) {
                $edited = $decoded;
            }
        }

        $result = self::confirm_voice($voice_id, $org_id, $user_id, $idempotency_key, $edited);
        if (is_wp_error($result)) {
            $code = 400;
            if ($result->get_error_code() === 'duplicate' || $result->get_error_code() === 'already_submitted') {
                $code = 409;
            }
            orabooks_json_error($result->get_error_message(), $code);
        }

        orabooks_json_success(['voice_input' => $result], 'Voice input confirmed');
    }

    public function ajax_list() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_GET['org_id'] ?? $_POST['org_id'] ?? 0);

        $this->require_voice_access($user_id, $org_id);

        $rows = self::list_voice_inputs($org_id, ['limit' => 50]);
        orabooks_json_success([
            'voice_inputs' => array_map([self::class, 'format_voice_input'], $rows ?: []),
        ]);
    }
}
