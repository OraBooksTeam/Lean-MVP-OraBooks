<?php
if (!defined('ABSPATH')) exit;

// Prevent multiple loads
if (class_exists('OraBooks_ShurjoPay_Gateway')) {
    return;
}

class OraBooks_ShurjoPay_Gateway extends OraBooks_Payment_Gateway {
    public function __construct() {
        parent::__construct();
        $this->id = 'shurjopay';
        $this->title = 'ShurjoPay';
        $this->description = 'Pay via ShurjoPay - Cards, Mobile Banking, Internet Banking';
    }

    public function is_available() {
        // Aggressively default to TRUE unless specifically disabled
        $enabled = orabooks_get_membership_option('orabooks_shurjopay_enabled', 1);
        return $enabled == 1;
    }
    
    public function process_payment($order_id, $amount, $level_id) {
        global $wpdb;
        
        // Build Guide Compliance: Get current mode
        $current_mode = class_exists('OraBooks_Mode_Manager') ? OraBooks_Mode_Manager::get_current_mode() : 'business';
        
        // Build Guide Compliance: Log payment attempt for audit trail
        if (class_exists('OraBooks_Audit_Logger')) {
            $logger = OraBooks_Audit_Logger::get_instance();
            $logger->log_action(array(
                'user_id' => get_current_user_id(),
                'action_type' => 'payment_attempt',
                'action_description' => sprintf('Payment attempt via ShurjoPay: Order %s, Amount %.2f', $order_id, $amount),
                'mode' => $current_mode,
                'entity_type' => 'order',
                'entity_id' => $order_id,
                'after_state' => array(
                    'gateway' => 'shurjopay',
                    'amount' => $amount,
                    'level_id' => $level_id
                )
            ));
        }
        
        // Ensure tables are set up
        if (function_exists('orabooks_handle_multisite_tables')) {
            orabooks_handle_multisite_tables();
        }
        
        // Check if orders table exists
        if (!isset($wpdb->orabooks_orders)) {
            error_log('ShurjoPay: orabooks_orders table not defined');
            return array(
                'result' => 'error',
                'message' => 'Database tables not configured. Please contact administrator.'
            );
        }
        
        // Build Guide Compliance: Use security class for input sanitization
        if (class_exists('OraBooks_Security')) {
            $security = OraBooks_Security::get_instance();
            $order_id = $security->sanitize_input($order_id);
            $amount = floatval($security->sanitize_input($amount));
            $level_id = intval($security->sanitize_input($level_id));
        }
        
        // Debug
        error_log('ShurjoPay processing payment for Order: ' . $order_id . ', Amount: ' . $amount . ', Level: ' . $level_id);
        
        // Get ShurjoPay credentials - defaulting to the values visible in Admin Settings if not yet saved
        $username = orabooks_get_membership_option('orabooks_shurjopay_username', '');
        $password = orabooks_get_membership_option('orabooks_shurjopay_password', '');
        
        error_log('ShurjoPay: Username configured: ' . (!empty($username) ? 'YES' : 'NO'));
        
        if (empty($username) || empty($password)) {
            return array(
                'result' => 'error',
                'message' => 'ShurjoPay credentials (username/password) are missing in settings.'
            );
        }
        
        $user = wp_get_current_user();
        $level = orabooks_get_level($level_id);
        
        if (!$level) {
            error_log('ShurjoPay: Level not found - ID: ' . $level_id);
            return array(
                'result' => 'error',
                'message' => 'Invalid membership level'
            );
        }
        
        // Step 1: Get authentication token from ShurjoPay
        error_log('ShurjoPay: Getting auth token...');
        $auth_response = $this->get_shurjopay_token($username, $password);
        
        if (is_wp_error($auth_response)) {
            error_log('ShurjoPay Token Error: ' . $auth_response->get_error_message());
            return array(
                'result' => 'error',
                'message' => 'ShurjoPay API Auth Error: ' . $auth_response->get_error_message()
            );
        }
        
        if (!isset($auth_response['token'])) {
            error_log('ShurjoPay Token Response Invalid: ' . print_r($auth_response, true));
            return array(
                'result' => 'error',
                'message' => 'ShurjoPay Authentication Failed. Check API Credentials.'
            );
        }
        
        $token = $auth_response['token'];
        $store_id = isset($auth_response['store_id']) ? $auth_response['store_id'] : '';
        
        error_log('ShurjoPay: Auth token received, Store ID: ' . $store_id);
        
        // Step 2: Prepare Payment Data
        $phone = get_user_meta($user->ID, 'billing_phone', true);
        if (empty($phone)) $phone = get_user_meta($user->ID, 'phone', true);
        if (empty($phone)) $phone = '01700000000'; // Default valid phone format

        $customer_address = get_user_meta($user->ID, 'billing_address_1', true);
        if (empty($customer_address)) $customer_address = 'Dhaka, Bangladesh';

        $customer_city = get_user_meta($user->ID, 'billing_city', true);
        if (empty($customer_city)) $customer_city = 'Dhaka';

        $order_prefix = orabooks_get_membership_option('orabooks_shurjopay_prefix', 'ETC');
        $final_order_id = !empty($order_id) ? $order_id : ($order_prefix . uniqid());

        // Construct Return URLs
        $return_url = home_url('/?orabooks_payment=shurjopay_return&order_id=' . rawurlencode($final_order_id));
        $cancel_url = home_url('/?orabooks_payment=shurjopay_cancel');

        $payment_data = array(
            'token' => $token,
            'store_id' => $store_id,
            'amount' => $amount,
            'order_id' => $final_order_id,
            'prefix' => $order_prefix,
            'currency' => 'BDT',
            'client_ip' => $this->get_client_ip(),
            'customer_name' => $user->display_name ?: 'Customer',
            'customer_email' => $user->user_email,
            'customer_phone' => $phone,
            'customer_address' => $customer_address,
            'customer_city' => $customer_city,
            'return_url' => $return_url,
            'cancel_url' => $cancel_url,
            'value1' => 'membership',
            'value2' => $level->name,
            'value3' => $user->ID
        );
        
        error_log('ShurjoPay: Payment data prepared, executing payment...');
        
        // Step 3: Execute Payment
        $execute_response = $this->execute_shurjopay_payment($payment_data);
        
        if (is_wp_error($execute_response)) {
             error_log('ShurjoPay Execute Error: ' . $execute_response->get_error_message());
             return array(
                'result' => 'error',
                'message' => 'ShurjoPay API Execute Error: ' . $execute_response->get_error_message()
            );
        }

        if (!$execute_response || !isset($execute_response['checkout_url'])) {
            error_log('ShurjoPay Execute Response Invalid: ' . print_r($execute_response, true));
            return array(
                'result' => 'error',
                'message' => 'ShurjoPay did not return a checkout URL.'
            );
        }
        
        error_log('ShurjoPay: Checkout URL received: ' . $execute_response['checkout_url']);
        
        // Save Order
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->orabooks_orders} WHERE order_id = %s", $payment_data['order_id']));
        if ($existing) {
            $wpdb->update($wpdb->orabooks_orders, array(
                'user_id' => $user->ID,
                'level_id' => $level_id,
                'gateway' => 'shurjopay',
                'amount' => $amount,
                'status' => 'pending'
            ), array('order_id' => $payment_data['order_id']));
        } else {
            $order_row = array(
                'order_id' => $payment_data['order_id'],
                'user_id' => $user->ID,
                'level_id' => $level_id,
                'gateway' => 'shurjopay',
                'amount' => $amount,
                'status' => 'pending',
                'created_at' => current_time('mysql')
            );
            $wpdb->insert($wpdb->orabooks_orders, $order_row);
        }
        
        error_log('ShurjoPay: Order saved successfully, redirecting...');
        
        return array(
            'result' => 'success',
            'redirect' => $execute_response['checkout_url'],
            'redirect_url' => $execute_response['checkout_url']
        );
    }
    
    private function get_shurjopay_token($username, $password) {
        $api_url = orabooks_get_membership_option('orabooks_shurjopay_api_url', 'https://engine.shurjopayment.com');
        $endpoint = rtrim($api_url, '/') . '/api/get_token';

        $post_fields = array(
            'username' => $username,
            'password' => $password
        );

        $response = wp_remote_post($endpoint, array(
            'body' => json_encode($post_fields),
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (isset($result['token'])) {
            return $result;
        }
        
        // Pass through error message from API if exists
        $msg = isset($result['message']) ? $result['message'] : 'Unknown Auth Error';
        return new WP_Error('shurjopay_auth_error', $msg);
    }
    
    private function execute_shurjopay_payment($payment_data) {
        $api_url = orabooks_get_membership_option('orabooks_shurjopay_api_url', 'https://engine.shurjopayment.com');
        $endpoint = rtrim($api_url, '/') . '/api/secret-pay';
        $response = wp_remote_post($endpoint, array(
            'body' => json_encode($payment_data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $payment_data['token']
            ),
            'timeout' => 30,
            'sslverify' => false
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        // Normalize various possible response shapes to ensure a checkout URL is available
        if (is_array($result)) {
            if (empty($result['checkout_url'])) {
                if (!empty($result['data']) && is_array($result['data']) && !empty($result['data']['checkout_url'])) {
                    $result['checkout_url'] = $result['data']['checkout_url'];
                } elseif (!empty($result['payment_url'])) {
                    $result['checkout_url'] = $result['payment_url'];
                } elseif (!empty($result['redirect_url'])) {
                    $result['checkout_url'] = $result['redirect_url'];
                } elseif (!empty($result['url'])) {
                    $result['checkout_url'] = $result['url'];
                }
            }

            // If still no checkout URL, return a WP_Error with any API-provided message for clearer debugging
            if (empty($result['checkout_url'])) {
                $msg = '';
                if (!empty($result['message'])) $msg = $result['message'];
                elseif (!empty($result['error'])) $msg = is_array($result['error']) ? json_encode($result['error']) : $result['error'];
                else $msg = 'No checkout URL returned by ShurjoPay API';
                return new WP_Error('shurjopay_no_checkout_url', $msg);
            }
        }

        return $result;
    }

    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '127.0.0.1';
    }
    
    public function handle_callback() {
        if (isset($_GET['orabooks_payment']) && $_GET['orabooks_payment'] === 'shurjopay_return') {
            $this->handle_shurjopay_return();
        }
        
        if (isset($_POST['order_id']) && isset($_POST['currency']) && isset($_POST['amount'])) {
            $this->handle_shurjopay_ipn();
        }
    }
    
    private function handle_shurjopay_return() {
        $order_id = isset($_GET['order_id']) ? sanitize_text_field(wp_unslash($_GET['order_id'])) : '';
        
        if (empty($order_id)) {
            wp_redirect(home_url('/orabooks-confirmation?payment=failed&reason=no_order_id'));
            exit;
        }
        
        if (function_exists('orabooks_handle_multisite_tables')) {
            orabooks_handle_multisite_tables();
        }

        global $wpdb;
        if (!isset($wpdb->orabooks_orders)) {
            wp_redirect(home_url('/orabooks-confirmation?payment=failed&reason=db_error'));
            exit;
        }

        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->orabooks_orders} WHERE order_id = %s",
            $order_id
        ));
        
        if (!$order) {
            $failure_page = orabooks_get_membership_option('orabooks_payment_failure_page');
            $redirect_url = $failure_page ? get_permalink($failure_page) : home_url('/orabooks-confirmation');
            wp_redirect(add_query_arg(array('payment' => 'failed', 'reason' => 'order_not_found'), $redirect_url));
            exit;
        }
        
        // Verification
        $username = orabooks_get_membership_option('orabooks_shurjopay_username', '');
        $password = orabooks_get_membership_option('orabooks_shurjopay_password', '');
        $api_url = orabooks_get_membership_option('orabooks_shurjopay_api_url', 'https://engine.shurjopayment.com');

        $auth = $this->get_shurjopay_token($username, $password);
        if ($auth && isset($auth['token'])) {
            $token = $auth['token'];
            $verify_endpoint = rtrim($api_url, '/') . '/api/verification';

            $response = wp_remote_post($verify_endpoint, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ),
                'body' => json_encode(array('order_id' => $order_id)),
                'timeout' => 30,
                'sslverify' => false
            ));

            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $verification = json_decode($body, true);

                $sp_code = null;
                // Handle different response structures
                if (is_array($verification)) {
                    if (isset($verification[0]['sp_code'])) {
                        $sp_code = $verification[0]['sp_code'];
                    } elseif (isset($verification['sp_code'])) {
                        $sp_code = $verification['sp_code'];
                    }
                }

                // Success check (handle both string and int types)
                if ((string)$sp_code === '000' || (string)$sp_code === '1000') {
                    $wpdb->update( $wpdb->orabooks_orders, array('status' => 'completed'), array('id' => $order->id) );
                    do_action('orabooks_order_completed', $order->id, $order);
                    
                    $success_page = orabooks_get_membership_option('orabooks_payment_success_page');
                    $redirect_url = $success_page ? get_permalink($success_page) : home_url('/orabooks-confirmation');
                    $redirect_url = add_query_arg(array('payment' => 'success', 'gateway' => 'shurjopay'), $redirect_url);
                    
                    // Apply redirection filter for site signup flow
                    $redirect_url = apply_filters('orabooks_payment_success_redirect', $redirect_url, $order->order_id, $order);
                    
                    wp_redirect($redirect_url);
                    exit;
                }
            }
        }
        
        $failure_page = orabooks_get_membership_option('orabooks_payment_failure_page');
        $redirect_url = $failure_page ? get_permalink($failure_page) : home_url('/orabooks-confirmation');
        wp_redirect(add_query_arg(array('payment' => 'failed', 'gateway' => 'shurjopay'), $redirect_url));
        exit;
    }
    
    private function handle_shurjopay_ipn() {
        $order_id = isset($_POST['order_id']) ? sanitize_text_field(wp_unslash($_POST['order_id'])) : '';
        $sp_code = isset($_POST['sp_code']) ? sanitize_text_field(wp_unslash($_POST['sp_code'])) : '';
        
        if (empty($order_id)) {
            echo 'OK';
            exit;
        }

        if (function_exists('orabooks_handle_multisite_tables')) {
            orabooks_handle_multisite_tables();
        }

        global $wpdb;
        if (!isset($wpdb->orabooks_orders)) {
            echo 'OK';
            exit;
        }

        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->orabooks_orders} WHERE order_id = %s",
            $order_id
        ));
        
        if ($order && ($sp_code == '000' || $sp_code == 1000)) {
            $wpdb->update($wpdb->orabooks_orders, array('status' => 'completed'), array('id' => $order->id));
            do_action('orabooks_order_completed', $order->id, $order);
        }
        echo 'OK';
        exit;
    }
    
    public function get_settings_fields() {
        return array(
            array(
                'id' => 'enabled',
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'description' => 'Enable ShurjoPay Payment Gateway',
                'default' => '1'
            ),
            array(
                'id' => 'title',
                'title' => 'Title',
                'type' => 'text',
                'description' => 'Checkout Title',
                'default' => 'ShurjoPay'
            ),
            array(
                'id' => 'description',
                'title' => 'Description',
                'type' => 'textarea',
                'description' => 'Checkout Description',
                'default' => 'Pay via ShurjoPay'
            ),
            array(
                'id' => 'api_url',
                'title' => 'API URL',
                'type' => 'text',
                'description' => 'ShurjoPay API endpoint',
                'default' => 'https://engine.shurjopayment.com'
            ),
            array(
                'id' => 'username',
                'title' => 'API Username',
                'type' => 'text',
                'default' => 'ExtremenestCorporation'
            ),
            array(
                'id' => 'password',
                'title' => 'API Password',
                'type' => 'password',
                'default' => 'extrmcgjts$z#phe'
            ),
            array(
                'id' => 'prefix',
                'title' => 'Order Prefix',
                'type' => 'text',
                'default' => 'ETC'
            ),
            array(
                'id' => 'test_mode',
                'title' => 'Test Mode',
                'type' => 'checkbox'
            )
        );
    }
}