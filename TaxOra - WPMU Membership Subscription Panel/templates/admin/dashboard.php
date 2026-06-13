<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Build Guide Compliance: Check permissions before displaying dashboard
if ( class_exists( 'OraBooks_Permission_Matrix' ) ) {
    $user_id = get_current_user_id();
    $role = OraBooks_Permission_Matrix::get_user_role( $user_id );
    $mode = OraBooks_Permission_Matrix::get_current_mode( $user_id );
    
    $permission = OraBooks_Permission_Matrix::check_permission(
        $user_id,
        $role,
        $mode,
        OraBooks_Permission_Matrix::ACTION_VIEW_DATA_REPORTS
    );
    
    if ( ! $permission['allowed'] ) {
        wp_die( 'You do not have permission to view the dashboard.' );
    }
}

global $wpdb;
$levels_table = $wpdb->orabooks_levels;
$orders_table = $wpdb->orabooks_orders;
$total_levels = intval( $wpdb->get_var( "SELECT COUNT(*) FROM $levels_table WHERE is_active = 1" ) );
$total_orders = intval( $wpdb->get_var( "SELECT COUNT(*) FROM $orders_table WHERE status='completed'" ) );
$revenue = floatval( $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM $orders_table WHERE status='completed'" ) );
$members = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}usermeta WHERE meta_key='orabooks_level'" ) );
$recent_orders = $wpdb->get_results( "SELECT * FROM $orders_table ORDER BY created_at DESC LIMIT 5" );
$recent_members = get_users( array( 
    'meta_key' => 'orabooks_level',
    'number' => 5,
    'orderby' => 'user_registered',
    'order' => 'DESC' 
) );

$reports_url = admin_url( 'admin.php?page=orabooks-membership-reports' );
$members_url = admin_url( 'admin.php?page=orabooks-membership-members' );
$orders_url = admin_url( 'admin.php?page=orabooks-membership-orders' );
$levels_url = admin_url( 'admin.php?page=orabooks-membership-levels' );

// Build Guide Compliance: Use localization system for currency formatting
if ( class_exists( 'OraBooks_Localization' ) ) {
    $localization = OraBooks_Localization::get_instance();
    $revenue_display = $localization->format_currency( $revenue, 'BDT' );
} else {
    // Fallback to hard-coded BDT
    $revenue_display = number_format( $revenue, 2 ) . '৳';
}

// Build Guide Compliance: Get current mode for display
$current_mode = class_exists( 'OraBooks_Mode_Manager' ) ? OraBooks_Mode_Manager::get_current_mode() : 'business';
$mode_display = ucfirst( $current_mode ) . ' Mode';

// Build Guide Compliance: Log dashboard view for audit trail
if ( class_exists( 'OraBooks_Audit_Logger' ) ) {
    $logger = OraBooks_Audit_Logger::get_instance();
    $logger->log_action( array(
        'user_id' => get_current_user_id(),
        'action_type' => 'dashboard_view',
        'action_description' => 'Dashboard viewed in ' . $current_mode . ' mode',
        'mode' => $current_mode,
    ) );
}

// Check if we have real orders
$has_real_orders = $total_orders > 0;
?>

<div class="wrap orabooks-admin">
    <!-- Modern Header with Gradient -->
    <div style="background: linear-gradient(135deg, #2563eb 0%, #9333ea 50%, #4f46e5 100%); border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; color: white; position: relative; overflow: hidden;">
        <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.1);"></div>
        <div style="position: relative; z-index: 10;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="font-size: 2.25rem; font-weight: 700; margin-bottom: 0.5rem;">Orabooks Dashboard</h1>
                    <p style="color: rgba(219,234,254,1); font-size: 1.125rem;">Membership Management & Analytics</p>
                </div>
                <div style="background: rgba(255,255,255,0.2); padding: 0.5rem 1rem; border-radius: 0.5rem;">
                    <span style="font-size: 0.875rem; font-weight: 500;"><?php echo esc_html( $mode_display ); ?></span>
                </div>
            </div>
        </div>
        <div style="position: absolute; top: 0; right: 0; width: 256px; height: 256px; background: rgba(255,255,255,0.05); border-radius: 50%; margin-right: -128px; margin-top: -128px;"></div>
        <div style="position: absolute; bottom: 0; left: 0; width: 192px; height: 192px; background: rgba(255,255,255,0.05); border-radius: 50%; margin-left: -96px; margin-bottom: -96px;"></div>
    </div>
    
    <!-- Simple Stats Table -->
    <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); margin-bottom: 2rem;">
        <div style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Overview</h2>
            <p style="color: #6b7280; font-size: 0.875rem;">Membership statistics at a glance</p>
        </div>
        
        <table style="width: 100%; border-collapse: collapse;">
            <thead style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border-bottom: 1px solid #e5e7eb;">
                <tr>
                    <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Metric</th>
                    <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Value</th>
                    <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Action</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom: 1px solid #f3f4f6;">
                    <td style="padding: 1rem; font-weight: 500; color: #1f2937;">Total Revenue</td>
                    <td style="padding: 1rem; font-weight: 600; color: #10b981;"><?php echo $revenue_display; ?></td>
                    <td style="padding: 1rem;">
                        <a href="<?php echo esc_url( $reports_url ); ?>" style="color: #3b82f6; text-decoration: none; font-size: 0.875rem;">View Reports</a>
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid #f3f4f6;">
                    <td style="padding: 1rem; font-weight: 500; color: #1f2937;">Total Members</td>
                    <td style="padding: 1rem; font-weight: 600; color: #3b82f6;"><?php echo esc_html( $members ); ?></td>
                    <td style="padding: 1rem;">
                        <a href="<?php echo esc_url( $members_url ); ?>" style="color: #3b82f6; text-decoration: none; font-size: 0.875rem;">View Members</a>
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid #f3f4f6;">
                    <td style="padding: 1rem; font-weight: 500; color: #1f2937;">Completed Orders</td>
                    <td style="padding: 1rem; font-weight: 600; color: #f59e0b;"><?php echo esc_html( $total_orders ); ?></td>
                    <td style="padding: 1rem;">
                        <a href="<?php echo esc_url( $orders_url ); ?>" style="color: #3b82f6; text-decoration: none; font-size: 0.875rem;">View Orders</a>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 1rem; font-weight: 500; color: #1f2937;">Active Levels</td>
                    <td style="padding: 1rem; font-weight: 600; color: #8b5cf6;"><?php echo esc_html( $total_levels ); ?></td>
                    <td style="padding: 1rem;">
                        <a href="<?php echo esc_url( $levels_url ); ?>" style="color: #3b82f6; text-decoration: none; font-size: 0.875rem;">View Levels</a>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Summary Charts Section -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
        <!-- Revenue by Subscription Level Pie Chart -->
        <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);">
            <div style="margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Revenue by Subscription Level</h3>
                <p style="color: #6b7280; font-size: 0.875rem;">Revenue distribution across membership levels</p>
            </div>
            <div style="position: relative; height: 300px;">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Payment Method Distribution Chart -->
        <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);">
            <div style="margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Payment Methods</h3>
                <p style="color: #6b7280; font-size: 0.875rem;">Payment gateway usage distribution</p>
            </div>
            <div style="position: relative; height: 300px;">
                <canvas id="paymentChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Monthly Revenue Trend Chart -->
    <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); margin-bottom: 2rem;">
        <div style="margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Monthly Revenue Trend</h3>
            <p style="color: #6b7280; font-size: 0.875rem;">Revenue trends over the last 6 months</p>
        </div>
        <div style="position: relative; height: 350px;">
            <canvas id="trendChart"></canvas>
        </div>
    </div>
    
    <!-- Recent Orders Table -->
    <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); margin-bottom: 2rem;">
        <div style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Recent Orders</h2>
            <p style="color: #6b7280; font-size: 0.875rem;">Latest completed orders</p>
        </div>
        
        <?php if ( ! empty( $recent_orders ) ) : ?>
            <div style="overflow-x-auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border-bottom: 1px solid #e5e7eb;">
                        <tr>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Order ID</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Amount</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Status</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $recent_orders as $o ) : 
                            // Build Guide Compliance: Use localization system for currency formatting
                            if (class_exists('OraBooks_Localization')) {
                                $localization = OraBooks_Localization::get_instance();
                                $amount_display = $localization->format_currency($o->amount ?? 0, 'BDT');
                            } else {
                                $amount_display = number_format($o->amount ?? 0, 2) . '৳';
                            }
                        ?>
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 1rem; font-weight: 500; color: #1f2937;"><?php echo esc_html( substr($o->order_id ?? '', 0, 12) ); ?></td>
                                <td style="padding: 1rem; font-weight: 600; color: #10b981;"><?php echo $amount_display; ?></td>
                                <td style="padding: 1rem;">
                                    <span style="background: <?php echo ($o->status ?? '') === 'completed' ? '#d1fae5' : '#fef3c7'; ?>; color: <?php echo ($o->status ?? '') === 'completed' ? '#065f46' : '#92400e'; ?>; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500;">
                                        <?php echo esc_html( $o->status ?? '' ); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; color: #6b7280; font-size: 0.875rem;">
                                    <?php 
                                    // Build Guide Compliance: Use localization system for date formatting
                                    if (class_exists('OraBooks_Localization')) {
                                        $localization = OraBooks_Localization::get_instance();
                                        echo esc_html($localization->format_date(strtotime($o->created_at ?? '')));
                                    } else {
                                        echo date('M j, Y', strtotime($o->created_at ?? ''));
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
            <p style="color: #6b7280; font-size: 0.875rem;">No recent orders found.</p>
        <?php endif; ?>
    </div>
    
    <!-- Recent Members Table -->
    <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);">
        <div style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">Recent Members</h2>
            <p style="color: #6b7280; font-size: 0.875rem;">Latest registered members</p>
        </div>
        
        <?php if ( ! empty( $recent_members ) ) : ?>
            <div style="overflow-x-auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border-bottom: 1px solid #e5e7eb;">
                        <tr>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Username</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Email</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Level</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $recent_members as $u ) : 
                            $level_id = get_user_meta( $u->ID, 'orabooks_level', true );
                            $level_name = $level_id ? $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->orabooks_levels} WHERE id = %d", $level_id)) : 'None';
                        ?>
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 1rem; font-weight: 500; color: #1f2937;"><?php echo esc_html( $u->user_login ?? '' ); ?></td>
                                <td style="padding: 1rem; color: #6b7280; font-size: 0.875rem;"><?php echo esc_html( $u->user_email ?? '' ); ?></td>
                                <td style="padding: 1rem;">
                                    <span style="background: #ede9fe; color: #5b21b6; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500;">
                                        <?php echo esc_html( $level_name ); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; color: #6b7280; font-size: 0.875rem;">
                                    <?php 
                                    // Build Guide Compliance: Use localization system for date formatting
                                    if (class_exists('OraBooks_Localization')) {
                                        $localization = OraBooks_Localization::get_instance();
                                        echo esc_html($localization->format_date(strtotime($u->user_registered ?? '')));
                                    } else {
                                        echo date('M j, Y', strtotime($u->user_registered ?? ''));
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
            <p style="color: #6b7280; font-size: 0.875rem;">No recent members found.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Build Guide Compliance: Get chart data from server
jQuery(document).ready(function($) {
    // Revenue by Subscription Level Pie Chart
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'orabooks_get_revenue_by_level',
            nonce: '<?php echo wp_create_nonce("orabooks_dashboard_charts"); ?>'
        },
        success: function(response) {
            if (response.success && response.data) {
                const ctx = document.getElementById('revenueChart').getContext('2d');
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: response.data.labels,
                        datasets: [{
                            data: response.data.values,
                            backgroundColor: [
                                '#3b82f6',
                                '#10b981', 
                                '#f59e0b',
                                '#ef4444',
                                '#8b5cf6',
                                '#ec4899'
                            ],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((value / total) * 100).toFixed(1);
                                        return label + ': ' + value.toLocaleString() + '৳ (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
    });

    // Payment Method Distribution Chart
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'orabooks_get_payment_methods',
            nonce: '<?php echo wp_create_nonce("orabooks_dashboard_charts"); ?>'
        },
        success: function(response) {
            if (response.success && response.data) {
                const ctx = document.getElementById('paymentChart').getContext('2d');
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: response.data.labels,
                        datasets: [{
                            data: response.data.values,
                            backgroundColor: [
                                '#06b6d4',
                                '#84cc16',
                                '#f97316',
                                '#a855f7'
                            ],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((value / total) * 100).toFixed(1);
                                        return label + ': ' + value + ' orders (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
    });

    // Monthly Revenue Trend Chart
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'orabooks_get_monthly_trend',
            nonce: '<?php echo wp_create_nonce("orabooks_dashboard_charts"); ?>'
        },
        success: function(response) {
            if (response.success && response.data) {
                const ctx = document.getElementById('trendChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: response.data.labels,
                        datasets: [{
                            label: 'Monthly Revenue',
                            data: response.data.values,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#3b82f6',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            pointHoverRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Revenue: ' + context.parsed.y.toLocaleString() + '৳';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return value.toLocaleString() + '৳';
                                    }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }
        }
    });
});
</script>
        
        


