<?php
if (!defined('ABSPATH')) {
    exit;
}
// Add this line to access the global $wpdb object
global $wpdb;

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Build query
$where_conditions = array('1=1');
$query_params = array();

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $where_conditions[] = 'o.status = %s';
    $query_params[] = sanitize_text_field($_GET['status']);
}

if (isset($_GET['gateway']) && $_GET['gateway'] !== '') {
    $where_conditions[] = 'o.gateway = %s';
    $query_params[] = sanitize_text_field($_GET['gateway']);
}

if (isset($_GET['search']) && $_GET['search'] !== '') {
    $search = sanitize_text_field($_GET['search']);
    $where_conditions[] = '(o.order_id LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s OR l.name LIKE %s)';
    $query_params[] = '%' . $wpdb->esc_like($search) . '%';
    $query_params[] = '%' . $wpdb->esc_like($search) . '%';
    $query_params[] = '%' . $wpdb->esc_like($search) . '%';
    $query_params[] = '%' . $wpdb->esc_like($search) . '%';
}

$where_clause = implode(' AND ', $where_conditions);

// Get orders
$orders = $wpdb->get_results($wpdb->prepare("
    SELECT o.*, 
           u.user_login, 
           u.user_email,
           l.name as level_name,
           l.price as level_price,
           g.name as group_name
    FROM {$wpdb->orabooks_orders} o
    LEFT JOIN {$wpdb->users} u ON o.user_id = u.ID
    LEFT JOIN {$wpdb->orabooks_levels} l ON o.level_id = l.id
    LEFT JOIN {$wpdb->orabooks_groups} g ON l.group_id = g.id
    WHERE {$where_clause}
    ORDER BY o.created_at DESC
    LIMIT %d OFFSET %d
", array_merge($query_params, array($per_page, $offset))));

// Total count for pagination
$total_orders = $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(*)
    FROM {$wpdb->orabooks_orders} o
    LEFT JOIN {$wpdb->users} u ON o.user_id = u.ID
    LEFT JOIN {$wpdb->orabooks_levels} l ON o.level_id = l.id
    WHERE {$where_clause}
", $query_params));

$total_pages = ceil($total_orders / $per_page);

// Stats
$stats = $wpdb->get_results("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_orders,
        SUM(amount) as total_revenue
    FROM {$wpdb->orabooks_orders}
    WHERE status = 'completed'
");
$stats = $stats[0] ?? null;
?>

<div class="wrap orabooks-admin">
    <!-- Modern Header with Gradient -->
    <div style="background: linear-gradient(135deg, #2563eb 0%, #9333ea 50%, #4f46e5 100%); border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; color: white; position: relative; overflow: hidden;">
        <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.1);"></div>
        <div style="position: relative; z-index: 10;">
            <h1 style="font-size: 2.25rem; font-weight: 700; margin-bottom: 0.5rem;">Orders Management</h1>
            <p style="color: rgba(219,234,254,1); font-size: 1.125rem;">Track and manage all subscription orders</p>
        </div>
        <div style="position: absolute; top: 0; right: 0; width: 256px; height: 256px; background: rgba(255,255,255,0.05); border-radius: 50%; margin-right: -128px; margin-top: -128px;"></div>
        <div style="position: absolute; bottom: 0; left: 0; width: 192px; height: 192px; background: rgba(255,255,255,0.05); border-radius: 50%; margin-left: -96px; margin-bottom: -96px;"></div>
    </div>

    <!-- Simple Stats Table -->
    <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); margin-bottom: 2rem;">
        <div style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Order Statistics</h2>
            <p style="color: #6b7280; font-size: 0.875rem;">Overview of your order metrics</p>
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
                    <td style="padding: 1rem; font-weight: 500; color: #1f2937;">Total Orders</td>
                    <td style="padding: 1rem; font-weight: 600; color: #3b82f6;"><?php echo esc_html($stats->total_orders ?? 0); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #f3f4f6;">
                    <td style="padding: 1rem; font-weight: 500; color: #1f2937;">Completed Orders</td>
                    <td style="padding: 1rem; font-weight: 600; color: #10b981;"><?php echo esc_html($stats->completed_orders ?? 0); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #f3f4f6;">
                    <td style="padding: 1rem; font-weight: 500; color: #1f2937;">Pending Orders</td>
                    <td style="padding: 1rem; font-weight: 600; color: #f59e0b;"><?php echo esc_html($stats->pending_orders ?? 0); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #f3f4f6;">
                    <td style="padding: 1rem; font-weight: 500; color: #1f2937;">Total Revenue</td>
                    <td style="padding: 1rem; font-weight: 600; color: #8b5cf6;"><?php echo number_format($stats->total_revenue ?? 0, 2); ?>৳</td>
                </tr>
            </tbody>
        </table>
    </div>
                        
    <div class="orabooks-admin-content">
        <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);">
            <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Order History</h2>
                    <p style="color: #6b7280; font-size: 0.875rem;">View and filter all subscription orders</p>
                </div>
                <div style="background: #f9fafb; border-radius: 0.5rem; padding: 1rem;">
                    <form method="get" action="" style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center;">
                        <input type="hidden" name="page" value="orabooks-orders">
                        <select name="status" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                            <option value="">All Statuses</option>
                            <option value="completed" <?php selected($_GET['status'] ?? '', 'completed'); ?>>Completed</option>
                            <option value="pending" <?php selected($_GET['status'] ?? '', 'pending'); ?>>Pending</option>
                            <option value="failed" <?php selected($_GET['status'] ?? '', 'failed'); ?>>Failed</option>
                            <option value="refunded" <?php selected($_GET['status'] ?? '', 'refunded'); ?>>Refunded</option>
                        </select>
                        <select name="gateway" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                            <option value="">All Gateways</option>
                            <option value="manual-test" <?php selected($_GET['gateway'] ?? '', 'manual-test'); ?>>Manual Test</option>
                            <option value="stripe" <?php selected($_GET['gateway'] ?? '', 'stripe'); ?>>Stripe</option>
                            <option value="paypal" <?php selected($_GET['gateway'] ?? '', 'paypal'); ?>>PayPal</option>
                        </select>
                        <input type="text" name="search" placeholder="Search orders..." value="<?php echo esc_attr($_GET['search'] ?? ''); ?>" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                        <button type="submit" style="background: #3b82f6; color: white; padding: 0.5rem 1rem; border-radius: 0.375rem; border: none; cursor: pointer;">Filter</button>
                        <a href="?page=orabooks-orders" style="background: white; color: #374151; padding: 0.5rem 1rem; border-radius: 0.375rem; border: 1px solid #d1d5db; text-decoration: none;">Reset</a>
                    </form>
                </div>
            </div>

            <div style="overflow-x-auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border-bottom: 1px solid #e5e7eb;">
                        <tr>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Order ID</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">User</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Level</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Amount</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Gateway</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Status</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Date</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Actions</th>
                        </tr>
                    </thead>
                <tbody>
                    <?php if ($orders): ?>
                        <?php foreach ($orders as $order): ?>
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 1rem; font-weight: 500; color: #1f2937;"><?php echo esc_html($order->order_id); ?></td>
                                <td style="padding: 1rem;">
                                    <?php if ($order->user_login): ?>
                                        <a href="<?php echo get_edit_user_link($order->user_id); ?>" style="color: #3b82f6; text-decoration: none; font-weight: 500;">
                                            <?php echo esc_html($order->user_login); ?>
                                        </a>
                                        <br><small style="color: #6b7280; font-size: 0.75rem;"><?php echo esc_html($order->user_email); ?></small>
                                    <?php else: ?>
                                        <em style="color: #9ca3af;">User deleted</em>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem; font-weight: 500; color: #1f2937;">
                                    <?php echo esc_html($order->level_name); ?>
                                    <?php if ($order->group_name): ?>
                                        <br><small style="color: #6b7280; font-size: 0.75rem;">Group: <?php echo esc_html($order->group_name); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem; font-weight: 600; color: #10b981;"><?php echo number_format($order->amount, 2); ?>৳</td>
                                <td style="padding: 1rem; color: #6b7280; font-size: 0.875rem;"><?php echo esc_html(ucfirst(str_replace('-', ' ', (string)$order->gateway))); ?></td>
                                <td style="padding: 1rem;">
                                    <span style="background: <?php echo $order->status === 'completed' ? '#d1fae5' : '#fef3c7'; ?>; color: <?php echo $order->status === 'completed' ? '#065f46' : '#92400e'; ?>; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500;">
                                        <?php echo esc_html(ucfirst($order->status)); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; color: #6b7280; font-size: 0.875rem;"><?php echo date('M j, Y g:i A', strtotime($order->created_at)); ?></td>
                                <td style="padding: 1rem;">
                                    <button type="button" onclick="viewOrderDetails(<?php echo esc_attr($order->id); ?>)" style="background: white; border: 1px solid #d1d5db; color: #374151; padding: 0.5rem 1rem; border-radius: 0.375rem; font-size: 0.75rem; cursor: pointer; margin-right: 0.5rem;">
                                        View Details
                                    </button>
                                    <?php if ($order->status === 'pending'): ?>
                                        <button type="button" onclick="updateOrderStatus(<?php echo esc_attr($order->id); ?>, 'completed')" style="background: #3b82f6; border: none; color: white; padding: 0.5rem 1rem; border-radius: 0.375rem; font-size: 0.75rem; cursor: pointer;">
                                            Mark Complete
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">
                                No orders found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Order Details Modal -->
<div id="order-modal" class="orabooks-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Order Details</h3>
            <span class="close" onclick="closeOrderModal()">&times;</span>
        </div>
        <div class="modal-body" id="order-details">
            <!-- Order details will be loaded here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="button" onclick="closeOrderModal()">Close</button>
        </div>
    </div>
</div>

<script type="text/javascript">
function viewOrderDetails(orderId) {
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'action': 'orabooks_get_order_details',
            'order_id': orderId,
            'nonce': '<?php echo wp_create_nonce('orabooks_admin_nonce'); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('order-details').innerHTML = data.data.html;
            document.getElementById('order-modal').style.display = 'block';
        }
    });
}

function closeOrderModal() {
    document.getElementById('order-modal').style.display = 'none';
}

function updateOrderStatus(orderId, status) {
    if (confirm('Are you sure you want to update this order status?')) {
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                'action': 'orabooks_update_order_status',
                'order_id': orderId,
                'status': status,
                'nonce': '<?php echo wp_create_nonce('orabooks_admin_nonce'); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.data);
            }
        });
    }
}
</script>