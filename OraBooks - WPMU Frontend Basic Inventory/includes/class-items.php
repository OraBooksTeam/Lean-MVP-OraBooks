<?php
if (!defined('ABSPATH')) {
    exit;
}

class Frontend_Inventory_Items
{

    public static function init()
    {
        add_action('wp_ajax_frontend_insert_item', array(__CLASS__, 'handle_insert_item'));

        // Update Item
        add_action('wp_ajax_frontend_update_item', array(__CLASS__, 'handle_update_item'));

        // Items List Actions (from View Items)
        add_action('wp_ajax_frontend_delete_item', array(__CLASS__, 'handle_delete_item'));
        add_action('wp_ajax_frontend_update_item_status', array(__CLASS__, 'handle_update_status'));

        // Search Items
        add_action('wp_ajax_search_items', array(__CLASS__, 'handle_search_items'));

        // Generate Item Code
        add_action('wp_ajax_generate_item_code', array(__CLASS__, 'handle_generate_item_code'));

        // Import Items
        add_action('wp_ajax_frontend_import_items', array(__CLASS__, 'handle_import_items'));
        add_action('wp_ajax_frontend_download_item_template', array(__CLASS__, 'handle_download_item_template'));
    }

    public static function handle_generate_item_code()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        global $wpdb;
        $items_table = $wpdb->prefix . 'orabooks_db_items';

        $last_item = $wpdb->get_row("SELECT count_id FROM $items_table ORDER BY id DESC LIMIT 1");
        $count_id = ($last_item && $last_item->count_id) ? $last_item->count_id + 1 : 1;
        $item_code = 'ITM-' . str_pad($count_id, 6, '0', STR_PAD_LEFT);

        wp_send_json_success(array(
            'item_code' => $item_code,
            'count_id' => $count_id
        ));
    }

    public static function handle_update_item()
    {
        // Verify Nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'frontend_ajax_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        if (!orabooks_can_access_inventory()) {
            wp_send_json_error(array('message' => 'Access denied.'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_db_items';

        // Get item ID
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        if (!$item_id) {
            wp_send_json_error(array('message' => 'Invalid item ID.'));
        }

        // Sanitization & Validation
        $item_code = sanitize_text_field($_POST['item_code']);
        $item_name = sanitize_text_field($_POST['item_name']);
        $price = floatval($_POST['price']);

        if (empty($item_code) || empty($item_name)) {
            wp_send_json_error(array('message' => 'Code and Name are required.'));
        }

        $item_type = isset($_POST['item_type']) ? sanitize_text_field($_POST['item_type']) : 'Single';

        $data = array(
            'item_code' => $item_code,
            'item_name' => $item_name,
            'store_id' => intval($_POST['store_id']),
            'brand_id' => isset($_POST['brand_id']) ? intval($_POST['brand_id']) : 0,
            'category_id' => intval($_POST['category_id']),
            'unit_id' => isset($_POST['unit_id']) ? intval($_POST['unit_id']) : 0,
            'sku' => isset($_POST['sku']) ? sanitize_text_field($_POST['sku']) : '',
            'hsn' => isset($_POST['hsn']) ? sanitize_text_field($_POST['hsn']) : '',
            'alert_qty' => isset($_POST['alert_qty']) ? floatval($_POST['alert_qty']) : 0,
            'seller_points' => isset($_POST['seller_points']) ? sanitize_text_field($_POST['seller_points']) : '',
            'custom_barcode' => isset($_POST['custom_barcode']) ? sanitize_text_field($_POST['custom_barcode']) : '',
            'description' => sanitize_textarea_field($_POST['description']),
            'discount_type' => isset($_POST['discount_type']) ? sanitize_text_field($_POST['discount_type']) : 'Percentage',
            'discount' => isset($_POST['discount']) ? floatval($_POST['discount']) : 0,
            'price' => $price,
            'tax_id' => intval($_POST['tax_id']),
            'purchase_price' => isset($_POST['purchase_price']) ? floatval($_POST['purchase_price']) : 0,
            'tax_type' => sanitize_text_field($_POST['tax_type']),
            'profit_margin' => isset($_POST['profit_margin']) ? floatval($_POST['profit_margin']) : 0.00,
            'sales_price' => floatval($_POST['sales_price']),
            'mrp' => isset($_POST['mrp']) ? floatval($_POST['mrp']) : 0,
            'warehouse_id' => isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0,
            'stock' => isset($_POST['adjustment_qty']) ? floatval($_POST['adjustment_qty']) : 0.00,
            'item_group' => $item_type === 'Variants' ? 'Variants' : 'Single'
        );

        // Handle Image Upload
        if (!empty($_FILES['item_image']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $attachment_id = media_handle_upload('item_image', 0);

            if (is_wp_error($attachment_id)) {
                wp_send_json_error(array('message' => 'Image upload failed: ' . $attachment_id->get_error_message()));
            } else {
                $data['item_image'] = wp_get_attachment_url($attachment_id);
            }
        }

        // Update
        $updated = $wpdb->update($table_name, $data, array('id' => $item_id));

        if ($updated !== false) {
            // Update warehouse items table with stock changes
            $adjustment_qty = isset($_POST['adjustment_qty']) ? floatval($_POST['adjustment_qty']) : 0;
            $warehouse_id = isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0;

            if ($warehouse_id > 0) {
                $warehouse_items_table = $wpdb->prefix . 'orabooks_db_warehouseitems';

                // Check if warehouse item record exists
                $existing_warehouse_item = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, available_qty FROM $warehouse_items_table WHERE warehouse_id = %d AND item_id = %d",
                    $warehouse_id,
                    $item_id
                ));

                if ($existing_warehouse_item) {
                    // Update existing record
                    $wpdb->update(
                        $warehouse_items_table,
                        array('available_qty' => $adjustment_qty),
                        array('id' => $existing_warehouse_item->id)
                    );
                } else {
                    // Insert new record
                    $wpdb->insert(
                        $warehouse_items_table,
                        array(
                            'store_id' => intval($_POST['store_id']),
                            'warehouse_id' => $warehouse_id,
                            'item_id' => $item_id,
                            'available_qty' => $adjustment_qty
                        )
                    );
                }
            }

            wp_send_json_success(array('message' => 'Item updated successfully!'));
        } else {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        }
    }

    public static function handle_insert_item()
    {
        // Verify Nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'frontend_ajax_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        if (!orabooks_can_access_inventory()) {
            wp_send_json_error(array('message' => 'Access denied.'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_db_items';

        // Sanitization & Validation
        $item_code = sanitize_text_field($_POST['item_code']);
        $item_name = sanitize_text_field($_POST['item_name']);
        $price = floatval($_POST['price']);

        // Prevent Duplicate: Check if item_code already exists
        if (!empty($item_code)) {
            $existing_item = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE item_code = %s", $item_code));
            if ($existing_item) {
                wp_send_json_error(array('message' => 'Duplicate Entry: Item code ' . $item_code . ' already exists.'));
            }
        }

        if (empty($item_code) || empty($item_name)) {
            wp_send_json_error(array('message' => 'Code and Name are required.'));
        }

        $service_bit = isset($_POST['service_bit']) ? intval($_POST['service_bit']) : 0;
        $item_type = isset($_POST['item_type']) ? sanitize_text_field($_POST['item_type']) : '';

        $data = array(
            'item_code' => $item_code,
            'item_name' => $item_name,
            'store_id' => intval($_POST['store_id']),
            'brand_id' => isset($_POST['brand_id']) ? intval($_POST['brand_id']) : 0,
            'category_id' => intval($_POST['category_id']),
            'unit_id' => isset($_POST['unit_id']) ? intval($_POST['unit_id']) : 0,
            'sku' => isset($_POST['sku']) ? sanitize_text_field($_POST['sku']) : '',
            'hsn' => isset($_POST['hsn']) ? sanitize_text_field($_POST['hsn']) : '',
            'alert_qty' => isset($_POST['alert_qty']) ? floatval($_POST['alert_qty']) : 0,
            'seller_points' => isset($_POST['seller_points']) ? sanitize_text_field($_POST['seller_points']) : '',
            'custom_barcode' => isset($_POST['custom_barcode']) ? sanitize_text_field($_POST['custom_barcode']) : '',
            'description' => sanitize_textarea_field($_POST['description']),
            'discount_type' => isset($_POST['discount_type']) ? sanitize_text_field($_POST['discount_type']) : 'Percentage',
            'discount' => isset($_POST['discount']) ? floatval($_POST['discount']) : 0,
            'price' => $price,
            'tax_id' => intval($_POST['tax_id']),
            'purchase_price' => isset($_POST['purchase_price']) ? floatval($_POST['purchase_price']) : 0,
            'tax_type' => sanitize_text_field($_POST['tax_type']),
            'profit_margin' => isset($_POST['profit_margin']) ? floatval($_POST['profit_margin']) : 0.00,
            'sales_price' => floatval($_POST['sales_price']),
            'mrp' => isset($_POST['mrp']) ? floatval($_POST['mrp']) : 0,
            'warehouse_id' => isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0,
            'stock' => isset($_POST['adjustment_qty']) ? floatval($_POST['adjustment_qty']) : 0.00,
            'count_id' => intval($_POST['count_id']),
            'created_date' => current_time('mysql'),
            'status' => 1,
            'service_bit' => $service_bit,
            'item_type' => $item_type === 'Variants' ? 'Variants' : 'Single'
        );

        // Handle Image Upload
        if (!empty($_FILES['item_image']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $attachment_id = media_handle_upload('item_image', 0);

            if (is_wp_error($attachment_id)) {
                wp_send_json_error(array('message' => 'Image upload failed: ' . $attachment_id->get_error_message()));
            } else {
                $data['item_image'] = wp_get_attachment_url($attachment_id);
            }
        }

        // Insert
        $inserted = $wpdb->insert($table_name, $data);

        if ($inserted) {
            $last_id = $wpdb->insert_id;

            // Update warehouse items table with opening stock
            $adjustment_qty = isset($_POST['adjustment_qty']) ? floatval($_POST['adjustment_qty']) : 0;
            $warehouse_id = isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0;

            if ($adjustment_qty > 0 && $warehouse_id > 0) {
                $warehouse_items_table = $wpdb->prefix . 'orabooks_db_warehouseitems';

                // Check if warehouse item record exists
                $existing_warehouse_item = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, available_qty FROM $warehouse_items_table WHERE warehouse_id = %d AND item_id = %d",
                    $warehouse_id,
                    $last_id
                ));

                if ($existing_warehouse_item) {
                    // Update existing record
                    $new_qty = $existing_warehouse_item->available_qty + $adjustment_qty;
                    $wpdb->update(
                        $warehouse_items_table,
                        array('available_qty' => $new_qty),
                        array('id' => $existing_warehouse_item->id)
                    );
                } else {
                    // Insert new record
                    $wpdb->insert(
                        $warehouse_items_table,
                        array(
                            'store_id' => intval($_POST['store_id']),
                            'warehouse_id' => $warehouse_id,
                            'item_id' => $last_id,
                            'available_qty' => $adjustment_qty
                        )
                    );
                }
            }

            $tax_percent = 0;
            if ($tax_id) {
                $tax_row = $wpdb->get_row($wpdb->prepare("SELECT tax FROM {$wpdb->prefix}orabooks_db_tax WHERE id = %d", $tax_id));
                if ($tax_row) {
                    $tax_percent = $tax_row->tax;
                }
            }
            wp_send_json_success(array(
                'message' => 'Item added successfully!',
                'item_id' => $last_id,
                'item_name' => $item_name,
                'item_code' => $item_code,
                'sku' => isset($_POST['sku']) ? sanitize_text_field($_POST['sku']) : '',
                'price' => $price,
                'tax_percent' => $tax_percent
            ));
        } else {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        }
    }

    public static function handle_delete_item()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if (!orabooks_can_access_inventory()) {
            wp_send_json_error(array('message' => 'Access denied.'));
        }

        global $wpdb;
        $id = intval($_POST['id']);
        $table = $wpdb->prefix . 'orabooks_db_items';

        $deleted = $wpdb->delete($table, array('id' => $id));

        if ($deleted) {
            wp_send_json_success('Item deleted');
        } else {
            wp_send_json_error('Failed to delete');
        }
    }

    public static function handle_update_status()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if (!orabooks_can_access_inventory()) {
            wp_send_json_error(array('message' => 'Access denied.'));
        }

        global $wpdb;
        $id = intval($_POST['id']);
        $status = intval($_POST['status']);
        $table = $wpdb->prefix . 'orabooks_db_items';

        $updated = $wpdb->update($table, array('status' => $status), array('id' => $id));

        if ($updated !== false) {
            wp_send_json_success('Status updated');
        } else {
            wp_send_json_error('Failed to update status');
        }
    }

    public static function handle_search_items()
    {
        // Verify nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'frontend_ajax_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }

        if (!orabooks_can_access_inventory()) {
            wp_send_json_error(array('message' => 'Access denied.'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'orabooks_db_items';

        $search_query = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        $where = '';
        if (!empty($search_query)) {
            $where = $wpdb->prepare(
                "WHERE i.item_name LIKE %s OR i.item_code LIKE %s OR b.brand_name LIKE %s",
                "%{$search_query}%",
                "%{$search_query}%",
                "%{$search_query}%"
            );
        }

        // Get items
        $items = $wpdb->get_results("SELECT i.*, b.brand_name as brand, c.category_name as category 
                                     FROM $table_name i 
                                     LEFT JOIN {$wpdb->prefix}orabooks_db_brands b ON i.brand_id = b.id 
                                     LEFT JOIN {$wpdb->prefix}orabooks_db_category c ON i.category_id = c.id 
                                     $where 
                                     ORDER BY i.id DESC LIMIT 100");

        if ($items !== false) {
            wp_send_json_success(array('items' => $items));
        } else {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        }
    }

    public static function handle_download_item_template()
    {
        if (!orabooks_can_access_inventory()) {
            wp_die('Access denied.');
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=items_import_template.csv');

        $output = fopen('php://output', 'w');

        // Headers
        fputcsv($output, array(
            'Item Name',
            'Item Code',
            'Category',
            'Brand',
            'Unit',
            'SKU',
            'HSN',
            'Alert Quantity',
            'Base Price',
            'Tax Name',
            'Tax Type',
            'Profit Margin %',
            'Sales Price',
            'MRP',
            'Opening Stock'
        ));

        // Sample Data Row
        fputcsv($output, array(
            'Sample Item',
            'ITM-000001',
            'General',
            'Generic',
            'pcs',
            'SKU001',
            '1234',
            '10',
            '100',
            'None',
            'Inclusive',
            '20',
            '120',
            '120',
            '50'
        ));

        fclose($output);
        exit;
    }

    public static function handle_import_items()
    {
        check_ajax_referer('frontend_ajax_nonce', 'security');

        if (!orabooks_can_access_inventory()) {
            wp_send_json_error(array('message' => 'Access denied.'));
        }

        if (empty($_FILES['item_csv']['tmp_name'])) {
            wp_send_json_error(array('message' => 'Please upload a CSV file.'));
        }

        global $wpdb;
        $warehouse_id = isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0;

        if (empty($warehouse_id)) {
            wp_send_json_error(array('message' => 'Please select a warehouse.'));
        }

        $items_table = $wpdb->prefix . 'orabooks_db_items';
        $brands_table = $wpdb->prefix . 'orabooks_db_brands';
        $categories_table = $wpdb->prefix . 'orabooks_db_category';
        $units_table = $wpdb->prefix . 'orabooks_db_units';
        $tax_table = $wpdb->prefix . 'orabooks_db_tax';

        $file = $_FILES['item_csv']['tmp_name'];
        $handle = fopen($file, 'r');

        if ($handle === false) {
            wp_send_json_error(array('message' => 'Failed to open file.'));
        }

        // Read header row
        $headers = fgetcsv($handle);

        $imported_count = 0;
        $error_count = 0;
        $errors = array();

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2)
                continue; // Skip empty rows

            // Map columns (adjust indices based on template generated above)
            $item_name = sanitize_text_field($row[0]);
            $item_code = sanitize_text_field($row[1]);
            $category_name = sanitize_text_field($row[2]);
            $brand_name = sanitize_text_field($row[3]);
            $unit_name = sanitize_text_field($row[4]);
            $sku = sanitize_text_field($row[5]);
            $hsn = sanitize_text_field($row[6]);
            $alert_qty = floatval($row[7]);
            $base_price = floatval($row[8]);
            $tax_name = sanitize_text_field($row[9]);
            $tax_type = sanitize_text_field($row[10]);
            $profit_margin = floatval($row[11]);
            $sales_price = floatval($row[12]);
            $mrp = floatval($row[13]);
            $stock = floatval($row[14]);

            if (empty($item_name)) {
                $error_count++;
                $errors[] = "Row " . ($imported_count + $error_count + 1) . ": Item Name is required.";
                continue;
            }

            // Look up or Create Category
            $category_id = 1; // Default
            if (!empty($category_name)) {
                $cat = $wpdb->get_row($wpdb->prepare("SELECT id FROM $categories_table WHERE category_name = %s", $category_name));
                if ($cat) {
                    $category_id = $cat->id;
                } else {
                    $wpdb->insert($categories_table, array('category_name' => $category_name, 'status' => 1, 'store_id' => 1));
                    $category_id = $wpdb->insert_id;
                }
            }

            // Look up or Create Brand
            $brand_id = 0;
            if (!empty($brand_name)) {
                $brand = $wpdb->get_row($wpdb->prepare("SELECT id FROM $brands_table WHERE brand_name = %s", $brand_name));
                if ($brand) {
                    $brand_id = $brand->id;
                } else {
                    $wpdb->insert($brands_table, array('brand_name' => $brand_name, 'status' => 1, 'store_id' => 1));
                    $brand_id = $wpdb->insert_id;
                }
            }

            // Look up or Create Unit
            $unit_id = 0;
            if (!empty($unit_name)) {
                $unit = $wpdb->get_row($wpdb->prepare("SELECT id FROM $units_table WHERE unit_name = %s", $unit_name));
                if ($unit) {
                    $unit_id = $unit->id;
                } else {
                    $wpdb->insert($units_table, array('unit_name' => $unit_name, 'status' => 1, 'store_id' => 1));
                    $unit_id = $wpdb->insert_id;
                }
            }

            // Look up Tax
            $tax_id = 0;
            if (!empty($tax_name) && strtolower($tax_name) !== 'none') {
                $tax = $wpdb->get_row($wpdb->prepare("SELECT id FROM $tax_table WHERE tax_name = %s", $tax_name));
                if ($tax) {
                    $tax_id = $tax->id;
                }
            }

            // Auto-generate code if empty
            if (empty($item_code)) {
                $last_item = $wpdb->get_row("SELECT count_id FROM $items_table ORDER BY id DESC LIMIT 1");
                $count_id = ($last_item && $last_item->count_id) ? $last_item->count_id + 1 : 1;
                $item_code = 'ITM-' . str_pad($count_id, 6, '0', STR_PAD_LEFT);
            } else {
                // Check if code exists to avoid duplicate codes if we decide to treat it as unique
                $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $items_table WHERE item_code = %s", $item_code));
                if ($existing) {
                    // Update instead of insert? For now, let's just create a new one with a suffix or skip
                    // Or let's just allow it if the DB allows it.
                }

                // Try to get count_id from code or just use next available
                $last_item = $wpdb->get_row("SELECT count_id FROM $items_table ORDER BY id DESC LIMIT 1");
                $count_id = ($last_item && $last_item->count_id) ? $last_item->count_id + 1 : 1;
            }

            $data = array(
                'store_id' => 1,
                'count_id' => $count_id,
                'item_code' => $item_code,
                'item_name' => $item_name,
                'category_id' => $category_id,
                'brand_id' => $brand_id,
                'unit_id' => $unit_id,
                'sku' => $sku,
                'hsn' => $hsn,
                'alert_qty' => $alert_qty,
                'price' => $base_price,
                'tax_id' => $tax_id,
                'tax_type' => $tax_type ?: 'Inclusive',
                'profit_margin' => $profit_margin,
                'sales_price' => $sales_price,
                'mrp' => $mrp ?: $sales_price,
                'warehouse_id' => $warehouse_id,
                'stock' => $stock,
                'created_date' => current_time('mysql'),
                'status' => 1,
                'item_type' => 'Single'
            );

            $inserted = $wpdb->insert($items_table, $data);
            if ($inserted) {
                $imported_count++;
            } else {
                $error_count++;
                $errors[] = "Row " . ($imported_count + $error_count) . ": Database error - " . $wpdb->last_error;
            }
        }

        fclose($handle);

        wp_send_json_success(array(
            'message' => "Import completed! $imported_count items imported.",
            'imported' => $imported_count,
            'errors' => $errors
        ));
    }
}

Frontend_Inventory_Items::init();
