<?php
if (!defined('ABSPATH'))
    exit;

global $wpdb;

$currency = '৳';
$search_nonce = wp_create_nonce('frontend_ajax_nonce');
?>

<div class="container mx-auto px-4 py-6 text-gray-800">
    <div class="flex justify-between items-center mb-6 no-print">
        <div class="flex items-center gap-3">
            <div class="bg-indigo-600 p-2 rounded-lg text-white">
                <i class="fa-solid fa-file-invoice-dollar text-xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Income Statement</h1>
        </div>
        <div class="flex gap-2">
            <button onclick="window.location.reload()"
                class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded transition-colors duration-200">
                <i class="fa-solid fa-rotate mr-2"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border border-gray-100 no-print">
        <form id="income-statement-search-form" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Start
                    Date</label>
                <input type="date" id="income_start_date" value="<?php echo date('Y-m-01'); ?>"
                    class="w-full rounded-lg border-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5 px-3 border bg-gray-50">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">End Date</label>
                <input type="date" id="income_end_date" value="<?php echo date('Y-m-d'); ?>"
                    class="w-full rounded-lg border-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5 px-3 border bg-gray-50">
            </div>
            <div>
                <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-4 rounded-lg transition-all duration-200 shadow-sm">
                    <i class="fa-solid fa-magnifying-glass mr-2"></i> Generate
                </button>
            </div>
        </form>
    </div>

    <!-- Export Buttons & Search -->
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-4 no-print">
        <div class="relative w-full md:w-1/3">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <i class="fa-solid fa-search text-gray-400"></i>
            </div>
            <input type="search" id="incomeTableSearch"
                class="block w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-xl leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 sm:text-sm shadow-sm"
                placeholder="Search categories...">
        </div>

        <div class="flex justify-end gap-2 flex-wrap w-full md:w-auto">
            <button id="incomePrintBtn"
                class="text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 font-medium rounded-lg text-sm px-4 py-2 transition-all shadow-sm">
                <i class="fa-solid fa-print mr-1"></i> Print
            </button>
            <button id="incomePdfBtn"
                class="text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 font-medium rounded-lg text-sm px-4 py-2 transition-all shadow-sm">
                <i class="fa-solid fa-file-pdf mr-1 text-red-600"></i> PDF
            </button>
            <button id="incomeExcelBtn"
                class="text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 font-medium rounded-lg text-sm px-4 py-2 transition-all shadow-sm">
                <i class="fa-solid fa-file-excel mr-1 text-green-600"></i> Excel
            </button>
        </div>
    </div>

    <!-- Period Display -->
    <div id="income-report-header" class="mb-4 text-center hidden">
        <h2 class="text-xl font-bold text-gray-800">Income Statement</h2>
        <p class="text-sm text-gray-600 italic mt-1" id="income-period-text"></p>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200" id="income-statement-table">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Description</th>
                        <th class="px-6 py-4 text-right text-xs font-bold uppercase tracking-wider">Account Total
                            (<?php echo $currency; ?>)</th>
                        <th class="px-6 py-4 text-right text-xs font-bold uppercase tracking-wider">Total
                            (<?php echo $currency; ?>)</th>
                    </tr>
                </thead>
                <tbody id="income-report-table-body" class="bg-white divide-y divide-gray-200">
                    <tr>
                        <td colspan="3" class="px-6 py-10 text-center text-gray-500">Click Generate to load income data
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function ($) {

        $('#incomeTableSearch').on('keyup', function () {
            const value = $(this).val().toLowerCase();
            $('#income-report-table-body tr').filter(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        $('#income-statement-search-form').on('submit', function (e) {
            e.preventDefault();

            const start = $('#income_start_date').val();
            const end = $('#income_end_date').val();

            const ajaxData = {
                action: 'search_income_statement_report',
                security: '<?php echo $search_nonce; ?>',
                start_date: start,
                end_date: end
            };

            $.ajax({
                url: typeof obn_ajax !== 'undefined' ? obn_ajax.ajax_url : (typeof obn_accounting_ajax !== 'undefined' ? obn_accounting_ajax.ajax_url : '<?php echo esc_url(obn_ajax_admin_url()); ?>'),
                type: 'POST',
                data: ajaxData,
                beforeSend: function () {
                    $('#income-report-table-body').html('<tr><td colspan="3" class="px-6 py-10 text-center text-gray-500"><i class="fa-solid fa-spinner fa-spin text-2xl text-indigo-500"></i><div class="mt-2 text-sm font-medium">Processing financial data...</div></td></tr>');
                },
                success: function (res) {
                    $('#income-report-table-body').html(res);
                    $('#income-period-text').text('For the period of ( Transaction date ): ' + start + ' to ' + end);
                    $('#income-report-header').removeClass('hidden');
                },
                error: function (xhr, status, error) {
                    $('#income-report-table-body').html('<tr><td colspan="3" class="px-6 py-10 text-center text-red-500">Failed to load report: ' + error + '</td></tr>');
                }
            });
        });

        // --- Export Logic ---
        function getTableData() {
            const data = []; const headers = [];
            $('#income-statement-table thead tr th').each(function () { if ($(this).is(':visible')) headers.push($(this).text().trim()); });
            data.push(headers);
            $('#income-report-table-body tr').each(function () {
                const row = [];
                $(this).find('td').each(function () { if ($(this).is(':visible')) row.push($(this).text().trim()); });
                if (row.length > 0 && !row[0].includes('Click Generate') && !row[0].includes('No revenue')) data.push(row);
            });
            return data;
        }

        $('#incomePrintBtn').on('click', function () {
            const printWindow = window.open('', '', 'height=600,width=900');
            const start = $('#income_start_date').val();
            const end = $('#income_end_date').val();
            printWindow.document.write('<html><head><title>Income Statement</title>');
            printWindow.document.write('<style>body{font-family:sans-serif;font-size:12px;padding:20px;}table{width:100%;border-collapse:collapse;margin-top:20px;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background-color:#f4f4f4;} .text-right{text-align:right;} .bg-gray-50{background:#f9fafb;} .font-bold{font-weight:bold;} .pl-10{padding-left:40px;} .uppercase{text-transform:uppercase;}</style></head><body>');
            printWindow.document.write('<h1 style="margin-bottom:5px;">Income Statement</h1>');
            printWindow.document.write('<p style="margin-top:0;color:#666;">For the period of ( Transaction date ): ' + start + ' to ' + end + '</p>');
            const tableData = getTableData();
            printWindow.document.write('<table>');
            tableData.forEach((row, i) => {
                printWindow.document.write('<tr>');
                row.forEach((cell, ci) => {
                    const alignClass = ci >= 1 ? ' class="text-right"' : '';
                    printWindow.document.write((i === 0 ? '<th' + alignClass + '>' : '<td' + alignClass + '>') + cell + (i === 0 ? '</th>' : '</td>'));
                });
                printWindow.document.write('</tr>');
            });
            printWindow.document.write('</table></body></html>');
            printWindow.document.close();
            printWindow.print();
        });

        $('#incomePdfBtn').on('click', function () {
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
            const doc = new jsPDF('p', 'mm', 'a4');
            const start = $('#income_start_date').val();
            const end = $('#income_end_date').val();
            doc.setFontSize(18); doc.text('Income Statement', 14, 15);
            doc.setFontSize(10); doc.text('For the period of ( Transaction date ): ' + start + ' to ' + end, 14, 22);
            const tableData = getTableData();

            doc.autoTable({
                head: [tableData[0]],
                body: tableData.slice(1),
                startY: 28,
                theme: 'grid',
                headStyles: { fillColor: [31, 41, 55] },
                columnStyles: { 1: { halign: 'right' }, 2: { halign: 'right' } }
            });
            doc.save('income-statement.pdf');
        }

        $('#incomeExcelBtn').on('click', function () {
            if (typeof XLSX === 'undefined') {
                const s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
                s.onload = () => exportExcel(); document.head.appendChild(s);
            } else exportExcel();
        });

        function exportExcel() {
            const tableData = getTableData();
            const ws = XLSX.utils.aoa_to_sheet(tableData);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Income Statement');
            XLSX.writeFile(wb, 'income-statement.xlsx');
        }
    });
</script>