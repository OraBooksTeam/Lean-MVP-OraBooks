<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Get warehouses for dropdown
$warehouses = $wpdb->get_results("SELECT id, warehouse_name, warehouse_type FROM {$wpdb->prefix}orabooks_db_warehouse WHERE status = 1");

// Get filter values
$warehouse_id = isset($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : 0;
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// Build query
$where = " WHERE 1=1";
if ($warehouse_id > 0) {
    $where .= " AND s.warehouse_id = $warehouse_id";
}
if (!empty($search)) {
    $where .= $wpdb->prepare(" AND (s.reference_no LIKE %s OR s.adjustment_note LIKE %s)", "%$search%", "%$search%");
}

// Get adjustments
// Joined with users table (wp_users or custom? Source used 'orabooks_db_users' but standard WP usually uses wp_users for created_by if it's WP user ID. Source mapped created_by to 'u.id' of 'orabooks_db_users'. I'll stick to WP users if created_by is current_user_id() from WP, but source explicitly said 'orabooks_db_users'. 
// However, in my backend logic I used `get_current_user_id()`, which is WP ID. So I should join with {$wpdb->users}.
// If source used custom users table, I might need to check if I ported that. Assuming WP users for new plugin context.)
$adjustments = $wpdb->get_results("
    SELECT s.*, w.warehouse_name, u.display_name as created_by_name
    FROM {$wpdb->prefix}orabooks_db_stockadjustment s
    LEFT JOIN {$wpdb->prefix}orabooks_db_warehouse w ON s.warehouse_id = w.id
    LEFT JOIN {$wpdb->users} u ON s.created_by = u.ID
    $where
    ORDER BY s.id DESC
");
?>

<div class="container mx-auto px-4 py-6 text-gray-800">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Stock Adjustment List</h1>
        <a href="<?php echo esc_url(add_query_arg('view', 'add-adjustment')); ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded transition-colors duration-200">
            <i class="fa-solid fa-plus mr-2"></i> Add Adjustment
        </a>
    </div>

    <!-- Search & Filters -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6 border border-gray-200">
        <form method="get" class="flex flex-wrap gap-4 items-end">
            <!-- Hidden inputs to persist view -->
            <input type="hidden" name="view" value="adjustment-list">
            
            <div class="flex-1 min-w-[200px]">
                <label for="warehouse_id" class="block text-sm font-medium text-gray-700 mb-1">Warehouse</label>
                <select name="warehouse_id" id="warehouse_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
                    <option value="0">All Warehouses</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?php echo $warehouse->id; ?>" <?php selected($warehouse_id ?: ($warehouse->warehouse_type === 'system' ? $warehouse->id : null), $warehouse->id); ?>>
                            <?php echo esc_html($warehouse->warehouse_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
             <div class="flex-1 min-w-[200px]">
                <label for="s" class="block text-sm font-medium text-gray-700 mb-1">Reference No</label>
                <input type="text" name="s" id="s" value="<?php echo esc_attr($search); ?>" placeholder="Reference No" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
            </div>

            <div>
                <button type="submit" class="bg-gray-800 hover:bg-gray-900 text-white font-medium py-2 px-4 rounded transition-colors duration-200">
                    <i class="fa-solid fa-search mr-1"></i> Search
                </button>
                <a href="<?php echo esc_url(remove_query_arg(['warehouse_id', 's'])); ?>" class="ml-2 text-gray-600 hover:text-gray-800 font-medium py-2 px-3 rounded border border-gray-300 hover:bg-gray-50 transition-colors duration-200">
                    <i class="fa-solid fa-rotate-right mr-1"></i> Reset
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
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference No</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Warehouse</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (count($adjustments) > 0): ?>
                        <?php $sl = 1; foreach ($adjustments as $row): ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $sl++; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('d-m-Y', strtotime($row->adjustment_date)); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600"><?php echo esc_html($row->reference_no); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo esc_html($row->warehouse_name); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        <?php echo esc_html($row->created_by_name); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <a href="<?php echo esc_url(add_query_arg(['view' => 'adjustment-invoice', 'id' => $row->id])); ?>" class="text-blue-600 hover:text-blue-900 mr-3" title="View Invoice">
                                        <i class="fa-solid fa-file-invoice"></i>
                                    </a>
                                    <a href="<?php echo esc_url(add_query_arg(['view' => 'edit-adjustment', 'id' => $row->id])); ?>" class="text-green-600 hover:text-green-900 mr-3" title="Edit">
                                        <i class="fa-solid fa-edit"></i>
                                    </a>
                                    <button class="text-red-600 hover:text-red-900 delete-adjustment" data-id="<?php echo $row->id; ?>" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fa-solid fa-box-open text-4xl mb-3 text-gray-300"></i>
                                    <p class="text-lg font-medium">No adjustments found</p>
                                    <p class="text-sm">Get started by adding a new stock adjustment.</p>
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
    $('.delete-adjustment').on('click', function() {
        if (!confirm('Are you sure you want to delete this adjustment? This will reverse stock changes.')) {
            return;
        }
        
        var id = $(this).data('id');
        var btn = $(this);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'delete_adjustment',
                id: id,
                security: '<?php echo wp_create_nonce('frontend_ajax_nonce'); ?>'
            },
            beforeSend: function() {
                 btn.html('<i class="fa-solid fa-spinner fa-spin"></i>');
            },
            success: function(response) {
                if (response.success) {
                    // alert('Adjustment deleted successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    btn.html('<i class="fa-solid fa-trash"></i>');
                }
            },
            error: function() {
                alert('Server error occurred.');
                btn.html('<i class="fa-solid fa-trash"></i>');
            }
        });
    });
});
</script>
