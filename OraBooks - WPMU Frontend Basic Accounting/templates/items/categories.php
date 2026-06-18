<?php
/**
 * Categories Management Template for Accounting
 */
if (!defined('ABSPATH'))
    exit;

global $wpdb;
$table_name = $wpdb->prefix . 'orabooks_db_category';
$categories = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
$nonce = wp_create_nonce('frontend_ajax_nonce');
?>

<div class="obn-card p-6 !pt-2">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-2xl font-bold text-gray-800">Categories Management</h3>
        <button type="button" id="obn-acc-toggle-category-form"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all shadow-sm flex items-center gap-2">
            <i class="fa-solid fa-plus-circle"></i> Add New Category
        </button>
    </div>

    <!-- Success Message -->
    <div id="obn-acc-category-success"
        class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline" id="obn-acc-category-success-text"></span>
        <button type="button" class="absolute top-3 right-3 text-green-700 hover:text-green-900 focus:outline-none"
            onclick="this.parentElement.classList.add('hidden');">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Add New Category Form (Bulk Multi-Row) -->
    <div id="obn-acc-category-form-section"
        class="hidden bg-white rounded-lg shadow-md p-6 mb-6 border border-gray-200">
        <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4"
            id="obn-acc-category-form-title">Add New Categories</h3>

        <!-- Bulk insert warning -->
        <div id="obn-acc-category-bulk-error"
            class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline" id="obn-acc-category-bulk-error-text"></span>
            <button type="button" class="absolute top-3 right-3 text-red-700 hover:text-red-900 focus:outline-none"
                onclick="this.parentElement.classList.add('hidden');">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="obn-acc-category-form">
            <input type="hidden" name="action" value="frontend_bulk_save_categories">
            <input type="hidden" name="security" value="<?php echo $nonce; ?>">

            <!-- Category rows container -->
            <div id="obn-acc-category-rows">
                <div
                    class="obn-category-row grid grid-cols-1 md:grid-cols-12 gap-3 items-end mb-3 pb-3 border-b border-gray-100">
                    <div class="md:col-span-3">
                        <label class="block text-gray-700 text-xs font-bold mb-1">Code</label>
                        <input type="text" name="categories[0][category_code]"
                            class="obn-cat-code w-full bg-gray-100 text-gray-700 border border-gray-300 rounded py-2 px-3 text-sm focus:outline-none cursor-not-allowed"
                            readonly>
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-gray-700 text-xs font-bold mb-1">Category Name <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="categories[0][category_name]" required
                            class="obn-cat-name w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 text-sm focus:outline-none focus:border-blue-500 placeholder-gray-400"
                            placeholder="Enter category name">
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-gray-700 text-xs font-bold mb-1">Description</label>
                        <input type="text" name="categories[0][description]"
                            class="obn-cat-desc w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 text-sm focus:outline-none focus:border-blue-500 placeholder-gray-400"
                            placeholder="Optional description">
                    </div>
                    <div class="md:col-span-1 flex items-end justify-center">
                        <button type="button"
                            class="obn-remove-row-btn bg-red-500 hover:bg-red-600 text-white rounded-lg p-2 transition-colors text-sm hidden"
                            title="Remove Row">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Add Row Button -->
            <div class="flex justify-start mt-2 mb-4">
                <button type="button" id="obn-acc-add-category-row"
                    class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-plus-circle"></i> Add Row +
                </button>
            </div>

            <div class="flex justify-end gap-2 mt-4 pt-3 border-t border-gray-200">
                <button type="button" id="obn-acc-category-cancel-btn"
                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition-colors text-sm">Cancel</button>
                <button type="submit" id="obn-acc-category-save-btn"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded transition-colors text-sm font-bold">
                    <i class="fa fa-save mr-2"></i> Save All Categories
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
            <input type="search" id="obn-acc-categories-search"
                class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 transition-all"
                placeholder="Search categories...">
        </div>
        <div class="flex items-center gap-2">
            <div class="relative inline-block text-left">
                <button type="button"
                    class="obn-column-toggle-btn inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fa-solid fa-columns mr-2"></i> Columns
                </button>
                <div
                    class="obn-column-dropdown hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                    <div class="py-1 p-3 space-y-2">
                        <?php
                        $cat_cols = ['Code', 'Name', 'Description', 'Status'];
                        foreach ($cat_cols as $idx => $name): ?>
                            <label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
                                <input type="checkbox" checked
                                    class="obn-col-hide form-checkbox h-4 w-4 text-blue-600 rounded"
                                    data-column="<?php echo $idx; ?>" data-table="#obn-categories-table">
                                <span class="ml-3 text-sm text-gray-700 font-bold uppercase"><?php echo $name; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="flex items-center bg-gray-100 p-1 rounded-lg">
                <button
                    class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
                    data-table="#obn-categories-table" data-title="Categories List" title="Print"><i
                        class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span></button>
                <button
                    class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
                    data-table="#obn-categories-table" data-title="Categories List" title="PDF"><i
                        class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span></button>
                <button
                    class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
                    data-table="#obn-categories-table" data-title="Categories List" title="Excel"><i
                        class="fa-solid fa-file-excel mr-1"></i> <span class="hidden sm:inline">Excel</span></button>
                <button
                    class="obn-csv-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
                    data-table="#obn-categories-table" data-title="Categories_List" title="CSV"><i
                        class="fa-solid fa-file-csv mr-1"></i> <span class="hidden sm:inline">CSV</span></button>
            </div>
        </div>
    </div>

    <!-- Categories Table -->
    <div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
        <table id="obn-categories-table" class="w-full text-sm">
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
                <?php if (!empty($categories)):
                    foreach ($categories as $cat): ?>
                        <tr class="hover:bg-gray-50" data-id="<?php echo esc_attr($cat->id); ?>">
                            <td class="px-4 py-3 text-gray-800 font-medium"><?php echo esc_html($cat->category_code); ?></td>
                            <td class="px-4 py-3 text-gray-800 font-bold"><?php echo esc_html($cat->category_name); ?></td>
                            <td class="px-4 py-3 text-gray-600"><?php echo esc_html($cat->description); ?></td>
                            <td class="px-4 py-3 text-center no-export">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" class="obn-toggle-category-status sr-only peer"
                                        data-id="<?php echo esc_attr($cat->id); ?>"
                                        data-status="<?php echo esc_attr($cat->status); ?>" data-nonce="<?php echo $nonce; ?>"
                                        <?php echo ($cat->status == 1) ? 'checked' : ''; ?>>
                                    <div
                                        class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
                                    </div>
                                </label>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2 flex justify-end no-export">
                                <button
                                    class="obn-edit-category-btn px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium transition"
                                    data-id="<?php echo esc_attr($cat->id); ?>"
                                    data-code="<?php echo esc_attr($cat->category_code); ?>"
                                    data-name="<?php echo esc_attr($cat->category_name); ?>"
                                    data-desc="<?php echo esc_attr($cat->description); ?>">Edit</button>
                                <button
                                    class="obn-delete-category-btn px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition"
                                    data-id="<?php echo esc_attr($cat->id); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">No categories found. Click "Add New
                            Category" to create one.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    jQuery(document).ready(function ($) {
        var categoryRowIndex = 1;
        var categoryNonce = '<?php echo $nonce; ?>';
        var isEditMode = false;
        var editCategoryId = '';

        // Generate category codes for all current rows
        function generateAllCategoryCodes(callback) {
            var rows = $('#obn-acc-category-rows .obn-category-row');
            var total = rows.length;
            if (total === 0) { if (callback) callback(); return; }

            $.post(obn_ajax.ajax_url, {
                action: 'frontend_generate_bulk_category_codes',
                count: total,
                security: categoryNonce
            }, function (res) {
                if (res.success && res.data.codes) {
                    rows.each(function (i) {
                        $(this).find('.obn-cat-code').val(res.data.codes[i] || '');
                    });
                }
                if (callback) callback();
            });
        }

        // Build one category row HTML
        function buildCategoryRow(idx) {
            return '<div class="obn-category-row grid grid-cols-1 md:grid-cols-12 gap-3 items-end mb-3 pb-3 border-b border-gray-100">' +
                '<div class="md:col-span-3">' +
                '<label class="block text-gray-700 text-xs font-bold mb-1">Code</label>' +
                '<input type="text" name="categories[' + idx + '][category_code]" class="obn-cat-code w-full bg-gray-100 text-gray-700 border border-gray-300 rounded py-2 px-3 text-sm focus:outline-none cursor-not-allowed" readonly>' +
                '</div>' +
                '<div class="md:col-span-4">' +
                '<label class="block text-gray-700 text-xs font-bold mb-1">Category Name <span class="text-red-500">*</span></label>' +
                '<input type="text" name="categories[' + idx + '][category_name]" required class="obn-cat-name w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 text-sm focus:outline-none focus:border-blue-500 placeholder-gray-400" placeholder="Enter category name">' +
                '</div>' +
                '<div class="md:col-span-4">' +
                '<label class="block text-gray-700 text-xs font-bold mb-1">Description</label>' +
                '<input type="text" name="categories[' + idx + '][description]" class="obn-cat-desc w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 text-sm focus:outline-none focus:border-blue-500 placeholder-gray-400" placeholder="Optional description">' +
                '</div>' +
                '<div class="md:col-span-1 flex items-end justify-center">' +
                '<button type="button" class="obn-remove-row-btn bg-red-500 hover:bg-red-600 text-white rounded-lg p-2 transition-colors text-sm" title="Remove Row">' +
                '<i class="fa-solid fa-trash-can"></i>' +
                '</button>' +
                '</div>' +
                '</div>';
        }

        // Update remove button visibility
        function updateRemoveButtons() {
            var count = $('#obn-acc-category-rows .obn-category-row').length;
            if (count <= 1) {
                $('#obn-acc-category-rows .obn-remove-row-btn').addClass('hidden');
            } else {
                $('#obn-acc-category-rows .obn-remove-row-btn').removeClass('hidden');
            }
        }

        // Reset the form back to a single empty row
        function resetBulkForm() {
            $('#obn-acc-category-rows').html(buildCategoryRow(0));
            categoryRowIndex = 1;
            isEditMode = false;
            editCategoryId = '';
            updateRemoveButtons();
            $('#obn-acc-category-bulk-error').addClass('hidden');
            $('#obn-add-category-row').show();
            generateAllCategoryCodes();
        }

        // Toggle form open
        $('#obn-acc-toggle-category-form').on('click', function () {
            resetBulkForm();
            $('#obn-acc-category-form-section').removeClass('hidden');
            $('html, body').animate({ scrollTop: 0 }, 'slow');
        });

        $('#obn-acc-category-cancel-btn').on('click', function () {
            $('#obn-acc-category-form-section').addClass('hidden');
            resetBulkForm();
        });

        // Add Row
        $('#obn-acc-add-category-row').on('click', function () {
            var idx = categoryRowIndex++;
            $('#obn-acc-category-rows').append(buildCategoryRow(idx));
            updateRemoveButtons();
            generateAllCategoryCodes();
            $('#obn-acc-category-rows .obn-category-row:last .obn-cat-name').focus();
        });

        // Remove Row (delegated)
        $(document).on('click', '.obn-remove-row-btn', function () {
            $(this).closest('.obn-category-row').remove();
            updateRemoveButtons();
        });

        // SINGLE unified submit handler for both bulk and edit modes
        $('#obn-acc-category-form').on('submit', function (e) {
            e.preventDefault();
            var $saveBtn = $('#obn-acc-category-save-btn');
            $saveBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-2"></i> Saving...');

            if (isEditMode) {
                // Edit mode: send single category update via explicit POST
                var $section = $('#obn-acc-category-form-section');
                var code = $section.find('.obn-cat-code').val();
                var name = $section.find('.obn-cat-name').val();
                var desc = $section.find('.obn-cat-desc').val();

                $.post(obn_ajax.ajax_url, {
                    action: 'frontend_save_category',
                    security: categoryNonce,
                    id: editCategoryId,
                    category_code: code,
                    category_name: name,
                    description: desc
                }, function (response) {
                    $saveBtn.prop('disabled', false).html('<i class="fa fa-save mr-2"></i> Update Category');
                    if (response.success) {
                        $('#obn-acc-category-success-text').text(response.data.message);
                        $('#obn-acc-category-success').removeClass('hidden');
                        $('#obn-acc-category-bulk-error').addClass('hidden');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        $('#obn-acc-category-bulk-error-text').text(response.data.message);
                        $('#obn-acc-category-bulk-error').removeClass('hidden');
                    }
                }).fail(function () {
                    $saveBtn.prop('disabled', false).html('<i class="fa fa-save mr-2"></i> Update Category');
                    $('#obn-acc-category-bulk-error-text').text('Network error. Please try again.');
                    $('#obn-acc-category-bulk-error').removeClass('hidden');
                });
            } else {
                // Bulk mode: serialize all rows and send
                var formData = $(this).serialize();

                $.post(obn_ajax.ajax_url, formData, function (response) {
                    $saveBtn.prop('disabled', false).html('<i class="fa fa-save mr-2"></i> Save All Categories');
                    if (response.success) {
                        $('#obn-acc-category-success-text').text(response.data.message);
                        $('#obn-acc-category-success').removeClass('hidden');
                        $('#obn-acc-category-bulk-error').addClass('hidden');
                        setTimeout(function () { location.reload(); }, 1200);
                    } else {
                        $('#obn-acc-category-bulk-error-text').text(response.data.message);
                        $('#obn-acc-category-bulk-error').removeClass('hidden');
                    }
                }).fail(function () {
                    $saveBtn.prop('disabled', false).html('<i class="fa fa-save mr-2"></i> Save All Categories');
                    $('#obn-acc-category-bulk-error-text').text('Network error. Please try again.');
                    $('#obn-acc-category-bulk-error').removeClass('hidden');
                });
            }
        });

        // Edit category (single-row edit from table)
        $(document).on('click', '.obn-edit-category-btn', function () {
            var id = $(this).data('id');
            var code = $(this).data('code');
            var name = $(this).data('name');
            var desc = $(this).data('desc');

            isEditMode = true;
            editCategoryId = id;

            var $section = $('#obn-acc-category-form-section');
            $section.removeClass('hidden');
            $('#obn-acc-category-form-title').text('Edit Category');
            $('#obn-acc-category-save-btn').html('<i class="fa fa-save mr-2"></i> Update Category');
            $('#obn-acc-add-category-row').hide();
            $('#obn-acc-category-bulk-error').addClass('hidden');

            // Replace rows with a single edit row using same class names for easy read
            var escCode = code.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            var escName = name.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            var escDesc = desc.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');

            var html = '<div class="obn-category-row grid grid-cols-1 md:grid-cols-12 gap-3 items-end mb-3 pb-3 border-b border-gray-100">' +
                '<div class="md:col-span-3">' +
                '<label class="block text-gray-700 text-xs font-bold mb-1">Code</label>' +
                '<input type="text" class="obn-cat-code w-full bg-gray-100 text-gray-700 border border-gray-300 rounded py-2 px-3 text-sm focus:outline-none cursor-not-allowed" readonly value="' + escCode + '">' +
                '</div>' +
                '<div class="md:col-span-4">' +
                '<label class="block text-gray-700 text-xs font-bold mb-1">Category Name <span class="text-red-500">*</span></label>' +
                '<input type="text" required class="obn-cat-name w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 text-sm focus:outline-none focus:border-blue-500" value="' + escName + '">' +
                '</div>' +
                '<div class="md:col-span-4">' +
                '<label class="block text-gray-700 text-xs font-bold mb-1">Description</label>' +
                '<input type="text" class="obn-cat-desc w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 text-sm focus:outline-none focus:border-blue-500" value="' + escDesc + '">' +
                '</div>' +
                '<div class="md:col-span-1"></div>' +
                '</div>';

            $('#obn-acc-category-rows').html(html);
            $('html, body').animate({ scrollTop: 0 }, 'slow');
        });

        // Delete category
        $(document).on('click', '.obn-delete-category-btn', function () {
            if (!confirm('Are you sure you want to delete this category?')) return;
            var id = $(this).data('id');

            $.post(obn_ajax.ajax_url, {
                action: 'frontend_delete_category',
                id: id,
                security: categoryNonce
            }, function (response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error deleting category');
                }
            });
        });

        // Toggle status
        $(document).on('change', '.obn-toggle-category-status', function () {
            var id = $(this).data('id');
            var status = $(this).is(':checked') ? 1 : 0;
            var $toggle = $(this);

            $.post(obn_ajax.ajax_url, {
                action: 'frontend_update_category_status',
                id: id,
                status: status,
                security: categoryNonce
            }).done(function (response) {
                // Hide any previous error alert
                $('#obn-acc-category-bulk-error').addClass('hidden');
                if (!response || !response.success) {
                    // Show error message
                    $('#obn-acc-category-bulk-error-text').text(response && response.data && response.data.message ? response.data.message : 'Failed to update status.');
                    $('#obn-acc-category-bulk-error').removeClass('hidden');
                    // Revert toggle
                    $toggle.prop('checked', !$toggle.is(':checked'));
                }
            }).fail(function () {
                // Revert the toggle on network error
                $toggle.prop('checked', !$toggle.is(':checked'));
                $('#obn-acc-category-bulk-error-text').text('Network error while updating status.');
                $('#obn-acc-category-bulk-error').removeClass('hidden');
            });
        });

        // Search
        $('#obn-acc-categories-search').on('keyup', function () {
            var value = this.value.toLowerCase();
            $('#obn-categories-table tbody tr').filter(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });

        // Column visibility
        $(document).on('click', '.obn-column-toggle-btn', function (e) {
            e.stopPropagation();
            $(this).next('.obn-column-dropdown').toggleClass('hidden');
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('.obn-column-toggle-btn, .obn-column-dropdown').length) {
                $('.obn-column-dropdown').addClass('hidden');
            }
        });

        $('.obn-col-hide').on('change', function () {
            var column = $(this).data('column');
            var isChecked = $(this).is(':checked');
            var table = $(this).data('table');
            $(table + ' thead tr th').eq(column).toggle(isChecked);
            $(table + ' tbody tr').each(function () {
                $(this).find('td').eq(column).toggle(isChecked);
            });
        });
    });
</script>