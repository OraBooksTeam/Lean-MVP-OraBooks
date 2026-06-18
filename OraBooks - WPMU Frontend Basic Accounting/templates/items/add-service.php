<?php
/**
 * Add Service Template
 */
if (!defined('ABSPATH'))
    exit;
global $wpdb;
$categories_table = $wpdb->prefix . 'orabooks_db_category';
$tax_table = $wpdb->prefix . 'orabooks_db_tax';
$categories = $wpdb->get_results("SELECT * FROM {$categories_table} WHERE status = 1 ORDER BY category_name ASC");
$taxes = $wpdb->get_results("SELECT * FROM {$tax_table} WHERE status = 1 ORDER BY tax_name ASC");
?>
<div class="obn-card p-6 !pt-4">
    <?php
    $items_table = $wpdb->prefix . 'orabooks_db_items';
    $max_code = $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(item_code) FROM {$items_table} WHERE service_bit = %d",
        1
    ));
    if ($max_code) {
        // Extract numeric part and increment while preserving prefix
        if (preg_match('/(.*?)(\d+)$/', $max_code, $matches)) {
            $prefix = $matches[1];
            $number = $matches[2];
            $new_number = str_pad(intval($number) + 1, strlen($number), '0', STR_PAD_LEFT);
            $next_service_code = $prefix . $new_number;
        } else {
            $next_service_code = $max_code . '-1';
        }
    } else {
        $next_service_code = 'SER-000001';
    }
    ?>
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-2xl font-bold text-gray-800">Add New Service</h3>
        <a href="#" onclick="showView('obn-view-view-items')"
            class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-xl hover:from-gray-700 hover:to-gray-800 transition-all font-bold text-xs uppercase tracking-widest shadow-lg shadow-gray-200 hover:shadow-gray-300 active:scale-95">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Items
        </a>
    </div>

    <form id="acc-service-add-form" class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-3xl">
        <input type="hidden" name="action" value="obn_insert_item">
        <input type="hidden" name="item_type" value="service">
        <input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('obn_auth_nonce')); ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Service Code <span
                        class="text-red-500">*</span></label>
                <input type="text" name="item_code" id="acc_service_code"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                    placeholder="Auto or manual code" value="<?php echo esc_attr($next_service_code); ?>" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Service Name <span
                        class="text-red-500">*</span></label>
                <input type="text" name="item_name"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                    placeholder="Enter service name" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                <div class="flex gap-2">
                    <select name="category_id"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat->id); ?>"><?php echo esc_html($cat->category_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="openModal('modal_category')"
                        class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg border"><i
                            class="fa-solid fa-plus"></i></button>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Sales Price <span
                        class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0" name="sales_price"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="0.00"
                    required value="0">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Tax</label>
                <div class="flex gap-2">
                    <select name="tax_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">No Tax</option>
                        <?php foreach ($taxes as $tax): ?>
                            <option value="<?php echo esc_attr($tax->id); ?>">
                                <?php echo esc_html($tax->tax_name . ' (' . $tax->tax . '%)'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="openModal('modal_tax')"
                        class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg border"><i
                            class="fa-solid fa-plus"></i></button>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">SKU</label>
                <input type="text" name="sku"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                    placeholder="Stock Keeping Unit">
            </div>
        </div>
        <div class="mt-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
            <textarea name="description" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                rows="3" placeholder="Service description..."></textarea>
        </div>
        <div class="mt-6 flex gap-3">
            <button type="submit" id="acc-service-add-save"
                class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2.5 rounded-lg font-semibold transition-all shadow-md active:scale-95 flex items-center">
                <i class="fa-solid fa-save mr-2"></i> Save Service
            </button>
            <button type="button" onclick="showView('obn-view-view-items')"
                class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-800 px-6 py-2.5 rounded-lg font-semibold transition-colors shadow-sm">Cancel</button>
        </div>
    </form>
</div>

<script>
    jQuery(document).ready(function ($) {
        $('#acc-service-add-form').on('submit', function (e) {
            e.preventDefault();
            var form = $(this);
            var btn = $('#acc-service-add-save');
            btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Saving...');
            $.post(obn_ajax.ajax_url, form.serialize(), function (response) {
                if (response.success) {
                    alert(response.data.message);
                    localStorage.setItem('obn-after-reload-view', 'obn-view-view-items');
                    location.reload();
                } else {
                    alert(response.data || 'Insert failed.');
                    btn.prop('disabled', false).html('<i class="fa-solid fa-save mr-2"></i> Save Service');
                }
            }).fail(function () {
                alert('Request failed.');
                btn.prop('disabled', false).html('<i class="fa-solid fa-save mr-2"></i> Save Service');
            });
        });
    });

</script>