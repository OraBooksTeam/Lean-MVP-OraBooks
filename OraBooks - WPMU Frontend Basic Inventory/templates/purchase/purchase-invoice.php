<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Table names
$purchase_table     = $wpdb->prefix . 'orabooks_db_purchase';
$purchase_items_table = $wpdb->prefix . 'orabooks_db_purchaseitems';
$suppliers_table    = $wpdb->prefix . 'orabooks_db_suppliers';
$payments_table     = $wpdb->prefix . 'orabooks_db_purchasepayments';
$accounts_table     = $wpdb->prefix . 'orabooks_ac_accounts';
$paymenttypes_table = $wpdb->prefix . 'orabooks_db_paymenttypes';
$store_table        = $wpdb->prefix . 'orabooks_db_store';
$items_master_table = $wpdb->prefix . 'orabooks_db_items';

// Get ID
$purchase_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$purchase_id) {
    echo '<div class="bg-red-50 border-l-4 border-red-400 p-4 text-red-700">No purchase specified.</div>';
    return;
}

// Fetch Purchase
$purchase = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$purchase_table} WHERE id = %d LIMIT 1", $purchase_id));

if (!$purchase) {
    echo '<div class="bg-red-50 border-l-4 border-red-400 p-4 text-red-700">Purchase not found.</div>';
    return;
}

// Fetch Items
$items = $wpdb->get_results($wpdb->prepare("
    SELECT pi.*, im.item_name, im.item_code 
    FROM {$purchase_items_table} pi
    LEFT JOIN {$items_master_table} im ON pi.item_id = im.id
    WHERE pi.purchase_id = %d 
    ORDER BY pi.id ASC
", $purchase_id));

// Fetch Supplier
$supplier = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$suppliers_table} WHERE id = %d", $purchase->supplier_id));

// Fetch Store/Company Info
$store = $wpdb->get_row("SELECT * FROM {$store_table} LIMIT 1");

// Fetch Payments
$payments = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$payments_table} WHERE purchase_id = %d ORDER BY id ASC", $purchase_id));

// Currency Symbol
$currency = '৳';

// Helper for formatting
$fmt = function($v) use ($currency) { return $currency . ' ' . number_format((float)$v, 2); };
?>

<div class="max-w-4xl mx-auto">
    <!-- Action Bar -->
    <div class="flex justify-between items-center mb-6 no-print">
        <div class="flex items-center">
            <a href="?view=view-purchase" class="mr-4 text-gray-500 hover:text-gray-700 transition-colors">
                <i class="fa-solid fa-arrow-left text-xl"></i>
            </a>
            <h1 class="text-2xl font-bold text-gray-800">Purchase Invoice</h1>
        </div>
        <div class="flex gap-3">
            <button onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-all font-medium shadow-sm active:scale-95">
                <i class="fa-solid fa-print mr-2"></i> Print
            </button>
            <button id="export-pdf" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-all font-medium shadow-sm active:scale-95">
                <i class="fa-solid fa-file-pdf mr-2"></i> PDF
            </button>
        </div>
    </div>

    <!-- Invoice Content -->
    <div id="invoice-render-area" class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100 mb-10">
        <!-- Top Gradient Bar -->
        <div class="h-2 bg-gradient-to-r from-indigo-600 to-blue-500"></div>
        
        <div class="p-8 md:p-12">
            <!-- Header -->
            <div class="flex justify-between gap-8 mb-2">
                <div>
                    <h2 class="text-3xl font-black text-indigo-600 mb-2 uppercase tracking-tighter">Purchase Invoice</h2>
                    <p class="text-gray-500 font-medium">Invoice No: <span class="text-gray-900 font-bold"><?php echo esc_html($purchase->purchase_code); ?></span></p>
                    <p class="text-gray-500 font-medium">Date: <span class="text-gray-900 font-bold"><?php echo date('d M, Y', strtotime($purchase->purchase_date)); ?></span></p>
                </div>
                <div class="text-right">
                    <div class="text-xl font-bold text-gray-900 mb-1"><?php echo esc_html($store->store_name ?? get_bloginfo('name')); ?></div>
                    <div class="text-gray-500 text-sm space-y-0.5">
                        <?php if(!empty($store->address)): ?> <p><?php echo esc_html($store->address); ?></p> <?php endif; ?>
                        <?php if(!empty($store->city)): ?> <p><?php echo esc_html($store->city . ', ' . $store->postcode); ?></p> <?php endif; ?>
                        <?php if(!empty($store->mobile)): ?> <p>Mob: <?php echo esc_html($store->mobile); ?></p> <?php endif; ?>
                        <?php if(!empty($store->email)): ?> <p><?php echo esc_html($store->email); ?></p> <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Billing Info -->
            <div class="grid grid-cols-2 gap-8 mb-2">
                <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Supplier Information</h3>
                    <?php if ($supplier): ?>
                        <div class="text-lg font-bold text-gray-900 mb-2"><?php echo esc_html($supplier->supplier_name); ?></div>
                        <div class="text-gray-600 text-sm space-y-1">
                            <?php if(!empty($supplier->mobile)): ?> <p class="flex items-center"><i class="fa-solid fa-phone-flip mr-2 text-xs opacity-50"></i> <?php echo esc_html($supplier->mobile); ?></p> <?php endif; ?>
                            <?php if(!empty($supplier->email)): ?> <p class="flex items-center"><i class="fa-solid fa-envelope mr-2 text-xs opacity-50"></i> <?php echo esc_html($supplier->email); ?></p> <?php endif; ?>
                            <?php if(!empty($supplier->address)): ?> <p class="flex items-start"><i class="fa-solid fa-location-dot mr-2 text-xs mt-1 opacity-50"></i> <?php echo nl2br(esc_html($supplier->address)); ?></p> <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-400 italic">No supplier info</p>
                    <?php endif; ?>
                </div>
                <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100 flex flex-col justify-center">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Status & Reference</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-500">Purchase Status:</span>
                            <?php 
                            $status_class = 'bg-gray-200 text-gray-800';
                            if($purchase->purchase_status == 'Received') $status_class = 'bg-emerald-100 text-emerald-800';
                            if($purchase->purchase_status == 'Pending')  $status_class = 'bg-amber-100 text-amber-800';
                            ?>
                            <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $status_class; ?>"><?php echo esc_html($purchase->purchase_status); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-500">Payment Status:</span>
                             <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo ($purchase->payment_status == 'Paid') ? 'bg-indigo-100 text-indigo-800' : 'bg-rose-100 text-rose-800'; ?>">
                                <?php echo esc_html($purchase->payment_status); ?>
                            </span>
                        </div>
                        <?php if($purchase->reference_no): ?>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-500">Ref No:</span>
                            <span class="text-sm font-bold text-gray-900"><?php echo esc_html($purchase->reference_no); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="mb-4 overflow-x-auto rounded-xl border border-gray-200">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-900 text-white">
                            <th class="p-4 text-xs font-bold uppercase tracking-wider">#</th>
                            <th class="p-4 text-xs font-bold uppercase tracking-wider">Description</th>
                            <th class="p-4 text-xs font-bold uppercase tracking-wider text-right">Qty</th>
                            <th class="p-4 text-xs font-bold uppercase tracking-wider text-right">Unit Price</th>
                            <th class="p-4 text-xs font-bold uppercase tracking-wider text-right">Discount</th>
                            <th class="p-4 text-xs font-bold uppercase tracking-wider text-right">Tax</th>
                            <th class="p-4 text-xs font-bold uppercase tracking-wider text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($items as $idx => $item): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="p-4 text-sm text-gray-500"><?php echo $idx + 1; ?></td>
                                <td class="p-4">
                                    <div class="font-bold text-gray-900 text-sm"><?php echo esc_html($item->item_name); ?></div>
                                    <div class="text-xs text-gray-400">SKU: <?php echo esc_html($item->item_code); ?></div>
                                </td>
                                <td class="p-4 text-sm text-right font-medium"><?php echo number_format($item->purchase_qty, 2); ?></td>
                                <td class="p-4 text-sm text-right"><?php echo number_format($item->price_per_unit, 2); ?></td>
                                <td class="p-4 text-sm text-right text-gray-500"><?php echo number_format($item->discount_amt, 2); ?></td>
                                <td class="p-4 text-sm text-right text-gray-500"><?php echo number_format($item->tax_amt, 2); ?></td>
                                <td class="p-4 text-sm text-right font-bold text-gray-900"><?php echo number_format($item->total_cost, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Summary -->
            <div class="grid grid-cols-2 gap-8">
                <div>
                    <?php if(!empty($purchase->purchase_note)): ?>
                    <div class="mb-8 p-4 bg-blue-50 rounded-xl border border-blue-100">
                        <h4 class="text-xs font-bold text-blue-600 uppercase tracking-widest mb-2 flex items-center">
                            <i class="fa-solid fa-circle-info mr-2"></i> Purchase Note
                        </h4>
                        <p class="text-sm text-blue-800 leading-relaxed"><?php echo nl2br(esc_html($purchase->purchase_note)); ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($payments)): ?>
                    <div>
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Payment History</h3>
                        <div class="space-y-2">
                            <?php foreach($payments as $p): ?>
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg border border-gray-100">
                                <div>
                                    <div class="text-xs font-bold text-gray-900"><?php echo date('d-m-Y', strtotime($p->payment_date)); ?></div>
                                    <div class="text-[10px] text-gray-500 uppercase"><?php echo esc_html($p->payment_type); ?></div>
                                </div>
                                <div class="text-sm font-black text-gray-900"><?php echo $fmt($p->payment); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div>
                    <div class="bg-gray-900 rounded-2xl p-6 text-white shadow-lg space-y-4">
                        <div class="flex justify-between items-center text-sm opacity-60">
                            <span>Subtotal</span>
                            <span class="font-bold"><?php echo $fmt($purchase->subtotal); ?></span>
                        </div>
                        <?php 
                        $total_tax = 0;
                        foreach ($items as $item) {
                            $total_tax += floatval($item->tax_amt);
                        }
                        // Include tax from other charges
                        $other_charges_tax = floatval($purchase->other_charges_amt) - floatval($purchase->other_charges_input);
                        $total_tax += $other_charges_tax;

                        if ($total_tax > 0): 
                        ?>
                        <div class="flex justify-between items-center text-sm opacity-60">
                            <span>Tax Payable</span>
                            <span class="font-bold">+ <?php echo $fmt($total_tax); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($purchase->other_charges_amt > 0): ?>
                        <div class="flex justify-between items-center text-sm opacity-60">
                            <span>Other Charges</span>
                            <span class="font-bold"><?php echo $fmt($purchase->other_charges_input); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($purchase->tot_discount_to_all_amt > 0): ?>
                        <div class="flex justify-between items-center text-sm text-rose-400">
                            <span>Total Discount <?php echo ($purchase->discount_to_all_type === 'Percentage' && $purchase->discount_to_all_input > 0) ? '(' . floatval($purchase->discount_to_all_input) . '%)' : ''; ?></span>
                            <span class="font-bold">- <?php echo $fmt($purchase->tot_discount_to_all_amt); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($purchase->round_off != 0): ?>
                        <div class="flex justify-between items-center text-sm opacity-60">
                            <span>Round Off</span>
                            <span class="font-bold"><?php echo ($purchase->round_off > 0 ? '+' : '') . number_format($purchase->round_off, 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="pt-4 border-t border-white/10 flex justify-between items-end">
                            <span class="text-sm font-bold uppercase tracking-widest opacity-60">Grand Total</span>
                            <span class="text-3xl font-black text-emerald-400"><?php echo $fmt($purchase->grand_total); ?></span>
                        </div>
                        <div class="flex justify-between items-center pt-2 text-sm opacity-60">
                            <span>Paid Amount</span>
                            <span class="font-bold"><?php echo $fmt($purchase->paid_amount); ?></span>
                        </div>
                        <?php $due = $purchase->grand_total - $purchase->paid_amount; ?>
                        <div class="flex justify-between items-center pt-2 text-sm <?php echo $due > 0 ? 'text-rose-400' : 'text-emerald-400'; ?>">
                            <span class="font-bold uppercase tracking-widest opacity-60">Balance Due</span>
                            <span class="font-black text-xl"><?php echo $fmt(max(0, $due)); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="pt-8 border-t border-gray-100 text-center">
                <p class="text-gray-400 text-sm italic">Thank you for your business!</p>
                <div class="flex justify-center gap-4 mt-4 text-[10px] text-gray-300 uppercase tracking-[0.2em]">
                    <span>Secure Transaction</span>
                    <span>•</span>
                    <span>Inventory System</span>
                    <span>•</span>
                    <span><?php echo date('Y'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body { background: white !important; overflow: visible !important; }
    nav, aside, .no-print { display: none !important; }
    main { padding: 0 !important; margin: 0 !important; overflow: visible !important; }
    #invoice-render-area { 
        box-shadow: none !important; 
        border: none !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
jQuery(document).ready(function($) {
    $('#export-pdf').on('click', function() {
        const { jsPDF } = window.jspdf;
        const btn = $(this);
        const originalContent = btn.html();
        
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Generating...');

        const element = document.getElementById('invoice-render-area');
        
        html2canvas(element, {
            scale: 2,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff'
        }).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const pdf = new jsPDF('p', 'mm', 'a4');
            const imgWidth = 210;
            const pageHeight = 297;
            const imgHeight = (canvas.height * imgWidth) / canvas.width;
            let heightLeft = imgHeight;
            let position = 0;

            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;

            while (heightLeft >= 0) {
                position = heightLeft - imgHeight;
                pdf.addPage();
                pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
            }

            pdf.save('Invoice_<?php echo esc_js($purchase->purchase_code); ?>.pdf');
            btn.prop('disabled', false).html(originalContent);
        });
    });
});
</script>
