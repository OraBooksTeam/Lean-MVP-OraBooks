<?php
if (!defined('ABSPATH'))
    exit;
global $wpdb;
$table = $wpdb->prefix . 'orabooks_ac_depreciation_methods';
$methods = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");

$nonce = wp_create_nonce('obn_assets_action_nonce');
?>

<div class="obn-card p-6 !pt-4">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-2xl font-bold text-gray-800">Depreciation Methods</h3>
            <p class="text-gray-500 text-sm">Manage methods for calculating asset depreciation.</p>
        </div>
        <button type="button" onclick="jQuery('#obn-method-add-modal').removeClass('hidden')"
            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg shadow-md transition-all">
            <i class="fa-solid fa-plus mr-2"></i> Add Method
        </button>
    </div>

    <div class="overflow-x-auto bg-white rounded-xl border border-gray-200 shadow-sm">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider">Method Name</th>
                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider">Slug</th>
                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider">Description</th>
                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($methods):
                    foreach ($methods as $m): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 font-bold text-gray-900"><?php echo esc_html($m->name); ?></td>
                            <td class="px-6 py-4 text-gray-600 font-mono"><?php echo esc_html($m->slug); ?></td>
                            <td class="px-6 py-4 text-gray-500 max-w-xs truncate"
                                title="<?php echo esc_attr($m->description); ?>">
                                <?php echo esc_html($m->description); ?>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="px-2 py-1 rounded-full text-xs font-bold <?php echo $m->status ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>">
                                    <?php echo $m->status ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="4" class="px-6 py-10 text-center text-gray-400 italic">No methods found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Method Modal -->
<div id="obn-method-add-modal"
    class="fixed inset-0 bg-black bg-opacity-50 hidden z-[9999] flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 relative">
        <h3 class="text-xl font-bold text-gray-800 mb-6 border-b pb-2">Add Depreciation Method</h3>
        <form id="obn-method-add-form" class="space-y-4">
            <input type="hidden" name="action" value="obn_insert_depr_method">
            <input type="hidden" name="security" value="<?php echo $nonce; ?>">

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Method Name <span
                        class="text-red-500">*</span></label>
                <input type="text" name="name"
                    class="meth_name w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-100 outline-none"
                    placeholder="e.g. Double Declining" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Slug <span
                        class="text-red-500">*</span></label>
                <input type="text" name="slug" class="meth_slug w-full px-4 py-2 border rounded bg-gray-50"
                    placeholder="e.g. double_declining" readonly required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Description</label>
                <textarea name="description" class="w-full px-4 py-2 border rounded" rows="3"></textarea>
            </div>

            <div class="flex gap-2 pt-4">
                <button type="submit"
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded shadow">Save
                    Method</button>
                <button type="button" onclick="jQuery('#obn-method-add-modal').addClass('hidden')"
                    class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold py-2 rounded px-4">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    jQuery(document).ready(function ($) {
        // Slug generation
        $(document).on('input', '.meth_name', function () {
            const slug = $(this).val().toLowerCase()
                .replace(/[^a-z0-9]/g, '_')
                .replace(/_+/g, '_')
                .replace(/^_|_$/g, '');
            $('.meth_slug').val(slug);
        });

        // AJAX Submission
        $(document).on('submit', '#obn-method-add-form', function (e) {
            e.preventDefault();
            console.log('Submitting depreciation method form...');

            const $form = $(this);
            const $btn = $form.find('button[type="submit"]');

            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: obn_ajax.ajax_url,
                type: 'POST',
                data: $form.serialize(),
                success: function (res) {
                    console.log('Response:', res);
                    if (res.success) {
                        alert(res.data.message);
                        window.location.hash = 'view=depreciation-methods';
                        location.reload();
                    } else {
                        alert('Error: ' + res.data);
                        $btn.prop('disabled', false).text('Save Method');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('An error occurred while saving.');
                    $btn.prop('disabled', false).text('Save Method');
                }
            });
        });
    });
</script>