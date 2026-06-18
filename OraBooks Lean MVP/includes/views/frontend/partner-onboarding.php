<div class="orabooks-auth-shell" id="orabooks-partner-info">
    <div class="orabooks-form-container">
        <h2><?php esc_html_e('Partner Onboarding', 'orabooks'); ?></h2>
        <p><?php esc_html_e('Your partner code and onboarding status.', 'orabooks'); ?></p>
        <div class="orabooks-form-group">
            <label for="orabooks-partner-code"><?php esc_html_e('Partner Code', 'orabooks'); ?></label>
            <input type="text" id="orabooks-partner-code" readonly>
            <button type="button" id="orabooks-copy-code" class="orabooks-btn orabooks-btn-secondary orabooks-btn-sm"><?php esc_html_e('Copy Code', 'orabooks'); ?></button>
        </div>
        <div id="orabooks-partner-details"></div>
        <div id="orabooks-partner-status" class="orabooks-message"></div>
        <div class="orabooks-coa-export-actions" style="margin-top:16px;">
            <button class="orabooks-btn orabooks-btn-secondary orabooks-btn-sm orabooks-onboarding-export-trigger" data-export-type="partner_onboarding" data-format="csv"><?php esc_html_e('Export CSV', 'orabooks'); ?></button>
        </div>
        <div id="orabooks-onboarding-export-msg" class="orabooks-message" style="display:none;"></div>
    </div>
</div>
