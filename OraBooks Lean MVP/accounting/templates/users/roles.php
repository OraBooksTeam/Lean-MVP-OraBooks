<?php
if (!defined('ABSPATH')) exit;

$roles = OBN_Roles::get_roles();

// var_dump($roles); // Debugging line to check the roles data
?>
<div class="obn-view-section" id="obn-view-ac-roles">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800"><i class="fa-solid fa-user-tag mr-2 text-blue-600"></i> Roles Management</h2>
        <div class="breadcrumb text-sm text-gray-500">
            <a href="#" class="obn-nav-link hover:text-blue-600" data-target="dashboard">Dashboard</a>
            <span class="mx-2">/</span>
            <a href="#" class="obn-nav-link hover:text-blue-600" data-target="users">Users</a>
            <span class="mx-2">/</span>
            <span class="text-gray-800">Roles</span>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-800">All Roles</h3>
            <button type="button" onclick="showAddRoleModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-5 rounded-lg shadow-lg hover:shadow-xl transition-all flex items-center text-sm">
                <i class="fa-solid fa-plus mr-2"></i> Add Role
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="obn-ac-roles-table">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-gray-600 text-sm uppercase">
                        <th class="px-6 py-4 font-bold">#</th>
                        <th class="px-6 py-4 font-bold">Role Name</th>
                        <th class="px-6 py-4 font-bold">Description</th>
                        <th class="px-6 py-4 font-bold">Status</th>
                        <th class="px-6 py-4 font-bold">Created At</th>
                        <th class="px-6 py-4 font-bold text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($roles)): ?>
                        <?php foreach ($roles as $i => $role): ?>
                            <tr class="border-t border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 text-sm text-gray-500"><?php echo $i + 1; ?></td>
                                <td class="px-6 py-4 font-bold text-gray-800"><?php echo esc_html($role['role_name']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo esc_html($role['description'] ?: '-'); ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold <?php echo $role['status'] == 1 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                        <?php echo $role['status'] == 1 ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500"><?php echo date('M d, Y', strtotime($role['created_at'])); ?></td>
                                <td class="px-6 py-4 text-center">
                                    <button onclick="editRole(<?php echo $role['id']; ?>)" class="text-blue-600 hover:bg-blue-50 p-2 rounded-md transition-all mr-1" title="Edit"><i class="fa-solid fa-edit"></i></button>
                                    <button onclick="toggleRoleStatus(<?php echo $role['id']; ?>, <?php echo $role['status']; ?>)" class="<?php echo $role['status'] == 1 ? 'text-red-600 hover:bg-red-50' : 'text-green-600 hover:bg-green-50'; ?> p-2 rounded-md transition-all" title="<?php echo $role['status'] == 1 ? 'Deactivate' : 'Activate'; ?>">
                                        <i class="fa-solid fa-<?php echo $role['status'] == 1 ? 'ban' : 'check'; ?>"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500 italic">No roles found. Click "Add Role" to create one.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Role Modal -->
<div id="obn-ac-add-role-modal" class="fixed inset-0 z-[999999] hidden items-center justify-center">
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm" id="obn-ac-add-role-overlay"></div>
    <div class="relative inline-block align-middle bg-white rounded-2xl text-left overflow-hidden shadow-2xl w-full max-w-md mx-auto border border-gray-100">
        <form id="obn-ac-add-role-form">
            <input type="hidden" name="action" value="obn_ac_add_role">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('frontend_ajax_nonce'); ?>">
            <div class="bg-white px-6 py-5">
                <div class="flex items-center justify-between mb-6 border-b border-gray-100 pb-4">
                    <h3 class="text-xl font-bold text-gray-900">Add Role</h3>
                    <button type="button" class="obn-ac-close-modal w-10 h-10 flex items-center justify-center rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all"><i class="fa-solid fa-times text-xl"></i></button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Role Name <span class="text-red-500">*</span></label>
                        <input type="text" name="role_name" required class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3" placeholder="e.g. Accountant, Manager">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                        <textarea name="description" rows="2" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3" placeholder="Optional description..."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
                        <select name="status" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-4 flex items-center justify-end gap-4 border-t border-gray-100">
                <button type="button" class="obn-ac-close-modal px-5 py-2.5 text-sm font-bold text-gray-600 hover:text-gray-900 border border-gray-200 rounded-xl hover:bg-white transition-all">Cancel</button>
                <button type="submit" class="obn-ac-save-btn px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl text-sm font-bold shadow-lg transition-all flex items-center"><i class="fa-solid fa-check-circle mr-2"></i> Save Role</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Role Modal -->
<div id="obn-ac-edit-role-modal" class="fixed inset-0 z-[999999] hidden items-center justify-center">
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm" id="obn-ac-edit-role-overlay"></div>
    <div class="relative inline-block align-middle bg-white rounded-2xl text-left overflow-hidden shadow-2xl w-full max-w-md mx-auto border border-gray-100">
        <form id="obn-ac-edit-role-form">
            <input type="hidden" name="action" value="obn_ac_update_role">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('frontend_ajax_nonce'); ?>">
            <input type="hidden" name="id" id="obn-ac-edit-role-id">
            <div class="bg-white px-6 py-5">
                <div class="flex items-center justify-between mb-6 border-b border-gray-100 pb-4">
                    <h3 class="text-xl font-bold text-gray-900">Edit Role</h3>
                    <button type="button" class="obn-ac-close-modal w-10 h-10 flex items-center justify-center rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all"><i class="fa-solid fa-times text-xl"></i></button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Role Name <span class="text-red-500">*</span></label>
                        <input type="text" name="role_name" id="obn-ac-edit-role-name" required class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                        <textarea name="description" id="obn-ac-edit-role-desc" rows="2" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
                        <select name="status" id="obn-ac-edit-role-status" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-4 flex items-center justify-end gap-4 border-t border-gray-100">
                <button type="button" class="obn-ac-close-modal px-5 py-2.5 text-sm font-bold text-gray-600 hover:text-gray-900 border border-gray-200 rounded-xl hover:bg-white transition-all">Cancel</button>
                <button type="submit" class="obn-ac-save-btn px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl text-sm font-bold shadow-lg transition-all flex items-center"><i class="fa-solid fa-check-circle mr-2"></i> Update Role</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    function showAddRoleModal() { $('#obn-ac-add-role-modal').removeClass('hidden').addClass('flex'); }
    function closeAddRoleModal() { $('#obn-ac-add-role-modal').addClass('hidden').removeClass('flex'); }
    function showEditRoleModal() { $('#obn-ac-edit-role-modal').removeClass('hidden').addClass('flex'); }
    function closeEditRoleModal() { $('#obn-ac-edit-role-modal').addClass('hidden').removeClass('flex'); }

    $('body').on('click', '.obn-ac-close-modal, #obn-ac-add-role-overlay, #obn-ac-edit-role-overlay', function(e) {
        e.preventDefault(); closeAddRoleModal(); closeEditRoleModal();
    });
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') { closeAddRoleModal(); closeEditRoleModal(); }
    });

    window.showAddRoleModal = showAddRoleModal;

    $('#obn-ac-add-role-form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('.obn-ac-save-btn');
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Saving...');
        $.post(obn_ajax.ajax_url, $(this).serialize(), function(res) {
            if (res.success) { Swal.fire('Success', res.data.message, 'success').then(function() { location.reload(); }); }
            else { Swal.fire('Error', res.data.message || 'Failed', 'error'); }
        }).fail(function() { Swal.fire('Error', 'Network error', 'error'); })
        .always(function() { $btn.prop('disabled', false).html('<i class="fa-solid fa-check-circle mr-2"></i> Save Role'); });
    });

    window.editRole = function(id) {
        $.post(obn_ajax.ajax_url, { action: 'obn_ac_get_role', id: id }, function(res) {
            if (res.success) {
                var r = res.data;
                $('#obn-ac-edit-role-id').val(r.id);
                $('#obn-ac-edit-role-name').val(r.role_name);
                $('#obn-ac-edit-role-desc').val(r.description || '');
                $('#obn-ac-edit-role-status').val(r.status);
                showEditRoleModal();
            }
        });
    };

    $('#obn-ac-edit-role-form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('.obn-ac-save-btn');
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Updating...');
        $.post(obn_ajax.ajax_url, $(this).serialize(), function(res) {
            if (res.success) { Swal.fire('Success', res.data.message, 'success').then(function() { location.reload(); }); }
            else { Swal.fire('Error', res.data.message || 'Failed', 'error'); }
        }).fail(function() { Swal.fire('Error', 'Network error', 'error'); })
        .always(function() { $btn.prop('disabled', false).html('<i class="fa-solid fa-check-circle mr-2"></i> Update Role'); });
    });

    window.toggleRoleStatus = function(id, currentStatus) {
        var action = currentStatus == 1 ? 'deactivate' : 'activate';
        Swal.fire({ title: 'Are you sure?', text: 'Do you want to ' + action + ' this role?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#1569B3', cancelButtonColor: '#d33', confirmButtonText: 'Yes, ' + action + ' it!' }).then(function(result) {
            if (result.isConfirmed) {
                $.post(obn_ajax.ajax_url, { action: 'obn_ac_toggle_role_status', id: id, nonce: obn_ajax.nonce }, function(res) {
                    if (res.success) { Swal.fire('Success', res.data.message, 'success').then(function() { location.reload(); }); }
                    else { Swal.fire('Error', res.data.message || 'Failed', 'error'); }
                });
            }
        });
    };
});
</script>
