<?php
if ( ! defined( 'ABSPATH' ) ) exit;
global $wpdb;
$coa_table = $wpdb->prefix . 'orabooks_ac_coa_list';
$methods_table = $wpdb->prefix . 'orabooks_ac_depreciation_methods';
$accounts = $wpdb->get_results("SELECT id, account_name, account_code FROM $coa_table WHERE status = 1 ORDER BY account_name ASC");
$methods = $wpdb->get_results("SELECT id, name, slug FROM $methods_table WHERE status = 1 ORDER BY name ASC");

$nonce = wp_create_nonce('obn_assets_action_nonce');
?>

<div class="obn-card p-6 !pt-4">
    <div class="mb-4">
        <h3 class="text-2xl font-bold text-gray-800">Edit Asset</h3>
    </div>

    <form id="obn-asset-edit-form" class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-4xl">
        <input type="hidden" name="action" value="obn_update_asset">
        <input type="hidden" name="security" value="<?php echo $nonce; ?>">
        <input type="hidden" id="edit_asset_id" name="id" value="">

        <div class="grid grid-cols-2 gap-6">
            <!-- Row 1 -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Asset Name <span class="text-red-500">*</span></label>
                <input type="text" id="edit_asset_name" name="name" class="w-full px-4 py-2 border rounded" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                <select id="edit_asset_category" name="category" class="w-full px-4 py-2 border rounded">
                    <option value="">- Select Asset Category -</option>
                    <?php
                    global $wpdb;
                    $category_table = $wpdb->prefix . 'orabooks_ac_asset_category';
                    $categories = $wpdb->get_results("SELECT id, category_name FROM $category_table WHERE status = 1 ORDER BY category_name ASC");
                    foreach ($categories as $cat):
                    ?>
                        <option value="<?php echo esc_attr($cat->id); ?>">
                            <?php echo esc_html($cat->category_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Row 2 -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Purchase Date <span class="text-red-500">*</span></label>
                <input type="date" id="edit_asset_purchase_date" name="purchase_date" class="w-full px-4 py-2 border rounded" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Cost Value <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" id="edit_asset_cost" name="cost" class="w-full px-4 py-2 border rounded" required>
            </div>

            <!-- Row 3 -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Salvage Value</label>
                <input type="number" step="0.01" id="edit_asset_salvage_value" name="salvage_value" class="w-full px-4 py-2 border rounded">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Useful Life (Years) <span class="text-red-500">*</span></label>
                <input type="number" id="edit_asset_useful_life" name="useful_life_years" class="w-full px-4 py-2 border rounded" required>
            </div>

            <!-- Row 4 -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Depreciation Method</label>
                <select id="edit_asset_method" name="depreciation_method" class="w-full px-4 py-2 border rounded">
                    <?php foreach($methods as $m) echo '<option value="'.$m->slug.'">'.esc_html($m->name).'</option>'; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Asset Account <span class="text-red-500">*</span></label>
                <select id="edit_asset_account_id" name="asset_account_id" class="w-full px-4 py-2 border rounded" required>
                    <option value="">Select Account</option>
                    <?php foreach($accounts as $acc) echo '<option value="'.$acc->id.'">'.esc_html($acc->account_name).'</option>'; ?>
                </select>
            </div>

            <!-- Row 5 -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Depr. Expense Account <span class="text-red-500">*</span></label>
                <select id="edit_asset_depr_expense_id" name="depr_expense_account_id" class="w-full px-4 py-2 border rounded" required>
                    <option value="">Select Account</option>
                    <?php foreach($accounts as $acc) echo '<option value="'.$acc->id.'">'.esc_html($acc->account_name).'</option>'; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Accum. Depr. Account <span class="text-red-500">*</span></label>
                <select id="edit_asset_accum_depr_id" name="accum_depr_account_id" class="w-full px-4 py-2 border rounded" required>
                    <option value="">Select Account</option>
                    <?php foreach($accounts as $acc) echo '<option value="'.$acc->id.'">'.esc_html($acc->account_name).'</option>'; ?>
                </select>
            </div>
        </div>

        <div class="mt-6 flex gap-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Update Asset</button>
            <button type="button" onclick="obn_switch_view('asset-list')" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Cancel</button>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $(document).on('submit', '#obn-asset-edit-form', function(e) {
        e.preventDefault();
        console.log('Submitting asset edit form...');
        
        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        $btn.prop('disabled', true).text('Updating...');

        $.ajax({
            url: obn_ajax.ajax_url,
            type: 'POST',
            data: $form.serialize(),
            success: function(res) {
                console.log('Response:', res);
                if(res.success) {
                    alert(res.data.message);
                    window.location.hash = 'view=asset-list';
                    location.reload();
                } else {
                    alert('Error: ' + res.data);
                    $btn.prop('disabled', false).text('Update Asset');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('An error occurred during update. Check console for details.');
                $btn.prop('disabled', false).text('Update Asset');
            }
        });
    });
});
</script>
