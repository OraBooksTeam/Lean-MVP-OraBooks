<?php
/**
 * Wizard Step 1: General Information
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$site_name = get_option( 'orabooks_site_name', get_bloginfo( 'name' ) );
$site_description = get_option( 'orabooks_site_description', '' );
$collect_payments = get_option( 'orabooks_collect_payments', false );
$subscriber_type = get_option( 'orabooks_subscriber_type', 'individual' ); // Default to individual

// Check if pages already exist
$pages_exist = false;
$page_slugs = array( 'orabooks-pricing', 'orabooks-my-account', 'login', 'orabooks-register' );
foreach ( $page_slugs as $slug ) {
    if ( get_page_by_path( $slug ) ) {
        $pages_exist = true;
        break;
    }
}
?>
<div class="orabooks-wizard__step orabooks-wizard__step-1">
    <div class="orabooks-wizard__step-header">
        <h2><?php esc_html_e( 'Welcome to Orabooks Membership', 'orabooks-membership' ); ?></h2>
        <p><?php esc_html_e( 'Tell us about your membership site to get up and running in 5 easy steps.', 'orabooks-membership' ); ?></p>
    </div>
    <form action="" method="post">
        <div class="orabooks-wizard__field">
            <label class="orabooks-wizard__label-block" for="site_name">
                <?php esc_html_e( 'Site Name', 'orabooks-membership' ); ?>
            </label>
            <p class="orabooks-wizard__field-description">
                <?php esc_html_e( 'Enter a name for your membership site.', 'orabooks-membership' ); ?>
            </p>
            <input type="text" id="site_name" name="site_name" class="orabooks-wizard__field-block" value="<?php echo esc_attr( $site_name ); ?>" required>
        </div>
        
        <div class="orabooks-wizard__field">
            <label class="orabooks-wizard__label-block" for="site_description">
                <?php esc_html_e( 'Site Description', 'orabooks-membership' ); ?>
            </label>
            <p class="orabooks-wizard__field-description">
                <?php esc_html_e( 'Briefly describe your membership site.', 'orabooks-membership' ); ?>
            </p>
            <textarea id="site_description" name="site_description" class="orabooks-wizard__field-block" rows="3"><?php echo esc_textarea( $site_description ); ?></textarea>
        </div>
        
        <div class="orabooks-wizard__field">
            <label class="orabooks-wizard__label-block">
                <?php esc_html_e( 'Subscriber Type', 'orabooks-membership' ); ?>
            </label>
            <p class="orabooks-wizard__field-description">
                <?php esc_html_e( 'Select the type of subscribers for this site. This determines their access level to Orabooks plugins.', 'orabooks-membership' ); ?>
            </p>
            
            <div class="orabooks-subscriber-type-options" style="margin-top: 15px;">
                <label class="orabooks-subscriber-type-option" style="display: block; padding: 15px; margin-bottom: 10px; border: 2px solid #ddd; border-radius: 5px; cursor: pointer; transition: all 0.3s;">
                    <input type="radio" name="subscriber_type" value="agent" <?php checked( $subscriber_type, 'agent' ); ?> style="margin-right: 10px;">
                    <strong style="font-size: 16px;"><?php esc_html_e( 'Agent', 'orabooks-membership' ); ?></strong>
                    <p style="margin: 5px 0 0 25px; color: #666;">
                        <?php esc_html_e( 'Subscribers will have full access to all Orabooks plugins and features from the admin panel.', 'orabooks-membership' ); ?>
                    </p>
                </label>
                
                <label class="orabooks-subscriber-type-option" style="display: block; padding: 15px; margin-bottom: 10px; border: 2px solid #ddd; border-radius: 5px; cursor: pointer; transition: all 0.3s;">
                    <input type="radio" name="subscriber_type" value="individual" <?php checked( $subscriber_type, 'individual' ); ?> style="margin-right: 10px;">
                    <strong style="font-size: 16px;"><?php esc_html_e( 'Individual', 'orabooks-membership' ); ?></strong>
                    <p style="margin: 5px 0 0 25px; color: #666;">
                        <?php esc_html_e( 'Subscribers will have basic subscriber role only, with no plugin access from the admin panel.', 'orabooks-membership' ); ?>
                    </p>
                </label>
            </div>
        </div>
        
        <div class="orabooks-wizard__field">
            <label class="orabooks-wizard__label-block" for="create_pages">
                <input type="checkbox" name="create_pages" id="create_pages" value="1" <?php checked( ! $pages_exist ); ?> <?php disabled( $pages_exist ); ?>>
                <?php esc_html_e( 'Yes, generate the required plugin pages for me. (Recommended)', 'orabooks-membership' ); ?>
            </label>
            <?php if ( $pages_exist ) : ?>
                <p class="orabooks-wizard__field-description">
                    <?php esc_html_e( 'We detected you have pages assigned for Orabooks Membership, this option is disabled.', 'orabooks-membership' ); ?>
                </p>
            <?php else : ?>
                <p class="orabooks-wizard__field-description">
                    <?php esc_html_e( 'We will automatically create frontend pages for your levels, account management, login, and more.', 'orabooks-membership' ); ?>
                </p>
            <?php endif; ?>
        </div>
        
        <div class="orabooks-wizard__field">
            <label class="orabooks-wizard__label-block" for="collect_payments">
                <input type="checkbox" name="collect_payments" id="collect_payments" value="1" <?php checked( $collect_payments ); ?>>
                <?php esc_html_e( 'Yes, I will be collecting payments for my memberships.', 'orabooks-membership' ); ?>
            </label>
        </div>
        
        <p class="orabooks_wizard__submit">
            <?php wp_nonce_field( 'orabooks_wizard_step_1_nonce', 'orabooks_wizard_step_1_nonce' ); ?>
            <input type="hidden" name="wizard-action" value="step-1"/>
            <input type="submit" name="submit" class="button button-primary button-hero" value="<?php esc_attr_e( 'Submit and Continue', 'orabooks-membership' ); ?>" /><br/>
            <a class="orabooks_wizard__skip" href="<?php echo esc_url( admin_url( 'admin.php?page=orabooks-wizard&step=payments' ) ); ?>">
                <?php esc_html_e( 'Skip', 'orabooks-membership' ); ?>
            </a>
        </p>
    </form>
</div>
