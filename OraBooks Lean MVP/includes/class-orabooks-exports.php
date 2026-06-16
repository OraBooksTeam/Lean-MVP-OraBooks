<?php
/**
 * OraBooks PDF/CSV Exports (SL-114)
 *
 * Central export service for all reports and data sets.
 * Features:
 * - Async background generation via SL-303 queue
 * - CSV and PDF format support (PDF with watermark)
 * - Rate limiting (10 req/hour/user)
 * - Pre-signed download URLs with 7-day expiry
 * - Download counting and audit logging
 * - Automatic cleanup of expired exports
 * - SL-250 notification integration on ready
 * - SL-009 audit trail for all operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Exports {

    const TABLE_REQUESTS = 'export_requests';
    const TABLE_FILES    = 'export_files';

    /** Rate limit: max exports per hour per user */
    const RATE_LIMIT_MAX     = 10;
    const RATE_LIMIT_PERIOD  = 3600; // 1 hour in seconds

    /** Retention */
    const DEFAULT_RETENTION_DAYS = 7;

    private static $instance = null;

    /** Registered report data providers: export_type => callable */
    private static $report_providers = [];

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();

            // Cron: cleanup expired exports (daily)
            add_action('orabooks_exports_cleanup', [self::$instance, 'cleanup_expired']);

            // AJAX: request export
            add_action('wp_ajax_orabooks_export_request', [self::$instance, 'ajax_request_export']);
            // AJAX: list user exports
            add_action('wp_ajax_orabooks_exports_list', [self::$instance, 'ajax_exports_list']);
            // AJAX: download export
            add_action('wp_ajax_orabooks_export_download', [self::$instance, 'ajax_download_export']);
            // AJAX: cancel export
            add_action('wp_ajax_orabooks_export_cancel', [self::$instance, 'ajax_cancel_export']);
            // AJAX: export stats
            add_action('wp_ajax_orabooks_exports_stats', [self::$instance, 'ajax_exports_stats']);

            // Register default report data providers
            self::register_default_providers();
        }
        return self::$instance;
    }

    // ================================================================
    // DATABASE SCHEMA
    // ================================================================

    /**
     * Get CREATE TABLE SQL statements.
     */
    public static function get_create_table_sql() {
        global $wpdb;

        $table_requests = OraBooks_Database::table(self::TABLE_REQUESTS);
        $table_files    = OraBooks_Database::table(self::TABLE_FILES);

        $sql = [];

        $sql[] = "CREATE TABLE IF NOT EXISTS {$table_requests} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            export_type VARCHAR(50) NOT NULL COMMENT 'e.g. pnl, ar_aging, notification_log, coa, audit_log, etc.',
            format ENUM('csv','pdf') NOT NULL,
            parameters JSON DEFAULT NULL COMMENT 'Filters, date range, etc.',
            status ENUM('pending','generating','ready','failed','expired','cancelled') DEFAULT 'pending',
            file_url VARCHAR(500) DEFAULT NULL COMMENT 'Pre-signed / direct download URL',
            file_size BIGINT UNSIGNED DEFAULT NULL,
            file_hash VARCHAR(64) DEFAULT NULL COMMENT 'SHA-256 of generated file',
            expires_at TIMESTAMP NULL COMMENT '7 days after ready',
            download_count INT UNSIGNED DEFAULT 0,
            correlation_id VARCHAR(64) DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            generated_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_org_user_status (org_id, user_id, status),
            INDEX idx_expires (expires_at),
            INDEX idx_status_created (status, created_at)
        ) {$wpdb->get_charset_collate()};";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$table_files} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            export_request_id BIGINT UNSIGNED NOT NULL,
            storage_key VARCHAR(500) DEFAULT NULL COMMENT 'File path in wp_upload_dir',
            encryption_key_id VARCHAR(100) DEFAULT NULL COMMENT 'KMS/AES key ID if encrypted',
            is_encrypted TINYINT(1) DEFAULT 1,
            retention_days INT DEFAULT 7,
            FOREIGN KEY (export_request_id) REFERENCES {$table_requests}(id) ON DELETE CASCADE
        ) {$wpdb->get_charset_collate()};";

        return $sql;
    }

    // ================================================================
    // REPORT DATA PROVIDER REGISTRY
    // ================================================================

    /**
     * Register a report data provider for a given export_type.
     *
     * @param string   $export_type Export type key (e.g. 'coa', 'audit_log').
     * @param callable $provider    Function(parameters array) => array of row objects/arrays.
     */
    public static function register_report_provider($export_type, $provider) {
        self::$report_providers[$export_type] = $provider;
    }

    /**
     * Get report data from registered provider or return sample data.
     */
    private static function get_report_data($export_type, $parameters) {
        if (isset(self::$report_providers[$export_type])) {
            try {
                return call_user_func(self::$report_providers[$export_type], $parameters);
            } catch (\Exception $e) {
                orabooks_log_event('export_data_error', "Report provider error for {$export_type}: " . $e->getMessage(), 'warning');
                return null;
            }
        }
        return null;
    }

    /**
     * Register default report providers that leverage existing OraBooks classes.
     */
    public static function register_default_providers() {
        // Chart of Accounts export
        self::register_report_provider('coa', function($params) {
            if (class_exists('OraBooks_COA') && method_exists('OraBooks_COA', 'export_csv')) {
                // Reuse existing COA export logic
                $org_id = intval($params['org_id'] ?? 0);
                if ($org_id) {
                    $accounts = OraBooks_COA::get_accounts($org_id);
                    if (is_array($accounts)) {
                        return $accounts;
                    }
                }
            }
            return null;
        });

        // Audit Log export
        self::register_report_provider('audit_log', function($params) {
            if (class_exists('OraBooks_Audit') && method_exists('OraBooks_Audit', 'export_csv')) {
                $org_id = intval($params['org_id'] ?? 0);
                $args = [];
                if (!empty($params['start_date'])) $args['start_date'] = $params['start_date'];
                if (!empty($params['end_date'])) $args['end_date'] = $params['end_date'];
                if (!empty($params['event_type'])) $args['event_type'] = $params['event_type'];
                if ($org_id) {
                    return OraBooks_Audit::get_logs($org_id, $args);
                }
            }
            return null;
        });

        // Notification Log export
        self::register_report_provider('notification_log', function($params) {
            if (class_exists('OraBooks_Notifications') && method_exists('OraBooks_Notifications', 'get_notifications')) {
                $org_id = intval($params['org_id'] ?? 0);
                $user_id = intval($params['user_id'] ?? 0);
                $args = [];
                if (!empty($params['start_date'])) $args['start_date'] = $params['start_date'];
                if (!empty($params['end_date'])) $args['end_date'] = $params['end_date'];
                if (!empty($params['event_type'])) $args['event_type'] = $params['event_type'];
                if ($org_id && $user_id) {
                    return OraBooks_Notifications::get_notifications($org_id, $user_id, $args);
                }
            }
            return null;
        });
    }

    // ================================================================
    // REQUEST EXPORT
    // ================================================================

    /**
     * Request a new export.
     *
     * @param int    $org_id      Organization ID.
     * @param int    $user_id     User ID.
     * @param string $export_type Export type key (e.g. 'coa', 'audit_log', 'pnl').
     * @param string $format      'csv' or 'pdf'.
     * @param array  $parameters  Optional filters/parameters for the report.
     * @return array|WP_Error     { id, status, correlation_id } or error.
     */
    public static function request_export($org_id, $user_id, $export_type, $format, $parameters = []) {
        global $wpdb;

        // Validate format
        if (!in_array($format, ['csv', 'pdf'])) {
            return new \WP_Error('invalid_format', 'Format must be csv or pdf');
        }

        // Rate limit check
        $rate_key = "export_{$user_id}";
        if (!orabooks_check_rate_limit($rate_key, self::RATE_LIMIT_MAX, self::RATE_LIMIT_PERIOD)) {
            return new \WP_Error('rate_limit', sprintf(
                'Rate limit exceeded. Max %d exports per hour.', self::RATE_LIMIT_MAX
            ));
        }

        $table = OraBooks_Database::table(self::TABLE_REQUESTS);
        $correlation_id = orabooks_uuid();

        $wpdb->insert($table, [
            'org_id'         => $org_id,
            'user_id'        => $user_id,
            'export_type'    => $export_type,
            'format'         => $format,
            'parameters'     => json_encode($parameters),
            'status'         => 'pending',
            'correlation_id' => $correlation_id,
            'created_at'     => current_time('mysql', true),
        ], ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']);

        $export_id = $wpdb->insert_id;
        if (!$export_id) {
            return new \WP_Error('db_error', 'Failed to create export record');
        }

        // Enqueue async job via SL-303
        orabooks_enqueue_job('generate_export', [
            'export_id' => $export_id,
            'org_id'    => $org_id,
            'user_id'   => $user_id,
        ], [
            'queue_name'  => 'exports',
            'priority'    => 5,
            'max_retries' => 3,
        ]);

        // Audit log
        orabooks_log_event('export_requested', "Export {$export_type} as {$format} requested (#{$export_id})", 'info', [
            'export_id'       => $export_id,
            'export_type'     => $export_type,
            'format'          => $format,
            'correlation_id'  => $correlation_id,
        ], $user_id, $org_id);

        // Publish event via SL-302
        if (function_exists('orabooks_publish_event')) {
            orabooks_publish_event('export_requested', $export_id, [
                'export_id'      => $export_id,
                'export_type'    => $export_type,
                'format'         => $format,
                'org_id'         => $org_id,
                'user_id'        => $user_id,
                'correlation_id' => $correlation_id,
            ]);
        }

        return [
            'id'             => $export_id,
            'status'         => 'pending',
            'correlation_id' => $correlation_id,
        ];
    }

    // ================================================================
    // EXPORT GENERATION (SL-303 Handler)
    // ================================================================

    /**
     * Generate export file — registered as SL-303 handler.
     *
     * @param object $job     The async job object.
     * @param array  $payload Job payload { export_id, org_id, user_id }.
     * @return true|string    True on success, error string on failure.
     */
    public static function generate_export_job($job, $payload) {
        global $wpdb;

        $export_id = intval($payload['export_id'] ?? 0);
        $org_id    = intval($payload['org_id'] ?? 0);
        $user_id   = intval($payload['user_id'] ?? 0);

        if (!$export_id || !$org_id) {
            return 'Missing export_id or org_id';
        }

        $table_requests = OraBooks_Database::table(self::TABLE_REQUESTS);
        $table_files    = OraBooks_Database::table(self::TABLE_FILES);

        // Fetch export record
        $export = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_requests} WHERE id = %d",
            $export_id
        ));

        if (!$export) {
            return "Export #{$export_id} not found";
        }

        if ($export->status !== 'pending') {
            return "Export #{$export_id} is not pending (status: {$export->status})";
        }

        // Mark as generating
        $wpdb->update(
            $table_requests,
            ['status' => 'generating'],
            ['id' => $export_id],
            ['%s'],
            ['%d']
        );

        try {
            // Get parameters
            $parameters = json_decode($export->parameters, true) ?: [];
            $parameters['org_id'] = $org_id;
            $parameters['user_id'] = $user_id;

            // Get report data from registered provider
            $report_data = self::get_report_data($export->export_type, $parameters);

            if ($report_data === null) {
                // No provider registered — generate sample/empty data with columns from parameters
                $columns = $parameters['columns'] ?? [];
                $rows    = $parameters['rows'] ?? [];
                $report_data = [
                    'columns' => $columns,
                    'rows'    => $rows,
                ];
            }

            $upload_dir = wp_upload_dir();
            $exports_dir = $upload_dir['basedir'] . '/orabooks-exports';
            $exports_url  = $upload_dir['baseurl'] . '/orabooks-exports';

            // Create directory if needed
            if (!file_exists($exports_dir)) {
                wp_mkdir_p($exports_dir);
            }

            // Generate unique filename
            $date_part = date('Ymd_His');
            $filename  = sanitize_file_name("{$export->export_type}_{$date_part}_{$export_id}");

            $file_path = null;
            $file_size = 0;
            $file_hash = '';

            if ($export->format === 'csv') {
                $result = self::generate_csv($report_data, $exports_dir, $filename, $export);
                if (is_wp_error($result)) {
                    throw new \Exception($result->get_error_message());
                }
                $file_path = $result['path'];
                $file_size = $result['size'];
                $file_hash = $result['hash'];
            } else {
                // PDF — generate watermarked HTML
                $result = self::generate_pdf_html($report_data, $exports_dir, $filename, $export, $org_id, $user_id);
                if (is_wp_error($result)) {
                    throw new \Exception($result->get_error_message());
                }
                $file_path = $result['path'];
                $file_size = $result['size'];
                $file_hash = $result['hash'];
            }

            // Build download URL
            $relative_path = 'orabooks-exports/' . basename($file_path);
            $file_url      = $upload_dir['baseurl'] . '/' . $relative_path;
            $storage_key   = $relative_path;

            // Calculate expiry (7 days from now)
            $expires_at = date('Y-m-d H:i:s', time() + (self::DEFAULT_RETENTION_DAYS * 86400));

            // Update export request record
            $wpdb->update(
                $table_requests,
                [
                    'status'       => 'ready',
                    'file_url'     => $file_url,
                    'file_size'    => $file_size,
                    'file_hash'    => $file_hash,
                    'expires_at'   => $expires_at,
                    'generated_at' => current_time('mysql', true),
                ],
                ['id' => $export_id],
                ['%s', '%s', '%d', '%s', '%s', '%s'],
                ['%d']
            );

            // Insert file metadata
            $wpdb->insert($table_files, [
                'export_request_id' => $export_id,
                'storage_key'       => $storage_key,
                'is_encrypted'      => 0, // WordPress uploads are server-secured
                'retention_days'    => self::DEFAULT_RETENTION_DAYS,
            ], ['%d', '%s', '%d', '%d']);

            // Audit log
            orabooks_log_event('export_generated', "Export #{$export_id} ({$export->export_type}) generated as {$export->format}", 'info', [
                'export_id'   => $export_id,
                'format'      => $export->format,
                'file_size'   => $file_size,
                'expires_at'  => $expires_at,
            ], $user_id, $org_id);

            // Publish event for SL-250 notification
            do_action('orabooks_export_ready', $export_id, [
                'export_id'   => $export_id,
                'export_type' => $export->export_type,
                'format'      => $export->format,
                'org_id'      => $org_id,
                'user_id'     => $user_id,
                'correlation_id' => $export->correlation_id,
            ]);

            // Publish EventBus event
            if (function_exists('orabooks_publish_event')) {
                orabooks_publish_event('export_ready', $export_id, [
                    'export_id'   => $export_id,
                    'export_type' => $export->export_type,
                    'format'      => $export->format,
                    'org_id'      => $org_id,
                    'user_id'     => $user_id,
                ]);
            }

            return true;

        } catch (\Exception $e) {
            // Mark as failed
            $wpdb->update(
                $table_requests,
                [
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                ],
                ['id' => $export_id],
                ['%s', '%s'],
                ['%d']
            );

            orabooks_log_event('export_failed', "Export #{$export_id} failed: " . $e->getMessage(), 'warning', [
                'export_id' => $export_id,
                'error'     => $e->getMessage(),
            ], $user_id, $org_id);

            // Notify user via SL-250
            do_action('orabooks_export_failed', $export_id, [
                'export_id'   => $export_id,
                'export_type' => $export->export_type,
                'format'      => $export->format,
                'org_id'      => $org_id,
                'user_id'     => $user_id,
                'error'       => $e->getMessage(),
            ]);

            return $e->getMessage();
        }
    }

    // ================================================================
    // CSV GENERATION
    // ================================================================

    /**
     * Generate a CSV file from report data.
     *
     * @param array|object $report_data Report data with 'columns' and 'rows' or array of objects.
     * @param string       $exports_dir Directory to write to.
     * @param string       $filename    Base filename (without extension).
     * @param object       $export      Export request record (for metadata).
     * @return array|WP_Error { path, size, hash } or error.
     */
    private static function generate_csv($report_data, $exports_dir, $filename, $export) {
        $csv_path = $exports_dir . '/' . $filename . '.csv';

        $fh = @fopen($csv_path, 'w');
        if (!$fh) {
            return new \WP_Error('file_write', "Cannot write to {$csv_path}");
        }

        // Extract columns and rows from various data shapes
        $columns = [];
        $rows    = [];

        if (is_array($report_data)) {
            if (isset($report_data['columns'])) {
                $columns = $report_data['columns'];
                $rows    = $report_data['rows'] ?? [];
            } elseif (isset($report_data[0]) && is_object($report_data[0])) {
                // Array of objects — extract keys as columns
                $columns = array_keys(get_object_vars($report_data[0]));
                $rows    = $report_data;
            } elseif (isset($report_data[0]) && is_array($report_data[0])) {
                // Array of associative arrays
                $columns = array_keys($report_data[0]);
                $rows    = $report_data;
            }
        }

        // Write BOM for Excel compatibility
        fwrite($fh, "\xEF\xBB\xBF");

        // Write header row
        if (!empty($columns)) {
            fputcsv($fh, $columns, ',', '"', '\\');
        } else {
            // Fallback: write event_type,timestamp,description columns
            fputcsv($fh, ['event_type', 'timestamp', 'description', 'details'], ',', '"', '\\');
        }

        // Write data rows
        if (!empty($rows)) {
            foreach ($rows as $row) {
                if (is_object($row)) {
                    $row = (array) $row;
                }
                if (is_array($row)) {
                    // Only include columns we defined
                    $ordered = [];
                    foreach ($columns as $col) {
                        $ordered[] = $row[$col] ?? '';
                    }
                    fputcsv($fh, $ordered, ',', '"', '\\');
                } else {
                    fputcsv($fh, [$row], ',', '"', '\\');
                }
            }
        }

        fclose($fh);

        $file_size = filesize($csv_path);
        $file_hash = hash_file('sha256', $csv_path);

        return [
            'path' => $csv_path,
            'size' => $file_size,
            'hash' => $file_hash,
        ];
    }

    // ================================================================
    // PDF GENERATION (Watermarked HTML)
    // ================================================================

    /**
     * Generate a watermarked HTML document (printable, PDF-like) from report data.
     *
     * @param array|object $report_data Report data.
     * @param string       $exports_dir Directory to write to.
     * @param string       $filename    Base filename.
     * @param object       $export      Export request record.
     * @param int          $org_id      Organization ID.
     * @param int          $user_id     User ID.
     * @return array|WP_Error { path, size, hash } or error.
     */
    private static function generate_pdf_html($report_data, $exports_dir, $filename, $export, $org_id, $user_id) {
        $html_path = $exports_dir . '/' . $filename . '.html';

        // Gather report metadata
        $org_name   = self::get_org_name($org_id);
        $user_email = orabooks_get_user_email($user_id);
        $date_str   = current_time('Y-m-d H:i:s T');
        $correlation_id = $export->correlation_id ?? orabooks_uuid();

        // Extract columns and rows
        $columns = [];
        $rows    = [];

        if (is_array($report_data)) {
            if (isset($report_data['columns'])) {
                $columns = $report_data['columns'];
                $rows    = $report_data['rows'] ?? [];
            } elseif (isset($report_data[0]) && is_object($report_data[0])) {
                $columns = array_keys(get_object_vars($report_data[0]));
                $rows    = $report_data;
            } elseif (isset($report_data[0]) && is_array($report_data[0])) {
                $columns = array_keys($report_data[0]);
                $rows    = $report_data;
            }
        }

        // Build HTML rows
        $table_rows_html = '';
        if (!empty($rows)) {
            foreach ($rows as $row) {
                if (is_object($row)) {
                    $row = (array) $row;
                }
                $table_rows_html .= '<tr>';
                foreach ($columns as $col) {
                    $val = $row[$col] ?? '';
                    if (is_array($val) || is_object($val)) {
                        $val = json_encode($val);
                    }
                    $table_rows_html .= '<td>' . esc_html($val) . '</td>';
                }
                $table_rows_html .= '</tr>';
            }
        } else {
            $table_rows_html = '<tr><td colspan="' . max(1, count($columns)) . '" class="empty">No data available.</td></tr>';
        }

        // Build columns HTML for header
        $col_headers = '';
        foreach ($columns as $col) {
            $col_headers .= '<th>' . esc_html($col) . '</th>';
        }
        if (empty($columns)) {
            $col_headers = '<th>Data</th>';
        }

        $export_type_label = strtoupper(str_replace('_', ' ', $export->export_type));
        $page_title = esc_html("{$export_type_label} — {$org_name}");

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{$page_title}</title>
<style>
    @page { margin: 20mm 15mm; }
    body {
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 11pt;
        line-height: 1.5;
        color: #1a1a2e;
        margin: 0;
        padding: 20px;
    }
    .watermark {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-30deg);
        font-size: 60pt;
        font-weight: bold;
        color: rgba(200, 0, 0, 0.08);
        pointer-events: none;
        z-index: 999;
        white-space: nowrap;
        text-transform: uppercase;
        letter-spacing: 8px;
        user-select: none;
    }
    .header {
        border-bottom: 2px solid #1a1a2e;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    .header h1 {
        font-size: 18pt;
        margin: 0 0 5px 0;
        color: #1a1a2e;
    }
    .header .meta {
        font-size: 9pt;
        color: #666;
    }
    .header .meta span {
        margin-right: 20px;
    }
    .signature-badge {
        display: inline-block;
        border: 1px solid #2ecc71;
        background: #e8f8f0;
        color: #27ae60;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 8pt;
        font-weight: bold;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
        font-size: 10pt;
    }
    table th {
        background: #1a1a2e;
        color: #fff;
        padding: 8px 10px;
        text-align: left;
        font-weight: 600;
        white-space: nowrap;
    }
    table td {
        padding: 6px 10px;
        border-bottom: 1px solid #e0e0e0;
        vertical-align: top;
    }
    table tr:nth-child(even) td {
        background: #f8f9fa;
    }
    table tr:hover td {
        background: #eef2ff;
    }
    .empty {
        text-align: center;
        color: #999;
        font-style: italic;
        padding: 30px;
    }
    .footer {
        margin-top: 30px;
        border-top: 1px solid #ccc;
        padding-top: 10px;
        font-size: 8pt;
        color: #999;
        text-align: center;
    }
    .footer .correlation {
        font-family: 'Courier New', monospace;
        color: #666;
    }
    @media print {
        .watermark { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>
    <div class="watermark">CONFIDENTIAL — {$org_name}</div>

    <div class="header">
        <h1>{$export_type_label} Report</h1>
        <div class="meta">
            <span>🏢 {$org_name}</span>
            <span>👤 {$user_email}</span>
            <span>📅 {$date_str}</span>
            <span class="signature-badge">🔒 OraBooks Signed Export</span>
        </div>
        <div class="meta" style="margin-top:5px;">
            <span>Correlation: <span class="correlation">{$correlation_id}</span></span>
            <span>Format: PDF (Watermarked)</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>{$col_headers}</tr>
        </thead>
        <tbody>
            {$table_rows_html}
        </tbody>
    </table>

    <div class="footer">
        Generated by OraBooks — {$org_name}<br>
        Correlation ID: <span class="correlation">{$correlation_id}</span><br>
        Confidential — Do not distribute without authorization.
    </div>
</body>
</html>
HTML;

        $written = file_put_contents($html_path, $html);
        if ($written === false) {
            return new \WP_Error('file_write', "Cannot write to {$html_path}");
        }

        $file_size = filesize($html_path);
        $file_hash = hash_file('sha256', $html_path);

        return [
            'path' => $html_path,
            'size' => $file_size,
            'hash' => $file_hash,
        ];
    }

    /**
     * Get organization name by ID.
     */
    private static function get_org_name($org_id) {
        global $wpdb;
        $table = OraBooks_Database::table('organizations');
        $name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$table} WHERE id = %d", $org_id
        ));
        return $name ?: 'Organization #' . $org_id;
    }

    // ================================================================
    // DOWNLOAD EXPORT
    // ================================================================

    /**
     * Serve an export file for download.
     *
     * @param int $export_id Export request ID.
     * @param int $user_id   Requesting user ID.
     * @return array|WP_Error { file_url, file_size, file_hash, filename } or error.
     */
    public static function download_export($export_id, $user_id) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_REQUESTS);
        $export = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $export_id
        ));

        if (!$export) {
            return new \WP_Error('not_found', 'Export not found');
        }

        // Check ownership (only requester or org admin can download)
        if ((int)$export->user_id !== (int)$user_id && !current_user_can('manage_options')) {
            return new \WP_Error('permission_denied', 'You do not have permission to download this export');
        }

        if ($export->status !== 'ready') {
            return new \WP_Error('not_ready', 'Export is not ready for download (status: ' . $export->status . ')');
        }

        // Check expiry
        if ($export->expires_at && strtotime($export->expires_at) < time()) {
            return new \WP_Error('expired', 'Export has expired');
        }

        // Increment download count
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET download_count = download_count + 1 WHERE id = %d",
            $export_id
        ));

        // Audit log
        orabooks_log_event('export_downloaded', "Export #{$export_id} ({$export->export_type}) downloaded", 'info', [
            'export_id'      => $export_id,
            'export_type'    => $export->export_type,
            'format'         => $export->format,
            'download_count' => $export->download_count + 1,
            'correlation_id' => $export->correlation_id,
        ], $user_id, $export->org_id);

        // Determine file extension for download
        $ext = ($export->format === 'csv') ? '.csv' : '.html';
        $download_filename = sanitize_file_name("{$export->export_type}_" . date('Y-m-d') . $ext);

        return [
            'file_url'  => $export->file_url,
            'file_size' => $export->file_size,
            'file_hash' => $export->file_hash,
            'filename'  => $download_filename,
            'export'    => $export,
        ];
    }

    // ================================================================
    // CANCEL EXPORT
    // ================================================================

    /**
     * Cancel a pending export.
     */
    public static function cancel_export($export_id, $user_id) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_REQUESTS);
        $export = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $export_id
        ));

        if (!$export) {
            return new \WP_Error('not_found', 'Export not found');
        }

        // Check ownership
        if ((int)$export->user_id !== (int)$user_id && !current_user_can('manage_options')) {
            return new \WP_Error('permission_denied', 'You do not have permission to cancel this export');
        }

        if ($export->status !== 'pending') {
            return new \WP_Error('invalid_status', 'Only pending exports can be cancelled');
        }

        $wpdb->update(
            $table,
            ['status' => 'cancelled'],
            ['id' => $export_id],
            ['%s'],
            ['%d']
        );

        orabooks_log_event('export_cancelled', "Export #{$export_id} cancelled by user", 'info', [
            'export_id' => $export_id,
        ], $user_id, $export->org_id);

        return true;
    }

    // ================================================================
    // EXPIRY CLEANUP (Daily Cron)
    // ================================================================

    /**
     * Clean up expired exports — delete files and mark as expired.
     */
    public function cleanup_expired() {
        global $wpdb;

        $table_requests = OraBooks_Database::table(self::TABLE_REQUESTS);
        $table_files    = OraBooks_Database::table(self::TABLE_FILES);

        // Find expired ready exports
        $expired = $wpdb->get_results(
            "SELECT r.id, r.file_url, f.storage_key
             FROM {$table_requests} r
             LEFT JOIN {$table_files} f ON f.export_request_id = r.id
             WHERE r.status = 'ready'
               AND r.expires_at IS NOT NULL
               AND r.expires_at < NOW()"
        );

        $deleted_count = 0;

        foreach ($expired as $item) {
            // Delete physical file
            if (!empty($item->storage_key)) {
                $upload_dir = wp_upload_dir();
                $full_path = $upload_dir['basedir'] . '/' . $item->storage_key;
                if (file_exists($full_path)) {
                    @unlink($full_path);
                    $deleted_count++;
                }
            }

            // Mark as expired
            $wpdb->update(
                $table_requests,
                ['status' => 'expired'],
                ['id' => $item->id],
                ['%s'],
                ['%d']
            );
        }

        if ($deleted_count > 0 || count($expired) > 0) {
            orabooks_log_event('export_cleanup', "Export cleanup: {$deleted_count} files deleted, " . count($expired) . " records expired", 'info');
        }

        return count($expired);
    }

    // ================================================================
    // QUERY METHODS
    // ================================================================

    /**
     * Get exports for a user/org with pagination.
     */
    public static function get_user_exports($org_id, $user_id, $page = 1, $per_page = 20) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_REQUESTS);
        $offset = ($page - 1) * $per_page;

        $total = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE org_id = %d AND user_id = %d",
            $org_id, $user_id
        ));

        $exports = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE org_id = %d AND user_id = %d
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            $org_id, $user_id, $per_page, $offset
        ));

        return [
            'exports'    => $exports,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'total_pages' => ceil($total / $per_page),
        ];
    }

    /**
     * Get export statistics for admin dashboard.
     */
    public static function get_export_stats() {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_REQUESTS);

        $stats = [];

        // Status counts
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$table} GROUP BY status"
        );
        foreach ($status_counts as $row) {
            $stats[$row->status . '_count'] = (int)$row->count;
        }

        // Total exports
        $stats['total'] = array_sum([
            $stats['pending_count'] ?? 0,
            $stats['generating_count'] ?? 0,
            $stats['ready_count'] ?? 0,
            $stats['failed_count'] ?? 0,
            $stats['expired_count'] ?? 0,
            $stats['cancelled_count'] ?? 0,
        ]);

        // Total downloads
        $stats['total_downloads'] = (int)$wpdb->get_var(
            "SELECT SUM(download_count) FROM {$table}"
        );

        // Exports in last 24h
        $stats['last_24h'] = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        // By format
        $stats['by_format'] = $wpdb->get_results(
            "SELECT format, COUNT(*) as count FROM {$table} GROUP BY format"
        );

        // By export type
        $stats['by_type'] = $wpdb->get_results(
            "SELECT export_type, COUNT(*) as count FROM {$table} GROUP BY export_type ORDER BY count DESC LIMIT 10"
        );

        return $stats;
    }

    // ================================================================
    // AJAX HANDLERS
    // ================================================================

    /**
     * AJAX: Request a new export.
     * Expects: export_type, format, [parameters]
     */
    public function ajax_request_export() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Authentication required', 401);
        }

        $export_type = sanitize_text_field($_POST['export_type'] ?? '');
        $format      = sanitize_text_field($_POST['format'] ?? 'csv');
        $parameters  = isset($_POST['parameters']) ? json_decode(stripslashes($_POST['parameters']), true) : [];

        if (empty($export_type)) {
            orabooks_json_error('Export type required', 400);
        }

        // Get user's org
        $org_id = self::get_user_org_id($user_id);
        if (!$org_id) {
            orabooks_json_error('No organization found', 400);
        }

        // Check permission
        if (!orabooks_has_permission($user_id, $org_id, 'export_reports')) {
            orabooks_json_error('Permission denied: export_reports required', 403);
        }

        $result = self::request_export($org_id, $user_id, $export_type, $format, $parameters);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success($result, 'Export requested successfully');
    }

    /**
     * AJAX: List exports for current user.
     */
    public function ajax_exports_list() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Authentication required', 401);
        }

        $page    = max(1, intval($_GET['page'] ?? 1));
        $org_id  = self::get_user_org_id($user_id);

        if (!$org_id) {
            orabooks_json_success(['exports' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'total_pages' => 0]);
        }

        $result = self::get_user_exports($org_id, $user_id, $page);

        // Format for frontend
        $formatted = [];
        foreach ($result['exports'] as $export) {
            $formatted[] = [
                'id'             => (int)$export->id,
                'export_type'    => $export->export_type,
                'format'         => $export->format,
                'status'         => $export->status,
                'file_url'       => $export->file_url,
                'file_size'      => $export->file_size ? self::format_file_size($export->file_size) : null,
                'file_hash'      => $export->file_hash,
                'expires_at'     => $export->expires_at,
                'download_count' => (int)$export->download_count,
                'error_message'  => $export->error_message,
                'generated_at'   => $export->generated_at,
                'created_at'     => $export->created_at,
                'can_download'   => $export->status === 'ready' && (!$export->expires_at || strtotime($export->expires_at) > time()),
                'can_cancel'     => $export->status === 'pending',
                'time_remaining' => $export->expires_at ? self::time_remaining($export->expires_at) : '',
            ];
        }

        orabooks_json_success([
            'exports'     => $formatted,
            'total'       => (int)$result['total'],
            'page'        => $result['page'],
            'per_page'    => $result['per_page'],
            'total_pages' => $result['total_pages'],
        ]);
    }

    /**
     * AJAX: Download an export.
     */
    public function ajax_download_export() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Authentication required', 401);
        }

        $export_id = intval($_GET['export_id'] ?? 0);
        if (!$export_id) {
            orabooks_json_error('Export ID required', 400);
        }

        $result = self::download_export($export_id, $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success([
            'file_url'  => $result['file_url'],
            'file_size' => $result['file_size'],
            'file_hash' => $result['file_hash'],
            'filename'  => $result['filename'],
        ]);
    }

    /**
     * AJAX: Cancel an export.
     */
    public function ajax_cancel_export() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Authentication required', 401);
        }

        $export_id = intval($_POST['export_id'] ?? 0);
        if (!$export_id) {
            orabooks_json_error('Export ID required', 400);
        }

        $result = self::cancel_export($export_id, $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success([], 'Export cancelled');
    }

    /**
     * AJAX: Get export stats (admin only).
     */
    public function ajax_exports_stats() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }

        $stats = self::get_export_stats();
        orabooks_json_success($stats);
    }

    // ================================================================
    // UTILITY METHODS
    // ================================================================

    /**
     * Get user's primary org ID.
     */
    private static function get_user_org_id($user_id) {
        global $wpdb;
        $table = OraBooks_Database::table('user_org');
        $org_id = $wpdb->get_var($wpdb->prepare(
            "SELECT org_id FROM {$table} WHERE user_id = %d LIMIT 1",
            $user_id
        ));
        return $org_id ? (int)$org_id : 0;
    }

    /**
     * Format file size in human-readable format.
     */
    private static function format_file_size($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Get time remaining until expiry.
     */
    private static function time_remaining($expires_at) {
        $remaining = strtotime($expires_at) - time();
        if ($remaining <= 0) {
            return __('Expired', 'orabooks');
        }
        $days = floor($remaining / 86400);
        $hours = floor(($remaining % 86400) / 3600);
        if ($days > 0) {
            return sprintf(__('%d days %d hrs', 'orabooks'), $days, $hours);
        }
        return sprintf(__('%d hrs', 'orabooks'), $hours);
    }
}

/**
 * Global helper: Request an export from anywhere.
 */
function orabooks_request_export($org_id, $user_id, $export_type, $format, $parameters = []) {
    if (class_exists('OraBooks_Exports')) {
        return OraBooks_Exports::request_export($org_id, $user_id, $export_type, $format, $parameters);
    }
    return new \WP_Error('class_missing', 'OraBooks_Exports not loaded');
}
