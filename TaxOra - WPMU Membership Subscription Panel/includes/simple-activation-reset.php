<?php
/**
 * Simple Activation Page Reset
 * Restores wp-activate.php to original WordPress state
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Remove all custom modifications from activation page
 */
function orabooks_reset_activation_page() {
    global $pagenow;
    
    // Only run on activation page
    if ($pagenow !== 'wp-activate.php' && strpos((string)$_SERVER['REQUEST_URI'], 'wp-activate.php') === false) {
        return;
    }
    
    ?>
    <style id="reset-activation-page">
        /* Reset everything to default WordPress state */
        
        /* Fix header covering content */
        #main-header,
        #top-header,
        header,
        .site-header {
            position: relative !important;
            top: auto !important;
            margin-bottom: 0 !important;
            z-index: auto !important;
        }
        
        /* Ensure content is not hidden and remove extra space */
        body {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        
        /* Remove extra space above header */
        html {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        
        #main-header,
        #top-header,
        header,
        .site-header {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        
        /* Remove all custom positioning */
        .et_fixed_nav {
            position: relative !important;
            top: 0 !important;
        }
        
        /* Hide problematic elements */
        .widget_archive,
        .widget_categories,
        .widget_search,
        .widget {
            display: none !important;
        }
        
        /* Ensure main content area is visible and centered */
        #content,
        .entry-content,
        .main-content,
        #main,
        .container,
        #et-main-area {
            display: block !important;
            position: relative !important;
            z-index: 1 !important;
            background: #fff !important;
            padding: 20px !important;
            margin: 20px auto !important;
            max-width: 1080px !important; /* Match Divi's default container width */
            width: 100% !important;
        }
        
        /* Remove sidebar completely */
        #sidebar,
        .sidebar {
            display: none !important;
        }
        
        /* Make content area full width */
        #left-area,
        #primary {
            width: 100% !important;
            float: none !important;
            margin: 0 !important;
        }
        
        /* Ensure forms are visible */
        form {
            display: block !important;
            background: #fff !important;
            padding: 20px !important;
            border: 1px solid #ddd !important;
        }
        
        /* Remove dark overlays */
        .et_overlay,
        .et_pb_overlay,
        .et_pb_section {
            background: transparent !important;
        }
    </style>
    <?php
}
add_action('wp_head', 'orabooks_reset_activation_page', 9999);
