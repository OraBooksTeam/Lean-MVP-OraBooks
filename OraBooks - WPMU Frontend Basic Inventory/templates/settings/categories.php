<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'orabooks_db_category';
$categories = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Categories Management</h1>
        <button id="toggle-form-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition-colors text-sm font-medium">
            <i class="fa-solid fa-plus mr-2"></i> Add New Category
        </button>
    </div>

    <!-- Success Message -->
    <div id="success-message" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline" id="success-text"></span>
        <button type="button" class="absolute top-3 right-3 text-green-700 hover:text-green-900 focus:outline-none" onclick="this.parentElement.classList.add('hidden');">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Add/Edit Form Section (Hidden by default) -->
    <div id="form-section" class="hidden bg-white rounded-lg shadow-md p-6 mb-6 border border-gray-200">
        <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4" id="form-title">Add New Category</h3>
        
        <form id="category-form" class="space-y-4">
            <input type="hidden" name="action" value="frontend_save_category">
            <input type="hidden" name="security" value="<?php echo wp_create_nonce('frontend_ajax_nonce'); ?>">
            <input type="hidden" name="id" id="category_id" value="">
            <input type="hidden" name="store_id" value="1">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Category Code -->
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Category Code</label>
                    <input type="text" name="category_code" id="category_code" class="w-full bg-gray-100 text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none cursor-not-allowed" readonly>
                </div>

                <!-- Category Name -->
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Category Name <span class="text-red-500">*</span></label>
                    <input type="text" name="category_name" id="category_name" required class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500 placeholder-gray-400">
                </div>
            </div>

            <!-- Description -->
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                <textarea name="description" id="description" rows="3" class="w-full bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 focus:outline-none focus:border-blue-500"></textarea>
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <button type="button" id="cancel-btn" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition-colors text-sm">Cancel</button>
                <button type="submit" id="save-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition-colors text-sm font-bold">
                    <i class="fa fa-save mr-2"></i> Save Category
                </button>
            </div>
        </form>
    </div>

    <!-- Search Bar & Export Toolbar -->
    <div class="search-filter-bar mb-4 flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
        <div class="relative flex-1 w-full">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <i class="fa-solid fa-search text-gray-400 text-sm"></i>
            </div>
            <input type="search" id="searchInput" class="block w-full pl-10 pr-3 py-2 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500" placeholder="Search categories...">
        </div>
        
        <!-- Export & Column Buttons -->
        <div class="export-toolbar flex gap-2 flex-wrap">
            <button id="printBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" title="Print">
                <i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
            </button>
            <button id="pdfBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" title="Export to PDF">
                <i class="fa-solid fa-file-pdf mr-1 text-red-600"></i> <span class="hidden sm:inline">PDF</span>
            </button>
            <button id="excelBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" title="Export to Excel">
                <i class="fa-solid fa-file-excel mr-1 text-green-600"></i> <span class="hidden sm:inline">Excel</span>
            </button>
            <button id="csvBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" title="Export to CSV">
                <i class="fa-solid fa-file-csv mr-1 text-blue-600"></i> <span class="hidden sm:inline">CSV</span>
            </button>
            
            <!-- Column Visibility Dropdown -->
            <div class="relative">
                <button id="columnToggleBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors" title="Toggle Columns">
                    <i class="fa-solid fa-columns mr-1"></i> Columns
                </button>
                <div id="columnDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                    <div class="p-3 space-y-2">
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="0" checked> Category Code
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="1" checked> Category Name
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="2" checked> Description
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="3" checked> Status
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="4" checked> Actions
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Categories List Table -->
    <div class="bg-white rounded-lg shadow-md overflow-x-auto border border-gray-200">
        <table id="categoriesTable" class="min-w-full divide-y divide-gray-200">
            <thead class="bg-indigo-600 text-white">
                <tr>
                    <th scope="col" class="px-6 py-3">Category Code</th>
                    <th scope="col" class="px-6 py-3">Category Name</th>
                    <th scope="col" class="px-6 py-3">Description</th>
                    <th scope="col" class="px-6 py-3">Status</th>
                    <th scope="col" class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="categories-table-body">
                <?php if (!empty($categories)) : ?>
                    <?php foreach ($categories as $cat) : ?>
                        <tr class="bg-white border-b hover:bg-gray-50 text-gray-700">
                            <td class="px-6 py-4 font-medium"><?php echo esc_html($cat->category_code); ?></td>
                            <td class="px-6 py-4 font-bold text-gray-900"><?php echo esc_html($cat->category_name); ?></td>
                            <td class="px-6 py-4"><?php echo esc_html($cat->description); ?></td>
                            <td class="px-6 py-4">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" class="sr-only peer toggle-status" data-id="<?php echo $cat->id; ?>" <?php checked($cat->status, 1); ?>>
                                    <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button class="text-blue-600 hover:text-blue-900 mr-3 edit-category" 
                                    data-id="<?php echo $cat->id; ?>"
                                    data-code="<?php echo esc_attr($cat->category_code); ?>"
                                    data-name="<?php echo esc_attr($cat->category_name); ?>"
                                    data-desc="<?php echo esc_attr($cat->description); ?>">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <button class="text-red-600 hover:text-red-900 delete-category" data-id="<?php echo $cat->id; ?>">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr class="bg-white border-b">
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No categories found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
jQuery(document).ready(function($) {
    
    // --- Existing Form Logic ---
    function resetForm() {
        $('#category-form')[0].reset();
        $('#category_id').val('');
        $('#category_code').val(''); 
        $('#form-title').text('Add New Category');
        $('#save-btn').html('<i class="fa fa-save mr-2"></i> Save Category');
    }

    function generateCode() {
         $.post('<?php echo admin_url('admin-ajax.php'); ?>', { 
             action: 'frontend_generate_category_code', 
             security: '<?php echo wp_create_nonce('frontend_ajax_nonce'); ?>' 
         }, function(res){
            if(res.success){
                $('#category_code').val(res.data.code);
            }
        });
    }

    $('#toggle-form-btn').on('click', function() {
        resetForm();
        $('#form-section').removeClass('hidden');
        generateCode();
        $('html, body').animate({ scrollTop: 0 }, 'slow');
    });

    $('#cancel-btn').on('click', function() {
        $('#form-section').addClass('hidden');
        resetForm();
        let url = new URL(window.location.href);
        url.searchParams.delete('action');
        window.history.replaceState({}, '', url);
    });

     // Handle URL action=add
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'add') {
        $('#toggle-form-btn').click();
    }

    // Edit Category
    $(document).on('click', '.edit-category', function() {
        let id = $(this).data('id');
        let code = $(this).data('code');
        let name = $(this).data('name');
        let desc = $(this).data('desc');

        $('#category_id').val(id);
        $('#category_code').val(code);
        $('#category_name').val(name);
        $('#description').val(desc);

        $('#form-title').text('Edit Category');
        $('#save-btn').html('<i class="fa fa-save mr-2"></i> Update Category');
        $('#form-section').removeClass('hidden');
        $('html, body').animate({ scrollTop: 0 }, 'slow');
    });

    // Save Category
    $('#category-form').on('submit', function(e) {
        e.preventDefault();
        let formData = $(this).serialize();
        
        $.post('<?php echo admin_url('admin-ajax.php'); ?>', formData, function(response) {
            if(response.success) {
                $('#success-text').text(response.data.message);
                $('#success-message').removeClass('hidden');
                setTimeout(function(){ location.reload(); }, 1000);
            } else {
                alert('Error: ' + response.data.message);
            }
        });
    });

    // Delete Category
    $(document).on('click', '.delete-category', function() {
        if(!confirm('Are you sure you want to delete this category?')) return;
        let id = $(this).data('id');

        $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'frontend_delete_category',
            id: id,
            security: '<?php echo wp_create_nonce('frontend_ajax_nonce'); ?>'
        }, function(response) {
            if(response.success) {
                location.reload();
            } else {
                alert('Error deleting category');
            }
        });
    });

    // Toggle Status
    $(document).on('change', '.toggle-status', function() {
        let id = $(this).data('id');
        let status = $(this).is(':checked') ? 1 : 0;

        $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'frontend_update_category_status',
            id: id,
            status: status,
            security: '<?php echo wp_create_nonce('frontend_ajax_nonce'); ?>'
        });
    });

    // --- New Search / Export Logic ---

    // Client-side Search
    $('#searchInput').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $("#categoriesTable tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });

    // Column visibility toggle
    $('#columnToggleBtn').on('click', function(e) {
        e.stopPropagation();
        $('#columnDropdown').toggleClass('hidden');
    });
    
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#columnToggleBtn, #columnDropdown').length) {
            $('#columnDropdown').addClass('hidden');
        }
    });
    
    $('.column-toggle').on('change', function() {
        const column = $(this).data('column');
        const isChecked = $(this).is(':checked');
        
        // Toggle header
        $('#categoriesTable thead tr th').eq(column).toggle(isChecked);
        // Toggle cells
        $('#categoriesTable tbody tr').each(function() {
            $(this).find('td').eq(column).toggle(isChecked);
        });
    });

    // Helper to get visible table data for export
    function getTableData() {
        const data = [];
        const headers = [];
        
        $('#categoriesTable thead tr th').each(function(index) {
            if($(this).is(':visible') && index < 4) { // Exclude Actions (index 4)
                headers.push($(this).text().trim());
            }
        });
        data.push(headers);
        
        $('#categoriesTable tbody tr').each(function() {
            if($(this).is(':visible')){
                const row = [];
                $(this).find('td').each(function(index) {
                    if($(this).is(':visible') && index < 4) {
                        let text = $(this).text().trim();
                        text = text.replace(/\s+/g, ' ').trim();
                        row.push(text);
                    }
                });
                if(row.length > 0) {
                    data.push(row);
                }
            }
        });
        
        return data;
    }

    // Print functionality
    $('#printBtn').on('click', function() {
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Categories List</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-family: Arial, sans-serif; }');
        printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 20px; }');
        printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
        printWindow.document.write('th { background-color: #f3f4f6; font-weight: bold; }');
        printWindow.document.write('h1 { text-align: center; color: #333; }');
        printWindow.document.write('</style></head><body>');
        printWindow.document.write('<h1>Categories List</h1>');
        
        const tableData = getTableData();
        printWindow.document.write('<table>');
        tableData.forEach(function(row, index) {
            printWindow.document.write('<tr>');
            row.forEach(function(cell) {
                const tag = index === 0 ? 'th' : 'td';
                printWindow.document.write('<' + tag + '>' + cell + '</' + tag + '>');
            });
            printWindow.document.write('</tr>');
        });
        printWindow.document.write('</table></body></html>');
        printWindow.document.close();
        printWindow.print();
    });

    // PDF Export
    $('#pdfBtn').on('click', function() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        doc.setFontSize(18);
        doc.text('Categories List', 14, 22);
        
        const tableData = getTableData();
        const headers = tableData[0];
        const rows = tableData.slice(1);
        
        doc.autoTable({
            head: [headers],
            body: rows,
            startY: 30,
            theme: 'grid',
            styles: { fontSize: 10 },
            headStyles: { fillColor: [79, 70, 229] }
        });
        
        doc.save('categories-list.pdf');
    });

    // Excel Export
    $('#excelBtn').on('click', function() {
        const tableData = getTableData();
        const ws = XLSX.utils.aoa_to_sheet(tableData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Categories');
        XLSX.writeFile(wb, 'categories-list.xlsx');
    });

    // CSV Export
    $('#csvBtn').on('click', function() {
        const tableData = getTableData();
        let csv = '';
        
        tableData.forEach(function(row) {
            csv += row.map(function(cell) {
                return '"' + cell.replace(/"/g, '""') + '"';
            }).join(',') + '\n';
        });
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'categories-list.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

});
</script>
