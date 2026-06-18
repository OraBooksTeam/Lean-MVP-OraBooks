<?php
if (!defined('ABSPATH')) exit;

// Guard: Only render once to prevent duplicate IDs when multiple templates include this file
if (defined('PAYMENT_TYPE_MODAL_RENDERED')) {
    return;
}
define('PAYMENT_TYPE_MODAL_RENDERED', true);

$nonce = wp_create_nonce('frontend_ajax_nonce');
?>
<!-- Payment Type Modal -->
<div id="payment-type-modal" class="fixed inset-0 z-[999999] hidden overflow-y-auto" aria-labelledby="payment-type-modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-opacity" aria-hidden="true" id="payment-type-modal-overlay"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-gray-100">
            <form id="payment-type-form">
                <input type="hidden" name="action" value="frontend_insert_payment_type">
                <input type="hidden" name="security" value="<?php echo esc_attr($nonce); ?>">

                <div class="bg-white px-6 py-5">
                    <div class="flex items-center justify-between mb-6 border-b border-gray-100 pb-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg mr-3">
                                <i class="fa-solid fa-wallet text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900" id="payment-type-modal-title">Add Payment Type</h3>
                                <p class="text-sm text-gray-500 mt-0.5">Enter a new payment method</p>
                            </div>
                        </div>
                        <button type="button" class="close-payment-type-modal w-10 h-10 flex items-center justify-center rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all">
                            <i class="fa-solid fa-times text-xl"></i>
                        </button>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Payment Type Name <span class="text-red-500">*</span></label>
                        <input type="text" name="payment_type" id="payment-type-name" required
                            class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm transition-all py-2.5 px-3"
                            placeholder="e.g. Cash, Bank, bKash, Nagad...">
                    </div>
                </div>

                <div class="bg-gray-50 px-6 py-4 flex items-center justify-end gap-4 border-t border-gray-100">
                    <button type="button" class="close-payment-type-modal px-5 py-2.5 text-sm font-bold text-gray-600 hover:text-gray-900 border border-gray-200 rounded-xl hover:bg-white hover:shadow-sm transition-all">
                        Cancel
                    </button>
                    <button type="submit" id="save-payment-type-btn" class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl hover:from-blue-700 hover:to-indigo-700 text-sm font-bold shadow-lg shadow-blue-500/30 transition-all flex items-center">
                        <i class="fa-solid fa-check-circle mr-2"></i> Save Payment Type
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const modal = $('#payment-type-modal');
    const form = $('#payment-type-form');

    // Move modal to parent container to avoid being hidden inside page containers while keeping styling intact
    if (modal.length) {
        const parent = modal.closest('.obn-view-section').parent();
        if (parent.length) {
            modal.appendTo(parent);
        } else {
            modal.appendTo('body');
        }
    }

    // Open Modal - exposed globally so the Add Sale page can call it
    window.openPaymentTypeModal = function() {
        modal.removeClass('hidden');
        $('body').addClass('overflow-hidden');
        $('#payment-type-name').val('').focus();
    };

    // Close Modal
    function closePaymentTypeModal() {
        modal.addClass('hidden');
        $('body').removeClass('overflow-hidden');
        form[0].reset();
    }

    $('.close-payment-type-modal, #payment-type-modal-overlay').on('click', closePaymentTypeModal);

    // Enter key submits the form
    $('#payment-type-name').on('keydown', function(e) {
        if (e.which === 13 || e.keyCode === 13) {
            e.preventDefault();
            form.submit();
        }
    });

    // Form Submission
    form.on('submit', function(e) {
        e.preventDefault();
        const btn = $('#save-payment-type-btn');
        const paymentType = $('#payment-type-name').val().trim();

        if (!paymentType) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Required', text: 'Please enter a payment type name.' });
            } else {
                alert('Please enter a payment type name.');
            }
            return;
        }

        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Saving...');

        $.ajax({
            url: '<?php echo admin_url("admin-ajax.php"); ?>',
            type: 'POST',
            data: form.serialize(),
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

                    // Add the new option to all payment type select boxes
                    const newOption = new Option(res.data.payment_type, res.data.id, true, true);
                    $('#as-payment-type-id, #payment_type_id').append(newOption).trigger('change');

                    closePaymentTypeModal();
                } else {
                    const errMsg = res.data || res.data.message || 'Failed to save payment type';
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
                btn.prop('disabled', false).html('<i class="fa-solid fa-check-circle mr-2"></i> Save Payment Type');
            }
        });
    });
});
</script>
