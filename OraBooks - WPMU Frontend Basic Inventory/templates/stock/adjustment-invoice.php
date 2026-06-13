<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Get adjustment ID
$adjustment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$adjustment_id) {
    echo '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4">No adjustment specified. Provide <code>?id=123</code>.</div>';
    return;
}

// Fetch adjustment
$adjustment = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}orabooks_db_stockadjustment WHERE id = %d LIMIT 1",
    $adjustment_id
));

if (!$adjustment) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">Adjustment not found.</div>';
    return;
}

// Fetch warehouse
$warehouse = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}orabooks_db_warehouse WHERE id = %d LIMIT 1",
    $adjustment->warehouse_id
));

// Fetch adjustment items
$items = $wpdb->get_results($wpdb->prepare(
    "SELECT ai.*, i.item_name, i.item_code, i.sku 
    FROM {$wpdb->prefix}orabooks_db_stockadjustmentitems ai
    LEFT JOIN {$wpdb->prefix}orabooks_db_items i ON ai.item_id = i.id
    WHERE ai.adjustment_id = %d
    ORDER BY ai.id ASC",
    $adjustment_id
));

// Fetch company/store
$company = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}orabooks_db_store LIMIT 1");
?>

<style>
    @media print {
        body * { visibility: hidden; }
        #adjustment-print-area, #adjustment-print-area * { visibility: visible; }
        #adjustment-print-area { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 20px; background: white; }
        .no-print { display: none !important; }
    }
    .adjustment-container { background: #fff; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
    .adjustment-table th { background: #f3f4f6; color: #374151; font-weight: 600; text-transform: uppercase; font-size: 12px; padding: 12px 16px; text-align: left; }
    .adjustment-table td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; }
</style>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6 no-print">
        <h1 class="text-2xl font-bold text-gray-800">Stock Adjustment Invoice</h1>
        <div class="flex gap-2">
            <button onclick="window.print()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors shadow-sm">
                <i class="fa-solid fa-print mr-2"></i> Print
            </button>
            <a href="<?php echo esc_url(add_query_arg('view', 'adjustment-list')); ?>" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors shadow-sm">
                Back to List
            </a>
        </div>
    </div>

    <div id="adjustment-print-area" class="adjustment-container p-8 border border-gray-100">
        <!-- Header -->
        <div class="flex justify-between pb-6 border-b-2 border-purple-500 mb-8 items-start">
            <div>
                <?php if (!empty($company->logo)): ?>
                    <img src="<?php echo esc_url($company->logo); ?>" alt="Logo" class="h-16 mb-4 object-contain">
                <?php endif; ?>
                <h2 class="text-3xl font-bold text-purple-600">STOCK ADJUSTMENT</h2>
                <p class="text-gray-500 mt-1">Reference: <span class="font-bold text-gray-800"><?php echo esc_html($adjustment->reference_no); ?></span></p>
            </div>
            <div class="text-right">
                <div class="text-gray-600 font-medium">Date: <?php echo date('d-M-Y', strtotime($adjustment->adjustment_date)); ?></div>
                <div class="mt-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase <?php echo $adjustment->status == 1 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                        <?php echo $adjustment->status == 1 ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Info Grid -->
        <div class="grid grid-cols-2 gap-8 mb-8">
            <div class="bg-gray-50 p-6 rounded-lg border-l-4 border-purple-500">
                <h5 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Company</h5>
                <div class="text-lg font-bold text-gray-800"><?php echo esc_html($company->store_name); ?></div>
                <div class="text-gray-600 mt-2 text-sm space-y-1">
                    <p><?php echo esc_html($company->address); ?></p>
                    <p><?php echo esc_html($company->city . ', ' . $company->state . ' ' . $company->postcode); ?></p>
                    <p>Phone: <?php echo esc_html($company->phone ?: $company->mobile); ?></p>
                    <p>Email: <?php echo esc_html($company->email); ?></p>
                </div>
            </div>
            <div class="bg-gray-50 p-6 rounded-lg border-l-4 border-blue-500">
                <h5 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Warehouse</h5>
                <div class="text-lg font-bold text-gray-800"><?php echo esc_html($warehouse->warehouse_name); ?></div>
                <div class="text-gray-600 mt-2 text-sm space-y-1">
                    <p><?php echo esc_html(@$warehouse->address); ?></p>
                    <p>Mobile: <?php echo esc_html($warehouse->mobile); ?></p>
                    <p>Email: <?php echo esc_html($warehouse->email); ?></p>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="overflow-x-auto mb-8">
            <table class="w-full text-sm adjustment-table">
                <thead>
                    <tr>
                        <th class="w-12">#</th>
                        <th>Item Name</th>
                        <th class="text-center">Item Code</th>
                        <th class="text-right">Adjustment Qty</th>
                        <th class="text-center">Type</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700">
                    <?php 
                    $total_addition = 0;
                    $total_subtraction = 0;
                    foreach ($items as $idx => $item): 
                        $qty = floatval($item->adjustment_qty);
                        if ($qty >= 0) {
                            $total_addition += $qty;
                        } else {
                            $total_subtraction += abs($qty);
                        }
                    ?>
                    <tr>
                        <td><?php echo $idx + 1; ?></td>
                        <td>
                            <div class="font-bold text-gray-800"><?php echo esc_html($item->item_name); ?></div>
                        </td>
                        <td class="text-center text-gray-600"><?php echo esc_html($item->item_code ?: $item->sku); ?></td>
                        <td class="text-right font-bold <?php echo $qty >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo number_format(abs($qty), 2); ?>
                        </td>
                        <td class="text-center">
                            <?php if ($qty >= 0): ?>
                                <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-bold">Addition (+)</span>
                            <?php else: ?>
                                <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-bold">Subtraction (-)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-bold">
                        <td colspan="3" class="text-right p-4">Summary:</td>
                        <td class="text-right p-4">
                            <div class="text-green-600">+<?php echo number_format($total_addition, 2); ?></div>
                            <div class="text-red-600">-<?php echo number_format($total_subtraction, 2); ?></div>
                        </td>
                        <td class="text-center p-4">
                            <span class="text-gray-800">Net: <?php echo number_format($total_addition - $total_subtraction, 2); ?></span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Note -->
        <?php if (!empty($adjustment->adjustment_note)): ?>
            <div class="mb-8">
                <h5 class="text-sm font-bold text-gray-800 mb-2 border-b pb-2">Note:</h5>
                <p class="text-sm text-gray-600 bg-purple-50 p-4 rounded"><?php echo esc_html($adjustment->adjustment_note); ?></p>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="mt-16 text-center border-t pt-8 text-gray-400 text-sm">
            <p class="font-bold text-gray-600 uppercase mb-1">Stock Adjustment Record</p>
            <p>This is a computer-generated document. No signature required.</p>
            <div class="mt-4 text-xs">
                Generated by Inventory Management System &bull; <?php echo date('Y-m-d H:i'); ?>
            </div>
        </div>
    </div>
</div>
