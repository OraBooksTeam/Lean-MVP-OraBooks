<?php
if (!defined('ABSPATH')) exit;

// Guard: Only render once to prevent duplicate IDs when multiple templates include this file
if (defined('ADD_BANK_ACCOUNT_MODAL_RENDERED')) {
    return;
}
define('ADD_BANK_ACCOUNT_MODAL_RENDERED', true);

$nonce = wp_create_nonce('frontend_ajax_nonce');

// Fetch all existing accounts for Parent Account select
global $wpdb;
$accounts_table = $wpdb->prefix . 'orabooks_ac_accounts';
$existing_accounts = $wpdb->get_results("SELECT id, account_name, account_code FROM $accounts_table WHERE (status = 1 OR status IS NULL) ORDER BY account_code ASC");
?>
<!-- Add Bank Account Modal -->
<div id="add-bank-account-modal" class="fixed inset-0 z-[999999] hidden items-center justify-center" aria-labelledby="add-bank-account-modal-title" role="dialog" aria-modal="true">
    <!-- Background overlay -->
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-opacity" aria-hidden="true" id="add-bank-account-modal-overlay"></div>

    <!-- Modal panel -->
    <div class="relative inline-block align-middle bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all w-full max-w-md mx-auto border border-gray-100">
        <form id="add-bank-account-form">
            <input type="hidden" name="action" value="frontend_insert_account">
            <input type="hidden" name="security" value="<?php echo esc_attr($nonce); ?>">

            <div class="bg-white px-6 py-5">
                <div class="flex items-center justify-between mb-6 border-b border-gray-100 pb-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg mr-3">
                            <i class="fa-solid fa-building-columns text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900" id="add-bank-account-modal-title">Add Bank Account</h3>
                            <p class="text-sm text-gray-500 mt-0.5">Enter a new bank account</p>
                        </div>
                    </div>
                    <button type="button" class="close-bank-account-modal w-10 h-10 flex items-center justify-center rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Parent Account</label>
                        <select name="parent_account" id="ba-parent-account"
                            class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2.5 px-3 bg-white">
                            <option value="0">— None (Top Level) —</option>
                            <?php foreach ($existing_accounts as $acc): ?>
                                <option value="<?php echo esc_attr($acc->id); ?>">
                                    <?php echo esc_html($acc->account_code . ' - ' . $acc->account_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Account Name <span class="text-red-500">*</span></label>
                        <input type="text" name="account_name" id="ba-account-name" required
                            class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2.5 px-3"
                            placeholder="e.g. DBBL Account, BRAC Bank...">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Account Code <span class="text-red-500">*</span></label>
                        <input type="text" name="account_code" id="ba-account-code" required maxlength="10"
                            class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2.5 px-3"
                            placeholder="e.g. 1001">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Opening Balance</label>
                        <input type="number" step="0.01" name="opening_balance" id="ba-opening-balance" value="0"
                            class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2.5 px-3"
                            placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Note</label>
                        <textarea name="note" id="ba-note" rows="2"
                            class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2.5 px-3"
                            placeholder="Optional notes..."></textarea>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 px-6 py-4 flex items-center justify-end gap-4 border-t border-gray-100">
                <button type="button" class="close-bank-account-modal px-5 py-2.5 text-sm font-bold text-gray-600 hover:text-gray-900 border border-gray-200 rounded-xl hover:bg-white hover:shadow-sm transition-all">
                    Cancel
                </button>
                <button type="submit" id="save-bank-account-btn" class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl hover:from-blue-700 hover:to-indigo-700 text-sm font-bold shadow-lg shadow-blue-500/30 transition-all flex items-center">
                    <i class="fa-solid fa-check-circle mr-2"></i> Save Account
                </button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var $modal = $('#add-bank-account-modal');
    var $form  = $('#add-bank-account-form');

    // Move modal to body to avoid z-index / overflow clipping issues
    if ($modal.length) {
        $modal.appendTo('body');
    }

    // Open Modal - exposed globally so the Add Sale page can call it
    window.openAddBankAccountModal = function() {
        console.log('Opening Add Bank Account Modal');
        $modal.removeClass('hidden').addClass('flex');
        $('body').addClass('overflow-hidden');
        $form[0].reset();
        $('#ba-account-name').focus();
    };

    // Close Modal
    function closeBankAccountModal() {
        $modal.addClass('hidden').removeClass('flex');
        $('body').removeClass('overflow-hidden');
        $form[0].reset();
    }

    // Use event delegation on body for close buttons (handles modal being moved to body)
    $('body').on('click', '.close-bank-account-modal', function(e) {
        e.preventDefault();
        e.stopPropagation();
        closeBankAccountModal();
    });

    // Close on overlay click
    $('body').on('click', '#add-bank-account-modal-overlay', function(e) {
        e.preventDefault();
        closeBankAccountModal();
    });

    // Close on Escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && !$modal.hasClass('hidden')) {
            closeBankAccountModal();
        }
    });

    // Enter key submits the form
    $('#ba-account-name, #ba-account-code').on('keydown', function(e) {
        if (e.which === 13 || e.keyCode === 13) {
            e.preventDefault();
            $form.submit();
        }
    });

    // Form Submission
    $form.on('submit', function(e) {
        e.preventDefault();
        var btn = $('#save-bank-account-btn');
        var accountName = $('#ba-account-name').val().trim();
        var accountCode = $('#ba-account-code').val().trim();

        if (!accountName) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Required', text: 'Please enter an account name.' });
            } else {
                alert('Please enter an account name.');
            }
            return;
        }
        if (!accountCode) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Required', text: 'Please enter an account code.' });
            } else {
                alert('Please enter an account code.');
            }
            return;
        }

        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Saving...');

        $.ajax({
            url: '<?php echo admin_url("admin-ajax.php"); ?>',
            type: 'POST',
            data: $form.serialize(),
            success: function(res) {
                if (res.success) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Added!',
                            text: res.data.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }

                    // Add the new option to all account select boxes (Add Sale & Edit Sale)
                    var newOption = new Option(res.data.account_code + ' - ' + res.data.account_name, res.data.id, true, true);
                    $('#as-account-id, #account_id').append(newOption).trigger('change');

                    closeBankAccountModal();
                } else {
                    var errMsg = res.data || res.data.message || 'Failed to save account';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: errMsg });
                    } else {
                        alert('Error: ' + errMsg);
                    }
                }
            },
            error: function() {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not connect to server.' });
                } else {
                    alert('Connection error');
                }
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fa-solid fa-check-circle mr-2"></i> Save Account');
            }
        });
    });
});
</script>
