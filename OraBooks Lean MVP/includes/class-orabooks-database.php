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
        
        // ============================================================
        // SL-017: accounts table (Chart of Accounts)
        // ============================================================
        $table_accounts = $wpdb->prefix . 'orabooks_accounts';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_accounts} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            code VARCHAR(20) NOT NULL,
            name VARCHAR(255) NOT NULL,
            type ENUM('asset','liability','equity','revenue','expense') NOT NULL,
            normal_balance ENUM('debit','credit') NOT NULL,
            system_generated TINYINT(1) DEFAULT 1,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_org_code (org_id, code),
            FOREIGN KEY (org_id) REFERENCES {$table_organizations}(id) ON DELETE CASCADE
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-017: account_balances table
        // ============================================================
        $table_balances = $wpdb->prefix . 'orabooks_account_balances';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_balances} (
            org_id BIGINT UNSIGNED NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            balance DECIMAL(20,2) NOT NULL DEFAULT 0,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (org_id, account_id),
            FOREIGN KEY (account_id) REFERENCES {$table_accounts}(id) ON DELETE CASCADE
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-001: journals table
        // ============================================================
        $table_journals = $wpdb->prefix . 'orabooks_journals';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_journals} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            journal_number VARCHAR(30) UNIQUE NULL,
            status ENUM('draft','review_pending','approved','posted','locked','reversed') NOT NULL DEFAULT 'draft',
            transaction_date DATE NOT NULL,
            idempotency_key VARCHAR(128) NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            approved_by BIGINT UNSIGNED NULL,
            posted_by BIGINT UNSIGNED NULL,
            approved_at TIMESTAMP NULL,
            posted_at TIMESTAMP NULL,
            reversal_of_id BIGINT UNSIGNED NULL,
            reversal_reason TEXT NULL,
            source_type VARCHAR(50) NULL,
            source_id BIGINT UNSIGNED NULL,
            source_hash VARCHAR(64) NULL,
            journal_hash VARCHAR(64) NULL,
            previous_hash VARCHAR(64) NULL,
            total_amount DECIMAL(20,2) DEFAULT 0,
            revision_number INT DEFAULT 1,
            approval_round INT DEFAULT 0,
            approved_snapshot_hash VARCHAR(64) NULL,
            approval_stale TINYINT(1) DEFAULT 0,
            approval_expires_at TIMESTAMP NULL,
            lock_after_approval TINYINT(1) DEFAULT 1,
            last_submitted_at TIMESTAMP NULL,
            last_submitted_by BIGINT UNSIGNED NULL,
            rejected_reason TEXT NULL,
            metadata JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_org_idempotency (org_id, idempotency_key),
            FOREIGN KEY (org_id) REFERENCES {$table_organizations}(id) ON DELETE CASCADE,
            INDEX idx_org_status (org_id, status)
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-001: journal_lines table
        // ============================================================
        $table_jlines = $wpdb->prefix . 'orabooks_journal_lines';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_jlines} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            journal_id BIGINT UNSIGNED NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            account_code VARCHAR(20) NOT NULL,
            debit_amount DECIMAL(20,2) DEFAULT 0,
            credit_amount DECIMAL(20,2) DEFAULT 0,
            currency_code CHAR(3) DEFAULT 'USD',
            exchange_rate DECIMAL(12,6) DEFAULT 1,
            description TEXT NULL,
            FOREIGN KEY (journal_id) REFERENCES {$table_journals}(id) ON DELETE CASCADE,
            FOREIGN KEY (account_id) REFERENCES {$table_accounts}(id),
            CHECK (debit_amount >= 0 AND credit_amount >= 0)
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-001: ledger_entries table (immutable posting record)
        // ============================================================
        $table_ledger = $wpdb->prefix . 'orabooks_ledger_entries';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_ledger} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            journal_id BIGINT UNSIGNED NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            debit_amount DECIMAL(20,2) DEFAULT 0,
            credit_amount DECIMAL(20,2) DEFAULT 0,
            posting_batch_id BIGINT UNSIGNED NOT NULL,
            posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (org_id) REFERENCES {$table_organizations}(id) ON DELETE CASCADE,
            INDEX idx_org_account_date (org_id, account_id, posted_at)
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-001: posting_batches table
        // ============================================================
        $table_batches = $wpdb->prefix . 'orabooks_posting_batches';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_batches} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            year INT NOT NULL,
            batch_number INT NOT NULL,
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_org_year_batch (org_id, year, batch_number)
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-001: balance_snapshots table
        // ============================================================
        $table_snapshots = $wpdb->prefix . 'orabooks_balance_snapshots';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_snapshots} (
            org_id BIGINT UNSIGNED NOT NULL,
            snapshot_date DATE NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            balance DECIMAL(20,2) NOT NULL,
            PRIMARY KEY (org_id, snapshot_date, account_id)
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-001: fiscal_periods table
        // ============================================================
        $table_fiscal = $wpdb->prefix . 'orabooks_fiscal_periods';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_fiscal} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            status ENUM('open','soft_closed','hard_closed') DEFAULT 'open',
            closed_by BIGINT UNSIGNED NULL,
            closed_at TIMESTAMP NULL,
            reopened_by BIGINT UNSIGNED NULL,
            reopened_at TIMESTAMP NULL,
            reopen_reason TEXT NULL,
            FOREIGN KEY (org_id) REFERENCES {$table_organizations}(id) ON DELETE CASCADE,
            UNIQUE KEY uk_org_period (org_id, period_start)
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-001: outbox_messages table (transactional outbox)
        // ============================================================
        $table_outbox = $wpdb->prefix . 'orabooks_outbox_messages';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_outbox} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(100) NOT NULL,
            aggregate_id BIGINT UNSIGNED NOT NULL,
            payload JSON NOT NULL,
            status ENUM('pending','sent','failed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            retry_count INT DEFAULT 0,
            last_attempt_at TIMESTAMP NULL,
            INDEX idx_status_created (status, created_at)
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-001: posting_retry_queue table
        // ============================================================
        $table_retry = $wpdb->prefix . 'orabooks_posting_retry_queue';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_retry} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            journal_id BIGINT UNSIGNED NOT NULL,
            error_message TEXT NULL,
            retry_count INT DEFAULT 0,
            status ENUM('pending','processing','failed','manual_review') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (journal_id) REFERENCES {$table_journals}(id) ON DELETE CASCADE
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-001: read_model_versions table
        // ============================================================
        $table_rmv = $wpdb->prefix . 'orabooks_read_model_versions';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_rmv} (
            projection_name VARCHAR(100) PRIMARY KEY,
            version INT NOT NULL DEFAULT 1,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-002: journal_approval_history table (append-only)
        // ============================================================
        $table_approval = $wpdb->prefix . 'orabooks_journal_approval_history';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_approval} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            journal_id BIGINT UNSIGNED NOT NULL,
            action ENUM('submit','approve','reject','resubmit','delegate','escalate','invalidate','expire') NOT NULL,
            performed_by BIGINT UNSIGNED NOT NULL,
            reason TEXT NULL,
            snapshot_hash VARCHAR(64) NULL,
            approval_round INT NOT NULL,
            revision_number INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (journal_id) REFERENCES {$table_journals}(id) ON DELETE CASCADE
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-002: approval_delegations table
        // ============================================================
        $table_delegations = $wpdb->prefix . 'orabooks_approval_delegations';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_delegations} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT UNSIGNED NOT NULL,
            delegator_user_id BIGINT UNSIGNED NOT NULL,
            delegate_user_id BIGINT UNSIGNED NOT NULL,
            starts_at TIMESTAMP NOT NULL,
            ends_at TIMESTAMP NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            revoked_at TIMESTAMP NULL,
            FOREIGN KEY (org_id) REFERENCES {$table_organizations}(id) ON DELETE CASCADE
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-002: approval_policies table
        // ============================================================
        $table_policies = $wpdb->prefix . 'orabooks_approval_policies';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_policies} (
            org_id BIGINT UNSIGNED PRIMARY KEY,
            approval_expiry_hours INT DEFAULT 72,
            reminder_hours_before_expiry INT DEFAULT 24,
            max_approval_rounds INT DEFAULT 5,
            maker_checker_required TINYINT(1) DEFAULT 1,
            mfa_amount_threshold DECIMAL(20,2) DEFAULT 10000.00,
            escalation_after_hours INT DEFAULT 48,
            escalation_role ENUM('admin','owner') DEFAULT 'admin'
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-303: async_jobs table
        // ============================================================
        $table_jobs = $wpdb->prefix . 'orabooks_async_jobs';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_jobs} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            queue_name VARCHAR(50) DEFAULT 'default',
            job_type VARCHAR(100) NOT NULL,
            payload JSON NOT NULL,
            status ENUM('pending','processing','completed','failed','dead_letter') DEFAULT 'pending',
            priority INT DEFAULT 5,
            retry_count INT DEFAULT 0,
            max_retries INT DEFAULT 5,
            next_retry_at TIMESTAMP NULL,
            started_at TIMESTAMP NULL,
            completed_at TIMESTAMP NULL,
            last_error TEXT NULL,
            heartbeat_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status_priority (status, priority, created_at)
        ) {$charset_collate};";
        dbDelta($sql);
        
        // ============================================================
        // SL-301: state_machine_transitions table
        // ============================================================
        $table_sm = $wpdb->prefix . 'orabooks_state_machine_transitions';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_sm} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            record_type VARCHAR(50) NOT NULL,
            record_id BIGINT UNSIGNED NOT NULL,
            from_state VARCHAR(50) NOT NULL,
            to_state VARCHAR(50) NOT NULL,
            event VARCHAR(50) NOT NULL,
            triggered_by BIGINT UNSIGNED NULL,
            reason TEXT NULL,
            metadata JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_record (record_type, record_id),
            INDEX idx_created (created_at)
        ) {$charset_collate};";
        dbDelta($sql);
        
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