<?php
/**
 * Plugin Name: OraBooks - WPMU Frontend DashBoard
 * Plugin URI: https://www.enest.com.bd/plugins/wpmu-frontend-dashboard
 * Description: Manage Frontend DashBoard
 * Version: 1.0
 * Author: Engr. AnwarIT CASDP and Jahidul Islam
 * Author URI: https://www.anwarit.com
 * Creditse: TOB Developer Team
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpmu-frontend-dashboard
 *
 * Minimum PHP: 8.0.30
 * Minimum WP: 6.8
 * @author 		Engr.AnwarIT- CASDP/TaxOra
 * @package		WPMU Frontend Dashboard
 *
 * @copyright 	Copyright (c) 2025, TaxOra LLC USA
 * Orabooks Addon: true
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include the autoloader
require_once __DIR__ . '/autoload.php';

use WPFD\Core\Plugin;
use WPFD\Core\Activator;

// Register activation and deactivation hooks
register_activation_hook(__FILE__, [Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Activator::class, 'deactivate']);

// Initialize plugin
add_action('plugins_loaded', function() {
    Plugin::init();
}, 10);
