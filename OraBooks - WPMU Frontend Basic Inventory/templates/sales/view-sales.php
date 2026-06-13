<?php
if (!defined('ABSPATH'))
    exit;

global $wpdb;

// Fetch dropdown data
$customers = $wpdb->get_results("SELECT id, customer_name FROM {$wpdb->prefix}orabooks_db_customers WHERE status = 1 ORDER BY customer_name ASC");
$warehouses = $wpdb->get_results("SELECT id, warehouse_name, warehouse_type FROM {$wpdb->prefix}orabooks_db_warehouse WHERE status = 1 ORDER BY warehouse_name ASC");
$users = get_users(['orderby' => 'display_name']);

// Initial stats
$stats = $wpdb->get_row("SELECT SUM(grand_total) as total_sales, SUM(paid_amount) as total_paid FROM {$wpdb->prefix}orabooks_db_sales WHERE status = 1");
$total_due = ($stats->total_sales ?? 0) - ($stats->total_paid ?? 0);
$total_qty = $wpdb->get_var("SELECT SUM(sales_qty) FROM {$wpdb->prefix}orabooks_db_salesitems");

$currency = '৳';
$nonce = wp_create_nonce('frontend_ajax_nonce');
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4 border-b border-gray-50 pb-6">
        <div class="flex items-center">
            <div
                class="w-12 h-12 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center text-white shadow-lg mr-4">
                <i class="fa-solid fa-list-check text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Sales List</h1>
                <p class="text-sm text-gray-500 mt-1">Manage and view all sales records</p>
            </div>
        </div>
        <div class="flex gap-3">
            <a href="<?php echo esc_url(add_query_arg('view', 'add-sale')); ?>"
                class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg hover:from-blue-700 hover:to-indigo-700 transition-all font-medium shadow-md hover:shadow-lg">
                <i class="fa-solid fa-plus mr-2"></i> New Sale
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="rounded-xl shadow-sm p-5 text-white transform hover:scale-[1.02] transition-transform"
            style="background: linear-gradient(135deg, #1a83db, #1569B3);">
            <h3 class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Total Quantity</h3>
            <p class="text-2xl font-bold"><?php echo number_format($total_qty ?? 0, 2); ?></p>
        </div>
        <div class="rounded-xl shadow-sm p-5 text-white transform hover:scale-[1.02] transition-transform"
            style="background: linear-gradient(135deg, #41d155, #39B54A);">
            <h3 class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Total Paid</h3>
            <p class="text-2xl font-bold"><?php echo $currency . ' ' . number_format($stats->total_paid ?? 0, 2); ?></p>
        </div>
        <div class="rounded-xl shadow-sm p-5 text-white transform hover:scale-[1.02] transition-transform"
            style="background: linear-gradient(135deg, #1a83db, #1569B3);">
            <h3 class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Total Sales</h3>
            <p class="text-2xl font-bold"><?php echo $currency . ' ' . number_format($stats->total_sales ?? 0, 2); ?>
            </p>
        </div>
        <div class="rounded-xl shadow-sm p-5 text-white transform hover:scale-[1.02] transition-transform"
            style="background: linear-gradient(135deg, #41d155, #39B54A);">
            <h3 class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Total Due</h3>
            <p class="text-2xl font-bold"><?php echo $currency . ' ' . number_format($total_due, 2); ?></p>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-gray-50 rounded-xl p-6 border border-gray-200 mb-8 shadow-inner relative">
        <h2 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-4 flex items-center">
            <i class="fa-solid fa-filter mr-2"></i> Filter Results
        </h2>
        <form id="search-form" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-6 items-end">
            <div class="lg:col-span-1">
                <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Start Date</label>
                <input type="date" id="start_date"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-white">
            </div>
            <div class="lg:col-span-1">
                <label class="block text-xs font-bold text-gray-600 uppercase mb-2">End Date</label>
                <input type="date" id="end_date"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-white">
            </div>
            <div class="lg:col-span-1">
                <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Customer</label>
                <select id="customer_id"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5 px-3 border bg-white filter-select2">
                    <option value="">All Customers</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?php echo $c->id; ?>"><?php echo esc_html($c->customer_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lg:col-span-1">
                <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Warehouse</label>
                <select id="warehouse_id"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5 px-3 border bg-white filter-select2">
                    <option value="">All Warehouses</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?php echo $w->id; ?>" <?php selected($w->warehouse_type, 'system'); ?>>
                            <?php echo esc_html($w->warehouse_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lg:col-span-1">
                <label class="block text-xs font-bold text-gray-600 uppercase mb-2">User</label>
                <select id="user_id"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5 px-3 border bg-white filter-select2">
                    <option value="">All Users</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u->ID; ?>"><?php echo esc_html($u->display_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lg:col-span-1">
                <button type="submit"
                    class="w-full text-white font-bold py-2.5 px-4 rounded-lg transition-colors duration-200 shadow-md flex items-center justify-center hover:opacity-90"
                    style="background-color: #39B54A;">
                    <i class="fa-solid fa-magnifying-glass mr-2"></i> Search
                </button>
            </div>
        </form>
    </div>

    <!-- Search Bar & Export Toolbar -->
    <div
        class="search-filter-bar mb-6 flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between no-print">
        <div class="relative flex-1 w-full">
            <div class="absolute inset-y-0 left-0 flex items-center pl-2.5 pointer-events-none">
                <i class="fa-solid fa-search text-gray-400 text-sm"></i>
            </div>
            <input type="search" id="searchInput"
                class="block w-full pl-8 pr-3 py-2 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                placeholder="Search records...">
        </div>

        <!-- Export & Column Buttons -->
        <div class="export-toolbar flex gap-2 flex-wrap">
            <button id="printBtn"
                class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
                title="Print">
                <i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
            </button>
            <button id="pdfBtn"
                class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
                title="Export to PDF">
                <i class="fa-solid fa-file-pdf mr-1 text-red-600"></i> <span class="hidden sm:inline">PDF</span>
            </button>
            <button id="excelBtn"
                class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
                title="Export to Excel">
                <i class="fa-solid fa-file-excel mr-1 text-green-600"></i> <span class="hidden sm:inline">Excel</span>
            </button>
            <button id="csvBtn"
                class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
                title="Export to CSV">
                <i class="fa-solid fa-file-csv mr-1 text-blue-600"></i> <span class="hidden sm:inline">CSV</span>
            </button>

            <!-- Column Visibility Dropdown -->
            <div class="relative">
                <button id="columnToggleBtn"
                    class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors"
                    title="Toggle Columns">
                    <i class="fa-solid fa-columns mr-1"></i> Columns
                </button>
                <div id="columnDropdown"
                    class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                    <div class="p-3 space-y-2">
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="0" checked> #
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="1" checked> Warehouse
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="2" checked> Invoice No.
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="3" checked> Date
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="4" checked> Customer
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="5" checked> Total
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="6" checked> Paid
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="7" checked> Due
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="8" checked> Created By
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div id="sales-table-container" class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
        <div class="overflow-x-auto overflow-y-auto max-h-[600px] scroll-smooth">
            <table id="sales-table" class="min-w-full divide-y divide-gray-200">
                <thead class="text-white border-b border-gray-200" style="background-color: #1569B3;">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-white-400 uppercase tracking-widest">#
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-white-400 uppercase tracking-widest">
                            Invoice No.</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-white-400 uppercase tracking-widest">Date
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-white-400 uppercase tracking-widest">
                            Customer</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-white-400 uppercase tracking-widest">
                            Total Qty</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-white-400 uppercase tracking-widest">
                            Status</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-white-400 uppercase tracking-widest">
                            Total</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-white-400 uppercase tracking-widest">Paid
                        </th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-white-400 uppercase tracking-widest">Due
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-white-400 uppercase tracking-widest">
                            Action</th>
                    </tr>
                </thead>
                <tbody id="sales-table-body" class="bg-white divide-y divide-gray-100">
                    <tr>
                        <td colspan="10" class="px-6 py-12 text-center text-gray-400 group">
                            <div class="flex flex-col items-center">
                                <i
                                    class="fa-solid fa-magnifying-glass text-4xl mb-4 opacity-20 group-hover:opacity-40 transition-opacity"></i>
                                <span class="text-lg font-medium">Click Search to load data</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <tfoot class="bg-gray-50 font-bold text-gray-700 border-t-2 border-gray-200">
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-right text-sm uppercase tracking-wider text-gray-400">
                            Total Summary:</td>
                        <td id="foot-total" class="px-6 py-4 text-right text-blue-600 text-lg font-black">0.00</td>
                        <td id="foot-paid" class="px-6 py-4 text-right text-emerald-600 text-lg font-black">0.00</td>
                        <td id="foot-due" class="px-6 py-4 text-right text-rose-600 text-lg font-black">0.00</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Scripts for Datepicker & Export Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

<script>
    jQuery(document).ready(function ($) {
        const ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
        const nonce = '<?php echo $nonce; ?>';

        // Init Select2 with local parent to fix alignment in scrollable dashboard
        $('.filter-select2').select2({
            width: '100%',
            dropdownParent: $('#search-form').parent()
        });

        let searchTimeout;

        function fetchSales() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'search_sales',
                    security: nonce,
                    start_date: $('#start_date').val(),
                    end_date: $('#end_date').val(),
                    customer_id: $('#customer_id').val(),
                    warehouse_id: $('#warehouse_id').val(),
                    user_id: $('#user_id').val(),
                    search: $('#searchInput').val() // Unified search
                },
                beforeSend: function () {
                    $('#sales-table-body').html('<tr><td colspan="11" class="px-6 py-12 text-center text-gray-500"><div class="flex flex-col items-center"><i class="fa-solid fa-spinner fa-spin text-4xl text-blue-600 mb-4"></i><div class="text-lg font-medium">Fetching Sales Records...</div></div></td></tr>');
                },
                success: function (res) {
                    $('#sales-table-body').html(res);
                    calculateFooter();
                    // Re-apply column visibility after refresh
                    applyColumnVisibility();
                },
                error: function (xhr) {
                    alert("Search failed: " + xhr.statusText);
                }
            });
        }

        function calculateFooter() {
            let total = 0, paid = 0, due = 0;

            $('#sales-table-body tr').each(function () {
                if ($(this).find('td').length > 1) { // Skip "No records" row
                    const t = parseFloat($(this).find('.grand-total').text().replace(/,/g, '')) || 0;
                    const p = parseFloat($(this).find('.paid-amount').text().replace(/,/g, '')) || 0;
                    const d = parseFloat($(this).find('.due-amount').text().replace(/,/g, '')) || 0;
                    total += t; paid += p; due += d;
                }
            });

            $('#foot-total').text(total.toLocaleString('en-US', { minimumFractionDigits: 2 }));
            $('#foot-paid').text(paid.toLocaleString('en-US', { minimumFractionDigits: 2 }));
            $('#foot-due').text(due.toLocaleString('en-US', { minimumFractionDigits: 2 }));
        }

        // Event Listeners
        $('#search-form').on('submit', function (e) {
            e.preventDefault();
            fetchSales();
        });

        $('#searchInput').on('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(fetchSales, 500);
        });

        // Column Visibility Logic
        function applyColumnVisibility() {
            $('.column-toggle').each(function () {
                const column = $(this).data('column');
                const isChecked = $(this).is(':checked');

                $('table thead tr th').eq(column).toggle(isChecked);
                $('table tbody tr').each(function () {
                    $(this).find('td').eq(column).toggle(isChecked);
                });
                $('table tfoot tr td').each(function () {
                    // Logic for footer is tricky because of colspans.
                    // Ideally we should just rely on the table/header/body.
                    // Footer usually has totals.
                });
            });
        }

        $('#columnToggleBtn').on('click', function (e) {
            e.stopPropagation();
            $('#columnDropdown').toggleClass('hidden');
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#columnToggleBtn, #columnDropdown').length) {
                $('#columnDropdown').addClass('hidden');
            }
        });

        $('.column-toggle').on('change', function () {
            applyColumnVisibility();
        });

        // Delete Sale Event Listener
        $(document).on('click', '.delete-sale', function () {
            const saleId = $(this).data('id');
            if (confirm('Are you sure you want to delete this sale? This action cannot be undone and will restore any deducted stock.')) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'delete_sale',
                        security: nonce,
                        sales_id: saleId
                    },
                    success: function (res) {
                        if (res.success) {
                            fetchSales(); // Refresh the table
                        } else {
                            alert('Error: ' + (res.data.message || res.data));
                        }
                    },
                    error: function (xhr) {
                        alert('An error occurred while deleting the sale.');
                    }
                });
            }
        });

        // Get table data for export (Respecting visibility)
        function getTableData() {
            const data = [];
            const headers = [];

            $('#sales-table thead tr th').each(function (index) {
                if ($(this).is(':visible') && index < 10) { // Exclude Actions
                    headers.push($(this).text().trim());
                }
            });
            data.push(headers);

            $('#sales-table tbody tr').each(function () {
                const row = [];
                // Check if it's a data row (not the loading or empty message)
                if ($(this).find('td').length > 1) {
                    $(this).find('td').each(function (index) {
                        if ($(this).is(':visible') && index < 10) {
                            row.push($(this).text().trim().replace(/\s+/g, ' '));
                        }
                    });
                    if (row.length > 0) data.push(row);
                }
            });
            return data;
        }

        // Print functionality
        $("#printBtn").click(function () {
            const printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>Sales Report</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: Arial, sans-serif; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 20px; }');
            printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
            printWindow.document.write('th { background-color: #f3f4f6; font-weight: bold; }');
            printWindow.document.write('h1 { text-align: center; color: #333; }');
            printWindow.document.write('</style></head><body>');
            printWindow.document.write('<h1>Sales Report</h1>');

            const tableData = getTableData();
            printWindow.document.write('<table>');
            tableData.forEach(function (row, index) {
                printWindow.document.write('<tr>');
                row.forEach(function (cell) {
                    const tag = index === 0 ? 'th' : 'td';
                    printWindow.document.write('<' + tag + '>' + cell + '</' + tag + '>');
                });
                printWindow.document.write('</tr>');
            });
            printWindow.document.write('</table></body></html>');
            printWindow.document.close();
            printWindow.print();
        });

        // Export PDF
        $("#pdfBtn").click(function () {
            const { jsPDF } = window.jspdf;
            let doc = new jsPDF('l', 'mm', 'a4');

            doc.setFontSize(18);
            doc.text("Sales Report", 14, 15);

            const tableData = getTableData();
            const headers = tableData[0];
            const rows = tableData.slice(1);

            doc.autoTable({
                head: [headers],
                body: rows,
                startY: 25,
                theme: 'grid',
                styles: { fontSize: 8 },
                headStyles: { fillColor: [59, 130, 246] },
            });

            doc.save('Sales_Report_' + new Date().toISOString().slice(0, 10) + '.pdf');
        });

        // Export Excel
        $("#excelBtn").click(function () {
            const tableData = getTableData();
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet(tableData);
            XLSX.utils.book_append_sheet(wb, ws, 'Sales List');
            XLSX.writeFile(wb, 'Sales_Report_' + new Date().toISOString().slice(0, 10) + '.xlsx');
        });

        // Export CSV
        $("#csvBtn").click(function () {
            const tableData = getTableData();
            let csv = '';
            tableData.forEach(function (row) {
                csv += row.map(function (cell) {
                    return '"' + cell.replace(/"/g, '""') + '"';
                }).join(',') + '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'Sales_Report_' + new Date().toISOString().slice(0, 10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });

        // Auto-search on load
        fetchSales();
    });
</script>

<style>
    @media print {

        /* Hide everything except the table container */
        body aside,
        body nav,
        .bg-gradient-to-r,
        .grid.lg\:grid-cols-4,
        .bg-gray-50.rounded-xl,
        .no-print {
            display: none !important;
        }

        /* Make the table container full width and remove shadows */
        #sales-table-container {
            position: absolute;
            left: 0;
            top: 0;
            width: 100% !important;
            box-shadow: none !important;
            border: 1px solid #eee !important;
        }

        /* Reset main area padding and layout */
        main {
            padding: 0 !important;
            margin: 0 !important;
        }

        body,
        html {
            height: auto !important;
            overflow: visible !important;
        }

        /* Expand the scrollable container for full table visibility */
        .max-h-\[600px\] {
            max-height: none !important;
            overflow: visible !important;
        }
    }
</style>