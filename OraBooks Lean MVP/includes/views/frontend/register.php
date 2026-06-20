<div class="orabooks-auth-shell">
    <div class="orabooks-form-container">
        <h2><?php esc_html_e('Create Account', 'orabooks'); ?></h2>
        <p><?php esc_html_e('Start your OraBooks journey.', 'orabooks'); ?></p>
        <form id="orabooks-register-form" class="orabooks-form">
            <div class="orabooks-form-group">
                <label for="reg-email"><?php esc_html_e('Email', 'orabooks'); ?></label>
                <input type="email" id="reg-email" required autocomplete="email">
            </div>
            <div class="orabooks-form-group">
                <label for="reg-password"><?php esc_html_e('Password', 'orabooks'); ?></label>
                <input type="password" id="reg-password" required autocomplete="new-password" minlength="8">
                <small><?php esc_html_e('Min 8 characters with mixed case, number, and special character.', 'orabooks'); ?></small>
            </div>
            <div class="orabooks-form-group">
                <label for="reg-confirm-password"><?php esc_html_e('Confirm Password', 'orabooks'); ?></label>
                <input type="password" id="reg-confirm-password" required autocomplete="new-password">
            </div>
            <div class="orabooks-form-group">
                <label for="reg-user-type"><?php esc_html_e('I am a', 'orabooks'); ?></label>
                <select id="reg-user-type">
                    <option value="customer"><?php esc_html_e('Customer', 'orabooks'); ?></option>
                    <option value="partner"><?php esc_html_e('Partner', 'orabooks'); ?></option>
                </select>
            </div>
            <div class="orabooks-form-group orabooks-partner-only" style="display:none;">
                <label for="reg-partner-type"><?php esc_html_e('Partner Type', 'orabooks'); ?></label>
                <select id="reg-partner-type">
                    <option value="individual"><?php esc_html_e('Individual', 'orabooks'); ?></option>
                    <option value="agency"><?php esc_html_e('Agency', 'orabooks'); ?></option>
                    <option value="reseller"><?php esc_html_e('Reseller', 'orabooks'); ?></option>
                    <option value="strategic_partner"><?php esc_html_e('Strategic Partner', 'orabooks'); ?></option>
                </select>
            </div>
            <div class="orabooks-form-group orabooks-partner-org orabooks-partner-only" style="display:none;">
                <label for="reg-org-name"><?php esc_html_e('Organization Name', 'orabooks'); ?></label>
                <input type="text" id="reg-org-name" autocomplete="organization">
            </div>
            <div class="orabooks-form-group orabooks-customer-only">
                <label for="reg-partner-code"><?php esc_html_e('Partner Code (optional)', 'orabooks'); ?></label>
                <input type="text" id="reg-partner-code" autocomplete="off">
            </div>
            <div class="orabooks-form-group">
                <label>
                    <input type="checkbox" id="reg-accept-terms" value="1">
                    <?php esc_html_e('I accept the terms and conditions', 'orabooks'); ?>
                </label>
            </div>
            <div class="orabooks-form-actions">
                <button type="submit" class="orabooks-btn orabooks-btn-primary"><?php esc_html_e('Create Account', 'orabooks'); ?></button>
            </div>
        </form>
        <div id="orabooks-register-message" class="orabooks-message"></div>
        <p class="orabooks-auth-links">
            <a href="<?php echo esc_url(orabooks_get_network_login_url('login')); ?>"><?php esc_html_e('Already have an account? Log in', 'orabooks'); ?></a>
        </p>
    </div>
</div>
