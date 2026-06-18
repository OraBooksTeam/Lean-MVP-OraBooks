<?php
/**
 * Add/Edit Reimbursement Template
 */
if (!defined('ABSPATH'))
    exit;

global $wpdb;
$cat_table = $wpdb->prefix . 'orabooks_db_expense_category';
$categories = $wpdb->get_results("SELECT id, category_name FROM $cat_table WHERE status = 1 ORDER BY category_name ASC");
$reimb_nonce = wp_create_nonce('obn_reimbursement_nonce');
?>

<div class="obn-card p-6 !pt-4">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-2xl font-bold text-gray-800">New Reimbursement Request</h3>
        <button class="obn-dash-link bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded transition"
            data-target="reimbursement-list">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to List
        </button>
    </div>

    <form id="obn-reimbursement-form" class="space-y-6" enctype="multipart/form-data">
        <input type="hidden" name="action" value="obn_save_reimbursement">
        <input type="hidden" name="security" value="<?php echo $reimb_nonce; ?>">
        <input type="hidden" name="id" value="0">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                <label class="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">Request Date</label>
                <input type="date" name="date"
                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-400 outline-none transition"
                    value="<?php echo current_time('Y-m-d'); ?>" required>
            </div>
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                <label class="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">General
                    Description</label>
                <input type="text" name="description"
                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-400 outline-none transition"
                    placeholder="e.g. Travel to HQ, Office Supplies">
            </div>
        </div>

        <!-- Items Table -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left w-48">Date</th>
                        <th class="px-4 py-3 text-left w-64">Category</th>
                        <th class="px-4 py-3 text-left">Description</th>
                        <th class="px-4 py-3 text-right w-40">Amount</th>
                        <th class="px-4 py-3 text-center w-20"></th>
                    </tr>
                </thead>
                <tbody id="reimb-items-body" class="divide-y divide-gray-100">
                    <tr class="reimb-row">
                        <td class="px-3 py-3"><input type="date" name="items[0][date]" class="w-full p-2 border rounded"
                                value="<?php echo current_time('Y-m-d'); ?>" required></td>
                        <td class="px-3 py-3">
                            <select name="items[0][category_id]" class="w-full p-2 border rounded" required>
                                <option value="">— Select —</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat->id; ?>"><?php echo esc_html($cat->category_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="px-3 py-3"><input type="text" name="items[0][description]"
                                class="w-full p-2 border rounded" placeholder="Details..."></td>
                        <td class="px-3 py-3"><input type="number" step="0.01" name="items[0][amount]"
                                class="w-full p-2 border rounded text-right reimb-amount" value="0.00" min="0.01"
                                required></td>
                        <td class="px-3 py-3 text-center"><button type="button"
                                class="text-rose-500 hover:text-rose-700 remove-row" style="display:none;"><i
                                    class="fa-solid fa-trash"></i></button></td>
                    </tr>
                </tbody>
                <tfoot class="bg-gray-50 font-bold">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-right text-gray-700">Total Amount</td>
                        <td class="px-4 py-3 text-right text-blue-700 text-lg" id="reimb-grand-total">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <button type="button" id="add-item-row"
            class="text-blue-600 hover:text-blue-800 font-bold flex items-center gap-2 px-1">
            <i class="fa-solid fa-circle-plus"></i> Add Another Item
        </button>

        <!-- Attachments -->
        <div class="bg-blue-50 p-6 rounded-xl border border-blue-100">
            <h4 class="text-blue-800 font-bold mb-4 flex items-center gap-2">
                <i class="fa-solid fa-paperclip"></i> Receipts & Documents
            </h4>
            <div class="flex items-center justify-center w-full">
                <label
                    class="flex flex-col items-center justify-center w-full h-32 border-2 border-blue-300 border-dashed rounded-lg cursor-pointer bg-white hover:bg-blue-100 transition-all">
                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                        <i class="fa-solid fa-cloud-arrow-up text-3xl mb-3 text-blue-500"></i>
                        <p class="mb-2 text-sm text-gray-700"><span class="font-bold">Click to upload</span> or drag and
                            drop</p>
                        <p class="text-xs text-gray-500">PDF, PNG, JPG (MAX. 5MB per file)</p>
                    </div>
                    <input type="file" name="receipts[]" class="hidden" multiple accept=".pdf,.png,.jpg,.jpeg">
                </label>
            </div>
            <div id="file-list" class="mt-4 grid grid-cols-2 gap-2"></div>
        </div>

        <div class="flex gap-4 pt-4 border-t border-gray-100">
            <button type="submit"
                class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-8 py-3 rounded-lg shadow-md transform hover:scale-105 transition-all">
                Save as Draft
            </button>
            <button type="reset"
                class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-6 py-3 rounded-lg transition">
                Reset Form
            </button>
        </div>
    </form>
</div>

<script>
    jQuery(document).ready(function ($) {
        let rowCount = 1;

        $('#add-item-row').click(function () {
            const newRow = $('.reimb-row').first().clone();
            newRow.find('input').val('');
            newRow.find('.reimb-amount').val('0.00');
            newRow.find('select').val('');
            newRow.find('.remove-row').show();

            // Update names
            newRow.find('input, select').each(function () {
                const name = $(this).attr('name');
                $(this).attr('name', name.replace('[0]', '[' + rowCount + ']'));
            });

            $('#reimb-items-body').append(newRow);
            rowCount++;
        });

        $(document).on('click', '.remove-row', function () {
            $(this).closest('tr').remove();
            window.obn_reimb_calc_total();
        });

        $(document).on('input', '.reimb-amount', function () {
            window.obn_reimb_calc_total();
        });

        window.obn_reimb_calc_total = function () {
            let total = 0;
            $('.reimb-amount').each(function () {
                total += parseFloat($(this).val()) || 0;
            });
            $('#reimb-grand-total').text(total.toFixed(2));
        }

        window.obn_reimb_reset_form = function () {
            const $form = $('#obn-reimbursement-form');
            $form[0].reset();
            $form.find('input[name="id"]').val('0');
            $('#obn-view-reimbursement-add h3').text('New Reimbursement Request');
            $form.find('button[type="submit"]').text('Save as Draft');

            // Reset items to a single empty row
            const $body = $('#reimb-items-body');
            const $firstRow = $body.find('.reimb-row').first().clone();
            $firstRow.find('input').val('');
            $firstRow.find('.reimb-amount').val('0.00');
            $firstRow.find('select').val('');
            $firstRow.find('.remove-row').hide();

            $body.empty().append($firstRow);
            window.obn_reimb_calc_total();
        };

        $('input[type="file"]').change(function (e) {
            const files = e.target.files;
            const list = $('#file-list');
            list.empty();
            for (let i = 0; i < files.length; i++) {
                list.append('<div class="bg-white p-2 rounded border text-xs truncate"><i class="fa-solid fa-file mr-2 text-blue-500"></i>' + files[i].name + '</div>');
            }
        });

        let isSubmitting = false;
        $(document).on('submit', '#obn-reimbursement-form', function (e) {
            e.preventDefault();
            if (isSubmitting) return;

            const $form = $(this);
            const $btn = $form.find('button[type="submit"]');
            const formData = new FormData(this);

            isSubmitting = true;
            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: obn_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (res) {
                    if (typeof res === 'string') {
                        try { res = JSON.parse(res); } catch (e) { }
                    }

                    if (res && res.success) {
                        alert(res.data?.message || 'Reimbursement request saved.');
                        window.location.hash = 'view=reimbursement-list';
                        location.reload();
                    } else {
                        isSubmitting = false;
                        const errorMsg = (res && res.data) ? (typeof res.data === 'string' ? res.data : res.data.message) : 'Failed to save reimbursement.';
                        alert('Error: ' + errorMsg);
                        $btn.prop('disabled', false).text('Save as Draft');
                    }
                },
                error: function (xhr, status, error) {
                    isSubmitting = false;
                    console.error('AJAX Error:', error);
                    alert('Server communication failed. Please check console.');
                    $btn.prop('disabled', false).text('Save as Draft');
                }
            });
        });
    });
</script>