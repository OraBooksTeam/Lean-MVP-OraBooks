<?php
if (!defined('ABSPATH'))
    exit;

global $wpdb;

// Fetch Dropdown Data
$warehouses = $wpdb->get_results("SELECT id, warehouse_name FROM {$wpdb->prefix}orabooks_db_warehouse WHERE status=1 ORDER BY id ASC");
$customers = $wpdb->get_results("SELECT id, customer_name FROM {$wpdb->prefix}orabooks_db_customers ORDER BY id ASC");

// Get filter values
$warehouse_id = isset($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : 0;
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d');

// Build query
$where = ["s.sales_status = 'Pending'"];
$params = [];

if ($warehouse_id) {
    $where[] = "s.warehouse_id = %d";
    $params[] = $warehouse_id;
}

if ($customer_id) {
    $where[] = "s.customer_id = %d";
    $params[] = $customer_id;
}

if ($start_date) {
    $where[] = "DATE(s.sales_date) >= %s";
    $params[] = $start_date;
}

if ($end_date) {
    $where[] = "DATE(s.sales_date) <= %s";
    $params[] = $end_date;
}

$where_clause = "WHERE " . implode(" AND ", $where);

// Fetch sales data
$sales = $wpdb->get_results($wpdb->prepare("
    SELECT s.*, w.warehouse_name, c.customer_name
    FROM {$wpdb->prefix}orabooks_db_sales s
    LEFT JOIN {$wpdb->prefix}orabooks_db_warehouse w ON s.warehouse_id = w.id
    LEFT JOIN {$wpdb->prefix}orabooks_db_customers c ON s.customer_id = c.id
    $where_clause
    ORDER BY s.sales_date DESC
", $params));

// Calculate totals
$total_qty = 0;
$total_sales = 0;
foreach ($sales as $sale) {
    $items = $wpdb->get_results($wpdb->prepare("
        SELECT sales_qty FROM {$wpdb->prefix}orabooks_db_salesitems WHERE sales_id = %d
    ", $sale->id));
    foreach ($items as $item) {
        $total_qty += $item->sales_qty;
    }
    $total_sales += $sale->grand_total;
}

$currency = $wpdb->get_var("SELECT c.currency_code FROM {$wpdb->prefix}orabooks_db_currency c JOIN {$wpdb->prefix}orabooks_db_store s ON c.id = s.currency_id LIMIT 1") ?: 'BDT';
$nonce = wp_create_nonce('frontend_ajax_nonce');
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 md:p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-4 md:mb-6 gap-3 md:gap-4">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-yellow-600 rounded-xl flex items-center justify-center text-white shadow-lg mr-4">
                <i class="fa-solid fa-clock text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Pending Delivery</h1>
                <p class="text-sm text-gray-500 mt-1">Manage sales pending delivery</p>
            </div>
        </div>
        <a href="?view=view-sales"
            class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-yellow-600 to-orange-600 text-white rounded-lg hover:from-yellow-700 hover:to-orange-700 transition-all font-medium shadow-md hover:shadow-lg">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Sales
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-gray-50 rounded-lg p-3 md:p-5 border border-gray-200 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
            <i class="fa-solid fa-filter mr-2 text-blue-600"></i> Filters
        </h2>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3 md:gap-5">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-tighter mb-1">Warehouse</label>
                <select name="warehouse_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm py-2 px-3 border bg-white h-[42px]">
                    <option value="">All Warehouses</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?php echo $warehouse->id; ?>" <?php echo ($warehouse_id == $warehouse->id) ? 'selected' : ''; ?>>
                            <?php echo esc_html($warehouse->warehouse_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-tighter mb-1">Customer</label>
                <select name="customer_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm py-2 px-3 border bg-white h-[42px]">
                    <option value="">All Customers</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo $customer->id; ?>" <?php echo ($customer_id == $customer->id) ? 'selected' : ''; ?>>
                            <?php echo esc_html($customer->customer_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                 <label class="block text-xs font-semibold text-gray-500 uppercase tracking-tighter mb-1">From Date</label>
                 <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm py-2 px-3 border bg-white h-[42px]">
            </div>
            <div>
                 <label class="block text-xs font-semibold text-gray-500 uppercase tracking-tighter mb-1">To Date</label>
                 <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm py-2 px-3 border bg-white h-[42px]">
            </div>
            <div>
                <button type="submit" class="w-full text-white font-bold py-2 px-4 rounded-lg transition-all h-[42px] flex items-center justify-center hover:opacity-90" style="background-color: #39B54A;">
                    <i class="fa-solid fa-filter mr-2"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="rounded-xl shadow-sm p-5 text-white" style="background: linear-gradient(135deg, #1a83db, #1569B3);">
            <h3 class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Pending Orders</h3>
            <p class="text-2xl font-bold"><?php echo count($sales); ?></p>
        </div>
        <div class="rounded-xl shadow-sm p-5 text-white" style="background: linear-gradient(135deg, #41d155, #39B54A);">
             <h3 class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Total Quantity</h3>
             <p class="text-2xl font-bold"><?php echo number_format($total_qty ?? 0, 0); ?></p>
        </div>
        <div class="rounded-xl shadow-sm p-5 text-white" style="background: linear-gradient(135deg, #1a83db, #1569B3);">
             <h3 class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Pending Value</h3>
             <p class="text-2xl font-bold"><?php echo $currency . ' ' . number_format($total_sales, 2); ?></p>
        </div>
    </div>

    <!-- Search Bar & Export Toolbar -->
    <div class="search-filter-bar mb-4 flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
        <div class="relative flex-1 w-full">
            <div class="absolute inset-y-0 left-0 flex items-center pl-2.5 pointer-events-none">
                <i class="fa-solid fa-search text-gray-400 text-sm"></i>
            </div>
            <input type="search" id="searchInput" class="block w-full pl-8 pr-3 py-2 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 h-[42px]" placeholder="Search by code, customer, or status...">
        </div>
        
        <!-- Export Buttons -->
        <div class="export-toolbar flex gap-2 flex-wrap">
            <button id="salespendingdeliveryPrintBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none h-[42px]" title="Print">
                <i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
            </button>
            <button id="salespendingdeliveryPdfBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none h-[42px]" title="Export to PDF">
                <i class="fa-solid fa-file-pdf mr-1 text-red-600"></i> <span class="hidden sm:inline">PDF</span>
            </button>
            <button id="salespendingdeliveryExcelBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none h-[42px]" title="Export to Excel">
                <i class="fa-solid fa-file-excel mr-1 text-green-600"></i> <span class="hidden sm:inline">Excel</span>
            </button>
            <button id="salespendingdeliveryCsvBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none h-[42px]" title="Export to CSV">
                <i class="fa-solid fa-file-csv mr-1 text-blue-600"></i> <span class="hidden sm:inline">CSV</span>
            </button>
        </div>
    </div>

    <!-- Sales Table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table id="salesTable" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Date</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Sales Code</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Customer</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider">Total</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <?php if (count($sales) > 0): ?>
                        <?php foreach ($sales as $s): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo date('d-m-Y', strtotime($s->sales_date)); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-yellow-600"><?php echo esc_html($s->sales_code); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium"><?php echo esc_html($s->customer_name); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-yellow-100 text-yellow-800">
                                        <i class="fa-solid fa-clock mr-1"></i> Pending
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-gray-900"><?php echo number_format($s->grand_total, 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <button type="button" class="text-green-600 hover:text-green-900 bg-green-50 p-2 rounded-lg transition-colors deliver-sale" data-id="<?php echo $s->id; ?>" title="Deliver Sale">
                                        <i class="fa-solid fa-truck"></i>
                                    </button>
                                    <a href="<?php echo esc_url(add_query_arg(['view' => 'sales-invoice', 'sales_id' => $s->id])); ?>" class="text-blue-600 hover:text-blue-900 bg-blue-50 p-2 rounded-lg transition-colors" title="View Sale">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <!-- <a href="<?php //echo esc_url(add_query_arg(['view' => 'edit-sales', 'id' => $s->id])); ?>" class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 p-2 rounded-lg transition-colors" title="Edit Sale">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <button type="button" class="text-rose-600 hover:text-rose-900 bg-rose-50 p-2 rounded-lg transition-colors delete-sale" data-id="<?php //echo $s->id; ?>" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </button> -->
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-400">
                            <i class="fa-solid fa-clock text-5xl mb-3 block opacity-20"></i>
                            No pending deliveries found with current filters.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="bg-yellow-50 font-bold text-gray-900 border-t-2 border-yellow-200">
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-right uppercase tracking-wider text-xs">Summary:</td>
                        <td class="px-6 py-4 text-right text-sm"><?php echo number_format($total_sales, 2); ?></td>
                        <td class="px-6 py-4"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
jQuery(document).ready(function($) {
    // Deliver Sale
    $('.deliver-sale').on('click', function() {
        const saleId = $(this).data('id');
        
        if (confirm('Are you sure you want to mark this sale as delivered? This will update stock and create journal entries.')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_sales_status',
                    security: '<?php echo $nonce; ?>',
                    sales_id: saleId,
                    sales_status: 'Delivered'
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        alert('Success: Sale has been marked as delivered successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred while processing your request.');
                }
            });
        }
    });

    // Search
    $('#searchInput').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $("#salesTable tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });

    // Export functionality
    function getSalesPendingTableData() {
        const data = [];
        
        // Headers
        data.push(['Date', 'Sales Code', 'Customer', 'Status', 'Total']);
        
        // Get table rows
        $('#salesTable tbody tr').each(function() {
            const row = [];
            $(this).find('td').each(function() {
                row.push($(this).text().trim());
            });
            data.push(row);
        });
        
        return data;
    }

    // Print
    $('#salespendingdeliveryPrintBtn').on('click', function() {
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Pending Delivery</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-family: Arial, sans-serif; }');
        printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 20px; }');
        printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
        printWindow.document.write('th { background-color: #1569B3; color: white; font-weight: bold; }');
        printWindow.document.write('h1 { text-align: center; color: #333; }');
        printWindow.document.write('</style></head><body>');
        printWindow.document.write('<h1>Pending Delivery</h1>');
        
        const tableData = getSalesPendingTableData();
        printWindow.document.write('<table>');
        tableData.forEach(function(row, index) {
            printWindow.document.write('<tr>');
            row.forEach(function(cell) {
                const tag = index === 0 ? 'th' : 'td';
                printWindow.document.write('<' + tag + '>' + cell + '</' + tag + '>');
            });
            printWindow.document.write('</tr>');
        });
        printWindow.document.write('</table>');
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    });

    // PDF Export
    $('#salespendingdeliveryPdfBtn').on('click', function() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        const tableData = getSalesPendingTableData();
        const headers = tableData[0];
        const rows = tableData.slice(1);
        
        doc.autoTable({
            head: [headers],
            body: rows,
            startY: 30,
            theme: 'grid',
            styles: { fontSize: 8 },
            headStyles: { fillColor: [21, 105, 179] }
        });
        
        doc.save('pending-delivery.pdf');
    });

    // Excel Export
    $('#salespendingdeliveryExcelBtn').on('click', function() {
        const tableData = getSalesPendingTableData();
        const ws = XLSX.utils.aoa_to_sheet(tableData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Pending Delivery");
        XLSX.writeFile(wb, "pending-delivery.xlsx");
    });

    // CSV Export
    $('#salespendingdeliveryCsvBtn').on('click', function() {
        const tableData = getSalesPendingTableData();
        let csv = tableData.map(row => row.map(cell => '"' + cell.replace(/"/g, '""') + '"').join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'pending-delivery.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
});
</script>
