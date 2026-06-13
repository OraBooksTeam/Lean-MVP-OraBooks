<?php
/**
 * Wizard Step 2: Payment Settings
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure payment gateways are loaded
if ( function_exists( 'orabooks_load_payment_gateways' ) ) {
    orabooks_load_payment_gateways();
}

$payment_gateway = get_option( 'orabooks_payment_gateway', '' );
$collect_payments = get_option( 'orabooks_collect_payments', false );

// Get available payment gateways dynamically
$available_gateways = array();
if ( function_exists( 'orabooks_init_payment_gateways' ) ) {
    $gateways = orabooks_init_payment_gateways();
    if ( ! empty( $gateways ) && is_array( $gateways ) ) {
        foreach ( $gateways as $gateway_id => $gateway ) {
            if ( is_object( $gateway ) && method_exists( $gateway, 'get_title' ) ) {
                $available_gateways[ $gateway_id ] = array(
                    'title' => $gateway->get_title(),
                    'description' => method_exists( $gateway, 'get_description' ) ? $gateway->get_description() : ''
                );
            }
        }
    }
}

// Fallback to default gateways if none are loaded
if ( empty( $available_gateways ) ) {
    $available_gateways = array(
        'sslcommerz' => array(
            'title' => 'SSLCommerz',
            'description' => __( 'Popular payment gateway in Bangladesh.', 'orabooks-membership' )
        ),
        'shurjopay' => array(
            'title' => 'ShurjoPay',
            'description' => __( 'Another popular payment gateway option.', 'orabooks-membership' )
        ),
        'stripe' => array(
            'title' => 'Stripe',
            'description' => __( 'International payment gateway.', 'orabooks-membership' )
        ),
        'paypal' => array(
            'title' => 'PayPal',
            'description' => __( 'International payment gateway.', 'orabooks-membership' )
        )
    );
}

// Add "Other/Setup Later" option
$available_gateways['other'] = array(
    'title' => __( 'Other/Setup Later', 'orabooks-membership' ),
    'description' => ''
);
?>
<div class="orabooks-wizard__step orabooks-wizard__step-2">
    <div class="orabooks-wizard__step-header">
        <h2><?php esc_html_e( 'Payment Settings', 'orabooks-membership' ); ?></h2>
        <p><?php esc_html_e( 'Configure your payment gateway settings.', 'orabooks-membership' ); ?></p>
    </div>
    
    <?php if ( ! $collect_payments ) : ?>
        <div class="orabooks-wizard__field orabooks-wizard__field-alt" style="background: #fff3cd; border-left-color: #ffc107;">
            <p>
                <strong><?php esc_html_e( 'Note:', 'orabooks-membership' ); ?></strong>
                <?php esc_html_e( 'You indicated you will not be collecting payments. You can still configure payment gateways now or skip this step.', 'orabooks-membership' ); ?>
            </p>
        </div>
    <?php endif; ?>
    
    <form action="" method="post">
        <div class="orabooks-wizard__field">
            <label class="orabooks-wizard__label-block">
                <?php esc_html_e( 'Currency', 'orabooks-membership' ); ?>
            </label>
            <p class="orabooks-wizard__field-description">
                <?php esc_html_e( 'All transactions use Bangladeshi Taka (BDT)', 'orabooks-membership' ); ?>
            </p>
            <p><strong>Bangladeshi Taka (BDT)</strong></p>
        </div>
        
        <fieldset>
            <legend><?php esc_html_e( 'Configure Your Payment Gateway', 'orabooks-membership' ); ?></legend>
            <?php if ( ! empty( $available_gateways ) ) : ?>
                <?php foreach ( $available_gateways as $gateway_id => $gateway_info ) : ?>
                    <div class="orabooks-wizard__field">
                        <label for="<?php echo esc_attr( $gateway_id ); ?>" class="orabooks-wizard__label-block" style="display: flex; align-items: flex-start; padding: 12px; border: 1px solid #e0e0e0; border-radius: 4px; margin-bottom: 10px; cursor: pointer; transition: all 0.2s ease;">
                            <input type="radio" name="payment_gateway" id="<?php echo esc_attr( $gateway_id ); ?>" value="<?php echo esc_attr( $gateway_id ); ?>" style="margin-right: 10px; margin-top: 2px; flex-shrink: 0;" <?php checked( $gateway_id === $payment_gateway || ( empty( $payment_gateway ) && $gateway_id === 'sslcommerz' ) ); ?>>
                            <div style="flex: 1;">
                                <strong><?php echo esc_html( $gateway_info['title'] ); ?></strong>
                                <?php if ( ! empty( $gateway_info['description'] ) ) : ?>
                                    <p class="orabooks-wizard__field-description" style="margin: 5px 0 0 0;">
                                        <?php echo esc_html( $gateway_info['description'] ); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </label>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="orabooks-wizard__field">
                    <p class="orabooks-wizard__field-description" style="color: #d63638;">
                        <?php esc_html_e( 'No payment gateways are currently available. Please ensure payment gateway files are properly loaded.', 'orabooks-membership' ); ?>
                    </p>
                </div>
            <?php endif; ?>
        </fieldset>

        <div class="orabooks-wizard__field orabooks-wizard__field-alt">
            <p>
                <?php if ( file_exists( TAXORA_MEMBERSHIP_DIR . 'images/logo.png' ) ) : ?>
                    <img src="<?php echo esc_url( TAXORA_MEMBERSHIP_URL . 'images/logo.png' ); ?>" style="width: 24px; height: 24px; vertical-align: middle;" />
                <?php endif; ?>
                <?php esc_html_e( 'Payment gateways may be configured under Settings > Payment Gateways.', 'orabooks-membership' ); ?>
            </p>
        </div>
        
        <p class="orabooks_wizard__submit">
            <?php wp_nonce_field( 'orabooks_wizard_step_2_nonce', 'orabooks_wizard_step_2_nonce' ); ?>
            <input type="hidden" name="wizard-action" value="step-2"/>
            <input type="submit" name="submit" class="button button-primary button-hero" value="<?php esc_attr_e( 'Submit and Continue', 'orabooks-membership' ); ?>" /><br/>
            <a class="orabooks_wizard__skip" href="<?php echo esc_url( admin_url( 'admin.php?page=orabooks-wizard&step=groups' ) ); ?>">
                <?php esc_html_e( 'Skip', 'orabooks-membership' ); ?>
            </a>
        </p>
    </form>
</div>
