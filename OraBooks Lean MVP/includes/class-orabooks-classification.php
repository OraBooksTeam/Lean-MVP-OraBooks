<?php
/**
 * OraBooks Smart Classification & Tax Hints (SL-022)
 *
 * Rule-first + AI-stub account mapping and SL-305 tax hints for expenses/invoices/journal lines.
 * Suggestions only — never posts or approves. Async via SL-303, events via SL-302.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Classification {

    const CONFIDENCE_THRESHOLD = 70.0;
    const MODEL_VERSION        = 'mvp-stub-1.0';
    const TAX_ENGINE_VERSION   = 'sl-305-1.0';
    const RATE_LIMIT_MAX       = 10;
    const RATE_LIMIT_PERIOD    = 60;

    private static $instance = null;

    private static $record_types = [
        'expense' => [
            'table'         => 'expenses',
            'org_column'    => 'org_id',
            'amount_column' => 'total_amount',
            'text_columns'  => ['vendor', 'category', 'description'],
        ],
        'invoice' => [
            'table'         => 'invoices',
            'org_column'    => 'org_id',
            'amount_column' => 'total_amount',
            'text_columns'  => ['description'],
        ],
        'journal_line' => [
            'table'          => 'journal_lines',
            'org_column'     => null,
            'org_join'       => [
                'table'      => 'journals',
                'line_fk'    => 'journal_id',
                'org_column' => 'org_id',
            ],
            'amount_columns' => ['debit_amount', 'credit_amount'],
            'text_columns'   => ['description', 'account_code'],
        ],
    ];

    private static $default_rules = [
        ['rule_type' => 'keyword', 'match_value' => 'office', 'account_code' => '5100', 'priority' => 10],
        ['rule_type' => 'keyword', 'match_value' => 'meal', 'account_code' => '5200', 'priority' => 10],
        ['rule_type' => 'keyword', 'match_value' => 'travel', 'account_code' => '5300', 'priority' => 10],
        ['rule_type' => 'keyword', 'match_value' => 'salary', 'account_code' => '5000', 'priority' => 10],
        ['rule_type' => 'keyword', 'match_value' => 'payroll', 'account_code' => '5000', 'priority' => 10],
        ['rule_type' => 'keyword', 'match_value' => 'software', 'account_code' => '5500', 'priority' => 10],
        ['rule_type' => 'vendor', 'match_value' => 'amazon', 'account_code' => '5100', 'priority' => 20],
        ['rule_type' => 'vendor', 'match_value' => 'uber', 'account_code' => '5300', 'priority' => 20],
    ];

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();

            add_action('wp_ajax_orabooks_classification_run', [self::$instance, 'ajax_run']);
            add_action('wp_ajax_nopriv_orabooks_classification_run', [self::$instance, 'ajax_run']);
            add_action('wp_ajax_orabooks_classification_apply', [self::$instance, 'ajax_apply']);
            add_action('wp_ajax_nopriv_orabooks_classification_apply', [self::$instance, 'ajax_apply']);
            add_action('wp_ajax_orabooks_classification_override', [self::$instance, 'ajax_override']);
            add_action('wp_ajax_nopriv_orabooks_classification_override', [self::$instance, 'ajax_override']);

            if (class_exists('OraBooks_AsyncQueue')) {
                OraBooks_AsyncQueue::register_handler('classify_transaction', [self::class, 'handle_async_job']);
            }
        }

        return self::$instance;
    }

    public static function register_event_consumer() {
        if (!class_exists('OraBooks_EventBus')) {
            return;
        }

        OraBooks_EventBus::register_consumer('classification_requested', function ($event, $payload) {
            if (!class_exists('OraBooks_AsyncQueue')) {
                return;
            }

            OraBooks_AsyncQueue::enqueue('classify_transaction', [
                'record_type' => $payload['record_type'] ?? '',
                'record_id'   => (int) ($payload['record_id'] ?? 0),
                'org_id'      => (int) ($payload['org_id'] ?? 0),
                'idempotency_key' => $payload['idempotency_key'] ?? '',
            ], ['priority' => 4]);
        });
    }

    public static function get_create_table_sql() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table = OraBooks_Database::table('classification_rules');
        $orgs = OraBooks_Database::table('organizations');

        return [
            "CREATE TABLE IF NOT EXISTS {$table} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                rule_type ENUM('vendor','keyword','category') NOT NULL DEFAULT 'keyword',
                match_value VARCHAR(255) NOT NULL,
                account_code VARCHAR(20) NOT NULL,
                tax_jurisdiction VARCHAR(32) DEFAULT NULL,
                priority INT NOT NULL DEFAULT 10,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (org_id) REFERENCES {$orgs}(id) ON DELETE CASCADE,
                INDEX idx_org_active (org_id, is_active, priority)
            ) {$charset};",
        ];
    }

    /**
     * Add classification columns to transaction tables (idempotent).
     */
    public static function ensure_schema() {
        global $wpdb;

        foreach (['expenses', 'invoices', 'journal_lines'] as $base_table) {
            $table = OraBooks_Database::table($base_table);
            $existing = $wpdb->get_results("SHOW COLUMNS FROM {$table}");
            $fields = array_map(function ($col) {
                return $col->Field;
            }, $existing ?: []);

            if (!in_array('classification_status', $fields, true)) {
                if (in_array('updated_at', $fields, true)) {
                    $wpdb->query("ALTER TABLE {$table} ADD COLUMN classification_status ENUM('pending','processed','overridden','failed') NOT NULL DEFAULT 'pending' AFTER updated_at");
                } else {
                    $wpdb->query("ALTER TABLE {$table} ADD COLUMN classification_status ENUM('pending','processed','overridden','failed') NOT NULL DEFAULT 'pending'");
                }
            }
            if (!in_array('suggested_account_code', $fields, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN suggested_account_code VARCHAR(20) DEFAULT NULL");
            }
            if (!in_array('suggested_account_id', $fields, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN suggested_account_id BIGINT UNSIGNED DEFAULT NULL");
            }
            if (!in_array('account_confidence', $fields, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN account_confidence DECIMAL(5,2) DEFAULT NULL");
            }
            if (!in_array('tax_hints', $fields, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN tax_hints JSON DEFAULT NULL");
            }
            if (!in_array('classification_risk_score', $fields, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN classification_risk_score JSON DEFAULT NULL");
            }
            if (!in_array('classification_model_version', $fields, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN classification_model_version VARCHAR(20) DEFAULT NULL");
            }
            if (!in_array('tax_engine_version', $fields, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN tax_engine_version VARCHAR(20) DEFAULT NULL");
            }
            if (!in_array('classification_idempotency_key', $fields, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN classification_idempotency_key VARCHAR(128) DEFAULT NULL");
            }
            if (!in_array('classification_reason', $fields, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN classification_reason TEXT DEFAULT NULL");
            }
            if (!in_array('last_classified_at', $fields, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN last_classified_at TIMESTAMP NULL DEFAULT NULL");
            }

            $indexes = $wpdb->get_results("SHOW INDEX FROM {$table}");
            $index_map = [];
            foreach ($indexes ?: [] as $idx) {
                $index_map[$idx->Key_name] = (int) $idx->Non_unique;
            }

            if ($base_table === 'journal_lines') {
                if (!isset($index_map['uk_journal_classification_idempotency'])) {
                    $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY uk_journal_classification_idempotency (journal_id, classification_idempotency_key)");
                }
                continue;
            }

            if (isset($index_map['idx_org_classification_idempotency']) && (int) $index_map['idx_org_classification_idempotency'] === 1) {
                $wpdb->query("ALTER TABLE {$table} DROP INDEX idx_org_classification_idempotency");
            }

            if (!isset($index_map['uk_org_classification_idempotency'])) {
                $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY uk_org_classification_idempotency (org_id, classification_idempotency_key)");
            }
        }
    }

    public static function seed_default_rules($org_id) {
        global $wpdb;

        $org_id = (int) $org_id;
        $table = OraBooks_Database::table('classification_rules');
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE org_id = %d",
            $org_id
        ));

        if ($count > 0) {
            return;
        }

        foreach (self::$default_rules as $rule) {
            $wpdb->insert($table, [
                'org_id'       => $org_id,
                'rule_type'    => $rule['rule_type'],
                'match_value'  => $rule['match_value'],
                'account_code' => $rule['account_code'],
                'priority'     => (int) $rule['priority'],
                'is_active'    => 1,
            ], ['%d', '%s', '%s', '%s', '%d', '%d']);
        }
    }

    /**
     * Queue classification for a draft transaction.
     */
    public static function request($record_type, $record_id, $org_id, $context = []) {
        global $wpdb;

        $record_type = sanitize_text_field($record_type);
        $record_id = (int) $record_id;
        $org_id = (int) $org_id;

        if (!isset(self::$record_types[$record_type])) {
            return new WP_Error('invalid_type', __('Unknown record type', 'orabooks'));
        }

        if (!orabooks_check_rate_limit("classification_{$org_id}", self::RATE_LIMIT_MAX, self::RATE_LIMIT_PERIOD)) {
            return new WP_Error('rate_limit', __('Too many classification requests. Please try again later.', 'orabooks'), ['status' => 429]);
        }

        self::seed_default_rules($org_id);

        $record = self::get_record($record_type, $record_id, $org_id);
        if (!$record) {
            return new WP_Error('not_found', __('Record not found', 'orabooks'));
        }

        $idempotency_key = $context['idempotency_key'] ?? self::build_idempotency_key($record_type, $record_id, $record);
        $map = self::$record_types[$record_type];
        $table = OraBooks_Database::table($map['table']);

        if (self::uses_org_join($map)) {
            $join_table = OraBooks_Database::table($map['org_join']['table']);
            $line_fk = $map['org_join']['line_fk'];
            $join_org_column = $map['org_join']['org_column'];
            $duplicate = $wpdb->get_var($wpdb->prepare(
                "SELECT t.id
                   FROM {$table} t
                   INNER JOIN {$join_table} j ON j.id = t.{$line_fk}
                  WHERE j.{$join_org_column} = %d
                    AND t.classification_idempotency_key = %s
                    AND t.id != %d
                    AND t.classification_status IN ('pending','processed')",
                $org_id,
                $idempotency_key,
                $record_id
            ));
        } else {
            $duplicate = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table}
                 WHERE org_id = %d AND classification_idempotency_key = %s
                   AND id != %d AND classification_status IN ('pending','processed')",
                $org_id,
                $idempotency_key,
                $record_id
            ));
        }

        if ($duplicate) {
            return new WP_Error('duplicate', __('Classification already requested for this content hash', 'orabooks'), ['status' => 409]);
        }

        self::update_classification_fields($map, $record_id, $org_id, [
            'classification_status'          => 'pending',
            'classification_idempotency_key' => $idempotency_key,
        ], ['%s', '%s']);

        $payload = [
            'record_type'     => $record_type,
            'record_id'       => $record_id,
            'org_id'          => $org_id,
            'idempotency_key' => $idempotency_key,
        ];

        if (function_exists('orabooks_publish_event')) {
            orabooks_publish_event('classification_requested', $record_id, $payload);
        } elseif (class_exists('OraBooks_AsyncQueue')) {
            OraBooks_AsyncQueue::enqueue('classify_transaction', $payload, ['priority' => 4]);
        } else {
            self::run($record_type, $record_id, $org_id);
        }

        return [
            'record_type'     => $record_type,
            'record_id'       => $record_id,
            'status'          => 'pending',
            'idempotency_key' => $idempotency_key,
        ];
    }

    public static function handle_async_job($job, $payload) {
        $record_type = sanitize_text_field($payload['record_type'] ?? '');
        $record_id = (int) ($payload['record_id'] ?? 0);
        $org_id = (int) ($payload['org_id'] ?? 0);

        $result = self::run(
            $record_type,
            $record_id,
            $org_id
        );

        if (is_wp_error($result)) {
            self::mark_failed($record_type, $record_id, $org_id, $result->get_error_message());
            return $result->get_error_message();
        }

        return true;
    }

    /**
     * Run classification synchronously (rule engine + AI stub + tax hints).
     */
    public static function run($record_type, $record_id, $org_id) {
        global $wpdb;
        $started_at = microtime(true);

        $record_type = sanitize_text_field($record_type);
        $record_id = (int) $record_id;
        $org_id = (int) $org_id;

        if (!isset(self::$record_types[$record_type])) {
            return new WP_Error('invalid_type', __('Unknown record type', 'orabooks'));
        }

        $record = self::get_record($record_type, $record_id, $org_id);
        if (!$record) {
            return new WP_Error('not_found', __('Record not found', 'orabooks'));
        }

        $map = self::$record_types[$record_type];
        $table = OraBooks_Database::table($map['table']);
        $text = self::extract_text($record, $map['text_columns']);
        $amount = self::resolve_amount($record, $map);

        $rule_result = self::match_rules($org_id, $record, $text);
        $use_rules = self::should_prioritize_rules($org_id);

        if ($use_rules && $rule_result) {
            $suggestion = $rule_result;
        } else {
            $suggestion = OraBooks_Ai_Providers::classify_record($record_type, $record, $text, $amount, $org_id);
            if ($rule_result && !$use_rules) {
                $suggestion['rule_match'] = $rule_result;
            }
        }

        if (empty($suggestion['account_code'])) {
            self::update_classification_fields($map, $record_id, $org_id, [
                'classification_status' => 'failed',
                'classification_reason' => 'Unable to generate classification suggestion',
                'last_classified_at'    => current_time('mysql', true),
            ], ['%s', '%s', '%s']);

            self::record_observability('classification', 'failed_count', 1, $org_id, [
                'record_type' => $record_type,
                'record_id' => $record_id,
            ]);

            return new WP_Error('classification_failed', __('Classification failed', 'orabooks'));
        }

        $jurisdiction = $suggestion['tax_jurisdiction'] ?? 'US';
        $tax_hints = self::build_tax_hints($org_id, $amount, $jurisdiction);

        $account = null;
        if (class_exists('OraBooks_COA')) {
            $account = OraBooks_COA::get_account_by_code($org_id, $suggestion['account_code']);
        }

        $risk_score = [
            'level' => $suggestion['confidence'] < self::CONFIDENCE_THRESHOLD ? 'medium' : 'low',
            'score' => max(0, 100 - (float) $suggestion['confidence']),
        ];

        self::update_classification_fields(
            $map,
            $record_id,
            $org_id,
            [
                'classification_status'          => 'processed',
                'suggested_account_code'         => $suggestion['account_code'],
                'suggested_account_id'           => $account ? (int) $account->id : null,
                'account_confidence'             => $suggestion['confidence'],
                'tax_hints'                      => wp_json_encode($tax_hints),
                'classification_risk_score'      => wp_json_encode($risk_score),
                'classification_model_version'   => $suggestion['model_version'] ?? OraBooks_Ai_Providers::model_version('classification'),
                'tax_engine_version'             => self::TAX_ENGINE_VERSION,
                'classification_reason'          => $suggestion['reason'],
                'last_classified_at'             => current_time('mysql', true),
            ],
            ['%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        $latency_ms = round((microtime(true) - $started_at) * 1000, 2);
        self::record_observability('classification', 'latency_ms', (float) $latency_ms, $org_id, [
            'record_type' => $record_type,
            'record_id' => $record_id,
            'source' => $suggestion['source'] ?? 'unknown',
        ]);
        self::record_observability('classification', 'confidence_score', (float) $suggestion['confidence'], $org_id, [
            'record_type' => $record_type,
            'record_id' => $record_id,
        ]);
        self::record_observability('classification', 'low_confidence_count', $suggestion['confidence'] < self::CONFIDENCE_THRESHOLD ? 1.0 : 0.0, $org_id, [
            'record_type' => $record_type,
            'record_id' => $record_id,
        ]);

        orabooks_log_event('classification_suggested', sprintf(
            'Classification suggested for %s #%d',
            $record_type,
            $record_id
        ), 'info', [
            'record_type'    => $record_type,
            'record_id'      => $record_id,
            'account_code'   => $suggestion['account_code'],
            'confidence'     => $suggestion['confidence'],
            'source'         => $suggestion['source'],
            'tax_hints'      => $tax_hints,
        ], 0, $org_id);

        if (function_exists('orabooks_publish_event')) {
            orabooks_publish_event('classification_completed', $record_id, [
                'record_type'  => $record_type,
                'record_id'    => $record_id,
                'org_id'       => $org_id,
                'account_code' => $suggestion['account_code'],
                'confidence'   => $suggestion['confidence'],
            ]);
        }

        if ($suggestion['confidence'] < self::CONFIDENCE_THRESHOLD && class_exists('OraBooks_Ai_Review')) {
            OraBooks_Ai_Review::enqueue($org_id, $record_type, $record_id, null, [
                'confidence'        => $suggestion['confidence'],
                'risk_level'        => $risk_score['level'],
                'model_version'     => $suggestion['model_version'] ?? OraBooks_Ai_Providers::model_version('classification'),
                'escalation_reason' => 'low_classification_confidence',
                'explanation'       => $suggestion['reason'],
            ], $amount);
        }

        return self::format_classification((object) array_merge((array) $record, [
            'classification_status'        => 'processed',
            'suggested_account_code'       => $suggestion['account_code'],
            'suggested_account_id'         => $account ? (int) $account->id : null,
            'account_confidence'           => $suggestion['confidence'],
            'tax_hints'                    => wp_json_encode($tax_hints),
            'classification_risk_score'    => wp_json_encode($risk_score),
            'classification_model_version' => $suggestion['model_version'] ?? OraBooks_Ai_Providers::model_version('classification'),
            'tax_engine_version'           => self::TAX_ENGINE_VERSION,
            'classification_reason'        => $suggestion['reason'],
            'last_classified_at'           => current_time('mysql', true),
        ]));
    }

    public static function preview($record_type, $record_id, $org_id) {
        $record_type = sanitize_text_field($record_type);
        $record_id = (int) $record_id;
        $org_id = (int) $org_id;

        if (!isset(self::$record_types[$record_type])) {
            return new WP_Error('invalid_type', __('Unknown record type', 'orabooks'));
        }

        $record = self::get_record($record_type, $record_id, $org_id);
        if (!$record) {
            return new WP_Error('not_found', __('Record not found', 'orabooks'));
        }

        $map = self::$record_types[$record_type];
        $text = self::extract_text($record, $map['text_columns']);
        $amount = self::resolve_amount($record, $map);

        $rule_result = self::match_rules($org_id, $record, $text);
        $use_rules = self::should_prioritize_rules($org_id);

        if ($use_rules && $rule_result) {
            $suggestion = $rule_result;
        } else {
            $suggestion = OraBooks_Ai_Providers::classify_record($record_type, $record, $text, $amount, $org_id);
            if ($rule_result && !$use_rules) {
                $suggestion['rule_match'] = $rule_result;
            }
        }

        if (empty($suggestion['account_code'])) {
            return new WP_Error('classification_failed', __('Classification failed', 'orabooks'));
        }

        $tax_hints = self::build_tax_hints($org_id, $amount, $suggestion['tax_jurisdiction'] ?? 'US');
        $risk_score = [
            'level' => $suggestion['confidence'] < self::CONFIDENCE_THRESHOLD ? 'medium' : 'low',
            'score' => max(0, 100 - (float) $suggestion['confidence']),
        ];

        return [
            'status'                 => 'preview',
            'suggested_account_code' => $suggestion['account_code'],
            'account_confidence'     => (float) $suggestion['confidence'],
            'tax_hints'              => $tax_hints,
            'risk_score'             => $risk_score,
            'model_version'          => $suggestion['model_version'] ?? OraBooks_Ai_Providers::model_version('classification'),
            'tax_engine_version'     => self::TAX_ENGINE_VERSION,
            'reason'                 => $suggestion['reason'] ?? null,
            'last_classified_at'     => null,
            'low_confidence'         => (float) $suggestion['confidence'] < self::CONFIDENCE_THRESHOLD,
        ];
    }

    public static function apply_suggestions($record_type, $record_id, $org_id, $user_id) {
        $record = self::get_record($record_type, $record_id, $org_id);
        if (!$record) {
            return new WP_Error('not_found', __('Record not found', 'orabooks'));
        }

        if ($record->classification_status !== 'processed') {
            return new WP_Error('not_ready', __('Classification is not ready to apply', 'orabooks'));
        }

        $tax_hints = self::decode_json_field($record->tax_hints);
        $updates = [];

        if ($record_type === 'expense') {
            if (!empty($record->suggested_account_code)) {
                $updates['category'] = self::account_code_to_category($record->suggested_account_code);
            }
            if (!empty($tax_hints['tax_rate'])) {
                $updates['tax_rate'] = (float) $tax_hints['tax_rate'];
                if ($record->total_amount) {
                    $total = (float) $record->total_amount;
                    $rate = (float) $tax_hints['tax_rate'];
                    $tax_amount = round($total * ($rate / 100) / (1 + ($rate / 100)), 2);
                    $updates['tax_amount'] = $tax_amount;
                }
            }
        }

        if ($record_type === 'journal_line') {
            if (empty($record->suggested_account_code)) {
                return new WP_Error('not_ready', __('No suggested account available', 'orabooks'));
            }

            if (class_exists('OraBooks_Posting')) {
                $journal = OraBooks_Posting::get_journal((int) ($record->journal_id ?? 0), (int) $org_id);
                if ($journal && $journal->status !== 'draft') {
                    return new WP_Error('invalid_status', __('Only draft journal lines can be updated', 'orabooks'));
                }
            }

            global $wpdb;
            $line_table = OraBooks_Database::table('journal_lines');
            $account = class_exists('OraBooks_COA')
                ? OraBooks_COA::get_account_by_code((int) $org_id, (string) $record->suggested_account_code)
                : null;
            if (!$account) {
                return new WP_Error('invalid_account', __('Suggested account not found', 'orabooks'));
            }

            $wpdb->update(
                $line_table,
                [
                    'account_code' => (string) $record->suggested_account_code,
                    'account_id'   => (int) $account->id,
                ],
                ['id' => (int) $record_id],
                ['%s', '%d'],
                ['%d']
            );

            $updates['account_code'] = (string) $record->suggested_account_code;
        }

        if ($record_type === 'invoice' && !empty($tax_hints['tax_rate'])) {
            global $wpdb;
            $table = OraBooks_Database::table('invoices');
            $tax_base = max(0, (float) $record->total_amount - (float) ($record->tax_amount ?? 0));
            $rate = (float) $tax_hints['tax_rate'];
            $tax_amount = round($tax_base * ($rate / 100), 2);
            $wpdb->update(
                $table,
                [
                    'tax_rate'   => $rate,
                    'tax_amount' => $tax_amount,
                    'total_amount' => round($tax_base + $tax_amount, 2),
                ],
                ['id' => (int) $record_id, 'org_id' => (int) $org_id],
                ['%f', '%f', '%f'],
                ['%d', '%d']
            );
        }

        if (!empty($updates)) {
            global $wpdb;
            $map = self::$record_types[$record_type];
            $where = self::uses_org_join($map)
                ? ['id' => (int) $record_id]
                : ['id' => (int) $record_id, 'org_id' => (int) $org_id];
            $where_formats = self::uses_org_join($map) ? ['%d'] : ['%d', '%d'];

            $wpdb->update(
                OraBooks_Database::table($map['table']),
                $updates,
                $where,
                array_fill(0, count($updates), '%s'),
                $where_formats
            );
        }

        orabooks_log_event('classification_applied', sprintf('AI suggestions applied to %s #%d', $record_type, $record_id), 'info', [
            'record_type' => $record_type,
            'record_id'   => $record_id,
            'updates'     => $updates,
        ], (int) $user_id, (int) $org_id);

        return self::get_record($record_type, $record_id, $org_id);
    }

    public static function override($record_type, $record_id, $org_id, $user_id, $account_code, $tax_rate = null) {
        global $wpdb;

        $record = self::get_record($record_type, $record_id, $org_id);
        if (!$record) {
            return new WP_Error('not_found', __('Record not found', 'orabooks'));
        }

        $account_code = sanitize_text_field($account_code);
        $account = class_exists('OraBooks_COA') ? OraBooks_COA::get_account_by_code($org_id, $account_code) : null;

        $map = self::$record_types[$record_type];
        $table = OraBooks_Database::table($map['table']);

        self::update_classification_fields(
            $map,
            $record_id,
            $org_id,
            [
                'classification_status'  => 'overridden',
                'suggested_account_code' => $account_code,
                'suggested_account_id'   => $account ? (int) $account->id : null,
            ],
            ['%s', '%s', '%d']
        );

        if ($record_type === 'journal_line' && $account) {
            if (class_exists('OraBooks_Posting')) {
                $journal = OraBooks_Posting::get_journal((int) ($record->journal_id ?? 0), (int) $org_id);
                if ($journal && $journal->status !== 'draft') {
                    return new WP_Error('invalid_status', __('Only draft journal lines can be updated', 'orabooks'));
                }
            }

            $wpdb->update(
                $table,
                [
                    'account_code' => $account_code,
                    'account_id'   => (int) $account->id,
                ],
                ['id' => (int) $record_id],
                ['%s', '%d'],
                ['%d']
            );
        }

        if ($tax_rate !== null && ($record_type === 'expense' || $record_type === 'invoice')) {
            $wpdb->update(
                $table,
                ['tax_rate' => (float) $tax_rate],
                ['id' => (int) $record_id, 'org_id' => (int) $org_id],
                ['%f'],
                ['%d', '%d']
            );
        }

        orabooks_log_event('classification_override', sprintf('User overrode classification on %s #%d', $record_type, $record_id), 'info', [
            'record_type'  => $record_type,
            'record_id'    => $record_id,
            'account_code' => $account_code,
            'tax_rate'     => $tax_rate,
        ], (int) $user_id, (int) $org_id);

        self::record_observability('classification', 'override_count', 1, (int) $org_id, [
            'record_type' => $record_type,
            'record_id' => $record_id,
        ]);

        return self::get_record($record_type, $record_id, $org_id);
    }

    private static function should_prioritize_rules($org_id) {
        $org_id = (int) $org_id;

        if (class_exists('OraBooks_Organization') && $org_id > 0) {
            $org = OraBooks_Organization::get($org_id);
            if ($org && !empty($org->config)) {
                $config = json_decode((string) $org->config, true);
                if (is_array($config) && array_key_exists('rule_precedence_over_ai', $config)) {
                    return !empty($config['rule_precedence_over_ai']);
                }
            }
        }

        return (bool) get_option('orabooks_rule_precedence_over_ai', 1);
    }

    private static function record_observability($component, $metric_name, $value, $org_id = null, array $metadata = []) {
        if (!class_exists('OraBooks_Observability')) {
            return;
        }

        OraBooks_Observability::record_metric((string) $component, (string) $metric_name, (float) $value, $org_id ? (int) $org_id : null, $metadata);
    }

    public static function format_classification($row) {
        if (!$row) {
            return null;
        }

        $tax_hints = self::decode_json_field($row->tax_hints ?? null);
        $risk = self::decode_json_field($row->classification_risk_score ?? null);

        return [
            'status'               => $row->classification_status ?? 'pending',
            'suggested_account_code' => $row->suggested_account_code ?? null,
            'suggested_account_id'   => !empty($row->suggested_account_id) ? (int) $row->suggested_account_id : null,
            'account_confidence'     => isset($row->account_confidence) ? (float) $row->account_confidence : null,
            'tax_hints'              => $tax_hints,
            'risk_score'             => $risk,
            'model_version'          => $row->classification_model_version ?? null,
            'tax_engine_version'     => $row->tax_engine_version ?? null,
            'reason'                 => $row->classification_reason ?? null,
            'last_classified_at'     => $row->last_classified_at ?? null,
            'low_confidence'         => isset($row->account_confidence) && (float) $row->account_confidence < self::CONFIDENCE_THRESHOLD,
        ];
    }

    public function ajax_run() {
        $user_id = orabooks_get_current_user_id();
        $org_id = orabooks_get_current_org_id($user_id);

        if (!$user_id || !$org_id) {
            orabooks_json_error('Authentication required', 401);
        }

        if (!self::can_view($user_id, $org_id)) {
            orabooks_json_error('Permission denied', 403);
        }

        $record_type = sanitize_text_field($_REQUEST['record_type'] ?? '');
        $record_id = (int) ($_REQUEST['record_id'] ?? 0);

        if (!empty($_REQUEST['async'])) {
            $result = self::request($record_type, $record_id, $org_id);
        } else {
            $result = self::run($record_type, $record_id, $org_id);
        }

        if (is_wp_error($result)) {
            $status = (int) ($result->get_error_data()['status'] ?? 400);
            orabooks_json_error($result->get_error_message(), $status);
        }

        orabooks_json_success(['classification' => is_array($result) ? $result : self::format_classification($result)]);
    }

    public function ajax_apply() {
        $user_id = orabooks_get_current_user_id();
        $org_id = orabooks_get_current_org_id($user_id);

        if (!$user_id || !$org_id) {
            orabooks_json_error('Authentication required', 401);
        }

        if (!self::can_manage($user_id, $org_id)) {
            orabooks_json_error('Permission denied', 403);
        }

        $record_type = sanitize_text_field($_POST['record_type'] ?? '');
        $record_id = (int) ($_POST['record_id'] ?? 0);
        $result = self::apply_suggestions($record_type, $record_id, $org_id, $user_id);

        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success(['classification' => self::format_classification($result)]);
    }

    public function ajax_override() {
        $user_id = orabooks_get_current_user_id();
        $org_id = orabooks_get_current_org_id($user_id);

        if (!$user_id || !$org_id) {
            orabooks_json_error('Authentication required', 401);
        }

        if (!self::can_manage($user_id, $org_id)) {
            orabooks_json_error('Permission denied', 403);
        }

        $record_type = sanitize_text_field($_POST['record_type'] ?? '');
        $record_id = (int) ($_POST['record_id'] ?? 0);
        $account_code = sanitize_text_field($_POST['account_code'] ?? '');
        $tax_rate = isset($_POST['tax_rate']) ? (float) $_POST['tax_rate'] : null;

        $result = self::override($record_type, $record_id, $org_id, $user_id, $account_code, $tax_rate);

        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success(['classification' => self::format_classification($result)]);
    }

    private static function can_view($user_id, $org_id) {
        return OraBooks_RBAC::require_permission($user_id, $org_id, 'view_classification')
            || OraBooks_RBAC::require_permission($user_id, $org_id, 'view_expenses')
            || OraBooks_RBAC::require_permission($user_id, $org_id, 'view_invoices');
    }

    private static function can_manage($user_id, $org_id) {
        return OraBooks_RBAC::require_permission($user_id, $org_id, 'override_classification')
            || OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_expenses')
            || OraBooks_RBAC::require_permission($user_id, $org_id, 'create_invoice');
    }

    private static function get_record($record_type, $record_id, $org_id) {
        global $wpdb;

        $map = self::$record_types[$record_type] ?? null;
        if (!$map) {
            return null;
        }

        $table = OraBooks_Database::table($map['table']);

        if (self::uses_org_join($map)) {
            $join_table = OraBooks_Database::table($map['org_join']['table']);
            $line_fk = $map['org_join']['line_fk'];
            $join_org_column = $map['org_join']['org_column'];

            return $wpdb->get_row($wpdb->prepare(
                "SELECT t.*, j.{$join_org_column} AS org_id
                   FROM {$table} t
                   INNER JOIN {$join_table} j ON j.id = t.{$line_fk}
                  WHERE t.id = %d AND j.{$join_org_column} = %d",
                (int) $record_id,
                (int) $org_id
            ));
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND {$map['org_column']} = %d",
            (int) $record_id,
            (int) $org_id
        ));
    }

    private static function extract_text($record, $columns) {
        $parts = [];
        foreach ($columns as $column) {
            if (!empty($record->{$column})) {
                $parts[] = (string) $record->{$column};
            }
        }
        return strtolower(implode(' ', $parts));
    }

    private static function match_rules($org_id, $record, $text) {
        global $wpdb;

        $table = OraBooks_Database::table('classification_rules');
        $rules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d AND is_active = 1 ORDER BY priority DESC, id ASC",
            (int) $org_id
        ));

        foreach ($rules ?: [] as $rule) {
            $match = strtolower($rule->match_value);
            $haystack = $text;

            if ($rule->rule_type === 'vendor' && !empty($record->vendor)) {
                $haystack = strtolower((string) $record->vendor);
            } elseif ($rule->rule_type === 'category' && !empty($record->category)) {
                $haystack = strtolower((string) $record->category);
            }

            if (strpos($haystack, $match) !== false) {
                return [
                    'account_code'      => $rule->account_code,
                    'confidence'      => 95.0,
                    'source'            => 'rule',
                    'reason'            => sprintf("Matched %s rule '%s'", $rule->rule_type, $rule->match_value),
                    'tax_jurisdiction'  => $rule->tax_jurisdiction ?: 'US',
                ];
            }
        }

        return null;
    }

    public static function run_classification_stub($record_type, $record, $text, $amount) {
        $defaults = [
            '5100' => ['Office Supplies', 88.0],
            '5200' => ['Meals & Entertainment', 82.0],
            '5300' => ['Travel', 80.0],
            '5400' => ['Utilities', 78.0],
            '5500' => ['Software', 86.0],
            '5000' => ['Salary', 90.0],
            '4000' => ['Sales Revenue', 75.0],
        ];

        $account_code = '5100';
        $confidence = 72.0;
        $reason = 'Default office expense classification';

        if ($record_type === 'invoice') {
            $account_code = '4000';
            $confidence = 84.0;
            $reason = 'Default revenue account for invoice';
        }

        foreach ($defaults as $code => $meta) {
            if (strpos($text, strtolower($meta[0])) !== false || strpos($text, strtolower(str_replace(' & ', ' ', $meta[0]))) !== false) {
                $account_code = $code;
                $confidence = $meta[1];
                $reason = "AI matched keyword for {$meta[0]}";
                break;
            }
        }

        if (strpos($text, 'unknown') !== false || trim($text) === '') {
            $confidence = 55.0;
            $reason = 'Insufficient description — low confidence';
        }

        if (!empty($record->category)) {
            $category_map = [
                'meals'           => '5200',
                'travel'          => '5300',
                'office supplies' => '5100',
                'software'        => '5500',
                'utilities'       => '5400',
                'salary'          => '5000',
                'payroll'         => '5000',
            ];
            $cat = strtolower((string) $record->category);
            foreach ($category_map as $needle => $code) {
                if (strpos($cat, $needle) !== false) {
                    $account_code = $code;
                    $confidence = max($confidence, 90.0);
                    $reason = "AI mapped category '{$record->category}'";
                    break;
                }
            }
        }

        return [
            'account_code'     => $account_code,
            'confidence'       => $confidence,
            'source'           => 'ai_stub',
            'reason'           => $reason,
            'tax_jurisdiction' => 'US',
            'model_version'    => OraBooks_Ai_Providers::model_version('classification'),
        ];
    }

    private static function build_tax_hints($org_id, $amount, $jurisdiction) {
        if (!class_exists('OraBooks_Tax') || $amount <= 0) {
            return [
                'tax_rate'   => 0,
                'tax_type'   => 'None',
                'confidence' => 50,
                'source'     => 'fallback',
            ];
        }

        $calc = OraBooks_Tax::calculate([
            'org_id'       => $org_id,
            'amount'       => $amount,
            'jurisdiction' => $jurisdiction,
        ]);

        if (is_wp_error($calc)) {
            return [
                'tax_rate'   => 0,
                'tax_type'   => 'None',
                'confidence' => 40,
                'source'     => 'error',
            ];
        }

        return [
            'tax_rate'   => (float) ($calc['tax_rate'] ?? 0),
            'tax_type'   => $calc['tax_type'] ?? 'Sales Tax',
            'tax_amount' => (float) ($calc['tax_amount'] ?? 0),
            'confidence' => 90,
            'source'     => 'sl-305',
            'rule_id'    => $calc['rule_id'] ?? null,
        ];
    }

    private static function build_idempotency_key($record_type, $record_id, $record) {
        $map = self::$record_types[$record_type];
        $parts = [$record_type, $record_id];
        foreach ($map['text_columns'] as $column) {
            $parts[] = (string) ($record->{$column} ?? '');
        }
        $parts[] = (string) self::resolve_amount($record, $map);
        return substr(hash('sha256', implode('|', $parts)), 0, 64);
    }

    private static function uses_org_join($map) {
        return !empty($map['org_join']) && is_array($map['org_join']);
    }

    private static function resolve_amount($record, $map) {
        if (!empty($map['amount_column'])) {
            return (float) ($record->{$map['amount_column']} ?? 0);
        }

        if (!empty($map['amount_columns']) && is_array($map['amount_columns'])) {
            $total = 0.0;
            foreach ($map['amount_columns'] as $column) {
                $total += (float) ($record->{$column} ?? 0);
            }
            return $total;
        }

        return 0.0;
    }

    private static function update_classification_fields($map, $record_id, $org_id, $data, $data_formats) {
        global $wpdb;

        $table = OraBooks_Database::table($map['table']);
        if (self::uses_org_join($map)) {
            return $wpdb->update(
                $table,
                $data,
                ['id' => (int) $record_id],
                $data_formats,
                ['%d']
            );
        }

        return $wpdb->update(
            $table,
            $data,
            ['id' => (int) $record_id, 'org_id' => (int) $org_id],
            $data_formats,
            ['%d', '%d']
        );
    }

    private static function mark_failed($record_type, $record_id, $org_id, $reason) {
        if (!isset(self::$record_types[$record_type])) {
            return;
        }

        $map = self::$record_types[$record_type];
        self::update_classification_fields(
            $map,
            (int) $record_id,
            (int) $org_id,
            [
                'classification_status' => 'failed',
                'classification_reason' => sanitize_text_field($reason),
                'last_classified_at'    => current_time('mysql', true),
            ],
            ['%s', '%s', '%s']
        );
    }

    private static function account_code_to_category($code) {
        $map = [
            '5100' => 'Office Supplies',
            '5200' => 'Meals',
            '5300' => 'Travel',
            '5400' => 'Utilities',
            '5500' => 'Software',
        ];
        return $map[$code] ?? 'General Expense';
    }

    private static function decode_json_field($value) {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return [];
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
