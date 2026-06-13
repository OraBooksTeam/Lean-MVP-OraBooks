<?php
if (!defined('ABSPATH')) exit;

class OraBooks_PayPal_Gateway extends OraBooks_Payment_Gateway {
    
    public function __construct() {
        parent::__construct();
        $this->id = 'paypal';
        $this->title = 'PayPal';
        $this->description = 'Pay via PayPal';
    }

    public function is_available() {
        if (!orabooks_get_membership_option('orabooks_paypal_enabled', 0)) {
            return false;
        }
        return !empty(orabooks_get_membership_option('orabooks_paypal_email', ''));
    }
    
    public function process_payment($order_id, $amount, $level_id) {
        global $wpdb;
        orabooks_handle_multisite_tables();
        
        $order_data = array(
            'order_id' => $order_id ? $order_id : 'PAYPAL' . time() . rand(1000, 9999),
            'user_id' => get_current_user_id(),
            'level_id' => $level_id,
            'gateway' => $this->id,
            'amount' => $amount,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert($wpdb->orabooks_orders, $order_data);
        
        $sandbox = (bool) orabooks_get_membership_option('orabooks_paypal_sandbox', 1);
        $paypal_url = $sandbox
            ? 'https://www.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://www.paypal.com/cgi-bin/webscr';
            
        $business_email = orabooks_get_membership_option('orabooks_paypal_email', '');
        if (empty($business_email)) {
            return array(
                'result' => 'error',
                'message' => 'PayPal is not properly configured'
            );
        }
        $currency = orabooks_get_membership_option('orabooks_payment_currency', 'BDT');
        
        $paypal_args = array(
            'cmd' => '_xclick',
            'business' => $business_email,
            'currency_code' => $currency,
            'amount' => $amount,
            'item_name' => 'OraBooks Membership',
            'item_number' => $level_id,
            'custom' => $order_data['order_id'],
            'return' => home_url('/?orabooks_payment=paypal_success'),
            'cancel_return' => home_url('/?orabooks_payment=paypal_cancelled'),
            'notify_url' => home_url('/?orabooks_payment=paypal_ipn'),
            'no_note' => '1',
            'no_shipping' => '1'
        );
        
        $redirect_url = $paypal_url . '?' . http_build_query($paypal_args);
        
        return array(
            'result' => 'success',
            'redirect' => $redirect_url,
            'redirect_url' => $redirect_url
        );
    }
    
    public function handle_callback() {
        if (isset($_GET['orabooks_payment']) && $_GET['orabooks_payment'] === 'paypal_ipn') {
            // Handle PayPal IPN
        }
    }
    
    public function get_settings_fields() {
        return array(
            array(
                'id' => 'enabled',
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'description' => 'Enable PayPal Payment Gateway'
            ),
            array(
                'id' => 'email',
                'title' => 'PayPal Email',
                'type' => 'email',
                'description' => 'Your PayPal business email'
            ),
            array(
                'id' => 'test_mode',
                'title' => 'Sandbox Mode',
                'type' => 'checkbox',
                'description' => 'Enable PayPal sandbox mode'
            )
        );
    }
}