<?php
class OBN_Money_Transfer {

	public function __construct() {
		add_action( 'wp_ajax_obn_insert_money_transfer', array( $this, 'insert_money_transfer' ) );
		add_action( 'wp_ajax_obn_get_money_transfer', array( $this, 'get_money_transfer' ) );
		add_action( 'wp_ajax_obn_update_money_transfer', array( $this, 'update_money_transfer' ) );
		add_action( 'wp_ajax_obn_delete_money_transfer', array( $this, 'delete_money_transfer' ) );
	}

    private function generate_transfer_code() {
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_ac_moneytransfer';
        $last = $wpdb->get_var("SELECT count_id FROM $table ORDER BY id DESC LIMIT 1");
        $next_id = intval($last) + 1;
        $code = 'MT-' . str_pad($next_id, 6, '0', STR_PAD_LEFT);
        return array('code' => $code, 'count_id' => $next_id);
    }

	public function insert_money_transfer() {
		check_ajax_referer( 'obn_money_transfer_nonce', 'security' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'Unauthorized' );
        }

		global $wpdb;
		$transfer_date = sanitize_text_field( $_POST['transfer_date'] );
		$debit_account_id = intval( $_POST['debit_account_id'] );
		$credit_account_id = intval( $_POST['credit_account_id'] );
		$amount = floatval( $_POST['amount'] );
		$reference_no = sanitize_text_field( $_POST['reference_no'] );
		$note = sanitize_textarea_field( $_POST['note'] );

        if ( empty($transfer_date) || $debit_account_id <= 0 || $credit_account_id <= 0 || $amount <= 0 ) {
            wp_send_json_error( 'Please fill all required fields.' );
        }

        if ( $debit_account_id === $credit_account_id ) {
            wp_send_json_error( 'Debit and Credit accounts cannot be the same.' );
        }

        $code_data = $this->generate_transfer_code();

		$data = array(
            'store_id' => get_current_blog_id(),
			'count_id' => $code_data['count_id'],
			'transfer_code' => $code_data['code'],
			'transfer_date' => $transfer_date,
			'reference_no' => $reference_no,
			'debit_account_id' => $debit_account_id,
			'credit_account_id' => $credit_account_id,
			'amount' => $amount,
			'note' => $note,
			'created_by' => get_current_user_id(),
			'created_date' => current_time( 'Y-m-d' ),
			'created_time' => current_time( 'H:i:s' ),
			'system_ip' => $_SERVER['REMOTE_ADDR'],
			'system_name' => gethostbyaddr($_SERVER['REMOTE_ADDR']),
			'status' => 1
		);

		$table = $wpdb->prefix . 'orabooks_ac_moneytransfer';
		$result = $wpdb->insert( $table, $data );

		if ( $result ) {
            $transfer_id = $wpdb->insert_id;
            // Update Account Balances
            $acc_table = $wpdb->prefix . 'orabooks_ac_accounts';
            // Debit (Receiver) increases
            $wpdb->query( $wpdb->prepare("UPDATE $acc_table SET balance = balance + %f WHERE id = %d", $amount, $debit_account_id) );
            // Credit (Sender) decreases
            $wpdb->query( $wpdb->prepare("UPDATE $acc_table SET balance = balance - %f WHERE id = %d", $amount, $credit_account_id) );

            // Insert into Transactions table
            $wpdb->insert( $wpdb->prefix . 'orabooks_ac_transactions', array(
                'store_id' => $data['store_id'],
                'payment_code' => $data['transfer_code'],
                'transaction_date' => $data['transfer_date'],
                'transaction_type' => 'Money Transfer',
                'debit_account_id' => $data['debit_account_id'],
                'credit_account_id' => $data['credit_account_id'],
                'debit_amt' => $data['amount'],
                'credit_amt' => $data['amount'],
                'note' => $data['note'],
                'created_by' => $data['created_by'],
                'created_date' => $data['created_date'],
                'ref_moneytransfer_id' => $transfer_id,
                'short_code' => $data['transfer_code'],
            ) );

			wp_send_json_success( array( 'message' => 'Money Transfer added successfully.' ) );
		} else {
			wp_send_json_error( 'Failed to insert data.' );
		}
	}

	public function get_money_transfer() {
		check_ajax_referer( 'obn_money_transfer_nonce', 'security' );
		if ( ! is_user_logged_in() ) wp_send_json_error( 'Unauthorized' );

		global $wpdb;
		$id = intval( $_POST['id'] );
		$table = $wpdb->prefix . 'orabooks_ac_moneytransfer';
		
		$transfer = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id) );
		
		if ( $transfer ) {
			wp_send_json_success( $transfer );
		} else {
			wp_send_json_error( 'Transfer not found.' );
		}
	}

	public function update_money_transfer() {
		check_ajax_referer( 'obn_money_transfer_nonce', 'security' );
		if ( ! is_user_logged_in() ) wp_send_json_error( 'Unauthorized' );

		global $wpdb;
		$id = intval( $_POST['id'] );
		$transfer_date = sanitize_text_field( $_POST['transfer_date'] );
		$debit_account_id = intval( $_POST['debit_account_id'] );
		$credit_account_id = intval( $_POST['credit_account_id'] );
		$amount = floatval( $_POST['amount'] );
		$reference_no = sanitize_text_field( $_POST['reference_no'] );
		$note = sanitize_textarea_field( $_POST['note'] );

		if ( empty($transfer_date) || $debit_account_id <= 0 || $credit_account_id <= 0 || $amount <= 0 ) {
			wp_send_json_error( 'Please fill all required fields.' );
		}

		if ( $debit_account_id === $credit_account_id ) {
			wp_send_json_error( 'Debit and Credit accounts cannot be the same.' );
		}

		$table = $wpdb->prefix . 'orabooks_ac_moneytransfer';
		
		// Get old transfer data to reverse balances
		$old_transfer = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id) );
		if ( ! $old_transfer ) {
			wp_send_json_error( 'Transfer not found.' );
		}

		// Reverse old balances
		$acc_table = $wpdb->prefix . 'orabooks_ac_accounts';
		$wpdb->query( $wpdb->prepare("UPDATE $acc_table SET balance = balance - %f WHERE id = %d", $old_transfer->amount, $old_transfer->debit_account_id) );
		$wpdb->query( $wpdb->prepare("UPDATE $acc_table SET balance = balance + %f WHERE id = %d", $old_transfer->amount, $old_transfer->credit_account_id) );

		// Update transfer
		$data = array(
			'transfer_date' => $transfer_date,
			'reference_no' => $reference_no,
			'debit_account_id' => $debit_account_id,
			'credit_account_id' => $credit_account_id,
			'amount' => $amount,
			'note' => $note
		);

		$result = $wpdb->update( $table, $data, array( 'id' => $id ) );

		if ( $result !== false ) {
			// Apply new balances
			$wpdb->query( $wpdb->prepare("UPDATE $acc_table SET balance = balance + %f WHERE id = %d", $amount, $debit_account_id) );
			$wpdb->query( $wpdb->prepare("UPDATE $acc_table SET balance = balance - %f WHERE id = %d", $amount, $credit_account_id) );

            // Update Transaction record
            $wpdb->update( $wpdb->prefix . 'orabooks_ac_transactions', array(
                'transaction_date' => $transfer_date,
                'debit_account_id' => $debit_account_id,
                'credit_account_id' => $credit_account_id,
                'debit_amt' => $amount,
                'credit_amt' => $amount,
                'note' => $note,
            ), array( 'ref_moneytransfer_id' => $id ) );

			wp_send_json_success( array( 'message' => 'Money Transfer updated successfully.' ) );
		} else {
			// Re-apply old balances if update failed
			$wpdb->query( $wpdb->prepare("UPDATE $acc_table SET balance = balance + %f WHERE id = %d", $old_transfer->amount, $old_transfer->debit_account_id) );
			$wpdb->query( $wpdb->prepare("UPDATE $acc_table SET balance = balance - %f WHERE id = %d", $old_transfer->amount, $old_transfer->credit_account_id) );
			
			wp_send_json_error( 'Failed to update transfer.' );
		}
	}

	public function delete_money_transfer() {
		check_ajax_referer( 'obn_money_transfer_nonce', 'security' );
        if ( ! is_user_logged_in() ) wp_send_json_error( 'Unauthorized' );

		global $wpdb;
		$id = intval( $_POST['id'] );
        $table = $wpdb->prefix . 'orabooks_ac_moneytransfer';
        
        // Get transfer details before delete to reverse balance
        $transfer = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id) );
        if ( ! $transfer ) {
            wp_send_json_error( 'Transfer not found.' );
        }

		$result = $wpdb->delete( $table, array( 'id' => $id ) );

		if ( $result ) {
            // Reverse Account Balances
            $acc_table = $wpdb->prefix . 'orabooks_ac_accounts';
             // Debit (Receiver) decreases (reversal)
             $wpdb->query( $wpdb->prepare("UPDATE $acc_table SET balance = balance - %f WHERE id = %d", $transfer->amount, $transfer->debit_account_id) );
             // Credit (Sender) increases (reversal)
             $wpdb->query( $wpdb->prepare("UPDATE $acc_table SET balance = balance + %f WHERE id = %d", $transfer->amount, $transfer->credit_account_id) );

            // Delete transaction record
            $wpdb->delete( $wpdb->prefix . 'orabooks_ac_transactions', array( 'ref_moneytransfer_id' => $id ) );

			wp_send_json_success( 'Transfer deleted successfully.' );
		} else {
			wp_send_json_error( 'Failed to delete.' );
		}
	}
}
new OBN_Money_Transfer();
