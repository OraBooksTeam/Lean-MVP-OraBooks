<?php
/**
 * PWA Dashboard Template
 */

defined('ABSPATH') || exit;

$user = wp_get_current_user();
$overview = $this->get_dashboard_overview();
$stats = $overview['stats'];
$recent_posts = $overview['recent_posts'];
$features_data = $this->get_addon_features();
$features = $features_data['features'];

// Get user membership level
$membership_level = get_user_meta($user->ID, 'orabooks_level', true);
$level_name = $membership_level ? ucfirst($membership_level) : 'Free';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="h-full bg-gray-50">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#4f46e5">
    <title>Dashboard - <?php bloginfo('name'); ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <?php
    $wpfd_assets_url = plugin_dir_url(dirname(__DIR__) . '/wp-frontend-dashboard.php') . 'assets/';
    $wpfd_tailwind_path = dirname(__DIR__) . '/assets/css/dashboard-compiled.css';
    ?>
    <link rel="stylesheet" href="<?php echo esc_url($wpfd_assets_url . 'css/dashboard-compiled.css?v=' . (file_exists($wpfd_tailwind_path) ? filemtime($wpfd_tailwind_path) : time())); ?>">
    <!-- jQuery and Chart.js use WordPress-enqueued versions -->
    <?php wp_enqueue_script('jquery'); ?>
    <?php wp_print_scripts('jquery'); ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        :root {
            --safe-area-inset-bottom: env(safe-area-inset-bottom);
        }
        body {
            -webkit-tap-highlight-color: transparent;
            overscroll-behavior-y: contain;
        }
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .active-nav {
            color: #0ea5e9 !important;
        }
        .bg-primary-500.active-nav {
            color: #ffffff !important;
        }
        .active-nav svg {
            filter: drop-shadow(0 0 8px rgba(14, 165, 233, 0.45));
        }
        .bg-primary-500.active-nav svg {
            filter: drop-shadow(0 0 8px rgba(255, 255, 255, 0.45));
        }
        /* Mobile scroll behavior */
        @media (max-width: 768px) {
            .hide-scrollbar::-webkit-scrollbar {
                display: none;
            }
            .hide-scrollbar {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }
        }
        /* PWA specific animations */
        .page-transition {
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        .section-hidden {
            opacity: 0;
            transform: translateY(10px);
            pointer-events: none;
        }
        /* Skeleton loading */
        .skeleton {
            background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
        }
        @keyframes skeleton-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        /* Widget Styles */
        .widget {
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.05), 0 5px 15px -5px rgba(0, 0, 0, 0.02);
            border: 1px solid #e5e7eb;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .widget.dragging {
            opacity: 0.95;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            cursor: grabbing;
        }
        .widget.drag-over {
            border: 2px dashed #0ea5e9;
        }
        .widget-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            cursor: grab;
            user-select: none;
        }
        .widget-header:active {
            cursor: grabbing;
        }
        .widget-header:hover {
            background: #f3f4f6;
        }
        .widget-title {
            font-weight: 600;
            color: #111827;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .widget-controls {
            display: flex;
            gap: 0.5rem;
        }
        .widget-controls button {
            width: 2rem;
            height: 2rem;
            border: none;
            background: white;
            border-radius: 0.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: bold;
            color: #6b7280;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .widget-controls button:hover {
            background: #0ea5e9;
            color: white;
            transform: scale(1.1);
        }
        .widget-content {
            padding: 1.5rem;
            transition: all 0.3s ease;
        }
        .widget.minimized .widget-content {
            display: none;
        }
        .widget.minimized {
            min-height: auto;
        }
        .widget-full-width {
            grid-column: 1 / -1;
        }
        .widget-placeholder {
            background: rgba(14, 165, 233, 0.08);
            border: 2px dashed #0ea5e9;
            border-radius: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.2s ease;
        }
    </style>
</head>
<body class="h-full flex flex-col md:flex-row overflow-hidden">

    <!-- Desktop Sidebar -->
    <aside class="hidden md:flex w-72 flex-col bg-premium-dark text-white p-6 shrink-0 z-50">
        <?php
        // Get the global logo from Orabooks plugin
        $orabooks_logo = '';
        if (class_exists('MultisiteGlobalMenu')) {
            // Get the logo option directly since get_secure_logo_url() is private
            $logo_url = get_site_option('multisite_global_logo', '');
            
            if (!empty($logo_url)) {
                // Sanitize the URL similar to the private method
                $orabooks_logo = esc_url_raw($logo_url);
                
                // Convert HTTP to HTTPS if the current site is HTTPS
                if (is_ssl() || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')) {
                    $orabooks_logo = str_replace('http://', 'https://', $orabooks_logo);
                }
            }
        }
        ?>
        <div class="flex items-center justify-center mb-10 px-2">
            <?php if (!empty($orabooks_logo)): ?>
                <img src="<?php echo esc_url($orabooks_logo); ?>" alt="Logo" class="h-12 w-auto object-contain">
            <?php else: ?>
                <div class="w-10 h-10 bg-primary-500 rounded-xl flex items-center justify-center shadow-lg shadow-primary-500/30">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
            <?php endif; ?>
        </div>

        <nav class="flex-1 space-y-1">
            <button onclick="loadSection('overview')" data-section="overview" class="nav-btn group flex items-center w-full gap-3 px-4 py-3 rounded-xl transition bg-primary-500 text-white shadow-lg shadow-primary-500/20">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                <span class="font-medium">Overview</span>
            </button>

                        <button onclick="loadSection('posts')" data-section="posts" class="nav-btn group flex items-center w-full gap-3 px-4 py-3 rounded-xl transition text-gray-400 hover:text-white hover:bg-white/5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                <span class="font-medium">Posts</span>
            </button>
            <button onclick="loadSection('pages')" data-section="pages" class="nav-btn group flex items-center w-full gap-3 px-4 py-3 rounded-xl transition text-gray-400 hover:text-white hover:bg-white/5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                <span class="font-medium">Pages</span>
            </button>
            <button onclick="loadSection('users')" data-section="users" class="nav-btn group flex items-center w-full gap-3 px-4 py-3 rounded-xl transition text-gray-400 hover:text-white hover:bg-white/5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                <span class="font-medium">Users</span>
            </button>
            <button onclick="loadSection('media')" data-section="media" class="nav-btn group flex items-center w-full gap-3 px-4 py-3 rounded-xl transition text-gray-400 hover:text-white hover:bg-white/5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <span class="font-medium">Media Library</span>
            </button>
            <button onclick="loadSection('settings')" data-section="settings" class="nav-btn group flex items-center w-full gap-3 px-4 py-3 rounded-xl transition text-gray-400 hover:text-white hover:bg-white/5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-1.066 2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066 2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-1.543.94-3.31-.826-2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span class="font-medium">Settings</span>
            </button>
            <button onclick="loadSection('upgrade')" data-section="upgrade" class="nav-btn group flex items-center w-full gap-3 px-4 py-3 rounded-xl transition text-gray-400 hover:text-white hover:bg-white/5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                <span class="font-medium">Upgrade Plan</span>
            </button>
            
            <div class="pt-6 pb-2 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Addons</div>
            <?php foreach ($features as $feature): ?>
            <a href="<?php echo esc_url($feature['url'] ?? '#'); ?>" class="nav-btn group flex items-center w-full gap-3 px-4 py-3 rounded-xl transition text-gray-400 hover:text-white hover:bg-white/5">
                <div class="w-5 h-5">
                    <?php $this->render_feature_icon($feature['icon']); ?>
                </div>
                <span class="font-medium"><?php echo $feature['name']; ?></span>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="mt-auto pt-6 border-t border-white/10">
            <?php
            $avatar_id = get_user_meta($user->ID, 'wpfd_avatar_id', true);
            $avatar_url = $avatar_id ? wp_get_attachment_image_url($avatar_id, 'thumbnail') : null;
            ?>
            <button onclick="loadSection('profile')" data-section="profile" class="nav-btn flex items-center gap-3 px-2 mb-6 w-full text-left hover:bg-white/5 rounded-xl transition p-2">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-bold overflow-hidden">
                    <?php if ($avatar_url): ?>
                        <img src="<?php echo esc_url($avatar_url); ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user->display_name, 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-semibold truncate text-white"><?php echo esc_html($user->display_name); ?></div>
                    <div class="text-xs text-gray-500 truncate"><?php echo esc_html($level_name); ?></div>
                </div>
            </button>
            <a href="<?php echo wp_logout_url(); ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl text-red-400 hover:bg-red-400/10 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                <span class="font-medium">Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col h-full overflow-hidden relative">
        
        <!-- Top Bar -->
        <header class="glass sticky top-0 z-40 h-16 flex items-center justify-between px-6 shrink-0 md:border-b-0">
            <div class="flex items-center gap-4">
                <button onclick="toggleMobileSidebar()" class="md:hidden p-2 -ml-2 text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <h2 id="page-title" class="text-lg font-bold text-gray-900 hidden md:block"></h2>
            </div>

            <div class="flex items-center gap-4">
                <button id="search-container" onclick="Dashboard.showSearchModal()" class="relative flex items-center group cursor-pointer hover:bg-gray-200/80 transition-all rounded-xl">
                    <div class="absolute left-3.5 text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <div class="w-64 lg:w-80 bg-gray-100/80 border-none rounded-xl py-2.5 pl-10 pr-12 text-sm text-left text-gray-400 select-none">
                        Search everything...
                    </div>
                    <div class="absolute right-3 px-1.5 py-0.5 border border-gray-200 rounded text-[10px] font-bold text-gray-400 uppercase bg-white/50">
                        K
                    </div>
                </button>
                <button id="notification-btn" class="p-2 text-gray-600 hover:bg-gray-100 rounded-full transition relative">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    <span id="notification-counter" class="absolute top-2 right-2 w-4 h-4 bg-red-500 rounded-full border-2 border-white text-[10px] text-white flex items-center justify-center font-bold hidden">0</span>
                </button>
            </div>
        </header>

        <!-- Content Scroller -->
        <main id="main-scroller" class="flex-1 overflow-y-auto p-4 md:p-8 space-y-8 pb-32 md:pb-8 hide-scrollbar">
            
            <div id="section-content" class="page-transition">
                <!-- Dashboard Overview Section (Default) -->
                <div id="dashboard-widgets" class="space-y-6">
                    <!-- Welcome Hero Widget -->
                    <div class="widget" data-widget-id="hero">
                        <div class="widget-header">
                            <div class="flex items-center gap-3 flex-1">
                                <span class="drag-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600">⋮⋮</span>
                                <span class="widget-title">Welcome</span>
                            </div>
                            <div class="widget-controls">
                                <button class="widget-minimize" title="Minimize">−</button>
                                <button class="widget-maximize hidden" title="Maximize">+</button>
                            </div>
                        </div>
                        <div class="widget-content">
                            <div class="bg-gradient-to-r from-primary-600 to-indigo-700 rounded-3xl p-8 text-white shadow-premium relative overflow-hidden">
                                <div class="relative z-10">
                                    <h1 class="text-3xl font-bold mb-2">Hello, <?php echo esc_html($user->first_name ?: $user->display_name); ?>!</h1>
                                    <p class="text-primary-100 text-lg">Here's what's happening with your site today.</p>
                                </div>
                                <!-- Abstract shapes for flair -->
                                <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
                                <div class="absolute right-20 top-0 w-32 h-32 bg-primary-400/20 rounded-full blur-2xl"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Widget -->
                    <div class="widget" data-widget-id="stats">
                        <div class="widget-header">
                            <div class="flex items-center gap-3 flex-1">
                                <span class="drag-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600">⋮⋮</span>
                                <span class="widget-title">Statistics</span>
                            </div>
                            <div class="widget-controls">
                                <button class="widget-minimize" title="Minimize">−</button>
                                <button class="widget-maximize hidden" title="Maximize">+</button>
                            </div>
                        </div>
                        <div class="widget-content">
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
                                <div onclick="loadSection('posts')" class="bg-white p-6 rounded-3xl shadow-premium border border-gray-100 hover:shadow-premium-hover transition duration-300 cursor-pointer">
                                    <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                    </div>
                                    <div class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['posts']); ?></div>
                                    <div class="text-sm font-medium text-gray-500">Total Posts</div>
                                </div>
                                <div onclick="loadSection('pages')" class="bg-white p-6 rounded-3xl shadow-premium border border-gray-100 hover:shadow-premium-hover transition duration-300 cursor-pointer">
                                    <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-2xl flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
                                    </div>
                                    <div class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['pages']); ?></div>
                                    <div class="text-sm font-medium text-gray-500">Total Pages</div>
                                </div>
                                <div onclick="loadSection('users')" class="bg-white p-6 rounded-3xl shadow-premium border border-gray-100 hover:shadow-premium-hover transition duration-300 cursor-pointer">
                                    <div class="w-12 h-12 bg-green-50 text-green-600 rounded-2xl flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                    </div>
                                    <div class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['users']); ?></div>
                                    <div class="text-sm font-medium text-gray-500">Members</div>
                                </div>
                                <div onclick="loadSection('media')" class="bg-white p-6 rounded-3xl shadow-premium border border-gray-100 hover:shadow-premium-hover transition duration-300 cursor-pointer">
                                    <div class="w-12 h-12 bg-orange-50 text-orange-600 rounded-2xl flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                    <div class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['media']); ?></div>
                                    <div class="text-sm font-medium text-gray-500">Assets</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics Widget -->
                    <div class="widget widget-full-width" data-widget-id="analytics">
                        <div class="widget-header">
                            <div class="flex items-center gap-3 flex-1">
                                <span class="drag-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600">⋮⋮</span>
                                <span class="widget-title">Activity Overview</span>
                            </div>
                            <div class="widget-controls">
                                <button class="widget-minimize" title="Minimize">−</button>
                                <button class="widget-maximize hidden" title="Maximize">+</button>
                            </div>
                        </div>
                        <div class="widget-content">
                            <div class="bg-white rounded-3xl p-6 shadow-premium border border-gray-100">
                                <div class="flex items-center justify-between mb-6">
                                    <h3 class="text-lg font-bold text-gray-900">Activity Overview</h3>
                                    <select class="bg-gray-50 border-none rounded-lg text-sm px-3 py-1 font-medium">
                                        <option>Last 7 Days</option>
                                        <option>Last 30 Days</option>
                                    </select>
                                </div>
                                <div class="h-64">
                                    <canvas id="activityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Posts Widget -->
                    <div class="widget" data-widget-id="recent-posts">
                        <div class="widget-header">
                            <div class="flex items-center gap-3 flex-1">
                                <span class="drag-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600">⋮⋮</span>
                                <span class="widget-title">Recent Posts</span>
                            </div>
                            <div class="widget-controls">
                                <button class="widget-minimize" title="Minimize">−</button>
                                <button class="widget-maximize hidden" title="Maximize">+</button>
                            </div>
                        </div>
                        <div class="widget-content">
                            <div class="bg-white rounded-3xl p-6 shadow-premium border border-gray-100">
                                <h3 class="text-lg font-bold text-gray-900 mb-6">Recent Posts</h3>
                                <div class="space-y-4">
                                    <?php foreach ($recent_posts as $post): ?>
                                    <div class="flex items-start gap-3 p-3 rounded-2xl hover:bg-gray-50 transition cursor-pointer">
                                        <div class="w-10 h-10 bg-primary-100 text-primary-600 rounded-xl shrink-0 flex items-center justify-center">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2z"></path></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-sm font-bold text-gray-900 truncate"><?php echo esc_html($post['title']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($post['date'])); ?> • <?php echo esc_html($post['author']); ?></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button onclick="loadSection('posts')" class="w-full mt-6 py-3 text-sm font-bold text-primary-600 hover:text-primary-700 transition">View All Content →</button>
                            </div>
                        </div>
                    </div>

                    <!-- Addon Features Widget -->
                    <?php if (!empty($features)): ?>
                    <div class="widget" data-widget-id="features">
                        <div class="widget-header">
                            <div class="flex items-center gap-3 flex-1">
                                <span class="drag-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600">⋮⋮</span>
                                <span class="widget-title">Available Features</span>
                            </div>
                            <div class="widget-controls">
                                <button class="widget-minimize" title="Minimize">−</button>
                                <button class="widget-maximize hidden" title="Maximize">+</button>
                            </div>
                        </div>
                        <div class="widget-content">
                            <div class="bg-white rounded-3xl p-6 shadow-premium border border-gray-100">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <?php foreach ($features as $feature): ?>
                                    <div onclick="window.location.href='<?php echo esc_url($feature['url'] ?? '#'); ?>'" class="p-4 rounded-2xl bg-gray-50 hover:bg-gray-100 transition cursor-pointer border border-gray-200 hover:border-gray-300">
                                        <div class="flex items-center gap-3 mb-2">
                                            <div class="w-10 h-10 bg-primary-100 text-primary-600 rounded-xl flex items-center justify-center">
                                                <?php $this->render_feature_icon($feature['icon'] ?? 'package'); ?>
                                            </div>
                                            <h4 class="font-bold text-gray-900"><?php echo esc_html($feature['name']); ?></h4>
                                        </div>
                                        <p class="text-sm text-gray-600"><?php echo esc_html($feature['description']); ?></p>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <!-- Bottom Navigation (PWA style) -->
        <nav class="md:hidden glass fixed bottom-0 left-0 right-0 h-20 px-6 flex items-center justify-between z-50 pb-[env(safe-area-inset-bottom)]">
            <button onclick="loadSection('overview')" data-section="overview" class="mobile-nav-btn flex flex-col items-center gap-1 active-nav">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                <span class="text-[10px] font-bold uppercase tracking-wider">Home</span>
            </button>
            <button onclick="loadSection('posts')" data-section="posts" class="mobile-nav-btn flex flex-col items-center gap-1 text-gray-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                <span class="text-[10px] font-bold uppercase tracking-wider">Content</span>
            </button>
            
            
            <button onclick="loadSection('media')" data-section="media" class="mobile-nav-btn flex flex-col items-center gap-1 text-gray-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <span class="text-[10px] font-bold uppercase tracking-wider">Assets</span>
            </button>
            <button onclick="loadSection('settings')" data-section="settings" class="mobile-nav-btn flex flex-col items-center gap-1 text-gray-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                <span class="text-[10px] font-bold uppercase tracking-wider">Settings</span>
            </button>
        </nav>

        <!-- Quick Menu Modal (Mobile Only) -->
        <div id="quick-menu" class="hidden fixed inset-0 z-50 overflow-hidden">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity opacity-0" id="quick-menu-overlay"></div>
            <div class="absolute bottom-0 left-0 right-0 bg-white rounded-t-3xl p-8 transform translate-y-full transition-transform duration-300" id="quick-menu-content">
                <div class="w-12 h-1.5 bg-gray-200 rounded-full mx-auto mb-8"></div>
                <h3 class="text-xl font-bold text-gray-900 mb-6 text-center">Quick Actions</h3>
                <div class="grid grid-cols-2 gap-4">
                    <button onclick="quickAction('new-post')" class="flex flex-col items-center gap-3 p-6 rounded-2xl bg-blue-50 text-blue-600 font-bold active:scale-95 transition">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </div>
                        <span>New Post</span>
                    </button>
                    <button onclick="quickAction('new-page')" class="flex flex-col items-center gap-3 p-6 rounded-2xl bg-purple-50 text-purple-600 font-bold active:scale-95 transition">
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <span>New Page</span>
                    </button>
                    <button onclick="quickAction('upload-media')" class="flex flex-col items-center gap-3 p-6 rounded-2xl bg-orange-50 text-orange-600 font-bold active:scale-95 transition">
                        <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        </div>
                        <span>Upload Media</span>
                    </button>
                    <button onclick="quickAction('settings')" class="flex flex-col items-center gap-3 p-6 rounded-2xl bg-gray-50 text-gray-600 font-bold active:scale-95 transition">
                        <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        </div>
                        <span>Settings</span>
                    </button>
                </div>
                <button onclick="toggleQuickMenu()" class="w-full mt-8 py-4 text-gray-500 font-bold hover:text-gray-900 transition">Cancel</button>
            </div>
        </div>

        <!-- Mobile Drawer (Sidebar) -->
        <div id="mobile-sidebar" class="md:hidden fixed inset-0 z-[60] overflow-hidden pointer-events-none">
            <div class="absolute inset-0 bg-black/60 opacity-0 transition-opacity duration-300" id="sidebar-overlay"></div>
            <div class="absolute top-0 left-0 bottom-0 w-80 bg-premium-dark p-6 transform -translate-x-full transition-transform duration-300 pointer-events-auto" id="sidebar-content">
                <!-- Content same as desktop aside but slightly adapted -->
                <div class="flex items-center justify-between mb-10 px-2">
                    <div class="flex items-center justify-center flex-1">
                        <?php if (!empty($orabooks_logo)): ?>
                            <img src="<?php echo esc_url($orabooks_logo); ?>" alt="Logo" class="h-10 w-auto object-contain">
                        <?php else: ?>
                            <div class="w-8 h-8 bg-primary-500 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button onclick="toggleMobileSidebar()" class="text-gray-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <nav class="space-y-1" id="mobile-nav-container">
                    <button onclick="loadSection('overview')" data-section="overview" class="mobile-nav-btn group flex items-center w-full gap-3 px-4 py-3 rounded-xl transition bg-primary-500 text-white shadow-lg shadow-primary-500/20">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        <span class="font-medium">Overview</span>
                    </button>
                    <button onclick="loadSection('posts')" data-section="posts" class="mobile-nav-btn group flex items-center w-full gap-3 px-4 py-3 rounded-xl transition text-gray-400 hover:text-white hover:bg-white/5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        <span class="font-medium">Posts</span>
                    </button>
                    <button onclick="loadSection('pages')" data-section="pages" class="mobile-nav-btn group flex items-center w-full gap-3 px-4 py-3 rounded-xl transition text-gray-400 hover:text-white hover:bg-white/5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        <span class="font-medium">Pages</span>
                    </button>
                    <button onclick="loadSection('users')" data-section="users" class="mobile-nav-btn group flex items-center w-full gap-3 px-4 py-3 rounded-xl transition text-gray-400 hover:text-white hover:bg-white/5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        <span class="font-medium">Users</span>
                    </button>
                    <button onclick="loadSection('media')" data-section="media" class="mobile-nav-btn group flex items-center w-full gap-3 px-4 py-3 rounded-xl transition text-gray-400 hover:text-white hover:bg-white/5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span class="font-medium">Media Library</span>
                    </button>
                    <button onclick="loadSection('settings')" data-section="settings" class="mobile-nav-btn group flex items-center w-full gap-3 px-4 py-3 rounded-xl transition text-gray-400 hover:text-white hover:bg-white/5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        <span class="font-medium">Settings</span>
                    </button>
                    <button onclick="loadSection('upgrade')" data-section="upgrade" class="mobile-nav-btn group flex items-center w-full gap-3 px-4 py-3 rounded-xl transition text-gray-400 hover:text-white hover:bg-white/5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        <span class="font-medium">Upgrade Plan</span>
                    </button>
                    
                    <div class="pt-6 pb-2 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Addons</div>
                    <?php foreach ($features as $feature): ?>
                    <a href="<?php echo esc_url($feature['url'] ?? '#'); ?>" class="mobile-nav-btn group flex items-center w-full gap-3 px-4 py-3 rounded-xl transition text-gray-400 hover:text-white hover:bg-white/5">
                        <div class="w-5 h-5">
                            <?php $this->render_feature_icon($feature['icon']); ?>
                        </div>
                        <span class="font-medium"><?php echo $feature['name']; ?></span>
                    </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>

    </div>

    <!-- Modals & Toasts -->
    <div id="modal-container" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeModal()"></div>
        <div class="bg-white rounded-3xl w-full max-w-lg relative z-10 overflow-hidden shadow-2xl transform scale-95 opacity-0 transition-all duration-300" id="modal-content">
            <!-- Dynamic Modal Content -->
        </div>
    </div>

    <script>
        <?php
        // Get the correct pricing page URL based on multisite context
        $pricing_url = home_url('/orabooks-pricing');
        if (function_exists('orabooks_get_or_create_page')) {
            if (is_multisite() && get_current_blog_id() != 1) {
                $pricing_page = get_page_by_path('upgrade-plan');
                if ($pricing_page) {
                    $pricing_url = get_permalink($pricing_page->ID);
                } else {
                    $pricing_url = home_url('/upgrade-plan');
                }
            } else {
                $pricing_page = orabooks_get_or_create_page('Orabooks Pricing', '[orabooks_levels]');
                if ($pricing_page) {
                    $pricing_url = get_permalink($pricing_page->ID);
                }
            }
        }
        ?>
        var wpfdVars = {
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('wpfd_nonce'); ?>',
            paymentNonce: '<?php echo wp_create_nonce('orabooks_payment_nonce'); ?>',
            restUrl: '<?php echo esc_url_raw(rest_url('wpfd/v1')); ?>',
            restNonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
            homeUrl: '<?php echo home_url('/'); ?>',
            adminBase: '<?php echo admin_url(); ?>',
            logoutUrl: '<?php echo esc_url_raw(wp_logout_url(home_url('/'))); ?>',
            isAdmin: <?php echo current_user_can('manage_options') ? 'true' : 'false'; ?>,
            pricingUrl: '<?php echo esc_url_raw($pricing_url); ?>'
        };
    </script>
    <script src="<?php echo plugin_dir_url(__FILE__) . '../assets/js/dashboard.js?v=' . filemtime(plugin_dir_path(__FILE__) . '../assets/js/dashboard.js'); ?>" onerror="console.error('Failed to load dashboard.js');"></script>

    <script>
        // Fallback: Ensure functions are available even if Dashboard.init() fails
        if (typeof loadSection === 'undefined') {
            window.loadSection = function(section) {
                console.log('Fallback loadSection called for:', section);
                const adminPages = {
                    'posts': 'edit.php?post_type=post',
                    'pages': 'edit.php?post_type=page',
                    'users': 'users.php',
                    'media': 'upload.php',
                    'settings': 'options-general.php'
                };

                if (section === 'overview') {
                    window.location.reload();
                } else if (section === 'upgrade') {
                    const container = document.getElementById('section-content');
                    if (container) {
                        container.innerHTML = `
                            <div class="h-[calc(100vh-160px)] w-full overflow-hidden rounded-3xl bg-white shadow-premium border border-gray-100">
                                <iframe src="` + wpfdVars.pricingUrl + `?wpfd_iframe=1" class="w-full h-full border-none" id="content-iframe"></iframe>
                            </div>
                        `;
                        container.classList.remove('section-hidden');
                        const pageTitle = document.getElementById('page-title');
                        if(pageTitle) pageTitle.innerText = 'Upgrade Plan';
                    } else {
                        window.location.href = wpfdVars.pricingUrl;
                    }
                    return;
                } else if (adminPages[section]) {
                    if (typeof window.openAdminPage === 'function') {
                        window.openAdminPage(adminPages[section]);
                    } else {
                        showFallbackModal('<?php echo admin_url(); ?>' + adminPages[section], section);
                    }
                } else {
                    console.warn('Unknown section:', section);
                }
            };
        }

        if (typeof quickAction === 'undefined') {
            window.quickAction = function(action) {
                console.log('Fallback quickAction called for:', action);
                const urls = {
                    'new-post': 'post-new.php',
                    'new-page': 'post-new.php?post_type=page',
                    'upload-media': 'media-new.php',
                    'new-user': 'user-new.php',
                    'settings': 'options-general.php'
                };
                if (urls[action]) {
                    if (typeof window.openAdminPage === 'function') {
                        window.openAdminPage(urls[action]);
                    } else {
                        showFallbackModal('<?php echo admin_url(); ?>' + urls[action], action);
                    }
                }
            };
        }

        function showFallbackModal(url, title) {
            const modal = document.getElementById('modal-container');
            const content = document.getElementById('modal-content');
            if (modal && content) {
                content.className = "bg-white rounded-3xl w-full max-w-6xl h-[90vh] relative z-10 overflow-hidden shadow-2xl transform scale-100 opacity-100 transition-all duration-300 flex flex-col";
                content.innerHTML = `
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center shrink-0 bg-white">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 uppercase">` + title + `</h3>
                        </div>
                        <button onclick="document.getElementById('modal-container').classList.add('hidden'); document.getElementById('modal-container').classList.remove('flex');" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Close">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <div class="flex-1 overflow-hidden bg-gray-50">
                        <iframe src="` + url + `" class="w-full h-full border-none bg-white"></iframe>
                    </div>
                `;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            } else {
                window.location.href = url;
            }
        }

        if (typeof toggleMobileSidebar === 'undefined') {
            window.toggleMobileSidebar = function() {
                console.log('Fallback toggleMobileSidebar called');
                alert('Mobile sidebar toggle not available');
            };
        }

        if (typeof toggleQuickMenu === 'undefined') {
            window.toggleQuickMenu = function() {
                console.log('Fallback toggleQuickMenu called');
                alert('Quick menu toggle not available');
            };
        }

        console.log('Fallback functions installed');
        // Debug: Check if jQuery loaded
        console.log('Dashboard Debug:');
        console.log('jQuery loaded:', typeof jQuery !== 'undefined');
        console.log('$ loaded:', typeof $ !== 'undefined');
        console.log('wpfdVars defined:', typeof wpfdVars !== 'undefined');
        console.log('Dashboard object defined:', typeof Dashboard !== 'undefined');
        console.log('loadSection function defined:', typeof loadSection !== 'undefined');

        // Debug: Test button click handlers
        if (typeof loadSection === 'function') {
            console.log('loadSection is available');
        } else {
            console.error('loadSection is NOT available - buttons will not work');
        }

        if (typeof quickAction === 'function') {
            console.log('quickAction is available');
        } else {
            console.error('quickAction is NOT available - quick action buttons will not work');
        }

        // Inline chart init
        const ctx = document.getElementById('activityChart')?.getContext('2d');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Page Views',
                        data: [1200, 1900, 1500, 2100, 2400, 1800, 2200],
                        borderColor: '#0ea5e9',
                        backgroundColor: 'rgba(14, 165, 233, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 4,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#0ea5e9',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { display: false, beginAtZero: true },
                        x: { grid: { display: false }, ticks: { font: { weight: '600' } } }
                    }
                }
            });
        }

        // Widget functionality
        function initWidgets() {
            const widgetsContainer = document.getElementById('dashboard-widgets');
            if (!widgetsContainer) return;

            const widgets = document.querySelectorAll('.widget');
            
            // Minimize/Maximize functionality
            widgets.forEach(widget => {
                const minimizeBtn = widget.querySelector('.widget-minimize');
                const maximizeBtn = widget.querySelector('.widget-maximize');
                
                if (minimizeBtn) {
                    minimizeBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        widget.classList.add('minimized');
                        minimizeBtn.classList.add('hidden');
                        maximizeBtn.classList.remove('hidden');
                        saveWidgetState();
                    });
                }
                
                if (maximizeBtn) {
                    maximizeBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        widget.classList.remove('minimized');
                        maximizeBtn.classList.add('hidden');
                        minimizeBtn.classList.remove('hidden');
                        saveWidgetState();
                    });
                }
            });

            // Drag and drop functionality
            let draggedWidget = null;
            let placeholder = null;
            let dragOffsetX = 0;
            let dragOffsetY = 0;

            widgets.forEach(widget => {
                const header = widget.querySelector('.widget-header');
                if (!header) return;

                header.addEventListener('mousedown', (e) => {
                    // Don't drag if clicking a control button
                    if (e.target.closest('.widget-controls button')) return;

                    e.preventDefault();

                    draggedWidget = widget;
                    const rect = widget.getBoundingClientRect();
                    dragOffsetX = e.clientX - rect.left;
                    dragOffsetY = e.clientY - rect.top;

                    // Create placeholder to maintain layout
                    placeholder = document.createElement('div');
                    placeholder.className = 'widget-placeholder';
                    placeholder.style.height = rect.height + 'px';
                    widget.parentNode.insertBefore(placeholder, widget.nextSibling);

                    // Float the widget for dragging
                    widget.style.position = 'fixed';
                    widget.style.width = rect.width + 'px';
                    widget.style.left = rect.left + 'px';
                    widget.style.top = rect.top + 'px';
                    widget.style.zIndex = '1000';
                    widget.classList.add('dragging');

                    // Prevent scrolling during drag
                    const scroller = document.getElementById('main-scroller');
                    if (scroller) scroller.style.overflow = 'hidden';
                    document.body.style.userSelect = 'none';
                });
            });

            document.addEventListener('mousemove', (e) => {
                if (!draggedWidget) return;

                draggedWidget.style.left = (e.clientX - dragOffsetX) + 'px';
                draggedWidget.style.top = (e.clientY - dragOffsetY) + 'px';

                // Highlight drop target
                const others = Array.from(widgetsContainer.querySelectorAll('.widget')).filter(w => w !== draggedWidget);

                for (const widget of others) {
                    const rect = widget.getBoundingClientRect();
                    const centerY = rect.top + rect.height / 2;
                    widget.classList.remove('drag-over');

                    if (e.clientY < centerY) {
                        widget.classList.add('drag-over');
                        break;
                    }
                }
            });

            document.addEventListener('mouseup', (e) => {
                if (!draggedWidget) return;

                // Find drop target
                let dropTarget = null;
                const others = Array.from(widgetsContainer.querySelectorAll('.widget')).filter(w => w !== draggedWidget);

                for (const widget of others) {
                    if (widget.classList.contains('drag-over')) {
                        dropTarget = widget;
                    }
                    widget.classList.remove('drag-over');
                }

                if (dropTarget) {
                    widgetsContainer.insertBefore(draggedWidget, dropTarget);
                } else if (others.length > 0) {
                    widgetsContainer.appendChild(draggedWidget);
                }

                saveWidgetOrder();

                // Reset styles
                draggedWidget.style.position = '';
                draggedWidget.style.width = '';
                draggedWidget.style.left = '';
                draggedWidget.style.top = '';
                draggedWidget.style.zIndex = '';
                draggedWidget.classList.remove('dragging');

                // Remove placeholder
                if (placeholder && placeholder.parentNode) {
                    placeholder.parentNode.removeChild(placeholder);
                }
                placeholder = null;

                const scroller = document.getElementById('main-scroller');
                if (scroller) scroller.style.overflow = '';
                document.body.style.userSelect = '';
                draggedWidget = null;
            });

            // Load saved widget state
            loadWidgetState();
            
            console.log('Widgets initialized:', widgets.length);
        }

        function saveWidgetState() {
            const widgets = document.querySelectorAll('.widget');
            const state = [];
            widgets.forEach(widget => {
                state.push({
                    id: widget.dataset.widgetId,
                    minimized: widget.classList.contains('minimized')
                });
            });
            localStorage.setItem('dashboardWidgetState', JSON.stringify(state));
        }

        function saveWidgetOrder() {
            const widgets = document.querySelectorAll('.widget');
            const order = [];
            widgets.forEach(widget => {
                order.push(widget.dataset.widgetId);
            });
            localStorage.setItem('dashboardWidgetOrder', JSON.stringify(order));
        }

        function loadWidgetState() {
            const state = localStorage.getItem('dashboardWidgetState');
            const order = localStorage.getItem('dashboardWidgetOrder');
            
            if (state) {
                const widgetState = JSON.parse(state);
                widgetState.forEach(item => {
                    const widget = document.querySelector(`[data-widget-id="${item.id}"]`);
                    if (widget && item.minimized) {
                        widget.classList.add('minimized');
                        const minimizeBtn = widget.querySelector('.widget-minimize');
                        const maximizeBtn = widget.querySelector('.widget-maximize');
                        if (minimizeBtn) minimizeBtn.classList.add('hidden');
                        if (maximizeBtn) maximizeBtn.classList.remove('hidden');
                    }
                });
            }

            if (order) {
                const widgetOrder = JSON.parse(order);
                const widgetsContainer = document.getElementById('dashboard-widgets');
                const widgets = {};
                document.querySelectorAll('.widget').forEach(widget => {
                    widgets[widget.dataset.widgetId] = widget;
                });
                
                widgetOrder.forEach(widgetId => {
                    if (widgets[widgetId]) {
                        widgetsContainer.appendChild(widgets[widgetId]);
                    }
                });
            }
        }

        // Initialize widgets on page load
        document.addEventListener('DOMContentLoaded', initWidgets);
    </script>
</body>
</html>
