<div class="wrap orabooks-admin">
    <h1><?php _e('Audit Log', 'orabooks'); ?></h1>
    <div class="orabooks-filters">
        <select id="audit-filter-event">
            <option value=""><?php _e('All Events', 'orabooks'); ?></option>
            <option value="login_success"><?php _e('Login', 'orabooks'); ?></option>
            <option value="user_registered"><?php _e('Registration', 'orabooks'); ?></option>
            <option value="org_created"><?php _e('Org Created', 'orabooks'); ?></option>
            <option value="partner_attribution_verified"><?php _e('Attribution', 'orabooks'); ?></option>
        </select>
        <input type="text" id="audit-filter-user" placeholder="<?php esc_attr_e('User ID', 'orabooks'); ?>">
        <input type="date" id="audit-filter-from">
        <input type="date" id="audit-filter-to">
        <button class="button" onclick="orabooksLoadAuditLogs()"><?php _e('Filter', 'orabooks'); ?></button>
        <button class="button" onclick="orabooksExportAuditLogs()"><?php _e('Export CSV (Direct)', 'orabooks'); ?></button>
        <button class="button button-primary orabooks-export-trigger" data-export-type="audit_log" data-format="csv"><?php _e('Export CSV (Async)', 'orabooks'); ?></button>
        <button class="button orabooks-export-trigger" data-export-type="audit_log" data-format="pdf"><?php _e('Export PDF (Async)', 'orabooks'); ?></button>
    </div>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Timestamp', 'orabooks'); ?></th>
                <th><?php _e('User', 'orabooks'); ?></th>
                <th><?php _e('Event', 'orabooks'); ?></th>
                <th><?php _e('Severity', 'orabooks'); ?></th>
                <th><?php _e('Description', 'orabooks'); ?></th>
                <th><?php _e('IP', 'orabooks'); ?></th>
                <th><?php _e('Correlation ID', 'orabooks'); ?></th>
            </tr>
        </thead>
        <tbody id="orabooks-audit-table-body">
            <tr><td colspan="7"><?php _e('Loading...', 'orabooks'); ?></td></tr>
        </tbody>
    </table>
</div>