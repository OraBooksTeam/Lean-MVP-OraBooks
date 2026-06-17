<?php
/**
 * OraBooks CSV Imports (SL-113)
 *
 * CSV upload channel for bulk data ingestion with async parsing,
 * header mapping, confidence scoring, and derived resource creation.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Csv_Imports {

    const TABLE_IMPORTS = 'csv_imports';
    const TABLE_ROWS    = 'csv_import_rows';

    const MAX_FILE_SIZE      = 10485760; // 10 MB
    const MAX_ROWS           = 10000;
    const RATE_LIMIT_MAX     = 5;
    const RATE_LIMIT_PERIOD  = 60;
    const CONFIDENCE_THRESHOLD = 70.0;
    const RETENTION_DAYS     = 90;
    const PREVIEW_ROW_LIMIT  = 10;

    /** Supported resource types for MVP import. */
    const RESOURCE_TYPES = [
        'inventory_item',
        'contact',
        'vendor',
        'expense',
        'invoice',
    ];

    private static $instance = null;

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();

            add_action('orabooks_csv_imports_purge', [self::$instance, 'cron_purge_old_imports']);

            add_action('wp_ajax_orabooks_csv_upload', [self::$instance, 'ajax_upload']);
            add_action('wp_ajax_orabooks_csv_import_get', [self::$instance, 'ajax_get_import']);
            add_action('wp_ajax_orabooks_csv_import_confirm', [self::$instance, 'ajax_confirm']);
            add_action('wp_ajax_orabooks_csv_imports_list', [self::$instance, 'ajax_list_imports']);
        }

        return self::$instance;
    }

    // ================================================================
    // DATABASE SCHEMA
    // ================================================================

    public static function get_create_table_sql() {
        global $wpdb;

        $table_imports = OraBooks_Database::table(self::TABLE_IMPORTS);
        $table_rows    = OraBooks_Database::table(self::TABLE_ROWS);
        $table_orgs    = OraBooks_Database::table('organizations');
        $charset       = $wpdb->get_charset_collate();

        return [
            "CREATE TABLE IF NOT EXISTS {$table_imports} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                storage_key VARCHAR(500) NOT NULL COMMENT 'Path under wp_upload_dir/orabooks-imports',
                original_filename VARCHAR(255) NOT NULL,
                file_hash VARCHAR(64) NOT NULL,
                resource_type VARCHAR(50) NOT NULL,
                header_mapping JSON DEFAULT NULL,
                total_rows INT UNSIGNED DEFAULT 0,
                processed_rows INT UNSIGNED DEFAULT 0,
                status ENUM('uploaded','parsing','mapping','pending_confirm','confirmed','failed') DEFAULT 'uploaded',
                idempotency_key VARCHAR(128) DEFAULT NULL,
                confirm_idempotency_key VARCHAR(128) DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                retention_class ENUM('standard','legal_hold') DEFAULT 'standard',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
                UNIQUE KEY uk_idempotency (idempotency_key),
                UNIQUE KEY uk_confirm_idempotency (confirm_idempotency_key),
                INDEX idx_org_status (org_id, status),
                INDEX idx_user_created (user_id, created_at)
            ) {$charset};",
            "CREATE TABLE IF NOT EXISTS {$table_rows} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                import_id BIGINT UNSIGNED NOT NULL,
                row_index INT UNSIGNED NOT NULL,
                raw_data JSON DEFAULT NULL,
                parsed_data JSON DEFAULT NULL,
                confidence_avg DECIMAL(5,2) DEFAULT NULL,
                risk_scores JSON DEFAULT NULL,
                derived_resource_type VARCHAR(50) DEFAULT NULL,
                derived_resource_id BIGINT UNSIGNED DEFAULT NULL,
                status ENUM('pending','processed','failed','escalated') DEFAULT 'pending',
                dead_letter_reason TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (import_id) REFERENCES {$table_imports}(id) ON DELETE CASCADE,
                UNIQUE KEY uk_import_row (import_id, row_index),
                INDEX idx_import_status (import_id, status)
            ) {$charset};",
        ];
    }

    // ================================================================
    // UPLOAD & PARSE
    // ================================================================

    /**
     * Create an import from uploaded CSV content.
     *
     * @param int    $org_id
     * @param int    $user_id
     * @param string $resource_type
     * @param string $filename
     * @param string $content Raw CSV bytes
     * @param string $idempotency_key
     * @return array|WP_Error
     */
    public static function upload_import($org_id, $user_id, $resource_type, $filename, $content, $idempotency_key = '') {
        global $wpdb;

        $org_id = intval($org_id);
        $user_id = intval($user_id);
        $resource_type = sanitize_text_field($resource_type);

        if ($org_id <= 0 || $user_id <= 0) {
            return new WP_Error('missing_context', 'Organization and user are required');
        }

        if (!in_array($resource_type, self::RESOURCE_TYPES, true)) {
            return new WP_Error('invalid_resource_type', 'Unsupported resource type');
        }

        $perm = OraBooks_RBAC::require_permission($user_id, $org_id, 'submit_transaction');
        if (is_wp_error($perm)) {
            return $perm;
        }

        $rate_key = "csv_upload_{$user_id}";
        if (!orabooks_check_rate_limit($rate_key, self::RATE_LIMIT_MAX, self::RATE_LIMIT_PERIOD)) {
            return new WP_Error('rate_limit', sprintf(
                'Rate limit exceeded. Max %d uploads per minute.', self::RATE_LIMIT_MAX
            ));
        }

        if ($content === '' || strlen($content) > self::MAX_FILE_SIZE) {
            return new WP_Error('invalid_file', 'CSV file is empty or exceeds 10MB limit');
        }

        if ($idempotency_key === '') {
            $idempotency_key = orabooks_uuid();
        }
        $idempotency_key = sanitize_text_field($idempotency_key);

        $table = OraBooks_Database::table(self::TABLE_IMPORTS);
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE idempotency_key = %s",
            $idempotency_key
        ));
        if ($existing) {
            return new WP_Error('duplicate', 'Import with this idempotency key already exists', ['import_id' => (int) $existing]);
        }

        $file_hash = hash('sha256', $content);
        $storage = self::store_file($org_id, $filename, $content);
        if (is_wp_error($storage)) {
            return $storage;
        }

        $wpdb->insert($table, [
            'org_id'            => $org_id,
            'user_id'           => $user_id,
            'storage_key'       => $storage['storage_key'],
            'original_filename' => sanitize_file_name($filename),
            'file_hash'         => $file_hash,
            'resource_type'     => $resource_type,
            'status'            => 'uploaded',
            'idempotency_key'   => $idempotency_key,
        ], ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']);

        $import_id = (int) $wpdb->insert_id;
        if (!$import_id) {
            return new WP_Error('db_error', 'Failed to create import record');
        }

        orabooks_log_event('csv_import_uploaded', "CSV import #{$import_id} uploaded ({$resource_type})", 'info', [
            'import_id'     => $import_id,
            'resource_type' => $resource_type,
            'filename'      => sanitize_file_name($filename),
        ], $user_id, $org_id);

        orabooks_publish_event('csv_parsing_requested', $import_id, [
            'import_id'     => $import_id,
            'org_id'        => $org_id,
            'user_id'       => $user_id,
            'resource_type' => $resource_type,
        ]);

        orabooks_enqueue_job('parse_csv_import', [
            'import_id' => $import_id,
            'org_id'    => $org_id,
        ], [
            'queue_name'  => 'imports',
            'priority'    => 5,
            'max_retries' => 3,
        ]);

        return [
            'id'              => $import_id,
            'status'          => 'uploaded',
            'idempotency_key' => $idempotency_key,
            'resource_type'   => $resource_type,
        ];
    }

    /**
     * Async job handler: parse CSV and populate import rows.
     */
    public static function parse_csv_import_job($job, $payload) {
        global $wpdb;

        $import_id = intval($payload['import_id'] ?? 0);
        $org_id = intval($payload['org_id'] ?? 0);
        if ($import_id <= 0) {
            return 'Missing import_id';
        }

        $import = self::get_import($import_id, $org_id);
        if (!$import) {
            return 'Import not found';
        }

        $table = OraBooks_Database::table(self::TABLE_IMPORTS);
        $wpdb->update($table, ['status' => 'parsing'], ['id' => $import_id], ['%s'], ['%d']);

        $content = self::read_stored_file($import->storage_key);
        if (is_wp_error($content)) {
            self::mark_import_failed($import_id, $content->get_error_message());
            return $content->get_error_message();
        }

        $parsed = self::parse_csv_content($content);
        if (is_wp_error($parsed)) {
            self::mark_import_failed($import_id, $parsed->get_error_message());
            orabooks_publish_event('csv_import_failed', $import_id, [
                'import_id' => $import_id,
                'org_id'    => (int) $import->org_id,
                'user_id'   => (int) $import->user_id,
                'reason'    => $parsed->get_error_message(),
            ]);
            return $parsed->get_error_message();
        }

        if ($parsed['row_count'] > self::MAX_ROWS) {
            $msg = sprintf('Row count %d exceeds limit of %d', $parsed['row_count'], self::MAX_ROWS);
            self::mark_import_failed($import_id, $msg);
            orabooks_publish_event('csv_import_failed', $import_id, [
                'import_id' => $import_id,
                'org_id'    => (int) $import->org_id,
                'user_id'   => (int) $import->user_id,
                'reason'    => $msg,
            ]);
            return $msg;
        }

        $mapping = self::suggest_header_mapping($parsed['headers'], $import->resource_type);
        $rows_table = OraBooks_Database::table(self::TABLE_ROWS);

        foreach ($parsed['rows'] as $index => $row) {
            $raw = [];
            foreach ($parsed['headers'] as $col_index => $header) {
                $raw['col' . $col_index] = $row[$col_index] ?? '';
            }

            $parsed_data = self::apply_mapping($raw, $mapping, $import->resource_type);
            $parsed_data = self::normalize_parsed_row($parsed_data, $import->resource_type);
            $confidence = self::compute_row_confidence($parsed_data, $import->resource_type);

            $wpdb->insert($rows_table, [
                'import_id'       => $import_id,
                'row_index'       => $index,
                'raw_data'        => wp_json_encode($raw),
                'parsed_data'     => wp_json_encode($parsed_data),
                'confidence_avg'  => $confidence['avg'],
                'risk_scores'     => wp_json_encode($confidence['risks']),
                'status'          => 'pending',
            ], ['%d', '%d', '%s', '%s', '%f', '%s', '%s']);
        }

        $wpdb->update($table, [
            'status'          => 'pending_confirm',
            'header_mapping'  => wp_json_encode($mapping),
            'total_rows'      => $parsed['row_count'],
        ], ['id' => $import_id], ['%s', '%s', '%d'], ['%d']);

        orabooks_log_event('csv_import_parsed', "CSV import #{$import_id} parsed ({$parsed['row_count']} rows)", 'info', [
            'import_id'  => $import_id,
            'total_rows' => $parsed['row_count'],
        ], (int) $import->user_id, (int) $import->org_id);

        return true;
    }

    /**
     * Parse CSV string into headers and rows.
     *
     * @return array|WP_Error { headers, rows, row_count }
     */
    public static function parse_csv_content($content) {
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return new WP_Error('empty_csv', 'CSV file contains no data');
        }

        $headers = str_getcsv(array_shift($lines));
        $headers = array_map(function ($h) {
            return trim(sanitize_text_field($h));
        }, $headers);

        if (empty(array_filter($headers))) {
            return new WP_Error('missing_headers', 'CSV must include a header row');
        }

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = str_getcsv($line);
            if (count($row) === 1 && $row[0] === null) {
                continue;
            }
            $rows[] = $row;
        }

        return [
            'headers'   => $headers,
            'rows'      => $rows,
            'row_count' => count($rows),
        ];
    }

    /**
     * Suggest column-to-field mapping from header labels.
     */
    public static function suggest_header_mapping(array $headers, $resource_type) {
        $aliases = self::get_field_aliases($resource_type);
        $mapping = [];

        foreach ($headers as $index => $header) {
            $normalized = strtolower(preg_replace('/[^a-z0-9]+/', '_', trim($header)));
            $segments = array_filter(explode('_', $normalized));
            $field = null;

            foreach ($aliases as $field_name => $patterns) {
                foreach ($patterns as $pattern) {
                    if ($normalized === $pattern || in_array($pattern, $segments, true)) {
                        $field = $field_name;
                        break 2;
                    }
                }
            }

            if ($field) {
                $mapping[(string) $index] = $field;
            }
        }

        return $mapping;
    }

    /**
     * Apply header mapping to raw row data.
     */
    public static function apply_mapping(array $raw, array $mapping, $resource_type) {
        $parsed = ['resource_type' => $resource_type];

        foreach ($mapping as $col_index => $field) {
            $key = 'col' . $col_index;
            if (!isset($raw[$key])) {
                continue;
            }
            $value = trim((string) $raw[$key]);
            if ($value === '') {
                continue;
            }

            switch ($field) {
                case 'email':
                    $parsed[$field] = sanitize_email($value);
                    break;
                case 'total_amount':
                case 'tax_amount':
                case 'initial_stock':
                case 'initial_cost':
                case 'low_stock_threshold':
                case 'payment_terms':
                    $parsed[$field] = is_numeric(str_replace([',', '$'], '', $value))
                        ? floatval(str_replace([',', '$'], '', $value))
                        : $value;
                    break;
                default:
                    $parsed[$field] = sanitize_text_field($value);
            }
        }

        return $parsed;
    }

    /**
     * Rule-based confidence scoring for a parsed row.
     *
     * @return array { avg: float, risks: array }
     */
    public static function compute_row_confidence(array $parsed, $resource_type) {
        $required = self::get_required_fields($resource_type);
        $scores = [];
        $risks = [];

        foreach ($required as $field) {
            if (!empty($parsed[$field])) {
                $scores[] = 100;
            } else {
                $scores[] = 0;
                $risks[] = "missing_{$field}";
            }
        }

        if (!empty($parsed['email']) && !is_email($parsed['email'])) {
            $scores[] = 20;
            $risks[] = 'invalid_email';
        }

        foreach (['total_amount', 'tax_amount', 'initial_cost', 'initial_stock'] as $numeric_field) {
            if (isset($parsed[$numeric_field]) && !is_numeric($parsed[$numeric_field])) {
                $scores[] = 25;
                $risks[] = "invalid_{$numeric_field}";
            }
        }

        if (!empty($parsed['bill_date']) || !empty($parsed['invoice_date'])) {
            $date_val = $parsed['bill_date'] ?? $parsed['invoice_date'];
            $ts = strtotime((string) $date_val);
            $scores[] = ($ts !== false) ? 90 : 30;
            if ($ts === false) {
                $risks[] = 'invalid_date';
            }
        }

        if (empty($scores)) {
            return ['avg' => 0.0, 'risks' => ['no_data']];
        }

        return [
            'avg'   => round(array_sum($scores) / count($scores), 2),
            'risks' => $risks,
        ];
    }

    // ================================================================
    // CONFIRM & PROCESS ROWS
    // ================================================================

    /**
     * Confirm import and create derived resources.
     */
    public static function confirm_import($import_id, $org_id, $user_id, $confirm_key, $overrides = []) {
        global $wpdb;

        $import_id = intval($import_id);
        $org_id = intval($org_id);
        $user_id = intval($user_id);
        $confirm_key = sanitize_text_field($confirm_key);

        if ($import_id <= 0 || $confirm_key === '') {
            return new WP_Error('missing_field', 'Import ID and idempotency key are required');
        }

        $perm = OraBooks_RBAC::require_permission($user_id, $org_id, 'submit_transaction');
        if (is_wp_error($perm)) {
            return $perm;
        }

        $import = self::get_import($import_id, $org_id);
        if (!$import) {
            return new WP_Error('not_found', 'Import not found');
        }

        if ($import->status === 'confirmed') {
            if ($import->confirm_idempotency_key === $confirm_key) {
                return self::get_import_summary($import_id, $org_id);
            }
            return new WP_Error('already_confirmed', 'Import already confirmed with a different key');
        }

        if ($import->status !== 'pending_confirm') {
            return new WP_Error('invalid_status', 'Import is not ready for confirmation');
        }

        $table = OraBooks_Database::table(self::TABLE_IMPORTS);
        $dup = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE confirm_idempotency_key = %s AND id != %d",
            $confirm_key,
            $import_id
        ));
        if ($dup) {
            return new WP_Error('duplicate_confirm', 'Confirm idempotency key already used', ['status' => 409]);
        }

        if (!empty($overrides['header_mapping']) && is_array($overrides['header_mapping'])) {
            $wpdb->update($table, [
                'header_mapping' => wp_json_encode($overrides['header_mapping']),
            ], ['id' => $import_id], ['%s'], ['%d']);
            $import->header_mapping = wp_json_encode($overrides['header_mapping']);
        }

        $wpdb->update($table, [
            'status'                   => 'confirmed',
            'confirm_idempotency_key'  => $confirm_key,
        ], ['id' => $import_id], ['%s', '%s'], ['%d']);

        $rows_table = OraBooks_Database::table(self::TABLE_ROWS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$rows_table} WHERE import_id = %d ORDER BY row_index ASC",
            $import_id
        ));

        $processed = 0;
        $escalated = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $parsed = json_decode($row->parsed_data, true) ?: [];
            if (!empty($overrides['rows'][$row->row_index]) && is_array($overrides['rows'][$row->row_index])) {
                $parsed = array_merge($parsed, $overrides['rows'][$row->row_index]);
            }
            $parsed = self::normalize_parsed_row($parsed, $import->resource_type);

            $confidence = self::compute_row_confidence($parsed, $import->resource_type);
            $wpdb->update($rows_table, [
                'parsed_data'    => wp_json_encode($parsed),
                'confidence_avg' => $confidence['avg'],
                'risk_scores'    => wp_json_encode($confidence['risks']),
            ], ['id' => $row->id], ['%s', '%f', '%s'], ['%d']);

            if ($confidence['avg'] < self::CONFIDENCE_THRESHOLD) {
                $wpdb->update($rows_table, ['status' => 'escalated'], ['id' => $row->id], ['%s'], ['%d']);
                orabooks_publish_event('csv_row_escalated', $import_id, [
                    'import_id'  => $import_id,
                    'row_id'     => (int) $row->id,
                    'row_index'  => (int) $row->row_index,
                    'org_id'     => $org_id,
                    'confidence' => $confidence['avg'],
                    'risks'      => $confidence['risks'],
                ]);
                $escalated++;
                continue;
            }

            $result = self::create_derived_resource($org_id, $import->resource_type, $parsed, $import_id, (int) $row->row_index);
            if (is_wp_error($result)) {
                $wpdb->update($rows_table, [
                    'status'             => 'failed',
                    'dead_letter_reason' => $result->get_error_message(),
                ], ['id' => $row->id], ['%s', '%s'], ['%d']);
                $failed++;
                continue;
            }

            $wpdb->update($rows_table, [
                'status'                => 'processed',
                'derived_resource_type' => $result['resource_type'],
                'derived_resource_id'   => $result['resource_id'],
            ], ['id' => $row->id], ['%s', '%s', '%d'], ['%d']);
            $processed++;
        }

        $wpdb->update($table, ['processed_rows' => $processed], ['id' => $import_id], ['%d'], ['%d']);

        orabooks_log_event('csv_import_confirmed', "CSV import #{$import_id} confirmed", 'info', [
            'import_id'  => $import_id,
            'processed'  => $processed,
            'escalated'  => $escalated,
            'failed'     => $failed,
        ], $user_id, $org_id);

        orabooks_publish_event('csv_import_completed', $import_id, [
            'import_id'     => $import_id,
            'org_id'        => $org_id,
            'user_id'       => $user_id,
            'resource_type' => $import->resource_type,
            'processed'     => $processed,
            'escalated'     => $escalated,
            'failed'        => $failed,
            'total_rows'    => (int) $import->total_rows,
        ]);

        return [
            'import_id'  => $import_id,
            'status'     => 'confirmed',
            'processed'  => $processed,
            'escalated'  => $escalated,
            'failed'     => $failed,
            'total_rows' => (int) $import->total_rows,
        ];
    }

    /**
     * Create a derived resource from parsed row data.
     *
     * @return array|WP_Error { resource_type, resource_id }
     */
    public static function create_derived_resource($org_id, $resource_type, array $parsed, $import_id, $row_index) {
        $idempotency = 'csv_' . $import_id . '_' . $row_index;

        switch ($resource_type) {
            case 'inventory_item':
                if (!class_exists('OraBooks_Inventory')) {
                    return new WP_Error('missing_module', 'Inventory module unavailable');
                }
                $result = OraBooks_Inventory::create_product($org_id, [
                    'sku'                 => $parsed['sku'] ?? ('SKU-' . $row_index),
                    'name'                => $parsed['name'] ?? 'Imported Product',
                    'unit'                => $parsed['unit'] ?? 'piece',
                    'initial_stock'       => $parsed['initial_stock'] ?? 0,
                    'initial_cost'        => $parsed['initial_cost'] ?? 0,
                    'low_stock_threshold' => $parsed['low_stock_threshold'] ?? null,
                ]);
                if (is_wp_error($result)) {
                    return $result;
                }
                return [
                    'resource_type' => 'inventory_item',
                    'resource_id'   => (int) ($result->id ?? 0),
                ];

            case 'contact':
            case 'vendor':
                if (!class_exists('OraBooks_Vendors')) {
                    return new WP_Error('missing_module', 'Vendors module unavailable');
                }
                $result = OraBooks_Vendors::create_vendor($org_id, [
                    'name'  => $parsed['name'] ?? $parsed['vendor_name'] ?? 'Imported Contact',
                    'email' => $parsed['email'] ?? null,
                    'tax_id' => $parsed['tax_id'] ?? null,
                    'payment_terms' => $parsed['payment_terms'] ?? 30,
                ]);
                if (is_wp_error($result)) {
                    return $result;
                }
                return [
                    'resource_type' => 'contact',
                    'resource_id'   => (int) ($result->id ?? 0),
                ];

            case 'expense':
                return self::create_expense_from_row($org_id, $parsed, $idempotency);

            case 'invoice':
                return self::create_invoice_from_row($org_id, $parsed, $idempotency);

            default:
                return new WP_Error('unsupported', 'Unsupported resource type');
        }
    }

    private static function create_expense_from_row($org_id, array $parsed, $idempotency) {
        global $wpdb;

        if (!class_exists('OraBooks_Vendors')) {
            return new WP_Error('missing_module', 'Vendors module unavailable');
        }

        $vendor_name = $parsed['vendor_name'] ?? $parsed['name'] ?? '';
        if ($vendor_name === '') {
            return new WP_Error('missing_vendor', 'Vendor name is required for expense import');
        }

        $vendors_table = OraBooks_Database::table('vendors');
        $vendor_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$vendors_table} WHERE org_id = %d AND name = %s LIMIT 1",
            $org_id,
            $vendor_name
        ));

        if (!$vendor_id) {
            $vendor = OraBooks_Vendors::create_vendor($org_id, ['name' => $vendor_name]);
            if (is_wp_error($vendor)) {
                return $vendor;
            }
            $vendor_id = (int) $vendor->id;
        }

        $bill = OraBooks_Vendors::create_bill($org_id, [
            'vendor_id'    => $vendor_id,
            'total_amount' => $parsed['total_amount'] ?? $parsed['amount'] ?? 0,
            'tax_amount'   => $parsed['tax_amount'] ?? 0,
            'bill_date'    => $parsed['bill_date'] ?? current_time('Y-m-d'),
            'description'  => $parsed['description'] ?? 'CSV import',
            'idempotency_key' => $idempotency,
        ]);

        if (is_wp_error($bill)) {
            return $bill;
        }

        $bills_table = OraBooks_Database::table('bills');
        $wpdb->update(
            $bills_table,
            ['workflow_status' => 'submitted'],
            ['id' => (int) $bill->id, 'org_id' => $org_id],
            ['%s'],
            ['%d', '%d']
        );

        return [
            'resource_type' => 'expense',
            'resource_id'   => (int) $bill->id,
        ];
    }

    private static function create_invoice_from_row($org_id, array $parsed, $idempotency) {
        if (!class_exists('OraBooks_Customers')) {
            return new WP_Error('missing_module', 'Customers module unavailable');
        }

        if (empty($parsed['customer_id'])) {
            return new WP_Error('missing_customer', 'Customer ID is required for invoice import');
        }

        $invoice = OraBooks_Customers::create_invoice($org_id, [
            'customer_id'     => (int) $parsed['customer_id'],
            'total_amount'    => $parsed['total_amount'] ?? $parsed['amount'] ?? 0,
            'tax_amount'      => $parsed['tax_amount'] ?? 0,
            'invoice_date'    => $parsed['invoice_date'] ?? current_time('Y-m-d'),
            'description'     => $parsed['description'] ?? 'CSV import',
            'workflow_status' => 'submitted',
            'idempotency_key' => $idempotency,
        ]);

        if (is_wp_error($invoice)) {
            return $invoice;
        }

        return [
            'resource_type' => 'invoice',
            'resource_id'   => (int) ($invoice->id ?? 0),
        ];
    }

    // ================================================================
    // QUERIES & HELPERS
    // ================================================================

    public static function get_import($import_id, $org_id = 0) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_IMPORTS);
        if ($org_id > 0) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND org_id = %d",
                intval($import_id),
                intval($org_id)
            ));
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            intval($import_id)
        ));
    }

    public static function get_import_preview($import_id, $org_id) {
        global $wpdb;

        $import = self::get_import($import_id, $org_id);
        if (!$import) {
            return null;
        }

        $rows_table = OraBooks_Database::table(self::TABLE_ROWS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, row_index, raw_data, parsed_data, confidence_avg, risk_scores, status
             FROM {$rows_table}
             WHERE import_id = %d
             ORDER BY row_index ASC
             LIMIT %d",
            $import_id,
            self::PREVIEW_ROW_LIMIT
        ));

        return [
            'import' => self::format_import($import),
            'rows'   => array_map([self::class, 'format_row'], $rows ?: []),
        ];
    }

    public static function get_import_summary($import_id, $org_id) {
        global $wpdb;

        $import = self::get_import($import_id, $org_id);
        if (!$import) {
            return new WP_Error('not_found', 'Import not found');
        }

        $rows_table = OraBooks_Database::table(self::TABLE_ROWS);
        $counts = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) AS cnt FROM {$rows_table} WHERE import_id = %d GROUP BY status",
            $import_id
        ), OBJECT_K);

        return [
            'import' => self::format_import($import),
            'row_counts' => [
                'processed' => (int) ($counts['processed']->cnt ?? 0),
                'escalated' => (int) ($counts['escalated']->cnt ?? 0),
                'failed'    => (int) ($counts['failed']->cnt ?? 0),
                'pending'   => (int) ($counts['pending']->cnt ?? 0),
            ],
        ];
    }

    public static function list_imports($org_id, $user_id = 0, $limit = 20) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_IMPORTS);
        $limit = max(1, min(100, intval($limit)));

        if ($user_id > 0) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE org_id = %d AND user_id = %d ORDER BY created_at DESC LIMIT %d",
                intval($org_id),
                intval($user_id),
                $limit
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d ORDER BY created_at DESC LIMIT %d",
            intval($org_id),
            $limit
        ));
    }

    public static function format_import($import) {
        return [
            'id'                => (int) $import->id,
            'org_id'            => (int) $import->org_id,
            'user_id'           => (int) $import->user_id,
            'resource_type'     => $import->resource_type,
            'original_filename' => $import->original_filename,
            'status'            => $import->status,
            'total_rows'        => (int) $import->total_rows,
            'processed_rows'    => (int) $import->processed_rows,
            'header_mapping'    => json_decode($import->header_mapping ?: '{}', true),
            'created_at'        => $import->created_at,
        ];
    }

    private static function format_row($row) {
        return [
            'id'             => (int) $row->id,
            'row_index'      => (int) $row->row_index,
            'raw_data'       => json_decode($row->raw_data ?: '{}', true),
            'parsed_data'    => json_decode($row->parsed_data ?: '{}', true),
            'confidence_avg' => $row->confidence_avg !== null ? (float) $row->confidence_avg : null,
            'risk_scores'    => json_decode($row->risk_scores ?: '[]', true),
            'status'         => $row->status,
        ];
    }

    /**
     * Normalize parsed row field aliases before confidence / resource creation.
     */
    public static function normalize_parsed_row(array $parsed, $resource_type) {
        if (empty($parsed['total_amount']) && !empty($parsed['amount'])) {
            $parsed['total_amount'] = $parsed['amount'];
        }
        if (empty($parsed['vendor_name']) && !empty($parsed['name']) && in_array($resource_type, ['expense'], true)) {
            $parsed['vendor_name'] = $parsed['name'];
        }
        return $parsed;
    }

    private static function get_required_fields($resource_type) {
        $map = [
            'inventory_item' => ['sku', 'name'],
            'contact'        => ['name'],
            'vendor'         => ['name'],
            'expense'        => ['vendor_name', 'total_amount'],
            'invoice'        => ['customer_id', 'total_amount'],
        ];

        return $map[$resource_type] ?? ['name'];
    }

    private static function get_field_aliases($resource_type) {
        $common = [
            'name'          => ['name', 'contact', 'contact_name', 'full_name'],
            'email'         => ['email', 'email_address', 'e_mail'],
            'vendor_name'   => ['vendor', 'vendor_name', 'supplier', 'payee'],
            'total_amount'  => ['amount', 'total', 'total_amount', 'value', 'price'],
            'tax_amount'    => ['tax', 'tax_amount', 'vat', 'gst'],
            'description'   => ['description', 'memo', 'notes', 'details'],
            'bill_date'     => ['date', 'bill_date', 'expense_date', 'transaction_date'],
            'invoice_date'  => ['date', 'invoice_date', 'issue_date'],
            'customer_id'   => ['customer_id', 'customer', 'client_id'],
            'sku'           => ['sku', 'product_sku', 'item_code', 'code'],
            'unit'          => ['unit', 'uom'],
            'initial_stock' => ['stock', 'quantity', 'qty', 'initial_stock'],
            'initial_cost'  => ['cost', 'unit_cost', 'initial_cost'],
            'tax_id'        => ['tax_id', 'vat_number', 'gstin'],
            'payment_terms' => ['payment_terms', 'terms', 'due_days'],
        ];

        $fields = self::get_required_fields($resource_type);
        $aliases = [];
        foreach ($fields as $field) {
            if (isset($common[$field])) {
                $aliases[$field] = $common[$field];
            }
        }

        foreach ($common as $field => $patterns) {
            if (!isset($aliases[$field])) {
                $aliases[$field] = $patterns;
            }
        }

        return $aliases;
    }

    private static function store_file($org_id, $filename, $content) {
        $upload_dir = wp_upload_dir();
        $base = $upload_dir['basedir'] . '/orabooks-imports/' . intval($org_id);

        if (!wp_mkdir_p($base)) {
            return new WP_Error('storage_error', 'Could not create import storage directory');
        }

        $safe_name = sanitize_file_name($filename);
        $storage_key = 'orabooks-imports/' . intval($org_id) . '/' . wp_hash($content . microtime(true)) . '_' . $safe_name;
        $full_path = $upload_dir['basedir'] . '/' . $storage_key;

        if (file_put_contents($full_path, $content) === false) {
            return new WP_Error('storage_error', 'Could not save CSV file');
        }

        return ['storage_key' => $storage_key, 'full_path' => $full_path];
    }

    public static function read_stored_file($storage_key) {
        $upload_dir = wp_upload_dir();
        $path = $upload_dir['basedir'] . '/' . ltrim($storage_key, '/');

        if (!file_exists($path)) {
            return new WP_Error('file_missing', 'Import file not found');
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return new WP_Error('file_read_error', 'Could not read import file');
        }

        return $content;
    }

    private static function mark_import_failed($import_id, $message) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_IMPORTS);
        $wpdb->update($table, [
            'status'        => 'failed',
            'error_message' => sanitize_text_field($message),
        ], ['id' => intval($import_id)], ['%s', '%s'], ['%d']);
    }

    /**
     * Purge import files past retention (respects legal_hold).
     */
    public function cron_purge_old_imports() {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_IMPORTS);
        $cutoff = gmdate('Y-m-d H:i:s', time() - (self::RETENTION_DAYS * DAY_IN_SECONDS));

        $imports = $wpdb->get_results($wpdb->prepare(
            "SELECT id, storage_key FROM {$table}
             WHERE retention_class = 'standard'
               AND created_at < %s
               AND storage_key IS NOT NULL
               AND storage_key != ''",
            $cutoff
        ));

        if (empty($imports)) {
            return;
        }

        $upload_dir = wp_upload_dir();
        foreach ($imports as $import) {
            $path = $upload_dir['basedir'] . '/' . ltrim($import->storage_key, '/');
            if (file_exists($path)) {
                @unlink($path);
            }
            $wpdb->update($table, ['storage_key' => ''], ['id' => (int) $import->id], ['%s'], ['%d']);
        }

        orabooks_log_event('csv_imports_purged', count($imports) . ' import files purged', 'info', [
            'count' => count($imports),
        ]);
    }

    // ================================================================
    // AJAX
    // ================================================================

    public function ajax_upload() {
        check_ajax_referer('orabooks_ajax', 'nonce');

        $user_id = get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $resource_type = sanitize_text_field($_POST['resource_type'] ?? '');
        $idempotency_key = sanitize_text_field($_POST['idempotency_key'] ?? '');

        if (!$user_id || !$org_id) {
            orabooks_json_error('Authentication required', 401);
        }

        if (empty($_FILES['csv_file']['tmp_name'])) {
            orabooks_json_error('CSV file is required', 400);
        }

        $content = file_get_contents($_FILES['csv_file']['tmp_name']);
        $filename = sanitize_file_name($_FILES['csv_file']['name'] ?? 'import.csv');

        $result = self::upload_import($org_id, $user_id, $resource_type, $filename, $content, $idempotency_key);
        if (is_wp_error($result)) {
            $code = $result->get_error_code() === 'duplicate' ? 409 : 400;
            orabooks_json_error($result->get_error_message(), $code, $result->get_error_data());
        }

        orabooks_json_success($result);
    }

    public function ajax_get_import() {
        check_ajax_referer('orabooks_ajax', 'nonce');

        $user_id = get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $import_id = intval($_POST['import_id'] ?? 0);

        if (!$user_id || !$org_id || !$import_id) {
            orabooks_json_error('Missing parameters', 400);
        }

        $perm = OraBooks_RBAC::require_permission($user_id, $org_id, 'submit_transaction');
        if (is_wp_error($perm)) {
            orabooks_json_error($perm->get_error_message(), 403);
        }

        $preview = self::get_import_preview($import_id, $org_id);
        if (!$preview) {
            orabooks_json_error('Import not found', 404);
        }

        orabooks_json_success($preview);
    }

    public function ajax_confirm() {
        check_ajax_referer('orabooks_ajax', 'nonce');

        $user_id = get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $import_id = intval($_POST['import_id'] ?? 0);
        $confirm_key = sanitize_text_field($_POST['idempotency_key'] ?? '');

        $overrides = [];
        if (!empty($_POST['header_mapping'])) {
            $overrides['header_mapping'] = json_decode(stripslashes($_POST['header_mapping']), true);
        }
        if (!empty($_POST['row_overrides'])) {
            $overrides['rows'] = json_decode(stripslashes($_POST['row_overrides']), true);
        }

        if (!$user_id || !$org_id || !$import_id || $confirm_key === '') {
            orabooks_json_error('Missing parameters', 400);
        }

        $result = self::confirm_import($import_id, $org_id, $user_id, $confirm_key, $overrides);
        if (is_wp_error($result)) {
            $code = 400;
            if ($result->get_error_code() === 'duplicate_confirm') {
                $code = 409;
            }
            orabooks_json_error($result->get_error_message(), $code, $result->get_error_data());
        }

        orabooks_json_success($result);
    }

    public function ajax_list_imports() {
        check_ajax_referer('orabooks_ajax', 'nonce');

        $user_id = get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);

        if (!$user_id || !$org_id) {
            orabooks_json_error('Authentication required', 401);
        }

        $perm = OraBooks_RBAC::require_permission($user_id, $org_id, 'submit_transaction');
        if (is_wp_error($perm)) {
            orabooks_json_error($perm->get_error_message(), 403);
        }

        $imports = self::list_imports($org_id, $user_id);
        orabooks_json_success([
            'imports' => array_map([self::class, 'format_import'], $imports ?: []),
        ]);
    }
}
