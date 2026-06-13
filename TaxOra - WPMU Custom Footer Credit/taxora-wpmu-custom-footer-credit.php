<?php
/**
 * Plugin Name: TaxOra WPMU Custom Footer Credit
 * Plugin URI: https://www.enest.com.bd/plugins/wpmu-custom-footer-credit
 * Description: Manage Custom Footer Credit in Multisite Network
 * Version: 1.0
 * Author: Engr. AnwarIT CASDP and Jahidul Islam
 * Author URI: https://www.anwarit.com
 * Creditse: TOB Developer Team
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxora-wpmu-custom-footer-credit
 *
 * Minimum PHP: 8.0.30
 * Minimum WP: 6.8
 * @author 		Engr.AnwarIT- CASDP/TaxOra
 * @package		WPMU Custom Footer Credit
 *
 * @copyright 	Copyright (c) 2025, TaxOra LLC USA
 * Orabooks Addon: true
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
if ( ! defined( 'TAXORA_FOOTER_CREDIT_VERSION' ) ) {
    define( 'TAXORA_FOOTER_CREDIT_VERSION', '1.0.0' );
}

if ( ! defined( 'TAXORA_FOOTER_CREDIT_DIR' ) ) {
    define( 'TAXORA_FOOTER_CREDIT_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'TAXORA_FOOTER_CREDIT_URL' ) ) {
    define( 'TAXORA_FOOTER_CREDIT_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'TAXORA_FOOTER_CREDIT_BASENAME' ) ) {
    define( 'TAXORA_FOOTER_CREDIT_BASENAME', plugin_basename( __FILE__ ) );
}

/**
 * Check for TaxOra - WPMU Membership Subscription Panel Plugin
 */
function taxora_footer_credit_check_dependency() {
    if ( function_exists( 'orabooks_register_addon' ) || 
         function_exists( 'orabooks_is_feature_enabled' ) ||
         class_exists( 'OraBooks' ) || 
         defined( 'ORABOOKS_VERSION' ) ) {
        return true;
    }

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $active_plugins = (array) get_option( 'active_plugins', array() );
    if ( is_multisite() ) {
        $network_active = (array) get_site_option( 'active_sitewide_plugins', array() );
        $active_plugins = array_merge( $active_plugins, array_keys( $network_active ) );
    }

    foreach ( $active_plugins as $plugin_path ) {
        if ( stripos( $plugin_path, 'taxora' ) !== false && stripos( $plugin_path, 'membership' ) !== false ) {
            return true;
        }
        if ( stripos( $plugin_path, 'mbrshp-subs-panel' ) !== false ) {
            return true;
        }
    }

    return false;
}

function taxora_footer_credit_dependency_notice() {
    echo '<div class="notice notice-error is-dismissible"><p>' . 
        esc_html__( 'TaxOra - WPMU Custom Footer Credit requires the TaxOra - WPMU Membership Subscription Panel to be installed and activated.', 'taxora-wpmu-custom-footer-credit' ) . 
        '</p></div>';
}

/**
 * Enforce dependency: Deactivate if membership plugin is missing
 */
function taxora_footer_credit_enforce_dependency() {
    if ( ! is_admin() || ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    if ( ! taxora_footer_credit_check_dependency() ) {
        if ( is_plugin_active( TAXORA_FOOTER_CREDIT_BASENAME ) ) {
            deactivate_plugins( TAXORA_FOOTER_CREDIT_BASENAME );
            add_action( 'admin_notices', 'taxora_footer_credit_dependency_notice' );
            add_action( 'network_admin_notices', 'taxora_footer_credit_dependency_notice' );
            if ( isset( $_GET['activate'] ) ) {
                unset( $_GET['activate'] );
            }
        }
    }
}
add_action( 'admin_init', 'taxora_footer_credit_enforce_dependency' );

// Include the main footer credit class
require_once TAXORA_FOOTER_CREDIT_DIR . 'includes/class-footer-credit-manager.php';

/**
 * Initialize the plugin
 */
function taxora_footer_credit_init() {
    if ( ! taxora_footer_credit_check_dependency() ) {
        return;
    }

    // Load the footer credit manager
    TaxOra_Custom_Footer_Credit::get_instance();

    // Load text domain for translations
    load_plugin_textdomain( 'taxora-wpmu-custom-footer-credit', false, dirname( TAXORA_FOOTER_CREDIT_BASENAME ) . '/languages' );
}

add_action( 'plugins_loaded', 'taxora_footer_credit_init' );

/**
 * Activation hook
 */
function taxora_footer_credit_activate() {
    // Default options on activation
    if ( ! get_option( 'taxora_footer_credit_text' ) ) {
        update_option( 'taxora_footer_credit_text', 'Copyright © ' . date( 'Y' ) . ' ' . get_bloginfo( 'name' ) . '. All rights reserved.' );
    }

    if ( ! get_option( 'taxora_footer_credit_hide_divi' ) ) {
        update_option( 'taxora_footer_credit_hide_divi', 'yes' );
    }

    if ( ! get_option( 'taxora_footer_credit_hide_extra' ) ) {
        update_option( 'taxora_footer_credit_hide_extra', 'yes' );
    }
}
register_activation_hook( __FILE__, 'taxora_footer_credit_activate' );

/**
 * Deactivation hook
 */
function taxora_footer_credit_deactivate() {
    // Clean up if needed
}
register_deactivation_hook( __FILE__, 'taxora_footer_credit_deactivate' );
