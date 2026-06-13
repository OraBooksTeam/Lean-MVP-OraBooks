<?php
/**
 * Match Activation Page to Signup Page Styling
 * Makes wp-activate.php look exactly like wp-signup.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Apply signup page styling to activation page
 */
function orabooks_match_signup_to_activation() {
    global $pagenow;
    
    // Only run on activation page
    if ($pagenow !== 'wp-activate.php' && strpos((string)$_SERVER['REQUEST_URI'], 'wp-activate.php') === false) {
        return;
    }
    
    ?>
    <style id="match-signup-activation">
        /* Apply signup page body class styling */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            line-height: 1.6 !important;
            color: #333 !important;
            background: #fff !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        /* Reset all spacing */
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
        }
        
        /* Header positioning - match signup page */
        #main-header,
        #top-header,
        header,
        .site-header {
            position: relative !important;
            top: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            z-index: auto !important;
        }
        
        /* Main container - match signup page layout */
        .container,
        #et-main-area,
        #content,
        .main-content {
            max-width: 1080px !important;
            margin: 0 auto !important;
            padding: 40px 20px !important;
            background: #fff !important;
            position: relative !important;
        }
        
        /* Content area - full width like signup */
        #left-area,
        #primary,
        #content {
            width: 100% !important;
            float: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        /* Hide sidebar completely */
        #sidebar,
        .sidebar {
            display: none !important;
        }
        
        /* Hide all blog widgets */
        .widget_archive,
        .widget_categories,
        .widget_search,
        .widget,
        .widget-area {
            display: none !important;
        }
        
        /* Form styling - match signup page */
        form {
            background: #f8f9fa !important;
            padding: 30px !important;
            border-radius: 8px !important;
            border: 1px solid #e9ecef !important;
            margin: 20px 0 !important;
            max-width: 600px !important;
            margin-left: auto !important;
            margin-right: auto !important;
        }
        
        /* Input fields - match signup styling */
        input[type="text"],
        input[type="email"],
        input[type="password"],
        select,
        textarea {
            width: 100% !important;
            padding: 12px 16px !important;
            border: 1px solid #ddd !important;
            border-radius: 4px !important;
            font-size: 16px !important;
            background: #fff !important;
            margin-bottom: 15px !important;
        }
        
        /* Submit button - match signup styling */
        input[type="submit"],
        button[type="submit"] {
            background: #0073aa !important;
            color: #fff !important;
            padding: 12px 24px !important;
            border: none !important;
            border-radius: 4px !important;
            font-size: 16px !important;
            cursor: pointer !important;
            font-weight: 600 !important;
        }
        
        /* Headings - match signup page */
        h1, h2, h3 {
            color: #333 !important;
            margin-bottom: 20px !important;
            font-weight: 600 !important;
        }
        
        /* Remove dark overlays */
        .et_overlay,
        .et_pb_overlay,
        .dark-overlay,
        .et_pb_section {
            background: transparent !important;
        }
        
        /* Remove fixed positioning */
        .et_fixed_nav {
            position: relative !important;
            top: 0 !important;
        }
        
        /* Footer - match signup page */
        footer,
        .site-footer,
        #main-footer {
            margin-top: 40px !important;
            padding: 20px 0 !important;
            background: #f8f9fa !important;
            text-align: center !important;
        }
    </style>
    <?php
}
add_action('wp_head', 'orabooks_match_signup_to_activation', 9999);
