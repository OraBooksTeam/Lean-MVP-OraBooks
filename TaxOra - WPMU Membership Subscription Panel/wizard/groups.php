<?php
/**
 * Wizard Step 3: Membership Groups
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="orabooks-wizard__step orabooks-wizard__step-3">
    <div class="orabooks-wizard__step-header">
        <h2><?php esc_html_e( 'Membership Groups', 'orabooks-membership' ); ?></h2>
        <p><?php esc_html_e( 'Create your first membership group. Groups help you organize different types of membership levels.', 'orabooks-membership' ); ?></p>
    </div>
    <form action="" method="post">
        <div class="orabooks-wizard__field">
            <label for="group_name" class="orabooks-wizard__label-block">
                <?php esc_html_e( 'Group Name', 'orabooks-membership' ); ?>
            </label>
            <input type="text" name="group_name" id="group_name" class="orabooks-wizard__field-block" placeholder="<?php esc_attr_e( 'e.g., General Plans', 'orabooks-membership' ); ?>" required />
            <p class="description"><?php esc_html_e( 'A name for this group of membership levels.', 'orabooks-membership' ); ?></p>
        </div>
        
        <div class="orabooks-wizard__field">
            <label for="group_description" class="orabooks-wizard__label-block">
                <?php esc_html_e( 'Group Description (Optional)', 'orabooks-membership' ); ?>
            </label>
            <textarea name="group_description" id="group_description" class="orabooks-wizard__field-block" rows="3"></textarea>
        </div>

        <p class="orabooks_wizard__submit">
            <?php wp_nonce_field( 'orabooks_wizard_step_3_nonce', 'orabooks_wizard_step_3_nonce' ); ?>
            <input type="hidden" name="wizard-action" value="step-3"/>
            <input type="submit" name="submit" class="button button-primary button-hero" value="<?php esc_attr_e( 'Create Group and Continue', 'orabooks-membership' ); ?>" /><br/>
            <a class="orabooks_wizard__skip" href="<?php echo esc_url( admin_url( 'admin.php?page=orabooks-wizard&step=levels' ) ); ?>">
                <?php esc_html_e( 'Skip', 'orabooks-membership' ); ?>
            </a>
        </p>
    </form>
</div>
