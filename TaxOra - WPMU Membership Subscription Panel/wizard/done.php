<?php
/**
 * Wizard Step 5: Setup Complete
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Mark wizard as completed when this page loads
if ( ! get_option( 'orabooks_wizard_completed', false ) ) {
    update_option( 'orabooks_wizard_completed', true );
    update_option( 'orabooks_wizard_step', 'done' );
}
?>
<div class="orabooks-wizard__step orabooks-wizard__step-5">
    <div class="orabooks-wizard__step-header">
        <h2><?php esc_html_e( 'Setup Complete', 'orabooks-membership' ); ?></h2>
        <p>
            <strong><?php esc_html_e( 'Congratulations!', 'orabooks-membership' ); ?></strong>
            <?php esc_html_e( 'Your membership site is ready.', 'orabooks-membership' ); ?>
        </p>
    </div>
    
    <div class="orabooks-wizard__field">
        <h3 class="orabooks-wizard__section-title"><?php esc_html_e( "What's next?", 'orabooks-membership' ); ?></h3>
        
        <div class="orabooks-wizard__col">
            <p>
                <span class="orabooks-wizard__subtitle"><?php esc_html_e( 'Manage Memberships', 'orabooks-membership' ); ?></span><br>
                <?php esc_html_e( 'Create and manage membership levels, members, and subscriptions.', 'orabooks-membership' ); ?>
            </p>
        </div>
        <div class="orabooks-wizard__col">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=orabooks-membership-levels' ) ); ?>" class="button button-primary button-hero">
                <?php esc_html_e( 'Manage Levels', 'orabooks-membership' ); ?>
            </a>
        </div>

        <div class="orabooks-wizard__col">
            <p>
                <span class="orabooks-wizard__subtitle"><?php esc_html_e( 'Payment Settings', 'orabooks-membership' ); ?></span><br>
                <?php esc_html_e( 'Finish configuring your payment gateway settings.', 'orabooks-membership' ); ?>
            </p>
        </div>
        <div class="orabooks-wizard__col">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=orabooks-membership-settings' ) ); ?>" class="button button-hero">
                <?php esc_html_e( 'View Settings', 'orabooks-membership' ); ?>
            </a>
        </div>

        <div class="orabooks-wizard__col">
            <p>
                <span class="orabooks-wizard__subtitle"><?php esc_html_e( 'Dashboard', 'orabooks-membership' ); ?></span><br>
                <?php esc_html_e( 'View your membership dashboard and statistics.', 'orabooks-membership' ); ?>
            </p>
        </div>
        <div class="orabooks-wizard__col">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=orabooks-membership' ) ); ?>" class="button button-hero">
                <?php esc_html_e( 'Go to Dashboard', 'orabooks-membership' ); ?>
            </a>
        </div>
    </div>
</div>
