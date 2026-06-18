<?php
if ( ! defined( 'ABSPATH' ) ) exit;
global $wpdb;
$assets_table = $wpdb->prefix . 'orabooks_ac_assets';
$disposal_table = $wpdb->prefix . 'orabooks_ac_asset_disposals';

$disposals = $wpdb->get_results("
    SELECT d.*, a.name as asset_name, a.cost 
    FROM $disposal_table d 
    JOIN $assets_table a ON d.asset_id = a.id 
    ORDER BY d.disposal_date DESC
");
?>

<div class="obn-card p-6 !pt-4">
    <div class="mb-8">
        <h3 class="text-2xl font-bold text-gray-800">Disposal Register</h3>
        <p class="text-gray-500">Log of all assets that have been retired, sold, or scrapped.</p>
    </div>

    <div class="overflow-x-auto bg-white rounded-xl border border-gray-200 shadow-sm">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-4 font-bold text-gray-700 uppercase">Asset</th>
                    <th class="px-6 py-4 font-bold text-gray-700 uppercase">Disposal Date</th>
                    <th class="px-6 py-4 font-bold text-gray-700 uppercase text-right">Original Cost</th>
                    <th class="px-6 py-4 font-bold text-gray-700 uppercase text-right">Sale Price</th>
                    <th class="px-6 py-4 font-bold text-gray-700 uppercase text-right">Gain / Loss</th>
                    <th class="px-6 py-4 font-bold text-gray-700 uppercase">JE Ref</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($disposals): foreach($disposals as $d): 
                    $gain_loss_class = $d->gain_loss >= 0 ? 'text-emerald-600' : 'text-rose-600';
                ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 font-bold text-gray-900"><?php echo esc_html($d->asset_name); ?></td>
                    <td class="px-6 py-4 text-gray-600"><?php echo date('M d, Y', strtotime($d->disposal_date)); ?></td>
                    <td class="px-6 py-4 text-right text-gray-600"><?php echo number_format($d->cost, 2); ?></td>
                    <td class="px-6 py-4 text-right font-medium text-gray-900"><?php echo number_format($d->sale_price, 2); ?></td>
                    <td class="px-6 py-4 text-right font-black <?php echo $gain_loss_class; ?>">
                        <?php echo ($d->gain_loss >= 0 ? '+' : '') . number_format($d->gain_loss, 2); ?>
                    </td>
                    <td class="px-6 py-4">
                        <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs font-mono">JE#<?php echo $d->journal_entry_id; ?></span>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400 italic">No asset disposals found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
