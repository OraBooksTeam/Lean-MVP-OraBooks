<?php
/*
Plugin Name: TaxOra Dashboard Topbar
Description: Premium SaaS dashboard topbar shortcode
Version: 2.0.0
Author: TaxOra
*/

// Prevent direct access
defined( 'ABSPATH' ) || exit;

// Register shortcode
function taxora_topbar_register_shortcode() {
    add_shortcode('taxora_topbar', 'taxora_topbar_shortcode_callback');
}
add_action('init', 'taxora_topbar_register_shortcode');

function taxora_topbar_is_dashboard_request() {
    if (is_admin() || wp_doing_ajax()) {
        return false;
    }

    $route = get_query_var('wpfd_route');
    if ($route === 'dashboard') {
        return true;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
    $path = trim((string) parse_url($request_uri, PHP_URL_PATH), '/');
    $home_path = trim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');

    if ($home_path && ($path === $home_path || strpos($path, $home_path . '/') === 0)) {
        $path = trim(substr($path, strlen($home_path)), '/');
    }

    return $path === 'dashboard' || strpos($path, 'dashboard/') === 0;
}

function taxora_topbar_start_dashboard_buffer() {
    if (!is_user_logged_in() || !taxora_topbar_is_dashboard_request()) {
        return;
    }

    global $taxora_topbar_dashboard_html;
    $taxora_topbar_dashboard_html = taxora_topbar_shortcode_callback();

    if (!$taxora_topbar_dashboard_html) {
        return;
    }

    ob_start('taxora_topbar_inject_into_dashboard_html');
}
add_action('template_redirect', 'taxora_topbar_start_dashboard_buffer', 0);

function taxora_topbar_inject_into_dashboard_html($html) {
    if (stripos($html, 'id="taxora-topbar"') !== false || stripos($html, "id='taxora-topbar'") !== false) {
        return $html;
    }

    global $taxora_topbar_dashboard_html;
    $topbar_html = $taxora_topbar_dashboard_html;

    if (!$topbar_html) {
        return $html;
    }

    $header_pattern = '/<header\b([^>]*\bclass=(["\'])(?=[^"\']*\bglass\b)(?=[^"\']*\bsticky\b)(?=[^"\']*\btop-0\b)[^"\']*\2[^>]*)>/i';
    if (preg_match($header_pattern, $html)) {
        return preg_replace_callback($header_pattern, function($matches) use ($topbar_html) {
            return $topbar_html . $matches[0];
        }, $html, 1);
    }

    if (stripos($html, '<body') !== false) {
        return preg_replace_callback('/(<body\b[^>]*>)/i', function($matches) use ($topbar_html) {
            return $matches[0] . $topbar_html;
        }, $html, 1);
    }

    return $topbar_html . $html;
}

// AJAX handler for language update
function taxora_update_user_language() {
    check_ajax_referer('taxora_lang_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_die('Not logged in');
    }
    
    $lang = sanitize_text_field($_POST['lang']);
    $allowed_langs = ['en', 'bn', 'ar'];
    
    if (in_array($lang, $allowed_langs)) {
        // Update user meta
        update_user_meta(get_current_user_id(), 'taxora_language', $lang);
        
        // Update WordPress locale if needed
        $locale_map = [
            'en' => 'en_US',
            'bn' => 'bn_BD',
            'ar' => 'ar'
        ];
        
        if (isset($locale_map[$lang])) {
            update_user_meta(get_current_user_id(), 'locale', $locale_map[$lang]);
        }
        
        wp_send_json_success(['message' => 'Language updated']);
    } else {
        wp_send_json_error(['message' => 'Invalid language']);
    }
}
add_action('wp_ajax_taxora_update_user_language', 'taxora_update_user_language');

// Shortcode callback
function taxora_topbar_shortcode_callback() {
    // Only for logged-in users
    if (!is_user_logged_in()) {
        return '';
    }
    
    // Get current user data
    $current_user = wp_get_current_user();
    $logout_url = wp_logout_url();
    $profile_url = admin_url('profile.php');
    $home_url = home_url('/dashboard');
    
    // Get current language
    $current_lang = get_user_meta(get_current_user_id(), 'taxora_language', true) ?: 'en';
    $lang_names = [
        'en' => 'English',
        'bn' => 'Bangla', 
        'ar' => 'Arabic'
    ];
    $current_lang_name = $lang_names[$current_lang] ?? 'English';
    $topbar_datetime_settings = [
        'dateFormat' => get_option('date_format') ?: 'F j, Y',
        'timeFormat' => 'g:i:s A',
        'useBrowserTime' => true,
    ];
    
    ob_start();
    ?>
    <style>
    /* TaxOra Topbar Styles */
    .taxora-topbar {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.95) 100%);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-bottom: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06), 0 1px 1px rgba(0, 0, 0, 0.04);
        height: 56px;
        display: flex;
        align-items: center;
        padding: 0 24px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        z-index: 99999;
    }
    
    .taxora-topbar:hover {
        box-shadow: 0 8px 10px -1px rgba(0, 0, 0, 0.12), 0 4px 6px -1px rgba(0, 0, 0, 0.08), 0 2px 2px rgba(0, 0, 0, 0.06);
    }
    
    .taxora-topbar-left {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 0 0 auto;
    }
    
    .taxora-topbar-btn {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(249, 250, 251, 0.8) 100%);
        border: 1px solid rgba(226, 232, 240, 0.6);
        border-radius: 10px;
        padding: 8px 14px;
        font-size: 14px;
        font-weight: 500;
        color: #374151;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        position: relative;
        overflow: hidden;
    }
    
    .taxora-topbar-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
        transition: left 0.5s;
    }
    
    .taxora-topbar-btn:hover {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(241, 245, 249, 0.9) 100%);
        border-color: rgba(99, 102, 241, 0.3);
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1), 0 4px 8px rgba(0, 0, 0, 0.06);
    }
    
    .taxora-topbar-btn:hover::before {
        left: 100%;
    }
    
    .taxora-topbar-btn:active {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
    }
    
    .taxora-topbar-text {
        font-size: 14px;
        font-weight: 500;
        color: #374151;
        text-decoration: none;
        transition: all 0.15s ease;
        padding: 6px 12px;
        border-radius: 8px;
    }
    
    .taxora-topbar-text:hover {
        background: rgba(0, 0, 0, 0.05);
        color: #1f2937;
        transform: translateY(-1px);
    }
    
    .taxora-topbar-center {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 500;
        color: #1f2937;
    }
    
    .taxora-clock {
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.02em;
    }
    
    .taxora-calendar-btn {
        background: none;
        border: none;
        padding: 4px;
        cursor: pointer;
        border-radius: 6px;
        transition: background 0.15s ease;
        color: #6b7280;
    }
    
    .taxora-calendar-btn:hover {
        background: rgba(0, 0, 0, 0.05);
        color: #374151;
    }
    
    .taxora-date {
        color: #6b7280;
        font-weight: 400;
    }
    
    .taxora-topbar-right {
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 0 0 auto;
    }
    
    .taxora-dropdown {
        position: relative;
    }
    
    .taxora-dropdown-btn {
        background: rgba(0, 0, 0, 0.02);
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 8px;
        padding: 6px 12px;
        font-size: 13px;
        font-weight: 500;
        color: #374151;
        cursor: pointer;
        transition: all 0.15s ease;
        display: flex;
        align-items: center;
        gap: 6px;
        min-width: 120px;
        justify-content: space-between;
    }
    
    .taxora-dropdown-btn:hover {
        background: rgba(0, 0, 0, 0.05);
        border-color: rgba(0, 0, 0, 0.12);
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
    }
    
    .taxora-dropdown-menu {
        position: absolute;
        top: calc(100% + 4px);
        right: 0;
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1), 0 4px 10px rgba(0, 0, 0, 0.05);
        min-width: 180px;
        padding: 8px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-8px);
        transition: all 0.15s ease;
        z-index: 100000;
    }
    
    .taxora-dropdown-menu.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    
    .taxora-dropdown-menu a {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        color: #374151;
        text-decoration: none;
        transition: all 0.15s ease;
        cursor: pointer;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
    }
    
    .taxora-dropdown-menu a:hover {
        background: rgba(0, 0, 0, 0.05);
        color: #1f2937;
    }
    
    .taxora-dropdown-menu a.active,
    .taxora-dropdown-menu a.selected {
        background: rgba(99, 102, 241, 0.1);
        color: #6366f1;
        font-weight: 600;
    }
    
    .taxora-dropdown-menu a.active::after,
    .taxora-dropdown-menu a.selected::after {
        content: '✓';
        float: right;
        margin-left: 8px;
    }
    
    .taxora-dropdown-menu a:hover {
        transform: translateX(2px);
    }
    
    .taxora-calendar-dropdown {
        position: fixed;
        top: 52px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1), 0 4px 10px rgba(0, 0, 0, 0.05);
        min-width: 280px;
        padding: 12px;
        z-index: 100000;
        opacity: 0;
        visibility: hidden;
        transform: translateX(-50%) translateY(-8px);
        transition: all 0.15s ease;
    }
    
    .taxora-calendar-dropdown.show {
        opacity: 1;
        visibility: visible;
        transform: translateX(-50%) translateY(0);
    }
    
    .taxora-calendar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
        font-weight: 600;
        color: #1f2937;
    }
    
    .taxora-calendar-header button {
        background: rgba(0, 0, 0, 0.02);
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 6px;
        padding: 4px 8px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    
    .taxora-calendar-header button:hover {
        background: rgba(0, 0, 0, 0.05);
    }
    
    .taxora-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 4px;
        font-size: 12px;
    }
    
    .taxora-calendar-day-header {
        text-align: center;
        font-weight: 600;
        color: #6b7280;
        padding: 4px;
    }
    
    .taxora-calendar-day {
        text-align: center;
        padding: 6px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s ease;
        color: #374151;
    }
    
    .taxora-calendar-day:hover {
        background: rgba(0, 0, 0, 0.05);
    }
    
    .taxora-calendar-day.today {
        background: #3b82f6;
        color: white;
        font-weight: 600;
    }
    
    /* Laptop View */
    @media (max-width: 1440px) {
        .taxora-topbar {
            padding: 0 22px;
            height: 54px;
        }
        
        .taxora-topbar-left {
            gap: 7px;
        }
        
        .taxora-topbar-btn {
            padding: 7px 13px;
            font-size: 13px;
            border-radius: 9px;
        }
        
        .taxora-topbar-center {
            font-size: 13px;
            gap: 7px;
        }
        
        .taxora-clock {
            font-size: 12px;
        }
        
        .taxora-date {
            font-size: 11px;
        }
        
        .taxora-calendar-btn {
            padding: 7px;
            font-size: 15px;
        }
        
        .taxora-topbar-right {
            gap: 9px;
        }
        
        .taxora-dropdown-btn {
            min-width: 110px;
            font-size: 12px;
            padding: 7px 11px;
            border-radius: 9px;
        }
        
        .taxora-dropdown-menu {
            min-width: 190px;
            border-radius: 11px;
        }
        
        .taxora-calendar-dropdown {
            min-width: 310px;
            border-radius: 13px;
        }
    }
    
    /* Tablet View */
    @media (max-width: 1024px) {
        .taxora-topbar {
            padding: 0 18px;
            height: 50px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.97) 0%, rgba(248, 250, 252, 0.93) 100%);
        }
        
        .taxora-topbar-left {
            gap: 6px;
        }
        
        .taxora-topbar-btn {
            padding: 6px 11px;
            font-size: 12px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
        }
        
        .taxora-topbar-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08);
        }
        
        .taxora-topbar-center {
            font-size: 12px;
            gap: 6px;
        }
        
        .taxora-clock {
            font-size: 11px;
        }
        
        .taxora-date {
            font-size: 10px;
        }
        
        .taxora-calendar-btn {
            padding: 6px;
            font-size: 14px;
        }
        
        .taxora-topbar-right {
            gap: 7px;
        }
        
        .taxora-dropdown-btn {
            min-width: 95px;
            font-size: 11px;
            padding: 6px 9px;
            border-radius: 8px;
        }
        
        .taxora-dropdown-menu {
            min-width: 170px;
            border-radius: 10px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08);
        }
        
        .taxora-calendar-dropdown {
            min-width: 280px;
            border-radius: 12px;
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }
    }
    
    /* Mobile Responsive */
    @media (max-width: 768px) {
        .taxora-topbar {
            padding: 0 8px;
            height: 44px;
        }
        
        .taxora-topbar-left {
            gap: 2px;
        }
        
        .taxora-topbar-btn {
            padding: 6px 8px;
            font-size: 12px;
            min-width: 36px;
            height: 32px;
        }
        
        .taxora-topbar-text {
            padding: 6px 8px;
            font-size: 12px;
        }
        
        .taxora-topbar-center {
            font-size: 12px;
            gap: 6px;
        }
        
        .taxora-clock {
            font-size: 11px;
        }
        
        .taxora-date {
            font-size: 10px;
            display: none;
        }
        
        .taxora-calendar-btn {
            padding: 6px;
            font-size: 14px;
        }
        
        .taxora-topbar-right {
            gap: 6px;
        }
        
        .taxora-dropdown-btn {
            min-width: 80px;
            font-size: 11px;
            padding: 6px 8px;
            height: 32px;
        }
        
        .taxora-dropdown-menu {
            min-width: 160px;
            right: -8px;
        }
        
        .taxora-calendar-dropdown {
            min-width: 280px;
            left: 50%;
            transform: translateX(-50%);
            right: auto;
        }
        
        .taxora-calendar-dropdown.show {
            transform: translateX(-50%);
        }
        
        .taxora-calendar-grid {
            font-size: 11px;
        }
        
        .taxora-calendar-day {
            padding: 4px;
            font-size: 11px;
        }
        
        .taxora-calendar-header {
            font-size: 13px;
        }
        
        .taxora-calendar-header button {
            padding: 3px 6px;
            font-size: 11px;
        }
    }
    
    /* Small Mobile */
    @media (max-width: 480px) {
        .taxora-topbar {
            padding: 0 6px;
        }
        
        .taxora-topbar-left {
            gap: 1px;
        }
        
        .taxora-topbar-btn {
            padding: 5px 6px;
            min-width: 32px;
            height: 28px;
        }
        
        .taxora-topbar-center {
            gap: 4px;
        }
        
        .taxora-clock {
            font-size: 10px;
        }
        
        .taxora-calendar-btn {
            padding: 4px;
            font-size: 12px;
        }
        
        .taxora-topbar-right {
            gap: 4px;
        }
        
        .taxora-dropdown-btn {
            min-width: 70px;
            font-size: 10px;
            padding: 5px 6px;
            height: 28px;
        }
        
        .taxora-dropdown-menu {
            min-width: 140px;
            font-size: 12px;
        }
        
        .taxora-dropdown-menu a {
            padding: 6px 8px;
            font-size: 12px;
        }
    }
    </style>
    
    <div class="taxora-topbar" id="taxora-topbar">
        <!-- Left Navigation -->
        <div class="taxora-topbar-left">
            <button class="taxora-topbar-btn" onclick="taxoraDashboardBack()">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </button>
            <a href="<?php echo esc_url($home_url); ?>" class="taxora-topbar-btn">
                🏠
            </a>
            <button class="taxora-topbar-btn" onclick="taxoraDashboardForward()">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
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
                    <?php echo esc_html($current_lang_name); ?> ▼
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
                    <a href="<?php echo esc_url($profile_url); ?>">My Account</a>
                    <a href="#" onclick="upgradePlan()">Upgrade Plan</a>
                    <a href="<?php echo esc_url($logout_url); ?>">Logout</a>
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
    $ajax_url = admin_url('admin-ajax.php');
    $lang_nonce = wp_create_nonce('taxora_lang_nonce');
    ?>
    <script>
    // TaxOra Topbar - inline
    (function () {
        var ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
        var langNonce = <?php echo wp_json_encode($lang_nonce); ?>;

        // --- Clock ---
        function updateClock() {
            var now = new Date();
            var el = document.getElementById('taxora-clock');
            if (el) el.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
            el = document.getElementById('taxora-date');
            if (el) el.textContent = now.toLocaleDateString('en-US', { weekday: 'short', month: 'long', day: 'numeric', year: 'numeric' });
        }
        updateClock();
        setInterval(updateClock, 1000);

        // --- Calendar ---
        var calDate = new Date();
        function renderCalendar() {
            var y = calDate.getFullYear(), m = calDate.getMonth();
            var mel = document.getElementById('taxora-calendar-month');
            if (mel) mel.textContent = new Date(y, m).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            var grid = document.getElementById('taxora-calendar-grid');
            if (!grid) return;
            grid.innerHTML = '';
            ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(function(d) {
                var h = document.createElement('div');
                h.className = 'taxora-calendar-day-header';
                h.textContent = d;
                grid.appendChild(h);
            });
            var fd = new Date(y, m, 1).getDay();
            var dim = new Date(y, m + 1, 0).getDate();
            var today = new Date();
            for (var i = 0; i < fd; i++) grid.appendChild(document.createElement('div'));
            for (var d = 1; d <= dim; d++) {
                var el = document.createElement('div');
                el.className = 'taxora-calendar-day';
                el.textContent = d;
                if (y === today.getFullYear() && m === today.getMonth() && d === today.getDate()) el.classList.add('today');
                el.onclick = closeCalendar;
                grid.appendChild(el);
            }
        }
        function changeMonth(dir) { calDate.setMonth(calDate.getMonth() + dir); renderCalendar(); }

        // --- Dropdowns ---
        function toggleLanguage() {
            var m = document.getElementById('taxora-language-menu');
            if (m) { m.classList.toggle('show'); closeOtherDropdowns('taxora-language-menu'); }
        }
        function toggleSettings() {
            var m = document.getElementById('taxora-settings-menu');
            if (m) { m.classList.toggle('show'); closeOtherDropdowns('taxora-settings-menu'); }
        }
        function toggleCalendar() {
            var c = document.getElementById('taxora-calendar');
            if (c) { c.classList.toggle('show'); closeOtherDropdowns('taxora-calendar'); if (c.classList.contains('show')) renderCalendar(); }
        }
        function closeOtherDropdowns(ex) {
            ['taxora-language-menu','taxora-settings-menu','taxora-calendar'].forEach(function(id) {
                if (id === ex) return;
                var e = document.getElementById(id);
                if (e) e.classList.remove('show');
            });
        }
        function closeCalendar() { var c = document.getElementById('taxora-calendar'); if (c) c.classList.remove('show'); }

        // --- Language ---
        var langLabels = { en: 'English', bn: 'Bangla', ar: 'Arabic' };
        var translations = {
            bn: {
                'My Account': 'আমার অ্যাকাউন্ট', 'Upgrade Plan': 'প্ল্যান আপগ্রেড করুন', 'Logout': 'লগ আউট',
                'Overview': 'ওভারভিউ', 'Posts': 'পোস্ট', 'Media Library': 'মিডিয়া লাইব্রেরি',
                'Settings': 'সেটিংস', 'Users': 'ব্যবহারকারী', 'Profile': 'প্রোফাইল',
                'Content': 'কন্টেন্ট', 'Assets': 'সম্পদ', 'Create Post': 'পোস্ট তৈরি করুন',
                'Create Page': 'পেজ তৈরি করুন', 'Recent Posts': 'সাম্প্রতিক পোস্ট',
                'Statistics': 'পরিসংখ্যান', 'Total Posts': 'মোট পোস্ট', 'Total Pages': 'মোট পেজ',
                'Members': 'সদস্য', 'Activity Overview': 'কার্যকলাপ ওভারভিউ',
                'Available Features': 'উপলব্ধ ফিচার'
            },
            ar: {
                'My Account': 'حسابي', 'Upgrade Plan': 'ترقية الخطة', 'Logout': 'تسجيل الخروج',
                'Overview': 'نظرة عامة', 'Posts': 'المشاركات', 'Media Library': 'مكتبة الوسائط',
                'Settings': 'الإعدادات', 'Users': 'المستخدمون', 'Profile': 'الملف الشخصي',
                'Content': 'المحتوى', 'Assets': 'الأصول', 'Create Post': 'إنشاء مشاركة',
                'Create Page': 'إنشاء صفحة', 'Recent Posts': 'المشاركات الأخيرة',
                'Statistics': 'الإحصائيات', 'Total Posts': 'إجمالي المشاركات', 'Total Pages': 'إجمالي الصفحات',
                'Members': 'الأعضاء', 'Activity Overview': 'نظرة عامة على النشاط',
                'Available Features': 'الميزات المتاحة'
            }
        };

        function getT() {
            return document.querySelectorAll(
                '#taxora-settings-menu a, .nav-btn span, .widget-title, .widget h1, .widget p, ' +
                '.text-sm.font-medium.text-gray-500, h3.text-lg.font-bold.text-gray-900, ' +
                '.mobile-nav-btn span, button[onclick*="quickAction"] span, #page-title'
            );
        }

        function switchLanguage(lang) {
            var sel = langLabels[lang] ? lang : 'en';
            localStorage.setItem('taxora_language', sel);
            // Store originals
            getT().forEach(function(e) { if (!e.dataset.oraOrig) e.dataset.oraOrig = e.textContent; });
            // Close menu
            var menu = document.getElementById('taxora-language-menu');
            if (menu) menu.classList.remove('show');
            // Update language button text
            var langBtn = document.querySelector('.taxora-dropdown-btn');
            if (langBtn) langBtn.textContent = (langLabels[sel] || 'English') + ' ▼';
            // Translate elements
            getT().forEach(function(e) {
                var orig = e.dataset.oraOrig || e.textContent;
                e.textContent = (sel === 'en') ? orig : (translations[sel] && translations[sel][orig] ? translations[sel][orig] : orig);
            });
            // Active state on menu items
            ['en','bn','ar'].forEach(function(k) {
                var a = document.querySelector('a[onclick*="switchLanguage(\'' + k + '\')"]');
                if (a) { a.classList.toggle('active', k === sel); a.classList.toggle('selected', k === sel); }
            });
            // RTL
            document.documentElement.dir = sel === 'ar' ? 'rtl' : 'ltr';
            document.documentElement.lang = sel;
            // Persist
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('action=taxora_update_user_language&lang=' + encodeURIComponent(sel) + '&nonce=' + encodeURIComponent(langNonce));
        }

        // --- Navigation with section history ---
        var sectionHistory = [];
        var sectionForward = [];
        var currentSection = 'overview';

        function getLoadSection() {
            return typeof window.loadSection === 'function' ? window.loadSection : null;
        }

        function taxoraDashboardBack() {
            if (sectionHistory.length > 0) {
                var prev = sectionHistory.pop();
                sectionForward.push(currentSection);
                currentSection = prev;
                var ls = getLoadSection();
                if (ls) ls(prev);
            }
        }
        function taxoraDashboardForward() {
            if (sectionForward.length > 0) {
                var next = sectionForward.pop();
                sectionHistory.push(currentSection);
                currentSection = next;
                var ls = getLoadSection();
                if (ls) ls(next);
            }
        }

        // Track section navigation by wrapping loadSection once it exists
        function wrapLoadSection() {
            var orig = window.loadSection;
            if (typeof orig !== 'function') return false;
            window.loadSection = function(section, params) {
                if (section && section !== currentSection) {
                    sectionHistory.push(currentSection);
                    sectionForward = [];
                    currentSection = section;
                }
                // Intercept overview to prevent location.reload()
                if (section === 'overview') {
                    restoreOverview();
                    return;
                }
                return orig(section, params);
            };
            return true;
        }

        // Cache and restore overview content (avoids page reload)
        var overviewHtml = null;
        var overviewTitle = '';

        function captureOverview() {
            var container = document.getElementById('section-content');
            if (container && overviewHtml === null) {
                overviewHtml = container.innerHTML;
            }
            var title = document.getElementById('page-title');
            if (title && !overviewTitle) {
                overviewTitle = title.textContent || 'Overview';
            }
        }

        function restoreOverview() {
            var container = document.getElementById('section-content');
            if (container && overviewHtml !== null) {
                container.innerHTML = overviewHtml;
                container.classList.remove('section-hidden');
            }
            var title = document.getElementById('page-title');
            if (title && overviewTitle) {
                title.textContent = overviewTitle;
            }
            // Update active nav
            document.querySelectorAll('.nav-btn, .mobile-nav-btn').forEach(function(b) {
                b.classList.remove('bg-primary-500', 'text-white', 'shadow-lg', 'active-nav');
                b.classList.add('text-gray-400');
            });
            document.querySelectorAll('[data-section="overview"]').forEach(function(b) {
                b.classList.add('bg-primary-500', 'text-white', 'shadow-lg', 'active-nav');
                b.classList.remove('text-gray-400');
            });
        }

        // --- Upgrade Plan ---
        function upgradePlan() {
            var btn = document.querySelector('button[data-section="upgrade"]');
            if (btn) btn.click();
            closeOtherDropdowns('taxora-settings-menu');
        }

        // --- Init ---
        function init() {
            // Capture overview content for back-navigation without reload
            captureOverview();
            // Apply saved language
            var saved = localStorage.getItem('taxora_language');
            if (saved && langLabels[saved]) switchLanguage(saved);
            // Click outside
            document.addEventListener('click', function(e) {
                document.querySelectorAll('.taxora-dropdown-menu, .taxora-calendar-dropdown').forEach(function(d) {
                    if (!d.contains(e.target) && !e.target.closest('.taxora-dropdown-btn') && !e.target.closest('.taxora-calendar-btn'))
                        d.classList.remove('show');
                });
            });
            // Wrap loadSection once it exists
            wrapLoadSection();
        }

        // Keep trying to wrap loadSection until it succeeds (dashboard.js may load late)
        function tryWrap() {
            if (wrapLoadSection()) return;
            setTimeout(tryWrap, 200);
        }
        tryWrap();

        // Run init when topbar exists
        var wait = setInterval(function() {
            if (document.getElementById('taxora-topbar')) {
                clearInterval(wait);
                init();
            }
        }, 50);

        // Expose globally
        window.toggleLanguage = toggleLanguage;
        window.toggleSettings = toggleSettings;
        window.toggleCalendar = toggleCalendar;
        window.changeMonth = changeMonth;
        window.switchLanguage = switchLanguage;
        window.upgradePlan = upgradePlan;
        window.taxoraDashboardBack = taxoraDashboardBack;
        window.taxoraDashboardForward = taxoraDashboardForward;
        window.closeCalendar = closeCalendar;
    })();
    </script>
    <?php
    return ob_get_clean();
}
