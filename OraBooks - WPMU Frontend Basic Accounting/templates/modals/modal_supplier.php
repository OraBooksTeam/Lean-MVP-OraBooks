<?php
if (!defined('ABSPATH')) exit;
$nonce = wp_create_nonce('frontend_ajax_nonce');
?>
<!-- Supplier Modal -->
<div id="supplier-modal" class="fixed inset-0 z-[999999] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-opacity" aria-hidden="true" id="supplier-modal-overlay"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full border border-gray-100">
            <form id="supplier-form">
                <input type="hidden" name="action" value="frontend_save_supplier">
                <input type="hidden" name="security" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="id" value="0">
                <input type="hidden" name="store_id" value="1">

                <div class="bg-white px-8 py-6">
                    <div class="flex items-center justify-between mb-8 border-b border-gray-100 pb-5">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-indigo-600 rounded-xl flex items-center justify-center text-white shadow-lg mr-4">
                                <i class="fa-solid fa-truck-field text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900" id="modal-title">Add New Supplier</h3>
                                <p class="text-sm text-gray-500 mt-0.5">Enter supplier details below</p>
                            </div>
                        </div>
                        <button type="button" class="w-10 h-10 flex items-center justify-center rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all close-supplier-modal">
                            <i class="fa-solid fa-times text-xl"></i>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-5">
                        <!-- Section: Basic Info -->
                        <div class="md:col-span-3">
                            <h4 class="text-xs font-bold text-indigo-600 uppercase tracking-[0.1em] mb-1">Basic Information</h4>
                            <div class="h-1 w-12 bg-indigo-600 rounded-full"></div>
                        </div>
                        
                        <div class="md:col-span-1">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Supplier Name <span class="text-red-500">*</span></label>
                            <input type="text" name="supplier_name" required class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 shadow-sm transition-all py-2 px-2.5" placeholder="Supplier Co.">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Mobile Number</label>
                            <input type="text" name="mobile" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 shadow-sm transition-all py-2 px-2.5" placeholder="+1 234 567 890">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Email Address</label>
                            <input type="email" name="email" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 shadow-sm transition-all py-2 px-2.5" placeholder="supplier@example.com">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">GST Number</label>
                            <input type="text" name="gst_number" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 shadow-sm transition-all py-2 px-2.5" placeholder="GSTIN">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Tax Number</label>
                            <input type="text" name="tax_number" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 shadow-sm transition-all py-2 px-2.5" placeholder="Tax ID">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Opening Balance</label>
                            <input type="number" step="0.01" name="opening_balance" value="0.00" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 shadow-sm transition-all py-2 px-2.5">
                        </div>

                        <!-- Section: Address -->
                        <div class="md:col-span-3 mt-4">
                            <h4 class="text-xs font-bold text-emerald-600 uppercase tracking-[0.1em] mb-1">Address Details</h4>
                            <div class="h-1 w-12 bg-emerald-600 rounded-full"></div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Street Address</label>
                            <textarea name="address" rows="2" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 shadow-sm transition-all py-2 px-2.5" placeholder="Street name, Building, Area..."></textarea>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">City</label>
                                <input type="text" name="city" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 shadow-sm transition-all py-2 px-2.5" placeholder="City">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Postcode</label>
                                <input type="text" name="postcode" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 shadow-sm transition-all py-2 px-2.5" placeholder="ZIP Code">
                            </div>
                        </div>
                        
                        <!-- Hidden fields for compatibility -->
                        <input type="hidden" name="country" value="">
                        <input type="hidden" name="state" value="">
                    </div>
                </div>

                <div class="bg-gray-50 px-8 py-5 flex items-center justify-end gap-4 border-t border-gray-100">
                    <button type="button" class="close-supplier-modal px-6 py-2.5 text-sm font-bold text-gray-600 hover:text-gray-900 border border-gray-200 rounded-xl hover:bg-white hover:shadow-sm transition-all">
                        Cancel
                    </button>
                    <button type="submit" id="save-supplier-btn" class="px-8 py-2.5 bg-gradient-to-r from-indigo-600 to-blue-600 text-white rounded-xl hover:from-indigo-700 hover:to-blue-700 text-sm font-bold shadow-lg shadow-indigo-500/30 transition-all flex items-center">
                        <i class="fa-solid fa-check-circle mr-2"></i> Save Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
#supplier-modal {
    z-index: 999999;
}
</style>

<script>
jQuery(document).ready(function($) {
    const modal = $('#supplier-modal');
    const form = $('#supplier-form');

    // Open Modal
    window.openSupplierModal = function() {
        modal.removeClass('hidden');
        $('body').addClass('overflow-hidden');
    };

    // Close Modal
    function closeSupplierModal() {
        modal.addClass('hidden');
        $('body').removeClass('overflow-hidden');
        form[0].reset();
    }

    $('.close-supplier-modal, #supplier-modal-overlay').on('click', closeSupplierModal);

    // Form Submission
    form.on('submit', function(e) {
        e.preventDefault();
        const btn = $('#save-supplier-btn');
        const formData = new FormData(this);

        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Saving...');

        $.ajax({
            url: '<?php echo admin_url("admin-ajax.php"); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                if (res.success) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ 
                            icon: 'success', 
                            title: 'Supplier Added!', 
                            text: res.data.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        alert(res.data.message);
                    }

                    // Update Dropdown in Parent Page (Purchase)
                    if ($('#supplier_id').length) {
                        const newOption = new Option(res.data.supplier_name, res.data.supplier_id, true, true);
                        $('#supplier_id').append(newOption).trigger('change');
                    }

                    closeSupplierModal();
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.data.message || 'Failed to save' });
                    } else {
                        alert('Error: ' + (res.data.message || 'Failed to save'));
                    }
                }
            },
            error: function() {
                alert('Connection error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fa-solid fa-check-circle mr-2"></i> Save Supplier');
            }
        });
    });
});
</script>