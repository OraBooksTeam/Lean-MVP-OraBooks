<?php
/**
 * SL-014 – Organizations / Users / Teams
 *
 * Manages user membership, roles, team invites, and team groupings within an organization (tenant).
 * Build Order: SL-004 → SL-013 → SL-003 → SL-014
 *
 * Key features:
 * - user_org table: user membership in orgs with role
 * - org_invites table: invite tokens (SHA-256 hash only), 7-day expiry
 * - teams table: team groupings within an org (reserved for future team-based permissions)
 * - team_members table: membership within teams (reserved for future)
 * - Roles: owner, admin, approver, staff, viewer
 * - Multi-org membership doctrine
 * - Partner org multi-user support (agency/reseller/strategic_partner)
 * - Invite workflow: send, accept, resend, cancel
 * - Role management: update, remove, list
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Users_Teams {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Valid roles enum
     */
    const ROLES = array('owner', 'admin', 'approver', 'staff', 'viewer');

    /**
     * Roles that can be assigned via invite (owner is special, assigned at org creation)
     */
    const INVITE_ROLES = array('admin', 'approver', 'staff', 'viewer');

    /**
     * Invite token expiry in days
     */
    const INVITE_EXPIRY_DAYS = 7;

    /**
     * Max invites per minute per org
     */
    const INVITE_RATE_LIMIT = 10;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init_table_names'));
        add_action('init', array($this, 'register_rewrite_rules'));
    }

    /**
     * SL-014: Initialize table names for multisite.
     */
    public function init_table_names() {
        global $wpdb;

        $prefix = is_multisite() ? $wpdb->base_prefix : $wpdb->prefix;
        $wpdb->orabooks_user_org = $prefix . 'orabooks_user_org';
        $wpdb->orabooks_org_invites = $prefix . 'orabooks_org_invites';
        $wpdb->orabooks_teams = $prefix . 'orabooks_teams';
        $wpdb->orabooks_team_members = $prefix . 'orabooks_team_members';
    }

    /**
     * Register rewrite rules.
     */
    public function register_rewrite_rules() {
        // API endpoints for team management (future)
    }

    /**
     * SL-014 §5.1: Create tables (run during activation).
     * Includes reserved future tables: teams and team_members.
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // user_org table
        $user_org_table = $wpdb->base_prefix . 'orabooks_user_org';
        $sql = "CREATE TABLE IF NOT EXISTS {$user_org_table} (
            user_id INT NOT NULL,
            org_id INT NOT NULL,
            role ENUM('owner','admin','approver','staff','viewer') NOT NULL,
            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, org_id),
            INDEX idx_org (org_id),
            INDEX idx_role (role)
        ) {$charset_collate};";
        dbDelta($sql);

        // org_invites table
        $invites_table = $wpdb->base_prefix . 'orabooks_org_invites';
        $sql_invites = "CREATE TABLE IF NOT EXISTS {$invites_table} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            org_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            role ENUM('admin','approver','staff','viewer') NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            used BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            accepted_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            INDEX idx_token_hash (token_hash),
            INDEX idx_org_email (org_id, email),
            INDEX idx_expires (expires_at)
        ) {$charset_collate};";
        dbDelta($sql_invites);

        // ================================================================
        // FUTURE EXPANSION: Teams and Team Members tables
        // These are RESERVED for future SL (team-based permissions/groups).
        // Schema is created now to avoid migration later.
        // Reference: SL-014 §10 Future Expansion Note, lines 1032-1034, 1294
        // ================================================================
        
        // teams table: team groupings within an org
        $teams_table = $wpdb->base_prefix . 'orabooks_teams';
        $sql_teams = "CREATE TABLE IF NOT EXISTS {$teams_table} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            org_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$wpdb->base_prefix}orabooks_organizations(id),
            INDEX idx_org (org_id),
            UNIQUE KEY uk_team_name_per_org (org_id, name)
        ) {$charset_collate};";
        dbDelta($sql_teams);

        // team_members table: which users belong to which team with role
        $team_members_table = $wpdb->base_prefix . 'orabooks_team_members';
        $sql_team_members = "CREATE TABLE IF NOT EXISTS {$team_members_table} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            team_id BIGINT NOT NULL,
            user_id INT NOT NULL,
            role ENUM('lead','member') DEFAULT 'member',
            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (team_id) REFERENCES {$teams_table}(id) ON DELETE CASCADE,
            INDEX idx_team (team_id),
            INDEX idx_user (user_id),
            UNIQUE KEY uk_team_user (team_id, user_id)
        ) {$charset_collate};";
        dbDelta($sql_team_members);

        error_log('[OraBooks SL-014] User/org, invites, teams, and team_members tables created/verified.');
    }

    // ================================================================
    // USER-ORG MANAGEMENT
    // ================================================================

    /**
     * SL-014: Add a user as owner of an org (called at org creation).
     *
     * @param int $user_id User ID
     * @param int $org_id  Organization ID
     * @return true|WP_Error
     */
    public function add_owner($user_id, $org_id) {
        return $this->add_user_to_org($user_id, $org_id, 'owner');
    }

    /**
     * SL-014: Add a user to an organization with a specific role.
     *
     * @param int    $user_id User ID
     * @param int    $org_id  Organization ID
     * @param string $role    Role name
     * @return true|WP_Error
     */
    public function add_user_to_org($user_id, $org_id, $role) {
        global $wpdb;

        if (!in_array($role, self::ROLES, true)) {
            return new WP_Error('invalid_role', __('Invalid role.', 'orabooks'));
        }

        $table = $wpdb->base_prefix . 'orabooks_user_org';

        // Check if membership already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE user_id = %d AND org_id = %d",
            $user_id,
            $org_id
        ));

        if ($existing) {
            return new WP_Error('already_member', __('User is already a member of this organization.', 'orabooks'));
        }

        // Only one owner per org
        if ($role === 'owner') {
            $existing_owner = $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM {$table} WHERE org_id = %d AND role = 'owner'",
                $org_id
            ));
            if ($existing_owner) {
                return new WP_Error('owner_exists', __('Organization already has an owner. Transfer ownership to change.', 'orabooks'));
            }
        }

        $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'org_id'  => $org_id,
                'role'    => $role,
                'joined_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s')
        );

        if ($wpdb->last_error) {
            return new WP_Error('db_error', $wpdb->last_error);
        }

        return true;
    }

    /**
     * SL-014 §5.2: Invite a user to join an organization.
     *
     * @param int    $org_id     Organization ID
     * @param string $email      Email of the user to invite
     * @param string $role       Role to assign (admin/approver/staff/viewer)
     * @param int    $invited_by User ID of the person sending the invite
     * @return array|WP_Error
     */
    public function invite_user($org_id, $email, $role, $invited_by) {
        global $wpdb;

        // Validate role (must be invite-able role, not owner)
        if (!in_array($role, self::INVITE_ROLES, true)) {
            return new WP_Error('invalid_role', __('Role must be one of: admin, approver, staff, viewer.', 'orabooks'));
        }

        $email = sanitize_email($email);
        if (!is_email($email)) {
            return new WP_Error('invalid_email', __('Invalid email address.', 'orabooks'));
        }

        $table = $wpdb->base_prefix . 'orabooks_user_org';
        $invites_table = $wpdb->base_prefix . 'orabooks_org_invites';

        // Check if user already has active membership in this org
        $user = get_user_by('email', $email);
        if ($user) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM {$table} WHERE user_id = %d AND org_id = %d",
                $user->ID,
                $org_id
            ));
            if ($existing) {
                return new WP_Error('already_member', __('User already in organization.', 'orabooks'));
            }
        }

        // Rate limit check: 10 invites per minute per org
        $recent_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$invites_table} 
             WHERE org_id = %d AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            $org_id
        ));
        if ($recent_count >= self::INVITE_RATE_LIMIT) {
            return new WP_Error('rate_limit', __('Invite rate limit exceeded. Try again later.', 'orabooks'));
        }

        // Cancel any existing pending invite for this email+org
        $wpdb->delete(
            $invites_table,
            array('org_id' => $org_id, 'email'  => $email, 'used'   => 0),
            array('%d', '%s', '%d')
        );

        // Generate secure token: raw 32 bytes → hex (64 chars)
        $raw_token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $raw_token);
        $expires_at = date('Y-m-d H:i:s', time() + self::INVITE_EXPIRY_DAYS * DAY_IN_SECONDS);

        $wpdb->insert(
            $invites_table,
            array(
                'org_id'     => $org_id,
                'email'      => $email,
                'role'       => $role,
                'token_hash' => $token_hash,
                'expires_at' => $expires_at,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );

        if ($wpdb->last_error) {
            return new WP_Error('db_error', $wpdb->last_error);
        }

        $invite_id = $wpdb->insert_id;

        // Audit event
        do_action('orabooks_security_event', 'invite_sent', array(
            'org_id'        => $org_id,
            'invited_email' => $email,
            'role'          => $role,
            'invited_by'    => $invited_by,
            'invite_id'     => $invite_id,
        ));

        error_log(sprintf(
            '[OraBooks SL-014] Invite sent: org=%d, email=%s, role=%s, token=[REDACTED]',
            $org_id,
            $email,
            $role
        ));

        return array(
            'message'   => __('Invitation sent.', 'orabooks'),
            'invite_id' => $invite_id,
            'raw_token' => $raw_token,
        );
    }

    /**
     * SL-014 §5.3: Accept an invite using the raw token.
     *
     * @param string $raw_token The raw invite token from the email link
     * @return array|WP_Error
     */
    public function accept_invite($raw_token) {
        global $wpdb;

        $token_hash = hash('sha256', $raw_token);
        $invites_table = $wpdb->base_prefix . 'orabooks_org_invites';

        // Look up the invite
        $invite = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$invites_table} 
             WHERE token_hash = %s AND used = 0 AND expires_at > NOW()",
            $token_hash
        ));

        if (!$invite) {
            return new WP_Error('invalid_token', __('Invalid or expired invitation.', 'orabooks'));
        }

        $user = get_user_by('email', $invite->email);

        if (!$user) {
            // User doesn't exist - return invite data for signup redirect
            return array(
                'requires_signup' => true,
                'email'           => $invite->email,
                'role'            => $invite->role,
                'org_id'          => $invite->org_id,
                'invite_id'       => $invite->id,
                'token_hash'      => $token_hash,
                'message'         => __('Please sign up to accept the invitation.', 'orabooks'),
            );
        }

        // User exists - check if already a member
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$wpdb->base_prefix}orabooks_user_org WHERE user_id = %d AND org_id = %d",
            $user->ID,
            $invite->org_id
        ));

        if ($existing) {
            return new WP_Error('already_member', __('You are already a member of this organization.', 'orabooks'));
        }

        // ── Wrap add-user + mark-invite in a transaction for atomicity ──────
        $wpdb->query('START TRANSACTION');

        $result = $this->add_user_to_org($user->ID, $invite->org_id, $invite->role);
        if (is_wp_error($result)) {
            $wpdb->query('ROLLBACK');
            return $result;
        }

        $updated = $wpdb->update(
            $invites_table,
            array('used' => 1, 'accepted_at' => current_time('mysql')),
            array('id' => $invite->id),
            array('%d', '%s'),
            array('%d')
        );

        if ($updated === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', $wpdb->last_error);
        }

        $wpdb->query('COMMIT');

        // Audit event
        do_action('orabooks_security_event', 'invite_accepted', array(
            'org_id'        => $invite->org_id,
            'user_id'       => $user->ID,
            'role'          => $invite->role,
            'invited_email' => $invite->email,
        ));

        // Get org name for the success message
        $org_name = '';
        if (class_exists('OraBooks_Organizations')) {
            $org = OraBooks_Organizations::get_instance()->get_organization($invite->org_id);
            if ($org) {
                $org_name = $org->name;
            }
        }

        return array(
            'message'  => sprintf(
                __('You have been added to %s with role %s.', 'orabooks'),
                $org_name ?: "org #{$invite->org_id}",
                $invite->role
            ),
            'org_id'   => $invite->org_id,
            'user_id'  => $user->ID,
            'role'     => $invite->role,
            'accepted' => true,
        );
    }

    /**
     * SL-014: Complete invite acceptance after user signup.
     * Called by SL-013 authentication after a new user signs up via invite link.
     *
     * @param int    $user_id     New user ID
     * @param string $token_hash  SHA-256 hash of the invite token
     * @return true|WP_Error
     */
    public function complete_invite_after_signup($user_id, $token_hash) {
        global $wpdb;

        $invites_table = $wpdb->base_prefix . 'orabooks_org_invites';
        $invite = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$invites_table} WHERE token_hash = %s AND used = 0 AND expires_at > NOW()",
            $token_hash
        ));

        if (!$invite) {
            return new WP_Error('invalid_token', __('Invalid or expired invitation.', 'orabooks'));
        }

        $user = get_userdata($user_id);
        if (!$user || $user->user_email !== $invite->email) {
            return new WP_Error('email_mismatch', __('Email does not match the invitation.', 'orabooks'));
        }

        $result = $this->add_user_to_org($user_id, $invite->org_id, $invite->role);
        if (is_wp_error($result)) {
            return $result;
        }

        $wpdb->update(
            $invites_table,
            array('used' => 1, 'accepted_at' => current_time('mysql')),
            array('id' => $invite->id),
            array('%d', '%s'),
            array('%d')
        );

        do_action('orabooks_security_event', 'invite_accepted', array(
            'org_id'        => $invite->org_id,
            'user_id'       => $user_id,
            'role'          => $invite->role,
            'invited_email' => $invite->email,
        ));

        return true;
    }

    /**
     * SL-014 §5.4: Update a user's role in an organization.
     *
     * @param int    $org_id     Organization ID
     * @param int    $user_id    Target user ID
     * @param string $new_role   New role
     * @param int    $changed_by User ID making the change (must be owner)
     * @return true|WP_Error
     */
    public function update_user_role($org_id, $user_id, $new_role, $changed_by) {
        global $wpdb;

        $table = $wpdb->base_prefix . 'orabooks_user_org';

        // Validate role
        if (!in_array($new_role, self::ROLES, true)) {
            return new WP_Error('invalid_role', __('Invalid role.', 'orabooks'));
        }

        // Cannot assign owner via this endpoint
        if ($new_role === 'owner') {
            return new WP_Error('cannot_assign_owner', __('Use transfer ownership for owner role changes.', 'orabooks'));
        }

        // Cannot change own role
        if ((int)$user_id === (int)$changed_by) {
            return new WP_Error('cannot_change_self', __('Cannot change your own role.', 'orabooks'));
        }

        // Get current membership
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND org_id = %d",
            $user_id,
            $org_id
        ));

        if (!$membership) {
            return new WP_Error('not_member', __('User is not a member of this organization.', 'orabooks'));
        }

        $old_role = $membership->role;

        // Cannot remove the last owner
        if ($old_role === 'owner') {
            $owner_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE org_id = %d AND role = 'owner'",
                $org_id
            ));
            if ($owner_count <= 1) {
                return new WP_Error('last_owner', __('Cannot change role of the last owner.', 'orabooks'));
            }
        }

        $wpdb->update(
            $table,
            array('role' => $new_role),
            array('user_id' => $user_id, 'org_id' => $org_id),
            array('%s'),
            array('%d', '%d')
        );

        // Audit event
        do_action('orabooks_security_event', 'user_role_changed', array(
            'org_id'     => $org_id,
            'user_id'    => $user_id,
            'old_role'   => $old_role,
            'new_role'   => $new_role,
            'changed_by' => $changed_by,
        ));

        // Revoke sessions
        do_action('orabooks_revoke_user_sessions', $user_id, $org_id);

        return true;
    }

    /**
     * SL-014 §5.5: Remove a user from an organization.
     *
     * @param int $org_id      Organization ID
     * @param int $user_id     Target user ID
     * @param int $removed_by  User ID performing the removal
     * @return true|WP_Error
     */
    public function remove_user($org_id, $user_id, $removed_by) {
        global $wpdb;

        $table = $wpdb->base_prefix . 'orabooks_user_org';

        // Cannot remove self
        if ((int)$user_id === (int)$removed_by) {
            return new WP_Error('cannot_remove_self', __('Cannot remove yourself. Transfer ownership first.', 'orabooks'));
        }

        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND org_id = %d",
            $user_id,
            $org_id
        ));

        if (!$membership) {
            return new WP_Error('not_member', __('User is not a member of this organization.', 'orabooks'));
        }

        // Cannot remove the last owner
        if ($membership->role === 'owner') {
            $owner_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE org_id = %d AND role = 'owner'",
                $org_id
            ));
            if ($owner_count <= 1) {
                return new WP_Error('last_owner', __('Cannot remove the last owner.', 'orabooks'));
            }
        }

        $wpdb->delete(
            $table,
            array('user_id' => $user_id, 'org_id' => $org_id),
            array('%d', '%d')
        );

        // Audit event
        do_action('orabooks_security_event', 'user_removed', array(
            'org_id'          => $org_id,
            'removed_user_id' => $user_id,
            'removed_by'      => $removed_by,
        ));

        // Revoke all sessions for this user in this org
        do_action('orabooks_revoke_user_sessions', $user_id, $org_id);

        return true;
    }

    /**
     * SL-014 §5.6: Resend an invite (generates new token, old one invalidated).
     *
     * @param int $invite_id  Invite ID
     * @param int $org_id     Organization ID
     * @return array|WP_Error
     */
    public function resend_invite($invite_id, $org_id) {
        global $wpdb;

        $invites_table = $wpdb->base_prefix . 'orabooks_org_invites';
        $invite = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$invites_table} WHERE id = %d AND org_id = %d",
            $invite_id,
            $org_id
        ));

        if (!$invite) {
            return new WP_Error('invite_not_found', __('Invite not found.', 'orabooks'));
        }

        if ($invite->used) {
            return new WP_Error('already_used', __('Invite has already been used.', 'orabooks'));
        }

        // Generate new token
        $raw_token = bin2hex(random_bytes(32));
        $new_hash = hash('sha256', $raw_token);
        $new_expires = date('Y-m-d H:i:s', time() + self::INVITE_EXPIRY_DAYS * DAY_IN_SECONDS);

        $wpdb->update(
            $invites_table,
            array(
                'token_hash' => $new_hash,
                'expires_at' => $new_expires,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $invite_id),
            array('%s', '%s', '%s'),
            array('%d')
        );

        do_action('orabooks_security_event', 'invite_resent', array(
            'org_id'        => $org_id,
            'invite_id'     => $invite_id,
            'invited_email' => $invite->email,
        ));

        return array(
            'message'   => __('Invitation resent.', 'orabooks'),
            'raw_token' => $raw_token,
        );
    }

    /**
     * SL-014 §5.7: Cancel a pending invite.
     *
     * @param int $invite_id Invite ID
     * @param int $org_id    Organization ID
     * @return true|WP_Error
     */
    public function cancel_invite($invite_id, $org_id) {
        global $wpdb;

        $invites_table = $wpdb->base_prefix . 'orabooks_org_invites';
        $invite = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$invites_table} WHERE id = %d AND org_id = %d",
            $invite_id,
            $org_id
        ));

        if (!$invite) {
            return new WP_Error('invite_not_found', __('Invite not found.', 'orabooks'));
        }

        $wpdb->delete(
            $invites_table,
            array('id' => $invite_id, 'org_id' => $org_id),
            array('%d', '%d')
        );

        do_action('orabooks_security_event', 'invite_cancelled', array(
            'org_id'        => $org_id,
            'invite_id'     => $invite_id,
            'invited_email' => $invite->email,
        ));

        return true;
    }

    /**
     * SL-014 §5.8: List team members of an organization.
     *
     * @param int $org_id Organization ID
     * @return array Array of member objects
     */
    public function list_team_members($org_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID as id, u.user_email as email, u.display_name as name, 
                    uo.role, 'active' as status
             FROM {$wpdb->base_prefix}orabooks_user_org uo
             JOIN {$wpdb->users} u ON uo.user_id = u.ID
             WHERE uo.org_id = %d
             ORDER BY FIELD(uo.role, 'owner', 'admin', 'approver', 'staff', 'viewer'), u.display_name",
            $org_id
        ));
    }

    /**
     * SL-014 §5.9: List pending invites for an organization.
     *
     * @param int $org_id Organization ID
     * @return array Array of pending invite objects
     */
    public function list_pending_invites($org_id) {
        global $wpdb;

        $invites_table = $wpdb->base_prefix . 'orabooks_org_invites';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, email, role, created_at, expires_at
             FROM {$invites_table}
             WHERE org_id = %d AND used = 0 AND expires_at > NOW()
             ORDER BY created_at DESC",
            $org_id
        ));
    }

    /**
     * SL-014: Get a user's role in an organization.
     */
    public function get_user_role($user_id, $org_id) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT role FROM {$wpdb->base_prefix}orabooks_user_org WHERE user_id = %d AND org_id = %d",
            $user_id,
            $org_id
        ));
    }

    /**
     * SL-014: Get all organizations a user belongs to.
     */
    public function get_user_organizations($user_id) {
        global $wpdb;

        $org_table = $wpdb->base_prefix . 'orabooks_organizations';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT uo.org_id, uo.role, uo.joined_at, o.name as org_name, o.subdomain, 
                    o.organization_type, o.tier, o.status
             FROM {$wpdb->base_prefix}orabooks_user_org uo
             LEFT JOIN {$org_table} o ON uo.org_id = o.id
             WHERE uo.user_id = %d
             ORDER BY o.name",
            $user_id
        ));
    }

    /**
     * SL-014: Simplified RBAC permission check.
     */
    public function has_permission($user_id, $org_id, $action) {
        $role = $this->get_user_role($user_id, $org_id);
        if (!$role) {
            return false;
        }

        $permissions = array(
            'owner' => array(
                'invite_user', 'change_role', 'remove_user', 'view_team',
                'view_pending_invites', 'resend_invite', 'cancel_invite',
                'transfer_ownership', 'manage_org', 'view_all',
                'manage_teams', 'manage_team_members',
            ),
            'admin' => array(
                'invite_user', 'view_team', 'view_pending_invites',
                'resend_invite', 'cancel_invite',
            ),
            'approver' => array(
                'view_team', 'approve_journals',
            ),
            'staff' => array(
                'view_team', 'enter_transactions',
            ),
            'viewer' => array(
                'view_team',
            ),
        );

        if (!isset($permissions[$role])) {
            return false;
        }

        return in_array($action, $permissions[$role], true);
    }

    /**
     * SL-014 §5.10: Transfer ownership (reserved - returns 501 in MVP).
     */
    public function transfer_ownership($org_id, $new_owner_user_id, $current_owner_id) {
        return new WP_Error('not_implemented', __('Ownership transfer not implemented in MVP.', 'orabooks'));
    }

    /**
     * SL-014 §5.11: Clean up expired invites.
     */
    public function cleanup_expired_invites() {
        global $wpdb;

        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->base_prefix}orabooks_org_invites WHERE expires_at < NOW()"
        );

        if ($deleted > 0) {
            error_log('[OraBooks SL-014] Cleaned up ' . $deleted . ' expired invites.');
        }

        return $deleted;
    }

    /**
     * SL-014: Get user's profile information within an org context.
     */
    public function get_user_profile($user_id, $org_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $role = $this->get_user_role($user_id, $org_id);
        $orgs = $this->get_user_organizations($user_id);

        $profile = array(
            'id'          => $user->ID,
            'email'       => $user->user_email,
            'name'        => $user->display_name,
            'role'        => $role,
            'orgs'        => $orgs,
            'is_partner'  => false,
            'teams'       => $this->get_user_teams($user_id, $org_id),
        );

        // Check if this is a partner org
        foreach ($orgs as $org) {
            if ((int)$org->org_id === (int)$org_id && $org->organization_type === 'partner') {
                $profile['is_partner'] = true;
                $profile['badge'] = array(
                    'text'    => __('Partner Account (Commission)', 'orabooks'),
                    'tooltip' => __('You earn commissions from qualified customers attributed to your Partner Code. No accounting features.', 'orabooks'),
                );
                if (!empty($org->org_name)) {
                    $profile['organization_name'] = $org->org_name;
                }
                break;
            }
        }

        return $profile;
    }

    // ================================================================
    // TEAMS MANAGEMENT (Reserved for Future Expansion)
    // Reference: SL-014 §10 Future Expansion Note
    // Tables teams and team_members created for forward compatibility.
    // Full team-based permissions and group management coming in future SL.
    // ================================================================

    /**
     * FUTURE: Create a team within an organization.
     * Teams allow grouping users for batch role/permission assignments.
     *
     * @param int    $org_id      Organization ID
     * @param string $name        Team name
     * @param string $description Optional description
     * @return array|WP_Error
     */
    public function create_team($org_id, $name, $description = '') {
        global $wpdb;

        $teams_table = $wpdb->base_prefix . 'orabooks_teams';

        // Check for duplicate team name within org
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$teams_table} WHERE org_id = %d AND name = %s",
            $org_id,
            $name
        ));

        if ($existing) {
            return new WP_Error('team_exists', __('A team with this name already exists in this organization.', 'orabooks'));
        }

        $wpdb->insert(
            $teams_table,
            array(
                'org_id'      => $org_id,
                'name'        => sanitize_text_field($name),
                'description' => sanitize_textarea_field($description),
                'created_at'  => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s')
        );

        if ($wpdb->last_error) {
            return new WP_Error('db_error', $wpdb->last_error);
        }

        $team_id = $wpdb->insert_id;

        do_action('orabooks_security_event', 'team_created', array(
            'org_id'  => $org_id,
            'team_id' => $team_id,
            'name'    => $name,
        ));

        return array(
            'team_id' => $team_id,
            'name'    => $name,
        );
    }

    /**
     * FUTURE: List all teams in an organization.
     *
     * @param int $org_id Organization ID
     * @return array Array of team objects with member counts
     */
    public function list_teams($org_id) {
        global $wpdb;

        $teams_table = $wpdb->base_prefix . 'orabooks_teams';
        $members_table = $wpdb->base_prefix . 'orabooks_team_members';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, COUNT(tm.id) as member_count
             FROM {$teams_table} t
             LEFT JOIN {$members_table} tm ON t.id = tm.team_id
             WHERE t.org_id = %d
             GROUP BY t.id
             ORDER BY t.name",
            $org_id
        ));
    }

    /**
     * FUTURE: Get a team by ID.
     *
     * @param int $team_id Team ID
     * @return object|false
     */
    public function get_team($team_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->base_prefix}orabooks_teams WHERE id = %d",
            $team_id
        ));
    }

    /**
     * FUTURE: Update a team's name or description.
     *
     * @param int    $team_id     Team ID
     * @param string $name        New team name
     * @param string $description New description
     * @return true|WP_Error
     */
    public function update_team($team_id, $name, $description = '') {
        global $wpdb;

        $teams_table = $wpdb->base_prefix . 'orabooks_teams';
        $team = $this->get_team($team_id);
        if (!$team) {
            return new WP_Error('team_not_found', __('Team not found.', 'orabooks'));
        }

        $wpdb->update(
            $teams_table,
            array(
                'name'        => sanitize_text_field($name),
                'description' => sanitize_textarea_field($description),
                'updated_at'  => current_time('mysql'),
            ),
            array('id' => $team_id),
            array('%s', '%s', '%s'),
            array('%d')
        );

        do_action('orabooks_security_event', 'team_updated', array(
            'org_id'  => $team->org_id,
            'team_id' => $team_id,
            'name'    => $name,
        ));

        return true;
    }

    /**
     * FUTURE: Delete a team.
     * CASCADE will remove all team_members entries.
     *
     * @param int $team_id Team ID
     * @return true|WP_Error
     */
    public function delete_team($team_id) {
        global $wpdb;

        $teams_table = $wpdb->base_prefix . 'orabooks_teams';
        $team = $this->get_team($team_id);
        if (!$team) {
            return new WP_Error('team_not_found', __('Team not found.', 'orabooks'));
        }

        $wpdb->delete($teams_table, array('id' => $team_id), array('%d'));

        do_action('orabooks_security_event', 'team_deleted', array(
            'org_id'  => $team->org_id,
            'team_id' => $team_id,
            'name'    => $team->name,
        ));

        return true;
    }

    /**
     * FUTURE: Add a user to a team.
     *
     * @param int    $team_id Team ID
     * @param int    $user_id User ID
     * @param string $role    Role within team: 'lead' or 'member'
     * @return true|WP_Error
     */
    public function add_user_to_team($team_id, $user_id, $role = 'member') {
        global $wpdb;

        if (!in_array($role, array('lead', 'member'), true)) {
            return new WP_Error('invalid_team_role', __('Team role must be lead or member.', 'orabooks'));
        }

        $members_table = $wpdb->base_prefix . 'orabooks_team_members';
        $teams_table = $wpdb->base_prefix . 'orabooks_teams';

        // Verify team exists
        $team = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$teams_table} WHERE id = %d", $team_id));
        if (!$team) {
            return new WP_Error('team_not_found', __('Team not found.', 'orabooks'));
        }

        // Verify user is a member of the org
        $is_org_member = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$wpdb->base_prefix}orabooks_user_org WHERE user_id = %d AND org_id = %d",
            $user_id,
            $team->org_id
        ));
        if (!$is_org_member) {
            return new WP_Error('not_org_member', __('User is not a member of this organization.', 'orabooks'));
        }

        // Check if already in team
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$members_table} WHERE team_id = %d AND user_id = %d",
            $team_id,
            $user_id
        ));
        if ($existing) {
            return new WP_Error('already_in_team', __('User is already a member of this team.', 'orabooks'));
        }

        $wpdb->insert(
            $members_table,
            array(
                'team_id'   => $team_id,
                'user_id'   => $user_id,
                'role'      => $role,
                'joined_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s')
        );

        do_action('orabooks_security_event', 'team_member_added', array(
            'team_id' => $team_id,
            'user_id' => $user_id,
            'role'    => $role,
        ));

        return true;
    }

    /**
     * FUTURE: Remove a user from a team.
     *
     * @param int $team_id Team ID
     * @param int $user_id User ID
     * @return true|WP_Error
     */
    public function remove_user_from_team($team_id, $user_id) {
        global $wpdb;

        $members_table = $wpdb->base_prefix . 'orabooks_team_members';
        $teams_table = $wpdb->base_prefix . 'orabooks_teams';

        $team = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$teams_table} WHERE id = %d", $team_id));
        if (!$team) {
            return new WP_Error('team_not_found', __('Team not found.', 'orabooks'));
        }

        $deleted = $wpdb->delete(
            $members_table,
            array('team_id' => $team_id, 'user_id' => $user_id),
            array('%d', '%d')
        );

        if ($deleted === 0) {
            return new WP_Error('not_in_team', __('User is not a member of this team.', 'orabooks'));
        }

        do_action('orabooks_security_event', 'team_member_removed', array(
            'team_id' => $team_id,
            'user_id' => $user_id,
        ));

        return true;
    }

    /**
     * FUTURE: List members of a team.
     *
     * @param int $team_id Team ID
     * @return array Array of team member objects
     */
    public function list_team_members_by_team($team_id) {
        global $wpdb;

        $members_table = $wpdb->base_prefix . 'orabooks_team_members';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT tm.user_id, tm.role as team_role, tm.joined_at,
                    u.display_name, u.user_email
             FROM {$members_table} tm
             JOIN {$wpdb->users} u ON tm.user_id = u.ID
             WHERE tm.team_id = %d
             ORDER BY FIELD(tm.role, 'lead', 'member'), u.display_name",
            $team_id
        ));
    }

    /**
     * FUTURE: Get all teams a user belongs to within an org.
     *
     * @param int $user_id User ID
     * @param int $org_id  Organization ID
     * @return array Array of team objects
     */
    public function get_user_teams($user_id, $org_id) {
        global $wpdb;

        $teams_table = $wpdb->base_prefix . 'orabooks_teams';
        $members_table = $wpdb->base_prefix . 'orabooks_team_members';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.id, t.name, t.description, tm.role as team_role
             FROM {$members_table} tm
             JOIN {$teams_table} t ON tm.team_id = t.id
             WHERE tm.user_id = %d AND t.org_id = %d
             ORDER BY t.name",
            $user_id,
            $org_id
        ));
    }

    /**
     * FUTURE: Assign a team-level permission/role to all team members.
     * Enables batch role assignment (SL-014 §10: "একাধিক ইউজার একসাথে রোল পাবে").
     *
     * @param int    $team_id Team ID
     * @param string $org_role Role to assign in user_org for all team members
     * @return int Number of users updated
     */
    public function assign_org_role_to_team($team_id, $org_role) {
        global $wpdb;

        if (!in_array($org_role, self::ROLES, true)) {
            return new WP_Error('invalid_role', __('Invalid organization role.', 'orabooks'));
        }

        $members = $this->list_team_members_by_team($team_id);
        $updated = 0;

        foreach ($members as $member) {
            $result = $this->update_user_role(
                $this->get_team($team_id)->org_id,
                $member->user_id,
                $org_role,
                0 // system action
            );
            if (!is_wp_error($result)) {
                $updated++;
            }
        }

        return $updated;
    }
}

// Initialize
OraBooks_Users_Teams::get_instance();