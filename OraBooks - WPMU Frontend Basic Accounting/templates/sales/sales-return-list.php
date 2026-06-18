<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Filters
$warehouse_id = isset($_GET['warehouse']) ? intval($_GET['warehouse']) : 0;
$customer_id  = isset($_GET['customer']) ? intval($_GET['customer']) : 0;
$start_date   = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$end_date     = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

// Dropdowns
$warehouses = $wpdb->get_results("SELECT id, warehouse_name, warehouse_type FROM {$wpdb->prefix}orabooks_db_warehouse WHERE status=1");
$customers  = $wpdb->get_results("SELECT id, customer_name FROM {$wpdb->prefix}orabooks_db_customers WHERE status=1");

// Logic
$where = "WHERE sr.status = 1";
if ($warehouse_id) $where .= $wpdb->prepare(" AND sr.warehouse_id = %d", $warehouse_id);
if ($customer_id)  $where .= $wpdb->prepare(" AND sr.customer_id = %d", $customer_id);
if ($start_date)   $where .= $wpdb->prepare(" AND sr.return_date >= %s", $start_date);
if ($end_date)     $where .= $wpdb->prepare(" AND sr.return_date <= %s", $end_date);

$returns = $wpdb->get_results("
    SELECT sr.*, c.customer_name, u.display_name,
           (SELECT COALESCE(SUM(sri.sales_qty), 0) 
            FROM {$wpdb->prefix}orabooks_db_salesitemsreturn sri 
            WHERE sri.return_id = sr.id) as total_qty
    FROM {$wpdb->prefix}orabooks_db_salesreturn sr
    LEFT JOIN {$wpdb->prefix}orabooks_db_customers c ON sr.customer_id = c.id
    LEFT JOIN {$wpdb->users} u ON sr.created_by = u.ID
    $where
    ORDER BY sr.id DESC
");

// Stats calculation
$total_return = 0;
$total_refunded = 0;
foreach ($returns as $r) {
    if ($r->return_status !== 'Rejected') {
        $total_return   += floatval($r->grand_total);
        $total_refunded += floatval($r->paid_amount);
    }
}
$total_due = $total_return - $total_refunded;
$total_qty = $wpdb->get_var("
    SELECT SUM(si.sales_qty) 
    FROM {$wpdb->prefix}orabooks_db_salesitemsreturn si
    JOIN {$wpdb->prefix}orabooks_db_salesreturn sr ON si.return_id = sr.id
    WHERE sr.status = 1 AND sr.return_status != 'Rejected'
");

$currency = '৳';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center mr-4 shadow-sm">
                <i class="fa-solid fa-arrow-rotate-left text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Sales Return List</h1>
                <p class="text-sm text-gray-500 mt-1">Manage, Track and Approve Returned Sales</p>
            </div>
        </div>
        <a href="?view=view-sales" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-all font-medium shadow-md hover:shadow-lg active:scale-95">
             <i class="fa-solid fa-arrow-left-long mr-2"></i> Back to Sales List
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-gradient-to-br from-indigo-500 to-blue-600 rounded-xl shadow-sm p-5 text-white">
            <h3 class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Total Quantity Returned</h3>
            <p class="text-2xl font-bold"><?php echo number_format($total_qty ?? 0, 0); ?></p>
        </div>
        <div class="bg-gradient-to-br from-emerald-600 to-emerald-400 rounded-xl shadow-sm p-5 text-white">
             <h3 class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Total Refunded</h3>
             <p class="text-2xl font-bold"><?php echo $currency . ' ' . number_format($total_refunded, 2); ?></p>
        </div>
        <div class="bg-gradient-to-br from-indigo-600 to-blue-500 rounded-xl shadow-sm p-5 text-white">
             <h3 class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Total Return Amount</h3>
             <p class="text-2xl font-bold"><?php echo $currency . ' ' . number_format($total_return, 2); ?></p>
        </div>
        <div class="bg-gradient-to-br from-rose-500 to-rose-400 rounded-xl shadow-sm p-5 text-white">
             <h3 class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Total Credit/Due</h3>
             <p class="text-2xl font-bold"><?php echo $currency . ' ' . number_format($total_due, 2); ?></p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-gray-50 rounded-xl border border-gray-200 p-5 mb-8">
        <form method="get" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <input type="hidden" name="view" value="sales-return-list">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-tighter mb-1">Warehouse</label>
                <select name="warehouse" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2 px-3 border bg-white h-[42px]">
                    <option value="0">All Warehouses</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?php echo $w->id; ?>" <?php selected($warehouse_id ?: ($w->warehouse_type === 'system' ? $w->id : null), $w->id); ?>><?php echo esc_html($w->warehouse_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                 <label class="block text-xs font-semibold text-gray-500 uppercase tracking-tighter mb-1">Customer</label>
                 <select name="customer" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2 px-3 border bg-white h-[42px]">
                    <option value="0">All Customers</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?php echo $c->id; ?>" <?php selected($customer_id, $c->id); ?>><?php echo esc_html($c->customer_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                 <label class="block text-xs font-semibold text-gray-500 uppercase tracking-tighter mb-1">From Date</label>
                 <input type="date" name="date_from" value="<?php echo esc_attr($start_date); ?>" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2 px-3 border bg-white h-[42px]">
            </div>
            <div>
                 <label class="block text-xs font-semibold text-gray-500 uppercase tracking-tighter mb-1">To Date</label>
                 <input type="date" name="date_to" value="<?php echo esc_attr($end_date); ?>" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2 px-3 border bg-white h-[42px]">
            </div>
            <div>
                <button type="submit" class="w-full bg-indigo-800 hover:bg-indigo-900 text-white font-bold py-2 px-4 rounded-lg transition-all h-[42px] flex items-center justify-center">
                    <i class="fa-solid fa-filter mr-2"></i> Filter
                </button>
            </div>
        </form>
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
            <button id="salesreturnlistPrintBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none h-[42px]" title="Print">
                <i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
            </button>
            <button id="salesreturnlistPdfBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none h-[42px]" title="Export to PDF">
                <i class="fa-solid fa-file-pdf mr-1 text-red-600"></i> <span class="hidden sm:inline">PDF</span>
            </button>
            <button id="salesreturnlistExcelBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none h-[42px]" title="Export to Excel">
                <i class="fa-solid fa-file-excel mr-1 text-green-600"></i> <span class="hidden sm:inline">Excel</span>
            </button>
            <button id="salesreturnlistCsvBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none h-[42px]" title="Export to CSV">
                <i class="fa-solid fa-file-csv mr-1 text-blue-600"></i> <span class="hidden sm:inline">CSV</span>
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table id="returnsTable" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Date</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Return Code</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Reference</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Customer</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider">Return Qty</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider">Total</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider">Refunded</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wider">Refund Status</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <?php if (count($returns) > 0): ?>
                        <?php foreach ($returns as $r): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo date('d-m-Y', strtotime($r->return_date)); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-indigo-600"><?php echo esc_html($r->return_code); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo esc_html($r->reference_no); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium"><?php echo esc_html($r->customer_name); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-gray-900"><?php echo number_format($r->total_qty ?? 0, 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php 
                                    $status_class = 'bg-gray-100 text-gray-800';
                                    if($r->return_status == 'Approved') $status_class = 'bg-green-100 text-green-800 border-green-200';
                                    if($r->return_status == 'Pending')  $status_class = 'bg-amber-100 text-amber-800 border-amber-200 animate-pulse';
                                    if($r->return_status == 'Rejected')  $status_class = 'bg-red-100 text-red-800 border-red-200 line-through';
                                    ?>
                                    <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-bold rounded-full border <?php echo $status_class; ?>">
                                        <?php echo esc_html($r->return_status); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-gray-900"><?php echo number_format($r->grand_total, 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-emerald-600"><?php echo number_format($r->paid_amount, 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                     <?php 
                                     // Refund status should match return status
                                     $refund_status = $r->return_status;
                                     $refund_status_class = 'bg-gray-100 text-gray-800';
                                     if($refund_status == 'Approved') $refund_status_class = 'bg-emerald-100 text-emerald-800 border-emerald-200';
                                     if($refund_status == 'Pending')  $refund_status_class = 'bg-amber-100 text-amber-800 border-amber-200 animate-pulse';
                                     if($refund_status == 'Rejected')  $refund_status_class = 'bg-rose-100 text-rose-800 border-rose-200 line-through';
                                     ?>
                                     <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-bold rounded-full border <?php echo $refund_status_class; ?>">
                                        <?php echo esc_html($refund_status); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="flex items-center justify-center gap-2">
                                        <?php if ($r->return_status == 'Pending'): ?>
                                            <button type="button" class="text-white bg-green-500 hover:bg-green-600 px-2 py-1 rounded shadow-sm text-xs transition-colors approve-return" data-id="<?php echo $r->id; ?>" title="Approve">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                            <button type="button" class="text-white bg-orange-500 hover:bg-orange-600 px-2 py-1 rounded shadow-sm text-xs transition-colors reject-return" data-id="<?php echo $r->id; ?>" title="Reject">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        <?php endif; ?>

                                        <a href="<?php echo esc_url(add_query_arg(['view' => 'sales-return-invoice', 'id' => $r->id])); ?>" class="text-emerald-600 hover:text-emerald-900 bg-emerald-50 p-2 rounded-lg transition-colors" title="View Details">
                                            <i class="fa-solid fa-file-invoice"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="10" class="px-6 py-12 text-center text-gray-400">
                            <i class="fa-solid fa-receipt text-5xl mb-3 block opacity-20"></i>
                            No sales returns found.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
    const nonce = '<?php echo wp_create_nonce("frontend_ajax_nonce"); ?>';

    $('.approve-return').on('click', function() {
        if (!confirm('Are you sure you want to approve this sales return? This will RESTORE items to stock and adjust original sale record.')) return;
        const id = $(this).data('id');
        $.post(ajaxurl, { action: 'approve_salesreturn', id: id, security: nonce }, function(res) {
            if (res.success) {
                Swal.fire('Approved!', res.data, 'success').then(() => location.reload());
            } else {
                Swal.fire('Error', res.data, 'error');
            }
        });
    });

    $('.reject-return').on('click', function() {
        if (!confirm('Are you sure you want to reject this return?')) return;
        const id = $(this).data('id');
        $.post(ajaxurl, { action: 'reject_salesreturn', id: id, security: nonce }, function(res) {
            if (res.success) {
                Swal.fire('Rejected!', 'Return marked as rejected.', 'success').then(() => location.reload());
            } else {
                Swal.fire('Error', res.data, 'error');
            }
        });
    });

    $('.delete-return').on('click', function() {
        if (!confirm('Are you sure you want to delete this return?')) return;
        const id = $(this).data('id');
        $.post(ajaxurl, { action: 'delete_salesreturn', sales_id: id, security: nonce }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data);
            }
        });
    });

    // Search
    $('#searchInput').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $("#returnsTable tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });

    // Export Functions
    function getSalesReturnTableData() {
        const data = [];
        const headers = [];
        
        $('#returnsTable thead tr th').each(function(index) {
            if($(this).is(':visible')) {
                headers.push($(this).text().trim());
            }
        });
        data.push(headers);
        
        $('#returnsTable tbody tr:visible').each(function() {
            const row = [];
            $(this).find('td').each(function(index) {
                if($(this).is(':visible')) {
                    let text = $(this).text().trim();
                    text = text.replace(/\s+/g, ' ').trim();
                    row.push(text);
                }
            });
            if(row.length > 0) {
                data.push(row);
            }
        });
        
        return data;
    }

    // Print
    $('#salesreturnlistPrintBtn').on('click', function() {
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Sales Returns</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-family: Arial, sans-serif; }');
        printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 20px; }');
        printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
        printWindow.document.write('th { background-color: #1569B3; color: white; font-weight: bold; }');
        printWindow.document.write('h1 { text-align: center; color: #333; }');
        printWindow.document.write('</style></head><body>');
        printWindow.document.write('<h1>Sales Returns</h1>');
        
        const tableData = getSalesReturnTableData();
        printWindow.document.write('<table>');
        tableData.forEach(function(row, index) {
            printWindow.document.write('<tr>');
            row.forEach(function(cell) {
                const tag = index === 0 ? 'th' : 'td';
                printWindow.document.write('<' + tag + '>' + cell + '</' + tag + '>');
            });
            printWindow.document.write('</tr>');
        });
        printWindow.document.write('</table></body></html>');
        printWindow.document.close();
        printWindow.print();
    });

    // PDF
    $('#salesreturnlistPdfBtn').on('click', function() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        doc.setFontSize(18);
        doc.text('Sales Returns', 14, 22);
        
        const tableData = getSalesReturnTableData();
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
        
        doc.save('sales-returns.pdf');
    });

    // Excel
    $('#salesreturnlistExcelBtn').on('click', function() {
        const tableData = getSalesReturnTableData();
        const ws = XLSX.utils.aoa_to_sheet(tableData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Sales Returns');
        XLSX.writeFile(wb, 'sales-returns.xlsx');
    });

    // CSV
    $('#salesreturnlistCsvBtn').on('click', function() {
        const tableData = getSalesReturnTableData();
        let csv = tableData.map(row => row.map(cell => '"' + cell.replace(/"/g, '""') + '"').join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'sales-returns.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
});
</script>
