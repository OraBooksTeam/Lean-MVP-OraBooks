<?php
if (!defined('ABSPATH'))
    exit;

global $wpdb;

// Fetch Dropdown Data
$warehouses = $wpdb->get_results("SELECT id, warehouse_name, warehouse_type FROM {$wpdb->prefix}orabooks_db_warehouse WHERE status=1 ORDER BY id ASC");
$customers = $wpdb->get_results("SELECT id, customer_name FROM {$wpdb->prefix}orabooks_db_customers WHERE status=1 ORDER BY customer_name ASC");
$payment_types = $wpdb->get_results("SELECT id, payment_type FROM {$wpdb->prefix}orabooks_db_paymenttypes WHERE status=1 ORDER BY id ASC");
$accounts = $wpdb->get_results("SELECT id, account_name, account_code FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE status=1 ORDER BY account_code ASC");
$taxes = $wpdb->get_results("SELECT id, tax_name, tax FROM {$wpdb->prefix}orabooks_db_tax WHERE status=1 ORDER BY id ASC");

$nonce = wp_create_nonce('frontend_ajax_nonce');
$source_sales_id = isset($_GET['sales_id']) ? intval($_GET['sales_id']) : 0;
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

    /* Ensure all parents and the grid itself don't clip Select2 dropdowns */
    .refund-process-container,
    .refund-process-container div,
    .refund-process-container .grid {
        position: relative !important;
        overflow: visible !important;
    }

    .refund-process-container {
        padding-bottom: 50px !important;
    }
</style>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-2">
        <div class="flex items-center">
            <div
                class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center mr-4 shadow-sm">
                <i class="fa-solid fa-arrow-rotate-left text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">New Sales Return</h1>
                <p class="text-sm text-gray-500 mt-1">Process a return from an existing sale</p>
            </div>
        </div>
        <a href="?view=sales-return-list"
            class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-all font-medium shadow-md">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to List
        </a>
    </div>

    <form id="return-form" class="space-y-6">
        <input type="hidden" name="action" value="insert_salesreturn">
        <input type="hidden" name="security" value="<?php echo esc_attr($nonce); ?>">
        <input type="hidden" name="original_sales_id" id="original_sales_id"
            value="<?php echo esc_attr($source_sales_id); ?>">

        <!-- Sales Info -->
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
            <h2 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-4">Sale Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Return Code</label>
                    <input type="text" name="sales_code" id="sales_code" readonly
                        class="w-full rounded-lg border-gray-300 bg-gray-100 py-2 px-3 text-sm font-mono text-indigo-700"
                        placeholder="Generating...">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Customer</label>
                    <select name="customer_id" id="customer_id" required
                        class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm select2">
                        <option value="">- Select Customer -</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?php echo $c->id; ?>"><?php echo esc_html($c->customer_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Warehouse</label>
                    <select name="warehouse_id" id="warehouse_id" required
                        class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm select2">
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?php echo $w->id; ?>" <?php selected($w->warehouse_type, 'system'); ?>>
                                <?php echo esc_html($w->warehouse_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Return Date</label>
                    <input type="date" name="sales_date" id="sales_date" value="<?php echo date('Y-m-d'); ?>"
                        class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Reference No.</label>
                    <input type="text" name="reference_no" id="reference_no"
                        class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm" placeholder="e.g. Sales Inv Ref">
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden shadow-sm">
            <table class="min-w-full divide-y divide-gray-200" id="items-table">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-widest">Item</th>
                        <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-widest">Original Total
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-widest">Return Qty</th>
                        <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-widest">Price</th>
                        <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-widest">Discount</th>
                        <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-widest">Tax Amt</th>
                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-widest">Total</th>
                        <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-widest">Action</th>
                    </tr>
                </thead>
                <tbody id="items-tbody" class="bg-white divide-y divide-gray-100">
                    <tr id="empty-row">
                        <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                            <i class="fa-solid fa-magnifying-glass text-4xl mb-4 opacity-20"></i>
                            Select a Sales Invoice to load items for return.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Bottom Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Return Note</label>
                    <textarea name="sales_note" id="sales_note" rows="3"
                        class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm"
                        placeholder="Reason for return..."></textarea>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                <div class="space-y-3 mb-6">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Subtotal:</span>
                        <span class="font-bold" id="subtotal_text">0.00</span>
                    </div>
                    <div class="flex justify-between items-center text-indigo-600">
                        <span class="text-sm">Discount on All:</span>
                        <span class="font-bold">- <span id="discount_all_text">0.00</span></span>
                    </div>
                    <div class="flex justify-between items-center text-emerald-600 border-t border-gray-200 pt-3">
                        <span class="text-lg font-bold">Grand Total:</span>
                        <span class="text-2xl font-black" id="grand_total_text">0.00</span>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-xl border border-indigo-100 mb-6 refund-process-container">
                    <h3 class="text-xs font-bold text-indigo-800 uppercase tracking-widest mb-4">Refund Process</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Refund Amount</label>
                            <input type="number" step="0.01" name="payment_amount" id="payment_amount"
                                class="w-full rounded-lg border-indigo-300 py-2 px-3 text-lg font-bold text-indigo-600"
                                placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Payment Type</label>
                            <select name="payment_type_id" id="payment_type_id"
                                class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm select2">
                                <?php foreach ($payment_types as $pt): ?>
                                    <option value="<?php echo $pt->id; ?>" <?php selected(strtolower($pt->payment_type), 'bank'); ?>><?php echo esc_html($pt->payment_type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Refund Account</label>
                            <select name="account_id" id="account_id"
                                class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm select2">
                                <option value="">- Select Account -</option>
                                <?php foreach ($accounts as $a): ?>
                                    <option value="<?php echo $a->id; ?>">
                                        <?php echo esc_html($a->account_code ? $a->account_code . ' - ' : '') . esc_html($a->account_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Return Reason</label>
                            <select name="return_reason" id="return_reason"
                                class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm select2">
                                <option value="">- Select Reason -</option>
                                <option value="Damaged">Damaged</option>
                                <option value="Incorrect Item">Incorrect Item</option>
                                <option value="Quality Issue">Quality Issue</option>
                                <option value="Expired">Expired</option>
                                <option value="Customer Dissatisfied">Customer Dissatisfied</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="button" id="submit-return"
                    class="w-full py-4 bg-indigo-600 text-white rounded-xl font-bold text-lg shadow-lg hover:bg-indigo-700 transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-check-circle"></i> Submit Sales Return
                </button>
            </div>
        </div>
    </form>
</div>

<template id="item-row-template">
    <tr class="item-row">
        <td class="px-4 py-3">
            <input type="hidden" name="items[][item_id]" class="row-item-id">
            <input type="hidden" class="row-account-id">
            <div class="font-medium text-gray-800 row-item-name"></div>
            <div class="text-[10px] text-gray-400 font-mono row-item-code"></div>
        </td>
        <td class="px-4 py-3 text-center text-sm font-bold text-gray-400 row-orig-qty">0</td>
        <td class="px-4 py-3">
            <input type="number" step="0.01" min="0" class="w-20 rounded border-gray-300 text-center py-1 row-qty"
                value="1">
        </td>
        <td class="px-4 py-3">
            <input type="number" step="0.01" class="w-24 rounded border-gray-300 text-right py-1 row-price" value="0.00"
                readonly>
        </td>
        <td class="px-4 py-3">
            <input type="number" step="0.01" class="w-20 rounded border-gray-300 text-right py-1 row-discount"
                value="0.00" readonly>
        </td>
        <td class="px-4 py-3 text-right text-sm row-tax-amt">0.00</td>
        <td class="px-4 py-3 text-right text-sm font-bold row-total">0.00</td>
        <td class="px-4 py-3 text-center">
            <button type="button" class="remove-item text-red-400 hover:text-red-600"><i
                    class="fa-solid fa-trash"></i></button>
        </td>
    </tr>
</template>

<script>
    jQuery(document).ready(function ($) {
        const ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
        const nonce = '<?php echo $nonce; ?>';
        const sourceId = <?php echo $source_sales_id; ?>;

        function fmt(n) { return parseFloat(n || 0).toFixed(2); }

        // Initialize Select2 with parent binding to solve the "hiding immediately" issue
        setTimeout(function () {
            $('.select2').each(function () {
                var $this = $(this);
                $this.select2({
                    width: '100%',
                    dropdownParent: $this.parent()
                });
            });
        }, 100);

        function generateCode() {
            $.post(ajaxurl, { action: 'generate_salesreturn_code', security: nonce }, function (res) {
                if (res.success) $('#sales_code').val(res.data.code);
            });
        }
        generateCode();

        function initSalesReturnView() {
            if (sourceId > 0) {
                loadSalesData(sourceId);
            } else {
                Swal.fire('Tip', 'Please initiate sales return from the Sales List to automatically load items.', 'info');
            }
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('view') === 'add-sales-return') {
            initSalesReturnView();
        } else if (sourceId > 0) {
            // Always load data if sourceId is provided, even if we are not the active view right now
            // (though normally view would be set)
            loadSalesData(sourceId);
        }

        $(document).on('obn_view_activated', function(e, viewName) {
            if (viewName === 'add-sales-return' && sourceId === 0) {
                initSalesReturnView();
            }
        });

        function loadSalesData(id) {
            $.get(ajaxurl, { action: 'get_sales_details', id: id, security: nonce }, function (res) {
                if (res.success) {
                    const s = res.data.sale;
                    const items = res.data.items;

                    $('#customer_id').val(s.customer_id).trigger('change');
                    $('#warehouse_id').val(s.warehouse_id).trigger('change');
                    $('#reference_no').val('RTN-' + s.sales_code);
                    $('#sales_note').val('Return for ' + s.sales_code);

                    $('#items-tbody').empty();
                    items.forEach(addItemRow);
                    recalculate();
                }
            });
        }

        function addItemRow(item) {
            const tpl = $('#item-row-template').html();
            const $row = $(tpl);

            $row.find('.row-item-id').val(item.item_id);
            $row.find('.row-item-name').text(item.item_name);
            $row.find('.row-item-code').text(item.item_code);
            $row.find('.row-orig-qty').text(item.sales_qty);
            $row.find('.row-qty').val(item.sales_qty).attr('max', item.sales_qty);
            $row.find('.row-price').val(item.price_per_unit);
            $row.find('.row-discount').val(item.discount_input);
            $row.find('.row-account-id').val(item.account_id);

            $row.find('.row-qty').on('input', function () {
                const max = parseFloat($(this).attr('max'));
                const val = parseFloat($(this).val());
                if (val > max) {
                    $(this).val(max);
                    Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'Cannot exceed sold quantity', showConfirmButton: false, timer: 2000 });
                }
                recalculate();
            });

            $row.find('.remove-item').on('click', function () {
                $(this).closest('tr').remove();
                if ($('#items-tbody tr').length === 0) {
                    $('#items-tbody').append('<tr id="empty-row"><td colspan="8" class="px-6 py-12 text-center text-gray-400">No items.</td></tr>');
                }
                recalculate();
            });

            $('#items-tbody').append($row);
        }

        function recalculate() {
            let subtotal = 0;
            let grandTotal = 0;

            $('.item-row').each(function () {
                const qty = parseFloat($(this).find('.row-qty').val()) || 0;
                const price = parseFloat($(this).find('.row-price').val()) || 0;
                const disc = parseFloat($(this).find('.row-discount').val()) || 0;

                // Note: Simplifiying tax calculation for return to match original item
                // On original sale, price_per_unit usually includes tax or is handled via unit_total_cost
                const line_sub = qty * price;
                const line_disc = disc; // Assuming it mirrors original
                const total = line_sub - line_disc;

                $(this).find('.row-total').text(fmt(total));
                subtotal += total;
            });

            $('#subtotal_text').text(fmt(subtotal));
            $('#grand_total_text').text(fmt(subtotal));
            $('#payment_amount').val(fmt(subtotal));
        }

        $('#submit-return').on('click', function () {
            const btn = $(this);
            const items = [];

            $('.item-row').each(function () {
                items.push({
                    item_id: $(this).find('.row-item-id').val(),
                    name: $(this).find('.row-item-name').text(),
                    qty: $(this).find('.row-qty').val(),
                    unit_price: $(this).find('.row-price').val(),
                    discount: $(this).find('.row-discount').val(),
                    total: $(this).find('.row-total').text(),
                    account_id: $(this).find('.row-account-id').val()
                });
            });

            const data = {
                action: 'insert_salesreturn',
                security: nonce,
                original_sales_id: sourceId,
                sales_code: $('#sales_code').val(),
                customer_id: $('#customer_id').val(),
                warehouse_id: $('#warehouse_id').val(),
                sales_date: $('#sales_date').val(),
                reference_no: $('#reference_no').val(),
                sales_note: $('#sales_note').val(),
                subtotal: $('#subtotal_text').text(),
                grand_total: $('#grand_total_text').text(),
                payment_amount: $('#payment_amount').val(),
                payment_type_id: $('#payment_type_id').val(),
                account_id: $('#account_id').val(),
                return_reason: $('#return_reason').val(),
                items_json: JSON.stringify(items)
            };

            btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Processing...');
            $.post(ajaxurl, data, function (res) {
                if (res.success) {
                    Swal.fire('Saved!', 'Sales return recorded (Pending approval).', 'success').then(() => {
                        window.location.href = '?view=sales-return-list';
                    });
                } else {
                    Swal.fire('Error', res.data, 'error');
                    btn.prop('disabled', false).html('<i class="fa-solid fa-check-circle"></i> Submit Sales Return');
                }
            });
        });
    });
</script>
