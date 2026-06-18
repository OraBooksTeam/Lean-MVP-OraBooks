<div class="orabooks-notification-center">
    <h2><?php esc_html_e('Notifications', 'orabooks'); ?></h2>
    <div id="orabooks-nc-unread-badge" class="orabooks-info-banner" style="display:none;"></div>
    <div class="orabooks-filters">
        <select id="orabooks-nc-filter-event">
            <option value=""><?php esc_html_e('All events', 'orabooks'); ?></option>
            <option value="security_alert"><?php esc_html_e('Security', 'orabooks'); ?></option>
            <option value="export_ready"><?php esc_html_e('Exports', 'orabooks'); ?></option>
        </select>
        <select id="orabooks-nc-filter-priority">
            <option value=""><?php esc_html_e('All priorities', 'orabooks'); ?></option>
            <option value="low"><?php esc_html_e('Low', 'orabooks'); ?></option>
            <option value="normal"><?php esc_html_e('Normal', 'orabooks'); ?></option>
            <option value="high"><?php esc_html_e('High', 'orabooks'); ?></option>
            <option value="critical"><?php esc_html_e('Critical', 'orabooks'); ?></option>
        </select>
        <select id="orabooks-nc-filter-status">
            <option value=""><?php esc_html_e('All statuses', 'orabooks'); ?></option>
            <option value="unread"><?php esc_html_e('Unread', 'orabooks'); ?></option>
            <option value="read"><?php esc_html_e('Read', 'orabooks'); ?></option>
        </select>
        <button type="button" id="orabooks-nc-filter-apply" class="orabooks-btn orabooks-btn-secondary orabooks-btn-sm"><?php esc_html_e('Apply', 'orabooks'); ?></button>
        <button type="button" id="orabooks-nc-mark-all-read" class="orabooks-btn orabooks-btn-secondary orabooks-btn-sm"><?php esc_html_e('Mark all read', 'orabooks'); ?></button>
        <button type="button" class="orabooks-btn orabooks-btn-secondary orabooks-btn-sm orabooks-notif-export-trigger" data-export-type="notification_log" data-format="csv"><?php esc_html_e('Export CSV', 'orabooks'); ?></button>
    </div>
    <div id="orabooks-notif-export-msg" class="orabooks-message" style="display:none;"></div>
    <div id="orabooks-nc-list"><p class="orabooks-loading"><?php esc_html_e('Loading notifications...', 'orabooks'); ?></p></div>
    <div id="orabooks-nc-proof-modal" class="orabooks-modal" style="display:none;">
        <div class="orabooks-modal-content">
            <pre id="orabooks-nc-proof-content"></pre>
            <button type="button" class="orabooks-modal-close orabooks-btn orabooks-btn-secondary"><?php esc_html_e('Close', 'orabooks'); ?></button>
        </div>
    </div>
</div>
