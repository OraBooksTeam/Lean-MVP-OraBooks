<?php
/**
 * Orabooks Membership Setup Wizard
 * Main wizard template file
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get current step
if ( empty( $_REQUEST['step'] ) ) {
    $previous_step = get_option( 'orabooks_wizard_step' );
    if ( ! empty( $previous_step ) ) {
        $active_step = sanitize_text_field( $previous_step );
    } else {
        $active_step = 'general';
    }
} elseif ( ! empty( $_REQUEST['step'] ) ) {
    $active_step = sanitize_text_field( $_REQUEST['step'] );
} else {
    $active_step = 'general';
}

?>
<div class="orabooks-wizard">
    <div class="orabooks-wizard__background"></div>
    <div class="orabooks-wizard__header">
        <h1>
            <a class="orabooks_logo" href="<?php echo esc_url( home_url() ); ?>">
                <?php if ( file_exists( TAXORA_MEMBERSHIP_DIR . 'images/logo.png' ) ) : ?>
                    <img src="<?php echo esc_url( TAXORA_MEMBERSHIP_URL . 'images/logo.png' ); ?>" alt="<?php esc_attr_e( 'Orabooks Membership', 'orabooks-membership' ); ?>" />
                <?php else : ?>
                    <span><?php esc_html_e( 'Orabooks Membership', 'orabooks-membership' ); ?></span>
                <?php endif; ?>
            </a>
        </h1>
        <nav class="orabooks-stepper">
            <ul class="orabooks-stepper__steps">
                <?php
                $setup_steps = array(
                    'general' => __( 'General Info', 'orabooks-membership' ),
                    'payments' => __( 'Payments', 'orabooks-membership' ),
                    'groups' => __( 'Groups', 'orabooks-membership' ),
                    'levels' => __( 'Levels', 'orabooks-membership' ),
                    'done' => __( 'All Set!', 'orabooks-membership' ),
                );

                $count = 0;
                foreach ( $setup_steps as $setup_step => $name ) {
                    $classes = array( 'orabooks-stepper__step' );
                    if ( $setup_step === $active_step ) {
                        $classes[] = 'is-active';
                    }
                    $class = implode( ' ', array_unique( $classes ) );
                    $count++;
                    ?>
                    <li class="<?php echo esc_attr( $class ); ?>">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=orabooks-wizard&step=' . $setup_step ) ); ?>">
                            <div class="orabooks-stepper__step-icon">
                                <span class="orabooks-stepper__step-number">
                                    <span class="screen-reader-text"><?php esc_html_e( 'Step', 'orabooks-membership' ); ?></span>
                                    <?php echo esc_html( $count ); ?>
                                </span>
                            </div>
                            <span class="orabooks-stepper__step-label"<?php echo ( in_array( 'is-active', $classes ) ) ? ' aria-label="' . sprintf( esc_html__( '%s Active Step', 'orabooks-membership' ), esc_html( $name ) ) . '"' : ''; ?>>
                                <?php echo esc_html( $name ); ?>
                            </span>
                        </a>
                    </li>
                    <?php
                }
                ?>
            </ul>
            <div class="orabooks-stepper__step-divider"></div>
        </nav>
    </div>

    <div class="orabooks-wizard__container">
        <?php
        // Load the wizard step template
        $step_file = TAXORA_MEMBERSHIP_DIR . 'wizard/' . $active_step . '.php';
        if ( file_exists( $step_file ) && isset( $setup_steps[ $active_step ] ) ) {
            include $step_file;
        } else {
            include TAXORA_MEMBERSHIP_DIR . 'wizard/general.php';
        }
        ?>
        <?php if ( $active_step !== 'done' ) : ?>
            <p class="orabooks-wizard__exit">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=orabooks-membership' ) ); ?>">
                    <?php esc_html_e( 'Exit Wizard and Return to Dashboard', 'orabooks-membership' ); ?>
                </a>
            </p>
        <?php endif; ?>
    </div>
</div>
