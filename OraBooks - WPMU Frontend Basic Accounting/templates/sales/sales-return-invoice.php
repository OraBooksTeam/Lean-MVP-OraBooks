<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    echo '<div class="p-4 bg-red-100 text-red-700">Invalid ID.</div>';
    return;
}

$return = $wpdb->get_row($wpdb->prepare("
    SELECT sr.*, c.customer_name, c.mobile as customer_mobile, c.email as customer_email, c.address as customer_address
    FROM {$wpdb->prefix}orabooks_db_salesreturn sr
    LEFT JOIN {$wpdb->prefix}orabooks_db_customers c ON sr.customer_id = c.id
    WHERE sr.id = %d
", $id));

if (!$return) {
    echo '<div class="p-4 bg-red-100 text-red-700">Return not found.</div>';
    return;
}

$items = $wpdb->get_results($wpdb->prepare("
    SELECT si.*, i.item_name, i.item_code 
    FROM {$wpdb->prefix}orabooks_db_salesitemsreturn si
    LEFT JOIN {$wpdb->prefix}orabooks_db_items i ON si.item_id = i.id
    WHERE si.return_id = %d
", $id));

$store = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}orabooks_db_store LIMIT 1");
$currency = '৳';
?>

<div class="max-w-4xl mx-auto py-8">
    <div class="flex justify-between items-center mb-6 no-print">
        <a href="?view=sales-return-list" class="text-gray-500 hover:text-indigo-600 transition-colors flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i> Back to List
        </a>
        <div class="flex gap-2">
             <button onclick="window.print()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-all font-bold shadow-md">
                <i class="fa-solid fa-print mr-1"></i> Print
            </button>
             <button id="export-pdf" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all font-bold shadow-md">
                <i class="fa-solid fa-file-pdf mr-1"></i> PDF
            </button>
        </div>
    </div>

    <div id="invoice-card" class="bg-white shadow-2xl rounded-2xl overflow-hidden border border-gray-100">
        <div class="h-2 bg-indigo-600"></div>
        <div class="p-8 md:p-12">
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between gap-8 mb-12">
                <div>
                    <h2 class="text-3xl font-black text-indigo-600 mb-4 uppercase tracking-tighter">Sales Return Invoice</h2>
                    <div class="space-y-1 text-sm">
                        <p class="text-gray-500">Return No: <span class="text-gray-900 font-bold"><?php echo esc_html($return->return_code); ?></span></p>
                        <p class="text-gray-500">Date: <span class="text-gray-900 font-bold"><?php echo date('M d, Y', strtotime($return->return_date)); ?></span></p>
                        <p class="text-gray-500">Status: <span class="text-indigo-600 font-bold"><?php echo esc_html($return->return_status); ?></span></p>
                    </div>
                </div>
                <div class="text-right">
                    <?php if (!empty($store->logo)): ?>
                        <img src="<?php echo esc_url($store->logo); ?>" alt="Logo" class="h-12 w-auto ml-auto mb-4">
                    <?php endif; ?>
                    <div class="text-xl font-black text-gray-900"><?php echo esc_html($store->store_name ?? 'Your Company'); ?></div>
                    <div class="text-gray-500 text-sm mt-2">
                        <p><?php echo esc_html($store->address); ?></p>
                        <p><?php echo esc_html($store->mobile); ?></p>
                        <p><?php echo esc_html($store->email); ?></p>
                    </div>
                </div>
            </div>

            <!-- Addresses -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 mb-12">
                <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Customer Details</h3>
                    <div class="text-lg font-black text-gray-900 mb-2"><?php echo esc_html($return->customer_name); ?></div>
                    <div class="text-sm text-gray-600 space-y-1">
                        <p class="flex items-center gap-2"><i class="fa-solid fa-mobile-screen text-xs opacity-50"></i> <?php echo esc_html($return->customer_mobile); ?></p>
                        <?php if($return->customer_email): ?>
                            <p class="flex items-center gap-2"><i class="fa-solid fa-envelope text-xs opacity-50"></i> <?php echo esc_html($return->customer_email); ?></p>
                        <?php endif; ?>
                        <p class="flex items-start gap-2"><i class="fa-solid fa-location-dot text-xs opacity-50 mt-1"></i> <?php echo nl2br(esc_html($return->customer_address)); ?></p>
                    </div>
                </div>
                <div class="flex flex-col justify-center text-right">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Financial Summary</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between text-gray-500">
                            <span>Subtotal:</span>
                            <span class="font-bold text-gray-900"><?php echo $currency . ' ' . number_format($return->subtotal, 2); ?></span>
                        </div>
                        <div class="flex justify-between text-gray-500">
                            <span>Discount:</span>
                            <span class="font-bold text-gray-900">- <?php echo $currency . ' ' . number_format($return->tot_discount_to_all_amt, 2); ?></span>
                        </div>
                        <div class="flex justify-between items-end pt-4 border-t border-gray-100">
                            <span class="text-sm font-bold text-gray-400 uppercase">Return Amount:</span>
                            <span class="text-3xl font-black text-indigo-600"><?php echo $currency . ' ' . number_format($return->grand_total, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="mb-12 overflow-hidden rounded-xl border border-gray-200">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="p-4 text-xs font-bold uppercase tracking-widest">Item Description</th>
                            <th class="p-4 text-xs font-bold uppercase tracking-widest text-center">Returned Qty</th>
                            <th class="p-4 text-xs font-bold uppercase tracking-widest text-right">Unit Price</th>
                            <th class="p-4 text-xs font-bold uppercase tracking-widest text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach($items as $idx => $item): ?>
                            <tr>
                                <td class="p-4">
                                    <div class="font-bold text-gray-900"><?php echo esc_html($item->item_name); ?></div>
                                    <div class="text-[10px] text-gray-400 font-mono"><?php echo esc_html($item->item_code); ?></div>
                                </td>
                                <td class="p-4 text-center font-medium"><?php echo number_format($item->sales_qty, 2); ?></td>
                                <td class="p-4 text-right"><?php echo number_format($item->price_per_unit, 2); ?></td>
                                <td class="p-4 text-right font-black text-gray-900"><?php echo number_format($item->total_cost, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Notes and Refund -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                <div>
                    <?php if($return->return_note): ?>
                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Notes</h4>
                        <div class="p-4 bg-gray-50 rounded-xl text-sm text-gray-600 italic border border-gray-100 italic">
                            "<?php echo esc_html($return->return_note); ?>"
                        </div>
                    <?php endif; ?>
                </div>
                <div class="bg-indigo-900 text-white rounded-2xl p-6 shadow-xl">
                    <h3 class="text-xs font-bold text-indigo-400 uppercase tracking-widest mb-4">Refund Information</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between text-sm opacity-60">
                            <span>Refunded Amount:</span>
                            <span class="font-bold"><?php echo $currency . ' ' . number_format($return->paid_amount, 2); ?></span>
                        </div>
                        <div class="flex justify-between text-sm opacity-60">
                            <span>Refund Status:</span>
                            <span class="font-bold"><?php echo esc_html($return->payment_status); ?></span>
                        </div>
                        <div class="pt-4 border-t border-white/10 flex justify-between items-end">
                            <span class="text-xs font-bold uppercase opacity-60">Remaining Credit:</span>
                            <span class="text-xl font-black text-emerald-400"><?php echo $currency . ' ' . number_format(max(0, $return->grand_total - $return->paid_amount), 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-16 pt-8 border-t border-gray-100 text-center">
                <p class="text-gray-400 text-sm italic">This is a system generated document.</p>
                <div class="flex justify-center gap-4 mt-4 text-[10px] text-gray-300 uppercase tracking-[0.2em] font-bold">
                    <span>Authorized Return</span>
                    <span>•</span>
                    <span>Inventory Management</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body { background: white !important; }
    nav, aside, header, .no-print { display: none !important; }
    main { padding: 0 !important; margin: 0 !important; }
    #invoice-card { box-shadow: none !important; border: none !important; }
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#export-pdf').on('click', function() {
        const { jsPDF } = window.jspdf;
        const element = document.getElementById('invoice-card');
        html2canvas(element, { scale: 2 }).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const pdf = new jsPDF('p', 'mm', 'a4');
            const width = pdf.internal.pageSize.getWidth();
            const height = (canvas.height * width) / canvas.width;
            pdf.addImage(imgData, 'PNG', 0, 0, width, height);
            pdf.save('SalesReturn_<?php echo esc_js($return->return_code); ?>.pdf');
        });
    });
});
</script>
