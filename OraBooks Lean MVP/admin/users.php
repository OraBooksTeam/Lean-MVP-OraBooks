<div class="wrap orabooks-admin">
    <h1><?php _e('Users & Teams', 'orabooks'); ?></h1>
    <div class="orabooks-section">
        <h2><?php _e('Users', 'orabooks'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('ID', 'orabooks'); ?></th>
                    <th><?php _e('Email', 'orabooks'); ?></th>
                    <th><?php _e('Type', 'orabooks'); ?></th>
                    <th><?php _e('Verified', 'orabooks'); ?></th>
                    <th><?php _e('2FA', 'orabooks'); ?></th>
                    <th><?php _e('Org ID', 'orabooks'); ?></th>
                    <th><?php _e('Created', 'orabooks'); ?></th>
                </tr>
            </thead>
            <tbody id="orabooks-users-table-body">
                <tr><td colspan="7"><?php _e('Loading...', 'orabooks'); ?></td></tr>
            </tbody>
        </table>
    </div>
</div>