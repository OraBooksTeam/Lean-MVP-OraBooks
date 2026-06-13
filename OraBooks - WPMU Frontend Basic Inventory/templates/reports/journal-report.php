<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Fetch dropdown data
$users = get_users(['orderby'=>'display_name']);
$source_types = $wpdb->get_col("SELECT DISTINCT source_type FROM {$wpdb->prefix}orabooks_ac_journal_entry WHERE source_type IS NOT NULL AND source_type != ''");

// Initial stats
$stats = $wpdb->get_row("SELECT SUM(total_debit) as total_debit, SUM(total_credit) as total_credit FROM {$wpdb->prefix}orabooks_ac_journal_entry WHERE status = 'Posted'");

$currency = '৳';
$search_nonce = wp_create_nonce('frontend_ajax_nonce');
?>

<div class="container mx-auto px-4 py-6 text-gray-800">
    <div class="flex justify-between items-center mb-6 no-print">
        <div class="flex items-center gap-3">
            <div class="bg-blue-600 p-2 rounded-lg text-white">
                <i class="fa-solid fa-book-journal-whills text-xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Journal Report</h1>
        </div>
        <div class="flex gap-2">
             <button onclick="window.location.reload()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded transition-colors duration-200">
                <i class="fa-solid fa-rotate mr-2"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 no-print">
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-medium text-gray-500 mb-1">Total Debit</h3>
                <p class="text-2xl font-bold text-gray-900"><?php echo $currency . ' ' . number_format($stats->total_debit ?? 0, 2); ?></p>
            </div>
            <div class="bg-blue-50 p-4 rounded-full text-blue-600">
                <i class="fa-solid fa-arrow-up-right-dots text-2xl"></i>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 flex items-center justify-between">
            <div>
                 <h3 class="text-sm font-medium text-gray-500 mb-1">Total Credit</h3>
                 <p class="text-2xl font-bold text-gray-900"><?php echo $currency . ' ' . number_format($stats->total_credit ?? 0, 2); ?></p>
            </div>
            <div class="bg-green-50 p-4 rounded-full text-green-600">
                <i class="fa-solid fa-arrow-down-left-dots text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border border-gray-100 no-print">
        <form id="search-form" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Start Date</label>
                <input type="date" id="start_date" class="w-full rounded-lg border-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5 px-3 border bg-gray-50">
            </div>
            <div>
                 <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">End Date</label>
                 <input type="date" id="end_date" class="w-full rounded-lg border-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5 px-3 border bg-gray-50">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Source Type</label>
                <select id="source_type" class="w-full rounded-lg border-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5 px-3 border bg-gray-50">
                    <option value="">All Types</option>
                    <?php foreach ($source_types as $st): ?>
                        <option value="<?php echo esc_attr($st); ?>"><?php echo esc_html($st); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Creator</label>
                <select id="user_id" class="w-full rounded-lg border-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5 px-3 border bg-gray-50">
                    <option value="">All Users</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u->ID; ?>"><?php echo esc_html($u->display_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-lg transition-all duration-200 shadow-sm">
                    <i class="fa-solid fa-magnifying-glass mr-2"></i> Fetch Journals
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
            <input type="search" id="tableSearch" class="block w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-xl leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 sm:text-sm shadow-sm" placeholder="Filter journals in view...">
        </div>

        <div class="flex justify-end gap-2 flex-wrap w-full md:w-auto">
            <button id="printBtn" class="text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-4 py-2 transition-all shadow-sm">
                <i class="fa-solid fa-print mr-1"></i> Print
            </button>
            <button id="pdfBtn" class="text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-4 py-2 transition-all shadow-sm">
                <i class="fa-solid fa-file-pdf mr-1 text-red-600"></i> PDF
            </button>
            <button id="excelBtn" class="text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-4 py-2 transition-all shadow-sm">
                <i class="fa-solid fa-file-excel mr-1 text-green-600"></i> Excel
            </button>
            
            <!-- Column Visibility Dropdown -->
            <div class="relative">
                <button id="columnToggleBtn" class="text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-4 py-2 transition-all shadow-sm">
                    <i class="fa-solid fa-columns mr-1"></i> Columns
                </button>
                <div id="columnDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden">
                    <div class="p-4 space-y-3">
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded transition-colors text-sm font-medium text-gray-700">
                            <input type="checkbox" class="column-toggle rounded border-gray-300 text-blue-600 mr-3" data-column="0" checked> #
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded transition-colors text-sm font-medium text-gray-700">
                            <input type="checkbox" class="column-toggle rounded border-gray-300 text-blue-600 mr-3" data-column="1" checked> Date
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded transition-colors text-sm font-medium text-gray-700">
                            <input type="checkbox" class="column-toggle rounded border-gray-300 text-blue-600 mr-3" data-column="2" checked> Journal ID
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded transition-colors text-sm font-medium text-gray-700">
                            <input type="checkbox" class="column-toggle rounded border-gray-300 text-blue-600 mr-3" data-column="3" checked> Reference
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded transition-colors text-sm font-medium text-gray-700">
                            <input type="checkbox" class="column-toggle rounded border-gray-300 text-blue-600 mr-3" data-column="4" checked> Account
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded transition-colors text-sm font-medium text-gray-700">
                            <input type="checkbox" class="column-toggle rounded border-gray-300 text-blue-600 mr-3" data-column="5" checked> Description
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded transition-colors text-sm font-medium text-gray-700">
                            <input type="checkbox" class="column-toggle rounded border-gray-300 text-blue-600 mr-3" data-column="6" checked> Debit
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded transition-colors text-sm font-medium text-gray-700">
                            <input type="checkbox" class="column-toggle rounded border-gray-300 text-blue-600 mr-3" data-column="7" checked> Credit
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-indigo-500 text-white">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">#</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Date</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Journal ID</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Reference</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Account</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Description</th>
                        <th class="px-6 py-4 text-right text-xs font-bold uppercase tracking-wider">Debit</th>
                        <th class="px-6 py-4 text-right text-xs font-bold uppercase tracking-wider">Credit</th>
                    </tr>
                </thead>
                <tbody id="journal-table-body" class="bg-white divide-y divide-gray-200">
                     <tr>
                        <td colspan="8" class="px-6 py-10 text-center text-gray-500">Click Fetch Journals to load data</td>
                    </tr>
                </tbody>
                <tfoot class="bg-gray-50 font-bold text-gray-800">
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-right">Grand Total:</td>
                        <td id="foot-debit" class="px-6 py-4 text-right text-blue-600">0.00</td>
                        <td id="foot-credit" class="px-6 py-4 text-right text-green-600">0.00</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    function calculateFooter() {
        let debit = 0, credit = 0;
        
        $('#journal-table-body tr:visible').each(function() {
            const d = parseFloat($(this).find('.debit-amt').text().replace(/,/g, '')) || 0;
            const c = parseFloat($(this).find('.credit-amt').text().replace(/,/g, '')) || 0;
            debit += d; 
            credit += c;
        });

        $('#foot-debit').text(debit.toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#foot-credit').text(credit.toLocaleString('en-US', {minimumFractionDigits: 2}));
    }

    // Client-side quick filter
    $('#tableSearch').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $('#journal-table-body tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
        calculateFooter();
    });

    $('#search-form').on('submit', function(e) {
        e.preventDefault();
        
        const ajaxData = {
            action: 'search_journal_report',
            security: '<?php echo $search_nonce; ?>',
            start_date: $('#start_date').val(),
            end_date: $('#end_date').val(),
            source_type: $('#source_type').val(),
            user_id: $('#user_id').val()
        };
        
        console.log('Fetching journals with data:', ajaxData);
        
        $.ajax({
            url: typeof frontend_inventory_ajax !== 'undefined' ? frontend_inventory_ajax.ajax_url : '<?php echo admin_url("admin-ajax.php"); ?>',
            type: 'POST',
            data: ajaxData,
            beforeSend: function() {
                $('#journal-table-body').html('<tr><td colspan="8" class="px-6 py-10 text-center text-gray-500"><i class="fa-solid fa-spinner fa-spin text-2xl text-blue-500"></i><div class="mt-2 text-sm font-medium">Reading ledgers...</div></td></tr>');
            },
            success: function(res) {
                console.log('Journal records received:', res.length, 'characters');
                $('#journal-table-body').html(res);
                calculateFooter();
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr.responseText);
                $('#journal-table-body').html('<tr><td colspan="8" class="px-6 py-10 text-center text-red-500">Failed to load journal records: ' + error + '</td></tr>');
            }
        });
    });

    // Column visibility toggle
    $('#columnToggleBtn').on('click', function(e) { e.stopPropagation(); $('#columnDropdown').toggleClass('hidden'); });
    $(document).on('click', function(e) { if (!$(e.target).closest('#columnToggleBtn, #columnDropdown').length) $('#columnDropdown').addClass('hidden'); });
    
    $('.column-toggle').on('change', function() {
        const column = $(this).data('column');
        const isChecked = $(this).is(':checked');
        $('#journal-table-body tr').each(function() { $(this).find('td').eq(column).toggle(isChecked); });
        $('table thead tr th').eq(column).toggle(isChecked);
        if(column == 6) $('#foot-debit').toggle(isChecked);
        if(column == 7) $('#foot-credit').toggle(isChecked);
    });

    // --- Export Logic ---
    function getTableData() {
        const data = []; const headers = [];
        $('table thead tr th').each(function() { if($(this).is(':visible')) headers.push($(this).text().trim()); });
        data.push(headers);
        $('#journal-table-body tr').each(function() {
            const row = [];
            $(this).find('td').each(function() { if($(this).is(':visible')) row.push($(this).text().trim()); });
            if(row.length > 0 && !row[0].includes('Click Fetch') && !row[0].includes('No records')) data.push(row);
        });
        return data;
    }

    $('#printBtn').on('click', function() {
        const printWindow = window.open('', '', 'height=600,width=900');
        printWindow.document.write('<html><head><title>Journal Report</title>');
        printWindow.document.write('<style>body{font-family:"Inter",sans-serif;font-size:12px;}table{width:100%;border-collapse:collapse;margin-top:20px;}th,td{border:1px solid #e2e8f0;padding:10px;text-align:left;}th{background-color:#f8fafc;font-weight:bold;color:#64748b;text-transform:uppercase;font-size:10px;}h1{text-align:center;color:#1e293b;} tfoot{font-weight:bold;background:#f8fafc;}</style></head><body>');
        printWindow.document.write('<h1>Journal Report</h1>');
        const tableData = getTableData();
        printWindow.document.write('<table>');
        tableData.forEach((row, i) => {
            printWindow.document.write('<tr>');
            row.forEach(cell => { printWindow.document.write((i===0?'<th>':'<td>')+cell+(i===0?'</th>':'</td>')); });
            printWindow.document.write('</tr>');
        });
        // Footer manual add for print
        printWindow.document.write('<tfoot><tr><td colspan="'+(tableData[0].length-2)+'" style="text-align:right">Grand Total:</td><td>'+$('#foot-debit').text()+'</td><td>'+$('#foot-credit').text()+'</td></tr></tfoot>');
        printWindow.document.write('</table></body></html>');
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
        doc.setFontSize(18); doc.text('Journal Report', 14, 15); doc.setFontSize(10);
        const tableData = getTableData();
        doc.autoTable({ head: [tableData[0]], body: tableData.slice(1), startY: 25, theme: 'striped', headStyles: {fillColor: [30, 41, 59]}, margin: {top: 25} });
        doc.save('journal-report.pdf');
    }

    $('#excelBtn').on('click', function() {
        if (typeof XLSX === 'undefined') {
            const s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
            s.onload = () => exportExcel(); document.head.appendChild(s);
        } else exportExcel();
    });

    function exportExcel() {
        const tableData = getTableData();
        const ws = XLSX.utils.aoa_to_sheet(tableData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Journals');
        XLSX.writeFile(wb, 'journal-report.xlsx');
    }
});
</script>
<style>
@media print {
    body * { visibility: hidden; }
    .container, .container * { visibility: visible; }
    .container { position: absolute; left: 0; top: 0; width: 100%; }
    .no-print, form, button, .inventory-sidebar { display: none !important; }
}
input[type="date"]::-webkit-calendar-picker-indicator {
    cursor: pointer;
    border-radius: 4px;
    margin-right: 2px;
    opacity: 0.6;
    filter: invert(0.5);
}
</style>
