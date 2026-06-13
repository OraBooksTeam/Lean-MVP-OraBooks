<?php
/**
 * Admin Dashboard Template
 */

// Get current user data
$current_user = wp_get_current_user();
$subscription_count = $this->get_active_subscriptions_count();
$member_count = $this->get_total_members_count();
$revenue = $this->get_monthly_revenue();
?>

<div class="taxora-admin-dashboard">
    <div class="taxora-stats-grid">
        <div class="taxora-stat-card">
            <h3>Total Members</h3>
            <div class="taxora-stat-number"><?php echo number_format($member_count); ?></div>
            <div class="taxora-stat-label">Registered Users</div>
        </div>
        
        <div class="taxora-stat-card">
            <h3>Active Subscriptions</h3>
            <div class="taxora-stat-number"><?php echo number_format($subscription_count); ?></div>
            <div class="taxora-stat-label">Active Plans</div>
        </div>
        
        <div class="taxora-stat-card">
            <h3>Monthly Revenue</h3>
            <div class="taxora-stat-number">$<?php echo number_format($revenue, 2); ?></div>
            <div class="taxora-stat-label">This Month</div>
        </div>
        
        <div class="taxora-stat-card">
            <h3>New Signups</h3>
            <div class="taxora-stat-number"><?php echo $this->get_new_signups_count(); ?></div>
            <div class="taxora-stat-label">This Month</div>
        </div>
    </div>
    
    <div class="taxora-activity-section">
        <h3>Recent Activity</h3>
        <div class="taxora-activity-feed">
            <?php
            $recent_activities = $this->get_recent_activities();
            if (empty($recent_activities)) {
                echo '<p>No recent activity to display.</p>';
            } else {
                foreach ($recent_activities as $activity) {
                    echo '<div class="taxora-activity-item">';
                    echo '<span class="taxora-activity-time">' . date('M j, Y g:i A', strtotime($activity->created_at)) . '</span>';
                    echo '<span class="taxora-activity-text">' . esc_html($activity->description) . '</span>';
                    echo '</div>';
                }
            }
            ?>
        </div>
    </div>
    
    <div class="taxora-quick-actions">
        <h3>Quick Actions</h3>
        <div class="taxora-action-buttons">
            <a href="<?php echo admin_url('admin.php?page=taxora-plans'); ?>" class="button button-primary">Manage Plans</a>
            <a href="<?php echo admin_url('admin.php?page=taxora-members'); ?>" class="button button-secondary">View Members</a>
            <a href="<?php echo admin_url('admin.php?page=taxora-pages'); ?>" class="button button-secondary">Edit Pages</a>
        </div>
    </div>
</div>

<?php
// Helper methods for the dashboard
class Taxora_Dashboard_Helper {
    
    public function get_active_subscriptions_count() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}taxora_subscriptions WHERE status = 'active'");
        return $count ? $count : 0;
    }
    
    public function get_total_members_count() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}taxora_subscriptions GROUP BY user_id");
        return $count ? $count : 0;
    }
    
    public function get_monthly_revenue() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        $revenue = $wpdb->get_var("
            SELECT SUM(sp.price_monthly) 
            FROM {$prefix}taxora_subscriptions s 
            JOIN {$prefix}taxora_subscription_plans sp ON s.plan_id = sp.id 
            WHERE s.status = 'active'
        ");
        return $revenue ? $revenue : 0;
    }
    
    public function get_new_signups_count() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        $count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$prefix}taxora_subscriptions 
            WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
            AND YEAR(created_at) = YEAR(CURRENT_DATE())
        ");
        return $count ? $count : 0;
    }
    
    public function get_recent_activities() {
        // Sample recent activities
        return [
            (object) [
                'description' => 'New member registration',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
            ],
            (object) [
                'description' => 'Subscription upgraded to Professional',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ],
            (object) [
                'description' => 'Payment received for Starter plan',
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours'))
            ]
        ];
    }
}

$dashboard_helper = new Taxora_Dashboard_Helper();
