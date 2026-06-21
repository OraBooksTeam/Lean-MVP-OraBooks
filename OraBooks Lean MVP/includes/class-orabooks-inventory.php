<?php
/**
 * OraBooks Inventory Lite (SL-034)
 *
 * Product/SKU management, weighted-average costing, stock movements,
 * negative stock prevention, and COGS posting hooks.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Inventory {

    private static $instance = null;

    const INVENTORY_ASSET_ACCOUNT = '1200';
    const COGS_ACCOUNT = '5100';

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();

            add_action('wp_ajax_orabooks_inventory_products_list', [self::$instance, 'ajax_products_list']);
            add_action('wp_ajax_nopriv_orabooks_inventory_products_list', [self::$instance, 'ajax_products_list']);
            add_action('wp_ajax_orabooks_inventory_product_create', [self::$instance, 'ajax_product_create']);
            add_action('wp_ajax_nopriv_orabooks_inventory_product_create', [self::$instance, 'ajax_product_create']);
            add_action('wp_ajax_orabooks_inventory_product_adjust', [self::$instance, 'ajax_adjust_stock']);
            add_action('wp_ajax_nopriv_orabooks_inventory_product_adjust', [self::$instance, 'ajax_adjust_stock']);
            add_action('wp_ajax_orabooks_inventory_movements', [self::$instance, 'ajax_movements']);
            add_action('wp_ajax_nopriv_orabooks_inventory_movements', [self::$instance, 'ajax_movements']);
            add_action('wp_ajax_orabooks_inventory_lookups_list', [self::$instance, 'ajax_lookups_list']);
            add_action('wp_ajax_nopriv_orabooks_inventory_lookups_list', [self::$instance, 'ajax_lookups_list']);
            add_action('wp_ajax_orabooks_inventory_lookup_create', [self::$instance, 'ajax_lookup_create']);
            add_action('wp_ajax_nopriv_orabooks_inventory_lookup_create', [self::$instance, 'ajax_lookup_create']);
            add_action('wp_ajax_orabooks_inventory_lookup_code', [self::$instance, 'ajax_lookup_code']);
            add_action('wp_ajax_nopriv_orabooks_inventory_lookup_code', [self::$instance, 'ajax_lookup_code']);

            add_action('orabooks_vendor_bill_posted', [self::$instance, 'on_vendor_bill_posted'], 10, 2);
            add_action('orabooks_invoice_posted', [self::$instance, 'on_invoice_posted'], 10, 2);
        }
        return self::$instance;
    }

    public static function get_create_table_sql() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_products = OraBooks_Database::table('products');
        $table_movements = OraBooks_Database::table('inventory_movements');
        $table_lookups = OraBooks_Database::table('inventory_lookups');
        $table_orgs = OraBooks_Database::table('organizations');

        return [
            "CREATE TABLE IF NOT EXISTS {$table_products} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                sku VARCHAR(100) NOT NULL,
                name VARCHAR(255) NOT NULL,
                brand_name VARCHAR(120) NULL,
                category_name VARCHAR(120) NULL,
                hsn VARCHAR(64) NULL,
                stock_keeping_unit VARCHAR(120) NULL,
                barcode VARCHAR(120) NULL,
                description TEXT NULL,
                item_image_url VARCHAR(500) NULL,
                discount_type ENUM('Percentage','Fixed') DEFAULT 'Percentage',
                discount DECIMAL(20,2) DEFAULT 0,
                price DECIMAL(20,2) DEFAULT 0,
                purchase_price DECIMAL(20,6) DEFAULT 0,
                sales_price DECIMAL(20,2) DEFAULT 0,
                mrp DECIMAL(20,2) DEFAULT 0,
                profit_margin DECIMAL(10,2) DEFAULT 0,
                tax_name VARCHAR(120) NULL,
                tax_percent DECIMAL(10,4) DEFAULT 0,
                tax_type ENUM('Inclusive','Exclusive') DEFAULT 'Inclusive',
                warehouse_name VARCHAR(120) NULL,
                item_type ENUM('Single','Variants','service') DEFAULT 'Single',
                seller_points DECIMAL(20,2) DEFAULT 0,
                unit VARCHAR(50) DEFAULT 'piece',
                current_stock DECIMAL(20,4) NOT NULL DEFAULT 0,
                average_cost DECIMAL(20,6) NOT NULL DEFAULT 0,
                low_stock_threshold DECIMAL(20,4) NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_org_sku (org_id, sku),
                FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
                INDEX idx_org_active (org_id, is_active),
                INDEX idx_sku (sku)
            ) {$charset_collate};",
            "CREATE TABLE IF NOT EXISTS {$table_movements} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                quantity_change DECIMAL(20,4) NOT NULL,
                stock_before DECIMAL(20,4) NOT NULL,
                stock_after DECIMAL(20,4) NOT NULL,
                unit_cost DECIMAL(20,6) NOT NULL DEFAULT 0,
                movement_value DECIMAL(20,2) NOT NULL DEFAULT 0,
                reference_type ENUM('opening','purchase','sale','adjustment') NOT NULL,
                reference_id BIGINT UNSIGNED NULL,
                reason TEXT NULL,
                note TEXT NULL,
                journal_id BIGINT UNSIGNED NULL,
                created_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES {$table_products}(id) ON DELETE CASCADE,
                INDEX idx_org_product (org_id, product_id),
                INDEX idx_reference (reference_type, reference_id),
                INDEX idx_created (created_at)
            ) {$charset_collate};",
            "CREATE TABLE IF NOT EXISTS {$table_lookups} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                lookup_type ENUM('brand','category','unit','tax','warehouse') NOT NULL,
                name VARCHAR(120) NOT NULL,
                code VARCHAR(50) NULL,
                tax_percent DECIMAL(10,4) NULL,
                description TEXT NULL,
                warehouse_type VARCHAR(50) DEFAULT 'custom',
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_org_type_name (org_id, lookup_type, name),
                FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
                INDEX idx_org_type (org_id, lookup_type)
            ) {$charset_collate};",
        ];
    }

    private static function lookup_types() {
        return ['brand', 'category', 'unit', 'tax', 'warehouse'];
    }

    private static function normalize_lookup_type($type) {
        $type = sanitize_key((string) $type);
        return in_array($type, self::lookup_types(), true) ? $type : '';
    }

    private static function maybe_ensure_lookup_schema() {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;

        global $wpdb;
        $table = OraBooks_Database::table('inventory_lookups');
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            return;
        }

        $upgrade = ABSPATH . 'wp-admin/includes/upgrade.php';
        if (file_exists($upgrade)) {
            require_once $upgrade;

            $lookup_sql = array_values(array_filter(
                self::get_create_table_sql(),
                function ($sql) {
                    return stripos($sql, 'orabooks_inventory_lookups') !== false;
                }
            ));

            if (!empty($lookup_sql[0]) && function_exists('dbDelta')) {
                dbDelta($lookup_sql[0]);
            }
        }
    }

    private static function format_lookup($row) {
        return [
            'id' => (int) $row->id,
            'lookup_type' => $row->lookup_type,
            'name' => $row->name,
            'code' => $row->code,
            'tax_percent' => $row->tax_percent !== null ? (float) $row->tax_percent : null,
            'description' => $row->description,
            'warehouse_type' => $row->warehouse_type,
        ];
    }

    private static function seed_default_lookups($org_id) {
        global $wpdb;
        $table = OraBooks_Database::table('inventory_lookups');
        $org_id = (int) $org_id;

        $defaults = [
            ['category', 'General', null, null, null, null],
            ['unit', 'piece', null, null, null, null],
            ['warehouse', 'Main Warehouse', null, null, null, 'system'],
        ];

        foreach ($defaults as $row) {
            [$type, $name, $code, $tax_percent, $description, $warehouse_type] = $row;
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE org_id = %d AND lookup_type = %s AND name = %s LIMIT 1",
                $org_id,
                $type,
                $name
            ));
            if ($exists) {
                continue;
            }

            $wpdb->insert($table, [
                'org_id' => $org_id,
                'lookup_type' => $type,
                'name' => $name,
                'code' => $code,
                'tax_percent' => $tax_percent,
                'description' => $description,
                'warehouse_type' => $warehouse_type ?: 'custom',
                'is_active' => 1,
            ]);
        }
    }

    public static function generate_lookup_code($org_id, $lookup_type) {
        global $wpdb;
        $table = OraBooks_Database::table('inventory_lookups');
        $org_id = (int) $org_id;
        $lookup_type = self::normalize_lookup_type($lookup_type);

        if ($lookup_type === 'brand') {
            $prefix = 'IBRAND-';
        } elseif ($lookup_type === 'category') {
            $prefix = 'ICAT-';
        } else {
            return '';
        }

        $next_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) + 1 FROM {$table} WHERE org_id = %d AND lookup_type = %s",
            $org_id,
            $lookup_type
        ));

        return $prefix . str_pad((string) max(1, $next_id), 5, '0', STR_PAD_LEFT);
    }

    public static function get_lookups_list($org_id, $lookup_type = null) {
        global $wpdb;
        self::maybe_ensure_lookup_schema();
        self::seed_default_lookups($org_id);

        $table = OraBooks_Database::table('inventory_lookups');
        $org_id = (int) $org_id;
        $lookup_type = $lookup_type ? self::normalize_lookup_type($lookup_type) : '';

        if ($lookup_type !== '') {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE org_id = %d AND lookup_type = %s AND is_active = 1
                 ORDER BY name ASC",
                $org_id,
                $lookup_type
            ));
            return array_map([self::class, 'format_lookup'], $rows ?: []);
        }

        $grouped = [];
        foreach (self::lookup_types() as $type) {
            $grouped[$type] = [];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE org_id = %d AND is_active = 1
             ORDER BY lookup_type ASC, name ASC",
            $org_id
        ));

        foreach ($rows ?: [] as $row) {
            if (!isset($grouped[$row->lookup_type])) {
                continue;
            }
            $grouped[$row->lookup_type][] = self::format_lookup($row);
        }

        return $grouped;
    }

    public static function create_lookup($org_id, $lookup_type, $data) {
        global $wpdb;
        self::maybe_ensure_lookup_schema();
        self::seed_default_lookups($org_id);

        $table = OraBooks_Database::table('inventory_lookups');
        $org_id = (int) $org_id;
        $lookup_type = self::normalize_lookup_type($lookup_type);

        if ($org_id <= 0) {
            return new WP_Error('invalid_org', 'Organization is required.');
        }
        if ($lookup_type === '') {
            return new WP_Error('invalid_lookup_type', 'Invalid lookup type.');
        }

        $name = sanitize_text_field($data['name'] ?? '');
        if ($name === '') {
            return new WP_Error('missing_name', ucfirst($lookup_type) . ' name is required.');
        }

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE org_id = %d AND lookup_type = %s AND name = %s LIMIT 1",
            $org_id,
            $lookup_type,
            $name
        ));
        if ($exists) {
            return new WP_Error('duplicate_name', "A {$lookup_type} with this name already exists.");
        }

        $description = sanitize_textarea_field($data['description'] ?? '');
        $insert = [
            'org_id' => $org_id,
            'lookup_type' => $lookup_type,
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'is_active' => 1,
        ];

        if (in_array($lookup_type, ['brand', 'category'], true)) {
            $code = sanitize_text_field($data['code'] ?? '');
            if ($code === '') {
                $code = self::generate_lookup_code($org_id, $lookup_type);
            }
            $insert['code'] = $code;
        }

        if ($lookup_type === 'tax') {
            $tax_percent = isset($data['tax_percent']) ? floatval($data['tax_percent']) : null;
            if ($tax_percent === null || $tax_percent < 0) {
                return new WP_Error('invalid_tax_percent', 'Tax percentage is required.');
            }
            $insert['tax_percent'] = $tax_percent;
        }

        if ($lookup_type === 'warehouse') {
            $insert['warehouse_type'] = sanitize_text_field($data['warehouse_type'] ?? 'custom') ?: 'custom';
        }

        $inserted = $wpdb->insert($table, $insert);
        if (!$inserted) {
            return new WP_Error('insert_failed', 'Failed to save ' . $lookup_type . '.');
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND org_id = %d LIMIT 1",
            (int) $wpdb->insert_id,
            $org_id
        ));

        return self::format_lookup($row);
    }

    private static function maybe_ensure_product_schema() {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;

        global $wpdb;
        $table = OraBooks_Database::table('products');
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return;
        }

        $fields = self::get_table_column_names($table);
        $additions = [
            'brand_name' => "ALTER TABLE {$table} ADD COLUMN brand_name VARCHAR(120) NULL AFTER name",
            'category_name' => "ALTER TABLE {$table} ADD COLUMN category_name VARCHAR(120) NULL AFTER brand_name",
            'hsn' => "ALTER TABLE {$table} ADD COLUMN hsn VARCHAR(64) NULL AFTER category_name",
            'stock_keeping_unit' => "ALTER TABLE {$table} ADD COLUMN stock_keeping_unit VARCHAR(120) NULL AFTER hsn",
            'barcode' => "ALTER TABLE {$table} ADD COLUMN barcode VARCHAR(120) NULL AFTER stock_keeping_unit",
            'description' => "ALTER TABLE {$table} ADD COLUMN description TEXT NULL AFTER barcode",
            'item_image_url' => "ALTER TABLE {$table} ADD COLUMN item_image_url VARCHAR(500) NULL AFTER description",
            'discount_type' => "ALTER TABLE {$table} ADD COLUMN discount_type ENUM('Percentage','Fixed') DEFAULT 'Percentage' AFTER item_image_url",
            'discount' => "ALTER TABLE {$table} ADD COLUMN discount DECIMAL(20,2) DEFAULT 0 AFTER discount_type",
            'price' => "ALTER TABLE {$table} ADD COLUMN price DECIMAL(20,2) DEFAULT 0 AFTER discount",
            'purchase_price' => "ALTER TABLE {$table} ADD COLUMN purchase_price DECIMAL(20,6) DEFAULT 0 AFTER price",
            'sales_price' => "ALTER TABLE {$table} ADD COLUMN sales_price DECIMAL(20,2) DEFAULT 0 AFTER purchase_price",
            'mrp' => "ALTER TABLE {$table} ADD COLUMN mrp DECIMAL(20,2) DEFAULT 0 AFTER sales_price",
            'profit_margin' => "ALTER TABLE {$table} ADD COLUMN profit_margin DECIMAL(10,2) DEFAULT 0 AFTER mrp",
            'tax_name' => "ALTER TABLE {$table} ADD COLUMN tax_name VARCHAR(120) NULL AFTER profit_margin",
            'tax_percent' => "ALTER TABLE {$table} ADD COLUMN tax_percent DECIMAL(10,4) DEFAULT 0 AFTER tax_name",
            'tax_type' => "ALTER TABLE {$table} ADD COLUMN tax_type ENUM('Inclusive','Exclusive') DEFAULT 'Inclusive' AFTER tax_percent",
            'warehouse_name' => "ALTER TABLE {$table} ADD COLUMN warehouse_name VARCHAR(120) NULL AFTER tax_type",
            'item_type' => "ALTER TABLE {$table} ADD COLUMN item_type ENUM('Single','Variants','service') DEFAULT 'Single' AFTER warehouse_name",
            'seller_points' => "ALTER TABLE {$table} ADD COLUMN seller_points DECIMAL(20,2) DEFAULT 0 AFTER item_type",
        ];

        foreach ($additions as $column => $sql) {
            if (!in_array($column, $fields, true)) {
                if ($wpdb->query($sql) !== false) {
                    $fields[] = $column;
                }
            }
        }
    }

    private static function get_table_column_names($table) {
        global $wpdb;
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table}");
        if (empty($columns)) {
            return [];
        }

        return array_map(function ($col) {
            return $col->Field;
        }, $columns);
    }

    private static function next_item_code($org_id) {
        global $wpdb;

        $table = OraBooks_Database::table('products');
        $last_number = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(sku, 5) AS UNSIGNED)), 1000)
             FROM {$table}
             WHERE org_id = %d AND sku LIKE 'ITM-____'",
            (int) $org_id
        ));

        return 'ITM-' . str_pad((string) ($last_number + 1), 4, '0', STR_PAD_LEFT);
    }

    private static function calculate_purchase_price($price, $tax_percent, $tax_type) {
        $price = (float) $price;
        $tax_percent = (float) $tax_percent;

        if ($tax_type === 'Exclusive' && $tax_percent > 0) {
            return round($price + ($price * $tax_percent / 100), 6);
        }

        return round($price, 6);
    }

    private static function calculate_sales_price($price, $profit_margin) {
        $price = (float) $price;
        $profit_margin = (float) $profit_margin;
        return round($price + ($price * $profit_margin / 100), 2);
    }

    private static function enum_value($value, $allowed, $fallback) {
        $value = sanitize_text_field($value);
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    public static function create_product($org_id, $data) {
        global $wpdb;

        self::maybe_ensure_product_schema();

        $org_id = intval($org_id);
        $sku = strtoupper(sanitize_text_field($data['sku'] ?? $data['item_code'] ?? ''));
        $name = sanitize_text_field($data['name'] ?? $data['item_name'] ?? '');
        $initial_stock = round(floatval($data['initial_stock'] ?? $data['opening_stock'] ?? 0), 4);
        $initial_cost = round(floatval($data['initial_cost'] ?? $data['purchase_price'] ?? $data['price'] ?? 0), 6);
        $price = round(floatval($data['price'] ?? 0), 2);
        $tax_percent = round(floatval($data['tax_percent'] ?? 0), 4);
        $tax_type = sanitize_text_field($data['tax_type'] ?? 'Inclusive');
        $profit_margin = round(floatval($data['profit_margin'] ?? 0), 2);
        $purchase_price = array_key_exists('purchase_price', $data)
            ? round(floatval($data['purchase_price']), 6)
            : self::calculate_purchase_price($price, $tax_percent, $tax_type);
        $sales_price = array_key_exists('sales_price', $data)
            ? round(floatval($data['sales_price']), 2)
            : self::calculate_sales_price($price, $profit_margin);
        $mrp = array_key_exists('mrp', $data) ? round(floatval($data['mrp']), 2) : $sales_price;
        $initial_cost = $initial_cost > 0 ? $initial_cost : $purchase_price;

        if ($org_id <= 0 || $name === '') {
            return new WP_Error('missing_field', 'Organization and item name are required');
        }

        if ($sku === '') {
            $sku = self::next_item_code($org_id);
        }

        if ($initial_stock < 0) {
            return new WP_Error('invalid_stock', 'Initial stock cannot be negative');
        }

        if ($initial_cost < 0) {
            return new WP_Error('invalid_cost', 'Initial cost cannot be negative');
        }

        $table = OraBooks_Database::table('products');
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE org_id = %d AND sku = %s",
            $org_id,
            $sku
        ));

        if ($existing) {
            return new WP_Error('duplicate_sku', 'SKU already exists for this organization');
        }

        $wpdb->insert(
            $table,
            [
                'org_id' => $org_id,
                'sku' => $sku,
                'name' => $name,
                'unit' => sanitize_text_field($data['unit'] ?? 'piece'),
                'brand_name' => sanitize_text_field($data['brand_name'] ?? ''),
                'category_name' => sanitize_text_field($data['category_name'] ?? ''),
                'hsn' => sanitize_text_field($data['hsn'] ?? ''),
                'stock_keeping_unit' => sanitize_text_field($data['stock_keeping_unit'] ?? ''),
                'barcode' => sanitize_text_field($data['barcode'] ?? ''),
                'description' => sanitize_textarea_field($data['description'] ?? ''),
                'item_image_url' => esc_url_raw($data['item_image_url'] ?? ''),
                'discount_type' => self::enum_value($data['discount_type'] ?? 'Percentage', ['Percentage', 'Fixed'], 'Percentage'),
                'discount' => round(floatval($data['discount'] ?? 0), 2),
                'price' => $price,
                'purchase_price' => $purchase_price,
                'sales_price' => $sales_price,
                'mrp' => $mrp,
                'profit_margin' => $profit_margin,
                'tax_name' => sanitize_text_field($data['tax_name'] ?? ''),
                'tax_percent' => $tax_percent,
                'tax_type' => self::enum_value($tax_type, ['Inclusive', 'Exclusive'], 'Inclusive'),
                'warehouse_name' => sanitize_text_field($data['warehouse_name'] ?? ''),
                'item_type' => self::enum_value($data['item_type'] ?? 'Single', ['Single', 'Variants', 'service'], 'Single'),
                'seller_points' => round(floatval($data['seller_points'] ?? 0), 2),
                'current_stock' => $initial_stock,
                'average_cost' => $initial_cost,
                'low_stock_threshold' => isset($data['low_stock_threshold']) ? floatval($data['low_stock_threshold']) : null,
                'is_active' => 1,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%s', '%f', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%d']
        );

        $product_id = intval($wpdb->insert_id);

        if ($initial_stock > 0) {
            self::insert_movement([
                'org_id' => $org_id,
                'product_id' => $product_id,
                'quantity_change' => $initial_stock,
                'stock_before' => 0,
                'stock_after' => $initial_stock,
                'unit_cost' => $initial_cost,
                'movement_value' => round($initial_stock * $initial_cost, 2),
                'reference_type' => 'opening',
                'reference_id' => null,
                'reason' => 'Opening balance',
                'note' => null,
                'journal_id' => null,
                'created_by' => orabooks_get_current_user_id(),
            ]);
        }

        orabooks_log_event('inventory_product_created', "Product created: {$sku}", 'info', [
            'product_id' => $product_id,
            'sku' => $sku,
            'initial_stock' => $initial_stock,
        ], orabooks_get_current_user_id(), $org_id);

        return self::get_product($product_id, $org_id);
    }

    public static function get_product($product_id, $org_id) {
        global $wpdb;

        self::maybe_ensure_product_schema();

        $table = OraBooks_Database::table('products');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND org_id = %d",
            intval($product_id),
            intval($org_id)
        ));
    }

    public static function get_product_by_sku($org_id, $sku) {
        global $wpdb;

        self::maybe_ensure_product_schema();

        $table = OraBooks_Database::table('products');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE org_id = %d AND sku = %s",
            intval($org_id),
            strtoupper(sanitize_text_field($sku))
        ));
    }

    public static function get_products_list($org_id, $args = []) {
        global $wpdb;

        self::maybe_ensure_product_schema();

        $table = OraBooks_Database::table('products');
        $where = 'org_id = %d';
        $params = [intval($org_id)];

        if (!empty($args['search'])) {
            $where .= ' AND (sku LIKE %s OR name LIKE %s OR category_name LIKE %s OR brand_name LIKE %s OR stock_keeping_unit LIKE %s OR barcode LIKE %s)';
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (isset($args['is_active'])) {
            $where .= ' AND is_active = %d';
            $params[] = intval($args['is_active']);
        }

        $limit = intval($args['limit'] ?? 50);
        $offset = intval($args['offset'] ?? 0);
        $params[] = $limit;
        $params[] = $offset;

        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where} ORDER BY sku ASC LIMIT %d OFFSET %d",
            $params
        ));

        return [
            'products' => $products,
            'total' => count($products),
            'page' => ($limit > 0) ? floor($offset / $limit) + 1 : 1,
            'per_page' => $limit,
        ];
    }

    public static function receive_purchase($org_id, $product_id, $quantity, $unit_cost, $reference_id = null, $user_id = null) {
        global $wpdb;

        $quantity = round(floatval($quantity), 4);
        $unit_cost = round(floatval($unit_cost), 6);
        $product = self::get_product($product_id, $org_id);

        if (!$product) {
            return new WP_Error('not_found', 'Product not found');
        }

        if ($quantity <= 0 || $unit_cost < 0) {
            return new WP_Error('invalid_purchase', 'Purchase quantity must be positive and cost cannot be negative');
        }

        $stock_before = round(floatval($product->current_stock), 4);
        $stock_after = round($stock_before + $quantity, 4);
        $old_value = $stock_before * floatval($product->average_cost);
        $purchase_value = $quantity * $unit_cost;
        $new_average_cost = $stock_after > 0 ? round(($old_value + $purchase_value) / $stock_after, 6) : 0;

        $wpdb->update(
            OraBooks_Database::table('products'),
            [
                'current_stock' => $stock_after,
                'average_cost' => $new_average_cost,
            ],
            ['id' => intval($product_id), 'org_id' => intval($org_id)],
            ['%f', '%f'],
            ['%d', '%d']
        );

        $movement_id = self::insert_movement([
            'org_id' => intval($org_id),
            'product_id' => intval($product_id),
            'quantity_change' => $quantity,
            'stock_before' => $stock_before,
            'stock_after' => $stock_after,
            'unit_cost' => $unit_cost,
            'movement_value' => round($purchase_value, 2),
            'reference_type' => 'purchase',
            'reference_id' => $reference_id ? intval($reference_id) : null,
            'reason' => 'Purchase receipt',
            'note' => null,
            'journal_id' => null,
            'created_by' => $user_id ? intval($user_id) : orabooks_get_current_user_id(),
        ]);

        orabooks_log_event('inventory_purchase_received', 'Inventory purchase received', 'info', [
            'product_id' => intval($product_id),
            'quantity' => $quantity,
            'average_cost' => $new_average_cost,
            'movement_id' => $movement_id,
        ], $user_id ?: orabooks_get_current_user_id(), intval($org_id));

        return [
            'product_id' => intval($product_id),
            'movement_id' => $movement_id,
            'stock_before' => $stock_before,
            'stock_after' => $stock_after,
            'average_cost' => $new_average_cost,
        ];
    }

    public static function record_sale($org_id, $product_id, $quantity, $reference_id = null, $user_id = null) {
        global $wpdb;

        $quantity = round(floatval($quantity), 4);
        $product = self::get_product($product_id, $org_id);

        if (!$product) {
            return new WP_Error('not_found', 'Product not found');
        }

        if ($quantity <= 0) {
            return new WP_Error('invalid_sale', 'Sale quantity must be positive');
        }

        $stock_before = round(floatval($product->current_stock), 4);
        if ($stock_before - $quantity < 0) {
            return new WP_Error('negative_stock', 'Insufficient stock for this sale');
        }

        $stock_after = round($stock_before - $quantity, 4);
        $average_cost = round(floatval($product->average_cost), 6);
        $cogs_amount = round($quantity * $average_cost, 2);
        $journal_id = self::create_cogs_journal($org_id, $product, $quantity, $cogs_amount, $reference_id, $user_id);

        $wpdb->update(
            OraBooks_Database::table('products'),
            ['current_stock' => $stock_after],
            ['id' => intval($product_id), 'org_id' => intval($org_id)],
            ['%f'],
            ['%d', '%d']
        );

        $movement_id = self::insert_movement([
            'org_id' => intval($org_id),
            'product_id' => intval($product_id),
            'quantity_change' => -$quantity,
            'stock_before' => $stock_before,
            'stock_after' => $stock_after,
            'unit_cost' => $average_cost,
            'movement_value' => $cogs_amount,
            'reference_type' => 'sale',
            'reference_id' => $reference_id ? intval($reference_id) : null,
            'reason' => 'Sale',
            'note' => null,
            'journal_id' => is_wp_error($journal_id) ? null : $journal_id,
            'created_by' => $user_id ? intval($user_id) : orabooks_get_current_user_id(),
        ]);

        orabooks_log_event('inventory_sale_recorded', 'Inventory sale recorded', 'info', [
            'product_id' => intval($product_id),
            'quantity' => $quantity,
            'cogs_amount' => $cogs_amount,
            'movement_id' => $movement_id,
            'journal_id' => is_wp_error($journal_id) ? null : $journal_id,
        ], $user_id ?: orabooks_get_current_user_id(), intval($org_id));

        return [
            'product_id' => intval($product_id),
            'movement_id' => $movement_id,
            'stock_before' => $stock_before,
            'stock_after' => $stock_after,
            'cogs_amount' => $cogs_amount,
            'journal_id' => is_wp_error($journal_id) ? null : $journal_id,
        ];
    }

    public static function adjust_stock($org_id, $product_id, $quantity_change, $reason, $user_id = null, $note = '') {
        global $wpdb;

        $quantity_change = round(floatval($quantity_change), 4);
        $reason = sanitize_text_field($reason);
        $product = self::get_product($product_id, $org_id);

        if (!$product) {
            return new WP_Error('not_found', 'Product not found');
        }

        if ($quantity_change == 0.0) {
            return new WP_Error('invalid_adjustment', 'Adjustment quantity cannot be zero');
        }

        if ($reason === '') {
            return new WP_Error('reason_required', 'Adjustment reason is required');
        }

        $stock_before = round(floatval($product->current_stock), 4);
        $stock_after = round($stock_before + $quantity_change, 4);
        if ($stock_after < 0) {
            return new WP_Error('negative_stock', 'Adjustment cannot make stock negative');
        }

        $unit_cost = round(floatval($product->average_cost), 6);
        $movement_value = round(abs($quantity_change) * $unit_cost, 2);

        $wpdb->update(
            OraBooks_Database::table('products'),
            ['current_stock' => $stock_after],
            ['id' => intval($product_id), 'org_id' => intval($org_id)],
            ['%f'],
            ['%d', '%d']
        );

        $movement_id = self::insert_movement([
            'org_id' => intval($org_id),
            'product_id' => intval($product_id),
            'quantity_change' => $quantity_change,
            'stock_before' => $stock_before,
            'stock_after' => $stock_after,
            'unit_cost' => $unit_cost,
            'movement_value' => $movement_value,
            'reference_type' => 'adjustment',
            'reference_id' => null,
            'reason' => $reason,
            'note' => sanitize_textarea_field($note),
            'journal_id' => null,
            'created_by' => $user_id ? intval($user_id) : orabooks_get_current_user_id(),
        ]);

        orabooks_log_event('inventory_adjusted', 'Inventory stock adjusted', 'warning', [
            'product_id' => intval($product_id),
            'quantity_change' => $quantity_change,
            'stock_before' => $stock_before,
            'stock_after' => $stock_after,
            'reason' => $reason,
            'movement_id' => $movement_id,
        ], $user_id ?: orabooks_get_current_user_id(), intval($org_id));

        return [
            'product_id' => intval($product_id),
            'movement_id' => $movement_id,
            'stock_before' => $stock_before,
            'stock_after' => $stock_after,
        ];
    }

    public static function get_movements($org_id, $product_id, $args = []) {
        global $wpdb;

        $table = OraBooks_Database::table('inventory_movements');
        $limit = intval($args['limit'] ?? 100);
        $offset = intval($args['offset'] ?? 0);

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE org_id = %d AND product_id = %d
             ORDER BY created_at DESC, id DESC
             LIMIT %d OFFSET %d",
            intval($org_id),
            intval($product_id),
            $limit,
            $offset
        ));
    }

    public static function get_recent_movements($org_id, $args = []) {
        global $wpdb;

        $table_movements = OraBooks_Database::table('inventory_movements');
        $table_products = OraBooks_Database::table('products');
        $limit = intval($args['limit'] ?? 25);
        $offset = intval($args['offset'] ?? 0);

        return $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, p.sku, p.name AS product_name
             FROM {$table_movements} m
             JOIN {$table_products} p ON m.product_id = p.id
             WHERE m.org_id = %d
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT %d OFFSET %d",
            intval($org_id),
            $limit,
            $offset
        ));
    }

    public function on_vendor_bill_posted($bill_id, $payload = []) {
        $org_id = intval($payload['org_id'] ?? 0);
        $items = $payload['inventory_items'] ?? [];
        foreach ($items as $item) {
            $product_id = intval($item['product_id'] ?? 0);
            if ($org_id && $product_id) {
                self::receive_purchase(
                    $org_id,
                    $product_id,
                    floatval($item['quantity'] ?? 0),
                    floatval($item['unit_cost'] ?? 0),
                    intval($bill_id)
                );
            }
        }
    }

    public function on_invoice_posted($invoice_id, $payload = []) {
        $org_id = intval($payload['org_id'] ?? 0);
        $items = $payload['inventory_items'] ?? [];
        foreach ($items as $item) {
            $product_id = intval($item['product_id'] ?? 0);
            if ($org_id && $product_id) {
                self::record_sale(
                    $org_id,
                    $product_id,
                    floatval($item['quantity'] ?? 0),
                    intval($invoice_id)
                );
            }
        }
    }

    private static function insert_movement($data) {
        global $wpdb;

        $wpdb->insert(
            OraBooks_Database::table('inventory_movements'),
            [
                'org_id' => intval($data['org_id']),
                'product_id' => intval($data['product_id']),
                'quantity_change' => floatval($data['quantity_change']),
                'stock_before' => floatval($data['stock_before']),
                'stock_after' => floatval($data['stock_after']),
                'unit_cost' => floatval($data['unit_cost']),
                'movement_value' => floatval($data['movement_value']),
                'reference_type' => sanitize_text_field($data['reference_type']),
                'reference_id' => isset($data['reference_id']) ? intval($data['reference_id']) : null,
                'reason' => isset($data['reason']) ? sanitize_text_field($data['reason']) : null,
                'note' => isset($data['note']) ? sanitize_textarea_field($data['note']) : null,
                'journal_id' => isset($data['journal_id']) ? intval($data['journal_id']) : null,
                'created_by' => isset($data['created_by']) ? intval($data['created_by']) : null,
            ],
            ['%d', '%d', '%f', '%f', '%f', '%f', '%f', '%s', '%d', '%s', '%s', '%d', '%d']
        );

        return intval($wpdb->insert_id);
    }

    private static function create_cogs_journal($org_id, $product, $quantity, $cogs_amount, $reference_id, $user_id) {
        if (!class_exists('OraBooks_Posting') || $cogs_amount <= 0) {
            return null;
        }

        $journal_id = OraBooks_Posting::create_journal([
            'org_id' => intval($org_id),
            'transaction_date' => current_time('Y-m-d'),
            'source_type' => 'inventory_sale',
            'source_id' => $reference_id ? intval($reference_id) : null,
            'metadata' => [
                'product_id' => intval($product->id),
                'sku' => $product->sku,
                'quantity' => floatval($quantity),
            ],
        ], $user_id ?: orabooks_get_current_user_id());

        if (is_wp_error($journal_id)) {
            return $journal_id;
        }

        OraBooks_Posting::add_lines($journal_id, [
            [
                'account_code' => self::COGS_ACCOUNT,
                'debit' => $cogs_amount,
                'credit' => 0,
                'description' => 'COGS for SKU ' . $product->sku,
            ],
            [
                'account_code' => self::INVENTORY_ASSET_ACCOUNT,
                'debit' => 0,
                'credit' => $cogs_amount,
                'description' => 'Inventory reduction for SKU ' . $product->sku,
            ],
        ]);

        return $journal_id;
    }

    private function current_user_id() {
        return orabooks_get_current_user_id();
    }

    private function require_customer_org_access($user_id, $org_id) {
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }
    }

    private function require_inventory_permission($user_id, $org_id, $permissions) {
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        if ($org_id <= 0) {
            orabooks_json_error('Organization ID required', 400);
        }

        $this->require_customer_org_access($user_id, $org_id);

        if (current_user_can('manage_options')) {
            return;
        }

        foreach ((array) $permissions as $permission) {
            if (OraBooks_RBAC::require_permission($user_id, $org_id, $permission)) {
                return;
            }
        }

        orabooks_json_error('Permission denied', 403);
    }

    public function ajax_products_list() {
        $user_id = $this->current_user_id();
        $org_id = intval($_GET['org_id'] ?? 0);
        $this->require_inventory_permission($user_id, $org_id, ['view_reports']);
        orabooks_json_success(self::get_products_list($org_id, $_GET));
    }

    public function ajax_product_create() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_inventory_permission($user_id, $org_id, ['manage_org_settings']);

        if (!empty($_FILES['item_image']['name'])) {
            if (!function_exists('media_handle_upload')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
            }

            $attachment_id = media_handle_upload('item_image', 0);
            if (is_wp_error($attachment_id)) {
                orabooks_json_error($attachment_id->get_error_message(), 400);
            }

            $_POST['item_image_url'] = wp_get_attachment_url($attachment_id);
        }

        $result = self::create_product($org_id, $_POST);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success(['product' => $result]);
    }

    public function ajax_adjust_stock() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_inventory_permission($user_id, $org_id, ['manage_org_settings', 'submit_transaction']);
        $result = self::adjust_stock(
            $org_id,
            intval($_POST['product_id'] ?? 0),
            floatval($_POST['quantity_change'] ?? 0),
            sanitize_text_field($_POST['reason'] ?? ''),
            $user_id,
            sanitize_textarea_field($_POST['note'] ?? '')
        );
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success($result);
    }

    public function ajax_movements() {
        $user_id = $this->current_user_id();
        $org_id = intval($_GET['org_id'] ?? 0);
        $this->require_inventory_permission($user_id, $org_id, ['view_reports']);
        $product_id = intval($_GET['product_id'] ?? 0);
        $movements = $product_id > 0
            ? self::get_movements($org_id, $product_id, $_GET)
            : self::get_recent_movements($org_id, $_GET);
        orabooks_json_success(['movements' => $movements]);
    }

    public function ajax_lookups_list() {
        $user_id = $this->current_user_id();
        $org_id = intval($_GET['org_id'] ?? 0);
        $this->require_inventory_permission($user_id, $org_id, ['view_reports']);
        $lookup_type = sanitize_key($_GET['lookup_type'] ?? '');
        $lookups = self::get_lookups_list($org_id, $lookup_type !== '' ? $lookup_type : null);
        if ($lookup_type !== '') {
            orabooks_json_success(['lookups' => $lookups]);
            return;
        }
        orabooks_json_success(['lookups' => $lookups]);
    }

    public function ajax_lookup_create() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $this->require_inventory_permission($user_id, $org_id, ['manage_org_settings']);
        $lookup_type = sanitize_key($_POST['lookup_type'] ?? '');
        $result = self::create_lookup($org_id, $lookup_type, $_POST);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success(['lookup' => $result]);
    }

    public function ajax_lookup_code() {
        $user_id = $this->current_user_id();
        $org_id = intval($_GET['org_id'] ?? 0);
        $this->require_inventory_permission($user_id, $org_id, ['view_reports']);
        $lookup_type = sanitize_key($_GET['lookup_type'] ?? '');
        orabooks_json_success([
            'code' => self::generate_lookup_code($org_id, $lookup_type),
        ]);
    }
}
