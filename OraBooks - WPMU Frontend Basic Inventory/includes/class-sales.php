<?php
if (!defined('ABSPATH')) {
    exit;
}

class Frontend_Inventory_Sales
{

    public function __construct()
    {
        // AJAX handlers
        add_action('wp_ajax_search_sales_items', [$this, 'handle_search_sales_items']);
        add_action('wp_ajax_generate_sales_code', [$this, 'handle_generate_sales_code']);
        add_action('wp_ajax_insert_sale', [$this, 'handle_insert_sale']);
        add_action('wp_ajax_update_sale', [$this, 'handle_update_sale']);
        add_action('wp_ajax_delete_sale', [$this, 'handle_delete_sale']);
        add_action('wp_ajax_search_sales', [$this, 'handle_search_sales']);
        add_action('wp_ajax_get_sales_details', [$this, 'handle_get_sales_details']);
        add_action('wp_ajax_get_payment_type_name', [$this, 'handle_get_payment_type_name']);
        add_action('wp_ajax_update_sales_status', [$this, 'handle_update_sales_status']);

        // Ensure email sends as HTML
        add_filter('wp_mail_content_type', function () {
            return 'text/html';
        });
    }

    public function handle_get_sales_details()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if (!orabooks_can_access_inventory()) {
            wp_send_json_error('Access denied.');
        }
        global $wpdb;
        $id = intval($_GET['id']);
        if (!$id)
            wp_send_json_error('Invalid ID');

        $sale = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_sales WHERE id=%d", $id));
        if (!$sale)
            wp_send_json_error('Sale not found');

        $items = $wpdb->get_results($wpdb->prepare("
            SELECT si.*, i.item_name, i.item_code, i.sku, i.stock 
            FROM {$wpdb->prefix}orabooks_db_salesitems si
            LEFT JOIN {$wpdb->prefix}orabooks_db_items i ON si.item_id = i.id
            WHERE si.sales_id=%d
        ", $id));

        $payments = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}orabooks_db_salespayments 
            WHERE sales_id=%d 
            ORDER BY id ASC
        ", $id));

        wp_send_json_success(['sale' => $sale, 'items' => $items, 'payments' => $payments]);
    }

    public function handle_search_sales_items()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if (!orabooks_can_access_inventory()) {
            wp_send_json_error('Access denied.');
        }
        global $wpdb;
        $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';

        $like = '%' . $wpdb->esc_like($term) . '%';
        $table = $wpdb->prefix . 'orabooks_db_items';

        $sql = "SELECT id, item_name, item_code, sku, sales_price, purchase_price, tax_id, hsn, category_id, brand_id, stock, item_image, sales_account_id 
                FROM $table 
                WHERE (item_name LIKE %s OR sku LIKE %s OR item_code LIKE %s) AND status=1 
                LIMIT 50";

        // If term is empty (e.g. for POS initial load), get all items (limit 50)
        if (empty($term)) {
            $sql = "SELECT id, item_name, item_code, sku, sales_price, purchase_price, tax_id, hsn, category_id, brand_id, stock, item_image, sales_account_id 
                    FROM $table 
                    WHERE status=1 
                    LIMIT 50";
            $rows = $wpdb->get_results($sql);
        } else {
            $rows = $wpdb->get_results($wpdb->prepare($sql, $like, $like, $like));
        }

        // Fetch Default Sales Account (4000) once
        $default_sales_acc = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE account_code = '4000' AND status=1 LIMIT 1");
        if (!$default_sales_acc) {
            $default_sales_acc = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE account_name LIKE '%Sales%' AND status=1 LIMIT 1");
        }

        $data = [];
        foreach ($rows as $r) {
            $tax_percent = 0;
            if (!empty($r->tax_id)) {
                $tax = $wpdb->get_row($wpdb->prepare("SELECT tax FROM {$wpdb->prefix}orabooks_db_tax WHERE id=%d", intval($r->tax_id)));
                $tax_percent = $tax ? floatval($tax->tax) : 0;
            }
            $data[] = [
                'id' => intval($r->id),
                'item_name' => $r->item_name,
                'item_code' => $r->item_code,
                'sku' => $r->sku,
                'price' => floatval($r->sales_price),
                'purchase_price' => floatval($r->purchase_price ?? 0),
                'tax_id' => intval($r->tax_id),
                'tax_percent' => $tax_percent,
                'hsn' => $r->hsn,
                'category_id' => intval($r->category_id),
                'brand_id' => intval($r->brand_id),
                'stock' => floatval($r->stock),
                'item_image' => $r->item_image,
                'sales_account_id' => intval($r->sales_account_id ?: $default_sales_acc)
            ];
        }

        wp_send_json_success($data);
    }

    public function handle_generate_sales_code()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if (!orabooks_can_access_inventory()) {
            wp_send_json_error('Access denied.');
        }
        global $wpdb;

        // Fetch prefix from store table
        $store_prefix = $wpdb->get_var("SELECT sales_init FROM {$wpdb->prefix}orabooks_db_store LIMIT 1");
        if (empty($store_prefix)) {
            $store_prefix = 'SAL-';
        }

        $table = $wpdb->prefix . 'orabooks_db_sales';
        // Check if table exists first to avoid error if called before table creation
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            wp_send_json_success(['code' => $store_prefix . str_pad(1, 6, '0', STR_PAD_LEFT)]);
        }

        $last = $wpdb->get_var("SELECT sales_code FROM $table WHERE sales_code LIKE '$store_prefix%' ORDER BY id DESC LIMIT 1");
        $next = 1;
        if ($last) {
            $last_num = str_replace($store_prefix, '', $last);
            if (is_numeric($last_num)) {
                $next = intval($last_num) + 1;
            }
        }

        $code = $store_prefix . str_pad($next, 6, '0', STR_PAD_LEFT);
        wp_send_json_success(['code' => $code]);
    }

    public function handle_get_payment_type_name()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if (!orabooks_can_access_inventory()) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $payment_type_id = intval($_POST['payment_type_id'] ?? 0);

        if (!$payment_type_id) {
            wp_send_json_error('Payment type ID required');
        }

        $payment_type = $wpdb->get_var($wpdb->prepare(
            "SELECT payment_type FROM {$wpdb->prefix}orabooks_db_paymenttypes WHERE id = %d",
            $payment_type_id
        ));

        if ($payment_type) {
            wp_send_json_success(['name' => $payment_type]);
        } else {
            wp_send_json_error('Payment type not found');
        }
    }

    public function handle_update_sales_status()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        // Debug: Temporarily bypass permission check
        if (!orabooks_can_access_inventory()) {
            error_log('Access denied for user: ' . get_current_user_id());
            wp_send_json_error(['message' => 'Access denied. User ID: ' . get_current_user_id()]);
        }

        global $wpdb;
        $sales_id = intval($_POST['sales_id'] ?? 0);
        $new_status = sanitize_text_field($_POST['sales_status'] ?? '');

        if (!$sales_id) {
            wp_send_json_error(['message' => 'Invalid sale ID.']);
        }

        $allowed = ['Pending', 'Delivered', 'Cancelled'];
        if (!in_array($new_status, $allowed, true)) {
            wp_send_json_error(['message' => 'Invalid status value.']);
        }

        $sale = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}orabooks_db_sales WHERE id = %d",
            $sales_id
        ));

        if (!$sale) {
            wp_send_json_error(['message' => 'Sale not found.']);
        }

        // Validate transition
        $valid_transitions = [
            'Ordered' => ['Pending', 'Cancelled'],
            'Pending' => ['Delivered', 'Cancelled'],
        ];

        $current_status = $sale->sales_status;
        if (!isset($valid_transitions[$current_status]) || !in_array($new_status, $valid_transitions[$current_status], true)) {
            wp_send_json_error(['message' => "Cannot transition from '{$current_status}' to '{$new_status}'."]);
        }

        // Update the status on the sale and its items
        $wpdb->update(
            $wpdb->prefix . 'orabooks_db_sales',
            ['sales_status' => $new_status],
            ['id' => $sales_id]
        );
        $wpdb->update(
            $wpdb->prefix . 'orabooks_db_salesitems',
            ['sales_status' => $new_status],
            ['sales_id' => $sales_id]
        );

        // When marking as Delivered: deduct stock + journal entry
        if ($new_status === 'Delivered') {
            $warehouse_id = intval($sale->warehouse_id);
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}orabooks_db_salesitems WHERE sales_id = %d",
                $sales_id
            ));

            foreach ($items as $item) {
                $qty = floatval($item->sales_qty);
                $item_id = intval($item->item_id);

                // Deduct global stock
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}orabooks_db_items SET stock = stock - %f WHERE id = %d",
                    $qty,
                    $item_id
                ));

                // Deduct warehouse stock
                $wh = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}orabooks_db_warehouseitems WHERE warehouse_id = %d AND item_id = %d",
                    $warehouse_id,
                    $item_id
                ));
                if ($wh) {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$wpdb->prefix}orabooks_db_warehouseitems SET available_qty = available_qty - %f WHERE id = %d",
                        $qty,
                        $wh->id
                    ));
                } else {
                    $wpdb->insert($wpdb->prefix . 'orabooks_db_warehouseitems', [
                        'store_id' => intval($sale->store_id),
                        'warehouse_id' => $warehouse_id,
                        'item_id' => $item_id,
                        'available_qty' => -$qty,
                    ]);
                }
            }

            // Create journal entry
            $this->create_sales_journal_entry($sales_id);
        }

        wp_send_json_success(['message' => "Sale status updated to '{$new_status}' successfully."]);
    }

    private function generate_sales_payment_code()
    {
        global $wpdb;
        $store_prefix = $wpdb->get_var("SELECT sales_payment_init FROM {$wpdb->prefix}orabooks_db_store LIMIT 1");
        if (empty($store_prefix)) {
            $store_prefix = 'SP-';
        }

        $table = $wpdb->prefix . 'orabooks_db_salespayments';
        $last = $wpdb->get_var("SELECT payment_code FROM $table WHERE payment_code LIKE '$store_prefix%' ORDER BY id DESC LIMIT 1");
        $next = 1;
        if ($last) {
            $last_num = str_replace($store_prefix, '', $last);
            if (is_numeric($last_num)) {
                $next = intval($last_num) + 1;
            }
        }

        return $store_prefix . str_pad($next, 6, '0', STR_PAD_LEFT);
    }

    public function handle_insert_sale()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if (!orabooks_can_access_inventory()) {
            wp_send_json_error('Access denied.');
        }
        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_sales';
        $sale_code = $wpdb->get_var($wpdb->prepare("SELECT sales_code FROM $table WHERE id = %d", $sales_id));

        $warehouse_id = intval($_POST['warehouse_id'] ?? 0);
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $created_by = get_current_user_id();
        $system_ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);

        // Prevent Duplicate: Check if sales_code already exists
        $sales_code = sanitize_text_field($_POST['sales_code'] ?? '');
        if (!empty($sales_code)) {
            $existing_sale = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE sales_code = %s", $sales_code));
            if ($existing_sale) {
                wp_send_json_error(['message' => 'Duplicate Entry: Sale code ' . $sales_code . ' already exists.']);
            }
        }

        if (!$warehouse_id)
            wp_send_json_error('Warehouse required');
        // Allow null customer_id for walk-in customers
        if (!$customer_id) {
            $customer_id = null; // Explicitly set to null for walk-in
        }

        $items_json = wp_kses_post($_POST['items_json'] ?? '[]'); // stored as JSON

        $store_id = intval($_POST['store_id'] ?? 1);
        $count_id = $wpdb->get_var($wpdb->prepare("SELECT MAX(count_id) FROM $table WHERE store_id = %d", $store_id));
        $count_id = ($count_id) ? intval($count_id) + 1 : 1;

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

        // Prepare data
        $data = [
            'store_id' => $store_id,
            'count_id' => $count_id,
            'warehouse_id' => $warehouse_id,
            'sales_code' => sanitize_text_field($_POST['sales_code'] ?? ''),
            'reference_no' => sanitize_text_field($_POST['reference_no'] ?? ''),
            'sales_date' => sanitize_text_field($_POST['sales_date'] ?? current_time('Y-m-d')),
            'due_date' => sanitize_text_field($_POST['due_date'] ?? current_time('Y-m-d')),
            'sales_status' => sanitize_text_field($_POST['sales_status'] ?? 'Delivered'),
            'customer_id' => $customer_id,
            'other_charges_input' => $other_charges_input,
            'other_charges_tax_id' => $other_charges_tax_id,
            'other_charges_amt' => $other_charges_amt,
            'discount_to_all_input' => $discount_to_all_input,
            'discount_to_all_type' => $discount_to_all_type,
            'tot_discount_to_all_amt' => $tot_discount_to_all_amt,
            'subtotal' => $subtotal,
            'round_off' => floatval($_POST['round_off'] ?? 0),
            'grand_total' => floatval($_POST['grand_total'] ?? 0),
            'sales_note' => sanitize_textarea_field($_POST['sales_note'] ?? ''),
            'payment_status' => 'Unpaid',
            'paid_amount' => floatval($_POST['payment_amount'] ?? 0),
            'created_date' => current_time('Y-m-d'),
            'created_time' => current_time('H:i:s'),
            'created_by' => $created_by,
            'system_ip' => $system_ip,
            'system_name' => 'System', // php_uname('n') might leak server info
            'company_id' => 1,
            'pos' => intval($_POST['pos'] ?? 0),
            'status' => 1,
            'invoice_terms' => $_POST['items_json'] ?? '[]',
        ];

        // Determine payment status
        if ($data['paid_amount'] > 0) {
            if ($data['paid_amount'] >= $data['grand_total']) {
                $data['payment_status'] = 'Paid';
            } else {
                $data['payment_status'] = 'Partial';
            }
        } else {
            $data['payment_status'] = 'Due';
        }

        $inserted = $wpdb->insert($table, $data);

        if (!$inserted) {
            wp_send_json_error('DB insert failed: ' . $wpdb->last_error);
        }

        $last_id = $wpdb->insert_id;

        // Insert Payment
        $payinserted = true;
        if (floatval($_POST['payment_amount']) > 0) {
            $payment_type_id = intval($_POST['payment_type_id'] ?? 0);
            $account_id = intval($_POST['account_id'] ?? 0);

            // Fetch payment type name to check if it's "Bank"
            $payment_type_name = $wpdb->get_var($wpdb->prepare("SELECT payment_type FROM {$wpdb->prefix}orabooks_db_paymenttypes WHERE id = %d", $payment_type_id));

            // Auto-assign account based on payment type
            if (!$account_id) {
                if (strtolower($payment_type_name) === 'bank') {
                    // Get first bank account
                    $account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_accounts WHERE (account_code LIKE '1000%' OR account_name LIKE '%bank%') AND (status=1 OR status IS NULL) AND (delete_bit=0 OR delete_bit IS NULL) LIMIT 1");
                    if (!$account_id) {
                        $account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code LIKE '1000%' OR account_name LIKE '%bank%') AND status=1 LIMIT 1");
                    }
                } else {
                    // Get cash account for non-bank payments
                    $account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_accounts WHERE (account_code LIKE '1000%' OR account_name LIKE '%cash%') AND (status=1 OR status IS NULL) AND (delete_bit=0 OR delete_bit IS NULL) LIMIT 1");
                    if (!$account_id) {
                        $account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code LIKE '1000%' OR account_name LIKE '%cash%') AND status=1 LIMIT 1");
                    }
                }
            }

            $payment_code = $this->generate_sales_payment_code();

            $paydata = [
                'store_id' => intval($_POST['store_id'] ?? 1),
                'sales_id' => $last_id,
                'customer_id' => $customer_id,
                'payment_code' => $payment_code,
                'short_code' => $payment_code,
                'payment_date' => $data['sales_date'],
                'payment_type' => $payment_type_id,
                'payment' => floatval($_POST['payment_amount'] ?? 0),
                'payment_note' => sanitize_textarea_field($_POST['payment_note'] ?? ''),
                'account_id' => $account_id,
                'mobile_number' => sanitize_text_field($_POST['bkash_number'] ?? $_POST['nagad_number'] ?? ''),
                'bank_name' => sanitize_text_field($_POST['bank_name'] ?? ''),
                'cheque_number' => sanitize_text_field($_POST['check_number'] ?? ''),
                'change_return' => 0,
                'system_ip' => $system_ip,
                'system_name' => 'System',
                'created_time' => current_time('H:i:s'),
                'created_date' => current_time('Y-m-d'),
                'created_by' => $created_by,
                'status' => 1
            ];
            $wpdb->insert($wpdb->prefix . 'orabooks_db_salespayments', $paydata);
            $salespayment_id = $wpdb->insert_id;

            // Insert into Customer Payments for Reporting
            $wpdb->insert($wpdb->prefix . 'orabooks_db_customer_payments', [
                'salespayment_id' => $salespayment_id,
                'customer_id'     => $customer_id,
                'payment_date'   => $data['sales_date'],
                'payment_type'   => $payment_type_name,
                'payment'        => $paydata['payment'],
                'payment_note'   => $paydata['payment_note'],
                'system_ip'      => $system_ip,
                'system_name'    => 'System',
                'created_time'   => current_time('H:i:s'),
                'created_date'   => current_time('Y-m-d'),
                'created_by'     => $created_by,
                'status'         => 1,
                'account_id'     => $account_id,
                'reference_no'   => $data['sales_code']
            ]);

            // Get Customer Account from orabooks_ac_accounts
            $customer_account_id = null;
            if ($customer_id) {
                $customer_account_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_ac_accounts WHERE customer_id = %d", $customer_id));
            }

            // Insert into orabooks_ac_transactions only if customer account exists
            if ($customer_account_id) {
                $wpdb->insert($wpdb->prefix . 'orabooks_ac_transactions', [
                    'store_id' => $data['store_id'],
                    'payment_code' => $payment_code,
                    'transaction_date' => $data['sales_date'],
                    'transaction_type' => 'Sales',
                    'debit_account_id' => $paydata['account_id'], // Bank/Cash (Debit)
                    'credit_account_id' => $customer_account_id,   // Customer (Credit)
                    'credit_amt' => $paydata['payment'],
                    'note' => $paydata['payment_note'],
                    'created_by' => $created_by,
                    'created_date' => current_time('Y-m-d'),
                    'ref_salespayments_id' => $salespayment_id,
                    'customer_id' => $customer_id,
                    'short_code' => $payment_code,
                ]);
            }

            // Update Account Balances
            if ($paydata['account_id'] > 0) {
                $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_ac_accounts SET balance = balance + %f WHERE id = %d", $paydata['payment'], $paydata['account_id']));
            }
            if ($customer_account_id > 0) {
                $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_ac_accounts SET balance = balance - %f WHERE id = %d", $paydata['payment'], $customer_account_id));
            }
        }

        // Insert Items
        $items = json_decode(stripslashes($_POST['items_json']), true);
        $items_inserted = true;
        if (is_array($items) && count($items) > 0) {
            foreach ($items as $item) {
                // Ensure item_id exists
                if (empty($item['item_id']))
                    continue;

                $item_data = [
                    'store_id' => $data['store_id'],
                    'sales_id' => $last_id,
                    'sales_status' => $data['sales_status'],
                    'item_id' => intval($item['item_id']),
                    'account_id' => intval($item['account_id']),
                    'description' => sanitize_text_field($item['name'] ?? ''),
                    'sales_qty' => floatval($item['qty'] ?? 0),
                    'price_per_unit' => floatval($item['unit_price'] ?? 0),
                    'tax_type' => 'Percentage', // Default
                    'tax_id' => 0, // Need to handle tax_id if possible
                    'tax_amt' => floatval($item['tax_amt'] ?? 0),
                    'discount_type' => 'Fixed', // Default
                    'discount_input' => floatval($item['discount'] ?? 0),
                    'discount_amt' => floatval($item['discount'] ?? 0),
                    'unit_total_cost' => floatval($item['unit_price'] ?? 0) * floatval($item['qty'] ?? 0),
                    'total_cost' => floatval($item['total'] ?? 0),
                    'status' => 1,
                    'seller_points' => 0,
                    'purchase_price' => $wpdb->get_var($wpdb->prepare("SELECT purchase_price FROM {$wpdb->prefix}orabooks_db_items WHERE id = %d", intval($item['item_id'])))
                ];
                $res = $wpdb->insert($wpdb->prefix . 'orabooks_db_salesitems', $item_data);
                if (!$res)
                    $items_inserted = false;

                // Only update stock and warehouse stock if status is Delivered
                if ($data['sales_status'] === 'Delivered') {
                    // Update stock? Assuming simple stock decrement
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$wpdb->prefix}orabooks_db_items SET stock = stock - %f WHERE id = %d",
                        $item_data['sales_qty'],
                        $item_data['item_id']
                    ));

                    // Update warehouse stock (Deduct)
                    $wh_res = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_db_warehouseitems WHERE warehouse_id = %d AND item_id = %d", $warehouse_id, $item_data['item_id']));
                    if ($wh_res) {
                        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_warehouseitems SET available_qty = available_qty - %f WHERE id = %d", $item_data['sales_qty'], $wh_res->id));
                    } else {
                        $wpdb->insert($wpdb->prefix . 'orabooks_db_warehouseitems', [
                            'store_id' => $data['store_id'],
                            'warehouse_id' => $warehouse_id,
                            'item_id' => $item_data['item_id'],
                            'available_qty' => -($item_data['sales_qty'])
                        ]);
                    }
                }
            }
        }

        $msg = 'Sales saved successfully';
        if ($inserted) {
            // Only create journal entry if status is Delivered
            if ($data['sales_status'] === 'Delivered') {
                $this->create_sales_journal_entry($last_id);
            }
            if ($this->send_invoice_email($last_id)) {
                $msg .= ' and invoice emailed';
            }
        }

        wp_send_json_success(['message' => $msg, 'sale_id' => $last_id]);
    }

    /**
     * Create IFRS Journal Entry for Sales with Proper VAT & Discount Handling
     */
    private function create_sales_journal_entry($sales_id)
    {
        global $wpdb;

        // Fetch Sale Details
        $sale = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_sales WHERE id = %d", $sales_id));
        if (!$sale)
            return;

        $store_id = $sale->store_id;
        $customer_id = $sale->customer_id;
        $grand_total = floatval($sale->grand_total);
        $paid_amount = floatval($sale->paid_amount);
        $due_amount = $grand_total - $paid_amount;
        $sales_date = $sale->sales_date;
        $subtotal = floatval($sale->subtotal);
        $total_discount = floatval($sale->tot_discount_to_all_amt);
        $other_charges = floatval($sale->other_charges_input);

        // Fetch Currency
        $currency_code = $wpdb->get_var($wpdb->prepare("SELECT currency_code FROM {$wpdb->prefix}orabooks_db_currency WHERE id = (SELECT currency_id FROM {$wpdb->prefix}orabooks_db_store WHERE id = %d LIMIT 1)", $store_id));
        if (!$currency_code)
            $currency_code = 'BDT';

        // 1. Find Required Accounts
        $accounts = $this->get_journal_accounts($store_id);

        // 2. Calculate VAT and Discount amounts
        $tax_amounts = $this->calculate_sale_vat($sales_id);
        $total_vat = array_sum($tax_amounts);

        // 3. Generate Entry Number
        $entry_number = 'JE-SAL-' . str_pad($sales_id, 6, '0', STR_PAD_LEFT);

        // 4. Check if journal entry already exists and delete if found
        $existing_entry_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_ac_journal_entry WHERE source_type = 'Sales' AND source_id = %d", $sales_id));
        if ($existing_entry_id) {
            $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_line', ['journal_entry_id' => $existing_entry_id]);
            $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_entry', ['id' => $existing_entry_id]);
        }

        // 5. Insert Main Journal Entry
        $entry_data = [
            'organization_id' => $store_id,
            'entry_number' => $entry_number,
            'entry_date' => $sales_date,
            'posting_date' => $sales_date,
            'document_date' => $sales_date,
            'description' => 'Sales Journal Entry for ' . $sale->sales_code . ' (' . $sale->payment_status . ')',
            'source_type' => 'Sales',
            'source_id' => $sales_id,
            'status' => 'Posted',
            'total_debit' => $grand_total,
            'total_credit' => $grand_total,
            'currency' => $currency_code,
            'base_currency' => $currency_code,
            'created_by' => $sale->created_by,
            'created_at' => current_time('mysql'),
            'posted_at' => current_time('mysql'),
            'locked' => 1
        ];

        $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_entry', $entry_data);
        $journal_entry_id = $wpdb->insert_id;

        if (!$journal_entry_id)
            return;

        // 6. Insert Journal Lines
        $line_num = 1;
        $total_credit = 0;
        $total_debit = 0;

        // --- CREDIT SIDE ---

        // Sales Revenue (Net Sales - Excluding VAT)
        $net_sales_amount = $subtotal - $total_discount;
        if ($net_sales_amount > 0) {
            $items = $wpdb->get_results($wpdb->prepare("SELECT account_id, SUM(total_cost - tax_amt) as total_rev FROM {$wpdb->prefix}orabooks_db_salesitems WHERE sales_id = %d GROUP BY account_id", $sales_id));

            if ($items) {
                foreach ($items as $item) {
                    $acc_id = intval($item->account_id) ?: $accounts['sales_revenue'];
                    if ($acc_id) {
                        $item_net_amount = $item->total_rev - ($item->total_rev * $total_discount / $subtotal);
                        $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
                            'journal_entry_id' => $journal_entry_id,
                            'account_id' => $acc_id,
                            'description' => 'Sales Revenue - ' . $sale->sales_code,
                            'debit' => 0,
                            'credit' => $item_net_amount,
                            'debit_amt' => 0,
                            'credit_amt' => $item_net_amount,
                            'currency' => $currency_code,
                            'exchange_rate' => 1,
                            'amount_base' => $item_net_amount,
                            'line_number' => $line_num++,
                            'status' => 1
                        ]);
                        $total_credit += $item_net_amount;
                    }
                }
            }
        }

        // Other Charges Revenue (if any)
        if ($other_charges > 0 && $accounts['other_revenue']) {
            $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
                'journal_entry_id' => $journal_entry_id,
                'account_id' => $accounts['other_revenue'],
                'description' => 'Other Charges - ' . $sale->sales_code,
                'debit' => 0,
                'credit' => $other_charges,
                'debit_amt' => 0,
                'credit_amt' => $other_charges,
                'currency' => $currency_code,
                'exchange_rate' => 1,
                'amount_base' => $other_charges,
                'line_number' => $line_num++,
                'status' => 1
            ]);
            $total_credit += $other_charges;
        }

        // VAT Liability (Credit)
        if ($total_vat > 0 && $accounts['vat_payable']) {
            $vat_acc_name = $wpdb->get_var($wpdb->prepare("SELECT account_name FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE id = %d", $accounts['vat_payable']));
            $vat_acc_code = $wpdb->get_var($wpdb->prepare("SELECT account_code FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE id = %d", $accounts['vat_payable']));
            
            $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
                'journal_entry_id' => $journal_entry_id,
                'account_id' => $accounts['vat_payable'],
                'description' => $vat_acc_name . ($vat_acc_code ? ' - ' . $vat_acc_code : '') . ' - ' . $sale->sales_code,
                'debit' => 0,
                'credit' => $total_vat,
                'debit_amt' => 0,
                'credit_amt' => $total_vat,
                'currency' => $currency_code,
                'exchange_rate' => 1,
                'amount_base' => $total_vat,
                'line_number' => $line_num++,
                'status' => 1
            ]);
            $total_credit += $total_vat;
        }

        // --- DEBIT SIDE ---

        // Cash/Bank (Paid Portion)
        if ($paid_amount > 0) {
            $payment = $wpdb->get_row($wpdb->prepare("SELECT account_id FROM {$wpdb->prefix}orabooks_db_salespayments WHERE sales_id = %d ORDER BY id DESC LIMIT 1", $sales_id));
            $cash_account_id = $payment ? intval($payment->account_id) : $accounts['cash_bank'];

            if ($cash_account_id) {
                $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
                    'journal_entry_id' => $journal_entry_id,
                    'account_id' => $cash_account_id,
                    'contact_id' => $customer_id,
                    'description' => 'Cash/Bank Received - ' . $sale->sales_code . ' (' . $sale->payment_status . ')',
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
                $total_debit += $paid_amount;
            }
        }

        // Accounts Receivable (Due Portion)
        if ($due_amount > 0 && $accounts['accounts_receivable']) {
            $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
                'journal_entry_id' => $journal_entry_id,
                'account_id' => $accounts['accounts_receivable'],
                'contact_id' => $customer_id,
                'description' => 'Accounts Receivable - ' . $sale->sales_code . ' (' . $sale->payment_status . ')',
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
            $total_debit += $due_amount;
        }

        // Discount Allowed (Debit - Expense)
        if ($total_discount > 0 && $accounts['discount_allowed']) {
            $disc_acc_name = $wpdb->get_var($wpdb->prepare("SELECT account_name FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE id = %d", $accounts['discount_allowed']));
            $disc_acc_code = $wpdb->get_var($wpdb->prepare("SELECT account_code FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE id = %d", $accounts['discount_allowed']));
            
            $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
                'journal_entry_id' => $journal_entry_id,
                'account_id' => $accounts['discount_allowed'],
                'description' => $disc_acc_name . ($disc_acc_code ? ' - ' . $disc_acc_code : '') . ' - ' . $sale->sales_code,
                'debit' => $total_discount,
                'credit' => 0,
                'debit_amt' => $total_discount,
                'credit_amt' => 0,
                'currency' => $currency_code,
                'exchange_rate' => 1,
                'amount_base' => $total_discount,
                'line_number' => $line_num++,
                'status' => 1
            ]);
            $total_debit += $total_discount;
        }

        // Update journal entry totals
        $wpdb->update($wpdb->prefix . 'orabooks_ac_journal_entry', [
            'total_debit' => $total_debit,
            'total_credit' => $total_credit
        ], ['id' => $journal_entry_id]);
    }

    /**
     * Get required accounts for journal entries
     */
    private function get_journal_accounts($store_id)
    {
        global $wpdb;

        return [
            'sales_revenue' => $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE account_code = '4000' AND status=1 LIMIT 1") ?: $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE ((account_name LIKE '%Sales%' AND account_name NOT LIKE '%Tax%') OR account_name LIKE '%Revenue%') AND status=1 LIMIT 1"),
            'accounts_receivable' => $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code = '1200' OR account_name LIKE '%Accounts Receivable%' OR account_name LIKE '%Debtors%') AND status=1 LIMIT 1"),
            'cash_bank' => $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code = '1000' OR account_name LIKE '%Cash%' OR account_name LIKE '%Bank%') AND status=1 LIMIT 1"),
            'vat_payable' => $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE account_name = 'VAT TAX Payable' AND status=1 LIMIT 1") ?: $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code = '2100' OR account_name LIKE '%VAT%' OR account_name LIKE '%Tax Payable%') AND status=1 LIMIT 1"),
            'discount_allowed' => $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE account_name = 'Sales Discount' AND status=1 LIMIT 1") ?: $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code = '5000' OR account_name LIKE '%Discount%' OR account_name LIKE '%Sales Discount%') AND status=1 LIMIT 1"),
            'other_revenue' => $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code = '4100' OR account_name LIKE '%Other Income%' OR account_name LIKE '%Other Revenue%') AND status=1 LIMIT 1")
        ];
    }

    /**
     * Calculate VAT amounts for sale
     */
    private function calculate_sale_vat($sales_id)
    {
        global $wpdb;

        $vat_amounts = [];

        // Get VAT from sales items
        $items = $wpdb->get_results($wpdb->prepare("SELECT tax_amt FROM {$wpdb->prefix}orabooks_db_salesitems WHERE sales_id = %d AND tax_amt > 0", $sales_id));
        foreach ($items as $item) {
            $vat_amounts[] = floatval($item->tax_amt);
        }

        // Get VAT from other charges
        $sale = $wpdb->get_row($wpdb->prepare("SELECT other_charges_input, other_charges_tax_id FROM {$wpdb->prefix}orabooks_db_sales WHERE id = %d", $sales_id));
        if ($sale && $sale->other_charges_tax_id > 0) {
            $tax_percent = $wpdb->get_var($wpdb->prepare("SELECT tax FROM {$wpdb->prefix}orabooks_db_tax WHERE id = %d", $sale->other_charges_tax_id));
            if ($tax_percent) {
                $vat_amounts[] = floatval($sale->other_charges_input) * floatval($tax_percent) / 100;
            }
        }

        return $vat_amounts;
    }

    public function handle_update_sale()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if (!orabooks_can_access_inventory()) {
            wp_send_json_error('Access denied.');
        }
        // Removed auth check as requested

        global $wpdb;
        $sales_id = intval($_POST['sales_id'] ?? 0);
        if (!$sales_id)
            wp_send_json_error('Sales ID required');

        $table = $wpdb->prefix . 'orabooks_db_sales';
        $sale_code = $wpdb->get_var($wpdb->prepare("SELECT sales_code FROM $table WHERE id = %d", $sales_id));

        $warehouse_id = intval($_POST['warehouse_id'] ?? 0);
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $system_ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);

        if (!$warehouse_id)
            wp_send_json_error('Warehouse required');
        // Allow null customer_id for walk-in customers
        if (!$customer_id) {
            $customer_id = null; // Explicitly set to null for walk-in
        }

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

        // Prepare data
        $data = [
            'warehouse_id' => $warehouse_id,
            'reference_no' => sanitize_text_field($_POST['reference_no'] ?? ''),
            'sales_date' => sanitize_text_field($_POST['sales_date'] ?? current_time('Y-m-d')),
            'due_date' => sanitize_text_field($_POST['due_date'] ?? current_time('Y-m-d')),
            'sales_status' => sanitize_text_field($_POST['sales_status'] ?? 'Delivered'),
            'customer_id' => $customer_id,
            'other_charges_input' => $other_charges_input,
            'other_charges_tax_id' => $other_charges_tax_id,
            'other_charges_amt' => $other_charges_amt,
            'discount_to_all_input' => $discount_to_all_input,
            'discount_to_all_type' => $discount_to_all_type,
            'tot_discount_to_all_amt' => $tot_discount_to_all_amt,
            'subtotal' => $subtotal,
            'round_off' => floatval($_POST['round_off'] ?? 0),
            'grand_total' => floatval($_POST['grand_total'] ?? 0),
            'sales_note' => sanitize_textarea_field($_POST['sales_note'] ?? ''),
            'paid_amount' => floatval($_POST['payment_amount'] ?? 0),
            'invoice_terms' => $_POST['items_json'] ?? '[]',
        ];

        // Determine payment status
        if ($data['paid_amount'] > 0) {
            if ($data['paid_amount'] >= $data['grand_total']) {
                $data['payment_status'] = 'Paid';
            } else {
                $data['payment_status'] = 'Partial';
            }
        } else {
            $data['payment_status'] = 'Due';
        }

        $wpdb->update($table, $data, ['id' => $sales_id]);

        // Handle Items: Delete old ones and add new ones (Simplified logic)
        // First restore stock for old items
        $old_sale = $wpdb->get_row($wpdb->prepare("SELECT warehouse_id FROM {$wpdb->prefix}orabooks_db_sales WHERE id = %d", $sales_id));
        $old_items = $wpdb->get_results($wpdb->prepare("SELECT item_id, sales_qty FROM {$wpdb->prefix}orabooks_db_salesitems WHERE sales_id = %d", $sales_id));
        foreach ($old_items as $oi) {
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_items SET stock = stock + %f WHERE id = %d", $oi->sales_qty, $oi->item_id));

            // Restore warehouse stock
            if ($old_sale) {
                $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_warehouseitems SET available_qty = available_qty + %f WHERE warehouse_id = %d AND item_id = %d", $oi->sales_qty, $old_sale->warehouse_id, $oi->item_id));
            }
        }
        $wpdb->delete($wpdb->prefix . 'orabooks_db_salesitems', ['sales_id' => $sales_id]);

        // Insert New Items
        $items = json_decode(stripslashes($_POST['items_json']), true);
        if (is_array($items)) {
            foreach ($items as $item) {
                if (empty($item['item_id']))
                    continue;
                $item_data = [
                    'store_id' => 1,
                    'sales_id' => $sales_id,
                    'sales_status' => 'Final',
                    'item_id' => intval($item['item_id']),
                    'account_id' => intval($item['account_id']),
                    'description' => sanitize_text_field($item['name'] ?? ''),
                    'sales_qty' => floatval($item['qty'] ?? 0),
                    'price_per_unit' => floatval($item['unit_price'] ?? 0),
                    'tax_type' => 'Percentage',
                    'tax_amt' => floatval($item['tax_amt'] ?? 0),
                    'discount_input' => floatval($item['discount'] ?? 0),
                    'discount_amt' => floatval($item['discount'] ?? 0),
                    'unit_total_cost' => floatval($item['unit_price'] ?? 0) * floatval($item['qty'] ?? 0),
                    'total_cost' => floatval($item['total'] ?? 0),
                    'status' => 1,
                    'purchase_price' => $wpdb->get_var($wpdb->prepare("SELECT purchase_price FROM {$wpdb->prefix}orabooks_db_items WHERE id = %d", intval($item['item_id'])))
                ];
                $wpdb->insert($wpdb->prefix . 'orabooks_db_salesitems', $item_data);

                // Only update stock and warehouse stock if status is Delivered
                if ($data['sales_status'] === 'Delivered') {
                    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_items SET stock = stock - %f WHERE id = %d", $item_data['sales_qty'], $item_data['item_id']));

                    // Update warehouse stock (Deduct)
                    $wh_res = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_db_warehouseitems WHERE warehouse_id = %d AND item_id = %d", $warehouse_id, $item_data['item_id']));
                    if ($wh_res) {
                        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_warehouseitems SET available_qty = available_qty - %f WHERE id = %d", $item_data['sales_qty'], $wh_res->id));
                    } else {
                        $wpdb->insert($wpdb->prefix . 'orabooks_db_warehouseitems', [
                            'store_id' => 1,
                            'warehouse_id' => $warehouse_id,
                            'item_id' => $item_data['item_id'],
                            'available_qty' => -($item_data['sales_qty'])
                        ]);
                    }
                }
            }
        }

        if (floatval($_POST['payment_amount']) > 0) {
            $payment_type_id = intval($_POST['payment_type_id'] ?? 0);
            $account_id = intval($_POST['account_id'] ?? 0);

            // Fetch payment type name to check if it's "Bank"
            $payment_type_name = $wpdb->get_var($wpdb->prepare("SELECT payment_type FROM {$wpdb->prefix}orabooks_db_paymenttypes WHERE id = %d", $payment_type_id));

            // Revert old payment balances
            $old_transactions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_ac_transactions WHERE ref_salespayments_id IN (SELECT id FROM {$wpdb->prefix}orabooks_db_salespayments WHERE sales_id = %d)", $sales_id));
            foreach ($old_transactions as $ot) {
                if ($ot->debit_account_id > 0) {
                    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_ac_accounts SET balance = balance - %f WHERE id = %d", $ot->debit_amt, $ot->debit_account_id));
                }
                if ($ot->credit_account_id > 0) {
                    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_ac_accounts SET balance = balance + %f WHERE id = %d", $ot->credit_amt, $ot->credit_account_id));
                }
            }

            $payment_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_db_salespayments WHERE sales_id = %d", $sales_id));
            if (!empty($payment_ids)) {
                $wpdb->query("DELETE FROM {$wpdb->prefix}orabooks_ac_transactions WHERE ref_salespayments_id IN (" . implode(',', array_map('intval', $payment_ids)) . ")");
                $wpdb->query("DELETE FROM {$wpdb->prefix}orabooks_db_customer_payments WHERE salespayment_id IN (" . implode(',', array_map('intval', $payment_ids)) . ")");
            }
            $wpdb->delete($wpdb->prefix . 'orabooks_db_salespayments', ['sales_id' => $sales_id]);

            // Auto-assign account based on payment type
            if (!$account_id) {
                if (strtolower($payment_type_name) === 'bank') {
                    // Get first bank account
                    $account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_accounts WHERE (account_code LIKE '1000%' OR account_name LIKE '%bank%') AND (status=1 OR status IS NULL) AND (delete_bit=0 OR delete_bit IS NULL) LIMIT 1");
                    if (!$account_id) {
                        $account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code LIKE '1000%' OR account_name LIKE '%bank%') AND status=1 LIMIT 1");
                    }
                } else {
                    // Get cash account for non-bank payments
                    $account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_accounts WHERE (account_code LIKE '1000%' OR account_name LIKE '%cash%') AND (status=1 OR status IS NULL) AND (delete_bit=0 OR delete_bit IS NULL) LIMIT 1");
                    if (!$account_id) {
                        $account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code LIKE '1000%' OR account_name LIKE '%cash%') AND status=1 LIMIT 1");
                    }
                }
            }

            $payment_code = $this->generate_sales_payment_code();

                $paydata = [
                    'store_id' => intval($_POST['store_id'] ?? 1),
                    'sales_id' => $sales_id,
                    'customer_id' => $customer_id,
                    'payment_code' => $payment_code,
                    'short_code' => $payment_code,
                    'payment_date' => $data['sales_date'],
                    'payment_type' => $payment_type_id,
                    'payment' => floatval($_POST['payment_amount'] ?? 0),
                    'payment_note' => sanitize_textarea_field($_POST['payment_note'] ?? ''),
                    'account_id' => $account_id,
                    'change_return' => 0,
                    'system_ip' => $system_ip,
                    'system_name' => 'System',
                    'created_time' => current_time('H:i:s'),
                    'created_date' => current_time('Y-m-d'),
                    'created_by' => get_current_user_id(),
                    'status' => 1
                ];
                $wpdb->insert($wpdb->prefix . 'orabooks_db_salespayments', $paydata);
                $salespayment_id = $wpdb->insert_id;

                // Insert into Customer Payments for Reporting
                $wpdb->insert($wpdb->prefix . 'orabooks_db_customer_payments', [
                    'salespayment_id' => $salespayment_id,
                    'customer_id'     => $customer_id,
                    'payment_date'   => $data['sales_date'],
                    'payment_type'   => $payment_type_name,
                    'payment'        => $paydata['payment'],
                    'payment_note'   => $paydata['payment_note'],
                    'system_ip'      => $system_ip,
                    'system_name'    => 'System',
                    'created_time'   => current_time('H:i:s'),
                    'created_date'   => current_time('Y-m-d'),
                    'created_by'     => get_current_user_id(),
                    'status'         => 1,
                    'account_id'     => $account_id,
                    'reference_no'   => $sale_code ?? '' // Reference from original sale code if possible, or fetch it
                ]);

                // Get Customer Account from orabooks_ac_accounts
                $customer_account_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_ac_accounts WHERE customer_id = %d", $customer_id));

                // Insert into orabooks_ac_transactions
                $wpdb->insert($wpdb->prefix . 'orabooks_ac_transactions', [
                    'store_id' => intval($_POST['store_id'] ?? 1),
                    'payment_code' => $payment_code,
                    'transaction_date' => $data['sales_date'],
                    'transaction_type' => 'Sales',
                    'debit_account_id' => $paydata['account_id'],
                    'credit_account_id' => $customer_account_id,
                    // 'debit_amt'         => $paydata['payment'],
                    'credit_amt' => $paydata['payment'],
                    'note' => $paydata['payment_note'],
                    'created_by' => get_current_user_id(),
                    'created_date' => current_time('Y-m-d'),
                    'ref_salespayments_id' => $salespayment_id,
                    'customer_id' => $customer_id,
                    'short_code' => $payment_code,
                ]);

                // Update Account Balances
                if ($paydata['account_id'] > 0) {
                    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_ac_accounts SET balance = balance + %f WHERE id = %d", $paydata['payment'], $paydata['account_id']));
                }
                if ($customer_account_id > 0) {
                    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_ac_accounts SET balance = balance - %f WHERE id = %d", $paydata['payment'], $customer_account_id));
                }
            }

        // Only create journal entry if status is Delivered
        if ($data['sales_status'] === 'Delivered') {
            $this->create_sales_journal_entry($sales_id);
        }

        wp_send_json_success(['message' => 'Sales updated successfully', 'sale_id' => $sales_id]);
    }

    /**
     * Get status badge HTML for sales status
     */
    private function get_status_badge($status)
    {
        $status = ucfirst(strtolower($status));

        switch ($status) {
            case 'Ordered':
                return '<span class="px-2.5 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-blue-100 text-blue-800">
                    <i class="fa-solid fa-clipboard-list mr-1"></i> Ordered
                </span>';
            case 'Pending':
                return '<span class="px-2.5 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-yellow-100 text-yellow-800">
                    <i class="fa-solid fa-clock mr-1"></i> Pending
                </span>';
            case 'Delivered':
                return '<span class="px-2.5 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-green-100 text-green-800">
                    <i class="fa-solid fa-check-circle mr-1"></i> Delivered
                </span>';
            default:
                return '<span class="px-2.5 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-gray-100 text-gray-800">
                    <i class="fa-solid fa-question-circle mr-1"></i> ' . esc_html($status) . '
                </span>';
        }
    }

    public function handle_search_sales()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if (!orabooks_can_access_inventory()) {
            wp_send_json_error('Access denied.');
        }
        global $wpdb;

        $start = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $customer = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $user = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $warehouse = isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0;

        $where = "WHERE s.status = 1";
        if ($start)
            $where .= $wpdb->prepare(" AND DATE(s.sales_date) >= %s", $start);
        if ($end)
            $where .= $wpdb->prepare(" AND DATE(s.sales_date) <= %s", $end);
        if ($customer)
            $where .= $wpdb->prepare(" AND s.customer_id = %d", $customer);
        if ($user)
            $where .= $wpdb->prepare(" AND s.created_by = %d", $user);
        if ($warehouse)
            $where .= $wpdb->prepare(" AND s.warehouse_id = %d", $warehouse);

        // Generic Search
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= $wpdb->prepare(" AND (
                s.sales_code LIKE %s 
                OR s.reference_no LIKE %s 
                OR c.customer_name LIKE %s
                OR w.warehouse_name LIKE %s
                OR u.display_name LIKE %s
                OR s.sales_date LIKE %s
                OR CAST(s.grand_total AS CHAR) LIKE %s
                OR CAST(s.paid_amount AS CHAR) LIKE %s
                OR CAST((s.grand_total - s.paid_amount) AS CHAR) LIKE %s
            )",
                $like,
                $like,
                $like,
                $like,
                $like,
                $like,
                $like,
                $like,
                $like
            );
        }

        $sql = "SELECT s.*, 
                       c.customer_name, 
                       c.customer_code,
                       (SELECT COALESCE(SUM(si.sales_qty), 0) 
                        FROM {$wpdb->prefix}orabooks_db_salesitems si 
                        WHERE si.sales_id = s.id) as total_qty
                FROM {$wpdb->prefix}orabooks_db_sales s
                LEFT JOIN {$wpdb->prefix}orabooks_db_customers c ON s.customer_id = c.id
                $where
                ORDER BY s.sales_date DESC";

        $rows = $wpdb->get_results($sql);

        if ($rows) {
            $i = 1;
            foreach ($rows as $r) {
                $due = floatval($r->grand_total) - floatval($r->paid_amount);
                $date = date('d-m-Y', strtotime($r->sales_date));

                echo '<tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . $i . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">' . esc_html($r->sales_code) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . $date . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . esc_html($r->customer_name ?? '-') . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-gray-900">' . number_format($r->total_qty ?? 0, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        ' . $this->get_status_badge($r->sales_status ?? 'Ordered') . '
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900 grand-total">' . number_format($r->grand_total, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-600 paid-amount">' . number_format($r->paid_amount, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600 due-amount">' . number_format($due, 2) . '</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                        <div class="flex justify-center items-center space-x-2">
                            ';
                if (Frontend_Inventory_Permissions::has_view_permission('sales-order-list') && $r->sales_status !== 'Delivered') {
                    echo '<a href="?view=sales-order-list" class="w-8 h-8 inline-flex items-center justify-center text-blue-600 hover:text-blue-900 bg-blue-50 rounded-lg transition-colors" title="View Sales Orders">
                                    <i class="fa-solid fa-clipboard-list"></i>
                                </a>';
                }
                if (Frontend_Inventory_Permissions::has_view_permission('sales-pending-delivery') && $r->sales_status !== 'Delivered') {
                    echo '<a href="?view=sales-pending-delivery" class="w-8 h-8 inline-flex items-center justify-center text-yellow-600 hover:text-yellow-900 bg-yellow-50 rounded-lg transition-colors" title="View Pending Delivery">
                                    <i class="fa-solid fa-clock"></i>
                                </a>';
                }
                
                echo '<a href="?view=edit-sales&sale_id=' . $r->id . '" class="w-8 h-8 inline-flex items-center justify-center text-blue-600 hover:text-blue-900 bg-blue-50 rounded-lg transition-colors" title="Edit">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            <button type="button" class="w-8 h-8 inline-flex items-center justify-center text-rose-600 hover:text-rose-900 bg-rose-50 rounded-lg transition-colors delete-sale" data-id="' . $r->id . '" title="Delete">
                                <i class="fa-solid fa-trash"></i>
                            </button>';
                
                echo '<a href="?view=sales-invoice&sales_id=' . $r->id . '" class="w-8 h-8 inline-flex items-center justify-center text-cyan-600 hover:text-cyan-900 bg-cyan-50 rounded-lg transition-colors" title="View Invoice">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <a href="?view=add-sales-return&sales_id=' . $r->id . '" class="w-8 h-8 inline-flex items-center justify-center text-indigo-600 hover:text-indigo-900 bg-indigo-50 rounded-lg transition-colors" title="Create Return">
                                <i class="fa-solid fa-arrow-rotate-left"></i>
                            </a>
                        </div>
                    </td>
                </tr>';
                $i++;
            }
        } else {
            echo '<tr><td colspan="11" class="px-6 py-10 text-center text-gray-500">No records found</td></tr>';
        }

        wp_die();
    }

    /**
     * Handle Deletion of a Sale
     */
    public function handle_delete_sale()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if (!orabooks_can_access_inventory()) {
            wp_send_json_error('Access denied.');
        }

        global $wpdb;
        $sales_id = intval($_POST['sales_id'] ?? 0);
        if (!$sales_id) {
            wp_send_json_error(['message' => 'Sales ID required']);
        }

        $sale = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_sales WHERE id = %d", $sales_id));
        if (!$sale) {
            wp_send_json_error(['message' => 'Sales record not found']);
        }

        // 1. Restore Stock (if delivered)
        if ($sale->sales_status === 'Delivered') {
            $items = $wpdb->get_results($wpdb->prepare("SELECT item_id, sales_qty FROM {$wpdb->prefix}orabooks_db_salesitems WHERE sales_id = %d", $sales_id));
            foreach ($items as $item) {
                // Restore main stock
                $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_items SET stock = stock + %f WHERE id = %d", $item->sales_qty, $item->item_id));

                // Restore warehouse stock
                $wh_res = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_db_warehouseitems WHERE warehouse_id = %d AND item_id = %d", $sale->warehouse_id, $item->item_id));
                if ($wh_res) {
                    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_warehouseitems SET available_qty = available_qty + %f WHERE id = %d", $item->sales_qty, $wh_res->id));
                }
            }
        }

        // 2. Delete Items
        $wpdb->delete($wpdb->prefix . 'orabooks_db_salesitems', ['sales_id' => $sales_id]);

        // 3. Revert Payment Balances
        $old_transactions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_ac_transactions WHERE ref_salespayments_id IN (SELECT id FROM {$wpdb->prefix}orabooks_db_salespayments WHERE sales_id = %d)", $sales_id));
        foreach ($old_transactions as $ot) {
            $amount = floatval($ot->credit_amt) > 0 ? floatval($ot->credit_amt) : floatval($ot->debit_amt);
            if ($ot->debit_account_id > 0) {
                $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_ac_accounts SET balance = balance - %f WHERE id = %d", $amount, $ot->debit_account_id));
            }
            if ($ot->credit_account_id > 0) {
                $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_ac_accounts SET balance = balance + %f WHERE id = %d", $amount, $ot->credit_account_id));
            }
        }

        // 4. Delete Transactions & Payments
        $payment_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_db_salespayments WHERE sales_id = %d", $sales_id));
        if (!empty($payment_ids)) {
            $wpdb->query("DELETE FROM {$wpdb->prefix}orabooks_ac_transactions WHERE ref_salespayments_id IN (" . implode(',', array_map('intval', $payment_ids)) . ")");
            $wpdb->delete($wpdb->prefix . 'orabooks_db_salespayments', ['sales_id' => $sales_id]);
        }

        // 4.5 Delete Customer Payments
        if (!empty($payment_ids)) {
            $wpdb->query("DELETE FROM {$wpdb->prefix}orabooks_db_customer_payments WHERE salespayment_id IN (" . implode(',', array_map('intval', $payment_ids)) . ")");
        }

        // 5. Delete Journal Entries
        $existing_entry_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_ac_journal_entry WHERE source_type = 'Sales' AND source_id = %d", $sales_id));
        if (!empty($existing_entry_ids)) {
            $ids_placeholder = implode(',', array_map('intval', $existing_entry_ids));
            $wpdb->query("DELETE FROM {$wpdb->prefix}orabooks_ac_journal_line WHERE journal_entry_id IN ($ids_placeholder)");
            $wpdb->query("DELETE FROM {$wpdb->prefix}orabooks_ac_journal_entry WHERE id IN ($ids_placeholder)");
        }

        // 6. Delete Sale
        $deleted = $wpdb->delete($wpdb->prefix . 'orabooks_db_sales', ['id' => $sales_id]);

        if ($deleted) {
            wp_send_json_success(['message' => 'Sale deleted successfully.']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete sale.']);
        }
    }
    /**
     * Send Invoice Email to Customer
     */
    public function send_invoice_email($sales_id)
    {
        global $wpdb;
        $sale = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_sales WHERE id = %d", $sales_id));
        if (!$sale || empty($sale->customer_id))
            return false;

        $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_customers WHERE id = %d", $sale->customer_id));
        if (!$customer || empty($customer->email) || !is_email($customer->email))
            return false;

        $company = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}orabooks_db_store LIMIT 1");
        $items = json_decode(stripslashes($sale->invoice_terms), true);
        if (!is_array($items))
            $items = [];

        $subject = "Invoice from " . ($company->store_name ?? 'Our Store') . " - #" . $sale->sales_code;

        $currency_symbol = '৳';
        $currency_row = $wpdb->get_row("SELECT symbol FROM {$wpdb->prefix}orabooks_db_currency LIMIT 1");
        if ($currency_row && !empty($currency_row->symbol))
            $currency_symbol = $currency_row->symbol;

        // Build HTML Body (Visual Email)
        ob_start();
        ?>
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee;">
            <div style="text-align: center; border-bottom: 2px solid #1569B3; padding-bottom: 20px; mb: 20px;">
                <h1 style="color: #1569B3; margin: 0;">INVOICE</h1>
                <p style="color: #666;">#<?php echo esc_html($sale->sales_code); ?> | Date:
                    <?php echo date('d-M-Y', strtotime($sale->sales_date)); ?>
                </p>
            </div>

            <div style="margin-top: 20px;">
                <p>Dear <?php echo esc_html($customer->customer_name); ?>,</p>
                <p>Thank you for your purchase. Your invoice <strong>#<?php echo esc_html($sale->sales_code); ?></strong> is
                    ready.</p>
                <p><strong>Total Amount:</strong> <?php echo $currency_symbol . ' ' . number_format($sale->grand_total, 2); ?>
                </p>
                <p>Please find the detailed invoice attached as a PDF.</p>
            </div>

            <div
                style="margin-top: 40px; text-align: center; color: #999; font-size: 12px; border-top: 1px solid #eee; padding-top: 20px;">
                <p>Thank you for choosing <?php echo esc_html($company->store_name ?? 'Our Store'); ?>!</p>
                <p><?php echo esc_html($company->address ?? ''); ?></p>
            </div>
        </div>
        <?php
        $body = ob_get_clean();

        // Generate PDF Attachment
        $pdf_content = $this->generate_invoice_pdf($sales_id);
        $attachments = [];
        if ($pdf_content) {
            $upload_dir = wp_upload_dir();
            $pdf_path = $upload_dir['basedir'] . '/invoice-' . sanitize_file_name($sale->sales_code) . '.pdf';
            file_put_contents($pdf_path, $pdf_content);
            if (file_exists($pdf_path)) {
                $attachments[] = $pdf_path;
            }
        }

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . ($company->store_name ?? get_bloginfo('name')) . ' <' . ($company->email ?? get_option('admin_email')) . '>'
        );

        $result = wp_mail($customer->email, $subject, $body, $headers, $attachments);

        // Debugging for Localhost
        if (!$result) {
            add_action('wp_mail_failed', function ($error) {
                error_log('WPMU Inventory Email Failed: ' . print_r($error, true));
            });
        }

        // Cleanup
        foreach ($attachments as $file) {
            if (file_exists($file))
                unlink($file);
        }

        return $result;
    }

    /**
     * Generate PDF using Dompdf
     */
    private function generate_invoice_pdf($sales_id)
    {
        $dompdf_path = FRONTEND_INVENTORY_PATH . 'lib/dompdf/';

        // Manual loading of Dompdf if no autoloader found
        if (!class_exists('Dompdf\\Dompdf')) {
            // Need to handle dependencies if missing, but we'll try to include the main class
            // usually requires an autoloader for PSR-4
            spl_autoload_register(function ($class) use ($dompdf_path) {
                if (strpos($class, 'Dompdf\\') === 0) {
                    $file = $dompdf_path . 'src/' . str_replace('\\', '/', substr($class, 7)) . '.php';
                    if (file_exists($file))
                        require_once $file;
                }
            });
        }

        if (!class_exists('Dompdf\\Dompdf'))
            return false;

        // Fetch HTML
        ob_start();
        // Set context for the template
        $_GET['sales_id'] = $sales_id;
        include FRONTEND_INVENTORY_TEMPLATE_PATH . 'sales/sales-invoice.php';
        $html = ob_get_clean();

        // Basic HTML cleanup for Dompdf (it struggles with complex external CSS/JS)
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $html);
        $html = preg_replace('/<button\b[^>]*>(.*?)<\/button>/is', "", $html);
        $html = preg_replace('/<a\b[^>]*class="[^"]*no-print[^"]*"[^>]*>(.*?)<\/a>/is', "", $html);

        try {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans'); // Better Unicode support

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->loadHtml($html);
            $dompdf->render();
            return $dompdf->output();
        } catch (\Exception $e) {
            error_log('Dompdf Error: ' . $e->getMessage());
            return false;
        }
    }
}

new Frontend_Inventory_Sales();
