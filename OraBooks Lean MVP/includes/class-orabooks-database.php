<?php
/**
 * OraBooks Database Schema & Management
 * 
 * Handles all database table creation, migration, and schema management.
 * Build Order: SL-004 Foundation Layer
 */

if (!defined('ABSPATH')) {
    exit;
}

class OraBooks_Database {
    
    private static $instance = null;
    
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function install() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // ============================================================
        // SL-004: organizations table (Foundation)
        // ============================================================
        $table_organizations = $wpdb->prefix . 'orabooks_organizations';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_organizations} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            tier ENUM('free','premium','enterprise','partner') NOT NULL,
            subdomain VARCHAR(63) UNIQUE NOT NULL,
            owner_id BIGINT UNSIGNED NOT NULL,
            region VARCHAR(20) DEFAULT 'us-east',
            status ENUM('pending_setup','active','suspended','payout_hold','fraud_freeze') DEFAULT 'active',
            organization_type ENUM('customer','partner') NOT NULL,
            partner_commission_for_staff_viewer TINYINT(1) DEFAULT 0,
            config JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_subdomain (subdomain),
            INDEX idx_owner (owner_id)
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-004: org_quotas table
        // ============================================================
        $table_quotas = $wpdb->prefix . 'orabooks_org_quotas';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_quotas} (
            org_id BIGINT UNSIGNED PRIMARY KEY,
            api_calls_limit INT NULL,
            storage_limit_mb INT NULL,
            user_limit INT NULL,
            FOREIGN KEY (org_id) REFERENCES {$table_organizations}(id) ON DELETE CASCADE
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-004: partner_reactivation_reviews table
        // ============================================================
        $table_reactivation = $wpdb->prefix . 'orabooks_partner_reactivation_reviews';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_reactivation} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            requested_by BIGINT UNSIGNED NOT NULL,
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reason TEXT NOT NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            reviewed_at TIMESTAMP NULL,
            decision ENUM('approved','denied') NULL,
            reviewer_notes TEXT NULL,
            FOREIGN KEY (org_id) REFERENCES {$table_organizations}(id) ON DELETE CASCADE,
            INDEX idx_org (org_id)
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-013: users table extensions (custom table for OraBooks users)
        // ============================================================
        $table_users = $wpdb->prefix . 'orabooks_users';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_users} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            wp_user_id BIGINT UNSIGNED NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NULL,
            is_active TINYINT(1) DEFAULT 1,
            is_email_verified TINYINT(1) DEFAULT 0,
            email_verification_token VARCHAR(128) NULL,
            email_verification_expires_at TIMESTAMP NULL,
            is_2fa_enabled TINYINT(1) DEFAULT 0,
            auth_provider ENUM('local','google') DEFAULT 'local',
            org_id BIGINT UNSIGNED NULL,
            is_partner TINYINT(1) DEFAULT 0,
            subdomain VARCHAR(63) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$table_organizations}(id) ON DELETE SET NULL,
            INDEX idx_email (email),
            INDEX idx_wp_user (wp_user_id)
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-013: refresh_tokens table
        // ============================================================
        $table_tokens = $wpdb->prefix . 'orabooks_refresh_tokens';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_tokens} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            org_id BIGINT UNSIGNED NULL,
            token_hash VARCHAR(128) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            revoked_at TIMESTAMP NULL,
            device_metadata TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES {$table_users}(id) ON DELETE CASCADE,
            INDEX idx_user (user_id),
            INDEX idx_token (token_hash)
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-013: partner_codes table
        // ============================================================
        $table_partner_codes = $wpdb->prefix . 'orabooks_partner_codes';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_partner_codes} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            partner_code VARCHAR(32) UNIQUE NOT NULL,
            partner_code_normalized VARCHAR(32) GENERATED ALWAYS AS (UPPER(TRIM(partner_code))) STORED,
            partner_type ENUM('individual','accountant','agency','reseller','strategic_partner') NOT NULL DEFAULT 'individual',
            organization_name VARCHAR(255) NULL,
            status ENUM('pending_review','active','disabled','expired','inactive') DEFAULT 'pending_review',
            is_active_code TINYINT(1) GENERATED ALWAYS AS (CASE WHEN status = 'active' THEN 1 ELSE 0 END) STORED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            approved_at TIMESTAMP NULL,
            approved_by BIGINT UNSIGNED NULL,
            disabled_at TIMESTAMP NULL,
            disabled_reason TEXT NULL,
            expires_at TIMESTAMP NULL,
            last_attribution_at TIMESTAMP NULL,
            deactivation_reminder_sent_at TIMESTAMP NULL,
            low_activity_reminder_sent_at TIMESTAMP NULL,
            FOREIGN KEY (org_id) REFERENCES {$table_organizations}(id) ON DELETE CASCADE,
            INDEX idx_code (partner_code_normalized),
            INDEX idx_user (user_id),
            INDEX idx_inactive (status, last_attribution_at)
        ) {$charset_collate};";
        dbDelta($sql);
        
        // Add unique constraint for active code per user
        $wpdb->query("ALTER TABLE {$table_partner_codes} ADD CONSTRAINT uk_user_active_code UNIQUE (user_id, is_active_code)");
        
        // ============================================================
        // SL-013: partner_attributions table
        // ============================================================
        $table_attributions = $wpdb->prefix . 'orabooks_partner_attributions';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_attributions} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NULL,
            partner_user_id BIGINT UNSIGNED NOT NULL,
            customer_user_id BIGINT UNSIGNED NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            partner_code_used VARCHAR(32) NOT NULL,
            partner_code_normalized VARCHAR(32) GENERATED ALWAYS AS (UPPER(TRIM(partner_code_used))) STORED,
            attribution_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('pending','verified','blocked') DEFAULT 'pending',
            verified_at TIMESTAMP NULL,
            blocked_at TIMESTAMP NULL,
            blocked_reason TEXT NULL,
            idempotency_key VARCHAR(128) UNIQUE,
            FOREIGN KEY (partner_user_id) REFERENCES {$table_users}(id) ON DELETE CASCADE,
            INDEX idx_partner (partner_user_id),
            INDEX idx_customer (customer_user_id),
            INDEX idx_status (status)
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-013: partner_terms_acceptance table
        // ============================================================
        $table_terms = $wpdb->prefix . 'orabooks_partner_terms_acceptance';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_terms} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            terms_version VARCHAR(20) NOT NULL,
            accepted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            FOREIGN KEY (user_id) REFERENCES {$table_users}(id) ON DELETE CASCADE,
            INDEX idx_user (user_id)
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-014: user_org table
        // ============================================================
        $table_user_org = $wpdb->prefix . 'orabooks_user_org';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_user_org} (
            user_id BIGINT UNSIGNED NOT NULL,
            org_id BIGINT UNSIGNED NOT NULL,
            role ENUM('owner','admin','approver','staff','viewer') NOT NULL,
            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, org_id),
            FOREIGN KEY (user_id) REFERENCES {$table_users}(id) ON DELETE CASCADE,
            FOREIGN KEY (org_id) REFERENCES {$table_organizations}(id) ON DELETE CASCADE
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-014: org_invites table
        // ============================================================
        $table_invites = $wpdb->prefix . 'orabooks_org_invites';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_invites} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(255) NOT NULL,
            role ENUM('admin','approver','staff','viewer') NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            accepted_at TIMESTAMP NULL,
            FOREIGN KEY (org_id) REFERENCES {$table_organizations}(id) ON DELETE CASCADE,
            INDEX idx_token (token_hash),
            INDEX idx_org_email (org_id, email)
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-009: audit_logs table
        // ============================================================
        $table_audit = $wpdb->prefix . 'orabooks_audit_logs';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_audit} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            event_type VARCHAR(64) NOT NULL,
            severity ENUM('info','warning','critical') DEFAULT 'info',
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            correlation_id CHAR(36) NOT NULL,
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$table_organizations}(id) ON DELETE CASCADE,
            INDEX idx_org_time (org_id, created_at),
            INDEX idx_user (user_id),
            INDEX idx_event (event_type),
            INDEX idx_correlation (correlation_id)
        ) {$charset_collate};";
        dbDelta($sql);
        
        // Create archive table
        $table_audit_archive = $wpdb->prefix . 'orabooks_audit_logs_archive';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_audit_archive} LIKE {$table_audit};";
        dbDelta($sql);
        
        // ============================================================
        // SL-013: 2FA backup codes table
        // ============================================================
        $table_backup = $wpdb->prefix . 'orabooks_2fa_backup_codes';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_backup} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            code_hash VARCHAR(128) NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES {$table_users}(id) ON DELETE CASCADE,
            INDEX idx_user (user_id)
        ) {$charset_collate};";
        dbDelta($sql);
        
        // Schedule cron jobs
        if (!wp_next_scheduled('orabooks_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'orabooks_daily_cleanup');
        }
        if (!wp_next_scheduled('orabooks_partner_activity_check')) {
            wp_schedule_event(time(), 'daily', 'orabooks_partner_activity_check');
        }
        
        update_option('orabooks_db_version', ORABOOKS_DB_VERSION);
    }
    
    /**
     * Get table name with prefix
     */
    public static function table($name) {
        global $wpdb;
        return $wpdb->prefix . 'orabooks_' . $name;
    }
}