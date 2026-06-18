<?php
/**
 * Import Items Template
 */
if (!defined('ABSPATH')) exit;
?>
<div class="obn-card p-6 !pt-4">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-2xl font-bold text-gray-800">Import Items</h3>
        <a href="#" onclick="showView('obn-view-view-items')" class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-xl hover:from-gray-700 hover:to-gray-800 transition-all font-bold text-xs uppercase tracking-widest shadow-lg shadow-gray-200 hover:shadow-gray-300 active:scale-95">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Items
        </a>
    </div>

    <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-2xl">
        <div class="mb-6">
            <h4 class="text-lg font-bold text-gray-800 mb-2">Upload File</h4>
            <p class="text-sm text-gray-600 mb-4">Upload a CSV or Excel file with your item data. <a href="#" id="obn-download-template" class="text-blue-600 hover:text-blue-800 font-semibold underline">Download template</a> to see the required format.</p>
            <form id="obn-import-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="obn_import_items">
                <input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('obn_auth_nonce')); ?>">
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-blue-400 transition-colors cursor-pointer" id="obn-drop-zone">
                    <i class="fa-solid fa-cloud-arrow-up text-5xl text-gray-300 mb-4"></i>
                    <p class="text-gray-600 font-medium mb-2">Drag & drop your file here or <span class="text-blue-600 font-semibold">browse</span></p>
                    <p class="text-xs text-gray-400">Supported formats: CSV, XLSX, XLS</p>
                    <input type="file" name="import_file" id="obn-import-file" accept=".csv,.xlsx,.xls" class="hidden" required>
                </div>
                <div id="obn-file-info" class="hidden mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-file-excel text-blue-600 text-xl"></i>
                            <span id="obn-file-name" class="font-medium text-gray-700"></span>
                        </div>
                        <button type="button" id="obn-remove-file" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-times"></i></button>
                    </div>
                </div>
                <div class="mt-6">
                    <button type="submit" id="obn-import-submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition-all shadow-md disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        <i class="fa-solid fa-upload mr-2"></i> Import Items
                    </button>
                </div>
            </form>
        </div>

        <div class="border-t border-gray-200 pt-6">
            <h4 class="text-lg font-bold text-gray-800 mb-2">Import Instructions</h4>
            <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                <li>Download the template to see the required columns</li>
                <li>First row must be the header row (will be skipped)</li>
                <li>Required columns: Item Name</li>
                <li>Optional columns: Item Code, Item Type (goods/service), Sales Price, Purchase Price, Opening Stock</li>
                <li>Maximum file size: 2MB</li>
            </ul>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Download template
    $('#obn-download-template').on('click', function(e) {
        e.preventDefault();
        var form = $('<form>', {
            method: 'post',
            action: obn_ajax.ajax_url
        }).append(
            $('<input>', { type: 'hidden', name: 'action', value: 'obn_download_item_template' }),
            $('<input>', { type: 'hidden', name: 'security', value: '<?php echo wp_create_nonce('obn_auth_nonce'); ?>' })
        );
        form.appendTo('body').submit().remove();
    });

    // File selection
    var $fileInput = $('#obn-import-file');
    var $dropZone = $('#obn-drop-zone');
    var $fileInfo = $('#obn-file-info');
    var $fileName = $('#obn-file-name');
    var $submitBtn = $('#obn-import-submit');
    var $removeFile = $('#obn-remove-file');

    $dropZone.on('click', function() { $fileInput.click(); });

    $fileInput.on('change', function() {
        var file = this.files[0];
        if (file) {
            $dropZone.addClass('hidden');
            $fileInfo.removeClass('hidden');
            $fileName.text(file.name);
            $submitBtn.prop('disabled', false);
        }
    });

    $removeFile.on('click', function() {
        $fileInput.val('');
        $dropZone.removeClass('hidden');
        $fileInfo.addClass('hidden');
        $submitBtn.prop('disabled', true);
    });

    // Drag & drop
    $dropZone.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('border-blue-500 bg-blue-50');
    }).on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('border-blue-500 bg-blue-50');
    }).on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('border-blue-500 bg-blue-50');
        var files = e.originalEvent.dataTransfer.files;
        if (files.length) {
            $fileInput[0].files = files;
            $fileInput.trigger('change');
        }
    });

    // Submit import
    $('#obn-import-form').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        var btn = $submitBtn;
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Importing...');
        $.ajax({
            url: obn_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    localStorage.setItem('obn-after-reload-view', 'obn-view-view-items');
                    location.reload();
                } else {
                    alert(response.data || 'Import failed.');
                    btn.prop('disabled', false).html('<i class="fa-solid fa-upload mr-2"></i> Import Items');
                }
            },
            error: function() {
                alert('Import request failed.');
                btn.prop('disabled', false).html('<i class="fa-solid fa-upload mr-2"></i> Import Items');
            }
        });
    });
});
</script>
