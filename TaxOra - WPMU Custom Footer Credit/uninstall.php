<?php
/**
 * Uninstall File
 * Handles cleanup when plugin is deleted
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete all option keys set by this plugin
delete_option( 'taxora_subsite_footer_credit' );
delete_option( 'taxora_footer_credit_hide_divi' );
delete_option( 'taxora_footer_credit_hide_extra' );

// Network-wide options (if multisite)
if ( is_multisite() ) {
    delete_site_option( 'taxora_network_footer_credit' );
    delete_site_option( 'taxora_use_network_footer_credit' );
    delete_site_option( 'taxora_footer_credit_hide_divi' );
    delete_site_option( 'taxora_footer_credit_hide_extra' );
}
