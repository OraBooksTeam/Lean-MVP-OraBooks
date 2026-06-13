<?php
if (!defined('ABSPATH')) exit;

class OraBooks_Payment_Processor {
    
    public function __construct() {
        add_action('wp_ajax_orabooks_process_payment', array($this, 'process_payment'));
        add_action('wp_ajax_nopriv_orabooks_process_payment', array($this, 'process_payment'));
        add_action('template_redirect', array($this, 'handle_payment_callbacks'));
    }
    
    public function process_payment() {
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to make a payment');
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'orabooks_payment_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $level_id = isset($_POST['level_id']) ? intval($_POST['level_id']) : 0;
        $gateway = isset($_POST['gateway']) ? sanitize_key($_POST['gateway']) : '';
        $user_id = get_current_user_id();
        
        // Build Guide Compliance: Get current mode
        $current_mode = class_exists('OraBooks_Mode_Manager') ? OraBooks_Mode_Manager::get_current_mode() : 'business';
        
        $level = orabooks_get_level($level_id);
        if (!$level) {
            wp_send_json_error('Invalid membership level');
        }
        
        // Create order record
        global $wpdb;
        orabooks_handle_multisite_tables();
        $order_id = strtoupper($gateway) . time() . rand(1000, 9999);
        $order_data = array(
            'order_id' => $order_id,
            'user_id' => $user_id,
            'level_id' => $level_id,
            'gateway' => $gateway,
            'amount' => $level->price,
            'status' => 'pending',
            'mode' => $current_mode,
            'created_by' => $user_id,
            'updated_by' => $user_id,
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert($wpdb->orabooks_orders, $order_data);
        $order_row_id = $wpdb->insert_id;
        
        // Build Guide Compliance: Log order creation for audit trail
        if (class_exists('OraBooks_Audit_Logger')) {
            $logger = OraBooks_Audit_Logger::get_instance();
            $logger->log_action(array(
                'user_id' => $user_id,
                'action_type' => 'payment_initiated',
                'action_description' => sprintf('Payment initiated via %s: Order %s for Level %d in %s mode', $gateway, $order_id, $level_id, $current_mode),
                'mode' => $current_mode,
                'entity_type' => 'order',
                'entity_id' => $order_row_id,
                'after_state' => array(
                    'order_id' => $order_id,
                    'amount' => $level->price,
                    'gateway' => $gateway
                )
            ));
        }
        
        // Process payment based on gateway
        $result = $this->process_gateway_payment($gateway, $order_id, $level->price, $level_id, $user_id);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'redirect_url' => $result['redirect_url'],
                'order_id' => $order_id
            ));
        } else {
            // Update order status to failed
            $wpdb->update(
                $wpdb->orabooks_orders,
                array(
                    'status' => 'failed',
                    'updated_by' => $user_id
                ),
                array('id' => $order_row_id)
            );
            
            // Build Guide Compliance: Log payment failure for audit trail
            if (class_exists('OraBooks_Audit_Logger')) {
                $logger = OraBooks_Audit_Logger::get_instance();
                $logger->log_action(array(
                    'user_id' => $user_id,
                    'action_type' => 'payment_failed',
                    'action_description' => sprintf('Payment failed via %s: Order %s', $gateway, $order_id),
                    'mode' => $current_mode,
                    'entity_type' => 'order',
                    'entity_id' => $order_row_id
                ));
            }
            
            wp_send_json_error($result['message']);
        }
    }
    
    private function process_gateway_payment($gateway, $order_id, $amount, $level_id, $user_id) {
        switch ($gateway) {
            case 'sslcommerz':
                return $this->process_sslcommerz_payment($order_id, $amount, $level_id, $user_id);
                
            case 'paypal':
                return $this->process_paypal_payment($order_id, $amount, $level_id, $user_id);
                
            case 'stripe':
                return $this->process_stripe_payment($order_id, $amount, $level_id, $user_id);
                
            case 'bank_transfer':
                return $this->process_bank_transfer_payment($order_id, $amount, $level_id, $user_id);
                
            case 'shurjopay':
                return $this->process_shurjopay_payment($order_id, $amount, $level_id, $user_id);
                
            default:
                return array(
                    'success' => false,
                    'message' => 'Invalid payment gateway'
                );
        }
    }
    
    private function process_sslcommerz_payment($order_id, $amount, $level_id, $user_id) {
        $store_id = orabooks_get_membership_option('orabooks_sslcommerz_store_id');
        $store_password = orabooks_get_membership_option('orabooks_sslcommerz_store_password');
        $test_mode = orabooks_get_membership_option('orabooks_sslcommerz_test_mode', 1);
        
        if (!$store_id || !$store_password) {
            return array(
                'success' => false,
                'message' => 'SSL Commerz is not properly configured'
            );
        }
        
        $user = get_userdata($user_id);
        $currency = get_option('orabooks_currency', 'BDT');
        
        $post_data = array(
            'store_id' => $store_id,
            'store_passwd' => $store_password,
            'total_amount' => $amount,
            'currency' => $currency,
            'tran_id' => $order_id,
            'success_url' => home_url('/?orabooks_payment=sslcommerz_success'),
            'fail_url' => home_url('/?orabooks_payment=sslcommerz_failed'),
            'cancel_url' => home_url('/?orabooks_payment=sslcommerz_cancelled'),
            'cus_name' => $user->display_name,
            'cus_email' => $user->user_email,
            'cus_phone' => 'N/A',
            'product_category' => 'Membership',
            'product_name' => 'OraBooks Membership',
            'product_profile' => 'general'
        );
        
        $api_url = $test_mode ? 
            'https://sandbox.sslcommerz.com/gwprocess/v4/api.php' : 
            'https://securepay.sslcommerz.com/gwprocess/v4/api.php';
        
        $response = wp_remote_post($api_url, array(
            'body' => $post_data,
            'timeout' => 30
        ));
        
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $result = json_decode($body, true);
            
            if (isset($result['status']) && $result['status'] === 'SUCCESS') {
                return array(
                    'success' => true,
                    'redirect_url' => $result['GatewayPageURL']
                );
            }
        }
        
        return array(
            'success' => false,
            'message' => 'Payment initiation failed. Please try again.'
        );
    }
    
    private function process_paypal_payment($order_id, $amount, $level_id, $user_id) {
        $paypal_email = orabooks_get_membership_option('orabooks_paypal_email');
        $sandbox = orabooks_get_membership_option('orabooks_paypal_sandbox', 1);
        $currency = get_option('orabooks_currency', 'BDT');
        
        if (!$paypal_email) {
            return array(
                'success' => false,
                'message' => 'PayPal is not properly configured'
            );
        }
        
        $paypal_url = $sandbox ? 
            'https://www.sandbox.paypal.com/cgi-bin/webscr' : 
            'https://www.paypal.com/cgi-bin/webscr';
            
        $success_url = orabooks_get_membership_option('orabooks_payment_success_page') ? 
            get_permalink(orabooks_get_membership_option('orabooks_payment_success_page')) : 
            home_url('/orabooks-confirmation');
            
        $paypal_args = array(
            'cmd' => '_xclick',
            'business' => $paypal_email,
            'currency_code' => $currency,
            'amount' => $amount,
            'item_name' => 'OraBooks Membership',
            'item_number' => $level_id,
            'custom' => $order_id,
            'return' => $success_url . '?payment=success&gateway=paypal',
            'cancel_return' => home_url('/?orabooks_payment=paypal_cancelled'),
            'notify_url' => home_url('/?orabooks_payment=paypal_ipn'),
            'no_note' => '1',
            'no_shipping' => '1'
        );
        
        $redirect_url = $paypal_url . '?' . http_build_query($paypal_args);
        
        return array(
            'success' => true,
            'redirect_url' => $redirect_url
        );
    }
    
    private function process_stripe_payment($order_id, $amount, $level_id, $user_id) {
        // This would integrate with Stripe API
        // For now, return a placeholder
        return array(
            'success' => false,
            'message' => 'Stripe integration coming soon'
        );
    }
    
    private function process_bank_transfer_payment($order_id, $amount, $level_id, $user_id) {
        // For bank transfer, we just create the order and show instructions
        global $wpdb;
        orabooks_handle_multisite_tables();
        
        $wpdb->update(
            $wpdb->orabooks_orders,
            array('status' => 'pending_bank_transfer'),
            array('order_id' => $order_id)
        );
        
        $instructions = orabooks_get_membership_option('orabooks_bank_transfer_instructions');
        $bank_transfer_page = orabooks_get_or_create_page('Bank Transfer Instructions', '');
        
        if ($bank_transfer_page) {
            $redirect_url = add_query_arg(array(
                'order_id' => $order_id,
                'payment_type' => 'bank_transfer'
            ), get_permalink($bank_transfer_page->ID));
        } else {
            $redirect_url = home_url('/?orabooks_payment=bank_transfer&order=' . $order_id);
        }
        
        return array(
            'success' => true,
            'redirect_url' => $redirect_url
        );
    }
    
    private function process_shurjopay_payment($order_id, $amount, $level_id, $user_id) {
        // Delegate to ShurjoPay gateway class
        if (class_exists('OraBooks_ShurjoPay_Gateway')) {
            try {
                // Ensure credentials are set properly before usage
                $gateway_obj = new OraBooks_ShurjoPay_Gateway();
                
                // Note: The OraBooks_ShurjoPay_Gateway::process_payment method handles its own credential validation.
                $result = $gateway_obj->process_payment($order_id, $amount, $level_id);
                
                if (is_array($result) && isset($result['result']) && $result['result'] === 'success' && !empty($result['redirect_url'])) {
                    return array(
                        'success' => true,
                        'redirect_url' => $result['redirect_url']
                    );
                } else {
                    $message = is_array($result) && isset($result['message']) ? $result['message'] : 'ShurjoPay payment failed';
                    return array(
                        'success' => false,
                        'message' => $message
                    );
                }
            } catch (Exception $e) {
                error_log('ShurjoPay Payment Error: ' . $e->getMessage());
                return array(
                    'success' => false,
                    'message' => 'Payment processing error: ' . $e->getMessage()
                );
            }
        }
        
        return array(
            'success' => false,
            'message' => 'ShurjoPay gateway class is not available. Please reinstall the plugin.'
        );
    }
    
    public function handle_payment_callbacks() {
        if (isset($_GET['orabooks_payment'])) {
            $payment_type = sanitize_text_field($_GET['orabooks_payment']);
            
            switch ($payment_type) {
                case 'sslcommerz_success':
                case 'sslcommerz_failed':
                    $this->handle_sslcommerz_callback();
                    break;
                    
                case 'paypal_ipn':
                    $this->handle_paypal_ipn();
                    break;
            }
        }
    }
    
    private function handle_sslcommerz_callback() {
        if (isset($_POST['tran_id']) && isset($_POST['status'])) {
            $order_id = sanitize_text_field($_POST['tran_id']);
            $status = sanitize_text_field($_POST['status']);
            
            global $wpdb;
            orabooks_handle_multisite_tables();
            $order = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->orabooks_orders} WHERE order_id = %s",
                $order_id
            ));
            
            if ($order && $status === 'VALID') {
                // Verify payment with SSL Commerz using POST for security
                $store_id = orabooks_get_membership_option('orabooks_sslcommerz_store_id');
                $store_passwd = orabooks_get_membership_option('orabooks_sslcommerz_store_password');
                $verify_data = array(
                    'store_id' => $store_id,
                    'store_passwd' => $store_passwd,
                    'val_id' => isset($_POST['val_id']) ? sanitize_text_field($_POST['val_id']) : '',
                    'format' => 'json'
                );
                
                $verify_url = orabooks_get_membership_option('orabooks_sslcommerz_test_mode', 1) ?
                    'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php' :
                    'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php';
                
                // Use POST instead of GET to avoid credentials in URL logs
                $verify_response = wp_remote_post($verify_url, array(
                    'body' => $verify_data,
                    'timeout' => 30
                ));
                
                if (!is_wp_error($verify_response)) {
                    $verify_result = json_decode(wp_remote_retrieve_body($verify_response), true);
                    
                    if (isset($verify_result['status']) && $verify_result['status'] === 'VALID') {
                        // Payment successful
                        $wpdb->update(
                            $wpdb->orabooks_orders,
                            array('status' => 'completed'),
                            array('id' => $order->id)
                        );
                        
                        // Trigger order completion
                        do_action('orabooks_order_completed', $order->id, $order);
                        
                        // Default success URL
                        $success_url = orabooks_get_membership_option('orabooks_payment_success_page') ? 
                            get_permalink(orabooks_get_membership_option('orabooks_payment_success_page')) : 
                            home_url('/orabooks-confirmation');
                        $success_url .= '?payment=success&gateway=sslcommerz';
                        
                        // Apply filter to allow redirect to wp-signup if user has no site
                        $redirect_url = apply_filters('orabooks_payment_success_redirect', $success_url, $order->id, $order);
                        
                        wp_redirect($redirect_url);
                        exit;
                    }
                }
            }
            
            $failure_url = orabooks_get_membership_option('orabooks_payment_failure_page') ? 
                get_permalink(orabooks_get_membership_option('orabooks_payment_failure_page')) : 
                home_url('/orabooks-confirmation');
                
            wp_redirect($failure_url . '?payment=failed&gateway=sslcommerz');
            exit;
        }
    }
    
    private function handle_paypal_ipn() {
        // Read POST data from PayPal
        $raw_post_data = file_get_contents('php://input');
        if (empty($raw_post_data)) {
            return;
        }
        
        // Build the verification request to PayPal
        $sandbox = orabooks_get_membership_option('orabooks_paypal_sandbox', 1);
        $paypal_url = $sandbox ? 
            'https://www.sandbox.paypal.com/cgi-bin/webscr' : 
            'https://www.paypal.com/cgi-bin/webscr';
        
        $verify_data = 'cmd=_notify-validate&' . $raw_post_data;
        
        $response = wp_remote_post($paypal_url, array(
            'body' => $verify_data,
            'timeout' => 30,
            'headers' => array(
                'Connection' => 'close',
                'User-Agent' => 'OraBooks-Membership'
            )
        ));
        
        if (is_wp_error($response)) {
            return;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_body !== 'VERIFIED') {
            return;
        }
        
        // Parse the IPN data
        $ipn_data = array();
        parse_str($raw_post_data, $ipn_data);
        
        $txn_type = isset($ipn_data['txn_type']) ? sanitize_text_field($ipn_data['txn_type']) : '';
        $payment_status = isset($ipn_data['payment_status']) ? sanitize_text_field($ipn_data['payment_status']) : '';
        $custom = isset($ipn_data['custom']) ? sanitize_text_field($ipn_data['custom']) : '';
        $txn_id = isset($ipn_data['txn_id']) ? sanitize_text_field($ipn_data['txn_id']) : '';
        $receiver_email = isset($ipn_data['receiver_email']) ? sanitize_text_field($ipn_data['receiver_email']) : '';
        $mc_gross = isset($ipn_data['mc_gross']) ? floatval($ipn_data['mc_gross']) : 0;
        
        // Verify receiver email matches our configuration
        $paypal_email = orabooks_get_membership_option('orabooks_paypal_email');
        if (strtolower($receiver_email) !== strtolower($paypal_email)) {
            return;
        }
        
        // Only process completed payments
        if (strtolower($payment_status) !== 'completed') {
            return;
        }
        
        global $wpdb;
        orabooks_handle_multisite_tables();
        
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->orabooks_orders} WHERE order_id = %s AND gateway = 'paypal'",
            $custom
        ));
        
        if (!$order || $order->status === 'completed') {
            return;
        }
        
        // Update order to completed
        $wpdb->update(
            $wpdb->orabooks_orders,
            array(
                'status' => 'completed',
                'transaction_id' => $txn_id,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $order->id)
        );
        
        // Trigger order completion
        do_action('orabooks_order_completed', $order->id, $order);
    }
}

/**
 * Fresh payment nonce for dashboard / SPA checkout (nonces are not single-use).
 */
add_action('wp_ajax_orabooks_payment_nonce', 'orabooks_ajax_payment_nonce');
function orabooks_ajax_payment_nonce() {
    if (!is_user_logged_in()) {
        wp_send_json_error('Login required');
    }
    wp_send_json_success(array(
        'nonce' => wp_create_nonce('orabooks_payment_nonce'),
    ));
}

new OraBooks_Payment_Processor();