<?php
/**
 * Members Admin Template
 */

// Get current data
$members = $this->get_all_members();
$subscription_count = $this->get_active_subscriptions_count();
?>

<div class="taxora-admin-members">
    <div class="taxora-admin-header">
        <h1>Members</h1>
        <div class="taxora-admin-actions">
            <a href="<?php echo admin_url('admin.php?page=taxora-members&action=add'); ?>" class="button button-primary">Add Member</a>
            <a href="<?php echo admin_url('admin.php?page=taxora-settings'); ?>" class="button button-secondary">Settings</a>
        </div>
    </div>
    
    <div class="taxora-admin-stats">
        <div class="taxora-stat-item">
            <span class="taxora-stat-label">Total Members:</span>
            <span class="taxora-stat-value"><?php echo count($members); ?></span>
        </div>
        <div class="taxora-stat-item">
            <span class="taxora-stat-label">Active Subscriptions:</span>
            <span class="taxora-stat-value"><?php echo $subscription_count; ?></span>
        </div>
    </div>
    
    <div class="taxora-members-container">
        <?php if (empty($members)): ?>
            <div class="taxora-no-data">
                <p>No members found.</p>
            </div>
        <?php else: ?>
            <div class="taxora-member-filters">
                <div class="taxora-filter-group">
                    <label for="taxora_filter_status">Filter by Status:</label>
                    <select name="taxora_filter_status" id="taxora_filter_status">
                        <option value="">All Members</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="taxora-filter-group">
                    <label for="taxora_filter_plan">Filter by Plan:</label>
                    <select name="taxora_filter_plan" id="taxora_filter_plan">
                        <option value="">All Plans</option>
                        <?php
                        $plans = $this->get_all_plans();
                        foreach ($plans as $plan) {
                            echo '<option value="' . $plan->id . '">' . esc_html($plan->name) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="taxora-filter-group">
                    <label for="taxora_search">Search:</label>
                    <input type="text" name="taxora_search" id="taxora_search" placeholder="Search members...">
                </div>
                <div class="taxora-filter-group">
                    <button type="submit" class="button">Apply Filters</button>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped taxora-members-table">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-checkbox"><input type="checkbox" id="taxora-select-all"></th>
                        <th scope="col" class="manage-column column-name">Name</th>
                        <th scope="col" class="manage-column column-email">Email</th>
                        <th scope="col" class="manage-column column-plan">Plan</th>
                        <th scope="col" class="manage-column column-status">Status</th>
                        <th scope="col" class="manage-column column-date">Joined</th>
                        <th scope="col" class="manage-column column-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="member_ids[]" value="<?php echo $member->user_id; ?>">
                            </th>
                            <td class="name-column">
                                <strong>
                                    <a href="<?php echo admin_url('admin.php?page=taxora-members&action=edit&id=' . $member->user_id); ?>">
                                        <?php echo esc_html($member->display_name); ?>
                                    </a>
                                </strong>
                                <div class="taxora-member-email"><?php echo esc_html($member->user_email); ?></div>
                            </td>
                            <td class="email-column">
                                <a href="mailto:<?php echo esc_attr($member->user_email); ?>">
                                    <?php echo esc_html($member->user_email); ?>
                                </a>
                            </td>
                            <td class="plan-column">
                                <span class="taxora-plan-badge taxora-plan-<?php echo $member->plan_name; ?>">
                                    <?php echo esc_html($member->plan_name); ?>
                                </span>
                            </td>
                            <td class="status-column">
                                <span class="taxora-status taxora-status-<?php echo $member->subscription_status; ?>">
                                    <?php echo ucfirst($member->subscription_status); ?>
                                </span>
                            </td>
                            <td class="date-column">
                                <?php echo date('M j, Y', strtotime($member->created_at)); ?>
                            </td>
                            <td class="actions-column">
                                <div class="taxora-action-buttons">
                                    <a href="<?php echo admin_url('admin.php?page=taxora-members&action=view&id=' . $member->user_id); ?>" class="button button-small">View</a>
                                    <a href="<?php echo admin_url('admin.php?page=taxora-members&action=edit&id=' . $member->user_id); ?>" class="button button-small">Edit</a>
                                    <a href="<?php echo admin_url('admin.php?page=taxora-members&action=delete&id=' . $member->user_id); ?>" class="button button-small button-danger" onclick="return confirm('Are you sure you want to delete this member?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (!empty($members)): ?>
                <div class="taxora-pagination">
                    <?php
                    // Simple pagination
                    $total_pages = ceil(count($members) / 20);
                    $current_page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
                    
                    for ($i = 1; $i <= $total_pages; $i++) {
                        $class = $i == $current_page ? 'current' : '';
                        echo '<a href="' . admin_url('admin.php?page=taxora-members&paged=' . $i) . '" class="taxora-page-link ' . $class . '">' . $i . '</a>';
                    }
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <div class="taxora-admin-footer">
        <p><strong>Bulk Actions:</strong> Select members and use bulk actions from the dropdown above.</p>
    </div>
</div>

<?php
// Helper methods
class Taxora_Members_Helper {
    
    public function get_all_members() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        return $wpdb->get_results("
            SELECT u.ID, u.display_name, u.user_email, 
                   s.status, s.plan_id, s.created_at,
                   p.name as plan_name
            FROM {$wpdb->users} u
            LEFT JOIN {$prefix}taxora_subscriptions s ON u.ID = s.user_id
            LEFT JOIN {$prefix}taxora_subscription_plans p ON s.plan_id = p.id
            ORDER BY u.display_name ASC
        ");
    }
    
    public function get_active_subscriptions_count() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}taxora_subscriptions WHERE status = 'active'");
        return $count ? $count : 0;
    }
}

$members_helper = new Taxora_Members_Helper();
