<?php
if (!defined('ABSPATH')) {
    exit;
}

$users = Frontend_Inventory_Permissions::get_all_users();
$sidebar_items = Frontend_Inventory_Permissions::get_all_sidebar_items();

// Group sidebar items by module and parent
$grouped_items = [];
$seen_ids = []; // To prevent duplicates in the UI

foreach ($sidebar_items as $item) {
    if (in_array($item['id'], $seen_ids))
        continue;
    $seen_ids[] = $item['id'];

    $module = ucfirst($item['module']);
    if (!isset($grouped_items[$module])) {
        $grouped_items[$module] = [];
    }

    if ($item['parent'] == 0) {
        if (!isset($grouped_items[$module][$item['id']])) {
            $grouped_items[$module][$item['id']] = $item;
            $grouped_items[$module][$item['id']]['children'] = [];
        } else {
            // Real parent data found after placeholder was created by a child
            $children = $grouped_items[$module][$item['id']]['children'];
            $grouped_items[$module][$item['id']] = $item;
            $grouped_items[$module][$item['id']]['children'] = $children;
        }
    } else {
        if (!isset($grouped_items[$module][$item['parent']])) {
            $grouped_items[$module][$item['parent']] = ['children' => []];
        }
        $grouped_items[$module][$item['parent']]['children'][] = $item;
    }
}
?>

<div class="user-permissions-page">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">User Permissions</h2>
        <div class="breadcrumb text-sm text-gray-500">
            <a href="?view=dashboard" class="hover:text-blue-600">Dashboard</a>
            <span class="mx-2">/</span>
            <span class="text-gray-800">Permissions</span>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-bottom bg-gray-50">
            <div class="max-w-md">
                <label for="permission-user-select" class="block text-sm font-semibold text-gray-700 mb-2">Select
                    User</label>
                <select id="permission-user-select"
                    class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm transition-all"
                    style="width: 100% !important;">
                    <option value="">-- Choose a User --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user->ID; ?>">
                            <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div id="permissions-container" class="p-6 opacity-50 pointer-events-none transition-all">
            <form id="user-permissions-form">
                <input type="hidden" name="user_id" id="hidden_user_id" value="">

                <div class="mb-6 pb-4 border-b border-gray-100 flex items-center justify-between">
                    <label class="inline-flex items-center cursor-pointer group">
                        <input type="checkbox" id="select-all-permissions"
                            class="w-6 h-6 text-blue-600 border-gray-300 rounded focus:ring-blue-500 transition-all">
                        <span
                            class="ml-3 font-bold text-lg text-gray-700 group-hover:text-blue-600 transition-colors">Select
                            All Features</span>
                    </label>
                    <span class="text-xs text-gray-400 italic">Toggle all available modules and sub-features</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <?php foreach ($grouped_items as $module => $parents): ?>
                        <div class="module-section">
                            <h3
                                class="text-lg font-bold text-blue-800 mb-4 pb-2 border-b-2 border-blue-100 flex items-center">
                                <i class="fa-solid fa-layer-group mr-2"></i> <?php echo $module; ?> Module
                            </h3>

                            <div class="space-y-6">
                                <?php foreach ($parents as $parent_id => $parent):
                                    if (!isset($parent['menu_title']))
                                        continue; // Skip placeholders
                                    ?>
                                    <div class="parent-item-group bg-slate-50 p-4 rounded-lg border border-slate-100">
                                        <div class="flex items-center mb-3">
                                            <label class="inline-flex items-center cursor-pointer">
                                                <input type="checkbox" name="sidebar_ids[]" value="<?php echo $parent['id']; ?>"
                                                    class="parent-checkbox w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                                <span class="ml-3 font-bold text-gray-800 text-md">
                                                    <i
                                                        class="<?php echo esc_attr($parent['icon']); ?> mr-2 text-blue-500 w-5 text-center"></i>
                                                    <?php echo esc_html($parent['menu_title']); ?>
                                                </span>
                                            </label>
                                        </div>

                                        <?php if (!empty($parent['children'])): ?>
                                            <div class="ml-8 grid grid-cols-1 gap-2 mt-2">
                                                <?php foreach ($parent['children'] as $child): ?>
                                                    <label
                                                        class="inline-flex items-center cursor-pointer p-1 hover:bg-white rounded transition-colors">
                                                        <input type="checkbox" name="sidebar_ids[]" value="<?php echo $child['id']; ?>"
                                                            class="child-checkbox w-4 h-4 text-blue-500 border-gray-300 rounded focus:ring-blue-500">
                                                        <span class="ml-3 text-sm text-gray-600">
                                                            <i
                                                                class="<?php echo esc_attr($child['icon']); ?> mr-2 w-4 text-center opacity-70"></i>
                                                            <?php echo esc_html($child['menu_title']); ?>
                                                        </span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-100 flex justify-end">
                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-1 flex items-center">
                        <i class="fa-solid fa-save mr-2"></i> Save Permissions
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Permissions List -->
    <div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
            <h3 class="text-xl font-bold text-gray-800">Assigned Permissions</h3>
            <button id="refresh-permissions-list" class="text-blue-600 hover:text-blue-800 text-sm font-semibold">
                <i class="fa-solid fa-sync mr-1"></i> Refresh
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-sm uppercase">
                        <th class="px-6 py-4 font-bold">User</th>
                        <th class="px-6 py-4 font-bold">Permitted Features</th>
                        <th class="px-6 py-4 font-bold text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="permissions-list-body">
                    <!-- Loaded via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function ($) {
        const $userSelect = $('#permission-user-select');
        const $container = $('#permissions-container');
        const $form = $('#user-permissions-form');
        const $hiddenUserId = $('#hidden_user_id');
        const $listBody = $('#permissions-list-body');

        function loadPermissionsList() {
            $.ajax({
                url: frontend_inventory_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_all_assigned_permissions',
                    nonce: frontend_inventory_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        let html = '';
                        if (response.data.length === 0) {
                            html = '<tr><td colspan="3" class="px-6 py-8 text-center text-gray-500 italic">No custom permissions assigned yet.</td></tr>';
                        } else {
                            response.data.forEach(item => {
                                html += `
                                <tr class="border-t border-gray-100 hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-800">${item.display_name}</div>
                                        <div class="text-xs text-gray-500">${item.user_email}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap gap-1">
                                            ${item.feature_count} features assigned
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button class="edit-permission text-blue-600 hover:bg-blue-50 p-2 rounded-md transition-all mr-2" data-user-id="${item.user_id}">
                                            <i class="fa-solid fa-edit"></i>
                                        </button>
                                        <button class="delete-permission text-red-600 hover:bg-red-50 p-2 rounded-md transition-all" data-user-id="${item.user_id}">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
                            });
                        }
                        $listBody.html(html);
                    }
                }
            });
        }

        loadPermissionsList();

        $('#refresh-permissions-list').on('click', function (e) {
            e.preventDefault();
            console.log('OBN Debug: Refreshing inventory permissions list');
            loadPermissionsList();
        });

        // Edit button in list
        $(document).on('click', '.edit-permission', function () {
            const userId = $(this).data('user-id');
            $userSelect.val(userId).trigger('change');
            $('html, body').animate({ scrollTop: 0 }, 'slow');
        });

        // Delete button in list
        $(document).on('click', '.delete-permission', function () {
            const userId = $(this).data('user-id');
            Swal.fire({
                title: 'Are you sure?',
                text: "This will remove all custom permissions for this user. They will no longer see any features unless they are an admin.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#1569B3',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: frontend_inventory_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'delete_user_permissions',
                            user_id: userId,
                            nonce: frontend_inventory_ajax.nonce
                        },
                        success: function (response) {
                            if (response.success) {
                                Swal.fire('Deleted!', response.data.message, 'success');
                                loadPermissionsList();
                                if ($userSelect.val() == userId) {
                                    $userSelect.val('').trigger('change');
                                }
                            } else {
                                Swal.fire('Error', response.data.message, 'error');
                            }
                        }
                    });
                }
            });
        });

        // Initialize Select2 if available
        if ($.fn.select2) {
            $userSelect.select2({
                placeholder: 'Search for a user...',
                allowClear: true,
                dropdownParent: $('.user-permissions-page')
            });
        }

        $userSelect.on('change', function () {
            const userId = $(this).val();
            if (!userId) {
                $container.addClass('opacity-50 pointer-events-none');
                $form[0].reset();
                $hiddenUserId.val('');
                return;
            }

            $hiddenUserId.val(userId);

            // Fetch permissions for this user
            $.ajax({
                url: frontend_inventory_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_user_permissions',
                    user_id: userId,
                    nonce: frontend_inventory_ajax.nonce
                },
                beforeSend: function () {
                    $container.addClass('opacity-50 pointer-events-none');
                },
                success: function (response) {
                    if (response.success) {
                        $container.removeClass('opacity-50 pointer-events-none');
                        $form[0].reset();

                        const sidebarIds = response.data.sidebar_ids;
                        if (sidebarIds && Array.isArray(sidebarIds)) {
                            sidebarIds.forEach(id => {
                                $form.find(`input[value="${id}"]`).prop('checked', true);
                            });
                        }
                        updateSelectAllState();
                    } else {
                        Swal.fire('Error', response.data.message || 'Failed to fetch permissions', 'error');
                    }
                },
                error: function () {
                    Swal.fire('Error', 'Network error or server failed.', 'error');
                },
                complete: function () {
                    // Ensure container is interactive if we have a user
                    if ($userSelect.val()) {
                        $container.removeClass('opacity-50 pointer-events-none');
                    }
                }
            });
        });

        // Select All Permissions Logic
        $('#select-all-permissions').on('change', function () {
            const isChecked = $(this).is(':checked');
            $form.find('input[type="checkbox"]').not(this).prop('checked', isChecked);
        });

        // Parent/Child Checkbox Logic
        $('.parent-checkbox').on('change', function () {
            const isChecked = $(this).is(':checked');
            $(this).closest('.parent-item-group').find('.child-checkbox').prop('checked', isChecked);
            updateSelectAllState();
        });

        $('.child-checkbox').on('change', function () {
            const $group = $(this).closest('.parent-item-group');
            const hasCheckedChild = $group.find('.child-checkbox:checked').length > 0;
            $group.find('.parent-checkbox').prop('checked', hasCheckedChild);
            updateSelectAllState();
        });

        function updateSelectAllState() {
            const allCheckboxes = $form.find('input[type="checkbox"]').not('#select-all-permissions');
            const checkedCheckboxes = allCheckboxes.filter(':checked');
            $('#select-all-permissions').prop('checked', allCheckboxes.length === checkedCheckboxes.length);
            $('#select-all-permissions').prop('indeterminate', checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length);
        }

        // Use direct click for reliability
        $form.find('button[type="submit"]').on('click', function (e) {
            e.preventDefault();

            try {
                const $btn = $(this);
                const originalBtnHtml = $btn.html();

                const userId = $hiddenUserId.val();
                if (!userId) {
                    Swal.fire('Error', 'Please select a user first.', 'error');
                    return;
                }

                const formData = $form.serialize() + '&action=save_user_permissions&nonce=' + frontend_inventory_ajax.nonce;

                $.ajax({
                    url: frontend_inventory_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    beforeSend: function () {
                        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i> Saving...');
                        Swal.fire({
                            title: 'Saving...',
                            allowOutsideClick: false,
                            didOpen: () => { Swal.showLoading(); }
                        });
                    },
                    success: function (response) {
                        if (response.success) {
                            Swal.fire('Success', response.data.message, 'success');
                            loadPermissionsList();
                        } else {
                            Swal.fire('Error', response.data.message || 'Failed to save permissions', 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Network or server error.', 'error');
                    },
                    complete: function () {
                        $btn.prop('disabled', false).html(originalBtnHtml);
                    }
                });
            } catch (err) {
                console.error('Inventory Permissions Error:', err);
            }
        });
    });
</script>

<style>
    .user-permissions-page {
        position: relative !important;
    }

    .user-permissions-page .select2-container--default .select2-selection--single {
        height: 44px !important;
        padding: 8px !important;
        border-radius: 8px !important;
        border-color: #d1d5db !important;
    }

    .user-permissions-page .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px !important;
    }

    .parent-item-group:hover {
        background-color: #f8fafc;
        border-color: #e2e8f0;
    }

    .module-section {
        background: #fff;
        padding: 1.5rem;
        border-radius: 12px;
    }

    .swal2-container {
        z-index: 9999999 !important;
    }

    .select2-dropdown {
        z-index: 999999 !important;
    }
</style>
