<?php
/**
 * OraBooks Workflow State Engine
 *
 * Central state machine for business records: validates transitions,
 * updates status inside a DB transaction, logs audit trail, and publishes
 * state_transition events.
 */

if (!defined('ABSPATH')) {
 exit;
}

class OraBooks_Workflow {

 private static $instance = null;
 private static $machines = [];

 /** Maps record types to table + status column metadata. */
 private static $record_map = [
 'journal' => [
 'table' => 'journals',
 'status_column' => 'status',
 'org_id_column' => 'org_id',
 ],
 'invoice' => [
 'table' => 'invoices',
 'status_column' => 'workflow_status',
 'org_id_column' => 'org_id',
 ],
 'bill' => [
 'table' => 'bills',
 'status_column' => 'workflow_status',
 'org_id_column' => 'org_id',
 ],
 'expense' => [
 'table' => 'expenses',
 'status_column' => 'workflow_status',
 'org_id_column' => 'org_id',
 ],
 'commission' => [
 'table' => 'commissions_earned',
 'status_column' => 'status',
 'org_id_column' => 'org_id',
 ],
 ];

 public static function init() {
 if (self::$instance === null) {
 self::$instance = new self;
 self::register_default_machines;

 add_action('wp_ajax_orabooks_workflow_transitions', [self::$instance, 'ajax_get_transitions']);
 add_action('wp_ajax_orabooks_workflow_allowed_events', [self::$instance, 'ajax_allowed_events']);
 add_action('wp_ajax_orabooks_workflow_transition', [self::$instance, 'ajax_transition']);
 add_action('wp_ajax_orabooks_workflow_health', [self::$instance, 'ajax_workflow_health']);
 }

 if (class_exists('OraBooks_Workflow_Integration')) {
 OraBooks_Workflow_Integration::init;
 }

 return self::$instance;
 }

 /**
 * Default state machine definitions (MVP hard-coded, filterable).
 */
 public static function register_default_machines() {
 self::$machines = apply_filters('orabooks_workflow_state_machines', [
 'journal' => [
 'states' => ['draft', 'review_pending', 'approved', 'posted', 'locked', 'reversed'],
 'transitions' => [
 'submit' => ['from' => 'draft', 'to' => 'review_pending'],
 'approve' => ['from' => 'review_pending', 'to' => 'approved'],
 'reject' => ['from' => 'review_pending', 'to' => 'draft'],
 'post' => ['from' => 'approved', 'to' => 'posted'],
 'lock' => ['from' => 'posted', 'to' => 'locked'],
 'reverse' => ['from' => ['posted', 'locked'], 'to' => 'reversed'],
 'edit' => ['from' => ['draft', 'approved'], 'to' => 'draft'],
 ],
 ],
 'invoice' => [
 'states' => ['draft', 'submitted', 'approved', 'sent', 'posted', 'cancelled'],
 'transitions' => [
 'submit' => ['from' => 'draft', 'to' => 'submitted'],
 'approve' => ['from' => ['submitted', 'sent'], 'to' => 'approved'],
 'send' => ['from' => 'draft', 'to' => 'sent'],
 'post' => ['from' => ['draft', 'sent', 'submitted', 'approved'], 'to' => 'posted'],
 'cancel' => ['from' => ['draft', 'submitted', 'approved', 'sent'], 'to' => 'cancelled'],
 'lock' => ['from' => 'posted', 'to' => 'posted'],
 ],
 ],
 'bill' => [
 'states' => ['draft', 'submitted', 'approved', 'posted', 'void'],
 'transitions' => [
 'submit' => ['from' => 'draft', 'to' => 'submitted'],
 'approve' => ['from' => 'submitted', 'to' => 'approved'],
 'post' => ['from' => ['approved', 'submitted'], 'to' => 'posted'],
 'void' => ['from' => ['draft', 'submitted', 'approved'], 'to' => 'void'],
 ],
 ],
 'expense' => [
 'states' => ['draft', 'submitted', 'ai_review', 'approved', 'posted', 'locked'],
 'transitions' => [
 'submit' => ['from' => 'draft', 'to' => 'submitted'],
 'ai_review' => ['from' => ['draft', 'submitted'], 'to' => 'ai_review'],
 'approve' => ['from' => ['submitted', 'ai_review'], 'to' => 'approved'],
 'reject' => ['from' => ['submitted', 'ai_review'], 'to' => 'draft'],
 'post' => ['from' => 'approved', 'to' => 'posted'],
 'lock' => ['from' => 'posted', 'to' => 'locked'],
 ],
 ],
 'commission' => [
 'states' => ['earned', 'paid', 'expired'],
 'transitions' => [
 'pay' => ['from' => 'earned', 'to' => 'paid'],
 'expire' => ['from' => 'earned', 'to' => 'expired'],
 ],
 ],
 ]);
 }

 public static function get_machines() {
 if (empty(self::$machines)) {
 self::register_default_machines;
 }
 return self::$machines;
 }

 /**
 * Events allowed from a given state (for UI / SL callers).
 *
 * @return string[]
 */
 public static function allowed_events($record_type, $current_state) {
 $machines = self::get_machines;
 $sm = $machines[$record_type] ?? null;
 if (!$sm) {
 return [];
 }

 $allowed = [];
 foreach ($sm['transitions'] as $event => $def) {
 $from_states = is_array($def['from']) ? $def['from']: [$def['from']];
 if (in_array($current_state, $from_states, true)) {
 $allowed[] = $event;
 }
 }

 return $allowed;
 }

 /**
 * Validate whether an event can fire from the current state.
 *
 * @return true|WP_Error
 */
 public static function validate_transition($record_type, $current_state, $event) {
 $machines = self::get_machines;
 $sm = $machines[$record_type] ?? null;

 if (!$sm) {
 return new WP_Error('invalid_type', __('Unknown record type', 'orabooks'));
 }

 $transition = $sm['transitions'][$event] ?? null;
 if (!$transition) {
 return new WP_Error('invalid_event', __('Unknown event for this record type', 'orabooks'));
 }

 $from_states = is_array($transition['from']) ? $transition['from']: [$transition['from']];
 if (!in_array($current_state, $from_states, true)) {
 orabooks_log_event('invalid_state_transition', sprintf(
 'Invalid transition %s on %s from state %s',
 $event,
 $record_type,
 $current_state
 ), 'warning', [
 'record_type' => $record_type,
 'event' => $event,
 'current_state' => $current_state,
 'allowed_from' => $from_states,
 ]);

 return new WP_Error(
 'invalid_state',
 sprintf(__('Cannot transition from state: %s', 'orabooks'), $current_state),
 ['status' => 409]
 );
 }

 return true;
 }

 /**
 * Execute a state transition (validate, optionally update status, log, publish).
 *
 * @param array $context user_id, org_id, reason, metadata, update_status (default true), service_name
 * @return array|WP_Error
 */
 public static function transition($record_type, $record_id, $event, $context = []) {
 global $wpdb;

 $record_id = (int) $record_id;
 $user_id = isset($context['user_id']) ? (int) $context['user_id']: null;
 $reason = $context['reason'] ?? null;
 $metadata = $context['metadata'] ?? null;
 $update_status = array_key_exists('update_status', $context) ? (bool) $context['update_status']: true;
 $org_id = isset($context['org_id']) ? (int) $context['org_id']: null;
 $skip_transaction = !empty($context['skip_transaction']);

 $map = self::$record_map[$record_type] ?? null;
 if (!$map) {
 return new WP_Error('invalid_type', __('Unknown record type', 'orabooks'));
 }

 $table = OraBooks_Database::table($map['table']);
 $status_col = $map['status_column'];
 $org_col = $map['org_id_column'];

 self::begin_transaction($skip_transaction);

 try {
 $sql = "SELECT * FROM {$table} WHERE id = %d";
 $params = [$record_id];
 if ($org_id) {
 $sql.= " AND {$org_col} = %d";
 $params[] = $org_id;
 }
 $sql.= ' FOR UPDATE';

 $record = $wpdb->get_row($wpdb->prepare($sql,...$params));
 if (!$record) {
 self::rollback_transaction($skip_transaction);
 return new WP_Error('not_found', __('Record not found', 'orabooks'));
 }

 $resolved_org_id = $org_id ?: (int) ($record->{$org_col} ?? 0);
 $current_state = $record->{$status_col};

 $validation = self::validate_transition($record_type, $current_state, $event);
 if (is_wp_error($validation)) {
 self::track_failure($record_type, $event, $resolved_org_id, $validation->get_error_code, $context);
 self::rollback_transaction($skip_transaction);
 return $validation;
 }

 $preconditions = self::check_preconditions($record_type, $event, $record, $context);
 if (is_wp_error($preconditions)) {
 self::track_failure($record_type, $event, $resolved_org_id, $preconditions->get_error_code, $context);
 self::rollback_transaction($skip_transaction);
 return $preconditions;
 }

 $machines = self::get_machines;
 $to_state = $machines[$record_type]['transitions'][$event]['to'];

 $row_updates = apply_filters(
 'orabooks_workflow_row_updates',
 is_array($context['row_updates'] ?? null) ? $context['row_updates']: [],
 $record_type,
 $event,
 $record,
 $context
 );

 if ($update_status || !empty($row_updates)) {
 $update_data = $row_updates;
 if ($update_status) {
 $update_data[$status_col] = $to_state;
 }

 $updated = $wpdb->update(
 $table,
 $update_data,
 ['id' => $record_id],
 self::build_update_formats($update_data),
 ['%d']
 );
 if ($updated === false) {
 self::rollback_transaction($skip_transaction);
 return new WP_Error('db_error', __('Failed to update record status', 'orabooks'));
 }
 }

 $transition_id = self::persist_transition(
 $record_type,
 $record_id,
 $current_state,
 $to_state,
 $event,
 $user_id,
 $reason,
 $metadata,
 $resolved_org_id
 );
 if ($transition_id <= 0) {
 self::rollback_transaction($skip_transaction);
 return new WP_Error('db_error', __('Failed to persist transition', 'orabooks'));
 }

 $publish_result = self::publish_transition_event(
 $record_type,
 $record_id,
 $current_state,
 $to_state,
 $event,
 array_merge($context, ['org_id' => $resolved_org_id]),
 $record
 );
 if (self::should_rollback_on_publish_failure($publish_result, $context)) {
 self::rollback_transaction($skip_transaction);
 return is_wp_error($publish_result)
 ? $publish_result
: new WP_Error('event_publish_failed', __('Failed to publish state_transition event', 'orabooks'));
 }

 self::commit_transaction($skip_transaction);
 } catch (Exception $e) {
 self::rollback_transaction($skip_transaction);
 return new WP_Error('transition_failed', $e->getMessage);
 }

 $result = [
 'transition_id' => $transition_id,
 'from_state' => $current_state,
 'to_state' => $to_state,
 'event' => $event,
 'org_id' => $resolved_org_id,
 ];

 do_action(
 'orabooks_workflow_after_transition',
 $record_type,
 $record_id,
 $event,
 $result,
 $record,
 $context
 );

 orabooks_log_event('state_changed', sprintf(
 '%s #%d transitioned %s → %s via %s',
 $record_type,
 $record_id,
 $current_state,
 $to_state,
 $event
 ), 'info', [
 'record_type' => $record_type,
 'record_id' => $record_id,
 'from_state' => $current_state,
 'to_state' => $to_state,
 'event' => $event,
 'transition_id' => $transition_id,
 ], $user_id, $resolved_org_id);

 return $result;
 }

 /**
 * @deprecated Use OraBooks_Workflow::transition — logs only when caller already updated status.
 */
 public static function record_transition($record_type, $record_id, $event, $user_id, $reason = null, $metadata = null) {
 if (function_exists('_deprecated_function')) {
 _deprecated_function(__METHOD__, '1.1.0', 'OraBooks_Workflow::transition');
 }

 return self::transition($record_type, $record_id, $event, [
 'user_id' => $user_id,
 'reason' => $reason,
 'metadata' => $metadata,
 'update_status' => false,
 ]);
 }

 /**
 * Fetch transition history for a record (optionally org-scoped).
 */
 public static function get_transitions($record_type, $record_id, $limit = 50, $org_id = null) {
 global $wpdb;

 $table = OraBooks_Database::table('state_machine_transitions');
 $limit = max(1, min(200, (int) $limit));

 $sql = "SELECT * FROM {$table}
 WHERE record_type = %s AND record_id = %d";
 $params = [sanitize_text_field($record_type), (int) $record_id];

 if ($org_id) {
 $sql.= ' AND org_id = %d';
 $params[] = (int) $org_id;
 }

 $sql.= ' ORDER BY id DESC LIMIT %d';
 $params[] = $limit;

 return $wpdb->get_results($wpdb->prepare($sql,...$params));
 }

 public static function format_transition_row($row) {
 if (!$row) {
 return null;
 }

 $formatted = [
 'id' => (int) $row->id,
 'record_type' => $row->record_type,
 'record_id' => (int) $row->record_id,
 'from_state' => $row->from_state,
 'to_state' => $row->to_state,
 'event' => $row->event,
 'triggered_by' => $row->triggered_by ? (int) $row->triggered_by: null,
 'reason' => $row->reason,
 'created_at' => $row->created_at,
 ];

 if (isset($row->org_id)) {
 $formatted['org_id'] = $row->org_id ? (int) $row->org_id: null;
 }

 return $formatted;
 }

 public function ajax_get_transitions() {
 $user_id = orabooks_get_current_user_id;
 $org_id = orabooks_get_current_org_id($user_id);

 if (!$user_id || !$org_id) {
 orabooks_json_error('Authentication required', 401);
 }

 if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'view_audit_logs')) {
 orabooks_json_error('Permission denied', 403);
 }

 $record_type = sanitize_text_field($_REQUEST['record_type'] ?? '');
 $record_id = (int) ($_REQUEST['record_id'] ?? 0);

 if ($record_type === '' || $record_id <= 0) {
 orabooks_json_error('record_type and record_id are required', 400);
 }

 $rows = self::get_transitions($record_type, $record_id, 50, $org_id);
 orabooks_json_success([
 'transitions' => array_map([self::class, 'format_transition_row'], $rows ?: []),
 ]);
 }

 public function ajax_allowed_events() {
 $user_id = orabooks_get_current_user_id;
 $org_id = orabooks_get_current_org_id($user_id);

 if (!$user_id || !$org_id) {
 orabooks_json_error('Authentication required', 401);
 }

 if (!self::current_user_can_transition($user_id, $org_id)) {
 orabooks_json_error('Permission denied', 403);
 }

 $record_type = sanitize_text_field($_REQUEST['record_type'] ?? '');
 $record_id = (int) ($_REQUEST['record_id'] ?? 0);

 if ($record_type === '' || $record_id <= 0) {
 orabooks_json_error('record_type and record_id are required', 400);
 }

 $state = self::get_record_state($record_type, $record_id, $org_id);
 if (is_wp_error($state)) {
 orabooks_json_error($state->get_error_message, 404);
 }

 orabooks_json_success([
 'current_state' => $state,
 'allowed_events' => self::allowed_events($record_type, $state),
 ]);
 }

 public function ajax_transition() {
 $user_id = orabooks_get_current_user_id;
 $org_id = orabooks_get_current_org_id($user_id);

 if (!$user_id || !$org_id) {
 orabooks_json_error('Authentication required', 401);
 }

 if (!self::current_user_can_transition($user_id, $org_id)) {
 orabooks_json_error('Permission denied', 403);
 }

 $record_type = sanitize_text_field($_REQUEST['record_type'] ?? '');
 $record_id = (int) ($_REQUEST['record_id'] ?? 0);
 $event = sanitize_text_field($_REQUEST['event'] ?? '');

 if ($record_type === '' || $record_id <= 0 || $event === '') {
 orabooks_json_error('record_type, record_id, and event are required', 400);
 }

 $result = self::transition($record_type, $record_id, $event, [
 'user_id' => $user_id,
 'org_id' => $org_id,
 'reason' => isset($_REQUEST['reason']) ? sanitize_textarea_field(wp_unslash($_REQUEST['reason'])): null,
 ]);

 if (is_wp_error($result)) {
 $status = 400;
 $data = $result->get_error_data;
 if (is_array($data) && isset($data['status'])) {
 $status = (int) $data['status'];
 }
 orabooks_json_error($result->get_error_message, $status);
 }

 orabooks_json_success($result);
 }

 /**
 * @return string|WP_Error
 */
 private static function get_record_state($record_type, $record_id, $org_id) {
 global $wpdb;

 $map = self::$record_map[$record_type] ?? null;
 if (!$map) {
 return new WP_Error('invalid_type', __('Unknown record type', 'orabooks'));
 }

 $table = OraBooks_Database::table($map['table']);
 $status_col = $map['status_column'];
 $org_col = $map['org_id_column'];

 $record = $wpdb->get_row($wpdb->prepare(
 "SELECT {$status_col} FROM {$table} WHERE id = %d AND {$org_col} = %d",
 (int) $record_id,
 (int) $org_id
 ));

 if (!$record) {
 return new WP_Error('not_found', __('Record not found', 'orabooks'));
 }

 return (string) $record->{$status_col};
 }

 private static function current_user_can_transition($user_id, $org_id) {
 if (function_exists('current_user_can') && current_user_can('manage_options')) {
 return true;
 }

 return orabooks_has_permission($user_id, $org_id, 'manage_settings')
 || orabooks_has_permission($user_id, $org_id, 'submit_transaction')
 || orabooks_has_permission($user_id, $org_id, 'approve_journal');
 }

 public function ajax_workflow_health() {
 $user_id = orabooks_get_current_user_id;
 $org_id = orabooks_get_current_org_id($user_id);

 if (!$user_id || !$org_id) {
 orabooks_json_error('Authentication required', 401);
 }

 if (!orabooks_has_permission($user_id, $org_id, 'view_audit_logs')
 && !orabooks_has_permission($user_id, $org_id, 'manage_settings')) {
 orabooks_json_error('Permission denied', 403);
 }

 if (!class_exists('OraBooks_Observability')) {
 orabooks_json_error('Observability module unavailable', 503);
 }

 orabooks_json_success([
 'workflow' => OraBooks_Observability::get_workflow_health($org_id),
 ]);
 }

 private static function track_failure($record_type, $event, $org_id, $reason, $context) {
 if (class_exists('OraBooks_Workflow_Integration')) {
 OraBooks_Workflow_Integration::track_failure($record_type, $event, (int) $org_id, (string) $reason, is_array($context) ? $context: []);
 }
 }

 /**
 * @return true|WP_Error
 */
 private static function check_preconditions($record_type, $event, $record, $context) {
 $result = apply_filters(
 'orabooks_workflow_preconditions',
 true,
 $record_type,
 $event,
 $record,
 $context
 );

 if ($result === true) {
 return true;
 }

 if (is_wp_error($result)) {
 return $result;
 }

 return new WP_Error(
 'precondition_failed',
 __('Transition preconditions failed', 'orabooks'),
 ['status' => 400]
 );
 }

 private static function should_rollback_on_publish_failure($publish_result, $context) {
 if ($publish_result === true) {
 return false;
 }

 $strict = apply_filters(
 'orabooks_workflow_rollback_on_publish_failure',
 !empty($context['require_event_publish']),
 $publish_result,
 $context
 );

 return (bool) $strict;
 }

 private static function build_update_formats(array $data) {
 $formats = [];
 foreach ($data as $value) {
 if (is_int($value)) {
 $formats[] = '%d';
 } elseif (is_float($value)) {
 $formats[] = '%f';
 } elseif ($value === null) {
 $formats[] = '%s';
 } elseif (is_numeric($value)) {
 $formats[] = strpos((string) $value, '.') !== false ? '%f': '%d';
 } else {
 $formats[] = '%s';
 }
 }
 return $formats;
 }

 private static function begin_transaction($skip = false) {
 if ($skip) {
 return;
 }
 global $wpdb;
 $wpdb->query('START TRANSACTION');
 }

 private static function commit_transaction($skip = false) {
 if ($skip) {
 return;
 }
 global $wpdb;
 $wpdb->query('COMMIT');
 }

 private static function rollback_transaction($skip = false) {
 if ($skip) {
 return;
 }
 global $wpdb;
 $wpdb->query('ROLLBACK');
 }

 private static function persist_transition($record_type, $record_id, $from_state, $to_state, $event, $user_id, $reason, $metadata, $org_id = null) {
 global $wpdb;

 $table = OraBooks_Database::table('state_machine_transitions');
 $inserted = $wpdb->insert($table, [
 'org_id' => $org_id ? (int) $org_id: null,
 'record_type' => sanitize_text_field($record_type),
 'record_id' => (int) $record_id,
 'from_state' => sanitize_text_field($from_state),
 'to_state' => sanitize_text_field($to_state),
 'event' => sanitize_text_field($event),
 'triggered_by' => $user_id ? (int) $user_id: null,
 'reason' => $reason ? sanitize_textarea_field($reason): null,
 'metadata' => !empty($metadata) ? wp_json_encode($metadata): null,
 ], ['%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s']);

 if ($inserted === false) {
 return 0;
 }

 return (int) $wpdb->insert_id;
 }

 /**
 * @return true|WP_Error
 */
 private static function publish_transition_event($record_type, $record_id, $from_state, $to_state, $event, $context, $record) {
 if (!function_exists('orabooks_publish_event')) {
 return true;
 }

 $org_id = (int) ($context['org_id'] ?? ($record->org_id ?? 0));

 $result = orabooks_publish_event('state_transition', (int) $record_id, [
 'record_type' => $record_type,
 'record_id' => (int) $record_id,
 'org_id' => $org_id,
 'from_state' => $from_state,
 'to_state' => $to_state,
 'event' => $event,
 'triggered_by' => $context['user_id'] ?? null,
 'service_name' => $context['service_name'] ?? 'orabooks_workflow',
 'reason' => $context['reason'] ?? null,
 'metadata' => $context['metadata'] ?? null,
 ]);

 if ($result === false) {
 return new WP_Error('event_publish_failed', __('Failed to publish state_transition event', 'orabooks'));
 }

 if (is_wp_error($result)) {
 return $result;
 }

 return true;
 }
}
