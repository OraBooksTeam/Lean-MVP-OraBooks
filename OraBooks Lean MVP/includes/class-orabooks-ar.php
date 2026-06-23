<?php
/**
 * OraBooks AR Extension (SL-021)
 *
 * Credit notes, payment allocations, customer-level payments, reversals,
 * AR config, statement snapshots, and wallet/credit balance maintenance.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_AR {

    private static $instance = null;

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();

            add_action('wp_ajax_orabooks_invoice_submit', [self::$instance, 'ajax_invoice_submit']);
            add_action('wp_ajax_nopriv_orabooks_invoice_submit', [self::$instance, 'ajax_invoice_submit']);
            add_action('wp_ajax_orabooks_invoice_approve', [self::$instance, 'ajax_invoice_approve']);
            add_action('wp_ajax_nopriv_orabooks_invoice_approve', [self::$instance, 'ajax_invoice_approve']);
            add_action('wp_ajax_orabooks_customer_payment_record', [self::$instance, 'ajax_record_customer_payment']);
            add_action('wp_ajax_nopriv_orabooks_customer_payment_record', [self::$instance, 'ajax_record_customer_payment']);
            add_action('wp_ajax_orabooks_payment_reverse', [self::$instance, 'ajax_reverse_payment']);
            add_action('wp_ajax_nopriv_orabooks_payment_reverse', [self::$instance, 'ajax_reverse_payment']);
            add_action('wp_ajax_orabooks_credit_note_create', [self::$instance, 'ajax_create_credit_note']);
            add_action('wp_ajax_nopriv_orabooks_credit_note_create', [self::$instance, 'ajax_create_credit_note']);
            add_action('wp_ajax_orabooks_credit_note_post', [self::$instance, 'ajax_post_credit_note']);
            add_action('wp_ajax_nopriv_orabooks_credit_note_post', [self::$instance, 'ajax_post_credit_note']);
            add_action('wp_ajax_orabooks_credit_note_void', [self::$instance, 'ajax_void_credit_note']);
            add_action('wp_ajax_nopriv_orabooks_credit_note_void', [self::$instance, 'ajax_void_credit_note']);
            add_action('wp_ajax_orabooks_credit_notes_list', [self::$instance, 'ajax_credit_notes_list']);
            add_action('wp_ajax_nopriv_orabooks_credit_notes_list', [self::$instance, 'ajax_credit_notes_list']);
            add_action('wp_ajax_orabooks_ar_config_get', [self::$instance, 'ajax_ar_config_get']);
            add_action('wp_ajax_nopriv_orabooks_ar_config_get', [self::$instance, 'ajax_ar_config_get']);
            add_action('wp_ajax_orabooks_ar_config_save', [self::$instance, 'ajax_ar_config_save']);
            add_action('wp_ajax_nopriv_orabooks_ar_config_save', [self::$instance, 'ajax_ar_config_save']);
            add_action('wp_ajax_orabooks_ar_aging', [self::$instance, 'ajax_ar_aging']);
            add_action('wp_ajax_nopriv_orabooks_ar_aging', [self::$instance, 'ajax_ar_aging']);
            add_action('wp_ajax_orabooks_customer_statements_list', [self::$instance, 'ajax_statements_list']);
            add_action('wp_ajax_nopriv_orabooks_customer_statements_list', [self::$instance, 'ajax_statements_list']);

            add_action('orabooks_daily_customer_statement_snapshot', [self::$instance, 'daily_statement_snapshot']);
            add_action('orabooks_daily_invoice_dunning_check', [self::$instance, 'daily_dunning_check']);
        }
        return self::$instance;
    }

    public static function get_create_table_sql() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $tables = [];
        $orgs = OraBooks_Database::table('organizations');
        $customers = OraBooks_Database::table('customers');
        $invoices = OraBooks_Database::table('invoices');
        $payments = OraBooks_Database::table('payments');

        $tables[] = "CREATE TABLE IF NOT EXISTS " . OraBooks_Database::table('customer_ar_configs') . " (
            org_id BIGINT UNSIGNED PRIMARY KEY,
            auto_post_on_approve TINYINT(1) DEFAULT 1,
            auto_apply_customer_credit TINYINT(1) DEFAULT 1,
            write_off_threshold DECIMAL(20,2) DEFAULT 100,
            bad_debt_account_code VARCHAR(50) DEFAULT '6100',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$orgs}(id) ON DELETE CASCADE
        ) {$charset};";

        $tables[] = "CREATE TABLE IF NOT EXISTS " . OraBooks_Database::table('payment_allocations') . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            payment_id BIGINT UNSIGNED NOT NULL,
            invoice_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(20,2) NOT NULL,
            allocation_method ENUM('FIFO','manual','auto_credit') DEFAULT 'FIFO',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$orgs}(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES {$customers}(id) ON DELETE CASCADE,
            FOREIGN KEY (payment_id) REFERENCES {$payments}(id) ON DELETE CASCADE,
            FOREIGN KEY (invoice_id) REFERENCES {$invoices}(id) ON DELETE CASCADE,
            INDEX idx_invoice (invoice_id),
            INDEX idx_customer (customer_id)
        ) {$charset};";

        $tables[] = "CREATE TABLE IF NOT EXISTS " . OraBooks_Database::table('credit_notes') . " (
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
            requires_second_approval TINYINT(1) DEFAULT 0,
            workflow_status ENUM('draft','submitted','approved','posted','void') DEFAULT 'draft',
            journal_id BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NULL,
            approved_by BIGINT UNSIGNED NULL,
            approved_at TIMESTAMP NULL,
            posted_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$orgs}(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES {$customers}(id) ON DELETE CASCADE,
            FOREIGN KEY (invoice_id) REFERENCES {$invoices}(id) ON DELETE SET NULL,
            UNIQUE KEY uk_org_credit_note (org_id, credit_note_number),
            INDEX idx_customer (customer_id),
            INDEX idx_invoice (invoice_id),
            INDEX idx_status (workflow_status)
        ) {$charset};";

        $tables[] = "CREATE TABLE IF NOT EXISTS " . OraBooks_Database::table('customer_statement_snapshots') . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            statement_month CHAR(7) NOT NULL,
            ar_balance DECIMAL(20,2) NOT NULL DEFAULT 0,
            credit_balance DECIMAL(20,2) NOT NULL DEFAULT 0,
            open_invoices_json JSON NULL,
            paid_invoices_json JSON NULL,
            aging_json JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$orgs}(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES {$customers}(id) ON DELETE CASCADE,
            UNIQUE KEY uk_customer_month (customer_id, statement_month),
            INDEX idx_org (org_id)
        ) {$charset};";

        $tables[] = "CREATE TABLE IF NOT EXISTS " . OraBooks_Database::table('installment_plans') . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            invoice_id BIGINT UNSIGNED NOT NULL,
            installment_number INT NOT NULL,
            due_date DATE NOT NULL,
            amount DECIMAL(20,2) NOT NULL,
            status ENUM('scheduled','paid','overdue','cancelled') DEFAULT 'scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$orgs}(id) ON DELETE CASCADE,
            FOREIGN KEY (invoice_id) REFERENCES {$invoices}(id) ON DELETE CASCADE,
            INDEX idx_invoice (invoice_id)
        ) {$charset};";

        return $tables;
    }

    public static function ensure_schema() {
        global $wpdb;

        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;

        foreach (self::get_create_table_sql() as $sql) {
            $wpdb->query($sql);
        }

        $invoices = OraBooks_Database::table('invoices');
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $invoices)) === $invoices) {
            $cols = $wpdb->get_col("SHOW COLUMNS FROM {$invoices}", 0);
            $add = [
                'lock_status' => "ALTER TABLE {$invoices} ADD COLUMN lock_status ENUM('unlocked','locked') DEFAULT 'unlocked'",
                'journal_id' => "ALTER TABLE {$invoices} ADD COLUMN journal_id BIGINT UNSIGNED NULL",
                'rendered_copy' => "ALTER TABLE {$invoices} ADD COLUMN rendered_copy JSON NULL",
                'approved_by' => "ALTER TABLE {$invoices} ADD COLUMN approved_by BIGINT UNSIGNED NULL",
                'approved_at' => "ALTER TABLE {$invoices} ADD COLUMN approved_at TIMESTAMP NULL",
                'posted_at' => "ALTER TABLE {$invoices} ADD COLUMN posted_at TIMESTAMP NULL",
                'dunning_stage' => "ALTER TABLE {$invoices} ADD COLUMN dunning_stage ENUM('none','reminder_1','reminder_2','escalation','collection','legal_hold') DEFAULT 'none'",
                'installment_plan_id' => "ALTER TABLE {$invoices} ADD COLUMN installment_plan_id BIGINT UNSIGNED NULL",
            ];
            foreach ($add as $col => $sql) {
                if (!in_array($col, $cols, true)) {
                    $wpdb->query($sql);
                }
            }
        }

        $payments = OraBooks_Database::table('payments');
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $payments)) === $payments) {
            $cols = $wpdb->get_col("SHOW COLUMNS FROM {$payments}", 0);
            $add = [
                'customer_id' => "ALTER TABLE {$payments} ADD COLUMN customer_id BIGINT UNSIGNED NULL",
                'unapplied_amount' => "ALTER TABLE {$payments} ADD COLUMN unapplied_amount DECIMAL(20,2) NOT NULL DEFAULT 0",
                'type' => "ALTER TABLE {$payments} ADD COLUMN type ENUM('payment','reversal','refund') DEFAULT 'payment'",
                'reverses_payment_id' => "ALTER TABLE {$payments} ADD COLUMN reverses_payment_id BIGINT UNSIGNED NULL",
                'journal_id' => "ALTER TABLE {$payments} ADD COLUMN journal_id BIGINT UNSIGNED NULL",
            ];
            foreach ($add as $col => $sql) {
                if (!in_array($col, $cols, true)) {
                    $wpdb->query($sql);
                }
            }

            $invoice_col = $wpdb->get_row("SHOW COLUMNS FROM {$payments} LIKE 'invoice_id'");
            if ($invoice_col && strtoupper((string) $invoice_col->Null) === 'NO') {
                $wpdb->query("ALTER TABLE {$payments} MODIFY invoice_id BIGINT UNSIGNED NULL");
            }
        }
    }

    public static function get_ar_config($org_id) {
        global $wpdb;
        self::ensure_schema();

        $table = OraBooks_Database::table('customer_ar_configs');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE org_id = %d", (int) $org_id));
        if ($row) {
            return $row;
        }

        return (object) [
            'org_id' => (int) $org_id,
            'auto_post_on_approve' => 1,
            'auto_apply_customer_credit' => 1,
            'write_off_threshold' => 100,
            'bad_debt_account_code' => '6100',
        ];
    }

    public static function save_ar_config($org_id, array $data) {
        global $wpdb;
        self::ensure_schema();

        $table = OraBooks_Database::table('customer_ar_configs');
        $payload = [
            'org_id' => (int) $org_id,
            'auto_post_on_approve' => !empty($data['auto_post_on_approve']) ? 1 : 0,
            'auto_apply_customer_credit' => !empty($data['auto_apply_customer_credit']) ? 1 : 0,
            'write_off_threshold' => round((float) ($data['write_off_threshold'] ?? 100), 2),
            'bad_debt_account_code' => sanitize_text_field($data['bad_debt_account_code'] ?? '6100'),
        ];

        $existing = $wpdb->get_var($wpdb->prepare("SELECT org_id FROM {$table} WHERE org_id = %d", (int) $org_id));
        if ($existing) {
            unset($payload['org_id']);
            $wpdb->update($table, $payload, ['org_id' => (int) $org_id]);
        } else {
            $wpdb->insert($table, $payload);
        }

        orabooks_log_event('ar_config_updated', 'Customer AR configuration updated', 'info', $payload, orabooks_get_current_user_id(), (int) $org_id);
        return self::get_ar_config($org_id);
    }

    public static function submit_invoice($org_id, $invoice_id, $user_id) {
        $invoice = OraBooks_Customers::get_invoice((int) $invoice_id);
        if (!$invoice || (int) $invoice->org_id !== (int) $org_id) {
            return new WP_Error('not_found', 'Invoice not found');
        }
        if (!in_array($invoice->workflow_status, ['draft'], true)) {
            return new WP_Error('invalid_status', 'Only draft invoices can be submitted');
        }

        if (!class_exists('OraBooks_Workflow')) {
            return new WP_Error('workflow_unavailable', 'Workflow engine unavailable');
        }

        $result = OraBooks_Workflow::transition('invoice', (int) $invoice_id, 'submit', [
            'user_id' => (int) $user_id,
            'org_id'  => (int) $org_id,
        ]);
        if (is_wp_error($result)) {
            return $result;
        }

        orabooks_log_event('invoice_submitted', "Invoice {$invoice->invoice_number} submitted", 'info', [
            'invoice_id' => (int) $invoice_id,
        ], (int) $user_id, (int) $org_id);

        return OraBooks_Customers::get_invoice((int) $invoice_id);
    }

    public static function approve_invoice($org_id, $invoice_id, $user_id) {
        $invoice = OraBooks_Customers::get_invoice((int) $invoice_id);
        if (!$invoice || (int) $invoice->org_id !== (int) $org_id) {
            return new WP_Error('not_found', 'Invoice not found');
        }
        if (!in_array($invoice->workflow_status, ['submitted', 'sent'], true)) {
            return new WP_Error('invalid_status', 'Only submitted invoices can be approved');
        }

        if (!class_exists('OraBooks_Workflow')) {
            return new WP_Error('workflow_unavailable', 'Workflow engine unavailable');
        }

        $result = OraBooks_Workflow::transition('invoice', (int) $invoice_id, 'approve', [
            'user_id' => (int) $user_id,
            'org_id'  => (int) $org_id,
            'row_updates' => [
                'approved_by' => (int) $user_id,
                'approved_at' => current_time('mysql'),
            ],
        ]);
        if (is_wp_error($result)) {
            return $result;
        }

        orabooks_log_event('invoice_approved', "Invoice {$invoice->invoice_number} approved", 'info', [
            'invoice_id' => (int) $invoice_id,
        ], (int) $user_id, (int) $org_id);

        $config = self::get_ar_config($org_id);
        if (!empty($config->auto_post_on_approve)) {
            return OraBooks_Customers::post_invoice((int) $org_id, (int) $invoice_id, (int) $user_id);
        }

        return OraBooks_Customers::get_invoice((int) $invoice_id);
    }

    public static function adjust_customer_credit_balance($customer_id, $org_id, $delta) {
        global $wpdb;
        $table = OraBooks_Database::table('customers');
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT credit_balance FROM {$table} WHERE id = %d AND org_id = %d",
            (int) $customer_id,
            (int) $org_id
        ));
        if (!$customer) {
            return new WP_Error('not_found', 'Customer not found');
        }

        $new_balance = max(0, round((float) $customer->credit_balance + (float) $delta, 2));
        $wpdb->update(
            $table,
            ['credit_balance' => $new_balance],
            ['id' => (int) $customer_id, 'org_id' => (int) $org_id],
            ['%f'],
            ['%d', '%d']
        );

        return $new_balance;
    }

    public static function apply_customer_credit_to_invoice($org_id, $customer_id, $invoice_id, $user_id) {
        global $wpdb;

        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . OraBooks_Database::table('customers') . " WHERE id = %d AND org_id = %d",
            (int) $customer_id,
            (int) $org_id
        ));
        if (!$customer || (float) $customer->credit_balance <= 0 || !(int) $customer->auto_apply_credit) {
            return 0.0;
        }

        $invoice = OraBooks_Customers::get_invoice((int) $invoice_id);
        if (!$invoice || (int) $invoice->customer_id !== (int) $customer_id) {
            return 0.0;
        }

        $outstanding = max(0, round((float) $invoice->total_amount - (float) ($invoice->paid_amount ?? 0), 2));
        if ($outstanding <= 0) {
            return 0.0;
        }

        $applied = min((float) $customer->credit_balance, $outstanding);
        if ($applied <= 0) {
            return 0.0;
        }

        $table_payments = OraBooks_Database::table('payments');
        $wpdb->insert(
            $table_payments,
            [
                'org_id' => (int) $org_id,
                'customer_id' => (int) $customer_id,
                'invoice_id' => (int) $invoice_id,
                'payment_date' => current_time('Y-m-d'),
                'amount' => $applied,
                'unapplied_amount' => 0,
                'payment_method' => 'other',
                'type' => 'payment',
                'reference' => 'AUTO_CREDIT',
                'notes' => 'Auto-applied customer credit',
                'idempotency_key' => orabooks_uuid(),
            ],
            ['%d', '%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s']
        );
        $payment_id = (int) $wpdb->insert_id;

        self::insert_allocation((int) $org_id, (int) $customer_id, $payment_id, (int) $invoice_id, $applied, 'auto_credit');
        self::update_invoice_paid_amount((int) $invoice_id, $applied);
        self::adjust_customer_credit_balance((int) $customer_id, (int) $org_id, -$applied);

        orabooks_log_event('customer_credit_applied', 'Customer credit auto-applied to invoice', 'info', [
            'invoice_id' => (int) $invoice_id,
            'amount' => $applied,
        ], (int) $user_id, (int) $org_id);

        return $applied;
    }

    public static function record_customer_payment($org_id, $customer_id, array $data) {
        global $wpdb;
        self::ensure_schema();

        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'Payment amount must be greater than 0');
        }

        $invoice_id = !empty($data['invoice_id']) ? (int) $data['invoice_id'] : 0;
        $table_payments = OraBooks_Database::table('payments');

        $wpdb->insert(
            $table_payments,
            [
                'org_id' => (int) $org_id,
                'customer_id' => (int) $customer_id,
                'invoice_id' => $invoice_id > 0 ? $invoice_id : null,
                'payment_date' => $data['payment_date'] ?? current_time('Y-m-d'),
                'amount' => $amount,
                'unapplied_amount' => 0,
                'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                'type' => 'payment',
                'reference' => sanitize_text_field($data['reference'] ?? ''),
                'notes' => sanitize_textarea_field($data['notes'] ?? ''),
                'idempotency_key' => $data['idempotency_key'] ?? orabooks_uuid(),
            ],
            ['%d', '%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s']
        );

        $payment_id = (int) $wpdb->insert_id;
        $method = sanitize_text_field($data['allocation_method'] ?? 'FIFO');

        if ($invoice_id > 0) {
            $invoice = OraBooks_Customers::get_invoice($invoice_id);
            if (!$invoice || (int) $invoice->customer_id !== (int) $customer_id) {
                return new WP_Error('invalid_invoice', 'Invoice does not belong to this customer');
            }
            $applied = min($amount, max(0, (float) $invoice->total_amount - (float) ($invoice->paid_amount ?? 0)));
            self::insert_allocation((int) $org_id, (int) $customer_id, $payment_id, $invoice_id, $applied, 'manual');
            self::update_invoice_paid_amount($invoice_id, $applied);
            $remaining = round($amount - $applied, 2);
        } else {
            $remaining = self::allocate_payment_fifo((int) $org_id, (int) $customer_id, $payment_id, $amount, $method);
        }

        if ($remaining > 0) {
            self::adjust_customer_credit_balance((int) $customer_id, (int) $org_id, $remaining);
            $wpdb->update(
                $table_payments,
                ['unapplied_amount' => $remaining],
                ['id' => $payment_id],
                ['%f'],
                ['%d']
            );
        }

        OraBooks_Customers::create_payment_journal_for_ar(
            (int) $org_id,
            $payment_id,
            $amount - $remaining,
            $data['payment_date'] ?? current_time('Y-m-d'),
            'Customer payment',
            (int) ($data['user_id'] ?? orabooks_get_current_user_id())
        );

        OraBooks_Customers::recompute_active_status((int) $customer_id);

        orabooks_log_event('customer_payment_recorded', 'Customer payment recorded', 'info', [
            'payment_id' => $payment_id,
            'customer_id' => (int) $customer_id,
            'amount' => $amount,
            'unapplied_amount' => $remaining,
        ], (int) ($data['user_id'] ?? orabooks_get_current_user_id()), (int) $org_id);

        return [
            'payment_id' => $payment_id,
            'allocated_amount' => round($amount - $remaining, 2),
            'unapplied_amount' => round($remaining, 2),
        ];
    }

    public static function reverse_payment($org_id, $payment_id, $user_id, $reason = '') {
        global $wpdb;
        self::ensure_schema();

        $table = OraBooks_Database::table('payments');
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND org_id = %d",
            (int) $payment_id,
            (int) $org_id
        ));
        if (!$payment || $payment->type !== 'payment') {
            return new WP_Error('not_found', 'Payment not found');
        }

        $amount = (float) $payment->amount;
        $wpdb->insert(
            $table,
            [
                'org_id' => (int) $org_id,
                'customer_id' => (int) $payment->customer_id,
                'invoice_id' => (int) $payment->invoice_id,
                'payment_date' => current_time('Y-m-d'),
                'amount' => -abs($amount),
                'unapplied_amount' => 0,
                'payment_method' => $payment->payment_method,
                'type' => 'reversal',
                'reference' => sanitize_text_field($reason),
                'notes' => 'Reversal of payment #' . (int) $payment_id,
                'reverses_payment_id' => (int) $payment_id,
                'idempotency_key' => orabooks_uuid(),
            ],
            ['%d', '%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s']
        );

        $reversal_id = (int) $wpdb->insert_id;

        $alloc_table = OraBooks_Database::table('payment_allocations');
        $allocations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$alloc_table} WHERE payment_id = %d",
            (int) $payment_id
        ));
        foreach ($allocations ?: [] as $alloc) {
            self::update_invoice_paid_amount((int) $alloc->invoice_id, -(float) $alloc->amount);
        }

        if ((float) ($payment->unapplied_amount ?? 0) > 0) {
            self::adjust_customer_credit_balance((int) $payment->customer_id, (int) $org_id, -(float) $payment->unapplied_amount);
        }

        orabooks_log_event('payment_reversed', 'Payment reversed', 'info', [
            'payment_id' => (int) $payment_id,
            'reversal_id' => $reversal_id,
            'reason' => $reason,
        ], (int) $user_id, (int) $org_id);

        return ['reversal_id' => $reversal_id];
    }

    public static function create_credit_note($org_id, array $data) {
        global $wpdb;
        self::ensure_schema();

        $customer_id = (int) ($data['customer_id'] ?? 0);
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $reason = sanitize_textarea_field($data['reason'] ?? '');

        if ($customer_id <= 0 || $amount <= 0 || $reason === '') {
            return new WP_Error('invalid_credit_note', 'Customer, amount, and reason are required');
        }

        $config = self::get_ar_config($org_id);
        $is_write_off = !empty($data['is_write_off']);
        $bad_debt = $is_write_off
            ? sanitize_text_field($data['bad_debt_account_code'] ?? $config->bad_debt_account_code)
            : null;
        $requires_second = $is_write_off && $amount > (float) $config->write_off_threshold;

        $number = !empty($data['credit_note_number'])
            ? sanitize_text_field($data['credit_note_number'])
            : self::generate_credit_note_number($org_id, $data['credit_date'] ?? current_time('Y-m-d'));

        $wpdb->insert(
            OraBooks_Database::table('credit_notes'),
            [
                'org_id' => (int) $org_id,
                'customer_id' => $customer_id,
                'invoice_id' => !empty($data['invoice_id']) ? (int) $data['invoice_id'] : null,
                'credit_note_number' => $number,
                'credit_date' => $data['credit_date'] ?? current_time('Y-m-d'),
                'amount' => $amount,
                'reason' => $reason,
                'is_write_off' => $is_write_off ? 1 : 0,
                'bad_debt_account_code' => $bad_debt,
                'requires_second_approval' => $requires_second ? 1 : 0,
                'workflow_status' => 'draft',
                'created_by' => orabooks_get_current_user_id(),
            ],
            ['%d', '%d', '%d', '%s', '%s', '%f', '%s', '%d', '%s', '%d', '%s', '%d']
        );

        $id = (int) $wpdb->insert_id;
        orabooks_log_event('credit_note_created', "Credit note {$number} created", 'info', [
            'credit_note_id' => $id,
            'customer_id' => $customer_id,
            'amount' => $amount,
        ], orabooks_get_current_user_id(), (int) $org_id);

        return self::format_credit_note(self::get_credit_note($id, (int) $org_id));
    }

    public static function post_credit_note($org_id, $credit_note_id, $user_id) {
        global $wpdb;

        $note = self::get_credit_note((int) $credit_note_id, (int) $org_id);
        if (!$note) {
            return new WP_Error('not_found', 'Credit note not found');
        }
        if (!in_array($note->workflow_status, ['draft', 'submitted', 'approved'], true)) {
            return new WP_Error('invalid_status', 'Credit note cannot be posted from current status');
        }

        if ((int) $note->requires_second_approval === 1 && empty($note->approved_by)) {
            return new WP_Error('approval_required', 'Write-off above threshold requires manager approval');
        }

        $journal_id = self::create_credit_note_journal($note, (int) $user_id);
        if (is_wp_error($journal_id)) {
            return $journal_id;
        }

        $wpdb->update(
            OraBooks_Database::table('credit_notes'),
            [
                'workflow_status' => 'posted',
                'journal_id' => is_numeric($journal_id) ? (int) $journal_id : null,
                'posted_at' => current_time('mysql'),
            ],
            ['id' => (int) $credit_note_id],
            ['%s', '%d', '%s'],
            ['%d']
        );

        if (!empty($note->invoice_id)) {
            $invoice = OraBooks_Customers::get_invoice((int) $note->invoice_id);
            if ($invoice) {
                $new_paid = max(0, round((float) ($invoice->paid_amount ?? 0) - (float) $note->amount, 2));
                $wpdb->update(
                    OraBooks_Database::table('invoices'),
                    [
                        'paid_amount' => $new_paid,
                        'payment_status' => 'credited',
                    ],
                    ['id' => (int) $note->invoice_id],
                    ['%f', '%s'],
                    ['%d']
                );
            }
        }

        orabooks_log_event('credit_note_posted', "Credit note {$note->credit_note_number} posted", 'info', [
            'credit_note_id' => (int) $credit_note_id,
            'journal_id' => $journal_id,
        ], (int) $user_id, (int) $org_id);

        return self::format_credit_note(self::get_credit_note((int) $credit_note_id, (int) $org_id));
    }

    public static function void_credit_note($org_id, $credit_note_id, $user_id, $reason = '') {
        $note = self::get_credit_note((int) $credit_note_id, (int) $org_id);
        if (!$note) {
            return new WP_Error('not_found', 'Credit note not found');
        }
        if (!in_array($note->workflow_status, ['draft', 'submitted'], true)) {
            return new WP_Error('invalid_status', 'Only draft or submitted credit notes can be voided');
        }

        global $wpdb;
        $wpdb->update(
            OraBooks_Database::table('credit_notes'),
            ['workflow_status' => 'void'],
            ['id' => (int) $credit_note_id],
            ['%s'],
            ['%d']
        );

        orabooks_log_event('credit_note_voided', "Credit note {$note->credit_note_number} voided", 'info', [
            'credit_note_id' => (int) $credit_note_id,
            'reason' => $reason,
        ], (int) $user_id, (int) $org_id);

        return self::format_credit_note(self::get_credit_note((int) $credit_note_id, (int) $org_id));
    }

    public static function build_invoice_rendered_copy($invoice) {
        if (!$invoice) {
            return null;
        }

        return [
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date,
            'due_date' => $invoice->due_date,
            'customer_id' => (int) $invoice->customer_id,
            'description' => $invoice->description ?? '',
            'subtotal' => max(0, round((float) $invoice->total_amount - (float) ($invoice->tax_amount ?? 0), 2)),
            'tax_amount' => (float) ($invoice->tax_amount ?? 0),
            'tax_rate' => (float) ($invoice->tax_rate ?? 0),
            'total_amount' => (float) $invoice->total_amount,
            'currency' => $invoice->currency ?? 'USD',
            'rendered_at' => current_time('mysql'),
        ];
    }

    public static function get_ar_aging($org_id, $as_of_date = null) {
        global $wpdb;
        $as_of_date = $as_of_date ?: current_time('Y-m-d');
        $table = OraBooks_Database::table('invoices');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, customer_id, invoice_number, due_date, total_amount, paid_amount, payment_status
             FROM {$table}
             WHERE org_id = %d AND workflow_status = 'posted' AND payment_status IN ('unpaid','partial','overdue')
             ORDER BY due_date ASC",
            (int) $org_id
        ));

        $buckets = ['current' => 0.0, '30' => 0.0, '60' => 0.0, '90_plus' => 0.0];
        foreach ($rows ?: [] as $row) {
            $outstanding = max(0, (float) $row->total_amount - (float) ($row->paid_amount ?? 0));
            $days = floor((strtotime($as_of_date) - strtotime($row->due_date)) / DAY_IN_SECONDS);
            if ($days <= 0) {
                $bucket = 'current';
            } elseif ($days <= 30) {
                $bucket = '30';
            } elseif ($days <= 60) {
                $bucket = '60';
            } else {
                $bucket = '90_plus';
            }
            $buckets[$bucket] += $outstanding;
        }

        return $buckets;
    }

    public function daily_statement_snapshot() {
        global $wpdb;
        self::ensure_schema();

        $month = current_time('Y-m');
        $customers = $wpdb->get_results("SELECT * FROM " . OraBooks_Database::table('customers'));
        $snap_table = OraBooks_Database::table('customer_statement_snapshots');
        $inv_table = OraBooks_Database::table('invoices');

        foreach ($customers ?: [] as $customer) {
            $open = $wpdb->get_results($wpdb->prepare(
                "SELECT id, invoice_number, due_date, total_amount, paid_amount, payment_status
                 FROM {$inv_table}
                 WHERE org_id = %d AND customer_id = %d AND workflow_status = 'posted'
                   AND payment_status IN ('unpaid','partial','overdue')",
                (int) $customer->org_id,
                (int) $customer->id
            ));
            $ar_balance = 0.0;
            foreach ($open ?: [] as $inv) {
                $ar_balance += max(0, (float) $inv->total_amount - (float) ($inv->paid_amount ?? 0));
            }

            $wpdb->replace(
                $snap_table,
                [
                    'org_id' => (int) $customer->org_id,
                    'customer_id' => (int) $customer->id,
                    'statement_month' => $month,
                    'ar_balance' => $ar_balance,
                    'credit_balance' => (float) ($customer->credit_balance ?? 0),
                    'open_invoices_json' => wp_json_encode($open ?: []),
                    'paid_invoices_json' => wp_json_encode([]),
                    'aging_json' => wp_json_encode(self::get_ar_aging((int) $customer->org_id)),
                ],
                ['%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s']
            );
        }
    }

    public function daily_dunning_check() {
        global $wpdb;
        self::ensure_schema();

        $table = OraBooks_Database::table('invoices');
        $wpdb->query(
            "UPDATE {$table}
             SET dunning_stage = CASE
                 WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 1 AND 14 THEN 'reminder_1'
                 WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 15 AND 30 THEN 'reminder_2'
                 WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 31 AND 60 THEN 'escalation'
                 WHEN DATEDIFF(CURDATE(), due_date) > 60 THEN 'collection'
                 ELSE dunning_stage
             END
             WHERE payment_status IN ('unpaid','partial','overdue')
               AND workflow_status = 'posted'
               AND due_date < CURDATE()"
        );
    }

    private static function allocate_payment_fifo($org_id, $customer_id, $payment_id, $amount, $method = 'FIFO') {
        global $wpdb;
        $remaining = round((float) $amount, 2);
        $table = OraBooks_Database::table('invoices');

        $invoices = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE org_id = %d AND customer_id = %d AND workflow_status = 'posted'
               AND payment_status IN ('unpaid','partial','overdue')
             ORDER BY due_date ASC, id ASC",
            (int) $org_id,
            (int) $customer_id
        ));

        foreach ($invoices ?: [] as $invoice) {
            if ($remaining <= 0) {
                break;
            }
            $outstanding = max(0, round((float) $invoice->total_amount - (float) ($invoice->paid_amount ?? 0), 2));
            if ($outstanding <= 0) {
                continue;
            }
            $applied = min($remaining, $outstanding);
            self::insert_allocation((int) $org_id, (int) $customer_id, (int) $payment_id, (int) $invoice->id, $applied, $method);
            self::update_invoice_paid_amount((int) $invoice->id, $applied);
            $remaining = round($remaining - $applied, 2);
        }

        return $remaining;
    }

    public static function insert_allocation_public($org_id, $customer_id, $payment_id, $invoice_id, $amount, $method = 'manual') {
        self::insert_allocation((int) $org_id, (int) $customer_id, (int) $payment_id, (int) $invoice_id, (float) $amount, $method);
    }

    private static function insert_allocation($org_id, $customer_id, $payment_id, $invoice_id, $amount, $method) {
        global $wpdb;
        $wpdb->insert(
            OraBooks_Database::table('payment_allocations'),
            [
                'org_id' => (int) $org_id,
                'customer_id' => (int) $customer_id,
                'payment_id' => (int) $payment_id,
                'invoice_id' => (int) $invoice_id,
                'amount' => round((float) $amount, 2),
                'allocation_method' => sanitize_text_field($method),
            ],
            ['%d', '%d', '%d', '%d', '%f', '%s']
        );
    }

    private static function update_invoice_paid_amount($invoice_id, $delta) {
        global $wpdb;
        $table = OraBooks_Database::table('invoices');
        $invoice = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $invoice_id));
        if (!$invoice) {
            return;
        }

        $paid = max(0, round((float) ($invoice->paid_amount ?? 0) + (float) $delta, 2));
        $total = (float) $invoice->total_amount;
        if ($paid >= $total) {
            $status = 'paid';
            $paid_at = current_time('mysql');
            $lock = 'locked';
        } elseif ($paid > 0) {
            $status = 'partial';
            $paid_at = null;
            $lock = $invoice->lock_status ?? 'unlocked';
        } else {
            $status = 'unpaid';
            $paid_at = null;
            $lock = $invoice->lock_status ?? 'unlocked';
        }

        $wpdb->update(
            $table,
            [
                'paid_amount' => $paid,
                'payment_status' => $status,
                'paid_at' => $paid_at,
                'lock_status' => $lock,
            ],
            ['id' => (int) $invoice_id],
            ['%f', '%s', '%s', '%s'],
            ['%d']
        );
    }

    private static function create_credit_note_journal($note, $user_id) {
        if (!class_exists('OraBooks_Posting')) {
            return null;
        }

        $org_id = (int) $note->org_id;
        $amount = (float) $note->amount;
        $ar_code = '1100';
        $credit_code = (int) $note->is_write_off === 1 && !empty($note->bad_debt_account_code)
            ? $note->bad_debt_account_code
            : '4000';

        $journal_id = OraBooks_Posting::create_journal([
            'org_id' => $org_id,
            'transaction_date' => $note->credit_date,
            'source_type' => 'credit_note',
            'source_id' => (int) $note->id,
            'idempotency_key' => 'credit_note_' . (int) $note->id,
        ], (int) $user_id);

        if (is_wp_error($journal_id)) {
            return $journal_id;
        }

        OraBooks_Posting::add_lines($journal_id, [
            ['account_code' => $credit_code, 'debit' => $amount, 'credit' => 0, 'description' => 'Credit note ' . $note->credit_note_number],
            ['account_code' => $ar_code, 'debit' => 0, 'credit' => $amount, 'description' => 'Credit note ' . $note->credit_note_number],
        ]);

        OraBooks_Posting::submit_journal($journal_id, (int) $user_id);
        OraBooks_Posting::approve_journal($journal_id, (int) $user_id);
        OraBooks_Posting::post_journal($journal_id, (int) $user_id);

        return $journal_id;
    }

    public static function get_credit_note($id, $org_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . OraBooks_Database::table('credit_notes') . " WHERE id = %d AND org_id = %d",
            (int) $id,
            (int) $org_id
        ));
    }

    public static function list_credit_notes($org_id, $customer_id = 0) {
        global $wpdb;
        self::ensure_schema();
        $table = OraBooks_Database::table('credit_notes');
        if ($customer_id > 0) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE org_id = %d AND customer_id = %d ORDER BY created_at DESC LIMIT 50",
                (int) $org_id,
                (int) $customer_id
            ));
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE org_id = %d ORDER BY created_at DESC LIMIT 50",
                (int) $org_id
            ));
        }
        return array_map([self::class, 'format_credit_note'], $rows ?: []);
    }

    public static function format_credit_note($row) {
        if (!$row) {
            return null;
        }
        return [
            'id' => (int) $row->id,
            'org_id' => (int) $row->org_id,
            'customer_id' => (int) $row->customer_id,
            'invoice_id' => !empty($row->invoice_id) ? (int) $row->invoice_id : null,
            'credit_note_number' => $row->credit_note_number,
            'credit_date' => $row->credit_date,
            'amount' => (float) $row->amount,
            'reason' => $row->reason,
            'is_write_off' => (int) ($row->is_write_off ?? 0),
            'requires_second_approval' => (int) ($row->requires_second_approval ?? 0),
            'workflow_status' => $row->workflow_status,
            'journal_id' => !empty($row->journal_id) ? (int) $row->journal_id : null,
            'created_at' => $row->created_at,
        ];
    }

    private static function generate_credit_note_number($org_id, $date) {
        global $wpdb;
        $year = date('Y', strtotime($date));
        $table = OraBooks_Database::table('credit_notes');
        $last = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(credit_note_number, '-', -1) AS UNSIGNED))
             FROM {$table} WHERE org_id = %d AND credit_note_number LIKE %s",
            (int) $org_id,
            'CN-' . $year . '-%'
        ));
        return sprintf('CN-%s-%06d', $year, $last + 1);
    }

    private function require_ar_access($user_id, $org_id, $permission = 'create_invoice') {
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }
        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }
        if (!current_user_can('manage_options') && !OraBooks_RBAC::require_permission($user_id, $org_id, $permission)) {
            orabooks_json_error('Permission denied', 403);
        }
    }

    public function ajax_invoice_submit() {
        $user_id = orabooks_get_current_user_id();
        $org_id = (int) ($_POST['org_id'] ?? 0);
        $invoice_id = (int) ($_POST['invoice_id'] ?? 0);
        $this->require_ar_access($user_id, $org_id);
        $result = self::submit_invoice($org_id, $invoice_id, $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success(['invoice' => OraBooks_Customers::format_invoice($result)]);
    }

    public function ajax_invoice_approve() {
        $user_id = orabooks_get_current_user_id();
        $org_id = (int) ($_POST['org_id'] ?? 0);
        $invoice_id = (int) ($_POST['invoice_id'] ?? 0);
        $this->require_ar_access($user_id, $org_id, 'approve_journal');
        $result = self::approve_invoice($org_id, $invoice_id, $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success(['invoice' => OraBooks_Customers::format_invoice(
            is_object($result) ? $result : OraBooks_Customers::get_invoice($invoice_id)
        )]);
    }

    public function ajax_record_customer_payment() {
        $user_id = orabooks_get_current_user_id();
        $org_id = (int) ($_POST['org_id'] ?? 0);
        $customer_id = (int) ($_POST['customer_id'] ?? 0);
        $this->require_ar_access($user_id, $org_id);
        $result = self::record_customer_payment($org_id, $customer_id, array_merge($_POST, ['user_id' => $user_id]));
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success($result);
    }

    public function ajax_reverse_payment() {
        $user_id = orabooks_get_current_user_id();
        $org_id = (int) ($_POST['org_id'] ?? 0);
        $payment_id = (int) ($_POST['payment_id'] ?? 0);
        $this->require_ar_access($user_id, $org_id, 'manage_org_settings');
        $result = self::reverse_payment($org_id, $payment_id, $user_id, sanitize_text_field($_POST['reason'] ?? ''));
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success($result);
    }

    public function ajax_create_credit_note() {
        $user_id = orabooks_get_current_user_id();
        $org_id = (int) ($_POST['org_id'] ?? 0);
        $this->require_ar_access($user_id, $org_id);
        $result = self::create_credit_note($org_id, $_POST);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success(['credit_note' => $result]);
    }

    public function ajax_post_credit_note() {
        $user_id = orabooks_get_current_user_id();
        $org_id = (int) ($_POST['org_id'] ?? 0);
        $credit_note_id = (int) ($_POST['credit_note_id'] ?? 0);
        $this->require_ar_access($user_id, $org_id, 'approve_journal');
        $result = self::post_credit_note($org_id, $credit_note_id, $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success(['credit_note' => $result]);
    }

    public function ajax_void_credit_note() {
        $user_id = orabooks_get_current_user_id();
        $org_id = (int) ($_POST['org_id'] ?? 0);
        $credit_note_id = (int) ($_POST['credit_note_id'] ?? 0);
        $this->require_ar_access($user_id, $org_id);
        $result = self::void_credit_note($org_id, $credit_note_id, $user_id, sanitize_text_field($_POST['reason'] ?? ''));
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success(['credit_note' => $result]);
    }

    public function ajax_credit_notes_list() {
        $user_id = orabooks_get_current_user_id();
        $org_id = (int) ($_GET['org_id'] ?? $_POST['org_id'] ?? 0);
        $customer_id = (int) ($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);
        $this->require_ar_access($user_id, $org_id, 'view_invoices');
        orabooks_json_success(['credit_notes' => self::list_credit_notes($org_id, $customer_id)]);
    }

    public function ajax_ar_config_get() {
        $user_id = orabooks_get_current_user_id();
        $org_id = (int) ($_GET['org_id'] ?? 0);
        $this->require_ar_access($user_id, $org_id, 'view_invoices');
        $config = self::get_ar_config($org_id);
        orabooks_json_success([
            'config' => [
                'auto_post_on_approve' => (int) ($config->auto_post_on_approve ?? 1),
                'auto_apply_customer_credit' => (int) ($config->auto_apply_customer_credit ?? 1),
            ],
        ]);
    }

    public function ajax_ar_config_save() {
        $user_id = orabooks_get_current_user_id();
        $org_id = (int) ($_POST['org_id'] ?? 0);
        $this->require_ar_access($user_id, $org_id, 'manage_org_settings');
        orabooks_json_success(['config' => self::save_ar_config($org_id, $_POST)]);
    }

    public function ajax_ar_aging() {
        $user_id = orabooks_get_current_user_id();
        $org_id = (int) ($_GET['org_id'] ?? 0);
        $this->require_ar_access($user_id, $org_id, 'view_invoices');
        orabooks_json_success(['aging' => self::get_ar_aging($org_id)]);
    }

    public function ajax_statements_list() {
        $user_id = orabooks_get_current_user_id();
        $org_id = (int) ($_GET['org_id'] ?? 0);
        $customer_id = (int) ($_GET['customer_id'] ?? 0);
        $this->require_ar_access($user_id, $org_id, 'view_invoices');
        global $wpdb;
        $table = OraBooks_Database::table('customer_statement_snapshots');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d AND customer_id = %d ORDER BY statement_month DESC LIMIT 24",
            $org_id,
            $customer_id
        ));
        orabooks_json_success(['statements' => $rows ?: []]);
    }
}
