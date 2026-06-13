<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

// Handle member actions
if ( isset( $_GET['action'] ) && isset( $_GET['user_id'] ) ) {
    $user_id = intval( $_GET['user_id'] );
    $action = sanitize_text_field( $_GET['action'] );
    
    if ( $action === 'delete' && check_admin_referer( 'delete_member_' . $user_id ) ) {
        $is_subscriber = get_user_meta( $user_id, 'orabooks_level', true );

        if ( $is_subscriber ) {
            echo '<div style="background: linear-gradient(135deg, #dc2626 0%, #ec4899 100%); border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1.5rem; color: white;">
                <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem;">Cannot Delete Subscriber</h3>
                <p style="color: rgba(254, 226, 226, 1); font-size: 0.875rem;">This user is a subscriber. Please remove them from subscribers first.</p>
            </div>';
        } else {
            if ( wp_delete_user( $user_id ) ) {
                echo '<div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1.5rem; color: white;">
                    <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem;">Member Deleted Successfully</h3>
                    <p style="color: rgba(236, 253, 245, 1); font-size: 0.875rem;">The member has been removed from the system.</p>
                </div>';
            } else {
                echo '<div style="background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1.5rem; color: white;">
                    <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem;">Error Deleting Member</h3>
                    <p style="color: rgba(254, 226, 226, 1); font-size: 0.875rem;">There was an error removing the member.</p>
                </div>';
            }
        }
    }
}

// Get all members (registered users without subscriptions)
$all_users = get_users( array( 
    'number' => 200,
    'orderby' => 'user_registered',
    'order' => 'DESC'
) );
$members = array();

foreach ( $all_users as $user ) {
    $is_subscriber = get_user_meta( $user->ID, 'orabooks_level', true );
    if ( ! $is_subscriber ) {
        $members[] = $user;
    }
}
?>

<div class="wrap orabooks-admin">
    <!-- Modern Header with Gradient -->
    <div style="background: linear-gradient(135deg, #2563eb 0%, #9333ea 50%, #4f46e5 100%); border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; color: white; position: relative; overflow: hidden;">
        <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.1);"></div>
        <div style="position: relative; z-index: 10;">
            <h1 style="font-size: 2.25rem; font-weight: 700; margin-bottom: 0.5rem;">Members</h1>
            <p style="color: rgba(219,234,254,1); font-size: 1.125rem;">Registered users without active subscriptions</p>
        </div>
        <div style="position: absolute; top: 0; right: 0; width: 256px; height: 256px; background: rgba(255,255,255,0.05); border-radius: 50%; margin-right: -128px; margin-top: -128px;"></div>
        <div style="position: absolute; bottom: 0; left: 0; width: 192px; height: 192px; background: rgba(255,255,255,0.05); border-radius: 50%; margin-left: -96px; margin-bottom: -96px;"></div>
    </div>

    <?php if ( ! empty( $members ) ) : ?>
        <div style="background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);">
            <div style="margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">All Members</h2>
                <p style="color: #6b7280; font-size: 0.875rem;">Manage registered users who haven't subscribed yet</p>
            </div>

            <div style="overflow-x-auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border-bottom: 1px solid #e5e7eb;">
                        <tr>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">ID</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Username</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Email</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Display Name</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Registration Date</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $members as $member ) :
                            $registration_date = date( 'M j, Y', strtotime( $member->user_registered ) );
                        ?>
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 1rem; font-weight: 500; color: #1f2937;"><?php echo intval( $member->ID ); ?></td>
                                <td style="padding: 1rem; font-weight: 500; color: #1f2937;"><?php echo esc_html( $member->user_login ); ?></td>
                                <td style="padding: 1rem; color: #6b7280; font-size: 0.875rem;"><?php echo esc_html( $member->user_email ); ?></td>
                                <td style="padding: 1rem; color: #6b7280; font-size: 0.875rem;"><?php echo esc_html( $member->display_name ); ?></td>
                                <td style="padding: 1rem; color: #6b7280; font-size: 0.875rem;"><?php echo esc_html( $registration_date ); ?></td>
                                <td style="padding: 1rem;">
                                    <a href="<?php echo esc_url( add_query_arg( array( 'user_id' => $member->ID, 'action' => 'edit' ), admin_url( 'user-edit.php' ) ) ); ?>" style="color: #3b82f6; text-decoration: none; font-size: 0.875rem; margin-right: 0.75rem;">Edit</a>
                                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'user_id' => $member->ID, 'action' => 'delete' ), admin_url( 'admin.php?page=orabooks-membership-members' ) ), 'delete_member_' . $member->ID ) ); ?>" style="color: #dc2626; text-decoration: none; font-size: 0.875rem;" onclick="return confirm('Are you sure you want to delete this member?\n\nThis action cannot be undone.')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else : ?>
        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 0.5rem; padding: 1rem;">
            <p style="color: #1e40af; font-size: 0.875rem;">No members found. Members are users who have registered but haven't purchased any subscription plans.</p>
        </div>
    <?php endif; ?>
</div>