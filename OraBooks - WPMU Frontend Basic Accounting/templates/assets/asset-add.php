<?php
if ( ! defined( 'ABSPATH' ) ) exit;
global $wpdb;
$coa_table = $wpdb->prefix . 'orabooks_ac_coa_list';
$methods_table = $wpdb->prefix . 'orabooks_ac_depreciation_methods';
$paymenttypes_table = $wpdb->prefix . 'orabooks_db_paymenttypes';
$accounts_table = $wpdb->prefix . 'orabooks_ac_accounts';
$accounts = $wpdb->get_results("SELECT id, account_name, account_code FROM $coa_table WHERE status = 1 ORDER BY account_name ASC");
$methods = $wpdb->get_results("SELECT id, name, slug FROM $methods_table WHERE status = 1 ORDER BY name ASC");
$payment_types = $wpdb->get_results("SELECT id, payment_type FROM $paymenttypes_table WHERE status = 1 ORDER BY payment_type ASC");
$bank_accounts = $wpdb->get_results("SELECT id, account_name, account_code FROM $accounts_table WHERE status = 1 ORDER BY id ASC");

$nonce = wp_create_nonce('obn_assets_action_nonce');
?>

<div class="obn-card p-6 !pt-4">
    <!-- Header Section -->
    <div class="mb-6 text-center">
        <div class="inline-flex items-center justify-center w-14 h-14 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full mb-3">
            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
        </div>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">Acquire New Asset</h3>
        <p class="text-gray-600 text-sm">Add a new fixed asset to your accounting system</p>
    </div>

    <form id="obn-asset-add-form" class="bg-white rounded-xl border border-gray-200 shadow-lg max-w-5xl mx-auto overflow-hidden">
        <input type="hidden" name="action" value="obn_insert_asset">
        <input type="hidden" name="security" value="<?php echo $nonce; ?>">

        <!-- Basic Information Section -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-4 border-b border-gray-200">
            <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Basic Information
            </h4>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Asset Name -->
                <div class="form-group">
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        Asset Name <span class="text-red-500 ml-1">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <input type="text" name="name" class="w-full pl-4 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200" placeholder="e.g. MacBook Pro M3" required>
                    </div>
                </div>

                <!-- Asset Category -->
                <div class="form-group">
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        Asset Category <span class="text-red-500 ml-1">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                        </div>
                        <select name="category" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 appearance-none" required>
                            <option value="">- Select Asset Category -</option>
                            <?php
                            global $wpdb;
                            $category_table = $wpdb->prefix . 'orabooks_ac_asset_category';
                            $categories = $wpdb->get_results("SELECT id, category_name FROM $category_table WHERE status = 1 ORDER BY category_name ASC");
                            foreach ($categories as $cat):
                            ?>
                                <option value="<?php echo esc_attr($cat->id); ?>">
                                    <?php echo esc_html($cat->category_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Purchase Date -->
                <div class="form-group">
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        Purchase Date <span class="text-red-500 ml-1">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <input type="date" name="purchase_date" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200" required>
                    </div>
                </div>

                <!-- Cost Value -->
                <div class="form-group">
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        Cost Value <span class="text-red-500 ml-1">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                        <input type="number" step="0.0" name="cost" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200" placeholder="0.00" required>
                    </div>
                </div>
            </div>
        </div>

        <!-- Depreciation Section -->
        <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-4 border-b border-gray-200">
            <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                Depreciation Details
            </h4>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Salvage Value -->
                <div class="form-group">
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        Salvage Value
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                        <input type="number" name="salvage_value" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                    </div>
                </div>

                <!-- Useful Life -->
                <div class="form-group">
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        Useful Life (Years) <span class="text-red-500 ml-1">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <input type="number" name="useful_life_years" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200" placeholder="e.g. 5" required>
                    </div>
                </div>

                <!-- Depreciation Method -->
                <div class="form-group">
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        Depreciation Method
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                        <select name="depreciation_method" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 appearance-none">
                            <?php foreach($methods as $m) echo '<option value="'.$m->slug.'">'.esc_html($m->name).'</option>'; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accounts Section -->
        <div class="bg-gradient-to-r from-purple-50 to-pink-50 px-6 py-4 border-b border-gray-200">
            <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                </svg>
                Chart of Accounts
            </h4>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <!-- Asset Account -->
                <div class="form-group">
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        Asset Account <span class="text-red-500 ml-1">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <select name="asset_account_id" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 appearance-none" required>
                            <option value="">Select Account</option>
                            <?php foreach($accounts as $acc) echo '<option value="'.$acc->id.'">'.esc_html($acc->account_name).'</option>'; ?>
                        </select>
                    </div>
                </div>

                <!-- Depreciation Expense Account -->
                <div class="form-group">
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        Depr. Expense Account <span class="text-red-500 ml-1">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <select name="depr_expense_account_id" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 appearance-none" required>
                            <option value="">Select Account</option>
                            <?php foreach($accounts as $acc) echo '<option value="'.$acc->id.'">'.esc_html($acc->account_name).'</option>'; ?>
                        </select>
                    </div>
                </div>

                <!-- Accumulated Depreciation Account -->
                <div class="form-group">
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        Accum. Depr. Account <span class="text-red-500 ml-1">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                        <select name="accum_depr_account_id" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 appearance-none" required>
                            <option value="">Select Account</option>
                            <?php foreach($accounts as $acc) echo '<option value="'.$acc->id.'">'.esc_html($acc->account_name).'</option>'; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Information Section -->
        <div class="bg-gradient-to-r from-amber-50 to-orange-50 px-6 py-4 border-b border-gray-200">
            <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                <svg class="w-5 h-5 mr-2 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                </svg>
                Payment Information
            </h4>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Payment Type -->
                <div class="form-group">
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        Payment Type
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                            </svg>
                        </div>
                        <select name="payment_type_id" id="payment_type_id" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 appearance-none">
                            <option value="">Select Payment Type</option>
                            <?php foreach($payment_types as $pt) echo '<option value="'.$pt->id.'">'.esc_html($pt->payment_type).'</option>'; ?>
                        </select>
                    </div>
                </div>

                <!-- Bank Account -->
                <div id="bank_account_field" class="form-group" style="display: none;">
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        Bank Account
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <select name="bank_account_id" id="bank_account_id" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 appearance-none">
                            <option value="">Select Bank Account</option>
                            <?php foreach($bank_accounts as $acc) echo '<option value="'.$acc->id.'">'.esc_html($acc->account_name.' ('.$acc->account_code.')').'</option>'; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row gap-3 justify-end">
            <button type="button" onclick="obn_switch_view('asset-list')" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg font-medium transition-all duration-200 transform hover:scale-105">
                <span class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Cancel
                </span>
            </button>
            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white rounded-lg font-medium transition-all duration-200 transform hover:scale-105 shadow-lg">
                <span class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Save & Record Asset
                </span>
            </button>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle payment type change to show/hide bank account field with smooth animation
    $('#payment_type_id').on('change', function() {
        var selectedPaymentType = $(this).find('option:selected').text().toLowerCase();
        var bankAccountField = $('#bank_account_field');
        
        if (selectedPaymentType.includes('bank') || selectedPaymentType.includes('check')) {
            bankAccountField.slideDown(300, function() {
                $('#bank_account_id').prop('required', true);
            });
        } else {
            bankAccountField.slideUp(300, function() {
                $('#bank_account_id').prop('required', false).val('');
            });
        }
    });

    // Form validation helper
    function validateForm($form) {
        let isValid = true;
        const errors = [];

        // Clear previous error states
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.error-message').remove();

        // Validate required fields
        $form.find('[required]').each(function() {
            const $field = $(this);
            const value = $field.val().trim();
            
            if (!value) {
                isValid = false;
                $field.addClass('is-invalid');
                const label = $field.prev('label').text().replace('*', '').trim();
                errors.push(`${label} is required`);
            }
        });

        // Validate numeric fields
        $form.find('input[type="number"]').each(function() {
            const $field = $(this);
            const value = parseFloat($field.val());
            const label = $field.prev('label').text().replace('*', '').trim();
            
            if (!isNaN(value) && value < 0) {
                isValid = false;
                $field.addClass('is-invalid');
                errors.push(`${label} must be a positive number`);
            }
        });

        // Validate cost vs salvage value
        // const cost = parseFloat($form.find('input[name="cost"]').val());
        // const salvage = parseFloat($form.find('input[name="salvage_value"]').val());
        
        // if (!isNaN(cost) && !isNaN(salvage) && salvage > cost) {
        //     isValid = false;
        //     $form.find('input[name="salvage_value"]').addClass('is-invalid');
        //     errors.push('Salvage value cannot be greater than cost value');
        // }

        return { isValid, errors };
    }

    // Show validation errors
    function showErrors(errors) {
        const errorHtml = `
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                <div class="flex items-start">
                    <svg class="w-5 h-5 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <p class="font-semibold">Please correct the following errors:</p>
                        <ul class="mt-1 list-disc list-inside text-sm">
                            ${errors.map(error => `<li>${error}</li>`).join('')}
                        </ul>
                    </div>
                </div>
            </div>
        `;
        
        $('#obn-asset-add-form').prepend(errorHtml);
        
        // Scroll to top of form to show errors
        $('html, body').animate({
            scrollTop: $('#obn-asset-add-form').offset().top - 100
        }, 500);
    }

    // Show success message
    function showSuccess(message) {
        const successHtml = `
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="font-medium">${message}</span>
                </div>
            </div>
        `;
        
        $('#obn-asset-add-form').prepend(successHtml);
    }

    // Handle form submission
    $(document).on('submit', '#obn-asset-add-form', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        
        // Remove any existing messages
        $form.find('.bg-red-50, .bg-green-50').remove();
        
        // Validate form
        const validation = validateForm($form);
        
        if (!validation.isValid) {
            showErrors(validation.errors);
            return;
        }
        
        // Show loading state
        const originalContent = $submitBtn.html();
        $submitBtn.prop('disabled', true).addClass('opacity-75 cursor-not-allowed').html(`
            <span class="flex items-center">
                <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Saving Asset...
            </span>
        `);

        // Submit form via AJAX
        $.ajax({
            url: obn_ajax.ajax_url,
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            success: function(response) {
                console.log('Response:', response);
                
                if (response.success) {
                    showSuccess(response.data.message);
                    
                    // Redirect after a short delay
                    setTimeout(function() {
                        window.location.hash = 'view=asset-list';
                        location.reload();
                    }, 1500);
                } else {
                    // Restore button and show error
                    $submitBtn.prop('disabled', false).removeClass('opacity-75 cursor-not-allowed').html(originalContent);
                    showErrors([response.data || 'An error occurred while saving the asset.']);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                
                // Restore button and show error
                $submitBtn.prop('disabled', false).removeClass('opacity-75 cursor-not-allowed').html(originalContent);
                showErrors(['A network error occurred. Please check your connection and try again.']);
            }
        });
    });

    // Add input styling for focus/blur events
    $('input, select').on('focus', function() {
        $(this).parent().addClass('focused');
    }).on('blur', function() {
        $(this).parent().removeClass('focused');
    });

    // Auto-format numeric inputs
    // $('input[type="number"]').on('input', function() {
    //     const value = $(this).val();
    //     if (value && !isNaN(value)) {
    //         // Format to 2 decimal places for monetary fields
    //         if ($(this).attr('name') === 'cost' || $(this).attr('name') === 'salvage_value') {
    //             $(this).val(parseFloat(value).toFixed(2));
    //         }
    //     }
    // });

    // Add CSS for validation states
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .is-invalid {
                border-color: #ef4444 !important;
                box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
            }
            .is-invalid:focus {
                border-color: #ef4444 !important;
                box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2) !important;
            }
            .focused {
                transform: translateY(-1px);
            }
            .animate-spin {
                animation: spin 1s linear infinite;
            }
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `)
        .appendTo('head');
});
</script>
