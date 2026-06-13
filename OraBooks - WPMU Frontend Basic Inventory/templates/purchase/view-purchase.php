<?php
if (!defined('ABSPATH'))
    exit;

global $wpdb;

// Filters
$warehouse_id = isset($_GET['warehouse']) ? intval($_GET['warehouse']) : 0;
$supplier_id = isset($_GET['supplier']) ? intval($_GET['supplier']) : 0;
$start_date = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$end_date = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

// Dropdowns
$warehouses = $wpdb->get_results("SELECT id, warehouse_name, warehouse_type FROM {$wpdb->prefix}orabooks_db_warehouse WHERE status=1");
$suppliers = $wpdb->get_results("SELECT id, supplier_name FROM {$wpdb->prefix}orabooks_db_suppliers WHERE status=1");

// Logic
$where = "WHERE p.status = 1";
if ($warehouse_id)
    $where .= $wpdb->prepare(" AND p.warehouse_id = %d", $warehouse_id);
if ($supplier_id)
    $where .= $wpdb->prepare(" AND p.supplier_id = %d", $supplier_id);
if ($start_date)
    $where .= $wpdb->prepare(" AND p.purchase_date >= %s", $start_date);
if ($end_date)
    $where .= $wpdb->prepare(" AND p.purchase_date <= %s", $end_date);

$purchases = $wpdb->get_results("
    SELECT p.*, s.supplier_name, u.display_name,
           (SELECT COALESCE(SUM(pi.purchase_qty), 0) 
            FROM {$wpdb->prefix}orabooks_db_purchaseitems pi 
            WHERE pi.purchase_id = p.id) as total_purchase_qty,
           (SELECT COALESCE(SUM(pri.purchase_qty), 0) 
            FROM {$wpdb->prefix}orabooks_db_purchasereturn pr
            JOIN {$wpdb->prefix}orabooks_db_purchaseitemsreturn pri ON pri.return_id = pr.id
            WHERE pr.purchase_id = p.id AND pr.return_status = 'Approved') as total_return_qty
    FROM {$wpdb->prefix}orabooks_db_purchase p
    LEFT JOIN {$wpdb->prefix}orabooks_db_suppliers s ON p.supplier_id = s.id
    LEFT JOIN {$wpdb->users} u ON p.created_by = u.ID
    $where
    HAVING (total_return_qty IS NULL OR total_return_qty < total_purchase_qty)
    ORDER BY p.id DESC
");

// Stats calculation
$total_purchase = 0;
$total_paid = 0;
foreach ($purchases as $p) {
    $total_purchase += floatval($p->grand_total);
    $total_paid += floatval($p->paid_amount);
}
$total_due = $total_purchase - $total_paid;
$total_qty = $wpdb->get_var("SELECT SUM(purchase_qty) FROM {$wpdb->prefix}orabooks_db_purchaseitems");

$currency = '৳';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <div class="flex items-center">
            <div
                class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center mr-4 shadow-sm">
                <i class="fa-solid fa-list-check text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Purchase List</h1>
                <p class="text-sm text-gray-500 mt-1">Manage and track your inventory purchases</p>
            </div>
        </div>
        <a href="<?php echo esc_url(add_query_arg('view', 'add-purchase')); ?>"
            class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-all font-medium shadow-md hover:shadow-lg active:scale-95">
            <i class="fa-solid fa-plus mr-2"></i> New Purchase
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="rounded-xl shadow-sm p-5 text-white" style="background: linear-gradient(135deg, #1a83db, #1569B3);">
            <h3 class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Total Quantity</h3>
            <p class="text-2xl font-bold"><?php echo number_format($total_qty ?? 0, 0); ?></p>
        </div>
        <div class="rounded-xl shadow-sm p-5 text-white" style="background: linear-gradient(135deg, #41d155, #39B54A);">
            <h3 class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Total Paid</h3>
            <p class="text-2xl font-bold"><?php echo $currency . ' ' . number_format($total_paid, 2); ?></p>
        </div>
        <div class="rounded-xl shadow-sm p-5 text-white" style="background: linear-gradient(135deg, #1a83db, #1569B3);">
            <h3 class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Total Purchase</h3>
            <p class="text-2xl font-bold"><?php echo $currency . ' ' . number_format($total_purchase, 2); ?></p>
        </div>
        <div class="rounded-xl shadow-sm p-5 text-white" style="background: linear-gradient(135deg, #41d155, #39B54A);">
            <h3 class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Total Due</h3>
            <p class="text-2xl font-bold"><?php echo $currency . ' ' . number_format($total_due, 2); ?></p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-gray-50 rounded-xl border border-gray-200 p-5 mb-8">
        <form method="get" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <input type="hidden" name="view" value="view-purchase">
            <div>
                <label
                    class="block text-xs font-semibold text-gray-500 uppercase tracking-tighter mb-1">Warehouse</label>
                <select name="warehouse"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2 px-3 border bg-white h-[42px]">
                    <option value="0">All Warehouses</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?php echo $w->id; ?>" <?php selected($warehouse_id ?: ($w->warehouse_type === 'system' ? $w->id : null), $w->id); ?>><?php echo esc_html($w->warehouse_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label
                    class="block text-xs font-semibold text-gray-500 uppercase tracking-tighter mb-1">Supplier</label>
                <select name="supplier"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2 px-3 border bg-white h-[42px]">
                    <option value="0">All Suppliers</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?php echo $s->id; ?>" <?php selected($supplier_id, $s->id); ?>>
                            <?php echo esc_html($s->supplier_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-tighter mb-1">From
                    Date</label>
                <input type="date" name="date_from" value="<?php echo esc_attr($start_date); ?>"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2 px-3 border bg-white h-[42px]">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-tighter mb-1">To Date</label>
                <input type="date" name="date_to" value="<?php echo esc_attr($end_date); ?>"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2 px-3 border bg-white h-[42px]">
            </div>
            <div>
                <button type="submit"
                    class="w-full text-white font-bold py-2 px-4 rounded-lg transition-all h-[42px] flex items-center justify-center hover:opacity-90"
                    style="background-color: #39B54A;">
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
            <input type="search" id="searchInput"
                class="block w-full pl-8 pr-3 py-2 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-indigo-500 focus:border-indigo-500 h-[42px]"
                placeholder="Search by code, supplier, or status...">
        </div>

        <!-- Export & Column Buttons -->
        <div class="export-toolbar flex gap-2 flex-wrap">
            <button id="printBtn"
                class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none h-[42px]"
                title="Print">
                <i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
            </button>
            <button id="pdfBtn"
                class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none h-[42px]"
                title="Export to PDF">
                <i class="fa-solid fa-file-pdf mr-1 text-red-600"></i> <span class="hidden sm:inline">PDF</span>
            </button>
            <button id="excelBtn"
                class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none h-[42px]"
                title="Export to Excel">
                <i class="fa-solid fa-file-excel mr-1 text-green-600"></i> <span class="hidden sm:inline">Excel</span>
            </button>
            <button id="csvBtn"
                class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none h-[42px]"
                title="Export to CSV">
                <i class="fa-solid fa-file-csv mr-1 text-blue-600"></i> <span class="hidden sm:inline">CSV</span>
            </button>

            <!-- Column Visibility Dropdown -->
            <div class="relative">
                <button id="columnToggleBtn"
                    class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors h-[42px]"
                    title="Toggle Columns">
                    <i class="fa-solid fa-columns mr-1"></i> Columns
                </button>
                <div id="columnDropdown"
                    class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10 transition-all">
                    <div class="p-3 space-y-2">
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="0" checked> Date
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="1" checked> Code
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="2" checked> Supplier
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="3" checked> Qty
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="4" checked> Status
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="5" checked> Total
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="6" checked> Paid
                        </label>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                            <input type="checkbox" class="column-toggle mr-2" data-column="7" checked> Payment
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table id="purchasesTable" class="min-w-full divide-y divide-gray-200">
                <thead class="text-white" style="background-color: #1569B3;">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Date</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Purchase Code
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Supplier</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider">Qty</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider">Total</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider">Paid</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wider">Payment</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <?php if (count($purchases) > 0): ?>
                        <?php foreach ($purchases as $p): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo date('d-m-Y', strtotime($p->purchase_date)); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-indigo-600">
                                    <?php echo esc_html($p->purchase_code); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                    <?php echo esc_html($p->supplier_name); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-gray-900">
                                    <?php echo number_format($p->total_purchase_qty ?? 0, 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php
                                    $status_class = 'bg-gray-100 text-gray-800';
                                    $status_icon = 'fa-circle';
                                    if ($p->purchase_status == 'Received') {
                                        $status_class = 'bg-green-100 text-green-800';
                                        $status_icon = 'fa-check-circle';
                                    }
                                    if ($p->purchase_status == 'Pending') {
                                        $status_class = 'bg-yellow-100 text-yellow-800';
                                        $status_icon = 'fa-clock';
                                    }
                                    if ($p->purchase_status == 'Ordered') {
                                        $status_class = 'bg-blue-100 text-blue-800';
                                        $status_icon = 'fa-paper-plane';
                                    }
                                    ?>
                                    <span
                                        class="px-2.5 py-1 inline-flex text-xs leading-5 font-bold rounded-full <?php echo $status_class; ?>">
                                        <i class="fa-solid <?php echo $status_icon; ?> mr-1"></i>
                                        <?php echo esc_html($p->purchase_status); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-gray-900">
                                    <?php echo number_format($p->grand_total, 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-emerald-600">
                                    <?php echo number_format($p->paid_amount, 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span
                                        class="px-2.5 py-1 inline-flex text-xs leading-5 font-bold rounded-full 
                                        <?php echo ($p->payment_status === 'Paid') ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'; ?>">
                                        <?php echo esc_html($p->payment_status); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <a href="<?php echo esc_url(add_query_arg(['view' => 'purchase-invoice', 'id' => $p->id])); ?>"
                                        class="text-emerald-600 hover:text-emerald-900 bg-emerald-50 p-2 rounded-lg transition-colors"
                                        title="Invoice">
                                        <i class="fa-solid fa-file-invoice"></i>
                                    </a>
                                    <?php if (Frontend_Inventory_Permissions::has_view_permission('purchase-ordered-list') && $p->purchase_status !== 'Received'): ?>
                                        <a href="<?php echo esc_url(add_query_arg(['view' => 'purchase-ordered-list'])); ?>"
                                            class="text-blue-600 hover:text-blue-900 bg-blue-50 p-2 rounded-lg transition-colors"
                                            title="View Purchase Orders">
                                            <i class="fa-solid fa-clipboard-list"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (Frontend_Inventory_Permissions::has_view_permission('purchase-pending-list') && $p->purchase_status !== 'Received'): ?>
                                        <a href="<?php echo esc_url(add_query_arg(['view' => 'purchase-pending-list'])); ?>"
                                            class="text-yellow-600 hover:text-yellow-900 bg-yellow-50 p-2 rounded-lg transition-colors"
                                            title="View Pending Purchases">
                                            <i class="fa-solid fa-clock"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php //if ($p->purchase_status !== 'Received'): ?>
                                    <a href="<?php echo esc_url(add_query_arg(['view' => 'edit-purchase', 'id' => $p->id])); ?>"
                                        class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 p-2 rounded-lg transition-colors"
                                        title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <button type="button"
                                        class="text-rose-600 hover:text-rose-900 bg-rose-50 p-2 rounded-lg transition-colors delete-purchase"
                                        data-id="<?php echo $p->id; ?>" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                    <?php //endif; ?>
                                    <a href="<?php echo esc_url(add_query_arg(['view' => 'add-purchase-return', 'purchase_id' => $p->id])); ?>"
                                        class="text-amber-600 hover:text-amber-900 bg-amber-50 p-2 rounded-lg transition-colors"
                                        title="Return">
                                        <i class="fa-solid fa-arrow-rotate-left"></i>
                                    </a>
                </div>
                </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="9" class="px-6 py-12 text-center text-gray-400">
                    <i class="fa-solid fa-receipt text-5xl mb-3 block opacity-20"></i>
                    No purchases found with current filters.
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
        <tfoot class="bg-indigo-50 font-bold text-gray-900 border-t-2 border-indigo-200">
            <tr>
                <td colspan="5" class="px-6 py-4 text-right uppercase tracking-wider text-xs">Summary:</td>
                <td class="px-6 py-4 text-right text-sm"><?php echo number_format($total_purchase, 2); ?></td>
                <td class="px-6 py-4 text-right text-sm text-emerald-700"><?php echo number_format($total_paid, 2); ?>
                </td>
                <td colspan="2" class="px-6 py-4"></td>
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
    jQuery(document).ready(function ($) {
        // Delete Purchase
        $('.delete-purchase').on('click', function () {
            if (!confirm('Are you sure you want to delete this purchase? This will also update item stocks.')) return;

            const id = $(this).data('id');
            const btn = $(this);

            $.post(frontend_inventory_ajax.ajax_url, {
                action: 'delete_purchase',
                purchase_id: id,
                security: '<?php echo wp_create_nonce("frontend_ajax_nonce"); ?>'
            }, function (res) {
                if (res.success) {
                    btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
                } else {
                    alert(res.data || 'Failed to delete purchase');
                }
            });
        });

        // --- Search Functionality (Client Side) ---
        $('#searchInput').on('keyup', function () {
            var value = $(this).val().toLowerCase();
            $("#purchasesTable tbody tr").filter(function () {
                var rowText = $(this).text().toLowerCase();
                // Optional: Exclude 'Action' column text if needed, but general text match is usually fine
                $(this).toggle(rowText.indexOf(value) > -1)
            });

            // Handling "No records found" visibility if necessary
            // A simple generic "No records" check could be added here
        });

        // --- Column Visibility ---
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
            const column = $(this).data('column');
            const isChecked = $(this).is(':checked');

            // Toggle Header
            $('#purchasesTable thead tr th').eq(column).toggle(isChecked);

            // Toggle Rows
            $('#purchasesTable tbody tr').each(function () {
                $(this).find('td').eq(column).toggle(isChecked);
            });

            // Toggle Footer (approximate, since footer adds columns differently)
            // For footer, since it uses colspans, simple index toggling might break layout. 
            // We will leave footer as is for now or just hide the summary cells if strictly required, 
            // but typically footer summaries remain visible or require complex colspan recalc.
        });

        // --- Export Functions ---

        function getTableData() {
            const data = [];
            const headers = [];

            $('#purchasesTable thead tr th').each(function (index) {
                if ($(this).is(':visible') && index < 7) { // Exclude Actions (last col is index 7)
                    headers.push($(this).text().trim());
                }
            });
            data.push(headers);

            $('#purchasesTable tbody tr:visible').each(function () {
                const row = [];
                $(this).find('td').each(function (index) {
                    if ($(this).is(':visible') && index < 7) {
                        let text = $(this).text().trim();
                        // Clean up badge text newlines
                        text = text.replace(/\s+/g, ' ').trim();
                        row.push(text);
                    }
                });
                if (row.length > 0) {
                    data.push(row);
                }
            });

            return data;
        }

        // Print
        $('#printBtn').on('click', function () {
            const printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>Purchase List</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: Arial, sans-serif; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 20px; }');
            printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
            printWindow.document.write('th { background-color: #f3f4f6; font-weight: bold; }');
            printWindow.document.write('h1 { text-align: center; color: #333; }');
            printWindow.document.write('</style></head><body>');
            printWindow.document.write('<h1>Purchase List</h1>');

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

        // PDF
        $('#pdfBtn').on('click', function () {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            doc.setFontSize(18);
            doc.text('Purchase List', 14, 22);

            const tableData = getTableData();
            const headers = tableData[0];
            const rows = tableData.slice(1);

            doc.autoTable({
                head: [headers],
                body: rows,
                startY: 30,
                theme: 'grid',
                styles: { fontSize: 8 },
                headStyles: { fillColor: [79, 70, 229] } // Indigo-600
            });

            doc.save('purchase-list.pdf');
        });

        // Excel
        $('#excelBtn').on('click', function () {
            const tableData = getTableData();
            const ws = XLSX.utils.aoa_to_sheet(tableData);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Purchases');
            XLSX.writeFile(wb, 'purchase-list.xlsx');
        });

        // CSV
        $('#csvBtn').on('click', function () {
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
            link.setAttribute('download', 'purchase-list.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });

    });
</script>