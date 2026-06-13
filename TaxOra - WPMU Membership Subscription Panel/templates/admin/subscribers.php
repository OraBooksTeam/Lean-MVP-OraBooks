<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

// Handle subscriber actions
if ( isset( $_GET['action'] ) && isset( $_GET['user_id'] ) ) {
    $user_id = intval( $_GET['user_id'] );
    $action = sanitize_text_field( $_GET['action'] );
    
    if ( $action === 'delete' && check_admin_referer( 'delete_subscriber_' . $user_id ) ) {
        delete_user_meta( $user_id, 'orabooks_level' );
        delete_user_meta( $user_id, 'orabooks_subdomain' );
        delete_user_meta( $user_id, 'orabooks_workspace_setup' );
        echo '<div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
            <svg style="width: 20px; height: 20px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div>
                <h3 style="font-weight: 600; margin-bottom: 0.25rem;">Subscriber Removed Successfully</h3>
                <p style="font-size: 0.875rem; opacity: 0.9;">User account kept but membership level removed.</p>
            </div>
        </div>';
    }
}

// Get all subscribers (users with orabooks_level meta)
$subscribers = get_users( array(
    'meta_key' => 'orabooks_level',
    'meta_compare' => 'EXISTS',
    'number' => 200,
    'orderby' => 'user_registered',
    'order' => 'DESC'
) );
?>

<div class="wrap orabooks-admin">
    <!-- Modern Header with Gradient -->
    <div style="background: linear-gradient(135deg, #2563eb 0%, #9333ea 50%, #4f46e5 100%); border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; color: white; position: relative; overflow: hidden;">
        <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.1);"></div>
        <div style="position: relative; z-index: 10;">
            <h1 style="font-size: 2.25rem; font-weight: 700; margin-bottom: 0.5rem;">Subscribers</h1>
            <p style="color: rgba(219,234,254,1); font-size: 1.125rem;">Users who have purchased membership plans</p>
        </div>
        <div style="position: absolute; top: 0; right: 0; width: 256px; height: 256px; background: rgba(255,255,255,0.05); border-radius: 50%; margin-right: -128px; margin-top: -128px;"></div>
        <div style="position: absolute; bottom: 0; left: 0; width: 192px; height: 192px; background: rgba(255,255,255,0.05); border-radius: 50%; margin-left: -96px; margin-bottom: -96px;"></div>
    </div>
    
    <?php if ( ! empty( $subscribers ) ) : ?>
        <!-- Subscribers Table -->
        <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);">
            <div style="margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">All Subscribers</h2>
                <p style="color: #6b7280; font-size: 0.875rem;">Manage your active membership subscribers</p>
            </div>
            
            <div style="overflow-x-auto;">
                <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border-bottom: 1px solid #e5e7eb;">
                    <tr>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">ID</th>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Username</th>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Email</th>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Membership Level</th>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Subscription Date</th>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $subscribers as $subscriber ) : 
                        $level_id = get_user_meta( $subscriber->ID, 'orabooks_level', true );
                        $level_name = 'Unknown';
                        
                        if ( $level_id ) {
                        $level = $wpdb->get_row( $wpdb->prepare( 
                            "SELECT name FROM {$wpdb->orabooks_levels} WHERE id = %d", 
                            $level_id 
                        ) );
                        if ( $level ) {
                            $level_name = $level->name;
                        }
                    }
                    
                    // Get subscription date from orders
                    $order = $wpdb->get_row( $wpdb->prepare( 
                        "SELECT created_at FROM {$wpdb->orabooks_orders} WHERE user_id = %d AND status = 'completed' ORDER BY created_at ASC LIMIT 1", 
                        $subscriber->ID 
                    ) );
                    
                    $subscription_date = $order ? date( 'M j, Y', strtotime( $order->created_at ) ) : 'N/A';
                ?>
                    <tr style="border-bottom: 1px solid #f3f4f6; transition: background-color 0.2s;">
                        <td style="padding: 1rem; font-weight: 600; color: #374151;"><?php echo intval( $subscriber->ID ); ?></td>
                        <td style="padding: 1rem; font-weight: 500; color: #1f2937;"><?php echo esc_html( $subscriber->user_login ); ?></td>
                        <td style="padding: 1rem; color: #6b7280;"><?php echo esc_html( $subscriber->user_email ); ?></td>
                        <td style="padding: 1rem;">
                            <span style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: white; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500;"><?php echo esc_html( $level_name ); ?></span>
                        </td>
                        <td style="padding: 1rem; color: #6b7280; font-size: 0.875rem;"><?php echo esc_html( $subscription_date ); ?></td>
                        <td style="padding: 1rem;">
                            <a href="<?php echo esc_url( add_query_arg( array( 'user_id' => $subscriber->ID, 'action' => 'edit' ), admin_url( 'user-edit.php' ) ) ); ?>" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: white; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500; text-decoration: none; display: inline-block; margin-right: 0.5rem; transition: all 0.2s;">Edit</a>
                            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'user_id' => $subscriber->ID, 'action' => 'delete' ), admin_url( 'admin.php?page=orabooks-membership-subscribers' ) ), 'delete_subscriber_' . $subscriber->ID ) ); ?>" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: white; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500; text-decoration: none; display: inline-block; transition: all 0.2s;" onclick="return confirm('Are you sure you want to remove this subscriber?\n\nThis will remove their membership level but keep their user account.')">Remove Subscriber</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <!-- Empty State -->
        <div style="background: white; border-radius: 0.75rem; padding: 3rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); text-align: center;">
            <div style="display: flex; flex-direction: column; align-items: center; gap: 1.5rem;">
                <div style="background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); border-radius: 50%; padding: 1.5rem;">
                    <svg style="width: 48px; height: 48px; color: #9ca3af;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">No Subscribers Found</h3>
                    <p style="color: #6b7280; font-size: 0.875rem; max-width: 400px; margin: 0 auto;">Subscribers are users who have purchased membership plans. When users subscribe, they'll appear here.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>