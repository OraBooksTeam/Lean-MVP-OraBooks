<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title(''); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('taxora-dashboard-page'); ?>>
    <div class="taxora-dashboard-container">
        <!-- Welcome Section -->
        <div class="taxora-dashboard-welcome">
            <h2>Welcome, <?php echo esc_html(wp_get_current_user()->display_name); ?>!</h2>
            <p>Your membership dashboard provides access to all your subscription features and account management tools.</p>
        </div>
        
        <!-- Quick Stats -->
        <div class="taxora-dashboard-stats">
            <h3>Your Membership</h3>
            <div class="taxora-membership-info">
                <div class="taxora-membership-plan">
                    <span class="taxora-membership-label">Current Plan:</span>
                    <span class="taxora-membership-value"><?php echo esc_html($this->get_user_plan_name()); ?></span>
                </div>
                <div class="taxora-membership-status">
                    <span class="taxora-membership-label">Status:</span>
                    <span class="taxora-membership-value taxora-status-<?php echo $this->get_user_subscription_status(); ?>"><?php echo ucfirst($this->get_user_subscription_status()); ?></span>
                </div>
                <div class="taxora-membership-date">
                    <span class="taxora-membership-label">Member Since:</span>
                    <span class="taxora-membership-value"><?php echo date('F j, Y', strtotime($this->get_user_member_since())); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="taxora-dashboard-actions">
            <h3>Quick Actions</h3>
            <div class="taxora-action-buttons">
                <a href="#pricing" class="taxora-button taxora-button-primary">Upgrade Plan</a>
                <a href="#profile" class="taxora-button taxora-button-secondary">Update Profile</a>
                <a href="#billing" class="taxora-button taxora-button-secondary">View Billing</a>
                <a href="#support" class="taxora-button taxora-button-outline">Get Support</a>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="taxora-dashboard-activity">
            <h3>Recent Activity</h3>
            <div class="taxora-activity-feed">
                <?php
                $activities = $this->get_user_activities();
                if (empty($activities)) {
                    echo '<p>No recent activity to display.</p>';
                } else {
                    foreach ($activities as $activity) {
                        echo '<div class="taxora-activity-item">';
                            echo '<span class="taxora-activity-time">' . date('M j, Y g:i A', strtotime($activity->created_at)) . '</span>';
                            echo '<span class="taxora-activity-text">' . esc_html($activity->description) . '</span>';
                            echo '</div>';
                    }
                }
                ?>
            </div>
        </div>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>

<?php
// Helper methods for dashboard
class Taxora_Dashboard_Helper {
    
    public function get_user_plan_name() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        $user_id = get_current_user_id();
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, p.name as plan_name 
             FROM {$prefix}taxora_subscriptions s 
             JOIN {$prefix}taxora_subscription_plans p ON s.plan_id = p.id 
             WHERE s.user_id = %d AND s.status = 'active' 
             ORDER BY s.created_at DESC LIMIT 1",
            $user_id
        ));
        
        return $subscription ? $subscription->plan_name : 'No Active Plan';
    }
    
    public function get_user_subscription_status() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        $user_id = get_current_user_id();
        $subscription = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$prefix}taxora_subscriptions 
             WHERE user_id = %d AND status = 'active' 
             ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));
        
        return $subscription ? $subscription : 'inactive';
    }
    
    public function get_user_member_since() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        $user_id = get_current_user_id();
        $member = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM {$prefix}taxora_subscriptions 
             WHERE user_id = %d 
             ORDER BY created_at ASC LIMIT 1",
            $user_id
        ));
        
        return $member ? $member : current_time('mysql');
    }
    
    public function get_user_activities() {
        // Sample user activities
        return [
            (object) [
                'description' => 'Logged in to dashboard',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
            ],
            (object) [
                'description' => 'Updated profile information',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ],
            (object) [
                'description' => 'Viewed billing statement',
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
            ]
        ];
    }
}

$dashboard_helper = new Taxora_Dashboard_Helper();
