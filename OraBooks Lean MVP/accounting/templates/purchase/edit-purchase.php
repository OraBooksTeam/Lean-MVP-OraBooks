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
$purchase_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$purchase_id) {
    echo '<div class="p-6 bg-red-100 text-red-700 rounded-lg">Invalid Purchase ID</div>';
    return;
}
?>

<style>
    .ui-autocomplete {
        z-index: 9999 !important;
        max-height: 300px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }

    .ui-menu-item {
        padding: 8px 12px;
        cursor: pointer;
        font-size: 14px;
    }

    .ui-state-active {
        background: #eff6ff !important;
        border: none !important;
        color: #2563eb !important;
    }
</style>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 relative min-h-[400px]">
    <!-- Loader -->
    <div id="page-loader" class="absolute inset-0 bg-white bg-opacity-90 z-50 flex items-center justify-center rounded-xl">
        <div class="text-center">
            <div class="w-16 h-16 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
            <p class="text-gray-500 font-bold">Fetching Purchase Data...</p>
        </div>
    </div>

    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
        <div class="flex items-center">
            <div
                class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center mr-4 shadow-sm font-bold">
                <i class="fa-solid fa-pen-to-square text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Edit Purchase</h1>
                <p class="text-sm text-gray-500 mt-1">Review and update purchase details</p>
            </div>
        </div>
        <a href="?view=view-purchase"
            class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-xl hover:from-indigo-700 hover:to-violet-700 transition-all font-bold text-xs uppercase tracking-widest shadow-lg shadow-indigo-200 hover:shadow-indigo-300 active:scale-95">
            <i class="fa-solid fa-arrow-left-long mr-2"></i> Back to List
        </a>
    </div>

    <form id="purchase-form" class="space-y-6 relative">
        <input type="hidden" name="action" value="update_purchase">
        <input type="hidden" name="security" value="<?php echo esc_attr($nonce); ?>">
        <input type="hidden" name="purchase_id" value="<?php echo esc_attr($purchase_id); ?>">

        <!-- Top Section: Details -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-6 flex items-center">
                <i class="fa-solid fa-circle-info mr-2 text-indigo-500"></i> Basic Information
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Purchase Code</label>
                    <input type="text" name="purchase_code" id="purchase_code" readonly
                        class="w-full rounded-lg border-gray-200 bg-gray-100 text-gray-500 cursor-not-allowed py-2.5 px-3 border shadow-sm font-bold text-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Warehouse <span
                            class="text-red-500">*</span></label>
                    <select name="warehouse_id" id="warehouse_id" required
                        class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm py-2 px-3 border bg-white">
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?php echo esc_attr($w->id); ?>"><?php echo esc_html($w->warehouse_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Supplier <span
                            class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <select name="supplier_id" id="supplier_id" required
                            class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm py-2 px-3 border bg-white">
                            <option value="">- Select Supplier -</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?php echo esc_attr($s->id); ?>"><?php echo esc_html($s->supplier_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button"
                            class="px-1.5 py-0.5 md:px-3 md:py-2 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 border border-indigo-200 transition-colors text-[10px] md:text-base flex-shrink-0"
                            title="Add New Supplier" onclick="openSupplierModal()">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Purchase Date</label>
                    <input type="date" name="purchase_date" id="purchase_date"
                        class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm py-2 px-3 border">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Reference No.</label>
                    <input type="text" name="reference_no" id="reference_no"
                        class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm py-2 px-3 border"
                        placeholder="Optional">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Purchase Status</label>
                    <select name="purchase_status" id="purchase_status"
                        class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm py-2 px-3 border">
                        <option value="Received">Received</option>
                        <option value="Pending">Pending</option>
                        <option value="Ordered">Ordered</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Middle Section: Items -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
            <div
                class="p-4 bg-gray-50 border-b border-gray-200 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex-1 flex items-center gap-4 w-full">
                    <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest whitespace-nowrap">
                        <i class="fa-solid fa-box mr-2 text-indigo-500"></i> Purchased Items
                    </h2>
                    <div class="w-full max-w-xl relative flex gap-2">
                        <div class="relative flex-1">
                            <input type="text" id="item-autocomplete"
                                class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-400 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                placeholder="Add more items by name or code...">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-plus text-gray-400 text-xs"></i>
                            </div>
                        </div>
                        <button type="button" onclick="openItemModal()"
                            class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 border border-indigo-200 transition-colors flex items-center justify-center flex-shrink-0"
                            title="Add New Item">
                            <i class="fa-solid fa-plus-circle text-lg"></i>
                        </button>
                    </div>
                </div>
                <button type="button" id="add-new-row"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-bold text-xs uppercase tracking-tighter shadow-sm flex items-center">
                    <i class="fa-solid fa-plus mr-2"></i> Add Blank Row
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="items-table">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr class="bg-indigo-600 text-white text-sm uppercase tracking-wider">
                            <th class="p-3 font-semibold border-b w-1/4 text-center">Item Name</th>
                            <th class="p-3 font-semibold border-b w-24 text-center">Stock</th>
                            <th class="p-3 font-semibold border-b w-40">Account</th>
                            <th class="p-3 font-semibold border-b w-32">Qty</th>
                            <th class="p-3 font-semibold border-b w-32">Unit Cost</th>
                            <th class="p-3 font-semibold border-b w-16">Discount</th>
                            <th class="p-3 font-semibold border-b w-16">Tax %</th>
                            <th class="p-3 font-semibold border-b w-24 text-right">Tax Amt</th>
                            <th class="p-3 font-semibold border-b w-32 text-right">Total</th>
                            <th class="p-3 font-semibold border-b w-16 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="items-tbody" class="divide-y divide-gray-50">
                        <!-- rows dynamic -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bottom Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
            <!-- Left: Notes -->
            <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-6">Additional Info</h2>
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Other Charges</label>
                            <div class="flex rounded-lg shadow-sm overflow-hidden border border-gray-300">
                                <input type="number" step="0.01" name="other_charges_input" id="other_charges_input"
                                    value="0"
                                    class="w-1/2 border-0 focus:ring-0 focus:border-0 rounded-l-lg border-r border-gray-200 py-2.5 px-3">
                                <select name="other_charges_tax_id" id="other_charges_tax_id"
                                    class="w-1/2 border-0 focus:ring-0 focus:border-0 bg-gray-50 text-xs font-bold py-2.5">
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
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Discount to All</label>
                            <div class="flex rounded-lg shadow-sm overflow-hidden border border-gray-300">
                                <input type="number" step="0.01" id="discount_to_all_input" name="discount_to_all_input"
                                    value="0"
                                    class="w-1/2 border-0 focus:ring-0 focus:border-0 rounded-l-lg border-r border-gray-200 py-2.5 px-3 font-bold text-rose-600">
                                <select id="discount_to_all_type" name="discount_to_all_type"
                                    class="w-1/2 border-0 focus:ring-0 focus:border-0 bg-gray-50 text-xs font-bold py-2.5">
                                    <option value="Percentage">Percentage %</option>
                                    <option value="Fixed">Fixed Amount</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Purchase Note</label>
                        <textarea name="purchase_note" id="purchase_note" rows="4"
                            class="w-full rounded-xl border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm px-4 py-3"
                            placeholder="Click to add note..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Right: Calculation Card -->
            <div
                class="bg-white rounded-3xl p-8 text-gray-900 border border-gray-200 shadow-sm relative overflow-hidden group">
                <div
                    class="absolute -top-24 -right-24 w-64 h-64 bg-indigo-500 opacity-5 rounded-full blur-3xl group-hover:opacity-10 transition-opacity">
                </div>
                <h2
                    class="text-sm font-bold uppercase tracking-[0.2em] mb-8 text-indigo-600 opacity-80 border-b border-gray-100 pb-4">
                    Order Summary</h2>

                <div class="space-y-4 mb-8">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-500 font-medium">Items Subtotal</span>
                        <span class="font-bold text-gray-900" id="subtotal_text">0.00</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-500 font-medium">Other Charges</span>
                        <span class="font-bold text-indigo-600" id="other_charges_text">0.00</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="font-medium text-rose-500">Total Discount</span>
                        <span class="font-black text-rose-600">- <span id="discount_all_text">0.00</span></span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-500 font-medium">Tax Collected</span>
                        <span class="font-bold text-emerald-600" id="tax_total_text">0.00</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-400 font-medium text-xs">Round Off Adjustment</span>
                        <span class="font-medium text-gray-500 text-xs" id="round_off_text">0.00</span>
                    </div>
                    <div class="pt-6 border-t border-gray-100 flex justify-between items-end">
                        <div>
                            <span
                                class="block text-[10px] font-black uppercase tracking-[0.3em] text-indigo-600 mb-1">Grand
                                Total</span>
                            <span class="text-xs text-gray-500 font-medium italic">*Calculated automatically</span>
                        </div>
                        <span class="text-4xl font-black text-indigo-600" id="grand_total_text">0.00</span>
                    </div>
                    <div class="pt-2 flex justify-between items-center text-sm">
                        <span class="text-gray-500 font-medium">Balance Due</span>
                        <span class="font-black text-rose-600" id="balance_due_text">0.00</span>
                    </div>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-2xl p-6 mb-8">
                    <h3
                        class="text-[10px] font-black uppercase tracking-[0.2em] text-indigo-600 mb-4 flex items-center">
                        <i class="fa-solid fa-wallet mr-2"></i> Payment Details
                    </h3>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase mb-2">Payment
                                Type</label>
                            <select id="payment_type_id" name="payment_type_id"
                                class="w-full rounded-lg border-gray-200 bg-white text-gray-900 text-xs py-2">
                                <option value="">- Select -</option>
                                <?php foreach ($payment_types as $pt): ?>
                                    <option value="<?php echo esc_attr($pt->id); ?>">
                                        <?php echo esc_html($pt->payment_type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase mb-2">Amount Paid</label>
                            <input type="number" step="0.01" name="payment_amount" id="payment_amount"
                                class="w-full rounded-lg border-indigo-200 bg-white text-emerald-600 font-bold text-sm py-2 px-3 focus:ring-0 focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase mb-2">Account</label>
                            <select id="account_id" name="account_id"
                                class="w-full rounded-lg border-gray-200 bg-white text-gray-900 text-xs py-2">
                                <option value="">- Select -</option>
                                <?php foreach ($accounts as $a): ?>
                                    <option value="<?php echo esc_attr($a->id); ?>">
                                        <?php echo esc_html($a->account_code ? $a->account_code . ' - ' : ''); ?>
                                        <?php echo esc_html($a->account_selection_name ?: $a->account_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase mb-2">Payment
                                Note</label>
                            <input type="text" name="payment_note" id="payment_note"
                                class="w-full rounded-lg border-gray-200 bg-white text-gray-900 text-xs py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="Reference...">
                        </div>
                    </div>
                </div>

                <div
                    class="bg-amber-500/10 border border-amber-500/20 rounded-xl p-4 mb-6 text-xs text-amber-700 flex items-start">
                    <i class="fa-solid fa-triangle-exclamation mr-3 mt-1 opacity-70"></i>
                    <p class="leading-relaxed">Stock revisions and purchase pricing updates will be processed
                        automatically upon saving changes to this entry.</p>
                </div>

                <button type="button" id="update-purchase"
                    class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl transition-all font-black text-sm uppercase tracking-[0.2em] shadow-lg hover:shadow-indigo-500/30 active:scale-[0.98] flex justify-center items-center">
                    <i class="fa-solid fa-cloud-arrow-up mr-3"></i> Apply Changes
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Template for Row -->
<template id="row-template">
    <tr class="hover:bg-gray-50 transition-colors" data-item-id="">
        <td class="p-3 border-b">
            <input type="hidden" class="row-item-id">
            <div class="flex items-center group">
                <div class="flex flex-col">
                    <span class="row-name font-medium text-gray-900 block truncate max-w-[200px] hidden">-</span>
                    <span class="row-code text-[10px] text-gray-400 font-mono hidden">-</span>
                </div>
                <input type="text"
                    class="row-item-search w-full bg-transparent border-none focus:ring-0 text-sm p-0 placeholder-gray-300"
                    placeholder="Search item...">
                <button type="button"
                    class="change-item hidden ml-2 text-gray-400 hover:text-blue-500 transition-colors">
                    <i class="fa-solid fa-rotate text-xs"></i>
                </button>
            </div>
        </td>
        <td class="p-3 border-b text-center">
            <span
                class="row-stock inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">0</span>
        </td>
        <td class="p-3 border-b">
            <select
                class="row-account-id w-full rounded border-gray-300 text-xs focus:ring-indigo-500 focus:border-indigo-500 bg-transparent">
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
                <input type="number" min="0.01" step="0.01"
                    class="row-qty w-full border-0 text-center text-sm focus:ring-0 p-1" value="1">
                <button type="button"
                    class="qty-inc px-2 py-1 bg-gray-50 hover:bg-gray-100 border-l border-gray-300 text-gray-600 transition-colors">+</button>
            </div>
        </td>
        <td class="p-3 border-b">
            <input type="number" step="0.01"
                class="row-price w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500 py-1"
                value="0.00">
        </td>
        <td class="p-3 border-b">
            <input type="number" step="0.01"
                class="row-discount w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500 py-1"
                value="0.00">
        </td>
        <td class="p-3 border-b">
            <input type="number" step="0.01"
                class="row-tax w-full rounded border-gray-300 bg-gray-50 text-sm focus:ring-0 py-1" readonly
                value="0.00">
        </td>
        <td class="p-3 border-b">
            <input type="text"
                class="row-tax-amt w-full bg-transparent border-none text-right text-sm text-gray-500 font-medium p-0"
                readonly value="0.00">
        </td>
        <td class="p-3 border-b">
            <input type="text"
                class="row-total w-full bg-transparent border-none text-right text-sm text-gray-900 font-bold p-0"
                readonly value="0.00">
        </td>
        <td class="p-3 border-b text-center">
            <button type="button"
                class="remove-row text-red-300 hover:text-red-600 transition-colors p-2 underline decoration-dotted underline-offset-4 font-bold text-xs uppercase"
                title="Remove Row">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </td>
    </tr>
</template>

<script>
    jQuery(document).ready(function ($) {
        const container = $('#obn-view-edit-purchase').length ? $('#obn-view-edit-purchase') : $(document);
        let ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
        if (window.location.protocol === 'https:') {
            ajaxurl = ajaxurl.replace(/^http:/, 'https:');
        }
        const nonce = '<?php echo esc_js($nonce); ?>';
        const purchase_id = <?php echo intval($purchase_id); ?>;

        function fmt(v) { return parseFloat(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        function toFloat(v) { return Number((v || 0).toString().replace(/,/g, '')) || 0; }
        function getAjaxErrorMessage(xhr, fallback) {
            const res = xhr && xhr.responseJSON ? xhr.responseJSON : {};
            if (res.data && res.data.message) return res.data.message;
            if (typeof res.data === 'string') return res.data;
            if (res.message) return res.message;
            return fallback || 'Request failed.';
        }

        // Init Select2 with local parent to fix alignment
        if (container.find('#warehouse_id').hasClass("select2-hidden-accessible")) {
            container.find('#warehouse_id, #supplier_id, #payment_type_id, #account_id').select2('destroy');
        }
        container.find('#warehouse_id, #supplier_id, #payment_type_id, #account_id').select2({
            width: '100%',
            dropdownParent: container.find('#purchase-form')
        });

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

        // Load details via AJAX
        container.find('#page-loader').show();
        $.post(ajaxurl, {
                action: 'get_purchase_details',
                purchase_id: purchase_id,
                security: nonce
            }, function (res) {
            if (res.success) {
                const p = res.data.purchase;
                const items = res.data.items;
                const payments = res.data.payments;

                container.find('#purchase_code').val(p.purchase_code);
                container.find('#warehouse_id').val(p.warehouse_id).trigger('change');
                container.find('#supplier_id').val(p.supplier_id).trigger('change');
                container.find('#reference_no').val(p.reference_no);
                container.find('#purchase_date').val(p.purchase_date);
                container.find('#purchase_status').val(p.purchase_status);

                container.find('#other_charges_input').val(p.other_charges_input);
                container.find('#other_charges_tax_id').val(p.other_charges_tax_id);
                container.find('#discount_to_all_input').val(p.discount_to_all_input);
                container.find('#discount_to_all_type').val(p.discount_to_all_type);
                container.find('#purchase_note').val(p.purchase_note);

                if (items && items.length > 0) {
                    items.forEach(item => { populateRowFromData(item); });
                }

                if (payments && payments.length > 0) {
                    const pay = payments[0]; // Populating the first payment as default for edit
                    container.find('#payment_amount').val(pay.payment);
                    container.find('#payment_type_id').val(pay.payment_type).trigger('change');
                    container.find('#account_id').val(pay.account_id).trigger('change');
                    container.find('#payment_note').val(pay.payment_note);
                }

                recalc();
                container.find('#page-loader').fadeOut(500);
            } else {
                container.find('#page-loader').html('<div class="text-center p-8 bg-white rounded-xl shadow-lg border border-red-100"><i class="fa-solid fa-circle-exclamation text-rose-500 text-4xl mb-4"></i><p class="text-gray-800 font-bold">' + (res.data || 'Failed to load details') + '</p><a href="?view=view-purchase" class="mt-4 inline-block text-indigo-600 font-bold border-b-2 border-indigo-600">Back to List</a></div>');
            }
        }, 'json').fail(function (xhr, status, error) {
            console.error('OraBooks edit purchase load error:', status, error, xhr.responseText);
            container.find('#page-loader').html('<div class="text-center p-8 bg-white rounded-xl shadow-lg border border-red-100"><i class="fa-solid fa-circle-exclamation text-rose-500 text-4xl mb-4"></i><p class="text-gray-800 font-bold">Failed to load purchase data.</p><p class="text-xs text-gray-500 mt-2">' + (error || status) + '</p><a href="?view=view-purchase" class="mt-4 inline-block text-indigo-600 font-bold border-b-2 border-indigo-600">Back to List</a></div>');
        });

        function populateRowFromData(item) {
            const tpl = container.find('#row-template').get(0);
            const clone = tpl.content.cloneNode(true);
            const row = $(clone.children).filter('tr').first();
            if (!row.length) return;

            populateRow(row, {
                id: item.item_id,
                item_name: item.item_name || item.description,
                item_code: item.item_code,
                sku: item.sku,
                stock: item.stock,
                purchase_price: item.price_per_unit,
                account_id: item.account_id
            });

            row.find('.row-qty').val(item.purchase_qty);
            row.find('.row-discount').val(item.discount_input);

            // Deduction of tax percent
            let tax_pct = 0;
            const line_val = toFloat(item.price_per_unit) * toFloat(item.purchase_qty);
            const taxable = line_val - toFloat(item.discount_input);
            if (taxable > 0 && toFloat(item.tax_amt) > 0) {
                tax_pct = (toFloat(item.tax_amt) / taxable) * 100;
            }
            row.find('.row-tax').val(tax_pct.toFixed(2));

            container.find('#items-tbody').append(row);

            // Init Select2 for account in row
            row.find('.row-account-id').select2({
                width: '100%',
                dropdownParent: container.find('#purchase-form')
            });

            bindRowEvents(row);
        }

        // Add Row Button
        container.find('#add-new-row').off('click').on('click', function () {
            addNewItemRow(null);
        });

        function populateRow(row, item) {
            row.attr('data-item-id', item.id);
            row.find('.row-item-id').val(item.id);
            row.find('.row-name').text(item.item_name).removeClass('hidden');
            row.find('.row-code').text(item.item_code || item.sku || '').removeClass('hidden');
            row.find('.change-item').removeClass('hidden');
            row.find('.row-item-search').addClass('hidden');
            row.find('.row-stock').text(item.stock || '0');
            row.find('.row-price').val(item.purchase_price || item.cost || item.price || 0);
            row.find('.row-tax').val(item.tax_percent || 0);

            // Handle Default Account
            if (item.purchase_account_id) {
                row.find('.row-account-id').val(item.purchase_account_id);
            } else if (item.account_id) {
                row.find('.row-account-id').val(item.account_id);
            } else {
                const invAcc = row.find('.row-account-id option').filter(function () {
                    const text = $(this).text().toLowerCase();
                    return text === 'inventory' || text.endsWith(' - inventory') || text.indexOf('140') !== -1;
                }).val();
                if (invAcc) row.find('.row-account-id').val(invAcc);
            }
        }

        function addNewItemRow(item) {
            const tpl = container.find('#row-template').get(0);
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
            row.find('.row-account-id').select2({
                width: '100%',
                dropdownParent: container.find('#purchase-form')
            });

            bindRowEvents(row);
            recalc();
        }

        function bindRowEvents(row) {
            // Init row autocomplete
            row.find('.row-item-search').autocomplete({
                source: function (request, response) {
                    $.post(ajaxurl, { action: 'search_purchase_items', term: request.term, security: nonce }, function (res) {
                        if (res.success) {
                            response(res.data.map(function (i) { return { label: i.item_name + ' (' + (i.item_code || i.sku || '') + ')', value: i.item_name, data: i }; }));
                        } else response([]);
                    }, 'json');
                },
                minLength: 1,
                select: function (evt, ui) {
                    const data = ui.item.data;
                    populateRow(row, {
                        id: data.id,
                        item_name: data.item_name,
                        item_code: data.item_code,
                        sku: data.sku,
                        stock: data.stock,
                        purchase_price: data.purchase_price || data.cost,
                        tax_percent: data.tax_percent,
                        purchase_account_id: data.purchase_account_id
                    });
                    return false;
                }
            });

            row.find('.change-item').on('click', function () {
                $(this).addClass('hidden');
                row.find('.row-name, .row-code').addClass('hidden');
                row.find('.row-item-search').removeClass('hidden').val('').focus();
            });

            row.find('.qty-inc').on('click', function () {
                const input = $(this).closest('div').find('.row-qty');
                input.val(parseFloat(input.val() || 0) + 1).trigger('input');
            });
            row.find('.qty-dec').on('click', function () {
                const input = $(this).closest('div').find('.row-qty');
                const v = parseFloat(input.val() || 0);
                if (v > 1) input.val(v - 1).trigger('input');
            });

            row.find('input, select').on('input change', recalc);
            row.find('.remove-row').on('click', function () {
                $(this).closest('tr').fadeOut(200, function () { $(this).remove(); recalc(); });
            });
        }

        // Global Autocomplete
        container.find('#item-autocomplete').autocomplete({
            source: function (request, response) {
                $.post(ajaxurl, { action: 'search_purchase_items', term: request.term, security: nonce }, function (res) {
                    if (res.success) {
                        response(res.data.map(function (i) { return { label: i.item_name + ' (' + (i.item_code || i.sku || '') + ') - Stock: ' + i.stock, value: i.item_name, data: i }; }));
                    } else response([]);
                }, 'json');
            },
            minLength: 1,
            select: function (evt, ui) {
                addNewItemRow({
                    id: ui.item.data.id,
                    item_name: ui.item.data.item_name,
                    item_code: ui.item.data.item_code,
                    sku: ui.item.data.sku,
                    stock: ui.item.data.stock,
                    purchase_price: ui.item.data.purchase_price || ui.item.data.cost,
                    tax_percent: ui.item.data.tax_percent,
                    purchase_account_id: ui.item.data.purchase_account_id
                });
                return false;
            }
        });

        // Calculation
        function recalc() {
            let subtotal = 0, tax_total = 0;

            container.find('#items-tbody tr').each(function () {
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

        // Update
        container.find('#update-purchase').off('click').on('click', function () {
            const btn = $(this);
            if (!container.find('#warehouse_id').val()) { alert('Select warehouse'); return; }
            if (!container.find('#supplier_id').val()) { alert('Select supplier'); return; }
            if (container.find('#items-tbody tr').length === 0) { alert('Add at least one item'); return; }

            const items = [];
            container.find('#items-tbody tr').each(function () {
                const item_id = $(this).find('.row-item-id').val();
                const is_search_visible = !$(this).find('.row-item-search').hasClass('hidden');
                const name = is_search_visible ? $(this).find('.row-item-search').val().trim() : $(this).find('.row-name').text().trim();
                
                if (item_id || name) {
                    items.push({
                        item_id: item_id,
                        name: name,
                        account_id: $(this).find('.row-account-id').val(),
                        qty: toFloat($(this).find('.row-qty').val()),
                        unit_price: toFloat($(this).find('.row-price').val()),
                        discount: toFloat($(this).find('.row-discount').val()),
                        tax_percent: toFloat($(this).find('.row-tax').val()),
                        tax_amt: toFloat($(this).find('.row-tax-amt').val()),
                        total: toFloat($(this).find('.row-total').val())
                    });
                }
            });

            const payload = {
                action: 'update_purchase',
                security: nonce,
                purchase_id: purchase_id,
                warehouse_id: container.find('#warehouse_id').val(),
                purchase_code: container.find('#purchase_code').val(),
                reference_no: container.find('#reference_no').val(),
                purchase_date: container.find('#purchase_date').val(),
                purchase_status: container.find('#purchase_status').val(),
                supplier_id: container.find('#supplier_id').val(),
                other_charges_input: toFloat(container.find('#other_charges_input').val()),
                other_charges_tax_id: container.find('#other_charges_tax_id').val(),
                discount_to_all_input: toFloat(container.find('#discount_to_all_input').val()),
                discount_to_all_type: container.find('#discount_to_all_type').val(),
                subtotal: toFloat(container.find('#subtotal_text').text()),
                round_off: toFloat(container.find('#round_off_text').text()),
                grand_total: toFloat(container.find('#grand_total_text').text()),
                purchase_note: container.find('#purchase_note').val(),
                payment_amount: toFloat(container.find('#payment_amount').val()),
                payment_type_id: container.find('#payment_type_id').val(),
                account_id: container.find('#account_id').val(),
                payment_note: container.find('#payment_note').val(),
                items_json: JSON.stringify(items)
            };

            btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-3"></i> Syncing Data...');

            $.post(ajaxurl, payload, function (res) {
                if (res.success) {
                    Swal.fire({
                        title: 'Updated!',
                        text: 'Purchase record has been successfully updated.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        const purchaseId = res.data.purchase_id;
                        window.location.href = '?view=purchase-invoice&id=' + purchaseId;
                    });
                } else {
                    Swal.fire('Error', (res.data && res.data.message) || res.data || 'Failed to update record', 'error');
                }
            }, 'json').fail(function (xhr) {
                Swal.fire('Error', getAjaxErrorMessage(xhr, 'Failed to update purchase.'), 'error');
            }).always(function () {
                btn.prop('disabled', false).html('<i class="fa-solid fa-cloud-arrow-up mr-3"></i> Apply Changes');
            });
        });

        // Callback for New Item Modal
        window.onItemAdded = function (item) {
            addNewItemRow({
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
<?php include FRONTEND_ACCOUNTING_TEMPLATE_PATH . 'modals/modal_item.php'; ?>
