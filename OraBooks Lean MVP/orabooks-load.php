<?php
/**
 * OraBooks Loader - Ensures WordPress environment is available
 * 
 * This file bootstraps the plugin outside of WordPress admin context
 * for AJAX and API endpoints.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants if not already defined
if (!defined('ORABOOKS_VERSION')) {
    define('ORABOOKS_VERSION', '1.0.0');
    define('ORABOOKS_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('ORABOOKS_PLUGIN_URL', plugin_dir_url(__FILE__));
    define('ORABOOKS_DB_VERSION', '1.0.0');
}

// Core includes
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-database.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-auth.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-organization.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-rbac.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-team.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-audit.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-partner.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/class-orabooks-secrets.php';
require_once ORABOOKS_PLUGIN_DIR . 'includes/helpers.php';