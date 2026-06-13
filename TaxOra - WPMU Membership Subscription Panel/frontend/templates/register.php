<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title(''); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('taxora-register-page'); ?>>
    <div class="taxora-register-container">
        <div class="taxora-register-form">
            <h1>Create Your Account</h1>
            <p>Join thousands of businesses using TaxOra Membership Platform</p>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('taxora_register', 'taxora_register_nonce'); ?>
                
                <div class="taxora-form-row">
                    <label for="user_login">Username</label>
                    <input type="text" name="user_login" id="user_login" required>
                </div>
                
                <div class="taxora-form-row">
                    <label for="user_email">Email</label>
                    <input type="email" name="user_email" id="user_email" required>
                </div>
                
                <div class="taxora-form-row">
                    <label for="user_pass">Password</label>
                    <input type="password" name="user_pass" id="user_pass" required>
                </div>
                
                <div class="taxora-form-row">
                    <label for="user_pass_confirm">Confirm Password</label>
                    <input type="password" name="user_pass_confirm" id="user_pass_confirm" required>
                </div>
                
                <div class="taxora-form-row">
                    <label for="first_name">First Name</label>
                    <input type="text" name="first_name" id="first_name" required>
                </div>
                
                <div class="taxora-form-row">
                    <label for="last_name">Last Name</label>
                    <input type="text" name="last_name" id="last_name" required>
                </div>
                
                <div class="taxora-form-row">
                    <label for="company_name">Company Name</label>
                    <input type="text" name="company_name" id="company_name">
                </div>
                
                <div class="taxora-form-row">
                    <label for="phone">Phone</label>
                    <input type="tel" name="phone" id="phone">
                </div>
                
                <div class="taxora-form-row">
                    <label for="plan_id">Choose Plan</label>
                    <select name="plan_id" id="plan_id">
                        <?php
                        $plans = $this->get_all_plans();
                        foreach ($plans as $plan) {
                            echo '<option value="' . $plan->id . '">' . esc_html($plan->name) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div class="taxora-form-row">
                    <label for="agree_terms">
                        <input type="checkbox" name="agree_terms" id="agree_terms" required>
                        I agree to the <a href="#terms">Terms and Conditions</a>
                    </label>
                </div>
                
                <div class="taxora-form-row">
                    <button type="submit" class="taxora-button taxora-button-primary">Create Account</button>
                </div>
            </form>
        </div>
        
        <div class="taxora-register-sidebar">
            <div class="taxora-sidebar-section">
                <h3>Why Choose TaxOra?</h3>
                <ul>
                    <li>✓ Professional features</li>
                    <li>✓ 24/7 customer support</li>
                    <li>✓ Secure payments</li>
                    <li>✓ Advanced analytics</li>
                    <li>✓ Easy integration</li>
                </ul>
            </div>
            
            <div class="taxora-sidebar-section">
                <h3>Already Have Account?</h3>
                <a href="#login" class="taxora-button taxora-button-outline">Sign In</a>
            </div>
        </div>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>
