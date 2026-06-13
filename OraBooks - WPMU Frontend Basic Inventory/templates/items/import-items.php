<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$warehouses_table = $wpdb->prefix . 'orabooks_db_warehouse';
$warehouses = $wpdb->get_results("SELECT id, warehouse_name, warehouse_type FROM $warehouses_table WHERE status=1 ORDER BY warehouse_name ASC");
?>

<div class="p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center mr-4 shadow-sm font-bold">
                <i class="fa-solid fa-file-import text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Import Items</h1>
                <p class="text-sm text-gray-500 mt-1">Bulk upload items using a CSV file</p>
            </div>
        </div>
        <a href="<?php echo esc_url(add_query_arg('view', 'view-items')); ?>" class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-xl hover:from-gray-700 hover:to-gray-800 transition-all font-bold text-xs uppercase tracking-widest shadow-lg shadow-gray-200 hover:shadow-gray-300 active:scale-95">
            <i class="fa-solid fa-arrow-left-long mr-2"></i> Back to List
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Import Tool -->
        <div class="lg:col-span-2">
            <div class="bg-white p-6 rounded-2xl shadow-xl border border-gray-100 mb-6">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-4 border-b border-gray-50 pb-4">
                    <h3 class="text-lg font-bold text-gray-800 flex items-center">
                        <i class="fa-solid fa-cloud-arrow-up mr-2 text-blue-500"></i>
                        Upload CSV File
                    </h3>
                    
                    <div class="w-full sm:w-auto flex items-center gap-3">
                        <label class="whitespace-nowrap text-xs font-bold text-gray-500 uppercase tracking-wider">Select Warehouse <span class="text-red-500">*</span></label>
                        <select name="warehouse_id" form="import-items-form" required class="w-full sm:w-64 bg-gray-50 border border-gray-200 text-gray-700 py-2 px-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm transition-all">
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?php echo $wh->id; ?>" <?php selected($wh->warehouse_type, 'system'); ?>><?php echo esc_html($wh->warehouse_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <form id="import-items-form" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="action" value="frontend_import_items">
                    <input type="hidden" name="security" value="<?php echo wp_create_nonce('frontend_ajax_nonce'); ?>">
                    
                    <div class="flex items-center justify-center w-full">
                        <label for="item_csv" class="flex flex-col items-center justify-center w-full h-64 border-2 border-gray-300 border-dashed rounded-2xl cursor-pointer bg-gray-50 hover:bg-gray-100 transition-all hover:border-blue-400 group">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <div class="w-16 h-16 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                                    <i class="fa-solid fa-file-csv text-3xl"></i>
                                </div>
                                <p class="mb-2 text-sm text-gray-700"><span class="font-bold">Click to upload</span> or drag and drop</p>
                                <p class="text-xs text-gray-500">CSV files only (Max. 5MB)</p>
                                <div id="file-name-display" class="mt-4 text-blue-600 font-medium hidden"></div>
                            </div>
                            <input id="item_csv" name="item_csv" type="file" accept=".csv" class="hidden" required />
                        </label>
                    </div>

                    <div id="import-progress" class="hidden">
                        <div class="w-full bg-gray-200 rounded-full h-2.5 mb-2">
                            <div id="progress-bar" class="bg-blue-600 h-2.5 rounded-full" style="width: 0%"></div>
                        </div>
                        <p id="progress-text" class="text-xs text-gray-600 text-center italic"></p>
                    </div>

                    <button type="submit" id="import-btn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 px-6 rounded-xl focus:outline-none shadow-indigo-100 shadow-lg transition-all flex items-center justify-center gap-2 group">
                        <i class="fa-solid fa-upload group-hover:-translate-y-1 transition-transform"></i>
                        Start Import Process
                    </button>
                </form>

                <!-- Results Display -->
                <div id="import-results" class="mt-8 hidden">
                    <div class="p-4 rounded-xl border mb-4" id="results-status"></div>
                    <div id="error-list" class="space-y-2 max-h-64 overflow-y-auto"></div>
                </div>
            </div>
        </div>

        <!-- Sidebar: Info & Template -->
        <div class="lg:col-span-1">
            <div class="bg-gradient-to-br from-indigo-600 to-violet-700 p-6 rounded-2xl shadow-xl text-white mb-6">
                <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-download"></i>
                    Get Started
                </h3>
                <p class="text-indigo-100 text-sm mb-6 leading-relaxed">
                    To ensure your import is successful, please use our standardized template. It includes all required columns in the correct order.
                </p>
                <a href="<?php echo admin_url('admin-ajax.php?action=frontend_download_item_template'); ?>" class="block w-full bg-white text-indigo-600 text-center py-3 px-4 rounded-xl font-bold hover:bg-indigo-50 transition-colors shadow-lg">
                    <i class="fa-solid fa-file-arrow-down mr-2"></i>
                    Download CSV Template
                </a>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100">
                <h3 class="text-md font-bold text-gray-800 mb-4 border-b pb-2">Important Notes</h3>
                <ul class="space-y-3 text-sm text-gray-600">
                    <li class="flex items-start gap-3">
                        <i class="fa-solid fa-circle-check text-green-500 mt-1"></i>
                        <span><strong>Item Name</strong> is mandatory for every row.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fa-solid fa-circle-info text-blue-500 mt-1"></i>
                        <span>If <strong>Item Code</strong> is left blank, it will be auto-generated.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fa-solid fa-circle-info text-blue-500 mt-1"></i>
                        <span>New <strong>Categories, Brands, and Units</strong> will be created automatically if they don't exist.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fa-solid fa-circle-exclamation text-amber-500 mt-1"></i>
                        <span><strong>Tax Type</strong> should be either "Inclusive" or "Exclusive".</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Show selected filename
    $('#item_csv').on('change', function(e) {
        const fileName = e.target.files[0] ? e.target.files[0].name : '';
        if (fileName) {
            $('#file-name-display').text('Selected: ' + fileName).removeClass('hidden');
        } else {
            $('#file-name-display').addClass('hidden');
        }
    });

    // Form Submission
    $('#import-items-form').on('submit', function(e) {
        e.preventDefault();
        
        const $btn = $('#import-btn');
        const originalHtml = $btn.html();
        const formData = new FormData(this);

        // UI Reset
        $('#import-results').addClass('hidden');
        $('#error-list').empty();
        $('#import-progress').removeClass('hidden');
        $('#progress-bar').css('width', '30%');
        $('#progress-text').text('Uploading and parsing file...');
        
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Processing...');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#progress-bar').css('width', '100%');
                $('#import-results').removeClass('hidden');
                
                if (response.success) {
                    $('#results-status').removeClass('bg-red-50 text-red-700 border-red-200').addClass('bg-green-50 text-green-700 border-green-200');
                    $('#results-status').html('<strong><i class="fa-solid fa-circle-check mr-2"></i> Success!</strong> ' + response.data.message);
                    
                    if (response.data.errors && response.data.errors.length > 0) {
                        $('#error-list').append('<p class="text-sm font-bold text-gray-700 mb-2">Warnings/Errors in some rows:</p>');
                        response.data.errors.forEach(function(error) {
                            $('#error-list').append('<div class="p-2 text-xs bg-amber-50 text-amber-800 border-l-4 border-amber-400">' + error + '</div>');
                        });
                    }

                    // Redirect after a short delay to allow user to see the success message
                    setTimeout(function() {
                        window.location.href = '<?php echo esc_url(add_query_arg('view', 'view-items')); ?>';
                    }, 2000);
                } else {
                    $('#results-status').removeClass('bg-green-50 text-green-700 border-green-200').addClass('bg-red-50 text-red-700 border-red-200');
                    $('#results-status').html('<strong><i class="fa-solid fa-circle-exclamation mr-2"></i> Import Failed</strong><br>' + response.data.message);
                }
            },
            error: function() {
                alert('Server error occurred. The file might be too large or incorrectly formatted.');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalHtml);
                $('#progress-text').text('Process finished.');
            }
        });
    });
});
</script>
