<?php
if (!defined('ABSPATH')) {
    exit;
}

// Add this line to access the global $wpdb object
global $wpdb;

// Date range
$start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-t');
$group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

// Revenue Report
$revenue_where = "o.status = 'completed' AND o.created_at BETWEEN %s AND %s";
$revenue_params = array($start_date . ' 00:00:00', $end_date . ' 23:59:59');

if ($group_id > 0) {
    $revenue_where .= " AND l.group_id = %d";
    $revenue_params[] = $group_id;
}

$revenue_data = $wpdb->get_results($wpdb->prepare("
    SELECT 
        DATE(o.created_at) as date,
        COUNT(*) as order_count,
        SUM(o.amount) as daily_revenue,
        AVG(o.amount) as avg_order_value
    FROM {$wpdb->orabooks_orders} o
    LEFT JOIN {$wpdb->orabooks_levels} l ON o.level_id = l.id
    WHERE {$revenue_where}
    GROUP BY DATE(o.created_at)
    ORDER BY date ASC
", $revenue_params));

// Subscription Report
$subscription_data = $wpdb->get_results($wpdb->prepare("
    SELECT 
        l.name as level_name,
        g.name as group_name,
        COUNT(*) as subscriber_count,
        SUM(l.price) as monthly_revenue
    FROM {$wpdb->orabooks_subscriptions} s
    LEFT JOIN {$wpdb->orabooks_levels} l ON s.level_id = l.id
    LEFT JOIN {$wpdb->orabooks_groups} g ON l.group_id = g.id
    WHERE s.status = 'active'
    " . ($group_id > 0 ? " AND l.group_id = %d" : "") . "
    GROUP BY s.level_id
    ORDER BY monthly_revenue DESC
", $group_id > 0 ? array($group_id) : array()));

// Customer Acquisition
$customer_data = $wpdb->get_results($wpdb->prepare("
    SELECT 
        DATE(u.user_registered) as date,
        COUNT(*) as new_customers
    FROM {$wpdb->users} u
    INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'orabooks_level'
    WHERE u.user_registered BETWEEN %s AND %s
    GROUP BY DATE(u.user_registered)
    ORDER BY date ASC
", array($start_date . ' 00:00:00', $end_date . ' 23:59:59')));

// Get groups for filter
$groups = $wpdb->get_results("SELECT * FROM {$wpdb->orabooks_groups} ORDER BY name");

// Summary Stats
$summary_stats = $wpdb->get_row($wpdb->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(o.amount) as total_revenue,
        AVG(o.amount) as avg_revenue,
        COUNT(DISTINCT o.user_id) as unique_customers
    FROM {$wpdb->orabooks_orders} o
    LEFT JOIN {$wpdb->orabooks_levels} l ON o.level_id = l.id
    WHERE o.status = 'completed' AND o.created_at BETWEEN %s AND %s
    " . ($group_id > 0 ? " AND l.group_id = %d" : ""),
    $group_id > 0 ? array($start_date . ' 00:00:00', $end_date . ' 23:59:59', $group_id) : array($start_date . ' 00:00:00', $end_date . ' 23:59:59')
));

$active_subscribers = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->orabooks_subscriptions} WHERE status = 'active'");
?>

<div class="wrap orabooks-admin">
    <!-- Modern Header with Gradient -->
    <div style="background: linear-gradient(135deg, #2563eb 0%, #9333ea 50%, #4f46e5 100%); border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; color: white; position: relative; overflow: hidden;">
        <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.1);"></div>
        <div style="position: relative; z-index: 10;">
            <h1 style="font-size: 2.25rem; font-weight: 700; margin-bottom: 0.5rem;">Analytics & Reports</h1>
            <p style="color: rgba(219,234,254,1); font-size: 1.125rem;">Comprehensive insights into your membership business</p>
        </div>
        <div style="position: absolute; top: 0; right: 0; width: 256px; height: 256px; background: rgba(255,255,255,0.05); border-radius: 50%; margin-right: -128px; margin-top: -128px;"></div>
        <div style="position: absolute; bottom: 0; left: 0; width: 192px; height: 192px; background: rgba(255,255,255,0.05); border-radius: 50%; margin-left: -96px; margin-bottom: -96px;"></div>
    </div>

    <!-- Simple Report Filters -->
    <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); margin-bottom: 2rem;">
        <div style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Report Filters</h2>
            <p style="color: #6b7280; font-size: 0.875rem;">Customize your analytics view</p>
        </div>
        <form method="get" action="" style="display: flex; flex-wrap: wrap; gap: 1rem;">
            <input type="hidden" name="page" value="orabooks-reports">
            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
            </div>
                    <div style="flex: 1; min-width: 200px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;">Filter by Group</label>
                <select id="group_id" name="group_id" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                    <option value="0">All Groups</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?php echo esc_attr($group->id); ?>" <?php selected($group_id, $group->id); ?>>
                            <?php echo esc_html($group->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; align-items: flex-end;">
                <button type="submit" style="background: linear-gradient(135deg, #3b82f6 0%, #4f46e5 100%); color: white; padding: 0.75rem 1.5rem; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500; border: none; cursor: pointer; margin-top: 1.5rem;">
                    Generate Report
                </button>
            </div>
        </form>
    </div>

    <!-- Summary Stats Table -->
    <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); margin-bottom: 2rem;">
        <div style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Quick Stats</h2>
            <p style="color: #6b7280; font-size: 0.875rem;">Overview of your membership metrics</p>
        </div>

        <table style="width: 100%; border-collapse: collapse;">
            <thead style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border-bottom: 1px solid #e5e7eb;">
                <tr>
                    <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Metric</th>
                    <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Value</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom: 1px solid #f3f4f6;">
                    <td style="padding: 1rem; font-weight: 500; color: #1f2937;">Active Subscribers</td>
                    <td style="padding: 1rem; font-weight: 600; color: #10b981;"><?php echo intval($active_subscribers); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #f3f4f6;">
                    <td style="padding: 1rem; font-weight: 500; color: #1f2937;">Total Revenue (Period)</td>
                    <td style="padding: 1rem; font-weight: 600; color: #3b82f6;"><?php echo number_format(array_sum(array_column($revenue_data, 'daily_revenue')), 2); ?>৳</td>
                </tr>
                <tr style="border-bottom: 1px solid #f3f4f6;">
                    <td style="padding: 1rem; font-weight: 500; color: #1f2937;">Total Orders</td>
                    <td style="padding: 1rem; font-weight: 600; color: #f59e0b;"><?php echo intval($summary_stats->total_orders ?? 0); ?></td>
                </tr>
                <tr>
                    <td style="padding: 1rem; font-weight: 500; color: #1f2937;">Avg Order Value</td>
                    <td style="padding: 1rem; font-weight: 600; color: #8b5cf6;"><?php echo number_format($summary_stats->avg_revenue ?? 0, 2); ?>৳</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Subscription Breakdown -->
    <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);">
        <div style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Subscription Breakdown</h2>
            <p style="color: #6b7280; font-size: 0.875rem;">Active subscriptions by level</p>
        </div>

        <div style="overflow-x-auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border-bottom: 1px solid #e5e7eb;">
                    <tr>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Level</th>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Group</th>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Subscribers</th>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Monthly Revenue</th>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Revenue Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($subscription_data): ?>
                        <?php 
                        $total_monthly_revenue = array_sum(array_column($subscription_data, 'monthly_revenue'));
                        foreach ($subscription_data as $data): 
                            $revenue_share = $total_monthly_revenue > 0 ? ($data->monthly_revenue / $total_monthly_revenue) * 100 : 0;
                        ?>
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 1rem; font-weight: 500; color: #1f2937;"><?php echo esc_html($data->level_name); ?></td>
                                <td style="padding: 1rem; color: #6b7280; font-size: 0.875rem;"><?php echo esc_html($data->group_name); ?></td>
                                <td style="padding: 1rem; font-weight: 600; color: #3b82f6;"><?php echo intval($data->subscriber_count); ?></td>
                                <td style="padding: 1rem; font-weight: 600; color: #10b981;"><?php echo number_format($data->monthly_revenue, 2); ?>৳</td>
                                <td style="padding: 1rem;">
                                    <span style="background: #ede9fe; color: #5b21b6; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500;">
                                        <?php echo number_format($revenue_share, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem; color: #6b7280;">No subscription data available</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
                                                                                                                                    
