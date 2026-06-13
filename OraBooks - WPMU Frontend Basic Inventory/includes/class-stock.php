<?php
if (!defined('ABSPATH')) exit;

class Frontend_Inventory_Stock {
    
    public function __construct() {
        // Stock Adjustment
        add_action('wp_ajax_save_adjustment', [$this, 'handle_save_adjustment']);
        add_action('wp_ajax_update_adjustment', [$this, 'handle_update_adjustment']);
        add_action('wp_ajax_delete_adjustment', [$this, 'handle_delete_adjustment']);
        
        // Stock Transfer
        add_action('wp_ajax_insert_transfer', [$this, 'handle_insert_transfer']);
        add_action('wp_ajax_update_transfer', [$this, 'handle_update_transfer']);
        add_action('wp_ajax_delete_transfer', [$this, 'handle_delete_transfer']);
        
        // Stock Search
        add_action('wp_ajax_search_stock_items', [$this, 'handle_search_stock_items']);
        add_action('wp_ajax_get_items_stock_batch', [$this, 'handle_get_items_stock_batch']);
    }

    // --- Search Stock Items ---
    public function handle_search_stock_items() {
        check_ajax_referer('frontend_ajax_nonce', 'security');
        if ( ! orabooks_can_access_inventory() ) wp_send_json_error('Access denied.');
        global $wpdb;

        $search = sanitize_text_field($_POST['search']);
        $warehouse_id = intval($_POST['warehouse_id']);

        if (empty($search)) {
            wp_send_json_success([]);
        }

        // Search items. If warehouse_id is provided, we might want to join with warehouseitems to show valid stock.
        // Or just search items and let user see 0 stock.
        // Source implies generic search but let's be smart.
        
        $sql = "SELECT i.id, i.item_name, i.item_code, i.sku, i.price, i.purchase_price, i.tax_id, i.tax_type,
                COALESCE(w.available_qty, 0) as stock
                FROM {$wpdb->prefix}orabooks_db_items i
                LEFT JOIN {$wpdb->prefix}orabooks_db_warehouseitems w ON i.id = w.item_id AND w.warehouse_id = %d
                WHERE (i.item_name LIKE %s OR i.item_code LIKE %s OR i.sku LIKE %s) AND i.status = 1
                LIMIT 20";
        
        $query = $wpdb->prepare($sql, $warehouse_id, '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%');
        $results = $wpdb->get_results($query);

        wp_send_json_success($results);
    }

    public function handle_get_items_stock_batch() {
        check_ajax_referer('frontend_ajax_nonce', 'security');
        if ( ! orabooks_can_access_inventory() ) wp_send_json_error('Access denied.');
        
        global $wpdb;
        $ids = isset($_POST['item_ids']) ? array_map('intval', $_POST['item_ids']) : [];
        $wh_id = intval($_POST['warehouse_id']);
        
        if(empty($ids)) wp_send_json_success([]);
        
        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        
        $query = $wpdb->prepare("
            SELECT item_id, available_qty as stock 
            FROM {$wpdb->prefix}orabooks_db_warehouseitems 
            WHERE warehouse_id = %d AND item_id IN ($ids_placeholder)
        ", array_merge([$wh_id], $ids));
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Format result
        $data = [];
        foreach($ids as $id) {
            $found = 0;
            foreach($results as $r) {
                if($r['item_id'] == $id) {
                    $found = floatval($r['stock']);
                    break;
                }
            }
            $data[] = ['item_id' => $id, 'stock' => $found];
        }
        
        wp_send_json_success($data);
    }

    // --- Save Adjustment ---
    public function handle_save_adjustment() {
        check_ajax_referer('frontend_ajax_nonce', 'security');
        if ( ! orabooks_can_access_inventory() ) wp_send_json_error('Access denied.');
        
        global $wpdb;
        
        $warehouse_id = intval($_POST['warehouse_id']);
        $adjustment_date = sanitize_text_field($_POST['adjustment_date']);
        $reference_no = sanitize_text_field($_POST['reference_no']);
        $adjustment_note = sanitize_textarea_field($_POST['adjustment_note']);
        $items = isset($_POST['items']) ? $_POST['items'] : [];
        
        if (empty($items)) {
            wp_send_json_error('No items provided');
        }
        
        $table_adj = $wpdb->prefix . 'orabooks_db_stockadjustment';
        $count_id = $wpdb->get_var($wpdb->prepare("SELECT MAX(count_id) FROM $table_adj WHERE store_id = %d", 1));
        $count_id = ($count_id) ? intval($count_id) + 1 : 1;

        $adjustment_data = [
            'store_id' => 1,
            'count_id' => $count_id,
            'warehouse_id' => $warehouse_id,
            'reference_no' => $reference_no,
            'adjustment_date' => $adjustment_date,
            'adjustment_note' => $adjustment_note,
            'created_date' => current_time('mysql', false), // Just date from source? source uses date('Y-m-d') often, but here mysql format.
            'created_time' => current_time('mysql', true),
            'created_by' => get_current_user_id(),
            'system_ip' => $_SERVER['REMOTE_ADDR'],
            'system_name' => 'System', // Placeholder
            'status' => 1
        ];
        
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'orabooks_db_stockadjustment',
            $adjustment_data
        );
        
        if (!$inserted) {
            wp_send_json_error('Failed to save adjustment: ' . $wpdb->last_error);
        }
        
        $adjustment_id = $wpdb->insert_id;
        
        foreach ($items as $item) {
            $item_id = intval($item['item_id']);
            $qty = floatval($item['qty']); // Can be negative
            
            $item_data = [
                'store_id' => 1,
                'warehouse_id' => $warehouse_id,
                'adjustment_id' => $adjustment_id,
                'item_id' => $item_id,
                'adjustment_qty' => $qty,
                'status' => 1
            ];
            
            $wpdb->insert(
                $wpdb->prefix . 'orabooks_db_stockadjustmentitems',
                $item_data
            );
            
            // Update warehouse stock
            // Check if record exists
            $current_stock_row = $wpdb->get_row($wpdb->prepare("
                SELECT id, available_qty FROM {$wpdb->prefix}orabooks_db_warehouseitems
                WHERE warehouse_id = %d AND item_id = %d
            ", $warehouse_id, $item_id));
            
            $current_stock = $current_stock_row ? floatval($current_stock_row->available_qty) : 0;
            $new_stock = $current_stock + $qty;
            
            if ($current_stock_row) {
                $wpdb->update(
                    $wpdb->prefix . 'orabooks_db_warehouseitems',
                    ['available_qty' => $new_stock],
                    ['id' => $current_stock_row->id]
                );
            } else {
                $wpdb->insert(
                    $wpdb->prefix . 'orabooks_db_warehouseitems',
                    [
                        'store_id' => 1,
                        'warehouse_id' => $warehouse_id,
                        'item_id' => $item_id,
                        'available_qty' => $new_stock
                    ]
                );
            }
        }
        
        wp_send_json_success('Adjustment saved successfully');
    }
    
    // --- Delete Adjustment ---
    public function handle_delete_adjustment() {
        check_ajax_referer('frontend_ajax_nonce', 'security');
        if ( ! orabooks_can_access_inventory() ) wp_send_json_error('Access denied.');
        
        global $wpdb;
        $id = intval($_POST['id']);
        
        // Get adjustment items
        $items = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}orabooks_db_stockadjustmentitems
            WHERE adjustment_id = %d
        ", $id));
        
        // Reverse stock
        foreach ($items as $item) {
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}orabooks_db_warehouseitems 
                SET available_qty = available_qty - %f 
                WHERE warehouse_id = %d AND item_id = %d
            ", floatval($item->adjustment_qty), $item->warehouse_id, $item->item_id));
        }
        
        // Delete items
        $wpdb->delete($wpdb->prefix . 'orabooks_db_stockadjustmentitems', ['adjustment_id' => $id]);
        
        // Delete adjustment
        $wpdb->delete($wpdb->prefix . 'orabooks_db_stockadjustment', ['id' => $id]);
        
        wp_send_json_success('Adjustment deleted');
    }

    // --- Insert Transfer ---
    public function handle_insert_transfer() {
        check_ajax_referer('frontend_ajax_nonce', 'security');
        if ( ! orabooks_can_access_inventory() ) wp_send_json_error('Access denied.');
        global $wpdb;
        
        $date = sanitize_text_field($_POST['transfer_date']);
        $from = intval($_POST['warehouse_from']);
        $to = intval($_POST['warehouse_to']);
        $note = sanitize_textarea_field($_POST['note']);
        $items = isset($_POST['items']) ? $_POST['items'] : []; // Should be array of item objects
        
        // Since serialize was used in source, items might be key-value pairs. 
        // But source JS used `items[index][item_id]`, which PHP receives as array.
        
        if (!$from || !$to || empty($items)) {
            wp_send_json_error('Invalid data');
        }

        if ($from == $to) {
             wp_send_json_error('Source and Destination warehouses cannot be the same');
        }
        
        $table_transfer = $wpdb->prefix . 'orabooks_db_stocktransfer';
        $count_id = $wpdb->get_var($wpdb->prepare("SELECT MAX(count_id) FROM $table_transfer WHERE store_id = %d", 1));
        $count_id = ($count_id) ? intval($count_id) + 1 : 1;

        $wpdb->insert(
            $table_transfer,
            [
                'store_id' => 1,
                'count_id' => $count_id,
                'warehouse_from' => $from,
                'warehouse_to' => $to,
                'transfer_date' => $date,
                'note' => $note,
                'created_by' => get_current_user_id(),
                'created_date' => current_time('mysql'),
                'status' => 1
            ]
        );
        $transfer_id = $wpdb->insert_id;
        
        foreach ($items as $item) {
            $item_id = intval($item['item_id']);
            $qty = floatval($item['qty']);
            
            if ($qty <= 0) continue;

            $wpdb->insert(
                $wpdb->prefix . 'orabooks_db_stocktransferitems',
                [
                    'stocktransfer_id' => $transfer_id,
                    'store_id' => 1,
                    'warehouse_from' => $from,
                    'warehouse_to' => $to,
                    'item_id' => $item_id,
                    'transfer_qty' => $qty,
                    'status' => 1
                ]
            );
            
            // Decrease From
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}orabooks_db_warehouseitems 
                SET available_qty = available_qty - %f 
                WHERE warehouse_id = %d AND item_id = %d
            ", $qty, $from, $item_id));
            
            // Increase To
            $exists = $wpdb->get_var($wpdb->prepare("
                SELECT id FROM {$wpdb->prefix}orabooks_db_warehouseitems 
                WHERE warehouse_id = %d AND item_id = %d
            ", $to, $item_id));
            
            if ($exists) {
                $wpdb->query($wpdb->prepare("
                    UPDATE {$wpdb->prefix}orabooks_db_warehouseitems 
                    SET available_qty = available_qty + %f 
                    WHERE warehouse_id = %d AND item_id = %d
                ", $qty, $to, $item_id));
            } else {
                $wpdb->insert(
                    $wpdb->prefix . 'orabooks_db_warehouseitems',
                    [
                        'store_id' => 1,
                        'warehouse_id' => $to,
                        'item_id' => $item_id,
                        'available_qty' => $qty
                    ]
                );
            }
        }
        
        wp_send_json_success('Transfer saved successfully');
    }
    
    public function handle_delete_transfer() {
         check_ajax_referer('frontend_ajax_nonce', 'security');
         if ( ! orabooks_can_access_inventory() ) wp_send_json_error('Access denied.');
         global $wpdb;
         $id = intval($_POST['id']);
         
         // Get transfer
         $transfer = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_stocktransfer WHERE id = %d", $id));
         if (!$transfer) wp_send_json_error('Transfer not found');
         
         $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}orabooks_db_stocktransferitems WHERE stocktransfer_id = %d", $id));
         
         foreach ($items as $item) {
             $qty = floatval($item->transfer_qty);
             // Revert: Add back to FROM, Subtract from TO
             
             // From: Add
             $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_warehouseitems SET available_qty = available_qty + %f WHERE warehouse_id = %d AND item_id = %d", $qty, $item->warehouse_from, $item->item_id));
             
             // To: Subtract
              $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}orabooks_db_warehouseitems SET available_qty = available_qty - %f WHERE warehouse_id = %d AND item_id = %d", $qty, $item->warehouse_to, $item->item_id));
         }
         
         $wpdb->delete($wpdb->prefix . 'orabooks_db_stocktransferitems', ['stocktransfer_id' => $id]);
         $wpdb->delete($wpdb->prefix . 'orabooks_db_stocktransfer', ['id' => $id]);
         
         wp_send_json_success('Transfer deleted successfully');
    }
    
    // --- Update Adjustment ---
    public function handle_update_adjustment() {
        check_ajax_referer('frontend_ajax_nonce', 'security');
        if ( ! orabooks_can_access_inventory() ) wp_send_json_error('Access denied.');
        
        global $wpdb;
        
        $adjustment_id = intval($_POST['adjustment_id']);
        $warehouse_id = intval($_POST['warehouse_id']);
        $adjustment_date = sanitize_text_field($_POST['adjustment_date']);
        $reference_no = sanitize_text_field($_POST['reference_no']);
        $adjustment_note = sanitize_textarea_field($_POST['adjustment_note']);
        $items = isset($_POST['items']) ? $_POST['items'] : [];
        
        if (empty($items)) {
            wp_send_json_error('No items provided');
        }
        
        // Get old items to reverse stock changes
        $old_items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}orabooks_db_stockadjustmentitems WHERE adjustment_id = %d",
            $adjustment_id
        ));
        
        // Reverse old stock changes
        foreach ($old_items as $old_item) {
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}orabooks_db_warehouseitems 
                SET available_qty = available_qty - %f 
                WHERE warehouse_id = %d AND item_id = %d
            ", floatval($old_item->adjustment_qty), $old_item->warehouse_id, $old_item->item_id));
        }
        
        // Delete old items
        $wpdb->delete($wpdb->prefix . 'orabooks_db_stockadjustmentitems', ['adjustment_id' => $adjustment_id]);
        
        // Update adjustment header
        $wpdb->update(
            $wpdb->prefix . 'orabooks_db_stockadjustment',
            [
                'warehouse_id' => $warehouse_id,
                'adjustment_date' => $adjustment_date,
                'adjustment_note' => $adjustment_note
            ],
            ['id' => $adjustment_id]
        );
        
        // Insert new items and update stock
        foreach ($items as $item) {
            $item_id = intval($item['item_id']);
            $qty = floatval($item['qty']);
            
            $item_data = [
                'store_id' => 1,
                'warehouse_id' => $warehouse_id,
                'adjustment_id' => $adjustment_id,
                'item_id' => $item_id,
                'adjustment_qty' => $qty,
                'status' => 1
            ];
            
            $wpdb->insert(
                $wpdb->prefix . 'orabooks_db_stockadjustmentitems',
                $item_data
            );
            
            // Update warehouse stock
            $current_stock_row = $wpdb->get_row($wpdb->prepare("
                SELECT id, available_qty FROM {$wpdb->prefix}orabooks_db_warehouseitems
                WHERE warehouse_id = %d AND item_id = %d
            ", $warehouse_id, $item_id));
            
            $current_stock = $current_stock_row ? floatval($current_stock_row->available_qty) : 0;
            $new_stock = $current_stock + $qty;
            
            if ($current_stock_row) {
                $wpdb->update(
                    $wpdb->prefix . 'orabooks_db_warehouseitems',
                    ['available_qty' => $new_stock],
                    ['id' => $current_stock_row->id]
                );
            } else {
                $wpdb->insert(
                    $wpdb->prefix . 'orabooks_db_warehouseitems',
                    [
                        'store_id' => 1,
                        'warehouse_id' => $warehouse_id,
                        'item_id' => $item_id,
                        'available_qty' => $new_stock
                    ]
                );
            }
        }
        
        wp_send_json_success('Adjustment updated successfully');
    }
    
    // --- Update Transfer ---
    public function handle_update_transfer() {
        check_ajax_referer('frontend_ajax_nonce', 'security');
        if ( ! orabooks_can_access_inventory() ) wp_send_json_error('Access denied.');
        global $wpdb;
        
        $transfer_id = intval($_POST['transfer_id']);
        $date = sanitize_text_field($_POST['transfer_date']);
        $from = intval($_POST['warehouse_from']);
        $to = intval($_POST['warehouse_to']);
        $note = sanitize_textarea_field($_POST['note']);
        $items = isset($_POST['items']) ? $_POST['items'] : [];
        
        if (!$from || !$to || empty($items)) {
            wp_send_json_error('Invalid data');
        }

        if ($from == $to) {
             wp_send_json_error('Source and Destination warehouses cannot be the same');
        }
        
        // Get old transfer to reverse stock
        $old_transfer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}orabooks_db_stocktransfer WHERE id = %d",
            $transfer_id
        ));
        
        if (!$old_transfer) wp_send_json_error('Transfer not found');
        
        $old_items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}orabooks_db_stocktransferitems WHERE stocktransfer_id = %d",
            $transfer_id
        ));
        
        // Reverse old transfer
        foreach ($old_items as $old_item) {
            $qty = floatval($old_item->transfer_qty);
            // Add back to FROM
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}orabooks_db_warehouseitems 
                SET available_qty = available_qty + %f 
                WHERE warehouse_id = %d AND item_id = %d
            ", $qty, $old_item->warehouse_from, $old_item->item_id));
            
            // Subtract from TO
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}orabooks_db_warehouseitems 
                SET available_qty = available_qty - %f 
                WHERE warehouse_id = %d AND item_id = %d
            ", $qty, $old_item->warehouse_to, $old_item->item_id));
        }
        
        // Delete old items
        $wpdb->delete($wpdb->prefix . 'orabooks_db_stocktransferitems', ['stocktransfer_id' => $transfer_id]);
        
        // Update transfer header
        $wpdb->update(
            $wpdb->prefix . 'orabooks_db_stocktransfer',
            [
                'warehouse_from' => $from,
                'warehouse_to' => $to,
                'transfer_date' => $date,
                'note' => $note
            ],
            ['id' => $transfer_id]
        );
        
        // Insert new items and update stock
        foreach ($items as $item) {
            $item_id = intval($item['item_id']);
            $qty = floatval($item['qty']);
            
            if ($qty <= 0) continue;

            $wpdb->insert(
                $wpdb->prefix . 'orabooks_db_stocktransferitems',
                [
                    'stocktransfer_id' => $transfer_id,
                    'store_id' => 1,
                    'warehouse_from' => $from,
                    'warehouse_to' => $to,
                    'item_id' => $item_id,
                    'transfer_qty' => $qty,
                    'status' => 1
                ]
            );
            
            // Decrease From
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}orabooks_db_warehouseitems 
                SET available_qty = available_qty - %f 
                WHERE warehouse_id = %d AND item_id = %d
            ", $qty, $from, $item_id));
            
            // Increase To
            $exists = $wpdb->get_var($wpdb->prepare("
                SELECT id FROM {$wpdb->prefix}orabooks_db_warehouseitems 
                WHERE warehouse_id = %d AND item_id = %d
            ", $to, $item_id));
            
            if ($exists) {
                $wpdb->query($wpdb->prepare("
                    UPDATE {$wpdb->prefix}orabooks_db_warehouseitems 
                    SET available_qty = available_qty + %f 
                    WHERE warehouse_id = %d AND item_id = %d
                ", $qty, $to, $item_id));
            } else {
                $wpdb->insert(
                    $wpdb->prefix . 'orabooks_db_warehouseitems',
                    [
                        'store_id' => 1,
                        'warehouse_id' => $to,
                        'item_id' => $item_id,
                        'available_qty' => $qty
                    ]
                );
            }
        }
        
        wp_send_json_success('Transfer updated successfully');
    }
}

