<?php
/**
 * Login Page Display
 * Displays wp-login.php content on custom /login page
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display WordPress login form on custom login page
 * Redirected to default WordPress wp-login.php for standard styling
 */
function orabooks_display_login_page() {
    // Disabled - login page now redirects to default WordPress wp-login.php
    return;
}

/**
 * Get WordPress login form HTML
 */
function orabooks_get_wp_login_form() {
    return do_shortcode('[login_widget]');
}

/**
 * Custom login styles - using WordPress default CSS (all custom styles removed)
 */
function orabooks_add_login_page_styles() {
    // Using default WordPress login page styling
    return;
}

/**
 * Handle login errors and display them
 */
function orabooks_handle_login_errors() {
    if (isset($_GET['login']) && $_GET['login'] === 'failed') {
        $error_message = '<div class="login-error">' . __('Invalid username or password. Please try again.', 'orabooks') . '</div>';
        return $error_message;
    }
    
    if (isset($_GET['loggedout']) && $_GET['loggedout'] === 'true') {
        $success_message = '<div class="message success">' . __('You have been logged out successfully.', 'orabooks') . '</div>';
        return $success_message;
    }
    
    if (isset($_GET['registration']) && $_GET['registration'] === 'complete') {
        $success_message = '<div class="message success">' . __('Registration complete. Please log in.', 'orabooks') . '</div>';
        return $success_message;
    }
    
    return '';
}

/**
 * Hook into WordPress to display login page
 */
add_action('template_redirect', 'orabooks_display_login_page');

/**
 * Filter login form to add our error messages
 */
add_filter('login_message', 'orabooks_handle_login_errors');


/**
 * Create a login page shortcode
 */
function orabooks_login_shortcode($atts) {
    // Shortcode attributes
    $atts = shortcode_atts(array(
        'redirect' => home_url('/dashboard'),
        'show_register' => 'true',
        'show_lostpassword' => 'true'
    ), $atts);
    
    // Check if user is logged in
    if (is_user_logged_in()) {
        return '<p>' . __('You are already logged in.', 'orabooks') . '</p>';
    }
    
    // Get login form
    $login_form = orabooks_get_wp_login_form();
    
    // Build login page HTML
    $html = '<div class="orabooks-login-container">';
    $html .= '<div class="orabooks-login-wrapper">';
    
    $html .= '<div class="orabooks-login-header">';
    $html .= '<h1>' . get_bloginfo('name') . '</h1>';
    $html .= '<p class="login-description">' . __('Sign in to your account', 'orabooks') . '</p>';
    $html .= '</div>';
    
    $html .= $login_form;
    
    if ($atts['show_register'] === 'true' || $atts['show_lostpassword'] === 'true') {
        $html .= '<div class="orabooks-login-footer">';
        $html .= '<p class="login-links">';
        
        if ($atts['show_register'] === 'true' && get_option('users_can_register')) {
            $register_url = wp_registration_url();
            $html .= '<a href="' . esc_url($register_url) . '" class="register-link">' . __('Register', 'orabooks') . '</a>';
        }
        
        if ($atts['show_lostpassword'] === 'true') {
            if ($atts['show_register'] === 'true') {
                $html .= ' | ';
            }
            $lostpassword_url = wp_lostpassword_url();
            $html .= '<a href="' . esc_url($lostpassword_url) . '" class="lost-password-link">' . __('Lost your password?', 'orabooks') . '</a>';
        }
        
        $html .= '</p>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    // Add styles
    $html .= '<style type="text/css">';
    $html .= file_get_contents(__FILE__, false, null, null, 0, filesize(__FILE__) - strpos(file_get_contents(__FILE__), '/* Add custom styles for login page */'));
    $html .= '</style>';
    
    return $html;
}

// Register shortcode - replaced by [login_widget] from separate plugin
// add_shortcode('orabooks_login', 'orabooks_login_shortcode');

/**
 * Add rewrite rule for /login page
 */
function orabooks_login_rewrite_rules($rules) {
    $new_rules = array(
        'login/?$' => 'index.php?orabooks_login=1',
    );
    $rules = $new_rules + $rules;
    return $rules;
}

add_filter('rewrite_rules_array', 'orabooks_login_rewrite_rules');

/**
 * Handle login query var
 */
function orabooks_login_query_vars($query_vars) {
    $query_vars[] = 'orabooks_login';
    return $query_vars;
}

add_filter('query_vars', 'orabooks_login_query_vars');

/**
 * Template redirect for login page
 */
function orabooks_login_template_redirect($template) {
    if (get_query_var('orabooks_login')) {
        // Look for login template in theme
        $login_template = locate_template(array('orabooks-login.php', 'login-page.php'));
        
        if ($login_template) {
            return $login_template;
        }
        
        // Use default template
        return dirname(dirname(dirname(__FILE__))) . '/templates/login-page-template.php';
    }
    
    return $template;
}

add_filter('template_include', 'orabooks_login_template_redirect');

// Flush rewrite rules on activation
register_activation_hook(__FILE__, 'orabooks_login_flush_rules');

function orabooks_login_flush_rules() {
    orabooks_login_rewrite_rules(get_option('rewrite_rules'));
    flush_rewrite_rules();
}
?>
