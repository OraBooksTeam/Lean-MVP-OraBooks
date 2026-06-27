<?php
/**
 * OraBooks Standard Sales Invoice Document (SL-021 extension)
 *
 * Line items, party snapshots, org document settings, and rendered invoice HTML.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Invoice_Document {

    private static $instance = null;

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            add_action('wp_ajax_orabooks_invoice_rendered_get', [self::$instance, 'ajax_invoice_rendered_get']);
            add_action('wp_ajax_orabooks_invoice_document_config_get', [self::$instance, 'ajax_document_config_get']);
            add_action('wp_ajax_orabooks_invoice_document_config_set', [self::$instance, 'ajax_document_config_set']);
        }
        return self::$instance;
    }

    public static function get_create_table_sql() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_invoices = OraBooks_Database::table('invoices');
        $table_orgs = OraBooks_Database::table('organizations');
        $table_lines = OraBooks_Database::table('invoice_line_items');

        return [
            "CREATE TABLE IF NOT EXISTS {$table_lines} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                invoice_id BIGINT UNSIGNED NOT NULL,
                line_number INT NOT NULL DEFAULT 1,
                description TEXT NOT NULL,
                quantity DECIMAL(20,4) NOT NULL DEFAULT 1,
                unit_price DECIMAL(20,2) NOT NULL DEFAULT 0,
                line_total DECIMAL(20,2) NOT NULL DEFAULT 0,
                sku_code VARCHAR(64) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (org_id) REFERENCES {$table_orgs}(id) ON DELETE CASCADE,
                FOREIGN KEY (invoice_id) REFERENCES {$table_invoices}(id) ON DELETE CASCADE,
                INDEX idx_invoice (invoice_id),
                INDEX idx_org (org_id)
            ) {$charset_collate};",
        ];
    }

    public static function ensure_schema() {
        global $wpdb;

        $table_invoices = OraBooks_Database::table('invoices');
        $table_lines = OraBooks_Database::table('invoice_line_items');
        $invoice_fields = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_invoices)) === $table_invoices)
            ? self::get_table_column_names($table_invoices)
            : [];
        $lines_ready = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_lines)) === $table_lines;

        if (
            self::get_schema_flag('orabooks_sl021_invoice_document_v1') === '1'
            && in_array('subtotal_amount', $invoice_fields, true)
            && $lines_ready
        ) {
            return;
        }

        $upgrade = ABSPATH . 'wp-admin/includes/upgrade.php';
        if (file_exists($upgrade)) {
            require_once $upgrade;
            foreach (self::get_create_table_sql() as $sql) {
                dbDelta($sql);
            }
        }

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_invoices)) === $table_invoices) {
            $fields = self::get_table_column_names($table_invoices);
            $additions = [
                'subtotal_amount' => "ALTER TABLE {$table_invoices} ADD COLUMN subtotal_amount DECIMAL(20,2) NOT NULL DEFAULT 0",
                'discount_amount' => "ALTER TABLE {$table_invoices} ADD COLUMN discount_amount DECIMAL(20,2) NOT NULL DEFAULT 0",
                'po_reference' => "ALTER TABLE {$table_invoices} ADD COLUMN po_reference VARCHAR(100) NULL",
                'seller_snapshot' => "ALTER TABLE {$table_invoices} ADD COLUMN seller_snapshot LONGTEXT NULL",
                'buyer_snapshot' => "ALTER TABLE {$table_invoices} ADD COLUMN buyer_snapshot LONGTEXT NULL",
            ];
            foreach ($additions as $column => $sql) {
                if (!in_array($column, $fields, true)) {
                    $wpdb->query($sql);
                }
            }

            $fields = self::get_table_column_names($table_invoices);
            if (in_array('subtotal_amount', $fields, true) && in_array('total_amount', $fields, true) && in_array('tax_amount', $fields, true)) {
                $wpdb->query(
                    "UPDATE {$table_invoices}
                     SET subtotal_amount = GREATEST(0, total_amount - COALESCE(tax_amount, 0))
                     WHERE (subtotal_amount IS NULL OR subtotal_amount = 0)
                       AND total_amount > 0"
                );
            }
        }

        if (class_exists('OraBooks_AR_Wallet')) {
            OraBooks_AR_Wallet::ensure_schema();
            $table_configs = OraBooks_Database::table('customer_ar_configs');
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_configs)) === $table_configs) {
                $fields = self::get_table_column_names($table_configs);
                $config_additions = [
                    'seller_legal_name' => "ALTER TABLE {$table_configs} ADD COLUMN seller_legal_name VARCHAR(255) NULL AFTER revenue_account_code",
                    'seller_address' => "ALTER TABLE {$table_configs} ADD COLUMN seller_address TEXT NULL AFTER seller_legal_name",
                    'seller_tax_id' => "ALTER TABLE {$table_configs} ADD COLUMN seller_tax_id VARCHAR(64) NULL AFTER seller_address",
                    'seller_email' => "ALTER TABLE {$table_configs} ADD COLUMN seller_email VARCHAR(255) NULL AFTER seller_tax_id",
                    'seller_phone' => "ALTER TABLE {$table_configs} ADD COLUMN seller_phone VARCHAR(50) NULL AFTER seller_email",
                    'payment_instructions' => "ALTER TABLE {$table_configs} ADD COLUMN payment_instructions TEXT NULL AFTER seller_phone",
                    'invoice_terms' => "ALTER TABLE {$table_configs} ADD COLUMN invoice_terms TEXT NULL AFTER payment_instructions",
                ];
                foreach ($config_additions as $column => $sql) {
                    if (!in_array($column, $fields, true)) {
                        $wpdb->query($sql);
                    }
                }
            }
        }

        $invoice_fields = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_invoices)) === $table_invoices)
            ? self::get_table_column_names($table_invoices)
            : [];
        $lines_ready = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_lines)) === $table_lines;
        if (in_array('subtotal_amount', $invoice_fields, true) && $lines_ready) {
            self::set_schema_flag('orabooks_sl021_invoice_document_v1', '1');
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

    public static function save_line_items($org_id, $invoice_id, array $lines) {
        global $wpdb;

        self::ensure_schema();

        $table = OraBooks_Database::table('invoice_line_items');
        $wpdb->delete($table, ['invoice_id' => intval($invoice_id)], ['%d']);

        foreach ($lines as $line) {
            $wpdb->insert(
                $table,
                [
                    'org_id' => intval($org_id),
                    'invoice_id' => intval($invoice_id),
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

    public static function get_line_items($invoice_id) {
        global $wpdb;

        self::ensure_schema();

        $table = OraBooks_Database::table('invoice_line_items');
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return [];
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE invoice_id = %d ORDER BY line_number ASC, id ASC",
            intval($invoice_id)
        )) ?: [];
    }

    public static function get_document_config($org_id) {
        global $wpdb;

        self::ensure_schema();

        $org_id = intval($org_id);
        $org = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name FROM " . OraBooks_Database::table('organizations') . " WHERE id = %d",
            $org_id
        ));

        $defaults = [
            'org_id' => $org_id,
            'seller_legal_name' => $org->name ?? '',
            'seller_address' => '',
            'seller_tax_id' => '',
            'seller_email' => '',
            'seller_phone' => '',
            'payment_instructions' => '',
            'invoice_terms' => 'Payment is due by the due date shown above. Late payments may incur additional charges.',
        ];

        if (!class_exists('OraBooks_AR_Wallet')) {
            return (object) $defaults;
        }

        $config = OraBooks_AR_Wallet::get_ar_config($org_id);
        foreach ($defaults as $key => $value) {
            if (isset($config->$key) && $config->$key !== '' && $config->$key !== null) {
                $defaults[$key] = $config->$key;
            }
        }

        return (object) $defaults;
    }

    public static function save_document_config($org_id, $data) {
        if (!class_exists('OraBooks_AR_Wallet')) {
            return new WP_Error('unavailable', 'AR configuration module unavailable');
        }

        self::ensure_schema();

        return OraBooks_AR_Wallet::save_ar_config($org_id, array_merge($data, [
            'seller_legal_name' => sanitize_text_field($data['seller_legal_name'] ?? ''),
            'seller_address' => sanitize_textarea_field($data['seller_address'] ?? ''),
            'seller_tax_id' => sanitize_text_field($data['seller_tax_id'] ?? ''),
            'seller_email' => sanitize_email($data['seller_email'] ?? ''),
            'seller_phone' => sanitize_text_field($data['seller_phone'] ?? ''),
            'payment_instructions' => sanitize_textarea_field($data['payment_instructions'] ?? ''),
            'invoice_terms' => sanitize_textarea_field($data['invoice_terms'] ?? ''),
        ]));
    }

    public static function build_buyer_snapshot($customer) {
        if (!$customer) {
            return [];
        }

        $billing = array_filter([
            $customer->address ?? '',
            trim(($customer->city ?? '') . ' ' . ($customer->postcode ?? '')),
            trim(($customer->state_id ?? '') . ' ' . ($customer->country_id ?? '')),
        ]);

        $shipping = array_filter([
            $customer->ship_address ?? '',
            trim(($customer->ship_city ?? '') . ' ' . ($customer->ship_postcode ?? '')),
            trim(($customer->ship_state_id ?? '') . ' ' . ($customer->ship_country_id ?? '')),
        ]);

        return [
            'name' => $customer->display_name ?? $customer->email ?? '',
            'email' => $customer->contact_email ?? $customer->email ?? '',
            'phone' => $customer->mobile ?? $customer->phone ?? '',
            'tax_id' => $customer->gstin ?? $customer->tax_number ?? '',
            'billing_address' => implode("\n", $billing),
            'shipping_address' => implode("\n", $shipping),
            'payment_terms' => intval($customer->payment_terms ?? 30),
        ];
    }

    public static function build_seller_snapshot($org_id) {
        $config = self::get_document_config($org_id);
        return [
            'legal_name' => $config->seller_legal_name ?? '',
            'address' => $config->seller_address ?? '',
            'tax_id' => $config->seller_tax_id ?? '',
            'email' => $config->seller_email ?? '',
            'phone' => $config->seller_phone ?? '',
            'payment_instructions' => $config->payment_instructions ?? '',
            'invoice_terms' => $config->invoice_terms ?? '',
        ];
    }

    public static function snapshot_invoice_parties($invoice_id, $org_id) {
        global $wpdb;

        self::ensure_schema();

        $invoice = OraBooks_Customers::get_invoice(intval($invoice_id));
        if (!$invoice) {
            return new WP_Error('not_found', 'Invoice not found');
        }

        $customer = OraBooks_Customers::get_by_id(intval($invoice->customer_id));
        $seller = self::build_seller_snapshot(intval($org_id));
        $buyer = self::build_buyer_snapshot($customer);

        $table = OraBooks_Database::table('invoices');
        $wpdb->update(
            $table,
            [
                'seller_snapshot' => wp_json_encode($seller),
                'buyer_snapshot' => wp_json_encode($buyer),
            ],
            ['id' => intval($invoice_id)],
            ['%s', '%s'],
            ['%d']
        );

        return [
            'seller' => $seller,
            'buyer' => $buyer,
        ];
    }

    public static function decode_snapshot($raw) {
        if (empty($raw)) {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function enrich_invoice($invoice) {
        if (!$invoice) {
            return $invoice;
        }

        $invoice->line_items = self::get_line_items(intval($invoice->id));
        $invoice->seller_snapshot = self::decode_snapshot($invoice->seller_snapshot ?? null);
        $invoice->buyer_snapshot = self::decode_snapshot($invoice->buyer_snapshot ?? null);

        if (empty($invoice->line_items) && floatval($invoice->subtotal_amount ?? 0) <= 0) {
            $invoice->subtotal_amount = self::get_invoice_tax_base($invoice);
        }

        $rendered = self::get_rendered_copy(intval($invoice->id));
        $invoice->rendered_copy = $rendered;

        return $invoice;
    }

    private static function get_invoice_tax_base($invoice) {
        $total = floatval($invoice->total_amount ?? 0);
        $tax = floatval($invoice->tax_amount ?? 0);
        return max(0, round($total - $tax, 2));
    }

    public static function get_rendered_copy($invoice_id) {
        global $wpdb;

        self::ensure_schema();

        $table = OraBooks_Database::table('invoice_rendered_copy');
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE invoice_id = %d",
            intval($invoice_id)
        ));
    }

    public static function save_rendered_copy($invoice_id, $org_id) {
        global $wpdb;

        self::ensure_schema();

        $invoice = OraBooks_Customers::get_invoice(intval($invoice_id));
        if (!$invoice) {
            return null;
        }

        $invoice = self::enrich_invoice($invoice);
        $lines = $invoice->line_items ?: [];
        $seller = !empty($invoice->seller_snapshot) ? $invoice->seller_snapshot : self::build_seller_snapshot(intval($org_id));
        $buyer = !empty($invoice->buyer_snapshot) ? $invoice->buyer_snapshot : self::build_buyer_snapshot(
            OraBooks_Customers::get_by_id(intval($invoice->customer_id))
        );

        $subtotal = floatval($invoice->subtotal_amount ?? 0);
        if ($subtotal <= 0) {
            $subtotal = self::subtotal_from_lines(array_map(function ($line) {
                return (array) $line;
            }, $lines));
        }
        if ($subtotal <= 0) {
            $subtotal = self::get_invoice_tax_base($invoice);
        }

        $discount = floatval($invoice->discount_amount ?? 0);
        $tax_amount = floatval($invoice->tax_amount ?? 0);
        $tax_rate = floatval($invoice->tax_rate ?? 0);
        $total = floatval($invoice->total_amount ?? 0);
        $paid = floatval($invoice->paid_amount ?? 0);
        $balance_due = max(0, round($total - $paid, 2));
        $currency = $invoice->currency ?? 'USD';

        $payload = [
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date,
            'due_date' => $invoice->due_date,
            'po_reference' => $invoice->po_reference ?? '',
            'currency' => $currency,
            'subtotal_amount' => $subtotal,
            'discount_amount' => $discount,
            'tax_type' => $invoice->tax_type ?? 'Tax',
            'tax_rate' => $tax_rate,
            'tax_amount' => $tax_amount,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'balance_due' => $balance_due,
            'seller' => $seller,
            'buyer' => $buyer,
            'line_items' => array_map(function ($line) {
                return [
                    'line_number' => intval($line->line_number ?? 0),
                    'description' => $line->description ?? '',
                    'quantity' => floatval($line->quantity ?? 0),
                    'unit_price' => floatval($line->unit_price ?? 0),
                    'line_total' => floatval($line->line_total ?? 0),
                    'sku_code' => $line->sku_code ?? '',
                ];
            }, $lines),
        ];

        $html = self::build_rendered_html($payload);

        $table = OraBooks_Database::table('invoice_rendered_copy');
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE invoice_id = %d",
            intval($invoice_id)
        ));

        $row = [
            'org_id' => intval($org_id),
            'invoice_id' => intval($invoice_id),
            'rendered_html' => $html,
            'rendered_json' => wp_json_encode($payload),
            'rendered_at' => current_time('mysql'),
        ];

        if ($existing) {
            $wpdb->update($table, $row, ['id' => intval($existing)]);
        } else {
            $wpdb->insert($table, $row);
        }

        return $row;
    }

    public static function build_rendered_html(array $payload) {
        $lines_html = '';
        foreach ($payload['line_items'] as $line) {
            $lines_html .= sprintf(
                '<tr><td>%s</td><td>%s</td><td class="num">%s</td><td class="num">%s</td><td class="num">%s</td></tr>',
                esc_html((string) ($line['sku_code'] ?: '—')),
                esc_html($line['description']),
                esc_html(number_format(floatval($line['quantity']), 2)),
                esc_html(number_format(floatval($line['unit_price']), 2)),
                esc_html(number_format(floatval($line['line_total']), 2))
            );
        }

        if ($lines_html === '') {
            $lines_html = '<tr><td colspan="5">No line items recorded.</td></tr>';
        }

        $seller = $payload['seller'] ?? [];
        $buyer = $payload['buyer'] ?? [];
        $currency = esc_html($payload['currency'] ?? 'USD');

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo esc_html($payload['invoice_number'] ?? 'Invoice'); ?></title>
<style>
body{font-family:Arial,Helvetica,sans-serif;color:#111827;margin:24px;line-height:1.45}
.header{display:flex;justify-content:space-between;gap:24px;margin-bottom:24px}
.title{font-size:28px;font-weight:700;margin:0}
.meta,.party{font-size:14px;color:#374151}
.party h3{margin:0 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:#6b7280}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin:24px 0}
table{width:100%;border-collapse:collapse;margin-top:16px}
th,td{border:1px solid #e5e7eb;padding:10px;text-align:left;font-size:14px}
th{background:#f9fafb;text-transform:uppercase;font-size:12px;letter-spacing:.03em}
.num{text-align:right;white-space:nowrap}
.totals{margin-top:16px;width:320px;margin-left:auto}
.totals td{border:none;padding:6px 0}
.totals .label{text-align:left;color:#4b5563}
.totals .value{text-align:right;font-weight:600}
.grand{font-size:18px;border-top:2px solid #111827;padding-top:8px}
.footer{margin-top:28px;font-size:13px;color:#4b5563;white-space:pre-wrap}
.badge{display:inline-block;padding:2px 8px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:12px}
</style>
</head>
<body>
<div class="header">
  <div>
    <h1 class="title">Tax Invoice</h1>
    <div class="meta">
      <div><strong>Invoice #:</strong> <?php echo esc_html($payload['invoice_number'] ?? ''); ?></div>
      <div><strong>Date:</strong> <?php echo esc_html($payload['invoice_date'] ?? ''); ?></div>
      <div><strong>Due:</strong> <?php echo esc_html($payload['due_date'] ?? ''); ?></div>
      <?php if (!empty($payload['po_reference'])) : ?>
      <div><strong>PO / Ref:</strong> <?php echo esc_html($payload['po_reference']); ?></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="meta" style="text-align:right">
    <span class="badge"><?php echo esc_html($currency); ?></span>
  </div>
</div>

<div class="grid">
  <div class="party">
    <h3>From</h3>
    <div><strong><?php echo esc_html($seller['legal_name'] ?? ''); ?></strong></div>
    <div><?php echo nl2br(esc_html($seller['address'] ?? '')); ?></div>
    <?php if (!empty($seller['tax_id'])) : ?><div>Tax ID: <?php echo esc_html($seller['tax_id']); ?></div><?php endif; ?>
    <?php if (!empty($seller['email'])) : ?><div><?php echo esc_html($seller['email']); ?></div><?php endif; ?>
    <?php if (!empty($seller['phone'])) : ?><div><?php echo esc_html($seller['phone']); ?></div><?php endif; ?>
  </div>
  <div class="party">
    <h3>Bill To</h3>
    <div><strong><?php echo esc_html($buyer['name'] ?? ''); ?></strong></div>
    <div><?php echo nl2br(esc_html($buyer['billing_address'] ?? '')); ?></div>
    <?php if (!empty($buyer['tax_id'])) : ?><div>Tax ID: <?php echo esc_html($buyer['tax_id']); ?></div><?php endif; ?>
    <?php if (!empty($buyer['email'])) : ?><div><?php echo esc_html($buyer['email']); ?></div><?php endif; ?>
    <?php if (!empty($buyer['shipping_address']) && ($buyer['shipping_address'] ?? '') !== ($buyer['billing_address'] ?? '')) : ?>
    <div style="margin-top:10px"><strong>Ship To</strong><br><?php echo nl2br(esc_html($buyer['shipping_address'])); ?></div>
    <?php endif; ?>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>SKU</th>
      <th>Description</th>
      <th class="num">Qty</th>
      <th class="num">Unit Price</th>
      <th class="num">Amount</th>
    </tr>
  </thead>
  <tbody>
    <?php echo $lines_html; ?>
  </tbody>
</table>

<table class="totals">
  <tr><td class="label">Subtotal</td><td class="value num"><?php echo esc_html(number_format(floatval($payload['subtotal_amount'] ?? 0), 2)); ?> <?php echo $currency; ?></td></tr>
  <?php if (floatval($payload['discount_amount'] ?? 0) > 0) : ?>
  <tr><td class="label">Discount</td><td class="value num">-<?php echo esc_html(number_format(floatval($payload['discount_amount']), 2)); ?> <?php echo $currency; ?></td></tr>
  <?php endif; ?>
  <tr><td class="label"><?php echo esc_html($payload['tax_type'] ?? 'Tax'); ?> (<?php echo esc_html(number_format(floatval($payload['tax_rate'] ?? 0), 2)); ?>%)</td><td class="value num"><?php echo esc_html(number_format(floatval($payload['tax_amount'] ?? 0), 2)); ?> <?php echo $currency; ?></td></tr>
  <tr class="grand"><td class="label">Total</td><td class="value num"><?php echo esc_html(number_format(floatval($payload['total_amount'] ?? 0), 2)); ?> <?php echo $currency; ?></td></tr>
  <?php if (floatval($payload['paid_amount'] ?? 0) > 0) : ?>
  <tr><td class="label">Paid</td><td class="value num"><?php echo esc_html(number_format(floatval($payload['paid_amount']), 2)); ?> <?php echo $currency; ?></td></tr>
  <tr><td class="label">Balance Due</td><td class="value num"><?php echo esc_html(number_format(floatval($payload['balance_due'] ?? 0), 2)); ?> <?php echo $currency; ?></td></tr>
  <?php endif; ?>
</table>

<?php if (!empty($seller['payment_instructions'])) : ?>
<div class="footer"><strong>Payment Instructions</strong><br><?php echo nl2br(esc_html($seller['payment_instructions'])); ?></div>
<?php endif; ?>

<?php if (!empty($seller['invoice_terms'])) : ?>
<div class="footer"><strong>Terms</strong><br><?php echo nl2br(esc_html($seller['invoice_terms'])); ?></div>
<?php endif; ?>
</body>
</html>
        <?php
        return trim(ob_get_clean());
    }

    private static function get_table_column_names($table) {
        global $wpdb;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return [];
        }
        return $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0) ?: [];
    }

    private static function get_schema_flag($key) {
        return get_option($key, '');
    }

    private static function set_schema_flag($key, $value) {
        update_option($key, $value, false);
    }

    private function require_access($user_id, $org_id, $capability = 'view_invoices') {
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }
        $isolation = OraBooks_Auth::require_customer_org($user_id, $org_id);
        if (is_wp_error($isolation)) {
            orabooks_json_error($isolation->get_error_message(), 403);
        }
        if (!current_user_can('manage_options') && !OraBooks_RBAC::require_permission($user_id, $org_id, $capability)) {
            orabooks_json_error('Permission denied', 403);
        }
    }

    public function ajax_invoice_rendered_get() {
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $invoice_id = intval($_GET['invoice_id'] ?? 0);
        $org_id = intval($_GET['org_id'] ?? 0);
        if (!$invoice_id) {
            orabooks_json_error('Invoice ID required', 400);
        }

        $invoice = OraBooks_Customers::get_invoice($invoice_id);
        if (!$invoice) {
            orabooks_json_error('Invoice not found', 404);
        }

        if (!$org_id) {
            $org_id = intval($invoice->org_id);
        }

        $this->require_access($user_id, $org_id, 'view_invoices');

        $rendered = self::get_rendered_copy($invoice_id);
        if (!$rendered && in_array($invoice->workflow_status, ['posted'], true)) {
            self::snapshot_invoice_parties($invoice_id, $org_id);
            self::save_rendered_copy($invoice_id, $org_id);
            $rendered = self::get_rendered_copy($invoice_id);
        }

        if (!$rendered) {
            orabooks_json_error('Rendered invoice not available until invoice is posted.', 404);
        }

        orabooks_json_success([
            'rendered_html' => $rendered->rendered_html,
            'rendered_json' => json_decode((string) ($rendered->rendered_json ?? ''), true),
            'rendered_at' => $rendered->rendered_at,
        ]);
    }

    public function ajax_document_config_get() {
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }
        $org_id = intval($_GET['org_id'] ?? 0);
        if (!$org_id) {
            orabooks_json_error('Organization ID required', 400);
        }
        $this->require_access($user_id, $org_id, 'view_reports');
        orabooks_json_success(['config' => self::get_document_config($org_id)]);
    }

    public function ajax_document_config_set() {
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }
        $org_id = intval($_POST['org_id'] ?? 0);
        if (!$org_id) {
            orabooks_json_error('Organization ID required', 400);
        }
        $this->require_access($user_id, $org_id, 'manage_org_settings');
        $config = self::save_document_config($org_id, $_POST);
        if (is_wp_error($config)) {
            orabooks_json_error($config->get_error_message(), 400);
        }
        orabooks_json_success(['config' => self::get_document_config($org_id)], 'Invoice document settings saved');
    }
}
