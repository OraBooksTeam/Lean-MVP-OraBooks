<?php
/**
 * Wizard Step 4: Membership Levels
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$collecting_payment = get_option( 'orabooks_collect_payments', false );
?>
<div class="orabooks-wizard__step orabooks-wizard__step-4">
    <div class="orabooks-wizard__step-header">
        <h2><?php esc_html_e( 'Membership Levels', 'orabooks-membership' ); ?></h2>
        <p><?php esc_html_e( 'Set up your first membership levels from this wizard. You can set up more membership levels with additional settings later.', 'orabooks-membership' ); ?></p>
    </div>
    <form action="" method="post">
        <fieldset>
            <legend class="screen-reader-text"><?php esc_html_e( 'Create Your Membership Levels', 'orabooks-membership' ); ?></legend>
            <div class="orabooks-wizard__field">
                <div class="orabooks-wizard__field__checkbox-group">
                    <input type="checkbox" id="orabooks-wizard__free-level" name="create_free_level" value="1">
                    <label for="orabooks-wizard__free-level" class="orabooks-wizard__label-block">
                        <?php esc_html_e( 'Create a Free Membership Level', 'orabooks-membership' ); ?>
                    </label>
                    <div class="orabooks-wizard__field__checkbox-content" style="display: none;">
                        <label for="free_level_name"><?php esc_html_e( 'Level Name', 'orabooks-membership' ); ?></label>
                        <input type="text" name="free_level_name" id="free_level_name" placeholder="<?php esc_attr_e( 'Free', 'orabooks-membership' ); ?>" />
                    </div>
                </div>
            </div>
            <script>
                jQuery(document).ready(function() {
                    jQuery('#orabooks-wizard__free-level').on('click', function() {
                        if (jQuery(this).prop('checked')) {
                            jQuery(this).parent().find('.orabooks-wizard__field__checkbox-content').show();
                        } else {
                            jQuery(this).parent().find('.orabooks-wizard__field__checkbox-content').hide();
                        }
                    });
                });
            </script>
            
            <?php if ( $collecting_payment ) : ?>
            <div class="orabooks-wizard__field">
                <div class="orabooks-wizard__field__checkbox-group">
                    <input type="checkbox" id="orabooks-wizard__paid-level" name="create_paid_level" value="1">
                    <label for="orabooks-wizard__paid-level" class="orabooks-wizard__label-block">
                        <?php esc_html_e( 'Create a Paid Membership Level', 'orabooks-membership' ); ?>
                    </label>
                    <div class="orabooks-wizard__field__checkbox-content" style="display: none;">
                        <div>
                            <label for="paid_level_name"><?php esc_html_e( 'Level Name', 'orabooks-membership' ); ?></label>
                            <input type="text" id="paid_level_name" name="paid_level_name" placeholder="<?php esc_attr_e( 'Premium', 'orabooks-membership' ); ?>"/>
                        </div>
                        <div>
                            <label for="paid_level_amount"><?php esc_html_e( 'Fee', 'orabooks-membership' ); ?></label>
                            <input type="text" id="paid_level_amount" name="paid_level_amount" placeholder="<?php esc_attr_e( 'Amount (i.e. "1000")', 'orabooks-membership' ); ?>" />
                            <?php esc_html_e( 'BDT every', 'orabooks-membership' ); ?>
                            <select id="billing_period" name="billing_period">
                                <option value="day"><?php esc_html_e( 'Day', 'orabooks-membership' ); ?></option>
                                <option value="week"><?php esc_html_e( 'Week', 'orabooks-membership' ); ?></option>
                                <option value="month" selected><?php esc_html_e( 'Month', 'orabooks-membership' ); ?></option>
                                <option value="year"><?php esc_html_e( 'Year', 'orabooks-membership' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                jQuery(document).ready(function() {
                    jQuery('#orabooks-wizard__paid-level').on('click', function() {
                        if (jQuery(this).prop('checked')) {
                            jQuery(this).parent().find('.orabooks-wizard__field__checkbox-content').show();
                        } else {
                            jQuery(this).parent().find('.orabooks-wizard__field__checkbox-content').hide();
                        }
                    });
                });
            </script>
            <?php endif; ?>
        </fieldset>
        
        <div class="orabooks-wizard__field orabooks-wizard__field-alt">
            <p>
                <?php if ( file_exists( TAXORA_MEMBERSHIP_DIR . 'images/logo.png' ) ) : ?>
                    <img src="<?php echo esc_url( TAXORA_MEMBERSHIP_URL . 'images/logo.png' ); ?>" style="width: 24px; height: 24px; vertical-align: middle;" />
                <?php endif; ?>
                <?php esc_html_e( 'Content restrictions are configured after initial setup.', 'orabooks-membership' ); ?>
            </p>
        </div>
        
        <p class="orabooks_wizard__submit">
            <?php wp_nonce_field( 'orabooks_wizard_step_4_nonce', 'orabooks_wizard_step_4_nonce' ); ?>
            <input type="hidden" name="wizard-action" value="step-4"/>
            <input type="submit" name="submit" class="button button-primary button-hero" value="<?php esc_attr_e( 'Submit and Continue', 'orabooks-membership' ); ?>" /><br/>
            <a class="orabooks_wizard__skip" href="<?php echo esc_url( admin_url( 'admin.php?page=orabooks-wizard&step=done' ) ); ?>">
                <?php esc_html_e( 'Skip', 'orabooks-membership' ); ?>
            </a>
        </p>
    </form>
</div>
