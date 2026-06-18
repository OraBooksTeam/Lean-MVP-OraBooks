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
$original_purchase_id = isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : 0;

if (!$original_purchase_id) {
    echo '<div class="p-6 bg-red-100 text-red-700 rounded-lg">Invalid Purchase Invoice ID</div>';
    return;
}
?>

<style>
    .select2-container {
        z-index: 999999 !important;
    }

    .select2-dropdown {
        z-index: 999999 !important;
        border: 1px solid #d1d5db !important;
        border-radius: 0.5rem !important;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
    }

    .select2-container--default .select2-selection--single {
        border-color: #d1d5db !important;
        height: 40px !important;
        display: flex !important;
        align-items: center !important;
        border-radius: 0.5rem !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 38px !important;
    }

    /* Ensure dropdowns aren't clipped */
    .purchase-return-container,
    .purchase-return-container div,
    .purchase-return-container .grid {
        position: relative !important;
        overflow: visible !important;
    }

    .purchase-return-container {
        padding-bottom: 50px !important;
    }

    .ui-autocomplete {
        z-index: 999999 !important;
        max-height: 300px;
        overflow-y: auto;
        overflow-x: hidden;
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

    .ui-state-active {
        background: #eff6ff !important;
        border: none !important;
        color: #2563eb !important;
    }

    /* Custom color scheme */
    .custom-green-100 {
        background-color: #d4f1d4 !important;
    }

    .custom-green-500 {
        background-color: #39B54A !important;
    }

    .custom-green-600 {
        background-color: #2d9a3a !important;
    }

    .custom-green-700 {
        background-color: #237a2e !important;
    }

    .custom-green-text {
        color: #39B54A !important;
    }

    .custom-green-text-dark {
        color: #2d9a3a !important;
    }

    .custom-blue-600 {
        background-color: #1569B3 !important;
    }

    .custom-blue-700 {
        background-color: #125491 !important;
    }

    .custom-blue-text {
        color: #1569B3 !important;
    }

    .custom-border-green {
        border-color: #39B54A !important;
    }

    .custom-border-blue {
        border-color: #1569B3 !important;
    }

    .custom-shadow-green {
        box-shadow: 0 10px 15px -3px rgba(57, 181, 74, 0.2) !important;
    }

    .custom-shadow-blue {
        box-shadow: 0 10px 15px -3px rgba(21, 105, 179, 0.2) !important;
    }
</style>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 purchase-return-container">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
        <div class="flex items-center">
            <div
                class="w-12 h-12 custom-green-100 custom-green-text rounded-lg flex items-center justify-center mr-4 shadow-sm font-bold">
                <i class="fa-solid fa-rotate-left text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">New Purchase Return</h1>
                <p class="text-sm text-gray-500 mt-1">Create a return from an existing purchase invoice</p>
            </div>
        </div>
        <a href="?view=purchase-return-list"
            class="inline-flex items-center px-5 py-2.5 custom-green-600 text-white rounded-xl hover:custom-green-700 transition-all font-bold text-xs uppercase tracking-widest shadow-lg custom-shadow-green active:scale-95">
            <i class="fa-solid fa-arrow-left-long mr-2"></i> Back to List
        </a>
    </div>

    <form id="purchase-form" class="space-y-6">
        <input type="hidden" name="action" value="insert_purchasereturn">
        <input type="hidden" name="security" value="<?php echo esc_attr($nonce); ?>">
        <input type="hidden" name="original_purchase_id" value="<?php echo esc_attr($original_purchase_id); ?>">

        <!-- Top Section: Details -->
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
            <h2 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-4">Return Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Return Code</label>
                    <input type="text" name="purchase_code" id="purchase_code" readonly
                        class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm font-bold custom-green-text"
                        placeholder="Generating...">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">
                        Warehouse <span class="text-blue-500">*</span>
                        Warehouse <span class="text-red-500">*</span>
                    </label>
                    <select name="warehouse_id" id="warehouse_id" required
                        class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm select2">
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?php echo esc_attr($w->id); ?>"><?php echo esc_html($w->warehouse_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">
                        Supplier <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-2">
                        <select name="supplier_id" id="supplier_id" required
                            class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm select2">
                            <option value="">- Select Supplier -</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?php echo esc_attr($s->id); ?>"><?php echo esc_html($s->supplier_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button"
                            class="min-h-[25px] px-1.5 py-0.5 md:px-3 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 border border-blue-200 transition-colors text-[10px] md:text-base flex-shrink-0"
                            title="Add New Supplier" onclick="openSupplierModal()">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Return Date</label>
                    <input type="date" name="purchase_date" id="purchase_date" value="<?php echo date('Y-m-d'); ?>"
                        class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Reference No.</label>
                    <input type="text" name="reference_no" id="reference_no"
                        class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm" placeholder="Original Invoice No">
                </div>
            </div>
        </div>

        <!-- Middle Section: Items -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
            <div
                class="p-4 bg-gray-50 border-b border-gray-200 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex-1 flex items-center gap-4 w-full">
                    <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest whitespace-nowrap">
                        <i class="fa-solid fa-box mr-2 custom-green-text"></i> Returned Items
                    </h2>
                    <p class="text-xs text-amber-600 ml-4 font-medium"><i class="fa-solid fa-circle-info mr-1"></i> You
                        can adjust quantities of the items being returned.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="items-table">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-widest">Item</th>
                            <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-widest w-24">Stock
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-widest">Account</th>
                            <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-widest w-32">Ret. Qty
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-widest w-32">Unit Cost
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-widest w-24">Discount
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-widest w-24">Tax %
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-widest w-24">Tax Amt
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-widest w-32">Total</th>
                            <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-widest w-16">Action
                            </th>
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
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Return Note</label>
                        <textarea name="purchase_note" id="purchase_note" rows="4"
                            class="w-full rounded-xl border-gray-300 focus:ring-green-500 focus:border-green-500 shadow-sm px-4 py-3"
                            placeholder="Click to add note..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Right: Calculation Card -->
            <div
                class="bg-white rounded-3xl p-8 text-gray-900 border border-gray-200 shadow-sm relative overflow-hidden group">
                <div
                    class="absolute -top-24 -right-24 w-64 h-64 custom-green-500 opacity-5 rounded-full blur-3xl group-hover:opacity-10 transition-opacity">
                </div>
                <h2
                    class="text-sm font-bold uppercase tracking-[0.2em] mb-8 custom-green-text opacity-80 border-b border-gray-100 pb-4">
                    Order Summary</h2>

                <div class="space-y-4 mb-8">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-500 font-medium">Items Subtotal</span>
                        <span class="font-bold text-gray-900" id="subtotal_text">0.00</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-500 font-medium">Other Charges</span>
                        <span class="font-bold custom-green-text" id="other_charges_text">0.00</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="font-medium custom-green-text">Total Discount</span>
                        <span class="font-black custom-green-text-dark">- <span
                                id="discount_all_text">0.00</span></span>
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
                                class="block text-[10px] font-black uppercase tracking-[0.3em] custom-green-text mb-1">Grand
                                Total</span>
                            <span class="text-xs text-gray-500 font-medium italic">*Calculated automatically</span>
                        </div>
                        <span class="text-4xl font-black custom-green-text" id="grand_total_text">0.00</span>
                    </div>
                    <div class="pt-2 flex justify-between items-center text-sm">
                        <span class="text-gray-500 font-medium">Balance Due</span>
                        <span class="font-black custom-green-text" id="balance_due_text">0.00</span>
                    </div>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-2xl p-6 mb-8 purchase-return-container">
                    <h3
                        class="text-[10px] font-black uppercase tracking-[0.2em] custom-green-text mb-4 flex items-center">
                        <i class="fa-solid fa-wallet mr-2"></i> Refund Details
                    </h3>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase mb-2">Payment
                                Type</label>
                            <select id="payment_type_id" name="payment_type_id"
                                class="w-full rounded-lg border-gray-200 bg-white text-gray-900 text-xs py-2 select2">
                                <option value="">- Select -</option>
                                <?php foreach ($payment_types as $pt): ?>
                                    <option value="<?php echo esc_attr($pt->id); ?>">
                                        <?php echo esc_html($pt->payment_type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase mb-2">Refund
                                Amount</label>
                            <input type="number" step="0.01" name="payment_amount" id="payment_amount"
                                class="w-full rounded-lg custom-border-green bg-white text-emerald-600 font-bold text-sm py-2 px-3 focus:ring-0 custom-border-green">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase mb-2">Refund
                                Account</label>
                            <select id="account_id" name="account_id"
                                class="w-full rounded-lg border-gray-200 bg-white text-gray-900 text-xs py-2 select2">
                                <option value="">- Select -</option>
                                <?php foreach ($coa_accounts as $a): ?>
                                    <option value="<?php echo esc_attr($a->id); ?>">
                                        <?php echo esc_html($a->account_code ? $a->account_code . ' - ' : '') . esc_html($a->account_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase mb-2">Refund Note</label>
                            <input type="text" name="payment_note" id="payment_note"
                                class="w-full rounded-lg border-gray-200 bg-white text-gray-900 text-xs py-2 px-3 focus:ring-green-500 custom-border-green"
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

                <button type="button" id="save-purchase"
                    class="w-full py-4 custom-green-500 hover:custom-green-600 text-white rounded-2xl transition-all font-black text-sm uppercase tracking-[0.2em] shadow-lg custom-shadow-green active:scale-[0.98] flex justify-center items-center">
                    <i class="fa-solid fa-cloud-arrow-up mr-3"></i> Submit Purchase Return
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
                    class="change-item hidden ml-2 text-gray-400 hover:custom-green-text transition-colors">
                    <i class="fa-solid fa-rotate text-xs"></i>
                </button>
            </div>
        </td>
        <td class="p-3 border-b text-center">
            <span
                class="row-stock inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">0</span>
        </td>
        <td class="px-4 py-3 border-b">
            <select class="row-account-id w-full rounded-lg border-gray-300 text-xs py-1">
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
                class="row-price w-full rounded border-gray-300 text-sm focus:ring-green-500 custom-border-green py-1"
                value="0.00">
        </td>
        <td class="p-3 border-b">
            <input type="number" step="0.01"
                class="row-discount w-full rounded border-gray-300 text-sm focus:ring-green-500 custom-border-green py-1"
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
        const container = $('#obn-view-add-purchase-return');
        const ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
        const nonce = '<?php echo esc_js($nonce); ?>';
        const original_purchase_id = '<?php echo esc_js($original_purchase_id); ?>';

        function fmt(v) { return parseFloat(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        function toFloat(v) { return Number((v || 0).toString().replace(/,/g, '')) || 0; }

        // Initialize Select2 with parent binding
        if (container.find('#warehouse_id').hasClass("select2-hidden-accessible")) {
            container.find('#warehouse_id, #supplier_id, #payment_type_id, #account_id').select2('destroy');
        }
        container.find('#warehouse_id, #supplier_id, #payment_type_id, #account_id').select2({
            width: '100%',
            dropdownParent: container.find('#purchase-form')
        });

        // Generate Code
        function generateCode() {
            $.post(ajaxurl, { action: 'generate_purchasereturn_code', security: nonce }, function (res) {
                if (res.success) container.find('#purchase_code').val(res.data.code);
            }, 'json');
        }
        generateCode();

        // Load Original Purchase Data
        $.get(ajaxurl, { action: 'get_purchase_details', id: original_purchase_id, security: nonce }, function (res) {
            if (res.success) {
                const p = res.data.purchase;
                const items = res.data.items;

                container.find('#warehouse_id').val(p.warehouse_id).trigger('change');
                container.find('#supplier_id').val(p.supplier_id).trigger('change');
                container.find('#reference_no').val('RTN-' + p.purchase_code);

                container.find('#other_charges_input').val(p.other_charges_input || 0);
                container.find('#other_charges_tax_id').val(p.other_charges_tax_id || '');
                container.find('#discount_to_all_input').val(p.discount_to_all_input || 0);
                container.find('#discount_to_all_type').val(p.discount_to_all_type || 'Percentage');

                if (items && items.length > 0) {
                    items.forEach(item => { populateRowFromData(item); });
                }

                recalc();
            } else {
                Swal.fire('Error', res.data || 'Failed to load original invoice', 'error');
            }
        }, 'json');

        function populateRowFromData(item) {
            const tpl = document.getElementById('row-template');
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
            row.find('.row-discount').val(item.discount_input || 0);

            // Deduction of tax percent
            let tax_pct = 0;
            const line_val = toFloat(item.price_per_unit) * toFloat(item.purchase_qty);
            const taxable = Math.max(0, line_val - toFloat(item.discount_input || 0));
            if (taxable > 0 && toFloat(item.tax_amt) > 0) {
                tax_pct = (toFloat(item.tax_amt) / taxable) * 100;
            }
            row.find('.row-tax').val(tax_pct.toFixed(2));

            container.find('#items-tbody').append(row);
            bindRowEvents(row);
        }

        // Add Row Button (usually disabled in return, but if allowed)
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

        function bindRowEvents(row) {
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

        let isRecalculating = false;
        function recalc() {
            if (isRecalculating) return;
            isRecalculating = true;
            let subtotal = 0, tax_total = 0;

            container.find('#items-tbody tr').each(function () {
                const qty = toFloat($(this).find('.row-qty').val());
                const price = toFloat($(this).find('.row-price').val());
                const discount = toFloat($(this).find('.row-discount').val());
                const tax_pct = toFloat($(this).find('.row-tax').val());

                const line_before = qty * price;
                const taxable = Math.max(0, line_before - discount);
                const tax_amt = (taxable * tax_pct) / 100;
                const line_total = taxable + tax_amt;

                $(this).find('.row-tax-amt').val(fmt(tax_amt));
                $(this).find('.row-total').val(fmt(line_total));

                subtotal += taxable;
                tax_total += tax_amt;
            });

            const other_charges_in = toFloat(container.find('#other_charges_input').val());
            let other_tax_amt = 0;
            const other_tax_sel = container.find('#other_charges_tax_id option:selected');
            if (other_tax_sel.val()) {
                other_tax_amt = (other_charges_in * toFloat(other_tax_sel.data('percent'))) / 100;
            }

            const disc_all_in = toFloat(container.find('#discount_to_all_input').val());
            let disc_all_amt = (container.find('#discount_to_all_type').val() === 'Percentage') ? (subtotal * disc_all_in / 100) : disc_all_in;

            const subtotal_post_disc = Math.max(0, subtotal - disc_all_amt);
            const final_tax = tax_total + other_tax_amt;
            const grand_before_round = subtotal_post_disc + final_tax + other_charges_in;
            const grand_total = Math.round(grand_before_round * 100) / 100;
            const round_off = +(grand_total - grand_before_round).toFixed(2);

            container.find('#subtotal_text').text(fmt(subtotal));
            container.find('#other_charges_text').text(fmt(other_charges_in + other_tax_amt));
            container.find('#discount_all_text').text(fmt(disc_all_amt));
            container.find('#tax_total_text').text(fmt(final_tax));
            container.find('#round_off_text').text((round_off >= 0 ? '+' : '') + round_off.toFixed(2));
            container.find('#grand_total_text').text(fmt(grand_total));

            // Auto-fill Refund Amount if it hasn't been manually edited by the user yet
            if (document.activeElement !== document.getElementById('payment_amount')) {
                container.find('#payment_amount').val(grand_total.toFixed(2));
            }

            const paid = toFloat(container.find('#payment_amount').val());
            const balance = Math.max(0, grand_total - paid);
            container.find('#balance_due_text').text(fmt(balance));

            isRecalculating = false;
        }

        container.find('#other_charges_input, #other_charges_tax_id, #discount_to_all_input, #discount_to_all_type, #payment_amount').on('input change', recalc);

        // Form Submission
        container.find('#save-purchase').off('click').on('click', function () {
            const btn = $(this);
            if (!container.find('#warehouse_id').val()) { alert('Please select a warehouse'); return; }
            if (!container.find('#supplier_id').val()) { alert('Please select a supplier'); return; }
            if (container.find('#items-tbody tr').length === 0) { alert('Your return is empty. Please add items.'); return; }

            const items = [];
            container.find('#items-tbody tr').each(function () {
                const item_id = $(this).find('.row-item-id').val();
                if (item_id) {
                    items.push({
                        item_id: item_id,
                        name: $(this).find('.row-name').text(),
                        account_id: $(this).find('.row-account-id').val(),
                        qty: $(this).find('.row-qty').val(),
                        unit_price: $(this).find('.row-price').val(),
                        discount: $(this).find('.row-discount').val(),
                        tax_percent: $(this).find('.row-tax').val(),
                        tax_amt: toFloat($(this).find('.row-tax-amt').val()),
                        total: toFloat($(this).find('.row-total').val())
                    });
                }
            });

            const payload = {
                action: 'insert_purchasereturn',
                security: nonce,
                original_purchase_id: original_purchase_id,
                warehouse_id: container.find('#warehouse_id').val(),
                purchase_code: container.find('#purchase_code').val(),
                reference_no: container.find('#reference_no').val(),
                purchase_date: container.find('#purchase_date').val(),
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

            btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-3"></i> Submitting...');

            $.post(ajaxurl, payload, function (res) {
                if (res.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Purchase return has been successfully submitted.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        const purchaseId = res.data.purchase_id;
                        window.location.href = '?view=purchase-return-invoice&id=' + purchaseId;
                    });
                } else {
                    Swal.fire('Error', res.data || 'Failed to submit return', 'error');
                }
            }, 'json').always(function () {
                btn.prop('disabled', false).html('<i class="fa-solid fa-check-circle mr-3"></i> Submit Purchase Return');
            });
        });

    });
</script>

<?php include FRONTEND_ACCOUNTING_TEMPLATE_PATH . 'modals/modal_supplier.php'; ?>
<?php include FRONTEND_ACCOUNTING_TEMPLATE_PATH . 'modals/modal_item.php'; ?>