<?php
if (!defined('ABSPATH'))
    exit;

global $wpdb;

// Fetch Dropdown Data
$warehouses = $wpdb->get_results("SELECT id, warehouse_name, warehouse_type FROM {$wpdb->prefix}orabooks_db_warehouse WHERE status=1 ORDER BY id ASC");
$suppliers = $wpdb->get_results("SELECT id, supplier_name FROM {$wpdb->prefix}orabooks_db_suppliers ORDER BY id ASC");
$payment_types = $wpdb->get_results("SELECT id, payment_type FROM {$wpdb->prefix}orabooks_db_paymenttypes WHERE status=1 ORDER BY id ASC");
$accounts = $wpdb->get_results("SELECT id, account_name, account_code, account_selection_name FROM {$wpdb->prefix}orabooks_ac_accounts WHERE status=1 ORDER BY account_code ASC");
$coa_accounts = $wpdb->get_results("SELECT id, account_name, account_code FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE status=1 ORDER BY account_code ASC");
$taxes = $wpdb->get_results("SELECT id, tax_name, tax FROM {$wpdb->prefix}orabooks_db_tax WHERE status=1 ORDER BY id ASC");

$nonce = wp_create_nonce('frontend_ajax_nonce');
?>

<style>
    .ui-autocomplete {
        z-index: 9999 !important;
        max-height: 300px;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 5px 0;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }

    .ui-menu-item {
        padding: 8px 12px;
        cursor: pointer;
        font-size: 14px;
        color: #1e293b;
    }

    .ui-state-active,
    .ui-widget-content .ui-state-active {
        background: #eff6ff !important;
        border: none !important;
        color: #2563eb !important;
        margin: 0 !important;
    }

    #items-table thead th {
        color: #ffffff !important;
    }
</style>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-2">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center mr-4 shadow-sm">
                <i class="fa-solid fa-cart-plus text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">New Purchase</h1>
                <p class="text-sm text-gray-500 mt-1">Create a new purchase record</p>
            </div>
        </div>
        <a href="?view=view-purchase"
            class="text-white font-bold py-2 px-4 rounded-lg transition-all h-[42px] flex items-center justify-center hover:opacity-90"
            style="background-color: #39B54A;">
            <i class="fa-solid fa-arrow-left-long mr-2"></i> Back to List
        </a>
    </div>

    <form id="purchase-form" class="space-y-6 relative">
        <input type="hidden" name="action" value="insert_purchase">
        <input type="hidden" name="security" value="<?php echo esc_attr($nonce); ?>">
        <input type="hidden" name="store_id" value="1">

        <!-- Top Section: Purchase Info -->
        <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fa-solid fa-file-invoice-dollar mr-2 text-blue-600"></i> Purchase Details
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Purchase Code</label>
                    <div class="relative">
                        <span
                            class="absolute inset-y-0 left-0 pl-3 flex items-center text-blue-500 pointer-events-none">
                        </span>
                        <input type="text" name="purchase_code" id="purchase_code" readonly
                            class="pl-12 w-full rounded-lg border-gray-400 bg-gray-50 text-gray-700 cursor-not-allowed focus:ring-blue-500 focus:border-blue-500 shadow-sm border py-2"
                            placeholder="Generating...">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Warehouse <span class="text-red-500">*</span>
                    </label>
                    <select name="warehouse_id" id="acc_warehouse_id" required
                        class="w-full p-2 rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?php echo esc_attr($w->id); ?>" <?php selected($w->warehouse_type, 'system'); ?>><?php echo esc_html($w->warehouse_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Supplier <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="text" name="supplier_name" id="supplier_name" required
                            class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm py-2"
                            placeholder="Type supplier name...">
                        <input type="hidden" name="supplier_id" id="supplier_id" value="">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reference No.</label>
                    <input type="text" name="reference_no" id="reference_no"
                        class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm py-2"
                        placeholder="e.g. INV-001">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Purchase Date</label>
                    <input type="date" name="purchase_date" id="purchase_date" value="<?php echo date('Y-m-d'); ?>"
                        class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="purchase_status" id="purchase_status"
                        class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm py-2">
                        <option value="Received">Received</option>
                        <option value="Pending">Pending</option>
                        <option value="Ordered">Ordered</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Middle Section: Items Table -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden shadow-sm">
            <div
                class="p-4 bg-gray-50 border-b border-gray-200 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex-1 flex items-center gap-4 w-full">
                    <h2 class="text-lg font-semibold text-gray-800 flex items-center whitespace-nowrap">
                        <i class="fa-solid fa-box mr-2 text-blue-600"></i> Items
                    </h2>
                </div>
                <button type="button" id="add-new-row"
                    class="text-white font-bold py-2 px-4 rounded-lg transition-all h-[42px] flex items-center justify-center hover:opacity-90"
                    style="background-color: #39B54A;">
                    <i class="fa-solid fa-plus mr-2"></i> Add Row
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="items-table">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr class="text-white text-sm uppercase tracking-wider" style="background-color: #1569B3;">
                            <th class="p-3 font-semibold border-b w-1/4 text-white">Item Name</th>
                            <th class="p-3 font-semibold border-b w-24 text-center text-white">Stock</th>
                            <th class="p-3 font-semibold border-b w-40 text-white">Account</th>
                            <th class="p-3 font-semibold border-b w-32 text-white">Qty</th>
                            <th class="p-3 font-semibold border-b w-32 text-white">Unit Cost</th>
                            <th class="p-3 font-semibold border-b w-24 text-white">Discount</th>
                            <th class="p-3 font-semibold border-b w-24 text-white">Tax %</th>
                            <th class="p-3 font-semibold border-b w-24 text-right text-white">Tax Amt</th>
                            <th class="p-3 font-semibold border-b w-32 text-right text-white">Total</th>
                            <th class="p-3 font-semibold border-b w-16 text-center text-white">Action</th>
                        </tr>
                    </thead>
                    <tbody id="items-tbody" class="divide-y divide-gray-100">
                        <!-- rows -->
                        <tr id="empty-row-msg">
                            <td colspan="10" class="p-8 text-center text-gray-400">
                                <!-- <i class="fa-solid fa-cart-plus text-4xl mb-3 block opacity-20"></i>
                                Click "Add Row" and type an item name to start adding items -->
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bottom Section: Calculations & Payment -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left: Extras & Notes -->
            <div class="bg-gray-50 rounded-lg p-3 border border-gray-200 h-full">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Additional Details</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Other Charges</label>
                        <div class="flex rounded-md shadow-sm overflow-hidden border border-gray-300">
                            <input type="number" step="0.01" name="other_charges_input" id="other_charges_input"
                                value="" placeholder="0.00"
                                class="w-[60%] border-0 focus:ring-0 focus:border-0 rounded-l-lg border-r border-gray-200 py-2">
                            <select name="other_charges_tax_id" id="other_charges_tax_id"
                                class="w-[40%] border-0 focus:ring-0 focus:border-0 bg-gray-50 text-sm py-2">
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
                        <div class="flex rounded-md shadow-sm overflow-hidden border border-gray-300">
                            <input type="number" step="0.01" id="discount_to_all_input" name="discount_to_all_input"
                                value="" placeholder="0.00"
                                class="w-[60%] border-0 focus:ring-0 focus:border-0 rounded-l-lg border-r border-gray-200 py-2">
                            <select id="discount_to_all_type" name="discount_to_all_type"
                                class="w-[40%] border-0 focus:ring-0 focus:border-0 bg-gray-50 text-sm py-2">
                                <option value="Percentage">Percentage %</option>
                                <option value="Fixed">Fixed Amount</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purchase Note</label>
                        <textarea name="purchase_note" id="purchase_note" rows="3"
                            class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                            placeholder="Add a note..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Right: Totals & Payment -->
            <div class="bg-gray-50 rounded-lg p-3 border border-gray-200 h-full">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Payment & Totals</h2>

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

                <div class="bg-white p-4 rounded-lg border border-blue-100 shadow-sm mb-4">
                    <h3 class="text-sm font-semibold text-blue-800 mb-3 flex items-center">
                        <i class="fa-solid fa-wallet mr-2"></i> Make Payment
                    </h3>
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Payment Type</label>
                            <div class="flex gap-1">
                                <select id="payment_type_id" name="payment_type_id"
                                    class="flex-1 rounded border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500 h-[38px]">
                                    <option value="">- Select -</option>
                                    <?php foreach ($payment_types as $pt): ?>
                                        <option value="<?php echo esc_attr($pt->id); ?>">
                                            <?php echo esc_html($pt->payment_type); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" id="purchase-add-payment-type-btn"
                                    class="w-[38px] h-[38px] flex-shrink-0 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 border border-blue-200 transition-colors flex items-center justify-center"
                                    title="Add New Payment Type">
                                    <i class="fa-solid fa-plus text-lg"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Paid Amount</label>
                            <input type="number" step="0.01" name="payment_amount" id="payment_amount" value=""
                                placeholder="0.00"
                                class="w-full rounded border-2 border-blue-300 text-sm focus:ring-blue-500 focus:border-blue-500 h-[38px] font-bold text-blue-700">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Account</label>
                            <select id="account_id" name="account_id"
                                class="w-full p-2 rounded border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">- Select -</option>
                                <?php
                                $is_first = true;
                                foreach ($accounts as $a):
                                    $selected = $is_first ? 'selected' : '';
                                    $is_first = false;
                                    ?>
                                    <option value="<?php echo esc_attr($a->id); ?>" <?php echo $selected; ?>>
                                        <?php echo esc_html($a->account_code ? $a->account_code . ' - ' : ''); ?>
                                        <?php echo esc_html($a->account_selection_name ?: $a->account_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Payment Note</label>
                            <input type="text" name="payment_note" id="payment_note"
                                class="w-full rounded border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500 h-[38px]"
                                placeholder="Note...">
                        </div>
                    </div>
                </div>

                <button type="button" id="save-purchase"
                    class="w-full py-3 text-white rounded-lg transition-all font-bold text-lg shadow-md flex justify-center items-center hover:opacity-90"
                    style="background-color: #39B54A;">
                    <i class="fa-solid fa-check-circle mr-2"></i> Save Purchase
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Template for Row (Hidden) -->
<template id="row-template">
    <tr class="hover:bg-gray-50 transition-colors" data-item-id="">
        <td class="p-3 border-b">
            <input type="hidden" name="items[][item_id]" class="row-item-id">
            <input type="text" name="items[][item_name]"
                class="row-item-name w-full rounded border-gray-300 text-sm py-1 px-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                placeholder="Type item name..." required>
            <span class="row-code text-[10px] text-gray-400 font-mono block mt-1 hidden">-</span>
        </td>
        <td class="p-3 border-b text-center">
            <span
                class="row-stock inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">0</span>
        </td>
        <td class="p-3 border-b">
            <select name="items[][account_id]"
                class="row-account-id w-full rounded border-gray-300 text-xs focus:ring-blue-500 focus:border-blue-500 bg-transparent">
                <option value="">- Account -</option>
                <?php foreach ($coa_accounts as $ca): ?>
                    <option value="<?php echo esc_attr($ca->id); ?>">
                        <?php echo esc_html($ca->account_code . ' - ' . $ca->account_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="p-3 border-b">
            <div class="flex items-center shadow-sm rounded border border-gray-300 overflow-hidden w-28">
                <button type="button"
                    class="qty-dec px-2 py-1 bg-gray-50 hover:bg-gray-100 border-r border-gray-300 text-gray-600 transition-colors">-</button>
                <input type="number" min="0.01" step="0.01" name="items[][qty]"
                    class="row-qty w-full border-0 text-center text-sm focus:ring-0 p-1" value="1">
                <button type="button"
                    class="qty-inc px-2 py-1 bg-gray-50 hover:bg-gray-100 border-l border-gray-300 text-gray-600 transition-colors">+</button>
            </div>
        </td>
        <td class="p-3 border-b">
            <input type="number" step="0.01" name="items[][unit_cost]"
                class="row-price w-full rounded border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500 py-1"
                value="0.00">
        </td>
        <td class="p-3 border-b">
            <input type="number" step="0.01" name="items[][discount]"
                class="row-discount w-full rounded border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500 py-1"
                value="0.00">
        </td>
        <td class="p-3 border-b">
            <input type="number" step="0.01" name="items[][tax_percent]"
                class="row-tax w-full rounded border-gray-300 bg-gray-50 text-sm focus:ring-0 py-1" readonly
                value="0.00">
        </td>
        <td class="p-3 border-b">
            <input type="text" name="items[][tax_amt]"
                class="row-tax-amt w-full bg-transparent border-none text-right text-sm text-gray-500 font-medium p-0"
                readonly value="0.00">
        </td>
        <td class="p-3 border-b">
            <input type="text" name="items[][total]"
                class="row-total w-full bg-transparent border-none text-right text-sm text-gray-900 font-bold p-0"
                readonly value="0.00">
        </td>
        <td class="p-3 border-b text-center">
            <button type="button" class="remove-row text-red-300 hover:text-red-600 transition-colors p-2">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </td>
    </tr>
</template>

<script>
    jQuery(document).ready(function ($) {
        const container = $('#obn-view-add-purchase');
        const ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
        const nonce = '<?php echo esc_js($nonce); ?>';

        function fmt(v) { return parseFloat(v || 0).toFixed(2); }
        function toFloat(v) { return Number((v || 0).toString().replace(/,/g, '')) || 0; }
        function getAjaxErrorMessage(xhr, fallback) {
            const res = xhr && xhr.responseJSON ? xhr.responseJSON : {};
            if (res.data && res.data.message) return res.data.message;
            if (typeof res.data === 'string') return res.data;
            if (res.message) return res.message;
            return fallback || 'Request failed.';
        }

        // Init Select2 with local parent to fix alignment
        // if (container.find('#acc_warehouse_id').hasClass("select2-hidden-accessible")) {
        //     container.find('#acc_warehouse_id, #payment_type_id, #account_id').select2('destroy');
        // }
        // container.find('#acc_warehouse_id, #payment_type_id, #account_id').select2({
        //     width: '100%',
        //     dropdownParent: container.find('#purchase-form')
        // });

        // Supplier autocomplete setup
        const suppliers = <?php echo json_encode($suppliers); ?>;
        container.find('#supplier_name').autocomplete({
            source: function (request, response) {
                const term = request.term.toLowerCase();
                const matches = suppliers.filter(function (s) {
                    return s.supplier_name.toLowerCase().indexOf(term) !== -1;
                }).map(function (s) {
                    return { label: s.supplier_name, value: s.supplier_name, id: s.id };
                });
                response(matches);
            },
            minLength: 1,
            select: function (evt, ui) {
                container.find('#supplier_id').val(ui.item.id);
            },
            change: function (evt, ui) {
                if (!ui.item) {
                    const typedVal = $(this).val().trim();
                    const matched = suppliers.find(function (s) {
                        return s.supplier_name.toLowerCase() === typedVal.toLowerCase();
                    });
                    if (matched) {
                        container.find('#supplier_id').val(matched.id);
                    } else {
                        container.find('#supplier_id').val('');
                    }
                }
            }
        });

        // Generate Code
        function generateCode() {
            $.post(ajaxurl, { action: 'generate_purchase_code', security: nonce }, function (res) {
                if (res.success) container.find('#purchase_code').val(res.data.code);
            }, 'json');
        }
        generateCode();

        // Add New Row Button
        container.find('#add-new-row').off('click').on('click', function () {
            addItemRow(null);
        });

        // Add Row
        function addItemRow(item) {
            container.find('#empty-row-msg').hide();
            const tpl = document.getElementById('row-template');
            const clone = tpl.content.cloneNode(true);
            const row = $(clone.children).filter('tr').first();
            if (!row.length) return;

            // Set default account even for blank rows
            const invAcc = row.find('.row-account-id option').filter(function () {
                const text = $(this).text().toLowerCase();
                return text === 'inventory' || text.endsWith(' - inventory') || text.indexOf('140') !== -1;
            }).val();
            if (invAcc) row.find('.row-account-id').val(invAcc);

            if (item) {
                populateRow(row, item);
            }

            container.find('#items-tbody').append(row);

            // Init Select2 for account in row
            // row.find('.row-account-id').select2({
            //     width: '100%',
            //     dropdownParent: container.find('#purchase-form')
            // });

            // Init row autocomplete
            row.find('.row-item-name').autocomplete({
                source: function (request, response) {
                    $.post(ajaxurl, { action: 'search_purchase_items', term: request.term, security: nonce }, function (res) {
                        if (res.success) {
                            response(res.data.map(function (i) { return { label: i.item_name + ' (' + (i.item_code || i.sku || '') + ') - Stock: ' + i.stock, value: i.item_name, data: i }; }));
                        } else response([]);
                    }, 'json');
                },
                minLength: 1,
                select: function (evt, ui) {
                    populateRow(row, ui.item.data);
                    return false;
                },
                change: function (evt, ui) {
                    if (!ui.item) {
                        const val = $(this).val().trim();
                        if (row.data('selected-name') !== val) {
                            row.find('.row-item-id').val('');
                            row.find('.row-code').addClass('hidden').text('-');
                            row.find('.row-stock').text('0');
                        }
                    }
                }
            });

            // Handle typing / clearing name
            row.find('.row-item-name').on('input', function () {
                const val = $(this).val().trim();
                if (row.data('selected-name') !== val) {
                    row.find('.row-item-id').val('');
                    row.find('.row-code').addClass('hidden').text('-');
                    row.find('.row-stock').text('0');
                }
            });

            recalc();

            // Bind events
            row.find('.qty-inc').on('click', function () {
                const input = $(this).prev('input');
                input.val(parseFloat(input.val() || 0) + 1).trigger('input');
            });
            row.find('.qty-dec').on('click', function () {
                const input = $(this).next('input');
                const v = parseFloat(input.val() || 0);
                if (v > 1) input.val(v - 1).trigger('input');
            });
            row.find('input').on('input', recalc);
            row.find('.remove-row').on('click', function () {
                $(this).closest('tr').remove();
                if (container.find('#items-tbody tr:not(#empty-row-msg)').length === 0) container.find('#empty-row-msg').show();
                recalc();
            });
        }

        function populateRow(row, item) {
            row.attr('data-item-id', item.id);
            row.find('.row-item-id').val(item.id);
            row.find('.row-item-name').val(item.item_name);
            row.data('selected-name', item.item_name);
            row.find('.row-code').text(item.item_code || item.sku || '').removeClass('hidden');
            row.find('.row-stock').text(item.stock || '0');
            row.find('.row-price').val(item.purchase_price || item.cost || 0);
            row.find('.row-tax').val(item.tax_percent || 0);

            // Handle Default Account
            if (item.purchase_account_id) {
                row.find('.row-account-id').val(item.purchase_account_id).trigger('change');
            } else {
                const invAcc = row.find('.row-account-id option').filter(function () {
                    const text = $(this).text().toLowerCase();
                    return text === 'inventory' || text.endsWith(' - inventory') || text.indexOf('140') !== -1;
                }).val();
                if (invAcc) row.find('.row-account-id').val(invAcc).trigger('change');
            }

            recalc();
        }

        // Calculation
        function recalc() {
            let subtotal = 0, tax_total = 0;

            container.find('#items-tbody tr:not(#empty-row-msg)').each(function () {
                const qty = toFloat($(this).find('.row-qty').val());
                const price = toFloat($(this).find('.row-price').val());
                const discount = toFloat($(this).find('.row-discount').val());
                const tax_pct = toFloat($(this).find('.row-tax').val());

                const line_before = qty * price;
                const line_discount = discount;
                const taxable = Math.max(0, line_before - line_discount);
                const tax_amt = (taxable * tax_pct) / 100;
                const line_total = taxable + tax_amt;

                $(this).find('.row-tax-amt').val(fmt(tax_amt));
                $(this).find('.row-total').val(fmt(line_total));

                subtotal += taxable;
                tax_total += tax_amt;
            });

            // Other charges
            const other_charges = toFloat(container.find('#other_charges_input').val());
            let other_tax_amt = 0;
            const other_tax_sel = container.find('#other_charges_tax_id option:selected');
            if (other_tax_sel.val()) {
                const pct = toFloat(other_tax_sel.data('percent'));
                other_tax_amt = (other_charges * pct) / 100;
            }

            // Discount All
            const discount_val = toFloat(container.find('#discount_to_all_input').val());
            const discount_type = container.find('#discount_to_all_type').val();
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

            container.find('#subtotal_text').text(fmt(subtotal));
            container.find('#other_charges_text').text(fmt(other_charges + other_tax_amt));
            container.find('#discount_all_text').text(fmt(discount_all_amt));
            container.find('#tax_total_text').text(fmt(tax_total_sum));
            container.find('#round_off_text').text(fmt(round_off));
            container.find('#grand_total_text').text(fmt(grand_total));
        }

        container.find('#other_charges_input, #other_charges_tax_id, #discount_to_all_input, #discount_to_all_type').off('input change').on('input change', recalc);

        // Save
        container.find('#save-purchase').off('click').on('click', function () {
            const btn = $(this);
            if (!container.find('#acc_warehouse_id').val()) { alert('Select warehouse'); return; }
            if (!container.find('#supplier_name').val().trim()) { alert('Select or type a supplier name'); return; }
            if (container.find('#items-tbody tr:not(#empty-row-msg)').length === 0) { alert('Add at least one item'); return; }

            const items = [];
            container.find('#items-tbody tr:not(#empty-row-msg)').each(function () {
                const item_id = $(this).find('.row-item-id').val();
                items.push({
                    item_id: item_id,
                    name: $(this).find('.row-item-name').val().trim(),
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
                action: 'insert_purchase',
                security: nonce,
                store_id: container.find('input[name="store_id"]').val(),
                warehouse_id: container.find('#acc_warehouse_id').val(),
                purchase_code: container.find('#purchase_code').val(),
                reference_no: container.find('#reference_no').val(),
                purchase_date: container.find('#purchase_date').val(),
                purchase_status: container.find('#purchase_status').val(),
                supplier_id: container.find('#supplier_id').val(),
                supplier_name: container.find('#supplier_name').val().trim(),
                other_charges_input: toFloat(container.find('#other_charges_input').val()),
                other_charges_tax_id: container.find('#other_charges_tax_id').val(),
                discount_to_all_input: toFloat(container.find('#discount_to_all_input').val()),
                discount_to_all_type: container.find('#discount_to_all_type').val(),
                subtotal: toFloat(container.find('#subtotal_text').text()),
                round_off: toFloat(container.find('#round_off_text').text()),
                grand_total: toFloat(container.find('#grand_total_text').text()),
                purchase_note: container.find('#purchase_note').val(),
                payment_type_id: container.find('#payment_type_id').val(),
                payment_amount: toFloat(container.find('#payment_amount').val()),
                payment_note: container.find('#payment_note').val(),
                account_id: container.find('#account_id').val(),
                items_json: JSON.stringify(items)
            };

            btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Saving...');
            $.post(ajaxurl, payload, function (res) {
                if (res.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Purchase record has been saved.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        const purchaseId = res.data.purchase_id;
                        window.location.href = '?view=purchase-invoice&id=' + purchaseId;
                    });
                } else {
                    Swal.fire('Error', (res.data && res.data.message) || res.data || 'Failed to save', 'error');
                }
            }, 'json').fail(function (xhr) {
                Swal.fire('Error', getAjaxErrorMessage(xhr, 'Failed to save purchase.'), 'error');
            }).always(function () {
                btn.prop('disabled', false).html('<i class="fa-solid fa-check-circle mr-2"></i> Save Purchase');
            });
        });

        // Open Payment Type Modal
        container.find('#purchase-add-payment-type-btn').on('click', function () {
            if (typeof window.openPaymentTypeModal === 'function') {
                window.openPaymentTypeModal();
            }
        });

        // Callback for New Item Modal
        window.onItemAdded = function (item) {
            addItemRow({
                id: item.item_id,
                item_name: item.item_name,
                item_code: item.item_code,
                sku: item.sku,
                stock: 0,
                purchase_price: item.price,
                tax_percent: item.tax_percent
            });
        };
    });
</script>

<?php include FRONTEND_ACCOUNTING_TEMPLATE_PATH . 'modals/modal_supplier.php'; ?>
<?php include FRONTEND_ACCOUNTING_TEMPLATE_PATH . 'modals/modal_paymenttype.php'; ?>
<?php //include FRONTEND_ACCOUNTING_TEMPLATE_PATH . 'modals/modal_item.php'; ?>