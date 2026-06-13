<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Get transfer ID
$transfer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$transfer_id) {
    echo '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4">No transfer specified.</div>';
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

// Fetch transfer items
$items = $wpdb->get_results($wpdb->prepare(
    "SELECT ti.*, i.item_name, i.item_code, i.sku,
    COALESCE(wi.available_qty, 0) as current_stock
    FROM {$wpdb->prefix}orabooks_db_stocktransferitems ti
    LEFT JOIN {$wpdb->prefix}orabooks_db_items i ON ti.item_id = i.id
    LEFT JOIN {$wpdb->prefix}orabooks_db_warehouseitems wi ON wi.item_id = i.id AND wi.warehouse_id = %d
    WHERE ti.stocktransfer_id = %d
    ORDER BY ti.id ASC",
    $transfer->warehouse_from,
    $transfer_id
));

// Get warehouses
$warehouses = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}orabooks_db_warehouse WHERE status = 1");

$search_nonce = wp_create_nonce('search_stock_items');
$update_nonce = wp_create_nonce('update_transfer');
?>
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">
            <i class="fa-solid fa-edit text-orange-600 mr-2"></i>Edit Stock Transfer
        </h1>
        <a href="<?php echo esc_url(add_query_arg('view', 'transfer-list')); ?>" class="text-gray-600 hover:text-gray-900 font-medium transition-colors duration-200">
            <i class="fa-solid fa-arrow-left mr-1"></i> Back to List
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200">
        <div class="p-6">
            <form id="transfer-form" class="space-y-6">
                <input type="hidden" name="transfer_id" value="<?php echo $transfer_id; ?>">
                
                <!-- Top Row -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="transfer_date" class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                        <input type="date" name="transfer_date" id="transfer_date" value="<?php echo esc_attr($transfer->transfer_date); ?>" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
                    </div>
                    <div>
                        <label for="warehouse_from" class="block text-sm font-medium text-gray-700 mb-1">From Warehouse *</label>
                        <select name="warehouse_from" id="warehouse_from" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
                            <?php foreach ($warehouses as $w): ?>
                                <option value="<?php echo $w->id; ?>" <?php selected($transfer->warehouse_from, $w->id); ?>>
                                    <?php echo esc_html($w->warehouse_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="warehouse_to" class="block text-sm font-medium text-gray-700 mb-1">To Warehouse *</label>
                        <select name="warehouse_to" id="warehouse_to" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
                            <?php foreach ($warehouses as $w): ?>
                                <option value="<?php echo $w->id; ?>" <?php selected($transfer->warehouse_to, $w->id); ?>>
                                    <?php echo esc_html($w->warehouse_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Search -->
                <div>
                     <label for="item-autocomplete" class="block text-sm font-medium text-gray-700 mb-1">Search Item (from Source Warehouse)</label>
                     <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                             <i class="fa-solid fa-magnifying-glass text-gray-400"></i>
                        </div>
                        <input type="text" id="item-autocomplete" class="w-full pl-10 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-white" placeholder="Search item by name / code...">
                     </div>
                     <p class="mt-1 text-xs text-red-500 hidden" id="warehouse-error">Please select 'From Warehouse' first.</p>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto border rounded-md border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-12">#</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Item Name</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider w-40">Available Qty</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider w-40">Transfer Qty</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider w-24">Action</th>
                            </tr>
                        </thead>
                        <tbody id="items-tbody" class="bg-white divide-y divide-gray-200">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
                
                <div>
                     <label for="note" class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                     <textarea name="note" id="note" rows="2" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50"><?php echo esc_textarea($transfer->note); ?></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                     <a href="<?php echo esc_url(add_query_arg('view', 'transfer-list')); ?>" class="px-4 py-2 bg-white text-gray-700 font-medium rounded-md border border-gray-300 hover:bg-gray-50">Cancel</a>
                     <button type="submit" id="submit-btn" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 items-center flex">
                         <i class="fa-solid fa-save mr-2"></i> Update Transfer
                     </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
    let itemsData = <?php echo json_encode(array_map(function($item) {
        return [
            'id' => $item->item_id,
            'name' => $item->item_name,
            'stock' => floatval($item->current_stock),
            'qty' => floatval($item->transfer_qty)
        ];
    }, $items)); ?>;

    // Initial render
    renderTable();

    $('#warehouse_from').on('change', function() {
        if ($(this).val()) {
            $('#item-autocomplete').prop('disabled', false).removeClass('bg-gray-100 cursor-not-allowed').addClass('bg-white');
            $('#warehouse-error').addClass('hidden');
        } else {
            $('#item-autocomplete').prop('disabled', true).addClass('bg-gray-100 cursor-not-allowed').removeClass('bg-white');
        }
    });

    $('#item-autocomplete').autocomplete({
        source: function(request, response) {
            const wh = $('#warehouse_from').val();
            if (!wh) { $('#warehouse-error').removeClass('hidden'); return; }
            
            $.ajax({
                url: ajaxurl, type: 'POST', dataType: 'json',
                data: { action: 'search_stock_items', security: '<?php echo $search_nonce; ?>', search: request.term, warehouse_id: wh },
                success: function(data) {
                    if (data.success && data.data.length > 0) {
                        response($.map(data.data, function(item) {
                            return { label: item.item_name + ' (Qty: ' + (item.stock || 0) + ')', value: item.item_name, data: item };
                        }));
                    } else response([]);
                }
            });
        },
        minLength: 2,
        select: function(e, ui) { addItem(ui.item.data); $(this).val(''); return false; }
    });

    function addItem(item) {
        if (itemsData.find(i => i.id == item.id)) { alert('Item already added.'); return; }
        const stock = parseFloat(item.stock) || 0;
        if (stock <= 0) { alert('Item has no stock in source warehouse.'); return; }

        itemsData.push({ id: item.id, name: item.item_name, stock: stock, qty: 1 });
        renderTable();
    }

    function renderTable() {
        const tbody = $('#items-tbody'); tbody.empty();
        if (itemsData.length === 0) {
             tbody.html('<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No items.</td></tr>'); return;
        }
        
        itemsData.forEach((item, index) => {
             const row = `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-500">${index + 1}</td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">${item.name}</td>
                    <td class="px-6 py-4 text-sm text-right text-gray-700">${item.stock}</td>
                    <td class="px-6 py-4 text-right">
                        <input type="number" value="${item.qty}" min="0.01" max="${item.stock}" step="any" data-id="${item.id}" class="qty-input w-24 rounded-md border-gray-300 text-sm py-1 px-2 border text-right">
                    </td>
                    <td class="px-6 py-4 text-right">
                        <button type="button" class="text-red-600 hover:text-red-900 remove-item" data-id="${item.id}"><i class="fa-solid fa-trash"></i></button>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    $(document).on('input', '.qty-input', function() {
        const id = $(this).data('id');
        const val = parseFloat($(this).val()) || 0;
        const item = itemsData.find(i => i.id == id);
        if (item) {
            if (val > item.stock) {
                alert('Transfer quantity cannot exceed available stock (' + item.stock + ')');
                $(this).val(item.stock);
                item.qty = item.stock;
            } else {
                item.qty = val;
            }
        }
    });

    $(document).on('click', '.remove-item', function() {
        itemsData = itemsData.filter(i => i.id != $(this).data('id'));
        renderTable();
    });

    $('#transfer-form').on('submit', function(e) {
        e.preventDefault();
        const from = $('#warehouse_from').val();
        const to = $('#warehouse_to').val();
        
        if (!from || !to) { alert('Select warehouses.'); return; }
        if (from == to) { alert('Source and Destination warehouses must be different.'); return; }
        if (itemsData.length == 0) { alert('Add items.'); return; }

        const btn = $('#submit-btn'); const orig = btn.html();
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Updating...');

        $.ajax({
            url: ajaxurl, type: 'POST',
            data: {
                action: 'update_transfer',
                security: '<?php echo wp_create_nonce('frontend_ajax_nonce'); ?>',
                transfer_id: <?php echo $transfer_id; ?>,
                transfer_date: $('#transfer_date').val(),
                warehouse_from: from,
                warehouse_to: to,
                note: $('#note').val(),
                items: itemsData.map(i => ({ item_id: i.id, qty: i.qty }))
            },
            success: function(r) {
                if (r.success) { alert('Updated!'); window.location.href = '<?php echo esc_url(add_query_arg('view', 'transfer-list')); ?>'; }
                else { alert(r.data); btn.prop('disabled', false).html(orig); }
            },
            error: function() { alert('Error.'); btn.prop('disabled', false).html(orig); }
        });
    });
});
</script>
