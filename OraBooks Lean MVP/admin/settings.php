<div class="wrap orabooks-admin">
    <h1><?php _e('OraBooks Settings', 'orabooks'); ?></h1>
    <form method="post" action="options.php">
        <?php settings_fields('orabooks_settings'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Block Same Email Domain', 'orabooks'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="orabooks_block_same_email_domain" value="1" <?php checked(get_option('orabooks_block_same_email_domain', 0)); ?>>
                        <?php _e('Block partner attribution when customer email domain matches partner email domain', 'orabooks'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Staff/Viewer Commission Access', 'orabooks'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="orabooks_partner_commission_for_staff_viewer" value="1" <?php checked(get_option('orabooks_partner_commission_for_staff_viewer', 0)); ?>>
                        <?php _e('Allow Staff and Viewer roles to access partner commission dashboard', 'orabooks'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Audit Log Retention', 'orabooks'); ?></th>
                <td>
                    <input type="number" name="orabooks_audit_retention_days" value="<?php echo esc_attr(get_option('orabooks_audit_retention_days', 365)); ?>" min="30" max="3650">
                    <p class="description"><?php _e('Number of days to retain audit logs before archival', 'orabooks'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('JWT Expiry', 'orabooks'); ?></th>
                <td>
                    <input type="number" name="orabooks_jwt_expiry" value="<?php echo esc_attr(get_option('orabooks_jwt_expiry', 900)); ?>" min="60" max="86400">
                    <p class="description"><?php _e('JWT token expiry in seconds (default: 900 = 15 min)', 'orabooks'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Refresh Token Expiry', 'orabooks'); ?></th>
                <td>
                    <input type="number" name="orabooks_refresh_token_expiry" value="<?php echo esc_attr(get_option('orabooks_refresh_token_expiry', 604800)); ?>" min="3600" max="2592000">
                    <p class="description"><?php _e('Refresh token expiry in seconds (default: 604800 = 7 days)', 'orabooks'); ?></p>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>