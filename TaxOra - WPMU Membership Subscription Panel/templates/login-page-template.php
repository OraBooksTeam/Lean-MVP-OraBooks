<?php
/**
 * Login Page Template
 * Template for displaying WordPress login on custom /login page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get page header
get_header();

// Display login content
?>
<div class="orabooks-login-page">
    <div class="orabooks-login-container">
        <div class="orabooks-login-wrapper">
            <div class="orabooks-login-header">
                <h1><?php echo get_bloginfo('name'); ?></h1>
                <p class="login-description"><?php _e('Sign in to your account', 'orabooks'); ?></p>
            </div>
            
            <?php
            // Display custom login widget content
            echo do_shortcode('[login_widget]');
            ?>
            
            <div class="orabooks-login-footer">
                <p class="login-links">
                    <?php if (get_option('users_can_register')) : ?>
                        <a href="<?php echo esc_url(wp_registration_url()); ?>" class="register-link">
                            <?php _e('Register', 'orabooks'); ?>
                        </a>
                    <?php endif; ?>
                    
                    <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="lost-password-link">
                        <?php _e('Lost your password?', 'orabooks'); ?>
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<style type="text/css">
    .orabooks-login-page {
        max-width: 400px;
        margin: 50px auto;
        padding: 20px;
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    
    .orabooks-login-wrapper {
        position: relative;
    }
    
    .orabooks-login-header {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .orabooks-login-header h1 {
        font-size: 24px;
        font-weight: 600;
        color: #333;
        margin: 0 0 10px 0;
    }
    
    .orabooks-login-header .login-description {
        color: #666;
        font-size: 14px;
        margin: 0;
    }
    
    /* WordPress login form styling */
    .orabooks-login-page .login-form {
        margin: 0;
    }
    
    .orabooks-login-page .login-form label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #333;
        font-size: 14px;
    }
    
    .orabooks-login-page .login-form input[type="text"],
    .orabooks-login-page .login-form input[type="password"] {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        margin-bottom: 16px;
        box-sizing: border-box;
        transition: border-color 0.3s ease;
    }
    
    .orabooks-login-page .login-form input[type="text"]:focus,
    .orabooks-login-page .login-form input[type="password"]:focus {
        border-color: #0073aa;
        outline: none;
        box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
    }
    
    .orabooks-login-page .login-form .forgetmenot {
        margin-bottom: 20px;
    }
    
    .orabooks-login-page .login-form .forgetmenot label {
        display: flex;
        align-items: center;
        font-size: 14px;
        color: #666;
        cursor: pointer;
    }
    
    .orabooks-login-page .login-form .forgetmenot input[type="checkbox"] {
        margin-right: 8px;
    }
    
    .orabooks-login-page .login-form input[type="submit"] {
        width: 100%;
        padding: 12px 20px;
        background: #0073aa;
        border: none;
        border-radius: 4px;
        color: #fff;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    
    .orabooks-login-page .login-form input[type="submit"]:hover {
        background: #005a87;
    }
    
    .orabooks-login-footer {
        text-align: center;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }
    
    .orabooks-login-footer .login-links {
        font-size: 14px;
        color: #666;
    }
    
    .orabooks-login-footer .login-links a {
        color: #0073aa;
        text-decoration: none;
        transition: color 0.3s ease;
        margin: 0 10px;
    }
    
    .orabooks-login-footer .login-links a:first-child {
        margin-left: 0;
    }
    
    .orabooks-login-footer .login-links a:last-child {
        margin-right: 0;
    }
    
    .orabooks-login-footer .login-links a:hover {
        color: #005a87;
        text-decoration: underline;
    }
    
    /* Error message styling */
    .orabooks-login-page .login-error,
    .orabooks-login-page .message {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        color: #856404;
        padding: 12px;
        margin-bottom: 20px;
        border-radius: 4px;
        font-size: 14px;
    }
    
    /* Success message styling */
    .orabooks-login-page .message.success {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }
    
    /* Responsive design */
    @media screen and (max-width: 480px) {
        .orabooks-login-page {
            margin: 20px;
            padding: 15px;
        }
        
        .orabooks-login-header h1 {
            font-size: 20px;
        }
    }
    
    /* Hide default WordPress login elements */
    .orabooks-login-page .login-form p {
        margin-bottom: 16px;
    }
    
    .orabooks-login-page .login-form #nav,
    .orabooks-login-page .login-form #backtoblog {
        display: none;
    }
</style>

<?php
// Get page footer
get_footer();
?>
