<div class="wrap orabooks-admin">
    <h1><?php _e('Users & Teams', 'orabooks'); ?></h1>
    
    <div class="orabooks-coa-export-actions" style="margin-bottom:16px;padding:12px 16px;background:#f0f6fc;border:1px solid #c5d9ed;border-radius:4px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <span style="font-weight:600;color:#1d2327;">📊 <?php _e('Export:', 'orabooks'); ?></span>
        <button class="button button-primary orabooks-users-export-trigger" data-export-type="users_data" data-format="csv"><?php _e('Export CSV (Async)', 'orabooks'); ?></button>
        <button class="button orabooks-users-export-trigger" data-export-type="users_data" data-format="pdf"><?php _e('Export PDF (Async)', 'orabooks'); ?></button>
        <span style="color:#666;font-size:12px;">📁 <?php _e('Async — you\'ll get a notification when ready.', 'orabooks'); ?></span>
    </div>
    <div id="orabooks-users-export-msg" class="orabooks-message" style="display:none;"></div>

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