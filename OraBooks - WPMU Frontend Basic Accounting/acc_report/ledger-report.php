<?php
if (!defined('ABSPATH'))
    exit;

global $wpdb;

$currency = '৳';
$search_nonce = wp_create_nonce('frontend_ajax_nonce');

// Fetch accounts for dropdown
$coa_table = "{$wpdb->prefix}orabooks_ac_coa_list";
$accounts = $wpdb->get_results("SELECT id, account_name, account_code FROM $coa_table ORDER BY account_code ASC");
?>

<div class="container mx-auto px-4 py-6 text-gray-800">
    <div class="flex justify-between items-center mb-6 no-print">
        <div class="flex items-center gap-3">
            <div class="bg-indigo-600 p-2 rounded-lg text-white">
                <i class="fa-solid fa-book-journal-whills text-xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Ledger Report</h1>
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
        <form id="ledger-search-form" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Select
                    Account</label>
                <select id="ledger_account_id"
                    class="w-full rounded-lg border-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5 px-3 border bg-gray-50 transition-all duration-200 outline-none">
                    <option value="">-- Choose Account --</option>
                    <?php if ($accounts):
                        foreach ($accounts as $acc): ?>
                            <option value="<?php echo $acc->id; ?>">
                                <?php echo esc_html($acc->account_code . ' - ' . $acc->account_name); ?></option>
                        <?php endforeach; endif; ?>
                </select>
            </div>
            <div class="md:col-span-1.5">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Start
                    Date</label>
                <input type="date" id="ledger_start_date" value="<?php echo date('Y-m-01'); ?>"
                    class="w-full rounded-lg border-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5 px-3 border bg-gray-50">
            </div>
            <div class="md:col-span-1.5">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">End Date</label>
                <input type="date" id="ledger_end_date" value="<?php echo date('Y-m-d'); ?>"
                    class="w-full rounded-lg border-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5 px-3 border bg-gray-50">
            </div>
            <div class="md:col-span-1">
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
            <input type="search" id="ledgerTableSearch"
                class="block w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-xl leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 sm:text-sm shadow-sm"
                placeholder="Search transactions...">
        </div>

        <div class="flex justify-end gap-2 flex-wrap w-full md:w-auto">
            <button id="ledgerPrintBtn"
                class="text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 font-medium rounded-lg text-sm px-4 py-2 transition-all shadow-sm">
                <i class="fa-solid fa-print mr-1"></i> Print
            </button>
            <button id="ledgerPdfBtn"
                class="text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 font-medium rounded-lg text-sm px-4 py-2 transition-all shadow-sm">
                <i class="fa-solid fa-file-pdf mr-1 text-red-600"></i> PDF
            </button>
            <button id="ledgerExcelBtn"
                class="text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 font-medium rounded-lg text-sm px-4 py-2 transition-all shadow-sm">
                <i class="fa-solid fa-file-excel mr-1 text-green-600"></i> Excel
            </button>
        </div>
    </div>

    <!-- Period Display -->
    <div id="ledger-report-header" class="mb-4 text-center hidden">
        <h2 class="text-xl font-bold text-gray-800" id="ledger-account-title">Ledger Report</h2>
        <p class="text-sm text-gray-600 italic mt-1" id="ledger-period-text"></p>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200" id="ledger-table">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider w-32">Date</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Particulars</th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider w-32">Ref No</th>
                        <th class="px-6 py-4 text-right text-xs font-bold uppercase tracking-wider w-32">Debit
                            (<?php echo $currency; ?>)</th>
                        <th class="px-6 py-4 text-right text-xs font-bold uppercase tracking-wider w-32">Credit
                            (<?php echo $currency; ?>)</th>
                        <th class="px-6 py-4 text-right text-xs font-bold uppercase tracking-wider w-32">Balance
                            (<?php echo $currency; ?>)</th>
                    </tr>
                </thead>
                <tbody id="ledger-report-table-body" class="bg-white divide-y divide-gray-200">
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-gray-500">Select an account and click
                            Generate</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function ($) {

        if ($.fn.select2) {
            $('#ledger_account_id').select2({
                placeholder: '-- Selection --',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#ledger_account_id').parent()
            });
        }

        $('#ledgerTableSearch').on('keyup', function () {
            const value = $(this).val().toLowerCase();
            $('#ledger-report-table-body tr').filter(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        $('#ledger-search-form').on('submit', function (e) {
            e.preventDefault();

            const account_id = $('#ledger_account_id').val();
            const start = $('#ledger_start_date').val();
            const end = $('#ledger_end_date').val();

            if (!account_id) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'Please select an account first.', 'error');
                } else {
                    alert('Please select an account first.');
                }
                return;
            }

            const ajaxData = {
                action: 'search_ledger_report',
                security: '<?php echo $search_nonce; ?>',
                account_id: account_id,
                start_date: start,
                end_date: end
            };

            $.ajax({
                url: typeof obn_ajax !== 'undefined' ? obn_ajax.ajax_url : (typeof obn_accounting_ajax !== 'undefined' ? obn_accounting_ajax.ajax_url : '<?php echo get_admin_url(get_current_blog_id(), "admin-ajax.php"); ?>'),
                type: 'POST',
                data: ajaxData,
                beforeSend: function () {
                    $('#ledger-report-table-body').html('<tr><td colspan="6" class="px-6 py-10 text-center text-gray-500"><i class="fa-solid fa-spinner fa-spin text-2xl text-indigo-500"></i><div class="mt-2 text-sm font-medium">Fetching details...</div></td></tr>');
                },
                success: function (res) {
                    $('#ledger-report-table-body').html(res);
                    $('#ledger-account-title').text('Ledger: ' + $('#ledger_account_id option:selected').text());
                    $('#ledger-period-text').text('For the period of ( Transaction date ): ' + start + ' to ' + end);
                    $('#ledger-report-header').removeClass('hidden');
                },
                error: function (xhr, status, error) {
                    $('#ledger-report-table-body').html('<tr><td colspan="6" class="px-6 py-10 text-center text-red-500">Failed to load: ' + error + '</td></tr>');
                }
            });
        });

        // --- Export Logic ---
        function getTableData() {
            const data = []; const headers = [];
            $('#ledger-table thead tr th').each(function () { if ($(this).is(':visible')) headers.push($(this).text().trim()); });
            data.push(headers);
            $('#ledger-report-table-body tr').each(function () {
                const row = [];
                $(this).find('td').each(function () { if ($(this).is(':visible')) row.push($(this).text().trim()); });
                if (row.length > 0 && !$(this).text().includes('Select an account')) data.push(row);
            });
            return data;
        }

        $('#ledgerPrintBtn').on('click', function () {
            const printWindow = window.open('', '', 'height=600,width=900');
            const start = $('#ledger_start_date').val();
            const end = $('#ledger_end_date').val();
            const account = $('#ledger_account_id option:selected').text();
            printWindow.document.write('<html><head><title>Ledger Report</title>');
            printWindow.document.write('<style>body{font-family:sans-serif;font-size:12px;padding:20px;}table{width:100%;border-collapse:collapse;margin-top:20px;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background-color:#f4f4f4;} .text-right{text-align:right;} .font-bold{font-weight:bold;} .bg-gray-100{background:#f3f4f6;} .bg-indigo-50{background:#f5f3ff;}</style></head><body>');
            printWindow.document.write('<h1 style="margin-bottom:5px;">Ledger: ' + account + '</h1>');
            printWindow.document.write('<p style="margin-top:0;color:#666;">For the period of ( Transaction date ): ' + start + ' to ' + end + '</p>');
            const tableData = getTableData();
            printWindow.document.write('<table>');
            tableData.forEach((row, i) => {
                printWindow.document.write('<tr>');
                row.forEach((cell, ci) => {
                    let extra = '';
                    let alignClass = '';

                    if (i === 0) {
                        alignClass = ci >= 3 ? ' class="text-right"' : '';
                    } else {
                        // Summary Row Detection
                        if (row.length === 4 && ci === 0) { // Totals row
                            extra = ' colspan="3"';
                            alignClass = ' class="text-right font-bold bg-gray-100"';
                        } else if (row.length === 5 && ci === 1) { // Opening Balance
                            extra = ' colspan="2"';
                            alignClass = ' class="font-bold bg-indigo-50"';
                        }

                        if (!alignClass) {
                            const posFromEnd = row.length - 1 - ci;
                            if (posFromEnd < 3) alignClass = ' class="text-right"';
                        }
                    }
                    const tag = i === 0 ? 'th' : 'td';
                    printWindow.document.write('<' + tag + extra + alignClass + '>' + cell + '</' + tag + '>');
                });
                printWindow.document.write('</tr>');
            });
            printWindow.document.write('</table></body></html>');
            printWindow.document.close();
            printWindow.print();
        });

        $('#ledgerPdfBtn').on('click', function () {
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
            const start = $('#ledger_start_date').val();
            const end = $('#ledger_end_date').val();
            const account = $('#ledger_account_id option:selected').text();
            doc.setFontSize(16); doc.text('Ledger: ' + account, 14, 15);
            doc.setFontSize(10); doc.text('For the period of ( Transaction date ): ' + start + ' to ' + end, 14, 22);

            const tableData = getTableData();
            const bodyData = tableData.slice(1).map(row => {
                if (row.length === 4) {
                    return [
                        { content: row[0], colSpan: 3, styles: { halign: 'right', fontStyle: 'bold', fillColor: [243, 244, 246] } },
                        row[1], row[2], row[3]
                    ];
                } else if (row.length === 5) {
                    return [
                        row[0],
                        { content: row[1], colSpan: 2, styles: { fontStyle: 'bold', fillColor: [245, 243, 255] } },
                        row[2], row[3], row[4]
                    ];
                }
                return row;
            });

            doc.autoTable({
                head: [tableData[0]],
                body: bodyData,
                startY: 28,
                theme: 'grid',
                headStyles: { fillColor: [31, 41, 55] },
                columnStyles: { 3: { halign: 'right' }, 4: { halign: 'right' }, 5: { halign: 'right' } }
            });
            doc.save('ledger-' + account.replace(/ /g, '_') + '.pdf');
        }

        $('#ledgerExcelBtn').on('click', function () {
            if (typeof XLSX === 'undefined') {
                const s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
                s.onload = () => exportExcel(); document.head.appendChild(s);
            } else exportExcel();
        });

        function exportExcel() {
            const tableData = getTableData();
            const account = $('#ledger_account_id option:selected').text();
            const ws = XLSX.utils.aoa_to_sheet(tableData);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Ledger');
            XLSX.writeFile(wb, 'ledger-' + account.replace(/ /g, '_') + '.xlsx');
        }
    });
</script>