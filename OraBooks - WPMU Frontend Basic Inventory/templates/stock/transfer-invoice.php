<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Get transfer ID
$transfer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$transfer_id) {
    echo '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4">No transfer specified. Provide <code>?id=123</code>.</div>';
    return;
}

// Fetch transfer
$transfer = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}orabooks_db_stocktransfer WHERE id = %d LIMIT 1",
    $transfer_id
));

if (!$transfer) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">Transfer not found.</div>';
    return;
}

// Fetch warehouses
$warehouse_from = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}orabooks_db_warehouse WHERE id = %d LIMIT 1",
    $transfer->warehouse_from
));

$warehouse_to = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}orabooks_db_warehouse WHERE id = %d LIMIT 1",
    $transfer->warehouse_to
));

// Fetch transfer items
$items = $wpdb->get_results($wpdb->prepare(
    "SELECT ti.*, i.item_name, i.item_code, i.sku 
    FROM {$wpdb->prefix}orabooks_db_stocktransferitems ti
    LEFT JOIN {$wpdb->prefix}orabooks_db_items i ON ti.item_id = i.id
    WHERE ti.stocktransfer_id = %d
    ORDER BY ti.id ASC",
    $transfer_id
));

// Fetch company/store
$company = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}orabooks_db_store LIMIT 1");
?>

<style>
    @media print {
        body * { visibility: hidden; }
        #transfer-print-area, #transfer-print-area * { visibility: visible; }
        #transfer-print-area { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 20px; background: white; }
        .no-print { display: none !important; }
    }
    .transfer-container { background: #fff; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
    .transfer-table th { background: #f3f4f6; color: #374151; font-weight: 600; text-transform: uppercase; font-size: 12px; padding: 12px 16px; text-align: left; }
    .transfer-table td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; }
</style>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6 no-print">
        <h1 class="text-2xl font-bold text-gray-800">Stock Transfer Invoice</h1>
        <div class="flex gap-2">
            <button onclick="window.print()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors shadow-sm">
                <i class="fa-solid fa-print mr-2"></i> Print
            </button>
            <a href="<?php echo esc_url(add_query_arg('view', 'transfer-list')); ?>" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors shadow-sm">
                Back to List
            </a>
        </div>
    </div>

    <div id="transfer-print-area" class="transfer-container p-8 border border-gray-100">
        <!-- Header -->
        <div class="flex justify-between pb-6 border-b-2 border-orange-500 mb-8 items-start">
            <div>
                <?php if (!empty($company->logo)): ?>
                    <img src="<?php echo esc_url($company->logo); ?>" alt="Logo" class="h-16 mb-4 object-contain">
                <?php endif; ?>
                <h2 class="text-3xl font-bold text-orange-600">STOCK TRANSFER</h2>
                <p class="text-gray-500 mt-1">Transfer ID: <span class="font-bold text-gray-800">#<?php echo $transfer_id; ?></span></p>
            </div>
            <div class="text-right">
                <div class="text-gray-600 font-medium">Date: <?php echo date('d-M-Y', strtotime($transfer->transfer_date)); ?></div>
                <div class="mt-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase <?php echo $transfer->status == 1 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                        <?php echo $transfer->status == 1 ? 'Completed' : 'Cancelled'; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Info Grid -->
        <div class="grid grid-cols-2 gap-8 mb-8">
            <div class="bg-red-50 p-6 rounded-lg border-l-4 border-red-500">
                <h5 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">From Warehouse</h5>
                <div class="text-lg font-bold text-gray-800"><?php echo esc_html($warehouse_from->warehouse_name); ?></div>
                <div class="text-gray-600 mt-2 text-sm space-y-1">
                    <p><?php echo esc_html(@$warehouse_from->address); ?></p>
                    <p>Mobile: <?php echo esc_html($warehouse_from->mobile); ?></p>
                    <p>Email: <?php echo esc_html($warehouse_from->email); ?></p>
                </div>
            </div>
            <div class="bg-green-50 p-6 rounded-lg border-l-4 border-green-500">
                <h5 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">To Warehouse</h5>
                <div class="text-lg font-bold text-gray-800"><?php echo esc_html($warehouse_to->warehouse_name); ?></div>
                <div class="text-gray-600 mt-2 text-sm space-y-1">
                    <p><?php echo esc_html(@$warehouse_to->address); ?></p>
                    <p>Mobile: <?php echo esc_html($warehouse_to->mobile); ?></p>
                    <p>Email: <?php echo esc_html($warehouse_to->email); ?></p>
                </div>
            </div>
        </div>

        <!-- Company Info -->
        <div class="bg-gray-50 p-6 rounded-lg border-l-4 border-blue-500 mb-8">
            <h5 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Company</h5>
            <div class="text-lg font-bold text-gray-800"><?php echo esc_html($company->store_name); ?></div>
            <div class="text-gray-600 mt-2 text-sm space-y-1">
                <p><?php echo esc_html($company->address); ?></p>
                <p><?php echo esc_html($company->city . ', ' . $company->state . ' ' . $company->postcode); ?></p>
                <p>Phone: <?php echo esc_html($company->phone ?: $company->mobile); ?></p>
                <p>Email: <?php echo esc_html($company->email); ?></p>
            </div>
        </div>

        <!-- Items Table -->
        <div class="overflow-x-auto mb-8">
            <table class="w-full text-sm transfer-table">
                <thead>
                    <tr>
                        <th class="w-12">#</th>
                        <th>Item Name</th>
                        <th class="text-center">Item Code</th>
                        <th class="text-right">Transfer Qty</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700">
                    <?php 
                    $total_qty = 0;
                    foreach ($items as $idx => $item): 
                        $qty = floatval($item->transfer_qty);
                        $total_qty += $qty;
                    ?>
                    <tr>
                        <td><?php echo $idx + 1; ?></td>
                        <td>
                            <div class="font-bold text-gray-800"><?php echo esc_html($item->item_name); ?></div>
                        </td>
                        <td class="text-center text-gray-600"><?php echo esc_html($item->item_code ?: $item->sku); ?></td>
                        <td class="text-right font-bold text-orange-600">
                            <?php echo number_format($qty, 2); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-bold">
                        <td colspan="3" class="text-right p-4">Total Transfer Quantity:</td>
                        <td class="text-right p-4 text-orange-600">
                            <?php echo number_format($total_qty, 2); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Note -->
        <?php if (!empty($transfer->note)): ?>
            <div class="mb-8">
                <h5 class="text-sm font-bold text-gray-800 mb-2 border-b pb-2">Note:</h5>
                <p class="text-sm text-gray-600 bg-orange-50 p-4 rounded"><?php echo esc_html($transfer->note); ?></p>
            </div>
        <?php endif; ?>

        <!-- Transfer Flow Diagram -->
        <div class="mb-8 bg-gradient-to-r from-red-50 via-yellow-50 to-green-50 p-6 rounded-lg border border-gray-200">
            <h5 class="text-sm font-bold text-gray-800 mb-4 text-center">Transfer Flow</h5>
            <div class="flex items-center justify-center gap-4">
                <div class="text-center">
                    <div class="bg-red-500 text-white rounded-full w-16 h-16 flex items-center justify-center font-bold text-2xl mb-2">
                        <i class="fa-solid fa-warehouse"></i>
                    </div>
                    <div class="text-xs font-bold text-gray-700"><?php echo esc_html($warehouse_from->warehouse_name); ?></div>
                    <div class="text-xs text-red-600 font-bold mt-1">- <?php echo number_format($total_qty, 2); ?></div>
                </div>
                
                <div class="flex-1 flex items-center justify-center">
                    <div class="border-t-4 border-dashed border-orange-400 w-full relative">
                        <div class="absolute -top-3 left-1/2 transform -translate-x-1/2 bg-white px-2">
                            <i class="fa-solid fa-arrow-right text-orange-500 text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="text-center">
                    <div class="bg-green-500 text-white rounded-full w-16 h-16 flex items-center justify-center font-bold text-2xl mb-2">
                        <i class="fa-solid fa-warehouse"></i>
                    </div>
                    <div class="text-xs font-bold text-gray-700"><?php echo esc_html($warehouse_to->warehouse_name); ?></div>
                    <div class="text-xs text-green-600 font-bold mt-1">+ <?php echo number_format($total_qty, 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-16 text-center border-t pt-8 text-gray-400 text-sm">
            <p class="font-bold text-gray-600 uppercase mb-1">Stock Transfer Record</p>
            <p>This is a computer-generated document. No signature required.</p>
            <div class="mt-4 text-xs">
                Generated by Inventory Management System &bull; <?php echo date('Y-m-d H:i'); ?>
            </div>
        </div>
    </div>
</div>
