<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title(''); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('taxora-login-page'); ?>>
    <div class="taxora-login-container">
        <div class="taxora-login-form">
            <h1>Sign In to Your Account</h1>
            <p>Access your TaxOra membership dashboard and manage your subscription</p>
            
            <?php if (isset($_GET['redirect_to'])): ?>
                <div class="taxora-login-message taxora-message-info">
                    <?php
                    $redirect_url = esc_url($_GET['redirect_to']);
                    echo 'Please sign in to access: <strong>' . parse_url($redirect_url, PHP_URL_HOST) . '</strong>';
                    ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('taxora_login', 'taxora_login_nonce'); ?>
                
                <div class="taxora-form-row">
                    <label for="user_login">Username or Email</label>
                    <input type="text" name="user_login" id="user_login" required>
                </div>
                
                <div class="taxora-form-row">
                    <label for="user_pass">Password</label>
                    <input type="password" name="user_pass" id="user_pass" required>
                </div>
                
                <div class="taxora-form-row">
                    <label class="taxora-checkbox-label">
                        <input type="checkbox" name="remember_me" id="remember_me">
                        Remember me
                    </label>
                </div>
                
                <div class="taxora-form-row">
                    <button type="submit" class="taxora-button taxora-button-primary">Sign In</button>
                </div>
                
                <div class="taxora-form-links">
                    <a href="#register" class="taxora-link">Don't have an account? Register</a>
                    <a href="#forgot-password" class="taxora-link">Forgot Password?</a>
                </div>
            </form>
        </div>
        
        <div class="taxora-login-sidebar">
            <div class="taxora-sidebar-section">
                <h3>Need Help?</h3>
                <p>Our support team is here to help you with any questions or issues.</p>
                <a href="#contact" class="taxora-button taxora-button-outline">Contact Support</a>
            </div>
            
            <div class="taxora-sidebar-section">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="#pricing">View Plans</a></li>
                    <li><a href="#register">Create Account</a></li>
                    <li><a href="#contact">Contact Us</a></li>
                </ul>
            </div>
        </div>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>
