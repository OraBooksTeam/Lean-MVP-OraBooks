<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Login - Tailwind CSS Test</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        /* Force styles to override everything */
        * {
            margin: 0 !important;
            padding: 0 !important;
            box-sizing: border-box !important;
        }
        
        html, body {
            font-family: 'Inter', system-ui, sans-serif !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            min-height: 100vh !important;
        }
        
        .test-container {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-height: 100vh !important;
            padding: 2rem !important;
        }
        
        .test-card {
            background: white !important;
            border-radius: 1rem !important;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1) !important;
            padding: 2rem !important;
            max-width: 400px !important;
            width: 100% !important;
        }
        
        .test-title {
            font-size: 1.5rem !important;
            font-weight: 700 !important;
            color: #111827 !important;
            margin-bottom: 1rem !important;
            text-align: center !important;
        }
        
        .test-text {
            color: #6b7280 !important;
            margin-bottom: 1.5rem !important;
            text-align: center !important;
        }
        
        .test-button {
            background: #10b981 !important;
            color: white !important;
            border: none !important;
            border-radius: 0.5rem !important;
            padding: 0.75rem 1.5rem !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            width: 100% !important;
            transition: all 0.2s !important;
        }
        
        .test-button:hover {
            background: #059669 !important;
            transform: scale(1.05) !important;
        }
        
        .status-box {
            background: #dcfce7 !important;
            border: 1px solid #bbf7d0 !important;
            color: #166534 !important;
            padding: 1rem !important;
            border-radius: 0.5rem !important;
            margin-bottom: 1rem !important;
            text-align: center !important;
        }
        
        .error-box {
            background: #fef2f2 !important;
            border: 1px solid #fecaca !important;
            color: #991b1b !important;
            padding: 1rem !important;
            border-radius: 0.5rem !important;
            margin-bottom: 1rem !important;
            text-align: center !important;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-card">
            <h1 class="test-title">🎨 Tailwind CSS Test</h1>
            
            <div class="status-box">
                ✅ If you see styled content, Tailwind CSS is working!
            </div>
            
            <p class="test-text">
                This page tests if Tailwind CSS loads correctly on client sites.
                The background should be a gradient, and this card should have shadows and rounded corners.
            </p>
            
            <button class="test-button" onclick="alert('Tailwind CSS is working!')">
                Test Button (should be green)
            </button>
            
            <div class="error-box">
                ⚠️ If this page looks plain/white, Tailwind CSS is NOT loading.
            </div>
        </div>
    </div>
    
    <script>
        console.log('Debug: Page loaded');
        console.log('Debug: Tailwind should be working if you see styled content');
        
        // Test Tailwind classes
        document.addEventListener('DOMContentLoaded', function() {
            const button = document.querySelector('.test-button');
            if (button) {
                // Add Tailwind hover effect
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.05)';
                });
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            }
        });
    </script>
</body>
</html>
