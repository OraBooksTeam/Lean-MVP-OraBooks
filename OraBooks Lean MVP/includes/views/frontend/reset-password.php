<?php
$token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
?>
<div class="orabooks-auth-shell">
    <div class="orabooks-form-container">
        <h2><?php esc_html_e('Reset Password', 'orabooks'); ?></h2>
        <p><?php esc_html_e('Choose a new password for your account.', 'orabooks'); ?></p>
        <form id="orabooks-reset-password-form" class="orabooks-form">
            <input type="hidden" id="reset-token" value="<?php echo esc_attr($token); ?>">
            <div class="orabooks-form-group">
                <label for="reset-password"><?php esc_html_e('New Password', 'orabooks'); ?></label>
                <input type="password" id="reset-password" required autocomplete="new-password" minlength="8">
            </div>
            <div class="orabooks-form-group">
                <label for="reset-confirm-password"><?php esc_html_e('Confirm Password', 'orabooks'); ?></label>
                <input type="password" id="reset-confirm-password" required autocomplete="new-password" minlength="8">
            </div>
            <div class="orabooks-form-actions">
                <button type="submit" class="orabooks-btn orabooks-btn-primary"><?php esc_html_e('Reset Password', 'orabooks'); ?></button>
            </div>
        </form>
        <div id="orabooks-reset-password-message" class="orabooks-message"></div>
        <p class="orabooks-auth-links">
            <a href="<?php echo esc_url(orabooks_get_network_login_url('login')); ?>"><?php esc_html_e('Back to login', 'orabooks'); ?></a>
        </p>
    </div>
</div>
