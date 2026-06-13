<?php
/**
 * Clean Signup Styling
 * Simple, clean CSS for wp-signup.php page
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add clean CSS to wp-signup.php
 */
function orabooks_clean_signup_styling() {
    // Only apply to wp-signup.php
    global $pagenow;
    if ($pagenow !== 'wp-signup.php') {
        return;
    }
    
    ?>
    <style>
        /* Body styling - subtle background */
        body {
            background: #f8fafc;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        
        /* Main content wrapper */
        #content, .wrap, .mu_register {
            background: #ffffff;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            max-width: 600px;
            width: 100%;
            margin: 40px auto;
            border: 1px solid #e2e8f0;
        }
        
        /* Signup form styling */
        .mu_register {
            background: transparent;
            padding: 0;
            margin: 0;
            box-shadow: none;
            border: none;
            max-width: 100%;
        }
        
        .mu_register h2 {
            color: #1a202c;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 16px;
            text-align: center;
            line-height: 1.2;
        }
        
        .mu_register p {
            color: #4a5568;
            font-size: 16px;
            margin-bottom: 32px;
            text-align: center;
            line-height: 1.6;
        }
        
        .mu_register label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #2d3748;
            font-size: 14px;
        }
        
        .mu_register input[type="text"],
        .mu_register input[type="email"],
        .mu_register input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s ease;
            box-sizing: border-box;
            background: white;
        }
        
        .mu_register input:focus {
            outline: none;
            border-color: #3b82f6;
        }
        
        .mu_register .submit {
            background: #3b82f6;
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
            width: 100%;
            margin-top: 16px;
        }
        
        .mu_register .submit:hover {
            background: #2563eb;
        }
        
        /* Error styling */
        .error, .mu_register .error {
            background: #fed7d7;
            border: 1px solid #feb2b2;
            color: #c53030;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        /* Success styling */
        .mu_register .updated {
            background: #c6f6d5;
            border: 1px solid #9ae6b4;
            color: #22543d;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        
        /* Responsive design - Mobile */
        @media (max-width: 768px) {
            body {
                padding: 0;
            }
            
            #content, .wrap, .mu_register {
                margin: 20px 16px;
                padding: 24px 20px;
                border-radius: 12px;
            }
            
            .mu_register h2 {
                font-size: 24px;
                margin-bottom: 12px;
            }
            
            .mu_register p {
                font-size: 15px;
                margin-bottom: 24px;
            }
            
            .mu_register input[type="text"],
            .mu_register input[type="email"],
            .mu_register input[type="password"] {
                padding: 12px 14px;
                font-size: 16px;
            }
            
            .mu_register .submit {
                padding: 12px 20px;
                font-size: 16px;
            }
        }
        
        /* Responsive design - Small mobile */
        @media (max-width: 480px) {
            #content, .wrap, .mu_register {
                margin: 16px 12px;
                padding: 20px 16px;
            }
            
            .mu_register h2 {
                font-size: 22px;
            }
            
            .mu_register p {
                font-size: 14px;
                margin-bottom: 20px;
            }
            
            .mu_register input[type="text"],
            .mu_register input[type="email"],
            .mu_register input[type="password"] {
                padding: 12px;
            }
            
            .mu_register .submit {
                padding: 12px 20px;
            }
        }
        
        /* Responsive design - Tablet */
        @media (min-width: 769px) and (max-width: 1024px) {
            #content, .wrap, .mu_register {
                margin: 40px 32px;
                padding: 32px 28px;
            }
        }
        
        /* Responsive design - Desktop */
        @media (min-width: 1025px) {
            #content, .wrap, .mu_register {
                margin: 60px auto;
                padding: 48px;
                max-width: 600px;
            }
        }
        
        /* Large desktop */
        @media (min-width: 1440px) {
            #content, .wrap, .mu_register {
                max-width: 700px;
                padding: 56px;
            }
        }
    </style>
    <?php
}

// Add the styling to wp-signup.php
add_action('signup_header', 'orabooks_clean_signup_styling');

/**
 * Add custom JavaScript for better UX
 */
function orabooks_clean_signup_script() {
    global $pagenow;
    if ($pagenow !== 'wp-signup.php') {
        return;
    }
    
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add smooth scrolling
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
            
            // Add form validation feedback
            const form = document.querySelector('form');
            if (form) {
                const inputs = form.querySelectorAll('input[type="text"], input[type="email"], input[type="password"]');
                
                inputs.forEach(input => {
                    input.addEventListener('blur', function() {
                        if (this.value.trim() === '') {
                            this.style.borderColor = '#e53e3e';
                        } else {
                            this.style.borderColor = '#e2e8f0';
                        }
                    });
                    
                    input.addEventListener('focus', function() {
                        this.style.borderColor = '#3b82f6';
                    });
                });
            }
        });
    </script>
    <?php
}

// Add the script to wp-signup.php
add_action('signup_footer', 'orabooks_clean_signup_script');

/**
 * Clean up the signup form HTML
 */
function orabooks_clean_signup_form_html() {
    global $pagenow;
    if ($pagenow !== 'wp-signup.php') {
        return;
    }
    
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Clean up the form structure
            const muRegister = document.querySelector('.mu_register');
            if (muRegister) {
                // Add custom class for styling
                muRegister.classList.add('clean-signup-form');
                
                // Improve form layout
                const labels = muRegister.querySelectorAll('label');
                labels.forEach(label => {
                    const input = label.nextElementSibling;
                    if (input && input.tagName === 'INPUT') {
                        const wrapper = document.createElement('div');
                        wrapper.style.marginBottom = '20px';
                        label.parentNode.insertBefore(wrapper, label);
                        wrapper.appendChild(label);
                        wrapper.appendChild(input);
                    }
                });
                
                // Style the submit button
                const submitBtn = muRegister.querySelector('input[type="submit"]');
                if (submitBtn) {
                    submitBtn.classList.add('submit');
                }
            }
        });
    </script>
    <?php
}

// Add form cleanup
add_action('signup_footer', 'orabooks_clean_signup_form_html');
?>