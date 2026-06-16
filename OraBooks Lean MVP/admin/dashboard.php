<div class="wrap orabooks-admin orabooks-dashboard">
    <h1><?php _e('OraBooks Dashboard', 'orabooks'); ?>
        <span class="orabooks-last-updated" id="orabooks-last-updated"></span>
        <button id="orabooks-dash-refresh" class="page-title-action"><?php _e('Refresh', 'orabooks'); ?></button>
    </h1>

    <!-- Loading skeleton -->
    <div id="orabooks-dash-loading" class="orabooks-dash-loading">
        <div class="orabooks-stats-grid">
            <div class="orabooks-skeleton-card"><div class="orabooks-skeleton-pulse orabooks-skeleton-h3"></div><div class="orabooks-skeleton-pulse orabooks-skeleton-number"></div><div class="orabooks-skeleton-pulse orabooks-skeleton-label"></div></div>
            <div class="orabooks-skeleton-card"><div class="orabooks-skeleton-pulse orabooks-skeleton-h3"></div><div class="orabooks-skeleton-pulse orabooks-skeleton-number"></div><div class="orabooks-skeleton-pulse orabooks-skeleton-label"></div></div>
            <div class="orabooks-skeleton-card"><div class="orabooks-skeleton-pulse orabooks-skeleton-h3"></div><div class="orabooks-skeleton-pulse orabooks-skeleton-number"></div><div class="orabooks-skeleton-pulse orabooks-skeleton-label"></div></div>
            <div class="orabooks-skeleton-card"><div class="orabooks-skeleton-pulse orabooks-skeleton-h3"></div><div class="orabooks-skeleton-pulse orabooks-skeleton-number"></div><div class="orabooks-skeleton-pulse orabooks-skeleton-label"></div></div>
        </div>
    </div>

    <!-- Error banner -->
    <div id="orabooks-dash-error" class="notice notice-error" style="display:none;">
        <p><?php _e('Unable to load dashboard data. Please try again.', 'orabooks'); ?></p>
    </div>

    <!-- Dashboard content (hidden until loaded) -->
    <div id="orabooks-dash-content" style="display:none;">

        <!-- ===================== Primary Stat Cards ===================== -->
        <div class="orabooks-stats-grid">

            <!-- Total Organizations -->
            <div class="orabooks-stat-card">
                <div class="orabooks-stat-icon orabooks-stat-icon-orgs">
                    <span class="dashicons dashicons-building"></span>
                </div>
                <div class="orabooks-stat-body">
                    <h3><?php _e('Organizations', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-number" id="orabooks-stat-orgs-total">—</p>
                    <div class="orabooks-stat-footer" id="orabooks-stat-orgs-breakdown"></div>
                </div>
            </div>

            <!-- Active Partners -->
            <div class="orabooks-stat-card">
                <div class="orabooks-stat-icon orabooks-stat-icon-partners">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="orabooks-stat-body">
                    <h3><?php _e('Active Partners', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-number" id="orabooks-stat-partners-active">—</p>
                    <div class="orabooks-stat-footer" id="orabooks-stat-partners-breakdown"></div>
                </div>
            </div>

            <!-- Total Users -->
            <div class="orabooks-stat-card">
                <div class="orabooks-stat-icon orabooks-stat-icon-users">
                    <span class="dashicons dashicons-admin-users"></span>
                </div>
                <div class="orabooks-stat-body">
                    <h3><?php _e('Total Users', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-number" id="orabooks-stat-users-total">—</p>
                    <div class="orabooks-stat-footer" id="orabooks-stat-users-breakdown"></div>
                </div>
            </div>

            <!-- Verified Attributions -->
            <div class="orabooks-stat-card">
                <div class="orabooks-stat-icon orabooks-stat-icon-attributions">
                    <span class="dashicons dashicons-networking"></span>
                </div>
                <div class="orabooks-stat-body">
                    <h3><?php _e('Verified Attributions', 'orabooks'); ?></h3>
                    <p class="orabooks-stat-number" id="orabooks-stat-attributions-verified">—</p>
                    <div class="orabooks-stat-footer" id="orabooks-stat-attributions-breakdown"></div>
                </div>
            </div>

        </div><!-- /orabooks-stats-grid -->

        <!-- ===================== Detail Panels Row ===================== -->
        <div class="orabooks-detail-row">

            <!-- Organizations Detail -->
            <div class="orabooks-detail-panel">
                <div class="orabooks-panel-header">
                    <span class="dashicons dashicons-building"></span>
                    <h3><?php _e('Organization Breakdown', 'orabooks'); ?></h3>
                </div>
                <div class="orabooks-panel-body">
                    <div class="orabooks-metric-row">
                        <span class="orabooks-metric-label"><?php _e('Customer Orgs', 'orabooks'); ?></span>
                        <span class="orabooks-metric-value" id="orabooks-stat-orgs-customer">—</span>
                    </div>
                    <div class="orabooks-metric-row">
                        <span class="orabooks-metric-label"><?php _e('Partner Orgs', 'orabooks'); ?></span>
                        <span class="orabooks-metric-value" id="orabooks-stat-orgs-partner">—</span>
                    </div>
                    <div class="orabooks-metric-row">
                        <span class="orabooks-metric-label orabooks-status-active"><?php _e('Active', 'orabooks'); ?></span>
                        <span class="orabooks-metric-value" id="orabooks-stat-orgs-active">—</span>
                    </div>
                    <div class="orabooks-metric-row">
                        <span class="orabooks-metric-label orabooks-status-pending"><?php _e('Pending Setup', 'orabooks'); ?></span>
                        <span class="orabooks-metric-value" id="orabooks-stat-orgs-pending">—</span>
                    </div>
                    <div class="orabooks-metric-row">
                        <span class="orabooks-metric-label orabooks-status-suspended"><?php _e('Suspended', 'orabooks'); ?></span>
                        <span class="orabooks-metric-value" id="orabooks-stat-orgs-suspended">—</span>
                    </div>
                </div>
                <div class="orabooks-panel-footer">
                    <a href="admin.php?page=orabooks-organizations" class="orabooks-panel-link"><?php _e('View All Organizations &rarr;', 'orabooks'); ?></a>
                </div>
            </div>

            <!-- Partner Codes Detail -->
            <div class="orabooks-detail-panel">
                <div class="orabooks-panel-header">
                    <span class="dashicons dashicons-groups"></span>
                    <h3><?php _e('Partner Codes Breakdown', 'orabooks'); ?></h3>
                </div>
                <div class="orabooks-panel-body">
                    <div class="orabooks-metric-row">
                        <span class="orabooks-metric-label orabooks-status-active"><?php _e('Active', 'orabooks'); ?></span>
                        <span class="orabooks-metric-value" id="orabooks-stat-partners-active-detail">—</span>
                    </div>
                    <div class="orabooks-metric-row">
                        <span class="orabooks-metric-label orabooks-status-pending"><?php _e('Pending Review', 'orabooks'); ?></span>
                        <span class="orabooks-metric-value" id="orabooks-stat-partners-pending">—</span>
                    </div>
                    <div class="orabooks-metric-row">
                        <span class="orabooks-metric-label orabooks-status-inactive"><?php _e('Inactive', 'orabooks'); ?></span>
                        <span class="orabooks-metric-value" id="orabooks-stat-partners-inactive">—</span>
                    </div>
                    <div class="orabooks-metric-row">
                        <span class="orabooks-metric-label orabooks-status-disabled"><?php _e('Disabled', 'orabooks'); ?></span>
                        <span class="orabooks-metric-value" id="orabooks-stat-partners-disabled">—</span>
                    </div>
                </div>
                <div class="orabooks-panel-footer">
                    <a href="admin.php?page=orabooks-partners" class="orabooks-panel-link"><?php _e('Manage Partners &rarr;', 'orabooks'); ?></a>
                </div>
            </div>

            <!-- User Stats Detail -->
            <div class="orabooks-detail-panel">
                <div class="orabooks-panel-header">
                    <span class="dashicons dashicons-admin-users"></span>
                    <h3><?php _e('User Stats', 'orabooks'); ?></h3>
                </div>
                <div class="orabooks-panel-body">
                    <div class="orabooks-metric-row">
                        <span class="orabooks-metric-label"><?php _e('Customer Users', 'orabooks'); ?></span>
                        <span class="orabooks-metric-value" id="orabooks-stat-users-customer">—</span>
                    </div>
                    <div class="orabooks-metric-row">
                        <span class="orabooks-metric-label"><?php _e('Partner Users', 'orabooks'); ?></span>
                        <span class="orabooks-metric-value" id="orabooks-stat-users-partner">—</span>
                    </div>
                    <div class="orabooks-metric-row">
                        <span class="orabooks-metric-label orabooks-status-active"><?php _e('Verified Email', 'orabooks'); ?></span>
                        <span class="orabooks-metric-value" id="orabooks-stat-users-verified">—</span>
                    </div>
                    <div class="orabooks-metric-row">
                        <span class="orabooks-metric-label orabooks-status-active"><?php _e('2FA Enabled', 'orabooks'); ?></span>
                        <span class="orabooks-metric-value" id="orabooks-stat-users-2fa">—</span>
                    </div>
                </div>
                <div class="orabooks-panel-footer">
                    <a href="admin.php?page=orabooks-users" class="orabooks-panel-link"><?php _e('View All Users &rarr;', 'orabooks'); ?></a>
                </div>
            </div>

            <!-- Attributions Detail -->
            <div class="orabooks-detail-panel">
                <div class="orabooks-panel-header">
                    <span class="dashicons dashicons-networking"></span>
                    <h3><?php _e('Attribution Stats', 'orabooks'); ?></h3>
                </div>
                <div class="orabooks-panel-body">
                    <div class="orabooks-metric-row">
                        <span class="orabooks-metric-label"><?php _e('Total', 'orabooks'); ?></span>
                        <span class="orabooks-metric-value" id="orabooks-stat-attributions-total">—</span>
                    </div>
                    <div class="orabooks-metric-row">
                        <span class="orabooks-metric-label orabooks-status-active"><?php _e('Verified', 'orabooks'); ?></span>
                        <span class="orabooks-metric-value" id="orabooks-stat-attributions-verified-detail">—</span>
                    </div>
                    <div class="orabooks-metric-row">
                        <span class="orabooks-metric-label orabooks-status-pending"><?php _e('Pending', 'orabooks'); ?></span>
                        <span class="orabooks-metric-value" id="orabooks-stat-attributions-pending">—</span>
                    </div>
                    <div class="orabooks-metric-row">
                        <span class="orabooks-metric-label orabooks-status-disabled"><?php _e('Blocked', 'orabooks'); ?></span>
                        <span class="orabooks-metric-value" id="orabooks-stat-attributions-blocked">—</span>
                    </div>
                </div>
                <div class="orabooks-panel-footer">
                    <a href="admin.php?page=orabooks-partners" class="orabooks-panel-link"><?php _e('View Attributions &rarr;', 'orabooks'); ?></a>
                </div>
            </div>

        </div><!-- /orabooks-detail-row -->

        <!-- ===================== Recent Activity & Quick Actions ===================== -->
        <div class="orabooks-bottom-row">

            <!-- Recent Activity Panel -->
            <div class="orabooks-recent-activity">
                <div class="orabooks-panel-header">
                    <span class="dashicons dashicons-clock"></span>
                    <h3><?php _e('Recent Activity (Last 7 Days)', 'orabooks'); ?></h3>
                </div>
                <div class="orabooks-panel-body">
                    <div class="orabooks-activity-item">
                        <span class="dashicons dashicons-building"></span>
                        <span class="orabooks-activity-text">
                            <?php printf(__('<strong>%s</strong> new organizations', 'orabooks'), '<span id="orabooks-stat-recent-orgs">—</span>'); ?>
                        </span>
                    </div>
                    <div class="orabooks-activity-item">
                        <span class="dashicons dashicons-admin-users"></span>
                        <span class="orabooks-activity-text">
                            <?php printf(__('<strong>%s</strong> new users registered', 'orabooks'), '<span id="orabooks-stat-recent-users">—</span>'); ?>
                        </span>
                    </div>
                    <div class="orabooks-activity-item">
                        <span class="dashicons dashicons-networking"></span>
                        <span class="orabooks-activity-text">
                            <?php printf(__('<strong>%s</strong> new attributions', 'orabooks'), '<span id="orabooks-stat-recent-attributions">—</span>'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Panel -->
            <div class="orabooks-quick-actions">
                <div class="orabooks-panel-header">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <h3><?php _e('Quick Actions', 'orabooks'); ?></h3>
                </div>
                <div class="orabooks-panel-body">
                    <div class="orabooks-qa-item">
                        <a href="admin.php?page=orabooks-partners" class="orabooks-qa-link">
                            <span class="orabooks-qa-label"><?php _e('Review Pending Partners', 'orabooks'); ?></span>
                            <span class="orabooks-qa-badge" id="orabooks-qa-pending-partners"></span>
                        </a>
                    </div>
                    <div class="orabooks-qa-item">
                        <a href="admin.php?page=orabooks-organizations" class="orabooks-qa-link">
                            <span class="orabooks-qa-label"><?php _e('Pending Organizations', 'orabooks'); ?></span>
                            <span class="orabooks-qa-badge" id="orabooks-qa-pending-orgs"></span>
                        </a>
                    </div>
                    <div class="orabooks-qa-item">
                        <a href="admin.php?page=orabooks-settings" class="orabooks-qa-link">
                            <span class="orabooks-qa-label"><?php _e('System Settings', 'orabooks'); ?></span>
                        </a>
                    </div>
                    <div class="orabooks-qa-item">
                        <a href="admin.php?page=orabooks-audit" class="orabooks-qa-link">
                            <span class="orabooks-qa-label"><?php _e('View Audit Log', 'orabooks'); ?></span>
                        </a>
                    </div>
                </div>
            </div>

        </div><!-- /orabooks-bottom-row -->

    </div><!-- /orabooks-dash-content -->
</div>