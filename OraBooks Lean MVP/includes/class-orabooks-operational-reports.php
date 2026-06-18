<?php
/**
 * OraBooks Operational Reports (SL-075)
 *
 * Near real-time operational reporting from read models. This module does not
 * generate core financial statements; those belong to SL-074.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Operational_Reports {

    const CACHE_TTL = 300;

    private static $instance = null;

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();

            add_action('wp_ajax_orabooks_operational_report', [self::$instance, 'ajax_generate_report']);
            add_action('wp_ajax_nopriv_orabooks_operational_report', [self::$instance, 'ajax_generate_report']);
            add_action('wp_ajax_orabooks_operational_export', [self::$instance, 'ajax_request_export']);
            add_action('wp_ajax_nopriv_orabooks_operational_export', [self::$instance, 'ajax_request_export']);
            add_action('wp_ajax_orabooks_inventory_reorder_level', [self::$instance, 'ajax_update_reorder_level']);
            add_action('wp_ajax_nopriv_orabooks_inventory_reorder_level', [self::$instance, 'ajax_update_reorder_level']);

            add_action('orabooks_invoice_posted', [self::$instance, 'project_invoice_posted'], 10, 2);
            add_action('orabooks_payment_recorded', [self::$instance, 'project_invoice_posted'], 10, 2);
            add_action('orabooks_bill_posted', [self::$instance, 'project_bill_posted'], 10, 2);
            add_action('orabooks_bill_payment_recorded', [self::$instance, 'project_bill_posted'], 10, 2);
            add_action('orabooks_inventory_movement', [self::$instance, 'project_inventory_status'], 10, 2);
            add_action('orabooks_bank_statement_imported', [self::$instance, 'project_bank_reconciliation_summary'], 10, 2);
            add_action('orabooks_reconciliation_completed', [self::$instance, 'project_bank_reconciliation_summary'], 10, 2);
            add_action('orabooks_daily_low_stock_check', [self::$instance, 'check_low_stock_alerts']);
        }
        return self::$instance;
    }

    public static function get_create_table_sql() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $tables = [];
        $table_orgs = OraBooks_Database::table('organizations');

        // report_ar_aging and report_ap_aging are created by SL-074 as shared read models.
        $table_inventory = OraBooks_Database::table('report_inventory_status');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_inventory} (
            org_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            sku VARCHAR(100) NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            current_stock DECIMAL(20,4) DEFAULT 0,
            reorder_level DECIMAL(20,4) DEFAULT 10,
            status VARCHAR(20) NOT NULL DEFAULT 'ok',
            last_event_id BIGINT UNSIGNED NULL,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (org_id, product_id),
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
            INDEX idx_org_status (org_id, status),
            INDEX idx_sku (sku)
        ) {$charset_collate};";

        $table_bank = OraBooks_Database::table('report_bank_reconciliation_summary');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_bank} (
            org_id BIGINT UNSIGNED NOT NULL,
            bank_account_id BIGINT UNSIGNED NOT NULL,
            as_of_date DATE NOT NULL,
            total_unmatched_count INT DEFAULT 0,
            total_unmatched_amount DECIMAL(20,2) DEFAULT 0,
            last_reconciled_at TIMESTAMP NULL,
            last_event_id BIGINT UNSIGNED NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (org_id, bank_account_id, as_of_date),
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE
        ) {$charset_collate};";

        $table_sales = OraBooks_Database::table('report_sales_summary');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_sales} (
            org_id BIGINT UNSIGNED NOT NULL,
            period_date DATE NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            total_sales DECIMAL(20,2) DEFAULT 0,
            total_returns DECIMAL(20,2) DEFAULT 0,
            net_sales DECIMAL(20,2) DEFAULT 0,
            last_event_id BIGINT UNSIGNED NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (org_id, period_date, customer_id),
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
            INDEX idx_org_customer (org_id, customer_id)
        ) {$charset_collate};";

        $table_purchase = OraBooks_Database::table('report_purchase_summary');
        $tables[] = "CREATE TABLE IF NOT EXISTS {$table_purchase} (
            org_id BIGINT UNSIGNED NOT NULL,
            period_date DATE NOT NULL,
            vendor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            total_purchases DECIMAL(20,2) DEFAULT 0,
            last_event_id BIGINT UNSIGNED NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (org_id, period_date, vendor_id),
            FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
            INDEX idx_org_vendor (org_id, vendor_id)
        ) {$charset_collate};";

        return $tables;
    }

    public static function generate_report($org_id, $report_type, $args = []) {
        $org_id = intval($org_id);
        $report_type = sanitize_text_field($report_type);
        $correlation_id = sanitize_text_field($args['correlation_id'] ?? self::correlation_id());

        if ($org_id <= 0 || !self::valid_report_type($report_type)) {
            return new WP_Error('invalid_report_request', 'Invalid operational report request.');
        }

        $cache_key = self::cache_key($org_id, $report_type, $args);
        $cached = wp_cache_get($cache_key, 'orabooks_operational_reports');
        if ($cached !== false) {
            $cached['from_cache'] = true;
            return $cached;
        }

        switch ($report_type) {
            case 'ar_aging':
                $data = self::get_ar_aging($org_id, $args['as_of_date'] ?? current_time('Y-m-d'), intval($args['customer_id'] ?? 0));
                break;
            case 'ap_aging':
                $data = self::get_ap_aging($org_id, $args['as_of_date'] ?? current_time('Y-m-d'), intval($args['vendor_id'] ?? 0));
                break;
            case 'inventory_status':
                $data = self::get_inventory_status($org_id, $args);
                break;
            case 'bank_reconciliation':
                $data = self::get_bank_reconciliation_summary($org_id, $args);
                break;
            case 'sales_summary':
                $data = self::get_sales_summary($org_id, $args);
                break;
            case 'purchase_summary':
                $data = self::get_purchase_summary($org_id, $args);
                break;
            default:
                return new WP_Error('unsupported_report', 'Unsupported operational report.');
        }

        $result = [
            'report_type' => $report_type,
            'org_id' => $org_id,
            'correlation_id' => $correlation_id,
            'generated_at' => current_time('mysql'),
            'from_cache' => false,
            'data' => $data,
        ];

        wp_cache_set($cache_key, $result, 'orabooks_operational_reports', self::CACHE_TTL);
        orabooks_log_event('operational_report_generated', 'Operational report generated', 'info', [
            'report_type' => $report_type,
            'correlation_id' => $correlation_id,
        ], get_current_user_id(), $org_id);

        return $result;
    }

    private static function valid_report_type($type) {
        return in_array($type, ['ar_aging', 'ap_aging', 'inventory_status', 'bank_reconciliation', 'sales_summary', 'purchase_summary'], true);
    }

    public static function get_ar_aging($org_id, $as_of_date = null, $customer_id = 0) {
        global $wpdb;

        $table = OraBooks_Database::table('report_ar_aging');
        $where = 'org_id = %d';
        $params = [intval($org_id)];

        if ($customer_id > 0) {
            $where .= ' AND entity_id = %d';
            $params[] = $customer_id;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT entity_id as customer_id, bucket, SUM(amount) as amount
             FROM {$table}
             WHERE {$where}
             GROUP BY entity_id, bucket
             ORDER BY entity_id, bucket",
            $params
        ));

        return self::aging_rows($rows, 'customer_id');
    }

    public static function get_ap_aging($org_id, $as_of_date = null, $vendor_id = 0) {
        if (class_exists('OraBooks_Vendors') && method_exists('OraBooks_Vendors', 'get_ap_aging') && $vendor_id <= 0) {
            return [
                'summary' => OraBooks_Vendors::get_ap_aging($org_id, $as_of_date),
                'rows' => [],
            ];
        }

        global $wpdb;
        $table = OraBooks_Database::table('report_ap_aging');
        $where = 'org_id = %d';
        $params = [intval($org_id)];

        if ($vendor_id > 0) {
            $where .= ' AND entity_id = %d';
            $params[] = $vendor_id;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT entity_id as vendor_id, bucket, SUM(amount) as amount
             FROM {$table}
             WHERE {$where}
             GROUP BY entity_id, bucket
             ORDER BY entity_id, bucket",
            $params
        ));

        return ['summary' => self::aging_totals($rows), 'rows' => self::aging_rows($rows, 'vendor_id')];
    }

    private static function aging_rows($rows, $entity_key) {
        $result = [];
        foreach ($rows as $row) {
            $id = intval($row->{$entity_key});
            if (!isset($result[$id])) {
                $result[$id] = [
                    $entity_key => $id,
                    'current' => 0.0,
                    '30' => 0.0,
                    '60' => 0.0,
                    '90_plus' => 0.0,
                    'total_due' => 0.0,
                ];
            }
            $bucket = self::normalize_bucket($row->bucket);
            $amount = (float) $row->amount;
            $result[$id][$bucket] += $amount;
            $result[$id]['total_due'] += $amount;
        }
        return array_values($result);
    }

    private static function aging_totals($rows) {
        $totals = ['current' => 0.0, '30' => 0.0, '60' => 0.0, '90_plus' => 0.0];
        foreach ($rows as $row) {
            $totals[self::normalize_bucket($row->bucket)] += (float) $row->amount;
        }
        return $totals;
    }

    private static function normalize_bucket($bucket) {
        $bucket = sanitize_text_field($bucket);
        return in_array($bucket, ['current', '30', '60', '90_plus'], true) ? $bucket : 'current';
    }

    public static function get_inventory_status($org_id, $args = []) {
        global $wpdb;

        $table = OraBooks_Database::table('report_inventory_status');
        $where = 'org_id = %d';
        $params = [intval($org_id)];

        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $params[] = sanitize_text_field($args['status']);
        }

        if (!empty($args['category'])) {
            $where .= ' AND product_name LIKE %s';
            $params[] = '%' . $wpdb->esc_like(sanitize_text_field($args['category'])) . '%';
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where} ORDER BY status ASC, product_name ASC",
            $params
        ));

        $low_count = 0;
        foreach ($rows as $row) {
            $row->current_stock = (float) $row->current_stock;
            $row->reorder_level = (float) $row->reorder_level;
            $row->status = $row->current_stock < $row->reorder_level ? 'low' : 'ok';
            if ($row->status === 'low') {
                $low_count++;
            }
        }

        return ['products' => $rows, 'low_stock_count' => $low_count];
    }

    public static function get_bank_reconciliation_summary($org_id, $args = []) {
        global $wpdb;

        $table = OraBooks_Database::table('report_bank_reconciliation_summary');
        $where = 'org_id = %d';
        $params = [intval($org_id)];

        if (!empty($args['bank_account_id'])) {
            $where .= ' AND bank_account_id = %d';
            $params[] = intval($args['bank_account_id']);
        }

        if (!empty($args['as_of_date'])) {
            $where .= ' AND as_of_date <= %s';
            $params[] = sanitize_text_field($args['as_of_date']);
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where} ORDER BY as_of_date DESC, bank_account_id ASC",
            $params
        ));
    }

    public static function get_sales_summary($org_id, $args = []) {
        return self::period_summary('report_sales_summary', $org_id, $args, 'customer_id', ['total_sales', 'total_returns', 'net_sales']);
    }

    public static function get_purchase_summary($org_id, $args = []) {
        return self::period_summary('report_purchase_summary', $org_id, $args, 'vendor_id', ['total_purchases']);
    }

    private static function period_summary($table_name, $org_id, $args, $entity_col, $amount_cols) {
        global $wpdb;

        $table = OraBooks_Database::table($table_name);
        $where = 'org_id = %d';
        $params = [intval($org_id)];

        if (!empty($args['start_date'])) {
            $where .= ' AND period_date >= %s';
            $params[] = sanitize_text_field($args['start_date']);
        }
        if (!empty($args['end_date'])) {
            $where .= ' AND period_date <= %s';
            $params[] = sanitize_text_field($args['end_date']);
        }
        if (!empty($args[$entity_col])) {
            $where .= " AND {$entity_col} = %d";
            $params[] = intval($args[$entity_col]);
        }

        $sum_cols = [];
        foreach ($amount_cols as $col) {
            $sum_cols[] = "SUM({$col}) as {$col}";
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT period_date, {$entity_col}, " . implode(', ', $sum_cols) . "
             FROM {$table}
             WHERE {$where}
             GROUP BY period_date, {$entity_col}
             ORDER BY period_date ASC",
            $params
        ));
    }

    public static function update_reorder_level($org_id, $product_id, $reorder_level) {
        global $wpdb;

        $table_products = OraBooks_Database::table('products');
        $table_status = OraBooks_Database::table('report_inventory_status');
        $reorder_level = max(0, (float) $reorder_level);

        $wpdb->update($table_products, ['low_stock_threshold' => $reorder_level], [
            'org_id' => intval($org_id),
            'id' => intval($product_id),
        ], ['%f'], ['%d', '%d']);

        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT id, org_id, sku, name, current_stock, low_stock_threshold FROM {$table_products} WHERE org_id = %d AND id = %d",
            intval($org_id),
            intval($product_id)
        ));

        if ($product) {
            self::upsert_inventory_status($product, null);
        }

        self::invalidate_cache($org_id, 'inventory_status');
        return true;
    }

    public static function project_inventory_status($org_id, $payload = []) {
        global $wpdb;

        $product_id = intval($payload['product_id'] ?? 0);
        if ($product_id <= 0) {
            return new WP_Error('missing_product', 'Missing product for inventory projection.');
        }

        $table_products = OraBooks_Database::table('products');
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT id, org_id, sku, name, current_stock, low_stock_threshold FROM {$table_products} WHERE id = %d AND org_id = %d",
            $product_id,
            intval($org_id)
        ));

        if (!$product) {
            return new WP_Error('product_not_found', 'Product not found for inventory projection.');
        }

        self::upsert_inventory_status($product, intval($payload['event_id'] ?? 0));
        self::invalidate_cache($org_id, 'inventory_status');
        return true;
    }

    private static function upsert_inventory_status($product, $event_id = null) {
        global $wpdb;

        $table = OraBooks_Database::table('report_inventory_status');
        $reorder_level = isset($product->low_stock_threshold) && $product->low_stock_threshold !== null ? (float) $product->low_stock_threshold : 10.0;
        $current_stock = (float) $product->current_stock;
        $status = $current_stock < $reorder_level ? 'low' : 'ok';

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (org_id, product_id, sku, product_name, current_stock, reorder_level, status, last_event_id)
             VALUES (%d, %d, %s, %s, %f, %f, %s, %d)
             ON DUPLICATE KEY UPDATE
                sku = VALUES(sku),
                product_name = VALUES(product_name),
                current_stock = VALUES(current_stock),
                reorder_level = VALUES(reorder_level),
                status = VALUES(status),
                last_event_id = GREATEST(COALESCE(last_event_id, 0), VALUES(last_event_id))",
            intval($product->org_id),
            intval($product->id),
            $product->sku,
            $product->name,
            $current_stock,
            $reorder_level,
            $status,
            intval($event_id)
        ));
    }

    public static function project_invoice_posted($invoice_id, $payload = []) {
        global $wpdb;

        $org_id = intval($payload['org_id'] ?? 0);
        $table_invoices = OraBooks_Database::table('invoices');
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT id, org_id, customer_id, transaction_date, due_date, total_amount, paid_amount, payment_status, workflow_status
             FROM {$table_invoices} WHERE id = %d AND org_id = %d",
            intval($invoice_id),
            $org_id
        ));
        if (!$invoice) {
            return new WP_Error('invoice_not_found', 'Invoice not found for report projection.');
        }

        self::project_ar_for_invoice($invoice, intval($payload['event_id'] ?? 0));
        self::project_sales_for_invoice($invoice, intval($payload['event_id'] ?? 0));
        self::invalidate_cache($org_id, 'ar_aging');
        self::invalidate_cache($org_id, 'sales_summary');
        return true;
    }

    private static function project_ar_for_invoice($invoice, $event_id = 0) {
        global $wpdb;

        $table = OraBooks_Database::table('report_ar_aging');
        $outstanding = max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount);
        $bucket = self::bucket_for_due_date($invoice->due_date, current_time('Y-m-d'));

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (org_id, entity_id, period_date, amount, bucket)
             VALUES (%d, %d, %s, %f, %s)
             ON DUPLICATE KEY UPDATE amount = VALUES(amount), bucket = VALUES(bucket)",
            intval($invoice->org_id),
            intval($invoice->customer_id),
            current_time('Y-m-d'),
            $outstanding,
            $bucket
        ));
    }

    private static function project_sales_for_invoice($invoice, $event_id = 0) {
        global $wpdb;

        $table = OraBooks_Database::table('report_sales_summary');
        $date = sanitize_text_field($invoice->transaction_date);
        $total_sales = (float) $invoice->total_amount;
        $returns = $invoice->payment_status === 'cancelled' ? $total_sales : 0.0;
        $net = $total_sales - $returns;

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (org_id, period_date, customer_id, total_sales, total_returns, net_sales, last_event_id)
             VALUES (%d, %s, %d, %f, %f, %f, %d)
             ON DUPLICATE KEY UPDATE
                total_sales = VALUES(total_sales),
                total_returns = VALUES(total_returns),
                net_sales = VALUES(net_sales),
                last_event_id = GREATEST(COALESCE(last_event_id, 0), VALUES(last_event_id))",
            intval($invoice->org_id),
            $date,
            intval($invoice->customer_id),
            $total_sales,
            $returns,
            $net,
            intval($event_id)
        ));
    }

    public static function project_bill_posted($bill_id, $payload = []) {
        global $wpdb;

        $org_id = intval($payload['org_id'] ?? 0);
        $table_bills = OraBooks_Database::table('bills');
        $bill = $wpdb->get_row($wpdb->prepare(
            "SELECT id, org_id, vendor_id, bill_date, due_date, total_amount, paid_amount, payment_status, workflow_status
             FROM {$table_bills} WHERE id = %d AND org_id = %d",
            intval($bill_id),
            $org_id
        ));
        if (!$bill) {
            return new WP_Error('bill_not_found', 'Bill not found for report projection.');
        }

        self::project_ap_for_bill($bill);
        self::project_purchase_for_bill($bill, intval($payload['event_id'] ?? 0));
        self::invalidate_cache($org_id, 'ap_aging');
        self::invalidate_cache($org_id, 'purchase_summary');
        return true;
    }

    private static function project_ap_for_bill($bill) {
        global $wpdb;

        $table = OraBooks_Database::table('report_ap_aging');
        $outstanding = max(0, (float) $bill->total_amount - (float) $bill->paid_amount);
        $bucket = self::bucket_for_due_date($bill->due_date, current_time('Y-m-d'));

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (org_id, entity_id, period_date, amount, bucket)
             VALUES (%d, %d, %s, %f, %s)
             ON DUPLICATE KEY UPDATE amount = VALUES(amount), bucket = VALUES(bucket)",
            intval($bill->org_id),
            intval($bill->vendor_id),
            current_time('Y-m-d'),
            $outstanding,
            $bucket
        ));
    }

    private static function project_purchase_for_bill($bill, $event_id = 0) {
        global $wpdb;

        $table = OraBooks_Database::table('report_purchase_summary');
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (org_id, period_date, vendor_id, total_purchases, last_event_id)
             VALUES (%d, %s, %d, %f, %d)
             ON DUPLICATE KEY UPDATE
                total_purchases = VALUES(total_purchases),
                last_event_id = GREATEST(COALESCE(last_event_id, 0), VALUES(last_event_id))",
            intval($bill->org_id),
            sanitize_text_field($bill->bill_date),
            intval($bill->vendor_id),
            (float) $bill->total_amount,
            intval($event_id)
        ));
    }

    public static function project_bank_reconciliation_summary($org_id, $payload = []) {
        global $wpdb;

        $org_id = intval($org_id);
        $bank_account_id = intval($payload['bank_account_id'] ?? 0);
        if ($org_id <= 0 || $bank_account_id <= 0) {
            return new WP_Error('invalid_bank_projection', 'Missing bank reconciliation projection identifiers.');
        }

        $table_tx = OraBooks_Database::table('bank_transactions');
        $table_log = OraBooks_Database::table('reconciliation_log');
        $table_summary = OraBooks_Database::table('report_bank_reconciliation_summary');
        $as_of_date = sanitize_text_field($payload['as_of_date'] ?? current_time('Y-m-d'));

        $summary = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) as unmatched_count, COALESCE(SUM(amount), 0) as unmatched_amount
             FROM {$table_tx}
             WHERE org_id = %d AND bank_account_id = %d AND status = 'unmatched'",
            $org_id,
            $bank_account_id
        ));
        $last_reconciled_at = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(reconciled_at) FROM {$table_log} WHERE org_id = %d AND bank_account_id = %d",
            $org_id,
            $bank_account_id
        ));

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_summary} (org_id, bank_account_id, as_of_date, total_unmatched_count, total_unmatched_amount, last_reconciled_at, last_event_id)
             VALUES (%d, %d, %s, %d, %f, %s, %d)
             ON DUPLICATE KEY UPDATE
                total_unmatched_count = VALUES(total_unmatched_count),
                total_unmatched_amount = VALUES(total_unmatched_amount),
                last_reconciled_at = VALUES(last_reconciled_at),
                last_event_id = GREATEST(COALESCE(last_event_id, 0), VALUES(last_event_id))",
            $org_id,
            $bank_account_id,
            $as_of_date,
            intval($summary->unmatched_count ?? 0),
            (float) ($summary->unmatched_amount ?? 0),
            $last_reconciled_at,
            intval($payload['event_id'] ?? 0)
        ));

        self::invalidate_cache($org_id, 'bank_reconciliation');
        return true;
    }

    private static function bucket_for_due_date($due_date, $as_of_date) {
        $days = floor((strtotime($as_of_date) - strtotime($due_date)) / DAY_IN_SECONDS);
        if ($days <= 0) {
            return 'current';
        }
        if ($days <= 30) {
            return '30';
        }
        if ($days <= 60) {
            return '60';
        }
        return '90_plus';
    }

    public static function check_low_stock_alerts($org_id = null) {
        global $wpdb;

        $table = OraBooks_Database::table('report_inventory_status');
        $where = "status = 'low'";
        $params = [];
        if ($org_id) {
            $where .= ' AND org_id = %d';
            $params[] = intval($org_id);
        }

        $sql = "SELECT * FROM {$table} WHERE {$where}";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);

        foreach ($rows as $row) {
            orabooks_log_event('inventory_low_stock_alert', 'Low stock alert', 'warning', [
                'product_id' => intval($row->product_id),
                'sku' => $row->sku,
                'current_stock' => (float) $row->current_stock,
                'reorder_level' => (float) $row->reorder_level,
            ], null, intval($row->org_id));

            if (function_exists('orabooks_publish_event')) {
                orabooks_publish_event('inventory_low_stock_alert', intval($row->product_id), [
                    'org_id' => intval($row->org_id),
                    'product_id' => intval($row->product_id),
                    'sku' => $row->sku,
                    'product_name' => $row->product_name,
                    'priority' => 'high',
                ]);
            }
        }

        return ['alerts' => count($rows)];
    }

    private static function cache_key($org_id, $report_type, $args) {
        ksort($args);
        return 'op_report_' . md5($org_id . '|' . $report_type . '|' . wp_json_encode($args));
    }

    public static function invalidate_cache($org_id, $report_type = null) {
        // WordPress object cache has no portable group flush. Version bump keeps
        // current request caches coherent while persistent caches expire by TTL.
        wp_cache_set('last_invalidation_' . intval($org_id) . '_' . ($report_type ?: 'all'), time(), 'orabooks_operational_reports', self::CACHE_TTL);
    }

    private static function correlation_id() {
        return function_exists('orabooks_uuid') ? orabooks_uuid() : bin2hex(random_bytes(16));
    }

    /**
     * Resolve operational report data for SL-114 CSV/PDF export.
     *
     * @param array $params org_id, report_type (or export_type operational_*).
     * @return array|null { columns, rows } or null on failure.
     */
    public static function export_report_data($params) {
        $org_id = intval($params['org_id'] ?? 0);
        $report_type = sanitize_text_field($params['report_type'] ?? '');

        if (!$report_type && !empty($params['export_type']) && strpos($params['export_type'], 'operational_') === 0) {
            $report_type = substr($params['export_type'], strlen('operational_'));
        }

        if ($org_id <= 0 || !self::valid_report_type($report_type)) {
            return null;
        }

        $result = self::generate_report($org_id, $report_type, $params);
        if (is_wp_error($result)) {
            return null;
        }

        return self::flatten_for_export($result);
    }

    /**
     * Flatten an operational report into tabular export rows.
     */
    public static function flatten_for_export($result) {
        $report_type = $result['report_type'] ?? '';
        $data = $result['data'] ?? $result;

        switch ($report_type) {
            case 'ar_aging':
                $rows = is_array($data) ? array_values($data) : [];
                return [
                    'columns' => ['customer_id', 'current', '30', '60', '90_plus', 'total_due'],
                    'rows' => $rows,
                ];

            case 'ap_aging':
                $rows = is_array($data) && isset($data['rows']) ? $data['rows'] : (is_array($data) ? array_values($data) : []);
                return [
                    'columns' => ['vendor_id', 'current', '30', '60', '90_plus', 'total_due'],
                    'rows' => $rows,
                ];

            case 'inventory_status':
                $rows = [];
                foreach ($data['products'] ?? [] as $product) {
                    $rows[] = is_object($product) ? (array) $product : $product;
                }
                if (empty($rows)) {
                    return null;
                }
                return [
                    'columns' => array_keys($rows[0]),
                    'rows' => $rows,
                ];

            case 'bank_reconciliation':
                $rows = [];
                foreach (is_array($data) ? $data : [] as $row) {
                    $rows[] = is_object($row) ? (array) $row : $row;
                }
                if (empty($rows)) {
                    return null;
                }
                return [
                    'columns' => array_keys($rows[0]),
                    'rows' => $rows,
                ];

            case 'sales_summary':
            case 'purchase_summary':
                $rows = [];
                foreach (is_array($data) ? $data : [] as $row) {
                    $rows[] = is_object($row) ? (array) $row : $row;
                }
                if (empty($rows)) {
                    return null;
                }
                return [
                    'columns' => array_keys($rows[0]),
                    'rows' => $rows,
                ];
        }

        return null;
    }

    public function ajax_generate_report() {
        $user_id = get_current_user_id();
        $org_id = intval($_REQUEST['org_id'] ?? 0);
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'view_operational_reports')) {
            orabooks_json_error('Permission denied', 403);
        }

        $result = self::generate_report($org_id, $_REQUEST['report_type'] ?? '', $_REQUEST);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success($result);
    }

    public function ajax_request_export() {
        $user_id = get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'export_reports')) {
            orabooks_json_error('Permission denied', 403);
        }

        orabooks_log_event('operational_report_export_requested', 'Operational report export requested', 'info', [
            'report_type' => sanitize_text_field($_POST['report_type'] ?? ''),
            'format' => sanitize_text_field($_POST['format'] ?? 'csv'),
        ], $user_id, $org_id);

        if (class_exists('OraBooks_Exports') && method_exists('OraBooks_Exports', 'request_export')) {
            $result = OraBooks_Exports::request_export($org_id, $user_id, 'operational_' . sanitize_text_field($_POST['report_type'] ?? 'report'), sanitize_text_field($_POST['format'] ?? 'csv'), $_POST);
            if (is_wp_error($result)) {
                orabooks_json_error($result->get_error_message(), 400);
            }
            orabooks_json_success($result);
        }

        orabooks_json_error('Export service unavailable.', 501);
    }

    public function ajax_update_reorder_level() {
        $user_id = get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_inventory')) {
            orabooks_json_error('Permission denied', 403);
        }

        $result = self::update_reorder_level($org_id, intval($_POST['product_id'] ?? 0), floatval($_POST['reorder_level'] ?? 0));
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success([], 'Reorder level updated.');
    }
}
