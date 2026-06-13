<?php
if (!defined('ABSPATH')) exit;

wp_enqueue_script('jquery-ui-autocomplete');
?>
<!-- JsBarcode -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<!-- jsPDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

<div class="p-4 md:p-6">
    <div class="max-w-6xl mx-auto mb-6 md:mb-8 print:hidden">
        <div class="flex flex-col md:flex-row items-start md:items-center gap-4">
            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gradient-to-br from-gray-700 to-gray-900 flex-shrink-0 flex items-center justify-center shadow-lg">
                <i class="fa-solid fa-barcode text-white text-xl md:text-2xl"></i>
            </div>
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Print Labels</h1>
                <p class="text-sm md:text-base text-gray-500 mt-1">Generate and print barcodes for your inventory items</p>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto print:w-full print:max-w-none">
        
        <!-- Search & Control Panel (Hidden on Print) -->
        <div class="bg-white rounded-2xl p-4 md:p-6 shadow-xl border border-gray-100 mb-8 print:hidden">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Search -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Search Item</label>
                    <div class="relative">
                        <input type="search" id="item-autocomplete" 
                            class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all text-sm md:text-base"
                            placeholder="Type item name or code...">
                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-gray-400"></i>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">Start typing to search items...</p>
                </div>

                <!-- Preview Controls -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-end justify-end gap-3 mt-2 sm:mt-0">
                    <button type="button" id="btn-preview" class="flex-1 sm:flex-none justify-center px-4 md:px-6 py-2.5 md:py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-colors shadow-md flex items-center gap-2 text-sm md:text-base">
                        <i class="fa-solid fa-eye"></i> Generate Preview
                    </button>
                    <button type="button" onclick="window.print()" class="flex-1 sm:flex-none justify-center px-4 md:px-6 py-2.5 md:py-3 bg-gray-800 hover:bg-gray-900 text-white font-bold rounded-xl transition-colors shadow-md flex items-center gap-2 text-sm md:text-base">
                        <i class="fa-solid fa-print"></i> Print
                    </button>
                    <a href="<?php echo esc_url(add_query_arg('view', 'view-items')); ?>" class="flex-1 sm:flex-none justify-center px-4 md:px-6 py-2.5 md:py-3 bg-white border border-gray-300 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 transition-colors text-center text-sm md:text-base">
                        Close
                    </a>
                </div>
            </div>

            <!-- Selected Items Table -->
            <div class="mt-8">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Items to Print</h3>
                <div class="overflow-x-auto border border-gray-200 rounded-xl">
                    <table class="w-full text-left text-sm text-gray-500">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-700">
                            <tr>
                                <th class="px-4 md:px-6 py-3 whitespace-nowrap">Item Name</th>
                                <th class="px-4 md:px-6 py-3 w-28 md:w-32 whitespace-nowrap">Quantity</th>
                                <th class="px-4 md:px-6 py-3 w-16 md:w-20 text-right whitespace-nowrap">Action</th>
                            </tr>
                        </thead>
                        <tbody id="label-items-tbody" class="divide-y divide-gray-200 bg-white">
                            <!-- Rows via JS -->
                            <tr id="empty-row">
                                <td colspan="3" class="px-6 py-8 text-center text-gray-400 italic">
                                    No items selected. Search and add items above.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold text-gray-900">
                            <tr>
                                <td class="px-4 md:px-6 py-3 text-right">Total Labels:</td>
                                <td class="px-4 md:px-6 py-3"><span id="total-qty">0</span></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Print Preview Area -->
        <div id="preview-section" class="hidden print:block mt-8">
            <div class="flex items-center justify-between mb-4 print:hidden">
                <h3 class="text-lg font-bold text-gray-800">Label Preview</h3>
                <button type="button" id="btn-pdf" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl transition-colors shadow-md flex items-center gap-2 text-sm">
                    <i class="fa-solid fa-file-pdf"></i> Export to PDF
                </button>
            </div>
            <div id="print-area" class="flex flex-wrap gap-2 justify-start print:gap-0 print:block">
                <!-- Labels generated here -->
            </div>
        </div>

    </div>
</div>

<style>
    /* Print Styles */
    @media print {
        @page { margin: 0; size: auto; }
        body * { visibility: hidden; }
        #print-area, #print-area * { visibility: visible; }
        #print-area { 
            position: absolute; 
            left: 0; 
            top: 0; 
            width: 100%; 
            padding: 10px;
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            gap: 5px; /* Adjust based on label sheet spacing */
        }
        
        .label-item {
            border: none !important; /* Remove borders for final print if using sticker paper */
            page-break-inside: avoid;
        }
        
        /* Ensure background colors/images print */
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }

    /* Label Styling */
    .label-item {
        width: 2.5in; 
        height: 1in;
        border: 1px dotted #ccc; /* Guide border for preview */
        background: white;
        padding: 4px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        overflow: hidden;
        box-sizing: border-box;
    }
    
    .barcode-svg {
        max-width: 95%;
        height: 35px;
        margin: 2px 0;
    }
</style>

<script>
jQuery(document).ready(function($) {
    let itemsData = [];

    // Autocomplete
    $('#item-autocomplete').autocomplete({
        source: function(request, response) {
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'frontend_search_items_for_labels',
                    security: typeof frontend_inventory_ajax !== 'undefined' ? frontend_inventory_ajax.nonce : '<?php echo wp_create_nonce("frontend_ajax_nonce"); ?>',
                    term: request.term
                },
                success: function(res) {
                    if (res.success && res.data && res.data.length > 0) {
                        response(res.data.map(function(i) {
                            return {
                                label: i.item_name + ' (' + i.item_code + ')',
                                value: i.item_name,
                                data: i
                            };
                        }));
                    } else {
                        response([]);
                    }
                }
            });
        },
        minLength: 1,
        select: function(event, ui) {
            addItemRow(ui.item.data);
            $(this).val('');
            return false;
        }
    });

    function addItemRow(item) {
        // Prevent dupes
        if (itemsData.find(i => i.id == item.id)) {
            alert('Item already added.');
            return;
        }

        itemsData.push(item);
        $('#empty-row').hide();
        
        let rowId = 'row-' + item.id;
        let html = `
            <tr id="${rowId}" data-id="${item.id}" class="hover:bg-gray-50 transition-colors">
                <td class="px-4 md:px-6 py-3">
                    <div class="font-bold text-gray-800 text-sm md:text-base">${item.item_name}</div>
                    <div class="text-xs text-gray-500">Code: ${item.item_code}</div>
                </td>
                <td class="px-4 md:px-6 py-3">
                    <input type="number" class="qty-input w-20 px-3 py-1 border border-gray-300 rounded focus:border-blue-500 focus:outline-none text-center text-sm md:text-base" 
                           value="1" min="1" onchange="updateTotal()">
                </td>
                <td class="px-4 md:px-6 py-3 text-right">
                    <button type="button" class="text-red-500 hover:text-red-700 transition-colors w-8 h-8 rounded-full hover:bg-red-50 flex items-center justify-center ml-auto" 
                            onclick="removeRow(${item.id})">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#label-items-tbody').append(html);
        updateTotal();
    }

    window.removeRow = function(id) {
        itemsData = itemsData.filter(i => i.id != id);
        $('#row-' + id).remove();
        if(itemsData.length === 0) $('#empty-row').show();
        updateTotal();
    };

    window.updateTotal = function() {
        let total = 0;
        $('.qty-input').each(function() {
            total += parseInt($(this).val()) || 0;
        });
        $('#total-qty').text(total);
    };

    // Generate Preview
    $('#btn-preview').click(function() {
        if (itemsData.length === 0) {
            alert('Please add items first.');
            return;
        }

        let container = $('#print-area');
        container.empty();

        $('#label-items-tbody tr').not('#empty-row').each(function() {
            let id = $(this).data('id');
            let item = itemsData.find(i => i.id == id);
            
            if (!item) return; // Skip if item data not found
            
            let qty = parseInt($(this).find('.qty-input').val()) || 0;
            let codeValue = item.item_code || ('ITEM-' + item.id);

            for (let k = 0; k < qty; k++) {
                let uniqueId = 'barcode-' + id + '-' + k;
                
                let labelHtml = `
                    <div class="label-item">
                        <h4 class="text-xs font-bold truncate w-full px-1 leading-tight mb-0.5">${item.item_name}</h4>
                        <svg id="${uniqueId}" class="barcode-svg"></svg>
                        <div class="text-[10px] leading-tight flex gap-2 justify-center mt-0.5">
                             ${item.price > 0 ? `<span class="font-semibold">Price: ${parseFloat(item.price).toFixed(2)}</span>` : ''}
                             <span class="text-gray-600">${item.item_code}</span>
                        </div>
                    </div>
                `;
                container.append(labelHtml);

                // Generate Barcode
                try {
                    JsBarcode("#" + uniqueId, codeValue, {
                        format: "CODE128",
                        width: 1.5,
                        height: 30,
                        displayValue: false,
                        margin: 0
                    });
                } catch(e) {
                    console.error("Barcode error for " + codeValue, e);
                }
            }
        });

        $('#preview-section').removeClass('hidden');
        $('html, body').animate({
            scrollTop: $("#preview-section").offset().top - 20
        }, 500);
    });

    // PDF Export
    $('#btn-pdf').click(function() {
        if (itemsData.length === 0) {
            alert('Please generate preview first.');
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({
            orientation: 'landscape',
            unit: 'mm',
            format: 'a4'
        });

        // Add title
        doc.setFontSize(16);
        doc.text('Barcode Labels', 10, 10);

        // Get all label items
        const labels = [];
        $('#print-area .label-item').each(function(index) {
            const $label = $(this);
            const itemName = $label.find('h4').text().trim();
            const price = $label.find('.font-semibold').text().trim();
            const code = $label.find('.text-gray-600').text().trim();
            const barcodeSvg = $label.find('svg').get(0);
            
            // Convert SVG to data URL
            const svgData = new XMLSerializer().serializeToString(barcodeSvg);
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const img = new Image();
            
            img.onload = function() {
                canvas.width = 180; // 2.5 inches in mm (approx)
                canvas.height = 72;  // 1 inch in mm (approx)
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                
                const imgData = canvas.toDataURL('image/png');
                labels.push({
                    name: itemName,
                    price: price,
                    code: code,
                    barcode: imgData
                });
                
                // When all labels are processed, generate PDF
                if (labels.length === $('#print-area .label-item').length) {
                    generatePDF(doc, labels);
                }
            };
            
            img.src = 'data:image/svg+xml;base64,' + btoa(svgData);
        });
    });

    function generatePDF(doc, labels) {
        let yPosition = 25;
        const pageHeight = doc.internal.pageSize.height;
        const labelWidth = 60;
        const labelHeight = 25;
        const labelsPerRow = 4;
        const margin = 5;
        
        labels.forEach((label, index) => {
            const row = Math.floor(index / labelsPerRow);
            const col = index % labelsPerRow;
            const x = margin + (col * (labelWidth + margin));
            const y = yPosition + (row * (labelHeight + margin));
            
            // Check if we need a new page
            if (y + labelHeight > pageHeight - 10) {
                doc.addPage();
                yPosition = 25;
                const newRow = Math.floor(index / labelsPerRow);
                const newCol = index % labelsPerRow;
                y = yPosition + (newRow * (labelHeight + margin));
            }
            
            // Draw label border
            doc.rect(x, y, labelWidth, labelHeight);
            
            // Add item name (truncated)
            doc.setFontSize(8);
            const truncatedName = label.name.length > 15 ? label.name.substring(0, 15) + '...' : label.name;
            doc.text(truncatedName, x + 2, y + 5);
            
            // Add barcode image
            try {
                doc.addImage(label.barcode, 'PNG', x + 2, y + 7, 56, 10);
            } catch(e) {
                console.error('Error adding barcode image:', e);
            }
            
            // Add price and code
            doc.setFontSize(6);
            if (label.price) {
                doc.text(label.price, x + 2, y + 20);
            }
            doc.text(label.code, x + 2, y + 23);
        });
        
        // Save PDF
        const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
        doc.save('barcode-labels-' + timestamp + '.pdf');
    }
});
</script>
