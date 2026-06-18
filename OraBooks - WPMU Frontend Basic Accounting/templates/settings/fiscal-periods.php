<?php
if (!defined('ABSPATH')) {
    exit;
}

$repo = class_exists('OBN_Fiscal_Period_Repository') ? new OBN_Fiscal_Period_Repository() : null;
$periods = $repo ? $repo->paginate(obn_current_org_id(), [
    'page' => isset($_GET['fp_page']) ? intval($_GET['fp_page']) : 1,
    'per_page' => 25,
]) : ['data' => [], 'page' => 1, 'per_page' => 25, 'total' => 0, 'total_pages' => 0];
$nonce = wp_create_nonce('obn_fiscal_period_nonce');
$can_close = class_exists('OBN_Fiscal_Period_Policy') && OBN_Fiscal_Period_Policy::can_close();

if (!function_exists('obn_fp_status_badge')) {
    function obn_fp_status_badge($status) {
        $classes = [
            'OPEN' => 'bg-green-100 text-green-800',
            'SOFT_CLOSED' => 'bg-amber-100 text-amber-800',
            'HARD_CLOSED' => 'bg-rose-100 text-rose-800',
        ];
        $labels = [
            'OPEN' => 'Open',
            'SOFT_CLOSED' => 'Soft Closed',
            'HARD_CLOSED' => 'Hard Closed',
        ];
        $class = $classes[$status] ?? 'bg-gray-100 text-gray-800';
        $label = $labels[$status] ?? $status;
        return '<span class="px-2.5 py-1 rounded-full text-xs font-bold ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }
}
?>

<div class="obn-card p-6 !pt-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <h3 class="text-2xl font-bold text-gray-800">Fiscal Periods</h3>
            <p class="text-sm text-gray-500 mt-1">Manage posting locks for monthly, quarterly, and fiscal-year accounting periods.</p>
        </div>
        <?php if ($can_close): ?>
            <button id="obn-fp-open-create" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded font-semibold shadow-sm">
                + Create Period
            </button>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-green-50 border border-green-100 p-4 rounded-xl">
            <div class="font-bold text-green-800">Open</div>
            <div class="text-sm text-green-700">Posting, reversals, and adjustments are allowed.</div>
        </div>
        <div class="bg-amber-50 border border-amber-100 p-4 rounded-xl">
            <div class="font-bold text-amber-800">Soft Closed</div>
            <div class="text-sm text-amber-700">New postings are blocked. Reopen requires approval and audit reason.</div>
        </div>
        <div class="bg-rose-50 border border-rose-100 p-4 rounded-xl">
            <div class="font-bold text-rose-800">Hard Closed</div>
            <div class="text-sm text-rose-700">Fully locked. Reopen requires Super Admin override.</div>
        </div>
    </div>

    <div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
        <table id="obn-fiscal-period-table" class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Period</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Start Date</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">End Date</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Closed By</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Closed At</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (!empty($periods['data'])): ?>
                    <?php foreach ($periods['data'] as $period): ?>
                        <tr class="hover:bg-gray-50" data-id="<?php echo esc_attr($period['id']); ?>" data-status="<?php echo esc_attr($period['status']); ?>">
                            <td class="px-4 py-3">
                                <div class="font-bold text-gray-800"><?php echo esc_html($period['period_name']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo esc_html($period['period_type']); ?></div>
                            </td>
                            <td class="px-4 py-3 text-gray-600"><?php echo esc_html($period['period_start']); ?></td>
                            <td class="px-4 py-3 text-gray-600"><?php echo esc_html($period['period_end']); ?></td>
                            <td class="px-4 py-3"><?php echo obn_fp_status_badge($period['status']); ?></td>
                            <td class="px-4 py-3 text-gray-600"><?php echo esc_html($period['closed_by_name'] ?: '-'); ?></td>
                            <td class="px-4 py-3 text-gray-600"><?php echo esc_html($period['closed_at'] ?: '-'); ?></td>
                            <td class="px-4 py-3 text-right no-export">
                                <?php if ($period['status'] === 'OPEN' && $can_close): ?>
                                    <button class="obn-fp-open-close px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs font-bold" data-id="<?php echo esc_attr($period['id']); ?>" data-close-type="soft">
                                        Close
                                    </button>
                                <?php elseif ($period['status'] === 'SOFT_CLOSED' && $can_close): ?>
                                    <button class="obn-fp-open-reopen px-3 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded text-xs font-bold mr-2" data-id="<?php echo esc_attr($period['id']); ?>">
                                        Reopen
                                    </button>
                                    <button class="obn-fp-open-close px-3 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded text-xs font-bold" data-id="<?php echo esc_attr($period['id']); ?>" data-close-type="hard">
                                        Hard Close
                                    </button>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500 font-semibold">View Only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">No fiscal periods found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-4 text-sm text-gray-500">
        Showing <?php echo esc_html(count($periods['data'])); ?> of <?php echo esc_html($periods['total']); ?> periods.
    </div>
</div>

<div id="obn-fp-modal-backdrop" class="hidden fixed inset-0 bg-black bg-opacity-40 z-40"></div>

<div id="obn-fp-create-modal" class="hidden fixed z-50 inset-x-0 top-16 mx-auto bg-white rounded-xl shadow-2xl border border-gray-200 max-w-xl p-6">
    <h4 class="text-xl font-bold text-gray-800 mb-4">Create Fiscal Period</h4>
    <form id="obn-fp-create-form">
        <input type="hidden" name="action" value="obn_fiscal_period_create">
        <input type="hidden" name="security" value="<?php echo esc_attr($nonce); ?>">
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Period Name</label>
                <input name="period_name" class="w-full px-4 py-2 border rounded" placeholder="January 2026" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Type</label>
                <select name="period_type" class="w-full px-4 py-2 border rounded">
                    <option value="MONTH">Month</option>
                    <option value="QUARTER">Quarter</option>
                    <option value="FISCAL_YEAR">Fiscal Year</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
                <input class="w-full px-4 py-2 border rounded bg-gray-100" value="Open" readonly>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Start Date</label>
                <input type="date" name="period_start" class="w-full px-4 py-2 border rounded" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">End Date</label>
                <input type="date" name="period_end" class="w-full px-4 py-2 border rounded" required>
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="obn-fp-cancel px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded font-bold">Create</button>
        </div>
    </form>
</div>

<div id="obn-fp-close-modal" class="hidden fixed z-50 inset-x-0 top-16 mx-auto bg-white rounded-xl shadow-2xl border border-gray-200 max-w-lg p-6">
    <h4 class="text-xl font-bold text-gray-800 mb-4">Close Fiscal Period</h4>
    <form id="obn-fp-close-form">
        <input type="hidden" name="action" value="obn_fiscal_period_close">
        <input type="hidden" name="security" value="<?php echo esc_attr($nonce); ?>">
        <input type="hidden" name="id" id="obn-fp-close-id">
        <label class="block text-sm font-semibold text-gray-700 mb-2">Close Type</label>
        <select name="closeType" id="obn-fp-close-type" class="w-full px-4 py-2 border rounded mb-4">
            <option value="soft">Soft Close</option>
            <option value="hard">Hard Close</option>
        </select>
        <div id="obn-fp-close-warning" class="p-3 bg-amber-50 text-amber-800 rounded border border-amber-100 text-sm mb-4">
            Soft Close: No new transactions allowed.
        </div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Note</label>
        <textarea name="note" class="w-full px-4 py-2 border rounded" rows="3" placeholder="Month end closing"></textarea>
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="obn-fp-cancel px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold">Close Period</button>
        </div>
    </form>
</div>

<div id="obn-fp-reopen-modal" class="hidden fixed z-50 inset-x-0 top-16 mx-auto bg-white rounded-xl shadow-2xl border border-gray-200 max-w-lg p-6">
    <h4 class="text-xl font-bold text-gray-800 mb-4">Reopen Fiscal Period</h4>
    <form id="obn-fp-reopen-form">
        <input type="hidden" name="action" value="obn_fiscal_period_reopen">
        <input type="hidden" name="security" value="<?php echo esc_attr($nonce); ?>">
        <input type="hidden" name="id" id="obn-fp-reopen-id">
        <div class="p-3 bg-blue-50 text-blue-800 rounded border border-blue-100 text-sm mb-4">
            Audit log will record this action.
        </div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Reason <span class="text-red-500">*</span></label>
        <textarea name="reason" class="w-full px-4 py-2 border rounded" rows="3" placeholder="Late supplier invoice" required></textarea>
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="obn-fp-cancel px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded font-bold">Reopen Period</button>
        </div>
    </form>
</div>

<div id="obn-fp-override-modal" class="hidden fixed z-50 inset-x-0 top-16 mx-auto bg-white rounded-xl shadow-2xl border border-gray-200 max-w-lg p-6">
    <h4 class="text-xl font-bold text-gray-800 mb-4">Super Admin Override</h4>
    <form id="obn-fp-override-form">
        <input type="hidden" name="action" value="obn_fiscal_period_override_reopen">
        <input type="hidden" name="security" value="<?php echo esc_attr($nonce); ?>">
        <input type="hidden" name="id" id="obn-fp-override-id">
        <div class="p-3 bg-rose-50 text-rose-800 rounded border border-rose-100 text-sm mb-4">
            Hard close override is exceptional and permanently audited.
        </div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Justification <span class="text-red-500">*</span></label>
        <textarea name="justification" class="w-full px-4 py-2 border rounded" rows="3" required></textarea>
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="obn-fp-cancel px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-rose-700 hover:bg-rose-800 text-white rounded font-bold">Override Reopen</button>
        </div>
    </form>
</div>

<script>
(function($) {
    const ajaxurl = '<?php echo esc_url(admin_url("admin-ajax.php")); ?>';
    const backdrop = $('#obn-fp-modal-backdrop');

    function openModal(selector) {
        backdrop.removeClass('hidden');
        $(selector).removeClass('hidden');
    }

    function closeModals() {
        backdrop.addClass('hidden');
        $('#obn-fp-create-modal, #obn-fp-close-modal, #obn-fp-reopen-modal, #obn-fp-override-modal').addClass('hidden');
    }

    function submitForm(form) {
        $.post(ajaxurl, $(form).serialize(), function(res) {
            if (res.success) {
                alert(res.data.message);
                window.location.reload();
                return;
            }
            alert((res.data && res.data.message) || 'Fiscal period action failed.');
        }).fail(function(xhr) {
            const res = xhr.responseJSON || {};
            alert((res.data && res.data.message) || 'Fiscal period action failed.');
        });
    }

    $('#obn-fp-open-create').on('click', function() {
        openModal('#obn-fp-create-modal');
    });

    $('.obn-fp-open-close').on('click', function() {
        $('#obn-fp-close-id').val($(this).data('id'));
        $('#obn-fp-close-type').val($(this).data('close-type') || 'soft').trigger('change');
        openModal('#obn-fp-close-modal');
    });

    $('.obn-fp-open-reopen').on('click', function() {
        $('#obn-fp-reopen-id').val($(this).data('id'));
        openModal('#obn-fp-reopen-modal');
    });

    $('.obn-fp-open-override').on('click', function() {
        $('#obn-fp-override-id').val($(this).data('id'));
        openModal('#obn-fp-override-modal');
    });

    $('#obn-fp-close-type').on('change', function() {
        const warning = this.value === 'hard'
            ? 'Hard Close: Fully locked and cannot be reopened without administrative override.'
            : 'Soft Close: No new transactions allowed.';
        $('#obn-fp-close-warning').text(warning);
    });

    $('.obn-fp-cancel, #obn-fp-modal-backdrop').on('click', closeModals);
    $('#obn-fp-create-form, #obn-fp-close-form, #obn-fp-reopen-form, #obn-fp-override-form').on('submit', function(e) {
        e.preventDefault();
        submitForm(this);
    });
})(jQuery);
</script>
