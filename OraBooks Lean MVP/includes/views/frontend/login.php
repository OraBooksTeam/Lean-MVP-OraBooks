<div class="orabooks-auth-shell">
    <div class="orabooks-form-container">
        <h2><?php esc_html_e('Log In', 'orabooks'); ?></h2>
        <p><?php esc_html_e('Welcome back to OraBooks.', 'orabooks'); ?></p>
        <form id="orabooks-login-form" class="orabooks-form">
            <div class="orabooks-form-group">
                <label for="login-email"><?php esc_html_e('Email', 'orabooks'); ?></label>
                <input type="email" id="login-email" required autocomplete="email">
            </div>
            <div class="orabooks-form-group">
                <label for="login-password"><?php esc_html_e('Password', 'orabooks'); ?></label>
                <input type="password" id="login-password" required autocomplete="current-password">
            </div>
            <div class="orabooks-form-actions">
                <button type="submit" class="orabooks-btn orabooks-btn-primary"><?php esc_html_e('Log In', 'orabooks'); ?></button>
            </div>
        </form>
        <div id="orabooks-login-message" class="orabooks-message"></div>
        <p class="orabooks-auth-divider"><?php esc_html_e('or', 'orabooks'); ?></p>
        <button type="button" class="orabooks-btn orabooks-btn-google orabooks-btn-secondary"><?php esc_html_e('Sign in with Google', 'orabooks'); ?></button>
        <p class="orabooks-auth-links">
            <a href="<?php echo esc_url(home_url('/reset-password/')); ?>"><?php esc_html_e('Forgot password?', 'orabooks'); ?></a>
            &nbsp;|&nbsp;
            <a href="<?php echo esc_url(home_url('/register/')); ?>"><?php esc_html_e('Create account', 'orabooks'); ?></a>
        </p>
    </div>
</div>
