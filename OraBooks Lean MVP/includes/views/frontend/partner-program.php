<div class="orabooks-partner-dashboard">
    <h2><?php esc_html_e('Partner Program', 'orabooks'); ?></h2>
    <div class="orabooks-form-group">
        <label for="orabooks-dash-partner-code"><?php esc_html_e('Partner Code', 'orabooks'); ?></label>
        <input type="text" id="orabooks-dash-partner-code" readonly>
        <button type="button" id="orabooks-dash-copy-code" class="orabooks-btn orabooks-btn-secondary orabooks-btn-sm"><?php esc_html_e('Copy', 'orabooks'); ?></button>
    </div>
    <div id="orabooks-partner-type-display"></div>
    <div id="orabooks-status-banners"></div>
    <div class="orabooks-commission-stats">
        <div class="orabooks-stat-card"><h3><?php esc_html_e('Attributions', 'orabooks'); ?></h3><p id="orabooks-attr-total">0</p></div>
        <div class="orabooks-stat-card"><h3><?php esc_html_e('Verified', 'orabooks'); ?></h3><p id="orabooks-attr-verified">0</p></div>
        <div class="orabooks-stat-card"><h3><?php esc_html_e('Pending', 'orabooks'); ?></h3><p id="orabooks-attr-pending">0</p></div>
        <div class="orabooks-stat-card"><h3><?php esc_html_e('Earned', 'orabooks'); ?></h3><p id="orabooks-comm-earned">$0.00</p></div>
        <div class="orabooks-stat-card"><h3><?php esc_html_e('Pending Payout', 'orabooks'); ?></h3><p id="orabooks-comm-pending">$0.00</p></div>
        <div class="orabooks-stat-card"><h3><?php esc_html_e('Paid', 'orabooks'); ?></h3><p id="orabooks-comm-paid">$0.00</p></div>
        <div class="orabooks-stat-card"><h3><?php esc_html_e('Expired', 'orabooks'); ?></h3><p id="orabooks-comm-expired">$0.00</p></div>
    </div>
    <h3><?php esc_html_e('Attributions', 'orabooks'); ?></h3>
    <table class="orabooks-table">
        <thead><tr><th><?php esc_html_e('Customer', 'orabooks'); ?></th><th><?php esc_html_e('Status', 'orabooks'); ?></th><th><?php esc_html_e('Date', 'orabooks'); ?></th></tr></thead>
        <tbody id="orabooks-attr-table-body"><tr><td colspan="3"><?php esc_html_e('Loading...', 'orabooks'); ?></td></tr></tbody>
    </table>
    <h3><?php esc_html_e('Payout Breakdown', 'orabooks'); ?></h3>
    <table class="orabooks-table">
        <thead><tr><th><?php esc_html_e('Gross', 'orabooks'); ?></th><th><?php esc_html_e('Fee', 'orabooks'); ?></th><th><?php esc_html_e('Net', 'orabooks'); ?></th></tr></thead>
        <tbody id="orabooks-payout-breakdown-body"><tr><td colspan="3"><?php esc_html_e('Loading...', 'orabooks'); ?></td></tr></tbody>
    </table>
    <div id="orabooks-partner-dashboard-message" class="orabooks-message"></div>
    <button type="button" id="orabooks-show-reactivation" class="orabooks-btn orabooks-btn-secondary"><?php esc_html_e('Request Reactivation', 'orabooks'); ?></button>
    <div id="orabooks-reactivation-modal" class="orabooks-modal" style="display:none;">
        <div class="orabooks-modal-content">
            <h3><?php esc_html_e('Request Reactivation', 'orabooks'); ?></h3>
            <textarea id="orabooks-reactivation-reason" rows="4" placeholder="<?php esc_attr_e('Reason for reactivation...', 'orabooks'); ?>"></textarea>
            <button type="button" id="orabooks-submit-reactivation" class="orabooks-btn orabooks-btn-primary"><?php esc_html_e('Submit', 'orabooks'); ?></button>
            <button type="button" class="orabooks-modal-close orabooks-btn orabooks-btn-secondary"><?php esc_html_e('Close', 'orabooks'); ?></button>
            <div id="orabooks-reactivation-message" class="orabooks-message"></div>
        </div>
    </div>
</div>
