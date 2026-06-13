<?php
/**
 * Expense AJAX Handler Class
 * 
 * Manages CRUD operations for expenses in Frontend-Accounting plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class OBN_Expenses
{
    public static function init()
    {
        add_action('wp_ajax_obn_insert_expense', [self::class, 'insert_expense']);
        add_action('wp_ajax_nopriv_obn_insert_expense', [self::class, 'insert_expense']);

        add_action('wp_ajax_obn_update_expense', [self::class, 'update_expense']);
        add_action('wp_ajax_nopriv_obn_update_expense', [self::class, 'update_expense']);

        add_action('wp_ajax_obn_delete_expense', [self::class, 'delete_expense']);
        add_action('wp_ajax_nopriv_obn_delete_expense', [self::class, 'delete_expense']);

        add_action('wp_ajax_obn_toggle_expense_status', [self::class, 'toggle_expense_status']);
        add_action('wp_ajax_nopriv_obn_toggle_expense_status', [self::class, 'toggle_expense_status']);

        add_action('wp_ajax_obn_get_expense', [self::class, 'get_expense']);
        add_action('wp_ajax_nopriv_obn_get_expense', [self::class, 'get_expense']);
    }

    public static function insert_expense()
    {
        check_ajax_referer('obn_expense_action_nonce', 'security');

        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting()) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;

        $expense_date = sanitize_text_field($_POST['expense_date'] ?? current_time('Y-m-d'));
        $reference_no = sanitize_text_field($_POST['reference_no'] ?? '');
        $supplier_id = intval($_POST['supplier_id'] ?? 0);
        $payment_type = sanitize_text_field($_POST['payment_type'] ?? '');
        $account_id = intval($_POST['bank_account_id'] ?? 0);
        $billing_address = sanitize_textarea_field($_POST['billing_address'] ?? '');
        $total_amount = floatval($_POST['total_amount'] ?? 0);
        $paid_amount = floatval($_POST['paid_amount'] ?? 0);
        $due_amount = $total_amount - $paid_amount;
        $payment_status = sanitize_text_field($_POST['payment_status'] ?? 'Paid');
        $comments = sanitize_textarea_field($_POST['comments'] ?? '');
        $expense_items = json_decode(stripslashes($_POST['expense_items'] ?? '[]'), true);

        if (!$expense_date || !$payment_type || $total_amount <= 0 || empty($expense_items)) {
            wp_send_json_error('All required fields must be filled');
        }

        // Validate expense items
        foreach ($expense_items as $item) {
            if (empty($item['account_id']) || empty($item['description']) || floatval($item['amount']) <= 0) {
                wp_send_json_error('All expense items must have account, description, and amount');
            }
        }

        $expense_table = $wpdb->prefix . 'orabooks_db_expense';
        $expense_items_table = $wpdb->prefix . 'orabooks_db_expense_items';

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Insert main expense record
            $insert_data = [
                'expense_date' => $expense_date,
                'reference_no' => $reference_no,
                'supplier_id' => $supplier_id,
                'payment_type' => $payment_type,
                'account_id' => $account_id,
                'billing_address' => $billing_address,
                'total_amount' => $total_amount,
                'paid_amount' => $paid_amount,
                'due_amount' => $due_amount,
                'payment_status' => $payment_status,
                'comments' => $comments,
                'created_by' => get_current_user_id(),
                'created_date' => current_time('Y-m-d'),
                'created_time' => current_time('H:i:s'),
                'system_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'system_name' => gethostname(),
                'status' => 1,
            ];

            $result = $wpdb->insert($expense_table, $insert_data);

            if ($result === false) {
                throw new Exception('Failed to insert expense: ' . $wpdb->last_error);
            }

            $expense_id = $wpdb->insert_id;

            // Insert expense items
            foreach ($expense_items as $item) {
                $item_data = [
                    'expense_id' => $expense_id,
                    'account_id' => intval($item['account_id']),
                    'description' => sanitize_text_field($item['description']),
                    'amount' => floatval($item['amount']),
                    'created_by' => get_current_user_id(),
                    'created_date' => current_time('Y-m-d'),
                    'created_time' => current_time('H:i:s'),
                    'system_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'system_name' => gethostname(),
                    'status' => 1,
                ];

                $item_result = $wpdb->insert($expense_items_table, $item_data);

                if ($item_result === false) {
                    throw new Exception('Failed to insert expense item: ' . $wpdb->last_error);
                }
            }

            // Sync Journal Entry
            self::sync_journal_entry($expense_id);

            // Commit transaction
            $wpdb->query('COMMIT');

            wp_send_json_success(['message' => 'Expense added successfully', 'expense_id' => $expense_id]);

        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            wp_send_json_error($e->getMessage());
        }
    }

    public static function update_expense()
    {
        check_ajax_referer('obn_expense_action_nonce', 'security');

        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting()) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;

        $expense_id = intval($_POST['id'] ?? 0);
        if (!$expense_id)
            wp_send_json_error('Invalid expense ID');

        $expense_date = sanitize_text_field($_POST['expense_date'] ?? '');
        $reference_no = sanitize_text_field($_POST['reference_no'] ?? '');
        $supplier_id = intval($_POST['supplier_id'] ?? 0);
        $payment_type = sanitize_text_field($_POST['payment_type'] ?? '');
        $account_id = intval($_POST['bank_account_id'] ?? 0);
        $billing_address = sanitize_textarea_field($_POST['billing_address'] ?? '');
        $total_amount = floatval($_POST['total_amount'] ?? 0);
        $paid_amount = floatval($_POST['paid_amount'] ?? 0);
        $due_amount = $total_amount - $paid_amount;
        $payment_status = sanitize_text_field($_POST['payment_status'] ?? 'Paid');
        $comments = sanitize_textarea_field($_POST['comments'] ?? '');
        $expense_items = json_decode(stripslashes($_POST['expense_items'] ?? '[]'), true);

        if (!$expense_date || !$payment_type || $total_amount <= 0 || empty($expense_items)) {
            wp_send_json_error('All required fields must be filled');
        }

        // Validate expense items
        foreach ($expense_items as $item) {
            if (empty($item['account_id']) || empty($item['description']) || floatval($item['amount']) <= 0) {
                wp_send_json_error('All expense items must have account, description, and amount');
            }
        }

        $expense_table = $wpdb->prefix . 'orabooks_db_expense';
        $expense_items_table = $wpdb->prefix . 'orabooks_db_expense_items';

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Update main expense record
            $update_data = [
                'expense_date' => $expense_date,
                'reference_no' => $reference_no,
                'supplier_id' => $supplier_id,
                'payment_type' => $payment_type,
                'account_id' => $account_id,
                'billing_address' => $billing_address,
                'total_amount' => $total_amount,
                'paid_amount' => $paid_amount,
                'due_amount' => $due_amount,
                'payment_status' => $payment_status,
                'comments' => $comments,
            ];

            $updated = $wpdb->update($expense_table, $update_data, ['id' => $expense_id]);

            if ($updated === false) {
                throw new Exception('Failed to update expense: ' . $wpdb->last_error);
            }

            // Delete existing expense items
            $deleted_items = $wpdb->delete($expense_items_table, ['expense_id' => $expense_id]);

            if ($deleted_items === false) {
                throw new Exception('Failed to delete existing expense items: ' . $wpdb->last_error);
            }

            // Insert new expense items
            foreach ($expense_items as $item) {
                $item_data = [
                    'expense_id' => $expense_id,
                    'account_id' => intval($item['account_id']),
                    'description' => sanitize_text_field($item['description']),
                    'amount' => floatval($item['amount']),
                    'created_by' => get_current_user_id(),
                    'created_date' => current_time('Y-m-d'),
                    'created_time' => current_time('H:i:s'),
                    'system_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'system_name' => gethostname(),
                    'status' => 1,
                ];

                $item_result = $wpdb->insert($expense_items_table, $item_data);

                if ($item_result === false) {
                    throw new Exception('Failed to insert expense item: ' . $wpdb->last_error);
                }
            }

            // Sync Journal Entry
            self::sync_journal_entry($expense_id);

            // Commit transaction
            $wpdb->query('COMMIT');

            wp_send_json_success(['message' => 'Expense updated successfully']);

        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            wp_send_json_error($e->getMessage());
        }
    }

    public static function delete_expense()
    {
        check_ajax_referer('obn_expense_action_nonce', 'security');

        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting()) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;

        $expense_id = intval($_POST['id'] ?? 0);
        if (!$expense_id)
            wp_send_json_error('Invalid expense ID');

        $expense_table = $wpdb->prefix . 'orabooks_db_expense';
        $expense_items_table = $wpdb->prefix . 'orabooks_db_expense_items';

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Delete expense items first
            $deleted_items = $wpdb->delete($expense_items_table, ['expense_id' => $expense_id]);

            if ($deleted_items === false) {
                throw new Exception('Failed to delete expense items: ' . $wpdb->last_error);
            }

            // Delete main expense record
            $deleted = $wpdb->delete($expense_table, ['id' => $expense_id]);

            if ($deleted === false) {
                throw new Exception('Failed to delete expense: ' . $wpdb->last_error);
            }

            // Delete associated journal entry
            $je_table = $wpdb->prefix . 'orabooks_ac_journal_entry';
            $je_line_table = $wpdb->prefix . 'orabooks_ac_journal_line';
            
            $je_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $je_table WHERE source_type = 'Expense' AND source_id = %d", $expense_id));
            if ($je_id) {
                $wpdb->delete($je_line_table, ['journal_entry_id' => $je_id]);
                $wpdb->delete($je_table, ['id' => $je_id]);
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            wp_send_json_success('Expense deleted successfully');

        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            wp_send_json_error($e->getMessage());
        }
    }

    public static function toggle_expense_status()
    {
        check_ajax_referer('obn_expense_action_nonce', 'security');

        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting()) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;

        $expense_id = intval($_POST['id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);

        if (!$expense_id)
            wp_send_json_error('Invalid expense ID');

        $expense_table = $wpdb->prefix . 'orabooks_db_expense';
        $updated = $wpdb->update($expense_table, ['status' => $status], ['id' => $expense_id]);

        if ($updated === false) {
            wp_send_json_error('Failed to update status: ' . $wpdb->last_error);
        }

        wp_send_json_success('Status updated successfully');
    }

    public static function get_expense()
    {
        check_ajax_referer('obn_expense_action_nonce', 'security');

        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting()) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;

        $expense_id = intval($_POST['id'] ?? 0);
        if (!$expense_id)
            wp_send_json_error('Invalid expense ID');

        $expense_table = $wpdb->prefix . 'orabooks_db_expense';
        $expense_items_table = $wpdb->prefix . 'orabooks_db_expense_items';

        $account_table = $wpdb->prefix . 'orabooks_ac_accounts';
        // Get main expense record with bank account name
        $expense = $wpdb->get_row($wpdb->prepare("SELECT e.*, a.account_name as bank_account_name FROM $expense_table e LEFT JOIN $account_table a ON e.account_id = a.id WHERE e.id = %d", $expense_id));

        if (!$expense) {
            wp_send_json_error('Expense not found');
        }

        // Get expense items
        $expense_items = $wpdb->get_results($wpdb->prepare("
            SELECT ei.*, coa.account_name, coa.account_code 
            FROM $expense_items_table ei
            LEFT JOIN {$wpdb->prefix}orabooks_ac_coa_list coa ON ei.account_id = coa.id
            WHERE ei.expense_id = %d AND ei.status = 1
            ORDER BY ei.id ASC
        ", $expense_id));

        // Combine expense and items data
        $expense_data = [
            'expense' => $expense,
            'items' => $expense_items
        ];

        wp_send_json_success($expense_data);
    }

    /**
     * Create or Refresh a balanced Journal Entry for the expense
     */
    private static function sync_journal_entry($expense_id)
    {
        global $wpdb;

        // Fetch expense data
        $expense_table = $wpdb->prefix . 'orabooks_db_expense';
        $expense_items_table = $wpdb->prefix . 'orabooks_db_expense_items';
        $je_table = $wpdb->prefix . 'orabooks_ac_journal_entry';
        $je_line_table = $wpdb->prefix . 'orabooks_ac_journal_line';

        $expense = $wpdb->get_row($wpdb->prepare("SELECT * FROM $expense_table WHERE id = %d", $expense_id));
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $expense_items_table WHERE expense_id = %d", $expense_id));

        if (!$expense || !$items) return;

        // Delete existing JE if any
        $old_je_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $je_table WHERE source_type = 'Expense' AND source_id = %d", $expense_id));
        if ($old_je_id) {
            $wpdb->delete($je_line_table, ['journal_entry_id' => $old_je_id]);
            $wpdb->delete($je_table, ['id' => $old_je_id]);
        }

        // Prepare Journal Lines
        $lines = [];

        // 1. Debit lines (Expense Accounts)
        foreach ($items as $item) {
            $lines[] = [
                'account_id' => $item->account_id,
                'description' => $item->description,
                'debit' => $item->amount,
                'credit' => 0
            ];
        }

        // 2. Credit lines (Payment / Liability)
        
        // Paid part
        if ($expense->paid_amount > 0) {
            
            // Step 1: Get payment type record
            $payment_type_record = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * 
                     FROM {$wpdb->prefix}orabooks_db_paymenttypes 
                     WHERE payment_type = %s 
                     LIMIT 1",
                    $expense->payment_type
                )
            );

            // Default account search keyword
            $account_keyword = 'Cash';

            // Step 2: Determine account keyword
            if ($payment_type_record) {
                
                // $payment_type = strtolower(trim($payment_type_record->payment_type));

                if (in_array($payment_type_record->payment_type, ['Bank', 'Check'])) {
                    $account_keyword = 'Bank';
                }
                else {
                    $account_keyword = 'Cash';
                }
            }

            // Step 3: Get COA account ID dynamically
            $payment_account_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id
                     FROM {$wpdb->prefix}orabooks_ac_coa_list
                     WHERE account_name = %s
                     LIMIT 1",
                    $account_keyword
                )
            );

            // Step 4: Create credit line only if valid account found
            if (!empty($payment_account_id)) {
                
                $lines[] = [
                    'account_id' => $payment_account_id,
                    'description' => $account_keyword . ' payment credit',
                    'debit' => 0,
                    'credit' => $expense->paid_amount
                ];
            }
        }

        // Due part
        if ($expense->due_amount > 0) {
            // Get Accounts Payable account ID (Code 2100)
            $ap_account_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE account_code = %s",
                '2100'
            ));

            if ($ap_account_id) {
                $lines[] = [
                    'account_id' => $ap_account_id,
                    'description' => 'Account payable credit',
                    'debit' => 0,
                    'credit' => $expense->due_amount
                ];
            }
        }

        // If no lines (e.g. amount is 0), skip
        if (empty($lines)) return;

        // Call the static helper from OBN_Journal_Entries
        if (class_exists('OBN_Journal_Entries')) {
            $je_result = OBN_Journal_Entries::add_entry([
                'entry_date' => $expense->expense_date,
                'reference_no' => $expense->reference_no,
                'description' => $expense->comments ?: 'Auto-generated from Expense Entry',
                'source_type' => 'Expense',
                'source_id' => $expense_id,
                'lines' => $lines
            ]);

            if (is_wp_error($je_result)) {
                throw new Exception($je_result->get_error_message());
            }
        }
    }
}

OBN_Expenses::init();
