<?php
/**
 * Subscription Plans Admin Template
 */

// Get current data
$plans = $this->get_all_plans();
$subscription_count = $this->get_total_subscriptions_count();
?>

<div class="taxora-admin-plans">
    <div class="taxora-admin-header">
        <h1>Subscription Plans</h1>
        <div class="taxora-admin-actions">
            <a href="<?php echo admin_url('admin.php?page=taxora-plans&action=add'); ?>" class="button button-primary">Add New Plan</a>
            <a href="<?php echo admin_url('admin.php?page=taxora-settings'); ?>" class="button button-secondary">Settings</a>
        </div>
    </div>
    
    <div class="taxora-admin-stats">
        <div class="taxora-stat-item">
            <span class="taxora-stat-label">Total Plans:</span>
            <span class="taxora-stat-value"><?php echo count($plans); ?></span>
        </div>
        <div class="taxora-stat-item">
            <span class="taxora-stat-label">Active Subscriptions:</span>
            <span class="taxora-stat-value"><?php echo $subscription_count; ?></span>
        </div>
    </div>
    
    <div class="taxora-plans-container">
        <?php if (empty($plans)): ?>
            <div class="taxora-no-data">
                <p>No subscription plans found. <a href="<?php echo admin_url('admin.php?page=taxora-plans&action=add'); ?>">Create your first plan</a>.</p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped taxora-plans-table">
                <thead>
                    <tr>
                        <th>Plan Name</th>
                        <th>Price</th>
                        <th>Billing</th>
                        <th>Features</th>
                        <th>Users</th>
                        <th>Popular</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $plan): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($plan->name); ?></strong>
                                <?php if ($plan->is_popular): ?>
                                    <span class="taxora-popular-badge">Popular</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="taxora-price">
                                    <span class="taxora-price-amount">$<?php echo number_format($plan->price_monthly, 2); ?></span>
                                    <span class="taxora-price-period">/month</span>
                                </div>
                                <div class="taxora-price-yearly">
                                    $<?php echo number_format($plan->price_yearly, 2); ?> <span class="taxora-price-period">/year</span>
                                </div>
                            </td>
                            <td>
                                <?php echo ucfirst($plan->billing_cycle); ?>
                            </td>
                            <td>
                                <div class="taxora-features">
                                    <?php 
                                    $features = json_decode($plan->features, true);
                                    if (is_array($features)) {
                                        foreach ($features as $feature => $enabled) {
                                            if ($enabled) {
                                                echo '<span class="taxora-feature taxora-feature-enabled">' . esc_html(ucfirst(str_replace('_', ' ', $feature))) . '</span>';
                                            } else {
                                                echo '<span class="taxora-feature taxora-feature-disabled">' . esc_html(ucfirst(str_replace('_', ' ', $feature))) . '</span>';
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                            </td>
                            <td>
                                <?php echo $plan->max_users == -1 ? 'Unlimited' : $plan->max_users; ?>
                            </td>
                            <td>
                                <?php if ($plan->is_popular): ?>
                                    <span class="taxora-status taxora-status-active">Popular</span>
                                <?php else: ?>
                                    <span class="taxora-status taxora-status-inactive">Standard</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($plan->is_active): ?>
                                    <span class="taxora-status taxora-status-active">Active</span>
                                <?php else: ?>
                                    <span class="taxora-status taxora-status-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="taxora-actions">
                                    <a href="<?php echo admin_url('admin.php?page=taxora-plans&action=edit&id=' . $plan->id); ?>" class="button button-small">Edit</a>
                                    <a href="<?php echo admin_url('admin.php?page=taxora-plans&action=delete&id=' . $plan->id); ?>" class="button button-small button-danger" onclick="return confirm('Are you sure you want to delete this plan?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="taxora-admin-footer">
        <p><strong>Tip:</strong> Popular plans are displayed prominently to encourage upgrades. Click "Edit" to modify plan details or pricing.</p>
    </div>
</div>

<?php
// Helper methods
class Taxora_Plans_Helper {
    
    public function get_all_plans() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        return $wpdb->get_results("SELECT * FROM {$prefix}taxora_subscription_plans ORDER BY price_monthly ASC");
    }
    
    public function get_total_subscriptions_count() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}taxora_subscriptions WHERE status = 'active'");
        return $count ? $count : 0;
    }
}

$plans_helper = new Taxora_Plans_Helper();
