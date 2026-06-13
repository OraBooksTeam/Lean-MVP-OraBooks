<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Define tables
$sales_table = $wpdb->prefix . 'orabooks_db_sales';
$sales_items_table = $wpdb->prefix . 'orabooks_db_salesitems';
$customers_table = $wpdb->prefix . 'orabooks_db_customers';
$payments_table = $wpdb->prefix . 'orabooks_db_salespayments';
$accounts_table = $wpdb->prefix . 'orabooks_ac_accounts';
$paymenttypes_table = $wpdb->prefix . 'orabooks_db_paymenttypes';
$currency_table = $wpdb->prefix . 'orabooks_db_currency';
$company_table = $wpdb->prefix . 'orabooks_db_store';

// Get ID
$sales_id = isset($_GET['sales_id']) ? intval($_GET['sales_id']) : 0;
$sales_code = isset($_GET['sales_code']) ? sanitize_text_field($_GET['sales_code']) : '';

if (!$sales_id && empty($sales_code)) {
    echo '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4">No sale specified. Provide <code>?sales_id=123</code> or <code>?sales_code=SL-00001</code>.</div>';
    return;
}

// Fetch sale
if ($sales_id) {
    $sale = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$sales_table} WHERE id = %d LIMIT 1", $sales_id));
} else {
    $sale = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$sales_table} WHERE sales_code = %s LIMIT 1", $sales_code));
    if ($sale) $sales_id = intval($sale->id);
}

if (!$sale) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">Sale not found.</div>';
    return;
}

// Currency symbol
$currency_symbol = '৳';
$currency_row = $wpdb->get_row("SELECT symbol FROM {$currency_table} LIMIT 1");
if ($currency_row && !empty($currency_row->symbol)) {
    $currency_symbol = $currency_row->symbol;
}

// Fetch store (company)
$company = $wpdb->get_row("SELECT * FROM {$company_table} LIMIT 1");

// Fetch customer
$customer = null;
if (!empty($sale->customer_id)) {
    $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$customers_table} WHERE id = %d LIMIT 1", intval($sale->customer_id)));
}

if (!$customer) {
    $customer = (object) [
        'customer_name' => 'Walk-in Customer',
        'address' => '',
        'mobile' => '',
        'email' => ''
    ];
}

// Fetch items
$items = [];
$invoice_terms = $sale->invoice_terms ?? '';
if (!empty($invoice_terms)) {
    $decoded = json_decode(stripslashes($invoice_terms), true);
    if (is_array($decoded)) {
        $items = $decoded;
    }
}

// Fetch payments
$payments = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$payments_table} WHERE sales_id = %d ORDER BY id ASC", $sales_id));

// Fetch maps
$accounts_map = [];
$account_rows = $wpdb->get_results("SELECT id, account_name FROM {$accounts_table}");
if ($account_rows) foreach ($account_rows as $r) $accounts_map[intval($r->id)] = $r->account_name;

$pt_map = [];
$pt_rows = $wpdb->get_results("SELECT id, payment_type FROM {$paymenttypes_table} WHERE status=1");
if ($pt_rows) foreach ($pt_rows as $p) $pt_map[intval($p->id)] = $p->payment_type;

// Calculate total tax amount
$total_tax_amount = 0;
foreach ($items as $item) {
    if (isset($item['tax_amt'])) {
        $total_tax_amount += floatval($item['tax_amt']);
    }
}

// Helpers
$fmt = function($v) use ($currency_symbol) { return $currency_symbol . ' ' . number_format((float)$v, 2); };
?>

<style>
    @media print {
        body * { visibility: hidden; }
        #invoice-print-area, #invoice-print-area * { visibility: visible; }
        #invoice-print-area { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 20px; background: white; }
        .no-print { display: none !important; }
    }
    .invoice-container { background: #fff; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
    .invoice-table th { background: #f3f4f6; color: #374151; font-weight: 600; text-transform: uppercase; font-size: 12px; padding: 12px 16px; text-align: left; }
    .invoice-table td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; }
</style>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6 no-print">
        <h1 class="text-2xl font-bold text-gray-800">Sales Invoice</h1>
        <div class="flex gap-2">
            <button onclick="window.print()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors shadow-sm">
                <i class="fa-solid fa-print mr-2"></i> Print
            </button>
            <a href="?view=view-sales" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors shadow-sm">
                Back to List
            </a>
        </div>
    </div>

    <div id="invoice-print-area" class="invoice-container p-8 border border-gray-100">
        <!-- Header -->
        <div class="flex justify-between pb-6 border-b-2 border-blue-500 mb-8 items-start">
            <div>
                 <?php if (!empty($company->logo)): ?>
                    <img src="<?php echo esc_url($company->logo); ?>" alt="Logo" class="h-16 mb-4 object-contain">
                <?php endif; ?>
                <h2 class="text-3xl font-bold text-blue-600">INVOICE</h2>
                <p class="text-gray-500 mt-1">Invoice #: <span class="font-bold text-gray-800"><?php echo esc_html($sale->sales_code); ?></span></p>
            </div>
            <div class="text-right">
                <div class="text-gray-600 font-medium">Date: <?php echo date('d-M-Y', strtotime($sale->sales_date)); ?></div>
                <div class="mt-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase <?php 
                        $status = strtolower($sale->payment_status);
                        echo ($status === 'paid') ? 'bg-green-100 text-green-700' : (($status === 'partial') ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700');
                    ?>">
                        <?php echo esc_html($sale->payment_status); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Info Grid -->
        <div class="grid grid-cols-2 gap-8 mb-8">
            <div class="bg-gray-50 p-6 rounded-lg border-l-4 border-blue-500">
                <h5 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">From</h5>
                <div class="text-lg font-bold text-gray-800"><?php echo esc_html($company->store_name); ?></div>
                <div class="text-gray-600 mt-2 text-sm space-y-1">
                    <p><?php echo esc_html($company->address); ?></p>
                    <p><?php echo esc_html($company->city . ', ' . $company->state . ' ' . $company->postcode); ?></p>
                    <p>Phone: <?php echo esc_html($company->phone ?: $company->mobile); ?></p>
                    <p>Email: <?php echo esc_html($company->email); ?></p>
                </div>
            </div>
            <div class="bg-gray-50 p-6 rounded-lg border-l-4 border-green-500">
                <h5 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Bill To</h5>
                <div class="text-lg font-bold text-gray-800"><?php echo esc_html($customer->customer_name); ?></div>
                <div class="text-gray-600 mt-2 text-sm space-y-1">
                    <p><?php echo esc_html($customer->address); ?></p>
                    <p>Mobile: <?php echo esc_html($customer->mobile); ?></p>
                    <p>Email: <?php echo esc_html($customer->email); ?></p>
                </div>
            </div>
        </div>

        <!-- Items -->
        <div class="overflow-x-auto mb-8">
            <table class="w-full text-sm invoice-table">
                <thead>
                    <tr>
                        <th class="w-12">#</th>
                        <th>Item Description</th>
                        <th class="text-right">Price</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Tax (%)</th>
                        <th class="text-right">Discount</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700">
                    <?php 
                    $total_qty = 0;
                    foreach ($items as $idx => $item): 
                        $total_qty += floatval($item['qty']);
                    ?>
                    <tr>
                        <td><?php echo $idx + 1; ?></td>
                        <td>
                            <div class="font-bold text-gray-800"><?php echo esc_html($item['name']); ?></div>
                        </td>
                        <td class="text-right"><?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="text-center"><?php echo number_format($item['qty'], 2); ?></td>
                        <td class="text-right"><?php echo number_format($item['tax_percent'] ?? 0, 2); ?>%</td>
                        <td class="text-right"><?php echo number_format($item['discount'], 2); ?></td>
                        <td class="text-right font-bold text-gray-900"><?php echo number_format($item['total'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-bold">
                        <td colspan="3" class="text-right p-4">Summary:</td>
                        <td class="text-center p-4"><?php echo number_format($total_qty, 2); ?></td>
                        <td colspan="2"></td>
                        <td class="text-right p-4"><?php echo number_format($sale->subtotal, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Totals & Payments -->
        <div class="grid grid-cols-2 gap-8">
            <!-- Payment History -->
            <div>
                <h5 class="text-sm font-bold text-gray-800 mb-4 border-b pb-2">Payment History</h5>
                <?php if ($payments): ?>
                    <table class="w-full text-xs border border-gray-100">
                        <tr class="bg-gray-50">
                            <th class="p-2 text-left border-b">Date</th>
                            <th class="p-2 text-left border-b">Type/Account</th>
                            <th class="p-2 text-right border-b">Amount</th>
                        </tr>
                        <?php foreach ($payments as $p): ?>
                        <tr>
                            <td class="p-2 border-b"><?php echo date('d-m-y', strtotime($p->payment_date)); ?></td>
                            <td class="p-2 border-b">
                                <?php echo esc_html($pt_map[intval($p->payment_type)] ?? ''); ?> / 
                                <?php echo esc_html($accounts_map[intval($p->account_id)] ?? ''); ?>
                            </td>
                            <td class="p-2 border-b text-right font-bold"><?php echo $fmt($p->payment); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p class="text-gray-400 italic text-sm">No payments recorded yet.</p>
                <?php endif; ?>

                <?php if (!empty($sale->sales_note)): ?>
                    <div class="mt-6">
                        <h5 class="text-sm font-bold text-gray-800 mb-2">Note:</h5>
                        <p class="text-sm text-gray-600 bg-blue-50 p-3 rounded"><?php echo esc_html($sale->sales_note); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Totals -->
            <div>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="font-bold"><?php echo $fmt($sale->subtotal); ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Other Charges</span>
                        <span class="font-bold"><?php echo $fmt($sale->other_charges_amt); ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Tax Amount</span>
                        <span class="font-bold"><?php echo $fmt($total_tax_amount); ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 text-red-500">Discount on All <?php echo ($sale->discount_to_all_type === 'Percentage' && $sale->discount_to_all_input > 0) ? '(' . floatval($sale->discount_to_all_input) . '%)' : ''; ?></span>
                        <span class="font-bold text-red-500">- <?php echo $fmt($sale->tot_discount_to_all_amt); ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Round Off</span>
                        <span class="font-bold"><?php echo $fmt($sale->round_off); ?></span>
                    </div>
                    <div class="flex justify-between text-xl border-t-2 border-gray-200 pt-4 mt-4">
                        <span class="font-bold text-gray-800">Grand Total</span>
                        <span class="font-bold text-green-600"><?php echo $fmt($sale->grand_total); ?></span>
                    </div>
                    <div class="flex justify-between text-sm pt-2">
                        <span class="text-gray-600">Total Paid</span>
                        <span class="font-bold text-blue-600"><?php echo $fmt($sale->paid_amount); ?></span>
                    </div>
                    <div class="flex justify-between text-lg border-t pt-2 mt-2">
                        <span class="font-bold text-gray-800">Balance Due</span>
                        <span class="font-bold text-red-600">
                            <?php echo $fmt(max(0, $sale->grand_total - $sale->paid_amount)); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-16 text-center border-t pt-8 text-gray-400 text-sm">
            <p class="font-bold text-gray-600 uppercase mb-1">Thank you for your business!</p>
            <p>This is a computer-generated invoice. No signature required.</p>
            <div class="mt-4 text-xs">
                Generated by Inventory Management System &bull; <?php echo date('Y-m-d H:i'); ?>
            </div>
        </div>
    </div>
</div>

