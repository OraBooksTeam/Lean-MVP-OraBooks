<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Get warehouses for filters
$warehouses = $wpdb->get_results("SELECT id, warehouse_name, warehouse_type FROM {$wpdb->prefix}orabooks_db_warehouse WHERE status = 1");

// Filters
$from_id = isset($_GET['from_id']) ? intval($_GET['from_id']) : 0;
$to_id = isset($_GET['to_id']) ? intval($_GET['to_id']) : 0;
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : ''; // Search note or system_name? Transfers don't have ref no.

$where = " WHERE 1=1";
if ($from_id > 0) $where .= " AND t.warehouse_from = $from_id";
if ($to_id > 0) $where .= " AND t.warehouse_to = $to_id";
if (!empty($search)) $where .= $wpdb->prepare(" AND t.note LIKE %s", "%$search%");

$transfers = $wpdb->get_results("
    SELECT t.*, 
           w1.warehouse_name as from_warehouse, 
           w2.warehouse_name as to_warehouse,
           u.display_name as created_by_name
    FROM {$wpdb->prefix}orabooks_db_stocktransfer t
    LEFT JOIN {$wpdb->prefix}orabooks_db_warehouse w1 ON t.warehouse_from = w1.id
    LEFT JOIN {$wpdb->prefix}orabooks_db_warehouse w2 ON t.warehouse_to = w2.id
    LEFT JOIN {$wpdb->users} u ON t.created_by = u.ID
    $where
    ORDER BY t.created_date DESC, t.created_time DESC
");

?>
<div class="container mx-auto px-4 py-6 text-gray-800">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Stock Transfer List</h1>
        <a href="<?php echo esc_url(add_query_arg('view', 'add-transfer')); ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded transition-colors duration-200">
            <i class="fa-solid fa-plus mr-2"></i> New Transfer
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6 border border-gray-200">
        <form method="get" class="flex flex-wrap gap-4 items-end">
            <input type="hidden" name="view" value="transfer-list">
            
            <div class="flex-1 min-w-[200px]">
                <label for="from_id" class="block text-sm font-medium text-gray-700 mb-1">From Warehouse</label>
                <select name="from_id" id="from_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
                    <option value="0">All</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?php echo $w->id; ?>" <?php selected($from_id ?: ($w->warehouse_type === 'system' ? $w->id : null), $w->id); ?>><?php echo esc_html($w->warehouse_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex-1 min-w-[200px]">
                <label for="to_id" class="block text-sm font-medium text-gray-700 mb-1">To Warehouse</label>
                <select name="to_id" id="to_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
                    <option value="0">All</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?php echo $w->id; ?>" <?php selected($to_id, $w->id); ?>><?php echo esc_html($w->warehouse_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="flex-1 min-w-[200px]">
                 <label for="s" class="block text-sm font-medium text-gray-700 mb-1">Search Note</label>
                 <input type="text" name="s" id="s" value="<?php echo esc_attr($search); ?>" placeholder="Search..." class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
            </div>

            <div>
                <button type="submit" class="bg-gray-800 hover:bg-gray-900 text-white font-medium py-2 px-4 rounded transition-colors duration-200">
                    <i class="fa-solid fa-filter mr-1"></i> Filter
                </button>
                 <a href="<?php echo esc_url(remove_query_arg(['from_id', 'to_id', 's'])); ?>" class="ml-2 text-gray-600 hover:text-gray-800 font-medium py-2 px-3 rounded border border-gray-300 hover:bg-gray-50 transition-colors duration-200">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden border border-gray-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From Warehouse</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">To Warehouse</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Items</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (count($transfers) > 0): ?>
                        <?php foreach ($transfers as $row): 
                            // Get items count
                            $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}orabooks_db_stocktransferitems WHERE stocktransfer_id = %d", $row->id));
                        ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('d-m-Y', strtotime($row->transfer_date)); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo esc_html($row->from_warehouse); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo esc_html($row->to_warehouse); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600"><?php echo $count; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo esc_html($row->created_by_name); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="<?php echo esc_url(add_query_arg(['view' => 'transfer-invoice', 'id' => $row->id])); ?>" class="text-blue-600 hover:text-blue-900 mr-3" title="View Invoice">
                                        <i class="fa-solid fa-file-invoice"></i>
                                    </a>
                                    <a href="<?php echo esc_url(add_query_arg(['view' => 'edit-transfer', 'id' => $row->id])); ?>" class="text-green-600 hover:text-green-900 mr-3" title="Edit">
                                        <i class="fa-solid fa-edit"></i>
                                    </a>
                                    <button class="text-red-600 hover:text-red-900 delete-transfer" data-id="<?php echo $row->id; ?>" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                         <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fa-solid fa-truck-moving text-4xl mb-3 text-gray-300"></i>
                                    <p class="text-lg font-medium">No stock transfers found</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
jQuery(document).ready(function($) {
    $('.delete-transfer').on('click', function() {
        if (!confirm('Are you sure? This will reverse the stock transfer.')) return;
        var id = $(this).data('id');
        var btn = $(this);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'delete_transfer', id: id, security: '<?php echo wp_create_nonce('frontend_ajax_nonce'); ?>' },
             beforeSend: function() { btn.html('<i class="fa-solid fa-spinner fa-spin"></i>'); },
            success: function(r) {
                if (r.success) location.reload();
                else alert(r.data);
            }
        });
    });
});
</script>
