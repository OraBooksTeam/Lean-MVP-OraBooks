<?php
/**
 * OraBooks Customers / Invoices / AR Module (SL-021)
 *
 * Manages the customer lifecycle, invoicing, and payment tracking.
 * Serves as the truth source for customer active status used by the
 * commission engine (SL-068).
 *
 * Key features:
 * - customers.is_active as the authoritative truth source
 * - Full invoice lifecycle (draft → sent → posted → paid/overdue)
 * - Payment tracking with partial payment support
 * - Automatic customer_active_status read model update via SL-068
 * - Revenue recognition via SL-001 posting engine integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Customers {

    private static $instance = null;

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();

            // AJAX endpoints
            add_action('wp_ajax_orabooks_customers_list', [self::$instance, 'ajax_customers_list']);
            add_action('wp_ajax_orabooks_customer_get', [self::$instance, 'ajax_customer_get']);
            add_action('wp_ajax_orabooks_customer_update', [self::$instance, 'ajax_customer_update']);
            add_action('wp_ajax_orabooks_invoices_list', [self::$instance, 'ajax_invoices_list']);
            add_action('wp_ajax_orabooks_invoice_create', [self::$instance, 'ajax_invoice_create']);
            add_action('wp_ajax_orabooks_invoice_get', [self::$instance, 'ajax_invoice_get']);
            add_action('wp_ajax_orabooks_invoice_record_payment', [self::$instance, 'ajax_record_payment']);
            add_action('wp_ajax_orabooks_customer_stats', [self::$instance, 'ajax_customer_stats']);

            // Cron jobs
            add_action('orabooks_daily_customer_status_check', [self::$instance, 'daily_customer_status_check']);
            add_action('orabooks_daily_invoice_overdue_check', [self::$instance, 'daily_invoice_overdue_check']);
        }
        return self::$instance;
    }

    // ================================================================
    // DATABASE SCHEMA
    // ================================================================

    public static function get_create_table_sql() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $tables = [];

        // SL-021: customers table (is_active truth source for commission engine)
        $table_customers = OraBooks_Database::table('customers');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_customers} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            org_id BIGINT UNSIGNED NOT NULL,
            is_active TINYINT(1) DEFAULT 1 COMMENT 'Authoritative truth source for commission engine SL-068',
            last_paid_invoice_date DATE NULL,
            lifetime_value DECIMAL(20,2) DEFAULT 0,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_user (user_id),
            FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}orabooks_users(id) ON DELETE CASCADE,
            FOREIGN KEY (org_id) REFERENCES {$wpdb->prefix}orabooks_organizations(id) ON DELETE CASCADE,
            INDEX idx_org (org_id),
            INDEX idx_active (is_active)
        ) {$charset_collate};";

        // SL-021: invoices table
        $table_invoices = OraBooks_Database::table('invoices');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_invoices} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            invoice_number VARCHAR(50) NOT NULL,
            invoice_date DATE NOT NULL,
            transaction_date DATE NOT NULL,
            due_date DATE NOT NULL,
            description TEXT NULL,
            total_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
            tax_amount DECIMAL(20,2) DEFAULT 0,
            currency CHAR(3) DEFAULT 'USD',
            payment_status ENUM('unpaid','partial','paid','overdue','cancelled') DEFAULT 'unpaid',
            workflow_status ENUM('draft','sent','posted','cancelled') DEFAULT 'draft',
            paid_amount DECIMAL(20,2) DEFAULT 0,
            paid_at TIMESTAMP NULL,
            last_payment_date DATE NULL,
            metadata JSON NULL,
            idempotency_key VARCHAR(128),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$wpdb->prefix}orabooks_organizations(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES {$table_customers}(id) ON DELETE CASCADE,
            UNIQUE KEY uk_org_invoice (org_id, invoice_number),
            UNIQUE KEY uk_idempotency (idempotency_key),
            INDEX idx_payment_status (payment_status),
            INDEX idx_workflow (workflow_status),
            INDEX idx_due_date (due_date),
            INDEX idx_transaction_date (transaction_date)
        ) {$charset_collate};";

        // SL-021: payments table
        $table_payments = OraBooks_Database::table('payments');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_payments} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            invoice_id BIGINT UNSIGNED NOT NULL,
            payment_date DATE NOT NULL,
            amount DECIMAL(20,2) NOT NULL,
            payment_method ENUM('bank_transfer','credit_card','cash','check','other') DEFAULT 'bank_transfer',
            reference VARCHAR(255) NULL,
            notes TEXT NULL,
            idempotency_key VARCHAR(128),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$wpdb->prefix}orabooks_organizations(id) ON DELETE CASCADE,
            FOREIGN KEY (invoice_id) REFERENCES {$table_invoices}(id) ON DELETE CASCADE,
            UNIQUE KEY uk_idempotency_payment (idempotency_key),
            INDEX idx_invoice (invoice_id),
            INDEX idx_payment_date (payment_date)
        ) {$charset_collate};";

        // Seed customers for existing users who are partners' customers
        // Done in seed_default_customers()

        return $tables;
    }

    // ================================================================
    // SEED DEFAULT CUSTOMERS
    // ================================================================

    /**
     * Seed customer records for users referenced in partner_attributions
     * that don't already have a customers row.
     */
    public static function seed_default_customers() {
        global $wpdb;

        $table_customers = OraBooks_Database::table('customers');
        $table_attributions = OraBooks_Database::table('partner_attributions');
        $table_users = OraBooks_Database::table('users');

        // Find users referenced as customers in attributions but not yet in customers table
        $missing_customers = $wpdb->get_results(
            "SELECT DISTINCT pa.customer_user_id, u.org_id
             FROM {$table_attributions} pa
             JOIN {$table_users} u ON pa.customer_user_id = u.id
             LEFT JOIN {$table_customers} c ON c.user_id = pa.customer_user_id
             WHERE c.id IS NULL"
        );

        foreach ($missing_customers as $mc) {
            $wpdb->insert(
                $table_customers,
                [
                    'user_id' => $mc->customer_user_id,
                    'org_id'  => $mc->org_id,
                    'is_active' => 1,
                ],
                ['%d', '%d', '%d']
            );
        }

        return count($missing_customers);
    }

    // ================================================================
    // CUSTOMER METHODS
    // ================================================================

    /**
     * Get or create a customer record for a user.
     */
    public static function get_or_create($user_id, $org_id) {
        global $wpdb;

        $table = OraBooks_Database::table('customers');
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d",
            $user_id
        ));

        if ($customer) {
            return $customer;
        }

        $wpdb->insert(
            $table,
            [
                'user_id'   => $user_id,
                'org_id'    => $org_id,
                'is_active' => 1,
            ],
            ['%d', '%d', '%d']
        );

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $wpdb->insert_id
        ));
    }

    /**
     * Get customer by user ID.
     */
    public static function get_by_user_id($user_id) {
        global $wpdb;
        $table = OraBooks_Database::table('customers');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, u.email, u.is_email_verified, u.created_at as user_created_at
             FROM {$table} c
             JOIN {$wpdb->prefix}orabooks_users u ON c.user_id = u.id
             WHERE c.user_id = %d",
            $user_id
        ));
    }

    /**
     * List customers for an organization with optional filters.
     */
    public static function get_list($org_id, $args = []) {
        global $wpdb;

        $table = OraBooks_Database::table('customers');
        $table_users = OraBooks_Database::table('users');
        $table_invoices = OraBooks_Database::table('invoices');

        $where = 'c.org_id = %d';
        $params = [$org_id];

        if (isset($args['is_active'])) {
            $where .= ' AND c.is_active = %d';
            $params[] = (int) $args['is_active'];
        }

        if (!empty($args['search'])) {
            $where .= ' AND (u.email LIKE %s OR c.notes LIKE %s)';
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search;
            $params[] = $search;
        }

        $limit = $args['limit'] ?? 50;
        $offset = $args['offset'] ?? 0;

        $sql = "SELECT c.*, u.email,
                       (SELECT COUNT(*) FROM {$table_invoices} WHERE customer_id = c.id) as invoice_count,
                       (SELECT COALESCE(SUM(total_amount), 0) FROM {$table_invoices} WHERE customer_id = c.id AND payment_status IN ('paid', 'partial')) as total_paid
                FROM {$table} c
                JOIN {$table_users} u ON c.user_id = u.id
                WHERE {$where}
                ORDER BY c.updated_at DESC
                LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $results = $wpdb->get_results($wpdb->prepare($sql, $params));

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$table} c JOIN {$table_users} u ON c.user_id = u.id WHERE {$where}";
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, array_slice($params, 0, count($params) - 2)));

        return [
            'customers' => $results,
            'total'     => $total,
            'page'      => ($limit > 0) ? floor($offset / $limit) + 1 : 1,
            'per_page'  => $limit,
        ];
    }

    /**
     * Update customer is_active status — this is the truth source
     * that triggers the commission engine's read model refresh.
     */
    public static function update_active_status($customer_id, $is_active) {
        global $wpdb;

        $table = OraBooks_Database::table('customers');
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $customer_id
        ));

        if (!$customer) {
            return new WP_Error('not_found', 'Customer not found');
        }

        $old_status = (bool) $customer->is_active;

        $wpdb->update(
            $table,
            ['is_active' => $is_active ? 1 : 0],
            ['id' => $customer_id],
            ['%d'],
            ['%d']
        );

        // Synchronize the commission engine's customer_active_status read model
        if (class_exists('OraBooks_Commission') && method_exists('OraBooks_Commission', 'refresh_customer_active_status')) {
            OraBooks_Commission::refresh_customer_active_status($customer->user_id);
        }

        // Audit log
        if ($old_status !== (bool) $is_active) {
            orabooks_log_event(
                $is_active ? 'customer_activated' : 'customer_deactivated',
                "Customer #{$customer->user_id} " . ($is_active ? 'activated' : 'deactivated'),
                'info',
                ['customer_id' => $customer->id, 'user_id' => $customer->user_id, 'org_id' => $customer->org_id],
                get_current_user_id(),
                $customer->org_id
            );

            // Publish event for partner commission engine
            do_action('orabooks_customer_active_status_changed', $customer->user_id, (bool) $is_active, $customer->org_id);
        }

        return true;
    }

    // ================================================================
    // INVOICE METHODS
    // ================================================================

    /**
     * Create a new invoice.
     */
    public static function create_invoice($org_id, $data) {
        global $wpdb;

        $table = OraBooks_Database::table('invoices');

        // Validate required fields
        if (empty($data['customer_id'])) {
            return new WP_Error('missing_field', 'Customer ID is required');
        }
        if (empty($data['total_amount']) || $data['total_amount'] <= 0) {
            return new WP_Error('invalid_amount', 'Total amount must be greater than 0');
        }

        // Generate invoice number if not provided
        if (empty($data['invoice_number'])) {
            $data['invoice_number'] = self::generate_invoice_number($org_id);
        }

        // Verify unique invoice number within org
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE org_id = %d AND invoice_number = %s",
            $org_id, $data['invoice_number']
        ));
        if ($existing) {
            return new WP_Error('duplicate', 'Invoice number already exists for this organization');
        }

        $invoice_date = $data['invoice_date'] ?? current_time('Y-m-d');
        $due_days = $data['due_days'] ?? 30;

        $wpdb->insert(
            $table,
            [
                'org_id'          => $org_id,
                'customer_id'     => (int) $data['customer_id'],
                'invoice_number'  => $data['invoice_number'],
                'invoice_date'    => $invoice_date,
                'transaction_date' => $data['transaction_date'] ?? $invoice_date,
                'due_date'        => $data['due_date'] ?? date('Y-m-d', strtotime($invoice_date . " +{$due_days} days")),
                'description'     => $data['description'] ?? '',
                'total_amount'    => $data['total_amount'],
                'tax_amount'      => $data['tax_amount'] ?? 0,
                'currency'        => $data['currency'] ?? 'USD',
                'payment_status'  => 'unpaid',
                'workflow_status' => $data['workflow_status'] ?? 'draft',
                'idempotency_key' => $data['idempotency_key'] ?? orabooks_uuid(),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s']
        );

        $invoice_id = $wpdb->insert_id;

        // Ensure customer record exists
        $table_customers = OraBooks_Database::table('customers');
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_customers} WHERE id = %d",
            (int) $data['customer_id']
        ));

        orabooks_log_event('invoice_created', "Invoice #{$data['invoice_number']} created for customer #{$data['customer_id']}", 'info', [
            'invoice_id'     => $invoice_id,
            'invoice_number' => $data['invoice_number'],
            'customer_id'    => (int) $data['customer_id'],
            'total_amount'   => $data['total_amount'],
            'org_id'         => $org_id,
        ], get_current_user_id(), $org_id);

        return self::get_invoice($invoice_id);
    }

    /**
     * Get a single invoice by ID.
     */
    public static function get_invoice($invoice_id) {
        global $wpdb;

        $table = OraBooks_Database::table('invoices');
        $table_customers = OraBooks_Database::table('customers');
        $table_users = OraBooks_Database::table('users');
        $table_payments = OraBooks_Database::table('payments');

        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT i.*, c.user_id as customer_user_id, u.email as customer_email
             FROM {$table} i
             JOIN {$table_customers} c ON i.customer_id = c.id
             JOIN {$table_users} u ON c.user_id = u.id
             WHERE i.id = %d",
            $invoice_id
        ));

        if (!$invoice) {
            return null;
        }

        // Get payments for this invoice
        $invoice->payments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_payments} WHERE invoice_id = %d ORDER BY payment_date DESC",
            $invoice_id
        ));

        return $invoice;
    }

    /**
     * List invoices for an organization with filters.
     */
    public static function get_invoices_list($org_id, $args = []) {
        global $wpdb;

        $table = OraBooks_Database::table('invoices');
        $table_customers = OraBooks_Database::table('customers');
        $table_users = OraBooks_Database::table('users');

        $where = 'i.org_id = %d';
        $params = [$org_id];

        if (!empty($args['customer_id'])) {
            $where .= ' AND i.customer_id = %d';
            $params[] = (int) $args['customer_id'];
        }

        if (!empty($args['payment_status'])) {
            $where .= ' AND i.payment_status = %s';
            $params[] = $args['payment_status'];
        }

        if (!empty($args['workflow_status'])) {
            $where .= ' AND i.workflow_status = %s';
            $params[] = $args['workflow_status'];
        }

        if (!empty($args['from_date'])) {
            $where .= ' AND i.transaction_date >= %s';
            $params[] = $args['from_date'];
        }

        if (!empty($args['to_date'])) {
            $where .= ' AND i.transaction_date <= %s';
            $params[] = $args['to_date'];
        }

        $limit = $args['limit'] ?? 50;
        $offset = $args['offset'] ?? 0;

        $sql = "SELECT i.*, u.email as customer_email,
                       (SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}orabooks_payments WHERE invoice_id = i.id) as total_paid_amount
                FROM {$table} i
                JOIN {$table_customers} c ON i.customer_id = c.id
                JOIN {$table_users} u ON c.user_id = u.id
                WHERE {$where}
                ORDER BY i.transaction_date DESC
                LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $results = $wpdb->get_results($wpdb->prepare($sql, $params));

        // Total count
        $count_params = array_slice($params, 0, count($params) - 2);
        $count_sql = "SELECT COUNT(*) FROM {$table} i WHERE {$where}";
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $count_params));

        return [
            'invoices'  => $results,
            'total'     => $total,
            'page'      => ($limit > 0) ? floor($offset / $limit) + 1 : 1,
            'per_page'  => $limit,
        ];
    }

    /**
     * Record a payment against an invoice.
     * Automatically updates invoice payment_status and the customer's
     * last_paid_invoice_date and is_active status.
     */
    public static function record_payment($org_id, $invoice_id, $data) {
        global $wpdb;

        $table_invoices = OraBooks_Database::table('invoices');
        $table_payments = OraBooks_Database::table('payments');
        $table_customers = OraBooks_Database::table('customers');

        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_invoices} WHERE id = %d AND org_id = %d",
            $invoice_id, $org_id
        ));

        if (!$invoice) {
            return new WP_Error('not_found', 'Invoice not found');
        }

        if ($invoice->payment_status === 'cancelled') {
            return new WP_Error('cancelled', 'Cannot pay a cancelled invoice');
        }

        $payment_amount = (float) ($data['amount'] ?? 0);
        if ($payment_amount <= 0) {
            return new WP_Error('invalid_amount', 'Payment amount must be greater than 0');
        }

        $payment_date = $data['payment_date'] ?? current_time('Y-m-d');

        // Record the payment
        $wpdb->insert(
            $table_payments,
            [
                'org_id'       => $org_id,
                'invoice_id'   => $invoice_id,
                'payment_date' => $payment_date,
                'amount'       => $payment_amount,
                'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                'reference'    => $data['reference'] ?? '',
                'notes'        => $data['notes'] ?? '',
                'idempotency_key' => $data['idempotency_key'] ?? orabooks_uuid(),
            ],
            ['%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s']
        );

        $payment_id = $wpdb->insert_id;

        // Calculate new total paid
        $total_paid = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$table_payments} WHERE invoice_id = %d",
            $invoice_id
        ));

        // Determine new payment status
        if ($total_paid >= $invoice->total_amount) {
            $new_status = 'paid';
            $paid_at = current_time('mysql');
        } elseif ($total_paid > 0) {
            $new_status = 'partial';
            $paid_at = null;
        } else {
            $new_status = 'unpaid';
            $paid_at = null;
        }

        // Update invoice
        $wpdb->update(
            $table_invoices,
            [
                'payment_status'    => $new_status,
                'paid_amount'       => $total_paid,
                'paid_at'           => $paid_at,
                'last_payment_date' => $payment_date,
                'workflow_status'   => ($new_status === 'paid' || $new_status === 'partial') ? 'posted' : $invoice->workflow_status,
            ],
            ['id' => $invoice_id],
            ['%s', '%f', '%s', '%s', '%s'],
            ['%d']
        );

        // Update customer's last_paid_invoice_date and refresh active status
        $wpdb->update(
            $table_customers,
            [
                'last_paid_invoice_date' => $payment_date,
                'is_active'              => 1, // Payment makes customer active
            ],
            ['id' => $invoice->customer_id],
            ['%s', '%d'],
            ['%d']
        );

        // Refresh the commission engine's customer_active_status read model
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id FROM {$table_customers} WHERE id = %d",
            $invoice->customer_id
        ));
        if ($customer && class_exists('OraBooks_Commission') && method_exists('OraBooks_Commission', 'refresh_customer_active_status')) {
            OraBooks_Commission::refresh_customer_active_status($customer->user_id);
        }

        // Publish event for partner commission engine
        do_action('orabooks_customer_active_status_changed', $customer->user_id ?? 0, true, $org_id);

        orabooks_log_event('payment_recorded', "Payment of {$payment_amount} recorded for invoice #{$invoice->invoice_number}", 'info', [
            'payment_id'    => $payment_id,
            'invoice_id'    => $invoice_id,
            'invoice_number'=> $invoice->invoice_number,
            'amount'        => $payment_amount,
            'payment_status'=> $new_status,
            'customer_id'   => $invoice->customer_id,
            'org_id'        => $org_id,
        ], get_current_user_id(), $org_id);

        return [
            'payment_id'     => $payment_id,
            'invoice_id'     => $invoice_id,
            'amount'         => $payment_amount,
            'new_status'     => $new_status,
            'total_paid'     => $total_paid,
            'payment_date'   => $payment_date,
        ];
    }

    /**
     * Get customer stats for an organization (for dashboards).
     */
    public static function get_customer_stats($org_id) {
        global $wpdb;

        $table_customers = OraBooks_Database::table('customers');
        $table_invoices = OraBooks_Database::table('invoices');
        $table_payments = OraBooks_Database::table('payments');

        $stats = [];

        // Total customers
        $stats['total_customers'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_customers} WHERE org_id = %d",
            $org_id
        ));

        // Active customers
        $stats['active_customers'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_customers} WHERE org_id = %d AND is_active = 1",
            $org_id
        ));

        // Inactive customers
        $stats['inactive_customers'] = $stats['total_customers'] - $stats['active_customers'];

        // Total invoices
        $stats['total_invoices'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_invoices} WHERE org_id = %d",
            $org_id
        ));

        // Invoices by payment status
        $stats['paid_invoices'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_invoices} WHERE org_id = %d AND payment_status = 'paid'",
            $org_id
        ));
        $stats['unpaid_invoices'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_invoices} WHERE org_id = %d AND payment_status = 'unpaid'",
            $org_id
        ));
        $stats['overdue_invoices'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_invoices} WHERE org_id = %d AND payment_status = 'overdue'",
            $org_id
        ));

        // Total revenue (paid amounts)
        $stats['total_revenue'] = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$table_payments} WHERE org_id = %d",
            $org_id
        ));

        // Outstanding AR (unpaid invoice totals)
        $stats['outstanding_ar'] = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0)
             FROM {$table_invoices}
             WHERE org_id = %d AND payment_status IN ('unpaid', 'partial')",
            $org_id
        ));

        return $stats;
    }

    // ================================================================
    // CRON JOBS
    // ================================================================

    /**
     * Daily check: mark customers as inactive if they have no paid
     * invoices within the active window. Also recalc lifetime_value.
     */
    public function daily_customer_status_check() {
        global $wpdb;

        $table_customers = OraBooks_Database::table('customers');
        $table_invoices = OraBooks_Database::table('invoices');
        $config_table = OraBooks_Database::table('partner_commission_config');

        $config = $wpdb->get_row("SELECT customer_active_window_days FROM {$config_table} WHERE id = 1");
        $window_days = $config ? (int) $config->customer_active_window_days : 30;

        // Find customers whose last_paid_invoice_date is outside the window
        $inactive_count = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_customers}
             SET is_active = 0
             WHERE is_active = 1
               AND (last_paid_invoice_date IS NULL
                    OR last_paid_invoice_date < DATE_SUB(CURDATE(), INTERVAL %d DAY))",
            $window_days
        ));

        // Recalculate lifetime_value for all customers
        $wpdb->query(
            "UPDATE {$table_customers} c
             JOIN (
                 SELECT customer_id, COALESCE(SUM(total_amount), 0) as total
                 FROM {$table_invoices}
                 WHERE payment_status IN ('paid', 'partial')
                 GROUP BY customer_id
             ) i ON c.id = i.customer_id
             SET c.lifetime_value = i.total"
        );

        orabooks_log_event('customer_status_check',
            "Daily customer status check: {$inactive_count} customers marked inactive",
            'info', ['inactivated' => $inactive_count], null, null);

        // Sync with commission engine read model
        if (class_exists('OraBooks_Commission') && method_exists('OraBooks_Commission', 'refresh_all_customer_active_status')) {
            OraBooks_Commission::refresh_all_customer_active_status();
        }

        return $inactive_count;
    }

    /**
     * Daily check: mark invoices as overdue if past due_date and unpaid/partial.
     */
    public function daily_invoice_overdue_check() {
        global $wpdb;

        $table = OraBooks_Database::table('invoices');

        $overdue_count = $wpdb->query(
            "UPDATE {$table}
             SET payment_status = 'overdue'
             WHERE payment_status IN ('unpaid', 'partial')
               AND workflow_status IN ('sent', 'posted')
               AND due_date < CURDATE()"
        );

        if ($overdue_count > 0) {
            orabooks_log_event('invoice_overdue_check',
                "Daily overdue check: {$overdue_count} invoices marked overdue",
                'info', ['marked_overdue' => $overdue_count], null, null);
        }

        return $overdue_count;
    }

    /**
     * Generate a unique invoice number for an organization.
     */
    private static function generate_invoice_number($org_id) {
        global $wpdb;

        $table = OraBooks_Database::table('invoices');
        $year = date('Y');
        $month = date('m');

        $max_num = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '-', -1) AS UNSIGNED))
             FROM {$table}
             WHERE org_id = %d AND invoice_number LIKE %s",
            $org_id,
            "INV-{$year}{$month}-%"
        ));

        $next_num = str_pad($max_num + 1, 4, '0', STR_PAD_LEFT);
        return "INV-{$year}{$month}-{$next_num}";
    }

    // ================================================================
    // AJAX HANDLERS
    // ================================================================

    /**
     * List customers for admin.
     */
    public function ajax_customers_list() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }

        $org_id = intval($_GET['org_id'] ?? 0);
        if (!$org_id) {
            orabooks_json_error('Organization ID required', 400);
        }

        $args = [
            'is_active' => isset($_GET['is_active']) ? intval($_GET['is_active']) : null,
            'search'    => sanitize_text_field($_GET['search'] ?? ''),
            'limit'     => intval($_GET['limit'] ?? 50),
            'offset'    => intval($_GET['offset'] ?? 0),
        ];

        $result = self::get_list($org_id, $args);
        orabooks_json_success($result);
    }

    /**
     * Get a single customer.
     */
    public function ajax_customer_get() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }

        $user_id = intval($_GET['user_id'] ?? 0);
        if (!$user_id) {
            orabooks_json_error('User ID required', 400);
        }

        $customer = self::get_by_user_id($user_id);
        if (!$customer) {
            orabooks_json_error('Customer not found', 404);
        }

        orabooks_json_success($customer);
    }

    /**
     * Update customer (primarily is_active status).
     */
    public function ajax_customer_update() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }

        $customer_id = intval($_POST['customer_id'] ?? 0);
        if (!$customer_id) {
            orabooks_json_error('Customer ID required', 400);
        }

        $is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : null;
        $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : null;

        if ($is_active !== null) {
            $result = self::update_active_status($customer_id, $is_active);
            if (is_wp_error($result)) {
                orabooks_json_error($result->get_error_message(), 400);
            }
        }

        if ($notes !== null) {
            global $wpdb;
            $table = OraBooks_Database::table('customers');
            $wpdb->update(
                $table,
                ['notes' => $notes],
                ['id' => $customer_id],
                ['%s'],
                ['%d']
            );
        }

        orabooks_json_success([], 'Customer updated');
    }

    /**
     * List invoices.
     */
    public function ajax_invoices_list() {
        $user_id = get_current_user_id();
        $org_id = intval($_GET['org_id'] ?? 0);

        if (!current_user_can('manage_options') && !OraBooks_RBAC::require_permission($user_id, $org_id, 'view_invoices')) {
            orabooks_json_error('Permission denied', 403);
        }

        if (!$org_id) {
            // Try to get from user's org
            global $wpdb;
            $table_users = OraBooks_Database::table('users');
            $org_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT org_id FROM {$table_users} WHERE id = %d",
                $user_id
            ));
        }

        if (!$org_id) {
            orabooks_json_error('Organization ID required', 400);
        }

        $args = [
            'customer_id'      => intval($_GET['customer_id'] ?? 0),
            'payment_status'   => sanitize_text_field($_GET['payment_status'] ?? ''),
            'workflow_status'  => sanitize_text_field($_GET['workflow_status'] ?? ''),
            'from_date'        => sanitize_text_field($_GET['from_date'] ?? ''),
            'to_date'          => sanitize_text_field($_GET['to_date'] ?? ''),
            'limit'            => intval($_GET['limit'] ?? 50),
            'offset'           => intval($_GET['offset'] ?? 0),
        ];

        $result = self::get_invoices_list($org_id, $args);
        orabooks_json_success($result);
    }

    /**
     * Create an invoice.
     */
    public function ajax_invoice_create() {
        $user_id = get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);

        if (!current_user_can('manage_options') && !OraBooks_RBAC::require_permission($user_id, $org_id, 'create_invoices')) {
            orabooks_json_error('Permission denied', 403);
        }

        if (!$org_id) {
            orabooks_json_error('Organization ID required', 400);
        }

        $data = [
            'customer_id'      => intval($_POST['customer_id'] ?? 0),
            'invoice_number'   => sanitize_text_field($_POST['invoice_number'] ?? ''),
            'invoice_date'     => sanitize_text_field($_POST['invoice_date'] ?? ''),
            'transaction_date' => sanitize_text_field($_POST['transaction_date'] ?? ''),
            'due_date'         => sanitize_text_field($_POST['due_date'] ?? ''),
            'description'      => sanitize_textarea_field($_POST['description'] ?? ''),
            'total_amount'     => floatval($_POST['total_amount'] ?? 0),
            'tax_amount'       => floatval($_POST['tax_amount'] ?? 0),
            'currency'         => sanitize_text_field($_POST['currency'] ?? 'USD'),
            'workflow_status'  => sanitize_text_field($_POST['workflow_status'] ?? 'draft'),
            'due_days'         => intval($_POST['due_days'] ?? 30),
            'idempotency_key'  => sanitize_text_field($_POST['idempotency_key'] ?? ''),
        ];

        $result = self::create_invoice($org_id, $data);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success($result, 'Invoice created');
    }

    /**
     * Get single invoice details.
     */
    public function ajax_invoice_get() {
        $user_id = get_current_user_id();
        $invoice_id = intval($_GET['invoice_id'] ?? 0);

        if (!$invoice_id) {
            orabooks_json_error('Invoice ID required', 400);
        }

        $invoice = self::get_invoice($invoice_id);
        if (!$invoice) {
            orabooks_json_error('Invoice not found', 404);
        }

        // Permission check: must be admin or have view_invoices in the org
        if (!current_user_can('manage_options')) {
            $org_id = (int) $invoice->org_id;
            if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'view_invoices')) {
                orabooks_json_error('Permission denied', 403);
            }
        }

        orabooks_json_success($invoice);
    }

    /**
     * Record a payment against an invoice.
     */
    public function ajax_record_payment() {
        $user_id = get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);

        if (!current_user_can('manage_options') && !OraBooks_RBAC::require_permission($user_id, $org_id, 'create_invoices')) {
            orabooks_json_error('Permission denied', 403);
        }

        if (!$org_id) {
            orabooks_json_error('Organization ID required', 400);
        }

        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        if (!$invoice_id) {
            orabooks_json_error('Invoice ID required', 400);
        }

        $data = [
            'amount'       => floatval($_POST['amount'] ?? 0),
            'payment_date' => sanitize_text_field($_POST['payment_date'] ?? ''),
            'payment_method' => sanitize_text_field($_POST['payment_method'] ?? 'bank_transfer'),
            'reference'    => sanitize_text_field($_POST['reference'] ?? ''),
            'notes'        => sanitize_textarea_field($_POST['notes'] ?? ''),
            'idempotency_key' => sanitize_text_field($_POST['idempotency_key'] ?? ''),
        ];

        $result = self::record_payment($org_id, $invoice_id, $data);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success($result, 'Payment recorded');
    }

    /**
     * Customer stats for admin dashboard.
     */
    public function ajax_customer_stats() {
        if (!current_user_can('manage_options')) {
            orabooks_json_error('Permission denied', 403);
        }

        $org_id = intval($_GET['org_id'] ?? 0);

        // If no org specified, aggregate across all orgs
        if (!$org_id) {
            global $wpdb;
            $table_orgs = OraBooks_Database::table('organizations');
            $orgs = $wpdb->get_col("SELECT id FROM {$table_orgs}");
            $aggregate = [
                'total_customers'  => 0,
                'active_customers' => 0,
                'inactive_customers' => 0,
                'total_invoices'   => 0,
                'paid_invoices'    => 0,
                'unpaid_invoices'  => 0,
                'overdue_invoices' => 0,
                'total_revenue'    => 0,
                'outstanding_ar'   => 0,
            ];
            foreach ($orgs as $oid) {
                $stats = self::get_customer_stats($oid);
                foreach ($aggregate as $key => &$val) {
                    $val += $stats[$key] ?? 0;
                }
            }
            orabooks_json_success($aggregate);
        }

        $stats = self::get_customer_stats($org_id);
        orabooks_json_success($stats);
    }
}
