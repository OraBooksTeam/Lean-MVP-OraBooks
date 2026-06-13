<?php

/**
 * Plugin Name: OraBooks - WPMU Default Backend Login Logo
 * Plugin URI: https://www.enest.com.bd/plugins/wpmu-tob-dfltloginlogo
 * Description: Quickly Customize the Default Login Logo.
 * Version: 1/25
 * Author: Engr. AnwarIT CASDP and Nasir Uddin
 * Author URI: https://www.anwarit.com
 * Creditse: TOB Developer Team
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpmu tob dfltloginlogo
 *
 * Minimum PHP: 8.0.30
 * Minimum WP: 6.8
 * @author 		Engr.AnwarIT- CASDP/TaxOra
 * @package		WPMU Default Logo
 *
 * @copyright 	Copyright (c) 2025, TaxOra LLC USA
 * Orabooks Addon: true
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check for OraBooks Membership Plugin
 */
function orabooks_login_logo_check_dependency() {
    // 1. Check for common OraBooks functions/classes (Best)
    if ( function_exists( 'orabooks_register_addon' ) || 
         function_exists( 'orabooks_is_feature_enabled' ) ||
         class_exists( 'OraBooks' ) || 
         defined( 'ORABOOKS_VERSION' ) ) {
        return true;
    }

    // 2. Fallback check for active plugins list
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $active_plugins = (array) get_option( 'active_plugins', array() );
    if ( is_multisite() ) {
        $network_active = (array) get_site_option( 'active_sitewide_plugins', array() );
        $active_plugins = array_merge( $active_plugins, array_keys( $network_active ) );
    }

    foreach ( $active_plugins as $plugin_path ) {
        // Look for membership plugin specifically
        if ( stripos( $plugin_path, 'taxora' ) !== false && stripos( $plugin_path, 'membership' ) !== false ) {
            return true;
        }
        // Broad search for any "OraBooks" core plugin if membership is integrated
        if ( stripos( $plugin_path, 'taxora-membership' ) !== false ) {
            return true;
        }
    }

    return false;
}

function orabooks_login_logo_membership_notice() {
    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'OraBooks - WPMU-Default-Backend-Login-Logo requires the TaxOra - WPMU Membership Subscription Panel to be installed and activated.', 'wpmu tob dfltloginlogo' ) . '</p></div>';
}

/**
 * Enforce dependency: Deactivate if membership plugin is missing
 */
function orabooks_login_logo_enforce_dependency() {
    // Only run this in admin and if we're not currently activating/deactivating
    if ( ! is_admin() || ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    // Delay check until all plugins are loaded (admin_init is late enough)
    if ( ! orabooks_login_logo_check_dependency() ) {
        $plugin_file = plugin_basename( __FILE__ );
        if ( is_plugin_active( $plugin_file ) ) {
            deactivate_plugins( $plugin_file );
            add_action( 'admin_notices', 'orabooks_login_logo_membership_notice' );
            add_action( 'network_admin_notices', 'orabooks_login_logo_membership_notice' );
            // Suppress the "Plugin activated" message
            if ( isset( $_GET['activate'] ) ) {
                unset( $_GET['activate'] );
            }
        }
    }
}
add_action( 'admin_init', 'orabooks_login_logo_enforce_dependency' );

// Only run on subsites
if ( is_main_site() ) {
    return;
}

// Add custom CSS to login page
add_action( 'login_enqueue_scripts', 'taxora_custom_login_logo' );

// Register Settings
add_action('admin_menu', 'taxora_login_logo_menu');
function taxora_login_logo_menu() {
    add_options_page(
        'Login Logo Settings',   
        'Login Logo',
        'manage_options',
        'taxora-login-logo',
        'taxora_login_logo_page_html'
    );
}

add_action('admin_enqueue_scripts', 'taxora_login_logo_enqueue_scripts');
function taxora_login_logo_enqueue_scripts($hook) {
    if ($hook != 'settings_page_taxora-login-logo') {
        return;
    }
    wp_enqueue_media();
}

add_action('admin_init', 'taxora_login_logo_settings');
function taxora_login_logo_settings() {
    register_setting('taxora_login_logo_group', 'taxora_custom_login_logo');

    add_settings_section(
        'taxora_login_logo_section',
        'Custom Login Logo',
        null, // No callback needed for simple section description
        'taxora-login-logo'
    );

    add_settings_field(
        'taxora_custom_login_logo',
        'Logo URL',
        'taxora_custom_login_logo_callback',
        'taxora-login-logo',
        'taxora_login_logo_section'
    );
}

function taxora_custom_login_logo_callback() {
    $value = get_option('taxora_custom_login_logo');
    echo '<input type="text" name="taxora_custom_login_logo" id="taxora_custom_login_logo" value="' . esc_attr($value) . '" class="regular-text" placeholder="Enter Logo URL" />';
    echo '<input type="button" class="button button-secondary" value="Upload Image" id="taxora_upload_logo_button" />';
    echo '<p class="description">Upload or select an image for the login logo. Leave empty to use the default.</p>';
    echo '<div id="taxora_login_logo_preview" style="margin-top:10px;">';
    if ($value) {
        echo '<img src="' . esc_url($value) . '" style="max-width: 150px; height: auto;" />';
    }
    echo '</div>';
    
    // Inline JS for simplicity
    ?>
    <script>
    jQuery(document).ready(function($){
        var mediaUploader;
        $('#taxora_upload_logo_button').click(function(e) {
            e.preventDefault();
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            mediaUploader = wp.media.frames.file_frame = wp.media({
                title: 'Choose Login Logo',
                button: {
                    text: 'Choose Logo'
                },
                multiple: false
            });
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#taxora_custom_login_logo').val(attachment.url);
                $('#taxora_login_logo_preview').html('<img src="' + attachment.url + '" style="max-width: 150px; height: auto;" />');
            });
            mediaUploader.open();
        });
    });
    </script>
    <?php
}

function taxora_login_logo_page_html() {
    ?>
    <div class="wrap">
        <h1>Login Logo Settings</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('taxora_login_logo_group');
            do_settings_sections('taxora-login-logo');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function taxora_custom_login_logo() {
    $custom_logo = get_option('taxora_custom_login_logo');

    if ( ! empty( $custom_logo ) ) {
        $logo_url = $custom_logo;
    } else {
        // Default for subsites (since this plugin only runs on subsites)
        $logo_url = plugin_dir_url( __FILE__ ) . 'assets/orabooks_logo.png';
    }
    ?>
    <style>
        /* Replace login logo */
        #login h1 a {
            background-image: url('<?php echo esc_url($logo_url); ?>');
            background-size: contain; /* Adjusts image inside box */
            width: 150px;             /* Change width */
            height: 150px;            /* Change height */
            margin-bottom: -20px;
        }
    </style>
    <?php
}

// Change login logo URL
add_filter( 'login_headerurl', 'taxora_custom_login_headerurl' );
function taxora_custom_login_headerurl() {
    return 'https://www.taxorausa.com/';
}
