<?php
/**
 * Brands Management Template for Accounting
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'orabooks_db_brands';
$brands = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
$nonce = wp_create_nonce('frontend_ajax_nonce');
?>

<div class="obn-card p-6 !pt-2">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-2xl font-bold text-gray-800">Brands Management</h3>
        <button type="button" id="obn-toggle-brand-form"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all shadow-sm flex items-center gap-2">
            <i class="fa-solid fa-plus-circle"></i> Add New Brand
        </button>
    </div>

    <!-- Success Message -->
    <div id="obn-brand-success" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline" id="obn-brand-success-text"></span>
        <button type="button" class="absolute top-3 right-3 text-green-700 hover:text-green-900 focus:outline-none" onclick="this.parentElement.classList.add('hidden');">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Add New Brand Form (Bulk Multi-Row) -->
    <div id="obn-brand-form-section" class="hidden bg-white rounded-lg shadow-md p-6 mb-6 border border-gray-200">
        <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4" id="obn-brand-form-title">Add New Brands</h3>

        <!-- Bulk insert error -->
        <div id="obn-brand-bulk-error" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline" id="obn-brand-bulk-error-text"></span>
            <button type="button" class="absolute top-3 right-3 text-red-700 hover:text-red-900 focus:outline-none" onclick="this.parentElement.classList.add('hidden');">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="obn-brand-form">
            <input type="hidden" name="action" value="frontend_bulk_save_brands">
            <input type="hidden" name="security" value="<?php echo $nonce; ?>">

            <!-- Brand rows container -->
            <div id="obn-brand-rows">
                <div class="obn-brand-row grid grid-cols-1 md:grid-cols-12 gap-3 items-end mb-3 pb-3 border-b border-gray-100">
                    <div class="md:col-span-3">
                        <label class="block text-gray-700 text-xs font-bold mb-1">Code</label>
                        <input type="text" name="brands[0][brand_code]" class="obn-brand-code w-full bg-gray-100 text-gray-700 border border-gray-300 rounded py-2 px-3 text-sm focus:outline-none cursor-not-allowed" readonly>
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-gray-700 text-xs font-bold mb-1">Brand Name <span class="text-red-500">*</span></label>
                        <input type="text" name="brands[0][brand_name]" class="obn-brand-name w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 text-sm focus:outline-none focus:border-blue-500 placeholder-gray-400" placeholder="Enter brand name">
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-gray-700 text-xs font-bold mb-1">Description</label>
                        <input type="text" name="brands[0][description]" class="obn-brand-desc w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 text-sm focus:outline-none focus:border-blue-500 placeholder-gray-400" placeholder="Optional description">
                    </div>
                    <div class="md:col-span-1 flex items-end justify-center">
                        <button type="button" class="obn-remove-row-btn bg-red-500 hover:bg-red-600 text-white rounded-lg p-2 transition-colors text-sm hidden" title="Remove Row">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Add Row Button -->
            <div class="flex justify-start mt-2 mb-4">
                <button type="button" id="obn-add-brand-row" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-plus-circle"></i> Add Row +
                </button>
            </div>

            <div class="flex justify-end gap-2 mt-4 pt-3 border-t border-gray-200">
                <button type="button" id="obn-brand-cancel-btn" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition-colors text-sm">Cancel</button>
                <button type="submit" id="obn-brand-save-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded transition-colors text-sm font-bold">
                    <i class="fa fa-save mr-2"></i> Save All Brands
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
            <input type="search" id="obn-brands-search" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="Search brands...">
        </div>
        <div class="flex items-center gap-2">
            <div class="relative inline-block text-left">
                <button type="button" class="obn-column-toggle-btn inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fa-solid fa-columns mr-2"></i> Columns
                </button>
                <div class="obn-column-dropdown hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                    <div class="py-1 p-3 space-y-2">
                        <?php
                        $brand_cols = ['Code', 'Name', 'Description', 'Status'];
                        foreach ($brand_cols as $idx => $name): ?>
                        <label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
                            <input type="checkbox" checked class="obn-col-hide form-checkbox h-4 w-4 text-blue-600 rounded" data-column="<?php echo $idx; ?>" data-table="#obn-brands-table">
                            <span class="ml-3 text-sm text-gray-700 font-bold uppercase"><?php echo $name; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="flex items-center bg-gray-100 p-1 rounded-lg">
                <button class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-brands-table" data-title="Brands List" title="Print"><i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span></button>
                <button class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-brands-table" data-title="Brands List" title="PDF"><i class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span></button>
                <button class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-brands-table" data-title="Brands List" title="Excel"><i class="fa-solid fa-file-excel mr-1"></i> <span class="hidden sm:inline">Excel</span></button>
                <button class="obn-csv-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-brands-table" data-title="Brands_List" title="CSV"><i class="fa-solid fa-file-csv mr-1"></i> <span class="hidden sm:inline">CSV</span></button>
            </div>
        </div>
    </div>

    <!-- Brands Table -->
    <div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
        <table id="obn-brands-table" class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Code</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Name</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Description</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-700 no-export">Status</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (!empty($brands)): foreach ($brands as $brand): ?>
                <tr class="hover:bg-gray-50" data-id="<?php echo esc_attr($brand->id); ?>">
                    <td class="px-4 py-3 text-gray-800 font-medium"><?php echo esc_html($brand->brand_code); ?></td>
                    <td class="px-4 py-3 text-gray-800 font-bold"><?php echo esc_html($brand->brand_name); ?></td>
                    <td class="px-4 py-3 text-gray-600"><?php echo esc_html($brand->description); ?></td>
                    <td class="px-4 py-3 text-center no-export">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" class="obn-toggle-brand-status sr-only peer" data-id="<?php echo esc_attr($brand->id); ?>" data-status="<?php echo esc_attr($brand->status); ?>" data-nonce="<?php echo $nonce; ?>" <?php echo ($brand->status == 1) ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </td>
                    <td class="px-4 py-3 text-right space-x-2 flex justify-end no-export">
                        <button class="obn-edit-brand-btn px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium transition" 
                            data-id="<?php echo esc_attr($brand->id); ?>"
                            data-code="<?php echo esc_attr($brand->brand_code); ?>"
                            data-name="<?php echo esc_attr($brand->brand_name); ?>"
                            data-desc="<?php echo esc_attr($brand->description); ?>">Edit</button>
                        <button class="obn-delete-brand-btn px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition" data-id="<?php echo esc_attr($brand->id); ?>">Delete</button>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">No brands found. Click "Add New Brand" to create one.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var brandRowIdx = 0; // next index for brand rows
    var brandCodeSeq = 0; // client-side counter for incrementing brand codes
    var baseCodePrefix = 'IBRAND-';

    // --- Generate a brand code (client-side, no AJAX race condition) ---
    function nextBrandCode() {
        brandCodeSeq++;
        return baseCodePrefix + String(brandCodeSeq).padStart(5, '0');
    }

    // --- Fetch the starting sequence from the server (once) ---
    function initBrandCodeSequence(callback) {
        $.post(obn_ajax.ajax_url, {
            action: 'frontend_generate_brand_code',
            security: '<?php echo $nonce; ?>'
        }, function(res) {
            if (res.success && res.data.code) {
                var parts = res.data.code.split('-');
                var num = parseInt(parts[1], 10);
                if (!isNaN(num)) {
                    brandCodeSeq = num - 1; // so nextBrandCode() returns the server's number first
                }
            }
            callback();
        });
    }

    // --- Create a brand row HTML with given index ---
    function createBrandRow(idx) {
        var removeBtnClass = brandRowIdx > 0 ? '' : ' hidden';
        return '<div class="obn-brand-row grid grid-cols-1 md:grid-cols-12 gap-3 items-end mb-3 pb-3 border-b border-gray-100" data-row-idx="' + idx + '">' +
            '<div class="md:col-span-3">' +
                '<label class="block text-gray-700 text-xs font-bold mb-1">Code</label>' +
                '<input type="text" name="brands[' + idx + '][brand_code]" class="obn-brand-code w-full bg-gray-100 text-gray-700 border border-gray-300 rounded py-2 px-3 text-sm focus:outline-none cursor-not-allowed" readonly>' +
            '</div>' +
            '<div class="md:col-span-4">' +
                '<label class="block text-gray-700 text-xs font-bold mb-1">Brand Name <span class="text-red-500">*</span></label>' +
                '<input type="text" name="brands[' + idx + '][brand_name]" class="obn-brand-name w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 text-sm focus:outline-none focus:border-blue-500 placeholder-gray-400" placeholder="Enter brand name">' +
            '</div>' +
            '<div class="md:col-span-4">' +
                '<label class="block text-gray-700 text-xs font-bold mb-1">Description</label>' +
                '<input type="text" name="brands[' + idx + '][description]" class="obn-brand-desc w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 text-sm focus:outline-none focus:border-blue-500 placeholder-gray-400" placeholder="Optional description">' +
            '</div>' +
            '<div class="md:col-span-1 flex items-end justify-center">' +
                '<button type="button" class="obn-remove-row-btn bg-red-500 hover:bg-red-600 text-white rounded-lg p-2 transition-colors text-sm' + removeBtnClass + '" title="Remove Row">' +
                    '<i class="fa-solid fa-trash-can"></i>' +
                '</button>' +
            '</div>' +
        '</div>';
    }

    // --- Toggle remove button visibility based on row count ---
    function refreshRemoveButtons() {
        var totalRows = $('#obn-brand-rows .obn-brand-row').length;
        if (totalRows > 1) {
            $('#obn-brand-rows .obn-remove-row-btn').removeClass('hidden');
        } else {
            $('#obn-brand-rows .obn-remove-row-btn').addClass('hidden');
        }
    }

    // --- Reset form to single empty row ---
    function resetBrandForm() {
        brandRowIdx = 0;
        $('#obn-brand-rows').html(createBrandRow(0));
        $('#obn-brand-form-title').text('Add New Brands');
        $('#obn-brand-save-btn').html('<i class="fa fa-save mr-2"></i> Save All Brands');
        $('#obn-brand-bulk-error').addClass('hidden');
        $('#obn-add-brand-row').show();
        // Remove any edit-mode hidden field
        $('#obn-brand-edit-id').remove();
    }

    // --- Toggle form visibility (add new) ---
    $('#obn-toggle-brand-form').on('click', function() {
        resetBrandForm();
        $('#obn-brand-form-section').removeClass('hidden');
        // Fetch starting sequence from server, then fill first row code
        initBrandCodeSequence(function() {
            $('#obn-brand-rows .obn-brand-row:first .obn-brand-code').val(nextBrandCode());
        });
        $('html, body').animate({ scrollTop: 0 }, 'slow');
    });

    $('#obn-brand-cancel-btn').on('click', function() {
        $('#obn-brand-form-section').addClass('hidden');
        resetBrandForm();
    });

    // --- Edit brand (populate first row with existing data) ---
    $(document).on('click', '.obn-edit-brand-btn', function() {
        var id   = $(this).data('id');
        var code = $(this).data('code');
        var name = $(this).data('name');
        var desc = $(this).data('desc');

        resetBrandForm();
        $('#obn-brand-form-title').text('Edit Brand');
        $('#obn-brand-save-btn').html('<i class="fa fa-save mr-2"></i> Update Brand');

        // Hide multi-row controls when editing a single brand
        $('#obn-add-brand-row').hide();
        $('.obn-remove-row-btn').addClass('hidden');

        var $firstRow = $('#obn-brand-rows .obn-brand-row:first');
        $firstRow.find('.obn-brand-code').val(code);
        $firstRow.find('.obn-brand-name').val(name);
        $firstRow.find('.obn-brand-desc').val(desc);

        // Inject hidden edit fields into the form
        if (!$('#obn-brand-edit-id').length) {
            $('#obn-brand-form').append('<input type="hidden" id="obn-brand-edit-id" name="brand_id" value="' + id + '">');
        } else {
            $('#obn-brand-edit-id').val(id);
        }

        $('#obn-brand-form-section').removeClass('hidden');
        $('html, body').animate({ scrollTop: 0 }, 'slow');
    });

    // --- Add Row button ---
    $('#obn-add-brand-row').on('click', function() {
        brandRowIdx++;
        var newIdx = brandRowIdx;
        $('#obn-brand-rows').append(createBrandRow(newIdx));
        refreshRemoveButtons();
        // Generate code client-side (no AJAX needed)
        $('#obn-brand-rows .obn-brand-row[data-row-idx="' + newIdx + '"] .obn-brand-code').val(nextBrandCode());
        // Focus the new brand name input
        $('#obn-brand-rows .obn-brand-row[data-row-idx="' + newIdx + '"] .obn-brand-name').focus();
    });

    // --- Remove Row button ---
    $(document).on('click', '.obn-remove-row-btn', function() {
        var $row = $(this).closest('.obn-brand-row');
        $row.fadeOut(200, function() {
            $row.remove();
            refreshRemoveButtons();
        });
    });

    // --- Save brands (bulk or single-edit) ---
    $('#obn-brand-form').on('submit', function(e) {
        e.preventDefault();

        var editId = $('#obn-brand-edit-id').val();

        // --- Single-brand edit mode ---
        if (editId) {
            var $row = $('#obn-brand-rows .obn-brand-row:first');
            var $btn = $('#obn-brand-save-btn');
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-2"></i> Saving...');

            $.post(obn_ajax.ajax_url, {
                action: 'frontend_save_brand',
                security: '<?php echo $nonce; ?>',
                id: editId,
                brand_code: $row.find('.obn-brand-code').val().trim(),
                brand_name: $row.find('.obn-brand-name').val().trim(),
                description: $row.find('.obn-brand-desc').val().trim()
            }, function(response) {
                $btn.prop('disabled', false).html('<i class="fa fa-save mr-2"></i> Update Brand');
                if (response.success) {
                    $('#obn-brand-success-text').text(response.data.message);
                    $('#obn-brand-success').removeClass('hidden');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    $('#obn-brand-bulk-error-text').text(response.data.message);
                    $('#obn-brand-bulk-error').removeClass('hidden');
                }
            }).fail(function() {
                $btn.prop('disabled', false).html('<i class="fa fa-save mr-2"></i> Update Brand');
                $('#obn-brand-bulk-error-text').text('An unexpected error occurred. Please try again.');
                $('#obn-brand-bulk-error').removeClass('hidden');
            });
            return;
        }

        // --- Bulk insert mode ---
        var brands = [];
        var $btn = $('#obn-brand-save-btn');
        $('#obn-brand-rows .obn-brand-row').each(function() {
            var $row = $(this);
            var name = $row.find('.obn-brand-name').val().trim();
            var code = $row.find('.obn-brand-code').val().trim();
            var desc = $row.find('.obn-brand-desc').val().trim();
            if (name !== '') {
                brands.push({ brand_name: name, brand_code: code, description: desc });
            }
        });

        if (brands.length === 0) {
            $('#obn-brand-bulk-error-text').text('Please enter at least one brand name.');
            $('#obn-brand-bulk-error').removeClass('hidden');
            return;
        }
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-2"></i> Saving...');

        $.post(obn_ajax.ajax_url, {
            action: 'frontend_bulk_save_brands',
            security: '<?php echo $nonce; ?>',
            brands: brands
        }, function(response) {
            $btn.prop('disabled', false).html('<i class="fa fa-save mr-2"></i> Save All Brands');
            if (response.success) {
                $('#obn-brand-success-text').text(response.data.message);
                $('#obn-brand-success').removeClass('hidden');
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                $('#obn-brand-bulk-error-text').text(response.data.message);
                $('#obn-brand-bulk-error').removeClass('hidden');
            }
        }).fail(function() {
            $btn.prop('disabled', false).html('<i class="fa fa-save mr-2"></i> Save All Brands');
            $('#obn-brand-bulk-error-text').text('An unexpected error occurred. Please try again.');
            $('#obn-brand-bulk-error').removeClass('hidden');
        });
    });

    // --- Delete brand ---
    $(document).on('click', '.obn-delete-brand-btn', function() {
        if (!confirm('Are you sure you want to delete this brand?')) return;
        var id = $(this).data('id');

        $.post(obn_ajax.ajax_url, {
            action: 'frontend_delete_brand',
            id: id,
            security: '<?php echo $nonce; ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error deleting brand');
            }
        });
    });

    // --- Toggle status ---
    $(document).on('change', '.obn-toggle-brand-status', function() {
        var id = $(this).data('id');
        var status = $(this).is(':checked') ? 1 : 0;

        $.post(obn_ajax.ajax_url, {
            action: 'frontend_update_brand_status',
            id: id,
            status: status,
            security: '<?php echo $nonce; ?>'
        });
    });

    // --- Search ---
    $('#obn-brands-search').on('keyup', function() {
        var value = this.value.toLowerCase();
        $('#obn-brands-table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    // --- Column visibility ---
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
