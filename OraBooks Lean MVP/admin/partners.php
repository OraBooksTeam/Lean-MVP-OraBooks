<div class="wrap orabooks-admin">
    <h1><?php _e('Partner Management', 'orabooks'); ?></h1>
    
    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper">
        <a href="#tab-pending" class="nav-tab nav-tab-active" data-tab="pending">
            <?php _e('Pending Approvals', 'orabooks'); ?>
            <span id="orabooks-pending-count" class="awaiting-mod"></span>
        </a>
        <a href="#tab-reactivation" class="nav-tab" data-tab="reactivation">
            <?php _e('Reactivation Requests', 'orabooks'); ?>
            <span id="orabooks-reactivation-count" class="awaiting-mod"></span>
        </a>
        <a href="#tab-active" class="nav-tab" data-tab="active">
            <?php _e('Active Partners', 'orabooks'); ?>
        </a>
    </nav>
    
    <!-- Tab: Pending Approvals -->
    <div id="orabooks-tab-pending" class="orabooks-admin-tab-content orabooks-admin-tab-active">
        <p class="description"><?php _e('Review pending partner code requests. Approving will activate the partner code and organization.', 'orabooks'); ?></p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('ID', 'orabooks'); ?></th>
                    <th><?php _e('Partner Code', 'orabooks'); ?></th>
                    <th><?php _e('Email', 'orabooks'); ?></th>
                    <th><?php _e('Partner Type', 'orabooks'); ?></th>
                    <th><?php _e('Organization', 'orabooks'); ?></th>
                    <th><?php _e('Org Status', 'orabooks'); ?></th>
                    <th><?php _e('Requested', 'orabooks'); ?></th>
                    <th><?php _e('Actions', 'orabooks'); ?></th>
                </tr>
            </thead>
            <tbody id="orabooks-pending-partners-body">
                <tr><td colspan="8"><?php _e('Loading...', 'orabooks'); ?></td></tr>
            </tbody>
        </table>
    </div>
    
    <!-- Tab: Reactivation Requests -->
    <div id="orabooks-tab-reactivation" class="orabooks-admin-tab-content" style="display:none;">
        <p class="description"><?php _e('Review partner reactivation requests. Approving will reactivate the partner code and organization.', 'orabooks'); ?></p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('ID', 'orabooks'); ?></th>
                    <th><?php _e('Partner Code', 'orabooks'); ?></th>
                    <th><?php _e('Requested By', 'orabooks'); ?></th>
                    <th><?php _e('Organization', 'orabooks'); ?></th>
                    <th><?php _e('Reason', 'orabooks'); ?></th>
                    <th><?php _e('Requested', 'orabooks'); ?></th>
                    <th><?php _e('Actions', 'orabooks'); ?></th>
                </tr>
            </thead>
            <tbody id="orabooks-reactivation-partners-body">
                <tr><td colspan="7"><?php _e('Loading...', 'orabooks'); ?></td></tr>
            </tbody>
        </table>
    </div>
    
    <!-- Tab: Active Partners -->
    <div id="orabooks-tab-active" class="orabooks-admin-tab-content" style="display:none;">
        <p class="description"><?php _e('View all active partners and their statistics.', 'orabooks'); ?></p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('ID', 'orabooks'); ?></th>
                    <th><?php _e('Partner Code', 'orabooks'); ?></th>
                    <th><?php _e('Email', 'orabooks'); ?></th>
                    <th><?php _e('Type', 'orabooks'); ?></th>
                    <th><?php _e('Organization', 'orabooks'); ?></th>
                    <th><?php _e('Verified Attributions', 'orabooks'); ?></th>
                    <th><?php _e('Approved', 'orabooks'); ?></th>
                    <th><?php _e('Last Attribution', 'orabooks'); ?></th>
                </tr>
            </thead>
            <tbody id="orabooks-active-partners-body">
                <tr><td colspan="8"><?php _e('Loading...', 'orabooks'); ?></td></tr>
            </tbody>
        </table>
    </div>
    
    <!-- Reject Modal -->
    <div id="orabooks-reject-modal" class="orabooks-modal" style="display:none;">
        <div class="orabooks-modal-content" style="background:#fff;padding:20px;max-width:400px;margin:100px auto;border:1px solid #ccd0d4;border-radius:4px;">
            <h3><?php _e('Reject Partner Code', 'orabooks'); ?></h3>
            <p><?php _e('Provide a reason for rejection:', 'orabooks'); ?></p>
            <textarea id="orabooks-reject-reason" rows="3" style="width:100%;" placeholder="<?php esc_attr_e('Reason for rejection...', 'orabooks'); ?>"></textarea>
            <p style="text-align:right;margin-top:12px;">
                <button id="orabooks-reject-cancel" class="button" style="margin-right:8px;"><?php _e('Cancel', 'orabooks'); ?></button>
                <button id="orabooks-reject-confirm" class="button button-primary"><?php _e('Confirm Reject', 'orabooks'); ?></button>
            </p>
            <div id="orabooks-reject-message" class="orabooks-message"></div>
        </div>
    </div>
</div>
