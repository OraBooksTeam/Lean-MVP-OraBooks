<?php
/**
 * Units Management Template for Accounting
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'orabooks_db_units';
$units = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
$nonce = wp_create_nonce('frontend_ajax_nonce');
?>

<div class="obn-card p-6 !pt-2">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-2xl font-bold text-gray-800">Units Management</h3>
        <button type="button" id="obn-toggle-unit-form"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all shadow-sm flex items-center gap-2">
            <i class="fa-solid fa-plus-circle"></i> Add New Unit
        </button>
    </div>

    <!-- Success Message -->
    <div id="obn-unit-success" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline" id="obn-unit-success-text"></span>
        <button type="button" class="absolute top-3 right-3 text-green-700 hover:text-green-900 focus:outline-none" onclick="this.parentElement.classList.add('hidden');">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Add/Edit Form -->
    <div id="obn-unit-form-section" class="hidden bg-white rounded-lg shadow-md p-6 mb-6 border border-gray-200">
        <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4" id="obn-unit-form-title">Add New Unit</h3>
        
        <form id="obn-unit-form" class="space-y-4">
            <input type="hidden" name="action" value="frontend_save_unit">
            <input type="hidden" name="security" value="<?php echo $nonce; ?>">
            <input type="hidden" name="id" id="obn-unit-id" value="">

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Unit Name <span class="text-red-500">*</span></label>
                <input type="text" name="unit_name" id="obn-unit-name" required class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500 placeholder-gray-400">
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                <textarea name="description" id="obn-unit-desc" rows="3" class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500"></textarea>
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <button type="button" id="obn-unit-cancel-btn" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition-colors text-sm">Cancel</button>
                <button type="submit" id="obn-unit-save-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition-colors text-sm font-bold">
                    <i class="fa fa-save mr-2"></i> Save Unit
                </button>
            </div>
        </form>
    </div>

    <!-- Search & Export Toolbar -->
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <div class="relative w-full md:w-96">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="search" id="obn-units-search" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="Search units...">
        </div>
        <div class="flex items-center gap-2">
            <div class="relative inline-block text-left">
                <button type="button" class="obn-column-toggle-btn inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fa-solid fa-columns mr-2"></i> Columns
                </button>
                <div class="obn-column-dropdown hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                    <div class="py-1 p-3 space-y-2">
                        <?php
                        $unit_cols = ['Name', 'Description', 'Status'];
                        foreach ($unit_cols as $idx => $name): ?>
                        <label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
                            <input type="checkbox" checked class="obn-col-hide form-checkbox h-4 w-4 text-blue-600 rounded" data-column="<?php echo $idx; ?>" data-table="#obn-units-table">
                            <span class="ml-3 text-sm text-gray-700 font-bold uppercase"><?php echo $name; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="flex items-center bg-gray-100 p-1 rounded-lg">
                <button class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-units-table" data-title="Units List" title="Print"><i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span></button>
                <button class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-units-table" data-title="Units List" title="PDF"><i class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span></button>
                <button class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-units-table" data-title="Units List" title="Excel"><i class="fa-solid fa-file-excel mr-1"></i> <span class="hidden sm:inline">Excel</span></button>
                <button class="obn-csv-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-units-table" data-title="Units_List" title="CSV"><i class="fa-solid fa-file-csv mr-1"></i> <span class="hidden sm:inline">CSV</span></button>
            </div>
        </div>
    </div>

    <!-- Units Table -->
    <div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
        <table id="obn-units-table" class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Name</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Description</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-700 no-export">Status</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (!empty($units)): foreach ($units as $unit): ?>
                <tr class="hover:bg-gray-50" data-id="<?php echo esc_attr($unit->id); ?>">
                    <td class="px-4 py-3 text-gray-800 font-bold"><?php echo esc_html($unit->unit_name); ?></td>
                    <td class="px-4 py-3 text-gray-600"><?php echo esc_html($unit->description); ?></td>
                    <td class="px-4 py-3 text-center no-export">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" class="obn-toggle-unit-status sr-only peer" data-id="<?php echo esc_attr($unit->id); ?>" data-status="<?php echo esc_attr($unit->status); ?>" data-nonce="<?php echo $nonce; ?>" <?php echo ($unit->status == 1) ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </td>
                    <td class="px-4 py-3 text-right space-x-2 flex justify-end no-export">
                        <button class="obn-edit-unit-btn px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium transition" 
                            data-id="<?php echo esc_attr($unit->id); ?>"
                            data-name="<?php echo esc_attr($unit->unit_name); ?>"
                            data-desc="<?php echo esc_attr($unit->description); ?>">Edit</button>
                        <button class="obn-delete-unit-btn px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition" data-id="<?php echo esc_attr($unit->id); ?>">Delete</button>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">No units found. Click "Add New Unit" to create one.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Reset form
    function resetUnitForm() {
        $('#obn-unit-form')[0].reset();
        $('#obn-unit-id').val('');
        $('#obn-unit-form-title').text('Add New Unit');
        $('#obn-unit-save-btn').html('<i class="fa fa-save mr-2"></i> Save Unit');
    }

    // Toggle form
    $('#obn-toggle-unit-form').on('click', function() {
        resetUnitForm();
        $('#obn-unit-form-section').removeClass('hidden');
        $('html, body').animate({ scrollTop: 0 }, 'slow');
    });

    $('#obn-unit-cancel-btn').on('click', function() {
        $('#obn-unit-form-section').addClass('hidden');
        resetUnitForm();
    });

    // Edit unit
    $(document).on('click', '.obn-edit-unit-btn', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var desc = $(this).data('desc');

        $('#obn-unit-id').val(id);
        $('#obn-unit-name').val(name);
        $('#obn-unit-desc').val(desc);
        $('#obn-unit-form-title').text('Edit Unit');
        $('#obn-unit-save-btn').html('<i class="fa fa-save mr-2"></i> Update Unit');
        $('#obn-unit-form-section').removeClass('hidden');
        $('html, body').animate({ scrollTop: 0 }, 'slow');
    });

    // Save unit
    $('#obn-unit-form').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize();

        $.post(obn_ajax.ajax_url, formData, function(response) {
            if (response.success) {
                $('#obn-unit-success-text').text(response.data.message);
                $('#obn-unit-success').removeClass('hidden');
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                alert('Error: ' + response.data.message);
            }
        });
    });

    // Delete unit
    $(document).on('click', '.obn-delete-unit-btn', function() {
        if (!confirm('Are you sure you want to delete this unit?')) return;
        var id = $(this).data('id');

        $.post(obn_ajax.ajax_url, {
            action: 'frontend_delete_unit',
            id: id,
            security: '<?php echo $nonce; ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error deleting unit');
            }
        });
    });

    // Toggle status
    $(document).on('change', '.obn-toggle-unit-status', function() {
        var id = $(this).data('id');
        var status = $(this).is(':checked') ? 1 : 0;

        $.post(obn_ajax.ajax_url, {
            action: 'frontend_update_unit_status',
            id: id,
            status: status,
            security: '<?php echo $nonce; ?>'
        });
    });

    // Search
    $('#obn-units-search').on('keyup', function() {
        var value = this.value.toLowerCase();
        $('#obn-units-table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    // Column visibility
    $(document).on('click', '.obn-column-toggle-btn', function(e) {
        e.stopPropagation();
        $(this).next('.obn-column-dropdown').toggleClass('hidden');
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.obn-column-toggle-btn, .obn-column-dropdown').length) {
            $('.obn-column-dropdown').addClass('hidden');
        }
    });

    $('.obn-col-hide').on('change', function() {
        var column = $(this).data('column');
        var isChecked = $(this).is(':checked');
        var table = $(this).data('table');
        $(table + ' thead tr th').eq(column).toggle(isChecked);
        $(table + ' tbody tr').each(function() {
            $(this).find('td').eq(column).toggle(isChecked);
        });
    });
});
</script>
