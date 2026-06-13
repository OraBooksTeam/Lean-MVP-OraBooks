<?php
/**
 * Quotation Invoice View
 */
?>
<div id="obn-view-quotation-invoice" class="obn-view-section hidden" style="display:none;">
    <div class="wrap orabooks-wrap">
        <div class="flex justify-between items-center mb-4 no-print">
            <h1 class="text-2xl font-bold text-gray-800">Quotation Invoice</h1>
            <div class="space-x-2">
                <button id="obn-print-invoice" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm transition"><i class="fa-solid fa-print mr-1"></i> Print</button>
                <button id="obn-export-pdf" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm transition"><i class="fa-solid fa-file-pdf mr-1"></i> PDF</button>
                <button class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-sm transition obn-quotation-back-list">Back to List</button>
            </div>
        </div>

        <div id="obn-invoice-print-area" class="bg-white p-10 max-w-4xl mx-auto shadow-lg rounded-lg border border-gray-100">
            <!-- Loading State -->
            <div id="obn-invoice-loading" class="text-center py-10 text-gray-500">
                <i class="fa-solid fa-spinner fa-spin fa-2x mb-3"></i>
                <p>Loading Invoice...</p>
            </div>

            <!-- Content -->
            <div id="obn-invoice-content" class="hidden">
                <!-- Header -->
                <div class="flex justify-between border-b-2 border-blue-500 pb-5 mb-8">
                    <div>
                        <h2 class="text-3xl font-bold text-blue-500">QUOTATION</h2>
                        <p class="text-gray-500 mt-1 text-lg"># <span id="inv-code"></span></p>
                    </div>
                    <div class="text-right text-gray-600">
                        <div class="mb-1"><span class="font-bold">Date:</span> <span id="inv-date"></span></div>
                        <div class="mb-1"><span class="font-bold">Valid Until:</span> <span id="inv-expire"></span></div>
                        <div><span class="font-bold">Status:</span> <span id="inv-status" class="px-2 py-0.5 rounded text-xs text-white font-bold ml-1"></span></div>
                    </div>
                </div>

                <!-- Info -->
                <div class="flex flex-col md:flex-row gap-8 mb-8">
                    <div class="flex-1 bg-gray-50 p-5 rounded-lg border-l-4 border-blue-500">
                        <h5 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">From</h5>
                        <strong class="block text-lg text-gray-800 mb-2" id="inv-comp-name"></strong>
                        <div class="text-sm text-gray-600 space-y-1" id="inv-comp-details"></div>
                    </div>
                    <div class="flex-1 bg-gray-50 p-5 rounded-lg border-l-4 border-teal-500">
                        <h5 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">To</h5>
                        <strong class="block text-lg text-gray-800 mb-2" id="inv-cust-name"></strong>
                        <div class="text-sm text-gray-600 space-y-1" id="inv-cust-details"></div>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto mb-8">
                    <table class="w-full text-sm">
                        <thead class="bg-black text-white">
                            <tr>
                                <th class="px-4 py-3 text-left rounded-tl">#</th>
                                <th class="px-4 py-3 text-left w-1/3">Item Description</th>
                                <th class="px-4 py-3 text-right">Price</th>
                                <th class="px-4 py-3 text-center">Qty</th>
                                <th class="px-4 py-3 text-right">Tax</th>
                                <th class="px-4 py-3 text-right">Discount</th>
                                <th class="px-4 py-3 text-right rounded-tr">Total</th>
                            </tr>
                        </thead>
                        <tbody id="inv-items-tbody" class="text-gray-600 divide-y divide-gray-100">
                            <!-- Items -->
                        </tbody>
                    </table>
                </div>

                <!-- Footer Stats -->
                <div class="flex flex-col md:flex-row gap-8">
                    <div class="md:w-7/12">
                        <div id="inv-note-wrapper" class="hidden bg-yellow-50 p-4 rounded border border-yellow-200 mb-6">
                            <h6 class="text-yellow-800 font-bold text-xs uppercase mb-1">Note:</h6>
                            <p id="inv-note" class="text-sm text-yellow-800 italic"></p>
                        </div>
                        <div class="text-xs text-gray-400">
                            <p class="font-bold text-gray-500 mb-1">Terms & Conditions:</p>
                            <ul class="list-disc pl-4 space-y-1">
                                <li>Valid until expiry date.</li>
                                <li>Prices subject to change.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="md:w-5/12">
                         <table class="w-full text-sm">
                             <tr class="border-b border-gray-100"><td class="py-2 text-right text-gray-500 pr-4">Subtotal:</td><td class="py-2 text-right font-bold text-gray-800" id="inv-subtotal"></td></tr>
                             <tr class="border-b border-gray-100 hidden" id="inv-row-tax"><td class="py-2 text-right text-gray-500 pr-4">Tax Total:</td><td class="py-2 text-right text-gray-800" id="inv-tax"></td></tr>
                             <tr class="border-b border-gray-100 hidden" id="inv-row-other"><td class="py-2 text-right text-gray-500 pr-4">Other Charges:</td><td class="py-2 text-right text-gray-800" id="inv-other"></td></tr>
                             <tr class="border-b border-gray-100 hidden" id="inv-row-disc"><td class="py-2 text-right text-gray-500 pr-4">Discount on All:</td><td class="py-2 text-right text-red-500" id="inv-disc"></td></tr>
                             <tr class="border-b border-gray-100 hidden" id="inv-row-round"><td class="py-2 text-right text-gray-500 pr-4">Round Off:</td><td class="py-2 text-right text-gray-800" id="inv-round"></td></tr>
                             <tr class="border-t-2 border-gray-100"><td class="py-4 text-right text-gray-800 font-bold text-lg pr-4">Grand Total:</td><td class="py-4 text-right text-blue-600 font-bold text-lg" id="inv-grand"></td></tr>
                         </table>
                    </div>
                </div>
                
                <div class="mt-12 text-center text-gray-400 text-sm border-t border-gray-100 pt-6">
                    <p class="font-medium text-gray-500">Thank you for your business!</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<style>
@media print {
    body * { visibility: hidden; }
    #obn-invoice-print-area, #obn-invoice-print-area * { visibility: visible; }
    #obn-invoice-print-area { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 0; box-shadow: none; border: none; }
    .no-print { display: none !important; }
}
</style>

<script>
jQuery(document).ready(function($) {
    function fmt(v){ return parseFloat(v||0).toFixed(2); }
    
    $(document).on('obn:quotation:invoice', function(e, id) {
        if(!id) return;
        
        $('#obn-invoice-content').addClass('hidden');
        $('#obn-invoice-loading').removeClass('hidden');

        $.post(obn_ajax.ajax_url, { action: 'obn_get_quotation_invoice_data', quotation_id: id, security: obn_ajax.nonce }, function(res){
            $('#obn-invoice-loading').addClass('hidden');
            if(!res.success) { alert('Failed to load invoice'); return; }
            
            let data = res.data;
            let q = data.quotation;
            let c = data.company;
            let cust = data.customer;
            let items = data.items;
            
            // Header
            $('#inv-code').text(q.quotation_code);
            $('#inv-date').text(new Date(q.quotation_date).toLocaleDateString());
            $('#inv-expire').text(q.expire_date ? new Date(q.expire_date).toLocaleDateString() : '-');
            
            let statusColor = { 'Draft':'#6b7280', 'Sent':'#1569B3', 'Accepted':'#39B54A', 'Declined':'#ef4444' };
            $('#inv-status').text(q.quotation_status).css('background-color', statusColor[q.quotation_status]||'#6b7280');
            
            // Company
            $('#inv-comp-name').text(c ? (c.company_name||'My Company') : 'My Company');
            let cDet = '';
            if(c) {
                if(c.address) cDet += `<div>${c.address}</div>`;
                if(c.phone) cDet += `<div><i class="fa-solid fa-phone w-5"></i> ${c.phone}</div>`;
                if(c.email) cDet += `<div><i class="fa-solid fa-envelope w-5"></i> ${c.email}</div>`;
            }
            $('#inv-comp-details').html(cDet);
            
            // Customer
            if(cust) {
                $('#inv-cust-name').text(cust.customer_name);
                let custDet = '';
                if(cust.address) custDet += `<div>${cust.address}</div>`;
                if(cust.mobile) custDet += `<div><i class="fa-solid fa-phone w-5"></i> ${cust.mobile}</div>`;
                if(cust.email) custDet += `<div><i class="fa-solid fa-envelope w-5"></i> ${cust.email}</div>`;
                $('#inv-cust-details').html(custDet);
            } else {
                 $('#inv-cust-name').text('Unknown Customer');
                 $('#inv-cust-details').text('');
            }
            
            // Items
            let tbody = $('#inv-items-tbody');
            tbody.empty();
            if(items && items.length) {
                items.forEach((item, i) => {
                    tbody.append(`
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-slate-50">
                            <td class="px-4 py-3">${i+1}</td>
                            <td class="px-4 py-3 font-medium">${item.description}</td>
                            <td class="px-4 py-3 text-right">${fmt(item.price)}</td>
                            <td class="px-4 py-3 text-center text-gray-500">${item.qty}</td>
                            <td class="px-4 py-3 text-right text-gray-500">${fmt(item.tax_amt)}</td>
                            <td class="px-4 py-3 text-right text-gray-500">${fmt(item.discount)}</td>
                            <td class="px-4 py-3 text-right font-bold text-gray-800">${fmt(item.total)}</td>
                        </tr>
                    `);
                });
            } else {
                tbody.append('<tr><td colspan="7" class="text-center py-4">No items</td></tr>');
            }
            
            // Totals
            $('#inv-subtotal').text(fmt(q.subtotal));
            
            // Tax total calc logic matching backend
            let tax_total = 0; items.forEach(i => tax_total += parseFloat(i.tax_amt));
            if(tax_total > 0) {
                 $('#inv-tax').text(fmt(tax_total));
                 $('#inv-row-tax').removeClass('hidden');
            } else $('#inv-row-tax').addClass('hidden');
            
            if(parseFloat(q.other_charges_amt) > 0) {
                 $('#inv-other').text(fmt(q.other_charges_amt));
                 $('#inv-row-other').removeClass('hidden');
            } else $('#inv-row-other').addClass('hidden');

            if(parseFloat(q.tot_discount_to_all_amt) > 0) {
                 $('#inv-disc').text('- ' + fmt(q.tot_discount_to_all_amt));
                 $('#inv-row-disc').removeClass('hidden');
            } else $('#inv-row-disc').addClass('hidden');
            
            if(parseFloat(q.round_off) !== 0) {
                 $('#inv-round').text(fmt(q.round_off));
                 $('#inv-row-round').removeClass('hidden');
            } else $('#inv-row-round').addClass('hidden');
            
            $('#inv-grand').text(fmt(q.grand_total));
            
            // Note
            if(q.quotation_note) {
                $('#inv-note').text(q.quotation_note);
                $('#inv-note-wrapper').removeClass('hidden');
            } else {
                $('#inv-note-wrapper').addClass('hidden');
            }
            
            $('#obn-invoice-content').removeClass('hidden');
        });
    });

    // Print
    $('#obn-print-invoice').on('click', function(){ window.print(); });

    // PDF
    $('#obn-export-pdf').on('click', function(){
        const { jsPDF } = window.jspdf;
        const element = document.querySelector("#obn-invoice-print-area");
        
        html2canvas(element, { scale: 2 }).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const pdf = new jsPDF('p', 'mm', 'a4');
            const imgWidth = 210; 
            const imgHeight = (canvas.height * imgWidth) / canvas.width;
            
            pdf.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight);
            pdf.save("Quotation.pdf");
        });
    });
});
</script>
