<?php
/**
 * SL-301 Phase 3 — Workflow integrations (preconditions, events, observability hooks).
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Workflow_Integration {

    public static function init() {
        add_filter('orabooks_workflow_preconditions', [__CLASS__, 'apply_preconditions'], 10, 5);
        add_action('orabooks_workflow_after_transition', [__CLASS__, 'on_after_transition'], 10, 6);
    }

    /**
     * Central RBAC + fiscal preconditions for workflow transitions.
     *
     * @return true|WP_Error
     */
    public static function apply_preconditions($result, $record_type, $event, $record, $context) {
        if ($result !== true || is_wp_error($result)) {
            return $result;
        }

        if (!empty($context['skip_preconditions'])) {
            return true;
        }

        $user_id = (int) ($context['user_id'] ?? 0);
        $org_id = (int) ($context['org_id'] ?? ($record->org_id ?? 0));

        if ($record_type === 'journal') {
            $journal_check = self::journal_preconditions($event, $record, $user_id, $org_id, $context);
            if (is_wp_error($journal_check)) {
                return $journal_check;
            }
        }

        if ($record_type === 'expense') {
            $expense_check = self::expense_preconditions($event, $user_id, $org_id);
            if (is_wp_error($expense_check)) {
                return $expense_check;
            }
        }

        if (in_array($record_type, ['invoice', 'bill'], true)) {
            $ap_ar_check = self::invoice_bill_preconditions($record_type, $event, $user_id, $org_id);
            if (is_wp_error($ap_ar_check)) {
                return $ap_ar_check;
            }
        }

        return true;
    }

    /**
     * @return true|WP_Error
     */
    private static function journal_preconditions($event, $record, $user_id, $org_id, $context) {
        if ($user_id <= 0 && !in_array($event, ['lock'], true)) {
            return new WP_Error('auth_required', __('Authentication required for journal transition', 'orabooks'), ['status' => 401]);
        }

        switch ($event) {
            case 'submit':
            case 'edit':
                if (!self::has_permission($user_id, $org_id, 'submit_transaction')) {
                    return self::deny('submit_transaction');
                }
                break;

            case 'approve':
                if (!self::has_permission($user_id, $org_id, 'approve_journal')
                    && !(class_exists('OraBooks_Approval') && OraBooks_Approval::user_can_approve($user_id, $org_id))) {
                    return self::deny('approve_journal');
                }

                if (class_exists('OraBooks_Approval')) {
                    $policy = OraBooks_Approval::get_policy($org_id);
                    if ($policy && !empty($policy->maker_checker_required) && (int) ($record->created_by ?? 0) === $user_id) {
                        return new WP_Error('maker_checker', __('Creator cannot approve own journal', 'orabooks'), ['status' => 403]);
                    }
                    if ($policy) {
                        $mfa_check = OraBooks_Approval::verify_mfa_for_high_value(
                            $user_id,
                            (float) ($record->total_amount ?? 0),
                            $policy,
                            is_array($context) ? $context : []
                        );
                        if (is_wp_error($mfa_check)) {
                            return $mfa_check;
                        }
                    }
                }
                break;

            case 'reject':
                if (class_exists('OraBooks_Approval') && OraBooks_Approval::user_can_approve($user_id, $org_id)) {
                    break;
                }
                if (!self::has_permission($user_id, $org_id, 'approve_journal')) {
                    return self::deny('approve_journal');
                }
                break;

            case 'post':
                if (!self::has_permission($user_id, $org_id, 'submit_transaction')
                    && !self::has_permission($user_id, $org_id, 'approve_journal')) {
                    return self::deny('submit_transaction');
                }
                if (class_exists('OraBooks_Fiscal') && method_exists('OraBooks_Fiscal', 'can_post')) {
                    $fiscal = OraBooks_Fiscal::can_post($org_id, $record->transaction_date ?? current_time('Y-m-d'));
                    if (is_wp_error($fiscal)) {
                        return $fiscal;
                    }
                }
                break;

            case 'reverse':
                if (!self::has_permission($user_id, $org_id, 'reverse_journal')) {
                    return self::deny('reverse_journal');
                }
                break;

            case 'lock':
                // Internal step after post — no extra RBAC.
                break;
        }

        return true;
    }

    /**
     * @return true|WP_Error
     */
    private static function expense_preconditions($event, $user_id, $org_id) {
        if ($user_id <= 0) {
            return new WP_Error('auth_required', __('Authentication required', 'orabooks'), ['status' => 401]);
        }

        if (in_array($event, ['submit', 'ai_review'], true)) {
            if (!self::has_permission($user_id, $org_id, 'manage_expenses')) {
                return self::deny('manage_expenses');
            }
        }

        if (in_array($event, ['approve', 'reject'], true)) {
            if (!self::has_permission($user_id, $org_id, 'approve_expense')) {
                return self::deny('approve_expense');
            }
        }

        if ($event === 'post') {
            if (!self::has_permission($user_id, $org_id, 'manage_expenses')
                && !self::has_permission($user_id, $org_id, 'approve_expense')) {
                return self::deny('manage_expenses');
            }
        }

        if ($event === 'lock') {
            // Internal step after post — no extra RBAC.
        }

        return true;
    }

    /**
     * @return true|WP_Error
     */
    private static function invoice_bill_preconditions($record_type, $event, $user_id, $org_id) {
        if ($user_id <= 0 && $event !== 'post') {
            return new WP_Error('auth_required', __('Authentication required', 'orabooks'), ['status' => 401]);
        }

        $perm = $record_type === 'invoice' ? 'create_invoice' : 'manage_settings';

        if (in_array($event, ['send', 'submit', 'approve', 'void', 'cancel'], true)) {
            if (!self::has_permission($user_id, $org_id, $perm)
                && !self::has_permission($user_id, $org_id, 'manage_settings')) {
                return self::deny($perm);
            }
        }

        if ($event === 'post') {
            if (!self::has_permission($user_id, $org_id, 'manage_settings')
                && !self::has_permission($user_id, $org_id, $perm)) {
                return self::deny('manage_settings');
            }
        }

        return true;
    }

    public static function on_after_transition($record_type, $record_id, $event, $result, $record, $context) {
        if (class_exists('OraBooks_Observability')) {
            $org_id = (int) ($result['org_id'] ?? ($context['org_id'] ?? ($record->org_id ?? 0)));
            OraBooks_Observability::record_metric('workflow', 'transition_success_count', 1, $org_id ?: null, [
                'record_type' => $record_type,
                'event'         => $event,
                'to_state'      => $result['to_state'] ?? '',
            ]);
        }
    }

    public static function track_failure($record_type, $event, $org_id, $reason, $context = []) {
        orabooks_log_event('workflow_transition_failed', sprintf(
            'Workflow transition failed: %s/%s — %s',
            $record_type,
            $event,
            $reason
        ), 'warning', [
            'record_type' => $record_type,
            'event'       => $event,
            'reason'      => $reason,
            'context'     => $context,
        ], $context['user_id'] ?? null, $org_id ?: null);

        if (class_exists('OraBooks_Observability')) {
            OraBooks_Observability::record_metric('workflow', 'transition_failure_count', 1, $org_id ?: null, [
                'record_type' => $record_type,
                'event'         => $event,
                'reason'        => $reason,
            ]);
        }
    }

    private static function has_permission($user_id, $org_id, $permission) {
        if ($user_id <= 0 || $org_id <= 0) {
            return false;
        }

        if (function_exists('current_user_can') && current_user_can('manage_options')) {
            return true;
        }

        return orabooks_has_permission($user_id, $org_id, $permission);
    }

    private static function deny($permission) {
        return new WP_Error(
            'precondition_failed',
            sprintf(__('Permission denied: %s', 'orabooks'), $permission),
            ['status' => 403, 'permission' => $permission]
        );
    }
}
