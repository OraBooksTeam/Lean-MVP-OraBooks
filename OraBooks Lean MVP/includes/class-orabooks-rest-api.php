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

        register_rest_route(self::NAMESPACE, '/internal/state/transition', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rest_state_transition'],
            'permission_callback' => [__CLASS__, 'can_execute_state_transition'],
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

        if (class_exists('OraBooks_TwoFactor')) {
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
}
