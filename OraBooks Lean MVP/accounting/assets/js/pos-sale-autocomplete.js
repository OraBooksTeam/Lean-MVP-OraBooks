// assets/js/pos-sale-autocomplete.js
jQuery(document).ready(function($) {
    // Ensure autocomplete attribute is off to avoid native browser suggestions
    $('#cart-item-search').attr('autocomplete', 'off').autocomplete({
        source: function(request, response) {
            $.post(obnPosSale.ajaxurl, {
                action: 'search_sales_items',
                term: request.term,
                security: obnPosSale.nonce
            }, function(res) {
                if (res.success) {
                    response(res.data.map(function(i) {
                        const code = i.item_code || i.sku || i.custom_barcode || '';
                        return {
                            label: i.item_name + ' (' + code + ') - Stock: ' + (i.stock || 0),
                            value: i.item_name,
                            data: i
                        };
                    })));
                } else {
                    response([]);
                }
            }, 'json');
        },
        minLength: 1,
        appendTo: 'body',
        select: function(event, ui) {
            // Add selected product to cart
            if (typeof addToCart === 'function') {
                addToCart(ui.item.data);
            }
            $(this).val('');
            return false;
        }
    }).on('keypress', function(e) {
        // Support barcode scan (Enter key)
        if (e.which === 13) {
            const term = $(this).val().trim().toLowerCase();
            if (term) {
                const product = allProducts.find(p =>
                    (p.item_code && p.item_code.toLowerCase() === term) ||
                    (p.sku && p.sku.toLowerCase() === term) ||
                    (p.custom_barcode && p.custom_barcode.toLowerCase() === term) ||
                    p.item_name.toLowerCase() === term
                );
                if (product) {
                    if (typeof addToCart === 'function') {
                        addToCart(product);
                    }
                    $(this).val('');
                    e.preventDefault();
                } else {
                    // Let autocomplete handle it if no exact match
                    return true;
                }
            }
        }
    });
});
