<?php
/**
 * Cash Transactions Report for Frontend Accounting
 */
global $wpdb;

$sales_table    = $wpdb->prefix . 'orabooks_db_salespayments';
$purchase_table = $wpdb->prefix . 'orabooks_db_purchasepayments';
$accounts_table = $wpdb->prefix . 'orabooks_ac_accounts';

// Get filter values from either $_POST (AJAX) or $_GET (Reset/Initial)
$filter_from_date = isset($_REQUEST['from_date']) ? sanitize_text_field($_REQUEST['from_date']) : '';
$filter_to_date   = isset($_REQUEST['to_date']) ? sanitize_text_field($_REQUEST['to_date']) : '';
$filter_type      = isset($_REQUEST['payment_type']) ? sanitize_text_field($_REQUEST['payment_type']) : '';
$filter_user      = isset($_REQUEST['user_id']) ? sanitize_text_field($_REQUEST['user_id']) : '';

// 1. Fetch Users for mapping and dropdown
$users_raw = $wpdb->get_results("SELECT id, user_login FROM {$wpdb->prefix}users");
$users_map = [];
if ($users_raw) {
    foreach ($users_raw as $u) {
        $users_map[$u->id] = $u->user_login;
    }
}

// 2. Build Query Parts
$query_parts = [];

// Sales Payments Query
$sw = ["1=1"];
if (!empty($filter_from_date)) $sw[] = $wpdb->prepare("payment_date >= %s", $filter_from_date);
if (!empty($filter_to_date))   $sw[] = $wpdb->prepare("payment_date <= %s", $filter_to_date);
if (!empty($filter_user))      $sw[] = $wpdb->prepare("created_by = %s", $filter_user);

if (empty($filter_type) || $filter_type === 'Sales') {
    $query_parts[] = "SELECT payment_date, payment_code, 'Sales' as transaction_type, payment, payment_note, created_by, account_id, id FROM $sales_table WHERE " . implode(" AND ", $sw);
}

// Purchase Payments Query
$pw = ["1=1"];
if (!empty($filter_from_date)) $pw[] = $wpdb->prepare("payment_date >= %s", $filter_from_date);
if (!empty($filter_to_date))   $pw[] = $wpdb->prepare("payment_date <= %s", $filter_to_date);
if (!empty($filter_user))      $pw[] = $wpdb->prepare("created_by = %s", $filter_user);

if (empty($filter_type) || $filter_type === 'Purchase') {
    $query_parts[] = "SELECT payment_date, payment_code, 'Purchase' as transaction_type, payment, payment_note, created_by, account_id, id FROM $purchase_table WHERE " . implode(" AND ", $pw);
}

// Final SQL Execution
$results = [];
if (!empty($query_parts)) {
    $final_sql = "(" . implode(") UNION ALL (", $query_parts) . ") ORDER BY payment_date DESC, id DESC";
    $results = $wpdb->get_results($final_sql);
}

// Fetch Account names for display mapping
$accounts_raw = $wpdb->get_results("SELECT id, account_name FROM $accounts_table");
$accounts_map = [];
if ($accounts_raw) {
    foreach($accounts_raw as $ar) {
        $accounts_map[$ar->id] = $ar->account_name;
    }
}
?>

<div id="obn-view-cash-transactions" class="obn-view-section active">
    <div class="obn-card p-6 !pt-4">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-2xl font-bold text-gray-800">Cash Transactions Report</h3>
        </div>

        <!-- Search Criteria -->
        <div class="bg-white p-6 rounded-xl border border-gray-200 mb-8 shadow-sm">
            <form id="obn-cash-report-filter" class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-4 items-end text-sm">
                <div>
                    <label class="block text-gray-700 font-bold mb-2">From Date</label>
                    <input type="date" name="from_date" value="<?php echo esc_attr($filter_from_date); ?>" class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 transition-all">
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2">To Date</label>
                    <input type="date" name="to_date" value="<?php echo esc_attr($filter_to_date); ?>" class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 transition-all">
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Payment Type</label>
                    <select name="payment_type" class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="">- All Types -</option>
                        <option value="Sales" <?php selected($filter_type, 'Sales'); ?>>Sales</option>
                        <option value="Purchase" <?php selected($filter_type, 'Purchase'); ?>>Purchase</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Created By</label>
                    <select name="user_id" class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="">- All Users -</option>
                        <?php foreach ($users_map as $uid => $uname): ?>
                            <option value="<?php echo esc_attr($uid); ?>" <?php selected($filter_user, $uid); ?>><?php echo esc_html($uname); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-bold shadow-md transition-all flex-grow">
                        Search
                    </button>
                    <button type="button" id="obn-cash-report-reset" class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-4 py-2 rounded-lg font-bold transition-all border border-gray-300">
                        Reset
                    </button>
                </div>
            </form>
        </div>

        <!-- Search Bar & Export Toolbar -->
        <div class="search-filter-bar mb-6 flex flex-col md:flex-row gap-4 items-stretch md:items-center justify-between">
            <div class="relative w-full md:w-80">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <i class="fa-solid fa-search text-gray-400"></i>
                </div>
                <input type="search" id="obn-cash-transactions-search" class="block w-full p-2 pl-10 pr-3 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Search in results...">
            </div>
            
            <div class="export-toolbar flex items-center gap-3 flex-wrap">
                <div class="flex items-center bg-gray-100 p-1.5 rounded-lg shadow-sm">
                    <button id="printBtn" class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-cash-transactions-table" data-title="Cash Transactions Report" title="Print">
                        <i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
                    </button>
                    <button id="pdfBtn" class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-cash-transactions-table" data-title="Cash Transactions Report" title="PDF">
                        <i class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span>
                    </button>
                    <button id="excelBtn" class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-cash-transactions-table" data-title="Cash_Transactions" title="Excel"><i class="fa-solid fa-file-excel mr-1"></i> <span class="hidden sm:inline">Excel</span></button>
                    <button id="csvBtn" class="obn-csv-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" data-table="#obn-cash-transactions-table" data-title="Cash_Transactions" title="CSV"><i class="fa-solid fa-file-csv mr-1"></i> <span class="hidden sm:inline">CSV</span></button>
                </div>

                <!-- Column Visibility (Now Last) -->
                <div class="relative inline-block text-left">
                    <button type="button" class="obn-column-toggle-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-semibold rounded-lg text-sm px-4 py-2.5 transition-colors flex items-center shadow-sm">
                        <i class="fa-solid fa-columns mr-2 text-indigo-500"></i> Columns
                    </button>
                    <div class="obn-column-dropdown hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-2xl border border-gray-200 z-[9999] p-4 space-y-3">
                        <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Display Columns</div>
                        <?php 
                        $cols = ['Date', 'Payment Code', 'Payment Type', 'Payment', 'Note', 'Created by', 'Account'];
                        foreach($cols as $idx => $name): ?>
                        <label class="flex items-center cursor-pointer hover:bg-gray-50 p-2 rounded-lg transition-colors text-sm font-semibold text-gray-700">
                            <input type="checkbox" class="obn-col-hide rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 mr-3 w-4 h-4" data-column="<?php echo $idx; ?>" data-table="#obn-cash-transactions-table" checked>
                            <?php echo $name; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Table -->
        <div class="overflow-x-auto bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <table id="obn-cash-transactions-table" class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 uppercase text-xs font-black">
                    <tr>
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Payment Code</th>
                        <th class="px-6 py-4 text-center">Payment Type</th>
                        <th class="px-6 py-4 text-right">Payment</th>
                        <th class="px-6 py-4">Note</th>
                        <th class="px-6 py-4">Created by</th>
                        <th class="px-6 py-4">Account</th>
                        <th class="px-6 py-4 text-center no-export">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    <?php if ($results): foreach ($results as $row): ?>
                        <tr class="hover:bg-blue-50/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo esc_html(date('Y-m-d', strtotime($row->payment_date))); ?></td>
                            <td class="px-6 py-4 font-mono font-bold text-indigo-700"><?php echo esc_html($row->payment_code); ?></td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-3 py-1 rounded-full text-[10px] font-black tracking-wider uppercase <?php echo $row->transaction_type === 'Sales' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>">
                                    <?php echo esc_html($row->transaction_type); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right font-black text-gray-900"><?php echo number_format($row->payment, 2); ?></td>
                            <td class="px-6 py-4 italic text-gray-500 font-medium"><?php echo esc_html($row->payment_note ?: '-'); ?></td>
                            <td class="px-6 py-4 font-bold"><?php echo isset($users_map[$row->created_by]) ? esc_html($users_map[$row->created_by]) : esc_html($row->created_by); ?></td>
                            <td class="px-6 py-4">
                                <span class="bg-gray-100 px-2 py-1 rounded text-xs font-bold text-gray-600">
                                    <?php echo isset($accounts_map[$row->account_id]) ? esc_html($accounts_map[$row->account_id]) : '—'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center no-export">
                                <button class="p-2 text-rose-500 hover:bg-rose-100 rounded-lg transition obn-delete-cash-tx" 
                                        data-id="<?php echo $row->id; ?>" 
                                        data-type="<?php echo $row->transaction_type; ?>" 
                                        title="Delete Transaction">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <td colspan="8" class="px-6 py-20 text-center text-gray-400">
                                <div class="flex flex-col items-center">
                                    <i class="fa-solid fa-receipt text-6xl mb-4 text-gray-200"></i>
                                    <p class="text-lg font-bold text-gray-400 uppercase tracking-widest">No matching records found</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function($) {
    function initCashReport() {
        const $form = $('#obn-cash-report-filter');
        const $container = $('#obn-view-cash-transactions');
        const $table = $('#obn-cash-transactions-table');

        $form.off('submit').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            $container.css('opacity', '0.5');
            
            $.ajax({
                url: obn_ajax.ajax_url,
                type: 'POST',
                data: formData + '&action=obn_filter_cash_transactions&security=' + obn_ajax.nonce,
                success: function(res) {
                    if(res.success) {
                        $container.replaceWith(res.data);
                    } else {
                        alert('Search failed: ' + (res.data || 'Unknown error'));
                        $container.css('opacity', '1');
                    }
                },
                error: function() {
                    alert('Request error. Please check your connection.');
                    $container.css('opacity', '1');
                }
            });
        });

        $('#obn-cash-report-reset').off('click').on('click', function() {
            $form[0].reset();
            $form.trigger('submit');
        });

        // Delete Action
        $('.obn-delete-cash-tx').off('click').on('click', function() {
            if (!confirm('Are you sure you want to delete this transaction? This action cannot be undone.')) return;

            const $btn = $(this);
            const id = $btn.data('id');
            const type = $btn.data('type');
            
            $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i>');

            $.ajax({
                url: obn_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'obn_delete_cash_transaction',
                    security: obn_ajax.nonce,
                    id: id,
                    type: type
                },
                success: function(res) {
                    if (res.success) {
                        $btn.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                            if ($container.find('tbody tr').length === 0) {
                                $form.trigger('submit');
                            }
                        });
                    } else {
                        alert(res.data);
                        $btn.prop('disabled', false).html('<i class="fa-solid fa-trash-can"></i>');
                    }
                },
                error: function() {
                    alert('Delete request failed.');
                    $btn.prop('disabled', false).html('<i class="fa-solid fa-trash-can"></i>');
                }
            });
        });
    }

    // Run on document ready
    $(document).ready(initCashReport);
    if (typeof obn_reports_init_loaded === 'undefined') {
        initCashReport();
    }
})(jQuery);
</script>
