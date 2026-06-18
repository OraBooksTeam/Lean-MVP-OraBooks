<?php
/**
 * Add Quotation View
 */
global $wpdb;
$wh_table   = $wpdb->prefix . 'orabooks_db_warehouse';
$cust_table = $wpdb->prefix . 'orabooks_db_customers';
$tax_table  = $wpdb->prefix . 'orabooks_db_tax';

$warehouses = $wpdb->get_results("SELECT id, warehouse_name, warehouse_type FROM {$wh_table} WHERE status=1 ORDER BY warehouse_name ASC");
$customers  = $wpdb->get_results("SELECT id, customer_name FROM {$cust_table} WHERE status=1 ORDER BY customer_name ASC");
$taxes      = $wpdb->get_results("SELECT id, tax_name, tax FROM {$tax_table} WHERE status=1 ORDER BY tax_name ASC");
?>

<style>
/* CSS for jQuery UI Autocomplete (in case theme doesn't support it) */
.ui-autocomplete {
    position: absolute;
    z-index: 99999;
    background: #ffffff;
    border: 1px solid #d1d5db;
    list-style: none;
    padding: 0;
    margin: 0;
    max-height: 300px;
    overflow-y: auto;
    overflow-x: hidden;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    border-radius: 0.375rem;
    font-family: inherit;
    font-size: 0.875rem;
    color: #374151;
}
.ui-autocomplete li {
    padding: 0;
    margin: 0;
    border-bottom: 1px solid #f3f4f6;
}
.ui-autocomplete li:last-child {
    border-bottom: none;
}
.ui-autocomplete li div { /* jQuery UI often wraps content in div */
    padding: 8px 12px;
    cursor: pointer;
    display: block;
}
.ui-autocomplete li:hover, .ui-autocomplete li.ui-state-focus {
    background-color: #f3f4f6;
    color: #111827;
}
.ui-helper-hidden-accessible { display: none; }
</style>

<div id="obn-view-quotation-add" class="obn-view-section hidden" style="display:none;">
    <div class="obn-card">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-800">Add New Quotation</h3>
            <button class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded shadow transition obn-quotation-back-list">
                <i class="fa-solid fa-arrow-left mr-1"></i> Back to List
            </button>
        </div>

        <form id="obn-quotation-add-form" class="space-y-6">
            <input type="hidden" name="action" value="obn_insert_quotation">
            <input type="hidden" name="store_id" value="1">

            <!-- Top Info -->
            <div class="bg-gray-50 p-6 rounded-lg border border-gray-200">
                <h4 class="text-lg font-semibold text-gray-700 mb-4 pb-2 border-b">Quotation Details</h4>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quotation Code</label>
                        <div class="flex">
                            <input type="text" name="quotation_code" id="obn_add_q_code" class="w-full px-3 py-2 bg-gray-200 border border-gray-300 rounded-l text-gray-700 cursor-not-allowed" readonly>
                            <button type="button" id="obn_add_q_refresh_code" class="bg-blue-100 text-blue-600 px-3 border border-l-0 border-blue-200 rounded-r hover:bg-blue-200" title="Generate New Code">
                                <i class="fa-solid fa-sync"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse <span class="text-red-500">*</span></label>
                        <select name="warehouse_id" id="obn_add_q_warehouse" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500" required>
                            <?php foreach ($warehouses as $w): ?>
                                <option value="<?php echo esc_attr($w->id); ?>" <?php selected($w->warehouse_type, 'system'); ?>><?php echo esc_html($w->warehouse_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Customer <span class="text-red-500">*</span></label>
                        <select name="customer_id" id="obn_add_q_customer" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">- Select Customer -</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?php echo esc_attr($c->id); ?>"><?php echo esc_html($c->customer_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reference No.</label>
                        <input type="text" name="reference_no" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="quotation_date" id="obn_add_q_date" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500" value="<?php echo current_time('Y-m-d'); ?>" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                        <input type="date" name="expiry_date" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="quotation_status" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                            <option value="Draft">Draft</option>
                            <option value="Sent">Sent</option>
                            <option value="Accepted">Accepted</option>
                            <option value="Declined">Declined</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Items -->
            <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm relative">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="text-lg font-semibold text-gray-700">Items</h4>
                    <button type="button" id="obn-add-q-add-row" class="bg-blue-100 text-blue-700 hover:bg-blue-200 px-3 py-1 rounded text-sm font-medium transition">
                        <i class="fa-solid fa-plus mr-1"></i> Add Row
                    </button>
                </div>
                
                <div class="mb-4 relative">
                     <input type="text" id="obn-add-q-autocomplete-search" class="w-full md:w-1/2 px-4 py-2 border border-gray-300 rounded shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="🔍 Search item by name, code or barcode...">
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-600" id="obn-add-q-items-table">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-3 py-2 w-1/3">Item Name</th>
                                <th class="px-3 py-2 w-24">Qty</th>
                                <th class="px-3 py-2 w-24">Price</th>
                                <th class="px-3 py-2 w-24">Discount</th>
                                <th class="px-3 py-2 w-16">Tax %</th>
                                <th class="px-3 py-2 w-24">Tax Amt</th>
                                <th class="px-3 py-2 w-24">Total</th>
                                <th class="px-3 py-2 w-10 text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="obn-add-q-items-tbody">
                            <!-- Rows -->
                        </tbody>
                    </table>
                </div>
            </div>

             <!-- Totals & Notes -->
             <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Additional -->
                <div class="bg-gray-50 p-6 rounded-lg border border-gray-200">
                     <h4 class="text-lg font-semibold text-gray-700 mb-4 pb-2 border-b">Additional details</h4>
                     
                     <div class="flex justify-between items-center mb-4 p-3 bg-white rounded border">
                         <span class="font-medium text-gray-700">Total Quantity</span>
                         <span id="obn-add-q-total-qty" class="text-xl font-bold text-blue-600">0.00</span>
                     </div>
                     
                     <div class="mb-4">
                         <label class="block text-sm font-medium text-gray-700 mb-1">Other Charges</label>
                         <div class="flex space-x-2">
                             <input type="number" step="any" name="other_charges_input" id="obn_add_q_other_charges" value="0" class="flex-1 px-3 py-2 border border-gray-300 rounded">
                             <select name="other_charges_tax_id" id="obn_add_q_other_tax" class="w-32 px-3 py-2 border border-gray-300 rounded">
                                 <option value="">- Tax -</option>
                                 <?php foreach ($taxes as $t): ?>
                                     <option value="<?php echo esc_attr($t->id); ?>" data-percent="<?php echo esc_attr($t->tax); ?>"><?php echo esc_html($t->tax_name); ?> (<?php echo $t->tax; ?>%)</option>
                                 <?php endforeach; ?>
                             </select>
                         </div>
                     </div>
                     
                     <div class="mb-4">
                         <label class="block text-sm font-medium text-gray-700 mb-1">Discount to All</label>
                         <div class="flex space-x-2">
                             <input type="number" step="any" name="discount_to_all_input" id="obn_add_q_disc_all" value="0" class="flex-1 px-3 py-2 border border-gray-300 rounded">
                             <select name="discount_to_all_type" id="obn_add_q_disc_type" class="w-32 px-3 py-2 border border-gray-300 rounded">
                                 <option value="Percentage">Per %</option>
                                 <option value="Fixed">Fixed</option>
                             </select>
                         </div>
                     </div>
                     
                     <div>
                         <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                         <textarea name="quotation_note" class="w-full px-3 py-2 border border-gray-300 rounded" rows="3" placeholder="Add a note..."></textarea>
                     </div>
                </div>
                
                <!-- Totals -->
                <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm flex flex-col justify-between">
                     <h4 class="text-lg font-semibold text-gray-700 mb-4 pb-2 border-b">Summary</h4>
                     
                     <div class="space-y-3 text-sm text-gray-600">
                         <div class="flex justify-between"><span>Subtotal</span> <span class="font-bold" id="obn-add-q-subtotal">0.00</span></div>
                         <div class="flex justify-between"><span>Other Charges</span> <span class="font-bold" id="obn-add-q-other-total">0.00</span></div>
                         <div class="flex justify-between text-red-600"><span>Discount on All</span> <span class="font-bold" id="obn-add-q-disc-total">0.00</span></div>
                         <div class="flex justify-between"><span>Tax Total</span> <span class="font-bold" id="obn-add-q-tax-total">0.00</span></div>
                         <div class="flex justify-between"><span>Round Off</span> <span class="font-bold" id="obn-add-q-round">0.00</span></div>
                     </div>
                     
                     <div class="mt-6 pt-4 border-t border-gray-100">
                         <div class="flex justify-between items-center text-xl font-bold text-gray-800">
                             <span>Grand Total</span>
                             <span class="text-green-600" id="obn-add-q-grand-total">0.00</span>
                         </div>
                     </div>
                     
                     <button type="submit" id="obn-add-q-save-btn" class="w-full mt-6 bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-bold shadow-lg transition transform hover:-translate-y-0.5">
                         <i class="fa-solid fa-save mr-2"></i> Save Quotation
                     </button>
                </div>
             </div>

        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    function fmt(v){ return parseFloat(v||0).toFixed(2); }
    function toFloat(v){ return Number((v||0).toString().replace(/,/g,'')) || 0; }

    // Init Autocomplete on Document Ready
    // We target the search input by ID
    var searchInput = $('#obn-add-q-autocomplete-search');
    
    // Shared autocomplete source config
    var autocompleteSource = function(req, res) {
        $.post(obn_ajax.ajax_url, { 
            action: 'obn_search_quotation_items', 
            term: req.term, 
            security: obn_ajax.nonce 
        }, function(resp){
            if(resp.success && resp.data.length > 0) {
                res(resp.data.map(i => ({ 
                    label: `${i.item_name} (${i.item_code}) - ${fmt(i.price)}`, 
                    value: i.item_name, 
                    data: i 
                })));
            } else {
                res([{ label: 'No items found', value: '', data: null }]);
            }
        }).fail(function(){
            res([]);
        });
    };

    if (typeof $.fn.autocomplete !== 'undefined') {
        searchInput.autocomplete({
            minLength: 2,
            source: autocompleteSource,
            select: function(e, ui) {
                if(ui.item.data) {
                    addItemRow(ui.item.data);
                }
                $(this).val('');
                return false;
            }
        });
    } else {
        console.warn('jQuery UI Autocomplete not found. Item search may not work.');
    }

    // Helper to generate code
    function generateQuotationCode() {
        $('#obn_add_q_code').val('Generating...');
        $.post(obn_ajax.ajax_url, { action: 'obn_generate_quotation_code', security: obn_ajax.nonce }, function(res){
             if(res.success) $('#obn_add_q_code').val(res.data.code);
             else {
                 console.error('Quotation code error:', res);
                 $('#obn_add_q_code').val('');
             }
        }).fail(function(xhr){ 
            console.error('Quotation code connection failed', xhr); 
            $('#obn_add_q_code').val('Error');
        });
    }

    // Explicit refresh button
    $('#obn_add_q_refresh_code').on('click', function(e){
        e.preventDefault();
        generateQuotationCode();
    });

    // Event: Show/Add View Trigger
    $(document).on('obn:quotation:add', function() {
        console.log('Quotation Add View Triggered');
        
        // Reset form
        $('#obn-quotation-add-form')[0].reset();
        $('#obn-add-q-items-tbody').empty();
        
        // Set dates
        let dateEl = $('#obn_add_q_date');
        if(dateEl.length === 0) dateEl = $('input[name="quotation_date"]');
        dateEl.val(new Date().toISOString().split('T')[0]);
        
        // Add one empty row
        addItemRow({});
        
        // Generate new code
        generateQuotationCode();
        
        // Reset totals
        recalc();
    });

    // Auto-trigger if this view is visible on page load (e.g. refresh, though view is hidden default)
    if($('#obn-view-quotation-add').is(':visible')) {
        $(document).trigger('obn:quotation:add');
    }

    $('.obn-quotation-back-list').on('click', function(){
        $('.obn-view-section').hide();
        $('#obn-view-quotation-list').fadeIn();
    });

    // Add Row Logic
    function addItemRow(item) {
        let row = $('<tr>').addClass('border-b hover:bg-gray-50');
        let itemIdVal = item.id ? `<input type="hidden" name="items[][id]" value="${item.id}">` : `<input type="hidden" name="items[][id]" value="">`;
        
        // Replaced Select2 with Input for consistent Autocomplete
        row.append(`
            <td class="p-2 relative">
                ${itemIdVal}
                <input type="text" class="obn-item-input w-full border border-gray-300 rounded px-2 py-1 placeholder-gray-400 text-gray-700" placeholder="Type to search..." value="${item.item_name || ''}">
                <div class="text-xs text-gray-500 mt-1 item-meta">${item.item_code||''}</div>
            </td>
            <td class="p-2"><input type="number" min="0.01" step="any" class="w-full border border-gray-300 rounded px-2 py-1 text-center row-qty" value="${item.qty||1}"></td>
            <td class="p-2"><input type="number" step="any" class="w-full border border-gray-300 rounded px-2 py-1 row-price" value="${fmt(item.price)}"></td>
            <td class="p-2"><input type="number" step="any" class="w-full border border-gray-300 rounded px-2 py-1 row-disc" value="${fmt(item.discount)}"></td>
            <td class="p-2"><input type="number" step="any" class="w-full border border-gray-300 rounded px-2 py-1 row-tax" value="${fmt(item.tax_percent)}"></td>
            <td class="p-2"><input type="text" readonly class="w-full bg-gray-100 border border-gray-300 rounded px-2 py-1 row-tax-amt" value="0.00"></td>
            <td class="p-2"><input type="text" readonly class="w-full bg-gray-100 border border-gray-300 rounded px-2 py-1 row-total" value="0.00"></td>
            <td class="p-2 text-center"><button type="button" class="text-red-500 hover:text-red-700 remove-row"><i class="fa-solid fa-times"></i></button></td>
        `);

        $('#obn-add-q-items-tbody').append(row);
        
        // Init Autocomplete on the new input
        let input = row.find('.obn-item-input');
        if (typeof $.fn.autocomplete !== 'undefined') {
            input.autocomplete({
                minLength: 1,
                source: autocompleteSource,
                select: function(e, ui) {
                    let data = ui.item.data;
                    if(data) {
                        row.find('input[name="items[][id]"]').val(data.id);
                        row.find('.row-price').val(fmt(data.price));
                        row.find('.obn-item-input').val(ui.item.value); // Ensure name is set
                        row.find('.row-tax').val(fmt(data.tax_percent));
                        row.find('.item-meta').text(data.item_code);
                        recalc();
                    }
                    return false;
                }
            });
        }

        row.find('input').on('input', recalc);
        row.find('.remove-row').on('click', function(){ row.remove(); recalc(); });
        recalc();
    }

    $('#obn-add-q-add-row').on('click', function(){ addItemRow({}); });

    function recalc() {
        let subtotal = 0, tax_total = 0, total_qty = 0;
        
        $('#obn-add-q-items-tbody tr').each(function(){
            let qty = toFloat($(this).find('.row-qty').val());
            let price = toFloat($(this).find('.row-price').val());
            let disc = toFloat($(this).find('.row-disc').val());
            let tax_pct = toFloat($(this).find('.row-tax').val());
            
            let total_before = qty * price;
            let taxable = Math.max(0, total_before - disc);
            let tax_amt = (taxable * tax_pct) / 100;
            let total = taxable + tax_amt;
            
            $(this).find('.row-tax-amt').val(fmt(tax_amt));
            $(this).find('.row-total').val(fmt(total));
            
            subtotal += taxable;
            tax_total += tax_amt;
            total_qty += qty;
        });

        // Other charges
        let other_charges = toFloat($('#obn_add_q_other_charges').val());
        let other_tax_sel = $('#obn_add_q_other_tax option:selected');
        let other_tax_pct = other_tax_sel.length ? toFloat(other_tax_sel.data('percent')) : 0;
        let other_tax_amt = (other_charges * other_tax_pct) / 100;
        
        // Discount All
        let disc_all_input = toFloat($('#obn_add_q_disc_all').val());
        let disc_type = $('#obn_add_q_disc_type').val();
        let disc_all_amt = (disc_type === 'Percentage') ? (subtotal * disc_all_input)/100 : disc_all_input;
        
        let sub_after_disc = Math.max(0, subtotal - disc_all_amt);
        let final_tax = tax_total + other_tax_amt;
        let grand = sub_after_disc + final_tax + other_charges;
        let grand_rounded = Math.round(grand * 100) / 100; // simple round
        let round_off = grand_rounded - grand;

        $('#obn-add-q-subtotal').text(fmt(subtotal));
        $('#obn-add-q-other-total').text(fmt(other_charges + other_tax_amt));
        $('#obn-add-q-disc-total').text(fmt(disc_all_amt));
        $('#obn-add-q-tax-total').text(fmt(final_tax));
        $('#obn-add-q-round').text(fmt(round_off));
        $('#obn-add-q-grand-total').text(fmt(grand_rounded));
        $('#obn-add-q-total-qty').text(fmt(total_qty));
    }
    
    $('#obn_add_q_other_charges, #obn_add_q_other_tax, #obn_add_q_disc_all, #obn_add_q_disc_type').on('input change', recalc);

    // Submit Handler (Moved outside to prevent duplicate bindings)
    $('#obn-quotation-add-form').on('submit', function(e){
        e.preventDefault();
        
        // Collect items
        let items = [];
        $('#obn-add-q-items-tbody tr').each(function(){
            let id = $(this).find('input[name="items[][id]"]').val();
            // Changed from select to input val()
            let name = $(this).find('.obn-item-input').val(); 
            let qty = $(this).find('.row-qty').val();
            let price = $(this).find('.row-price').val();
            let disc = $(this).find('.row-disc').val();
            let tax = $(this).find('.row-tax').val();
            let tax_amt = $(this).find('.row-tax-amt').val();
            let total = $(this).find('.row-total').val();
            
            if(name && qty) {
                 items.push({ 
                     item_id: id, name: name, qty: qty, unit_price: price, 
                     discount: disc, tax_percent: tax, tax_amt: tax_amt, total: total 
                 });
            }
        });
        
        if(!items.length) { alert('Please add at least one item.'); return; }
        
        let data = $(this).serializeArray();
        data.push({ name: 'security', value: obn_ajax.nonce });
        data.push({ name: 'items_json', value: JSON.stringify(items) });
        // Add calculated totals explicitly
        data.push({ name: 'subtotal', value:  toFloat($('#obn-add-q-subtotal').text()) });
        data.push({ name: 'tax_total', value:  toFloat($('#obn-add-q-tax-total').text()) });
        data.push({ name: 'round_off', value:  toFloat($('#obn-add-q-round').text()) });
        data.push({ name: 'grand_total', value:  toFloat($('#obn-add-q-grand-total').text()) });
        
        let btn = $('#obn-add-q-save-btn');
        btn.prop('disabled', true).text('Saving...');
        
        $.post(obn_ajax.ajax_url, data, function(res){
             if(res.success) {
                 alert('Quotation saved!');
                 // Redirect to invoice
                 $('.obn-view-section').hide();
                 $('#obn-view-quotation-invoice').fadeIn();
                 $(document).trigger('obn:quotation:invoice', [res.data.quotation_id]); 
             } else {
                 let msg = (typeof res.data === 'string') ? res.data : (res.data.message || 'Unknown error');
                 alert('Save Failed: ' + msg);
             }
        }).fail(function(xhr) { 
            console.error(xhr);
            alert('Network/Server Error: ' + xhr.statusText); 
        })
        .always(function(){ btn.prop('disabled', false).html('<i class="fa-solid fa-save mr-2"></i> Save Quotation'); });
    });
});
</script>
