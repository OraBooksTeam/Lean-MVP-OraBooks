<div class="wrap orabooks-admin">
    <h1><?php _e('Organizations', 'orabooks'); ?></h1>
    <div class="orabooks-filters">
        <select id="org-filter-type">
            <option value=""><?php _e('All Types', 'orabooks'); ?></option>
            <option value="customer"><?php _e('Customer', 'orabooks'); ?></option>
            <option value="partner"><?php _e('Partner', 'orabooks'); ?></option>
        </select>
        <select id="org-filter-status">
            <option value=""><?php _e('All Statuses', 'orabooks'); ?></option>
            <option value="active"><?php _e('Active', 'orabooks'); ?></option>
            <option value="pending_setup"><?php _e('Pending Setup', 'orabooks'); ?></option>
            <option value="suspended"><?php _e('Suspended', 'orabooks'); ?></option>
        </select>
        <button class="button" onclick="orabooksLoadOrgs()"><?php _e('Refresh', 'orabooks'); ?></button>
    </div>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('ID', 'orabooks'); ?></th>
                <th><?php _e('Name', 'orabooks'); ?></th>
                <th><?php _e('Subdomain', 'orabooks'); ?></th>
                <th><?php _e('Type', 'orabooks'); ?></th>
                <th><?php _e('Tier', 'orabooks'); ?></th>
                <th><?php _e('Region', 'orabooks'); ?></th>
                <th><?php _e('Status', 'orabooks'); ?></th>
                <th><?php _e('Created', 'orabooks'); ?></th>
                <th><?php _e('Actions', 'orabooks'); ?></th>
            </tr>
        </thead>
        <tbody id="orabooks-orgs-table-body">
            <tr><td colspan="9"><?php _e('Loading...', 'orabooks'); ?></td></tr>
        </tbody>
    </table>
</div>