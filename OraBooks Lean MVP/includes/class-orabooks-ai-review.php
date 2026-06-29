<?php
/**
 * OraBooks AI Review Queue (SL-076)
 *
 * Backend queue for low-confidence / high-risk items. Never mutates journal
 * status directly — publishes events or waits for SL-002 resolve_ai_review().
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Ai_Review {

    const TABLE_QUEUE       = 'ai_review_queue';
    const TABLE_HISTORY     = 'ai_review_history';
    const TABLE_DEAD_LETTERS = 'ai_review_dead_letters';
    const TABLE_MODELS      = 'ai_model_registry';

    const CONFIDENCE_THRESHOLD = 70.0;
    const MAX_RETRIES          = 3;
    const RETENTION_DAYS       = 90;
    const LEASE_SECONDS        = 300;
    const MODEL_VERSION        = 'mvp-stub-1.0';

    public static function active_model_version() {
        return class_exists('OraBooks_Ai_Providers')
            ? OraBooks_Ai_Providers::model_version('classification')
            : self::MODEL_VERSION;
    }

    private static $instance = null;

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();

            add_action('orabooks_ai_review_process', [self::$instance, 'cron_process_queue']);
            add_action('orabooks_ai_review_purge', [self::$instance, 'cron_purge_resolved']);

            add_action('orabooks_csv_row_escalated', [self::$instance, 'on_csv_row_escalated'], 10, 2);

            add_action('wp_ajax_orabooks_ai_review_list', [self::$instance, 'ajax_list']);
            add_action('wp_ajax_orabooks_ai_review_resolve', [self::$instance, 'ajax_resolve']);
        }

        return self::$instance;
    }

    public static function get_create_table_sql() {
        global $wpdb;

        $table_queue = OraBooks_Database::table(self::TABLE_QUEUE);
        $table_history = OraBooks_Database::table(self::TABLE_HISTORY);
        $table_dead = OraBooks_Database::table(self::TABLE_DEAD_LETTERS);
        $table_models = OraBooks_Database::table(self::TABLE_MODELS);
        $table_orgs = OraBooks_Database::table('organizations');
        $table_journals = OraBooks_Database::table('journals');
        $charset = $wpdb->get_charset_collate();

        return [
            "CREATE TABLE IF NOT EXISTS {$table_queue} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                resource_type VARCHAR(50) NOT NULL DEFAULT 'journal',
                resource_id BIGINT UNSIGNED NOT NULL,
                journal_id BIGINT UNSIGNED NULL,
                confidence_score DECIMAL(5,2) NOT NULL DEFAULT 0,
                risk_level ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
                escalation_reason VARCHAR(255) DEFAULT NULL,
                explanation TEXT DEFAULT NULL,
                model_version VARCHAR(50) DEFAULT NULL,
                total_amount DECIMAL(20,2) DEFAULT 0,
                priority_score INT NOT NULL DEFAULT 0,
                status ENUM('pending','processing','escalated','resolved') NOT NULL DEFAULT 'pending',
                retry_count INT UNSIGNED NOT NULL DEFAULT 0,
                next_retry_at TIMESTAMP NULL,
                lease_expires_at TIMESTAMP NULL,
                processing_token VARCHAR(64) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                resolved_at TIMESTAMP NULL,
                FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
                INDEX idx_org_status (org_id, status),
                INDEX idx_journal (journal_id),
                INDEX idx_resource (resource_type, resource_id),
                INDEX idx_retry (status, next_retry_at)
            ) {$charset};",
            "CREATE TABLE IF NOT EXISTS {$table_history} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                queue_id BIGINT UNSIGNED NOT NULL,
                org_id BIGINT UNSIGNED NOT NULL,
                action ENUM('enqueue','claim','retry','escalate','resolve','dead_letter','ready') NOT NULL,
                performed_by BIGINT UNSIGNED DEFAULT 0,
                details JSON DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (queue_id) REFERENCES {$table_queue}(id) ON DELETE CASCADE,
                INDEX idx_queue (queue_id),
                INDEX idx_org_created (org_id, created_at)
            ) {$charset};",
            "CREATE TABLE IF NOT EXISTS {$table_dead} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                queue_id BIGINT UNSIGNED NOT NULL,
                org_id BIGINT UNSIGNED NOT NULL,
                resource_type VARCHAR(50) NOT NULL,
                resource_id BIGINT UNSIGNED NOT NULL,
                journal_id BIGINT UNSIGNED NULL,
                payload JSON DEFAULT NULL,
                moved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_org (org_id)
            ) {$charset};",
            "CREATE TABLE IF NOT EXISTS {$table_models} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                model_key VARCHAR(100) NOT NULL UNIQUE,
                provider VARCHAR(100) DEFAULT 'mvp-stub',
                version VARCHAR(50) NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                config JSON DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) {$charset};",
        ];
    }

    public static function passes_threshold(array $evaluation) {
        $confidence = (float) ($evaluation['confidence'] ?? 0);
        $risk = $evaluation['risk_level'] ?? 'high';
        return $confidence >= self::CONFIDENCE_THRESHOLD && $risk === 'low';
    }

    public static function evaluate_journal($journal_id, $org_id) {
        global $wpdb;

        $journal_id = intval($journal_id);
        $table_journals = OraBooks_Database::table('journals');
        $table_lines = OraBooks_Database::table('journal_lines');

        $journal = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_journals} WHERE id = %d AND org_id = %d",
            $journal_id,
            intval($org_id)
        ));

        if (!$journal) {
            return [
                'confidence'   => 50.0,
                'risk_level'   => 'high',
                'explanation'  => 'Journal not found for evaluation',
                'model_version'=> self::active_model_version(),
            ];
        }

        $lines = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_lines} WHERE journal_id = %d",
            $journal_id
        ));

        $confidence = 88.0;
        $risk = 'low';
        $reasons = [];

        $line_count = count($lines ?: []);
        if ($line_count < 2) {
            $confidence = 55.0;
            $risk = 'high';
            $reasons[] = 'insufficient journal lines';
        }

        foreach ($lines ?: [] as $line) {
            if (trim((string) $line->description) === '') {
                $confidence = min($confidence, 62.0);
                $risk = $risk === 'low' ? 'medium' : $risk;
                $reasons[] = 'missing line descriptions';
                break;
            }
        }

        $amount = (float) $journal->total_amount;
        if ($amount >= 50000) {
            $confidence = min($confidence, 64.0);
            $risk = $amount >= 100000 ? 'high' : 'medium';
            $reasons[] = 'high-value transaction';
        }

        if ($confidence < self::CONFIDENCE_THRESHOLD && $risk === 'low') {
            $risk = 'medium';
        }

        return [
            'confidence'    => round($confidence, 2),
            'risk_level'    => $risk,
            'explanation'   => $reasons
                ? 'AI could not classify confidently because: ' . implode(', ', $reasons)
                : 'Standard journal entry passed automated checks',
            'model_version' => self::active_model_version(),
        ];
    }

    public static function enqueue($org_id, $resource_type, $resource_id, $journal_id, array $evaluation, $total_amount = 0) {
        global $wpdb;

        $org_id = intval($org_id);
        $resource_id = intval($resource_id);
        $journal_id = $journal_id ? intval($journal_id) : null;
        $resource_type = sanitize_text_field($resource_type);

        $table = OraBooks_Database::table(self::TABLE_QUEUE);
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE org_id = %d AND resource_type = %s AND resource_id = %d
               AND status IN ('pending','processing','escalated')",
            $org_id,
            $resource_type,
            $resource_id
        ));

        if ($existing) {
            return new WP_Error('duplicate', 'Item already in AI review queue', ['queue_id' => (int) $existing]);
        }

        $priority = self::compute_priority($evaluation, $total_amount);

        $wpdb->insert($table, [
            'org_id'            => $org_id,
            'resource_type'     => $resource_type,
            'resource_id'       => $resource_id,
            'journal_id'        => $journal_id,
            'confidence_score'  => (float) ($evaluation['confidence'] ?? 0),
            'risk_level'        => sanitize_text_field($evaluation['risk_level'] ?? 'medium'),
            'escalation_reason' => sanitize_text_field($evaluation['escalation_reason'] ?? 'low_confidence'),
            'explanation'       => sanitize_textarea_field($evaluation['explanation'] ?? ''),
            'model_version'     => sanitize_text_field($evaluation['model_version'] ?? self::active_model_version()),
            'total_amount'      => (float) $total_amount,
            'priority_score'    => $priority,
            'status'            => 'pending',
        ], ['%d', '%s', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%f', '%d', '%s']);

        $queue_id = (int) $wpdb->insert_id;
        if (!$queue_id) {
            return new WP_Error('db_error', 'Failed to enqueue AI review item');
        }

        self::record_history($queue_id, $org_id, 'enqueue', 0, [
            'confidence' => $evaluation['confidence'] ?? 0,
            'risk_level' => $evaluation['risk_level'] ?? 'medium',
        ]);

        orabooks_log_event('ai_review_enqueued', "AI review queue item #{$queue_id} created", 'info', [
            'queue_id'      => $queue_id,
            'resource_type' => $resource_type,
            'resource_id'   => $resource_id,
            'journal_id'    => $journal_id,
        ], null, $org_id);

        return ['id' => $queue_id];
    }

    public static function resolve_ai_review($journal_id, $org_id, $user_id = 0) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_QUEUE);
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE org_id = %d AND journal_id = %d AND status IN ('pending','processing','escalated')",
            intval($org_id),
            intval($journal_id)
        ));

        foreach ($items ?: [] as $item) {
            $wpdb->update($table, [
                'status'      => 'resolved',
                'resolved_at' => current_time('mysql'),
            ], ['id' => (int) $item->id], ['%s', '%s'], ['%d']);

            self::record_history((int) $item->id, (int) $item->org_id, 'resolve', (int) $user_id, [
                'journal_id' => (int) $journal_id,
            ]);
        }

        return count($items ?: []);
    }

    public static function resolve_ai_review_by_resource($org_id, $resource_type, $resource_id, $user_id = 0) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_QUEUE);
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE org_id = %d AND resource_type = %s AND resource_id = %d
               AND status IN ('pending','processing','escalated')",
            intval($org_id),
            sanitize_text_field($resource_type),
            intval($resource_id)
        ));

        foreach ($items ?: [] as $item) {
            $wpdb->update($table, [
                'status'      => 'resolved',
                'resolved_at' => current_time('mysql'),
            ], ['id' => (int) $item->id], ['%s', '%s'], ['%d']);

            self::record_history((int) $item->id, (int) $item->org_id, 'resolve', (int) $user_id, [
                'resource_type' => $resource_type,
                'resource_id'   => (int) $resource_id,
            ]);
        }

        return count($items ?: []);
    }

    public static function list_queue($org_id, $args = []) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_QUEUE);
        $journals = OraBooks_Database::table('journals');
        $org_id = intval($org_id);
        $limit = max(1, min(100, intval($args['limit'] ?? 25)));

        $statuses = !empty($args['statuses'])
            ? (array) $args['statuses']
            : ['escalated', 'pending'];

        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $params = array_merge([$org_id], $statuses, [$limit]);

        return $wpdb->get_results($wpdb->prepare(
            "SELECT q.*, j.journal_number
             FROM {$table} q
             LEFT JOIN {$journals} j ON j.id = q.journal_id
             WHERE q.org_id = %d AND q.status IN ({$placeholders})
             ORDER BY q.priority_score DESC, q.created_at DESC
             LIMIT %d",
            $params
        ));
    }

    public static function get_queue_stats($org_id) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_QUEUE);
        $org_id = intval($org_id);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) AS total FROM {$table} WHERE org_id = %d GROUP BY status",
            $org_id
        ));

        $stats = [
            'pending'    => 0,
            'processing' => 0,
            'escalated'  => 0,
            'resolved'   => 0,
            'total_open' => 0,
        ];

        foreach ($rows ?: [] as $row) {
            if (isset($stats[$row->status])) {
                $stats[$row->status] = (int) $row->total;
            }
        }

        $stats['total_open'] = $stats['pending'] + $stats['processing'] + $stats['escalated'];

        return $stats;
    }

    public static function format_queue_item($row) {
        return [
            'id'                => (int) $row->id,
            'org_id'            => (int) $row->org_id,
            'resource_type'     => $row->resource_type,
            'resource_id'       => (int) $row->resource_id,
            'journal_id'        => $row->journal_id ? (int) $row->journal_id : null,
            'journal_number'    => $row->journal_number ?? null,
            'confidence_score'  => (float) $row->confidence_score,
            'risk_level'        => $row->risk_level,
            'escalation_reason' => $row->escalation_reason,
            'explanation'       => $row->explanation,
            'total_amount'      => (float) $row->total_amount,
            'priority_score'    => (int) $row->priority_score,
            'status'            => $row->status,
            'retry_count'       => (int) $row->retry_count,
            'created_at'        => $row->created_at,
            'updated_at'        => $row->updated_at,
        ];
    }

    public function cron_process_queue() {
        for ($processed = 0; $processed < 5; $processed++) {
            $item = $this->claim_next_item();
            if (!$item) {
                break;
            }

            $this->process_queue_item($item);
        }
    }

    private function claim_next_item() {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_QUEUE);
        $now = current_time('mysql', true);
        $token = orabooks_uuid();
        $lease = gmdate('Y-m-d H:i:s', time() + self::LEASE_SECONDS);

        $wpdb->query('START TRANSACTION');

        try {
            $item = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE status = 'pending'
                   AND (next_retry_at IS NULL OR next_retry_at <= %s)
                   AND (lease_expires_at IS NULL OR lease_expires_at <= %s)
                 ORDER BY priority_score DESC, created_at ASC
                 LIMIT 1
                 FOR UPDATE SKIP LOCKED",
                $now,
                $now
            ));

            if (!$item) {
                $wpdb->query('ROLLBACK');
                return null;
            }

            $updated = $wpdb->update($table, [
                'status' => 'processing',
                'processing_token' => $token,
                'lease_expires_at' => $lease,
            ], [
                'id' => (int) $item->id,
                'status' => 'pending',
            ], ['%s', '%s', '%s'], ['%d', '%s']);

            if (!$updated) {
                $wpdb->query('ROLLBACK');
                return null;
            }

            self::record_history((int) $item->id, (int) $item->org_id, 'claim', 0, ['token' => $token]);
            $wpdb->query('COMMIT');

            $item->status = 'processing';
            $item->processing_token = $token;
            $item->lease_expires_at = $lease;

            return $item;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            return null;
        }
    }

    private function process_queue_item($item) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_QUEUE);

        if ($item->resource_type === 'journal' && $item->journal_id) {
            $evaluation = self::evaluate_journal((int) $item->journal_id, (int) $item->org_id);
        } else {
            $evaluation = [
                'confidence'    => (float) $item->confidence_score,
                'risk_level'    => $item->risk_level,
                'explanation'   => $item->explanation,
                'model_version' => $item->model_version,
            ];
        }

        if (self::passes_threshold($evaluation)) {
            if (function_exists('orabooks_publish_event')) {
                orabooks_publish_event('journal_ready_for_review', (int) ($item->journal_id ?: $item->resource_id), [
                    'journal_id' => (int) $item->journal_id,
                    'org_id'     => (int) $item->org_id,
                    'queue_id'   => (int) $item->id,
                ]);
            }

            $wpdb->update($table, [
                'status'           => 'resolved',
                'resolved_at'      => current_time('mysql'),
                'confidence_score' => (float) $evaluation['confidence'],
                'risk_level'       => sanitize_text_field($evaluation['risk_level']),
                'processing_token' => null,
                'lease_expires_at' => null,
            ], ['id' => (int) $item->id], ['%s', '%s', '%f', '%s', '%s', '%s'], ['%d']);

            self::record_history((int) $item->id, (int) $item->org_id, 'ready', 0, $evaluation);
            return;
        }

        $retry_count = self::next_retry_count((int) $item->retry_count);

        if (self::should_escalate_after_retry($retry_count)) {
            $this->escalate_item($item, $evaluation);
            return;
        }

        $backoff = self::backoff_seconds_for_retry($retry_count);
        $next_retry = gmdate('Y-m-d H:i:s', time() + $backoff);

        $wpdb->update($table, [
            'status'           => 'pending',
            'retry_count'      => $retry_count,
            'next_retry_at'    => $next_retry,
            'confidence_score' => (float) $evaluation['confidence'],
            'risk_level'       => sanitize_text_field($evaluation['risk_level']),
            'explanation'      => sanitize_textarea_field($evaluation['explanation'] ?? ''),
            'processing_token' => null,
            'lease_expires_at' => null,
        ], ['id' => (int) $item->id], ['%s', '%d', '%s', '%f', '%s', '%s', '%s', '%s'], ['%d']);

        self::record_history((int) $item->id, (int) $item->org_id, 'retry', 0, [
            'retry_count' => $retry_count,
            'next_retry_at' => $next_retry,
        ]);
    }

    private function escalate_item($item, array $evaluation) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_QUEUE);

        $wpdb->update($table, [
            'status'           => 'escalated',
            'confidence_score' => (float) ($evaluation['confidence'] ?? $item->confidence_score),
            'risk_level'       => sanitize_text_field($evaluation['risk_level'] ?? $item->risk_level),
            'explanation'      => sanitize_textarea_field($evaluation['explanation'] ?? $item->explanation),
            'processing_token' => null,
            'lease_expires_at' => null,
        ], ['id' => (int) $item->id], ['%s', '%f', '%s', '%s', '%s', '%s'], ['%d']);

        self::record_history((int) $item->id, (int) $item->org_id, 'escalate', 0, $evaluation);

        if (function_exists('orabooks_publish_event')) {
            orabooks_publish_event('ai_review_escalated', (int) ($item->journal_id ?: $item->resource_id), [
                'queue_id'      => (int) $item->id,
                'org_id'        => (int) $item->org_id,
                'journal_id'    => (int) $item->journal_id,
                'resource_type' => $item->resource_type,
                'confidence'    => (float) ($evaluation['confidence'] ?? 0),
                'risk_level'    => $evaluation['risk_level'] ?? 'high',
            ]);
        }

        // Keep escalated item visible in queue UI, and also archive a dead-letter copy
        // for manual forensic inspection and operational triage.
        $dead_letter_id = $this->copy_to_dead_letters($item, $evaluation, 'max_retries_exceeded');
        self::record_history((int) $item->id, (int) $item->org_id, 'dead_letter', 0, [
            'dead_letter_id' => $dead_letter_id,
            'reason' => 'max_retries_exceeded',
            'retry_count' => (int) $item->retry_count + 1,
        ]);
    }

    private function copy_to_dead_letters($item, array $evaluation, $reason = 'unknown') {
        global $wpdb;

        $table_dead = OraBooks_Database::table(self::TABLE_DEAD_LETTERS);

        $payload = [
            'reason' => sanitize_text_field((string) $reason),
            'retry_count' => (int) $item->retry_count + 1,
            'evaluation' => [
                'confidence' => (float) ($evaluation['confidence'] ?? $item->confidence_score ?? 0),
                'risk_level' => sanitize_text_field((string) ($evaluation['risk_level'] ?? $item->risk_level ?? 'high')),
                'explanation' => sanitize_textarea_field((string) ($evaluation['explanation'] ?? $item->explanation ?? '')),
                'model_version' => sanitize_text_field((string) ($evaluation['model_version'] ?? $item->model_version ?? self::active_model_version())),
            ],
            'queue_snapshot' => [
                'queue_id' => (int) $item->id,
                'org_id' => (int) $item->org_id,
                'resource_type' => sanitize_text_field((string) $item->resource_type),
                'resource_id' => (int) $item->resource_id,
                'journal_id' => $item->journal_id ? (int) $item->journal_id : null,
                'status' => sanitize_text_field((string) $item->status),
                'priority_score' => (int) ($item->priority_score ?? 0),
                'total_amount' => (float) ($item->total_amount ?? 0),
                'created_at' => $item->created_at ?? null,
                'updated_at' => $item->updated_at ?? null,
            ],
        ];

        $wpdb->insert($table_dead, [
            'queue_id' => (int) $item->id,
            'org_id' => (int) $item->org_id,
            'resource_type' => sanitize_text_field((string) $item->resource_type),
            'resource_id' => (int) $item->resource_id,
            'journal_id' => $item->journal_id ? (int) $item->journal_id : null,
            'payload' => wp_json_encode($payload),
        ], ['%d', '%d', '%s', '%d', '%d', '%s']);

        return (int) $wpdb->insert_id;
    }

    public function on_csv_row_escalated($import_id, $data) {
        $org_id = (int) ($data['org_id'] ?? 0);
        $row_index = (int) ($data['row_index'] ?? 0);
        $confidence = (float) ($data['confidence'] ?? 0);

        if (!$org_id) {
            return;
        }

        self::enqueue($org_id, 'csv_import', (int) $import_id, null, [
            'confidence'        => $confidence,
            'risk_level'        => $confidence < 50 ? 'high' : 'medium',
            'explanation'       => sprintf('CSV import row %d flagged for manual review', $row_index + 1),
            'model_version'     => self::active_model_version(),
            'escalation_reason' => 'csv_low_confidence',
        ], 0);
    }

    public function cron_purge_resolved() {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_QUEUE);
        $cutoff = gmdate('Y-m-d H:i:s', time() - (self::RETENTION_DAYS * DAY_IN_SECONDS));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE status = 'resolved' AND resolved_at IS NOT NULL AND resolved_at < %s",
            $cutoff
        ));
    }

    private static function compute_priority(array $evaluation, $total_amount) {
        $score = 0;
        $risk = $evaluation['risk_level'] ?? 'medium';
        if ($risk === 'high') {
            $score += 100;
        } elseif ($risk === 'medium') {
            $score += 50;
        }
        $score += min(50, (int) round(((float) $total_amount) / 1000));
        $score += max(0, (int) round((self::CONFIDENCE_THRESHOLD - (float) ($evaluation['confidence'] ?? 0)) / 2));
        return $score;
    }

    public static function next_retry_count($current_retry_count) {
        return max(0, (int) $current_retry_count) + 1;
    }

    public static function should_escalate_after_retry($retry_count) {
        return (int) $retry_count > self::MAX_RETRIES;
    }

    public static function backoff_seconds_for_retry($retry_count) {
        $retry_count = max(1, (int) $retry_count);
        return (int) (pow(2, $retry_count) * 5);
    }

    private static function record_history($queue_id, $org_id, $action, $user_id, $details = []) {
        global $wpdb;

        $table = OraBooks_Database::table(self::TABLE_HISTORY);
        $wpdb->insert($table, [
            'queue_id'      => intval($queue_id),
            'org_id'        => intval($org_id),
            'action'        => sanitize_text_field($action),
            'performed_by'  => intval($user_id),
            'details'       => wp_json_encode($details),
        ], ['%d', '%d', '%s', '%d', '%s']);
    }

    private function current_user_id() {
        return orabooks_get_current_user_id();
    }

    private function require_queue_access($user_id, $org_id) {
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'view_ai_review_queue')) {
            orabooks_json_error('Permission denied', 403);
        }
    }

    public function ajax_list() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? $_GET['org_id'] ?? 0);

        $this->require_queue_access($user_id, $org_id);

        $status = sanitize_text_field($_GET['status'] ?? $_POST['status'] ?? '');
        $args = ['limit' => intval($_GET['limit'] ?? $_POST['limit'] ?? 25)];
        if ($status !== '') {
            $args['statuses'] = [$status];
        }

        $rows = self::list_queue($org_id, $args);
        orabooks_json_success([
            'items' => array_map([self::class, 'format_queue_item'], $rows ?: []),
        ]);
    }

    public function ajax_resolve() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? $_GET['org_id'] ?? 0);
        $queue_id = intval($_POST['queue_id'] ?? $_GET['queue_id'] ?? 0);
        $journal_id = intval($_POST['journal_id'] ?? $_GET['journal_id'] ?? 0);
        $resource_type = sanitize_text_field($_POST['resource_type'] ?? $_GET['resource_type'] ?? '');
        $resource_id = intval($_POST['resource_id'] ?? $_GET['resource_id'] ?? 0);

        $this->require_queue_access($user_id, $org_id);

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'approve_journal')) {
            orabooks_json_error('Permission denied', 403);
        }

        $resolved = 0;

        if ($queue_id > 0) {
            global $wpdb;
            $table = OraBooks_Database::table(self::TABLE_QUEUE);
            $item = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND org_id = %d",
                $queue_id,
                $org_id
            ));

            if (!$item) {
                orabooks_json_error('Queue item not found', 404);
            }

            if ($item->journal_id) {
                $resolved = self::resolve_ai_review((int) $item->journal_id, $org_id, $user_id);
            } else {
                $resolved = self::resolve_ai_review_by_resource(
                    $org_id,
                    $item->resource_type,
                    (int) $item->resource_id,
                    $user_id
                );
            }
        } elseif ($journal_id > 0) {
            $resolved = self::resolve_ai_review($journal_id, $org_id, $user_id);
        } elseif ($resource_type !== '' && $resource_id > 0) {
            $resolved = self::resolve_ai_review_by_resource($org_id, $resource_type, $resource_id, $user_id);
        } else {
            orabooks_json_error('Missing parameters', 400);
        }

        orabooks_json_success([
            'resolved_count' => $resolved,
            'message'        => $resolved > 0
                ? sprintf('%d AI review item(s) marked resolved.', $resolved)
                : 'No open AI review items matched.',
        ]);
    }
}
