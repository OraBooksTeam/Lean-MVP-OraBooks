<div class="orabooks-auth-shell">
    <div class="orabooks-form-container">
        <h2><?php esc_html_e('Choose Your Plan', 'orabooks'); ?></h2>
        <p><?php esc_html_e('Select a tier and choose your organization subdomain.', 'orabooks'); ?></p>
        <div class="orabooks-form-group">
            <label for="tier-subdomain"><?php esc_html_e('Subdomain', 'orabooks'); ?></label>
            <input type="text" id="tier-subdomain" placeholder="mycompany" autocomplete="off">
            <button type="button" id="orabooks-check-subdomain" class="orabooks-btn orabooks-btn-secondary orabooks-btn-sm"><?php esc_html_e('Check availability', 'orabooks'); ?></button>
            <div id="orabooks-subdomain-status" class="orabooks-message"></div>
        </div>
        <form id="orabooks-tier-form" class="orabooks-form">
            <div class="orabooks-form-group">
                <label><input type="radio" name="tier" value="free" checked> <?php esc_html_e('Free', 'orabooks'); ?></label>
            </div>
            <div class="orabooks-form-group">
                <label><input type="radio" name="tier" value="premium"> <?php esc_html_e('Premium', 'orabooks'); ?></label>
            </div>
            <div class="orabooks-form-group">
                <label><input type="radio" name="tier" value="enterprise"> <?php esc_html_e('Enterprise', 'orabooks'); ?></label>
            </div>
            <div class="orabooks-form-group" id="orabooks-tier-region-group" style="display:none;">
                <label for="tier-region"><?php esc_html_e('Data Residency Region', 'orabooks'); ?></label>
                <select id="tier-region" name="region">
                    <option value=""><?php esc_html_e('Select a region', 'orabooks'); ?></option>
                    <option value="us-east"><?php esc_html_e('US East', 'orabooks'); ?></option>
                    <option value="eu-west-1"><?php esc_html_e('EU West', 'orabooks'); ?></option>
                    <option value="ap-southeast-1"><?php esc_html_e('Asia Pacific', 'orabooks'); ?></option>
                </select>
            </div>
            <div id="orabooks-tier-message" class="orabooks-message"></div>
            <div class="orabooks-form-actions">
                <button type="submit" class="orabooks-btn orabooks-btn-primary"><?php esc_html_e('Continue', 'orabooks'); ?></button>
            </div>
        </form>
    </div>
</div>
