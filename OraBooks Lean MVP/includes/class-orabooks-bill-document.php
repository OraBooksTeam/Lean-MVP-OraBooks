<?php
/**
 * OraBooks Vendor Bill Document (SL-027 extension)
 *
 * Bill line items for multi-line vendor bills.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Bill_Document {

    public static function init() {
        return new self();
    }

    public static function get_create_table_sql() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_bills = OraBooks_Database::table('bills');
        $table_orgs = OraBooks_Database::table('organizations');
        $table_lines = OraBooks_Database::table('bill_line_items');

        return [
            "CREATE TABLE IF NOT EXISTS {$table_lines} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                bill_id BIGINT UNSIGNED NOT NULL,
                line_number INT NOT NULL DEFAULT 1,
                description TEXT NOT NULL,
                quantity DECIMAL(20,4) NOT NULL DEFAULT 1,
                unit_price DECIMAL(20,2) NOT NULL DEFAULT 0,
                line_total DECIMAL(20,2) NOT NULL DEFAULT 0,
                sku_code VARCHAR(64) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
                FOREIGN KEY (bill_id) REFERENCES {$table_bills}(id) ON DELETE CASCADE,
                INDEX idx_bill (bill_id),
                INDEX idx_org (org_id)
            ) {$charset_collate};",
        ];
    }

    public static function ensure_schema() {
        global $wpdb;

        $table_lines = OraBooks_Database::table('bill_line_items');
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_lines)) === $table_lines) {
            return;
        }

        $upgrade = ABSPATH . 'wp-admin/includes/upgrade.php';
        if (file_exists($upgrade)) {
            require_once $upgrade;
            foreach (self::get_create_table_sql() as $sql) {
                dbDelta($sql);
            }
        }
    }

    public static function normalize_line_items($lines) {
        if (is_string($lines)) {
            $decoded = json_decode(wp_unslash($lines), true);
            if (!is_array($decoded)) {
                $decoded = json_decode(stripslashes($lines), true);
            }
            $lines = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($lines)) {
            return [];
        }

        $normalized = [];
        $line_number = 1;
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $description = sanitize_textarea_field($line['description'] ?? '');
            if ($description === '') {
                continue;
            }
            $quantity = round(max(0.0001, floatval($line['quantity'] ?? 1)), 4);
            $unit_price = round(floatval($line['unit_price'] ?? 0), 2);
            $line_total = round(floatval($line['line_total'] ?? ($quantity * $unit_price)), 2);
            if ($line_total <= 0) {
                continue;
            }
            $normalized[] = [
                'line_number' => $line_number++,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'line_total' => $line_total,
                'sku_code' => !empty($line['sku_code']) ? sanitize_text_field($line['sku_code']) : null,
            ];
        }

        return $normalized;
    }

    public static function subtotal_from_lines(array $lines) {
        $total = 0.0;
        foreach ($lines as $line) {
            $total += floatval($line['line_total'] ?? 0);
        }
        return round($total, 2);
    }

    public static function save_line_items($org_id, $bill_id, array $lines) {
        global $wpdb;

        self::ensure_schema();

        $table = OraBooks_Database::table('bill_line_items');
        $wpdb->delete($table, ['bill_id' => intval($bill_id)], ['%d']);

        foreach ($lines as $line) {
            $wpdb->insert(
                $table,
                [
                    'org_id' => intval($org_id),
                    'bill_id' => intval($bill_id),
                    'line_number' => intval($line['line_number']),
                    'description' => $line['description'],
                    'quantity' => floatval($line['quantity']),
                    'unit_price' => floatval($line['unit_price']),
                    'line_total' => floatval($line['line_total']),
                    'sku_code' => $line['sku_code'],
                ],
                ['%d', '%d', '%d', '%s', '%f', '%f', '%f', '%s']
            );
        }
    }

    public static function get_line_items($bill_id) {
        global $wpdb;

        self::ensure_schema();

        $table = OraBooks_Database::table('bill_line_items');
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return [];
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE bill_id = %d ORDER BY line_number ASC, id ASC",
            intval($bill_id)
        )) ?: [];
    }
}
