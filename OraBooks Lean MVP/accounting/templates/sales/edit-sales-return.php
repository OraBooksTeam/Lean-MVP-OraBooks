<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if(!$id) {
    echo "ID missing.";
    return;
}

// Fetch Dropdown Data
$warehouses = $wpdb->get_results("SELECT id, warehouse_name, warehouse_type FROM {$wpdb->prefix}orabooks_db_warehouse WHERE status=1 ORDER BY id ASC");
$customers  = $wpdb->get_results("SELECT id, customer_name FROM {$wpdb->prefix}orabooks_db_customers WHERE status=1 ORDER BY customer_name ASC");
$payment_types = $wpdb->get_results("SELECT id, payment_type FROM {$wpdb->prefix}orabooks_db_paymenttypes WHERE status=1 ORDER BY id ASC");
$accounts   = $wpdb->get_results("SELECT id, account_name, account_code FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE status=1 ORDER BY account_code ASC");

$nonce = wp_create_nonce('frontend_ajax_nonce');
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
    .edit-return-container, .edit-return-container div {
        position: relative !important;
        overflow: visible !important;
    }
</style>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 edit-return-container">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-2">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center mr-4 shadow-sm">
                <i class="fa-solid fa-pen-to-square text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Edit Sales Return</h1>
                <p class="text-sm text-gray-500 mt-1">Modify pending sales return details</p>
            </div>
        </div>
        <a href="?view=sales-return-list" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-all font-medium shadow-md">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to List
        </a>
    </div>

    <form id="return-form" class="space-y-6">
        <input type="hidden" name="action" value="update_salesreturn">
        <input type="hidden" name="security" value="<?php echo esc_attr($nonce); ?>">
        <input type="hidden" name="sales_id" id="sales_id" value="<?php echo esc_attr($id); ?>">

        <!-- Sales Info -->
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
            <h2 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-4">Return Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Return Code</label>
                    <input type="text" id="sales_code" readonly class="w-full rounded-lg border-gray-300 bg-gray-100 py-2 px-3 text-sm font-mono text-indigo-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Customer</label>
                    <select name="customer_id" id="customer_id" required class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm select2">
                        <option value="">- Select Customer -</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?php echo $c->id; ?>"><?php echo esc_html($c->customer_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                     <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Warehouse</label>
                     <select name="warehouse_id" id="warehouse_id" required class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm select2">
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?php echo $w->id; ?>"><?php echo esc_html($w->warehouse_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Return Date</label>
                    <input type="date" name="sales_date" id="sales_date" class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm">
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden shadow-sm">
            <table class="min-w-full divide-y divide-gray-200" id="items-table">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-widest">Item</th>
                        <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-widest">Original Total</th>
                        <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-widest">Return Qty</th>
                        <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-widest">Price</th>
                        <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-widest">Discount</th>
                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-widest">Total</th>
                        <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-widest">Action</th>
                    </tr>
                </thead>
                <tbody id="items-tbody" class="bg-white divide-y divide-gray-100">
                    <!-- Loaded via JS -->
                </tbody>
            </table>
        </div>

        <!-- Bottom Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Return Note</label>
                    <textarea name="sales_note" id="sales_note" rows="3" class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm"></textarea>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                <div class="space-y-3 mb-6">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Subtotal:</span>
                        <span class="font-bold" id="subtotal_text">0.00</span>
                    </div>
                    <div class="flex justify-between items-center text-emerald-600 border-t border-gray-200 pt-3">
                        <span class="text-lg font-bold">Grand Total:</span>
                        <span class="text-2xl font-black" id="grand_total_text">0.00</span>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-xl border border-indigo-100 mb-6">
                    <h3 class="text-xs font-bold text-indigo-800 uppercase tracking-widest mb-4">Refund Update</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Refund Amount</label>
                            <input type="number" step="0.01" name="payment_amount" id="payment_amount" class="w-full rounded-lg border-indigo-300 py-2 px-3 text-lg font-bold text-indigo-600">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Payment Type</label>
                            <select name="payment_type_id" id="payment_type_id" class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm select2">
                                <?php foreach ($payment_types as $pt): ?>
                                    <option value="<?php echo $pt->id; ?>"><?php echo esc_html($pt->payment_type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Refund Account</label>
                            <select name="account_id" id="account_id" class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm select2">
                                <option value="">- Select Account -</option>
                                <?php foreach ($accounts as $a): ?>
                                    <option value="<?php echo $a->id; ?>"><?php echo esc_html($a->account_code ? $a->account_code . ' - ' : '') . esc_html($a->account_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                             <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Return Reason / Problem</label>
                             <select name="return_reason" id="return_reason" class="w-full rounded-lg border-gray-300 py-2 px-3 text-sm select2">
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

                <button type="button" id="submit-return" class="w-full py-4 bg-indigo-600 text-white rounded-xl font-bold text-lg shadow-lg hover:bg-indigo-700 transition-all">
                    Update Sales Return
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
        </td>
        <td class="px-4 py-3 text-center text-sm font-bold text-gray-400 row-orig-qty">-</td>
        <td class="px-4 py-3">
            <input type="number" step="0.01" min="0" class="w-20 rounded border-gray-300 text-center py-1 row-qty">
        </td>
        <td class="px-4 py-3">
            <input type="number" step="0.01" class="w-24 rounded border-gray-300 text-right py-1 row-price" readonly>
        </td>
        <td class="px-4 py-3">
            <input type="number" step="0.01" class="w-20 rounded border-gray-300 text-right py-1 row-discount" readonly>
        </td>
        <td class="px-4 py-3 text-right text-sm font-bold row-total">0.00</td>
        <td class="px-4 py-3 text-center">
            <button type="button" class="remove-item text-red-400 hover:text-red-600"><i class="fa-solid fa-trash"></i></button>
        </td>
    </tr>
</template>

<script>
jQuery(document).ready(function($) {
    const ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
    const nonce = '<?php echo $nonce; ?>';
    const returnId = <?php echo $id; ?>;


    setTimeout(function() {
        $('.select2').each(function() {
            var $this = $(this);
            $this.select2({ 
                width: '100%',
                dropdownParent: $this.parent()
            });
        });
    }, 100);

    function loadReturnData() {
        $.get(ajaxurl, { action: 'get_salesreturn_details', id: returnId, security: nonce }, function(res) {
            if (res.success) {
                const sr = res.data.sales;
                const items = res.data.items;
                const payments = res.data.payments;

                $('#sales_code').val(sr.return_code);
                $('#customer_id').val(sr.customer_id).trigger('change');
                $('#warehouse_id').val(sr.warehouse_id).trigger('change');
                $('#sales_date').val(sr.return_date);
                $('#sales_note').val(sr.return_note);
                $('#payment_amount').val(sr.paid_amount);

                if (payments.length > 0) {
                    $('#payment_type_id').val(payments[0].payment_type).trigger('change');
                    $('#account_id').val(payments[0].account_id).trigger('change');
                }

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
        $row.find('.row-qty').val(item.sales_qty);
        $row.find('.row-price').val(item.price_per_unit);
        $row.find('.row-discount').val(item.discount_input);
        $row.find('.row-account-id').val(item.account_id);

        $row.find('.row-qty').on('input', recalculate);
        $row.find('.remove-item').on('click', function() { $(this).closest('tr').remove(); recalculate(); });

        $('#items-tbody').append($row);
    }

    function recalculate() {
        let subtotal = 0;
        $('.item-row').each(function() {
            const qty = parseFloat($(this).find('.row-qty').val()) || 0;
            const price = parseFloat($(this).find('.row-price').val()) || 0;
            const disc = parseFloat($(this).find('.row-discount').val()) || 0;
            const total = (qty * price) - disc;
            $(this).find('.row-total').text(total.toFixed(2));
            subtotal += total;
        });
        $('#subtotal_text').text(subtotal.toFixed(2));
        $('#grand_total_text').text(subtotal.toFixed(2));
    }

    $('#submit-return').on('click', function() {
        const items = [];
        $('.item-row').each(function() {
            items.push({
                item_id: $(this).find('.row-item-id').val(),
                qty: $(this).find('.row-qty').val(),
                unit_price: $(this).find('.row-price').val(),
                discount: $(this).find('.row-discount').val(),
                total: $(this).find('.row-total').text(),
                account_id: $(this).find('.row-account-id').val()
            });
        });

        const data = {
            action: 'update_salesreturn',
            security: nonce,
            sales_id: returnId,
            customer_id: $('#customer_id').val(),
            warehouse_id: $('#warehouse_id').val(),
            sales_date: $('#sales_date').val(),
            sales_note: $('#sales_note').val(),
            subtotal: $('#subtotal_text').text(),
            grand_total: $('#grand_total_text').text(),
            payment_amount: $('#payment_amount').val(),
            payment_type_id: $('#payment_type_id').val(),
            account_id: $('#account_id').val(),
            return_reason: $('#return_reason').val(),
            items_json: JSON.stringify(items)
        };

        $.post(ajaxurl, data, function(res) {
            if (res.success) {
                Swal.fire('Updated!', 'Return details updated.', 'success').then(() => {
                    window.location.href = '?view=sales-return-list';
                });
            } else {
                Swal.fire('Error', res.data, 'error');
            }
        });
    });

    loadReturnData();
});
</script>

