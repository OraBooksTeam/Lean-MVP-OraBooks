<?php
if (!defined('ABSPATH')) exit;
$nonce = wp_create_nonce('frontend_ajax_nonce');

global $wpdb;
$brands = $wpdb->get_results("SELECT id, brand_name FROM {$wpdb->prefix}orabooks_db_brands WHERE status=1 ORDER BY brand_name ASC");
$categories = $wpdb->get_results("SELECT id, category_name FROM {$wpdb->prefix}orabooks_db_category WHERE status=1 ORDER BY category_name ASC");
$units = $wpdb->get_results("SELECT id, unit_name FROM {$wpdb->prefix}orabooks_db_units WHERE status=1 ORDER BY unit_name ASC");
$taxes = $wpdb->get_results("SELECT id, tax_name, tax FROM {$wpdb->prefix}orabooks_db_tax WHERE status=1 ORDER BY id ASC");
$warehouses = $wpdb->get_results("SELECT id, warehouse_name, warehouse_type FROM {$wpdb->prefix}orabooks_db_warehouse WHERE status=1 ORDER BY warehouse_name ASC");
?>

<!-- Item Modal -->
<div id="item-modal" class="fixed inset-0 z-[10010] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-opacity" aria-hidden="true" id="item-modal-overlay"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full border border-gray-100">
            <form id="item-modal-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="frontend_insert_item">
                <input type="hidden" name="security" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="store_id" value="1">
                <input type="hidden" name="count_id" id="m_count_id" value="">

                <div class="bg-white px-8 py-6">
                    <div class="flex items-center justify-between mb-8 border-b border-gray-100 pb-5">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg mr-4">
                                <i class="fa-solid fa-box-open text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900" id="modal-title">Add New Item</h3>
                                <p class="text-sm text-gray-500 mt-0.5">Quickly create a new inventory item</p>
                            </div>
                        </div>
                        <button type="button" class="w-10 h-10 flex items-center justify-center rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all close-item-modal">
                            <i class="fa-solid fa-times text-xl"></i>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-5">
                        <!-- Section: Basic Info -->
                        <div class="md:col-span-3">
                            <h4 class="text-xs font-bold text-blue-600 uppercase tracking-[0.1em] mb-1">Basic Information</h4>
                            <div class="h-1 w-12 bg-blue-600 rounded-full"></div>
                        </div>
                        
                        <div class="md:col-span-1">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Item Code <span class="text-red-500">*</span></label>
                            <input type="text" name="item_code" id="m_item_code" readonly class="w-full rounded-xl border-gray-200 bg-gray-50 text-gray-500 cursor-not-allowed py-2 px-2.5 font-mono text-sm" placeholder="Generating...">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Item Name <span class="text-red-500">*</span></label>
                            <input type="text" name="item_name" required class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2 px-2.5" placeholder="Enter item name">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Brand</label>
                            <select name="brand_id" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2 px-2.5">
                                <option value="">- Select Brand -</option>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?php echo $brand->id; ?>"><?php echo esc_html($brand->brand_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Category <span class="text-red-500">*</span></label>
                            <select name="category_id" required class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2 px-2.5">
                                <option value="">- Select Category -</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat->id; ?>"><?php echo esc_html($cat->category_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Unit <span class="text-red-500">*</span></label>
                            <select name="unit_id" required class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2 px-2.5">
                                <option value="">- Select Unit -</option>
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?php echo $unit->id; ?>"><?php echo esc_html($unit->unit_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">SKU</label>
                            <input type="text" name="sku" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2 px-2.5" placeholder="Stock Keeping Unit">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">HSN</label>
                            <input type="text" name="hsn" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2 px-2.5" placeholder="HSN Code">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Barcode</label>
                            <input type="text" name="custom_barcode" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2 px-2.5" placeholder="Custom Barcode">
                        </div>

                        <!-- Section: Discount Settings -->
                        <div class="md:col-span-3 mt-4">
                            <h4 class="text-xs font-bold text-amber-600 uppercase tracking-[0.1em] mb-1">Discount Settings</h4>
                            <div class="h-1 w-12 bg-amber-600 rounded-full"></div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Discount Type</label>
                            <select name="discount_type" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2 px-2.5">
                                <option value="Percentage">Percentage (%)</option>
                                <option value="Fixed">Fixed</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Discount Value</label>
                            <input type="number" step="0.01" name="discount" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2 px-2.5" placeholder="0.00">
                        </div>

                        <div class="md:col-span-1"></div>

                        <!-- Section: Pricing -->
                        <div class="md:col-span-3 mt-4">
                            <h4 class="text-xs font-bold text-emerald-600 uppercase tracking-[0.1em] mb-1">Pricing & Tax</h4>
                            <div class="h-1 w-12 bg-emerald-600 rounded-full"></div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Price (Excl. Tax) <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" name="price" id="m_price" required class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2 px-2.5" placeholder="0.00">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Tax <span class="text-red-500">*</span></label>
                            <select name="tax_id" id="m_tax_id" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2 px-2.5">
                                <option value="" data-percent="0">- Select Tax -</option>
                                <?php foreach ($taxes as $tax): ?>
                                    <option value="<?php echo $tax->id; ?>" data-percent="<?php echo esc_attr($tax->tax); ?>"><?php echo esc_html($tax->tax_name); ?> (<?php echo esc_html($tax->tax); ?>%)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Tax Type</label>
                            <select name="tax_type" id="m_tax_type" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2 px-2.5">
                                <option value="Inclusive">Inclusive</option>
                                <option value="Exclusive">Exclusive</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Purchase Price</label>
                            <input type="number" step="0.01" name="purchase_price" id="m_purchase_price" readonly class="w-full rounded-xl border-gray-200 bg-gray-50 text-gray-500 cursor-not-allowed py-2 px-2.5" placeholder="0.00">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Profit Margin (%)</label>
                            <input type="number" step="0.01" name="profit_margin" id="m_profit_margin" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2 px-2.5" placeholder="0.00">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Sales Price</label>
                            <input type="number" step="0.01" name="sales_price" id="m_sales_price" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2 px-2.5" placeholder="0.00">
                        </div>

                        <!-- Section: Stock -->
                        <div class="md:col-span-3 mt-4">
                            <h4 class="text-xs font-bold text-orange-600 uppercase tracking-[0.1em] mb-1">Stock & Others</h4>
                            <div class="h-1 w-12 bg-orange-600 rounded-full"></div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Alert Quantity</label>
                            <input type="number" name="alert_qty" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2 px-2.5" placeholder="0">
                        </div>



                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Opening Stock</label>
                            <input type="number" step="0.01" name="adjustment_qty" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2 px-2.5" placeholder="0.00">
                        </div>

                        <div class="md:col-span-1">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Item Image</label>
                            <input type="file" name="item_image" id="m_item_image" accept="image/*" class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                        </div>

                        <div class="md:col-span-2">
                             <label class="block text-sm font-semibold text-gray-700 mb-1.5">Item Description</label>
                             <textarea name="description" rows="2" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2 px-2.5" placeholder="Additional details..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 px-8 py-5 flex items-center justify-end gap-4 border-t border-gray-100">
                    <button type="button" class="close-item-modal px-6 py-2.5 text-sm font-bold text-gray-600 hover:text-gray-900 border border-gray-200 rounded-xl hover:bg-white hover:shadow-sm transition-all">
                        Cancel
                    </button>
                    <button type="submit" id="save-item-modal-btn" class="px-8 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl hover:from-blue-700 hover:to-indigo-700 text-sm font-bold shadow-lg shadow-blue-500/30 transition-all flex items-center">
                        <i class="fa-solid fa-check-circle mr-2"></i> Save Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
#item-modal {
    z-index: 10010;
}
</style>

<script>
jQuery(document).ready(function($) {
    const modal = $('#item-modal');
    const form = $('#item-modal-form');

    // Open Modal
    window.openItemModal = function() {
        modal.removeClass('hidden');
        $('body').addClass('overflow-hidden');
        generateItemCode();
    };

    // Close Modal
    function closeItemModal() {
        modal.addClass('hidden');
        $('body').removeClass('overflow-hidden');
        form[0].reset();
        $('#m_item_code').val('');
    }

    $('.close-item-modal, #item-modal-overlay').on('click', closeItemModal);

    // Generate Item Code
    function generateItemCode() {
        $.ajax({
            url: '<?php echo admin_url("admin-ajax.php"); ?>',
            type: 'POST',
            data: {
                action: 'generate_item_code',
                security: '<?php echo $nonce; ?>'
            },
            success: function(res) {
                if (res.success) {
                    $('#m_item_code').val(res.data.item_code);
                    $('#m_count_id').val(res.data.count_id);
                }
            }
        });
    }

    // Auto Calculations
    function calculatePricing() {
        let price = parseFloat($('#m_price').val()) || 0;
        let taxPercent = parseFloat($('#m_tax_id option:selected').data('percent')) || 0;
        let taxType = $('#m_tax_type').val();
        let profitMargin = parseFloat($('#m_profit_margin').val()) || 0;

        // Purchase Price
        let purchasePrice = price;
        if (taxType === 'Exclusive' && taxPercent > 0) {
            purchasePrice = price + (price * taxPercent / 100);
        }
        $('#m_purchase_price').val(purchasePrice.toFixed(2));

        // Sales Price
        let salesPrice = price;
        if (profitMargin > 0) {
            salesPrice = price + (price * profitMargin / 100);
        }
        $('#m_sales_price').val(salesPrice.toFixed(2));
    }

    $('#m_price, #m_profit_margin, #m_tax_id, #m_tax_type').on('keyup change', calculatePricing);

    // Form Submission
    form.on('submit', function(e) {
        e.preventDefault();
        const btn = $('#save-item-modal-btn');
        const formData = new FormData(this);

        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Saving...');

        $.ajax({
            url: '<?php echo admin_url("admin-ajax.php"); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                if (res.success) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'success', title: 'Item Added!', text: res.data.message, timer: 1500, showConfirmButton: false });
                    }

                    // Callback to parent page if needed
                    if (window.onItemAdded) {
                        window.onItemAdded(res.data);
                    }

                    closeItemModal();
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.data.message || 'Failed to save' });
                    } else {
                        alert('Error: ' + (res.data.message || 'Failed to save'));
                    }
                }
            },
            error: function() {
                alert('Connection error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fa-solid fa-check-circle mr-2"></i> Save Item');
            }
        });
    });
});
</script>