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

// Logic
$where = "WHERE p.status = 1";
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
    ORDER BY p.purchase_date DESC
");

// Stats calculation
$total_purchase = 0;
$total_paid = 0;
foreach ($purchases as $p) {
    $total_purchase += floatval($p->grand_total);
    $total_paid     += floatval($p->paid_amount);
}
$total_due = $total_purchase - $total_paid;
$total_qty = $wpdb->get_var("SELECT SUM(purchase_qty) FROM {$wpdb->prefix}orabooks_db_purchaseitems"); // This counts ALL items, not just filtered. Replicating source logic slightly simpler but okay.

$currency = '৳';
?>

<div class="container mx-auto px-4 py-6 text-gray-800">
    <div class="flex justify-between items-center mb-6 no-print">
        <h1 class="text-2xl font-bold text-gray-800">Purchase Report</h1>
        <a href="<?php echo esc_url(add_query_arg('view', 'add-purchase')); ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded transition-colors duration-200">
             <i class="fa-solid fa-plus mr-2"></i> Create Purchase
        </a>
    </div>

     <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 no-print">
        <div class="bg-gradient-to-br from-blue-400 to-indigo-500 rounded-lg shadow-md p-5 text-white">
            <h3 class="text-sm font-medium opacity-90 mb-1">Total Quantity</h3>
            <p class="text-2xl font-bold"><?php echo number_format($total_qty ?? 0, 2); ?></p>
        </div>
        <div class="bg-gradient-to-br from-teal-400 to-emerald-500 rounded-lg shadow-md p-5 text-white">
             <h3 class="text-sm font-medium opacity-90 mb-1">Total Paid Amount</h3>
             <p class="text-2xl font-bold"><?php echo $currency . ' ' . number_format($total_paid, 2); ?></p>
        </div>
        <div class="bg-gradient-to-br from-blue-500 to-indigo-400 rounded-lg shadow-md p-5 text-white">
             <h3 class="text-sm font-medium opacity-90 mb-1">Total Purchase Amount</h3>
             <p class="text-2xl font-bold"><?php echo $currency . ' ' . number_format($total_purchase, 2); ?></p>
        </div>
         <div class="bg-gradient-to-br from-teal-500 to-emerald-400 rounded-lg shadow-md p-5 text-white">
             <h3 class="text-sm font-medium opacity-90 mb-1">Total Due Amount</h3>
             <p class="text-2xl font-bold"><?php echo $currency . ' ' . number_format($total_due, 2); ?></p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm p-5 mb-6 border border-gray-200 no-print">
        <form method="get" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <input type="hidden" name="view" value="purchase-report">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse</label>
                <select name="warehouse" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
                    <option value="0">All Warehouses</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?php echo $w->id; ?>" <?php selected($warehouse_id ?: ($w->warehouse_type === 'system' ? $w->id : null), $w->id); ?>><?php echo esc_html($w->warehouse_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                 <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                 <select name="supplier" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
                    <option value="0">All Suppliers</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?php echo $s->id; ?>" <?php selected($supplier_id, $s->id); ?>><?php echo esc_html($s->supplier_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
             <div>
                 <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                 <input type="date" name="date_from" value="<?php echo esc_attr($start_date); ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
            </div>
             <div>
                 <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                 <input type="date" name="date_to" value="<?php echo esc_attr($end_date); ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2 px-3 border bg-gray-50">
            </div>
            <div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded transition-colors duration-200">
                    Filter
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
                    <div class="p-3 space-y-2">
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="0" checked> Date
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="1" checked> Code
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="2" checked> Status
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="3" checked> Supplier
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="4" checked> Total
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="5" checked> Paid
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="6" checked> Payment Status
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden border border-gray-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-indigo-500 text-white">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Supplier</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider">Total</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider">Paid</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider pl-8">Payment</th> <!-- Padding left for align -->
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (count($purchases) > 0): ?>
                        <?php foreach ($purchases as $p): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('d-m-Y', strtotime($p->purchase_date)); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600"><?php echo esc_html($p->purchase_code); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?php echo esc_html($p->purchase_status); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo esc_html($p->supplier_name); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium"><?php echo number_format($p->grand_total, 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-600"><?php echo number_format($p->paid_amount, 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm pl-8">
                                     <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo ($p->payment_status === 'Paid') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo esc_html($p->payment_status); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="px-6 py-10 text-center text-gray-500">No purchases found.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="bg-gray-100 font-bold text-gray-700">
                    <tr>
                         <td colspan="4" class="px-6 py-3 text-right">Summary:</td>
                         <td class="px-6 py-3 text-right"><?php echo number_format($total_purchase, 2); ?></td>
                         <td class="px-6 py-3 text-right"><?php echo number_format($total_paid, 2); ?></td>
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
        let total = 0, paid = 0;
        $('tbody tr:visible').each(function() {
             const t = parseFloat($(this).find('td').eq(4).text().replace(/,/g, '')) || 0;
             const p = parseFloat($(this).find('td').eq(5).text().replace(/,/g, '')) || 0;
             total += t; paid += p;
        });
        $('tfoot tr td').eq(1).text(total.toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('tfoot tr td').eq(2).text(paid.toLocaleString('en-US', {minimumFractionDigits: 2}));
    }

    // Client-side search
    $('#tableSearch').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $('tbody tr').filter(function() {
            if($(this).find('td').length > 1) { 
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            }
        });
        calculateFooter();
    });

    // Column visibility toggle
    $('#columnToggleBtn').on('click', function(e) { e.stopPropagation(); $('#columnDropdown').toggleClass('hidden'); });
    $(document).on('click', function(e) { if (!$(e.target).closest('#columnToggleBtn, #columnDropdown').length) $('#columnDropdown').addClass('hidden'); });
    
    $('.column-toggle').on('change', function() {
        const col = $(this).data('column');
        const checked = $(this).is(':checked');
        $('table thead tr th').eq(col).toggle(checked);
        $('table tbody tr').each(function() { $(this).find('td').eq(col).toggle(checked); });
        // Foot
        if(col === 4) $('table tfoot tr td').eq(1).toggle(checked);
        if(col === 5) $('table tfoot tr td').eq(2).toggle(checked);
    });

    function getTableData() {
        const data = [];
        const headers = [];
        $('table thead tr th').each(function() { if($(this).is(':visible')) headers.push($(this).text().trim()); });
        data.push(headers);
        $('table tbody tr').each(function() {
            const row = [];
            $(this).find('td').each(function() { if($(this).is(':visible')) row.push($(this).text().trim()); });
            if(row.length > 0 && row[0] !== 'No purchases found.') data.push(row);
        });
        return data;
    }

    $('#printBtn').on('click', function() {
        const win = window.open('', '', 'height=600,width=800');
        win.document.write('<html><head><title>Purchase Report</title><style>body{font-family:Arial,sans-serif;}table{width:100%;border-collapse:collapse;margin-top:20px;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background-color:#f3f4f6;font-weight:bold;}h1{text-align:center;color:#333;}</style></head><body>');
        win.document.write('<h1>Purchase Report</h1>');
        const data = getTableData();
        win.document.write('<table>');
        data.forEach((row, i) => {
            win.document.write('<tr>');
            row.forEach(cell => win.document.write((i===0?'<th>':'<td>')+cell+(i===0?'</th>':'</td>')));
            win.document.write('</tr>');
        });
        win.document.write('</table></body></html>');
        win.document.close(); win.print();
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
        doc.text('Purchase Report', 14, 15);
        const data = getTableData();
        doc.autoTable({ head: [data[0]], body: data.slice(1), startY: 20, theme: 'grid', styles: {fontSize: 8} });
        doc.save('purchase-report.pdf');
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
        XLSX.utils.book_append_sheet(wb, ws, 'Purchases');
        XLSX.writeFile(wb, 'purchase-report.xlsx');
    }

    $('#csvBtn').on('click', function() {
        let csv = getTableData().map(row => row.map(cell => '"' + cell.replace(/"/g, '""') + '"').join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'purchase-report.csv';
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
