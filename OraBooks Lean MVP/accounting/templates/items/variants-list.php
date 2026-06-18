<?php
/**
 * Variants List Template
 */
if (!defined('ABSPATH'))
    exit;
global $wpdb;
$variants_table = $wpdb->prefix . 'orabooks_db_variants';
$variants = $wpdb->get_results("SELECT * FROM {$variants_table} ORDER BY id DESC");
?>
<div class="obn-card p-6 !pt-4">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-2xl font-bold text-gray-800">Item Variants</h3>
        <div class="flex gap-2">
            <button type="button" id="acc-show-variant-add"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-plus-circle"></i> Add Variant
            </button>
            <button type="button" onclick="showView('obn-view-view-items')"
                class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-semibold transition-all shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> Back
            </button>
        </div>
    </div>

    <div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
        <table class="w-full text-sm" id="acc-variants-table">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">#</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Variant Code</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Variant Name</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Description</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-700 no-export">Status</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-700 no-export">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($variants):
                    $cnt = 1;
                    foreach ($variants as $v): ?>
                        <tr data-id="<?php echo esc_attr($v->id); ?>" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-600"><?php echo esc_html($cnt++); ?></td>
                            <td class="px-4 py-3 text-gray-600 font-mono font-medium"><?php echo esc_html($v->variant_code); ?>
                            </td>
                            <td class="px-4 py-3 text-gray-800 font-medium"><?php echo esc_html($v->variant_name); ?></td>
                            <td class="px-4 py-3 text-gray-500 max-w-xs truncate"><?php echo esc_html($v->description); ?></td>
                            <td class="px-4 py-3 text-center no-export">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" class="obn-toggle-variant-status sr-only peer"
                                        data-id="<?php echo esc_attr($v->id); ?>"
                                        data-status="<?php echo esc_attr($v->status); ?>"
                                        data-nonce="<?php echo esc_attr(wp_create_nonce('obn_auth_nonce')); ?>" <?php echo ($v->status == 1) ? 'checked' : ''; ?>>
                                    <div
                                        class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
                                    </div>
                                </label>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2 flex justify-end no-export">
                                <button
                                    class="obn-edit-variant-btn px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium transition"
                                    data-id="<?php echo esc_attr($v->id); ?>">Edit</button>
                                <button
                                    class="obn-delete-variant-btn px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium transition"
                                    data-id="<?php echo esc_attr($v->id); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">No variants found. Click "Add Variant"
                            to create one.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Variant Modal -->
<div id="acc-variant-modal"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[999999] hidden">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <h3 class="text-xl font-bold text-gray-800" id="acc-variant-modal-title">Add Variant</h3>
            <button type="button" class="obn-close-variant-modal text-gray-400 hover:text-gray-600"><i
                    class="fa-solid fa-times text-xl"></i></button>
        </div>
        <form id="acc-variant-form" class="p-6">
            <input type="hidden" name="action" value="obn_save_variant">
            <input type="hidden" name="id" id="acc_variant_id" value="0">
            <input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('obn_auth_nonce')); ?>">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Variant Name <span
                            class="text-red-500">*</span></label>
                    <input type="text" name="variant_name" id="acc_variant_name"
                        class="w-full px-4 py-2 border rounded-lg" placeholder="e.g., Small, Large, Red, Blue" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Variant Code <span
                            class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <input type="text" name="variant_code" id="acc_variant_code"
                            class="w-full px-4 py-2 border rounded-lg" placeholder="Auto-generated" required>
                        <button type="button" id="acc-generate-code"
                            class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg border text-sm"><i
                                class="fa-solid fa-rotate"></i></button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <textarea name="description" id="acc_variant_description" class="w-full px-4 py-2 border rounded-lg"
                        rows="3" placeholder="Optional description..."></textarea>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                    <input type="hidden" name="action" value="obn_save_variant">
                    <?php // Action field retained, duplicate removed ?>
                <button type="submit" id="acc-variant-save"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-lg font-semibold transition-all flex items-center">
                    <i class="fa-solid fa-save mr-2"></i> Save Variant
                </button>
                <button type="button"
                    class="obn-close-variant-modal bg-white border border-gray-300 hover:bg-gray-50 text-gray-800 px-6 py-2.5 rounded-lg font-semibold">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    jQuery(document).ready(function ($) {
        // Open add variant modal (auto-generate code)
        $(document).on('click', '#acc-show-variant-add', function () {
            $('#acc-variant-form')[0].reset();
            $('#acc_variant_id').val('0');
            $('#acc-variant-modal-title').text('Add Variant');
            $('#acc-variant-modal').removeClass('hidden');

            // Auto-generate variant code when modal opens
                console.log('Generating variant code...');
                $.post(obn_ajax.ajax_url, {
                    action: 'obn_generate_variant_code',
                    security: obn_ajax.auth_nonce
                }, function (response) {
                    console.log('Variant code response:', response);
                    if (response.success) $('#acc_variant_code').val(response.data.code);
                    else console.error('Failed to generate code');
                });
        });

        // Close modal
        $(document).on('click', '.obn-close-variant-modal', function () {
            $('#acc-variant-modal').addClass('hidden');
        });

        // Close on overlay click
        $('#acc-variant-modal').on('click', function (e) {
            if (e.target === this) $(this).addClass('hidden');
        });

        // Save variant
        $('#acc-variant-form').on('submit', function (e) {
            e.preventDefault();
            var btn = $('#acc-variant-save');
            btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Saving...');
            $.post(obn_ajax.ajax_url, $(this).serialize(), function (response) {
                if (response.success) {
                    $('#acc-variant-modal').addClass('hidden');
                    location.reload();
                } else {
                    alert(response.data || 'Failed to save variant.');
                    btn.prop('disabled', false).html('<i class="fa-solid fa-save mr-2"></i> Save Variant');
                }
            }).fail(function () {
                alert('Request failed.');
                btn.prop('disabled', false).html('<i class="fa-solid fa-save mr-2"></i> Save Variant');
            });
        });

        // Edit variant
        $(document).on('click', '.obn-edit-variant-btn', function () {
            var id = $(this).data('id');
            // Load variant data
            var row = $(this).closest('tr');
            var cells = row.find('td');
            $('#acc_variant_id').val(id);
            // We'd need an AJAX call to get full variant data, for now just show the form
            $.post(obn_ajax.ajax_url, {
                action: 'obn_get_variant',
                id: id,
                security: obn_ajax.auth_nonce
            }, function (response) {
                if (response.success) {
                    var v = response.data;
                    $('#acc_variant_name').val(v.variant_name);
                    $('#acc_variant_code').val(v.variant_code);
                    $('#acc_variant_description').val(v.description);
                    $('#acc-variant-modal-title').text('Edit Variant');
                    $('#acc-variant-modal').removeClass('hidden');
                } else {
                    alert('Failed to load variant.');
                }
            });
        });

        // Delete variant
        $(document).on('click', '.obn-delete-variant-btn', function () {
            if (!confirm('Are you sure you want to delete this variant?')) return;
            var id = $(this).data('id');
            $.post(obn_ajax.ajax_url, {
                action: 'obn_delete_variant',
                id: id,
                security: obn_ajax.auth_nonce
            }, function (response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || 'Delete failed.');
                }
            });
        });

        // Toggle variant status
        $(document).on('change', '.obn-toggle-variant-status', function () {
            var checkbox = $(this);
            $.post(obn_ajax.ajax_url, {
                action: 'obn_update_variant_status',
                id: checkbox.data('id'),
                status: checkbox.prop('checked') ? 1 : 0,
                security: checkbox.data('nonce')
            }, function (response) {
                if (!response.success) {
                    checkbox.prop('checked', !checkbox.prop('checked'));
                }
            });
        });
    });
</script>