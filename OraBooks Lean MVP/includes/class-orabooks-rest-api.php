<?php
/**
 * OraBooks REST API + OpenAPI discovery (SL-304, SL-028)
 *
 * Registers /wp-json/api/* routes documented in docs/openapi/openapi.json.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Rest_Api {

    const NAMESPACE = 'api';

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        register_rest_route(self::NAMESPACE, '/openapi.json', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'rest_openapi'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/pwa/manifest', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [class_exists('OraBooks_Pwa') ? 'OraBooks_Pwa' : __CLASS__, 'rest_manifest'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/pwa/service-worker', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [class_exists('OraBooks_Pwa') ? 'OraBooks_Pwa' : __CLASS__, 'rest_service_worker'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/fiscal-periods', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'rest_list_fiscal_periods'],
                'permission_callback' => [__CLASS__, 'can_view_fiscal'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'rest_create_fiscal_period'],
                'permission_callback' => [__CLASS__, 'can_manage_fiscal'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/fiscal-periods/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'rest_get_fiscal_period'],
            'permission_callback' => [__CLASS__, 'can_view_fiscal'],
        ]);

        register_rest_route(self::NAMESPACE, '/fiscal-periods/(?P<id>\d+)/close', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_close_fiscal_period'],
            'permission_callback' => [__CLASS__, 'can_manage_fiscal'],
        ]);

        register_rest_route(self::NAMESPACE, '/fiscal-periods/(?P<id>\d+)/reopen', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_reopen_fiscal_period'],
            'permission_callback' => [__CLASS__, 'can_manage_fiscal'],
        ]);

        register_rest_route(self::NAMESPACE, '/fiscal-periods/(?P<id>\d+)/override-reopen', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_override_reopen_fiscal_period'],
            'permission_callback' => [__CLASS__, 'can_override_fiscal'],
        ]);

        register_rest_route(self::NAMESPACE, '/expenses', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'rest_list_expenses'],
            'permission_callback' => [__CLASS__, 'can_view_expenses'],
        ]);

        register_rest_route(self::NAMESPACE, '/expenses/upload-receipt', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_upload_receipt'],
            'permission_callback' => [__CLASS__, 'can_manage_expenses'],
        ]);

        register_rest_route(self::NAMESPACE, '/expenses/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'rest_get_expense'],
            'permission_callback' => [__CLASS__, 'can_view_expenses'],
        ]);

        register_rest_route(self::NAMESPACE, '/expenses/(?P<id>\d+)/confirm-submit', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_confirm_expense'],
            'permission_callback' => [__CLASS__, 'can_manage_expenses'],
        ]);

        register_rest_route(self::NAMESPACE, '/journals', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'rest_list_journals'],
                'permission_callback' => [__CLASS__, 'can_view_journals'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'rest_create_journal'],
                'permission_callback' => [__CLASS__, 'can_submit_journal'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/journals/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'rest_get_journal'],
            'permission_callback' => [__CLASS__, 'can_view_journals'],
        ]);

        register_rest_route(self::NAMESPACE, '/journals/(?P<id>\d+)/submit', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_submit_journal'],
            'permission_callback' => [__CLASS__, 'can_submit_journal'],
        ]);

        register_rest_route(self::NAMESPACE, '/journals/(?P<id>\d+)/approve', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_approve_journal'],
            'permission_callback' => [__CLASS__, 'can_approve_journal'],
        ]);

        register_rest_route(self::NAMESPACE, '/journals/(?P<id>\d+)/reject', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_reject_journal'],
            'permission_callback' => [__CLASS__, 'can_approve_journal'],
        ]);

        register_rest_route(self::NAMESPACE, '/journals/(?P<id>\d+)/post', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_post_journal'],
            'permission_callback' => [__CLASS__, 'can_approve_journal'],
        ]);

        register_rest_route(self::NAMESPACE, '/journals/(?P<id>\d+)/reverse', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_reverse_journal'],
            'permission_callback' => [__CLASS__, 'can_reverse_journal'],
        ]);

        register_rest_route(self::NAMESPACE, '/classification/run', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_run_classification'],
            'permission_callback' => [__CLASS__, 'can_view_classification'],
        ]);

        register_rest_route(self::NAMESPACE, '/internal/state/transition', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_state_transition'],
            'permission_callback' => [__CLASS__, 'can_execute_state_transition'],
        ]);

        register_rest_route(self::NAMESPACE, '/internal/jobs/enqueue', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_enqueue_internal_job'],
            'permission_callback' => [__CLASS__, 'can_manage_internal_jobs'],
        ]);

        register_rest_route(self::NAMESPACE, '/internal/jobs/(?P<id>\d+)/retry', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_retry_internal_job'],
            'permission_callback' => [__CLASS__, 'can_manage_internal_jobs'],
        ]);

        register_rest_route(self::NAMESPACE, '/internal/tax/calculate', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_tax_calculate'],
            'permission_callback' => [__CLASS__, 'can_calculate_tax'],
        ]);

        register_rest_route(self::NAMESPACE, '/internal/tax/snapshot', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_tax_snapshot'],
            'permission_callback' => [__CLASS__, 'can_submit_tax_snapshot'],
        ]);

        register_rest_route(self::NAMESPACE, '/tax/configs', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'rest_tax_list_configs'],
                'permission_callback' => [__CLASS__, 'can_manage_tax_config'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'rest_tax_save_config'],
                'permission_callback' => [__CLASS__, 'can_manage_tax_config'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/tax/jurisdictions', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'rest_tax_list_jurisdictions'],
            'permission_callback' => [__CLASS__, 'can_calculate_tax'],
        ]);

        register_rest_route(self::NAMESPACE, '/tax/lock-status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'rest_tax_lock_status'],
            'permission_callback' => [__CLASS__, 'can_manage_tax_config'],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/2fa/setup', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_2fa_setup'],
            'permission_callback' => [__CLASS__, 'can_manage_own_2fa'],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/2fa/verify-setup', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_2fa_verify_setup'],
            'permission_callback' => [__CLASS__, 'can_manage_own_2fa'],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/2fa/challenge', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_2fa_challenge'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/2fa/disable', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_2fa_disable'],
            'permission_callback' => [__CLASS__, 'can_manage_own_2fa'],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/2fa/regenerate-backup-codes', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_2fa_regenerate_backup_codes'],
            'permission_callback' => [__CLASS__, 'can_manage_own_2fa'],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/2fa/reveal-backup-codes', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_2fa_reveal_backup_codes'],
            'permission_callback' => [__CLASS__, 'can_manage_own_2fa'],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/2fa/status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'rest_2fa_status'],
            'permission_callback' => [__CLASS__, 'can_manage_own_2fa'],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/2fa/admin-recover', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_2fa_admin_recover'],
            'permission_callback' => [__CLASS__, 'can_admin_recover_2fa'],
        ]);

        register_rest_route(self::NAMESPACE, '/org/security/2fa-policy', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'rest_get_org_2fa_policy'],
                'permission_callback' => [__CLASS__, 'can_manage_org_2fa_policy'],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [__CLASS__, 'rest_set_org_2fa_policy'],
                'permission_callback' => [__CLASS__, 'can_manage_org_2fa_policy'],
            ],
        ]);
    }

    public static function rest_manifest() {
        if (class_exists('OraBooks_Pwa')) {
            return OraBooks_Pwa::rest_manifest();
        }

        return rest_ensure_response([]);
    }

    public static function rest_service_worker() {
        if (class_exists('OraBooks_Pwa')) {
            return OraBooks_Pwa::rest_service_worker();
        }

        return new WP_Error('orabooks_pwa_unavailable', 'PWA service worker unavailable.', ['status' => 503]);
    }

    public static function openapi_spec_path() {
        return ORABOOKS_PLUGIN_DIR . 'docs/openapi/openapi.json';
    }

    public static function load_openapi_spec() {
        $path = self::openapi_spec_path();
        if (!file_exists($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function rest_openapi() {
        $spec = self::load_openapi_spec();
        if (empty($spec)) {
            return new WP_Error('openapi_missing', 'OpenAPI specification is not available.', ['status' => 404]);
        }

        return rest_ensure_response($spec);
    }

    public static function resolve_route_id(WP_REST_Request $request) {
        return (int) $request->get_param('id');
    }

    public static function resolve_org_id(WP_REST_Request $request) {
        $org_id = (int) ($request->get_header('X-OraBooks-Org-Id') ?: $request->get_param('org_id'));
        return $org_id > 0 ? $org_id : 0;
    }

    public static function current_user_id() {
        return function_exists('orabooks_get_current_user_id')
            ? (int) orabooks_get_current_user_id()
            : (int) get_current_user_id();
    }

    public static function require_org_access($request, $permission) {
        $user_id = self::current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'Not authenticated.', ['status' => 401]);
        }

        $org_id = self::resolve_org_id($request);
        if ($org_id <= 0) {
            return new WP_Error('org_required', 'org_id is required.', ['status' => 400]);
        }

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            return new WP_Error('forbidden', $isolation->get_error_message(), ['status' => 403]);
        }

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, $permission)) {
            return new WP_Error('forbidden', 'Permission denied.', ['status' => 403]);
        }

        if (class_exists('OraBooks_TwoFactor') && $permission !== 'manage_org_settings') {
            $compliance = OraBooks_TwoFactor::assert_org_compliance($user_id, $org_id);
            if (is_wp_error($compliance)) {
                return $compliance;
            }
        }

        return ['user_id' => $user_id, 'org_id' => $org_id];
    }

    public static function can_view_fiscal($request) {
        return !is_wp_error(self::require_org_access($request, 'manage_fiscal_periods'));
    }

    public static function can_manage_fiscal($request) {
        return self::can_view_fiscal($request);
    }

    public static function can_override_fiscal($request) {
        $user_id = self::current_user_id();
        return $user_id > 0 && current_user_can('manage_options');
    }

    public static function can_view_expenses($request) {
        return !is_wp_error(self::require_org_access($request, 'view_expenses'));
    }

    public static function can_manage_expenses($request) {
        return !is_wp_error(self::require_org_access($request, 'manage_expenses'));
    }

    public static function can_view_journals($request) {
        return !is_wp_error(self::require_org_access($request, 'view_reports'));
    }

    public static function can_submit_journal($request) {
        return !is_wp_error(self::require_org_access($request, 'submit_transaction'));
    }

    public static function can_approve_journal($request) {
        $context = self::require_org_access($request, 'approve_journal');
        if (is_wp_error($context)) {
            return false;
        }
        if (class_exists('OraBooks_Approval') && !OraBooks_Approval::user_can_approve($context['user_id'], $context['org_id'])) {
            return false;
        }
        return true;
    }

    public static function can_reverse_journal($request) {
        return !is_wp_error(self::require_org_access($request, 'reverse_journal'));
    }

    public static function can_view_classification($request) {
        return !is_wp_error(self::require_org_access($request, 'view_reports'));
    }

    public static function can_manage_classification($request) {
        return !is_wp_error(self::require_org_access($request, 'create_invoice'));
    }

    public static function can_manage_internal_jobs($request) {
        $user_id = self::current_user_id();
        return $user_id > 0 && current_user_can('manage_options');
    }

    public static function rest_enqueue_internal_job(WP_REST_Request $request) {
        if (!class_exists('OraBooks_AsyncQueue')) {
            return new WP_Error('unavailable', 'Async queue unavailable.', ['status' => 503]);
        }

        $job_type = sanitize_key((string) $request->get_param('job_type'));
        if ($job_type === '') {
            return new WP_Error('invalid_request', 'job_type is required.', ['status' => 422]);
        }

        $payload = $request->get_param('payload');
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($payload)) {
            return new WP_Error('invalid_request', 'payload must be an object.', ['status' => 422]);
        }

        $opts = [
            'queue_name' => sanitize_key((string) ($request->get_param('queue_name') ?: 'default')),
            'priority' => (int) ($request->get_param('priority') ?? 5),
            'max_retries' => (int) ($request->get_param('max_retries') ?? OraBooks_AsyncQueue::DEFAULT_MAX_RETRIES),
            'delay_seconds' => (int) ($request->get_param('delay_seconds') ?? 0),
        ];

        $idempotency_key = sanitize_text_field((string) ($request->get_param('idempotency_key') ?? ''));
        if ($idempotency_key !== '') {
            $opts['idempotency_key'] = $idempotency_key;
        }

        $job_id = OraBooks_AsyncQueue::enqueue_job($job_type, $payload, $opts);
        if (!$job_id) {
            return new WP_Error('enqueue_failed', 'Failed to enqueue job.', ['status' => 500]);
        }

        return rest_ensure_response([
            'job_id' => (int) $job_id,
            'status' => 'pending',
            'queue_name' => $opts['queue_name'],
            'job_type' => $job_type,
        ]);
    }

    public static function rest_retry_internal_job(WP_REST_Request $request) {
        if (!class_exists('OraBooks_AsyncQueue')) {
            return new WP_Error('unavailable', 'Async queue unavailable.', ['status' => 503]);
        }

        $job_id = self::resolve_route_id($request);
        if ($job_id <= 0) {
            return new WP_Error('invalid_request', 'Job ID is required.', ['status' => 422]);
        }

        $result = OraBooks_AsyncQueue::retry_job($job_id);
        if (is_wp_error($result)) {
            $status = 409;
            if ($result->get_error_code() === 'not_found') {
                $status = 404;
            } elseif ($result->get_error_code() === 'forbidden') {
                $status = 403;
            }
            $result->add_data(['status' => $status]);
            return $result;
        }

        return rest_ensure_response([
            'success' => true,
            'job_id' => $job_id,
            'status' => 'pending',
        ]);
    }

    public static function rest_list_journals(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'view_reports');
        if (is_wp_error($context)) {
            return $context;
        }

        $journals = OraBooks_Posting::get_journals($context['org_id'], [
            'status'       => sanitize_text_field($request->get_param('status') ?? ''),
            'from_date'    => sanitize_text_field($request->get_param('from_date') ?? $request->get_param('fromDate') ?? ''),
            'to_date'      => sanitize_text_field($request->get_param('to_date') ?? $request->get_param('toDate') ?? ''),
            'account_code' => sanitize_text_field($request->get_param('account_code') ?? $request->get_param('accountCode') ?? ''),
            'limit'        => min(100, max(1, (int) ($request->get_param('limit') ?: 50))),
            'offset'       => max(0, (int) ($request->get_param('offset') ?: 0)),
        ]);

        return rest_ensure_response([
            'items' => array_map([OraBooks_Posting::class, 'format_journal'], $journals ?: []),
        ]);
    }

    public static function rest_get_journal(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'view_reports');
        if (is_wp_error($context)) {
            return $context;
        }

        $journal_id = self::resolve_route_id($request);
        $journal = OraBooks_Posting::get_journal($journal_id, $context['org_id']);
        if (!$journal) {
            return new WP_Error('not_found', 'Journal not found.', ['status' => 404]);
        }

        return rest_ensure_response([
            'journal' => OraBooks_Posting::format_journal($journal),
            'lines' => array_map(
                static function ($line) use ($context) {
                    return OraBooks_Posting::format_journal_line($line, (int) $context['org_id']);
                },
                OraBooks_Posting::get_journal_lines($journal_id) ?: []
            ),
            'approval_history' => array_map(
                [OraBooks_Posting::class, 'format_approval_history_row'],
                OraBooks_Posting::get_approval_history($journal_id) ?: []
            ),
        ]);
    }

    public static function rest_create_journal(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'submit_transaction');
        if (is_wp_error($context)) {
            return $context;
        }

        $lines = $request->get_param('lines');
        if (is_string($lines)) {
            $lines = json_decode($lines, true);
        }

        $journal_id = OraBooks_Posting::create_journal([
            'org_id'           => $context['org_id'],
            'transaction_date' => sanitize_text_field($request->get_param('transaction_date') ?? $request->get_param('transactionDate') ?? gmdate('Y-m-d')),
            'source_type'      => sanitize_text_field($request->get_param('source_type') ?? $request->get_param('sourceType') ?? 'manual'),
            'source_id'        => $request->get_param('source_id') ?? $request->get_param('sourceId'),
            'idempotency_key'  => sanitize_text_field($request->get_header('Idempotency-Key') ?: $request->get_param('idempotency_key') ?: ''),
        ], $context['user_id']);

        if (is_wp_error($journal_id)) {
            $journal_id->add_data(['status' => 422]);
            return $journal_id;
        }

        if (is_array($lines) && !empty($lines)) {
            $normalized = [];
            foreach ($lines as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $normalized[] = [
                    'account_code' => sanitize_text_field($line['account_code'] ?? $line['accountCode'] ?? ''),
                    'debit'        => (float) ($line['debit_amount'] ?? $line['debit'] ?? 0),
                    'credit'       => (float) ($line['credit_amount'] ?? $line['credit'] ?? 0),
                    'description'  => sanitize_text_field($line['description'] ?? ''),
                ];
            }
            $line_result = OraBooks_Posting::add_lines((int) $journal_id, $normalized);
            if (is_wp_error($line_result)) {
                $line_result->add_data(['status' => 422]);
                return $line_result;
            }
        }

        $journal = OraBooks_Posting::get_journal((int) $journal_id, $context['org_id']);
        return rest_ensure_response([
            'journal_id' => (int) $journal_id,
            'journal'    => OraBooks_Posting::format_journal($journal),
        ]);
    }

    public static function rest_submit_journal(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'submit_transaction');
        if (is_wp_error($context)) {
            return $context;
        }

        $journal = OraBooks_Posting::get_journal(self::resolve_route_id($request), $context['org_id']);
        if (!$journal) {
            return new WP_Error('not_found', 'Journal not found.', ['status' => 404]);
        }

        $result = OraBooks_Posting::submit_journal(self::resolve_route_id($request), $context['user_id']);
        if (is_wp_error($result)) {
            $result->add_data(['status' => 409]);
            return $result;
        }

        return rest_ensure_response(is_array($result) ? $result : ['success' => true]);
    }

    public static function rest_approve_journal(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'approve_journal');
        if (is_wp_error($context)) {
            return $context;
        }

        $journal = OraBooks_Posting::get_journal(self::resolve_route_id($request), $context['org_id']);
        if (!$journal) {
            return new WP_Error('not_found', 'Journal not found.', ['status' => 404]);
        }

        $args = [];
        if ($request->get_param('mfa_otp') || $request->get_param('mfaOtp')) {
            $args['mfa_otp'] = sanitize_text_field($request->get_param('mfa_otp') ?: $request->get_param('mfaOtp'));
        }

        $result = OraBooks_Posting::approve_journal(self::resolve_route_id($request), $context['user_id'], $args);
        if (is_wp_error($result)) {
            $result->add_data(['status' => 409]);
            return $result;
        }

        return rest_ensure_response(['success' => true]);
    }

    public static function rest_reject_journal(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'approve_journal');
        if (is_wp_error($context)) {
            return $context;
        }

        $journal = OraBooks_Posting::get_journal(self::resolve_route_id($request), $context['org_id']);
        if (!$journal) {
            return new WP_Error('not_found', 'Journal not found.', ['status' => 404]);
        }

        $reason = sanitize_textarea_field($request->get_param('reason') ?? '');
        $result = OraBooks_Posting::reject_journal(self::resolve_route_id($request), $context['user_id'], $reason);
        if (is_wp_error($result)) {
            $result->add_data(['status' => 409]);
            return $result;
        }

        return rest_ensure_response(['success' => true]);
    }

    public static function rest_post_journal(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'approve_journal');
        if (is_wp_error($context)) {
            return $context;
        }

        $journal = OraBooks_Posting::get_journal(self::resolve_route_id($request), $context['org_id']);
        if (!$journal) {
            return new WP_Error('not_found', 'Journal not found.', ['status' => 404]);
        }

        $result = OraBooks_Posting::post_journal(self::resolve_route_id($request), $context['user_id']);
        if (is_wp_error($result)) {
            $result->add_data(['status' => 409]);
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function rest_reverse_journal(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'reverse_journal');
        if (is_wp_error($context)) {
            return $context;
        }

        $reason = sanitize_textarea_field($request->get_param('reason') ?? '');
        $result = OraBooks_Posting::reverse_journal(self::resolve_route_id($request), $context['org_id'], $context['user_id'], $reason);
        if (is_wp_error($result)) {
            $result->add_data(['status' => 409]);
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function rest_run_classification(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'view_reports');
        if (is_wp_error($context)) {
            return $context;
        }

        if (!class_exists('OraBooks_Classification')) {
            return new WP_Error('unavailable', 'Classification engine unavailable.', ['status' => 503]);
        }

        $record_type = sanitize_text_field($request->get_param('record_type') ?? $request->get_param('recordType') ?? '');
        $record_id = (int) ($request->get_param('record_id') ?? $request->get_param('recordId') ?? 0);
        $persist = rest_sanitize_boolean($request->get_param('persist') ?? false);

        if ($record_id <= 0 || $record_type === '') {
            return new WP_Error('invalid_request', 'record_type and record_id are required.', ['status' => 422]);
        }

        if ($persist) {
            $manage = self::require_org_access($request, 'create_invoice');
            if (is_wp_error($manage)) {
                return $manage;
            }
        }

        $result = $persist
            ? OraBooks_Classification::run($record_type, $record_id, $context['org_id'])
            : OraBooks_Classification::preview($record_type, $record_id, $context['org_id']);

        if (is_wp_error($result)) {
            $result->add_data(['status' => 422]);
            return $result;
        }

        return rest_ensure_response([
            'record_type' => $record_type,
            'record_id' => $record_id,
            'persist' => $persist,
            'classification' => $result,
        ]);
    }

    public static function rest_list_fiscal_periods(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'manage_fiscal_periods');
        if (is_wp_error($context)) {
            return $context;
        }

        $result = OraBooks_Fiscal::paginate_periods($context['org_id'], [
            'status'   => sanitize_text_field($request->get_param('status') ?? ''),
            'year'     => (int) $request->get_param('year'),
            'month'    => (int) $request->get_param('month'),
            'page'     => max(1, (int) ($request->get_param('page') ?: 1)),
            'per_page' => min(100, max(1, (int) ($request->get_param('per_page') ?: 20))),
        ]);

        return rest_ensure_response($result);
    }

    public static function rest_get_fiscal_period(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'manage_fiscal_periods');
        if (is_wp_error($context)) {
            return $context;
        }

        $period = OraBooks_Fiscal::get_period((int) $request['id'], $context['org_id']);
        if (!$period) {
            return new WP_Error('not_found', 'Fiscal period not found.', ['status' => 404]);
        }

        return rest_ensure_response(OraBooks_Fiscal::format_period_for_api($period));
    }

    public static function rest_create_fiscal_period(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'manage_fiscal_periods');
        if (is_wp_error($context)) {
            return $context;
        }

        $result = OraBooks_Fiscal::create_period(
            $context['org_id'],
            sanitize_text_field($request->get_param('period_start') ?: $request->get_param('periodStart')),
            sanitize_text_field($request->get_param('period_end') ?: $request->get_param('periodEnd')),
            sanitize_text_field($request->get_param('period_name') ?: $request->get_param('periodName') ?: '')
        );

        if (is_wp_error($result)) {
            $result->add_data(['status' => 422]);
            return $result;
        }

        $period = OraBooks_Fiscal::get_period((int) $result, $context['org_id']);
        return rest_ensure_response(OraBooks_Fiscal::format_period_for_api($period));
    }

    public static function rest_close_fiscal_period(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'manage_fiscal_periods');
        if (is_wp_error($context)) {
            return $context;
        }

        $close_type = sanitize_text_field($request->get_param('closeType') ?: 'soft');
        $hard_confirm = rest_sanitize_boolean($request->get_param('hardConfirm') ?? $request->get_param('hard_confirm') ?? false);
        $result = OraBooks_Fiscal::close_period(
            (int) $request['id'],
            $context['org_id'],
            $close_type,
            $context['user_id'],
            sanitize_textarea_field($request->get_param('note') ?? ''),
            ['hard_confirm' => $hard_confirm]
        );

        if (is_wp_error($result)) {
            $result->add_data(['status' => 409]);
            return $result;
        }

        $period = OraBooks_Fiscal::get_period((int) $request['id'], $context['org_id']);
        return rest_ensure_response([
            'success'  => true,
            'period'   => OraBooks_Fiscal::format_period_for_api($period),
            'warnings' => $result['warnings'] ?? [],
            'pending'  => $result['pending'] ?? [],
        ]);
    }

    public static function rest_reopen_fiscal_period(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'manage_fiscal_periods');
        if (is_wp_error($context)) {
            return $context;
        }

        $result = OraBooks_Fiscal::reopen_period(
            (int) $request['id'],
            $context['org_id'],
            $context['user_id'],
            sanitize_textarea_field($request->get_param('reason') ?? '')
        );

        if (is_wp_error($result)) {
            $result->add_data(['status' => 409]);
            return $result;
        }

        $period = OraBooks_Fiscal::get_period((int) $request['id'], $context['org_id']);
        return rest_ensure_response(['success' => true, 'period' => OraBooks_Fiscal::format_period_for_api($period)]);
    }

    public static function rest_override_reopen_fiscal_period(WP_REST_Request $request) {
        $user_id = self::current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'Not authenticated.', ['status' => 401]);
        }
        if (!current_user_can('manage_options')) {
            return new WP_Error('forbidden', 'Super Admin permission required.', ['status' => 403]);
        }

        $org_id = self::resolve_org_id($request);
        if ($org_id <= 0) {
            return new WP_Error('org_required', 'org_id is required.', ['status' => 400]);
        }

        $justification = sanitize_textarea_field($request->get_param('justification') ?? '');
        if (trim($justification) === '') {
            return new WP_Error('justification_required', 'Mandatory justification is required.', ['status' => 422]);
        }

        $result = OraBooks_Fiscal::override_reopen_period((int) $request['id'], $org_id, $user_id, $justification);
        if (is_wp_error($result)) {
            $result->add_data(['status' => 409]);
            return $result;
        }

        $period = OraBooks_Fiscal::get_period((int) $request['id'], $org_id);
        return rest_ensure_response(['success' => true, 'period' => OraBooks_Fiscal::format_period_for_api($period)]);
    }

    public static function rest_list_expenses(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'view_expenses');
        if (is_wp_error($context)) {
            return $context;
        }

        $page = max(1, (int) ($request->get_param('page') ?: 1));
        $per_page = min(100, max(1, (int) ($request->get_param('per_page') ?: 20)));
        $status = sanitize_text_field($request->get_param('workflow_status') ?? '');

        $rows = OraBooks_Expenses::list_expenses($context['org_id'], [
            'workflow_status' => $status,
            'limit'           => $per_page,
            'offset'          => ($page - 1) * $per_page,
        ]);

        $items = array_map(function ($row) {
            return OraBooks_Expenses::format_expense($row);
        }, $rows ?: []);

        return rest_ensure_response([
            'items'    => $items,
            'page'     => $page,
            'per_page' => $per_page,
            'total'    => count($items),
        ]);
    }

    public static function rest_get_expense(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'view_expenses');
        if (is_wp_error($context)) {
            return $context;
        }

        $expense = OraBooks_Expenses::get_expense((int) $request['id'], $context['org_id']);
        if (!$expense) {
            return new WP_Error('not_found', 'Expense not found.', ['status' => 404]);
        }

        return rest_ensure_response(['expense' => OraBooks_Expenses::format_expense($expense, true)]);
    }

    public static function rest_upload_receipt(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'manage_expenses');
        if (is_wp_error($context)) {
            return $context;
        }

        $files = $request->get_file_params();
        if (empty($files['receipt_file'])) {
            return new WP_Error('file_required', 'receipt_file is required.', ['status' => 400]);
        }

        $file = $files['receipt_file'];
        if (!empty($file['error'])) {
            return new WP_Error('upload_failed', 'Upload failed.', ['status' => 400]);
        }

        $content = file_get_contents($file['tmp_name']);
        $result = OraBooks_Expenses::upload_receipt(
            $context['org_id'],
            $context['user_id'],
            sanitize_file_name($file['name'] ?? 'receipt.jpg'),
            $content,
            sanitize_text_field($file['type'] ?? ''),
            sanitize_text_field($request->get_param('idempotency_key') ?? '')
        );

        if (is_wp_error($result)) {
            $result->add_data(['status' => 400]);
            return $result;
        }

        return rest_ensure_response(['expense' => $result]);
    }

    public static function rest_confirm_expense(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'manage_expenses');
        if (is_wp_error($context)) {
            return $context;
        }

        $idempotency_key = sanitize_text_field($request->get_param('idempotency_key') ?? '');
        $edited = $request->get_param('edited_fields');
        if (!is_array($edited)) {
            $edited = [];
        }

        $result = OraBooks_Expenses::confirm_submit(
            (int) $request['id'],
            $context['org_id'],
            $context['user_id'],
            $idempotency_key,
            $edited
        );

        if (is_wp_error($result)) {
            $status = $result->get_error_code() === 'duplicate' ? 409 : 400;
            $result->add_data(['status' => $status]);
            return $result;
        }

        return rest_ensure_response(['expense' => $result]);
    }

    public static function can_execute_state_transition($request) {
        $user_id = self::current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'Not authenticated.', ['status' => 401]);
        }

        $org_id = self::resolve_org_id($request);
        if ($org_id <= 0) {
            return new WP_Error('org_required', 'org_id is required.', ['status' => 400]);
        }

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            return new WP_Error('forbidden', $isolation->get_error_message(), ['status' => 403]);
        }

        if (function_exists('current_user_can') && current_user_can('manage_options')) {
            return true;
        }

        $allowed = orabooks_has_permission($user_id, $org_id, 'manage_settings')
            || orabooks_has_permission($user_id, $org_id, 'submit_transaction')
            || orabooks_has_permission($user_id, $org_id, 'approve_journal');

        if (!$allowed) {
            return new WP_Error('forbidden', 'Permission denied.', ['status' => 403]);
        }

        return true;
    }

    public static function rest_state_transition(WP_REST_Request $request) {
        if (!class_exists('OraBooks_Workflow')) {
            return new WP_Error('workflow_unavailable', 'Workflow engine unavailable.', ['status' => 503]);
        }

        $user_id = self::current_user_id();
        $org_id = self::resolve_org_id($request);
        $record_type = sanitize_text_field($request->get_param('record_type') ?? '');
        $record_id = (int) $request->get_param('record_id');
        $event = sanitize_text_field($request->get_param('event') ?? '');
        $reason = $request->get_param('reason');
        $reason = $reason !== null ? sanitize_textarea_field((string) $reason) : null;

        if ($record_type === '' || $record_id <= 0 || $event === '') {
            return new WP_Error('invalid_request', 'record_type, record_id, and event are required.', ['status' => 400]);
        }

        $context = [
            'user_id' => $user_id,
            'org_id'  => $org_id,
            'reason'  => $reason,
        ];

        if ($request->get_param('mfa_otp') !== null) {
            $context['mfa_otp'] = sanitize_text_field((string) $request->get_param('mfa_otp'));
        }
        if ($request->get_param('mfa_verified') !== null) {
            $context['mfa_verified'] = (bool) $request->get_param('mfa_verified');
        }

        $result = OraBooks_Workflow::transition($record_type, $record_id, $event, $context);
        if (is_wp_error($result)) {
            $status = 400;
            $data = $result->get_error_data();
            if (is_array($data) && isset($data['status'])) {
                $status = (int) $data['status'];
            }
            if ($result->get_error_code() === 'invalid_transition') {
                $status = 409;
            }
            $result->add_data(['status' => $status]);
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function can_manage_own_2fa($request) {
        return self::current_user_id() > 0;
    }

    public static function can_admin_recover_2fa($request) {
        $user_id = self::current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'Not authenticated.', ['status' => 401]);
        }

        if (function_exists('current_user_can') && current_user_can('manage_options')) {
            return true;
        }

        $org_id = self::resolve_org_id($request);
        if ($org_id > 0 && OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_org_settings')) {
            return true;
        }

        return new WP_Error('forbidden', 'Permission denied.', ['status' => 403]);
    }

    public static function can_manage_org_2fa_policy($request) {
        $context = self::require_org_access($request, 'manage_org_settings');
        return !is_wp_error($context);
    }

    public static function rest_2fa_setup(WP_REST_Request $request) {
        $result = OraBooks_TwoFactor::setup(self::current_user_id());
        if (is_wp_error($result)) {
            $result->add_data(['status' => (int) ($result->get_error_data()['status'] ?? 400)]);
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function rest_2fa_verify_setup(WP_REST_Request $request) {
        $result = OraBooks_TwoFactor::verify_setup(
            self::current_user_id(),
            sanitize_text_field($request->get_param('otp_code') ?? $request->get_param('otpCode') ?? '')
        );

        if (is_wp_error($result)) {
            $result->add_data(['status' => (int) ($result->get_error_data()['status'] ?? 400)]);
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function rest_2fa_challenge(WP_REST_Request $request) {
        $result = OraBooks_TwoFactor::challenge(
            sanitize_text_field($request->get_param('temp_token') ?? $request->get_param('tempToken') ?? ''),
            sanitize_text_field($request->get_param('otp_code') ?? $request->get_param('otpCode') ?? ''),
            sanitize_text_field($request->get_param('backup_code') ?? $request->get_param('backupCode') ?? '')
        );

        if (is_wp_error($result)) {
            $result->add_data(['status' => (int) ($result->get_error_data()['status'] ?? 401)]);
            return $result;
        }

        return rest_ensure_response(orabooks_redact_client_auth_response($result));
    }

    public static function rest_2fa_disable(WP_REST_Request $request) {
        $result = OraBooks_TwoFactor::disable(
            self::current_user_id(),
            sanitize_text_field($request->get_param('otp_code') ?? $request->get_param('otpCode') ?? '')
        );

        if (is_wp_error($result)) {
            $result->add_data(['status' => (int) ($result->get_error_data()['status'] ?? 400)]);
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function rest_2fa_regenerate_backup_codes(WP_REST_Request $request) {
        $result = OraBooks_TwoFactor::regenerate_backup_codes(
            self::current_user_id(),
            sanitize_text_field($request->get_param('otp_code') ?? $request->get_param('otpCode') ?? '')
        );

        if (is_wp_error($result)) {
            $result->add_data(['status' => (int) ($result->get_error_data()['status'] ?? 400)]);
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function rest_2fa_reveal_backup_codes(WP_REST_Request $request) {
        $result = OraBooks_TwoFactor::reveal_backup_codes(
            self::current_user_id(),
            sanitize_text_field($request->get_param('otp_code') ?? $request->get_param('otpCode') ?? '')
        );

        if (is_wp_error($result)) {
            $result->add_data(['status' => (int) ($result->get_error_data()['status'] ?? 400)]);
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function rest_2fa_status(WP_REST_Request $request) {
        return rest_ensure_response(OraBooks_TwoFactor::get_status(self::current_user_id()));
    }

    public static function rest_2fa_admin_recover(WP_REST_Request $request) {
        $result = OraBooks_TwoFactor::admin_recover(
            (int) ($request->get_param('target_user_id') ?? $request->get_param('targetUserId') ?? 0),
            self::current_user_id(),
            sanitize_textarea_field($request->get_param('justification') ?? '')
        );

        if (is_wp_error($result)) {
            $result->add_data(['status' => (int) ($result->get_error_data()['status'] ?? 400)]);
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function rest_get_org_2fa_policy(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'manage_org_settings');
        if (is_wp_error($context)) {
            return $context;
        }

        return rest_ensure_response(OraBooks_TwoFactor::get_org_policy($context['org_id']));
    }

    public static function rest_set_org_2fa_policy(WP_REST_Request $request) {
        $context = self::require_org_access($request, 'manage_org_settings');
        if (is_wp_error($context)) {
            return $context;
        }

        $enabled = rest_sanitize_boolean($request->get_param('require_2fa') ?? $request->get_param('require2fa') ?? false);
        $result = OraBooks_TwoFactor::set_org_requires_2fa($context['org_id'], $enabled, $context['user_id']);

        if (is_wp_error($result)) {
            $result->add_data(['status' => (int) ($result->get_error_data()['status'] ?? 400)]);
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function can_calculate_tax($request) {
        $context = self::require_org_access($request, 'create_invoice');
        return is_wp_error($context) ? $context : true;
    }

    public static function can_manage_tax_config($request) {
        $context = self::require_org_access($request, 'manage_org_settings');
        return is_wp_error($context) ? $context : true;
    }

    public static function can_submit_tax_snapshot($request) {
        $context = self::require_org_access($request, 'submit_transaction');
        return is_wp_error($context) ? $context : true;
    }

    public static function rest_tax_calculate(WP_REST_Request $request) {
        if (!class_exists('OraBooks_Tax')) {
            return new WP_Error('tax_unavailable', 'Tax engine unavailable.', ['status' => 503]);
        }

        $context = self::require_org_access($request, 'create_invoice');
        if (is_wp_error($context)) {
            return $context;
        }

        $payload = [
            'org_id' => $context['org_id'],
            'amount' => $request->get_param('amount'),
            'jurisdiction' => $request->get_param('jurisdiction'),
            'billing_address' => $request->get_param('billing_address'),
            'customer_tax_status' => $request->get_param('customer_tax_status'),
            'product_type' => $request->get_param('product_type'),
            'validate_posting_accounts' => rest_sanitize_boolean($request->get_param('validate_posting_accounts') ?? false),
        ];

        $result = OraBooks_Tax::calculate($payload);
        if (is_wp_error($result)) {
            $result->add_data(['status' => 400]);
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function rest_tax_snapshot(WP_REST_Request $request) {
        if (!class_exists('OraBooks_Tax')) {
            return new WP_Error('tax_unavailable', 'Tax engine unavailable.', ['status' => 503]);
        }

        $context = self::require_org_access($request, 'submit_transaction');
        if (is_wp_error($context)) {
            return $context;
        }

        $payload = [
            'org_id' => $context['org_id'],
            'transaction_id' => (int) $request->get_param('transaction_id'),
            'transaction_type' => sanitize_text_field($request->get_param('transaction_type') ?? ''),
            'amount' => $request->get_param('amount'),
            'jurisdiction' => $request->get_param('jurisdiction'),
            'transaction_date' => $request->get_param('transaction_date'),
            'tax_type' => $request->get_param('tax_type'),
            'override' => rest_sanitize_boolean($request->get_param('override') ?? false),
            'override_tax_rate' => $request->get_param('override_tax_rate'),
            'override_reason' => $request->get_param('override_reason'),
            'override_note' => $request->get_param('override_note'),
            'metadata' => $request->get_param('metadata'),
        ];

        $result = OraBooks_Tax::create_snapshot($payload, $context['user_id']);
        if (is_wp_error($result)) {
            $result->add_data(['status' => 409]);
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function rest_tax_list_configs(WP_REST_Request $request) {
        if (!class_exists('OraBooks_Tax')) {
            return new WP_Error('tax_unavailable', 'Tax engine unavailable.', ['status' => 503]);
        }

        $context = self::require_org_access($request, 'manage_org_settings');
        if (is_wp_error($context)) {
            return $context;
        }

        return rest_ensure_response([
            'configs' => OraBooks_Tax::list_configs($context['org_id']),
            'override_reasons' => OraBooks_Tax::DEFAULT_OVERRIDE_REASONS,
            'lock_status' => OraBooks_Tax::get_lock_status($context['org_id']),
        ]);
    }

    public static function rest_tax_save_config(WP_REST_Request $request) {
        if (!class_exists('OraBooks_Tax')) {
            return new WP_Error('tax_unavailable', 'Tax engine unavailable.', ['status' => 503]);
        }

        $context = self::require_org_access($request, 'manage_org_settings');
        if (is_wp_error($context)) {
            return $context;
        }

        $result = OraBooks_Tax::save_config($context['org_id'], [
            'jurisdiction' => $request->get_param('jurisdiction'),
            'default_tax_rate' => $request->get_param('default_tax_rate'),
            'tax_type' => $request->get_param('tax_type'),
            'is_active' => $request->get_param('is_active'),
            'exemption_certificate_url' => $request->get_param('exemption_certificate_url'),
            'override_reasons' => $request->get_param('override_reasons'),
            'transaction_date' => $request->get_param('transaction_date'),
        ], $context['user_id']);

        if (is_wp_error($result)) {
            $result->add_data(['status' => 400]);
            return $result;
        }

        return rest_ensure_response(['config' => OraBooks_Tax::format_config($result)]);
    }

    public static function rest_tax_list_jurisdictions(WP_REST_Request $request) {
        if (!class_exists('OraBooks_Tax')) {
            return new WP_Error('tax_unavailable', 'Tax engine unavailable.', ['status' => 503]);
        }

        $context = self::require_org_access($request, 'create_invoice');
        if (is_wp_error($context)) {
            return $context;
        }

        return rest_ensure_response(['jurisdictions' => OraBooks_Tax::list_jurisdictions()]);
    }

    public static function rest_tax_lock_status(WP_REST_Request $request) {
        if (!class_exists('OraBooks_Tax')) {
            return new WP_Error('tax_unavailable', 'Tax engine unavailable.', ['status' => 503]);
        }

        $context = self::require_org_access($request, 'manage_org_settings');
        if (is_wp_error($context)) {
            return $context;
        }

        $date = sanitize_text_field($request->get_param('transaction_date') ?? '');
        return rest_ensure_response(OraBooks_Tax::get_lock_status($context['org_id'], $date ?: null));
    }
}
