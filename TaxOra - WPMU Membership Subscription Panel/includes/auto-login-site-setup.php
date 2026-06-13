<?php
/**
 * Email Functions for User Activation
 * 
 * Handles sending login credentials email after user activation
 */

if (!defined('ABSPATH')) exit;

// Track whether the current activation request completed successfully.
$orabooks_activation_success = false;

// Disable the activation page fallback handler to let WordPress handle it normally
// add_action('plugins_loaded', 'orabooks_activate_page_fallback_handler', 0);
// function orabooks_activate_page_fallback_handler() {
//     if (!isset($_SERVER['SCRIPT_NAME'])) {
//         return;
//     }
//
//     $script_name = basename($_SERVER['SCRIPT_NAME']);
//     if ($script_name !== 'wp-activate.php') {
//         return;
//     }
//
//     if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'wp-activate.php') === false) {
//         return;
//     }
//
//     // Disable the shutdown function since fallback handler is disabled
//     // @ob_start();
//     // register_shutdown_function('orabooks_activate_page_shutdown');
// }

// Disable the shutdown function since fallback handler is disabled
// function orabooks_activate_page_shutdown() {
//     // $output = '';
//     // if (ob_get_length() !== false) {
//     //     $output = trim((string) ob_get_contents());
//     // }
//
//     // $has_error = error_get_last();
//     // if (!$output || $output === '' || $has_error !== null) {
//     //     if ($has_error) {
//     //         error_log('OraBooks activation page fatal: ' . print_r($has_error, true));
//     //     }
//
//     //     while (ob_get_level() > 0) {
//     //         @ob_end_clean();
//     //     }
//
//     //     if (!headers_sent()) {
//     //         header('Content-Type: text/html; charset=UTF-8');
//     //     }
//

/**
 * Block WordPress default activation emails and send custom ones from the site
 */
add_filter('wpmu_signup_user_notification', 'orabooks_send_custom_user_activation_email', 10, 4);
function orabooks_send_custom_user_activation_email($user, $user_email, $key, $meta) {
    // Block WordPress default email
    error_log("Blocking default WordPress activation email, sending custom email to: $user_email");
    
    // Send our custom activation email
    orabooks_send_site_activation_email($user, $user_email, $key, 'user');
    
    return false; // Prevent WordPress from sending its email
}

add_filter('wpmu_signup_blog_notification', 'orabooks_send_custom_blog_activation_email', 10, 7);
function orabooks_send_custom_blog_activation_email($domain, $path, $title, $user_login, $user_email, $key, $meta) {
    // Block WordPress default email
    error_log("Blocking default WordPress blog activation email, sending custom email to: $user_email");
    
    // Send our custom activation email
    orabooks_send_site_activation_email($user_login, $user_email, $key, 'blog', $domain, $title);
    
    return false; // Prevent WordPress from sending its email
}

/**
 * Send custom activation email from the site
 */
function orabooks_send_site_activation_email($user_login, $user_email, $key, $type = 'user', $domain = '', $title = '') {
    // Get site name and URL
    $site_name = get_site_option('site_name') ?: 'TaxOra';
    
    // Get system domain-based email
    $system_domain = parse_url(network_site_url(), PHP_URL_HOST);
    $from_email = 'noreply@' . $system_domain;
    
    // Build activation URL
    if ($type === 'blog') {
        $activate_url = network_site_url("wp-activate.php?key=$key");
    } else {
        $activate_url = network_site_url("wp-activate.php?key=$key");
    }
    
    // Email subject
    $subject = sprintf('[%s] Activate Your Account', $site_name);
    
    // Email message
    if ($type === 'blog') {
        $message = sprintf("
Howdy,

Your account has been created on %s!

To activate your site and complete setup, please click the link below:

%s

Username: %s
Site: %s
Title: %s

After clicking the activation link, you'll receive another email with your login credentials.

Thanks!
--The Team @ %s
",
            $site_name,
            $activate_url,
            $user_login,
            $domain,
            $title,
            $site_name
        );
    } else {
        $message = sprintf("
Howdy,

Your account has been created on %s!

To activate your account, please click the link below:

%s

Username: %s

After clicking the activation link, you'll receive another email with your login credentials.

Thanks!
--The Team @ %s
",
            $site_name,
            $activate_url,
            $user_login,
            $site_name
        );
    }
    
    // Set headers
    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        sprintf('From: %s <%s>', $site_name, $from_email)
    );
    
    // Send email
    $result = wp_mail($user_email, $subject, $message, $headers);
    
    if ($result) {
        error_log("Custom activation email sent to $user_email with activation link: $activate_url");
    } else {
        error_log("Failed to send custom activation email to $user_email");
    }
    
    return $result;
}

/**
 * CRITICAL FIX: Also block WordPress new user notifications
 */
add_filter('wp_new_user_notification_email_admin', 'orabooks_block_admin_new_user_email', 999, 2);
function orabooks_block_admin_new_user_email($wp_new_user_notification_email_admin, $user) {
    error_log("BLOCKING admin new user notification for: {$user->user_email}");
    return false;
}

add_filter('wp_new_user_notification_email', 'orabooks_block_new_user_email', 999, 2);
function orabooks_block_new_user_email($wp_new_user_notification_email, $user) {
    error_log("BLOCKING new user notification for: {$user->user_email}");
    return false;
}

/**
 * Block WordPress default password change/reset emails
 */
add_filter('wp_password_change_notification_email', 'orabooks_block_password_change_email', 999, 2);
function orabooks_block_password_change_email($wp_password_change_notification_email, $user, $userdata) {
    error_log("BLOCKING WordPress password change notification for: {$user->user_email}");
    return false;
}

add_filter('send_password_change_email', 'orabooks_block_password_change_email_admin', 999, 3);
function orabooks_block_password_change_email_admin($send, $user, $userdata) {
    error_log("BLOCKING WordPress password change admin notification for: {$user->user_email}");
    return false;
}

add_filter('retrieve_password_message', 'orabooks_block_password_reset_message', 999, 4);
function orabooks_block_password_reset_message($message, $key, $user_login, $user_data) {
    error_log("BLOCKING WordPress password reset message for: {$user_data->user_email}");
    return false;
}

add_filter('retrieve_password_notification_email', 'orabooks_block_password_reset_email', 999, 2);
function orabooks_block_password_reset_email($retrieve_password_notification_email, $user, $key) {
    error_log("BLOCKING WordPress password reset notification for: {$user->user_email}");
    return false;
}

/**
 * Block WordPress user activation emails (for multisite)
 */
add_filter('wpmu_signup_user_notification_email', 'orabooks_block_signup_notification_email', 999, 2);
function orabooks_block_signup_notification_email($wpmu_signup_user_notification_email, $user_login, $user_email, $key, $meta) {
    error_log("BLOCKING WordPress signup notification for: {$user_email}");
    return false;
}

add_filter('wpmu_welcome_user_notification_email', 'orabooks_block_welcome_notification_email', 999, 2);
function orabooks_block_welcome_notification_email($wpmu_welcome_user_notification_email, $user_id, $password, $meta, $user_login) {
    $user = get_userdata($user_id);
    error_log("BLOCKING WordPress welcome notification for: {$user->user_email}");
    return false;
}

/**
 * CRITICAL: Block the actual welcome notification ACTIONS (not just filters)
 * WordPress sends emails via these actions after activation
 */
add_action('wpmu_welcome_notification', 'orabooks_block_welcome_action', 1, 5);
function orabooks_block_welcome_action($user_id, $password, $meta) {
    error_log("BLOCKING wpmu_welcome_notification action for User ID: {$user_id}");
    // Remove all other callbacks for this action to prevent WordPress from sending email
    remove_all_actions('wpmu_welcome_notification', 10);
    return false;
}

add_action('wpmu_welcome_user_notification', 'orabooks_block_welcome_user_action', 1, 3);
function orabooks_block_welcome_user_action($user_id, $password, $meta) {
    error_log("BLOCKING wpmu_welcome_user_notification action for User ID: {$user_id}");
    // Remove all other callbacks for this action
    remove_all_actions('wpmu_welcome_user_notification', 10);
    return false;
}

add_filter('wpmu_welcome_notification', 'orabooks_prevent_welcome_notification', 1, 5);
function orabooks_prevent_welcome_notification($user_id, $password, $meta) {
    error_log("PREVENTING wpmu_welcome_notification filter for User ID: {$user_id}");
    return false;
}

/**
 * Block blog welcome notifications
 */
add_action('wpmu_welcome_blog_notification', 'orabooks_block_blog_welcome_action', 1);
function orabooks_block_blog_welcome_action() {
    error_log("BLOCKING wpmu_welcome_blog_notification action");
    // Remove all other callbacks
    remove_all_actions('wpmu_welcome_blog_notification', 10);
    return false;
}

/**
 * Simplified email blocking - only block WordPress core emails
 */
add_filter('wp_mail', 'orabooks_block_all_wp_emails', 999, 1);
function orabooks_block_all_wp_emails($args) {
    $subject = $args['subject'] ?? '';
    $to = $args['to'] ?? '';
    
    // Allow all emails from our system domain
    $system_domain = parse_url(network_site_url(), PHP_URL_HOST);
    $from_email = $args['headers'] ?? '';
    
    if (is_array($from_email)) {
        $from_email = implode('', $from_email);
    }
    
    $from_email = (string)$from_email;
    if (strpos($from_email, $system_domain) !== false) {
        error_log("ALLOWING system email: Subject='{$subject}' To='{$to}'");
        return $args;
    }
    
    // Block WordPress default emails
    $wordpress_subjects = array(
        'New User Registration',
        'Password Reset',
        'Your password has been changed',
        'New Site Created'
    );
    
    foreach ($wordpress_subjects as $wp_subject) {
        if (strpos((string)$subject, $wp_subject) !== false) {
            error_log("BLOCKING WordPress email: Subject='{$subject}' To='{$to}'");
            return false;
        }
    }
    
    error_log("ALLOWING email: Subject='{$subject}' To='{$to}'");
    return $args;
}

/**
 * Clear pending signups if user tries to register again with same email/domain
 */
add_filter('wpmu_validate_user_signup', 'orabooks_clear_pending_user_signup_on_retry', 10, 1);
function orabooks_clear_pending_user_signup_on_retry($result) {
    if (isset($result['errors']) && $result['errors']->get_error_message('user_email')) {
        $user_email = $result['user_email'];
        global $wpdb;
        
        $pending = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->signups} WHERE user_email = %s AND active = 0",
            $user_email
        ));
        
        if ($pending) {
            error_log("Clearing pending USER signup for $user_email to allowed resend.");
            $wpdb->delete($wpdb->signups, array('user_email' => $user_email, 'active' => 0));
            $result['errors']->remove('user_email');
        }
    }
    return $result;
}

add_filter('wpmu_validate_blog_signup', 'orabooks_clear_pending_blog_signup_on_retry', 10, 1);
function orabooks_clear_pending_blog_signup_on_retry($result) {
    if (isset($result['errors']) && $result['errors']->get_error_message('blogname')) {
        $blogname = $result['blogname'];
        global $wpdb;
        
        $domain = $blogname . '.' . DOMAIN_CURRENT_SITE;
        
        $pending = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->signups} WHERE domain = %s AND active = 0",
            $domain
        ));
        
        if ($pending) {
            error_log("Clearing pending BLOG signup for $domain to allowed resend.");
            $wpdb->delete($wpdb->signups, array('domain' => $domain, 'active' => 0));
            $result['errors']->remove('blogname');
        }
    }
    return $result;
}

/**
 * Send login credentials email AFTER user activates account
 */
add_action('wpmu_activate_user', 'orabooks_send_credentials_after_activation', 10, 3);
function orabooks_send_credentials_after_activation($user_id, $password, $meta) {
    global $orabooks_activation_success;
    $orabooks_activation_success = true;

    error_log("User activated - sending credentials email to User ID $user_id");
    
    // Check if we already sent email to avoid duplicates
    $email_sent = get_user_meta($user_id, 'orabooks_credentials_email_sent', true);
    if ($email_sent) {
        error_log("Credentials email already sent to User ID $user_id - skipping");
        return;
    }
    
    // Get user's primary blog if they have one
    $primary_blog = get_active_blog_for_user($user_id);
    if ($primary_blog) {
        $blog_id = $primary_blog->blog_id;
        $domain = $primary_blog->domain;
        
        error_log("User has primary blog: Blog ID $blog_id, Domain $domain");
        
        // Send login credentials email using the activation password if available
        $result = orabooks_send_credentials_email($user_id, $domain, $blog_id, $password);
        
        if ($result) {
            update_user_meta($user_id, 'orabooks_credentials_email_sent', true);
            error_log("Login credentials email sent successfully to User ID $user_id");
        } else {
            error_log("Failed to send login credentials email to User ID $user_id");
        }
    } else {
        error_log("User has no primary blog - sending welcome credentials");
        
        // Send welcome email with credentials using the activation password if available
        $result = orabooks_send_welcome_credentials_email($user_id, $password);
        
        if ($result) {
            update_user_meta($user_id, 'orabooks_credentials_email_sent', true);
            error_log("Welcome credentials email sent successfully to User ID $user_id");
        } else {
            error_log("Failed to send welcome credentials email to User ID $user_id");
        }
    }
}

add_action('wpmu_activate_blog', 'orabooks_send_credentials_after_blog_activation', 10, 5);
function orabooks_send_credentials_after_blog_activation($blog_id, $user_id, $password, $title, $meta) {
    global $orabooks_activation_success;
    $orabooks_activation_success = true;

    error_log("Blog activated - sending credentials email for Blog ID $blog_id, User ID $user_id");
    
    // Check if we already sent email to avoid duplicates
    $email_sent = get_user_meta($user_id, 'orabooks_credentials_email_sent', true);
    if ($email_sent) {
        error_log("Credentials email already sent to User ID $user_id - skipping");
        return;
    }
    
    $blog_details = get_blog_details($blog_id);
    $domain = $blog_details->domain;
    
    error_log("Blog activation details: Domain $domain");
    
    // Send login credentials email using the activation password if available
    $result = orabooks_send_credentials_email($user_id, $domain, $blog_id, $password);
    
    if ($result) {
        update_user_meta($user_id, 'orabooks_credentials_email_sent', true);
        error_log("Login credentials email sent successfully for blog activation to User ID $user_id");
    } else {
        error_log("Failed to send login credentials email for blog activation to User ID $user_id");
    }
}

/**
 * Send login credentials email with password
 */
function orabooks_send_credentials_email($user_id, $domain, $blog_id = null, $password = '') {
    $user = get_userdata($user_id);
    if (!$user) {
        error_log("User not found: $user_id");
        return false;
    }
    
    // Use the activation password when provided; otherwise generate a new password.
    if (empty($password)) {
        $password = wp_generate_password(12, false);
        wp_set_password($password, $user_id);
    } else {
        error_log("Reusing activation password for User ID $user_id");
    }
    
    // Get site URL and name
    $site_name = get_site_option('site_name') ?: 'TaxOra';
    
    // Get system domain-based email
    $system_domain = parse_url(network_site_url(), PHP_URL_HOST);
    $from_email = 'noreply@' . $system_domain;
    
    $site_url = $blog_id ? get_site_url($blog_id) : 'https://' . $domain;
    $login_url = $blog_id ? get_site_url($blog_id, 'login') : 'https://' . $domain . '/login';
    
    // Email subject
    $subject = sprintf('[%s] Your Login Credentials', $site_name);
    
    // Email message
    $message = sprintf("
Howdy %s,

Your account has been successfully activated!

You can log in to the administrator account with the following information:

Username: %s
Password: %s

Your Site: %s
Login Here: %s

IMPORTANT: Please save these credentials securely.

Thanks!
--The Team @ %s
",
        $user->user_login,
        $user->user_login,
        $password,
        $site_url,
        $login_url,
        $site_name
    );
    
    // Set headers
    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        sprintf('From: %s <%s>', $site_name, $from_email)
    );
    
    // Send email
    $result = wp_mail($user->user_email, $subject, $message, $headers);
    
    if ($result) {
        error_log("Login credentials email sent to {$user->user_email} for site {$domain}");
    } else {
        error_log("Failed to send login credentials email to {$user->user_email}");
    }
    
    return $result;
}

/**
 * Send welcome credentials email for users without a blog
 */
function orabooks_send_welcome_credentials_email($user_id, $password = '') {
    $user = get_userdata($user_id);
    if (!$user) {
        error_log("User not found: $user_id");
        return false;
    }
    
    // Use the activation password when provided; otherwise generate a new password.
    if (empty($password)) {
        $password = wp_generate_password(12, false);
        wp_set_password($password, $user_id);
    } else {
        error_log("Reusing activation password for welcome message for User ID $user_id");
    }
    
    // Get site info
    $site_name = get_site_option('site_name') ?: 'TaxOra';
    
    // Get system domain-based email
    $system_domain = parse_url(network_site_url(), PHP_URL_HOST);
    $from_email = 'noreply@' . $system_domain;
    
    $main_site_url = network_site_url();
    $login_url = network_site_url('login');
    
    // Email subject
    $subject = sprintf('[%s] Your Login Credentials', $site_name);
    
    // Email message
    $message = sprintf("
Howdy %s,

Welcome to %s! Your account has been successfully activated.

You can log in to the administrator account with the following information:

Username: %s
Password: %s

Login Here: %s

Once logged in, you can create your own site from your dashboard.

IMPORTANT: Please save these credentials securely.

Thanks!
--The Team @ %s
",
        $user->user_login,
        $site_name,
        $user->user_login,
        $password,
        $login_url,
        $site_name
    );
    
    // Set headers
    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        sprintf('From: %s <%s>', $site_name, $from_email)
    );
    
    // Send email
    $result = wp_mail($user->user_email, $subject, $message, $headers);
    
    if ($result) {
        error_log("Welcome credentials email sent to {$user->user_email}");
    } else {
        error_log("Failed to send welcome credentials email to {$user->user_email}");
    }
    
    return $result;
}

// Disable activation hooks to let WordPress handle activation page normally
// add_action('activate_header', 'orabooks_activation_header_styles');
// function orabooks_activation_header_styles() {
//     // Let theme handle default styling
//     return;
// }
//
// // Remove activation confirmation message
// add_action('activate_form', 'orabooks_show_activation_confirmation_message');
// function orabooks_show_activation_confirmation_message() {
//     // Remove the activation completed message
//     return;
// }
//
// // Remove activation footer message
// add_action('activate_footer', 'orabooks_show_activation_footer_message');
// function orabooks_show_activation_footer_message() {
//     // Remove the activation completed message
//     return;
// }
