<?php
/**
 * OraBooks Journal Approval Gate (SL-002)
 *
 * Human approval workflow: maker-checker, MFA thresholds, delegation,
 * expiry, escalation, append-only history, and outbox events.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Approval {

    private static $instance = null;

    public static function init() {
        if (self::$instance !== null) {
            return self::$instance;
        }

        self::$instance = new self();

        add_action('orabooks_approval_expire_stale', [__CLASS__, 'cron_expire_stale_approvals']);
        add_action('orabooks_approval_escalate_overdue', [__CLASS__, 'cron_escalate_overdue_reviews']);
        add_action('orabooks_approval_expiry_reminders', [__CLASS__, 'cron_expiry_reminders']);

        add_action('wp_ajax_orabooks_resubmit_journal', [self::$instance, 'ajax_resubmit_journal']);
        add_action('wp_ajax_nopriv_orabooks_resubmit_journal', [self::$instance, 'ajax_resubmit_journal']);
        add_action('wp_ajax_orabooks_update_journal_draft', [self::$instance, 'ajax_update_journal_draft']);
        add_action('wp_ajax_nopriv_orabooks_update_journal_draft', [self::$instance, 'ajax_update_journal_draft']);
        add_action('wp_ajax_orabooks_approval_policy_get', [self::$instance, 'ajax_policy_get']);
        add_action('wp_ajax_nopriv_orabooks_approval_policy_get', [self::$instance, 'ajax_policy_get']);
        add_action('wp_ajax_orabooks_approval_policy_save', [self::$instance, 'ajax_policy_save']);
        add_action('wp_ajax_nopriv_orabooks_approval_policy_save', [self::$instance, 'ajax_policy_save']);
        add_action('wp_ajax_orabooks_approval_delegation_create', [self::$instance, 'ajax_delegation_create']);
        add_action('wp_ajax_nopriv_orabooks_approval_delegation_create', [self::$instance, 'ajax_delegation_create']);
        add_action('wp_ajax_orabooks_approval_delegation_revoke', [self::$instance, 'ajax_delegation_revoke']);
        add_action('wp_ajax_nopriv_orabooks_approval_delegation_revoke', [self::$instance, 'ajax_delegation_revoke']);
        add_action('wp_ajax_orabooks_approval_delegations_list', [self::$instance, 'ajax_delegations_list']);
        add_action('wp_ajax_nopriv_orabooks_approval_delegations_list', [self::$instance, 'ajax_delegations_list']);

        return self::$instance;
    }

    public static function ensure_policy($org_id) {
        global $wpdb;

        $org_id = (int) $org_id;
        if ($org_id <= 0) {
            return null;
        }

        $table = OraBooks_Database::table('approval_policies');
        $policy = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d",
            $org_id
        ));

        if ($policy) {
            return $policy;
        }

        $wpdb->insert($table, ['org_id' => $org_id], ['%d']);
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d",
            $org_id
        ));
    }

    public static function get_policy($org_id) {
        return self::ensure_policy($org_id);
    }

    public static function save_policy($org_id, array $data) {
        global $wpdb;

        self::ensure_policy($org_id);
        $table = OraBooks_Database::table('approval_policies');

        $allowed = [
            'approval_expiry_hours',
            'reminder_hours_before_expiry',
            'max_approval_rounds',
            'maker_checker_required',
            'mfa_amount_threshold',
            'escalation_after_hours',
            'escalation_role',
        ];

        $update = [];
        $formats = [];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            if (in_array($field, ['maker_checker_required'], true)) {
                $update[$field] = !empty($data[$field]) ? 1 : 0;
                $formats[] = '%d';
            } elseif (in_array($field, ['approval_expiry_hours', 'reminder_hours_before_expiry', 'max_approval_rounds', 'escalation_after_hours'], true)) {
                $update[$field] = (int) $data[$field];
                $formats[] = '%d';
            } elseif ($field === 'mfa_amount_threshold') {
                $update[$field] = (float) $data[$field];
                $formats[] = '%f';
            } else {
                $update[$field] = sanitize_text_field((string) $data[$field]);
                $formats[] = '%s';
            }
        }

        if (empty($update)) {
            return self::get_policy($org_id);
        }

        if (!empty($update['escalation_role']) && !in_array($update['escalation_role'], ['admin', 'owner'], true)) {
            return new WP_Error('invalid_escalation_role', 'escalation_role must be admin or owner');
        }

        $wpdb->update($table, $update, ['org_id' => (int) $org_id], $formats, ['%d']);
        return self::get_policy($org_id);
    }

    public static function user_can_approve($user_id, $org_id) {
        if (OraBooks_RBAC::require_permission($user_id, $org_id, 'approve_journal')) {
            return true;
        }

        return self::has_active_delegation($user_id, $org_id) !== null;
    }

    public static function has_active_delegation($delegate_user_id, $org_id) {
        global $wpdb;

        $table = OraBooks_Database::table('approval_delegations');
        $now = gmdate('Y-m-d H:i:s');

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE org_id = %d AND delegate_user_id = %d
               AND revoked_at IS NULL
               AND starts_at <= %s AND ends_at >= %s
             ORDER BY ends_at DESC LIMIT 1",
            (int) $org_id,
            (int) $delegate_user_id,
            $now,
            $now
        ));
    }

    public static function create_delegation($org_id, $delegator_user_id, $delegate_user_id, $starts_at, $ends_at, $created_by) {
        global $wpdb;

        if ((int) $delegator_user_id === (int) $delegate_user_id) {
            return new WP_Error('invalid_delegate', 'Delegator and delegate must be different users');
        }

        if (strtotime($ends_at) <= strtotime($starts_at)) {
            return new WP_Error('invalid_range', 'Delegation end must be after start');
        }

        if (!OraBooks_RBAC::require_permission($created_by, $org_id, 'manage_org_settings')) {
            return new WP_Error('permission_denied', 'Permission denied');
        }

        $table = OraBooks_Database::table('approval_delegations');
        $wpdb->insert($table, [
            'org_id'             => (int) $org_id,
            'delegator_user_id'  => (int) $delegator_user_id,
            'delegate_user_id'   => (int) $delegate_user_id,
            'starts_at'          => $starts_at,
            'ends_at'            => $ends_at,
            'created_by'         => (int) $created_by,
        ], ['%d', '%d', '%d', '%s', '%s', '%d']);

        $id = (int) $wpdb->insert_id;
        orabooks_log_event('approval_delegation_created', 'Approval delegation created', 'info', [
            'delegation_id' => $id,
            'org_id'        => (int) $org_id,
            'delegate_user_id' => (int) $delegate_user_id,
        ], $created_by, $org_id);

        return ['id' => $id];
    }

    public static function revoke_delegation($delegation_id, $user_id, $org_id) {
        global $wpdb;

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_org_settings')) {
            return new WP_Error('permission_denied', 'Permission denied');
        }

        $table = OraBooks_Database::table('approval_delegations');
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND org_id = %d",
            (int) $delegation_id,
            (int) $org_id
        ));

        if (!$row) {
            return new WP_Error('not_found', 'Delegation not found');
        }

        if ($row->revoked_at) {
            return true;
        }

        $wpdb->update(
            $table,
            ['revoked_at' => gmdate('Y-m-d H:i:s')],
            ['id' => (int) $delegation_id],
            ['%s'],
            ['%d']
        );

        orabooks_log_event('approval_delegation_revoked', 'Approval delegation revoked', 'info', [
            'delegation_id' => (int) $delegation_id,
        ], $user_id, $org_id);

        return true;
    }

    public static function list_delegations($org_id, $include_revoked = false) {
        global $wpdb;

        $table = OraBooks_Database::table('approval_delegations');
        $sql = "SELECT * FROM {$table} WHERE org_id = %d";
        if (!$include_revoked) {
            $sql .= ' AND revoked_at IS NULL';
        }
        $sql .= ' ORDER BY created_at DESC';

        return $wpdb->get_results($wpdb->prepare($sql, (int) $org_id));
    }

    public static function format_delegation($row) {
        return [
            'id'                => (int) $row->id,
            'org_id'            => (int) $row->org_id,
            'delegator_user_id' => (int) $row->delegator_user_id,
            'delegate_user_id'  => (int) $row->delegate_user_id,
            'starts_at'         => $row->starts_at,
            'ends_at'           => $row->ends_at,
            'created_by'        => (int) $row->created_by,
            'revoked_at'        => $row->revoked_at,
            'created_at'        => $row->created_at,
        ];
    }

    public static function verify_mfa_for_high_value($user_id, $amount, $policy, array $args = []) {
        $threshold = (float) ($policy->mfa_amount_threshold ?? 10000);
        if ($amount < $threshold) {
            return true;
        }

        if (!empty($args['mfa_verified'])) {
            return true;
        }

        $otp = OraBooks_Secrets::normalize_totp_code($args['mfa_otp'] ?? '');
        if ($otp === '') {
            return new WP_Error('mfa_required', 'High-value approval requires two-factor authentication');
        }

        $wp_user_id = function_exists('orabooks_get_wp_user_id_for_orabooks_user')
            ? orabooks_get_wp_user_id_for_orabooks_user((int) $user_id)
            : 0;
        if ($wp_user_id <= 0 && function_exists('orabooks_ensure_wp_user_link_for_orabooks_user')) {
            $wp_user_id = orabooks_ensure_wp_user_link_for_orabooks_user((int) $user_id);
        }

        $secret = function_exists('orabooks_get_2fa_secret')
            ? orabooks_get_2fa_secret($wp_user_id)
            : get_user_meta((int) $wp_user_id, 'orabooks_2fa_secret', true);
        if (!$secret) {
            return new WP_Error('2fa_not_enabled', 'Two-factor authentication is not enabled for this user');
        }

        if (!class_exists('OraBooks_Secrets') || !OraBooks_Secrets::verify_totp($secret, $otp)) {
            return new WP_Error('mfa_invalid', 'Invalid two-factor authentication code');
        }

        return true;
    }

    public static function approve_journal($journal_id, $user_id, array $args = []) {
        global $wpdb;

        $table = OraBooks_Database::table('journals');
        $journal = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d FOR UPDATE",
            (int) $journal_id
        ));

        if (!$journal || $journal->status !== 'review_pending') {
            return new WP_Error('invalid_status', 'Journal not in review_pending');
        }

        if (!self::user_can_approve($user_id, (int) $journal->org_id)) {
            return new WP_Error('permission_denied', 'Permission denied');
        }

        $policy = self::get_policy((int) $journal->org_id);
        if ($policy && !empty($policy->maker_checker_required) && (int) $journal->created_by === (int) $user_id) {
            return new WP_Error('maker_checker', 'Creator cannot approve own journal');
        }

        $current_hash = self::compute_snapshot_hash((int) $journal_id);
        $expires_at = gmdate('Y-m-d H:i:s', time() + ((int) ($policy->approval_expiry_hours ?? 72) * 3600));

        $transition = OraBooks_Workflow::transition('journal', (int) $journal_id, 'approve', [
            'user_id' => (int) $user_id,
            'org_id' => (int) $journal->org_id,
            'mfa_otp' => $args['mfa_otp'] ?? null,
            'mfa_verified' => !empty($args['mfa_verified']),
            'row_updates' => [
                'approved_by' => (int) $user_id,
                'approved_at' => gmdate('Y-m-d H:i:s'),
                'approved_snapshot_hash' => $current_hash,
                'approval_expires_at' => $expires_at,
                'approval_stale' => 0,
                'lock_after_approval' => 1,
            ],
        ]);
        if (is_wp_error($transition)) {
            return $transition;
        }

        self::record_history(
            (int) $journal_id,
            'approve',
            (int) $user_id,
            $current_hash,
            (int) $journal->approval_round,
            (int) $journal->revision_number
        );

        self::publish_event('journal_approved', (int) $journal_id, [
            'org_id'     => (int) $journal->org_id,
            'journal_id' => (int) $journal_id,
            'amount'     => (float) $journal->total_amount,
        ]);

        orabooks_log_event('journal_approved', "Journal #$journal_id approved by user $user_id", 'info', [
            'journal_id' => (int) $journal_id,
        ], $user_id, (int) $journal->org_id);

        if (class_exists('OraBooks_Ai_Review')) {
            OraBooks_Ai_Review::resolve_ai_review((int) $journal_id, (int) $journal->org_id, (int) $user_id);
        }

        return true;
    }

    public static function resubmit_journal($journal_id, $user_id) {
        global $wpdb;

        $table = OraBooks_Database::table('journals');
        $journal = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d FOR UPDATE",
            (int) $journal_id
        ));

        if (!$journal || $journal->status !== 'draft') {
            return new WP_Error('invalid_status', 'Only draft journals can be resubmitted');
        }

        if ((int) $journal->approval_round <= 0) {
            return new WP_Error('invalid_resubmit', 'Use submit for first-time approval');
        }

        $wpdb->update(
            $table,
            ['rejected_reason' => null],
            ['id' => (int) $journal_id],
            [null],
            ['%d']
        );

        self::record_history(
            (int) $journal_id,
            'resubmit',
            (int) $user_id,
            null,
            (int) $journal->approval_round,
            (int) $journal->revision_number,
            'Resubmitted after corrections'
        );

        if (!class_exists('OraBooks_Posting')) {
            return new WP_Error('posting_unavailable', 'Posting engine unavailable');
        }

        return OraBooks_Posting::promote_to_review_pending((int) $journal_id, (int) $user_id);
    }

    public static function invalidate_on_edit($journal_id, $user_id) {
        global $wpdb;

        $table = OraBooks_Database::table('journals');
        $journal = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d FOR UPDATE",
            (int) $journal_id
        ));

        if (!$journal || $journal->status !== 'approved') {
            return false;
        }

        $new_revision = (int) $journal->revision_number + 1;

        if (!class_exists('OraBooks_Workflow')) {
            return false;
        }

        $transition = OraBooks_Workflow::transition('journal', (int) $journal_id, 'edit', [
            'user_id' => (int) $user_id,
            'org_id' => (int) $journal->org_id,
            'row_updates' => [
                'approved_snapshot_hash' => null,
                'approved_by' => null,
                'approved_at' => null,
                'approval_expires_at' => null,
                'lock_after_approval' => 0,
                'approval_stale' => 1,
                'revision_number' => $new_revision,
            ],
        ]);
        if (is_wp_error($transition)) {
            return false;
        }

        self::record_history(
            (int) $journal_id,
            'invalidate',
            (int) $user_id,
            null,
            (int) $journal->approval_round,
            $new_revision,
            'Approval invalidated by edit'
        );

        self::publish_event('journal_approval_invalidated', (int) $journal_id, [
            'org_id' => (int) $journal->org_id,
        ]);

        return true;
    }

    public static function validate_submit_rounds($journal, $policy) {
        $max = (int) ($policy->max_approval_rounds ?? 5);
        if ($max > 0 && (int) $journal->approval_round >= $max) {
            return new WP_Error('max_rounds', 'Maximum approval rounds exceeded');
        }
        return true;
    }

    public static function on_submitted($journal_id, $user_id, $org_id, $round) {
        self::publish_event('journal_submitted', (int) $journal_id, [
            'org_id' => (int) $org_id,
            'round'  => (int) $round,
        ]);
    }

    public static function on_rejected($journal_id, $user_id, $org_id, $reason) {
        self::publish_event('journal_rejected', (int) $journal_id, [
            'org_id' => (int) $org_id,
            'reason' => $reason,
        ]);
    }

    public static function compute_snapshot_hash($journal_id) {
        global $wpdb;

        $table = OraBooks_Database::table('journals');
        $table_lines = OraBooks_Database::table('journal_lines');

        $journal = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $journal_id));
        if (!$journal) {
            return hash('sha256', 'missing-journal-' . (int) $journal_id);
        }

        $lines = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_lines} WHERE journal_id = %d ORDER BY id ASC",
            (int) $journal_id
        ));

        $metadata = [];
        if (!empty($journal->metadata)) {
            $decoded = json_decode($journal->metadata, true);
            if (is_array($decoded)) {
                unset($decoded['ai_suggestions'], $decoded['ai_confidence'], $decoded['classification']);
                $metadata = $decoded;
            }
        }

        $canonical_lines = [];
        foreach ($lines as $line) {
            $canonical_lines[] = [
                'account_id'  => (int) $line->account_id,
                'debit'       => number_format((float) $line->debit_amount, 2, '.', ''),
                'credit'      => number_format((float) $line->credit_amount, 2, '.', ''),
                'description' => (string) ($line->description ?? ''),
                'currency'    => (string) ($line->currency_code ?? 'USD'),
            ];
        }

        $data = [
            'journal_id'       => (int) $journal_id,
            'lines'            => $canonical_lines,
            'metadata'         => $metadata,
            'source_id'        => $journal->source_id ? (int) $journal->source_id : null,
            'source_type'      => (string) ($journal->source_type ?? ''),
            'transaction_date' => (string) $journal->transaction_date,
        ];
        ksort($data);

        return hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES));
    }

    public static function record_history($journal_id, $action, $user_id, $snapshot_hash = null, $round = 0, $revision = 1, $reason = null) {
        global $wpdb;

        $table = OraBooks_Database::table('journal_approval_history');
        $wpdb->insert($table, [
            'journal_id'      => (int) $journal_id,
            'action'          => $action,
            'performed_by'    => (int) $user_id,
            'snapshot_hash'   => $snapshot_hash,
            'approval_round'  => (int) $round,
            'revision_number' => (int) $revision,
            'reason'          => $reason,
        ], ['%d', '%s', '%d', '%s', '%d', '%d', '%s']);
    }

    public static function publish_event($event_type, $journal_id, array $payload) {
        if (class_exists('OraBooks_EventBus')) {
            OraBooks_EventBus::publish($event_type, (int) $journal_id, $payload);
            return;
        }

        global $wpdb;
        $table = OraBooks_Database::table('outbox_messages');
        $wpdb->insert($table, [
            'event_type'   => $event_type,
            'aggregate_id' => (int) $journal_id,
            'payload'      => wp_json_encode($payload),
        ], ['%s', '%d', '%s']);
    }

    public static function get_pending_queue($org_id, $sort = 'created_at', $order = 'ASC') {
        global $wpdb;

        $allowed_sort = [
            'created_at'   => 'created_at',
            'total_amount' => 'total_amount',
            'age'          => 'created_at',
        ];
        $column = $allowed_sort[$sort] ?? 'created_at';
        $direction = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        $table = OraBooks_Database::table('journals');
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE org_id = %d AND status = 'review_pending'
             ORDER BY {$column} {$direction}
             LIMIT 100",
            (int) $org_id
        ));
    }

    public static function cron_expire_stale_approvals() {
        global $wpdb;

        $table = OraBooks_Database::table('journals');
        $rows = $wpdb->get_results(
            "SELECT id, org_id, approval_round, revision_number, created_by
             FROM {$table}
             WHERE status = 'approved'
               AND approval_stale = 0
               AND approval_expires_at IS NOT NULL
               AND approval_expires_at < UTC_TIMESTAMP()"
        );

        foreach ($rows as $journal) {
            $wpdb->update(
                $table,
                ['approval_stale' => 1],
                ['id' => (int) $journal->id],
                ['%d'],
                ['%d']
            );

            self::record_history(
                (int) $journal->id,
                'expire',
                0,
                null,
                (int) $journal->approval_round,
                (int) $journal->revision_number,
                'Approval expired'
            );

            self::publish_event('journal_approval_expired', (int) $journal->id, [
                'org_id' => (int) $journal->org_id,
            ]);

            if (class_exists('OraBooks_Notifications') && (int) $journal->created_by > 0) {
                OraBooks_Notifications::notify(
                    (int) $journal->created_by,
                    'journal_approval_expired',
                    [
                        'journal_id' => (int) $journal->id,
                        'org_id'     => (int) $journal->org_id,
                    ],
                    (int) $journal->org_id
                );
            }
        }
    }

    public static function cron_expiry_reminders() {
        global $wpdb;

        $table_journals = OraBooks_Database::table('journals');
        $table_policies = OraBooks_Database::table('approval_policies');

        $rows = $wpdb->get_results(
            "SELECT j.id, j.org_id, j.approved_by, j.approval_expires_at, j.approval_round, j.revision_number,
                    p.reminder_hours_before_expiry
             FROM {$table_journals} j
             LEFT JOIN {$table_policies} p ON p.org_id = j.org_id
             WHERE j.status = 'approved'
               AND j.approval_stale = 0
               AND j.approval_expires_at IS NOT NULL
               AND j.approval_expires_at > UTC_TIMESTAMP()
               AND TIMESTAMPDIFF(HOUR, UTC_TIMESTAMP(), j.approval_expires_at) <= COALESCE(p.reminder_hours_before_expiry, 24)"
        );

        foreach ($rows as $journal) {
            if (class_exists('OraBooks_Notifications') && (int) $journal->approved_by > 0) {
                OraBooks_Notifications::notify(
                    (int) $journal->approved_by,
                    'journal_approval_expiring',
                    [
                        'journal_id' => (int) $journal->id,
                        'org_id'     => (int) $journal->org_id,
                        'expires_at' => $journal->approval_expires_at,
                    ],
                    (int) $journal->org_id
                );
            }
        }
    }

    public static function cron_escalate_overdue_reviews() {
        global $wpdb;

        $table_journals = OraBooks_Database::table('journals');
        $table_policies = OraBooks_Database::table('approval_policies');

        $rows = $wpdb->get_results(
            "SELECT j.id, j.org_id, j.approval_round, j.revision_number, j.last_submitted_at,
                    p.escalation_after_hours, p.escalation_role
             FROM {$table_journals} j
             LEFT JOIN {$table_policies} p ON p.org_id = j.org_id
             WHERE j.status = 'review_pending'
               AND j.last_submitted_at IS NOT NULL
               AND TIMESTAMPDIFF(HOUR, j.last_submitted_at, UTC_TIMESTAMP()) >= COALESCE(p.escalation_after_hours, 48)"
        );

        foreach ($rows as $journal) {
            self::record_history(
                (int) $journal->id,
                'escalate',
                0,
                null,
                (int) $journal->approval_round,
                (int) $journal->revision_number,
                'Overdue review_pending escalation'
            );

            self::publish_event('journal_approval_escalated', (int) $journal->id, [
                'org_id'          => (int) $journal->org_id,
                'escalation_role' => $journal->escalation_role ?? 'admin',
            ]);

            if (class_exists('OraBooks_Notifications')) {
                self::notify_org_admins(
                    (int) $journal->org_id,
                    'journal_approval_escalated',
                    [
                        'journal_id' => (int) $journal->id,
                        'org_id'     => (int) $journal->org_id,
                    ]
                );
            }
        }
    }

    private static function notify_org_admins($org_id, $event_type, array $payload) {
        if (!class_exists('OraBooks_Notifications')) {
            return;
        }

        global $wpdb;
        $table_user_org = OraBooks_Database::table('user_org');
        $admins = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id FROM {$table_user_org} WHERE org_id = %d AND role IN ('owner', 'admin')",
            (int) $org_id
        ));

        foreach ($admins ?: [] as $admin) {
            OraBooks_Notifications::notify((int) $admin->user_id, $event_type, $payload, (int) $org_id);
        }
    }

    public static function install_history_guards() {
        global $wpdb;

        $table = OraBooks_Database::table('journal_approval_history');
        $trigger_update = $wpdb->prefix . 'orabooks_prevent_approval_history_update';
        $trigger_delete = $wpdb->prefix . 'orabooks_prevent_approval_history_delete';

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS
             WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = %s",
            $trigger_update
        ));

        if ($exists) {
            return;
        }

        $wpdb->query('DROP TRIGGER IF EXISTS ' . $trigger_update);
        $wpdb->query('DROP TRIGGER IF EXISTS ' . $trigger_delete);

        $wpdb->query(
            "CREATE TRIGGER {$trigger_update} BEFORE UPDATE ON {$table}
             FOR EACH ROW
             SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'UPDATE not allowed on approval history'"
        );

        $wpdb->query(
            "CREATE TRIGGER {$trigger_delete} BEFORE DELETE ON {$table}
             FOR EACH ROW
             SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DELETE not allowed on approval history'"
        );
    }

    private function require_journal_org($user_id, $journal_id) {
        global $wpdb;

        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $table = OraBooks_Database::table('journals');
        $journal = $wpdb->get_row($wpdb->prepare(
            "SELECT org_id FROM {$table} WHERE id = %d",
            (int) $journal_id
        ));

        if (!$journal) {
            orabooks_json_error('Journal not found', 404);
        }

        $isolation = OraBooks_Auth::require_customer_org($user_id, (int) $journal->org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }

        return (int) $journal->org_id;
    }

    public function ajax_resubmit_journal() {
        $user_id = orabooks_get_current_user_id();
        $journal_id = (int) ($_POST['journal_id'] ?? 0);
        $org_id = $this->require_journal_org($user_id, $journal_id);

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'submit_transaction')) {
            orabooks_json_error('Permission denied', 403);
        }

        $result = self::resubmit_journal($journal_id, $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success([], 'Journal resubmitted for approval');
    }

    public function ajax_update_journal_draft() {
        $user_id = orabooks_get_current_user_id();
        $journal_id = (int) ($_POST['journal_id'] ?? 0);
        $org_id = $this->require_journal_org($user_id, $journal_id);

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'submit_transaction')) {
            orabooks_json_error('Permission denied', 403);
        }

        if (!class_exists('OraBooks_Posting')) {
            orabooks_json_error('Posting engine unavailable', 500);
        }

        $journal = OraBooks_Posting::get_journal($journal_id, $org_id);
        if (!$journal) {
            orabooks_json_error('Journal not found', 404);
        }

        if (!in_array($journal->status, ['draft', 'approved'], true)) {
            orabooks_json_error('Only draft or approved journals can be edited', 400);
        }

        if ($journal->status === 'approved') {
            self::invalidate_on_edit($journal_id, $user_id);
            $journal = OraBooks_Posting::get_journal($journal_id, $org_id);
        } elseif ($journal->status === 'draft') {
            global $wpdb;
            $table = OraBooks_Database::table('journals');
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET revision_number = revision_number + 1 WHERE id = %d",
                $journal_id
            ));
        }

        if (!empty($_POST['transaction_date'])) {
            global $wpdb;
            $table = OraBooks_Database::table('journals');
            $wpdb->update(
                $table,
                ['transaction_date' => sanitize_text_field($_POST['transaction_date'])],
                ['id' => $journal_id],
                ['%s'],
                ['%d']
            );
        }

        if (!empty($_POST['lines'])) {
            $lines = json_decode(stripslashes((string) $_POST['lines']), true);
            if (!is_array($lines)) {
                orabooks_json_error('Invalid lines payload', 400);
            }

            global $wpdb;
            $table_lines = OraBooks_Database::table('journal_lines');
            $wpdb->delete($table_lines, ['journal_id' => $journal_id], ['%d']);

            $add = OraBooks_Posting::add_lines($journal_id, $lines);
            if (is_wp_error($add)) {
                orabooks_json_error($add->get_error_message(), 400);
            }
        }

        orabooks_json_success([
            'journal' => OraBooks_Posting::format_journal(OraBooks_Posting::get_journal($journal_id, $org_id)),
        ], 'Journal updated');
    }

    public function ajax_policy_get() {
        $user_id = orabooks_get_current_user_id();
        $org_id = (int) ($_GET['org_id'] ?? $_POST['org_id'] ?? 0);

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_org_settings')) {
            orabooks_json_error('Permission denied', 403);
        }

        $policy = self::get_policy($org_id);
        orabooks_json_success(['policy' => $policy]);
    }

    public function ajax_policy_save() {
        $user_id = orabooks_get_current_user_id();
        $org_id = (int) ($_POST['org_id'] ?? 0);

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_org_settings')) {
            orabooks_json_error('Permission denied', 403);
        }

        $result = self::save_policy($org_id, $_POST);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success(['policy' => $result], 'Approval policy saved');
    }

    public function ajax_delegation_create() {
        $user_id = orabooks_get_current_user_id();
        $org_id = (int) ($_POST['org_id'] ?? 0);

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }

        $result = self::create_delegation(
            $org_id,
            (int) ($_POST['delegator_user_id'] ?? $user_id),
            (int) ($_POST['delegate_user_id'] ?? 0),
            sanitize_text_field($_POST['starts_at'] ?? gmdate('Y-m-d H:i:s')),
            sanitize_text_field($_POST['ends_at'] ?? gmdate('Y-m-d H:i:s', time() + (7 * DAY_IN_SECONDS))),
            $user_id
        );

        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success($result, 'Delegation created');
    }

    public function ajax_delegation_revoke() {
        $user_id = orabooks_get_current_user_id();
        $org_id = (int) ($_POST['org_id'] ?? 0);
        $delegation_id = (int) ($_POST['delegation_id'] ?? 0);

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }

        $result = self::revoke_delegation($delegation_id, $user_id, $org_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success([], 'Delegation revoked');
    }

    public function ajax_delegations_list() {
        $user_id = orabooks_get_current_user_id();
        $org_id = (int) ($_GET['org_id'] ?? $_POST['org_id'] ?? 0);

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_org_settings')) {
            orabooks_json_error('Permission denied', 403);
        }

        $rows = self::list_delegations($org_id);
        orabooks_json_success([