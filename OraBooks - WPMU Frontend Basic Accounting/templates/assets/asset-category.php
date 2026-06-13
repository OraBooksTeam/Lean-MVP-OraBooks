<?php
/**
 * Asset Category Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$nonce = wp_create_nonce('obn_assets_action_nonce');

// Get asset categories
$category_table = $wpdb->prefix . 'orabooks_ac_asset_category';
$categories = $wpdb->get_results("SELECT * FROM $category_table ORDER BY category_name ASC");
?>

<div class="obn-card p-6 !pt-4">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-2xl font-bold text-gray-800">Asset Categories</h3>
            <p class="text-gray-600 mt-1">Manage asset categories and their depreciation settings</p>
        </div>
        <button id="obn-add-category-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
            <i class="fa-solid fa-plus mr-2"></i>Add Category
        </button>
    </div>

    <!-- Category List Table -->
    <div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
        <table id="obn-asset-categories-table" class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Category Code</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Category Name</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Description</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-700 no-export">Status</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($categories): ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr data-id="<?php echo esc_attr($cat->id); ?>" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-800 font-medium">
                                <?php echo esc_html($cat->category_code ?: '-'); ?>
                            </td>
                            <td class="px-4 py-3 text-gray-800">
                                <?php echo esc_html($cat->category_name); ?>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                <?php echo esc_html(substr($cat->description, 0, 50) . (strlen($cat->description) > 50 ? '...' : '')); ?>
                            </td>
                            <td class="px-4 py-3 text-center no-export">
                                <label class="flex items-center justify-center">
                                    <input type="checkbox" class="obn-toggle-asset-category-status"
                                        data-id="<?php echo esc_attr($cat->id); ?>"
                                        data-nonce="<?php echo esc_attr($nonce); ?>" <?php checked($cat->status, 1); ?>
                                        style="width:18px;height:18px;cursor:pointer;">
                                </label>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2 flex justify-end no-export">
                                <button class="obn-edit-category text-blue-600 hover:text-blue-800" 
                                    data-id="<?php echo esc_attr($cat->id); ?>"
                                    data-nonce="<?php echo esc_attr($nonce); ?>">
                                    <i class="fa-solid fa-edit"></i>
                                </button>
                                <button class="obn-delete-category text-red-600 hover:text-red-800"
                                    data-id="<?php echo esc_attr($cat->id); ?>"
                                    data-nonce="<?php echo esc_attr($nonce); ?>">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                            No asset categories found. Click "Add Category" to create one.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Category Modal -->
<div id="obn-category-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-2xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-semibold text-gray-900" id="obn-category-modal-title">Add Asset Category</h3>
            <button id="obn-close-category-modal" class="text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        
        <form id="obn-category-form">
            <input type="hidden" name="action" value="obn_manage_asset_category">
            <input type="hidden" name="security" value="<?php echo $nonce; ?>">
            <input type="hidden" name="category_id" id="obn_category_id">
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category Code</label>
                    <input type="text" name="category_code" id="obn_category_code" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="e.g. COMP_EQUIP">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category Name <span class="text-red-500">*</span></label>
                    <input type="text" name="category_name" id="obn_category_name" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="e.g. Computers & Equipment">
                </div>
            </div>
            
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea name="description" id="obn_category_description" rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Describe this asset category..."></textarea>
            </div>
            
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" id="obn-cancel-category" 
                    class="px-4 py-2 text-gray-700 bg-gray-200 border border-gray-300 rounded-md hover:bg-gray-300">
                    Cancel
                </button>
                <button type="submit" 
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md">
                    Save Category
                </button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Add Category Modal
    $('#obn-add-category-btn').on('click', function() {
        $('#obn-category-modal-title').text('Add Asset Category');
        $('#obn-category-form')[0].reset();
        $('#obn_category_id').val('');
        $('#obn-category-modal').removeClass('hidden').addClass('flex');
    });

    // Close Modal
    $('#obn-close-category-modal, #obn-cancel-category').on('click', function() {
        $('#obn-category-modal').addClass('hidden').removeClass('flex');
    });

    // Edit Category
    $(document).on('click', '.obn-edit-category', function() {
        var btn = $(this);
        var id = btn.data('id');
        var nonce = btn.data('nonce');
        
        $.ajax({
            url: obn_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'obn_get_asset_category',
                id: id,
                security: nonce
            },
            success: function(response) {
                if (response.success) {
                    var cat = response.data;
                    $('#obn-category-modal-title').text('Edit Asset Category');
                    $('#obn_category_id').val(cat.id);
                    $('#obn_category_code').val(cat.category_code);
                    $('#obn_category_name').val(cat.category_name);
                    $('#obn_category_description').val(cat.description);
                    $('#obn-category-modal').removeClass('hidden').addClass('flex');
                } else {
                    alert('Error loading category: ' + response.data);
                }
            },
            error: function() {
                alert('Error loading category data');
            }
        });
    });

    // Save Category
    $('#obn-category-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var originalText = $btn.text();
        
        $btn.prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: obn_ajax.ajax_url,
            type: 'POST',
            data: $form.serialize(),
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });

    // Toggle Status
    $(document).on('change', '.obn-toggle-asset-category-status', function() {
        var checkbox = $(this);
        var id = checkbox.data('id');
        var nonce = checkbox.data('nonce');
        var status = checkbox.is(':checked') ? 1 : 0;
        
        $.ajax({
            url: obn_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'obn_toggle_asset_category_status',
                id: id,
                status: status,
                security: nonce
            },
            success: function(response) {
                console.log('Toggle status response:', response);
                if (response.success) {
                    // Success - show message and reload data
                    console.log('Status updated successfully');
                    alert('Category status updated successfully!');
                    // Optionally reload the page to show updated status
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    console.log('Error updating status:', response.data);
                    alert('Error updating status: ' + (response.data || 'Unknown error'));
                    checkbox.prop('checked', !checkbox.is(':checked'));
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX error:', status, error);
                console.log('Response text:', xhr.responseText);
                alert('AJAX error while updating status. Check console for details.');
                checkbox.prop('checked', !checkbox.is(':checked'));
            }
        });
    });

    // Delete Category
    $(document).on('click', '.obn-delete-category', function() {
        var btn = $(this);
        var id = btn.data('id');
        var nonce = btn.data('nonce');
        
        if (!confirm('Are you sure you want to delete this asset category? This action cannot be undone.')) {
            return;
        }
        
        $.ajax({
            url: obn_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'obn_delete_asset_category',
                id: id,
                security: nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    btn.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error deleting category');
            }
        });
    });
});
</script>
