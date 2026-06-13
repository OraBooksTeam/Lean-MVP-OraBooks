<?php
if (!defined('ABSPATH')) exit;

class OraBooks_Stripe_Gateway extends OraBooks_Payment_Gateway {
    
    public function __construct() {
        parent::__construct();
        $this->id = 'stripe';
        $this->title = 'Stripe';
        $this->description = 'Pay via Stripe with Credit/Debit cards';
    }

    public function is_available() {
        if (!orabooks_get_membership_option('orabooks_stripe_enabled', 0)) {
            return false;
        }
        return !empty(orabooks_get_membership_option('orabooks_stripe_publishable_key', ''))
            && !empty(orabooks_get_membership_option('orabooks_stripe_secret_key', ''));
    }
    
    public function process_payment($order_id, $amount, $level_id) {
        // Stripe integration would go here
        // You'll need to implement Stripe PHP SDK
        
        return array(
            'result' => 'success',
            'redirect' => home_url('/?orabooks_payment=stripe&order=' . $order_id)
        );
    }
    
    public function handle_callback() {
        // Stripe webhook handling
    }
    
    public function get_settings_fields() {
        return array(
            array(
                'id' => 'enabled',
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'description' => 'Enable Stripe Payment Gateway'
            ),
            array(
                'id' => 'publishable_key',
                'title' => 'Publishable Key',
                'type' => 'text',
                'description' => 'Your Stripe Publishable Key'
            ),
            array(
                'id' => 'secret_key',
                'title' => 'Secret Key',
                'type' => 'password',
                'description' => 'Your Stripe Secret Key'
            ),
            array(
                'id' => 'test_mode',
                'title' => 'Test Mode',
                'type' => 'checkbox',
                'description' => 'Enable Stripe test mode'
            )
        );
    }
}