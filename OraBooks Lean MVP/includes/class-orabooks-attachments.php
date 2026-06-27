<?php
/**
 * OraBooks Attachments & Versioning (SL-203)
 *
 * Generic attachment engine with versioning, idempotency, soft delete, and retention.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Attachments {

    const TABLE_ATTACHMENTS = 'attachments';
    const TABLE_VERSIONS    = 'attachment_versions';

    const MAX_FILE_SIZE     = 26214400; // 25 MB
    const RATE_LIMIT_MAX    = 10;
    const RATE_LIMIT_PERIOD = 60;
    const RETENTION_DAYS    = 90;

    const RESOURCE_TYPES = [
        'invoice',
        'bill',
        'expense',
        'voice_input',
        'csv_import',
        'user_profile',
        'journal',
        'customer',
        'vendor',
        'inventory_item',
        'bank_account',
        'bank_transaction',
        'export',
        'general',
    ];

    const ALLOWED_MIME_PREFIXES = [
        'application/pdf',
        'image/',
        'text/csv',
        'text/plain',
        'audio/',
        'video/',
        'application/vnd.',
        'application/msword',
        'application/vnd.openxmlformats-officedocument',
    ];

    private static $instance = null;

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();

            add_action('orabooks_attachments_purge', [self::$instance, 'cron_purge_old_attachments']);

            add_action('wp_ajax_orabooks_attachment_upload', [self::$instance, 'ajax_upload']);
            add_action('wp_ajax_orabooks_attachments_list', [self::$instance, 'ajax_list']);
            add_action('wp_ajax_orabooks_attachment_get', [self::$instance, 'ajax_get']);
            add_action('wp_ajax_orabooks_attachment_delete', [self::$instance, 'ajax_delete']);
            add_action('wp_ajax_orabooks_attachment_download', [self::$instance, 'ajax_download']);
        }

        return self::$instance;
    }

    public static function get_create_table_sql() {
        global $wpdb;

        $table_attachments = OraBooks_Database::table(self::TABLE_ATTACHMENTS);
        $table_versions    = OraBooks_Database::table(self::TABLE_VERSIONS);
        $table_orgs        = OraBooks_Database::table('organizations');
        $charset           = $wpdb->get_charset_collate();

        return [
            "CREATE TABLE IF NOT EXISTS {$table_attachments} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                resource_type VARCHAR(50) NOT NULL,
                resource_id BIGINT UNSIGNED NOT NULL,
                current_version_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL,
                retention_class ENUM('standard','legal_hold') DEFAULT 'standard',
                FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
                INDEX idx_org_resource (org_id, resource_type, resource_id),
                INDEX idx_org_deleted (org_id, deleted_at)
            ) {$charset};",
            "CREATE TABLE IF NOT EXISTS {$table_versions} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                attachment_id BIGINT UNSIGNED NOT NULL,
                version_number INT UNSIGNED NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_size BIGINT UNSIGNED NOT NULL,
                file_hash VARCHAR(64) NOT NULL,
                mime_type VARCHAR(100) DEFAULT NULL,
                storage_path VARCHAR(500) NOT NULL,
                uploaded_by BIGINT UNSIGNED NOT NULL,
                idempotency_key VARCHAR(128) DEFAULT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                virus_scan_status ENUM('pending','clean','infected','error') DEFAULT 'pending',
                FOREIGN KEY (attachment_id) REFERENCES {$table_attachments}(id) ON DELETE CASCADE,
                UNIQUE KEY uk_idempotency (idempotency_key),
                INDEX idx_attachment_version (attachment_id, version_number),
                INDEX idx_file_hash (file_hash)
            ) {$charset};",
        ];
    }

    public static function upload_attachment(
        $org_id,
        $user_id,
        $resource_type,
        $resource_id,
        $filename,
        $content,
        $mime_type = '',
        $attachment_id = 0,
        $idempotency_key = ''
    ) {
        global $wpdb;

        $org_id = intval($org_id);
        $user_id = intval($user_id);
        $resource_id = intval($resource_id);
        $resource_type = sanitize_text_field($resource_type);
        $attachment_id = intval($attachment_id);

        if ($org_id <= 0 || $user_id <= 0 || $resource_id <= 0) {
            return new WP_Error('missing_context', 'Organization, user, and resource are required');
        }

        if (!in_array($resource_type, self::RESOURCE_TYPES, true)) {
            return new WP_Error('invalid_resource_type', 'Unsupported resource type');
        }

        if (!self::can_access_resource($user_id, $org_id, $resource_type, 'write')) {
            return new WP_Error('permission_denied', 'Permission denied');
        }

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'submit_transaction')) {
            return new WP_Error('permission_denied', 'Permission denied');
        }

        if (!orabooks_check_rate_limit("attachment_upload_{$user_id}", self::RATE_LIMIT_MAX, self::RATE_LIMIT_PERIOD)) {
            return new WP_Error('rate_limit', sprintf('Rate limit exceeded. Max %d uploads per minute.', self::RATE_LIMIT_MAX));
        }

        if ($content === '' || strlen($content) > self::MAX_FILE_SIZE) {
            return new WP_Error('invalid_file', 'File is empty or exceeds 25MB limit');
        }

        $mime_type = sanitize_text_field($mime_type ?: 'application/octet-stream');
        if (!self::is_allowed_mime($mime_type)) {
            return new WP_Error('invalid_mime', 'Unsupported file type');
        }

        if ($idempotency_key === '') {
            $idempotency_key = orabooks_uuid();
        }
        $idempotency_key = sanitize_text_field($idempotency_key);

        $versions_table = OraBooks_Database::table(self::TABLE_VERSIONS);
        $existing_version = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$versions_table} WHERE idempotency_key = %s",
            $idempotency_key
        ));
        if ($existing_version) {
            return new WP_Error('duplicate', 'Attachment with this idempotency key already exists', ['version_id' => (int) $existing_version]);
        }

        $file_hash = hash('sha256', $content);
        $attachments_table = OraBooks_Database::table(self::TABLE_ATTACHMENTS);

        if ($attachment_id > 0) {
            $attachment = self::get_attachment($attachment_id, $org_id);
            if (!$attachment || $attachment->deleted_at) {
                return new WP_Error('not_found', 'Attachment not found');
            }
        } else {
            $duplicate = $wpdb->get_row($wpdb->prepare(
                "SELECT a.id, v.id AS version_id
                 FROM {$attachments_table} a
                 JOIN {$versions_table} v ON v.attachment_id = a.id
                 WHERE a.org_id = %d AND a.resource_type = %s AND a.resource_id = %d
                   AND a.deleted_at IS NULL AND v.file_hash = %s
                 ORDER BY v.version_number DESC LIMIT 1",
                $org_id,
                $resource_type,
                $resource_id,
                $file_hash
            ));
            if ($duplicate) {
                return [
                    'attachment_id' => (int) $duplicate->id,
                    'version_id'    => (int) $duplicate->version_id,
                    'deduplicated'  => true,
                ];
            }

            $wpdb->insert($attachments_table, [
                'org_id'        => $org_id,
                'resource_type' => $resource_type,
                'resource_id'   => $resource_id,
            ], ['%d', '%s', '%d']);

            $attachment_id = (int) $wpdb->insert_id;
            if (!$attachment_id) {
                return new WP_Error('db_error', 'Failed to create attachment record');
            }
        }

        $version_number = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(version_number), 0) + 1 FROM {$versions_table} WHERE attachment_id = %d",
            $attachment_id
        ));

        $storage = self::store_file($org_id, $filename, $content);
        if (is_wp_error($storage)) {
            return $storage;
        }

        $wpdb->insert($versions_table, [
            'attachment_id'     => $attachment_id,
            'version_number'    => $version_number,
            'file_name'         => sanitize_file_name($filename),
            'file_size'         => strlen($content),
            'file_hash'         => $file_hash,
            'mime_type'         => $mime_type,
            'storage_path'      => $storage['storage_path'],
            'uploaded_by'       => $user_id,
            'idempotency_key'   => $idempotency_key,
            'virus_scan_status' => 'clean',
        ], ['%d', '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s']);

        $version_id = (int) $wpdb->insert_id;
        if (!$version_id) {
            return new WP_Error('db_error', 'Failed to create attachment version');
        }

        $wpdb->update(
            $attachments_table,
            ['current_version_id' => $version_id, 'deleted_at' => null],
            ['id' => $attachment_id],
            ['%d', '%s'],
            ['%d']
        );

        orabooks_log_event('attachment_uploaded', "Attachment #{$attachment_id} v{$version_number} uploaded ({$resource_type})", 'info', [
            'attachment_id' => $attachment_id,
            'version_id'    => $version_id,
            'resource_type' => $resource_type,
            'resource_id'   => $resource_id,
            'file_hash'     => $file_hash,
        ], $user_id, $org_id);

        return [
            'attachment_id'  => $attachment_id,
            'version_id'     => $version_id,
            'version_number' => $version_number,
            'idempotency_key'=> $idempotency_key,
        ];
    }

    public static function list_attachments($org_id, $args = []) {
        global $wpdb;

        $table_attachments = OraBooks_Database::table(self::TABLE_ATTACHMENTS);
        $table_versions    = OraBooks_Database::table(self::TABLE_VERSIONS);
        $org_id = intval($org_id);
        $limit = max(1, min(100, intval($args['limit'] ?? 25)));
        $include_deleted = !empty($args['include_deleted']);

        $where = 'a.org_id = %d';
        $params = [$org_id];

        if (!$include_deleted) {
            $where .= ' AND a.deleted_at IS NULL';
        }

        if (!empty($args['resource_type'])) {
            $where .= ' AND a.resource_type = %s';
            $params[] = sanitize_text_field($args['resource_type']);
        }

        if (!empty($args['resource_id'])) {
            $where .= ' AND a.resource_id = %d';
            $params[] = intval($args['resource_id']);
        }

        $params[] = $limit;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, v.id AS version_id, v.version_number, v.file_name, v.file_size,
                    v.file_hash, v.mime_type, v.uploaded_by, v.uploaded_at, v.virus_scan_status
             FROM {$table_attachments} a
             LEFT JOIN {$table_versions} v ON v.id = a.current_version_id
             WHERE {$where}
             ORDER BY a.updated_at DESC
             LIMIT %d",
            $params
        ));
    }

    public static function get_attachment($attachment_id, $org_id = 0) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_ATTACHMENTS);
        if ($org_id > 0) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND org_id = %d",
                intval($attachment_id),
                intval($org_id)
            ));
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            intval($attachment_id)
        ));
    }

    public static function get_version($version_id, $org_id = 0) {
        global $wpdb;

        $table_versions = OraBooks_Database::table(self::TABLE_VERSIONS);
        $table_attachments = OraBooks_Database::table(self::TABLE_ATTACHMENTS);

        if ($org_id > 0) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT v.* FROM {$table_versions} v
                 JOIN {$table_attachments} a ON a.id = v.attachment_id
                 WHERE v.id = %d AND a.org_id = %d",
                intval($version_id),
                intval($org_id)
            ));
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_versions} WHERE id = %d",
            intval($version_id)
        ));
    }

    public static function list_versions($attachment_id, $org_id) {
        global $wpdb;

        $attachment = self::get_attachment($attachment_id, $org_id);
        if (!$attachment) {
            return [];
        }

        $table = OraBooks_Database::table(self::TABLE_VERSIONS);
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE attachment_id = %d ORDER BY version_number DESC",
            intval($attachment_id)
        ));
    }

    public static function soft_delete($attachment_id, $org_id, $user_id) {
        global $wpdb;

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_org_settings')) {
            return new WP_Error('permission_denied', 'Permission denied');
        }

        $attachment = self::get_attachment($attachment_id, $org_id);
        if (!$attachment || $attachment->deleted_at) {
            return new WP_Error('not_found', 'Attachment not found');
        }

        if ($attachment->retention_class === 'legal_hold') {
            return new WP_Error('legal_hold', 'Attachment is under legal hold and cannot be deleted');
        }

        $table = OraBooks_Database::table(self::TABLE_ATTACHMENTS);
        $wpdb->update(
            $table,
            ['deleted_at' => current_time('mysql')],
            ['id' => intval($attachment_id)],
            ['%s'],
            ['%d']
        );

        orabooks_log_event('attachment_deleted', "Attachment #{$attachment_id} soft deleted", 'info', [
            'attachment_id' => (int) $attachment_id,
            'resource_type' => $attachment->resource_type,
            'resource_id'   => (int) $attachment->resource_id,
        ], $user_id, $org_id);

        return true;
    }

    public static function get_attachment_stats($org_id) {
        global $wpdb;

        $table_attachments = OraBooks_Database::table(self::TABLE_ATTACHMENTS);
        $table_versions    = OraBooks_Database::table(self::TABLE_VERSIONS);

        $active = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_attachments} WHERE org_id = %d AND deleted_at IS NULL",
            intval($org_id)
        ));

        $deleted = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_attachments} WHERE org_id = %d AND deleted_at IS NOT NULL",
            intval($org_id)
        ));

        $total_bytes = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(v.file_size), 0)
             FROM {$table_attachments} a
             JOIN {$table_versions} v ON v.id = a.current_version_id
             WHERE a.org_id = %d AND a.deleted_at IS NULL",
            intval($org_id)
        ));

        return [
            'active_count'  => $active,
            'deleted_count' => $deleted,
            'total_bytes'   => $total_bytes,
        ];
    }

    public static function prepare_download($attachment_id, $org_id, $user_id, $version_id = 0) {
        $attachment = self::get_attachment($attachment_id, $org_id);
        if (!$attachment || $attachment->deleted_at) {
            return new WP_Error('not_found', 'Attachment not found');
        }

        if (!self::can_download($user_id, $org_id)) {
            return new WP_Error('permission_denied', 'Permission denied');
        }

        if (!self::can_access_resource($user_id, $org_id, (string) $attachment->resource_type, 'read')) {
            return new WP_Error('permission_denied', 'Permission denied');
        }

        if ($version_id <= 0) {
            $version_id = (int) $attachment->current_version_id;
        }

        $version = self::get_version($version_id, $org_id);
        if (!$version || (int) $version->attachment_id !== (int) $attachment_id) {
            return new WP_Error('not_found', 'Attachment version not found');
        }

        if ($version->virus_scan_status === 'infected') {
            return new WP_Error('infected', 'File is blocked due to virus scan results');
        }

        $content = self::read_stored_file($version->storage_path);
        if (is_wp_error($content)) {
            return $content;
        }

        orabooks_log_event('attachment_downloaded', "Attachment #{$attachment_id} v{$version->version_number} downloaded", 'info', [
            'attachment_id' => (int) $attachment_id,
            'version_id'      => (int) $version_id,
        ], $user_id, $org_id);

        return [
            'content'   => $content,
            'filename'  => $version->file_name,
            'mime_type' => $version->mime_type ?: 'application/octet-stream',
            'file_size' => (int) $version->file_size,
        ];
    }

    public static function format_attachment_row($row) {
        return [
            'id'                => (int) $row->id,
            'org_id'            => (int) $row->org_id,
            'resource_type'     => $row->resource_type,
            'resource_id'       => (int) $row->resource_id,
            'current_version_id'=> isset($row->current_version_id) ? (int) $row->current_version_id : null,
            'deleted_at'        => $row->deleted_at ?? null,
            'retention_class'   => $row->retention_class ?? 'standard',
            'created_at'        => $row->created_at ?? null,
            'updated_at'        => $row->updated_at ?? null,
            'version_id'        => isset($row->version_id) ? (int) $row->version_id : null,
            'version_number'    => isset($row->version_number) ? (int) $row->version_number : null,
            'file_name'         => $row->file_name ?? null,
            'file_size'         => isset($row->file_size) ? (int) $row->file_size : null,
            'file_hash'         => $row->file_hash ?? null,
            'mime_type'         => $row->mime_type ?? null,
            'uploaded_by'       => isset($row->uploaded_by) ? (int) $row->uploaded_by : null,
            'uploaded_at'       => $row->uploaded_at ?? null,
            'virus_scan_status' => $row->virus_scan_status ?? null,
        ];
    }

    public static function format_version($version) {
        return [
            'id'                => (int) $version->id,
            'attachment_id'     => (int) $version->attachment_id,
            'version_number'    => (int) $version->version_number,
            'file_name'         => $version->file_name,
            'file_size'         => (int) $version->file_size,
            'file_hash'         => $version->file_hash,
            'mime_type'         => $version->mime_type,
            'uploaded_by'       => (int) $version->uploaded_by,
            'uploaded_at'       => $version->uploaded_at,
            'virus_scan_status' => $version->virus_scan_status,
        ];
    }

    public static function can_download($user_id, $org_id) {
        return OraBooks_RBAC::require_permission($user_id, $org_id, 'view_reports');
    }

    public static function can_delete($user_id, $org_id) {
        return OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_org_settings');
    }

    public static function can_upload($user_id, $org_id) {
        return OraBooks_RBAC::require_permission($user_id, $org_id, 'submit_transaction');
    }

    private static function can_access_resource($user_id, $org_id, $resource_type, $action = 'read') {
        $permission = self::resource_permission($resource_type, $action);
        return OraBooks_RBAC::require_permission($user_id, $org_id, $permission);
    }

    private static function resource_permission($resource_type, $action = 'read') {
        $resource_type = sanitize_text_field((string) $resource_type);
        $action = $action === 'write' ? 'write' : 'read';

        $map = [
            'invoice' => ['read' => 'view_invoices', 'write' => 'create_invoice'],
            'customer' => ['read' => 'view_invoices', 'write' => 'create_invoice'],
            'bill' => ['read' => 'manage_expenses', 'write' => 'manage_expenses'],
            'expense' => ['read' => 'manage_expenses', 'write' => 'manage_expenses'],
            'vendor' => ['read' => 'manage_expenses', 'write' => 'manage_expenses'],
            'inventory_item' => ['read' => 'manage_inventory', 'write' => 'manage_inventory'],
        ];

        if (isset($map[$resource_type])) {
            return $map[$resource_type][$action];
        }

        return $action === 'write' ? 'submit_transaction' : 'view_reports';
    }

    private static function is_allowed_mime($mime_type) {
        foreach (self::ALLOWED_MIME_PREFIXES as $prefix) {
            if (strpos($mime_type, $prefix) === 0) {
                return true;
            }
        }

        return $mime_type === 'application/octet-stream';
    }

    private static function store_file($org_id, $filename, $content) {
        $upload_dir = wp_upload_dir();
        $base = $upload_dir['basedir'] . '/orabooks-attachments/' . intval($org_id);

        if (!wp_mkdir_p($base)) {
            return new WP_Error('storage_error', 'Could not create attachment storage directory');
        }

        $encrypted = self::encrypt_content($content);
        $safe_name = sanitize_file_name($filename);
        $storage_path = 'orabooks-attachments/' . intval($org_id) . '/' . wp_hash($content . microtime(true)) . '_' . $safe_name . '.enc';
        $full_path = $upload_dir['basedir'] . '/' . $storage_path;

        if (file_put_contents($full_path, $encrypted) === false) {
            return new WP_Error('storage_error', 'Could not save attachment file');
        }

        return ['storage_path' => $storage_path, 'full_path' => $full_path];
    }

    public static function read_stored_file($storage_path) {
        $upload_dir = wp_upload_dir();
        $path = $upload_dir['basedir'] . '/' . ltrim($storage_path, '/');

        if (!file_exists($path)) {
            return new WP_Error('file_missing', 'Attachment file not found');
        }

        $encrypted = file_get_contents($path);
        if ($encrypted === false) {
            return new WP_Error('file_read_error', 'Could not read attachment file');
        }

        $content = self::decrypt_content($encrypted);
        if ($content === false) {
            return new WP_Error('file_decrypt_error', 'Could not decrypt attachment file');
        }

        return $content;
    }

    private static function encrypt_content($data) {
        $method = 'aes-256-cbc';
        $key = self::get_file_encryption_key();
        $iv = substr(hash('sha256', $key . '_attachment_iv'), 0, 16);
        return openssl_encrypt($data, $method, $key, 0, $iv);
    }

    private static function decrypt_content($data) {
        $method = 'aes-256-cbc';
        $key = self::get_file_encryption_key();
        $iv = substr(hash('sha256', $key . '_attachment_iv'), 0, 16);
        return openssl_decrypt($data, $method, $key, 0, $iv);
    }

    private static function get_file_encryption_key() {
        if (class_exists('OraBooks_Secrets')) {
            return OraBooks_Secrets::get_encryption_key();
        }

        return wp_salt('auth');
    }

    public function cron_purge_old_attachments() {
        global $wpdb;

        $table_attachments = OraBooks_Database::table(self::TABLE_ATTACHMENTS);
        $table_versions    = OraBooks_Database::table(self::TABLE_VERSIONS);
        $cutoff = gmdate('Y-m-d H:i:s', time() - (self::RETENTION_DAYS * DAY_IN_SECONDS));

        $attachments = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$table_attachments}
             WHERE deleted_at IS NOT NULL
               AND deleted_at < %s
               AND retention_class = 'standard'",
            $cutoff
        ));

        if (empty($attachments)) {
            return;
        }

        $upload_dir = wp_upload_dir();
        foreach ($attachments as $attachment) {
            $versions = $wpdb->get_results($wpdb->prepare(
                "SELECT storage_path FROM {$table_versions} WHERE attachment_id = %d",
                (int) $attachment->id
            ));

            foreach ($versions ?: [] as $version) {
                $path = $upload_dir['basedir'] . '/' . ltrim($version->storage_path, '/');
                if (file_exists($path)) {
                    @unlink($path);
                }
            }

            $wpdb->delete($table_versions, ['attachment_id' => (int) $attachment->id], ['%d']);
            $wpdb->delete($table_attachments, ['id' => (int) $attachment->id], ['%d']);
        }

        orabooks_log_event('attachments_purged', count($attachments) . ' soft-deleted attachments purged', 'info', [
            'count' => count($attachments),
        ]);
    }

    private function current_user_id() {
        return orabooks_get_current_user_id();
    }

    private function require_customer_org_access($user_id, $org_id) {
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }
    }

    public function ajax_upload() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $resource_type = sanitize_text_field($_POST['resource_type'] ?? 'general');
        $resource_id = intval($_POST['resource_id'] ?? 0);
        $attachment_id = intval($_POST['attachment_id'] ?? 0);
        $idempotency_key = sanitize_text_field($_POST['idempotency_key'] ?? '');

        $this->require_customer_org_access($user_id, $org_id);

        if (empty($_FILES['attachment_file']['tmp_name'])) {
            orabooks_json_error('Attachment file is required', 400);
        }

        if ($resource_id <= 0) {
            orabooks_json_error('Resource ID is required', 400);
        }

        $content = file_get_contents($_FILES['attachment_file']['tmp_name']);
        $filename = sanitize_file_name($_FILES['attachment_file']['name'] ?? 'attachment.bin');
        $mime_type = sanitize_text_field($_FILES['attachment_file']['type'] ?? 'application/octet-stream');

        $result = self::upload_attachment(
            $org_id,
            $user_id,
            $resource_type,
            $resource_id,
            $filename,
            $content,
            $mime_type,
            $attachment_id,
            $idempotency_key
        );

        if (is_wp_error($result)) {
            $code = $result->get_error_code() === 'duplicate' ? 409 : 400;
            orabooks_json_error($result->get_error_message(), $code, $result->get_error_data());
        }

        orabooks_json_success($result);
    }

    public function ajax_list() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? $_GET['org_id'] ?? 0);
        $resource_type = sanitize_text_field($_POST['resource_type'] ?? $_GET['resource_type'] ?? '');
        $resource_id = intval($_POST['resource_id'] ?? $_GET['resource_id'] ?? 0);

        $this->require_customer_org_access($user_id, $org_id);

        $args = ['limit' => intval($_GET['limit'] ?? $_POST['limit'] ?? 25)];
        if ($resource_type !== '') {
            $args['resource_type'] = $resource_type;
        }
        if ($resource_id > 0) {
            $args['resource_id'] = $resource_id;
        }

        $rows = self::list_attachments($org_id, $args);
        orabooks_json_success([
            'attachments' => array_map([self::class, 'format_attachment_row'], $rows ?: []),
        ]);
    }

    public function ajax_get() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? $_GET['org_id'] ?? 0);
        $attachment_id = intval($_POST['attachment_id'] ?? $_GET['attachment_id'] ?? 0);

        $this->require_customer_org_access($user_id, $org_id);

        if (!$attachment_id) {
            orabooks_json_error('Missing attachment ID', 400);
        }

        $attachment = self::get_attachment($attachment_id, $org_id);
        if (!$attachment) {
            orabooks_json_error('Attachment not found', 404);
        }

        if (!self::can_access_resource($user_id, $org_id, (string) $attachment->resource_type, 'read')) {
            orabooks_json_error('Permission denied', 403);
        }

        $versions = self::list_versions($attachment_id, $org_id);
        $current = self::get_version((int) $attachment->current_version_id, $org_id);

        $row = (object) array_merge((array) $attachment, [
            'version_id'         => $current ? (int) $current->id : null,
            'version_number'     => $current ? (int) $current->version_number : null,
            'file_name'          => $current ? $current->file_name : null,
            'file_size'          => $current ? (int) $current->file_size : null,
            'file_hash'          => $current ? $current->file_hash : null,
            'mime_type'          => $current ? $current->mime_type : null,
            'uploaded_by'        => $current ? (int) $current->uploaded_by : null,
            'uploaded_at'        => $current ? $current->uploaded_at : null,
            'virus_scan_status'  => $current ? $current->virus_scan_status : null,
        ]);

        orabooks_json_success([
            'attachment' => self::format_attachment_row($row),
            'versions'   => array_map([self::class, 'format_version'], $versions ?: []),
            'capabilities' => [
                'download' => self::can_download($user_id, $org_id),
                'delete'   => self::can_delete($user_id, $org_id),
                'upload'   => self::can_upload($user_id, $org_id),
            ],
        ]);
    }

    public function ajax_delete() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $attachment_id = intval($_POST['attachment_id'] ?? 0);

        $this->require_customer_org_access($user_id, $org_id);

        if (!$attachment_id) {
            orabooks_json_error('Missing attachment ID', 400);
        }

        $result = self::soft_delete($attachment_id, $org_id, $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success([], 'Attachment deleted');
    }

    public function ajax_download() {
        $user_id = $this->current_user_id();
        $org_id = intval($_GET['org_id'] ?? 0);
        $attachment_id = intval($_GET['attachment_id'] ?? 0);
        $version_id = intval($_GET['version_id'] ?? 0);

        $this->require_customer_org_access($user_id, $org_id);

        if (!$attachment_id) {
            orabooks_json_error('Missing attachment ID', 400);
        }

        $download = self::prepare_download($attachment_id, $org_id, $user_id, $version_id);
        if (is_wp_error($download)) {
            orabooks_json_error($download->get_error_message(), 400);
        }

        nocache_headers();
        header('Content-Type: ' . $download['mime_type']);
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($download['filename']) . '"');
        header('Content-Length: ' . strlen($download['content']));
        echo $download['content'];
        exit;
    }
}
