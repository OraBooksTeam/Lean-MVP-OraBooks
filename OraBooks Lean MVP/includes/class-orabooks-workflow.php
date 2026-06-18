<?php
/**
 * OraBooks Workflow State Engine (SL-301)
 *
 * Central state machine for business records: validates transitions,
 * updates status, logs audit trail, and publishes state_transition events.
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
            'table'          => 'journals',
            'status_column'  => 'status',
            'org_id_column'  => 'org_id',
        ],
        'invoice' => [
            'table'          => 'invoices',
            'status_column'  => 'workflow_status',
            'org_id_column'  => 'org_id',
        ],
        'bill' => [
            'table'          => 'bills',
            'status_column'  => 'workflow_status',
            'org_id_column'  => 'org_id',
        ],
        'expense' => [
            'table'          => 'expenses',
            'status_column'  => 'workflow_status',
            'org_id_column'  => 'org_id',
        ],
        'commission' => [
            'table'          => 'commissions_earned',
            'status_column'  => 'status',
            'org_id_column'  => 'org_id',
        ],
    ];

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            self::register_default_machines();

            add_action('wp_ajax_orabooks_workflow_transitions', [self::$instance, 'ajax_get_transitions']);
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
                    'submit'  => ['from' => 'draft', 'to' => 'review_pending'],
                    'approve' => ['from' => 'review_pending', 'to' => 'approved'],
                    'reject'  => ['from' => 'review_pending', 'to' => 'draft'],
                    'post'    => ['from' => 'approved', 'to' => 'posted'],
                    'lock'    => ['from' => 'posted', 'to' => 'locked'],
                    'reverse' => ['from' => ['posted', 'locked'], 'to' => 'reversed'],
                    'edit'    => ['from' => ['draft', 'approved'], 'to' => 'draft'],
                ],
            ],
            'invoice' => [
                'states' => ['draft', 'sent', 'posted', 'cancelled'],
                'transitions' => [
                    'send'     => ['from' => 'draft', 'to' => 'sent'],
                    'post'     => ['from' => ['draft', 'sent'], 'to' => 'posted'],
                    'cancel'   => ['from' => ['draft', 'sent'], 'to' => 'cancelled'],
                ],
            ],
            'bill' => [
                'states' => ['draft', 'submitted', 'approved', 'posted', 'void'],
                'transitions' => [
                    'submit'  => ['from' => 'draft', 'to' => 'submitted'],
                    'approve' => ['from' => 'submitted', 'to' => 'approved'],
                    'post'    => ['from' => ['approved', 'submitted'], 'to' => 'posted'],
                    'void'    => ['from' => ['draft', 'submitted', 'approved'], 'to' => 'void'],
                ],
            ],
            'expense' => [
                'states' => ['draft', 'submitted', 'ai_review', 'approved', 'posted', 'locked'],
                'transitions' => [
                    'submit'  => ['from' => 'draft', 'to' => 'submitted'],
                    'ai_review' => ['from' => ['draft', 'submitted'], 'to' => 'ai_review'],
                    'approve' => ['from' => ['submitted', 'ai_review'], 'to' => 'approved'],
                    'post'    => ['from' => 'approved', 'to' => 'posted'],
                    'lock'    => ['from' => 'posted', 'to' => 'locked'],
                ],
            ],
            'commission' => [
                'states' => ['pending', 'verified', 'paid', 'blocked'],
                'transitions' => [
                    'verify' => ['from' => 'pending', 'to' => 'verified'],
                    'pay'    => ['from' => 'verified', 'to' => 'paid'],
                    'block'  => ['from' => ['pending', 'verified'], 'to' => 'blocked'],
                ],
            ],
        ]);
    }

    public static function get_machines() {
        if (empty(self::$machines)) {
            self::register_default_machines();
        }
        return self::$machines;
    }

    /**
     * Validate whether an event can fire from the current state.
     *
     * @return true|WP_Error
     */
    public static function validate_transition($record_type, $current_state, $event) {
        $machines = self::get_machines();
        $sm = $machines[$record_type] ?? null;

        if (!$sm) {
            return new WP_Error('invalid_type', __('Unknown record type', 'orabooks'));
        }

        $transition = $sm['transitions'][$event] ?? null;
        if (!$transition) {
            return new WP_Error('invalid_event', __('Unknown event for this record type', 'orabooks'));
        }

        $from_states = is_array($transition['from']) ? $transition['from'] : [$transition['from']];
        if (!in_array($current_state, $from_states, true)) {
            orabooks_log_event('invalid_state_transition', sprintf(
                'Invalid transition %s on %s from state %s',
                $event,
                $record_type,
                $current_state
            ), 'warning', [
                'record_type'   => $record_type,
                'event'         => $event,
                'current_state' => $current_state,
                'allowed_from'  => $from_states,
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
        $user_id = isset($context['user_id']) ? (int) $context['user_id'] : null;
        $reason = $context['reason'] ?? null;
        $metadata = $context['metadata'] ?? null;
        $update_status = array_key_exists('update_status', $context) ? (bool) $context['update_status'] : true;
        $org_id = isset($context['org_id']) ? (int) $context['org_id'] : null;

        $map = self::$record_map[$record_type] ?? null;
        if (!$map) {
            return new WP_Error('invalid_type', __('Unknown record type', 'orabooks'));
        }

        $table = OraBooks_Database::table($map['table']);
        $status_col = $map['status_column'];
        $org_col = $map['org_id_column'];

        $sql = "SELECT * FROM {$table} WHERE id = %d";
        $params = [$record_id];
        if ($org_id) {
            $sql .= " AND {$org_col} = %d";
            $params[] = $org_id;
        }
        $sql .= ' FOR UPDATE';

        $record = $wpdb->get_row($wpdb->prepare($sql, ...$params));
        if (!$record) {
            return new WP_Error('not_found', __('Record not found', 'orabooks'));
        }

        $current_state = $record->{$status_col};
        $validation = self::validate_transition($record_type, $current_state, $event);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $machines = self::get_machines();
        $to_state = $machines[$record_type]['transitions'][$event]['to'];

        if ($update_status) {
            $wpdb->update(
                $table,
                [$status_col => $to_state],
                ['id' => $record_id],
                ['%s'],
                ['%d']
            );
        }

        $transition_id = self::persist_transition(
            $record_type,
            $record_id,
            $current_state,
            $to_state,
            $event,
            $user_id,
            $reason,
            $metadata
        );

        self::publish_transition_event($record_type, $record_id, $current_state, $to_state, $event, $context, $record);

        orabooks_log_event('state_changed', sprintf(
            '%s #%d transitioned %s → %s via %s',
            $record_type,
            $record_id,
            $current_state,
            $to_state,
            $event
        ), 'info', [
            'record_type'    => $record_type,
            'record_id'      => $record_id,
            'from_state'     => $current_state,
            'to_state'       => $to_state,
            'event'          => $event,
            'transition_id'  => $transition_id,
        ], $user_id, $org_id ?: (int) ($record->{$org_col} ?? 0));

        return [
            'transition_id' => $transition_id,
            'from_state'    => $current_state,
            'to_state'      => $to_state,
            'event'         => $event,
        ];
    }

    /**
     * Log a transition after the caller has already updated status (SL-001 compat).
     */
    public static function record_transition($record_type, $record_id, $event, $user_id, $reason = null, $metadata = null) {
        return self::transition($record_type, $record_id, $event, [
            'user_id'       => $user_id,
            'reason'        => $reason,
            'metadata'      => $metadata,
            'update_status' => false,
        ]);
    }

    /**
     * Fetch transition history for a record.
     */
    public static function get_transitions($record_type, $record_id, $limit = 50) {
        global $wpdb;

        $table = OraBooks_Database::table('state_machine_transitions');
        $limit = max(1, min(200, (int) $limit));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE record_type = %s AND record_id = %d
             ORDER BY id DESC
             LIMIT %d",
            sanitize_text_field($record_type),
            (int) $record_id,
            $limit
        ));
    }

    public static function format_transition_row($row) {
        if (!$row) {
            return null;
        }

        return [
            'id'           => (int) $row->id,
            'record_type'  => $row->record_type,
            'record_id'    => (int) $row->record_id,
            'from_state'   => $row->from_state,
            'to_state'     => $row->to_state,
            'event'        => $row->event,
            'triggered_by' => $row->triggered_by ? (int) $row->triggered_by : null,
            'reason'       => $row->reason,
            'created_at'   => $row->created_at,
        ];
    }

    public function ajax_get_transitions() {
        $user_id = orabooks_get_current_user_id();
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

        $rows = self::get_transitions($record_type, $record_id);
        orabooks_json_success([
            'transitions' => array_map([self::class, 'format_transition_row'], $rows ?: []),
        ]);
    }

    private static function persist_transition($record_type, $record_id, $from_state, $to_state, $event, $user_id, $reason, $metadata) {
        global $wpdb;

        $table = OraBooks_Database::table('state_machine_transitions');
        $wpdb->insert($table, [
            'record_type'  => sanitize_text_field($record_type),
            'record_id'    => (int) $record_id,
            'from_state'   => sanitize_text_field($from_state),
            'to_state'     => sanitize_text_field($to_state),
            'event'        => sanitize_text_field($event),
            'triggered_by' => $user_id ? (int) $user_id : null,
            'reason'       => $reason ? sanitize_textarea_field($reason) : null,
            'metadata'     => !empty($metadata) ? wp_json_encode($metadata) : null,
        ], ['%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    private static function publish_transition_event($record_type, $record_id, $from_state, $to_state, $event, $context, $record) {
        if (!function_exists('orabooks_publish_event')) {
            return;
        }

        $org_id = (int) ($context['org_id'] ?? ($record->org_id ?? 0));

        orabooks_publish_event('state_transition', (int) $record_id, [
            'record_type'  => $record_type,
            'record_id'    => (int) $record_id,
            'org_id'       => $org_id,
            'from_state'   => $from_state,
            'to_state'     => $to_state,
            'event'        => $event,
            'triggered_by' => $context['user_id'] ?? null,
            'service_name' => $context['service_name'] ?? 'orabooks_workflow',
            'reason'       => $context['reason'] ?? null,
            'metadata'     => $context['metadata'] ?? null,
        ]);
    }
}
