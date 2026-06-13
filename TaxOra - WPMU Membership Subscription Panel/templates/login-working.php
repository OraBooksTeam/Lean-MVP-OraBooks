<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - OraBooks</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        orabooks: {
                            primary: "#43a62d",
                            secondary: "#2d7a1d"
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif']
                    }
                }
            }
        }
    </script>
    
    <!-- Force Tailwind styles -->
    <style>
        /* Reset and force styles */
        * {
            margin: 0 !important;
            padding: 0 !important;
            box-sizing: border-box !important;
        }
        
        html, body {
            font-family: 'Inter', system-ui, sans-serif !important;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #dcfce7 100%) !important;
            min-height: 100vh !important;
        }
        
        .login-container {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-height: 100vh !important;
            padding: 2rem !important;
        }
        
        .login-card {
            background: white !important;
            border-radius: 1rem !important;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1) !important;
            padding: 2rem !important;
            max-width: 400px !important;
            width: 100% !important;
        }
        
        .logo-container {
            text-align: center !important;
            margin-bottom: 2rem !important;
        }
        
        .logo {
            width: 5rem !important;
            height: 5rem !important;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            border-radius: 1rem !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            margin: 0 auto 1.5rem !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
        }
        
        .title {
            font-size: 1.875rem !important;
            font-weight: 700 !important;
            color: #111827 !important;
            margin-bottom: 0.5rem !important;
            text-align: center !important;
        }
        
        .subtitle {
            color: #6b7280 !important;
            text-align: center !important;
            margin-bottom: 0 !important;
        }
        
        .form-group {
            margin-bottom: 1.5rem !important;
        }
        
        .form-label {
            display: block !important;
            font-size: 0.875rem !important;
            font-weight: 500 !important;
            color: #374151 !important;
            margin-bottom: 0.5rem !important;
        }
        
        .form-input {
            width: 100% !important;
            padding: 0.75rem 1rem !important;
            border: 1px solid #d1d5db !important;
            border-radius: 0.5rem !important;
            font-size: 0.875rem !important;
            transition: all 0.2s !important;
        }
        
        .form-input:focus {
            outline: none !important;
            border-color: #10b981 !important;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1) !important;
        }
        
        .form-row {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            margin-bottom: 1.5rem !important;
        }
        
        .checkbox-label {
            display: flex !important;
            align-items: center !important;
            font-size: 0.875rem !important;
            color: #374151 !important;
        }
        
        .checkbox {
            width: 1rem !important;
            height: 1rem !important;
            color: #10b981 !important;
            margin-right: 0.5rem !important;
        }
        
        .link {
            color: #10b981 !important;
            text-decoration: none !important;
            font-size: 0.875rem !important;
            font-weight: 500 !important;
        }
        
        .link:hover {
            color: #059669 !important;
            text-decoration: underline !important;
        }
        
        .submit-button {
            width: 100% !important;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            color: white !important;
            border: none !important;
            padding: 0.75rem 1rem !important;
            border-radius: 0.5rem !important;
            font-size: 1rem !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
        }
        
        .submit-button:hover {
            transform: scale(1.02) !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2) !important;
        }
        
        .submit-button:disabled {
            opacity: 0.5 !important;
            cursor: not-allowed !important;
            transform: none !important;
        }
        
        .footer-links {
            text-align: center !important;
            margin-top: 2rem !important;
            color: #6b7280 !important;
            font-size: 0.875rem !important;
        }
        
        .footer-links a {
            color: #10b981 !important;
            text-decoration: none !important;
            font-weight: 500 !important;
            margin: 0 1rem !important;
        }
        
        .footer-links a:hover {
            color: #059669 !important;
            text-decoration: underline !important;
        }
        
        .alert {
            padding: 1rem !important;
            border-radius: 0.5rem !important;
            margin-bottom: 1.5rem !important;
        }
        
        .alert-error {
            background: #fef2f2 !important;
            border: 1px solid #fecaca !important;
            color: #991b1b !important;
        }
        
        .alert-success {
            background: #f0fdf4 !important;
            border: 1px solid #bbf7d0 !important;
            color: #166534 !important;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Logo -->
            <div class="logo-container">
                <div class="logo">
                    <svg width="40" height="40" viewBox="0 0 20 20" fill="white">
                        <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h1 class="title">Welcome Back</h1>
                <p class="subtitle">Sign in to your OraBooks account</p>
            </div>
            
            <!-- Login Form -->
            <form name="loginform" id="loginform" action="<?php echo esc_url(site_url('wp-login.php', 'login_post')); ?>" method="post">
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr(isset($_REQUEST['redirect_to']) ? esc_url_raw($_REQUEST['redirect_to']) : admin_url()); ?>" />
                
                <?php
                $error = isset($_GET['login_error']) ? sanitize_text_field($_GET['login_error']) : '';
                $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
                ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <strong>Error:</strong> <?php echo esc_html($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <strong>Success:</strong> <?php echo esc_html($message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Username Field -->
                <div class="form-group">
                    <label for="orabooks-user-login" class="form-label">Username or Email Address</label>
                    <input 
                        type="text" 
                        name="log" 
                        id="orabooks-user-login" 
                        class="form-input"
                        placeholder="Enter your username or email"
                        value="<?php echo esc_attr(isset($_POST['log']) ? sanitize_user($_POST['log']) : ''); ?>"
                        size="20"
                        autocapitalize="off"
                        autocomplete="username"
                        required
                    />
                </div>
                
                <!-- Password Field -->
                <div class="form-group">
                    <label for="orabooks-user-pass" class="form-label">Password</label>
                    <input 
                        type="password" 
                        name="pwd" 
                        id="orabooks-user-pass" 
                        class="form-input"
                        placeholder="Enter your password"
                        size="20"
                        autocomplete="current-password"
                        required
                    />
                </div>
                
                <!-- Remember Me & Forgot Password -->
                <div class="form-row">
                    <label class="checkbox-label">
                        <input 
                            type="checkbox" 
                            name="rememberme" 
                            id="orabooks-rememberme" 
                            class="checkbox"
                            <?php checked(isset($_POST['rememberme'])); ?>
                        />
                        Remember me
                    </label>
                    
                    <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="link">
                        Forgot password?
                    </a>
                </div>
                
                <!-- Submit Button -->
                <button 
                    type="submit" 
                    name="wp-submit" 
                    id="orabooks-wp-submit" 
                    class="submit-button"
                >
                    Sign In
                </button>
                
                <?php wp_nonce_field('orabooks-client-login', 'orabooks_login_nonce'); ?>
            </form>
            
            <!-- Footer Links -->
            <div class="footer-links">
                <?php if (get_option('users_can_register')): ?>
                    <a href="<?php echo esc_url(wp_registration_url()); ?>">
                        Don't have an account? Sign up
                    </a>
                <?php endif; ?>
                
                <a href="<?php echo esc_url(home_url('/')); ?>">
                    ← Back to Home
                </a>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginform');
            const submitBtn = document.getElementById('orabooks-wp-submit');
            
            // Auto-focus first empty field
            const usernameInput = document.getElementById('orabooks-user-login');
            const passwordInput = document.getElementById('orabooks-user-pass');
            
            if (!usernameInput.value) {
                usernameInput.focus();
            } else if (!passwordInput.value) {
                passwordInput.focus();
            }
            
            // Add loading state
            form.addEventListener('submit', function(e) {
                const username = usernameInput.value.trim();
                const password = passwordInput.value.trim();
                
                // Basic validation
                if (!username || !password) {
                    e.preventDefault();
                    alert('Please enter both username and password.');
                    return;
                }
                
                submitBtn.disabled = true;
                submitBtn.textContent = 'Signing in...';
            });
        });
    </script>
</body>
</html>
