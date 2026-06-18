<?php
global $wpdb;
$acc_table = $wpdb->prefix . 'orabooks_ac_accounts';
$accounts = $wpdb->get_results("SELECT id, account_name FROM {$acc_table} WHERE status=1 ORDER BY account_name ASC");
$mt_nonce = wp_create_nonce('obn_money_transfer_nonce');
?>
<div id="obn-view-money-transfer-edit" class="obn-view-section" style="display:none;">
    <div class="obn-card p-6 !pt-4">
        <h3 class="text-2xl font-bold text-gray-800 mb-6">Edit Money Transfer</h3>
        <form id="obn-money-transfer-edit-form"
            class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm max-w-3xl">
            <input type="hidden" name="action" value="obn_update_money_transfer">
            <input type="hidden" name="security" value="<?php echo esc_attr($mt_nonce); ?>">
            <input type="hidden" id="obn_edit_transfer_id" name="id" value="">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Transfer Code</label>
                    <input type="text" id="obn_edit_transfer_code"
                        class="w-full px-4 py-2 border rounded bg-gray-100 cursor-not-allowed" readonly>
                    <p class="text-xs text-gray-500 mt-1">Auto-generated (cannot be changed)</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Transfer Date <span
                            class="text-red-500">*</span></label>
                    <input type="date" id="obn_edit_transfer_date" name="transfer_date"
                        class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Reference No</label>
                    <input type="text" id="obn_edit_mt_reference_no" name="reference_no" value=""
                        class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Debit Account (Receiver) <span
                            class="text-red-500">*</span></label>
                    <select id="obn_edit_debit_account" name="debit_account_id"
                        class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Select Account</option>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?php echo esc_attr($acc->id); ?>"><?php echo esc_html($acc->account_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Credit Account (Sender) <span
                            class="text-red-500">*</span></label>
                    <select id="obn_edit_credit_account" name="credit_account_id"
                        class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Select Account</option>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?php echo esc_attr($acc->id); ?>"><?php echo esc_html($acc->account_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Amount <span
                            class="text-red-500">*</span></label>
                    <input type="number" step="0.01" id="obn_edit_amount" name="amount"
                        class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500" required>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Note</label>
                    <textarea id="obn_edit_mt_note" name="note" rows="3"
                        class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded shadow transition">Update
                    Transfer</button>
                <button type="button" id="obn-money-transfer-edit-cancel"
                    class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded transition">Cancel</button>
            </div>
        </form>
    </div>
</div>