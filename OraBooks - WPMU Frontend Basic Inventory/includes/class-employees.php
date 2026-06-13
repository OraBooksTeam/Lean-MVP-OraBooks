<?php

if (!defined('ABSPATH')) {
    exit;
}

class Frontend_Inventory_Employees
{
    public static function init()
    {
        add_action('wp_ajax_frontend_inventory_get_employees', [__CLASS__, 'ajax_get_employees']);
        add_action('wp_ajax_frontend_inventory_add_employee', [__CLASS__, 'ajax_add_employee']);
        add_action('wp_ajax_frontend_inventory_update_employee', [__CLASS__, 'ajax_update_employee']);
        add_action('wp_ajax_frontend_inventory_toggle_employee_status', [__CLASS__, 'ajax_toggle_employee_status']);
        add_action('wp_ajax_frontend_inventory_get_employee', [__CLASS__, 'ajax_get_employee']);
        add_action('wp_ajax_frontend_inventory_get_roles_for_select', [__CLASS__, 'ajax_get_roles_for_select']);
    }

    /**
     * Get all employees
     */
    public static function get_employees($store_id = null)
    {
        global $wpdb;
        $table_employees = $wpdb->prefix . 'orabooks_db_employees';
        $table_roles = $wpdb->prefix . 'orabooks_db_roles';
        
        $sql = "SELECT e.*, r.role_name 
                FROM $table_employees e 
                LEFT JOIN $table_roles r ON e.role_id = r.id 
                WHERE e.status != 2";
        
        if ($store_id) {
            $sql .= $wpdb->prepare(" AND e.store_id = %d", $store_id);
        }
        
        $sql .= " ORDER BY e.created_at DESC";
        
        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get employee by ID
     */
    public static function get_employee($id)
    {
        global $wpdb;
        $table_employees = $wpdb->prefix . 'orabooks_db_employees';
        $table_roles = $wpdb->prefix . 'orabooks_db_roles';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT e.*, r.role_name 
             FROM $table_employees e 
             LEFT JOIN $table_roles r ON e.role_id = r.id 
             WHERE e.id = %d",
            $id
        ), ARRAY_A);
    }

    /**
     * Generate employee code
     */
    public static function generate_employee_code($store_id = null)
    {
        global $wpdb;
        $table_employees = $wpdb->prefix . 'orabooks_db_employees';
        
        $prefix = 'EMP';
        $year = date('Y');
        
        // Get the last employee code
        $sql = "SELECT employee_code FROM $table_employees WHERE employee_code LIKE %s ORDER BY id DESC LIMIT 1";
        $last_code = $wpdb->get_var($wpdb->prepare($sql, $prefix . $year . '%'));
        
        if ($last_code) {
            // Extract the number part and increment
            $number = (int)substr($last_code, -4);
            $number++;
        } else {
            $number = 1;
        }
        
        return $prefix . $year . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Add new employee
     */
    public static function add_employee($data)
    {
        global $wpdb;
        $table_employees = $wpdb->prefix . 'orabooks_db_employees';
        
        // Generate employee code if not provided
        if (empty($data['employee_code'])) {
            $data['employee_code'] = self::generate_employee_code($data['store_id']);
        }
        
        $employee_data = [
            'store_id' => isset($data['store_id']) ? $data['store_id'] : null,
            'role_id' => isset($data['role_id']) && !empty($data['role_id']) ? $data['role_id'] : null,
            'employee_code' => sanitize_text_field($data['employee_code']),
            'first_name' => sanitize_text_field($data['first_name']),
            'last_name' => sanitize_text_field($data['last_name']),
            'email' => sanitize_email($data['email']),
            'mobile' => sanitize_text_field($data['mobile']),
            'phone' => sanitize_text_field($data['phone']),
            'address' => sanitize_textarea_field($data['address']),
            'city' => sanitize_text_field($data['city']),
            'state' => sanitize_text_field($data['state']),
            'postcode' => sanitize_text_field($data['postcode']),
            'country' => sanitize_text_field($data['country']),
            'hire_date' => !empty($data['hire_date']) ? date('Y-m-d', strtotime($data['hire_date'])) : null,
            'salary' => !empty($data['salary']) ? floatval($data['salary']) : null,
            'username' => isset($data['username']) ? sanitize_text_field($data['username']) : null,
            'password' => isset($data['password']) ? $data['password'] : null,
            'status' => isset($data['status']) ? (int)$data['status'] : 1,
            'created_by' => get_current_user_id(),
            'system_ip' => self::get_client_ip(),
            'system_name' => self::get_client_hostname(),
        ];
        
        $format = ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d', '%s', '%s', '%s'];
        
        $result = $wpdb->insert($table_employees, $employee_data, $format);
        
        if ($result === false) {
            return ['success' => false, 'message' => 'Failed to add employee.'];
        }
        
        return ['success' => true, 'message' => 'Employee added successfully.', 'id' => $wpdb->insert_id];
    }

    /**
     * Update employee
     */
    public static function update_employee($id, $data)
    {
        global $wpdb;
        $table_employees = $wpdb->prefix . 'orabooks_db_employees';
        
        $employee_data = [
            'role_id' => isset($data['role_id']) && !empty($data['role_id']) ? $data['role_id'] : null,
            'first_name' => sanitize_text_field($data['first_name']),
            'last_name' => sanitize_text_field($data['last_name']),
            'email' => sanitize_email($data['email']),
            'mobile' => sanitize_text_field($data['mobile']),
            'phone' => sanitize_text_field($data['phone']),
            'address' => sanitize_textarea_field($data['address']),
            'city' => sanitize_text_field($data['city']),
            'state' => sanitize_text_field($data['state']),
            'postcode' => sanitize_text_field($data['postcode']),
            'country' => sanitize_text_field($data['country']),
            'hire_date' => !empty($data['hire_date']) ? date('Y-m-d', strtotime($data['hire_date'])) : null,
            'salary' => !empty($data['salary']) ? floatval($data['salary']) : null,
            'username' => isset($data['username']) ? sanitize_text_field($data['username']) : null,
            'status' => isset($data['status']) ? (int)$data['status'] : 1,
        ];
        
        // Only add password to update data if it's provided
        if (isset($data['password']) && !empty($data['password'])) {
            $employee_data['password'] = $data['password'];
        }
        
        // Build format array dynamically based on the actual employee_data structure
        $format = [];
        foreach ($employee_data as $key => $value) {
            switch ($key) {
                case 'role_id':
                case 'status':
                    $format[] = '%d';
                    break;
                case 'salary':
                    $format[] = '%f';
                    break;
                default:
                    $format[] = '%s';
                    break;
            }
        }
        
        $result = $wpdb->update(
            $table_employees,
            $employee_data,
            ['id' => $id],
            $format,
            ['%d']
        );
        
        if ($result === false) {
            return ['success' => false, 'message' => 'Failed to update employee.'];
        }
        
        return ['success' => true, 'message' => 'Employee updated successfully.'];
    }

    /**
     * Toggle employee status (soft delete)
     */
    public static function toggle_employee_status($id)
    {
        global $wpdb;
        $table_employees = $wpdb->prefix . 'orabooks_db_employees';
        
        $employee = self::get_employee($id);
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found.'];
        }
        
        $new_status = $employee['status'] == 1 ? 0 : 1;
        
        $result = $wpdb->update(
            $table_employees,
            ['status' => $new_status],
            ['id' => $id],
            ['%d'],
            ['%d']
        );
        
        if ($result === false) {
            return ['success' => false, 'message' => 'Failed to update employee status.'];
        }
        
        $status_text = $new_status == 1 ? 'activated' : 'deactivated';
        return ['success' => true, 'message' => "Employee {$status_text} successfully.", 'status' => $new_status];
    }

    /**
     * Get client IP address
     */
    private static function get_client_ip()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }

    /**
     * Get client hostname
     */
    private static function get_client_hostname()
    {
        return gethostbyaddr(self::get_client_ip());
    }

    /**
     * AJAX: Get employees
     */
    public static function ajax_get_employees()
    {
        if (!orabooks_can_access_inventory()) {
            wp_die('Access denied');
        }
        
        $store_id = isset($_POST['store_id']) ? (int)$_POST['store_id'] : null;
        $employees = self::get_employees($store_id);
        
        wp_send_json_success($employees);
    }

    /**
     * AJAX: Add employee
     */
    public static function ajax_add_employee()
    {
        if (!orabooks_can_access_inventory()) {
            wp_die('Access denied');
        }
        
        check_ajax_referer('frontend_ajax_nonce', 'nonce');
        
        $data = [
            'first_name' => isset($_POST['first_name']) ? $_POST['first_name'] : '',
            'last_name' => isset($_POST['last_name']) ? $_POST['last_name'] : '',
            'email' => isset($_POST['email']) ? $_POST['email'] : '',
            'mobile' => isset($_POST['mobile']) ? $_POST['mobile'] : '',
            'phone' => isset($_POST['phone']) ? $_POST['phone'] : '',
            'address' => isset($_POST['address']) ? $_POST['address'] : '',
            'city' => isset($_POST['city']) ? $_POST['city'] : '',
            'state' => isset($_POST['state']) ? $_POST['state'] : '',
            'postcode' => isset($_POST['postcode']) ? $_POST['postcode'] : '',
            'country' => isset($_POST['country']) ? $_POST['country'] : '',
            'hire_date' => isset($_POST['hire_date']) ? $_POST['hire_date'] : '',
            'salary' => isset($_POST['salary']) ? $_POST['salary'] : '',
            'username' => isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '',
            'password' => isset($_POST['password']) && !empty($_POST['password']) ? wp_hash_password($_POST['password']) : '',
            'role_id' => isset($_POST['role_id']) ? (int)$_POST['role_id'] : null,
            'store_id' => isset($_POST['store_id']) ? (int)$_POST['store_id'] : null,
            'status' => isset($_POST['status']) ? (int)$_POST['status'] : 1,
        ];
        
        // Validation
        if (empty($data['first_name'])) {
            wp_send_json_error(['message' => 'First name is required.']);
        }
        
        if (!empty($data['email']) && !is_email($data['email'])) {
            wp_send_json_error(['message' => 'Invalid email address.']);
        }
        
        if (!empty($data['username'])) {
            // Check if username already exists
            global $wpdb;
            $table_employees = $wpdb->prefix . 'orabooks_db_employees';
            $existing_username = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_employees WHERE username = %s AND id != %d",
                $data['username'], 0
            ));
            
            if ($existing_username > 0) {
                wp_send_json_error(['message' => 'Username already exists.']);
            }
        }
        
        // Only validate password if it's provided (not empty)
        if (isset($data['password']) && $data['password'] !== '') {
            // Debug: Log password length for troubleshooting
            error_log('Password received: ' . $data['password'] . ' (Length: ' . strlen($data['password']) . ')');
            
            // Password validation: at least one capital, one special character, one numeric
            if (strlen($data['password']) < 8) {
                wp_send_json_error(['message' => 'Password must be at least 8 characters long.']);
            }
            
            if (!preg_match('/[A-Z]/', $data['password'])) {
                wp_send_json_error(['message' => 'Password must contain at least one uppercase letter.']);
            }
            
            if (!preg_match('/[a-z]/', $data['password'])) {
                wp_send_json_error(['message' => 'Password must contain at least one lowercase letter.']);
            }
            
            if (!preg_match('/[0-9]/', $data['password'])) {
                wp_send_json_error(['message' => 'Password must contain at least one number.']);
            }
            
            if (!preg_match('/[!@#\$%^&*()_+=\-\[\]{};:\'",.<>?\/|`~]/', $data['password'])) {
                wp_send_json_error(['message' => 'Password must contain at least one special character.']);
            }
        }
        
        $result = self::add_employee($data);
        
        if ($result['success']) {
            wp_send_json_success(['message' => $result['message'], 'id' => $result['id']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * AJAX: Update employee
     */
    public static function ajax_update_employee()
    {
        if (!orabooks_can_access_inventory()) {
            wp_die('Access denied');
        }
        
        check_ajax_referer('frontend_ajax_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => 'Employee ID is required.']);
        }
        
        $data = [
            'first_name' => isset($_POST['first_name']) ? $_POST['first_name'] : '',
            'last_name' => isset($_POST['last_name']) ? $_POST['last_name'] : '',
            'email' => isset($_POST['email']) ? $_POST['email'] : '',
            'mobile' => isset($_POST['mobile']) ? $_POST['mobile'] : '',
            'phone' => isset($_POST['phone']) ? $_POST['phone'] : '',
            'address' => isset($_POST['address']) ? $_POST['address'] : '',
            'city' => isset($_POST['city']) ? $_POST['city'] : '',
            'state' => isset($_POST['state']) ? $_POST['state'] : '',
            'postcode' => isset($_POST['postcode']) ? $_POST['postcode'] : '',
            'country' => isset($_POST['country']) ? $_POST['country'] : '',
            'hire_date' => isset($_POST['hire_date']) ? $_POST['hire_date'] : '',
            'salary' => isset($_POST['salary']) ? $_POST['salary'] : '',
            'username' => isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '',
            'role_id' => isset($_POST['role_id']) ? (int)$_POST['role_id'] : null,
            'status' => isset($_POST['status']) ? (int)$_POST['status'] : 1,
        ];
        
        // Validation
        if (empty($data['first_name'])) {
            wp_send_json_error(['message' => 'First name is required.']);
        }
        
        if (!empty($data['email']) && !is_email($data['email'])) {
            wp_send_json_error(['message' => 'Invalid email address.']);
        }
        
        if (!empty($data['username'])) {
            // Check if username already exists for other employees
            global $wpdb;
            $table_employees = $wpdb->prefix . 'orabooks_db_employees';
            $existing_username = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_employees WHERE username = %s AND id != %d",
                $data['username'], $id
            ));
            
            if ($existing_username > 0) {
                wp_send_json_error(['message' => 'Username already exists.']);
            }
        }
        
        // Handle password - validate and add to data if provided
        if (isset($_POST['password']) && !empty($_POST['password'])) {
            $raw_password = $_POST['password'];
            
            // Password validation: at least one capital, one special character, one numeric
            if (strlen($raw_password) < 8) {
                wp_send_json_error(['message' => 'Password must be at least 8 characters long.']);
            }
            
            if (!preg_match('/[A-Z]/', $raw_password)) {
                wp_send_json_error(['message' => 'Password must contain at least one uppercase letter.']);
            }
            
            if (!preg_match('/[a-z]/', $raw_password)) {
                wp_send_json_error(['message' => 'Password must contain at least one lowercase letter.']);
            }
            
            if (!preg_match('/[0-9]/', $raw_password)) {
                wp_send_json_error(['message' => 'Password must contain at least one number.']);
            }
            
            if (!preg_match('/[!@#\$%^&*()_+=\-\[\]{};:\'",.<>?\/|`~]/', $raw_password)) {
                wp_send_json_error(['message' => 'Password must contain at least one special character.']);
            }
            
            // Add validated and hashed password to data
            $data['password'] = wp_hash_password($raw_password);
        }
        
        $result = self::update_employee($id, $data);
        
        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * AJAX: Toggle employee status
     */
    public static function ajax_toggle_employee_status()
    {
        if (!orabooks_can_access_inventory()) {
            wp_die('Access denied');
        }
        
        check_ajax_referer('frontend_ajax_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => 'Employee ID is required.']);
        }
        
        $result = self::toggle_employee_status($id);
        
        if ($result['success']) {
            wp_send_json_success(['message' => $result['message'], 'status' => $result['status']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * AJAX: Get employee
     */
    public static function ajax_get_employee()
    {
        if (!orabooks_can_access_inventory()) {
            wp_die('Access denied');
        }
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => 'Employee ID is required.']);
        }
        
        $employee = self::get_employee($id);
        
        if ($employee) {
            wp_send_json_success($employee);
        } else {
            wp_send_json_error(['message' => 'Employee not found.']);
        }
    }

    /**
     * AJAX: Get roles for select dropdown
     */
    public static function ajax_get_roles_for_select()
    {
        if (!orabooks_can_access_inventory()) {
            wp_die('Access denied');
        }
        
        global $wpdb;
        $table_roles = $wpdb->prefix . 'orabooks_db_roles';
        
        $roles = $wpdb->get_results(
            "SELECT id, role_name FROM $table_roles WHERE status = 1 ORDER BY role_name ASC",
            ARRAY_A
        );
        
        wp_send_json_success($roles);
    }

    /**
     * Render employees list template
     */
    public static function render_employees_list()
    {
        if (!orabooks_can_access_inventory()) {
            echo '<div class="alert alert-danger">Access denied. You do not have permission to access this module.</div>';
            return;
        }
        
        $employees = self::get_employees();
        
        ob_start();
        ?>
        <div class="container-fluid">
            <div class="row mb-3">
                <div class="col-md-6">
                    <h3><i class="fa-solid fa-users me-2"></i>Employees Management</h3>
                </div>
                <div class="col-md-6 text-end">
                    <button type="button" class="btn btn-primary" onclick="showAddEmployeeModal()">
                        <i class="fa-solid fa-plus me-2"></i>Add Employee
                    </button>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="employeesTable">
                            <thead>
                                <tr>
                                    <th>Employee Code</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Mobile</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($employees)): ?>
                                    <?php foreach ($employees as $employee): ?>
                                        <tr>
                                            <td><?php echo esc_html($employee['employee_code']); ?></td>
                                            <td><?php echo esc_html($employee['first_name'] . ' ' . $employee['last_name']); ?></td>
                                            <td><?php echo esc_html($employee['email'] ? $employee['email'] : '-'); ?></td>
                                            <td><?php echo esc_html($employee['mobile'] ? $employee['mobile'] : '-'); ?></td>
                                            <td><?php echo esc_html($employee['role_name'] ? $employee['role_name'] : '-'); ?></td>
                                            <td>
                                                <span class="badge <?php echo $employee['status'] == 1 ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo $employee['status'] == 1 ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-warning" onclick="editEmployee(<?php echo $employee['id']; ?>)">
                                                    <i class="fa-solid fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm <?php echo $employee['status'] == 1 ? 'btn-danger' : 'btn-success'; ?>" 
                                                        onclick="toggleEmployeeStatus(<?php echo $employee['id']; ?>, <?php echo $employee['status']; ?>)"
                                                        title="<?php echo $employee['status'] == 1 ? 'Deactivate Employee' : 'Activate Employee'; ?>">
                                                    <i class="fa-solid fa-<?php echo $employee['status'] == 1 ? 'ban' : 'check'; ?>"></i>
                                                    <?php echo $employee['status'] == 1 ? ' Deactivate' : ' Activate'; ?>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No employees found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Employee Modal -->
        <div class="modal fade" id="addEmployeeModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Employee</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addEmployeeForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="first_name" class="form-label">First Name *</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="last_name" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="mobile" class="form-label">Mobile</label>
                                        <input type="text" class="form-control" id="mobile" name="mobile">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password" name="password">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('password')">
                                                <i class="fa-solid fa-eye" id="password-icon"></i>
                                            </button>
                                        </div>
                                        <small class="form-text text-muted">Leave blank to keep existing password</small>
                                        <div class="form-text text-muted">Password must be at least 8 characters with 1 uppercase, 1 lowercase, 1 number, and 1 special character</div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input type="text" class="form-control" id="phone" name="phone">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="role_id" class="form-label">Role</label>
                                        <select class="form-select" id="role_id" name="role_id">
                                            <option value="">Select Role</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="hire_date" class="form-label">Hire Date</label>
                                        <input type="date" class="form-control" id="hire_date" name="hire_date">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="salary" class="form-label">Salary</label>
                                        <input type="number" step="0.01" class="form-control" id="salary" name="salary">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="city" name="city">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="state" class="form-label">State</label>
                                        <input type="text" class="form-control" id="state" name="state">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="postcode" class="form-label">Postcode</label>
                                        <input type="text" class="form-control" id="postcode" name="postcode">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="country" class="form-label">Country</label>
                                <input type="text" class="form-control" id="country" name="country">
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
                        <button type="button" class="btn btn-primary" onclick="saveEmployee()">Save Employee</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Employee Modal -->
        <div class="modal fade" id="editEmployeeModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Employee</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editEmployeeForm">
                            <input type="hidden" id="edit_employee_id" name="id">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_first_name" class="form-label">First Name *</label>
                                        <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_last_name" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="edit_last_name" name="last_name">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="edit_email" name="email">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="edit_username" name="username" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_mobile" class="form-label">Mobile</label>
                                        <input type="text" class="form-control" id="edit_mobile" name="mobile">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_password" class="form-label">Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="edit_password" name="password">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('edit_password')">
                                                <i class="fa-solid fa-eye" id="edit_password-icon"></i>
                                            </button>
                                        </div>
                                        <small class="form-text text-muted">Leave blank to keep existing password (shown masked as •••••••••)</small>
                                        <div class="form-text text-muted">Password must be at least 8 characters with 1 uppercase, 1 lowercase, 1 number, and 1 special character</div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_phone" class="form-label">Phone</label>
                                        <input type="text" class="form-control" id="edit_phone" name="phone">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_role_id" class="form-label">Role</label>
                                        <select class="form-select" id="edit_role_id" name="role_id">
                                            <option value="">Select Role</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_hire_date" class="form-label">Hire Date</label>
                                        <input type="date" class="form-control" id="edit_hire_date" name="hire_date">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_salary" class="form-label">Salary</label>
                                        <input type="number" step="0.01" class="form-control" id="edit_salary" name="salary">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_address" class="form-label">Address</label>
                                <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="edit_city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="edit_city" name="city">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="edit_state" class="form-label">State</label>
                                        <input type="text" class="form-control" id="edit_state" name="state">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="edit_postcode" class="form-label">Postcode</label>
                                        <input type="text" class="form-control" id="edit_postcode" name="postcode">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_country" class="form-label">Country</label>
                                <input type="text" class="form-control" id="edit_country" name="country">
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
                        <button type="button" class="btn btn-primary" onclick="updateEmployee()">Update Employee</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        // Load roles for dropdowns
        function loadRoles() {
            const formData = new FormData();
            formData.append('action', 'frontend_inventory_get_roles_for_select');
            formData.append('nonce', frontend_inventory_ajax.nonce);

            return fetch(frontend_inventory_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const roles = data.data;
                    const roleSelects = ['#role_id', '#edit_role_id'];
                    
                    roleSelects.forEach(selector => {
                        const select = document.querySelector(selector);
                        if (select) {
                            select.innerHTML = '<option value="">Select Role</option>';
                            roles.forEach(role => {
                                const option = document.createElement('option');
                                option.value = role.id;
                                option.textContent = role.role_name;
                                select.appendChild(option);
                            });
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error loading roles:', error);
            });
        }

        function showAddEmployeeModal() {
            loadRoles();
            jQuery('#addEmployeeModal').modal('show');
        }

        function saveEmployee() {
            const form = document.getElementById('addEmployeeForm');
            const formData = new FormData(form);
            
            // Debug: Log password field value
            const passwordValue = formData.get('password');
            console.log('Password being submitted:', passwordValue);
            
            formData.append('action', 'frontend_inventory_add_employee');
            formData.append('nonce', frontend_inventory_ajax.nonce);

            fetch(frontend_inventory_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success', data.data.message, 'success');
                    jQuery('#addEmployeeModal').modal('hide');
                    location.reload();
                } else {
                    Swal.fire('Error', data.data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'An error occurred while saving the employee.', 'error');
            });
        }

        function editEmployee(id) {
            const formData = new FormData();
            formData.append('action', 'frontend_inventory_get_employee');
            formData.append('id', id);
            formData.append('nonce', frontend_inventory_ajax.nonce);

            fetch(frontend_inventory_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const employee = data.data;
                    
                    // Load roles first, then set employee data
                    loadRoles().then(() => {
                        document.getElementById('edit_employee_id').value = employee.id;
                        document.getElementById('edit_first_name').value = employee.first_name;
                        document.getElementById('edit_last_name').value = employee.last_name || '';
                        document.getElementById('edit_email').value = employee.email || '';
                        document.getElementById('edit_username').value = employee.username || '';
                        document.getElementById('edit_mobile').value = employee.mobile || '';
                        document.getElementById('edit_phone').value = employee.phone || '';
                        document.getElementById('edit_role_id').value = employee.role_id || '';
                        document.getElementById('edit_hire_date').value = employee.hire_date || '';
                        document.getElementById('edit_salary').value = employee.salary || '';
                        document.getElementById('edit_address').value = employee.address || '';
                        document.getElementById('edit_city').value = employee.city || '';
                        document.getElementById('edit_state').value = employee.state || '';
                        document.getElementById('edit_postcode').value = employee.postcode || '';
                        document.getElementById('edit_country').value = employee.country || '';
                        document.getElementById('edit_status').value = employee.status;
                        // Show masked password if exists
                        if (employee.password && employee.password !== '') {
                            document.getElementById('edit_password').value = '••••••••••';
                            document.getElementById('edit_password').setAttribute('data-actual-password', 'masked');
                        } else {
                            document.getElementById('edit_password').value = '';
                            document.getElementById('edit_password').setAttribute('data-actual-password', '');
                        }
                        jQuery('#editEmployeeModal').modal('show');
                    });
                } else {
                    Swal.fire('Error', data.data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'An error occurred while loading employee.', 'error');
            });
        }

        function updateEmployee() {
            const form = document.getElementById('editEmployeeForm');
            const formData = new FormData(form);
            
            // Handle password field properly
            const passwordField = document.getElementById('edit_password');
            const passwordValue = formData.get('password');
            
            // If password is masked or empty, don't include it in the form data
            if (passwordValue === '••••••••' || passwordValue === '' || passwordField.getAttribute('data-actual-password') === 'masked') {
                formData.delete('password');
                console.log('Password not provided, keeping existing password');
            } else {
                console.log('New password provided for update:', passwordValue);
            }
            
            formData.append('action', 'frontend_inventory_update_employee');
            formData.append('nonce', frontend_inventory_ajax.nonce);

            fetch(frontend_inventory_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success', data.data.message, 'success');
                    jQuery('#editEmployeeModal').modal('hide');
                    location.reload();
                } else {
                    Swal.fire('Error', data.data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'An error occurred while updating the employee.', 'error');
            });
        }

        function toggleEmployeeStatus(id, currentStatus) {
            const action = currentStatus == 1 ? 'deactivate' : 'activate';
            Swal.fire({
                title: 'Are you sure?',
                text: `Do you want to ${action} this employee?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#1569B3',
                cancelButtonColor: '#d33',
                confirmButtonText: `Yes, ${action} it!`
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'frontend_inventory_toggle_employee_status');
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
                        Swal.fire('Error', 'An error occurred while updating the employee status.', 'error');
                    });
                }
            });
        }

        // Toggle password visibility
        function togglePasswordVisibility(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '-icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Load roles when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadRoles();
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
