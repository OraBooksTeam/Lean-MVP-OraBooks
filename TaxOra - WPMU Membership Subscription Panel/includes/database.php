<?php

if ( ! class_exists( 'OraBooks_DB_Tables' ) ) {
    class OraBooks_DB_Tables {
        public static function init() {
            orabooks_update_database_schema();
        }
        public static function activate() {
            orabooks_activate();
        }
        public static function deactivate() {
            if ( function_exists( 'orabooks_network_deactivate' ) ) {
                orabooks_network_deactivate( false );
            }
        }
        public static function uninstall() {
            orabooks_uninstall();
        }
        public static function create_tables() {
            orabooks_create_tables();
        }
        public static function update_database_schema() {
            orabooks_update_database_schema();
        }
        public static function update_schema() {
            orabooks_update_database_schema();
        }
        public static function setup() {
            orabooks_handle_multisite_tables();
        }
    }
}

// Call this function when the plugin loads
add_action('plugins_loaded', 'orabooks_handle_multisite_tables');
add_action('admin_init', 'orabooks_handle_multisite_tables');

// Add this function to check and update the feature_assignments table structure
function orabooks_update_feature_assignments_table() {
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    // Check if settings column exists
    $column_exists = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->orabooks_feature_assignments} LIKE 'settings'");
    
    if (!$column_exists) {
        error_log('Adding settings column to feature_assignments table');
        $wpdb->query("ALTER TABLE {$wpdb->orabooks_feature_assignments} ADD COLUMN settings text NULL AFTER access_type");
    }
    
    // Check if created_at column exists
    $created_at_exists = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->orabooks_feature_assignments} LIKE 'created_at'");
    
    if (!$created_at_exists) {
        error_log('Adding created_at column to feature_assignments table');
        $wpdb->query("ALTER TABLE {$wpdb->orabooks_feature_assignments} ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP AFTER settings");
    }
}

// Call this function when the plugin loads
add_action('plugins_loaded', 'orabooks_update_feature_assignments_table');
// Multi-site Table Handling
function orabooks_handle_multisite_tables() {
    global $wpdb;
    
    // Always use the main site's tables for Orabooks data in Multisite
    // This ensures plans, orders, and subscriptions are centralized
    if ( is_multisite() ) {
        $prefix = $wpdb->base_prefix;
        
        $wpdb->orabooks_levels = $prefix . 'orabooks_levels';
        $wpdb->orabooks_orders = $prefix . 'orabooks_orders';
        $wpdb->orabooks_subscriptions = $prefix . 'orabooks_subscriptions';
        $wpdb->orabooks_groups = $prefix . 'orabooks_groups';
        $wpdb->orabooks_feature_assignments = $prefix . 'orabooks_feature_assignments';
        $wpdb->orabooks_email_queue = $prefix . 'orabooks_email_queue';
        $wpdb->orabooks_usage_log = $prefix . 'orabooks_usage_log';
        $wpdb->orabooks_audit_log = $prefix . 'orabooks_audit_log';
    } else {
        $wpdb->orabooks_levels = $wpdb->prefix . 'orabooks_levels';
        $wpdb->orabooks_orders = $wpdb->prefix . 'orabooks_orders';
        $wpdb->orabooks_subscriptions = $wpdb->prefix . 'orabooks_subscriptions';
        $wpdb->orabooks_groups = $wpdb->prefix . 'orabooks_groups';
        $wpdb->orabooks_feature_assignments = $wpdb->prefix . 'orabooks_feature_assignments';
        $wpdb->orabooks_email_queue = $wpdb->prefix . 'orabooks_email_queue';
        $wpdb->orabooks_usage_log = $wpdb->prefix . 'orabooks_usage_log';
        $wpdb->orabooks_audit_log = $wpdb->prefix . 'orabooks_audit_log';
    }
}

// Safe table name getter
function orabooks_get_table_name( $table_type ) {
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    $table_map = array(
        'levels' => $wpdb->orabooks_levels,
        'orders' => $wpdb->orabooks_orders,
        'subscriptions' => $wpdb->orabooks_subscriptions,
        'groups' => $wpdb->orabooks_groups,
        'feature_assignments' => $wpdb->orabooks_feature_assignments,
        'email_queue' => $wpdb->orabooks_email_queue,
        'usage_log' => $wpdb->orabooks_usage_log
    );
    
    return isset( $table_map[$table_type] ) ? $table_map[$table_type] : false;
}

// Build Guide Compliance: Add audit columns to existing tables
function orabooks_add_build_guide_audit_columns() {
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    $tables_to_update = array(
        $wpdb->orabooks_groups,
        $wpdb->orabooks_levels,
        $wpdb->orabooks_orders,
        $wpdb->orabooks_subscriptions,
        $wpdb->orabooks_feature_assignments,
        $wpdb->orabooks_email_queue,
        $wpdb->orabooks_usage_log,
        $wpdb->orabooks_audit_log
    );
    
    $columns_to_add = array(
        'mode' => "ADD COLUMN mode varchar(20) DEFAULT 'business'",
        'updated_at' => "ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        'created_by' => "ADD COLUMN created_by bigint(20) DEFAULT NULL",
        'updated_by' => "ADD COLUMN updated_by bigint(20) DEFAULT NULL"
    );
    
    foreach ($tables_to_update as $table) {
        // Skip audit log table for column updates - it has its own schema
        if ($table === $wpdb->orabooks_audit_log) {
            continue;
        }
        
        foreach ($columns_to_add as $column_name => $add_sql) {
            $column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table LIKE '$column_name'");
            if (!$column_exists) {
                $wpdb->query("ALTER TABLE $table $add_sql");
                error_log("Build Guide Compliance: Added column $column_name to table $table");
            }
        }
        
        // Add mode index if it doesn't exist
        $index_exists = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = '$table' 
            AND index_name = 'mode'
        ");
        
        if (!$index_exists) {
            $wpdb->query("CREATE INDEX mode ON $table (mode)");
            error_log("Build Guide Compliance: Added mode index to table $table");
        }
    }
    
    // Add build guide specific columns to levels table
    orabooks_add_build_guide_level_columns();
}

// Add specific columns for build guide compliance in levels table
function orabooks_add_build_guide_level_columns() {
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    // Add build_guide_level_key column for mapping to build guide levels
    $bg_key_exists = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->orabooks_levels} LIKE 'build_guide_level_key'");
    if (!$bg_key_exists) {
        $wpdb->query("ALTER TABLE {$wpdb->orabooks_levels} ADD COLUMN build_guide_level_key varchar(50) DEFAULT NULL AFTER mode");
        error_log("Build Guide Compliance: Added build_guide_level_key column to levels table");
    }
    
    // Add tier_rank column for ordering levels
    $tier_rank_exists = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->orabooks_levels} LIKE 'tier_rank'");
    if (!$tier_rank_exists) {
        $wpdb->query("ALTER TABLE {$wpdb->orabooks_levels} ADD COLUMN tier_rank int DEFAULT 0 AFTER build_guide_level_key");
        error_log("Build Guide Compliance: Added tier_rank column to levels table");
    }
    
    // Add max_users column for user limits
    $max_users_exists = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->orabooks_levels} LIKE 'max_users'");
    if (!$max_users_exists) {
        $wpdb->query("ALTER TABLE {$wpdb->orabooks_levels} ADD COLUMN max_users int DEFAULT NULL AFTER tier_rank");
        error_log("Build Guide Compliance: Added max_users column to levels table");
    }
    
    // Add feature_limits column (JSON) for storing feature limits
    $feature_limits_exists = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->orabooks_levels} LIKE 'feature_limits'");
    if (!$feature_limits_exists) {
        $wpdb->query("ALTER TABLE {$wpdb->orabooks_levels} ADD COLUMN feature_limits text DEFAULT NULL AFTER max_users");
        error_log("Build Guide Compliance: Added feature_limits column to levels table");
    }
    
    // Add index for build_guide_level_key
    $bg_key_index_exists = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM information_schema.statistics 
        WHERE table_schema = DATABASE() 
        AND table_name = '{$wpdb->orabooks_levels}' 
        AND index_name = 'build_guide_level_key'
    ");
    
    if (!$bg_key_index_exists) {
        $wpdb->query("CREATE INDEX build_guide_level_key ON {$wpdb->orabooks_levels} (build_guide_level_key)");
        error_log("Build Guide Compliance: Added build_guide_level_key index to levels table");
    }
}

// Database schema updates
function orabooks_update_database_schema() {
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    $charset_collate = $wpdb->get_charset_collate();
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    
    // Build Guide Compliance: Add audit columns to existing tables
    orabooks_add_build_guide_audit_columns();
    
    // Add performance indexes first
    orabooks_add_performance_indexes();
    
    // Check if is_active column exists in levels table
    $column_exists = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->orabooks_levels} LIKE 'is_active'");
    if ( ! $column_exists ) {
        $wpdb->query("ALTER TABLE {$wpdb->orabooks_levels} ADD COLUMN is_active tinyint(1) DEFAULT 1");
    }

    // Check if label column exists in levels table (for admin-defined plan labels)
    $label_column_exists = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->orabooks_levels} LIKE 'label'");
    if ( ! $label_column_exists ) {
        // Allow up to 50 characters for a short label like 'Free', 'Popular', 'Recommended'
        $wpdb->query("ALTER TABLE {$wpdb->orabooks_levels} ADD COLUMN label varchar(50) DEFAULT NULL");
    }
    
    // Add unique constraint to prevent duplicate level names within the same group
    $unique_exists = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM information_schema.table_constraints 
        WHERE constraint_schema = DATABASE() 
        AND table_name = '{$wpdb->orabooks_levels}' 
        AND constraint_name = 'unique_level_name_per_group'
    ");
    
    if ( ! $unique_exists ) {
        $wpdb->query("
            ALTER TABLE {$wpdb->orabooks_levels} 
            ADD CONSTRAINT unique_level_name_per_group 
            UNIQUE (group_id, name)
        ");
    }
    
    // Check if subscriptions table exists and has correct structure
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->orabooks_subscriptions}'");
    if ( $table_exists != $wpdb->orabooks_subscriptions ) {
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->orabooks_subscriptions} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            subscription_id varchar(191) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            level_id mediumint(9) DEFAULT NULL,
            gateway varchar(50) DEFAULT NULL,
            status varchar(50) DEFAULT 'active',
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            ends_at datetime NULL,
            auto_renew tinyint(1) DEFAULT 1,
            renewal_attempts int DEFAULT 0,
            last_renewal_attempt datetime NULL,
            meta longtext NULL,
            PRIMARY KEY (id),
            KEY(user_id),
            KEY(status),
            KEY(ends_at),
            KEY(user_status),
            KEY(ends_status)
        ) $charset_collate;";
        dbDelta( $sql );
    } else {
        // Add missing columns to existing table
        $auto_renew_exists = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->orabooks_subscriptions} LIKE 'auto_renew'");
        if ( ! $auto_renew_exists ) {
            $wpdb->query("ALTER TABLE {$wpdb->orabooks_subscriptions} ADD COLUMN auto_renew tinyint(1) DEFAULT 1");
        }
        
        $renewal_attempts_exists = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->orabooks_subscriptions} LIKE 'renewal_attempts'");
        if ( ! $renewal_attempts_exists ) {
            $wpdb->query("ALTER TABLE {$wpdb->orabooks_subscriptions} ADD COLUMN renewal_attempts int DEFAULT 0");
        }
        
        $last_renewal_exists = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->orabooks_subscriptions} LIKE 'last_renewal_attempt'");
        if ( ! $last_renewal_exists ) {
            $wpdb->query("ALTER TABLE {$wpdb->orabooks_subscriptions} ADD COLUMN last_renewal_attempt datetime NULL");
        }
        
        // Add performance indexes if they don't exist
        orabooks_add_subscription_indexes();
    }
}

// Call this function on plugin load
add_action( 'plugins_loaded', 'orabooks_update_database_schema' );

// Activation hook
function orabooks_activate( $network_wide = false ) {
    global $wpdb;
    
    // Fix output compression conflicts
    orabooks_fix_output_compression_conflicts();
    
    try {
        if ( is_multisite() && $network_wide ) {
            $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
            foreach ( $blog_ids as $blog_id ) {
                switch_to_blog( $blog_id );
                orabooks_create_tables();
                // Automatically create required pages for the plugin
                if (function_exists('orabooks_create_required_pages')) {
                    orabooks_create_required_pages();
                }
                restore_current_blog();
            }
        } else {
            orabooks_create_tables();
            // Automatically create required pages for the plugin
            if (function_exists('orabooks_create_required_pages')) {
                orabooks_create_required_pages();
            }
        }
        
        // Flush rewrite rules and trigger workspace rewrite setup
        flush_rewrite_rules();
        
        // Set wizard flag if not already completed
        if ( ! get_option( 'orabooks_wizard_completed', false ) ) {
            update_option( 'orabooks_wizard_step', 'general' );
        }
    } finally {
        // Restore error handler
        orabooks_restore_error_handler();
    }
    
    do_action('orabooks_plugin_activated');
}

// Create database tables
function orabooks_create_tables() {
    global $wpdb;
    
    // Set table names first
    orabooks_handle_multisite_tables();
    
    $charset_collate = $wpdb->get_charset_collate();
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    // Groups table (Build Guide Compliant: added updated_at, created_by, updated_by, mode)
    $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->orabooks_groups} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name tinytext NOT NULL,
        description text NULL,
        mode varchar(20) DEFAULT 'business',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by bigint(20) DEFAULT NULL,
        updated_by bigint(20) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY mode (mode)
    ) $charset_collate;";
    dbDelta( $sql1 );

    // Levels table (Build Guide Compliant: added updated_at, created_by, updated_by, mode)
    $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->orabooks_levels} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        group_id mediumint(9) DEFAULT NULL,
        name tinytext NOT NULL,
        description text NULL,
        price decimal(10,2) NOT NULL DEFAULT 0.00,
        billing_period varchar(20) DEFAULT 'monthly',
        trial_days int DEFAULT 0,
        currency varchar(10) DEFAULT 'BDT',
        currency_symbol varchar(10) DEFAULT '৳',
        currency_position varchar(10) DEFAULT 'before',
        is_active tinyint(1) DEFAULT 1,
        mode varchar(20) DEFAULT 'business',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by bigint(20) DEFAULT NULL,
        updated_by bigint(20) DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY mode (mode)
    ) $charset_collate;";
    dbDelta( $sql2 );

    // Orders table (Build Guide Compliant: added updated_at, created_by, updated_by, mode)
    $sql3 = "CREATE TABLE IF NOT EXISTS {$wpdb->orabooks_orders} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        order_id varchar(191) NOT NULL,
        user_id bigint(20) DEFAULT NULL,
        level_id mediumint(9) DEFAULT NULL,
        gateway varchar(50) DEFAULT NULL,
        amount decimal(10,2) DEFAULT 0.00,
        status varchar(50) DEFAULT 'pending',
        mode varchar(20) DEFAULT 'business',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by bigint(20) DEFAULT NULL,
        updated_by bigint(20) DEFAULT NULL,
        meta longtext NULL,
        PRIMARY KEY (id),
        KEY(order_id),
        KEY(user_id),
        KEY(status),
        KEY mode (mode)
    ) $charset_collate;";
    dbDelta( $sql3 );

    // Subscriptions table (Build Guide Compliant: added updated_at, created_by, updated_by, mode)
    $sql4 = "CREATE TABLE IF NOT EXISTS {$wpdb->orabooks_subscriptions} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        subscription_id varchar(191) NOT NULL,
        user_id bigint(20) DEFAULT NULL,
        level_id mediumint(9) DEFAULT NULL,
        gateway varchar(50) DEFAULT NULL,
        status varchar(50) DEFAULT 'active',
        mode varchar(20) DEFAULT 'business',
        started_at datetime DEFAULT CURRENT_TIMESTAMP,
        ends_at datetime NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by bigint(20) DEFAULT NULL,
        updated_by bigint(20) DEFAULT NULL,
        meta longtext NULL,
        PRIMARY KEY (id),
        KEY(user_id),
        KEY(status),
        KEY mode (mode)
    ) $charset_collate;";
    dbDelta( $sql4 );

    // Feature assignments table (Build Guide Compliant: added updated_at, created_by, updated_by, mode)
    $sql5 = "CREATE TABLE IF NOT EXISTS {$wpdb->orabooks_feature_assignments} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        level_id mediumint(9) NOT NULL,
        feature_key varchar(100) NOT NULL,
        feature_name varchar(255) NOT NULL,
        access_type varchar(50) DEFAULT 'full',
        mode varchar(20) DEFAULT 'business',
        settings text NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by bigint(20) DEFAULT NULL,
        updated_by bigint(20) DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY level_feature (level_id, feature_key),
        KEY mode (mode)
    ) $charset_collate;";
    dbDelta( $sql5 );

    // Email queue table (Build Guide Compliant: added updated_at, created_by, updated_by, mode)
    $sql6 = "CREATE TABLE IF NOT EXISTS {$wpdb->orabooks_email_queue} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        to_email varchar(255) NOT NULL,
        subject varchar(255) NOT NULL,
        message longtext NOT NULL,
        template varchar(100) NULL,
        priority tinyint DEFAULT 5,
        status enum('pending', 'sent', 'failed') DEFAULT 'pending',
        attempts int DEFAULT 0,
        last_error text NULL,
        scheduled_at datetime NULL,
        sent_at datetime NULL,
        mode varchar(20) DEFAULT 'business',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by bigint(20) DEFAULT NULL,
        updated_by bigint(20) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_status_priority (status, priority),
        KEY idx_scheduled (scheduled_at),
        KEY mode (mode)
    ) $charset_collate;";
    dbDelta( $sql6 );

    // Usage log table (Build Guide Compliant: added updated_at, created_by, updated_by, mode)
    $sql7 = "CREATE TABLE IF NOT EXISTS {$wpdb->orabooks_usage_log} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        blog_id bigint(20) NOT NULL,
        category varchar(50) NOT NULL,
        item_key varchar(100) NOT NULL,
        action_type varchar(50) NOT NULL,
        mode varchar(20) DEFAULT 'business',
        metadata longtext NULL,
        ip_address varchar(45) NULL,
        user_agent text NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by bigint(20) DEFAULT NULL,
        updated_by bigint(20) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_user_created (user_id, created_at),
        KEY idx_blog_feature (blog_id, category, item_key),
        KEY idx_created (created_at),
        KEY idx_category (category),
        KEY idx_item_key (item_key),
        KEY mode (mode)
    ) $charset_collate;";
    dbDelta( $sql7 );

    // Audit log table (Build Guide Compliant: comprehensive audit trail)
    $sql8 = "CREATE TABLE IF NOT EXISTS {$wpdb->orabooks_audit_log} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) DEFAULT NULL,
        action_type varchar(100) NOT NULL,
        action_description text NULL,
        mode varchar(20) DEFAULT 'business',
        entity_type varchar(50) DEFAULT NULL,
        entity_id bigint(20) DEFAULT NULL,
        before_state longtext NULL,
        after_state longtext NULL,
        ip_address varchar(45) NULL,
        user_agent text NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_user_id (user_id),
        KEY idx_action_type (action_type),
        KEY idx_mode (mode),
        KEY idx_entity (entity_type, entity_id),
        KEY idx_created_at (created_at)
    ) $charset_collate;";
    dbDelta( $sql8 );

    // Create default data
    orabooks_create_default_data();
}

// Create default data
function orabooks_create_default_data() {
    global $wpdb;
    
    // Ensure table names are set
    orabooks_handle_multisite_tables();
    
    $exists = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->orabooks_levels}" );
    if ( empty( $exists ) ) {
        $wpdb->insert( $wpdb->orabooks_groups, array( 
            'name' => 'Default', 
            'description' => 'Default membership group' 
        ) );
        $group_id = $wpdb->insert_id;
        
        $wpdb->insert( $wpdb->orabooks_levels, array(
            'group_id' => $group_id,
            'name' => 'Free',
            'description' => 'Basic free membership',
            'price' => 0.00,
            'billing_period' => 'free',
            'is_active' => 1
        ) );
        
        $wpdb->insert( $wpdb->orabooks_levels, array(
            'group_id' => $group_id,
            'name' => 'Pro Monthly',
            'description' => 'Professional monthly plan',
            'price' => 29.00,
            'billing_period' => 'monthly',
            'is_active' => 1
        ) );
        
        $wpdb->insert( $wpdb->orabooks_levels, array(
            'group_id' => $group_id,
            'name' => 'Pro Yearly',
            'description' => 'Professional yearly plan',
            'price' => 299.00,
            'billing_period' => 'yearly',
            'is_active' => 1
        ) );
    }
}

// orabooks_create_pages removal - Pages now handled centrally in includes/client-default-pages.php

// Uninstall cleanup
function orabooks_uninstall() {
    global $wpdb;
    
    if ( is_multisite() ) {
        $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
        foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            orabooks_drop_tables();
            restore_current_blog();
        }
    } else {
        orabooks_drop_tables();
    }
}

function orabooks_drop_tables() {
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->orabooks_levels}" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->orabooks_orders}" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->orabooks_subscriptions}" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->orabooks_groups}" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->orabooks_feature_assignments}" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->orabooks_email_queue}" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->orabooks_usage_log}" );
}

// Database helper functions
function orabooks_get_level( $level_id ) {
    global $wpdb;
    $table = orabooks_get_table_name( 'levels' );
    if ( ! $table ) return null;
    
    return $wpdb->get_row( $wpdb->prepare( 
        "SELECT * FROM $table WHERE id = %d", 
        $level_id 
    ) );
}

function orabooks_get_user_level( $user_id ) {
    $level_id = get_user_meta( $user_id, 'orabooks_level', true );
    return $level_id ? orabooks_get_level( $level_id ) : null;
}

function orabooks_get_user_orders( $user_id, $limit = 10 ) {
    global $wpdb;
    $table = orabooks_get_table_name( 'orders' );
    if ( ! $table ) return array();
    
    return $wpdb->get_results( $wpdb->prepare( 
        "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d", 
        $user_id, $limit 
    ) );
}

// Check if tables exist
function orabooks_check_tables_exist() {
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    $tables = array(
        'groups' => $wpdb->orabooks_groups,
        'levels' => $wpdb->orabooks_levels,
        'orders' => $wpdb->orabooks_orders,
        'subscriptions' => $wpdb->orabooks_subscriptions,
        'feature_assignments' => $wpdb->orabooks_feature_assignments,
        'email_queue' => $wpdb->orabooks_email_queue,
        'usage_log' => $wpdb->orabooks_usage_log
    );
    
    foreach ( $tables as $type => $table ) {
        // Use direct query instead of prepare() for table existence check
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) != $table ) {
            return false;
        }
    }
    
    return true;
}

// Safe database query function
function orabooks_db_query( $query, $output = OBJECT ) {
    global $wpdb;
    orabooks_handle_multisite_tables();
    return $wpdb->get_results( $query, $output );
}

// Add performance indexes for optimization
function orabooks_add_performance_indexes() {
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    // Add indexes to orders table
    $orders_indexes = [
        'idx_user_status' => "CREATE INDEX idx_user_status ON {$wpdb->orabooks_orders} (user_id, status)",
        'idx_created_status' => "CREATE INDEX idx_created_status ON {$wpdb->orabooks_orders} (created_at, status)",
        'idx_level_status' => "CREATE INDEX idx_level_status ON {$wpdb->orabooks_orders} (level_id, status)"
    ];
    
    foreach ($orders_indexes as $index_name => $sql) {
        $index_exists = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = '{$wpdb->orabooks_orders}' 
            AND index_name = '{$index_name}'
        ");
        
        if (!$index_exists) {
            error_log("Adding index {$index_name} to orders table");
            $wpdb->query($sql);
        }
    }
    
    // Add indexes to subscriptions table
    $subscriptions_indexes = [
        'idx_user_status' => "CREATE INDEX idx_user_status ON {$wpdb->orabooks_subscriptions} (user_id, status)",
        'idx_ends_status' => "CREATE INDEX idx_ends_status ON {$wpdb->orabooks_subscriptions} (ends_at, status)"
    ];
    
    foreach ($subscriptions_indexes as $index_name => $sql) {
        $index_exists = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = '{$wpdb->orabooks_subscriptions}' 
            AND index_name = '{$index_name}'
        ");
        
        if (!$index_exists) {
            error_log("Adding index {$index_name} to subscriptions table");
            $wpdb->query($sql);
        }
    }
    
    // Add indexes to feature_assignments table
    $feature_indexes = [
        'idx_feature_level' => "CREATE INDEX idx_feature_level ON {$wpdb->orabooks_feature_assignments} (feature_key, level_id)"
    ];
    
    foreach ($feature_indexes as $index_name => $sql) {
        $index_exists = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = '{$wpdb->orabooks_feature_assignments}' 
            AND index_name = '{$index_name}'
        ");
        
        if (!$index_exists) {
            error_log("Adding index {$index_name} to feature_assignments table");
            $wpdb->query($sql);
        }
    }
}

// Add subscription-specific indexes
function orabooks_add_subscription_indexes() {
    global $wpdb;
    orabooks_handle_multisite_tables();
    
    $indexes = [
        'idx_ends_at' => "CREATE INDEX idx_ends_at ON {$wpdb->orabooks_subscriptions} (ends_at)",
        'idx_user_status' => "CREATE INDEX idx_user_status ON {$wpdb->orabooks_subscriptions} (user_id, status)",
        'idx_ends_status' => "CREATE INDEX idx_ends_status ON {$wpdb->orabooks_subscriptions} (ends_at, status)"
    ];
    
    foreach ($indexes as $index_name => $sql) {
        $index_exists = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = '{$wpdb->orabooks_subscriptions}' 
            AND index_name = '{$index_name}'
        ");
        
        if (!$index_exists) {
            error_log("Adding index {$index_name} to subscriptions table");
            $wpdb->query($sql);
        }
    }
}
