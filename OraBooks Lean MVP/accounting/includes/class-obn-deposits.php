<?php
/**
 * Frontend-Accounting Deposits handlers
 */

if (!defined('ABSPATH'))
    exit;

class OBN_Deposits
{
    public function __construct()
    {
        add_action('wp_ajax_obn_insert_deposit', array($this, 'insert_deposit'));
        add_action('wp_ajax_nopriv_obn_insert_deposit', array($this, 'insert_deposit'));

        add_action('wp_ajax_obn_update_deposit', array($this, 'update_deposit'));
        add_action('wp_ajax_nopriv_obn_update_deposit', array($this, 'update_deposit'));

        add_action('wp_ajax_obn_delete_deposit', array($this, 'delete_deposit'));
        add_action('wp_ajax_nopriv_obn_delete_deposit', array($this, 'delete_deposit'));

        add_action('wp_ajax_obn_toggle_deposit_status', array($this, 'toggle_status'));
        add_action('wp_ajax_nopriv_obn_toggle_deposit_status', array($this, 'toggle_status'));

        add_action('wp_ajax_obn_get_deposit', array($this, 'get_deposit'));
        add_action('wp_ajax_nopriv_obn_get_deposit', array($this, 'get_deposit'));
    }

    public static function insert_deposit()
    {
        check_ajax_referer('obn_deposit_action_nonce', 'security');
        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting())
            wp_send_json_error('Access denied.');

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_moneydeposits';

        $deposit_date = sanitize_text_field($_POST['deposit_date'] ?? '');
        $reference_no = sanitize_text_field($_POST['reference_no'] ?? '');
        $debit_account = intval($_POST['debit_ac'] ?? 0);
        $credit_account = intval($_POST['credit_ac'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $note = $_POST['note'] ?? '';

        if (empty($deposit_date) || !$debit_account || !$credit_account || $amount <= 0) {
            wp_send_json_error('All required fields must be provided.');
        }
        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            OBN_Fiscal_Period_Posting_Guard::assert_can_post_or_fail($deposit_date);
        }

        $inserted = $wpdb->insert($table, array(
            'deposit_date' => $deposit_date,
            'reference_no' => $reference_no,
            'debit_account_id' => $debit_account,
            'credit_account_id' => $credit_account,
            'amount' => $amount,
            'note' => $note,
            'status' => 1,
            'created_by' => get_current_user_id(),
            'created_date' => date('Y-m-d'),
            'created_time' => current_time('mysql'),
        ), array('%s', '%s', '%d', '%d', '%f', '%s', '%d', '%s', '%s', '%s'));

        if ($inserted) {
            $inserted_id = $wpdb->insert_id;

            // Update Account Balances
            $acc_table = $wpdb->prefix . 'orabooks_ac_accounts';
            // Debit (Receiver) increases
            $wpdb->query($wpdb->prepare("UPDATE $acc_table SET balance = balance + %f WHERE id = %d", $amount, $debit_account));
            // Credit (Sender) decreases
            $wpdb->query($wpdb->prepare("UPDATE $acc_table SET balance = balance - %f WHERE id = %d", $amount, $credit_account));

            // Insert into Transactions table
            $wpdb->insert($wpdb->prefix . 'orabooks_ac_transactions', array(
                'store_id' => obn_current_org_id(),
                'payment_code' => $reference_no,
                'transaction_date' => $deposit_date,
                'transaction_type' => 'Money Deposit',
                'debit_account_id' => $debit_account,
                'credit_account_id' => $credit_account,
                'debit_amt' => $amount,
                'credit_amt' => $amount,
                'note' => $note,
                'created_by' => get_current_user_id(),
                'created_date' => date('Y-m-d'),
                'ref_moneydeposits_id' => $inserted_id,
                'short_code' => $reference_no,
            ));

            wp_send_json_success(array('message' => 'Deposit added successfully.'));
        }
        wp_send_json_error('Failed to add deposit.');
    }

    public static function update_deposit()
    {
        check_ajax_referer('obn_deposit_action_nonce', 'security');
        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting())
            wp_send_json_error('Access denied.');

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_moneydeposits';

        $id = intval($_POST['id'] ?? 0);
        $deposit_date = sanitize_text_field($_POST['deposit_date'] ?? '');
        $reference_no = sanitize_text_field($_POST['reference_no'] ?? '');
        $debit_account = intval($_POST['debit_ac'] ?? 0);
        $credit_account = intval($_POST['credit_ac'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $note = sanitize_text_field($_POST['note'] ?? '');

        if ($id <= 0 || empty($deposit_date) || !$debit_account || !$credit_account || $amount <= 0) {
            wp_send_json_error('All required fields must be provided.');
        }

        // Get old data for balance reversal
        $old_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if (!$old_data)
            wp_send_json_error('Deposit not found.');
        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(obn_current_org_id(), $old_data->deposit_date);
            if (is_wp_error($modification_allowed)) {
                wp_send_json_error($modification_allowed->get_error_message(), 409);
            }
            OBN_Fiscal_Period_Posting_Guard::assert_can_post_or_fail($deposit_date);
        }

        // Revert old balances
        $acc_table = $wpdb->prefix . 'orabooks_ac_accounts';
        $wpdb->query($wpdb->prepare("UPDATE $acc_table SET balance = balance - %f WHERE id = %d", $old_data->amount, $old_data->debit_account_id));
        $wpdb->query($wpdb->prepare("UPDATE $acc_table SET balance = balance + %f WHERE id = %d", $old_data->amount, $old_data->credit_account_id));

        $updated = $wpdb->update($table, array(
            'deposit_date' => $deposit_date,
            'reference_no' => $reference_no,
            'debit_account_id' => $debit_account,
            'credit_account_id' => $credit_account,
            'amount' => $amount,
            'note' => $note,
            'created_by' => get_current_user_id(),
            'created_date' => date('Y-m-d'),
            'created_time' => current_time('mysql'),
        ), array('id' => $id), array('%s', '%s', '%d', '%d', '%f', '%s', '%d', '%s', '%s', '%s'), array('%d'));

        if ($updated !== false) {
            // Apply new balances
            $wpdb->query($wpdb->prepare("UPDATE $acc_table SET balance = balance + %f WHERE id = %d", $amount, $debit_account));
            $wpdb->query($wpdb->prepare("UPDATE $acc_table SET balance = balance - %f WHERE id = %d", $amount, $credit_account));

            // Update Transaction record
            $wpdb->update($wpdb->prefix . 'orabooks_ac_transactions', array(
                'transaction_date' => $deposit_date,
                'debit_account_id' => $debit_account,
                'credit_account_id' => $credit_account,
                'debit_amt' => $amount,
                'credit_amt' => $amount,
                'note' => $note,
            ), array('ref_moneydeposits_id' => $id));

            wp_send_json_success(array('message' => 'Deposit updated successfully.'));
        }
        wp_send_json_error('Failed to update deposit.');
    }

    public static function delete_deposit()
    {
        check_ajax_referer('obn_deposit_action_nonce', 'security');
        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting())
            wp_send_json_error('Access denied.');

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_moneydeposits';
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0)
            wp_send_json_error('Invalid ID');

        // Get data for balance reversal
        $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if (!$data)
            wp_send_json_error('Deposit not found.');
        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(obn_current_org_id(), $data->deposit_date);
            if (is_wp_error($modification_allowed)) {
                wp_send_json_error($modification_allowed->get_error_message(), 409);
            }
        }

        $deleted = $wpdb->delete($table, array('id' => $id));
        if ($deleted) {
            // Revert balances
            $acc_table = $wpdb->prefix . 'orabooks_ac_accounts';
            $wpdb->query($wpdb->prepare("UPDATE $acc_table SET balance = balance - %f WHERE id = %d", $data->amount, $data->debit_account_id));
            $wpdb->query($wpdb->prepare("UPDATE $acc_table SET balance = balance + %f WHERE id = %d", $data->amount, $data->credit_account_id));

            // Delete transaction record
            $wpdb->delete($wpdb->prefix . 'orabooks_ac_transactions', array('ref_moneydeposits_id' => $id));

            wp_send_json_success(array('message' => 'Deposit deleted successfully.'));
        }
        wp_send_json_error('Failed to delete deposit.');
    }

    public static function toggle_status()
    {
        check_ajax_referer('obn_deposit_action_nonce', 'security');
        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting())
            wp_send_json_error('Access denied.');

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_moneydeposits';
        $id = intval($_POST['id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);
        if ($id <= 0)
            wp_send_json_error('Invalid ID');
        $data = $wpdb->get_row($wpdb->prepare("SELECT deposit_date FROM {$table} WHERE id = %d", $id));
        if (!$data) {
            wp_send_json_error('Deposit not found.');
        }
        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(obn_current_org_id(), $data->deposit_date);
            if (is_wp_error($modification_allowed)) {
                wp_send_json_error($modification_allowed->get_error_message(), 409);
            }
        }

        $updated = $wpdb->update($table, array('status' => $status), array('id' => $id));
        if ($updated !== false)
            wp_send_json_success(array('new_status' => $status));
        wp_send_json_error('Failed to toggle status.');
    }

    public static function get_deposit()
    {
        check_ajax_referer('obn_deposit_action_nonce', 'security');
        $auth = new OBN_Auth();
        if (!is_user_logged_in() || !$auth->can_access_accounting())
            wp_send_json_error('Access denied.');

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_moneydeposits';
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0)
            wp_send_json_error('Invalid ID');

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if ($row)
            wp_send_json_success($row);
        wp_send_json_error('Not found');
    }

}

new OBN_Deposits();
