<?php
/**
 * Items AJAX handlers for the Accounting module
 */
if (!defined('ABSPATH')) exit;

class OBN_Items {

    public function __construct() {
        add_action('wp_ajax_obn_insert_item', [__CLASS__, 'handle_insert_item']);
        add_action('wp_ajax_nopriv_obn_insert_item', [__CLASS__, 'handle_insert_item']);
        add_action('wp_ajax_obn_update_item', [__CLASS__, 'handle_update_item']);
        add_action('wp_ajax_nopriv_obn_update_item', [__CLASS__, 'handle_update_item']);
        add_action('wp_ajax_obn_update_item_status', [__CLASS__, 'handle_update_item_status']);
        add_action('wp_ajax_nopriv_obn_update_item_status', [__CLASS__, 'handle_update_item_status']);
        add_action('wp_ajax_obn_search_items', [__CLASS__, 'handle_search_items']);
        add_action('wp_ajax_nopriv_obn_search_items', [__CLASS__, 'handle_search_items']);
        add_action('wp_ajax_obn_import_items', [__CLASS__, 'handle_import_items']);
        add_action('wp_ajax_nopriv_obn_import_items', [__CLASS__, 'handle_import_items']);
        add_action('wp_ajax_obn_download_item_template', [__CLASS__, 'handle_download_item_template']);
        add_action('wp_ajax_nopriv_obn_download_item_template', [__CLASS__, 'handle_download_item_template']);
        add_action('wp_ajax_obn_get_item', [__CLASS__, 'handle_get_item']);
        add_action('wp_ajax_nopriv_obn_get_item', [__CLASS__, 'handle_get_item']);
        add_action('wp_ajax_obn_delete_item', [__CLASS__, 'handle_delete_item']);
        add_action('wp_ajax_nopriv_obn_delete_item', [__CLASS__, 'handle_delete_item']);
    }

    public static function handle_update_item() {
        check_ajax_referer('obn_auth_nonce', 'security');
        if (!is_user_logged_in()) wp_die('Unauthorized');

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_items';
        $id = intval($_POST['id'] ?? 0);

        // Duplicate Check
        $where = $id > 0 ? $wpdb->prepare( " AND id != %d", $id ) : "";
        $item_code = sanitize_text_field($_POST['item_code'] ?? '');
        $item_name = sanitize_text_field($_POST['item_name'] ?? '');

        $code_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE item_code = %s $where", $item_code));
        if ($code_exists) {
            wp_send_json_error("Warning: The item code '$item_code' already exists. Duplicate entry not allowed.");
        }

        $name_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE item_name = %s $where", $item_name));
        if ($name_exists) {
            wp_send_json_error("Warning: The item name '$item_name' already exists. Duplicate entry not allowed.");
        }

        // Handle image upload
        $item_image = null;
        if (!empty($_FILES['item_image']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            $upload = wp_handle_upload($_FILES['item_image'], ['test_form' => false]);
            if (!isset($upload['error'])) {
                $item_image = $upload['url'];
            }
        }

        $data = [
            'item_code'       => $item_code,
            'item_name'       => $item_name,
            'service_bit'     => ($_POST['item_type'] ?? '') === 'service' ? 1 : 0,
            'warehouse_id'    => intval($_POST['warehouse_id'] ?? 0),
            'item_group'      => sanitize_text_field($_POST['item_group'] ?? ''),
            'category_id'     => intval($_POST['category_id'] ?? 0),
            'brand_id'        => intval($_POST['brand_id'] ?? 0),
            'unit_id'         => intval($_POST['unit_id'] ?? 0),
            'purchase_account_id' => intval($_POST['purchase_account_id'] ?? 0),
            'sales_account_id'    => intval($_POST['sales_account_id'] ?? 0),
            'purchase_price'  => floatval($_POST['purchase_price'] ?? 0),
            'sales_price'     => floatval($_POST['sales_price'] ?? 0),
            'price'           => floatval($_POST['price'] ?? 0),
            'description'     => sanitize_textarea_field($_POST['description'] ?? ''),
            'tax_id'          => intval($_POST['tax_id'] ?? 0),
            'sku'             => sanitize_text_field($_POST['sku'] ?? ''),
            'custom_barcode'  => sanitize_text_field($_POST['barcode'] ?? ''),
            'hsn'             => sanitize_text_field($_POST['hsn'] ?? ''),
            'seller_points'   => floatval($_POST['seller_points'] ?? 0),
            'discount_type'   => sanitize_text_field($_POST['discount_type'] ?? 'Percentage'),
            'discount'        => floatval($_POST['discount'] ?? 0),
            'tax_type'        => sanitize_text_field($_POST['tax_type'] ?? 'Inclusive'),
            'profit_margin'   => floatval($_POST['profit_margin'] ?? 0),
            'mrp'             => floatval($_POST['mrp'] ?? 0),
            'stock'           => floatval($_POST['opening_stock'] ?? 0),
            'alert_qty'       => intval($_POST['alert_stock'] ?? 0),
        ];

        // Only update image if a new one was uploaded
        if ($item_image !== null) {
            $data['item_image'] = $item_image;
        }

        $result = $wpdb->update($table, $data, ['id' => $id]);
        if ($result !== false) {
            // Update opening stock in warehouseitems
            $warehouse_id = intval($_POST['warehouse_id'] ?? 0);
            $opening_stock = floatval($_POST['opening_stock'] ?? 0);
            if ($warehouse_id > 0) {
                $wh_table = $wpdb->prefix . 'orabooks_db_warehouseitems';
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$wh_table} WHERE warehouse_id = %d AND item_id = %d",
                    $warehouse_id,
                    $id
                ));
                if ($existing) {
                    $wpdb->update(
                        $wh_table,
                        ['available_qty' => $opening_stock],
                        ['id' => $existing->id]
                    );
                } else {
                    $wpdb->insert($wh_table, [
                        'store_id'      => 1,
                        'warehouse_id'  => $warehouse_id,
                        'item_id'       => $id,
                        'available_qty' => $opening_stock,
                    ]);
                }
            }

            wp_send_json_success(['message' => 'Item updated successfully.', 'id' => $id]);
        } else {
            wp_send_json_error('Failed to update item.');
        }
    }

    public static function handle_insert_item() {
        check_ajax_referer('obn_auth_nonce', 'security');
        if (!is_user_logged_in()) wp_die('Unauthorized');

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_items';

        $item_code = sanitize_text_field($_POST['item_code'] ?? '');
        $item_name = sanitize_text_field($_POST['item_name'] ?? '');

        // Auto-generate item code if empty
        if (empty($item_code)) {
            $last_id = $wpdb->get_var("SELECT id FROM $table ORDER BY id DESC LIMIT 1");
            $next_id = $last_id ? intval($last_id) + 1 : 1;
            $item_code = 'ITM-' . str_pad($next_id, 4, '0', STR_PAD_LEFT);
        }

        // Duplicate Check
        $code_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE item_code = %s", $item_code));
        if ($code_exists) {
            wp_send_json_error("Warning: The item code '$item_code' already exists. Duplicate entry not allowed.");
        }

        $name_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE item_name = %s", $item_name));
        if ($name_exists) {
            wp_send_json_error("Warning: The item name '$item_name' already exists. Duplicate entry not allowed.");
        }

        // Handle image upload
        $item_image = '';
        if (!empty($_FILES['item_image']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            $upload = wp_handle_upload($_FILES['item_image'], ['test_form' => false]);
            if (!isset($upload['error'])) {
                $item_image = $upload['url'];
            }
        }

        $data = [
            'item_code'       => $item_code,
            'item_name'       => $item_name,
            'service_bit'     => ($_POST['item_type'] ?? '') === 'service' ? 1 : 0,
            'warehouse_id'    => intval($_POST['warehouse_id'] ?? 0),
            'item_group'      => sanitize_text_field($_POST['item_group'] ?? ''),
            'category_id'     => intval($_POST['category_id'] ?? 0),
            'brand_id'        => intval($_POST['brand_id'] ?? 0),
            'unit_id'         => intval($_POST['unit_id'] ?? 0),
            'purchase_account_id' => intval($_POST['purchase_account_id'] ?? 0),
            'sales_account_id'    => intval($_POST['sales_account_id'] ?? 0),
            'purchase_price'  => floatval($_POST['purchase_price'] ?? 0),
            'sales_price'     => floatval($_POST['sales_price'] ?? 0),
            'price'           => floatval($_POST['price'] ?? 0),
            'description'     => sanitize_textarea_field($_POST['description'] ?? ''),
            'tax_id'          => intval($_POST['tax_id'] ?? 0),
            'sku'             => sanitize_text_field($_POST['sku'] ?? ''),
            'custom_barcode'  => sanitize_text_field($_POST['barcode'] ?? ''),
            'hsn'             => sanitize_text_field($_POST['hsn'] ?? ''),
            'seller_points'   => floatval($_POST['seller_points'] ?? 0),
            'discount_type'   => sanitize_text_field($_POST['discount_type'] ?? 'Percentage'),
            'discount'        => floatval($_POST['discount'] ?? 0),
            'tax_type'        => sanitize_text_field($_POST['tax_type'] ?? 'Inclusive'),
            'profit_margin'   => floatval($_POST['profit_margin'] ?? 0),
            'mrp'             => floatval($_POST['mrp'] ?? 0),
            'stock'           => floatval($_POST['opening_stock'] ?? 0),
            'alert_qty'       => intval($_POST['alert_stock'] ?? 0),
            'item_image'      => $item_image,
            'status'          => 1,
            'store_id'        => 1,
        ];

        $result = $wpdb->insert($table, $data);
        if ($result) {
            $insert_id = $wpdb->insert_id;

            // Insert opening stock into warehouseitems
            $warehouse_id = intval($_POST['warehouse_id'] ?? 0);
            $opening_stock = floatval($_POST['opening_stock'] ?? 0);
            if ($warehouse_id > 0) {
                $wh_table = $wpdb->prefix . 'orabooks_db_warehouseitems';
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$wh_table} WHERE warehouse_id = %d AND item_id = %d",
                    $warehouse_id,
                    $insert_id
                ));
                if ($existing) {
                    $wpdb->update(
                        $wh_table,
                        ['available_qty' => $opening_stock],
                        ['id' => $existing->id]
                    );
                } else {
                    $wpdb->insert($wh_table, [
                        'store_id'      => 1,
                        'warehouse_id'  => $warehouse_id,
                        'item_id'       => $insert_id,
                        'available_qty' => $opening_stock,
                    ]);
                }
            }

            wp_send_json_success(['message' => 'Item added successfully.', 'id' => $insert_id]);
        } else {
            wp_send_json_error('Failed to add item.');
        }
    }

    public static function handle_update_item_status() {
        check_ajax_referer('obn_auth_nonce', 'security');
        if (!is_user_logged_in()) wp_die('Unauthorized');

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_items';
        $id = intval($_POST['id']);
        $status = intval($_POST['status']);

        $wpdb->update($table, ['status' => $status], ['id' => $id]);
        wp_send_json_success(['message' => 'Status updated.']);
    }

    public static function handle_get_item() {
        check_ajax_referer('obn_auth_nonce', 'security');
        if (!is_user_logged_in()) wp_die('Unauthorized');

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_items';
        $wh_table = $wpdb->prefix . 'orabooks_db_warehouseitems';
        $id = intval($_POST['id']);

        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        if ($item) {
            // Convert service_bit to item_type
            $item->item_type = ($item->service_bit == 1) ? 'service' : 'Single';
            
            // Prefer the item warehouse column and fall back to warehouseitems for older rows.
            $warehouse_data = $wpdb->get_row($wpdb->prepare(
                "SELECT warehouse_id FROM $wh_table WHERE item_id = %d LIMIT 1",
                $id
            ));
            $item->warehouse_id = $item->warehouse_id ?: ($warehouse_data ? $warehouse_data->warehouse_id : 0);
            
            // Ensure all Select2 field IDs are strings for proper value matching with HTML options
            $item->tax_id = (string) intval($item->tax_id);
            $item->category_id = (string) intval($item->category_id);
            $item->brand_id = (string) intval($item->brand_id);
            $item->unit_id = (string) intval($item->unit_id);
            $item->warehouse_id = (string) intval($item->warehouse_id);
            
            wp_send_json_success($item);
        } else {
            wp_send_json_error('Item not found.');
        }
    }

    public static function handle_delete_item() {
        check_ajax_referer('obn_auth_nonce', 'security');
        if (!is_user_logged_in()) wp_die('Unauthorized');

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_items';
        $id = intval($_POST['id']);

        $result = $wpdb->delete($table, ['id' => $id]);
        if ($result) {
            wp_send_json_success(['message' => 'Item deleted successfully.']);
        } else {
            wp_send_json_error('Failed to delete item.');
        }
    }

    public static function handle_search_items() {
        check_ajax_referer('obn_auth_nonce', 'security');
        if (!is_user_logged_in()) wp_die('Unauthorized');

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_items';
        $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

        $sql = "SELECT * FROM $table WHERE status = 1 AND (item_name LIKE %s OR item_code LIKE %s OR sku LIKE %s) LIMIT 20";
        $like = '%' . $wpdb->esc_like($search) . '%';
        $items = $wpdb->get_results($wpdb->prepare($sql, $like, $like, $like));

        $results = [];
        foreach ($items as $item) {
            $results[] = [
                'id'              => $item->id,
                'item_code'       => $item->item_code,
                'item_name'       => $item->item_name,
                'sales_price'     => $item->sales_price ?? 0,
                'purchase_price'  => $item->purchase_price ?? 0,
                'sku'             => $item->sku ?? '',
            ];
        }
        wp_send_json($results);
    }

    public static function handle_import_items() {
        check_ajax_referer('obn_auth_nonce', 'security');
        if (!is_user_logged_in()) wp_die('Unauthorized');

        if (empty($_FILES['import_file'])) {
            wp_send_json_error('No file uploaded.');
        }

        $file = $_FILES['import_file'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

        if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
            wp_send_json_error('Invalid file format. Please upload CSV or Excel file.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'orabooks_db_items';
        $imported = 0;
        $errors = [];

        if ($ext === 'csv') {
            $handle = fopen($file['tmp_name'], 'r');
            $header = fgetcsv($handle);
            if (!$header) {
                fclose($handle);
                wp_send_json_error('Invalid CSV file.');
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 2) continue;
                $item_name = sanitize_text_field($row[0]);
                if (empty($item_name)) continue;

                $data = [
                    'item_name'      => $item_name,
                    'item_code'      => isset($row[1]) ? sanitize_text_field($row[1]) : '',
                    'item_type'      => isset($row[2]) ? sanitize_text_field($row[2]) : 'goods',
                    'sales_price'    => isset($row[3]) ? floatval($row[3]) : 0,
                    'purchase_price' => isset($row[4]) ? floatval($row[4]) : 0,
                    'opening_stock'  => isset($row[5]) ? intval($row[5]) : 0,
                    'status'         => 1,
                ];
                $result = $wpdb->insert($table, $data);
                if ($result) $imported++;
                else $errors[] = 'Failed to import: ' . $item_name;
            }
            fclose($handle);
        } else {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
                wp_send_json_error('Excel import requires PhpSpreadsheet library.');
            }

            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();

                foreach ($rows as $index => $row) {
                    if ($index === 0) continue; // skip header
                    if (empty($row[0]) && empty($row[1])) continue;
                    $item_name = sanitize_text_field($row[1] ?? '');
                    if (empty($item_name)) continue;

                    $data = [
                        'item_code'      => sanitize_text_field($row[0] ?? ''),
                        'item_name'      => $item_name,
                        'item_type'      => sanitize_text_field($row[2] ?? 'goods'),
                        'sales_price'    => floatval($row[3] ?? 0),
                        'purchase_price' => floatval($row[4] ?? 0),
                        'opening_stock'  => intval($row[5] ?? 0),
                        'status'         => 1,
                    ];
                    $result = $wpdb->insert($table, $data);
                    if ($result) $imported++;
                    else $errors[] = 'Row ' . ($index + 1) . ': Failed to import ' . $item_name;
                }
            } catch (\Exception $e) {
                wp_send_json_error('Excel processing error: ' . $e->getMessage());
            }
        }

        if ($imported > 0) {
            wp_send_json_success([
                'message' => "Successfully imported {$imported} items.",
                'imported' => $imported,
                'errors' => $errors
            ]);
        } else {
            wp_send_json_error('No items were imported. ' . (!empty($errors) ? implode(', ', $errors) : ''));
        }
    }

    public static function handle_download_item_template() {
        check_ajax_referer('obn_auth_nonce', 'security');
        if (!is_user_logged_in()) wp_die('Unauthorized');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="item_import_template.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Item Code', 'Item Name', 'Item Type', 'Sales Price', 'Purchase Price', 'Opening Stock']);
        fputcsv($output, ['ITEM001', 'Sample Item', 'goods', '100.00', '80.00', '10']);
        fputcsv($output, ['ITEM002', 'Sample Service', 'service', '200.00', '0', '0']);
        fclose($output);
        exit;
    }
}

// instantiate to register actions
new OBN_Items();
