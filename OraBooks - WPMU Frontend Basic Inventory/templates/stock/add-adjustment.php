<?php
if (!defined('ABSPATH'))
    exit;

global $wpdb;

// Get warehouses
$warehouses = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}orabooks_db_warehouse WHERE status = 1");

$reference_no = 'ADJ-' . date('YmdHis');
$search_nonce = wp_create_nonce('search_stock_items');
?>
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Add Stock Adjustment</h1>
        <a href="<?php echo esc_url(add_query_arg('view', 'adjustment-list')); ?>"
            class="text-gray-600 hover:text-gray-900 font-medium transition-colors duration-200">
            <i class="fa-solid fa-arrow-left mr-1"></i> Back to List
        </a>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200">
        <div class="p-6">
            <form id="adjustment-form" class="space-y-6">
                <!-- Top Row -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="warehouse_id" class="block text-sm font-medium text-gray-700 mb-1">
                            Warehouse <span class="text-red-500">*</span>
                        </label>
                        <select name="warehouse_id" id="warehouse_id" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
                            <?php foreach ($warehouses as $warehouse): ?>
                                <option value="<?php echo $warehouse->id; ?>" <?php selected($warehouse->warehouse_type, 'system'); ?>><?php echo esc_html($warehouse->warehouse_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="adjustment_date" class="block text-sm font-medium text-gray-700 mb-1">
                            Adjustment Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="adjustment_date" id="adjustment_date"
                            value="<?php echo date('Y-m-d'); ?>" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
                    </div>

                    <div>
                        <label for="reference_no" class="block text-sm font-medium text-gray-700 mb-1">Reference
                            No.</label>
                        <input type="text" name="reference_no" id="reference_no"
                            value="<?php echo esc_attr($reference_no); ?>" readonly
                            class="w-full rounded-md border-gray-300 bg-gray-100 text-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border cursor-not-allowed">
                    </div>
                </div>

                <!-- Search -->
                <div>
                    <label for="item-autocomplete" class="block text-sm font-medium text-gray-700 mb-1">Search
                        Item</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-magnifying-glass text-gray-400"></i>
                        </div>
                        <input type="search" id="item-autocomplete"
                            class="w-full pl-10 py-2 rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm ui-autocomplete-input disabled:bg-gray-100 disabled:cursor-not-allowed"
                            placeholder="Search item by name / code / sku..." disabled>
                    </div>
                    <p class="mt-1 text-xs text-red-500 hidden" id="warehouse-error">Please select a warehouse first.
                    </p>
                </div>

                <!-- Items Table -->
                <div class="overflow-x-auto border rounded-md border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200" id="items-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-12">
                                    #</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                    Item Name</th>
                                <th scope="col"
                                    class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider w-32">
                                    Allocated Stock</th>
                                <th scope="col"
                                    class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider w-40">
                                    Qty</th> <!-- Adjustment Qty -->
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-32">
                                    Type</th>
                                <th scope="col"
                                    class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider w-24">
                                    Action</th>
                            </tr>
                        </thead>
                        <tbody id="items-tbody" class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    <span class="block text-sm">No items added. Select a warehouse and search for
                                        items.</span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="3" class="px-6 py-3 text-right font-bold text-gray-700">Total Adjustment
                                    Qty:</td>
                                <td class="px-6 py-3 text-right font-bold text-blue-600" id="total-qty">0.00</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Note -->
                <div>
                    <label for="adjustment_note" class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                    <textarea name="adjustment_note" id="adjustment_note" rows="3"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50"></textarea>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                    <a href="<?php echo esc_url(add_query_arg('view', 'adjustment-list')); ?>"
                        class="px-4 py-2 bg-white text-gray-700 font-medium rounded-md border border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200">
                        Cancel
                    </a>
                    <button type="submit" id="submit-btn"
                        class="px-6 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-sm transition-colors duration-200 flex items-center">
                        <i class="fa-solid fa-save mr-2"></i> Save Adjustment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function ($) {
        const ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
        let itemsData = [];
        let itemIndex = 0;

        // Warehouse Change
        $('#warehouse_id').on('change', function () {
            if ($(this).val()) {
                $('#item-autocomplete').prop('disabled', false).removeClass('bg-gray-100 cursor-not-allowed').addClass('bg-white');
                $('#warehouse-error').addClass('hidden');
            } else {
                $('#item-autocomplete').prop('disabled', true).addClass('bg-gray-100 cursor-not-allowed').removeClass('bg-white');
                itemsData = [];
                renderTable();
            }
        });

        // Autocomplete
        $('#item-autocomplete').autocomplete({
            source: function (request, response) {
                const warehouseId = $('#warehouse_id').val();
                if (!warehouseId) {
                    $('#warehouse-error').removeClass('hidden');
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'search_stock_items',
                        security: '<?php echo wp_create_nonce('frontend_ajax_nonce'); ?>',
                        search: request.term,
                        warehouse_id: warehouseId
                    },
                    success: function (data) {
                        if (data.success && data.data.length > 0) {
                            response($.map(data.data, function (item) {
                                return {
                                    label: item.item_name + ' (' + (item.sku || item.item_code) + ') - Stock: ' + (item.stock || 0),
                                    value: item.item_name,
                                    data: item
                                };
                            }));
                        } else {
                            response([]);
                        }
                    }
                });
            },
            minLength: 2,
            select: function (event, ui) {
                addItem(ui.item.data);
                $(this).val('');
                return false;
            }
        });

        function addItem(item) {
            if (itemsData.find(i => i.id == item.id)) {
                alert('Item already added.');
                return;
            }

            itemsData.push({
                id: item.id,
                name: item.item_name,
                code: item.item_code || item.sku,
                current_stock: parseFloat(item.stock) || 0,
                qty: 0
            });

            renderTable();
            // Focus on the newly added input
            setTimeout(() => {
                $(`#qty-${item.id}`).focus();
            }, 100);
        }

        function renderTable() {
            const tbody = $('#items-tbody');
            tbody.empty();

            if (itemsData.length === 0) {
                tbody.html(`<tr>
                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                    <span class="block text-sm">No items added. Select a warehouse and search for items.</span>
                </td>
             </tr>`);
                $('#total-qty').text('0.00');
                return;
            }

            let totalQty = 0;

            itemsData.forEach((item, index) => {
                totalQty += parseFloat(item.qty) || 0;
                const type = parseFloat(item.qty) >= 0 ? '<span class="text-green-600 font-medium">Addition (+)</span>' : '<span class="text-red-600 font-medium">Subtraction (-)</span>';

                const row = `
                <tr class="hover:bg-gray-50 border-b border-gray-100 last:border-0">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${index + 1}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <div class="font-medium">${item.name}</div>
                        <div class="text-xs text-gray-500">${item.code}</div>
                        <input type="hidden" name="items[${index}][item_id]" value="${item.id}">
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 text-right font-medium">${item.current_stock}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <input type="number" id="qty-${item.id}" step="any" min="-${item.current_stock}" value="${item.qty}" data-id="${item.id}" 
                            class="qty-input w-32 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-1 px-2 border text-right">
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-left">${type}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button type="button" class="text-red-600 hover:text-red-900 remove-item" data-id="${item.id}">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
                tbody.append(row);
            });

            $('#total-qty').text(totalQty.toFixed(2));
        }

        // Update Qty
        $(document).on('input', '.qty-input', function () {
            const id = $(this).data('id');
            const val = parseFloat($(this).val()) || 0;

            const item = itemsData.find(i => i.id == id);
            if (item) {
                // Validate Subtraction limit
                if (val < 0 && Math.abs(val) > item.current_stock) {
                    alert('Cannot reduce stock below zero. Max reduction allowed: ' + item.current_stock);
                    $(this).val(-item.current_stock);
                    item.qty = -item.current_stock;
                } else {
                    item.qty = val;
                }
                renderTable(); // Re-render to update Type label and Total
                // Restore focus (re-render loses it)
                // Actually re-rendering entire table on input is bad for UX (focus loss). 
                // Better to just update the row's Type and Total.

                // Optimization: Just update DOM elements instead of full re-render for input
                // But for now, let's keep it simple. If focus loss is issue, we can optimize.
                // Wait, re-render WILL lose focus.
                // Let's optimize.

                // Fix: Do NOT call renderTable here unless we really need to add/remove rows.
                // Just update total and type label.
                updateRowSummary(id, val);
            }
        });

        function updateRowSummary(id, val) {
            let totalQty = 0;
            itemsData.forEach(i => {
                totalQty += parseFloat(i.qty) || 0;
            });
            $('#total-qty').text(totalQty.toFixed(2));

            const row = $(`#qty-${id}`).closest('tr');
            const typeLabel = val >= 0 ? '<span class="text-green-600 font-medium">Addition (+)</span>' : '<span class="text-red-600 font-medium">Subtraction (-)</span>';
            row.find('td:eq(4)').html(typeLabel);
        }

        // Remove Item
        $(document).on('click', '.remove-item', function () {
            const id = $(this).data('id');
            itemsData = itemsData.filter(i => i.id != id);
            renderTable();
        });

        // Submit
        $('#adjustment-form').on('submit', function (e) {
            e.preventDefault();

            if (itemsData.length === 0) {
                alert('Please add at least one item.');
                return;
            }

            const btn = $('#submit-btn');
            const originalText = btn.html();
            btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Saving...');

            const formData = {
                action: 'save_adjustment',
                security: '<?php echo wp_create_nonce('frontend_ajax_nonce'); ?>',
                warehouse_id: $('#warehouse_id').val(),
                adjustment_date: $('#adjustment_date').val(),
                reference_no: $('#reference_no').val(),
                adjustment_note: $('#adjustment_note').val(),
                items: itemsData.map(i => ({ item_id: i.id, qty: i.qty }))
            };

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                success: function (response) {
                    if (response.success) {
                        alert('Adjustment saved successfully!');
                        window.location.href = '<?php echo esc_url(add_query_arg('view', 'adjustment-list')); ?>';
                    } else {
                        alert('Error: ' + response.data);
                        btn.prop('disabled', false).html(originalText);
                    }
                },
                error: function () {
                    alert('Server error occurred.');
                    btn.prop('disabled', false).html(originalText);
                }
            });
        });
        // Initialize state
        $('#warehouse_id').trigger('change');
    });
</script>