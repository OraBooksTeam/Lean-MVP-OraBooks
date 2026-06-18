<?php
/**
 * My Reimbursements List Template
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$reimb_table = $wpdb->prefix . 'orabooks_reimbursements';
$current_user_id = get_current_user_id();
$reimbursements = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $reimb_table WHERE employee_id = %d ORDER BY date DESC", $current_user_id ) );
$reimb_nonce = wp_create_nonce( 'obn_reimbursement_nonce' );
?>

<div class="obn-card p-6 !pt-4">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-2xl font-bold text-gray-800">My Reimbursements</h3>
        <button onclick="window.obn_reimb_reset_form()" class="obn-dash-link bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow-sm transition" data-target="reimbursement-add">
            <i class="fa-solid fa-plus mr-2"></i> New Request
        </button>
    </div>

    <!-- Filters/Search (Simplified for now) -->
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <div class="relative w-full md:w-80">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="search" id="obn-reimbursement-search" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg sm:text-sm focus:ring-blue-500 focus:border-blue-500 transition-all" placeholder="Search my requests...">
        </div>
    </div>

    <div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
        <table id="obn-reimbursement-table" class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Date</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Ref #</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Description</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Amount</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-700">Status</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
                </tr>
            </thead>
            <tbody id="obn-reimbursement-tbody" class="divide-y divide-gray-200">
                <?php if ( $reimbursements ) : foreach ( $reimbursements as $r ) : 
                    $status_class = '';
                    switch($r->status) {
                        case 'Draft': $status_class = 'bg-gray-100 text-gray-800'; break;
                        case 'Submitted': $status_class = 'bg-blue-100 text-blue-800'; break;
                        case 'Approved': $status_class = 'bg-emerald-100 text-emerald-800'; break;
                        case 'Rejected': $status_class = 'bg-rose-100 text-rose-800'; break;
                        case 'Paid': $status_class = 'bg-indigo-100 text-indigo-800'; break;
                    }
                ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 text-gray-600"><?php echo esc_html( date('d-m-Y', strtotime($r->date)) ); ?></td>
                    <td class="px-4 py-3 font-semibold text-gray-800"><?php echo esc_html( $r->reimbursement_no ); ?></td>
                    <td class="px-4 py-3 text-gray-600 max-w-xs truncate"><?php echo esc_html( $r->description ); ?></td>
                    <td class="px-4 py-3 text-right font-bold text-gray-800"><?php echo number_format($r->total_amount, 2); ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-1 rounded-full text-xs font-bold uppercase <?php echo $status_class; ?>">
                            <?php echo esc_html( $r->status ); ?>
                        </span>
                    </td>
                        <td class="px-4 py-3 text-right space-x-1">
                            <?php if($r->status === 'Draft'): ?>
                                <button onclick="obn_submit_reimb(<?php echo $r->id; ?>, '<?php echo $reimb_nonce; ?>')" class="text-blue-600 hover:text-blue-800" title="Submit"><i class="fa-solid fa-paper-plane"></i></button>
                                <button onclick="obn_edit_reimb(<?php echo $r->id; ?>, '<?php echo $reimb_nonce; ?>')" class="text-gray-600 hover:text-gray-800" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                                <button onclick="obn_delete_reimb(<?php echo $r->id; ?>, '<?php echo $reimb_nonce; ?>')" class="text-rose-600 hover:text-rose-800" title="Delete"><i class="fa-solid fa-trash"></i></button>
                            <?php else: ?>
                                <button class="obn-view-reimb text-teal-600 hover:text-teal-800" data-id="<?php echo $r->id; ?>" title="View"><i class="fa-solid fa-eye"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500 italic">No reimbursement requests found.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function ($) {
        window.obn_submit_reimb = function (id, nonce) {
            if (!confirm('Are you sure you want to submit this request for approval?')) return;

            $.ajax({
                url: obn_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'obn_submit_reimbursement',
                    id: id,
                    security: nonce
                },
                success: function (res) {
                    if (typeof res === 'string') { try { res = JSON.parse(res); } catch (e) { } }
                    if (res && res.success) {
                        alert(res.data?.message || 'Submitted successfully.');
                        location.reload();
                    } else {
                        alert('Error: ' + (res.data || 'Failed to submit'));
                    }
                },
                error: function () {
                    alert('Server communication failed.');
                }
            });
        };

        window.obn_delete_reimb = function (id, nonce) {
            if (!confirm('Delete this draft?')) return;

            $.ajax({
                url: obn_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'obn_delete_reimbursement',
                    id: id,
                    security: nonce
                },
                success: function (res) {
                    if (typeof res === 'string') { try { res = JSON.parse(res); } catch (e) { } }
                    if (res && res.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (res.data || 'Failed to delete'));
                    }
                }
            });
        };

        window.obn_edit_reimb = function (id, nonce) {
            $.ajax({
                url: obn_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'obn_get_reimbursement',
                    id: id,
                    security: nonce
                },
                success: function (res) {
                    if (typeof res === 'string') { try { res = JSON.parse(res); } catch (e) { } }
                    if (res && res.success) {
                        const data = res.data;
                        const $form = $('#obn-reimbursement-form');
                        
                        // Switch view
                        $('.obn-view-section').hide();
                        $('#obn-view-reimbursement-add').show().addClass('active');
                        
                        // Update Header and Button
                        $('#obn-view-reimbursement-add h3').text('Edit Reimbursement Request');
                        $form.find('button[type="submit"]').text('Update Draft');

                        // Populate basic info
                        $form.find('input[name="id"]').val(data.id);
                        $form.find('input[name="date"]').val(data.date);
                        $form.find('input[name="description"]').val(data.description);

                        // Populate items
                        const $body = $('#reimb-items-body');
                        
                        // Capture category options BEFORE emptying the body
                        // We search in the whole form for ANY reimbursement category select to get the options
                        const categoryOptions = $form.find('select[name^="items"][name$="[category_id]"]').first().html();
                        
                        $body.empty();
                        
                        if (data.items && data.items.length > 0) {
                            data.items.forEach((item, index) => {
                                let rowHtml = `
                                    <tr class="reimb-row">
                                        <td class="px-3 py-3"><input type="date" name="items[${index}][date]" class="w-full p-2 border rounded" value="${item.date}" required></td>
                                        <td class="px-3 py-3">
                                            <select name="items[${index}][category_id]" class="w-full p-2 border rounded" required>
                                                ${categoryOptions}
                                            </select>
                                        </td>
                                        <td class="px-3 py-3"><input type="text" name="items[${index}][description]" class="w-full p-2 border rounded" placeholder="Details..." value="${item.description}"></td>
                                        <td class="px-3 py-3"><input type="number" step="0.01" name="items[${index}][amount]" class="w-full p-2 border rounded text-right reimb-amount" value="${item.amount}" min="0.01" required></td>
                                        <td class="px-3 py-3 text-center"><button type="button" class="text-rose-500 hover:text-rose-700 remove-row" ${index === 0 ? 'style="display:none;"' : ''}><i class="fa-solid fa-trash"></i></button></td>
                                    </tr>`;
                                $body.append(rowHtml);
                                $body.find(`tr:last select`).val(item.category_id);
                            });
                        }
                        
                        // Recalculate total
                        if (typeof window.obn_reimb_calc_total === 'function') {
                            window.obn_reimb_calc_total();
                        } else {
                            // Fallback if not globally available
                            let total = 0;
                            $('.reimb-amount').each(function () {
                                total += parseFloat($(this).val()) || 0;
                            });
                            $('#reimb-grand-total').text(total.toFixed(2));
                        }
                    } else {
                        alert('Error fetching data: ' + (res.data || 'Unknown error'));
                    }
                }
            });
        };
        // Search Functionality
        $('#obn-reimbursement-search').on('keyup', function () {
            const val = $(this).val().toLowerCase();
            $('#obn-reimbursement-tbody tr').filter(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
            });
        });
    });
</script>
