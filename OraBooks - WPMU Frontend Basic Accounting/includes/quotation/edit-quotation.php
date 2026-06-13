<?php
/**
 * Edit Quotation View (Template)
 * Populated via AJAX when opened
 */
global $wpdb;
$wh_table   = $wpdb->prefix . 'orabooks_db_warehouse';
$cust_table = $wpdb->prefix . 'orabooks_db_customers';
$tax_table  = $wpdb->prefix . 'orabooks_db_tax';

$warehouses = $wpdb->get_results("SELECT id, warehouse_name, warehouse_type FROM {$wh_table} WHERE status=1 ORDER BY warehouse_name ASC");
$customers  = $wpdb->get_results("SELECT id, customer_name FROM {$cust_table} WHERE status=1 ORDER BY customer_name ASC");
$taxes      = $wpdb->get_results("SELECT id, tax_name, tax FROM {$tax_table} WHERE status=1 ORDER BY tax_name ASC");
?>

<div id="obn-view-quotation-edit" class="obn-view-section hidden" style="display:none;">
    <div class="obn-card">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-800">Edit Quotation</h3>
            <button class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded shadow transition obn-quotation-back-list">
                <i class="fa-solid fa-arrow-left mr-1"></i> Back to List
            </button>
        </div>

        <form id="obn-quotation-edit-form" class="space-y-6">
            <input type="hidden" name="action" value="obn_update_quotation">
            <input type="hidden" name="quotation_id" id="obn_edit_q_id">
            <input type="hidden" name="store_id" value="1">

            <!-- Top Info -->
            <div class="bg-gray-50 p-6 rounded-lg border border-gray-200">
                <h4 class="text-lg font-semibold text-gray-700 mb-4 pb-2 border-b">Quotation Details</h4>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quotation Code</label>
                        <input type="text" name="quotation_code" id="obn_edit_q_code" class="w-full px-3 py-2 bg-gray-200 border border-gray-300 rounded text-gray-700 cursor-not-allowed" readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse <span class="text-red-500">*</span></label>
                        <select name="warehouse_id" id="obn_edit_q_warehouse" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500" required>
                            <?php foreach ($warehouses as $w): ?>
                                <option value="<?php echo esc_attr($w->id); ?>"><?php echo esc_html($w->warehouse_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Customer <span class="text-red-500">*</span></label>
                        <select name="customer_id" id="obn_edit_q_customer" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">- Select Customer -</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?php echo esc_attr($c->id); ?>"><?php echo esc_html($c->customer_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reference No.</label>
                        <input type="text" name="reference_no" id="obn_edit_q_ref" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="quotation_date" id="obn_edit_q_date" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                        <input type="date" name="expiry_date" id="obn_edit_q_expiry" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="quotation_status" id="obn_edit_q_status" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
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
                    <button type="button" id="obn-edit-q-add-row" class="bg-blue-100 text-blue-700 hover:bg-blue-200 px-3 py-1 rounded text-sm font-medium transition">
                        <i class="fa-solid fa-plus mr-1"></i> Add Row
                    </button>
                </div>
                
                <div class="mb-4">
                     <input type="text" id="obn-edit-q-autocomplete" class="w-full md:w-1/2 px-4 py-2 border border-gray-300 rounded shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="🔍 Search item by name, code or barcode...">
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-600">
                        <thead class="bg-gray-100 text-gray-700 font-semibold uppercase">
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
                        <tbody id="obn-edit-q-items-tbody">
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
                         <span id="obn-edit-q-total-qty" class="text-xl font-bold text-blue-600">0.00</span>
                     </div>
                     
                     <div class="mb-4">
                         <label class="block text-sm font-medium text-gray-700 mb-1">Other Charges</label>
                         <div class="flex space-x-2">
                             <input type="number" step="0.01" name="other_charges_input" id="obn_edit_q_other_charges" value="0" class="flex-1 px-3 py-2 border border-gray-300 rounded">
                             <select name="other_charges_tax_id" id="obn_edit_q_other_tax" class="w-32 px-3 py-2 border border-gray-300 rounded">
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
                             <input type="number" step="0.01" name="discount_to_all_input" id="obn_edit_q_disc_all" value="0" class="flex-1 px-3 py-2 border border-gray-300 rounded">
                             <select name="discount_to_all_type" id="obn_edit_q_disc_type" class="w-32 px-3 py-2 border border-gray-300 rounded">
                                 <option value="Percentage">Per %</option>
                                 <option value="Fixed">Fixed</option>
                             </select>
                         </div>
                     </div>
                     
                     <div>
                         <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                         <textarea name="quotation_note" id="obn_edit_q_note" class="w-full px-3 py-2 border border-gray-300 rounded" rows="3" placeholder="Add a note..."></textarea>
                     </div>
                </div>
                
                <!-- Totals -->
                <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm flex flex-col justify-between">
                     <h4 class="text-lg font-semibold text-gray-700 mb-4 pb-2 border-b">Summary</h4>
                     
                     <div class="space-y-3 text-sm text-gray-600">
                         <div class="flex justify-between"><span>Subtotal</span> <span class="font-bold" id="obn-edit-q-subtotal">0.00</span></div>
                         <div class="flex justify-between"><span>Other Charges</span> <span class="font-bold" id="obn-edit-q-other-total">0.00</span></div>
                         <div class="flex justify-between text-red-600"><span>Discount on All</span> <span class="font-bold" id="obn-edit-q-disc-total">0.00</span></div>
                         <div class="flex justify-between"><span>Tax Total</span> <span class="font-bold" id="obn-edit-q-tax-total">0.00</span></div>
                         <div class="flex justify-between"><span>Round Off</span> <span class="font-bold" id="obn-edit-q-round">0.00</span></div>
                     </div>
                     
                     <div class="mt-6 pt-4 border-t border-gray-100">
                         <div class="flex justify-between items-center text-xl font-bold text-gray-800">
                             <span>Grand Total</span>
                             <span class="text-green-600" id="obn-edit-q-grand-total">0.00</span>
                         </div>
                     </div>
                     
                     <button type="submit" id="obn-edit-q-save-btn" class="w-full mt-6 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-bold shadow-lg transition transform hover:-translate-y-0.5">
                         <i class="fa-solid fa-save mr-2"></i> Update Quotation
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

    $(document).on('obn:quotation:edit', function(e, id) {
        if(!id) return;
        $('#obn_edit_q_id').val(id);
        
        // Fetch DATA
        $.post(obn_ajax.ajax_url, { action: 'obn_get_quotation', quotation_id: id, security: obn_ajax.nonce }, function(res){
            if(!res.success) { alert('Error fetching quotation'); return; }
            let q = res.data.quotation;
            let items = res.data.items;
            
            $('#obn_edit_q_code').val(q.quotation_code);
            $('#obn_edit_q_warehouse').val(q.warehouse_id);
            $('#obn_edit_q_customer').val(q.customer_id);
            $('#obn_edit_q_ref').val(q.reference_no);
            $('#obn_edit_q_date').val(q.quotation_date);
            $('#obn_edit_q_expiry').val(q.expire_date);
            $('#obn_edit_q_status').val(q.quotation_status);
            $('#obn_edit_q_other_charges').val(q.other_charges_input);
            $('#obn_edit_q_other_tax').val(q.other_charges_tax_id);
            $('#obn_edit_q_disc_all').val(q.discount_to_all_input);
            $('#obn_edit_q_disc_type').val(q.discount_to_all_type);
            $('#obn_edit_q_note').val(q.quotation_note);
            
            $('#obn-edit-q-items-tbody').empty();
            if(items && items.length) {
                items.forEach(i => addItemRowEdit(i));
            } else {
                addItemRowEdit({});
            }
            recalcEdit();
            
        });
        
        initAutocompleteEdit();
    });

    $('.obn-quotation-back-list').on('click', function(){
        $('.obn-view-section').hide();
        $('#obn-view-quotation-list').fadeIn();
    });

    function addItemRowEdit(item) {
        let row = $('<tr>').addClass('border-b hover:bg-gray-50');
        let itemIdVal = item.id ? `<input type="hidden" name="items[][id]" value="${item.id}">` : `<input type="hidden" name="items[][id]" value="">`;
        
        row.append(`
            <td class="p-2">
                ${itemIdVal}
                <select class="obn-edit-item-select w-full border border-gray-300 rounded px-2 py-1"></select>
                <div class="text-xs text-gray-500 mt-1 item-meta">${item.item_code||''}</div>
            </td>
            <td class="p-2"><input type="number" min="0.01" step="0.01" class="w-full border border-gray-300 rounded px-2 py-1 text-center row-qty" value="${item.qty||1}"></td>
            <td class="p-2"><input type="number" step="0.01" class="w-full border border-gray-300 rounded px-2 py-1 row-price" value="${fmt(item.price)}"></td>
            <td class="p-2"><input type="number" step="0.01" class="w-full border border-gray-300 rounded px-2 py-1 row-disc" value="${fmt(item.discount)}"></td>
            <td class="p-2"><input type="number" step="0.01" class="w-full border border-gray-300 rounded px-2 py-1 row-tax" value="${fmt(item.tax_percent)}"></td>
            <td class="p-2"><input type="text" readonly class="w-full bg-gray-100 border border-gray-300 rounded px-2 py-1 row-tax-amt" value="0.00"></td>
            <td class="p-2"><input type="text" readonly class="w-full bg-gray-100 border border-gray-300 rounded px-2 py-1 row-total" value="0.00"></td>
            <td class="p-2 text-center"><button type="button" class="text-red-500 hover:text-red-700 remove-row"><i class="fa-solid fa-times"></i></button></td>
        `);

        $('#obn-edit-q-items-tbody').append(row);
        
        let sel = row.find('.obn-edit-item-select');
        sel.select2({
            placeholder: 'Search Item',
            allowClear: true,
            width: '100%',
            ajax: {
                url: obn_ajax.ajax_url,
                dataType: 'json',
                type: 'POST',
                delay: 250,
                data: function(params){ return { action: 'obn_search_quotation_items', term: params.term, security: obn_ajax.nonce }; },
                processResults: function(data){
                    if(!data.success) return { results: [] };
                    return { results: data.data.map(i => ({ id: i.id, text: `${i.item_name} ${i.item_code?'('+i.item_code+')':''}`, data: i })) };
                }
            }
        });

        if(item.id || item.item_name) { // id checks item_id from DB, but for Select2 we need select2 value
             // item.id here is item_id from quotationitems table, which is correct
             let opt = new Option(`${item.item_name}`, item.id, true, true);
             opt.data = item;
             sel.append(opt).trigger('change');
        }

        sel.on('select2:select', function(e){
             let data = e.params.data.data;
             if(data) {
                 row.find('input[name="items[][id]"]').val(data.id);
                 row.find('.row-price').val(fmt(data.price));
                 row.find('.row-tax').val(fmt(data.tax_percent));
                 row.find('.item-meta').text(data.item_code);
                 recalcEdit();
             }
        });
        
        row.find('input').on('input', recalcEdit);
        row.find('.remove-row').on('click', function(){ row.remove(); recalcEdit(); });
        // recalcEdit(); // deferred to main logic to avoid spam
    }

    $('#obn-edit-q-add-row').on('click', function(){ addItemRowEdit({}); });

    function initAutocompleteEdit() {
        $('#obn-edit-q-autocomplete').autocomplete({
            source: function(req, res) {
                $.post(obn_ajax.ajax_url, { action: 'obn_search_quotation_items', term: req.term, security: obn_ajax.nonce }, function(resp){
                    if(resp.success) res(resp.data.map(i => ({ label: `${i.item_name} (${i.item_code})`, value: i.item_name, data: i })));
                    else res([]);
                });
            },
            select: function(e, ui) {
                addItemRowEdit(ui.item.data);
                $(this).val('');
                return false;
            }
        });
    }

    function recalcEdit() {
        let subtotal = 0, tax_total = 0, total_qty = 0;
        
        $('#obn-edit-q-items-tbody tr').each(function(){
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

        let other_charges = toFloat($('#obn_edit_q_other_charges').val());
        let other_tax_sel = $('#obn_edit_q_other_tax option:selected');
        let other_tax_pct = other_tax_sel.length ? toFloat(other_tax_sel.data('percent')) : 0;
        let other_tax_amt = (other_charges * other_tax_pct) / 100;
        
        let disc_all_input = toFloat($('#obn_edit_q_disc_all').val());
        let disc_type = $('#obn_edit_q_disc_type').val();
        let disc_all_amt = (disc_type === 'Percentage') ? (subtotal * disc_all_input)/100 : disc_all_input;
        
        let sub_after_disc = Math.max(0, subtotal - disc_all_amt);
        let final_tax = tax_total + other_tax_amt;
        let grand = sub_after_disc + final_tax + other_charges;
        let grand_rounded = Math.round(grand * 100) / 100;
        let round_off = grand_rounded - grand;

        $('#obn-edit-q-subtotal').text(fmt(subtotal));
        $('#obn-edit-q-other-total').text(fmt(other_charges + other_tax_amt));
        $('#obn-edit-q-disc-total').text(fmt(disc_all_amt));
        $('#obn-edit-q-tax-total').text(fmt(final_tax));
        $('#obn-edit-q-round').text(fmt(round_off));
        $('#obn-edit-q-grand-total').text(fmt(grand_rounded));
        $('#obn-edit-q-total-qty').text(fmt(total_qty));
    }
    
    $('#obn_edit_q_other_charges, #obn_edit_q_other_tax, #obn_edit_q_disc_all, #obn_edit_q_disc_type').on('input change', recalcEdit);

    $('#obn-quotation-edit-form').on('submit', function(e){
        e.preventDefault();
        
        let items = [];
        $('#obn-edit-q-items-tbody tr').each(function(){
            let id = $(this).find('input[name="items[][id]"]').val();
            let name = $(this).find('.obn-edit-item-select option:selected').text();
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
        data.push({ name: 'subtotal', value:  toFloat($('#obn-edit-q-subtotal').text()) });
        data.push({ name: 'tax_total', value:  toFloat($('#obn-edit-q-tax-total').text()) });
        data.push({ name: 'round_off', value:  toFloat($('#obn-edit-q-round').text()) });
        data.push({ name: 'grand_total', value:  toFloat($('#obn-edit-q-grand-total').text()) });
        
        let btn = $('#obn-edit-q-save-btn');
        btn.prop('disabled', true).text('Updating...');
        
        $.post(obn_ajax.ajax_url, data, function(res){
             if(res.success) {
                 alert('Quotation updated!');
                 $('.obn-view-section').hide();
                 $('#obn-view-quotation-list').fadeIn();
             } else {
                 alert('Error: ' + (typeof res.data === 'string' ? res.data : (res.data.message || 'Unknown error')));
             }
        }).always(function(){ btn.prop('disabled', false).html('<i class="fa-solid fa-save mr-2"></i> Update Quotation'); });
    });
});
</script>
