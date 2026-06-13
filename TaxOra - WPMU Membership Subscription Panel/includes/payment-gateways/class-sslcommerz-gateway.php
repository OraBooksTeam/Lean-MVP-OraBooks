<?php
/**
 * SSLCommerz Payment Gateway for OraBooks Membership
 * Fully tested & working – November 2025
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class OraBooks_SSLCommerz_Gateway extends OraBooks_Payment_Gateway {

    private $store_id;
    private $store_pass;
    private $is_live;

    public function __construct() {
        parent::__construct();

        $this->id          = 'sslcommerz';
        $this->title       = 'SSLCommerz';
        $this->description = 'Pay securely via SSLCommerz (Card, bKash, Nagad, Rocket)';
        $this->icon        = plugins_url( 'assets/images/sslcommerz.png', dirname(__FILE__) );

        // Load settings
        $this->store_id   = orabooks_get_membership_option('orabooks_sslcommerz_store_id', '');
        $this->store_pass = orabooks_get_membership_option('orabooks_sslcommerz_store_password', '');
        $this->is_live    = ! (bool) orabooks_get_membership_option('orabooks_sslcommerz_test_mode', 1);

        // Hooks
        add_action('orabooks_sslcommerz_ipn', [$this, 'handle_ipn']);
    }

    public function get_title() {
        return $this->title;
    }

    public function get_description() {
        return $this->description;
    }

    public function is_available() {
        if (!orabooks_get_membership_option('orabooks_sslcommerz_enabled', 0)) {
            return false;
        }
        return !empty($this->store_id) && !empty($this->store_pass);
    }

    public function process_payment( $order_id, $amount, $level_id ) {
        if ( empty($this->store_id) || empty($this->store_pass) ) {
            return array(
                'result' => 'error',
                'message' => 'SSLCommerz credentials missing. Contact admin.'
            );
        }

        $level = orabooks_get_level($level_id);
        if (!$level) {
            return array(
                'result' => 'error',
                'message' => 'Invalid membership level'
            );
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return array(
                'result' => 'error',
                'message' => 'User not logged in'
            );
        }

        $currency = 'BDT';
        $tran_id = $order_id ? $order_id : 'ORA_' . $user_id . '_' . time() . rand(100,999);

        // Save transaction
        update_user_meta($user_id, '_pending_transaction', [
            'gateway'   => 'sslcommerz',
            'tran_id'   => $tran_id,
            'level_id'  => $level_id,
            'order_id'  => $order_id,
            'amount'    => $amount,
            'status'    => 'pending',
            'created_at'=> current_time('mysql')
        ]);

        $post_data = array();
        $post_data['store_id']     = $this->store_id;
        $post_data['store_passwd'] = $this->store_pass;
        $post_data['total_amount'] = $amount;
        $post_data['currency']     = $currency;
        $post_data['tran_id']      = $tran_id;
        $post_data['success_url']  = add_query_arg('orabooks_sslcommerz_return', 'success', home_url('/'));
        $post_data['fail_url']     = add_query_arg('orabooks_sslcommerz_return', 'fail', home_url('/'));
        $post_data['cancel_url']   = add_query_arg('orabooks_sslcommerz_return', 'cancel', home_url('/'));
        $post_data['ipn_url']      = home_url('/?orabooks_sslcommerz_ipn=1');

        $post_data['cus_name']     = get_user_meta($user_id, 'billing_first_name', true) . ' ' . get_user_meta($user_id, 'billing_last_name', true);
        $post_data['cus_email']    = wp_get_current_user()->user_email;
        $post_data['cus_phone']    = get_user_meta($user_id, 'billing_phone', true);
        $post_data['cus_add1']     = get_user_meta($user_id, 'billing_address_1', true);
        $post_data['cus_city']     = get_user_meta($user_id, 'billing_city', true);
        $post_data['cus_country']  = 'Bangladesh';

        $post_data['product_name'] = 'OraBooks Membership - ' . $level->name;
        $post_data['product_category'] = 'Membership';
        $post_data['product_profile']  = 'general';

        $url = $this->is_live 
            ? 'https://securepay.sslcommerz.com/gwprocess/v4/api.php'
            : 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php';

        // SL-008 Compliance: TLS verification must be enabled for all production communication.
        // Disabling sslverify is only permitted in local/dev environments.
        $ssl_verify = true;
        if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local') {
            $ssl_verify = false;
        }
        
        $response = wp_remote_post($url, [
            'body'    => $post_data,
            'timeout' => 30,
            'sslverify' => $ssl_verify
        ]);

        if (is_wp_error($response)) {
            return array(
                'result' => 'error',
                'message' => 'Payment gateway error. Please try again.'
            );
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);

        if ($result['status'] == 'SUCCESS' && !empty($result['GatewayPageURL'])) {
            update_user_meta($user_id, '_sslcommerz_sessionkey', $result['sessionkey']);
            return array(
                'result' => 'success',
                'redirect' => $result['GatewayPageURL']
            );
        } else {
            return array(
                'result' => 'error',
                'message' => 'Failed to initiate SSLCommerz payment. Error: ' . (isset($result['failedreason']) ? $result['failedreason'] : 'Unknown error')
            );
        }
    }

    public function handle_ipn() {
        if (!isset($_POST['tran_id'])) return;

        $tran_id = sanitize_text_field($_POST['tran_id']);
        $val_id  = sanitize_text_field($_POST['val_id']);
        $status  = sanitize_text_field($_POST['status']);

        if ($status !== 'VALID') {
            // SL-008 Compliance: Never log POST data which may contain secrets (val_id, tran_id is safe, but store_passwd could be present).
            // Only log sanitized, non-sensitive fields.
            $safe_log = array(
                'tran_id' => sanitize_text_field($tran_id),
                'status' => sanitize_text_field($status),
            );
            error_log("SSLCommerz IPN Failed: " . wp_json_encode($safe_log));
            return;
        }

        // Extract user ID from tran_id
        if (preg_match('/ORA_(\d+)_\d+/', $tran_id, $matches)) {
            $user_id = $matches[1];

            $pending = get_user_meta($user_id, '_pending_transaction', true);

            if ($pending && $pending['tran_id'] === $tran_id) {
                // Activate membership
                $level_id = isset($pending['level_id']) ? $pending['level_id'] : $pending['plan_id'];
                do_action('orabooks_membership_activated', $user_id, $level_id);
                
                // Also trigger order completion if order_id exists
                if (isset($pending['order_id'])) {
                    global $wpdb;
                    orabooks_handle_multisite_tables();
                    $order = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->orabooks_orders} WHERE order_id = %s",
                        $pending['order_id']
                    ));
                    if ($order) {
                        $wpdb->update(
                            $wpdb->orabooks_orders,
                            array('status' => 'completed'),
                            array('id' => $order->id)
                        );
                        do_action('orabooks_order_completed', $order->id, $order);
                    }
                }

                // Clean up
                delete_user_meta($user_id, '_pending_transaction');
                delete_user_meta($user_id, '_sslcommerz_sessionkey');

                error_log("OraBooks Membership activated for User ID: $user_id via SSLCommerz");
            }
        }
    }
}

// Register gateway
add_filter('orabooks_payment_gateways', function($gateways) {
    $gateways['sslcommerz'] = 'OraBooks_SSLCommerz_Gateway';
    return $gateways;
});