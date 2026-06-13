<?php

if (!defined('ABSPATH')) {
    exit;
}

class Frontend_Inventory_Roles
{
    public static function init()
    {
        add_action('wp_ajax_frontend_inventory_get_roles', [__CLASS__, 'ajax_get_roles']);
        add_action('wp_ajax_frontend_inventory_add_role', [__CLASS__, 'ajax_add_role']);
        add_action('wp_ajax_frontend_inventory_update_role', [__CLASS__, 'ajax_update_role']);
        add_action('wp_ajax_frontend_inventory_toggle_role_status', [__CLASS__, 'ajax_toggle_role_status']);
        add_action('wp_ajax_frontend_inventory_get_role', [__CLASS__, 'ajax_get_role']);
    }

    /**
     * Get all roles
     */
    public static function get_roles($store_id = null)
    {
        global $wpdb;
        $table_roles = $wpdb->prefix . 'orabooks_db_roles';
        
        $sql = "SELECT * FROM $table_roles WHERE status != 2";
        
        if ($store_id) {
            $sql .= $wpdb->prepare(" AND store_id = %d", $store_id);
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get role by ID
     */
    public static function get_role($id)
    {
        global $wpdb;
        $table_roles = $wpdb->prefix . 'orabooks_db_roles';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_roles WHERE id = %d",
            $id
        ), ARRAY_A);
    }

    /**
     * Add new role
     */
    public static function add_role($data)
    {
        global $wpdb;
        $table_roles = $wpdb->prefix . 'orabooks_db_roles';
        
        $role_data = [
            'store_id' => isset($data['store_id']) ? $data['store_id'] : null,
            'role_name' => sanitize_text_field($data['role_name']),
            'description' => sanitize_textarea_field($data['description']),
            'status' => isset($data['status']) ? (int)$data['status'] : 1,
            'created_by' => get_current_user_id(),
        ];
        
        $format = ['%d', '%s', '%s', '%d', '%s'];
        
        $result = $wpdb->insert($table_roles, $role_data, $format);
        
        if ($result === false) {
            return ['success' => false, 'message' => 'Failed to add role.'];
        }
        
        return ['success' => true, 'message' => 'Role added successfully.', 'id' => $wpdb->insert_id];
    }

    /**
     * Update role
     */
    public static function update_role($id, $data)
    {
        global $wpdb;
        $table_roles = $wpdb->prefix . 'orabooks_db_roles';
        
        $role_data = [
            'role_name' => sanitize_text_field($data['role_name']),
            'description' => sanitize_textarea_field($data['description']),
            'status' => isset($data['status']) ? (int)$data['status'] : 1,
        ];
        
        $format = ['%s', '%s', '%d'];
        
        $result = $wpdb->update(
            $table_roles,
            $role_data,
            ['id' => $id],
            $format,
            ['%d']
        );
        
        if ($result === false) {
            return ['success' => false, 'message' => 'Failed to update role.'];
        }
        
        return ['success' => true, 'message' => 'Role updated successfully.'];
    }

    /**
     * Toggle role status (soft delete)
     */
    public static function toggle_role_status($id)
    {
        global $wpdb;
        $table_roles = $wpdb->prefix . 'orabooks_db_roles';
        
        $role = self::get_role($id);
        if (!$role) {
            return ['success' => false, 'message' => 'Role not found.'];
        }
        
        $new_status = $role['status'] == 1 ? 0 : 1;
        
        $result = $wpdb->update(
            $table_roles,
            ['status' => $new_status],
            ['id' => $id],
            ['%d'],
            ['%d']
        );
        
        if ($result === false) {
            return ['success' => false, 'message' => 'Failed to update role status.'];
        }
        
        $status_text = $new_status == 1 ? 'activated' : 'deactivated';
        return ['success' => true, 'message' => "Role {$status_text} successfully.", 'status' => $new_status];
    }

    /**
     * AJAX: Get roles
     */
    public static function ajax_get_roles()
    {
        if (!orabooks_can_access_inventory()) {
            wp_die('Access denied');
        }
        
        $store_id = isset($_POST['store_id']) ? (int)$_POST['store_id'] : null;
        $roles = self::get_roles($store_id);
        
        wp_send_json_success($roles);
    }

    /**
     * AJAX: Add role
     */
    public static function ajax_add_role()
    {
        if (!orabooks_can_access_inventory()) {
            wp_die('Access denied');
        }
        
        check_ajax_referer('frontend_ajax_nonce', 'nonce');
        
        $data = [
            'role_name' => isset($_POST['role_name']) ? $_POST['role_name'] : '',
            'description' => isset($_POST['description']) ? $_POST['description'] : '',
            'store_id' => isset($_POST['store_id']) ? (int)$_POST['store_id'] : null,
            'status' => isset($_POST['status']) ? (int)$_POST['status'] : 1,
        ];
        
        // Validation
        if (empty($data['role_name'])) {
            wp_send_json_error(['message' => 'Role name is required.']);
        }
        
        $result = self::add_role($data);
        
        if ($result['success']) {
            wp_send_json_success(['message' => $result['message'], 'id' => $result['id']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * AJAX: Update role
     */
    public static function ajax_update_role()
    {
        if (!orabooks_can_access_inventory()) {
            wp_die('Access denied');
        }
        
        check_ajax_referer('frontend_ajax_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => 'Role ID is required.']);
        }
        
        $data = [
            'role_name' => isset($_POST['role_name']) ? $_POST['role_name'] : '',
            'description' => isset($_POST['description']) ? $_POST['description'] : '',
            'status' => isset($_POST['status']) ? (int)$_POST['status'] : 1,
        ];
        
        // Validation
        if (empty($data['role_name'])) {
            wp_send_json_error(['message' => 'Role name is required.']);
        }
        
        $result = self::update_role($id, $data);
        
        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * AJAX: Toggle role status
     */
    public static function ajax_toggle_role_status()
    {
        if (!orabooks_can_access_inventory()) {
            wp_die('Access denied');
        }
        
        check_ajax_referer('frontend_ajax_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => 'Role ID is required.']);
        }
        
        $result = self::toggle_role_status($id);
        
        if ($result['success']) {
            wp_send_json_success(['message' => $result['message'], 'status' => $result['status']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * AJAX: Get role
     */
    public static function ajax_get_role()
    {
        if (!orabooks_can_access_inventory()) {
            wp_die('Access denied');
        }
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => 'Role ID is required.']);
        }
        
        $role = self::get_role($id);
        
        if ($role) {
            wp_send_json_success($role);
        } else {
            wp_send_json_error(['message' => 'Role not found.']);
        }
    }

    /**
     * Render roles list template
     */
    public static function render_roles_list()
    {
        if (!orabooks_can_access_inventory()) {
            echo '<div class="alert alert-danger">Access denied. You do not have permission to access this module.</div>';
            return;
        }
        
        $roles = self::get_roles();
        
        ob_start();
        ?>
        <div class="container-fluid">
            <div class="row mb-3">
                <div class="col-md-6">
                    <h3><i class="fa-solid fa-user-tag me-2"></i>Roles Management</h3>
                </div>
                <div class="col-md-6 text-end">
                    <button type="button" class="btn btn-primary" onclick="showAddRoleModal()">
                        <i class="fa-solid fa-plus me-2"></i>Add Role
                    </button>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="rolesTable">
                            <thead>
                                <tr>
                                    <th>Role Name</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($roles)): ?>
                                    <?php foreach ($roles as $role): ?>
                                        <tr>
                                            <td><?php echo esc_html($role['role_name']); ?></td>
                                            <td><?php echo esc_html($role['description'] ? $role['description'] : '-'); ?></td>
                                            <td>
                                                <span class="badge <?php echo $role['status'] == 1 ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo $role['status'] == 1 ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($role['created_at'])); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-warning" onclick="editRole(<?php echo $role['id']; ?>)">
                                                    <i class="fa-solid fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm <?php echo $role['status'] == 1 ? 'btn-danger' : 'btn-success'; ?>" 
                                                        onclick="toggleRoleStatus(<?php echo $role['id']; ?>, <?php echo $role['status']; ?>)"
                                                        title="<?php echo $role['status'] == 1 ? 'Deactivate Role' : 'Activate Role'; ?>">
                                                    <i class="fa-solid fa-<?php echo $role['status'] == 1 ? 'ban' : 'check'; ?>"></i>
                                                    <?php echo $role['status'] == 1 ? ' Deactivate' : ' Activate'; ?>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No roles found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Role Modal -->
        <div class="modal fade" id="addRoleModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addRoleForm">
                            <div class="mb-3">
                                <label for="role_name" class="form-label">Role Name *</label>
                                <input type="text" class="form-control" id="role_name" name="role_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="saveRole()">Save Role</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Role Modal -->
        <div class="modal fade" id="editRoleModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editRoleForm">
                            <input type="hidden" id="edit_role_id" name="id">
                            <div class="mb-3">
                                <label for="edit_role_name" class="form-label">Role Name *</label>
                                <input type="text" class="form-control" id="edit_role_name" name="role_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_description" class="form-label">Description</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="status">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="updateRole()">Update Role</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        function showAddRoleModal() {
            jQuery('#addRoleModal').modal('show');
        }

        function saveRole() {
            const form = document.getElementById('addRoleForm');
            const formData = new FormData(form);
            formData.append('action', 'frontend_inventory_add_role');
            formData.append('nonce', frontend_inventory_ajax.nonce);

            fetch(frontend_inventory_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success', data.data.message, 'success');
                    jQuery('#addRoleModal').modal('hide');
                    location.reload();
                } else {
                    Swal.fire('Error', data.data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'An error occurred while saving the role.', 'error');
            });
        }

        function editRole(id) {
            const formData = new FormData();
            formData.append('action', 'frontend_inventory_get_role');
            formData.append('id', id);
            formData.append('nonce', frontend_inventory_ajax.nonce);

            fetch(frontend_inventory_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const role = data.data;
                    document.getElementById('edit_role_id').value = role.id;
                    document.getElementById('edit_role_name').value = role.role_name;
                    document.getElementById('edit_description').value = role.description || '';
                    document.getElementById('edit_status').value = role.status;
                    jQuery('#editRoleModal').modal('show');
                } else {
                    Swal.fire('Error', data.data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'An error occurred while loading the role.', 'error');
            });
        }

        function updateRole() {
            const form = document.getElementById('editRoleForm');
            const formData = new FormData(form);
            formData.append('action', 'frontend_inventory_update_role');
            formData.append('nonce', frontend_inventory_ajax.nonce);

            fetch(frontend_inventory_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success', data.data.message, 'success');
                    jQuery('#editRoleModal').modal('hide');
                    location.reload();
                } else {
                    Swal.fire('Error', data.data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'An error occurred while updating the role.', 'error');
            });
        }

        function toggleRoleStatus(id, currentStatus) {
            const action = currentStatus == 1 ? 'deactivate' : 'activate';
            Swal.fire({
                title: 'Are you sure?',
                text: `Do you want to ${action} this role?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#1569B3',
                cancelButtonColor: '#d33',
                confirmButtonText: `Yes, ${action} it!`
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'frontend_inventory_toggle_role_status');
                    formData.append('id', id);
                    formData.append('nonce', frontend_inventory_ajax.nonce);

                    fetch(frontend_inventory_ajax.ajax_url, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Success', data.data.message, 'success');
                            location.reload();
                        } else {
                            Swal.fire('Error', data.data.message, 'error');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error', 'An error occurred while updating the role status.', 'error');
                    });
                }
            });
        }
        </script>
        <?php
        return ob_get_clean();
    }
}
