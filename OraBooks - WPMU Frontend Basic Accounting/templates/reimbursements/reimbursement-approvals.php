<?php if (!defined('ABSPATH')) exit;
global $wpdb;
$reimb_table = $wpdb->prefix . 'orabooks_reimbursements';
$coa_table = $wpdb->prefix . 'orabooks_ac_coa_list';
$pending = $wpdb->get_results("SELECT r.*, u.display_name as employee_name FROM $reimb_table r JOIN {$wpdb->base_prefix}users u ON r.employee_id = u.ID WHERE r.status = 'Submitted' ORDER BY r.date ASC");
$approved = $wpdb->get_results("SELECT r.*, u.display_name as employee_name FROM $reimb_table r JOIN {$wpdb->base_prefix}users u ON r.employee_id = u.ID WHERE r.status = 'Approved' ORDER BY r.date ASC");
$accounts = $wpdb->get_results("SELECT id, account_name, account_code FROM $coa_table WHERE status = 1 ORDER BY account_name ASC");
$reimb_nonce = wp_create_nonce('obn_reimbursement_nonce');
?>
<div class="obn-card p-6 !pt-4">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-2xl font-bold text-gray-800">Reimbursement Management</h3>
    </div>

    <!-- Tabs & Search -->
    <div class="flex flex-col md:flex-row justify-between items-end mb-6 gap-4">
        <div class="flex border-b border-gray-200 w-full md:w-auto" id="reimb-mgmt-tabs">
            <button class="px-6 py-3 border-b-2 border-blue-600 text-blue-600 font-bold tab-btn active" data-tab="pending">
                Pending Approvals (<?php echo count($pending); ?>)
            </button>
            <button class="px-6 py-3 border-b-2 border-transparent text-gray-500 font-bold tab-btn" data-tab="approved">
                To Pay (<?php echo count($approved); ?>)
            </button>
        </div>
        
        <div class="relative w-full md:w-80">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="search" id="reimb-mgmt-search" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="Search approvals...">
        </div>
    </div>

    <!-- Pending Tab -->
    <div id="tab-pending" class="tab-content transition-all duration-300">
        <div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Submitted By</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Ref #</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Date</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-700">Amount</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($pending):
                        foreach ($pending as $r): ?>
                            <tr class="hover:bg-gray-50 transition border-b">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-800"><?php echo esc_html($r->employee_name); ?></div>
                                </td>
                                <td class="px-4 py-3 font-mono text-blue-600"><?php echo esc_html($r->reimbursement_no); ?>
                                </td>
                                <td class="px-4 py-3 text-gray-600"><?php echo date('d-m-Y', strtotime($r->date)); ?></td>
                                <td class="px-4 py-3 text-right font-bold text-gray-900">
                                    <?php echo number_format($r->total_amount, 2); ?></td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <button onclick="obn_view_reimb_details(<?php echo $r->id; ?>)"
                                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded text-xs font-bold transition">View
                                        Details</button>
                                    <button onclick="obn_approve_reimb_modal(<?php echo $r->id; ?>)"
                                        class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded text-xs font-bold transition">Approve</button>
                                    <button onclick="obn_reject_reimb(<?php echo $r->id; ?>)"
                                        class="bg-rose-600 hover:bg-rose-700 text-white px-3 py-1.5 rounded text-xs font-bold transition">Reject</button>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-gray-500 italic">No pending requests.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Approved/To Pay Tab -->
    <div id="tab-approved" class="tab-content hidden transition-all duration-300">
        <div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Employee</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Ref #</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Approved Date</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-700">Amount</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($approved):
                        foreach ($approved as $r): ?>
                            <tr class="hover:bg-gray-50 transition border-b">
                                <td class="px-4 py-3 font-bold text-gray-800"><?php echo esc_html($r->employee_name); ?></td>
                                <td class="px-4 py-3 font-mono text-emerald-600"><?php echo esc_html($r->reimbursement_no); ?>
                                </td>
                                <td class="px-4 py-3 text-gray-600"><?php echo date('d-m-Y', strtotime($r->approved_at)); ?>
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-gray-900">
                                    <?php echo number_format($r->total_amount, 2); ?></td>
                                <td class="px-4 py-3 text-right">
                                    <button onclick="obn_pay_reimb_modal(<?php echo $r->id; ?>)"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded text-xs font-bold transition">Process
                                        Payment</button>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-gray-500 italic">No approved requests waiting
                                for payment.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Container (Reuse for Approve and Pay) -->
<div id="reimb-action-modal"
    class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[1000] p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden" id="modal-container">
        <div id="modal-header" class="p-6 text-white flex justify-between items-center">
            <h3 class="text-xl font-bold" id="modal-title">Action</h3>
            <button class="close-modal text-white text-2xl">&times;</button>
        </div>
        <form id="reimb-action-form" class="p-6 space-y-4">
            <input type="hidden" name="action" id="reimb_ajax_action">
            <input type="hidden" name="security" value="<?php echo $reimb_nonce; ?>">
            <input type="hidden" name="id" id="reimb_id_input">

            <div id="modal-body-fields">
                <!-- Dynamic Fields -->
            </div>

            <div class="flex gap-3 pt-4">
                <button type="submit" id="submit-btn"
                    class="flex-1 text-white font-bold py-3 rounded-lg shadow-md transition-all">Confirm</button>
                <button type="button"
                    class="close-modal flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3 rounded-lg transition">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Detailed View Modal -->
<div id="obn-view-detail-modal"
    class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[1010] p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden transform transition-all">
        <div class="bg-gray-800 p-6 text-white flex justify-between items-center">
            <h3 class="text-xl font-bold">Reimbursement Details</h3>
            <button class="close-detail-modal text-white text-2xl">&times;</button>
        </div>
        <div class="p-6 max-h-[80vh] overflow-y-auto" id="detail-modal-body">
            <div class="text-center py-10"><i class="fa-solid fa-spinner fa-spin text-4xl text-blue-500"></i></div>
        </div>
        <div class="p-6 border-t bg-gray-50 flex justify-end">
            <button
                class="close-detail-modal bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded font-bold transition">Close</button>
        </div>
    </div>
</div>

<script>
    // Move functions outside of jQuery(document).ready for global availability
    window.obn_view_reimb_details = function (id) {
        const $ = jQuery;
        $('#detail-modal-body').html('<div class="text-center py-10"><i class="fa-solid fa-spinner fa-spin text-4xl text-blue-500"></i></div>');
        $('#obn-view-detail-modal').removeClass('hidden').addClass('flex');

        $.post(obn_ajax.ajax_url, {
            action: 'obn_get_reimbursement',
            id: id,
            security: '<?php echo $reimb_nonce; ?>'
        }, function (res) {
            if (res.success) {
                const r = res.data;
                let itemsHtml = '';
                r.items.forEach(item => {
                    itemsHtml += `
                        <tr class="border-b">
                            <td class="py-3">${item.date}</td>
                            <td class="py-3 text-gray-600">${item.description}</td>
                            <td class="py-3 text-right font-bold">${parseFloat(item.amount).toFixed(2)}</td>
                        </tr>
                    `;
                });

                let attachmentsHtml = '';
                if (r.attachments && r.attachments.length > 0) {
                    r.attachments.forEach(att => {
                        attachmentsHtml += `
                            <a href="${att.file_url}" target="_blank" class="flex items-center p-2 bg-blue-50 rounded border border-blue-100 hover:bg-blue-200 transition">
                                <i class="fa-solid fa-file-invoice text-blue-600 mr-2 text-xl"></i>
                                <span class="text-xs font-semibold text-blue-800 truncate">${att.file_name}</span>
                            </a>
                        `;
                    });
                } else {
                    attachmentsHtml = '<p class="text-gray-400 italic text-sm">No attachments found.</p>';
                }

                $('#detail-modal-body').html(`
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <p class="text-xs text-gray-400 uppercase font-bold tracking-wider">Submitted By</p>
                            <p class="font-bold text-gray-800">User ID: ${r.employee_id}</p>
                            <p class="text-sm text-gray-500">Ref: ${r.reimbursement_no}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase font-bold tracking-wider">Date</p>
                            <p class="text-gray-800">${r.date}</p>
                        </div>
                    </div>
                    <div class="mb-6">
                        <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-2">Itemized List</p>
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr><th class="py-2 px-2">Date</th><th class="py-2 px-2">Description</th><th class="py-2 px-2 text-right">Amount</th></tr>
                            </thead>
                            <tbody>${itemsHtml}</tbody>
                            <tfoot>
                                <tr class="font-bold text-lg"><td colspan="2" class="py-4 text-right pr-4">Grand Total</td><td class="py-4 text-right text-blue-700">${parseFloat(r.total_amount).toFixed(2)}</td></tr>
                            </tfoot>
                        </table>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-2">Supporting Documents</p>
                        <div class="grid grid-cols-2 gap-2">${attachmentsHtml}</div>
                    </div>
                `);
            } else {
                $('#detail-modal-body').html('<div class="text-rose-600 font-bold text-center p-10">Failed to load reimbursement details.</div>');
            }
        }, 'json').fail(function () {
            $('#detail-modal-body').html('<div class="text-rose-600 font-bold text-center p-10">Server communication failed.</div>');
        });
    };

    window.obn_approve_reimb_modal = function (id) {
        const $ = jQuery;
        $('#reimb_ajax_action').val('obn_approve_reimbursement');
        $('#reimb_id_input').val(id);
        $('#modal-title').text('Approve Reimbursement');
        $('#modal-header').removeClass('bg-blue-600').addClass('bg-emerald-600');
        $('#submit-btn').addClass('bg-emerald-600 hover:bg-emerald-700').removeClass('bg-blue-600 hover:bg-blue-700');

        $('#modal-body-fields').html(`
            <div class="bg-emerald-50 p-3 rounded border border-emerald-100 mb-4">
                <p class="text-xs text-emerald-800">Approving will post a Journal Entry to record this expense and liability.</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Expense Account (Debit)</label>
                <select name="expense_account_id" class="w-full p-2.5 border rounded-lg focus:ring-2 focus:ring-emerald-400 outline-none" required>
                    <option value="">— Select Expense Account —</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?php echo $acc->id; ?>"><?php echo esc_html($acc->account_name . ' (' . $acc->account_code . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Payable Account (Credit)</label>
                <select name="payable_account_id" class="w-full p-2.5 border rounded-lg focus:ring-2 focus:ring-emerald-400 outline-none" required>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?php echo $acc->id; ?>" <?php echo ($acc->account_code == '2100') ? 'selected' : ''; ?>>
                            <?php echo esc_html($acc->account_name . ' (' . $acc->account_code . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        `);
        $('#reimb-action-modal').removeClass('hidden').addClass('flex');
    };

    window.obn_pay_reimb_modal = function (id) {
        const $ = jQuery;
        $('#reimb_ajax_action').val('obn_pay_reimbursement');
        $('#reimb_id_input').val(id);
        $('#modal-title').text('Process Payment');
        $('#modal-header').removeClass('bg-emerald-600').addClass('bg-blue-600');
        $('#submit-btn').addClass('bg-blue-600 hover:bg-blue-700').removeClass('bg-emerald-600 hover:bg-emerald-700');

        $('#modal-body-fields').html(`
            <div class="bg-blue-50 p-3 rounded border border-blue-100 mb-4">
                <p class="text-xs text-blue-800">Select the payment source to settle the liability (Debit Payable, Credit Bank/Cash).</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Payment Source (Credit)</label>
                <select name="payment_account_id" class="w-full p-2.5 border rounded-lg focus:ring-2 focus:ring-blue-400 outline-none" required>
                    <option value="">— Select Bank or Cash Account —</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?php echo $acc->id; ?>"><?php echo esc_html($acc->account_name . ' (' . $acc->account_code . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="payable_account_id" value="<?php echo $wpdb->get_var("SELECT id FROM $coa_table WHERE account_code = '2100'"); ?>">
        `);
        $('#reimb-action-modal').removeClass('hidden').addClass('flex');
    };

    window.obn_reject_reimb = function (id) {
        const $ = jQuery;
        Swal.fire({
            title: 'Reject Reimbursement?',
            text: 'Enter the reason for rejection:',
            input: 'textarea',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, Reject',
            preConfirm: (note) => {
                if (!note) {
                    Swal.showValidationMessage('Rejection reason is required');
                }
                return note;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(obn_ajax.ajax_url, {
                    action: 'obn_reject_reimbursement',
                    id: id,
                    note: result.value,
                    security: '<?php echo $reimb_nonce; ?>'
                }, function (res) {
                    if (typeof res === 'string') { try { res = JSON.parse(res); } catch (e) { } }

                    if (res && res.success) {
                        Swal.fire({
                            title: 'Returned to Draft',
                            text: 'Request has been sent back to the employee as draft.',
                            icon: 'success',
                            confirmButtonColor: '#1569B3'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', (res && res.data) ? res.data : 'Failed to reject request.', 'error');
                    }
                }, 'json').fail(function () {
                    Swal.fire('Error', 'Server communication failed.', 'error');
                });
            }
        });
    };

    jQuery(document).ready(function ($) {
        // Tab switching
        $('.tab-btn').click(function () {
            const tab = $(this).data('tab');
            $('.tab-btn').removeClass('active border-blue-600 text-blue-600').addClass('border-transparent text-gray-500');
            $(this).addClass('active border-blue-600 text-blue-600').removeClass('border-transparent text-gray-500');
            $('.tab-content').addClass('hidden');
            $('#tab-' + tab).removeClass('hidden');
        });

        $('.close-detail-modal').click(function () {
            $('#obn-view-detail-modal').addClass('hidden').removeClass('flex');
        });

        $('.close-modal').click(function () {
            $('#reimb-action-modal').addClass('hidden').removeClass('flex');
        });

        $('#reimb-action-form').submit(function (e) {
            e.preventDefault();
            const data = $(this).serialize();
            const $btn = $('#submit-btn');
            $btn.prop('disabled', true).text('Processing...');

            $.post(obn_ajax.ajax_url, data, function (res) {
                if (typeof res === 'string') { try { res = JSON.parse(res); } catch (e) { } }

                if (res && res.success) {
                    const msg = (res.data && res.data.message) ? res.data.message : 'Action completed successfully.';
                    Swal.fire({
                        title: 'Success!',
                        text: msg,
                        icon: 'success',
                        confirmButtonColor: '#059669',
                        confirmButtonText: 'OK'
                    }).then(() => location.reload());
                } else {
                    const errorMsg = (res && res.data) ? (typeof res.data === 'string' ? res.data : (res.data.message || 'Action failed.')) : 'Action failed.';
                    Swal.fire('Error', errorMsg, 'error');
                }
                $btn.prop('disabled', false).text('Confirm');
            }, 'json').fail(function () {
                Swal.fire('Error', 'Server communication failed.', 'error');
                $btn.prop('disabled', false).text('Confirm');
            });
        });

        // Search Implementation
        $('#reimb-mgmt-search').on('keyup', function () {
            const val = $(this).val().toLowerCase();
            $('.tab-content table tbody tr').filter(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
            });
        });
    });
</script>
