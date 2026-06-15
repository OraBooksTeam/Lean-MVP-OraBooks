<?php
/**
 * OraBooks Team Management (SL-014)
 * 
 * User invite, role management, team membership for organizations.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Team {
    
    private static $instance = null;
    
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            add_action('wp_ajax_orabooks_invite_user', [self::$instance, 'ajax_invite_user']);
            add_action('wp_ajax_orabooks_list_members', [self::$instance, 'ajax_list_members']);
            add_action('wp_ajax_orabooks_update_role', [self::$instance, 'ajax_update_role']);
            add_action('wp_ajax_orabooks_remove_user', [self::$instance, 'ajax_remove_user']);
            add_action('wp_ajax_orabooks_list_pending_invites', [self::$instance, 'ajax_list_pending_invites']);
            add_action('wp_ajax_orabooks_resend_invite', [self::$instance, 'ajax_resend_invite']);
            add_action('wp_ajax_orabooks_cancel_invite', [self::$instance, 'ajax_cancel_invite']);
            add_action('wp_ajax_nopriv_orabooks_accept_invite', [self::$instance, 'ajax_accept_invite']);
        }
        return self::$instance;
    }
    
    public static function invite_user($org_id, $email, $role, $invited_by) {
        global $wpdb;
        
        $table_invites = OraBooks_Database::table('org_invites');
        $table_users = OraBooks_Database::table('users');
        $table_user_org = OraBooks_Database::table('user_org');
        
        // Check if user already in org
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT uo.user_id FROM {$table_user_org} uo JOIN {$table_users} u ON uo.user_id = u.id WHERE uo.org_id = %d AND u.email = %s",
            $org_id, $email
        ));
        if ($existing) {
            return new WP_Error('already_member', 'User already in organization');
        }
        
        // Rate limit: 10 invites per minute per org
        if (!orabooks_check_rate_limit('invite_' . $org_id, 10, 60)) {
            return new WP_Error('rate_limit', 'Too many invites. Please wait.');
        }
        
        // Generate token
        $raw_token = orabooks_random_string(32);
        $token_hash = orabooks_hash_token($raw_token);
        $expires = date('Y-m-d H:i:s', time() + 604800); // 7 days
        
        $wpdb->insert(
            $table_invites,
            [
                'org_id' => $org_id,
                'email' => $email,
                'role' => $role,
                'token_hash' => $token_hash,
                'expires_at' => $expires
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
        
        $invite_id = $wpdb->insert_id;
        
        $org = OraBooks_Organization::get($org_id);
        
        orabooks_log_event('invite_sent', "Invite sent to $email for org {$org->name}", 'info', [
            'role' => $role,
            'email' => $email
        ], $invited_by, $org_id);
        
        return [
            'invite_id' => $invite_id,
            'invite_link' => home_url('/orabooks-accept-invite?token=' . $raw_token)
        ];
    }
    
    public static function accept_invite($raw_token) {
        global $wpdb;
        
        $table_invites = OraBooks_Database::table('org_invites');
        $table_users = OraBooks_Database::table('users');
        $table_user_org = OraBooks_Database::table('user_org');
        
        $token_hash = orabooks_hash_token($raw_token);
        
        $invite = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_invites} WHERE token_hash = %s AND used = 0 AND expires_at > NOW()",
            $token_hash
        ));
        
        if (!$invite) {
            return new WP_Error('invalid_invite', 'Invalid or expired invitation');
        }
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_users} WHERE email = %s", $invite->email
        ));
        
        if (!$user) {
            return new WP_Error('user_not_found', 'Please create an account first before accepting the invite');
        }
        
        // Check existing membership
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$table_user_org} WHERE user_id = %d AND org_id = %d",
            $user->id, $invite->org_id
        ));
        
        if ($existing) {
            $wpdb->update($table_invites, ['used' => 1], ['id' => $invite->id], ['%d'], ['%d']);
            return new WP_Error('already_member', 'You are already a member of this organization');
        }
        
        $wpdb->insert(
            $table_user_org,
            ['user_id' => $user->id, 'org_id' => $invite->org_id, 'role' => $invite->role],
            ['%d', '%d', '%s']
        );
        
        $wpdb->update($table_invites, ['used' => 1, 'accepted_at' => current_time('mysql')], ['id' => $invite->id], ['%d', '%s'], ['%d']);
        
        orabooks_log_event('invite_accepted', "User {$user->email} accepted invite to org {$invite->org_id}", 'info', [
            'role' => $invite->role
        ], $user->id, $invite->org_id);
        
        return ['org_id' => $invite->org_id, 'role' => $invite->role];
    }
    
    public static function update_role($org_id, $target_user_id, $new_role, $changed_by) {
        global $wpdb;
        
        if ($target_user_id == $changed_by) {
            return new WP_Error('self_change', 'Cannot change your own role');
        }
        
        $table_user_org = OraBooks_Database::table('user_org');
        
        // Check if target is last owner
        $owner_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_user_org} WHERE org_id = %d AND role = 'owner'",
            $org_id
        ));
        
        $current_role = $wpdb->get_var($wpdb->prepare(
            "SELECT role FROM {$table_user_org} WHERE user_id = %d AND org_id = %d",
            $target_user_id, $org_id
        ));
        
        if ($current_role === 'owner' && $owner_count <= 1) {
            return new WP_Error('last_owner', 'Cannot demote the last owner');
        }
        
        $wpdb->update(
            $table_user_org,
            ['role' => $new_role],
            ['user_id' => $target_user_id, 'org_id' => $org_id],
            ['%s'],
            ['%d', '%d']
        );
        
        // Revoke refresh tokens for this user in this org
        OraBooks_Auth::revoke_user_tokens($target_user_id, $org_id);
        
        orabooks_log_event('user_role_changed', "User $target_user_id role changed from $current_role to $new_role", 'info', [
            'old_role' => $current_role,
            'new_role' => $new_role
        ], $changed_by, $org_id);
        
        return true;
    }
    
    public static function remove_user($org_id, $target_user_id, $removed_by) {
        global $wpdb;
        
        if ($target_user_id == $removed_by) {
            return new WP_Error('self_remove', 'Cannot remove yourself');
        }
        
        $table_user_org = OraBooks_Database::table('user_org');
        
        $owner_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_user_org} WHERE org_id = %d AND role = 'owner'",
            $org_id
        ));
        
        $current_role = $wpdb->get_var($wpdb->prepare(
            "SELECT role FROM {$table_user_org} WHERE user_id = %d AND org_id = %d",
            $target_user_id, $org_id
        ));
        
        if ($current_role === 'owner' && $owner_count <= 1) {
            return new WP_Error('last_owner', 'Cannot remove the last owner');
        }
        
        $wpdb->delete(
            $table_user_org,
            ['user_id' => $target_user_id, 'org_id' => $org_id],
            ['%d', '%d']
        );
        
        // Revoke all tokens
        OraBooks_Auth::revoke_user_tokens($target_user_id, $org_id);
        
        orabooks_log_event('user_removed', "User $target_user_id removed from org $org_id", 'warning', [], $removed_by, $org_id);
        
        return true;
    }
    
    // AJAX handlers
    public function ajax_invite_user() {
        $user_id = get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $email = sanitize_email($_POST['email'] ?? '');
        $role = sanitize_text_field($_POST['role'] ?? 'staff');
        
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'invite_user')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $result = self::invite_user($org_id, $email, $role, $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success($result, 'Invitation sent');
    }
    
    public function ajax_list_members() {
        global $wpdb;
        $org_id = intval($_GET['org_id'] ?? 0);
        
        $table = OraBooks_Database::table('user_org');
        $table_users = OraBooks_Database::table('users');
        
        $members = $wpdb->get_results($wpdb->prepare(
            "SELECT u.id, u.email, uo.role, uo.joined_at, 'active' as status
             FROM {$table} uo
             JOIN {$table_users} u ON uo.user_id = u.id
             WHERE uo.org_id = %d
             ORDER BY FIELD(uo.role, 'owner', 'admin', 'approver', 'staff', 'viewer'), u.email",
            $org_id
        ));
        
        orabooks_json_success($members);
    }
    
    public function ajax_update_role() {
        $user_id = get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $target_user_id = intval($_POST['user_id'] ?? 0);
        $new_role = sanitize_text_field($_POST['role'] ?? '');
        
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'change_role')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $result = self::update_role($org_id, $target_user_id, $new_role, $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success([], 'Role updated');
    }
    
    public function ajax_remove_user() {
        $user_id = get_current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $target_user_id = intval($_POST['user_id'] ?? 0);
        
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'remove_user')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $result = self::remove_user($org_id, $target_user_id, $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success([], 'User removed');
    }
    
    public function ajax_list_pending_invites() {
        global $wpdb;
        $org_id = intval($_GET['org_id'] ?? 0);
        
        $table = OraBooks_Database::table('org_invites');
        $invites = $wpdb->get_results($wpdb->prepare(
            "SELECT id, email, role, created_at, expires_at FROM {$table} WHERE org_id = %d AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC",
            $org_id
        ));
        
        orabooks_json_success($invites);
    }
    
    public function ajax_resend_invite() {
        global $wpdb;
        
        $invite_id = intval($_POST['invite_id'] ?? 0);
        $org_id = intval($_POST['org_id'] ?? 0);
        $user_id = get_current_user_id();
        
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'invite_user')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $table = OraBooks_Database::table('org_invites');
        
        $raw_token = orabooks_random_string(32);
        $token_hash = orabooks_hash_token($raw_token);
        $expires = date('Y-m-d H:i:s', time() + 604800);
        
        $wpdb->update(
            $table,
            ['token_hash' => $token_hash, 'expires_at' => $expires],
            ['id' => $invite_id, 'org_id' => $org_id],
            ['%s', '%s'],
            ['%d', '%d']
        );
        
        $invite = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $invite_id));
        
        orabooks_log_event('invite_resent', "Invite resent to {$invite->email}", 'info', [], $user_id, $org_id);
        orabooks_json_success(['invite_link' => home_url('/orabooks-accept-invite?token=' . $raw_token)], 'Invitation resent');
    }
    
    public function ajax_cancel_invite() {
        global $wpdb;
        
        $invite_id = intval($_POST['invite_id'] ?? 0);
        $org_id = intval($_POST['org_id'] ?? 0);
        $user_id = get_current_user_id();
        
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'invite_user')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $table = OraBooks_Database::table('org_invites');
        $invite = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d AND org_id = %d", $invite_id, $org_id));
        
        if ($invite) {
            $wpdb->delete($table, ['id' => $invite_id], ['%d']);
            orabooks_log_event('invite_cancelled', "Invite cancelled for {$invite->email}", 'info', [], $user_id, $org_id);
        }
        
        orabooks_json_success([], 'Invitation cancelled');
    }
    
    public function ajax_accept_invite() {
        $token = $_GET['token'] ?? '';
        
        if (empty($token)) {
            wp_die('Invalid invitation link.');
        }
        
        $result = self::accept_invite($token);
        
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
        
        $org = OraBooks_Organization::get($result['org_id']);
        wp_redirect(home_url('/dashboard?org=' . $org->subdomain));
        exit;
    }
}