<?php
if ( ! defined( 'ABSPATH' ) ) exit;
global $wpdb;
$assets_table = $wpdb->prefix . 'orabooks_ac_assets';
$depr_table = $wpdb->prefix . 'orabooks_ac_depreciation_records';
$coa_table = $wpdb->prefix . 'orabooks_ac_coa_list';
$category_table = $wpdb->prefix . 'orabooks_ac_asset_category';

$assets = $wpdb->get_results("
    SELECT a.*, 
           c.category_name,
           COALESCE((SELECT SUM(depreciation_amount) FROM $depr_table WHERE asset_id = a.id), 0) as accum_depr
    FROM $assets_table a 
    LEFT JOIN $category_table c ON a.category = c.id
    WHERE a.status = 'Active' 
    ORDER BY a.id DESC
");

$nonce = wp_create_nonce('obn_assets_action_nonce');
?>

<div class="obn-card p-6 !pt-4">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-2xl font-bold text-gray-800">Asset Register</h3>
            <p class="text-gray-500 text-sm">Track and manage your fixed assets and depreciation.</p>
        </div>
        <button onclick="obn_switch_view('asset-add')" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg shadow-md transition-all flex items-center gap-2">
            <i class="fa-solid fa-plus"></i> New Asset
        </button>
    </div>

    <!-- Summary Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <?php
        $total_cost = 0; $total_accum = 0;
        foreach($assets as $as) { $total_cost += $as->cost; $total_accum += $as->accum_depr; }
        ?>
        <div class="bg-blue-50 border border-blue-100 p-4 rounded-xl">
            <p class="text-blue-600 text-xs font-bold uppercase">Total Asset Cost</p>
            <h4 class="text-2xl font-bold text-blue-900"><?php echo number_format($total_cost, 2); ?></h4>
        </div>
        <div class="bg-emerald-50 border border-emerald-100 p-4 rounded-xl">
            <p class="text-emerald-600 text-xs font-bold uppercase">Accumulated Depreciation</p>
            <h4 class="text-2xl font-bold text-emerald-900"><?php echo number_format($total_accum, 2); ?></h4>
        </div>
        <div class="bg-indigo-50 border border-indigo-100 p-4 rounded-xl">
            <p class="text-indigo-600 text-xs font-bold uppercase">Net Book Value</p>
            <h4 class="text-2xl font-bold text-indigo-900"><?php echo number_format($total_cost - $total_accum, 2); ?></h4>
        </div>
    </div>

    <div class="overflow-x-auto bg-white rounded-xl border border-gray-200">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider">Asset Name</th>
                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider">Category</th>
                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider">Purchase Date</th>
                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-right">Cost</th>
                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-right">Accum. Depr</th>
                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-right">NBV</th>
                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($assets): foreach($assets as $asset): 
                    $nbv = $asset->cost - $asset->accum_depr;
                ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="font-bold text-gray-900"><?php echo esc_html($asset->name); ?></div>
                        <div class="text-xs text-gray-400">ID: #<?php echo $asset->id; ?></div>
                    </td>
                    <td class="px-6 py-4 text-gray-600"><?php echo esc_html(!empty($asset->category_name) ? $asset->category_name : $asset->category); ?></td>
                    <td class="px-6 py-4 text-gray-600"><?php echo date('M d, Y', strtotime($asset->purchase_date)); ?></td>
                    <td class="px-6 py-4 text-right font-medium text-gray-900"><?php echo number_format($asset->cost, 2); ?></td>
                    <td class="px-6 py-4 text-right text-rose-500"><?php echo number_format($asset->accum_depr, 2); ?></td>
                    <td class="px-6 py-4 text-right font-bold text-emerald-600"><?php echo number_format($nbv, 2); ?></td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex justify-center gap-2">
                             <button onclick="obn_run_depreciation(<?php echo $asset->id; ?>, '<?php echo $nonce; ?>')" class="text-blue-600 hover:text-blue-800 p-1 rounded hover:bg-blue-50" title="Depreciate">
                                <i class="fa-solid fa-calculator"></i>
                             </button>
                             <button onclick="obn_edit_asset(<?php echo $asset->id; ?>, '<?php echo $nonce; ?>')" class="text-indigo-600 hover:text-indigo-800 p-1 rounded hover:bg-indigo-50" title="Edit">
                                <i class="fa-solid fa-pen-to-square"></i>
                             </button>
                             <button onclick="obn_show_dispose_modal(<?php echo $asset->id; ?>, '<?php echo esc_js($asset->name); ?>', <?php echo $nbv; ?>)" class="text-orange-600 hover:text-orange-800 p-1 rounded hover:bg-orange-50" title="Dispose">
                                <i class="fa-solid fa-trash-arrow-up"></i>
                             </button>
                             <button onclick="obn_delete_asset(<?php echo $asset->id; ?>, '<?php echo $nonce; ?>')" class="text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50" title="Delete">
                                <i class="fa-solid fa-trash"></i>
                             </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="7" class="px-6 py-10 text-center text-gray-400 italic">No assets registered yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Disposal Modal -->
<div id="obn-dispose-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-[9999] flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 relative">
        <h3 class="text-xl font-bold text-gray-800 mb-2">Dispose Asset</h3>
        <p id="dispose-asset-name" class="text-gray-500 mb-4 font-medium"></p>
        
        <form id="obn-asset-dispose-form">
            <input type="hidden" name="action" value="obn_dispose_asset">
            <input type="hidden" name="security" value="<?php echo $nonce; ?>">
            <input type="hidden" id="dispose_asset_id" name="asset_id">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Disposal Date</label>
                    <input type="date" name="disposal_date" value="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border rounded" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Sale Price / Scrap Value</label>
                    <input type="number" step="0.01" name="sale_price" class="w-full px-3 py-2 border rounded" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Proceeds Cash/Bank Account</label>
                    <select name="cash_account_id" class="w-full px-3 py-2 border rounded" required>
                        <option value="">Select Account</option>
                        <?php 
                        $accounts = $wpdb->get_results("SELECT id, account_name FROM $coa_table WHERE status = 1 ORDER BY account_name ASC");
                        foreach($accounts as $acc) echo '<option value="'.$acc->id.'">'.esc_html($acc->account_name).'</option>';
                        ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Gain/Loss Account</label>
                    <select name="gain_loss_account_id" class="w-full px-3 py-2 border rounded" required>
                        <option value="">Select Account</option>
                        <?php foreach($accounts as $acc) echo '<option value="'.$acc->id.'">'.esc_html($acc->account_name).'</option>'; ?>
                    </select>
                </div>
            </div>
            
            <div class="flex gap-2 mt-6">
                <button type="submit" class="flex-1 bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 rounded shadow">Process Disposal</button>
                <button type="button" onclick="document.getElementById('obn-dispose-modal').classList.add('hidden')" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold py-2 rounded transition">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    window.obn_show_dispose_modal = function(id, name, nbv) {
        document.getElementById('dispose_asset_id').value = id;
        document.getElementById('dispose-asset-name').innerText = name + ' (NBV: ' + nbv.toFixed(2) + ')';
        document.getElementById('obn-dispose-modal').classList.remove('hidden');
    }

    window.obn_run_depreciation = function(id, nonce) {
        if(!confirm('Record monthly depreciation for this asset?')) return;
        const period = prompt('Enter period date (YYYY-MM-DD)', '<?php echo date('Y-m-t'); ?>');
        if(!period) return;

        $.ajax({
            url: obn_ajax.ajax_url, type: 'POST',
            data: { action: 'obn_run_depreciation', security: nonce, asset_id: id, period_date: period },
            success: function(res) {
                if(res.success) { alert(res.data.message); window.location.hash = 'view=asset-list'; location.reload(); } else { alert(res.data); }
            }
        });
    }

    window.obn_edit_asset = function(id, nonce) {
        $.ajax({
            url: obn_ajax.ajax_url, type: 'POST',
            data: { action: 'obn_get_asset', security: nonce, id: id },
            success: function(res) {
                if(res.success) {
                    const a = res.data;
                    $('#edit_asset_id').val(a.id);
                    $('#edit_asset_name').val(a.name);
                    $('#edit_asset_category').val(a.category);
                    $('#edit_asset_purchase_date').val(a.purchase_date);
                    $('#edit_asset_cost').val(a.cost);
                    $('#edit_asset_salvage_value').val(a.salvage_value);
                    $('#edit_asset_useful_life').val(a.useful_life_years);
                    $('#edit_asset_method').val(a.depreciation_method);
                    $('#edit_asset_account_id').val(a.asset_account_id);
                    $('#edit_asset_depr_expense_id').val(a.depr_expense_account_id);
                    $('#edit_asset_accum_depr_id').val(a.accum_depr_account_id);
                    obn_switch_view('asset-edit');
                } else { alert(res.data); }
            }
        });
    }

    window.obn_delete_asset = function(id, nonce) {
        if(!confirm('Are you sure you want to delete this asset?')) return;
        $.ajax({
            url: obn_ajax.ajax_url, type: 'POST',
            data: { action: 'obn_delete_asset', security: nonce, id: id },
            success: function(res) {
                if(res.success) { alert(res.data.message); window.location.hash = 'view=asset-list'; location.reload(); } else { alert(res.data); }
            }
        });
    }

    $(document).on('submit', '#obn-asset-dispose-form', function(e) {
        e.preventDefault();
        if(!confirm('Process asset disposal?')) return;
        
        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        $btn.prop('disabled', true).text('Processing...');

        $.ajax({
            url: obn_ajax.ajax_url, 
            type: 'POST',
            data: $form.serialize(),
            success: function(res) {
                if(res.success) { 
                    alert(res.data.message); 
                    window.location.hash = 'view=asset-list'; 
                    location.reload(); 
                } else { 
                    alert(res.data); 
                    $btn.prop('disabled', false).text('Process Disposal');
                }
            },
            error: function() {
                alert('An error occurred during disposal.');
                $btn.prop('disabled', false).text('Process Disposal');
            }
        });
    });
});
</script>
