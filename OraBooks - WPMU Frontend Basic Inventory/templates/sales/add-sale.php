<?php
if (!defined('ABSPATH'))
    exit;

global $wpdb;

// Fetch Dropdown Data
$warehouses = $wpdb->get_results("SELECT id, warehouse_name, warehouse_type FROM {$wpdb->prefix}orabooks_db_warehouse WHERE status=1 ORDER BY id ASC");
$customers = $wpdb->get_results("SELECT id, customer_name FROM {$wpdb->prefix}orabooks_db_customers ORDER BY id ASC");
$payment_types = $wpdb->get_results("SELECT id, payment_type FROM {$wpdb->prefix}orabooks_db_paymenttypes WHERE status=1 ORDER BY id ASC");
// Try to get accounts from ac_accounts first with flexible conditions
$accounts = $wpdb->get_results("SELECT id, account_name, account_code, account_selection_name FROM {$wpdb->prefix}orabooks_ac_accounts WHERE (status=1 OR status IS NULL) AND (delete_bit=0 OR delete_bit IS NULL) ORDER BY account_code ASC");

// Debug: Add some sample accounts if empty for testing
if (empty($accounts)) {
    $accounts = [
        (object) ['id' => 1, 'account_code' => '1001', 'account_name' => 'Cash Account', 'account_selection_name' => 'Cash Account'],
        (object) ['id' => 2, 'account_code' => '1002', 'account_name' => 'Bank Account - DBBL', 'account_selection_name' => 'Bank Account - DBBL'],
        (object) ['id' => 3, 'account_code' => '1003', 'account_name' => 'Bank Account - BRAC', 'account_selection_name' => 'Bank Account - BRAC'],
    ];
}
$coa_accounts = $wpdb->get_results("SELECT id, account_name, account_code FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE status=1 ORDER BY account_code ASC");
$taxes = $wpdb->get_results("SELECT id, tax_name, tax FROM {$wpdb->prefix}orabooks_db_tax WHERE status=1 ORDER BY id ASC");
$nonce = wp_create_nonce('frontend_ajax_nonce');
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 md:p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-4 md:mb-6 gap-3 md:gap-4">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg mr-4">
                <i class="fa-solid fa-cart-shopping text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">New Sale</h1>
                <p class="text-sm text-gray-500 mt-1">Create a new sales invoice</p>
            </div>
        </div>
        <a href="?view=view-sales"
            class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg hover:from-blue-700 hover:to-indigo-700 transition-all font-medium shadow-md hover:shadow-lg">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to List
        </a>
    </div>

    <form id="sales-form" class="space-y-3 md:space-y-6 relative">
        <input type="hidden" name="action" value="insert_sale">
        <input type="hidden" name="security" value="<?php echo esc_attr($nonce); ?>">
        <input type="hidden" name="store_id" value="1">

        <!-- Top Section: Sale Info -->
        <div class="bg-gray-50 rounded-lg p-3 md:p-5 border border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
                <i class="fa-solid fa-file-invoice mr-2 text-blue-600"></i> Sale Details
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 md:gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sales Code</label>
                    <div class="flex rounded-lg shadow-sm group">
                        <span
                            class="inline-flex items-center px-3 border border-r-0 border-gray-300 bg-gray-100 text-gray-500 rounded-l-lg">
                            <i class="fa-solid fa-hashtag text-sm"></i>
                        </span>
                        <input type="text" name="sales_code" id="sales_code" readonly
                            class="flex-1 block w-full rounded-none rounded-r-lg border-gray-300 bg-gray-50 text-gray-600 cursor-not-allowed focus:ring-blue-500 focus:border-blue-500 h-10 md:h-11 px-3 text-sm"
                            placeholder="Generating...">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Warehouse <span class="text-red-500">*</span>
                    </label>
                    <select name="warehouse_id" id="warehouse_id" required
                        class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm h-10 md:h-11">
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?php echo esc_attr($w->id); ?>" <?php selected($w->warehouse_type, 'system'); ?>><?php echo esc_html($w->warehouse_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Customer <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-2">
                        <select name="customer_id" id="customer_id" required
                            class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm h-10 md:h-11">
                            <option value="">- Select Customer -</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?php echo esc_attr($c->id); ?>"><?php echo esc_html($c->customer_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button"
                            class="w-10 md:w-12 h-10 md:h-11 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 border border-blue-200 transition-colors flex items-center justify-center flex-shrink-0"
                            title="Add New Customer" onclick="openCustomerModal()">
                            <i class="fa-solid fa-plus text-lg"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reference No.</label>
                    <input type="text" name="reference_no" id="reference_no"
                        class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm h-10 md:h-11 px-3"
                        placeholder="e.g. PO-123">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sales Date</label>
                    <input type="date" name="sales_date" id="sales_date" value="<?php echo date('Y-m-d'); ?>"
                        class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm h-10 md:h-11 px-3">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                    <input type="date" name="due_date" id="due_date" value="<?php echo date('Y-m-d'); ?>"
                        class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm h-10 md:h-11 px-3">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="sales_status" id="sales_status"
                        class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm py-2 h-10 md:h-11">
                        <option value="Delivered">Delivered</option>
                        <option value="Pending">Pending</option>
                        <option value="Ordered">Ordered</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Middle Section: Items Table -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden shadow-sm">
            <div
                class="p-3 md:p-4 bg-gray-50 border-b border-gray-200 flex flex-col md:flex-row justify-between items-center gap-3 md:gap-4">
                <div class="w-full md:w-auto flex-1 max-w-2xl flex gap-2">
                    <div class="flex-1 flex rounded-lg shadow-sm group">
                        <div
                            class="inline-flex items-center px-4 rounded-l-lg border border-r-0 border-gray-300 bg-white text-gray-400 group-focus-within:text-blue-500 group-focus-within:border-blue-500 transition-colors">
                            <i class="fa-solid fa-search"></i>
                        </div>
                        <input type="text" id="item-autocomplete"
                            class="flex-1 block w-full rounded-none rounded-r-lg border-gray-300 leading-5 bg-white placeholder-gray-500 focus:outline-none focus:border-blue-500 focus:ring-0 sm:text-sm h-11 px-3 py-2"
                            placeholder="Search items by name, code or SKU...">
                    </div>
                    <button type="button"
                        class="w-12 h-11 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 border border-blue-200 transition-colors flex items-center justify-center flex-shrink-0"
                        title="Add New Item" onclick="openItemModal()">
                        <i class="fa-solid fa-plus-circle text-xl"></i>
                    </button>
                </div>
                <button type="button" id="add-blank-row"
                    class="w-full md:w-auto px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium shadow-sm h-11 flex items-center justify-center">
                    <i class="fa-solid fa-plus mr-2"></i> Add Row
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[1000px]" id="items-table">
                    <thead>
                        <tr
                            class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm uppercase tracking-wider">
                            <th class="p-3 font-semibold border-b w-64">Item Name</th>
                            <th class="p-3 font-semibold border-b w-20 text-center">Stock</th>
                            <th class="p-3 font-semibold border-b w-40">Account</th>
                            <th class="p-3 font-semibold border-b w-32">Qty</th>
                            <th class="p-3 font-semibold border-b w-32">Unit Price</th>
                            <th class="p-3 font-semibold border-b w-24">Discount</th>
                            <th class="p-3 font-semibold border-b w-20">Tax %</th>
                            <th class="p-3 font-semibold border-b w-24">Tax Amt</th>
                            <th class="p-3 font-semibold border-b w-32">Total</th>
                            <th class="p-3 font-semibold border-b w-16 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="items-tbody" class="divide-y divide-gray-100">
                        <!-- rows appended dynamically -->
                        <tr id="empty-row-msg">
                            <td colspan="9" class="p-8 text-center text-gray-400">
                                <i class="fa-solid fa-cart-plus text-4xl mb-3 block opacity-20"></i>
                                Search and select items to add them to the sale
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bottom Section: Calculations & Payment -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
            <!-- Left: Extras & Notes -->
            <div class="bg-gray-50 rounded-lg p-3 md:p-5 border border-gray-200 h-full">
                <h2 class="text-lg font-semibold text-gray-800 mb-3">Additional Details</h2>

                <div
                    class="flex justify-between items-center p-3 bg-white rounded-lg border border-gray-200 mb-3 shadow-sm">
                    <span class="font-medium text-gray-700">Total Quantity:</span>
                    <span id="total_qty_text" class="text-xl font-bold text-blue-600">0.00</span>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Other Charges</label>
                        <div class="flex rounded-md shadow-sm">
                            <input type="number" step="0.01" name="other_charges_input" id="other_charges_input"
                                value="" placeholder="0.00"
                                class="w-[60%] rounded-l-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 h-10 md:h-11 px-3">
                            <select name="other_charges_tax_id" id="other_charges_tax_id"
                                class="w-[40%] rounded-r-lg border-l-0 border-gray-300 bg-gray-50 focus:ring-blue-500 focus:border-blue-500 text-sm h-10 md:h-11 px-2">
                                <option value="">No Tax</option>
                                <?php foreach ($taxes as $t): ?>
                                    <option value="<?php echo esc_attr($t->id); ?>"
                                        data-percent="<?php echo esc_attr($t->tax); ?>">
                                        <?php echo esc_html($t->tax_name . ' (' . $t->tax . '%)'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Discount to All</label>
                        <div class="flex rounded-md shadow-sm">
                            <input type="number" step="0.01" id="discount_to_all_input" name="discount_to_all_input"
                                value="" placeholder="0.00"
                                class="w-[60%] rounded-l-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 h-10 md:h-11 px-3">
                            <select id="discount_to_all_type" name="discount_to_all_type"
                                class="w-[40%] rounded-r-lg border-l-0 border-gray-300 bg-gray-50 focus:ring-blue-500 focus:border-blue-500 text-sm h-10 md:h-11 px-2">
                                <option value="Percentage">Percentage %</option>
                                <option value="Fixed">Fixed Amount</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sale Note</label>
                        <textarea name="sales_note" id="sales_note" rows="3"
                            class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm p-3"
                            placeholder="Add a note..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Right: Totals & Payment -->
            <div class="bg-gray-50 rounded-lg p-3 md:p-5 border border-gray-200 h-full">
                <h2 class="text-lg font-semibold text-gray-800 mb-3">Payment & Totals</h2>

                <div class="space-y-3 mb-6">
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Subtotal</span>
                        <span class="font-bold text-gray-800" id="subtotal_text">0.00</span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Other Charges</span>
                        <span class="font-bold text-gray-800" id="other_charges_text">0.00</span>
                    </div>
                    <div class="flex justify-between text-sm text-red-600">
                        <span>Discount on All</span>
                        <span class="font-bold">- <span id="discount_all_text">0.00</span></span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Tax Total</span>
                        <span class="font-bold text-gray-800" id="tax_total_text">0.00</span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Round Off</span>
                        <span class="font-bold text-gray-800" id="round_off_text">0.00</span>
                    </div>
                    <div
                        class="border-t border-gray-300 pt-3 flex justify-between text-lg font-bold text-gray-900 items-end">
                        <span>Grand Total</span>
                        <span class="text-2xl text-green-600" id="grand_total_text">0.00</span>
                    </div>
                </div>

                <div class="bg-white p-3 md:p-4 rounded-lg border border-blue-100 shadow-sm mb-4">
                    <h3 class="text-sm font-semibold text-blue-800 mb-3 flex items-center">
                        <i class="fa-solid fa-wallet mr-2"></i> Make Payment
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Payment Type</label>
                            <select id="payment_type_id" name="payment_type_id"
                                class="w-full rounded border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500 h-10 md:h-11">
                                <option value="">- Select -</option>
                                <?php foreach ($payment_types as $pt): ?>
                                    <option value="<?php echo esc_attr($pt->id); ?>">
                                        <?php echo esc_html($pt->payment_type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Paid Amount</label>
                            <input type="number" step="0.01" name="payment_amount" id="payment_amount" value=""
                                placeholder="0.00"
                                class="w-full rounded border-2 border-blue-400 text-sm focus:ring-blue-500 focus:border-blue-500 font-semibold text-blue-700 bg-blue-50/30 h-10 md:h-11 px-3">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Account</label>
                            <select id="account_id" name="account_id"
                                class="w-full rounded border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500 h-10 md:h-11">
                                <option value="">- Select -</option>
                                <?php foreach ($coa_accounts as $ca): ?>
                                    <option value="<?php echo esc_attr($ca->id); ?>">
                                        <?php echo esc_html(($ca->account_code ? $ca->account_code . ' - ' : '') . $ca->account_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Payment Note</label>
                            <textarea name="payment_note" id="payment_note" rows="1"
                                class="w-full rounded border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500 min-h-[44px] leading-tight py-2 px-3"></textarea>
                        </div>
                    </div>
                </div>

                <button type="button" id="save-sale"
                    class="w-full py-4 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all font-bold text-lg shadow-md flex justify-center items-center">
                    <i class="fa-solid fa-check-circle mr-2"></i> Save Sale
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Template for Row (Hidden) -->
<template id="row-template">
    <tr class="bg-white border-b hover:bg-gray-50 transition-colors" data-item-id="">
        <td class="p-3">
            <input type="hidden" name="items[][item_id]" class="row-item-id">
            <input type="text"
                class="w-full border-gray-200 rounded text-sm row-name focus:border-blue-400 focus:ring-blue-400 transition-all font-medium text-gray-800"
                placeholder="Type to search item...">
            <div class="text-[10px] text-gray-400 mt-1 row-code"></div>
        </td>
        <td class="p-3 text-center">
            <span class="inline-block px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs font-medium row-stock"></span>
        </td>
        <td class="p-3">
            <select
                class="row-account-id w-full rounded border-gray-300 text-xs focus:ring-blue-500 focus:border-blue-500 bg-transparent">
                <option value="">- Account -</option>
                <?php foreach ($coa_accounts as $ca): ?>
                    <option value="<?php echo esc_attr($ca->id); ?>">
                        <?php echo esc_html($ca->account_code . ' - ' . $ca->account_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="p-3">
            <div class="flex items-center">
                <button type="button"
                    class="qty-dec w-6 h-8 bg-gray-200 hover:bg-gray-300 rounded-l flex items-center justify-center text-gray-600">-</button>
                <input type="number" min="0.01" step="0.01"
                    class="w-16 h-8 border-y border-gray-300 text-center text-sm row-qty focus:ring-0 focus:outline-none"
                    value="1">
                <button type="button"
                    class="qty-inc w-6 h-8 bg-gray-200 hover:bg-gray-300 rounded-r flex items-center justify-center text-gray-600">+</button>
            </div>
        </td>
        <td class="p-3">
            <input type="number" step="0.01" class="w-24 rounded border-gray-300 text-sm row-price" value="0.00">
        </td>
        <td class="p-3">
            <input type="number" step="0.01" class="w-20 rounded border-gray-300 text-sm row-discount" value="0.00">
        </td>
        <td class="p-3">
            <input type="number" step="0.01" class="w-16 rounded border-gray-300 text-sm row-tax" value="0" readonly
                tabindex="-1">
        </td>
        <td class="p-3">
            <input type="text" class="w-20 rounded border-gray-300 bg-gray-50 text-sm row-tax-amt cursor-default"
                value="0.00" readonly tabindex="-1">
        </td>
        <td class="p-3 font-medium text-gray-900">
            <input type="text"
                class="w-24 rounded border-gray-300 bg-gray-50 text-sm font-semibold row-total cursor-default"
                value="0.00" readonly tabindex="-1">
        </td>
        <td class="p-3 text-center">
            <button type="button" class="text-red-500 hover:text-red-700 p-2 remove-row" title="Remove Item">
                <i class="fa-solid fa-trash"></i>
            </button>
        </td>
    </tr>
</template>

<script>
    jQuery(document).ready(function ($) {
        const ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
        const nonce = '<?php echo esc_js($nonce); ?>';

        function fmt(v) { return parseFloat(v || 0).toFixed(2); }
        function toFloat(v) { return Number((v || 0).toString().replace(/,/g, '')) || 0; }

        // Init Select2 with local parent to fix alignment
        $('#warehouse_id, #customer_id, #payment_type_id, #account_id').select2({
            width: '100%',
            dropdownParent: $('#sales-form')
        });

        // Generate Code
        function generateSalesCode() {
            $.post(ajaxurl, { action: 'generate_sales_code', security: nonce }, function (res) {
                if (res.success) $('#sales_code').val(res.data.code);
            }, 'json');
        }
        generateSalesCode();

        // Autocomplete
        $('#item-autocomplete').autocomplete({
            source: function (request, response) {
                $.post(ajaxurl, { action: 'search_sales_items', term: request.term, security: nonce }, function (res) {
                    if (res.success) {
                        response(res.data.map(function (i) { return { label: i.item_name + ' (' + (i.item_code || i.sku || '') + ')', value: i.item_name, data: i }; }));
                    } else response([]);
                }, 'json');
            },
            minLength: 1,
            select: function (evt, ui) {
                addItemRow(ui.item.data);
                $(this).val('');
                return false;
            }
        });

        // Add Blank Row
        $('#add-blank-row').on('click', function () {
            addItemRow({
                id: '',
                item_name: '',
                item_code: '',
                sku: '',
                stock: 0,
                price: 0,
                tax_percent: 0
            });
        });

        // Add Row
        function addItemRow(item) {
            $('#empty-row-msg').hide();
            const tpl = document.getElementById('row-template');
            const clone = tpl.content.cloneNode(true);
            const row = $(clone).find('tr');

            row.attr('data-item-id', item.id);
            row.find('.row-item-id').val(item.id);
            row.find('.row-name').val(item.item_name);
            row.find('.row-code').text(item.item_code || item.sku || '');
            row.find('.row-stock').text(item.stock || '0');
            row.find('.row-price').val(item.price);
            row.find('.row-tax').val(item.tax_percent || 0);

            // Handle Default Account
            if (item.sales_account_id) {
                row.find('.row-account-id').val(item.sales_account_id);
            } else {
                // Fallback: try to find a sales/income account (Specifically code 4000)
                const salesAcc = row.find('.row-account-id option').filter(function () {
                    const text = $(this).text().toLowerCase();
                    return text.indexOf('4000') !== -1 || text === 'sales' || text.endsWith(' - sales');
                }).val();
                if (salesAcc) row.find('.row-account-id').val(salesAcc);
            }

            $('#items-tbody').append(row);

            // Init Select2 for account in row
            row.find('.row-account-id').select2({
                width: '100%',
                dropdownParent: $('#sales-form')
            });

            // Init Autocomplete for this row's name input
            row.find('.row-name').autocomplete({
                source: function (request, response) {
                    $.post(ajaxurl, { action: 'search_sales_items', term: request.term, security: nonce }, function (res) {
                        if (res.success) {
                            response(res.data.map(function (i) { return { label: i.item_name + ' (' + (i.item_code || i.sku || '') + ')', value: i.item_name, data: i }; }));
                        } else response([]);
                    }, 'json');
                },
                minLength: 1,
                select: function (evt, ui) {
                    const data = ui.item.data;
                    const r = $(this).closest('tr');
                    r.attr('data-item-id', data.id);
                    r.find('.row-item-id').val(data.id);
                    r.find('.row-name').val(data.item_name);
                    r.find('.row-code').text(data.item_code || data.sku || '');
                    r.find('.row-stock').text(data.stock || '0');
                    r.find('.row-price').val(data.price);
                    r.find('.row-tax').val(data.tax_percent || 0);

                    // Handle Default Account
                    if (data.sales_account_id) {
                        r.find('.row-account-id').val(data.sales_account_id);
                    } else {
                        const salesAcc = r.find('.row-account-id option').filter(function () {
                            const text = $(this).text().toLowerCase();
                            return text.indexOf('4000') !== -1 || text === 'sales' || text.endsWith(' - sales');
                        }).val();
                        if (salesAcc) r.find('.row-account-id').val(salesAcc);
                    }

                    r.find('.row-account-id').trigger('change.select2');
                    recalc();
                    return false;
                }
            });

            recalc();

            // Bind events for new row
            row.find('.qty-inc').on('click', function () {
                const input = $(this).prev('input');
                input.val(parseFloat(input.val() || 0) + 1).trigger('input');
            });
            row.find('.qty-dec').on('click', function () {
                const input = $(this).next('input');
                const v = parseFloat(input.val() || 0);
                if (v > 1) input.val(v - 1).trigger('input');
            });
            row.find('input, select').on('input change', recalc);
            row.find('.remove-row').on('click', function () {
                $(this).closest('tr').remove();
                if ($('#items-tbody tr').length === 0 || $('#items-tbody tr:visible').length === 0) $('#empty-row-msg').show();
                recalc();
            });
        }

        // Calculation
        function recalc() {
            let subtotal = 0, tax_total = 0, total_qty = 0;

            $('#items-tbody tr:not(#empty-row-msg)').each(function () {
                const qty = toFloat($(this).find('.row-qty').val());
                const price = toFloat($(this).find('.row-price').val());
                const discount = toFloat($(this).find('.row-discount').val());
                const tax_pct = toFloat($(this).find('.row-tax').val());

                const line_before = qty * price;
                const line_discount = discount; // Fixed discount per item row as per UI
                const taxable = Math.max(0, line_before - line_discount);
                const tax_amt = (taxable * tax_pct) / 100;
                const line_total = taxable + tax_amt;

                $(this).find('.row-tax-amt').val(fmt(tax_amt));
                $(this).find('.row-total').val(fmt(line_total));

                subtotal += taxable;
                tax_total += tax_amt;
                total_qty += qty;
            });

            // Other charges
            const other_charges = toFloat($('#other_charges_input').val());
            let other_tax_amt = 0;
            const other_tax_sel = $('#other_charges_tax_id option:selected');
            if (other_tax_sel.val()) {
                const pct = toFloat(other_tax_sel.data('percent'));
                other_tax_amt = (other_charges * pct) / 100;
            }

            // Discount All
            const discount_val = toFloat($('#discount_to_all_input').val());
            const discount_type = $('#discount_to_all_type').val();
            let discount_all_amt = 0;
            if (discount_type === 'Percentage') {
                discount_all_amt = (subtotal * discount_val) / 100;
            } else {
                discount_all_amt = discount_val;
            }

            const subtotal_after_discount = Math.max(0, subtotal - discount_all_amt);
            const tax_total_sum = tax_total + other_tax_amt;
            const grand_before_round = subtotal_after_discount + tax_total_sum + other_charges;
            const grand_total = Math.round((grand_before_round) * 100) / 100;
            const round_off = +(grand_total - grand_before_round).toFixed(2);

            $('#subtotal_text').text(fmt(subtotal));
            $('#other_charges_text').text(fmt(other_charges + other_tax_amt));
            $('#discount_all_text').text(fmt(discount_all_amt));
            $('#tax_total_text').text(fmt(tax_total_sum));
            $('#round_off_text').text(fmt(round_off));
            $('#grand_total_text').text(fmt(grand_total));
            $('#total_qty_text').text(fmt(total_qty));
        }

        $('#other_charges_input, #other_charges_tax_id, #discount_to_all_input, #discount_to_all_type').on('input change', recalc);

        // Save
        $('#save-sale').on('click', function () {
            const btn = $(this);
            if (!$('#warehouse_id').val()) { alert('Select warehouse'); return; }
            if (!$('#customer_id').val()) { alert('Select customer'); return; }
            if ($('#items-tbody tr:not(#empty-row-msg)').length === 0) { alert('Add at least one item'); return; }

            const items = [];
            $('#items-tbody tr:not(#empty-row-msg)').each(function () {
                const item_id = $(this).find('.row-item-id').val();
                items.push({
                    item_id: item_id,
                    name: $(this).find('.row-name').val(),
                    account_id: $(this).find('.row-account-id').val(),
                    qty: $(this).find('.row-qty').val(),
                    unit_price: $(this).find('.row-price').val(),
                    discount: $(this).find('.row-discount').val(),
                    tax_percent: $(this).find('.row-tax').val(),
                    tax_amt: $(this).find('.row-tax-amt').val(),
                    total: $(this).find('.row-total').val()
                });
            });

            const payload = {
                action: 'insert_sale',
                security: nonce,
                store_id: $('input[name="store_id"]').val(),
                warehouse_id: $('#warehouse_id').val(),
                sales_code: $('#sales_code').val(),
                reference_no: $('#reference_no').val(),
                sales_date: $('#sales_date').val(),
                due_date: $('#due_date').val(),
                sales_status: $('#sales_status').val(),
                customer_id: $('#customer_id').val(),
                other_charges_input: toFloat($('#other_charges_input').val()),
                other_charges_tax_id: $('#other_charges_tax_id').val(),
                discount_to_all_input: toFloat($('#discount_to_all_input').val()),
                discount_to_all_type: $('#discount_to_all_type').val(),
                subtotal: toFloat($('#subtotal_text').text()),
                tax_total: toFloat($('#tax_total_text').text()),
                round_off: toFloat($('#round_off_text').text()),
                grand_total: toFloat($('#grand_total_text').text()),
                sales_note: $('#sales_note').val(),
                payment_type_id: $('#payment_type_id').val(),
                payment_amount: toFloat($('#payment_amount').val()),
                payment_note: $('#payment_note').val(),
                account_id: $('#account_id').val(),
                items_json: JSON.stringify(items)
            };

            btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Saving...');
            $.post(ajaxurl, payload, function (res) {
                if (res.success) {
                    alert(res.data.message);
                    window.location.href = '?view=sales-invoice&sales_id=' + res.data.sale_id;
                } else {
                    alert('Error: ' + (res.data || 'Failed to save'));
                }
            }, 'json').always(function () {
                btn.prop('disabled', false).html('<i class="fa-solid fa-check-circle mr-2"></i> Save Sale');
            });
        });


        // Callback for New Item Modal
        window.onItemAdded = function (item) {
            addItemRow({
                id: item.item_id,
                item_name: item.item_name,
                item_code: item.item_code,
                sku: item.sku,
                stock: 0,
                price: item.price,
                tax_percent: item.tax_percent
            });
        };
    });
</script>

<?php include FRONTEND_INVENTORY_TEMPLATE_PATH . 'modals/modal_customer.php'; ?>
<?php include FRONTEND_INVENTORY_TEMPLATE_PATH . 'modals/modal_item.php'; ?>
