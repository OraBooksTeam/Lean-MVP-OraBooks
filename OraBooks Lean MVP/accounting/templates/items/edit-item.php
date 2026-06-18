<?php
/**
 * Edit Item Template
 */
if (!defined('ABSPATH')) exit;
global $wpdb;
$categories_table = $wpdb->prefix . 'orabooks_db_category';
$brands_table = $wpdb->prefix . 'orabooks_db_brands';
$units_table = $wpdb->prefix . 'orabooks_db_units';
$tax_table = $wpdb->prefix . 'orabooks_db_tax';
$warehouses_table = $wpdb->prefix . 'orabooks_db_warehouse';

$categories = $wpdb->get_results("SELECT * FROM {$categories_table} WHERE status = 1 OR status IS NULL ORDER BY category_name ASC");
$brands = $wpdb->get_results("SELECT * FROM {$brands_table} WHERE status = 1 OR status IS NULL ORDER BY brand_name ASC");
$units = $wpdb->get_results("SELECT * FROM {$units_table} WHERE status = 1 OR status IS NULL ORDER BY unit_name ASC");
$taxes = $wpdb->get_results("SELECT * FROM {$tax_table} ORDER BY tax_name ASC");
$warehouses = $wpdb->get_results("SELECT * FROM {$warehouses_table} WHERE status = 1 OR status IS NULL ORDER BY warehouse_name ASC");
?>
<div class="acc_card p-6 !pt-4">
    <div class="flex flex-col md:flex-row justify-between items-center mb-8 border-b border-gray-100 pb-4 gap-4">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center mr-4 shadow-sm border border-orange-100">
                <i class="fa-solid fa-edit text-xl"></i>
            </div>
            <div>
                <h3 class="text-2xl font-bold text-gray-800">Edit Item</h3>
                <p class="text-sm text-gray-500 mt-0.5">Update your existing inventory stock details</p>
            </div>
        </div>
        <a href="#" onclick="showView('obn-view-view-items')" class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-gray-700 to-gray-800 text-white rounded-xl hover:from-gray-800 hover:to-gray-900 transition-all font-bold text-xs uppercase tracking-widest shadow-lg shadow-gray-200 hover:shadow-gray-300 active:scale-95">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Items
        </a>
    </div>

    <form id="acc_item_edit_form" class="space-y-6">
        <input type="hidden" name="action" value="obn_update_item">
        <input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('obn_auth_nonce')); ?>">
        <input type="hidden" name="id" id="acc_edit_item_id" value="">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Left Column: Item Information -->
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-5">
                <h4 class="text-lg font-bold text-gray-800 flex items-center gap-2 border-b border-gray-50 pb-3 mb-2">
                    <i class="fa-solid fa-circle-info text-blue-500"></i> Item Information
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-1">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Item Code <span class="text-red-500">*</span></label>
                        <input type="text" name="item_code" id="acc_edit_item_code" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all text-gray-600 font-medium" required readonly>
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Item Name <span class="text-red-500">*</span></label>
                        <input type="text" name="item_name" id="acc_edit_item_name" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all" placeholder="Enter item name" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Brand</label>
                        <div class="flex gap-2">
                            <select name="brand_id" id="acc_edit_brand_id" class="acc_select2_edit w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all">
                                <option value="">- Select -</option>
                                <?php foreach ($brands as $brand): ?>
                                <option value="<?php echo esc_attr($brand->id); ?>"><?php echo esc_html($brand->brand_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" onclick="openModal('modal_brand')" class="px-3.5 py-2.5 bg-gray-50 hover:bg-gray-100 text-gray-600 rounded-xl border border-gray-200 transition-all" title="Add Brand"><i class="fa-solid fa-plus"></i></button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Category <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <select name="category_id" id="acc_edit_category_id" class="acc_select2_edit w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all" required>
                                <option value="">- Select -</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat->id); ?>"><?php echo esc_html($cat->category_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" onclick="openModal('modal_category')" class="px-3.5 py-2.5 bg-gray-50 hover:bg-gray-100 text-gray-600 rounded-xl border border-gray-200 transition-all" title="Add Category"><i class="fa-solid fa-plus"></i></button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Unit <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <select name="unit_id" id="acc_edit_unit_id" class="acc_select2_edit w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all" required>
                                <option value="">- Select -</option>
                                <?php foreach ($units as $unit): ?>
                                <option value="<?php echo esc_attr($unit->id); ?>"><?php echo esc_html($unit->unit_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" onclick="openModal('modal_unit')" class="px-3.5 py-2.5 bg-gray-50 hover:bg-gray-100 text-gray-600 rounded-xl border border-gray-200 transition-all" title="Add Unit"><i class="fa-solid fa-plus"></i></button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">HSN</label>
                        <input type="text" name="hsn" id="acc_edit_hsn" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all" placeholder="HSN Code">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">SKU</label>
                        <input type="text" name="sku" id="acc_edit_sku" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all" placeholder="Stock Keeping Unit">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Barcode</label>
                        <input type="text" name="barcode" id="acc_edit_barcode" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all" placeholder="Barcode / UPC">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Item Group</label>
                    <input type="text" name="item_group" id="acc_edit_item_group" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all" placeholder="e.g. Raw Material, Finished Goods">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Alert Quantity</label>
                        <input type="number" name="alert_stock" id="acc_edit_alert_stock" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all" placeholder="0" min="0">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Seller Points</label>
                        <input type="text" name="seller_points" id="acc_edit_seller_points" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all" placeholder="0">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <textarea name="description" id="acc_edit_description" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all" rows="3" placeholder="Optional item details..."></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Item Image</label>
                    <div class="flex items-center justify-center w-full">
                        <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-200 border-dashed rounded-xl cursor-pointer bg-gray-50 hover:bg-gray-100 transition-all">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <i class="fa-solid fa-cloud-arrow-up text-2xl text-gray-400 mb-2"></i>
                                <p class="text-xs text-gray-500">Click to upload or drag and drop</p>
                            </div>
                            <input type="file" name="item_image" id="acc_item_image_edit_input" class="hidden" accept="image/*" />
                        </label>
                    </div>
                    <div id="acc_image_edit_preview" class="mt-4 hidden w-32 h-32 rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                        <img src="" alt="Preview" class="w-full h-full object-cover">
                    </div>
                </div>
            </div>

            <!-- Right Column: Pricing & Stock -->
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-5 h-fit">
                <h4 class="text-lg font-bold text-gray-800 flex items-center gap-2 border-b border-gray-50 pb-3 mb-2">
                    <i class="fa-solid fa-calculator text-blue-500"></i> Pricing & Stock
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Discount Type</label>
                        <select name="discount_type" id="acc_edit_discount_type" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all">
                            <option value="Percentage">Percentage (%)</option>
                            <option value="Fixed">Fixed Amount</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Discount</label>
                        <input type="number" step="0.01" name="discount" id="acc_edit_discount" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all" placeholder="0.00">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Price <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" name="price" id="acc_edit_price" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all font-semibold" placeholder="0.00" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Purchase Price</label>
                        <input type="number" step="0.01" name="purchase_price" id="acc_edit_purchase_price" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all text-gray-600 font-medium" placeholder="0.00" readonly>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Tax <span class="text-red-500"></span></label>
                        <div class="flex gap-2">
                            <select name="tax_id" id="acc_edit_tax_id" class="acc_select2_edit w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all">
                                <option value="" data-percent="0">- Select -</option>
                                <?php foreach ($taxes as $tax): ?>
                                <option value="<?php echo esc_attr($tax->id); ?>" data-percent="<?php echo esc_attr($tax->tax); ?>"><?php echo esc_html($tax->tax_name . ' (' . $tax->tax . '%)'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" onclick="openModal('modal_tax')" class="px-3.5 py-2.5 bg-gray-50 hover:bg-gray-100 text-gray-600 rounded-xl border border-gray-200 transition-all" title="Add Tax"><i class="fa-solid fa-plus"></i></button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Tax Type</label>
                        <select name="tax_type" id="acc_edit_tax_type" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all">
                            <option value="Inclusive">Inclusive</option>
                            <option value="Exclusive">Exclusive</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Profit Margin (%)</label>
                    <input type="number" step="0.01" name="profit_margin" id="acc_edit_profit_margin" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all" placeholder="0.00">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Sales Price</label>
                        <input type="number" step="0.01" name="sales_price" id="acc_edit_sales_price_input" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all font-bold text-blue-600" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">MRP</label>
                        <input type="number" step="0.01" name="mrp" id="acc_edit_mrp" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all" placeholder="0.00">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Warehouse</label>
                        <select name="warehouse_id" id="acc_edit_warehouse_id" class="acc_select2_edit w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all">
                            <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?php echo esc_attr($warehouse->id); ?>"><?php echo esc_html($warehouse->warehouse_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Opening Stock</label>
                        <input type="number" step="0.01" name="opening_stock" id="acc_edit_opening_stock" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all" placeholder="0.00">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Item Type</label>
                    <select name="item_type" id="acc_edit_item_type" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-all font-medium">
                        <option value="Single">Single Item</option>
                        <option value="Variants">Product Variants</option>
                        <option value="service">Service</option>
                    </select>
                </div>

                <div class="pt-6">
                    <button type="submit" id="acc_item_edit_save" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-4 rounded-xl font-bold transition-all shadow-lg shadow-blue-100 flex items-center justify-center gap-2 active:scale-[0.98]">
                        <i class="fa-solid fa-save text-lg"></i> Update Item
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    var $editForm = $('#acc_item_edit_form');

    // Initialize Select2 with form as dropdown parent for reliable positioning
    function initSelect2Edit() {
        $('.obn-select2-edit', $editForm).each(function() {
            if ($(this).hasClass('select2-hidden-accessible')) {
                $(this).select2('destroy');
            }
            $(this).select2({
                width: '100%',
                dropdownParent: $editForm
            });
        });
    }

    // Re-init Select2 when view is activated (event fires after fadeIn completes)
    $(document).on('obn_view_activated', function(e, view) {
        if (view === 'edit-item') {
            initSelect2Edit();
        }
    });

    // Image Preview
    $('#acc_item_image_edit_input').on('change', function(e) {
        if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#acc_image_edit_preview img').attr('src', e.target.result);
                $('#acc_image_edit_preview').removeClass('hidden');
            }
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Auto Calculations — made globally accessible for edit button handler in view-items.php
    window.calculatePricingEdit = function () {
        let price = parseFloat($('#acc_edit_price').val()) || 0;
        let taxPercent = parseFloat($('#acc_edit_tax_id option:selected').data('percent')) || 0;
        let taxType = $('#acc_edit_tax_type').val();
        let profitMargin = parseFloat($('#acc_edit_profit_margin').val()) || 0;

        // Purchase Price calculation — no profit margin applied
        let purchasePrice = price;
        if (taxType === 'Exclusive' && taxPercent > 0) {
            purchasePrice = price + (price * taxPercent / 100);
        }
        $('#acc_edit_purchase_price').val(purchasePrice.toFixed(2));

        // Sales Price calculation — profit margin applied to base price
        let salesPrice = price;
        if (profitMargin > 0) {
            salesPrice = price + (price * profitMargin / 100);
        }
        $('#acc_edit_sales_price_input').val(salesPrice.toFixed(2));

        // MRP calculation — matches Sales Price
        $('#acc_edit_mrp').val(salesPrice.toFixed(2));
    }

    $('#acc_edit_price, #acc_edit_profit_margin, #acc_edit_tax_id, #acc_edit_tax_type').on('keyup change', window.calculatePricingEdit);

    $('#acc_item_edit_form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var btn = $('#acc_item_edit_save');
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Updating...');
        
        var formData = new FormData(this);
        
        $.ajax({
            url: obn_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: response.data.message,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    setTimeout(function() {
                        localStorage.setItem('obn-after-reload-view', 'obn-view-view-items');
                        location.reload();
                    }, 1500);
                } else {
                    Swal.fire('Error', response.data || 'Update failed.', 'error');
                    btn.prop('disabled', false).html('<i class="fa-solid fa-save mr-2"></i> Update Item');
                }
            },
            error: function() {
                Swal.fire('Error', 'Request failed.', 'error');
                btn.prop('disabled', false).html('<i class="fa-solid fa-save mr-2"></i> Update Item');
            }
        });
    });
});
</script>
