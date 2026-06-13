<?php
// Group management AJAX
add_action( 'wp_ajax_orabooks_save_group', 'orabooks_save_group' );
function orabooks_save_group() {
    error_log('orabooks_save_group called');

    // Build Guide Compliance: Use security class for nonce verification
    if (class_exists('OraBooks_Security')) {
        $security = OraBooks_Security::get_instance();
        if (!$security->verify_nonce($_POST['nonce'], 'orabooks-admin-nonce')) {
            wp_send_json_error('Security check failed');
        }
    } else {
        check_ajax_referer('orabooks-admin-nonce', 'nonce');
    }

    if (!current_user_can('manage_options')) wp_send_json_error('Access denied');
    
    // Build Guide Compliance: Get current mode
    $current_mode = class_exists('OraBooks_Mode_Manager') ? OraBooks_Mode_Manager::get_current_mode() : 'business';
    
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    $id = intval($_POST['id'] ?? 0);
    $name = sanitize_text_field($_POST['name'] ?? '');
    $desc = sanitize_textarea_field($_POST['description'] ?? '');
    
    if (empty($name)) {
        error_log('Group name validation failed');
        wp_send_json_error('Group name is required');
    }
    
    $data = array(
        'name' => $name,
        'description' => $desc,
        'mode' => $current_mode,
        'updated_by' => get_current_user_id()
    );
    
    if ($id > 0) {
        error_log("Updating group ID: $id with data: " . print_r($data, true));
        
        // Build Guide Compliance: Get before state for audit
        $before_state = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->orabooks_groups} WHERE id = %d", $id), ARRAY_A);
        
        $result = $wpdb->update($wpdb->orabooks_groups, $data, array('id' => $id));
        if ($result === false) {
            error_log('Database error during update: ' . $wpdb->last_error);
            wp_send_json_error('Database error: ' . $wpdb->last_error);
        }
        
        // Build Guide Compliance: Log group update for audit trail
        if (class_exists('OraBooks_Audit_Logger')) {
            $logger = OraBooks_Audit_Logger::get_instance();
            $logger->log_coa_change(get_current_user_id(), $id, 'update', $before_state, $data);
        }
        
        error_log('Group updated successfully');
        wp_send_json_success('Group updated successfully');
    } else {
        error_log('Inserting new group with data: ' . print_r($data, true));
        $data['created_by'] = get_current_user_id();
        $result = $wpdb->insert($wpdb->orabooks_groups, $data);
        if ($result === false) {
            error_log('Database error during insert: ' . $wpdb->last_error);
            wp_send_json_error('Database error: ' . $wpdb->last_error);
        }
        
        // Build Guide Compliance: Log group creation for audit trail
        if (class_exists('OraBooks_Audit_Logger')) {
            $logger = OraBooks_Audit_Logger::get_instance();
            $logger->log_coa_change(get_current_user_id(), $wpdb->insert_id, 'create', null, $data);
        }
        
        error_log('Group created successfully with ID: ' . $wpdb->insert_id);
        wp_send_json_success(array('id' => $wpdb->insert_id, 'message' => 'Group created successfully'));
    }
}

add_action('wp_ajax_orabooks_delete_group', 'orabooks_delete_group');
function orabooks_delete_group() {
    error_log('orabooks_delete_group called');

    // Build Guide Compliance: Use security class for nonce verification
    if (class_exists('OraBooks_Security')) {
        $security = OraBooks_Security::get_instance();
        if (!$security->verify_nonce($_POST['nonce'], 'orabooks-admin-nonce')) {
            wp_send_json_error('Security check failed');
        }
    } else {
        check_ajax_referer('orabooks-admin-nonce', 'nonce');
    }

    if (!current_user_can('manage_options')) wp_send_json_error('Access denied');
    
    // Build Guide Compliance: Get current mode
    $current_mode = class_exists('OraBooks_Mode_Manager') ? OraBooks_Mode_Manager::get_current_mode() : 'business';
    
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        error_log('Invalid group ID: ' . $id);
        wp_send_json_error('Invalid group ID');
    }
    
    // Check if group exists
    $group_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->orabooks_groups} WHERE id = %d",
        $id
    ));
    
    if (!$group_exists) {
        error_log('Group not found with ID: ' . $id);
        wp_send_json_error('Group not found');
    }
    
    // Check if group has levels
    $levels_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->orabooks_levels} WHERE group_id = %d",
        $id
    ));
    
    if ($levels_count > 0) {
        error_log("Cannot delete group ID: $id because it has $levels_count levels");
        wp_send_json_error('Cannot delete group with existing levels. Please delete or move levels first.');
    }
    
    error_log("Deleting group ID: $id");
    
    // Build Guide Compliance: Get before state for audit
    $before_state = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->orabooks_groups} WHERE id = %d", $id), ARRAY_A);
    
    $result = $wpdb->delete($wpdb->orabooks_groups, array('id' => $id));
    if ($result === false) {
        error_log('Database error during delete: ' . $wpdb->last_error);
        wp_send_json_error('Database error: ' . $wpdb->last_error);
    }
    
    // Build Guide Compliance: Log group deletion for audit trail
    if (class_exists('OraBooks_Audit_Logger')) {
        $logger = OraBooks_Audit_Logger::get_instance();
        $logger->log_action(array(
            'user_id' => get_current_user_id(),
            'action_type' => 'group_deleted',
            'action_description' => sprintf('Group deleted: ID %d in %s mode', $id, $current_mode),
            'mode' => $current_mode,
            'entity_type' => 'group',
            'entity_id' => $id,
            'before_state' => $before_state
        ));
    }
    
    error_log('Group deleted successfully');
    wp_send_json_success('Group deleted successfully');
}

add_action('wp_ajax_orabooks_save_level', 'orabooks_save_level');
function orabooks_save_level() {
    error_log('orabooks_save_level called');
    
    // Build Guide Compliance: Use security class for nonce verification
    if (class_exists('OraBooks_Security')) {
        $security = OraBooks_Security::get_instance();
        if (!$security->verify_nonce($_POST['nonce'], 'orabooks-admin-nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
    } else {
        if (!check_ajax_referer('orabooks-admin-nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
            return;
        }
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Access denied');
        return;
    }
    
    // Build Guide Compliance: Get current mode
    $current_mode = class_exists('OraBooks_Mode_Manager') ? OraBooks_Mode_Manager::get_current_mode() : 'business';
    
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    $id = intval($_POST['level_id'] ?? 0);
    $name = sanitize_text_field($_POST['level_name'] ?? '');
    $desc = sanitize_textarea_field($_POST['level_description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $period = sanitize_text_field($_POST['billing_period'] ?? 'monthly');
    $group_id = intval($_POST['group_id'] ?? 0);
    $currency = get_option('orabooks_currency', 'BDT');
    $currency_symbol = get_option('orabooks_currency_symbol', '৳');
    $currency_position = get_option('orabooks_currency_position', 'after');
    $trial_days = intval($_POST['trial_days'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $label = sanitize_text_field($_POST['level_label'] ?? '');
    
    // Debug: Check what values we're getting
    error_log("Level name: '$name'");
    error_log("Group ID: '$group_id'");
    error_log("Price: '$price'");
    error_log("Billing period: '$period'");
    
    // Validation - FIXED: Check if name is empty after sanitization
    if (empty(trim($name))) {
        error_log('Level name validation failed - name is empty');
        wp_send_json_error('Level name is required');
        return;
    }
    
    if ($group_id <= 0) {
        error_log('Group ID validation failed - group_id is: ' . $group_id);
        wp_send_json_error('Please select a valid group');
        return;
    }
    
    if ($price < 0) {
        wp_send_json_error('Price cannot be negative');
        return;
    }
    
    // Check for duplicate level name within the same group (for new levels only)
    if ($id <= 0) {
        $existing_level = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->orabooks_levels} 
             WHERE group_id = %d AND name = %s",
            $group_id, $name
        ));
        
        if ($existing_level > 0) {
            error_log("Duplicate level name detected: '$name' in group $group_id");
            wp_send_json_error('A level with this name already exists in this group. Please choose a different name.');
            return;
        }
    }
    
    $data = array(
        'name' => $name,
        'description' => $desc,
        'label' => $label,
        'price' => $price,
        'billing_period' => $period,
        'group_id' => $group_id,
        'currency' => $currency,
        'currency_symbol' => $currency_symbol,
        'currency_position' => $currency_position,
        'trial_days' => $trial_days,
        'is_active' => $is_active,
        'mode' => $current_mode,
        'updated_by' => get_current_user_id()
    );
    
    error_log('Data to save: ' . print_r($data, true));
    
    try {
        if ($id > 0) {
            // Update existing level
            // Build Guide Compliance: Get before state for audit
            $before_state = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->orabooks_levels} WHERE id = %d", $id), ARRAY_A);
            
            $result = $wpdb->update($wpdb->orabooks_levels, $data, array('id' => $id));
            if ($result === false) {
                error_log('Database update error: ' . $wpdb->last_error);
                wp_send_json_error('Database error: ' . $wpdb->last_error);
                return;
            }
            
            // Build Guide Compliance: Log level update for audit trail
            if (class_exists('OraBooks_Audit_Logger')) {
                $logger = OraBooks_Audit_Logger::get_instance();
                $logger->log_coa_change(get_current_user_id(), $id, 'update', $before_state, $data);
            }
            
            error_log('Level updated successfully');
            wp_send_json_success('Level updated successfully');
        } else {
            // Insert new level
            $data['created_by'] = get_current_user_id();
            $result = $wpdb->insert($wpdb->orabooks_levels, $data);
            if ($result === false) {
                error_log('Database insert error: ' . $wpdb->last_error);
                wp_send_json_error('Database error: ' . $wpdb->last_error);
                return;
            }
            
            // Build Guide Compliance: Log level creation for audit trail
            if (class_exists('OraBooks_Audit_Logger')) {
                $logger = OraBooks_Audit_Logger::get_instance();
                $logger->log_coa_change(get_current_user_id(), $wpdb->insert_id, 'create', null, $data);
            }
            
            error_log('Level created successfully with ID: ' . $wpdb->insert_id);
            wp_send_json_success(array('id' => $wpdb->insert_id, 'message' => 'Level created successfully'));
        }
    } catch (Exception $e) {
        error_log('Exception in level save: ' . $e->getMessage());
        wp_send_json_error('Error saving level: ' . $e->getMessage());
    }
}

add_action('wp_ajax_orabooks_delete_level', 'orabooks_delete_level');
function orabooks_delete_level() {
    error_log('orabooks_delete_level called');
    
    // Build Guide Compliance: Use security class for nonce verification
    if (class_exists('OraBooks_Security')) {
        $security = OraBooks_Security::get_instance();
        if (!$security->verify_nonce($_POST['nonce'], 'orabooks-admin-nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
    } else {
        check_ajax_referer('orabooks-admin-nonce', 'nonce');
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Access denied');
        return;
    }
    
    // Build Guide Compliance: Get current mode
    $current_mode = class_exists('OraBooks_Mode_Manager') ? OraBooks_Mode_Manager::get_current_mode() : 'business';
    
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    // FIX: Check for both 'id' and 'level_id' parameter names
    $id = intval($_POST['level_id'] ?? $_POST['id'] ?? 0);
    
    error_log('Level ID to delete: ' . $id);
    
    if ( $id <= 0 ) {
        error_log('Invalid level ID received: ' . $id);
        wp_send_json_error( 'Invalid level ID' );
        return;
    }
    
    // Check if level exists
    $level_exists = $wpdb->get_var( $wpdb->prepare( 
        "SELECT COUNT(*) FROM {$wpdb->orabooks_levels} WHERE id = %d", 
        $id 
    ) );
    
    if ( ! $level_exists ) {
        error_log('Level not found with ID: ' . $id);
        wp_send_json_error( 'Level not found' );
        return;
    }
    
    // Check if level has active subscriptions
    $subscriptions_count = $wpdb->get_var( $wpdb->prepare( 
        "SELECT COUNT(*) FROM {$wpdb->orabooks_subscriptions} WHERE level_id = %d AND status = 'active'", 
        $id 
    ) );
    
    error_log('Active subscriptions count: ' . $subscriptions_count);
    
    if ( $subscriptions_count > 0 ) {
        wp_send_json_error( 'Cannot delete level with active subscriptions. Please cancel subscriptions first.' );
        return;
    }
    
    // Also check if there are any completed orders for this level
    $orders_count = $wpdb->get_var( $wpdb->prepare( 
        "SELECT COUNT(*) FROM {$wpdb->orabooks_orders} WHERE level_id = %d AND status = 'completed'", 
        $id 
    ) );
    
    error_log('Completed orders count: ' . $orders_count);
    
    if ( $orders_count > 0 ) {
        wp_send_json_error( 'Cannot delete level with existing orders. This level has historical order data.' );
        return;
    }
    
    // Build Guide Compliance: Get before state for audit BEFORE deletion
    $before_state = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->orabooks_levels} WHERE id = %d", $id), ARRAY_A);
    
    if (!$before_state) {
        wp_send_json_error('Level not found');
        return;
    }
    
    // Build Guide Compliance: Log level deletion for audit trail
    if (class_exists('OraBooks_Audit_Logger')) {
        $logger = OraBooks_Audit_Logger::get_instance();
        $logger->log_action(array(
            'user_id' => get_current_user_id(),
            'action_type' => 'level_deleted',
            'action_description' => sprintf('Level deleted: ID %d in %s mode', $id, $current_mode),
            'mode' => $current_mode,
            'entity_type' => 'level',
            'entity_id' => $id,
            'before_state' => $before_state
        ));
    }
    
    // Delete feature assignments after audit log
    $features_deleted = $wpdb->delete($wpdb->orabooks_feature_assignments, array('level_id' => $id));
    error_log('Feature assignments deleted: ' . $features_deleted);
    
    // Delete the level
    $result = $wpdb->delete($wpdb->orabooks_levels, array('id' => $id));
    
    if ($result === false) {
        error_log('Database delete error: ' . $wpdb->last_error);
        wp_send_json_error('Database error: ' . $wpdb->last_error);
        return;
    }
    
    error_log('Level deleted successfully. Rows affected: ' . $result);
    wp_send_json_success('Level deleted successfully');
}

// AJAX handler for checkout processing
add_action('wp_ajax_orabooks_process_checkout', 'orabooks_process_checkout');
add_action('wp_ajax_nopriv_orabooks_process_checkout', 'orabooks_process_checkout');
function orabooks_process_checkout() {
    // Build Guide Compliance: Use security class for nonce verification
    if (class_exists('OraBooks_Security')) {
        $security = OraBooks_Security::get_instance();
        if (!$security->verify_nonce($_POST['nonce'], 'orabooks_checkout_nonce')) {
            wp_send_json_error('Security check failed');
        }
    } else {
        if (!wp_verify_nonce($_POST['nonce'], 'orabooks_checkout_nonce')) {
            wp_send_json_error('Security check failed');
        }
    }
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Please log in to complete your purchase');
    }
    
    // Build Guide Compliance: Get current mode
    $current_mode = class_exists('OraBooks_Mode_Manager') ? OraBooks_Mode_Manager::get_current_mode() : 'business';
    
    $level_id = intval($_POST['level_id'] ?? 0);
    $user_id = get_current_user_id();
    global $wpdb;
    
    error_log('AJAX Checkout processing for level: ' . $level_id . ', user: ' . $user_id);
    
    // Validate level
    $level = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->orabooks_levels} WHERE id=%d AND is_active=1", $level_id));
    if (!$level) {
        wp_send_json_error('Invalid level or level not available');
    }
    
    // Get the payment gateway from request
    $gateway = isset($_POST['gateway']) ? sanitize_text_field($_POST['gateway']) : '';
    if (empty($gateway)) {
        wp_send_json_error('Payment gateway is required');
    }
    
    // Create order with pending status
    $order_id = 'OB' . time() . rand(1000, 9999);
    $amount = floatval($level->price);
    
    $order_data = array(
        'order_id' => $order_id,
        'user_id' => $user_id,
        'level_id' => $level_id,
        'gateway' => $gateway,
        'amount' => $amount,
        'status' => 'pending',
        'mode' => $current_mode,
        'created_by' => $user_id,
        'updated_by' => $user_id,
        'created_at' => current_time('mysql')
    );
    
    $result = $wpdb->insert($wpdb->orabooks_orders, $order_data);
    
    if ($result === false) {
        wp_send_json_error('Error creating order. Please try again.');
    }
    
    $insert_id = $wpdb->insert_id;
    $order_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->orabooks_orders} WHERE id=%d", $insert_id));
    
    // Build Guide Compliance: Log order creation for audit trail
    if (class_exists('OraBooks_Audit_Logger')) {
        $logger = OraBooks_Audit_Logger::get_instance();
        $logger->log_action(array(
            'user_id' => $user_id,
            'action_type' => 'order_created',
            'action_description' => sprintf('Order created: %s for Level %d via %s in %s mode', $order_id, $level_id, $gateway, $current_mode),
            'mode' => $current_mode,
            'entity_type' => 'order',
            'entity_id' => $insert_id,
            'after_state' => array(
                'order_id' => $order_id,
                'amount' => $amount,
                'level_id' => $level_id,
                'gateway' => $gateway,
                'status' => 'pending'
            )
        ));
    }
    
    // Process payment through the appropriate gateway
    $payment_result = apply_filters('orabooks_process_gateway_payment', array(
        'success' => false,
        'message' => 'No payment gateway handler found'
    ), $gateway, $order_id, $amount, $level_id, $user_id);
    
    if ($payment_result['success'] && !empty($payment_result['redirect_url'])) {
        wp_send_json_success(array(
            'redirect_url' => $payment_result['redirect_url'],
            'message' => 'Redirecting to payment gateway...'
        ));
    } elseif ($payment_result['success']) {
        // Get confirmation page URL
        $confirm_page = get_page_by_path('orabooks-confirmation');
        $redirect_url = $confirm_page ? get_permalink($confirm_page->ID) : home_url();
        wp_send_json_success(array(
            'redirect_url' => $redirect_url,
            'message' => 'Purchase completed successfully'
        ));
    } else {
        wp_send_json_error($payment_result['message']);
    }
}

// Feature Assignment AJAX Handlers (Restored and Enhanced)
add_action( 'wp_ajax_orabooks_load_feature_assignment_form', 'orabooks_load_feature_assignment_form' );
function orabooks_load_feature_assignment_form() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Access denied' );
    check_ajax_referer( 'orabooks-admin-nonce', 'nonce' );
    
    $level_id = intval( $_POST['level_id'] ?? 0 );
    
    if ( $level_id <= 0 ) {
        wp_send_json_error( 'Invalid level ID' );
        return;
    }
    // Force reload addons to ensure we have the latest registrations and limitations
    if (class_exists('OraBooks_Addon_Registry')) {
        OraBooks_Addon_Registry::load_addons(true);
    }
    
    $available_features = orabooks_get_available_features();
    $assigned_features = orabooks_get_level_features( $level_id );
    $assigned_data = array();
    
    foreach ( $assigned_features as $feature ) {
        $assigned_data[$feature->feature_key] = array(
            'access_type' => $feature->access_type,
            'settings' => json_decode($feature->settings, true) ?: array()
        );
    }
    
    ob_start();
    ?>
    <div class="feature-assignment-form">
        <p style="margin-bottom: 1.5rem; color: #6b7280;">Configure which features this plan can access and set specific limitations.</p>
        
        <div class="feature-categories">
            <?php
            $categories = array();
            foreach ( $available_features as $feature_key => $feature ) {
                $category = $feature['category'] ?? 'general';
                $categories[$category][] = array_merge( $feature, array( 'key' => $feature_key ) );
            }
            
            foreach ( $categories as $category_name => $category_features ) :
            ?>
            <div class="feature-category" style="margin-bottom: 2rem;">
                <h4 style="font-size: 1rem; font-weight: 700; color: #374151; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #f3f4f6;"><?php echo esc_html( ucfirst( $category_name ) ); ?></h4>
                <div class="feature-list" style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php foreach ( $category_features as $feature ) : 
                        $data = $assigned_data[$feature['key']] ?? array('access_type' => 'none', 'settings' => array());
                        $is_checked = ($data['access_type'] !== 'none');
                        $current_access_type = $data['access_type'] === 'none' ? 'full' : $data['access_type'];
                        $settings = $data['settings'];
                        $limit_value = $settings['limit'] ?? '';
                    ?>
                    <div class="feature-item" style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
                            <label class="feature-toggle" style="display: flex; gap: 1rem; flex: 1; cursor: pointer;">
                                <input type="checkbox" 
                                       class="feature-checkbox" 
                                       value="<?php echo esc_attr( $feature['key'] ); ?>" 
                                       data-feature-name="<?php echo esc_attr( $feature['name'] ); ?>"
                                       style="margin-top: 0.25rem;"
                                       <?php checked( $is_checked ); ?>>
                                <span class="feature-info">
                                    <span style="display: block; font-weight: 700; color: #111827;"><?php echo esc_html( $feature['name'] ); ?></span>
                                    <span style="display: block; font-size: 0.875rem; color: #6b7280; margin-top: 0.25rem;"><?php echo esc_html( $feature['description'] ); ?></span>
                                </span>
                            </label>
                            
                            <div class="access-manager" style="display: flex; flex-direction: column; gap: 0.75rem; min-width: 200px;">
                                <select class="access-type-select" 
                                        id="access_type_<?php echo esc_attr( $feature['key'] ); ?>"
                                        onchange="toggleLimitationManager('<?php echo esc_attr($feature['key']); ?>')"
                                        style="width: 100%; border-radius: 0.375rem; border-color: #d1d5db;"
                                        <?php disabled( ! $is_checked ); ?>>
                                    <option value="full" <?php selected( $current_access_type, 'full' ); ?>>Full Access</option>
                                    <option value="limited" <?php selected( $current_access_type, 'limited' ); ?>>Limited Access</option>
                                    <option value="readonly" <?php selected( $current_access_type, 'readonly' ); ?>>Read Only</option>
                                </select>
                                
                                <div id="limitation_manager_<?php echo esc_attr($feature['key']); ?>" 
                                     class="limitation-manager" 
                                     style="display: <?php echo $is_checked ? 'block' : 'none'; ?>; background: #fff; padding: 1rem; border: 1px solid #e5e7eb; border-radius: 0.5rem; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">
                                    
                                    <?php if ( !empty($feature['limitations']) && is_array($feature['limitations']) ) : ?>
                                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                        <?php foreach ($feature['limitations'] as $limit_id => $limit_config) : 
                                            $limit_val = $settings[$limit_id] ?? ($settings['limit'] ?? ''); 
                                        ?>
                                            <div class="limitation-field">
                                                <label style="font-size: 0.75rem; font-weight: 600; color: #4b5563; display: block; margin-bottom: 0.25rem;">
                                                    <?php echo esc_html($limit_config['name']); ?>
                                                </label>
                                                <input type="<?php echo esc_attr($limit_config['type']); ?>" 
                                                       class="granular-limit"
                                                       data-limit-id="<?php echo esc_attr($limit_id); ?>"
                                                       id="limit_<?php echo esc_attr($feature['key']); ?>_<?php echo esc_attr($limit_id); ?>" 
                                                       value="<?php echo esc_attr($limit_val); ?>" 
                                                       placeholder="<?php echo esc_attr($limit_config['placeholder'] ?? ''); ?>"
                                                       style="width: 100%; font-size: 0.875rem; padding: 0.375rem; border-radius: 0.25rem; border: 1px solid #d1d5db;">
                                                <?php if (!empty($limit_config['description'])) : ?>
                                                    <span style="font-size: 0.65rem; color: #9ca3af;"><?php echo esc_html($limit_config['description']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        </div>
                                    <?php else : ?>
                                        <label style="font-size: 0.75rem; font-weight: 600; color: #4b5563; display: block; margin-bottom: 0.5rem;">LIMITATION (Count/Value)</label>
                                        <input type="number" 
                                               id="limit_<?php echo esc_attr($feature['key']); ?>" 
                                               class="general-limit"
                                               value="<?php echo esc_attr($limit_value); ?>" 
                                               placeholder="e.g. 5"
                                               style="width: 100%; font-size: 0.875rem; padding: 0.375rem; border-radius: 0.25rem; border: 1px solid #d1d5db;">
                                        <p style="font-size: 0.7rem; color: #9ca3af; margin-top: 0.4rem; line-height: 1.2;">Leave empty for system defaults or enter 0 for no limit.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <script>
        function toggleLimitationManager(featureKey) {
            // Limitation fields remain visible regardless of access type when feature is enabled
        }
        
        // Initial setup for checkboxes
        document.querySelectorAll('.feature-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                const featureKey = this.value;
                const select = document.getElementById('access_type_' + featureKey);
                if (select) select.disabled = !this.checked;
                
                const manager = document.getElementById('limitation_manager_' + featureKey);
                if (manager) {
                    manager.style.display = this.checked ? 'block' : 'none';
                }
            });
        });
        </script>
    </div>
    <?php
    
    $form = ob_get_clean();
    wp_send_json_success( array( 'form' => $form ) );
}

add_action( 'wp_ajax_orabooks_save_feature_assignments', 'orabooks_save_feature_assignments' );
function orabooks_save_feature_assignments() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Access denied' );
    check_ajax_referer( 'orabooks-admin-nonce', 'nonce' );
    
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    $level_id = intval( $_POST['level_id'] ?? 0 );
    if ( $level_id <= 0 ) {
        wp_send_json_error( 'Invalid level ID' );
        return;
    }
    
    $features = array();
    if ( isset( $_POST['features'] ) ) {
        if ( is_string( $_POST['features'] ) ) {
            $features = json_decode( stripslashes( $_POST['features'] ), true );
        } else {
            $features = $_POST['features'];
        }
    }
    
    if ( ! is_array( $features ) ) {
        wp_send_json_error( 'Invalid features data' );
        return;
    }
    
    // Delete existing assignments
    $wpdb->delete( $wpdb->orabooks_feature_assignments, array( 'level_id' => $level_id ) );
    
    $inserted = 0;
    $user_id = get_current_user_id();
    
    foreach ( $features as $feature_key => $data ) {
        if ( ! isset( $data['enabled'] ) || $data['enabled'] !== 'yes' ) {
            continue;
        }
        
        $access_type = $data['access_type'] ?? 'full';
        $settings = array(
            'available' => true,
        );
        
        if (isset($data['settings']) && is_array($data['settings'])) {
            $settings = array_merge($settings, $data['settings']);
        } elseif (isset($data['limit'])) {
            $settings['limit'] = $data['limit'] !== '' ? intval($data['limit']) : null;
        }
        
        $wpdb->insert( 
            $wpdb->orabooks_feature_assignments, 
            array(
                'level_id' => $level_id,
                'feature_key' => sanitize_text_field($feature_key),
                'feature_name' => sanitize_text_field($data['name'] ?? ''),
                'access_type' => sanitize_text_field($access_type),
                'settings' => wp_json_encode($settings),
                'created_by' => $user_id,
                'updated_by' => $user_id,
                'created_at' => current_time('mysql')
            )
        );
        $inserted++;
    }
    
    wp_send_json_success( array( 'message' => "Successfully saved $inserted feature assignments.", 'inserted' => $inserted ) );
}

// Revenue data AJAX
add_action( 'wp_ajax_orabooks_get_revenue_data', 'orabooks_get_revenue_data' );
function orabooks_get_revenue_data() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    check_ajax_referer( 'orabooks-admin-nonce', 'nonce' );
    
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    $table = $wpdb->orabooks_orders;
    $type = sanitize_text_field( $_GET['type'] ?? 'monthly' );
    $start = !empty( $_GET['start'] ) ? sanitize_text_field( $_GET['start'] ) : date( 'Y-m-01', strtotime( '-11 months' ) );
    $end = !empty( $_GET['end'] ) ? sanitize_text_field( $_GET['end'] ) : date( 'Y-m-t' );

    $results = array( 'labels' => array(), 'totals' => array() );
    $total_orders = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = %s", 'completed' ) );
    $has_real_orders = $total_orders > 0;

    if ( $has_real_orders ) {
        // Get real data based on type
        if ( $type === 'daily' ) {
            $query = $wpdb->prepare( "
                SELECT DATE(created_at) as label, COALESCE(SUM(amount), 0) as total
                FROM $table
                WHERE status = %s AND created_at BETWEEN %s AND %s
                GROUP BY DATE(created_at)
                ORDER BY created_at ASC
            ", 'completed', $start . ' 00:00:00', $end . ' 23:59:59' );
        } elseif ( $type === 'yearly' ) {
            $query = $wpdb->prepare( "
                SELECT YEAR(created_at) as label, COALESCE(SUM(amount), 0) as total 
                FROM $table 
                WHERE status = %s
                GROUP BY YEAR(created_at) 
                ORDER BY YEAR(created_at) ASC
            ", 'completed' );
        } else {
            $query = $wpdb->prepare( "
                SELECT DATE_FORMAT(created_at, '%b %Y') as label, COALESCE(SUM(amount), 0) as total
                FROM $table 
                WHERE status = %s AND created_at BETWEEN %s AND %s
                GROUP BY YEAR(created_at), MONTH(created_at) 
                ORDER BY created_at ASC
            ", 'completed', $start . ' 00:00:00', $end . ' 23:59:59' );
        }
        
        $data = $wpdb->get_results( $query );
        foreach ( $data as $row ) {
            $results['labels'][] = $row->label;
            $results['totals'][] = floatval( $row->total );
        }
        $results['is_demo_data'] = false;
    } else {
        // Generate sample data
        $results = orabooks_generate_sample_revenue_data( $type, $start, $end );
        $results['is_demo_data'] = true;
    }
    
    $results['total_orders'] = $total_orders;
    wp_send_json( $results );
}

function orabooks_generate_sample_revenue_data( $type = 'monthly', $start = '', $end = '' ) {
    $results = array( 'labels' => array(), 'totals' => array() );
    
    if ( $type === 'daily' ) {
        $current = new DateTime( $start );
        $end_date = new DateTime( $end );
        while ( $current <= $end_date ) {
            $results['labels'][] = $current->format( 'Y-m-d' );
            $results['totals'][] = rand( 50, 500 );
            $current->modify( '+1 day' );
        }
    } elseif ( $type === 'yearly' ) {
        $start_year = date( 'Y', strtotime( $start ) );
        $end_year = date( 'Y', strtotime( $end ) );
        for ( $year = $start_year; $year <= $end_year; $year++ ) {
            $results['labels'][] = $year;
            $results['totals'][] = rand( 1000, 5000 );
        }
    } else {
        $current = new DateTime( $start );
        $end_date = new DateTime( $end );
        while ( $current <= $end_date ) {
            $results['labels'][] = $current->format( 'M Y' );
            $results['totals'][] = rand( 100, 1000 );
            $current->modify( '+1 month' );
        }
    }
    
    return $results;
}

// Add missing AJAX handlers
add_action( 'wp_ajax_orabooks_get_level', 'orabooks_ajax_get_level' );
function orabooks_ajax_get_level() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Access denied' );
    check_ajax_referer( 'orabooks-admin-nonce', 'nonce' );
    
    $level_id = intval( $_POST['level_id'] ?? 0 );
    if ( $level_id <= 0 ) wp_send_json_error( 'Invalid level ID' );
    
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    $level = $wpdb->get_row( $wpdb->prepare( 
        "SELECT * FROM {$wpdb->orabooks_levels} WHERE id = %d", 
        $level_id 
    ) );
    
    if ( $level ) {
        wp_send_json_success( $level );
    } else {
        wp_send_json_error( 'Level not found' );
    }
}

// Add cleanup data AJAX handler
add_action( 'wp_ajax_orabooks_cleanup_data', 'orabooks_ajax_cleanup_data' );
function orabooks_ajax_cleanup_data() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Access denied' );
    check_ajax_referer( 'orabooks-admin-nonce', 'nonce' );
    
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    // Delete orders older than 1 year
    $one_year_ago = date('Y-m-d H:i:s', strtotime('-1 year'));
    $deleted_count = $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->orabooks_orders} WHERE created_at < %s AND status != 'completed'",
            $one_year_ago
        )
    );
    
    if ( $deleted_count === false ) {
        wp_send_json_error( 'Database error: ' . $wpdb->last_error );
    }
    
    wp_send_json_success( $deleted_count . ' old records cleaned up' );
}

// AJAX handler for saving settings
add_action( 'wp_ajax_orabooks_save_settings', 'orabooks_ajax_save_settings' );
function orabooks_ajax_save_settings() {
    // Prevent any output before JSON response
    // Clean any existing output buffers to prevent PHP notices from breaking JSON
    $max_cleans = 20;
    while ( ob_get_level() && $max_cleans > 0 ) {
        ob_end_clean();
        $max_cleans--;
    }
    
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'orabooks-admin-nonce' ) ) {
        wp_send_json_error( 'Security check failed' );
        // wp_send_json_error() already calls wp_die()
    }
    
    // Check permission
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied' );
        // wp_send_json_error() already calls wp_die()
    }
    
    try {
        // Currency Settings - BDT only
        update_option( 'orabooks_currency_symbol', '৳' );
        update_option( 'orabooks_currency_position', 'after' );
        
        // Page Settings - use null coalescing to prevent undefined index warnings
        update_option( 'orabooks_pricing_page_id', intval( $_POST['pricing_page_id'] ?? 0 ) );
        update_option( 'orabooks_checkout_page_id', intval( $_POST['checkout_page_id'] ?? 0 ) );
        update_option( 'orabooks_account_page_id', intval( $_POST['account_page_id'] ?? 0 ) );
        update_option( 'orabooks_features_page_id', intval( $_POST['features_page_id'] ?? 0 ) );
        update_option( 'orabooks_confirmation_page_id', intval( $_POST['confirmation_page_id'] ?? 0 ) );
        update_option( 'orabooks_register_page_id', intval( $_POST['register_page_id'] ?? 0 ) );
        update_option( 'orabooks_login_page_id', intval( $_POST['login_page_id'] ?? 0 ) );
        
        // Email Settings
        $from_email = isset( $_POST['from_email'] ) ? sanitize_email( $_POST['from_email'] ) : '';
        $from_name = isset( $_POST['from_name'] ) ? sanitize_text_field( $_POST['from_name'] ) : '';
        update_option( 'orabooks_from_email', $from_email ? $from_email : get_bloginfo('admin_email') );
        update_option( 'orabooks_from_name', $from_name ? $from_name : get_bloginfo('name') );
        

        
        // Payment Settings (network-wide on multisite — main site)
        if (function_exists('orabooks_save_payment_settings_from_post')) {
            orabooks_save_payment_settings_from_post($_POST);
        }
        
        // Currency Settings
        update_option('orabooks_currency_code', sanitize_text_field($_POST['currency_code'] ?? 'BDT'));
        update_option('orabooks_currency_name', sanitize_text_field($_POST['currency_name'] ?? 'Bangladeshi Taka'));
        update_option('orabooks_currency_symbol', sanitize_text_field($_POST['currency_symbol'] ?? '৳'));
        update_option('orabooks_currency_position', sanitize_text_field($_POST['currency_position'] ?? 'before'));
        update_option('orabooks_currency_decimals', intval($_POST['currency_decimals'] ?? 2));
        update_option('orabooks_currency_thousands_separator', sanitize_text_field($_POST['currency_thousands_separator'] ?? ','));

        // Security Settings (ReCAPTCHA)
        $recaptcha_site_key = isset( $_POST['recaptcha_site_key'] ) ? sanitize_text_field( $_POST['recaptcha_site_key'] ) : '';
        update_option( 'orabooks_recaptcha_site_key', $recaptcha_site_key );
        if ( ! empty( $_POST['recaptcha_secret_key'] ) ) {
            update_option( 'orabooks_recaptcha_secret_key', sanitize_text_field( $_POST['recaptcha_secret_key'] ) );
        }

        // Feature Settings
        if ( isset( $_POST['features_config'] ) && is_array( $_POST['features_config'] ) ) {
            $features_config = array();
            foreach ( $_POST['features_config'] as $feature_key => $feature ) {
                if ( is_array( $feature ) ) {
                    $features_config[$feature_key] = array(
                        'name' => sanitize_text_field( $feature['name'] ?? '' ),
                        'description' => sanitize_text_field( $feature['description'] ?? '' ),
                        'subdomain_path' => sanitize_text_field( $feature['subdomain_path'] ?? '' ),
                        'enabled_levels' => isset( $feature['enabled_levels'] ) && is_array( $feature['enabled_levels'] ) ? array_map( 'intval', $feature['enabled_levels'] ) : array()
                    );
                }
            }
            update_option( 'orabooks_features_config', $features_config );
        }
        
        wp_send_json_success( 'Settings saved successfully!' );
        // wp_send_json_success() already calls wp_die()
    } catch ( Exception $e ) {
        error_log( 'Orabooks settings save error: ' . $e->getMessage() );
        wp_send_json_error( 'Error saving settings: ' . $e->getMessage() );
    }
}

// User-initiated account deletion handler
add_action('wp_ajax_orabooks_delete_own_account', 'orabooks_delete_own_account');

// Dashboard Chart Data Handlers
add_action('wp_ajax_orabooks_get_revenue_by_level', 'orabooks_get_revenue_by_level');
function orabooks_get_revenue_by_level() {
    // Build Guide Compliance: Security nonce verification
    if (!wp_verify_nonce($_POST['nonce'], 'orabooks_dashboard_charts')) {
        wp_send_json_error('Security check failed');
    }
    
    // Build Guide Compliance: Permission check
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    // Memory optimization: Limit results
    $revenue_by_level = $wpdb->get_results("
        SELECT 
            l.name,
            COALESCE(SUM(o.amount), 0) as revenue
        FROM {$wpdb->orabooks_levels} l
        LEFT JOIN {$wpdb->orabooks_orders} o ON l.id = o.level_id AND o.status = 'completed'
        WHERE l.is_active = 1
        GROUP BY l.id, l.name
        HAVING revenue > 0
        ORDER BY revenue DESC
        LIMIT 10
    ");
    
    $labels = array();
    $values = array();
    
    foreach ($revenue_by_level as $level) {
        if ($level->revenue > 0) {
            $labels[] = $level->name;
            $values[] = floatval($level->revenue);
        }
    }
    
    wp_send_json_success(array(
        'labels' => $labels,
        'values' => $values
    ));
}

add_action('wp_ajax_orabooks_get_payment_methods', 'orabooks_get_payment_methods');
function orabooks_get_payment_methods() {
    // Build Guide Compliance: Security nonce verification
    if (!wp_verify_nonce($_POST['nonce'], 'orabooks_dashboard_charts')) {
        wp_send_json_error('Security check failed');
    }
    
    // Build Guide Compliance: Permission check
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    // Memory optimization: Limit results
    $payment_methods = $wpdb->get_results("
        SELECT 
            CASE 
                WHEN order_id LIKE 'SHJ%' THEN 'ShurjoPay'
                WHEN order_id LIKE 'SSL%' THEN 'SSL Commerz'
                WHEN order_id LIKE 'BANK%' THEN 'Bank Transfer'
                WHEN order_id LIKE 'PAYPAL%' THEN 'PayPal'
                ELSE 'Other'
            END as payment_method,
            COUNT(*) as order_count
        FROM {$wpdb->orabooks_orders}
        WHERE status = 'completed'
        GROUP BY payment_method
        ORDER BY order_count DESC
        LIMIT 10
    ");
    
    $labels = array();
    $values = array();
    
    foreach ($payment_methods as $method) {
        $labels[] = $method->payment_method;
        $values[] = intval($method->order_count);
    }
    
    wp_send_json_success(array(
        'labels' => $labels,
        'values' => $values
    ));
}

add_action('wp_ajax_orabooks_get_monthly_trend', 'orabooks_get_monthly_trend');
function orabooks_get_monthly_trend() {
    // Build Guide Compliance: Security nonce verification
    if (!wp_verify_nonce($_POST['nonce'], 'orabooks_dashboard_charts')) {
        wp_send_json_error('Security check failed');
    }
    
    // Build Guide Compliance: Permission check
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    $monthly_revenue = $wpdb->get_results("
        SELECT 
            DATE_FORMAT(created_at, '%b %Y') as month_label,
            DATE_FORMAT(created_at, '%Y-%m') as month_key,
            SUM(amount) as monthly_revenue
        FROM {$wpdb->orabooks_orders}
        WHERE status = 'completed' 
        AND created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
        GROUP BY month_label, month_key
        ORDER BY month_key ASC
    ");
    
    $labels = array();
    $values = array();
    
    // Fill missing months with zero revenue
    $all_months = array();
    for ($i = 5; $i >= 0; $i--) {
        $month = date('M Y', strtotime("-$i months"));
        $all_months[$month] = 0;
    }
    
    foreach ($monthly_revenue as $month) {
        $all_months[$month->month_label] = floatval($month->monthly_revenue);
    }
    
    foreach ($all_months as $month => $revenue) {
        $labels[] = $month;
        $values[] = $revenue;
    }
    
    wp_send_json_success(array(
        'labels' => $labels,
        'values' => $values
    ));
}

function orabooks_delete_own_account() {
    // Basic verification
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'You must be logged in to perform this action.' );
    }

    check_ajax_referer( 'orabooks-frontend-nonce', 'nonce' );

    $user_id = get_current_user_id();
    $reason = sanitize_textarea_field( $_POST['reason'] ?? 'No reason provided.' );

    // Log the deletion request for admin audit (could be a table, but error log for now)
    error_log( "Orabooks: User ID $user_id is deleting their account. Reason: $reason" );

    // Perform deletion
    if ( is_multisite() ) {
        require_once( ABSPATH . 'wp-admin/includes/ms.php' );
        
        // Find and delete user's sites
        $user_blogs = get_blogs_of_user( $user_id );
        foreach ( $user_blogs as $blog ) {
            $blog_id = (int) $blog->userblog_id;
            // Never delete the main site!
            if ( $blog_id !== 1 ) {
                wpmu_delete_blog( $blog_id, true );
            }
        }
        
        // Delete user from the network
        wpmu_delete_user( $user_id );
    } else {
        require_once( ABSPATH . 'wp-admin/includes/user.php' );
        wp_delete_user( $user_id );
    }

    wp_send_json_success( 'Account and associated data deleted successfully. We are sorry to see you go.' );
}

// End of AJAX Handlers
