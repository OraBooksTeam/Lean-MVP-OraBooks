<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

// Fetch Data
$warehouses = $wpdb->get_results("SELECT id, warehouse_name, warehouse_type FROM {$wpdb->prefix}orabooks_db_warehouse WHERE status=1 ORDER BY id ASC");
$customers  = $wpdb->get_results("SELECT id, customer_name FROM {$wpdb->prefix}orabooks_db_customers ORDER BY id ASC");
$categories = $wpdb->get_results("SELECT id, category_name FROM {$wpdb->prefix}orabooks_db_category WHERE status=1 ORDER BY category_name ASC");
$brands     = $wpdb->get_results("SELECT id, brand_name FROM {$wpdb->prefix}orabooks_db_brands WHERE status=1 ORDER BY brand_name ASC");
$payment_types = $wpdb->get_results("SELECT id, payment_type FROM {$wpdb->prefix}orabooks_db_paymenttypes WHERE status=1 ORDER BY id ASC");
// Fetch accounts from ac_coa_list
$accounts = $wpdb->get_results("SELECT id, account_name, account_code FROM {$wpdb->prefix}orabooks_ac_coa_list WHERE status=1 ORDER BY account_code ASC");

// Debug: Add some sample accounts if empty for testing
if (empty($accounts)) {
    $accounts = [
        (object)['id' => 1, 'account_code' => 'AC0001', 'account_name' => 'MD. Farid'],
        (object)['id' => 2, 'account_code' => 'AC0002', 'account_name' => 'MD. Ahmed.'],
        (object)['id' => 3, 'account_code' => '1001', 'account_name' => 'Cash Account'],
        (object)['id' => 4, 'account_code' => '1002', 'account_name' => 'Bank Account - DBBL'],
        (object)['id' => 5, 'account_code' => '1003', 'account_name' => 'Bank Account - BRAC'],
    ];
}
$taxes      = $wpdb->get_results("SELECT id, tax_name, tax FROM {$wpdb->prefix}orabooks_db_tax WHERE status=1 ORDER BY id ASC");

$nonce = wp_create_nonce('frontend_ajax_nonce');
?>

<div class="lg:h-[calc(100vh-100px)] h-auto flex flex-col lg:flex-row gap-4">
    <!-- LEFT COLUMN: Cart & Checkout -->
    <div class="w-full lg:w-1/2 flex flex-col bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden min-h-[600px] lg:min-h-0">
        <!-- Header: Warehouse & Customer -->
        <div class="p-4 bg-gray-50 border-b border-gray-200 space-y-3">
             <div class="flex justify-between items-center">
                 <h2 class="font-bold text-gray-700 flex items-center mb-1 md:mb-0"><i class="fa-solid fa-cash-register mr-2 text-blue-600"></i> <span class="hidden sm:inline">POS Terminal</span><span class="sm:hidden">POS</span></h2>
                 <div class="flex items-center gap-2">
                     <span class="text-[16px] text-gray-400 font-medium">CODE:</span>
                     <input type="text" id="sales_code" readonly class="text-[14px] bg-transparent border-none text-left text-gray-500 p-0 rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 px-2 py-2" placeholder="SL-XXXXX">
                 </div>
             </div>
             
             <div class="grid grid-cols-2 gap-2">
                  <select id="warehouse_id" class="text-sm rounded border-gray-300 focus:ring-blue-500 focus:border-blue-500">
                      <?php foreach ($warehouses as $w): ?>
                          <option value="<?php echo $w->id; ?>" <?php selected($w->warehouse_type, 'system'); ?>><?php echo esc_html($w->warehouse_name); ?></option>
                      <?php endforeach; ?>
                  </select>
                 <div class="flex">
                      <select id="customer_id" class="w-full text-sm rounded-l border-gray-300 focus:ring-blue-500 focus:border-blue-500">
                         <option value="">Walk-in Customer</option>
                         <?php foreach ($customers as $c): echo "<option value='{$c->id}'>{$c->customer_name}</option>"; endforeach; ?>
                     </select>
                      <button type="button" onclick="openCustomerModal()" class="min-h-[25px] bg-blue-100 text-blue-600 px-1.5 py-0.5 md:px-3 rounded-r border-y border-r border-gray-300 hover:bg-blue-200 text-[10px] md:text-base flex-shrink-0" title="Add New Customer">
                          <i class="fa-solid fa-plus"></i>
                      </button>
                 </div>
             </div>
             
             <!-- Item Search with Barcode Support -->
             <div class="flex gap-2">
                 <div class="relative flex-1">
                     <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                         <i class="fa-solid fa-search"></i>
                     </span>
                     <input type="search" id="cart-item-search" class="w-full pl-10 rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm py-2" placeholder="Search by name, code, SKU or barcode...">
                 </div>
                 <button type="button" onclick="openItemModal()" class="bg-blue-100 text-blue-600 px-3 rounded-lg border border-gray-300 hover:bg-blue-200 transition-colors flex items-center justify-center flex-shrink-0" title="Add New Item">
                      <i class="fa-solid fa-plus-circle text-lg"></i>
                  </button>
             </div>
        </div>

        <!-- Cart Items -->
        <div class="flex-1 overflow-y-auto p-0 scrollbar-thin scrollbar-thumb-gray-300 max-h-[400px] lg:max-h-none">
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-gray-500 uppercase bg-gray-100 sticky top-0 z-10 shadow-sm">
                    <tr>
                        <th class="px-3 py-2">Item</th>
                        <th class="px-2 py-2 text-center hidden md:table-cell">Stock</th>
                        <th class="px-2 py-2 text-center">Qty</th>
                        <th class="px-2 py-2 text-right">Price</th>
                        <th class="px-2 py-2 text-right">Disc</th>
                        <th class="px-2 py-2 text-right hidden md:table-cell">Tax%</th>
                        <th class="px-2 py-2 text-right">Total</th>
                        <th class="px-2 py-2 w-8"></th>
                    </tr>
                </thead>
                <tbody id="pos-cart-tbody" class="divide-y divide-gray-100">
                    <!-- Dynamic Rows -->
                    <tr id="pos-empty-cart">
                        <td colspan="8" class="py-10 text-center text-gray-400">
                            <i class="fa-solid fa-basket-shopping text-3xl mb-2 block opacity-30"></i>
                            Cart is empty
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Totals & Checkout -->
        <div class="bg-gray-50 border-t border-gray-200 p-4">
            <div class="space-y-1 mb-4 text-sm">
                <div class="flex justify-between text-gray-600">
                    <span>Total Qty</span>
                    <span class="font-semibold" id="pos_total_qty">0.00</span>
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>Subtotal</span>
                    <span class="font-semibold" id="pos_subtotal">0.00</span>
                </div>
                 <div class="flex justify-between text-gray-600">
                    <span>Tax</span>
                    <span class="font-semibold" id="pos_tax">0.00</span>
                </div>
                <div class="flex justify-between text-gray-600 items-center">
                    <span>Discount</span>
                     <div class="flex w-24">
                        <input type="number" id="pos_discount_input" class="w-full h-6 text-right text-xs border-gray-300 rounded focus:ring-blue-500" value="" placeholder="0.00">
                     </div>
                </div>
                 <div class="flex justify-between text-lg font-bold text-gray-800 border-t border-gray-300 pt-2 mt-2">
                    <span>Total</span>
                    <span class="text-green-600" id="pos_total">0.00</span>
                </div>
            </div>

            <div class="space-y-2 mb-3">
                 <select id="pos_payment_type" class="w-full text-xs rounded border-gray-300 py-2">
                     <option value="">Payment Type</option>
                     <?php foreach ($payment_types as $pt): echo "<option value='{$pt->id}'>{$pt->payment_type}</option>"; endforeach; ?>
                 </select>
                 <div id="bank_account_section" class="hidden">
                     <select id="pos_account" name="account_id" class="w-full text-xs rounded border-gray-300 py-2">
                        <option value="">Select Bank Account</option>
                        <?php 
                        foreach ($accounts as $a): 
                            echo "<option value='{$a->id}'>" . esc_html(($a->account_code ? $a->account_code . ' - ' : '') . $a->account_name) . "</option>"; 
                        endforeach; 
                        ?>
                     </select>
                 </div>
                 <div id="bkash_section" class="hidden">
                     <input type="tel" id="pos_bkash_number" class="w-full text-xs rounded border-gray-300 py-2" placeholder="Enter bKash number (01xxxxxxxxx)" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                 </div>
                 <div id="nagad_section" class="hidden">
                     <input type="tel" id="pos_nagad_number" class="w-full text-xs rounded border-gray-300 py-2" placeholder="Enter Nagad number (01xxxxxxxxx)" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                 </div>
                 <div id="check_section" class="hidden">
                     <input type="text" id="pos_bank_name" class="w-full text-xs rounded border-gray-300 py-2 mb-2" placeholder="Enter bank name">
                     <input type="text" id="pos_check_number" class="w-full text-xs rounded border-gray-300 py-2" placeholder="Enter check number">
                 </div>
            </div>
            
            <div class="flex gap-2">
                <button type="button" id="btn-reset-pos" class="px-4 py-3 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 font-medium">
                    <i class="fa-solid fa-trash"></i>
                </button>
                <button type="button" id="btn-pay-now" class="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-bold shadow-md flex justify-center items-center">
                    <span>PAY NOW</span>
                    <span class="ml-2 bg-green-700 px-2 py-0.5 rounded text-xs" id="pay_btn_amount">0.00</span>
                </button>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: Product Grid -->
    <div class="w-full lg:w-1/2 flex flex-col bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden min-h-[600px] lg:min-h-0">
        <!-- Filters -->
        <div class="p-4 border-b border-gray-200 bg-gray-50">
             <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                 <div class="relative">
                     <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                         <i class="fa-solid fa-search"></i>
                     </span>
                     <input type="search" id="grid-search" class="w-full pl-10 rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm py-2" placeholder="Search by name, code, SKU or barcode...">
                 </div>
                 <select id="grid-category" class="rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm py-2">
                     <option value="">All Categories</option>
                     <?php foreach ($categories as $c): echo "<option value='{$c->id}'>{$c->category_name}</option>"; endforeach; ?>
                 </select>
                 <select id="grid-brand" class="rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm py-2">
                     <option value="">All Brands</option>
                     <?php foreach ($brands as $b): echo "<option value='{$b->id}'>{$b->brand_name}</option>"; endforeach; ?>
                 </select>
             </div>
        </div>
        
        <!-- Products Grid -->
        <div class="flex-1 overflow-y-auto p-4 bg-gray-100 scrollbar-thin">
            <div id="product-grid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                <!-- Products loaded via JS -->
                 <div class="col-span-full text-center py-20 text-gray-500">
                     <i class="fa-solid fa-circle-notch fa-spin text-3xl mb-3 block"></i>
                     Loading products...
                 </div>
            </div>
        </div>
    </div>
</div>

<!-- Cart Item Template -->
<template id="pos-row-tpl">
    <tr class="bg-white border-b hover:bg-gray-50 group">
        <td class="px-3 py-2 align-middle">
            <div class="font-medium text-gray-800 text-xs md:text-sm truncate max-w-[80px] md:max-w-[120px] item-name"></div>
            <div class="text-[9px] md:text-[10px] text-gray-500 item-code"></div>
        </td>
        <td class="px-2 py-2 text-center hidden md:table-cell">
            <span class="inline-block px-2 py-1 bg-gray-100 text-gray-600 rounded text-[10px] font-medium item-stock"></span>
        </td>
        <td class="px-2 py-2 align-middle">
            <div class="flex items-center justify-center bg-gray-100 rounded border border-gray-200">
                <button class="w-4 h-4 md:w-6 md:h-6 flex items-center justify-center text-gray-500 hover:text-red-500 hover:bg-gray-200 rounded-l transition-colors btn-dec text-[10px] md:text-sm">-</button>
                <input type="text" class="w-5 md:w-8 h-4 md:h-6 text-center text-[9px] md:text-xs bg-transparent border-0 p-0 focus:ring-0 item-qty" value="1" readonly>
                <button class="w-4 h-4 md:w-6 md:h-6 flex items-center justify-center text-gray-500 hover:text-green-500 hover:bg-gray-200 rounded-r transition-colors btn-inc text-[10px] md:text-sm">+</button>
            </div>
        </td>
        <td class="px-2 py-2 text-right text-gray-600 text-[9px] md:text-xs item-price">0.00</td>
        <td class="px-2 py-2 text-right">
            <input type="number" step="0.01" class="w-10 md:w-16 h-5 md:h-6 text-right text-[9px] md:text-xs border-gray-300 rounded focus:ring-blue-500 item-discount" value="0">
        </td>
        <td class="px-2 py-2 text-right text-gray-600 text-[10px] md:text-xs item-tax hidden md:table-cell">0</td>
        <td class="px-2 py-2 text-right font-medium text-gray-800 text-xs item-total">0.00</td>
        <td class="px-2 py-2 text-right">
             <button class="text-gray-400 hover:text-red-500 transition-colors btn-remove">
                 <i class="fa-solid fa-times text-xs"></i>
             </button>
        </td>
    </tr>
</template>

<script>
jQuery(document).ready(function($) {
    const ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
    const nonce = '<?php echo esc_js($nonce); ?>';
    
    // State
    let cart = []; // { id, name, code, price, qty, stock, tax_id, tax_pct }
    let allProducts = [];

    // Init
    generateSalesCode();
    loadProducts();

    // Select2
    $('#customer_id, #warehouse_id').select2({ width: '100%' });

    // --- LOGIC ---

    function generateSalesCode(){
        $.post(ajaxurl, { action: 'generate_sales_code', security: nonce }, function(res){
            if (res.success) $('#sales_code').val(res.data.code);
        }, 'json');
    }

    function loadProducts() {
        $.post(ajaxurl, { action: 'search_sales_items', term: '', security: nonce }, function(res){
            if (res.success) {
                allProducts = res.data;
                renderGrid(allProducts);
            }
        }, 'json');
    }

    function renderGrid(products) {
        const grid = $('#product-grid');
        grid.empty();
        
        if (products.length === 0) {
            grid.html('<div class="col-span-full text-center py-10 text-gray-400">No products found</div>');
            return;
        }

        products.forEach(p => {
             const img = p.item_image ? p.item_image : 'https://via.placeholder.com/150?text=No+Img';
             const card = `
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow cursor-pointer product-card overflow-hidden group" data-id="${p.id}">
                    <div class="h-32 bg-gray-100 flex items-center justify-center overflow-hidden relative">
                         <img src="${img}" class="object-cover w-full h-full group-hover:scale-110 transition-transform duration-300">
                         <div class="absolute top-2 right-2 bg-black/50 text-white text-[10px] px-1.5 py-0.5 rounded backdrop-blur-sm">
                            Qty: ${p.stock}
                         </div>
                    </div>
                    <div class="p-3">
                        <div class="font-medium text-gray-800 text-sm truncate mb-1" title="${p.item_name}">${p.item_name}</div>
                        <div class="flex justify-between items-center">
                            <div class="font-bold text-blue-600 text-sm">${parseFloat(p.price).toFixed(2)}</div>
                            <div class="text-[10px] text-gray-500">${p.item_code||p.sku}</div>
                        </div>
                    </div>
                </div>
            `;
            grid.append(card);
        });
    }

    // Filter Grid (only for category and brand, search handled by autocomplete)
    $('#grid-category, #grid-brand').on('change', function(){
        const cat = $('#grid-category').val();
        const brand = $('#grid-brand').val();

        const filtered = allProducts.filter(p => {
            const mCat = !cat || p.category_id == cat;
            const mBrand = !brand || p.brand_id == brand;
            return mCat && mBrand;
        });
        renderGrid(filtered);
    });

    // Add to Cart Logic
    $(document).on('click', '.product-card', function(){
        const id = $(this).data('id');
        const product = allProducts.find(p => p.id == id);
        if(product) addToCart(product);
    });

    // Autocomplete for Left Column (Cart Section)
    $('#cart-item-search').autocomplete({
        source: function(request, response){
            $.post(ajaxurl, { action: 'search_sales_items', term: request.term, security: nonce }, function(res){
                if (res.success) {
                    response(res.data.map(function(i){ 
                        const code = i.item_code || i.sku || i.custom_barcode || '';
                        return { 
                            label: i.item_name + ' (' + code + ') - Stock: ' + (i.stock || 0), 
                            value: i.item_name, 
                            data: i 
                        }; 
                    }));
                } else response([]);
            }, 'json');
        },
        minLength: 1,
        select: function(evt, ui){
            addToCart(ui.item.data);
            $(this).val('');
            return false;
        }
    }).on('keypress', function(e){
        // Support direct barcode scan (Enter key)
        if(e.which === 13) {
            const term = $(this).val().trim().toLowerCase();
            if(term) {
                const product = allProducts.find(p => 
                    (p.item_code && p.item_code.toLowerCase() === term) || 
                    (p.sku && p.sku.toLowerCase() === term) ||
                    (p.custom_barcode && p.custom_barcode.toLowerCase() === term) ||
                    p.item_name.toLowerCase() === term
                );
                
                if(product) {
                    addToCart(product);
                    $(this).val('');
                    e.preventDefault();
                } else {
                    // Let autocomplete handle it if no exact match
                    return true;
                }
            }
        }
    });

    // Autocomplete for Right Column (Product Grid)
    $('#grid-search').autocomplete({
        source: function(request, response){
            $.post(ajaxurl, { action: 'search_sales_items', term: request.term, security: nonce }, function(res){
                if (res.success) {
                    response(res.data.map(function(i){ 
                        const code = i.item_code || i.sku || i.custom_barcode || '';
                        return { 
                            label: i.item_name + ' (' + code + ') - Stock: ' + (i.stock || 0), 
                            value: i.item_name, 
                            data: i 
                        }; 
                    }));
                } else response([]);
            }, 'json');
        },
        minLength: 1,
        select: function(evt, ui){
            addToCart(ui.item.data);
            $(this).val('');
            return false;
        }
    }).on('keypress', function(e){
        // Support direct barcode scan (Enter key)
        if(e.which === 13) {
            const term = $(this).val().trim().toLowerCase();
            if(term) {
                const product = allProducts.find(p => 
                    (p.item_code && p.item_code.toLowerCase() === term) || 
                    (p.sku && p.sku.toLowerCase() === term) ||
                    (p.custom_barcode && p.custom_barcode.toLowerCase() === term) ||
                    p.item_name.toLowerCase() === term
                );
                
                if(product) {
                    addToCart(product);
                    $(this).val('');
                    e.preventDefault();
                } else {
                    // Let autocomplete handle it if no exact match
                    return true;
                }
            }
        }
    });

    function addToCart(product) {
        const existing = cart.find(i => i.id === product.id);
        if (existing) {
            existing.qty++;
        } else {
            cart.push({
                id: product.id,
                name: product.item_name,
                code: product.item_code || product.sku,
                price: parseFloat(product.price),
                qty: 1,
                stock: product.stock,
                discount: 0,
                account_id: product.sales_account_id,
                tax_id: product.tax_id,
                tax_pct: parseFloat(product.tax_percent || 0)
            });
        }
        renderCart();
    }

    function renderCart() {
        const tbody = $('#pos-cart-tbody');
        tbody.empty();

        if (cart.length === 0) {
            tbody.html('<tr id="pos-empty-cart"><td colspan="8" class="py-10 text-center text-gray-400">Cart is empty</td></tr>');
            recalc();
            return;
        }

        const tpl = document.getElementById('pos-row-tpl');

        cart.forEach((item, idx) => {
            const clone = tpl.content.cloneNode(true);
            const tr = $(clone).find('tr');
            
            tr.find('.item-name').text(item.name);
            tr.find('.item-code').text(item.code);
            tr.find('.item-stock').text(item.stock || '0');
            tr.find('.item-qty').val(item.qty);
            tr.find('.item-price').text(item.price.toFixed(2));
            tr.find('.item-discount').val(item.discount || 0);
            tr.find('.item-tax').text(item.tax_pct);
            
            const line_val = item.price * item.qty;
            const line_discount = parseFloat(item.discount || 0);
            const taxable = Math.max(0, line_val - line_discount);
            const line_tax = (taxable * item.tax_pct) / 100;
            const line_total = taxable + line_tax;
            tr.find('.item-total').text(line_total.toFixed(2));
            
            tr.find('.btn-inc').on('click', () => { cart[idx].qty++; renderCart(); });
            tr.find('.btn-dec').on('click', () => { 
                if(cart[idx].qty > 1) { cart[idx].qty--; renderCart(); } 
                else { cart.splice(idx, 1); renderCart(); }
            });
            tr.find('.item-discount').on('input', function() {
                cart[idx].discount = parseFloat($(this).val() || 0);
                renderCart();
            });
            tr.find('.btn-remove').on('click', () => { cart.splice(idx, 1); renderCart(); });

            tbody.append(tr);
        });
        recalc();
    }

    function recalc() {
        let subtotal = 0;
        let tax = 0;
        let total_qty = 0;

        cart.forEach(item => {
            const line_val = item.price * item.qty;
            const line_discount = parseFloat(item.discount || 0);
            const taxable = Math.max(0, line_val - line_discount);
            const line_tax = (taxable * item.tax_pct) / 100;
            subtotal += taxable;
            tax += line_tax;
            total_qty += item.qty;
        });

        const discount = parseFloat($('#pos_discount_input').val() || 0);
        const total = subtotal + tax - discount;

        $('#pos_total_qty').text(total_qty.toFixed(2));
        $('#pos_subtotal').text(subtotal.toFixed(2));
        $('#pos_tax').text(tax.toFixed(2));
        $('#pos_total').text(total.toFixed(2));
        $('#pay_btn_amount').text(total.toFixed(2));
    }

    $('#pos_discount_input').on('input', recalc);
    $('#btn-reset-pos').on('click', () => { 
        if(confirm('Clear cart?')) { cart = []; renderCart(); $('#pos_discount_input').val(0); } 
    });

    // Payment type change handler
    $('#pos_payment_type').on('change', function() {
        const paymentTypeId = $(this).val();
        const bankSection = $('#bank_account_section');
        const bkashSection = $('#bkash_section');
        const nagadSection = $('#nagad_section');
        const checkSection = $('#check_section');
        
        if (paymentTypeId) {
            // Get payment type name to check payment type
            $.post(ajaxurl, {
                action: 'get_payment_type_name',
                security: nonce,
                payment_type_id: paymentTypeId
            }, function(res) {
                if (res.success) {
                    const paymentName = res.data.name.toLowerCase();
                    
                    // Reset all sections
                    bankSection.addClass('hidden');
                    bkashSection.addClass('hidden');
                    nagadSection.addClass('hidden');
                    checkSection.addClass('hidden');
                    
                    // Reset all fields
                    $('#pos_account').prop('required', false).val('');
                    $('#pos_bkash_number').prop('required', false).val('');
                    $('#pos_nagad_number').prop('required', false).val('');
                    $('#pos_bank_name').prop('required', false).val('');
                    $('#pos_check_number').prop('required', false).val('');
                    
                    // Show appropriate section based on payment type
                    if (paymentName.includes('bank')) {
                        bankSection.removeClass('hidden');
                        $('#pos_account').prop('required', true);
                    } else if (paymentName.includes('bkash')) {
                        bkashSection.removeClass('hidden');
                        $('#pos_bkash_number').prop('required', true);
                    } else if (paymentName.includes('nagad') || paymentName.includes('nogod')) {
                        nagadSection.removeClass('hidden');
                        $('#pos_nagad_number').prop('required', true);
                    } else if (paymentName.includes('check')) {
                        checkSection.removeClass('hidden');
                        $('#pos_bank_name').prop('required', true);
                        $('#pos_check_number').prop('required', true);
                    }
                }
            });
        } else {
            // Hide all sections
            bankSection.addClass('hidden');
            bkashSection.addClass('hidden');
            nagadSection.addClass('hidden');
            checkSection.addClass('hidden');
            
            // Reset all fields
            $('#pos_account').prop('required', false).val('');
            $('#pos_bkash_number').prop('required', false).val('');
            $('#pos_nagad_number').prop('required', false).val('');
            $('#pos_bank_name').prop('required', false).val('');
            $('#pos_check_number').prop('required', false).val('');
        }
    });

    // Checkout
    $('#btn-pay-now').on('click', function(){
        if(cart.length === 0) { alert('Cart is empty!'); return; }
        if(!$('#customer_id').val()) { 
            // Allow walk-in customer - create default customer logic
            // For now, we'll allow empty customer_id for walk-in
        }
        if(!$('#pos_payment_type').val()) { alert('Select payment type'); return; }
        // Only require account if bank payment type is selected
        if(!$('#bank_account_section').hasClass('hidden') && !$('#pos_account').val()) { 
            alert('Select bank account'); return; 
        }
        // Only require bKash number if bKash payment type is selected
        if(!$('#bkash_section').hasClass('hidden') && !$('#pos_bkash_number').val()) { 
            alert('Enter bKash number'); return; 
        }
        // Only require Nagad number if Nagad payment type is selected
        if(!$('#nagad_section').hasClass('hidden') && !$('#pos_nagad_number').val()) { 
            alert('Enter Nagad number'); return; 
        }
        // Only require bank name and check number if check payment type is selected
        if(!$('#check_section').hasClass('hidden')) {
            if(!$('#pos_bank_name').val()) { 
                alert('Enter bank name'); return; 
            }
            if(!$('#pos_check_number').val()) { 
                alert('Enter check number'); return; 
            }
        }
        if(!$('#warehouse_id').val()) { alert('Select Warehouse'); return; }


        const btn = $(this);
        const total = parseFloat($('#pos_total').text());
        
        const payload = {
             action: 'insert_sale',
             security: nonce,
             store_id: 1, // Default store
             warehouse_id: $('#warehouse_id').val(),
             sales_code: $('#sales_code').val(),
             reference_no: '',
             sales_date: new Date().toISOString().slice(0,10),
             due_date: new Date().toISOString().slice(0,10),
             customer_id: $('#customer_id').val() || null, // Allow null for walk-in
             other_charges_input: 0,
             discount_to_all_input: parseFloat($('#pos_discount_input').val() || 0),
             discount_to_all_type: 'Fixed',
             subtotal: parseFloat($('#pos_subtotal').text()),
             grand_total: total,
             payment_amount: total, // Full payment in POS
             payment_type_id: $('#pos_payment_type').val(),
             account_id: $('#pos_account').val(),
             bkash_number: $('#pos_bkash_number').val(),
             nagad_number: $('#pos_nagad_number').val(),
             bank_name: $('#pos_bank_name').val(),
             check_number: $('#pos_check_number').val(),
             payment_note: 'POS Sale',
             pos: 1, // Flag as POS
             items_json: JSON.stringify(cart.map(i => {
                // Calculate tax properly
                const lineSubtotal = i.price * i.qty;
                const lineDiscount = parseFloat(i.discount || 0);
                const taxableAmount = Math.max(0, lineSubtotal - lineDiscount);
                const taxAmount = (taxableAmount * i.tax_pct) / 100;
                const lineTotal = taxableAmount + taxAmount;
                
                return {
                    item_id: i.id,
                    name: i.name,
                    account_id: i.account_id,
                    qty: i.qty,
                    unit_price: i.price,
                    discount: i.discount || 0,
                    tax_amt: taxAmount,
                    total: lineTotal
                };
            }))
        };

        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Processing...');
        
        $.post(ajaxurl, payload, function(res){
            if(res.success) {
                alert(res.data.message);
                window.location.href = '?view=sales-invoice&sales_id=' + res.data.sale_id;
            } else {
                alert('Error: ' + res.data);
                btn.prop('disabled', false).html('<span>PAY NOW</span><span class="ml-2 bg-green-700 px-2 py-0.5 rounded text-xs" id="pay_btn_amount">'+total.toFixed(2)+'</span>');
            }
        }, 'json');
    });


    // Callback for New Item Modal
    window.onItemAdded = function(item) {
        const product = {
            id: item.item_id,
            item_name: item.item_name,
            item_code: item.item_code,
            sku: item.sku,
            stock: 0,
            price: item.price,
            sales_account_id: item.sales_account_id,
            tax_id: 0, 
            tax_percent: item.tax_percent
        };
        // Add to global list if not exists
        if (!allProducts.find(p => p.id == product.id)) {
            allProducts.unshift(product);
            renderGrid(allProducts);
        }
        addToCart(product);
    };
});
</script>

<?php include FRONTEND_INVENTORY_TEMPLATE_PATH . 'modals/modal_customer.php'; ?>
<?php include FRONTEND_INVENTORY_TEMPLATE_PATH . 'modals/modal_item.php'; ?>

