<?php
/*
TaxOra Dashboard Topbar old backup.
This file is intentionally not a WordPress plugin entry point.
*/

// Prevent direct access
defined( 'ABSPATH' ) || exit;

// Plugin constants
define( 'TAXORA_TOPBAR_VERSION', '1.0.0' );
define( 'TAXORA_TOPBAR_URL', plugin_dir_url( __FILE__ ) );

// Debug: Log plugin loading
error_log('TaxOra Plugin: Main file loaded');

// Enqueue assets
function taxora_topbar_enqueue_assets() {
    // Check if on dashboard route
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( strpos( $request_uri, '/dashboard' ) === false ) {
        return;
    }
    
    // Only for logged-in users
    if ( ! is_user_logged_in() ) {
        return;
    }
    
    // Enqueue CSS
    wp_enqueue_style( 
        'taxora-topbar', 
        TAXORA_TOPBAR_URL . 'assets/css/topbar.css', 
        array(), 
        TAXORA_TOPBAR_VERSION 
    );
    
    // Enqueue JS
    wp_enqueue_script( 
        'taxora-topbar', 
        TAXORA_TOPBAR_URL . 'assets/js/topbar.js', 
        array(), 
        TAXORA_TOPBAR_VERSION, 
        true 
    );
}
add_action( 'wp_enqueue_scripts', 'taxora_topbar_enqueue_assets' );

// Render topbar function - moved to JavaScript
function taxora_topbar_get_html() {
    ob_start();
    ?>
    <div id="taxora-topbar" class="taxora-topbar">
        <!-- Left Navigation -->
        <div class="taxora-topbar-left">
            <button class="taxora-topbar-btn" onclick="history.back()">
                ← Back
            </button>
            <a href="<?php echo esc_url( home_url() ); ?>" class="taxora-topbar-btn">
                ⌂ Home
            </a>
            <button class="taxora-topbar-btn" onclick="history.forward()">
                Forward →
            </button>
        </div>
        
        <!-- Center Clock -->
        <div class="taxora-topbar-center">
            <span class="taxora-clock" id="taxora-clock">12:00:00 PM</span>
            <button class="taxora-calendar-btn" onclick="toggleCalendar()">
                📅
            </button>
            <span class="taxora-date" id="taxora-date">Loading...</span>
        </div>
        
        <!-- Right Controls -->
        <div class="taxora-topbar-right">
            <!-- Language Dropdown -->
            <div class="taxora-dropdown">
                <button class="taxora-dropdown-btn" onclick="toggleLanguage()">
                    English ▼
                </button>
                <div class="taxora-dropdown-menu" id="taxora-language-menu">
                    <a href="#" onclick="switchLanguage('en')">English</a>
                    <a href="#" onclick="switchLanguage('bn')">Bangla</a>
                    <a href="#" onclick="switchLanguage('ar')">Arabic</a>
                </div>
            </div>
            
            <!-- Settings Dropdown -->
            <div class="taxora-dropdown">
                <button class="taxora-dropdown-btn" onclick="toggleSettings()">
                    Settings ▼
                </button>
                <div class="taxora-dropdown-menu" id="taxora-settings-menu">
                    <a href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>">My Account</a>
                    <a href="#" onclick="upgradePlan()">Upgrade Plan</a>
                    <a href="<?php echo esc_url( wp_logout_url() ); ?>">Logout</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Calendar Dropdown -->
    <div class="taxora-calendar-dropdown" id="taxora-calendar">
        <div class="taxora-calendar-header">
            <button onclick="changeMonth(-1)">‹</button>
            <span id="taxora-calendar-month">May 2026</span>
            <button onclick="changeMonth(1)">›</button>
        </div>
        <div class="taxora-calendar-grid" id="taxora-calendar-grid">
            <!-- Calendar will be populated by JS -->
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Pass topbar HTML to JavaScript
function taxora_topbar_pass_to_js() {
    if ( ! is_user_logged_in() ) {
        return;
    }
    
    $topbar_html = taxora_topbar_get_html();
    ?>
    <script>
    window.taxoraTopbarHTML = <?php echo json_encode($topbar_html); ?>;
    console.log('TaxOra: Topbar HTML passed to JavaScript');
    </script>
    <?php
}
add_action( 'wp_footer', 'taxora_topbar_pass_to_js' );

// Remove body class - no longer needed
// add_filter( 'body_class', 'taxora_topbar_body_class' );
