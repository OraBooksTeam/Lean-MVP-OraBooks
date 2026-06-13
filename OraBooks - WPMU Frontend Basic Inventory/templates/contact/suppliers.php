<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_store = $wpdb->prefix . 'orabooks_db_store';
$store = $wpdb->get_row("SELECT id, supplier_init FROM $table_store LIMIT 1");
$store_id = $store ? $store->id : 1;
$supplier_init = ($store && !empty($store->supplier_init)) ? $store->supplier_init : 'SUP-';

$table = $wpdb->prefix . 'orabooks_db_suppliers';
$suppliers = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");

// Calculate next code for new supplier
$last_count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
$next_count_id = $last_count + 1;
$next_code = $supplier_init . str_pad($next_count_id, 6, '0', STR_PAD_LEFT);
?>

<div class="p-6">
    <div class="max-w-7xl mx-auto">

        
        <!-- Header & Add Button -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg">
                    <i class="fa-solid fa-truck-field text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Suppliers</h1>
                    <p class="text-gray-500 text-sm">Manage your suppliers</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="<?php echo esc_url(add_query_arg('view', 'import-suppliers')); ?>" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transition-all flex items-center gap-2">
                    <i class="fa-solid fa-file-import"></i> <span>Import Suppliers</span>
                </a>
                <button id="toggle-form-btn" class="px-6 py-2.5 bg-gray-900 hover:bg-black text-white font-medium rounded-xl shadow-lg hover:shadow-xl transition-all flex items-center gap-2">
                    <i class="fa-solid fa-plus"></i> <span>Add New Supplier</span>
                </button>
            </div>
        </div>

        <!-- Add/Edit Form Section -->
        <div id="form-section" class="hidden mb-8">
            <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-gray-800" id="form-title">Add New Supplier</h2>
                    <button id="cancel-btn" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="p-6">
                    <form id="supplier-form" class="space-y-6">
                        <input type="hidden" name="action" value="frontend_save_supplier">
                        <input type="hidden" name="security" value="<?php echo wp_create_nonce('frontend_ajax_nonce'); ?>">
                        <input type="hidden" name="id" id="supplier_id" value="">
                        <input type="hidden" name="store_id" value="<?php echo $store_id; ?>">
                        <input type="hidden" name="count_id" id="count_id" value="<?php echo $next_count_id; ?>">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Basic Info -->
                            <div class="space-y-4">
                                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide border-b pb-2 mb-4">Basic Information</h3>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Supplier Code</label>
                                        <input type="text" name="supplier_code" id="supplier_code" value="<?php echo $next_code; ?>" readonly
                                               class="w-full px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg focus:outline-none cursor-not-allowed">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Supplier Name <span class="text-red-500">*</span></label>
                                        <input type="text" name="supplier_name" id="supplier_name" required
                                               class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Mobile</label>
                                        <input type="text" name="mobile" id="mobile"
                                            class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                        <input type="email" name="email" id="email"
                                            class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">GST Number</label>
                                        <input type="text" name="gst_number" id="gst_number"
                                            class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Tax Number</label>
                                        <input type="text" name="tax_number" id="tax_number"
                                            class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Opening Balance</label>
                                    <input type="number" name="opening_balance" id="opening_balance" step="0.01" value="0.00"
                                        class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>

                            <!-- Address Info -->
                            <div class="space-y-4">
                                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide border-b pb-2 mb-4">Address Details</h3>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                                        <select name="country" id="country" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="">Loading...</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                                        <select name="state" id="state" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="">Select Country First</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                                        <input type="text" name="city" id="city"
                                            class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Postcode</label>
                                        <input type="text" name="postcode" id="postcode"
                                            class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                    <textarea name="address" id="address" rows="3"
                                        class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-6 border-t border-gray-100">
                            <button type="submit" id="save-btn" class="px-8 py-3 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl shadow-lg transform transition hover:-translate-y-0.5">
                                Save Supplier
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Suppliers List -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
            <!-- Search & Export Toolbar -->
            <div class="p-4 border-b border-gray-100 bg-gray-50 flex flex-col md:flex-row gap-4 items-center justify-between">
                <!-- Search Input -->
                <div class="relative w-full md:w-1/3">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <i class="fa-solid fa-search text-gray-400 text-sm"></i>
                    </div>
                    <input type="search" id="searchInput" class="block w-full pl-10 pr-3 py-2 text-sm text-gray-900 border border-gray-300 rounded-lg bg-white focus:ring-amber-500 focus:border-amber-500" placeholder="Search suppliers...">
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
                                    <input type="checkbox" class="column-toggle mr-2" data-column="0" checked> Supplier Name
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
                            <th class="px-6 py-4 font-bold">Supplier Name</th>
                            <th class="px-6 py-4 font-bold">Contact</th>
                            <th class="px-6 py-4 font-bold">Location</th>
                            <th class="px-6 py-4 font-bold text-right">Balance</th>
                            <th class="px-6 py-4 font-bold text-center">Status</th>
                            <th class="px-6 py-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if($suppliers): foreach($suppliers as $sup): ?>
                        <tr class="hover:bg-gray-50 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-800"><?php echo esc_html($sup->supplier_name); ?></div>
                                <div class="text-xs text-gray-500 font-medium"><?php echo esc_html($sup->supplier_code); ?></div>
                                <div class="text-xs text-gray-400">ID: <?php echo $sup->id; ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php if($sup->mobile): ?><div><i class="fa fa-phone w-4 text-gray-400"></i> <?php echo esc_html($sup->mobile); ?></div><?php endif; ?>
                                <?php if($sup->email): ?><div><i class="fa fa-envelope w-4 text-gray-400"></i> <?php echo esc_html($sup->email); ?></div><?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo esc_html(implode(', ', array_filter([$sup->city, $sup->state_id]))); ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php 
                                    $balance = floatval($sup->opening_balance); 
                                    $class = $balance > 0 ? 'text-red-500' : 'text-green-500';
                                ?>
                                <span class="font-bold <?php echo $class; ?>"><?php echo number_format($balance, 2); ?></span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button class="toggle-status relative inline-flex items-center cursor-pointer" data-id="<?php echo $sup->id; ?>" data-status="<?php echo $sup->status; ?>">
                                    <div class="w-11 h-6 bg-gray-200 rounded-full peer-checked:bg-green-500 transition-colors <?php echo $sup->status == 1 ? 'bg-green-500' : 'bg-gray-200'; ?>"></div>
                                    <div class="absolute w-4 h-4 bg-white rounded-full shadow inset-y-1 left-1 transition-transform <?php echo $sup->status == 1 ? 'translate-x-full' : ''; ?>"></div>
                                </button>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button onclick='editSupplier(<?php echo json_encode($sup); ?>)' class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 flex items-center justify-center transition-colors" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button onclick="deleteSupplier(<?php echo $sup->id; ?>)" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 flex items-center justify-center transition-colors" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500 italic">No suppliers found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // API: Load Countries
    fetch('https://restcountries.com/v3.1/all?fields=name')
        .then(res => res.json())
        .then(data => {
            const sorted = data.sort((a,b)=>a.name.common.localeCompare(b.name.common));
            let opts = '<option value="">Select Country</option>';
            sorted.forEach(c => { opts += `<option value="${c.name.common}">${c.name.common}</option>`; });
            $('#country').html(opts);
        });

    // API: Load States
    $('#country').on('change', function() {
        const country = $(this).val();
        const stateSelect = $('#state');
        
        if(!country) { stateSelect.html('<option value="">Select Country First</option>'); return; }
        
        stateSelect.html('<option>Loading...</option>');
        
        fetch('https://countriesnow.space/api/v0.1/countries/states', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ country: country })
        })
        .then(res => res.json())
        .then(data => {
            if(data.data && data.data.states && data.data.states.length) {
                let opts = '<option value="">Select State</option>';
                data.data.states.forEach(st => { opts += `<option value="${st.name}">${st.name}</option>`; });
                stateSelect.html(opts);
            } else {
                stateSelect.html('<option value="">No states found</option>');
            }
        })
        .catch(() => stateSelect.html('<option value="">Failed to load</option>'));
    });

    // Form Toggle
    $(document).on('click', '#toggle-form-btn', function() {
        resetForm();
        $('#form-section').removeClass('hidden');
        $('#form-title').text('Add New Supplier');
        $('html, body').animate({ scrollTop: 0 }, 'slow');
        $('#save-btn').text('Save Supplier');
    });

    $(document).on('click', '#cancel-btn', function() {
        $('#form-section').addClass('hidden');
        resetForm();
    });

    function resetForm() {
        $('#supplier-form')[0].reset();
        $('#supplier_id').val('');
        $('#count_id').val('<?php echo $next_count_id; ?>');
        $('#supplier_code').val('<?php echo $next_code; ?>');
    }

    // Save
    $('#supplier-form').on('submit', function(e) {
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
                    $('#save-btn').prop('disabled', false).text('Save Supplier');
                }
            },
            error: function() {
                alert('Server error.');
                $('#save-btn').prop('disabled', false).text('Save Supplier');
            }
        });
    });

    // Delete
    window.deleteSupplier = function(id) {
        if(!confirm('Are you sure?')) return;
        $.post('<?php echo admin_url("admin-ajax.php"); ?>', {
            action: 'frontend_delete_supplier',
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
            action: 'frontend_update_supplier_status',
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
    window.editSupplier = function(sup) {
        resetForm();
        $('#form-section').removeClass('hidden');
        $('#form-title').text('Edit Supplier');
        $('#save-btn').text('Update Supplier');
        $('html, body').animate({ scrollTop: 0 }, 'slow');

        $('#supplier_id').val(sup.id);
        $('#count_id').val(sup.count_id);
        $('#supplier_code').val(sup.supplier_code);
        $('#supplier_name').val(sup.supplier_name);
        $('#mobile').val(sup.mobile);
        $('#email').val(sup.email);
        $('#gst_number').val(sup.gstin);
        $('#tax_number').val(sup.tax_number);
        $('#opening_balance').val(sup.opening_balance);
        $('#city').val(sup.city);
        $('#postcode').val(sup.postcode);
        $('#address').val(sup.address);
        
        if(sup.country_id) {
            $('#country').val(sup.country_id).trigger('change');
            setTimeout(() => {
                $('#state').val(sup.state_id);
            }, 1000);
        }
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
            if(row.length > 0 && row[0] !== 'No suppliers found.') {
                data.push(row);
            }
        });
        
        return data;
    }
    
    // Print functionality
    $('#printBtn').on('click', function() {
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Suppliers List</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-family: Arial, sans-serif; }');
        printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 20px; }');
        printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
        printWindow.document.write('th { background-color: #f3f4f6; font-weight: bold; }');
        printWindow.document.write('h1 { text-align: center; color: #333; }');
        printWindow.document.write('</style></head><body>');
        printWindow.document.write('<h1>Suppliers List</h1>');
        
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
        doc.text('Suppliers List', 14, 22);
        
        const tableData = getTableData();
        const headers = tableData[0];
        const rows = tableData.slice(1);
        
        doc.autoTable({
            head: [headers],
            body: rows,
            startY: 30,
            theme: 'grid',
            styles: { fontSize: 8 },
            headStyles: { fillColor: [245, 158, 11] }
        });
        
        doc.save('suppliers-list.pdf');
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
        XLSX.utils.book_append_sheet(wb, ws, 'Suppliers');
        XLSX.writeFile(wb, 'suppliers-list.xlsx');
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
        link.setAttribute('download', 'suppliers-list.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
});
</script>
