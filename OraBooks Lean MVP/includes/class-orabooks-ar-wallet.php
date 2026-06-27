<?php
/**
 * OraBooks AR Wallet Module (SL-021 extension)
 *
 * Customer wallet, FIFO payment allocation, credit notes, write-offs,
 * payment reversals, invoice rendered snapshots, and statement snapshots.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_AR_Wallet {

    private static $instance = null;

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();

            add_action('wp_ajax_orabooks_customer_wallet', [self::$instance, 'ajax_customer_wallet']);
            add_action('wp_ajax_orabooks_customer_payment_record', [self::$instance, 'ajax_record_customer_payment']);
            add_action('wp_ajax_orabooks_payment_reverse', [self::$instance, 'ajax_reverse_payment']);
            add_action('wp_ajax_orabooks_credit_note_create', [self::$instance, 'ajax_create_credit_note']);
            add_action('wp_ajax_orabooks_credit_note_post', [self::$instance, 'ajax_post_credit_note']);
            add_action('wp_ajax_orabooks_credit_note_void', [self::$instance, 'ajax_void_credit_note']);
            add_action('wp_ajax_orabooks_credit_notes_list', [self::$instance, 'ajax_credit_notes_list']);
            add_action('wp_ajax_orabooks_ar_config_get', [self::$instance, 'ajax_ar_config_get']);
            add_action('wp_ajax_orabooks_ar_config_set', [self::$instance, 'ajax_ar_config_set']);
            add_action('wp_ajax_orabooks_customer_statements_list', [self::$instance, 'ajax_customer_statements_list']);

            add_action('orabooks_monthly_customer_statement_snapshot', [self::$instance, 'monthly_statement_snapshot']);
        }

        return self::$instance;
    }

    public static function get_create_table_sql() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $tables = [];

        $table_customers = OraBooks_Database::table('customers');
        $table_invoices = OraBooks_Database::table('invoices');
        $table_payments = OraBooks_Database::table('payments');
        $table_allocations = OraBooks_Database::table('payment_allocations');
        $table_credit_notes = OraBooks_Database::table('credit_notes');
        $table_configs = OraBooks_Database::table('customer_ar_configs');
        $table_rendered = OraBooks_Database::table('invoice_rendered_copy');
        $table_snapshots = OraBooks_Database::table('customer_statement_snapshots');
        $table_installments = OraBooks_Database::table('installment_plans');
        $table_orgs = OraBooks_Database::table('organizations');

        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_allocations} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            payment_id BIGINT UNSIGNED NOT NULL,
            invoice_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(20,2) NOT NULL,
            allocation_method ENUM('FIFO','manual','auto_credit') DEFAULT 'FIFO',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES {$table_customers}(id) ON DELETE CASCADE,
            FOREIGN KEY (payment_id) REFERENCES {$table_payments}(id) ON DELETE CASCADE,
            FOREIGN KEY (invoice_id) REFERENCES {$table_invoices}(id) ON DELETE CASCADE,
            INDEX idx_invoice (invoice_id),
            INDEX idx_customer (customer_id),
            INDEX idx_payment (payment_id)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_credit_notes} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            invoice_id BIGINT UNSIGNED NULL,
            credit_note_number VARCHAR(50) NOT NULL,
            credit_date DATE NOT NULL,
            amount DECIMAL(20,2) NOT NULL,
            reason TEXT NOT NULL,
            is_write_off TINYINT(1) DEFAULT 0,
            bad_debt_account_code VARCHAR(50) NULL,
            workflow_status ENUM('draft','submitted','approved','posted','void') DEFAULT 'draft',
            journal_id BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NULL,
            approved_by BIGINT UNSIGNED NULL,
            approved_at TIMESTAMP NULL,
            posted_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES {$table_customers}(id) ON DELETE CASCADE,
            FOREIGN KEY (invoice_id) REFERENCES {$table_invoices}(id) ON DELETE SET NULL,
            UNIQUE KEY uk_org_credit_note (org_id, credit_note_number),
            INDEX idx_customer (customer_id),
            INDEX idx_invoice (invoice_id),
            INDEX idx_workflow (workflow_status)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_configs} (
            org_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            auto_post_on_approve TINYINT(1) DEFAULT 1,
            auto_apply_customer_credit TINYINT(1) DEFAULT 1,
            write_off_threshold DECIMAL(20,2) DEFAULT 100,
            bad_debt_account_code VARCHAR(50) DEFAULT '1200',
            ar_account_code VARCHAR(50) DEFAULT '1100',
            cash_account_code VARCHAR(50) DEFAULT '1000',
            revenue_account_code VARCHAR(50) DEFAULT '4000',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_rendered} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            invoice_id BIGINT UNSIGNED NOT NULL,
            rendered_html LONGTEXT NULL,
            rendered_json JSON NULL,
            rendered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
            FOREIGN KEY (invoice_id) REFERENCES {$table_invoices}(id) ON DELETE CASCADE,
            UNIQUE KEY uk_invoice_render (invoice_id)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_snapshots} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            snapshot_date DATE NOT NULL,
            statement_month CHAR(7) NOT NULL,
            balance DECIMAL(20,2) NOT NULL DEFAULT 0,
            credit_balance DECIMAL(20,2) NOT NULL DEFAULT 0,
            open_invoices_json JSON NULL,
            paid_invoices_json JSON NULL,
            payments_json JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES {$table_customers}(id) ON DELETE CASCADE,
            UNIQUE KEY uk_customer_month (customer_id, statement_month),
            INDEX idx_org_month (org_id, statement_month)
        ) {$charset_collate};";

        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_installments} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            invoice_id BIGINT UNSIGNED NOT NULL,
            installment_number INT NOT NULL,
            due_date DATE NOT NULL,
            amount DECIMAL(20,2) NOT NULL,
            status ENUM('scheduled','due','paid','void') DEFAULT 'scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
            FOREIGN KEY (invoice_id) REFERENCES {$table_invoices}(id) ON DELETE CASCADE,
            INDEX idx_invoice (invoice_id)
        ) {$charset_collate};";

        return $tables;
    }

    public static function ensure_schema() {
        global $wpdb;

        if (self::get_schema_flag('orabooks_sl021_ar_wallet_v1') === '1') {
            return;
        }

        $upgrade = ABSPATH . 'wp-admin/includes/upgrade.php';
        if (file_exists($upgrade)) {
            require_once $upgrade;
            foreach (self::get_create_table_sql() as $sql) {
                dbDelta($sql);
            }
        }

        $table_invoices = OraBooks_Database::table('invoices');
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_invoices)) === $table_invoices) {
            $fields = self::get_table_column_names($table_invoices);
            $additions = [
                'lock_status' => "ALTER TABLE {$table_invoices} ADD COLUMN lock_status ENUM('unlocked','locked') DEFAULT 'unlocked'",
                'dunning_stage' => "ALTER TABLE {$table_invoices} ADD COLUMN dunning_stage ENUM('none','reminder_1','reminder_2','escalation','collection','legal_hold') DEFAULT 'none'",
                'journal_id' => "ALTER TABLE {$table_invoices} ADD COLUMN journal_id BIGINT UNSIGNED NULL",
            ];
            foreach ($additions as $column => $sql) {
                if (!in_array($column, $fields, true)) {
                    $wpdb->query($sql);
                }
            }

            if (in_array('payment_status', $fields, true)) {
                $wpdb->query(
                    "ALTER TABLE {$table_invoices}
                     MODIFY payment_status ENUM('unpaid','partial','paid','overdue','cancelled','credited') DEFAULT 'unpaid'"
                );
            }
        }

        $table_payments = OraBooks_Database::table('payments');
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_payments)) === $table_payments) {
            $fields = self::get_table_column_names($table_payments);
            $additions = [
                'customer_id' => "ALTER TABLE {$table_payments} ADD COLUMN customer_id BIGINT UNSIGNED NULL AFTER org_id",
                'unapplied_amount' => "ALTER TABLE {$table_payments} ADD COLUMN unapplied_amount DECIMAL(20,2) NOT NULL DEFAULT 0 AFTER amount",
                'type' => "ALTER TABLE {$table_payments} ADD COLUMN type ENUM('payment','reversal','refund') DEFAULT 'payment' AFTER payment_method",
                'reverses_payment_id' => "ALTER TABLE {$table_payments} ADD COLUMN reverses_payment_id BIGINT UNSIGNED NULL AFTER notes",
                'journal_id' => "ALTER TABLE {$table_payments} ADD COLUMN journal_id BIGINT UNSIGNED NULL AFTER reverses_payment_id",
                'allocation_method' => "ALTER TABLE {$table_payments} ADD COLUMN allocation_method ENUM('FIFO','manual') DEFAULT 'FIFO' AFTER journal_id",
            ];
            foreach ($additions as $column => $sql) {
                if (!in_array($column, $fields, true)) {
                    $wpdb->query($sql);
                }
            }

            if (in_array('invoice_id', $fields, true)) {
                $wpdb->query("ALTER TABLE {$table_payments} MODIFY invoice_id BIGINT UNSIGNED NULL");
            }

            $wpdb->query(
                "UPDATE {$table_payments} p
                 INNER JOIN {$table_invoices} i ON p.invoice_id = i.id
                 SET p.customer_id = i.customer_id
                 WHERE p.customer_id IS NULL AND p.invoice_id IS NOT NULL"
            );
        }

        self::set_schema_flag('orabooks_sl021_ar_wallet_v1', '1');
    }

    public static function get_ar_config($org_id) {
        global $wpdb;

        self::ensure_schema();

        $table = OraBooks_Database::table('customer_ar_configs');
        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d",
            intval($org_id)
        ));

        if ($config) {
            return $config;
        }

        return (object) [
            'org_id' => intval($org_id),
            'auto_post_on_approve' => 1,
            'auto_apply_customer_credit' => 1,
            'write_off_threshold' => 100,
            'bad_debt_account_code' => '1200',
            'ar_account_code' => '1100',
            'cash_account_code' => '1000',
            'revenue_account_code' => '4000',
        ];
    }

    public static function save_ar_config($org_id, $data) {
        global $wpdb;

        self::ensure_schema();

        $table = OraBooks_Database::table('customer_ar_configs');
        $payload = [
            'org_id' => intval($org_id),
            'auto_post_on_approve' => !empty($data['auto_post_on_approve']) ? 1 : 0,
            'auto_apply_customer_credit' => !empty($data['auto_apply_customer_credit']) ? 1 : 0,
            'write_off_threshold' => round(floatval($data['write_off_threshold'] ?? 100), 2),
            'bad_debt_account_code' => sanitize_text_field($data['bad_debt_account_code'] ?? '1200'),
            'ar_account_code' => sanitize_text_field($data['ar_account_code'] ?? '1100'),
            'cash_account_code' => sanitize_text_field($data['cash_account_code'] ?? '1000'),
            'revenue_account_code' => sanitize_text_field($data['revenue_account_code'] ?? '4000'),
        ];

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT org_id FROM {$table} WHERE org_id = %d",
            intval($org_id)
        ));

        if ($existing) {
            unset($payload['org_id']);
            $wpdb->update($table, $payload, ['org_id' => intval($org_id)]);
        } else {
            $wpdb->insert($table, $payload);
        }

        return self::get_ar_config($org_id);
    }

    public static function validate_customer_credit_for_new_invoice($org_id, $customer_id, $projected_amount = 0) {
        global $wpdb;

        $table = OraBooks_Database::table('customers');
        $table_invoices = OraBooks_Database::table('invoices');

        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT credit_hold, credit_limit FROM {$table} WHERE id = %d AND org_id = %d",
            intval($customer_id),
            intval($org_id)
        ));

        if (!$customer) {
            return new WP_Error('not_found', 'Customer not found');
        }

        if ((int) $customer->credit_hold === 1) {
            return new WP_Error('credit_hold', 'Customer is on credit hold. New invoices are blocked.');
        }

        $credit_limit = floatval($customer->credit_limit ?? 0);
        if ($credit_limit > 0) {
            $outstanding = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0)
                 FROM {$table_invoices}
                 WHERE customer_id = %d
                   AND org_id = %d
                   AND payment_status IN ('unpaid', 'partial', 'overdue')
                   AND workflow_status NOT IN ('cancelled')",
                intval($customer_id),
                intval($org_id)
            ));
            if (($outstanding + floatval($projected_amount)) > $credit_limit) {
                return new WP_Error(
                    'credit_limit',
                    sprintf('Invoice would exceed customer credit limit (%s).', number_format($credit_limit, 2))
                );
            }
        }

        return true;
    }

    public static function get_customer_wallet($customer_id, $org_id) {
        global $wpdb;

        self::ensure_schema();

        $customer = OraBooks_Customers::get_by_id(intval($customer_id));
        if (!$customer || (int) $customer->org_id !== (int) $org_id) {
            return new WP_Error('not_found', 'Customer not found');
        }

        $table_invoices = OraBooks_Database::table('invoices');
        $table_payments = OraBooks_Database::table('payments');

        $wallet_balance = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0)
             FROM {$table_invoices}
             WHERE customer_id = %d AND org_id = %d
               AND workflow_status = 'posted'
               AND payment_status IN ('unpaid', 'partial', 'overdue')",
            intval($customer_id),
            intval($org_id)
        ));

        $payments = [];
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_payments)) === $table_payments) {
            $payments = $wpdb->get_results($wpdb->prepare(
                "SELECT p.*, i.invoice_number
                 FROM {$table_payments} p
                 LEFT JOIN {$table_invoices} i ON p.invoice_id = i.id
                 WHERE p.org_id = %d AND p.customer_id = %d
                 ORDER BY p.payment_date DESC, p.id DESC
                 LIMIT 100",
                intval($org_id),
                intval($customer_id)
            ));
        }

        $invoices = OraBooks_Customers::get_invoices_list(intval($org_id), [
            'customer_id' => intval($customer_id),
            'limit' => 100,
        ]);

        return [
            'customer' => $customer,
            'wallet_balance' => round($wallet_balance + floatval($customer->opening_balance ?? 0), 2),
            'credit_balance' => round(floatval($customer->credit_balance ?? 0), 2),
            'credit_limit' => round(floatval($customer->credit_limit ?? 0), 2),
            'credit_hold' => (int) ($customer->credit_hold ?? 0),
            'auto_apply_credit' => (int) ($customer->auto_apply_credit ?? 1),
            'invoices' => $invoices['invoices'] ?? [],
            'payments' => $payments,
        ];
    }

    /**
     * Record payment against an invoice (manual target) with FIFO overflow and overpayment credit.
     */
    public static function record_payment($org_id, $invoice_id, $data) {
        global $wpdb;

        self::ensure_schema();

        $table_invoices = OraBooks_Database::table('invoices');
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_invoices} WHERE id = %d AND org_id = %d",
            intval($invoice_id),
            intval($org_id)
        ));

        if (!$invoice) {
            return new WP_Error('not_found', 'Invoice not found');
        }

        if ($invoice->payment_status === 'cancelled') {
            return new WP_Error('cancelled', 'Cannot pay a cancelled invoice');
        }

        $amount = round(floatval($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'Payment amount must be greater than 0');
        }

        $method = sanitize_text_field($data['allocation_method'] ?? 'manual');
        if (!in_array($method, ['FIFO', 'manual'], true)) {
            $method = 'manual';
        }

        return self::record_customer_payment(intval($org_id), intval($invoice->customer_id), array_merge($data, [
            'amount' => $amount,
            'allocation_method' => $method,
            'target_invoice_id' => intval($invoice_id),
        ]));
    }

    public static function record_customer_payment($org_id, $customer_id, $data) {
        global $wpdb;

        self::ensure_schema();

        $org_id = intval($org_id);
        $customer_id = intval($customer_id);
        $amount = round(floatval($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'Payment amount must be greater than 0');
        }

        $payment_date = $data['payment_date'] ?? current_time('Y-m-d');
        $allocation_method = sanitize_text_field($data['allocation_method'] ?? 'FIFO');
        if (!in_array($allocation_method, ['FIFO', 'manual'], true)) {
            $allocation_method = 'FIFO';
        }

        $target_invoice_id = intval($data['target_invoice_id'] ?? $data['invoice_id'] ?? 0);
        $table_payments = OraBooks_Database::table('payments');

        $idempotency_key = !empty($data['idempotency_key']) ? sanitize_text_field($data['idempotency_key']) : orabooks_uuid();
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_payments} WHERE idempotency_key = %s",
            $idempotency_key
        ));
        if ($existing) {
            return [
                'payment_id' => intval($existing),
                'duplicate' => true,
            ];
        }

        $wpdb->insert(
            $table_payments,
            [
                'org_id' => $org_id,
                'customer_id' => $customer_id,
                'invoice_id' => $target_invoice_id > 0 ? $target_invoice_id : null,
                'payment_date' => $payment_date,
                'amount' => $amount,
                'unapplied_amount' => 0,
                'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                'type' => 'payment',
                'reference' => isset($data['reference']) ? sanitize_text_field($data['reference']) : '',
                'notes' => isset($data['notes']) ? sanitize_textarea_field($data['notes']) : '',
                'allocation_method' => $allocation_method,
                'idempotency_key' => $idempotency_key,
            ],
            ['%d', '%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        $payment_id = intval($wpdb->insert_id);
        $remaining = $amount;

        if ($target_invoice_id > 0 && $allocation_method === 'manual') {
            $remaining = self::allocate_to_invoice(
                $org_id,
                $customer_id,
                $payment_id,
                $target_invoice_id,
                $remaining,
                'manual'
            );
        }

        if ($remaining > 0) {
            $remaining = self::allocate_payment_fifo($org_id, $customer_id, $payment_id, $remaining);
        }

        if ($remaining > 0) {
            self::adjust_customer_credit_balance($customer_id, $org_id, $remaining);
            $wpdb->update(
                $table_payments,
                ['unapplied_amount' => $remaining],
                ['id' => $payment_id],
                ['%f'],
                ['%d']
            );
        }

        $journal_id = self::create_payment_journal(
            $org_id,
            $payment_id,
            $amount,
            $payment_date,
            $customer_id,
            orabooks_get_current_user_id()
        );
        if (!is_wp_error($journal_id) && $journal_id) {
            $wpdb->update(
                $table_payments,
                ['journal_id' => intval($journal_id)],
                ['id' => $payment_id],
                ['%d'],
                ['%d']
            );
        }

        OraBooks_Customers::recompute_active_status($customer_id);

        orabooks_log_event('payment_recorded', 'Customer payment recorded', 'info', [
            'payment_id' => $payment_id,
            'customer_id' => $customer_id,
            'amount' => $amount,
            'unapplied_amount' => $remaining,
        ], orabooks_get_current_user_id(), $org_id);

        do_action('orabooks_payment_recorded', $payment_id, [
            'customer_id' => $customer_id,
            'amount' => $amount,
            'org_id' => $org_id,
        ]);

        return [
            'payment_id' => $payment_id,
            'allocated_amount' => round($amount - $remaining, 2),
            'unapplied_amount' => round($remaining, 2),
            'journal_id' => is_wp_error($journal_id) ? null : $journal_id,
        ];
    }

    private static function allocate_to_invoice($org_id, $customer_id, $payment_id, $invoice_id, $amount, $method = 'manual') {
        global $wpdb;

        $table_invoices = OraBooks_Database::table('invoices');
        $table_allocations = OraBooks_Database::table('payment_allocations');

        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_invoices}
             WHERE id = %d AND org_id = %d AND customer_id = %d
               AND workflow_status = 'posted'
               AND payment_status IN ('unpaid','partial','overdue')",
            intval($invoice_id),
            intval($org_id),
            intval($customer_id)
        ));

        if (!$invoice) {
            return round(floatval($amount), 2);
        }

        $outstanding = max(0, round(floatval($invoice->total_amount) - floatval($invoice->paid_amount), 2));
        if ($outstanding <= 0) {
            return round(floatval($amount), 2);
        }

        $applied = min(round(floatval($amount), 2), $outstanding);
        self::insert_allocation_and_update_invoice(
            $org_id,
            $customer_id,
            $payment_id,
            intval($invoice->id),
            $applied,
            $method,
            $invoice
        );

        return round(floatval($amount) - $applied, 2);
    }

    private static function allocate_payment_fifo($org_id, $customer_id, $payment_id, $amount) {
        global $wpdb;

        $table_invoices = OraBooks_Database::table('invoices');
        $remaining = round(floatval($amount), 2);

        $invoices = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_invoices}
             WHERE org_id = %d AND customer_id = %d
               AND workflow_status = 'posted'
               AND payment_status IN ('unpaid','partial','overdue')
             ORDER BY due_date ASC, id ASC",
            intval($org_id),
            intval($customer_id)
        ));

        foreach ($invoices as $invoice) {
            if ($remaining <= 0) {
                break;
            }

            $outstanding = max(0, round(floatval($invoice->total_amount) - floatval($invoice->paid_amount), 2));
            if ($outstanding <= 0) {
                continue;
            }

            $applied = min($remaining, $outstanding);
            self::insert_allocation_and_update_invoice(
                $org_id,
                $customer_id,
                $payment_id,
                intval($invoice->id),
                $applied,
                'FIFO',
                $invoice
            );
            $remaining = round($remaining - $applied, 2);
        }

        return max(0, $remaining);
    }

    private static function insert_allocation_and_update_invoice($org_id, $customer_id, $payment_id, $invoice_id, $applied, $method, $invoice) {
        global $wpdb;

        $table_allocations = OraBooks_Database::table('payment_allocations');
        $table_invoices = OraBooks_Database::table('invoices');
        $table_payments = OraBooks_Database::table('payments');

        $wpdb->insert(
            $table_allocations,
            [
                'org_id' => intval($org_id),
                'customer_id' => intval($customer_id),
                'payment_id' => intval($payment_id),
                'invoice_id' => intval($invoice_id),
                'amount' => $applied,
                'allocation_method' => $method,
            ],
            ['%d', '%d', '%d', '%d', '%f', '%s']
        );

        $new_paid = round(floatval($invoice->paid_amount) + $applied, 2);
        $new_status = ($new_paid >= floatval($invoice->total_amount)) ? 'paid' : 'partial';
        $lock_status = ($new_status === 'paid') ? 'locked' : ($invoice->lock_status ?? 'unlocked');

        $wpdb->update(
            $table_invoices,
            [
                'paid_amount' => $new_paid,
                'payment_status' => $new_status,
                'last_payment_date' => current_time('Y-m-d'),
                'paid_at' => ($new_status === 'paid') ? current_time('mysql') : null,
                'lock_status' => $lock_status,
            ],
            ['id' => intval($invoice_id)],
            ['%f', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        if (empty($invoice->invoice_id)) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_payments} SET invoice_id = %d WHERE id = %d AND invoice_id IS NULL",
                intval($invoice_id),
                intval($payment_id)
            ));
        }
    }

    public static function apply_auto_credit_to_invoice($org_id, $invoice) {
        global $wpdb;

        if (!$invoice || empty($invoice->customer_id)) {
            return null;
        }

        $config = self::get_ar_config($org_id);
        if (empty($config->auto_apply_customer_credit)) {
            return null;
        }

        $table_customers = OraBooks_Database::table('customers');
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id, credit_balance, auto_apply_credit FROM {$table_customers} WHERE id = %d AND org_id = %d",
            intval($invoice->customer_id),
            intval($org_id)
        ));

        if (!$customer || empty($customer->auto_apply_credit)) {
            return null;
        }

        $credit = round(floatval($customer->credit_balance), 2);
        if ($credit <= 0) {
            return null;
        }

        $outstanding = max(0, round(floatval($invoice->total_amount) - floatval($invoice->paid_amount), 2));
        if ($outstanding <= 0) {
            return null;
        }

        $applied = min($credit, $outstanding);
        $table_payments = OraBooks_Database::table('payments');

        $wpdb->insert(
            $table_payments,
            [
                'org_id' => intval($org_id),
                'customer_id' => intval($customer->id),
                'invoice_id' => intval($invoice->id),
                'payment_date' => current_time('Y-m-d'),
                'amount' => $applied,
                'unapplied_amount' => 0,
                'payment_method' => 'other',
                'type' => 'payment',
                'reference' => 'AUTO-CREDIT',
                'notes' => 'Auto-applied customer credit balance',
                'allocation_method' => 'manual',
                'idempotency_key' => 'auto_credit_' . intval($invoice->id),
            ],
            ['%d', '%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        $payment_id = intval($wpdb->insert_id);
        self::insert_allocation_and_update_invoice(
            intval($org_id),
            intval($customer->id),
            $payment_id,
            intval($invoice->id),
            $applied,
            'auto_credit',
            $invoice
        );

        self::adjust_customer_credit_balance(intval($customer->id), intval($org_id), -$applied);

        return $applied;
    }

    public static function adjust_customer_credit_balance($customer_id, $org_id, $credit_delta) {
        global $wpdb;

        $table = OraBooks_Database::table('customers');
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET credit_balance = GREATEST(0, credit_balance + %f)
             WHERE id = %d AND org_id = %d",
            floatval($credit_delta),
            intval($customer_id),
            intval($org_id)
        ));
    }

    public static function lock_invoice_on_post($invoice_id, $org_id) {
        global $wpdb;

        $table = OraBooks_Database::table('invoices');
        $wpdb->update(
            $table,
            ['lock_status' => 'locked'],
            ['id' => intval($invoice_id), 'org_id' => intval($org_id)],
            ['%s'],
            ['%d', '%d']
        );
    }

    public static function save_invoice_rendered_copy($invoice_id, $org_id) {
        global $wpdb;

        self::ensure_schema();

        $invoice = OraBooks_Customers::get_invoice(intval($invoice_id));
        if (!$invoice) {
            return null;
        }

        $payload = [
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date,
            'due_date' => $invoice->due_date,
            'customer_name' => $invoice->customer_name ?? '',
            'description' => $invoice->description ?? '',
            'total_amount' => floatval($invoice->total_amount),
            'tax_amount' => floatval($invoice->tax_amount ?? 0),
            'currency' => $invoice->currency ?? 'USD',
            'workflow_status' => $invoice->workflow_status,
            'payment_status' => $invoice->payment_status,
        ];

        $html = sprintf(
            '<div class="orabooks-invoice"><h1>%s</h1><p>Date: %s | Due: %s</p><p>Customer: %s</p><p>Total: %s %s</p></div>',
            esc_html($invoice->invoice_number),
            esc_html($invoice->invoice_date),
            esc_html($invoice->due_date),
            esc_html($invoice->customer_name ?? ''),
            esc_html(number_format(floatval($invoice->total_amount), 2)),
            esc_html($invoice->currency ?? 'USD')
        );

        $table = OraBooks_Database::table('invoice_rendered_copy');
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE invoice_id = %d",
            intval($invoice_id)
        ));

        $row = [
            'org_id' => intval($org_id),
            'invoice_id' => intval($invoice_id),
            'rendered_html' => $html,
            'rendered_json' => wp_json_encode($payload),
            'rendered_at' => current_time('mysql'),
        ];

        if ($existing) {
            $wpdb->update($table, $row, ['id' => intval($existing)]);
        } else {
            $wpdb->insert($table, $row);
        }

        return true;
    }

    public static function create_credit_note($org_id, $data) {
        global $wpdb;

        self::ensure_schema();

        $org_id = intval($org_id);
        $customer_id = intval($data['customer_id'] ?? 0);
        $invoice_id = !empty($data['invoice_id']) ? intval($data['invoice_id']) : null;
        $amount = round(floatval($data['amount'] ?? 0), 2);
        $reason = sanitize_textarea_field($data['reason'] ?? '');

        if ($customer_id <= 0 || $amount <= 0 || $reason === '') {
            return new WP_Error('invalid_credit_note', 'Customer, amount, and reason are required');
        }

        if ($invoice_id) {
            $table_invoices = OraBooks_Database::table('invoices');
            $invoice = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_invoices} WHERE id = %d AND org_id = %d AND customer_id = %d",
                $invoice_id,
                $org_id,
                $customer_id
            ));
            if (!$invoice || $invoice->workflow_status !== 'posted') {
                return new WP_Error('invalid_invoice', 'Credit note requires a posted invoice');
            }
        }

        $config = self::get_ar_config($org_id);
        $is_write_off = !empty($data['is_write_off']);
        $number = !empty($data['credit_note_number'])
            ? sanitize_text_field($data['credit_note_number'])
            : self::generate_credit_note_number($org_id, $data['credit_date'] ?? current_time('Y-m-d'));

        $wpdb->insert(
            OraBooks_Database::table('credit_notes'),
            [
                'org_id' => $org_id,
                'customer_id' => $customer_id,
                'invoice_id' => $invoice_id,
                'credit_note_number' => $number,
                'credit_date' => $data['credit_date'] ?? current_time('Y-m-d'),
                'amount' => $amount,
                'reason' => $reason,
                'is_write_off' => $is_write_off ? 1 : 0,
                'bad_debt_account_code' => $is_write_off ? sanitize_text_field($data['bad_debt_account_code'] ?? $config->bad_debt_account_code) : null,
                'workflow_status' => sanitize_text_field($data['workflow_status'] ?? 'draft'),
                'created_by' => orabooks_get_current_user_id(),
            ],
            ['%d', '%d', '%d', '%s', '%s', '%f', '%s', '%d', '%s', '%s', '%d']
        );

        $credit_note_id = intval($wpdb->insert_id);

        return [
            'credit_note_id' => $credit_note_id,
            'credit_note_number' => $number,
            'requires_second_approval' => $is_write_off && $amount > floatval($config->write_off_threshold),
        ];
    }

    public static function post_credit_note($org_id, $credit_note_id, $user_id, $second_approver_id = 0) {
        global $wpdb;

        $table = OraBooks_Database::table('credit_notes');
        $note = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND org_id = %d",
            intval($credit_note_id),
            intval($org_id)
        ));

        if (!$note) {
            return new WP_Error('not_found', 'Credit note not found');
        }

        if ($note->workflow_status === 'posted') {
            return new WP_Error('already_posted', 'Credit note is already posted');
        }

        if ($note->workflow_status === 'void') {
            return new WP_Error('void', 'Cannot post a void credit note');
        }

        if (!in_array($note->workflow_status, ['draft', 'submitted', 'approved'], true)) {
            return new WP_Error('invalid_status', 'Credit note cannot be posted from current status');
        }

        $config = self::get_ar_config($org_id);
        if ((int) $note->is_write_off === 1 && floatval($note->amount) > floatval($config->write_off_threshold)) {
            if (intval($second_approver_id) <= 0 || intval($second_approver_id) === intval($user_id)) {
                return new WP_Error('approval_required', 'Write-off above threshold requires a second approver');
            }
        }

        $journal_id = self::create_credit_note_journal($note, intval($user_id), $config);
        if (is_wp_error($journal_id)) {
            return $journal_id;
        }

        if ($journal_id && class_exists('OraBooks_Posting')) {
            $submit = OraBooks_Posting::submit_journal(intval($journal_id), intval($user_id));
            if (is_wp_error($submit)) {
                return $submit;
            }
        }

        $wpdb->update(
            $table,
            [
                'workflow_status' => 'posted',
                'journal_id' => intval($journal_id),
                'approved_by' => intval($second_approver_id) > 0 ? intval($second_approver_id) : intval($user_id),
                'approved_at' => current_time('mysql'),
                'posted_at' => current_time('mysql'),
            ],
            ['id' => intval($credit_note_id)],
            ['%s', '%d', '%d', '%s', '%s'],
            ['%d']
        );

        if (!empty($note->invoice_id)) {
            self::apply_credit_note_to_invoice(intval($note->invoice_id), floatval($note->amount));
        }

        orabooks_log_event('credit_note_posted', 'Credit note posted', 'info', [
            'credit_note_id' => intval($credit_note_id),
            'amount' => floatval($note->amount),
        ], intval($user_id), intval($org_id));

        return self::get_credit_note(intval($credit_note_id));
    }

    public static function void_credit_note($org_id, $credit_note_id, $user_id) {
        global $wpdb;

        $table = OraBooks_Database::table('credit_notes');
        $note = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND org_id = %d",
            intval($credit_note_id),
            intval($org_id)
        ));

        if (!$note) {
            return new WP_Error('not_found', 'Credit note not found');
        }

        if (!in_array($note->workflow_status, ['draft', 'submitted'], true)) {
            return new WP_Error('invalid_status', 'Only draft or submitted credit notes can be voided');
        }

        $wpdb->update(
            $table,
            ['workflow_status' => 'void'],
            ['id' => intval($credit_note_id)],
            ['%s'],
            ['%d']
        );

        orabooks_log_event('credit_note_voided', 'Credit note voided', 'info', [
            'credit_note_id' => intval($credit_note_id),
        ], intval($user_id), intval($org_id));

        return self::get_credit_note(intval($credit_note_id));
    }

    private static function apply_credit_note_to_invoice($invoice_id, $amount) {
        global $wpdb;

        $table = OraBooks_Database::table('invoices');
        $invoice = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", intval($invoice_id)));
        if (!$invoice) {
            return;
        }

        $new_paid = round(floatval($invoice->paid_amount) + floatval($amount), 2);
        $new_status = 'credited';
        if ($new_paid < floatval($invoice->total_amount)) {
            $new_status = ($new_paid > 0) ? 'partial' : $invoice->payment_status;
        }

        $wpdb->update(
            $table,
            [
                'paid_amount' => min($new_paid, floatval($invoice->total_amount)),
                'payment_status' => $new_status,
            ],
            ['id' => intval($invoice_id)],
            ['%f', '%s'],
            ['%d']
        );
    }

    public static function reverse_payment($org_id, $payment_id, $user_id, $reason = '') {
        global $wpdb;

        self::ensure_schema();

        $table_payments = OraBooks_Database::table('payments');
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_payments} WHERE id = %d AND org_id = %d",
            intval($payment_id),
            intval($org_id)
        ));

        if (!$payment) {
            return new WP_Error('not_found', 'Payment not found');
        }

        if ($payment->type !== 'payment') {
            return new WP_Error('invalid_type', 'Only standard payments can be reversed');
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_payments} WHERE reverses_payment_id = %d AND type = 'reversal'",
            intval($payment_id)
        ));
        if ($existing) {
            return new WP_Error('already_reversed', 'Payment has already been reversed');
        }

        $table_allocations = OraBooks_Database::table('payment_allocations');
        $allocations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_allocations} WHERE payment_id = %d",
            intval($payment_id)
        ));

        foreach ($allocations as $allocation) {
            self::reverse_allocation($allocation);
        }

        $unapplied = floatval($payment->unapplied_amount ?? 0);
        if ($unapplied > 0 && !empty($payment->customer_id)) {
            self::adjust_customer_credit_balance(intval($payment->customer_id), intval($org_id), -$unapplied);
        }

        $wpdb->insert(
            $table_payments,
            [
                'org_id' => intval($org_id),
                'customer_id' => intval($payment->customer_id),
                'invoice_id' => $payment->invoice_id ? intval($payment->invoice_id) : null,
                'payment_date' => current_time('Y-m-d'),
                'amount' => floatval($payment->amount),
                'unapplied_amount' => 0,
                'payment_method' => $payment->payment_method,
                'type' => 'reversal',
                'reference' => 'REV-' . intval($payment_id),
                'notes' => sanitize_textarea_field($reason),
                'reverses_payment_id' => intval($payment_id),
                'allocation_method' => $payment->allocation_method ?? 'FIFO',
                'idempotency_key' => 'reversal_' . intval($payment_id),
            ],
            ['%d', '%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        $reversal_id = intval($wpdb->insert_id);
        $journal_id = self::create_payment_reversal_journal(
            intval($org_id),
            $reversal_id,
            floatval($payment->amount),
            current_time('Y-m-d'),
            intval($payment->customer_id),
            intval($user_id)
        );

        if (!is_wp_error($journal_id) && $journal_id) {
            $wpdb->update(
                $table_payments,
                ['journal_id' => intval($journal_id)],
                ['id' => $reversal_id],
                ['%d'],
                ['%d']
            );
        }

        orabooks_log_event('payment_reversed', 'Payment reversed', 'info', [
            'payment_id' => intval($payment_id),
            'reversal_id' => $reversal_id,
        ], intval($user_id), intval($org_id));

        return [
            'reversal_id' => $reversal_id,
            'journal_id' => is_wp_error($journal_id) ? null : $journal_id,
        ];
    }

    private static function reverse_allocation($allocation) {
        global $wpdb;

        $table_invoices = OraBooks_Database::table('invoices');
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_invoices} WHERE id = %d",
            intval($allocation->invoice_id)
        ));
        if (!$invoice) {
            return;
        }

        $new_paid = max(0, round(floatval($invoice->paid_amount) - floatval($allocation->amount), 2));
        $new_status = 'unpaid';
        if ($new_paid > 0 && $new_paid < floatval($invoice->total_amount)) {
            $new_status = 'partial';
        } elseif ($new_paid >= floatval($invoice->total_amount)) {
            $new_status = 'paid';
        }

        $wpdb->update(
            $table_invoices,
            [
                'paid_amount' => $new_paid,
                'payment_status' => $new_status,
                'lock_status' => ($new_status === 'paid') ? 'locked' : 'unlocked',
                'paid_at' => ($new_status === 'paid') ? current_time('mysql') : null,
            ],
            ['id' => intval($invoice->id)],
            ['%f', '%s', '%s', '%s'],
            ['%d']
        );
    }

    public static function get_credit_note($credit_note_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . OraBooks_Database::table('credit_notes') . " WHERE id = %d",
            intval($credit_note_id)
        ));
    }

    public static function list_credit_notes($org_id, $args = []) {
        global $wpdb;

        $table = OraBooks_Database::table('credit_notes');
        $where = 'org_id = %d';
        $params = [intval($org_id)];

        if (!empty($args['customer_id'])) {
            $where .= ' AND customer_id = %d';
            $params[] = intval($args['customer_id']);
        }

        if (!empty($args['invoice_id'])) {
            $where .= ' AND invoice_id = %d';
            $params[] = intval($args['invoice_id']);
        }

        $limit = intval($args['limit'] ?? 50);
        $params[] = $limit;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d",
            ...$params
        ));
    }

    public function monthly_statement_snapshot() {
        global $wpdb;

        self::ensure_schema();

        $table_customers = OraBooks_Database::table('customers');
        $table_snapshots = OraBooks_Database::table('customer_statement_snapshots');
        $month = wp_date('Y-m');
        $snapshot_date = wp_date('Y-m-t');

        $customers = $wpdb->get_results("SELECT id, org_id, credit_balance, opening_balance FROM {$table_customers} WHERE is_active = 1");

        foreach ($customers as $customer) {
            $wallet = self::get_customer_wallet(intval($customer->id), intval($customer->org_id));
            if (is_wp_error($wallet)) {
                continue;
            }

            $open = [];
            $paid = [];
            foreach ($wallet['invoices'] as $invoice) {
                $row = [
                    'invoice_number' => $invoice->invoice_number,
                    'total_amount' => floatval($invoice->total_amount),
                    'paid_amount' => floatval($invoice->paid_amount ?? 0),
                    'payment_status' => $invoice->payment_status,
                ];
                if (in_array($invoice->payment_status, ['unpaid', 'partial', 'overdue'], true)) {
                    $open[] = $row;
                } elseif ($invoice->payment_status === 'paid') {
                    $paid[] = $row;
                }
            }

            $wpdb->replace(
                $table_snapshots,
                [
                    'org_id' => intval($customer->org_id),
                    'customer_id' => intval($customer->id),
                    'snapshot_date' => $snapshot_date,
                    'statement_month' => $month,
                    'balance' => floatval($wallet['wallet_balance']),
                    'credit_balance' => floatval($wallet['credit_balance']),
                    'open_invoices_json' => wp_json_encode($open),
                    'paid_invoices_json' => wp_json_encode($paid),
                    'payments_json' => wp_json_encode($wallet['payments']),
                ],
                ['%d', '%d', '%s', '%s', '%f', '%f', '%s', '%s', '%s']
            );
        }
    }

    private static function generate_credit_note_number($org_id, $date) {
        global $wpdb;

        $year = date('Y', strtotime($date));
        $table = OraBooks_Database::table('credit_notes');
        $last = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(credit_note_number, '-', -1) AS UNSIGNED))
             FROM {$table}
             WHERE org_id = %d AND credit_note_number LIKE %s",
            intval($org_id),
            'CN-' . $year . '-%'
        ));

        return sprintf('CN-%s-%06d', $year, intval($last) + 1);
    }

    private static function create_payment_journal($org_id, $payment_id, $amount, $payment_date, $customer_id, $user_id) {
        $config = self::get_ar_config($org_id);
        $cash_code = $config->cash_account_code ?: '1000';
        $ar_code = $config->ar_account_code ?: '1100';

        if (!class_exists('OraBooks_Posting') || !class_exists('OraBooks_COA')) {
            return null;
        }

        if (!OraBooks_COA::get_account_by_code($org_id, $cash_code) || !OraBooks_COA::get_account_by_code($org_id, $ar_code)) {
            return null;
        }

        $journal_id = OraBooks_Posting::create_journal([
            'org_id' => intval($org_id),
            'transaction_date' => $payment_date,
            'source_type' => 'invoice_payment',
            'source_id' => intval($payment_id),
            'idempotency_key' => 'payment_' . intval($payment_id),
            'metadata' => [
                'payment_id' => intval($payment_id),
                'customer_id' => intval($customer_id),
            ],
        ], 0);

        if (is_wp_error($journal_id)) {
            return $journal_id;
        }

        OraBooks_Posting::add_lines($journal_id, [
            [
                'account_code' => $cash_code,
                'debit' => floatval($amount),
                'credit' => 0,
                'description' => 'Customer payment #' . intval($payment_id),
            ],
            [
                'account_code' => $ar_code,
                'debit' => 0,
                'credit' => floatval($amount),
                'description' => 'AR reduction for payment #' . intval($payment_id),
            ],
        ]);

        self::auto_post_system_journal(intval($journal_id));
        return intval($journal_id);
    }

    private static function create_payment_reversal_journal($org_id, $reversal_id, $amount, $payment_date, $customer_id, $user_id) {
        $config = self::get_ar_config($org_id);
        $cash_code = $config->cash_account_code ?: '1000';
        $ar_code = $config->ar_account_code ?: '1100';

        if (!class_exists('OraBooks_Posting') || !class_exists('OraBooks_COA')) {
            return null;
        }

        $journal_id = OraBooks_Posting::create_journal([
            'org_id' => intval($org_id),
            'transaction_date' => $payment_date,
            'source_type' => 'invoice_payment_reversal',
            'source_id' => intval($reversal_id),
            'idempotency_key' => 'payment_reversal_' . intval($reversal_id),
            'metadata' => [
                'reversal_id' => intval($reversal_id),
                'customer_id' => intval($customer_id),
            ],
        ], 0);

        if (is_wp_error($journal_id)) {
            return $journal_id;
        }

        OraBooks_Posting::add_lines($journal_id, [
            [
                'account_code' => $ar_code,
                'debit' => floatval($amount),
                'credit' => 0,
                'description' => 'Reverse AR for payment reversal #' . intval($reversal_id),
            ],
            [
                'account_code' => $cash_code,
                'debit' => 0,
                'credit' => floatval($amount),
                'description' => 'Reverse cash for payment reversal #' . intval($reversal_id),
            ],
        ]);

        self::auto_post_system_journal(intval($journal_id));
        return intval($journal_id);
    }

    private static function create_credit_note_journal($note, $user_id, $config) {
        if (!class_exists('OraBooks_Posting')) {
            return new WP_Error('posting_unavailable', 'Posting engine unavailable');
        }

        $org_id = intval($note->org_id);
        $amount = floatval($note->amount);
        $ar_code = $config->ar_account_code ?: '1100';
        $credit_code = ((int) $note->is_write_off === 1)
            ? ($note->bad_debt_account_code ?: $config->bad_debt_account_code ?: '1200')
            : ($config->revenue_account_code ?: '4000');

        $journal_id = OraBooks_Posting::create_journal([
            'org_id' => $org_id,
            'transaction_date' => $note->credit_date,
            'source_type' => 'credit_note',
            'source_id' => intval($note->id),
            'idempotency_key' => 'credit_note_' . intval($note->id),
            'metadata' => [
                'credit_note_number' => $note->credit_note_number,
                'is_write_off' => (int) $note->is_write_off,
            ],
        ], intval($user_id));

        if (is_wp_error($journal_id)) {
            return $journal_id;
        }

        OraBooks_Posting::add_lines($journal_id, [
            [
                'account_code' => $credit_code,
                'debit' => $amount,
                'credit' => 0,
                'description' => 'Credit note ' . $note->credit_note_number,
            ],
            [
                'account_code' => $ar_code,
                'debit' => 0,
                'credit' => $amount,
                'description' => 'AR credit ' . $note->credit_note_number,
            ],
        ]);

        return intval($journal_id);
    }

    private static function auto_post_system_journal($journal_id) {
        if (!class_exists('OraBooks_Posting')) {
            return;
        }

        $system_user = 0;
        $submit = OraBooks_Posting::submit_journal($journal_id, $system_user);
        if (is_wp_error($submit)) {
            return;
        }
        $approve = OraBooks_Posting::approve_journal($journal_id, $system_user);
        if (is_wp_error($approve)) {
            return;
        }
        OraBooks_Posting::post_journal($journal_id, $system_user);
    }

    private static function get_table_column_names($table) {
        global $wpdb;

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return [];
        }

        return $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0) ?: [];
    }

    private static function get_schema_flag($key) {
        return get_option($key, '');
    }

    private static function set_schema_flag($key, $value) {
        update_option($key, $value, false);
    }

    private function require_ar_access($user_id, $org_id, $capability = 'create_invoice') {
        if (class_exists('OraBooks_Customers')) {
            $customers = OraBooks_Customers::init();
            if (method_exists($customers, 'require_customer_access')) {
                $customers->require_customer_access($user_id, $org_id, $capability);
            }
        }
    }

    public function ajax_customer_wallet() {
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $customer_id = intval($_GET['customer_id'] ?? 0);
        $org_id = intval($_GET['org_id'] ?? 0);
        $customer = OraBooks_Customers::get_by_id($customer_id);
        if (!$customer) {
            orabooks_json_error('Customer not found', 404);
        }

        if (!$org_id) {
            $org_id = intval($customer->org_id);
        }

        $this->require_ar_access($user_id, $org_id, 'view_invoices');
        $wallet = self::get_customer_wallet($customer_id, $org_id);
        if (is_wp_error($wallet)) {
            orabooks_json_error($wallet->get_error_message(), 400);
        }

        orabooks_json_success($wallet);
    }

    public function ajax_record_customer_payment() {
        global $wpdb;
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $org_id = intval($_POST['org_id'] ?? 0);
        $customer_id = intval($_POST['customer_id'] ?? 0);

        if (!$org_id && $customer_id) {
            $org_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT org_id FROM " . OraBooks_Database::table('customers') . " WHERE id = %d",
                $customer_id
            ));
        }

        if (!$org_id || !$customer_id) {
            orabooks_json_error('Organization and customer are required', 400);
        }

        $this->require_ar_access($user_id, $org_id, 'create_invoice');

        $result = self::record_customer_payment($org_id, $customer_id, [
            'amount' => floatval($_POST['amount'] ?? 0),
            'payment_date' => sanitize_text_field($_POST['payment_date'] ?? ''),
            'payment_method' => sanitize_text_field($_POST['payment_method'] ?? 'bank_transfer'),
            'reference' => sanitize_text_field($_POST['reference'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'allocation_method' => sanitize_text_field($_POST['allocation_method'] ?? 'FIFO'),
            'target_invoice_id' => intval($_POST['invoice_id'] ?? 0),
            'idempotency_key' => sanitize_text_field($_POST['idempotency_key'] ?? ''),
        ]);

        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success($result, 'Payment recorded');
    }

    public function ajax_reverse_payment() {
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $org_id = intval($_POST['org_id'] ?? 0);
        $payment_id = intval($_POST['payment_id'] ?? 0);
        if (!$org_id || !$payment_id) {
            orabooks_json_error('Organization and payment are required', 400);
        }

        $this->require_ar_access($user_id, $org_id, 'create_invoice');
        $result = self::reverse_payment($org_id, $payment_id, $user_id, sanitize_textarea_field($_POST['reason'] ?? ''));
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success($result, 'Payment reversed');
    }

    public function ajax_create_credit_note() {
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $org_id = intval($_POST['org_id'] ?? 0);
        if (!$org_id) {
            orabooks_json_error('Organization ID required', 400);
        }

        $this->require_ar_access($user_id, $org_id, 'create_invoice');
        $result = self::create_credit_note($org_id, [
            'customer_id' => intval($_POST['customer_id'] ?? 0),
            'invoice_id' => intval($_POST['invoice_id'] ?? 0),
            'amount' => floatval($_POST['amount'] ?? 0),
            'reason' => sanitize_textarea_field($_POST['reason'] ?? ''),
            'is_write_off' => !empty($_POST['is_write_off']),
            'credit_date' => sanitize_text_field($_POST['credit_date'] ?? ''),
        ]);

        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success($result, 'Credit note created');
    }

    public function ajax_post_credit_note() {
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $org_id = intval($_POST['org_id'] ?? 0);
        $credit_note_id = intval($_POST['credit_note_id'] ?? 0);
        if (!$org_id || !$credit_note_id) {
            orabooks_json_error('Organization and credit note are required', 400);
        }

        $this->require_ar_access($user_id, $org_id, 'create_invoice');
        $result = self::post_credit_note(
            $org_id,
            $credit_note_id,
            $user_id,
            intval($_POST['second_approver_id'] ?? 0)
        );
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success(['credit_note' => $result], 'Credit note posted');
    }

    public function ajax_void_credit_note() {
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $org_id = intval($_POST['org_id'] ?? 0);
        $credit_note_id = intval($_POST['credit_note_id'] ?? 0);
        if (!$org_id || !$credit_note_id) {
            orabooks_json_error('Organization and credit note are required', 400);
        }

        $this->require_ar_access($user_id, $org_id, 'create_invoice');
        $result = self::void_credit_note($org_id, $credit_note_id, $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }

        orabooks_json_success(['credit_note' => $result], 'Credit note voided');
    }

    public function ajax_credit_notes_list() {
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $org_id = intval($_GET['org_id'] ?? 0);
        if (!$org_id) {
            orabooks_json_error('Organization ID required', 400);
        }

        $this->require_ar_access($user_id, $org_id, 'view_invoices');
        $notes = self::list_credit_notes($org_id, [
            'customer_id' => intval($_GET['customer_id'] ?? 0),
            'invoice_id' => intval($_GET['invoice_id'] ?? 0),
            'limit' => intval($_GET['limit'] ?? 50),
        ]);

        orabooks_json_success(['credit_notes' => $notes]);
    }

    public function ajax_ar_config_get() {
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $org_id = intval($_GET['org_id'] ?? 0);
        if (!$org_id) {
            orabooks_json_error('Organization ID required', 400);
        }

        $this->require_ar_access($user_id, $org_id, 'view_reports');
        orabooks_json_success(['config' => self::get_ar_config($org_id)]);
    }

    public function ajax_ar_config_set() {
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $org_id = intval($_POST['org_id'] ?? 0);
        if (!$org_id) {
            orabooks_json_error('Organization ID required', 400);
        }

        $this->require_ar_access($user_id, $org_id, 'manage_org_settings');
        $config = self::save_ar_config($org_id, $_POST);
        orabooks_json_success(['config' => $config], 'AR configuration saved');
    }

    public function ajax_customer_statements_list() {
        global $wpdb;

        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $org_id = intval($_GET['org_id'] ?? 0);
        $customer_id = intval($_GET['customer_id'] ?? 0);
        if (!$org_id || !$customer_id) {
            orabooks_json_error('Organization and customer are required', 400);
        }

        $this->require_ar_access($user_id, $org_id, 'view_invoices');

        $table = OraBooks_Database::table('customer_statement_snapshots');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d AND customer_id = %d ORDER BY statement_month DESC",
            $org_id,
            $customer_id
        ));

        orabooks_json_success(['statements' => $rows]);
    }
}
