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
            add_action('wp_ajax_nopriv_orabooks_invite_user', [self::$instance, 'ajax_invite_user']);
            add_action('wp_ajax_orabooks_list_members', [self::$instance, 'ajax_list_members']);
            add_action('wp_ajax_nopriv_orabooks_list_members', [self::$instance, 'ajax_list_members']);
            add_action('wp_ajax_orabooks_update_role', [self::$instance, 'ajax_update_role']);
            add_action('wp_ajax_nopriv_orabooks_update_role', [self::$instance, 'ajax_update_role']);
            add_action('wp_ajax_orabooks_remove_user', [self::$instance, 'ajax_remove_user']);
            add_action('wp_ajax_nopriv_orabooks_remove_user', [self::$instance, 'ajax_remove_user']);
            add_action('wp_ajax_orabooks_list_pending_invites', [self::$instance, 'ajax_list_pending_invites']);
            add_action('wp_ajax_nopriv_orabooks_list_pending_invites', [self::$instance, 'ajax_list_pending_invites']);
            add_action('wp_ajax_orabooks_resend_invite', [self::$instance, 'ajax_resend_invite']);
            add_action('wp_ajax_nopriv_orabooks_resend_invite', [self::$instance, 'ajax_resend_invite']);
            add_action('wp_ajax_orabooks_cancel_invite', [self::$instance, 'ajax_cancel_invite']);
            add_action('wp_ajax_nopriv_orabooks_cancel_invite', [self::$instance, 'ajax_cancel_invite']);
            add_action('wp_ajax_orabooks_accept_invite', [self::$instance, 'ajax_accept_invite']);
            add_action('wp_ajax_nopriv_orabooks_accept_invite', [self::$instance, 'ajax_accept_invite_legacy_redirect']);
            add_action('wp_ajax_orabooks_preview_invite', [self::$instance, 'ajax_preview_invite']);
            add_action('wp_ajax_nopriv_orabooks_preview_invite', [self::$instance, 'ajax_preview_invite']);
            add_action('wp_ajax_orabooks_transfer_ownership', [self::$instance, 'ajax_transfer_ownership']);
            add_action('orabooks_team_cleanup_expired_invites', [self::class, 'cleanup_expired_invites']);
        }
        return self::$instance;
    }
    
    private static function is_invite_role($role) {
        return in_array($role, ['admin', 'approver', 'staff', 'viewer'], true);
    }
    
    private static function is_member_role($role) {
        return in_array($role, ['owner', 'admin', 'approver', 'staff', 'viewer'], true);
    }

    private static function build_invite_link($raw_token) {
        return orabooks_get_accept_invite_url($raw_token);
    }

    /**
     * Send team invitation email (SL-014).
     *
     * @return bool True when wp_mail succeeds or mail is unavailable in CLI/tests.
     */
    private static function send_invite_email($email, $org_name, $role, $raw_token) {
        if (!function_exists('wp_mail')) {
            return true;
        }

        $invite_link = self::build_invite_link($raw_token);
        $site_name = function_exists('get_bloginfo') ? get_bloginfo('name') : 'OraBooks';
        if ($site_name === '') {
            $site_name = 'OraBooks';
        }

        $subject = sprintf('[%s] You have been invited to join %s', $site_name, $org_name);
        $message = sprintf(
            "You have been invited to join %s on %s as %s.\n\nAccept your invitation using this secure link (valid for 7 days):\n%s\n\nIf you did not expect this invitation, you can ignore this email.",
            $org_name,
            $site_name,
            $role,
            $invite_link
        );

        $from_email = function_exists('get_option') ? get_option('admin_email') : '';
        if (function_exists('is_multisite') && is_multisite() && function_exists('get_site_option')) {
            $network_email = get_site_option('admin_email');
            if (!empty($network_email) && is_email($network_email)) {
                $from_email = $network_email;
            }
        }
        if (empty($from_email) || !is_email($from_email)) {
            $from_email = 'wordpress@' . (isset($_SERVER['HTTP_HOST']) ? preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']) : 'localhost');
        }

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            sprintf('From: %s <%s>', $site_name, $from_email),
        ];

        $content_type_filter = static function () {
            return 'text/plain';
        };
        add_filter('wp_mail_content_type', $content_type_filter);
        $sent = wp_mail($email, $subject, $message, $headers);
        remove_filter('wp_mail_content_type', $content_type_filter);

        if (!$sent) {
            orabooks_log_event('invite_email_failed', "Failed to send invite email to {$email}", 'warning', [
                'org_name' => $org_name,
                'role' => $role,
            ]);
        }

        return (bool) $sent;
    }

    /**
     * Preview a pending invite without accepting it (SL-014).
     */
    public static function preview_invite($raw_token) {
        global $wpdb;

        $raw_token = sanitize_text_field((string) $raw_token);
        if ($raw_token === '') {
            return new WP_Error('invalid_invite', 'Invalid or expired invitation');
        }

        $table_invites = OraBooks_Database::table('org_invites');
        $token_hash = orabooks_hash_token($raw_token);

        $invite = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_invites} WHERE token_hash = %s AND used = 0 AND expires_at > NOW()",
            $token_hash
        ));

        if (!$invite) {
            return new WP_Error('invalid_invite', 'Invalid or expired invitation');
        }

        $org = OraBooks_Organization::get((int) $invite->org_id);

        return [
            'email' => $invite->email,
            'role' => $invite->role,
            'org_id' => (int) $invite->org_id,
            'org_name' => $org ? $org->name : '',
            'expires_at' => $invite->expires_at,
        ];
    }
    
    public static function invite_user($org_id, $email, $role, $invited_by) {
        global $wpdb;
        
        $email = sanitize_email($email);
        $role = sanitize_text_field($role);
        
        if (!is_email($email)) {
            return new WP_Error('invalid_email', 'Invalid email address');
        }
        
        if (!self::is_invite_role($role)) {
            return new WP_Error('invalid_role', 'Invalid invite role');
        }
        
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

        $email_sent = self::send_invite_email($email, $org->name, $role, $raw_token);
        
        return [
            'invite_id' => $invite_id,
            'invite_link' => self::build_invite_link($raw_token),
            'email_sent' => $email_sent,
        ];
    }
    
    public static function accept_invite($raw_token, $expected_user_id = 0) {
        global $wpdb;
        
        $table_invites = OraBooks_Database::table('org_invites');
        $table_users = OraBooks_Database::table('users');
        $table_user_org = OraBooks_Database::table('user_org');
        
        $token_hash = orabooks_hash_token($raw_token);
        $expected_user_id = (int) $expected_user_id;

        $wpdb->query('START TRANSACTION');

        $invite = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_invites} WHERE token_hash = %s AND used = 0 AND expires_at > NOW() FOR UPDATE",
            $token_hash
        ));
        
        if (!$invite) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('invalid_invite', 'Invalid or expired invitation');
        }
        
        if (!self::is_invite_role($invite->role)) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('invalid_role', 'Invalid invite role');
        }
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_users} WHERE email = %s", $invite->email
        ));
        
        if (!$user) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('user_not_found', 'Please create an account first before accepting the invite');
        }

        if ($expected_user_id > 0 && (int) $user->id !== $expected_user_id) {
            $wpdb->query('ROLLBACK');
            return new WP_Error(
                'invite_email_mismatch',
                'This invitation was sent to a different email address. Please log in with the invited account.'
            );
        }
        
        // Check existing membership
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$table_user_org} WHERE user_id = %d AND org_id = %d",
            $user->id, $invite->org_id
        ));
        
        if ($existing) {
            $wpdb->update($table_invites, ['used' => 1], ['id' => $invite->id], ['%d'], ['%d']);
            $wpdb->query('COMMIT');
            return new WP_Error('already_member', 'You are already a member of this organization');
        }
        
        $wpdb->insert(
            $table_user_org,
            ['user_id' => $user->id, 'org_id' => $invite->org_id, 'role' => $invite->role],
            ['%d', '%d', '%s']
        );
        
        if (!$user->org_id) {
            $wpdb->update($table_users, ['org_id' => $invite->org_id], ['id' => $user->id], ['%d'], ['%d']);
        }
        
        $wpdb->update(
            $table_invites,
            ['used' => 1, 'accepted_at' => current_time('mysql')],
            ['id' => $invite->id],
            ['%d', '%s'],
            ['%d']
        );

        $wpdb->query('COMMIT');
        
        orabooks_log_event('invite_accepted', "User {$user->email} accepted invite to org {$invite->org_id}", 'info', [
            'role' => $invite->role
        ], $user->id, $invite->org_id);
        
        return [
            'org_id' => (int) $invite->org_id,
            'role' => $invite->role,
            'user_id' => (int) $user->id,
        ];
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

        if (!self::is_member_role($new_role)) {
            return new WP_Error('invalid_role', 'Invalid role');
        }
        
        $wpdb->update(
            $table_user_org,
            ['role' => $new_role],
            ['user_id' => $target_user_id, 'org_id' => $org_id],
            ['%s'],
            ['%d', '%d']
        );
        
        // Revoke refresh tokens for this user in this org (SL-003 permission cache invalidation).
        OraBooks_Auth::revoke_user_tokens($target_user_id, $org_id);

        if (class_exists('OBN_Access_Control')) {
            OBN_Access_Control::log_role_change($org_id, $target_user_id, $changed_by, $current_role, $new_role);
        } else {
            orabooks_log_event('user_role_changed', "User $target_user_id role changed from $current_role to $new_role", 'info', [
                'old_role' => $current_role,
                'new_role' => $new_role
            ], $changed_by, $org_id);
        }
        
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

    public static function list_members($org_id) {
        global $wpdb;

        $table = OraBooks_Database::table('user_org');
        $table_users = OraBooks_Database::table('users');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.id, u.email, uo.role, uo.joined_at, 'active' as status
             FROM {$table} uo
             JOIN {$table_users} u ON uo.user_id = u.id
             WHERE uo.org_id = %d
             ORDER BY FIELD(uo.role, 'owner', 'admin', 'approver', 'staff', 'viewer'), u.email",
            intval($org_id)
        ));
    }

    public static function list_pending_invites($org_id) {
        global $wpdb;

        $table = OraBooks_Database::table('org_invites');
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, email, role, created_at, expires_at FROM {$table} WHERE org_id = %d AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC",
            intval($org_id)
        ));
    }

    public static function get_team_stats($org_id) {
        $members = self::list_members($org_id) ?: [];
        $invites = self::list_pending_invites($org_id) ?: [];

        $by_role = [
            'owner' => 0,
            'admin' => 0,
            'approver' => 0,
            'staff' => 0,
            'viewer' => 0,
        ];

        foreach ($members as $member) {
            if (isset($by_role[$member->role])) {
                $by_role[$member->role]++;
            }
        }

        return [
            'total_members' => count($members),
            'pending_invites' => count($invites),
            'by_role' => $by_role,
        ];
    }

    public static function format_member($member) {
        return [
            'id' => (int) $member->id,
            'email' => $member->email,
            'role' => $member->role,
            'joined_at' => $member->joined_at,
            'status' => $member->status ?? 'active',
        ];
    }

    public static function format_invite($invite) {
        return [
            'id' => (int) $invite->id,
            'email' => $invite->email,
            'role' => $invite->role,
            'created_at' => $invite->created_at,
            'expires_at' => $invite->expires_at,
        ];
    }
    
    // AJAX handlers
    private function current_user_id() {
        return orabooks_get_current_user_id();
    }

    private function require_org_member_access($user_id, $org_id) {
        global $wpdb;

        if (!$user_id) {
            orabooks_json_error('Not authenticated', 401);
        }

        $org_id = intval($org_id);
        if ($org_id <= 0) {
            orabooks_json_error('Organization is required', 400);
        }

        $org = OraBooks_Organization::get($org_id);
        if (!$org) {
            orabooks_json_error('Organization not found', 404);
        }

        if ($org->status !== 'active') {
            orabooks_json_error('Your organization is not active. Please contact support.', 403);
        }

        $table_user_org = OraBooks_Database::table('user_org');
        $membership = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$table_user_org} WHERE user_id = %d AND org_id = %d",
            intval($user_id),
            $org_id
        ));

        if (!$membership && !current_user_can('manage_options')) {
            orabooks_json_error('You are not a member of this organization', 403);
        }
    }
    
    public function ajax_invite_user() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $email = sanitize_email($_POST['email'] ?? '');
        $role = sanitize_text_field($_POST['role'] ?? 'staff');

        $this->require_org_member_access($user_id, $org_id);
        
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_employees')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $result = self::invite_user($org_id, $email, $role, $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success($result, 'Invitation sent');
    }
    
    public function ajax_list_members() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? $_GET['org_id'] ?? 0);

        $this->require_org_member_access($user_id, $org_id);

        $members = self::list_members($org_id);
        orabooks_json_success([
            'members' => array_map([self::class, 'format_member'], $members ?: []),
        ]);
    }
    
    public function ajax_update_role() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $target_user_id = intval($_POST['user_id'] ?? 0);
        $new_role = sanitize_text_field($_POST['role'] ?? '');

        $this->require_org_member_access($user_id, $org_id);
        
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_roles')) {
            orabooks_json_error('Permission denied', 403);
        }
        
        $result = self::update_role($org_id, $target_user_id, $new_role, $user_id);
        if (is_wp_error($result)) {
            orabooks_json_error($result->get_error_message(), 400);
        }
        orabooks_json_success([], 'Role updated');
    }
    
    public function ajax_remove_user() {
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? 0);
        $target_user_id = intval($_POST['user_id'] ?? 0);

        $this->require_org_member_access($user_id, $org_id);
        
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
        $user_id = $this->current_user_id();
        $org_id = intval($_POST['org_id'] ?? $_GET['org_id'] ?? 0);

        $this->require_org_member_access($user_id, $org_id);

        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_employees')) {
            orabooks_json_error('Permission denied', 403);
        }

        $invites = self::list_pending_invites($org_id);
        orabooks_json_success([
            'invites' => array_map([self::class, 'format_invite'], $invites ?: []),
        ]);
    }
    
    public function ajax_resend_invite() {
        global $wpdb;
        
        $invite_id = intval($_POST['invite_id'] ?? 0);
        $org_id = intval($_POST['org_id'] ?? 0);
        $user_id = $this->current_user_id();

        $this->require_org_member_access($user_id, $org_id);
        
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_employees')) {
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

        $org = OraBooks_Organization::get($org_id);
        $email_sent = self::send_invite_email(
            $invite->email,
            $org ? $org->name : 'Organization',
            $invite->role,
            $raw_token
        );

        orabooks_json_success([
            'invite_link' => self::build_invite_link($raw_token),
            'email_sent' => $email_sent,
        ], 'Invitation resent');
    }
    
    public function ajax_cancel_invite() {
        global $wpdb;
        
        $invite_id = intval($_POST['invite_id'] ?? 0);
        $org_id = intval($_POST['org_id'] ?? 0);
        $user_id = $this->current_user_id();

        $this->require_org_member_access($user_id, $org_id);
        
        if (!OraBooks_RBAC::require_permission($user_id, $org_id, 'manage_employees')) {
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
        $user_id = orabooks_get_current_user_id();
        if (!$user_id) {
            orabooks_json_error('Please log in to accept this invitation.', 401);
        }

        $token = sanitize_text_field($_POST['token'] ?? '');
        if ($token === '') {
            orabooks_json_error('Invitation token is required.', 400);
        }

        $result = self::accept_invite($token, $user_id);
        if (is_wp_error($result)) {
            $status = $result->get_error_code() === 'invite_email_mismatch' ? 403 : 400;
            orabooks_json_error($result->get_error_message(), $status);
        }

        if (!class_exists('OraBooks_Organization') || !class_exists('OraBooks_Auth')) {
            orabooks_json_error('Team invite service is unavailable.', 503);
        }

        $org = OraBooks_Organization::get((int) $result['org_id']);
        if (!$org || empty($org->subdomain)) {
            orabooks_json_error('Organization is not available yet.', 400);
        }

        $session = OraBooks_Auth::issue_auth_session(
            (int) $result['user_id'],
            (int) $result['org_id'],
            $result['role'],
            '/team/'
        );

        if (is_wp_error($session)) {
            orabooks_json_error($session->get_error_message(), 400);
        }

        $session = orabooks_enrich_login_response($session);
        orabooks_json_success(orabooks_redact_client_auth_response($session), 'Invitation accepted');
    }

    /**
     * Legacy admin-ajax invite links redirect to the React accept-invite page.
     */
    public function ajax_accept_invite_legacy_redirect() {
        $token = isset($_REQUEST['token']) ? sanitize_text_field(wp_unslash($_REQUEST['token'])) : '';
        $destination = orabooks_get_accept_invite_url($token);
        wp_redirect($destination);
        exit;
    }

    public function ajax_preview_invite() {
        $token = sanitize_text_field($_REQUEST['token'] ?? $_POST['token'] ?? '');
        $preview = self::preview_invite($token);
        if (is_wp_error($preview)) {
            orabooks_json_error($preview->get_error_message(), 400);
        }
        orabooks_json_success($preview);
    }

    /**
     * Reserved MVP endpoint — ownership transfer (SL-014 §5.10).
     */
    public function ajax_transfer_ownership() {
        orabooks_json_error('Ownership transfer is not implemented in MVP.', 501);
    }

    /**
     * Delete expired invite rows (optional nightly cleanup, SL-014 §5.11).
     */
    public static function cleanup_expired_invites() {
        global $wpdb;

        $table = OraBooks_Database::table('org_invites');
        $deleted = $wpdb->query("DELETE FROM {$table} WHERE expires_at < NOW()");

        return is_numeric($deleted) ? (int) $deleted : 0;
    }
}