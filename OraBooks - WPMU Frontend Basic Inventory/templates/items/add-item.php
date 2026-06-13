<?php
if (!defined('ABSPATH'))
    exit;

global $wpdb;
$brands_table = $wpdb->prefix . 'orabooks_db_brands';
$categories_table = $wpdb->prefix . 'orabooks_db_category';
$units_table = $wpdb->prefix . 'orabooks_db_units';
$tax_table = $wpdb->prefix . 'orabooks_db_tax';
$warehouses_table = $wpdb->prefix . 'orabooks_db_warehouse';
$items_table = $wpdb->prefix . 'orabooks_db_items';

// Fetch Dropdown Data
$brands = $wpdb->get_results("SELECT * FROM $brands_table WHERE status=1 ORDER BY brand_name ASC");
$categories = $wpdb->get_results("SELECT * FROM $categories_table WHERE status=1 ORDER BY category_name ASC");
$units = $wpdb->get_results("SELECT * FROM $units_table WHERE status=1 ORDER BY unit_name ASC");
$taxes = $wpdb->get_results("SELECT * FROM $tax_table WHERE status=1 ORDER BY tax_name ASC");
$warehouses = $wpdb->get_results("SELECT * FROM $warehouses_table WHERE status=1 ORDER BY warehouse_name ASC");

// Auto-Generate Item Code
$last_item = $wpdb->get_row("SELECT count_id FROM $items_table ORDER BY id DESC LIMIT 1");
$count_id = ($last_item && $last_item->count_id) ? $last_item->count_id + 1 : 1;
$item_code = 'ITM-' . str_pad($count_id, 6, '0', STR_PAD_LEFT);
?>

<div class="p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
        <div class="flex items-center">
            <div
                class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center mr-4 shadow-sm font-bold">
                <i class="fa-solid fa-plus-circle text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Add New Item</h1>
                <p class="text-sm text-gray-500 mt-1">Create and manage your inventory stock</p>
            </div>
        </div>
        <a href="<?php echo esc_url(add_query_arg('view', 'view-items')); ?>"
            class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-xl hover:from-indigo-700 hover:to-violet-700 transition-all font-bold text-xs uppercase tracking-widest shadow-lg shadow-indigo-200 hover:shadow-indigo-300 active:scale-95">
            <i class="fa-solid fa-arrow-left-long mr-2"></i> Back to List
        </a>
    </div>

    <!-- Success Message -->
    <div id="success-message"
        class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline" id="success-text"></span>
    </div>

    <form id="add-item-form" class="space-y-6 relative" enctype="multipart/form-data">
        <input type="hidden" name="action" value="frontend_insert_item">
        <input type="hidden" name="security" value="<?php echo wp_create_nonce('frontend_ajax_nonce'); ?>">
        <input type="hidden" name="store_id" value="1">
        <input type="hidden" name="count_id" value="<?php echo esc_attr($count_id); ?>">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left Column: Item Info -->
            <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4">Item Information</h3>

                <!-- Item Code -->
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Item Code <span
                            class="text-red-500">*</span></label>
                    <input type="text" name="item_code" value="<?php echo esc_attr($item_code); ?>" readonly
                        class="w-full bg-gray-100 text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500 cursor-not-allowed">
                </div>

                <!-- Item Name -->
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Item Name <span
                            class="text-red-500">*</span></label>
                    <input type="text" name="item_name" required
                        class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500 placeholder-gray-400"
                        placeholder="Enter item name">
                </div>

                <!-- Brand -->
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Brand</label>
                    <div class="flex">
                        <select name="brand_id"
                            class="w-full bg-white text-gray-700 border border-gray-300 rounded-l py-2 px-3 focus:outline-none focus:border-blue-500">
                            <option value="">- Select -</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?php echo $brand->id; ?>"><?php echo esc_html($brand->brand_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" data-bs-toggle="modal" data-bs-target="#brand_modal"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-600 px-3 py-2 rounded-r border border-l-0 border-gray-300 flex items-center justify-center"
                            title="Add New Brand">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>

                <!-- Category -->
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Category <span
                            class="text-red-500">*</span></label>
                    <div class="flex">
                        <select name="category_id" required
                            class="w-full bg-white text-gray-700 border border-gray-300 rounded-l py-2 px-3 focus:outline-none focus:border-blue-500">
                            <option value="">- Select -</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat->id; ?>"><?php echo esc_html($cat->category_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" data-bs-toggle="modal" data-bs-target="#category_modal"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-600 px-3 py-2 rounded-r border border-l-0 border-gray-300 flex items-center justify-center"
                            title="Add New Category">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>

                <!-- Unit -->
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Unit <span
                            class="text-red-500">*</span></label>
                    <div class="flex">
                        <select name="unit_id" required
                            class="w-full bg-white text-gray-700 border border-gray-300 rounded-l py-2 px-3 focus:outline-none focus:border-blue-500">
                            <option value="">- Select -</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?php echo $unit->id; ?>"><?php echo esc_html($unit->unit_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" data-bs-toggle="modal" data-bs-target="#unit_modal"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-600 px-3 py-2 rounded-r border border-l-0 border-gray-300 flex items-center justify-center"
                            title="Add New Unit">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>

                <!-- SKU & HSN -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">SKU</label>
                        <input type="text" name="sku"
                            class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">HSN</label>
                        <input type="text" name="hsn"
                            class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500">
                    </div>
                </div>

                <!-- Alert Quantity & Seller Points -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Alert Quantity</label>
                        <input type="number" name="alert_qty"
                            class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500"
                            min="0">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Seller Points</label>
                        <input type="text" name="seller_points"
                            class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500">
                    </div>
                </div>

                <!-- Barcode -->
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Barcode</label>
                    <input type="text" name="custom_barcode"
                        class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500">
                </div>

                <!-- Description -->
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                    <textarea name="description" rows="3"
                        class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500"></textarea>
                </div>
                <!-- Item Image -->
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Item Image</label>
                    <input type="file" name="item_image" id="item_image" accept="image/*"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer bg-white border border-gray-300 rounded">
                    <div id="image-preview"
                        class="mt-4 hidden w-32 h-32 rounded border border-gray-300 overflow-hidden">
                        <img src="" alt="Preview" class="w-full h-full object-cover">
                    </div>
                </div>

            </div>

            <!-- Right Column: Pricing & Stock -->
            <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200 h-fit">
                <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4">Pricing & Stock</h3>

                <!-- Discount Type & Discount -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Discount Type</label>
                        <select name="discount_type"
                            class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500">
                            <option value="Percentage">Percentage(%)</option>
                            <option value="Fixed">Fixed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Discount</label>
                        <input type="number" step="0.01" name="discount"
                            class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500">
                    </div>
                </div>

                <!-- Price & Purchase Price -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Price <span
                                class="text-red-500">*</span></label>
                        <input type="number" step="0.01" name="price" id="price" required
                            class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Purchase Price</label>
                        <input type="number" step="0.01" name="purchase_price" id="purchase_price"
                            class="w-full bg-gray-100 text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none cursor-not-allowed">
                    </div>
                </div>

                <!-- Tax -->
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Tax <span
                            class="text-red-500">*</span></label>
                    <div class="flex">
                        <select name="tax_id" id="tax_id"
                            class="w-full bg-white text-gray-700 border border-gray-300 rounded-l py-2 px-3 focus:outline-none focus:border-blue-500">
                            <option value="" data-percent="0">- Select -</option>
                            <?php foreach ($taxes as $tax): ?>
                                <option value="<?php echo $tax->id; ?>" data-percent="<?php echo esc_attr($tax->tax); ?>">
                                    <?php echo esc_html($tax->tax_name); ?> (<?php echo esc_html($tax->tax); ?>%)</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" data-bs-toggle="modal" data-bs-target="#tax_modal"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-600 px-3 py-2 rounded-r border border-l-0 border-gray-300"
                            title="Add New Tax">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>

                <!-- Tax Type -->
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Tax Type</label>
                    <select name="tax_type" id="tax_type"
                        class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500">
                        <option value="Inclusive">Inclusive</option>
                        <option value="Exclusive">Exclusive</option>
                    </select>
                </div>

                <!-- Profit Margin -->
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Profit Margin (%)</label>
                    <input type="number" step="0.01" name="profit_margin" id="profit_margin"
                        class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500">
                </div>

                <!-- Sales Price & MRP -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Sales Price</label>
                        <input type="number" step="0.01" name="sales_price" id="sales_price"
                            class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">MRP</label>
                        <input type="number" step="0.01" name="mrp" id="mrp"
                            class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500">
                    </div>
                </div>

                <!-- Warehouse & Opening Stock -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Warehouse</label>
                        <select name="warehouse_id"
                            class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500">
                            <?php foreach ($warehouses as $warehouse): ?>
                                <option value="<?php echo $warehouse->id; ?>" <?php selected($warehouse->warehouse_type, 'system'); ?>><?php echo esc_html($warehouse->warehouse_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Opening Stock</label>
                        <input type="number" step="0.01" name="adjustment_qty"
                            class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500">
                    </div>
                </div>

                <!-- Item Type -->
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Item Type</label>
                    <select name="item_type"
                        class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500">
                        <option value="Single">Single</option>
                        <option value="Variants">Variants</option>
                    </select>
                </div>

                <!-- Submit Button -->
                <div class="mt-8">
                    <button type="submit" id="save-btn"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded focus:outline-none focus:shadow-outline transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fa fa-save mr-2"></i> Save Item
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    jQuery(document).ready(function ($) {
        // Init Select2 with local parent to fix alignment
        $('select').select2({ 
            width: '100%',
            dropdownParent: $('#add-item-form')
        });

        // Image Preview
        $('#item_image').on('change', function (e) {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    $('#image-preview img').attr('src', e.target.result);
                    $('#image-preview').removeClass('hidden');
                }
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Auto Calculations
        function calculatePricing() {
            let price = parseFloat($('#price').val()) || 0;
            let taxPercent = parseFloat($('#tax_id option:selected').data('percent')) || 0;
            let taxType = $('#tax_type').val();
            let profitMargin = parseFloat($('#profit_margin').val()) || 0;

            // Purchase Price calculation
            let purchasePrice = price;
            if (taxType === 'Exclusive' && taxPercent > 0) {
                purchasePrice = price + (price * taxPercent / 100);
            }
            $('#purchase_price').val(purchasePrice.toFixed(2));

            // Sales Price calculation
            let salesPrice = price;
            if (profitMargin > 0) {
                salesPrice = price + (price * profitMargin / 100);
            }
            $('#sales_price').val(salesPrice.toFixed(2));

            // MRP calculation - Auto fill with Price
            $('#mrp').val(salesPrice.toFixed(2));
        }

        $('#price, #profit_margin').on('keyup change', calculatePricing);
        $('#tax_id, #tax_type').on('change', calculatePricing);

        // Form Submission
        $('#add-item-form').on('submit', function (e) {
            e.preventDefault();

            var $btn = $('#save-btn');
            var originalText = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-2"></i> Saving...');

            var formData = new FormData(this);

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        $('#success-text').text(response.data.message);
                        $('#success-message').removeClass('hidden').addClass('block');
                        $('#add-item-form')[0].reset();
                        $('#image-preview').addClass('hidden');
                        $('html, body').animate({ scrollTop: 0 }, 'slow');
                        setTimeout(function () {
                            window.location.href = '<?php echo esc_url(add_query_arg('view', 'view-items')); ?>';
                        }, 1500);
                    } else {
                        alert('Error: ' + (response.data.message || 'Unknown error occurred'));
                    }
                },
                error: function () {
                    alert('Server error occurred. Please try again.');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        });
    });
</script>

<?php
include FRONTEND_INVENTORY_PATH . 'templates/modals/modal_brand.php';
include FRONTEND_INVENTORY_PATH . 'templates/modals/modal_category.php';
include FRONTEND_INVENTORY_PATH . 'templates/modals/modal_unit.php';
include FRONTEND_INVENTORY_PATH . 'templates/modals/modal_tax.php';
?>