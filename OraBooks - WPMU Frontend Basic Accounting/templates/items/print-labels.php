<?php
/**
 * Print Labels Template
 */
if (!defined('ABSPATH')) exit;
?>
<div class="obn-card p-6 !pt-4">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-2xl font-bold text-gray-800">Print Labels</h3>
        <a href="#" onclick="showView('obn-view-view-items')" class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-xl hover:from-gray-700 hover:to-gray-800 transition-all font-bold text-xs uppercase tracking-widest shadow-lg shadow-gray-200 hover:shadow-gray-300 active:scale-95">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Items
        </a>
    </div>

    <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
        <div class="mb-6">
            <h4 class="text-lg font-bold text-gray-800 mb-4">Select Items for Labels</h4>
            <div class="relative w-full mb-4">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input type="search" id="obn-labels-search" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="Search items to add to labels...">
            </div>
            <div id="obn-labels-search-results" class="hidden max-h-60 overflow-y-auto border border-gray-200 rounded-lg mb-4"></div>
        </div>

        <div class="mb-6">
            <h4 class="text-lg font-bold text-gray-800 mb-2">Selected Items</h4>
            <div id="obn-labels-selected" class="min-h-[100px] border-2 border-dashed border-gray-300 rounded-lg p-4">
                <p class="text-gray-400 text-center mt-8">Search and select items to add to labels</p>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="button" id="obn-print-labels-btn" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-semibold transition-all shadow-md disabled:opacity-50 disabled:cursor-not-allowed flex items-center" disabled>
                <i class="fa-solid fa-print mr-2"></i> Print Labels
            </button>
            <button type="button" id="obn-clear-labels" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-800 px-6 py-2.5 rounded-lg font-semibold transition-colors shadow-sm">Clear All</button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {

    // Use the current site's AJAX endpoint directly for this frontend template.
    // This avoids stale/wrong localized ajax_url values on WPMU/subsite pages.
    let ajaxurl = '<?php echo esc_url(get_admin_url(get_current_blog_id(), "admin-ajax.php")); ?>';
    if (window.location.protocol === 'https:' && ajaxurl.indexOf('http:') === 0) {
        ajaxurl = ajaxurl.replace(/^http:/, 'https:');
    }
    const authNonce = (typeof obn_ajax !== 'undefined' && obn_ajax.auth_nonce)
        ? obn_ajax.auth_nonce
        : '<?php echo esc_js(wp_create_nonce('obn_auth_nonce')); ?>';
    
    var selectedItems = {};

    // Search items
    var searchTimeout;
    $('#obn-labels-search').on('keyup', function() {
        clearTimeout(searchTimeout);
        var val = this.value;
        if (val.length < 2) { $('#obn-labels-search-results').addClass('hidden'); return; }
        searchTimeout = setTimeout(function() {
            $.get(ajaxurl, {
                action: 'obn_search_items_for_labels',
                q: val,
                security: authNonce
            }, function(response) {
                var html = '';
                if (response.length) {
                    response.forEach(function(item) {
                        if (selectedItems[item.id]) return;
                        html += '<div class="p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 flex items-center justify-between obn-label-result" data-id="' + item.id + '" data-name="' + $('<span>').text(item.item_name).html() + '" data-code="' + $('<span>').text(item.item_code).html() + '" data-price="' + item.sales_price + '">';
                        html += '<div><div class="font-medium text-gray-800">' + $('<span>').text(item.item_name).html() + '</div><div class="text-xs text-gray-500">' + $('<span>').text(item.item_code).html() + (item.sku ? ' | SKU: ' + item.sku : '') + '</div></div>';
                        html += '<div class="text-sm font-semibold text-blue-600">$' + parseFloat(item.sales_price).toFixed(2) + '</div>';
                        html += '</div>';
                    });
                } else {
                    html = '<div class="p-4 text-center text-gray-500">No items found.</div>';
                }
                $('#obn-labels-search-results').html(html).removeClass('hidden');
            });
        }, 300);
    });

    // Add item to selection
    $(document).on('click', '.obn-label-result', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var code = $(this).data('code');
        var price = $(this).data('price');
        selectedItems[id] = { id: id, name: name, code: code, price: price };
        renderSelected();
        $('#obn-labels-search').val('');
        $('#obn-labels-search-results').addClass('hidden');
    });

    // Remove item from selection
    $(document).on('click', '.obn-label-remove', function() {
        var id = $(this).data('id');
        delete selectedItems[id];
        renderSelected();
    });

    // Clear all
    $('#obn-clear-labels').on('click', function() {
        selectedItems = {};
        renderSelected();
    });

    function renderSelected() {
        var container = $('#obn-labels-selected');
        var btn = $('#obn-print-labels-btn');
        var ids = Object.keys(selectedItems);
        if (ids.length === 0) {
            container.html('<p class="text-gray-400 text-center mt-8">Search and select items to add to labels</p>');
            btn.prop('disabled', true);
            return;
        }
        btn.prop('disabled', false);
        var html = '<div class="grid gap-2">';
        ids.forEach(function(id) {
            var item = selectedItems[id];
            html += '<div class="flex items-center justify-between bg-gray-50 p-3 rounded-lg border border-gray-200">';
            html += '<div><div class="font-medium text-gray-800">' + $('<span>').text(item.name).html() + '</div><div class="text-xs text-gray-500">' + $('<span>').text(item.code).html() + '</div></div>';
            html += '<div class="flex items-center gap-3">';
            html += '<span class="text-sm font-semibold">$' + parseFloat(item.price).toFixed(2) + '</span>';
            html += '<button type="button" class="obn-label-remove text-red-500 hover:text-red-700" data-id="' + id + '"><i class="fa-solid fa-times"></i></button>';
            html += '</div></div>';
        });
        html += '</div>';
        container.html(html);
    }

    // Print labels
    $('#obn-print-labels-btn').on('click', function() {
        var ids = Object.keys(selectedItems);
        if (ids.length === 0) { alert('Please select at least one item.'); return; }
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Processing...');
        $.post(ajaxurl, {
            action: 'obn_print_labels',
            items: ids,
            security: authNonce
        }, function(response) {
            if (response.success && response.data.html) {
                var printWindow = window.open('', '_blank');
                printWindow.document.write(response.data.html);
                printWindow.document.close();
            } else {
                alert(response.data || 'Failed to generate labels.');
            }
            btn.prop('disabled', false).html('<i class="fa-solid fa-print mr-2"></i> Print Labels');
        }).fail(function() {
            alert('Request failed.');
            btn.prop('disabled', false).html('<i class="fa-solid fa-print mr-2"></i> Print Labels');
        });
    });
});
</script>
