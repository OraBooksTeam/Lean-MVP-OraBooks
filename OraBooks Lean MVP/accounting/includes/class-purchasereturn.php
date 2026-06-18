<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Frontend_Accounting_PurchaseReturn')) {
    class Frontend_Accounting_PurchaseReturn
    {

        public function __construct()
        {
            // AJAX handlers for Purchase Returns
            add_action('wp_ajax_generate_purchasereturn_code', [$this, 'handle_generate_purchasereturn_code']);
            add_action('wp_ajax_insert_purchasereturn', [$this, 'handle_insert_purchasereturn']);
            add_action('wp_ajax_get_purchasereturn_details', [$this, 'handle_get_purchasereturn_details']);
            add_action('wp_ajax_update_purchasereturn', [$this, 'handle_update_purchasereturn']);
            add_action('wp_ajax_delete_purchasereturn', [$this, 'handle_delete_purchasereturn']);

            // New Workflow Actions
            add_action('wp_ajax_approve_purchasereturn', [$this, 'handle_approve_purchasereturn']);
            add_action('wp_ajax_reject_purchasereturn', [$this, 'handle_reject_purchasereturn']);
        }

        public function handle_get_purchasereturn_details()
        {
            check_ajax_referer('frontend_ajax_nonce', 'security');

            if (!orabooks_can_access_accounting()) {
                wp_send_json_error('Access denied.');
            }
            global $wpdb;
            $return_date = sanitize_text_field($_POST['purchase_date'] ?? current_time('Y-m-d'));
            if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                OBN_Fiscal_Period_Posting_Guard::assert_can_post_or_fail($return_date);
            }
            $id = intval($_GET['id']);
            if (!$id)
                wp_send_json_error('Invalid ID');

            $return_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_purchasereturn WHERE id=%d", $id));
            if (!$return_data)
                wp_send_json_error('Purchase Return not found');

            $items = $wpdb->get_results($wpdb->prepare("
            SELECT pi.*, i.item_name, i.item_code, i.sku, i.stock 
            FROM {$wpdb->prefix}orabooks_db_purchaseitemsreturn pi
            LEFT JOIN {$wpdb->prefix}orabooks_db_items i ON pi.item_id = i.id
            WHERE pi.return_id=%d
        ", $id));

            $payments = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}orabooks_db_purchasepaymentsreturn 
            WHERE return_id=%d 
            ORDER BY id ASC
        ", $id));

            // For UI purposes, we map return_data closer to what the frontend expects
            // e.g. mapping return_code to purchase_code so the UI doesn't break initially
            $return_data->purchase_code = $return_data->return_code;
            $return_data->purchase_date = $return_data->return_date;
            $return_data->purchase_note = $return_data->return_note;

            wp_send_json_success(['purchase' => $return_data, 'items' => $items, 'payments' => $payments]);
        }

        public function handle_generate_purchasereturn_code()
        {
            check_ajax_referer('frontend_ajax_nonce', 'security');

            if (!orabooks_can_access_accounting()) {
                wp_send_json_error('Access denied.');
            }
            global $wpdb;

            $table = $wpdb->prefix . 'orabooks_db_purchasereturn';
            $store_table = $wpdb->prefix . 'orabooks_db_store';

            $prefix = $wpdb->get_var("SELECT purchase_return_init FROM $store_table WHERE id = 1");
            if (!$prefix) {
                $prefix = 'PR-';
            }

            $last = $wpdb->get_var("SELECT return_code FROM $table WHERE return_code LIKE '$prefix%' ORDER BY id DESC LIMIT 1");
            $next = 1;
            if ($last) {
                $num = str_replace($prefix, '', $last);
                if (is_numeric($num)) {
                    $next = intval($num) + 1;
                }
            }
            $code = $prefix . str_pad($next, 6, '0', STR_PAD_LEFT);
            wp_send_json_success(['code' => $code]);
        }

        private function validate_return_quantities($original_purchase_id, $items, $current_return_id = 0)
        {
            global $wpdb;

            // 1. Get Original Purchased Quantities
            $original_items = $wpdb->get_results($wpdb->prepare("SELECT item_id, purchase_qty FROM {$wpdb->prefix}orabooks_db_purchaseitems WHERE purchase_id = %d", $original_purchase_id));
            $orig_map = [];
            foreach ($original_items as $oi) {
                $orig_map[$oi->item_id] = floatval($oi->purchase_qty);
            }

            // 2. Get Previously Returned Quantities (excluding Rejected, and excluding the current return if editing)
            $returned_sql = $wpdb->prepare("
            SELECT pi.item_id, SUM(pi.purchase_qty) as total_returned
            FROM {$wpdb->prefix}orabooks_db_purchaseitemsreturn pi
            JOIN {$wpdb->prefix}orabooks_db_purchasereturn pr ON pi.return_id = pr.id
            WHERE pr.purchase_id = %d AND pr.return_status != 'Rejected' AND pr.id != %d
            GROUP BY pi.item_id
        ", $original_purchase_id, $current_return_id);

            $returned_items = $wpdb->get_results($returned_sql);
            $ret_map = [];
            foreach ($returned_items as $ri) {
                $ret_map[$ri->item_id] = floatval($ri->total_returned);
            }

            // 3. Validate Attempted Quantities
            foreach ($items as $item) {
                $item_id = intval($item['item_id']);
                $attempt_qty = floatval($item['qty'] ?? 0);
                $max_allowed = ($orig_map[$item_id] ?? 0) - ($ret_map[$item_id] ?? 0);

                if ($attempt_qty > $max_allowed) {
                    return [
                        'valid' => false,
                        'message' => 'Return quantity (' . $attempt_qty . ') exceeds allowed remaining purchased quantity (' . $max_allowed . ') for item: ' . sanitize_text_field($item['name'])
                    ];
                }
            }
            return ['valid' => true];
        }

        public function handle_insert_purchasereturn()
        {
            check_ajax_referer('frontend_ajax_nonce', 'security');

            if (!orabooks_can_access_accounting()) {
                wp_send_json_error('Access denied.');
            }

            global $wpdb;

            // Ensure we are linking to an original purchase
            $purchase_id = intval($_POST['original_purchase_id'] ?? 0);
            // Fallback or explicit check if they passed it as something else (add-purchase-return might not post original_purchase_id cleanly)
            if (!$purchase_id && !empty($_POST['reference_no'])) {
                // Usually Reference No holds 'RTN-PUR001'
                $ref = str_replace('RTN-', '', sanitize_text_field($_POST['reference_no']));
                $purchase_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_db_purchase WHERE purchase_code = %s", $ref));
            }

            if (!$purchase_id)
                wp_send_json_error('Original Purchase Invoice reference is required to process a return.');

            $items = json_decode(stripslashes($_POST['items_json'] ?? '[]'), true);
            if (!is_array($items) || count($items) == 0)
                wp_send_json_error('No items to return.');

            // STRICT VALIDATION
            $validation = $this->validate_return_quantities($purchase_id, $items, 0);
            if (!$validation['valid']) {
                wp_send_json_error($validation['message']);
            }

            $table = $wpdb->prefix . 'orabooks_db_purchasereturn';

            $warehouse_id = intval($_POST['warehouse_id'] ?? 0);
            $supplier_id = intval($_POST['supplier_id'] ?? 0);
            $created_by = get_current_user_id();
            $system_ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);

            $return_code = sanitize_text_field($_POST['purchase_code'] ?? ''); // Maps from UI
            if (!empty($return_code)) {
                $existing_return = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE return_code = %s", $return_code));
                if ($existing_return) {
                    wp_send_json_error('Duplicate Entry: Return code ' . $return_code . ' already exists.');
                }
            }

            $store_id = intval($_POST['store_id'] ?? 1);

            $other_charges_input = floatval($_POST['other_charges_input'] ?? 0);
            $other_charges_tax_id = intval($_POST['other_charges_tax_id'] ?? 0);
            $other_tax_percent = 0;
            if ($other_charges_tax_id > 0) {
                $other_tax_percent = floatval($wpdb->get_var($wpdb->prepare("SELECT tax FROM {$wpdb->prefix}orabooks_db_tax WHERE id = %d", $other_charges_tax_id)));
            }
            $other_charges_amt = $other_charges_input + ($other_charges_input * $other_tax_percent / 100);

            $discount_to_all_input = floatval($_POST['discount_to_all_input'] ?? 0);
            $discount_to_all_type = sanitize_text_field($_POST['discount_to_all_type'] ?? 'Percentage');
            $subtotal = floatval($_POST['subtotal'] ?? 0);
            $tot_discount_to_all_amt = ($discount_to_all_type === 'Percentage') ? ($subtotal * $discount_to_all_input / 100) : $discount_to_all_input;

            $paid_amount = floatval($_POST['payment_amount'] ?? 0);

            $data = [
                'store_id' => $store_id,
                'warehouse_id' => $warehouse_id,
                'purchase_id' => $purchase_id,
                'return_code' => $return_code,
                'reference_no' => sanitize_text_field($_POST['reference_no'] ?? ''),
                'return_date' => $return_date,
                'return_status' => 'Pending', // New Workflow: Starts as pending
                'supplier_id' => $supplier_id,
                'other_charges_input' => $other_charges_input,
                'other_charges_tax_id' => $other_charges_tax_id,
                'other_charges_amt' => $other_charges_amt,
                'discount_to_all_input' => $discount_to_all_input,
                'discount_to_all_type' => $discount_to_all_type,
                'tot_discount_to_all_amt' => $tot_discount_to_all_amt,
                'subtotal' => $subtotal,
                'round_off' => floatval($_POST['round_off'] ?? 0),
                'grand_total' => floatval($_POST['grand_total'] ?? 0),
                'return_note' => sanitize_textarea_field($_POST['purchase_note'] ?? ''),
                'paid_amount' => $paid_amount,
                'created_date' => current_time('Y-m-d'),
                'created_time' => current_time('H:i:s'),
                'created_by' => $created_by,
                'system_ip' => $system_ip,
                'system_name' => 'System',
                'status' => 1,
            ];

            if ($paid_amount > 0) {
                $data['payment_status'] = ($paid_amount >= $data['grand_total']) ? 'Refunded' : 'Partial Refund';
            } else {
                $data['payment_status'] = 'Credit Note'; // If unpaid, offsets supplier balance
            }

            $inserted = $wpdb->insert($table, $data);
            if (!$inserted)
                wp_send_json_error('DB insert failed: ' . $wpdb->last_error);

            $last_id = $wpdb->insert_id;

            foreach ($items as $item) {
                if (empty($item['item_id']))
                    continue;

                $item_data = [
                    'return_id' => $last_id,
                    'item_id' => intval($item['item_id']),
                    'purchase_qty' => floatval($item['qty'] ?? 0),
                    'price_per_unit' => floatval($item['unit_price'] ?? 0),
                    'tax_type' => 'Percentage',
                    'tax_id' => 0,
                    'tax_amt' => floatval($item['tax_amt'] ?? 0),
                    'discount_type' => 'Fixed',
                    'discount_input' => floatval($item['discount'] ?? 0),
                    'discount_amt' => floatval($item['discount'] ?? 0),
                    'unit_total_cost' => floatval($item['unit_price'] ?? 0) * floatval($item['qty'] ?? 0),
                    'total_cost' => floatval($item['total'] ?? 0),
                    'account_id' => intval($item['account_id']),
                ];
                $wpdb->insert($wpdb->prefix . 'orabooks_db_purchaseitemsreturn', $item_data);

                // Note: We DO NOT decrement stock here. Stock is decremented upon Approval.
            }

            // Payments (Refunds)
            if ($paid_amount > 0) {
                $payment_type_id = intval($_POST['payment_type_id'] ?? 0);
                $account_id = intval($_POST['account_id'] ?? 0);

                $paydata = [
                    'return_id' => $last_id,
                    'payment_date' => $data['return_date'],
                    'payment_type' => $payment_type_id,
                    'payment' => $paid_amount,
                    'payment_note' => sanitize_textarea_field($_POST['payment_note'] ?? ''),
                    'account_id' => $account_id,
                    'supplier_id' => $supplier_id,
                    'created_date' => current_time('Y-m-d'),
                    'created_by' => $created_by,
                ];
                $wpdb->insert($wpdb->prefix . 'orabooks_db_purchasepaymentsreturn', $paydata);
            }

            wp_send_json_success(['message' => 'Purchase Return Submitted (Pending Approval)', 'purchase_id' => $last_id]);
        }

        public function handle_update_purchasereturn()
        {
            check_ajax_referer('frontend_ajax_nonce', 'security');

            if (!orabooks_can_access_accounting()) {
                wp_send_json_error('Access denied.');
            }

            global $wpdb;
            $id = intval($_POST['purchase_id']); // Usually passed as purchase_id in UI
            if (!$id)
                wp_send_json_error('Invalid ID');
            $return_date = sanitize_text_field($_POST['purchase_date'] ?? current_time('Y-m-d'));
            if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                OBN_Fiscal_Period_Posting_Guard::assert_can_post_or_fail($return_date);
            }

            $existing_return = $wpdb->get_row($wpdb->prepare("SELECT return_status, purchase_id, return_date FROM {$wpdb->prefix}orabooks_db_purchasereturn WHERE id=%d", $id));
            if (!$existing_return)
                wp_send_json_error('Return not found');
            if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(obn_current_org_id(), $existing_return->return_date);
                if (is_wp_error($modification_allowed)) {
                    wp_send_json_error($modification_allowed->get_error_message(), 409);
                }
            }

            if ($existing_return->return_status != 'Pending') {
                wp_send_json_error('Only Pending returns can be edited.');
            }

            $items = json_decode(stripslashes($_POST['items_json'] ?? '[]'), true);
            if (!is_array($items) || count($items) == 0)
                wp_send_json_error('No items to return.');

            // STRICT VALIDATION
            $validation = $this->validate_return_quantities($existing_return->purchase_id, $items, $id);
            if (!$validation['valid']) {
                wp_send_json_error($validation['message']);
            }

            $table = $wpdb->prefix . 'orabooks_db_purchasereturn';

            $warehouse_id = intval($_POST['warehouse_id'] ?? 0);
            $supplier_id = intval($_POST['supplier_id'] ?? 0);

            $other_charges_input = floatval($_POST['other_charges_input'] ?? 0);
            $other_charges_tax_id = intval($_POST['other_charges_tax_id'] ?? 0);
            $other_tax_percent = 0;
            if ($other_charges_tax_id > 0) {
                $other_tax_percent = floatval($wpdb->get_var($wpdb->prepare("SELECT tax FROM {$wpdb->prefix}orabooks_db_tax WHERE id = %d", $other_charges_tax_id)));
            }
            $other_charges_amt = $other_charges_input + ($other_charges_input * $other_tax_percent / 100);

            $discount_to_all_input = floatval($_POST['discount_to_all_input'] ?? 0);
            $discount_to_all_type = sanitize_text_field($_POST['discount_to_all_type'] ?? 'Percentage');
            $subtotal = floatval($_POST['subtotal'] ?? 0);
            $tot_discount_to_all_amt = ($discount_to_all_type === 'Percentage') ? ($subtotal * $discount_to_all_input / 100) : $discount_to_all_input;

            $paid_amount = floatval($_POST['payment_amount'] ?? 0);

            $data = [
                'warehouse_id' => $warehouse_id,
                'reference_no' => sanitize_text_field($_POST['reference_no'] ?? ''),
                'return_date' => $return_date,
                'supplier_id' => $supplier_id,
                'other_charges_input' => $other_charges_input,
                'other_charges_tax_id' => $other_charges_tax_id,
                'other_charges_amt' => $other_charges_amt,
                'discount_to_all_input' => $discount_to_all_input,
                'discount_to_all_type' => $discount_to_all_type,
                'tot_discount_to_all_amt' => $tot_discount_to_all_amt,
                'subtotal' => $subtotal,
                'round_off' => floatval($_POST['round_off'] ?? 0),
                'grand_total' => floatval($_POST['grand_total'] ?? 0),
                'return_note' => sanitize_textarea_field($_POST['purchase_note'] ?? ''),
                'paid_amount' => $paid_amount,
            ];

            if ($paid_amount > 0) {
                $data['payment_status'] = ($paid_amount >= $data['grand_total']) ? 'Refunded' : 'Partial Refund';
            } else {
                $data['payment_status'] = 'Credit Note';
            }

            $wpdb->update($table, $data, ['id' => $id]);

            $wpdb->delete($wpdb->prefix . 'orabooks_db_purchaseitemsreturn', ['return_id' => $id]);
            foreach ($items as $item) {
                if (empty($item['item_id']))
                    continue;

                $item_data = [
                    'return_id' => $id,
                    'item_id' => intval($item['item_id']),
                    'purchase_qty' => floatval($item['qty'] ?? 0),
                    'price_per_unit' => floatval($item['unit_price'] ?? 0),
                    'tax_type' => 'Percentage',
                    'tax_id' => 0,
                    'tax_amt' => floatval($item['tax_amt'] ?? 0),
                    'discount_type' => 'Fixed',
                    'discount_input' => floatval($item['discount'] ?? 0),
                    'discount_amt' => floatval($item['discount'] ?? 0),
                    'unit_total_cost' => floatval($item['unit_price'] ?? 0) * floatval($item['qty'] ?? 0),
                    'total_cost' => floatval($item['total'] ?? 0),
                    'account_id' => intval($item['account_id']),
                ];
                $wpdb->insert($wpdb->prefix . 'orabooks_db_purchaseitemsreturn', $item_data);
            }

            $wpdb->delete($wpdb->prefix . 'orabooks_db_purchasepaymentsreturn', ['return_id' => $id]);
            if ($paid_amount > 0) {
                $paydata = [
                    'return_id' => $id,
                    'payment_date' => $data['return_date'],
                    'payment_type' => intval($_POST['payment_type_id'] ?? 0),
                    'payment' => $paid_amount,
                    'payment_note' => sanitize_textarea_field($_POST['payment_note'] ?? ''),
                    'account_id' => intval($_POST['account_id'] ?? 0),
                    'supplier_id' => $supplier_id,
                    'created_date' => current_time('Y-m-d'),
                    'created_by' => get_current_user_id()
                ];
                $wpdb->insert($wpdb->prefix . 'orabooks_db_purchasepaymentsreturn', $paydata);
            }

            wp_send_json_success(['message' => 'Purchase Return Updated successfully', 'purchase_id' => $id]);
        }

        public function handle_delete_purchasereturn()
        {
            check_ajax_referer('frontend_ajax_nonce', 'security');

            if (!orabooks_can_access_accounting()) {
                wp_send_json_error('Access denied.');
            }

            global $wpdb;
            $id = intval($_POST['purchase_id']); // Usually passed as purchase_id
            if (!$id)
                wp_send_json_error('Invalid ID');

            $return_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_purchasereturn WHERE id=%d", $id));
            if (!$return_data)
                wp_send_json_error('Return not found.');
            if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(obn_current_org_id(), $return_data->return_date);
                if (is_wp_error($modification_allowed)) {
                    wp_send_json_error($modification_allowed->get_error_message(), 409);
                }
            }

            if ($return_data->return_status == 'Approved') {
                // Cannot delete approved returns directly without proper adjustments/reversals.
                // Or if design allows, we must RESTORE stock and reverse Journal Entry
                $this->reverse_approved_return($id, $return_data);
            }

            $wpdb->delete($wpdb->prefix . 'orabooks_db_purchasereturn', ['id' => $id]);
            $wpdb->delete($wpdb->prefix . 'orabooks_db_purchaseitemsreturn', ['return_id' => $id]);
            $wpdb->delete($wpdb->prefix . 'orabooks_db_purchasepaymentsreturn', ['return_id' => $id]);

            wp_send_json_success('Purchase Return entry deleted successfully');
        }

        /* 
         * NEW: Approval Workflow Endpoint 
         */
        public function handle_approve_purchasereturn()
        {
            check_ajax_referer('frontend_ajax_nonce', 'security');

            if (!orabooks_can_access_accounting()) {
                wp_send_json_error('Access denied.');
            }

            global $wpdb;
            $id = intval($_POST['id']);
            if (!$id)
                wp_send_json_error('Invalid ID');

            $return_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_purchasereturn WHERE id=%d", $id));
            if (!$return_data)
                wp_send_json_error('Return not found');
            if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(obn_current_org_id(), $return_data->return_date);
                if (is_wp_error($modification_allowed)) {
                    wp_send_json_error($modification_allowed->get_error_message(), 409);
                }
            }
            if ($return_data->return_status != 'Pending')
                wp_send_json_error('Only pending returns can be approved.');

            $original_purchase_id = $return_data->purchase_id;

            // 1. DEDUCT STOCK & UPDATE ORIGINAL PURCHASE ITEMS
            $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_purchaseitemsreturn WHERE return_id=%d", $id));

            // Log Stock Adjustment
            $wpdb->insert($wpdb->prefix . 'orabooks_db_stockadjustment', [
                'store_id' => $return_data->store_id,
                'warehouse_id' => $return_data->warehouse_id,
                'reference_no' => 'RTN-' . $return_data->return_code,
                'adjustment_date' => current_time('Y-m-d'),
                'adjustment_note' => 'Adjustment for Purchase Return: ' . $return_data->return_code,
                'created_date' => current_time('Y-m-d'),
                'created_by' => get_current_user_id(),
                'status' => 1
            ]);
            $adj_id = $wpdb->insert_id;

            foreach ($items as $item) {
                // Subtract main stock
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}orabooks_db_items SET stock = stock - %f WHERE id = %d",
                    $item->purchase_qty,
                    $item->item_id
                ));

                // Subtract warehouse stock
                $wh_res = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_db_warehouseitems WHERE warehouse_id = %d AND item_id = %d", $return_data->warehouse_id, $item->item_id));
                if ($wh_res) {
                    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_warehouseitems SET available_qty = available_qty - %f WHERE id = %d", $item->purchase_qty, $wh_res->id));
                }

                // AFFECT ORIGINAL PURCHASE QTY
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}orabooks_db_purchaseitems 
                 SET purchase_qty = purchase_qty - %f,
                     total_cost = total_cost - %f
                 WHERE purchase_id = %d AND item_id = %d",
                    $item->purchase_qty,
                    $item->total_cost,
                    $original_purchase_id,
                    $item->item_id
                ));

                // Log adjustment item
                $wpdb->insert($wpdb->prefix . 'orabooks_db_stockadjustmentitems', [
                    'store_id' => $return_data->store_id,
                    'warehouse_id' => $return_data->warehouse_id,
                    'adjustment_id' => $adj_id,
                    'item_id' => $item->item_id,
                    'adjustment_qty' => -($item->purchase_qty),
                    'status' => 1,
                    'description' => 'Purchase Return Approved',
                    'account_id' => $item->account_id
                ]);
            }

            // 2. AFFECT ORIGINAL PURCHASE PAYMENT & TOTALS
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}orabooks_db_purchase 
             SET subtotal = subtotal - %f,
                 grand_total = grand_total - %f,
                 paid_amount = paid_amount - %f
             WHERE id = %d",
                $return_data->subtotal,
                $return_data->grand_total,
                $return_data->paid_amount,
                $original_purchase_id
            ));

            // 3. CREATE ACCOUNTING ENTRIES
            $this->create_purchasereturn_journal_entry($id, $return_data);

            // 4. Mark Approved
            $wpdb->update($wpdb->prefix . 'orabooks_db_purchasereturn', [
                'return_status' => 'Approved',
                'approved_by' => get_current_user_id(),
                'approved_date' => current_time('mysql')
            ], ['id' => $id]);

            wp_send_json_success('Return Approved. Stock, Original Purchase Qty and Payment adjusted.');
        }

        /* 
         * NEW: Workflow Rejection 
         */
        public function handle_reject_purchasereturn()
        {
            check_ajax_referer('frontend_ajax_nonce', 'security');

            if (!orabooks_can_access_accounting()) {
                wp_send_json_error('Access denied.');
            }

            global $wpdb;
            $id = intval($_POST['id']);
            if (!$id)
                wp_send_json_error('Invalid ID');

            $return_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_purchasereturn WHERE id=%d", $id));
            if (!$return_data)
                wp_send_json_error('Return not found');
            if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(obn_current_org_id(), $return_data->return_date);
                if (is_wp_error($modification_allowed)) {
                    wp_send_json_error($modification_allowed->get_error_message(), 409);
                }
            }

            if ($return_data->return_status == 'Approved') {
                $this->reverse_approved_return($id, $return_data);
            }

            $wpdb->update($wpdb->prefix . 'orabooks_db_purchasereturn', [
                'return_status' => 'Rejected',
                'approved_by' => get_current_user_id(),
                'approved_date' => current_time('mysql')
            ], ['id' => $id]);

            wp_send_json_success('Return Rejected. Status updated.');
        }

        private function reverse_approved_return($id, $return_data)
        {
            global $wpdb;
            $original_purchase_id = $return_data->purchase_id;

            // 1. REVERSE STOCK
            $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_purchaseitemsreturn WHERE return_id=%d", $id));
            foreach ($items as $item) {
                // Restore main stock
                $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_items SET stock = stock + %f WHERE id=%d", $item->purchase_qty, $item->item_id));

                // Restore warehouse stock
                $wh_res = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_db_warehouseitems WHERE warehouse_id = %d AND item_id = %d", $return_data->warehouse_id, $item->item_id));
                if ($wh_res) {
                    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_warehouseitems SET available_qty = available_qty + %f WHERE id = %d", $item->purchase_qty, $wh_res->id));
                }

                // RESTORE ORIGINAL PURCHASE QTY
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}orabooks_db_purchaseitems 
                 SET purchase_qty = purchase_qty + %f,
                     total_cost = total_cost + %f
                 WHERE purchase_id = %d AND item_id = %d",
                    $item->purchase_qty,
                    $item->total_cost,
                    $original_purchase_id,
                    $item->item_id
                ));
            }

            // 2. RESTORE ORIGINAL PURCHASE TOTALS
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}orabooks_db_purchase 
             SET subtotal = subtotal + %f,
                 grand_total = grand_total + %f,
                 paid_amount = paid_amount + %f
             WHERE id = %d",
                $return_data->subtotal,
                $return_data->grand_total,
                $return_data->paid_amount,
                $original_purchase_id
            ));

            // 3. Reverse Journal Entries
            $existing_entry_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_ac_journal_entry WHERE source_type = 'PurchaseReturn' AND source_id = %d", $id));
            if ($existing_entry_id) {
                if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                    OBN_Fiscal_Period_Posting_Guard::assert_journal_entry_modifiable_or_fail($existing_entry_id);
                }
                $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_line', ['journal_entry_id' => $existing_entry_id]);
                $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_entry', ['id' => $existing_entry_id]);
            }
        }

        private function create_purchasereturn_journal_entry($return_id, $return_data)
        {
            global $wpdb;

            $store_id = $return_data->store_id;
            $supplier_id = $return_data->supplier_id;
            $grand_total = floatval($return_data->grand_total);
            $paid_amount = floatval($return_data->paid_amount);
            $due_amount = $grand_total - $paid_amount;
            $return_date = $return_data->return_date;

            if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                $posting_allowed = OBN_Fiscal_Period_Posting_Guard::can_post(obn_current_org_id(), $return_date);
                if (is_wp_error($posting_allowed)) {
                    wp_send_json_error($posting_allowed->get_error_message(), 409);
                }
            }

            $currency_code = $wpdb->get_var($wpdb->prepare("SELECT currency_code FROM {$wpdb->prefix}orabooks_db_currency WHERE id = (SELECT currency_id FROM {$wpdb->prefix}orabooks_db_store WHERE id = %d LIMIT 1)", $store_id));
            if (!$currency_code)
                $currency_code = 'BDT';

            // 1. Find Accounts
            $inventory_account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_name = 'Inventory' OR account_code = '1100' OR account_name LIKE '%Inventory%' OR account_name LIKE '%Stock%') AND status=1 ORDER BY (account_name = 'Inventory') DESC LIMIT 1");
            $ap_account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code = '2000' OR account_name LIKE '%Accounts Payable%' OR account_name LIKE '%Creditors%') AND status=1 LIMIT 1");

            $payment = $wpdb->get_row($wpdb->prepare("SELECT account_id FROM {$wpdb->prefix}orabooks_db_purchasepaymentsreturn WHERE return_id = %d LIMIT 1", $return_id));
            $cash_account_id = $payment ? intval($payment->account_id) : 0;

            if (!$cash_account_id && $paid_amount > 0) {
                $cash_account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code = '1000' OR account_name LIKE '%Cash%' OR account_name LIKE '%Bank%' OR account_name LIKE '%Petty%') AND status=1 LIMIT 1");
            }

            $entry_number = 'JE-PRN-' . str_pad($return_id, 6, '0', STR_PAD_LEFT);

            $entry_data = [
                'store_id' => $store_id,
                'organization_id' => obn_current_org_id(),
                'entry_number' => $entry_number,
                'entry_date' => $return_date,
                'posting_date' => $return_date,
                'document_date' => $return_date,
                'reference_no' => $return_data->return_code,
                'description' => 'Purchase Return Approved Journal Entry ' . $return_data->return_code,
                'source_type' => 'PurchaseReturn',
                'source_id' => $return_id,
                'status' => 'Posted',
                'currency' => $currency_code,
                'base_currency' => $currency_code,
                'total_debit' => $grand_total,
                'total_credit' => $grand_total,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'posted_at' => current_time('mysql'),
                'locked' => 1
            ];

            $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_entry', $entry_data);
            $journal_entry_id = $wpdb->insert_id;

            if (!$journal_entry_id)
                return;

            $line_num = 1;

            // CREDIT: Inventory (Total Amount - because items are returned)
            if ($inventory_account_id) {
                $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
                    'journal_entry_id' => $journal_entry_id,
                    'account_id' => $inventory_account_id,
                    'description' => 'Inventory - ' . $return_data->return_code,
                    'debit' => 0,
                    'credit' => $grand_total,
                    'debit_amt' => 0,
                    'credit_amt' => $grand_total,
                    'currency' => $currency_code,
                    'exchange_rate' => 1,
                    'amount_base' => $grand_total,
                    'line_number' => $line_num++,
                    'status' => 1
                ]);
            }

            // DEBIT: Cash/Bank (Paid/Refunded Portion)
            if ($paid_amount > 0 && $cash_account_id) {
                $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
                    'journal_entry_id' => $journal_entry_id,
                    'account_id' => $cash_account_id,
                    'contact_id' => $supplier_id,
                    'description' => 'Cash/Bank Refunded - ' . $return_data->return_code,
                    'debit' => $paid_amount,
                    'credit' => 0,
                    'debit_amt' => $paid_amount,
                    'credit_amt' => 0,
                    'currency' => $currency_code,
                    'exchange_rate' => 1,
                    'amount_base' => $paid_amount,
                    'line_number' => $line_num++,
                    'status' => 1
                ]);
            }

            // DEBIT: Accounts Payable (Due/Credit Note Portion)
            if ($due_amount > 0 && $ap_account_id) {
                $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
                    'journal_entry_id' => $journal_entry_id,
                    'account_id' => $ap_account_id,
                    'contact_id' => $supplier_id,
                    'description' => 'Credit Note against Payable - ' . $return_data->return_code,
                    'debit' => $due_amount,
                    'credit' => 0,
                    'debit_amt' => $due_amount,
                    'credit_amt' => 0,
                    'currency' => $currency_code,
                    'exchange_rate' => 1,
                    'amount_base' => $due_amount,
                    'line_number' => $line_num++,
                    'status' => 1
                ]);
            }
        }
    }

    new Frontend_Accounting_PurchaseReturn();
}
