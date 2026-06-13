<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$currency = '৳';
$search_nonce = wp_create_nonce('frontend_ajax_nonce');
?>

<div class="container mx-auto px-4 py-6 text-gray-800">
    <div class="flex justify-between items-center mb-6 no-print">
        <div class="flex items-center gap-3">
            <div class="bg-indigo-600 p-2 rounded-lg text-white">
                <i class="fa-solid fa-scale-balanced text-xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Trial Balance</h1>
        </div>
        <div class="flex gap-2">
             <button onclick="window.location.reload()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded transition-colors duration-200">
                <i class="fa-solid fa-rotate mr-2"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border border-gray-100 no-print">
        <form id="search-form" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Start Date</label>
                <input type="date" id="start_date" value="<?php echo date('Y-m-01'); ?>" class="w-full rounded-lg border-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5 px-3 border bg-gray-50">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">End Date</label>
                <input type="date" id="end_date" value="<?php echo date('Y-m-d'); ?>" class="w-full rounded-lg border-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5 px-3 border bg-gray-50">
            </div>
            <div>
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-4 rounded-lg transition-all duration-200 shadow-sm">
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
            <input type="search" id="tableSearch" class="block w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-xl leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 sm:text-sm shadow-sm" placeholder="Search accounts...">
        </div>

        <div class="flex justify-end gap-2 flex-wrap w-full md:w-auto">
            <button id="printBtn" class="text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 font-medium rounded-lg text-sm px-4 py-2 transition-all shadow-sm">
                <i class="fa-solid fa-print mr-1"></i> Print
            </button>
            <button id="pdfBtn" class="text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 font-medium rounded-lg text-sm px-4 py-2 transition-all shadow-sm">
                <i class="fa-solid fa-file-pdf mr-1 text-red-600"></i> PDF
            </button>
            <button id="excelBtn" class="text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 font-medium rounded-lg text-sm px-4 py-2 transition-all shadow-sm">
                <i class="fa-solid fa-file-excel mr-1 text-green-600"></i> Excel
            </button>
        </div>
    </div>

    <!-- Period Display -->
    <div id="report-header" class="mb-4 text-center hidden">
        <h2 class="text-xl font-bold text-gray-800">Trial Balance</h2>
        <p class="text-sm text-gray-600 italic mt-1" id="period-text"></p>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200" id="trial-balance-table">
                <thead class="bg-indigo-500 text-white">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider w-16">#</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider w-32">Code</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Account name</th>
                        <th class="px-6 py-4 text-right text-xs font-bold uppercase tracking-wider">Debit (<?php echo $currency; ?>)</th>
                        <th class="px-6 py-4 text-right text-xs font-bold uppercase tracking-wider">Credit (<?php echo $currency; ?>)</th>
                    </tr>
                </thead>
                <tbody id="report-table-body" class="bg-white divide-y divide-gray-200">
                     <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-gray-500">Click Generate Report to load data</td>
                    </tr>
                </tbody>
                <tfoot class="bg-slate-50 font-bold text-gray-900 border-t-2 border-slate-200">
                    <tr>
                        <td colspan="3" class="px-6 py-4 text-right uppercase tracking-wider text-sm">Totals</td>
                        <td id="foot-debit" class="px-6 py-4 text-right text-indigo-700 text-lg">0.00</td>
                        <td id="foot-credit" class="px-6 py-4 text-right text-indigo-700 text-lg">0.00</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <!-- Footer balance check message -->
    <div id="balance-check" class="mt-4 p-4 rounded-lg hidden"></div>
</div>

<script>
jQuery(document).ready(function($) {
    function getTableData() {
        const headerCells = [];
        $('#trial-balance-table thead th').each(function() {
            headerCells.push($(this).text().trim());
        });

        const rows = [headerCells];
        $('#report-table-body tr:visible').each(function() {
            const columns = [];
            $(this).find('td').each(function() {
                columns.push($(this).text().trim());
            });
            if (columns.length) {
                rows.push(columns);
            }
        });

        return rows;
    }

    function parseAmount(value) {
        if (value == null) return 0;
        let cleaned = value.toString().trim();
        if (cleaned === '-' || cleaned === '') return 0;
        cleaned = cleaned.replace(/,/g, '');
        cleaned = cleaned.replace(/\(([^)]+)\)/g, '-$1');
        cleaned = cleaned.replace(/[^0-9.\-]/g, '');
        if (cleaned === '-' || cleaned === '') return 0;
        return parseFloat(cleaned) || 0;
    }

    function calculateFooter() {
        let debit = 0;
        let credit = 0;

        $('#report-table-body tr:visible').each(function() {
            const dText = $(this).find('.debit-amt').text().trim();
            const cText = $(this).find('.credit-amt').text().trim();
            debit += parseAmount(dText);
            credit += parseAmount(cText);
        });

        $('#foot-debit').text(debit.toLocaleString('en-US', { minimumFractionDigits: 2 }));
        $('#foot-credit').text(credit.toLocaleString('en-US', { minimumFractionDigits: 2 }));

        const $check = $('#balance-check');
        if (Math.abs(debit - credit) < 0.01 && debit > 0) {
            $check.removeClass('hidden bg-red-50 text-red-700').addClass('bg-green-50 text-green-700 flex items-center gap-2')
                .html('<i class="fa-solid fa-circle-check"></i> <span>Trial Balance is balanced. Total debits equal total credits.</span>');
        } else if (Math.abs(debit - credit) >= 0.01) {
            $check.removeClass('hidden bg-green-50 text-green-700').addClass('bg-red-50 text-red-700 flex items-center gap-2')
                .html('<i class="fa-solid fa-circle-exclamation"></i> <span>Trial Balance is NOT balanced! Difference: ' + Math.abs(debit - credit).toLocaleString('en-US', { minimumFractionDigits: 2 }) + '</span>');
        } else {
            $check.addClass('hidden').removeClass('bg-green-50 text-green-700 bg-red-50 text-red-700 flex items-center gap-2').html('');
        }
    }

    function updateSearch() {
        const value = $('#tableSearch').val().toLowerCase();
        $('#report-table-body tr').each(function() {
            const show = $(this).text().toLowerCase().indexOf(value) > -1;
            $(this).toggle(show);
        });
        calculateFooter();
    }

    function exportPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');
        const start = $('#start_date').val();
        const end = $('#end_date').val();
        doc.setFontSize(18);
        doc.text('Trial Balance Report', 14, 15);
        doc.setFontSize(10);
        doc.text('For the period of ( Transaction date ): ' + start + ' to ' + end, 14, 22);

        const tableData = getTableData();
        const bodyWithFooter = tableData.slice(1);
        bodyWithFooter.push(['', '', 'TOTALS', $('#foot-debit').text(), $('#foot-credit').text()]);

        doc.autoTable({
            head: [tableData[0]],
            body: bodyWithFooter,
            startY: 28,
            theme: 'grid',
            headStyles: { fillColor: [31, 41, 55] },
            columnStyles: { 3: { halign: 'right' }, 4: { halign: 'right' } },
            didParseCell: function(data) {
                if (data.row.index === bodyWithFooter.length - 1) {
                    data.cell.styles.fontStyle = 'bold';
                    data.cell.styles.fillColor = [249, 250, 251];
                }
            }
        });

        doc.save('trial-balance.pdf');
    }

    function exportExcel() {
        const tableData = getTableData();
        tableData.push(['', '', 'TOTALS', $('#foot-debit').text(), $('#foot-credit').text()]);
        const ws = XLSX.utils.aoa_to_sheet(tableData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Trial Balance');
        XLSX.writeFile(wb, 'trial-balance.xlsx');
    }

    function openPrintWindow() {
        const printWindow = window.open('', '', 'height=600,width=900');
        const start = $('#start_date').val();
        const end = $('#end_date').val();

        printWindow.document.write('<html><head><title>Trial Balance Report</title>');
        printWindow.document.write('<style>body{font-family:sans-serif;font-size:12px;padding:20px;}table{width:100%;border-collapse:collapse;margin-top:20px;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background-color:#f4f4f4;} .text-right{text-align:right;} tfoot{font-weight:bold;background:#f9f9f9;}</style></head><body>');
        printWindow.document.write('<h1 style="margin-bottom:5px;">Trial Balance Report</h1>');
        printWindow.document.write('<p style="margin-top:0;color:#666;">For the period of ( Transaction date ): ' + start + ' to ' + end + '</p>');
        const tableData = getTableData();
        printWindow.document.write('<table>');

        tableData.forEach((row, rowIndex) => {
            printWindow.document.write('<tr>');
            row.forEach((cell, cellIndex) => {
                const alignClass = cellIndex >= 3 ? ' class="text-right"' : '';
                const tag = rowIndex === 0 ? 'th' : 'td';
                printWindow.document.write('<' + tag + alignClass + '>' + cell + '</' + tag + '>');
            });
            printWindow.document.write('</tr>');
        });

        printWindow.document.write('<tfoot><tr><td colspan="3" class="text-right">TOTALS</td><td class="text-right">' + $('#foot-debit').text() + '</td><td class="text-right">' + $('#foot-credit').text() + '</td></tr></tfoot>');
        printWindow.document.write('</table></body></html>');
        printWindow.document.close();
        printWindow.print();
    }

    $('#tableSearch').on('keyup', updateSearch);

    $('#printBtn').on('click', function() {
        openPrintWindow();
    });

    $('#pdfBtn').on('click', function() {
        if (typeof window.jspdf === 'undefined') {
            const s1 = document.createElement('script');
            s1.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
            s1.onload = function() {
                const s2 = document.createElement('script');
                s2.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js';
                s2.onload = function() {
                    exportPDF();
                };
                document.head.appendChild(s2);
            };
            document.head.appendChild(s1);
        } else {
            exportPDF();
        }
    });

    $('#excelBtn').on('click', function() {
        if (typeof XLSX === 'undefined') {
            const s = document.createElement('script');
            s.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
            s.onload = function() {
                exportExcel();
            };
            document.head.appendChild(s);
        } else {
            exportExcel();
        }
    });

    const tableBody = document.querySelector('#report-table-body');
    if (tableBody) {
        const observer = new MutationObserver(function() {
            calculateFooter();
        });
        observer.observe(tableBody, { childList: true, subtree: true });
    }

    calculateFooter();
});
</script>