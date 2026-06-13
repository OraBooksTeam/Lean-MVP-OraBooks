<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_store = $wpdb->prefix . 'orabooks_db_store';
$store = $wpdb->get_row("SELECT id, customer_init FROM $table_store LIMIT 1");
$store_id = $store ? $store->id : 1;
$customer_init = ($store && !empty($store->customer_init)) ? $store->customer_init : 'CUS-';

$table = $wpdb->prefix . 'orabooks_db_customers';
$customers = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");

// Calculate next code for new customer
$last_count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
$next_count_id = $last_count + 1;
$next_code = $customer_init . str_pad($next_count_id, 6, '0', STR_PAD_LEFT);
?>

<div class="p-6">
    <div class="max-w-7xl mx-auto">

        
        <!-- Header & Add Button -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg">
                    <i class="fa-solid fa-users text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Customers</h1>
                    <p class="text-gray-500 text-sm">Manage your customer base</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="<?php echo esc_url(add_query_arg('view', 'import-customers')); ?>" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transition-all flex items-center gap-2">
                    <i class="fa-solid fa-file-import"></i> <span>Import Customers</span>
                </a>
                <button id="toggle-form-btn" class="px-6 py-2.5 bg-gray-900 hover:bg-black text-white font-medium rounded-xl shadow-lg hover:shadow-xl transition-all flex items-center gap-2">
                    <i class="fa-solid fa-plus"></i> <span>Add New Customer</span>
                </button>
            </div>
        </div>

        <!-- Add/Edit Form Section -->
        <div id="form-section" class="hidden mb-8">
            <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-gray-800" id="form-title">Add New Customer</h2>
                    <button id="cancel-btn" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="p-6">
                    <form id="customer-form" class="space-y-6" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="frontend_save_customer">
                        <input type="hidden" name="security" value="<?php echo wp_create_nonce('frontend_ajax_nonce'); ?>">
                        <input type="hidden" name="id" id="customer_id" value="">
                        <input type="hidden" name="store_id" value="<?php echo $store_id; ?>">
                        <input type="hidden" name="count_id" id="count_id" value="<?php echo $next_count_id; ?>">

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- LEFT COLUMN -->
                            <div class="space-y-8">
                                <!-- Basic Info -->
                                <div class="bg-gray-50/50 p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide border-b pb-2 mb-4 flex items-center gap-2">
                                        <i class="fa-solid fa-user-tag text-blue-500"></i> Basic Information
                                    </h3>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Customer Code</label>
                                            <input type="text" name="customer_code" id="customer_code" value="<?php echo $next_code; ?>" readonly
                                                   class="w-full px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg focus:outline-none cursor-not-allowed">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Customer Name <span class="text-red-500">*</span></label>
                                            <input type="text" name="customer_name" id="customer_name" required
                                                   class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Mobile</label>
                                            <input type="text" name="mobile" id="mobile"
                                                class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                            <input type="text" name="phone" id="phone"
                                                class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                        <input type="email" name="email" id="email"
                                            class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">GST Number</label>
                                            <input type="text" name="gstin" id="gstin"
                                                class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Tax Number</label>
                                            <input type="text" name="tax_number" id="tax_number"
                                                class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Credit Limit</label>
                                            <input type="number" name="credit_limit" id="credit_limit" step="0.01" placeholder="-1 for No Limit"
                                                class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Opening Balance</label>
                                            <input type="number" name="opening_balance" id="opening_balance" step="0.01" value="0.00"
                                                class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Attachment</label>
                                        <input type="file" name="attachment_1" id="attachment_1"
                                            class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                        <div id="attachment-preview" class="mt-2 hidden">
                                            <a href="#" target="_blank" class="text-blue-600 text-xs underline">View Current Attachment</a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Address Info -->
                                <div class="bg-gray-50/50 p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide border-b pb-2 mb-4 flex items-center gap-2">
                                        <i class="fa-solid fa-map-location-dot text-blue-500"></i> Address Details
                                    </h3>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                                            <select name="country_id" id="country_id" class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="">Loading...</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                                            <select name="state_id" id="state_id" class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="">Select Country First</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                                            <input type="text" name="city" id="city"
                                                class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Postcode</label>
                                            <input type="text" name="postcode" id="postcode"
                                                class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                        <textarea name="address" id="address" rows="3"
                                            class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Location Link</label>
                                        <input type="url" name="location_link" id="location_link" placeholder="https://maps.google.com/..."
                                            class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>
                            </div>

                            <!-- RIGHT COLUMN -->
                            <div class="space-y-8">
                                <!-- Shipping Address -->
                                <div class="bg-gray-50/50 p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide border-b pb-2 mb-4 flex items-center gap-2">
                                            <i class="fa-solid fa-truck text-blue-500"></i> Shipping Address
                                        </h3>
                                        <div class="flex items-center gap-2 mb-4">
                                            <input type="checkbox" id="same_as_address" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2">
                                            <label for="same_as_address" class="text-sm font-medium text-gray-700 cursor-pointer">Same as Address Details</label>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                                            <select name="ship_country_id" id="ship_country_id" class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="">Loading...</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                                            <select name="ship_state_id" id="ship_state_id" class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="">Select Country First</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                                            <input type="text" name="ship_city" id="ship_city"
                                                class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Postcode</label>
                                            <input type="text" name="ship_postcode" id="ship_postcode"
                                                class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                        <textarea name="ship_address" id="ship_address" rows="3"
                                            class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                    </div>
                                </div>

                                <!-- Advanced Settings -->
                                <div class="bg-gray-50/50 p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide border-b pb-2 mb-4 flex items-center gap-2">
                                        <i class="fa-solid fa-sliders text-blue-500"></i> Advanced Settings
                                    </h3>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Price Level Type</label>
                                            <select name="price_level_type" id="price_level_type" class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="Increase">Increase</option>
                                                <option value="Decrease">Decrease</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Price Level (%)</label>
                                            <input type="number" name="price_level" id="price_level" step="0.01" value="0.00"
                                                class="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-6 border-t border-gray-100">
                            <button type="submit" id="save-btn" class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg transform transition hover:-translate-y-0.5">
                                Save Customer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Customers List -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
            <!-- Search & Export Toolbar -->
            <div class="p-4 border-b border-gray-100 bg-gray-50 flex flex-col md:flex-row gap-4 items-center justify-between">
                <!-- Search Input -->
                <div class="relative w-full md:w-1/3">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <i class="fa-solid fa-search text-gray-400 text-sm"></i>
                    </div>
                    <input type="search" id="searchInput" class="block w-full pl-10 pr-3 py-2 text-sm text-gray-900 border border-gray-300 rounded-lg bg-white focus:ring-blue-500 focus:border-blue-500" placeholder="Search customers...">
                </div>

                <!-- Export & Column Buttons -->
                <div class="flex gap-2 flex-wrap justify-end w-full md:w-auto">
                    <button id="printBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" title="Print">
                        <i class="fa-solid fa-print mr-1"></i> <span class="hidden sm:inline">Print</span>
                    </button>
                    <button id="pdfBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" title="Export to PDF">
                        <i class="fa-solid fa-file-pdf mr-1 text-red-600"></i> <span class="hidden sm:inline">PDF</span>
                    </button>
                    <button id="excelBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" title="Export to Excel">
                        <i class="fa-solid fa-file-excel mr-1 text-green-600"></i> <span class="hidden sm:inline">Excel</span>
                    </button>
                    <button id="csvBtn" class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors flex-1 sm:flex-none" title="Export to CSV">
                        <i class="fa-solid fa-file-csv mr-1 text-blue-600"></i> <span class="hidden sm:inline">CSV</span>
                    </button>
                    
                    <!-- Column Visibility Dropdown -->
                    <div class="relative flex-1 sm:flex-none">
                        <button id="columnToggleBtn" class="w-full sm:w-auto text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-3 py-2 transition-colors" title="Toggle Columns">
                            <i class="fa-solid fa-columns mr-1"></i> Columns
                        </button>
                        <div id="columnDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                            <div class="p-3 space-y-2">
                                <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                    <input type="checkbox" class="column-toggle mr-2" data-column="0" checked> Customer Name
                                </label>
                                <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                    <input type="checkbox" class="column-toggle mr-2" data-column="1" checked> Contact
                                </label>
                                <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                    <input type="checkbox" class="column-toggle mr-2" data-column="2" checked> Location
                                </label>
                                <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                    <input type="checkbox" class="column-toggle mr-2" data-column="3" checked> Balance
                                </label>
                                <label class="flex items-center cursor-pointer hover:bg-gray-50 p-1 rounded">
                                    <input type="checkbox" class="column-toggle mr-2" data-column="4" checked> Status
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-indigo-600 text-white">
                        <tr class="text-sm uppercase tracking-wider">
                            <th class="px-6 py-4 font-bold">Customer Name</th>
                            <th class="px-6 py-4 font-bold">Contact</th>
                            <th class="px-6 py-4 font-bold">Location</th>
                            <th class="px-6 py-4 font-bold text-right">Balance</th>
                            <th class="px-6 py-4 font-bold text-center">Status</th>
                            <th class="px-6 py-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if($customers): foreach($customers as $cust): ?>
                        <tr class="hover:bg-gray-50 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-800"><?php echo esc_html($cust->customer_name); ?></div>
                                <div class="text-xs text-gray-500 font-medium"><?php echo esc_html($cust->customer_code); ?></div>
                                <div class="text-xs text-gray-400">ID: <?php echo $cust->id; ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php if($cust->mobile): ?><div><i class="fa fa-phone w-4 text-gray-400"></i> <?php echo esc_html($cust->mobile); ?></div><?php endif; ?>
                                <?php if($cust->email): ?><div><i class="fa fa-envelope w-4 text-gray-400"></i> <?php echo esc_html($cust->email); ?></div><?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo esc_html(implode(', ', array_filter([$cust->city, $cust->state_id]))); ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php 
                                    $balance = floatval($cust->opening_balance); 
                                    $class = $balance > 0 ? 'text-red-500' : 'text-green-500';
                                ?>
                                <span class="font-bold <?php echo $class; ?>"><?php echo number_format($balance, 2); ?></span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button class="toggle-status relative inline-flex items-center cursor-pointer" data-id="<?php echo $cust->id; ?>" data-status="<?php echo $cust->status; ?>">
                                    <div class="w-11 h-6 bg-gray-200 rounded-full peer-checked:bg-green-500 transition-colors <?php echo $cust->status == 1 ? 'bg-green-500' : 'bg-gray-200'; ?>"></div>
                                    <div class="absolute w-4 h-4 bg-white rounded-full shadow inset-y-1 left-1 transition-transform <?php echo $cust->status == 1 ? 'translate-x-full' : ''; ?>"></div>
                                </button>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button onclick='editCustomer(<?php echo json_encode($cust); ?>)' class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 flex items-center justify-center transition-colors" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button onclick="deleteCustomer(<?php echo $cust->id; ?>)" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 flex items-center justify-center transition-colors" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500 italic">No customers found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // API: Load Countries (Shared for both sets of dropdowns)
    fetch('https://restcountries.com/v3.1/all?fields=name')
        .then(res => res.json())
        .then(data => {
            const sorted = data.sort((a,b)=>a.name.common.localeCompare(b.name.common));
            let opts = '<option value="">Select Country</option>';
            sorted.forEach(c => { opts += `<option value="${c.name.common}">${c.name.common}</option>`; });
            $('#country_id, #ship_country_id').html(opts);
        });

    // Helper: Load States
    function loadStates(country, targetSelect, selectedState = '') {
        if(!country) { $(targetSelect).html('<option value="">Select Country First</option>'); return; }
        $(targetSelect).html('<option>Loading...</option>');
        
        fetch('https://countriesnow.space/api/v0.1/countries/states', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ country: country })
        })
        .then(res => res.json())
        .then(data => {
            if(data.data && data.data.states && data.data.states.length) {
                let opts = '<option value="">Select State</option>';
                data.data.states.forEach(st => { 
                    const selected = st.name === selectedState ? 'selected' : '';
                    opts += `<option value="${st.name}" ${selected}>${st.name}</option>`; 
                });
                $(targetSelect).html(opts);
            } else {
                $(targetSelect).html('<option value="">No states found</option>');
            }
        })
        .catch(() => $(targetSelect).html('<option value="">Failed to load</option>'));
    }

    // Billing Country Change
    $('#country_id').on('change', function() {
        loadStates($(this).val(), '#state_id');
    });

    // Shipping Country Change
    $('#ship_country_id').on('change', function() {
        loadStates($(this).val(), '#ship_state_id');
    });

    // Same as Address Details checkbox functionality
    $('#same_as_address').on('change', function() {
        if ($(this).is(':checked')) {
            // Copy address details to shipping address
            copyAddressToShipping();
        } else {
            // Clear shipping address fields
            clearShippingAddress();
        }
    });

    function copyAddressToShipping() {
        // Copy country
        $('#ship_country_id').val($('#country_id').val());
        
        // Copy state (need to reload states for the new country)
        var country = $('#country_id').val();
        var selectedState = $('#state_id').val();
        if (country) {
            loadStates(country, '#ship_state_id', selectedState);
        }
        
        // Copy other fields
        $('#ship_city').val($('#city').val());
        $('#ship_postcode').val($('#postcode').val());
        $('#ship_address').val($('#address').val());
    }

    function clearShippingAddress() {
        $('#ship_country_id').val('');
        $('#ship_state_id').html('<option value="">Select Country First</option>');
        $('#ship_city').val('');
        $('#ship_postcode').val('');
        $('#ship_address').val('');
    }

    // Auto-copy when address fields change if checkbox is checked
    $('#country_id, #state_id, #city, #postcode, #address').on('change', function() {
        if ($('#same_as_address').is(':checked')) {
            copyAddressToShipping();
        }
    });

    // Also handle when country changes to reload states
    $('#country_id').on('change', function() {
        if ($('#same_as_address').is(':checked')) {
            var country = $(this).val();
            var selectedState = $('#state_id').val();
            if (country) {
                loadStates(country, '#ship_state_id', selectedState);
                // Copy other fields
                $('#ship_city').val($('#city').val());
                $('#ship_postcode').val($('#postcode').val());
                $('#ship_address').val($('#address').val());
            }
        }
    });

    // Form Toggle
    $(document).on('click', '#toggle-form-btn', function() {
        resetForm();
        $('#form-section').removeClass('hidden');
        $('#form-title').text('Add New Customer');
        $('html, body').animate({ scrollTop: 0 }, 'slow');
        $('#save-btn').text('Save Customer');
    });

    $(document).on('click', '#cancel-btn', function() {
        $('#form-section').addClass('hidden');
        resetForm();
    });

    function resetForm() {
        $('#customer-form')[0].reset();
        $('#customer_id').val('');
        $('#count_id').val('<?php echo $next_count_id; ?>');
        $('#customer_code').val('<?php echo $next_code; ?>');
        $('#attachment-preview').addClass('hidden');
        $('#state_id, #ship_state_id').html('<option value="">Select Country First</option>');
        $('#same_as_address').prop('checked', false);
    }

    // Save Customer
    $('#customer-form').on('submit', function(e) {
        e.preventDefault();
        let formData = new FormData(this);
        
        $('#save-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: '<?php echo admin_url("admin-ajax.php"); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                if(res.success) {
                    location.reload();
                } else {
                    alert('Error: ' + res.data.message);
                    $('#save-btn').prop('disabled', false).text('Save Customer');
                }
            },
            error: function() {
                alert('Server error.');
                $('#save-btn').prop('disabled', false).text('Save Customer');
            }
        });
    });

    // Delete
    window.deleteCustomer = function(id) {
        if(!confirm('Are you sure?')) return;
        $.post('<?php echo admin_url("admin-ajax.php"); ?>', {
            action: 'frontend_delete_customer',
            id: id,
            security: '<?php echo wp_create_nonce("frontend_ajax_nonce"); ?>'
        }, function(res) {
            if(res.success) location.reload();
            else alert('Failed to delete.');
        });
    };

    // Toggle Status
    $('.toggle-status').on('click', function() {
        let btn = $(this);
        let id = btn.data('id');
        let current = btn.data('status');
        let newStatus = current == 1 ? 0 : 1;
        
        btn.css('opacity', '0.5').css('pointer-events', 'none');
        $.post('<?php echo admin_url("admin-ajax.php"); ?>', {
            action: 'frontend_update_customer_status',
            id: id,
            status: newStatus,
            security: '<?php echo wp_create_nonce("frontend_ajax_nonce"); ?>'
        }, function(res) {
            btn.css('opacity', '1').css('pointer-events', 'auto');
            if(res.success) {
                btn.data('status', newStatus);
                let bg = btn.find('div').first();
                let dot = btn.find('div').last();
                
                if(newStatus == 1) {
                    bg.removeClass('bg-gray-200').addClass('bg-green-500');
                    dot.addClass('translate-x-full');
                } else {
                    bg.removeClass('bg-green-500').addClass('bg-gray-200');
                    dot.removeClass('translate-x-full');
                }
            } else {
                alert('Update failed');
            }
        });
    });

    // Edit
    window.editCustomer = function(cust) {
        resetForm();
        $('#form-section').removeClass('hidden');
        $('#form-title').text('Edit Customer');
        $('#save-btn').text('Update Customer');
        $('html, body').animate({ scrollTop: 0 }, 'slow');

        $('#customer_id').val(cust.id);
        $('#count_id').val(cust.count_id);
        $('#customer_code').val(cust.customer_code);
        $('#customer_name').val(cust.customer_name);
        $('#mobile').val(cust.mobile);
        $('#phone').val(cust.phone);
        $('#email').val(cust.email);
        $('#gstin').val(cust.gstin);
        $('#tax_number').val(cust.tax_number);
        $('#credit_limit').val(cust.credit_limit);
        $('#opening_balance').val(cust.opening_balance);
        $('#city').val(cust.city);
        $('#postcode').val(cust.postcode);
        $('#address').val(cust.address);
        $('#location_link').val(cust.location_link);

        // Shipping
        $('#ship_city').val(cust.ship_city);
        $('#ship_postcode').val(cust.ship_postcode);
        $('#ship_address').val(cust.ship_address);

        // Advanced
        $('#price_level_type').val(cust.price_level_type || 'Increase');
        $('#price_level').val(cust.price_level || '0.00');

        // Attachment
        if(cust.attachment_1) {
            $('#attachment-preview').removeClass('hidden').find('a').attr('href', cust.attachment_1);
        }
        
        // Handle dropdowns
        if(cust.country_id) {
            $('#country_id').val(cust.country_id);
            loadStates(cust.country_id, '#state_id', cust.state_id);
        }
        if(cust.ship_country_id) {
            $('#ship_country_id').val(cust.ship_country_id);
            loadStates(cust.ship_country_id, '#ship_state_id', cust.ship_state_id);
        }
        
        // Check if shipping address is same as billing address and set checkbox
        var isSameAddress = (
            cust.country_id === cust.ship_country_id &&
            cust.state_id === cust.ship_state_id &&
            cust.city === cust.ship_city &&
            cust.postcode === cust.ship_postcode &&
            cust.address === cust.ship_address
        );
        
        $('#same_as_address').prop('checked', isSameAddress);
    };

    // Client-side Search
    $('#searchInput').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $("table tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });

    // Column visibility toggle
    $('#columnToggleBtn').on('click', function(e) {
        e.stopPropagation();
        $('#columnDropdown').toggleClass('hidden');
    });
    
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#columnToggleBtn, #columnDropdown').length) {
            $('#columnDropdown').addClass('hidden');
        }
    });
    
    $('.column-toggle').on('change', function() {
        const column = $(this).data('column');
        const isChecked = $(this).is(':checked');
        
        $('table thead tr th').eq(column).toggle(isChecked);
        $('table tbody tr').each(function() {
            $(this).find('td').eq(column).toggle(isChecked);
        });
    });
    
    // Get table data for export
    function getTableData() {
        const data = [];
        const headers = [];
        
        $('table thead tr th').each(function(index) {
            if($(this).is(':visible') && index < 5) { // Exclude Actions column
                headers.push($(this).text().trim());
            }
        });
        data.push(headers);
        
        $('table tbody tr').each(function() {
            const row = [];
            $(this).find('td').each(function(index) {
                if($(this).is(':visible') && index < 5) {
                    let text = $(this).text().trim();
                    // Clean up text
                    text = text.replace(/\s+/g, ' ').trim();
                    row.push(text);
                }
            });
            if(row.length > 0 && row[0] !== 'No customers found.') {
                data.push(row);
            }
        });
        
        return data;
    }
    
    // Print functionality
    $('#printBtn').on('click', function() {
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Customers List</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-family: Arial, sans-serif; }');
        printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 20px; }');
        printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
        printWindow.document.write('th { background-color: #f3f4f6; font-weight: bold; }');
        printWindow.document.write('h1 { text-align: center; color: #333; }');
        printWindow.document.write('</style></head><body>');
        printWindow.document.write('<h1>Customers List</h1>');
        
        const tableData = getTableData();
        printWindow.document.write('<table>');
        tableData.forEach(function(row, index) {
            printWindow.document.write('<tr>');
            row.forEach(function(cell) {
                const tag = index === 0 ? 'th' : 'td';
                printWindow.document.write('<' + tag + '>' + cell + '</' + tag + '>');
            });
            printWindow.document.write('</tr>');
        });
        printWindow.document.write('</table></body></html>');
        printWindow.document.close();
        printWindow.print();
    });
    
    // PDF Export
    $('#pdfBtn').on('click', function() {
        // Load jsPDF dynamically if not already loaded
        if (typeof window.jspdf === 'undefined') {
            const script1 = document.createElement('script');
            script1.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
            script1.onload = function() {
                const script2 = document.createElement('script');
                script2.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js';
                script2.onload = function() {
                    exportPDF();
                };
                document.head.appendChild(script2);
            };
            document.head.appendChild(script1);
        } else {
            exportPDF();
        }
    });

    function exportPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        doc.setFontSize(18);
        doc.text('Customers List', 14, 22);
        
        const tableData = getTableData();
        const headers = tableData[0];
        const rows = tableData.slice(1);
        
        doc.autoTable({
            head: [headers],
            body: rows,
            startY: 30,
            theme: 'grid',
            styles: { fontSize: 8 },
            headStyles: { fillColor: [59, 130, 246] }
        });
        
        doc.save('customers-list.pdf');
    }
    
    // Excel Export
    $('#excelBtn').on('click', function() {
        // Load SheetJS dynamically if not already loaded
        if (typeof XLSX === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
            script.onload = function() {
                exportExcel();
            };
            document.head.appendChild(script);
        } else {
            exportExcel();
        }
    });

    function exportExcel() {
        const tableData = getTableData();
        const ws = XLSX.utils.aoa_to_sheet(tableData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Customers');
        XLSX.writeFile(wb, 'customers-list.xlsx');
    }
    
    // CSV Export
    $('#csvBtn').on('click', function() {
        const tableData = getTableData();
        let csv = '';
        
        tableData.forEach(function(row) {
            csv += row.map(function(cell) {
                return '"' + cell.replace(/"/g, '""') + '"';
            }).join(',') + '\n';
        });
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'customers-list.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
});
</script>
