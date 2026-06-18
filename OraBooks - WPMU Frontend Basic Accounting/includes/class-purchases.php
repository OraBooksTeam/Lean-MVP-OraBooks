<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Frontend_Accounting_Purchases')) {
    class Frontend_Accounting_Purchases
    {

        public function __construct()
        {
            // AJAX handlers
            add_action('wp_ajax_search_purchase_items', [$this, 'handle_search_purchase_items']);
            add_action('wp_ajax_generate_purchase_code', [$this, 'handle_generate_purchase_code']);
            add_action('wp_ajax_insert_purchase', [$this, 'handle_insert_purchase']);
            add_action('wp_ajax_get_purchase_details', [$this, 'handle_get_purchase_details']);
            add_action('wp_ajax_update_purchase', [$this, 'handle_update_purchase']);
            add_action('wp_ajax_delete_purchase', [$this, 'handle_delete_purchase']);
            add_action('wp_ajax_update_purchase_status', [$this, 'handle_update_purchase_status']);
        }

        public function handle_get_purchase_details()
        {
            check_ajax_referer('frontend_ajax_nonce', 'security');

            if (!orabooks_can_access_accounting()) {
                wp_send_json_error('Access denied.');
            }
            global $wpdb;
            $id = intval($_REQUEST['id'] ?? ($_REQUEST['purchase_id'] ?? 0));
            if (!$id)
                wp_send_json_error('Invalid ID');

            $purchase = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_purchase WHERE id=%d", $id));
            if (!$purchase)
                wp_send_json_error('Purchase not found');

            $items = $wpdb->get_results($wpdb->prepare("
            SELECT pi.*, i.item_name, i.item_code, i.sku, i.stock 
            FROM {$wpdb->prefix}orabooks_db_purchaseitems pi
            LEFT JOIN {$wpdb->prefix}orabooks_db_items i ON pi.item_id = i.id
            WHERE pi.purchase_id=%d
        ", $id));

            $payments = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}orabooks_db_purchasepayments 
            WHERE purchase_id=%d 
            ORDER BY id ASC
        ", $id));

            wp_send_json_success(['purchase' => $purchase, 'items' => $items, 'payments' => $payments]);
        }

        public function handle_update_purchase()
        {
            check_ajax_referer('frontend_ajax_nonce', 'security');

            if (!orabooks_can_access_accounting()) {
                wp_send_json_error('Access denied.');
            }
            // Removed auth check as requested

            global $wpdb;
            $id = intval($_POST['purchase_id']);
            if (!$id)
                wp_send_json_error('Invalid Purchase ID');
            $purchase_date = sanitize_text_field($_POST['purchase_date'] ?? current_time('Y-m-d'));
            if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                $existing_purchase = $wpdb->get_row($wpdb->prepare("SELECT purchase_date FROM {$wpdb->prefix}orabooks_db_purchase WHERE id = %d", $id));
                if (!$existing_purchase) {
                    wp_send_json_error('Purchase not found');
                }
                $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(get_current_blog_id(), $existing_purchase->purchase_date);
                if (is_wp_error($modification_allowed)) {
                    wp_send_json_error($modification_allowed->get_error_message(), 409);
                }
                OBN_Fiscal_Period_Posting_Guard::assert_can_post_or_fail($purchase_date);
            }

            $table = $wpdb->prefix . 'orabooks_db_purchase';

            // Revert old stock
            $old_purchase = $wpdb->get_row($wpdb->prepare("SELECT warehouse_id, purchase_status FROM {$wpdb->prefix}orabooks_db_purchase WHERE id=%d", $id));
            $old_items = $wpdb->get_results($wpdb->prepare("SELECT item_id, purchase_qty FROM {$wpdb->prefix}orabooks_db_purchaseitems WHERE purchase_id=%d", $id));
            if ($old_items && $old_purchase && $old_purchase->purchase_status === 'Received') {
                foreach ($old_items as $oi) {
                    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_items SET stock = stock - %f WHERE id=%d", $oi->purchase_qty, $oi->item_id));

                    // Revert warehouse stock
                    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_warehouseitems SET available_qty = available_qty - %f WHERE warehouse_id = %d AND item_id = %d", $oi->purchase_qty, $old_purchase->warehouse_id, $oi->item_id));
                }
            }
            $wpdb->delete($wpdb->prefix . 'orabooks_db_purchaseitems', ['purchase_id' => $id]);

            $warehouse_id = intval($_POST['warehouse_id'] ?? 0);
            $supplier_id = intval($_POST['supplier_id'] ?? 0);
            $supplier_name = sanitize_text_field($_POST['supplier_name'] ?? '');
            $created_by = get_current_user_id();
            $system_ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);

            if ($supplier_id <= 0 && !empty($supplier_name)) {
                // Check if supplier already exists with this name (case-insensitive)
                $existing_supplier_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}orabooks_db_suppliers WHERE LOWER(supplier_name) = LOWER(%s) LIMIT 1",
                    $supplier_name
                ));
                if ($existing_supplier_id) {
                    $supplier_id = intval($existing_supplier_id);
                } else {
                    // Insert new supplier
                    $store_id = intval($_POST['store_id'] ?? 1);
                    $count_id = $wpdb->get_var($wpdb->prepare("SELECT MAX(count_id) FROM {$wpdb->prefix}orabooks_db_suppliers WHERE store_id = %d", $store_id));
                    $count_id = ($count_id) ? intval($count_id) + 1 : 1;

                    $prefix = $wpdb->get_var($wpdb->prepare("SELECT supplier_init FROM {$wpdb->prefix}orabooks_db_store WHERE id = %d", $store_id));
                    if (!$prefix) {
                        $prefix = 'SUP-';
                    }
                    $supplier_code = $prefix . str_pad($count_id, 6, '0', STR_PAD_LEFT);

                    $supplier_data = array(
                        'store_id' => $store_id,
                        'supplier_name' => $supplier_name,
                        'supplier_code' => $supplier_code,
                        'count_id' => $count_id,
                        'status' => 1,
                        'created_date' => current_time('Y-m-d'),
                        'created_time' => current_time('mysql'),
                        'created_by' => $created_by,
                        'system_ip' => $system_ip,
                        'system_name' => 'System',
                    );
                    $wpdb->insert($wpdb->prefix . 'orabooks_db_suppliers', $supplier_data);
                    $supplier_id = $wpdb->insert_id;
                }
            }

            // if (!$warehouse_id || !$supplier_id) {
            //     wp_send_json_error('Warehouse and Supplier required');
            // }

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

            $data = [
                'warehouse_id' => $warehouse_id,
                'reference_no' => sanitize_text_field($_POST['reference_no'] ?? ''),
                'purchase_date' => $purchase_date,
                'purchase_status' => sanitize_text_field($_POST['purchase_status'] ?? 'Received'),
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
                'purchase_note' => sanitize_textarea_field($_POST['purchase_note'] ?? ''),
                'paid_amount' => floatval($_POST['payment_amount'] ?? 0),
            ];

            if ($data['paid_amount'] > 0) {
                $data['payment_status'] = ($data['paid_amount'] >= $data['grand_total']) ? 'Paid' : 'Partial';
            } else {
                $data['payment_status'] = 'Unpaid';
            }

            if ($wpdb->update($table, $data, ['id' => $id])) {
                // Journal entry creation will be called later after all updates
            }

            $items = json_decode(wp_unslash($_POST['items_json'] ?? '[]'), true);
            if (is_array($items)) {
                // Delete old items first to avoid duplicates on update
                $wpdb->delete($wpdb->prefix . 'orabooks_db_purchaseitems', ['purchase_id' => $id]);

                foreach ($items as $item) {
                    $item_id = intval($item['item_id'] ?? 0);
                    $item_name = sanitize_text_field($item['name'] ?? '');

                    if ($item_id <= 0 && !empty($item_name)) {
                        // Check if item already exists by name
                        $existing_item_id = $wpdb->get_var($wpdb->prepare(
                            "SELECT id FROM {$wpdb->prefix}orabooks_db_items WHERE LOWER(item_name) = LOWER(%s) LIMIT 1",
                            $item_name
                        ));
                        if ($existing_item_id) {
                            $item_id = intval($existing_item_id);
                        } else {
                            // Dynamically insert a new item
                            $items_table = $wpdb->prefix . 'orabooks_db_items';
                            $last_item = $wpdb->get_row("SELECT count_id FROM $items_table ORDER BY id DESC LIMIT 1");
                            $count_id = ($last_item && $last_item->count_id) ? $last_item->count_id + 1 : 1;
                            $item_code = 'ITM-' . str_pad($count_id, 6, '0', STR_PAD_LEFT);

                            $category_id = intval($wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_db_category LIMIT 1") ?: 1);
                            $unit_id = intval($wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_db_units LIMIT 1") ?: 0);

                            $price = floatval($item['unit_price'] ?? 0);

                            $new_item_data = array(
                                'store_id' => $store_id,
                                'count_id' => $count_id,
                                'item_code' => $item_code,
                                'item_name' => $item_name,
                                'category_id' => $category_id,
                                'brand_id' => 0,
                                'unit_id' => $unit_id,
                                'sku' => '',
                                'hsn' => '',
                                'alert_qty' => 0,
                                'price' => $price,
                                'tax_id' => 0,
                                'purchase_price' => $price,
                                'tax_type' => 'Inclusive',
                                'profit_margin' => 0.00,
                                'sales_price' => $price,
                                'mrp' => $price,
                                'warehouse_id' => $warehouse_id,
                                'stock' => 0.00,
                                'created_date' => current_time('mysql'),
                                'status' => 1,
                                'item_type' => 'Single'
                            );
                            $wpdb->insert($items_table, $new_item_data);
                            $item_id = $wpdb->insert_id;
                        }
                    }

                    if ($item_id <= 0)
                        continue;

                    $item_data = [
                        'store_id' => $store_id,
                        'purchase_id' => $id,
                        'purchase_status' => $data['purchase_status'],
                        'item_id' => $item_id,
                        'account_id' => intval($item['account_id']),
                        'description' => sanitize_text_field($item['name'] ?? ''),
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
                        'status' => 1,
                    ];
                    $item_inserted = $wpdb->insert($wpdb->prefix . 'orabooks_db_purchaseitems', $item_data);
                    if (!$item_inserted) {
                        wp_send_json_error(['message' => 'Failed to save purchase line items: ' . $wpdb->last_error]);
                    }

                    if ($data['purchase_status'] == 'Received') {
                        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_items SET stock = stock + %f WHERE id = %d", $item_data['purchase_qty'], $item_data['item_id']));
                        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_items SET purchase_price = %f WHERE id = %d", $item_data['price_per_unit'], $item_data['item_id']));

                        // Update warehouse stock
                        $wh_res = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_db_warehouseitems WHERE warehouse_id = %d AND item_id = %d", $warehouse_id, $item_data['item_id']));
                        if ($wh_res) {
                            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_warehouseitems SET available_qty = available_qty + %f WHERE id = %d", $item_data['purchase_qty'], $wh_res->id));
                        } else {
                            $wpdb->insert($wpdb->prefix . 'orabooks_db_warehouseitems', [
                                'store_id' => $store_id,
                                'warehouse_id' => $warehouse_id,
                                'item_id' => $item_data['item_id'],
                                'available_qty' => $item_data['purchase_qty']
                            ]);
                        }
                    }
                }
            }

            if (floatval($_POST['payment_amount'] ?? 0) > 0) {
                $payment_type_id = intval($_POST['payment_type_id'] ?? 0);
                $account_id = intval($_POST['account_id'] ?? 0);

                // Revert old transactions
                $payment_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_db_purchasepayments WHERE purchase_id = %d", $id));
                if (!empty($payment_ids)) {
                    $id_str = implode(',', array_map('intval', $payment_ids));
                    // Remove old transactions and account balance updates as journal entries are now primary
                    $wpdb->query("DELETE FROM {$wpdb->prefix}orabooks_ac_transactions WHERE ref_purchasepayments_id IN ($id_str)");
                    $wpdb->query("DELETE FROM {$wpdb->prefix}orabooks_db_supplier_payments WHERE purchasepayment_id IN ($id_str)");
                }
                $wpdb->delete($wpdb->prefix . 'orabooks_db_purchasepayments', ['purchase_id' => $id]);

                $payment_code = $this->generate_purchase_payment_code();
                $payment_count_id = $wpdb->get_var($wpdb->prepare("SELECT MAX(count_id) FROM {$wpdb->prefix}orabooks_db_purchasepayments WHERE store_id = %d", $store_id));
                $payment_count_id = ($payment_count_id) ? intval($payment_count_id) + 1 : 1;

                $paydata = [
                    'store_id' => $store_id,
                    'count_id' => $payment_count_id,
                    'purchase_id' => $id,
                    'payment_code' => $payment_code,
                    'short_code' => $payment_code,
                    'payment_date' => $data['purchase_date'],
                    'payment_type' => $payment_type_id,
                    'payment' => floatval($_POST['payment_amount'] ?? 0),
                    'payment_note' => sanitize_textarea_field($_POST['payment_note'] ?? ''),
                    'account_id' => $account_id,
                    'system_ip' => $system_ip,
                    'system_name' => 'System',
                    'created_time' => current_time('H:i:s'),
                    'created_date' => current_time('Y-m-d'),
                    'created_by' => $created_by,
                    'status' => 1,
                    'supplier_id' => $supplier_id,
                ];
                $wpdb->insert($wpdb->prefix . 'orabooks_db_purchasepayments', $paydata);
                $purchasepayment_id = $wpdb->insert_id;

                // Insert into orabooks_db_supplier_payments
                $wpdb->insert($wpdb->prefix . 'orabooks_db_supplier_payments', [
                    'purchasepayment_id' => $purchasepayment_id,
                    'supplier_id' => $supplier_id,
                    'payment_date' => $paydata['payment_date'],
                    'payment_type' => $paydata['payment_type'],
                    'payment' => $paydata['payment'],
                    'payment_note' => $paydata['payment_note'],
                    'system_ip' => $paydata['system_ip'],
                    'system_name' => $paydata['system_name'],
                    'created_time' => $paydata['created_time'],
                    'created_date' => $paydata['created_date'],
                    'created_by' => $paydata['created_by'],
                    'status' => 1
                ]);
            }

            // Finalize accounting record
            $this->create_purchase_journal_entry($id);

            wp_send_json_success(['message' => 'Purchase updated successfully', 'purchase_id' => $id]);
        }

        public function handle_search_purchase_items()
        {
            check_ajax_referer('frontend_ajax_nonce', 'security');

            if (!orabooks_can_access_accounting()) {
                wp_send_json_error('Access denied.');
            }
            global $wpdb;
            $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';

            $like = '%' . $wpdb->esc_like($term) . '%';
            $table = $wpdb->prefix . 'orabooks_db_items';

            $sql = "SELECT id, item_name, item_code, sku, price, purchase_price, tax_id, stock, purchase_account_id FROM $table WHERE (item_name LIKE %s OR sku LIKE %s OR item_code LIKE %s) AND status=1 LIMIT 50";

            if (empty($term)) {
                $sql = "SELECT id, item_name, item_code, sku, price, purchase_price, tax_id, stock, purchase_account_id FROM $table WHERE status=1 LIMIT 50";
                $rows = $wpdb->get_results($sql);
            } else {
                $rows = $wpdb->get_results($wpdb->prepare($sql, $like, $like, $like));
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
                    'purchase_price' => floatval($r->purchase_price ?: $r->price),
                    'tax_percent' => $tax_percent,
                    'stock' => floatval($r->stock),
                    'purchase_account_id' => intval($r->purchase_account_id ?? 0)
                ];
            }
            wp_send_json_success($data);
        }

        public function handle_delete_purchase()
        {
            check_ajax_referer('frontend_ajax_nonce', 'security');

            if (!orabooks_can_access_accounting()) {
                wp_send_json_error('Access denied.');
            }
            // Removed auth check as requested

            global $wpdb;
            $id = intval($_POST['purchase_id']);
            if (!$id)
                wp_send_json_error('Invalid ID');

            // Revert stock if purchase was 'Received'
            $purchase = $wpdb->get_row($wpdb->prepare("SELECT warehouse_id, purchase_status, purchase_date FROM {$wpdb->prefix}orabooks_db_purchase WHERE id=%d", $id));
            if (!$purchase) {
                wp_send_json_error('Purchase not found');
            }
            if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(get_current_blog_id(), $purchase->purchase_date);
                if (is_wp_error($modification_allowed)) {
                    wp_send_json_error($modification_allowed->get_error_message(), 409);
                }
            }
            if ($purchase && $purchase->purchase_status === 'Received') {
                $items = $wpdb->get_results($wpdb->prepare("SELECT item_id, purchase_qty FROM {$wpdb->prefix}orabooks_db_purchaseitems WHERE purchase_id=%d", $id));
                if ($items) {
                    foreach ($items as $item) {
                        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_items SET stock = stock - %f WHERE id=%d", $item->purchase_qty, $item->item_id));

                        // Revert warehouse stock
                        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_warehouseitems SET available_qty = available_qty - %f WHERE warehouse_id = %d AND item_id = %d", $item->purchase_qty, $purchase->warehouse_id, $item->item_id));
                    }
                }
            }

            // Revert payment balances before deletion - these are now handled by journal entries
            // We delete the related payment IDs from transactions and supplier payments
            $payment_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_db_purchasepayments WHERE purchase_id = %d", $id));
            if (!empty($payment_ids)) {
                $id_str = implode(',', array_map('intval', $payment_ids));
                $wpdb->query("DELETE FROM {$wpdb->prefix}orabooks_ac_transactions WHERE ref_purchasepayments_id IN ($id_str)");
                $wpdb->query("DELETE FROM {$wpdb->prefix}orabooks_db_supplier_payments WHERE purchasepayment_id IN ($id_str)");
            }

            // Delete primary records
            $wpdb->delete($wpdb->prefix . 'orabooks_db_purchase', ['id' => $id]);
            $wpdb->delete($wpdb->prefix . 'orabooks_db_purchaseitems', ['purchase_id' => $id]);

            $wpdb->delete($wpdb->prefix . 'orabooks_db_purchasepayments', ['purchase_id' => $id]);
            $wpdb->delete($wpdb->prefix . 'orabooks_db_supplier_payments', ['purchase_id' => $id]); // Ensure supplier payments are also deleted

            // Delete Journal Entry
            $existing_entry_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_ac_journal_entry WHERE source_type = 'Purchase' AND source_id = %d", $id));
            if ($existing_entry_id) {
                if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                    OBN_Fiscal_Period_Posting_Guard::assert_journal_entry_modifiable_or_fail($existing_entry_id);
                }
                $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_line', ['journal_entry_id' => $existing_entry_id]);
                $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_entry', ['id' => $existing_entry_id]);
            }

            wp_send_json_success('Purchase deleted successfully');
        }

        public function handle_update_purchase_status()
        {
            check_ajax_referer('frontend_ajax_nonce', 'security');

            if (!orabooks_can_access_accounting()) {
                wp_send_json_error('Access denied.');
            }

            global $wpdb;
            $purchase_id = intval($_POST['purchase_id']);
            $new_status = sanitize_text_field($_POST['purchase_status']);

            if (!$purchase_id || !in_array($new_status, ['Ordered', 'Pending', 'Received'])) {
                wp_send_json_error('Invalid parameters');
            }

            $table = $wpdb->prefix . 'orabooks_db_purchase';

            // Get current purchase details
            $purchase = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $purchase_id));
            if (!$purchase) {
                wp_send_json_error('Purchase not found');
            }
            if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                $modification_allowed = OBN_Fiscal_Period_Posting_Guard::can_modify(get_current_blog_id(), $purchase->purchase_date);
                if (is_wp_error($modification_allowed)) {
                    wp_send_json_error($modification_allowed->get_error_message(), 409);
                }
            }

            // Update status
            $updated = $wpdb->update($table, ['purchase_status' => $new_status], ['id' => $purchase_id]);

            if ($updated === false) {
                wp_send_json_error('Database error: ' . $wpdb->last_error);
            }

            // If status is being changed to 'Received', update stock and create journal entry
            if ($new_status === 'Received' && $purchase->purchase_status !== 'Received') {
                // Get purchase items
                $items = $wpdb->get_results($wpdb->prepare("
                SELECT item_id, purchase_qty, price_per_unit 
                FROM {$wpdb->prefix}orabooks_db_purchaseitems 
                WHERE purchase_id = %d
            ", $purchase_id));

                if ($items) {
                    foreach ($items as $item) {
                        // Update item stock
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$wpdb->prefix}orabooks_db_items SET stock = stock + %f WHERE id = %d",
                            $item->purchase_qty,
                            $item->item_id
                        ));

                        // Update item purchase price
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$wpdb->prefix}orabooks_db_items SET purchase_price = %f WHERE id = %d",
                            $item->price_per_unit,
                            $item->item_id
                        ));

                        // Update warehouse stock
                        $wh_res = $wpdb->get_row($wpdb->prepare(
                            "SELECT id FROM {$wpdb->prefix}orabooks_db_warehouseitems WHERE warehouse_id = %d AND item_id = %d",
                            $purchase->warehouse_id,
                            $item->item_id
                        ));

                        if ($wh_res) {
                            $wpdb->query($wpdb->prepare(
                                "UPDATE {$wpdb->prefix}orabooks_db_warehouseitems SET available_qty = available_qty + %f WHERE id = %d",
                                $item->purchase_qty,
                                $wh_res->id
                            ));
                        } else {
                            $wpdb->insert($wpdb->prefix . 'orabooks_db_warehouseitems', [
                                'store_id' => $purchase->store_id,
                                'warehouse_id' => $purchase->warehouse_id,
                                'item_id' => $item->item_id,
                                'available_qty' => $item->purchase_qty
                            ]);
                        }
                    }
                }

                // Create journal entry
                $this->create_purchase_journal_entry($purchase_id);
            }

            // If status is being changed FROM 'Received', revert stock and delete journal entry
            elseif ($purchase->purchase_status === 'Received' && $new_status !== 'Received') {
                // Get purchase items to revert stock
                $items = $wpdb->get_results($wpdb->prepare("
                SELECT item_id, purchase_qty 
                FROM {$wpdb->prefix}orabooks_db_purchaseitems 
                WHERE purchase_id = %d
            ", $purchase_id));

                if ($items) {
                    foreach ($items as $item) {
                        // Revert item stock
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$wpdb->prefix}orabooks_db_items SET stock = stock - %f WHERE id = %d",
                            $item->purchase_qty,
                            $item->item_id
                        ));

                        // Revert warehouse stock
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$wpdb->prefix}orabooks_db_warehouseitems SET available_qty = available_qty - %f WHERE warehouse_id = %d AND item_id = %d",
                            $item->purchase_qty,
                            $purchase->warehouse_id,
                            $item->item_id
                        ));
                    }
                }

                // Delete journal entry
                $existing_entry_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}orabooks_ac_journal_entry WHERE source_type = 'Purchase' AND source_id = %d",
                    $purchase_id
                ));
                if ($existing_entry_id) {
                    if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                        OBN_Fiscal_Period_Posting_Guard::assert_journal_entry_modifiable_or_fail($existing_entry_id);
                    }
                    $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_line', ['journal_entry_id' => $existing_entry_id]);
                    $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_entry', ['id' => $existing_entry_id]);
                }
            }

            wp_send_json_success(['message' => 'Purchase status updated successfully']);
        }

        public function handle_generate_purchase_code()
        {
            check_ajax_referer('frontend_ajax_nonce', 'security');

            if (!orabooks_can_access_accounting()) {
                wp_send_json_error('Access denied.');
            }
            global $wpdb;

            $table = $wpdb->prefix . 'orabooks_db_purchase';
            $store_table = $wpdb->prefix . 'orabooks_db_store';

            // Fetch prefix from store settings
            $prefix = $wpdb->get_var("SELECT purchase_init FROM $store_table WHERE id = 1");
            if (!$prefix) {
                $prefix = 'PUR-';
            }

            // Check if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                wp_send_json_success(['code' => $prefix . str_pad(1, 6, '0', STR_PAD_LEFT)]);
                return;
            }

            $last = $wpdb->get_var("SELECT purchase_code FROM $table WHERE purchase_code LIKE '$prefix%' ORDER BY id DESC LIMIT 1");
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

        private function generate_purchase_payment_code()
        {
            global $wpdb;
            $store_prefix = $wpdb->get_var("SELECT purchase_payment_init FROM {$wpdb->prefix}orabooks_db_store LIMIT 1");
            if (empty($store_prefix)) {
                $store_prefix = 'PP-';
            }

            $table = $wpdb->prefix . 'orabooks_db_purchasepayments';
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

        public function handle_insert_purchase()
        {
            check_ajax_referer('frontend_ajax_nonce', 'security');

            if (!orabooks_can_access_accounting()) {
                wp_send_json_error('Access denied.');
            }
            // Removed auth check as requested

            global $wpdb;
            $table = $wpdb->prefix . 'orabooks_db_purchase';
            $purchase_date = sanitize_text_field($_POST['purchase_date'] ?? current_time('Y-m-d'));
            if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                OBN_Fiscal_Period_Posting_Guard::assert_can_post_or_fail($purchase_date);
            }

            $warehouse_id = intval($_POST['warehouse_id'] ?? 0);
            $supplier_id = intval($_POST['supplier_id'] ?? 0);
            $supplier_name = sanitize_text_field($_POST['supplier_name'] ?? '');
            $created_by = get_current_user_id();
            $system_ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);

            if ($supplier_id <= 0 && !empty($supplier_name)) {
                // Check if supplier already exists with this name (case-insensitive)
                $existing_supplier_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}orabooks_db_suppliers WHERE LOWER(supplier_name) = LOWER(%s) LIMIT 1",
                    $supplier_name
                ));
                if ($existing_supplier_id) {
                    $supplier_id = intval($existing_supplier_id);
                } else {
                    // Insert new supplier
                    $store_id = intval($_POST['store_id'] ?? 1);
                    $count_id = $wpdb->get_var($wpdb->prepare("SELECT MAX(count_id) FROM {$wpdb->prefix}orabooks_db_suppliers WHERE store_id = %d", $store_id));
                    $count_id = ($count_id) ? intval($count_id) + 1 : 1;

                    $prefix = $wpdb->get_var($wpdb->prepare("SELECT supplier_init FROM {$wpdb->prefix}orabooks_db_store WHERE id = %d", $store_id));
                    if (!$prefix) {
                        $prefix = 'SUP-';
                    }
                    $supplier_code = $prefix . str_pad($count_id, 6, '0', STR_PAD_LEFT);

                    $supplier_data = array(
                        'store_id' => $store_id,
                        'supplier_name' => $supplier_name,
                        'supplier_code' => $supplier_code,
                        'count_id' => $count_id,
                        'status' => 1,
                        'created_date' => current_time('Y-m-d'),
                        'created_time' => current_time('mysql'),
                        'created_by' => $created_by,
                        'system_ip' => $system_ip,
                        'system_name' => 'System',
                    );
                    $wpdb->insert($wpdb->prefix . 'orabooks_db_suppliers', $supplier_data);
                    $supplier_id = $wpdb->insert_id;
                }
            }

            // Prevent Duplicate: Check if purchase_code already exists
            $purchase_code = sanitize_text_field($_POST['purchase_code'] ?? '');
            if (!empty($purchase_code)) {
                $existing_purchase = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE purchase_code = %s", $purchase_code));
                if ($existing_purchase) {
                    wp_send_json_error(['message' => 'Duplicate Entry: Purchase code ' . $purchase_code . ' already exists.']);
                }
            }

            // if (!$warehouse_id || !$supplier_id) {
            //     wp_send_json_error('Warehouse and Supplier required');
            // }

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

            $data = [
                'store_id' => $store_id,
                'count_id' => $count_id,
                'warehouse_id' => $warehouse_id,
                'purchase_code' => sanitize_text_field($_POST['purchase_code'] ?? ''),
                'reference_no' => sanitize_text_field($_POST['reference_no'] ?? ''),
                'purchase_date' => $purchase_date,
                'purchase_status' => sanitize_text_field($_POST['purchase_status'] ?? 'Received'),
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
                'purchase_note' => sanitize_textarea_field($_POST['purchase_note'] ?? ''),
                'payment_status' => 'Unpaid',
                'paid_amount' => floatval($_POST['payment_amount'] ?? 0),
                'created_date' => current_time('Y-m-d'),
                'created_time' => current_time('H:i:s'),
                'created_by' => $created_by,
                'system_ip' => $system_ip,
                'system_name' => 'System',
                'company_id' => 1,
                'status' => 1,
                'return_bit' => 0,
            ];

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
            if (!$inserted)
                wp_send_json_error('DB insert failed: ' . $wpdb->last_error);

            $last_id = $wpdb->insert_id;

            // Items
            $items_json_raw = wp_unslash($_POST['items_json'] ?? '[]');
            $items = json_decode($items_json_raw, true);
            if (is_array($items) && count($items) > 0) {
                foreach ($items as $item) {
                    $item_id = intval($item['item_id'] ?? 0);
                    $item_name = sanitize_text_field($item['name'] ?? '');

                    if ($item_id <= 0 && !empty($item_name)) {
                        // Check if item already exists by name
                        $existing_item_id = $wpdb->get_var($wpdb->prepare(
                            "SELECT id FROM {$wpdb->prefix}orabooks_db_items WHERE LOWER(item_name) = LOWER(%s) LIMIT 1",
                            $item_name
                        ));
                        if ($existing_item_id) {
                            $item_id = intval($existing_item_id);
                        } else {
                            // Dynamically insert a new item
                            $items_table = $wpdb->prefix . 'orabooks_db_items';
                            $last_item = $wpdb->get_row("SELECT count_id FROM $items_table ORDER BY id DESC LIMIT 1");
                            $count_id = ($last_item && $last_item->count_id) ? $last_item->count_id + 1 : 1;
                            $item_code = 'ITM-' . str_pad($count_id, 6, '0', STR_PAD_LEFT);

                            $category_id = intval($wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_db_category LIMIT 1") ?: 1);
                            $unit_id = intval($wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_db_units LIMIT 1") ?: 0);

                            $price = floatval($item['unit_price'] ?? 0);

                            $new_item_data = array(
                                'store_id' => $data['store_id'],
                                'count_id' => $count_id,
                                'item_code' => $item_code,
                                'item_name' => $item_name,
                                'category_id' => $category_id,
                                'brand_id' => 0,
                                'unit_id' => $unit_id,
                                'sku' => '',
                                'hsn' => '',
                                'alert_qty' => 0,
                                'price' => $price,
                                'tax_id' => 0,
                                'purchase_price' => $price,
                                'tax_type' => 'Inclusive',
                                'profit_margin' => 0.00,
                                'sales_price' => $price,
                                'mrp' => $price,
                                'warehouse_id' => $data['warehouse_id'],
                                'stock' => 0.00,
                                'created_date' => current_time('Y-m-d'),
                                'created_time' => current_time('H:i:s'),
                                'created_by' => $data['created_by'],
                                'system_ip' => $data['system_ip'],
                                'system_name' => $data['system_name'],
                                'status' => 1,
                                'item_type' => 'Single'
                            );
                            $wpdb->insert($items_table, $new_item_data);
                            $item_id = $wpdb->insert_id;
                        }
                    }

                    if ($item_id <= 0)
                        continue;

                    $item_data = [
                        'store_id' => $data['store_id'],
                        'purchase_id' => $last_id,
                        'purchase_status' => $data['purchase_status'],
                        'item_id' => $item_id,
                        'account_id' => intval($item['account_id']),
                        'description' => $item_name,
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
                        'status' => 1,
                    ];
                    $item_inserted = $wpdb->insert($wpdb->prefix . 'orabooks_db_purchaseitems', $item_data);
                    if (!$item_inserted) {
                        wp_send_json_error(['message' => 'Failed to save purchase line items: ' . $wpdb->last_error]);
                    }

                    // Update stock (Increment)
                    if ($data['purchase_status'] == 'Received') {
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$wpdb->prefix}orabooks_db_items SET stock = stock + %f WHERE id = %d",
                            $item_data['purchase_qty'],
                            $item_data['item_id']
                        ));

                        // Update Item Purchase Price to latest
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$wpdb->prefix}orabooks_db_items SET purchase_price = %f WHERE id = %d",
                            $item_data['price_per_unit'],
                            $item_data['item_id']
                        ));

                        // Update warehouse stock
                        $wh_res = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_db_warehouseitems WHERE warehouse_id = %d AND item_id = %d", $warehouse_id, $item_data['item_id']));
                        if ($wh_res) {
                            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_warehouseitems SET available_qty = available_qty + %f WHERE id = %d", $item_data['purchase_qty'], $wh_res->id));
                        } else {
                            $wpdb->insert($wpdb->prefix . 'orabooks_db_warehouseitems', [
                                'store_id' => $data['store_id'],
                                'warehouse_id' => $warehouse_id,
                                'item_id' => $item_data['item_id'],
                                'available_qty' => $item_data['purchase_qty']
                            ]);
                        }
                    }
                }
            }

            // Payments
            if (floatval($_POST['payment_amount'] ?? 0) > 0) {
                $payment_type_id = intval($_POST['payment_type_id'] ?? 0);
                $account_id = intval($_POST['account_id'] ?? 0);

                $payment_code = $this->generate_purchase_payment_code();
                $payment_count_id = $wpdb->get_var($wpdb->prepare("SELECT MAX(count_id) FROM {$wpdb->prefix}orabooks_db_purchasepayments WHERE store_id = %d", $store_id));
                $payment_count_id = ($payment_count_id) ? intval($payment_count_id) + 1 : 1;

                $paydata = [
                    'store_id' => $store_id,
                    'count_id' => $payment_count_id,
                    'purchase_id' => $last_id,
                    'payment_code' => $payment_code,
                    'short_code' => $payment_code,
                    'payment_date' => $data['purchase_date'],
                    'payment_type' => $payment_type_id,
                    'payment' => floatval($_POST['payment_amount'] ?? 0),
                    'payment_note' => sanitize_textarea_field($_POST['payment_note'] ?? ''),
                    'account_id' => $account_id,
                    'system_ip' => $system_ip,
                    'system_name' => 'System',
                    'created_time' => current_time('H:i:s'),
                    'created_date' => current_time('Y-m-d'),
                    'created_by' => $created_by,
                    'status' => 1,
                    'supplier_id' => $supplier_id,
                ];
                $wpdb->insert($wpdb->prefix . 'orabooks_db_purchasepayments', $paydata);
                $purchasepayment_id = $wpdb->insert_id;

                // Insert into orabooks_db_supplier_payments
                $wpdb->insert($wpdb->prefix . 'orabooks_db_supplier_payments', [
                    'purchasepayment_id' => $purchasepayment_id,
                    'supplier_id' => $supplier_id,
                    'payment_date' => $paydata['payment_date'],
                    'payment_type' => $paydata['payment_type'],
                    'payment' => $paydata['payment'],
                    'payment_note' => $paydata['payment_note'],
                    'system_ip' => $paydata['system_ip'],
                    'system_name' => $paydata['system_name'],
                    'created_time' => $paydata['created_time'],
                    'created_date' => $paydata['created_date'],
                    'created_by' => $paydata['created_by'],
                    'status' => 1
                ]);
            }

            if ($inserted) {
                // Journal entry creation is the primary accounting record now
                $this->create_purchase_journal_entry($last_id);
            }

            wp_send_json_success(['message' => 'Purchase saved successfully', 'purchase_id' => $last_id]);
        }

        /**
         * Create IFRS Journal Entry for Purchase (Only if Received)
         */
        private function create_purchase_journal_entry($purchase_id)
        {
            global $wpdb;

            // Fetch Purchase Details
            $purchase = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_purchase WHERE id = %d", $purchase_id));
            if (!$purchase)
                return;

            // ONLY create journal entry if purchase is received
            if ($purchase->purchase_status !== 'Received') {
                // Delete any existing journal entry if status changed from Received to something else
                $existing_entry_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_ac_journal_entry WHERE source_type = 'Purchase' AND source_id = %d", $purchase_id));
                if ($existing_entry_id) {
                    if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                        OBN_Fiscal_Period_Posting_Guard::assert_journal_entry_modifiable_or_fail($existing_entry_id);
                    }
                    $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_line', ['journal_entry_id' => $existing_entry_id]);
                    $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_entry', ['id' => $existing_entry_id]);
                }
                return;
            }

            $store_id = $purchase->store_id;
            $supplier_id = $purchase->supplier_id;
            $grand_total = floatval($purchase->grand_total);
            $paid_amount = floatval($purchase->paid_amount);
            $due_amount = $grand_total - $paid_amount;
            $purchase_date = $purchase->purchase_date;

            if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                $posting_allowed = OBN_Fiscal_Period_Posting_Guard::can_post(get_current_blog_id(), $purchase_date);
                if (is_wp_error($posting_allowed)) {
                    wp_send_json_error($posting_allowed->get_error_message(), 409);
                }
            }

            // Fetch Purchase Items for detailed tax and discount calculation
            $purchase_items = $wpdb->get_results($wpdb->prepare("
            SELECT pi.*, i.tax_id as item_tax_id, t.tax as tax_percent 
            FROM {$wpdb->prefix}orabooks_db_purchaseitems pi
            LEFT JOIN {$wpdb->prefix}orabooks_db_items i ON pi.item_id = i.id
            LEFT JOIN {$wpdb->prefix}orabooks_db_tax t ON i.tax_id = t.id
            WHERE pi.purchase_id = %d
        ", $purchase_id));

            // Calculate totals
            $subtotal = 0;
            $total_tax = 0;
            $total_discount = 0;

            foreach ($purchase_items as $item) {
                $item_subtotal = floatval($item->purchase_qty) * floatval($item->price_per_unit);
                $item_discount = floatval($item->discount_amt);
                $taxable_amount = $item_subtotal - $item_discount;
                $item_tax = floatval($item->tax_amt);

                $subtotal += $taxable_amount;
                $total_tax += $item_tax;
                $total_discount += $item_discount;
            }

            // Add other charges tax if applicable
            $other_charges_tax = 0;
            if ($purchase->other_charges_tax_id > 0) {
                $other_tax_percent = floatval($wpdb->get_var($wpdb->prepare("SELECT tax FROM {$wpdb->prefix}orabooks_db_tax WHERE id = %d", $purchase->other_charges_tax_id)));
                $other_charges_tax = floatval($purchase->other_charges_input) * ($other_tax_percent / 100);
                $total_tax += $other_charges_tax;
            }

            // Add discount to all if applicable
            $total_discount += floatval($purchase->tot_discount_to_all_amt);

            // Fetch Currency
            $currency_code = $wpdb->get_var($wpdb->prepare("SELECT currency_code FROM {$wpdb->prefix}orabooks_db_currency WHERE id = (SELECT currency_id FROM {$wpdb->prefix}orabooks_db_store WHERE id = %d LIMIT 1)", $store_id));
            if (!$currency_code)
                $currency_code = 'BDT';

            // 1. Find Accounts from orabooks_ac_coa_list
            // Inventory Account (Prioritize 'Inventory')
            $inventory_account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_name = 'Inventory' OR account_code = '1100' OR account_name LIKE '%Inventory%' OR account_name LIKE '%Stock%') AND status=1 ORDER BY (account_name = 'Inventory') DESC LIMIT 1");

            // Accounts Payable Account (General AP)
            $ap_account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code = '2100' OR account_name LIKE '%Accounts Payable%' OR account_name LIKE '%Creditors%') AND status=1 LIMIT 1");

            // VAT/Tax Account (Prioritize 'VAT TAX Payable')
            $vat_account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_name = 'VAT TAX Payable' OR account_code = '2100' OR account_name LIKE '%Input Tax%' OR account_name LIKE '%VAT%' OR account_name LIKE '%Tax Payable%') AND status=1 ORDER BY (account_name = 'VAT TAX Payable') DESC LIMIT 1");

            // Discount/Income Account (Prioritize 'Purchase Discount')
            $discount_account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_name = 'Purchase Discount' OR account_code = '4000' OR account_name LIKE '%Purchase Discount%' OR account_name LIKE '%Discount Received%' OR account_name LIKE '%Discount Income%') AND status=1 ORDER BY (account_name = 'Purchase Discount') DESC LIMIT 1");

            // Cash/Bank Account (From payment record if available, otherwise general)
            $payment = $wpdb->get_row($wpdb->prepare("SELECT account_id FROM {$wpdb->prefix}orabooks_db_purchasepayments WHERE purchase_id = %d ORDER BY id DESC LIMIT 1", $purchase_id));
            $cash_account_id = $payment ? intval($payment->account_id) : 0;

            if ($cash_account_id > 0) {
                // Map from orabooks_ac_accounts to orabooks_ac_coa_list
                $ac_code = $wpdb->get_var($wpdb->prepare("SELECT account_code FROM {$wpdb->prefix}orabooks_ac_accounts WHERE id = %d", $cash_account_id));
                if ($ac_code) {
                    $coa_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE account_code = %s AND status=1 LIMIT 1", $ac_code));
                    if ($coa_id) {
                        $cash_account_id = intval($coa_id);
                    } else {
                        $cash_account_id = 0;
                    }
                } else {
                    $cash_account_id = 0;
                }
            }

            // Fallback to general Cash/Bank if mapping failed or no account was selected
            if (!$cash_account_id && $paid_amount > 0) {
                $cash_account_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE (account_code = '1000' OR account_name LIKE '%Cash%' OR account_name LIKE '%Bank%' OR account_name LIKE '%Petty%') AND status=1 LIMIT 1");
            }

            // Generate Entry Number
            $entry_number = 'JE-PUR-' . str_pad($purchase_id, 6, '0', STR_PAD_LEFT);

            // Check if journal entry already exists for this purchase
            $existing_entry_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orabooks_ac_journal_entry WHERE source_type = 'Purchase' AND source_id = %d", $purchase_id));
            if ($existing_entry_id) {
                if (class_exists('OBN_Fiscal_Period_Posting_Guard')) {
                    OBN_Fiscal_Period_Posting_Guard::assert_journal_entry_modifiable_or_fail($existing_entry_id);
                }
                $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_line', ['journal_entry_id' => $existing_entry_id]);
                $wpdb->delete($wpdb->prefix . 'orabooks_ac_journal_entry', ['id' => $existing_entry_id]);
            }

            // 2. Insert Journal Entry (wpf_orabooks_ac_journal_entry)
            $entry_data = [
                'store_id' => $store_id,
                'organization_id' => get_current_blog_id(),
                'entry_number' => $entry_number,
                'entry_date' => $purchase_date,
                'posting_date' => $purchase_date,
                'document_date' => $purchase_date,
                'reference_no' => $purchase->purchase_code,
                'description' => 'Purchase Journal Entry for ' . $purchase->purchase_code . ' (Received)',
                'source_type' => 'Purchase',
                'source_id' => $purchase_id,
                'status' => 'Posted',
                'currency' => $currency_code,
                'base_currency' => $currency_code,
                'total_debit' => $subtotal + $total_tax,  // Net purchase + VAT
                'total_credit' => $grand_total,           // Total paid + due
                'created_by' => $purchase->created_by,
                'created_at' => current_time('mysql'),
                'posted_at' => current_time('mysql'),
                'locked' => 1
            ];

            $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_entry', $entry_data);
            $journal_entry_id = $wpdb->insert_id;

            if (!$journal_entry_id)
                return;

            // 3. Insert Journal Lines (wpf_orabooks_ac_journal_line)
            $line_num = 1;

            // --- DEBIT: Inventory (Net Purchase Amount) ---
            if ($inventory_account_id && $subtotal > 0) {
                $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
                    'journal_entry_id' => $journal_entry_id,
                    'account_id' => $inventory_account_id,
                    'description' => 'Inventory - ' . $purchase->purchase_code,
                    'debit' => $subtotal,
                    'credit' => 0,
                    'debit_amt' => $subtotal,
                    'credit_amt' => 0,
                    'currency' => $currency_code,
                    'exchange_rate' => 1,
                    'amount_base' => $subtotal,
                    'line_number' => $line_num++,
                    'status' => 1
                ]);
            }

            // --- DEBIT: VAT/Input Tax (Tax Amount) ---
            if ($vat_account_id && $total_tax > 0) {
                $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
                    'journal_entry_id' => $journal_entry_id,
                    'account_id' => $vat_account_id,
                    'description' => 'VAT TAX Payable - ' . $purchase->purchase_code,
                    'debit' => $total_tax,
                    'credit' => 0,
                    'debit_amt' => $total_tax,
                    'credit_amt' => 0,
                    'currency' => $currency_code,
                    'exchange_rate' => 1,
                    'amount_base' => $total_tax,
                    'line_number' => $line_num++,
                    'status' => 1
                ]);
            }

            // --- CREDIT: Purchase Discount (Discount Amount) ---
            if ($discount_account_id && $total_discount > 0) {
                $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
                    'journal_entry_id' => $journal_entry_id,
                    'account_id' => $discount_account_id,
                    'description' => 'Purchase Discount - ' . $purchase->purchase_code,
                    'debit' => 0,
                    'credit' => $total_discount,
                    'debit_amt' => 0,
                    'credit_amt' => $total_discount,
                    'currency' => $currency_code,
                    'exchange_rate' => 1,
                    'amount_base' => $total_discount,
                    'line_number' => $line_num++,
                    'status' => 1
                ]);
            }

            // --- CREDIT: Cash/Bank (Paid Portion) ---
            if ($paid_amount > 0 && $cash_account_id) {
                $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
                    'journal_entry_id' => $journal_entry_id,
                    'account_id' => $cash_account_id,
                    'contact_id' => $supplier_id,
                    'description' => 'Cash/Bank Paid - ' . $purchase->purchase_code,
                    'debit' => 0,
                    'credit' => $paid_amount,
                    'debit_amt' => 0,
                    'credit_amt' => $paid_amount,
                    'currency' => $currency_code,
                    'exchange_rate' => 1,
                    'amount_base' => $paid_amount,
                    'line_number' => $line_num++,
                    'status' => 1
                ]);
            }

            // --- CREDIT: Accounts Payable (Due Portion) ---
            if ($due_amount > 0 && $ap_account_id) {
                $wpdb->insert($wpdb->prefix . 'orabooks_ac_journal_line', [
                    'journal_entry_id' => $journal_entry_id,
                    'account_id' => $ap_account_id,
                    'contact_id' => $supplier_id,
                    'description' => 'Accounts Payable - ' . $purchase->purchase_code,
                    'debit' => 0,
                    'credit' => $due_amount,
                    'debit_amt' => 0,
                    'credit_amt' => $due_amount,
                    'currency' => $currency_code,
                    'exchange_rate' => 1,
                    'amount_base' => $due_amount,
                    'line_number' => $line_num++,
                    'status' => 1
                ]);
            }
        }
    }

    new Frontend_Accounting_Purchases();
}
