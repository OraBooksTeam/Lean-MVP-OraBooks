<?php
if (!defined('ABSPATH')) exit;

// Guard: Only render once to prevent duplicate IDs when multiple templates include this file
if (defined('SALE_ROW_ACCOUNT_MODAL_RENDERED')) {
    return;
}
define('SALE_ROW_ACCOUNT_MODAL_RENDERED', true);

global $wpdb;
$coa_types    = $wpdb->get_results("SELECT id, coa_type FROM {$wpdb->prefix}orabooks_ac_coa_types WHERE status = 1 ORDER BY coa_type ASC");
$coa_nonce    = wp_create_nonce('obn_accounts_action_nonce');
?>
<!-- COA Quick-Add Modal (for Add Sale row Account) — matches expense-list style -->
<div id="sale-row-coa-add-modal"
     style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:999999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:24px;max-width:480px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);position:relative;display:block;">

        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-bold text-gray-800">Add New Account</h4>
            <button type="button" class="sale-coa-modal-close text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>

        <!-- Form -->
        <form id="sale-coa-quick-add-form" class="flex flex-col gap-4">
            <input type="hidden" name="action" value="obn_insert_coa">
            <input type="hidden" name="security" value="<?php echo esc_attr($coa_nonce); ?>">

            <!-- Account Type -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Account Type <span class="text-red-500">*</span></label>
                <select name="coa_type_id" id="sale-coa-type-id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400" required>
                    <option value="">Select Account Type</option>
                    <?php foreach ($coa_types as $t): ?>
                        <option value="<?php echo esc_attr($t->id); ?>"><?php echo esc_html($t->coa_type); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Account Code -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Account Code <span class="text-red-500">*</span></label>
                <input type="text" name="account_code" id="sale-coa-account-code" maxlength="10"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400"
                    placeholder="e.g. 5010" required>
            </div>

            <!-- Account Name -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Account Name <span class="text-red-500">*</span></label>
                <input type="text" name="account_name" id="sale-coa-account-name" maxlength="150"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400"
                    placeholder="e.g. Office Supplies" required>
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Description</label>
                <textarea name="description" id="sale-coa-description"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400"
                    rows="2" placeholder="Optional"></textarea>
            </div>

            <!-- Buttons -->
            <div class="flex gap-2 justify-end mt-2">
                <button type="button" class="sale-coa-modal-close px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg text-sm font-medium transition">Cancel</button>
                <button type="submit" id="sale-coa-save-btn" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition">Save Account</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function ($) {
    var $modal  = $('#sale-row-coa-add-modal');
    var $form   = $('#sale-coa-quick-add-form');

    // Move modal to body to avoid z-index / overflow clipping issues
    $modal.appendTo('body');

    // Track which row's select triggered the modal
    var $targetRowSelect = null;

    // Expose opener globally so add-sale.php click handler can call it
    window.openRowAccountModal = function ($rowSelect) {
        $targetRowSelect = $rowSelect || null;
        $form[0].reset();
        $modal.css('display', 'flex');
        $('body').addClass('overflow-hidden');
        $('#sale-coa-account-code').focus();
    };

    // Close modal helpers
    function closeSaleCoaModal() {
        $modal.css('display', 'none');
        $('body').removeClass('overflow-hidden');
        $form[0].reset();
        $targetRowSelect = null;
    }

    // Close on button / overlay click
    $(document).on('click', '.sale-coa-modal-close', closeSaleCoaModal);
    $modal.on('click', function (e) {
        // close only if clicking the dark backdrop (not the inner content box)
        if ($(e.target).is($modal)) closeSaleCoaModal();
    });

    // Form submit
    $form.on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#sale-coa-save-btn');

        var coaTypeId   = $('#sale-coa-type-id').val();
        var accountCode = $('#sale-coa-account-code').val().trim();
        var accountName = $('#sale-coa-account-name').val().trim();

        if (!coaTypeId || !accountCode || !accountName) {
            alert('Account Type, Code, and Name are required.');
            return;
        }

        $btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: '<?php echo esc_url(admin_url("admin-ajax.php")); ?>',
            type: 'POST',
            data: $form.serialize(),
            success: function (res) {
                if (res.success) {
                    var newId    = res.data.id;
                    var newLabel = res.data.account_code + ' - ' + res.data.account_name;

                    // Append new option to ALL .row-account-id selects in the page
                    $('.row-account-id').each(function () {
                        $(this).append(new Option(newLabel, newId));
                    });

                    // Select the new option in the triggering row's dropdown
                    if ($targetRowSelect && $targetRowSelect.length) {
                        $targetRowSelect.val(newId).trigger('change');
                    }

                    closeSaleCoaModal();

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'success', title: 'Added!', text: res.data.message, timer: 1500, showConfirmButton: false });
                    }
                } else {
                    var msg = (res.data && typeof res.data === 'string') ? res.data : 'Failed to save account.';
                    alert('Error: ' + msg);
                }
            },
            error: function () {
                alert('Connection error. Please try again.');
            },
            complete: function () {
                $btn.prop('disabled', false).text('Save Account');
            }
        });
    });
});
</script>
