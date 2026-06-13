<?php
/**
 * View Quotations for Frontend Accounting
 */
global $wpdb;
$table_name      = $wpdb->prefix . 'orabooks_db_quotation';
$customers_table = $wpdb->prefix . 'orabooks_db_customers';
$users_table     = $wpdb->prefix . 'users';
$wh_table        = $wpdb->prefix . 'orabooks_db_warehouse';

// Get filter values
$filter_warehouse = isset($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : 0;
$filter_from_date = isset($_GET['from_date']) ? sanitize_text_field($_GET['from_date']) : '';
$filter_to_date   = isset($_GET['to_date']) ? sanitize_text_field($_GET['to_date']) : '';
$filter_user      = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Build WHERE conditions
$where_conditions = ["q.status = 1"];

if ($filter_warehouse > 0) {
    $where_conditions[] = $wpdb->prepare("q.warehouse_id = %d", $filter_warehouse);
}
if (!empty($filter_from_date)) {
    $where_conditions[] = $wpdb->prepare("q.quotation_date >= %s", $filter_from_date);
}
if (!empty($filter_to_date)) {
    $where_conditions[] = $wpdb->prepare("q.quotation_date <= %s", $filter_to_date);
}
if ($filter_user > 0) {
    $where_conditions[] = $wpdb->prepare("q.created_by = %d", $filter_user);
}

$where_sql = implode(" AND ", $where_conditions);

// Fetch quotations
$quotations = $wpdb->get_results("
    SELECT q.*, c.customer_name, u.display_name as created_by_name, w.warehouse_name
    FROM $table_name q 
    LEFT JOIN $customers_table c ON q.customer_id = c.id 
    LEFT JOIN $users_table u ON q.created_by = u.ID
    LEFT JOIN $wh_table w ON q.warehouse_id = w.id
    WHERE $where_sql
    ORDER BY q.id DESC
");

// Dropdowns
$warehouses = $wpdb->get_results("SELECT id, warehouse_name, warehouse_type FROM {$wh_table} WHERE status=1 ORDER BY warehouse_name ASC");
$users = $wpdb->get_results("SELECT DISTINCT u.ID, u.display_name FROM $users_table u INNER JOIN $table_name q ON u.ID = q.created_by WHERE q.status = 1 ORDER BY u.display_name ASC");

// Summary
$total_quotations = count($quotations);
$total_amount     = 0;
foreach ($quotations as $q) {
    $total_amount += floatval($q->grand_total);
}
?>

<div id="obn-view-quotation-list" class="obn-view-section">
    <div class="obn-card">
        <div class="flex flex-col md:flex-row justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-800 mb-4 md:mb-0">Quotation List</h3>
            <button id="obn-quotation-show-add" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow transition duration-200">
                <i class="fa-solid fa-plus mr-1"></i> Add New Quotation
            </button>
        </div>

        <!-- Filters -->
        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-6">
            <form id="obn-quotation-filter-form" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Warehouse</label>
                    <select id="filter_warehouse_id" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                        <option value="">- All -</option>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?php echo esc_attr($w->id); ?>" <?php selected($filter_warehouse ?: ($w->warehouse_type === 'system' ? $w->id : null), $w->id); ?>><?php echo esc_html($w->warehouse_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">From Date</label>
                    <input type="date" id="filter_from_date" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">To Date</label>
                    <input type="date" id="filter_to_date" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Created By</label>
                    <select id="filter_user_id" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                        <option value="">- All Users -</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo esc_attr($user->ID); ?>"><?php echo esc_html($user->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="button" id="obn-quotation-filter-btn" class="w-full bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded transition duration-200">
                        <i class="fa-solid fa-search mr-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
            <div class="relative w-full md:w-80">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input type="search" id="obn-quotation-search" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="Search quotations...">
            </div>
            
            <div class="flex items-center gap-3">
                <div class="flex items-center bg-gray-100 p-1.5 rounded-lg shadow-sm">
                    <button id="printBtn" class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-quotations-table" data-title="Quotations List" title="Print">
                        <i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
                    </button>
                    <button id="pdfBtn" class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-quotations-table" data-title="Quotations List" title="PDF">
                        <i class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span>
                    </button>
                    <button id="excelBtn" class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-quotations-table" data-title="Quotations_List" title="Excel">
                        <i class="fa-solid fa-file-excel mr-1"></i> <span class="hidden sm:inline">Excel</span>
                    </button>
                    <button id="csvBtn" class="obn-csv-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-quotations-table" data-title="Quotations_List" title="CSV">
                        <i class="fa-solid fa-file-csv mr-1"></i> <span class="hidden sm:inline">CSV</span>
                    </button>
                </div>

                <!-- Column Visibility -->
                <div class="relative inline-block text-left">
                    <button type="button" class="obn-column-toggle-btn inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200">
                        <i class="fa-solid fa-columns mr-2"></i> Columns
                    </button>
                    <div class="obn-column-dropdown hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                        <div class="py-1 p-3 space-y-2">
                            <?php 
                            $q_cols = ['#', 'Code', 'Date', 'Expiry', 'Customer', 'Warehouse', 'Total', 'Status'];
                            foreach($q_cols as $idx => $name): ?>
                            <label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
                                <input type="checkbox" checked class="obn-col-hide form-checkbox h-4 w-4 text-blue-600 rounded" data-column="<?php echo $idx; ?>" data-table="#obn-quotations-table">
                                <span class="ml-3 text-sm text-gray-700 font-bold uppercase"><?php echo $name; ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
            <table id="obn-quotations-table" class="w-full text-sm text-left text-gray-600">
                <thead class="bg-gray-50 text-gray-700 uppercase font-medium border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Expiry</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Warehouse</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right no-export">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($quotations): $cnt = 1; foreach ($quotations as $q): 
                        $status_colors = [
                            'Draft' => 'bg-gray-100 text-gray-800',
                            'Sent' => 'bg-blue-100 text-blue-800',
                            'Accepted' => 'bg-green-100 text-green-800',
                            'Declined' => 'bg-red-100 text-red-800',
                            'Converted' => 'bg-purple-100 text-purple-800'
                        ];
                        $status_class = $status_colors[$q->quotation_status] ?? 'bg-gray-100 text-gray-800';
                    ?>
                    <tr class="hover:bg-gray-50 transition duration-150" data-id="<?php echo esc_attr($q->id); ?>">
                        <td class="px-4 py-3"><?php echo $cnt++; ?></td>
                        <td class="px-4 py-3 font-medium text-gray-900"><?php echo esc_html($q->quotation_code); ?></td>
                        <td class="px-4 py-3"><?php echo esc_html(date('d M Y', strtotime($q->quotation_date))); ?></td>
                        <td class="px-4 py-3 text-xs text-gray-500"><?php echo !empty($q->expire_date) ? esc_html(date('d M Y', strtotime($q->expire_date))) : '-'; ?></td>
                        <td class="px-4 py-3 text-gray-900"><?php echo esc_html($q->customer_name); ?></td>
                        <td class="px-4 py-3"><?php echo esc_html($q->warehouse_name ?: '-'); ?></td>
                        <td class="px-4 py-3 text-right font-bold text-gray-800"><?php echo number_format($q->grand_total, 2); ?></td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-1 rounded text-xs font-semibold <?php echo $status_class; ?>">
                                <?php echo esc_html($q->quotation_status); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-1 no-export">
                            <button class="obn-quotation-view-invoice p-1 text-blue-500 hover:text-blue-700 transition" data-id="<?php echo esc_attr($q->id); ?>" title="View Invoice">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <button class="obn-quotation-edit p-1 text-green-500 hover:text-green-700 transition" data-id="<?php echo esc_attr($q->id); ?>" title="Edit">
                                <i class="fa-solid fa-edit"></i>
                            </button>
                            <button class="obn-quotation-delete p-1 text-red-500 hover:text-red-700 transition" data-id="<?php echo esc_attr($q->id); ?>" title="Delete">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                            <i class="fa-solid fa-file-invoice text-4xl mb-3 text-gray-300 block"></i>
                            No quotations found.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <?php if ($quotations): ?>
                <tfoot class="bg-gray-50 font-bold text-gray-700">
                    <tr>
                        <td colspan="6" class="px-4 py-3 text-right">Total:</td>
                        <td class="px-4 py-3 text-right text-blue-600"><?php echo number_format($total_amount, 2); ?></td>
                        <td colspan="2" class="no-export"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

