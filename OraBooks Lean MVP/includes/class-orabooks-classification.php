<?php
/**
 * OraBooks Smart Classification & Tax Hints
 *
 * Rule-first + AI-stub account mapping and tax hints for expenses/invoices.
 * Suggestions only — never posts or approves. Async via, events via.
 */

if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Classification {

 const CONFIDENCE_THRESHOLD = 70.0;
 const MODEL_VERSION = 'mvp-stub-1.0';
 const TAX_ENGINE_VERSION = '-1.0';
 const RATE_LIMIT_MAX = 10;
 const RATE_LIMIT_PERIOD = 60;

 private static $instance = null;

 private static $record_types = [
 'expense' => [
 'table' => 'expenses',
 'org_column' => 'org_id',
 'amount_column' => 'total_amount',
 'text_columns' => ['vendor', 'category', 'description'],
 ],
 'invoice' => [
 'table' => 'invoices',
 'org_column' => 'org_id',
 'amount_column' => 'total_amount',
 'text_columns' => ['description'],
 ],
 'journal_line' => [
 'table' => 'journal_lines',
 'org_column' => null,
 'parent_table' => 'journals',
 'parent_fk' => 'journal_id',
 'amount_column' => 'debit_amount',
 'text_columns' => ['description', 'account_code'],
 ],
 ];

 private static $default_rules = [
 ['rule_type' => 'keyword', 'match_value' => 'office', 'account_code' => '5100', 'priority' => 10],
 ['rule_type' => 'keyword', 'match_value' => 'meal', 'account_code' => '5200', 'priority' => 10],
 ['rule_type' => 'keyword', 'match_value' => 'travel', 'account_code' => '5300', 'priority' => 10],
 ['rule_type' => 'keyword', 'match_value' => 'software', 'account_code' => '5500', 'priority' => 10],
 ['rule_type' => 'vendor', 'match_value' => 'amazon', 'account_code' => '5100', 'priority' => 20],
 ['rule_type' => 'vendor', 'match_value' => 'uber', 'account_code' => '5300', 'priority' => 20],
 ];

 public static function init {
 if (self::$instance === null) {
 self::$instance = new self;

 add_action('wp_ajax_orabooks_classification_run', [self::$instance, 'ajax_run']);
 add_action('wp_ajax_nopriv_orabooks_classification_run', [self::$instance, 'ajax_run']);
 add_action('wp_ajax_orabooks_classification_apply', [self::$instance, 'ajax_apply']);
 add_action('wp_ajax_nopriv_orabooks_classification_apply', [self::$instance, 'ajax_apply']);
 add_action('wp_ajax_orabooks_classification_override', [self::$instance, 'ajax_override']);
 add_action('wp_ajax_nopriv_orabooks_classification_override', [self::$instance, 'ajax_override']);
 add_action('wp_ajax_orabooks_classification_status', [self::$instance, 'ajax_status']);
 add_action('wp_ajax_nopriv_orabooks_classification_status', [self::$instance, 'ajax_status']);
 add_action('wp_ajax_orabooks_classification_rules_list', [self::$instance, 'ajax_rules_list']);
 add_action('wp_ajax_nopriv_orabooks_classification_rules_list', [self::$instance, 'ajax_rules_list']);
 add_action('wp_ajax_orabooks_classification_rules_save', [self::$instance, 'ajax_rules_save']);
 add_action('wp_ajax_nopriv_orabooks_classification_rules_save', [self::$instance, 'ajax_rules_save']);
 add_action('wp_ajax_orabooks_classification_rules_delete', [self::$instance, 'ajax_rules_delete']);
 add_action('wp_ajax_nopriv_orabooks_classification_rules_delete', [self::$instance, 'ajax_rules_delete']);
 add_action('wp_ajax_orabooks_classification_live_check', [self::$instance, 'ajax_live_check']);
 add_action('wp_ajax_nopriv_orabooks_classification_live_check', [self::$instance, 'ajax_live_check']);

 if (class_exists('OraBooks_AsyncQueue')) {
 OraBooks_AsyncQueue::register_handler('classify_transaction', [self::class, 'handle_async_job']);
 }
 }

 return self::$instance;
 }

 public static function register_event_consumer {
 if (!class_exists('OraBooks_EventBus')) {
 return;
 }

 OraBooks_EventBus::register_consumer('classification_requested', function ($event, $payload) {
 if (!class_exists('OraBooks_AsyncQueue')) {
 return;
 }

 OraBooks_AsyncQueue::enqueue('classify_transaction', [
 'record_type' => $payload['record_type'] ?? '',
 'record_id' => (int) ($payload['record_id'] ?? 0),
 'org_id' => (int) ($payload['org_id'] ?? 0),
 'idempotency_key' => $payload['idempotency_key'] ?? '',
 ], ['priority' => 4]);
 });
 }

 public static function get_create_table_sql {
 global $wpdb;
 $charset = $wpdb->get_charset_collate;
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
 public static function ensure_schema {
 global $wpdb;

 $columns_sql = "
 classification_status ENUM('pending','processed','overridden','failed') NOT NULL DEFAULT 'pending',
 suggested_account_code VARCHAR(20) DEFAULT NULL,
 suggested_account_id BIGINT UNSIGNED DEFAULT NULL,
 account_confidence DECIMAL(5,2) DEFAULT NULL,
 tax_hints JSON DEFAULT NULL,
 classification_risk_score JSON DEFAULT NULL,
 classification_model_version VARCHAR(20) DEFAULT NULL,
 tax_engine_version VARCHAR(20) DEFAULT NULL,
 classification_idempotency_key VARCHAR(128) DEFAULT NULL,
 classification_reason TEXT DEFAULT NULL,
 last_classified_at TIMESTAMP NULL DEFAULT NULL
 ";

 foreach (['expenses', 'invoices', 'journal_lines'] as $base_table) {
 $table = OraBooks_Database::table($base_table);
 $existing = $wpdb->get_results("SHOW COLUMNS FROM {$table}");
 $fields = array_map(function ($col) {
 return $col->Field;
 }, $existing ?: []);

 if (!in_array('classification_status', $fields, true)) {
 $wpdb->query("ALTER TABLE {$table} ADD COLUMN classification_status ENUM('pending','processed','overridden','failed') NOT NULL DEFAULT 'pending' AFTER updated_at");
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
 $index_names = array_map(function ($idx) {
 return $idx->Key_name;
 }, $indexes ?: []);

 if ($base_table !== 'journal_lines') {
 if (in_array('idx_org_classification_idempotency', $index_names, true)
 && !in_array('uniq_org_classification_idempotency', $index_names, true)) {
 $wpdb->query("ALTER TABLE {$table} DROP INDEX idx_org_classification_idempotency");
 }
 if (!in_array('uniq_org_classification_idempotency', $index_names, true)) {
 $wpdb->query("ALTER TABLE {$table} ADD UNIQUE INDEX uniq_org_classification_idempotency (org_id, classification_idempotency_key)");
 }
 } elseif (!in_array('idx_journal_line_classification_idempotency', $index_names, true)) {
 $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_journal_line_classification_idempotency (journal_id, classification_idempotency_key)");
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
 'org_id' => $org_id,
 'rule_type' => $rule['rule_type'],
 'match_value' => $rule['match_value'],
 'account_code' => $rule['account_code'],
 'priority' => (int) $rule['priority'],
 'is_active' => 1,
 ], ['%d', '%s', '%s', '%s', '%d', '%d']);
 }
 }

 /**
 * Debounced classification request ( cooldown).
 */
 public static function maybe_request($record_type, $record_id, $org_id, $context = []) {
 $record_type = sanitize_text_field($record_type);
 $record_id = (int) $record_id;
 $org_id = (int) $org_id;

 if ($record_id <= 0 || $org_id <= 0) {
 return new WP_Error('invalid_request', __('Invalid classification request.', 'orabooks'));
 }

 $debounce_key = 'orabooks_cls_debounce_'. md5($record_type. '|'. $record_id. '|'. $org_id);
 if (get_transient($debounce_key)) {
 return [
 'record_type' => $record_type,
 'record_id' => $record_id,
 'status' => 'pending',
 'debounced' => true,
 ];
 }

 set_transient($debounce_key, 1, 1);

 return self::request($record_type, $record_id, $org_id, $context);
 }

 public static function rule_precedence_over_ai_enabled {
 if (get_option('orabooks_rule_precedence_over_ai', null) !== null) {
 return (bool) get_option('orabooks_rule_precedence_over_ai', 1);
 }

 return (bool) get_option('orabooks_rule_precedes_over_ai', 1);
 }

 public static function set_rule_precedence_over_ai($enabled) {
 update_option('orabooks_rule_precedence_over_ai', $enabled ? 1: 0, false);
 update_option('orabooks_rule_precedes_over_ai', $enabled ? 1: 0, false);
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

 if (!empty($record->classification_idempotency_key)
 && (string) $record->classification_idempotency_key === (string) $idempotency_key
 && in_array($record->classification_status ?? '', ['pending', 'processed'], true)) {
 return [
 'record_type' => $record_type,
 'record_id' => $record_id,
 'status' => $record->classification_status,
 'idempotency_key' => $idempotency_key,
 'classification' => self::format_classification($record),
 ];
 }

 $duplicate = null;
 if ($map['org_column']) {
 $duplicate = $wpdb->get_var($wpdb->prepare(
 "SELECT id FROM {$table}
 WHERE org_id = %d AND classification_idempotency_key = %s
 AND id != %d AND classification_status IN ('pending','processed')",
 $org_id,
 $idempotency_key,
 $record_id
 ));
 } else {
 $duplicate = $wpdb->get_var($wpdb->prepare(
 "SELECT id FROM {$table}
 WHERE classification_idempotency_key = %s
 AND id != %d AND classification_status IN ('pending','processed')",
 $idempotency_key,
 $record_id
 ));
 }

 if ($duplicate) {
 return new WP_Error('duplicate', __('Classification already requested for this content hash', 'orabooks'), ['status' => 409]);
 }

 $pending_update = [
 'classification_status' => 'pending',
 'classification_idempotency_key' => $idempotency_key,
 ];

 if ($map['org_column']) {
 $wpdb->update(
 $table,
 $pending_update,
 ['id' => $record_id, $map['org_column'] => $org_id],
 ['%s', '%s'],
 ['%d', '%d']
 );
 } else {
 $wpdb->update(
 $table,
 $pending_update,
 ['id' => $record_id],
 ['%s', '%s'],
 ['%d']
 );
 }

 $payload = [
 'record_type' => $record_type,
 'record_id' => $record_id,
 'org_id' => $org_id,
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
 'record_type' => $record_type,
 'record_id' => $record_id,
 'status' => 'pending',
 'idempotency_key' => $idempotency_key,
 ];
 }

 public static function handle_async_job($job, $payload) {
 $record_type = sanitize_text_field($payload['record_type'] ?? '');
 $record_id = (int) ($payload['record_id'] ?? 0);
 $org_id = (int) ($payload['org_id'] ?? 0);

 $result = self::run($record_type, $record_id, $org_id);

 if (is_wp_error($result)) {
 self::mark_failed($record_type, $record_id, $org_id, $result->get_error_message);
 return $result->get_error_message;
 }

 return true;
 }

 /**
 * On-demand classification without persisting (REST dry-run).
 */
 public static function dry_run($record_type, $record_id, $org_id) {
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
 $amount = (float) ($record->{$map['amount_column']} ?? 0);
 if ($record_type === 'journal_line') {
 $amount = max((float) ($record->debit_amount ?? 0), (float) ($record->credit_amount ?? 0));
 }

 self::seed_default_rules($org_id);
 $rule_result = self::match_rules($org_id, $record, $text);
 $use_rules = self::rule_precedence_over_ai_enabled;
 $suggestion = ($use_rules && $rule_result)
 ? $rule_result
: OraBooks_Ai_Providers::classify_record($record_type, $record, $text, $amount, $org_id);

 $tax_hints = self::build_tax_hints($org_id, $amount, $suggestion['tax_jurisdiction'] ?? 'US');

 return [
 'suggested_account_code' => $suggestion['account_code'],
 'account_confidence' => $suggestion['confidence'],
 'tax_hints' => $tax_hints,
 'reason' => self::encode_reason($suggestion),
 'source' => $suggestion['source'] ?? 'ai',
 'model_version' => $suggestion['model_version'] ?? OraBooks_Ai_Providers::model_version('classification'),
 'low_confidence' => (float) $suggestion['confidence'] < self::CONFIDENCE_THRESHOLD,
 ];
 }

 /**
 * Run classification synchronously (rule engine + AI stub + tax hints).
 */
 public static function run($record_type, $record_id, $org_id) {
 global $wpdb;

 $started = microtime(true);
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
 $amount = (float) ($record->{$map['amount_column']} ?? 0);
 if ($record_type === 'journal_line') {
 $amount = max((float) ($record->debit_amount ?? 0), (float) ($record->credit_amount ?? 0));
 }

 $rule_result = self::match_rules($org_id, $record, $text);
 $use_rules = self::rule_precedence_over_ai_enabled;

 if ($use_rules && $rule_result) {
 $suggestion = $rule_result;
 } else {
 $suggestion = OraBooks_Ai_Providers::classify_record($record_type, $record, $text, $amount, $org_id);
 if ($rule_result && !$use_rules) {
 $suggestion['rule_match'] = $rule_result;
 }
 }

 $jurisdiction = $suggestion['tax_jurisdiction'] ?? 'US';
 $tax_hints = self::build_tax_hints($org_id, $amount, $jurisdiction);

 $account = null;
 if (class_exists('OraBooks_COA')) {
 $account = OraBooks_COA::get_account_by_code($org_id, $suggestion['account_code']);
 if (!$account) {
 orabooks_log_event('classification_invalid_account', 'Suggested account missing from COA', 'warning', [
 'record_type' => $record_type,
 'record_id' => $record_id,
 'account_code' => $suggestion['account_code'],
 ], 0, $org_id);
 $suggestion['confidence'] = min((float) $suggestion['confidence'], 55.0);
 }
 }

 $risk_score = [
 'level' => $suggestion['confidence'] < self::CONFIDENCE_THRESHOLD ? 'medium': 'low',
 'score' => max(0, 100 - (float) $suggestion['confidence']),
 ];

 $reason_payload = self::encode_reason($suggestion);
 $update_data = [
 'classification_status' => 'processed',
 'suggested_account_code' => $suggestion['account_code'],
 'suggested_account_id' => $account ? (int) $account->id: null,
 'account_confidence' => $suggestion['confidence'],
 'tax_hints' => wp_json_encode($tax_hints),
 'classification_risk_score' => wp_json_encode($risk_score),
 'classification_model_version' => $suggestion['model_version'] ?? OraBooks_Ai_Providers::model_version('classification'),
 'tax_engine_version' => self::TAX_ENGINE_VERSION,
 'classification_reason' => $reason_payload,
 'last_classified_at' => current_time('mysql', true),
 ];

 if ($map['org_column']) {
 $wpdb->update(
 $table,
 $update_data,
 ['id' => $record_id, $map['org_column'] => $org_id],
 ['%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s'],
 ['%d', '%d']
 );
 } else {
 $wpdb->update(
 $table,
 $update_data,
 ['id' => $record_id],
 ['%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s'],
 ['%d']
 );
 }

 if (class_exists('OraBooks_Observability') && method_exists('OraBooks_Observability', 'record_metric')) {
 OraBooks_Observability::record_metric(
 'classification',
 'latency_ms',
 round((microtime(true) - $started) * 1000, 2),
 ['record_type' => $record_type, 'org_id' => $org_id]
 );
 OraBooks_Observability::record_metric(
 'classification',
 'confidence_score',
 (float) $suggestion['confidence'],
 ['record_type' => $record_type, 'org_id' => $org_id]
 );
 }

 orabooks_log_event('classification_suggested', sprintf(
 'Classification suggested for %s #%d',
 $record_type,
 $record_id
 ), 'info', [
 'record_type' => $record_type,
 'record_id' => $record_id,
 'account_code' => $suggestion['account_code'],
 'confidence' => $suggestion['confidence'],
 'source' => $suggestion['source'],
 'tax_hints' => $tax_hints,
 ], 0, $org_id);

 if (function_exists('orabooks_publish_event')) {
 orabooks_publish_event('classification_completed', $record_id, [
 'record_type' => $record_type,
 'record_id' => $record_id,
 'org_id' => $org_id,
 'account_code' => $suggestion['account_code'],
 'confidence' => $suggestion['confidence'],
 ]);
 }

 if ($suggestion['confidence'] < self::CONFIDENCE_THRESHOLD && class_exists('OraBooks_Ai_Review')) {
 OraBooks_Ai_Review::enqueue($org_id, $record_type, $record_id, null, [
 'confidence' => $suggestion['confidence'],
 'risk_level' => $risk_score['level'],
 'model_version' => $suggestion['model_version'] ?? OraBooks_Ai_Providers::model_version('classification'),
 'escalation_reason' => 'low_classification_confidence',
 'explanation' => $suggestion['reason'],
 ], $amount);
 }

 return self::format_classification((object) array_merge((array) $record, [
 'classification_status' => 'processed',
 'suggested_account_code' => $suggestion['account_code'],
 'suggested_account_id' => $account ? (int) $account->id: null,
 'account_confidence' => $suggestion['confidence'],
 'tax_hints' => wp_json_encode($tax_hints),
 'classification_risk_score' => wp_json_encode($risk_score),
 'classification_model_version' => $suggestion['model_version'] ?? OraBooks_Ai_Providers::model_version('classification'),
 'tax_engine_version' => self::TAX_ENGINE_VERSION,
 'classification_reason' => $reason_payload,
 'last_classified_at' => current_time('mysql', true),
 ]));
 }

 public static function get_status($record_type, $record_id, $org_id) {
 $record = self::get_record($record_type, $record_id, $org_id);
 if (!$record) {
 return new WP_Error('not_found', __('Record not found', 'orabooks'));
 }

 return self::format_classification($record);
 }

 public static function list_rules($org_id) {
 global $wpdb;

 $table = OraBooks_Database::table('classification_rules');
 return $wpdb->get_results($wpdb->prepare(
 "SELECT * FROM {$table} WHERE org_id = %d ORDER BY priority DESC, id ASC",
 (int) $org_id
 )) ?: [];
 }

 public static function save_rule($org_id, $data, $user_id) {
 global $wpdb;

 $org_id = (int) $org_id;
 $table = OraBooks_Database::table('classification_rules');
 $rule_id = (int) ($data['id'] ?? 0);

 $payload = [
 'org_id' => $org_id,
 'rule_type' => sanitize_text_field($data['rule_type'] ?? 'keyword'),
 'match_value' => sanitize_text_field($data['match_value'] ?? ''),
 'account_code' => sanitize_text_field($data['account_code'] ?? ''),
 'tax_jurisdiction' => !empty($data['tax_jurisdiction']) ? sanitize_text_field($data['tax_jurisdiction']): null,
 'priority' => (int) ($data['priority'] ?? 10),
 'is_active' => !empty($data['is_active']) ? 1: 0,
 ];

 if ($payload['match_value'] === '' || $payload['account_code'] === '') {
 return new WP_Error('validation', __('Match value and account code are required.', 'orabooks'));
 }

 if (!in_array($payload['rule_type'], ['vendor', 'keyword', 'category'], true)) {
 return new WP_Error('validation', __('Invalid rule type.', 'orabooks'));
 }

 if ($rule_id > 0) {
 $wpdb->update($table, $payload, ['id' => $rule_id, 'org_id' => $org_id]);
 orabooks_log_event('classification_rule_updated', 'Classification rule updated', 'info', [
 'rule_id' => $rule_id,
 ], (int) $user_id, $org_id);
 return $rule_id;
 }

 $wpdb->insert($table, $payload);
 $rule_id = (int) $wpdb->insert_id;
 orabooks_log_event('classification_rule_created', 'Classification rule created', 'info', [
 'rule_id' => $rule_id,
 ], (int) $user_id, $org_id);

 return $rule_id;
 }

 public static function delete_rule($org_id, $rule_id, $user_id) {
 global $wpdb;

 $table = OraBooks_Database::table('classification_rules');
 $deleted = $wpdb->delete($table, ['id' => (int) $rule_id, 'org_id' => (int) $org_id], ['%d', '%d']);

 if (!$deleted) {
 return new WP_Error('not_found', __('Rule not found.', 'orabooks'));
 }

 orabooks_log_event('classification_rule_deleted', 'Classification rule deleted', 'info', [
 'rule_id' => (int) $rule_id,
 ], (int) $user_id, (int) $org_id);

 return true;
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

 if ($record_type === 'journal_line' && !empty($record->suggested_account_code)) {
 global $wpdb;
 $account = class_exists('OraBooks_COA')
 ? OraBooks_COA::get_account_by_code($org_id, $record->suggested_account_code)
: null;
 if ($account) {
 $wpdb->update(
 OraBooks_Database::table('journal_lines'),
 [
 'account_id' => (int) $account->id,
 'account_code' => $record->suggested_account_code,
 ],
 ['id' => (int) $record_id],
 ['%d', '%s'],
 ['%d']
 );
 }
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
 'tax_rate' => $rate,
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
 $wpdb->update(
 OraBooks_Database::table($map['table']),
 $updates,
 ['id' => (int) $record_id, 'org_id' => (int) $org_id],
 array_fill(0, count($updates), '%s'),
 ['%d', '%d']
 );
 }

 orabooks_log_event('classification_applied', sprintf('AI suggestions applied to %s #%d', $record_type, $record_id), 'info', [
 'record_type' => $record_type,
 'record_id' => $record_id,
 'updates' => $updates,
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
 $account = class_exists('OraBooks_COA') ? OraBooks_COA::get_account_by_code($org_id, $account_code): null;

 $map = self::$record_types[$record_type];
 $table = OraBooks_Database::table($map['table']);

 $wpdb->update(
 $table,
 [
 'classification_status' => 'overridden',
 'suggested_account_code' => $account_code,
 'suggested_account_id' => $account ? (int) $account->id: null,
 ],
 $map['org_column']
 ? ['id' => (int) $record_id, $map['org_column'] => (int) $org_id]
: ['id' => (int) $record_id],
 ['%s', '%s', '%d'],
 $map['org_column'] ? ['%d', '%d']: ['%d']
 );

 if ($tax_rate !== null && $record_type === 'expense') {
 $wpdb->update(
 $table,
 ['tax_rate' => (float) $tax_rate],
 ['id' => (int) $record_id, 'org_id' => (int) $org_id],
 ['%f'],
 ['%d', '%d']
 );
 }

 if ($record_type === 'journal_line' && $account) {
 $wpdb->update(
 OraBooks_Database::table('journal_lines'),
 [
 'account_id' => (int) $account->id,
 'account_code' => $account_code,
 ],
 ['id' => (int) $record_id],
 ['%d', '%s'],
 ['%d']
 );
 }

 if (class_exists('OraBooks_Observability') && method_exists('OraBooks_Observability', 'record_metric')) {
 OraBooks_Observability::record_metric('classification', 'override_count', 1, ['org_id' => (int) $org_id]);
 }

 orabooks_log_event('classification_override', sprintf('User overrode classification on %s #%d', $record_type, $record_id), 'info', [
 'record_type' => $record_type,
 'record_id' => $record_id,
 'account_code' => $account_code,
 'tax_rate' => $tax_rate,
 ], (int) $user_id, (int) $org_id);

 return self::get_record($record_type, $record_id, $org_id);
 }

 public static function format_classification($row) {
 if (!$row) {
 return null;
 }

 $tax_hints = self::decode_json_field($row->tax_hints ?? null);
 $risk = self::decode_json_field($row->classification_risk_score ?? null);

 return [
 'status' => $row->classification_status ?? 'pending',
 'suggested_account_code' => $row->suggested_account_code ?? null,
 'suggested_account_id' => !empty($row->suggested_account_id) ? (int) $row->suggested_account_id: null,
 'account_confidence' => isset($row->account_confidence) ? (float) $row->account_confidence: null,
 'tax_hints' => $tax_hints,
 'risk_score' => $risk,
 'model_version' => $row->classification_model_version ?? null,
 'tax_engine_version' => $row->tax_engine_version ?? null,
 'reason' => self::decode_reason($row->classification_reason ?? null),
 'reason_detail' => self::decode_reason_detail($row->classification_reason ?? null),
 'last_classified_at' => $row->last_classified_at ?? null,
 'low_confidence' => isset($row->account_confidence) && (float) $row->account_confidence < self::CONFIDENCE_THRESHOLD,
 ];
 }

 public function ajax_run {
 $user_id = orabooks_get_current_user_id;
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
 $status = (int) ($result->get_error_data['status'] ?? 400);
 orabooks_json_error($result->get_error_message, $status);
 }

 orabooks_json_success(['classification' => is_array($result) ? $result: self::format_classification($result)]);
 }

 public function ajax_apply {
 $user_id = orabooks_get_current_user_id;
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
 orabooks_json_error($result->get_error_message, 400);
 }

 orabooks_json_success(['classification' => self::format_classification($result)]);
 }

 public function ajax_override {
 $user_id = orabooks_get_current_user_id;
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
 $tax_rate = isset($_POST['tax_rate']) ? (float) $_POST['tax_rate']: null;

 $result = self::override($record_type, $record_id, $org_id, $user_id, $account_code, $tax_rate);

 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message, 400);
 }

 orabooks_json_success(['classification' => self::format_classification($result)]);
 }

 public function ajax_status {
 $user_id = orabooks_get_current_user_id;
 $org_id = orabooks_get_current_org_id($user_id);

 if (!$user_id || !$org_id) {
 orabooks_json_error('Authentication required', 401);
 }

 if (!self::can_view($user_id, $org_id)) {
 orabooks_json_error('Permission denied', 403);
 }

 $record_type = sanitize_text_field($_REQUEST['record_type'] ?? '');
 $record_id = (int) ($_REQUEST['record_id'] ?? 0);
 $result = self::get_status($record_type, $record_id, $org_id);

 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message, 400);
 }

 orabooks_json_success(['classification' => $result]);
 }

 public function ajax_rules_list {
 $user_id = orabooks_get_current_user_id;
 $org_id = orabooks_get_current_org_id($user_id);

 if (!$user_id || !$org_id) {
 orabooks_json_error('Authentication required', 401);
 }

 if (!self::can_manage_rules($user_id, $org_id)) {
 orabooks_json_error('Permission denied', 403);
 }

 $rules = self::list_rules($org_id);
 orabooks_json_success([
 'rules' => array_map([self::class, 'format_rule'], $rules),
 'rule_precedes_ai' => self::rule_precedence_over_ai_enabled,
 ]);
 }

 public function ajax_rules_save {
 $user_id = orabooks_get_current_user_id;
 $org_id = orabooks_get_current_org_id($user_id);

 if (!$user_id || !$org_id) {
 orabooks_json_error('Authentication required', 401);
 }

 if (!self::can_manage_rules($user_id, $org_id)) {
 orabooks_json_error('Permission denied', 403);
 }

 if (isset($_POST['rule_precedes_ai'])) {
 self::set_rule_precedence_over_ai(!empty($_POST['rule_precedes_ai']));
 }

 $data = [
 'id' => (int) ($_POST['id'] ?? 0),
 'rule_type' => sanitize_text_field($_POST['rule_type'] ?? 'keyword'),
 'match_value' => sanitize_text_field($_POST['match_value'] ?? ''),
 'account_code' => sanitize_text_field($_POST['account_code'] ?? ''),
 'tax_jurisdiction' => sanitize_text_field($_POST['tax_jurisdiction'] ?? ''),
 'priority' => (int) ($_POST['priority'] ?? 10),
 'is_active' => !empty($_POST['is_active']),
 ];

 if ($data['match_value'] !== '' || $data['account_code'] !== '') {
 $result = self::save_rule($org_id, $data, $user_id);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message, 400);
 }
 }

 orabooks_json_success([
 'rules' => array_map([self::class, 'format_rule'], self::list_rules($org_id)),
 'rule_precedes_ai' => self::rule_precedence_over_ai_enabled,
 ], 'Classification rules saved');
 }

 public function ajax_rules_delete {
 $user_id = orabooks_get_current_user_id;
 $org_id = orabooks_get_current_org_id($user_id);
 $rule_id = (int) ($_POST['rule_id'] ?? 0);

 if (!$user_id || !$org_id) {
 orabooks_json_error('Authentication required', 401);
 }

 if (!self::can_manage_rules($user_id, $org_id)) {
 orabooks_json_error('Permission denied', 403);
 }

 $result = self::delete_rule($org_id, $rule_id, $user_id);
 if (is_wp_error($result)) {
 orabooks_json_error($result->get_error_message, 400);
 }

 orabooks_json_success([
 'rules' => array_map([self::class, 'format_rule'], self::list_rules($org_id)),
 ], 'Rule deleted');
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

 private static function can_manage_rules($user_id, $org_id) {
 return OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_classification_rules')
 || OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_org_settings');
 }

 public static function format_rule($rule) {
 return [
 'id' => (int) $rule->id,
 'rule_type' => $rule->rule_type,
 'match_value' => $rule->match_value,
 'account_code' => $rule->account_code,
 'tax_jurisdiction' => $rule->tax_jurisdiction,
 'priority' => (int) $rule->priority,
 'is_active' => (bool) $rule->is_active,
 ];
 }

 private static function get_record($record_type, $record_id, $org_id) {
 global $wpdb;

 $map = self::$record_types[$record_type] ?? null;
 if (!$map) {
 return null;
 }

 if ($record_type === 'journal_line') {
 $lines = OraBooks_Database::table('journal_lines');
 $journals = OraBooks_Database::table('journals');
 return $wpdb->get_row($wpdb->prepare(
 "SELECT jl.* FROM {$lines} jl
 INNER JOIN {$journals} j ON j.id = jl.journal_id
 WHERE jl.id = %d AND j.org_id = %d",
 (int) $record_id,
 (int) $org_id
 ));
 }

 $table = OraBooks_Database::table($map['table']);
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
 'account_code' => $rule->account_code,
 'confidence' => 95.0,
 'source' => 'rule',
 'reason' => sprintf("Matched %s rule '%s'", $rule->rule_type, $rule->match_value),
 'tax_jurisdiction' => $rule->tax_jurisdiction ?: 'US',
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
 'meals' => '5200',
 'travel' => '5300',
 'office supplies' => '5100',
 'software' => '5500',
 'utilities' => '5400',
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
 'account_code' => $account_code,
 'confidence' => $confidence,
 'source' => 'ai_stub',
 'reason' => $reason,
 'tax_jurisdiction' => 'US',
 'model_version' => OraBooks_Ai_Providers::model_version('classification'),
 ];
 }

 private static function build_tax_hints($org_id, $amount, $jurisdiction) {
 if (!class_exists('OraBooks_Tax') || $amount <= 0) {
 return [
 'tax_rate' => 0,
 'tax_type' => 'None',
 'confidence' => 50,
 'source' => 'fallback',
 ];
 }

 $calc = OraBooks_Tax::calculate([
 'org_id' => $org_id,
 'amount' => $amount,
 'jurisdiction' => $jurisdiction,
 ]);

 if (is_wp_error($calc)) {
 return [
 'tax_rate' => 0,
 'tax_type' => 'None',
 'confidence' => 40,
 'source' => 'error',
 ];
 }

 return [
 'tax_rate' => (float) ($calc['tax_rate'] ?? 0),
 'tax_type' => $calc['tax_type'] ?? 'Sales Tax',
 'tax_amount' => (float) ($calc['tax_amount'] ?? 0),
 'confidence' => 90,
 'source' => '',
 'rule_id' => $calc['rule_id'] ?? null,
 ];
 }

 private static function build_idempotency_key($record_type, $record_id, $record) {
 $map = self::$record_types[$record_type];
 $parts = [$record_type, $record_id];
 foreach ($map['text_columns'] as $column) {
 $parts[] = (string) ($record->{$column} ?? '');
 }
 $parts[] = (string) ($record->{$map['amount_column']} ?? '');
 return substr(hash('sha256', implode('|', $parts)), 0, 64);
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
 return is_array($decoded) ? $decoded: [];
 }

 private static function mark_failed($record_type, $record_id, $org_id, $message) {
 global $wpdb;

 if (!isset(self::$record_types[$record_type])) {
 return;
 }

 $map = self::$record_types[$record_type];
 $table = OraBooks_Database::table($map['table']);
 $payload = [
 'classification_status' => 'failed',
 'classification_reason' => self::encode_reason([
 'summary' => (string) $message,
 'source' => 'system',
 ]),
 'last_classified_at' => current_time('mysql', true),
 ];

 if ($map['org_column']) {
 $wpdb->update(
 $table,
 $payload,
 ['id' => (int) $record_id, $map['org_column'] => (int) $org_id],
 ['%s', '%s', '%s'],
 ['%d', '%d']
 );
 } else {
 $wpdb->update($table, $payload, ['id' => (int) $record_id], ['%s', '%s', '%s'], ['%d']);
 }

 orabooks_log_event('classification_failed', (string) $message, 'warning', [
 'record_type' => $record_type,
 'record_id' => (int) $record_id,
 ], 0, (int) $org_id);
 }

 private static function encode_reason($suggestion) {
 if (is_string($suggestion)) {
 return $suggestion;
 }

 $summary = $suggestion['reason'] ?? ($suggestion['summary'] ?? '');
 $payload = [
 'summary' => (string) $summary,
 'source' => (string) ($suggestion['source'] ?? 'ai'),
 'account_code' => (string) ($suggestion['account_code'] ?? ''),
 'confidence' => isset($suggestion['confidence']) ? (float) $suggestion['confidence']: null,
 ];

 return wp_json_encode($payload);
 }

 private static function decode_reason($value) {
 $detail = self::decode_reason_detail($value);
 return $detail['summary'] ?? (is_string($value) ? $value: '');
 }

 private static function decode_reason_detail($value) {
 if ($value === null || $value === '') {
 return [];
 }
 if (is_array($value)) {
 return $value;
 }
 $decoded = json_decode((string) $value, true);
 if (is_array($decoded)) {
 return $decoded;
 }
 return ['summary' => (string) $value];
 }

 /**
 * Live (production) readiness checks for — callable from UI without PHPUnit.
 *
 * @return array{ok:bool,checks:array<int,array<string,mixed>>,environment:array<string,mixed>,manual_steps:array<int,array<string,string>>}
 */
 public static function run_live_checks($org_id = 0) {
 global $wpdb;

 $org_id = (int) $org_id;
 $checks = [];
 $ok = true;

 $add = function ($id, $label, $passed, $detail = '', $action = '') use (&$checks, &$ok) {
 $passed = (bool) $passed;
 if (!$passed) {
 $ok = false;
 }
 $row = [
 'id' => (string) $id,
 'label' => (string) $label,
 'ok' => $passed,
 'detail' => (string) $detail,
 ];
 if ($action !== '') {
 $row['action_url'] = (string) $action;
 }
 $checks[] = $row;
 };

 $rules_table = OraBooks_Database::table('classification_rules');
 $add(
 'table_classification_rules',
 'Classification rules table',
 $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $rules_table)) === $rules_table,
 $rules_table
 );

 foreach (['expenses', 'invoices'] as $base) {
 $table = OraBooks_Database::table($base);
 $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
 $has_cols = is_array($columns)
 && in_array('classification_status', $columns, true)
 && in_array('classification_idempotency_key', $columns, true);
 $add(
 $base. '_classification_columns',
 ucfirst($base). ' classification columns',
 $has_cols,
 is_array($columns) ? implode(', ', array_intersect($columns, ['classification_status', 'suggested_account_code'])): 'n/a'
 );
 }

 $journal_lines = OraBooks_Database::table('journal_lines');
 if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $journal_lines)) === $journal_lines) {
 $jl_cols = $wpdb->get_col("SHOW COLUMNS FROM {$journal_lines}", 0);
 $add(
 'journal_lines_classification_columns',
 'Journal line classification columns',
 is_array($jl_cols) && in_array('classification_status', $jl_cols, true),
 is_array($jl_cols) ? implode(', ', array_intersect($jl_cols, ['classification_status', 'suggested_account_code'])): 'n/a'
 );
 }

 $handler = class_exists('OraBooks_AsyncQueue')
 ? OraBooks_AsyncQueue::get_handler('classify_transaction')
: null;
 $add('async_classify_handler', 'Async handler: classify_transaction', is_callable($handler));

 $provider = class_exists('OraBooks_Ai_Providers')
 ? OraBooks_Ai_Providers::provider_name('classification')
: 'missing';
 $add(
 'ai_classification_provider',
 'AI classification provider ',
 true,
 $provider === OraBooks_Ai_Providers::STUB_PROVIDER
 ? 'Using MVP stub — configure OpenAI/Azure for live AI'
: 'Active provider: '. $provider
 );

 $manifest = class_exists('OraBooks_Assets') ? OraBooks_Assets::get_react_manifest: [];
 $generated = $manifest['generated_at'] ?? '';
 $add(
 'react_ui_bundle',
 'React UI bundle deployed',
 !empty($manifest['files']),
 $generated ? 'Built at '. $generated: 'deploy-manifest.json missing — run orabooks-ui/build-live.ps1'
 );

 if ($org_id > 0) {
 $rule_count = (int) $wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM {$rules_table} WHERE org_id = %d AND is_active = 1",
 $org_id
 ));
 $add(
 'org_classification_rules',
 'Active classification rules for org',
 $rule_count > 0,
 (string) $rule_count. ' rule(s)',
 '/tax-settings'
 );

 if (class_exists('OraBooks_COA')) {
 $accounts = OraBooks_COA::get_accounts($org_id);
 $add(
 'org_coa_loaded',
 'Chart of accounts loaded ',
 !empty($accounts),
 empty($accounts) ? 'Import or seed COA first': count($accounts). ' account(s)',
 '/chart-of-accounts'
 );
 }

 if (class_exists('OraBooks_Tax')) {
 $tax_configs = OraBooks_Tax::list_configs($org_id);
 $add(
 'org_tax_config',
 'Tax configuration ',
 !empty($tax_configs),
 empty($tax_configs) ? 'Add tax jurisdiction in Tax Settings': count($tax_configs). ' jurisdiction(s)',
 '/tax-settings'
 );
 }

 $expenses_table = OraBooks_Database::table('expenses');
 $sample = $wpdb->get_row($wpdb->prepare(
 "SELECT id, classification_status, suggested_account_code, account_confidence
 FROM {$expenses_table}
 WHERE org_id = %d
 ORDER BY id DESC LIMIT 1",
 $org_id
 ));
 $add(
 'sample_expense_classification',
 'Latest expense has classification data',
 $sample && !empty($sample->classification_status),
 $sample
 ? sprintf('#%d status=%s account=%s', (int) $sample->id, $sample->classification_status, $sample->suggested_account_code ?: '—')
: 'Upload a receipt on Expenses page to create one',
 '/expenses'
 );
 }

 $manual_steps = [
 [
 'step' => '1',
 'title' => 'System check',
 'detail' => 'Click "Run live check" on this page — all rows should be green (AI stub is OK for MVP).',
 ],
 [
 'step' => '2',
 'title' => 'Expense classification',
 'detail' => 'Go to Expenses → upload receipt → wait for AI Classification panel (pending → processed).',
 'url' => '/expenses',
 ],
 [
 'step' => '3',
 'title' => 'Apply / Override',
 'detail' => 'On a processed expense, test Apply AI suggestions and Override modal.',
 'url' => '/expenses',
 ],
 [
 'step' => '4',
 'title' => 'Invoice classification',
 'detail' => 'Create draft invoice → View detail → classification panel should appear.',
 'url' => '/invoices',
 ],
 [
 'step' => '5',
 'title' => 'Classification rules',
 'detail' => 'Tax Settings → Classification rules → toggle "Rules dominate AI", add a keyword rule.',
 'url' => '/tax-settings',
 ],
 [
 'step' => '6',
 'title' => 'Observability',
 'detail' => 'Open Observability — classification metrics should appear after classifying.',
 'url' => '/observability',
 ],
 ];

 return [
 'ok' => $ok,
 'checks' => $checks,
 'environment' => [
 'org_id' => $org_id > 0 ? $org_id: null,
 'rule_precedence_ai' => self::rule_precedence_over_ai_enabled,
 'confidence_threshold'=> self::CONFIDENCE_THRESHOLD,
 'react_bundle_at' => $generated ?: null,
 'plugin_version' => defined('ORABOOKS_VERSION') ? ORABOOKS_VERSION: null,
 ],
 'manual_steps' => $manual_steps,
 ];
 }

 public function ajax_live_check {
 $user_id = orabooks_get_current_user_id;
 $org_id = orabooks_get_current_org_id($user_id);

 if (!$user_id || !$org_id) {
 orabooks_json_error('Authentication required', 401);
 }

 if (!self::can_view($user_id, $org_id) && !self::can_manage_rules($user_id, $org_id)) {
 orabooks_json_error('Permission denied', 403);
 }

 orabooks_json_success(self::run_live_checks($org_id));
 }
}
