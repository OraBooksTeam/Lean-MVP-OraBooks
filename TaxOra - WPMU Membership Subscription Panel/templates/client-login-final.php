<?php
/**
 * Final Client Login Page with Tailwind CSS
 * Completely standalone with no WordPress dependencies
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle redirect URL
$redirect_to = isset($_REQUEST['redirect_to']) ? esc_url_raw($_REQUEST['redirect_to']) : admin_url();
$redirect_to = wp_validate_redirect($redirect_to);

// Get any error messages
$error = isset($_GET['login_error']) ? sanitize_text_field($_GET['login_error']) : '';
$message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';

// Get site name
$site_name = get_bloginfo('name');
$site_description = get_bloginfo('description');
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo esc_attr($site_description); ?>">
    <title>Login - <?php echo esc_html($site_name); ?></title>
    
    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Google Fonts -->
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
    
    <style>
        /* Force Tailwind styles to override any conflicts */
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', system-ui, sans-serif !important;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #dcfce7 100%) !important;
        }
        
        /* Ensure Tailwind utilities work */
        .bg-gradient-to-br {
            background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 50%, #dcfce7 100%) !important;
        }
        
        .rounded-2xl {
            border-radius: 1rem !important;
        }
        
        .shadow-xl {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
        }
        
        /* Form styling overrides */
        input[type="text"], input[type="password"], input[type="checkbox"] {
            font-family: 'Inter', system-ui, sans-serif !important;
        }
        
        .border-gray-300 {
            border-color: #d1d5db !important;
        }
        
        .text-green-600 {
            color: #16a34a !important;
        }
        
        .bg-green-600 {
            background-color: #16a34a !important;
        }
        
        .hover\:bg-green-700:hover {
            background-color: #15803d !important;
        }
        
        /* Animation keyframes */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        .animate-slide-up {
            animation: slideUp 0.3s ease-out;
        }
    </style>
</head>
<body class="h-full">
    <div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8 py-12">
        <div class="max-w-md w-full">
            <!-- Logo and Header -->
            <div class="text-center mb-8 animate-fade-in">
                <div class="mx-auto w-20 h-20 bg-gradient-to-br from-green-500 to-green-600 rounded-2xl flex items-center justify-center shadow-xl mb-6">
                    <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Welcome Back</h1>
                <p class="text-gray-600">Sign in to your <?php echo esc_html($site_name); ?> account</p>
            </div>
            
            <!-- Login Form Container -->
            <div class="bg-white rounded-2xl shadow-xl p-8 animate-slide-up">
                <?php if ($error): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg animate-fade-in">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-red-600 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-4.293-4.293a1 1 0 00-1.414 1.414L10 11.414l4.293 4.293a1 1 0 001.414-1.414L11.414 10l4.293 4.293a1 1 0 001.414-1.414L10 8.586l4.293-4.293a1 1 0 001.414-1.414L10 5.414l8.293 8.293a1 1 0 001.414-1.414L10 11.414l-8.293 8.293a1 1 0 001.414-1.414L10 8.586z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-red-800 font-medium"><?php echo esc_html($error); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg animate-fade-in">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-green-600 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-4.293-4.293a1 1 0 00-1.414 1.414L10 11.414l4.293 4.293a1 1 0 001.414-1.414L11.414 10l4.293 4.293a1 1 0 001.414-1.414L10 8.586l4.293-4.293a1 1 0 001.414-1.414L10 5.414L8.293 8.293a1 1 0 001.414-1.414L10 11.414l-8.293 8.293a1 1 0 001.414-1.414L10 8.586z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-green-800 font-medium"><?php echo esc_html($message); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form name="loginform" id="loginform" action="<?php echo esc_url(site_url('wp-login.php', 'login_post')); ?>" method="post" class="space-y-6">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
                    
                    <!-- Username/Email Field -->
                    <div>
                        <label for="orabooks-user-login" class="block text-sm font-medium text-gray-700 mb-2">
                            Username or Email Address
                        </label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400 group-focus-within:text-green-600 transition-colors duration-200" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <input 
                                type="text" 
                                name="log" 
                                id="orabooks-user-login" 
                                class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200 text-gray-900 placeholder-gray-500 hover:border-gray-400"
                                placeholder="Enter your username or email"
                                value="<?php echo esc_attr(isset($_POST['log']) ? sanitize_user($_POST['log']) : ''); ?>"
                                size="20"
                                autocapitalize="off"
                                autocomplete="username"
                                required
                            />
                        </div>
                    </div>
                    
                    <!-- Password Field -->
                    <div>
                        <label for="orabooks-user-pass" class="block text-sm font-medium text-gray-700 mb-2">
                            Password
                        </label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400 group-focus-within:text-green-600 transition-colors duration-200" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v17a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm5-4a1 1 0 00-1 1v1a1 1 0 102 0v-1a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <input 
                                type="password" 
                                name="pwd" 
                                id="orabooks-user-pass" 
                                class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200 text-gray-900 placeholder-gray-500 hover:border-gray-400"
                                placeholder="Enter your password"
                                size="20"
                                autocomplete="current-password"
                                required
                            />
                            <button 
                                type="button" 
                                id="toggle-password" 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none focus:text-green-600 transition-colors duration-200"
                                onclick="togglePasswordVisibility()"
                                tabindex="-1"
                            >
                                <svg id="eye-icon" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 0l-6 6a1 1 0 001.414 1.414l6-6a1 1 0 00-1.414-1.414zM6 8a1 1 0 011-1v1a1 1 0 110-2 1 1 0 110-2v-1a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Remember Me & Forgot Password -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center cursor-pointer">
                            <input 
                                type="checkbox" 
                                name="rememberme" 
                                id="orabooks-rememberme" 
                                class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500 focus:ring-2 focus:ring-offset-0"
                                <?php checked(isset($_POST['rememberme'])); ?>
                            />
                            <span class="ml-2 text-sm text-gray-700 select-none">Remember me</span>
                        </label>
                        
                        <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="text-sm text-green-600 hover:text-green-800 font-medium transition-colors duration-200">
                            Forgot password?
                        </a>
                    </div>
                    
                    <!-- Submit Button -->
                    <button 
                        type="submit" 
                        name="wp-submit" 
                        id="orabooks-wp-submit" 
                        class="w-full bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                    >
                        <span class="flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 3a1 1 0 011 1v12a1 1 0 011-1h4a1 1 0 011 1v-1a1 1 0 011-1H4a1 1 0 00-1-1v1z" clip-rule="evenodd"/>
                                <path d="M13.293 7.293a1 1 0 011.414 0L9 10.414V17a1 1 0 11-2 0v-6.586l4.293-4.293a1 1 0 011.414-1.414L17 9.586V8a1 1 0 00-1-1.414l-4.293 4.293z"/>
                            </svg>
                            <span id="button-text">Sign In</span>
                        </span>
                    </button>
                    
                    <?php wp_nonce_field('orabooks-client-login', 'orabooks_login_nonce'); ?>
                </form>
            </div>
            
            <!-- Additional Links -->
            <div class="text-center mt-8 space-y-4 animate-fade-in">
                <?php if (get_option('users_can_register')): ?>
                    <p class="text-gray-600">
                        Don't have an account? 
                        <a href="<?php echo esc_url(wp_registration_url()); ?>" class="font-medium text-green-600 hover:text-green-800 transition-colors duration-200">
                            Sign up
                        </a>
                    </p>
                <?php endif; ?>
                
                <div class="flex items-center justify-center space-x-6 text-sm text-gray-500">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="hover:text-gray-700 transition-colors duration-200">
                        <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586L.293.293a1 1 0 001.414-1.414l-7-7z"/>
                        </svg>
                        Back to Home
                    </a>
                    
                    <a href="#" onclick="window.open('mailto:support@orabooks.com', '_blank')" class="hover:text-gray-700 transition-colors duration-200">
                        <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                        </svg>
                        Help
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('orabooks-user-pass');
            const eyeIcon = document.getElementById('eye-icon');
            const toggleBtn = document.getElementById('toggle-password');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = '<path fill-rule="evenodd" d="M10 4.293a1 1 0 00-.707.293l-3 3a1 1 0 001.414 1.414L10 7.586V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1V2a1 1 0 00-1-1.414l-3-3a1 1 0 00-1.414-1.414z" clip-rule="evenodd"/>';
                toggleBtn.setAttribute('aria-label', 'Hide password');
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = '<path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 0l-6 6a1 1 0 001.414 1.414l6-6a1 1 0 00-1.414-1.414zM6 8a1 1 0 011-1v1a1 1 0 110-2 1 1 0 110-2v-1a1 1 0 00-1-1z" clip-rule="evenodd"/>';
                toggleBtn.setAttribute('aria-label', 'Show password');
            }
        }
        
        // Auto-focus on first empty field
        document.addEventListener('DOMContentLoaded', function() {
            const usernameInput = document.getElementById('orabooks-user-login');
            const passwordInput = document.getElementById('orabooks-user-pass');
            
            // Add fade-in animation to form
            const form = document.getElementById('loginform');
            form.classList.add('animate-fade-in');
            
            if (!usernameInput.value) {
                usernameInput.focus();
            } else if (!passwordInput.value) {
                passwordInput.focus();
            }
        });
        
        // Add loading state to submit button
        document.getElementById('loginform').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('orabooks-wp-submit');
            const buttonText = document.getElementById('button-text');
            
            // Basic validation
            const username = document.getElementById('orabooks-user-login').value.trim();
            const password = document.getElementById('orabooks-user-pass').value.trim();
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please enter both username and password.');
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            buttonText.innerHTML = '<svg class="animate-spin w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 0119.601 5H16a1 1 0 001 1v-1a1 1 0 00-1-1h-1.17C5.06 5.687 5 5.35 5 5zm4 1V5a1 1 0 10-1 1v1h1.17C14.94 5.687 15 5.35 15 5zm4 1a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>Signing in...';
        });
    </script>
</body>
</html>
