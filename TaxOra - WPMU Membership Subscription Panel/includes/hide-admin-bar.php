<?php
/**
 * Hide WordPress Admin Bar on Frontend
 * Completely removes the blue admin bar from the main site frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hide WordPress admin bar completely on frontend
 */
function orabooks_hide_admin_bar_completely($show) {
    // Always hide on frontend
    if (!is_admin()) {
        return false;
    }
    return $show;
}
add_filter('show_admin_bar', 'orabooks_hide_admin_bar_completely', 999);

/**
 * Remove admin bar CSS and HTML completely
 */
function orabooks_remove_admin_bar_frontend() {
    if (!is_admin()) {
        ?>
        <style type="text/css">
            /* Remove admin bar completely */
            #wpadminbar {
                display: none !important;
                height: 0 !important;
                min-height: 0 !important;
            }
            
            /* Remove admin bar space */
            html {
                margin-top: 0 !important;
                padding-top: 0 !important;
            }
            
            html.wp-toolbar {
                margin-top: 0 !important;
                padding-top: 0 !important;
            }
            
            body {
                margin-top: 0 !important;
                padding-top: 0 !important;
            }
            
            body.admin-bar {
                margin-top: 0 !important;
                padding-top: 0 !important;
            }
            
            /* Remove any top bar elements */
            .top-bar,
            .topbar,
            .header-top,
            #top-header,
            .top-header,
            .admin-bar,
            .wp-admin-bar {
                display: none !important;
            }
            
            /* Remove any theme-specific top bars */
            .et_top_header,
            .et-top-navigation,
            .header-top-bar,
            .site-header-top,
            .main-header-top {
                display: none !important;
            }
            
            /* Remove admin bar space for mobile */
            @media screen and (max-width: 782px) {
                html {
                    margin-top: 0 !important;
                }
                #wpadminbar {
                    display: none !important;
                }
            }
        </style>
        <?php
    }
}
add_action('wp_head', 'orabooks_remove_admin_bar_frontend', 1);

/**
 * Remove admin bar scripts and styles
 */
function orabooks_remove_admin_bar_assets() {
    if (!is_admin()) {
        wp_dequeue_style('admin-bar');
        wp_dequeue_script('admin-bar');
        remove_action('wp_head', '_admin_bar_bump_cb');
    }
}
add_action('wp_enqueue_scripts', 'orabooks_remove_admin_bar_assets', 999);
