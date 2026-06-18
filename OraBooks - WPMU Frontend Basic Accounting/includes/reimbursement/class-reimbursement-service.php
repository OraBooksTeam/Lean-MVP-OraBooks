<?php
/**
 * Reimbursement Service Layer
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class OBN_Reimbursement_Service {
    private $repository;

    public function __construct($repository) {
        $this->repository = $repository;
    }

    public function create_reimbursement($data, $items, $attachments = []) {
        // Validation logic
        if (empty($data['employee_id']) || empty($items)) {
            throw new Exception('Employee ID and items are required.');
        }

        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            if (!empty($data['id'])) {
                $existing = $this->repository->find_by_id(intval($data['id']));
                if ($existing) {
                    $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(get_current_blog_id(), $existing->date);
                    if (is_wp_error($modification_allowed)) {
                        throw new Exception($modification_allowed->get_error_message());
                    }
                }
            }
            $this->assert_postable_date($data['date']);
            foreach ($items as $item) {
                if (!empty($item['date'])) {
                    $this->assert_postable_date($item['date']);
                }
            }
        }

        $data['total_amount'] = 0;
        foreach ($items as $item) {
            $data['total_amount'] += $item['amount'];
        }

        // Generate reimbursement number
        $data['reimbursement_no'] = 'REIMB-' . time();
        $data['status'] = 'Draft';

        return $this->repository->save($data, $items, $attachments);
    }

    public function submit($id) {
        $this->assert_reimbursement_modifiable($id);
        $success = $this->repository->update_status($id, 'Submitted', get_current_user_id(), 'Reimbursement submitted for approval.');
        if (!$success) throw new Exception('Failed to submit reimbursement.');
        return $success;
    }

    public function approve($id, $expense_account_id, $payable_account_id) {
        $reimbursement = $this->repository->find_by_id($id);
        if (!$reimbursement) throw new Exception('Reimbursement not found.');
        $this->assert_reimbursement_modifiable($id, $reimbursement);
        $this->assert_postable_date(current_time('Y-m-d'));

        if ($reimbursement->status !== 'Submitted') {
            throw new Exception('Only submitted reimbursements can be approved.');
        }

        // 1. Update Status
        $success = $this->repository->update_status($id, 'Approved', get_current_user_id(), 'Reimbursement approved.');

        if ($success) {
            // 2. Auto-generate Journal Entry
            $this->generate_approval_journal_entry($reimbursement, $expense_account_id, $payable_account_id);
        } else {
            throw new Exception('Failed to approve reimbursement (DB Error).');
        }

        return $success;
    }

    public function process_payment($id, $payment_account_id, $payable_account_id) {
        $reimbursement = $this->repository->find_by_id($id);
        if (!$reimbursement) throw new Exception('Reimbursement not found.');
        $this->assert_reimbursement_modifiable($id, $reimbursement);
        $this->assert_postable_date(current_time('Y-m-d'));

        if ($reimbursement->status !== 'Approved') {
            throw new Exception('Only approved reimbursements can be paid.');
        }

        // 1. Update Status
        $success = $this->repository->update_status($id, 'Paid', get_current_user_id(), 'Reimbursement paid.');

        if ($success) {
            // 2. Auto-generate Journal Entry
            $this->generate_payment_journal_entry($reimbursement, $payment_account_id, $payable_account_id);
        } else {
            throw new Exception('Failed to mark as paid.');
        }

        return $success;
    }

    public function reject($id, $note) {
        $this->assert_reimbursement_modifiable($id);
        return $this->repository->update_status($id, 'Draft', get_current_user_id(), $note);
    }

    public function assert_reimbursement_modifiable($id, $reimbursement = null) {
        $reimbursement = $reimbursement ?: $this->repository->find_by_id($id);
        if (!$reimbursement) {
            throw new Exception('Reimbursement not found.');
        }

        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(get_current_blog_id(), $reimbursement->date);
            if (is_wp_error($modification_allowed)) {
                throw new Exception($modification_allowed->get_error_message());
            }
        }
    }

    private function assert_postable_date($entry_date) {
        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            $posting_allowed = OBN_Fiscal_Period_Posting_Guard::can_post(get_current_blog_id(), $entry_date);
            if (is_wp_error($posting_allowed)) {
                throw new Exception($posting_allowed->get_error_message());
            }
        }
    }

    private function generate_approval_journal_entry($reimbursement, $expense_acc, $payable_acc) {
        global $wpdb;
        $je_table = $wpdb->prefix . 'orabooks_ac_journal_entry';
        $je_line_table = $wpdb->prefix . 'orabooks_ac_journal_line';
        $entry_date = current_time('Y-m-d');

        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            $posting_allowed = OBN_Fiscal_Period_Posting_Guard::can_post(get_current_blog_id(), $entry_date);
            if (is_wp_error($posting_allowed)) {
                throw new Exception($posting_allowed->get_error_message());
            }
        }

        $wpdb->insert($je_table, [
            'store_id' => get_current_blog_id(),
            'organization_id' => get_current_blog_id(),
            'entry_date' => $entry_date,
            'posting_date' => $entry_date,
            'reference_no' => $reimbursement->reimbursement_no,
            'description' => 'Reimbursement Approval: ' . $reimbursement->description,
            'total_debit' => $reimbursement->total_amount,
            'total_credit' => $reimbursement->total_amount,
            'status' => 'Posted',
            'created_by' => get_current_user_id()
        ]);

        $je_id = $wpdb->insert_id;

        // Debit Expense
        $wpdb->insert($je_line_table, [
            'journal_entry_id' => $je_id,
            'account_id' => $expense_acc,
            'debit' => $reimbursement->total_amount,
            'credit' => 0,
            'debit_amt' => $reimbursement->total_amount,
            'credit_amt' => 0,
            'description' => 'Expense for Reimbursement ' . $reimbursement->reimbursement_no,
            'line_number' => 1
        ]);

        // Credit Payable
        $wpdb->insert($je_line_table, [
            'journal_entry_id' => $je_id,
            'account_id' => $payable_acc,
            'debit' => 0,
            'credit' => $reimbursement->total_amount,
            'debit_amt' => 0,
            'credit_amt' => $reimbursement->total_amount,
            'description' => 'Payable for Reimbursement ' . $reimbursement->reimbursement_no,
            'line_number' => 2
        ]);
    }

    private function generate_payment_journal_entry($reimbursement, $payment_acc, $payable_acc) {
        global $wpdb;
        $je_table = $wpdb->prefix . 'orabooks_ac_journal_entry';
        $je_line_table = $wpdb->prefix . 'orabooks_ac_journal_line';
        $entry_date = current_time('Y-m-d');

        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            $posting_allowed = OBN_Fiscal_Period_Posting_Guard::can_post(get_current_blog_id(), $entry_date);
            if (is_wp_error($posting_allowed)) {
                throw new Exception($posting_allowed->get_error_message());
            }
        }

        $wpdb->insert($je_table, [
            'store_id' => get_current_blog_id(),
            'organization_id' => get_current_blog_id(),
            'entry_date' => $entry_date,
            'posting_date' => $entry_date,
            'reference_no' => 'PAY-' . $reimbursement->reimbursement_no,
            'description' => 'Reimbursement Payment: ' . $reimbursement->reimbursement_no,
            'total_debit' => $reimbursement->total_amount,
            'total_credit' => $reimbursement->total_amount,
            'status' => 'Posted',
            'created_by' => get_current_user_id()
        ]);

        $je_id = $wpdb->insert_id;

        // Debit Payable
        $wpdb->insert($je_line_table, [
            'journal_entry_id' => $je_id,
            'account_id' => $payable_acc,
            'debit' => $reimbursement->total_amount,
            'credit' => 0,
            'debit_amt' => $reimbursement->total_amount,
            'credit_amt' => 0,
            'description' => 'Settling Payable for Reimbursement ' . $reimbursement->reimbursement_no,
            'line_number' => 1
        ]);

        // Credit Cash / Bank
        $wpdb->insert($je_line_table, [
            'journal_entry_id' => $je_id,
            'account_id' => $payment_acc,
            'debit' => 0,
            'credit' => $reimbursement->total_amount,
            'debit_amt' => 0,
            'credit_amt' => $reimbursement->total_amount,
            'description' => 'Payment for Reimbursement ' . $reimbursement->reimbursement_no,
            'line_number' => 2
        ]);
    }
}
