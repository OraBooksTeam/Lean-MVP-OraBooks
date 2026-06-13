<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Filters
$warehouse_id = isset($_GET['warehouse']) ? intval($_GET['warehouse']) : 0;
$supplier_id  = isset($_GET['supplier']) ? intval($_GET['supplier']) : 0;
$start_date   = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$end_date     = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

// Dropdowns
$warehouses = $wpdb->get_results("SELECT id, warehouse_name, warehouse_type FROM {$wpdb->prefix}orabooks_db_warehouse WHERE status=1");
$suppliers  = $wpdb->get_results("SELECT id, supplier_name FROM {$wpdb->prefix}orabooks_db_suppliers WHERE status=1");

// Logic - Only Pending purchases
$where = "WHERE p.status = 1 AND p.purchase_status = 'Pending'";
if ($warehouse_id) $where .= $wpdb->prepare(" AND p.warehouse_id = %d", $warehouse_id);
if ($supplier_id)  $where .= $wpdb->prepare(" AND p.supplier_id = %d", $supplier_id);
if ($start_date)   $where .= $wpdb->prepare(" AND p.purchase_date >= %s", $start_date);
if ($end_date)     $where .= $wpdb->prepare(" AND p.purchase_date <= %s", $end_date);

$purchases = $wpdb->get_results("
    SELECT p.*, s.supplier_name, u.display_name
    FROM {$wpdb->prefix}orabooks_db_purchase p
    LEFT JOIN {$wpdb->prefix}orabooks_db_suppliers s ON p.supplier_id = s.id
    LEFT JOIN {$wpdb->users} u ON p.created_by = u.ID
    $where
    ORDER BY p.id DESC
");

// Stats calculation
$total_purchase = 0;
$total_qty = 0;
foreach ($purchases as $p) {
    $total_purchase += floatval($p->grand_total);
}
$total_qty = $wpdb->get_var("SELECT SUM(purchase_qty) FROM {$wpdb->prefix}orabooks_db_purchaseitems pi LEFT JOIN {$wpdb->prefix}orabooks_db_purchase p ON pi.purchase_id = p.id WHERE p.purchase_status = 'Pending'");

$currency = 'Tk';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-yellow-100 text-yellow-600 rounded-lg flex items-center justify-center mr-4 shadow-sm">
                <i class="fa-solid fa-clock text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Pending Purchases</h1>
                <p class="text-sm text-gray-500 mt-1">Approve and receive pending purchases</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="<?php echo esc_url(add_query_arg('view', 'view-purchase')); ?>" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-all font-medium shadow-md hover:shadow-lg active:scale-95">
                <i class="fa-solid fa-list mr-2"></i> All Purchases
            </a>
            <a href="<?php echo esc_url(add_query_arg('view', 'purchase-ordered-list')); ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all font-medium shadow-md hover:shadow-lg active:scale-95">
                <i class="fa-solid fa-clipboard-list mr-2"></i> Orders
            </a>
            <a href="<?php echo esc_url(add_query_arg('view', 'add-purchase')); ?>" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-all font-medium shadow-md hover:shadow-lg active:scale-95">
                 <i class="fa-solid fa-plus mr-2"></i> New Purchase
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="rounded-xl shadow-sm p-5 text-white" style="background: linear-gradient(135deg, #1a83db, #1569B3);">
            <h3 class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Pending Orders</h3>
            <p class="text-2xl font-bold"><?php echo count($purchases); ?></p>
        </div>
        <div class="rounded-xl shadow-sm p-5 text-white" style="background: linear-gradient(135deg, #41d155, #39B54A);">
             <h3 class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Total Quantity</h3>
             <p class="text-2xl font-bold"><?php echo number_format($total_qty ?? 0, 0); ?></p>
        </div>
        <div class="rounded-xl shadow-sm p-5 text-white" style="background: linear-gradient(135deg, #1a83db, #1569B3);">
             <h3 class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Pending Value</h3>
             <p class="text-2xl font-bold"><?php echo $currency . ' ' . number_format($total_purchase, 2); ?></p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-gray-50 rounded-xl border border-gray-200 p-5 mb-8">
        <form method="get" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <input type="hidden" name="view" value="purchase-pending-list">
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
                 <label class="block text-xs font-semibold text-gray-500 uppercase tracking-tighter mb-1">Supplier</label>
                 <select name="supplier" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2 px-3 border bg-white h-[42px]">
                    <option value="0">All Suppliers</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?php echo $s->id; ?>" <?php selected($supplier_id, $s->id); ?>><?php echo esc_html($s->supplier_name); ?></option>
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
                <button type="submit" class="w-full text-white font-bold py-2 px-4 rounded-lg transition-all h-[42px] flex items-center justify-center hover:opacity-90" style="background-color: #39B54A;">
                    <i class="fa-solid fa-filter mr-2"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Search Bar -->
    <div class="search-filter-bar mb-4 flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
        <div class="relative flex-1 w-full">
            <div class="absolute inset-y-0 left-0 flex items-center pl-2.5 pointer-events-none">
                <i class="fa-solid fa-search text-gray-400 text-sm"></i>
            </div>
            <input type="search" id="searchInput" class="block w-full pl-8 pr-3 py-2 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-indigo-500 focus:border-indigo-500 h-[42px]" placeholder="Search by code, supplier, or status...">
        </div>
        
        <!-- Export Buttons -->
        <div class="export-toolbar flex gap-2 flex-wrap">
            <button id="printBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none h-[42px]" title="Print">
                <i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
            </button>
            <button id="pdfBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none h-[42px]" title="Export to PDF">
                <i class="fa-solid fa-file-pdf mr-1 text-red-600"></i> <span class="hidden sm:inline">PDF</span>
            </button>
            <button id="excelBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none h-[42px]" title="Export to Excel">
                <i class="fa-solid fa-file-excel mr-1 text-green-600"></i> <span class="hidden sm:inline">Excel</span>
            </button>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table id="purchasesTable" class="min-w-full divide-y divide-gray-200">
                <thead class="text-white" style="background-color: #1569B3;">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Date</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Purchase Code</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Supplier</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider">Total</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <?php if (count($purchases) > 0): ?>
                        <?php foreach ($purchases as $p): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo date('d-m-Y', strtotime($p->purchase_date)); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-yellow-600"><?php echo esc_html($p->purchase_code); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium"><?php echo esc_html($p->supplier_name); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-yellow-100 text-yellow-800">
                                        <i class="fa-solid fa-clock mr-1"></i> Pending
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-gray-900"><?php echo number_format($p->grand_total, 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <button type="button" class="text-green-600 hover:text-green-900 bg-green-50 p-2 rounded-lg transition-colors receive-purchase" data-id="<?php echo $p->id; ?>" title="Receive Purchase">
                                        <i class="fa-solid fa-truck"></i>
                                    </button>
                                    <a href="<?php echo esc_url(add_query_arg(['view' => 'purchase-invoice', 'id' => $p->id])); ?>" class="text-blue-600 hover:text-blue-900 bg-blue-50 p-2 rounded-lg transition-colors" title="View Purchase">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <!-- <a href="<?php //echo esc_url(add_query_arg(['view' => 'edit-purchase', 'id' => $p->id])); ?>" class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 p-2 rounded-lg transition-colors" title="Edit Purchase">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <button type="button" class="text-rose-600 hover:text-rose-900 bg-rose-50 p-2 rounded-lg transition-colors delete-purchase" data-id="<?php //echo $p->id; ?>" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </button> -->
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-400">
                            <i class="fa-solid fa-clock text-5xl mb-3 block opacity-20"></i>
                            No pending purchases found with current filters.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="bg-yellow-50 font-bold text-gray-900 border-t-2 border-yellow-200">
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-right uppercase tracking-wider text-xs">Summary:</td>
                        <td class="px-6 py-4 text-right text-sm"><?php echo number_format($total_purchase, 2); ?></td>
                        <td class="px-6 py-4"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
jQuery(document).ready(function($) {
    // Receive Purchase (Change status to Received)
    $('.receive-purchase').on('click', function() {
        if (!confirm('Are you sure you want to receive this purchase? Stock will be updated and journal entry will be created.')) return;
        
        const id = $(this).data('id');
        const btn = $(this);
        
        $.post(frontend_inventory_ajax.ajax_url, {
            action: 'update_purchase_status',
            purchase_id: id,
            purchase_status: 'Received',
            security: '<?php echo wp_create_nonce("frontend_ajax_nonce"); ?>'
        }, function(res) {
            if (res.success) {
                btn.closest('tr').fadeOut(300, function() { $(this).remove(); });
                location.reload(); // Refresh to show updated counts
            } else {
                alert(res.data || 'Failed to receive purchase');
            }
        });
    });

    // Delete Purchase
    $('.delete-purchase').on('click', function() {
        if (!confirm('Are you sure you want to delete this purchase?')) return;
        
        const id = $(this).data('id');
        const btn = $(this);
        
        $.post(frontend_inventory_ajax.ajax_url, {
            action: 'delete_purchase',
            purchase_id: id,
            security: '<?php echo wp_create_nonce("frontend_ajax_nonce"); ?>'
        }, function(res) {
            if (res.success) {
                btn.closest('tr').fadeOut(300, function() { $(this).remove(); });
            } else {
                alert(res.data || 'Failed to delete purchase');
            }
        });
    });

    // Search Functionality
    $('#searchInput').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $("#purchasesTable tbody tr").filter(function() {
            var rowText = $(this).text().toLowerCase();
            $(this).toggle(rowText.indexOf(value) > -1)
        });
    });

    // Export Functions
    function getTableData() {
        const data = [];
        const headers = [];
        
        $('#purchasesTable thead tr th').each(function(index) {
            if($(this).is(':visible') && index < 5) {
                headers.push($(this).text().trim());
            }
        });
        data.push(headers);
        
        $('#purchasesTable tbody tr:visible').each(function() {
            const row = [];
            $(this).find('td').each(function(index) {
                if($(this).is(':visible') && index < 5) {
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
    $('#printBtn').on('click', function() {
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Pending Purchases</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-family: Arial, sans-serif; }');
        printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 20px; }');
        printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
        printWindow.document.write('th { background-color: #1569B3; color: white; font-weight: bold; }');
        printWindow.document.write('h1 { text-align: center; color: #333; }');
        printWindow.document.write('</style></head><body>');
        printWindow.document.write('<h1>Pending Purchases</h1>');
        
        const tableData = getTableData();
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
    $('#pdfBtn').on('click', function() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        doc.setFontSize(18);
        doc.text('Pending Purchases', 14, 22);
        
        const tableData = getTableData();
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
        
        doc.save('pending-purchases.pdf');
    });

    // Excel
    $('#excelBtn').on('click', function() {
        const tableData = getTableData();
        const ws = XLSX.utils.aoa_to_sheet(tableData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Pending Purchases');
        XLSX.writeFile(wb, 'pending-purchases.xlsx');
    });
});
</script>
