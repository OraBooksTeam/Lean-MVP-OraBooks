<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Frontend_Accounting_SalesReturn {

    public function __construct() {
        // AJAX handlers for Sales Returns
        add_action('wp_ajax_generate_salesreturn_code', [$this, 'handle_generate_salesreturn_code']);
        add_action('wp_ajax_insert_salesreturn', [$this, 'handle_insert_salesreturn']);
        add_action('wp_ajax_get_salesreturn_details', [$this, 'handle_get_salesreturn_details']);
        add_action('wp_ajax_update_salesreturn', [$this, 'handle_update_salesreturn']);
        add_action('wp_ajax_delete_salesreturn', [$this, 'handle_delete_salesreturn']);
        add_action('wp_ajax_search_salesreturn', [$this, 'handle_search_salesreturn']);
        
        // Workflow Actions
        add_action('wp_ajax_approve_salesreturn', [$this, 'handle_approve_salesreturn']);
        add_action('wp_ajax_reject_salesreturn', [$this, 'handle_reject_salesreturn']);
    }

    public function handle_get_salesreturn_details() {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if ( ! orabooks_can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }
        global $wpdb;
        $return_date = sanitize_text_field($_POST['sales_date'] ?? current_time('Y-m-d'));
        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            OBN_Fiscal_Period_Posting_Guard::assert_can_post_or_fail($return_date);
        }
        $id = intval($_GET['id']);
        if(!$id) wp_send_json_error('Invalid ID');

        $return_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_salesreturn WHERE id=%d", $id));
        if(!$return_data) wp_send_json_error('Sales Return not found');

        $items = $wpdb->get_results($wpdb->prepare("
            SELECT si.*, i.item_name, i.item_code, i.sku, i.stock 
            FROM {$wpdb->prefix}orabooks_db_salesitemsreturn si
            LEFT JOIN {$wpdb->prefix}orabooks_db_items i ON si.item_id = i.id
            WHERE si.return_id=%d
        ", $id));

        $payments = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}orabooks_db_salespaymentsreturn 
            WHERE return_id=%d 
            ORDER BY id ASC
        ", $id));

        // For UI purposes, we map return_data closer to what the frontend expects
        $return_data->sales_code = $return_data->return_code;
        $return_data->sales_date = $return_data->return_date;
        $return_data->sales_note = $return_data->return_note;

        wp_send_json_success(['sales' => $return_data, 'items' => $items, 'payments' => $payments]);
    }

    public function handle_generate_salesreturn_code() {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if ( ! orabooks_can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }
        global $wpdb;

        $table = $wpdb->prefix . 'orabooks_db_salesreturn';
        $store_table = $wpdb->prefix . 'orabooks_db_store';
        
        $prefix = $wpdb->get_var("SELECT sales_return_init FROM $store_table WHERE id = 1");
        if (!$prefix) {
            $prefix = 'SR-';
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

    private function validate_return_quantities($original_sales_id, $items, $current_return_id = 0) {
        global $wpdb;
        
        // 1. Get Original Sold Quantities
        $original_items = $wpdb->get_results($wpdb->prepare("SELECT item_id, sales_qty FROM {$wpdb->prefix}orabooks_db_salesitems WHERE sales_id = %d", $original_sales_id));
        $orig_map = [];
        foreach($original_items as $oi) {
            $orig_map[$oi->item_id] = floatval($oi->sales_qty);
        }

        // 2. Get Previously Returned Quantities
        $returned_sql = $wpdb->prepare("
            SELECT si.item_id, SUM(si.sales_qty) as total_returned
            FROM {$wpdb->prefix}orabooks_db_salesitemsreturn si
            JOIN {$wpdb->prefix}orabooks_db_salesreturn sr ON si.return_id = sr.id
            WHERE sr.sales_id = %d AND sr.return_status != 'Rejected' AND sr.id != %d
            GROUP BY si.item_id
        ", $original_sales_id, $current_return_id);

        $returned_items = $wpdb->get_results($returned_sql);
        $ret_map = [];
        foreach($returned_items as $ri) {
            $ret_map[$ri->item_id] = floatval($ri->total_returned);
        }

        // 3. Validate Attempted Quantities
        foreach($items as $item) {
            $item_id = intval($item['item_id']);
            $attempt_qty = floatval($item['qty'] ?? 0);
            $max_allowed = ($orig_map[$item_id] ?? 0) - ($ret_map[$item_id] ?? 0);
            
            if ($attempt_qty > $max_allowed) {
                return [
                    'valid' => false, 
                    'message' => 'Return quantity ('.$attempt_qty.') exceeds allowed remaining sold quantity ('.$max_allowed.') for item: ' . sanitize_text_field($item['name'])
                ];
            }
        }
        return ['valid' => true];
    }

    public function handle_insert_salesreturn() {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if ( ! orabooks_can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }
        
        global $wpdb;

        $sales_id = intval($_POST['original_sales_id'] ?? 0);
        if (!$sales_id && !empty($_POST['reference_no'])) {
            $ref = str_replace('RTN-', '', sanitize_text_field($_POST['reference_no']));
            $sales_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_db_sales WHERE sales_code = %s", $ref));
        }

        if (!$sales_id) wp_send_json_error('Original Sales Invoice reference is required.');

        $items = json_decode(stripslashes($_POST['items_json'] ?? '[]'), true);
        if (!is_array($items) || count($items) == 0) wp_send_json_error('No items to return.');

        $validation = $this->validate_return_quantities($sales_id, $items, 0);
        if (!$validation['valid']) {
            wp_send_json_error($validation['message']);
        }

        $table = $wpdb->prefix . 'orabooks_db_salesreturn';
        
        $warehouse_id = intval($_POST['warehouse_id'] ?? 0);
        $customer_id  = intval($_POST['customer_id'] ?? 0);
        $created_by   = get_current_user_id();
        $system_ip    = sanitize_text_field($_SERVER['REMOTE_ADDR']);

        $return_code = sanitize_text_field($_POST['sales_code'] ?? ''); 
        if (!empty($return_code)) {
            $existing_return = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE return_code = %s", $return_code));
            if ($existing_return) {
                wp_send_json_error('Duplicate Entry: Return code ' . $return_code . ' already exists.');
            }
        }

        $store_id = intval($_POST['store_id'] ?? 1);

        $other_charges_input   = floatval($_POST['other_charges_input'] ?? 0);
        $other_charges_tax_id  = intval($_POST['other_charges_tax_id'] ?? 0);
        $other_tax_percent = 0;
        if ($other_charges_tax_id > 0) {
            $other_tax_percent = floatval($wpdb->get_var($wpdb->prepare("SELECT tax FROM {$wpdb->prefix}orabooks_db_tax WHERE id = %d", $other_charges_tax_id)));
        }
        $other_charges_amt = $other_charges_input + ($other_charges_input * $other_tax_percent / 100);

        $discount_to_all_input = floatval($_POST['discount_to_all_input'] ?? 0);
        $discount_to_all_type  = sanitize_text_field($_POST['discount_to_all_type'] ?? 'Percentage');
        $subtotal              = floatval($_POST['subtotal'] ?? 0);
        $tot_discount_to_all_amt = ($discount_to_all_type === 'Percentage') ? ($subtotal * $discount_to_all_input / 100) : $discount_to_all_input;

        $paid_amount = floatval($_POST['payment_amount'] ?? 0);
        $return_reason = sanitize_text_field($_POST['return_reason'] ?? '');
        $return_note = sanitize_textarea_field($_POST['sales_note'] ?? '');
        if ($return_reason) {
            $return_note = "Reason: " . $return_reason . "\n" . $return_note;
        }

        $data = [
            'store_id'          => $store_id,
            'warehouse_id'      => $warehouse_id,
            'sales_id'          => $sales_id,
            'return_code'       => $return_code,
            'reference_no'      => sanitize_text_field($_POST['reference_no'] ?? ''),
            'return_date'       => $return_date,
            'return_status'     => 'Pending',
            'customer_id'       => $customer_id,
            'other_charges_input'   => $other_charges_input,
            'other_charges_tax_id'  => $other_charges_tax_id,
            'other_charges_amt'     => $other_charges_amt,
            'discount_to_all_input' => $discount_to_all_input,
            'discount_to_all_type'  => $discount_to_all_type,
            'tot_discount_to_all_amt' => $tot_discount_to_all_amt,
            'subtotal'                => $subtotal,
            'round_off'               => floatval($_POST['round_off'] ?? 0),
            'grand_total'             => floatval($_POST['grand_total'] ?? 0),
            'return_note'             => $return_note,
            'paid_amount'             => $paid_amount,
            'created_date'            => current_time('Y-m-d'),
            'created_time'            => current_time('H:i:s'),
            'created_by'              => $created_by,
            'system_ip'               => $system_ip,
            'system_name'             => 'System',
            'status'                  => 1,
        ];

        if ($paid_amount > 0) {
            $data['payment_status'] = ($paid_amount >= $data['grand_total']) ? 'Refunded' : 'Partial Refund';
        } else {
            $data['payment_status'] = 'Credit Note';
        }

        $inserted = $wpdb->insert($table, $data);
        if (!$inserted) wp_send_json_error('DB insert failed: ' . $wpdb->last_error);

        $last_id = $wpdb->insert_id;
        
        foreach ($items as $item) {
            if (empty($item['item_id'])) continue;

            $item_data = [
                'return_id' => $last_id,
                'item_id' => intval($item['item_id']),
                'sales_qty' => floatval($item['qty'] ?? 0),
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
            $wpdb->insert($wpdb->prefix . 'orabooks_db_salesitemsreturn', $item_data);
        }
        
        if($paid_amount > 0) {
            $paydata = [
                'return_id'     => $last_id,
                'payment_date'  => $data['return_date'],
                'payment_type'  => intval($_POST['payment_type_id'] ?? 0),
                'payment'       => $paid_amount,
                'payment_note'  => sanitize_textarea_field($_POST['payment_note'] ?? ''),
                'account_id'    => intval($_POST['account_id'] ?? 0),
                'customer_id'   => $customer_id,
                'created_date'  => current_time('Y-m-d'),
                'created_by'    => $created_by,
            ];
            $wpdb->insert($wpdb->prefix . 'orabooks_db_salespaymentsreturn', $paydata);
        }

        wp_send_json_success(['message' => 'Sales Return Submitted (Pending Approval)', 'sales_id' => $last_id]);
    }

    public function handle_update_salesreturn() {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if ( ! orabooks_can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }
        
        global $wpdb;
        $id = intval($_POST['sales_id']); 
        if(!$id) wp_send_json_error('Invalid ID');
        $return_date = sanitize_text_field($_POST['sales_date'] ?? current_time('Y-m-d'));
        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            OBN_Fiscal_Period_Posting_Guard::assert_can_post_or_fail($return_date);
        }

        $existing_return = $wpdb->get_row($wpdb->prepare("SELECT return_status, sales_id, return_date FROM {$wpdb->prefix}orabooks_db_salesreturn WHERE id=%d", $id));
        if(!$existing_return) wp_send_json_error('Return not found');
        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(get_current_blog_id(), $existing_return->return_date);
            if (is_wp_error($modification_allowed)) {
                wp_send_json_error($modification_allowed->get_error_message(), 409);
            }
        }

        if($existing_return->return_status != 'Pending') {
            wp_send_json_error('Only Pending returns can be edited.');
        }

        $items = json_decode(stripslashes($_POST['items_json'] ?? '[]'), true);
        if (!is_array($items) || count($items) == 0) wp_send_json_error('No items to return.');

        $validation = $this->validate_return_quantities($existing_return->sales_id, $items, $id);
        if (!$validation['valid']) {
            wp_send_json_error($validation['message']);
        }

        $table = $wpdb->prefix . 'orabooks_db_salesreturn';
        
        $warehouse_id = intval($_POST['warehouse_id'] ?? 0);
        $customer_id  = intval($_POST['customer_id'] ?? 0);
        
        $other_charges_input   = floatval($_POST['other_charges_input'] ?? 0);
        $other_charges_tax_id  = intval($_POST['other_charges_tax_id'] ?? 0);
        $other_tax_percent = 0;
        if ($other_charges_tax_id > 0) {
            $other_tax_percent = floatval($wpdb->get_var($wpdb->prepare("SELECT tax FROM {$wpdb->prefix}orabooks_db_tax WHERE id = %d", $other_charges_tax_id)));
        }
        $other_charges_amt = $other_charges_input + ($other_charges_input * $other_tax_percent / 100);

        $discount_to_all_input = floatval($_POST['discount_to_all_input'] ?? 0);
        $discount_to_all_type  = sanitize_text_field($_POST['discount_to_all_type'] ?? 'Percentage');
        $subtotal              = floatval($_POST['subtotal'] ?? 0);
        $tot_discount_to_all_amt = ($discount_to_all_type === 'Percentage') ? ($subtotal * $discount_to_all_input / 100) : $discount_to_all_input;

        $paid_amount = floatval($_POST['payment_amount'] ?? 0);
        $return_reason = sanitize_text_field($_POST['return_reason'] ?? '');
        $return_note = sanitize_textarea_field($_POST['sales_note'] ?? '');
        if ($return_reason) {
            // Check if reason already exists in note to avoid duplicates if updating
            if (strpos($return_note, "Reason: " . $return_reason) === false) {
                $return_note = "Reason: " . $return_reason . "\n" . $return_note;
            }
        }

        $data = [
            'warehouse_id'      => $warehouse_id,
            'reference_no'      => sanitize_text_field($_POST['reference_no'] ?? ''),
            'return_date'       => $return_date,
            'customer_id'       => $customer_id,
            'other_charges_input'   => $other_charges_input,
            'other_charges_tax_id'  => $other_charges_tax_id,
            'other_charges_amt'     => $other_charges_amt,
            'discount_to_all_input' => $discount_to_all_input,
            'discount_to_all_type'  => $discount_to_all_type,
            'tot_discount_to_all_amt' => $tot_discount_to_all_amt,
            'subtotal'                => $subtotal,
            'round_off'               => floatval($_POST['round_off'] ?? 0),
            'grand_total'             => floatval($_POST['grand_total'] ?? 0),
            'return_note'             => $return_note,
            'paid_amount'             => $paid_amount,
        ];

        if ($paid_amount > 0) {
            $data['payment_status'] = ($paid_amount >= $data['grand_total']) ? 'Refunded' : 'Partial Refund';
        } else {
            $data['payment_status'] = 'Credit Note';
        }
        
        $wpdb->update($table, $data, ['id' => $id]);
        
        $wpdb->delete($wpdb->prefix . 'orabooks_db_salesitemsreturn', ['return_id' => $id]);
        foreach ($items as $item) {
            if (empty($item['item_id'])) continue;
            
            $item_data = [
                'return_id' => $id,
                'item_id' => intval($item['item_id']),
                'sales_qty' => floatval($item['qty'] ?? 0),
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
            $wpdb->insert($wpdb->prefix . 'orabooks_db_salesitemsreturn', $item_data);
        }

        $wpdb->delete($wpdb->prefix . 'orabooks_db_salespaymentsreturn', ['return_id' => $id]);
        if($paid_amount > 0) {
            $paydata = [
                'return_id'     => $id,
                'payment_date'  => $data['return_date'],
                'payment_type'  => intval($_POST['payment_type_id'] ?? 0),
                'payment'       => $paid_amount,
                'payment_note'  => sanitize_textarea_field($_POST['payment_note'] ?? ''),
                'account_id'    => intval($_POST['account_id'] ?? 0),
                'customer_id'   => $customer_id,
                'created_date'  => current_time('Y-m-d'),
                'created_by'    => get_current_user_id()
            ];
            $wpdb->insert($wpdb->prefix . 'orabooks_db_salespaymentsreturn', $paydata);
        }
        
        wp_send_json_success(['message' => 'Sales Return Updated successfully', 'sales_id' => $id]);
    }

    public function handle_delete_salesreturn() {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if ( ! orabooks_can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }
        
        global $wpdb;
        $id = intval($_POST['sales_id']); 
        if (!$id) wp_send_json_error('Invalid ID');

        $return_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_salesreturn WHERE id=%d", $id));
        if(!$return_data) wp_send_json_error('Return not found.');
        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(get_current_blog_id(), $return_data->return_date);
            if (is_wp_error($modification_allowed)) {
                wp_send_json_error($modification_allowed->get_error_message(), 409);
            }
        }

        if ($return_data->return_status == 'Approved') {
            $this->reverse_approved_return($id, $return_data);
        }

        $wpdb->delete($wpdb->prefix . 'orabooks_db_salesreturn', ['id' => $id]);
        $wpdb->delete($wpdb->prefix . 'orabooks_db_salesitemsreturn', ['return_id' => $id]);
        $wpdb->delete($wpdb->prefix . 'orabooks_db_salespaymentsreturn', ['return_id' => $id]);

        wp_send_json_success('Sales Return entry deleted successfully');
    }

    public function handle_approve_salesreturn() {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if ( ! orabooks_can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $id = intval($_POST['id']);
        if (!$id) wp_send_json_error('Invalid ID');

        $return_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_salesreturn WHERE id=%d", $id));
        if(!$return_data) wp_send_json_error('Return not found');
        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(get_current_blog_id(), $return_data->return_date);
            if (is_wp_error($modification_allowed)) {
                wp_send_json_error($modification_allowed->get_error_message(), 409);
            }
        }
        if($return_data->return_status != 'Pending') wp_send_json_error('Only pending returns can be approved.');

        $original_sales_id = $return_data->sales_id;

        // 1. ADD STOCK (RESTOCK)
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_salesitemsreturn WHERE return_id=%d", $id));
        
        // Log Stock Adjustment
        $wpdb->insert($wpdb->prefix . 'orabooks_db_stockadjustment', [
            'store_id' => $return_data->store_id,
            'warehouse_id' => $return_data->warehouse_id,
            'reference_no' => 'SRTN-'. $return_data->return_code,
            'adjustment_date' => current_time('Y-m-d'),
            'adjustment_note' => 'Adjustment for Sales Return: ' . $return_data->return_code,
            'created_date' => current_time('Y-m-d'),
            'created_by' => get_current_user_id(),
            'status' => 1
        ]);
        $adj_id = $wpdb->insert_id;

        foreach ($items as $item) {
            // Add main stock
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}orabooks_db_items SET stock = stock + %f WHERE id = %d",
                $item->sales_qty, $item->item_id
            ));

            // Add warehouse stock
            $wh_res = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_db_warehouseitems WHERE warehouse_id = %d AND item_id = %d", $return_data->warehouse_id, $item->item_id));
            if ($wh_res) {
                $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_warehouseitems SET available_qty = available_qty + %f WHERE id = %d", $item->sales_qty, $wh_res->id));
            }

            // AFFECT ORIGINAL SALES QTY
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}orabooks_db_salesitems 
                 SET sales_qty = sales_qty - %f,
                     total_cost = total_cost - %f
                 WHERE sales_id = %d AND item_id = %d",
                $item->sales_qty, $item->total_cost, $original_sales_id, $item->item_id
            ));

            // Log adjustment item
            $wpdb->insert($wpdb->prefix . 'orabooks_db_stockadjustmentitems', [
                'store_id' => $return_data->store_id,
                'warehouse_id' => $return_data->warehouse_id,
                'adjustment_id' => $adj_id,
                'item_id' => $item->item_id,
                'adjustment_qty' => $item->sales_qty,
                'status' => 1,
                'description' => 'Sales Return Approved',
                'account_id' => $item->account_id
            ]);
        }

        // 2. AFFECT ORIGINAL SALES PAYMENT & TOTALS
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}orabooks_db_sales 
             SET subtotal = subtotal - %f,
                 grand_total = grand_total - %f,
                 paid_amount = paid_amount - %f
             WHERE id = %d",
            $return_data->subtotal, $return_data->grand_total, $return_data->paid_amount, $original_sales_id
        ));

        // 3. CREATE ACCOUNTING ENTRIES
        $this->create_salesreturn_journal_entry($id, $return_data);

        // 4. Mark Approved
        $wpdb->update($wpdb->prefix . 'orabooks_db_salesreturn', [
            'return_status' => 'Approved',
            'approved_by' => get_current_user_id(),
            'approved_date' => current_time('mysql')
        ], ['id' => $id]);

        wp_send_json_success('Return Approved. Stock restored, Original Sale Qty and Payment adjusted.');
    }

    public function handle_reject_salesreturn() {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if ( ! orabooks_can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $id = intval($_POST['id']);
        if (!$id) wp_send_json_error('Invalid ID');

        $return_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_salesreturn WHERE id=%d", $id));
        if(!$return_data) wp_send_json_error('Return not found');
        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(get_current_blog_id(), $return_data->return_date);
            if (is_wp_error($modification_allowed)) {
                wp_send_json_error($modification_allowed->get_error_message(), 409);
            }
        }

        if ($return_data->return_status == 'Approved') {
            $this->reverse_approved_return($id, $return_data);
        }

        $wpdb->update($wpdb->prefix . 'orabooks_db_salesreturn', [
            'return_status' => 'Rejected',
            'approved_by' => get_current_user_id(),
            'approved_date' => current_time('mysql')
        ], ['id' => $id]);

        wp_send_json_success('Return Rejected. Status updated.');
    }

    private function reverse_approved_return($id, $return_data) {
        global $wpdb;
        $original_sales_id = $return_data->sales_id;

        // 1. REVERSE STOCK (RE-DEDUCT)
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_salesitemsreturn WHERE return_id=%d", $id));
        foreach ($items as $item) {
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_items SET stock = stock - %f WHERE id=%d", $item->sales_qty, $item->item_id));
            
            $wh_res = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_db_warehouseitems WHERE warehouse_id = %d AND item_id = %d", $return_data->warehouse_id, $item->item_id));
            if ($wh_res) {
                $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_warehouseitems SET available_qty = available_qty - %f WHERE id = %d", $item->sales_qty, $wh_res->id));
            }

            // RESTORE ORIGINAL SALES QTY
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}orabooks_db_salesitems 
                 SET sales_qty = sales_qty + %f,
                     total_cost = total_cost + %f
                 WHERE sales_id = %d AND item_id = %d",
                $item->sales_qty, $item->total_cost, $original_sales_id, $item->item_id
            ));
        }

        // 2. RESTORE ORIGINAL SALES TOTALS
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}orabooks_db_sales 
             SET subtotal = subtotal + %f,
                 grand_total = grand_total + %f,
                 paid_amount = paid_amount + %f
             WHERE id = %d",
            $return_data->subtotal, $return_data->grand_total, $return_data->paid_amount, $original_sales_id
        ));

        // 3. Reverse Journal Entries
        $existing_entry_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_ac_journal_entry WHERE source_type = 'SalesReturn' AND source_id = %d", $id));
        if ($existing_entry_id) {
            if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                OBN_Fiscal_Period_Posting_Guard::assert_journal_entry_modifiable_or_fail($existing_entry_id);
            }
            $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_line', ['journal_entry_id' => $existing_entry_id]);
            $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_entry', ['id' => $existing_entry_id]);
        }
    }

    private function create_salesreturn_journal_entry($return_id, $return_data) {
        global $wpdb;

        $store_id    = $return_data->store_id;
        $customer_id = $return_data->customer_id;
        $grand_total = floatval($return_data->grand_total);
        $paid_amount = floatval($return_data->paid_amount);
        $due_amount  = $grand_total - $paid_amount;
        $return_date = $return_data->return_date;

        if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
            $posting_allowed = OBN_Fiscal_Period_Posting_Guard::can_post(get_current_blog_id(), $return_date);
            if (is_wp_error($posting_allowed)) {
                wp_send_json_error($posting_allowed->get_error_message(), 409);
            }
        }

        $currency_code = $wpdb->get_var($wpdb->prepare("SELECT currency_code FROM {$wpdb->prefix}orabooks_db_currency WHERE id = (SELECT currency_id FROM {$wpdb->prefix}orabooks_db_store WHERE id = %d LIMIT 1)", $store_id));
        if (!$currency_code) $currency_code = 'BDT';

        // 1. Find Accounts
        $inventory_account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code = '1100' OR account_name LIKE '%Inventory%') AND status=1 LIMIT 1");
        $ar_account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code = '1200' OR account_name LIKE '%Accounts Receivable%') AND status=1 LIMIT 1");
        $sales_return_account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code = '4100' OR account_name LIKE '%Sales Return%') AND status=1 LIMIT 1");
        if(!$sales_return_account_id) {
            $sales_return_account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code = '4000' OR account_name LIKE '%Sales%') AND status=1 LIMIT 1");
        }

        $payment = $wpdb->get_row($wpdb->prepare("SELECT account_id FROM {$wpdb->prefix}orabooks_db_salespaymentsreturn WHERE return_id = %d LIMIT 1", $return_id));
        $cash_account_id = $payment ? intval($payment->account_id) : 0;
        
        if (!$cash_account_id && $paid_amount > 0) {
             $cash_account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code = '1000' OR account_name LIKE '%Cash%' OR account_name LIKE '%Bank%') AND status=1 LIMIT 1");
        }

        $entry_number = 'JE-SRN-' . str_pad($return_id, 6, '0', STR_PAD_LEFT);

        $entry_data = [
            'store_id'        => $store_id,
            'organization_id' => get_current_blog_id(),
            'entry_number'    => $entry_number,
            'entry_date'      => $return_date,
            'posting_date'    => $return_date,
            'document_date'   => $return_date,
            'reference_no'    => $return_data->return_code,
            'description'     => 'Sales Return Approved Journal Entry ' . $return_data->return_code,
            'source_type'     => 'SalesReturn',
            'source_id'       => $return_id,
            'status'          => 'Posted',
            'currency'        => $currency_code,
            'base_currency'   => $currency_code,
            'total_debit'     => $grand_total,
            'total_credit'    => $grand_total,
            'created_by'      => get_current_user_id(),
            'created_at'      => current_time('mysql'),
            'posted_at'       => current_time('mysql'),
            'locked'          => 1
        ];
        
        $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_entry', $entry_data);
        $journal_entry_id = $wpdb->insert_id;

        if (!$journal_entry_id) return;

        $line_num = 1;

        // DEBIT: Sales Return (Contra-Revenue)
        if ($sales_return_account_id) {
            $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
                'journal_entry_id' => $journal_entry_id,
                'account_id'       => $sales_return_account_id,
                'description'      => 'Sales Return - ' . $return_data->return_code,
                'debit'            => $grand_total,
                'credit'           => 0,
                'debit_amt'        => $grand_total,
                'credit_amt'       => 0,
                'currency'         => $currency_code,
                'exchange_rate'    => 1,
                'amount_base'      => $grand_total,
                'line_number'      => $line_num++,
                'status'           => 1
            ]);
        }

        // CREDIT: Cash/Bank (Refunded Portion)
        if ($paid_amount > 0 && $cash_account_id) {
            $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
                'journal_entry_id' => $journal_entry_id,
                'account_id'       => $cash_account_id,
                'contact_id'       => $customer_id,
                'description'      => 'Cash/Bank Refunded - ' . $return_data->return_code,
                'debit'            => 0,
                'credit'           => $paid_amount,
                'debit_amt'        => 0,
                'credit_amt'       => $paid_amount,
                'currency'         => $currency_code,
                'exchange_rate'    => 1,
                'amount_base'      => $paid_amount,
                'line_number'      => $line_num++,
                'status'           => 1
            ]);
        }

        // CREDIT: Accounts Receivable (Unpaid Portion / Credit Note)
        if ($due_amount > 0 && $ar_account_id) {
            $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
                'journal_entry_id' => $journal_entry_id,
                'account_id'       => $ar_account_id,
                'contact_id'       => $customer_id,
                'description'      => 'Credit Note against Receivable - ' . $return_data->return_code,
                'debit'            => 0,
                'credit'           => $due_amount,
                'debit_amt'        => 0,
                'credit_amt'       => $due_amount,
                'currency'         => $currency_code,
                'exchange_rate'    => 1,
                'amount_base'      => $due_amount,
                'line_number'      => $line_num++,
                'status'           => 1
            ]);
        }
    }

    public function handle_search_salesreturn() {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if ( ! orabooks_can_access_accounting() ) {
            wp_send_json_error('Access denied.');
        }
        global $wpdb;

        $start     = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end       = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $customer  = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $search    = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        $where = "WHERE sr.status = 1";
        if ($start)     $where .= $wpdb->prepare(" AND DATE(sr.return_date) >= %s", $start);
        if ($end)       $where .= $wpdb->prepare(" AND DATE(sr.return_date) <= %s", $end);
        if ($customer)  $where .= $wpdb->prepare(" AND sr.customer_id = %d", $customer);

        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= $wpdb->prepare(" AND (
                sr.return_code LIKE %s 
                OR sr.reference_no LIKE %s 
                OR c.customer_name LIKE %s
            )", $like, $like, $like);
        }

        $sql = "SELECT sr.*, c.customer_name, 
                       (SELECT SUM(sales_qty) FROM {$wpdb->prefix}orabooks_db_salesitemsreturn WHERE return_id = sr.id) as total_qty
                FROM {$wpdb->prefix}orabooks_db_salesreturn sr
                LEFT JOIN {$wpdb->prefix}orabooks_db_customers c ON sr.customer_id = c.id
                $where
                ORDER BY sr.return_date DESC";
        
        $rows = $wpdb->get_results($sql);

        if ($rows) {
            $i = 1;
            foreach ($rows as $r) {
                $status_class = 'bg-gray-100 text-gray-800';
                if ($r->return_status == 'Approved') $status_class = 'bg-green-100 text-green-800';
                if ($r->return_status == 'Rejected') $status_class = 'bg-red-100 text-red-800';

                echo '<tr>
                    <td class="px-6 py-4">' . $i++ . '</td>
                    <td class="px-6 py-4 font-medium text-blue-600">' . esc_html($r->return_code) . '</td>
                    <td class="px-6 py-4">' . esc_html($r->customer_name) . '</td>
                    <td class="px-6 py-4">' . date('d-m-Y', strtotime($r->return_date)) . '</td>
                    <td class="px-6 py-4">' . number_format($r->total_qty, 2) . '</td>
                    <td class="px-6 py-4 font-bold text-right">' . number_format($r->grand_total, 2) . '</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs font-bold ' . $status_class . '">' . esc_html($r->return_status) . '</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex justify-center space-x-2">
                            <a href="?view=sales-return-invoice&id=' . $r->id . '" class="text-gray-600 hover:text-gray-900" title="View/Print"><i class="fa-solid fa-eye"></i></a>
                            ' . ($r->return_status == 'Pending' ? '
                            <a href="?view=edit-sales-return&id=' . $r->id . '" class="text-blue-600 hover:text-blue-900" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                            <button onclick="approveReturn(' . $r->id . ')" class="text-green-600 hover:text-green-900" title="Approve"><i class="fa-solid fa-check"></i></button>
                            <button onclick="rejectReturn(' . $r->id . ')" class="text-orange-600 hover:text-orange-900" title="Reject"><i class="fa-solid fa-xmark"></i></button>
                            ' : '') . '
                            <button onclick="deleteReturn(' . $r->id . ')" class="text-red-600 hover:text-red-900" title="Delete"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </td>
                </tr>';
            }
        } else {
            echo '<tr><td colspan="8" class="px-6 py-4 text-center">No returns found.</td></tr>';
        }
        wp_die();
    }
}
new Frontend_Accounting_SalesReturn();
