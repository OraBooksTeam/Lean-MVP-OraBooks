<?php
/**
 * SL-021 – Invoices / Wallet / Credit Notes / AR REST API
 *
 * Provides REST API endpoints under the orabooks/v1 namespace for:
 * - Invoice CRUD + state machine transitions + payment recording
 * - Credit note CRUD + posting
 * - Wallet read/update + transactions + credit limits
 * - Customer active status management
 * - AR aging reports
 *
 * Follows the same pattern as class-orabooks-partners.php REST routes.
 *
 * @package OraBooks_Membership
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Invoices_REST {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * API namespace
     */
    const API_NAMESPACE = 'orabooks/v1';

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register all REST API routes
     */
    public function register_routes() {
        $ns = self::API_NAMESPACE;

        // ── Invoices ────────────────────────────────────────────────────

        // GET/POST /invoice
        register_rest_route($ns, '/invoice', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_invoices'),
                'permission_callback' => array($this, 'check_org_access'),
                'args'                => $this->get_invoices_args(),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'create_invoice'),
                'permission_callback' => array($this, 'check_org_access'),
                'args'                => $this->create_invoice_args(),
            ),
        ));

        // GET/PUT /invoice/{id}
        register_rest_route($ns, '/invoice/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_invoice'),
                'permission_callback' => array($this, 'check_org_access'),
                'args'                => array('id' => array('required' => true, 'validate_callback' => 'is_numeric')),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'update_invoice'),
                'permission_callback' => array($this, 'check_org_access'),
                'args'                => array('id' => array('required' => true, 'validate_callback' => 'is_numeric')),
            ),
        ));

        // POST /invoice/{id}/submit
        register_rest_route($ns, '/invoice/(?P<id>\d+)/submit', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'submit_invoice'),
            'permission_callback' => array($this, 'check_org_access'),
            'args'                => array('id' => array('required' => true, 'validate_callback' => 'is_numeric')),
        ));

        // POST /invoice/{id}/approve
        register_rest_route($ns, '/invoice/(?P<id>\d+)/approve', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'approve_invoice'),
            'permission_callback' => array($this, 'check_org_access'),
            'args'                => array('id' => array('required' => true, 'validate_callback' => 'is_numeric')),
        ));

        // POST /invoice/{id}/post
        register_rest_route($ns, '/invoice/(?P<id>\d+)/post', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'post_invoice'),
            'permission_callback' => array($this, 'check_org_access'),
            'args'                => array('id' => array('required' => true, 'validate_callback' => 'is_numeric')),
        ));

        // POST /invoice/{id}/void
        register_rest_route($ns, '/invoice/(?P<id>\d+)/void', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'void_invoice'),
            'permission_callback' => array($this, 'check_org_access'),
            'args'                => array(
                'id'     => array('required' => true, 'validate_callback' => 'is_numeric'),
                'reason' => array('required' => false, 'type' => 'string'),
            ),
        ));

        // POST /invoice/{id}/return-to-draft
        register_rest_route($ns, '/invoice/(?P<id>\d+)/return-to-draft', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'return_to_draft'),
            'permission_callback' => array($this, 'check_org_access'),
            'args'                => array('id' => array('required' => true, 'validate_callback' => 'is_numeric')),
        ));

        // POST /invoice/{id}/payment
        register_rest_route($ns, '/invoice/(?P<id>\d+)/payment', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'record_payment'),
            'permission_callback' => array($this, 'check_org_access'),
            'args'                => array(
                'id'             => array('required' => true, 'validate_callback' => 'is_numeric'),
                'amount'         => array('required' => true, 'type' => 'number'),
                'payment_method' => array('required' => false, 'type' => 'string'),
                'gateway_ref'    => array('required' => false, 'type' => 'string'),
                'cash_account'   => array('required' => false, 'type' => 'integer'),
                'notes'          => array('required' => false, 'type' => 'string'),
                'payment_date'   => array('required' => false, 'type' => 'string'),
            ),
        ));

        // GET /invoice/{id}/allocations
        register_rest_route($ns, '/invoice/(?P<id>\d+)/allocations', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_allocations'),
            'permission_callback' => array($this, 'check_org_access'),
            'args'                => array('id' => array('required' => true, 'validate_callback' => 'is_numeric')),
        ));

        // ── Credit Notes ────────────────────────────────────────────────

        // GET/POST /credit-note
        register_rest_route($ns, '/credit-note', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_credit_notes'),
                'permission_callback' => array($this, 'check_org_access'),
                'args'                => $this->get_credit_notes_args(),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'create_credit_note'),
                'permission_callback' => array($this, 'check_org_access'),
                'args'                => $this->create_credit_note_args(),
            ),
        ));

        // POST /credit-note/{id}/post
        register_rest_route($ns, '/credit-note/(?P<id>\d+)/post', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'post_credit_note'),
            'permission_callback' => array($this, 'check_org_access'),
            'args'                => array('id' => array('required' => true, 'validate_callback' => 'is_numeric')),
        ));

        // POST /credit-note/{id}/void
        register_rest_route($ns, '/credit-note/(?P<id>\d+)/void', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'void_credit_note'),
            'permission_callback' => array($this, 'check_org_access'),
            'args'                => array(
                'id'     => array('required' => true, 'validate_callback' => 'is_numeric'),
                'reason' => array('required' => false, 'type' => 'string'),
            ),
        ));

        // ── Wallet ──────────────────────────────────────────────────────

        // GET /wallet?org_id=X&customer_id=Y
        register_rest_route($ns, '/wallet', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_wallet'),
            'permission_callback' => array($this, 'check_org_access'),
            'args'                => array(
                'org_id'      => array('required' => true, 'validate_callback' => 'is_numeric'),
                'customer_id' => array('required' => true, 'validate_callback' => 'is_numeric'),
            ),
        ));

        // PUT /wallet — update credit limit, credit hold, auto-apply
        register_rest_route($ns, '/wallet', array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array($this, 'update_wallet'),
            'permission_callback' => array($this, 'check_org_access'),
            'args'                => array(
                'org_id'           => array('required' => true, 'validate_callback' => 'is_numeric'),
                'customer_id'      => array('required' => true, 'validate_callback' => 'is_numeric'),
                'credit_limit'     => array('required' => false, 'type' => 'number'),
                'credit_hold'      => array('required' => false, 'type' => 'boolean'),
                'auto_apply_credit'=> array('required' => false, 'type' => 'boolean'),
                'balance'          => array('required' => false, 'type' => 'number'),
                'balance_reason'   => array('required' => false, 'type' => 'string'),
            ),
        ));

        // GET /wallet/transactions?org_id=X&customer_id=Y&limit=50
        register_rest_route($ns, '/wallet/transactions', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_wallet_transactions'),
            'permission_callback' => array($this, 'check_org_access'),
            'args'                => array(
                'org_id'      => array('required' => true, 'validate_callback' => 'is_numeric'),
                'customer_id' => array('required' => true, 'validate_callback' => 'is_numeric'),
                'limit'       => array('required' => false, 'default' => 50, 'validate_callback' => 'is_numeric'),
            ),
        ));

        // ── Customer Active Status ─────────────────────────────────────

        // GET /wallet/active-status?org_id=X&customer_id=Y
        register_rest_route($ns, '/wallet/active-status', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_active_status'),
            'permission_callback' => array($this, 'check_org_access'),
            'args'                => array(
                'org_id'      => array('required' => true, 'validate_callback' => 'is_numeric'),
                'customer_id' => array('required' => true, 'validate_callback' => 'is_numeric'),
            ),
        ));

        // PUT /wallet/active-status — toggle active/inactive
        register_rest_route($ns, '/wallet/active-status', array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array($this, 'update_active_status'),
            'permission_callback' => array($this, 'check_org_access'),
            'args'                => array(
                'org_id'      => array('required' => true, 'validate_callback' => 'is_numeric'),
                'customer_id' => array('required' => true, 'validate_callback' => 'is_numeric'),
                'is_active'   => array('required' => true, 'type' => 'boolean'),
                'reason'      => array('required' => false, 'type' => 'string'),
            ),
        ));

        // ── AR Aging ────────────────────────────────────────────────────

        // GET /ar-aging?org_id=X&detailed=1
        register_rest_route($ns, '/ar-aging', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_ar_aging'),
            'permission_callback' => array($this, 'check_org_access'),
            'args'                => array(
                'org_id'   => array('required' => true, 'validate_callback' => 'is_numeric'),
                'detailed' => array('required' => false, 'type' => 'boolean', 'default' => false),
            ),
        ));
    }

    // ══════════════════════════════════════════════════════════════════════
    // PERMISSION CALLBACKS
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Org-level auth middleware.
     *
     * 1. Verifies user is logged in (basic auth gate).
     * 2. Extracts org_id from request params, or from the target entity
     *    (invoice/credit-note) when not directly provided.
     * 3. Calls OraBooks_ACL_Endpoints::require_customer_org() to enforce
     *    the accounting isolation rule (blocks partner orgs).
     *
     * @param WP_REST_Request $request
     * @return true|WP_Error
     */
    public function check_org_access($request) {
        // Step 1: Basic auth gate
        if (!is_user_logged_in()) {
            return new WP_Error('rest_not_logged_in', __('Authentication required.', 'orabooks'), array('status' => 401));
        }

        $user_id = get_current_user_id();

        // Step 2: Extract org_id from the request
        $org_id = $this->resolve_org_id($request);

        if ($org_id <= 0) {
            return new WP_Error('missing_org_context', __('Organization context is required.', 'orabooks'), array('status' => 400));
        }

        // Step 3: Check org-level access (blocks partner orgs from accounting)
        if (class_exists('OraBooks_ACL_Endpoints')) {
            $check = OraBooks_ACL_Endpoints::require_customer_org($user_id, $org_id, true);
            if (is_wp_error($check)) {
                return $check;
            }
        }

        return true;
    }

    /**
     * Resolve the organization ID from a REST request.
     *
     * Checks in order:
     * 1. 'org_id' param directly on the request
     * 2. Invoice ID param → look up invoice's org_id
     * 3. Credit note ID param → look up credit note's org_id
     *
     * @param WP_REST_Request $request
     * @return int Organization ID (0 if not determined)
     */
    private function resolve_org_id($request) {
        // 1. Check for org_id directly on the request
        $direct = $request->get_param('org_id');
        if (!empty($direct) && (int) $direct > 0) {
            return (int) $direct;
        }

        // 2. Check for invoice ID → look up the invoice's org
        $invoice_id = $request->get_param('id');
        if (!empty($invoice_id)) {
            $invoice = $this->invoices()->get_invoice((int) $invoice_id);
            if ($invoice && !empty($invoice->org_id)) {
                return (int) $invoice->org_id;
            }
        }

        // 3. Check for credit note ID → look up via direct DB query
        // Reuse $invoice_id from step 2 (same param, different entity lookup)
        if (!empty($invoice_id)) {
            $route = $request->get_route();
            if (strpos($route, '/credit-note') !== false) {
                global $wpdb;
                $this->invoices()->register_table_names();
                $cn = $wpdb->get_row($wpdb->prepare(
                    "SELECT org_id FROM {$wpdb->orabooks_credit_notes} WHERE id = %d",
                    (int) $cn_id
                ));
                if ($cn && !empty($cn->org_id)) {
                    return (int) $cn->org_id;
                }
            }
        }

        return 0;
    }

    /**
     * Helper: get the invoices engine instance
     */
    private function invoices() {
        return OraBooks_Invoices::get_instance();
    }

    /**
     * Helper: return WP_REST_Response success
     */
    private function success($data, $status = 200) {
        return new WP_REST_Response(array('success' => true, 'data' => $data), $status);
    }

    /**
     * Helper: return WP_Error for WP_REST
     */
    private function error($message, $code = 'rest_error', $status = 400) {
        return new WP_Error($code, $message, array('status' => $status));
    }

    // ══════════════════════════════════════════════════════════════════════
    // INVOICE ENDPOINTS
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /invoice — list invoices
     */
    public function get_invoices($request) {
        $args = array(
            'org_id'         => $request->get_param('org_id'),
            'customer_id'    => $request->get_param('customer_id'),
            'status'         => $request->get_param('status'),
            'payment_status' => $request->get_param('payment_status'),
            'mode'           => $request->get_param('mode'),
            'date_from'      => $request->get_param('date_from'),
            'date_to'        => $request->get_param('date_to'),
            'orderby'        => $request->get_param('orderby') ?: 'created_at',
            'order'          => $request->get_param('order') ?: 'DESC',
            'limit'          => $request->get_param('limit') ?: 50,
            'offset'         => $request->get_param('offset') ?: 0,
        );
        $invoices = $this->invoices()->get_invoices($args);
        return $this->success(array(
            'invoices' => $invoices,
            'total'    => count($invoices),
        ));
    }

    /**
     * GET /invoice/{id} — get single invoice
     */
    public function get_invoice($request) {
        $invoice = $this->invoices()->get_invoice((int) $request->get_param('id'));
        if (!$invoice) {
            return $this->error('Invoice not found', 'not_found', 404);
        }
        return $this->success($invoice);
    }

    /**
     * POST /invoice — create invoice
     */
    public function create_invoice($request) {
        $data = $request->get_params();
        $result = $this->invoices()->create_invoice($data);
        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), $result->get_error_code(), 400);
        }
        $invoice = $this->invoices()->get_invoice($result);
        return $this->success($invoice, 201);
    }

    /**
     * PUT /invoice/{id} — update invoice
     */
    public function update_invoice($request) {
        $id = (int) $request->get_param('id');
        $data = $request->get_params();
        // Remove route param
        unset($data['id']);
        $result = $this->invoices()->update_invoice($id, $data);
        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), $result->get_error_code(), 400);
        }
        $invoice = $this->invoices()->get_invoice($id);
        return $this->success($invoice);
    }

    /**
     * POST /invoice/{id}/submit
     */
    public function submit_invoice($request) {
        return $this->transition_result(
            $this->invoices()->submit_invoice((int) $request->get_param('id'))
        );
    }

    /**
     * POST /invoice/{id}/approve
     */
    public function approve_invoice($request) {
        return $this->transition_result(
            $this->invoices()->approve_invoice((int) $request->get_param('id'))
        );
    }

    /**
     * POST /invoice/{id}/post
     */
    public function post_invoice($request) {
        $id = (int) $request->get_param('id');
        $posted_by = $request->get_param('posted_by') ?: get_current_user_id();
        return $this->transition_result(
            $this->invoices()->post_invoice($id, $posted_by)
        );
    }

    /**
     * POST /invoice/{id}/void
     */
    public function void_invoice($request) {
        $id = (int) $request->get_param('id');
        $reason = $request->get_param('reason') ?: '';
        return $this->transition_result(
            $this->invoices()->void_invoice($id, $reason)
        );
    }

    /**
     * POST /invoice/{id}/return-to-draft
     */
    public function return_to_draft($request) {
        return $this->transition_result(
            $this->invoices()->return_to_draft((int) $request->get_param('id'))
        );
    }

    /**
     * POST /invoice/{id}/payment — record payment
     */
    public function record_payment($request) {
        $data = array(
            'invoice_id'     => (int) $request->get_param('id'),
            'amount'         => (float) $request->get_param('amount'),
            'payment_method' => $request->get_param('payment_method') ?: 'manual',
            'gateway_ref'    => $request->get_param('gateway_ref') ?: '',
            'cash_account'   => (int) $request->get_param('cash_account') ?: 0,
            'notes'          => $request->get_param('notes') ?: '',
            'payment_date'   => $request->get_param('payment_date') ?: current_time('mysql'),
            'created_by'     => get_current_user_id(),
        );
        $result = $this->invoices()->record_payment($data);
        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), $result->get_error_code(), 400);
        }
        $invoice = $this->invoices()->get_invoice($data['invoice_id']);
        return $this->success(array(
            'payment' => $result,
            'invoice' => $invoice,
        ));
    }

    /**
     * GET /invoice/{id}/allocations
     */
    public function get_allocations($request) {
        $allocations = $this->invoices()->get_allocations((int) $request->get_param('id'));
        return $this->success(array('allocations' => $allocations));
    }

    /**
     * Helper: wrap a state transition result
     */
    private function transition_result($result) {
        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), $result->get_error_code(), 400);
        }
        return $this->success(array('success' => true));
    }

    // ══════════════════════════════════════════════════════════════════════
    // CREDIT NOTE ENDPOINTS
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /credit-note — list credit notes (simple wrapper around apply_credit_to_invoice scope)
     */
    public function get_credit_notes($request) {
        $invoices = $this->invoices();
        $invoices->register_table_names();
        global $wpdb;
        $table = $wpdb->orabooks_credit_notes;

        $org_id = $request->get_param('org_id');
        $customer_id = $request->get_param('customer_id');
        $status = $request->get_param('status');

        $where = array('1=1');
        $values = array();
        if ($org_id) {
            $where[] = 'org_id = %d';
            $values[] = (int) $org_id;
        }
        if ($customer_id) {
            $where[] = 'customer_id = %d';
            $values[] = (int) $customer_id;
        }
        if ($status) {
            $where[] = 'status = %s';
            $values[] = $status;
        }

        $where_clause = implode(' AND ', $where);
        $sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT 50";
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        $notes = $wpdb->get_results($sql);
        return $this->success(array('credit_notes' => $notes));
    }

    /**
     * POST /credit-note — create credit note
     */
    public function create_credit_note($request) {
        $data = array(
            'org_id'      => (int) $request->get_param('org_id'),
            'invoice_id'  => $request->get_param('invoice_id') ? (int) $request->get_param('invoice_id') : null,
            'customer_id' => (int) $request->get_param('customer_id'),
            'amount'      => (float) $request->get_param('amount'),
            'reason'      => $request->get_param('reason') ?: '',
            'mode'        => $request->get_param('mode') ?: 'business',
        );
        $result = $this->invoices()->create_credit_note($data);
        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), $result->get_error_code(), 400);
        }
        return $this->success(array('credit_note_id' => $result), 201);
    }

    /**
     * POST /credit-note/{id}/post
     */
    public function post_credit_note($request) {
        return $this->transition_result(
            $this->invoices()->post_credit_note((int) $request->get_param('id'))
        );
    }

    /**
     * POST /credit-note/{id}/void — void a draft credit note
     */
    public function void_credit_note($request) {
        global $wpdb;
        $id = (int) $request->get_param('id');
        $reason = $request->get_param('reason') ?: '';

        $invoices = $this->invoices();
        $invoices->register_table_names();

        // Direct status transition for credit notes
        $cn = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->orabooks_credit_notes} WHERE id = %d", $id
        ));

        if (!$cn) {
            return $this->error('Credit note not found', 'not_found', 404);
        }
        if ($cn->status !== 'draft') {
            return $this->error('Only draft credit notes can be voided', 'invalid_status', 400);
        }

        $wpdb->update(
            $wpdb->orabooks_credit_notes,
            array(
                'status'    => 'void',
                'voided_at' => current_time('mysql'),
            ),
            array('id' => $id)
        );

        do_action('orabooks_security_event', 'credit_note_voided', array(
            'credit_note_id' => $id,
            'reason'         => $reason,
        ));

        return $this->success(array('success' => true));
    }

    // ══════════════════════════════════════════════════════════════════════
    // WALLET ENDPOINTS
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /wallet — get customer wallet
     */
    public function get_wallet($request) {
        $org_id = (int) $request->get_param('org_id');
        $customer_id = (int) $request->get_param('customer_id');
        $wallet = $this->invoices()->get_wallet($org_id, $customer_id);
        return $this->success($wallet);
    }

    /**
     * PUT /wallet — update wallet settings (credit limit, hold, auto-apply, balance)
     */
    public function update_wallet($request) {
        $org_id = (int) $request->get_param('org_id');
        $customer_id = (int) $request->get_param('customer_id');
        $invoices = $this->invoices();

        if ($request->has_param('credit_limit')) {
            $result = $invoices->update_wallet_credit_limit($org_id, $customer_id, (float) $request->get_param('credit_limit'));
            if (is_wp_error($result)) {
                return $this->error($result->get_error_message(), $result->get_error_code(), 400);
            }
        }

        if ($request->has_param('credit_hold')) {
            $result = $invoices->set_credit_hold($org_id, $customer_id, (bool) $request->get_param('credit_hold'));
            if (is_wp_error($result)) {
                return $this->error($result->get_error_message(), $result->get_error_code(), 400);
            }
        }

        if ($request->has_param('auto_apply_credit')) {
            $result = $invoices->set_auto_apply_credit($org_id, $customer_id, (bool) $request->get_param('auto_apply_credit'));
            if (is_wp_error($result)) {
                return $this->error($result->get_error_message(), $result->get_error_code(), 400);
            }
        }

        if ($request->has_param('balance')) {
            $reason = $request->get_param('balance_reason') ?: 'API balance adjustment';
            $result = $invoices->update_wallet_balance($org_id, $customer_id, (float) $request->get_param('balance'), $reason);
            if (is_wp_error($result)) {
                return $this->error($result->get_error_message(), $result->get_error_code(), 400);
            }
        }

        $wallet = $invoices->get_wallet($org_id, $customer_id);
        return $this->success($wallet);
    }

    /**
     * GET /wallet/transactions — get wallet transaction history
     */
    public function get_wallet_transactions($request) {
        $org_id = (int) $request->get_param('org_id');
        $customer_id = (int) $request->get_param('customer_id');
        $limit = (int) $request->get_param('limit') ?: 50;
        $transactions = $this->invoices()->get_wallet_transactions($org_id, $customer_id, $limit);
        return $this->success(array(
            'transactions' => $transactions,
            'total'        => count($transactions),
        ));
    }

    // ══════════════════════════════════════════════════════════════════════
    // CUSTOMER ACTIVE STATUS ENDPOINTS
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /wallet/active-status — get customer active status
     */
    public function get_active_status($request) {
        $org_id = (int) $request->get_param('org_id');
        $customer_id = (int) $request->get_param('customer_id');
        $status = $this->invoices()->get_customer_active_status($org_id, $customer_id);
        return $this->success($status);
    }

    /**
     * PUT /wallet/active-status — toggle active/inactive
     */
    public function update_active_status($request) {
        $org_id = (int) $request->get_param('org_id');
        $customer_id = (int) $request->get_param('customer_id');
        $is_active = (bool) $request->get_param('is_active');
        $reason = $request->get_param('reason') ?: '';
        $result = $this->invoices()->set_customer_active_status($org_id, $customer_id, $is_active, $reason);
        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), $result->get_error_code(), 400);
        }
        $status = $this->invoices()->get_customer_active_status($org_id, $customer_id);
        return $this->success($status);
    }

    // ══════════════════════════════════════════════════════════════════════
    // AR AGING ENDPOINT
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /ar-aging — get AR aging report
     */
    public function get_ar_aging($request) {
        $org_id = (int) $request->get_param('org_id');
        $detailed = (bool) $request->get_param('detailed');
        $aging = $this->invoices()->get_ar_aging($org_id, $detailed);
        return $this->success($aging);
    }

    // ══════════════════════════════════════════════════════════════════════
    // ARGUMENT SCHEMAS
    // ══════════════════════════════════════════════════════════════════════

    private function get_invoices_args() {
        return array(
            'org_id'         => array('required' => false, 'type' => 'integer'),
            'customer_id'    => array('required' => false, 'type' => 'integer'),
            'status'         => array('required' => false, 'type' => 'string'),
            'payment_status' => array('required' => false, 'type' => 'string'),
            'mode'           => array('required' => false, 'type' => 'string'),
            'date_from'      => array('required' => false, 'type' => 'string'),
            'date_to'        => array('required' => false, 'type' => 'string'),
            'orderby'        => array('required' => false, 'type' => 'string'),
            'order'          => array('required' => false, 'type' => 'string'),
            'limit'          => array('required' => false, 'type' => 'integer', 'default' => 50),
            'offset'         => array('required' => false, 'type' => 'integer', 'default' => 0),
        );
    }

    private function create_invoice_args() {
        return array(
            'org_id'        => array('required' => true, 'type' => 'integer'),
            'customer_id'   => array('required' => true, 'type' => 'integer'),
            'customer_name' => array('required' => false, 'type' => 'string'),
            'customer_email'=> array('required' => false, 'type' => 'string'),
            'line_items'    => array('required' => false, 'type' => 'array'),
            'subtotal'      => array('required' => false, 'type' => 'number'),
            'discount_total'=> array('required' => false, 'type' => 'number'),
            'tax_total'     => array('required' => false, 'type' => 'number'),
            'total'         => array('required' => false, 'type' => 'number'),
            'currency'      => array('required' => false, 'type' => 'string'),
            'due_date'      => array('required' => false, 'type' => 'string'),
            'notes'         => array('required' => false, 'type' => 'string'),
            'terms'         => array('required' => false, 'type' => 'string'),
            'mode'          => array('required' => false, 'type' => 'string', 'default' => 'business'),
            'source_type'   => array('required' => false, 'type' => 'string'),
            'source_id'     => array('required' => false, 'type' => 'integer'),
        );
    }

    private function get_credit_notes_args() {
        return array(
            'org_id'      => array('required' => false, 'type' => 'integer'),
            'customer_id' => array('required' => false, 'type' => 'integer'),
            'status'      => array('required' => false, 'type' => 'string'),
        );
    }

    private function create_credit_note_args() {
        return array(
            'org_id'      => array('required' => true, 'type' => 'integer'),
            'invoice_id'  => array('required' => false, 'type' => 'integer'),
            'customer_id' => array('required' => true, 'type' => 'integer'),
            'amount'      => array('required' => true, 'type' => 'number'),
            'reason'      => array('required' => false, 'type' => 'string'),
            'mode'        => array('required' => false, 'type' => 'string', 'default' => 'business'),
        );
    }
}

// Initialize
OraBooks_Invoices_REST::get_instance();
