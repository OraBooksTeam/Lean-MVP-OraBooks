<?php
if (!defined('ABSPATH'))
    exit;

global $wpdb;
$table_payments = $wpdb->prefix . 'orabooks_db_customer_payments';
$table_customers = $wpdb->prefix . 'orabooks_db_customers';
$table_accounts = $wpdb->prefix . 'orabooks_ac_accounts';

// Fetch data for dropdowns
$customers = $wpdb->get_results("SELECT id, customer_name, customer_code FROM $table_customers WHERE status = 1 ORDER BY customer_name ASC");
$accounts = $wpdb->get_results("SELECT id, account_name FROM $table_accounts WHERE status = 1 ORDER BY account_name ASC");
$payment_types = $wpdb->get_results("SELECT id, payment_type FROM {$wpdb->prefix}orabooks_db_paymenttypes WHERE status = 1 ORDER BY payment_type ASC");

// Fetch existing payments
$payments = $wpdb->get_results("
    SELECT p.*, c.customer_name, a.account_name, pt.payment_type as payment_type_name
    FROM $table_payments p
    LEFT JOIN $table_customers c ON p.customer_id = c.id
    LEFT JOIN $table_accounts a ON p.account_id = a.id
    LEFT JOIN {$wpdb->prefix}orabooks_db_paymenttypes pt ON p.payment_type = pt.id
    ORDER BY p.id DESC
");

$currency = '৳';
$nonce = wp_create_nonce('frontend_ajax_nonce');
?>

<div class="p-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
            <div class="flex items-center gap-4">
                <div
                    class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-lg">
                    <i class="fa-solid fa-money-bill-wave text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Customer Payments</h1>
                    <p class="text-gray-500 text-sm">Manage and track customer payment records</p>
                </div>
            </div>
            <button id="create-pay-btn"
                class="px-6 py-2.5 bg-gray-900 hover:bg-black text-white font-medium rounded-xl shadow-lg hover:shadow-xl transition-all flex items-center gap-2">
                <i class="fa-solid fa-plus"></i> <span>Create Pay</span>
            </button>
        </div>

        <!-- Payment List Table -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
            <div
                class="p-4 border-b border-gray-100 bg-gray-50 flex flex-col md:flex-row gap-4 items-center justify-between">
                <div class="relative w-full md:w-1/3">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <i class="fa-solid fa-search text-gray-400 text-sm"></i>
                    </div>
                    <input type="search" id="paySearchInput"
                        class="block w-full pl-10 pr-3 py-2 text-sm text-gray-900 border border-gray-300 rounded-lg bg-white focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Search payments...">
                </div>
                <div class="flex gap-2">
                    <button id="printPayBtn"
                        class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-2 transition-colors">
                        <i class="fa-solid fa-print mr-1"></i> Print
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="payment-table">
                    <thead class="bg-gray-800 text-white">
                        <tr class="text-sm uppercase tracking-wider">
                            <th class="px-6 py-4 font-bold text-left">Date</th>
                            <th class="px-6 py-4 font-bold text-left">Customer</th>
                            <th class="px-6 py-4 font-bold text-left">Reference</th>
                            <th class="px-6 py-4 font-bold text-left">Account</th>
                            <th class="px-6 py-4 font-bold text-left">Type</th>
                            <th class="px-6 py-4 font-bold text-right">Amount</th>
                            <th class="px-6 py-4 font-bold text-center">Status</th>
                            <th class="px-6 py-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if ($payments):
                            foreach ($payments as $p): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <?php echo date('d-m-Y', strtotime($p->payment_date)); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-bold text-gray-800">
                                        <?php echo esc_html($p->customer_name); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo esc_html($p->reference_no); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo esc_html($p->account_name); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo esc_html($p->payment_type_name ?? $p->payment_type); ?></td>
                                    <td class="px-6 py-4 text-right font-bold text-blue-600">
                                        <?php echo number_format($p->payment, 2); ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button class="toggle-pay-status relative inline-flex items-center cursor-pointer"
                                            data-id="<?php echo $p->id; ?>" data-status="<?php echo $p->status; ?>">
                                            <div
                                                class="w-10 h-5 bg-gray-200 rounded-full transition-colors <?php echo $p->status == 1 ? 'bg-green-500' : 'bg-gray-200'; ?>">
                                            </div>
                                            <div
                                                class="absolute w-3 h-3 bg-white rounded-full shadow inset-y-1 left-1 transition-transform <?php echo $p->status == 1 ? 'translate-x-5' : ''; ?>">
                                            </div>
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button onclick='editPayment(<?php echo json_encode($p); ?>)'
                                                class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 flex items-center justify-center transition-colors">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <button onclick="deletePayment(<?php echo $p->id; ?>)"
                                                class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 flex items-center justify-center transition-colors">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-500 italic">No payments found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Payment Modal -->
<div id="payment-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div
            class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
            <div class="p-6 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-800" id="modal-title">Customer Payment</h3>
                <button id="close-modal" class="text-gray-400 hover:text-gray-600">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>

            <form id="payment-form" class="p-6">
                <input type="hidden" name="action" value="frontend_save_customer_pay">
                <input type="hidden" name="security" value="<?php echo $nonce; ?>">
                <input type="hidden" name="id" id="pay_id" value="">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Customer <span
                                class="text-red-500">*</span></label>
                        <select name="customer_id" id="pay_customer_id" required
                            class="w-full rounded-xl border-gray-200 focus:ring-blue-500 focus:border-blue-500 pay-select2">
                            <option value="">Select Customer</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?php echo $c->id; ?>">
                                    <?php echo esc_html($c->customer_name . ' (' . $c->customer_code . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Date <span
                                class="text-red-500">*</span></label>
                        <input type="date" name="payment_date" id="pay_date" required
                            value="<?php echo date('Y-m-d'); ?>"
                            class="w-full p-2 rounded-xl border-gray-200 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Reference</label>
                        <input type="text" name="reference_no" id="pay_reference"
                            class="w-full p-2 rounded-xl border-gray-200 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Ref #">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Payment Type</label>
                        <select name="payment_type_id" id="pay_type"
                            class="w-full p-2 rounded-xl border-gray-200 focus:ring-blue-500 focus:border-blue-500">
                            <?php foreach ($payment_types as $pt): ?>
                                <option value="<?php echo $pt->id; ?>"><?php echo esc_html($pt->payment_type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Deposit to <span
                                id="deposit-asterisk" class="text-red-500 hidden">*</span></label>
                        <select name="account_id" id="pay_account_id"
                            class="w-full p-2 rounded-xl border-gray-200 focus:ring-blue-500 focus:border-blue-500 pay-select2">
                            <option value="">Select Account</option>
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?php echo $a->id; ?>"><?php echo esc_html($a->account_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>

                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Payment Note</label>
                    <textarea name="payment_note" id="pay_note" rows="2"
                        class="w-full p-2 rounded-xl border-gray-200 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Add note..."></textarea>
                </div>

                <!-- Invoice Table -->
                <div class="mb-6 border rounded-xl overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200" id="invoice-selection-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Voucher No
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Due Date</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Total</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Balance</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Payment</th>
                            </tr>
                        </thead>
                        <tbody id="invoice-list" class="divide-y divide-gray-100">
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-400 italic">Select a customer to
                                    see due invoices</td>
                            </tr>
                        </tbody>
                        <tfoot id="invoice-tfoot" class="bg-gray-50 font-bold hidden">
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-right text-xs text-gray-500 uppercase">Totals</td>
                                <td id="total-grand-sum" class="px-4 py-3 text-right text-sm text-gray-600">0.00</td>
                                <td id="total-balance-sum" class="px-4 py-3 text-right text-sm text-gray-900">0.00</td>
                                <td class="px-4 py-3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="flex flex-col items-end mb-6">
                    <div class="w-full md:w-1/3 space-y-2">
                        <div class="flex justify-between items-center font-bold text-lg">
                            <span class="text-gray-600">Total Amount:</span>
                            <span class="text-blue-600"><?php echo $currency; ?> <span
                                    id="total-pay-display">0.00</span></span>
                        </div>
                        <input type="hidden" name="payment" id="total_payment_input" value="0">
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                    <button type="button" id="cancel-modal"
                        class="px-6 py-2.5 bg-gray-100 text-gray-700 font-bold rounded-xl hover:bg-gray-200 transition-colors">Cancel</button>
                    <button type="submit" id="save-pay-btn"
                        class="px-8 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg transition-all">Save
                        Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function ($) {
        const ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
        const nonce = '<?php echo $nonce; ?>';

        // Init Select2
        $('.pay-select2').select2({
            width: '100%',
            dropdownParent: $('#payment-modal')
        });

        // Toggle Modal
        $('#create-pay-btn').on('click', function () {
            resetPayForm();
            $('#payment-modal').removeClass('hidden');
            $('#modal-title').text('Create Customer Payment');
        });

        $('#close-modal, #cancel-modal').on('click', function () {
            $('#payment-modal').addClass('hidden');
        });

        function resetPayForm() {
            $('#payment-form')[0].reset();
            $('#pay_id').val('');
            $('#pay_customer_id, #pay_account_id').val('').trigger('change');
            $('#invoice-list').html('<tr><td colspan="5" class="px-4 py-6 text-center text-gray-400 italic">Select a customer to see due invoices</td></tr>');
            $('#total-pay-display').text('0.00');
            $('#total_payment_input').val('0');
        }

        // Fetch Invoices on Customer Change
        $('#pay_customer_id').on('change', function () {
            const customerId = $(this).val();
            if (!customerId) return;

            $('#invoice-list').html('<tr><td colspan="5" class="px-4 py-6 text-center text-gray-500"><i class="fa fa-spinner fa-spin mr-2"></i> Loading invoices...</td></tr>');

            $.post(ajaxurl, {
                action: 'frontend_get_customer_invoices',
                customer_id: customerId,
                security: nonce
            }, function (res) {
                if (res.success && res.data.length > 0) {
                    let html = '';
                    let grandSum = 0;
                    let balanceSum = 0;
                    res.data.forEach(inv => {
                        grandSum += parseFloat(inv.total) || 0;
                        balanceSum += parseFloat(inv.balance) || 0;
                        html += `
                        <tr class="inv-row">
                            <td class="px-4 py-3 text-sm text-gray-600">${inv.voucher_no}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">${inv.due_date}</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-600">${parseFloat(inv.total).toFixed(2)}</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-900 font-bold">${parseFloat(inv.balance).toFixed(2)}</td>
                            <td class="px-4 py-3 text-right">
                                <input type="number" step="0.01" class="w-24 p-1 rounded border-gray-300 text-right pay-input" data-id="${inv.id}" data-max="${inv.balance}" value="0">
                            </td>
                        </tr>
                    `;
                    });
                    $('#invoice-list').html(html);
                    $('#total-grand-sum').text(grandSum.toLocaleString('en-US', { minimumFractionDigits: 2 }));
                    $('#total-balance-sum').text(balanceSum.toLocaleString('en-US', { minimumFractionDigits: 2 }));
                    $('#invoice-tfoot').removeClass('hidden');
                } else {
                    $('#invoice-list').html('<tr><td colspan="5" class="px-4 py-6 text-center text-gray-500 italic">No due invoices found for this customer.</td></tr>');
                    $('#invoice-tfoot').addClass('hidden');
                }
            });
        });

        // Toggle Account Required based on Payment Type
        $('#pay_type').on('change', function () {
            const typeText = $(this).find('option:selected').text().toLowerCase();
            if (typeText.includes('bank') || typeText.includes('cheque') || typeText.includes('check')) {
                $('#pay_account_id').prop('required', true);
                $('#deposit-asterisk').removeClass('hidden');
            } else {
                $('#pay_account_id').prop('required', false);
                $('#deposit-asterisk').addClass('hidden');
            }
        }).trigger('change');

        // Calculate Total Payment
        $(document).on('input', '.pay-input', function () {
            let total = 0;
            $('.pay-input').each(function () {
                total += parseFloat($(this).val()) || 0;
            });
            $('#total-pay-display').text(total.toLocaleString('en-US', { minimumFractionDigits: 2 }));
            $('#total_payment_input').val(total);
        });

        // Save Payment
        $('#payment-form').on('submit', function (e) {
            e.preventDefault();
            const total = parseFloat($('#total_payment_input').val());
            if (total <= 0) {
                Swal.fire('Error', 'Payment amount must be greater than zero.', 'error');
                return;
            }

            const btn = $('#save-pay-btn');
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

            // Collect invoice payments
            let invoicePayments = {};
            $('.pay-input').each(function () {
                let val = parseFloat($(this).val()) || 0;
                if (val > 0) {
                    invoicePayments[$(this).data('id')] = val;
                }
            });

            let formData = $(this).serializeArray();
            // Add invoice payments to formData
            for (let saleId in invoicePayments) {
                formData.push({ name: `invoice_payments[${saleId}]`, value: invoicePayments[saleId] });
            }

            $.post(ajaxurl, formData, function (res) {
                if (res.success) {
                    Swal.fire('Success', res.data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', res.data.message, 'error');
                    btn.prop('disabled', false).text('Save Payment');
                }
            });
        });

        // Edit Payment
        window.editPayment = function (p) {
            resetPayForm();
            $('#payment-modal').removeClass('hidden');
            $('#modal-title').text('Edit Payment Record');
            $('#pay_id').val(p.id);
            $('#pay_customer_id').val(p.customer_id).trigger('change');
            $('#pay_date').val(p.payment_date);
            $('#pay_reference').val(p.reference_no);
            $('#pay_account_id').val(p.account_id).trigger('change');
            $('#pay_type').val(p.payment_type);
            $('#pay_note').val(p.payment_note);
            $('#total-pay-display').text(parseFloat(p.payment).toLocaleString('en-US', { minimumFractionDigits: 2 }));
            $('#total_payment_input').val(p.payment);

            // Note: For editing, we might want to show the amount in the first available row or handle it specifically
            // but for now, we'll just allow the user to re-distribute if they want.
        };

        // Delete Payment
        window.deletePayment = function (id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This action cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#1569B3',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post(ajaxurl, {
                        action: 'frontend_delete_customer_pay',
                        id: id,
                        security: nonce
                    }, function (res) {
                        if (res.success) {
                            Swal.fire('Deleted!', 'Payment record deleted.', 'success').then(() => location.reload());
                        }
                    });
                }
            });
        };

        // Toggle Status
        $('.toggle-pay-status').on('click', function () {
            let btn = $(this);
            let id = btn.data('id');
            let current = btn.data('status');
            let newStatus = current == 1 ? 0 : 1;

            btn.css('opacity', '0.5').css('pointer-events', 'none');
            $.post(ajaxurl, {
                action: 'frontend_update_customer_pay_status',
                id: id,
                status: newStatus,
                security: nonce
            }, function (res) {
                btn.css('opacity', '1').css('pointer-events', 'auto');
                if (res.success) {
                    btn.data('status', newStatus);
                    let bg = btn.find('div').first();
                    let dot = btn.find('div').last();

                    if (newStatus == 1) {
                        bg.removeClass('bg-gray-200').addClass('bg-green-500');
                        dot.addClass('translate-x-5');
                    } else {
                        bg.removeClass('bg-green-500').addClass('bg-gray-200');
                        dot.removeClass('translate-x-5');
                    }
                }
            });
        });

        // Client-side Search
        $('#paySearchInput').on('keyup', function () {
            var value = $(this).val().toLowerCase();
            $("#payment-table tbody tr").filter(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        // Print List
        $('#printPayBtn').on('click', function () {
            window.print();
        });
    });
</script>

<style>
    @media print {

        #inventory-sidebar,
        #inventory-mobile-header,
        .search-filter-bar,
        #create-pay-btn,
        .p-4.border-b,
        th:last-child,
        td:last-child,
        #payment-modal {
            display: none !important;
        }

        main {
            padding: 0 !important;
        }

        .bg-white {
            box-shadow: none !important;
            border: none !important;
        }

        table {
            width: 100% !important;
            border-collapse: collapse !important;
        }

        th,
        td {
            border: 1px solid #eee !important;
        }
    }
</style>
