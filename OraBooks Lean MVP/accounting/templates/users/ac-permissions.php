<?php
if (!defined('ABSPATH')) exit;

$roles = OBN_Accounting_Permissions::get_all_roles();
$sidebar_items = OBN_Accounting_Permissions::get_accounting_sidebar_items();

// Group sidebar items by parent
$grouped = [];
$seen_ids = [];
foreach ($sidebar_items as $item) {
    if (in_array($item['id'], $seen_ids)) continue;
    $seen_ids[] = $item['id'];
    if ($item['parent'] == 0) {
        if (!isset($grouped[$item['id']])) {
            $grouped[$item['id']] = $item;
            $grouped[$item['id']]['children'] = [];
        } else {
            $children = $grouped[$item['id']]['children'];
            $grouped[$item['id']] = $item;
            $grouped[$item['id']]['children'] = $children;
        }
    } else {
        if (!isset($grouped[$item['parent']])) {
            $grouped[$item['parent']] = ['children' => []];
        }
        $grouped[$item['parent']]['children'][] = $item;
    }
}
?>
<div class="obn-view-section" id="obn-view-ac-permissions">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800"><i class="fa-solid fa-shield-halved mr-2 text-blue-600"></i> Accounting Permissions</h2>
        <div class="breadcrumb text-sm text-gray-500">
            <a href="#" class="obn-nav-link hover:text-blue-600" data-target="dashboard">Dashboard</a>
            <span class="mx-2">/</span>
            <a href="#" class="obn-nav-link hover:text-blue-600" data-target="users">Users</a>
            <span class="mx-2">/</span>
            <span class="text-gray-800">Permissions</span>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 bg-gray-50">
            <div class="max-w-md">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Select Role</label>
                <select id="obn-ac-perm-role-select" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm" style="width:100%!important">
                    <option value="">-- Choose a Role --</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role->id; ?>"><?php echo esc_html($role->role_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div id="obn-ac-perm-container" class="p-6 opacity-50 pointer-events-none transition-all">
            <form id="obn-ac-perm-form">
                <input type="hidden" name="role_id" id="obn-ac-perm-role-id" value="">

                <div class="mb-6 pb-4 border-b border-gray-100 flex items-center justify-between">
                    <label class="inline-flex items-center cursor-pointer group">
                        <input type="checkbox" id="obn-ac-perm-select-all" class="w-6 h-6 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="ml-3 font-bold text-lg text-gray-700 group-hover:text-blue-600 transition-colors">Select All Features</span>
                    </label>
                    <span class="text-xs text-gray-400 italic">Toggle all available modules and sub-features</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <?php foreach ($grouped as $parent_id => $parent):
                        if (!isset($parent['menu_title'])) continue;
                    ?>
                        <div class="module-section">
                            <div class="parent-item-group bg-slate-50 p-4 rounded-lg border border-slate-100">
                                <div class="flex items-center mb-3">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="sidebar_ids[]" value="<?php echo $parent['id']; ?>" class="obn-ac-parent-cb w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        <span class="ml-3 font-bold text-gray-800 text-md">
                                            <i class="<?php echo esc_attr($parent['icon']); ?> mr-2 text-blue-500 w-5 text-center"></i>
                                            <?php echo esc_html($parent['menu_title']); ?>
                                        </span>
                                    </label>
                                </div>
                                <?php if (!empty($parent['children'])): ?>
                                    <div class="ml-8 grid grid-cols-1 gap-2 mt-2">
                                        <?php foreach ($parent['children'] as $child): ?>
                                            <label class="inline-flex items-center cursor-pointer p-1 hover:bg-white rounded transition-colors">
                                                <input type="checkbox" name="sidebar_ids[]" value="<?php echo $child['id']; ?>" class="obn-ac-child-cb w-4 h-4 text-blue-500 border-gray-300 rounded focus:ring-blue-500">
                                                <span class="ml-3 text-sm text-gray-600">
                                                    <i class="<?php echo esc_attr($child['icon']); ?> mr-2 w-4 text-center opacity-70"></i>
                                                    <?php echo esc_html($child['menu_title']); ?>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-100 flex justify-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-1 flex items-center">
                        <i class="fa-solid fa-save mr-2"></i> Save Permissions
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assigned Permissions List -->
    <div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
            <h3 class="text-xl font-bold text-gray-800">Assigned Permissions</h3>
            <button id="obn-ac-perm-refresh" class="text-blue-600 hover:text-blue-800 text-sm font-semibold flex items-center gap-1 transition-colors">
                <i class="fa-solid fa-sync"></i> Refresh
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-gray-600 text-sm uppercase">
                        <th class="px-6 py-4 font-bold">Role</th>
                        <th class="px-6 py-4 font-bold">Permitted Features</th>
                        <th class="px-6 py-4 font-bold text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="obn-ac-perm-list-body">
                    <tr><td colspan="3" class="px-6 py-8 text-center text-gray-500 italic">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var $page = $('#view-ac-permissions');
    var $roleSelect = $page.find('#obn-ac-perm-role-select');
    var $container = $page.find('#obn-ac-perm-container');
    var $form = $page.find('#obn-ac-perm-form');
    var $roleId = $page.find('#obn-ac-perm-role-id');
    var $listBody = $page.find('#obn-ac-perm-list-body');

    function loadPermissionsList() {
        $.post(obn_ajax.ajax_url, { action: 'obn_ac_get_all_assigned_permissions', nonce: obn_ajax.nonce }, function(res) {
            if (res.success && res.data) {
                if (res.data.length === 0) {
                    $listBody.html('<tr><td colspan="3" class="px-6 py-8 text-center text-gray-500 italic">No permissions assigned yet.</td></tr>');
                } else {
                    var html = '';
                    res.data.forEach(function(item) {
                        html += '<tr class="border-t border-gray-100 hover:bg-gray-50 transition-colors">' +
                            '<td class="px-6 py-4 font-bold text-gray-800">' + item.role_name + '</td>' +
                            '<td class="px-6 py-4"><span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs font-bold">' + item.feature_count + ' features</span></td>' +
                            '<td class="px-6 py-4 text-center">' +
                            '<button class="obn-ac-perm-edit text-blue-600 hover:bg-blue-50 p-2 rounded-md transition-all mr-2" data-role-id="' + item.role_id + '"><i class="fa-solid fa-edit"></i></button>' +
                            '<button class="obn-ac-perm-delete text-red-600 hover:bg-red-50 p-2 rounded-md transition-all" data-role-id="' + item.role_id + '"><i class="fa-solid fa-trash"></i></button>' +
                            '</td></tr>';
                    });
                    $listBody.html(html);
                }
            }
        });
    }

    loadPermissionsList();

    $page.on('click', '#obn-ac-perm-refresh', function(e) { e.preventDefault(); loadPermissionsList(); });

    $page.on('click', '.obn-ac-perm-edit', function() {
        var roleId = $(this).data('role-id');
        $roleSelect.val(roleId).trigger('change');
        $('html, body').animate({ scrollTop: 0 }, 'slow');
    });

    $page.on('click', '.obn-ac-perm-delete', function() {
        var roleId = $(this).data('role-id');
        Swal.fire({ title: 'Are you sure?', text: 'This will remove all permissions for this role.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#1569B3', confirmButtonText: 'Yes, delete!' }).then(function(result) {
            if (result.isConfirmed) {
                $.post(obn_ajax.ajax_url, { action: 'obn_ac_delete_permissions', role_id: roleId, nonce: obn_ajax.nonce }, function(res) {
                    if (res.success) { Swal.fire('Deleted!', res.data.message, 'success'); loadPermissionsList(); if ($roleSelect.val() == roleId) $roleSelect.val('').trigger('change'); }
                    else { Swal.fire('Error', res.data.message || 'Failed', 'error'); }
                });
            }
        });
    });

    // Select All
    $page.on('change', '#obn-ac-perm-select-all', function() {
        var isChecked = $(this).is(':checked');
        $form.find('input[type="checkbox"]').not(this).prop('checked', isChecked);
    });

    // Parent/Child
    $page.on('change', '.obn-ac-parent-cb', function() {
        $(this).closest('.parent-item-group').find('.obn-ac-child-cb').prop('checked', $(this).is(':checked'));
        updateSelectAll();
    });
    $page.on('change', '.obn-ac-child-cb', function() {
        var $group = $(this).closest('.parent-item-group');
        $group.find('.obn-ac-parent-cb').prop('checked', $group.find('.obn-ac-child-cb:checked').length > 0);
        updateSelectAll();
    });

    function updateSelectAll() {
        var all = $form.find('input[type="checkbox"]').not('#obn-ac-perm-select-all');
        var checked = all.filter(':checked');
        $('#obn-ac-perm-select-all').prop('checked', all.length === checked.length);
    }

    $roleSelect.on('change', function() {
        var roleId = $(this).val();
        if (!roleId) { $container.addClass('opacity-50 pointer-events-none'); $form[0].reset(); $roleId.val(''); return; }
        $roleId.val(roleId);
        $.post(obn_ajax.ajax_url, { action: 'obn_ac_get_permissions', role_id: roleId, nonce: obn_ajax.nonce }, function(res) {
            if (res.success && res.data) {
                $container.removeClass('opacity-50 pointer-events-none');
                $form[0].reset();
                if (res.data.sidebar_ids && Array.isArray(res.data.sidebar_ids)) {
                    res.data.sidebar_ids.forEach(function(id) { $form.find('input[value="' + id + '"]').prop('checked', true); });
                }
                updateSelectAll();
            }
        });
    });

    // Save
    $form.find('button[type="submit"]').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var origHtml = $btn.html();
        if (!$roleId.val()) { Swal.fire('Error', 'Please select a role first.', 'error'); return; }
        var formData = $form.serialize() + '&action=obn_ac_save_permissions&nonce=' + obn_ajax.nonce;
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Saving...');
        $.post(obn_ajax.ajax_url, formData, function(res) {
            if (res.success) { Swal.fire('Success', res.data.message, 'success'); loadPermissionsList(); }
            else { Swal.fire('Error', res.data.message || 'Failed', 'error'); }
        }).fail(function() { Swal.fire('Error', 'Network error.', 'error'); })
        .always(function() { $btn.prop('disabled', false).html(origHtml); });
    });
});
</script>

<style>
.obn-ac-parent-cb:checked, .obn-ac-child-cb:checked { accent-color: #2563eb; }
</style>
