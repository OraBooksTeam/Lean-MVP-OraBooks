<?php
/**
 * View Items Template
 */
if (!defined('ABSPATH')) exit;
global $wpdb;
$items_table = $wpdb->prefix . 'orabooks_db_items';
$warehouseitems_table = $wpdb->prefix . 'orabooks_db_warehouseitems';
$items = $wpdb->get_results("SELECT * FROM {$items_table} ORDER BY id DESC");
$categories_table = $wpdb->prefix . 'orabooks_db_category';
$brands_table = $wpdb->prefix . 'orabooks_db_brands';
?>
<div class="obn-card p-6 !pt-2">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-2xl font-bold text-gray-800">Items</h3>
        <div class="flex gap-2">
            <button type="button" onclick="showView('obn-view-add-item')"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-plus-circle"></i> Add Item
            </button>
            <button type="button" onclick="showView('obn-view-add-service')"
                class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-plus-circle"></i> Add Service
            </button>
            <button type="button" onclick="showView('obn-view-import-items')"
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-file-import"></i> Import
            </button>
            <button type="button" onclick="showView('obn-view-variants-list')"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-code-branch"></i> Variants
            </button>
            <button type="button" onclick="showView('obn-view-print-labels')"
                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-tag"></i> Print Labels
            </button>
        </div>
    </div>

    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <div class="relative w-full md:w-96">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="search" id="obn-items-search" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="Search items...">
        </div>
        <div class="flex items-center gap-2">
            <div class="relative inline-block text-left">
                <button type="button" class="obn-column-toggle-btn inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fa-solid fa-columns mr-2"></i> Columns
                </button>
                <div class="obn-column-dropdown hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                    <div class="py-1 p-3 space-y-2">
                        <?php
                        $item_cols = ['Code', 'Name', 'Brand', 'Category/Type', 'Stock', 'Price'];
                        foreach ($item_cols as $idx => $name): ?>
                        <label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
                            <input type="checkbox" checked class="obn-col-hide form-checkbox h-4 w-4 text-blue-600 rounded" data-column="<?php echo $idx; ?>" data-table="#obn-items-table">
                            <span class="ml-3 text-sm text-gray-700 font-bold uppercase"><?php echo $name; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="flex items-center bg-gray-100 p-1 rounded-lg">
                <button class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-items-table" data-title="Items List" title="Print"><i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span></button>
                <button class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-items-table" data-title="Items List" title="PDF"><i class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span></button>
                <button class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-items-table" data-title="Items List" title="Excel"><i class="fa-solid fa-file-excel mr-1"></i> <span class="hidden sm:inline">Excel</span></button>
                <button class="obn-csv-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-items-table" data-title="Items_List" title="CSV"><i class="fa-solid fa-file-csv mr-1"></i> <span class="hidden sm:inline">CSV</span></button>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
        <table id="obn-items-table" class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Code</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Name</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Brand</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Category / Type</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Stock</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Price</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-700 no-export">Status</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($items): foreach ($items as $item):
                    $cat_name = $item->category_id ? $wpdb->get_var($wpdb->prepare("SELECT category_name FROM {$categories_table} WHERE id = %d", $item->category_id)) : '';
                    $brand_name = $item->brand_id ? $wpdb->get_var($wpdb->prepare("SELECT brand_name FROM {$brands_table} WHERE id = %d", $item->brand_id)) : '';
                    $stock_qty = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(available_qty), 0) FROM {$warehouseitems_table} WHERE item_id = %d", $item->id));
                    $item_type_label = ($item->service_bit == 1) ? 'Service' : 'Single Item';
                ?>
                <tr class="hover:bg-gray-50" data-id="<?php echo esc_attr($item->id); ?>">
                    <td class="px-4 py-3 text-gray-800 font-medium"><?php echo esc_html($item->item_code); ?></td>
                    <td class="px-4 py-3 text-gray-800"><?php echo esc_html($item->item_name); ?></td>
                    <td class="px-4 py-3 text-gray-600"><?php echo esc_html($brand_name); ?></td>
                    <td class="px-4 py-3 text-gray-600">
                        <?php echo esc_html($cat_name); ?><?php echo ($cat_name ? ' / ' : ''); ?><span class="font-semibold"><?php echo $item_type_label; ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="font-semibold <?php echo ($stock_qty <= ($item->alert_qty ?? 0) && ($item->alert_qty ?? 0) > 0) ? 'text-red-600' : 'text-green-600'; ?>">
                            <?php echo esc_html($stock_qty); ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-800 font-semibold"><?php echo esc_html(number_format($item->sales_price ?? 0, 2)); ?></td>
                    <td class="px-4 py-3 text-center no-export">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" class="obn-toggle-item-status sr-only peer" data-id="<?php echo esc_attr($item->id); ?>" data-status="<?php echo esc_attr($item->status); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('obn_auth_nonce')); ?>" <?php echo ($item->status == 1) ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </td>
                    <td class="px-4 py-3 text-right space-x-2 flex justify-end no-export">
                        <button class="obn-edit-item-btn px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium transition" data-id="<?php echo esc_attr($item->id); ?>">Edit</button>
                        <button class="obn-delete-item-btn px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition" data-id="<?php echo esc_attr($item->id); ?>">Delete</button>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">No items found. Click "Add Item" to create one.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Items search
    $('#obn-items-search').on('keyup', function() {
        var value = this.value.toLowerCase();
        $('#obn-items-table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    // Toggle status
    $(document).on('change', '.obn-toggle-item-status', function() {
        var checkbox = $(this);
        var id = checkbox.data('id');
        var status = checkbox.prop('checked') ? 1 : 0;
        $.post(obn_ajax.ajax_url, {
            action: 'obn_update_item_status',
            id: id,
            status: status,
            security: checkbox.data('nonce')
        }, function(response) {
            if (!response.success) {
                checkbox.prop('checked', !checkbox.prop('checked'));
            }
        });
    });

    // Edit item
    $(document).on('click', '.obn-edit-item-btn', function() {
        var id = $(this).data('id');
        $.post(obn_ajax.ajax_url, {
            action: 'obn_get_item',
            id: id,
            security: '<?php echo wp_create_nonce('obn_auth_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                var item = response.data;
                $('#acc_edit_item_id').val(item.id);
                $('#acc_edit_item_code').val(item.item_code);
                $('#acc_edit_item_name').val(item.item_name);
                $('#acc_edit_item_type').val(item.item_type);
                $('#acc_edit_purchase_price').val(item.purchase_price);
                $('#acc_edit_price').val(item.price);
                $('#acc_edit_sales_price_input').val(item.sales_price);
                $('#acc_edit_opening_stock').val(item.stock || 0);
                $('#acc_edit_alert_stock').val(item.alert_qty || 0);
                $('#acc_edit_description').val(item.description);
                $('#acc_edit_sku').val(item.sku);
                $('#acc_edit_barcode').val(item.custom_barcode);
                $('#acc_edit_item_group').val(item.item_group || '');
                $('#acc_edit_hsn').val(item.hsn);
                $('#acc_edit_seller_points').val(item.seller_points);
                $('#acc_edit_discount_type').val(item.discount_type);
                $('#acc_edit_discount').val(item.discount);
                $('#acc_edit_tax_type').val(item.tax_type);
                $('#acc_edit_profit_margin').val(item.profit_margin);
                $('#acc_edit_mrp').val(item.mrp);

                // Initialize Select2 for edit form fields and set values after init
                if (typeof $.fn.select2 === 'function') {
                    $('#acc_edit_category_id, #acc_edit_tax_id, #acc_edit_brand_id, #acc_edit_unit_id, #acc_edit_warehouse_id').each(function() {
                        if ($(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2('destroy');
                        }
                        $(this).select2({
                            width: '100%',
                            dropdownParent: $('#obn-item-edit-form')
                        });
                    });
                    
                    // Set values AFTER Select2 is initialized
                    $('#acc_edit_category_id').val(item.category_id).trigger('change');
                    $('#acc_edit_brand_id').val(item.brand_id).trigger('change');
                    $('#acc_edit_unit_id').val(item.unit_id).trigger('change');
                    $('#acc_edit_tax_id').val(item.tax_id).trigger('change');
                    $('#acc_edit_warehouse_id').val(item.warehouse_id).trigger('change');
                }

                // Recalculate pricing fields to sync with loaded values
                calculatePricingEdit();
                
                if (item.image_url) {
                    $('#acc_image_edit_preview img').attr('src', item.image_url);
                    $('#acc_image_edit_preview').removeClass('hidden');
                } else {
                    $('#acc_image_edit_preview').addClass('hidden');
                }
                
                showView('obn-view-edit-item');
            } else {
                alert('Failed to fetch item details.');
            }
        });
    });

    // Delete item
    $(document).on('click', '.obn-delete-item-btn', function() {
        if (!confirm('Are you sure you want to delete this item?')) return;
        var btn = $(this);
        var id = btn.data('id');
        $.post(obn_ajax.ajax_url, {
            action: 'obn_delete_item',
            id: id,
            security: '<?php echo wp_create_nonce('obn_auth_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                btn.closest('tr').fadeOut(300, function() { $(this).remove(); });
            } else {
                alert(response.data || 'Delete failed.');
            }
        });
    });
});
</script>
