<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
// Fetch Categories
$categories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}orabooks_db_category WHERE status=1 ORDER BY category_name ASC");

// Fetch Taxes
$taxes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}orabooks_db_tax WHERE status=1 ORDER BY tax_name ASC");

// Auto-Generate Service Code (SRV-XXXX)
$table_items = $wpdb->prefix . 'orabooks_db_items';
$last_service = $wpdb->get_row("SELECT count_id FROM $table_items WHERE service_bit=1 ORDER BY id DESC LIMIT 1");
$count_id = ($last_service && $last_service->count_id) ? $last_service->count_id + 1 : 1;
$item_code = 'SRV-' . str_pad($count_id, 6, '0', STR_PAD_LEFT);
?>

<div class="p-6">
    <div class="max-w-6xl mx-auto mb-8">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg">
                <i class="fa-solid fa-hand-holding-dollar text-white text-2xl"></i>
            </div>
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Add New Service</h1>
                <p class="text-gray-500 mt-1">Create a new service entry for your business</p>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    <div id="success-message" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 max-w-6xl mx-auto" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline" id="success-text"></span>
    </div>

    <form id="add-service-form" class="max-w-6xl mx-auto" enctype="multipart/form-data">
        <input type="hidden" name="action" value="frontend_insert_item">
        <input type="hidden" name="security" value="<?php echo wp_create_nonce('frontend_ajax_nonce'); ?>">
        <input type="hidden" name="store_id" value="1">
        <input type="hidden" name="service_bit" value="1">
        <input type="hidden" name="item_type" value="Service">
        <input type="hidden" name="count_id" value="<?php echo esc_attr($count_id); ?>">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Service Information Card -->
            <div class="bg-white rounded-2xl p-6 shadow-xl border border-gray-100">
                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center">
                        <i class="fa-solid fa-info-circle"></i>
                    </div>
                    <h2 class="text-lg font-bold text-gray-800">Service Information</h2>
                </div>

                <div class="space-y-5">
                    <!-- Service Code -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Service Code <span class="text-red-500">*</span></label>
                        <input type="text" name="item_code" value="<?php echo esc_attr($item_code); ?>" readonly
                            class="w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-600 font-mono cursor-not-allowed">
                    </div>

                    <!-- Service Name -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Service Name <span class="text-red-500">*</span></label>
                        <input type="text" name="item_name" required placeholder="Enter service name"
                            class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    </div>

                    <!-- Category -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Category <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <select name="category_id" required
                                class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">— Select Category —</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat->id; ?>"><?php echo esc_html($cat->category_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" data-bs-toggle="modal" data-bs-target="#category_modal" class="bg-gray-200 hover:bg-gray-300 text-gray-600 px-3 py-2 rounded-lg flex items-center justify-center transition-colors" title="Add New Category">
                                <i class="fa fa-plus"></i>
                            </button>
                        </div>
                    </div>

                    <!-- SAC -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">SAC</label>
                        <input type="text" name="sac" placeholder="Service Accounting Code"
                            class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <!-- HSN -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">HSN</label>
                        <input type="text" name="hsn" placeholder="HSN Code"
                            class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <!-- Barcode -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Barcode</label>
                        <input type="text" name="custom_barcode" placeholder="Custom barcode"
                            class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <!-- Seller Points -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Seller Points</label>
                        <input type="number" name="seller_points" step="0.01" placeholder="0.00"
                            class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3" placeholder="Enter service description..."
                            class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>

                    <!-- Image Upload -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Service Image</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:bg-gray-50 transition-colors cursor-pointer" onclick="document.getElementById('service_image').click()">
                            <input type="file" name="item_image" id="service_image" class="hidden" accept="image/*" onchange="previewImage(event)">
                            <div id="upload-placeholder">
                                <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-400 mb-2"></i>
                                <p class="text-sm text-gray-500">Click to upload image</p>
                            </div>
                            <div id="image-preview-container" class="hidden mt-2">
                                <img id="imagePreview" src="" alt="Preview" class="max-h-32 mx-auto rounded shadow-sm">
                                <button type="button" onclick="clearImage(event)" class="text-xs text-red-500 mt-2 hover:underline">Remove Image</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pricing & Tax Card -->
            <div class="bg-white rounded-2xl p-6 shadow-xl border border-gray-100">
                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                    <div class="w-10 h-10 rounded-xl bg-green-100 text-green-600 flex items-center justify-center">
                        <i class="fa-solid fa-tags"></i>
                    </div>
                    <h2 class="text-lg font-bold text-gray-800">Pricing & Tax</h2>
                </div>

                <div class="space-y-5">
                    <!-- Discount Type -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Discount Type</label>
                        <select name="discount_type"
                            class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="Percentage">Percentage (%)</option>
                            <option value="Fixed">Fixed Amount</option>
                        </select>
                    </div>

                    <!-- Discount -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Discount</label>
                        <input type="number" name="discount" step="0.01" placeholder="0.00"
                            class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                     <!-- Price (Expense) -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Price (Expense) <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                            <input type="number" name="price" id="price" step="0.01" required placeholder="0.00"
                                class="w-full pl-8 pr-4 py-2 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>

                    <!-- Tax -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Tax <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <select name="tax_id" required
                                class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">— Select Tax —</option>
                                <?php foreach ($taxes as $tax): ?>
                                    <option value="<?php echo $tax->id; ?>"><?php echo esc_html($tax->tax_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" data-bs-toggle="modal" data-bs-target="#tax_modal" class="bg-gray-200 hover:bg-gray-300 text-gray-600 px-3 py-2 rounded-lg transition-colors" title="Add New Tax">
                                <i class="fa fa-plus"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Tax Type -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Sales Tax Type <span class="text-red-500">*</span></label>
                        <select name="tax_type" required
                            class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="Inclusive">Inclusive</option>
                            <option value="Exclusive">Exclusive</option>
                        </select>
                    </div>

                    <!-- Sales Price -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Sales Price <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                            <input type="number" name="sales_price" id="sales_price" step="0.01" required placeholder="0.00"
                                class="w-full pl-8 pr-4 py-2 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>

                     <!-- Info Box -->
                    <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 flex gap-3">
                         <i class="fa-solid fa-circle-info text-blue-500 mt-1"></i>
                         <div>
                             <h4 class="text-sm font-bold text-blue-800">Service Entry</h4>
                             <p class="text-xs text-blue-600 mt-1">Services are automatically marked and can be managed separately from inventory items.</p>
                         </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-8 flex justify-center gap-4">
             <button type="submit" id="save-btn" class="bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white font-bold py-3 px-8 rounded-xl shadow-lg transform transition hover:-translate-y-0.5">
                <i class="fa-solid fa-check mr-2"></i> Save Service
            </button>
        </div>
    </form>
</div>

<script>
    function previewImage(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e){
                document.getElementById('imagePreview').src = e.target.result;
                document.getElementById('upload-placeholder').classList.add('hidden');
                document.getElementById('image-preview-container').classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    }

    function clearImage(event) {
        if(event) event.stopPropagation();
        document.getElementById('service_image').value = '';
        document.getElementById('imagePreview').src = '';
        document.getElementById('upload-placeholder').classList.remove('hidden');
        document.getElementById('image-preview-container').classList.add('hidden');
    }

    jQuery(document).ready(function($) {
        $('#add-service-form').on('submit', function(e) {
            e.preventDefault();
            
            let formData = new FormData(this);
            $('#save-btn').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Saving...');

            $.ajax({
                url: '<?php echo admin_url("admin-ajax.php"); ?>',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $('#success-text').text(response.data.message);
                        $('#success-message').removeClass('hidden');
                        $('html, body').animate({ scrollTop: 0 }, 'slow');
                        
                        setTimeout(function(){
                            window.location.href = '<?php echo esc_url(add_query_arg('view', 'view-items')); ?>';
                        }, 1500);
                    } else {
                        alert('Error: ' + response.data.message);
                        $('#save-btn').prop('disabled', false).html('<i class="fa-solid fa-check mr-2"></i> Save Service');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    $('#save-btn').prop('disabled', false).html('<i class="fa-solid fa-check mr-2"></i> Save Service');
                }
            });
        });
    });
</script>

<?php 
    include FRONTEND_INVENTORY_PATH . 'templates/modals/modal_category.php'; 
    include FRONTEND_INVENTORY_PATH . 'templates/modals/modal_tax.php'; 
?>
