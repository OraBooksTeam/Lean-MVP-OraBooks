<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Fetch dropdown data
$warehouses = $wpdb->get_results("SELECT id, warehouse_name FROM {$wpdb->prefix}orabooks_db_warehouse WHERE status = 1 ORDER BY warehouse_name ASC");
$categories = $wpdb->get_results("SELECT id, category_name FROM {$wpdb->prefix}orabooks_db_category WHERE status = 1 ORDER BY category_name ASC");
$brands     = $wpdb->get_results("SELECT id, brand_name FROM {$wpdb->prefix}orabooks_db_brands WHERE status = 1 ORDER BY brand_name ASC");
$items_list = $wpdb->get_results("SELECT id, item_name, item_code FROM {$wpdb->prefix}orabooks_db_items WHERE status = 1 ORDER BY item_name ASC");
$users      = get_users(['orderby' => 'display_name']);

$currency = '৳';
$search_nonce = wp_create_nonce('frontend_ajax_nonce');
?>

<div class="container mx-auto px-4 py-6 text-gray-800">
    <div class="flex justify-between items-center mb-6 no-print">
        <h1 class="text-2xl font-bold text-gray-800">Stock Transfer Report</h1>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm p-5 mb-6 border border-gray-200 no-print">
        <form id="st-search-form" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">From Warehouse</label>
                <select id="from_warehouse_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
                    <option value="">All Warehouses</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?php echo $w->id; ?>"><?php echo esc_html($w->warehouse_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">To Warehouse</label>
                <select id="to_warehouse_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
                    <option value="">All Warehouses</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?php echo $w->id; ?>"><?php echo esc_html($w->warehouse_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Item Category</label>
                <select id="category_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat->id; ?>"><?php echo esc_html($cat->category_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Item Brand</label>
                <select id="brand_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
                    <option value="">All Brands</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?php echo $b->id; ?>"><?php echo esc_html($b->brand_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Item Name</label>
                <select id="item_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
                    <option value="">All Items</option>
                    <?php foreach ($items_list as $it): ?>
                        <option value="<?php echo $it->id; ?>"><?php echo esc_html($it->item_name . ' (' . $it->item_code . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
                <select id="user_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
                    <option value="">All Users</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u->ID; ?>"><?php echo esc_html($u->display_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-4 flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-8 rounded transition-colors duration-200">
                    Search
                </button>
            </div>
        </form>
    </div>

    <!-- Export Buttons & Column Visibility -->
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-4 no-print">
        <!-- Search Box -->
        <div class="relative w-full md:w-1/3">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <i class="fa-solid fa-search text-gray-400"></i>
            </div>
            <input type="search" id="tableSearch" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 sm:text-sm" placeholder="Search in table...">
        </div>

        <div class="flex justify-end gap-2 flex-wrap w-full md:w-auto">
            <button id="printBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors" title="Print">
                <i class="fa-solid fa-print mr-1"></i> Print
            </button>
            <button id="pdfBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors" title="Export to PDF">
                <i class="fa-solid fa-file-pdf mr-1 text-red-600"></i> PDF
            </button>
            <button id="excelBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors" title="Export to Excel">
                <i class="fa-solid fa-file-excel mr-1 text-green-600"></i> Excel
            </button>
            <button id="csvBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors" title="Export to CSV">
                <i class="fa-solid fa-file-csv mr-1 text-blue-600"></i> CSV
            </button>
            
            <!-- Column Visibility Dropdown -->
            <div class="relative">
                <button id="columnToggleBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors" title="Toggle Columns">
                    <i class="fa-solid fa-columns mr-1"></i> Columns
                </button>
                <div id="columnDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                    <div class="p-3 space-y-2 max-h-60 overflow-y-auto text-sm">
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="0" checked> #
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="1" checked> Transfer Date
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="2" checked> From Warehouse
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="3" checked> To Warehouse
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="4" checked> Item Name
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="5" checked> Category Name
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="6" checked> Brand Name
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="7" checked> Quantity
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="8" checked> User
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden border border-gray-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-indigo-500 text-white">
                    <tr>
                        <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider">#</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Transfer Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">From Warehouse</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">To Warehouse</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Item Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Category Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Brand Name</th>
                        <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider">Quantity</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">User</th>
                    </tr>
                </thead>
                <tbody id="st-table-body" class="bg-white divide-y divide-gray-200">
                     <tr>
                        <td colspan="9" class="px-6 py-10 text-center text-gray-500">Click Search to load data</td>
                    </tr>
                </tbody>
                <tfoot class="bg-gray-100 font-bold text-gray-700">
                    <tr>
                        <td colspan="7" class="px-6 py-3 text-right">Total Quantity:</td>
                        <td id="foot-qty" class="px-6 py-3 text-center">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    function calculateFooter() {
        let qty = 0;
        $('#st-table-body tr:visible').each(function() {
            const q = parseFloat($(this).find('.qty').text().replace(/,/g, '')) || 0;
            qty += q;
        });
        $('#foot-qty').text(qty.toLocaleString('en-US', {minimumFractionDigits: 2}));
    }

    // Client-side search
    $('#tableSearch').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $('#st-table-body tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
        calculateFooter();
    });

    $('#st-search-form').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: typeof frontend_inventory_ajax !== 'undefined' ? frontend_inventory_ajax.ajax_url : '<?php echo admin_url("admin-ajax.php"); ?>',
            type: 'POST',
            data: {
                action: 'search_stock_transfer_report',
                security: '<?php echo $search_nonce; ?>',
                start_date: $('#start_date').val(),
                end_date: $('#end_date').val(),
                from_warehouse_id: $('#from_warehouse_id').val(),
                to_warehouse_id: $('#to_warehouse_id').val(),
                category_id: $('#category_id').val(),
                brand_id: $('#brand_id').val(),
                item_id: $('#item_id').val(),
                user_id: $('#user_id').val()
            },
            beforeSend: function() {
                $('#st-table-body').html('<tr><td colspan="9" class="px-6 py-10 text-center text-gray-500"><i class="fa-solid fa-spinner fa-spin text-2xl"></i><div class="mt-2">Loading...</div></td></tr>');
            },
            success: function(res) {
                $('#st-table-body').html(res);
                calculateFooter();
            }
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
        
        $('#st-table-body tr').each(function() {
            $(this).find('td').eq(column).toggle(isChecked);
        });
        $('table thead tr th').eq(column).toggle(isChecked);
        
        if(column == 7) $('#foot-qty').toggle(isChecked);
    });

    // --- Export Logic ---
    function getTableData() {
        const data = [];
        const headers = [];
        $('table thead tr th').each(function() {
            if($(this).is(':visible')) headers.push($(this).text().trim());
        });
        data.push(headers);
        
        $('#st-table-body tr').each(function() {
            const row = [];
            $(this).find('td').each(function() {
                if($(this).is(':visible')) row.push($(this).text().trim());
            });
            if(row.length > 0 && row[0] !== 'Click Search to load data' && row[0] !== 'No records found') {
                data.push(row);
            }
        });
        return data;
    }

    $('#printBtn').on('click', function() {
        const printWindow = window.open('', '', 'height=600,width=900');
        printWindow.document.write('<html><head><title>Stock Transfer Report</title>');
        printWindow.document.write('<style>body{font-family:Arial,sans-serif;font-size:12px;}table{width:100%;border-collapse:collapse;margin-top:20px;}th,td{border:1px solid #ddd;padding:6px;text-align:left;}th{background-color:#eee;font-weight:bold;}.text-right{text-align:right;}.text-center{text-align:center;}h1{text-align:center;color:#333;}</style></head><body>');
        printWindow.document.write('<h1>Stock Transfer Report</h1>');
        const tableData = getTableData();
        printWindow.document.write('<table>');
        tableData.forEach((row, i) => {
            printWindow.document.write('<tr>');
            row.forEach(cell => { printWindow.document.write((i===0?'<th>':'<td>')+cell+(i===0?'</th>':'</td>')); });
            printWindow.document.write('</tr>');
        });
        printWindow.document.write('</table><div style="margin-top:10px; font-weight:bold; text-align:right;">' + $('tfoot').text() + '</div></body></html>');
        printWindow.document.close();
        printWindow.print();
    });

    $('#pdfBtn').on('click', function() {
        if (typeof window.jspdf === 'undefined') {
            const s1 = document.createElement('script'); s1.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
            s1.onload = () => {
                const s2 = document.createElement('script'); s2.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js';
                s2.onload = () => exportPDF(); document.head.appendChild(s2);
            };
            document.head.appendChild(s1);
        } else exportPDF();
    });

    function exportPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'mm', 'a4'); 
        doc.text('Stock Transfer Report', 14, 15);
        const tableData = getTableData();
        doc.autoTable({ head: [tableData[0]], body: tableData.slice(1), startY: 20, theme: 'grid', styles: {fontSize: 8} });
        doc.save('stock-transfer-report.pdf');
    }

    $('#excelBtn').on('click', function() {
        if (typeof XLSX === 'undefined') {
            const s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
            s.onload = () => exportExcel(); document.head.appendChild(s);
        } else exportExcel();
    });

    function exportExcel() {
        const ws = XLSX.utils.aoa_to_sheet(getTableData());
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'StockTransfer');
        XLSX.writeFile(wb, 'stock-transfer-report.xlsx');
    }

    $('#csvBtn').on('click', function() {
        let csv = getTableData().map(row => row.map(cell => '"' + (cell || '').replace(/"/g, '""') + '"').join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'stock-transfer-report.csv';
        link.click();
    });
});
</script>
<style>
@media print {
    body * { visibility: hidden; }
    .container, .container * { visibility: visible; }
    .container { position: absolute; left: 0; top: 0; width: 100%; }
    .no-print, form, button { display: none !important; }
}
</style>
