<?php
/**
 * OraBooks Expenses OCR (SL-028)
 *
 * Receipt upload, MVP OCR extraction, confirm/submit routing to SL-002 / SL-076,
 * approval and posting via SL-001.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Expenses {

    const TABLE_EXPENSES     = 'expenses';
    const TABLE_OCR_QUEUE    = 'ocr_processing_queue';
    const TABLE_LINE_ITEMS   = 'expense_line_items';
    const OCR_JOB_TYPE       = 'process_expense_ocr';

    const CONFIDENCE_THRESHOLD = 70.0;
    const MAX_RECEIPT_SIZE     = 10485760; // 10 MB
    const MAX_OCR_RETRIES      = 3;
    const UPLOAD_RATE_LIMIT_MAX = 10;
    const UPLOAD_RATE_LIMIT_PERIOD = 60;
    const OCR_MODEL_VERSION    = 'mvp-stub-1.0';
    const OCR_PROVIDER         = 'mvp-stub';

    const WORKFLOW_STATUSES = ['draft', 'submitted', 'ai_review', 'approved', 'posted', 'locked'];
    const PAYMENT_STATUSES  = ['unpaid', 'paid', 'reimbursable'];

    private static $instance = null;

    private static $category_accounts = [
        'meals'           => '5200',
        'travel'          => '5300',
        'office supplies' => '5100',
        'utilities'       => '5400',
        'software'        => '5500',
    ];

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();

            if (class_exists('OraBooks_AsyncQueue')) {
                OraBooks_AsyncQueue::register_handler(self::OCR_JOB_TYPE, [self::class, 'handle_ocr_async_job']);
            }

            add_action('orabooks_expenses_ocr_process', [self::$instance, 'cron_process_ocr_queue']);

            add_action('wp_ajax_orabooks_expense_upload_receipt', [self::$instance, 'ajax_upload_receipt']);
            add_action('wp_ajax_nopriv_orabooks_expense_upload_receipt', [self::$instance, 'ajax_upload_receipt']);
            add_action('wp_ajax_orabooks_expense_get', [self::$instance, 'ajax_get']);
            add_action('wp_ajax_nopriv_orabooks_expense_get', [self::$instance, 'ajax_get']);
            add_action('wp_ajax_orabooks_expense_confirm', [self::$instance, 'ajax_confirm']);
            add_action('wp_ajax_nopriv_orabooks_expense_confirm', [self::$instance, 'ajax_confirm']);
            add_action('wp_ajax_orabooks_expense_approve', [self::$instance, 'ajax_approve']);
            add_action('wp_ajax_nopriv_orabooks_expense_approve', [self::$instance, 'ajax_approve']);
            add_action('wp_ajax_orabooks_expense_reject', [self::$instance, 'ajax_reject']);
            add_action('wp_ajax_nopriv_orabooks_expense_reject', [self::$instance, 'ajax_reject']);
            add_action('wp_ajax_orabooks_expense_post', [self::$instance, 'ajax_post']);
            add_action('wp_ajax_nopriv_orabooks_expense_post', [self::$instance, 'ajax_post']);
            add_action('wp_ajax_orabooks_expense_override_tax', [self::$instance, 'ajax_override_tax']);
            add_action('wp_ajax_nopriv_orabooks_expense_override_tax', [self::$instance, 'ajax_override_tax']);
            add_action('wp_ajax_orabooks_expense_clear_tax_override', [self::$instance, 'ajax_clear_tax_override']);
            add_action('wp_ajax_nopriv_orabooks_expense_clear_tax_override', [self::$instance, 'ajax_clear_tax_override']);
            add_action('wp_ajax_orabooks_expenses_list', [self::$instance, 'ajax_list']);
            add_action('wp_ajax_nopriv_orabooks_expenses_list', [self::$instance, 'ajax_list']);
        }

        return self::$instance;
    }

    public static function get_create_table_sql() {
        global $wpdb;

        $table_expenses = OraBooks_Database::table(self::TABLE_EXPENSES);
        $table_queue = OraBooks_Database::table(self::TABLE_OCR_QUEUE);
        $table_lines = OraBooks_Database::table(self::TABLE_LINE_ITEMS);
        $table_orgs = OraBooks_Database::table('organizations');
        $table_attachments = OraBooks_Database::table('attachments');
        $charset = $wpdb->get_charset_collate();

        return [
            "CREATE TABLE IF NOT EXISTS {$table_expenses} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                vendor VARCHAR(255) DEFAULT NULL,
                vendor_tax_id VARCHAR(50) DEFAULT NULL,
                invoice_number VARCHAR(100) DEFAULT NULL,
                transaction_date DATE DEFAULT NULL,
                due_date DATE DEFAULT NULL,
                subtotal DECIMAL(20,2) DEFAULT NULL,
                tax_amount DECIMAL(20,2) DEFAULT NULL,
                tax_rate DECIMAL(5,2) DEFAULT NULL,
                tax_jurisdiction VARCHAR(32) DEFAULT NULL,
                tax_type VARCHAR(32) DEFAULT NULL,
                total_amount DECIMAL(20,2) DEFAULT NULL,
                currency CHAR(3) DEFAULT 'USD',
                payment_method VARCHAR(50) DEFAULT NULL,
                category VARCHAR(100) DEFAULT NULL,
                merchant_address TEXT DEFAULT NULL,
                description TEXT DEFAULT NULL,
                ocr_confidence DECIMAL(5,2) DEFAULT NULL,
                ocr_risk_level ENUM('low','medium','high') DEFAULT 'low',
                ocr_data JSON DEFAULT NULL,
                ocr_provider VARCHAR(50) DEFAULT NULL,
                ocr_model_version VARCHAR(20) DEFAULT NULL,
                ocr_snapshot_hash VARCHAR(64) DEFAULT NULL,
                receipt_image_hash VARCHAR(64) DEFAULT NULL,
                workflow_status ENUM('draft','submitted','ai_review','approved','posted','locked') NOT NULL DEFAULT 'draft',
                payment_status ENUM('unpaid','paid','reimbursable') NOT NULL DEFAULT 'unpaid',
                lock_status ENUM('unlocked','locked') NOT NULL DEFAULT 'unlocked',
                idempotency_key VARCHAR(128) DEFAULT NULL,
                attachment_id BIGINT UNSIGNED NULL,
                journal_id BIGINT UNSIGNED NULL,
                created_by BIGINT UNSIGNED NOT NULL,
                approved_by BIGINT UNSIGNED NULL,
                posted_by BIGINT UNSIGNED NULL,
                approved_at TIMESTAMP NULL,
                posted_at TIMESTAMP NULL,
                tax_override_reason VARCHAR(64) NULL,
                tax_override_by BIGINT UNSIGNED NULL,
                tax_override_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
                FOREIGN KEY (attachment_id) REFERENCES {$table_attachments}(id) ON DELETE SET NULL,
                UNIQUE KEY uk_idempotency (idempotency_key),
                INDEX idx_org_status (org_id, workflow_status),
                INDEX idx_org_created (org_id, created_at)
            ) {$charset};",
            "CREATE TABLE IF NOT EXISTS {$table_queue} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                expense_id BIGINT UNSIGNED NOT NULL,
                org_id BIGINT UNSIGNED NOT NULL,
                attachment_id BIGINT UNSIGNED NULL,
                file_path VARCHAR(500) DEFAULT NULL,
                status ENUM('pending','processing','completed','failed','escalated') NOT NULL DEFAULT 'pending',
                retry_count INT UNSIGNED NOT NULL DEFAULT 0,
                error_message TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (expense_id) REFERENCES {$table_expenses}(id) ON DELETE CASCADE,
                INDEX idx_status (status, created_at)
            ) {$charset};",
            "CREATE TABLE IF NOT EXISTS {$table_lines} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                expense_id BIGINT UNSIGNED NOT NULL,
                description VARCHAR(255) DEFAULT NULL,
                quantity DECIMAL(10,2) DEFAULT 1,
                unit_price DECIMAL(20,2) DEFAULT NULL,
                total_amount DECIMAL(20,2) DEFAULT NULL,
                line_confidence DECIMAL(5,2) DEFAULT NULL,
                FOREIGN KEY (expense_id) REFERENCES {$table_expenses}(id) ON DELETE CASCADE,
                INDEX idx_expense (expense_id)
            ) {$charset};",
        ];
    }

    public static function get_expense_stats($org_id) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_EXPENSES);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT workflow_status, COUNT(*) AS total FROM {$table} WHERE org_id = %d GROUP BY workflow_status",
            intval($org_id)
        ));

        $stats = [
            'total'      => 0,
            'draft'      => 0,
            'submitted'  => 0,
            'ai_review'  => 0,
            'approved'   => 0,
            'posted'     => 0,
            'pending_ocr'=> 0,
        ];

        foreach ($rows ?: [] as $row) {
            $key = $row->workflow_status;
            if (isset($stats[$key])) {
                $stats[$key] = (int) $row->total;
            }
            $stats['total'] += (int) $row->total;
        }

        $queue_table = OraBooks_Database::table(self::TABLE_OCR_QUEUE);
        $stats['pending_ocr'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$queue_table} q
             JOIN {$table} e ON e.id = q.expense_id
             WHERE e.org_id = %d AND q.status IN ('pending','processing')",
            intval($org_id)
        ));

        return $stats;
    }

    public static function list_expenses($org_id, $args = []) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_EXPENSES);
        $org_id = intval($org_id);
        $limit = max(1, min(100, intval($args['limit'] ?? 25)));
        $offset = max(0, intval($args['offset'] ?? 0));
        $status = sanitize_text_field($args['status'] ?? $args['workflow_status'] ?? '');

        if ($status !== '') {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE org_id = %d AND workflow_status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $org_id,
                $status,
                $limit,
                $offset
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $org_id,
            $limit,
            $offset
        ));
    }

    public static function get_expense($expense_id, $org_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . OraBooks_Database::table(self::TABLE_EXPENSES) . " WHERE id = %d AND org_id = %d",
            intval($expense_id),
            intval($org_id)
        ));
    }

    public static function get_line_items($expense_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . OraBooks_Database::table(self::TABLE_LINE_ITEMS) . " WHERE expense_id = %d ORDER BY id ASC",
            intval($expense_id)
        ));
    }

    public static function format_expense($row, $include_lines = false) {
        $ocr_data = [];
        if (!empty($row->ocr_data)) {
            $decoded = json_decode($row->ocr_data, true);
            $ocr_data = is_array($decoded) ? $decoded : [];
        }

        $formatted = [
            'id'               => (int) $row->id,
            'org_id'           => (int) $row->org_id,
            'vendor'           => $row->vendor,
            'vendor_tax_id'    => $row->vendor_tax_id,
            'invoice_number'   => $row->invoice_number,
            'transaction_date' => $row->transaction_date,
            'due_date'         => $row->due_date,
            'subtotal'         => $row->subtotal !== null ? (float) $row->subtotal : null,
            'tax_amount'       => $row->tax_amount !== null ? (float) $row->tax_amount : null,
            'tax_rate'         => $row->tax_rate !== null ? (float) $row->tax_rate : null,
            'total_amount'     => $row->total_amount !== null ? (float) $row->total_amount : null,
            'currency'         => $row->currency ?: 'USD',
            'payment_method'   => $row->payment_method,
            'category'         => $row->category,
            'merchant_address' => $row->merchant_address,
            'description'      => $row->description,
            'ocr_confidence'   => $row->ocr_confidence !== null ? (float) $row->ocr_confidence : null,
            'ocr_risk_level'   => $row->ocr_risk_level,
            'ocr_data'         => $ocr_data,
            'ocr_provider'     => $row->ocr_provider,
            'ocr_model_version'=> $row->ocr_model_version,
            'ocr_snapshot_hash'=> $row->ocr_snapshot_hash,
            'receipt_image_hash' => $row->receipt_image_hash ?? null,
            'workflow_status'  => $row->workflow_status,
            'payment_status'   => $row->payment_status,
            'lock_status'      => $row->lock_status,
            'attachment_id'    => $row->attachment_id ? (int) $row->attachment_id : null,
            'journal_id'       => $row->journal_id ? (int) $row->journal_id : null,
            'created_by'       => (int) $row->created_by,
            'approved_by'      => $row->approved_by ? (int) $row->approved_by : null,
            'posted_by'        => $row->posted_by ? (int) $row->posted_by : null,
            'approved_at'      => $row->approved_at,
            'posted_at'        => $row->posted_at,
            'created_at'       => $row->created_at,
            'updated_at'       => $row->updated_at,
            'tax_override_reason' => $row->tax_override_reason ?? null,
            'tax_override_by'     => !empty($row->tax_override_by) ? (int) $row->tax_override_by : null,
            'tax_override_at'     => $row->tax_override_at ?? null,
        ];

        if (class_exists('OraBooks_Classification')) {
            $formatted['classification'] = OraBooks_Classification::format_classification($row);
        }

        if ($include_lines) {
            $lines = self::get_line_items((int) $row->id);
            $formatted['line_items'] = array_map([self::class, 'format_line_item'], $lines ?: []);
        }

        return $formatted;
    }

    public static function format_line_item($row) {
        return [
            'id'              => (int) $row->id,
            'description'     => $row->description,
            'quantity'        => (float) $row->quantity,
            'unit_price'      => $row->unit_price !== null ? (float) $row->unit_price : null,
            'total_amount'    => $row->total_amount !== null ? (float) $row->total_amount : null,
            'line_confidence' => $row->line_confidence !== null ? (float) $row->line_confidence : null,
        ];
    }

    public static function upload_receipt($org_id, $user_id, $filename, $content, $mime_type = '', $idempotency_key = '') {
        global $wpdb;

        $org_id = intval($org_id);
        $user_id = intval($user_id);

        if ($content === '' || strlen($content) > self::MAX_RECEIPT_SIZE) {
            return new WP_Error('invalid_file', 'Receipt is empty or exceeds 10MB limit');
        }

        if (!orabooks_check_rate_limit("expense_receipt_upload_{$user_id}", self::UPLOAD_RATE_LIMIT_MAX, self::UPLOAD_RATE_LIMIT_PERIOD)) {
            return new WP_Error('rate_limit', 'Too many uploads. Limit is 10 uploads per minute.');
        }

        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        $mime_type = sanitize_text_field($mime_type ?: 'application/octet-stream');
        if (!in_array($mime_type, $allowed, true) && strpos($mime_type, 'image/') !== 0) {
            return new WP_Error('invalid_mime', 'Supported: PDF, JPG, PNG. Max 10MB.');
        }

        $table = OraBooks_Database::table(self::TABLE_EXPENSES);
        $wpdb->insert($table, [
            'org_id'          => $org_id,
            'workflow_status' => 'draft',
            'payment_status'  => 'unpaid',
            'lock_status'     => 'unlocked',
            'created_by'      => $user_id,
            'currency'        => 'USD',
        ], ['%d', '%s', '%s', '%s', '%d', '%s']);

        $expense_id = (int) $wpdb->insert_id;
        if (!$expense_id) {
            return new WP_Error('db_error', 'Failed to create expense draft');
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
            'expense',
            $expense_id,
            $filename,
            $content,
            $mime_type,
            0,
            $idempotency_key
        );

        if (is_wp_error($upload)) {
            $wpdb->delete($table, ['id' => $expense_id], ['%d']);
            return $upload;
        }

        $attachment_id = (int) ($upload['attachment_id'] ?? 0);
        $wpdb->update($table, ['attachment_id' => $attachment_id], ['id' => $expense_id], ['%d'], ['%d']);

        $queue_id = self::enqueue_ocr($expense_id, $org_id, $attachment_id);

        $queued_async = false;
        if (class_exists('OraBooks_AsyncQueue')) {
            $job_id = OraBooks_AsyncQueue::enqueue(self::OCR_JOB_TYPE, [
                'queue_id' => (int) $queue_id,
                'expense_id' => (int) $expense_id,
                'org_id' => (int) $org_id,
            ], [
                'queue_name' => 'ocr',
                'priority' => 3,
                'max_retries' => self::MAX_OCR_RETRIES,
                'idempotency_key' => 'ocr-' . (int) $queue_id,
            ]);
            $queued_async = (bool) $job_id;
        }

        // Fallback for environments where SL-303 queue worker is unavailable.
        if (!$queued_async) {
            self::init()->process_ocr_item_by_id($queue_id);
        }

        orabooks_log_event('expense_receipt_uploaded', "Receipt uploaded for expense #{$expense_id}", 'info', [
            'expense_id'    => $expense_id,
            'attachment_id' => $attachment_id,
        ], $user_id, $org_id);

        if (function_exists('orabooks_publish_event')) {
            orabooks_publish_event('ocr_requested', $expense_id, [
                'expense_id'    => $expense_id,
                'org_id'        => $org_id,
                'attachment_id' => $attachment_id,
            ]);
        }

        $expense = self::get_expense($expense_id, $org_id);
        return self::format_expense($expense, true);
    }

    public static function handle_ocr_async_job($job, $payload) {
        $queue_id = (int) ($payload['queue_id'] ?? 0);
        if ($queue_id <= 0) {
            return false;
        }

        $ok = self::init()->process_ocr_item_by_id($queue_id);
        return (bool) $ok;
    }

    private static function enqueue_ocr($expense_id, $org_id, $attachment_id) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_OCR_QUEUE);
        $wpdb->insert($table, [
            'expense_id'    => intval($expense_id),
            'org_id'        => intval($org_id),
            'attachment_id' => intval($attachment_id),
            'file_path'     => null,
            'status'        => 'pending',
        ], ['%d', '%d', '%d', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    public function cron_process_ocr_queue() {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_OCR_QUEUE);
        $items = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE status = 'pending' ORDER BY created_at ASC LIMIT 5"
        );

        foreach ($items ?: [] as $item) {
            $this->process_ocr_item($item);
        }
    }

    private function process_ocr_item_by_id($queue_id) {
        global $wpdb;

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . OraBooks_Database::table(self::TABLE_OCR_QUEUE) . " WHERE id = %d",
            intval($queue_id)
        ));

        if ($item && $item->status === 'pending') {
            return $this->process_ocr_item($item);
        }

        return false;
    }

    private function process_ocr_item($item) {
        global $wpdb;

        $queue_table = OraBooks_Database::table(self::TABLE_OCR_QUEUE);
        $expense_table = OraBooks_Database::table(self::TABLE_EXPENSES);

        $wpdb->update($queue_table, ['status' => 'processing'], ['id' => (int) $item->id], ['%s'], ['%d']);

        $expense = self::get_expense((int) $item->expense_id, (int) $item->org_id);
        if (!$expense) {
            $wpdb->update($queue_table, [
                'status'        => 'failed',
                'error_message' => 'Expense not found',
            ], ['id' => (int) $item->id], ['%s', '%s'], ['%d']);
            return false;
        }

        $filename = 'receipt';
        if ($expense->attachment_id && class_exists('OraBooks_Attachments')) {
            $attachment = OraBooks_Attachments::get_attachment((int) $expense->attachment_id, (int) $item->org_id);
            if ($attachment && $attachment->current_version_id) {
                $version = OraBooks_Attachments::get_version((int) $attachment->current_version_id);
                if ($version) {
                    $filename = $version->file_name;
                }
            }
        }

        $file_bytes = null;
        if ($expense->attachment_id) {
            $file_bytes = OraBooks_Ai_Providers::resolve_attachment_bytes((int) $expense->attachment_id, (int) $item->org_id);
        }

        try {
            $ocr = OraBooks_Ai_Providers::run_ocr([
                'filename'   => $filename,
                'expense_id' => (int) $item->expense_id,
                'file_bytes' => $file_bytes,
            ]);

            if (is_wp_error($ocr)) {
                throw new Exception($ocr->get_error_message());
            }
        } catch (Exception $e) {
            $retry = (int) $item->retry_count + 1;
            if ($retry > self::MAX_OCR_RETRIES) {
                $wpdb->update($queue_table, [
                    'status'        => 'failed',
                    'retry_count'   => $retry,
                    'error_message' => $e->getMessage(),
                ], ['id' => (int) $item->id], ['%s', '%d', '%s'], ['%d']);

                self::alert_ocr_failure((int) $item->org_id, (int) $item->expense_id, $e->getMessage(), $retry);
            } else {
                $wpdb->update($queue_table, [
                    'status'      => 'pending',
                    'retry_count' => $retry,
                ], ['id' => (int) $item->id], ['%s', '%d'], ['%d']);
            }
            return false;
        }

        $snapshot_hash = hash('sha256', wp_json_encode($ocr['ocr_data']));
        $receipt_hash = !empty($ocr['receipt_image_hash'])
            ? sanitize_text_field((string) $ocr['receipt_image_hash'])
            : (!empty($file_bytes) ? hash('sha256', (string) $file_bytes) : null);

        $status = ((float) ($ocr['ocr_confidence'] ?? 0) < self::CONFIDENCE_THRESHOLD || ($ocr['ocr_risk_level'] ?? 'low') === 'high')
            ? 'escalated'
            : 'completed';

        $wpdb->update($expense_table, [
            'vendor'             => sanitize_text_field($ocr['vendor']),
            'vendor_tax_id'      => sanitize_text_field($ocr['vendor_tax_id'] ?? ''),
            'invoice_number'     => sanitize_text_field($ocr['invoice_number']),
            'transaction_date'   => $ocr['transaction_date'],
            'due_date'           => !empty($ocr['due_date']) ? sanitize_text_field($ocr['due_date']) : null,
            'subtotal'           => $ocr['subtotal'],
            'tax_amount'         => $ocr['tax_amount'],
            'tax_rate'           => $ocr['tax_rate'],
            'total_amount'       => $ocr['total_amount'],
            'currency'           => sanitize_text_field($ocr['currency']),
            'payment_method'     => sanitize_text_field($ocr['payment_method']),
            'category'           => sanitize_text_field($ocr['category']),
            'merchant_address'   => !empty($ocr['merchant_address']) ? sanitize_textarea_field($ocr['merchant_address']) : null,
            'description'        => sanitize_textarea_field($ocr['description']),
            'ocr_confidence'     => $ocr['ocr_confidence'],
            'ocr_risk_level'     => $ocr['ocr_risk_level'],
            'ocr_data'           => wp_json_encode($ocr['ocr_data']),
            'ocr_provider'       => sanitize_text_field($ocr['provider'] ?? OraBooks_Ai_Providers::provider_name('ocr')),
            'ocr_model_version'  => sanitize_text_field($ocr['model_version'] ?? OraBooks_Ai_Providers::model_version('ocr')),
            'ocr_snapshot_hash'  => $snapshot_hash,
            'receipt_image_hash' => $receipt_hash,
        ], ['id' => (int) $item->expense_id]);

        self::replace_line_items((int) $item->expense_id, $ocr['line_items']);

        $wpdb->update($queue_table, ['status' => $status], ['id' => (int) $item->id], ['%s'], ['%d']);

        orabooks_log_event('ocr_processing_completed', "OCR completed for expense #{$item->expense_id}", 'info', [
            'expense_id'     => (int) $item->expense_id,
            'avg_confidence' => $ocr['ocr_confidence'],
            'risk_level'     => $ocr['ocr_risk_level'],
            'provider'       => $ocr['provider'] ?? OraBooks_Ai_Providers::provider_name('ocr'),
        ], 0, (int) $item->org_id);

        if (class_exists('OraBooks_Classification')) {
            OraBooks_Classification::request('expense', (int) $item->expense_id, (int) $item->org_id);
        }

        return true;
    }

    public static function run_ocr_stub($filename, $expense_id, $file_bytes = null) {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $base = preg_replace('/[_\-]+/', ' ', $base);
        $base = trim($base) ?: 'Unknown Vendor';

        $seed = crc32($filename . $expense_id);

        $text = '';
        if (!empty($file_bytes)) {
            $text = preg_replace('/[^A-Za-z0-9@.,:\/-\s]/', ' ', (string) $file_bytes);
            $text = preg_replace('/\s+/', ' ', (string) $text);
        }

        $vendor = ucwords($base);
        $base_lower = strtolower((string) $base);
        if (preg_match('/salary|payroll|wage/', $base_lower)) {
            $vendor = 'Staff Salary';
        }
        if ($text !== '' && preg_match('/(?:company\s*name|merchant|vendor|supplier|shop)\s*[:\-]\s*([A-Za-z][A-Za-z0-9\s&.,\-]{2,80})/i', $text, $m)) {
            $vendor = sanitize_text_field(trim($m[1]));
        } elseif ($text !== '' && preg_match('/paid\s*to\s*[:\-]\s*([A-Za-z][A-Za-z0-9\s&.,\-]{2,80})/i', $text, $m)) {
            $vendor = sanitize_text_field(trim($m[1]));
        }

        $total = 0.0;
        if ($text !== '' && preg_match('/(?:grand\s*total|total\s*amount|amount\s*due|total)\s*[:\-]?\s*(?:USD|BDT|EUR|GBP|\$|৳|€|£)?\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{1,2})|[0-9]+(?:\.[0-9]{1,2})?)/i', $text, $m)) {
            $total = (float) str_replace(',', '', $m[1]);
        }
        if ($total <= 0 && $text !== '' && preg_match_all('/([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{1,2})|[0-9]+(?:\.[0-9]{1,2})?)/', $text, $nums)) {
            $values = array_map(function ($n) {
                return (float) str_replace(',', '', $n);
            }, $nums[1]);
            $values = array_filter($values, function ($v) {
                return $v > 0.5 && $v < 100000000;
            });
            if (!empty($values)) {
                $total = max($values);
            }
        }
        if ($total <= 0) {
            // Keep deterministic fallback so OCR stub still produces usable preview values.
            $total = round(($expense_id % 10000) / 10 + ($seed % 5000) / 100 + 20, 2);
        }

        $tax_rate = 5.0;
        $tax_amount = round($total * ($tax_rate / 100) / (1 + ($tax_rate / 100)), 2);
        $subtotal = round($total - $tax_amount, 2);

        $invoice_number = 'RCP-' . str_pad((string) ($seed % 999999), 6, '0', STR_PAD_LEFT);
        if ($text !== '' && preg_match('/(?:invoice|receipt|voucher)\s*(?:no|number|#)?\s*[:\-]?\s*([A-Z0-9\-]{3,40})/i', $text, $m)) {
            $invoice_number = sanitize_text_field(trim($m[1]));
        }

        $transaction_date = current_time('Y-m-d');
        if ($text !== '' && preg_match('/(\d{4}[\/-]\d{1,2}[\/-]\d{1,2}|\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4})/', $text, $m)) {
            $parsed = strtotime($m[1]);
            if ($parsed) {
                $transaction_date = gmdate('Y-m-d', $parsed);
            }
        }

        $due_date = null;
        if ($text !== '' && preg_match('/due\s*date\s*[:\-]?\s*(\d{4}[\/-]\d{1,2}[\/-]\d{1,2}|\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4})/i', $text, $m)) {
            $parsed = strtotime($m[1]);
            if ($parsed) {
                $due_date = gmdate('Y-m-d', $parsed);
            }
        }

        $vendor_tax_id = '';
        if ($text !== '' && preg_match('/(?:vat|tin|bin|tax\s*id)\s*[:\-]?\s*([A-Z0-9\-]{4,40})/i', $text, $m)) {
            $vendor_tax_id = sanitize_text_field(trim($m[1]));
        }

        $merchant_address = '';
        if ($text !== '' && preg_match('/(?:address|addr)\s*[:\-]\s*([^\n\r]{6,180})/i', $text, $m)) {
            $merchant_address = sanitize_text_field(trim($m[1]));
        }

        $currency = 'USD';
        if (stripos($text, 'BDT') !== false || stripos($text, 'taka') !== false || strpos($text, '৳') !== false) {
            $currency = 'BDT';
        } elseif (stripos($text, 'EUR') !== false || strpos($text, '€') !== false) {
            $currency = 'EUR';
        } elseif (stripos($text, 'GBP') !== false || strpos($text, '£') !== false) {
            $currency = 'GBP';
        }

        $categories = ['Office Supplies', 'Meals', 'Travel', 'Software', 'Utilities'];
        $category = $categories[$seed % count($categories)];
        if (preg_match('/salary|payroll|wage/', $base_lower)) {
            $category = 'Salary';
        }
        if ($text !== '') {
            if (preg_match('/hotel|flight|uber|transport|travel/i', $text)) {
                $category = 'Travel';
            } elseif (preg_match('/restaurant|cafe|meal|food/i', $text)) {
                $category = 'Meals';
            } elseif (preg_match('/salary|staff\s*salary|wage|payroll/i', $text)) {
                $category = 'Salary';
            } elseif (preg_match('/software|subscription|saas/i', $text)) {
                $category = 'Software';
            } elseif (preg_match('/paper|stationery|office|printer/i', $text)) {
                $category = 'Office Supplies';
            }
        }

        $base_conf = $text !== '' ? 62 : 50;
        if ($total > 0) {
            $base_conf += 10;
        }

        $field_confidences = [
            'vendor'            => min(95, $base_conf + ($vendor !== 'Unknown Vendor' ? 10 : 0)),
            'vendor_tax_id'     => $vendor_tax_id !== '' ? min(95, $base_conf + 8) : max(40, $base_conf - 12),
            'invoice_number'    => min(92, $base_conf + 5),
            'transaction_date'  => min(92, $base_conf + 10),
            'due_date'          => $due_date ? min(90, $base_conf + 6) : max(45, $base_conf - 10),
            'subtotal'          => $total > 0 ? min(88, $base_conf + 8) : max(35, $base_conf - 15),
            'tax_amount'        => $total > 0 ? min(85, $base_conf + 4) : max(35, $base_conf - 15),
            'tax_rate'          => $total > 0 ? min(82, $base_conf + 2) : max(35, $base_conf - 15),
            'total_amount'      => $total > 0 ? min(90, $base_conf + 10) : max(30, $base_conf - 20),
            'currency'          => $text !== '' ? min(90, $base_conf + 8) : max(45, $base_conf - 10),
            'payment_method'    => $text !== '' ? min(82, $base_conf + 3) : max(45, $base_conf - 10),
            'line_items'        => $text !== '' ? min(80, $base_conf + 2) : max(40, $base_conf - 10),
            'category'          => min(88, $base_conf + 6),
            'merchant_address'  => $merchant_address !== '' ? min(90, $base_conf + 4) : max(42, $base_conf - 8),
        ];

        if (stripos($base, 'unknown') !== false || strlen($base) < 3) {
            $field_confidences['vendor'] = 58;
        }
        if ($total >= 5000) {
            $field_confidences['total_amount'] = min($field_confidences['total_amount'], 62);
        }
        if ($total >= 10000 || $expense_id >= 90000) {
            $total = max($total, 10000);
            $risk = 'high';
        }

        $avg = array_sum($field_confidences) / count($field_confidences);
        $risk = 'low';
        if ($avg < 70 || min($field_confidences) < 55) {
            $risk = $avg < 60 ? 'high' : 'medium';
        }
        if ($total >= 10000) {
            $risk = 'high';
        }

        $ocr_data = [
            'fields' => [],
        ];
        foreach ($field_confidences as $field => $confidence) {
            $ocr_data['fields'][$field] = [
                'confidence' => round($confidence, 2),
                'risk'       => $confidence >= 80 ? 'low' : ($confidence >= 65 ? 'medium' : 'high'),
            ];
        }

        return [
            'vendor'             => $vendor,
            'vendor_tax_id'      => $vendor_tax_id,
            'invoice_number'     => $invoice_number,
            'transaction_date'   => $transaction_date,
            'due_date'           => $due_date,
            'subtotal'           => $subtotal,
            'tax_amount'         => $tax_amount,
            'tax_rate'           => $tax_rate,
            'total_amount'       => $total,
            'currency'           => $currency,
            'payment_method'     => preg_match('/cash/i', $text) ? 'Cash' : (preg_match('/bank|transfer/i', $text) ? 'Bank Transfer' : 'Credit Card'),
            'category'           => $category,
            'merchant_address'   => $merchant_address,
            'description'        => 'OCR extracted expense from ' . $filename,
            'ocr_confidence'     => round($avg, 2),
            'ocr_risk_level'     => $risk,
            'ocr_data'           => $ocr_data,
            'receipt_image_hash' => !empty($file_bytes) ? hash('sha256', (string) $file_bytes) : null,
            'line_items'         => [[
                'description'     => $category . ' purchase',
                'quantity'        => 1,
                'unit_price'      => $subtotal,
                'total_amount'    => $subtotal,
                'line_confidence' => round($field_confidences['category'], 2),
            ]],
            'provider'           => OraBooks_Ai_Providers::STUB_PROVIDER,
            'model_version'      => OraBooks_Ai_Providers::STUB_MODEL_VERSION,
        ];
    }

    private static function alert_ocr_failure($org_id, $expense_id, $error_message, $retry_count) {
        $org_id = (int) $org_id;
        $expense_id = (int) $expense_id;
        $error_message = sanitize_text_field((string) $error_message);

        orabooks_log_event('ocr_processing_failed', "OCR failed for expense #{$expense_id}", 'warning', [
            'expense_id' => $expense_id,
            'retry_count' => (int) $retry_count,
            'error' => $error_message,
        ], 0, $org_id);

        if (function_exists('orabooks_publish_event')) {
            orabooks_publish_event('ocr_failed', $expense_id, [
                'expense_id' => $expense_id,
                'org_id' => $org_id,
                'retry_count' => (int) $retry_count,
                'error' => $error_message,
            ]);
        }

        if (!class_exists('OraBooks_Notifications')) {
            return;
        }

        foreach (self::get_alert_user_ids($org_id) as $user_id) {
            OraBooks_Notifications::send_notification((int) $user_id, 'ocr_processing_failed', [
                'title' => 'Expense OCR failed after retries',
                'message' => "Expense #{$expense_id} OCR failed after {$retry_count} retries.",
                'priority' => 'high',
                'expense_id' => $expense_id,
                'error' => $error_message,
            ], $org_id);
        }
    }

    private static function get_alert_user_ids($org_id) {
        global $wpdb;

        $table_users = OraBooks_Database::table('users');
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$table_users} WHERE org_id = %d AND role IN ('owner','admin')",
            (int) $org_id
        ));

        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
        if (empty($ids)) {
            $fallback = (int) get_current_user_id();
            if ($fallback > 0) {
                $ids[] = $fallback;
            }
        }

        return $ids;
    }

    private static function replace_line_items($expense_id, array $lines) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_LINE_ITEMS);
        $wpdb->delete($table, ['expense_id' => intval($expense_id)], ['%d']);

        foreach ($lines as $line) {
            $wpdb->insert($table, [
                'expense_id'      => intval($expense_id),
                'description'     => sanitize_text_field($line['description'] ?? ''),
                'quantity'        => (float) ($line['quantity'] ?? 1),
                'unit_price'      => (float) ($line['unit_price'] ?? 0),
                'total_amount'    => (float) ($line['total_amount'] ?? 0),
                'line_confidence' => (float) ($line['line_confidence'] ?? 0),
            ], ['%d', '%s', '%f', '%f', '%f', '%f']);
        }
    }

    public static function create_draft_from_voice($org_id, $user_id, array $extracted, $confidence = null, $risk_level = 'low') {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_EXPENSES);
        $wpdb->insert($table, [
            'org_id'            => intval($org_id),
            'vendor'            => sanitize_text_field($extracted['vendor'] ?? ''),
            'vendor_tax_id'     => sanitize_text_field($extracted['vendor_tax_id'] ?? ''),
            'invoice_number'    => sanitize_text_field($extracted['invoice_number'] ?? ''),
            'transaction_date'  => sanitize_text_field($extracted['transaction_date'] ?? current_time('Y-m-d')),
            'due_date'          => !empty($extracted['due_date']) ? sanitize_text_field($extracted['due_date']) : null,
            'subtotal'          => isset($extracted['subtotal']) ? (float) $extracted['subtotal'] : null,
            'tax_amount'        => isset($extracted['tax_amount']) ? (float) $extracted['tax_amount'] : null,
            'tax_rate'          => isset($extracted['tax_rate']) ? (float) $extracted['tax_rate'] : null,
            'total_amount'      => (float) ($extracted['amount'] ?? $extracted['total_amount'] ?? 0),
            'currency'          => sanitize_text_field($extracted['currency'] ?? 'USD'),
            'payment_method'    => sanitize_text_field($extracted['payment_method'] ?? 'Voice'),
            'category'          => sanitize_text_field($extracted['category'] ?? 'General'),
            'description'       => sanitize_textarea_field($extracted['description'] ?? 'Created from voice input'),
            'ocr_confidence'    => $confidence !== null ? (float) $confidence : null,
            'ocr_risk_level'    => sanitize_text_field($risk_level),
            'ocr_data'          => wp_json_encode(['source' => 'voice', 'fields' => $extracted]),
            'ocr_provider'      => OraBooks_Ai_Providers::provider_name('speech'),
            'ocr_model_version' => OraBooks_Ai_Providers::model_version('speech'),
            'workflow_status'   => 'draft',
            'payment_status'    => 'unpaid',
            'lock_status'       => 'unlocked',
            'created_by'        => intval($user_id),
        ]);

        $expense_id = (int) $wpdb->insert_id;
        if (!$expense_id) {
            return new WP_Error('db_error', 'Failed to create expense from voice input');
        }

        if (!empty($extracted['line_items']) && is_array($extracted['line_items'])) {
            self::replace_line_items($expense_id, $extracted['line_items']);
        }

        return ['id' => $expense_id];
    }

    /**
     * Manual tax override for draft expenses (SL-081).
     */
    public static function override_expense_tax($org_id, $expense_id, $new_tax_rate, $reason_code, $user_id, $jurisdiction = 'US') {
        global $wpdb;

        $org_id = (int) $org_id;
        $expense_id = (int) $expense_id;
        $user_id = (int) $user_id;
        $new_tax_rate = round((float) $new_tax_rate, 4);
        $reason_code = sanitize_text_field($reason_code);
        $jurisdiction = strtoupper(sanitize_text_field($jurisdiction ?: 'US'));

        $expense = self::get_expense($expense_id, $org_id);
        if (!$expense) {
            return new WP_Error('not_found', 'Expense not found');
        }

        if ($expense->workflow_status !== 'draft') {
            return new WP_Error('invalid_status', 'Tax can only be overridden on draft expenses');
        }

        if (class_exists('OraBooks_Tax') && OraBooks_Tax::is_tax_locked($org_id, [
            'transaction_date' => $expense->transaction_date ?? current_time('Y-m-d'),
        ])) {
            return new WP_Error('tax_locked', 'Tax is locked for this transaction period');
        }

        if (class_exists('OraBooks_Tax')) {
            $validation = OraBooks_Tax::validate_override($org_id, $jurisdiction, $new_tax_rate, $reason_code);
            if (is_wp_error($validation)) {
                return $validation;
            }
        }

        $tax_base = self::get_expense_tax_base($expense);
        $old_tax_rate = isset($expense->tax_rate) ? (float) $expense->tax_rate : 0;
        $old_tax_amount = (float) ($expense->tax_amount ?? 0);
        $new_tax_amount = round($tax_base * ($new_tax_rate / 100), 2);
        $new_total = round($tax_base + $new_tax_amount, 2);

        $table = OraBooks_Database::table(self::TABLE_EXPENSES);
        $wpdb->update(
            $table,
            [
                'tax_rate'            => $new_tax_rate,
                'tax_amount'          => $new_tax_amount,
                'total_amount'        => $new_total,
                'subtotal'            => $tax_base,
                'tax_jurisdiction'    => $jurisdiction,
                'tax_override_reason' => $reason_code,
                'tax_override_by'     => $user_id,
                'tax_override_at'     => current_time('mysql'),
            ],
            ['id' => $expense_id, 'org_id' => $org_id],
            ['%f', '%f', '%f', '%f', '%s', '%s', '%d', '%s'],
            ['%d', '%d']
        );

        orabooks_log_event('tax_override', 'Expense tax overridden', 'info', [
            'transaction_type' => 'expense',
            'transaction_id'   => $expense_id,
            'old_tax_rate'     => $old_tax_rate,
            'new_tax_rate'     => $new_tax_rate,
            'old_tax_amount'   => $old_tax_amount,
            'new_tax_amount'   => $new_tax_amount,
            'reason_code'      => $reason_code,
        ], $user_id, $org_id);

        return [
            'expense_id'          => $expense_id,
            'tax_rate'            => $new_tax_rate,
            'tax_amount'          => $new_tax_amount,
            'total_amount'        => $new_total,
            'tax_override_reason' => $reason_code,
            'tax_override_by'     => $user_id,
        ];
    }

    /**
     * Clear manual tax override and recalculate from SL-305 (SL-081 §5.4).
     */
    public static function clear_expense_tax_override($org_id, $expense_id, $user_id, $jurisdiction = 'US') {
        global $wpdb;

        $expense = self::get_expense((int) $expense_id, (int) $org_id);
        if (!$expense) {
            return new WP_Error('not_found', 'Expense not found');
        }

        if ($expense->workflow_status !== 'draft') {
            return new WP_Error('invalid_status', 'Override can only be cleared on draft expenses');
        }

        $tax_base = self::get_expense_tax_base($expense);
        $tax_rate = 0.0;
        $tax_amount = 0.0;
        $total = $tax_base;

        if (class_exists('OraBooks_Tax') && $tax_base > 0) {
            $calc = OraBooks_Tax::calculate([
                'org_id'       => (int) $org_id,
                'amount'       => $tax_base,
                'jurisdiction' => strtoupper(sanitize_text_field($jurisdiction ?: 'US')),
            ]);
            if (!is_wp_error($calc)) {
                $tax_rate = (float) ($calc['tax_rate'] ?? 0);
                $tax_amount = (float) ($calc['tax_amount'] ?? 0);
                $total = round($tax_base + $tax_amount, 2);
            }
        }

        $table = OraBooks_Database::table(self::TABLE_EXPENSES);
        $wpdb->update(
            $table,
            [
                'tax_rate'            => $tax_rate,
                'tax_amount'          => $tax_amount,
                'total_amount'        => $total,
                'tax_override_reason' => null,
                'tax_override_by'     => null,
                'tax_override_at'     => null,
            ],
            ['id' => (int) $expense_id, 'org_id' => (int) $org_id],
            ['%f', '%f', '%f', '%s', '%s', '%s'],
            ['%d', '%d']
        );

        orabooks_log_event('tax_override_cleared', 'Expense tax override cleared', 'info', [
            'expense_id' => (int) $expense_id,
        ], (int) $user_id, (int) $org_id);

        return self::format_expense(self::get_expense((int) $expense_id, (int) $org_id), true);
    }

    private static function get_expense_tax_base($expense) {
        if (!empty($expense->subtotal)) {
            return max(0, round((float) $expense->subtotal, 2));
        }

        $total = (float) ($expense->total_amount ?? 0);
        $tax = (float) ($expense->tax_amount ?? 0);
        return max(0, round($total - $tax, 2));
    }

    public static function confirm_submit($expense_id, $org_id, $user_id, $idempotency_key, array $edited_fields = []) {
        global $wpdb;

        $expense = self::get_expense($expense_id, $org_id);
        if (!$expense) {
            return new WP_Error('not_found', 'Expense not found');
        }

        if ($expense->workflow_status !== 'draft') {
            return new WP_Error('invalid_status', 'Only draft expenses can be submitted');
        }

        if ($expense->ocr_confidence === null) {
            return new WP_Error('ocr_pending', 'OCR processing is not complete yet');
        }

        $table = OraBooks_Database::table(self::TABLE_EXPENSES);

        if ($idempotency_key !== '') {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE idempotency_key = %s AND id != %d",
                sanitize_text_field($idempotency_key),
                intval($expense_id)
            ));
            if ($existing) {
                return new WP_Error('duplicate', 'Duplicate idempotency key', ['status' => 409]);
            }
        } else {
            $idempotency_key = orabooks_uuid();
        }

        $update = ['idempotency_key' => sanitize_text_field($idempotency_key)];
        $allowed = [
            'vendor', 'vendor_tax_id', 'invoice_number', 'transaction_date', 'due_date',
            'subtotal', 'tax_amount', 'tax_rate', 'total_amount', 'currency', 'payment_method',
            'category', 'merchant_address', 'description', 'payment_status',
        ];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $edited_fields)) {
                $value = $edited_fields[$field];
                if (in_array($field, ['subtotal', 'tax_amount', 'tax_rate', 'total_amount'], true)) {
                    $update[$field] = (float) $value;
                } else {
                    $update[$field] = is_string($value) ? sanitize_text_field($value) : $value;
                }
            }
        }

        $expense = (object) array_merge((array) $expense, $update);
        if (empty($expense->total_amount) || empty($expense->transaction_date)) {
            return new WP_Error('validation', 'Total amount and transaction date are required');
        }

        $confidence = (float) ($expense->ocr_confidence ?? 0);
        $risk = $expense->ocr_risk_level ?: 'medium';

        if ($confidence >= self::CONFIDENCE_THRESHOLD && $risk === 'low') {
            $workflow_event = 'submit';
            $event = 'expense_submitted';
            $log_event = 'expense_submitted';
            $target_status = 'submitted';
        } else {
            $workflow_event = 'ai_review';
            $event = 'expense_escalated';
            $log_event = 'expense_escalated_to_ai_review';
            $target_status = 'ai_review';
        }

        unset($update['workflow_status']);
        if (!empty($update)) {
            $wpdb->update($table, $update, ['id' => intval($expense_id), 'org_id' => intval($org_id)]);
        }

        if (!class_exists('OraBooks_Workflow')) {
            return new WP_Error('workflow_unavailable', 'Workflow engine unavailable');
        }

        $transition = OraBooks_Workflow::transition('expense', (int) $expense_id, $workflow_event, [
            'user_id' => (int) $user_id,
            'org_id'  => (int) $org_id,
        ]);
        if (is_wp_error($transition)) {
            return $transition;
        }

        if (function_exists('orabooks_publish_event')) {
            orabooks_publish_event($event, intval($expense_id), [
                'expense_id'  => intval($expense_id),
                'org_id'      => intval($org_id),
                'confidence'  => $confidence,
                'risk_level'  => $risk,
                'total_amount'=> (float) $expense->total_amount,
            ]);
        }

        if ($target_status === 'ai_review' && class_exists('OraBooks_Ai_Review')) {
            OraBooks_Ai_Review::enqueue(
                intval($org_id),
                'expense',
                intval($expense_id),
                null,
                [
                    'confidence'        => $confidence,
                    'risk_level'        => $risk,
                    'explanation'       => 'Expense OCR confidence below threshold or elevated risk',
                    'model_version'     => OraBooks_Ai_Providers::model_version('ocr'),
                    'escalation_reason' => 'expense_low_confidence',
                ],
                (float) $expense->total_amount
            );
        }

        orabooks_log_event($log_event, "Expense #{$expense_id} routed to {$target_status}", 'info', [
            'expense_id' => intval($expense_id),
            'confidence' => $confidence,
            'risk_level' => $risk,
        ], $user_id, $org_id);

        $updated = self::get_expense($expense_id, $org_id);
        return self::format_expense($updated, true);
    }

    public static function approve_expense($expense_id, $org_id, $user_id) {
        global $wpdb;

        $expense = self::get_expense($expense_id, $org_id);
        if (!$expense) {
            return new WP_Error('not_found', 'Expense not found');
        }

        if (!in_array($expense->workflow_status, ['submitted', 'ai_review'], true)) {
            return new WP_Error('invalid_status', 'Expense is not awaiting approval');
        }

        if (!class_exists('OraBooks_Workflow')) {
            return new WP_Error('workflow_unavailable', 'Workflow engine unavailable');
        }

        $transition = OraBooks_Workflow::transition('expense', (int) $expense_id, 'approve', [
            'user_id' => (int) $user_id,
            'org_id'  => (int) $org_id,
            'row_updates' => [
                'approved_by' => (int) $user_id,
                'approved_at' => current_time('mysql'),
            ],
        ]);
        if (is_wp_error($transition)) {
            return $transition;
        }

        orabooks_log_event('expense_approved', "Expense #{$expense_id} approved", 'info', [
            'expense_id' => intval($expense_id),
        ], $user_id, $org_id);

        if (class_exists('OraBooks_Ai_Review')) {
            OraBooks_Ai_Review::resolve_ai_review_by_resource(intval($org_id), 'expense', intval($expense_id), $user_id);
        }

        $auto_post = (bool) get_option('orabooks_expense_auto_post_on_approve', true);
        if ($auto_post) {
            return self::post_expense($expense_id, $org_id, $user_id);
        }

        return self::format_expense(self::get_expense($expense_id, $org_id), true);
    }

    public static function reject_expense($expense_id, $org_id, $user_id, $reason) {
        global $wpdb;

        if (empty($reason)) {
            return new WP_Error('reason_required', 'Rejection reason is required');
        }

        $expense = self::get_expense($expense_id, $org_id);
        if (!$expense) {
            return new WP_Error('not_found', 'Expense not found');
        }

        if (!in_array($expense->workflow_status, ['submitted', 'ai_review'], true)) {
            return new WP_Error('invalid_status', 'Expense is not awaiting approval');
        }

        if (!class_exists('OraBooks_Workflow')) {
            return new WP_Error('workflow_unavailable', 'Workflow engine unavailable');
        }

        $transition = OraBooks_Workflow::transition('expense', (int) $expense_id, 'reject', [
            'user_id' => (int) $user_id,
            'org_id'  => (int) $org_id,
            'reason'  => $reason,
        ]);
        if (is_wp_error($transition)) {
            return $transition;
        }

        if (class_exists('OraBooks_Ai_Review')) {
            OraBooks_Ai_Review::resolve_ai_review_by_resource(intval($org_id), 'expense', intval($expense_id), $user_id);
        }

        orabooks_log_event('expense_rejected', "Expense #{$expense_id} rejected: {$reason}", 'warning', [
            'expense_id' => intval($expense_id),
            'reason'     => $reason,
        ], $user_id, $org_id);

        return self::format_expense(self::get_expense($expense_id, $org_id), true);
    }

    public static function post_expense($expense_id, $org_id, $user_id) {
        global $wpdb;

        $expense = self::get_expense($expense_id, $org_id);
        if (!$expense) {
            return new WP_Error('not_found', 'Expense not found');
        }

        if ($expense->workflow_status === 'posted' || $expense->workflow_status === 'locked') {
            return self::format_expense($expense, true);
        }

        if ($expense->workflow_status !== 'approved') {
            return new WP_Error('invalid_status', 'Expense must be approved before posting');
        }

        $expense_tax = round(floatval($expense->tax_amount ?? 0), 2);
        if ($expense_tax > 0 && class_exists('OraBooks_Tax')) {
            $tax_type = sanitize_text_field($expense->tax_type ?? 'Sales Tax');
            $account_check = OraBooks_Tax::validate_tax_posting_accounts($org_id, $tax_type);
            if (is_wp_error($account_check)) {
                return $account_check;
            }
        }

        if (!class_exists('OraBooks_Posting') || !class_exists('OraBooks_COA')) {
            return new WP_Error('missing_module', 'Posting module unavailable');
        }

        $expense_account = self::category_account_code($expense->category);
        $cash_account = '1000';

        $journal_id = OraBooks_Posting::create_journal([
            'org_id'           => intval($org_id),
            'transaction_date' => $expense->transaction_date,
            'source_type'      => 'expense',
            'source_id'        => intval($expense_id),
            'metadata'         => ['vendor' => $expense->vendor, 'category' => $expense->category],
            'idempotency_key'  => 'expense-post-' . intval($expense_id),
        ], (int) $expense->created_by);

        if (!$journal_id) {
            return new WP_Error('journal_error', 'Failed to create journal for expense');
        }

        $amount = (float) $expense->total_amount;
        $desc = 'Expense: ' . ($expense->vendor ?: 'Receipt');

        $lines_result = OraBooks_Posting::add_lines($journal_id, [
            ['account_code' => $expense_account, 'debit' => $amount, 'credit' => 0, 'description' => $desc],
            ['account_code' => $cash_account, 'debit' => 0, 'credit' => $amount, 'description' => $desc],
        ]);

        if (is_wp_error($lines_result)) {
            return $lines_result;
        }

        $submit = OraBooks_Posting::submit_journal($journal_id, (int) $expense->created_by);
        if (is_wp_error($submit)) {
            return $submit;
        }

        if (is_array($submit) && !empty($submit['ai_review'])) {
            return new WP_Error('ai_review', 'Expense journal queued for AI review before posting');
        }

        OraBooks_Posting::approve_journal($journal_id, $user_id);
        $post = OraBooks_Posting::post_journal($journal_id, $user_id);
        if (is_wp_error($post)) {
            return $post;
        }

        if (!class_exists('OraBooks_Workflow')) {
            return new WP_Error('workflow_unavailable', 'Workflow engine unavailable');
        }

        $transition = OraBooks_Workflow::transition('expense', (int) $expense_id, 'post', [
            'user_id' => (int) $user_id,
            'org_id'  => (int) $org_id,
            'row_updates' => [
                'posted_by'   => (int) $user_id,
                'posted_at'   => current_time('mysql'),
                'journal_id'  => (int) $journal_id,
            ],
        ]);
        if (is_wp_error($transition)) {
            return $transition;
        }

        $lock_transition = OraBooks_Workflow::transition('expense', (int) $expense_id, 'lock', [
            'user_id' => (int) $user_id,
            'org_id'  => (int) $org_id,
            'row_updates' => [
                'lock_status' => 'locked',
            ],
        ]);
        if (is_wp_error($lock_transition)) {
            return $lock_transition;
        }

        if (class_exists('OraBooks_Tax') && method_exists('OraBooks_Tax', 'create_snapshot_from_expense')) {
            OraBooks_Tax::create_snapshot_from_expense(self::get_expense($expense_id, $org_id));
        } elseif (class_exists('OraBooks_Tax')) {
            $posted = self::get_expense($expense_id, $org_id);
            if ($posted) {
                $tax_base = self::get_expense_tax_base($posted);
                $payload = [
                    'org_id'       => (int) $org_id,
                    'amount'       => $tax_base,
                    'jurisdiction' => 'US',
                    'transaction_date' => $posted->transaction_date ?? current_time('Y-m-d'),
                ];
                if (!empty($posted->tax_override_reason)) {
                    $payload['override'] = true;
                    $payload['override_tax_rate'] = (float) ($posted->tax_rate ?? 0);
                    $payload['override_reason'] = $posted->tax_override_reason;
                }
                OraBooks_Tax::create_snapshot(array_merge($payload, [
                    'transaction_id'   => (int) $expense_id,
                    'transaction_type' => 'expense',
                ]));
            }
        }

        orabooks_log_event('expense_posted', "Expense #{$expense_id} posted to ledger", 'info', [
            'expense_id' => intval($expense_id),
            'journal_id' => intval($journal_id),
        ], $user_id, $org_id);

        return self::format_expense(self::get_expense($expense_id, $org_id), true);
    }

    private static function category_account_code($category) {
        $key = strtolower(trim((string) $category));
        foreach (self::$category_accounts as $label => $code) {
            if ($key === $label || str_contains($key, $label)) {
                return $code;
            }
        }
        return '5000';
    }

    private function require_expense_access($user_id, $org_id, $permission = 'view_expenses') {
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, $permission)) {
            orabooks_json_error('Permission denied', 403);
        }
    }

    public function ajax_upload_receipt() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_expense_access($user_id, $org_id, 'manage_expenses');

        if (empty($_FILES['receipt_file'])) {
            orabooks_json_error('Receipt file is required', 400);
        }

        $file = $_FILES['receipt_file'];
        if (!empty($file['error'])) {
            orabooks_json_error('Upload failed', 400);
        }

        $content = file_get_contents($file['tmp_name']);
        $result = self::upload_receipt(
            $org_id,
            $user_id,
            sanitize_file_name($file['name']),
            $content,
            sanitize_text_field($file['type'] ?? ''),
            sanitize_text_field($_POST['idempotency_key'] ?? '')
        );

        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success(['expense' => $result], 'Receipt uploaded and processed');
    }

    public function ajax_get() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_GET['org_id'] ?? $_POST['org_id'] ?? 0);
        $expense_id = intval($_GET['expense_id'] ?? $_POST['expense_id'] ?? 0);

        $this->require_expense_access($user_id, $org_id);

        $expense = self::get_expense($expense_id, $org_id);
        if (!$expense) {
            orabooks_json_error('Expense not found', 404);
        }

        orabooks_json_success(['expense' => self::format_expense($expense, true)]);
    }

    public function ajax_confirm() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $expense_id = intval($_POST['expense_id'] ?? 0);
        $idempotency_key = sanitize_text_field($_POST['idempotency_key'] ?? '');

        $this->require_expense_access($user_id, $org_id, 'manage_expenses');

        $edited = [];
        if (!empty($_POST['edited_fields'])) {
            $decoded = json_decode(stripslashes((string) $_POST['edited_fields']), true);
            if (is_array($decoded)) {
                $edited = $decoded;
            }
        }

        $result = self::confirm_submit($expense_id, $org_id, $user_id, $idempotency_key, $edited);
        if (is_wp_error($result)) {
            $code = $result->get_error_code() === 'duplicate' ? 409 : 400;
            orabooks_json_error($result->get_error_message(), $code);
        }

        orabooks_json_success(['expense' => $result], 'Expense submitted');
    }

    public function ajax_approve() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $expense_id = intval($_POST['expense_id'] ?? 0);

        $this->require_expense_access($user_id, $org_id, 'approve_expense');

        $result = self::approve_expense($expense_id, $org_id, $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success(['expense' => $result], 'Expense approved');
    }

    public function ajax_reject() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $expense_id = intval($_POST['expense_id'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? '');

        $this->require_expense_access($user_id, $org_id, 'approve_expense');

        $result = self::reject_expense($expense_id, $org_id, $user_id, $reason);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success(['expense' => $result], 'Expense rejected');
    }

    public function ajax_post() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $expense_id = intval($_POST['expense_id'] ?? 0);

        $this->require_expense_access($user_id, $org_id, 'approve_expense');

        $result = self::post_expense($expense_id, $org_id, $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success(['expense' => $result], 'Expense posted');
    }

    public function ajax_list() {
        $user_id = orabooks_get_current_user_id();
        $org_id = intval($_GET['org_id'] ?? $_POST['org_id'] ?? 0);
        $status = sanitize_text_field($_GET['status'] ?? $_POST['status'] ?? '');

        $this->require_expense_access($user_id, $org_id);

        $rows = self::list_expenses($org_id, ['status' => $status, 'limit' => 50]);
        orabooks_json_success([
            'expenses' => array_map(function ($row) {
                return self::format_expense($row);
            }, $rows ?: []),
        ]);
    }

    private function can_override_expense_tax($user_id, $org_id) {
        return current_user_can('manage_options')
            || OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_org_settings')
            || OraBooks_RBAC::require_permission($user_id, $org_id, 'approve_journal')
            || OraBooks_RBAC::require_permission($user_id, $org_id, 'approve_expense');
    }

    public function ajax_override_tax() {
        global $wpdb;

        $user_id = orabooks_get_current_user_id();
        $expense_id = intval($_POST['expense_id'] ?? 0);
        $org_id = intval($_POST['org_id'] ?? 0);

        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        if (!$expense_id) {
            orabooks_json_error('Expense ID required', 400);
        }

        if (!$org_id) {
            $table_expenses = OraBooks_Database::table(self::TABLE_EXPENSES);
            $org_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT org_id FROM {$table_expenses} WHERE id = %d",
                $expense_id
            ));
        }

        if (!$org_id) {
            orabooks_json_error('Organization ID required', 400);
        }

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }

        if (!$this->can_override_expense_tax($user_id, $org_id)) {
            orabooks_json_error('Permission denied', 403);
        }

        $result = self::override_expense_tax(
            $org_id,
            $expense_id,
            floatval($_POST['new_tax_rate'] ?? 0),
            sanitize_text_field($_POST['reason_code'] ?? ''),
            $user_id,
            sanitize_text_field($_POST['jurisdiction'] ?? 'US')
        );

        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success($result, 'Tax override applied');
    }

    public function ajax_clear_tax_override() {
        global $wpdb;

        $user_id = orabooks_get_current_user_id();
        $expense_id = intval($_POST['expense_id'] ?? 0);
        $org_id = intval($_POST['org_id'] ?? 0);

        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        if (!$expense_id) {
            orabooks_json_error('Expense ID required', 400);
        }

        if (!$org_id) {
            $table_expenses = OraBooks_Database::table(self::TABLE_EXPENSES);
            $org_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT org_id FROM {$table_expenses} WHERE id = %d",
                $expense_id
            ));
        }

        if (!$org_id) {
            orabooks_json_error('Organization ID required', 400);
        }

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }

        if (!$this->can_override_expense_tax($user_id, $org_id)) {
            orabooks_json_error('Permission denied', 403);
        }

        $result = self::clear_expense_tax_override(
            $org_id,
            $expense_id,
            $user_id,
            sanitize_text_field($_POST['jurisdiction'] ?? 'US')
        );

        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success(['expense' => $result], 'Tax override cleared');
    }
}
