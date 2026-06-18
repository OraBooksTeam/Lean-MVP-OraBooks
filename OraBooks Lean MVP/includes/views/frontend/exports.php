<div class="orabooks-export-status">
    <h2><?php esc_html_e('My Exports', 'orabooks'); ?></h2>
    <div class="orabooks-commission-stats">
        <div class="orabooks-stat-card"><h3><?php esc_html_e('Total', 'orabooks'); ?></h3><p id="orabooks-export-total">0</p></div>
        <div class="orabooks-stat-card"><h3><?php esc_html_e('Pending', 'orabooks'); ?></h3><p id="orabooks-export-pending">0</p></div>
        <div class="orabooks-stat-card"><h3><?php esc_html_e('Ready', 'orabooks'); ?></h3><p id="orabooks-export-ready">0</p></div>
    </div>
    <button type="button" id="orabooks-export-refresh" class="orabooks-btn orabooks-btn-secondary orabooks-btn-sm"><?php esc_html_e('Refresh', 'orabooks'); ?></button>
    <table class="orabooks-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Type', 'orabooks'); ?></th>
                <th><?php esc_html_e('Format', 'orabooks'); ?></th>
                <th><?php esc_html_e('Status', 'orabooks'); ?></th>
                <th><?php esc_html_e('Size', 'orabooks'); ?></th>
                <th><?php esc_html_e('Expires', 'orabooks'); ?></th>
                <th><?php esc_html_e('Downloads', 'orabooks'); ?></th>
                <th><?php esc_html_e('Actions', 'orabooks'); ?></th>
            </tr>
        </thead>
        <tbody id="orabooks-export-table-body">
            <tr><td colspan="7"><?php esc_html_e('Loading...', 'orabooks'); ?></td></tr>
        </tbody>
    </table>
    <div id="orabooks-export-pagination"></div>
</div>
