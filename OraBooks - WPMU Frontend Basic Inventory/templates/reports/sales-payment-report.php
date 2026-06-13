<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Fetch dropdown data
$customers = $wpdb->get_results("SELECT id, customer_name FROM {$wpdb->prefix}orabooks_db_customers WHERE status = 1 ORDER BY customer_name ASC");

$currency = '৳';
$search_nonce = wp_create_nonce('search_sales_payment_report');
?>

<div class="container mx-auto px-4 py-6 text-gray-800">
    <div class="flex justify-between items-center mb-6 no-print">
        <h1 class="text-2xl font-bold text-gray-800">Sales & Payment Report</h1>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm p-5 mb-6 border border-gray-200 no-print">
        <form id="spr-search-form" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Customer <span class="text-red-500">*</span></label>
                <select id="customer_id" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
                    <option value="">Select Customer</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?php echo $c->id; ?>"><?php echo esc_html($c->customer_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex justify-start">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-8 rounded transition-colors duration-200">
                    Search
                </button>
            </div>
        </form>
    </div>

    <!-- Table Top Info -->
    <div id="table-top-info" class="hidden bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6 grid grid-cols-1 md:grid-cols-4 gap-6">
        <div>
            <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Customer Name</span>
            <span id="top-customer-name" class="text-base font-bold text-gray-900">-</span>
        </div>
        <div>
            <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Contact</span>
            <span id="top-contact" class="text-base font-medium text-gray-700">-</span>
        </div>
        <div>
            <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Address</span>
            <span id="top-address" class="text-base font-medium text-gray-700">-</span>
        </div>
        <div>
            <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1 text-red-600">Previous Due</span>
            <span id="top-prev-due" class="text-lg font-bold text-red-600">0.00</span>
        </div>
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
                    <div class="p-3 space-y-2 text-sm">
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="0" checked> #
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="1" checked> Date
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="2" checked> Invoice No.
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="3" checked> Description
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="4" checked> Qty
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="5" checked> Tax
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="6" checked> Discount
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="7" checked> Bill Amt(<?php echo $currency; ?>)
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="8" checked> Receive(<?php echo $currency; ?>)
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="9" checked> Total(<?php echo $currency; ?>)
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
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Invoice No.</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider">Qty</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider">Tax</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider">Discount</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider">Bill Amt(<?php echo $currency; ?>)</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider">Receive(<?php echo $currency; ?>)</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider">Total(<?php echo $currency; ?>)</th>
                    </tr>
                </thead>
                <tbody id="ledger-table-body" class="bg-white divide-y divide-gray-200">
                     <tr>
                        <td colspan="10" class="px-6 py-10 text-center text-gray-500">Search by Customer to view Ledger</td>
                    </tr>
                </tbody>
                <tfoot class="bg-gray-100 font-bold text-gray-700">
                    <tr>
                        <td colspan="5" class="px-6 py-3 text-right">Summary Totals:</td>
                        <td id="foot-tax" class="px-6 py-3 text-right">0.00</td>
                        <td id="foot-discount" class="px-6 py-3 text-right">0.00</td>
                        <td id="foot-bill" class="px-6 py-3 text-right">0.00</td>
                        <td id="foot-recv" class="px-6 py-3 text-right">0.00</td>
                        <td id="foot-total" class="px-6 py-3 text-right">0.00</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    function calculateFooter() {
        let tax = 0, disc = 0, bill = 0, recv = 0, total = 0;
        
        $('#ledger-table-body tr:visible').each(function() {
             // Skip if it's the "loading" or "no records" row
             if($(this).find('td').length < 10) return;

             const t = parseFloat($(this).find('td').eq(5).text().replace(/,/g, '')) || 0;
             const d = parseFloat($(this).find('td').eq(6).text().replace(/,/g, '')) || 0;
             const b = parseFloat($(this).find('td').eq(7).text().replace(/,/g, '')) || 0;
             const r = parseFloat($(this).find('td').eq(8).text().replace(/,/g, '')) || 0;
             const tot = parseFloat($(this).find('td').eq(9).text().replace(/,/g, '')) || 0;
             
             tax += t; disc += d; bill += b; recv += r; total += tot;
        });

        $('#foot-tax').text(tax.toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#foot-discount').text(disc.toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#foot-bill').text(bill.toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#foot-recv').text(recv.toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#foot-total').text(total.toLocaleString('en-US', {minimumFractionDigits: 2}));
    }

    // Client-side search
    $('#tableSearch').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $('#ledger-table-body tr').filter(function() {
            if($(this).find('td').length > 1) {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            }
        });
        calculateFooter();
    });

    $('#spr-search-form').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: typeof frontend_inventory_ajax !== 'undefined' ? frontend_inventory_ajax.ajax_url : '<?php echo admin_url("admin-ajax.php"); ?>',
            type: 'POST',
            data: {
                action: 'search_sales_payment_report',
                security: '<?php echo $search_nonce; ?>',
                start_date: $('#start_date').val(),
                end_date: $('#end_date').val(),
                customer_id: $('#customer_id').val()
            },
            beforeSend: function() {
                $('#ledger-table-body').html('<tr><td colspan="10" class="px-6 py-10 text-center text-gray-500"><i class="fa-solid fa-spinner fa-spin text-2xl"></i><div class="mt-2">Loading Ledger...</div></td></tr>');
            },
            success: function(res) {
                if(res.success) {
                    $('#table-top-info').removeClass('hidden');
                    $('#top-customer-name').text(res.data.customer_name);
                    $('#top-contact').text(res.data.contact);
                    $('#top-address').text(res.data.address);
                    $('#top-prev-due').text(res.data.prev_due);
                    
                    $('#ledger-table-body').html(res.data.html);
                    
                    $('#foot-tax').text(res.data.footer_tax);
                    $('#foot-discount').text(res.data.footer_discount);
                    $('#foot-bill').text(res.data.footer_bill);
                    $('#foot-recv').text(res.data.footer_recv);
                    $('#foot-total').text(res.data.footer_total);
                } else {
                    alert(res.data || 'Error loading data');
                }
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
        
        $('#ledger-table-body tr').each(function() {
            $(this).find('td').eq(column).toggle(isChecked);
        });
        $('table thead tr th').eq(column).toggle(isChecked);
        
        // Footer cell mapping
        if(column == 5) $('#foot-tax').toggle(isChecked);
        if(column == 6) $('#foot-discount').toggle(isChecked);
        if(column == 7) $('#foot-bill').toggle(isChecked);
        if(column == 8) $('#foot-recv').toggle(isChecked);
        if(column == 9) $('#foot-total').toggle(isChecked);
    });

    // --- Export Logic ---
    function getTableData() {
        const data = [];
        const headers = [];
        $('table thead tr th').each(function() {
            if($(this).is(':visible')) headers.push($(this).text().trim());
        });
        data.push(headers);
        
        // Add info rows to export data
        const info = [
            ['Customer Name:', $('#top-customer-name').text()],
            ['Contact:', $('#top-contact').text()],
            ['Address:', $('#top-address').text()],
            ['Previous Due:', $('#top-prev-due').text()],
            [''] // Empty line
        ];
        
        $('#ledger-table-body tr').each(function() {
            const row = [];
            $(this).find('td').each(function() {
                if($(this).is(':visible')) row.push($(this).text().trim());
            });
            if(row.length > 0 && row[0] !== 'Search by Customer to view Ledger' && row[0] !== 'No records found') {
                data.push(row);
            }
        });
        
        // Add footer to data
        const footer = [];
        $('table tfoot tr td').each(function() {
             if($(this).is(':visible')) footer.push($(this).text().trim());
        });
        data.push(footer);

        return data;
    }

    $('#printBtn').on('click', function() {
        const printWindow = window.open('', '', 'height=600,width=900');
        printWindow.document.write('<html><head><title>Sales & Payment Report</title>');
        printWindow.document.write('<style>body{font-family:Arial,sans-serif;font-size:12px;}table{width:100%;border-collapse:collapse;margin-top:20px;}th,td{border:1px solid #ddd;padding:6px;text-align:left;}th{background-color:#eee;font-weight:bold;}.text-right{text-align:right;}.text-center{text-align:center;}h1{text-align:center;color:#333;}.top-info{margin-bottom:20px; font-weight:bold;}</style></head><body>');
        printWindow.document.write('<h1>Sales & Payment Report</h1>');
        
        printWindow.document.write('<div class="top-info">');
        printWindow.document.write('Customer: ' + $('#top-customer-name').text() + '<br>');
        printWindow.document.write('Contact: ' + $('#top-contact').text() + '<br>');
        printWindow.document.write('Address: ' + $('#top-address').text() + '<br>');
        printWindow.document.write('Previous Due: ' + $('#top-prev-due').text() + '<br>');
        printWindow.document.write('</div>');

        const tableData = [];
        const headers = [];
        $('table thead tr th').each(function() { if($(this).is(':visible')) headers.push($(this).text().trim()); });
        tableData.push(headers);
        $('#ledger-table-body tr').each(function() {
            const row = [];
            $(this).find('td').each(function() { if($(this).is(':visible')) row.push($(this).text().trim()); });
            if(row.length > 0) tableData.push(row);
        });

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
        doc.text('Sales & Payment Report', 14, 15);
        
        doc.setFontSize(10);
        doc.text('Customer: ' + $('#top-customer-name').text(), 14, 22);
        doc.text('Contact: ' + $('#top-contact').text(), 80, 22);
        doc.text('Previous Due: ' + $('#top-prev-due').text(), 140, 22);

        const tableData = [];
        const headers = [];
        $('table thead tr th').each(function() { if($(this).is(':visible')) headers.push($(this).text().trim()); });
        $('#ledger-table-body tr').each(function() {
            const row = [];
            $(this).find('td').each(function() { if($(this).is(':visible')) row.push($(this).text().trim()); });
            if(row.length > 0) tableData.push(row);
        });
        
        doc.autoTable({ head: [headers], body: tableData, startY: 28, theme: 'grid', styles: {fontSize: 8} });
        doc.save('sales-payment-ledger.pdf');
    }

    $('#excelBtn').on('click', function() {
        if (typeof XLSX === 'undefined') {
            const s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
            s.onload = () => exportExcel(); document.head.appendChild(s);
        } else exportExcel();
    });

    function exportExcel() {
        const tableData = [
            ['Customer Name', $('#top-customer-name').text()],
            ['Contact', $('#top-contact').text()],
            ['Address', $('#top-address').text()],
            ['Previous Due', $('#top-prev-due').text()],
            [],
        ];
        const headers = [];
        $('table thead tr th').each(function() { if($(this).is(':visible')) headers.push($(this).text().trim()); });
        tableData.push(headers);
        
        $('#ledger-table-body tr').each(function() {
            const row = [];
            $(this).find('td').each(function() { if($(this).is(':visible')) row.push($(this).text().trim()); });
            if(row.length > 0) tableData.push(row);
        });
        
        const ws = XLSX.utils.aoa_to_sheet(tableData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Sales_Payment_Ledger');
        XLSX.writeFile(wb, 'sales-payment-ledger.xlsx');
    }

    $('#csvBtn').on('click', function() {
        const tableData = [['Sales & Payment Report'], []];
        tableData.push(['Customer', $('#top-customer-name').text()]);
        tableData.push(['Contact', $('#top-contact').text()]);
        tableData.push(['Previous Due', $('#top-prev-due').text()]);
        tableData.push([]);
        
        const headers = [];
        $('table thead tr th').each(function() { if($(this).is(':visible')) headers.push($(this).text().trim()); });
        tableData.push(headers);
        
        $('#ledger-table-body tr').each(function() {
            const row = [];
            $(this).find('td').each(function() { if($(this).is(':visible')) row.push($(this).text().trim()); });
            if(row.length > 0) tableData.push(row);
        });

        let csv = tableData.map(row => row.map(cell => '"' + (cell || '').toString().replace(/"/g, '""') + '"').join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'sales-payment-ledger.csv';
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
