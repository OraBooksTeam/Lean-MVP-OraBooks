<div class="wrap orabooks-admin">
    <h1><?php esc_html_e('OraBooks Dashboard', 'orabooks'); ?></h1>
    <p>
        <button type="button" id="orabooks-dash-refresh" class="button"><?php esc_html_e('Refresh', 'orabooks'); ?></button>
        <span id="orabooks-last-updated" style="margin-left:12px;color:#666;"></span>
    </p>
    <div id="orabooks-dash-loading"><?php esc_html_e('Loading dashboard...', 'orabooks'); ?></div>
    <div id="orabooks-dash-error" style="display:none;" class="notice notice-error"><p><?php esc_html_e('Unable to load dashboard stats.', 'orabooks'); ?></p></div>
    <div id="orabooks-dash-content" style="display:none;">
        <div class="orabooks-commission-stats">
            <div class="orabooks-stat-card">
                <h3><?php esc_html_e('Organizations', 'orabooks'); ?></h3>
                <p class="orabooks-stat-amount" id="orabooks-stat-orgs-total">0</p>
                <div id="orabooks-stat-orgs-breakdown"></div>
            </div>
            <div class="orabooks-stat-card">
                <h3><?php esc_html_e('Active Partners', 'orabooks'); ?></h3>
                <p class="orabooks-stat-amount" id="orabooks-stat-partners-active">0</p>
                <div id="orabooks-stat-partners-breakdown"></div>
            </div>
            <div class="orabooks-stat-card">
                <h3><?php esc_html_e('Users', 'orabooks'); ?></h3>
                <p class="orabooks-stat-amount" id="orabooks-stat-users-total">0</p>
                <div id="orabooks-stat-users-breakdown"></div>
            </div>
            <div class="orabooks-stat-card">
                <h3><?php esc_html_e('Verified Attributions', 'orabooks'); ?></h3>
                <p class="orabooks-stat-amount" id="orabooks-stat-attributions-verified">0</p>
                <div id="orabooks-stat-attributions-breakdown"></div>
            </div>
        </div>
        <div class="orabooks-section" style="margin-top:24px;">
            <h2><?php esc_html_e('Detail', 'orabooks'); ?></h2>
            <table class="widefat striped">
                <tbody>
                    <tr><th><?php esc_html_e('Customer orgs', 'orabooks'); ?></th><td id="orabooks-stat-orgs-customer">—</td><th><?php esc_html_e('Partner orgs', 'orabooks'); ?></th><td id="orabooks-stat-orgs-partner">—</td></tr>
                    <tr><th><?php esc_html_e('Active orgs', 'orabooks'); ?></th><td id="orabooks-stat-orgs-active">—</td><th><?php esc_html_e('Pending orgs', 'orabooks'); ?></th><td id="orabooks-stat-orgs-pending">—</td></tr>
                    <tr><th><?php esc_html_e('Suspended orgs', 'orabooks'); ?></th><td id="orabooks-stat-orgs-suspended">—</td><th><?php esc_html_e('Recent orgs (7d)', 'orabooks'); ?></th><td id="orabooks-stat-recent-orgs">—</td></tr>
                    <tr><th><?php esc_html_e('Verified users', 'orabooks'); ?></th><td id="orabooks-stat-users-verified">—</td><th><?php esc_html_e('2FA enabled', 'orabooks'); ?></th><td id="orabooks-stat-users-2fa">—</td></tr>
                    <tr><th><?php esc_html_e('Recent users (7d)', 'orabooks'); ?></th><td id="orabooks-stat-recent-users">—</td><th><?php esc_html_e('Recent attributions (7d)', 'orabooks'); ?></th><td id="orabooks-stat-recent-attributions">—</td></tr>
                </tbody>
            </table>
        </div>
        <span id="orabooks-qa-pending-partners" style="display:none;"></span>
        <span id="orabooks-qa-pending-orgs" style="display:none;"></span>
    </div>
</div>
