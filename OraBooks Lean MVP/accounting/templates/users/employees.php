<?php
if (!defined('ABSPATH')) exit;

$employees = OBN_Employees::get_employees();
$roles_list = OBN_Roles::get_roles();
?>
<div class="obn-view-section" id="obn-view-employees">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800"><i class="fa-solid fa-users mr-2 text-blue-600"></i> Employees Management</h2>
        <div class="breadcrumb text-sm text-gray-500">
            <a href="#" class="obn-nav-link hover:text-blue-600" data-target="dashboard">Dashboard</a>
            <span class="mx-2">/</span>
            <a href="#" class="obn-nav-link hover:text-blue-600" data-target="users">Users</a>
            <span class="mx-2">/</span>
            <span class="text-gray-800">Employees</span>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-800">All Employees</h3>
            <button type="button" onclick="showAddEmployeeModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-5 rounded-lg shadow-lg hover:shadow-xl transition-all flex items-center text-sm">
                <i class="fa-solid fa-plus mr-2"></i> Add Employee
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="obn-ac-employees-table">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-gray-600 text-sm uppercase">
                        <th class="px-6 py-4 font-bold">Code</th>
                        <th class="px-6 py-4 font-bold">Name</th>
                        <th class="px-6 py-4 font-bold">Email</th>
                        <th class="px-6 py-4 font-bold">Mobile</th>
                        <th class="px-6 py-4 font-bold">Role</th>
                        <th class="px-6 py-4 font-bold">Status</th>
                        <th class="px-6 py-4 font-bold text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($employees)): ?>
                        <?php foreach ($employees as $emp): ?>
                            <tr class="border-t border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 text-sm font-mono text-gray-500"><?php echo esc_html($emp['employee_code']); ?></td>
                                <td class="px-6 py-4 font-bold text-gray-800"><?php echo esc_html($emp['first_name'] . ' ' . $emp['last_name']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo esc_html($emp['email'] ?: '-'); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo esc_html($emp['mobile'] ?: '-'); ?></td>
                                <td class="px-6 py-4"><span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs font-bold"><?php echo esc_html($emp['role_name'] ?: '-'); ?></span></td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold <?php echo $emp['status'] == 1 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                        <?php echo $emp['status'] == 1 ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <button onclick="editEmployee(<?php echo $emp['id']; ?>)" class="text-blue-600 hover:bg-blue-50 p-2 rounded-md transition-all mr-1" title="Edit"><i class="fa-solid fa-edit"></i></button>
                                    <button onclick="toggleEmployeeStatus(<?php echo $emp['id']; ?>, <?php echo $emp['status']; ?>)" class="<?php echo $emp['status'] == 1 ? 'text-red-600 hover:bg-red-50' : 'text-green-600 hover:bg-green-50'; ?> p-2 rounded-md transition-all" title="<?php echo $emp['status'] == 1 ? 'Deactivate' : 'Activate'; ?>">
                                        <i class="fa-solid fa-<?php echo $emp['status'] == 1 ? 'ban' : 'check'; ?>"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="px-6 py-12 text-center text-gray-500 italic">No employees found. Click "Add Employee" to create one.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Employee Modal -->
<div id="obn-ac-add-emp-modal" class="fixed inset-0 z-[999999] hidden items-center justify-center overflow-y-auto">
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm" id="obn-ac-add-emp-overlay"></div>
    <div class="relative inline-block align-middle bg-white rounded-2xl text-left overflow-hidden shadow-2xl w-full max-w-2xl mx-auto border border-gray-100 my-8">
        <form id="obn-ac-add-emp-form">
            <input type="hidden" name="action" value="obn_ac_add_employee">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('frontend_ajax_nonce'); ?>">
            <div class="bg-white px-6 py-5">
                <div class="flex items-center justify-between mb-6 border-b border-gray-100 pb-4">
                    <h3 class="text-xl font-bold text-gray-900">Add Employee</h3>
                    <button type="button" class="obn-ac-close-emp-modal w-10 h-10 flex items-center justify-center rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all"><i class="fa-solid fa-times text-xl"></i></button>
                </div>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" required class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Last Name</label>
                            <input type="text" name="last_name" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Email</label>
                            <input type="email" name="email" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Mobile</label>
                            <input type="text" name="mobile" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Phone</label>
                            <input type="text" name="phone" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Role</label>
                            <select name="role_id" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                                <option value="">Select Role</option>
                                <?php foreach ($roles_list as $rl): ?>
                                    <option value="<?php echo $rl['id']; ?>"><?php echo esc_html($rl['role_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Hire Date</label>
                            <input type="date" name="hire_date" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Salary</label>
                            <input type="number" step="0.01" name="salary" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Address</label>
                        <textarea name="address" rows="2" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3"></textarea>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">City</label>
                            <input type="text" name="city" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">State</label>
                            <input type="text" name="state" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Country</label>
                            <input type="text" name="country" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
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
                <button type="button" class="obn-ac-close-emp-modal px-5 py-2.5 text-sm font-bold text-gray-600 hover:text-gray-900 border border-gray-200 rounded-xl hover:bg-white transition-all">Cancel</button>
                <button type="submit" class="obn-ac-emp-save-btn px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl text-sm font-bold shadow-lg transition-all flex items-center"><i class="fa-solid fa-check-circle mr-2"></i> Save Employee</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Employee Modal -->
<div id="obn-ac-edit-emp-modal" class="fixed inset-0 z-[999999] hidden items-center justify-center overflow-y-auto">
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm" id="obn-ac-edit-emp-overlay"></div>
    <div class="relative inline-block align-middle bg-white rounded-2xl text-left overflow-hidden shadow-2xl w-full max-w-2xl mx-auto border border-gray-100 my-8">
        <form id="obn-ac-edit-emp-form">
            <input type="hidden" name="action" value="obn_ac_update_employee">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('frontend_ajax_nonce'); ?>">
            <input type="hidden" name="id" id="obn-ac-edit-emp-id">
            <div class="bg-white px-6 py-5">
                <div class="flex items-center justify-between mb-6 border-b border-gray-100 pb-4">
                    <h3 class="text-xl font-bold text-gray-900">Edit Employee</h3>
                    <button type="button" class="obn-ac-close-emp-modal w-10 h-10 flex items-center justify-center rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all"><i class="fa-solid fa-times text-xl"></i></button>
                </div>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" id="obn-ac-edit-emp-fn" required class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Last Name</label>
                            <input type="text" name="last_name" id="obn-ac-edit-emp-ln" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Email</label>
                            <input type="email" name="email" id="obn-ac-edit-emp-email" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Mobile</label>
                            <input type="text" name="mobile" id="obn-ac-edit-emp-mobile" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Phone</label>
                            <input type="text" name="phone" id="obn-ac-edit-emp-phone" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Role</label>
                            <select name="role_id" id="obn-ac-edit-emp-role" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                                <option value="">Select Role</option>
                                <?php foreach ($roles_list as $rl): ?>
                                    <option value="<?php echo $rl['id']; ?>"><?php echo esc_html($rl['role_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Hire Date</label>
                            <input type="date" name="hire_date" id="obn-ac-edit-emp-hire" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Salary</label>
                            <input type="number" step="0.01" name="salary" id="obn-ac-edit-emp-salary" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Address</label>
                        <textarea name="address" id="obn-ac-edit-emp-addr" rows="2" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3"></textarea>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">City</label>
                            <input type="text" name="city" id="obn-ac-edit-emp-city" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">State</label>
                            <input type="text" name="state" id="obn-ac-edit-emp-state" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Country</label>
                            <input type="text" name="country" id="obn-ac-edit-emp-country" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
                        <select name="status" id="obn-ac-edit-emp-status" class="w-full rounded-xl border-gray-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 shadow-sm py-2.5 px-3">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-4 flex items-center justify-end gap-4 border-t border-gray-100">
                <button type="button" class="obn-ac-close-emp-modal px-5 py-2.5 text-sm font-bold text-gray-600 hover:text-gray-900 border border-gray-200 rounded-xl hover:bg-white transition-all">Cancel</button>
                <button type="submit" class="obn-ac-emp-save-btn px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl text-sm font-bold shadow-lg transition-all flex items-center"><i class="fa-solid fa-check-circle mr-2"></i> Update Employee</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    function closeAddEmp() { $('#obn-ac-add-emp-modal').addClass('hidden').removeClass('flex'); }
    function closeEditEmp() { $('#obn-ac-edit-emp-modal').addClass('hidden').removeClass('flex'); }

    $('body').on('click', '.obn-ac-close-emp-modal, #obn-ac-add-emp-overlay, #obn-ac-edit-emp-overlay', function(e) {
        e.preventDefault(); closeAddEmp(); closeEditEmp();
    });
    $(document).on('keydown', function(e) { if (e.key === 'Escape') { closeAddEmp(); closeEditEmp(); } });

    window.showAddEmployeeModal = function() { $('#obn-ac-add-emp-modal').removeClass('hidden').addClass('flex'); };

    $('#obn-ac-add-emp-form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('.obn-ac-emp-save-btn');
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Saving...');
        $.post(obn_ajax.ajax_url, $(this).serialize(), function(res) {
            if (res.success) { Swal.fire('Success', res.data.message, 'success').then(function() { location.reload(); }); }
            else { Swal.fire('Error', res.data.message || 'Failed', 'error'); }
        }).fail(function() { Swal.fire('Error', 'Network error', 'error'); })
        .always(function() { $btn.prop('disabled', false).html('<i class="fa-solid fa-check-circle mr-2"></i> Save Employee'); });
    });

    window.editEmployee = function(id) {
        $.post(obn_ajax.ajax_url, { action: 'obn_ac_get_employee', id: id }, function(res) {
            if (res.success) {
                var e = res.data;
                $('#obn-ac-edit-emp-id').val(e.id);
                $('#obn-ac-edit-emp-fn').val(e.first_name);
                $('#obn-ac-edit-emp-ln').val(e.last_name || '');
                $('#obn-ac-edit-emp-email').val(e.email || '');
                $('#obn-ac-edit-emp-mobile').val(e.mobile || '');
                $('#obn-ac-edit-emp-phone').val(e.phone || '');
                $('#obn-ac-edit-emp-role').val(e.role_id || '');
                $('#obn-ac-edit-emp-hire').val(e.hire_date || '');
                $('#obn-ac-edit-emp-salary').val(e.salary || '');
                $('#obn-ac-edit-emp-addr').val(e.address || '');
                $('#obn-ac-edit-emp-city').val(e.city || '');
                $('#obn-ac-edit-emp-state').val(e.state || '');
                $('#obn-ac-edit-emp-country').val(e.country || '');
                $('#obn-ac-edit-emp-status').val(e.status);
                $('#obn-ac-edit-emp-modal').removeClass('hidden').addClass('flex');
            }
        });
    };

    $('#obn-ac-edit-emp-form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('.obn-ac-emp-save-btn');
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Updating...');
        $.post(obn_ajax.ajax_url, $(this).serialize(), function(res) {
            if (res.success) { Swal.fire('Success', res.data.message, 'success').then(function() { location.reload(); }); }
            else { Swal.fire('Error', res.data.message || 'Failed', 'error'); }
        }).fail(function() { Swal.fire('Error', 'Network error', 'error'); })
        .always(function() { $btn.prop('disabled', false).html('<i class="fa-solid fa-check-circle mr-2"></i> Update Employee'); });
    });

    window.toggleEmployeeStatus = function(id, currentStatus) {
        var action = currentStatus == 1 ? 'deactivate' : 'activate';
        Swal.fire({ title: 'Are you sure?', text: 'Do you want to ' + action + ' this employee?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#1569B3', cancelButtonColor: '#d33', confirmButtonText: 'Yes, ' + action + ' it!' }).then(function(result) {
            if (result.isConfirmed) {
                $.post(obn_ajax.ajax_url, { action: 'obn_ac_toggle_employee_status', id: id, nonce: obn_ajax.nonce }, function(res) {
                    if (res.success) { Swal.fire('Success', res.data.message, 'success').then(function() { location.reload(); }); }
                    else { Swal.fire('Error', res.data.message || 'Failed', 'error'); }
                });
            }
        });
    };
});
</script>
