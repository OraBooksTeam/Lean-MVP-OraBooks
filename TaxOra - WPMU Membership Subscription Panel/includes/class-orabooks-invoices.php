<?php
/**
 * SL-021 – Customers / Invoices / AR / Wallet
 *
 * Manages the invoice lifecycle (Draft → Submitted → Approved → Posted),
 * credit notes, accounts receivable (AR) subledger, and customer wallet
 * (current balance / credit balance).
 *
 * Core Principles:
 * - Invoices are immutable once posted (append-only ledger)
 * - Every posted invoice generates a canonical Journal Entry (Dr AR, Cr Income)
 * - Payment allocation uses FIFO by default
 * - Wallet tracks due balance vs credit (overpayments)
 * - Credit notes adjust AR via reverse JE
 *
 * @package OraBooks_Membership
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Invoices {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Invoice statuses
     */
    const STATUS_DRAFT     = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED  = 'approved';
    const STATUS_POSTED    = 'posted';
    const STATUS_VOID      = 'void';

    /**
     * Payment statuses
     */
    const PAYMENT_UNPAID     = 'unpaid';
    const PAYMENT_PARTIAL    = 'partial';
    const PAYMENT_PAID       = 'paid';
    const PAYMENT_OVERPAID   = 'overpaid';
    const PAYMENT_WRITTEN_OFF = 'written_off';
    const PAYMENT_CANCELLED  = 'cancelled';

    /**
     * Credit note statuses
     */
    const CN_STATUS_DRAFT = 'draft';
    const CN_STATUS_POSTED = 'posted';
    const CN_STATUS_VOID   = 'void';

    /**
     * Valid invoice status transitions
     */
    const VALID_TRANSITIONS = array(
        self::STATUS_DRAFT     => array(self::STATUS_SUBMITTED, self::STATUS_VOID),
        self::STATUS_SUBMITTED => array(self::STATUS_APPROVED, self::STATUS_DRAFT, self::STATUS_VOID),
        self::STATUS_APPROVED  => array(self::STATUS_POSTED, self::STATUS_DRAFT, self::STATUS_VOID),
        self::STATUS_POSTED    => array(),  // Terminal — immutable
        self::STATUS_VOID      => array(),  // Terminal
    );

    /**
     * Valid credit note status transitions
     */
    const CN_VALID_TRANSITIONS = array(
        self::CN_STATUS_DRAFT  => array(self::CN_STATUS_POSTED, self::CN_STATUS_VOID),
        self::CN_STATUS_POSTED => array(),  // Terminal
        self::CN_STATUS_VOID   => array(),  // Terminal
    );

    /**
     * Default CoA account codes used by invoicing
     */
    const COA_ACCOUNTS_RECEIVABLE = 1100;
    const COA_SALES_REVENUE       = 4000;
    const COA_SERVICE_REVENUE     = 4100;
    const COA_SALES_TAX_PAYABLE   = 2500;

    /**
     * Re-entrancy guard: prevents refresh_wallet() ↔ auto_apply_credit() recursion
     *
     * @var bool
     */
    private $auto_applying = false;

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
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'create_invoice_tables'));
        add_action('orabooks_daily_cron', array($this, 'process_aging'));

        // Listen for subscription renewals to create invoices
        add_action('orabooks_subscription_renewed', array($this, 'on_subscription_renewed'), 10, 3);
    }

    /**
     * Create invoice-related database tables
     */
    public function create_invoice_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $this->register_table_names();

        // ================================================================
        // INVOICES TABLE
        // ================================================================
        $invoices_table = $wpdb->orabooks_invoices;
        $sql_invoices = "CREATE TABLE IF NOT EXISTS {$invoices_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            org_id bigint(20) NOT NULL,
            invoice_number varchar(50) NOT NULL,
            customer_id bigint(20) NOT NULL,
            customer_name varchar(255) DEFAULT NULL,
            customer_email varchar(255) DEFAULT NULL,
            customer_address text DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            payment_status varchar(20) NOT NULL DEFAULT 'unpaid',
            invoice_date datetime DEFAULT NULL,
            due_date datetime DEFAULT NULL,
            line_items longtext DEFAULT NULL COMMENT 'JSON array of line items',
            subtotal decimal(20,2) DEFAULT 0.00,
            discount_total decimal(20,2) DEFAULT 0.00,
            tax_total decimal(20,2) DEFAULT 0.00,
            total decimal(20,2) DEFAULT 0.00,
            paid_amount decimal(20,2) DEFAULT 0.00,
            balance_due decimal(20,2) DEFAULT 0.00,
            currency varchar(10) DEFAULT 'USD',
            notes text DEFAULT NULL,
            terms text DEFAULT NULL,
            mode varchar(20) DEFAULT 'business',
            source_type varchar(50) DEFAULT NULL COMMENT 'e.g. subscription_renewal, manual',
            source_id bigint(20) DEFAULT NULL,
            je_id bigint(20) DEFAULT NULL COMMENT 'JE created on posting',
            posted_at datetime DEFAULT NULL,
            posted_by bigint(20) DEFAULT NULL,
            voided_at datetime DEFAULT NULL,
            void_reason text DEFAULT NULL,
            credit_note_applied decimal(20,2) DEFAULT 0.00,
            snapshot longtext DEFAULT NULL COMMENT 'JSON snapshot rendered at posting time',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) DEFAULT NULL,
            updated_by bigint(20) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_invoice_number (invoice_number),
            KEY org_id (org_id),
            KEY customer_id (customer_id),
            KEY status (status),
            KEY payment_status (payment_status),
            KEY due_date (due_date),
            KEY source (source_type, source_id),
            KEY je_id (je_id),
            KEY mode (mode)
        ) {$charset_collate};";
        dbDelta($sql_invoices);

        // ================================================================
        // CREDIT NOTES TABLE
        // ================================================================
        $cn_table = $wpdb->orabooks_credit_notes;
        $sql_cn = "CREATE TABLE IF NOT EXISTS {$cn_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            org_id bigint(20) NOT NULL,
            credit_note_number varchar(50) NOT NULL,
            invoice_id bigint(20) DEFAULT NULL COMMENT 'Original invoice this CN applies to',
            customer_id bigint(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            amount decimal(20,2) NOT NULL DEFAULT 0.00,
            remaining_credit decimal(20,2) NOT NULL DEFAULT 0.00,
            reason text DEFAULT NULL,
            mode varchar(20) DEFAULT 'business',
            je_id bigint(20) DEFAULT NULL COMMENT 'Reversal JE created on posting',
            posted_at datetime DEFAULT NULL,
            voided_at datetime DEFAULT NULL,
            snapshot longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) DEFAULT NULL,
            updated_by bigint(20) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_cn_number (credit_note_number),
            KEY org_id (org_id),
            KEY invoice_id (invoice_id),
            KEY customer_id (customer_id),
            KEY status (status),
            KEY je_id (je_id)
        ) {$charset_collate};";
        dbDelta($sql_cn);

        // ================================================================
        // CUSTOMER WALLET TABLE
        // ================================================================
        $wallet_table = $wpdb->orabooks_customer_wallet;
        $sql_wallet = "CREATE TABLE IF NOT EXISTS {$wallet_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            org_id bigint(20) NOT NULL,
            customer_id bigint(20) NOT NULL,
            current_balance decimal(20,2) DEFAULT 0.00 COMMENT 'Net amount owed by customer',
            credit_balance decimal(20,2) DEFAULT 0.00 COMMENT 'Overpayments / credit note balance',
            credit_limit decimal(20,2) DEFAULT 0.00,
            credit_hold tinyint(1) DEFAULT 0,
            auto_apply_credit tinyint(1) DEFAULT 1,
            last_activity_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_org_customer (org_id, customer_id),
            KEY org_id (org_id)
        ) {$charset_collate};";
        dbDelta($sql_wallet);

        // ================================================================
        // PAYMENT ALLOCATIONS TABLE (FIFO tracking)
        // ================================================================
        $alloc_table = $wpdb->orabooks_payment_allocations;
        $sql_alloc = "CREATE TABLE IF NOT EXISTS {$alloc_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            org_id bigint(20) NOT NULL,
            invoice_id bigint(20) NOT NULL,
            payment_id varchar(100) DEFAULT NULL COMMENT 'External payment/reference ID',
            amount decimal(20,2) NOT NULL DEFAULT 0.00,
            allocation_type varchar(20) NOT NULL DEFAULT 'payment' COMMENT 'payment, credit_note, reversal',
            allocated_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_by bigint(20) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id),
            KEY org_id (org_id),
            KEY payment_id (payment_id)
        ) {$charset_collate};";
        dbDelta($sql_alloc);

        // ================================================================
        // WALLET TRANSACTIONS TABLE (audit trail)
        // ================================================================
        $wt_table = $wpdb->orabooks_wallet_transactions;
        $sql_wt = "CREATE TABLE IF NOT EXISTS {$wt_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            org_id bigint(20) NOT NULL,
            customer_id bigint(20) NOT NULL,
            type varchar(20) NOT NULL COMMENT 'credit, debit, adjustment',
            amount decimal(20,2) NOT NULL DEFAULT 0.00,
            balance_before decimal(20,2) NOT NULL DEFAULT 0.00,
            balance_after decimal(20,2) NOT NULL DEFAULT 0.00,
            reference_type varchar(50) DEFAULT NULL COMMENT 'invoice, credit_note, payment, manual',
            reference_id bigint(20) DEFAULT NULL,
            description text DEFAULT NULL,
            created_by bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY org_id (org_id),
            KEY customer_id (customer_id),
            KEY type (type),
            KEY reference (reference_type, reference_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
        dbDelta($sql_wt);

        // ================================================================
        // CUSTOMER ACTIVE STATUS TABLE
        // ================================================================
        $cas_table = $wpdb->orabooks_customer_active_status;
        $sql_cas = "CREATE TABLE IF NOT EXISTS {$cas_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            org_id bigint(20) NOT NULL,
            customer_id bigint(20) NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            active_since datetime DEFAULT NULL,
            inactive_at datetime DEFAULT NULL,
            inactivity_reason varchar(255) DEFAULT NULL,
            mode varchar(20) DEFAULT 'business',
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_org_customer (org_id, customer_id),
            KEY org_id (org_id),
            KEY is_active (is_active),
            KEY mode (mode)
        ) {$charset_collate};";
        dbDelta($sql_cas);

        error_log('[OraBooks SL-021] Invoice, credit note, wallet, allocation, wallet_tx, and customer_active tables created/verified.');
    }

    /**
     * Register table names for multisite compatibility
     */
    public function register_table_names() {
        global $wpdb;

        $prefix = is_multisite() ? $wpdb->base_prefix : $wpdb->prefix;

        if (!isset($wpdb->orabooks_invoices)) {
            $wpdb->orabooks_invoices = $prefix . 'orabooks_invoices';
        }
        if (!isset($wpdb->orabooks_credit_notes)) {
            $wpdb->orabooks_credit_notes = $prefix . 'orabooks_credit_notes';
        }
        if (!isset($wpdb->orabooks_customer_wallet)) {
            $wpdb->orabooks_customer_wallet = $prefix . 'orabooks_customer_wallet';
        }
        if (!isset($wpdb->orabooks_payment_allocations)) {
            $wpdb->orabooks_payment_allocations = $prefix . 'orabooks_payment_allocations';
        }
        if (!isset($wpdb->orabooks_wallet_transactions)) {
            $wpdb->orabooks_wallet_transactions = $prefix . 'orabooks_wallet_transactions';
        }
        if (!isset($wpdb->orabooks_customer_active_status)) {
            $wpdb->orabooks_customer_active_status = $prefix . 'orabooks_customer_active_status';
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // INVOICE CRUD
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Create a new invoice (always starts in DRAFT status)
     *
     * @param array $data Invoice data
     * @return int|WP_Error Invoice ID or error
     */
    public function create_invoice($data) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_invoices;

        $defaults = array(
            'org_id'        => 0,
            'customer_id'   => 0,
            'customer_name' => '',
            'customer_email'=> '',
            'customer_address' => '',
            'invoice_date'  => current_time('mysql'),
            'due_date'      => date('Y-m-d H:i:s', strtotime('+30 days')),
            'line_items'    => array(),
            'subtotal'      => 0.00,
            'discount_total'=> 0.00,
            'tax_total'     => 0.00,
            'total'         => 0.00,
            'currency'      => 'USD',
            'notes'         => '',
            'terms'         => '',
            'mode'          => 'business',
            'source_type'   => null,
            'source_id'     => null,
            'created_by'    => get_current_user_id(),
        );

        $data = wp_parse_args($data, $defaults);

        // Validate required fields
        if (empty($data['org_id'])) {
            return new WP_Error('missing_org_id', 'Organization ID is required');
        }
        if (empty($data['customer_id'])) {
            return new WP_Error('missing_customer', 'Customer ID is required');
        }

        // Validate mode
        if (!in_array($data['mode'], array('business', 'law', 'faith'))) {
            return new WP_Error('invalid_mode', 'Invalid mode specified');
        }

        // Encode line_items as JSON
        if (is_array($data['line_items'])) {
            $data['line_items'] = wp_json_encode($data['line_items']);
        }

        // Check credit hold before creating invoice
        $credit_check = $this->check_credit_hold($data['org_id'], $data['customer_id']);
        if (is_wp_error($credit_check)) {
            return $credit_check;
        }

        // Generate invoice number
        $invoice_number = $this->generate_invoice_number($data['org_id']);

        $balance_due = (float) $data['total'] - (float) ($data['paid_amount'] ?? 0);

        $insert_data = array(
            'org_id'         => $data['org_id'],
            'invoice_number' => $invoice_number,
            'customer_id'    => $data['customer_id'],
            'customer_name'  => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'customer_address' => $data['customer_address'],
            'status'         => self::STATUS_DRAFT,
            'payment_status' => self::PAYMENT_UNPAID,
            'invoice_date'   => $data['invoice_date'],
            'due_date'       => $data['due_date'],
            'line_items'     => $data['line_items'],
            'subtotal'       => $data['subtotal'],
            'discount_total' => $data['discount_total'],
            'tax_total'      => $data['tax_total'],
            'total'          => $data['total'],
            'balance_due'    => $balance_due,
            'currency'       => $data['currency'],
            'notes'          => $data['notes'],
            'terms'          => $data['terms'],
            'mode'           => $data['mode'],
            'source_type'    => $data['source_type'],
            'source_id'      => $data['source_id'],
            'created_by'     => $data['created_by'],
        );

        $result = $wpdb->insert($table, $insert_data);

        if ($result === false) {
            return new WP_Error('insert_failed', 'Failed to create invoice: ' . $wpdb->last_error);
        }

        $invoice_id = $wpdb->insert_id;

        // Update wallet balance
        $this->refresh_wallet($data['org_id'], $data['customer_id']);

        // Audit event
        do_action('orabooks_security_event', 'invoice_created', array(
            'invoice_id'     => $invoice_id,
            'invoice_number' => $invoice_number,
            'org_id'         => $data['org_id'],
            'customer_id'    => $data['customer_id'],
            'total'          => $data['total'],
        ));

        return $invoice_id;
    }

    /**
     * Get invoice by ID
     *
     * @param int $invoice_id Invoice ID
     * @return object|null
     */
    public function get_invoice($invoice_id) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_invoices;

        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $invoice_id
        ));

        if ($invoice && !empty($invoice->line_items)) {
            $invoice->line_items = json_decode($invoice->line_items, true);
        }

        return $invoice;
    }

    /**
     * Get invoices with optional filters
     *
     * @param array $args Filter arguments
     * @return array Invoices
     */
    public function get_invoices($args = array()) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_invoices;

        $defaults = array(
            'org_id'         => null,
            'customer_id'    => null,
            'status'         => null,
            'payment_status' => null,
            'mode'           => null,
            'date_from'      => null,
            'date_to'        => null,
            'orderby'        => 'created_at',
            'order'          => 'DESC',
            'limit'          => 50,
            'offset'         => 0,
        );
        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['org_id']) {
            $where[] = 'org_id = %d';
            $values[] = $args['org_id'];
        }
        if ($args['customer_id']) {
            $where[] = 'customer_id = %d';
            $values[] = $args['customer_id'];
        }
        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        if ($args['payment_status']) {
            $where[] = 'payment_status = %s';
            $values[] = $args['payment_status'];
        }
        if ($args['mode']) {
            $where[] = 'mode = %s';
            $values[] = $args['mode'];
        }
        if ($args['date_from']) {
            $where[] = 'invoice_date >= %s';
            $values[] = $args['date_from'];
        }
        if ($args['date_to']) {
            $where[] = 'invoice_date <= %s';
            $values[] = $args['date_to'];
        }

        $where_clause = implode(' AND ', $where);
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d",
            array_merge($values, array($args['limit'], $args['offset']))
        );

        $invoices = $wpdb->get_results($sql);

        // Decode line items for display
        foreach ($invoices as $inv) {
            if (!empty($inv->line_items)) {
                $inv->line_items = json_decode($inv->line_items, true);
            }
        }

        return $invoices;
    }

    /**
     * Update invoice (only allowed in draft/rejected status)
     *
     * @param int   $invoice_id Invoice ID
     * @param array $data       Updated fields
     * @return true|WP_Error
     */
    public function update_invoice($invoice_id, $data) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_invoices;

        $invoice = $this->get_invoice($invoice_id);
        if (!$invoice) {
            return new WP_Error('not_found', 'Invoice not found');
        }

        // Only draft and submitted (rejected) invoices can be updated
        if (!in_array($invoice->status, array(self::STATUS_DRAFT, self::STATUS_SUBMITTED))) {
            return new WP_Error('invoice_locked', 'Only draft or returned invoices can be updated');
        }

        // Prevent changing invoice_number, org_id, status via this method
        unset($data['invoice_number']);
        unset($data['org_id']);
        unset($data['id']);
        unset($data['status']);

        // Recalculate balance_due if total or paid_amount changed
        if (isset($data['total']) || isset($data['paid_amount'])) {
            $total = isset($data['total']) ? (float) $data['total'] : (float) $invoice->total;
            $paid = isset($data['paid_amount']) ? (float) $data['paid_amount'] : (float) $invoice->paid_amount;
            $data['balance_due'] = $total - $paid;
        }

        // Re-encode line_items if array
        if (isset($data['line_items']) && is_array($data['line_items'])) {
            $data['line_items'] = wp_json_encode($data['line_items']);
        }

        $data['updated_by'] = get_current_user_id();

        $before = (array) $invoice;
        $wpdb->update($table, $data, array('id' => $invoice_id));

        // Audit
        do_action('orabooks_security_event', 'invoice_updated', array(
            'invoice_id' => $invoice_id,
            'invoice_number' => $invoice->invoice_number,
        ));

        return true;
    }

    // ══════════════════════════════════════════════════════════════════════
    // INVOICE STATE MACHINE
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Submit invoice for approval (Draft → Submitted)
     *
     * @param int $invoice_id Invoice ID
     * @return true|WP_Error
     */
    public function submit_invoice($invoice_id) {
        $result = $this->transition_status($invoice_id, self::STATUS_SUBMITTED);
        if (!is_wp_error($result)) {
            do_action('orabooks_security_event', 'invoice_submitted', array(
                'invoice_id' => $invoice_id,
            ));
        }
        return $result;
    }

    /**
     * Approve invoice (Submitted → Approved)
     *
     * @param int $invoice_id Invoice ID
     * @return true|WP_Error
     */
    public function approve_invoice($invoice_id) {
        $result = $this->transition_status($invoice_id, self::STATUS_APPROVED);
        if (!is_wp_error($result)) {
            do_action('orabooks_security_event', 'invoice_approved', array(
                'invoice_id' => $invoice_id,
            ));
        }
        return $result;
    }

    /**
     * Return invoice to draft (Submitted/Approved → Draft)
     *
     * @param int $invoice_id Invoice ID
     * @return true|WP_Error
     */
    public function return_to_draft($invoice_id) {
        return $this->transition_status($invoice_id, self::STATUS_DRAFT);
    }

    /**
     * Post invoice — creates Journal Entry, takes snapshot, locks it (Approved → Posted)
     *
     * Dr Accounts Receivable (1100)   Total
     *   Cr Sales Revenue (4000)       Subtotal
     *   Cr Sales Tax Payable (2500)   Tax Total
     *
     * @param int $invoice_id Invoice ID
     * @param int $posted_by  User ID posting
     * @return true|WP_Error
     */
    public function post_invoice($invoice_id, $posted_by = null) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_invoices;

        $invoice = $this->get_invoice($invoice_id);
        if (!$invoice) {
            return new WP_Error('not_found', 'Invoice not found');
        }

        // Must be approved to post
        if ($invoice->status !== self::STATUS_APPROVED) {
            return new WP_Error('invalid_status', 'Invoice must be approved before posting');
        }

        $posted_by = $posted_by ?: get_current_user_id();

        // ── 1. Create Journal Entry ──────────────────────────────────────
        if (!class_exists('OraBooks_Journal_Entry')) {
            return new WP_Error('missing_je_engine', 'Journal Entry engine not available');
        }

        $je = OraBooks_Journal_Entry::get_instance();
        $lines = array();

        // Dr Accounts Receivable (Asset)
        $lines[] = array(
            'account_id'  => self::COA_ACCOUNTS_RECEIVABLE,
            'line_type'   => 'debit',
            'amount'      => (float) $invoice->total,
            'description' => sprintf('Invoice %s', $invoice->invoice_number),
        );

        // Cr Sales Revenue / Service Revenue
        $revenue_account = !empty($invoice->source_type) && $invoice->source_type === 'subscription_renewal'
            ? self::COA_SERVICE_REVENUE
            : self::COA_SALES_REVENUE;

        $lines[] = array(
            'account_id'  => $revenue_account,
            'line_type'   => 'credit',
            'amount'      => (float) $invoice->subtotal,
            'description' => sprintf('Invoice %s revenue', $invoice->invoice_number),
        );

        // Cr Sales Tax Payable (if tax > 0)
        if ((float) $invoice->tax_total > 0) {
            $lines[] = array(
                'account_id'  => self::COA_SALES_TAX_PAYABLE,
                'line_type'   => 'credit',
                'amount'      => (float) $invoice->tax_total,
                'description' => sprintf('Invoice %s tax', $invoice->invoice_number),
            );
        }

        $je_id = $je->create_journal_entry(array(
            'description' => sprintf(
                __('SL-021: Invoice %s posted — Dr AR, Cr Revenue', 'orabooks'),
                $invoice->invoice_number
            ),
            'mode'        => $invoice->mode,
            'source_type' => 'invoice',
            'source_id'   => $invoice_id,
            'entry_date'  => current_time('mysql'),
            'lines'       => $lines,
            'created_by'  => $posted_by,
        ));

        if (is_wp_error($je_id)) {
            return $je_id;
        }

        // Post the JE immediately (auto-post for invoice posting)
        $post_result = $je->post_journal_entry($je_id, $posted_by);
        if (is_wp_error($post_result)) {
            return $post_result;
        }

        // ── 2. Take invoice snapshot ────────────────────────────────────
        $snapshot = wp_json_encode(array(
            'invoice_number' => $invoice->invoice_number,
            'customer_id'    => $invoice->customer_id,
            'customer_name'  => $invoice->customer_name,
            'invoice_date'   => $invoice->invoice_date,
            'due_date'       => $invoice->due_date,
            'line_items'     => $invoice->line_items,
            'subtotal'       => $invoice->subtotal,
            'discount_total' => $invoice->discount_total,
            'tax_total'      => $invoice->tax_total,
            'total'          => $invoice->total,
            'currency'       => $invoice->currency,
            'notes'          => $invoice->notes,
            'terms'          => $invoice->terms,
            'posted_at'      => current_time('mysql'),
            'schema_version' => '1.0',
        ));

        // ── 3. Update invoice ───────────────────────────────────────────
        $wpdb->update(
            $table,
            array(
                'status'         => self::STATUS_POSTED,
                'je_id'          => $je_id,
                'posted_at'      => current_time('mysql'),
                'posted_by'      => $posted_by,
                'snapshot'       => $snapshot,
                'updated_by'     => $posted_by,
                'balance_due'    => (float) $invoice->total - (float) $invoice->paid_amount,
            ),
            array('id' => $invoice_id)
        );

        // ── 4. Refresh wallet ──────────────────────────────────────────
        $this->refresh_wallet($invoice->org_id, $invoice->customer_id);

        // ── 5. Audit ───────────────────────────────────────────────────
        do_action('orabooks_security_event', 'invoice_posted', array(
            'invoice_id'     => $invoice_id,
            'invoice_number' => $invoice->invoice_number,
            'org_id'         => $invoice->org_id,
            'total'          => $invoice->total,
            'je_id'          => $je_id,
        ));

        return true;
    }

    /**
     * Void an invoice (Draft/Submitted/Approved → Void)
     *
     * @param int    $invoice_id Invoice ID
     * @param string $reason     Void reason
     * @return true|WP_Error
     */
    public function void_invoice($invoice_id, $reason = '') {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_invoices;

        $invoice = $this->get_invoice($invoice_id);
        if (!$invoice) {
            return new WP_Error('not_found', 'Invoice not found');
        }

        // Cannot void a posted invoice (must use credit note instead)
        if ($invoice->status === self::STATUS_POSTED) {
            return new WP_Error('posted_cannot_void', 'Posted invoices cannot be voided. Use a credit note instead.');
        }

        $result = $this->transition_status($invoice_id, self::STATUS_VOID);
        if (is_wp_error($result)) {
            return $result;
        }

        // Update void metadata
        $wpdb->update(
            $table,
            array(
                'voided_at'   => current_time('mysql'),
                'void_reason' => $reason,
                'payment_status' => 'cancelled',
                'updated_by'  => get_current_user_id(),
            ),
            array('id' => $invoice_id)
        );

        do_action('orabooks_security_event', 'invoice_voided', array(
            'invoice_id'     => $invoice_id,
            'invoice_number' => $invoice->invoice_number,
            'reason'         => $reason,
        ));

        return true;
    }

    /**
     * Transition invoice status with validation
     *
     * @param int    $invoice_id Invoice ID
     * @param string $new_status Target status
     * @return true|WP_Error
     */
    private function transition_status($invoice_id, $new_status) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_invoices;

        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $invoice_id
        ));

        if (!$invoice) {
            return new WP_Error('not_found', 'Invoice not found');
        }

        $current = $invoice->status;

        // Check if transition is valid
        if (!isset(self::VALID_TRANSITIONS[$current]) || !in_array($new_status, self::VALID_TRANSITIONS[$current], true)) {
            return new WP_Error(
                'invalid_transition',
                sprintf(
                    __('Cannot transition invoice from %s to %s.', 'orabooks'),
                    $current,
                    $new_status
                )
            );
        }

        $wpdb->update(
            $table,
            array(
                'status'     => $new_status,
                'updated_by' => get_current_user_id(),
            ),
            array('id' => $invoice_id)
        );

        return true;
    }

    // ══════════════════════════════════════════════════════════════════════
    // PAYMENT RECORDING
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Record a payment against an invoice.
     *
     * Creates a JE: Dr Cash/Bank (specified account), Cr AR (1100).
     * Updates invoice paid_amount, balance_due, payment_status.
     * Records FIFO allocation in orabooks_payment_allocations.
     * Overpayments go to wallet credit_balance.
     *
     * @param array $data Payment data:
     *   - invoice_id    (int, required) Invoice to pay
     *   - amount        (float, required) Payment amount
     *   - payment_date  (string, optional) Payment date, defaults to now
     *   - payment_method (string, optional) e.g. cash, bank_transfer, stripe
     *   - gateway_ref   (string, optional) External gateway transaction ID
     *   - cash_account  (int, optional) CoA code for cash/bank account (default: 1100 — same as AR, or configurable per org)
     *   - notes         (string, optional) Payment notes
     *   - created_by    (int, optional) User ID recording payment
     * @return array|WP_Error { payment_id, allocation_id, je_id } or error
     */
    public function record_payment($data) {
        global $wpdb;
        $this->register_table_names();

        $defaults = array(
            'invoice_id'    => 0,
            'amount'        => 0,
            'payment_date'  => current_time('mysql'),
            'payment_method'=> 'manual',
            'gateway_ref'   => '',
            'cash_account'  => 0,  // 0 means skip JE, caller provides JE separately
            'notes'         => '',
            'created_by'    => get_current_user_id(),
        );
        $data = wp_parse_args($data, $defaults);

        // Validate
        if (empty($data['invoice_id'])) {
            return new WP_Error('missing_invoice', 'Invoice ID is required');
        }
        if ((float) $data['amount'] <= 0) {
            return new WP_Error('invalid_amount', 'Payment amount must be positive');
        }

        $invoice = $this->get_invoice($data['invoice_id']);
        if (!$invoice) {
            return new WP_Error('invoice_not_found', 'Invoice not found');
        }

        // Only posted invoices can receive payments
        if ($invoice->status !== self::STATUS_POSTED) {
            return new WP_Error('invoice_not_posted', 'Can only record payments against posted invoices');
        }

        $amount = (float) $data['amount'];
        $remaining = (float) $invoice->balance_due;
        $apply = min($amount, $remaining);
        $new_paid = (float) $invoice->paid_amount + $apply;
        $new_balance = (float) $invoice->total - $new_paid;

        // Determine new payment status
        if ($new_balance <= 0.005) {
            $payment_status = self::PAYMENT_PAID;
        } elseif ($new_paid > 0) {
            $payment_status = self::PAYMENT_PARTIAL;
        } else {
            $payment_status = self::PAYMENT_UNPAID;
        }
        if ($new_paid > (float) $invoice->total) {
            $payment_status = self::PAYMENT_OVERPAID;
        }

        // ── 1. Create Journal Entry (Dr Cash, Cr AR) ───────────────────
        $je_id = null;
        if ((int) $data['cash_account'] > 0 && class_exists('OraBooks_Journal_Entry')) {
            $je = OraBooks_Journal_Entry::get_instance();
            $lines = array();

            // Dr Cash/Bank account
            $lines[] = array(
                'account_id'  => (int) $data['cash_account'],
                'line_type'   => 'debit',
                'amount'      => $apply,
                'description' => sprintf('Payment for invoice %s', $invoice->invoice_number),
            );

            // Cr Accounts Receivable
            $lines[] = array(
                'account_id'  => self::COA_ACCOUNTS_RECEIVABLE,
                'line_type'   => 'credit',
                'amount'      => $apply,
                'description' => sprintf('Payment for invoice %s', $invoice->invoice_number),
            );

            $je_id = $je->create_journal_entry(array(
                'description' => sprintf(
                    __('SL-021: Payment recorded for invoice %s', 'orabooks'),
                    $invoice->invoice_number
                ),
                'mode'        => $invoice->mode,
                'source_type' => 'payment',
                'source_id'   => $data['invoice_id'],
                'entry_date'  => $data['payment_date'],
                'lines'       => $lines,
                'created_by'  => $data['created_by'],
            ));

            if (is_wp_error($je_id)) {
                return $je_id;
            }

            // Auto-post the payment JE
            $post_result = $je->post_journal_entry($je_id, $data['created_by']);
            if (is_wp_error($post_result)) {
                return $post_result;
            }
        }

        // ── 2. Update invoice ──────────────────────────────────────────
        $inv_table = $wpdb->orabooks_invoices;
        $wpdb->update(
            $inv_table,
            array(
                'paid_amount'    => $new_paid,
                'balance_due'    => $new_balance,
                'payment_status' => $payment_status,
                'updated_by'     => $data['created_by'],
            ),
            array('id' => $data['invoice_id'])
        );

        // ── 3. Record FIFO allocation ──────────────────────────────────
        $this->record_allocation(
            $invoice->org_id,
            $data['invoice_id'],
            $apply,
            'payment'
        );

        // ── 4. Overpayment goes to wallet credit ───────────────────────
        if ($apply < $amount) {
            $this->add_wallet_credit(
                $invoice->org_id,
                $invoice->customer_id,
                $amount - $apply,
                'invoice',
                $data['invoice_id']
            );
        }

        // ── 5. Refresh wallet ─────────────────────────────────────────
        $this->refresh_wallet($invoice->org_id, $invoice->customer_id);

        // ── 6. Audit ──────────────────────────────────────────────────
        do_action('orabooks_security_event', 'payment_recorded', array(
            'invoice_id'     => $data['invoice_id'],
            'invoice_number' => $invoice->invoice_number,
            'amount'         => $apply,
            'payment_status' => $payment_status,
            'je_id'          => $je_id,
            'gateway_ref'    => $data['gateway_ref'],
            'payment_method' => $data['payment_method'],
        ));

        return array(
            'success'         => true,
            'invoice_id'      => $data['invoice_id'],
            'amount_applied'  => $apply,
            'overpayment'     => max(0, $amount - $apply),
            'payment_status'  => $payment_status,
            'je_id'           => $je_id,
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    // CREDIT NOTES
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Create a credit note (always starts in DRAFT)
     *
     * @param array $data Credit note data
     * @return int|WP_Error
     */
    public function create_credit_note($data) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_credit_notes;

        $defaults = array(
            'org_id'      => 0,
            'invoice_id'  => null,
            'customer_id' => 0,
            'amount'      => 0.00,
            'reason'      => '',
            'mode'        => 'business',
            'created_by'  => get_current_user_id(),
        );
        $data = wp_parse_args($data, $defaults);

        if (empty($data['org_id']) || empty($data['customer_id']) || $data['amount'] <= 0) {
            return new WP_Error('invalid_data', 'Org ID, Customer ID, and positive amount are required');
        }

        // If linked to an invoice, verify it exists and is posted
        if ($data['invoice_id']) {
            $invoice = $this->get_invoice($data['invoice_id']);
            if (!$invoice) {
                return new WP_Error('invoice_not_found', 'Linked invoice not found');
            }
            if ($invoice->status !== self::STATUS_POSTED) {
                return new WP_Error('invoice_not_posted', 'Credit notes can only be issued against posted invoices');
            }
            $data['org_id'] = $invoice->org_id;
            if (empty($data['customer_id'])) {
                $data['customer_id'] = $invoice->customer_id;
            }
        }

        $cn_number = $this->generate_credit_note_number($data['org_id']);

        $result = $wpdb->insert($table, array(
            'org_id'            => $data['org_id'],
            'credit_note_number' => $cn_number,
            'invoice_id'         => $data['invoice_id'],
            'customer_id'        => $data['customer_id'],
            'status'             => self::CN_STATUS_DRAFT,
            'amount'             => $data['amount'],
            'remaining_credit'   => $data['amount'],
            'reason'             => $data['reason'],
            'mode'               => $data['mode'],
            'created_by'         => $data['created_by'],
        ));

        if ($result === false) {
            return new WP_Error('insert_failed', 'Failed to create credit note: ' . $wpdb->last_error);
        }

        $cn_id = $wpdb->insert_id;

        do_action('orabooks_security_event', 'credit_note_created', array(
            'credit_note_id'     => $cn_id,
            'credit_note_number' => $cn_number,
            'invoice_id'         => $data['invoice_id'],
            'amount'             => $data['amount'],
        ));

        return $cn_id;
    }

    /**
     * Post a credit note — creates reversal JE, applies credit to invoice/wallet
     *
     * Dr Sales Revenue / Service Revenue (P&L)
     *   Cr Accounts Receivable (Asset)
     *
     * @param int $cn_id Credit note ID
     * @return true|WP_Error
     */
    public function post_credit_note($cn_id) {
        global $wpdb;
        $this->register_table_names();
        $cn_table = $wpdb->orabooks_credit_notes;

        $cn = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$cn_table} WHERE id = %d",
            $cn_id
        ));

        if (!$cn) {
            return new WP_Error('not_found', 'Credit note not found');
        }

        if ($cn->status !== self::CN_STATUS_DRAFT) {
            return new WP_Error('invalid_status', 'Only draft credit notes can be posted');
        }

        if (!class_exists('OraBooks_Journal_Entry')) {
            return new WP_Error('missing_je_engine', 'Journal Entry engine not available');
        }

        $posted_by = get_current_user_id();

        // ── Create reversal Journal Entry ──────────────────────────────
        $je = OraBooks_Journal_Entry::get_instance();
        $description = sprintf('Credit note %s', $cn->credit_note_number);

        // Dr Sales Revenue (reversal of income)
        // Cr Accounts Receivable (reduction of asset)
        $lines = array();
        $lines[] = array(
            'account_id'  => self::COA_SALES_REVENUE,
            'line_type'   => 'debit',
            'amount'      => (float) $cn->amount,
            'description' => $description,
        );
        $lines[] = array(
            'account_id'  => self::COA_ACCOUNTS_RECEIVABLE,
            'line_type'   => 'credit',
            'amount'      => (float) $cn->amount,
            'description' => $description,
        );

        $je_id = $je->create_journal_entry(array(
            'description' => sprintf(
                __('SL-021: Credit note %s posted — Dr Revenue, Cr AR', 'orabooks'),
                $cn->credit_note_number
            ),
            'mode'        => $cn->mode,
            'source_type' => 'credit_note',
            'source_id'   => $cn_id,
            'entry_date'  => current_time('mysql'),
            'lines'       => $lines,
            'created_by'  => $posted_by,
        ));

        if (is_wp_error($je_id)) {
            return $je_id;
        }

        $je->post_journal_entry($je_id, $posted_by);

        // ── Take snapshot ──────────────────────────────────────────────
        $snapshot = wp_json_encode(array(
            'credit_note_number' => $cn->credit_note_number,
            'invoice_id'         => $cn->invoice_id,
            'customer_id'        => $cn->customer_id,
            'amount'             => $cn->amount,
            'reason'             => $cn->reason,
            'posted_at'          => current_time('mysql'),
            'schema_version'     => '1.0',
        ));

        // ── Update credit note ─────────────────────────────────────────
        $wpdb->update(
            $cn_table,
            array(
                'status'           => self::CN_STATUS_POSTED,
                'je_id'            => $je_id,
                'posted_at'        => current_time('mysql'),
                'snapshot'         => $snapshot,
                'remaining_credit' => 0,
            ),
            array('id' => $cn_id)
        );

        // ── Apply credit to linked invoice ─────────────────────────────
        // Note: apply_credit_to_invoice() internally calls refresh_wallet() → auto_apply_credit()
        if ($cn->invoice_id) {
            $this->apply_credit_to_invoice($cn->invoice_id, $cn->amount, 'credit_note', $cn_id);
        } else {
            // No linked invoice — refresh wallet directly to update balances
            $this->add_wallet_credit($cn->org_id, $cn->customer_id, (float) $cn->amount, 'credit_note', $cn_id);
            $this->refresh_wallet($cn->org_id, $cn->customer_id);
        }

        do_action('orabooks_security_event', 'credit_note_posted', array(
            'credit_note_id'     => $cn_id,
            'credit_note_number' => $cn->credit_note_number,
            'invoice_id'         => $cn->invoice_id,
            'amount'             => $cn->amount,
            'je_id'              => $je_id,
        ));

        return true;
    }

    /**
     * Apply a credit (payment or credit note) to an invoice using FIFO
     *
     * @param int    $invoice_id      Invoice ID
     * @param float  $amount          Amount to apply
     * @param string $allocation_type payment, credit_note, reversal
     * @param int    $source_id       Optional source identifier
     * @return true|WP_Error
     */
    public function apply_credit_to_invoice($invoice_id, $amount, $allocation_type = 'payment', $source_id = null) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_invoices;

        $invoice = $this->get_invoice($invoice_id);
        if (!$invoice) {
            return new WP_Error('not_found', 'Invoice not found');
        }

        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'Amount must be positive');
        }

        $remaining = (float) $invoice->balance_due;
        $apply = min($amount, $remaining);
        $new_paid = (float) $invoice->paid_amount + $apply;
        $new_balance = (float) $invoice->total - $new_paid;

        // Determine new payment status
        if ($new_balance <= 0.005) {
            $payment_status = self::PAYMENT_PAID;
        } elseif ($new_paid > 0) {
            $payment_status = self::PAYMENT_PARTIAL;
        } else {
            $payment_status = self::PAYMENT_UNPAID;
        }

        // Overpaid check
        if ($new_paid > (float) $invoice->total) {
            $payment_status = self::PAYMENT_OVERPAID;
        }

        $wpdb->update(
            $table,
            array(
                'paid_amount'    => $new_paid,
                'balance_due'    => $new_balance,
                'payment_status' => $payment_status,
                'updated_by'     => get_current_user_id(),
            ),
            array('id' => $invoice_id)
        );

        // Record allocation
        $this->record_allocation($invoice->org_id, $invoice_id, $apply, $allocation_type);

        // If credit note and fully paid, also update credit note remaining
        if ($allocation_type === 'credit_note' && $source_id) {
            $this->register_table_names();
            $cn_table = $wpdb->orabooks_credit_notes;
            $wpdb->update(
                $cn_table,
                array('remaining_credit' => max(0, $amount - $apply)),
                array('id' => $source_id)
            );
        }

        // Unused credit (overpayment) goes to wallet
        if ($apply < $amount) {
            $this->add_wallet_credit($invoice->org_id, $invoice->customer_id, $amount - $apply, 'invoice', $invoice_id);
        }

        // Refresh wallet
        $this->refresh_wallet($invoice->org_id, $invoice->customer_id);

        do_action('orabooks_security_event', 'invoice_credit_applied', array(
            'invoice_id'      => $invoice_id,
            'amount'          => $apply,
            'allocation_type' => $allocation_type,
            'payment_status'  => $payment_status,
        ));

        return true;
    }

    /**
     * Record a payment allocation (FIFO tracking)
     */
    private function record_allocation($org_id, $invoice_id, $amount, $type = 'payment') {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_payment_allocations;

        $wpdb->insert($table, array(
            'org_id'          => $org_id,
            'invoice_id'      => $invoice_id,
            'amount'          => $amount,
            'allocation_type' => $type,
            'created_by'      => get_current_user_id(),
        ));
    }

    /**
     * Get payment allocations for an invoice
     *
     * @param int $invoice_id Invoice ID
     * @return array Allocations
     */
    public function get_allocations($invoice_id) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_payment_allocations;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE invoice_id = %d ORDER BY allocated_at ASC",
            $invoice_id
        ));
    }

    // ══════════════════════════════════════════════════════════════════════
    // CUSTOMER WALLET
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Get or create customer wallet
     *
     * @param int $org_id     Organization ID
     * @param int $customer_id Customer user ID
     * @return object Wallet object
     */
    public function get_wallet($org_id, $customer_id) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_customer_wallet;

        $wallet = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d AND customer_id = %d",
            $org_id,
            $customer_id
        ));

        if (!$wallet) {
            $wpdb->insert($table, array(
                'org_id'      => $org_id,
                'customer_id' => $customer_id,
            ));
            $wallet = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE org_id = %d AND customer_id = %d",
                $org_id,
                $customer_id
            ));
        }

        return $wallet;
    }

    /**
     * Add credit to customer wallet (overpayments, unused credit note amounts)
     *
     * @param int    $org_id
     * @param int    $customer_id
     * @param float  $amount
     * @param string $ref_type  Optional source reference type (invoice, credit_note, payment)
     * @param int    $ref_id    Optional source reference ID
     */
    public function add_wallet_credit($org_id, $customer_id, $amount, $ref_type = null, $ref_id = null) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_customer_wallet;

        if ((float) $amount <= 0) {
            return;
        }

        $wallet = $this->get_wallet($org_id, $customer_id);
        $old_credit = (float) $wallet->credit_balance;
        $new_credit = $old_credit + (float) $amount;

        $wpdb->update(
            $table,
            array(
                'credit_balance'  => $new_credit,
                'last_activity_at' => current_time('mysql'),
            ),
            array('id' => $wallet->id)
        );

        $this->log_wallet_transaction(
            $org_id, $customer_id,
            'credit',
            (float) $amount,
            $old_credit,
            $new_credit,
            $ref_type, $ref_id,
            $ref_type ? sprintf('Credit from %s #%d', $ref_type, $ref_id) : 'Credit added to wallet'
        );
    }

    /**
     * Refresh wallet balance based on actual invoice totals
     */
    public function refresh_wallet($org_id, $customer_id) {
        global $wpdb;
        $this->register_table_names();
        $inv_table = $wpdb->orabooks_invoices;
        $wallet_table = $wpdb->orabooks_customer_wallet;

        // Calculate actual balance from posted invoices
        $balance = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(balance_due), 0)
             FROM {$inv_table}
             WHERE org_id = %d AND customer_id = %d
               AND status = 'posted'
               AND payment_status NOT IN ('paid', 'cancelled', 'written_off')",
            $org_id,
            $customer_id
        ));

        $wallet = $this->get_wallet($org_id, $customer_id);

        $wpdb->update(
            $wallet_table,
            array(
                'current_balance' => (float) $balance,
                'last_activity_at' => current_time('mysql'),
            ),
            array('id' => $wallet->id)
        );

        // Auto-apply credit balance to unpaid invoices (with re-entrancy guard)
        if ((float) $wallet->credit_balance > 0 && $wallet->auto_apply_credit && !$this->auto_applying) {
            $this->auto_apply_credit($org_id, $customer_id);
        }
    }

    /**
     * Auto-apply wallet credit to oldest unpaid invoices
     */
    private function auto_apply_credit($org_id, $customer_id) {
        global $wpdb;
        $this->register_table_names();
        $inv_table = $wpdb->orabooks_invoices;
        $wallet_table = $wpdb->orabooks_customer_wallet;

        // Prevent re-entrancy: if apply_credit_to_invoice() → refresh_wallet() → auto_apply_credit()
        if ($this->auto_applying) {
            return;
        }
        $this->auto_applying = true;

        $wallet = $this->get_wallet($org_id, $customer_id);
        $available = (float) $wallet->credit_balance;

        if ($available <= 0) {
            $this->auto_applying = false;
            return;
        }

        // Get unpaid invoices ordered by due_date (FIFO)
        $unpaid = $wpdb->get_results($wpdb->prepare(
            "SELECT id, total, paid_amount, balance_due
             FROM {$inv_table}
             WHERE org_id = %d AND customer_id = %d
               AND status = 'posted'
               AND payment_status IN ('unpaid', 'partial')
             ORDER BY due_date ASC",
            $org_id,
            $customer_id
        ));

        foreach ($unpaid as $inv) {
            if ($available <= 0) {
                break;
            }

            $apply = min($available, (float) $inv->balance_due);

            // Decrement wallet credit in DB before applying to prevent stale reads in nested calls
            $wallet = $this->get_wallet($org_id, $customer_id);
            $old_credit = (float) $wallet->credit_balance;
            $new_credit = max(0, $old_credit - $apply);
            $wpdb->update(
                $wallet_table,
                array(
                    'credit_balance'  => $new_credit,
                    'last_activity_at' => current_time('mysql'),
                ),
                array('id' => $wallet->id)
            );

            $result = $this->apply_credit_to_invoice($inv->id, $apply, 'credit_note');

            if (is_wp_error($result)) {
                // Rollback the credit decrement on failure
                $wpdb->update(
                    $wallet_table,
                    array(
                        'credit_balance'  => $old_credit,
                        'last_activity_at' => current_time('mysql'),
                    ),
                    array('id' => $wallet->id)
                );
                error_log(sprintf(
                    '[OraBooks SL-021] Auto-apply credit failed for invoice #%d: %s',
                    $inv->id,
                    $result->get_error_message()
                ));
                continue;
            }

            // Log wallet debit transaction
            $this->log_wallet_transaction(
                $org_id, $customer_id,
                'debit',
                $apply,
                $old_credit,
                $new_credit,
                'invoice', $inv->id,
                sprintf('Auto-apply credit to invoice #%d', $inv->id)
            );

            $available -= $apply;
        }

        $this->auto_applying = false;
    }

    /**
     * Check if a customer has a credit hold that would block new invoices
     *
     * @param int $org_id
     * @param int $customer_id
     * @return true|WP_Error
     */
    public function check_credit_hold($org_id, $customer_id) {
        $wallet = $this->get_wallet($org_id, $customer_id);

        if ($wallet->credit_hold) {
            return new WP_Error(
                'credit_hold',
                __('Customer has a credit hold. New invoices cannot be created.', 'orabooks')
            );
        }

        // Check credit limit
        if ((float) $wallet->credit_limit > 0) {
            $current = abs((float) $wallet->current_balance);
            if ($current >= (float) $wallet->credit_limit) {
                return new WP_Error(
                    'credit_limit_exceeded',
                    __('Customer has exceeded their credit limit.', 'orabooks')
                );
            }
        }

        return true;
    }

    /**
     * Set customer credit hold
     *
     * @param int  $org_id
     * @param int  $customer_id
     * @param bool $hold
     * @return true|WP_Error
     */
    public function set_credit_hold($org_id, $customer_id, $hold = true) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_customer_wallet;

        $wallet = $this->get_wallet($org_id, $customer_id);
        $wpdb->update(
            $table,
            array('credit_hold' => $hold ? 1 : 0),
            array('id' => $wallet->id)
        );

        do_action('orabooks_security_event', 'customer_credit_hold', array(
            'org_id'      => $org_id,
            'customer_id' => $customer_id,
            'credit_hold' => $hold,
        ));

        return true;
    }

    /**
     * Log a wallet transaction for audit trail
     *
     * @param int    $org_id
     * @param int    $customer_id
     * @param string $type        credit, debit, adjustment
     * @param float  $amount
     * @param float  $balance_before
     * @param float  $balance_after
     * @param string $ref_type    invoice, credit_note, payment, manual
     * @param int    $ref_id
     * @param string $description
     */
    private function log_wallet_transaction($org_id, $customer_id, $type, $amount, $balance_before, $balance_after, $ref_type = null, $ref_id = null, $description = '') {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_wallet_transactions;

        $wpdb->insert($table, array(
            'org_id'         => $org_id,
            'customer_id'    => $customer_id,
            'type'           => $type,
            'amount'         => $amount,
            'balance_before' => $balance_before,
            'balance_after'  => $balance_after,
            'reference_type' => $ref_type,
            'reference_id'   => $ref_id,
            'description'    => $description,
            'created_by'     => get_current_user_id(),
        ));
    }

    /**
     * Get wallet transaction history
     *
     * @param int $org_id
     * @param int $customer_id
     * @param int $limit
     * @return array
     */
    public function get_wallet_transactions($org_id, $customer_id, $limit = 50) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_wallet_transactions;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE org_id = %d AND customer_id = %d
             ORDER BY created_at DESC
             LIMIT %d",
            $org_id,
            $customer_id,
            $limit
        ));
    }

    /**
     * Admin adjustment: update wallet balance with reason
     *
     * @param int    $org_id
     * @param int    $customer_id
     * @param float  $new_balance     New current_balance value
     * @param string $reason         Reason for the adjustment
     * @return true|WP_Error
     */
    public function update_wallet_balance($org_id, $customer_id, $new_balance, $reason = '') {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_customer_wallet;

        $wallet = $this->get_wallet($org_id, $customer_id);
        $old_balance = (float) $wallet->current_balance;
        $diff = (float) $new_balance - $old_balance;

        $wpdb->update(
            $table,
            array(
                'current_balance' => (float) $new_balance,
                'last_activity_at' => current_time('mysql'),
            ),
            array('id' => $wallet->id)
        );

        $this->log_wallet_transaction(
            $org_id, $customer_id,
            $diff >= 0 ? 'debit' : 'credit',
            abs($diff),
            $old_balance,
            (float) $new_balance,
            'manual', null,
            $reason ?: 'Admin balance adjustment'
        );

        do_action('orabooks_security_event', 'wallet_balance_adjusted', array(
            'org_id'      => $org_id,
            'customer_id' => $customer_id,
            'old_balance' => $old_balance,
            'new_balance' => (float) $new_balance,
            'reason'      => $reason,
        ));

        return true;
    }

    /**
     * Update customer credit limit
     *
     * @param int   $org_id
     * @param int   $customer_id
     * @param float $limit New credit limit (0 = no limit)
     * @return true|WP_Error
     */
    public function update_wallet_credit_limit($org_id, $customer_id, $limit) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_customer_wallet;

        $wallet = $this->get_wallet($org_id, $customer_id);
        $old_limit = (float) $wallet->credit_limit;

        $wpdb->update(
            $table,
            array(
                'credit_limit'    => max(0, (float) $limit),
                'last_activity_at' => current_time('mysql'),
            ),
            array('id' => $wallet->id)
        );

        do_action('orabooks_security_event', 'wallet_credit_limit_updated', array(
            'org_id'     => $org_id,
            'customer_id' => $customer_id,
            'old_limit'  => $old_limit,
            'new_limit'  => max(0, (float) $limit),
        ));

        return true;
    }

    /**
     * Toggle auto-apply credit setting for a customer
     *
     * @param int  $org_id
     * @param int  $customer_id
     * @param bool $enabled
     * @return true|WP_Error
     */
    public function set_auto_apply_credit($org_id, $customer_id, $enabled = true) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_customer_wallet;

        $wallet = $this->get_wallet($org_id, $customer_id);
        $wpdb->update(
            $table,
            array(
                'auto_apply_credit' => $enabled ? 1 : 0,
                'last_activity_at'  => current_time('mysql'),
            ),
            array('id' => $wallet->id)
        );

        do_action('orabooks_security_event', 'wallet_auto_apply_toggled', array(
            'org_id'      => $org_id,
            'customer_id' => $customer_id,
            'enabled'     => $enabled,
        ));

        return true;
    }

    // ══════════════════════════════════════════════════════════════════════
    // CUSTOMER ACTIVE STATUS (SL-021 source of truth)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Get or create customer active status record
     *
     * @param int $org_id
     * @param int $customer_id
     * @return object Active status object
     */
    public function get_customer_active_status($org_id, $customer_id) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_customer_active_status;

        $status = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d AND customer_id = %d",
            $org_id,
            $customer_id
        ));

        if (!$status) {
            $wpdb->insert($table, array(
                'org_id'      => $org_id,
                'customer_id' => $customer_id,
                'is_active'   => 1,
                'active_since' => current_time('mysql'),
                'mode'        => 'business',
            ));
            $status = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE org_id = %d AND customer_id = %d",
                $org_id,
                $customer_id
            ));
        }

        return $status;
    }

    /**
     * Set customer active status (is_active is the SL-021 source of truth)
     *
     * @param int    $org_id
     * @param int    $customer_id
     * @param bool   $is_active
     * @param string $reason Optional reason for deactivation
     * @return true|WP_Error
     */
    public function set_customer_active_status($org_id, $customer_id, $is_active, $reason = '') {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_customer_active_status;

        $current = $this->get_customer_active_status($org_id, $customer_id);

        $update_data = array(
            'is_active' => $is_active ? 1 : 0,
        );

        if ($is_active) {
            $update_data['active_since'] = current_time('mysql');
            $update_data['inactive_at'] = null;
            $update_data['inactivity_reason'] = null;
        } else {
            $update_data['inactive_at'] = current_time('mysql');
            $update_data['inactivity_reason'] = $reason;
        }

        $wpdb->update(
            $table,
            $update_data,
            array('id' => $current->id)
        );

        do_action('orabooks_security_event', 'customer_active_status_changed', array(
            'org_id'      => $org_id,
            'customer_id' => $customer_id,
            'was_active'  => (bool) $current->is_active,
            'is_active'   => $is_active,
            'reason'      => $reason,
        ));

        /**
         * Public hook for other modules (e.g., SL-068 commissions) to react
         */
        do_action('orabooks_customer_active_status_changed', $org_id, $customer_id, $is_active, $reason);

        return true;
    }

    /**
     * Check if a customer is active
     *
     * @param int $org_id
     * @param int $customer_id
     * @return bool
     */
    public function is_customer_active($org_id, $customer_id) {
        $status = $this->get_customer_active_status($org_id, $customer_id);
        return (bool) $status->is_active;
    }

    /**
     * Get all active customers for an org
     *
     * @param int  $org_id
     * @param int  $limit
     * @param int  $offset
     * @return array
     */
    public function get_active_customers($org_id, $limit = 50, $offset = 0) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_customer_active_status;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE org_id = %d AND is_active = 1
             ORDER BY active_since DESC
             LIMIT %d OFFSET %d",
            $org_id,
            $limit,
            $offset
        ));
    }

    // ══════════════════════════════════════════════════════════════════════
    // AR AGING REPORT
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Get AR aging report for an organization
     *
     * @param int  $org_id Organization ID
     * @param bool $detailed Include per-invoice breakdown
     * @return array Aging buckets
     */
    public function get_ar_aging($org_id, $detailed = false) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_invoices;

        $buckets = array(
            'current'  => array('label' => 'Current',  'total' => 0, 'invoices' => array()),
            '1_30'     => array('label' => '1–30 days', 'total' => 0, 'invoices' => array()),
            '31_60'    => array('label' => '31–60 days','total' => 0, 'invoices' => array()),
            '61_90'    => array('label' => '61–90 days','total' => 0, 'invoices' => array()),
            'over_90'  => array('label' => 'Over 90 days','total' => 0, 'invoices' => array()),
        );

        $invoices = $wpdb->get_results($wpdb->prepare(
            "SELECT *, DATEDIFF(NOW(), due_date) as days_overdue
             FROM {$table}
             WHERE org_id = %d
               AND status = 'posted'
               AND payment_status IN ('unpaid', 'partial')
             ORDER BY due_date ASC",
            $org_id
        ));

        $grand_total = 0;

        foreach ($invoices as $inv) {
            $inv->line_items = !empty($inv->line_items) ? json_decode($inv->line_items, true) : array();
            $days = (int) $inv->days_overdue;
            $amount = (float) $inv->balance_due;
            $grand_total += $amount;

            if ($days <= 0) {
                $bucket = 'current';
            } elseif ($days <= 30) {
                $bucket = '1_30';
            } elseif ($days <= 60) {
                $bucket = '31_60';
            } elseif ($days <= 90) {
                $bucket = '61_90';
            } else {
                $bucket = 'over_90';
            }

            $buckets[$bucket]['total'] += $amount;
            if ($detailed) {
                $buckets[$bucket]['invoices'][] = $inv;
            }
        }

        return array(
            'buckets'     => $buckets,
            'grand_total' => $grand_total,
            'org_id'      => $org_id,
            'as_of'       => current_time('mysql'),
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    // NUMBER GENERATION
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Generate a unique invoice number
     *
     * @param int $org_id Organization ID
     * @return string Invoice number
     */
    private function generate_invoice_number($org_id) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_invoices;

        $prefix = 'INV-' . date('Ymd-');
        $last = $wpdb->get_var($wpdb->prepare(
            "SELECT invoice_number FROM {$table}
             WHERE invoice_number LIKE %s
             ORDER BY id DESC LIMIT 1",
            $prefix . '%'
        ));

        if ($last) {
            $parts = explode('-', $last);
            $seq = (int) end($parts) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a unique credit note number
     *
     * @param int $org_id Organization ID
     * @return string Credit note number
     */
    private function generate_credit_note_number($org_id) {
        global $wpdb;
        $this->register_table_names();
        $table = $wpdb->orabooks_credit_notes;

        $prefix = 'CN-' . date('Ymd-');
        $last = $wpdb->get_var($wpdb->prepare(
            "SELECT credit_note_number FROM {$table}
             WHERE credit_note_number LIKE %s
             ORDER BY id DESC LIMIT 1",
            $prefix . '%'
        ));

        if ($last) {
            $parts = explode('-', $last);
            $seq = (int) end($parts) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    // ══════════════════════════════════════════════════════════════════════
    // EVENT HANDLERS
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Handle subscription renewal event — create an invoice
     *
     * @param int $subscription_id Subscription ID
     * @param int $user_id         User ID
     * @param int $order_id        Order ID
     */
    public function on_subscription_renewed($subscription_id, $user_id, $order_id) {
        global $wpdb;
        $this->register_table_names();

        // Get the org_id for this user
        $org_id = 0;
        if (class_exists('OraBooks_Organizations')) {
            $orgs = OraBooks_Organizations::get_instance();
            $orgs_table = $wpdb->base_prefix . 'orabooks_organizations';
            $org = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$orgs_table} WHERE owner_id = %d AND organization_type = 'customer' LIMIT 1",
                $user_id
            ));
            if ($org) {
                $org_id = (int) $org->id;
            }
        }

        if (empty($org_id)) {
            error_log('[OraBooks SL-021] Cannot create renewal invoice: no org found for user ' . $user_id);
            return;
        }

        // Get level info from the order
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->orabooks_orders} WHERE id = %d",
            $order_id
        ));

        $amount = $order ? (float) $order->amount : 0;
        $user_info = get_user_by('id', $user_id);

        $this->create_invoice(array(
            'org_id'        => $org_id,
            'customer_id'   => $user_id,
            'customer_name' => $user_info ? $user_info->display_name : '',
            'customer_email'=> $user_info ? $user_info->user_email : '',
            'subtotal'      => $amount,
            'total'         => $amount,
            'source_type'   => 'subscription_renewal',
            'source_id'     => $order_id,
            'terms'         => __('Payment due on receipt', 'orabooks'),
            'mode'          => 'business',
        ));
    }

    // ══════════════════════════════════════════════════════════════════════
    // CRON JOBS
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Daily cron: process aging (mark overdue, etc.)
     */
    public function process_aging() {
        // Future: Send dunning reminders, update aging flags
        // This is a placeholder for the dunning/collections integration
        error_log('[OraBooks SL-021] Daily aging processed.');
    }
}

// Initialize the invoicing system
OraBooks_Invoices::get_instance();
