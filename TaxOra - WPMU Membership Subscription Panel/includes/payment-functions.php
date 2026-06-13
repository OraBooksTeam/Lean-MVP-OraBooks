<?php
if (!defined('ABSPATH')) exit;

// Include all gateway files
function orabooks_load_payment_gateways() {
    $gateways = array(
        'SSLCommerz' => 'sslcommerz',
        'Stripe' => 'stripe',
        'PayPal' => 'paypal',
        'AmarPay' => 'amarpay',
        'SurePay' => 'surepay',
        'Bank' => 'bank',
        'ShurjoPay' => 'shurjopay'
    );
    
    foreach ($gateways as $class => $file) {
        $file_path = TAXORA_MEMBERSHIP_DIR . "includes/payment-gateways/class-{$file}-gateway.php";
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
}
// Removed duplicate loading - gateways are loaded in main plugin file
add_action('init', 'orabooks_load_payment_gateways');

// Initialize gateways
function orabooks_init_payment_gateways() {
    // Ensure gateways are loaded
    orabooks_load_payment_gateways();
    
    $gateways = array(
        'OraBooks_SSLCommerz_Gateway',
        'OraBooks_Stripe_Gateway', 
        'OraBooks_PayPal_Gateway',
        'OraBooks_AmarPay_Gateway',
        'OraBooks_SurePay_Gateway',
        'OraBooks_Bank_Gateway',
        'OraBooks_ShurjoPay_Gateway'
    );
    
    $available_gateways = array();
    
    foreach ($gateways as $gateway_class) {
        if (class_exists($gateway_class)) {
            try {
                $gateway = new $gateway_class();
                $gateway_id = $gateway->get_id();
                $available = true;
                
                if (method_exists($gateway, 'is_available')) {
                    try {
                        $available = (bool) $gateway->is_available();
                    } catch (Exception $e) {
                        $available = false;
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('OraBooks Gateway is_available error for ' . $gateway_class . ': ' . $e->getMessage());
                        }
                    }
                }

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('OraBooks Gateway Debug: ' . $gateway_class . ' (ID: ' . $gateway_id . ') - Available: ' . ($available ? 'true' : 'false'));
                }

                // Always register ShurjoPay when enabled (matches checkout UI).
                if ($available || ($gateway_id === 'shurjopay' && orabooks_get_membership_option('orabooks_shurjopay_enabled', 1))) {
                    $available_gateways[$gateway_id] = $gateway;
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('OraBooks Gateway instantiation error for ' . $gateway_class . ': ' . $e->getMessage());
                }
            }
        }
    }
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('OraBooks Available Gateways: ' . print_r(array_keys($available_gateways), true));
    }
    
    return $available_gateways;
}

// Handle payment callbacks - ADD SHURJOPAY
add_action('template_redirect', 'orabooks_handle_payment_callbacks');
function orabooks_handle_payment_callbacks() {
    if (isset($_GET['orabooks_payment'])) {
        $gateways = orabooks_init_payment_gateways();
        $payment_type = sanitize_text_field($_GET['orabooks_payment']);
        
        foreach ($gateways as $gateway) {
            if (strpos($payment_type, $gateway->get_id()) !== false) {
                $gateway->handle_callback();
                break;
            }
        }
    }
}

// Payment AJAX is handled by OraBooks_Payment_Processor in payment-processing.php

// Add payment settings to admin
add_action('orabooks_admin_settings_tabs', 'orabooks_add_payment_settings_tab');
function orabooks_add_payment_settings_tab($current_tab) {
    ?>
    <a href="#payments" class="nav-tab <?php echo $current_tab === 'payments' ? 'nav-tab-active' : ''; ?>">
        Payment Gateways
    </a>
    <?php
}

add_action('orabooks_admin_settings_content', 'orabooks_add_payment_settings_content');
function orabooks_add_payment_settings_content($current_tab) {
    if ($current_tab !== 'payments') return;
    
    $gateways = orabooks_init_payment_gateways();
    ?>
    <div class="orabooks-payment-gateways">
        <h2>Payment Gateway Settings</h2>
        
        <?php foreach ($gateways as $gateway): ?>
        <div class="gateway-settings">
            <h3><?php echo esc_html( $gateway->get_title() ); ?></h3>
            <?php $gateway->admin_options(); ?>
        </div>
        <hr>
        <?php endforeach; ?>
        
        <p class="submit">
            <button type="submit" name="orabooks_save_payment_settings" class="button button-primary">
                Save Payment Settings
            </button>
        </p>
    </div>
    <?php
}

// Handle payment settings save
add_action('admin_init', 'orabooks_save_payment_settings');
function orabooks_save_payment_settings() {
    if (isset($_POST['orabooks_save_payment_settings'])) {
        $gateways = orabooks_init_payment_gateways();
        
        if (!empty($gateways)) {
            foreach ($gateways as $gateway) {
                $fields = $gateway->get_settings_fields();
                foreach ($fields as $field) {
                    $option_name = 'orabooks_gateway_' . $gateway->get_id() . '_' . $field['id'];
                    $value = isset($_POST[$option_name]) ? sanitize_text_field($_POST[$option_name]) : '';
                    update_option($option_name, $value);
                }
            }
        }
        
        echo '<div class="notice notice-success"><p>Payment settings saved successfully!</p></div>';
    }
}