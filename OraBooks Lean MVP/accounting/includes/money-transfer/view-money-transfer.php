<?php
global $wpdb;
$mt_table = $wpdb->prefix . 'orabooks_ac_moneytransfer';
$acc_table = $wpdb->prefix . 'orabooks_ac_accounts';
$transfers = $wpdb->get_results("
    SELECT mt.*, 
           da.account_name as debit_account_name, 
           ca.account_name as credit_account_name 
    FROM {$mt_table} mt
    LEFT JOIN {$acc_table} da ON mt.debit_account_id = da.id
    LEFT JOIN {$acc_table} ca ON mt.credit_account_id = ca.id
    WHERE mt.status = 1 
    ORDER BY mt.id DESC
");
$mt_nonce = wp_create_nonce('obn_money_transfer_nonce');
?>
<div id="obn-view-money-transfer-list" class="obn-view-section">
    <div class="obn-card p-6 !pt-4">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-2xl font-bold text-gray-800">Money Transfer List</h3>
            <button id="obn-money-transfer-show-add"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow transition">+ Add
                Transfer</button>
        </div>
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
            <div class="relative w-full md:w-80">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <i class="fa-solid fa-search text-gray-400"></i>
                </span>
                <input type="search" id="obn-mt-search"
                    class="block w-full p-2 pl-10 pr-3 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="Search transfers...">
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center bg-gray-100 p-1.5 rounded-lg shadow-sm">
                    <button id="obn-print-btn"
                        class="obn-print-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
                        data-table="#obn-money-transfer-table" data-title="Money Transfers" title="Print">
                        <i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
                    </button>
                    <button id="obn-pdf-btn"
                        class="obn-pdf-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
                        data-table="#obn-money-transfer-table" data-title="Money Transfers" title="PDF">
                        <i class="fa-solid fa-file-pdf mr-1"></i> <span class="hidden sm:inline">PDF</span>
                    </button>
                    <button id="obn-excel-btn"
                        class="obn-excel-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
                        data-table="#obn-money-transfer-table" data-title="Money_Transfers" title="Excel">
                        <i class="fa-solid fa-file-excel mr-1"></i> <span class="hidden sm:inline">Excel</span>
                    </button>
                    <button id="obn-csv-btn"
                        class="obn-csv-btn text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none"
                        data-table="#obn-money-transfer-table" data-title="Money_Transfers" title="CSV">
                        <i class="fa-solid fa-file-csv mr-1"></i> <span class="hidden sm:inline">CSV</span>
                    </button>
                </div>

                <!-- Column Visibility -->
                <div class="relative inline-block text-left">
                    <button type="button"
                        class="obn-column-toggle-btn inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200">
                        <i class="fa-solid fa-columns mr-2"></i> Columns
                    </button>
                    <div
                        class="obn-column-dropdown hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                        <div class="py-1 p-3 space-y-2">
                            <?php
                            $mt_cols = ['Code', 'Date', 'Debit Account', 'Credit Account', 'Amount', 'Reference'];
                            foreach ($mt_cols as $idx => $name): ?>
                                <label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" checked
                                        class="obn-col-hide form-checkbox h-4 w-4 text-blue-600 rounded"
                                        data-column="<?php echo $idx; ?>" data-table="#obn-money-transfer-table">
                                    <span class="ml-3 text-sm text-gray-700 font-bold uppercase"><?php echo $name; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
            <table id="obn-money-transfer-table" class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-700 uppercase">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Debit Account</th>
                        <th class="px-4 py-3">Credit Account</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3 text-center no-export">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($transfers):
                        foreach ($transfers as $t): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-900"><?php echo esc_html($t->transfer_code); ?></td>
                                <td class="px-4 py-3 text-gray-600">
                                    <?php echo esc_html(date('d M Y', strtotime($t->transfer_date))); ?></td>
                                <td class="px-4 py-3 text-gray-800"><?php echo esc_html($t->debit_account_name); ?></td>
                                <td class="px-4 py-3 text-gray-800"><?php echo esc_html($t->credit_account_name); ?></td>
                                <td class="px-4 py-3 text-right font-bold text-gray-700">
                                    <?php echo number_format($t->amount, 2); ?></td>
                                <td class="px-4 py-3 text-gray-600"><?php echo esc_html($t->reference_no); ?></td>
                                <td class="px-4 py-3 text-center space-x-2 no-export">
                                    <button class="obn-money-transfer-edit p-1 text-blue-500 hover:text-blue-700 transition"
                                        data-id="<?php echo esc_attr($t->id); ?>"
                                        data-nonce="<?php echo esc_attr($mt_nonce); ?>" title="Edit">
                                        <i class="fa-solid fa-edit"></i>
                                    </button>
                                    <button class="obn-money-transfer-delete p-1 text-red-500 hover:text-red-700 transition"
                                        data-id="<?php echo esc_attr($t->id); ?>"
                                        data-nonce="<?php echo esc_attr($mt_nonce); ?>" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500">No transfers found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>